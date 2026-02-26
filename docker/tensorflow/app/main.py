"""
SIMP - Microserviço de Predição e Análise
API REST para predição de valores e detecção de anomalias
em dados de macromedição de água.

v2.0 - XGBoost via SIMP:
  - Predição via XGBoost (correlação de rede com tags auxiliares)
  - Dados buscados diretamente do banco SIMP
  - Sem dependência do banco FINDESLAB
  - Compatível com modelos LSTM legados (v1-v4)

Endpoints:
    GET  /health              - Health check
    POST /api/predict          - Predição de valores por hora (XGBoost/LSTM/fallback)
    POST /api/anomalies        - Detecção de anomalias (Autoencoder)
    POST /api/correlate        - Correlação entre pontos
    POST /api/train            - Treinar/retreinar modelo para um ponto
    GET  /api/model-status     - Status dos modelos treinados

@author Bruno - CESAN
@version 2.0
@date 2026-02
"""

import os
import json
import logging
import threading
import uuid
import subprocess
from datetime import datetime

from flask import Flask, request, jsonify
from dotenv import load_dotenv

# Módulos internos
from app.database import DatabaseManager
from app.predictor import TimeSeriesPredictor
from app.correlator import PointCorrelator

try:
    from app.anomaly_detector import AnomalyDetector
except ImportError:
    AnomalyDetector = None

# ============================================
# Configuração
# ============================================
load_dotenv()

app = Flask(__name__)

# Configurar logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(name)s: %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S'
)
logger = logging.getLogger('simp-tensorflow')

# Diretório para salvar modelos treinados
MODELS_DIR = os.environ.get('MODELS_DIR', '/app/models')
os.makedirs(MODELS_DIR, exist_ok=True)

# Instâncias dos serviços
db = DatabaseManager()
predictor = TimeSeriesPredictor(MODELS_DIR)
anomaly_detector = AnomalyDetector(MODELS_DIR) if AnomalyDetector else None
correlator = PointCorrelator()

# ============================================
# Sistema de Fila de Treinamento
# ============================================

QUEUE_FILE = os.path.join(MODELS_DIR, '_train_queue.json')
PROGRESS_FILE = os.path.join(MODELS_DIR, '_train_all_progress.json')
QUEUE_LOCK = threading.Lock()


def _ler_fila():
    """Lê a fila de treinamento do arquivo JSON."""
    if not os.path.exists(QUEUE_FILE):
        return []
    try:
        with open(QUEUE_FILE, 'r') as f:
            return json.load(f)
    except Exception:
        return []


def _salvar_fila(fila):
    """Salva a fila de treinamento no arquivo JSON."""
    with open(QUEUE_FILE, 'w') as f:
        json.dump(fila, f, ensure_ascii=False)


def _treino_em_execucao():
    """Verifica se há um treinamento em execução."""
    if not os.path.exists(PROGRESS_FILE):
        return False
    try:
        with open(PROGRESS_FILE, 'r') as f:
            prog = json.load(f)
        return prog.get('status') == 'running'
    except Exception:
        return False


def _verificar_e_enfileirar(tipo, params, usuario=None):
    """
    Thread-safe: verifica se há treino em execução.
    Se sim, adiciona na fila e retorna (job_id, posicao).
    Se não, retorna (None, None) → o chamador deve iniciar o treino.
    """
    with QUEUE_LOCK:
        if _treino_em_execucao():
            job_id = str(uuid.uuid4())[:8]
            item = {
                'job_id': job_id,
                'tipo': tipo,
                'params': params,
                'usuario': usuario,
                'criado_em': datetime.now().isoformat()
            }
            fila = _ler_fila()
            fila.append(item)
            _salvar_fila(fila)
            posicao = len(fila)
            logger.info(f"[Fila] Adicionado job {job_id} ({tipo}) na posição {posicao}")
            return job_id, posicao
        else:
            return None, None


def _processar_proxima_fila():
    """
    Verifica se há itens na fila e inicia o próximo treinamento.
    Chamado após cada treinamento finalizar.
    Escreve status 'running' dentro do lock para evitar race condition.
    """
    proximo = None
    with QUEUE_LOCK:
        if _treino_em_execucao():
            return

        fila = _ler_fila()
        if not fila:
            return

        proximo = fila.pop(0)
        _salvar_fila(fila)

        # Marcar como 'running' DENTRO do lock para evitar que outro request
        # inicie um treino paralelo no intervalo entre pop e start
        reserva = {
            'job_id': proximo['job_id'],
            'status': 'running',
            'tipo': proximo['tipo'],
            'message': 'Iniciando treino da fila...',
            'inicio': datetime.now().isoformat(),
            'fim': None,
            'sucesso': 0,
            'falha': 0,
            'total': 0,
            'ponto_atual': None,
            'resumo': '',
            'fila': fila
        }
        with open(PROGRESS_FILE, 'w') as f:
            json.dump(reserva, f, ensure_ascii=False)

    logger.info(f"[Fila] Processando próximo: job {proximo['job_id']} ({proximo['tipo']})")

    if proximo['tipo'] == 'train_all':
        _iniciar_treino_todos_background(
            semanas=proximo['params'].get('semanas', 24),
            job_id=proximo['job_id'],
            modo=proximo['params'].get('modo', 'fixo')
        )
    elif proximo['tipo'] == 'train_single':
        _iniciar_treino_single_background(
            cd_ponto=proximo['params']['cd_ponto'],
            tag_principal=proximo['params']['tag_principal'],
            semanas=proximo['params'].get('semanas', 24),
            job_id=proximo['job_id']
        )


def _iniciar_treino_todos_background(semanas, job_id, modo='fixo'):
    """Inicia o treino de todos os pontos em thread separada."""
    script_path = os.environ.get('TREINAR_SCRIPT', '/app/treinar_modelos.py')
    progress_file = PROGRESS_FILE

    # Gravar status inicial
    progress_data = {
        'job_id': job_id,
        'status': 'running',
        'tipo': 'train_all',
        'message': 'Iniciando treino de todos os pontos...',
        'semanas': semanas,
        'inicio': datetime.now().isoformat(),
        'fim': None,
        'sucesso': 0,
        'falha': 0,
        'total': 0,
        'ponto_atual': None,
        'resumo': '',
        'fila': _ler_fila()
    }
    with open(progress_file, 'w') as f:
        json.dump(progress_data, f, ensure_ascii=False)

    def _executar():
        try:
            cmd = [
                'python3', script_path,
                '--semanas', str(semanas),
                '--output', MODELS_DIR,
                '--modo', modo
            ]
            logger.info(f"[Job {job_id}] Train-all iniciado: {' '.join(cmd)}")

            process = subprocess.Popen(
                cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                text=True,
                bufsize=1
            )

            sucesso = 0
            falha = 0
            total = 0
            ponto_atual = None

            for linha in iter(process.stdout.readline, ''):
                linha = linha.strip()
                if not linha:
                    continue

                if 'Treinando ponto' in linha or 'Processando' in linha:
                    ponto_atual = linha
                if 'SUCESSO' in linha.upper() or 'salvo' in linha.lower():
                    sucesso += 1
                elif 'FALHA' in linha.upper() or 'ERRO' in linha.upper():
                    falha += 1
                if 'Total:' in linha:
                    try:
                        total = int(''.join(filter(str.isdigit, linha.split('Total:')[1].split()[0])))
                    except Exception:
                        pass

                try:
                    prog = {
                        'job_id': job_id,
                        'status': 'running',
                        'tipo': 'train_all',
                        'message': ponto_atual or linha,
                        'semanas': semanas,
                        'inicio': progress_data['inicio'],
                        'fim': None,
                        'sucesso': sucesso,
                        'falha': falha,
                        'total': total if total > 0 else sucesso + falha,
                        'ponto_atual': ponto_atual,
                        'resumo': '',
                        'fila': _ler_fila()
                    }
                    with open(progress_file, 'w') as f:
                        json.dump(prog, f, ensure_ascii=False)
                except Exception:
                    pass

            process.wait()
            retcode = process.returncode
            resumo = f'Sucesso: {sucesso} | Falha: {falha} | Total: {sucesso + falha}'

            try:
                predictor.models.clear()
            except Exception:
                pass

            status_final = 'completed' if retcode == 0 else 'error'
            prog_final = {
                'job_id': job_id,
                'status': status_final,
                'tipo': 'train_all',
                'message': f'Treino finalizado. {resumo}' if retcode == 0 else f'Treino encerrado com erros (código {retcode})',
                'semanas': semanas,
                'inicio': progress_data['inicio'],
                'fim': datetime.now().isoformat(),
                'sucesso': sucesso,
                'falha': falha,
                'total': sucesso + falha,
                'ponto_atual': None,
                'resumo': resumo,
                'fila': _ler_fila()
            }
            with open(progress_file, 'w') as f:
                json.dump(prog_final, f, ensure_ascii=False)

            logger.info(f"[Job {job_id}] Train-all finalizado: {resumo}")

        except Exception as e:
            logger.error(f"[Job {job_id}] Erro no treino background: {e}", exc_info=True)
            try:
                prog_erro = {
                    'job_id': job_id,
                    'status': 'error',
                    'tipo': 'train_all',
                    'message': str(e),
                    'semanas': semanas,
                    'inicio': progress_data['inicio'],
                    'fim': datetime.now().isoformat(),
                    'sucesso': 0,
                    'falha': 0,
                    'total': 0,
                    'ponto_atual': None,
                    'resumo': f'Erro: {str(e)}',
                    'fila': _ler_fila()
                }
                with open(progress_file, 'w') as f:
                    json.dump(prog_erro, f, ensure_ascii=False)
            except Exception:
                pass
        finally:
            # Processar próximo da fila
            _processar_proxima_fila()

    thread = threading.Thread(target=_executar, daemon=True)
    thread.start()


def _iniciar_treino_single_background(cd_ponto, tag_principal, semanas, job_id):
    """Inicia treino de ponto único em thread separada (quando enfileirado)."""
    script_path = os.environ.get('TREINAR_SCRIPT', '/app/treinar_modelos.py')
    progress_file = PROGRESS_FILE

    # Gravar status inicial
    progress_data = {
        'job_id': job_id,
        'status': 'running',
        'tipo': 'train_single',
        'message': f'Treinando ponto {cd_ponto} ({tag_principal})...',
        'semanas': semanas,
        'inicio': datetime.now().isoformat(),
        'fim': None,
        'sucesso': 0,
        'falha': 0,
        'total': 1,
        'ponto_atual': f'Ponto {cd_ponto} ({tag_principal})',
        'resumo': '',
        'fila': _ler_fila()
    }
    with open(progress_file, 'w') as f:
        json.dump(progress_data, f, ensure_ascii=False)

    def _executar():
        try:
            cmd = [
                'python3', script_path,
                '--tag', tag_principal,
                '--semanas', str(semanas),
                '--output', MODELS_DIR
            ]
            logger.info(f"[Job {job_id}] Train-single iniciado: {' '.join(cmd)}")

            result = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                timeout=600
            )

            if result.returncode == 0 and 'Sucesso: 0' not in result.stdout:
                # Sucesso
                try:
                    if cd_ponto in predictor.models:
                        del predictor.models[cd_ponto]
                    predictor._load_model(cd_ponto)
                except Exception:
                    pass

                metricas = predictor._load_metrics(cd_ponto)
                r2 = metricas.get('r2', 0) if metricas else 0

                prog_final = {
                    'job_id': job_id,
                    'status': 'completed',
                    'tipo': 'train_single',
                    'message': f'Modelo treinado com sucesso para ponto {cd_ponto} (R²={r2:.4f})',
                    'semanas': semanas,
                    'inicio': progress_data['inicio'],
                    'fim': datetime.now().isoformat(),
                    'sucesso': 1,
                    'falha': 0,
                    'total': 1,
                    'ponto_atual': None,
                    'resumo': f'Ponto {cd_ponto}: R²={r2:.4f}',
                    'fila': _ler_fila()
                }
            else:
                detalhe = result.stderr[-300:] if result.stderr else result.stdout[-300:]
                prog_final = {
                    'job_id': job_id,
                    'status': 'error',
                    'tipo': 'train_single',
                    'message': f'Erro no treino do ponto {cd_ponto}',
                    'semanas': semanas,
                    'inicio': progress_data['inicio'],
                    'fim': datetime.now().isoformat(),
                    'sucesso': 0,
                    'falha': 1,
                    'total': 1,
                    'ponto_atual': None,
                    'resumo': detalhe,
                    'fila': _ler_fila()
                }

            with open(progress_file, 'w') as f:
                json.dump(prog_final, f, ensure_ascii=False)

            logger.info(f"[Job {job_id}] Train-single finalizado: {prog_final['resumo']}")

        except subprocess.TimeoutExpired:
            prog_erro = {
                'job_id': job_id,
                'status': 'error',
                'tipo': 'train_single',
                'message': f'Timeout: treino do ponto {cd_ponto} excedeu 10 minutos',
                'semanas': semanas,
                'inicio': progress_data['inicio'],
                'fim': datetime.now().isoformat(),
                'sucesso': 0,
                'falha': 1,
                'total': 1,
                'ponto_atual': None,
                'resumo': 'Timeout',
                'fila': _ler_fila()
            }
            with open(progress_file, 'w') as f:
                json.dump(prog_erro, f, ensure_ascii=False)
        except Exception as e:
            logger.error(f"[Job {job_id}] Erro no treino single: {e}", exc_info=True)
            try:
                prog_erro = {
                    'job_id': job_id,
                    'status': 'error',
                    'tipo': 'train_single',
                    'message': str(e),
                    'semanas': semanas,
                    'inicio': progress_data['inicio'],
                    'fim': datetime.now().isoformat(),
                    'sucesso': 0,
                    'falha': 1,
                    'total': 1,
                    'ponto_atual': None,
                    'resumo': f'Erro: {str(e)}',
                    'fila': _ler_fila()
                }
                with open(progress_file, 'w') as f:
                    json.dump(prog_erro, f, ensure_ascii=False)
            except Exception:
                pass
        finally:
            # Processar próximo da fila
            _processar_proxima_fila()

    thread = threading.Thread(target=_executar, daemon=True)
    thread.start()


# ============================================
# Endpoints
# ============================================

@app.route('/health', methods=['GET'])
def health():
    """Health check - verifica se o serviço está operacional."""
    return jsonify({
        'status': 'ok',
        'service': 'simp-tensorflow',
        'version': '2.0',
        'timestamp': datetime.now().isoformat(),
        'tensorflow': AnomalyDetector is not None,
        'xgboost': True,
        'database': db.test_connection()
    })


@app.route('/api/predict', methods=['POST'])
def predict():
    """
    Predição de valores horários usando XGBoost (v5+) ou LSTM legado (v1-v4).
    Fallback estatístico se não houver modelo treinado.
    
    Recebe:
        cd_ponto (int): Código do ponto de medição
        data (str): Data alvo no formato YYYY-MM-DD
        horas (list[int], opcional): Lista de horas específicas [0-23]
        semanas_historico (int, opcional): Semanas de histórico (default: 12)
        tipo_medidor (int, opcional): 1=vazão, 2=pressão, 3=nível
    
    Retorna:
        predicoes (list): Lista com hora, valor_predito, confianca, metodo
        formula (str): Descrição da fórmula/modelo utilizado
        metricas (dict): Métricas do modelo (MAE, RMSE, R²)
        modelo (str): Tipo do modelo usado (xgboost, lstm, statistical_fallback)
    """
    try:
        dados = request.get_json()

        if not dados:
            return jsonify({'success': False, 'error': 'JSON não recebido'}), 400
        
        cd_ponto = dados.get('cd_ponto')
        data = dados.get('data')
        horas = dados.get('horas', list(range(24)))
        semanas = dados.get('semanas_historico', 12)
        tipo_medidor = dados.get('tipo_medidor', 1)
        
        if not cd_ponto or not data:
            return jsonify({'success': False, 'error': 'cd_ponto e data são obrigatórios'}), 400
        
        logger.info(f"Predição solicitada: ponto={cd_ponto}, data={data}, horas={horas}")
        
        # Buscar histórico do ponto no banco SIMP
        historico = db.get_historico_horario(cd_ponto, data, semanas, tipo_medidor)
        
        if historico is None or len(historico) < 168:  # Mínimo 1 semana de dados horários
            return jsonify({
                'success': False,
                'error': f'Histórico insuficiente: {len(historico) if historico is not None else 0} registros. Mínimo: 168 (1 semana).',
                'fallback': 'media_historica'
            }), 200
        
        # Executar predição (XGBoost, LSTM legado ou fallback estatístico)
        resultado = predictor.predict(
            cd_ponto=cd_ponto,
            historico=historico,
            data_alvo=data,
            horas=horas,
            tipo_medidor=tipo_medidor
        )
        
        return jsonify({
            'success': True,
            'predicoes': resultado['predicoes'],
            'formula': resultado['formula'],
            'metricas': resultado['metricas'],
            'modelo': resultado['modelo'],
            'dados_utilizados': len(historico)
        })
        
    except Exception as e:
        logger.error(f"Erro na predição: {str(e)}", exc_info=True)
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/api/anomalies', methods=['POST'])
def detect_anomalies():
    """
    Detecção de anomalias usando Autoencoder.
    
    Recebe:
        cd_ponto (int): Código do ponto de medição
        data (str): Data para análise no formato YYYY-MM-DD
        tipo_medidor (int, opcional): 1=vazão, 2=pressão, 3=nível
        sensibilidade (float, opcional): Limiar de anomalia 0.0-1.0 (default: 0.8)
    
    Retorna:
        anomalias (list): Lista com hora, valor_real, valor_esperado, score, tipo_anomalia
        resumo (str): Texto resumido das anomalias encontradas
    """
    try:
        dados = request.get_json()
        
        if not dados:
            return jsonify({'success': False, 'error': 'JSON não recebido'}), 400
        
        # Anomaly detector requer TensorFlow
        if anomaly_detector is None:
            return jsonify({
                'success': False,
                'error': 'Detector de anomalias indisponível (TensorFlow não instalado)'
            }), 503
        
        cd_ponto = dados.get('cd_ponto')
        data = dados.get('data')
        tipo_medidor = dados.get('tipo_medidor', 1)
        sensibilidade = dados.get('sensibilidade', 0.8)
        
        if not cd_ponto or not data:
            return jsonify({'success': False, 'error': 'cd_ponto e data são obrigatórios'}), 400
        
        logger.info(f"Detecção de anomalias: ponto={cd_ponto}, data={data}")
        
        # Buscar dados do dia e histórico
        dados_dia = db.get_dados_dia(cd_ponto, data, tipo_medidor)
        historico = db.get_historico_horario(cd_ponto, data, 12, tipo_medidor)
        
        if dados_dia is None or len(dados_dia) == 0:
            return jsonify({
                'success': False,
                'error': 'Sem dados para o dia informado'
            }), 200
        
        # Detectar anomalias
        resultado = anomaly_detector.detect(
            cd_ponto=cd_ponto,
            dados_dia=dados_dia,
            historico=historico,
            sensibilidade=sensibilidade,
            tipo_medidor=tipo_medidor
        )
        
        return jsonify({
            'success': True,
            'anomalias': resultado['anomalias'],
            'resumo': resultado['resumo'],
            'score_geral': resultado['score_geral'],
            'total_anomalias': resultado['total_anomalias']
        })
        
    except Exception as e:
        logger.error(f"Erro na detecção de anomalias: {str(e)}", exc_info=True)
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/api/correlate', methods=['POST'])
def correlate_points():
    """
    Análise de correlação entre pontos de medição.
    Identifica relações e sugere fórmulas de substituição.
    
    Recebe:
        cd_ponto_origem (int): Ponto com dados faltantes
        cd_pontos_candidatos (list[int]): Pontos para buscar correlação
        data_inicio (str): Início do período de análise
        data_fim (str): Fim do período de análise
        tipo_medidor (int, opcional): Tipo de medidor
    
    Retorna:
        correlacoes (list): Ponto, coeficiente R², fórmula sugerida
        melhor_formula (str): Fórmula recomendada
    """
    try:
        dados = request.get_json()
        
        if not dados:
            return jsonify({'success': False, 'error': 'JSON não recebido'}), 400
        
        cd_ponto_origem = dados.get('cd_ponto_origem')
        cd_pontos_candidatos = dados.get('cd_pontos_candidatos', [])
        data_inicio = dados.get('data_inicio')
        data_fim = dados.get('data_fim')
        tipo_medidor = dados.get('tipo_medidor', 1)
        
        if not cd_ponto_origem or not data_inicio or not data_fim:
            return jsonify({'success': False, 'error': 'cd_ponto_origem, data_inicio e data_fim são obrigatórios'}), 400
        
        # Se não informou candidatos, buscar pontos da mesma localidade
        if not cd_pontos_candidatos:
            cd_pontos_candidatos = db.get_pontos_mesma_localidade(cd_ponto_origem)
        
        if not cd_pontos_candidatos:
            return jsonify({
                'success': False,
                'error': 'Nenhum ponto candidato encontrado para correlação'
            }), 200
        
        logger.info(f"Correlação: origem={cd_ponto_origem}, candidatos={cd_pontos_candidatos}")
        
        # Buscar dados de todos os pontos
        dados_pontos = {}
        for cd_ponto in [cd_ponto_origem] + cd_pontos_candidatos:
            dados_pontos[cd_ponto] = db.get_dados_periodo(
                cd_ponto, data_inicio, data_fim, tipo_medidor
            )
        
        # Calcular correlações
        resultado = correlator.analyze(
            cd_ponto_origem=cd_ponto_origem,
            dados_pontos=dados_pontos
        )
        
        return jsonify({
            'success': True,
            'correlacoes': resultado['correlacoes'],
            'melhor_formula': resultado['melhor_formula'],
            'melhor_r2': resultado['melhor_r2']
        })
        
    except Exception as e:
        logger.error(f"Erro na correlação: {str(e)}", exc_info=True)
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/api/train', methods=['POST'])
def train():
    """
    Treinar modelo XGBoost via treinar_modelos.py (subprocess).

    Se já houver treino em execução, adiciona na fila e retorna posição.
    Caso contrário, inicia o treino em background e retorna imediatamente.
    """
    try:
        dados = request.get_json()
        cd_ponto = dados.get('cd_ponto')
        semanas = dados.get('semanas', 24)
        tipo_medidor = dados.get('tipo_medidor', 1)
        force = dados.get('force', False)
        usuario = dados.get('usuario', None)

        if not cd_ponto:
            return jsonify({'success': False, 'error': 'cd_ponto é obrigatório'}), 400

        # Verificar se já existe modelo (e não é force)
        if not force and predictor.has_model(cd_ponto):
            return jsonify({
                'success': True,
                'message': 'Modelo já existe. Use force=true para retreinar.',
                'model_info': predictor.get_model_info(cd_ponto)
            })

        # Buscar TAG principal do ponto no banco
        tag_principal = _buscar_tag_ponto(cd_ponto, tipo_medidor)

        if not tag_principal:
            return jsonify({
                'success': False,
                'error': f'Ponto {cd_ponto} não possui TAG configurada para o tipo de medidor {tipo_medidor}'
            }), 200

        script_path = os.environ.get('TREINAR_SCRIPT', '/app/treinar_modelos.py')
        if not os.path.exists(script_path):
            return jsonify({
                'success': False,
                'error': f'Script de treino não encontrado: {script_path}.'
            }), 500

        # Thread-safe: verificar se há treino em execução → enfileirar ou iniciar
        queued_job_id, posicao = _verificar_e_enfileirar('train_single', {
            'cd_ponto': cd_ponto,
            'tag_principal': tag_principal,
            'semanas': semanas,
            'tipo_medidor': tipo_medidor
        }, usuario)

        if queued_job_id:
            return jsonify({
                'success': True,
                'queued': True,
                'job_id': queued_job_id,
                'posicao_fila': posicao,
                'message': f'Treino adicionado na fila (posição {posicao}). Iniciará automaticamente.'
            })

        # Nenhum treino em execução → iniciar agora em background
        job_id = str(uuid.uuid4())[:8]
        _iniciar_treino_single_background(cd_ponto, tag_principal, semanas, job_id)

        return jsonify({
            'success': True,
            'queued': False,
            'job_id': job_id,
            'message': f'Treino iniciado para ponto {cd_ponto} ({tag_principal})'
        })

    except Exception as e:
        logger.error(f"Erro no treinamento: {str(e)}", exc_info=True)
        return jsonify({'success': False, 'error': str(e)}), 500


def _buscar_tag_ponto(cd_ponto: int, tipo_medidor: int = 1) -> str:
    """
    Busca a TAG principal do ponto de medição no banco SIMP.
    
    Mapeia o tipo de medidor para o campo correto:
      1,2,8 → DS_TAG_VAZAO
      4     → DS_TAG_PRESSAO  
      6     → DS_TAG_RESERVATORIO
    
    Returns:
        TAG principal (string) ou None
    """
    campo_tag = {
        1: 'DS_TAG_VAZAO',
        2: 'DS_TAG_VAZAO',
        4: 'DS_TAG_PRESSAO',
        6: 'DS_TAG_RESERVATORIO',
        8: 'DS_TAG_VAZAO'
    }.get(tipo_medidor, 'DS_TAG_VAZAO')
    
    conn = db._get_connection()
    if not conn:
        return None
    
    try:
        cursor = conn.cursor()
        cursor.execute(
            f"SELECT {campo_tag} FROM PONTO_MEDICAO WHERE CD_PONTO_MEDICAO = ?",
            (cd_ponto,)
        )
        row = cursor.fetchone()
        if row and row[0]:
            return row[0].strip()
        return None
    finally:
        conn.close()


def _treinar_via_script(tag_principal: str, semanas: int) -> dict:
    """
    Treina modelo completo usando as funções do treinar_modelos.py.
    
    Importa diretamente (sem subprocess) para:
      - Conectar ao FINDESLAB (Wonderware Historian via linked server)
      - Buscar tags auxiliares (RELACAO_TAGS)
      - Montar features com correlação de rede (44+ features)
      - Treinar XGBoost com métricas completas
    
    Args:
        tag_principal: TAG do ponto (ex: GPRS050_M010_MED)
        semanas: Semanas de histórico
    
    Returns:
        dict com success e error (se houver)
    """
    import sys
    
    # Garantir que o treinar_modelos.py está importável
    script_path = os.environ.get('TREINAR_SCRIPT', '/app/treinar_modelos.py')
    script_dir = os.path.dirname(script_path)
    if script_dir not in sys.path:
        sys.path.insert(0, script_dir)
    
    try:
        
        # Importar funções do treinar_modelos.py
        from treinar_modelos import (
            conectar_banco,
            buscar_relacoes,
            buscar_pontos_medicao,
            buscar_dados_tags,
            preparar_dados_treino,
            treinar_modelo,
            salvar_modelo
        )
        
        # Sobrescrever OUTPUT_DIR do treinar_modelos para usar o diretório do container
        import treinar_modelos
        treinar_modelos.OUTPUT_DIR = MODELS_DIR
        
    except ImportError as e:
        return {
            'success': False,
            'error': f'Não foi possível importar treinar_modelos: {e}. Verifique se o arquivo está montado em {script_path}'
        }
    
    try:
        # 1. Conectar ao banco (SIMP + FINDESLAB via linked server)
        conn = conectar_banco()
        
        # 2. Buscar relações (tags auxiliares) para a tag principal
        relacoes = buscar_relacoes(conn, tag_principal)
        if not relacoes or tag_principal not in relacoes:
            conn.close()
            return {
                'success': False,
                'error': f'Nenhuma relação de tags encontrada para {tag_principal}'
            }
        
        tags_auxiliares = relacoes[tag_principal]
        logger.info(f"  Tags auxiliares encontradas: {len(tags_auxiliares)}")
        
        # 3. Buscar mapeamento TAG → CD_PONTO_MEDICAO / TIPO_MEDIDOR
        pontos_df = buscar_pontos_medicao(conn)
        tag_to_ponto = {}
        tag_to_tipo = {}
        for _, row in pontos_df.iterrows():
            tag = row['TAG'].strip() if row['TAG'] else ''
            tag_to_ponto[tag] = int(row['CD_PONTO_MEDICAO'])
            tag_to_tipo[tag] = int(row['ID_TIPO_MEDIDOR'])
        
        cd_ponto = tag_to_ponto.get(tag_principal)
        tipo_medidor = tag_to_tipo.get(tag_principal, 1)
        
        if cd_ponto is None:
            cd_ponto = abs(hash(tag_principal)) % 100000
            logger.warning(f"  TAG '{tag_principal}' sem CD_PONTO_MEDICAO, usando hash: {cd_ponto}")
        
        # 4. Buscar dados do FINDESLAB (principal + auxiliares)
        todas_tags = [tag_principal] + tags_auxiliares
        dados = buscar_dados_tags(conn, todas_tags, semanas)
        conn.close()
        
        if dados.empty:
            return {
                'success': False,
                'error': f'Sem dados no FINDESLAB para as tags ({semanas} semanas)'
            }
        
        # 5. Preparar features (correlação de rede)
        resultado = preparar_dados_treino(dados, tag_principal, tags_auxiliares)
        if resultado is None:
            return {
                'success': False,
                'error': 'Dados insuficientes após preparação (mínimo 100 amostras)'
            }
        
        X, y, feature_names = resultado
        
        # 6. Treinar modelo XGBoost
        modelo, metricas, feature_importance = treinar_modelo(X, y, tag_principal)
        
        # 7. Salvar modelo + metadados completos
        logger.info(f"  Salvando modelo em: {MODELS_DIR}/ponto_{cd_ponto}/")
        os.makedirs(os.path.join(MODELS_DIR, f"ponto_{cd_ponto}"), exist_ok=True)
        salvar_modelo(
            cd_ponto=cd_ponto,
            tag_principal=tag_principal,
            tags_auxiliares=tags_auxiliares,
            modelo=modelo,
            feature_names=feature_names,
            metricas=metricas,
            feature_importance=feature_importance,
            tipo_medidor=tipo_medidor,
            output_dir=MODELS_DIR
        )
        
        logger.info(f"  Treino completo finalizado: R²={metricas.get('r2', 0):.4f}")
        
        return {'success': True}
        
    except Exception as e:
        logger.error(f"Erro no treino completo: {e}", exc_info=True)
        return {
            'success': False,
            'error': str(e)
        }

@app.route('/api/train-all', methods=['POST'])
def train_all():
    """
    Treinar TODOS os pontos em background (assíncrono).
    Se já houver treino em execução, adiciona na fila e retorna posição.
    O progresso é gravado em /app/models/_train_all_progress.json
    e pode ser consultado via GET /api/train-all/status.
    """
    try:
        dados = request.get_json()
        semanas = dados.get('semanas', 24)
        modo = dados.get('modo', 'fixo')
        usuario = dados.get('usuario', None)

        script_path = os.environ.get('TREINAR_SCRIPT', '/app/treinar_modelos.py')

        if not os.path.exists(script_path):
            return jsonify({
                'success': False,
                'error': f'Script não encontrado: {script_path}'
            }), 500

        # Thread-safe: verificar se há treino em execução → enfileirar ou iniciar
        queued_job_id, posicao = _verificar_e_enfileirar('train_all', {
            'semanas': semanas,
            'modo': modo
        }, usuario)

        if queued_job_id:
            return jsonify({
                'success': True,
                'queued': True,
                'job_id': queued_job_id,
                'posicao_fila': posicao,
                'message': f'Treino adicionado na fila (posição {posicao}). Iniciará automaticamente.'
            })

        # Nenhum treino em execução → iniciar agora
        job_id = str(uuid.uuid4())[:8]
        _iniciar_treino_todos_background(semanas, job_id, modo)

        return jsonify({
            'success': True,
            'queued': False,
            'message': 'Treino iniciado em background.',
            'job_id': job_id
        })

    except Exception as e:
        logger.error(f"Erro ao iniciar train-all: {e}", exc_info=True)
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/api/train-all/status', methods=['GET'])
def train_all_status():
    """
    Consultar progresso do treino em background + informações da fila.
    Lê o arquivo _train_all_progress.json e _train_queue.json.
    """
    if not os.path.exists(PROGRESS_FILE):
        return jsonify({
            'success': True,
            'status': 'idle',
            'message': 'Nenhum treino em andamento ou realizado.',
            'fila': _ler_fila()
        })

    try:
        with open(PROGRESS_FILE, 'r') as f:
            progress = json.load(f)
        # Sempre incluir estado atual da fila
        progress['fila'] = _ler_fila()
        return jsonify({
            'success': True,
            **progress
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'error': f'Erro ao ler progresso: {str(e)}'
        })


@app.route('/api/train-queue', methods=['GET'])
def train_queue_status():
    """
    Consultar estado da fila de treinamento.
    Retorna a fila atual e se há treino em execução.
    """
    fila = _ler_fila()
    em_execucao = _treino_em_execucao()

    # Ler info do treino atual
    treino_atual = None
    if em_execucao and os.path.exists(PROGRESS_FILE):
        try:
            with open(PROGRESS_FILE, 'r') as f:
                treino_atual = json.load(f)
        except Exception:
            pass

    return jsonify({
        'success': True,
        'em_execucao': em_execucao,
        'treino_atual': treino_atual,
        'fila': fila,
        'total_na_fila': len(fila)
    })

# ============================================
# Endpoints de Associações ([SIMP].[dbo].[AUX_RELACAO_PONTOS_MEDICAO])
# ============================================

@app.route('/api/relations', methods=['GET'])
def list_relations():
    """Lista todas as associações TAG_PRINCIPAL → [TAG_AUXILIAR]."""
    try:
        conn = db._get_connection()
        cursor = conn.cursor()
        cursor.execute("""
            SELECT LTRIM(RTRIM(TAG_PRINCIPAL)) AS TAG_PRINCIPAL,
                   LTRIM(RTRIM(TAG_AUXILIAR)) AS TAG_AUXILIAR
            FROM SIMP.dbo.AUX_RELACAO_PONTOS_MEDICAO
            WHERE LTRIM(RTRIM(TAG_PRINCIPAL)) <> LTRIM(RTRIM(TAG_AUXILIAR))
            ORDER BY TAG_PRINCIPAL, TAG_AUXILIAR
        """)
        rows = cursor.fetchall()
        conn.close()

        relacoes = {}
        for row in rows:
            principal = row[0]
            auxiliar = row[1]
            if principal not in relacoes:
                relacoes[principal] = []
            relacoes[principal].append(auxiliar)

        return jsonify({
            'success': True,
            'relacoes': relacoes,
            'total_principais': len(relacoes),
            'total_associacoes': len(rows)
        })
    except Exception as e:
        logger.error(f"Erro list_relations: {e}")
        return jsonify({'success': False, 'error': str(e)})


@app.route('/api/relations/add', methods=['POST'])
def add_relation():
    """Adiciona uma associação TAG_PRINCIPAL → TAG_AUXILIAR."""
    try:
        dados = request.get_json()
        tag_principal = (dados.get('tag_principal') or '').strip()
        tag_auxiliar = (dados.get('tag_auxiliar') or '').strip()

        if not tag_principal or not tag_auxiliar:
            return jsonify({'success': False, 'error': 'tag_principal e tag_auxiliar obrigatórios'})

        conn = db._get_connection()
        cursor = conn.cursor()

        # Se o valor recebido for numérico, é CD_PONTO_MEDICAO — resolver para TAG
        tag_principal = _resolver_tag(cursor, tag_principal)
        tag_auxiliar = _resolver_tag(cursor, tag_auxiliar)

        if not tag_principal or not tag_auxiliar:
            conn.close()
            return jsonify({'success': False, 'error': 'Não foi possível resolver a TAG do ponto informado'})

        if tag_principal == tag_auxiliar:
            conn.close()
            return jsonify({'success': False, 'error': 'TAG principal e auxiliar não podem ser iguais'})

        # Verificar duplicata
        cursor.execute("""
            SELECT COUNT(*) FROM SIMP.dbo.AUX_RELACAO_PONTOS_MEDICAO
            WHERE LTRIM(RTRIM(TAG_PRINCIPAL)) = ? AND LTRIM(RTRIM(TAG_AUXILIAR)) = ?
        """, (tag_principal, tag_auxiliar))
        if cursor.fetchone()[0] > 0:
            conn.close()
            return jsonify({'success': False, 'error': 'Associação já existe'})

        # Inserir
        cursor.execute("""
            INSERT INTO SIMP.dbo.AUX_RELACAO_PONTOS_MEDICAO (TAG_PRINCIPAL, TAG_AUXILIAR, DT_CADASTRO)
            VALUES (?, ?, GETDATE())
        """, (tag_principal, tag_auxiliar))
        conn.commit()
        conn.close()

        logger.info(f"Associação criada: {tag_principal} → {tag_auxiliar}")
        return jsonify({'success': True, 'message': f'Associação {tag_principal} → {tag_auxiliar} criada'})
    except Exception as e:
        logger.error(f"Erro add_relation: {e}")
        return jsonify({'success': False, 'error': str(e)})


def _resolver_tag(cursor, valor: str) -> str:
    """
    Se o valor for numérico (CD_PONTO_MEDICAO), busca a TAG real.
    Caso contrário, retorna o próprio valor (já é TAG).
    """
    if not valor.isdigit():
        return valor

    cursor.execute("""
        SELECT COALESCE(DS_TAG_VAZAO, DS_TAG_PRESSAO, DS_TAG_RESERVATORIO, DS_TAG_VOLUME) AS TAG
        FROM SIMP.dbo.PONTO_MEDICAO
        WHERE CD_PONTO_MEDICAO = ?
    """, (int(valor),))
    row = cursor.fetchone()
    if row and row[0]:
        return row[0].strip()

    return None


@app.route('/api/available-tags', methods=['GET'])
def available_tags():
    """Lista TAGs disponíveis de pontos de medição ativos."""
    try:
        conn = db._get_connection()
        cursor = conn.cursor()
        cursor.execute("""
            SELECT CD_PONTO_MEDICAO,
                   DS_NOME AS NM_PONTO_MEDICAO,
                   ID_TIPO_MEDIDOR,
                   COALESCE(DS_TAG_VAZAO, DS_TAG_PRESSAO, DS_TAG_RESERVATORIO, DS_TAG_VOLUME) AS TAG
            FROM SIMP.dbo.PONTO_MEDICAO
            WHERE DT_DESATIVACAO IS NULL
              AND COALESCE(DS_TAG_VAZAO, DS_TAG_PRESSAO, DS_TAG_RESERVATORIO, DS_TAG_VOLUME) IS NOT NULL
            ORDER BY COALESCE(DS_TAG_VAZAO, DS_TAG_PRESSAO, DS_TAG_RESERVATORIO, DS_TAG_VOLUME)
        """)
        rows = cursor.fetchall()
        conn.close()

        tags = []
        for row in rows:
            tags.append({
                'CD_PONTO_MEDICAO': row[0],
                'NM_PONTO_MEDICAO': row[1],
                'ID_TIPO_MEDIDOR': row[2],
                'TAG': row[3]
            })

        return jsonify({'success': True, 'tags': tags, 'total': len(tags)})
    except Exception as e:
        logger.error(f"Erro available_tags: {e}")
        return jsonify({'success': False, 'error': str(e)})


@app.route('/api/relations/delete', methods=['POST'])
def delete_relation():
    """Remove uma associação específica."""
    try:
        dados = request.get_json()
        tag_principal = (dados.get('tag_principal') or '').strip()
        tag_auxiliar = (dados.get('tag_auxiliar') or '').strip()

        if not tag_principal or not tag_auxiliar:
            return jsonify({'success': False, 'error': 'tag_principal e tag_auxiliar obrigatórios'})

        conn = db._get_connection()
        cursor = conn.cursor()
        cursor.execute("""
            DELETE FROM SIMP.dbo.AUX_RELACAO_PONTOS_MEDICAO
            WHERE LTRIM(RTRIM(TAG_PRINCIPAL)) = ? AND LTRIM(RTRIM(TAG_AUXILIAR)) = ?
        """, (tag_principal, tag_auxiliar))
        affected = cursor.rowcount
        conn.commit()
        conn.close()

        logger.info(f"Associação removida: {tag_principal} → {tag_auxiliar} ({affected} registros)")
        return jsonify({'success': True, 'message': f'Associação removida', 'registros': affected})
    except Exception as e:
        logger.error(f"Erro delete_relation: {e}")
        return jsonify({'success': False, 'error': str(e)})


@app.route('/api/relations/delete-all', methods=['POST'])
def delete_all_relations():
    """Remove todas as associações de uma TAG principal."""
    try:
        dados = request.get_json()
        tag_principal = (dados.get('tag_principal') or '').strip()

        if not tag_principal:
            return jsonify({'success': False, 'error': 'tag_principal obrigatório'})

        conn = db._get_connection()
        cursor = conn.cursor()
        cursor.execute("""
            DELETE FROM [SIMP].[dbo].[AUX_RELACAO_PONTOS_MEDICAO]
            WHERE LTRIM(RTRIM(TAG_PRINCIPAL)) = ?
        """, (tag_principal,))
        affected = cursor.rowcount
        conn.commit()
        conn.close()

        logger.info(f"Todas associações de {tag_principal} removidas ({affected} registros)")
        return jsonify({'success': True, 'message': f'{affected} associações removidas', 'registros': affected})
    except Exception as e:
        logger.error(f"Erro delete_all_relations: {e}")
        return jsonify({'success': False, 'error': str(e)})

@app.route('/api/model/delete', methods=['POST'])
def delete_model():
    """Remove a pasta do modelo treinado de um ponto."""
    import shutil
    try:
        dados = request.get_json()
        cd_ponto = dados.get('cd_ponto')

        if not cd_ponto:
            return jsonify({'success': False, 'error': 'cd_ponto é obrigatório'})

        ponto_dir = os.path.join(MODELS_DIR, f"ponto_{cd_ponto}")

        if not os.path.exists(ponto_dir):
            return jsonify({'success': False, 'error': f'Modelo do ponto {cd_ponto} não encontrado'})

        # Remover pasta inteira (model.json, metricas.json, etc.)
        shutil.rmtree(ponto_dir)

        # Remover do cache do predictor
        if cd_ponto in predictor.models:
            del predictor.models[cd_ponto]

        logger.info(f"Modelo removido: ponto_{cd_ponto}")
        return jsonify({'success': True, 'message': f'Modelo do ponto {cd_ponto} removido com sucesso'})
    except Exception as e:
        logger.error(f"Erro delete_model: {e}")
        return jsonify({'success': False, 'error': str(e)})

@app.route('/api/model-status', methods=['GET'])
def model_status():
    """Retorna status de todos os modelos treinados."""
    try:
        modelos = predictor.list_models()
        return jsonify({
            'success': True,
            'modelos': modelos,
            'total': len(modelos),
            'diretorio': MODELS_DIR
        })
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


# ============================================
# Inicialização
# ============================================
if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5000))
    debug = os.environ.get('FLASK_ENV', 'production') == 'development'
    logger.info(f"Iniciando SIMP Predição na porta {port}")
    app.run(host='0.0.0.0', port=port, debug=debug)
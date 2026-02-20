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
    
    Usa o script completo que conecta ao FINDESLAB, busca tags auxiliares
    e treina com correlação de rede. Sem fallback simplificado.
    """
    import subprocess
    
    try:
        dados = request.get_json()
        cd_ponto = dados.get('cd_ponto')
        semanas = dados.get('semanas', 24)
        tipo_medidor = dados.get('tipo_medidor', 1)
        force = dados.get('force', False)
        
        if not cd_ponto:
            return jsonify({'success': False, 'error': 'cd_ponto é obrigatório'}), 400
        
        # Verificar se já existe modelo (e não é force)
        if not force and predictor.has_model(cd_ponto):
            return jsonify({
                'success': True,
                'message': 'Modelo já existe. Use force=true para retreinar.',
                'model_info': predictor.get_model_info(cd_ponto)
            })
        
        # =============================================
        # Buscar TAG principal do ponto no banco
        # =============================================
        tag_principal = _buscar_tag_ponto(cd_ponto, tipo_medidor)
        
        if not tag_principal:
            return jsonify({
                'success': False,
                'error': f'Ponto {cd_ponto} não possui TAG configurada para o tipo de medidor {tipo_medidor}'
            }), 200
        
        # =============================================
        # Executar treinar_modelos.py via subprocess
        # =============================================
        script_path = os.environ.get('TREINAR_SCRIPT', '/app/treinar_modelos.py')
        
        if not os.path.exists(script_path):
            return jsonify({
                'success': False,
                'error': f'Script de treino não encontrado: {script_path}. Verifique o volume no docker-compose.'
            }), 500
        
        cmd = [
            'python3', script_path,
            '--tag', tag_principal,
            '--semanas', str(semanas),
            '--output', MODELS_DIR
        ]
        
        logger.info(f"Executando: {' '.join(cmd)}")
        
        result = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            timeout=600  # 10 minutos
        )
        
        # Log do output
        if result.stdout:
            logger.info(f"STDOUT:\n{result.stdout[-1000:]}")
        if result.stderr:
            logger.error(f"STDERR:\n{result.stderr[-500:]}")
        
        # Verificar resultado
        if result.returncode != 0:
            return jsonify({
                'success': False,
                'error': f'Erro no treino (código {result.returncode})',
                'detalhes': result.stderr[-300:] if result.stderr else result.stdout[-300:]
            }), 200
        
        # Verificar se teve sucesso no output
        if 'Sucesso: 0' in result.stdout:
            return jsonify({
                'success': False,
                'error': 'Treino executou mas nenhum modelo foi gerado com sucesso',
                'detalhes': result.stdout[-500:]
            }), 200
        
        # =============================================
        # Recarregar modelo treinado e retornar métricas
        # =============================================
        # Forçar recarga do modelo
        if cd_ponto in predictor.models:
            del predictor.models[cd_ponto]
        
        predictor._load_model(cd_ponto)
        metricas = predictor._load_metrics(cd_ponto)
        
        return jsonify({
            'success': True,
            'message': f'Modelo treinado com sucesso para ponto {cd_ponto} ({tag_principal})',
            'metricas': metricas,
            'epocas': metricas.get('n_arvores', 0),
            'dados_treino': metricas.get('amostras_treino', 0)
        })
        
    except subprocess.TimeoutExpired:
        return jsonify({
            'success': False,
            'error': 'Timeout: treino excedeu 10 minutos'
        }), 200
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
    Treinar TODOS os pontos com relações no FINDESLAB.
    Executa: python3 treinar_modelos.py --semanas N --output MODELS_DIR
    """
    import subprocess

    try:
        dados = request.get_json()
        semanas = dados.get('semanas', 24)

        script_path = os.environ.get('TREINAR_SCRIPT', '/app/treinar_modelos.py')

        if not os.path.exists(script_path):
            return jsonify({
                'success': False,
                'error': f'Script não encontrado: {script_path}'
            }), 500

        cmd = [
            'python3', script_path,
            '--semanas', str(semanas),
            '--output', MODELS_DIR
        ]

        logger.info(f"Train-all: {' '.join(cmd)}")

        result = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            timeout=1800  # 30 minutos para todos
        )

        if result.stdout:
            logger.info(f"STDOUT (últimas 1000 chars):\n{result.stdout[-1000:]}")
        if result.stderr:
            logger.error(f"STDERR:\n{result.stderr[-500:]}")

        # Extrair resumo do output
        resumo = ''
        for linha in result.stdout.split('\n'):
            if any(k in linha for k in ['Sucesso:', 'Falha:', 'Total:', 'RESUMO']):
                resumo += linha.strip() + ' | '

        if result.returncode != 0:
            return jsonify({
                'success': False,
                'error': f'Erro no treino (código {result.returncode})',
                'resumo': resumo,
                'detalhes': result.stderr[-300:] if result.stderr else result.stdout[-300:]
            })

        # Recarregar todos os modelos
        predictor.models.clear()

        return jsonify({
            'success': True,
            'message': f'Treino finalizado. {resumo}' if resumo else 'Treino de todos os pontos finalizado.',
            'resumo': resumo
        })

    except subprocess.TimeoutExpired:
        return jsonify({
            'success': False,
            'error': 'Timeout: treino excedeu 30 minutos'
        })
    except Exception as e:
        logger.error(f"Erro train-all: {e}", exc_info=True)
        return jsonify({'success': False, 'error': str(e)}), 500

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
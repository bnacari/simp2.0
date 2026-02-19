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
def train_model():
    """
    Treina ou retreina o modelo XGBoost para um ponto específico.
    Usa dados do próprio histórico do SIMP (sem tags auxiliares).
    Para treino completo com auxiliares, usar treinar_modelos.py.
    
    Recebe:
        cd_ponto (int): Código do ponto
        semanas (int, opcional): Semanas de histórico para treino (default: 24)
        tipo_medidor (int, opcional): Tipo de medidor
        force (bool, opcional): Forçar retreino mesmo se modelo existir
    """
    try:
        dados = request.get_json()
        cd_ponto = dados.get('cd_ponto')
        semanas = dados.get('semanas', 24)
        tipo_medidor = dados.get('tipo_medidor', 1)
        force = dados.get('force', False)
        
        if not cd_ponto:
            return jsonify({'success': False, 'error': 'cd_ponto é obrigatório'}), 400
        
        # Verificar se já existe modelo treinado
        if not force and predictor.has_model(cd_ponto):
            return jsonify({
                'success': True,
                'message': 'Modelo já existe. Use force=true para retreinar.',
                'model_info': predictor.get_model_info(cd_ponto)
            })
        
        logger.info(f"Treinamento: ponto={cd_ponto}, semanas={semanas}")
        
        # Buscar histórico extenso
        from datetime import date
        data_ref = date.today().isoformat()
        historico = db.get_historico_horario(cd_ponto, data_ref, semanas, tipo_medidor)
        
        if historico is None or len(historico) < 336:  # Mínimo 2 semanas
            return jsonify({
                'success': False,
                'error': f'Histórico insuficiente para treino: {len(historico) if historico is not None else 0} registros'
            }), 200
        
        # Treinar modelo
        resultado = predictor.train(cd_ponto, historico, tipo_medidor)
        
        return jsonify({
            'success': True,
            'message': f'Modelo treinado com sucesso para ponto {cd_ponto}',
            'metricas': resultado['metricas'],
            'n_arvores': resultado['n_arvores'],
            'dados_treino': resultado['dados_treino']
        })
        
    except Exception as e:
        logger.error(f"Erro no treinamento: {str(e)}", exc_info=True)
        return jsonify({'success': False, 'error': str(e)}), 500


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
"""
SIMP - Preditor de Séries Temporais
Prediz valores horários de vazão, pressão e nível para pontos de medição.

v6.0 - XGBoost (Correlação de Rede via SIMP):
  Substituição completa do LSTM por XGBoost.
  Dados das tags auxiliares buscados diretamente do banco SIMP.
  
  Vantagens:
    - Cada predição é INDEPENDENTE (sem feedback, sem drift)
    - Usa dados REAIS das auxiliares (não valores preditos)
    - Não tende à média (árvores particionam o espaço)
    - Treina em segundos, prediz em milissegundos
    - Sem normalização (escala real direta)
    - Feature importance interpretável
    - Sem dependência de banco externo (FINDESLAB)
  
  Compatível com modelos LSTM legados (v1-v4).

@author Bruno - CESAN
@version 6.0
@date 2026-02
"""

import os
import json
import logging
import pickle
from datetime import datetime
from typing import Dict, List, Optional, Any

import numpy as np
import pandas as pd

from sklearn.preprocessing import MinMaxScaler, RobustScaler
from sklearn.metrics import mean_absolute_error, mean_squared_error

import xgboost as xgb

# Tentar importar TensorFlow para compatibilidade com modelos legados
try:
    import tensorflow as tf
    from tensorflow.keras.models import load_model
    tf.get_logger().setLevel('ERROR')
    TF_AVAILABLE = True
except ImportError:
    TF_AVAILABLE = False

# Módulo para buscar dados das tags auxiliares (v6.0: via banco SIMP)
from app.database_findeslab import buscar_dados_tags_recentes, montar_features_xgboost

logger = logging.getLogger('simp-tensorflow.predictor')


class TimeSeriesPredictor:
    """
    Preditor de séries temporais.
    
    v6.0: XGBoost como modelo principal.
    - Features tabulares: auxiliares com lags + temporais
    - Cada predição é independente (sem sliding window)
    - Sem normalização (escala real)
    - Dados auxiliares buscados do banco SIMP
    - Compatível com LSTM legado (v1-v4) se TensorFlow disponível
    """

    LOOKBACK = 168
    MAX_EPOCHS = 100
    BATCH_SIZE = 32

    def __init__(self, models_dir: str):
        self.models_dir = models_dir
        self.models: Dict[int, Any] = {}           # XGBoost ou Keras model
        self.feature_scalers: Dict[int, Any] = {}   # Para modelos LSTM legados
        self.target_scalers: Dict[int, Any] = {}    # Para modelos LSTM v4

    # ============================================
    # Predição principal
    # ============================================

    def predict(
        self,
        cd_ponto: int,
        historico: pd.DataFrame,
        data_alvo: str,
        horas: List[int],
        tipo_medidor: int = 1
    ) -> Dict[str, Any]:
        """
        Prediz valores para as horas solicitadas.
        Detecta tipo do modelo (XGBoost v5+ ou LSTM legado).
        """
        modelo = self._load_model(cd_ponto)

        if modelo is None:
            return self._predict_statistical(historico, data_alvo, horas)

        metricas = self._load_metrics(cd_ponto)
        modelo_tipo = metricas.get('modelo_tipo', 'lstm')

        if modelo_tipo == 'xgboost':
            return self._predict_xgboost(cd_ponto, historico, data_alvo, horas, metricas)
        else:
            # Modelo LSTM legado (v1-v4)
            if TF_AVAILABLE:
                return self._predict_lstm_legado(cd_ponto, historico, data_alvo, horas, metricas)
            else:
                logger.warning(f"Modelo LSTM detectado mas TensorFlow não disponível, usando fallback")
                return self._predict_statistical(historico, data_alvo, horas)

    # ============================================
    # Predição XGBoost v5.0+
    # ============================================

    def _predict_xgboost(
        self,
        cd_ponto: int,
        historico: pd.DataFrame,
        data_alvo: str,
        horas: List[int],
        metricas: dict
    ) -> Dict[str, Any]:
        """
        Predição XGBoost por correlação de rede.
        
        Para cada hora solicitada:
          1. Busca dados REAIS recentes das auxiliares no SIMP
          2. Monta features tabulares (auxiliares com lags + temporais)
          3. Prediz valor do principal
        
        Cada predição é INDEPENDENTE — sem feedback, sem drift.
        """
        modelo = self.models[cd_ponto]
        feature_names = metricas.get('feature_names', [])
        tag_principal = metricas.get('tag_principal', '')
        tags_auxiliares = metricas.get('tags_auxiliares', [])
        lags = metricas.get('lags', [0, 1, 3, 6])
        max_lag = max(lags) if lags else 6

        # Buscar dados recentes das auxiliares no SIMP
        try:
            dados_auxiliares = buscar_dados_tags_recentes(tags_auxiliares, max_lag + 12, data_alvo)
        except Exception as e:
            logger.error(f"Erro ao buscar dados auxiliares: {e}")
            dados_auxiliares = None

        if dados_auxiliares is None or dados_auxiliares.empty:
            logger.warning(f"Sem dados das auxiliares, usando fallback estatístico")
            return self._predict_statistical(historico, data_alvo, horas)

        # Montar features
        features_df = montar_features_xgboost(
            dados_auxiliares, tags_auxiliares, feature_names, lags
        )

        if features_df is None or features_df.empty:
            logger.warning(f"Falha ao montar features XGBoost, usando fallback")
            return self._predict_statistical(historico, data_alvo, horas)

        # Predizer cada hora solicitada
        predicoes = []

        for hora in sorted(horas):
            try:
                # Buscar a linha que corresponde à hora solicitada
                data_alvo_dt = datetime.strptime(data_alvo, '%Y-%m-%d')
                dia_semana = data_alvo_dt.weekday()

                # Procurar linha exata da hora no DataFrame
                linha_hora = None
                for idx in features_df.index:
                    if hasattr(idx, 'hour') and idx.hour == hora:
                        linha_hora = features_df.loc[[idx]].copy()
                        break

                # Se não encontrou a hora exata, usar a mais recente
                if linha_hora is None:
                    linha_hora = features_df.iloc[-1:].copy()

                # Atualizar features temporais para a hora solicitada
                if 'hora_sin' in feature_names:
                    linha_hora['hora_sin'] = np.sin(2 * np.pi * hora / 24)
                if 'hora_cos' in feature_names:
                    linha_hora['hora_cos'] = np.cos(2 * np.pi * hora / 24)
                if 'dia_sem_sin' in feature_names:
                    linha_hora['dia_sem_sin'] = np.sin(2 * np.pi * dia_semana / 7)
                if 'dia_sem_cos' in feature_names:
                    linha_hora['dia_sem_cos'] = np.cos(2 * np.pi * dia_semana / 7)

                # Garantir ordem das colunas igual ao treino
                linha_pred = linha_hora[feature_names]

                # Predizer (numpy array para evitar feature_names mismatch)
                valor_predito = float(modelo.predict(linha_pred.values)[0])

                # Confiança baseada no histórico
                confianca = self._calcular_confianca_hora(historico, hora, data_alvo)

                predicoes.append({
                    'hora': int(hora),
                    'hora_formatada': f"{int(hora):02d}:00",
                    'valor_predito': round(float(max(0, valor_predito)), 2),
                    'confianca': round(float(confianca), 2),
                    'metodo': 'xgboost_correlacao'
                })

            except Exception as e:
                logger.error(f"Erro na predição hora {hora}: {e}")
                predicoes.append({
                    'hora': int(hora),
                    'hora_formatada': f"{int(hora):02d}:00",
                    'valor_predito': 0.0,
                    'confianca': 0.0,
                    'metodo': 'erro'
                })

        # Info do modelo
        n_aux = len(tags_auxiliares)
        r2 = metricas.get('r2', None)
        n_arvores = metricas.get('n_arvores', '?')

        formula = (
            f'XGBoost(features={len(feature_names)}, '
            f'auxiliares={n_aux}, '
            f'lags={lags}, '
            f'árvores={n_arvores}, '
            f'v=6.0)'
        )
        if r2 is not None:
            formula += f' [R²={r2:.3f}]'
        if tag_principal:
            formula += f' [TAG: {tag_principal}]'

        return {
            'predicoes': predicoes,
            'formula': formula,
            'metricas': metricas,
            'modelo': 'xgboost'
        }

    # ============================================
    # Predição LSTM legada (v1-v4) — compatibilidade
    # ============================================

    def _predict_lstm_legado(
        self,
        cd_ponto: int,
        historico: pd.DataFrame,
        data_alvo: str,
        horas: List[int],
        metricas: dict
    ) -> Dict[str, Any]:
        """Predição LSTM legada para compatibilidade com modelos v1-v4."""
        if not TF_AVAILABLE:
            return self._predict_statistical(historico, data_alvo, horas)

        modelo = self.models[cd_ponto]
        scaler = self.feature_scalers.get(cd_ponto)

        if scaler is None:
            return self._predict_statistical(historico, data_alvo, horas)

        features_df = self._prepare_features_lstm(historico)
        if len(features_df) < self.LOOKBACK:
            return self._predict_statistical(historico, data_alvo, horas)

        feature_cols = [c for c in features_df.columns if c != 'valor_original']
        scaled = scaler.transform(features_df[feature_cols].values)
        input_seq = scaled[-self.LOOKBACK:].copy()

        predicoes = []
        for hora in sorted(horas):
            X = input_seq.reshape(1, input_seq.shape[0], input_seq.shape[1])
            pred_scaled = modelo.predict(X, verbose=0)[0][0]

            dummy = np.zeros((1, input_seq.shape[1]))
            dummy[0, 0] = pred_scaled
            valor_predito = scaler.inverse_transform(dummy)[0][0]

            confianca = self._calcular_confianca_hora(historico, hora, data_alvo)

            predicoes.append({
                'hora': int(hora),
                'hora_formatada': f"{int(hora):02d}:00",
                'valor_predito': round(float(max(0, valor_predito)), 2),
                'confianca': round(float(confianca), 2),
                'metodo': 'lstm_legado'
            })

            nova_linha = input_seq[-1].copy()
            nova_linha[0] = pred_scaled
            input_seq = np.vstack([input_seq[1:], nova_linha])

        return {
            'predicoes': predicoes,
            'formula': f'LSTM_legado(v={metricas.get("versao_treino", "1.0")})',
            'metricas': metricas,
            'modelo': 'lstm'
        }

    # ============================================
    # Fallback estatístico
    # ============================================

    def _predict_statistical(
        self,
        historico: pd.DataFrame,
        data_alvo: str,
        horas: List[int]
    ) -> Dict[str, Any]:
        """Fallback estatístico: média ponderada por dia da semana + tendência."""
        data_alvo_dt = datetime.strptime(data_alvo, '%Y-%m-%d')
        dia_semana_alvo = data_alvo_dt.isoweekday()
        dia_sql = (dia_semana_alvo % 7) + 1
        hist_mesmo_dia = historico[historico['dia_semana'] == dia_sql].copy()

        medias_por_data_hora = hist_mesmo_dia.groupby(
            [hist_mesmo_dia['data'].dt.date, 'hora']
        )['valor'].mean().reset_index()
        medias_por_data_hora.columns = ['data', 'hora', 'valor']

        dados_hoje = historico[
            historico['data'].dt.date == data_alvo_dt.date()
        ]
        fator_tendencia = self._calcular_fator_tendencia(dados_hoje, hist_mesmo_dia)

        predicoes = []
        dados_hora = None

        for hora in sorted(horas):
            dados_hora = medias_por_data_hora[
                medias_por_data_hora['hora'] == hora
            ]['valor'].dropna()

            if len(dados_hora) == 0:
                predicoes.append({
                    'hora': int(hora),
                    'hora_formatada': f"{int(hora):02d}:00",
                    'valor_predito': 0.0,
                    'confianca': 0.0,
                    'metodo': 'sem_dados'
                })
                continue

            n = len(dados_hora)
            pesos = np.exp(np.linspace(0, 2, n))
            pesos /= pesos.sum()
            media_ponderada = np.average(dados_hora.values, weights=pesos)
            valor_predito = media_ponderada * fator_tendencia

            cv = dados_hora.std() / dados_hora.mean() if dados_hora.mean() > 0 else 1.0
            confianca = max(0.0, min(1.0, 1.0 - cv))
            fator_dados = min(1.0, n / 8.0)
            confianca *= fator_dados

            predicoes.append({
                'hora': int(hora),
                'hora_formatada': f"{int(hora):02d}:00",
                'valor_predito': round(float(max(0, valor_predito)), 2),
                'confianca': round(float(confianca), 2),
                'metodo': 'media_ponderada'
            })

        return {
            'predicoes': predicoes,
            'formula': (
                f'valor = média_ponderada_exponencial(últimas {len(dados_hora) if dados_hora is not None else 0} semanas) '
                f'× fator_tendência({fator_tendencia:.4f})'
            ),
            'metricas': {
                'metodo': 'estatístico (sem modelo treinado)',
                'semanas_utilizadas': hist_mesmo_dia['data'].dt.date.nunique(),
                'fator_tendencia': round(float(fator_tendencia), 4)
            },
            'modelo': 'statistical_fallback'
        }

    # ============================================
    # Treinamento via API (simplificado, sem auxiliares)
    # ============================================

    def train(
        self,
        cd_ponto: int,
        historico: pd.DataFrame,
        tipo_medidor: int = 1
    ) -> Dict[str, Any]:
        """
        Treina modelo XGBoost simples via API (sem tags auxiliares).
        Usa apenas features do próprio histórico do SIMP.
        Para treino completo com auxiliares, usar treinar_modelos.py.
        """
        logger.info(f"Treino via API para ponto {cd_ponto} com {len(historico)} registros")

        df = historico.copy()
        df = df.dropna(subset=['valor'])

        if len(df) < 100:
            raise ValueError(f"Dados insuficientes: {len(df)}")

        # Features simples do histórico
        df['hora_sin'] = np.sin(2 * np.pi * df['hora'] / 24)
        df['hora_cos'] = np.cos(2 * np.pi * df['hora'] / 24)
        df['dia_sem_sin'] = np.sin(2 * np.pi * df['dia_semana'] / 7)
        df['dia_sem_cos'] = np.cos(2 * np.pi * df['dia_semana'] / 7)
        df['valor_t1'] = df['valor'].shift(1)
        df['valor_t3'] = df['valor'].shift(3)
        df['valor_t6'] = df['valor'].shift(6)
        df = df.dropna()

        feature_cols = ['hora_sin', 'hora_cos', 'dia_sem_sin', 'dia_sem_cos',
                        'valor_t1', 'valor_t3', 'valor_t6']
        X = df[feature_cols]
        y = df['valor']

        split = int(len(X) * 0.85)
        X_train, X_val = X.iloc[:split], X.iloc[split:]
        y_train, y_val = y.iloc[:split], y.iloc[split:]

        modelo = xgb.XGBRegressor(
            n_estimators=300, max_depth=6, learning_rate=0.05,
            early_stopping_rounds=20, eval_metric='mae',
            n_jobs=-1, random_state=42
        )

        modelo.fit(X_train, y_train, eval_set=[(X_val, y_val)], verbose=False)

        y_pred = modelo.predict(X_val)
        mae = mean_absolute_error(y_val, y_pred)
        rmse = np.sqrt(mean_squared_error(y_val, y_pred))

        # Salvar
        ponto_dir = os.path.join(self.models_dir, f"ponto_{cd_ponto}")
        os.makedirs(ponto_dir, exist_ok=True)

        modelo.save_model(os.path.join(ponto_dir, 'model.json'))

        metricas = {
            'mae': round(float(mae), 4),
            'rmse': round(float(rmse), 4),
            'n_arvores': modelo.best_iteration + 1 if modelo.best_iteration else 300,
            'feature_names': feature_cols,
            'modelo_tipo': 'xgboost',
            'lags': [1, 3, 6],
            'treinado_em': datetime.now().isoformat(),
            'tipo_medidor': tipo_medidor,
            'versao_treino': '6.0_api'
        }

        with open(os.path.join(ponto_dir, 'metricas.json'), 'w') as f:
            json.dump(metricas, f, indent=2)

        self.models[cd_ponto] = modelo

        return {
            'metricas': {'mae': round(float(mae), 4), 'rmse': round(float(rmse), 4)},
            'n_arvores': metricas['n_arvores'],
            'dados_treino': len(X_train)
        }

    # ============================================
    # Features para LSTM legado
    # ============================================

    def _prepare_features_lstm(self, historico: pd.DataFrame) -> pd.DataFrame:
        """Prepara features para modelo LSTM legado."""
        df = historico.copy()
        df = df.dropna(subset=['valor'])
        if df.empty:
            return pd.DataFrame()

        df['hora_sin'] = np.sin(2 * np.pi * df['hora'] / 24)
        df['hora_cos'] = np.cos(2 * np.pi * df['hora'] / 24)
        df['dia_sem_sin'] = np.sin(2 * np.pi * df['dia_semana'] / 7)
        df['dia_sem_cos'] = np.cos(2 * np.pi * df['dia_semana'] / 7)
        medias = df.groupby(['dia_semana', 'hora'])['valor'].transform('mean')
        df['media_historica'] = medias
        df['valor_anterior'] = df['valor'].shift(1)
        df = df.dropna()
        df['valor_original'] = df['valor'].values

        feature_cols = [
            'valor', 'hora_sin', 'hora_cos',
            'dia_sem_sin', 'dia_sem_cos',
            'media_historica', 'valor_anterior',
            'valor_original'
        ]
        return df[feature_cols].reset_index(drop=True)

    # ============================================
    # Utilitários
    # ============================================

    def _calcular_fator_tendencia(self, dados_hoje, hist_mesmo_dia) -> float:
        """Calcula fator de tendência."""
        if dados_hoje.empty or len(dados_hoje) < 3:
            return 1.0

        soma_atual = 0.0
        soma_historica = 0.0
        horas_usadas = 0

        for hora in dados_hoje['hora'].unique():
            valor_atual = dados_hoje[dados_hoje['hora'] == hora]['valor'].mean()
            valores_hist = hist_mesmo_dia[hist_mesmo_dia['hora'] == hora]['valor']
            media_hist = valores_hist.mean() if len(valores_hist) > 0 else None

            if media_hist is not None and media_hist > 0 and not np.isnan(valor_atual):
                soma_atual += valor_atual
                soma_historica += media_hist
                horas_usadas += 1

        if horas_usadas >= 3 and soma_historica > 0:
            return max(0.5, min(2.0, soma_atual / soma_historica))
        return 1.0

    def _calcular_confianca_hora(self, historico, hora, data_alvo) -> float:
        """Calcula confiança da predição."""
        data_alvo_dt = datetime.strptime(data_alvo, '%Y-%m-%d')
        dia_sql = (data_alvo_dt.isoweekday() % 7) + 1

        dados_hora = historico[
            (historico['hora'] == hora) & (historico['dia_semana'] == dia_sql)
        ]['valor'].dropna()

        if len(dados_hora) < 2:
            return 0.3
        cv = dados_hora.std() / dados_hora.mean() if dados_hora.mean() > 0 else 1.0
        confianca = max(0.0, min(1.0, 1.0 - cv))
        return confianca * min(1.0, len(dados_hora) / 8.0)

    # ============================================
    # Persistência de modelos
    # ============================================

    def _save_model(self, cd_ponto, model, scaler, metricas):
        """Salva modelo (legado)."""
        ponto_dir = os.path.join(self.models_dir, f"ponto_{cd_ponto}")
        os.makedirs(ponto_dir, exist_ok=True)
        model.save(os.path.join(ponto_dir, 'model.h5'), save_format='h5')
        with open(os.path.join(ponto_dir, 'scaler.pkl'), 'wb') as f:
            pickle.dump(scaler, f)
        with open(os.path.join(ponto_dir, 'metricas.json'), 'w') as f:
            json.dump(metricas, f, indent=2)

    def _load_model(self, cd_ponto: int) -> Optional[Any]:
        """
        Carrega modelo do disco. Detecta automaticamente XGBoost ou LSTM.
        """
        if cd_ponto in self.models:
            return self.models[cd_ponto]

        ponto_dir = os.path.join(self.models_dir, f"ponto_{cd_ponto}")

        # Tentar XGBoost primeiro (v5.0+)
        xgb_path = os.path.join(ponto_dir, 'model.json')
        if os.path.exists(xgb_path):
            try:
                modelo = xgb.XGBRegressor()
                modelo.load_model(xgb_path)
                self.models[cd_ponto] = modelo
                logger.info(f"Modelo XGBoost carregado: ponto {cd_ponto}")
                return modelo
            except Exception as e:
                logger.error(f"Erro ao carregar XGBoost do ponto {cd_ponto}: {e}")

        # Tentar LSTM legado (v1-v4)
        if TF_AVAILABLE:
            h5_path = os.path.join(ponto_dir, 'model.h5')
            keras_path = os.path.join(ponto_dir, 'model.keras')
            model_path = h5_path if os.path.exists(h5_path) else keras_path

            if os.path.exists(model_path):
                try:
                    modelo = load_model(model_path)
                    self.models[cd_ponto] = modelo

                    scaler_path = os.path.join(ponto_dir, 'scaler.pkl')
                    if os.path.exists(scaler_path):
                        with open(scaler_path, 'rb') as f:
                            self.feature_scalers[cd_ponto] = pickle.load(f)

                    target_scaler_path = os.path.join(ponto_dir, 'target_scaler.pkl')
                    if os.path.exists(target_scaler_path):
                        with open(target_scaler_path, 'rb') as f:
                            self.target_scalers[cd_ponto] = pickle.load(f)

                    logger.info(f"Modelo LSTM legado carregado: ponto {cd_ponto}")
                    return modelo
                except Exception as e:
                    logger.error(f"Erro ao carregar LSTM do ponto {cd_ponto}: {e}")

        return None

    def _load_metrics(self, cd_ponto: int) -> dict:
        """Carrega métricas do último treino."""
        metricas_path = os.path.join(
            self.models_dir, f"ponto_{cd_ponto}", 'metricas.json'
        )
        if os.path.exists(metricas_path):
            with open(metricas_path) as f:
                return json.load(f)
        return {}

    def has_model(self, cd_ponto: int) -> bool:
        """Verifica se existe modelo treinado."""
        if cd_ponto in self.models:
            return True
        ponto_dir = os.path.join(self.models_dir, f"ponto_{cd_ponto}")
        return (
            os.path.exists(os.path.join(ponto_dir, 'model.json')) or
            os.path.exists(os.path.join(ponto_dir, 'model.h5'))
        )

    def get_model_info(self, cd_ponto: int) -> dict:
        """Retorna informações do modelo."""
        return {
            'cd_ponto': cd_ponto,
            'existe': self.has_model(cd_ponto),
            'metricas': self._load_metrics(cd_ponto)
        }

    def list_models(self) -> List[dict]:
        """Lista todos os modelos treinados."""
        modelos = []
        if os.path.exists(self.models_dir):
            for nome in os.listdir(self.models_dir):
                if nome.startswith('ponto_'):
                    cd_ponto = int(nome.replace('ponto_', ''))
                    modelos.append(self.get_model_info(cd_ponto))
        return modelos
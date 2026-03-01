"""
SIMP TensorFlow - Detector de Anomalias (Autoencoder)
Aprende o padrão "normal" de cada ponto e detecta desvios.

Substitui as regras fixas (variação > 100%, vazão zerada > 2h, etc.)
por detecção baseada em dados reais, mais precisa por ponto.

@author Bruno - CESAN
@version 1.0
"""

import os
import json
import logging
import pandas as pd
import numpy as np
from typing import Dict, List, Optional, Any
from sklearn.preprocessing import MinMaxScaler
try:
    import tensorflow as tf
    from tensorflow.keras.models import Sequential, load_model, Model
    from tensorflow.keras.layers import Dense, Input
    from tensorflow.keras.callbacks import EarlyStopping
    TF_AVAILABLE = True
except ImportError:
    TF_AVAILABLE = False
    tf = None

logger = logging.getLogger('simp-tensorflow.anomaly')


class AnomalyDetector:
    """
    Detector de anomalias usando Autoencoder + regras estatísticas.
    
    Estratégia em camadas:
    1. Regras simples (vazão negativa, zerada prolongada) → sempre aplicadas
    2. Z-score: desvio em relação à média/desvio padrão do histórico
    3. Autoencoder: erro de reconstrução alto = anomalia
    """

    def __init__(self, models_dir: str):
        """
        Args:
            models_dir: Diretório para modelos de autoencoder
        """
        self.models_dir = models_dir

    def detect(
        self,
        cd_ponto: int,
        dados_dia: pd.DataFrame,
        historico: Optional[pd.DataFrame],
        sensibilidade: float = 0.8,
        tipo_medidor: int = 1
    ) -> Dict[str, Any]:
        """
        Detecta anomalias nos dados de um dia.

        Combina 3 camadas de detecção:
        1. Regras determinísticas (sempre aplicadas)
        2. Análise estatística (Z-score)
        3. Autoencoder (se treinado)

        Args:
            cd_ponto: Código do ponto
            dados_dia: DataFrame com hora, valor, qtd_registros
            historico: DataFrame do histórico (pode ser None)
            sensibilidade: 0.0 (muito tolerante) a 1.0 (muito sensível)
            tipo_medidor: Tipo de medidor

        Returns:
            Dict com anomalias, resumo, score_geral
        """
        anomalias = []

        # ========================================
        # Camada 0: Detectar gaps (horas sem dados)
        # ========================================
        anomalias.extend(
            self._detectar_gaps(dados_dia)
        )

        # ========================================
        # Camada 1: Regras determinísticas
        # ========================================
        anomalias.extend(
            self._regras_deterministicas(dados_dia, tipo_medidor)
        )

        # ========================================
        # Camada 2: Análise estatística (Z-score)
        # ========================================
        if historico is not None and len(historico) > 0:
            anomalias.extend(
                self._analise_zscore(dados_dia, historico, sensibilidade, tipo_medidor)
            )

        # ========================================
        # Camada 3: Autoencoder (se disponível)
        # ========================================
        anomalias_ae = self._analise_autoencoder(
            cd_ponto, dados_dia, historico, sensibilidade
        )
        if anomalias_ae:
            anomalias.extend(anomalias_ae)

        # Remover duplicatas (mesma hora com mesmo tipo)
        anomalias = self._deduplicar(anomalias)

        # Ordenar por severidade
        severidade_ordem = {'critica': 0, 'alta': 1, 'media': 2, 'baixa': 3}
        anomalias.sort(key=lambda a: (
            severidade_ordem.get(a.get('severidade', 'baixa'), 4),
            a.get('hora', 0)
        ))

        # Calcular score geral do dia (0 = normal, 1 = muito anômalo)
        if anomalias:
            scores = [a.get('score', 0.5) for a in anomalias]
            score_geral = min(1.0, np.mean(scores) + 0.1 * len(anomalias))
        else:
            score_geral = 0.0

        # Gerar resumo textual
        resumo = self._gerar_resumo(anomalias, score_geral, dados_dia)

        return {
            'anomalias': anomalias,
            'resumo': resumo,
            'score_geral': round(float(score_geral), 2),
            'total_anomalias': len(anomalias)
        }

    # ============================================
    # Camada 0: Detectar Gaps (horas sem dados)
    # ============================================

    def _detectar_gaps(self, dados_dia: pd.DataFrame) -> List[Dict]:
        """
        Detecta horas completamente sem dados (gaps de comunicação).
        O DataFrame só contém horas que possuem registros, então horas
        ausentes indicam falha de comunicação do equipamento.
        """
        anomalias = []
        horas_presentes = set(int(row.get('hora', -1)) for _, row in dados_dia.iterrows())

        for hora in range(24):
            if hora not in horas_presentes:
                anomalias.append({
                    'hora': hora,
                    'hora_formatada': f"{hora:02d}:00",
                    'valor_real': None,
                    'valor_esperado': None,
                    'score': 0.95,
                    'severidade': 'critica',
                    'tipo': 'gap_comunicacao',
                    'descricao': f'Hora {hora:02d}:00 sem nenhum registro - falha de comunicação',
                    'metodo': 'regras'
                })

        return anomalias

    # ============================================
    # Camada 1: Regras Determinísticas
    # ============================================

    def _regras_deterministicas(
        self,
        dados_dia: pd.DataFrame,
        tipo_medidor: int
    ) -> List[Dict]:
        """
        Regras fixas baseadas em impossibilidades físicas.
        Mesmas regras do ia_config.php mas aplicadas programaticamente.
        """
        anomalias = []

        for _, row in dados_dia.iterrows():
            hora = int(row.get('hora', -1))
            valor = row.get('valor')
            qtd = int(row.get('qtd_registros', 0))

            if valor is None or pd.isna(valor):
                # Hora com registro mas valor nulo
                anomalias.append({
                    'hora': hora,
                    'hora_formatada': f"{hora:02d}:00",
                    'valor_real': None,
                    'valor_esperado': None,
                    'score': 0.9,
                    'severidade': 'alta',
                    'tipo': 'sem_dados',
                    'descricao': f'Hora {hora:02d}:00 sem dados válidos',
                    'metodo': 'regras'
                })
                continue

            valor = float(valor)

            # Vazão negativa (impossível) - tipos de vazão: 1, 2, 8
            if tipo_medidor in (1, 2, 8) and valor < 0:
                anomalias.append({
                    'hora': hora,
                    'hora_formatada': f"{hora:02d}:00",
                    'valor_real': round(valor, 2),
                    'valor_esperado': 0,
                    'score': 1.0,
                    'severidade': 'critica',
                    'tipo': 'valor_negativo',
                    'descricao': f'Vazão negativa ({valor:.2f} L/s) - impossível fisicamente',
                    'metodo': 'regras'
                })

            # Nível acima de 100% (tipo 6 = Nível Reservatório)
            # Usa contagem de minutos >= 100 (compatível com operacoes.php)
            if tipo_medidor == 6:
                minutos_extrav = int(row.get('minutos_extravasou', 0) or 0)
                valor_max = float(row.get('valor_max', 0) or 0)

                if minutos_extrav > 0:
                    # Severidade proporcional ao tempo de extravasamento
                    if minutos_extrav > 30:
                        severidade = 'critica'
                        score = 0.95
                    elif minutos_extrav > 10:
                        severidade = 'alta'
                        score = 0.85
                    else:
                        severidade = 'media'
                        score = 0.70

                    anomalias.append({
                        'hora': hora,
                        'hora_formatada': f"{hora:02d}:00",
                        'valor_real': round(valor_max, 2),
                        'valor_esperado': 100,
                        'score': score,
                        'severidade': severidade,
                        'tipo': 'fora_faixa',
                        'descricao': (
                            f'{minutos_extrav} min >= 100% na hora {hora:02d}:00 '
                            f'(máx {valor_max:.0f}%) - risco de extravasamento'
                        ),
                        'metodo': 'regras'
                    })

            # Registros incompletos (< 50 de 60 esperados)
            if qtd > 0 and qtd < 50:
                anomalias.append({
                    'hora': hora,
                    'hora_formatada': f"{hora:02d}:00",
                    'valor_real': round(valor, 2),
                    'valor_esperado': None,
                    'score': 0.4,
                    'severidade': 'baixa',
                    'tipo': 'dados_incompletos',
                    'descricao': f'Apenas {qtd}/60 registros na hora {hora:02d}:00',
                    'metodo': 'regras'
                })

        return anomalias

    # ============================================
    # Camada 2: Análise Z-Score
    # ============================================

    def _analise_zscore(
        self,
        dados_dia: pd.DataFrame,
        historico: pd.DataFrame,
        sensibilidade: float,
        tipo_medidor: int
    ) -> List[Dict]:
        """
        Detecta anomalias via Z-score: quão longe o valor está
        da média histórica daquela hora.
        """
        anomalias = []

        # Limiar de Z-score baseado na sensibilidade
        # sensibilidade 0.8 → z_limiar ≈ 2.0
        # sensibilidade 0.5 → z_limiar ≈ 3.0
        z_limiar = 4.0 - (sensibilidade * 2.5)

        for _, row in dados_dia.iterrows():
            hora = int(row.get('hora', -1))
            valor = row.get('valor')

            if valor is None or pd.isna(valor):
                continue

            valor = float(valor)

            # Buscar histórico da mesma hora
            hist_hora = historico[historico['hora'] == hora]['valor'].dropna()

            if len(hist_hora) < 4:
                continue

            media = hist_hora.mean()
            desvio = hist_hora.std()

            if desvio < 0.01:  # Desvio muito baixo, dados constantes
                continue

            z_score = abs(valor - media) / desvio

            if z_score > z_limiar:
                # Classificar severidade
                if z_score > z_limiar * 2:
                    severidade = 'alta'
                    score = min(1.0, 0.7 + (z_score - z_limiar) * 0.05)
                elif z_score > z_limiar * 1.5:
                    severidade = 'media'
                    score = 0.5 + (z_score - z_limiar) * 0.05
                else:
                    severidade = 'baixa'
                    score = 0.3 + (z_score - z_limiar) * 0.05

                direcao = 'acima' if valor > media else 'abaixo'

                anomalias.append({
                    'hora': hora,
                    'hora_formatada': f"{hora:02d}:00",
                    'valor_real': round(valor, 2),
                    'valor_esperado': round(float(media), 2),
                    'score': round(float(min(1.0, score)), 2),
                    'severidade': severidade,
                    'tipo': f'desvio_{direcao}',
                    'descricao': (
                        f'Valor {valor:.2f} está {z_score:.1f}σ {direcao} da média '
                        f'histórica ({media:.2f}) na hora {hora:02d}:00'
                    ),
                    'metodo': 'zscore',
                    'z_score': round(float(z_score), 2)
                })

        return anomalias

    # ============================================
    # Camada 3: Autoencoder
    # ============================================

    def _analise_autoencoder(
        self,
        cd_ponto: int,
        dados_dia: pd.DataFrame,
        historico: Optional[pd.DataFrame],
        sensibilidade: float
    ) -> List[Dict]:
        """
        Detecta anomalias usando Autoencoder.
        O autoencoder aprende a reconstruir dados "normais".
        Quando o erro de reconstrução é alto, indica anomalia.
        """
        if historico is None or len(historico) < 168:
            return []

        try:
            # Preparar perfil do dia (24 valores)
            perfil_dia = np.zeros(24)
            for _, row in dados_dia.iterrows():
                hora = int(row.get('hora', 0))
                valor = row.get('valor', 0)
                if valor is not None and not pd.isna(valor) and 0 <= hora < 24:
                    perfil_dia[hora] = float(valor)

            # Preparar perfis históricos (cada dia = 24 valores)
            perfis_historicos = []
            for data_grupo, grupo in historico.groupby(historico['data'].dt.date):
                perfil = np.zeros(24)
                for _, row in grupo.iterrows():
                    h = int(row['hora'])
                    v = row['valor']
                    if v is not None and not pd.isna(v) and 0 <= h < 24:
                        perfil[h] = float(v)
                # Só usar dias com pelo menos 20 horas preenchidas
                if np.count_nonzero(perfil) >= 20:
                    perfis_historicos.append(perfil)

            if len(perfis_historicos) < 14:  # Mínimo 2 semanas de perfis
                return []

            X = np.array(perfis_historicos)

            # Normalizar
            scaler = MinMaxScaler()
            X_scaled = scaler.fit_transform(X)

            # Treinar autoencoder simples (leve, rápido)
            input_dim = 24
            encoding_dim = 8

            autoencoder = Sequential([
                Dense(16, activation='relu', input_shape=(input_dim,)),
                Dense(encoding_dim, activation='relu'),
                Dense(16, activation='relu'),
                Dense(input_dim, activation='sigmoid')
            ])
            autoencoder.compile(optimizer='adam', loss='mse')

            autoencoder.fit(
                X_scaled, X_scaled,
                epochs=50,
                batch_size=min(16, len(X_scaled)),
                shuffle=True,
                verbose=0
            )

            # Reconstruir o dia atual
            dia_scaled = scaler.transform(perfil_dia.reshape(1, -1))
            dia_reconstruido = autoencoder.predict(dia_scaled, verbose=0)
            dia_reconstruido_real = scaler.inverse_transform(dia_reconstruido)[0]

            # Calcular erro de reconstrução por hora
            erros = np.abs(perfil_dia - dia_reconstruido_real)

            # Limiar baseado nos erros do treino
            erros_treino = np.abs(X_scaled - autoencoder.predict(X_scaled, verbose=0))
            erros_treino_real = np.abs(X - scaler.inverse_transform(
                autoencoder.predict(X_scaled, verbose=0)
            ))
            limiar_por_hora = np.percentile(
                erros_treino_real,
                (1.0 - sensibilidade) * 100,
                axis=0
            )

            anomalias = []
            for hora in range(24):
                if perfil_dia[hora] == 0:
                    continue

                if erros[hora] > limiar_por_hora[hora] and limiar_por_hora[hora] > 0:
                    score = min(1.0, float(erros[hora] / (limiar_por_hora[hora] * 2)))

                    anomalias.append({
                        'hora': hora,
                        'hora_formatada': f"{hora:02d}:00",
                        'valor_real': round(float(perfil_dia[hora]), 2),
                        'valor_esperado': round(float(dia_reconstruido_real[hora]), 2),
                        'score': round(score, 2),
                        'severidade': 'media' if score > 0.6 else 'baixa',
                        'tipo': 'padrao_incomum',
                        'descricao': (
                            f'Padrão incomum na hora {hora:02d}:00: valor {perfil_dia[hora]:.2f} '
                            f'vs esperado {dia_reconstruido_real[hora]:.2f} (autoencoder)'
                        ),
                        'metodo': 'autoencoder'
                    })

            return anomalias

        except Exception as e:
            logger.warning(f"Erro no autoencoder para ponto {cd_ponto}: {e}")
            return []

    # ============================================
    # Utilitários
    # ============================================

    def _deduplicar(self, anomalias: List[Dict]) -> List[Dict]:
        """Remove anomalias duplicadas (mesma hora, mantém a de maior score)."""
        melhores = {}
        for a in anomalias:
            chave = (a.get('hora'), a.get('tipo'))
            if chave not in melhores or a.get('score', 0) > melhores[chave].get('score', 0):
                melhores[chave] = a
        return list(melhores.values())

    def _gerar_resumo(
        self,
        anomalias: List[Dict],
        score_geral: float,
        dados_dia: pd.DataFrame
    ) -> str:
        """Gera texto resumido das anomalias encontradas."""
        total_horas = len(dados_dia)

        if not anomalias:
            return (
                f"Nenhuma anomalia detectada. Todos os {total_horas} registros "
                f"horários estão dentro dos padrões esperados."
            )

        criticas = [a for a in anomalias if a.get('severidade') == 'critica']
        altas = [a for a in anomalias if a.get('severidade') == 'alta']
        medias = [a for a in anomalias if a.get('severidade') == 'media']

        partes = [f"Detectadas {len(anomalias)} anomalias."]

        if criticas:
            horas_c = ', '.join(a['hora_formatada'] for a in criticas)
            partes.append(f"CRÍTICAS ({len(criticas)}): {horas_c}.")

        if altas:
            horas_a = ', '.join(a['hora_formatada'] for a in altas)
            partes.append(f"Altas ({len(altas)}): {horas_a}.")

        if medias:
            partes.append(f"Médias: {len(medias)}.")

        if score_geral > 0.7:
            partes.append("Recomenda-se revisão urgente dos dados deste dia.")
        elif score_geral > 0.4:
            partes.append("Alguns valores merecem atenção.")

        return ' '.join(partes)
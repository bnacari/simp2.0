#!/usr/bin/env python3
"""
SIMP - Script de Treino Offline XGBoost (v6.0)
=============================================================
Treina modelos XGBoost que aprendem: "dados os valores ATUAIS e RECENTES
das tags auxiliares + momento do dia/semana, qual deveria ser o valor
da tag principal?"

v6.0 - Migração FINDESLAB → SIMP:
  - Busca relações de AUX_RELACAO_PONTOS_MEDICAO no banco SIMP
  - Busca pontos de PONTO_MEDICAO no banco SIMP
  - Busca dados de REGISTRO_VAZAO_PRESSAO no banco SIMP
  - Elimina dependência do banco FINDESLAB
  - Mapeia TAG → CD_PONTO_MEDICAO → campo de valor automaticamente

Features por linha:
  [0..N-1]     aux_TAG_*_t0       → valor atual de cada auxiliar
  [N..2N-1]    aux_TAG_*_t1       → valor 1 hora atrás
  [2N..3N-1]   aux_TAG_*_t3       → valor 3 horas atrás
  [3N..4N-1]   aux_TAG_*_t6       → valor 6 horas atrás
  [4N]         hora_sin            → codificação cíclica da hora
  [4N+1]       hora_cos
  [4N+2]       dia_sem_sin         → codificação cíclica do dia
  [4N+3]       dia_sem_cos

Target:
  valor_principal (escala real, sem normalização)

Uso:
  python3 treinar_modelos.py                                    # Treinar todos
  python3 treinar_modelos.py --tag GPRS050_M010_MED             # Treinar só uma tag
  python3 treinar_modelos.py --semanas 52                       # 1 ano de histórico
  python3 treinar_modelos.py --bloco 1 --total-blocos 7         # Cron diário
  python3 treinar_modelos.py --workers 3                        # Paralelo

Após treino:
  docker cp modelos_treinados/. $(docker ps -q -f name=tensorflow):/app/models/

@author Bruno - CESAN
@version 6.0
@date 2026-02
"""

import os
import sys
import json
import pickle
import argparse
import logging
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Tuple
from concurrent.futures import ProcessPoolExecutor, as_completed

import numpy as np
import pandas as pd
import pyodbc
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
import xgboost as xgb

# ============================================
# Configuração
# ============================================

# v6.0: Usa banco SIMP diretamente (sem FINDESLAB)
DB_CONFIG = {
    'server': os.environ.get('DB_HOST', r'sgbd-dev-simp.sistemas.cesan.com.br\corporativo'),
    'database': os.environ.get('DB_NAME', 'simp'),
    'user': os.environ.get('DB_USER', 'simp'),
    'password': os.environ.get('DB_PASS', 'cesan'),
    'driver': '{ODBC Driver 17 for SQL Server}'
}

OUTPUT_DIR = os.environ.get('MODELS_DIR', './modelos_treinados')

# Lags das auxiliares: valor atual + 1h atrás + 3h atrás + 6h atrás
# Isso dá ao modelo contexto de tendência recente sem janela de 168h
LAGS = [0, 1, 3, 6]

# Parâmetros XGBoost
XGB_PARAMS = {
    'n_estimators': 500,        # Máximo de árvores (early stopping corta antes)
    'max_depth': 6,             # Profundidade de cada árvore
    'learning_rate': 0.05,      # Taxa de aprendizado conservadora
    'subsample': 0.8,           # 80% das amostras por árvore
    'colsample_bytree': 0.8,    # 80% das features por árvore
    'min_child_weight': 5,      # Mínimo de amostras por folha
    'reg_alpha': 0.1,           # Regularização L1
    'reg_lambda': 1.0,          # Regularização L2
    'random_state': 42,
    'n_jobs': -1,               # Usar todos os cores
}

VALIDATION_SPLIT = 0.15     # 15% para validação
EARLY_STOPPING = 30         # Parar se não melhorar em 30 rodadas

# Logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S'
)
logger = logging.getLogger('treino')


# ============================================
# Conexão com o banco SIMP
# ============================================

def conectar_banco() -> pyodbc.Connection:
    """
    Conecta ao banco SIMP.
    v6.0: Conexão direta ao SIMP (sem FINDESLAB).
    """
    conn_str = (
        f"DRIVER={DB_CONFIG['driver']};"
        f"SERVER={DB_CONFIG['server']};"
        f"DATABASE={DB_CONFIG['database']};"
        f"UID={DB_CONFIG['user']};"
        f"PWD={DB_CONFIG['password']};"
        f"TrustServerCertificate=yes;"
        f"Connection Timeout=30;"
    )
    return pyodbc.connect(conn_str)


# ============================================
# Consultas ao banco SIMP
# ============================================

def buscar_relacoes(conn: pyodbc.Connection, tag_filtro: str = None) -> Dict[str, List[str]]:
    """
    Busca relações TAG_PRINCIPAL → [TAG_AUXILIAR, ...].
    v6.0: Usa tabela AUX_RELACAO_PONTOS_MEDICAO no banco SIMP.
    """
    sql = """
        SELECT TAG_PRINCIPAL, TAG_AUXILIAR
        FROM SIMP.dbo.AUX_RELACAO_PONTOS_MEDICAO
        WHERE LTRIM(RTRIM(TAG_PRINCIPAL)) <> LTRIM(RTRIM(TAG_AUXILIAR))
    """
    params = []
    if tag_filtro:
        sql += " AND TAG_PRINCIPAL = ?"
        params.append(tag_filtro)
    
    sql += " ORDER BY TAG_PRINCIPAL, TAG_AUXILIAR"
    df = pd.read_sql(sql, conn, params=params)
    
    relacoes = {}
    for _, row in df.iterrows():
        principal = row['TAG_PRINCIPAL'].strip()
        auxiliar = row['TAG_AUXILIAR'].strip()
        if principal not in relacoes:
            relacoes[principal] = []
        relacoes[principal].append(auxiliar)
    
    logger.info(f"Relações carregadas: {len(relacoes)} tags principais")
    for tag, auxs in relacoes.items():
        logger.info(f"  {tag} → {len(auxs)} auxiliar(es): {', '.join(auxs)}")
    
    return relacoes


def buscar_pontos_medicao(conn: pyodbc.Connection) -> pd.DataFrame:
    """
    Busca mapeamento TAG → CD_PONTO_MEDICAO.
    v6.0: Usa tabela PONTO_MEDICAO do SIMP com todas as colunas de TAG.
    Retorna DataFrame com colunas: CD_PONTO_MEDICAO, TAG, ID_TIPO_MEDIDOR, NM_PONTO_MEDICAO
    """
    sql = """
        SELECT 
            CD_PONTO_MEDICAO,
            COALESCE(DS_TAG_VAZAO, DS_TAG_PRESSAO, DS_TAG_RESERVATORIO) AS TAG,
            ID_TIPO_MEDIDOR,
            DS_NOME AS NM_PONTO_MEDICAO
        FROM SIMP.dbo.PONTO_MEDICAO
        WHERE (DT_DESATIVACAO IS NULL OR DT_DESATIVACAO > GETDATE())
          AND (
              DS_TAG_VAZAO IS NOT NULL OR 
              DS_TAG_PRESSAO IS NOT NULL OR 
              DS_TAG_RESERVATORIO IS NOT NULL
          )
    """
    df = pd.read_sql(sql, conn)
    logger.info(f"Pontos de medição carregados: {len(df)}")
    return df


def _mapear_tag_para_campo(conn: pyodbc.Connection, tag: str) -> Tuple[Optional[int], str]:
    """
    Dado um TagName, retorna (CD_PONTO_MEDICAO, campo_valor).
    Busca em DS_TAG_VAZAO, DS_TAG_PRESSAO e DS_TAG_RESERVATORIO.
    
    Returns:
        Tupla (cd_ponto, campo_valor) ou (None, '') se não encontrar
    """
    sql = """
        SELECT TOP 1
            CD_PONTO_MEDICAO,
            DS_TAG_VAZAO,
            DS_TAG_PRESSAO,
            DS_TAG_RESERVATORIO
        FROM SIMP.dbo.PONTO_MEDICAO
        WHERE DS_TAG_VAZAO = ? OR DS_TAG_PRESSAO = ? OR DS_TAG_RESERVATORIO = ?
    """
    cursor = conn.cursor()
    cursor.execute(sql, tag, tag, tag)
    row = cursor.fetchone()
    
    if not row:
        return None, ''
    
    cd_ponto = row[0]
    tag_vazao = (row[1] or '').strip()
    tag_pressao = (row[2] or '').strip()
    tag_reservatorio = (row[3] or '').strip()
    
    if tag == tag_vazao:
        return cd_ponto, 'VL_VAZAO'
    elif tag == tag_pressao:
        return cd_ponto, 'VL_PRESSAO'
    elif tag == tag_reservatorio:
        return cd_ponto, 'VL_RESERVATORIO'
    
    return None, ''


def buscar_dados_tags(
    conn: pyodbc.Connection,
    tags: List[str],
    semanas: int = 24
) -> pd.DataFrame:
    """
    Busca dados históricos horários de múltiplas tags.
    v6.0: Usa REGISTRO_VAZAO_PRESSAO do SIMP em vez de RawDataIntouch do FINDESLAB.
    Mapeia cada TAG → CD_PONTO_MEDICAO + campo de valor correto.
    Tags sem ponto cadastrado no SIMP são ignoradas.
    
    Returns:
        DataFrame com colunas: data_hora, tag, valor, qtd_registros
    """
    data_inicio = datetime.now() - timedelta(weeks=semanas)
    
    # ============================================
    # 1. Mapear cada tag para CD_PONTO + campo valor
    # ============================================
    mapeamento = {}  # tag → (cd_ponto, campo_valor)
    for tag in tags:
        cd_ponto, campo_valor = _mapear_tag_para_campo(conn, tag)
        if cd_ponto is not None:
            mapeamento[tag] = (cd_ponto, campo_valor)
    
    tags_encontradas = set(mapeamento.keys())
    tags_ignoradas = set(tags) - tags_encontradas
    
    if tags_ignoradas:
        logger.warning(f"  Tags ignoradas (sem ponto no SIMP): {', '.join(sorted(tags_ignoradas))}")
    
    if not mapeamento:
        logger.warning(f"  Nenhuma tag encontrada no SIMP!")
        return pd.DataFrame(columns=['data_hora', 'tag', 'valor', 'qtd_registros'])
    
    logger.info(f"  Tags mapeadas: {len(mapeamento)} de {len(tags)}")
    
    # ============================================
    # 2. Agrupar por campo de valor para otimizar queries
    # ============================================
    # {campo_valor: [(tag, cd_ponto), ...]}
    grupos = {}
    for tag, (cd_ponto, campo_valor) in mapeamento.items():
        if campo_valor not in grupos:
            grupos[campo_valor] = []
        grupos[campo_valor].append((tag, cd_ponto))
    
    # ============================================
    # 3. Buscar dados de cada grupo
    # ============================================
    todos_dados = []
    
    for campo_valor, tags_pontos in grupos.items():
        cd_pontos = [cp for _, cp in tags_pontos]
        ponto_para_tag = {cp: tag for tag, cp in tags_pontos}
        
        placeholders = ','.join(['?' for _ in cd_pontos])
        
        sql = f"""
            SELECT 
                CAST(CAST(DT_LEITURA AS DATE) AS DATETIME) 
                    + CAST(DATEPART(HOUR, DT_LEITURA) AS FLOAT) / 24.0 AS data_hora,
                CD_PONTO_MEDICAO,
                AVG(CASE WHEN ID_SITUACAO = 1 THEN {campo_valor} ELSE NULL END) AS valor,
                COUNT(CASE WHEN ID_SITUACAO = 1 THEN 1 END) AS qtd_registros
            FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
            WHERE CD_PONTO_MEDICAO IN ({placeholders})
              AND DT_LEITURA >= ?
              AND ID_SITUACAO = 1
            GROUP BY 
                CAST(CAST(DT_LEITURA AS DATE) AS DATETIME) 
                    + CAST(DATEPART(HOUR, DT_LEITURA) AS FLOAT) / 24.0,
                CD_PONTO_MEDICAO
            ORDER BY data_hora, CD_PONTO_MEDICAO
        """
        
        params = cd_pontos + [data_inicio]
        cursor = conn.cursor()
        cursor.execute(sql, params)
        rows = cursor.fetchall()
        
        for row in rows:
            data_hora = row[0]
            cd_ponto = row[1]
            valor = row[2]
            qtd = row[3]
            tag = ponto_para_tag.get(cd_ponto)
            if tag and valor is not None:
                todos_dados.append({
                    'data_hora': data_hora,
                    'tag': tag,
                    'valor': float(valor),
                    'qtd_registros': int(qtd)
                })
    
    if not todos_dados:
        logger.warning(f"  Nenhum dado encontrado!")
        return pd.DataFrame(columns=['data_hora', 'tag', 'valor', 'qtd_registros'])
    
    df = pd.DataFrame(todos_dados)
    df['data_hora'] = pd.to_datetime(df['data_hora'])
    df['valor'] = pd.to_numeric(df['valor'], errors='coerce')
    
    tags_com_dados = df['tag'].nunique()
    logger.info(f"  → {len(df)} registros horários de {tags_com_dados} tags")
    
    return df


# ============================================
# Preparação dos dados para XGBoost
# ============================================

def preparar_dados_treino(
    dados: pd.DataFrame,
    tag_principal: str,
    tags_auxiliares: List[str]
) -> Optional[Tuple[pd.DataFrame, pd.Series, List[str]]]:
    """
    Prepara dados TABULARES para XGBoost.
    
    Cada linha = 1 hora. Features:
      - Valor atual (t0) de cada tag auxiliar
      - Valor com lag de 1h, 3h, 6h de cada tag auxiliar
      - Hora e dia da semana (codificação cíclica)
    
    Target = valor do ponto principal naquela hora.
    
    O modelo aprende correlação DIRETA entre auxiliares → principal.
    Sem janela deslizante, sem sequências.
    
    Returns:
        Tupla (X_df, y_series, feature_names) ou None
    """
    # Pivotar: cada tag vira coluna
    pivot = dados.pivot_table(
        index='data_hora',
        columns='tag',
        values='valor',
        aggfunc='mean'
    )
    
    if tag_principal not in pivot.columns:
        logger.error(f"Tag principal '{tag_principal}' não encontrada nos dados!")
        return None
    
    # Verificar quais auxiliares existem
    tags_aux_disponiveis = [t for t in tags_auxiliares if t in pivot.columns]
    if not tags_aux_disponiveis:
        logger.error(f"Nenhuma tag auxiliar com dados! Impossível treinar.")
        return None
    
    logger.info(f"  Tags auxiliares com dados: {len(tags_aux_disponiveis)}/{len(tags_auxiliares)}")
    
    # Reindexar para todas as horas
    full_range = pd.date_range(
        start=pivot.index.min(),
        end=pivot.index.max(),
        freq='h'
    )
    pivot = pivot.reindex(full_range)
    
    # Interpolar gaps curtos
    pivot = pivot.interpolate(method='linear', limit=6)
    pivot = pivot.ffill(limit=3).bfill(limit=3)
    
    # =============================================
    # Montar features tabulares
    # =============================================
    feature_names = []
    features = pd.DataFrame(index=pivot.index)
    
    # Para cada auxiliar: valor atual + lags
    for tag_aux in tags_aux_disponiveis:
        for lag in LAGS:
            col_name = f'aux_{tag_aux}_t{lag}'
            features[col_name] = pivot[tag_aux].shift(lag)
            feature_names.append(col_name)
    
    # Features temporais (codificação cíclica)
    features['hora_sin'] = np.sin(2 * np.pi * features.index.hour / 24)
    features['hora_cos'] = np.cos(2 * np.pi * features.index.hour / 24)
    features['dia_sem_sin'] = np.sin(2 * np.pi * features.index.dayofweek / 7)
    features['dia_sem_cos'] = np.cos(2 * np.pi * features.index.dayofweek / 7)
    feature_names.extend(['hora_sin', 'hora_cos', 'dia_sem_sin', 'dia_sem_cos'])
    
    # Target: valor do ponto principal
    target = pivot[tag_principal]
    
    # Combinar e limpar NaN
    combined = features.copy()
    combined['_target'] = target
    combined = combined.dropna()
    
    if len(combined) < 100:
        logger.error(f"  Dados insuficientes após limpeza: {len(combined)} (mínimo: 100)")
        return None
    
    X = combined[feature_names]
    y = combined['_target']
    
    logger.info(f"  Features: {len(feature_names)} colunas ({len(tags_aux_disponiveis)} auxiliares × {len(LAGS)} lags + 4 temporais)")
    logger.info(f"  Amostras: {len(X)} horas")
    logger.info(f"  Target range: {y.min():.2f} ~ {y.max():.2f} (média: {y.mean():.2f})")
    
    return X, y, feature_names


# ============================================
# Treinamento do modelo XGBoost
# ============================================

def treinar_modelo(
    X: pd.DataFrame,
    y: pd.Series,
    tag_principal: str
) -> Tuple[xgb.XGBRegressor, dict, dict]:
    """
    Treina modelo XGBoost de correlação de rede.
    
    Vantagens sobre LSTM:
      - Cada predição é independente (sem feedback/drift)
      - Não tende à média (árvores de decisão particionam o espaço)
      - Treina em segundos
      - Feature importance interpretável
    
    Returns:
        Tupla (modelo, metricas, feature_importance)
    """
    # Split temporal (últimos 15% para validação)
    split = int(len(X) * (1 - VALIDATION_SPLIT))
    X_train, X_val = X.iloc[:split], X.iloc[split:]
    y_train, y_val = y.iloc[:split], y.iloc[split:]
    
    logger.info(f"  Treino: {len(X_train)} | Validação: {len(X_val)} | Features: {X.shape[1]}")
    
    # Criar e treinar modelo
    modelo = xgb.XGBRegressor(
        **XGB_PARAMS,
        early_stopping_rounds=EARLY_STOPPING,
        eval_metric='mae'
    )
    
    logger.info(f"  Treinando XGBoost para '{tag_principal}'...")
    modelo.fit(
        X_train, y_train,
        eval_set=[(X_val, y_val)],
        verbose=False
    )
    
    # =============================================
    # Avaliar (métricas em escala REAL — sem normalização)
    # =============================================
    y_pred = modelo.predict(X_val)
    
    mae = mean_absolute_error(y_val, y_pred)
    rmse = np.sqrt(mean_squared_error(y_val, y_pred))
    r2 = r2_score(y_val, y_pred)
    
    # Correlação
    if y_val.std() > 0 and np.std(y_pred) > 0:
        correlacao = float(np.corrcoef(y_val, y_pred)[0, 1])
    else:
        correlacao = 0.0
    
    # MAPE
    mask = y_val > 0
    if mask.sum() > 0:
        mape = float(np.mean(np.abs((y_val[mask] - y_pred[mask]) / y_val[mask]))) * 100
    else:
        mape = 0.0
    
    # Feature importance (top 10)
    importances = modelo.feature_importances_
    feature_names = X.columns.tolist()
    fi_dict = dict(zip(feature_names, [round(float(v), 4) for v in importances]))
    fi_sorted = dict(sorted(fi_dict.items(), key=lambda x: x[1], reverse=True))
    
    # Número de árvores usadas (early stopping pode cortar)
    n_arvores = modelo.best_iteration + 1 if hasattr(modelo, 'best_iteration') and modelo.best_iteration is not None else XGB_PARAMS['n_estimators']
    
    metricas = {
        'mae': round(float(mae), 4),
        'rmse': round(float(rmse), 4),
        'r2': round(float(r2), 4),
        'correlacao': round(correlacao, 4),
        'mape_pct': round(mape, 2),
        'n_arvores': int(n_arvores),
        'max_depth': XGB_PARAMS['max_depth'],
        'learning_rate': XGB_PARAMS['learning_rate'],
        'amostras_treino': len(X_train),
        'amostras_validacao': len(X_val),
        'n_features': X.shape[1]
    }
    
    logger.info(f"  ✓ MAE={mae:.4f} | RMSE={rmse:.4f} | R²={r2:.4f}")
    logger.info(f"  ✓ Correlação: {correlacao:.4f} | MAPE: {mape:.1f}%")
    logger.info(f"  ✓ Árvores: {n_arvores} (de {XGB_PARAMS['n_estimators']})")
    
    # Log top 5 features mais importantes
    top5 = list(fi_sorted.items())[:5]
    logger.info(f"  ✓ Top 5 features:")
    for fname, imp in top5:
        logger.info(f"      {fname}: {imp:.4f}")
    
    return modelo, metricas, fi_sorted


# ============================================
# Salvar modelo
# ============================================

def salvar_modelo(
    cd_ponto: int,
    tag_principal: str,
    tags_auxiliares: List[str],
    modelo: xgb.XGBRegressor,
    feature_names: List[str],
    metricas: dict,
    feature_importance: dict,
    tipo_medidor: int,
    output_dir: str = None
):
    """
    Salva modelo XGBoost treinado.
    
    v6.0: Referencia banco SIMP nos metadados (sem FINDESLAB).
    Salva modelo nativo XGBoost (model.json) + metadados.
    Sem scaler (XGBoost trabalha com escala real).
    """
    base_dir = output_dir if output_dir else OUTPUT_DIR
    ponto_dir = os.path.join(base_dir, f"ponto_{cd_ponto}")
    os.makedirs(ponto_dir, exist_ok=True)
    
    # 1. Modelo XGBoost (formato nativo JSON — portável)
    model_path = os.path.join(ponto_dir, 'model.json')
    modelo.save_model(model_path)
    logger.info(f"  → Modelo salvo: {model_path}")
    
    # 2. Metadados completos
    metadados = {
        **metricas,
        'cd_ponto_medicao': cd_ponto,
        'tag_principal': tag_principal,
        'tags_auxiliares': tags_auxiliares,
        'feature_names': feature_names,
        'feature_importance': feature_importance,
        'tipo_medidor': tipo_medidor,
        'lags': LAGS,
        'modelo_tipo': 'xgboost',
        'target_tipo': 'correlacao',
        'treinado_em': datetime.now().isoformat(),
        'banco_treino': f"{DB_CONFIG['server']}\\{DB_CONFIG['database']}",
        'versao_treino': '6.0'
    }
    
    metricas_path = os.path.join(ponto_dir, 'metricas.json')
    with open(metricas_path, 'w', encoding='utf-8') as f:
        json.dump(metadados, f, indent=2, ensure_ascii=False)
    
    logger.info(f"  → Metadados salvos: {metricas_path}")
    
    # Limpar arquivos de versões anteriores (LSTM)
    for old_file in ['model.h5', 'scaler.pkl', 'target_scaler.pkl']:
        old_path = os.path.join(ponto_dir, old_file)
        if os.path.exists(old_path):
            os.remove(old_path)
            logger.info(f"  → Removido arquivo legado: {old_file}")


# ============================================
# Treino de um único ponto (para paralelização)
# ============================================

def treinar_ponto(args_tupla: tuple) -> Tuple[str, bool, str, float]:
    """Treina um único ponto. Isolado para ProcessPoolExecutor."""
    tag_principal, tags_auxiliares, cd_ponto, tipo_medidor, semanas, output_dir = args_tupla
    inicio_ponto = datetime.now()
    
    try:
        conn = conectar_banco()
        
        todas_tags = [tag_principal] + tags_auxiliares
        dados = buscar_dados_tags(conn, todas_tags, semanas)
        
        if dados.empty:
            conn.close()
            return tag_principal, False, "Sem dados", (datetime.now() - inicio_ponto).total_seconds()
        
        resultado = preparar_dados_treino(dados, tag_principal, tags_auxiliares)
        
        if resultado is None:
            conn.close()
            return tag_principal, False, "Dados insuficientes", (datetime.now() - inicio_ponto).total_seconds()
        
        X, y, feature_names = resultado
        
        modelo, metricas, feature_importance = treinar_modelo(X, y, tag_principal)
        
        salvar_modelo(
            cd_ponto=cd_ponto,
            tag_principal=tag_principal,
            tags_auxiliares=tags_auxiliares,
            modelo=modelo,
            feature_names=feature_names,
            metricas=metricas,
            feature_importance=feature_importance,
            tipo_medidor=tipo_medidor,
            output_dir=output_dir
        )
        
        conn.close()
        duracao = (datetime.now() - inicio_ponto).total_seconds()
        r2 = metricas.get('r2', 0)
        mape = metricas.get('mape_pct', 0)
        return tag_principal, True, f"R²={r2:.3f} MAPE={mape:.1f}%", duracao
        
    except Exception as e:
        return tag_principal, False, str(e), (datetime.now() - inicio_ponto).total_seconds()


# ============================================
# Fluxo principal
# ============================================

def treinar_todos(
    tag_filtro: str = None,
    semanas: int = 24,
    bloco: int = None,
    total_blocos: int = 7,
    workers: int = 1
):
    """Fluxo principal de treino."""
    inicio = datetime.now()
    logger.info("=" * 60)
    logger.info("SIMP - Treino XGBoost v6.0 (Dados via SIMP)")
    logger.info(f"Banco: {DB_CONFIG['server']}\\{DB_CONFIG['database']}")
    logger.info(f"Histórico: {semanas} semanas")
    logger.info(f"Abordagem: auxiliares(t0,t-1,t-3,t-6) + temporais → principal")
    logger.info(f"Modelo: XGBoost (max {XGB_PARAMS['n_estimators']} árvores, depth={XGB_PARAMS['max_depth']})")
    logger.info(f"Lags: {LAGS}")
    if tag_filtro:
        logger.info(f"Filtro: apenas tag '{tag_filtro}'")
    if bloco is not None:
        logger.info(f"Bloco: {bloco}/{total_blocos}")
    logger.info("=" * 60)
    
    conn = conectar_banco()
    logger.info("Conexão estabelecida com sucesso.")
    
    # v6.0: Busca relações e pontos do banco SIMP
    relacoes = buscar_relacoes(conn, tag_filtro)
    if not relacoes:
        logger.error("Nenhuma relação encontrada!")
        conn.close()
        return
    
    pontos_df = buscar_pontos_medicao(conn)
    tag_to_ponto = {}
    tag_to_tipo = {}
    for _, row in pontos_df.iterrows():
        tag = row['TAG'].strip() if row['TAG'] else ''
        tag_to_ponto[tag] = int(row['CD_PONTO_MEDICAO'])
        tag_to_tipo[tag] = int(row['ID_TIPO_MEDIDOR'])
    
    conn.close()
    
    # Filtrar por bloco
    tags_lista = list(relacoes.keys())
    total_geral = len(tags_lista)
    
    if bloco is not None:
        tamanho_bloco = total_geral // total_blocos + (1 if total_geral % total_blocos else 0)
        idx_inicio = (bloco - 1) * tamanho_bloco
        idx_fim = min(idx_inicio + tamanho_bloco, total_geral)
        tags_lista = tags_lista[idx_inicio:idx_fim]
        logger.info(f"Bloco {bloco}/{total_blocos}: tags {idx_inicio + 1} a {idx_fim} de {total_geral}")
    
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    
    # Montar tarefas
    tarefas = []
    for tag_principal in tags_lista:
        tags_auxiliares = relacoes[tag_principal]
        cd_ponto = tag_to_ponto.get(tag_principal)
        tipo_medidor = tag_to_tipo.get(tag_principal, 1)
        if cd_ponto is None:
            logger.warning(f"  TAG '{tag_principal}' sem CD_PONTO_MEDICAO, usando hash")
            cd_ponto = abs(hash(tag_principal)) % 100000
        tarefas.append((tag_principal, tags_auxiliares, cd_ponto, tipo_medidor, semanas, OUTPUT_DIR))
    
    total_tarefas = len(tarefas)
    sucesso = 0
    falha = 0
    
    if workers <= 1:
        for idx, tarefa in enumerate(tarefas, 1):
            logger.info("")
            logger.info(f"[{idx}/{total_tarefas}] TAG: {tarefa[0]}")
            logger.info(f"  Auxiliares: {', '.join(tarefa[1])}")
            logger.info(f"  CD_PONTO: {tarefa[2]} | Tipo: {tarefa[3]}")
            
            tag, ok, msg, duracao = treinar_ponto(tarefa)
            if ok:
                sucesso += 1
                logger.info(f"  ✓ {msg} ({duracao:.1f}s)")
            else:
                falha += 1
                logger.error(f"  ✗ {msg}")
    else:
        logger.info(f"\nTreino paralelo: {workers} workers × {total_tarefas} pontos...")
        with ProcessPoolExecutor(max_workers=workers) as executor:
            futuros = {executor.submit(treinar_ponto, t): t[0] for t in tarefas}
            for idx, futuro in enumerate(as_completed(futuros), 1):
                tag_principal = futuros[futuro]
                try:
                    tag, ok, msg, duracao = futuro.result(timeout=300)
                    if ok:
                        sucesso += 1
                        logger.info(f"  [{idx}/{total_tarefas}] ✓ {tag}: {msg} ({duracao:.1f}s)")
                    else:
                        falha += 1
                        logger.error(f"  [{idx}/{total_tarefas}] ✗ {tag}: {msg}")
                except Exception as e:
                    falha += 1
                    logger.error(f"  [{idx}/{total_tarefas}] ✗ {tag_principal}: {e}")
    
    # Resumo
    duracao_total = datetime.now() - inicio
    logger.info("")
    logger.info("=" * 60)
    logger.info("RESUMO v6.0 (XGBoost via SIMP)")
    logger.info(f"  Total: {total_tarefas}" + (f" (de {total_geral})" if bloco else ""))
    logger.info(f"  Sucesso: {sucesso} | Falha: {falha}")
    logger.info(f"  Duração: {duracao_total}")
    if sucesso > 0:
        media_seg = duracao_total.total_seconds() / sucesso
        logger.info(f"  Média/ponto: {media_seg:.1f}s")
    logger.info(f"  Modelos: {os.path.abspath(OUTPUT_DIR)}/")
    logger.info("")
    logger.info("Deploy:")
    logger.info(f"  docker cp {OUTPUT_DIR}/. $(docker ps -q -f name=tensorflow):/app/models/")
    logger.info("  docker service update --force simp20-php_simp20-tensorflow")
    logger.info("=" * 60)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(
        description='SIMP - Treino XGBoost v6.0 (Dados via SIMP)',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Exemplos:
  python treinar_modelos.py                                    # Treinar todos
  python treinar_modelos.py --tag GPRS050_M010_MED             # Treinar só uma tag
  python treinar_modelos.py --semanas 52                       # 1 ano
  python treinar_modelos.py --workers 3                        # Paralelo
  python treinar_modelos.py --bloco 1 --total-blocos 7         # Cron
        """
    )
    
    parser.add_argument('--tag', type=str, default=None)
    parser.add_argument('--semanas', type=int, default=24)
    parser.add_argument('--output', type=str, default=OUTPUT_DIR)
    parser.add_argument('--bloco', type=int, default=None)
    parser.add_argument('--total-blocos', type=int, default=7)
    parser.add_argument('--workers', type=int, default=1)
    
    args = parser.parse_args()
    
    if args.bloco is not None and (args.bloco < 1 or args.bloco > args.total_blocos):
        parser.error(f"--bloco deve ser entre 1 e {args.total_blocos}")
    if args.workers < 1:
        parser.error("--workers deve ser >= 1")
    if args.output != OUTPUT_DIR:
        OUTPUT_DIR = args.output
    
    treinar_todos(
        tag_filtro=args.tag,
        semanas=args.semanas,
        bloco=args.bloco,
        total_blocos=args.total_blocos,
        workers=args.workers
    )
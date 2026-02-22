"""
Módulo de busca de dados das tags auxiliares para predição XGBoost.
Busca dados diretamente do banco SIMP (REGISTRO_VAZAO_PRESSAO),
eliminando a dependência do banco FINDESLAB.

v6.0 - Busca via SIMP:
  - Mapeia TAG → CD_PONTO_MEDICAO via PONTO_MEDICAO
  - Busca dados de REGISTRO_VAZAO_PRESSAO (mesmo banco do SIMP)
  - Tags não encontradas no SIMP são ignoradas (XGBoost lida com NaN)
  - Sem necessidade de conexão externa ao FINDESLAB
  - Mesma interface (buscar_dados_tags_recentes / montar_features_xgboost)

@author Bruno - CESAN
@version 6.0
@date 2026-02
"""

import os
import logging
import pyodbc
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from typing import List, Optional, Dict, Tuple

logger = logging.getLogger('simp-tensorflow.findeslab')


def _get_simp_connection() -> Optional[pyodbc.Connection]:
    """
    Conecta ao banco SIMP usando as mesmas variáveis de ambiente
    do container (DB_HOST, DB_NAME, DB_USER, DB_PASS).
    Reutiliza a mesma conexão que o database.py já usa.
    
    Returns:
        Conexão pyodbc ou None se falhar
    """
    host = os.environ.get('DB_HOST', '')
    db = os.environ.get('DB_NAME', 'simp')
    user = os.environ.get('DB_USER', 'simp')
    password = os.environ.get('DB_PASS', '')

    if not host:
        logger.warning("DB_HOST não configurado")
        return None

    try:
        # Detectar driver ODBC disponível
        driver = '{ODBC Driver 18 for SQL Server}'
        available = pyodbc.drivers()
        if 'ODBC Driver 18 for SQL Server' not in available:
            if 'ODBC Driver 17 for SQL Server' in available:
                driver = '{ODBC Driver 17 for SQL Server}'
            else:
                logger.error(f"Nenhum driver ODBC encontrado. Disponíveis: {available}")
                return None

        # Tratar instância nomeada (ex: servidor\corporativo)
        server = host.replace('\\\\', '\\')

        conn_str = (
            f"DRIVER={driver};"
            f"SERVER={server};"
            f"DATABASE={db};"
            f"UID={user};"
            f"PWD={password};"
            f"TrustServerCertificate=yes;"
            f"Connection Timeout=30;"
        )
        conn = pyodbc.connect(conn_str)
        logger.info(f"Conectado ao SIMP ({host})")
        return conn
    except Exception as e:
        logger.error(f"Erro ao conectar SIMP: {e}")
        return None


def _mapear_tags_para_pontos(
    conn: pyodbc.Connection,
    tags: List[str]
) -> Dict[str, Tuple[int, str]]:
    """
    Mapeia TagNames para CD_PONTO_MEDICAO e campo de valor correspondente.
    
    Busca em DS_TAG_VAZAO, DS_TAG_PRESSAO e DS_TAG_RESERVATORIO.
    Determina automaticamente qual campo de valor usar (VL_VAZAO, VL_PRESSAO, VL_RESERVATORIO).
    
    Args:
        conn: Conexão pyodbc com o SIMP
        tags: Lista de TagNames das auxiliares
        
    Returns:
        Dict {tag: (cd_ponto, campo_valor)} para tags encontradas.
        Tags não encontradas são omitidas (ignoradas).
    """
    if not tags:
        return {}

    # Montar placeholders para IN clause
    placeholders = ','.join(['?' for _ in tags])

    sql = f"""
            SELECT 
                PM.CD_PONTO_MEDICAO,
                PM.DS_TAG_VAZAO,
                PM.DS_TAG_PRESSAO,
                PM.DS_TAG_RESERVATORIO,
                CASE WHEN PM.DT_DESATIVACAO IS NULL THEN 0 ELSE 1 END AS DESATIVADO,
                ISNULL(RVP.ULT_LEITURA, '1900-01-01') AS ULT_LEITURA
            FROM SIMP.dbo.PONTO_MEDICAO PM
            LEFT JOIN (
                SELECT CD_PONTO_MEDICAO, MAX(DT_LEITURA) AS ULT_LEITURA
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                WHERE ID_SITUACAO = 1
                GROUP BY CD_PONTO_MEDICAO
            ) RVP ON RVP.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
            WHERE PM.DS_TAG_VAZAO IN ({placeholders})
            OR PM.DS_TAG_PRESSAO IN ({placeholders})
            OR PM.DS_TAG_RESERVATORIO IN ({placeholders})
            ORDER BY DESATIVADO ASC, ULT_LEITURA DESC
        """

    # Triplicar parâmetros (uma vez para cada IN clause)
    params = list(tags) * 3

    cursor = conn.cursor()
    cursor.execute(sql, params)
    rows = cursor.fetchall()

    mapeamento = {} 
    for row in rows:
        cd_ponto = row[0]
        tag_vazao = (row[1] or '').strip()
        tag_pressao = (row[2] or '').strip()
        tag_reservatorio = (row[3] or '').strip()

        for tag in tags:
            if tag in mapeamento:
                continue  # Já mapeada pelo ponto com melhor ranking
            if tag == tag_vazao:
                mapeamento[tag] = (cd_ponto, 'VL_VAZAO')
            elif tag == tag_pressao:
                mapeamento[tag] = (cd_ponto, 'VL_PRESSAO')
            elif tag == tag_reservatorio:
                mapeamento[tag] = (cd_ponto, 'VL_RESERVATORIO')

    # Logar tags encontradas e ignoradas
    tags_encontradas = set(mapeamento.keys())
    tags_ignoradas = set(tags) - tags_encontradas
    
    if tags_encontradas:
        logger.info(f"Tags mapeadas no SIMP: {len(tags_encontradas)} de {len(tags)}")
    if tags_ignoradas:
        logger.warning(f"Tags ignoradas (sem ponto no SIMP): {', '.join(sorted(tags_ignoradas))}")

    return mapeamento


def buscar_dados_tags_recentes(
    tags: List[str],
    horas_necessarias: int = 12,
    data_alvo: str = None
) -> Optional[pd.DataFrame]:
    """
    Busca dados horários recentes das tags auxiliares no banco SIMP.
    
    v6.0: Usa REGISTRO_VAZAO_PRESSAO do SIMP em vez do FINDESLAB.
    Mapeia TAG → CD_PONTO_MEDICAO e busca o campo de valor correto.
    Tags sem ponto cadastrado no SIMP são ignoradas.
    
    Args:
        tags: Lista de TagNames das auxiliares
        horas_necessarias: Horas para buscar (default 12, cobre lag máximo de 6)
        data_alvo: Data alvo no formato YYYY-MM-DD (opcional)

    Returns:
        DataFrame com colunas: data_hora, tag, valor (mesmo formato do FINDESLAB)
    """
    conn = _get_simp_connection()
    if conn is None:
        return None

    try:
        # ============================================
        # 1. Mapear tags → pontos + campo de valor
        # ============================================
        mapeamento = _mapear_tags_para_pontos(conn, tags)

        if not mapeamento:
            logger.warning("Nenhuma tag auxiliar encontrada no SIMP")
            conn.close()
            return None

        # ============================================
        # 2. Calcular período de busca
        # ============================================
        if data_alvo:
            data_ref = datetime.strptime(data_alvo, '%Y-%m-%d')
            data_inicio = data_ref - timedelta(hours=horas_necessarias)
            data_fim = data_ref + timedelta(hours=24)
        else:
            data_inicio = datetime.now() - timedelta(hours=horas_necessarias * 2)
            data_fim = datetime.now() + timedelta(hours=1)

        # ============================================
        # 3. Buscar dados de cada tag/ponto
        # ============================================
        # Agrupar por campo de valor para otimizar queries
        # {campo_valor: [(tag, cd_ponto), ...]}
        grupos = {}
        for tag, (cd_ponto, campo_valor) in mapeamento.items():
            if campo_valor not in grupos:
                grupos[campo_valor] = []
            grupos[campo_valor].append((tag, cd_ponto))

        todos_dados = []

        for campo_valor, tags_pontos in grupos.items():
            cd_pontos = [cp for _, cp in tags_pontos]
            # Mapa reverso: cd_ponto → tag (para renomear na saída)
            ponto_para_tag = {cp: tag for tag, cp in tags_pontos}

            placeholders_pontos = ','.join(['?' for _ in cd_pontos])

            sql = f"""
                SELECT 
                    CAST(CAST(DT_LEITURA AS DATE) AS DATETIME) 
                        + CAST(DATEPART(HOUR, DT_LEITURA) AS FLOAT) / 24.0 AS data_hora,
                    CD_PONTO_MEDICAO,
                    AVG(CASE WHEN ID_SITUACAO = 1 THEN {campo_valor} ELSE NULL END) AS valor
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                WHERE CD_PONTO_MEDICAO IN ({placeholders_pontos})
                  AND DT_LEITURA >= ?
                  AND DT_LEITURA < ?
                  AND ID_SITUACAO = 1
                GROUP BY 
                    CAST(CAST(DT_LEITURA AS DATE) AS DATETIME) 
                        + CAST(DATEPART(HOUR, DT_LEITURA) AS FLOAT) / 24.0,
                    CD_PONTO_MEDICAO
                ORDER BY data_hora DESC
            """

            params = cd_pontos + [data_inicio, data_fim]
            cursor = conn.cursor()
            cursor.execute(sql, params)
            rows = cursor.fetchall()

            for row in rows:
                data_hora = row[0]
                cd_ponto = row[1]
                valor = row[2]
                # Converter CD_PONTO_MEDICAO de volta para TagName
                tag = ponto_para_tag.get(cd_ponto)
                if tag and valor is not None:
                    todos_dados.append({
                        'data_hora': data_hora,
                        'tag': tag,
                        'valor': float(valor)
                    })

        conn.close()

        if not todos_dados:
            logger.warning("Nenhum dado recente encontrado no SIMP para tags auxiliares")
            return None

        df = pd.DataFrame(todos_dados)
        df['data_hora'] = pd.to_datetime(df['data_hora'])
        df['valor'] = pd.to_numeric(df['valor'], errors='coerce')

        # Contar tags com dados
        tags_com_dados = df['tag'].nunique()
        logger.info(f"SIMP auxiliares: {len(df)} registros recentes de {tags_com_dados} tags")

        return df

    except Exception as e:
        logger.error(f"Erro ao buscar dados auxiliares do SIMP: {e}")
        try:
            conn.close()
        except:
            pass
        return None


def montar_features_xgboost(
    dados: pd.DataFrame,
    tags_auxiliares: List[str],
    feature_names: List[str],
    lags: List[int],
    hora_alvo: int = None
) -> Optional[pd.DataFrame]:
    """
    Monta features tabulares para XGBoost a partir dos dados recentes.
    
    v5.0: Cada linha = 1 hora. Colunas = auxiliares com lags + temporais.
    Sem normalização (XGBoost trabalha com escala real).
    
    NOTA: Esta função permanece inalterada — funciona com qualquer
    DataFrame no formato (data_hora, tag, valor), independente da origem.
    
    Args:
        dados: DataFrame com colunas [data_hora, tag, valor]
        tags_auxiliares: Lista de tags auxiliares
        feature_names: Lista de nomes das features (do metricas.json)
        lags: Lista de lags [0, 1, 3, 6]
        hora_alvo: Se definido, retorna features apenas para esta hora

    Returns:
        DataFrame com features prontas para predição ou None
    """
    # Pivotar: cada tag vira coluna
    pivot = dados.pivot_table(
        index='data_hora',
        columns='tag',
        values='valor',
        aggfunc='mean'
    )

    # Ordenar cronologicamente
    pivot = pivot.sort_index()

    # Interpolar gaps curtos
    pivot = pivot.interpolate(method='linear', limit=3)
    pivot = pivot.ffill(limit=2).bfill(limit=2)

    # Montar features na MESMA ORDEM do treino
    features = pd.DataFrame(index=pivot.index)

    for fname in feature_names:
        if fname.startswith('aux_'):
            # Extrair tag e lag do nome: aux_TAGNAME_t0 → TAGNAME, 0
            # O nome tem formato: aux_{tag}_{tN}
            # Precisamos encontrar o lag (último _tN)
            parts = fname.rsplit('_t', 1)
            if len(parts) == 2:
                tag_aux = parts[0][4:]  # Remover 'aux_'
                try:
                    lag = int(parts[1])
                except ValueError:
                    lag = 0
            else:
                tag_aux = fname[4:]
                lag = 0

            if tag_aux in pivot.columns:
                features[fname] = pivot[tag_aux].shift(lag)
            else:
                # Tag sem dados — preencher com NaN (XGBoost lida nativamente)
                features[fname] = np.nan
                logger.warning(f"Tag auxiliar '{tag_aux}' sem dados recentes")

        elif fname == 'hora_sin':
            features[fname] = np.sin(2 * np.pi * features.index.hour / 24)
        elif fname == 'hora_cos':
            features[fname] = np.cos(2 * np.pi * features.index.hour / 24)
        elif fname == 'dia_sem_sin':
            features[fname] = np.sin(2 * np.pi * features.index.dayofweek / 7)
        elif fname == 'dia_sem_cos':
            features[fname] = np.cos(2 * np.pi * features.index.dayofweek / 7)

    if features.empty:
        logger.error("Nenhuma feature montada")
        return None

    # Remover linhas completamente vazias
    features = features.dropna(how='all')

    if features.empty:
        logger.error("Todas as linhas de features são NaN")
        return None

    logger.info(f"Features XGBoost montadas: {features.shape[0]} linhas × {features.shape[1]} colunas")
    return features
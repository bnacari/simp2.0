"""
SIMP TensorFlow - Gerenciador de Banco de Dados
Conexão com SQL Server para leitura de dados históricos.

Usa as mesmas credenciais do container PHP (variáveis de ambiente).

@author Bruno - CESAN
@version 1.0
"""

import os
import logging
from datetime import datetime, timedelta
from typing import Optional, List, Dict

import pyodbc
import numpy as np
import pandas as pd

logger = logging.getLogger('simp-tensorflow.database')


class DatabaseManager:
    """
    Gerencia conexão com SQL Server do SIMP.
    Lê variáveis de ambiente DB_HOST, DB_NAME, DB_USER, DB_PASS
    (mesmas usadas pelo container PHP).
    """

    def __init__(self):
        """Inicializa configuração de conexão."""
        self.host = os.environ.get('DB_HOST', '')
        self.database = os.environ.get('DB_NAME', 'simp')
        self.user = os.environ.get('DB_USER', 'simp')
        self.password = os.environ.get('DB_PASS', '')
        self._connection_string = None

    @property
    def connection_string(self) -> str:
        """Monta string de conexão ODBC."""
        if not self._connection_string:
            # Tratar instância nomeada do SQL Server (ex: servidor\corporativo)
            server = self.host.replace('\\\\', '\\')
            self._connection_string = (
                f"DRIVER={{ODBC Driver 18 for SQL Server}};"
                f"SERVER={server};"
                f"DATABASE={self.database};"
                f"UID={self.user};"
                f"PWD={self.password};"
                f"TrustServerCertificate=yes;"
                f"Connection Timeout=30;"
            )
        return self._connection_string

    def _get_connection(self) -> pyodbc.Connection:
        """Cria nova conexão com o banco."""
        return pyodbc.connect(self.connection_string)

    def test_connection(self) -> bool:
        """Testa se a conexão com o banco está funcionando."""
        try:
            conn = self._get_connection()
            cursor = conn.cursor()
            cursor.execute("SELECT 1")
            cursor.close()
            conn.close()
            return True
        except Exception as e:
            logger.warning(f"Falha na conexão com banco: {e}")
            return False

    def get_historico_horario(
        self,
        cd_ponto: int,
        data_base: str,
        semanas: int = 12,
        tipo_medidor: int = 1
    ) -> Optional[pd.DataFrame]:
        """
        Busca histórico horário de um ponto de medição.
        Retorna DataFrame com colunas: data, hora, valor, qtd_registros.

        Usa a mesma lógica da query existente em consultarDadosIA.php:
        - AVG dos registros válidos (ID_SITUACAO = 1) agrupados por hora
        - Exclui registros descartados (ID_SITUACAO = 2)

        Args:
            cd_ponto: Código do ponto de medição
            data_base: Data de referência (YYYY-MM-DD)
            semanas: Quantidade de semanas de histórico
            tipo_medidor: 1=Macromedidor, 2=Pitométrica, 4=Pressão, 6=Nível, 8=Hidrômetro

        Returns:
            DataFrame com o histórico ou None se erro
        """
        try:
            # Determinar campo de valor baseado no tipo de medidor
            campo_valor = self._get_campo_valor(tipo_medidor)

            data_inicio = (
                datetime.strptime(data_base, '%Y-%m-%d') - timedelta(weeks=semanas)
            ).strftime('%Y-%m-%d')

            # Para reservatório (tipo 6): MAX como valor principal (compatível com operacoes.php)
            funcao_agregacao = 'MAX' if tipo_medidor == 6 else 'AVG'

            sql = f"""
                SELECT
                    CAST(DT_LEITURA AS DATE) AS data,
                    DATEPART(HOUR, DT_LEITURA) AS hora,
                    {funcao_agregacao}(CASE WHEN ID_SITUACAO = 1 THEN {campo_valor} ELSE NULL END) AS valor,
                    COUNT(CASE WHEN ID_SITUACAO = 1 THEN 1 END) AS qtd_registros,
                    DATEPART(WEEKDAY, DT_LEITURA) AS dia_semana
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                WHERE CD_PONTO_MEDICAO = ?
                  AND CAST(DT_LEITURA AS DATE) BETWEEN ? AND ?
                GROUP BY CAST(DT_LEITURA AS DATE), DATEPART(HOUR, DT_LEITURA),
                         DATEPART(WEEKDAY, DT_LEITURA)
                ORDER BY data, hora
            """

            conn = self._get_connection()
            df = pd.read_sql(sql, conn, params=[cd_ponto, data_inicio, data_base])
            conn.close()

            if df.empty:
                return None

            # Converter tipos
            df['data'] = pd.to_datetime(df['data'])
            df['hora'] = df['hora'].astype(int)
            df['valor'] = pd.to_numeric(df['valor'], errors='coerce')
            df['qtd_registros'] = df['qtd_registros'].astype(int)
            df['dia_semana'] = df['dia_semana'].astype(int)

            logger.info(
                f"Histórico carregado: ponto={cd_ponto}, "
                f"registros={len(df)}, período={data_inicio} a {data_base}"
            )

            return df

        except Exception as e:
            logger.error(f"Erro ao buscar histórico: {e}")
            return None

    def get_dados_dia(
        self,
        cd_ponto: int,
        data: str,
        tipo_medidor: int = 1
    ) -> Optional[pd.DataFrame]:
        """
        Busca dados horários de um dia específico.

        Para tipo_medidor=6 (Nível Reservatório), usa MAX como valor
        principal (não AVG) e conta minutos com leitura >= 100%,
        compatível com a lógica de getDadosHorarios.php / operacoes.php.

        Args:
            cd_ponto: Código do ponto
            data: Data no formato YYYY-MM-DD
            tipo_medidor: Tipo de medidor

        Returns:
            DataFrame com até 24 linhas (uma por hora) ou None
        """
        try:
            campo_valor = self._get_campo_valor(tipo_medidor)

            if tipo_medidor == 6:
                # Reservatório: MAX como valor principal + contagem de minutos >= 100
                sql = f"""
                    SELECT
                        DATEPART(HOUR, DT_LEITURA) AS hora,
                        MAX(CASE WHEN ID_SITUACAO = 1 THEN {campo_valor} ELSE NULL END) AS valor,
                        COUNT(CASE WHEN ID_SITUACAO = 1 THEN 1 END) AS qtd_registros,
                        MIN(CASE WHEN ID_SITUACAO = 1 THEN {campo_valor} ELSE NULL END) AS valor_min,
                        MAX(CASE WHEN ID_SITUACAO = 1 THEN {campo_valor} ELSE NULL END) AS valor_max,
                        SUM(CASE
                            WHEN ID_SITUACAO = 1 AND ID_TIPO_REGISTRO = 2 AND ID_TIPO_MEDICAO = 2
                                THEN ISNULL(NR_EXTRAVASOU, 0)
                            WHEN ID_SITUACAO = 1
                                THEN CASE WHEN ROUND({campo_valor}, 0) >= 100 THEN 1 ELSE 0 END
                            ELSE 0
                        END) AS minutos_extravasou
                    FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                    WHERE CD_PONTO_MEDICAO = ?
                      AND CAST(DT_LEITURA AS DATE) = ?
                    GROUP BY DATEPART(HOUR, DT_LEITURA)
                    ORDER BY hora
                """
            else:
                sql = f"""
                    SELECT
                        DATEPART(HOUR, DT_LEITURA) AS hora,
                        AVG(CASE WHEN ID_SITUACAO = 1 THEN {campo_valor} ELSE NULL END) AS valor,
                        COUNT(CASE WHEN ID_SITUACAO = 1 THEN 1 END) AS qtd_registros,
                        MIN(CASE WHEN ID_SITUACAO = 1 THEN {campo_valor} ELSE NULL END) AS valor_min,
                        MAX(CASE WHEN ID_SITUACAO = 1 THEN {campo_valor} ELSE NULL END) AS valor_max
                    FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                    WHERE CD_PONTO_MEDICAO = ?
                      AND CAST(DT_LEITURA AS DATE) = ?
                    GROUP BY DATEPART(HOUR, DT_LEITURA)
                    ORDER BY hora
                """

            conn = self._get_connection()
            df = pd.read_sql(sql, conn, params=[cd_ponto, data])
            conn.close()

            return df if not df.empty else None

        except Exception as e:
            logger.error(f"Erro ao buscar dados do dia: {e}")
            return None

    def get_dados_periodo(
        self,
        cd_ponto: int,
        data_inicio: str,
        data_fim: str,
        tipo_medidor: int = 1
    ) -> Optional[pd.DataFrame]:
        """
        Busca dados horários de um período para correlação entre pontos.

        Args:
            cd_ponto: Código do ponto
            data_inicio: Data início (YYYY-MM-DD)
            data_fim: Data fim (YYYY-MM-DD)
            tipo_medidor: Tipo de medidor

        Returns:
            DataFrame com data, hora, valor
        """
        try:
            campo_valor = self._get_campo_valor(tipo_medidor)

            sql = f"""
                SELECT 
                    CAST(DT_LEITURA AS DATE) AS data,
                    DATEPART(HOUR, DT_LEITURA) AS hora,
                    AVG(CASE WHEN ID_SITUACAO = 1 THEN {campo_valor} ELSE NULL END) AS valor
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                WHERE CD_PONTO_MEDICAO = ?
                  AND CAST(DT_LEITURA AS DATE) BETWEEN ? AND ?
                GROUP BY CAST(DT_LEITURA AS DATE), DATEPART(HOUR, DT_LEITURA)
                ORDER BY data, hora
            """

            conn = self._get_connection()
            df = pd.read_sql(sql, conn, params=[cd_ponto, data_inicio, data_fim])
            conn.close()

            if not df.empty:
                df['data'] = pd.to_datetime(df['data'])
                df['valor'] = pd.to_numeric(df['valor'], errors='coerce')

            return df if not df.empty else None

        except Exception as e:
            logger.error(f"Erro ao buscar dados do período: {e}")
            return None

    def get_pontos_mesma_localidade(self, cd_ponto: int) -> List[int]:
        """
        Busca outros pontos de medição na mesma localidade/unidade operacional.
        Útil para encontrar candidatos à correlação.

        Args:
            cd_ponto: Código do ponto de referência

        Returns:
            Lista de cd_ponto dos pontos na mesma localidade
        """
        try:
            sql = """
                SELECT p2.CD_PONTO_MEDICAO
                FROM SIMP.dbo.PONTO_MEDICAO p1
                INNER JOIN SIMP.dbo.PONTO_MEDICAO p2 
                    ON p1.CD_LOCALIDADE = p2.CD_LOCALIDADE
                WHERE p1.CD_PONTO_MEDICAO = ?
                  AND p2.CD_PONTO_MEDICAO != ?
                  AND p2.NR_ATIVO = 1
                ORDER BY p2.CD_PONTO_MEDICAO
            """

            conn = self._get_connection()
            cursor = conn.cursor()
            cursor.execute(sql, [cd_ponto, cd_ponto])
            pontos = [row[0] for row in cursor.fetchall()]
            cursor.close()
            conn.close()

            return pontos

        except Exception as e:
            logger.error(f"Erro ao buscar pontos da mesma localidade: {e}")
            return []

    @staticmethod
    def _get_campo_valor(tipo_medidor: int) -> str:
        """
        Retorna o nome do campo SQL baseado no tipo de medidor.
        Compatível com IDs do banco SIMP (1,2,4,6,8).

        Args:
            tipo_medidor: 1=Macromedidor, 2=Pitométrica, 4=Pressão,
                          6=Nível Reservatório, 8=Hidrômetro

        Returns:
            Nome do campo SQL
        """
        campos = {
            1: 'VL_VAZAO_EFETIVA',    # Macromedidor - Vazão (L/s)
            2: 'VL_VAZAO_EFETIVA',    # Estação Pitométrica - Vazão (L/s)
            4: 'VL_PRESSAO',           # Medidor de Pressão (mca)
            6: 'VL_RESERVATORIO',      # Nível do reservatório (%)
            8: 'VL_VAZAO_EFETIVA',    # Hidrômetro - Vazão (L/s)
        }
        return campos.get(tipo_medidor, 'VL_VAZAO_EFETIVA')
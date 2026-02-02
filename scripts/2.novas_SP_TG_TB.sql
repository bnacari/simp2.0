-- ┌─────────────────────────────────────────────────────────────────┐
-- │  REGISTRO_VAZAO_PRESSAO (dados brutos)                          │
-- └─────────────────────────────────────────────────────────────────┘
--                               │
--                               ▼
--               ┌───────────────────────────────────────┐
--               │  SP_PROCESSAR_MEDICAO_V2 (nova)       │
--               │  - Usa AVG                            │
--               │  - 4 flags apenas                     │
--               │  - Gera resumo textual                │
--               └───────────────────────────────────────┘
--                               │
--               ┌───────────────┴───────────────┐
--               ▼                               ▼
-- ┌─────────────────────────────┐     ┌─────────────────────────────┐
-- │ MEDICAO_RESUMO_HORARIO      │     │ IA_METRICAS_DIARIAS         │
-- │ (simplificado - opcional)   │     │ (principal - para IA)       │
-- └─────────────────────────────┘     └─────────────────────────────┘

-- ============================================================
-- SIMP - Sistema Integrado de Macromedição e Pitometria
-- ============================================================
-- Script: Criação de IA_METRICAS_DIARIAS e SP_PROCESSAR_MEDICAO_V2
-- Versão: 1.0
-- Data: 22/01/2026
-- Autor: Bruno
-- 
-- Objetivo: Tabela simplificada para análise de IA
-- Fórmula: AVG (média dos registros recebidos)
-- Flags: 4 (cobertura, sensor, valor, desvio)
-- ============================================================

USE [SIMP]
GO

PRINT '================================================';
PRINT 'SIMP - SETUP IA_METRICAS_DIARIAS';
PRINT '================================================';
PRINT '';

-- ============================================================
-- PARTE 1: CRIAR TABELA IA_METRICAS_DIARIAS
-- ============================================================

PRINT 'Criando tabela IA_METRICAS_DIARIAS...';

IF OBJECT_ID('dbo.IA_METRICAS_DIARIAS', 'U') IS NOT NULL
BEGIN
    PRINT '  - Tabela existente encontrada. Removendo...';
    DROP TABLE dbo.IA_METRICAS_DIARIAS;
END
GO

CREATE TABLE [dbo].[IA_METRICAS_DIARIAS](
    -- IDENTIFICAÇÃO
    [CD_CHAVE] [bigint] IDENTITY(1,1) NOT NULL,
    [CD_PONTO_MEDICAO] [int] NOT NULL,
    [DT_REFERENCIA] [date] NOT NULL,
    [ID_TIPO_MEDIDOR] [tinyint] NOT NULL,
    
    -- =============================================
    -- BLOCO 1: COBERTURA
    -- =============================================
    [QTD_REGISTROS] [int] NULL,
    [QTD_ESPERADA] [int] DEFAULT 1440,
    [PERC_COBERTURA] AS (CAST(CASE 
        WHEN [QTD_ESPERADA] > 0 THEN ([QTD_REGISTROS] * 100.0 / [QTD_ESPERADA]) 
        ELSE 0 END AS DECIMAL(5,2))) PERSISTED,
    [QTD_HORAS_COM_DADOS] [tinyint] NULL,
    [HORAS_SEM_DADOS] [varchar](100) NULL,
    
    -- =============================================
    -- BLOCO 2: VALORES (AVG)
    -- =============================================
    [VL_MEDIA] [decimal](18,4) NULL,
    [VL_MIN] [decimal](18,4) NULL,
    [VL_MAX] [decimal](18,4) NULL,
    [VL_DESVIO_PADRAO] [decimal](18,4) NULL,
    [QTD_ZEROS] [int] NULL,
    [QTD_VALORES_DISTINTOS] [int] NULL,
    
    -- =============================================
    -- BLOCO 3: HISTÓRICO
    -- =============================================
    [VL_MEDIA_HIST_4SEM] [decimal](18,4) NULL,
    [VL_DESVIO_HIST_PERC] [decimal](8,2) NULL,
    [VL_TENDENCIA_7D] [varchar](10) NULL,
    
    -- =============================================
    -- BLOCO 4: FLAGS SIMPLIFICADOS (apenas 4)
    -- =============================================
    [FL_COBERTURA_BAIXA] [bit] DEFAULT 0,
    [FL_SENSOR_PROBLEMA] [bit] DEFAULT 0,
    [FL_VALOR_ANOMALO] [bit] DEFAULT 0,
    [FL_DESVIO_SIGNIFICATIVO] [bit] DEFAULT 0,
    
    -- =============================================
    -- BLOCO 5: CONTEXTO
    -- =============================================
    [VL_LIMITE_INFERIOR] [decimal](18,4) NULL,
    [VL_LIMITE_SUPERIOR] [decimal](18,4) NULL,
    [QTD_TRATADOS_MANUAL] [int] DEFAULT 0,
    
    -- =============================================
    -- BLOCO 6: RESUMO PARA IA
    -- =============================================
    [DS_STATUS] [varchar](20) NULL,
    [DS_RESUMO] [varchar](500) NULL,
    
    -- METADADOS
    [DT_PROCESSAMENTO] [datetime] DEFAULT GETDATE(),
    
    CONSTRAINT [PK_IA_METRICAS_DIARIAS] PRIMARY KEY CLUSTERED ([CD_CHAVE]),
    CONSTRAINT [UK_IA_METRICAS_PONTO_DATA] UNIQUE ([CD_PONTO_MEDICAO], [DT_REFERENCIA]),
    CONSTRAINT [FK_IA_METRICAS_PONTO] FOREIGN KEY ([CD_PONTO_MEDICAO]) 
        REFERENCES [dbo].[PONTO_MEDICAO] ([CD_PONTO_MEDICAO])
);
GO

PRINT '  - Tabela criada com sucesso.';
PRINT '';

-- ============================================================
-- PARTE 2: CRIAR ÍNDICES
-- ============================================================

PRINT 'Criando indices...';

CREATE INDEX IX_IA_METRICAS_DATA ON IA_METRICAS_DIARIAS(DT_REFERENCIA);
CREATE INDEX IX_IA_METRICAS_STATUS ON IA_METRICAS_DIARIAS(DS_STATUS, DT_REFERENCIA);
CREATE INDEX IX_IA_METRICAS_FLAGS ON IA_METRICAS_DIARIAS(DT_REFERENCIA) 
    INCLUDE (FL_COBERTURA_BAIXA, FL_SENSOR_PROBLEMA, FL_VALOR_ANOMALO, FL_DESVIO_SIGNIFICATIVO);
GO

PRINT '  - Indices criados com sucesso.';
PRINT '';

-- ============================================================
-- PARTE 3: CRIAR STORED PROCEDURE
-- ============================================================

PRINT 'Criando SP_PROCESSAR_MEDICAO_V2...';
GO

CREATE OR ALTER PROCEDURE [dbo].[SP_PROCESSAR_MEDICAO_V2]
    @DT_PROCESSAMENTO DATE = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Default: ontem
    IF @DT_PROCESSAMENTO IS NULL
        SET @DT_PROCESSAMENTO = CAST(DATEADD(DAY, -1, GETDATE()) AS DATE);
    
    DECLARE @DT_INICIO DATETIME = CAST(@DT_PROCESSAMENTO AS DATETIME);
    DECLARE @DT_FIM DATETIME = DATEADD(DAY, 1, @DT_INICIO);
    
    PRINT '================================================';
    PRINT 'SP_PROCESSAR_MEDICAO_V2 - VERSAO SIMPLIFICADA';
    PRINT '================================================';
    PRINT 'Data: ' + CONVERT(VARCHAR, @DT_PROCESSAMENTO, 103);
    PRINT 'Formula: AVG (media dos registros recebidos)';
    PRINT '';
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- ========================================
        -- ETAPA 1: LIMPAR DADOS EXISTENTES
        -- ========================================
        DELETE FROM [dbo].[IA_METRICAS_DIARIAS] 
        WHERE [DT_REFERENCIA] = @DT_PROCESSAMENTO;
        
        PRINT 'Etapa 1: Dados anteriores removidos.';
        
        -- ========================================
        -- ETAPA 2: INSERIR MÉTRICAS BÁSICAS
        -- ========================================
        INSERT INTO [dbo].[IA_METRICAS_DIARIAS] (
            [CD_PONTO_MEDICAO],
            [DT_REFERENCIA],
            [ID_TIPO_MEDIDOR],
            [QTD_REGISTROS],
            [QTD_ESPERADA],
            [VL_MEDIA],
            [VL_MIN],
            [VL_MAX],
            [VL_DESVIO_PADRAO],
            [QTD_ZEROS],
            [QTD_VALORES_DISTINTOS],
            [QTD_HORAS_COM_DADOS],
            [VL_LIMITE_INFERIOR],
            [VL_LIMITE_SUPERIOR],
            [QTD_TRATADOS_MANUAL]
        )
        SELECT 
            RVP.CD_PONTO_MEDICAO,
            @DT_PROCESSAMENTO,
            PM.ID_TIPO_MEDIDOR,
            
            -- COBERTURA
            COUNT(*) AS QTD_REGISTROS,
            1440 AS QTD_ESPERADA,
            
            -- VALORES COM AVG (por tipo de medidor)
            AVG(CASE 
                WHEN PM.ID_TIPO_MEDIDOR IN (1, 2, 8) THEN CAST(ISNULL(RVP.VL_VAZAO_EFETIVA, RVP.VL_VAZAO) AS FLOAT)
                WHEN PM.ID_TIPO_MEDIDOR = 4 THEN RVP.VL_PRESSAO
                WHEN PM.ID_TIPO_MEDIDOR = 6 THEN RVP.VL_RESERVATORIO
            END) AS VL_MEDIA,
            
            MIN(CASE 
                WHEN PM.ID_TIPO_MEDIDOR IN (1, 2, 8) THEN CAST(ISNULL(RVP.VL_VAZAO_EFETIVA, RVP.VL_VAZAO) AS FLOAT)
                WHEN PM.ID_TIPO_MEDIDOR = 4 THEN RVP.VL_PRESSAO
                WHEN PM.ID_TIPO_MEDIDOR = 6 THEN RVP.VL_RESERVATORIO
            END) AS VL_MIN,
            
            MAX(CASE 
                WHEN PM.ID_TIPO_MEDIDOR IN (1, 2, 8) THEN CAST(ISNULL(RVP.VL_VAZAO_EFETIVA, RVP.VL_VAZAO) AS FLOAT)
                WHEN PM.ID_TIPO_MEDIDOR = 4 THEN RVP.VL_PRESSAO
                WHEN PM.ID_TIPO_MEDIDOR = 6 THEN RVP.VL_RESERVATORIO
            END) AS VL_MAX,
            
            STDEV(CASE 
                WHEN PM.ID_TIPO_MEDIDOR IN (1, 2, 8) THEN CAST(ISNULL(RVP.VL_VAZAO_EFETIVA, RVP.VL_VAZAO) AS FLOAT)
                WHEN PM.ID_TIPO_MEDIDOR = 4 THEN RVP.VL_PRESSAO
                WHEN PM.ID_TIPO_MEDIDOR = 6 THEN RVP.VL_RESERVATORIO
            END) AS VL_DESVIO_PADRAO,
            
            -- ZEROS
            SUM(CASE 
                WHEN PM.ID_TIPO_MEDIDOR IN (1, 2, 8) AND ISNULL(ISNULL(RVP.VL_VAZAO_EFETIVA, RVP.VL_VAZAO), 0) = 0 THEN 1
                WHEN PM.ID_TIPO_MEDIDOR = 4 AND ISNULL(RVP.VL_PRESSAO, 0) = 0 THEN 1
                WHEN PM.ID_TIPO_MEDIDOR = 6 AND ISNULL(RVP.VL_RESERVATORIO, 0) = 0 THEN 1
                ELSE 0
            END) AS QTD_ZEROS,
            
            -- VALORES DISTINTOS (detecta sensor travado)
            COUNT(DISTINCT CAST(ROUND(CASE 
                WHEN PM.ID_TIPO_MEDIDOR IN (1, 2, 8) THEN CAST(ISNULL(RVP.VL_VAZAO_EFETIVA, RVP.VL_VAZAO) AS FLOAT)
                WHEN PM.ID_TIPO_MEDIDOR = 4 THEN RVP.VL_PRESSAO
                WHEN PM.ID_TIPO_MEDIDOR = 6 THEN RVP.VL_RESERVATORIO
            END, 2) AS VARCHAR)) AS QTD_VALORES_DISTINTOS,
            
            -- HORAS COM DADOS
            COUNT(DISTINCT DATEPART(HOUR, RVP.DT_LEITURA)) AS QTD_HORAS_COM_DADOS,
            
            -- LIMITES (para reservatório, usa 0-100 se não definido)
            CASE 
                WHEN PM.ID_TIPO_MEDIDOR = 6 THEN ISNULL(PM.VL_LIMITE_INFERIOR_VAZAO, 0)
                ELSE PM.VL_LIMITE_INFERIOR_VAZAO
            END,
            CASE 
                WHEN PM.ID_TIPO_MEDIDOR = 6 THEN ISNULL(PM.VL_LIMITE_SUPERIOR_VAZAO, 100)
                ELSE PM.VL_LIMITE_SUPERIOR_VAZAO
            END,
            
            -- TRATADOS MANUALMENTE
            SUM(CASE WHEN RVP.ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS QTD_TRATADOS_MANUAL
            
        FROM [dbo].[REGISTRO_VAZAO_PRESSAO] RVP
        INNER JOIN [dbo].[PONTO_MEDICAO] PM ON RVP.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
        WHERE RVP.DT_LEITURA >= @DT_INICIO 
          AND RVP.DT_LEITURA < @DT_FIM
          AND RVP.ID_SITUACAO IN (1, 2)
        GROUP BY 
            RVP.CD_PONTO_MEDICAO, 
            PM.ID_TIPO_MEDIDOR,
            PM.VL_LIMITE_INFERIOR_VAZAO, 
            PM.VL_LIMITE_SUPERIOR_VAZAO;
        
        DECLARE @QTD_PONTOS INT = @@ROWCOUNT;
        PRINT 'Etapa 2: ' + CAST(@QTD_PONTOS AS VARCHAR) + ' pontos processados.';
        
        -- ========================================
        -- ETAPA 3: HORAS SEM DADOS
        -- ========================================
        UPDATE IM
        SET HORAS_SEM_DADOS = HSEM.LISTA_HORAS
        FROM [dbo].[IA_METRICAS_DIARIAS] IM
        CROSS APPLY (
            SELECT STRING_AGG(CAST(H.HORA AS VARCHAR), ',') AS LISTA_HORAS
            FROM (
                SELECT TOP 24 ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) - 1 AS HORA 
                FROM sys.objects
            ) H
            WHERE NOT EXISTS (
                SELECT 1 
                FROM [dbo].[REGISTRO_VAZAO_PRESSAO] RVP
                WHERE RVP.CD_PONTO_MEDICAO = IM.CD_PONTO_MEDICAO
                  AND RVP.DT_LEITURA >= @DT_INICIO
                  AND RVP.DT_LEITURA < @DT_FIM
                  AND DATEPART(HOUR, RVP.DT_LEITURA) = H.HORA
                  AND RVP.ID_SITUACAO IN (1, 2)
            )
        ) HSEM
        WHERE IM.DT_REFERENCIA = @DT_PROCESSAMENTO;
        
        PRINT 'Etapa 3: Horas sem dados identificadas.';
        
        -- ========================================
        -- ETAPA 4: MEDIA HISTORICA (4 semanas, mesmo dia)
        -- ========================================
        UPDATE IM
        SET 
            VL_MEDIA_HIST_4SEM = HIST.VL_MEDIA_HIST,
            VL_DESVIO_HIST_PERC = CASE 
                WHEN ISNULL(HIST.VL_MEDIA_HIST, 0) > 0.001 
                THEN CAST(
                    CASE 
                        WHEN ABS(((IM.VL_MEDIA - HIST.VL_MEDIA_HIST) / HIST.VL_MEDIA_HIST) * 100) > 9999 
                        THEN SIGN(((IM.VL_MEDIA - HIST.VL_MEDIA_HIST) / HIST.VL_MEDIA_HIST) * 100) * 9999
                        ELSE ((IM.VL_MEDIA - HIST.VL_MEDIA_HIST) / HIST.VL_MEDIA_HIST) * 100 
                    END AS DECIMAL(18,4))
                ELSE NULL 
            END
        FROM [dbo].[IA_METRICAS_DIARIAS] IM
        INNER JOIN (
            SELECT 
                CD_PONTO_MEDICAO,
                AVG(VL_MEDIA) AS VL_MEDIA_HIST
            FROM [dbo].[IA_METRICAS_DIARIAS]
            WHERE DT_REFERENCIA >= DATEADD(WEEK, -4, @DT_PROCESSAMENTO)
            AND DT_REFERENCIA < @DT_PROCESSAMENTO
            AND DATEPART(WEEKDAY, DT_REFERENCIA) = DATEPART(WEEKDAY, @DT_PROCESSAMENTO)
            GROUP BY CD_PONTO_MEDICAO
        ) HIST ON IM.CD_PONTO_MEDICAO = HIST.CD_PONTO_MEDICAO
        WHERE IM.DT_REFERENCIA = @DT_PROCESSAMENTO;

        PRINT 'Etapa 4: Media historica calculada.';
        
        -- ========================================
        -- ETAPA 5: TENDÊNCIA 7 DIAS
        -- ========================================
        UPDATE IM
        SET VL_TENDENCIA_7D = CASE
            WHEN T.VARIACAO_PERC > 10 THEN 'SUBINDO'
            WHEN T.VARIACAO_PERC < -10 THEN 'DESCENDO'
            ELSE 'ESTAVEL'
        END
        FROM [dbo].[IA_METRICAS_DIARIAS] IM
        CROSS APPLY (
            SELECT 
                CASE 
                    WHEN MIN(H.VL_MEDIA) > 0.001
                    THEN ((MAX(H.VL_MEDIA) - MIN(H.VL_MEDIA)) / MIN(H.VL_MEDIA)) * 100
                    ELSE 0
                END AS VARIACAO_PERC
            FROM (
                SELECT VL_MEDIA, ROW_NUMBER() OVER (ORDER BY DT_REFERENCIA) AS RN
                FROM [dbo].[IA_METRICAS_DIARIAS]
                WHERE CD_PONTO_MEDICAO = IM.CD_PONTO_MEDICAO
                  AND DT_REFERENCIA >= DATEADD(DAY, -7, @DT_PROCESSAMENTO)
                  AND DT_REFERENCIA <= @DT_PROCESSAMENTO
            ) H
        ) T
        WHERE IM.DT_REFERENCIA = @DT_PROCESSAMENTO;
        
        PRINT 'Etapa 5: Tendencia calculada.';
        
        -- ========================================
        -- ETAPA 6: FLAGS (DIFERENCIADOS POR TIPO)
        -- ========================================
        UPDATE [dbo].[IA_METRICAS_DIARIAS]
        SET 
            -- FLAG 1: Cobertura baixa (<50%)
            -- Igual para todos os tipos
            FL_COBERTURA_BAIXA = CASE 
                WHEN QTD_REGISTROS < 720 THEN 1 
                ELSE 0 
            END,
            
            -- FLAG 2: Problema no sensor
            -- DIFERENCIADO: Reservatório tem critérios mais relaxados
            FL_SENSOR_PROBLEMA = CASE 
                -- RESERVATÓRIO (ID_TIPO_MEDIDOR = 6)
                WHEN ID_TIPO_MEDIDOR = 6 THEN
                    CASE
                        -- Sensor travado: mesmo valor por muito tempo E com variação mínima
                        -- (reservatório pode ficar estável, então exige critério mais rígido)
                        WHEN QTD_VALORES_DISTINTOS = 1 AND QTD_REGISTROS >= 1000 THEN 1
                        -- Desvio padrão zero com muitos registros = definitivamente travado
                        WHEN VL_DESVIO_PADRAO = 0 AND QTD_REGISTROS >= 1000 THEN 1
                        -- Zeros NÃO são problema para reservatório (pode estar vazio)
                        ELSE 0
                    END
                    
                -- MACROMEDIDORES E PRESSÃO (ID_TIPO_MEDIDOR IN 1, 2, 4, 8)
                ELSE
                    CASE
                        -- Travado: poucos valores distintos
                        WHEN QTD_VALORES_DISTINTOS <= 3 AND QTD_REGISTROS >= 1000 THEN 1
                        -- Zeros excessivos quando deveria ter fluxo
                        WHEN QTD_ZEROS > (QTD_REGISTROS * 0.5) AND ISNULL(VL_MEDIA_HIST_4SEM, 0) > 0.1 THEN 1
                        -- Desvio padrão muito baixo
                        WHEN VL_DESVIO_PADRAO < 0.01 AND VL_MEDIA > 0.1 AND QTD_REGISTROS >= 1000 THEN 1
                        ELSE 0
                    END
            END,
            
            -- FLAG 3: Valor anômalo
            -- DIFERENCIADO: Reservatório tem faixa conhecida (0-100%)
            FL_VALOR_ANOMALO = CASE 
                -- RESERVATÓRIO (ID_TIPO_MEDIDOR = 6)
                WHEN ID_TIPO_MEDIDOR = 6 THEN
                    CASE
                        -- Valor negativo = impossível
                        WHEN VL_MIN < 0 THEN 1
                        -- Acima de 100% = impossível (ou acima do limite configurado)
                        WHEN VL_MAX > ISNULL(VL_LIMITE_SUPERIOR, 100) THEN 1
                        -- NÃO aplica spike detection para reservatório
                        ELSE 0
                    END
                    
                -- MACROMEDIDORES E PRESSÃO
                ELSE
                    CASE
                        -- Valor negativo
                        WHEN VL_MIN < 0 THEN 1
                        -- Acima do limite configurado
                        WHEN VL_LIMITE_SUPERIOR > 0 AND VL_MAX > VL_LIMITE_SUPERIOR THEN 1
                        -- Spike: max muito acima da média
                        WHEN VL_MEDIA > 0.1 AND VL_MAX > (VL_MEDIA * 10) THEN 1
                        ELSE 0
                    END
            END,
            
            -- FLAG 4: Desvio histórico significativo
            -- DIFERENCIADO: Reservatório permite mais variação (operação pode mudar)
            FL_DESVIO_SIGNIFICATIVO = CASE 
                -- RESERVATÓRIO: threshold de 50% (mais tolerante)
                WHEN ID_TIPO_MEDIDOR = 6 THEN
                    CASE WHEN ABS(ISNULL(VL_DESVIO_HIST_PERC, 0)) > 50 THEN 1 ELSE 0 END
                    
                -- MACROMEDIDORES: threshold de 30%
                ELSE
                    CASE WHEN ABS(ISNULL(VL_DESVIO_HIST_PERC, 0)) > 30 THEN 1 ELSE 0 END
            END
            
        WHERE DT_REFERENCIA = @DT_PROCESSAMENTO;
        
        PRINT 'Etapa 6: Flags calculados (diferenciados por tipo).';
        
        -- ========================================
        -- ETAPA 7: STATUS E RESUMO TEXTUAL
        -- ========================================
        UPDATE [dbo].[IA_METRICAS_DIARIAS]
        SET 
            -- STATUS
            DS_STATUS = CASE
                WHEN FL_COBERTURA_BAIXA = 1 THEN 'CRITICO'
                WHEN FL_SENSOR_PROBLEMA = 1 THEN 'CRITICO'
                WHEN FL_VALOR_ANOMALO = 1 THEN 'ATENCAO'
                WHEN FL_DESVIO_SIGNIFICATIVO = 1 THEN 'ATENCAO'
                ELSE 'OK'
            END,
            
            -- RESUMO TEXTUAL PARA IA
            DS_RESUMO = 
                -- Cobertura
                'Cobertura: ' + CAST(CAST(QTD_REGISTROS * 100.0 / 1440 AS INT) AS VARCHAR) + '% (' + 
                CAST(QTD_REGISTROS AS VARCHAR) + '/1440). ' +
                
                -- Horas sem dados
                CASE 
                    WHEN HORAS_SEM_DADOS IS NOT NULL AND LEN(HORAS_SEM_DADOS) > 0
                    THEN 'Horas sem dados: ' + HORAS_SEM_DADOS + '. '
                    ELSE ''
                END +
                
                -- Média e unidade
                'Media: ' + CAST(CAST(ISNULL(VL_MEDIA, 0) AS DECIMAL(12,2)) AS VARCHAR) + 
                CASE ID_TIPO_MEDIDOR 
                    WHEN 1 THEN ' l/s' 
                    WHEN 2 THEN ' l/s' 
                    WHEN 4 THEN ' mca' 
                    WHEN 6 THEN '%' 
                    WHEN 8 THEN ' l/s' 
                    ELSE '' 
                END + 
                ' (min: ' + CAST(CAST(ISNULL(VL_MIN, 0) AS DECIMAL(12,2)) AS VARCHAR) +
                ', max: ' + CAST(CAST(ISNULL(VL_MAX, 0) AS DECIMAL(12,2)) AS VARCHAR) + '). ' +
                
                -- Informação específica de reservatório
                CASE 
                    WHEN ID_TIPO_MEDIDOR = 6 THEN 
                        'Amplitude: ' + CAST(CAST(ISNULL(VL_MAX, 0) - ISNULL(VL_MIN, 0) AS DECIMAL(5,1)) AS VARCHAR) + '%. '
                    ELSE ''
                END +
                
                -- Comparação histórica
                CASE 
                    WHEN VL_MEDIA_HIST_4SEM IS NULL THEN 'Sem historico para comparacao. '
                    -- Thresholds diferentes por tipo
                    WHEN ID_TIPO_MEDIDOR = 6 AND VL_DESVIO_HIST_PERC > 50 THEN 'ACIMA do historico (+' + CAST(CAST(VL_DESVIO_HIST_PERC AS INT) AS VARCHAR) + '%). '
                    WHEN ID_TIPO_MEDIDOR = 6 AND VL_DESVIO_HIST_PERC < -50 THEN 'ABAIXO do historico (' + CAST(CAST(VL_DESVIO_HIST_PERC AS INT) AS VARCHAR) + '%). '
                    WHEN ID_TIPO_MEDIDOR != 6 AND VL_DESVIO_HIST_PERC > 30 THEN 'ACIMA do historico (+' + CAST(CAST(VL_DESVIO_HIST_PERC AS INT) AS VARCHAR) + '%). '
                    WHEN ID_TIPO_MEDIDOR != 6 AND VL_DESVIO_HIST_PERC < -30 THEN 'ABAIXO do historico (' + CAST(CAST(VL_DESVIO_HIST_PERC AS INT) AS VARCHAR) + '%). '
                    ELSE 'Dentro do padrao historico (' + CAST(CAST(VL_DESVIO_HIST_PERC AS INT) AS VARCHAR) + '%). '
                END +
                
                -- Tendência (mais relevante para reservatório)
                CASE 
                    WHEN VL_TENDENCIA_7D = 'SUBINDO' THEN 
                        CASE WHEN ID_TIPO_MEDIDOR = 6 
                            THEN 'Nivel medio em alta nos ultimos 7 dias. '
                            ELSE 'Tendencia de alta nos ultimos 7 dias. '
                        END
                    WHEN VL_TENDENCIA_7D = 'DESCENDO' THEN 
                        CASE WHEN ID_TIPO_MEDIDOR = 6 
                            THEN 'Nivel medio em queda nos ultimos 7 dias. '
                            ELSE 'Tendencia de queda nos ultimos 7 dias. '
                        END
                    ELSE ''
                END +
                
                -- Problemas detectados
                CASE WHEN FL_SENSOR_PROBLEMA = 1 THEN 'ALERTA: Possivel problema no sensor. ' ELSE '' END +
                CASE WHEN FL_VALOR_ANOMALO = 1 THEN 'ALERTA: Valores anomalos detectados. ' ELSE '' END +
                
                -- Tratamento manual
                CASE 
                    WHEN QTD_TRATADOS_MANUAL > 0 
                    THEN 'Houve tratamento manual em ' + CAST(QTD_TRATADOS_MANUAL AS VARCHAR) + ' registros.'
                    ELSE ''
                END
                
        WHERE DT_REFERENCIA = @DT_PROCESSAMENTO;
        
        PRINT 'Etapa 7: Status e resumo gerados.';
        
        COMMIT TRANSACTION;
        
        -- ========================================
        -- RESUMO FINAL
        -- ========================================
        PRINT '';
        PRINT '================================================';
        PRINT 'PROCESSAMENTO CONCLUIDO';
        PRINT '================================================';
        
        SELECT 
            'RESUMO' AS INFO,
            COUNT(*) AS TOTAL_PONTOS,
            SUM(CASE WHEN DS_STATUS = 'OK' THEN 1 ELSE 0 END) AS QTD_OK,
            SUM(CASE WHEN DS_STATUS = 'ATENCAO' THEN 1 ELSE 0 END) AS QTD_ATENCAO,
            SUM(CASE WHEN DS_STATUS = 'CRITICO' THEN 1 ELSE 0 END) AS QTD_CRITICO,
            SUM(CASE WHEN FL_COBERTURA_BAIXA = 1 THEN 1 ELSE 0 END) AS COM_FALHA_COMUNICACAO,
            SUM(CASE WHEN FL_SENSOR_PROBLEMA = 1 THEN 1 ELSE 0 END) AS COM_PROBLEMA_SENSOR,
            SUM(CASE WHEN FL_VALOR_ANOMALO = 1 THEN 1 ELSE 0 END) AS COM_VALOR_ANOMALO,
            SUM(CASE WHEN FL_DESVIO_SIGNIFICATIVO = 1 THEN 1 ELSE 0 END) AS COM_DESVIO_HISTORICO,
            CAST(AVG(PERC_COBERTURA) AS DECIMAL(5,2)) AS COBERTURA_MEDIA
        FROM [dbo].[IA_METRICAS_DIARIAS]
        WHERE DT_REFERENCIA = @DT_PROCESSAMENTO;
        
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 
            ROLLBACK TRANSACTION;
        
        PRINT 'ERRO: ' + ERROR_MESSAGE();
        THROW;
    END CATCH
END
GO

PRINT '  - SP criada com sucesso.';
PRINT '';



-- ============================================================
-- PARTE 4: CRIAR TRIGGER PARA ATUALIZAÇÃO AUTOMÁTICA DE MÉTRICAS
-- ============================================================

-- Entendi o fluxo! No seu sistema, o tratamento manual acontece em vários endpoints:

-- Descartar (excluirRegistrosEmMassa.php): ID_SITUACAO 1 → 2
-- Restaurar (restaurarRegistro.php): ID_SITUACAO 2 → 1
-- Importar planilha com sobrescrever: marca existente como 2

-- A trigger funciona automaticamente - ela intercepta qualquer UPDATE na tabela REGISTRO_VAZAO_PRESSAO quando o ID_SITUACAO muda. Você não precisa alterar nenhum código PHP.
-- Como Funciona na Prática
-- ┌─────────────────────────────────────────────────────────────────┐
-- │                         SEU SISTEMA                              │
-- ├─────────────────────────────────────────────────────────────────┤
-- │                                                                  │
-- │  [Operador descarta registro]                                    │
-- │           │                                                      │
-- │           ▼                                                      │
-- │  excluirRegistrosEmMassa.php                                     │
-- │           │                                                      │
-- │           ▼                                                      │
-- │  UPDATE REGISTRO_VAZAO_PRESSAO SET ID_SITUACAO = 2 ...          │
-- │           │                                                      │
-- │           │  ◄─── TRIGGER DISPARA AUTOMATICAMENTE               │
-- │           │                                                      │
-- │           ▼                                                      │
-- │  TR_ATUALIZA_METRICAS_TRATAMENTO                                │
-- │           │                                                      │
-- │           ▼                                                      │
-- │  UPDATE IA_METRICAS_DIARIAS SET QTD_TRATADOS_MANUAL = ...       │
-- │                                                                  │
-- └─────────────────────────────────────────────────────────────────┘

-- ============================================
-- SIMP - Trigger Completa de Reprocessamento
-- VERSÃO CORRIGIDA
-- ============================================


-- -- -- -- ============================================================
-- -- -- -- PARTE 5: SCRIPT PARA POPULAR HISTÓRICO (OPCIONAL)
-- -- -- -- ============================================================

-- -- -- -- ============================================================
-- -- -- -- Processar ultimos 5 dias COM DADOS em REGISTRO_VAZAO_PRESSAO
-- -- -- -- ============================================================

-- PRINT 'Populando historico de IA_METRICAS_DIARIAS...';
DECLARE @DIAS_PROCESSAR INT = 1;

-- -- Tabela temporaria com as datas que possuem dados

DECLARE @DatasComDados TABLE (DT_LEITURA DATE);

INSERT INTO @DatasComDados
SELECT DISTINCT TOP (@DIAS_PROCESSAR) 
    CAST(DT_LEITURA AS DATE) AS DT_LEITURA
FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
WHERE ID_SITUACAO IN (1, 2)
ORDER BY CAST(DT_LEITURA AS DATE) DESC;

-- Processar cada data (da mais antiga para a mais recente)
DECLARE @DATA DATE;

DECLARE cur CURSOR FOR 
    SELECT DT_LEITURA FROM @DatasComDados ORDER BY DT_LEITURA ASC;

OPEN cur;
FETCH NEXT FROM cur INTO @DATA;

WHILE @@FETCH_STATUS = 0
BEGIN
    PRINT 'Processando: ' + CONVERT(VARCHAR, @DATA, 103);
    EXEC SP_PROCESSAR_MEDICAO_V2 @DT_PROCESSAMENTO = @DATA;
    FETCH NEXT FROM cur INTO @DATA;
END

CLOSE cur;
DEALLOCATE cur;

PRINT 'Historico populado!';
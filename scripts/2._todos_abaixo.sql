-- ============================================================================
-- ============================================================================
-- SIMP - SCRIPT CONSOLIDADO DE ANÁLISE DE MEDIÇÃO
-- ============================================================================
-- ============================================================================
--
-- Versão: 2.0 - Blindada contra NULLs
-- Data: Janeiro/2025
--
-- CONTEÚDO:
--   PARTE 1: Tabelas de Resumo
--   PARTE 2: Tabela de Limites Padrão
--   PARTE 3: View de Limites (com fallback)
--   PARTE 4: Stored Procedure Principal (blindada)
--   PARTE 5: Views para Dashboard
--   PARTE 6: SP de Contexto para IA
--
-- ORDEM DE EXECUÇÃO: Executar o script inteiro de uma vez
--
-- ============================================================================

-- COMO CARREGAR OS DADOS
-- Processar de 01/01/2025 até 15/01/2025
-- DECLARE @DATA DATE = '2025-01-01';
-- DECLARE @DATA_FIM DATE = '2025-01-15';

-- WHILE @DATA <= @DATA_FIM
-- BEGIN
--     PRINT 'Processando: ' + CONVERT(VARCHAR, @DATA, 103);
--     EXEC SP_PROCESSAR_MEDICAO_DIARIA @DT_PROCESSAMENTO = @DATA;
--     SET @DATA = DATEADD(DAY, 1, @DATA);
-- END



USE [SIMP];
GO

PRINT '============================================';
PRINT 'INICIO DA INSTALACAO - SIMP ANALISE MEDICAO';
PRINT '============================================';
PRINT '';

-- ============================================================================
-- ============================================================================
-- PARTE 1: TABELAS DE RESUMO
-- ============================================================================
-- ============================================================================

PRINT '>> PARTE 1: Criando tabelas de resumo...';
PRINT '';

-- ============================================================================
-- TABELA: MEDICAO_RESUMO_HORARIO
-- Detalhamento por hora (24 registros por ponto/dia)
-- ============================================================================

IF EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[MEDICAO_RESUMO_HORARIO]') AND type in (N'U'))
BEGIN
    DROP TABLE [dbo].[MEDICAO_RESUMO_HORARIO];
    PRINT '   Tabela MEDICAO_RESUMO_HORARIO removida (recriando).';
END
GO

CREATE TABLE [dbo].[MEDICAO_RESUMO_HORARIO] (
    [CD_CHAVE]                  INT IDENTITY(1,1) NOT NULL,
    [CD_PONTO_MEDICAO]          INT NOT NULL,
    [DT_HORA]                   DATETIME NOT NULL,
    [NR_HORA]                   TINYINT NOT NULL,
    [ID_TIPO_MEDIDOR]           INT NULL,
    
    -- Contagens
    [QTD_REGISTROS]             INT NULL DEFAULT 0,
    [QTD_ESPERADA]              INT NULL DEFAULT 60,
    [QTD_ZEROS]                 INT NULL DEFAULT 0,
    [QTD_VALORES_DISTINTOS]     INT NULL DEFAULT 0,
    
    -- Estatísticas da hora
    [VL_MEDIA]                  DECIMAL(18,4) NULL,
    [VL_MIN]                    DECIMAL(18,4) NULL,
    [VL_MAX]                    DECIMAL(18,4) NULL,
    [VL_SOMA]                   DECIMAL(18,4) NULL,
    [VL_PRIMEIRO]               DECIMAL(18,4) NULL,
    [VL_ULTIMO]                 DECIMAL(18,4) NULL,
    
    -- Variação (para detectar spikes)
    [VL_VARIACAO_MAX]           DECIMAL(18,4) NULL,
    [VL_VARIACAO_PERC_MAX]      DECIMAL(18,4) NULL,
    
    -- Flags de anomalia
    [FL_VALOR_CONSTANTE]        BIT NULL DEFAULT 0,
    [FL_VALOR_NEGATIVO]         BIT NULL DEFAULT 0,
    [FL_FORA_FAIXA]             BIT NULL DEFAULT 0,
    [FL_SPIKE]                  BIT NULL DEFAULT 0,
    [FL_ZEROS_SUSPEITOS]        BIT NULL DEFAULT 0,
    
    -- Tratamento
    [FL_TRATADO]                BIT NULL DEFAULT 0,
    [QTD_TRATADOS]              INT NULL DEFAULT 0,
    
    -- Anomalias
    [FL_ANOMALIA]               BIT NULL DEFAULT 0,
    [DS_TIPO_ANOMALIA]          VARCHAR(500) NULL,
    
    -- Comparação histórica
    [VL_MEDIA_HISTORICA]        DECIMAL(18,4) NULL,
    
    -- Controle
    [DT_PROCESSAMENTO]          DATETIME NULL DEFAULT GETDATE(),
    
    CONSTRAINT [PK_MEDICAO_RESUMO_HORARIO] PRIMARY KEY CLUSTERED ([CD_CHAVE] ASC),
    CONSTRAINT [UK_MEDICAO_RESUMO_HORARIO] UNIQUE ([CD_PONTO_MEDICAO], [DT_HORA])
);

CREATE NONCLUSTERED INDEX [IX_RESUMO_HORARIO_DATA] ON [dbo].[MEDICAO_RESUMO_HORARIO] ([DT_HORA]) INCLUDE ([CD_PONTO_MEDICAO]);
CREATE NONCLUSTERED INDEX [IX_RESUMO_HORARIO_PONTO] ON [dbo].[MEDICAO_RESUMO_HORARIO] ([CD_PONTO_MEDICAO], [DT_HORA]);
CREATE NONCLUSTERED INDEX [IX_RESUMO_HORARIO_ANOMALIA] ON [dbo].[MEDICAO_RESUMO_HORARIO] ([FL_ANOMALIA]) WHERE [FL_ANOMALIA] = 1;

PRINT '   Tabela MEDICAO_RESUMO_HORARIO criada.';
GO

-- ============================================================================
-- TABELA: MEDICAO_RESUMO_DIARIO
-- Visão consolidada do dia (1 registro por ponto/dia)
-- ============================================================================

IF EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[MEDICAO_RESUMO_DIARIO]') AND type in (N'U'))
BEGIN
    DROP TABLE [dbo].[MEDICAO_RESUMO_DIARIO];
    PRINT '   Tabela MEDICAO_RESUMO_DIARIO removida (recriando).';
END
GO

CREATE TABLE [dbo].[MEDICAO_RESUMO_DIARIO] (
    [CD_CHAVE]                  INT IDENTITY(1,1) NOT NULL,
    [CD_PONTO_MEDICAO]          INT NOT NULL,
    [DT_MEDICAO]                DATE NOT NULL,
    [ID_TIPO_MEDIDOR]           INT NULL,
    
    -- ========================================
    -- CONTAGENS E ESTATÍSTICAS BÁSICAS
    -- ========================================
    [QTD_REGISTROS]             INT NULL DEFAULT 0,
    [QTD_ESPERADA]              INT NULL DEFAULT 1440,
    [VL_MEDIA_DIARIA]           DECIMAL(18,4) NULL,
    [VL_MIN_DIARIO]             DECIMAL(18,4) NULL,
    [VL_MAX_DIARIO]             DECIMAL(18,4) NULL,
    [VL_DESVIO_PADRAO]          DECIMAL(18,4) NULL,
    [VL_SOMA_DIARIA]            DECIMAL(18,4) NULL,
    
    -- ========================================
    -- LIMITES DO PONTO
    -- ========================================
    [VL_LIMITE_INFERIOR]        DECIMAL(18,4) NULL,
    [VL_LIMITE_SUPERIOR]        DECIMAL(18,4) NULL,
    [VL_CAPACIDADE_NOMINAL]     DECIMAL(18,4) NULL,
    
    -- ========================================
    -- FLAGS DE INTEGRIDADE (Telemetria/Comunicação)
    -- ========================================
    [FL_SEM_COMUNICACAO]        BIT NULL DEFAULT 0,
    [FL_VALOR_CONSTANTE]        BIT NULL DEFAULT 0,
    [FL_ZEROS_SUSPEITOS]        BIT NULL DEFAULT 0,
    [QTD_VALORES_DISTINTOS]     INT NULL DEFAULT 0,
    [QTD_ZEROS]                 INT NULL DEFAULT 0,
    [QTD_HORAS_SEM_DADO]        INT NULL DEFAULT 0,
    
    -- ========================================
    -- FLAGS DE VALIDAÇÃO FÍSICA/HIDRÁULICA
    -- ========================================
    [FL_VALOR_NEGATIVO]         BIT NULL DEFAULT 0,
    [FL_FORA_FAIXA]             BIT NULL DEFAULT 0,
    [FL_SPIKE]                  BIT NULL DEFAULT 0,
    [FL_INCOMPATIVEL]           BIT NULL DEFAULT 0,
    [QTD_NEGATIVOS]             INT NULL DEFAULT 0,
    [QTD_FORA_FAIXA]            INT NULL DEFAULT 0,
    [QTD_SPIKES]                INT NULL DEFAULT 0,
    
    -- ========================================
    -- FLAGS DE CONSISTÊNCIA TEMPORAL
    -- ========================================
    [FL_PERFIL_ANOMALO]         BIT NULL DEFAULT 0,
    [FL_DESVIO_HISTORICO]       BIT NULL DEFAULT 0,
    [VL_MEDIA_HISTORICA]        DECIMAL(18,4) NULL,
    [VL_DESVIO_HISTORICO]       DECIMAL(18,4) NULL,
    
    -- ========================================
    -- TRATAMENTOS
    -- ========================================
    [ID_SITUACAO]               INT NULL DEFAULT 1,
    [QTD_TRATAMENTOS]           INT NULL DEFAULT 0,
    [DS_HORAS_TRATADAS]         VARCHAR(100) NULL,
    
    -- ========================================
    -- SCORE DE SAÚDE E ANOMALIAS
    -- ========================================
    [VL_SCORE_SAUDE]            INT NULL DEFAULT 10,
    [FL_ANOMALIA]               BIT NULL DEFAULT 0,
    [DS_ANOMALIAS]              VARCHAR(1000) NULL,
    [DS_TIPO_PROBLEMA]          VARCHAR(50) NULL,
    
    -- ========================================
    -- CONTROLE
    -- ========================================
    [DT_PROCESSAMENTO]          DATETIME NULL DEFAULT GETDATE(),
    
    CONSTRAINT [PK_MEDICAO_RESUMO_DIARIO] PRIMARY KEY CLUSTERED ([CD_CHAVE] ASC),
    CONSTRAINT [UK_MEDICAO_RESUMO_DIARIO] UNIQUE ([CD_PONTO_MEDICAO], [DT_MEDICAO])
);

CREATE NONCLUSTERED INDEX [IX_RESUMO_DIARIO_DATA] ON [dbo].[MEDICAO_RESUMO_DIARIO] ([DT_MEDICAO]) INCLUDE ([CD_PONTO_MEDICAO], [FL_ANOMALIA]);
CREATE NONCLUSTERED INDEX [IX_RESUMO_DIARIO_PONTO] ON [dbo].[MEDICAO_RESUMO_DIARIO] ([CD_PONTO_MEDICAO]) INCLUDE ([DT_MEDICAO], [ID_SITUACAO]);
CREATE NONCLUSTERED INDEX [IX_RESUMO_DIARIO_ANOMALIA] ON [dbo].[MEDICAO_RESUMO_DIARIO] ([FL_ANOMALIA]) WHERE [FL_ANOMALIA] = 1;
CREATE NONCLUSTERED INDEX [IX_RESUMO_DIARIO_SCORE] ON [dbo].[MEDICAO_RESUMO_DIARIO] ([VL_SCORE_SAUDE]) INCLUDE ([CD_PONTO_MEDICAO], [DT_MEDICAO]);

PRINT '   Tabela MEDICAO_RESUMO_DIARIO criada.';
GO

PRINT '';
PRINT '>> PARTE 1: Concluida.';
PRINT '';

-- ============================================================================
-- ============================================================================
-- PARTE 2: TABELA DE LIMITES PADRÃO
-- ============================================================================
-- ============================================================================

PRINT '>> PARTE 2: Criando tabela de limites padrao...';
PRINT '';

IF EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[LIMITES_PADRAO_TIPO_MEDIDOR]') AND type in (N'U'))
BEGIN
    DROP TABLE [dbo].[LIMITES_PADRAO_TIPO_MEDIDOR];
    PRINT '   Tabela LIMITES_PADRAO_TIPO_MEDIDOR removida (recriando).';
END
GO

CREATE TABLE [dbo].[LIMITES_PADRAO_TIPO_MEDIDOR] (
    [ID_TIPO_MEDIDOR]       INT PRIMARY KEY,
    [DS_TIPO_MEDIDOR]       VARCHAR(50) NOT NULL,
    [DS_UNIDADE]            VARCHAR(10) NOT NULL,
    [VL_LIMITE_INFERIOR]    DECIMAL(18,4) NULL,
    [VL_LIMITE_SUPERIOR]    DECIMAL(18,4) NULL,
    [VL_VARIACAO_MAX_PERC]  DECIMAL(18,4) NULL,
    [VL_ZEROS_MAX_PERC]     DECIMAL(18,4) NULL,
    [DS_OBSERVACAO]         VARCHAR(500) NULL
);

-- Inserir valores padrão (AJUSTE CONFORME SUA REALIDADE!)
INSERT INTO [dbo].[LIMITES_PADRAO_TIPO_MEDIDOR] VALUES
(1, 'Macromedidor',         'L/s',  0,    500,   200,  25, 'Vazao tipica de macromedidores. Ajustar conforme realidade local.'),
(2, 'Estacao Pitometrica',  'L/s',  0,    300,   200,  25, 'Vazao tipica de estacoes pitometricas.'),
(4, 'Medidor Pressao',      'mca',  0,    80,    50,   10, 'Pressao tipica: 10-60 mca. Zero pode indicar falha.'),
(6, 'Nivel Reservatorio',   '%',    0,    100,   30,   5,  'Nivel 0-100%. Zeros prolongados indicam problema.'),
(8, 'Hidrometro',           'L/s',  0,    50,    200,  25, 'Vazao tipica de hidrometros residenciais/comerciais.');

PRINT '   Tabela LIMITES_PADRAO_TIPO_MEDIDOR criada e populada.';
GO

PRINT '';
PRINT '>> PARTE 2: Concluida.';
PRINT '';

-- ============================================================================
-- ============================================================================
-- PARTE 3: VIEW DE LIMITES (COM FALLBACK)
-- ============================================================================
-- ============================================================================

PRINT '>> PARTE 3: Criando view de limites com fallback...';
PRINT '';

IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_PONTO_MEDICAO_LIMITES')
    DROP VIEW [dbo].[VW_PONTO_MEDICAO_LIMITES];
GO

CREATE VIEW [dbo].[VW_PONTO_MEDICAO_LIMITES]
AS
WITH LimitesEstatisticos AS (
    SELECT 
        CD_PONTO_MEDICAO,
        ID_TIPO_MEDICAO AS ID_TIPO_MEDIDOR,
        AVG(CASE 
            WHEN ID_TIPO_MEDICAO IN (1, 2, 8) THEN ISNULL(VL_VAZAO_EFETIVA, VL_VAZAO)
            WHEN ID_TIPO_MEDICAO = 4 THEN VL_PRESSAO
            WHEN ID_TIPO_MEDICAO = 6 THEN VL_RESERVATORIO
        END) AS VL_MEDIA_HIST,
        STDEV(CASE 
            WHEN ID_TIPO_MEDICAO IN (1, 2, 8) THEN ISNULL(VL_VAZAO_EFETIVA, VL_VAZAO)
            WHEN ID_TIPO_MEDICAO = 4 THEN VL_PRESSAO
            WHEN ID_TIPO_MEDICAO = 6 THEN VL_RESERVATORIO
        END) AS VL_DESVIO_HIST,
        MIN(CASE 
            WHEN ID_TIPO_MEDICAO IN (1, 2, 8) THEN ISNULL(VL_VAZAO_EFETIVA, VL_VAZAO)
            WHEN ID_TIPO_MEDICAO = 4 THEN VL_PRESSAO
            WHEN ID_TIPO_MEDICAO = 6 THEN VL_RESERVATORIO
        END) AS VL_MIN_HIST,
        MAX(CASE 
            WHEN ID_TIPO_MEDICAO IN (1, 2, 8) THEN ISNULL(VL_VAZAO_EFETIVA, VL_VAZAO)
            WHEN ID_TIPO_MEDICAO = 4 THEN VL_PRESSAO
            WHEN ID_TIPO_MEDICAO = 6 THEN VL_RESERVATORIO
        END) AS VL_MAX_HIST,
        COUNT(*) AS QTD_REGISTROS_HIST
    FROM [dbo].[REGISTRO_VAZAO_PRESSAO]
    WHERE DT_LEITURA >= DATEADD(DAY, -30, GETDATE())
      AND ISNULL(ID_SITUACAO, 1) IN (1, 2)
    GROUP BY CD_PONTO_MEDICAO, ID_TIPO_MEDICAO
    HAVING COUNT(*) >= 1000
)
SELECT 
    PM.CD_PONTO_MEDICAO,
    PM.DS_NOME,
    PM.ID_TIPO_MEDIDOR,
    
    -- LIMITE INFERIOR: Cadastro > Estatístico > Padrão
    COALESCE(
        PM.VL_LIMITE_INFERIOR_VAZAO,
        CASE 
            WHEN LE.VL_MEDIA_HIST IS NOT NULL AND LE.VL_DESVIO_HIST IS NOT NULL
            THEN CASE 
                WHEN (LE.VL_MEDIA_HIST - 3 * ISNULL(LE.VL_DESVIO_HIST, 0)) < 0 THEN 0
                ELSE (LE.VL_MEDIA_HIST - 3 * ISNULL(LE.VL_DESVIO_HIST, 0))
            END
        END,
        LP.VL_LIMITE_INFERIOR,
        0
    ) AS VL_LIMITE_INFERIOR,
    
    -- LIMITE SUPERIOR: Cadastro > Capacidade > Estatístico > Padrão
    COALESCE(
        PM.VL_LIMITE_SUPERIOR_VAZAO,
        CASE PM.ID_TIPO_MEDIDOR
            WHEN 1 THEN MAC.VL_CAPACIDADE_NOMINAL
            WHEN 8 THEN HID.VL_LEITURA_LIMITE
        END,
        CASE 
            WHEN LE.VL_MEDIA_HIST IS NOT NULL AND LE.VL_DESVIO_HIST IS NOT NULL
            THEN CASE 
                WHEN (LE.VL_MEDIA_HIST + 3 * ISNULL(LE.VL_DESVIO_HIST, 0)) > (ISNULL(LE.VL_MAX_HIST, 0) * 1.5)
                THEN ISNULL(LE.VL_MAX_HIST, 0) * 1.5
                ELSE (LE.VL_MEDIA_HIST + 3 * ISNULL(LE.VL_DESVIO_HIST, 0))
            END
        END,
        LP.VL_LIMITE_SUPERIOR,
        999999
    ) AS VL_LIMITE_SUPERIOR,
    
    -- CAPACIDADE NOMINAL
    COALESCE(
        CASE PM.ID_TIPO_MEDIDOR
            WHEN 1 THEN MAC.VL_CAPACIDADE_NOMINAL
            WHEN 6 THEN NR.VL_VOLUME_TOTAL
            WHEN 8 THEN HID.VL_LEITURA_LIMITE
        END,
        LP.VL_LIMITE_SUPERIOR
    ) AS VL_CAPACIDADE_NOMINAL,
    
    -- VARIAÇÃO MÁXIMA PERMITIDA (%)
    COALESCE(
        CASE 
            WHEN ISNULL(LE.VL_MEDIA_HIST, 0) > 0.001 AND LE.VL_DESVIO_HIST IS NOT NULL
            THEN (ISNULL(LE.VL_DESVIO_HIST, 0) / LE.VL_MEDIA_HIST) * 300
        END,
        LP.VL_VARIACAO_MAX_PERC,
        200
    ) AS VL_VARIACAO_MAX_PERC,
    
    -- % MÁXIMA DE ZEROS
    COALESCE(LP.VL_ZEROS_MAX_PERC, 25) AS VL_ZEROS_MAX_PERC,
    
    -- VALORES ESTATÍSTICOS
    LE.VL_MEDIA_HIST,
    LE.VL_DESVIO_HIST,
    LE.VL_MIN_HIST,
    LE.VL_MAX_HIST,
    LE.QTD_REGISTROS_HIST,
    
    -- ORIGEM DOS LIMITES
    CASE 
        WHEN PM.VL_LIMITE_INFERIOR_VAZAO IS NOT NULL THEN 'CADASTRO'
        WHEN LE.VL_MEDIA_HIST IS NOT NULL THEN 'ESTATISTICO'
        WHEN LP.VL_LIMITE_INFERIOR IS NOT NULL THEN 'PADRAO'
        ELSE 'DEFAULT'
    END AS DS_ORIGEM_LIMITE_INF,
    
    CASE 
        WHEN PM.VL_LIMITE_SUPERIOR_VAZAO IS NOT NULL THEN 'CADASTRO'
        WHEN MAC.VL_CAPACIDADE_NOMINAL IS NOT NULL OR HID.VL_LEITURA_LIMITE IS NOT NULL THEN 'EQUIPAMENTO'
        WHEN LE.VL_MEDIA_HIST IS NOT NULL THEN 'ESTATISTICO'
        WHEN LP.VL_LIMITE_SUPERIOR IS NOT NULL THEN 'PADRAO'
        ELSE 'DEFAULT'
    END AS DS_ORIGEM_LIMITE_SUP,
    
    -- OUTRAS INFORMAÇÕES
    PM.VL_FATOR_CORRECAO_VAZAO,
    CASE PM.ID_TIPO_MEDIDOR
        WHEN 1 THEN MAC.VL_VAZAO_ESPERADA
        ELSE NULL
    END AS VL_VAZAO_ESPERADA,
    CASE 
        WHEN PM.DT_DESATIVACAO IS NULL OR PM.DT_DESATIVACAO > GETDATE() 
        THEN 1 ELSE 0 
    END AS FL_ATIVO

FROM [dbo].[PONTO_MEDICAO] PM
LEFT JOIN [dbo].[LIMITES_PADRAO_TIPO_MEDIDOR] LP ON PM.ID_TIPO_MEDIDOR = LP.ID_TIPO_MEDIDOR
LEFT JOIN LimitesEstatisticos LE ON PM.CD_PONTO_MEDICAO = LE.CD_PONTO_MEDICAO
LEFT JOIN [dbo].[MACROMEDIDOR] MAC ON PM.CD_PONTO_MEDICAO = MAC.CD_PONTO_MEDICAO AND PM.ID_TIPO_MEDIDOR = 1
LEFT JOIN [dbo].[ESTACAO_PITOMETRICA] EP ON PM.CD_PONTO_MEDICAO = EP.CD_PONTO_MEDICAO AND PM.ID_TIPO_MEDIDOR = 2
LEFT JOIN [dbo].[MEDIDOR_PRESSAO] MP ON PM.CD_PONTO_MEDICAO = MP.CD_PONTO_MEDICAO AND PM.ID_TIPO_MEDIDOR = 4
LEFT JOIN [dbo].[NIVEL_RESERVATORIO] NR ON PM.CD_PONTO_MEDICAO = NR.CD_PONTO_MEDICAO AND PM.ID_TIPO_MEDIDOR = 6
LEFT JOIN [dbo].[HIDROMETRO] HID ON PM.CD_PONTO_MEDICAO = HID.CD_PONTO_MEDICAO AND PM.ID_TIPO_MEDIDOR = 8;
GO

PRINT '   View VW_PONTO_MEDICAO_LIMITES criada.';
GO

-- View auxiliar: Pontos sem cadastro completo
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_PONTOS_SEM_CADASTRO')
    DROP VIEW [dbo].[VW_PONTOS_SEM_CADASTRO];
GO

CREATE VIEW [dbo].[VW_PONTOS_SEM_CADASTRO]
AS
SELECT 
    PML.CD_PONTO_MEDICAO,
    PML.DS_NOME,
    PML.ID_TIPO_MEDIDOR,
    LP.DS_TIPO_MEDIDOR,
    CASE 
        WHEN PML.DS_ORIGEM_LIMITE_INF = 'CADASTRO' AND PML.DS_ORIGEM_LIMITE_SUP IN ('CADASTRO', 'EQUIPAMENTO')
        THEN 'COMPLETO'
        WHEN PML.DS_ORIGEM_LIMITE_SUP = 'ESTATISTICO' OR PML.DS_ORIGEM_LIMITE_INF = 'ESTATISTICO'
        THEN 'ESTATISTICO'
        ELSE 'PADRAO'
    END AS STATUS_CADASTRO,
    PML.DS_ORIGEM_LIMITE_INF,
    PML.DS_ORIGEM_LIMITE_SUP,
    PML.VL_LIMITE_INFERIOR,
    PML.VL_LIMITE_SUPERIOR,
    PML.VL_CAPACIDADE_NOMINAL,
    PML.VL_MEDIA_HIST,
    PML.VL_DESVIO_HIST,
    PML.QTD_REGISTROS_HIST,
    CASE 
        WHEN PML.VL_MEDIA_HIST IS NOT NULL 
        THEN 'Sugestao: Inferior=' + 
             CAST(CAST(CASE WHEN ISNULL(PML.VL_MEDIA_HIST,0) - 2*ISNULL(PML.VL_DESVIO_HIST,0) < 0 THEN 0 
                       ELSE ISNULL(PML.VL_MEDIA_HIST,0) - 2*ISNULL(PML.VL_DESVIO_HIST,0) END AS DECIMAL(10,2)) AS VARCHAR) +
             ', Superior=' + 
             CAST(CAST(ISNULL(PML.VL_MEDIA_HIST,0) + 2*ISNULL(PML.VL_DESVIO_HIST,0) AS DECIMAL(10,2)) AS VARCHAR)
        ELSE 'Sem historico suficiente'
    END AS DS_SUGESTAO_LIMITES
FROM [dbo].[VW_PONTO_MEDICAO_LIMITES] PML
LEFT JOIN [dbo].[LIMITES_PADRAO_TIPO_MEDIDOR] LP ON PML.ID_TIPO_MEDIDOR = LP.ID_TIPO_MEDIDOR
WHERE PML.FL_ATIVO = 1
  AND (PML.DS_ORIGEM_LIMITE_INF NOT IN ('CADASTRO') OR PML.DS_ORIGEM_LIMITE_SUP NOT IN ('CADASTRO', 'EQUIPAMENTO'));
GO

PRINT '   View VW_PONTOS_SEM_CADASTRO criada.';
GO

PRINT '';
PRINT '>> PARTE 3: Concluida.';
PRINT '';

-- ============================================================================
-- ============================================================================
-- PARTE 4: STORED PROCEDURE PRINCIPAL (BLINDADA)
-- ============================================================================
-- ============================================================================

PRINT '>> PARTE 4: Criando stored procedure principal...';
PRINT '';

-- ============================================================================
-- SP_PROCESSAR_MEDICAO_DIARIA (VERSÃO CORRIGIDA)
-- Correção: Removido ID_TIPO_MEDICAO do GROUP BY para evitar duplicatas
-- ============================================================================

IF EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[SP_PROCESSAR_MEDICAO_DIARIA]') AND type in (N'P'))
    DROP PROCEDURE [dbo].[SP_PROCESSAR_MEDICAO_DIARIA];
GO

CREATE PROCEDURE [dbo].[SP_PROCESSAR_MEDICAO_DIARIA]
    @DT_PROCESSAMENTO DATE = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    IF @DT_PROCESSAMENTO IS NULL
        SET @DT_PROCESSAMENTO = CAST(DATEADD(DAY, -1, GETDATE()) AS DATE);
    
    DECLARE @DT_INICIO DATETIME = CAST(@DT_PROCESSAMENTO AS DATETIME);
    DECLARE @DT_FIM DATETIME = DATEADD(DAY, 1, @DT_INICIO);
    
    PRINT '============================================';
    PRINT 'PROCESSAMENTO DE MEDICAO - VERSAO BLINDADA';
    PRINT '============================================';
    PRINT 'Data: ' + CONVERT(VARCHAR, @DT_PROCESSAMENTO, 103);
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- ====================================================================
        -- ETAPA 0: Limpar dados existentes
        -- ====================================================================
        DELETE FROM [dbo].[MEDICAO_RESUMO_HORARIO] WHERE CAST([DT_HORA] AS DATE) = @DT_PROCESSAMENTO;
        DELETE FROM [dbo].[MEDICAO_RESUMO_DIARIO] WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO;
        PRINT 'Etapa 0: Dados anteriores removidos.';
        
        -- ====================================================================
        -- ETAPA 1: Popular MEDICAO_RESUMO_HORARIO
        -- CORRIGIDO: Removido ID_TIPO_MEDICAO do GROUP BY, usando MAX()
        -- ====================================================================
        INSERT INTO [dbo].[MEDICAO_RESUMO_HORARIO] (
            [CD_PONTO_MEDICAO], [DT_HORA], [NR_HORA], [ID_TIPO_MEDIDOR],
            [QTD_REGISTROS], [QTD_ZEROS], [QTD_VALORES_DISTINTOS],
            [VL_MEDIA], [VL_MIN], [VL_MAX], [VL_SOMA],
            [FL_TRATADO], [QTD_TRATADOS], [DT_PROCESSAMENTO]
        )
        SELECT 
            RVP.[CD_PONTO_MEDICAO],
            DATEADD(HOUR, DATEPART(HOUR, RVP.[DT_LEITURA]), CAST(CAST(RVP.[DT_LEITURA] AS DATE) AS DATETIME)),
            DATEPART(HOUR, RVP.[DT_LEITURA]),
            MAX(ISNULL(RVP.[ID_TIPO_MEDICAO], 0)),  -- MAX em vez de GROUP BY
            COUNT(*),
            SUM(CASE 
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) IN (1, 2, 8) 
                     AND ISNULL(ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO]), 0) = 0 THEN 1
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 4 
                     AND ISNULL(RVP.[VL_PRESSAO], 0) = 0 THEN 1
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 6 
                     AND ISNULL(RVP.[VL_RESERVATORIO], 0) = 0 THEN 1
                ELSE 0
            END),
            COUNT(DISTINCT CASE 
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) IN (1, 2, 8) 
                     THEN CAST(ROUND(ISNULL(ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO]), 0), 2) AS VARCHAR)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 4 
                     THEN CAST(ROUND(ISNULL(RVP.[VL_PRESSAO], 0), 2) AS VARCHAR)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 6 
                     THEN CAST(ROUND(ISNULL(RVP.[VL_RESERVATORIO], 0), 2) AS VARCHAR)
                ELSE '0'
            END),
            AVG(CASE 
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) IN (1, 2, 8) THEN ISNULL(ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO]), 0)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 4 THEN ISNULL(RVP.[VL_PRESSAO], 0)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 6 THEN ISNULL(RVP.[VL_RESERVATORIO], 0)
                ELSE 0
            END),
            MIN(CASE 
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) IN (1, 2, 8) THEN ISNULL(ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO]), 0)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 4 THEN ISNULL(RVP.[VL_PRESSAO], 0)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 6 THEN ISNULL(RVP.[VL_RESERVATORIO], 0)
                ELSE 0
            END),
            MAX(CASE 
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) IN (1, 2, 8) THEN ISNULL(ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO]), 0)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 4 THEN ISNULL(RVP.[VL_PRESSAO], 0)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 6 THEN ISNULL(RVP.[VL_RESERVATORIO], 0)
                ELSE 0
            END),
            SUM(CASE 
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) IN (1, 2, 8) THEN ISNULL(ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO]), 0)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 4 THEN ISNULL(RVP.[VL_PRESSAO], 0)
                WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 6 THEN ISNULL(RVP.[VL_RESERVATORIO], 0)
                ELSE 0
            END),
            MAX(CASE WHEN ISNULL(RVP.[ID_SITUACAO], 1) = 2 THEN 1 ELSE 0 END),
            SUM(CASE WHEN ISNULL(RVP.[ID_SITUACAO], 1) = 2 THEN 1 ELSE 0 END),
            GETDATE()
        FROM [dbo].[REGISTRO_VAZAO_PRESSAO] RVP
        WHERE RVP.[DT_LEITURA] >= @DT_INICIO 
          AND RVP.[DT_LEITURA] < @DT_FIM
          AND ISNULL(RVP.[ID_SITUACAO], 1) IN (1, 2)
        GROUP BY 
            RVP.[CD_PONTO_MEDICAO],
            DATEADD(HOUR, DATEPART(HOUR, RVP.[DT_LEITURA]), CAST(CAST(RVP.[DT_LEITURA] AS DATE) AS DATETIME)),
            DATEPART(HOUR, RVP.[DT_LEITURA]);
        
        DECLARE @QTD_HORARIO INT = @@ROWCOUNT;
        PRINT 'Etapa 1: MEDICAO_RESUMO_HORARIO - ' + CAST(@QTD_HORARIO AS VARCHAR) + ' registros.';
        
        -- ====================================================================
        -- ETAPA 2: Calcular variações (spikes)
        -- ====================================================================
        ;WITH VariacaoHoraria AS (
            SELECT 
                RVP.CD_PONTO_MEDICAO,
                DATEADD(HOUR, DATEPART(HOUR, RVP.DT_LEITURA), CAST(CAST(RVP.DT_LEITURA AS DATE) AS DATETIME)) AS DT_HORA,
                CASE 
                    WHEN ISNULL(RVP.ID_TIPO_MEDICAO, 0) IN (1, 2, 8) THEN ISNULL(ISNULL(RVP.VL_VAZAO_EFETIVA, RVP.VL_VAZAO), 0)
                    WHEN ISNULL(RVP.ID_TIPO_MEDICAO, 0) = 4 THEN ISNULL(RVP.VL_PRESSAO, 0)
                    WHEN ISNULL(RVP.ID_TIPO_MEDICAO, 0) = 6 THEN ISNULL(RVP.VL_RESERVATORIO, 0)
                    ELSE 0
                END AS VALOR,
                LAG(CASE 
                    WHEN ISNULL(RVP.ID_TIPO_MEDICAO, 0) IN (1, 2, 8) THEN ISNULL(ISNULL(RVP.VL_VAZAO_EFETIVA, RVP.VL_VAZAO), 0)
                    WHEN ISNULL(RVP.ID_TIPO_MEDICAO, 0) = 4 THEN ISNULL(RVP.VL_PRESSAO, 0)
                    WHEN ISNULL(RVP.ID_TIPO_MEDICAO, 0) = 6 THEN ISNULL(RVP.VL_RESERVATORIO, 0)
                    ELSE 0
                END) OVER (PARTITION BY RVP.CD_PONTO_MEDICAO ORDER BY RVP.DT_LEITURA) AS VALOR_ANT
            FROM [dbo].[REGISTRO_VAZAO_PRESSAO] RVP
            WHERE RVP.DT_LEITURA >= @DT_INICIO 
              AND RVP.DT_LEITURA < @DT_FIM
              AND ISNULL(RVP.ID_SITUACAO, 1) IN (1, 2)
        ),
        VariacaoCalc AS (
            SELECT 
                CD_PONTO_MEDICAO,
                DT_HORA,
                MAX(ABS(ISNULL(VALOR, 0) - ISNULL(VALOR_ANT, 0))) AS VL_VARIACAO_MAX,
                MAX(CASE 
                    WHEN ISNULL(VALOR_ANT, 0) > 0.001
                    THEN ABS((ISNULL(VALOR, 0) - ISNULL(VALOR_ANT, 0)) / VALOR_ANT) * 100 
                    ELSE 0 
                END) AS VL_VARIACAO_PERC_MAX
            FROM VariacaoHoraria
            WHERE VALOR_ANT IS NOT NULL
            GROUP BY CD_PONTO_MEDICAO, DT_HORA
        )
        UPDATE MRH
        SET VL_VARIACAO_MAX = ISNULL(VC.VL_VARIACAO_MAX, 0),
            VL_VARIACAO_PERC_MAX = ISNULL(VC.VL_VARIACAO_PERC_MAX, 0)
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        INNER JOIN VariacaoCalc VC ON MRH.CD_PONTO_MEDICAO = VC.CD_PONTO_MEDICAO 
            AND MRH.DT_HORA = VC.DT_HORA;
        
        PRINT 'Etapa 2: Variacoes calculadas.';
        
        -- ====================================================================
        -- ETAPA 3: Média histórica por hora
        -- ====================================================================
        UPDATE MRH
        SET [VL_MEDIA_HISTORICA] = ISNULL(HIST.VL_MEDIA_HIST, 0)
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        INNER JOIN (
            SELECT 
                RVP.[CD_PONTO_MEDICAO],
                DATEPART(HOUR, RVP.[DT_LEITURA]) AS NR_HORA,
                AVG(CASE 
                    WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) IN (1, 2, 8) THEN ISNULL(ISNULL(RVP.[VL_VAZAO_EFETIVA], RVP.[VL_VAZAO]), 0)
                    WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 4 THEN ISNULL(RVP.[VL_PRESSAO], 0)
                    WHEN ISNULL(RVP.[ID_TIPO_MEDICAO], 0) = 6 THEN ISNULL(RVP.[VL_RESERVATORIO], 0)
                    ELSE 0
                END) AS VL_MEDIA_HIST
            FROM [dbo].[REGISTRO_VAZAO_PRESSAO] RVP
            WHERE RVP.[DT_LEITURA] >= DATEADD(WEEK, -4, @DT_INICIO)
              AND RVP.[DT_LEITURA] < @DT_INICIO
              AND DATEPART(WEEKDAY, RVP.[DT_LEITURA]) = DATEPART(WEEKDAY, @DT_PROCESSAMENTO)
              AND ISNULL(RVP.[ID_SITUACAO], 1) IN (1, 2)
            GROUP BY RVP.[CD_PONTO_MEDICAO], DATEPART(HOUR, RVP.[DT_LEITURA])
        ) HIST ON MRH.[CD_PONTO_MEDICAO] = HIST.[CD_PONTO_MEDICAO] 
              AND MRH.[NR_HORA] = HIST.[NR_HORA]
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO;
        
        PRINT 'Etapa 3: Media historica calculada.';
        
        -- ====================================================================
        -- ETAPA 4: DETECTAR ANOMALIAS HORÁRIAS
        -- ====================================================================
        
        -- 4.1 VALOR CONSTANTE
        UPDATE MRH
        SET [FL_VALOR_CONSTANTE] = 1, [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'VALOR_CONSTANTE'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND ISNULL(MRH.[QTD_VALORES_DISTINTOS], 0) = 1
          AND ISNULL(MRH.[QTD_REGISTROS], 0) >= 50
          AND ISNULL(MRH.[VL_MEDIA], 0) <> 0;
        
        -- 4.2 VALOR NEGATIVO
        UPDATE MRH
        SET [FL_VALOR_NEGATIVO] = 1, [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 
                'VALOR_NEGATIVO_' + CAST(CAST(ISNULL([VL_MIN], 0) AS DECIMAL(10,2)) AS VARCHAR)
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND ISNULL(MRH.[VL_MIN], 0) < 0;
        
        -- 4.3 FORA DA FAIXA (usando view de limites)
        IF EXISTS (SELECT 1 FROM sys.views WHERE name = 'VW_PONTO_MEDICAO_LIMITES')
        BEGIN
            UPDATE MRH
            SET [FL_FORA_FAIXA] = 1, [FL_ANOMALIA] = 1,
                [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 
                    CASE 
                        WHEN ISNULL(MRH.[VL_MAX], 0) > ISNULL(PML.VL_LIMITE_SUPERIOR, 999999)
                        THEN 'ACIMA_LIMITE_' + CAST(CAST(ISNULL(MRH.[VL_MAX], 0) AS DECIMAL(10,2)) AS VARCHAR)
                        ELSE 'ABAIXO_LIMITE_' + CAST(CAST(ISNULL(MRH.[VL_MIN], 0) AS DECIMAL(10,2)) AS VARCHAR)
                    END
            FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
            INNER JOIN [dbo].[VW_PONTO_MEDICAO_LIMITES] PML ON MRH.[CD_PONTO_MEDICAO] = PML.[CD_PONTO_MEDICAO]
            WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
              AND ISNULL(MRH.[ID_TIPO_MEDIDOR], 0) IN (1, 2, 8)
              AND (
                  (ISNULL(PML.VL_LIMITE_SUPERIOR, 0) > 0 AND ISNULL(MRH.[VL_MAX], 0) > PML.VL_LIMITE_SUPERIOR)
                  OR (PML.VL_LIMITE_INFERIOR IS NOT NULL AND PML.VL_LIMITE_INFERIOR > 0 
                      AND ISNULL(MRH.[VL_MIN], 0) < PML.VL_LIMITE_INFERIOR AND ISNULL(MRH.[VL_MIN], 0) <> 0)
              );
        END
        
        -- 4.4 SPIKE (variação > 200%)
        UPDATE MRH
        SET [FL_SPIKE] = 1, [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 
                'SPIKE_' + CAST(CAST(ISNULL([VL_VARIACAO_PERC_MAX], 0) AS INT) AS VARCHAR) + '%'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND ISNULL(MRH.[VL_VARIACAO_PERC_MAX], 0) > 200;
        
        -- 4.5 ZEROS SUSPEITOS
        UPDATE MRH
        SET [FL_ZEROS_SUSPEITOS] = 1, [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 
                'ZEROS_SUSPEITOS_' + CAST(ISNULL([QTD_ZEROS], 0) AS VARCHAR) + 'MIN'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND ISNULL(MRH.[QTD_ZEROS], 0) >= 30
          AND ISNULL(MRH.[VL_MEDIA_HISTORICA], 0) > 0.001
          AND MRH.[NR_HORA] BETWEEN 6 AND 22;
        
        -- 4.6 REGISTROS INCOMPLETOS
        UPDATE MRH
        SET [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 
                'INCOMPLETO_' + CAST(ISNULL([QTD_REGISTROS], 0) AS VARCHAR) + '/60'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND ISNULL(MRH.[QTD_REGISTROS], 0) < 50;
        
        -- 4.7 PRESSÃO específicas
        UPDATE MRH
        SET [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 
                CASE 
                    WHEN ISNULL(MRH.[VL_MEDIA], 0) > 0 AND ISNULL(MRH.[VL_MEDIA], 0) < 10 
                        THEN 'PRESSAO_BAIXA_' + CAST(CAST(ISNULL([VL_MEDIA], 0) AS INT) AS VARCHAR) + 'MCA'
                    WHEN ISNULL(MRH.[VL_MAX], 0) > 60 
                        THEN 'PRESSAO_ALTA_' + CAST(CAST(ISNULL([VL_MAX], 0) AS INT) AS VARCHAR) + 'MCA'
                    ELSE 'VARIACAO_PRESSAO_' + CAST(CAST(ISNULL([VL_MAX], 0) - ISNULL([VL_MIN], 0) AS INT) AS VARCHAR) + 'MCA'
                END
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND ISNULL(MRH.[ID_TIPO_MEDIDOR], 0) = 4
          AND (
              (ISNULL(MRH.[VL_MEDIA], 0) > 0 AND ISNULL(MRH.[VL_MEDIA], 0) < 10)
              OR ISNULL(MRH.[VL_MAX], 0) > 60
              OR (ISNULL(MRH.[VL_MAX], 0) - ISNULL(MRH.[VL_MIN], 0)) > 20
          );
        
        -- 4.8 NÍVEL >= 100%
        UPDATE MRH
        SET [FL_ANOMALIA] = 1,
            [DS_TIPO_ANOMALIA] = ISNULL([DS_TIPO_ANOMALIA] + '; ', '') + 'NIVEL_100_EXTRAVASAMENTO'
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
          AND ISNULL(MRH.[ID_TIPO_MEDIDOR], 0) = 6
          AND ISNULL(MRH.[VL_MAX], 0) >= 100;
        
        PRINT 'Etapa 4: Anomalias horarias detectadas.';
        
        -- ====================================================================
        -- ETAPA 5: Popular MEDICAO_RESUMO_DIARIO
        -- CORRIGIDO: Removido ID_TIPO_MEDIDOR do GROUP BY, usando MAX()
        -- ====================================================================
        INSERT INTO [dbo].[MEDICAO_RESUMO_DIARIO] (
            [CD_PONTO_MEDICAO], [DT_MEDICAO], [ID_TIPO_MEDIDOR],
            [QTD_REGISTROS], [QTD_ESPERADA],
            [VL_MEDIA_DIARIA], [VL_MIN_DIARIO], [VL_MAX_DIARIO], 
            [VL_DESVIO_PADRAO], [VL_SOMA_DIARIA],
            [QTD_VALORES_DISTINTOS], [QTD_ZEROS], [QTD_HORAS_SEM_DADO],
            [ID_SITUACAO], [QTD_TRATAMENTOS], [DS_HORAS_TRATADAS],
            [DT_PROCESSAMENTO]
        )
        SELECT 
            MRH.[CD_PONTO_MEDICAO],
            @DT_PROCESSAMENTO,
            MAX(ISNULL(MRH.[ID_TIPO_MEDIDOR], 0)),  -- MAX em vez de GROUP BY
            ISNULL(SUM(ISNULL(MRH.[QTD_REGISTROS], 0)), 0),
            1440,
            CASE 
                WHEN ISNULL(SUM(ISNULL(MRH.[QTD_REGISTROS], 0)), 0) > 0 
                THEN ISNULL(SUM(ISNULL(MRH.[VL_SOMA], 0)), 0) / SUM(ISNULL(MRH.[QTD_REGISTROS], 0))
                ELSE 0 
            END,
            ISNULL(MIN(ISNULL(MRH.[VL_MIN], 0)), 0),
            ISNULL(MAX(ISNULL(MRH.[VL_MAX], 0)), 0),
            ISNULL(STDEV(ISNULL(MRH.[VL_MEDIA], 0)), 0),
            ISNULL(SUM(ISNULL(MRH.[VL_SOMA], 0)), 0),
            ISNULL(SUM(ISNULL(MRH.[QTD_VALORES_DISTINTOS], 0)), 0),
            ISNULL(SUM(ISNULL(MRH.[QTD_ZEROS], 0)), 0),
            24 - COUNT(*),
            CASE WHEN ISNULL(MAX(CAST(ISNULL(MRH.[FL_TRATADO], 0) AS INT)), 0) = 1 THEN 2 ELSE 1 END,
            ISNULL(SUM(ISNULL(MRH.[QTD_TRATADOS], 0)), 0),
            STUFF((
                SELECT ',' + CAST(MRH2.[NR_HORA] AS VARCHAR)
                FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH2
                WHERE MRH2.[CD_PONTO_MEDICAO] = MRH.[CD_PONTO_MEDICAO]
                  AND CAST(MRH2.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
                  AND ISNULL(MRH2.[FL_TRATADO], 0) = 1
                ORDER BY MRH2.[NR_HORA]
                FOR XML PATH('')
            ), 1, 1, ''),
            GETDATE()
        FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
        WHERE CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
        GROUP BY MRH.[CD_PONTO_MEDICAO];
        
        DECLARE @QTD_DIARIO INT = @@ROWCOUNT;
        PRINT 'Etapa 5: MEDICAO_RESUMO_DIARIO - ' + CAST(@QTD_DIARIO AS VARCHAR) + ' registros.';
        
        -- ====================================================================
        -- ETAPA 6: Atualizar limites da view
        -- ====================================================================
        IF EXISTS (SELECT 1 FROM sys.views WHERE name = 'VW_PONTO_MEDICAO_LIMITES')
        BEGIN
            UPDATE MRD
            SET [VL_LIMITE_INFERIOR] = ISNULL(PML.VL_LIMITE_INFERIOR, 0),
                [VL_LIMITE_SUPERIOR] = ISNULL(PML.VL_LIMITE_SUPERIOR, 999999),
                [VL_CAPACIDADE_NOMINAL] = PML.VL_CAPACIDADE_NOMINAL
            FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
            INNER JOIN [dbo].[VW_PONTO_MEDICAO_LIMITES] PML ON MRD.[CD_PONTO_MEDICAO] = PML.[CD_PONTO_MEDICAO]
            WHERE MRD.[DT_MEDICAO] = @DT_PROCESSAMENTO;
        END
        
        PRINT 'Etapa 6: Limites atualizados.';
        
        -- ====================================================================
        -- ETAPA 7: Média histórica diária
        -- ====================================================================
        UPDATE MRD
        SET [VL_MEDIA_HISTORICA] = ISNULL(HIST.VL_MEDIA_HIST, 0),
            [VL_DESVIO_HISTORICO] = CASE 
                WHEN ISNULL(HIST.VL_MEDIA_HIST, 0) > 0.001
                THEN ((ISNULL(MRD.[VL_MEDIA_DIARIA], 0) - HIST.VL_MEDIA_HIST) / HIST.VL_MEDIA_HIST) * 100
                ELSE 0 
            END
        FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
        INNER JOIN (
            SELECT [CD_PONTO_MEDICAO], AVG(ISNULL([VL_MEDIA_DIARIA], 0)) AS VL_MEDIA_HIST
            FROM [dbo].[MEDICAO_RESUMO_DIARIO]
            WHERE [DT_MEDICAO] >= DATEADD(WEEK, -4, @DT_PROCESSAMENTO)
              AND [DT_MEDICAO] < @DT_PROCESSAMENTO
              AND DATEPART(WEEKDAY, [DT_MEDICAO]) = DATEPART(WEEKDAY, @DT_PROCESSAMENTO)
            GROUP BY [CD_PONTO_MEDICAO]
        ) HIST ON MRD.[CD_PONTO_MEDICAO] = HIST.[CD_PONTO_MEDICAO]
        WHERE MRD.[DT_MEDICAO] = @DT_PROCESSAMENTO;
        
        PRINT 'Etapa 7: Media historica diaria calculada.';
        
        -- ====================================================================
        -- ETAPA 8: FLAGS DIÁRIAS
        -- ====================================================================
        
        -- 8.1 SEM COMUNICAÇÃO
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO] SET [FL_SEM_COMUNICACAO] = 1
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO AND ISNULL([QTD_REGISTROS], 0) < 720;
        
        -- 8.2 VALOR CONSTANTE
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO] SET [FL_VALOR_CONSTANTE] = 1
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO
          AND ISNULL([QTD_VALORES_DISTINTOS], 0) <= 5 AND ISNULL([QTD_REGISTROS], 0) >= 1000;
        
        -- 8.3 ZEROS SUSPEITOS
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO] SET [FL_ZEROS_SUSPEITOS] = 1
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO
          AND ISNULL([QTD_ZEROS], 0) > 360 AND ISNULL([VL_MEDIA_HISTORICA], 0) > 0.001;
        
        -- 8.4 VALOR NEGATIVO
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO]
        SET [FL_VALOR_NEGATIVO] = 1,
            [QTD_NEGATIVOS] = (SELECT COUNT(*) FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH 
                WHERE MRH.[CD_PONTO_MEDICAO] = [MEDICAO_RESUMO_DIARIO].[CD_PONTO_MEDICAO]
                  AND CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO AND ISNULL(MRH.[FL_VALOR_NEGATIVO], 0) = 1)
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO AND ISNULL([VL_MIN_DIARIO], 0) < 0;
        
        -- 8.5 FORA DA FAIXA
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO]
        SET [FL_FORA_FAIXA] = 1,
            [QTD_FORA_FAIXA] = (SELECT COUNT(*) FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH 
                WHERE MRH.[CD_PONTO_MEDICAO] = [MEDICAO_RESUMO_DIARIO].[CD_PONTO_MEDICAO]
                  AND CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO AND ISNULL(MRH.[FL_FORA_FAIXA], 0) = 1)
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO
          AND ((ISNULL([VL_LIMITE_SUPERIOR], 0) > 0 AND ISNULL([VL_MAX_DIARIO], 0) > [VL_LIMITE_SUPERIOR])
               OR (ISNULL([VL_CAPACIDADE_NOMINAL], 0) > 0 AND ISNULL([VL_MAX_DIARIO], 0) > [VL_CAPACIDADE_NOMINAL]));
        
        -- 8.6 SPIKES
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO]
        SET [FL_SPIKE] = 1,
            [QTD_SPIKES] = (SELECT COUNT(*) FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH 
                WHERE MRH.[CD_PONTO_MEDICAO] = [MEDICAO_RESUMO_DIARIO].[CD_PONTO_MEDICAO]
                  AND CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO AND ISNULL(MRH.[FL_SPIKE], 0) = 1)
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO
          AND EXISTS (SELECT 1 FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH 
                WHERE MRH.[CD_PONTO_MEDICAO] = [MEDICAO_RESUMO_DIARIO].[CD_PONTO_MEDICAO]
                  AND CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO AND ISNULL(MRH.[FL_SPIKE], 0) = 1);
        
        -- 8.7 DESVIO HISTÓRICO > 50%
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO] SET [FL_DESVIO_HISTORICO] = 1
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO AND ABS(ISNULL([VL_DESVIO_HISTORICO], 0)) > 50;
        
        -- 8.8 PERFIL ANÔMALO
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO] SET [FL_PERFIL_ANOMALO] = 1
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO
          AND ISNULL([VL_DESVIO_PADRAO], 0) < 0.1 AND ISNULL([VL_MEDIA_DIARIA], 0) > 0.001 AND ISNULL([QTD_REGISTROS], 0) >= 1000;
        
        PRINT 'Etapa 8: Flags diarias detectadas.';
        
        -- ====================================================================
        -- ETAPA 9: SCORE DE SAÚDE E CLASSIFICAÇÃO
        -- ====================================================================
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO]
        SET 
            [VL_SCORE_SAUDE] = 10
                - (CASE WHEN ISNULL([FL_SEM_COMUNICACAO], 0) = 1 THEN 3 ELSE 0 END)
                - (CASE WHEN ISNULL([FL_VALOR_CONSTANTE], 0) = 1 THEN 2 ELSE 0 END)
                - (CASE WHEN ISNULL([FL_ZEROS_SUSPEITOS], 0) = 1 THEN 1 ELSE 0 END)
                - (CASE WHEN ISNULL([FL_VALOR_NEGATIVO], 0) = 1 THEN 2 ELSE 0 END)
                - (CASE WHEN ISNULL([FL_FORA_FAIXA], 0) = 1 THEN 2 ELSE 0 END)
                - (CASE WHEN ISNULL([FL_SPIKE], 0) = 1 THEN 1 ELSE 0 END)
                - (CASE WHEN ISNULL([FL_PERFIL_ANOMALO], 0) = 1 THEN 1 ELSE 0 END)
                - (CASE WHEN ISNULL([FL_DESVIO_HISTORICO], 0) = 1 THEN 1 ELSE 0 END),
            
            [FL_ANOMALIA] = CASE 
                WHEN ISNULL([FL_SEM_COMUNICACAO], 0) = 1 OR ISNULL([FL_VALOR_CONSTANTE], 0) = 1 
                  OR ISNULL([FL_ZEROS_SUSPEITOS], 0) = 1 OR ISNULL([FL_VALOR_NEGATIVO], 0) = 1 
                  OR ISNULL([FL_FORA_FAIXA], 0) = 1 OR ISNULL([FL_SPIKE], 0) = 1
                  OR ISNULL([FL_PERFIL_ANOMALO], 0) = 1 OR ISNULL([FL_DESVIO_HISTORICO], 0) = 1
                THEN 1 ELSE 0 END,
            
            [DS_TIPO_PROBLEMA] = CASE
                WHEN ISNULL([FL_SEM_COMUNICACAO], 0) = 1 THEN 'COMUNICACAO'
                WHEN ISNULL([FL_VALOR_CONSTANTE], 0) = 1 OR ISNULL([FL_PERFIL_ANOMALO], 0) = 1 THEN 'MEDIDOR'
                WHEN ISNULL([FL_VALOR_NEGATIVO], 0) = 1 OR ISNULL([FL_FORA_FAIXA], 0) = 1 OR ISNULL([FL_SPIKE], 0) = 1 THEN 'HIDRAULICO'
                WHEN ISNULL([FL_ZEROS_SUSPEITOS], 0) = 1 OR ISNULL([FL_DESVIO_HISTORICO], 0) = 1 THEN 'VERIFICAR'
                ELSE NULL END,
            
            [DS_ANOMALIAS] = STUFF((
                SELECT DISTINCT '; ' + MRH.[DS_TIPO_ANOMALIA]
                FROM [dbo].[MEDICAO_RESUMO_HORARIO] MRH
                WHERE MRH.[CD_PONTO_MEDICAO] = [MEDICAO_RESUMO_DIARIO].[CD_PONTO_MEDICAO]
                  AND CAST(MRH.[DT_HORA] AS DATE) = @DT_PROCESSAMENTO
                  AND MRH.[DS_TIPO_ANOMALIA] IS NOT NULL AND MRH.[DS_TIPO_ANOMALIA] <> ''
                FOR XML PATH('')
            ), 1, 2, '')
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO;
        
        -- Score mínimo 0
        UPDATE [dbo].[MEDICAO_RESUMO_DIARIO] SET [VL_SCORE_SAUDE] = 0
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO AND ISNULL([VL_SCORE_SAUDE], 0) < 0;
        
        PRINT 'Etapa 9: Score de saude calculado.';
        
        COMMIT TRANSACTION;
        
        -- ====================================================================
        -- RESUMO FINAL
        -- ====================================================================
        PRINT '';
        PRINT '============================================';
        PRINT 'PROCESSAMENTO CONCLUIDO COM SUCESSO';
        PRINT '============================================';
        
        SELECT 
            'RESUMO' AS TIPO,
            COUNT(*) AS TOTAL_PONTOS,
            SUM(ISNULL([QTD_REGISTROS], 0)) AS TOTAL_MEDICOES,
            SUM(CASE WHEN ISNULL([FL_ANOMALIA], 0) = 1 THEN 1 ELSE 0 END) AS PONTOS_COM_ANOMALIA,
            SUM(CASE WHEN ISNULL([FL_SEM_COMUNICACAO], 0) = 1 THEN 1 ELSE 0 END) AS PROB_COMUNICACAO,
            SUM(CASE WHEN [DS_TIPO_PROBLEMA] = 'MEDIDOR' THEN 1 ELSE 0 END) AS PROB_MEDIDOR,
            SUM(CASE WHEN [DS_TIPO_PROBLEMA] = 'HIDRAULICO' THEN 1 ELSE 0 END) AS PROB_HIDRAULICO,
            SUM(CASE WHEN ISNULL([ID_SITUACAO], 1) = 2 THEN 1 ELSE 0 END) AS PONTOS_TRATADOS,
            AVG(ISNULL([VL_SCORE_SAUDE], 10)) AS SCORE_MEDIO,
            MIN(ISNULL([VL_SCORE_SAUDE], 10)) AS SCORE_MINIMO
        FROM [dbo].[MEDICAO_RESUMO_DIARIO]
        WHERE [DT_MEDICAO] = @DT_PROCESSAMENTO;
        
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        PRINT 'ERRO: ' + ERROR_MESSAGE();
    END CATCH
END
GO

PRINT 'SP_PROCESSAR_MEDICAO_DIARIA criada/atualizada com sucesso!';
GO

PRINT '';
PRINT '>> PARTE 4: Concluida.';
PRINT '';

-- ============================================================================
-- ============================================================================
-- PARTE 5: VIEWS PARA DASHBOARD
-- ============================================================================
-- ============================================================================

PRINT '>> PARTE 5: Criando views para dashboard...';
PRINT '';

-- ============================================================================
-- VW_DASHBOARD_RESUMO_GERAL
-- ============================================================================
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_DASHBOARD_RESUMO_GERAL')
    DROP VIEW [dbo].[VW_DASHBOARD_RESUMO_GERAL];
GO

CREATE VIEW [dbo].[VW_DASHBOARD_RESUMO_GERAL]
AS
WITH UltimaData AS (
    SELECT MAX(DT_MEDICAO) AS DT_REFERENCIA FROM [dbo].[MEDICAO_RESUMO_DIARIO]
)
SELECT 
    COUNT(DISTINCT MRD.CD_PONTO_MEDICAO) AS TOTAL_PONTOS,
    SUM(MRD.QTD_REGISTROS) AS TOTAL_MEDICOES,
    CAST(AVG(CAST(MRD.VL_SCORE_SAUDE AS DECIMAL(5,2))) AS DECIMAL(5,2)) AS SCORE_MEDIO,
    MIN(MRD.VL_SCORE_SAUDE) AS SCORE_MINIMO,
    SUM(CASE WHEN MRD.VL_SCORE_SAUDE >= 8 THEN 1 ELSE 0 END) AS PONTOS_SAUDAVEIS,
    SUM(CASE WHEN MRD.VL_SCORE_SAUDE BETWEEN 5 AND 7 THEN 1 ELSE 0 END) AS PONTOS_ALERTA,
    SUM(CASE WHEN MRD.VL_SCORE_SAUDE < 5 THEN 1 ELSE 0 END) AS PONTOS_CRITICOS,
    SUM(CASE WHEN MRD.DS_TIPO_PROBLEMA = 'COMUNICACAO' THEN 1 ELSE 0 END) AS PROB_COMUNICACAO,
    SUM(CASE WHEN MRD.DS_TIPO_PROBLEMA = 'MEDIDOR' THEN 1 ELSE 0 END) AS PROB_MEDIDOR,
    SUM(CASE WHEN MRD.DS_TIPO_PROBLEMA = 'HIDRAULICO' THEN 1 ELSE 0 END) AS PROB_HIDRAULICO,
    SUM(CASE WHEN MRD.FL_ANOMALIA = 1 THEN 1 ELSE 0 END) AS TOTAL_ANOMALIAS,
    SUM(CASE WHEN MRD.ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS PONTOS_TRATADOS,
    MIN(MRD.DT_MEDICAO) AS DATA_INICIO,
    MAX(MRD.DT_MEDICAO) AS DATA_FIM
FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
CROSS JOIN UltimaData UD
WHERE MRD.DT_MEDICAO > DATEADD(DAY, -7, UD.DT_REFERENCIA);
GO

PRINT 'VW_DASHBOARD_RESUMO_GERAL atualizada.';
GO

-- ============================================================================
-- VW_PONTOS_POR_SCORE_SAUDE
-- ============================================================================
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_PONTOS_POR_SCORE_SAUDE')
    DROP VIEW [dbo].[VW_PONTOS_POR_SCORE_SAUDE];
GO

CREATE VIEW [dbo].[VW_PONTOS_POR_SCORE_SAUDE]
AS
WITH UltimaData AS (
    SELECT MAX(DT_MEDICAO) AS DT_REFERENCIA FROM [dbo].[MEDICAO_RESUMO_DIARIO]
)
SELECT 
    MRD.CD_PONTO_MEDICAO,
    PM.DS_NOME AS NOME_PONTO,
    MRD.ID_TIPO_MEDIDOR,
    CASE MRD.ID_TIPO_MEDIDOR
        WHEN 1 THEN 'M - Macromedidor'
        WHEN 2 THEN 'E - Estacao Pitometrica'
        WHEN 4 THEN 'P - Pressao'
        WHEN 6 THEN 'R - Nivel Reservatorio'
        WHEN 8 THEN 'H - Hidrometro'
        ELSE 'Desconhecido'
    END AS TIPO_MEDIDOR,
    
    -- Score de saúde
    AVG(MRD.VL_SCORE_SAUDE) AS SCORE_MEDIO,
    MIN(MRD.VL_SCORE_SAUDE) AS SCORE_MINIMO,
    
    -- Classificação visual
    CASE 
        WHEN AVG(MRD.VL_SCORE_SAUDE) >= 8 THEN 'SAUDAVEL'
        WHEN AVG(MRD.VL_SCORE_SAUDE) >= 5 THEN 'ALERTA'
        ELSE 'CRITICO'
    END AS STATUS_SAUDE,
    
    -- Cor para dashboard
    CASE 
        WHEN AVG(MRD.VL_SCORE_SAUDE) >= 8 THEN '#22c55e'
        WHEN AVG(MRD.VL_SCORE_SAUDE) >= 5 THEN '#f59e0b'
        ELSE '#dc2626'
    END AS COR_STATUS,
    
    -- Ícone
    CASE 
        WHEN AVG(MRD.VL_SCORE_SAUDE) >= 8 THEN 'checkmark-circle'
        WHEN AVG(MRD.VL_SCORE_SAUDE) >= 5 THEN 'warning'
        ELSE 'alert-circle'
    END AS ICONE_STATUS,
    
    -- Contagens de problemas
    COUNT(*) AS DIAS_ANALISADOS,
    SUM(CASE WHEN ISNULL(MRD.FL_SEM_COMUNICACAO, 0) = 1 THEN 1 ELSE 0 END) AS DIAS_SEM_COMUNICACAO,
    SUM(CASE WHEN ISNULL(MRD.FL_VALOR_CONSTANTE, 0) = 1 THEN 1 ELSE 0 END) AS DIAS_VALOR_CONSTANTE,
    SUM(CASE WHEN ISNULL(MRD.FL_VALOR_NEGATIVO, 0) = 1 THEN 1 ELSE 0 END) AS DIAS_VALOR_NEGATIVO,
    SUM(CASE WHEN ISNULL(MRD.FL_FORA_FAIXA, 0) = 1 THEN 1 ELSE 0 END) AS DIAS_FORA_FAIXA,
    SUM(CASE WHEN ISNULL(MRD.FL_SPIKE, 0) = 1 THEN 1 ELSE 0 END) AS DIAS_COM_SPIKE,
    SUM(CASE WHEN ISNULL(MRD.FL_ZEROS_SUSPEITOS, 0) = 1 THEN 1 ELSE 0 END) AS DIAS_ZEROS_SUSPEITOS,
    SUM(CASE WHEN ISNULL(MRD.FL_ANOMALIA, 0) = 1 THEN 1 ELSE 0 END) AS DIAS_COM_ANOMALIA,
    SUM(CASE WHEN ISNULL(MRD.ID_SITUACAO, 1) = 2 THEN 1 ELSE 0 END) AS DIAS_TRATADOS,
    
    -- Estatísticas
    AVG(MRD.VL_MEDIA_DIARIA) AS MEDIA_PERIODO,
    AVG(MRD.QTD_REGISTROS) AS REGISTROS_MEDIO,
    AVG(MRD.VL_DESVIO_HISTORICO) AS DESVIO_HISTORICO_MEDIO

FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
CROSS JOIN UltimaData UD
LEFT JOIN [dbo].[PONTO_MEDICAO] PM ON MRD.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
WHERE MRD.DT_MEDICAO > DATEADD(DAY, -7, UD.DT_REFERENCIA)
GROUP BY MRD.CD_PONTO_MEDICAO, PM.DS_NOME, MRD.ID_TIPO_MEDIDOR;
GO

PRINT 'VW_PONTOS_POR_SCORE_SAUDE atualizada.';
GO

-- ============================================================================
-- VW_ANOMALIAS_RECENTES
-- ============================================================================
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_ANOMALIAS_RECENTES')
    DROP VIEW [dbo].[VW_ANOMALIAS_RECENTES];
GO

CREATE VIEW [dbo].[VW_ANOMALIAS_RECENTES]
AS
WITH UltimaData AS (
    SELECT MAX(DT_MEDICAO) AS DT_REFERENCIA FROM [dbo].[MEDICAO_RESUMO_DIARIO]
)
SELECT 
    MRD.CD_PONTO_MEDICAO,
    PM.DS_NOME AS NOME_PONTO,
    MRD.DT_MEDICAO,
    MRD.ID_TIPO_MEDIDOR,
    MRD.DS_TIPO_PROBLEMA,
    MRD.DS_ANOMALIAS,
    MRD.VL_SCORE_SAUDE,
    MRD.VL_MEDIA_DIARIA,
    MRD.VL_DESVIO_HISTORICO,
    MRD.ID_SITUACAO,
    CASE WHEN MRD.ID_SITUACAO = 2 THEN 'Tratado' ELSE 'Pendente' END AS STATUS_TRATAMENTO
FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
CROSS JOIN UltimaData UD
LEFT JOIN [dbo].[PONTO_MEDICAO] PM ON MRD.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
WHERE MRD.FL_ANOMALIA = 1
  AND MRD.DT_MEDICAO > DATEADD(DAY, -7, UD.DT_REFERENCIA);
GO

PRINT 'VW_ANOMALIAS_RECENTES atualizada.';
GO

-- ============================================================================
-- VW_EVOLUCAO_DIARIA
-- ============================================================================
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_EVOLUCAO_DIARIA')
    DROP VIEW [dbo].[VW_EVOLUCAO_DIARIA];
GO

CREATE VIEW [dbo].[VW_EVOLUCAO_DIARIA]
AS
WITH UltimaData AS (
    SELECT MAX(DT_MEDICAO) AS DT_REFERENCIA FROM [dbo].[MEDICAO_RESUMO_DIARIO]
)
SELECT 
    MRD.DT_MEDICAO,
    COUNT(*) AS TOTAL_PONTOS,
    CAST(AVG(CAST(MRD.VL_SCORE_SAUDE AS DECIMAL(5,2))) AS DECIMAL(5,2)) AS SCORE_MEDIO,
    SUM(CASE WHEN MRD.VL_SCORE_SAUDE >= 8 THEN 1 ELSE 0 END) AS QTD_SAUDAVEIS,
    SUM(CASE WHEN MRD.VL_SCORE_SAUDE BETWEEN 5 AND 7 THEN 1 ELSE 0 END) AS QTD_ALERTA,
    SUM(CASE WHEN MRD.VL_SCORE_SAUDE < 5 THEN 1 ELSE 0 END) AS QTD_CRITICOS,
    SUM(CASE WHEN MRD.FL_ANOMALIA = 1 THEN 1 ELSE 0 END) AS TOTAL_ANOMALIAS,
    SUM(MRD.QTD_TRATAMENTOS) AS TOTAL_TRATAMENTOS
FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
CROSS JOIN UltimaData UD
WHERE MRD.DT_MEDICAO > DATEADD(DAY, -30, UD.DT_REFERENCIA)
GROUP BY MRD.DT_MEDICAO;
GO

PRINT 'VW_EVOLUCAO_DIARIA atualizada.';
GO

-- ============================================================================
-- ============================================================================
-- PARTE 6: SP DE CONTEXTO PARA IA
-- ============================================================================
-- ============================================================================

PRINT '>> PARTE 6: Criando SP de contexto para IA...';
PRINT '';

IF EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[SP_CONTEXTO_IA]') AND type in (N'P'))
    DROP PROCEDURE [dbo].[SP_CONTEXTO_IA];
GO

CREATE PROCEDURE [dbo].[SP_CONTEXTO_IA]
    @DIAS_ANALISE INT = 7
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @DT_INICIO DATE = DATEADD(DAY, -@DIAS_ANALISE, CAST(GETDATE() AS DATE));
    DECLARE @CONTEXTO NVARCHAR(MAX) = '';
    DECLARE @TOTAL_PONTOS INT, @SCORE_MEDIO DECIMAL(5,2), @PROB_COM INT, @PROB_MED INT, @PROB_HID INT, @TOTAL_ANOM INT;
    
    SELECT 
        @TOTAL_PONTOS = COUNT(DISTINCT CD_PONTO_MEDICAO),
        @SCORE_MEDIO = AVG(CAST(VL_SCORE_SAUDE AS DECIMAL(5,2))),
        @PROB_COM = SUM(CASE WHEN DS_TIPO_PROBLEMA = 'COMUNICACAO' THEN 1 ELSE 0 END),
        @PROB_MED = SUM(CASE WHEN DS_TIPO_PROBLEMA = 'MEDIDOR' THEN 1 ELSE 0 END),
        @PROB_HID = SUM(CASE WHEN DS_TIPO_PROBLEMA = 'HIDRAULICO' THEN 1 ELSE 0 END),
        @TOTAL_ANOM = SUM(CASE WHEN FL_ANOMALIA = 1 THEN 1 ELSE 0 END)
    FROM [dbo].[MEDICAO_RESUMO_DIARIO]
    WHERE DT_MEDICAO >= @DT_INICIO;
    
    SET @CONTEXTO = '
=== CONTEXTO PARA IA - SIMP ===
Periodo: Ultimos ' + CAST(@DIAS_ANALISE AS VARCHAR) + ' dias

>>> RESUMO <<<
Pontos: ' + CAST(ISNULL(@TOTAL_PONTOS, 0) AS VARCHAR) + '
Score medio: ' + CAST(ISNULL(@SCORE_MEDIO, 0) AS VARCHAR) + '/10
Anomalias: ' + CAST(ISNULL(@TOTAL_ANOM, 0) AS VARCHAR) + '

>>> PROBLEMAS <<<
COMUNICACAO: ' + CAST(ISNULL(@PROB_COM, 0) AS VARCHAR) + '
MEDIDOR: ' + CAST(ISNULL(@PROB_MED, 0) AS VARCHAR) + '
HIDRAULICO: ' + CAST(ISNULL(@PROB_HID, 0) AS VARCHAR) + '

>>> PONTOS CRITICOS (Score < 5) <<<
';

    SELECT @CONTEXTO = @CONTEXTO + 
        '- ' + ISNULL(PM.DS_NOME, 'Ponto ' + CAST(MRD.CD_PONTO_MEDICAO AS VARCHAR)) + 
        ' | Score: ' + CAST(MRD.VL_SCORE_SAUDE AS VARCHAR) + 
        ' | ' + ISNULL(MRD.DS_TIPO_PROBLEMA, '') + CHAR(13) + CHAR(10)
    FROM [dbo].[MEDICAO_RESUMO_DIARIO] MRD
    LEFT JOIN [dbo].[PONTO_MEDICAO] PM ON MRD.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
    WHERE MRD.DT_MEDICAO >= @DT_INICIO AND MRD.VL_SCORE_SAUDE < 5
    ORDER BY MRD.VL_SCORE_SAUDE ASC;
    
    SELECT @CONTEXTO AS CONTEXTO_IA;
END
GO

PRINT '   Stored Procedure SP_CONTEXTO_IA criada.';
GO

PRINT '';
PRINT '>> PARTE 6: Concluida.';
PRINT '';

-- ============================================================================
-- ============================================================================
-- FINALIZAÇÃO
-- ============================================================================
-- ============================================================================

PRINT '============================================';
PRINT 'INSTALACAO CONCLUIDA COM SUCESSO!';
PRINT '============================================';
PRINT '';
PRINT 'Objetos criados:';
PRINT '  - MEDICAO_RESUMO_HORARIO (tabela)';
PRINT '  - MEDICAO_RESUMO_DIARIO (tabela)';
PRINT '  - LIMITES_PADRAO_TIPO_MEDIDOR (tabela)';
PRINT '  - VW_PONTO_MEDICAO_LIMITES (view)';
PRINT '  - VW_PONTOS_SEM_CADASTRO (view)';
PRINT '  - VW_DASHBOARD_RESUMO_GERAL (view)';
PRINT '  - VW_PONTOS_POR_SCORE_SAUDE (view)';
PRINT '  - VW_ANOMALIAS_RECENTES (view)';
PRINT '  - VW_EVOLUCAO_DIARIA (view)';
PRINT '  - SP_PROCESSAR_MEDICAO_DIARIA (procedure)';
PRINT '  - SP_CONTEXTO_IA (procedure)';
PRINT '';
PRINT 'Para executar o processamento:';
PRINT '  EXEC SP_PROCESSAR_MEDICAO_DIARIA;  -- Processa D-1';
PRINT '  EXEC SP_PROCESSAR_MEDICAO_DIARIA @DT_PROCESSAMENTO = ''2025-01-14'';';
PRINT '';
PRINT 'Para verificar cadastros pendentes:';
PRINT '  SELECT * FROM VW_PONTOS_SEM_CADASTRO;';
PRINT '';
PRINT '============================================';
GO


-- COMO CARREGAR OS DADOS =========================================================
-- Processar de 01/01/2025 até 15/01/2025
-- DECLARE @DATA DATE = '2025-01-01';
-- DECLARE @DATA_FIM DATE = '2025-01-15';

-- WHILE @DATA <= @DATA_FIM
-- BEGIN
--     PRINT 'Processando: ' + CONVERT(VARCHAR, @DATA, 103);
--     EXEC SP_PROCESSAR_MEDICAO_DIARIA @DT_PROCESSAMENTO = @DATA;
--     SET @DATA = DATEADD(DAY, 1, @DATA);
-- END



-- REGISTRO_VAZAO_PRESSAO (origem - ~1440 reg/ponto/dia)
--          │
--          ▼
-- ┌─────────────────────────────────────────────────┐
-- │        SP_PROCESSAR_MEDICAO_DIARIA              │
-- │                                                 │
-- │  Etapa 1-4: Agrupa por hora                     │
-- │         │                                       │
-- │         ▼                                       │
-- │  ┌─────────────────────────────────┐            │
-- │  │   MEDICAO_RESUMO_HORARIO        │            │
-- │  │   (24 registros por ponto/dia)  │            │
-- │  └─────────────────────────────────┘            │
-- │         │                                       │
-- │  Etapa 5-9: Consolida o dia                     │
-- │         │                                       │
-- │         ▼                                       │
-- │  ┌─────────────────────────────────┐            │
-- │  │   MEDICAO_RESUMO_DIARIO         │            │
-- │  │   (1 registro por ponto/dia)    │            │
-- │  └─────────────────────────────────┘            │
-- └─────────────────────────────────────────────────┘

-- PRINT '  - SP_CONTEXTO_IA (procedure)';
-- ┌─────────────────────────────────────────────────────────────┐
-- │  DIARIAMENTE (job agendado, ex: 6h da manhã)                │
-- │                                                             │
-- │  EXEC SP_PROCESSAR_MEDICAO_DIARIA;  -- Processa D-1         │
-- └─────────────────────────────────────────────────────────────┘
--                           │
--                           ▼
--               Tabelas RESUMO alimentadas
--                           │
--                           ▼
-- ┌─────────────────────────────────────────────────────────────┐
-- │  SOB DEMANDA (quando usuário clica "Analisar com IA")       │
-- │                                                             │
-- │  EXEC SP_CONTEXTO_IA @DIAS_ANALISE = 7;                     │
-- │                                                             │
-- │  Retorna texto formatado → Envia para DeepSeek → Resposta   │
-- └─────────────────────────────────────────────────────────────┘



-- QUERYES ÚTEIS PARA DASHBOARD E IA ============================
-- Mostrar período dos dados
SELECT 
    'PERIODO DOS DADOS' AS INFO,
    MIN(DT_MEDICAO) AS PRIMEIRA_DATA,
    MAX(DT_MEDICAO) AS ULTIMA_DATA,
    DATEDIFF(DAY, MIN(DT_MEDICAO), MAX(DT_MEDICAO)) + 1 AS DIAS_PROCESSADOS
FROM [dbo].[MEDICAO_RESUMO_DIARIO];

-- Testar views
SELECT 'VW_DASHBOARD_RESUMO_GERAL' AS VIEW_NAME, * FROM VW_DASHBOARD_RESUMO_GERAL;
GO

-- Pontos críticos (score < 5)
SELECT * FROM VW_PONTOS_POR_SCORE_SAUDE 
WHERE SCORE_MEDIO < 5 
ORDER BY SCORE_MEDIO;

-- Pontos saudáveis
SELECT * FROM VW_PONTOS_POR_SCORE_SAUDE 
WHERE STATUS_SAUDE = 'SAUDAVEL';

-- Anomalias pendentes (não tratadas)
SELECT * FROM VW_ANOMALIAS_RECENTES 
WHERE STATUS_TRATAMENTO = 'Pendente';

-- Evolução do score ao longo do tempo
SELECT * FROM VW_EVOLUCAO_DIARIA ORDER BY DT_MEDICAO;

-- Detalhe horário de um ponto específico
SELECT * FROM MEDICAO_RESUMO_HORARIO 
WHERE CD_PONTO_MEDICAO = 123 
  AND CAST(DT_HORA AS DATE) = '2025-01-14'
ORDER BY NR_HORA;

-- Contexto para IA
EXEC SP_CONTEXTO_IA @DIAS_ANALISE = 7;
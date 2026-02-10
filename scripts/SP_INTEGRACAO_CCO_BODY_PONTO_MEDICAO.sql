USE [SIMP]
GO
/****** Object:  StoredProcedure [dbo].[SP_INTEGRACAO_CCO_BODY_PONTO_MEDICAO]    Script Date: 09/02/2026 11:01:22 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO

ALTER PROCEDURE [dbo].[SP_INTEGRACAO_CCO_BODY_PONTO_MEDICAO]
(
    @id_tipo_leitura        tinyint,
    @cd_usuario             bigint,
    @cd_funcionalidade      int,
    @ds_versao              varchar(25),
    @sp_msg_erro            varchar(4000) output,
    @now                    datetime = null,
    @p_cd_ponto_medicao     int
)
AS
SET NOCOUNT ON;

-- Declara constantes
DECLARE @log_erro               tinyint = 1,
        @log_alerta             tinyint = 2,
        @log_aviso              tinyint = 3,
        @ID_TIPO_MEDICAO        tinyint = 1;

-- Declara variáveis
DECLARE @today                  datetime,
        @DT_EVENTO_MEDICAO      datetime;
    
-- Inicializa variáveis
SET @now = ISNULL(@now, GETDATE());
SET @today = @now;  -- <<< ALTERADO: agora inclui até o momento atual
SET @DT_EVENTO_MEDICAO = CONVERT(varchar, @now, 20);

BEGIN TRY
    -- =====================================================
    -- ETAPA 1: Criar tabela de pontos de medição
    -- =====================================================
    IF OBJECT_ID('TempDB..#ponto_medicao') IS NOT NULL DROP TABLE #ponto_medicao;
    
    CREATE TABLE #ponto_medicao
    (
        CD_PONTO_MEDICAO            int             NOT NULL,
        DS_NOME                     varchar(50)     NOT NULL,
        OP_PERIODICIDADE_LEITURA    tinyint         NOT NULL,
        DT_INICIAL                  datetime        NOT NULL,
        DT_FINAL                    datetime        NOT NULL,
        QT_CYCLES                   int             NOT NULL,
        CD_USUARIO_RESPONSAVEL      bigint          NOT NULL,
        CD_TAG                      tinyint         NOT NULL,
        DS_TAG                      varchar(25)     NOT NULL,
        CD_LOCALIDADE               int             NOT NULL,
        CD_UNIDADE                  int             NOT NULL,
        INDEX IX_PONTO_TAG CLUSTERED (CD_PONTO_MEDICAO, CD_TAG)
    );

    ;WITH PontoMedicao AS (
        SELECT  
            p.CD_PONTO_MEDICAO,
            p.DS_NOME,
            p.OP_PERIODICIDADE_LEITURA,
            CASE WHEN ISNULL(maxLeitura.DT_LEITURA, '19000101') > p.DT_ATIVACAO 
                 THEN maxLeitura.DT_LEITURA
                 ELSE p.DT_ATIVACAO
            END AS DT_INICIAL,
            @today AS DT_FINAL,
            p.CD_USUARIO_RESPONSAVEL,
            tag.CD_TAG,
            CASE tag.CD_TAG 
                WHEN 1 THEN p.DS_TAG_VAZAO
                WHEN 2 THEN p.DS_TAG_PRESSAO
                WHEN 3 THEN p.DS_TAG_TEMP_AGUA
                WHEN 4 THEN p.DS_TAG_TEMP_AMBIENTE
                WHEN 5 THEN p.DS_TAG_VOLUME
                WHEN 6 THEN p.DS_TAG_RESERVATORIO
            END AS DS_TAG,
            p.CD_LOCALIDADE,
            l.CD_UNIDADE
        FROM PONTO_MEDICAO p
        INNER JOIN LOCALIDADE l ON l.CD_CHAVE = p.CD_LOCALIDADE
        LEFT JOIN (
    SELECT CD_PONTO_MEDICAO, MAX(DT_LEITURA) AS DT_LEITURA
    FROM REGISTRO_VAZAO_PRESSAO
    WHERE ID_SITUACAO = 1
    GROUP BY CD_PONTO_MEDICAO
) maxLeitura ON p.CD_PONTO_MEDICAO = maxLeitura.CD_PONTO_MEDICAO
        CROSS APPLY (
            SELECT 1 AS CD_TAG WHERE p.DS_TAG_VAZAO IS NOT NULL UNION ALL
            SELECT 2 WHERE p.DS_TAG_PRESSAO IS NOT NULL UNION ALL
            SELECT 3 WHERE p.DS_TAG_TEMP_AGUA IS NOT NULL UNION ALL
            SELECT 4 WHERE p.DS_TAG_TEMP_AMBIENTE IS NOT NULL UNION ALL
            SELECT 5 WHERE p.DS_TAG_VOLUME IS NOT NULL UNION ALL
            SELECT 6 WHERE p.DS_TAG_RESERVATORIO IS NOT NULL
        ) tag
        WHERE 
            p.DT_ATIVACAO IS NOT NULL 
            AND p.DT_ATIVACAO <= @now
            AND (p.DT_DESATIVACAO IS NULL OR p.DT_DESATIVACAO > @now)
            AND p.ID_TIPO_LEITURA = @id_tipo_leitura
            AND p.CD_PONTO_MEDICAO = @p_cd_ponto_medicao
    )
    INSERT INTO #ponto_medicao
    SELECT 
        CD_PONTO_MEDICAO,
        DS_NOME,
        OP_PERIODICIDADE_LEITURA,
        -- Ajuste da data inicial conforme periodicidade
        CASE OP_PERIODICIDADE_LEITURA
            WHEN 1 THEN DATEADD(ss, IIF(CD_TAG <> 5, 1, 0), DATEADD(mi, DATEDIFF(mi, 0, DT_INICIAL), 0))
            WHEN 2 THEN DATEADD(mi, IIF(CD_TAG <> 5, 1, 0), DATEADD(hh, DATEDIFF(hh, 0, DT_INICIAL), 0))
            WHEN 3 THEN DATEADD(hh, IIF(CD_TAG <> 5, 1, 0), DATEADD(dd, DATEDIFF(dd, 0, DT_INICIAL), 0))
            WHEN 4 THEN DATEADD(dd, IIF(CD_TAG <> 5, 1, 0), DATEADD(mm, DATEDIFF(mm, 0, DT_INICIAL), 0))
        END AS DT_INICIAL,
        DT_FINAL,
        -- Cálculo de ciclos
        CASE OP_PERIODICIDADE_LEITURA
            WHEN 1 THEN DATEDIFF(ss, DT_INICIAL, DT_FINAL) + 1
            WHEN 2 THEN DATEDIFF(mi, DT_INICIAL, DT_FINAL) + 1
            WHEN 3 THEN DATEDIFF(hh, DT_INICIAL, DT_FINAL) + 1
            WHEN 4 THEN DATEDIFF(dd, DT_INICIAL, DT_FINAL) + 1
        END AS QT_CYCLES,
        CD_USUARIO_RESPONSAVEL,
        CD_TAG,
        DS_TAG,
        CD_LOCALIDADE,
        CD_UNIDADE
    FROM PontoMedicao
    WHERE DT_FINAL > DT_INICIAL;

    -- =====================================================
    -- ETAPA 2: Buscar dados do historiador
    -- =====================================================
    -- Criar tabela SEM índice (será criado após popular)
    IF OBJECT_ID('TempDB..#registro_vazao_pressao') IS NOT NULL DROP TABLE #registro_vazao_pressao;
    
    CREATE TABLE #registro_vazao_pressao
    (
        CD_PONTO_MEDICAO    int             NOT NULL,
        CD_TAG              tinyint         NOT NULL,
        DS_TAG              varchar(25)     NOT NULL,
        DT_LEITURA          datetime        NOT NULL,
        vvalue              numeric(25,16)  NULL
    );

    DECLARE @cdPontoMedicao INT,
            @cdTag          TINYINT,
            @dsTag          VARCHAR(25),
            @qtCycles       INT,
            @dtInicial      DATETIME,
            @dtFinal        DATETIME;

    -- Cursor otimizado com FAST_FORWARD
    DECLARE cs_ponto CURSOR LOCAL FAST_FORWARD FOR
        SELECT CD_PONTO_MEDICAO, CD_TAG, DS_TAG, QT_CYCLES, DT_INICIAL, DT_FINAL
        FROM #ponto_medicao
        ORDER BY OP_PERIODICIDADE_LEITURA, CD_TAG;

    OPEN cs_ponto;
    FETCH NEXT FROM cs_ponto INTO @cdPontoMedicao, @cdTag, @dsTag, @qtCycles, @dtInicial, @dtFinal;
    
    WHILE @@FETCH_STATUS = 0
    BEGIN    
        IF @cdTag = 5 -- TAG de volume (cálculo diferencial)
        BEGIN
            PRINT 'Buscando dados de VOLUME do historiador - TAG: ' + @dsTag;
            
            ;WITH RegistroVolume AS (
                SELECT
                    h.datetime,
					TRY_CAST(h.vvalue AS NUMERIC(25,16)) as vvalue,
                    ROW_NUMBER() OVER (ORDER BY h.datetime) as RowNum
                FROM [HISTORIADOR_CCO].[Runtime].[dbo].HISTORY h
                WHERE h.datetime >= @dtInicial
                    AND h.datetime <= @dtFinal
                    AND h.TagName = @dsTag
                    AND h.wwCycleCount = @qtCycles
                    AND h.wwRetrievalMode = 'Cyclic'
					AND h.vvalue IS NOT NULL 
					AND ISNUMERIC(h.vvalue) = 1
                    AND h.wwVersion = 'Latest'
            )
            INSERT INTO #registro_vazao_pressao (CD_PONTO_MEDICAO, CD_TAG, DS_TAG, DT_LEITURA, vvalue)
            SELECT
                @cdPontoMedicao,
                @cdTag,
                @dsTag,
                atual.datetime,
                atual.vvalue - anterior.vvalue
            FROM RegistroVolume atual
            INNER JOIN RegistroVolume anterior ON atual.RowNum = anterior.RowNum + 1
            WHERE (atual.vvalue - anterior.vvalue) IS NOT NULL;
        END
        ELSE
        BEGIN
            PRINT 'Buscando dados do historiador - TAG: ' + @dsTag + ' (CD_TAG: ' + CAST(@cdTag AS VARCHAR) + ')';
            
            INSERT INTO #registro_vazao_pressao (CD_PONTO_MEDICAO, CD_TAG, DS_TAG, DT_LEITURA, vvalue)
            SELECT
                @cdPontoMedicao,
                @cdTag,
                @dsTag,
                h.datetime,
				TRY_CAST(h.vvalue AS NUMERIC(25,16))		
				FROM [HISTORIADOR_CCO].[Runtime].[dbo].HISTORY h
            WHERE h.datetime >= @dtInicial
                AND h.datetime <= @dtFinal
                AND h.TagName = @dsTag
                AND h.wwCycleCount = @qtCycles
                AND h.wwRetrievalMode = 'Cyclic'
                AND h.wwVersion = 'Latest'
				AND ISNUMERIC(h.vvalue) = 1
                AND h.vvalue IS NOT NULL;
        END

        PRINT '  -> Registros inseridos: ' + CAST(@@ROWCOUNT AS VARCHAR);

        FETCH NEXT FROM cs_ponto INTO @cdPontoMedicao, @cdTag, @dsTag, @qtCycles, @dtInicial, @dtFinal;
    END

    CLOSE cs_ponto;
    DEALLOCATE cs_ponto;

    -- Criar índice APÓS popular (muito mais eficiente)
    CREATE CLUSTERED INDEX IX_TEMP_PM_LEITURA ON #registro_vazao_pressao (CD_PONTO_MEDICAO, DT_LEITURA);

    -- =====================================================
    -- ETAPA 3: Remover duplicidades em lote (fora do cursor!)
    -- =====================================================
    PRINT 'Removendo registros duplicados...';
    
    DELETE r
    FROM #registro_vazao_pressao r
    WHERE EXISTS (
        SELECT 1 
        FROM REGISTRO_VAZAO_PRESSAO rvp
        WHERE rvp.CD_PONTO_MEDICAO = r.CD_PONTO_MEDICAO 
            AND rvp.DT_LEITURA = r.DT_LEITURA 
            AND rvp.ID_SITUACAO = 1
    );

    PRINT '  -> Duplicados removidos: ' + CAST(@@ROWCOUNT AS VARCHAR);

    -- =====================================================
    -- ETAPA 4: Inserção final (dentro de transação)
    -- =====================================================
    PRINT 'Inserindo dados na tabela REGISTRO_VAZAO_PRESSAO...';
    
    BEGIN TRANSACTION;
    
    INSERT INTO REGISTRO_VAZAO_PRESSAO
    (
        CD_PONTO_MEDICAO, DT_LEITURA, ID_TIPO_REGISTRO, ID_TIPO_MEDICAO,        
        CD_USUARIO_RESPONSAVEL, CD_USUARIO_ULTIMA_ATUALIZACAO, DT_ULTIMA_ATUALIZACAO,
        DT_EVENTO_MEDICAO, VL_VAZAO, VL_PRESSAO, VL_TEMP_AGUA, VL_TEMP_AMBIENTE,
        ID_SITUACAO, ID_TIPO_VAZAO, VL_VOLUME, VL_PERIODO_MEDICAO_VOLUME,
        VL_VAZAO_EFETIVA, VL_RESERVATORIO, NR_EXTRAVASOU, NR_MOTIVO
    )
    SELECT
        base.CD_PONTO_MEDICAO,
        base.DT_LEITURA,
        @id_tipo_leitura,
        @ID_TIPO_MEDICAO,
        ponto.CD_USUARIO_RESPONSAVEL,
        @cd_usuario,
        @now,
        @DT_EVENTO_MEDICAO,
        MAX(CASE WHEN r.CD_TAG = 1 THEN r.vvalue END), -- VL_VAZAO
        MAX(CASE WHEN r.CD_TAG = 2 THEN r.vvalue END), -- VL_PRESSAO
        MAX(CASE WHEN r.CD_TAG = 3 THEN r.vvalue END), -- VL_TEMP_AGUA
        MAX(CASE WHEN r.CD_TAG = 4 THEN r.vvalue END), -- VL_TEMP_AMBIENTE
        1, -- ID_SITUACAO (Ativo)
        2, -- ID_TIPO_VAZAO (Macromedido)
        MAX(CASE WHEN r.CD_TAG = 5 THEN r.vvalue END), -- VL_VOLUME
        CASE WHEN MAX(CASE WHEN r.CD_TAG = 5 THEN r.vvalue END) IS NOT NULL 
             THEN CASE ponto.OP_PERIODICIDADE_LEITURA 
                    WHEN 1 THEN 1 
                    WHEN 2 THEN 60 
                    WHEN 3 THEN 3600 
                    WHEN 4 THEN 86400 
                  END
        END,
        MAX(CASE WHEN r.CD_TAG = 1 THEN r.vvalue END), -- VL_VAZAO_EFETIVA
        MAX(CASE WHEN r.CD_TAG = 6 THEN r.vvalue END), -- VL_RESERVATORIO
        CASE WHEN MAX(CASE WHEN r.CD_TAG = 6 THEN r.vvalue END) >= 100 THEN 1 END,
        CASE WHEN MAX(CASE WHEN r.CD_TAG = 6 THEN r.vvalue END) >= 100 THEN 1 END
    FROM #registro_vazao_pressao r
    INNER JOIN (
        SELECT DISTINCT CD_PONTO_MEDICAO, DT_LEITURA 
        FROM #registro_vazao_pressao
    ) base ON r.CD_PONTO_MEDICAO = base.CD_PONTO_MEDICAO AND r.DT_LEITURA = base.DT_LEITURA
    INNER JOIN (
        SELECT DISTINCT CD_PONTO_MEDICAO, CD_USUARIO_RESPONSAVEL, OP_PERIODICIDADE_LEITURA 
        FROM #ponto_medicao
    ) ponto ON base.CD_PONTO_MEDICAO = ponto.CD_PONTO_MEDICAO
    GROUP BY 
        base.CD_PONTO_MEDICAO, 
        base.DT_LEITURA, 
        ponto.CD_USUARIO_RESPONSAVEL, 
        ponto.OP_PERIODICIDADE_LEITURA
    HAVING 
        MAX(CASE WHEN r.CD_TAG = 1 THEN r.vvalue END) IS NOT NULL OR
        MAX(CASE WHEN r.CD_TAG = 2 THEN r.vvalue END) IS NOT NULL OR
        MAX(CASE WHEN r.CD_TAG = 3 THEN r.vvalue END) IS NOT NULL OR
        MAX(CASE WHEN r.CD_TAG = 4 THEN r.vvalue END) IS NOT NULL OR
        MAX(CASE WHEN r.CD_TAG = 5 THEN r.vvalue END) IS NOT NULL OR
        MAX(CASE WHEN r.CD_TAG = 6 THEN r.vvalue END) IS NOT NULL;

    PRINT '  -> Registros inseridos: ' + CAST(@@ROWCOUNT AS VARCHAR);

    -- =====================================================
    -- ETAPA 5: Log de pontos sem registros
    -- =====================================================
    PRINT 'Inserindo LOG...';
    
    INSERT INTO LOG (CD_USUARIO, CD_FUNCIONALIDADE, CD_UNIDADE, DT_LOG, TP_LOG, 
                     NM_LOG, DS_LOG, DS_VERSAO, NM_SERVIDOR)
    SELECT  
        @cd_usuario,
        @cd_funcionalidade,
        p.CD_UNIDADE,
        @now,
        @log_alerta,
        'Job de Integração do CCO',
        'Importação CCO do Ponto de Medição ' + CAST(p.CD_PONTO_MEDICAO AS VARCHAR) 
            + '-' + RTRIM(p.DS_NOME) + ' e da TAG: ' + RTRIM(p.DS_TAG) + ', sem registros p/ importar.',
        @ds_versao, 
        CAST(SERVERPROPERTY('MachineName') AS VARCHAR)
    FROM #ponto_medicao p
    WHERE NOT EXISTS (
        SELECT 1 FROM #registro_vazao_pressao r 
        WHERE r.CD_PONTO_MEDICAO = p.CD_PONTO_MEDICAO AND r.DS_TAG = p.DS_TAG
    );

    COMMIT TRANSACTION;
    
    PRINT 'Processo concluído com sucesso!';
    RETURN 0;

END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0
        ROLLBACK TRANSACTION;

    SET @sp_msg_erro = CAST(ERROR_NUMBER() AS VARCHAR) + ' - ' + ERROR_MESSAGE();
    PRINT 'ERRO: ' + @sp_msg_erro;
    RETURN -1;
END CATCH




-- Como Executar ==============================================================================
DECLARE @msg VARCHAR(4000);

EXEC SP_INTEGRACAO_CCO_BODY_PONTO_MEDICAO 
    @id_tipo_leitura = 8,           -- 1 = CCO
    @cd_usuario = 100,              -- Seu código de usuário
    @cd_funcionalidade = 1,         -- Código da funcionalidade
    @ds_versao = '1.0',
    @sp_msg_erro = @msg OUTPUT,
    @now = NULL,                    -- NULL = usa GETDATE()
    @p_cd_ponto_medicao = 123;      -- <<< Código do ponto de medição

-- Verificar resultado
IF @msg IS NOT NULL
    PRINT 'Erro: ' + @msg;
GO

-- Executar para uma data específica ==============================================================================
DECLARE @msg VARCHAR(4000);

EXEC SP_INTEGRACAO_CCO_BODY_PONTO_MEDICAO 
    @id_tipo_leitura = 8,
    @cd_usuario = 100,
    @cd_funcionalidade = 1,
    @ds_versao = '1.0',
    @sp_msg_erro = @msg OUTPUT,
    @now = '2025-12-05 23:59:59',   -- Data específica
    @p_cd_ponto_medicao = 1022;

SELECT @msg AS Erro;

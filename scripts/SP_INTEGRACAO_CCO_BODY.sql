USE [SIMP]
GO
/****** Object:  StoredProcedure [dbo].[SP_INTEGRACAO_CCO_BODY]    Script Date: 03/02/2026 12:27:44 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO

ALTER PROCEDURE [dbo].[SP_INTEGRACAO_CCO_BODY]
(
	@id_tipo_leitura		tinyint,
	@cd_usuario             bigint,
	@cd_funcionalidade		int,
	@ds_versao              varchar(20),
	@sp_msg_erro  			varchar(4000) output,
	@now					datetime = null
)
AS	
-- Declara constantes
DECLARE @log_erro							tinyint = 1,
		@log_alerta							tinyint = 2,
		@log_aviso							tinyint = 3,
		@ID_TIPO_MEDICAO					tinyint = 1;

-- Declara variaveis
DECLARE @rtn								int,
		@REGISTROS							bigint,
		@CD_PONTO_MEDICAO					int,
		@CD_PONTO_MEDICAO_AUX				int,
		@DT_LEITURA							datetime,
		@DT_LEITURA_AUX						datetime,
		@VVALUE								float,
		@VVALUE_AUX							float,
		@DS_TAG								varchar(25),
		@CD_TAG								tinyint,
		@VL_VAZAO							numeric (25,16),
		@VL_PRESSAO							float,
		@VL_TEMP_AGUA						float,
		@VL_TEMP_AMBIENTE					float,
		@CD_USUARIO_RESPONSAVEL				bigint,
		@CD_USUARIO_RESPONSAVEL_AUX			bigint,
		@DS_NOME							char(50),
		@DS_NOME_AUX						char(50),
		@DS_TAG_VAZAO_LOG					char(25),
		@DS_TAG_PRESSAO_LOG					char(25),
		@DS_TAG_TEMP_AGUA_LOG				char(25),
		@DS_TAG_TEMP_AMBIENTE_LOG			char(25),
		@DS_TAG_VOLUME_LOG     				char(25),
		@CD_UNIDADE							int,
		@CD_UNIDADE_AUX						int,
		@MENSAGEM_FORMATADA					varchar(4000),
		@DT_EVENTO_MEDICAO                  datetime,
		@VL_VOLUME                          float,
		@VL_PERIODO_MEDICAO_VOLUME          float,
		@OP_PERIODICIDADE_LEITURA           tinyint,
		@BI_CONTINUA                        int,
		@today								datetime;
    
-- Inicializa variáveis
SET @now = ISNULL(@now, GETDATE());
SET @today = DATEADD(mi,-1, DATEDIFF(DD,0,@now));
--SET @today = getdate();
SET @DT_EVENTO_MEDICAO = convert(varchar,@now,20)


-- Inicio do processo
BEGIN TRY
	if OBJECT_ID('TempDB..#ponto_medicao') IS NOT NULL DROP TABLE #ponto_medicao;
	CREATE TABLE #ponto_medicao
	(
		CD_PONTO_MEDICAO				int			            NOT NULL,
		DS_NOME							varchar(50)	            NOT NULL,
		OP_PERIODICIDADE_LEITURA		tinyint		            NOT NULL,
		DT_INICIAL						datetime	            NOT NULL,
		DT_FINAL						datetime	            NOT NULL,
		QT_CYCLES						int		                NOT NULL,
		CD_USUARIO_RESPONSAVEL			bigint		            NOT NULL,
		CD_TAG							tinyint		            NOT NULL,
		DS_TAG							varchar(25)	            NOT NULL,
		CD_LOCALIDADE					int			            NOT NULL,
		CD_UNIDADE  					int			            NOT NULL
	);

	WITH PontoMedicao AS (
		SELECT  
			p.CD_PONTO_MEDICAO,
			p.DS_NOME,
			p.OP_PERIODICIDADE_LEITURA,
			CASE WHEN ISNULL(maxLeitura.DT_LEITURA, CAST(0x00 AS DATETIME)) > p.DT_ATIVACAO THEN ISNULL(maxLeitura.DT_LEITURA, CAST(0x00 AS DATETIME))
				 ELSE p.DT_ATIVACAO
			END AS DT_INICIAL,
			@today AS DT_FINAL,
			p.CD_USUARIO_RESPONSAVEL,
			CD_TAG,
			CASE CD_TAG WHEN 1 THEN DS_TAG_VAZAO
						WHEN 2 THEN DS_TAG_PRESSAO
						WHEN 3 THEN DS_TAG_TEMP_AGUA
						WHEN 4 THEN DS_TAG_TEMP_AMBIENTE
						WHEN 5 THEN DS_TAG_VOLUME
						WHEN 6 THEN DS_TAG_RESERVATORIO  -- INCLUSÃO RESERVATORIO
						ELSE NULL
			END AS DS_TAG,
			p.CD_LOCALIDADE,
			l.CD_UNIDADE
		FROM PONTO_MEDICAO p
		INNER JOIN LOCALIDADE l ON l.CD_CHAVE = p.CD_LOCALIDADE
		LEFT OUTER JOIN (
			--SELECT CD_PONTO_MEDICAO, MAX(DT_LEITURA) AS DT_LEITURA FROM REGISTRO_VAZAO_PRESSAO GROUP BY CD_PONTO_MEDICAO
			-- Alteração feita por Vinícius em 13/06/2022
			SELECT CD_PONTO_MEDICAO, DT_LEITURA FROM ULTIMA_LEITURA_PONTO_MEDICAO
		) maxLeitura ON p.CD_PONTO_MEDICAO = maxLeitura.CD_PONTO_MEDICAO
		INNER JOIN (
			SELECT 1 AS CD_TAG UNION ALL
			SELECT 2 UNION ALL
			SELECT 3 UNION ALL
			SELECT 4 UNION ALL
			SELECT 5 UNION ALL
			SELECT 6  -- INCLUSÃO RESERVATORIO
		) AS tag ON tag.CD_TAG = 1 AND p.DS_TAG_VAZAO IS NOT NULL /* AND p.ID_TIPO_MEDIDOR <> 8 */ OR
					tag.CD_TAG = 2 AND p.DS_TAG_PRESSAO IS NOT NULL OR
					tag.CD_TAG = 3 AND p.DS_TAG_TEMP_AGUA IS NOT NULL OR
					tag.CD_TAG = 4 AND p.DS_TAG_TEMP_AMBIENTE IS NOT NULL OR
					tag.CD_TAG = 5 AND p.DS_TAG_VOLUME IS NOT NULL OR
					tag.CD_TAG = 6 AND p.DS_TAG_RESERVATORIO IS NOT NULL -- INCLUSÃO RESERVATORIO
		WHERE 
			(p.DT_ATIVACAO IS NOT NULL AND p.DT_ATIVACAO <= @now)
			AND (p.DT_DESATIVACAO IS NULL OR p.DT_DESATIVACAO > @now)
			AND p.ID_TIPO_LEITURA = @id_tipo_leitura
	), PontoDataInicial AS (
		SELECT
			CD_PONTO_MEDICAO,
			DS_NOME,
			OP_PERIODICIDADE_LEITURA,
			CASE WHEN OP_PERIODICIDADE_LEITURA = 1 THEN DATEADD(ss, CASE WHEN CD_TAG <> 5 THEN 1 ELSE 0 END, DATEADD(mi, DATEDIFF(mi,0,DT_INICIAL), 0)) -- zera segundos e adiciona 1 segundo se tag for diferente de 5
				 WHEN OP_PERIODICIDADE_LEITURA = 2 THEN DATEADD(mi, CASE WHEN CD_TAG <> 5 THEN 1 ELSE 0 END, DATEADD(hh, DATEDIFF(hh,0,DT_INICIAL), 0)) -- zera minutos e adiciona 1 minuto se tag for diferente de 5
				 WHEN OP_PERIODICIDADE_LEITURA = 3 THEN DATEADD(hh, CASE WHEN CD_TAG <> 5 THEN 1 ELSE 0 END, DATEADD(dd, DATEDIFF(dd,0,DT_INICIAL), 0)) -- zera horas e adiciona 1 hora se tag for diferente de 5
				 WHEN OP_PERIODICIDADE_LEITURA = 4 THEN DATEADD(dd, CASE WHEN CD_TAG <> 5 THEN 1 ELSE 0 END, DATEADD(mm, DATEDIFF(mm,0,DT_INICIAL), 0)) -- zera dias e adiciona 1 doa se tag for diferente de 5
				 ELSE NULL
			END AS DT_INICIAL,
			DT_FINAL,
			CD_USUARIO_RESPONSAVEL,
			CD_TAG,
			DS_TAG,
			CD_LOCALIDADE,
			CD_UNIDADE
		FROM PontoMedicao
		WHERE
			DT_FINAL > DT_INICIAL
	)
	INSERT INTO #ponto_medicao
	(
		CD_PONTO_MEDICAO,
		DS_NOME,
		OP_PERIODICIDADE_LEITURA,
		DT_INICIAL,
		DT_FINAL,
		QT_CYCLES,
		CD_USUARIO_RESPONSAVEL,
		CD_TAG,
		DS_TAG,
		CD_LOCALIDADE,
		CD_UNIDADE
	)
	SELECT 
		CD_PONTO_MEDICAO,
		DS_NOME,
		OP_PERIODICIDADE_LEITURA,
		DT_INICIAL,
		DT_FINAL,
		CASE WHEN OP_PERIODICIDADE_LEITURA = 1 THEN DATEDIFF(ss, DT_INICIAL, DT_FINAL) + 1
			 WHEN OP_PERIODICIDADE_LEITURA = 2 THEN DATEDIFF(mi, DT_INICIAL, DT_FINAL) + 1
			 WHEN OP_PERIODICIDADE_LEITURA = 3 THEN DATEDIFF(hh, DT_INICIAL, DT_FINAL) + 1
			 WHEN OP_PERIODICIDADE_LEITURA = 4 THEN DATEDIFF(dd, DT_INICIAL, DT_FINAL) + 1
			 ELSE NULL
		END AS QT_CYCLES,
		CD_USUARIO_RESPONSAVEL,
		CD_TAG,
		DS_TAG,
		CD_LOCALIDADE,
		CD_UNIDADE
	FROM PontoDataInicial;

    -- **** Cria tabela temporária para guardar informações de vazão, pressão, temperatura de água, 
    -- **** temperatura do ambiente e nível de reservatório.
    -- **** Faz 1 select na tabela HISTORY para cada tipo de frequencia: segundo, minuto, hora, dia
    -- *******************************************************************************************
    
    IF OBJECT_ID('TempDB..#registro_vazao_pressao') IS NOT NULL DROP TABLE #registro_vazao_pressao;
	CREATE TABLE #registro_vazao_pressao
	(
		CD_PONTO_MEDICAO				int						NOT NULL,
		CD_TAG							tinyint					NOT NULL,
		DS_TAG							varchar(25)				NOT NULL,
		DT_LEITURA						datetime				NOT NULL,
		vvalue							numeric(25,16)			NULL
	);
	CREATE CLUSTERED INDEX IX_TEMP_PM_LEITURA_TAG ON #registro_vazao_pressao (CD_PONTO_MEDICAO, DT_LEITURA);

	DECLARE @cdPontoMedicao AS INT,
			@cdTag AS TINYINT,
			@dsTag AS VARCHAR(25),
			@qtCycles AS INT,
			@dtInicial AS DATETIME,
			@dtFinal AS DATETIME;

	DECLARE cs_ponto CURSOR LOCAL FORWARD_ONLY FOR
	SELECT  
	   CD_PONTO_MEDICAO,
	   CD_TAG,
	   DS_TAG,
	   QT_CYCLES,
	   DT_INICIAL,
	   DT_FINAL
	FROM #ponto_medicao;

	OPEN cs_ponto;

	FETCH NEXT FROM cs_ponto INTO @cdPontoMedicao,
								  @cdTag,
								  @dsTag,
						 		  @qtCycles,
								  @dtInicial,
								  @dtFinal;							  
	WHILE (@@FETCH_STATUS = 0)
	BEGIN	
		IF (@cdTag = 5) -- tag de volume
		BEGIN
			WITH RegistroVolume AS (
				SELECT
				h.datetime,
				CAST(CAST(h.vvalue AS FLOAT(53)) AS NUMERIC(25,16)) as vvalue,
				ROW_NUMBER() OVER (ORDER BY h.datetime) as RowNum
				FROM [HISTORIADOR_CCO].[Runtime].[dbo].HISTORY h
				where h.datetime >= @dtInicial
					  AND h.datetime <= @dtFinal
					  AND h.TagName = @dsTag
					  AND h.wwCycleCount = @qtCycles
					  AND h.wwRetrievalMode = 'Cyclic'
					  AND h.wwVersion = 'Latest'
			)
			INSERT INTO #registro_vazao_pressao
			(
				CD_PONTO_MEDICAO,
				CD_TAG,
				DS_TAG,
				DT_LEITURA,
				vvalue
			)
			SELECT
				@cdPontoMedicao,
				@cdTag,
				@dsTag,
				atual.datetime,
				atual.vvalue - anterior.vvalue
			FROM RegistroVolume AS atual
			INNER JOIN RegistroVolume AS anterior ON atual.RowNum - 1 = anterior.RowNum
			WHERE
				atual.RowNum <> 1 AND
               (atual.vvalue - anterior.vvalue) IS NOT NULL
               -- AMS_3601 - Erick Sperandio - Impedindo que um registro duplicado seja inserido em REGISTRO_VAZAO_PRESSAO, vindo do Historiador
               AND 1>(SELECT COUNT(*) FROM REGISTRO_VAZAO_PRESSAO RVP
						WHERE	RVP.CD_PONTO_MEDICAO = @cdPontoMedicao AND
								RVP.DT_LEITURA = atual.datetime AND
								RVP.ID_SITUACAO = 1);
			   -- fim AMS_3601
		END
		ELSE
		BEGIN
			-- 0,26 segundos por ponto de medicao por dia no ambiente de dev
			-- copiou 3.373.849 em 5:51 para um mês.
			INSERT INTO #registro_vazao_pressao
			(
				CD_PONTO_MEDICAO,
				CD_TAG,
				DS_TAG,
				DT_LEITURA,	
				vvalue
			)
			-- Essa tem sempre que passar por cada ponto
			-- de medição senão NÂO FUNCIONA. Não sei por
			-- que diabos isso acontece.
			SELECT
				@cdPontoMedicao,
				@cdTag,
				@dsTag,
				h.datetime,
				CAST(CAST(h.vvalue AS FLOAT(53)) AS NUMERIC(25,16)) as vvalue
			FROM [HISTORIADOR_CCO].[Runtime].[dbo].HISTORY h
			where h.datetime >= @dtInicial
				  AND h.datetime <= @dtFinal
				  AND h.TagName = @dsTag
				  AND h.wwCycleCount = @qtCycles
				  AND h.wwRetrievalMode = 'Cyclic'
				  AND h.wwVersion = 'Latest'
                  AND CAST(CAST(h.vvalue AS FLOAT(53)) AS NUMERIC(25,16)) IS NOT NULL
             -- AMS_3601 - Erick Sperandio - Impedindo que um registro duplicado seja inserido em REGISTRO_VAZAO_PRESSAO, vindo do Historiador
				  AND 1>(SELECT COUNT(*) FROM REGISTRO_VAZAO_PRESSAO RVP
							WHERE	RVP.CD_PONTO_MEDICAO = @cdPontoMedicao AND
									RVP.DT_LEITURA = h.datetime AND
									RVP.ID_SITUACAO = 1);
			 -- fim AMS_3601
;
		END

		FETCH NEXT FROM cs_ponto INTO @cdPontoMedicao,
									  @cdTag,
									  @dsTag,
						 			  @qtCycles,
									  @dtInicial,
									  @dtFinal;
	END

	CLOSE cs_ponto;
	DEALLOCATE cs_ponto;

    -- Inicia a transação
	BEGIN TRANSACTION;
    INSERT INTO REGISTRO_VAZAO_PRESSAO
	(
		CD_PONTO_MEDICAO,
		DT_LEITURA,
		ID_TIPO_REGISTRO,
		ID_TIPO_MEDICAO,		
		CD_USUARIO_RESPONSAVEL,
		CD_USUARIO_ULTIMA_ATUALIZACAO,
		DT_ULTIMA_ATUALIZACAO,
		DT_EVENTO_MEDICAO,
		VL_VAZAO,
		VL_PRESSAO,
		VL_TEMP_AGUA,
		VL_TEMP_AMBIENTE,
		ID_SITUACAO,
		ID_TIPO_VAZAO,
		VL_VOLUME,
		VL_PERIODO_MEDICAO_VOLUME,
		-- AMS_3473 - Erick
		VL_VAZAO_EFETIVA,
		-- INCLUSÃO RESERVATORIO
		VL_RESERVATORIO,
		-- SR76402 - SR004
		NR_EXTRAVASOU,
		NR_MOTIVO	
		-- FIM
	)
	SELECT
		base.CD_PONTO_MEDICAO,
		base.DT_LEITURA,
		@id_tipo_leitura,
		@ID_TIPO_MEDICAO,
		ponto.CD_USUARIO_RESPONSAVEL,
		@cd_usuario AS CD_USUARIO_ULTIMA_ATUALIZACAO,
		@now AS DT_ULTIMA_ATUALIZACAO,
		@DT_EVENTO_MEDICAO AS DT_EVENTO_MEDICAO,
		vazao.vvalue AS VL_VAZAO,
		pressao.vvalue AS VL_PRESSAO,
		tempAgua.vvalue AS VL_TEMP_AGUA,
		tempAmbiente.vvalue AS VL_TEMP_AMBIENTE,
		1 AS ID_SITUACAO, -- Situação ativo
		2 AS ID_TIPO_VAZAO, -- Macromedido
		volume.vvalue AS VL_VOLUME,
		CASE WHEN volume.vvalue IS NOT NULL THEN CASE ponto.OP_PERIODICIDADE_LEITURA WHEN 1 THEN 1 -- segundo
																					 WHEN 2 THEN 60 -- minuto
																					 WHEN 3 THEN 3600 -- hora
																					 WHEN 4 THEN 86400 -- dia
																					 ELSE NULL
												 END
											ELSE NULL
		END AS VL_PERIODO_MEDICAO_VOLUME,
		-- AMS_3473 - Erick
		vazao.vvalue AS VL_VAZAO_EFETIVA,
		-- INCLUSÃO RESERVATORIO
		reservatorio.vvalue,
		-- SR76402 - SR004
		CASE WHEN reservatorio.vvalue >= 100 THEN 1 ELSE NULL END AS NR_EXTRAVASOU,
		CASE WHEN reservatorio.vvalue >= 100 THEN 1 ELSE NULL END AS NR_MOTIVO
		-- FIM
	FROM (
		SELECT DISTINCT CD_PONTO_MEDICAO, DT_LEITURA FROM #registro_vazao_pressao
	) as base
	INNER JOIN (
		SELECT DISTINCT CD_PONTO_MEDICAO, CD_USUARIO_RESPONSAVEL, OP_PERIODICIDADE_LEITURA FROM #ponto_medicao
	) as ponto ON base.CD_PONTO_MEDICAO = ponto.CD_PONTO_MEDICAO
	LEFT OUTER JOIN #registro_vazao_pressao AS vazao on base.CD_PONTO_MEDICAO = vazao.CD_PONTO_MEDICAO AND
														base.DT_LEITURA = vazao.DT_LEITURA AND
														vazao.CD_TAG = 1 -- leitura de vazao
	LEFT OUTER JOIN #registro_vazao_pressao AS pressao ON base.CD_PONTO_MEDICAO = pressao.CD_PONTO_MEDICAO AND
														  base.DT_LEITURA = pressao.DT_LEITURA AND
														  pressao.CD_TAG = 2 -- leitura de pressao
	LEFT OUTER JOIN #registro_vazao_pressao AS tempAgua ON base.CD_PONTO_MEDICAO = tempAgua.CD_PONTO_MEDICAO AND
														   base.DT_LEITURA = tempAgua.DT_LEITURA AND
														   tempAgua.CD_TAG = 3 -- temperatura da água
	LEFT OUTER JOIN #registro_vazao_pressao AS tempAmbiente ON base.CD_PONTO_MEDICAO = tempAmbiente.CD_PONTO_MEDICAO AND
															   base.DT_LEITURA = tempAmbiente.DT_LEITURA AND
															   tempAmbiente.CD_TAG = 4 -- temperatura ambiente
	LEFT OUTER JOIN #registro_vazao_pressao AS volume ON base.CD_PONTO_MEDICAO = volume.CD_PONTO_MEDICAO AND
														 base.DT_LEITURA = volume.DT_LEITURA AND
														 volume.CD_TAG = 5 -- temperatura ambiente
	LEFT OUTER JOIN #registro_vazao_pressao AS reservatorio ON base.CD_PONTO_MEDICAO = reservatorio.CD_PONTO_MEDICAO AND
														 base.DT_LEITURA = reservatorio.DT_LEITURA AND
														 reservatorio.CD_TAG = 6 -- INCLUSÃO RESERVATORIO
	WHERE 
		vazao.vvalue IS NOT NULL OR
		pressao.vvalue IS NOT NULL OR
		tempAgua.vvalue IS NOT NULL OR
		tempAmbiente.vvalue IS NOT NULL OR
		volume.vvalue IS NOT NULL OR
		reservatorio.vvalue IS NOT NULL;-- INCLUSÃO RESERVATORIO
	/*
	-- Insersão dos pontos de medição para as datas dos registros como validado
	WITH PontoMedicaoDia AS (
		SELECT DISTINCT CD_PONTO_MEDICAO, DATEADD(DD, 0, DATEDIFF(DD, 0, DT_LEITURA)) AS DT_DIA FROM  #registro_vazao_pressao
	)
	MERGE SITUACAO_PONTO_MEDICAO_DIA AS TARGET
	USING PontoMedicaoDia AS SOURCE ON TARGET.CD_PONTO_MEDICAO = SOURCE.CD_PONTO_MEDICAO AND
									   TARGET.DT_REFERENCIA = SOURCE.DT_DIA
	WHEN MATCHED AND TARGET.OP_SITUACAO_PONTO_MEDICAO NOT IN (2, 3) THEN UPDATE SET TARGET.OP_SITUACAO_PONTO_MEDICAO = 2 -- Validado(2), excetuando-se quando estiver marcado como exportado (3)
	WHEN NOT MATCHED THEN INSERT (CD_PONTO_MEDICAO, DT_REFERENCIA, OP_SITUACAO_PONTO_MEDICAO) VALUES (SOURCE.CD_PONTO_MEDICAO, SOURCE.DT_DIA, 2); -- Insersão como validado

	-- Erick - AMS_2005 - Inserção dos pontos de medição para as datas dos registros como validado, para cada dia, em PONTO_MEDICAO_OPERACAO_DIA
	
	DECLARE @dt_lote datetime;
	SET @dt_lote = GETDATE();
	
	WITH PontoMedicaoDadosDia AS (
		SELECT DISTINCT CD_PONTO_MEDICAO, DATEADD(DD, 0, DATEDIFF(DD, 0, DT_LEITURA)) 
			AS DT_INICIO, DATEADD(DD, 1, DATEDIFF(DD, 0, DT_LEITURA)) AS DT_FIM FROM  #registro_vazao_pressao
	)
	MERGE PONTO_MEDICAO_OPERACAO_DIA AS TARGET
	USING PontoMedicaoDadosDia AS SOURCE ON TARGET.CD_PONTO_MEDICAO = SOURCE.CD_PONTO_MEDICAO AND
									   TARGET.DT_HORA_INICIAL = SOURCE.DT_INICIO AND
									   TARGET.DT_HORA_FINAL = SOURCE.DT_FIM
    -- Usuário 100 = Bruno Nacari									   
	WHEN NOT MATCHED THEN INSERT (CD_PONTO_MEDICAO, DT_HORA_INICIAL, DT_HORA_FINAL, DT_ULTIMA_ATUALIZACAO, CD_USUARIO_ULTIMA_ATUALIZACAO) 
							VALUES (SOURCE.CD_PONTO_MEDICAO, SOURCE.DT_INICIO, SOURCE.DT_FIM, @dt_lote, 100); -- Insersão como validado
    -- FIM AMS_2005
    */
	INSERT INTO LOG
    (
    	CD_USUARIO,
    	CD_FUNCIONALIDADE,
    	CD_UNIDADE,
    	DT_LOG,
    	TP_LOG,
    	NM_LOG,
    	DS_LOG,
    	DS_VERSAO,
    	NM_SERVIDOR
    )
    SELECT  
        @cd_usuario,
        @cd_funcionalidade,
        p.CD_UNIDADE,
        @now,
        @log_alerta,
        'Job de Integração do CCO',
        'Importação CCO do Ponto de Medição ' + cast(p.CD_PONTO_MEDICAO as varchar) + '-' + RTRIM(p.DS_NOME) + ' e da TAG: ' + RTRIM(p.DS_TAG) + ', sem registros p/ importar.',
        @ds_versao, 
        cast(serverproperty('MachineName') as varchar)
    FROM #ponto_medicao p
    LEFT OUTER JOIN #registro_vazao_pressao AS r ON r.CD_PONTO_MEDICAO = p.CD_PONTO_MEDICAO AND
        											r.DS_TAG = p.DS_TAG
	WHERE
		r.CD_PONTO_MEDICAO IS NULL;
    
    -- *** Commit na transacao
	-- ****************************************************************************************
	IF @@TRANCOUNT > 0
		COMMIT TRANSACTION;

	RETURN 0
END TRY

-- *** Inicio do tratamento de erro
-- ****************************************************************************************
BEGIN CATCH
    -- *** Rollback na transacao
    -- ****************************************************************************************
    IF @@TRANCOUNT > 0
        ROLLBACK TRANSACTION;

    SET @sp_msg_erro = CAST(ERROR_NUMBER() AS VARCHAR) + ' - ' + ERROR_MESSAGE()    
    
	RETURN -1
END CATCH
	


USE [SIMP]
GO

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
	@now					datetime = null,
	@dias_retroativos		int = 30
)
AS	
SET NOCOUNT ON;

-- Declara constantes
DECLARE @log_erro							tinyint = 1,
		@log_alerta							tinyint = 2,
		@log_aviso							tinyint = 3,
		@ID_TIPO_MEDICAO					tinyint = 1;

-- Declara variaveis
DECLARE @rtn								int,
		@DT_EVENTO_MEDICAO                  datetime,
		@today								datetime,
		@dt_limite_minimo					datetime,
		@total_pontos						int = 0,
		@pontos_processados					int = 0,
		@pontos_com_dados					int = 0,
		@registros_inseridos				int = 0,
		@registros_duplicados				int = 0;
    
-- Inicializa variáveis
SET @now = ISNULL(@now, DATEADD(SECOND, -1, CAST(CAST(GETDATE() AS DATE) AS DATETIME)));
SET @today = @now;
SET @dt_limite_minimo = DATEADD(DAY, -@dias_retroativos, @now);
SET @DT_EVENTO_MEDICAO = CONVERT(VARCHAR, @now, 20);

-- Inicio do processo
BEGIN TRY
	-- =====================================================
	-- ETAPA 1: Criar tabela de pontos de medição
	-- =====================================================
	IF OBJECT_ID('TempDB..#ponto_medicao') IS NOT NULL DROP TABLE #ponto_medicao;
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
			CASE 
				WHEN ISNULL(maxLeitura.DT_LEITURA, CAST(0x00 AS DATETIME)) >= @dt_limite_minimo 
				THEN maxLeitura.DT_LEITURA
				WHEN p.DT_ATIVACAO >= @dt_limite_minimo 
				THEN p.DT_ATIVACAO
				ELSE @dt_limite_minimo
			END AS DT_INICIAL,
			@today AS DT_FINAL,
			p.CD_USUARIO_RESPONSAVEL,
			CD_TAG,
			CASE CD_TAG WHEN 1 THEN DS_TAG_VAZAO
						WHEN 2 THEN DS_TAG_PRESSAO
						WHEN 3 THEN DS_TAG_TEMP_AGUA
						WHEN 4 THEN DS_TAG_TEMP_AMBIENTE
						WHEN 5 THEN DS_TAG_VOLUME
						WHEN 6 THEN DS_TAG_RESERVATORIO
						ELSE NULL
			END AS DS_TAG,
			p.CD_LOCALIDADE,
			l.CD_UNIDADE
		FROM PONTO_MEDICAO p
		INNER JOIN LOCALIDADE l ON l.CD_CHAVE = p.CD_LOCALIDADE
		LEFT OUTER JOIN (
			SELECT CD_PONTO_MEDICAO, MAX(DT_LEITURA) AS DT_LEITURA
				FROM REGISTRO_VAZAO_PRESSAO
				WHERE ID_SITUACAO = 1
				GROUP BY CD_PONTO_MEDICAO
			) maxLeitura ON p.CD_PONTO_MEDICAO = maxLeitura.CD_PONTO_MEDICAO
		INNER JOIN (
			SELECT 1 AS CD_TAG UNION ALL
			SELECT 2 UNION ALL
			SELECT 3 UNION ALL
			SELECT 4 UNION ALL
			SELECT 5 UNION ALL
			SELECT 6
		) AS tag ON tag.CD_TAG = 1 AND p.DS_TAG_VAZAO IS NOT NULL OR
					tag.CD_TAG = 2 AND p.DS_TAG_PRESSAO IS NOT NULL OR
					tag.CD_TAG = 3 AND p.DS_TAG_TEMP_AGUA IS NOT NULL OR
					tag.CD_TAG = 4 AND p.DS_TAG_TEMP_AMBIENTE IS NOT NULL OR
					tag.CD_TAG = 5 AND p.DS_TAG_VOLUME IS NOT NULL OR
					tag.CD_TAG = 6 AND p.DS_TAG_RESERVATORIO IS NOT NULL
		WHERE 
			(p.DT_ATIVACAO IS NOT NULL AND p.DT_ATIVACAO <= @now)
			AND (p.DT_DESATIVACAO IS NULL OR p.DT_DESATIVACAO > @now)
			AND p.ID_TIPO_LEITURA = @id_tipo_leitura
		), PontoDataInicial AS (
				SELECT
					CD_PONTO_MEDICAO,
					DS_NOME,
					OP_PERIODICIDADE_LEITURA,
					CASE WHEN OP_PERIODICIDADE_LEITURA = 1 THEN DATEADD(ss, CASE WHEN CD_TAG <> 5 THEN 1 ELSE 0 END, DT_INICIAL)
						WHEN OP_PERIODICIDADE_LEITURA = 2 THEN DATEADD(mi, CASE WHEN CD_TAG <> 5 THEN 1 ELSE 0 END, DT_INICIAL)
						WHEN OP_PERIODICIDADE_LEITURA = 3 THEN DATEADD(hh, CASE WHEN CD_TAG <> 5 THEN 1 ELSE 0 END, DT_INICIAL)
						WHEN OP_PERIODICIDADE_LEITURA = 4 THEN DATEADD(dd, CASE WHEN CD_TAG <> 5 THEN 1 ELSE 0 END, DT_INICIAL)
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

	SELECT @total_pontos = COUNT(*) FROM #ponto_medicao;

	-- =====================================================
	-- ETAPA 2: Criar tabelas temporárias
	-- =====================================================
	-- Tabela RAW: dados brutos do historiador
	IF OBJECT_ID('TempDB..#registro_raw') IS NOT NULL DROP TABLE #registro_raw;
	CREATE TABLE #registro_raw
	(
		DT_LEITURA						datetime				NOT NULL,
		vvalue							numeric(25,16)			NULL
	);

	-- Tabela LIMPA: dados deduplicados prontos para inserir
	IF OBJECT_ID('TempDB..#registro_limpo') IS NOT NULL DROP TABLE #registro_limpo;
	CREATE TABLE #registro_limpo
	(
		CD_PONTO_MEDICAO				int						NOT NULL,
		DT_LEITURA						datetime				NOT NULL,
		vvalue							numeric(25,16)			NULL
	);

	-- Variáveis do cursor
	DECLARE @cdPontoMedicao INT,
			@cdTag TINYINT,
			@dsTag VARCHAR(25),
			@qtCycles INT,
			@dtInicial DATETIME,
			@dtFinal DATETIME,
			@cdUsuarioResponsavel BIGINT,
			@opPeriodicidadeLeitura TINYINT,
			@dsNome VARCHAR(50),
			@cdUnidade INT;

	DECLARE @sql NVARCHAR(MAX);
	DECLARE @rowcount INT;

	-- =====================================================
	-- ETAPA 3: Cursor para processar cada ponto/tag
	-- =====================================================
	DECLARE cs_ponto CURSOR LOCAL FAST_FORWARD FOR
	SELECT  
	   CD_PONTO_MEDICAO, CD_TAG, DS_TAG, QT_CYCLES, DT_INICIAL, DT_FINAL,
	   CD_USUARIO_RESPONSAVEL, OP_PERIODICIDADE_LEITURA, DS_NOME, CD_UNIDADE
	FROM #ponto_medicao
	ORDER BY CD_PONTO_MEDICAO, CD_TAG;

	OPEN cs_ponto;

	FETCH NEXT FROM cs_ponto INTO @cdPontoMedicao, @cdTag, @dsTag, @qtCycles, @dtInicial, @dtFinal,
								  @cdUsuarioResponsavel, @opPeriodicidadeLeitura, @dsNome, @cdUnidade;

	WHILE (@@FETCH_STATUS = 0)
	BEGIN
		SET @pontos_processados = @pontos_processados + 1;
		
		BEGIN TRY
			-- Limpa tabelas temporárias
			TRUNCATE TABLE #registro_raw;
			TRUNCATE TABLE #registro_limpo;

			-- =================================================
			-- PASSO 1: Buscar dados brutos do historiador
			-- =================================================
			SET @sql = N'
			INSERT INTO #registro_raw (DT_LEITURA, vvalue)
			SELECT
				h.datetime,
				CAST(CAST(h.vvalue AS FLOAT(53)) AS NUMERIC(25,16))
			FROM [HISTORIADOR_CCO].[Runtime].[dbo].Tag t,
			     [HISTORIADOR_CCO].[Runtime].[dbo].HISTORY h
			WHERE h.TagName IN (''' + @dsTag + ''')
			      AND t.TagName = h.TagName
			      AND h.datetime >= ''' + CONVERT(VARCHAR(23), @dtInicial, 121) + '''
			      AND h.datetime <= ''' + CONVERT(VARCHAR(23), @dtFinal, 121) + '''
			      AND h.wwCycleCount = ' + CAST(@qtCycles AS NVARCHAR) + '
			      AND h.wwRetrievalMode = ''Cyclic''
			      AND h.wwVersion = ''Latest''
			      AND h.vvalue IS NOT NULL';
			
			EXEC sp_executesql @sql;

			-- =================================================
			-- PASSO 2: Truncar para o minuto e pegar 1 por minuto
			-- =================================================
			IF (@cdTag = 5) -- volume: cálculo diferencial
			BEGIN
				;WITH RegistroMinuto AS (
					SELECT
						DATEADD(mi, DATEDIFF(mi, 0, DT_LEITURA), 0) AS dt_minuto,
						vvalue,
						ROW_NUMBER() OVER (PARTITION BY DATEADD(mi, DATEDIFF(mi, 0, DT_LEITURA), 0) ORDER BY DT_LEITURA) as rn
					FROM #registro_raw
				),
				RegistroUnico AS (
					SELECT dt_minuto, vvalue,
						   ROW_NUMBER() OVER (ORDER BY dt_minuto) as RowNum
					FROM RegistroMinuto
					WHERE rn = 1
				)
				INSERT INTO #registro_limpo (CD_PONTO_MEDICAO, DT_LEITURA, vvalue)
				SELECT
					@cdPontoMedicao,
					atual.dt_minuto,
					atual.vvalue - anterior.vvalue
				FROM RegistroUnico AS atual
				INNER JOIN RegistroUnico AS anterior ON atual.RowNum = anterior.RowNum + 1
				WHERE (atual.vvalue - anterior.vvalue) IS NOT NULL;
			END
			ELSE -- demais tags: pegar primeiro valor de cada minuto
			BEGIN
				;WITH RegistroMinuto AS (
					SELECT
						DATEADD(mi, DATEDIFF(mi, 0, DT_LEITURA), 0) AS dt_minuto,
						vvalue,
						ROW_NUMBER() OVER (PARTITION BY DATEADD(mi, DATEDIFF(mi, 0, DT_LEITURA), 0) ORDER BY DT_LEITURA) as rn
					FROM #registro_raw
				)
				INSERT INTO #registro_limpo (CD_PONTO_MEDICAO, DT_LEITURA, vvalue)
				SELECT
					@cdPontoMedicao,
					dt_minuto,
					vvalue
				FROM RegistroMinuto
				WHERE rn = 1;
			END

			-- =================================================
			-- PASSO 3: Remover registros que já existem na tabela final
			-- =================================================
			DELETE r
			FROM #registro_limpo r
			WHERE EXISTS (
				SELECT 1 
				FROM REGISTRO_VAZAO_PRESSAO rvp
				WHERE rvp.CD_PONTO_MEDICAO = r.CD_PONTO_MEDICAO 
					AND rvp.DT_LEITURA = r.DT_LEITURA
			);
			
			SET @registros_duplicados = @registros_duplicados + @@ROWCOUNT;

			-- =================================================
			-- PASSO 4: Inserir na tabela final
			-- =================================================
			SELECT @rowcount = COUNT(*) FROM #registro_limpo;

			IF @rowcount > 0
			BEGIN
				IF (@cdTag = 1) -- vazão
				BEGIN
					INSERT INTO REGISTRO_VAZAO_PRESSAO
					(CD_PONTO_MEDICAO, DT_LEITURA, ID_TIPO_REGISTRO, ID_TIPO_MEDICAO,
					 CD_USUARIO_RESPONSAVEL, CD_USUARIO_ULTIMA_ATUALIZACAO, DT_ULTIMA_ATUALIZACAO,
					 DT_EVENTO_MEDICAO, VL_VAZAO, VL_VAZAO_EFETIVA, ID_SITUACAO, ID_TIPO_VAZAO)
					SELECT CD_PONTO_MEDICAO, DT_LEITURA, @id_tipo_leitura, 1,
						   @cdUsuarioResponsavel, @cd_usuario, @now,
						   @DT_EVENTO_MEDICAO, vvalue, vvalue, 1, 2
					FROM #registro_limpo;
				END
				ELSE IF (@cdTag = 2) -- pressão
				BEGIN
					INSERT INTO REGISTRO_VAZAO_PRESSAO
					(CD_PONTO_MEDICAO, DT_LEITURA, ID_TIPO_REGISTRO, ID_TIPO_MEDICAO,
					 CD_USUARIO_RESPONSAVEL, CD_USUARIO_ULTIMA_ATUALIZACAO, DT_ULTIMA_ATUALIZACAO,
					 DT_EVENTO_MEDICAO, VL_PRESSAO, ID_SITUACAO, ID_TIPO_VAZAO)
					SELECT CD_PONTO_MEDICAO, DT_LEITURA, @id_tipo_leitura, 1,
						   @cdUsuarioResponsavel, @cd_usuario, @now,
						   @DT_EVENTO_MEDICAO, vvalue, 1, 2
					FROM #registro_limpo;
				END
				ELSE IF (@cdTag = 3) -- temperatura água
				BEGIN
					INSERT INTO REGISTRO_VAZAO_PRESSAO
					(CD_PONTO_MEDICAO, DT_LEITURA, ID_TIPO_REGISTRO, ID_TIPO_MEDICAO,
					 CD_USUARIO_RESPONSAVEL, CD_USUARIO_ULTIMA_ATUALIZACAO, DT_ULTIMA_ATUALIZACAO,
					 DT_EVENTO_MEDICAO, VL_TEMP_AGUA, ID_SITUACAO, ID_TIPO_VAZAO)
					SELECT CD_PONTO_MEDICAO, DT_LEITURA, @id_tipo_leitura, 1,
						   @cdUsuarioResponsavel, @cd_usuario, @now,
						   @DT_EVENTO_MEDICAO, vvalue, 1, 2
					FROM #registro_limpo;
				END
				ELSE IF (@cdTag = 4) -- temperatura ambiente
				BEGIN
					INSERT INTO REGISTRO_VAZAO_PRESSAO
					(CD_PONTO_MEDICAO, DT_LEITURA, ID_TIPO_REGISTRO, ID_TIPO_MEDICAO,
					 CD_USUARIO_RESPONSAVEL, CD_USUARIO_ULTIMA_ATUALIZACAO, DT_ULTIMA_ATUALIZACAO,
					 DT_EVENTO_MEDICAO, VL_TEMP_AMBIENTE, ID_SITUACAO, ID_TIPO_VAZAO)
					SELECT CD_PONTO_MEDICAO, DT_LEITURA, @id_tipo_leitura, 1,
						   @cdUsuarioResponsavel, @cd_usuario, @now,
						   @DT_EVENTO_MEDICAO, vvalue, 1, 2
					FROM #registro_limpo;
				END
				ELSE IF (@cdTag = 5) -- volume
				BEGIN
					INSERT INTO REGISTRO_VAZAO_PRESSAO
					(CD_PONTO_MEDICAO, DT_LEITURA, ID_TIPO_REGISTRO, ID_TIPO_MEDICAO,
					 CD_USUARIO_RESPONSAVEL, CD_USUARIO_ULTIMA_ATUALIZACAO, DT_ULTIMA_ATUALIZACAO,
					 DT_EVENTO_MEDICAO, VL_VOLUME, VL_PERIODO_MEDICAO_VOLUME, ID_SITUACAO, ID_TIPO_VAZAO)
					SELECT CD_PONTO_MEDICAO, DT_LEITURA, @id_tipo_leitura, 1,
						   @cdUsuarioResponsavel, @cd_usuario, @now,
						   @DT_EVENTO_MEDICAO, vvalue,
						   CASE @opPeriodicidadeLeitura WHEN 1 THEN 1 WHEN 2 THEN 60 WHEN 3 THEN 3600 WHEN 4 THEN 86400 END,
						   1, 2
					FROM #registro_limpo;
				END
				ELSE IF (@cdTag = 6) -- reservatório
				BEGIN
					INSERT INTO REGISTRO_VAZAO_PRESSAO
					(CD_PONTO_MEDICAO, DT_LEITURA, ID_TIPO_REGISTRO, ID_TIPO_MEDICAO,
					 CD_USUARIO_RESPONSAVEL, CD_USUARIO_ULTIMA_ATUALIZACAO, DT_ULTIMA_ATUALIZACAO,
					 DT_EVENTO_MEDICAO, VL_RESERVATORIO, NR_EXTRAVASOU, NR_MOTIVO, ID_SITUACAO, ID_TIPO_VAZAO)
					SELECT CD_PONTO_MEDICAO, DT_LEITURA, @id_tipo_leitura, 1,
						   @cdUsuarioResponsavel, @cd_usuario, @now,
						   @DT_EVENTO_MEDICAO, vvalue,
						   CASE WHEN vvalue >= 100 THEN 1 ELSE NULL END,
						   CASE WHEN vvalue >= 100 THEN 1 ELSE NULL END,
						   1, 2
					FROM #registro_limpo;
				END

				SET @pontos_com_dados = @pontos_com_dados + 1;
				SET @registros_inseridos = @registros_inseridos + @rowcount;
			END
			ELSE
			BEGIN
				INSERT INTO LOG (CD_USUARIO, CD_FUNCIONALIDADE, CD_UNIDADE, DT_LOG, TP_LOG, NM_LOG, DS_LOG, DS_VERSAO, NM_SERVIDOR)
				VALUES (@cd_usuario, @cd_funcionalidade, @cdUnidade, GETDATE(), @log_alerta, 'Job de Integração do CCO',
						'Ponto ' + CAST(@cdPontoMedicao AS VARCHAR) + '-' + RTRIM(@dsNome) + ' TAG: ' + RTRIM(@dsTag) + ', sem registros novos.',
						@ds_versao, CAST(SERVERPROPERTY('MachineName') AS VARCHAR));
			END

		END TRY
		BEGIN CATCH
			INSERT INTO LOG (CD_USUARIO, CD_FUNCIONALIDADE, CD_UNIDADE, DT_LOG, TP_LOG, NM_LOG, DS_LOG, DS_VERSAO, NM_SERVIDOR)
			VALUES (@cd_usuario, @cd_funcionalidade, @cdUnidade, GETDATE(), @log_erro, 'Erro no Job de Integração do CCO',
					'Erro no Ponto ' + CAST(@cdPontoMedicao AS VARCHAR) + ' TAG: ' + @dsTag + ' - ' + CAST(ERROR_NUMBER() AS VARCHAR) + ': ' + ERROR_MESSAGE(),
					@ds_versao, CAST(SERVERPROPERTY('MachineName') AS VARCHAR));
		END CATCH

		FETCH NEXT FROM cs_ponto INTO @cdPontoMedicao, @cdTag, @dsTag, @qtCycles, @dtInicial, @dtFinal,
									  @cdUsuarioResponsavel, @opPeriodicidadeLeitura, @dsNome, @cdUnidade;
	END

	CLOSE cs_ponto;
	DEALLOCATE cs_ponto;

	-- Log final de resumo
	INSERT INTO LOG (CD_USUARIO, CD_FUNCIONALIDADE, CD_UNIDADE, DT_LOG, TP_LOG, NM_LOG, DS_LOG, DS_VERSAO, NM_SERVIDOR)
	VALUES (@cd_usuario, @cd_funcionalidade, NULL, GETDATE(), @log_aviso, 'Job de Integração do CCO',
			'Resumo: ' + CAST(@pontos_processados AS VARCHAR) + '/' + CAST(@total_pontos AS VARCHAR) + ' pontos, ' +
			CAST(@pontos_com_dados AS VARCHAR) + ' com dados, ' + CAST(@registros_inseridos AS VARCHAR) + ' inseridos, ' +
			CAST(@registros_duplicados AS VARCHAR) + ' duplicados ignorados.',
			@ds_versao, CAST(SERVERPROPERTY('MachineName') AS VARCHAR));

	RETURN 0
END TRY

BEGIN CATCH
    SET @sp_msg_erro = CAST(ERROR_NUMBER() AS VARCHAR) + ' - ' + ERROR_MESSAGE()
	RETURN -1
END CATCH
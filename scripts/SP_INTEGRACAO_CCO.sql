USE [SIMP]
GO

SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO

--**************************************************************************
-- Procedure : SP_INTEGRACAO_CCO
--
-- Descrição : Procedure que executa a integração do BD do SIMP
--             com o BD do CCO. Ela chama a SP_INTEGRACAO_CCO_BODY.
--
-- Argumentos: @id_tipo_leitura     - Tipo de Leitura desejado para filtragem dos Pontos de Medição.
--             @ds_matricula        - Matricula do usuário da integração CCO
--             @sp_msg_erro         - Mensagem de erro retornada pela SP caso aconteça algum.
--             @now                 - Data/hora limite para busca (opcional, default dia anterior 23:59:59)
--             @dias_retroativos    - Quantidade máxima de dias para buscar retroativamente (opcional, default 30)
--
--**************************************************************************
-- Manutenção
--    Data       Autor           Empresa  Descrição
-- ----------    --------------- -------- ---------------------------
-- 18/11/2009    Akira           Vixteam  OS 36559 - Dividido em 2 SP
-- 04/02/2026    Bruno Nacari    CESAN    Adicionado parâmetro @dias_retroativos e SQL dinâmico para Historiador
--**************************************************************************

ALTER PROCEDURE [dbo].[SP_INTEGRACAO_CCO]
(
	@id_tipo_leitura		tinyint,
	@ds_matricula			varchar(10),
	@sp_msg_erro  			varchar(4000) output,
	@now					datetime = null,
	@dias_retroativos		int = 30
)
AS	

-- *** Declara constantes
-- ****************************************************************************************
DECLARE 
    @cd_funcionalidade					int = 19,
    @ds_versao                          varchar(20) = 'DB1.4.0.6',
    @log_erro							tinyint = 1,
    @log_alerta							tinyint = 2,
    @log_aviso							tinyint = 3

-- *** Declara variaveis
-- ****************************************************************************************
DECLARE
    @rtn								int,
    @cd_usuario							bigint,
    @ds_log_inicio						varchar(200)

-- *** Inicializa @now: se não informado, usa dia anterior às 23:59:59
-- ****************************************************************************************
SET @now = ISNULL(@now, DATEADD(SECOND, -1, CAST(CAST(GETDATE() AS DATE) AS DATETIME)));

-- *** Inicio do processo
-- ****************************************************************************************
BEGIN TRY
    -- **** Valida se foi passado um usuário válido para a SP
    -- *************************************************************************************
    SELECT @cd_usuario = CD_USUARIO
    FROM USUARIO
    WHERE DS_MATRICULA = @ds_matricula
    
    IF (@cd_usuario IS NULL)
    BEGIN
		SET @sp_msg_erro = 'ERRO: Usuário da Integração com CCO não existe. Matrícula: ' + @ds_matricula
		RAISERROR (9999991,-1,-1, @sp_msg_erro)
    END
    
    -- ***** Registra no log o inicio do job
    -- ***************************************************************************************
    SET @ds_log_inicio = 'Inicio - Data: ' + CONVERT(VARCHAR(19), @now, 120) + ' - Dias retroativos: ' + CAST(@dias_retroativos AS VARCHAR);
    
    EXEC @rtn = [dbo].SP_REGISTRA_LOG
    	@sprcd_usuario			= @cd_usuario,
    	@sprcd_funcionalidade	= @cd_funcionalidade,
    	@sprcd_unidade			= NULL,
    	@sprtp_log				= @log_aviso,
    	@sprnm_log				= 'Job de Integração do CCO',
    	@sprds_log				= @ds_log_inicio,
    	@sprds_versao           = @ds_versao

    -- ***** Chama a procedure principal
    -- ***************************************************************************************
    EXEC @rtn = [dbo].SP_INTEGRACAO_CCO_BODY 
        @id_tipo_leitura, 
        @cd_usuario, 
        @cd_funcionalidade, 
        @ds_versao, 
        @sp_msg_erro out, 
        @now,
        @dias_retroativos

    -- ***** Verifica se houve erro
    -- ***************************************************************************************
    IF @rtn <> 0
    BEGIN
    	EXEC @rtn = [dbo].SP_REGISTRA_LOG
    		@sprcd_usuario			= @cd_usuario,
    		@sprcd_funcionalidade	= @cd_funcionalidade,
    		@sprcd_unidade			= NULL,
    		@sprtp_log				= @log_erro,
    		@sprnm_log				= 'Erro no Job de Integração do CCO',
    		@sprds_log				= @sp_msg_erro,
    		@sprds_versao           = @ds_versao
    END

    -- ***** Registra no log que a execução da SP de integração com o CCO foi terminada *******
    -- ***************************************************************************************
    EXEC @rtn = [dbo].SP_REGISTRA_LOG
    	@sprcd_usuario			= @cd_usuario,
    	@sprcd_funcionalidade	= @cd_funcionalidade,
    	@sprcd_unidade			= NULL,
    	@sprtp_log				= @log_aviso,
    	@sprnm_log				= 'Job de Integração do CCO',
    	@sprds_log				= 'Fim',
    	@sprds_versao           = @ds_versao
END TRY

-- *** Inicio do tratamento de erro
-- ****************************************************************************************
BEGIN CATCH
	SET @sp_msg_erro = CAST(ERROR_NUMBER() AS VARCHAR) + ' - ' + ERROR_MESSAGE()

	EXEC @rtn = [dbo].SP_REGISTRA_LOG
		@sprcd_usuario			= @cd_usuario,
		@sprcd_funcionalidade	= @cd_funcionalidade,
		@sprcd_unidade			= NULL,
		@sprtp_log				= @log_erro,
		@sprnm_log				= 'Erro no Job de Integração do CCO',
		@sprds_log				= @sp_msg_erro,
		@sprds_versao           = @ds_versao

	RETURN -1
END CATCH
	
RETURN 0


-- -- COMO CHAMAR A PROCEDURE:
-- -- Busca últimos 7 dias até ontem 23:59:59 (default)
DECLARE @msg_erro VARCHAR(4000);
EXEC [simp].[dbo].SP_INTEGRACAO_CCO 
    @id_tipo_leitura = 8,
    @ds_matricula = '999999',
    @sp_msg_erro = @msg_erro OUTPUT,
    @dias_retroativos = 7;
SELECT @msg_erro AS Erro;

-- -- Busca últimos 60 dias até ontem 23:59:59 (default de ambos)
DECLARE @msg_erro VARCHAR(4000);
EXEC [simp].[dbo].SP_INTEGRACAO_CCO 
    @id_tipo_leitura = 8,
    @ds_matricula = '999999',
    @sp_msg_erro = @msg_erro OUTPUT;
SELECT @msg_erro AS Erro;

-- -- Busca até data específica (sobrescreve o default)
DECLARE @msg_erro VARCHAR(4000);
EXEC [simp].[dbo].SP_INTEGRACAO_CCO 
    @id_tipo_leitura = 8,
    @ds_matricula = '999999',
    @sp_msg_erro = @msg_erro OUTPUT,
    @now = '2025-12-07 23:59:59',
    @dias_retroativos = 7;
SELECT @msg_erro AS Erro;

-- Busca até agora (quase tempo real), últimos dados novos
DECLARE @msg_erro VARCHAR(4000);
DECLARE @agora DATETIME = GETDATE();
EXEC [simp].[dbo].SP_INTEGRACAO_CCO 
    @id_tipo_leitura = 8,
    @ds_matricula = '999999',
    @sp_msg_erro = @msg_erro OUTPUT,
    @now = @agora,
    @dias_retroativos = 1;
SELECT @msg_erro AS Erro;

-- -- Resumo dos defaults:

-- -- @now → dia anterior às 23:59:59
-- -- @dias_retroativos → 60 dias
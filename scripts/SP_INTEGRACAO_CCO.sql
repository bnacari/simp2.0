ALTER PROCEDURE [dbo].[SP_INTEGRACAO_CCO]
(
    @id_tipo_leitura        tinyint,
    @ds_matricula           varchar(10),
    @sp_msg_erro            varchar(4000) output,
    @now                    datetime = null,
    @dias_processar         int = 1  -- Novo parâmetro opcional
)
AS  

DECLARE 
    @cd_funcionalidade      int = 19,
    @ds_versao              varchar(20) = 'DB1.4.0.5',
    @log_erro               tinyint = 1,
    @log_alerta             tinyint = 2,
    @log_aviso              tinyint = 3

DECLARE
    @rtn                    int,
    @cd_usuario             bigint,
    @data                   date

BEGIN TRY
    -- Valida usuário
    SELECT @cd_usuario = CD_USUARIO
    FROM USUARIO
    WHERE DS_MATRICULA = @ds_matricula
    
    IF (@cd_usuario IS NULL)
    BEGIN
        SET @sp_msg_erro = 'ERRO: Usuário da Integração com CCO não existe. Matrícula: ' + @ds_matricula
        RAISERROR (9999991,-1,-1, @sp_msg_erro)
    END
    
    -- Log início
    EXEC @rtn = [dbo].SP_REGISTRA_LOG
        @sprcd_usuario          = @cd_usuario,
        @sprcd_funcionalidade   = @cd_funcionalidade,
        @sprcd_unidade          = NULL,
        @sprtp_log              = @log_aviso,
        @sprnm_log              = 'Job de Integração do CCO',
        @sprds_log              = 'Inicio',
        @sprds_versao           = @ds_versao

    -- =====================================================
    -- ETAPA 1: Integração CCO
    -- =====================================================
    EXEC @rtn = [dbo].SP_INTEGRACAO_CCO_BODY 
        @id_tipo_leitura, 
        @cd_usuario, 
        @cd_funcionalidade, 
        @ds_versao, 
        @sp_msg_erro OUTPUT, 
        @now

    IF @rtn <> 0
    BEGIN
        EXEC @rtn = [dbo].SP_REGISTRA_LOG
            @sprcd_usuario          = @cd_usuario,
            @sprcd_funcionalidade   = @cd_funcionalidade,
            @sprcd_unidade          = NULL,
            @sprtp_log              = @log_erro,
            @sprnm_log              = 'Erro no Job de Integração do CCO',
            @sprds_log              = @sp_msg_erro,
            @sprds_versao           = @ds_versao
        
        RETURN -1  -- Sai sem processar medições
    END

    -- =====================================================
    -- ETAPA 2: Processar Medições (após integração OK)
    -- =====================================================
    EXEC @rtn = [dbo].SP_REGISTRA_LOG
        @sprcd_usuario          = @cd_usuario,
        @sprcd_funcionalidade   = @cd_funcionalidade,
        @sprcd_unidade          = NULL,
        @sprtp_log              = @log_aviso,
        @sprnm_log              = 'Job de Integração do CCO',
        @sprds_log              = 'Iniciando processamento de medições',
        @sprds_versao           = @ds_versao

    DECLARE @DatasComDados TABLE (DT_LEITURA DATE);
    
    INSERT INTO @DatasComDados
    SELECT DISTINCT TOP (@dias_processar) 
        CAST(DT_LEITURA AS DATE) AS DT_LEITURA
    FROM REGISTRO_VAZAO_PRESSAO
    WHERE ID_SITUACAO IN (1, 2)
    ORDER BY CAST(DT_LEITURA AS DATE) DESC;

    DECLARE cur_datas CURSOR LOCAL FAST_FORWARD FOR 
        SELECT DT_LEITURA FROM @DatasComDados ORDER BY DT_LEITURA ASC;
    
    OPEN cur_datas;
    FETCH NEXT FROM cur_datas INTO @data;
    
    WHILE @@FETCH_STATUS = 0
    BEGIN
        EXEC SP_PROCESSAR_MEDICAO_V2 @DT_PROCESSAMENTO = @data;
        FETCH NEXT FROM cur_datas INTO @data;
    END
    
    CLOSE cur_datas;
    DEALLOCATE cur_datas;

    EXEC @rtn = [dbo].SP_REGISTRA_LOG
        @sprcd_usuario          = @cd_usuario,
        @sprcd_funcionalidade   = @cd_funcionalidade,
        @sprcd_unidade          = NULL,
        @sprtp_log              = @log_aviso,
        @sprnm_log              = 'Job de Integração do CCO',
        @sprds_log              = 'Processamento de medições concluído',
        @sprds_versao           = @ds_versao

    -- =====================================================
    -- Log fim
    -- =====================================================
    EXEC @rtn = [dbo].SP_REGISTRA_LOG
        @sprcd_usuario          = @cd_usuario,
        @sprcd_funcionalidade   = @cd_funcionalidade,
        @sprcd_unidade          = NULL,
        @sprtp_log              = @log_aviso,
        @sprnm_log              = 'Job de Integração do CCO',
        @sprds_log              = 'Fim',
        @sprds_versao           = @ds_versao

END TRY
BEGIN CATCH
    SET @sp_msg_erro = CAST(ERROR_NUMBER() AS VARCHAR) + ' - ' + ERROR_MESSAGE()

    EXEC @rtn = [dbo].SP_REGISTRA_LOG
        @sprcd_usuario          = @cd_usuario,
        @sprcd_funcionalidade   = @cd_funcionalidade,
        @sprcd_unidade          = NULL,
        @sprtp_log              = @log_erro,
        @sprnm_log              = 'Erro no Job de Integração do CCO',
        @sprds_log              = @sp_msg_erro,
        @sprds_versao           = @ds_versao

    RETURN -1
END CATCH
    
RETURN 0
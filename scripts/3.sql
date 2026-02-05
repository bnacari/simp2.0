-- ============================================================
-- SIMP - Fila de Reprocessamento Assíncrono de Métricas
-- ============================================================
-- Este script cria:
--   1. Tabela de fila para armazenar solicitações de reprocessamento
--   2. Stored Procedure para processar a fila
--   3. Job do SQL Server Agent que executa a cada 2 minutos
-- ============================================================

USE [SIMP]
GO

-- ============================================================
-- PARTE 1: CRIAR TABELA DE FILA
-- ============================================================

PRINT 'Criando tabela FILA_REPROCESSAMENTO_METRICAS...';

IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'FILA_REPROCESSAMENTO_METRICAS')
BEGIN
    CREATE TABLE [dbo].[FILA_REPROCESSAMENTO_METRICAS] (
        [ID] INT IDENTITY(1,1) PRIMARY KEY,
        [DT_REFERENCIA] DATE NOT NULL,
        [DS_ORIGEM] VARCHAR(100) NULL,          -- 'VALIDACAO', 'IMPORTACAO', 'EXCLUSAO', etc.
        [CD_USUARIO] INT NULL,                   -- Quem solicitou
        [DT_SOLICITACAO] DATETIME DEFAULT GETDATE(),
        [DT_PROCESSAMENTO] DATETIME NULL,        -- Quando foi processado
        [ID_STATUS] TINYINT DEFAULT 0,           -- 0=Pendente, 1=Processando, 2=Concluído, 9=Erro
        [DS_ERRO] VARCHAR(500) NULL,             -- Mensagem de erro se houver
        [NR_TENTATIVAS] TINYINT DEFAULT 0        -- Contador de tentativas
    );
    
    -- Índices
    CREATE INDEX IX_FILA_STATUS_DATA ON [dbo].[FILA_REPROCESSAMENTO_METRICAS] (ID_STATUS, DT_SOLICITACAO);
    CREATE INDEX IX_FILA_DT_REF ON [dbo].[FILA_REPROCESSAMENTO_METRICAS] (DT_REFERENCIA, ID_STATUS);
    
    PRINT '  - Tabela criada com sucesso.';
END
ELSE
BEGIN
    PRINT '  - Tabela já existe.';
END
GO

-- ============================================================
-- PARTE 2: PROCEDURE PARA ADICIONAR À FILA (evita duplicatas)
-- ============================================================

PRINT 'Criando SP_ENFILEIRAR_REPROCESSAMENTO...';
GO

CREATE OR ALTER PROCEDURE [dbo].[SP_ENFILEIRAR_REPROCESSAMENTO]
    @DT_REFERENCIA DATE,
    @DS_ORIGEM VARCHAR(100) = NULL,
    @CD_USUARIO INT = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Só adiciona se não existir pendente para a mesma data
    IF NOT EXISTS (
        SELECT 1 
        FROM [dbo].[FILA_REPROCESSAMENTO_METRICAS]
        WHERE DT_REFERENCIA = @DT_REFERENCIA
          AND ID_STATUS IN (0, 1)  -- Pendente ou Processando
    )
    BEGIN
        INSERT INTO [dbo].[FILA_REPROCESSAMENTO_METRICAS] 
            (DT_REFERENCIA, DS_ORIGEM, CD_USUARIO)
        VALUES 
            (@DT_REFERENCIA, @DS_ORIGEM, @CD_USUARIO);
    END
END
GO

PRINT '  - SP_ENFILEIRAR_REPROCESSAMENTO criada.';
GO

-- ============================================================
-- PARTE 3: PROCEDURE PARA PROCESSAR A FILA
-- ============================================================

PRINT 'Criando SP_PROCESSAR_FILA_METRICAS...';
GO

CREATE OR ALTER PROCEDURE [dbo].[SP_PROCESSAR_FILA_METRICAS]
    @MAX_ITENS INT = 10,           -- Máximo de itens por execução
    @MAX_TENTATIVAS INT = 3        -- Máximo de tentativas por item
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @ID INT;
    DECLARE @DT_REFERENCIA DATE;
    DECLARE @PROCESSADOS INT = 0;
    DECLARE @ERROS INT = 0;
    
    -- Cursor para processar itens pendentes (mais antigos primeiro)
    DECLARE cur CURSOR LOCAL FAST_FORWARD FOR
        SELECT TOP (@MAX_ITENS) ID, DT_REFERENCIA
        FROM [dbo].[FILA_REPROCESSAMENTO_METRICAS]
        WHERE ID_STATUS = 0                      -- Pendente
          AND NR_TENTATIVAS < @MAX_TENTATIVAS    -- Não excedeu tentativas
        ORDER BY DT_SOLICITACAO ASC;
    
    OPEN cur;
    FETCH NEXT FROM cur INTO @ID, @DT_REFERENCIA;
    
    WHILE @@FETCH_STATUS = 0
    BEGIN
        BEGIN TRY
            -- Marcar como processando
            UPDATE [dbo].[FILA_REPROCESSAMENTO_METRICAS]
            SET ID_STATUS = 1,
                NR_TENTATIVAS = NR_TENTATIVAS + 1
            WHERE ID = @ID;
            
            -- Executar reprocessamento
            EXEC [dbo].[SP_PROCESSAR_MEDICAO_V2] @DT_PROCESSAMENTO = @DT_REFERENCIA;
            
            -- Marcar como concluído
            UPDATE [dbo].[FILA_REPROCESSAMENTO_METRICAS]
            SET ID_STATUS = 2,
                DT_PROCESSAMENTO = GETDATE(),
                DS_ERRO = NULL
            WHERE ID = @ID;
            
            SET @PROCESSADOS = @PROCESSADOS + 1;
            
        END TRY
        BEGIN CATCH
            -- Marcar erro (volta para pendente se não excedeu tentativas)
            UPDATE [dbo].[FILA_REPROCESSAMENTO_METRICAS]
            SET ID_STATUS = CASE 
                    WHEN NR_TENTATIVAS >= @MAX_TENTATIVAS THEN 9  -- Erro definitivo
                    ELSE 0  -- Volta para pendente
                END,
                DS_ERRO = LEFT(ERROR_MESSAGE(), 500)
            WHERE ID = @ID;
            
            SET @ERROS = @ERROS + 1;
        END CATCH
        
        FETCH NEXT FROM cur INTO @ID, @DT_REFERENCIA;
    END
    
    CLOSE cur;
    DEALLOCATE cur;
    
    -- Retornar resumo
    SELECT 
        @PROCESSADOS AS PROCESSADOS,
        @ERROS AS ERROS,
        (SELECT COUNT(*) FROM [dbo].[FILA_REPROCESSAMENTO_METRICAS] WHERE ID_STATUS = 0) AS PENDENTES;
END
GO

PRINT '  - SP_PROCESSAR_FILA_METRICAS criada.';
GO

-- ============================================================
-- PARTE 4: LIMPEZA AUTOMÁTICA (registros antigos)
-- ============================================================

PRINT 'Criando SP_LIMPAR_FILA_METRICAS...';
GO

CREATE OR ALTER PROCEDURE [dbo].[SP_LIMPAR_FILA_METRICAS]
    @DIAS_MANTER INT = 7   -- Manter registros dos últimos N dias
AS
BEGIN
    SET NOCOUNT ON;
    
    DELETE FROM [dbo].[FILA_REPROCESSAMENTO_METRICAS]
    WHERE ID_STATUS IN (2, 9)  -- Concluído ou Erro definitivo
      AND DT_SOLICITACAO < DATEADD(DAY, -@DIAS_MANTER, GETDATE());
    
    SELECT @@ROWCOUNT AS REGISTROS_REMOVIDOS;
END
GO

PRINT '  - SP_LIMPAR_FILA_METRICAS criada.';
GO

-- ============================================================
-- PARTE 5: CRIAR JOB DO SQL SERVER AGENT
-- ============================================================

PRINT '';
PRINT 'Criando Job do SQL Server Agent...';

USE [msdb]
GO

-- Remover job existente se houver
IF EXISTS (SELECT 1 FROM msdb.dbo.sysjobs WHERE name = 'SIMP_Processar_Fila_Metricas')
BEGIN
    EXEC msdb.dbo.sp_delete_job @job_name = 'SIMP_Processar_Fila_Metricas', @delete_unused_schedule = 1;
    PRINT '  - Job anterior removido.';
END

-- Criar o job
DECLARE @jobId BINARY(16);

EXEC msdb.dbo.sp_add_job 
    @job_name = N'SIMP_Processar_Fila_Metricas',
    @enabled = 1,
    @description = N'Processa a fila de reprocessamento de métricas do SIMP a cada 2 minutos.',
    @category_name = N'[Uncategorized (Local)]',
    @owner_login_name = N'sa',
    @job_id = @jobId OUTPUT;

PRINT '  - Job criado.';

-- Adicionar step
EXEC msdb.dbo.sp_add_jobstep 
    @job_id = @jobId,
    @step_name = N'Processar Fila',
    @step_id = 1,
    @cmdexec_success_code = 0,
    @on_success_action = 3,  -- Ir para próximo step
    @on_fail_action = 2,     -- Falhar job
    @retry_attempts = 0,
    @retry_interval = 0,
    @subsystem = N'TSQL',
    @command = N'EXEC [SIMP].[dbo].[SP_PROCESSAR_FILA_METRICAS] @MAX_ITENS = 10;',
    @database_name = N'SIMP';

PRINT '  - Step 1 (Processar Fila) adicionado.';

-- Adicionar step de limpeza (roda após o principal)
EXEC msdb.dbo.sp_add_jobstep 
    @job_id = @jobId,
    @step_name = N'Limpar Registros Antigos',
    @step_id = 2,
    @cmdexec_success_code = 0,
    @on_success_action = 1,  -- Sucesso
    @on_fail_action = 1,     -- Sucesso mesmo se falhar (não é crítico)
    @retry_attempts = 0,
    @retry_interval = 0,
    @subsystem = N'TSQL',
    @command = N'EXEC [SIMP].[dbo].[SP_LIMPAR_FILA_METRICAS] @DIAS_MANTER = 7;',
    @database_name = N'SIMP';

PRINT '  - Step 2 (Limpeza) adicionado.';

-- Criar schedule (a cada 2 minutos)
EXEC msdb.dbo.sp_add_jobschedule 
    @job_id = @jobId,
    @name = N'A cada 2 minutos',
    @enabled = 1,
    @freq_type = 4,              -- Diário
    @freq_interval = 1,          -- Todo dia
    @freq_subday_type = 4,       -- Minutos
    @freq_subday_interval = 2,   -- A cada 2 minutos
    @active_start_date = 20240101,
    @active_start_time = 0;      -- 00:00:00

PRINT '  - Schedule configurado (a cada 2 minutos).';

-- Associar ao servidor local
EXEC msdb.dbo.sp_add_jobserver 
    @job_id = @jobId,
    @server_name = N'(local)';

PRINT '  - Job associado ao servidor.';

GO

-- ============================================================
-- RESUMO E VERIFICAÇÃO
-- ============================================================

USE [SIMP]
GO

PRINT '';
PRINT '================================================';
PRINT 'INSTALACAO CONCLUIDA';
PRINT '================================================';
PRINT '';
PRINT 'Objetos criados:';
PRINT '  - Tabela: FILA_REPROCESSAMENTO_METRICAS';
PRINT '  - SP: SP_ENFILEIRAR_REPROCESSAMENTO';
PRINT '  - SP: SP_PROCESSAR_FILA_METRICAS';
PRINT '  - SP: SP_LIMPAR_FILA_METRICAS';
PRINT '  - Job: SIMP_Processar_Fila_Metricas (a cada 2 min)';
PRINT '';
PRINT 'Como usar no PHP:';
PRINT '  $stmt = $pdo->prepare("EXEC SP_ENFILEIRAR_REPROCESSAMENTO ?, ?, ?");';
PRINT '  $stmt->execute([$data, "VALIDACAO", $cdUsuario]);';
PRINT '';
PRINT 'Para verificar a fila:';
PRINT '  SELECT * FROM FILA_REPROCESSAMENTO_METRICAS ORDER BY DT_SOLICITACAO DESC;';
PRINT '';
PRINT 'Para processar manualmente:';
PRINT '  EXEC SP_PROCESSAR_FILA_METRICAS;';
PRINT '';

-- Mostrar status atual
SELECT 
    ID_STATUS,
    CASE ID_STATUS 
        WHEN 0 THEN 'Pendente'
        WHEN 1 THEN 'Processando'
        WHEN 2 THEN 'Concluido'
        WHEN 9 THEN 'Erro'
    END AS STATUS,
    COUNT(*) AS QTD
FROM [dbo].[FILA_REPROCESSAMENTO_METRICAS]
GROUP BY ID_STATUS;
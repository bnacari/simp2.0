<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Executar Integração CCO - Ponto de Medição
 * Executa a stored procedure SP_INTEGRACAO_CCO_BODY_PONTO_MEDICAO
 * 
 * Compatível com PHP 8.3+
 */

// Inicializar variáveis globais ANTES de qualquer coisa
$phpErrors = [];
$debugInfo = [];
$listaPontos = null;

// Output buffering para capturar qualquer saída indesejada (ex: warnings, notices)
ob_start();

header('Content-Type: application/json; charset=utf-8');

// PHP 8.3: Registrar shutdown function para capturar fatal errors (TypeError, ValueError, etc.)
// set_error_handler NÃO captura erros fatais — apenas register_shutdown_function faz isso
register_shutdown_function(function() use (&$phpErrors, &$debugInfo, &$listaPontos) {
    $error = error_get_last();
    // Tipos de erro fatal que set_error_handler não captura
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        // Limpar qualquer output anterior (HTML de erro, etc.)
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        // Forçar header JSON (pode ter sido sobrescrito pelo conexao.php)
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro fatal no servidor: ' . $error['message'],
            'erros_php' => $phpErrors,
            'debug' => array_merge($debugInfo, [
                'fatal_error' => [
                    'type' => $error['type'],
                    'message' => $error['message'],
                    'file' => $error['file'],
                    'line' => $error['line']
                ]
            ])
        ]);
    }
});

// PHP 8.3: set_error_handler com type hints e retorno bool explícito
set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline) use (&$phpErrors): bool {
    $phpErrors[] = "[$errno] $errstr em $errfile:$errline";
    return true;
});

ini_set('display_errors', '0');
error_reporting(E_ALL);

try {
    // Verificação de autenticação e permissão
    require_once '../verificarAuth.php';
    require_once '../logHelper.php';

    // Verifica permissão de administração
    verificarPermissaoAjax('CADASTROS ADMINISTRATIVOS', ACESSO_ESCRITA);

    include_once '../conexao.php';

    // IMPORTANTE: conexao.php sobrescreve o Content-Type para text/html
    // Precisamos re-definir para JSON após o include
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    // Verificar se a conexão foi estabelecida
    if (!isset($pdoSIMP) || !($pdoSIMP instanceof PDO)) {
        throw new Exception('Conexão com banco de dados não estabelecida');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    // Captura e valida os pontos de medição
    // PHP 8.3: cast explícito para string + null coalescing
    $pontosInput = trim((string)($_POST['pontos'] ?? ''));
    
    if ($pontosInput === '') {
        throw new Exception('Informe pelo menos um código de ponto de medição');
    }

    // Limpa e valida os pontos (aceita separação por vírgula)
    $pontos = array_filter(array_map('trim', explode(',', $pontosInput)));
    
    if (empty($pontos)) {
        throw new Exception('Nenhum ponto de medição válido informado');
    }

    // Validar que todos são numéricos ou strings válidas
    foreach ($pontos as $ponto) {
        if ($ponto === '') {
            throw new Exception('Código de ponto de medição inválido');
        }
    }

    // Monta a lista de pontos formatada
    $listaPontos = implode(',', $pontos);

    // Parâmetros da stored procedure
    $idTipoLeitura = 8;
    // PHP 8.3: cast explícito para int, com fallback seguro
    $cdUsuario = isset($_SESSION['cd_usuario']) ? (int)$_SESSION['cd_usuario'] : 100;
    $cdFuncionalidade = 1;
    $dsVersao = '1.0';

    // Debug: capturar informações
    $debugInfo = [
        'pontos_input' => $pontosInput,
        'pontos_processados' => $listaPontos,
        'id_tipo_leitura' => $idTipoLeitura,
        'cd_usuario' => $cdUsuario,
        'cd_funcionalidade' => $cdFuncionalidade,
        'ds_versao' => $dsVersao
    ];

    // Escapar valores para evitar SQL injection
    $listaPontosEscapado = $pdoSIMP->quote($listaPontos);
    $dsVersaoEscapado = $pdoSIMP->quote($dsVersao);

    $sql = "
        SET NOCOUNT ON;
        
        DECLARE @msg VARCHAR(4000);
        DECLARE @resultado TABLE (
            linha INT IDENTITY(1,1),
            mensagem VARCHAR(MAX)
        );
        
        DECLARE @old_ansi_warnings BIT = 0;
        
        BEGIN TRY
            EXEC SP_INTEGRACAO_CCO_BODY_PONTO_MEDICAO 
                @id_tipo_leitura = {$idTipoLeitura},
                @cd_usuario = {$cdUsuario},
                @cd_funcionalidade = {$cdFuncionalidade},
                @ds_versao = {$dsVersaoEscapado},
                @sp_msg_erro = @msg OUTPUT,
                @now = NULL,
                @p_cd_ponto_medicao = {$listaPontosEscapado};
                
            SELECT 
                'sucesso' AS status,
                ISNULL(@msg, 'Processo executado') AS mensagem_erro,
                {$listaPontosEscapado} AS pontos_processados;
        END TRY
        BEGIN CATCH
            SELECT 
                'erro' AS status,
                ERROR_MESSAGE() AS mensagem_erro,
                {$listaPontosEscapado} AS pontos_processados;
        END CATCH
    ";

    $debugInfo['sql_executado'] = $sql;

    $stmt = $pdoSIMP->query($sql);

    // PHP 8.3: fetch() retorna false quando não há resultado — verificar antes de acessar
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $debugInfo['resultado_raw'] = $resultado;

    // PHP 8.3: verificar explicitamente se resultado é array válido
    if (!is_array($resultado)) {
        $status = 'desconhecido';
        $mensagemErro = 'Nenhum resultado retornado pela stored procedure';
        $pontosProcessados = $listaPontos;
    } else {
        $status = (string)($resultado['status'] ?? 'desconhecido');
        $mensagemErro = isset($resultado['mensagem_erro']) ? (string)$resultado['mensagem_erro'] : null;
        $pontosProcessados = (string)($resultado['pontos_processados'] ?? $listaPontos);
    }

    // Registrar log (isolado para não impactar resposta)
    try {
        if (function_exists('registrarLog')) {
            registrarLog(
                $pdoSIMP,
                'INTEGRAÇÃO CCO',
                'EXECUTAR',
                "Pontos: $listaPontos",
                $status === 'erro' ? "Erro: $mensagemErro" : "Sucesso: $mensagemErro",
                null
            );
        }
    } catch (\Throwable $logEx) {
        $phpErrors[] = 'Erro ao registrar log: ' . $logEx->getMessage();
    }

    // Limpar output buffer antes de enviar JSON
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    if ($status === 'erro') {
        echo json_encode([
            'sucesso' => false,
            'mensagem' => $mensagemErro,
            'pontos' => $listaPontos,
            'debug' => $debugInfo
        ]);
    } else {
        // Verificar se houve erro na mensagem de output
        $temErro = !empty($mensagemErro) && (
            stripos($mensagemErro, 'erro') !== false || 
            stripos($mensagemErro, 'error') !== false ||
            stripos($mensagemErro, 'falha') !== false
        );
        
        echo json_encode([
            'sucesso' => !$temErro,
            'mensagem' => $temErro 
                ? $mensagemErro 
                : "Integração executada para os pontos: $pontosProcessados. Retorno: " . ($mensagemErro ?: 'OK'),
            'pontos' => $listaPontos,
            'debug' => $debugInfo
        ]);
    }

} catch (PDOException $e) {
    // Limpar output buffer
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    // Forçar header JSON (conexao.php pode ter sobrescrito para text/html)
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    // Registrar erro no log (isolado)
    try {
        if (isset($pdoSIMP) && $pdoSIMP instanceof PDO && function_exists('registrarLog')) {
            registrarLog(
                $pdoSIMP,
                'INTEGRAÇÃO CCO',
                'ERRO',
                "Pontos: " . ($listaPontos ?? 'N/A'),
                $e->getMessage(),
                null
            );
        }
    } catch (\Throwable $logEx) {
        // Silencioso — não impactar resposta principal
    }
    
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro de banco de dados: ' . $e->getMessage(),
        'erros_php' => $phpErrors,
        'debug' => $debugInfo
    ]);

} catch (\Throwable $e) {
    // PHP 8.3: \Throwable captura tanto Exception quanto Error (TypeError, ValueError, etc.)
    // Limpar output buffer
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    // Forçar header JSON
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'sucesso' => false,
        'mensagem' => $e->getMessage(),
        'erros_php' => $phpErrors,
        'debug' => array_merge($debugInfo, [
            'exception_class' => get_class($e),
            'exception_file' => $e->getFile(),
            'exception_line' => $e->getLine()
        ])
    ]);
}

// Restaurar handler de erros
restore_error_handler();
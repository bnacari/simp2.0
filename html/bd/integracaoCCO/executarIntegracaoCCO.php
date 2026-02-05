<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Executar Integração CCO - Ponto de Medição
 * Executa a stored procedure SP_INTEGRACAO_CCO_BODY_PONTO_MEDICAO
 */

header('Content-Type: application/json; charset=utf-8');

// Capturar erros e warnings
$phpErrors = [];
set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$phpErrors) {
    $phpErrors[] = "[$errno] $errstr em $errfile:$errline";
    return true;
});

ini_set('display_errors', 0);
error_reporting(E_ALL);

// Verificação de autenticação e permissão
require_once '../verificarAuth.php';
require_once '../logHelper.php';

// Verifica permissão de administração
verificarPermissaoAjax('CADASTROS ADMINISTRATIVOS', ACESSO_ESCRITA);

include_once '../conexao.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    // Captura e valida os pontos de medição
    $pontosInput = isset($_POST['pontos']) ? trim($_POST['pontos']) : '';
    
    if (empty($pontosInput)) {
        throw new Exception('Informe pelo menos um código de ponto de medição');
    }

    // Limpa e valida os pontos (aceita separação por vírgula)
    $pontos = array_filter(array_map('trim', explode(',', $pontosInput)));
    
    if (empty($pontos)) {
        throw new Exception('Nenhum ponto de medição válido informado');
    }

    // Validar que todos são numéricos ou strings válidas
    foreach ($pontos as $ponto) {
        if (empty($ponto)) {
            throw new Exception('Código de ponto de medição inválido');
        }
    }

    // Monta a lista de pontos formatada
    $listaPontos = implode(',', $pontos);

    // Parâmetros da stored procedure
    $idTipoLeitura = 8;
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

    // Monta a query usando query direta (sem parâmetros nomeados para múltiplos statements)
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
        
        -- Captura mensagens de PRINT
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
    
    // Busca o resultado
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $debugInfo['resultado_raw'] = $resultado;
    
    $status = $resultado['status'] ?? 'desconhecido';
    $mensagemErro = $resultado['mensagem_erro'] ?? null;
    $pontosProcessados = $resultado['pontos_processados'] ?? $listaPontos;

    // Registrar log
    registrarLog(
        $pdoSIMP,
        'INTEGRAÇÃO CCO',
        'EXECUTAR',
        "Pontos: $listaPontos",
        $status === 'erro' ? "Erro: $mensagemErro" : "Sucesso: $mensagemErro",
        null
    );

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
    // Registrar erro no log
    if (isset($pdoSIMP)) {
        registrarLog(
            $pdoSIMP,
            'INTEGRAÇÃO CCO',
            'ERRO',
            "Pontos: " . ($listaPontos ?? 'N/A'),
            $e->getMessage(),
            null
        );
    }
    
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro de banco de dados: ' . $e->getMessage(),
        'erros_php' => $phpErrors,
        'debug' => $debugInfo ?? []
    ]);
} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => $e->getMessage(),
        'erros_php' => $phpErrors,
        'debug' => $debugInfo ?? []
    ]);
}

// Restaurar handler de erros
restore_error_handler();

// Restaurar handler de erros
restore_error_handler();

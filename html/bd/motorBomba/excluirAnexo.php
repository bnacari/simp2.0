<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Excluir Anexo de Conjunto Motor-Bomba
 * 
 * Utiliza tabela genérica SIMP.dbo.ANEXO
 * 
 * @author SIMP
 * @version 2.0
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
@include_once '../logHelper.php';
include_once '../conexao.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    $cdAnexo = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($cdAnexo <= 0) {
        throw new Exception('ID do anexo não informado');
    }

    // Buscar dados do anexo antes de excluir (para log)
    $sqlBusca = "SELECT CD_ANEXO, DS_NOME, DS_FILENAME, CD_CHAVE_FUNCIONALIDADE 
                 FROM SIMP.dbo.ANEXO 
                 WHERE CD_ANEXO = :id";
    $stmtBusca = $pdoSIMP->prepare($sqlBusca);
    $stmtBusca->execute([':id' => $cdAnexo]);
    $dadosAnexo = $stmtBusca->fetch(PDO::FETCH_ASSOC);

    if (!$dadosAnexo) {
        throw new Exception('Anexo não encontrado');
    }

    // Excluir do banco
    $sql = "DELETE FROM SIMP.dbo.ANEXO WHERE CD_ANEXO = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $cdAnexo]);

    // Registrar log
    if (function_exists('registrarLogDelete')) {
        try {
            registrarLogDelete('Conjunto Motor-Bomba', 'Anexo', $cdAnexo, $dadosAnexo['DS_FILENAME'], [
                'CD_ANEXO' => $cdAnexo,
                'DS_FILENAME' => $dadosAnexo['DS_FILENAME'],
                'DS_NOME' => $dadosAnexo['DS_NOME'],
                'CD_CONJUNTO_MOTOR_BOMBA' => $dadosAnexo['CD_CHAVE_FUNCIONALIDADE']
            ]);
        } catch (Exception $logEx) {
            error_log('Erro ao registrar log de exclusão anexo: ' . $logEx->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Anexo "' . $dadosAnexo['DS_FILENAME'] . '" excluído com sucesso!'
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // Registrar log de erro
    if (function_exists('registrarLogErro')) {
        try {
            registrarLogErro('Conjunto Motor-Bomba', 'DELETE_ANEXO', $e->getMessage(), [
                'cd_anexo' => $cdAnexo ?? null
            ]);
        } catch (Exception $logEx) {}
    }

    echo json_encode([
        'success' => false,
        'message' => 'Erro no banco de dados: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
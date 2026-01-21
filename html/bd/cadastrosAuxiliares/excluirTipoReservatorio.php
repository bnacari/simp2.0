<?php
/**
 * SIMP - Excluir Tipo de Reservatório (com Log)
 */
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
require_once '../logHelper.php';

verificarPermissaoAjax('CADASTRO', ACESSO_ESCRITA);

include_once '../conexao.php';

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if (!$id) {
        throw new Exception('ID não informado');
    }
    
    // Buscar dados antes de excluir
    $sqlBusca = "SELECT * FROM SIMP.dbo.TIPO_RESERVATORIO WHERE CD_CHAVE = :id";
    $stmtBusca = $pdoSIMP->prepare($sqlBusca);
    $stmtBusca->execute([':id' => $id]);
    $dadosExcluidos = $stmtBusca->fetch(PDO::FETCH_ASSOC);
    
    if (!$dadosExcluidos) {
        throw new Exception('Registro não encontrado');
    }
    
    $identificador = $dadosExcluidos['NOME'] ?? "ID: $id";
    
    // Excluir
    $sql = "DELETE FROM SIMP.dbo.TIPO_RESERVATORIO WHERE CD_CHAVE = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    registrarLogDelete('Cadastros Auxiliares', 'Tipo de Reservatório', $id, $identificador, $dadosExcluidos);
    
    echo json_encode(['success' => true, 'message' => 'Registro excluído com sucesso!']);
    
} catch (Exception $e) {
    registrarLogErro('Cadastros Auxiliares', 'DELETE', $e->getMessage(), ['id' => $id ?? null]);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
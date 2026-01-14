<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
verificarPermissaoAjax('CADASTRO', ACESSO_ESCRITA);

include_once '../conexao.php';

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if (!$id) throw new Exception('ID não informado');
    
    $sql = "DELETE FROM SIMP.dbo.TIPO_RESERVATORIO WHERE CD_CHAVE = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    echo json_encode(['success' => true, 'message' => 'Registro excluído com sucesso!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

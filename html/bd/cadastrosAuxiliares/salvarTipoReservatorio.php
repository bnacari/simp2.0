<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
verificarPermissaoAjax('CADASTRO', ACESSO_ESCRITA);

include_once '../conexao.php';

try {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    
    if (empty($nome)) throw new Exception('Nome é obrigatório');
    
    if ($id) {
        $sql = "UPDATE SIMP.dbo.TIPO_RESERVATORIO SET NOME = :nome WHERE CD_CHAVE = :id";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':nome' => $nome, ':id' => $id]);
        $msg = 'Tipo de reservatório atualizado com sucesso!';
    } else {
        $sql = "INSERT INTO SIMP.dbo.TIPO_RESERVATORIO (NOME) VALUES (:nome)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':nome' => $nome]);
        $msg = 'Tipo de reservatório cadastrado com sucesso!';
    }
    
    echo json_encode(['success' => true, 'message' => $msg]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

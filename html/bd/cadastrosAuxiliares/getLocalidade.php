<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
verificarPermissaoAjax('CADASTRO', ACESSO_LEITURA);

include_once '../conexao.php';

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$id) throw new Exception('ID não informado');
    
    $sql = "SELECT CD_CHAVE, CD_UNIDADE, DS_NOME, CD_LOCALIDADE, CD_ENTIDADE_VALOR_ID 
            FROM SIMP.dbo.LOCALIDADE WHERE CD_CHAVE = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($dados) {
        echo json_encode(['success' => true, 'data' => $dados]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

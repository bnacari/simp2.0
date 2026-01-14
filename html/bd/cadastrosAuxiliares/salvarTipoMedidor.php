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
    $tipoCalculo = isset($_POST['tipo_calculo']) ? (int)$_POST['tipo_calculo'] : null;
    
    if (empty($nome)) {
        throw new Exception('Nome é obrigatório');
    }
    
    if (empty($tipoCalculo)) {
        throw new Exception('Tipo de cálculo é obrigatório');
    }
    
    if ($id) {
        // UPDATE
        $sql = "UPDATE SIMP.dbo.TIPO_MEDIDOR SET DS_NOME = :nome, ID_TIPO_CALCULO = :tipo WHERE CD_CHAVE = :id";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':nome' => $nome, ':tipo' => $tipoCalculo, ':id' => $id]);
        $mensagem = 'Tipo de medidor atualizado com sucesso!';
    } else {
        // INSERT
        $sql = "INSERT INTO SIMP.dbo.TIPO_MEDIDOR (DS_NOME, ID_TIPO_CALCULO) VALUES (:nome, :tipo)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':nome' => $nome, ':tipo' => $tipoCalculo]);
        $mensagem = 'Tipo de medidor cadastrado com sucesso!';
    }
    
    echo json_encode(['success' => true, 'message' => $mensagem]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

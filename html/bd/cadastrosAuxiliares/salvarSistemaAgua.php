<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
verificarPermissaoAjax('CADASTRO', ACESSO_ESCRITA);

include_once '../conexao.php';

try {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $cdLocalidade = isset($_POST['cd_localidade']) ? trim($_POST['cd_localidade']) : '';
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $descricao = isset($_POST['descricao']) ? trim($_POST['descricao']) : '';
    $cdUsuario = getIdUsuario();
    
    if (empty($cdLocalidade)) throw new Exception('Código da localidade é obrigatório');
    if (empty($nome)) throw new Exception('Nome é obrigatório');
    
    if ($id) {
        $sql = "UPDATE SIMP.dbo.SISTEMA_AGUA 
                SET CD_LOCALIDADE = :codigo, DS_NOME = :nome, DS_DESCRICAO = :descricao,
                    DT_ULTIMA_ATUALIZACAO = GETDATE(), CD_USUARIO_ULTIMA_ATUALIZACAO = :usuario
                WHERE CD_CHAVE = :id";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':codigo' => $cdLocalidade, 
            ':nome' => $nome, 
            ':descricao' => $descricao,
            ':usuario' => $cdUsuario,
            ':id' => $id
        ]);
        $msg = 'Sistema de água atualizado com sucesso!';
    } else {
        $sql = "INSERT INTO SIMP.dbo.SISTEMA_AGUA (CD_LOCALIDADE, DS_NOME, DS_DESCRICAO, DT_ULTIMA_ATUALIZACAO, CD_USUARIO_ULTIMA_ATUALIZACAO) 
                VALUES (:codigo, :nome, :descricao, GETDATE(), :usuario)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':codigo' => $cdLocalidade, 
            ':nome' => $nome, 
            ':descricao' => $descricao,
            ':usuario' => $cdUsuario
        ]);
        $msg = 'Sistema de água cadastrado com sucesso!';
    }
    
    echo json_encode(['success' => true, 'message' => $msg]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

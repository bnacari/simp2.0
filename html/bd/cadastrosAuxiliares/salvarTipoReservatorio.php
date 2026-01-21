<?php
/**
 * SIMP - Salvar Tipo de Reservatório (com Log)
 */
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
require_once '../logHelper.php';

verificarPermissaoAjax('CADASTRO', ACESSO_ESCRITA);

include_once '../conexao.php';

try {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    
    if (empty($nome)) {
        throw new Exception('Nome é obrigatório');
    }
    
    if ($id) {
        // Buscar dados anteriores
        $sqlAnterior = "SELECT NOME FROM SIMP.dbo.TIPO_RESERVATORIO WHERE CD_CHAVE = :id";
        $stmtAnterior = $pdoSIMP->prepare($sqlAnterior);
        $stmtAnterior->execute([':id' => $id]);
        $dadosAnteriores = $stmtAnterior->fetch(PDO::FETCH_ASSOC);
        
        // UPDATE
        $sql = "UPDATE SIMP.dbo.TIPO_RESERVATORIO SET NOME = :nome WHERE CD_CHAVE = :id";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':nome' => $nome, ':id' => $id]);
        
        registrarLogUpdate('Cadastros Auxiliares', 'Tipo de Reservatório', $id, $nome, 
            ['anterior' => $dadosAnteriores, 'novo' => ['NOME' => $nome]]);
        
        $mensagem = 'Tipo de reservatório atualizado com sucesso!';
    } else {
        // INSERT
        $sql = "INSERT INTO SIMP.dbo.TIPO_RESERVATORIO (NOME) VALUES (:nome)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':nome' => $nome]);
        
        $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID");
        $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['ID'];
        
        registrarLogInsert('Cadastros Auxiliares', 'Tipo de Reservatório', $novoId, $nome, ['NOME' => $nome]);
        
        $mensagem = 'Tipo de reservatório cadastrado com sucesso!';
    }
    
    echo json_encode(['success' => true, 'message' => $mensagem]);
    
} catch (Exception $e) {
    registrarLogErro('Cadastros Auxiliares', $id ? 'UPDATE' : 'INSERT', $e->getMessage(), $_POST);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
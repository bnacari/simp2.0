<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
verificarPermissaoAjax('CADASTRO', ACESSO_ESCRITA);

include_once '../conexao.php';
@include_once '../logHelper.php';

try {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $cdLocalidade = isset($_POST['cd_localidade']) && $_POST['cd_localidade'] !== '' ? (int)$_POST['cd_localidade'] : null;
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $descricao = isset($_POST['descricao']) ? trim($_POST['descricao']) : null;
    $cdUsuario = isset($_SESSION['cd_usuario']) ? (int)$_SESSION['cd_usuario'] : null;
    
    if (empty($cdLocalidade)) throw new Exception('Localidade é obrigatória');
    if (empty($nome)) throw new Exception('Nome é obrigatório');
    
    if ($id) {
        $dadosAnteriores = null;
        try { $stmtAnt = $pdoSIMP->prepare("SELECT CD_LOCALIDADE, DS_NOME, DS_DESCRICAO FROM SIMP.dbo.SISTEMA_AGUA WHERE CD_CHAVE = :id"); $stmtAnt->execute([':id' => $id]); $dadosAnteriores = $stmtAnt->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
        
        $sql = "UPDATE SIMP.dbo.SISTEMA_AGUA SET CD_LOCALIDADE = :localidade, DS_NOME = :nome, DS_DESCRICAO = :descricao, DT_ULTIMA_ATUALIZACAO = GETDATE(), CD_USUARIO_ULTIMA_ATUALIZACAO = :usuario WHERE CD_CHAVE = :id";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':localidade' => $cdLocalidade, ':nome' => $nome, ':descricao' => $descricao, ':usuario' => $cdUsuario, ':id' => $id]);
        
        if (function_exists('registrarLogUpdate')) { try { registrarLogUpdate('Cadastros Auxiliares', 'Sistema de Água', $id, $nome, ['anterior' => $dadosAnteriores, 'novo' => ['CD_LOCALIDADE' => $cdLocalidade, 'DS_NOME' => $nome, 'DS_DESCRICAO' => $descricao]]); } catch (Exception $e) {} }
        
        $msg = 'Sistema de água atualizado com sucesso!';
    } else {
        $sql = "INSERT INTO SIMP.dbo.SISTEMA_AGUA (CD_LOCALIDADE, DS_NOME, DS_DESCRICAO, DT_ULTIMA_ATUALIZACAO, CD_USUARIO_ULTIMA_ATUALIZACAO) VALUES (:localidade, :nome, :descricao, GETDATE(), :usuario)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':localidade' => $cdLocalidade, ':nome' => $nome, ':descricao' => $descricao, ':usuario' => $cdUsuario]);
        
        $novoId = null;
        try { $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID"); $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['ID']; } catch (Exception $e) {}
        
        if (function_exists('registrarLogInsert')) { try { registrarLogInsert('Cadastros Auxiliares', 'Sistema de Água', $novoId, $nome, ['CD_LOCALIDADE' => $cdLocalidade, 'DS_NOME' => $nome, 'DS_DESCRICAO' => $descricao]); } catch (Exception $e) {} }
        
        $msg = 'Sistema de água cadastrado com sucesso!';
    }
    
    echo json_encode(['success' => true, 'message' => $msg]);
} catch (Exception $e) {
    if (function_exists('registrarLogErro')) { try { registrarLogErro('Cadastros Auxiliares', 'SALVAR', $e->getMessage(), $_POST); } catch (Exception $ex) {} }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
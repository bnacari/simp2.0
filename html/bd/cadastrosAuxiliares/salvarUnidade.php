<?php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';
@include_once '../logHelper.php';

try {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $codigo = isset($_POST['codigo']) ? trim($_POST['codigo']) : '';
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    
    if (empty($codigo)) { echo json_encode(['success' => false, 'message' => 'O código é obrigatório']); exit; }
    if (empty($nome)) { echo json_encode(['success' => false, 'message' => 'O nome é obrigatório']); exit; }
    
    $sqlCheck = "SELECT CD_UNIDADE FROM SIMP.dbo.UNIDADE WHERE CD_CODIGO = :codigo";
    if ($id) $sqlCheck .= " AND CD_UNIDADE != :id";
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $stmtCheck->bindValue(':codigo', $codigo);
    if ($id) $stmtCheck->bindValue(':id', $id, PDO::PARAM_INT);
    $stmtCheck->execute();
    
    if ($stmtCheck->fetch()) { echo json_encode(['success' => false, 'message' => 'Já existe uma unidade com este código']); exit; }
    
    if ($id) {
        $dadosAnteriores = null;
        try { $stmtAnt = $pdoSIMP->prepare("SELECT CD_CODIGO, DS_NOME FROM SIMP.dbo.UNIDADE WHERE CD_UNIDADE = :id"); $stmtAnt->execute([':id' => $id]); $dadosAnteriores = $stmtAnt->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
        
        $sql = "UPDATE SIMP.dbo.UNIDADE SET CD_CODIGO = :codigo, DS_NOME = :nome WHERE CD_UNIDADE = :id";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':codigo' => $codigo, ':nome' => $nome, ':id' => $id]);
        
        if (function_exists('registrarLogUpdate')) { try { registrarLogUpdate('Cadastros Auxiliares', 'Unidade', $id, "$codigo - $nome", ['anterior' => $dadosAnteriores, 'novo' => ['CD_CODIGO' => $codigo, 'DS_NOME' => $nome]]); } catch (Exception $e) {} }
        
        echo json_encode(['success' => true, 'message' => 'Unidade atualizada com sucesso!']);
    } else {
        $sql = "INSERT INTO SIMP.dbo.UNIDADE (CD_CODIGO, DS_NOME) VALUES (:codigo, :nome)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':codigo' => $codigo, ':nome' => $nome]);
        
        $novoId = null;
        try { $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID"); $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['ID']; } catch (Exception $e) {}
        
        if (function_exists('registrarLogInsert')) { try { registrarLogInsert('Cadastros Auxiliares', 'Unidade', $novoId, "$codigo - $nome", ['CD_CODIGO' => $codigo, 'DS_NOME' => $nome]); } catch (Exception $e) {} }
        
        echo json_encode(['success' => true, 'message' => 'Unidade cadastrada com sucesso!']);
    }
} catch (PDOException $e) {
    if (function_exists('registrarLogErro')) { try { registrarLogErro('Cadastros Auxiliares', 'SALVAR', $e->getMessage(), $_POST); } catch (Exception $ex) {} }
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar unidade: ' . $e->getMessage()]);
}
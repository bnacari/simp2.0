<?php
// bd/grupoUsuario/salvarGrupoUsuario.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';
@include_once '../logHelper.php';

try {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    
    if (empty($nome)) {
        throw new Exception('Nome é obrigatório');
    }
    
    // Verificar duplicidade
    $sqlCheck = "SELECT CD_GRUPO_USUARIO FROM SIMP.dbo.GRUPO_USUARIO WHERE DS_NOME = :nome" . ($id ? " AND CD_GRUPO_USUARIO != :id" : "");
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $params = [':nome' => $nome];
    if ($id) $params[':id'] = $id;
    $stmtCheck->execute($params);
    
    if ($stmtCheck->fetch()) {
        throw new Exception('Já existe um grupo com este nome');
    }
    
    if ($id) {
        // Buscar dados anteriores para log
        $dadosAnteriores = null;
        try {
            $stmtAnt = $pdoSIMP->prepare("SELECT DS_NOME FROM SIMP.dbo.GRUPO_USUARIO WHERE CD_GRUPO_USUARIO = :id");
            $stmtAnt->execute([':id' => $id]);
            $dadosAnteriores = $stmtAnt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
        
        // UPDATE
        $sql = "UPDATE SIMP.dbo.GRUPO_USUARIO SET DS_NOME = :nome WHERE CD_GRUPO_USUARIO = :id";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':nome' => $nome, ':id' => $id]);
        $msg = 'Grupo de usuário atualizado com sucesso!';
        
        // Log (isolado)
        try {
            if (function_exists('registrarLogUpdate')) {
                registrarLogUpdate('Cadastros Administrativos', 'Grupo de Usuário', $id, $nome, 
                    ['anterior' => $dadosAnteriores, 'novo' => ['DS_NOME' => $nome]]);
            }
        } catch (Exception $logEx) {}
    } else {
        // INSERT - coluna CD_GRUPO_USUARIO é IDENTITY
        $sql = "INSERT INTO SIMP.dbo.GRUPO_USUARIO (DS_NOME) VALUES (:nome)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':nome' => $nome]);
        
        // Buscar o ID inserido
        $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID");
        $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['ID'];
        
        $msg = 'Grupo de usuário cadastrado com sucesso!';
        
        // Log (isolado)
        try {
            if (function_exists('registrarLogInsert')) {
                registrarLogInsert('Cadastros Administrativos', 'Grupo de Usuário', $novoId, $nome, 
                    ['DS_NOME' => $nome]);
            }
        } catch (Exception $logEx) {}
    }
    
    echo json_encode(['success' => true, 'message' => $msg]);

} catch (PDOException $e) {
    // Log de erro (isolado)
    try {
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Cadastros Administrativos', $id ? 'UPDATE' : 'INSERT', $e->getMessage(), 
                ['nome' => $nome ?? '', 'id' => $id ?? '']);
        }
    } catch (Exception $logEx) {}
    
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar grupo de usuário: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
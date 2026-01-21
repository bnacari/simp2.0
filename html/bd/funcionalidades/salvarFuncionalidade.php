<?php
// bd/funcionalidades/salvarFuncionalidade.php
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
    $sqlCheck = "SELECT CD_FUNCIONALIDADE FROM SIMP.dbo.FUNCIONALIDADE WHERE DS_NOME = :nome" . ($id ? " AND CD_FUNCIONALIDADE != :id" : "");
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $params = [':nome' => $nome];
    if ($id) $params[':id'] = $id;
    $stmtCheck->execute($params);
    
    if ($stmtCheck->fetch()) {
        throw new Exception('Já existe uma funcionalidade com este nome');
    }
    
    if ($id) {
        // Buscar dados anteriores para log
        $dadosAnteriores = null;
        try {
            $stmtAnt = $pdoSIMP->prepare("SELECT DS_NOME FROM SIMP.dbo.FUNCIONALIDADE WHERE CD_FUNCIONALIDADE = :id");
            $stmtAnt->execute([':id' => $id]);
            $dadosAnteriores = $stmtAnt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
        
        // UPDATE
        $sql = "UPDATE SIMP.dbo.FUNCIONALIDADE SET DS_NOME = :nome WHERE CD_FUNCIONALIDADE = :id";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':nome' => $nome, ':id' => $id]);
        $msg = 'Funcionalidade atualizada com sucesso!';
        
        // Log (isolado)
        try {
            if (function_exists('registrarLogUpdate')) {
                registrarLogUpdate('Cadastros Administrativos', 'Funcionalidade', $id, $nome, 
                    ['anterior' => $dadosAnteriores, 'novo' => ['DS_NOME' => $nome]]);
            }
        } catch (Exception $logEx) {}
    } else {
        // Buscar próximo ID
        $stmtMax = $pdoSIMP->query("SELECT ISNULL(MAX(CD_FUNCIONALIDADE), 0) + 1 AS NOVO_ID FROM SIMP.dbo.FUNCIONALIDADE");
        $novoId = $stmtMax->fetch(PDO::FETCH_ASSOC)['NOVO_ID'];
        
        // INSERT
        $sql = "INSERT INTO SIMP.dbo.FUNCIONALIDADE (CD_FUNCIONALIDADE, DS_NOME) VALUES (:id, :nome)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':id' => $novoId, ':nome' => $nome]);
        $msg = 'Funcionalidade cadastrada com sucesso!';
        
        // Log (isolado)
        try {
            if (function_exists('registrarLogInsert')) {
                registrarLogInsert('Cadastros Administrativos', 'Funcionalidade', $novoId, $nome, 
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
    
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar funcionalidade: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
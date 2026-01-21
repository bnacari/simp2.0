<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
verificarPermissaoAjax('CADASTRO', ACESSO_ESCRITA);

include_once '../conexao.php';

try {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $cdUnidade = isset($_POST['cd_unidade']) ? (int)$_POST['cd_unidade'] : null;
    $cdLocalidade = isset($_POST['cd_localidade']) ? trim($_POST['cd_localidade']) : '';
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    
    if (empty($cdUnidade)) throw new Exception('Unidade é obrigatória');
    if (empty($cdLocalidade)) throw new Exception('Código da localidade é obrigatório');
    if (empty($nome)) throw new Exception('Nome é obrigatório');
    
    if ($id) {
        $sql = "UPDATE SIMP.dbo.LOCALIDADE 
                SET CD_UNIDADE = :unidade, CD_LOCALIDADE = :codigo, DS_NOME = :nome 
                WHERE CD_CHAVE = :id";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':unidade' => $cdUnidade, ':codigo' => $cdLocalidade, ':nome' => $nome, ':id' => $id]);
        $msg = 'Localidade atualizada com sucesso!';
        
        // Log (isolado)
        try {
            @include_once '../logHelper.php';
            if (function_exists('registrarLogUpdate')) {
                registrarLogUpdate('Cadastros Auxiliares', 'Localidade', $id, "$cdLocalidade - $nome", 
                    ['CD_UNIDADE' => $cdUnidade, 'CD_LOCALIDADE' => $cdLocalidade, 'DS_NOME' => $nome], $cdUnidade);
            }
        } catch (Exception $logEx) {}
    } else {
        $sql = "INSERT INTO SIMP.dbo.LOCALIDADE (CD_UNIDADE, CD_LOCALIDADE, DS_NOME) 
                VALUES (:unidade, :codigo, :nome)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':unidade' => $cdUnidade, ':codigo' => $cdLocalidade, ':nome' => $nome]);
        $msg = 'Localidade cadastrada com sucesso!';
        
        // Log (isolado)
        try {
            @include_once '../logHelper.php';
            if (function_exists('registrarLogInsert')) {
                $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID");
                $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['ID'];
                registrarLogInsert('Cadastros Auxiliares', 'Localidade', $novoId, "$cdLocalidade - $nome",
                    ['CD_UNIDADE' => $cdUnidade, 'CD_LOCALIDADE' => $cdLocalidade, 'DS_NOME' => $nome], $cdUnidade);
            }
        } catch (Exception $logEx) {}
    }
    
    echo json_encode(['success' => true, 'message' => $msg]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
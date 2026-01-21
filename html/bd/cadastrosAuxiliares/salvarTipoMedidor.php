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
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $tipoCalculo = isset($_POST['tipo_calculo']) ? (int)$_POST['tipo_calculo'] : null;
    
    if (empty($nome)) {
        throw new Exception('Nome é obrigatório');
    }
    
    if (empty($tipoCalculo)) {
        throw new Exception('Tipo de cálculo é obrigatório');
    }
    
    if ($id) {
        $dadosAnteriores = null;
        try {
            $sqlAnterior = "SELECT DS_NOME, ID_TIPO_CALCULO FROM SIMP.dbo.TIPO_MEDIDOR WHERE CD_CHAVE = :id";
            $stmtAnterior = $pdoSIMP->prepare($sqlAnterior);
            $stmtAnterior->execute([':id' => $id]);
            $dadosAnteriores = $stmtAnterior->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
        
        $sql = "UPDATE SIMP.dbo.TIPO_MEDIDOR SET DS_NOME = :nome, ID_TIPO_CALCULO = :tipo WHERE CD_CHAVE = :id";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':nome' => $nome, ':tipo' => $tipoCalculo, ':id' => $id]);
        
        if (function_exists('registrarLogUpdate')) {
            try {
                registrarLogUpdate('Cadastros Auxiliares', 'Tipo de Medidor', $id, $nome,
                    ['anterior' => $dadosAnteriores, 'novo' => ['DS_NOME' => $nome, 'ID_TIPO_CALCULO' => $tipoCalculo]]);
            } catch (Exception $e) {}
        }
        
        $mensagem = 'Tipo de medidor atualizado com sucesso!';
    } else {
        $sql = "INSERT INTO SIMP.dbo.TIPO_MEDIDOR (DS_NOME, ID_TIPO_CALCULO) VALUES (:nome, :tipo)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':nome' => $nome, ':tipo' => $tipoCalculo]);
        
        $novoId = null;
        try {
            $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID");
            $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['ID'];
        } catch (Exception $e) {}
        
        if (function_exists('registrarLogInsert')) {
            try {
                registrarLogInsert('Cadastros Auxiliares', 'Tipo de Medidor', $novoId, $nome,
                    ['DS_NOME' => $nome, 'ID_TIPO_CALCULO' => $tipoCalculo]);
            } catch (Exception $e) {}
        }
        
        $mensagem = 'Tipo de medidor cadastrado com sucesso!';
    }
    
    echo json_encode(['success' => true, 'message' => $mensagem]);
} catch (Exception $e) {
    if (function_exists('registrarLogErro')) {
        try { registrarLogErro('Cadastros Auxiliares', 'SALVAR', $e->getMessage(), $_POST); } catch (Exception $ex) {}
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
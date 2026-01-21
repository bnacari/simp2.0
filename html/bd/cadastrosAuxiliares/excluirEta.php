<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
verificarPermissaoAjax('CADASTRO', ACESSO_ESCRITA);

include_once '../conexao.php';
@include_once '../logHelper.php';

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) throw new Exception('ID não informado');
    
    $dadosExcluidos = null;
    $identificador = "ID: $id";
    try {
        $stmtBusca = $pdoSIMP->prepare("SELECT * FROM SIMP.dbo.ETA WHERE CD_CHAVE = :id");
        $stmtBusca->execute([':id' => $id]);
        $dadosExcluidos = $stmtBusca->fetch(PDO::FETCH_ASSOC);
        if ($dadosExcluidos) $identificador = $dadosExcluidos['DS_NOME'] ?? $identificador;
    } catch (Exception $e) {}
    
    $sql = "DELETE FROM SIMP.dbo.ETA WHERE CD_CHAVE = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    if (function_exists('registrarLogDelete')) { try { registrarLogDelete('Cadastros Auxiliares', 'ETA', $id, $identificador, $dadosExcluidos); } catch (Exception $e) {} }
    
    echo json_encode(['success' => true, 'message' => 'Registro excluído com sucesso!']);
} catch (Exception $e) {
    if (function_exists('registrarLogErro')) { try { registrarLogErro('Cadastros Auxiliares', 'DELETE', $e->getMessage(), ['id' => $id ?? null]); } catch (Exception $ex) {} }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
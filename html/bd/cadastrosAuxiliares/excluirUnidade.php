<?php
/**
 * SIMP - Excluir Unidade (com Log)
 */
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
require_once '../logHelper.php';

verificarPermissaoAjax('CADASTRO', ACESSO_ESCRITA);

include_once '../conexao.php';

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if (!$id) {
        throw new Exception('ID não informado');
    }
    
    // Buscar dados antes de excluir
    $sqlBusca = "SELECT * FROM SIMP.dbo.UNIDADE WHERE CD_UNIDADE = :id";
    $stmtBusca = $pdoSIMP->prepare($sqlBusca);
    $stmtBusca->execute([':id' => $id]);
    $dadosExcluidos = $stmtBusca->fetch(PDO::FETCH_ASSOC);
    
    if (!$dadosExcluidos) {
        throw new Exception('Registro não encontrado');
    }
    
    $identificador = ($dadosExcluidos['CD_CODIGO'] ?? '') . ' - ' . ($dadosExcluidos['DS_NOME'] ?? "ID: $id");
    
    // Verificar dependências (localidades)
    $sqlDep = "SELECT COUNT(*) AS QTD FROM SIMP.dbo.LOCALIDADE WHERE CD_UNIDADE = :id";
    $stmtDep = $pdoSIMP->prepare($sqlDep);
    $stmtDep->execute([':id' => $id]);
    $dependencias = $stmtDep->fetch(PDO::FETCH_ASSOC)['QTD'];
    
    if ($dependencias > 0) {
        throw new Exception("Não é possível excluir: existem $dependencias localidade(s) vinculada(s)");
    }
    
    // Excluir
    $sql = "DELETE FROM SIMP.dbo.UNIDADE WHERE CD_UNIDADE = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    registrarLogDelete('Cadastros Auxiliares', 'Unidade', $id, $identificador, $dadosExcluidos);
    
    echo json_encode(['success' => true, 'message' => 'Registro excluído com sucesso!']);
    
} catch (Exception $e) {
    registrarLogErro('Cadastros Auxiliares', 'DELETE', $e->getMessage(), ['id' => $id ?? null]);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
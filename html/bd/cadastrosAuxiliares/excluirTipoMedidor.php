<?php
/**
 * SIMP - Excluir Tipo de Medidor (com Log)
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
    
    // Buscar dados antes de excluir (para log)
    $sqlBusca = "SELECT * FROM SIMP.dbo.TIPO_MEDIDOR WHERE CD_CHAVE = :id";
    $stmtBusca = $pdoSIMP->prepare($sqlBusca);
    $stmtBusca->execute([':id' => $id]);
    $dadosExcluidos = $stmtBusca->fetch(PDO::FETCH_ASSOC);
    
    if (!$dadosExcluidos) {
        throw new Exception('Registro não encontrado');
    }
    
    $identificador = $dadosExcluidos['DS_NOME'] ?? "ID: $id";
    
    // Verificar dependências
    $sqlDep = "SELECT COUNT(*) AS QTD FROM SIMP.dbo.PONTO_MEDICAO WHERE ID_TIPO_MEDIDOR = :id";
    $stmtDep = $pdoSIMP->prepare($sqlDep);
    $stmtDep->execute([':id' => $id]);
    $dependencias = $stmtDep->fetch(PDO::FETCH_ASSOC)['QTD'];
    
    if ($dependencias > 0) {
        throw new Exception("Não é possível excluir: existem $dependencias ponto(s) de medição utilizando este tipo");
    }
    
    // Excluir
    $sql = "DELETE FROM SIMP.dbo.TIPO_MEDIDOR WHERE CD_CHAVE = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    // Registrar log
    registrarLogDelete('Cadastros Auxiliares', 'Tipo de Medidor', $id, $identificador, $dadosExcluidos);
    
    echo json_encode(['success' => true, 'message' => 'Registro excluído com sucesso!']);
    
} catch (Exception $e) {
    registrarLogErro('Cadastros Auxiliares', 'DELETE', $e->getMessage(), ['id' => $id ?? null]);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
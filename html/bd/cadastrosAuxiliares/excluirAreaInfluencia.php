<?php
/**
 * SIMP - Excluir Área de Influência (com Log)
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
    $sqlBusca = "SELECT * FROM SIMP.dbo.AREA_INFLUENCIA WHERE CD_AREA_INFLUENCIA = :id";
    $stmtBusca = $pdoSIMP->prepare($sqlBusca);
    $stmtBusca->execute([':id' => $id]);
    $dadosExcluidos = $stmtBusca->fetch(PDO::FETCH_ASSOC);
    
    if (!$dadosExcluidos) {
        throw new Exception('Registro não encontrado');
    }
    
    $identificador = $dadosExcluidos['DS_MUNICIPIO'] ?? "ID: $id";
    
    // Excluir bairros relacionados primeiro
    $sqlBairros = "DELETE FROM SIMP.dbo.AREA_INFLUENCIA_BAIRRO WHERE CD_AREA_INFLUENCIA = :id";
    $stmtBairros = $pdoSIMP->prepare($sqlBairros);
    $stmtBairros->execute([':id' => $id]);
    $bairrosExcluidos = $stmtBairros->rowCount();
    
    // Excluir área
    $sql = "DELETE FROM SIMP.dbo.AREA_INFLUENCIA WHERE CD_AREA_INFLUENCIA = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    $dadosExcluidos['bairros_excluidos'] = $bairrosExcluidos;
    
    registrarLogDelete('Cadastros Auxiliares', 'Área de Influência', $id, $identificador, $dadosExcluidos);
    
    echo json_encode(['success' => true, 'message' => 'Registro excluído com sucesso!']);
    
} catch (Exception $e) {
    registrarLogErro('Cadastros Auxiliares', 'DELETE', $e->getMessage(), ['id' => $id ?? null]);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
verificarPermissaoAjax('CADASTRO', ACESSO_ESCRITA);

include_once '../conexao.php';

try {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $municipio = isset($_POST['municipio']) ? trim($_POST['municipio']) : '';
    $taxaOcupacao = isset($_POST['taxa_ocupacao']) && $_POST['taxa_ocupacao'] !== '' ? (float)$_POST['taxa_ocupacao'] : null;
    $densidade = isset($_POST['densidade']) && $_POST['densidade'] !== '' ? (float)$_POST['densidade'] : null;
    
    if (empty($municipio)) throw new Exception('Município é obrigatório');
    
    if ($id) {
        $sql = "UPDATE SIMP.dbo.AREA_INFLUENCIA 
                SET DS_MUNICIPIO = :municipio, VL_TAXA_OCUPACAO = :taxa, VL_DENSIDADE_DEMOGRAFICA = :densidade 
                WHERE CD_AREA_INFLUENCIA = :id";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':municipio' => $municipio, ':taxa' => $taxaOcupacao, ':densidade' => $densidade, ':id' => $id]);
        $msg = 'Área de influência atualizada com sucesso!';
    } else {
        $sql = "INSERT INTO SIMP.dbo.AREA_INFLUENCIA (DS_MUNICIPIO, VL_TAXA_OCUPACAO, VL_DENSIDADE_DEMOGRAFICA) 
                VALUES (:municipio, :taxa, :densidade)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':municipio' => $municipio, ':taxa' => $taxaOcupacao, ':densidade' => $densidade]);
        $msg = 'Área de influência cadastrada com sucesso!';
    }
    
    echo json_encode(['success' => true, 'message' => $msg]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

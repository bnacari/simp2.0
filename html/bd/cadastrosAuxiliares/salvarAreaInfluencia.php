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
    $municipio = isset($_POST['municipio']) ? trim($_POST['municipio']) : '';
    $taxaOcupacao = isset($_POST['taxa_ocupacao']) && $_POST['taxa_ocupacao'] !== '' ? (float)$_POST['taxa_ocupacao'] : null;
    $densidade = isset($_POST['densidade']) && $_POST['densidade'] !== '' ? (float)$_POST['densidade'] : null;
    
    if (empty($municipio)) throw new Exception('Município é obrigatório');
    
    if ($id) {
        $dadosAnteriores = null;
        try { $stmtAnt = $pdoSIMP->prepare("SELECT DS_MUNICIPIO, VL_TAXA_OCUPACAO, VL_DENSIDADE_DEMOGRAFICA FROM SIMP.dbo.AREA_INFLUENCIA WHERE CD_AREA_INFLUENCIA = :id"); $stmtAnt->execute([':id' => $id]); $dadosAnteriores = $stmtAnt->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
        
        $sql = "UPDATE SIMP.dbo.AREA_INFLUENCIA SET DS_MUNICIPIO = :municipio, VL_TAXA_OCUPACAO = :taxa, VL_DENSIDADE_DEMOGRAFICA = :densidade WHERE CD_AREA_INFLUENCIA = :id";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':municipio' => $municipio, ':taxa' => $taxaOcupacao, ':densidade' => $densidade, ':id' => $id]);
        
        if (function_exists('registrarLogUpdate')) { try { registrarLogUpdate('Cadastros Auxiliares', 'Área de Influência', $id, $municipio, ['anterior' => $dadosAnteriores, 'novo' => ['DS_MUNICIPIO' => $municipio, 'VL_TAXA_OCUPACAO' => $taxaOcupacao, 'VL_DENSIDADE_DEMOGRAFICA' => $densidade]]); } catch (Exception $e) {} }
        
        $msg = 'Área de influência atualizada com sucesso!';
    } else {
        $sql = "INSERT INTO SIMP.dbo.AREA_INFLUENCIA (DS_MUNICIPIO, VL_TAXA_OCUPACAO, VL_DENSIDADE_DEMOGRAFICA) VALUES (:municipio, :taxa, :densidade)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':municipio' => $municipio, ':taxa' => $taxaOcupacao, ':densidade' => $densidade]);
        
        $novoId = null;
        try { $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID"); $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['ID']; } catch (Exception $e) {}
        
        if (function_exists('registrarLogInsert')) { try { registrarLogInsert('Cadastros Auxiliares', 'Área de Influência', $novoId, $municipio, ['DS_MUNICIPIO' => $municipio, 'VL_TAXA_OCUPACAO' => $taxaOcupacao, 'VL_DENSIDADE_DEMOGRAFICA' => $densidade]); } catch (Exception $e) {} }
        
        $msg = 'Área de influência cadastrada com sucesso!';
    }
    
    echo json_encode(['success' => true, 'message' => $msg]);
} catch (Exception $e) {
    if (function_exists('registrarLogErro')) { try { registrarLogErro('Cadastros Auxiliares', 'SALVAR', $e->getMessage(), $_POST); } catch (Exception $ex) {} }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
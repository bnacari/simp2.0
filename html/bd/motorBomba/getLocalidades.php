<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Buscar Localidades por Unidade (Módulo Motor-Bomba)
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
include_once '../conexao.php';

try {
    $cdUnidade = isset($_GET['cd_unidade']) ? trim($_GET['cd_unidade']) : '';

    if (empty($cdUnidade)) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    $sql = "SELECT CD_CHAVE, CD_LOCALIDADE, DS_NOME 
            FROM SIMP.dbo.LOCALIDADE 
            WHERE CD_UNIDADE = :cd_unidade 
            ORDER BY DS_NOME";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':cd_unidade' => $cdUnidade]);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $dados]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
}

<?php
/**
 * SIMP - Registro de Vazão e Pressão
 * Endpoint: Buscar informações de um Ponto de Medição específico
 * 
 * Usado para preencher o autocomplete via parâmetro GET
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
    include_once '../conexao.php';
    
    $cdPonto = isset($_GET['cd_ponto']) ? (int)$_GET['cd_ponto'] : 0;
    
    if ($cdPonto <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Código do ponto não informado'
        ]);
        exit;
    }
    
    $sql = "
        SELECT 
            PM.CD_PONTO_MEDICAO,
            PM.DS_NOME,
            PM.ID_TIPO_MEDIDOR,
            PM.DS_TAG_VAZAO,
            PM.DS_TAG_PRESSAO,
            L.CD_LOCALIDADE,
            L.CD_UNIDADE,
            L.DS_NOME AS LOCALIDADE_NOME,
            U.DS_NOME AS UNIDADE_NOME
        FROM SIMP.dbo.PONTO_MEDICAO PM
        LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
        LEFT JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
        WHERE PM.CD_PONTO_MEDICAO = :cdPonto
    ";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':cdPonto' => $cdPonto]);
    $ponto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ponto) {
        echo json_encode([
            'success' => true,
            'ponto' => $ponto
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Ponto não encontrado'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
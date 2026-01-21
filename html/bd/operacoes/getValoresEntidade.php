<?php
/**
 * SIMP - Buscar Valores de Entidade (Unidades Operacionais)
 * 
 * Retorna todas as unidades operacionais, opcionalmente filtradas por tipo.
 * Se tipoId não for informado, retorna TODAS as unidades.
 * 
 * @param tipoId (opcional) - Filtrar por tipo de entidade
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';

    $tipoId = isset($_GET['tipoId']) && $_GET['tipoId'] !== '' ? (int)$_GET['tipoId'] : 0;

    // Se tipoId informado, filtra por tipo
    if ($tipoId > 0) {
        $sql = "SELECT 
                    EV.CD_CHAVE AS cd,
                    EV.DS_NOME AS nome,
                    EV.CD_ENTIDADE_VALOR_ID AS id,
                    EV.ID_FLUXO AS fluxo,
                    ET.DS_NOME AS tipo_nome,
                    ET.CD_ENTIDADE_TIPO_ID AS tipo_id
                FROM SIMP.dbo.ENTIDADE_VALOR EV
                INNER JOIN SIMP.dbo.ENTIDADE_TIPO ET ON ET.CD_CHAVE = EV.CD_ENTIDADE_TIPO
                WHERE EV.CD_ENTIDADE_TIPO = :tipoId
                ORDER BY EV.DS_NOME";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':tipoId' => $tipoId]);
    } 
    // Se tipoId não informado, retorna TODAS as unidades operacionais
    else {
        $sql = "SELECT 
                    EV.CD_CHAVE AS cd,
                    EV.DS_NOME AS nome,
                    EV.CD_ENTIDADE_VALOR_ID AS id,
                    EV.ID_FLUXO AS fluxo,
                    ET.DS_NOME AS tipo_nome,
                    ET.CD_ENTIDADE_TIPO_ID AS tipo_id
                FROM SIMP.dbo.ENTIDADE_VALOR EV
                INNER JOIN SIMP.dbo.ENTIDADE_TIPO ET ON ET.CD_CHAVE = EV.CD_ENTIDADE_TIPO
                ORDER BY ET.DS_NOME, EV.DS_NOME";
        
        $stmt = $pdoSIMP->query($sql);
    }
    
    $valores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'valores' => $valores,
        'filtrado' => $tipoId > 0
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
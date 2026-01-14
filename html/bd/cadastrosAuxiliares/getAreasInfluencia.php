<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
verificarPermissaoAjax('CADASTRO', ACESSO_LEITURA);

include_once '../conexao.php';

try {
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    $porPagina = isset($_GET['porPagina']) ? (int)$_GET['porPagina'] : 20;
    $offset = ($pagina - 1) * $porPagina;

    $where = '';
    $params = [];
    
    if ($busca !== '') {
        $where = " WHERE A.DS_MUNICIPIO LIKE :busca";
        $params[':busca'] = '%' . $busca . '%';
    }

    $sqlCount = "SELECT COUNT(*) as total FROM SIMP.dbo.AREA_INFLUENCIA A" . $where;
    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPaginas = ceil($total / $porPagina);

    $sql = "SELECT 
                A.CD_AREA_INFLUENCIA,
                A.DS_MUNICIPIO,
                A.VL_TAXA_OCUPACAO,
                A.VL_DENSIDADE_DEMOGRAFICA,
                (SELECT COUNT(*) FROM SIMP.dbo.AREA_INFLUENCIA_BAIRRO B WHERE B.CD_AREA_INFLUENCIA = A.CD_AREA_INFLUENCIA) AS QTD_BAIRROS
            FROM SIMP.dbo.AREA_INFLUENCIA A
            $where
            ORDER BY A.DS_MUNICIPIO
            OFFSET $offset ROWS FETCH NEXT $porPagina ROWS ONLY";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'data' => $dados,
        'total' => (int)$total,
        'pagina' => $pagina,
        'porPagina' => $porPagina,
        'totalPaginas' => (int)$totalPaginas
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
}

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
        $where = " WHERE NOME LIKE :busca";
        $params[':busca'] = '%' . $busca . '%';
    }

    $sqlCount = "SELECT COUNT(*) as total FROM SIMP.dbo.TIPO_RESERVATORIO" . $where;
    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPaginas = ceil($total / $porPagina);

    $sql = "SELECT CD_CHAVE, NOME 
            FROM SIMP.dbo.TIPO_RESERVATORIO
            $where
            ORDER BY NOME
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

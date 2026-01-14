<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
verificarPermissaoAjax('CADASTRO', ACESSO_LEITURA);

include_once '../conexao.php';

try {
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    $cdUnidade = isset($_GET['cd_unidade']) && $_GET['cd_unidade'] !== '' ? (int)$_GET['cd_unidade'] : null;
    $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    $porPagina = isset($_GET['porPagina']) ? (int)$_GET['porPagina'] : 20;
    $offset = ($pagina - 1) * $porPagina;

    $where = "WHERE 1=1";
    $params = [];
    
    if ($busca !== '') {
        $where .= " AND (L.DS_NOME LIKE :busca OR L.CD_LOCALIDADE LIKE :busca2)";
        $params[':busca'] = '%' . $busca . '%';
        $params[':busca2'] = '%' . $busca . '%';
    }
    
    if ($cdUnidade !== null) {
        $where .= " AND L.CD_UNIDADE = :cd_unidade";
        $params[':cd_unidade'] = $cdUnidade;
    }

    $sqlCount = "SELECT COUNT(*) as total 
                 FROM SIMP.dbo.LOCALIDADE L
                 LEFT JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
                 $where";
    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPaginas = ceil($total / $porPagina);

    $sql = "SELECT 
                L.CD_CHAVE,
                L.CD_UNIDADE,
                L.DS_NOME,
                L.CD_LOCALIDADE,
                L.CD_ENTIDADE_VALOR_ID,
                U.DS_NOME AS DS_UNIDADE
            FROM SIMP.dbo.LOCALIDADE L
            LEFT JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
            $where
            ORDER BY L.DS_NOME
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

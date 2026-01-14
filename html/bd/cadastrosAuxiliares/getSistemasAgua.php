<?php
/**
 * SIMP - Cadastros Auxiliares
 * Endpoint: Buscar Sistemas de Água (tabela SISTEMA_AGUA)
 */

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

    $where = "WHERE 1=1";
    $params = [];
    
    if ($busca !== '') {
        $where .= " AND (SA.DS_NOME LIKE :busca OR SA.DS_DESCRICAO LIKE :busca2 OR LOC.CD_LOCALIDADE LIKE :busca3 OR LOC.DS_NOME LIKE :busca4)";
        $params[':busca'] = '%' . $busca . '%';
        $params[':busca2'] = '%' . $busca . '%';
        $params[':busca3'] = '%' . $busca . '%';
        $params[':busca4'] = '%' . $busca . '%';
    }

    $sqlCount = "SELECT COUNT(*) as total 
                 FROM SIMP.dbo.SISTEMA_AGUA SA
                 LEFT JOIN SIMP.dbo.LOCALIDADE LOC ON LOC.CD_CHAVE = SA.CD_LOCALIDADE
                 $where";
    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPaginas = ceil($total / $porPagina);

    $sql = "SELECT 
                SA.CD_CHAVE,
                SA.CD_LOCALIDADE,
                CASE 
                    WHEN LOC.CD_LOCALIDADE IS NOT NULL AND LOC.DS_NOME IS NOT NULL 
                    THEN CONCAT(LOC.CD_LOCALIDADE, ' - ', LOC.DS_NOME)
                    ELSE NULL 
                END AS DS_LOCALIDADE_FORMATADA,
                SA.DS_NOME,
                SA.DS_DESCRICAO,
                SA.DT_ULTIMA_ATUALIZACAO,
                SA.CD_USUARIO_ULTIMA_ATUALIZACAO
            FROM SIMP.dbo.SISTEMA_AGUA SA
            LEFT JOIN SIMP.dbo.LOCALIDADE LOC ON LOC.CD_CHAVE = SA.CD_LOCALIDADE
            $where
            ORDER BY SA.DS_NOME
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
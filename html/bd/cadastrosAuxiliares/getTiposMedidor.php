<?php
/**
 * SIMP - Cadastros Auxiliares
 * Endpoint: Buscar Tipos de Medidor com paginação
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

    // Condições WHERE
    $where = '';
    $params = [];
    
    if ($busca !== '') {
        $where = " WHERE DS_NOME LIKE :busca";
        $params[':busca'] = '%' . $busca . '%';
    }

    // Conta total
    $sqlCount = "SELECT COUNT(*) as total FROM SIMP.dbo.TIPO_MEDIDOR" . $where;
    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPaginas = ceil($total / $porPagina);

    // Busca dados paginados
    $sql = "SELECT CD_CHAVE, DS_NOME, ID_TIPO_CALCULO 
            FROM SIMP.dbo.TIPO_MEDIDOR
            $where
            ORDER BY DS_NOME
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

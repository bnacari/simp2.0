<?php
// bd/cadastrosAuxiliares/getUnidades.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';

try {
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    $porPagina = isset($_GET['porPagina']) ? (int)$_GET['porPagina'] : 20;
    
    if ($pagina < 1) $pagina = 1;
    if ($porPagina < 1) $porPagina = 20;
    
    $offset = ($pagina - 1) * $porPagina;
    
    // Condições WHERE
    $where = "WHERE 1=1";
    $params = [];
    
    if ($busca !== '') {
        $where .= " AND (U.DS_NOME LIKE :busca OR U.CD_CODIGO LIKE :busca)";
        $params[':busca'] = '%' . $busca . '%';
    }
    
    // Query de contagem
    $sqlCount = "SELECT COUNT(*) as total FROM SIMP.dbo.UNIDADE U $where";
    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Query principal
    $sql = "SELECT 
                U.CD_UNIDADE,
                U.DS_NOME,
                U.CD_CODIGO
            FROM SIMP.dbo.UNIDADE U
            $where
            ORDER BY U.CD_CODIGO, U.DS_NOME
            OFFSET :offset ROWS FETCH NEXT :porPagina ROWS ONLY";
    
    $stmt = $pdoSIMP->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':porPagina', $porPagina, PDO::PARAM_INT);
    $stmt->execute();
    
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $dados,
        'total' => (int)$total,
        'pagina' => $pagina,
        'porPagina' => $porPagina,
        'totalPaginas' => ceil($total / $porPagina)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar unidades: ' . $e->getMessage()
    ]);
}
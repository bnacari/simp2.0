<?php
/**
 * SIMP - Buscar Logs do Sistema
 * Endpoint para consulta de registros de log com filtros e paginação
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
    require_once '../conexao.php';

    // Verifica permissão
    verificarPermissaoAjax('Consultar Log', ACESSO_LEITURA);

    // Parâmetros de paginação
    $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
    $porPagina = isset($_GET['porPagina']) ? min(100, max(10, (int)$_GET['porPagina'])) : 20;
    $offset = ($pagina - 1) * $porPagina;

    // Parâmetros de filtro
    $dataInicio = isset($_GET['dataInicio']) && !empty($_GET['dataInicio']) ? $_GET['dataInicio'] : null;
    $dataFim = isset($_GET['dataFim']) && !empty($_GET['dataFim']) ? $_GET['dataFim'] : null;
    $cdUsuario = isset($_GET['cdUsuario']) && $_GET['cdUsuario'] !== '' ? (int)$_GET['cdUsuario'] : null;
    $cdFuncionalidade = isset($_GET['cdFuncionalidade']) && $_GET['cdFuncionalidade'] !== '' ? (int)$_GET['cdFuncionalidade'] : null;
    $cdUnidade = isset($_GET['cdUnidade']) && $_GET['cdUnidade'] !== '' ? (int)$_GET['cdUnidade'] : null;
    $tipo = isset($_GET['tipo']) && $_GET['tipo'] !== '' ? (int)$_GET['tipo'] : null;
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

    // Construir WHERE
    $where = [];
    $params = [];

    if ($dataInicio) {
        $where[] = "L.DT_LOG >= :dataInicio";
        $params[':dataInicio'] = $dataInicio . ' 00:00:00';
    }

    if ($dataFim) {
        $where[] = "L.DT_LOG <= :dataFim";
        $params[':dataFim'] = $dataFim . ' 23:59:59';
    }

    if ($cdUsuario !== null) {
        $where[] = "L.CD_USUARIO = :cdUsuario";
        $params[':cdUsuario'] = $cdUsuario;
    }

    if ($cdFuncionalidade !== null) {
        $where[] = "L.CD_FUNCIONALIDADE = :cdFuncionalidade";
        $params[':cdFuncionalidade'] = $cdFuncionalidade;
    }

    if ($cdUnidade !== null) {
        $where[] = "L.CD_UNIDADE = :cdUnidade";
        $params[':cdUnidade'] = $cdUnidade;
    }

    if ($tipo !== null) {
        $where[] = "L.TP_LOG = :tipo";
        $params[':tipo'] = $tipo;
    }

    if (!empty($busca)) {
        $where[] = "(L.NM_LOG LIKE :busca OR CAST(L.DS_LOG AS VARCHAR(MAX)) LIKE :busca)";
        $params[':busca'] = '%' . $busca . '%';
    }

    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    // Contar total
    $sqlCount = "
        SELECT COUNT(*) AS TOTAL
        FROM SIMP.dbo.LOG L
        $whereClause
    ";
    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetch(PDO::FETCH_ASSOC)['TOTAL'];
    $totalPaginas = ceil($total / $porPagina);

    // Buscar dados com paginação
    $sql = "
        SELECT 
            L.CD_LOG,
            L.CD_USUARIO,
            L.CD_FUNCIONALIDADE,
            L.CD_UNIDADE,
            L.DT_LOG,
            L.TP_LOG,
            L.NM_LOG,
            CAST(L.DS_LOG AS VARCHAR(500)) AS DS_LOG,
            L.DS_VERSAO,
            L.NM_SERVIDOR,
            U.DS_NOME AS DS_USUARIO,
            F.DS_NOME AS DS_FUNCIONALIDADE,
            UN.DS_NOME AS DS_UNIDADE
        FROM SIMP.dbo.LOG L
        LEFT JOIN SIMP.dbo.USUARIO U ON L.CD_USUARIO = U.CD_USUARIO
        LEFT JOIN SIMP.dbo.FUNCIONALIDADE F ON L.CD_FUNCIONALIDADE = F.CD_FUNCIONALIDADE
        LEFT JOIN SIMP.dbo.UNIDADE UN ON L.CD_UNIDADE = UN.CD_UNIDADE
        $whereClause
        ORDER BY L.DT_LOG DESC, L.CD_LOG DESC
        OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY
    ";

    $stmt = $pdoSIMP->prepare($sql);
    
    // Bind dos parâmetros de filtro
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // Bind dos parâmetros de paginação
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
    
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $logs,
        'pagina' => $pagina,
        'porPagina' => $porPagina,
        'total' => $total,
        'totalPaginas' => $totalPaginas
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar logs: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

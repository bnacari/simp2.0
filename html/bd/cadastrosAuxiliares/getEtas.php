<?php
/**
 * SIMP - Cadastros Auxiliares
 * Endpoint: Buscar ETAs com paginação
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
verificarPermissaoAjax('CADASTRO', ACESSO_LEITURA);

include_once '../conexao.php';

try {
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    $cdSistema = isset($_GET['cd_sistema']) && $_GET['cd_sistema'] !== '' ? (int)$_GET['cd_sistema'] : null;
    $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    $porPagina = isset($_GET['porPagina']) ? (int)$_GET['porPagina'] : 20;
    $offset = ($pagina - 1) * $porPagina;

    $where = "WHERE 1=1";
    $params = [];
    
    if ($busca !== '') {
        $where .= " AND (E.DS_NOME LIKE :busca OR E.DS_DESCRICAO LIKE :busca2)";
        $params[':busca'] = '%' . $busca . '%';
        $params[':busca2'] = '%' . $busca . '%';
    }
    
    if ($cdSistema !== null) {
        $where .= " AND E.CD_SISTEMA_AGUA = :cd_sistema";
        $params[':cd_sistema'] = $cdSistema;
    }

    $sqlCount = "SELECT COUNT(*) as total 
                 FROM SIMP.dbo.ETA E
                 LEFT JOIN SIMP.dbo.SISTEMA_AGUA S ON E.CD_SISTEMA_AGUA = S.CD_CHAVE
                 LEFT JOIN SIMP.dbo.FORMULA F ON E.CD_FORMULA_VOLUME_DISTRIBUIDO = F.CD_CHAVE
                 $where";
    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPaginas = ceil($total / $porPagina);

    $sql = "SELECT 
                E.CD_CHAVE,
                E.CD_SISTEMA_AGUA,
                E.DS_NOME,
                E.DS_DESCRICAO,
                E.VL_META_DIA,
                E.CD_ENTIDADE_VALOR_ID,
                E.CD_FORMULA_VOLUME_DISTRIBUIDO,
                E.DT_ULTIMA_ATUALIZACAO,
                E.CD_USUARIO_ULTIMA_ATUALIZACAO,
                S.DS_NOME AS DS_SISTEMA_AGUA,
                F.DS_NOME AS DS_FORMULA
            FROM SIMP.dbo.ETA E
            LEFT JOIN SIMP.dbo.SISTEMA_AGUA S ON E.CD_SISTEMA_AGUA = S.CD_CHAVE
            LEFT JOIN SIMP.dbo.FORMULA F ON E.CD_FORMULA_VOLUME_DISTRIBUIDO = F.CD_CHAVE
            $where
            ORDER BY E.DS_NOME
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

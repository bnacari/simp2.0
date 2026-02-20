<?php
/**
 * SIMP - Listar Conexões de Fluxo (Grafo Dirigido)
 * Retorna todas as conexões entre nós usando a view VW_ENTIDADE_CONEXOES.
 * 
 * GET params:
 *   - cd_nodo (int|null) : filtrar conexões de/para um nó específico
 *   - ativos_only (0|1)  : apenas conexões ativas (default: 1)
 * 
 * @author Bruno - CESAN
 * @version 1.0
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';

    if (!isset($pdoSIMP)) {
        throw new Exception('Conexão não estabelecida');
    }

    $cdNodo     = isset($_GET['cd_nodo']) && $_GET['cd_nodo'] !== '' ? (int)$_GET['cd_nodo'] : null;
    $ativosOnly = isset($_GET['ativos_only']) ? (int)$_GET['ativos_only'] : 1;

    // --------------------------------------------------
    // Montar query com filtros opcionais
    // --------------------------------------------------
    $where = [];
    $params = [];

    if ($ativosOnly) {
        $where[] = "OP_ATIVO = 1";
    }

    if ($cdNodo !== null) {
        $where[] = "(CD_NODO_ORIGEM = :cdNodo OR CD_NODO_DESTINO = :cdNodo2)";
        $params[':cdNodo']  = $cdNodo;
        $params[':cdNodo2'] = $cdNodo;
    }

    $sqlWhere = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT * FROM SIMP.dbo.VW_ENTIDADE_CONEXOES $sqlWhere ORDER BY NR_ORDEM, CD_CHAVE";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute($params);
    $conexoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'  => true,
        'conexoes' => $conexoes,
        'total'    => count($conexoes)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

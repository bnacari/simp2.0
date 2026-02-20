<?php
/**
 * SIMP - Listar Árvore de Entidades (Cascata Genérica)
 * Retorna a estrutura hierárquica completa usando a view recursiva.
 * 
 * Parâmetros GET:
 *   - ativos_only (0|1) : filtrar apenas nós ativos (default: 1)
 *   - cd_pai (int)      : buscar filhos de um nó específico (lazy load)
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

    $ativosOnly = isset($_GET['ativos_only']) ? (int)$_GET['ativos_only'] : 1;
    $cdPai      = isset($_GET['cd_pai']) && $_GET['cd_pai'] !== '' ? (int)$_GET['cd_pai'] : null;

    // --------------------------------------------------
    // Buscar todos os nós (a view já monta caminho/profundidade)
    // --------------------------------------------------
    $where = [];
    $params = [];

    if ($ativosOnly) {
        $where[] = "OP_ATIVO = 1";
    }

    $sqlWhere = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT 
                V.CD_CHAVE,
                V.CD_PAI,
                V.CD_ENTIDADE_NIVEL,
                V.DS_NOME,
                V.DS_IDENTIFICADOR,
                V.NR_ORDEM,
                V.CD_PONTO_MEDICAO,
                V.ID_OPERACAO,
                V.ID_FLUXO,
                V.DT_INICIO,
                V.DT_FIM,
                V.OP_ATIVO,
                V.DS_NIVEL,
                V.DS_ICONE,
                V.DS_COR,
                V.OP_PERMITE_PONTO,
                V.NR_PROFUNDIDADE,
                V.DS_CAMINHO,
                V.DS_ORDENACAO,
                N.NR_POS_X,
                N.NR_POS_Y,
                N.DS_OBSERVACAO,
                N.CD_SISTEMA_AGUA,
                SA.DS_NOME AS DS_SISTEMA_AGUA,
                ENL.OP_EH_SISTEMA
            FROM SIMP.dbo.VW_ENTIDADE_ARVORE V
            LEFT JOIN SIMP.dbo.ENTIDADE_NODO N ON N.CD_CHAVE = V.CD_CHAVE
            LEFT JOIN SIMP.dbo.SISTEMA_AGUA SA ON SA.CD_CHAVE = N.CD_SISTEMA_AGUA
            LEFT JOIN SIMP.dbo.ENTIDADE_NIVEL ENL ON ENL.CD_CHAVE = V.CD_ENTIDADE_NIVEL
            $sqlWhere
            ORDER BY V.DS_ORDENACAO";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute($params);

    $nosFlat = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Formatar datas
        if ($row['DT_INICIO'] instanceof DateTime) {
            $row['DT_INICIO'] = $row['DT_INICIO']->format('Y-m-d');
        }
        if ($row['DT_FIM'] instanceof DateTime) {
            $row['DT_FIM'] = $row['DT_FIM']->format('Y-m-d');
        }

        // Buscar nome do ponto se vinculado
        $row['DS_PONTO'] = null;
        if ($row['CD_PONTO_MEDICAO']) {
            try {
                $sqlPonto = "SELECT DS_NOME FROM SIMP.dbo.PONTO_MEDICAO WHERE CD_PONTO_MEDICAO = ?";
                $stmtP = $pdoSIMP->prepare($sqlPonto);
                $stmtP->execute([$row['CD_PONTO_MEDICAO']]);
                $ponto = $stmtP->fetch(PDO::FETCH_ASSOC);
                $row['DS_PONTO'] = $ponto ? $ponto['DS_NOME'] : null;
            } catch (Exception $e) {}
        }

        $nosFlat[] = $row;
    }

    // --------------------------------------------------
    // Montar árvore hierárquica a partir do flat
    // --------------------------------------------------
    $arvore = montarArvore($nosFlat);

    // --------------------------------------------------
    // Contadores
    // --------------------------------------------------
    $totalNos = count($nosFlat);
    $totalRaizes = 0;
    $totalComPonto = 0;
    $profundidadeMax = 0;

    foreach ($nosFlat as $n) {
        if ($n['CD_PAI'] === null) $totalRaizes++;
        if ($n['CD_PONTO_MEDICAO']) $totalComPonto++;
        if ($n['NR_PROFUNDIDADE'] > $profundidadeMax) $profundidadeMax = $n['NR_PROFUNDIDADE'];
    }

    echo json_encode([
        'success'  => true,
        'arvore'   => $arvore,
        'flat'     => $nosFlat,
        'stats'    => [
            'totalNos'         => $totalNos,
            'totalRaizes'      => $totalRaizes,
            'totalComPonto'    => $totalComPonto,
            'profundidadeMax'  => $profundidadeMax
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Monta a árvore hierárquica a partir de array flat.
 * Cada nó recebe array 'filhos' com seus descendentes diretos.
 */
function montarArvore($nosFlat) {
    $mapa = [];
    $raizes = [];

    // Indexar por CD_CHAVE
    foreach ($nosFlat as &$no) {
        $no['filhos'] = [];
        $mapa[$no['CD_CHAVE']] = &$no;
    }
    unset($no);

    // Montar hierarquia
    foreach ($mapa as $cd => &$no) {
        if ($no['CD_PAI'] === null || !isset($mapa[$no['CD_PAI']])) {
            $raizes[] = &$no;
        } else {
            $mapa[$no['CD_PAI']]['filhos'][] = &$no;
        }
    }
    unset($no);

    return $raizes;
}
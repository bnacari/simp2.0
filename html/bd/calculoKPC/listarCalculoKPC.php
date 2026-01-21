<?php
/**
 * SIMP - Listar Cálculos de KPC
 * 
 * Retorna lista paginada de cálculos de KPC com filtros.
 * Adicionado ID_TIPO_MEDIDOR para construção do código formatado do ponto
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
    include_once '../conexao.php';

    // Parâmetros de paginação
    $pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
    $limite = isset($_GET['limite']) ? min(100, max(1, (int)$_GET['limite'])) : 20;
    $offset = ($pagina - 1) * $limite;

    // Parâmetros de filtro
    $unidade = isset($_GET['unidade']) ? trim($_GET['unidade']) : '';
    $localidade = isset($_GET['localidade']) ? trim($_GET['localidade']) : '';
    $ponto = isset($_GET['ponto']) ? trim($_GET['ponto']) : '';
    $situacao = isset($_GET['situacao']) ? trim($_GET['situacao']) : '';
    $metodo = isset($_GET['metodo']) ? trim($_GET['metodo']) : '';
    $codigoInicial = isset($_GET['codigoInicial']) ? trim($_GET['codigoInicial']) : '';
    $anoInicial = isset($_GET['anoInicial']) ? trim($_GET['anoInicial']) : '';
    $codigoFinal = isset($_GET['codigoFinal']) ? trim($_GET['codigoFinal']) : '';
    $anoFinal = isset($_GET['anoFinal']) ? trim($_GET['anoFinal']) : '';
    $dataInicial = isset($_GET['dataInicial']) ? trim($_GET['dataInicial']) : '';
    $dataFinal = isset($_GET['dataFinal']) ? trim($_GET['dataFinal']) : '';

    // Monta a query base
    $sqlBase = "
        FROM SIMP.dbo.CALCULO_KPC CK
        INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON CK.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
        INNER JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
        INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
        WHERE 1=1
    ";

    $params = [];

    // Aplicar filtros
    if (!empty($unidade)) {
        $sqlBase .= " AND U.CD_UNIDADE = :unidade";
        $params[':unidade'] = $unidade;
    }

    if (!empty($localidade)) {
        $sqlBase .= " AND L.CD_CHAVE = :localidade";
        $params[':localidade'] = $localidade;
    }

    if (!empty($ponto)) {
        $sqlBase .= " AND PM.CD_PONTO_MEDICAO = :ponto";
        $params[':ponto'] = $ponto;
    }

    if (!empty($situacao)) {
        $sqlBase .= " AND CK.ID_SITUACAO = :situacao";
        $params[':situacao'] = $situacao;
    }

    if (!empty($metodo)) {
        $sqlBase .= " AND CK.ID_METODO = :metodo";
        $params[':metodo'] = $metodo;
    }

    if (!empty($codigoInicial)) {
        $sqlBase .= " AND CK.CD_CODIGO >= :codigoInicial";
        $params[':codigoInicial'] = $codigoInicial;
    }

    if (!empty($codigoFinal)) {
        $sqlBase .= " AND CK.CD_CODIGO <= :codigoFinal";
        $params[':codigoFinal'] = $codigoFinal;
    }

    if (!empty($anoInicial)) {
        $sqlBase .= " AND CK.CD_ANO >= :anoInicial";
        $params[':anoInicial'] = $anoInicial;
    }

    if (!empty($anoFinal)) {
        $sqlBase .= " AND CK.CD_ANO <= :anoFinal";
        $params[':anoFinal'] = $anoFinal;
    }

    if (!empty($dataInicial)) {
        $sqlBase .= " AND CAST(CK.DT_LEITURA AS DATE) >= :dataInicial";
        $params[':dataInicial'] = $dataInicial;
    }

    if (!empty($dataFinal)) {
        $sqlBase .= " AND CAST(CK.DT_LEITURA AS DATE) <= :dataFinal";
        $params[':dataFinal'] = $dataFinal;
    }

    // Contar total de registros
    $sqlCount = "SELECT COUNT(*) AS total " . $sqlBase;
    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

    // Buscar registros com paginação
    // MUDANÇA: Adicionado PM.ID_TIPO_MEDIDOR para construir código formatado
    $sql = "
        SELECT 
            CK.CD_CHAVE,
            CK.CD_PONTO_MEDICAO,
            CK.CD_CODIGO,
            CK.CD_ANO,
            CK.ID_SITUACAO,
            CK.DT_LEITURA,
            CK.VL_DIAMETRO_NOMINAL,
            CK.VL_DIAMETRO_REAL,
            CK.VL_PROJECAO_TAP,
            CK.VL_RAIO_TIP,
            CK.VL_FATOR_VELOCIDADE,
            CK.VL_CORRECAO_DIAMETRO,
            CK.VL_CORRECAO_PROJECAO_TAP,
            CK.VL_AREA_EFETIVA,
            CK.VL_KPC,
            CK.VL_VAZAO,
            CK.VL_PRESSAO,
            CK.VL_TEMPERATURA,
            CK.ID_METODO,
            CK.DS_OBSERVACAO,
            CK.VL_DIAMETRO_NOMINAL,
            PM.DS_NOME AS DS_PONTO_MEDICAO,
            PM.ID_TIPO_MEDIDOR,
            L.CD_LOCALIDADE,
            L.DS_NOME AS DS_LOCALIDADE,
            U.CD_UNIDADE,
            U.DS_NOME AS DS_UNIDADE
        " . $sqlBase . "
        ORDER BY CK.CD_CHAVE DESC
        OFFSET :offset ROWS FETCH NEXT :limite ROWS ONLY
    ";

    $stmt = $pdoSIMP->prepare($sql);
    
    // Bind dos parâmetros
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    
    $stmt->execute();
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $dados,
        'total' => $total,
        'pagina' => $pagina,
        'limite' => $limite,
        'totalPaginas' => ceil($total / $limite)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
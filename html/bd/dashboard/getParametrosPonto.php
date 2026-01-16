<?php
/**
 * SIMP - Dashboard de Saúde
 * Endpoint: Buscar parâmetros de entidade de um ponto de medição
 * 
 * Dado um CD_PONTO_MEDICAO, retorna os parâmetros necessários para
 * redirecionar para operacoes.php com os dropdowns preenchidos
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
    include_once '../conexao.php';
    
    $cdPonto = isset($_GET['cd_ponto']) ? (int)$_GET['cd_ponto'] : 0;
    
    if ($cdPonto <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Código do ponto não informado'
        ]);
        exit;
    }
    
    // Buscar informações do ponto e sua vinculação com entidades
    $sql = "
        SELECT TOP 1
            PM.CD_PONTO_MEDICAO,
            PM.DS_NOME AS PONTO_NOME,
            PM.ID_TIPO_MEDIDOR,
            L.CD_LOCALIDADE,
            L.CD_UNIDADE,
            ET.CD_CHAVE AS TIPO_CD,
            ET.CD_ENTIDADE_TIPO_ID AS TIPO_ID,
            ET.DS_NOME AS TIPO_NOME,
            EV.CD_CHAVE AS VALOR_CD,
            EV.CD_ENTIDADE_VALOR_ID AS VALOR_ID,
            EV.DS_NOME AS VALOR_NOME
        FROM SIMP.dbo.PONTO_MEDICAO PM
        LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
        LEFT JOIN SIMP.dbo.ENTIDADE_VALOR_ITEM EVI ON EVI.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
            AND (EVI.DT_FIM IS NULL OR EVI.DT_FIM >= GETDATE())
        LEFT JOIN SIMP.dbo.ENTIDADE_VALOR EV ON EV.CD_CHAVE = EVI.CD_ENTIDADE_VALOR
        LEFT JOIN SIMP.dbo.ENTIDADE_TIPO ET ON ET.CD_CHAVE = EV.CD_ENTIDADE_TIPO
        WHERE PM.CD_PONTO_MEDICAO = :cdPonto
        ORDER BY EVI.CD_CHAVE DESC
    ";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':cdPonto' => $cdPonto]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        echo json_encode([
            'success' => false,
            'message' => 'Ponto de medição não encontrado'
        ]);
        exit;
    }
    
    // Gerar código formatado do ponto
    $letrasTipo = [1 => 'M', 2 => 'E', 4 => 'P', 6 => 'R', 8 => 'H'];
    $letraTipo = $letrasTipo[$row['ID_TIPO_MEDIDOR']] ?? 'X';
    $codigoPonto = ($row['CD_LOCALIDADE'] ?? '000') . '-' .
                   str_pad($row['CD_PONTO_MEDICAO'], 6, '0', STR_PAD_LEFT) . '-' .
                   $letraTipo . '-' .
                   ($row['CD_UNIDADE'] ?? '00');
    
    // Buscar última data com dados disponíveis
    // Primeiro tenta na MEDICAO_RESUMO_DIARIO (dados processados)
    $ultimaData = null;
    
    try {
        $sqlData = "
            SELECT TOP 1 DT_MEDICAO AS ULTIMA_DATA
            FROM SIMP.dbo.MEDICAO_RESUMO_DIARIO
            WHERE CD_PONTO_MEDICAO = :cdPonto
            ORDER BY DT_MEDICAO DESC
        ";
        $stmtData = $pdoSIMP->prepare($sqlData);
        $stmtData->execute([':cdPonto' => $cdPonto]);
        $rowData = $stmtData->fetch(PDO::FETCH_ASSOC);
        if ($rowData && $rowData['ULTIMA_DATA']) {
            $ultimaData = $rowData['ULTIMA_DATA'];
        }
    } catch (Exception $e) {
        // Tabela não existe, continua para fallback
    }
    
    // Fallback: buscar na REGISTRO_VAZAO_PRESSAO
    if (!$ultimaData) {
        $sqlData = "
            SELECT TOP 1 CAST(DT_LEITURA AS DATE) AS ULTIMA_DATA
            FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
            WHERE CD_PONTO_MEDICAO = :cdPonto
            ORDER BY DT_LEITURA DESC
        ";
        $stmtData = $pdoSIMP->prepare($sqlData);
        $stmtData->execute([':cdPonto' => $cdPonto]);
        $rowData = $stmtData->fetch(PDO::FETCH_ASSOC);
        $ultimaData = $rowData ? $rowData['ULTIMA_DATA'] : null;
    }
    
    // Se ainda não tem, retorna null (não usar data de hoje como fallback)
    if (!$ultimaData) {
        $ultimaData = null;
    }
    
    // Montar resposta
    $response = [
        'success' => true,
        'ponto' => [
            'cd_ponto_medicao' => $row['CD_PONTO_MEDICAO'],
            'nome' => $row['PONTO_NOME'],
            'codigo' => $codigoPonto,
            'id_tipo_medidor' => $row['ID_TIPO_MEDIDOR'],
            'letra_tipo' => $letraTipo
        ],
        'entidade' => [
            'tipo_cd' => $row['TIPO_CD'],
            'tipo_id' => $row['TIPO_ID'],
            'tipo_nome' => $row['TIPO_NOME'],
            'valor_cd' => $row['VALOR_CD'],
            'valor_id' => $row['VALOR_ID'],
            'valor_nome' => $row['VALOR_NOME']
        ],
        'ultima_data' => $ultimaData,
        // URL pronta para redirecionar
        'url_operacoes' => $row['TIPO_CD'] && $row['VALOR_CD'] 
            ? "operacoes.php?tipo={$row['TIPO_CD']}&valor={$row['VALOR_CD']}&valorEntidadeId={$row['VALOR_ID']}&abrirValidacao=1&cdPonto={$cdPonto}&dataValidacao={$ultimaData}"
            : "operacoes.php?abrirValidacao=1&cdPonto={$cdPonto}&dataValidacao={$ultimaData}"
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
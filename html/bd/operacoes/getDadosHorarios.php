<?php
/**
 * SIMP - Buscar dados horÃ¡rios de um ponto em um dia especÃ­fico
 * Retorna mÃ©dia horÃ¡ria (soma/60) e valores min/max por hora
 * Para tipo 6 (NÃ­vel ReservatÃ³rio): retorna max e soma de NR_EXTRAVASOU
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';

    $cdPonto = isset($_GET['cdPonto']) ? (int)$_GET['cdPonto'] : 0;
    $data = isset($_GET['data']) ? $_GET['data'] : '';
    $tipoMedidor = isset($_GET['tipoMedidor']) ? (int)$_GET['tipoMedidor'] : 1;

    if ($cdPonto <= 0 || empty($data)) {
        throw new Exception('ParÃ¢metros invÃ¡lidos');
    }

    // Validar formato da data
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        throw new Exception('Formato de data invÃ¡lido');
    }

    // Definir coluna baseado no tipo de medidor
    $colunasPorTipo = [
        1 => 'VL_VAZAO_EFETIVA',  // Macromedidor
        2 => 'VL_VAZAO_EFETIVA',  // EstaÃ§Ã£o PitomÃ©trica
        4 => 'VL_PRESSAO',        // Medidor PressÃ£o
        6 => 'VL_RESERVATORIO',   // NÃ­vel ReservatÃ³rio
        8 => 'VL_VAZAO_EFETIVA'   // HidrÃ´metro
    ];

    $unidadesPorTipo = [
        1 => 'L/s',
        2 => 'L/s',
        4 => 'mca',
        6 => '%',
        8 => 'L/s'
    ];

    $coluna = $colunasPorTipo[$tipoMedidor] ?? 'VL_VAZAO_EFETIVA';
    $unidade = $unidadesPorTipo[$tipoMedidor] ?? 'L/s';

    // Query diferente para tipo 6 (NÃ­vel ReservatÃ³rio)
    if ($tipoMedidor == 6) {
        // Para nÃ­vel de reservatÃ³rio: max, min e soma de NR_EXTRAVASOU
        $sql = "SELECT 
                    DATEPART(HOUR, DT_LEITURA) AS HORA,
                    MIN(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} END) AS VALOR_MIN,
                    MAX(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} END) AS VALOR_MAX,
                    COUNT(CASE WHEN ID_SITUACAO = 1 THEN 1 END) AS QTD_REGISTROS,
                    COUNT(CASE WHEN ID_SITUACAO = 2 THEN 1 END) AS QTD_INATIVOS,
                    COUNT(CASE WHEN ID_SITUACAO = 1 AND ID_TIPO_REGISTRO = 2 AND ID_TIPO_MEDICAO = 2 THEN 1 END) AS QTD_TRATADOS,
                    SUM(CASE WHEN ID_SITUACAO = 1 THEN ISNULL(NR_EXTRAVASOU, 0) ELSE 0 END) AS SOMA_EXTRAVASOU
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                WHERE CD_PONTO_MEDICAO = :cdPonto
                  AND CAST(DT_LEITURA AS DATE) = :data
                  AND {$coluna} IS NOT NULL
                GROUP BY DATEPART(HOUR, DT_LEITURA)
                ORDER BY HORA";
    } else {
        // Para outros tipos: média, min, max
        $sql = "SELECT 
                    DATEPART(HOUR, DT_LEITURA) AS HORA,
                    SUM(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} ELSE 0 END) / 60.0 AS MEDIA,
                    MIN(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} END) AS VALOR_MIN,
                    MAX(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} END) AS VALOR_MAX,
                    COUNT(CASE WHEN ID_SITUACAO = 1 THEN 1 END) AS QTD_REGISTROS,
                    COUNT(CASE WHEN ID_SITUACAO = 2 THEN 1 END) AS QTD_INATIVOS,
                    COUNT(CASE WHEN ID_SITUACAO = 1 AND ID_TIPO_REGISTRO = 2 AND ID_TIPO_MEDICAO = 2 THEN 1 END) AS QTD_TRATADOS,
                    SUM(CASE WHEN ID_SITUACAO = 2 THEN {$coluna} ELSE 0 END) / NULLIF(COUNT(CASE WHEN ID_SITUACAO = 2 THEN 1 END), 0) AS MEDIA_INATIVOS
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                WHERE CD_PONTO_MEDICAO = :cdPonto
                  AND CAST(DT_LEITURA AS DATE) = :data
                  AND {$coluna} IS NOT NULL
                GROUP BY DATEPART(HOUR, DT_LEITURA)
                ORDER BY HORA";
    }

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([
        ':cdPonto' => $cdPonto,
        ':data' => $data
    ]);

    $dadosHorarios = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dados = [
            'hora' => (int)$row['HORA'],
            'min' => $row['VALOR_MIN'] !== null ? floatval($row['VALOR_MIN']) : null,
            'max' => $row['VALOR_MAX'] !== null ? floatval($row['VALOR_MAX']) : null,
            'qtd_registros' => (int)$row['QTD_REGISTROS'],
            'qtd_inativos' => (int)$row['QTD_INATIVOS'],
            'tratado' => (int)$row['QTD_TRATADOS'] > 0
        ];

        // Adicionar campos específicos por tipo
        if ($tipoMedidor == 6) {
            // Para nível: não tem média, mas tem soma de extravasou
            $dados['media'] = null;
            $dados['soma_extravasou'] = (int)$row['SOMA_EXTRAVASOU'];
            $dados['media_inativos'] = null;
        } else {
            // Para outros: tem média
            $dados['media'] = $row['MEDIA'] !== null ? round(floatval($row['MEDIA']), 2) : null;
            $dados['media_inativos'] = isset($row['MEDIA_INATIVOS']) && $row['MEDIA_INATIVOS'] !== null ? round(floatval($row['MEDIA_INATIVOS']), 2) : null;
        }

        $dadosHorarios[] = $dados;
    }

    // Buscar informaÃ§Ãµes do ponto
    $sqlPonto = "SELECT PM.DS_NOME, PM.ID_TIPO_MEDIDOR, L.CD_LOCALIDADE, L.CD_UNIDADE
                 FROM SIMP.dbo.PONTO_MEDICAO PM
                 LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
                 WHERE PM.CD_PONTO_MEDICAO = :cdPonto";
    $stmtPonto = $pdoSIMP->prepare($sqlPonto);
    $stmtPonto->execute([':cdPonto' => $cdPonto]);
    $ponto = $stmtPonto->fetch(PDO::FETCH_ASSOC);

    // Gerar cÃ³digo do ponto
    $letrasTipo = [1 => 'M', 2 => 'E', 4 => 'P', 6 => 'R', 8 => 'H'];
    $letraTipo = $letrasTipo[$tipoMedidor] ?? 'X';
    $codigoPonto = ($ponto['CD_LOCALIDADE'] ?? '000') . '-' . 
                  str_pad($cdPonto, 6, '0', STR_PAD_LEFT) . '-' . 
                  $letraTipo . '-' . 
                  ($ponto['CD_UNIDADE'] ?? '00');

    echo json_encode([
        'success' => true,
        'ponto' => [
            'cd' => $cdPonto,
            'codigo' => $codigoPonto,
            'nome' => $ponto['DS_NOME'] ?? '',
            'tipo_medidor' => $tipoMedidor
        ],
        'data' => $data,
        'unidade' => $unidade,
        'coluna' => $coluna,
        'dados' => $dadosHorarios
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
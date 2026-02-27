<?php
/**
 * SIMP - Buscar dados horários de um ponto em um dia específico
 * Retorna média horária e valores min/max por hora
 * Para tipo 6 (Nível Reservatório): retorna max e soma de NR_EXTRAVASOU
 * 
 * @version 2.4 - Alterado para usar AVG em vez de SUM/60 para média horária
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';

    $cdPonto = isset($_GET['cdPonto']) ? (int) $_GET['cdPonto'] : 0;
    $data = isset($_GET['data']) ? $_GET['data'] : '';
    $tipoMedidor = isset($_GET['tipoMedidor']) ? (int) $_GET['tipoMedidor'] : 1;

    if ($cdPonto <= 0 || empty($data)) {
        throw new Exception('Parâmetros inválidos');
    }

    // Validar formato da data
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        throw new Exception('Formato de data inválido');
    }

    // Definir coluna baseado no tipo de medidor
    $colunasPorTipo = [
        1 => 'VL_VAZAO_EFETIVA',  // Macromedidor
        2 => 'VL_VAZAO_EFETIVA',  // Estação Pitométrica
        4 => 'VL_PRESSAO',        // Medidor Pressão
        6 => 'VL_RESERVATORIO',   // Nível Reservatório
        8 => 'VL_VAZAO_EFETIVA'   // Hidrômetro
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

    // Query diferente para tipo 6 (Nível Reservatório)
    if ($tipoMedidor == 6) {
        // Para nível: usa MAX como valor principal, não faz média
        $sql = "SELECT 
                DATEPART(HOUR, DT_LEITURA) AS HORA,
                ROUND(MIN(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} END), 0) AS VALOR_MIN,
                ROUND(MAX(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} END), 0) AS VALOR_MAX,
                COUNT(CASE WHEN ID_SITUACAO = 1 THEN 1 END) AS QTD_REGISTROS,
                COUNT(CASE WHEN ID_SITUACAO = 2 THEN 1 END) AS QTD_INATIVOS,
                COUNT(CASE WHEN ID_SITUACAO = 1 AND ID_TIPO_REGISTRO = 2 AND ID_TIPO_MEDICAO = 2 THEN 1 END) AS QTD_TRATADOS,
                SUM(CASE
                    WHEN ID_SITUACAO = 1 AND ID_TIPO_REGISTRO = 2 AND ID_TIPO_MEDICAO = 2
                        THEN ISNULL(NR_EXTRAVASOU, 0)
                    WHEN ID_SITUACAO = 1
                        THEN CASE WHEN ROUND({$coluna}, 0) >= 100 THEN 1 ELSE 0 END
                    ELSE 0
                END) AS SOMA_EXTRAVASOU,
                -- Usuário que tratou (pega o mais recente da hora com ID_TIPO_REGISTRO = 2)
                (SELECT TOP 1 U.DS_NOME
                 FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO R2
                 LEFT JOIN SIMP.dbo.USUARIO U ON U.CD_USUARIO = R2.CD_USUARIO_ULTIMA_ATUALIZACAO
                 WHERE R2.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                   AND CAST(R2.DT_LEITURA AS DATE) = CAST(RVP.DT_LEITURA AS DATE)
                   AND DATEPART(HOUR, R2.DT_LEITURA) = DATEPART(HOUR, RVP.DT_LEITURA)
                   AND R2.ID_SITUACAO = 1
                   AND R2.ID_TIPO_REGISTRO = 2
                   AND R2.ID_TIPO_MEDICAO = 2
                 ORDER BY R2.DT_ULTIMA_ATUALIZACAO DESC
                ) AS DS_USUARIO_TRATOU,
                -- Motivo (evento) do tratamento mais recente
                (SELECT TOP 1 R3.NR_MOTIVO
                 FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO R3
                 WHERE R3.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                   AND CAST(R3.DT_LEITURA AS DATE) = CAST(RVP.DT_LEITURA AS DATE)
                   AND DATEPART(HOUR, R3.DT_LEITURA) = DATEPART(HOUR, RVP.DT_LEITURA)
                   AND R3.ID_SITUACAO = 1
                   AND R3.ID_TIPO_REGISTRO = 2
                   AND R3.ID_TIPO_MEDICAO = 2
                 ORDER BY R3.DT_ULTIMA_ATUALIZACAO DESC
                ) AS NR_MOTIVO_TRATOU,
                -- Observação (causa) do tratamento mais recente
                (SELECT TOP 1 R4.DS_OBSERVACAO
                 FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO R4
                 WHERE R4.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                   AND CAST(R4.DT_LEITURA AS DATE) = CAST(RVP.DT_LEITURA AS DATE)
                   AND DATEPART(HOUR, R4.DT_LEITURA) = DATEPART(HOUR, RVP.DT_LEITURA)
                   AND R4.ID_SITUACAO = 1
                   AND R4.ID_TIPO_REGISTRO = 2
                   AND R4.ID_TIPO_MEDICAO = 2
                 ORDER BY R4.DT_ULTIMA_ATUALIZACAO DESC
                ) AS DS_OBSERVACAO_TRATOU
            FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
            WHERE CD_PONTO_MEDICAO = :cdPonto
              AND CAST(DT_LEITURA AS DATE) = :data
              AND {$coluna} IS NOT NULL
            GROUP BY CD_PONTO_MEDICAO, CAST(DT_LEITURA AS DATE), DATEPART(HOUR, DT_LEITURA)
            ORDER BY HORA";
    } else {
        // Para outros tipos: média usando AVG (média real dos registros válidos)
        $sql = "SELECT 
                    DATEPART(HOUR, DT_LEITURA) AS HORA,
                    AVG(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} ELSE NULL END) AS MEDIA,
                    MIN(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} END) AS VALOR_MIN,
                    MAX(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} END) AS VALOR_MAX,
                    COUNT(CASE WHEN ID_SITUACAO = 1 THEN 1 END) AS QTD_REGISTROS,
                    COUNT(CASE WHEN ID_SITUACAO = 2 THEN 1 END) AS QTD_INATIVOS,
                    COUNT(CASE WHEN ID_SITUACAO = 1 AND ID_TIPO_REGISTRO = 2 AND ID_TIPO_MEDICAO = 2 THEN 1 END) AS QTD_TRATADOS,
                    AVG(CASE WHEN ID_SITUACAO = 2 THEN {$coluna} ELSE NULL END) AS MEDIA_INATIVOS,
                    -- Usuário que tratou (pega o mais recente da hora com ID_TIPO_REGISTRO = 2)
                    (SELECT TOP 1 U.DS_NOME
                    FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO R2
                    LEFT JOIN SIMP.dbo.USUARIO U ON U.CD_USUARIO = R2.CD_USUARIO_ULTIMA_ATUALIZACAO
                    WHERE R2.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                    AND CAST(R2.DT_LEITURA AS DATE) = CAST(RVP.DT_LEITURA AS DATE)
                    AND DATEPART(HOUR, R2.DT_LEITURA) = DATEPART(HOUR, RVP.DT_LEITURA)
                    AND R2.ID_SITUACAO = 1
                    AND R2.ID_TIPO_REGISTRO = 2
                    AND R2.ID_TIPO_MEDICAO = 2
                    ORDER BY R2.DT_ULTIMA_ATUALIZACAO DESC
                    ) AS DS_USUARIO_TRATOU,
                    -- Motivo (evento) do tratamento mais recente
                    (SELECT TOP 1 R3.NR_MOTIVO
                    FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO R3
                    WHERE R3.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                    AND CAST(R3.DT_LEITURA AS DATE) = CAST(RVP.DT_LEITURA AS DATE)
                    AND DATEPART(HOUR, R3.DT_LEITURA) = DATEPART(HOUR, RVP.DT_LEITURA)
                    AND R3.ID_SITUACAO = 1
                    AND R3.ID_TIPO_REGISTRO = 2
                    AND R3.ID_TIPO_MEDICAO = 2
                    ORDER BY R3.DT_ULTIMA_ATUALIZACAO DESC
                    ) AS NR_MOTIVO_TRATOU,
                    -- Observação (causa) do tratamento mais recente
                    (SELECT TOP 1 R4.DS_OBSERVACAO
                    FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO R4
                    WHERE R4.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                    AND CAST(R4.DT_LEITURA AS DATE) = CAST(RVP.DT_LEITURA AS DATE)
                    AND DATEPART(HOUR, R4.DT_LEITURA) = DATEPART(HOUR, RVP.DT_LEITURA)
                    AND R4.ID_SITUACAO = 1
                    AND R4.ID_TIPO_REGISTRO = 2
                    AND R4.ID_TIPO_MEDICAO = 2
                    ORDER BY R4.DT_ULTIMA_ATUALIZACAO DESC
                    ) AS DS_OBSERVACAO_TRATOU
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
                WHERE CD_PONTO_MEDICAO = :cdPonto
                AND CAST(DT_LEITURA AS DATE) = :data
                AND {$coluna} IS NOT NULL
                GROUP BY CD_PONTO_MEDICAO, CAST(DT_LEITURA AS DATE), DATEPART(HOUR, DT_LEITURA)
                ORDER BY HORA";
    }

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([
        ':cdPonto' => $cdPonto,
        ':data' => $data
    ]);

    $dadosHorarios = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // IMPORTANTE: Usar snake_case nos nomes dos campos para compatibilidade com frontend
        $dados = [
            'hora' => (int) $row['HORA'],
            'min' => $row['VALOR_MIN'] !== null ? floatval($row['VALOR_MIN']) : null,
            'max' => $row['VALOR_MAX'] !== null ? floatval($row['VALOR_MAX']) : null,
            'qtd_registros' => (int) $row['QTD_REGISTROS'],
            'qtd_inativos' => (int) $row['QTD_INATIVOS'],
            'tratado' => (int) $row['QTD_TRATADOS'] > 0,
            'motivo_tratou' => isset($row['NR_MOTIVO_TRATOU']) ? (int) $row['NR_MOTIVO_TRATOU'] : null,
            'observacao_tratou' => $row['DS_OBSERVACAO_TRATOU'] ?? null
        ];

        // Adicionar campos específicos por tipo
        if ($tipoMedidor == 6) {
            // Para nível: não tem média, mas tem soma de extravasou
            $dados['media'] = null;
            $dados['soma_extravasou'] = (int) $row['SOMA_EXTRAVASOU'];
            $dados['media_inativos'] = null;
            $dados['usuario_tratou'] = $row['DS_USUARIO_TRATOU'] ?? null;
        } else {
            // Para outros: tem média
            $dados['media'] = $row['MEDIA'] !== null ? round(floatval($row['MEDIA']), 2) : null;
            $dados['media_inativos'] = isset($row['MEDIA_INATIVOS']) && $row['MEDIA_INATIVOS'] !== null ? round(floatval($row['MEDIA_INATIVOS']), 2) : null;
            $dados['usuario_tratou'] = $row['DS_USUARIO_TRATOU'] ?? null;
        }

        $dadosHorarios[] = $dados;
    }

    // Buscar informações do ponto
    $sqlPonto = "SELECT PM.DS_NOME, PM.ID_TIPO_MEDIDOR, L.CD_LOCALIDADE, L.CD_UNIDADE
                 FROM SIMP.dbo.PONTO_MEDICAO PM
                 LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
                 WHERE PM.CD_PONTO_MEDICAO = :cdPonto";
    $stmtPonto = $pdoSIMP->prepare($sqlPonto);
    $stmtPonto->execute([':cdPonto' => $cdPonto]);
    $ponto = $stmtPonto->fetch(PDO::FETCH_ASSOC);

    // Gerar código do ponto
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
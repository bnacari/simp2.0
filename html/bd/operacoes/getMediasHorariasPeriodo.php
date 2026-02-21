<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Buscar Médias Horárias de um Ponto para o Período Completo
 * 
 * Retorna a média horária (ou máximo para reservatório) agrupada por dia+hora,
 * utilizado pelo gráfico popup em operacoes.php.
 * 
 * Parâmetros GET:
 *   - cd_ponto (int): Código do ponto de medição
 *   - data_inicio (string): Data início (YYYY-MM-DD)
 *   - data_fim (string): Data fim (YYYY-MM-DD)
 *   - tipo_medidor (int): Tipo do medidor (1,2,4,6,8)
 * 
 * Retorno JSON:
 *   - success (bool)
 *   - dados (array): Array de objetos {data, hora, media}
 *   - unidade (string): Unidade de medida
 * 
 * @author Bruno - SIMP
 * @version 1.0
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

try {
    require_once '../verificarAuth.php';

    include_once '../conexao.php';

    // ============================================
    // Validação dos parâmetros
    // ============================================
    $cdPonto = isset($_GET['cd_ponto']) && $_GET['cd_ponto'] !== '' ? (int) $_GET['cd_ponto'] : null;
    $dataInicio = isset($_GET['data_inicio']) ? trim($_GET['data_inicio']) : null;
    $dataFim = isset($_GET['data_fim']) ? trim($_GET['data_fim']) : null;
    $tipoMedidor = isset($_GET['tipo_medidor']) && $_GET['tipo_medidor'] !== '' ? (int) $_GET['tipo_medidor'] : null;

    if (empty($cdPonto)) {
        throw new Exception('Ponto de medição não informado');
    }
    if (empty($dataInicio) || empty($dataFim)) {
        throw new Exception('Período não informado');
    }

    // ============================================
    // Determinar coluna e unidade conforme tipo de medidor
    // ============================================
    $colunasPorTipo = [
        1 => 'VL_VAZAO_EFETIVA',
        2 => 'VL_VAZAO_EFETIVA',
        4 => 'VL_PRESSAO',
        6 => 'VL_RESERVATORIO',
        8 => 'VL_VAZAO_EFETIVA'
    ];
    $unidadesPorTipo = [
        1 => 'L/s',
        2 => 'L/s',
        4 => 'mca',
        6 => '%',
        8 => 'L/s'
    ];

    // Se tipo não informado, buscar do banco
    if (empty($tipoMedidor)) {
        $sqlTipo = "SELECT ID_TIPO_MEDIDOR FROM SIMP.dbo.PONTO_MEDICAO WHERE CD_PONTO_MEDICAO = ?";
        $stmtTipo = $pdoSIMP->prepare($sqlTipo);
        $stmtTipo->execute([$cdPonto]);
        $rowTipo = $stmtTipo->fetch(PDO::FETCH_ASSOC);
        $tipoMedidor = $rowTipo ? (int) $rowTipo['ID_TIPO_MEDIDOR'] : 1;
    }

    $coluna = $colunasPorTipo[$tipoMedidor] ?? 'VL_VAZAO_EFETIVA';
    $unidade = $unidadesPorTipo[$tipoMedidor] ?? 'L/s';

    // ============================================
    // Query: Médias horárias agrupadas por dia + hora
    // Para tipo 6 (reservatório), usa MAX ao invés de AVG
    // ============================================
    if ($tipoMedidor == 6) {
        $sql = "SELECT 
                    CONVERT(VARCHAR(10), CAST(DT_LEITURA AS DATE), 120) AS DT_DIA,
                    DATEPART(HOUR, DT_LEITURA) AS HORA,
                    MAX(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} END) AS MEDIA
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                WHERE CD_PONTO_MEDICAO = :cdPonto
                  AND DT_LEITURA >= :dataInicio
                  AND DT_LEITURA <= :dataFim + ' 23:59:59'
                  AND {$coluna} IS NOT NULL
                GROUP BY CAST(DT_LEITURA AS DATE), DATEPART(HOUR, DT_LEITURA)
                ORDER BY CAST(DT_LEITURA AS DATE), DATEPART(HOUR, DT_LEITURA)";
    } else {
        $sql = "SELECT 
                    CONVERT(VARCHAR(10), CAST(DT_LEITURA AS DATE), 120) AS DT_DIA,
                    DATEPART(HOUR, DT_LEITURA) AS HORA,
                    AVG(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} ELSE NULL END) AS MEDIA
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                WHERE CD_PONTO_MEDICAO = :cdPonto
                  AND DT_LEITURA >= :dataInicio
                  AND DT_LEITURA <= :dataFim + ' 23:59:59'
                  AND {$coluna} IS NOT NULL
                GROUP BY CAST(DT_LEITURA AS DATE), DATEPART(HOUR, DT_LEITURA)
                HAVING COUNT(CASE WHEN ID_SITUACAO = 1 THEN 1 END) > 0
                ORDER BY CAST(DT_LEITURA AS DATE), DATEPART(HOUR, DT_LEITURA)";
    }

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([
        ':cdPonto' => $cdPonto,
        ':dataInicio' => $dataInicio,
        ':dataFim' => $dataFim
    ]);

    $dados = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dados[] = [
            'data' => $row['DT_DIA'],
            'hora' => (int) $row['HORA'],
            'media' => $row['MEDIA'] !== null ? round(floatval($row['MEDIA']), 2) : null
        ];
    }

    echo json_encode([
        'success' => true,
        'dados' => $dados,
        'unidade' => $unidade,
        'tipo_medidor' => $tipoMedidor,
        'total_registros' => count($dados)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
<?php
/**
 * SIMP - Dashboard de Saúde
 * Endpoint: Evolução de Score de um Ponto Específico
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
    include_once '../conexao.php';
    
    // Parâmetros
    $cdPonto = isset($_GET['cd_ponto']) ? (int)$_GET['cd_ponto'] : 0;
    $dias = isset($_GET['dias']) ? (int)$_GET['dias'] : 7;
    $dias = max(1, min(90, $dias));
    
    if ($cdPonto <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Código do ponto não informado',
            'data' => []
        ]);
        exit;
    }
    
    // Verificar se tabela MEDICAO_RESUMO_DIARIO existe
    $checkTable = $pdoSIMP->query("SELECT OBJECT_ID('MEDICAO_RESUMO_DIARIO', 'U') AS TableExists");
    $tableExists = $checkTable->fetch(PDO::FETCH_ASSOC)['TableExists'];
    
    if ($tableExists) {
        // Buscar última data disponível
        $sqlMaxData = "SELECT MAX(DT_MEDICAO) AS DATA_MAX FROM MEDICAO_RESUMO_DIARIO WHERE CD_PONTO_MEDICAO = :cdPonto";
        $stmtMax = $pdoSIMP->prepare($sqlMaxData);
        $stmtMax->execute([':cdPonto' => $cdPonto]);
        $dataMax = $stmtMax->fetch(PDO::FETCH_ASSOC)['DATA_MAX'];
        
        if (!$dataMax) {
            // Ponto não tem dados
            echo json_encode([
                'success' => true,
                'data' => [],
                'message' => 'Ponto não possui dados de resumo'
            ]);
            exit;
        }
        
        $dataInicio = date('Y-m-d', strtotime("-{$dias} days", strtotime($dataMax)));
        
        $sql = "
            SELECT 
                DT_MEDICAO,
                VL_SCORE_SAUDE,
                VL_MEDIA_DIARIA,
                QTD_REGISTROS,
                FL_ANOMALIA,
                DS_TIPO_PROBLEMA,
                FL_SEM_COMUNICACAO,
                FL_VALOR_CONSTANTE,
                FL_VALOR_NEGATIVO,
                FL_FORA_FAIXA,
                FL_SPIKE
            FROM MEDICAO_RESUMO_DIARIO
            WHERE CD_PONTO_MEDICAO = :cdPonto
              AND DT_MEDICAO >= :dataInicio
              AND DT_MEDICAO <= :dataMax
            ORDER BY DT_MEDICAO
        ";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':cdPonto' => $cdPonto,
            ':dataInicio' => $dataInicio,
            ':dataMax' => $dataMax
        ]);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Fallback: calcular da tabela REGISTRO_VAZAO_PRESSAO
        // IMPORTANTE: Média diária = SUM(valores válidos) / 1440 (minutos no dia)
        $sql = "
            SELECT 
                CAST(DT_LEITURA AS DATE) AS DT_MEDICAO,
                COUNT(*) AS QTD_REGISTROS,
                CAST(SUM(VL_VAZAO_EFETIVA) AS DECIMAL(18,4)) / 1440.0 AS VL_MEDIA_DIARIA,
                CASE 
                    WHEN COUNT(*) < 720 THEN 3
                    WHEN COUNT(DISTINCT VL_VAZAO_EFETIVA) <= 5 THEN 5
                    ELSE 8
                END AS VL_SCORE_SAUDE,
                CASE WHEN COUNT(*) < 720 THEN 1 ELSE 0 END AS FL_ANOMALIA,
                CASE 
                    WHEN COUNT(*) < 720 THEN 'COMUNICACAO'
                    WHEN COUNT(DISTINCT VL_VAZAO_EFETIVA) <= 5 THEN 'MEDIDOR'
                    ELSE NULL
                END AS DS_TIPO_PROBLEMA,
                CASE WHEN COUNT(*) < 720 THEN 1 ELSE 0 END AS FL_SEM_COMUNICACAO,
                CASE WHEN COUNT(DISTINCT VL_VAZAO_EFETIVA) <= 5 THEN 1 ELSE 0 END AS FL_VALOR_CONSTANTE,
                0 AS FL_VALOR_NEGATIVO,
                0 AS FL_FORA_FAIXA,
                0 AS FL_SPIKE
            FROM REGISTRO_VAZAO_PRESSAO
            WHERE CD_PONTO_MEDICAO = :cdPonto
              AND DT_LEITURA >= DATEADD(DAY, -:dias, GETDATE())
            GROUP BY CAST(DT_LEITURA AS DATE)
            ORDER BY CAST(DT_LEITURA AS DATE)
        ";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':cdPonto' => $cdPonto, ':dias' => $dias]);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $dados,
        'cd_ponto' => $cdPonto,
        'dias' => $dias
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ], JSON_UNESCAPED_UNICODE);
}
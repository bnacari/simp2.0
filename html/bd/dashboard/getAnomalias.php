<?php
/**
 * SIMP - Dashboard de Saúde
 * Endpoint: Anomalias Recentes
 * 
 * Usa a view VW_ANOMALIAS_RECENTES
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
    include_once '../conexao.php';
    
    // Parâmetros
    $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 20;
    $limite = max(1, min(100, $limite));
    $filtro = isset($_GET['filtro']) ? trim($_GET['filtro']) : 'pendentes'; // todas, pendentes, tratadas
    $tipoProblema = isset($_GET['tipo']) ? trim($_GET['tipo']) : ''; // COMUNICACAO, MEDIDOR, HIDRAULICO, VERIFICAR
    $cdPonto = isset($_GET['cd_ponto']) ? (int)$_GET['cd_ponto'] : null;
    $dias = isset($_GET['dias']) ? (int)$_GET['dias'] : 7;
    
    $dataInicio = date('Y-m-d', strtotime("-{$dias} days"));
    $dataFim = date('Y-m-d');
    
    // Verificar se a view existe
    $checkView = $pdoSIMP->query("SELECT OBJECT_ID('VW_ANOMALIAS_RECENTES', 'V') AS ViewExists");
    $viewExists = $checkView->fetch(PDO::FETCH_ASSOC)['ViewExists'];
    
    if ($viewExists) {
        // Usar a view existente
        $where = [];
        $params = [];
        
        if ($filtro === 'pendentes') {
            $where[] = "STATUS_TRATAMENTO = 'Pendente'";
        } elseif ($filtro === 'tratadas') {
            $where[] = "STATUS_TRATAMENTO = 'Tratado'";
        }
        
        if (!empty($tipoProblema)) {
            $where[] = "DS_TIPO_PROBLEMA = :tipoProblema";
            $params[':tipoProblema'] = $tipoProblema;
        }
        
        if ($cdPonto) {
            $where[] = "CD_PONTO_MEDICAO = :cdPonto";
            $params[':cdPonto'] = $cdPonto;
        }
        
        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT TOP {$limite} * FROM VW_ANOMALIAS_RECENTES {$whereClause} ORDER BY DT_MEDICAO DESC, VL_SCORE_SAUDE ASC";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute($params);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Verificar se tabela MEDICAO_RESUMO_DIARIO existe
        $checkTable = $pdoSIMP->query("SELECT OBJECT_ID('MEDICAO_RESUMO_DIARIO', 'U') AS TableExists");
        $tableExists = $checkTable->fetch(PDO::FETCH_ASSOC)['TableExists'];
        
        if ($tableExists) {
            // Fallback: calcular da tabela de resumo diário
            $where = ["MRD.FL_ANOMALIA = 1", "MRD.DT_MEDICAO >= :dataInicio", "MRD.DT_MEDICAO <= :dataFim"];
            $params = [':dataInicio' => $dataInicio, ':dataFim' => $dataFim];
            
            if ($filtro === 'pendentes') {
                $where[] = "MRD.ID_SITUACAO = 1";
            } elseif ($filtro === 'tratadas') {
                $where[] = "MRD.ID_SITUACAO = 2";
            }
            
            if (!empty($tipoProblema)) {
                $where[] = "MRD.DS_TIPO_PROBLEMA = :tipoProblema";
                $params[':tipoProblema'] = $tipoProblema;
            }
            
            if ($cdPonto) {
                $where[] = "MRD.CD_PONTO_MEDICAO = :cdPonto";
                $params[':cdPonto'] = $cdPonto;
            }
            
            $whereClause = 'WHERE ' . implode(' AND ', $where);
            
            $sql = "
                SELECT TOP {$limite}
                    MRD.CD_PONTO_MEDICAO,
                    PM.DS_NOME AS NOME_PONTO,
                    MRD.DT_MEDICAO,
                    MRD.ID_TIPO_MEDIDOR,
                    MRD.DS_TIPO_PROBLEMA,
                    MRD.DS_ANOMALIAS,
                    MRD.VL_SCORE_SAUDE,
                    MRD.VL_MEDIA_DIARIA,
                    MRD.VL_DESVIO_HISTORICO,
                    MRD.ID_SITUACAO,
                    CASE WHEN MRD.ID_SITUACAO = 2 THEN 'Tratado' ELSE 'Pendente' END AS STATUS_TRATAMENTO
                FROM SIMP.dbo.MEDICAO_RESUMO_DIARIO MRD
                INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = MRD.CD_PONTO_MEDICAO
                {$whereClause}
                ORDER BY MRD.DT_MEDICAO DESC, MRD.VL_SCORE_SAUDE ASC
            ";
            
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->execute($params);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Fallback final: buscar registros com problemas da tabela original
            $sql = "
                SELECT TOP {$limite}
                    RVP.CD_PONTO_MEDICAO,
                    PM.DS_NOME AS NOME_PONTO,
                    CAST(RVP.DT_LEITURA AS DATE) AS DT_MEDICAO,
                    PM.ID_TIPO_MEDIDOR,
                    CASE 
                        WHEN COUNT(*) < 720 THEN 'COMUNICACAO'
                        WHEN COUNT(DISTINCT RVP.VL_VAZAO_EFETIVA) <= 5 THEN 'MEDIDOR'
                        WHEN MIN(RVP.VL_VAZAO_EFETIVA) < 0 THEN 'HIDRAULICO'
                        ELSE 'VERIFICAR'
                    END AS DS_TIPO_PROBLEMA,
                    'Anomalia detectada' AS DS_ANOMALIAS,
                    CASE 
                        WHEN COUNT(*) < 720 THEN 3
                        WHEN COUNT(DISTINCT RVP.VL_VAZAO_EFETIVA) <= 5 THEN 5
                        ELSE 6
                    END AS VL_SCORE_SAUDE,
                    AVG(RVP.VL_VAZAO_EFETIVA) AS VL_MEDIA_DIARIA,
                    0 AS VL_DESVIO_HISTORICO,
                    1 AS ID_SITUACAO,
                    'Pendente' AS STATUS_TRATAMENTO
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
                INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                WHERE CAST(RVP.DT_LEITURA AS DATE) >= :dataInicio
                  AND CAST(RVP.DT_LEITURA AS DATE) <= :dataFim
                GROUP BY RVP.CD_PONTO_MEDICAO, PM.DS_NOME, PM.ID_TIPO_MEDIDOR, CAST(RVP.DT_LEITURA AS DATE)
                HAVING COUNT(*) < 1440 * 0.8 OR COUNT(DISTINCT RVP.VL_VAZAO_EFETIVA) <= 5
                ORDER BY CAST(RVP.DT_LEITURA AS DATE) DESC
            ";
            
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->execute([':dataInicio' => $dataInicio, ':dataFim' => $dataFim]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // Calcular totais por tipo de problema
    $totaisPorTipo = [
        'COMUNICACAO' => 0,
        'MEDIDOR' => 0,
        'HIDRAULICO' => 0,
        'VERIFICAR' => 0
    ];
    
    foreach ($dados as $d) {
        $tipo = $d['DS_TIPO_PROBLEMA'] ?? 'VERIFICAR';
        if (isset($totaisPorTipo[$tipo])) {
            $totaisPorTipo[$tipo]++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $dados,
        'total' => count($dados),
        'totaisPorTipo' => $totaisPorTipo,
        'filtro' => $filtro
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ], JSON_UNESCAPED_UNICODE);
}

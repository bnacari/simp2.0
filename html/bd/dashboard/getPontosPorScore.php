<?php
/**
 * SIMP - Dashboard de Saúde
 * Endpoint: Pontos por Score de Saúde
 * 
 * Usa a view VW_PONTOS_POR_SCORE_SAUDE
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
    include_once '../conexao.php';
    
    // Parâmetros
    $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 20;
    $limite = max(1, min(100, $limite));
    $status = isset($_GET['status']) ? trim($_GET['status']) : ''; // CRITICO,ALERTA,SAUDAVEL
    $tipoMedidor = isset($_GET['tipo_medidor']) ? (int)$_GET['tipo_medidor'] : null;
    $ordenar = isset($_GET['ordenar']) ? trim($_GET['ordenar']) : 'score'; // score, anomalias, nome
    $ordem = isset($_GET['ordem']) && strtoupper($_GET['ordem']) === 'DESC' ? 'DESC' : 'ASC';
    
    // Buscar última data disponível nos dados (usado em todos os casos)
    $ultimaDataDisponivel = null;
    try {
        $sqlMaxData = "SELECT MAX(DT_MEDICAO) AS DATA_MAX FROM MEDICAO_RESUMO_DIARIO";
        $stmtMax = $pdoSIMP->query($sqlMaxData);
        $rowMax = $stmtMax->fetch(PDO::FETCH_ASSOC);
        if ($rowMax && $rowMax['DATA_MAX']) {
            $ultimaDataDisponivel = $rowMax['DATA_MAX'];
        }
    } catch (Exception $e) {
        // Tabela não existe, tentar REGISTRO_VAZAO_PRESSAO
        try {
            $sqlMaxData = "SELECT MAX(CAST(DT_LEITURA AS DATE)) AS DATA_MAX FROM REGISTRO_VAZAO_PRESSAO";
            $stmtMax = $pdoSIMP->query($sqlMaxData);
            $rowMax = $stmtMax->fetch(PDO::FETCH_ASSOC);
            if ($rowMax && $rowMax['DATA_MAX']) {
                $ultimaDataDisponivel = $rowMax['DATA_MAX'];
            }
        } catch (Exception $e2) {
            // Ignorar
        }
    }
    
    // Verificar se a view existe
    $checkView = $pdoSIMP->query("SELECT OBJECT_ID('VW_PONTOS_POR_SCORE_SAUDE', 'V') AS ViewExists");
    $viewExists = $checkView->fetch(PDO::FETCH_ASSOC)['ViewExists'];
    
    if ($viewExists) {
        // Usar a view existente
        $where = [];
        $params = [];
        
        if (!empty($status)) {
            $statusList = explode(',', $status);
            $placeholders = [];
            foreach ($statusList as $i => $s) {
                $placeholders[] = ":status{$i}";
                $params[":status{$i}"] = trim($s);
            }
            $where[] = "STATUS_SAUDE IN (" . implode(',', $placeholders) . ")";
        }
        
        if ($tipoMedidor) {
            $where[] = "ID_TIPO_MEDIDOR = :tipoMedidor";
            $params[':tipoMedidor'] = $tipoMedidor;
        }
        
        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Ordenação
        $orderBy = 'SCORE_MEDIO ASC'; // Padrão: menores scores primeiro
        if ($ordenar === 'anomalias') {
            $orderBy = "DIAS_COM_ANOMALIA {$ordem}";
        } elseif ($ordenar === 'nome') {
            $orderBy = "NOME_PONTO {$ordem}";
        } elseif ($ordem === 'DESC') {
            $orderBy = 'SCORE_MEDIO DESC';
        }
        
        $sql = "SELECT TOP {$limite} * FROM VW_PONTOS_POR_SCORE_SAUDE {$whereClause} ORDER BY {$orderBy}";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute($params);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Fallback: calcular diretamente
        
        // Verificar se tabela MEDICAO_RESUMO_DIARIO existe
        $checkTable = $pdoSIMP->query("SELECT OBJECT_ID('MEDICAO_RESUMO_DIARIO', 'U') AS TableExists");
        $tableExists = $checkTable->fetch(PDO::FETCH_ASSOC)['TableExists'];
        
        if ($tableExists) {
            // Buscar última data disponível nos dados
            $sqlMaxData = "SELECT MAX(DT_MEDICAO) AS DATA_MAX FROM MEDICAO_RESUMO_DIARIO";
            $stmtMax = $pdoSIMP->query($sqlMaxData);
            $dataMax = $stmtMax->fetch(PDO::FETCH_ASSOC)['DATA_MAX'];
            
            if ($dataMax) {
                $dataFim = $dataMax;
                $dataInicio = date('Y-m-d', strtotime('-7 days', strtotime($dataMax)));
            } else {
                $dataFim = date('Y-m-d');
                $dataInicio = date('Y-m-d', strtotime('-7 days'));
            }
            
            $sql = "
                SELECT TOP {$limite}
                    MRD.CD_PONTO_MEDICAO,
                    PM.DS_NOME AS NOME_PONTO,
                    PM.ID_TIPO_MEDIDOR,
                    CASE PM.ID_TIPO_MEDIDOR
                        WHEN 1 THEN 'M - Macromedidor'
                        WHEN 2 THEN 'E - Estação Pitométrica'
                        WHEN 4 THEN 'P - Medidor Pressão'
                        WHEN 6 THEN 'R - Nível Reservatório'
                        WHEN 8 THEN 'H - Hidrômetro'
                        ELSE 'X - Desconhecido'
                    END AS TIPO_MEDIDOR,
                    ROUND(AVG(CAST(MRD.VL_SCORE_SAUDE AS DECIMAL(5,2))), 2) AS SCORE_MEDIO,
                    MIN(MRD.VL_SCORE_SAUDE) AS SCORE_MINIMO,
                    CASE 
                        WHEN AVG(CAST(MRD.VL_SCORE_SAUDE AS DECIMAL(5,2))) >= 8 THEN 'SAUDAVEL'
                        WHEN AVG(CAST(MRD.VL_SCORE_SAUDE AS DECIMAL(5,2))) >= 5 THEN 'ALERTA'
                        ELSE 'CRITICO'
                    END AS STATUS_SAUDE,
                    CASE 
                        WHEN AVG(CAST(MRD.VL_SCORE_SAUDE AS DECIMAL(5,2))) >= 8 THEN '#22c55e'
                        WHEN AVG(CAST(MRD.VL_SCORE_SAUDE AS DECIMAL(5,2))) >= 5 THEN '#f59e0b'
                        ELSE '#dc2626'
                    END AS COR_STATUS,
                    COUNT(*) AS DIAS_ANALISADOS,
                    SUM(CASE WHEN MRD.FL_SEM_COMUNICACAO = 1 THEN 1 ELSE 0 END) AS DIAS_SEM_COMUNICACAO,
                    SUM(CASE WHEN MRD.FL_VALOR_CONSTANTE = 1 THEN 1 ELSE 0 END) AS DIAS_VALOR_CONSTANTE,
                    SUM(CASE WHEN MRD.FL_ANOMALIA = 1 THEN 1 ELSE 0 END) AS DIAS_COM_ANOMALIA,
                    ROUND(AVG(MRD.VL_MEDIA_DIARIA), 2) AS MEDIA_PERIODO
                FROM SIMP.dbo.MEDICAO_RESUMO_DIARIO MRD
                INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = MRD.CD_PONTO_MEDICAO
                WHERE MRD.DT_MEDICAO >= :dataInicio
                  AND MRD.DT_MEDICAO <= :dataFim
                GROUP BY MRD.CD_PONTO_MEDICAO, PM.DS_NOME, PM.ID_TIPO_MEDIDOR
                HAVING AVG(CAST(MRD.VL_SCORE_SAUDE AS DECIMAL(5,2))) < 8
                ORDER BY SCORE_MEDIO ASC
            ";
            
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->execute([':dataInicio' => $dataInicio, ':dataFim' => $dataFim]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Fallback: retornar lista de pontos básica
            $sql = "
                SELECT TOP {$limite}
                    PM.CD_PONTO_MEDICAO,
                    PM.DS_NOME AS NOME_PONTO,
                    PM.ID_TIPO_MEDIDOR,
                    CASE PM.ID_TIPO_MEDIDOR
                        WHEN 1 THEN 'M - Macromedidor'
                        WHEN 2 THEN 'E - Estação Pitométrica'
                        WHEN 4 THEN 'P - Medidor Pressão'
                        WHEN 6 THEN 'R - Nível Reservatório'
                        WHEN 8 THEN 'H - Hidrômetro'
                        ELSE 'X - Desconhecido'
                    END AS TIPO_MEDIDOR,
                    7.5 AS SCORE_MEDIO,
                    5 AS SCORE_MINIMO,
                    'ALERTA' AS STATUS_SAUDE,
                    '#f59e0b' AS COR_STATUS,
                    7 AS DIAS_ANALISADOS,
                    0 AS DIAS_SEM_COMUNICACAO,
                    0 AS DIAS_VALOR_CONSTANTE,
                    0 AS DIAS_COM_ANOMALIA,
                    0 AS MEDIA_PERIODO
                FROM SIMP.dbo.PONTO_MEDICAO PM
                WHERE PM.DT_DESATIVACAO IS NULL
                ORDER BY PM.DS_NOME
            ";
            
            $stmt = $pdoSIMP->query($sql);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $dados,
        'total' => count($dados),
        'ultima_data' => $ultimaDataDisponivel
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ], JSON_UNESCAPED_UNICODE);
}
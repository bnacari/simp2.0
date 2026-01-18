<?php
/**
 * SIMP - Dashboard de Saúde
 * Endpoint: Resumo Geral (KPIs principais)
 * 
 * Usa a view VW_DASHBOARD_RESUMO_GERAL
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
    include_once '../conexao.php';
    
    // Parâmetros
    $dias = isset($_GET['dias']) ? (int)$_GET['dias'] : 7;
    $dias = max(1, min(90, $dias)); // Limitar entre 1 e 90 dias
    
    // Verificar se a tabela de cache existe (mais rápida que view)
    $checkCache = $pdoSIMP->query("SELECT OBJECT_ID('TB_DASHBOARD_RESUMO_CACHE', 'U') AS CacheExists");
    $cacheExists = $checkCache->fetch(PDO::FETCH_ASSOC)['CacheExists'];
    
    // Verificar se a view existe (fallback)
    $checkView = $pdoSIMP->query("SELECT OBJECT_ID('VW_DASHBOARD_RESUMO_GERAL', 'V') AS ViewExists");
    $viewExists = $checkView->fetch(PDO::FETCH_ASSOC)['ViewExists'];
    
    // Buscar última data disponível (sempre necessário)
    $dataFim = date('Y-m-d');
    $dataInicio = date('Y-m-d', strtotime("-{$dias} days"));
    
    try {
        $sqlMaxData = "SELECT MAX(DT_MEDICAO) AS DATA_MAX FROM MEDICAO_RESUMO_DIARIO";
        $stmtMax = $pdoSIMP->query($sqlMaxData);
        $dataMax = $stmtMax->fetch(PDO::FETCH_ASSOC)['DATA_MAX'];
        if ($dataMax) {
            $dataFim = $dataMax;
            $dataInicio = date('Y-m-d', strtotime("-{$dias} days", strtotime($dataMax)));
        }
    } catch (Exception $e) {
        // Tabela não existe, usar datas padrão
    }
    
    if ($cacheExists) {
        // MELHOR OPÇÃO: Usar tabela de cache (instantâneo!)
        $sql = "SELECT TOP 1 * FROM TB_DASHBOARD_RESUMO_CACHE ORDER BY DT_ATUALIZACAO DESC";
        $stmt = $pdoSIMP->query($sql);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$dados) {
            // Cache vazio, forçar fallback
            $cacheExists = false;
        }
    }
    
    if (!$cacheExists && $viewExists) {
        // FALLBACK: Usar view (pode ser lenta)
        $sql = "SELECT * FROM VW_DASHBOARD_RESUMO_GERAL";
        $stmt = $pdoSIMP->query($sql);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Adicionar datas se não estiverem na view
        if ($dados && !isset($dados['DATA_INICIO'])) {
            $dados['DATA_INICIO'] = $dataInicio;
            $dados['DATA_FIM'] = $dataFim;
        }
    } elseif (!$cacheExists) {
        // Fallback: calcular diretamente das tabelas de resumo
        $dataInicio = date('Y-m-d', strtotime("-{$dias} days"));
        $dataFim = date('Y-m-d');
        
        // Verificar se tabela MEDICAO_RESUMO_DIARIO existe
        $checkTable = $pdoSIMP->query("SELECT OBJECT_ID('MEDICAO_RESUMO_DIARIO', 'U') AS TableExists");
        $tableExists = $checkTable->fetch(PDO::FETCH_ASSOC)['TableExists'];
        
        if ($tableExists) {
            // Buscar última data disponível
            $sqlMaxData = "SELECT MAX(DT_MEDICAO) AS DATA_MAX FROM MEDICAO_RESUMO_DIARIO";
            $stmtMax = $pdoSIMP->query($sqlMaxData);
            $dataMax = $stmtMax->fetch(PDO::FETCH_ASSOC)['DATA_MAX'];
            
            if ($dataMax) {
                $dataFim = $dataMax;
                $dataInicio = date('Y-m-d', strtotime("-{$dias} days", strtotime($dataMax)));
            }
            
            // Query que agrupa por ponto e calcula a média do score
            // Assim a contagem fica consistente com o monitoramento
            // IMPORTANTE: Conta PONTOS com problemas, não soma dias
            $sql = "
                SELECT 
                    COUNT(*) AS TOTAL_PONTOS,
                    SUM(TOTAL_MEDICOES) AS TOTAL_MEDICOES,
                    ROUND(AVG(SCORE_MEDIO), 2) AS SCORE_MEDIO,
                    MIN(SCORE_MEDIO) AS SCORE_MINIMO,
                    SUM(CASE WHEN SCORE_MEDIO >= 8 THEN 1 ELSE 0 END) AS PONTOS_SAUDAVEIS,
                    SUM(CASE WHEN SCORE_MEDIO >= 5 AND SCORE_MEDIO < 8 THEN 1 ELSE 0 END) AS PONTOS_ALERTA,
                    SUM(CASE WHEN SCORE_MEDIO < 5 THEN 1 ELSE 0 END) AS PONTOS_CRITICOS,
                    -- Conta PONTOS com problemas (não soma dias)
                    SUM(CASE WHEN DIAS_COMUNICACAO > 0 THEN 1 ELSE 0 END) AS PROB_COMUNICACAO,
                    SUM(CASE WHEN DIAS_MEDIDOR > 0 THEN 1 ELSE 0 END) AS PROB_MEDIDOR,
                    SUM(CASE WHEN DIAS_HIDRAULICO > 0 THEN 1 ELSE 0 END) AS PROB_HIDRAULICO,
                    SUM(CASE WHEN DIAS_ANOMALIA > 0 THEN 1 ELSE 0 END) AS TOTAL_ANOMALIAS,
                    SUM(CASE WHEN TOTAL_TRATADOS > 0 THEN 1 ELSE 0 END) AS PONTOS_TRATADOS,
                    :dataInicio AS DATA_INICIO,
                    :dataFim AS DATA_FIM
                FROM (
                    SELECT 
                        CD_PONTO_MEDICAO,
                        COUNT(*) AS TOTAL_MEDICOES,
                        AVG(CAST(VL_SCORE_SAUDE AS DECIMAL(5,2))) AS SCORE_MEDIO,
                        SUM(CASE WHEN FL_SEM_COMUNICACAO = 1 THEN 1 ELSE 0 END) AS DIAS_COMUNICACAO,
                        SUM(CASE WHEN FL_VALOR_CONSTANTE = 1 OR FL_PERFIL_ANOMALO = 1 THEN 1 ELSE 0 END) AS DIAS_MEDIDOR,
                        SUM(CASE WHEN FL_VALOR_NEGATIVO = 1 OR FL_FORA_FAIXA = 1 OR FL_SPIKE = 1 THEN 1 ELSE 0 END) AS DIAS_HIDRAULICO,
                        SUM(CASE WHEN FL_ANOMALIA = 1 THEN 1 ELSE 0 END) AS DIAS_ANOMALIA,
                        SUM(CASE WHEN ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS TOTAL_TRATADOS
                    FROM SIMP.dbo.MEDICAO_RESUMO_DIARIO
                    WHERE DT_MEDICAO >= :dataInicio2
                      AND DT_MEDICAO <= :dataFim2
                    GROUP BY CD_PONTO_MEDICAO
                ) AS PontosSumarizados
            ";
            
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->execute([
                ':dataInicio' => $dataInicio,
                ':dataFim' => $dataFim,
                ':dataInicio2' => $dataInicio,
                ':dataFim2' => $dataFim
            ]);
            $dados = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Fallback final: calcular da tabela REGISTRO_VAZAO_PRESSAO
            $sql = "
                SELECT 
                    COUNT(DISTINCT RVP.CD_PONTO_MEDICAO) AS TOTAL_PONTOS,
                    COUNT(*) AS TOTAL_MEDICOES,
                    7.5 AS SCORE_MEDIO,
                    5 AS SCORE_MINIMO,
                    0 AS PONTOS_SAUDAVEIS,
                    0 AS PONTOS_ALERTA,
                    0 AS PONTOS_CRITICOS,
                    0 AS PROB_COMUNICACAO,
                    0 AS PROB_MEDIDOR,
                    0 AS PROB_HIDRAULICO,
                    0 AS TOTAL_ANOMALIAS,
                    0 AS PONTOS_TRATADOS,
                    0 AS PONTOS_TRATAMENTO_RECORRENTE,
                    :dataInicio AS DATA_INICIO,
                    :dataFim AS DATA_FIM
                FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
                WHERE CAST(DT_LEITURA AS DATE) >= :dataInicio2
                  AND CAST(DT_LEITURA AS DATE) <= :dataFim2
            ";
            
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->execute([
                ':dataInicio' => $dataInicio,
                ':dataFim' => $dataFim,
                ':dataInicio2' => $dataInicio,
                ':dataFim2' => $dataFim
            ]);
            $dados = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Mensagem indicando que as views precisam ser criadas
            $dados['AVISO'] = 'Views de dashboard não encontradas. Execute os scripts de criação.';
        }
    }
    
    // Buscar pontos com tratamento recorrente (SEMPRE, independente de usar view ou não)
    if (!isset($dados['PONTOS_TRATAMENTO_RECORRENTE']) || $dados['PONTOS_TRATAMENTO_RECORRENTE'] === null) {
        try {
            $sqlTratamento = "
                SELECT COUNT(*) AS PONTOS_TRATAMENTO_RECORRENTE
                FROM (
                    SELECT CD_PONTO_MEDICAO, SUM(ISNULL(QTD_TRATAMENTOS, 0)) AS TOTAL_TRATAMENTOS
                    FROM MEDICAO_RESUMO_DIARIO
                    WHERE DT_MEDICAO >= :dataInicio
                      AND DT_MEDICAO <= :dataFim
                    GROUP BY CD_PONTO_MEDICAO
                    HAVING SUM(ISNULL(QTD_TRATAMENTOS, 0)) > 3
                ) AS PontosRecorrentes
            ";
            $stmtTrat = $pdoSIMP->prepare($sqlTratamento);
            $stmtTrat->execute([':dataInicio' => $dataInicio, ':dataFim' => $dataFim]);
            $tratamento = $stmtTrat->fetch(PDO::FETCH_ASSOC);
            $dados['PONTOS_TRATAMENTO_RECORRENTE'] = $tratamento['PONTOS_TRATAMENTO_RECORRENTE'] ?? 0;
        } catch (Exception $e) {
            // Coluna QTD_TRATAMENTOS pode não existir ainda
            $dados['PONTOS_TRATAMENTO_RECORRENTE'] = 0;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $dados
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
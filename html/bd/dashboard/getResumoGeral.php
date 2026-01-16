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
    
    // Verificar se a view existe
    $checkView = $pdoSIMP->query("SELECT OBJECT_ID('VW_DASHBOARD_RESUMO_GERAL', 'V') AS ViewExists");
    $viewExists = $checkView->fetch(PDO::FETCH_ASSOC)['ViewExists'];
    
    if ($viewExists) {
        // Usar a view existente
        $sql = "SELECT * FROM VW_DASHBOARD_RESUMO_GERAL";
        $stmt = $pdoSIMP->query($sql);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Fallback: calcular diretamente das tabelas de resumo
        $dataInicio = date('Y-m-d', strtotime("-{$dias} days"));
        $dataFim = date('Y-m-d');
        
        // Verificar se tabela MEDICAO_RESUMO_DIARIO existe
        $checkTable = $pdoSIMP->query("SELECT OBJECT_ID('MEDICAO_RESUMO_DIARIO', 'U') AS TableExists");
        $tableExists = $checkTable->fetch(PDO::FETCH_ASSOC)['TableExists'];
        
        if ($tableExists) {
            $sql = "
                SELECT 
                    COUNT(DISTINCT CD_PONTO_MEDICAO) AS TOTAL_PONTOS,
                    COUNT(*) AS TOTAL_MEDICOES,
                    ROUND(AVG(CAST(VL_SCORE_SAUDE AS DECIMAL(5,2))), 2) AS SCORE_MEDIO,
                    MIN(VL_SCORE_SAUDE) AS SCORE_MINIMO,
                    SUM(CASE WHEN VL_SCORE_SAUDE >= 8 THEN 1 ELSE 0 END) AS PONTOS_SAUDAVEIS,
                    SUM(CASE WHEN VL_SCORE_SAUDE >= 5 AND VL_SCORE_SAUDE < 8 THEN 1 ELSE 0 END) AS PONTOS_ALERTA,
                    SUM(CASE WHEN VL_SCORE_SAUDE < 5 THEN 1 ELSE 0 END) AS PONTOS_CRITICOS,
                    SUM(CASE WHEN FL_SEM_COMUNICACAO = 1 THEN 1 ELSE 0 END) AS PROB_COMUNICACAO,
                    SUM(CASE WHEN FL_VALOR_CONSTANTE = 1 OR FL_PERFIL_ANOMALO = 1 THEN 1 ELSE 0 END) AS PROB_MEDIDOR,
                    SUM(CASE WHEN FL_VALOR_NEGATIVO = 1 OR FL_FORA_FAIXA = 1 OR FL_SPIKE = 1 THEN 1 ELSE 0 END) AS PROB_HIDRAULICO,
                    SUM(CASE WHEN FL_ANOMALIA = 1 THEN 1 ELSE 0 END) AS TOTAL_ANOMALIAS,
                    SUM(CASE WHEN ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS PONTOS_TRATADOS,
                    :dataInicio AS DATA_INICIO,
                    :dataFim AS DATA_FIM
                FROM SIMP.dbo.MEDICAO_RESUMO_DIARIO
                WHERE DT_MEDICAO >= :dataInicio2
                  AND DT_MEDICAO <= :dataFim2
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

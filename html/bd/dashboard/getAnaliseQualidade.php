<?php
/**
 * SIMP - Análise de Qualidade dos Dados
 * 
 * Consulta direta em REGISTRO_VAZAO_PRESSAO (sem stored procedures)
 * Analisa pontos de medição por:
 * - Taxa de descarte (ID_SITUACAO = 2)
 * - Integridade dos dados (registros por dia)
 * 
 * Parâmetros:
 *   ?dias=7       - Período de análise (padrão: 7 dias)
 *   ?tipo=resumo  - Tipo de análise (resumo, descarte, integridade, ranking)
 * 
 * Classificação de Integridade:
 *   Bom:     ≥80% (≥1152 registros/dia)
 *   Regular: ≥50% (720-1151 registros/dia)  
 *   Péssimo: <50% (<720 registros/dia)
 * 
 * @version 1.1 - Com tratamento de erros melhorado
 */

// Desabilitar exibição de erros (retornar como JSON)
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');

// Handler de erro fatal
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro PHP: ' . $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ], JSON_UNESCAPED_UNICODE);
    }
});

try {
    // Verificar autenticação
    if (file_exists(__DIR__ . '/../verificarAuth.php')) {
        require_once __DIR__ . '/../verificarAuth.php';
    } else {
        // Se não existe verificarAuth, iniciar sessão manualmente
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    // Conexão com banco
    if (file_exists(__DIR__ . '/../conexao.php')) {
        include_once __DIR__ . '/../conexao.php';
    } else {
        throw new Exception('Arquivo de conexão não encontrado');
    }
    
    // Verificar se conexão existe
    if (!isset($pdoSIMP)) {
        throw new Exception('Conexão PDO não estabelecida');
    }
    
    // Parâmetros
    $dias = isset($_GET['dias']) ? (int)$_GET['dias'] : 7;
    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'resumo';
    $cdUnidade = isset($_GET['cd_unidade']) ? (int)$_GET['cd_unidade'] : null;
    $tipoMedidor = isset($_GET['tipo_medidor']) ? (int)$_GET['tipo_medidor'] : null;
    
    // Limitar dias para evitar consultas pesadas
    if ($dias < 1) $dias = 1;
    if ($dias > 90) $dias = 90;
    
    // Data de referência (último dia com dados)
    $sqlDataMax = "SELECT MAX(CAST(DT_LEITURA AS DATE)) AS DT_MAX FROM REGISTRO_VAZAO_PRESSAO";
    $dataMax = $pdoSIMP->query($sqlDataMax)->fetch(PDO::FETCH_ASSOC)['DT_MAX'];
    
    if (!$dataMax) {
        echo json_encode(['success' => true, 'data' => [], 'message' => 'Sem dados disponíveis']);
        exit;
    }
    
    // Calcular data inicial
    $dataInicio = date('Y-m-d', strtotime("-{$dias} days", strtotime($dataMax)));
    
    // Filtros opcionais
    $filtroUnidade = "";
    $filtroTipo = "";
    $params = [':dataInicio' => $dataInicio, ':dataMax' => $dataMax];
    
    if ($cdUnidade) {
        $filtroUnidade = " AND LOC.CD_UNIDADE = :cdUnidade";
        $params[':cdUnidade'] = $cdUnidade;
    }
    
    if ($tipoMedidor) {
        $filtroTipo = " AND PM.ID_TIPO_MEDIDOR = :tipoMedidor";
        $params[':tipoMedidor'] = $tipoMedidor;
    }
    
    switch ($tipo) {
        
        // ================================================================
        // RESUMO GERAL
        // ================================================================
        case 'resumo':
            // Estatísticas gerais do período
            $sql = "
                SELECT 
                    COUNT(DISTINCT RVP.CD_PONTO_MEDICAO) AS TOTAL_PONTOS_COM_DADOS,
                    COUNT(*) AS TOTAL_REGISTROS,
                    SUM(CASE WHEN RVP.ID_SITUACAO = 1 THEN 1 ELSE 0 END) AS REGISTROS_VALIDOS,
                    SUM(CASE WHEN RVP.ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS REGISTROS_DESCARTADOS,
                    ROUND(
                        CAST(SUM(CASE WHEN RVP.ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS FLOAT) / 
                        NULLIF(COUNT(*), 0) * 100, 2
                    ) AS TAXA_DESCARTE_GERAL,
                    COUNT(DISTINCT CAST(RVP.DT_LEITURA AS DATE)) AS DIAS_COM_DADOS
                FROM REGISTRO_VAZAO_PRESSAO RVP
                INNER JOIN PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                LEFT JOIN LOCALIDADE LOC ON LOC.CD_CHAVE = PM.CD_LOCALIDADE
                WHERE CAST(RVP.DT_LEITURA AS DATE) BETWEEN :dataInicio AND :dataMax
                {$filtroUnidade}
                {$filtroTipo}
            ";
            
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->execute($params);
            $resumo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Contagem por classificação de integridade
            $sqlIntegridade = "
                SELECT 
                    CLASSIFICACAO,
                    COUNT(*) AS QUANTIDADE
                FROM (
                    SELECT 
                        RVP.CD_PONTO_MEDICAO,
                        CAST(RVP.DT_LEITURA AS DATE) AS DT_DIA,
                        COUNT(*) AS QTD_REGISTROS,
                        CASE 
                            WHEN COUNT(*) >= 1152 THEN 'BOM'
                            WHEN COUNT(*) >= 720 THEN 'REGULAR'
                            ELSE 'PESSIMO'
                        END AS CLASSIFICACAO
                    FROM REGISTRO_VAZAO_PRESSAO RVP
                    INNER JOIN PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                    LEFT JOIN LOCALIDADE LOC ON LOC.CD_CHAVE = PM.CD_LOCALIDADE
                    WHERE RVP.ID_SITUACAO = 1
                      AND CAST(RVP.DT_LEITURA AS DATE) BETWEEN :dataInicio AND :dataMax
                      {$filtroUnidade}
                      {$filtroTipo}
                    GROUP BY RVP.CD_PONTO_MEDICAO, CAST(RVP.DT_LEITURA AS DATE)
                ) AS SUBQ
                GROUP BY CLASSIFICACAO
            ";
            
            $stmtInt = $pdoSIMP->prepare($sqlIntegridade);
            $stmtInt->execute($params);
            $integridadePorClasse = $stmtInt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formatar resposta
            $integridadeFormatada = ['BOM' => 0, 'REGULAR' => 0, 'PESSIMO' => 0];
            foreach ($integridadePorClasse as $item) {
                $integridadeFormatada[$item['CLASSIFICACAO']] = (int)$item['QUANTIDADE'];
            }
            
            echo json_encode([
                'success' => true,
                'tipo' => 'resumo',
                'periodo' => [
                    'data_inicio' => $dataInicio,
                    'data_fim' => $dataMax,
                    'dias' => $dias
                ],
                'totais' => [
                    'pontos_com_dados' => (int)$resumo['TOTAL_PONTOS_COM_DADOS'],
                    'total_registros' => (int)$resumo['TOTAL_REGISTROS'],
                    'registros_validos' => (int)$resumo['REGISTROS_VALIDOS'],
                    'registros_descartados' => (int)$resumo['REGISTROS_DESCARTADOS'],
                    'taxa_descarte_geral' => (float)$resumo['TAXA_DESCARTE_GERAL'],
                    'dias_com_dados' => (int)$resumo['DIAS_COM_DADOS']
                ],
                'integridade_diaria' => $integridadeFormatada,
                'total_dias_ponto' => array_sum($integridadeFormatada)
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        // ================================================================
        // PONTOS COM MAIOR TAXA DE DESCARTE
        // ================================================================
        case 'descarte':
            $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 10;
            
            $sql = "
                SELECT TOP {$limite}
                    RVP.CD_PONTO_MEDICAO,
                    PM.DS_NOME AS DS_PONTO,
                    PM.ID_TIPO_MEDIDOR,
                    LOC.DS_NOME AS DS_LOCALIDADE,
                    UNI.DS_NOME AS DS_UNIDADE,
                    COUNT(*) AS TOTAL_REGISTROS,
                    SUM(CASE WHEN RVP.ID_SITUACAO = 1 THEN 1 ELSE 0 END) AS REGISTROS_VALIDOS,
                    SUM(CASE WHEN RVP.ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS REGISTROS_DESCARTADOS,
                    ROUND(
                        CAST(SUM(CASE WHEN RVP.ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS FLOAT) / 
                        NULLIF(COUNT(*), 0) * 100, 2
                    ) AS TAXA_DESCARTE,
                    MIN(CAST(RVP.DT_LEITURA AS DATE)) AS PRIMEIRO_REGISTRO,
                    MAX(CAST(RVP.DT_LEITURA AS DATE)) AS ULTIMO_REGISTRO
                FROM REGISTRO_VAZAO_PRESSAO RVP
                INNER JOIN PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                LEFT JOIN LOCALIDADE LOC ON LOC.CD_CHAVE = PM.CD_LOCALIDADE
                LEFT JOIN UNIDADE UNI ON UNI.CD_UNIDADE = LOC.CD_UNIDADE
                WHERE CAST(RVP.DT_LEITURA AS DATE) BETWEEN :dataInicio AND :dataMax
                {$filtroUnidade}
                {$filtroTipo}
                GROUP BY 
                    RVP.CD_PONTO_MEDICAO, PM.DS_NOME, PM.ID_TIPO_MEDIDOR,
                    LOC.DS_NOME, UNI.DS_NOME
                HAVING SUM(CASE WHEN RVP.ID_SITUACAO = 2 THEN 1 ELSE 0 END) > 0
                ORDER BY TAXA_DESCARTE DESC, REGISTROS_DESCARTADOS DESC
            ";
            
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->execute($params);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Adicionar ícone do tipo de medidor
            $tiposMedidor = [
                1 => ['letra' => 'M', 'nome' => 'Macromedidor'],
                2 => ['letra' => 'E', 'nome' => 'Est. Pitométrica'],
                4 => ['letra' => 'P', 'nome' => 'Med. Pressão'],
                6 => ['letra' => 'R', 'nome' => 'Nível Reservatório'],
                8 => ['letra' => 'H', 'nome' => 'Hidrômetro']
            ];
            
            foreach ($dados as &$item) {
                $tm = $tiposMedidor[$item['ID_TIPO_MEDIDOR']] ?? ['letra' => '?', 'nome' => 'Desconhecido'];
                $item['TIPO_MEDIDOR_LETRA'] = $tm['letra'];
                $item['TIPO_MEDIDOR_NOME'] = $tm['nome'];
            }
            
            echo json_encode([
                'success' => true,
                'tipo' => 'descarte',
                'periodo' => ['data_inicio' => $dataInicio, 'data_fim' => $dataMax, 'dias' => $dias],
                'data' => $dados
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        // ================================================================
        // INTEGRIDADE POR PONTO (média de registros por dia)
        // ================================================================
        case 'integridade':
            $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 10;
            $ordem = isset($_GET['ordem']) && $_GET['ordem'] === 'melhores' ? 'DESC' : 'ASC';
            
            $sql = "
                SELECT TOP {$limite}
                    RVP.CD_PONTO_MEDICAO,
                    PM.DS_NOME AS DS_PONTO,
                    PM.ID_TIPO_MEDIDOR,
                    LOC.DS_NOME AS DS_LOCALIDADE,
                    UNI.DS_NOME AS DS_UNIDADE,
                    COUNT(DISTINCT CAST(RVP.DT_LEITURA AS DATE)) AS DIAS_COM_DADOS,
                    SUM(CASE WHEN RVP.ID_SITUACAO = 1 THEN 1 ELSE 0 END) AS REGISTROS_VALIDOS,
                    ROUND(
                        CAST(SUM(CASE WHEN RVP.ID_SITUACAO = 1 THEN 1 ELSE 0 END) AS FLOAT) / 
                        NULLIF(COUNT(DISTINCT CAST(RVP.DT_LEITURA AS DATE)), 0), 0
                    ) AS MEDIA_REGISTROS_DIA,
                    ROUND(
                        CAST(SUM(CASE WHEN RVP.ID_SITUACAO = 1 THEN 1 ELSE 0 END) AS FLOAT) / 
                        NULLIF(COUNT(DISTINCT CAST(RVP.DT_LEITURA AS DATE)), 0) / 1440 * 100, 2
                    ) AS PERCENTUAL_INTEGRIDADE,
                    CASE 
                        WHEN SUM(CASE WHEN RVP.ID_SITUACAO = 1 THEN 1 ELSE 0 END) / 
                             NULLIF(COUNT(DISTINCT CAST(RVP.DT_LEITURA AS DATE)), 0) >= 1152 THEN 'BOM'
                        WHEN SUM(CASE WHEN RVP.ID_SITUACAO = 1 THEN 1 ELSE 0 END) / 
                             NULLIF(COUNT(DISTINCT CAST(RVP.DT_LEITURA AS DATE)), 0) >= 720 THEN 'REGULAR'
                        ELSE 'PESSIMO'
                    END AS CLASSIFICACAO
                FROM REGISTRO_VAZAO_PRESSAO RVP
                INNER JOIN PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                LEFT JOIN LOCALIDADE LOC ON LOC.CD_CHAVE = PM.CD_LOCALIDADE
                LEFT JOIN UNIDADE UNI ON UNI.CD_UNIDADE = LOC.CD_UNIDADE
                WHERE CAST(RVP.DT_LEITURA AS DATE) BETWEEN :dataInicio AND :dataMax
                {$filtroUnidade}
                {$filtroTipo}
                GROUP BY 
                    RVP.CD_PONTO_MEDICAO, PM.DS_NOME, PM.ID_TIPO_MEDIDOR,
                    LOC.DS_NOME, UNI.DS_NOME
                ORDER BY MEDIA_REGISTROS_DIA {$ordem}, DIAS_COM_DADOS DESC
            ";
            
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->execute($params);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Adicionar ícone do tipo de medidor
            $tiposMedidor = [
                1 => ['letra' => 'M', 'nome' => 'Macromedidor'],
                2 => ['letra' => 'E', 'nome' => 'Est. Pitométrica'],
                4 => ['letra' => 'P', 'nome' => 'Med. Pressão'],
                6 => ['letra' => 'R', 'nome' => 'Nível Reservatório'],
                8 => ['letra' => 'H', 'nome' => 'Hidrômetro']
            ];
            
            foreach ($dados as &$item) {
                $tm = $tiposMedidor[$item['ID_TIPO_MEDIDOR']] ?? ['letra' => '?', 'nome' => 'Desconhecido'];
                $item['TIPO_MEDIDOR_LETRA'] = $tm['letra'];
                $item['TIPO_MEDIDOR_NOME'] = $tm['nome'];
            }
            
            echo json_encode([
                'success' => true,
                'tipo' => 'integridade',
                'periodo' => ['data_inicio' => $dataInicio, 'data_fim' => $dataMax, 'dias' => $dias],
                'ordem' => $ordem === 'DESC' ? 'melhores' : 'piores',
                'data' => $dados
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        // ================================================================
        // RANKING COMPLETO (combinando descarte + integridade)
        // ================================================================
        case 'ranking':
            $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 20;
            
            $sql = "
                SELECT TOP {$limite}
                    RVP.CD_PONTO_MEDICAO,
                    PM.DS_NOME AS DS_PONTO,
                    PM.ID_TIPO_MEDIDOR,
                    LOC.DS_NOME AS DS_LOCALIDADE,
                    UNI.DS_NOME AS DS_UNIDADE,
                    COUNT(*) AS TOTAL_REGISTROS,
                    SUM(CASE WHEN RVP.ID_SITUACAO = 1 THEN 1 ELSE 0 END) AS REGISTROS_VALIDOS,
                    SUM(CASE WHEN RVP.ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS REGISTROS_DESCARTADOS,
                    ROUND(
                        CAST(SUM(CASE WHEN RVP.ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS FLOAT) / 
                        NULLIF(COUNT(*), 0) * 100, 2
                    ) AS TAXA_DESCARTE,
                    COUNT(DISTINCT CAST(RVP.DT_LEITURA AS DATE)) AS DIAS_COM_DADOS,
                    ROUND(
                        CAST(SUM(CASE WHEN RVP.ID_SITUACAO = 1 THEN 1 ELSE 0 END) AS FLOAT) / 
                        NULLIF(COUNT(DISTINCT CAST(RVP.DT_LEITURA AS DATE)), 0), 0
                    ) AS MEDIA_REGISTROS_DIA,
                    CASE 
                        WHEN SUM(CASE WHEN RVP.ID_SITUACAO = 1 THEN 1 ELSE 0 END) / 
                             NULLIF(COUNT(DISTINCT CAST(RVP.DT_LEITURA AS DATE)), 0) >= 1152 THEN 'BOM'
                        WHEN SUM(CASE WHEN RVP.ID_SITUACAO = 1 THEN 1 ELSE 0 END) / 
                             NULLIF(COUNT(DISTINCT CAST(RVP.DT_LEITURA AS DATE)), 0) >= 720 THEN 'REGULAR'
                        ELSE 'PESSIMO'
                    END AS CLASSIFICACAO,
                    -- Score combinado: penaliza taxa de descarte e baixa integridade
                    ROUND(
                        (1 - CAST(SUM(CASE WHEN RVP.ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS FLOAT) / NULLIF(COUNT(*), 0)) * 
                        (CAST(SUM(CASE WHEN RVP.ID_SITUACAO = 1 THEN 1 ELSE 0 END) AS FLOAT) / NULLIF(COUNT(DISTINCT CAST(RVP.DT_LEITURA AS DATE)), 0) / 1440) * 100
                    , 2) AS SCORE_QUALIDADE
                FROM REGISTRO_VAZAO_PRESSAO RVP
                INNER JOIN PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                LEFT JOIN LOCALIDADE LOC ON LOC.CD_CHAVE = PM.CD_LOCALIDADE
                LEFT JOIN UNIDADE UNI ON UNI.CD_UNIDADE = LOC.CD_UNIDADE
                WHERE CAST(RVP.DT_LEITURA AS DATE) BETWEEN :dataInicio AND :dataMax
                {$filtroUnidade}
                {$filtroTipo}
                GROUP BY 
                    RVP.CD_PONTO_MEDICAO, PM.DS_NOME, PM.ID_TIPO_MEDIDOR,
                    LOC.DS_NOME, UNI.DS_NOME
                ORDER BY SCORE_QUALIDADE ASC
            ";
            
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->execute($params);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Adicionar ícone do tipo de medidor
            $tiposMedidor = [
                1 => ['letra' => 'M', 'nome' => 'Macromedidor'],
                2 => ['letra' => 'E', 'nome' => 'Est. Pitométrica'],
                4 => ['letra' => 'P', 'nome' => 'Med. Pressão'],
                6 => ['letra' => 'R', 'nome' => 'Nível Reservatório'],
                8 => ['letra' => 'H', 'nome' => 'Hidrômetro']
            ];
            
            foreach ($dados as &$item) {
                $tm = $tiposMedidor[$item['ID_TIPO_MEDIDOR']] ?? ['letra' => '?', 'nome' => 'Desconhecido'];
                $item['TIPO_MEDIDOR_LETRA'] = $tm['letra'];
                $item['TIPO_MEDIDOR_NOME'] = $tm['nome'];
            }
            
            echo json_encode([
                'success' => true,
                'tipo' => 'ranking',
                'periodo' => ['data_inicio' => $dataInicio, 'data_fim' => $dataMax, 'dias' => $dias],
                'data' => $dados
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        // ================================================================
        // EVOLUÇÃO DIÁRIA (para gráfico)
        // ================================================================
        case 'evolucao':
            $sql = "
                SELECT 
                    CAST(RVP.DT_LEITURA AS DATE) AS DT_DIA,
                    COUNT(*) AS TOTAL_REGISTROS,
                    SUM(CASE WHEN RVP.ID_SITUACAO = 1 THEN 1 ELSE 0 END) AS REGISTROS_VALIDOS,
                    SUM(CASE WHEN RVP.ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS REGISTROS_DESCARTADOS,
                    COUNT(DISTINCT RVP.CD_PONTO_MEDICAO) AS PONTOS_COM_DADOS,
                    ROUND(
                        CAST(SUM(CASE WHEN RVP.ID_SITUACAO = 2 THEN 1 ELSE 0 END) AS FLOAT) / 
                        NULLIF(COUNT(*), 0) * 100, 2
                    ) AS TAXA_DESCARTE
                FROM REGISTRO_VAZAO_PRESSAO RVP
                INNER JOIN PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                LEFT JOIN LOCALIDADE LOC ON LOC.CD_CHAVE = PM.CD_LOCALIDADE
                WHERE CAST(RVP.DT_LEITURA AS DATE) BETWEEN :dataInicio AND :dataMax
                {$filtroUnidade}
                {$filtroTipo}
                GROUP BY CAST(RVP.DT_LEITURA AS DATE)
                ORDER BY DT_DIA ASC
            ";
            
            $stmt = $pdoSIMP->prepare($sql);
            $stmt->execute($params);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'tipo' => 'evolucao',
                'periodo' => ['data_inicio' => $dataInicio, 'data_fim' => $dataMax, 'dias' => $dias],
                'data' => $dados
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Tipo de análise inválido']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar análise: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
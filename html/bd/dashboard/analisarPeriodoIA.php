<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Análise IA do Período (Dashboard)
 * 
 * @author Bruno
 * @version 1.1 - Corrigido tratamento de erros
 * @date 2026-01-23
 */

// Desabilitar exibição de erros no output (evita corromper JSON)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Capturar erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'error' => 'Erro fatal: ' . $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ], JSON_UNESCAPED_UNICODE);
    }
});

header('Content-Type: application/json; charset=utf-8');

try {
    // Verificar se o arquivo de conexão existe
    $conexaoPath = __DIR__ . '/../conexao.php';
    if (!file_exists($conexaoPath)) {
        throw new Exception('Arquivo de conexão não encontrado: ' . $conexaoPath);
    }
    include_once $conexaoPath;

    // Verificar conexão
    if (!isset($pdoSIMP)) {
        throw new Exception('Conexão com banco não estabelecida');
    }

    // Verificar se o arquivo de config existe
    $configPath = __DIR__ . '/../config/ia_config.php';
    if (!file_exists($configPath)) {
        throw new Exception('Arquivo de configuração IA não encontrado');
    }
    
    // Carregar configuração da IA
    $config = include $configPath;
    if (!is_array($config)) {
        throw new Exception('Configuração IA inválida');
    }
    
    $provider = $config['provider'] ?? 'deepseek';

    // ========================================
    // Carregar regras da IA do banco
    // ========================================
    $regrasIA = '';
    try {
        $sqlRegras = "SELECT DS_CONTEUDO FROM SIMP.dbo.IA_REGRAS";
        $stmtRegras = $pdoSIMP->query($sqlRegras);
        $regras = $stmtRegras->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($regras)) {
            $regrasIA = "REGRAS DE ANÁLISE DO SISTEMA:\n" . implode("\n", $regras);
        }
    } catch (Exception $e) {
        // Se falhar, continua sem regras do banco
        error_log('SIMP IA: Erro ao carregar regras: ' . $e->getMessage());
    }

    // ========================================
    // Receber dados da requisição
    // ========================================
    $rawInput = file_get_contents('php://input');
    
    if (empty($rawInput)) {
        throw new Exception('Nenhum dado recebido na requisição');
    }
    
    $dados = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }
    
    if (empty($dados)) {
        throw new Exception('Dados vazios após decodificação');
    }

    // Extrair dados
    $resumo = $dados['resumo'] ?? [];
    $evolucao = $dados['evolucao'] ?? [];
    $criticos = $dados['criticos'] ?? [];
    $maisTratados = $dados['maisTratados'] ?? [];
    $periodo = $dados['periodo'] ?? 7;
    $dataReferencia = $dados['dataReferencia'] ?? date('Y-m-d');
    $filtros = $dados['filtros'] ?? [];

    // ========================================
    // Construir contexto para a IA
    // ========================================
    $contexto = construirContextoDashboard($resumo, $evolucao, $criticos, $maisTratados, $periodo, $dataReferencia, $filtros);

    // Adicionar regras do banco
    if (!empty($regrasIA)) {
        $contexto .= "\n\n" . $regrasIA;
    }

    // ========================================
    // Prompt do sistema
    // ========================================
    $systemPrompt = <<<PROMPT
Você é um analista especializado em sistemas de abastecimento de água, focado em monitoramento de macromedição e pitometria.

Sua tarefa é analisar os dados agregados do dashboard do SIMP (Sistema Integrado de Macromedição e Pitometria) e fornecer:

1. **RESUMO EXECUTIVO** (2-3 frases): Visão geral da situação atual
2. **ALERTAS CRÍTICOS** (lista): Pontos que precisam de atenção imediata
3. **TENDÊNCIAS** (lista): Padrões identificados na evolução temporal
4. **RECOMENDAÇÕES** (lista priorizada): Ações sugeridas para a equipe

DIRETRIZES:
- Seja objetivo e direto
- Priorize informações acionáveis
- Use números e percentuais para fundamentar análises
- Destaque situações anômalas ou preocupantes
- Considere o contexto operacional (tratamento manual = esforço da equipe)
- Formate a resposta em Markdown para melhor legibilidade

MÉTRICAS DE REFERÊNCIA:
- Cobertura ideal: ≥95%
- Cobertura aceitável: ≥80%
- Cobertura crítica: <50%
- Tratamento manual alto: >5% dos registros
- Status OK: Ponto operando normalmente
- Status ATENÇÃO: Requer monitoramento
- Status CRÍTICO: Requer intervenção imediata
PROMPT;

    // ========================================
    // Preparar mensagem do usuário
    // ========================================
    $userMessage = "Analise os seguintes dados do dashboard:\n\n" . $contexto;

    // ========================================
    // Chamar API da IA
    // ========================================
    $resposta = chamarAPIIA($systemPrompt, $userMessage, $config, $provider);

    // ========================================
    // Retornar resposta
    // ========================================
    echo json_encode([
        'success' => true,
        'resposta' => $resposta,
        'provider' => $provider,
        'modelo' => $config[$provider]['model'] ?? 'desconhecido',
        'dataAnalise' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getFile() . ':' . $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}

// ========================================
// FUNÇÕES AUXILIARES
// ========================================

/**
 * Constrói o contexto textual para a IA analisar
 */
function construirContextoDashboard($resumo, $evolucao, $criticos, $maisTratados, $periodo, $dataReferencia, $filtros) {
    $ctx = [];

    // Informações do período
    $ctx[] = "=== PERÍODO DE ANÁLISE ===";
    $ctx[] = "Data de referência: " . $dataReferencia;
    $ctx[] = "Período analisado: últimos " . $periodo . " dias";
    
    if (!empty($filtros)) {
        if (!empty($filtros['unidade'])) $ctx[] = "Filtro Unidade: " . $filtros['unidade'];
        if (!empty($filtros['tipo'])) $ctx[] = "Filtro Tipo Medidor: " . $filtros['tipo'];
    }

    // Resumo geral
    $ctx[] = "\n=== RESUMO GERAL (última data) ===";
    $total = max(1, $resumo['total'] ?? 1);
    $ctx[] = "Total de pontos monitorados: " . ($resumo['total'] ?? 0);
    $ctx[] = "Pontos OK: " . ($resumo['ok'] ?? 0) . " (" . round((($resumo['ok'] ?? 0) / $total) * 100, 1) . "%)";
    $ctx[] = "Pontos em ATENÇÃO: " . ($resumo['atencao'] ?? 0) . " (" . round((($resumo['atencao'] ?? 0) / $total) * 100, 1) . "%)";
    $ctx[] = "Pontos CRÍTICOS: " . ($resumo['critico'] ?? 0) . " (" . round((($resumo['critico'] ?? 0) / $total) * 100, 1) . "%)";
    $ctx[] = "Cobertura média: " . number_format($resumo['coberturaMedia'] ?? 0, 1) . "%";

    // Tratamento manual (período completo)
    $ctx[] = "\n=== ESFORÇO OPERACIONAL (período completo) ===";
    $ctx[] = "Total de registros tratados manualmente: " . number_format($resumo['totalRegistrosTratados'] ?? 0, 0, ',', '.');
    $ctx[] = "Pontos que necessitaram tratamento: " . ($resumo['pontosComTratamento'] ?? 0);
    $ctx[] = "Percentual de registros tratados: " . number_format($resumo['percentualTratado'] ?? 0, 2) . "%";

    // Evolução temporal
    if (!empty($evolucao) && count($evolucao) > 1) {
        $ctx[] = "\n=== EVOLUÇÃO TEMPORAL ===";
        $primeiro = reset($evolucao);
        $ultimo = end($evolucao);
        
        $ctx[] = "Início do período (" . ($primeiro['DT_REFERENCIA'] ?? '') . "): OK=" . ($primeiro['QTD_OK'] ?? 0) . ", Atenção=" . ($primeiro['QTD_ATENCAO'] ?? 0) . ", Crítico=" . ($primeiro['QTD_CRITICO'] ?? 0);
        $ctx[] = "Fim do período (" . ($ultimo['DT_REFERENCIA'] ?? '') . "): OK=" . ($ultimo['QTD_OK'] ?? 0) . ", Atenção=" . ($ultimo['QTD_ATENCAO'] ?? 0) . ", Crítico=" . ($ultimo['QTD_CRITICO'] ?? 0);
        
        // Calcular tendência
        $variacaoOk = ($ultimo['QTD_OK'] ?? 0) - ($primeiro['QTD_OK'] ?? 0);
        $variacaoCritico = ($ultimo['QTD_CRITICO'] ?? 0) - ($primeiro['QTD_CRITICO'] ?? 0);
        $ctx[] = "Variação pontos OK: " . ($variacaoOk >= 0 ? '+' : '') . $variacaoOk;
        $ctx[] = "Variação pontos críticos: " . ($variacaoCritico >= 0 ? '+' : '') . $variacaoCritico;
    }

    // Pontos críticos
    if (!empty($criticos)) {
        $ctx[] = "\n=== TOP PONTOS CRÍTICOS/ATENÇÃO ===";
        foreach ($criticos as $i => $ponto) {
            $ctx[] = ($i + 1) . ". " . ($ponto['DS_NOME'] ?? 'Ponto ' . ($i+1)) . 
                     " | Status: " . ($ponto['DS_STATUS'] ?? '-') . 
                     " | Cobertura: " . number_format($ponto['PERC_COBERTURA'] ?? 0, 1) . "%";
        }
    }

    // Maior esforço operacional
    if (!empty($maisTratados)) {
        $ctx[] = "\n=== TOP MAIOR ESFORÇO OPERACIONAL ===";
        foreach ($maisTratados as $i => $ponto) {
            $ctx[] = ($i + 1) . ". " . ($ponto['DS_NOME'] ?? 'Ponto ' . ($i+1)) . 
                     " | Tratados: " . number_format($ponto['QTD_TRATADOS_MANUAL'] ?? 0, 0, ',', '.') . 
                     " (" . number_format($ponto['PERC_TRATADO'] ?? 0, 1) . "%)" .
                     " | Dias: " . ($ponto['DIAS_COM_TRATAMENTO'] ?? 1);
        }
    }

    return implode("\n", $ctx);
}

/**
 * Chama a API de IA (suporta DeepSeek, Groq e Gemini)
 */
function chamarAPIIA($systemPrompt, $userMessage, $config, $provider) {
    // Verificar se cURL está disponível
    if (!function_exists('curl_init')) {
        throw new Exception('Extensão cURL não está instalada');
    }
    
    if ($provider === 'gemini') {
        return chamarGemini($systemPrompt, $userMessage, $config);
    } else {
        return chamarOpenAICompatible($systemPrompt, $userMessage, $config, $provider);
    }
}

/**
 * Chama APIs compatíveis com OpenAI (DeepSeek, Groq)
 */
function chamarOpenAICompatible($systemPrompt, $userMessage, $config, $provider) {
    $providerConfig = $config[$provider] ?? [];
    
    $apiKey = $providerConfig['api_key'] ?? '';
    $apiUrl = $providerConfig['api_url'] ?? '';
    $model = $providerConfig['model'] ?? '';

    if (empty($apiKey)) {
        throw new Exception("API Key não configurada para: $provider");
    }
    
    if (empty($apiUrl)) {
        throw new Exception("URL da API não configurada para: $provider");
    }

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage]
        ],
        'temperature' => $config['temperature'] ?? 0.3,
        'max_tokens' => $config['max_tokens'] ?? 2048
    ];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($errno) {
        throw new Exception("Erro cURL ($errno): $error");
    }

    if ($httpCode !== 200) {
        $errorMsg = "API $provider retornou código $httpCode";
        $respData = json_decode($response, true);
        if (isset($respData['error']['message'])) {
            $errorMsg .= ': ' . $respData['error']['message'];
        }
        throw new Exception($errorMsg);
    }

    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Resposta da API não é JSON válido");
    }
    
    if (!isset($data['choices'][0]['message']['content'])) {
        throw new Exception("Resposta da API em formato inesperado");
    }
    
    return $data['choices'][0]['message']['content'];
}

/**
 * Chama API do Gemini
 */
function chamarGemini($systemPrompt, $userMessage, $config) {
    $geminiConfig = $config['gemini'] ?? [];
    $apiKey = $geminiConfig['api_key'] ?? '';
    $model = $geminiConfig['model'] ?? 'gemini-2.0-flash-lite';
    $baseUrl = $geminiConfig['api_url'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/';

    if (empty($apiKey)) {
        throw new Exception("API Key do Gemini não configurada");
    }

    $url = $baseUrl . $model . ':generateContent?key=' . $apiKey;

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $systemPrompt . "\n\n" . $userMessage]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => $config['temperature'] ?? 0.3,
            'maxOutputTokens' => $config['max_tokens'] ?? 2048
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($errno) {
        throw new Exception("Erro cURL Gemini ($errno): $error");
    }

    if ($httpCode !== 200) {
        $errorMsg = "Gemini retornou código $httpCode";
        $respData = json_decode($response, true);
        if (isset($respData['error']['message'])) {
            $errorMsg .= ': ' . $respData['error']['message'];
        }
        throw new Exception($errorMsg);
    }

    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Resposta do Gemini não é JSON válido");
    }
    
    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception("Resposta do Gemini em formato inesperado");
    }
    
    return $data['candidates'][0]['content']['parts'][0]['text'];
}
<?php
/**
 * SIMP - Chat com IA
 * 
 * Suporta DeepSeek, Groq e Gemini com histórico de conversa
 * Agora busca regras do banco de dados (tabela IA_REGRAS)
 * 
 * @author Bruno
 * @version 2.0 - Regras dinâmicas via banco de dados
 */

// Iniciar buffer para capturar qualquer output
ob_start();

error_reporting(0);
ini_set('display_errors', 0);

/**
 * Retorna JSON limpo e encerra execução
 */
function retornarJSONIA($data) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Capturar erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Erro PHP: ' . $error['message']
        ], JSON_UNESCAPED_UNICODE);
    }
});

try {
    // ========================================
    // Carregar configuração da IA
    // ========================================
    $configFile = __DIR__ . '/../config/ia_config.php';
    if (!file_exists($configFile)) {
        retornarJSONIA(['success' => false, 'error' => 'Arquivo de configuração não encontrado']);
    }
    
    $config = require $configFile;
    $provider = $config['provider'] ?? 'groq';
    
    // ========================================
    // Carregar regras da IA do BANCO DE DADOS
    // Com fallback para arquivo ia_regras.php
    // ========================================
    $regrasIA = '';
    
    // Incluir função de busca de regras
    $buscarRegrasFile = __DIR__ . '/../ia/buscarRegrasIA.php';
    if (file_exists($buscarRegrasFile)) {
        include_once $buscarRegrasFile;
        
        // Tentar buscar regras do banco
        try {
            include_once __DIR__ . '/../conexao.php';
            if (isset($pdoSIMP)) {
                // Usar função que busca do banco
                $regrasIA = obterRegrasIA($pdoSIMP);
            }
        } catch (Exception $e) {
            // Log do erro mas continua
            error_log('Erro ao buscar regras IA do banco: ' . $e->getMessage());
        }
    }
    
    // Fallback: se não conseguiu do banco, tentar arquivo
    if (empty($regrasIA)) {
        $regrasFile = __DIR__ . '/../config/ia_regras.php';
        if (file_exists($regrasFile)) {
            $regrasIA = require $regrasFile;
        }
    }

    // ========================================
    // Receber dados da requisição
    // ========================================
    $rawInput = file_get_contents('php://input');
    $dados = null;
    $contexto = '';
    $historico = [];
    
    if (!empty($rawInput)) {
        $dados = json_decode($rawInput, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            $contexto = $dados['contexto'] ?? '';
            $historico = $dados['historico'] ?? [];
        }
    }
    
    // Compatibilidade com formato antigo (pergunta simples)
    if (empty($historico)) {
        $pergunta = null;
        
        if (isset($_GET['pergunta'])) {
            $pergunta = $_GET['pergunta'];
        } elseif (isset($_POST['pergunta'])) {
            $pergunta = $_POST['pergunta'];
        } elseif (isset($dados['pergunta'])) {
            $pergunta = $dados['pergunta'];
        }
        
        if ($pergunta) {
            $historico = [['role' => 'user', 'content' => $pergunta]];
        }
    }
    
    if (empty($historico)) {
        retornarJSONIA([
            'success' => false,
            'error' => 'Nenhuma mensagem recebida'
        ]);
    }

    // Verificar se cURL está disponível
    if (!function_exists('curl_init')) {
        retornarJSONIA(['success' => false, 'error' => 'Extensão cURL não está instalada']);
    }

    // ========================================
    // Montar contexto completo com regras
    // ========================================
    $contextoCompleto = $contexto;
    if (!empty($regrasIA)) {
        $contextoCompleto .= "\n\n" . $regrasIA;
    }
    
    // ========================================
    // Chamar API baseado no provider
    // ========================================
    if ($provider === 'deepseek') {
        $resposta = chamarDeepSeekComHistorico($contextoCompleto, $historico, $config);
    } elseif ($provider === 'groq') {
        $resposta = chamarGroqComHistorico($contextoCompleto, $historico, $config);
    } else {
        $resposta = chamarGeminiComHistorico($contextoCompleto, $historico, $config);
    }

    retornarJSONIA([
        'success' => true,
        'resposta' => $resposta,
        'provider' => $provider,
        'modelo' => $provider === 'deepseek' ? $config['deepseek']['model'] : ($provider === 'groq' ? $config['groq']['model'] : $config['gemini']['model']),
        'mensagens_no_historico' => count($historico),
        'contexto_tamanho' => strlen($contexto),
        'regras_fonte' => !empty($regrasIA) ? 'banco' : 'nenhuma' // Indica se regras vieram do banco
    ]);

} catch (Exception $e) {
    retornarJSONIA([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Chama a API do DeepSeek com histórico (formato OpenAI)
 * 
 * @param string $contexto Contexto/instruções do sistema
 * @param array $historico Histórico de mensagens
 * @param array $config Configurações
 * @return string Resposta da IA
 */
function chamarDeepSeekComHistorico($contexto, $historico, $config) {
    $deepseekConfig = $config['deepseek'];
    $url = $deepseekConfig['api_url'];
    
    // Montar array de mensagens
    $messages = [];
    
    // Adicionar contexto como system message (se existir)
    if (!empty($contexto)) {
        $messages[] = [
            'role' => 'system',
            'content' => $contexto
        ];
    }
    
    // Adicionar histórico de mensagens
    foreach ($historico as $msg) {
        $messages[] = [
            'role' => $msg['role'],
            'content' => $msg['content']
        ];
    }
    
    $payload = [
        'model' => $deepseekConfig['model'],
        'messages' => $messages,
        'temperature' => $config['temperature'] ?? 0.3,
        'max_tokens' => $config['max_tokens'] ?? 2048,
        'stream' => false
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $deepseekConfig['api_key']
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);

    if ($curlErrno !== 0) {
        throw new Exception("Erro cURL #{$curlErrno}: {$curlError}");
    }

    if ($httpCode !== 200) {
        $data = json_decode($response, true);
        $errorMsg = $data['error']['message'] ?? "Erro HTTP {$httpCode}";
        throw new Exception($errorMsg);
    }

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? 'Sem resposta';
}

/**
 * Chama a API do Groq com histórico (formato OpenAI)
 * 
 * @param string $contexto Contexto/instruções do sistema
 * @param array $historico Histórico de mensagens
 * @param array $config Configurações
 * @return string Resposta da IA
 */
function chamarGroqComHistorico($contexto, $historico, $config) {
    $groqConfig = $config['groq'];
    $url = $groqConfig['api_url'];
    
    // Montar array de mensagens
    $messages = [];
    
    // Adicionar contexto como system message (se existir)
    if (!empty($contexto)) {
        $messages[] = [
            'role' => 'system',
            'content' => $contexto
        ];
    }
    
    // Adicionar histórico de mensagens
    foreach ($historico as $msg) {
        $messages[] = [
            'role' => $msg['role'],
            'content' => $msg['content']
        ];
    }
    
    $payload = [
        'model' => $groqConfig['model'],
        'messages' => $messages,
        'temperature' => $config['temperature'] ?? 0.3,
        'max_tokens' => $config['max_tokens'] ?? 2048
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $groqConfig['api_key']
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);

    if ($curlErrno !== 0) {
        throw new Exception("Erro cURL #{$curlErrno}: {$curlError}");
    }

    if ($httpCode !== 200) {
        $data = json_decode($response, true);
        $errorMsg = $data['error']['message'] ?? "Erro HTTP {$httpCode}";
        throw new Exception($errorMsg);
    }

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? 'Sem resposta';
}

/**
 * Chama a API do Gemini com histórico
 * 
 * @param string $contexto Contexto/instruções do sistema
 * @param array $historico Histórico de mensagens
 * @param array $config Configurações
 * @return string Resposta da IA
 */
function chamarGeminiComHistorico($contexto, $historico, $config) {
    $geminiConfig = $config['gemini'];
    $url = $geminiConfig['api_url'] . $geminiConfig['model'] . ':generateContent?key=' . $geminiConfig['api_key'];
    
    // Montar array de contents para Gemini
    $contents = [];
    
    foreach ($historico as $msg) {
        $role = $msg['role'] === 'assistant' ? 'model' : 'user';
        $contents[] = [
            'role' => $role,
            'parts' => [['text' => $msg['content']]]
        ];
    }
    
    $payload = [
        'contents' => $contents,
        'generationConfig' => [
            'temperature' => $config['temperature'] ?? 0.3,
            'maxOutputTokens' => $config['max_tokens'] ?? 2048
        ]
    ];
    
    // Adicionar contexto como system instruction (se existir)
    if (!empty($contexto)) {
        $payload['systemInstruction'] = [
            'parts' => [['text' => $contexto]]
        ];
    }
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);

    if ($curlErrno !== 0) {
        throw new Exception("Erro cURL #{$curlErrno}: {$curlError}");
    }

    if ($httpCode !== 200) {
        $data = json_decode($response, true);
        $errorMsg = $data['error']['message'] ?? "Erro HTTP {$httpCode}";
        throw new Exception($errorMsg);
    }

    $data = json_decode($response, true);
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Sem resposta';
}
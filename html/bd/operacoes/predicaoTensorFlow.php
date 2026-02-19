<?php
/**
 * SIMP - Predição via TensorFlow/XGBoost
 * 
 * Endpoint PHP que faz ponte entre o frontend e o microserviço
 * Python/XGBoost rodando em container separado.
 * 
 * Ações disponíveis:
 *   - predict:    Predição de valores horários (XGBoost ou fallback estatístico)
 *   - anomalies:  Detecção de anomalias (Autoencoder + Z-score + regras)
 *   - correlate:  Correlação entre pontos para fórmulas de substituição
 *   - train:      Treinar modelo para um ponto específico
 *   - status:     Status dos modelos treinados
 *   - health:     Verificar se o serviço está online
 * 
 * Localização: html/bd/operacoes/predicaoTensorFlow.php
 * 
 * @author Bruno - CESAN
 * @version 2.0 - Migração para XGBoost + remoção FINDESLAB
 * @date 2026-02
 */

// Desabilitar exibição de erros no output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Iniciar buffer para capturar qualquer output indesejado
ob_start();

/**
 * Retorna JSON limpo e encerra execução
 */
function retornarJSON_TF($data) {
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
    // Configuração do microserviço TensorFlow
    // ========================================
    
    // URL do container TensorFlow (mesmo Docker network)
    $tensorflowUrl = getenv('TENSORFLOW_URL') ?: 'http://simp20-tensorflow:5000';
    
    // Timeout para requisições (treino pode demorar mais)
    $timeoutPadrao = 30;
    $timeoutTreino = 300; // 5 minutos para treino

    // ========================================
    // Receber dados da requisição
    // ========================================
    $rawInput = file_get_contents('php://input');
    $dados = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Tentar GET params
        $dados = $_GET;
    }
    
    $acao = $dados['acao'] ?? $_GET['acao'] ?? '';
    
    if (empty($acao)) {
        retornarJSON_TF([
            'success' => false,
            'error' => 'Parâmetro "acao" é obrigatório. Valores: predict, anomalies, correlate, train, status, health'
        ]);
    }

    // ========================================
    // Roteamento por ação
    // ========================================
    switch ($acao) {
        
        // ----------------------------------------
        // HEALTH CHECK - Verificar se o serviço está online
        // ----------------------------------------
        case 'health':
            $resposta = chamarTensorFlow($tensorflowUrl . '/health', 'GET', null, 5);
            retornarJSON_TF([
                'success' => true,
                'tensorflow' => $resposta
            ]);
            break;
        
        // ----------------------------------------
        // PREDICT - Predição de valores horários
        // ----------------------------------------
        case 'predict':
            $cdPonto = intval($dados['cd_ponto'] ?? 0);
            $data = $dados['data'] ?? '';
            $horas = $dados['horas'] ?? null;
            $tipoMedidor = intval($dados['tipo_medidor'] ?? 1);
            $semanasHistorico = intval($dados['semanas_historico'] ?? 12);
            
            if (!$cdPonto || !$data) {
                retornarJSON_TF([
                    'success' => false,
                    'error' => 'cd_ponto e data são obrigatórios'
                ]);
            }
            
            $payload = [
                'cd_ponto' => $cdPonto,
                'data' => $data,
                'tipo_medidor' => $tipoMedidor,
                'semanas_historico' => $semanasHistorico
            ];
            
            if ($horas !== null) {
                $payload['horas'] = $horas;
            }
            
            $resposta = chamarTensorFlow(
                $tensorflowUrl . '/api/predict', 
                'POST', 
                $payload, 
                $timeoutPadrao
            );

            retornarJSON_TF($resposta);
            break;
        
        // ----------------------------------------
        // ANOMALIES - Detecção de anomalias
        // ----------------------------------------
        case 'anomalies':
            $cdPonto = intval($dados['cd_ponto'] ?? 0);
            $data = $dados['data'] ?? '';
            $tipoMedidor = intval($dados['tipo_medidor'] ?? 1);
            $sensibilidade = floatval($dados['sensibilidade'] ?? 0.8);
            
            if (!$cdPonto || !$data) {
                retornarJSON_TF([
                    'success' => false,
                    'error' => 'cd_ponto e data são obrigatórios'
                ]);
            }
            
            $resposta = chamarTensorFlow(
                $tensorflowUrl . '/api/anomalies',
                'POST',
                [
                    'cd_ponto' => $cdPonto,
                    'data' => $data,
                    'tipo_medidor' => $tipoMedidor,
                    'sensibilidade' => $sensibilidade
                ],
                $timeoutPadrao
            );
            
            retornarJSON_TF($resposta);
            break;
        
        // ----------------------------------------
        // CORRELATE - Correlação entre pontos
        // ----------------------------------------
        case 'correlate':
            $cdPontoOrigem = intval($dados['cd_ponto_origem'] ?? 0);
            $cdPontosCandidatos = $dados['cd_pontos_candidatos'] ?? [];
            $dataInicio = $dados['data_inicio'] ?? '';
            $dataFim = $dados['data_fim'] ?? '';
            $tipoMedidor = intval($dados['tipo_medidor'] ?? 1);
            
            if (!$cdPontoOrigem || !$dataInicio || !$dataFim) {
                retornarJSON_TF([
                    'success' => false,
                    'error' => 'cd_ponto_origem, data_inicio e data_fim são obrigatórios'
                ]);
            }
            
            $resposta = chamarTensorFlow(
                $tensorflowUrl . '/api/correlate',
                'POST',
                [
                    'cd_ponto_origem' => $cdPontoOrigem,
                    'cd_pontos_candidatos' => $cdPontosCandidatos,
                    'data_inicio' => $dataInicio,
                    'data_fim' => $dataFim,
                    'tipo_medidor' => $tipoMedidor
                ],
                60 // Correlação pode demorar um pouco mais
            );
            
            retornarJSON_TF($resposta);
            break;
        
        // ----------------------------------------
        // TRAIN - Treinar modelo para um ponto
        // ----------------------------------------
        case 'train':
            // Verificar permissão (apenas administradores)
            session_start();
            $perfil = $_SESSION['perfil'] ?? '';
            if (!in_array($perfil, ['ADMIN', 'DESENVOLVEDOR'])) {
                retornarJSON_TF([
                    'success' => false,
                    'error' => 'Permissão negada. Apenas administradores podem treinar modelos.'
                ]);
            }
            
            $cdPonto = intval($dados['cd_ponto'] ?? 0);
            $semanas = intval($dados['semanas'] ?? 24);
            $tipoMedidor = intval($dados['tipo_medidor'] ?? 1);
            $force = boolval($dados['force'] ?? false);
            
            if (!$cdPonto) {
                retornarJSON_TF([
                    'success' => false,
                    'error' => 'cd_ponto é obrigatório'
                ]);
            }
            
            $resposta = chamarTensorFlow(
                $tensorflowUrl . '/api/train',
                'POST',
                [
                    'cd_ponto' => $cdPonto,
                    'semanas' => $semanas,
                    'tipo_medidor' => $tipoMedidor,
                    'force' => $force
                ],
                $timeoutTreino
            );
            
            // Registrar log do treino
            try {
                include_once __DIR__ . '/../conexao.php';
                if (isset($pdoSIMP) && function_exists('registrarLog')) {
                    $status = ($resposta['success'] ?? false) ? 'SUCESSO' : 'ERRO';
                    registrarLog(
                        $pdoSIMP,
                        'TENSORFLOW',
                        'TREINO',
                        "Ponto: $cdPonto, Semanas: $semanas",
                        $status,
                        $_SESSION['cd_usuario'] ?? null
                    );
                }
            } catch (Exception $logEx) {
                // Log silencioso
            }
            
            retornarJSON_TF($resposta);
            break;
        
        // ----------------------------------------
        // STATUS - Status dos modelos treinados
        // ----------------------------------------
        case 'status':
            $resposta = chamarTensorFlow(
                $tensorflowUrl . '/api/model-status',
                'GET',
                null,
                10
            );
            retornarJSON_TF($resposta);
            break;
        
        // ----------------------------------------
        // Ação desconhecida
        // ----------------------------------------
        default:
            retornarJSON_TF([
                'success' => false,
                'error' => "Ação desconhecida: $acao"
            ]);
    }

} catch (Exception $e) {
    retornarJSON_TF([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}


// ============================================
// Função auxiliar: Chamada HTTP ao microserviço
// ============================================

/**
 * Faz requisição HTTP ao microserviço TensorFlow/XGBoost.
 * Bypassa proxy corporativo para comunicação interna Docker.
 * 
 * @param string $url      URL completa do endpoint
 * @param string $method   GET ou POST
 * @param array|null $data Dados para enviar (POST)
 * @param int $timeout     Timeout em segundos
 * @return array           Resposta decodificada do JSON
 * @throws Exception       Se o serviço não responder
 */
function chamarTensorFlow(string $url, string $method = 'POST', ?array $data = null, int $timeout = 30): array {
    if (!function_exists('curl_init')) {
        throw new Exception('Extensão cURL não está instalada');
    }
    
    $ch = curl_init();
    
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Cache-Control: no-cache, no-store',
            'Pragma: no-cache'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        // Bypass proxy corporativo para chamadas internas Docker
        CURLOPT_PROXY => '',
        CURLOPT_NOPROXY => '*',
        CURLOPT_FRESH_CONNECT => true,
        CURLOPT_FORBID_REUSE => true
    ];
    
    if ($method === 'POST' && $data !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    
    curl_setopt_array($ch, $options);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    // Erro de conexão (serviço offline)
    if ($curlErrno !== 0) {
        return [
            'success' => false,
            'error' => "Serviço TensorFlow indisponível: $curlError",
            'tensorflow_offline' => true,
            'fallback' => 'Usando método estatístico padrão do SIMP'
        ];
    }
    
    // Decodificar resposta
    $decoded = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => "Resposta inválida do TensorFlow (HTTP $httpCode)",
            'raw' => substr($response, 0, 500)
        ];
    }
    
    return $decoded;
}
<?php
/**
 * SIMP 2.0 - Fase A2: Motor Batch de Tratamento
 *
 * Analisa todos os pontos ativos, detecta anomalias (3 camadas + XGBoost),
 * classifica como correcao tecnica ou evento operacional, calcula score
 * de confianca composto e grava pendencias na IA_PENDENCIA_TRATAMENTO.
 *
 * Acoes:
 *   - executar_batch:   Processa todos os pontos para uma data (cron ou manual)
 *   - status_batch:     Resumo do ultimo processamento
 *   - reprocessar_ponto: Reprocessa um ponto especifico
 *
 * Fluxo:
 *   1. Buscar pontos ativos com TAG integrada
 *   2. Para cada ponto: chamar /api/anomalies + /api/predict no TensorFlow
 *   3. Classificar anomalia (tecnica vs operacional) via vizinhos no grafo
 *   4. Calcular score de confianca composto (5 componentes)
 *   5. UPSERT na IA_PENDENCIA_TRATAMENTO (idempotente)
 *
 * Localização: html/bd/operacoes/motorBatchTratamento.php
 *
 * @author  Bruno - CESAN
 * @version 1.0 - Fase A2
 * @date    2026-02
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('max_execution_time', 600); // 10 min para batch completo
ob_start();

/**
 * Retorna JSON limpo e encerra execucao.
 */
function retornarJSON_Batch($data)
{
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Capturar erros fatais
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => 'Erro PHP fatal: ' . $error['message']
        ], JSON_UNESCAPED_UNICODE);
    }
});

try {
    // ========================================
    // Conexao e configuracao
    // ========================================
    @include_once __DIR__ . '/../conexao.php';
    @include_once __DIR__ . '/../includes/logHelper.php';

    if (!isset($pdoSIMP)) {
        retornarJSON_Batch(['success' => false, 'error' => 'Conexao com banco nao estabelecida']);
    }

    // URL do container TensorFlow
    $tensorflowUrl = getenv('TENSORFLOW_URL') ?: 'http://simp20-tensorflow:5000';

    // Receber dados da requisicao
    $rawInput = file_get_contents('php://input');
    $dados    = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $dados = $_GET;
    }

    $acao = $dados['acao'] ?? $_GET['acao'] ?? '';

    if (empty($acao)) {
        retornarJSON_Batch([
            'success' => false,
            'error'   => 'Parametro "acao" obrigatorio. Valores: executar_batch, status_batch, reprocessar_ponto, progresso_batch'
        ]);
    }

    // Para polling de progresso, liberar session lock imediatamente
    if ($acao === 'progresso_batch') {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    // ========================================
    // Roteamento
    // ========================================
    switch ($acao) {

        case 'executar_batch':
            $dataRef = $dados['data'] ?? date('Y-m-d', strtotime('-1 day'));
            $resultado = executarBatch($pdoSIMP, $tensorflowUrl, $dataRef);
            retornarJSON_Batch($resultado);
            break;

        case 'status_batch':
            $dataRef = $dados['data'] ?? date('Y-m-d', strtotime('-1 day'));
            $resultado = statusBatch($pdoSIMP, $dataRef);
            retornarJSON_Batch($resultado);
            break;

        case 'reprocessar_ponto':
            $cdPonto = intval($dados['cd_ponto'] ?? 0);
            $dataRef = $dados['data'] ?? date('Y-m-d', strtotime('-1 day'));
            if (!$cdPonto) {
                retornarJSON_Batch(['success' => false, 'error' => 'cd_ponto obrigatorio']);
            }
            $resultado = processarPonto($pdoSIMP, $tensorflowUrl, $cdPonto, $dataRef);
            retornarJSON_Batch(['success' => true, 'ponto' => $resultado]);
            break;

        case 'progresso_batch':
            $resultado = progressoBatch();
            retornarJSON_Batch($resultado);
            break;

        default:
            retornarJSON_Batch(['success' => false, 'error' => "Acao '$acao' nao reconhecida"]);
    }

} catch (Exception $e) {
    retornarJSON_Batch(['success' => false, 'error' => $e->getMessage()]);
}


// ============================================================
// FUNCAO PRINCIPAL: EXECUTAR BATCH
// ============================================================

/**
 * Executa o motor batch para todos os pontos ativos.
 *
 * @param PDO    $pdo    Conexao PDO com SIMP
 * @param string $tfUrl  URL do container TensorFlow
 * @param string $data   Data de referencia (YYYY-MM-DD)
 * @return array         Resumo do processamento
 */
function executarBatch(PDO $pdo, string $tfUrl, string $data): array
{
    $inicio = microtime(true);

    // Liberar session lock para permitir polling simultaneo
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    // Arquivo de progresso do batch (JSON temporario)
    $progressFile = sys_get_temp_dir() . '/simp_batch_progress.json';

    // 1. Buscar pontos ativos com integracao
    $pontos = buscarPontosAtivos($pdo);

    $resultados = [
        'data'           => $data,
        'total_pontos'   => count($pontos),
        'processados'    => 0,
        'com_anomalia'   => 0,
        'pendencias_geradas' => 0,
        'erros'          => 0,
        'detalhes'       => []
    ];

    // 2. Buscar vizinhos do grafo (para classificacao topologica)
    $mapaVizinhos = buscarMapaVizinhos($pdo);

    // 3. Buscar versao topologia atual
    $versaoTopologia = buscarVersaoTopologiaAtual($pdo);

    // Gravar progresso inicial
    $progressData = [
        'status'             => 'running',
        'data'               => $data,
        'total'              => count($pontos),
        'processados'        => 0,
        'com_anomalia'       => 0,
        'pendencias_geradas' => 0,
        'erros'              => 0,
        'ponto_atual'        => null,
        'inicio'             => date('c'),
        'fim'                => null,
        'tempo_decorrido'    => 0
    ];
    @file_put_contents($progressFile, json_encode($progressData, JSON_UNESCAPED_UNICODE));

    // 4. Processar cada ponto
    foreach ($pontos as $ponto) {
        // Atualizar progresso com ponto atual
        $progressData['ponto_atual'] = $ponto['DS_NOME'] ?? ('Ponto #' . $ponto['CD_PONTO_MEDICAO']);
        $progressData['tempo_decorrido'] = round(microtime(true) - $inicio, 1);
        @file_put_contents($progressFile, json_encode($progressData, JSON_UNESCAPED_UNICODE));

        try {
            $resPonto = processarPonto($pdo, $tfUrl, $ponto['CD_PONTO_MEDICAO'], $data, $ponto, $mapaVizinhos, $versaoTopologia);

            $resultados['processados']++;
            if ($resPonto['anomalias_detectadas'] > 0) {
                $resultados['com_anomalia']++;
            }
            $resultados['pendencias_geradas'] += $resPonto['pendencias_gravadas'];
            $resultados['detalhes'][] = $resPonto;

            // Atualizar contadores no progresso
            $progressData['processados'] = $resultados['processados'];
            $progressData['com_anomalia'] = $resultados['com_anomalia'];
            $progressData['pendencias_geradas'] = $resultados['pendencias_geradas'];

        } catch (Exception $e) {
            $resultados['erros']++;
            $progressData['erros'] = $resultados['erros'];
            $resultados['detalhes'][] = [
                'cd_ponto'  => $ponto['CD_PONTO_MEDICAO'],
                'ds_nome'   => $ponto['DS_NOME'],
                'erro'      => $e->getMessage()
            ];
        }
    }

    // Gravar progresso final
    $progressData['status'] = $resultados['erros'] > 0 ? 'error' : 'completed';
    $progressData['processados'] = $resultados['processados'];
    $progressData['com_anomalia'] = $resultados['com_anomalia'];
    $progressData['pendencias_geradas'] = $resultados['pendencias_geradas'];
    $progressData['erros'] = $resultados['erros'];
    $progressData['ponto_atual'] = null;
    $progressData['fim'] = date('c');
    $progressData['tempo_decorrido'] = round(microtime(true) - $inicio, 1);
    @file_put_contents($progressFile, json_encode($progressData, JSON_UNESCAPED_UNICODE));

    $resultados['tempo_segundos'] = round(microtime(true) - $inicio, 2);

    // Log do batch (isolado)
    try {
        if (function_exists('registrarLog')) {
            registrarLog(
                $pdo,
                'BATCH_TRATAMENTO',
                'PROCESSAMENTO',
                "Data: $data | Pontos: {$resultados['processados']}/{$resultados['total_pontos']} | " .
                "Pendencias: {$resultados['pendencias_geradas']} | Erros: {$resultados['erros']} | " .
                "Tempo: {$resultados['tempo_segundos']}s",
                $resultados['erros'] > 0 ? 'PARCIAL' : 'SUCESSO',
                $_SESSION['cd_usuario'] ?? 0
            );
        }
    } catch (Exception $logEx) {
        // Silencioso
    }

    return ['success' => true] + $resultados;
}


// ============================================================
// PROCESSAR PONTO INDIVIDUAL
// ============================================================

/**
 * Processa um ponto individual: detecta anomalias, classifica e grava pendencias.
 *
 * @param PDO         $pdo              Conexao PDO
 * @param string      $tfUrl            URL TensorFlow
 * @param int         $cdPonto          Codigo do ponto
 * @param string      $data             Data de referencia
 * @param array|null  $infoPonto        Info pre-carregada do ponto (opcional)
 * @param array|null  $mapaVizinhos     Mapa de vizinhos do grafo (opcional)
 * @param int|null    $versaoTopologia  CD_CHAVE da versao atual (opcional)
 * @return array      Resultado do processamento
 */
function processarPonto(
    PDO $pdo,
    string $tfUrl,
    int $cdPonto,
    string $data,
    ?array $infoPonto = null,
    ?array $mapaVizinhos = null,
    ?int $versaoTopologia = null
): array {

    // Buscar info do ponto se nao foi passada
    if ($infoPonto === null) {
        $stmt = $pdo->prepare("
            SELECT PM.CD_PONTO_MEDICAO, PM.DS_NOME, PM.ID_TIPO_MEDIDOR,
                   PM.DS_TAG_VAZAO, PM.DS_TAG_PRESSAO, PM.DS_TAG_RESERVATORIO
            FROM SIMP.dbo.PONTO_MEDICAO PM
            WHERE PM.CD_PONTO_MEDICAO = ?
              AND (PM.DT_DESATIVACAO IS NULL OR PM.DT_DESATIVACAO > GETDATE())
        ");
        $stmt->execute([$cdPonto]);
        $infoPonto = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$infoPonto) {
            throw new Exception("Ponto $cdPonto nao encontrado ou inativo");
        }
    }

    $tipoMedidor = intval($infoPonto['ID_TIPO_MEDIDOR']);
    $resultado = [
        'cd_ponto'            => $cdPonto,
        'ds_nome'             => $infoPonto['DS_NOME'],
        'tipo_medidor'        => $tipoMedidor,
        'anomalias_detectadas' => 0,
        'pendencias_gravadas' => 0
    ];

    // ========================================
    // 1. Chamar deteccao de anomalias (TensorFlow)
    // ========================================
    $anomalias = chamarDeteccaoAnomalias($tfUrl, $cdPonto, $data, $tipoMedidor);

    if (empty($anomalias)) {
        return $resultado;
    }

    $resultado['anomalias_detectadas'] = count($anomalias);

    // ========================================
    // 2. Chamar predicao XGBoost (para score do modelo)
    // ========================================
    $predicoes = chamarPredicaoXGBoost($tfUrl, $cdPonto, $data, $tipoMedidor);

    // ========================================
    // 3. Classificar e gravar cada anomalia
    // ========================================
    // Carregar vizinhos se necessario
    if ($mapaVizinhos === null) {
        $mapaVizinhos = buscarMapaVizinhos($pdo);
    }
    if ($versaoTopologia === null) {
        $versaoTopologia = buscarVersaoTopologiaAtual($pdo);
    }

    // Buscar anomalias dos vizinhos na mesma data (para classificacao)
    $vizinhosCdPonto = $mapaVizinhos[$cdPonto] ?? [];
    $anomaliasVizinhos = buscarAnomaliasVizinhos($pdo, $vizinhosCdPonto, $data);

    // Buscar historico de anomalias deste ponto (para score historico)
    $historicoAnomalias = buscarHistoricoAnomaliasPonto($pdo, $cdPonto);

    foreach ($anomalias as $anomalia) {
        $hora = intval($anomalia['hora'] ?? -1);
        if ($hora < 0 || $hora > 23) continue;

        // ---- Score de confianca composto ----
        $scores = calcularScoreComposto(
            $anomalia,
            $predicoes[$hora] ?? null,
            $vizinhosCdPonto,
            $anomaliasVizinhos,
            $hora,
            $historicoAnomalias
        );

        // Filtro: so gera pendencia se confianca >= 0.70
        if ($scores['confianca'] < 0.70) continue;

        // ---- Classificar: tecnica vs operacional ----
        $classificacao = classificarAnomalia($hora, $vizinhosCdPonto, $anomaliasVizinhos);

        // ---- Mapear tipo de anomalia ----
        $tipoAnomalia = mapearTipoAnomalia($anomalia);

        // ---- Valor sugerido ----
        $vlSugerido = determinarValorSugerido($anomalia, $predicoes[$hora] ?? null);

        // ---- UPSERT na tabela ----
        $gravou = gravarPendencia($pdo, [
            'cd_ponto'            => $cdPonto,
            'dt_referencia'       => $data,
            'nr_hora'             => $hora,
            'id_tipo_anomalia'    => $tipoAnomalia,
            'id_classe_anomalia'  => $classificacao['classe'],
            'ds_severidade'       => $anomalia['severidade'] ?? 'media',
            'vl_real'             => $anomalia['valor_real'] ?? null,
            'vl_sugerido'         => $vlSugerido,
            'vl_media_historica'  => $anomalia['valor_esperado'] ?? null,
            'vl_predicao_xgboost' => $predicoes[$hora]['valor'] ?? null,
            'vl_confianca'        => $scores['confianca'],
            'vl_score_estatistico' => $scores['estatistico'],
            'vl_score_modelo'     => $scores['modelo'],
            'vl_score_topologico' => $scores['topologico'],
            'vl_score_historico'  => $scores['historico'],
            'vl_score_padrao'     => $scores['padrao'],
            'ds_metodo_deteccao'  => $anomalia['metodo'] ?? 'combinado',
            'vl_zscore'           => $anomalia['z_score'] ?? null,
            'ds_descricao'        => mb_substr($anomalia['descricao'] ?? '', 0, 500),
            'cd_versao_topologia' => $versaoTopologia,
            'ds_versao_modelo'    => 'v6.1',
            'qtd_vizinhos_anomalos' => $classificacao['qtd_vizinhos_anomalos'],
            'ds_vizinhos_anomalos'  => $classificacao['vizinhos_json'],
            'op_evento_propagado'   => $classificacao['propagado'] ? 1 : 0,
            'id_tipo_medidor'     => $tipoMedidor
        ]);

        if ($gravou) {
            $resultado['pendencias_gravadas']++;
        }
    }

    return $resultado;
}


// ============================================================
// CHAMADAS AO TENSORFLOW
// ============================================================

/**
 * Chama deteccao de anomalias no container TensorFlow.
 * Retorna lista de anomalias ou array vazio.
 */
function chamarDeteccaoAnomalias(string $tfUrl, int $cdPonto, string $data, int $tipoMedidor): array
{
    $resp = chamarTensorFlowBatch($tfUrl . '/api/anomalies', [
        'cd_ponto'      => $cdPonto,
        'data'          => $data,
        'tipo_medidor'  => $tipoMedidor,
        'sensibilidade' => 0.8
    ]);

    if (!($resp['success'] ?? false)) {
        return [];
    }

    return $resp['anomalias'] ?? [];
}

/**
 * Chama predicao XGBoost no container TensorFlow.
 * Retorna mapa hora => {valor, confianca, metodo}.
 */
function chamarPredicaoXGBoost(string $tfUrl, int $cdPonto, string $data, int $tipoMedidor): array
{
    $resp = chamarTensorFlowBatch($tfUrl . '/api/predict', [
        'cd_ponto'     => $cdPonto,
        'data'         => $data,
        'tipo_medidor' => $tipoMedidor,
        'horas'        => range(0, 23)
    ]);

    $mapa = [];
    if (($resp['success'] ?? false) && !empty($resp['predicoes'])) {
        foreach ($resp['predicoes'] as $pred) {
            $h = intval($pred['hora'] ?? -1);
            if ($h >= 0 && $h <= 23) {
                $mapa[$h] = [
                    'valor'     => floatval($pred['valor_predito'] ?? 0),
                    'confianca' => floatval($pred['confianca'] ?? 0),
                    'metodo'    => $pred['metodo'] ?? 'desconhecido'
                ];
            }
        }
    }
    return $mapa;
}

/**
 * Chama endpoint do TensorFlow via cURL (bypass proxy, timeout curto).
 */
function chamarTensorFlowBatch(string $url, array $payload, int $timeout = 30): array
{
    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'cURL nao disponivel'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_PROXY          => '',
        CURLOPT_NOPROXY        => '*'
    ]);

    $response = curl_exec($ch);
    $errno    = curl_errno($ch);
    curl_close($ch);

    if ($errno !== 0) {
        return ['success' => false, 'tensorflow_offline' => true];
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : ['success' => false, 'error' => 'Resposta invalida'];
}


// ============================================================
// SCORE DE CONFIANCA COMPOSTO
// ============================================================

/**
 * Calcula score de confianca composto (5 componentes ponderados).
 *
 * Formula: 0.30*Estat + 0.30*Modelo + 0.20*Topologia + 0.10*Historico + 0.10*Padrao
 *
 * @param array      $anomalia           Dados da anomalia detectada
 * @param array|null $predicaoHora       Predicao XGBoost para esta hora
 * @param array      $vizinhosCdPonto    Lista de CD_PONTO dos vizinhos
 * @param array      $anomaliasVizinhos  Anomalias dos vizinhos na mesma data
 * @param int        $hora               Hora (0-23)
 * @param array      $historicoAnomalias Historico de anomalias deste ponto
 * @return array     Scores individuais + confianca final
 */
function calcularScoreComposto(
    array $anomalia,
    ?array $predicaoHora,
    array $vizinhosCdPonto,
    array $anomaliasVizinhos,
    int $hora,
    array $historicoAnomalias
): array {

    // ---- 1. Score Estatistico (0.30) ----
    // Baseado no z-score: quanto maior, mais confiavel a deteccao
    $zScore = floatval($anomalia['z_score'] ?? 0);
    $scoreEstatistico = min(1.0, $zScore / 5.0); // z=5 -> score=1.0
    // Se detectado por regra deterministica, score alto
    if (($anomalia['metodo'] ?? '') === 'regras') {
        $scoreEstatistico = max($scoreEstatistico, 0.9);
    }

    // ---- 2. Score Modelo (0.30) ----
    // Diferenca entre valor real e predicao XGBoost
    $scoreModelo = 0.5; // Default se nao houver predicao
    if ($predicaoHora !== null && isset($anomalia['valor_real'])) {
        $vlReal = floatval($anomalia['valor_real']);
        $vlPred = floatval($predicaoHora['valor']);
        if ($vlPred > 0) {
            $desvioPerc = abs($vlReal - $vlPred) / $vlPred;
            $scoreModelo = min(1.0, $desvioPerc / 0.5); // 50% desvio -> score=1.0
        }
    }

    // ---- 3. Score Topologico (0.20) ----
    // Se vizinhos estao normais e so este ponto diverge = alta confianca de erro tecnico
    $scoreTopologico = 0.5; // Default
    if (!empty($vizinhosCdPonto)) {
        $vizinhosComAnomalia = 0;
        foreach ($vizinhosCdPonto as $cdViz) {
            if (isset($anomaliasVizinhos[$cdViz][$hora])) {
                $vizinhosComAnomalia++;
            }
        }
        // Quanto MENOS vizinhos anomalos, MAIS confianca (= erro tecnico isolado)
        $proporcaoNormais = 1.0 - ($vizinhosComAnomalia / count($vizinhosCdPonto));
        $scoreTopologico = $proporcaoNormais;
    }

    // ---- 4. Score Historico (0.10) ----
    // Este tipo de anomalia ja ocorreu neste ponto antes?
    $tipoAnomalia = $anomalia['tipo'] ?? '';
    $scoreHistorico = 0.5;
    if (!empty($historicoAnomalias)) {
        $ocorrencias = 0;
        foreach ($historicoAnomalias as $hist) {
            if (($hist['DS_METODO_DETECCAO'] ?? '') === ($anomalia['metodo'] ?? '')) {
                $ocorrencias++;
            }
        }
        // Mais ocorrencias = mais confianca (padrao recorrente)
        $scoreHistorico = min(1.0, $ocorrencias / 10.0);
    }

    // ---- 5. Score Padrao (0.10) ----
    // Padrao ja foi visto E validado por operador anteriormente?
    $scorePadrao = 0.3; // Default conservador
    if (!empty($historicoAnomalias)) {
        $validados = 0;
        foreach ($historicoAnomalias as $hist) {
            if (in_array(intval($hist['ID_STATUS'] ?? 0), [1, 2])) { // Aprovada ou ajustada
                $validados++;
            }
        }
        $scorePadrao = min(1.0, $validados / 5.0);
    }

    // ---- Composicao final ----
    $confianca = (0.30 * $scoreEstatistico)
               + (0.30 * $scoreModelo)
               + (0.20 * $scoreTopologico)
               + (0.10 * $scoreHistorico)
               + (0.10 * $scorePadrao);

    return [
        'confianca'    => round(min(1.0, max(0.0, $confianca)), 4),
        'estatistico'  => round($scoreEstatistico, 4),
        'modelo'       => round($scoreModelo, 4),
        'topologico'   => round($scoreTopologico, 4),
        'historico'    => round($scoreHistorico, 4),
        'padrao'       => round($scorePadrao, 4)
    ];
}


// ============================================================
// CLASSIFICACAO: TECNICA vs OPERACIONAL
// ============================================================

/**
 * Classifica anomalia: correcao tecnica (1) ou evento operacional (2).
 *
 * Regra: se >= 2 vizinhos tambem estao anomalos na mesma hora,
 * provavelmente e um evento operacional real (falta d'agua, vazamento).
 * NAO deve ser corrigido automaticamente.
 *
 * @param int   $hora                Hora da anomalia
 * @param array $vizinhosCdPonto     Lista de CD_PONTO dos vizinhos
 * @param array $anomaliasVizinhos   Anomalias dos vizinhos indexadas por ponto/hora
 * @return array  {classe, qtd_vizinhos_anomalos, vizinhos_json, propagado}
 */
function classificarAnomalia(int $hora, array $vizinhosCdPonto, array $anomaliasVizinhos): array
{
    $vizinhosAnomalos = [];

    foreach ($vizinhosCdPonto as $cdViz) {
        if (isset($anomaliasVizinhos[$cdViz][$hora])) {
            $vizinhosAnomalos[] = $cdViz;
        }
    }

    $qtd = count($vizinhosAnomalos);

    return [
        'classe'                 => $qtd >= 2 ? 2 : 1,  // 2+ vizinhos = evento operacional
        'qtd_vizinhos_anomalos'  => $qtd,
        'vizinhos_json'          => !empty($vizinhosAnomalos) ? json_encode($vizinhosAnomalos) : null,
        'propagado'              => $qtd >= 2
    ];
}


// ============================================================
// MAPEAMENTO E DETERMINACAO DE VALORES
// ============================================================

/**
 * Mapeia tipo textual da anomalia para ID numerico.
 */
function mapearTipoAnomalia(array $anomalia): int
{
    $tipo = $anomalia['tipo'] ?? '';
    $metodo = $anomalia['metodo'] ?? '';

    $mapa = [
        'zeros_prolongados'     => 1,
        'valor_zerado'          => 1,
        'valor_constante'       => 2,
        'sensor_travado'        => 2,
        'spike'                 => 3,
        'valor_extremo'         => 3,
        'desvio_acima'          => 4,
        'desvio_abaixo'         => 4,
        'padrao_incomum'        => 5,
        'desvio_modelo'         => 6,
        'gap_comunicacao'       => 7,
        'sem_dados'             => 7,
        'fora_faixa'            => 8,
        'valor_negativo'        => 8,
    ];

    // Buscar pelo tipo primeiro, depois pelo metodo
    if (isset($mapa[$tipo])) return $mapa[$tipo];

    // Fallback por metodo
    switch ($metodo) {
        case 'regras':      return 1;
        case 'zscore':      return 4;
        case 'autoencoder': return 5;
        case 'xgboost':     return 6;
        default:            return 4; // Default: desvio estatistico
    }
}

/**
 * Determina o valor sugerido para correcao.
 * Prioridade: predicao XGBoost > media historica > valor esperado.
 */
function determinarValorSugerido(array $anomalia, ?array $predicaoHora): ?float
{
    // Prioridade 1: predicao XGBoost
    if ($predicaoHora !== null && isset($predicaoHora['valor']) && $predicaoHora['valor'] > 0) {
        return round(floatval($predicaoHora['valor']), 4);
    }

    // Prioridade 2: valor esperado da anomalia (media historica)
    if (isset($anomalia['valor_esperado']) && floatval($anomalia['valor_esperado']) > 0) {
        return round(floatval($anomalia['valor_esperado']), 4);
    }

    return null;
}


// ============================================================
// CONSULTAS AO BANCO
// ============================================================

/**
 * Busca todos os pontos de medicao ativos com TAG integrada.
 */
function buscarPontosAtivos(PDO $pdo): array
{
    $sql = "
        SELECT 
            PM.CD_PONTO_MEDICAO,
            PM.DS_NOME,
            PM.ID_TIPO_MEDIDOR,
            PM.DS_TAG_VAZAO,
            PM.DS_TAG_PRESSAO,
            PM.DS_TAG_RESERVATORIO
        FROM SIMP.dbo.PONTO_MEDICAO PM
        WHERE (PM.DT_DESATIVACAO IS NULL OR PM.DT_DESATIVACAO > GETDATE())
          AND (PM.DS_TAG_VAZAO IS NOT NULL 
               OR PM.DS_TAG_PRESSAO IS NOT NULL 
               OR PM.DS_TAG_RESERVATORIO IS NOT NULL)
        ORDER BY PM.ID_TIPO_MEDIDOR, PM.DS_NOME
    ";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Busca mapa de vizinhos do grafo (AUX_RELACAO_PONTOS_MEDICAO).
 * Retorna: [cd_ponto => [cd_vizinho1, cd_vizinho2, ...]]
 */
function buscarMapaVizinhos(PDO $pdo): array
{
    $sql = "
        SELECT 
            PM_P.CD_PONTO_MEDICAO AS CD_PRINCIPAL,
            PM_A.CD_PONTO_MEDICAO AS CD_AUXILIAR
        FROM SIMP.dbo.AUX_RELACAO_PONTOS_MEDICAO R
        INNER JOIN SIMP.dbo.PONTO_MEDICAO PM_P ON (
            R.TAG_PRINCIPAL = PM_P.DS_TAG_VAZAO 
            OR R.TAG_PRINCIPAL = PM_P.DS_TAG_PRESSAO 
            OR R.TAG_PRINCIPAL = PM_P.DS_TAG_RESERVATORIO
        )
        INNER JOIN SIMP.dbo.PONTO_MEDICAO PM_A ON (
            R.TAG_AUXILIAR = PM_A.DS_TAG_VAZAO 
            OR R.TAG_AUXILIAR = PM_A.DS_TAG_PRESSAO 
            OR R.TAG_AUXILIAR = PM_A.DS_TAG_RESERVATORIO
        )
        WHERE (PM_P.DT_DESATIVACAO IS NULL OR PM_P.DT_DESATIVACAO > GETDATE())
          AND (PM_A.DT_DESATIVACAO IS NULL OR PM_A.DT_DESATIVACAO > GETDATE())
    ";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $mapa = [];
    foreach ($rows as $row) {
        $cdP = intval($row['CD_PRINCIPAL']);
        $cdA = intval($row['CD_AUXILIAR']);
        if (!isset($mapa[$cdP])) $mapa[$cdP] = [];
        if (!in_array($cdA, $mapa[$cdP])) {
            $mapa[$cdP][] = $cdA;
        }
    }
    return $mapa;
}

/**
 * Busca versao de topologia mais recente.
 */
function buscarVersaoTopologiaAtual(PDO $pdo): ?int
{
    try {
        $stmt = $pdo->query("SELECT TOP 1 CD_CHAVE FROM SIMP.dbo.VERSAO_TOPOLOGIA ORDER BY DT_CADASTRO DESC");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? intval($row['CD_CHAVE']) : null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Busca anomalias ja detectadas nos vizinhos para a mesma data.
 * Usado na classificacao (tecnica vs operacional).
 * Retorna: [cd_ponto => [hora => true, ...]]
 */
function buscarAnomaliasVizinhos(PDO $pdo, array $vizinhosCdPonto, string $data): array
{
    if (empty($vizinhosCdPonto)) return [];

    $placeholders = implode(',', array_fill(0, count($vizinhosCdPonto), '?'));
    $sql = "
        SELECT CD_PONTO_MEDICAO, NR_HORA
        FROM SIMP.dbo.IA_PENDENCIA_TRATAMENTO
        WHERE CD_PONTO_MEDICAO IN ($placeholders)
          AND DT_REFERENCIA = ?
          AND ID_STATUS = 0
    ";
    $params = array_merge($vizinhosCdPonto, [$data]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $mapa = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cd = intval($row['CD_PONTO_MEDICAO']);
        $hr = intval($row['NR_HORA']);
        if (!isset($mapa[$cd])) $mapa[$cd] = [];
        $mapa[$cd][$hr] = true;
    }

    // Tambem considerar anomalias da tabela de metricas diarias (flags)
    $sqlFlags = "
        SELECT CD_PONTO_MEDICAO 
        FROM SIMP.dbo.IA_METRICAS_DIARIAS
        WHERE CD_PONTO_MEDICAO IN ($placeholders)
          AND DT_REFERENCIA = ?
          AND (FL_VALOR_ANOMALO = 1 OR FL_SENSOR_PROBLEMA = 1)
    ";
    $stmtFlags = $pdo->prepare($sqlFlags);
    $stmtFlags->execute($params);

    while ($row = $stmtFlags->fetch(PDO::FETCH_ASSOC)) {
        $cd = intval($row['CD_PONTO_MEDICAO']);
        if (!isset($mapa[$cd])) {
            // Marcar todas as horas como potencialmente anomalas
            $mapa[$cd] = [];
            for ($h = 0; $h < 24; $h++) {
                $mapa[$cd][$h] = true;
            }
        }
    }

    return $mapa;
}

/**
 * Busca historico recente de anomalias deste ponto (ultimos 30 dias).
 * Usado para calcular score historico e score padrao.
 */
function buscarHistoricoAnomaliasPonto(PDO $pdo, int $cdPonto): array
{
    try {
        $sql = "
            SELECT TOP 100 ID_TIPO_ANOMALIA, DS_METODO_DETECCAO, ID_STATUS, DT_REFERENCIA
            FROM SIMP.dbo.IA_PENDENCIA_TRATAMENTO
            WHERE CD_PONTO_MEDICAO = ?
              AND DT_REFERENCIA >= DATEADD(DAY, -30, GETDATE())
            ORDER BY DT_REFERENCIA DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cdPonto]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}


// ============================================================
// UPSERT NA TABELA
// ============================================================

/**
 * Grava pendencia na IA_PENDENCIA_TRATAMENTO (UPSERT por ponto+data+hora+tipo).
 * Idempotente: rodar 2x nao duplica.
 *
 * @param PDO   $pdo   Conexao
 * @param array $dados Dados da pendencia
 * @return bool        Se gravou (true) ou ja existia (false)
 */
function gravarPendencia(PDO $pdo, array $dados): bool
{
    // MERGE (UPSERT) - SQL Server
    $sql = "
        MERGE INTO SIMP.dbo.IA_PENDENCIA_TRATAMENTO AS target
        USING (SELECT 
            :cd_ponto AS CD_PONTO_MEDICAO,
            :dt_referencia AS DT_REFERENCIA,
            :nr_hora AS NR_HORA,
            :id_tipo_anomalia AS ID_TIPO_ANOMALIA
        ) AS source
        ON (
            target.CD_PONTO_MEDICAO = source.CD_PONTO_MEDICAO
            AND target.DT_REFERENCIA = source.DT_REFERENCIA
            AND target.NR_HORA = source.NR_HORA
            AND target.ID_TIPO_ANOMALIA = source.ID_TIPO_ANOMALIA
        )
        WHEN MATCHED AND target.ID_STATUS = 0 THEN
            UPDATE SET
                ID_CLASSE_ANOMALIA  = :id_classe_anomalia,
                DS_SEVERIDADE       = :ds_severidade,
                VL_REAL             = :vl_real,
                VL_SUGERIDO         = :vl_sugerido,
                VL_MEDIA_HISTORICA  = :vl_media_historica,
                VL_PREDICAO_XGBOOST = :vl_predicao_xgboost,
                VL_CONFIANCA        = :vl_confianca,
                VL_SCORE_ESTATISTICO = :vl_score_estatistico,
                VL_SCORE_MODELO     = :vl_score_modelo,
                VL_SCORE_TOPOLOGICO = :vl_score_topologico,
                VL_SCORE_HISTORICO  = :vl_score_historico,
                VL_SCORE_PADRAO     = :vl_score_padrao,
                DS_METODO_DETECCAO  = :ds_metodo_deteccao,
                VL_ZSCORE           = :vl_zscore,
                DS_DESCRICAO        = :ds_descricao,
                CD_VERSAO_TOPOLOGIA = :cd_versao_topologia,
                DS_VERSAO_MODELO    = :ds_versao_modelo,
                QTD_VIZINHOS_ANOMALOS = :qtd_vizinhos_anomalos,
                DS_VIZINHOS_ANOMALOS  = :ds_vizinhos_anomalos,
                OP_EVENTO_PROPAGADO   = :op_evento_propagado,
                ID_TIPO_MEDIDOR     = :id_tipo_medidor,
                DT_GERACAO          = GETDATE()
        WHEN NOT MATCHED THEN
            INSERT (
                CD_PONTO_MEDICAO, DT_REFERENCIA, NR_HORA, ID_TIPO_ANOMALIA,
                ID_CLASSE_ANOMALIA, DS_SEVERIDADE,
                VL_REAL, VL_SUGERIDO, VL_MEDIA_HISTORICA, VL_PREDICAO_XGBOOST,
                VL_CONFIANCA, VL_SCORE_ESTATISTICO, VL_SCORE_MODELO,
                VL_SCORE_TOPOLOGICO, VL_SCORE_HISTORICO, VL_SCORE_PADRAO,
                DS_METODO_DETECCAO, VL_ZSCORE, DS_DESCRICAO,
                CD_VERSAO_TOPOLOGIA, DS_VERSAO_MODELO,
                QTD_VIZINHOS_ANOMALOS, DS_VIZINHOS_ANOMALOS, OP_EVENTO_PROPAGADO,
                ID_TIPO_MEDIDOR, DS_ORIGEM
            ) VALUES (
                :cd_ponto2, :dt_referencia2, :nr_hora2, :id_tipo_anomalia2,
                :id_classe_anomalia2, :ds_severidade2,
                :vl_real2, :vl_sugerido2, :vl_media_historica2, :vl_predicao_xgboost2,
                :vl_confianca2, :vl_score_estatistico2, :vl_score_modelo2,
                :vl_score_topologico2, :vl_score_historico2, :vl_score_padrao2,
                :ds_metodo_deteccao2, :vl_zscore2, :ds_descricao2,
                :cd_versao_topologia2, :ds_versao_modelo2,
                :qtd_vizinhos_anomalos2, :ds_vizinhos_anomalos2, :op_evento_propagado2,
                :id_tipo_medidor2, 'BATCH'
            );
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            // MATCH / UPDATE
            ':cd_ponto'              => $dados['cd_ponto'],
            ':dt_referencia'         => $dados['dt_referencia'],
            ':nr_hora'               => $dados['nr_hora'],
            ':id_tipo_anomalia'      => $dados['id_tipo_anomalia'],
            ':id_classe_anomalia'    => $dados['id_classe_anomalia'],
            ':ds_severidade'         => $dados['ds_severidade'],
            ':vl_real'               => $dados['vl_real'],
            ':vl_sugerido'           => $dados['vl_sugerido'],
            ':vl_media_historica'    => $dados['vl_media_historica'],
            ':vl_predicao_xgboost'   => $dados['vl_predicao_xgboost'],
            ':vl_confianca'          => $dados['vl_confianca'],
            ':vl_score_estatistico'  => $dados['vl_score_estatistico'],
            ':vl_score_modelo'       => $dados['vl_score_modelo'],
            ':vl_score_topologico'   => $dados['vl_score_topologico'],
            ':vl_score_historico'    => $dados['vl_score_historico'],
            ':vl_score_padrao'       => $dados['vl_score_padrao'],
            ':ds_metodo_deteccao'    => $dados['ds_metodo_deteccao'],
            ':vl_zscore'             => $dados['vl_zscore'],
            ':ds_descricao'          => $dados['ds_descricao'],
            ':cd_versao_topologia'   => $dados['cd_versao_topologia'],
            ':ds_versao_modelo'      => $dados['ds_versao_modelo'],
            ':qtd_vizinhos_anomalos' => $dados['qtd_vizinhos_anomalos'],
            ':ds_vizinhos_anomalos'  => $dados['ds_vizinhos_anomalos'],
            ':op_evento_propagado'   => $dados['op_evento_propagado'],
            ':id_tipo_medidor'       => $dados['id_tipo_medidor'],
            // INSERT (mesmos valores, nomes diferentes para PDO)
            ':cd_ponto2'              => $dados['cd_ponto'],
            ':dt_referencia2'         => $dados['dt_referencia'],
            ':nr_hora2'               => $dados['nr_hora'],
            ':id_tipo_anomalia2'      => $dados['id_tipo_anomalia'],
            ':id_classe_anomalia2'    => $dados['id_classe_anomalia'],
            ':ds_severidade2'         => $dados['ds_severidade'],
            ':vl_real2'               => $dados['vl_real'],
            ':vl_sugerido2'           => $dados['vl_sugerido'],
            ':vl_media_historica2'    => $dados['vl_media_historica'],
            ':vl_predicao_xgboost2'   => $dados['vl_predicao_xgboost'],
            ':vl_confianca2'          => $dados['vl_confianca'],
            ':vl_score_estatistico2'  => $dados['vl_score_estatistico'],
            ':vl_score_modelo2'       => $dados['vl_score_modelo'],
            ':vl_score_topologico2'   => $dados['vl_score_topologico'],
            ':vl_score_historico2'    => $dados['vl_score_historico'],
            ':vl_score_padrao2'       => $dados['vl_score_padrao'],
            ':ds_metodo_deteccao2'    => $dados['ds_metodo_deteccao'],
            ':vl_zscore2'             => $dados['vl_zscore'],
            ':ds_descricao2'          => $dados['ds_descricao'],
            ':cd_versao_topologia2'   => $dados['cd_versao_topologia'],
            ':ds_versao_modelo2'      => $dados['ds_versao_modelo'],
            ':qtd_vizinhos_anomalos2' => $dados['qtd_vizinhos_anomalos'],
            ':ds_vizinhos_anomalos2'  => $dados['ds_vizinhos_anomalos'],
            ':op_evento_propagado2'   => $dados['op_evento_propagado'],
            ':id_tipo_medidor2'       => $dados['id_tipo_medidor'],
        ]);

        return $stmt->rowCount() > 0;

    } catch (PDOException $e) {
        // Ignorar erro de constraint (confianca < 0.70) ou duplicata
        if (strpos($e->getMessage(), 'CK_CONFIANCA_MINIMA') !== false) {
            return false;
        }
        throw $e;
    }
}


// ============================================================
// PROGRESSO DO BATCH EM TEMPO REAL
// ============================================================

/**
 * Retorna o progresso atual do batch em execucao.
 * Le o arquivo JSON de progresso escrito pela funcao executarBatch.
 *
 * @return array Dados de progresso ou status idle
 */
function progressoBatch(): array
{
    $progressFile = sys_get_temp_dir() . '/simp_batch_progress.json';

    if (!file_exists($progressFile)) {
        return ['success' => true, 'status' => 'idle'];
    }

    $conteudo = @file_get_contents($progressFile);
    if ($conteudo === false) {
        return ['success' => true, 'status' => 'idle'];
    }

    $dados = json_decode($conteudo, true);
    if (!$dados) {
        return ['success' => true, 'status' => 'idle'];
    }

    // Limpar arquivo se batch concluiu ha mais de 5 minutos
    if (in_array($dados['status'] ?? '', ['completed', 'error'])) {
        $fim = strtotime($dados['fim'] ?? 'now');
        if ((time() - $fim) > 300) {
            @unlink($progressFile);
            return ['success' => true, 'status' => 'idle'];
        }
    }

    return ['success' => true] + $dados;
}


// ============================================================
// STATUS DO BATCH
// ============================================================

/**
 * Retorna resumo do ultimo processamento batch para uma data.
 */
function statusBatch(PDO $pdo, string $data): array
{
    $sql = "
        SELECT 
            COUNT(*) AS TOTAL_PENDENCIAS,
            SUM(CASE WHEN ID_STATUS = 0 THEN 1 ELSE 0 END) AS PENDENTES,
            SUM(CASE WHEN ID_STATUS = 1 THEN 1 ELSE 0 END) AS APROVADAS,
            SUM(CASE WHEN ID_STATUS = 2 THEN 1 ELSE 0 END) AS AJUSTADAS,
            SUM(CASE WHEN ID_STATUS = 3 THEN 1 ELSE 0 END) AS IGNORADAS,
            SUM(CASE WHEN ID_CLASSE_ANOMALIA = 1 THEN 1 ELSE 0 END) AS CORRECAO_TECNICA,
            SUM(CASE WHEN ID_CLASSE_ANOMALIA = 2 THEN 1 ELSE 0 END) AS EVENTO_OPERACIONAL,
            COUNT(DISTINCT CD_PONTO_MEDICAO) AS PONTOS_AFETADOS,
            AVG(VL_CONFIANCA) AS CONFIANCA_MEDIA,
            MIN(DT_GERACAO) AS PRIMEIRO_REGISTRO,
            MAX(DT_GERACAO) AS ULTIMO_REGISTRO
        FROM SIMP.dbo.IA_PENDENCIA_TRATAMENTO
        WHERE DT_REFERENCIA = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$data]);
    $resumo = $stmt->fetch(PDO::FETCH_ASSOC);

    // Distribuicao por tipo
    $sqlTipos = "
        SELECT ID_TIPO_ANOMALIA, COUNT(*) AS QTD
        FROM SIMP.dbo.IA_PENDENCIA_TRATAMENTO
        WHERE DT_REFERENCIA = ?
        GROUP BY ID_TIPO_ANOMALIA
        ORDER BY QTD DESC
    ";
    $stmtTipos = $pdo->prepare($sqlTipos);
    $stmtTipos->execute([$data]);
    $tipos = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

    return [
        'success' => true,
        'data'    => $data,
        'resumo'  => $resumo,
        'distribuicao_tipos' => $tipos
    ];
}
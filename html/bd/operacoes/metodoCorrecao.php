<?php
/**
 * SIMP 2.0 - Fase A2★: Metodos de Correcao
 *
 * Calcula ate 4 metodos de correcao para anomalias detectadas,
 * retornando valores estimados hora a hora e score de aderencia
 * para que o operador escolha o melhor metodo.
 *
 * Metodos (6 unificados com operacoes.php):
 *   1. XGBoost Rede        — predicao via vizinhanca topologica (TensorFlow container)
 *   2. PCHIP               — interpolacao monotonica usando horas validas como ancoras
 *   3. Historico+Tendencia  — media historica do mesmo dia da semana ajustada pelo fator de tendencia
 *   4. Tendencia da Rede    — fator de variacao dos outros pontos da rede vs historico
 *   5. Proporcao Historica  — proporcao do ponto na rede aplicada ao total atual
 *   6. Minimos Quadrados    — regressao linear sobre semanas historicas para projetar tendencia
 * 
 * Score de aderencia (0-10):
 *   score = (0.40 * R2 + 0.30 * (1 - MAE_norm) + 0.30 * (1 - RMSE_norm)) * 10
 *   Calculado comparando estimativa com horas NAO-anomalas do mesmo dia.
 *
 * Acoes:
 *   - calcular_metodos: Retorna metodos + valores + scores para um ponto/data
 *
 * Localizacao: html/bd/operacoes/metodoCorrecao.php
 *
 * @author  Bruno - CESAN
 * @version 1.0 - Fase A2★
 * @date    2026-02
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

/**
 * Retorna JSON limpo e encerra execucao.
 */
function retornarJSON_MC($data)
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
            'error' => 'Erro PHP fatal: ' . $error['message']
        ], JSON_UNESCAPED_UNICODE);
    }
});

try {
    // ========================================
    // Conexao e configuracao
    // ========================================
    @include_once __DIR__ . '/../conexao.php';

    if (!isset($pdoSIMP)) {
        retornarJSON_MC(['success' => false, 'error' => 'Conexao com banco nao estabelecida']);
    }

    // URL do container TensorFlow
    $tensorflowUrl = getenv('TENSORFLOW_URL') ?: 'http://simp20-tensorflow:5000';

    // Receber dados
    $rawInput = file_get_contents('php://input');
    $dados = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $dados = $_GET;
    }

    $acao = $dados['acao'] ?? $_GET['acao'] ?? '';

    if (empty($acao)) {
        retornarJSON_MC([
            'success' => false,
            'error' => 'Parametro "acao" obrigatorio. Valores: calcular_metodos'
        ]);
    }

    // ========================================
    // Roteamento
    // ========================================
    switch ($acao) {

        case 'calcular_metodos':
            $cdPonto = intval($dados['cd_ponto'] ?? 0);
            $dtReferencia = $dados['dt_referencia'] ?? '';
            $tipoMedidor = intval($dados['tipo_medidor'] ?? 1);
            $horasAnomalas = $dados['horas_anomalas'] ?? []; // Array de horas [0-23] que sao anomalas

            if ($cdPonto <= 0 || empty($dtReferencia)) {
                retornarJSON_MC(['success' => false, 'error' => 'cd_ponto e dt_referencia sao obrigatorios']);
            }

            $resultado = calcularMetodosCorrecao(
                $pdoSIMP,
                $tensorflowUrl,
                $cdPonto,
                $dtReferencia,
                $tipoMedidor,
                $horasAnomalas
            );
            retornarJSON_MC($resultado);
            break;

        default:
            retornarJSON_MC(['success' => false, 'error' => "Acao desconhecida: $acao"]);
    }

} catch (Exception $e) {
    retornarJSON_MC(['success' => false, 'error' => $e->getMessage()]);
}


// ============================================================
// FUNCAO PRINCIPAL: CALCULAR METODOS DE CORRECAO
// ============================================================

/**
 * Orquestra o calculo dos 4 metodos de correcao e retorna
 * valores estimados + score de aderencia para cada um.
 *
 * @param PDO    $pdo             Conexao PDO
 * @param string $tfUrl           URL do TensorFlow container
 * @param int    $cdPonto         Codigo do ponto de medicao
 * @param string $dtReferencia    Data no formato YYYY-MM-DD
 * @param int    $tipoMedidor     Tipo do medidor (1,2,4,6,8)
 * @param array  $horasAnomalas   Horas anomalas (ex: [1,2,3])
 * @return array                  Resultado com metodos, scores, valores reais
 */
function calcularMetodosCorrecao(
    PDO $pdo,
    string $tfUrl,
    int $cdPonto,
    string $dtReferencia,
    int $tipoMedidor,
    array $horasAnomalas
): array {

    $inicio = microtime(true);

    // ========================================
    // 1. Buscar valores reais do dia (hora a hora)
    // ========================================
    $valoresReais = buscarValoresHorarios($pdo, $cdPonto, $dtReferencia, $tipoMedidor);

    // Se nao foram informadas horas anomalas, inferir da tabela de pendencias
    if (empty($horasAnomalas)) {
        $horasAnomalas = buscarHorasAnomalas($pdo, $cdPonto, $dtReferencia);
    }

    // Horas validas = horas que NAO sao anomalas (para calcular aderencia)
    $horasValidas = [];
    for ($h = 0; $h < 24; $h++) {
        if (!in_array($h, $horasAnomalas) && isset($valoresReais[$h]) && $valoresReais[$h] !== null) {
            $horasValidas[$h] = $valoresReais[$h];
        }
    }

    // ========================================
    // 2. Calcular cada metodo em paralelo (sequencial no PHP)
    // ========================================
    $metodos = [];

    // --- 2a. XGBoost Rede (via TensorFlow container) ---
    $xgboost = calcularXGBoostRede($tfUrl, $cdPonto, $dtReferencia, $tipoMedidor);
    if ($xgboost !== null) {
        $score = calcularScoreAderencia($xgboost, $horasValidas);
        $metodos[] = [
            'id' => 'xgboost_rede',
            'nome' => 'XGBoost Rede',
            'icone' => 'hardware-chip-outline',
            'cor' => '#3b82f6',
            'valores' => $xgboost,
            'score_aderencia' => $score['score'],
            'metricas' => $score['metricas'],
            'descricao' => 'Predicao multivariada usando vizinhanca topologica do grafo hidraulico'
        ];
    }

    // --- 2b. PCHIP (interpolacao monotonica, PHP puro) ---
    // --- 2b. PCHIP (interpolacao monotonica, PHP puro) ---
    $pchip = calcularPCHIP($valoresReais, $horasAnomalas);
    if ($pchip !== null) {
        // PCHIP passa pelas ancoras (R2=1 artificial) — usar LOO-CV
        if (count($horasValidas) >= 4) {
            $scoreLOO = calcularScorePCHIP_LOO($valoresReais, array_keys($horasValidas));
            $score = [
                'score' => $scoreLOO['score'],
                'metricas' => [
                    'r2' => $scoreLOO['r2'],
                    'mae' => $scoreLOO['mae'],
                    'rmse' => $scoreLOO['rmse'],
                    'mae_norm' => $scoreLOO['mae_norm'],
                    'rmse_norm' => $scoreLOO['rmse_norm'],
                    'amostras' => $scoreLOO['amostras']
                ]
            ];
        } else {
            $score = calcularScoreAderencia($pchip, $horasValidas);
        }
        $metodos[] = [
            'id' => 'pchip',
            'nome' => 'PCHIP',
            'icone' => 'analytics-outline',
            'cor' => '#f59e0b',
            'valores' => $pchip,
            'score_aderencia' => $score['score'],
            'metricas' => $score['metricas'],
            'descricao' => 'Interpolacao monotonica por partes usando horas validas como ancoras'
        ];
    }

    // --- 2c. Historico + Tendencia (media historica × fator tendencia) ---
    $historicoTend = calcularHistoricoTendencia($pdo, $cdPonto, $dtReferencia, $tipoMedidor);
    if ($historicoTend !== null) {
        $score = calcularScoreAderencia($historicoTend, $horasValidas);
        $metodos[] = [
            'id' => 'historico_tendencia',
            'nome' => 'Hist. + Tendencia',
            'icone' => 'analytics-outline',
            'cor' => '#16a34a',
            'valores' => $historicoTend,
            'score_aderencia' => $score['score'],
            'metricas' => $score['metricas'],
            'descricao' => 'Media historica do mesmo dia da semana ajustada pelo fator de tendencia'
        ];
    }

    // --- 2d. Tendencia da Rede (fator de variacao dos vizinhos) ---
    $tendRede = calcularTendenciaRedeMC($pdo, $cdPonto, $dtReferencia, $tipoMedidor);
    if ($tendRede !== null) {
        $score = calcularScoreAderencia($tendRede, $horasValidas);
        $metodos[] = [
            'id' => 'tendencia_rede',
            'nome' => 'Tendencia Rede',
            'icone' => 'git-network-outline',
            'cor' => '#14b8a6',
            'valores' => $tendRede,
            'score_aderencia' => $score['score'],
            'metricas' => $score['metricas'],
            'descricao' => 'Analisa variacao dos outros pontos da rede e aplica o fator no ponto atual'
        ];
    }

    // --- 2e. Proporcao Historica (participacao do ponto na rede) ---
    $proporcao = calcularProporcaoHistoricaMC($pdo, $cdPonto, $dtReferencia, $tipoMedidor);
    if ($proporcao !== null) {
        $score = calcularScoreAderencia($proporcao, $horasValidas);
        $metodos[] = [
            'id' => 'proporcao',
            'nome' => 'Proporcao Hist.',
            'icone' => 'pie-chart-outline',
            'cor' => '#d946ef',
            'valores' => $proporcao,
            'score_aderencia' => $score['score'],
            'metricas' => $score['metricas'],
            'descricao' => 'Proporcao historica do ponto na rede aplicada ao total da rede hoje'
        ];
    }

    // --- 2f. Minimos Quadrados (regressao linear temporal) ---
    $minQuad = calcularMinimosQuadradosMC($pdo, $cdPonto, $dtReferencia, $tipoMedidor);
    if ($minQuad !== null) {
        $score = calcularScoreAderencia($minQuad, $horasValidas);
        $metodos[] = [
            'id' => 'minimos_quadrados',
            'nome' => 'Min. Quadrados',
            'icone' => 'trending-up-outline',
            'cor' => '#f97316',
            'valores' => $minQuad,
            'score_aderencia' => $score['score'],
            'metricas' => $score['metricas'],
            'descricao' => 'Regressao linear sobre semanas historicas para projetar tendencia'
        ];
    }

    // ========================================
    // 3. Ordenar por score (melhor primeiro)
    // ========================================
    usort($metodos, function ($a, $b) {
        return $b['score_aderencia'] <=> $a['score_aderencia'];
    });

    // Metodo recomendado = o de maior score
    $metodoRecomendado = !empty($metodos) ? $metodos[0]['id'] : null;

    return [
        'success' => true,
        'metodos' => $metodos,
        'valores_reais' => $valoresReais,
        'horas_anomalas' => array_values(array_map('intval', $horasAnomalas)),
        'horas_validas' => array_keys($horasValidas),
        'metodo_recomendado' => $metodoRecomendado,
        'total_metodos' => count($metodos),
        'tempo_ms' => round((microtime(true) - $inicio) * 1000)
    ];
}


// ============================================================
// METODO 1: XGBOOST REDE (via TensorFlow)
// ============================================================

/**
 * Chama /api/predict no container TensorFlow para obter
 * predicoes XGBoost hora a hora (0-23).
 *
 * @param string $tfUrl        URL do container
 * @param int    $cdPonto      Codigo do ponto
 * @param string $data         Data YYYY-MM-DD
 * @param int    $tipoMedidor  Tipo do medidor
 * @return array|null          Mapa hora => valor ou null se falhar
 */
function calcularXGBoostRede(string $tfUrl, int $cdPonto, string $data, int $tipoMedidor): ?array
{
    $resp = chamarTensorFlow($tfUrl . '/api/predict', [
        'cd_ponto' => $cdPonto,
        'data' => $data,
        'tipo_medidor' => $tipoMedidor,
        'horas' => range(0, 23)
    ]);

    if (!($resp['success'] ?? false) || empty($resp['predicoes'])) {
        return null;
    }

    $valores = [];
    foreach ($resp['predicoes'] as $pred) {
        $h = intval($pred['hora'] ?? -1);
        if ($h >= 0 && $h <= 23) {
            $valores[$h] = round(floatval($pred['valor_predito'] ?? 0), 4);
        }
    }

    // Precisa ter pelo menos 12 horas para ser valido
    return count($valores) >= 12 ? $valores : null;
}


// ============================================================
// METODO 2: PCHIP (Interpolacao Monotonica)
// ============================================================

/**
 * Piecewise Cubic Hermite Interpolating Polynomial.
 * Usa as horas validas (nao-anomalas) como pontos de ancoragem
 * e interpola os valores nas horas anomalas.
 *
 * Preserva monotonicidade — nao gera overshoots como spline cubica.
 *
 * @param array $valoresReais   Mapa hora => valor (incluindo anomalas)
 * @param array $horasAnomalas  Lista de horas anomalas
 * @return array|null           Mapa hora => valor interpolado para todas as 24h
 */
function calcularPCHIP(array $valoresReais, array $horasAnomalas): ?array
{
    // Separar ancoras (horas validas com valor)
    $ancorasX = []; // Horas
    $ancorasY = []; // Valores

    for ($h = 0; $h < 24; $h++) {
        if (!in_array($h, $horasAnomalas) && isset($valoresReais[$h]) && $valoresReais[$h] !== null) {
            $ancorasX[] = $h;
            $ancorasY[] = floatval($valoresReais[$h]);
        }
    }

    // Precisa de pelo menos 3 ancoras para interpolacao cubica
    if (count($ancorasX) < 3) {
        return null;
    }

    $n = count($ancorasX);

    // Calcular derivadas monotônicas (metodo Fritsch-Carlson)
    $derivadas = calcularDerivadasMonotonicas($ancorasX, $ancorasY);

    // Interpolar todas as 24 horas
    $valores = [];
    for ($h = 0; $h < 24; $h++) {
        if (!in_array($h, $horasAnomalas) && isset($valoresReais[$h]) && $valoresReais[$h] !== null) {
            // Hora valida: manter valor original
            $valores[$h] = round(floatval($valoresReais[$h]), 4);
        } else {
            // Hora anomala: interpolar via PCHIP
            $valores[$h] = round(interpolarPCHIP($h, $ancorasX, $ancorasY, $derivadas), 4);
        }
    }

    return $valores;
}

/**
 * Calcula derivadas monotônicas pelo metodo Fritsch-Carlson.
 * Garante que a interpolacao nao gera overshoots.
 *
 * @param array $x  Pontos X (horas)
 * @param array $y  Pontos Y (valores)
 * @return array    Derivadas em cada ponto
 */
function calcularDerivadasMonotonicas(array $x, array $y): array
{
    $n = count($x);
    $d = array_fill(0, $n, 0.0);

    if ($n < 2)
        return $d;

    // Diferencas divididas
    $delta = [];
    for ($i = 0; $i < $n - 1; $i++) {
        $dx = $x[$i + 1] - $x[$i];
        $delta[$i] = ($dx > 0) ? ($y[$i + 1] - $y[$i]) / $dx : 0.0;
    }

    if ($n === 2) {
        $d[0] = $delta[0];
        $d[1] = $delta[0];
        return $d;
    }

    // Derivadas iniciais (media harmonica)
    $d[0] = $delta[0];
    $d[$n - 1] = $delta[$n - 2];

    for ($i = 1; $i < $n - 1; $i++) {
        if ($delta[$i - 1] * $delta[$i] > 0) {
            // Mesma direcao: media harmonica
            $d[$i] = 2.0 * $delta[$i - 1] * $delta[$i] / ($delta[$i - 1] + $delta[$i]);
        } else {
            // Direcoes opostas ou zero: derivada zero (monotonica)
            $d[$i] = 0.0;
        }
    }

    // Correcao Fritsch-Carlson: limitar derivadas para preservar monotonicidade
    for ($i = 0; $i < $n - 1; $i++) {
        if (abs($delta[$i]) < 1e-10) {
            // Segmento constante: derivadas nos endpoints devem ser zero
            $d[$i] = 0.0;
            $d[$i + 1] = 0.0;
        } else {
            $alpha = $d[$i] / $delta[$i];
            $beta = $d[$i + 1] / $delta[$i];

            // Condicao de monotonicidade: alpha^2 + beta^2 <= 9
            $soma = $alpha * $alpha + $beta * $beta;
            if ($soma > 9.0) {
                $tau = 3.0 / sqrt($soma);
                $d[$i] = $tau * $alpha * $delta[$i];
                $d[$i + 1] = $tau * $beta * $delta[$i];
            }
        }
    }

    return $d;
}

/**
 * Interpola um valor em X usando PCHIP (Hermite cubico).
 *
 * @param float $xp          Ponto X a interpolar (hora)
 * @param array $x           Ancoras X
 * @param array $y           Ancoras Y
 * @param array $derivadas   Derivadas em cada ancora
 * @return float             Valor interpolado
 */
function interpolarPCHIP(float $xp, array $x, array $y, array $derivadas): float
{
    $n = count($x);

    // Clamping: se fora do intervalo, usar valor da borda
    if ($xp <= $x[0])
        return $y[0];
    if ($xp >= $x[$n - 1])
        return $y[$n - 1];

    // Encontrar intervalo [x[i], x[i+1]] que contem xp
    $i = 0;
    for ($k = 0; $k < $n - 1; $k++) {
        if ($xp >= $x[$k] && $xp <= $x[$k + 1]) {
            $i = $k;
            break;
        }
    }

    // Interpolacao Hermite cubica
    $h = $x[$i + 1] - $x[$i];
    if ($h <= 0)
        return $y[$i];

    $t = ($xp - $x[$i]) / $h;
    $t2 = $t * $t;
    $t3 = $t2 * $t;

    // Funcoes base de Hermite
    $h00 = 2 * $t3 - 3 * $t2 + 1;
    $h10 = $t3 - 2 * $t2 + $t;
    $h01 = -2 * $t3 + 3 * $t2;
    $h11 = $t3 - $t2;

    return $h00 * $y[$i] + $h10 * $h * $derivadas[$i]
        + $h01 * $y[$i + 1] + $h11 * $h * $derivadas[$i + 1];
}


// ============================================================
// METODO 3: HISTORICO + TENDENCIA
// ============================================================

/**
 * Calcula media historica do mesmo dia da semana (ultimas 4-8 semanas)
 * ajustada pelo fator de tendencia recente.
 *
 * Fator de tendencia = media dos ultimos 3 dias / media historica geral.
 * Valor estimado = media_historica_hora × fator_tendencia.
 *
 * @param PDO    $pdo           Conexao com banco
 * @param int    $cdPonto       Codigo do ponto
 * @param string $dtReferencia  Data YYYY-MM-DD
 * @param int    $tipoMedidor   Tipo do medidor
 * @return array|null           Mapa hora => valor ou null
 */
function calcularHistoricoTendencia(PDO $pdo, int $cdPonto, string $dtReferencia, int $tipoMedidor): ?array
{
    $colunasPorTipo = [
        1 => 'VL_VAZAO_EFETIVA',
        2 => 'VL_VAZAO_EFETIVA',
        4 => 'VL_PRESSAO',
        6 => 'VL_RESERVATORIO',
        8 => 'VL_VAZAO_EFETIVA'
    ];
    $coluna = $colunasPorTipo[$tipoMedidor] ?? 'VL_VAZAO_EFETIVA';

    // Buscar ultimas 8 semanas, mesmo dia da semana
    $datasHistoricas = [];
    for ($s = 1; $s <= 8; $s++) {
        $datasHistoricas[] = date('Y-m-d', strtotime($dtReferencia . " -{$s} weeks"));
    }

    // Coletar medias por hora das semanas historicas
    $somaPorHora = array_fill(0, 24, 0.0);
    $contPorHora = array_fill(0, 24, 0);

    foreach ($datasHistoricas as $dataHist) {
        $dados = buscarValoresHorarios($pdo, $cdPonto, $dataHist, $tipoMedidor);
        for ($h = 0; $h < 24; $h++) {
            if (isset($dados[$h]) && $dados[$h] !== null && $dados[$h] > 0) {
                $somaPorHora[$h] += $dados[$h];
                $contPorHora[$h]++;
            }
        }
    }

    // Calcular media historica por hora (minimo 4 semanas com dados)
    $mediaHistorica = array_fill(0, 24, null);
    $temDados = false;
    for ($h = 0; $h < 24; $h++) {
        if ($contPorHora[$h] >= 4) {
            $mediaHistorica[$h] = $somaPorHora[$h] / $contPorHora[$h];
            $temDados = true;
        }
    }

    if (!$temDados)
        return null;

    // Calcular fator de tendencia dos ultimos 3 dias
    $somaRecente = 0;
    $contRecente = 0;
    $somaHistGeral = 0;
    $contHistGeral = 0;

    for ($d = 1; $d <= 3; $d++) {
        $dataRecente = date('Y-m-d', strtotime($dtReferencia . " -{$d} days"));
        $dadosRecentes = buscarValoresHorarios($pdo, $cdPonto, $dataRecente, $tipoMedidor);
        for ($h = 0; $h < 24; $h++) {
            if (isset($dadosRecentes[$h]) && $dadosRecentes[$h] !== null && $dadosRecentes[$h] > 0) {
                $somaRecente += $dadosRecentes[$h];
                $contRecente++;
            }
            if ($mediaHistorica[$h] !== null) {
                $somaHistGeral += $mediaHistorica[$h];
                $contHistGeral++;
            }
        }
    }

    // Fator de tendencia (1.0 = sem tendencia)
    $fatorTendencia = 1.0;
    if ($contRecente > 0 && $contHistGeral > 0) {
        $mediaRecente = $somaRecente / $contRecente;
        $mediaHistGeral = $somaHistGeral / $contHistGeral;
        if ($mediaHistGeral > 0) {
            $fator = $mediaRecente / $mediaHistGeral;
            // Limitar fator entre 0.5 e 2.0
            $fatorTendencia = max(0.5, min(2.0, $fator));
        }
    }

    // Aplicar fator de tendencia na media historica
    $resultado = [];
    for ($h = 0; $h < 24; $h++) {
        if ($mediaHistorica[$h] !== null) {
            $resultado[$h] = round($mediaHistorica[$h] * $fatorTendencia, 4);
        } else {
            $resultado[$h] = null;
        }
    }

    return $resultado;
}

// ============================================================
// METODO 4: TENDENCIA DA REDE
// ============================================================

/**
 * Analisa variacao dos outros pontos da rede vs historico e aplica o fator.
 * Reutiliza a logica de getEstimativasRede.php adaptada para metodoCorrecao.
 *
 * @param PDO    $pdo           Conexao
 * @param int    $cdPonto       Codigo do ponto
 * @param string $dtReferencia  Data
 * @param int    $tipoMedidor   Tipo do medidor
 * @return array|null           Mapa hora => valor ou null
 */
function calcularTendenciaRedeMC(PDO $pdo, int $cdPonto, string $dtReferencia, int $tipoMedidor): ?array
{
    $colunasPorTipo = [
        1 => 'VL_VAZAO_EFETIVA',
        2 => 'VL_VAZAO_EFETIVA',
        4 => 'VL_PRESSAO',
        6 => 'VL_RESERVATORIO',
        8 => 'VL_VAZAO_EFETIVA'
    ];
    $coluna = $colunasPorTipo[$tipoMedidor] ?? 'VL_VAZAO_EFETIVA';

    // Buscar pontos da mesma rede (ENTIDADE_VALOR_ID)
    $pontosRede = buscarPontosRedeMC($pdo, $cdPonto, $dtReferencia);
    if (count($pontosRede) <= 1)
        return null;

    // Obter dados de hoje de todos os pontos
    $dadosTodosPontos = [];
    foreach ($pontosRede as $cdP => $info) {
        $colunaP = $colunasPorTipo[$info['tipo_medidor']] ?? 'VL_VAZAO_EFETIVA';
        $dadosTodosPontos[$cdP] = buscarValoresHorarios($pdo, $cdP, $dtReferencia, $info['tipo_medidor']);
    }

    // Historico: ultimas 4 semanas, mesmo dia da semana
    $datasHist = [];
    for ($s = 1; $s <= 4; $s++) {
        $datasHist[] = date('Y-m-d', strtotime($dtReferencia . " -{$s} weeks"));
    }

    // Calcular media historica de cada ponto por hora
    $mediaHistorica = [];
    foreach ($pontosRede as $cdP => $info) {
        $mediaHistorica[$cdP] = array_fill(0, 24, null);
        $colunaP = $colunasPorTipo[$info['tipo_medidor']] ?? 'VL_VAZAO_EFETIVA';
        $soma = array_fill(0, 24, 0.0);
        $cont = array_fill(0, 24, 0);

        foreach ($datasHist as $dataHist) {
            $dadosH = buscarValoresHorarios($pdo, $cdP, $dataHist, $info['tipo_medidor']);
            for ($h = 0; $h < 24; $h++) {
                if (isset($dadosH[$h]) && $dadosH[$h] !== null && $dadosH[$h] > 0) {
                    $soma[$h] += $dadosH[$h];
                    $cont[$h]++;
                }
            }
        }
        for ($h = 0; $h < 24; $h++) {
            if ($cont[$h] >= 2) {
                $mediaHistorica[$cdP][$h] = $soma[$h] / $cont[$h];
            }
        }
    }

    // Para cada hora: calcular fator de variacao da rede e aplicar
    $resultado = array_fill(0, 24, null);
    for ($h = 0; $h < 24; $h++) {
        $mediaAtualHist = $mediaHistorica[$cdPonto][$h] ?? null;
        if ($mediaAtualHist === null || $mediaAtualHist <= 0)
            continue;

        $fatores = [];
        foreach ($pontosRede as $cdP => $info) {
            if ($cdP === $cdPonto)
                continue;
            $valorHoje = $dadosTodosPontos[$cdP][$h] ?? null;
            $mediaHist = $mediaHistorica[$cdP][$h] ?? null;
            if ($valorHoje === null || $valorHoje <= 0 || $mediaHist === null || $mediaHist <= 0)
                continue;
            $fator = $valorHoje / $mediaHist;
            if ($fator >= 0.3 && $fator <= 3.0)
                $fatores[] = $fator;
        }

        if (empty($fatores))
            continue;

        // Mediana para robustez
        sort($fatores);
        $n = count($fatores);
        $fatorMedio = ($n % 2 === 0)
            ? ($fatores[$n / 2 - 1] + $fatores[$n / 2]) / 2
            : $fatores[floor($n / 2)];

        $valorEstimado = $mediaAtualHist * $fatorMedio;
        if ($valorEstimado > 0)
            $resultado[$h] = round($valorEstimado, 4);
    }

    // Verificar se tem pelo menos 12 horas com estimativa
    $horasEstimadas = count(array_filter($resultado, fn($v) => $v !== null));
    return $horasEstimadas >= 6 ? $resultado : null;
}

// ============================================================
// METODO 5: PROPORCAO HISTORICA
// ============================================================

/**
 * Calcula proporcao historica do ponto na rede e aplica ao total atual.
 *
 * @param PDO    $pdo           Conexao
 * @param int    $cdPonto       Codigo do ponto
 * @param string $dtReferencia  Data
 * @param int    $tipoMedidor   Tipo do medidor
 * @return array|null           Mapa hora => valor ou null
 */
function calcularProporcaoHistoricaMC(PDO $pdo, int $cdPonto, string $dtReferencia, int $tipoMedidor): ?array
{
    $colunasPorTipo = [
        1 => 'VL_VAZAO_EFETIVA',
        2 => 'VL_VAZAO_EFETIVA',
        4 => 'VL_PRESSAO',
        6 => 'VL_RESERVATORIO',
        8 => 'VL_VAZAO_EFETIVA'
    ];

    $pontosRede = buscarPontosRedeMC($pdo, $cdPonto, $dtReferencia);
    if (count($pontosRede) <= 1)
        return null;

    // Historico: ultimas 4 semanas
    $datasHist = [];
    for ($s = 1; $s <= 4; $s++) {
        $datasHist[] = date('Y-m-d', strtotime($dtReferencia . " -{$s} weeks"));
    }

    // Calcular proporcao media por hora: valor_ponto / total_rede
    $proporcoesPorHora = array_fill(0, 24, []);

    foreach ($datasHist as $dataHist) {
        // Buscar dados de todos os pontos nessa data
        $dadosDia = [];
        foreach ($pontosRede as $cdP => $info) {
            $dadosDia[$cdP] = buscarValoresHorarios($pdo, $cdP, $dataHist, $info['tipo_medidor']);
        }

        for ($h = 0; $h < 24; $h++) {
            $totalRede = 0;
            $valorPonto = null;
            foreach ($pontosRede as $cdP => $info) {
                $v = $dadosDia[$cdP][$h] ?? null;
                if ($v !== null && $v > 0) {
                    $totalRede += $v;
                    if ($cdP === $cdPonto)
                        $valorPonto = $v;
                }
            }
            if ($totalRede > 0 && $valorPonto !== null && $valorPonto > 0) {
                $proporcoesPorHora[$h][] = $valorPonto / $totalRede;
            }
        }
    }

    // Dados de hoje: total da rede
    $dadosHoje = [];
    foreach ($pontosRede as $cdP => $info) {
        $dadosHoje[$cdP] = buscarValoresHorarios($pdo, $cdP, $dtReferencia, $info['tipo_medidor']);
    }

    $resultado = array_fill(0, 24, null);
    for ($h = 0; $h < 24; $h++) {
        if (count($proporcoesPorHora[$h]) < 2)
            continue;

        // Media das proporcoes historicas
        $propMedia = array_sum($proporcoesPorHora[$h]) / count($proporcoesPorHora[$h]);

        // Total da rede hoje (excluindo o ponto atual)
        $totalHoje = 0;
        foreach ($pontosRede as $cdP => $info) {
            if ($cdP === $cdPonto)
                continue;
            $v = $dadosHoje[$cdP][$h] ?? null;
            if ($v !== null && $v > 0)
                $totalHoje += $v;
        }

        if ($totalHoje > 0 && $propMedia > 0 && $propMedia < 1) {
            // Estimar: se ponto = prop% do total, entao:
            // total_com_ponto = total_sem_ponto / (1 - prop)
            // valor_ponto = total_com_ponto × prop
            $totalComPonto = $totalHoje / (1 - $propMedia);
            $valorEstimado = $totalComPonto * $propMedia;
            if ($valorEstimado > 0)
                $resultado[$h] = round($valorEstimado, 4);
        }
    }

    $horasEstimadas = count(array_filter($resultado, fn($v) => $v !== null));
    return $horasEstimadas >= 6 ? $resultado : null;
}


// ============================================================
// METODO 6: MINIMOS QUADRADOS (regressao linear temporal)
// ============================================================

/**
 * Regressao linear sobre 6-8 semanas historicas (mesmo dia da semana)
 * para projetar tendencia. Captura drift lento (desgaste, demanda sazonal).
 *
 * @param PDO    $pdo           Conexao
 * @param int    $cdPonto       Codigo do ponto
 * @param string $dtReferencia  Data
 * @param int    $tipoMedidor   Tipo do medidor
 * @return array|null           Mapa hora => valor ou null
 */
function calcularMinimosQuadradosMC(PDO $pdo, int $cdPonto, string $dtReferencia, int $tipoMedidor): ?array
{
    $NUM_SEMANAS = 8;
    $MIN_SEMANAS = 3;

    // Datas historicas (mesmo dia da semana)
    $datasHistoricas = [];
    for ($s = 1; $s <= $NUM_SEMANAS; $s++) {
        $datasHistoricas[] = date('Y-m-d', strtotime($dtReferencia . " -{$s} weeks"));
    }
    $xHoje = count($datasHistoricas);

    // Coletar dados por hora
    $dadosPorHora = array_fill(0, 24, []);
    foreach ($datasHistoricas as $x => $dataHist) {
        $dados = buscarValoresHorarios($pdo, $cdPonto, $dataHist, $tipoMedidor);
        for ($h = 0; $h < 24; $h++) {
            if (isset($dados[$h]) && $dados[$h] !== null && $dados[$h] > 0) {
                $dadosPorHora[$h][] = ['x' => $x, 'y' => $dados[$h]];
            }
        }
    }

    $resultado = array_fill(0, 24, null);
    for ($h = 0; $h < 24; $h++) {
        $pontos = $dadosPorHora[$h];
        $n = count($pontos);
        if ($n < $MIN_SEMANAS)
            continue;

        $somaX = 0;
        $somaY = 0;
        foreach ($pontos as $p) {
            $somaX += $p['x'];
            $somaY += $p['y'];
        }
        $mediaX = $somaX / $n;
        $mediaY = $somaY / $n;

        $num = 0;
        $den = 0;
        foreach ($pontos as $p) {
            $dx = $p['x'] - $mediaX;
            $num += $dx * ($p['y'] - $mediaY);
            $den += $dx * $dx;
        }

        if (abs($den) < 0.0001) {
            $resultado[$h] = round($mediaY, 4);
            continue;
        }

        $b = $num / $den;
        $a = $mediaY - $b * $mediaX;
        $valorProjetado = $a + $b * $xHoje;

        // Validacoes de sanidade
        if ($valorProjetado <= 0) {
            $minHist = min(array_column($pontos, 'y'));
            $valorProjetado = $minHist * 0.8;
            if ($valorProjetado <= 0)
                continue;
        }

        // Limitar desvio a 50% da media
        $desvioPerc = abs($valorProjetado - $mediaY) / $mediaY;
        if ($desvioPerc > 0.5) {
            $valorProjetado = $mediaY * (1 + 0.5 * ($valorProjetado > $mediaY ? 1 : -1));
        }

        $resultado[$h] = round($valorProjetado, 4);
    }

    $horasEstimadas = count(array_filter($resultado, fn($v) => $v !== null));
    return $horasEstimadas >= 6 ? $resultado : null;
}

// ============================================================
// AUXILIAR: Buscar pontos da mesma rede (para metodoCorrecao)
// ============================================================

/**
 * Busca todos os pontos que pertencem a mesma rede (ENTIDADE_VALOR_ID)
 * do ponto informado. Retorna mapa cd_ponto => info.
 *
 * @param PDO    $pdo      Conexao
 * @param int    $cdPonto  Codigo do ponto
 * @param string $data     Data de referencia
 * @return array           Mapa cd_ponto => ['tipo_medidor' => int, ...]
 */
function buscarPontosRedeMC(PDO $pdo, int $cdPonto, string $data): array
{
    // Buscar ENTIDADE_VALOR_ID do ponto
    $sql = "SELECT DISTINCT EV.CD_ENTIDADE_VALOR_ID
            FROM SIMP.dbo.ENTIDADE_VALOR_ITEM EVI
            INNER JOIN SIMP.dbo.ENTIDADE_VALOR EV ON EV.CD_CHAVE = EVI.CD_ENTIDADE_VALOR
            WHERE EVI.CD_PONTO_MEDICAO = :cdPonto
              AND (EVI.DT_INICIO IS NULL OR EVI.DT_INICIO <= :data1)
              AND (EVI.DT_FIM IS NULL OR EVI.DT_FIM >= :data2)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cdPonto' => $cdPonto, ':data1' => $data, ':data2' => $data]);
    $entIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($entIds)) {
        // Retornar pelo menos o proprio ponto
        $sqlPonto = "SELECT ID_TIPO_MEDIDOR FROM SIMP.dbo.PONTO_MEDICAO WHERE CD_PONTO_MEDICAO = :cd";
        $stmtP = $pdo->prepare($sqlPonto);
        $stmtP->execute([':cd' => $cdPonto]);
        $rowP = $stmtP->fetch(PDO::FETCH_ASSOC);
        return [$cdPonto => ['tipo_medidor' => (int) ($rowP['ID_TIPO_MEDIDOR'] ?? 1)]];
    }

    // Montar placeholders nomeados para o IN (:ent0, :ent1, :ent2...)
    $params = [];
    $placeholders = [];
    foreach ($entIds as $idx => $entId) {
        $key = ':ent' . $idx;
        $placeholders[] = $key;
        $params[$key] = $entId;
    }
    $inClause = implode(',', $placeholders);

    // Adicionar parametros de data
    $params[':dataIni'] = $data;
    $params[':dataFim'] = $data;

    $sql2 = "SELECT DISTINCT EVI.CD_PONTO_MEDICAO, PM.ID_TIPO_MEDIDOR
             FROM SIMP.dbo.ENTIDADE_VALOR_ITEM EVI
             INNER JOIN SIMP.dbo.ENTIDADE_VALOR EV ON EV.CD_CHAVE = EVI.CD_ENTIDADE_VALOR
             INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = EVI.CD_PONTO_MEDICAO
             WHERE EV.CD_ENTIDADE_VALOR_ID IN ($inClause)
               AND (EVI.DT_INICIO IS NULL OR EVI.DT_INICIO <= :dataIni)
               AND (EVI.DT_FIM IS NULL OR EVI.DT_FIM >= :dataFim)
               AND (PM.DT_DESATIVACAO IS NULL OR PM.DT_DESATIVACAO > GETDATE())";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute($params);

    $resultado = [];
    while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        $resultado[(int) $row['CD_PONTO_MEDICAO']] = [
            'tipo_medidor' => (int) $row['ID_TIPO_MEDIDOR']
        ];
    }

    return $resultado;
}


// ============================================================
// SCORE DE ADERENCIA
// ============================================================

/**
 * Calcula score de aderencia (0-10) comparando valores estimados
 * com os valores reais das horas NAO-anomalas.
 *
 * Formula: score = (0.40 * R2 + 0.30 * (1 - MAE_norm) + 0.30 * (1 - RMSE_norm)) * 10
 *
 * @param array $estimados    Mapa hora => valor estimado
 * @param array $horasValidas Mapa hora => valor real (apenas horas nao-anomalas)
 * @return array              {score, metricas: {r2, mae, rmse, mae_norm, rmse_norm}}
 */
function calcularScoreAderencia(array $estimados, array $horasValidas): array
{
    // Pares (real, estimado) para horas onde ambos existem
    $reais = [];
    $ests = [];

    foreach ($horasValidas as $hora => $valorReal) {
        if (isset($estimados[$hora]) && $estimados[$hora] !== null) {
            $reais[] = floatval($valorReal);
            $ests[] = floatval($estimados[$hora]);
        }
    }

    $n = count($reais);

    // Score default (se nao houver dados suficientes para comparar)
    if ($n < 3) {
        return [
            'score' => 5.0,
            'metricas' => ['r2' => null, 'mae' => null, 'rmse' => null, 'amostras' => $n]
        ];
    }

    // --- MAE ---
    $somaAbs = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $somaAbs += abs($reais[$i] - $ests[$i]);
    }
    $mae = $somaAbs / $n;

    // --- RMSE ---
    $somaQuad = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $diff = $reais[$i] - $ests[$i];
        $somaQuad += $diff * $diff;
    }
    $rmse = sqrt($somaQuad / $n);

    // --- R² ---
    $mediaReal = array_sum($reais) / $n;
    $ssTot = 0.0;
    $ssRes = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $ssTot += ($reais[$i] - $mediaReal) * ($reais[$i] - $mediaReal);
        $ssRes += ($reais[$i] - $ests[$i]) * ($reais[$i] - $ests[$i]);
    }
    $r2 = ($ssTot > 0) ? (1.0 - $ssRes / $ssTot) : 0.0;
    $r2 = max(0.0, $r2); // Clampar para nao ficar negativo

    // --- Normalizar MAE e RMSE ---
    // Normalizar pelo range dos valores reais
    $rangeReal = max($reais) - min($reais);
    if ($rangeReal < 1e-6) {
        // Valores praticamente constantes: normalizar pela media
        $rangeReal = abs($mediaReal) > 0 ? abs($mediaReal) : 1.0;
    }
    $maeNorm = min(1.0, $mae / $rangeReal);
    $rmseNorm = min(1.0, $rmse / $rangeReal);

    // --- Score composto (0-10) ---
    $score = (0.40 * $r2 + 0.30 * (1.0 - $maeNorm) + 0.30 * (1.0 - $rmseNorm)) * 10.0;
    $score = round(max(0.0, min(10.0, $score)), 2);

    return [
        'score' => $score,
        'metricas' => [
            'r2' => round($r2, 4),
            'mae' => round($mae, 4),
            'rmse' => round($rmse, 4),
            'mae_norm' => round($maeNorm, 4),
            'rmse_norm' => round($rmseNorm, 4),
            'amostras' => $n
        ]
    ];
}


/**
 * Leave-One-Out Cross-Validation para PCHIP.
 *
 * Para cada hora valida:
 *   1. Remove ela das ancoras
 *   2. Recalcula PCHIP com as ancoras restantes
 *   3. Compara estimativa vs valor real removido
 *
 * @param array $reais        Array[0..23] valores reais
 * @param array $horasValidas Array de horas validas
 * @return array              Score e metricas
 */
function calcularScorePCHIP_LOO(array $reais, array $horasValidas): array
{
    $pares = [];

    foreach ($horasValidas as $idx => $horaRemovida) {
        // Montar ancoras sem a hora removida
        $ancorasLOO = [];
        foreach ($horasValidas as $h) {
            if ($h !== $horaRemovida && $reais[$h] !== null) {
                $ancorasLOO[$h] = floatval($reais[$h]);
            }
        }

        // Precisa de pelo menos 3 ancoras para PCHIP fazer sentido
        if (count($ancorasLOO) < 3)
            continue;

        // Recalcular PCHIP sem a hora removida
        $estimativaLOO = calcularPCHIP_Simples($ancorasLOO, $horaRemovida);

        if ($estimativaLOO !== null && $reais[$horaRemovida] !== null) {
            $pares[] = [
                'real' => floatval($reais[$horaRemovida]),
                'est' => $estimativaLOO
            ];
        }
    }

    return calcularMetricasScore($pares);
}


/**
 * Calcula PCHIP para UMA hora especifica dado um conjunto de ancoras.
 * Versao simplificada que interpola monotonicamente.
 *
 * @param array $ancoras  [hora => valor] ancoras (sem a hora alvo)
 * @param int   $horaAlvo Hora a estimar
 * @return float|null     Valor estimado ou null
 */
function calcularPCHIP_Simples(array $ancoras, int $horaAlvo): ?float
{
    ksort($ancoras);
    $horas = array_keys($ancoras);
    $valores = array_values($ancoras);
    $n = count($horas);

    if ($n < 2)
        return null;

    // Encontrar intervalo que contem horaAlvo
    $i = 0;
    for ($k = 0; $k < $n - 1; $k++) {
        if ($horaAlvo >= $horas[$k] && $horaAlvo <= $horas[$k + 1]) {
            $i = $k;
            break;
        }
        // Extrapolacao: antes do primeiro ponto
        if ($horaAlvo < $horas[0]) {
            return $valores[0]; // Flat extrapolation
        }
        // Extrapolacao: apos o ultimo ponto
        if ($horaAlvo > $horas[$n - 1]) {
            return $valores[$n - 1]; // Flat extrapolation
        }
    }

    // Interpolacao linear simples (dentro do intervalo)
    $h0 = $horas[$i];
    $h1 = $horas[$i + 1];
    $v0 = $valores[$i];
    $v1 = $valores[$i + 1];

    if ($h1 == $h0)
        return $v0;

    $t = ($horaAlvo - $h0) / ($h1 - $h0);
    return $v0 + ($v1 - $v0) * $t;
}


/**
 * Calcula metricas de score a partir de pares real/estimado.
 *
 * @param array $pares  Array de ['real' => float, 'est' => float]
 * @return array        Score e metricas
 */
function calcularMetricasScore(array $pares): array
{
    $n = count($pares);

    if ($n < 2) {
        return [
            'score' => 5.0,
            'r2' => 0,
            'mae' => 0,
            'rmse' => 0,
            'mae_norm' => 0,
            'rmse_norm' => 0,
            'amostras' => $n
        ];
    }

    // Calcular MAE e RMSE
    $somaErro = 0;
    $somaErro2 = 0;
    $somaReal = 0;
    $somaReal2 = 0;
    $somaEst = 0;
    $minReal = PHP_FLOAT_MAX;
    $maxReal = PHP_FLOAT_MIN;

    foreach ($pares as $p) {
        $erro = abs($p['real'] - $p['est']);
        $somaErro += $erro;
        $somaErro2 += $erro * $erro;
        $somaReal += $p['real'];
        $somaReal2 += $p['real'] * $p['real'];
        $minReal = min($minReal, $p['real']);
        $maxReal = max($maxReal, $p['real']);
    }

    $mae = $somaErro / $n;
    $rmse = sqrt($somaErro2 / $n);

    // R² (coeficiente de determinacao)
    $mediaReal = $somaReal / $n;
    $ssTot = 0;
    $ssRes = 0;
    foreach ($pares as $p) {
        $ssTot += ($p['real'] - $mediaReal) ** 2;
        $ssRes += ($p['real'] - $p['est']) ** 2;
    }
    $r2 = $ssTot > 0 ? max(0, 1 - ($ssRes / $ssTot)) : 0;

    // Normalizar MAE e RMSE pela amplitude
    $amplitude = $maxReal - $minReal;
    $maeNorm = $amplitude > 0 ? $mae / $amplitude : 0;
    $rmseNorm = $amplitude > 0 ? $rmse / $amplitude : 0;

    // Score composto (0-10)
    $score = (0.40 * $r2 + 0.30 * max(0, 1 - $maeNorm) + 0.30 * max(0, 1 - $rmseNorm)) * 10;
    $score = round(min(10, max(0, $score)), 2);

    return [
        'score' => $score,
        'r2' => round($r2, 4),
        'mae' => round($mae, 4),
        'rmse' => round($rmse, 4),
        'mae_norm' => round($maeNorm, 4),
        'rmse_norm' => round($rmseNorm, 4),
        'amostras' => $n
    ];
}

// ============================================================
// FUNCOES AUXILIARES
// ============================================================

/**
 * Busca valores medios horarios de um ponto em uma data especifica.
 *
 * @param PDO    $pdo          Conexao PDO
 * @param int    $cdPonto      Codigo do ponto
 * @param string $data         Data YYYY-MM-DD
 * @param int    $tipoMedidor  Tipo do medidor
 * @return array               Mapa hora => valor medio (null se sem dados)
 */
function buscarValoresHorarios(PDO $pdo, int $cdPonto, string $data, int $tipoMedidor): array
{
    // Coluna de valor conforme tipo de medidor
    $campo = campoValorPorTipo($tipoMedidor);

    $sql = "
        SELECT
            DATEPART(HOUR, DT_LEITURA) AS NR_HORA,
            AVG($campo) AS VL_MEDIA,
            COUNT(*) AS QTD_REGISTROS
        FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
        WHERE CD_PONTO_MEDICAO = :cd_ponto
          AND CAST(DT_LEITURA AS DATE) = :data
          AND ID_SITUACAO = 1
          AND $campo IS NOT NULL
        GROUP BY DATEPART(HOUR, DT_LEITURA)
        ORDER BY NR_HORA
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cd_ponto' => $cdPonto, ':data' => $data]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }

    $valores = [];
    for ($h = 0; $h < 24; $h++) {
        $valores[$h] = null;
    }

    foreach ($rows as $row) {
        $h = intval($row['NR_HORA']);
        $valores[$h] = round(floatval($row['VL_MEDIA']), 4);
    }

    return $valores;
}

/**
 * Busca horas anomalas de um ponto/data na tabela de pendencias.
 *
 * @param PDO    $pdo      Conexao PDO
 * @param int    $cdPonto  Codigo do ponto
 * @param string $data     Data YYYY-MM-DD
 * @return array           Lista de horas anomalas [0-23]
 */
function buscarHorasAnomalas(PDO $pdo, int $cdPonto, string $data): array
{
    $sql = "
        SELECT DISTINCT NR_HORA
        FROM SIMP.dbo.IA_PENDENCIA_TRATAMENTO
        WHERE CD_PONTO_MEDICAO = :cd_ponto
          AND DT_REFERENCIA = :data
          AND ID_STATUS = 0
        ORDER BY NR_HORA
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cd_ponto' => $cdPonto, ':data' => $data]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Retorna o campo de valor apropriado para o tipo de medidor.
 *
 * @param int $tipoMedidor  Tipo do medidor
 * @return string            Nome do campo SQL
 */
function campoValorPorTipo(int $tipoMedidor): string
{
    switch ($tipoMedidor) {
        case 4:
            return 'VL_PRESSAO';
        case 6:
            return 'VL_RESERVATORIO';
        default:
            return 'VL_VAZAO_EFETIVA';
    }
}

/**
 * Faz requisicao POST ao container TensorFlow.
 *
 * @param string $url   URL completa do endpoint
 * @param array  $dados Dados para enviar (JSON)
 * @param int    $timeout Timeout em segundos
 * @return array         Resposta decodificada ou [success => false]
 */
function chamarTensorFlow(string $url, array $dados, int $timeout = 30): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($dados),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode !== 200) {
        return ['success' => false, 'error' => $curlError ?: "HTTP $httpCode"];
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : ['success' => false, 'error' => 'JSON invalido'];
}
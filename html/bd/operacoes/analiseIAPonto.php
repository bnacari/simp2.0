<?php
/**
 * SIMP 2.0 - Analise IA por Ponto de Medicao
 *
 * Cruza 3 fontes de dados (observacoes historicas + vizinhanca flowchart + metricas ML)
 * e gera um diagnostico sucinto (~200 caracteres) via IA (DeepSeek/Groq/Gemini).
 * Resultado salvo na tabela IA_ANALISE_PONTO para consulta instantanea.
 *
 * Acoes:
 *   - analisar_ponto:    Gera analise para 1 ponto (sob demanda)
 *   - consultar_analise: Le analise salva no banco
 *   - analisar_batch:    Gera analise para todos os pontos ativos
 *
 * @author  Bruno - CESAN
 * @version 1.0
 * @date    2026-03
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

/**
 * Retorna JSON limpo e encerra execucao.
 */
function retornarJSON_AnaliseIA($data)
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
        retornarJSON_AnaliseIA(['success' => false, 'error' => 'Conexao com banco nao estabelecida']);
    }

    // Carregar configuracao da IA
    $configFile = __DIR__ . '/../config/ia_config.php';
    if (!file_exists($configFile)) {
        retornarJSON_AnaliseIA(['success' => false, 'error' => 'Arquivo de configuracao IA nao encontrado']);
    }
    $config = require $configFile;

    // Carregar regras da IA do banco (opcional)
    $regrasIA = '';
    $buscarRegrasFile = __DIR__ . '/../ia/buscarRegrasIA.php';
    if (file_exists($buscarRegrasFile)) {
        include_once $buscarRegrasFile;
        try {
            $regrasIA = obterRegrasIA($pdoSIMP);
        } catch (Exception $e) {
            // Silencioso — regras sao opcionais
        }
    }

    // URL do container TensorFlow (para metricas ML)
    $tensorflowUrl = getenv('TENSORFLOW_URL') ?: 'http://simp20-tensorflow:5000';

    // Receber dados da requisicao
    $rawInput = file_get_contents('php://input');
    $dados    = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $dados = $_GET;
    }

    $acao = $dados['acao'] ?? $_GET['acao'] ?? '';

    if (empty($acao)) {
        retornarJSON_AnaliseIA([
            'success' => false,
            'error'   => 'Parametro "acao" obrigatorio. Valores: analisar_ponto, consultar_analise, analisar_batch'
        ]);
    }

    // ========================================
    // Roteamento
    // ========================================
    switch ($acao) {

        case 'analisar_ponto':
            $cdPonto  = intval($dados['cd_ponto_medicao'] ?? 0);
            $dtInicio = $dados['dt_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
            $dtFim    = $dados['dt_fim'] ?? date('Y-m-d');

            if (!$cdPonto) {
                retornarJSON_AnaliseIA(['success' => false, 'error' => 'cd_ponto_medicao obrigatorio']);
            }

            $resultado = analisarPonto($pdoSIMP, $config, $regrasIA, $tensorflowUrl, $cdPonto, $dtInicio, $dtFim);
            retornarJSON_AnaliseIA($resultado);
            break;

        case 'consultar_analise':
            $cdPonto = intval($dados['cd_ponto_medicao'] ?? $_GET['cd_ponto_medicao'] ?? 0);
            if (!$cdPonto) {
                retornarJSON_AnaliseIA(['success' => false, 'error' => 'cd_ponto_medicao obrigatorio']);
            }

            $resultado = consultarAnalise($pdoSIMP, $cdPonto);
            retornarJSON_AnaliseIA($resultado);
            break;

        case 'analisar_batch':
            ini_set('max_execution_time', 1800); // 30 min para batch completo
            $dtInicio = $dados['dt_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
            $dtFim    = $dados['dt_fim'] ?? date('Y-m-d');

            // Liberar session lock para nao bloquear outras requisicoes
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $resultado = analisarBatch($pdoSIMP, $config, $regrasIA, $tensorflowUrl, $dtInicio, $dtFim);
            retornarJSON_AnaliseIA($resultado);
            break;

        default:
            retornarJSON_AnaliseIA(['success' => false, 'error' => "Acao '$acao' nao reconhecida"]);
    }

} catch (Exception $e) {
    retornarJSON_AnaliseIA(['success' => false, 'error' => $e->getMessage()]);
}


// ============================================================
// FUNCAO PRINCIPAL: ANALISAR PONTO INDIVIDUAL
// ============================================================

/**
 * Analisa um ponto de medicao cruzando observacoes, vizinhos e metricas ML.
 * Envia contexto para a IA e salva resultado no banco.
 *
 * @param PDO    $pdo        Conexao PDO
 * @param array  $config     Configuracao da IA (ia_config.php)
 * @param string $regrasIA   Regras IA do banco (texto livre)
 * @param string $tfUrl      URL do container TensorFlow
 * @param int    $cdPonto    Codigo do ponto
 * @param string $dtInicio   Data inicio (YYYY-MM-DD)
 * @param string $dtFim      Data fim (YYYY-MM-DD)
 * @return array             Resultado com ds_analise, metadados
 */
function analisarPonto(PDO $pdo, array $config, string $regrasIA, string $tfUrl, int $cdPonto, string $dtInicio, string $dtFim): array
{
    // ========================================
    // 1. Buscar info do ponto
    // ========================================
    $infoPonto = buscarInfoPonto($pdo, $cdPonto);
    if (!$infoPonto) {
        return ['success' => false, 'error' => "Ponto $cdPonto nao encontrado ou inativo"];
    }

    // ========================================
    // 2. Buscar observacoes do periodo
    // ========================================
    $observacoes = buscarObservacoesPeriodo($pdo, $cdPonto, $dtInicio, $dtFim);
    $qtdObservacoes = 0;
    foreach ($observacoes as $obs) {
        $qtdObservacoes += intval($obs['QTD']);
    }

    // ========================================
    // 3. Buscar vizinhos e verificar anomalias
    // ========================================
    $vizinhosAnalise = analisarVizinhos($pdo, $infoPonto, $dtInicio, $dtFim);
    $qtdVizinhosAnomalos = 0;
    foreach ($vizinhosAnalise['vizinhos'] as $viz) {
        if ($viz['anomalo']) {
            $qtdVizinhosAnomalos++;
        }
    }

    // ========================================
    // 4. Buscar metricas ML (via TensorFlow)
    // ========================================
    $metricasML = buscarMetricasML($tfUrl, $cdPonto);

    // ========================================
    // 5. Montar contexto para a IA
    // ========================================
    $contexto = montarContextoIA(
        $infoPonto,
        $dtInicio,
        $dtFim,
        $observacoes,
        $vizinhosAnalise,
        $metricasML,
        $regrasIA
    );

    // ========================================
    // 6. Chamar a IA
    // ========================================
    $provider = $config['provider'] ?? 'deepseek';
    $respostaIA = chamarProviderIA($contexto, $config, $provider);

    // Truncar se necessario (maximo 250 chars, salva ate 300 no campo)
    if (mb_strlen($respostaIA) > 250) {
        $respostaIA = mb_substr($respostaIA, 0, 247) . '...';
    }

    // ========================================
    // 7. Salvar/atualizar no banco (UPSERT)
    // ========================================
    $cdUsuario = $_SESSION['cd_usuario'] ?? null;
    salvarAnalisePonto($pdo, $cdPonto, $dtInicio, $dtFim, $respostaIA, $provider, $qtdObservacoes, $qtdVizinhosAnomalos, $cdUsuario);

    // ========================================
    // 8. Log (isolado)
    // ========================================
    try {
        if (function_exists('registrarLog')) {
            registrarLog(
                $pdo,
                'ANALISE_IA_PONTO',
                'GERAR',
                "Ponto: {$infoPonto['DS_NOME']} | Provider: $provider | Obs: $qtdObservacoes | Viz anomalos: $qtdVizinhosAnomalos",
                'SUCESSO',
                $cdUsuario ?? 0
            );
        }
    } catch (Exception $logEx) {
        // Silencioso
    }

    return [
        'success'               => true,
        'ds_analise'            => $respostaIA,
        'dt_geracao'            => date('Y-m-d H:i:s'),
        'provider'              => $provider,
        'qtd_observacoes'       => $qtdObservacoes,
        'qtd_vizinhos_anomalos' => $qtdVizinhosAnomalos,
        'total_vizinhos'        => count($vizinhosAnalise['vizinhos']),
        'dt_periodo_inicio'     => $dtInicio,
        'dt_periodo_fim'        => $dtFim
    ];
}


// ============================================================
// CONSULTAR ANALISE SALVA
// ============================================================

/**
 * Consulta analise salva no banco para um ponto.
 *
 * @param PDO $pdo     Conexao PDO
 * @param int $cdPonto Codigo do ponto
 * @return array       Dados da analise ou null
 */
function consultarAnalise(PDO $pdo, int $cdPonto): array
{
    $sql = "
        SELECT
            CD_CHAVE,
            CD_PONTO_MEDICAO,
            DT_PERIODO_INICIO,
            DT_PERIODO_FIM,
            DS_ANALISE,
            DS_PROVIDER,
            QTD_OBSERVACOES,
            QTD_VIZINHOS_ANOMALOS,
            CD_USUARIO_SOLICITANTE,
            DT_GERACAO
        FROM SIMP.dbo.IA_ANALISE_PONTO
        WHERE CD_PONTO_MEDICAO = ?
          AND ID_SITUACAO = 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cdPonto]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registro) {
        return ['success' => true, 'analise' => null];
    }

    return [
        'success' => true,
        'analise' => [
            'ds_analise'            => $registro['DS_ANALISE'],
            'dt_geracao'            => $registro['DT_GERACAO'],
            'provider'              => $registro['DS_PROVIDER'],
            'qtd_observacoes'       => intval($registro['QTD_OBSERVACOES']),
            'qtd_vizinhos_anomalos' => intval($registro['QTD_VIZINHOS_ANOMALOS']),
            'dt_periodo_inicio'     => $registro['DT_PERIODO_INICIO'],
            'dt_periodo_fim'        => $registro['DT_PERIODO_FIM']
        ]
    ];
}


// ============================================================
// BATCH: ANALISAR TODOS OS PONTOS ATIVOS
// ============================================================

/**
 * Analisa todos os pontos ativos, pulando os que ja foram analisados
 * nas ultimas 24 horas. Inclui sleep entre chamadas para rate limit.
 *
 * @param PDO    $pdo      Conexao PDO
 * @param array  $config   Configuracao da IA
 * @param string $regrasIA Regras IA
 * @param string $tfUrl    URL TensorFlow
 * @param string $dtInicio Data inicio
 * @param string $dtFim    Data fim
 * @return array           Resumo do batch
 */
function analisarBatch(PDO $pdo, array $config, string $regrasIA, string $tfUrl, string $dtInicio, string $dtFim): array
{
    // Buscar pontos ativos
    $sql = "
        SELECT CD_PONTO_MEDICAO, DS_NOME
        FROM SIMP.dbo.PONTO_MEDICAO
        WHERE DT_DESATIVACAO IS NULL OR DT_DESATIVACAO > GETDATE()
        ORDER BY DS_NOME
    ";
    $stmt = $pdo->query($sql);
    $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar pontos ja analisados nas ultimas 24h (para pular)
    $sqlRecentes = "
        SELECT CD_PONTO_MEDICAO
        FROM SIMP.dbo.IA_ANALISE_PONTO
        WHERE ID_SITUACAO = 1
          AND DT_GERACAO >= DATEADD(HOUR, -24, GETDATE())
    ";
    $stmtRecentes = $pdo->query($sqlRecentes);
    $pontosRecentes = [];
    while ($row = $stmtRecentes->fetch(PDO::FETCH_ASSOC)) {
        $pontosRecentes[intval($row['CD_PONTO_MEDICAO'])] = true;
    }

    $resultado = [
        'total_pontos'     => count($pontos),
        'total_processados' => 0,
        'total_sucesso'    => 0,
        'total_erros'      => 0,
        'total_pulados'    => 0,
        'detalhes'         => []
    ];

    foreach ($pontos as $ponto) {
        $cdPonto = intval($ponto['CD_PONTO_MEDICAO']);

        // Pular se ja analisado nas ultimas 24h
        if (isset($pontosRecentes[$cdPonto])) {
            $resultado['total_pulados']++;
            continue;
        }

        try {
            $res = analisarPonto($pdo, $config, $regrasIA, $tfUrl, $cdPonto, $dtInicio, $dtFim);
            $resultado['total_processados']++;

            if ($res['success']) {
                $resultado['total_sucesso']++;
            } else {
                $resultado['total_erros']++;
            }

            $resultado['detalhes'][] = [
                'cd_ponto' => $cdPonto,
                'ds_nome'  => $ponto['DS_NOME'],
                'success'  => $res['success'],
                'error'    => $res['error'] ?? null
            ];

        } catch (Exception $e) {
            $resultado['total_processados']++;
            $resultado['total_erros']++;
            $resultado['detalhes'][] = [
                'cd_ponto' => $cdPonto,
                'ds_nome'  => $ponto['DS_NOME'],
                'success'  => false,
                'error'    => $e->getMessage()
            ];
        }

        // Sleep entre chamadas para respeitar rate limit da API
        sleep(1);
    }

    // Log do batch (isolado)
    try {
        if (function_exists('registrarLog')) {
            registrarLog(
                $pdo,
                'ANALISE_IA_PONTO',
                'BATCH',
                "Processados: {$resultado['total_processados']} | Sucesso: {$resultado['total_sucesso']} | " .
                "Erros: {$resultado['total_erros']} | Pulados: {$resultado['total_pulados']}",
                $resultado['total_erros'] > 0 ? 'PARCIAL' : 'SUCESSO',
                $_SESSION['cd_usuario'] ?? 0
            );
        }
    } catch (Exception $logEx) {
        // Silencioso
    }

    return ['success' => true] + $resultado;
}


// ============================================================
// FUNCOES AUXILIARES: COLETA DE DADOS
// ============================================================

/**
 * Busca informacoes do ponto de medicao.
 */
function buscarInfoPonto(PDO $pdo, int $cdPonto): ?array
{
    $sql = "
        SELECT
            PM.CD_PONTO_MEDICAO,
            PM.DS_NOME,
            PM.ID_TIPO_MEDIDOR,
            PM.DS_TAG_VAZAO,
            PM.DS_TAG_PRESSAO,
            PM.DS_TAG_RESERVATORIO,
            PM.CD_LOCALIDADE,
            PM.CD_UNIDADE,
            L.DS_NOME AS DS_LOCALIDADE,
            U.DS_NOME AS DS_UNIDADE
        FROM SIMP.dbo.PONTO_MEDICAO PM
        LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_LOCALIDADE
        LEFT JOIN SIMP.dbo.UNIDADE U ON PM.CD_UNIDADE = U.CD_UNIDADE
        WHERE PM.CD_PONTO_MEDICAO = ?
          AND (PM.DT_DESATIVACAO IS NULL OR PM.DT_DESATIVACAO > GETDATE())
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cdPonto]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Busca observacoes (DS_OBSERVACAO) do periodo, agrupadas por texto e frequencia.
 * Retorna no maximo 20 grupos mais frequentes.
 */
function buscarObservacoesPeriodo(PDO $pdo, int $cdPonto, string $dtInicio, string $dtFim): array
{
    $sql = "
        SELECT TOP 20
            DS_OBSERVACAO AS TEXTO,
            COUNT(*) AS QTD
        FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
        WHERE CD_PONTO_MEDICAO = ?
          AND ID_SITUACAO IN (1, 2)
          AND DS_OBSERVACAO IS NOT NULL
          AND DS_OBSERVACAO <> ''
          AND DT_LEITURA BETWEEN ? AND ?
        GROUP BY DS_OBSERVACAO
        ORDER BY COUNT(*) DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cdPonto, $dtInicio, $dtFim]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Analisa vizinhos do flowchart: busca TAGs relacionadas, resolve para pontos,
 * e verifica se tiveram observacoes de anomalia no periodo.
 *
 * @return array  { vizinhos: [ {cd_ponto, ds_nome, qtd_obs_anomalia, anomalo}, ... ], total: N }
 */
function analisarVizinhos(PDO $pdo, array $infoPonto, string $dtInicio, string $dtFim): array
{
    $vizinhos = [];

    // Buscar TAGs do ponto principal
    $tagsPrincipal = array_filter([
        $infoPonto['DS_TAG_VAZAO'],
        $infoPonto['DS_TAG_PRESSAO'],
        $infoPonto['DS_TAG_RESERVATORIO']
    ]);

    if (empty($tagsPrincipal)) {
        return ['vizinhos' => [], 'total' => 0];
    }

    // Para cada TAG principal, buscar TAGs auxiliares na relacao
    $placeholders = implode(',', array_fill(0, count($tagsPrincipal), '?'));
    $sqlRelacao = "
        SELECT DISTINCT TAG_AUXILIAR
        FROM SIMP.dbo.AUX_RELACAO_PONTOS_MEDICAO
        WHERE TAG_PRINCIPAL IN ($placeholders)
    ";
    $stmtRelacao = $pdo->prepare($sqlRelacao);
    $stmtRelacao->execute(array_values($tagsPrincipal));
    $tagsAuxiliares = $stmtRelacao->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tagsAuxiliares)) {
        return ['vizinhos' => [], 'total' => 0];
    }

    // Resolver TAGs auxiliares para pontos de medicao
    $placeholdersTags = implode(',', array_fill(0, count($tagsAuxiliares), '?'));
    $sqlPontos = "
        SELECT DISTINCT PM.CD_PONTO_MEDICAO, PM.DS_NOME, PM.ID_TIPO_MEDIDOR
        FROM SIMP.dbo.PONTO_MEDICAO PM
        WHERE (PM.DS_TAG_VAZAO IN ($placeholdersTags)
               OR PM.DS_TAG_PRESSAO IN ($placeholdersTags)
               OR PM.DS_TAG_RESERVATORIO IN ($placeholdersTags))
          AND PM.CD_PONTO_MEDICAO != ?
          AND (PM.DT_DESATIVACAO IS NULL OR PM.DT_DESATIVACAO > GETDATE())
    ";
    // Montar parametros: tags 3x (para cada campo) + cdPonto
    $paramsPontos = array_merge($tagsAuxiliares, $tagsAuxiliares, $tagsAuxiliares, [$infoPonto['CD_PONTO_MEDICAO']]);
    $stmtPontos = $pdo->prepare($sqlPontos);
    $stmtPontos->execute($paramsPontos);
    $pontosVizinhos = $stmtPontos->fetchAll(PDO::FETCH_ASSOC);

    // Para cada vizinho, contar observacoes de anomalia no periodo
    foreach ($pontosVizinhos as $vizinho) {
        $sqlObs = "
            SELECT COUNT(*) AS QTD
            FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
            WHERE CD_PONTO_MEDICAO = ?
              AND ID_SITUACAO IN (1, 2)
              AND DS_OBSERVACAO IS NOT NULL
              AND DS_OBSERVACAO <> ''
              AND DT_LEITURA BETWEEN ? AND ?
        ";
        $stmtObs = $pdo->prepare($sqlObs);
        $stmtObs->execute([$vizinho['CD_PONTO_MEDICAO'], $dtInicio, $dtFim]);
        $qtdObs = intval($stmtObs->fetchColumn());

        // Mapear tipo do medidor para nome legivel
        $tipoNome = mapearTipoMedidorNome($vizinho['ID_TIPO_MEDIDOR']);

        $vizinhos[] = [
            'cd_ponto'          => $vizinho['CD_PONTO_MEDICAO'],
            'ds_nome'           => $vizinho['DS_NOME'],
            'tipo'              => $tipoNome,
            'qtd_obs_anomalia'  => $qtdObs,
            'anomalo'           => $qtdObs > 0
        ];
    }

    return [
        'vizinhos' => $vizinhos,
        'total'    => count($vizinhos)
    ];
}

/**
 * Busca metricas do modelo ML via API do TensorFlow.
 * Retorna null se o ponto nao tem modelo treinado.
 */
function buscarMetricasML(string $tfUrl, int $cdPonto): ?array
{
    if (!function_exists('curl_init')) return null;

    // Chamar endpoint de status dos modelos
    $ch = curl_init($tfUrl . '/api/model-status');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_PROXY          => '',
        CURLOPT_NOPROXY        => '*'
    ]);

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($errno !== 0 || empty($response)) return null;

    $data = json_decode($response, true);
    if (!($data['success'] ?? false) || empty($data['modelos'])) return null;

    // Filtrar pelo ponto desejado
    foreach ($data['modelos'] as $modelo) {
        if (intval($modelo['cd_ponto'] ?? 0) === $cdPonto && ($modelo['existe'] ?? false)) {
            return $modelo['metricas'] ?? null;
        }
    }

    return null;
}

/**
 * Mapeia ID do tipo de medidor para nome legivel.
 */
function mapearTipoMedidorNome(int $tipo): string
{
    $mapa = [
        1 => 'Macromedidor',
        2 => 'Pitometrica',
        4 => 'Pressao',
        6 => 'Reservatorio',
        8 => 'Hidrometro'
    ];
    return $mapa[$tipo] ?? 'Tipo ' . $tipo;
}

/**
 * Formata o codigo do ponto segundo o padrao do sistema.
 * Formato: CD_LOCALIDADE-CD_PONTO(6digitos)-LETRA-CD_UNIDADE
 * Letras: 1=M, 2=E, 4=P, 6=R, 8=H
 */
function formatarCodigoPonto(array $info): string
{
    $letras = [1 => 'M', 2 => 'E', 4 => 'P', 6 => 'R', 8 => 'H'];
    $letra = $letras[intval($info['ID_TIPO_MEDIDOR'])] ?? '?';
    $cdLocal = str_pad($info['CD_LOCALIDADE'] ?? '0', 3, '0', STR_PAD_LEFT);
    $cdPonto = str_pad($info['CD_PONTO_MEDICAO'], 6, '0', STR_PAD_LEFT);
    $cdUnidade = str_pad($info['CD_UNIDADE'] ?? '0', 2, '0', STR_PAD_LEFT);
    return "$cdLocal-$cdPonto-$letra-$cdUnidade";
}


// ============================================================
// MONTAGEM DO CONTEXTO PARA A IA
// ============================================================

/**
 * Monta o texto de contexto completo que sera enviado ao provider de IA.
 * Inclui: info do ponto, observacoes agrupadas, vizinhos, metricas ML.
 */
function montarContextoIA(
    array $infoPonto,
    string $dtInicio,
    string $dtFim,
    array $observacoes,
    array $vizinhosAnalise,
    ?array $metricasML,
    string $regrasIA
): string {

    $tipoNome = mapearTipoMedidorNome($infoPonto['ID_TIPO_MEDIDOR']);
    $codigo = formatarCodigoPonto($infoPonto);

    // Formatar datas para pt-BR
    $inicioFmt = date('d/m/Y', strtotime($dtInicio));
    $fimFmt = date('d/m/Y', strtotime($dtFim));

    $ctx = "=== PONTO DE MEDICAO ===\n";
    $ctx .= "Nome: {$infoPonto['DS_NOME']}\n";
    $ctx .= "Codigo: $codigo\n";
    $ctx .= "Tipo: $tipoNome\n";
    $ctx .= "Periodo analisado: $inicioFmt a $fimFmt\n\n";

    // Observacoes agrupadas
    $ctx .= "=== OBSERVACOES DO PERIODO (agrupadas por frequencia) ===\n";
    if (empty($observacoes)) {
        $ctx .= "Nenhuma observacao registrada no periodo.\n";
    } else {
        foreach ($observacoes as $obs) {
            $texto = mb_substr($obs['TEXTO'], 0, 120);
            $ctx .= "- \"$texto\": {$obs['QTD']} ocorrencia(s)\n";
        }
    }
    $ctx .= "\n";

    // Vizinhos do flowchart
    $ctx .= "=== VIZINHOS DO FLOWCHART ===\n";
    $totalViz = $vizinhosAnalise['total'];
    $ctx .= "Total de vizinhos: $totalViz\n";

    if ($totalViz > 0) {
        $anomalos = 0;
        foreach ($vizinhosAnalise['vizinhos'] as $viz) {
            $status = $viz['anomalo'] ? 'ANOMALO' : 'NORMAL';
            $ctx .= "- {$viz['ds_nome']} ({$viz['tipo']}): {$viz['qtd_obs_anomalia']} observacoes de anomalia -> $status\n";
            if ($viz['anomalo']) $anomalos++;
        }
        $ctx .= "Vizinhos anomalos: $anomalos de $totalViz\n";
    } else {
        $ctx .= "Nenhum vizinho encontrado no flowchart.\n";
    }
    $ctx .= "\n";

    // Metricas ML
    $ctx .= "=== MODELO ML (se existir) ===\n";
    if ($metricasML) {
        $r2 = isset($metricasML['r2']) ? number_format($metricasML['r2'], 4) : '-';
        $mape = isset($metricasML['mape_pct']) ? number_format($metricasML['mape_pct'], 1) . '%' : '-';
        $mae = isset($metricasML['mae']) ? number_format($metricasML['mae'], 2) : '-';
        $rmse = isset($metricasML['rmse']) ? number_format($metricasML['rmse'], 2) : '-';
        $ctx .= "R2: $r2 | MAPE: $mape | MAE: $mae | RMSE: $rmse\n";

        if (isset($metricasML['data_treino'])) {
            $ctx .= "Modelo treinado em: {$metricasML['data_treino']}\n";
        }

        // Interpretacao do R2
        $r2Val = floatval($metricasML['r2'] ?? 0);
        if ($r2Val >= 0.9) {
            $ctx .= "Interpretacao: Modelo com excelente aderencia (R2 >= 0.9)\n";
        } elseif ($r2Val >= 0.7) {
            $ctx .= "Interpretacao: Modelo com boa aderencia (R2 >= 0.7)\n";
        } elseif ($r2Val >= 0.5) {
            $ctx .= "Interpretacao: Modelo com aderencia moderada (R2 >= 0.5)\n";
        } else {
            $ctx .= "Interpretacao: Modelo com baixa aderencia (R2 < 0.5)\n";
        }
    } else {
        $ctx .= "Nenhum modelo ML treinado para este ponto.\n";
    }
    $ctx .= "\n";

    // Instrucao final
    $ctx .= "=== INSTRUCAO ===\n";
    $ctx .= "Gere um resumo de NO MAXIMO 200 caracteres. Seja direto, tecnico, objetivo.\n";
    $ctx .= "Priorize: (1) anomalias recorrentes, (2) comparacao com vizinhos, (3) recomendacao.\n";
    $ctx .= "NAO use bullet points ou listas. Texto corrido apenas.\n";
    $ctx .= "Responda em portugues brasileiro.\n";

    // Regras do banco (se existirem)
    if (!empty($regrasIA)) {
        $ctx .= "\n=== REGRAS ADICIONAIS ===\n" . $regrasIA;
    }

    return $ctx;
}


// ============================================================
// CHAMADA AO PROVIDER DE IA
// ============================================================

/**
 * Chama o provider de IA configurado (DeepSeek/Groq/Gemini).
 * Reutiliza as funcoes de analiseIA.php via include.
 *
 * @param string $contexto  Contexto completo montado
 * @param array  $config    Configuracao do provider
 * @param string $provider  Nome do provider (deepseek/groq/gemini)
 * @return string           Resposta da IA
 */
function chamarProviderIA(string $contexto, array $config, string $provider): string
{
    // Incluir funcoes de chamada dos providers
    $analiseIAFile = __DIR__ . '/analiseIA.php';

    // As funcoes de chamada sao definidas no escopo global do analiseIA.php.
    // Verificar se ja foram incluidas, senao incluir diretamente as funcoes.
    if (!function_exists('chamarDeepSeekComHistorico')) {
        // Precisamos das funcoes sem executar o fluxo principal do analiseIA.php.
        // Vamos reimplementar a chamada aqui para evitar side effects.
        return chamarProviderDireto($contexto, $config, $provider);
    }

    // Se ja estao disponiveis, usar diretamente
    $historico = [['role' => 'user', 'content' => 'Analise o ponto conforme o contexto fornecido.']];

    if ($provider === 'deepseek') {
        return chamarDeepSeekComHistorico($contexto, $historico, $config);
    } elseif ($provider === 'groq') {
        return chamarGroqComHistorico($contexto, $historico, $config);
    } else {
        return chamarGeminiComHistorico($contexto, $historico, $config);
    }
}

/**
 * Chamada direta ao provider via cURL (sem depender de analiseIA.php).
 * Suporta DeepSeek, Groq (formato OpenAI) e Gemini.
 */
function chamarProviderDireto(string $contexto, array $config, string $provider): string
{
    if (!function_exists('curl_init')) {
        throw new Exception('cURL nao disponivel');
    }

    // Montar payload conforme provider
    if ($provider === 'gemini') {
        return chamarGeminiDireto($contexto, $config);
    }

    // DeepSeek e Groq usam formato OpenAI
    $providerConfig = $config[$provider] ?? [];
    $url = $providerConfig['api_url'] ?? '';
    $apiKey = $providerConfig['api_key'] ?? '';
    $model = $providerConfig['model'] ?? '';

    if (empty($url) || empty($apiKey)) {
        throw new Exception("Configuracao do provider '$provider' incompleta");
    }

    $payload = [
        'model'       => $model,
        'messages'    => [
            ['role' => 'system', 'content' => $contexto],
            ['role' => 'user', 'content' => 'Analise o ponto conforme o contexto fornecido.']
        ],
        'temperature' => $config['temperature'] ?? 0.3,
        'max_tokens'  => $config['max_tokens'] ?? 2048,
        'stream'      => false
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 120,
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
        throw new Exception("Erro API ($provider): $errorMsg");
    }

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? 'Sem resposta';
}

/**
 * Chamada direta ao Gemini (formato especifico Google).
 */
function chamarGeminiDireto(string $contexto, array $config): string
{
    $geminiConfig = $config['gemini'] ?? [];
    $url = $geminiConfig['api_url'] . $geminiConfig['model'] . ':generateContent?key=' . $geminiConfig['api_key'];

    $payload = [
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => 'Analise o ponto conforme o contexto fornecido.']]]
        ],
        'systemInstruction' => [
            'parts' => [['text' => $contexto]]
        ],
        'generationConfig' => [
            'temperature'    => $config['temperature'] ?? 0.3,
            'maxOutputTokens' => $config['max_tokens'] ?? 2048
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 120,
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
        throw new Exception("Erro API (Gemini): $errorMsg");
    }

    $data = json_decode($response, true);
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Sem resposta';
}


// ============================================================
// SALVAR/ATUALIZAR ANALISE NO BANCO
// ============================================================

/**
 * Salva ou atualiza a analise do ponto na tabela IA_ANALISE_PONTO.
 * Se ja existe registro ativo (ID_SITUACAO=1), faz UPDATE.
 * Se nao existe, faz INSERT.
 */
function salvarAnalisePonto(
    PDO $pdo,
    int $cdPonto,
    string $dtInicio,
    string $dtFim,
    string $dsAnalise,
    string $provider,
    int $qtdObs,
    int $qtdVizAnomalo,
    ?int $cdUsuario
): void {

    // Verificar se ja existe registro ativo
    $sqlCheck = "SELECT CD_CHAVE FROM SIMP.dbo.IA_ANALISE_PONTO WHERE CD_PONTO_MEDICAO = ? AND ID_SITUACAO = 1";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute([$cdPonto]);
    $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        // UPDATE no registro existente
        $sqlUpdate = "
            UPDATE SIMP.dbo.IA_ANALISE_PONTO
            SET DT_PERIODO_INICIO      = ?,
                DT_PERIODO_FIM         = ?,
                DS_ANALISE             = ?,
                DS_PROVIDER            = ?,
                QTD_OBSERVACOES        = ?,
                QTD_VIZINHOS_ANOMALOS  = ?,
                CD_USUARIO_SOLICITANTE = ?,
                DT_GERACAO             = GETDATE()
            WHERE CD_CHAVE = ?
        ";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([
            $dtInicio, $dtFim, $dsAnalise, $provider,
            $qtdObs, $qtdVizAnomalo, $cdUsuario,
            $existente['CD_CHAVE']
        ]);
    } else {
        // INSERT novo registro
        $sqlInsert = "
            INSERT INTO SIMP.dbo.IA_ANALISE_PONTO (
                CD_PONTO_MEDICAO, DT_PERIODO_INICIO, DT_PERIODO_FIM,
                DS_ANALISE, DS_PROVIDER, QTD_OBSERVACOES,
                QTD_VIZINHOS_ANOMALOS, CD_USUARIO_SOLICITANTE
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([
            $cdPonto, $dtInicio, $dtFim, $dsAnalise, $provider,
            $qtdObs, $qtdVizAnomalo, $cdUsuario
        ]);
    }
}

<?php
/**
 * getEstimativasRede.php
 * 
 * Busca pontos de medição relacionados (mesma ENTIDADE_VALOR / rede operacional)
 * e calcula estimativas de valores usando 4 métodos:
 * 
 * 1. PCHIP: interpolação monotônica (Fritsch-Carlson) usando horas válidas como âncoras
 * 2. TENDÊNCIA DA REDE: analisa variação dos outros pontos vs histórico e aplica o fator
 * 3. PROPORÇÃO HISTÓRICA: usa a proporção média histórica do ponto na rede
 * 4. MÍNIMOS QUADRADOS: regressão linear sobre semanas históricas para projetar tendência
 * 
 * Parâmetros GET:
 *   cdPonto - Código do ponto de medição
 *   data    - Data no formato YYYY-MM-DD
 * 
 * Retorno JSON:
 *   - pontos_rede: lista de pontos na mesma entidade
 *   - estimativas: array[0..23] com valores estimados por cada método
 *   - metadados: informações sobre a rede e cálculos
 * 
 * @author SIMP - Sistema Integrado de Macromedição e Pitometria
 */

header('Content-Type: application/json; charset=utf-8');

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../conexao.php';

try {
    // ========================================
    // VALIDAÇÃO DE PARÂMETROS
    // ========================================
    $cdPonto = isset($_GET['cdPonto']) ? (int) $_GET['cdPonto'] : 0;
    $data = isset($_GET['data']) ? trim($_GET['data']) : '';

    if ($cdPonto <= 0) {
        throw new Exception('Código do ponto de medição inválido');
    }

    if (empty($data) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        throw new Exception('Data inválida. Use o formato YYYY-MM-DD');
    }

    // ========================================
    // 1. BUSCAR INFORMAÇÕES DO PONTO ATUAL
    // ========================================
    $sqlPonto = "SELECT 
                    PM.CD_PONTO_MEDICAO,
                    PM.DS_NOME,
                    PM.ID_TIPO_MEDIDOR,
                    PM.CD_LOCALIDADE,
                    L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
                    L.CD_UNIDADE
                 FROM SIMP.dbo.PONTO_MEDICAO PM
                 LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
                 WHERE PM.CD_PONTO_MEDICAO = :cdPonto";

    $stmtPonto = $pdoSIMP->prepare($sqlPonto);
    $stmtPonto->execute([':cdPonto' => $cdPonto]);
    $pontoAtual = $stmtPonto->fetch(PDO::FETCH_ASSOC);

    if (!$pontoAtual) {
        throw new Exception('Ponto de medição não encontrado');
    }

    $tipoMedidor = (int) $pontoAtual['ID_TIPO_MEDIDOR'];

    // Mapeamento de colunas por tipo de medidor
    $colunasPorTipo = [
        1 => 'VL_VAZAO_EFETIVA',   // Macromedidor
        2 => 'VL_VAZAO_EFETIVA',   // Estação Pitométrica
        4 => 'VL_PRESSAO',         // Pressão
        6 => 'VL_RESERVATORIO',    // Reservatório
        8 => 'VL_VAZAO_EFETIVA'    // Hidrômetro
    ];
    $coluna = $colunasPorTipo[$tipoMedidor] ?? 'VL_VAZAO_EFETIVA';

    // ========================================
    // 2. BUSCAR CD_ENTIDADE_VALOR_ID DO PONTO
    // A mesma lógica do getDadosOperacoes.php:
    // CD_ENTIDADE_VALOR_ID agrupa MÚLTIPLOS ENTIDADE_VALOR (entrada + saída)
    // ========================================
    $sqlEntidadePonto = "SELECT DISTINCT
                            EV.CD_ENTIDADE_VALOR_ID,
                            EV.CD_CHAVE AS CD_ENTIDADE_VALOR,
                            EV.ID_FLUXO,
                            ET.DS_NOME AS TIPO_ENTIDADE
                         FROM SIMP.dbo.ENTIDADE_VALOR_ITEM EVI
                         INNER JOIN SIMP.dbo.ENTIDADE_VALOR EV ON EV.CD_CHAVE = EVI.CD_ENTIDADE_VALOR
                         INNER JOIN SIMP.dbo.ENTIDADE_TIPO ET ON ET.CD_CHAVE = EV.CD_ENTIDADE_TIPO
                         WHERE EVI.CD_PONTO_MEDICAO = :cdPonto
                           AND ET.DT_EXC_ENTIDADE_TIPO IS NULL
                           AND (EVI.DT_INICIO IS NULL OR EVI.DT_INICIO <= :dataIni)
                           AND (EVI.DT_FIM IS NULL OR EVI.DT_FIM >= :dataFim)";

    $stmtEntPonto = $pdoSIMP->prepare($sqlEntidadePonto);
    $stmtEntPonto->execute([
        ':cdPonto' => $cdPonto,
        ':dataIni' => $data,
        ':dataFim' => $data
    ]);
    $entidadesDoPonto = $stmtEntPonto->fetchAll(PDO::FETCH_ASSOC);

    // Se o ponto não pertence a nenhuma entidade, retornar apenas interpolação
    if (empty($entidadesDoPonto)) {
        $dadosPontoAtual = obterDadosHorariosPonto($pdoSIMP, $cdPonto, $data, $coluna, $tipoMedidor);
        // Detectar horas sem dados (equivalente a "anomalas" para PCHIP)
        $horasNulas = [];
        for ($hh = 0; $hh < 24; $hh++) {
            if (!isset($dadosPontoAtual[$hh]) || $dadosPontoAtual[$hh] === null) {
                $horasNulas[] = $hh;
            }
        }
        $pchip = calcularPCHIP_Rede($dadosPontoAtual, $horasNulas);

        echo json_encode([
            'success' => true,
            'tem_rede' => false,
            'pontos_rede' => [],
            'estimativas' => [
                'pchip' => $pchip,
                'tendencia_rede' => array_fill(0, 24, null),
                'proporcao' => array_fill(0, 24, null),
                'minimos_quadrados' => calcularMinimosQuadrados($pdoSIMP, $cdPonto, $data, $coluna, $tipoMedidor)
            ],
            'metadados' => [
                'ponto_atual' => $cdPonto,
                'entidades' => [],
                'mensagem' => 'Ponto nao vinculado a nenhuma unidade operacional. Apenas PCHIP disponivel.'
            ]
        ]);
        exit;
    }

    // ========================================
    // 3. BUSCAR TODOS OS PONTOS DA REDE COMPLETA
    // Igual getDadosOperacoes.php: usa CD_ENTIDADE_VALOR_ID para
    // agrupar TODOS os ENTIDADE_VALOR (entrada + saída + municipal)
    // e depois buscar TODOS os pontos de TODOS eles
    // ========================================
    $pontosRede = [];
    $entidadesInfo = [];
    $valorEntidadeIdsUsados = [];

    foreach ($entidadesDoPonto as $entPonto) {
        $valorEntidadeId = $entPonto['CD_ENTIDADE_VALOR_ID'];

        // Evitar processar o mesmo CD_ENTIDADE_VALOR_ID mais de uma vez
        if (in_array($valorEntidadeId, $valorEntidadeIdsUsados)) {
            continue;
        }
        $valorEntidadeIdsUsados[] = $valorEntidadeId;

        // Buscar TODOS os ENTIDADE_VALOR com o mesmo CD_ENTIDADE_VALOR_ID
        // (ex: GUA-ETA-001 pode ter um registro de Entrada e outro de Saída)
        $sqlTodosValores = "SELECT CD_CHAVE, ID_FLUXO, DS_NOME
                            FROM SIMP.dbo.ENTIDADE_VALOR
                            WHERE CD_ENTIDADE_VALOR_ID = :valorEntId";
        $stmtTodosValores = $pdoSIMP->prepare($sqlTodosValores);
        $stmtTodosValores->execute([':valorEntId' => $valorEntidadeId]);
        $todosValores = $stmtTodosValores->fetchAll(PDO::FETCH_ASSOC);

        // Para cada ENTIDADE_VALOR, buscar seus pontos
        foreach ($todosValores as $ev) {
            $cdEntidadeValor = $ev['CD_CHAVE'];
            $fluxoId = (int) ($ev['ID_FLUXO'] ?? 1);
            $fluxoNome = [1 => 'Entrada', 2 => 'Saída', 3 => 'Municipal', 4 => 'N/A'][$fluxoId] ?? 'Entrada';

            $entidadesInfo[] = [
                'nome' => $ev['DS_NOME'],
                'id' => $valorEntidadeId,
                'tipo' => $entPonto['TIPO_ENTIDADE'],
                'fluxo' => $fluxoNome
            ];

            // Buscar todos os pontos vinculados a este ENTIDADE_VALOR
            $sqlPontosRede = "SELECT
                                EVI.CD_PONTO_MEDICAO,
                                PM.DS_NOME,
                                PM.ID_TIPO_MEDIDOR,
                                EVI.ID_OPERACAO,
                                EVI.NR_ORDEM,
                                L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
                                L.CD_UNIDADE
                              FROM SIMP.dbo.ENTIDADE_VALOR_ITEM EVI
                              INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = EVI.CD_PONTO_MEDICAO
                              LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
                              WHERE EVI.CD_ENTIDADE_VALOR = :cdEntVal
                                AND (EVI.DT_INICIO IS NULL OR EVI.DT_INICIO <= :dataIni)
                                AND (EVI.DT_FIM IS NULL OR EVI.DT_FIM >= :dataFim)
                              ORDER BY EVI.NR_ORDEM, PM.DS_NOME";

            $stmtPontosRede = $pdoSIMP->prepare($sqlPontosRede);
            $stmtPontosRede->execute([
                ':cdEntVal' => $cdEntidadeValor,
                ':dataIni' => $data,
                ':dataFim' => $data
            ]);
            $pontos = $stmtPontosRede->fetchAll(PDO::FETCH_ASSOC);

            foreach ($pontos as $p) {
                $cdP = (int) $p['CD_PONTO_MEDICAO'];

                // Se o ponto já foi adicionado por outra entidade, pular
                if (isset($pontosRede[$cdP])) {
                    continue;
                }

                // Letras do tipo de medidor
                $letrasTipo = [1 => 'M', 2 => 'E', 4 => 'P', 6 => 'R', 8 => 'H'];
                $letra = $letrasTipo[$p['ID_TIPO_MEDIDOR']] ?? 'X';
                $codigoFormatado = ($p['CD_LOCALIDADE_CODIGO'] ?? '0') . '-' .
                    str_pad($cdP, 6, '0', STR_PAD_LEFT) . '-' .
                    $letra . '-' .
                    ($p['CD_UNIDADE'] ?? '0');

                // Determinar operação EFETIVA do ponto para balanço hídrico:
                // Combina ID_FLUXO do ENTIDADE_VALOR pai com ID_OPERACAO do item.
                //
                // No SIMP, ID_OPERACAO é um MODIFICADOR dentro do grupo de fluxo:
                //   - Fluxo=Entrada + op=1 → ADICIONA ao sistema (+) → efetiva = 1
                //   - Fluxo=Entrada + op=2 → SUBTRAI da entrada    → efetiva = 2
                //   - Fluxo=Saída   + op=1 → SUBTRAI do sistema (-) → efetiva = 2
                //   - Fluxo=Saída   + op=2 → REDUZ saída (raro)    → efetiva = 1
                //   - Fluxo=Municipal(3)    → igual a Saída
                //
                // Resultado: operacao_efetiva = 1 (entrada/+) ou 2 (saída/-)
                $itemOp = (int) ($p['ID_OPERACAO'] ?? 1);

                if ($fluxoId == 2 || $fluxoId == 3) {
                    // Fluxo é Saída ou Municipal → inverte a lógica:
                    // op=1 no grupo saída = SÁIDA do sistema (efetiva=2)
                    // op=2 no grupo saída = REDUZ saída = efetivamente entrada (efetiva=1)
                    $operacaoEfetiva = ($itemOp == 2) ? 1 : 2;
                } else {
                    // Fluxo é Entrada ou N/A → mantém:
                    // op=1 no grupo entrada = ENTRADA (efetiva=1)
                    // op=2 no grupo entrada = SUBTRAI entrada (efetiva=2)
                    $operacaoEfetiva = $itemOp;
                }

                $pontosRede[$cdP] = [
                    'cd_ponto' => $cdP,
                    'nome' => $p['DS_NOME'],
                    'codigo' => $codigoFormatado,
                    'tipo_medidor' => (int) $p['ID_TIPO_MEDIDOR'],
                    'operacao' => $operacaoEfetiva,
                    'fluxo' => $fluxoNome,
                    'fluxo_id' => $fluxoId,
                    'id_operacao_original' => $itemOp,
                    'is_ponto_atual' => ($cdP === $cdPonto),
                    'entidade' => $ev['DS_NOME'],
                    'entidade_id' => $valorEntidadeId
                ];
            }
        }
    }

    // Se só existe o próprio ponto na rede, não há como fazer balanço/proporção
    if (count($pontosRede) <= 1) {
        $dadosPontoAtual = obterDadosHorariosPonto($pdoSIMP, $cdPonto, $data, $coluna, $tipoMedidor);
        $horasNulas = [];
        for ($hh = 0; $hh < 24; $hh++) {
            if (!isset($dadosPontoAtual[$hh]) || $dadosPontoAtual[$hh] === null) {
                $horasNulas[] = $hh;
            }
        }
        $pchip = calcularPCHIP_Rede($dadosPontoAtual, $horasNulas);

        echo json_encode([
            'success' => true,
            'tem_rede' => false,
            'pontos_rede' => array_values($pontosRede),
            'estimativas' => [
                'pchip' => $pchip,
                'tendencia_rede' => array_fill(0, 24, null),
                'proporcao' => array_fill(0, 24, null),
                'minimos_quadrados' => calcularMinimosQuadrados($pdoSIMP, $cdPonto, $data, $coluna, $tipoMedidor)
            ],
            'metadados' => [
                'ponto_atual' => $cdPonto,
                'entidades' => $entidadesInfo,
                'total_pontos_rede' => count($pontosRede),
                'mensagem' => 'Ponto e o unico na rede. Apenas PCHIP disponivel.'
            ]
        ]);
        exit;
    }

    // ========================================
    // 4. OBTER DADOS HORÁRIOS DE TODOS OS PONTOS DA REDE
    // ========================================
    $dadosTodosPontos = [];
    foreach ($pontosRede as $cdP => $infoPonto) {
        // Determinar coluna correta por tipo de medidor do ponto
        $colunaP = $colunasPorTipo[$infoPonto['tipo_medidor']] ?? 'VL_VAZAO_EFETIVA';
        $dadosTodosPontos[$cdP] = obterDadosHorariosPonto($pdoSIMP, $cdP, $data, $colunaP, $infoPonto['tipo_medidor']);
    }

    // ========================================
    // 5. METODO 1: PCHIP (interpolacao monotonica do proprio ponto)
    // Usa horas com dados como ancoras e interpola as horas sem dados
    // via curvas de Hermite (Fritsch-Carlson) — sem overshoots
    // ========================================
    $dadosPontoAtual = $dadosTodosPontos[$cdPonto] ?? [];
    $horasNulas = [];
    for ($hh = 0; $hh < 24; $hh++) {
        if (!isset($dadosPontoAtual[$hh]) || $dadosPontoAtual[$hh] === null) {
            $horasNulas[] = $hh;
        }
    }
    $pchip = calcularPCHIP_Rede($dadosPontoAtual, $horasNulas);

    // ========================================
    // 6. MÉTODO 2: TENDÊNCIA DA REDE (fator de variação dos outros pontos)
    // Analisa se os outros pontos estão variando para cima ou para baixo
    // em relação ao histórico e aplica o mesmo fator no ponto atual
    // ========================================
    $tendenciaRede = calcularTendenciaRede(
        $pdoSIMP,
        $cdPonto,
        $data,
        $pontosRede,
        $dadosTodosPontos,
        $coluna
    );

    // ========================================
    // 7. MÉTODO 3: PROPORÇÃO HISTÓRICA
    // ========================================
    $proporcao = calcularProporcaoHistorica(
        $pdoSIMP,
        $cdPonto,
        $data,
        $pontosRede,
        $dadosTodosPontos,
        $coluna
    );

    // ========================================
    // 7.5. MÉTODO 4: MÍNIMOS QUADRADOS (regressão linear)
    // Projeta o valor com base na tendência das últimas semanas
    // ========================================
    $minimosQuadrados = calcularMinimosQuadrados(
        $pdoSIMP,
        $cdPonto,
        $data,
        $coluna,
        $pontosRede[$cdPonto]['tipo_medidor'] ?? 1
    );

    // ========================================
    // 8. DADOS DOS PONTOS DA REDE (para contexto visual)
    // ========================================
    $pontosRedeResumo = [];
    foreach ($pontosRede as $cdP => $info) {
        $dadosP = $dadosTodosPontos[$cdP] ?? [];
        $horasComDados = 0;
        $mediaGeral = 0;
        $somaValores = 0;
        $totalReg = 0;

        foreach ($dadosP as $h => $d) {
            if ($d !== null) {
                $horasComDados++;
                $somaValores += $d;
                $totalReg++;
            }
        }

        $pontosRedeResumo[] = [
            'cd_ponto' => $cdP,
            'nome' => $info['nome'],
            'codigo' => $info['codigo'],
            'tipo_medidor' => $info['tipo_medidor'],
            'operacao' => $info['operacao'],
            'operacao_texto' => $info['operacao'] == 2 ? 'Saída (−)' : 'Entrada (+)',
            'fluxo' => $info['fluxo'] ?? '',
            'fluxo_id' => $info['fluxo_id'] ?? null,
            'id_operacao_original' => $info['id_operacao_original'] ?? null,
            'is_ponto_atual' => $info['is_ponto_atual'],
            'entidade' => $info['entidade'],
            'horas_com_dados' => $horasComDados,
            'media_geral' => $totalReg > 0 ? round($somaValores / $totalReg, 2) : null
        ];
    }

    // Contar pontos de entrada e saída para debug
    $qtdEntrada = 0;
    $qtdSaida = 0;
    foreach ($pontosRede as $p) {
        if ($p['operacao'] == 2)
            $qtdSaida++;
        else
            $qtdEntrada++;
    }

    // ========================================
    // 9. RETORNO
    // ========================================
    echo json_encode([
        'success' => true,
        'tem_rede' => true,
        'pontos_rede' => $pontosRedeResumo,
        'estimativas' => [
            'pchip' => $pchip,
            'tendencia_rede' => $tendenciaRede,
            'proporcao' => $proporcao,
            'minimos_quadrados' => $minimosQuadrados
        ],
        'metadados' => [
            'ponto_atual' => $cdPonto,
            'nome_ponto' => $pontoAtual['DS_NOME'],
            'tipo_medidor' => $tipoMedidor,
            'entidades' => $entidadesInfo,
            'total_pontos_rede' => count($pontosRede),
            'pontos_entrada' => $qtdEntrada,
            'pontos_saida' => $qtdSaida,
            'data' => $data
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'erro' => $e->getMessage()
    ]);
}


// ============================================================
// FUNÇÕES AUXILIARES
// ============================================================

/**
 * Obtém dados horários (média) de um ponto para uma data
 * Retorna array indexado por hora (0..23), null = sem dados
 *
 * @param PDO    $pdo         Conexão com banco
 * @param int    $cdPonto     Código do ponto
 * @param string $data        Data YYYY-MM-DD
 * @param string $coluna      Coluna do valor (VL_VAZAO_EFETIVA, VL_PRESSAO, etc.)
 * @param int    $tipoMedidor Tipo do medidor
 * @return array Array[0..23] com valor médio ou null
 */
function obterDadosHorariosPonto($pdo, $cdPonto, $data, $coluna, $tipoMedidor)
{
    // Inicializar 24 horas como null
    $resultado = array_fill(0, 24, null);

    try {
        if ($tipoMedidor == 6) {
            // Reservatório: usar MAX em vez de AVG
            $sql = "SELECT 
                        DATEPART(HOUR, DT_LEITURA) AS HORA,
                        MAX(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} END) AS VALOR
                    FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                    WHERE CD_PONTO_MEDICAO = :cdPonto
                      AND CAST(DT_LEITURA AS DATE) = :data
                      AND {$coluna} IS NOT NULL
                    GROUP BY DATEPART(HOUR, DT_LEITURA)";
        } else {
            // Demais tipos: usar AVG dos registros válidos
            $sql = "SELECT 
                        DATEPART(HOUR, DT_LEITURA) AS HORA,
                        AVG(CASE WHEN ID_SITUACAO = 1 THEN {$coluna} ELSE NULL END) AS VALOR
                    FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
                    WHERE CD_PONTO_MEDICAO = :cdPonto
                      AND CAST(DT_LEITURA AS DATE) = :data
                      AND {$coluna} IS NOT NULL
                    GROUP BY DATEPART(HOUR, DT_LEITURA)
                    HAVING COUNT(CASE WHEN ID_SITUACAO = 1 THEN 1 END) > 0";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cdPonto' => $cdPonto, ':data' => $data]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $hora = (int) $row['HORA'];
            $valor = $row['VALOR'] !== null ? round((float) $row['VALOR'], 2) : null;
            if ($valor !== null && $valor != 0) {
                $resultado[$hora] = $valor;
            }
        }
    } catch (Exception $e) {
        // Retorna array vazio (nulls) em caso de erro
    }

    return $resultado;
}

/**
 * METODO 1: PCHIP — Interpolacao Monotonica (Fritsch-Carlson)
 * 
 * Modo LOO (Leave-One-Out): para cada hora, remove ela das ancoras
 * e interpola usando as restantes. Gera estimativa independente
 * para TODAS as horas, util como linha de comparacao no grafico.
 * 
 * Se horasAnomalas informadas, essas horas sao excluidas das ancoras
 * e interpoladas diretamente (sem LOO).
 *
 * Requer pelo menos 3 ancoras. Fallback para interpolacao linear simples.
 *
 * @param array $dados         Array[0..23] com valores ou null
 * @param array $horasAnomalas Horas a excluir das ancoras (opcional)
 * @return array               Array[0..23] com valores estimados ou null
 */
function calcularPCHIP_Rede($dados, $horasAnomalas = [])
{
    $resultado = array_fill(0, 24, null);

    // Todas as horas validas (nao-anomalas e com dados)
    $horasValidas = [];
    for ($h = 0; $h < 24; $h++) {
        if (!in_array($h, $horasAnomalas) && isset($dados[$h]) && $dados[$h] !== null) {
            $horasValidas[$h] = floatval($dados[$h]);
        }
    }

    // Precisa de pelo menos 4 ancoras para LOO (3 restantes apos remover 1)
    if (count($horasValidas) < 4) {
        // Fallback: interpolacao linear simples se tiver pelo menos 2
        if (count($horasValidas) >= 2) {
            return calcularInterpolacaoLinearFallback($dados);
        }
        return $resultado;
    }

    // -------------------------------------------------------
    // MODO LOO: para cada hora, remover das ancoras e interpolar
    // Gera estimativa independente (nao passa pela ancora)
    // -------------------------------------------------------
    for ($h = 0; $h < 24; $h++) {
        // Montar ancoras SEM a hora atual
        $ancorasX = [];
        $ancorasY = [];

        foreach ($horasValidas as $hv => $val) {
            if ($hv === $h)
                continue; // LOO: excluir hora atual
            $ancorasX[] = $hv;
            $ancorasY[] = $val;
        }

        // Precisa de pelo menos 3 ancoras restantes para PCHIP
        if (count($ancorasX) < 3)
            continue;

        // Calcular derivadas e interpolar
        $derivadas = calcularDerivadasMonotonicasRede($ancorasX, $ancorasY);
        $valor = interpolarPCHIP_Rede($h, $ancorasX, $ancorasY, $derivadas);

        if ($valor >= 0) {
            $resultado[$h] = round($valor, 2);
        }
    }

    // -------------------------------------------------------
    // Horas anomalas (sem dados): interpolar usando TODAS as ancoras
    // -------------------------------------------------------
    if (!empty($horasAnomalas)) {
        $allX = array_keys($horasValidas);
        $allY = array_values($horasValidas);
        if (count($allX) >= 3) {
            $derivadasFull = calcularDerivadasMonotonicasRede($allX, $allY);
            foreach ($horasAnomalas as $ha) {
                $valor = interpolarPCHIP_Rede($ha, $allX, $allY, $derivadasFull);
                if ($valor >= 0) {
                    $resultado[$ha] = round($valor, 2);
                }
            }
        }
    }

    return $resultado;
}

/**
 * Calcula derivadas monotonicas pelo metodo Fritsch-Carlson.
 * Garante que a interpolacao nao gera overshoots.
 *
 * @param array $x Horas (ancoras X)
 * @param array $y Valores (ancoras Y)
 * @return array   Derivadas em cada ancora
 */
function calcularDerivadasMonotonicasRede($x, $y)
{
    $n = count($x);
    $d = array_fill(0, $n, 0.0);

    // Calcular inclinacoes entre pontos adjacentes (deltas)
    $delta = [];
    for ($i = 0; $i < $n - 1; $i++) {
        $hk = $x[$i + 1] - $x[$i];
        if ($hk > 0) {
            $delta[$i] = ($y[$i + 1] - $y[$i]) / $hk;
        } else {
            $delta[$i] = 0;
        }
    }

    // Derivadas iniciais (media harmonica dos deltas adjacentes)
    $d[0] = $delta[0];
    $d[$n - 1] = $delta[$n - 2];

    for ($i = 1; $i < $n - 1; $i++) {
        if ($delta[$i - 1] * $delta[$i] <= 0) {
            // Mudanca de sinal: derivada zero (ponto de inflexao)
            $d[$i] = 0;
        } else {
            // Media harmonica ponderada
            $d[$i] = ($delta[$i - 1] + $delta[$i]) / 2.0;
        }
    }

    // Correcao Fritsch-Carlson: garantir monotonicidade
    for ($i = 0; $i < $n - 1; $i++) {
        if (abs($delta[$i]) < 1e-10) {
            $d[$i] = 0;
            $d[$i + 1] = 0;
            continue;
        }
        $alfa = $d[$i] / $delta[$i];
        $beta = $d[$i + 1] / $delta[$i];

        // Verificacao: alfa^2 + beta^2 deve ser <= 9 para monotonicidade
        $radiusSquared = $alfa * $alfa + $beta * $beta;
        if ($radiusSquared > 9.0) {
            $tau = 3.0 / sqrt($radiusSquared);
            $d[$i] = $tau * $alfa * $delta[$i];
            $d[$i + 1] = $tau * $beta * $delta[$i];
        }
    }

    return $d;
}

/**
 * Interpola um ponto via PCHIP (Hermite cubico).
 *
 * @param float $xp        Hora a interpolar
 * @param array $x         Ancoras X (horas)
 * @param array $y         Ancoras Y (valores)
 * @param array $derivadas Derivadas monotonicas
 * @return float           Valor interpolado
 */
function interpolarPCHIP_Rede($xp, $x, $y, $derivadas)
{
    $n = count($x);

    // Clamping: fora do intervalo, usar valor da borda
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

/**
 * Fallback: interpolacao linear simples quando ha menos de 3 ancoras.
 * Mantida para compatibilidade quando PCHIP nao pode ser aplicado.
 *
 * @param array $dados Array[0..23] com valores ou null
 * @return array       Array[0..23] com estimativas ou null
 */
function calcularInterpolacaoLinearFallback($dados)
{
    $resultado = array_fill(0, 24, null);
    $MAX_GAP = 6;

    for ($h = 0; $h < 24; $h++) {
        if ($dados[$h] !== null)
            continue;

        // Encontrar hora valida anterior
        $hAntes = null;
        $vAntes = null;
        for ($a = $h - 1; $a >= 0; $a--) {
            if ($dados[$a] !== null) {
                $hAntes = $a;
                $vAntes = $dados[$a];
                break;
            }
        }
        // Encontrar hora valida posterior
        $hDepois = null;
        $vDepois = null;
        for ($d = $h + 1; $d < 24; $d++) {
            if ($dados[$d] !== null) {
                $hDepois = $d;
                $vDepois = $dados[$d];
                break;
            }
        }

        if ($hAntes !== null && $hDepois !== null && ($hDepois - $hAntes) <= $MAX_GAP) {
            $fator = ($h - $hAntes) / ($hDepois - $hAntes);
            $v = $vAntes + ($vDepois - $vAntes) * $fator;
            if ($v >= 0)
                $resultado[$h] = round($v, 2);
        } elseif ($hAntes !== null && ($h - $hAntes) <= $MAX_GAP) {
            $resultado[$h] = round($vAntes * (1 - ($h - $hAntes) * 0.02), 2);
        } elseif ($hDepois !== null && ($hDepois - $h) <= $MAX_GAP) {
            $resultado[$h] = round($vDepois * (1 - ($hDepois - $h) * 0.02), 2);
        }
    }
    return $resultado;
}


/**
 * MÉTODO 2: Tendência da Rede
 * 
 * Analisa como os OUTROS pontos da rede estão variando HOJE em relação
 * ao seu histórico recente (últimas 4 semanas, mesmo dia da semana).
 * 
 * Se os outros pontos estão, em média, 8% acima do normal, aplica +8%
 * sobre o histórico do ponto atual para estimar seu valor.
 * 
 * Lógica:
 *   1. Para cada outro ponto na rede, calcula: fator_variacao = valor_hoje / media_historica
 *   2. Faz a média ponderada dos fatores (peso = horas com dados)
 *   3. Aplica: estimativa = media_historica_ponto_atual × fator_medio_rede
 * 
 * Vantagem: não depende de operação entrada/saída; detecta contexto operacional
 * (ex: se a rede está operando acima do normal por demanda, o ponto também estará)
 *
 * @param PDO    $pdo              Conexão com banco
 * @param int    $cdPontoAtual     Código do ponto que estamos estimando
 * @param string $data             Data YYYY-MM-DD
 * @param array  $pontosRede       Informações de todos os pontos da rede
 * @param array  $dadosTodosPontos Dados horários de todos os pontos HOJE
 * @param string $coluna           Coluna de valor do ponto atual
 * @return array Array[0..23] com estimativa ou null
 */
function calcularTendenciaRede($pdo, $cdPontoAtual, $data, $pontosRede, $dadosTodosPontos, $coluna)
{
    $resultado = array_fill(0, 24, null);

    // Mapeamento de colunas por tipo de medidor
    $colunasPorTipo = [
        1 => 'VL_VAZAO_EFETIVA',
        2 => 'VL_VAZAO_EFETIVA',
        4 => 'VL_PRESSAO',
        6 => 'VL_RESERVATORIO',
        8 => 'VL_VAZAO_EFETIVA'
    ];

    // Datas históricas: últimas 4 semanas, mesmo dia da semana
    $datasHistoricas = [];
    for ($s = 1; $s <= 4; $s++) {
        $datasHistoricas[] = date('Y-m-d', strtotime($data . " -{$s} weeks"));
    }

    // -------------------------------------------------------
    // PASSO 1: Calcular média histórica por hora de CADA ponto da rede
    // -------------------------------------------------------
    // mediaHistorica[cdPonto][hora] = média das 4 semanas
    $mediaHistorica = [];

    foreach ($pontosRede as $cdP => $info) {
        $colP = $colunasPorTipo[$info['tipo_medidor']] ?? 'VL_VAZAO_EFETIVA';
        $historicoPorHora = array_fill(0, 24, []); // [hora] => [valores das semanas]

        foreach ($datasHistoricas as $dataHist) {
            $dadosHist = obterDadosHorariosPonto($pdo, $cdP, $dataHist, $colP, $info['tipo_medidor']);
            for ($h = 0; $h < 24; $h++) {
                if ($dadosHist[$h] !== null && $dadosHist[$h] > 0) {
                    $historicoPorHora[$h][] = $dadosHist[$h];
                }
            }
        }

        // Média histórica por hora
        $mediaHistorica[$cdP] = array_fill(0, 24, null);
        for ($h = 0; $h < 24; $h++) {
            if (count($historicoPorHora[$h]) >= 2) { // Mínimo 2 semanas
                $mediaHistorica[$cdP][$h] = array_sum($historicoPorHora[$h]) / count($historicoPorHora[$h]);
            }
        }
    }

    // Verificar se o ponto atual tem histórico suficiente
    $pontoAtualTemHistorico = false;
    for ($h = 0; $h < 24; $h++) {
        if ($mediaHistorica[$cdPontoAtual][$h] !== null) {
            $pontoAtualTemHistorico = true;
            break;
        }
    }
    if (!$pontoAtualTemHistorico) {
        return $resultado;
    }

    // -------------------------------------------------------
    // PASSO 2: Para cada hora, calcular fator de variação dos OUTROS pontos
    // -------------------------------------------------------
    for ($h = 0; $h < 24; $h++) {
        // O ponto atual precisa ter histórico nesta hora
        $mediaAtualHist = $mediaHistorica[$cdPontoAtual][$h] ?? null;
        if ($mediaAtualHist === null || $mediaAtualHist <= 0) {
            continue;
        }

        // Calcular fator de variação de cada outro ponto:
        // fator = valor_hoje / media_historica
        $fatoresVariacao = [];

        foreach ($pontosRede as $cdP => $info) {
            if ($cdP === $cdPontoAtual) {
                continue;
            }

            $valorHoje = $dadosTodosPontos[$cdP][$h] ?? null;
            $mediaHist = $mediaHistorica[$cdP][$h] ?? null;

            // Precisa ter valor hoje E histórico para calcular fator
            if ($valorHoje === null || $valorHoje <= 0 || $mediaHist === null || $mediaHist <= 0) {
                continue;
            }

            $fator = $valorHoje / $mediaHist;

            // Limitar fator entre 0.3 e 3.0 (variação máxima de -70% a +200%)
            // Evita distorção por pontos com problemas
            if ($fator >= 0.3 && $fator <= 3.0) {
                $fatoresVariacao[] = $fator;
            }
        }

        // Precisa de pelo menos 1 outro ponto com fator calculável
        if (empty($fatoresVariacao)) {
            continue;
        }

        // Fator médio da rede (mediana para robustez contra outliers)
        sort($fatoresVariacao);
        $n = count($fatoresVariacao);
        if ($n % 2 === 0) {
            $fatorMedio = ($fatoresVariacao[$n / 2 - 1] + $fatoresVariacao[$n / 2]) / 2;
        } else {
            $fatorMedio = $fatoresVariacao[floor($n / 2)];
        }

        // -------------------------------------------------------
        // PASSO 3: Aplicar fator no histórico do ponto atual
        // -------------------------------------------------------
        $valorEstimado = $mediaAtualHist * $fatorMedio;

        if ($valorEstimado > 0) {
            $resultado[$h] = round($valorEstimado, 2);
        }
    }

    return $resultado;
}


/**
 * MÉTODO 4: Mínimos Quadrados (Regressão Linear)
 * 
 * Usa os dados das últimas 6-8 semanas (mesmo dia da semana) para ajustar
 * uma reta de tendência por hora e projetar o valor de hoje.
 * 
 * Captura tendências graduais como:
 * - Desgaste de medidor (valores caindo lentamente)
 * - Aumento de demanda sazonal
 * - Deriva de calibração
 * 
 * Fórmula:
 *   y = a + b × x, onde:
 *     x = número da semana (0 = mais antiga, N = hoje)
 *     y = valor medido
 *     b = Σ((xi - x̄)(yi - ȳ)) / Σ((xi - x̄)²)
 *     a = ȳ - b × x̄
 * 
 * Projeção: valor_hoje = a + b × (N)
 * 
 * Requer mínimo 3 semanas históricas com dados para ser confiável.
 *
 * @param PDO    $pdo          Conexão com banco
 * @param int    $cdPonto      Código do ponto de medição
 * @param string $data         Data YYYY-MM-DD
 * @param string $coluna       Coluna de valor (VL_VAZAO_EFETIVA, etc.)
 * @param int    $tipoMedidor  Tipo do medidor (1,2,4,6,8)
 * @return array Array[0..23] com valor projetado ou null
 */
function calcularMinimosQuadrados($pdo, $cdPonto, $data, $coluna, $tipoMedidor)
{
    $resultado = array_fill(0, 24, null);
    $NUM_SEMANAS = 8; // Buscar até 8 semanas anteriores
    $MIN_SEMANAS = 3; // Mínimo de semanas com dados para regressão

    // Montar lista de datas históricas (mesmo dia da semana)
    // Ordenadas da mais antiga (x=0) para mais recente (x=N-1)
    $datasHistoricas = [];
    for ($s = $NUM_SEMANAS; $s >= 1; $s--) {
        $datasHistoricas[] = date('Y-m-d', strtotime($data . " -{$s} weeks"));
    }
    // x para "hoje" será count($datasHistoricas) = $NUM_SEMANAS
    $xHoje = count($datasHistoricas);

    // Coletar dados históricos por hora
    // dadosPorHora[hora] = [ [x, valor], [x, valor], ... ]
    $dadosPorHora = [];
    for ($h = 0; $h < 24; $h++) {
        $dadosPorHora[$h] = [];
    }

    foreach ($datasHistoricas as $x => $dataHist) {
        $dadosHist = obterDadosHorariosPonto($pdo, $cdPonto, $dataHist, $coluna, $tipoMedidor);

        for ($h = 0; $h < 24; $h++) {
            if ($dadosHist[$h] !== null && $dadosHist[$h] > 0) {
                $dadosPorHora[$h][] = ['x' => $x, 'y' => $dadosHist[$h]];
            }
        }
    }

    // Para cada hora, aplicar regressão linear
    for ($h = 0; $h < 24; $h++) {
        $pontos = $dadosPorHora[$h];
        $n = count($pontos);

        // Precisa de pelo menos MIN_SEMANAS pontos
        if ($n < $MIN_SEMANAS) {
            continue;
        }

        // Calcular médias
        $somaX = 0;
        $somaY = 0;
        foreach ($pontos as $p) {
            $somaX += $p['x'];
            $somaY += $p['y'];
        }
        $mediaX = $somaX / $n;
        $mediaY = $somaY / $n;

        // Calcular coeficientes da regressão (mínimos quadrados)
        // b = Σ((xi - x̄)(yi - ȳ)) / Σ((xi - x̄)²)
        $numerador = 0;
        $denominador = 0;
        foreach ($pontos as $p) {
            $dx = $p['x'] - $mediaX;
            $dy = $p['y'] - $mediaY;
            $numerador += $dx * $dy;
            $denominador += $dx * $dx;
        }

        // Se denominador é zero, todos os x são iguais (impossível para regressão)
        if (abs($denominador) < 0.0001) {
            // Sem tendência detectável, usar média
            $resultado[$h] = round($mediaY, 2);
            continue;
        }

        $b = $numerador / $denominador; // Inclinação (tendência por semana)
        $a = $mediaY - $b * $mediaX;    // Intercepto

        // Projetar valor para hoje (x = xHoje)
        $valorProjetado = $a + $b * $xHoje;

        // Validações de sanidade:
        // 1. Valor projetado deve ser positivo
        if ($valorProjetado <= 0) {
            // Tendência descendo muito, usar mínimo histórico
            $minHist = min(array_column($pontos, 'y'));
            $valorProjetado = $minHist * 0.8; // 80% do mínimo como piso
            if ($valorProjetado <= 0)
                continue;
        }

        // 2. Não pode desviar mais que 50% da média (limitar projeções extremas)
        $desvioPerc = abs($valorProjetado - $mediaY) / $mediaY;
        if ($desvioPerc > 0.5) {
            // Limitar: no máximo 50% acima ou abaixo da média
            $valorProjetado = $mediaY * (1 + 0.5 * ($valorProjetado > $mediaY ? 1 : -1));
        }

        $resultado[$h] = round($valorProjetado, 2);
    }

    return $resultado;
}


/**
 * MÉTODO 3: Proporção Histórica
 * 
 * Calcula a proporção média que o ponto representa na rede
 * nas últimas 4 semanas (mesmo dia da semana), e aplica essa
 * proporção ao total da rede na data atual.
 * 
 * Fórmula:
 *   proporção = média(valor_ponto / total_rede) nas 4 semanas
 *   valor_estimado = total_rede_hoje × proporção
 *
 * @param PDO    $pdo              Conexão com banco
 * @param int    $cdPontoAtual     Código do ponto
 * @param string $data             Data YYYY-MM-DD
 * @param array  $pontosRede       Informações dos pontos
 * @param array  $dadosTodosPontos Dados horários do dia atual
 * @param string $coluna           Coluna de valor do ponto atual
 * @return array Array[0..23] com estimativa ou null
 */
function calcularProporcaoHistorica($pdo, $cdPontoAtual, $data, $pontosRede, $dadosTodosPontos, $coluna)
{
    $resultado = array_fill(0, 24, null);

    // Determinar dia da semana para buscar mesmo dia nas semanas anteriores
    $diaSemana = date('w', strtotime($data)); // 0=dom, 6=sáb

    // Buscar proporção histórica do ponto nas últimas 8 semanas (mesmo dia da semana)
    // Selecionamos 8 para ter pelo menos 4 válidas
    $datasHistoricas = [];
    for ($s = 1; $s <= 8; $s++) {
        $dataHist = date('Y-m-d', strtotime($data . " -{$s} weeks"));
        $datasHistoricas[] = $dataHist;
    }

    // Coletar códigos dos outros pontos da rede (mesmo tipo = vazão)
    $outrosPontosCodigos = [];
    foreach ($pontosRede as $cdP => $info) {
        if ($cdP !== $cdPontoAtual) {
            $outrosPontosCodigos[] = $cdP;
        }
    }

    if (empty($outrosPontosCodigos)) {
        return $resultado;
    }

    // Proporção por hora: array[hora] => [proporções das semanas]
    $proporcoesPorHora = [];
    for ($h = 0; $h < 24; $h++) {
        $proporcoesPorHora[$h] = [];
    }

    // Para cada semana histórica, calcular a proporção do ponto
    foreach ($datasHistoricas as $dataHist) {
        // Valor do ponto atual em cada hora
        $dadosPontoHist = obterDadosHorariosPonto($pdo, $cdPontoAtual, $dataHist, $coluna, $pontosRede[$cdPontoAtual]['tipo_medidor']);

        // Valor total da rede (soma ponderada pela operação) em cada hora
        $totalRedePorHora = array_fill(0, 24, 0);
        $horasComDadosRede = array_fill(0, 24, 0);

        foreach ($pontosRede as $cdP => $info) {
            $colunaP = [
                1 => 'VL_VAZAO_EFETIVA',
                2 => 'VL_VAZAO_EFETIVA',
                4 => 'VL_PRESSAO',
                6 => 'VL_RESERVATORIO',
                8 => 'VL_VAZAO_EFETIVA'
            ][$info['tipo_medidor']] ?? 'VL_VAZAO_EFETIVA';

            $dadosPHist = obterDadosHorariosPonto($pdo, $cdP, $dataHist, $colunaP, $info['tipo_medidor']);

            for ($h = 0; $h < 24; $h++) {
                if ($dadosPHist[$h] !== null) {
                    $totalRedePorHora[$h] += abs($dadosPHist[$h]);
                    $horasComDadosRede[$h]++;
                }
            }
        }

        // Calcular proporção para cada hora
        for ($h = 0; $h < 24; $h++) {
            if ($dadosPontoHist[$h] !== null && $totalRedePorHora[$h] > 0) {
                $prop = abs($dadosPontoHist[$h]) / $totalRedePorHora[$h];
                // Proporção razoável: entre 0 e 1
                if ($prop > 0 && $prop <= 1) {
                    $proporcoesPorHora[$h][] = $prop;
                }
            }
        }
    }

    // Calcular total da rede HOJE (sem o ponto atual) para cada hora
    for ($h = 0; $h < 24; $h++) {
        // Precisa de pelo menos 2 semanas históricas para proporção confiável
        if (count($proporcoesPorHora[$h]) < 2) {
            continue;
        }

        // Proporção média
        $propMedia = array_sum($proporcoesPorHora[$h]) / count($proporcoesPorHora[$h]);

        // Total da rede hoje (exceto o ponto atual)
        $totalRedeHoje = 0;
        $pontosComDadosHoje = 0;

        foreach ($pontosRede as $cdP => $info) {
            if ($cdP === $cdPontoAtual) {
                continue;
            }
            $valor = $dadosTodosPontos[$cdP][$h] ?? null;
            if ($valor !== null) {
                $totalRedeHoje += abs($valor);
                $pontosComDadosHoje++;
            }
        }

        // Só estimar se há dados dos outros pontos hoje
        if ($pontosComDadosHoje === 0 || $totalRedeHoje <= 0) {
            continue;
        }

        // Fórmula: valor = total_outros / (1 - proporção) × proporção
        // Equivalente a: se ponto = 35% da rede, e outros = 65%, então:
        //   total_rede = total_outros / 0.65
        //   valor_ponto = total_rede × 0.35
        if ($propMedia < 1) {
            $totalRedeEstimado = $totalRedeHoje / (1 - $propMedia);
            $valorEstimado = $totalRedeEstimado * $propMedia;

            if ($valorEstimado > 0) {
                $resultado[$h] = round($valorEstimado, 2);
            }
        }
    }

    return $resultado;
}
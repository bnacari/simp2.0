<?php
/**
 * SIMP 2.0 - Fase A2: Endpoint de Tratamento em Lote
 *
 * CRUD para a tela de tratamento: listar pendencias, aprovar,
 * ajustar, ignorar, acoes em massa, estatisticas.
 *
 * Acoes:
 *   - listar:           Lista pendencias com filtros e paginacao
 *   - aprovar:          Aprova uma pendencia (aplica valor sugerido)
 *   - ajustar:          Ajusta uma pendencia (operador informa valor)
 *   - ignorar:          Ignora uma pendencia (justificativa obrigatoria)
 *   - aprovar_massa:    Aprova multiplas pendencias de uma vez
 *   - ignorar_massa:    Ignora multiplas pendencias de uma vez
 *   - estatisticas:     Resumo para cabecalho da tela
 *   - detalhe:          Detalhes de uma pendencia especifica
 *
 * Localização: html/bd/operacoes/tratamentoLote.php
 *
 * @author  Bruno - CESAN
 * @version 1.0 - Fase A2
 * @date    2026-02
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

/**
 * Retorna JSON limpo e encerra execucao.
 */
function retornarJSON_TL($data)
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
            'error' => 'Erro PHP: ' . $error['message']
        ], JSON_UNESCAPED_UNICODE);
    }
});

try {
    // ========================================
    // Conexao e autenticacao
    // ========================================
    @include_once __DIR__ . '/../conexao.php';
    @include_once __DIR__ . '/../includes/logHelper.php';

    if (!isset($pdoSIMP)) {
        retornarJSON_TL(['success' => false, 'error' => 'Conexao com banco nao estabelecida']);
    }

    // Receber dados
    $rawInput = file_get_contents('php://input');
    $dados = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $dados = $_GET;
    }

    $acao = $dados['acao'] ?? $_GET['acao'] ?? '';

    if (empty($acao)) {
        retornarJSON_TL([
            'success' => false,
            'error' => 'Parametro "acao" obrigatorio.'
        ]);
    }

    // Usuario logado
    $cdUsuario = intval($_SESSION['cd_usuario'] ?? $dados['cd_usuario'] ?? 0);

    // ========================================
    // Roteamento
    // ========================================
    switch ($acao) {

        // ----------------------------------------
        // LISTAR - Pendencias com filtros e paginacao
        // ----------------------------------------
        case 'listar':
            $resultado = listarPendencias($pdoSIMP, $dados);
            retornarJSON_TL($resultado);
            break;

        // ----------------------------------------
        // APROVAR - Aplica valor sugerido
        // ----------------------------------------
        case 'aprovar':
            $cdPendencia = intval($dados['cd_pendencia'] ?? 0);
            if (!$cdPendencia) {
                retornarJSON_TL(['success' => false, 'error' => 'cd_pendencia obrigatorio']);
            }
            $resultado = aplicarTratamento($pdoSIMP, $cdPendencia, 1, null, $cdUsuario, null);
            retornarJSON_TL($resultado);
            break;

        // ----------------------------------------
        // AJUSTAR - Operador informa valor
        // ----------------------------------------
        case 'ajustar':
            $cdPendencia = intval($dados['cd_pendencia'] ?? 0);
            $valorAjuste = isset($dados['valor']) ? floatval($dados['valor']) : null;
            if (!$cdPendencia || $valorAjuste === null) {
                retornarJSON_TL(['success' => false, 'error' => 'cd_pendencia e valor obrigatorios']);
            }
            $resultado = aplicarTratamento($pdoSIMP, $cdPendencia, 2, $valorAjuste, $cdUsuario, null);
            retornarJSON_TL($resultado);
            break;

        // ----------------------------------------
        // IGNORAR - Justificativa obrigatoria
        // ----------------------------------------
        case 'ignorar':
            $cdPendencia = intval($dados['cd_pendencia'] ?? 0);
            $justificativa = trim($dados['justificativa'] ?? '');
            if (!$cdPendencia || empty($justificativa)) {
                retornarJSON_TL(['success' => false, 'error' => 'cd_pendencia e justificativa obrigatorios']);
            }
            $resultado = aplicarTratamento($pdoSIMP, $cdPendencia, 3, null, $cdUsuario, $justificativa);
            retornarJSON_TL($resultado);
            break;

        // ----------------------------------------
        // APROVAR EM MASSA
        // ----------------------------------------
        case 'aprovar_massa':
            $ids = $dados['ids'] ?? [];
            if (empty($ids) || !is_array($ids)) {
                retornarJSON_TL(['success' => false, 'error' => 'Array "ids" obrigatorio']);
            }
            $resultado = aplicarTratamentoMassa($pdoSIMP, $ids, 1, $cdUsuario, null);
            retornarJSON_TL($resultado);
            break;

        // ----------------------------------------
        // IGNORAR EM MASSA
        // ----------------------------------------
        case 'ignorar_massa':
            $ids = $dados['ids'] ?? [];
            $justificativa = trim($dados['justificativa'] ?? '');
            if (empty($ids) || !is_array($ids) || empty($justificativa)) {
                retornarJSON_TL(['success' => false, 'error' => 'ids e justificativa obrigatorios']);
            }
            $resultado = aplicarTratamentoMassa($pdoSIMP, $ids, 3, $cdUsuario, $justificativa);
            retornarJSON_TL($resultado);
            break;

        // ----------------------------------------
        // ESTATISTICAS - Resumo para cabecalho
        // ----------------------------------------
        case 'estatisticas':
            $dataRef = $dados['data'] ?? null;
            $resultado = obterEstatisticas($pdoSIMP, $dataRef);
            retornarJSON_TL($resultado);
            break;

        // ----------------------------------------
        // DETALHE - Uma pendencia especifica
        // ----------------------------------------
        case 'detalhe':
            $cdPendencia = intval($dados['cd_pendencia'] ?? 0);
            if (!$cdPendencia) {
                retornarJSON_TL(['success' => false, 'error' => 'cd_pendencia obrigatorio']);
            }
            $resultado = obterDetalhe($pdoSIMP, $cdPendencia);
            retornarJSON_TL($resultado);
            break;

        default:
            retornarJSON_TL(['success' => false, 'error' => "Acao '$acao' nao reconhecida"]);
    }

} catch (Exception $e) {
    retornarJSON_TL(['success' => false, 'error' => $e->getMessage()]);
}


// ============================================================
// LISTAR PENDENCIAS
// ============================================================

/**
 * Lista pendencias com filtros, ordenacao e paginacao.
 *
 * Filtros disponiveis:
 *   - data:          Data de referencia (YYYY-MM-DD)
 *   - data_inicio:   Periodo - inicio
 *   - data_fim:      Periodo - fim
 *   - status:        0=Pendente, 1=Aprovada, 2=Ajustada, 3=Ignorada
 *   - classe:        1=Tecnica, 2=Operacional
 *   - tipo_anomalia: 1-8 (ID_TIPO_ANOMALIA)
 *   - tipo_medidor:  1,2,4,6,8
 *   - unidade:       CD_UNIDADE
 *   - confianca_min: Score minimo
 *   - busca:         Texto livre (nome do ponto)
 *   - pagina:        Pagina atual (default 1)
 *   - limite:        Registros por pagina (default 50)
 *   - ordenar:       Campo de ordenacao
 *   - direcao:       ASC ou DESC
 */
function listarPendencias(PDO $pdo, array $filtros): array
{
    // ---- Parametros de paginacao ----
    $pagina = max(1, intval($filtros['pagina'] ?? 1));
    $limite = min(200, max(10, intval($filtros['limite'] ?? 50)));
    $offset = ($pagina - 1) * $limite;

    // ---- Construir WHERE ----
    $where = [];
    $params = [];

    // Filtro de data
    if (!empty($filtros['data'])) {
        $where[] = "P.DT_REFERENCIA = :data";
        $params[':data'] = $filtros['data'];
    } elseif (!empty($filtros['data_inicio']) && !empty($filtros['data_fim'])) {
        $where[] = "P.DT_REFERENCIA BETWEEN :dt_ini AND :dt_fim";
        $params[':dt_ini'] = $filtros['data_inicio'];
        $params[':dt_fim'] = $filtros['data_fim'];
    }

    // Status (default: pendentes)
    if (isset($filtros['status']) && $filtros['status'] !== '' && $filtros['status'] !== 'todos') {
        $where[] = "P.ID_STATUS = :status";
        $params[':status'] = intval($filtros['status']);
    }

    // Classe de anomalia
    if (!empty($filtros['classe'])) {
        $where[] = "P.ID_CLASSE_ANOMALIA = :classe";
        $params[':classe'] = intval($filtros['classe']);
    }

    // Tipo de anomalia
    if (!empty($filtros['tipo_anomalia'])) {
        $where[] = "P.ID_TIPO_ANOMALIA = :tipo_anom";
        $params[':tipo_anom'] = intval($filtros['tipo_anomalia']);
    }

    // Tipo de medidor
    if (!empty($filtros['tipo_medidor'])) {
        $where[] = "P.ID_TIPO_MEDIDOR = :tipo_med";
        $params[':tipo_med'] = intval($filtros['tipo_medidor']);
    }

    // Unidade
    if (!empty($filtros['unidade'])) {
        $where[] = "L.CD_UNIDADE = :unidade";
        $params[':unidade'] = $filtros['unidade'];
    }

    // Confianca minima
    if (!empty($filtros['confianca_min'])) {
        $where[] = "P.VL_CONFIANCA >= :conf_min";
        $params[':conf_min'] = floatval($filtros['confianca_min']);
    }

    // Busca textual (nome, cd_ponto, tags)
    if (!empty($filtros['busca'])) {
        $termo = '%' . $filtros['busca'] . '%';
        $where[] = "(PM.DS_NOME LIKE :busca 
                     OR CAST(PM.CD_PONTO_MEDICAO AS VARCHAR) LIKE :busca2
                     OR PM.DS_TAG_VAZAO LIKE :busca3
                     OR PM.DS_TAG_PRESSAO LIKE :busca4
                     OR PM.DS_TAG_RESERVATORIO LIKE :busca5)";
        $params[':busca'] = $termo;
        $params[':busca2'] = $termo;
        $params[':busca3'] = $termo;
        $params[':busca4'] = $termo;
        $params[':busca5'] = $termo;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // ---- Ordenacao ----
    $ordenacaoPermitida = [
        'confianca' => 'P.VL_CONFIANCA',
        'data' => 'P.DT_REFERENCIA, P.NR_HORA',
        'hora' => 'P.NR_HORA',
        'severidade' => "CASE P.DS_SEVERIDADE WHEN 'critica' THEN 1 WHEN 'alta' THEN 2 WHEN 'media' THEN 3 ELSE 4 END",
        'tipo' => 'P.ID_TIPO_ANOMALIA',
        'prioridade' => "CASE P.ID_TIPO_MEDIDOR WHEN 6 THEN 1 WHEN 1 THEN 2 WHEN 2 THEN 3 WHEN 4 THEN 4 ELSE 9 END",
        'ponto' => 'PM.DS_NOME',
        'status' => 'P.ID_STATUS',
        'geracao' => 'P.DT_GERACAO'
    ];

    // Multi-coluna: campos e direcoes separados por virgula
    $camposOrdenar = explode(',', $filtros['ordenar'] ?? 'prioridade');
    $direcoesOrdenar = explode(',', $filtros['direcao'] ?? 'DESC');

    $orderParts = [];
    foreach ($camposOrdenar as $i => $campo) {
        $campo = trim($campo);
        $dir = strtoupper(trim($direcoesOrdenar[$i] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        if (isset($ordenacaoPermitida[$campo])) {
            $orderParts[] = $ordenacaoPermitida[$campo] . ' ' . $dir;
        }
    }
    if (empty($orderParts)) {
        $orderParts[] = $ordenacaoPermitida['prioridade'] . ' DESC';
    }
    $orderByFinal = implode(', ', $orderParts);

    // ---- Contar total ----
    $sqlCount = "
        SELECT COUNT(*) AS TOTAL
        FROM SIMP.dbo.IA_PENDENCIA_TRATAMENTO P
        INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = P.CD_PONTO_MEDICAO
        LEFT JOIN SIMP.dbo.LOCALIDADE L ON L.CD_CHAVE = PM.CD_LOCALIDADE
        $whereClause
    ";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = intval($stmtCount->fetchColumn());

    // ---- Buscar registros ----
    $sql = "
        SELECT 
            P.CD_CHAVE,
            P.CD_PONTO_MEDICAO,
            P.DT_REFERENCIA,
            P.NR_HORA,
            P.ID_TIPO_ANOMALIA,
            P.ID_CLASSE_ANOMALIA,
            P.DS_SEVERIDADE,
            P.VL_REAL,
            P.VL_SUGERIDO,
            P.VL_MEDIA_HISTORICA,
            P.VL_PREDICAO_XGBOOST,
            P.VL_CONFIANCA,
            P.VL_ZSCORE,
            P.DS_DESCRICAO,
            P.DS_METODO_DETECCAO,
            P.ID_STATUS,
            P.VL_VALOR_APLICADO,
            P.DT_ACAO,
            P.DT_GERACAO,
            P.ID_TIPO_MEDIDOR,
            P.QTD_VIZINHOS_ANOMALOS,
            P.OP_EVENTO_PROPAGADO,

            -- Ponto
            PM.DS_NOME AS DS_PONTO_NOME,

            -- Localidade / Unidade
            L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
            L.DS_NOME AS DS_LOCALIDADE,
            L.CD_UNIDADE,
            U.DS_NOME AS DS_UNIDADE,

            -- Codigo formatado
            ISNULL(CAST(L.CD_LOCALIDADE AS VARCHAR), '000') + '-' +
            RIGHT('000000' + CAST(PM.CD_PONTO_MEDICAO AS VARCHAR), 6) + '-' +
            CASE P.ID_TIPO_MEDIDOR
                WHEN 1 THEN 'M' WHEN 2 THEN 'E' WHEN 4 THEN 'P' WHEN 6 THEN 'R' WHEN 8 THEN 'H' ELSE 'X'
            END + '-' +
            ISNULL(CAST(L.CD_UNIDADE AS VARCHAR), '00') AS DS_CODIGO_FORMATADO,

            -- Descricoes auxiliares
            CASE P.ID_TIPO_ANOMALIA
                WHEN 1 THEN 'Valor zerado' WHEN 2 THEN 'Sensor travado' WHEN 3 THEN 'Spike'
                WHEN 4 THEN 'Desvio estatistico' WHEN 5 THEN 'Padrao incomum' WHEN 6 THEN 'Desvio modelo'
                WHEN 7 THEN 'Gap comunicacao' WHEN 8 THEN 'Fora de faixa' ELSE 'Outro'
            END AS DS_TIPO_ANOMALIA,

            CASE P.ID_CLASSE_ANOMALIA
                WHEN 1 THEN 'Correcao tecnica' WHEN 2 THEN 'Evento operacional' ELSE 'N/A'
            END AS DS_CLASSE_ANOMALIA,

            CASE
                WHEN P.VL_CONFIANCA >= 0.95 THEN 'alta'
                WHEN P.VL_CONFIANCA >= 0.85 THEN 'confiavel'
                WHEN P.VL_CONFIANCA >= 0.70 THEN 'atencao'
                ELSE 'baixa'
            END AS DS_BADGE_CONFIANCA,

            -- Prioridade hidraulica
            CASE P.ID_TIPO_MEDIDOR
                WHEN 6 THEN 1 WHEN 1 THEN 2 WHEN 2 THEN 3 WHEN 4 THEN 4 ELSE 9
            END AS NR_PRIORIDADE_HIDRAULICA,

            -- Hora formatada
            RIGHT('0' + CAST(P.NR_HORA AS VARCHAR), 2) + ':00' AS DS_HORA_FORMATADA,

            -- Nome do nodo no grafo
            EN.DS_NOME AS DS_NODO_NOME,

            -- Tags do ponto (para busca)
            PM.DS_TAG_VAZAO,
            PM.DS_TAG_PRESSAO,
            PM.DS_TAG_RESERVATORIO,

            -- Reservado (modelo verificado via TensorFlow no frontend)
            0 AS OP_TEM_MODELO

        FROM SIMP.dbo.IA_PENDENCIA_TRATAMENTO P
        INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = P.CD_PONTO_MEDICAO
        LEFT JOIN SIMP.dbo.LOCALIDADE L ON L.CD_CHAVE = PM.CD_LOCALIDADE
        LEFT JOIN SIMP.dbo.UNIDADE U ON U.CD_UNIDADE = L.CD_UNIDADE
        LEFT JOIN SIMP.dbo.ENTIDADE_NODO EN ON EN.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO AND EN.OP_ATIVO = 1
        $whereClause
        ORDER BY $orderByFinal, P.CD_CHAVE DESC
        OFFSET $offset ROWS FETCH NEXT $limite ROWS ONLY
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pendencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'success' => true,
        'pendencias' => $pendencias,
        'total' => $total,
        'pagina' => $pagina,
        'limite' => $limite,
        'paginas' => ceil($total / $limite)
    ];
}


// ============================================================
// APLICAR TRATAMENTO (INDIVIDUAL)
// ============================================================

/**
 * Aplica tratamento em uma pendencia individual.
 * Chama a SP_APLICAR_TRATAMENTO do banco.
 */
function aplicarTratamento(
    PDO $pdo,
    int $cdPendencia,
    int $idAcao,
    ?float $valorAplicado,
    int $cdUsuario,
    ?string $justificativa
): array {

    try {
        $sql = "EXEC SP_APLICAR_TRATAMENTO 
                    @CD_PENDENCIA = ?, 
                    @ID_ACAO = ?, 
                    @VL_VALOR_APLICADO = ?, 
                    @CD_USUARIO = ?, 
                    @DS_JUSTIFICATIVA = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $cdPendencia,
            $idAcao,
            $valorAplicado,
            $cdUsuario,
            $justificativa
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Log (isolado)
        try {
            if (function_exists('registrarLog')) {
                $acaoNome = ['', 'APROVAR', 'AJUSTAR', 'IGNORAR'][$idAcao] ?? 'DESCONHECIDA';
                registrarLog(
                    $pdo,
                    'TRATAMENTO_LOTE',
                    $acaoNome,
                    "Pendencia #$cdPendencia " . strtolower($acaoNome) . ($valorAplicado !== null ? " (valor: $valorAplicado)" : ''),
                    'SUCESSO',
                    $cdUsuario
                );
            }
        } catch (Exception $logEx) {
            // Silencioso
        }

        return [
            'success' => true,
            'message' => 'Tratamento aplicado com sucesso',
            'resultado' => $result
        ];

    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}


// ============================================================
// APLICAR TRATAMENTO EM MASSA
// ============================================================

/**
 * Aplica tratamento em multiplas pendencias.
 */
function aplicarTratamentoMassa(
    PDO $pdo,
    array $ids,
    int $idAcao,
    int $cdUsuario,
    ?string $justificativa
): array {

    $sucesso = 0;
    $erros = 0;
    $detalhes = [];

    foreach ($ids as $id) {
        $cdPendencia = intval($id);
        if ($cdPendencia <= 0)
            continue;

        $res = aplicarTratamento($pdo, $cdPendencia, $idAcao, null, $cdUsuario, $justificativa);

        if ($res['success']) {
            $sucesso++;
        } else {
            $erros++;
            $detalhes[] = "Pendencia #$cdPendencia: " . ($res['error'] ?? 'Erro desconhecido');
        }
    }

    return [
        'success' => true,
        'total' => count($ids),
        'sucesso' => $sucesso,
        'erros' => $erros,
        'detalhes' => $detalhes,
        'message' => "$sucesso tratamento(s) aplicado(s)" . ($erros > 0 ? ", $erros erro(s)" : '')
    ];
}


// ============================================================
// ESTATISTICAS
// ============================================================

/**
 * Retorna estatisticas para o cabecalho da tela de tratamento.
 * Se data nao informada, busca periodo com pendencias.
 */
function obterEstatisticas(PDO $pdo, ?string $dataRef): array
{
    // Se nao informou data, buscar ultima data com pendencias
    if (empty($dataRef)) {
        $stmt = $pdo->query("
            SELECT TOP 1 DT_REFERENCIA 
            FROM SIMP.dbo.IA_PENDENCIA_TRATAMENTO 
            ORDER BY DT_REFERENCIA DESC
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $dataRef = $row ? $row['DT_REFERENCIA'] : date('Y-m-d', strtotime('-1 day'));
    }

    // Resumo geral
    $sql = "
        SELECT 
            COUNT(*) AS TOTAL,
            SUM(CASE WHEN ID_STATUS = 0 THEN 1 ELSE 0 END) AS PENDENTES,
            SUM(CASE WHEN ID_STATUS IN (1,2) THEN 1 ELSE 0 END) AS TRATADAS,
            SUM(CASE WHEN ID_STATUS = 3 THEN 1 ELSE 0 END) AS IGNORADAS,
            COUNT(DISTINCT CD_PONTO_MEDICAO) AS PONTOS_AFETADOS,
            SUM(CASE WHEN ID_CLASSE_ANOMALIA = 1 AND ID_STATUS = 0 THEN 1 ELSE 0 END) AS TECNICAS_PENDENTES,
            SUM(CASE WHEN ID_CLASSE_ANOMALIA = 2 AND ID_STATUS = 0 THEN 1 ELSE 0 END) AS OPERACIONAIS_PENDENTES,
            SUM(CASE WHEN VL_CONFIANCA >= 0.95 AND ID_STATUS = 0 THEN 1 ELSE 0 END) AS ALTA_CONFIANCA_PENDENTES,
            CAST(AVG(CASE WHEN ID_STATUS = 0 THEN VL_CONFIANCA END) AS DECIMAL(5,4)) AS CONFIANCA_MEDIA_PENDENTES
        FROM SIMP.dbo.IA_PENDENCIA_TRATAMENTO
        WHERE DT_REFERENCIA = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dataRef]);
    $resumo = $stmt->fetch(PDO::FETCH_ASSOC);

    // Data do ultimo batch executado
    $sqlBatch = "
        SELECT TOP 1 DT_GERACAO 
        FROM SIMP.dbo.IA_PENDENCIA_TRATAMENTO 
        WHERE DS_ORIGEM = 'BATCH'
        ORDER BY DT_GERACAO DESC
    ";
    $stmtBatch = $pdo->query($sqlBatch);
    $rowBatch = $stmtBatch->fetch(PDO::FETCH_ASSOC);
    $dtUltimoBatch = $rowBatch ? $rowBatch['DT_GERACAO'] : null;

    // Por tipo de medidor
    $sqlTipoMed = "
        SELECT 
            ID_TIPO_MEDIDOR,
            CASE ID_TIPO_MEDIDOR
                WHEN 1 THEN 'Macromedidor' WHEN 2 THEN 'Pitometrica' WHEN 4 THEN 'Pressao'
                WHEN 6 THEN 'Reservatorio' WHEN 8 THEN 'Hidrometro' ELSE 'Outro'
            END AS DS_TIPO,
            COUNT(*) AS TOTAL,
            SUM(CASE WHEN ID_STATUS = 0 THEN 1 ELSE 0 END) AS PENDENTES
        FROM SIMP.dbo.IA_PENDENCIA_TRATAMENTO
        WHERE DT_REFERENCIA = ?
        GROUP BY ID_TIPO_MEDIDOR
        ORDER BY CASE ID_TIPO_MEDIDOR WHEN 6 THEN 1 WHEN 1 THEN 2 WHEN 2 THEN 3 WHEN 4 THEN 4 ELSE 9 END
    ";
    $stmtTipoMed = $pdo->prepare($sqlTipoMed);
    $stmtTipoMed->execute([$dataRef]);
    $porTipoMedidor = $stmtTipoMed->fetchAll(PDO::FETCH_ASSOC);

    // Por severidade
    $sqlSev = "
        SELECT 
            DS_SEVERIDADE,
            COUNT(*) AS TOTAL,
            SUM(CASE WHEN ID_STATUS = 0 THEN 1 ELSE 0 END) AS PENDENTES
        FROM SIMP.dbo.IA_PENDENCIA_TRATAMENTO
        WHERE DT_REFERENCIA = ?
        GROUP BY DS_SEVERIDADE
        ORDER BY CASE DS_SEVERIDADE WHEN 'critica' THEN 1 WHEN 'alta' THEN 2 WHEN 'media' THEN 3 ELSE 4 END
    ";
    $stmtSev = $pdo->prepare($sqlSev);
    $stmtSev->execute([$dataRef]);
    $porSeveridade = $stmtSev->fetchAll(PDO::FETCH_ASSOC);

    // Datas disponiveis (ultimas 30 com pendencias)
    $sqlDatas = "
        SELECT DISTINCT TOP 30 DT_REFERENCIA, COUNT(*) AS QTD
        FROM SIMP.dbo.IA_PENDENCIA_TRATAMENTO
        GROUP BY DT_REFERENCIA
        ORDER BY DT_REFERENCIA DESC
    ";
    $stmtDatas = $pdo->query($sqlDatas);
    $datasDisponiveis = $stmtDatas->fetchAll(PDO::FETCH_ASSOC);

    return [
        'success' => true,
        'data' => $dataRef,
        'resumo' => $resumo,
        'por_tipo_medidor' => $porTipoMedidor,
        'por_severidade' => $porSeveridade,
        'datas_disponiveis' => $datasDisponiveis,
        'dt_ultimo_batch'   => $dtUltimoBatch
    ];
}


// ============================================================
// DETALHE
// ============================================================

/**
 * Retorna detalhes completos de uma pendencia.
 * Inclui scores individuais, contexto topologico e historico.
 */
function obterDetalhe(PDO $pdo, int $cdPendencia): array
{
    // Dados da pendencia (via view)
    $sql = "
        SELECT *
        FROM SIMP.dbo.VW_PENDENCIA_TRATAMENTO
        WHERE CD_CHAVE = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cdPendencia]);
    $pendencia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pendencia) {
        return ['success' => false, 'error' => 'Pendencia nao encontrada'];
    }

    // Scores individuais
    $sqlScores = "
        SELECT VL_SCORE_ESTATISTICO, VL_SCORE_MODELO, VL_SCORE_TOPOLOGICO,
               VL_SCORE_HISTORICO, VL_SCORE_PADRAO
        FROM SIMP.dbo.IA_PENDENCIA_TRATAMENTO
        WHERE CD_CHAVE = ?
    ";
    $stmtScores = $pdo->prepare($sqlScores);
    $stmtScores->execute([$cdPendencia]);
    $scores = $stmtScores->fetch(PDO::FETCH_ASSOC);

    // Outras pendencias do mesmo ponto no mesmo dia
    $sqlIrmas = "
        SELECT NR_HORA, ID_TIPO_ANOMALIA, DS_SEVERIDADE, VL_CONFIANCA, ID_STATUS,
               RIGHT('0' + CAST(NR_HORA AS VARCHAR), 2) + ':00' AS DS_HORA_FORMATADA
        FROM SIMP.dbo.IA_PENDENCIA_TRATAMENTO
        WHERE CD_PONTO_MEDICAO = ? AND DT_REFERENCIA = ? AND CD_CHAVE != ?
        ORDER BY NR_HORA
    ";
    $stmtIrmas = $pdo->prepare($sqlIrmas);
    $stmtIrmas->execute([
        $pendencia['CD_PONTO_MEDICAO'],
        $pendencia['DT_REFERENCIA'],
        $cdPendencia
    ]);
    $outrasHoras = $stmtIrmas->fetchAll(PDO::FETCH_ASSOC);

    return [
        'success' => true,
        'pendencia' => $pendencia,
        'scores' => $scores,
        'outras_horas' => $outrasHoras
    ];
}
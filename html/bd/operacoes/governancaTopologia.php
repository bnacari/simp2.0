<?php
/**
 * SIMP 2.0 — Fase A1: Governanca e Versionamento de Topologia
 * 
 * Endpoint para gerenciar versoes de topologia e vinculo com modelos ML.
 * Chamado automaticamente ao salvar nó/conexao no flowchart,
 * e pela tela de modelos para exibir status/alertas.
 * 
 * Acoes:
 *   - gerar_snapshot       : Calcula hash SHA-256 da topologia e grava versao se mudou
 *   - verificar_status     : Retorna modelos desatualizados (alimenta banner de alerta)
 *   - historico_versoes    : Timeline de versoes da topologia (para flowchart)
 *   - registrar_modelo     : Registra novo modelo treinado vinculado a versao atual
 *   - invalidar_modelos    : Invalida modelos de um ponto/sistema manualmente
 *   - resumo_topologia     : Resumo da topologia atual (contadores, ultima versao)
 *   - config_sla           : Consultar/atualizar SLA de retreino
 * 
 * Caminho: html/bd/operacoes/governancaTopologia.php
 * 
 * @author Bruno - CESAN
 * @version 1.0
 * @date 2026-02
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

/**
 * Retorna JSON limpo e encerra execucao.
 * @param array $data Dados para serializar
 */
function retornarJSON_GOV($data)
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

    if (!isset($pdoSIMP)) {
        retornarJSON_GOV(['success' => false, 'error' => 'Conexao com banco nao estabelecida']);
    }

    // Receber dados (POST JSON ou GET)
    $rawInput = file_get_contents('php://input');
    $dados = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $dados = $_GET;
    }

    $acao = $dados['acao'] ?? $_GET['acao'] ?? '';

    if (empty($acao)) {
        retornarJSON_GOV([
            'success' => false,
            'error' => 'Parametro "acao" obrigatorio. Valores: gerar_snapshot, verificar_status, historico_versoes, registrar_modelo, invalidar_modelos, resumo_topologia, config_sla'
        ]);
    }

    // ========================================
    // Roteamento por acao
    // ========================================
    switch ($acao) {

        // ----------------------------------------
        // GERAR_SNAPSHOT — Calcular hash e gravar versao se mudou
        // Chamado automaticamente ao salvar nodo/conexao
        // ----------------------------------------
        case 'gerar_snapshot':
            $cdSistema = isset($dados['cd_sistema']) ? intval($dados['cd_sistema']) : null;
            $cdUsuario = isset($dados['cd_usuario']) ? intval($dados['cd_usuario']) : obterUsuarioSessao();
            $descricao = isset($dados['descricao']) ? trim($dados['descricao']) : null;

            $resultado = gerarSnapshotTopologia($pdoSIMP, $cdSistema, $cdUsuario, $descricao);
            retornarJSON_GOV($resultado);
            break;

        // ----------------------------------------
        // VERIFICAR_STATUS — Modelos desatualizados (para banner)
        // ----------------------------------------
        case 'verificar_status':
            $resultado = verificarStatusModelos($pdoSIMP);
            retornarJSON_GOV($resultado);
            break;

        // ----------------------------------------
        // HISTORICO_VERSOES — Timeline de versoes
        // ----------------------------------------
        case 'historico_versoes':
            $cdSistema = isset($dados['cd_sistema']) ? intval($dados['cd_sistema']) : null;
            $limite = isset($dados['limite']) ? intval($dados['limite']) : 20;
            $resultado = obterHistoricoVersoes($pdoSIMP, $cdSistema, $limite);
            retornarJSON_GOV($resultado);
            break;

        // ----------------------------------------
        // REGISTRAR_MODELO — Vincular modelo treinado a versao atual
        // ----------------------------------------
        case 'registrar_modelo':
            $resultado = registrarModelo($pdoSIMP, $dados);
            retornarJSON_GOV($resultado);
            break;

        // ----------------------------------------
        // INVALIDAR_MODELOS — Invalidar modelos manualmente
        // ----------------------------------------
        case 'invalidar_modelos':
            session_start();
            @include_once __DIR__ . '/../verificarAuth.php';
            if (function_exists('podeEditarTela') && !podeEditarTela('Treinamento IA')) {
                retornarJSON_GOV(['success' => false, 'error' => 'Permissao negada.']);
            }

            $cdPonto = isset($dados['cd_ponto']) ? intval($dados['cd_ponto']) : null;
            $motivo = isset($dados['motivo']) ? trim($dados['motivo']) : 'Invalidacao manual pelo operador';

            $resultado = invalidarModelos($pdoSIMP, $cdPonto, $motivo);
            retornarJSON_GOV($resultado);
            break;

        // ----------------------------------------
        // RESUMO_TOPOLOGIA — Contadores e ultima versao
        // ----------------------------------------
        case 'resumo_topologia':
            $resultado = obterResumoTopologia($pdoSIMP);
            retornarJSON_GOV($resultado);
            break;

        // ----------------------------------------
        // CONFIG_SLA — Consultar/atualizar SLA de retreino
        // ----------------------------------------
        case 'config_sla':
            $cdPonto = isset($dados['cd_ponto']) ? intval($dados['cd_ponto']) : null;
            $novoDias = isset($dados['dias']) ? intval($dados['dias']) : null;

            if ($novoDias !== null && $novoDias > 0) {
                // Atualizar SLA
                $resultado = atualizarSLA($pdoSIMP, $cdPonto, $novoDias);
            } else {
                // Consultar SLA atual
                $resultado = consultarSLA($pdoSIMP, $cdPonto);
            }
            retornarJSON_GOV($resultado);
            break;

        default:
            retornarJSON_GOV(['success' => false, 'error' => "Acao desconhecida: $acao"]);
    }

} catch (Exception $e) {
    retornarJSON_GOV(['success' => false, 'error' => $e->getMessage()]);
}


// ============================================
// FUNCOES AUXILIARES
// ============================================

/**
 * Obtem CD do usuario da sessao (se disponivel).
 * @return int|null
 */
function obterUsuarioSessao()
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    return isset($_SESSION['cd_usuario']) ? intval($_SESSION['cd_usuario']) : null;
}


// ============================================
// FUNCAO: GERAR SNAPSHOT DA TOPOLOGIA
// ============================================

/**
 * Calcula hash SHA-256 da topologia atual (nos + conexoes).
 * Se hash diferente do ultimo, grava nova versao em VERSAO_TOPOLOGIA
 * e detecta diff (nos adicionados/removidos).
 * 
 * @param PDO      $pdo         Conexao PDO
 * @param int|null $cdSistema   Sistema de abastecimento (NULL = global)
 * @param int|null $cdUsuario   Usuario que disparou a acao
 * @param string|null $descricao Descricao da mudanca
 * @return array Resultado com success, nova_versao, hash, etc.
 */
function gerarSnapshotTopologia($pdo, $cdSistema = null, $cdUsuario = null, $descricao = null)
{
    try {
        // ========================================
        // 1. Buscar nos ativos
        // ========================================
        $sqlNos = "
            SELECT 
                N.CD_CHAVE,
                N.CD_ENTIDADE_NIVEL,
                N.CD_PONTO_MEDICAO,
                N.OP_ATIVO
            FROM SIMP.dbo.ENTIDADE_NODO N
            WHERE N.OP_ATIVO = 1
            ORDER BY N.CD_CHAVE
        ";
        $stmtNos = $pdo->query($sqlNos);
        $nos = $stmtNos->fetchAll(PDO::FETCH_ASSOC);

        // ========================================
        // 2. Buscar conexoes ativas
        // ========================================
        $sqlCx = "
            SELECT 
                C.CD_CHAVE,
                C.CD_NODO_ORIGEM,
                C.CD_NODO_DESTINO,
                C.OP_ATIVO
            FROM SIMP.dbo.ENTIDADE_NODO_CONEXAO C
            WHERE C.OP_ATIVO = 1
            ORDER BY C.CD_CHAVE
        ";
        $stmtCx = $pdo->query($sqlCx);
        $conexoes = $stmtCx->fetchAll(PDO::FETCH_ASSOC);

        // ========================================
        // 3. Montar string canonica para hash
        //    Formato deterministico: N:CD|NIVEL|PONTO|ATIVO;C:ORIG|DEST|ATIVO;
        // ========================================
        $topoString = '';

        foreach ($nos as $n) {
            $ponto = $n['CD_PONTO_MEDICAO'] ?? 'NULL';
            $topoString .= "N:{$n['CD_CHAVE']}|{$n['CD_ENTIDADE_NIVEL']}|{$ponto}|{$n['OP_ATIVO']};";
        }

        foreach ($conexoes as $c) {
            $topoString .= "C:{$c['CD_NODO_ORIGEM']}|{$c['CD_NODO_DESTINO']}|{$c['OP_ATIVO']};";
        }

        // ========================================
        // 4. Calcular SHA-256
        // ========================================
        $hash = strtoupper(hash('sha256', $topoString));

        // ========================================
        // 5. Buscar ultimo hash do mesmo sistema
        // ========================================
        $sqlUltimo = "
            SELECT TOP 1 
                CD_CHAVE,
                DS_HASH_TOPOLOGIA,
                QTD_NOS_ATIVOS,
                QTD_CONEXOES_ATIVAS,
                QTD_NOS_COM_PONTO
            FROM SIMP.dbo.VERSAO_TOPOLOGIA
            WHERE ISNULL(CD_SISTEMA, 0) = :cdSistema
            ORDER BY DT_CADASTRO DESC
        ";
        $stmtUltimo = $pdo->prepare($sqlUltimo);
        $stmtUltimo->execute([':cdSistema' => intval($cdSistema ?? 0)]);
        $ultimaVersao = $stmtUltimo->fetch(PDO::FETCH_ASSOC);

        $ultimoHash = $ultimaVersao ? $ultimaVersao['DS_HASH_TOPOLOGIA'] : null;

        // ========================================
        // 6. Se hash mudou, gravar nova versao
        // ========================================
        if ($ultimoHash === null || $hash !== $ultimoHash) {

            // Contadores
            $qtdNos = count($nos);
            $qtdConexoes = count($conexoes);
            $qtdComPonto = 0;
            foreach ($nos as $n) {
                if (!empty($n['CD_PONTO_MEDICAO'])) {
                    $qtdComPonto++;
                }
            }

            // Calcular diff (se havia versao anterior)
            $diffJson = null;
            if ($ultimaVersao) {
                $diff = [
                    'nos_antes' => intval($ultimaVersao['QTD_NOS_ATIVOS']),
                    'nos_depois' => $qtdNos,
                    'conexoes_antes' => intval($ultimaVersao['QTD_CONEXOES_ATIVAS']),
                    'conexoes_depois' => $qtdConexoes,
                    'pontos_antes' => intval($ultimaVersao['QTD_NOS_COM_PONTO']),
                    'pontos_depois' => $qtdComPonto
                ];
                $diffJson = json_encode($diff, JSON_UNESCAPED_UNICODE);
            }

            // Inserir nova versao
            $sqlInsert = "
                INSERT INTO SIMP.dbo.VERSAO_TOPOLOGIA (
                    DS_HASH_TOPOLOGIA,
                    CD_SISTEMA,
                    QTD_NOS_ATIVOS,
                    QTD_CONEXOES_ATIVAS,
                    QTD_NOS_COM_PONTO,
                    DS_DESCRICAO,
                    DS_DIFF_JSON,
                    CD_USUARIO,
                    DT_CADASTRO
                ) VALUES (
                    :hash,
                    :cdSistema,
                    :qtdNos,
                    :qtdConexoes,
                    :qtdComPonto,
                    :descricao,
                    :diffJson,
                    :cdUsuario,
                    GETDATE()
                )
            ";
            $stmtInsert = $pdo->prepare($sqlInsert);
            $stmtInsert->execute([
                ':hash' => $hash,
                ':cdSistema' => $cdSistema,
                ':qtdNos' => $qtdNos,
                ':qtdConexoes' => $qtdConexoes,
                ':qtdComPonto' => $qtdComPonto,
                ':descricao' => $descricao ?? 'Snapshot automatico - topologia alterada',
                ':diffJson' => $diffJson,
                ':cdUsuario' => $cdUsuario
            ]);

            // Recuperar ID da nova versao
            $stmtId = $pdo->query("SELECT SCOPE_IDENTITY() AS ID");
            $cdNovaVersao = intval($stmtId->fetch(PDO::FETCH_ASSOC)['ID']);

            // Log (isolado)
            try {
                @include_once __DIR__ . '/../logHelper.php';
                if (function_exists('registrarLog')) {
                    registrarLog(
                        'Treinamento IA',
                        'INSERT',
                        "Nova versao topologia #{$cdNovaVersao} - Hash: " . substr($hash, 0, 16) . "... - Nos: {$qtdNos}, Conexoes: {$qtdConexoes}",
                        ['cd_versao' => $cdNovaVersao, 'hash' => $hash]
                    );
                }
            } catch (Exception $logEx) {
                // Silencioso
            }

            return [
                'success' => true,
                'nova_versao' => true,
                'cd_versao' => $cdNovaVersao,
                'hash' => $hash,
                'qtd_nos' => $qtdNos,
                'qtd_conexoes' => $qtdConexoes,
                'qtd_com_ponto' => $qtdComPonto,
                'diff' => $diffJson ? json_decode($diffJson, true) : null,
                'message' => 'Nova versao de topologia registrada'
            ];

        } else {
            // Hash igual — topologia nao mudou
            return [
                'success' => true,
                'nova_versao' => false,
                'hash' => $hash,
                'cd_versao' => intval($ultimaVersao['CD_CHAVE']),
                'message' => 'Topologia inalterada'
            ];
        }

    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Erro ao gerar snapshot: ' . $e->getMessage()];
    }
}


// ============================================
// FUNCAO: VERIFICAR STATUS DOS MODELOS
// ============================================

/**
 * Retorna modelos que precisam de retreino (topologia alterada ou SLA vencido).
 * Alimenta o banner de alerta na tela de modelos ML.
 * 
 * @param PDO $pdo Conexao PDO
 * @return array Lista de modelos desatualizados + contadores
 */
function verificarStatusModelos($pdo)
{
    try {
        // Verificar se a view existe
        $sqlCheck = "SELECT 1 FROM sys.views WHERE name = 'VW_MODELO_STATUS'";
        $stmtCheck = $pdo->query($sqlCheck);
        $viewExiste = $stmtCheck->fetch();

        if (!$viewExiste) {
            return [
                'success' => true,
                'tem_alerta' => false,
                'modelos' => [],
                'contadores' => [
                    'total' => 0,
                    'atualizados' => 0,
                    'desatualizados' => 0,
                    'sla_vencido' => 0,
                    'invalidados' => 0,
                    'sem_versao' => 0
                ],
                'message' => 'View VW_MODELO_STATUS nao encontrada. Execute o script SQL da Fase A1.'
            ];
        }

        // Buscar todos os modelos validos com status
        $sql = "
            SELECT 
                CD_MODELO_REGISTRO,
                CD_PONTO_MEDICAO,
                DS_PONTO_NOME,
                DS_TIPO_MODELO,
                VL_R2,
                DT_TREINO,
                DS_STATUS_MODELO,
                FL_TOPOLOGIA_ALTERADA,
                NR_DIAS_DESDE_TREINO,
                NR_DIAS_SLA_RESTANTES
            FROM SIMP.dbo.VW_MODELO_STATUS
            WHERE OP_VALIDO = 1
            ORDER BY 
                CASE DS_STATUS_MODELO 
                    WHEN 'SLA_VENCIDO'   THEN 0 
                    WHEN 'DESATUALIZADO' THEN 1 
                    WHEN 'SEM_VERSAO'    THEN 2
                    ELSE 3 
                END,
                NR_DIAS_SLA_RESTANTES ASC
        ";
        $stmt = $pdo->query($sql);
        $modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Contadores por status
        $contadores = [
            'total' => count($modelos),
            'atualizados' => 0,
            'desatualizados' => 0,
            'sla_vencido' => 0,
            'invalidados' => 0,
            'sem_versao' => 0
        ];

        $modelosAlerta = [];

        foreach ($modelos as $m) {
            $status = $m['DS_STATUS_MODELO'] ?? 'DESCONHECIDO';

            switch ($status) {
                case 'ATUALIZADO':
                    $contadores['atualizados']++;
                    break;
                case 'DESATUALIZADO':
                    $contadores['desatualizados']++;
                    $modelosAlerta[] = $m;
                    break;
                case 'SLA_VENCIDO':
                    $contadores['sla_vencido']++;
                    $modelosAlerta[] = $m;
                    break;
                case 'SEM_VERSAO':
                    $contadores['sem_versao']++;
                    $modelosAlerta[] = $m;
                    break;
                default:
                    break;
            }
        }

        $temAlerta = ($contadores['desatualizados'] + $contadores['sla_vencido']) > 0;

        return [
            'success' => true,
            'tem_alerta' => $temAlerta,
            'modelos' => $modelosAlerta,
            'contadores' => $contadores,
            'message' => $temAlerta
                ? ($contadores['desatualizados'] + $contadores['sla_vencido']) . ' modelo(s) precisam de retreino'
                : 'Todos os modelos estao atualizados'
        ];

    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Erro ao verificar status: ' . $e->getMessage()];
    }
}


// ============================================
// FUNCAO: HISTORICO DE VERSOES
// ============================================

/**
 * Retorna timeline de versoes da topologia.
 * Para exibicao no flowchart e auditoria.
 * 
 * @param PDO      $pdo        Conexao PDO
 * @param int|null $cdSistema  Filtro por sistema (NULL = todas)
 * @param int      $limite     Maximo de versoes retornadas
 * @return array Lista de versoes com diff e contadores
 */
function obterHistoricoVersoes($pdo, $cdSistema = null, $limite = 20)
{
    try {
        $limite = max(1, min(100, intval($limite))); // Sanitizar
        $sql = "
            SELECT TOP {$limite}
                VT.CD_CHAVE,
                VT.DS_HASH_TOPOLOGIA,
                VT.CD_SISTEMA,
                VT.QTD_NOS_ATIVOS,
                VT.QTD_CONEXOES_ATIVAS,
                VT.QTD_NOS_COM_PONTO,
                VT.DS_DESCRICAO,
                VT.DS_DIFF_JSON,
                VT.CD_USUARIO,
                VT.DT_CADASTRO,
                -- Modelos vinculados a esta versao
                (SELECT COUNT(*) 
                    FROM SIMP.dbo.MODELO_REGISTRO MR 
                    WHERE MR.CD_VERSAO_TOPOLOGIA = VT.CD_CHAVE 
                    AND MR.OP_VALIDO = 1
                    ) AS QTD_MODELOS_ATIVOS,
                    (SELECT COUNT(DISTINCT MR2.CD_PONTO_MEDICAO) 
                    FROM SIMP.dbo.MODELO_REGISTRO MR2 
                    WHERE MR2.OP_VALIDO = 1
                    ) AS QTD_PONTOS_COM_MODELO
            FROM SIMP.dbo.VERSAO_TOPOLOGIA VT
            WHERE 1=1
        ";

        $params = [];

        if ($cdSistema !== null) {
            $sql .= " AND ISNULL(VT.CD_SISTEMA, 0) = :cdSistema";
            $params[':cdSistema'] = intval($cdSistema);
        }

        $sql .= " ORDER BY VT.DT_CADASTRO DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $versoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formatar datas e parsear diff JSON
        foreach ($versoes as &$v) {
            if (isset($v['DT_CADASTRO']) && $v['DT_CADASTRO'] instanceof \DateTime) {
                $v['DT_CADASTRO'] = $v['DT_CADASTRO']->format('Y-m-d H:i:s');
            }
            if (!empty($v['DS_DIFF_JSON'])) {
                $v['diff'] = json_decode($v['DS_DIFF_JSON'], true);
            } else {
                $v['diff'] = null;
            }
            // Hash curto para exibicao
            $v['hash_curto'] = substr($v['DS_HASH_TOPOLOGIA'], 0, 12) . '...';
        }
        unset($v);

        return [
            'success' => true,
            'versoes' => $versoes,
            'total' => count($versoes)
        ];

    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Erro ao buscar historico: ' . $e->getMessage()];
    }
}


// ============================================
// FUNCAO: REGISTRAR MODELO TREINADO
// ============================================

/**
 * Registra um modelo ML treinado, vinculando-o a versao atual da topologia.
 * Chamado pelo pipeline de treino (Python via API ou PHP apos treino).
 * 
 * @param PDO   $pdo   Conexao PDO
 * @param array $dados Dados do modelo:
 *   - cd_ponto (int)          : CD_PONTO_MEDICAO
 *   - tipo_modelo (int)       : 1=XGBoost, 2=GNN, 3=LSTM, 4=Estatistico
 *   - r2 (float)              : Metrica R²
 *   - mae (float)             : Metrica MAE
 *   - rmse (float)            : Metrica RMSE
 *   - mape (float)            : Metrica MAPE
 *   - semanas (int)           : Semanas de historico usado
 *   - amostras (int)          : Numero de amostras de treino
 *   - features (int)          : Numero de features
 *   - versao_pipeline (string): Versao do pipeline (ex: 'v6.0')
 *   - caminho_modelo (string) : Caminho relativo do modelo salvo
 * @return array Resultado com cd_modelo_registro
 */
function registrarModelo($pdo, $dados)
{
    try {
        $cdPonto = intval($dados['cd_ponto'] ?? 0);
        $tipoModelo = intval($dados['tipo_modelo'] ?? 1);
        $r2 = isset($dados['r2']) ? floatval($dados['r2']) : null;
        $mae = isset($dados['mae']) ? floatval($dados['mae']) : null;
        $rmse = isset($dados['rmse']) ? floatval($dados['rmse']) : null;
        $mape = isset($dados['mape']) ? floatval($dados['mape']) : null;
        $semanas = isset($dados['semanas']) ? intval($dados['semanas']) : null;
        $amostras = isset($dados['amostras']) ? intval($dados['amostras']) : null;
        $features = isset($dados['features']) ? intval($dados['features']) : null;
        $versaoPipeline = isset($dados['versao_pipeline']) ? trim($dados['versao_pipeline']) : null;
        $caminhoModelo = isset($dados['caminho_modelo']) ? trim($dados['caminho_modelo']) : null;

        if ($cdPonto <= 0) {
            return ['success' => false, 'error' => 'cd_ponto e obrigatorio'];
        }

        // Buscar versao atual da topologia (mais recente)
        $sqlVersao = "
            SELECT TOP 1 CD_CHAVE 
            FROM SIMP.dbo.VERSAO_TOPOLOGIA
            ORDER BY DT_CADASTRO DESC
        ";
        $stmtVersao = $pdo->query($sqlVersao);
        $versaoAtual = $stmtVersao->fetch(PDO::FETCH_ASSOC);
        $cdVersaoTopologia = $versaoAtual ? intval($versaoAtual['CD_CHAVE']) : null;

        // Invalidar modelos anteriores do mesmo ponto e tipo (manter historico, marcar como invalido)
        $sqlInvalida = "
            UPDATE SIMP.dbo.MODELO_REGISTRO
            SET OP_VALIDO = 0,
                DS_MOTIVO_INVALIDACAO = 'Substituido por novo treino',
                DT_INVALIDACAO = GETDATE()
            WHERE CD_PONTO_MEDICAO = :cdPonto
              AND ID_TIPO_MODELO = :tipoModelo
              AND OP_VALIDO = 1
        ";
        $stmtInvalida = $pdo->prepare($sqlInvalida);
        $stmtInvalida->execute([':cdPonto' => $cdPonto, ':tipoModelo' => $tipoModelo]);

        // Inserir novo registro
        $sqlInsert = "
            INSERT INTO SIMP.dbo.MODELO_REGISTRO (
                CD_PONTO_MEDICAO,
                ID_TIPO_MODELO,
                CD_VERSAO_TOPOLOGIA,
                VL_R2, VL_MAE, VL_RMSE, VL_MAPE,
                NR_SEMANAS_HISTORICO,
                NR_AMOSTRAS_TREINO,
                NR_FEATURES,
                DS_VERSAO_PIPELINE,
                DS_CAMINHO_MODELO,
                OP_VALIDO,
                CD_USUARIO_TREINO,
                DT_TREINO,
                DT_CADASTRO
            ) VALUES (
                :cdPonto,
                :tipoModelo,
                :cdVersao,
                :r2, :mae, :rmse, :mape,
                :semanas,
                :amostras,
                :features,
                :versaoPipeline,
                :caminhoModelo,
                1,
                :cdUsuario,
                GETDATE(),
                GETDATE()
            )
        ";
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([
            ':cdPonto' => $cdPonto,
            ':tipoModelo' => $tipoModelo,
            ':cdVersao' => $cdVersaoTopologia,
            ':r2' => $r2,
            ':mae' => $mae,
            ':rmse' => $rmse,
            ':mape' => $mape,
            ':semanas' => $semanas,
            ':amostras' => $amostras,
            ':features' => $features,
            ':versaoPipeline' => $versaoPipeline,
            ':caminhoModelo' => $caminhoModelo,
            ':cdUsuario' => obterUsuarioSessao()
        ]);

        // Recuperar ID
        $stmtId = $pdo->query("SELECT SCOPE_IDENTITY() AS ID");
        $cdModeloRegistro = intval($stmtId->fetch(PDO::FETCH_ASSOC)['ID']);

        // Log (isolado)
        try {
            @include_once __DIR__ . '/../logHelper.php';
            if (function_exists('registrarLog')) {
                registrarLog(
                    'Treinamento IA',
                    'INSERT',
                    "Modelo #{$cdModeloRegistro} registrado - Ponto: {$cdPonto}, Tipo: {$tipoModelo}, R2: {$r2}, Versao Topologia: {$cdVersaoTopologia}",
                    ['cd_modelo' => $cdModeloRegistro, 'cd_ponto' => $cdPonto]
                );
            }
        } catch (Exception $logEx) {
            // Silencioso
        }

        return [
            'success' => true,
            'cd_modelo_registro' => $cdModeloRegistro,
            'cd_versao_topologia' => $cdVersaoTopologia,
            'message' => "Modelo registrado com sucesso (vinculado a versao #{$cdVersaoTopologia})"
        ];

    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Erro ao registrar modelo: ' . $e->getMessage()];
    }
}


// ============================================
// FUNCAO: INVALIDAR MODELOS
// ============================================

/**
 * Invalida modelos de um ponto (ou todos) manualmente.
 * 
 * @param PDO         $pdo     Conexao PDO
 * @param int|null    $cdPonto Ponto especifico (NULL = todos)
 * @param string      $motivo  Motivo da invalidacao
 * @return array Resultado com quantidade invalidada
 */
function invalidarModelos($pdo, $cdPonto = null, $motivo = 'Invalidacao manual')
{
    try {
        $sql = "
            UPDATE SIMP.dbo.MODELO_REGISTRO
            SET OP_VALIDO = 0,
                DS_MOTIVO_INVALIDACAO = :motivo,
                DT_INVALIDACAO = GETDATE()
            WHERE OP_VALIDO = 1
        ";
        $params = [':motivo' => $motivo];

        if ($cdPonto !== null && $cdPonto > 0) {
            $sql .= " AND CD_PONTO_MEDICAO = :cdPonto";
            $params[':cdPonto'] = $cdPonto;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $qtdInvalidados = $stmt->rowCount();

        // Log (isolado)
        try {
            @include_once __DIR__ . '/../logHelper.php';
            if (function_exists('registrarLog')) {
                $descPonto = $cdPonto ? "Ponto: {$cdPonto}" : 'Todos os pontos';
                registrarLog(
                    'Treinamento IA',
                    'UPDATE',
                    "Modelos invalidados: {$qtdInvalidados} - {$descPonto} - Motivo: {$motivo}",
                    ['cd_ponto' => $cdPonto, 'qtd' => $qtdInvalidados]
                );
            }
        } catch (Exception $logEx) {
            // Silencioso
        }

        return [
            'success' => true,
            'qtd_invalidados' => $qtdInvalidados,
            'message' => "{$qtdInvalidados} modelo(s) invalidado(s)"
        ];

    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Erro ao invalidar modelos: ' . $e->getMessage()];
    }
}


// ============================================
// FUNCAO: RESUMO DA TOPOLOGIA
// ============================================

/**
 * Retorna resumo da topologia atual: contadores, ultima versao, modelos ativos.
 * 
 * @param PDO $pdo Conexao PDO
 * @return array Resumo consolidado
 */
function obterResumoTopologia($pdo)
{
    try {
        // Ultima versao
        $sqlVersao = "
            SELECT TOP 1 
                CD_CHAVE,
                DS_HASH_TOPOLOGIA,
                QTD_NOS_ATIVOS,
                QTD_CONEXOES_ATIVAS,
                QTD_NOS_COM_PONTO,
                DS_DESCRICAO,
                DT_CADASTRO
            FROM SIMP.dbo.VERSAO_TOPOLOGIA
            ORDER BY DT_CADASTRO DESC
        ";
        $stmtVersao = $pdo->query($sqlVersao);
        $ultimaVersao = $stmtVersao->fetch(PDO::FETCH_ASSOC);

        // Formatar data
        if ($ultimaVersao && isset($ultimaVersao['DT_CADASTRO']) && $ultimaVersao['DT_CADASTRO'] instanceof \DateTime) {
            $ultimaVersao['DT_CADASTRO'] = $ultimaVersao['DT_CADASTRO']->format('Y-m-d H:i:s');
        }

        // Total de versoes
        $sqlTotalVersoes = "SELECT COUNT(*) AS TOTAL FROM SIMP.dbo.VERSAO_TOPOLOGIA";
        $totalVersoes = intval($pdo->query($sqlTotalVersoes)->fetch(PDO::FETCH_ASSOC)['TOTAL']);

        // Contadores de modelos
        $sqlModelos = "
            SELECT 
                COUNT(*) AS TOTAL_MODELOS,
                SUM(CASE WHEN OP_VALIDO = 1 THEN 1 ELSE 0 END) AS MODELOS_VALIDOS,
                SUM(CASE WHEN OP_VALIDO = 0 THEN 1 ELSE 0 END) AS MODELOS_INVALIDADOS
            FROM SIMP.dbo.MODELO_REGISTRO
        ";
        $contModelos = $pdo->query($sqlModelos)->fetch(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'ultima_versao' => $ultimaVersao,
            'total_versoes' => $totalVersoes,
            'hash_curto' => $ultimaVersao ? substr($ultimaVersao['DS_HASH_TOPOLOGIA'], 0, 12) . '...' : null,
            'modelos' => [
                'total' => intval($contModelos['TOTAL_MODELOS'] ?? 0),
                'validos' => intval($contModelos['MODELOS_VALIDOS'] ?? 0),
                'invalidados' => intval($contModelos['MODELOS_INVALIDADOS'] ?? 0)
            ]
        ];

    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Erro ao buscar resumo: ' . $e->getMessage()];
    }
}


// ============================================
// FUNCAO: CONSULTAR SLA
// ============================================

/**
 * Consulta SLA de retreino de um ponto (ou padrao global).
 * 
 * @param PDO      $pdo     Conexao PDO
 * @param int|null $cdPonto Ponto especifico (NULL = todos)
 * @return array SLA configurado
 */
function consultarSLA($pdo, $cdPonto = null)
{
    try {
        $sql = "
            SELECT 
                CD_PONTO_MEDICAO,
                NR_SLA_RETREINO_DIAS,
                DT_TREINO
            FROM SIMP.dbo.MODELO_REGISTRO
            WHERE OP_VALIDO = 1
        ";
        $params = [];

        if ($cdPonto !== null && $cdPonto > 0) {
            $sql .= " AND CD_PONTO_MEDICAO = :cdPonto";
            $params[':cdPonto'] = $cdPonto;
        }

        $sql .= " ORDER BY CD_PONTO_MEDICAO";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'sla_lista' => $resultados,
            'total' => count($resultados)
        ];

    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Erro ao consultar SLA: ' . $e->getMessage()];
    }
}


// ============================================
// FUNCAO: ATUALIZAR SLA
// ============================================

/**
 * Atualiza SLA de retreino de um ponto (ou todos os modelos validos).
 * 
 * @param PDO      $pdo     Conexao PDO
 * @param int|null $cdPonto Ponto especifico (NULL = todos)
 * @param int      $dias    Novo SLA em dias
 * @return array Resultado
 */
function atualizarSLA($pdo, $cdPonto = null, $dias = 7)
{
    try {
        $sql = "
            UPDATE SIMP.dbo.MODELO_REGISTRO
            SET NR_SLA_RETREINO_DIAS = :dias
            WHERE OP_VALIDO = 1
        ";
        $params = [':dias' => $dias];

        if ($cdPonto !== null && $cdPonto > 0) {
            $sql .= " AND CD_PONTO_MEDICAO = :cdPonto";
            $params[':cdPonto'] = $cdPonto;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $qtdAtualizado = $stmt->rowCount();

        return [
            'success' => true,
            'qtd_atualizados' => $qtdAtualizado,
            'novo_sla_dias' => $dias,
            'message' => "{$qtdAtualizado} modelo(s) com SLA atualizado para {$dias} dia(s)"
        ];

    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Erro ao atualizar SLA: ' . $e->getMessage()];
    }
}
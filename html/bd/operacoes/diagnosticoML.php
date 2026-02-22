<?php
/**
 * SIMP - Diagnóstico de Treino ML
 * 
 * Executa verificações sequenciais para diagnosticar por que um ponto
 * não treinou (ou treinou com qualidade baixa).
 * 
 * Verificações (7 etapas):
 *   1. TAG configurada no PONTO_MEDICAO
 *   2. Tipo de medidor compatível (1,2,4,6 — exclui hidrômetro)
 *   3. Dados históricos no REGISTRO_VAZAO_PRESSAO
 *   4. Relação como TAG principal na AUX_RELACAO_PONTOS_MEDICAO
 *   5. TAGs auxiliares válidas (sem duplicatas de resolução)
 *   6. Auxiliares com dados no período de treino
 *   7. Qualidade dos dados (gaps, zeros, cobertura)
 * 
 * Ações:
 *   - diagnostico: Executa diagnóstico completo para um cd_ponto
 * 
 * Caminho: html/bd/operacoes/diagnosticoML.php
 * 
 * @author Bruno - CESAN
 * @version 1.0
 * @date 2026-02
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

/**
 * Retorna JSON limpo e encerra execução.
 * @param array $data Dados para serializar
 */
function retornarJSON_DIAG($data)
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
    @include_once __DIR__ . '/../conexao.php';

    if (!isset($pdoSIMP)) {
        retornarJSON_DIAG(['success' => false, 'error' => 'Conexão com banco não estabelecida']);
    }

    // Receber dados
    $rawInput = file_get_contents('php://input');
    $dados = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $dados = $_GET;
    }

    $acao = $dados['acao'] ?? $_GET['acao'] ?? '';

    if ($acao !== 'diagnostico') {
        retornarJSON_DIAG(['success' => false, 'error' => 'Ação inválida. Use: diagnostico']);
    }

    $cdPonto = intval($dados['cd_ponto'] ?? 0);
    $semanas = intval($dados['semanas'] ?? 24);

    if ($cdPonto <= 0) {
        retornarJSON_DIAG(['success' => false, 'error' => 'cd_ponto é obrigatório']);
    }

    // Executar diagnóstico
    $resultado = executarDiagnostico($pdoSIMP, $cdPonto, $semanas);
    retornarJSON_DIAG($resultado);

} catch (Exception $e) {
    retornarJSON_DIAG(['success' => false, 'error' => $e->getMessage()]);
}


// ============================================
// FUNÇÃO PRINCIPAL
// ============================================

/**
 * Executa diagnóstico completo de viabilidade de treino ML.
 * 
 * @param PDO $pdo      Conexão PDO com SIMP
 * @param int $cdPonto  Código do ponto de medição
 * @param int $semanas  Semanas de histórico (padrão 24)
 * @return array        Resultado com etapas do diagnóstico
 */
function executarDiagnostico(PDO $pdo, int $cdPonto, int $semanas = 24): array
{
    // Letras por tipo de medidor (padrão SIMP)
    $letrasTipo = [1 => 'M', 2 => 'E', 4 => 'P', 6 => 'R', 8 => 'H'];
    $nomesTipo = [
        1 => 'Macromedidor',
        2 => 'Estação Pitométrica',
        4 => 'Medidor de Pressão',
        6 => 'Nível Reservatório',
        8 => 'Hidrômetro'
    ];
    $tiposPermitidos = [1, 2, 4, 6];

    $etapas = [];
    $tagPrincipal = null;
    $tipoMedidor = null;
    $temBloqueio = false; // Se alguma etapa for ❌, marca bloqueio

    // ========================================
    // ETAPA 1: TAG configurada
    // ========================================
    $sqlPonto = "
        SELECT 
            PM.CD_PONTO_MEDICAO,
            PM.DS_NOME,
            PM.ID_TIPO_MEDIDOR,
            PM.DS_TAG_VAZAO,
            PM.DS_TAG_PRESSAO,
            PM.DS_TAG_RESERVATORIO,
            PM.DT_DESATIVACAO,
            L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
            L.CD_UNIDADE
        FROM SIMP.dbo.PONTO_MEDICAO PM
        LEFT JOIN SIMP.dbo.LOCALIDADE L ON L.CD_CHAVE = PM.CD_LOCALIDADE
        WHERE PM.CD_PONTO_MEDICAO = :cdPonto
    ";
    $stmtPonto = $pdo->prepare($sqlPonto);
    $stmtPonto->execute([':cdPonto' => $cdPonto]);
    $ponto = $stmtPonto->fetch(PDO::FETCH_ASSOC);

    if (!$ponto) {
        return [
            'success' => true,
            'cd_ponto' => $cdPonto,
            'etapas' => [
                [
                    'numero' => 1,
                    'titulo' => 'Ponto de medição',
                    'status' => 'erro',
                    'mensagem' => "Ponto #$cdPonto não encontrado no cadastro",
                    'detalhes' => []
                ]
            ],
            'resumo' => ['viavel' => false, 'bloqueios' => 1, 'alertas' => 0]
        ];
    }

    // Montar código formatado
    $letra = $letrasTipo[$ponto['ID_TIPO_MEDIDOR']] ?? 'X';
    $codigoFormatado = ($ponto['CD_LOCALIDADE_CODIGO'] ?? '000') . '-'
        . str_pad($cdPonto, 6, '0', STR_PAD_LEFT) . '-'
        . $letra . '-' . ($ponto['CD_UNIDADE'] ?? '00');

    // Determinar TAG ativa
    $tagVazao = trim($ponto['DS_TAG_VAZAO'] ?? '');
    $tagPressao = trim($ponto['DS_TAG_PRESSAO'] ?? '');
    $tagReserv = trim($ponto['DS_TAG_RESERVATORIO'] ?? '');
    $tipoMedidor = intval($ponto['ID_TIPO_MEDIDOR']);

    // Resolver TAG conforme tipo
    if ($tipoMedidor == 4 && $tagPressao) {
        $tagPrincipal = $tagPressao;
        $campoTag = 'DS_TAG_PRESSAO';
    } elseif ($tipoMedidor == 6 && $tagReserv) {
        $tagPrincipal = $tagReserv;
        $campoTag = 'DS_TAG_RESERVATORIO';
    } elseif ($tagVazao) {
        $tagPrincipal = $tagVazao;
        $campoTag = 'DS_TAG_VAZAO';
    } elseif ($tagPressao) {
        $tagPrincipal = $tagPressao;
        $campoTag = 'DS_TAG_PRESSAO';
    } elseif ($tagReserv) {
        $tagPrincipal = $tagReserv;
        $campoTag = 'DS_TAG_RESERVATORIO';
    }

    if ($tagPrincipal) {
        $etapas[] = [
            'numero' => 1,
            'titulo' => 'TAG configurada',
            'status' => 'ok',
            'mensagem' => $tagPrincipal,
            'detalhes' => [
                ['label' => 'Campo', 'valor' => $campoTag],
                ['label' => 'Vazão', 'valor' => $tagVazao ?: '(vazio)'],
                ['label' => 'Pressão', 'valor' => $tagPressao ?: '(vazio)'],
                ['label' => 'Reservatório', 'valor' => $tagReserv ?: '(vazio)']
            ]
        ];
    } else {
        $temBloqueio = true;
        $etapas[] = [
            'numero' => 1,
            'titulo' => 'TAG configurada',
            'status' => 'erro',
            'mensagem' => 'Nenhuma TAG preenchida (DS_TAG_VAZAO, DS_TAG_PRESSAO, DS_TAG_RESERVATORIO todas NULL)',
            'detalhes' => [
                ['label' => 'Ação', 'valor' => 'Cadastrar TAG no Ponto de Medição']
            ]
        ];
    }

    // ========================================
    // ETAPA 2: Tipo compatível
    // ========================================
    $nomeTipo = $nomesTipo[$tipoMedidor] ?? "Desconhecido ($tipoMedidor)";

    if (in_array($tipoMedidor, $tiposPermitidos)) {
        $etapas[] = [
            'numero' => 2,
            'titulo' => 'Tipo de medidor compatível',
            'status' => 'ok',
            'mensagem' => "$nomeTipo (Tipo $tipoMedidor)",
            'detalhes' => []
        ];
    } else {
        $temBloqueio = true;
        $etapas[] = [
            'numero' => 2,
            'titulo' => 'Tipo de medidor compatível',
            'status' => 'erro',
            'mensagem' => "$nomeTipo (Tipo $tipoMedidor) — não suportado pelo ML",
            'detalhes' => [
                ['label' => 'Permitidos', 'valor' => 'Macromedidor(1), Pitométrica(2), Pressão(4), Reservatório(6)'],
                ['label' => 'Motivo', 'valor' => 'Hidrômetro possui escala e granularidade incompatíveis com macromedição']
            ]
        ];
    }

    // ========================================
    // ETAPA 3: Dados no histórico
    // ========================================
    $campoValor = 'VL_VAZAO';
    if ($tipoMedidor == 4)
        $campoValor = 'VL_PRESSAO';
    if ($tipoMedidor == 6)
        $campoValor = 'VL_RESERVATORIO';

    $sqlHist = "
        SELECT 
            COUNT(*) AS QTD_REGISTROS,
            MIN(DT_LEITURA) AS PRIMEIRA_LEITURA,
            MAX(DT_LEITURA) AS ULTIMA_LEITURA,
            SUM(CASE WHEN DT_LEITURA >= DATEADD(WEEK, -$semanas, GETDATE()) THEN 1 ELSE 0 END) AS QTD_PERIODO
        FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
        WHERE CD_PONTO_MEDICAO = :cdPonto
          AND ID_SITUACAO = 1
    ";
    $stmtHist = $pdo->prepare($sqlHist);
    $stmtHist->execute([':cdPonto' => $cdPonto]);

    $hist = $stmtHist->fetch(PDO::FETCH_ASSOC);

    $qtdRegistros = intval($hist['QTD_REGISTROS'] ?? 0);
    $qtdPeriodo = intval($hist['QTD_PERIODO'] ?? 0);
    $primeira = $hist['PRIMEIRA_LEITURA'];
    $ultima = $hist['ULTIMA_LEITURA'];

    // Mínimo recomendado: ~24 semanas × 7 dias × 24 horas = 4.032 registros horários
    $minimoRecomendado = $semanas * 7 * 24;
    $minimoAbsoluto = 100; // Mínimo do XGBoost no treinar_modelos.py

    if ($qtdPeriodo >= $minimoRecomendado) {
        $etapas[] = [
            'numero' => 3,
            'titulo' => 'Dados no histórico',
            'status' => 'ok',
            'mensagem' => number_format($qtdPeriodo, 0, ',', '.') . " registros nas últimas $semanas semanas",
            'detalhes' => [
                ['label' => 'Total geral', 'valor' => number_format($qtdRegistros, 0, ',', '.')],
                ['label' => 'Primeira leitura', 'valor' => $primeira ? date('d/m/Y', strtotime($primeira)) : '—'],
                ['label' => 'Última leitura', 'valor' => $ultima ? date('d/m/Y H:i', strtotime($ultima)) : '—'],
                ['label' => 'Recomendado', 'valor' => number_format($minimoRecomendado, 0, ',', '.') . " registros"]
            ]
        ];
    } elseif ($qtdPeriodo >= $minimoAbsoluto) {
        $etapas[] = [
            'numero' => 3,
            'titulo' => 'Dados no histórico',
            'status' => 'alerta',
            'mensagem' => number_format($qtdPeriodo, 0, ',', '.') . " registros — abaixo do recomendado ($minimoRecomendado)",
            'detalhes' => [
                ['label' => 'Total geral', 'valor' => number_format($qtdRegistros, 0, ',', '.')],
                ['label' => 'Primeira leitura', 'valor' => $primeira ? date('d/m/Y', strtotime($primeira)) : '—'],
                ['label' => 'Última leitura', 'valor' => $ultima ? date('d/m/Y H:i', strtotime($ultima)) : '—'],
                ['label' => 'Mínimo absoluto', 'valor' => "$minimoAbsoluto registros"],
                ['label' => 'Impacto', 'valor' => 'Modelo pode treinar mas com qualidade reduzida']
            ]
        ];
    } else {
        $temBloqueio = true;
        $statusHist = $qtdPeriodo == 0 ? 'erro' : 'alerta';
        $etapas[] = [
            'numero' => 3,
            'titulo' => 'Dados no histórico',
            'status' => $statusHist,
            'mensagem' => $qtdPeriodo == 0
                ? 'Nenhum registro no período de treino'
                : number_format($qtdPeriodo, 0, ',', '.') . " registros — insuficiente (mínimo: $minimoAbsoluto)",
            'detalhes' => [
                ['label' => 'Total geral', 'valor' => number_format($qtdRegistros, 0, ',', '.')],
                ['label' => 'Primeira leitura', 'valor' => $primeira ? date('d/m/Y', strtotime($primeira)) : '—'],
                ['label' => 'Última leitura', 'valor' => $ultima ? date('d/m/Y H:i', strtotime($ultima)) : '—'],
                ['label' => 'Ação', 'valor' => 'Verificar sincronização com Historiador CCO']
            ]
        ];
    }

    // Se não tem TAG, para aqui — as etapas seguintes dependem da TAG
    if (!$tagPrincipal) {
        return finalizarDiagnostico($cdPonto, $ponto, $codigoFormatado, $etapas, $semanas);
    }

    // ========================================
    // ETAPA 4: Relação como principal
    // ========================================
    $sqlRelacao = "
        SELECT TAG_AUXILIAR
        FROM SIMP.dbo.AUX_RELACAO_PONTOS_MEDICAO
        WHERE LTRIM(RTRIM(TAG_PRINCIPAL)) = :tag
          AND LTRIM(RTRIM(TAG_PRINCIPAL)) <> LTRIM(RTRIM(TAG_AUXILIAR))
        ORDER BY TAG_AUXILIAR
    ";
    $stmtRel = $pdo->prepare($sqlRelacao);
    $stmtRel->execute([':tag' => $tagPrincipal]);
    $auxiliares = $stmtRel->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($auxiliares)) {
        $etapas[] = [
            'numero' => 4,
            'titulo' => 'Relação como TAG principal',
            'status' => 'ok',
            'mensagem' => count($auxiliares) . " auxiliar(es) configurados",
            'detalhes' => array_map(function ($a) {
                return ['label' => 'Auxiliar', 'valor' => $a];
            }, $auxiliares)
        ];
    } else {
        // Verificar se existe como auxiliar
        $sqlComoAux = "
            SELECT COUNT(*) FROM SIMP.dbo.AUX_RELACAO_PONTOS_MEDICAO
            WHERE LTRIM(RTRIM(TAG_AUXILIAR)) = :tag
        ";
        $stmtAux = $pdo->prepare($sqlComoAux);
        $stmtAux->execute([':tag' => $tagPrincipal]);
        $existeComoAux = intval($stmtAux->fetchColumn()) > 0;

        $temBloqueio = true;
        $etapas[] = [
            'numero' => 4,
            'titulo' => 'Relação como TAG principal',
            'status' => 'erro',
            'mensagem' => 'TAG não cadastrada como principal na tabela de relações ML',
            'detalhes' => $existeComoAux
                ? [
                    ['label' => 'Observação', 'valor' => 'Esta TAG existe como AUXILIAR de outro ponto'],
                    ['label' => 'Ação', 'valor' => 'Cadastrar como principal em Associações ou usar Sincronizar Flowchart']
                ]
                : [
                    ['label' => 'Ação', 'valor' => 'Cadastrar relação em Associações ou configurar no Flowchart e sincronizar']
                ]
        ];
    }

    // Se não tem auxiliares, para aqui
    if (empty($auxiliares)) {
        return finalizarDiagnostico($cdPonto, $ponto, $codigoFormatado, $etapas, $semanas);
    }

    // ========================================
    // ETAPA 5: TAGs auxiliares — duplicatas de resolução
    // ========================================
    $duplicatas = [];
    $resolucoes = [];

    foreach ($auxiliares as $tagAux) {
        $sqlResolve = "
            SELECT 
                PM.CD_PONTO_MEDICAO,
                PM.DS_NOME,
                PM.DT_DESATIVACAO,
                (SELECT COUNT(*) 
                 FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
                 WHERE RVP.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
                   AND RVP.ID_SITUACAO = 1
                   AND RVP.DT_LEITURA >= DATEADD(WEEK, -$semanas, GETDATE())
                ) AS QTD_REGISTROS
            FROM SIMP.dbo.PONTO_MEDICAO PM
            WHERE PM.DS_TAG_VAZAO = :tag1
               OR PM.DS_TAG_PRESSAO = :tag2
               OR PM.DS_TAG_RESERVATORIO = :tag3
        ";
        $stmtRes = $pdo->prepare($sqlResolve);
        $stmtRes->execute([':tag1' => $tagAux, ':tag2' => $tagAux, ':tag3' => $tagAux]);
        $pontosResolve = $stmtRes->fetchAll(PDO::FETCH_ASSOC);

        $resolucoes[$tagAux] = $pontosResolve;
        if (count($pontosResolve) > 1) {
            $duplicatas[$tagAux] = $pontosResolve;
        }
    }

    if (empty($duplicatas)) {
        $etapas[] = [
            'numero' => 5,
            'titulo' => 'TAGs auxiliares — resolução',
            'status' => 'ok',
            'mensagem' => 'Todas as TAGs auxiliares resolvem para um único ponto',
            'detalhes' => []
        ];
    } else {
        $detalhes = [];
        foreach ($duplicatas as $tagDup => $pontos) {
            foreach ($pontos as $p) {
                $ativo = empty($p['DT_DESATIVACAO']) ? 'Ativo' : 'Desativado';
                $qtd = number_format(intval($p['QTD_REGISTROS']), 0, ',', '.');
                $detalhes[] = [
                    'label' => "$tagDup → #" . $p['CD_PONTO_MEDICAO'],
                    'valor' => "$ativo | $qtd reg | " . ($p['DS_NOME'] ?? '')
                ];
            }
        }
        $etapas[] = [
            'numero' => 5,
            'titulo' => 'TAGs auxiliares — resolução',
            'status' => 'alerta',
            'mensagem' => count($duplicatas) . ' TAG(s) resolvem para múltiplos pontos (risco de usar ponto errado)',
            'detalhes' => array_merge($detalhes, [
                ['label' => 'Impacto', 'valor' => 'Python pode resolver para o ponto sem dados (SELECT TOP 1 sem ORDER BY)'],
                ['label' => 'Ação', 'valor' => 'Desativar os pontos legados duplicados ou aplicar correção no treinar_modelos.py']
            ])
        ];
    }

    // ========================================
    // ETAPA 6: Auxiliares com dados no período
    // ========================================
    $auxComDados = 0;
    $auxSemDados = 0;
    $detalhesAux = [];

    foreach ($auxiliares as $tagAux) {
        $pontos = $resolucoes[$tagAux] ?? [];
        // Pegar o melhor ponto (ativo, mais dados)
        $melhorQtd = 0;
        $melhorPonto = null;
        foreach ($pontos as $p) {
            $qtd = intval($p['QTD_REGISTROS']);
            if ($qtd > $melhorQtd) {
                $melhorQtd = $qtd;
                $melhorPonto = $p;
            }
        }

        if ($melhorQtd > 0) {
            $auxComDados++;
            $detalhesAux[] = [
                'label' => "✅ $tagAux",
                'valor' => number_format($melhorQtd, 0, ',', '.') . " reg"
                    . ($melhorPonto ? " (#" . $melhorPonto['CD_PONTO_MEDICAO'] . ")" : '')
            ];
        } else {
            $auxSemDados++;
            $detalhesAux[] = [
                'label' => "❌ $tagAux",
                'valor' => '0 registros no período'
                    . (empty($pontos) ? ' (TAG não encontrada no SIMP)' : '')
            ];
        }
    }

    $totalAux = count($auxiliares);
    if ($auxSemDados == 0) {
        $etapas[] = [
            'numero' => 6,
            'titulo' => 'Auxiliares com dados',
            'status' => 'ok',
            'mensagem' => "$auxComDados/$totalAux auxiliares com dados nas últimas $semanas semanas",
            'detalhes' => $detalhesAux
        ];
    } elseif ($auxComDados > 0) {
        $etapas[] = [
            'numero' => 6,
            'titulo' => 'Auxiliares com dados',
            'status' => 'alerta',
            'mensagem' => "$auxComDados/$totalAux com dados — $auxSemDados sem dados",
            'detalhes' => array_merge($detalhesAux, [
                ['label' => 'Impacto', 'valor' => 'Modelo treina mas com menos features (menor qualidade possível)']
            ])
        ];
    } else {
        $temBloqueio = true;
        $etapas[] = [
            'numero' => 6,
            'titulo' => 'Auxiliares com dados',
            'status' => 'erro',
            'mensagem' => "Nenhum auxiliar com dados no período — treino impossível",
            'detalhes' => array_merge($detalhesAux, [
                ['label' => 'Ação', 'valor' => 'Verificar sincronização das TAGs auxiliares com o Historiador CCO']
            ])
        ];
    }

    // ========================================
    // ETAPA 7: Qualidade dos dados (principal)
    // ========================================
    if ($qtdPeriodo > 0) {
        $sqlQual = "
            SELECT
                COUNT(*) AS TOTAL,
                SUM(CASE WHEN $campoValor = 0 THEN 1 ELSE 0 END) AS ZEROS,
                SUM(CASE WHEN $campoValor IS NULL THEN 1 ELSE 0 END) AS NULOS,
                MIN($campoValor) AS MINIMO,
                MAX($campoValor) AS MAXIMO,
                AVG(CAST($campoValor AS FLOAT)) AS MEDIA,
                STDEV(CAST($campoValor AS FLOAT)) AS DESVIO
            FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO
            WHERE CD_PONTO_MEDICAO = :cdPonto
              AND ID_SITUACAO = 1
              AND DT_LEITURA >= DATEADD(WEEK, -$semanas, GETDATE())
        ";
        $stmtQual = $pdo->prepare($sqlQual);
        $stmtQual->execute([':cdPonto' => $cdPonto]);
        $qual = $stmtQual->fetch(PDO::FETCH_ASSOC);

        $total = intval($qual['TOTAL']);
        $zeros = intval($qual['ZEROS']);
        $nulos = intval($qual['NULOS']);
        $percZeros = $total > 0 ? round($zeros / $total * 100, 1) : 0;
        $percNulos = $total > 0 ? round($nulos / $total * 100, 1) : 0;

        // Cobertura: horas esperadas vs horas com dados
        $horasEsperadas = $semanas * 7 * 24;
        $cobertura = $horasEsperadas > 0 ? round($total / $horasEsperadas * 100, 1) : 0;
        $cobertura = min(100, $cobertura); // Pode ser >100% se tiver mais de 1 reg/hora

        // Determinar status
        $alertasQual = [];
        if ($percZeros > 20 && $tipoMedidor != 6) {
            $alertasQual[] = "Alto percentual de zeros ($percZeros%)";
        }
        if ($cobertura < 50) {
            $alertasQual[] = "Cobertura baixa ($cobertura% das horas)";
        }

        $statusQual = empty($alertasQual) ? 'ok' : 'alerta';
        $msgQual = empty($alertasQual)
            ? "Cobertura: $cobertura% | Zeros: $percZeros%"
            : implode(' | ', $alertasQual);

        $etapas[] = [
            'numero' => 7,
            'titulo' => 'Qualidade dos dados',
            'status' => $statusQual,
            'mensagem' => $msgQual,
            'detalhes' => [
                ['label' => 'Registros no período', 'valor' => number_format($total, 0, ',', '.')],
                ['label' => 'Cobertura temporal', 'valor' => "$cobertura% ($total / $horasEsperadas h esperadas)"],
                ['label' => 'Registros zerados', 'valor' => number_format($zeros, 0, ',', '.') . " ($percZeros%)"],
                ['label' => 'Registros nulos', 'valor' => number_format($nulos, 0, ',', '.') . " ($percNulos%)"],
                [
                    'label' => 'Faixa de valores',
                    'valor' =>
                        number_format(floatval($qual['MINIMO'] ?? 0), 2, ',', '.') . ' ~ '
                        . number_format(floatval($qual['MAXIMO'] ?? 0), 2, ',', '.')
                ],
                [
                    'label' => 'Média ± Desvio',
                    'valor' =>
                        number_format(floatval($qual['MEDIA'] ?? 0), 2, ',', '.') . ' ± '
                        . number_format(floatval($qual['DESVIO'] ?? 0), 2, ',', '.')
                ]
            ]
        ];
    }

    return finalizarDiagnostico($cdPonto, $ponto, $codigoFormatado, $etapas, $semanas);
}


/**
 * Monta o resultado final do diagnóstico com resumo.
 */
function finalizarDiagnostico(int $cdPonto, array $ponto, string $codigoFormatado, array $etapas, int $semanas): array
{
    $bloqueios = 0;
    $alertas = 0;
    $ok = 0;

    foreach ($etapas as $e) {
        if ($e['status'] === 'erro')
            $bloqueios++;
        elseif ($e['status'] === 'alerta')
            $alertas++;
        else
            $ok++;
    }

    $viavel = $bloqueios === 0;
    $veredicto = 'Treino viável';
    if ($bloqueios > 0) {
        $veredicto = "Treino bloqueado — $bloqueios impedimento(s)";
    } elseif ($alertas > 0) {
        $veredicto = "Treino viável com $alertas alerta(s)";
    }

    return [
        'success' => true,
        'cd_ponto' => $cdPonto,
        'ds_nome' => $ponto['DS_NOME'] ?? '',
        'codigo_formatado' => $codigoFormatado,
        'semanas' => $semanas,
        'etapas' => $etapas,
        'resumo' => [
            'viavel' => $viavel,
            'veredicto' => $veredicto,
            'bloqueios' => $bloqueios,
            'alertas' => $alertas,
            'ok' => $ok,
            'total_etapas' => count($etapas)
        ]
    ];
}
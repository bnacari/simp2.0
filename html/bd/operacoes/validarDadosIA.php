<?php
/**
 * SIMP - Validar/Corrigir dados via sugestão da IA
 * 
 * Permite salvar valores DIFERENTES para cada hora
 * Recebe: { cdPonto, data, tipoMedidor, valores: [{hora: 8, valor: 424.03}, {hora: 14, valor: 506.63}] }
 */

header('Content-Type: application/json; charset=utf-8');

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar permissão
require_once __DIR__ . '/../../includes/auth.php';
if (!podeEditarTela('Validação dos Dados')) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão para esta operação']);
    exit;
}

try {
    include_once '../conexao.php';

    // Receber dados
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Erro ao decodificar JSON: ' . json_last_error_msg());
    }

    $cdPonto = isset($input['cdPonto']) ? (int) $input['cdPonto'] : 0;
    $data = isset($input['data']) ? $input['data'] : '';
    $tipoMedidor = isset($input['tipoMedidor']) ? (int) $input['tipoMedidor'] : 1;
    $valores = isset($input['valores']) ? $input['valores'] : [];
    $observacao = isset($input['observacao']) ? trim($input['observacao']) : 'Valor sugerido e aplicado via IA';

    // Validações
    if ($cdPonto <= 0) {
        throw new Exception('Ponto de medição inválido');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        throw new Exception('Data inválida');
    }
    if (empty($valores) || !is_array($valores)) {
        throw new Exception('Nenhum valor para aplicar');
    }

    // Validar estrutura dos valores
    foreach ($valores as $item) {
        if (!isset($item['hora']) || !isset($item['valor'])) {
            throw new Exception('Estrutura de valores inválida');
        }
        if ($item['hora'] < 0 || $item['hora'] > 23) {
            throw new Exception('Hora inválida: ' . $item['hora']);
        }
    }

    // Buscar nome do ponto para log
    $nomePonto = "Ponto $cdPonto";
    try {
        $sqlPonto = "SELECT DS_NOME FROM SIMP.dbo.PONTO_MEDICAO WHERE CD_PONTO_MEDICAO = :cdPonto";
        $stmtPonto = $pdoSIMP->prepare($sqlPonto);
        $stmtPonto->execute([':cdPonto' => $cdPonto]);
        $rowPonto = $stmtPonto->fetch(PDO::FETCH_ASSOC);
        if ($rowPonto) {
            $nomePonto = $rowPonto['DS_NOME'];
        }
    } catch (Exception $e) {
    }

    // Definir coluna baseado no tipo de medidor
    $colunasPorTipo = [
        1 => 'VL_VAZAO_EFETIVA',
        2 => 'VL_VAZAO_EFETIVA',
        4 => 'VL_PRESSAO',
        8 => 'VL_VAZAO_EFETIVA'
    ];
    $coluna = $colunasPorTipo[$tipoMedidor] ?? 'VL_VAZAO_EFETIVA';

    // Obter usuário logado
    $cdUsuario = $_SESSION['cd_usuario'] ?? null;

    // Iniciar transação
    $pdoSIMP->beginTransaction();

    try {
        $totalInativados = 0;
        $totalInseridos = 0;
        $horasProcessadas = [];

        foreach ($valores as $item) {
            $hora = (int) $item['hora'];
            $novoValor = floatval($item['valor']);

            // 1. Marcar registros existentes na hora como inativos (ID_SITUACAO = 2)
            $sqlInativar = "UPDATE SIMP.dbo.REGISTRO_VAZAO_PRESSAO 
                            SET ID_SITUACAO = 2,
                                CD_USUARIO_ULTIMA_ATUALIZACAO = :cdUsuario,
                                DT_ULTIMA_ATUALIZACAO = GETDATE()
                            WHERE CD_PONTO_MEDICAO = :cdPonto
                              AND CAST(DT_LEITURA AS DATE) = :data
                              AND DATEPART(HOUR, DT_LEITURA) = :hora
                              AND ID_SITUACAO = 1";

            $stmtInativar = $pdoSIMP->prepare($sqlInativar);
            $stmtInativar->execute([
                ':cdUsuario' => $cdUsuario,
                ':cdPonto' => $cdPonto,
                ':data' => $data,
                ':hora' => $hora
            ]);
            $totalInativados += $stmtInativar->rowCount();

            // 2. Criar 60 novos registros (um para cada minuto)
            $sqlInsert = "INSERT INTO SIMP.dbo.REGISTRO_VAZAO_PRESSAO 
                          (CD_PONTO_MEDICAO, DT_LEITURA, DT_EVENTO_MEDICAO, {$coluna}, ID_SITUACAO, 
                           ID_TIPO_REGISTRO, ID_TIPO_MEDICAO, ID_TIPO_VAZAO,
                           CD_USUARIO_RESPONSAVEL, CD_USUARIO_ULTIMA_ATUALIZACAO, 
                           DT_ULTIMA_ATUALIZACAO, DS_OBSERVACAO)
                          VALUES 
                          (:cdPonto, :dtLeitura, GETDATE(), :valor, 1, 
                           2, 2, 2,
                           :cdUsuarioResp, :cdUsuarioAtu, 
                           GETDATE(), :observacao)";

            $stmtInsert = $pdoSIMP->prepare($sqlInsert);

            for ($minuto = 0; $minuto < 60; $minuto++) {
                $dtLeitura = sprintf('%s %02d:%02d:00', $data, $hora, $minuto);

                $stmtInsert->execute([
                    ':cdPonto' => $cdPonto,
                    ':dtLeitura' => $dtLeitura,
                    ':valor' => $novoValor,
                    ':cdUsuarioResp' => $cdUsuario,
                    ':cdUsuarioAtu' => $cdUsuario,
                    ':observacao' => $observacao ?: 'Valor sugerido e aplicado via IA'
                ]);
                $totalInseridos++;
            }

            $horasProcessadas[] = [
                'hora' => $hora,
                'valor' => $novoValor
            ];
        }

        $pdoSIMP->commit();

        // Reprocessar métricas da IA para a data validada (isolado)
        try {
            @include_once 'reprocessarMetricas.php';
            if (function_exists('reprocessarMetricasDiarias')) {
                reprocessarMetricasDiarias($pdoSIMP, $data);
            }
        } catch (Exception $reprocessEx) {
        }

        // Log (isolado)
        try {
            @include_once '../logHelper.php';
            if (function_exists('registrarLog')) {
                $qtdHoras = count($valores);
                $horasTexto = implode(', ', array_map(function ($v) {
                    return str_pad($v['hora'], 2, '0', STR_PAD_LEFT) . ':00 (' . number_format($v['valor'], 2, ',', '.') . ')';
                }, $valores));
                $identificador = "$nomePonto - $data";
                registrarLog(
                    'Validação dos Dados',
                    'VALIDACAO_IA',
                    "Validou dados via IA ($qtdHoras hora(s)). Valores: $horasTexto. $totalInativados descartados, $totalInseridos inseridos.",
                    ['cdPonto' => $cdPonto, 'data' => $data, 'valores' => $valores, 'identificador' => $identificador]
                );
            }
        } catch (Exception $logEx) {
        }

        $qtdHoras = count($valores);
        $mensagem = $totalInativados > 0
            ? "Dados validados com sucesso! {$totalInativados} registros substituídos e {$totalInseridos} novos registros criados em {$qtdHoras} hora(s)."
            : "Dados inseridos com sucesso! {$totalInseridos} novos registros criados em {$qtdHoras} hora(s).";

        echo json_encode([
            'success' => true,
            'message' => $mensagem,
            'registros_inativados' => $totalInativados,
            'registros_inseridos' => $totalInseridos,
            'horas_processadas' => $horasProcessadas
        ]);

    } catch (Exception $e) {
        $pdoSIMP->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    // Log de erro (isolado)
    try {
        @include_once '../logHelper.php';
        if (function_exists('registrarLogErro')) {
            registrarLogErro(
                'Validação dos Dados',
                'VALIDACAO_IA',
                $e->getMessage(),
                ['cdPonto' => $cdPonto ?? null, 'data' => $data ?? null, 'valores' => $valores ?? []]
            );
        }
    } catch (Exception $logEx) {
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
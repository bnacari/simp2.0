<?php
/**
 * SIMP - Validar/Corrigir dados horários
 * 
 * Para tipos normais (1, 2, 8):
 *   1. Marca registros existentes nas horas com ID_SITUACAO = 2
 *   2. Cria 60 novos registros por hora com o valor informado e ID_SITUACAO = 1
 * 
 * Para tipo 6 (Nível Reservatório) - MODO MANUAL:
 *   1. Marca registros existentes nas horas com ID_SITUACAO = 2 (descarta)
 *   2. Cria 60 novos registros por hora com VL_RESERVATORIO = 100, ID_SITUACAO = 1
 *   3. Distribui NR_EXTRAVASOU = 1 ALEATORIAMENTE em N registros (N = minutos informados)
 *   4. Define NR_MOTIVO e DS_OBSERVACAO em todos os novos registros
 * 
 * Para tipo 6 (Nível Reservatório) - MODO INTERVALO:
 *   1. Marca registros existentes nas horas com ID_SITUACAO = 2 (descarta)
 *   2. Cria 60 novos registros por hora com VL_RESERVATORIO = 100, ID_SITUACAO = 1
 *   3. Marca NR_EXTRAVASOU = 1 nos MINUTOS EXATOS do intervalo informado
 *      Ex: 10:30-11:30 → hora 10 min 30-59, hora 11 min 0-29
 *   4. Define NR_MOTIVO e DS_OBSERVACAO em todos os novos registros
 */

// DEBUG MODE - Definir como true para ver detalhes
$DEBUG = false;

header('Content-Type: application/json; charset=utf-8');

// Função para log de debug
function debugLog($message, $data = null) {
    global $DEBUG, $debugInfo;
    if ($DEBUG) {
        $debugInfo[] = [
            'message' => $message,
            'data' => $data,
            'time' => date('H:i:s.u')
        ];
    }
}

$debugInfo = [];

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar permissão
require_once __DIR__ . '/../../includes/auth.php';
if (!podeEditarTela('Validação dos Dados')) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão para esta operação', 'debug' => $debugInfo]);
    exit;
}

try {
    include_once '../conexao.php';
    debugLog('Conexão estabelecida');

    // Receber dados
    $rawInput = file_get_contents('php://input');
    debugLog('Input raw', $rawInput);
    
    $input = json_decode($rawInput, true);
    debugLog('Input parsed', $input);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Erro ao decodificar JSON: ' . json_last_error_msg());
    }
    
    $cdPonto = isset($input['cdPonto']) ? (int)$input['cdPonto'] : 0;
    $data = isset($input['data']) ? $input['data'] : '';
    $horas = isset($input['horas']) ? $input['horas'] : [];
    $tipoMedidor = isset($input['tipoMedidor']) ? (int)$input['tipoMedidor'] : 1;
    $observacao = isset($input['observacao']) ? trim($input['observacao']) : '';

    // Campos específicos por tipo
    $novoValor = isset($input['novoValor']) ? floatval($input['novoValor']) : null;
    $minutosExtravasou = isset($input['minutosExtravasou']) ? (int)$input['minutosExtravasou'] : 0;
    $motivo = isset($input['motivo']) ? (int)$input['motivo'] : null;
    
    // Campos para descarte por intervalo (tipo 6)
    $modoIntervalo = isset($input['modoIntervalo']) ? (bool)$input['modoIntervalo'] : false;
    $minutosDistribuidos = isset($input['minutosDistribuidos']) ? $input['minutosDistribuidos'] : null;
    $intervaloInicio = isset($input['intervaloInicio']) ? $input['intervaloInicio'] : null;
    $intervaloFim = isset($input['intervaloFim']) ? $input['intervaloFim'] : null;

    // Campo para minutos por hora individuais (tipo 6 - modo manual com extravasamento)
    $minutosPorHora = isset($input['minutosPorHora']) ? $input['minutosPorHora'] : null;

    debugLog('Parâmetros extraídos', [
        'cdPonto' => $cdPonto,
        'data' => $data,
        'horas' => $horas,
        'tipoMedidor' => $tipoMedidor,
        'novoValor' => $novoValor,
        'minutosExtravasou' => $minutosExtravasou,
        'motivo' => $motivo,
        'observacao' => $observacao,
        'modoIntervalo' => $modoIntervalo,
        'minutosDistribuidos' => $minutosDistribuidos,
        'intervaloInicio' => $intervaloInicio,
        'intervaloFim' => $intervaloFim,
        'minutosPorHora' => $minutosPorHora
    ]);

    // Compatibilidade: se receber 'hora' único, converter para array
    if (empty($horas) && isset($input['hora'])) {
        $horas = [(int)$input['hora']];
    }

    // Validações comuns
    if ($cdPonto <= 0) {
        throw new Exception('Ponto de medição inválido');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        throw new Exception('Data inválida');
    }
    if (empty($horas) || !is_array($horas)) {
        throw new Exception('Selecione pelo menos uma hora');
    }
    foreach ($horas as $hora) {
        if ($hora < 0 || $hora > 23) {
            throw new Exception('Hora inválida: ' . $hora);
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
    } catch (Exception $e) {}

    // Obter usuário logado
    $cdUsuario = $_SESSION['cd_usuario'] ?? null;
    debugLog('Usuário logado', $cdUsuario);

    // Iniciar transação
    $pdoSIMP->beginTransaction();
    debugLog('Transação iniciada');

    try {
        // Lógica diferente para tipo 6 (Nível Reservatório)
        if ($tipoMedidor == 6) {
            debugLog('Processando tipo 6 (Nível Reservatório)');
            debugLog('Modo intervalo', $modoIntervalo);
            
            // Validações específicas para tipo 6
            if (!$modoIntervalo) {
                // Modo manual: validar minutos (0 a 60)
                if ($minutosExtravasou < 0 || $minutosExtravasou > 60) {
                    throw new Exception('Minutos >= 100 deve ser entre 0 e 60');
                }
            } else {
                // Modo intervalo: validar distribuição
                if (empty($minutosDistribuidos)) {
                    throw new Exception('Distribuição de minutos por hora é obrigatória no modo intervalo');
                }
                if (empty($intervaloInicio) || empty($intervaloFim)) {
                    throw new Exception('Horário de início e fim são obrigatórios');
                }
                // Validar que cada hora tem dados válidos
                foreach ($minutosDistribuidos as $h => $dados) {
                    $mi = (int)($dados['minutoInicio'] ?? -1);
                    $mf = (int)($dados['minutoFim'] ?? -1);
                    if ($mi < 0 || $mi > 59 || $mf < 0 || $mf > 59 || $mf < $mi) {
                        throw new Exception("Intervalo de minutos inválido para hora {$h}: {$mi}-{$mf}");
                    }
                }
                debugLog('Validação intervalo OK', $minutosDistribuidos);
            }
            if ($motivo !== 1 && $motivo !== 2) {
                throw new Exception('Selecione o evento (Falha ou Extravasamento)');
            }

            $totalInativados = 0;
            $totalInseridos = 0;

            foreach ($horas as $hora) {
                $hora = (int)$hora;
                debugLog("Processando hora: {$hora}");
                
                // 1. Marcar registros existentes na hora como inativos (ID_SITUACAO = 2)
                $sqlInativar = "UPDATE SIMP.dbo.REGISTRO_VAZAO_PRESSAO 
                                SET ID_SITUACAO = 2,
                                    CD_USUARIO_ULTIMA_ATUALIZACAO = :cdUsuario,
                                    DT_ULTIMA_ATUALIZACAO = GETDATE()
                                WHERE CD_PONTO_MEDICAO = :cdPonto
                                  AND CAST(DT_LEITURA AS DATE) = :data
                                  AND DATEPART(HOUR, DT_LEITURA) = :hora
                                  AND ID_SITUACAO = 1";
                
                debugLog('SQL Inativar', $sqlInativar);
                debugLog('Params Inativar', [
                    'cdUsuario' => $cdUsuario,
                    'cdPonto' => $cdPonto,
                    'data' => $data,
                    'hora' => $hora
                ]);
                
                $stmtInativar = $pdoSIMP->prepare($sqlInativar);
                $stmtInativar->execute([
                    ':cdUsuario' => $cdUsuario,
                    ':cdPonto' => $cdPonto,
                    ':data' => $data,
                    ':hora' => $hora
                ]);
                $inativados = $stmtInativar->rowCount();
                $totalInativados += $inativados;
                debugLog("Registros inativados na hora {$hora}", $inativados);
                
                // 2. Determinar quais minutos terão NR_EXTRAVASOU = 1
                $minutosComExtravasou = [];
                
                if ($modoIntervalo && $minutosDistribuidos && isset($minutosDistribuidos[$hora])) {
                    // ========================================
                    // MODO INTERVALO: marcar minutos EXATOS
                    // Ex: intervalo 10:30-11:30
                    //   hora 10 → minutos 30 a 59 (range)
                    //   hora 11 → minutos 0 a 29 (range)
                    // ========================================
                    $dadosHora = $minutosDistribuidos[$hora];
                    $minInicio = (int)$dadosHora['minutoInicio'];
                    $minFim = (int)$dadosHora['minutoFim'];
                    $minutosComExtravasou = range($minInicio, $minFim);
                    debugLog("MODO INTERVALO - Hora {$hora}: min {$minInicio} a {$minFim} = " . count($minutosComExtravasou) . " min com NR_EXTRAVASOU=1", $minutosComExtravasou);
                } elseif ($minutosPorHora !== null && isset($minutosPorHora[$hora])) {
                    // ========================================
                    // MODO MANUAL POR HORA: minutos individuais por hora
                    // ========================================
                    $minutosHora = min((int)$minutosPorHora[$hora], 60);
                    if ($minutosHora > 0) {
                        $todosMinutos = range(0, 59);
                        shuffle($todosMinutos);
                        $minutosComExtravasou = array_slice($todosMinutos, 0, $minutosHora);
                    }
                    debugLog("MODO MANUAL POR HORA - Hora {$hora}: {$minutosHora} min aleatórios com NR_EXTRAVASOU=1", $minutosComExtravasou);
                } else {
                    // ========================================
                    // MODO MANUAL: distribuição aleatória (comportamento original)
                    // ========================================
                    if ($minutosExtravasou > 0) {
                        $todosMinutos = range(0, 59);
                        shuffle($todosMinutos);
                        $minutosComExtravasou = array_slice($todosMinutos, 0, $minutosExtravasou);
                    }
                    debugLog("MODO MANUAL - Hora {$hora}: {$minutosExtravasou} min aleatórios com NR_EXTRAVASOU=1", $minutosComExtravasou);
                }

                // 3. Criar 60 novos registros (um para cada minuto)
                $sqlInsert = "INSERT INTO SIMP.dbo.REGISTRO_VAZAO_PRESSAO 
                              (CD_PONTO_MEDICAO, DT_LEITURA, DT_EVENTO_MEDICAO, VL_RESERVATORIO, ID_SITUACAO, 
                               ID_TIPO_REGISTRO, ID_TIPO_MEDICAO, ID_TIPO_VAZAO,
                               NR_EXTRAVASOU, NR_MOTIVO,
                               CD_USUARIO_RESPONSAVEL, CD_USUARIO_ULTIMA_ATUALIZACAO, 
                               DT_ULTIMA_ATUALIZACAO, DS_OBSERVACAO)
                              VALUES 
                              (:cdPonto, :dtLeitura, GETDATE(), 100, 1, 
                               2, 2, 1,
                               :extravasou, :motivo,
                               :cdUsuarioResp, :cdUsuarioAtu, 
                               GETDATE(), :observacao)";
                
                debugLog('SQL Insert', $sqlInsert);
                
                // Montar observação com detalhes do intervalo
                $obsTexto = $observacao ?: 'Valor inserido/corrigido manualmente via sistema';
                if ($modoIntervalo && $intervaloInicio && $intervaloFim) {
                    $obsTexto = ($observacao ? $observacao . ' | ' : '') . "Descarte por intervalo: {$intervaloInicio} às {$intervaloFim}";
                }
                
                $stmtInsert = $pdoSIMP->prepare($sqlInsert);
                
                for ($minuto = 0; $minuto < 60; $minuto++) {
                    $dtLeitura = sprintf('%s %02d:%02d:00', $data, $hora, $minuto);
                    $extravasou = in_array($minuto, $minutosComExtravasou) ? 1 : 0;
                    
                    $params = [
                        ':cdPonto' => $cdPonto,
                        ':dtLeitura' => $dtLeitura,
                        ':extravasou' => $extravasou,
                        ':motivo' => $motivo,
                        ':cdUsuarioResp' => $cdUsuario,
                        ':cdUsuarioAtu' => $cdUsuario,
                        ':observacao' => $obsTexto
                    ];
                    
                    if ($minuto == 0) {
                        debugLog('Params Insert (primeiro registro)', $params);
                    }
                    
                    $stmtInsert->execute($params);
                    $totalInseridos++;
                }
                debugLog("Registros inseridos na hora {$hora}", 60);
            }

            $pdoSIMP->commit();
            debugLog('Transação commitada');

            // Log (isolado)
            try {
                @include_once '../logHelper.php';
                if (function_exists('registrarLog')) {
                    $qtdHoras = count($horas);
                    $motivoTexto = $motivo == 1 ? 'Falha' : 'Extravasamento';
                    $horasTexto = implode(', ', array_map(function($h) { return str_pad($h, 2, '0', STR_PAD_LEFT) . ':00'; }, $horas));
                    $identificador = "$nomePonto - $data";
                    
                    if ($modoIntervalo) {
                        // Log detalhado para modo intervalo
                        $detalheDist = [];
                        foreach ($minutosDistribuidos as $h => $d) {
                            $detalheDist[] = str_pad($h, 2, '0', STR_PAD_LEFT) . 'h=' . $d['minutos'] . 'min';
                        }
                        $detalheTexto = implode(', ', $detalheDist);
                        registrarLog('Validação dos Dados', 'VALIDACAO_NIVEL_INTERVALO', 
                            "Descarte por intervalo ({$intervaloInicio} às {$intervaloFim}). {$qtdHoras} hora(s): {$detalheTexto}. Evento: $motivoTexto. $totalInativados descartados, $totalInseridos inseridos.",
                            ['cdPonto' => $cdPonto, 'data' => $data, 'horas' => $horas, 'intervaloInicio' => $intervaloInicio, 'intervaloFim' => $intervaloFim, 'minutosDistribuidos' => $minutosDistribuidos, 'motivo' => $motivoTexto, 'identificador' => $identificador]);
                    } else {
                        registrarLog('Validação dos Dados', 'VALIDACAO_NIVEL', 
                            "Validou dados de nível ($qtdHoras hora(s): $horasTexto). Evento: $motivoTexto. $totalInativados descartados, $totalInseridos inseridos.",
                            ['cdPonto' => $cdPonto, 'data' => $data, 'horas' => $horas, 'minutosExtravasou' => $minutosExtravasou, 'motivo' => $motivoTexto, 'identificador' => $identificador]);
                    }
                }
            } catch (Exception $logEx) {}

            $qtdHoras = count($horas);
            $motivoTexto = $motivo == 1 ? 'Falha' : 'Extravasamento';
            
            if ($modoIntervalo) {
                $totalMinIntervalo = 0;
                foreach ($minutosDistribuidos as $d) {
                    $totalMinIntervalo += (int)$d['minutos'];
                }
                $mensagem = "Validação por intervalo realizada! Período: {$intervaloInicio} às {$intervaloFim} ({$totalMinIntervalo} min). {$totalInativados} registros descartados e {$totalInseridos} novos criados em {$qtdHoras} hora(s). Evento: {$motivoTexto}.";
            } elseif ($minutosPorHora !== null) {
                $totalMinPorHora = 0;
                foreach ($minutosPorHora as $m) {
                    $totalMinPorHora += (int)$m;
                }
                $mensagem = "Dados de nível validados! {$totalInativados} registros descartados e {$totalInseridos} novos criados em {$qtdHoras} hora(s). Total Min >= 100: {$totalMinPorHora}. Evento: {$motivoTexto}.";
            } else {
                $mensagem = "Dados de nível validados! {$totalInativados} registros descartados e {$totalInseridos} novos criados em {$qtdHoras} hora(s). Min >= 100: {$minutosExtravasou} por hora. Evento: {$motivoTexto}.";
            }

            echo json_encode([
                'success' => true,
                'message' => $mensagem,
                'registros_inativados' => $totalInativados,
                'registros_inseridos' => $totalInseridos,
                'horas_processadas' => $qtdHoras,
                'motivo' => $motivoTexto,
                'modoIntervalo' => $modoIntervalo,
                'debug' => $DEBUG ? $debugInfo : null
            ]);

        } else {
            // Lógica padrão para outros tipos de medidor
            
            // Validação específica
            if ($novoValor === null) {
                throw new Exception('Novo valor é obrigatório');
            }

            // Definir coluna baseado no tipo de medidor
            $colunasPorTipo = [
                1 => 'VL_VAZAO_EFETIVA',
                2 => 'VL_VAZAO_EFETIVA',
                4 => 'VL_PRESSAO',
                8 => 'VL_VAZAO_EFETIVA'
            ];
            $coluna = $colunasPorTipo[$tipoMedidor] ?? 'VL_VAZAO_EFETIVA';

            $totalInativados = 0;
            $totalInseridos = 0;

            foreach ($horas as $hora) {
                $hora = (int)$hora;
                
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
                               2, 2, 1,
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
                        ':observacao' => $observacao ?: 'Valor inserido/corrigido manualmente via sistema'
                    ]);
                    $totalInseridos++;
                }
            }

            $pdoSIMP->commit();

            // Log (isolado)
            try {
                @include_once '../logHelper.php';
                if (function_exists('registrarLog')) {
                    $qtdHoras = count($horas);
                    $horasTexto = implode(', ', array_map(function($h) { return str_pad($h, 2, '0', STR_PAD_LEFT) . ':00'; }, $horas));
                    $identificador = "$nomePonto - $data";
                    registrarLog('Validação dos Dados', 'VALIDACAO', 
                        "Validou dados ($qtdHoras hora(s): $horasTexto). Novo valor: $novoValor. $totalInativados descartados, $totalInseridos inseridos.",
                        ['cdPonto' => $cdPonto, 'data' => $data, 'horas' => $horas, 'novoValor' => $novoValor, 'coluna' => $coluna, 'identificador' => $identificador]);
                }
            } catch (Exception $logEx) {}

            $qtdHoras = count($horas);
            $mensagem = $totalInativados > 0 
                ? "Dados validados com sucesso! {$totalInativados} registros inativados e {$totalInseridos} novos registros criados em {$qtdHoras} hora(s)."
                : "Dados inseridos com sucesso! {$totalInseridos} novos registros criados em {$qtdHoras} hora(s).";

            echo json_encode([
                'success' => true,
                'message' => $mensagem,
                'registros_inativados' => $totalInativados,
                'registros_inseridos' => $totalInseridos,
                'horas_processadas' => $qtdHoras,
                'debug' => $DEBUG ? $debugInfo : null
            ]);
        }

    } catch (Exception $e) {
        debugLog('Erro na transação', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        $pdoSIMP->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    debugLog('Erro final', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

    // Log de erro (isolado)
    try {
        @include_once '../logHelper.php';
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Validação dos Dados', 'VALIDACAO', $e->getMessage(), 
                ['cdPonto' => $cdPonto ?? null, 'data' => $data ?? null, 'horas' => $horas ?? []]);
        }
    } catch (Exception $logEx) {}

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_details' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ],
        'debug' => $DEBUG ? $debugInfo : null
    ]);
}
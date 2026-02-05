<?php
/**
 * SIMP - Registro de Vazão e Pressão
 * Endpoint: Descartar/Excluir Registros em Massa
 * COM REGISTRO DE LOG
 * ATUALIZADO: Inclui CD_PONTO_MEDICAO no log
 * 
 * Lógica:
 * - Registros com ID_SITUACAO = 1: Soft Delete (muda para 2)
 * - Registros com ID_SITUACAO = 2: Hard Delete (remove permanentemente)
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

// Aumentar tempo limite para operações em massa
set_time_limit(0);
ini_set('max_execution_time', 0);

try {
    require_once '../verificarAuth.php';
    verificarPermissaoAjax('REGISTRO DE VAZÃO', ACESSO_ESCRITA);

    include_once '../conexao.php';
    @include_once '../logHelper.php';

    // Ler dados - tentar JSON primeiro, depois POST
    $chaves = [];

    $input = file_get_contents('php://input');

    if (!empty($input)) {
        $data = json_decode($input, true);
        if ($data && isset($data['chaves']) && is_array($data['chaves'])) {
            $chaves = $data['chaves'];
        }
    }

    // Fallback para POST tradicional
    if (empty($chaves) && isset($_POST['chaves'])) {
        if (is_array($_POST['chaves'])) {
            $chaves = $_POST['chaves'];
        } else {
            $chaves = json_decode($_POST['chaves'], true);
        }
    }

    if (empty($chaves) || !is_array($chaves)) {
        throw new Exception('Nenhum registro selecionado para exclusão');
    }

    // Validar que todas as chaves são inteiros
    $chavesValidas = [];
    foreach ($chaves as $chave) {
        if (is_numeric($chave) && $chave > 0) {
            $chavesValidas[] = (int) $chave;
        }
    }

    if (empty($chavesValidas)) {
        throw new Exception('Nenhuma chave válida encontrada');
    }

    $cdUsuario = $_SESSION['cd_usuario'] ?? null;

    // Buscar informações e separar por situação
    // Processar em lotes de 2000 para evitar limite de parâmetros do SQL Server
    $registros = [];
    $lotesSelect = array_chunk($chavesValidas, 2000);

    foreach ($lotesSelect as $loteChaves) {
        $placeholdersSelect = implode(',', array_fill(0, count($loteChaves), '?'));
        $sqlBusca = "SELECT RVP.CD_CHAVE, RVP.CD_PONTO_MEDICAO, RVP.DT_LEITURA, RVP.ID_SITUACAO, 
                            PM.DS_NOME AS DS_PONTO_MEDICAO, L.CD_UNIDADE
                     FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
                     LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                     LEFT JOIN SIMP.dbo.LOCALIDADE L ON L.CD_CHAVE = PM.CD_LOCALIDADE
                     WHERE RVP.CD_CHAVE IN ($placeholdersSelect)";
        $stmtBusca = $pdoSIMP->prepare($sqlBusca);
        $stmtBusca->execute($loteChaves);
        $registros = array_merge($registros, $stmtBusca->fetchAll(PDO::FETCH_ASSOC));
    }

    // Separar registros por situação
    $chavesSoftDelete = [];  // ID_SITUACAO = 1
    $chavesHardDelete = [];  // ID_SITUACAO = 2
    $pontosSoftDelete = [];
    $pontosHardDelete = [];

    foreach ($registros as $reg) {
        $cdPonto = $reg['CD_PONTO_MEDICAO'];

        if ($reg['ID_SITUACAO'] == 1) {
            $chavesSoftDelete[] = $reg['CD_CHAVE'];
            if (!isset($pontosSoftDelete[$cdPonto])) {
                $pontosSoftDelete[$cdPonto] = [
                    'cd_ponto_medicao' => $cdPonto, // Inclui CD_PONTO_MEDICAO
                    'nome' => $reg['DS_PONTO_MEDICAO'],
                    'quantidade' => 0
                ];
            }
            $pontosSoftDelete[$cdPonto]['quantidade']++;
        } elseif ($reg['ID_SITUACAO'] == 2) {
            $chavesHardDelete[] = $reg['CD_CHAVE'];
            if (!isset($pontosHardDelete[$cdPonto])) {
                $pontosHardDelete[$cdPonto] = [
                    'cd_ponto_medicao' => $cdPonto, // Inclui CD_PONTO_MEDICAO
                    'nome' => $reg['DS_PONTO_MEDICAO'],
                    'quantidade' => 0
                ];
            }
            $pontosHardDelete[$cdPonto]['quantidade']++;
        }
    }

    // Iniciar transação
    $pdoSIMP->beginTransaction();

    $descartados = 0;  // Soft delete
    $deletados = 0;    // Hard delete

    // ========== SOFT DELETE: ID_SITUACAO = 1 → 2 ==========
    if (!empty($chavesSoftDelete)) {
        // Usar lotes de 500 para evitar limites do SQL Server (2100 params máx)
        $lotes = array_chunk($chavesSoftDelete, 500);

        foreach ($lotes as $lote) {
            $placeholders = implode(',', array_fill(0, count($lote), '?'));

            $sqlUpdate = "UPDATE SIMP.dbo.REGISTRO_VAZAO_PRESSAO 
                          SET ID_SITUACAO = 2,
                              DT_ULTIMA_ATUALIZACAO = GETDATE(),
                              CD_USUARIO_ULTIMA_ATUALIZACAO = ?
                          WHERE CD_CHAVE IN ($placeholders) 
                          AND ID_SITUACAO = 1";

            $stmtUpdate = $pdoSIMP->prepare($sqlUpdate);
            $params = array_merge([$cdUsuario], $lote);
            $stmtUpdate->execute($params);

            // rowCount() pode não funcionar bem com SQL Server, então contamos as chaves do lote
            $descartados += count($lote);
        }
    }

    // ========== HARD DELETE: Remove permanentemente ==========
    if (!empty($chavesHardDelete)) {
        // Usar lotes de 500 para evitar limites do SQL Server
        $lotes = array_chunk($chavesHardDelete, 500);

        foreach ($lotes as $lote) {
            $placeholders = implode(',', array_fill(0, count($lote), '?'));

            $sqlDelete = "DELETE FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO 
                          WHERE CD_CHAVE IN ($placeholders)";

            $stmtDelete = $pdoSIMP->prepare($sqlDelete);
            $stmtDelete->execute($lote);
            $deletados += count($lote);
        }
    }

    // Commit
    $pdoSIMP->commit();

    // Reprocessar métricas (isolado)
    try {
        @include_once '../operacoes/reprocessarMetricas.php';
        if (function_exists('reprocessarMetricasMultiplasDatas')) {
            // Identificar datas afetadas a partir dos registros alterados
            $sqlDatas = "SELECT DISTINCT CAST(DT_LEITURA AS DATE) AS DT 
                     FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO 
                     WHERE CD_CHAVE IN ($placeholders)";
            $stmtDatas = $pdoSIMP->prepare($sqlDatas);
            $stmtDatas->execute($chavesValidas);
            $datasAfetadas = $stmtDatas->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($datasAfetadas)) {
                reprocessarMetricasMultiplasDatas($pdoSIMP, $datasAfetadas);
            }
        }
    } catch (Exception $reprocessEx) {
    }

    // Registrar logs (isolado)
    if (function_exists('registrarLogAlteracaoMassa')) {
        try {
            // Log de soft delete
            if ($descartados > 0) {
                $resumoPontos = [];
                foreach ($pontosSoftDelete as $cdPonto => $info) {
                    // Formato: CD_PONTO_MEDICAO-DS_NOME (X reg.)
                    $resumoPontos[] = $info['cd_ponto_medicao'] . '-' . $info['nome'] . ' (' . $info['quantidade'] . ' reg.)';
                }

                $contexto = [
                    'total_descartados' => $descartados,
                    'pontos_afetados' => $resumoPontos,
                    'acao' => 'DESCARTE EM MASSA (soft delete)'
                ];

                registrarLogAlteracaoMassa('Registro de Vazão e Pressão', 'Registro Vazão/Pressão', $descartados, 'Descarte em lote', $contexto);
            }

            // Log de hard delete
            if ($deletados > 0) {
                $resumoPontos = [];
                foreach ($pontosHardDelete as $cdPonto => $info) {
                    // Formato: CD_PONTO_MEDICAO-DS_NOME (X reg.)
                    $resumoPontos[] = $info['cd_ponto_medicao'] . '-' . $info['nome'] . ' (' . $info['quantidade'] . ' reg.)';
                }

                $contexto = [
                    'total_deletados' => $deletados,
                    'pontos_afetados' => $resumoPontos,
                    'acao' => 'EXCLUSÃO PERMANENTE EM MASSA (hard delete)'
                ];

                registrarLogAlteracaoMassa('Registro de Vazão e Pressão', 'Registro Vazão/Pressão', $deletados, 'Exclusão permanente em lote', $contexto);
            }
        } catch (Exception $logEx) {
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Operação concluída com sucesso',
        'descartados' => $descartados,
        'deletados' => $deletados,
        'solicitados' => count($chavesValidas),
        'debug' => [
            'chaves_recebidas' => count($chaves),
            'chaves_validas' => count($chavesValidas),
            'registros_encontrados' => count($registros),
            'soft_delete_candidatos' => count($chavesSoftDelete),
            'hard_delete_candidatos' => count($chavesHardDelete)
        ]
    ]);

} catch (PDOException $e) {
    if (isset($pdoSIMP) && $pdoSIMP->inTransaction()) {
        $pdoSIMP->rollBack();
    }

    // Registrar log de erro (isolado)
    if (function_exists('registrarLogErro')) {
        try {
            registrarLogErro('Registro de Vazão e Pressão', 'DELETE_MASSA', $e->getMessage(), ['chaves' => count($chavesValidas ?? [])]);
        } catch (Exception $ex) {
        }
    }

    // Verificar se é erro da trigger
    if (strpos($e->getMessage(), '9999998') !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Alguns registros não podem ser excluídos porque já foram exportados para SIGAO.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao processar: ' . $e->getMessage()
        ]);
    }

} catch (Exception $e) {
    if (isset($pdoSIMP) && $pdoSIMP->inTransaction()) {
        $pdoSIMP->rollBack();
    }

    // Registrar log de erro (isolado)
    if (function_exists('registrarLogErro')) {
        try {
            registrarLogErro('Registro de Vazão e Pressão', 'DELETE_MASSA', $e->getMessage(), ['chaves' => count($chavesValidas ?? [])]);
        } catch (Exception $ex) {
        }
    }

    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
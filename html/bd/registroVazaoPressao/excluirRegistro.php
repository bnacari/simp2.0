<?php
/**
 * SIMP - Registro de Vazão e Pressão
 * Endpoint: Excluir Registro Individual
 * COM REGISTRO DE LOG
 * 
 * Lógica:
 * - Se ID_SITUACAO = 1: Soft Delete (muda para 2)
 * - Se ID_SITUACAO = 2: Hard Delete (remove permanentemente)
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

try {
    require_once '../verificarAuth.php';
    verificarPermissaoAjax('REGISTRO DE VAZÃO', ACESSO_ESCRITA);

    include_once '../conexao.php';
    @include_once '../logHelper.php';

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    // Buscar dados do registro (para validação e log)
    $sqlBusca = "SELECT RVP.*, PM.DS_NOME AS DS_PONTO_MEDICAO, L.CD_UNIDADE
                 FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO RVP
                 LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = RVP.CD_PONTO_MEDICAO
                 LEFT JOIN SIMP.dbo.LOCALIDADE L ON L.CD_CHAVE = PM.CD_LOCALIDADE
                 WHERE RVP.CD_CHAVE = :id";
    $stmtBusca = $pdoSIMP->prepare($sqlBusca);
    $stmtBusca->execute([':id' => $id]);
    $dadosRegistro = $stmtBusca->fetch(PDO::FETCH_ASSOC);

    if (!$dadosRegistro) {
        echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
        exit;
    }

    $cdUnidadeLog = $dadosRegistro['CD_UNIDADE'] ?? null;
    $dsPontoMedicao = $dadosRegistro['DS_PONTO_MEDICAO'] ?? '';
    $dtLeitura = $dadosRegistro['DT_LEITURA'] ?? '';
    $idSituacao = $dadosRegistro['ID_SITUACAO'];

    if ($idSituacao == 1) {
        // ========== SOFT DELETE: ID_SITUACAO = 1 → 2 ==========
        $sql = "UPDATE SIMP.dbo.REGISTRO_VAZAO_PRESSAO 
                SET ID_SITUACAO = 2, 
                    DT_ULTIMA_ATUALIZACAO = GETDATE(),
                    CD_USUARIO_ULTIMA_ATUALIZACAO = :cd_usuario
                WHERE CD_CHAVE = :id AND ID_SITUACAO = 1";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':cd_usuario' => $_SESSION['cd_usuario'] ?? null
        ]);

        if ($stmt->rowCount() > 0) {
            // Log de soft delete (isolado)
            if (function_exists('registrarLogUpdate')) {
                try {
                    $identificador = "Ponto: $dsPontoMedicao | Data: $dtLeitura";
                    $dadosLog = [
                        'CD_CHAVE' => $id,
                        'CD_PONTO_MEDICAO' => $dadosRegistro['CD_PONTO_MEDICAO'] ?? null,
                        'DS_PONTO_MEDICAO' => $dsPontoMedicao,
                        'DT_LEITURA' => $dtLeitura,
                        'VL_VAZAO_EFETIVA' => $dadosRegistro['VL_VAZAO_EFETIVA'] ?? null,
                        'VL_PRESSAO' => $dadosRegistro['VL_PRESSAO'] ?? null,
                        'acao' => 'DESCARTE (soft delete)',
                        'ID_SITUACAO_ANTERIOR' => 1,
                        'ID_SITUACAO_NOVO' => 2
                    ];
                    registrarLogUpdate('Registro de Vazão e Pressão', 'Registro Vazão/Pressão', $id, $identificador, $dadosLog, $cdUnidadeLog);
                } catch (Exception $logEx) {}
            }

            echo json_encode([
                'success' => true, 
                'message' => 'Registro descartado com sucesso',
                'tipo' => 'soft_delete'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao descartar registro']);
        }

    } elseif ($idSituacao == 2) {
        // ========== HARD DELETE: Remove permanentemente ==========
        $sql = "DELETE FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO WHERE CD_CHAVE = :id";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() > 0) {
            // Log de hard delete (isolado)
            if (function_exists('registrarLogDelete')) {
                try {
                    $identificador = "Ponto: $dsPontoMedicao | Data: $dtLeitura (PERMANENTE)";
                    $dadosLog = [
                        'CD_CHAVE' => $id,
                        'CD_PONTO_MEDICAO' => $dadosRegistro['CD_PONTO_MEDICAO'] ?? null,
                        'DS_PONTO_MEDICAO' => $dsPontoMedicao,
                        'DT_LEITURA' => $dtLeitura,
                        'VL_VAZAO_EFETIVA' => $dadosRegistro['VL_VAZAO_EFETIVA'] ?? null,
                        'VL_PRESSAO' => $dadosRegistro['VL_PRESSAO'] ?? null,
                        'acao' => 'EXCLUSÃO PERMANENTE (hard delete)'
                    ];
                    registrarLogDelete('Registro de Vazão e Pressão', 'Registro Vazão/Pressão', $id, $identificador, $dadosLog, $cdUnidadeLog);
                } catch (Exception $logEx) {}
            }

            echo json_encode([
                'success' => true, 
                'message' => 'Registro removido permanentemente',
                'tipo' => 'hard_delete'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao remover registro']);
        }

    } else {
        echo json_encode(['success' => false, 'message' => 'Status do registro inválido']);
    }

} catch (PDOException $e) {
    // Registrar log de erro (isolado)
    if (function_exists('registrarLogErro')) { 
        try { registrarLogErro('Registro de Vazão e Pressão', 'DELETE', $e->getMessage(), ['id' => $id ?? null]); } catch (Exception $ex) {} 
    }

    // Verificar se é erro da trigger
    if (strpos($e->getMessage(), '9999998') !== false) {
        echo json_encode([
            'success' => false, 
            'message' => 'Este registro não pode ser excluído porque já foi exportado para SIGAO.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao processar: ' . $e->getMessage()]);
    }

} catch (Exception $e) {
    // Registrar log de erro (isolado)
    if (function_exists('registrarLogErro')) { 
        try { registrarLogErro('Registro de Vazão e Pressão', 'DELETE', $e->getMessage(), ['id' => $id ?? null]); } catch (Exception $ex) {} 
    }

    echo json_encode(['success' => false, 'message' => 'Erro ao processar: ' . $e->getMessage()]);
}
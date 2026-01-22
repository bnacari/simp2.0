<?php
/**
 * SIMP - Registro de Vazão e Pressão
 * Endpoint: Restaurar Registro Individual
 * COM REGISTRO DE LOG
 * ATUALIZADO: Inclui CD_PONTO_MEDICAO no log
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

    // Verificar se já está ativo
    if ($dadosRegistro['ID_SITUACAO'] == 1) {
        echo json_encode(['success' => false, 'message' => 'Registro já está ativo (ID_SITUACAO = 1)']);
        exit;
    }

    $cdUnidadeLog = $dadosRegistro['CD_UNIDADE'] ?? null;
    $dsPontoMedicao = $dadosRegistro['DS_PONTO_MEDICAO'] ?? '';
    $cdPontoMedicao = $dadosRegistro['CD_PONTO_MEDICAO'] ?? null;
    $dtLeitura = $dadosRegistro['DT_LEITURA'] ?? '';

    $cdUsuario = $_SESSION['cd_usuario'] ?? null;

    // Executar UPDATE para restaurar (ID_SITUACAO = 2 → 1)
    $sql = "UPDATE SIMP.dbo.REGISTRO_VAZAO_PRESSAO 
            SET ID_SITUACAO = 1, 
                DT_ULTIMA_ATUALIZACAO = GETDATE(),
                CD_USUARIO_ULTIMA_ATUALIZACAO = :cd_usuario
            WHERE CD_CHAVE = :id AND ID_SITUACAO = 2";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':cd_usuario' => $cdUsuario
    ]);

    if ($stmt->rowCount() > 0) {
        // Log de restauração (isolado)
        if (function_exists('registrarLogUpdate')) {
            try {
                // Formato: CD_PONTO_MEDICAO-DS_NOME | Data
                $identificador = "$cdPontoMedicao-$dsPontoMedicao | Data: $dtLeitura";
                $dadosLog = [
                    'CD_CHAVE' => $id,
                    'CD_PONTO_MEDICAO' => $cdPontoMedicao,
                    'DS_PONTO_MEDICAO' => $dsPontoMedicao,
                    'DT_LEITURA' => $dtLeitura,
                    'VL_VAZAO_EFETIVA' => $dadosRegistro['VL_VAZAO_EFETIVA'] ?? null,
                    'VL_PRESSAO' => $dadosRegistro['VL_PRESSAO'] ?? null,
                    'acao' => 'RESTAURAÇÃO (ID_SITUACAO: 2 → 1)',
                    'ID_SITUACAO_ANTERIOR' => 2,
                    'ID_SITUACAO_NOVO' => 1
                ];
                registrarLogUpdate('Registro de Vazão e Pressão', 'Registro Vazão/Pressão', $id, $identificador, $dadosLog, $cdUnidadeLog);
            } catch (Exception $logEx) {}
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Registro restaurado com sucesso'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao restaurar registro']);
    }

} catch (PDOException $e) {
    // Registrar log de erro (isolado)
    if (function_exists('registrarLogErro')) { 
        try { registrarLogErro('Registro de Vazão e Pressão', 'RESTAURAR', $e->getMessage(), ['id' => $id ?? null]); } catch (Exception $ex) {} 
    }

    echo json_encode(['success' => false, 'message' => 'Erro ao processar: ' . $e->getMessage()]);

} catch (Exception $e) {
    // Registrar log de erro (isolado)
    if (function_exists('registrarLogErro')) { 
        try { registrarLogErro('Registro de Vazão e Pressão', 'RESTAURAR', $e->getMessage(), ['id' => $id ?? null]); } catch (Exception $ex) {} 
    }

    echo json_encode(['success' => false, 'message' => 'Erro ao processar: ' . $e->getMessage()]);
}
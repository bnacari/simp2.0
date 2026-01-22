<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Excluir Conjunto Motor-Bomba
 * COM REGISTRO DE LOG
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
require_once '../logHelper.php';

include_once '../conexao.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Buscar dados antes de excluir (para log)
    $sqlBusca = "SELECT CMB.*, L.CD_UNIDADE 
                 FROM SIMP.dbo.CONJUNTO_MOTOR_BOMBA CMB
                 LEFT JOIN SIMP.dbo.LOCALIDADE L ON L.CD_CHAVE = CMB.CD_LOCALIDADE
                 WHERE CMB.CD_CHAVE = :id";
    $stmtBusca = $pdoSIMP->prepare($sqlBusca);
    $stmtBusca->execute([':id' => $id]);
    $dadosExcluidos = $stmtBusca->fetch(PDO::FETCH_ASSOC);

    if (!$dadosExcluidos) {
        throw new Exception('Registro não encontrado');
    }

    $identificador = ($dadosExcluidos['DS_CODIGO'] ?? '') . ' - ' . ($dadosExcluidos['DS_NOME'] ?? "ID: $id");
    $cdUnidadeLog = $dadosExcluidos['CD_UNIDADE'] ?? null;

    // Forçar modo de exceções para capturar erros de FK
    $pdoSIMP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "DELETE FROM SIMP.dbo.CONJUNTO_MOTOR_BOMBA WHERE CD_CHAVE = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);

    // Registrar log de DELETE
    try {
        $dadosLog = [
            'CD_CHAVE' => $id,
            'DS_CODIGO' => $dadosExcluidos['DS_CODIGO'] ?? null,
            'DS_NOME' => $dadosExcluidos['DS_NOME'] ?? null,
            'TP_EIXO' => $dadosExcluidos['TP_EIXO'] ?? null,
            'VL_POTENCIA_MOTOR' => $dadosExcluidos['VL_POTENCIA_MOTOR'] ?? null,
            'VL_VAZAO_BOMBA' => $dadosExcluidos['VL_VAZAO_BOMBA'] ?? null,
            'CD_LOCALIDADE' => $dadosExcluidos['CD_LOCALIDADE'] ?? null,
            'DS_LOCALIZACAO' => $dadosExcluidos['DS_LOCALIZACAO'] ?? null
        ];
        registrarLogDelete('Conjunto Motor-Bomba', 'Motor-Bomba', $id, $identificador, $dadosLog, $cdUnidadeLog);
    } catch (Exception $logEx) {
        error_log('Erro ao registrar log de DELETE Motor-Bomba: ' . $logEx->getMessage());
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Conjunto Motor-Bomba "' . ($dadosExcluidos['DS_NOME'] ?? $id) . '" excluído com sucesso!'
    ]);

} catch (PDOException $e) {
    $mensagem = $e->getMessage();
    
    // Tratar erro de FK de forma mais amigável
    if (strpos($mensagem, 'REFERENCE constraint') !== false || strpos($mensagem, 'FK_') !== false) {
        if (preg_match('/table "([^"]+)"/', $mensagem, $matches)) {
            $tabelaRelacionada = $matches[1];
            $mensagemAmigavel = "Não é possível excluir este Conjunto Motor-Bomba pois possui registros vinculados na tabela \"$tabelaRelacionada\". Remova os registros vinculados primeiro.";
        } else {
            $mensagemAmigavel = "Não é possível excluir este Conjunto Motor-Bomba pois possui registros vinculados no sistema.";
        }
        
        // Registrar log de erro
        try {
            registrarLogErro('Conjunto Motor-Bomba', 'DELETE', $mensagemAmigavel, ['id' => $id, 'erro_sql' => $mensagem]);
        } catch (Exception $logEx) {}
        
        echo json_encode([
            'success' => false, 
            'message' => $mensagemAmigavel,
            'erro_sql' => $mensagem
        ]);
    } else {
        // Registrar log de erro
        try {
            registrarLogErro('Conjunto Motor-Bomba', 'DELETE', $mensagem, ['id' => $id]);
        } catch (Exception $logEx) {}
        
        echo json_encode([
            'success' => false, 
            'message' => 'Erro no banco de dados: ' . $mensagem
        ]);
    }

} catch (Exception $e) {
    // Registrar log de erro
    try {
        registrarLogErro('Conjunto Motor-Bomba', 'DELETE', $e->getMessage(), ['id' => $id ?? null]);
    } catch (Exception $logEx) {}

    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

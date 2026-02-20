<?php
/**
 * SIMP - Excluir Nível (Cascata Genérica)
 * Só permite exclusão se nenhum nó estiver usando o nível.
 * 
 * POST params:
 *   - cd (int) : CD_CHAVE do nível
 * 
 * @author Bruno - CESAN
 * @version 1.0
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
if (!podeEditarTela('Cadastro de Entidade')) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão para esta operação']);
    exit;
}

try {
    include_once '../conexao.php';

    if (!isset($pdoSIMP)) {
        throw new Exception('Conexão não estabelecida');
    }

    $cd = isset($_POST['cd']) ? (int)$_POST['cd'] : 0;

    if ($cd <= 0) {
        throw new Exception('ID do nível não informado');
    }

    // ========================================
    // Verificar se existem nós usando este nível
    // ========================================
    $sqlUso = "SELECT COUNT(*) AS QTD FROM SIMP.dbo.ENTIDADE_NODO WHERE CD_ENTIDADE_NIVEL = :cd";
    $stmtUso = $pdoSIMP->prepare($sqlUso);
    $stmtUso->execute([':cd' => $cd]);
    $qtdUso = (int)$stmtUso->fetch(PDO::FETCH_ASSOC)['QTD'];

    if ($qtdUso > 0) {
        throw new Exception('Este nível está sendo usado por ' . $qtdUso . ' nó(s). Remova os nós primeiro.');
    }

    // ========================================
    // Buscar dados para log
    // ========================================
    $sqlBusca = "SELECT DS_NOME FROM SIMP.dbo.ENTIDADE_NIVEL WHERE CD_CHAVE = :cd";
    $stmtBusca = $pdoSIMP->prepare($sqlBusca);
    $stmtBusca->execute([':cd' => $cd]);
    $dadosNivel = $stmtBusca->fetch(PDO::FETCH_ASSOC);

    if (!$dadosNivel) {
        throw new Exception('Nível não encontrado');
    }

    // ========================================
    // Excluir (DELETE físico)
    // ========================================
    $sqlDel = "DELETE FROM SIMP.dbo.ENTIDADE_NIVEL WHERE CD_CHAVE = :cd";
    $stmtDel = $pdoSIMP->prepare($sqlDel);
    $stmtDel->execute([':cd' => $cd]);

    // Log isolado
    try {
        @include_once '../logHelper.php';
        if (function_exists('registrarLogDelete')) {
            registrarLogDelete('Cadastro Cascata', 'Nível', $cd, $dadosNivel['DS_NOME'], $dadosNivel);
        }
    } catch (Exception $logEx) {}

    echo json_encode([
        'success' => true,
        'message' => 'Nível "' . $dadosNivel['DS_NOME'] . '" excluído com sucesso!'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Log de erro isolado
    try {
        @include_once '../logHelper.php';
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Cadastro Cascata', 'DELETE NÍVEL', $e->getMessage(), ['cd' => $cd ?? null]);
        }
    } catch (Exception $logEx) {}

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
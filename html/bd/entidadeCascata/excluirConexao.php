<?php
/**
 * SIMP - Excluir Conexão de Fluxo
 * Remove ligação dirigida entre dois nós.
 * 
 * POST params:
 *   - cd (int) : CD_CHAVE da conexão
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

    $cd = isset($_POST['cd']) ? (int)$_POST['cd'] : 0;
    if ($cd <= 0) {
        throw new Exception('ID da conexão não informado');
    }

    // Buscar dados para log
    $stmtBusca = $pdoSIMP->prepare("SELECT * FROM SIMP.dbo.VW_ENTIDADE_CONEXOES WHERE CD_CHAVE = ?");
    $stmtBusca->execute([$cd]);
    $dados = $stmtBusca->fetch(PDO::FETCH_ASSOC);

    // Deletar
    $stmt = $pdoSIMP->prepare("DELETE FROM SIMP.dbo.ENTIDADE_NODO_CONEXAO WHERE CD_CHAVE = ?");
    $stmt->execute([$cd]);

    // Log isolado
    try {
        @include_once '../logHelper.php';
        if (function_exists('registrarLogDelete')) {
            $desc = $dados ? ($dados['DS_ORIGEM'] . ' → ' . $dados['DS_DESTINO']) : "ID: $cd";
            registrarLogDelete('Cadastro Cascata', 'Conexão Fluxo', $cd, $desc, $dados);
        }
    } catch (Exception $logEx) {}

    echo json_encode([
        'success' => true,
        'message' => 'Conexão removida com sucesso!'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

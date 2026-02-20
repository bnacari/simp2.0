<?php
/**
 * SIMP - Salvar Posição do Nodo no Canvas
 * Endpoint leve, chamado ao soltar o nó após arrastar.
 * 
 * POST params:
 *   - cd (int)   : CD_CHAVE do nodo
 *   - posX (int) : posição X no canvas
 *   - posY (int) : posição Y no canvas
 * 
 * @author Bruno - CESAN
 * @version 1.0
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';
if (!podeEditarTela('Cadastro de Entidade')) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

try {
    include_once '../conexao.php';

    $cd   = isset($_POST['cd']) ? (int)$_POST['cd'] : 0;
    $posX = isset($_POST['posX']) ? (int)$_POST['posX'] : 0;
    $posY = isset($_POST['posY']) ? (int)$_POST['posY'] : 0;

    if ($cd <= 0) {
        throw new Exception('ID não informado');
    }

    $sql = "UPDATE SIMP.dbo.ENTIDADE_NODO 
            SET NR_POS_X = :posX, NR_POS_Y = :posY, DT_ATUALIZACAO = GETDATE() 
            WHERE CD_CHAVE = :cd";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':posX' => $posX, ':posY' => $posY, ':cd' => $cd]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
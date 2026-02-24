<?php
/**
 * SIMP - Restaurar Nodo Desativado (Cascata Genérica)
 * Reativa nó (OP_ATIVO=1) e opcionalmente seus descendentes.
 * 
 * POST params:
 *   - cd (int)                : CD_CHAVE do nodo
 *   - incluirDescendentes (1) : se deve reativar descendentes também (default: 1)
 * 
 * @author Bruno - CESAN
 * @version 1.0
 */

header('Content-Type: application/json; charset=utf-8');

// Verificar permissão
require_once __DIR__ . '/../../includes/auth.php';
@include_once 'topologiaHelper.php';

if (!podeEditarTela('Cadastro de Entidade')) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão para esta operação']);
    exit;
}

try {
    include_once '../conexao.php';

    if (!isset($pdoSIMP)) {
        throw new Exception('Conexão não estabelecida');
    }

    $cd = isset($_POST['cd']) ? (int) $_POST['cd'] : 0;
    $incluirDesc = isset($_POST['incluirDescendentes']) ? (int) $_POST['incluirDescendentes'] : 1;

    if ($cd <= 0) {
        throw new Exception('ID do nó não informado');
    }

    // ========================================
    // Buscar dados do nó para validação e log
    // ========================================
    $sqlBusca = "SELECT N.*, NV.DS_NOME AS DS_NIVEL 
                 FROM SIMP.dbo.ENTIDADE_NODO N 
                 INNER JOIN SIMP.dbo.ENTIDADE_NIVEL NV ON NV.CD_CHAVE = N.CD_ENTIDADE_NIVEL
                 WHERE N.CD_CHAVE = :cd";
    $stmtBusca = $pdoSIMP->prepare($sqlBusca);
    $stmtBusca->execute([':cd' => $cd]);
    $dadosNodo = $stmtBusca->fetch(PDO::FETCH_ASSOC);

    if (!$dadosNodo) {
        throw new Exception('Nó não encontrado');
    }

    if ($dadosNodo['OP_ATIVO'] == 1) {
        throw new Exception('Este nó já está ativo');
    }

    // ========================================
    // Verificar se o pai está ativo
    // (não faz sentido restaurar filho com pai inativo)
    // ========================================
    if ($dadosNodo['CD_PAI']) {
        $sqlPai = "SELECT OP_ATIVO, DS_NOME FROM SIMP.dbo.ENTIDADE_NODO WHERE CD_CHAVE = :cdPai";
        $stmtPai = $pdoSIMP->prepare($sqlPai);
        $stmtPai->execute([':cdPai' => $dadosNodo['CD_PAI']]);
        $pai = $stmtPai->fetch(PDO::FETCH_ASSOC);

        if ($pai && $pai['OP_ATIVO'] == 0) {
            throw new Exception('O nó pai "' . $pai['DS_NOME'] . '" está inativo. Restaure-o primeiro.');
        }
    }

    // ========================================
    // Contar descendentes inativos
    // ========================================
    $totalDesc = 0;
    if ($incluirDesc) {
        $sqlDesc = "
            ;WITH CTE AS (
                SELECT CD_CHAVE FROM SIMP.dbo.ENTIDADE_NODO WHERE CD_PAI = :cd AND OP_ATIVO = 0
                UNION ALL
                SELECT N.CD_CHAVE FROM SIMP.dbo.ENTIDADE_NODO N
                INNER JOIN CTE C ON N.CD_PAI = C.CD_CHAVE
                WHERE N.OP_ATIVO = 0
            )
            SELECT COUNT(*) AS QTD FROM CTE
        ";
        $stmtDesc = $pdoSIMP->prepare($sqlDesc);
        $stmtDesc->execute([':cd' => $cd]);
        $totalDesc = (int) $stmtDesc->fetch(PDO::FETCH_ASSOC)['QTD'];
    }

    // ========================================
    // Restaurar nó (e descendentes se solicitado)
    // ========================================
    $pdoSIMP->beginTransaction();

    // Reativar o nó principal
    $sqlReativar = "UPDATE SIMP.dbo.ENTIDADE_NODO 
                    SET OP_ATIVO = 1, DT_ATUALIZACAO = GETDATE() 
                    WHERE CD_CHAVE = :cd";
    $stmtReativar = $pdoSIMP->prepare($sqlReativar);
    $stmtReativar->execute([':cd' => $cd]);

    // Reativar descendentes
    if ($incluirDesc && $totalDesc > 0) {
        $sqlReativarDesc = "
            ;WITH CTE AS (
                SELECT CD_CHAVE FROM SIMP.dbo.ENTIDADE_NODO WHERE CD_PAI = :cd AND OP_ATIVO = 0
                UNION ALL
                SELECT N.CD_CHAVE FROM SIMP.dbo.ENTIDADE_NODO N
                INNER JOIN CTE C ON N.CD_PAI = C.CD_CHAVE
                WHERE N.OP_ATIVO = 0
            )
            UPDATE SIMP.dbo.ENTIDADE_NODO 
            SET OP_ATIVO = 1, DT_ATUALIZACAO = GETDATE()
            WHERE CD_CHAVE IN (SELECT CD_CHAVE FROM CTE)
        ";
        $stmtReativarDesc = $pdoSIMP->prepare($sqlReativarDesc);
        $stmtReativarDesc->execute([':cd' => $cd]);
    }

    $pdoSIMP->commit();

    // ========================================
    // Log isolado (não interfere no fluxo)
    // ========================================
    try {
        @include_once '../logHelper.php';
        if (function_exists('registrarLogUpdate')) {
            registrarLogUpdate(
                'Cadastro Cascata',
                'Nodo (restaurar)',
                $cd,
                $dadosNodo['DS_NOME'],
                ['descendentes_restaurados' => $totalDesc]
            );
        }
    } catch (Exception $logEx) {
    }

    echo json_encode([
        'success' => true,
        'message' => 'Nó restaurado com sucesso!' . ($totalDesc > 0 ? " ($totalDesc descendente(s) também)" : ''),
        'restaurados' => $totalDesc + 1
    ], JSON_UNESCAPED_UNICODE);

    // Snapshot topologia (Fase A1 - Governança)
    try {
        if (function_exists('dispararSnapshotTopologia')) {
            dispararSnapshotTopologia($pdoSIMP, 'Nó restaurado: ' . $dadosNodo['DS_NOME'] . ($totalDesc > 0 ? " (+$totalDesc descendentes)" : ''));
        }
    } catch (Exception $snapEx) {
    }

} catch (Exception $e) {
    if (isset($pdoSIMP) && $pdoSIMP->inTransaction()) {
        $pdoSIMP->rollBack();
    }

    // Log de erro isolado
    try {
        @include_once '../logHelper.php';
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Cadastro Cascata', 'RESTAURAR', $e->getMessage(), ['cd' => $cd ?? null]);
        }
    } catch (Exception $logEx) {
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
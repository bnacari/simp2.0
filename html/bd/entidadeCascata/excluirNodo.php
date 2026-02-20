<?php
/**
 * SIMP - Excluir Nodo (Cascata Genérica)
 * Suporta soft delete (OP_ATIVO=0) ou exclusão em cascata dos filhos.
 * 
 * POST params:
 *   - cd (int)       : CD_CHAVE do nodo
 *   - modo (string)  : 'soft' (default) ou 'cascade' (exclui filhos fisicamente)
 * 
 * @author Bruno - CESAN
 * @version 1.0
 */

header('Content-Type: application/json; charset=utf-8');

// Verificar permissão
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

    $cd   = isset($_POST['cd']) ? (int)$_POST['cd'] : 0;
    $modo = isset($_POST['modo']) ? trim($_POST['modo']) : 'soft';

    if ($cd <= 0) {
        throw new Exception('ID do nó não informado');
    }

    // Buscar dados antes para log
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

    // Contar descendentes
    $sqlDesc = "
        WITH CTE AS (
            SELECT CD_CHAVE FROM SIMP.dbo.ENTIDADE_NODO WHERE CD_PAI = :cd
            UNION ALL
            SELECT N.CD_CHAVE FROM SIMP.dbo.ENTIDADE_NODO N
            INNER JOIN CTE C ON N.CD_PAI = C.CD_CHAVE
        )
        SELECT COUNT(*) AS QTD FROM CTE
    ";
    $stmtDesc = $pdoSIMP->prepare($sqlDesc);
    $stmtDesc->execute([':cd' => $cd]);
    $totalDesc = (int)$stmtDesc->fetch(PDO::FETCH_ASSOC)['QTD'];

    $pdoSIMP->beginTransaction();

    if ($modo === 'cascade') {
        // --------------------------------------------------
        // Exclusão física permanente (nó + descendentes + conexões)
        // --------------------------------------------------

        // Coletar todos os IDs a serem excluídos (nó + descendentes)
        $sqlIds = "
            WITH CTE AS (
                SELECT CD_CHAVE FROM SIMP.dbo.ENTIDADE_NODO WHERE CD_CHAVE = :cd
                UNION ALL
                SELECT N.CD_CHAVE FROM SIMP.dbo.ENTIDADE_NODO N
                INNER JOIN CTE C ON N.CD_PAI = C.CD_CHAVE
            )
            SELECT CD_CHAVE FROM CTE
        ";
        $stmtIds = $pdoSIMP->prepare($sqlIds);
        $stmtIds->execute([':cd' => $cd]);
        $idsExcluir = [];
        while ($r = $stmtIds->fetch(PDO::FETCH_ASSOC)) {
            $idsExcluir[] = (int)$r['CD_CHAVE'];
        }

        if (empty($idsExcluir)) $idsExcluir = [$cd];
        $placeholders = implode(',', $idsExcluir);

        // 1. Excluir conexões que envolvam qualquer nó do grupo
        $pdoSIMP->exec("DELETE FROM SIMP.dbo.ENTIDADE_NODO_CONEXAO 
                         WHERE CD_NODO_ORIGEM IN ($placeholders) OR CD_NODO_DESTINO IN ($placeholders)");

        // 2. Excluir descendentes (de baixo pra cima)
        $sqlDeleteDesc = "
            WITH CTE AS (
                SELECT CD_CHAVE FROM SIMP.dbo.ENTIDADE_NODO WHERE CD_PAI = :cd
                UNION ALL
                SELECT N.CD_CHAVE FROM SIMP.dbo.ENTIDADE_NODO N
                INNER JOIN CTE C ON N.CD_PAI = C.CD_CHAVE
            )
            DELETE FROM SIMP.dbo.ENTIDADE_NODO WHERE CD_CHAVE IN (SELECT CD_CHAVE FROM CTE)
        ";
        $stmtDelDesc = $pdoSIMP->prepare($sqlDeleteDesc);
        $stmtDelDesc->execute([':cd' => $cd]);

        // 3. Excluir o nó em si
        $sqlDelNodo = "DELETE FROM SIMP.dbo.ENTIDADE_NODO WHERE CD_CHAVE = :cd";
        $stmtDelNodo = $pdoSIMP->prepare($sqlDelNodo);
        $stmtDelNodo->execute([':cd' => $cd]);

        $pdoSIMP->commit();

        // Log isolado
        try {
            @include_once '../logHelper.php';
            if (function_exists('registrarLogDelete')) {
                registrarLogDelete('Cadastro Cascata', 'Nodo (cascade)', $cd, 
                    $dadosNodo['DS_NOME'] . " (+$totalDesc descendentes)", $dadosNodo);
            }
        } catch (Exception $logEx) {}

        echo json_encode([
            'success'    => true,
            'message'    => "Nó e $totalDesc descendente(s) excluídos com sucesso!",
            'excluidos'  => $totalDesc + 1
        ], JSON_UNESCAPED_UNICODE);

    } else {
        // --------------------------------------------------
        // Soft delete (marca como inativo)
        // --------------------------------------------------
        $sqlSoft = "UPDATE SIMP.dbo.ENTIDADE_NODO 
                    SET OP_ATIVO = 0, DT_ATUALIZACAO = GETDATE() 
                    WHERE CD_CHAVE = :cd";
        $stmtSoft = $pdoSIMP->prepare($sqlSoft);
        $stmtSoft->execute([':cd' => $cd]);

        // Desativar descendentes também
        if ($totalDesc > 0) {
            $sqlSoftDesc = "
                WITH CTE AS (
                    SELECT CD_CHAVE FROM SIMP.dbo.ENTIDADE_NODO WHERE CD_PAI = :cd
                    UNION ALL
                    SELECT N.CD_CHAVE FROM SIMP.dbo.ENTIDADE_NODO N
                    INNER JOIN CTE C ON N.CD_PAI = C.CD_CHAVE
                )
                UPDATE SIMP.dbo.ENTIDADE_NODO 
                SET OP_ATIVO = 0, DT_ATUALIZACAO = GETDATE()
                WHERE CD_CHAVE IN (SELECT CD_CHAVE FROM CTE)
            ";
            $stmtSoftDesc = $pdoSIMP->prepare($sqlSoftDesc);
            $stmtSoftDesc->execute([':cd' => $cd]);
        }

        $pdoSIMP->commit();

        // Log isolado
        try {
            @include_once '../logHelper.php';
            if (function_exists('registrarLogUpdate')) {
                registrarLogUpdate('Cadastro Cascata', 'Nodo (soft delete)', $cd, 
                    $dadosNodo['DS_NOME'], ['modo' => 'soft', 'descendentes' => $totalDesc]);
            }
        } catch (Exception $logEx) {}

        echo json_encode([
            'success'     => true,
            'message'     => "Nó desativado com sucesso!" . ($totalDesc > 0 ? " ($totalDesc descendente(s) também)" : ""),
            'desativados' => $totalDesc + 1
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    if ($pdoSIMP->inTransaction()) {
        $pdoSIMP->rollBack();
    }

    // Log de erro isolado
    try {
        @include_once '../logHelper.php';
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Cadastro Cascata', 'DELETE', $e->getMessage(), ['cd' => $cd ?? null]);
        }
    } catch (Exception $logEx) {}

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
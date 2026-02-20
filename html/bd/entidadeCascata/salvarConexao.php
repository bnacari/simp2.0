<?php
/**
 * SIMP - Salvar/Atualizar Conexão de Fluxo
 * Cria ligação dirigida: nó origem → nó destino (fluxo físico da água).
 * 
 * POST params:
 *   - cd (int|null)        : CD_CHAVE (null = nova conexão)
 *   - cdOrigem (int)       : CD_NODO_ORIGEM
 *   - cdDestino (int)      : CD_NODO_DESTINO
 *   - rotulo (string|null) : DS_ROTULO (ex: "Adutora DN600")
 *   - cor (string|null)    : DS_COR (hex, default #1565C0)
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

    $cd        = isset($_POST['cd']) && $_POST['cd'] !== '' ? (int)$_POST['cd'] : null;
    $cdOrigem  = isset($_POST['cdOrigem']) ? (int)$_POST['cdOrigem'] : 0;
    $cdDestino = isset($_POST['cdDestino']) ? (int)$_POST['cdDestino'] : 0;
    $rotulo    = isset($_POST['rotulo']) ? trim($_POST['rotulo']) : null;
    $cor       = isset($_POST['cor']) && $_POST['cor'] !== '' ? trim($_POST['cor']) : '#1565C0';

    // --------------------------------------------------
    // Validações
    // --------------------------------------------------
    if ($cdOrigem <= 0) {
        throw new Exception('Nó de origem é obrigatório');
    }
    if ($cdDestino <= 0) {
        throw new Exception('Nó de destino é obrigatório');
    }
    if ($cdOrigem === $cdDestino) {
        throw new Exception('Origem e destino não podem ser o mesmo nó');
    }

    // Verificar se os nós existem
    $sqlCheck = "SELECT CD_CHAVE, DS_NOME FROM SIMP.dbo.ENTIDADE_NODO WHERE CD_CHAVE IN (:o, :d)";
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $stmtCheck->execute([':o' => $cdOrigem, ':d' => $cdDestino]);
    $nosEncontrados = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);
    if (count($nosEncontrados) < 2) {
        throw new Exception('Um ou ambos os nós não foram encontrados');
    }

    // Verificar duplicidade (mesma origem→destino)
    $sqlDup = "SELECT CD_CHAVE FROM SIMP.dbo.ENTIDADE_NODO_CONEXAO 
               WHERE CD_NODO_ORIGEM = :o AND CD_NODO_DESTINO = :d";
    if ($cd !== null) {
        $sqlDup .= " AND CD_CHAVE <> :cd";
    }
    $stmtDup = $pdoSIMP->prepare($sqlDup);
    $paramsDup = [':o' => $cdOrigem, ':d' => $cdDestino];
    if ($cd !== null) $paramsDup[':cd'] = $cd;
    $stmtDup->execute($paramsDup);
    if ($stmtDup->fetch()) {
        throw new Exception('Já existe uma conexão entre estes dois nós nesta direção');
    }

    // --------------------------------------------------
    // INSERT ou UPDATE
    // --------------------------------------------------
    if ($cd !== null) {
        $sql = "UPDATE SIMP.dbo.ENTIDADE_NODO_CONEXAO SET
                    CD_NODO_ORIGEM  = :cdOrigem,
                    CD_NODO_DESTINO = :cdDestino,
                    DS_ROTULO       = :rotulo,
                    DS_COR          = :cor,
                    DT_ATUALIZACAO  = GETDATE()
                WHERE CD_CHAVE = :cd";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':cdOrigem'  => $cdOrigem,
            ':cdDestino' => $cdDestino,
            ':rotulo'    => $rotulo,
            ':cor'       => $cor,
            ':cd'        => $cd
        ]);

        // Log isolado
        try {
            @include_once '../logHelper.php';
            if (function_exists('registrarLogUpdate')) {
                registrarLogUpdate('Cadastro Cascata', 'Conexão Fluxo', $cd, $rotulo ?: "Conexão $cdOrigem→$cdDestino", $_POST);
            }
        } catch (Exception $logEx) {}

        echo json_encode([
            'success' => true,
            'message' => 'Conexão atualizada com sucesso!',
            'cd'      => $cd
        ], JSON_UNESCAPED_UNICODE);

    } else {
        // Calcular próxima ordem
        $stmtOrdem = $pdoSIMP->query("SELECT ISNULL(MAX(NR_ORDEM),0)+1 AS PROX FROM SIMP.dbo.ENTIDADE_NODO_CONEXAO");
        $ordem = (int)$stmtOrdem->fetch(PDO::FETCH_ASSOC)['PROX'];

        $sql = "INSERT INTO SIMP.dbo.ENTIDADE_NODO_CONEXAO 
                (CD_NODO_ORIGEM, CD_NODO_DESTINO, DS_ROTULO, DS_COR, NR_ORDEM)
                VALUES (:cdOrigem, :cdDestino, :rotulo, :cor, :ordem)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':cdOrigem'  => $cdOrigem,
            ':cdDestino' => $cdDestino,
            ':rotulo'    => $rotulo,
            ':cor'       => $cor,
            ':ordem'     => $ordem
        ]);

        $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID");
        $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['ID'];

        // Log isolado
        try {
            @include_once '../logHelper.php';
            if (function_exists('registrarLogInsert')) {
                registrarLogInsert('Cadastro Cascata', 'Conexão Fluxo', $novoId, $rotulo ?: "Conexão $cdOrigem→$cdDestino", $_POST);
            }
        } catch (Exception $logEx) {}

        echo json_encode([
            'success' => true,
            'message' => 'Conexão criada com sucesso!',
            'cd'      => (int)$novoId
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    try {
        @include_once '../logHelper.php';
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Cadastro Cascata', 'Conexão', $e->getMessage(), $_POST);
        }
    } catch (Exception $logEx) {}

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

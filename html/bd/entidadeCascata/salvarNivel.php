<?php
/**
 * SIMP - Salvar/Atualizar Nível (Cascata Genérica)
 * 
 * POST params:
 *   - cd (int|null)          : CD_CHAVE (null = novo)
 *   - nome (string)          : DS_NOME
 *   - icone (string)         : DS_ICONE (ionicon)
 *   - cor (string)           : DS_COR (hex)
 *   - ordem (int)            : NR_ORDEM
 *   - permitePonto (0|1)     : OP_PERMITE_PONTO
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

    $cd            = isset($_POST['cd']) && $_POST['cd'] !== '' ? (int)$_POST['cd'] : null;
    $nome          = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $icone         = isset($_POST['icone']) ? trim($_POST['icone']) : 'ellipse-outline';
    $cor           = isset($_POST['cor']) ? trim($_POST['cor']) : '#607D8B';
    $ordem         = isset($_POST['ordem']) ? (int)$_POST['ordem'] : 0;
    $permitePonto  = isset($_POST['permitePonto']) ? (int)$_POST['permitePonto'] : 0;
    $ehSistema     = isset($_POST['ehSistema']) ? (int)$_POST['ehSistema'] : 0;

    if ($nome === '') {
        throw new Exception('Nome do nível é obrigatório');
    }

    if ($cd !== null) {
        // UPDATE
        $sql = "UPDATE SIMP.dbo.ENTIDADE_NIVEL SET
                    DS_NOME = :nome, DS_ICONE = :icone, DS_COR = :cor,
                    NR_ORDEM = :ordem, OP_PERMITE_PONTO = :permitePonto,
                    OP_EH_SISTEMA = :ehSistema,
                    DT_ATUALIZACAO = GETDATE()
                WHERE CD_CHAVE = :cd";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':nome' => $nome, ':icone' => $icone, ':cor' => $cor,
            ':ordem' => $ordem, ':permitePonto' => $permitePonto, 
            ':ehSistema' => $ehSistema, ':cd' => $cd
        ]);

        echo json_encode(['success' => true, 'message' => 'Nível atualizado!', 'cd' => $cd], JSON_UNESCAPED_UNICODE);
    } else {
        // INSERT
        if ($ordem <= 0) {
            $stmtMax = $pdoSIMP->query("SELECT ISNULL(MAX(NR_ORDEM),0)+1 AS PROX FROM SIMP.dbo.ENTIDADE_NIVEL");
            $ordem = (int)$stmtMax->fetch(PDO::FETCH_ASSOC)['PROX'];
        }

        $sql = "INSERT INTO SIMP.dbo.ENTIDADE_NIVEL (DS_NOME, DS_ICONE, DS_COR, NR_ORDEM, OP_PERMITE_PONTO, OP_EH_SISTEMA)
                VALUES (:nome, :icone, :cor, :ordem, :permitePonto, :ehSistema)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':nome' => $nome, ':icone' => $icone, ':cor' => $cor,
            ':ordem' => $ordem, ':permitePonto' => $permitePonto,
            ':ehSistema' => $ehSistema
        ]);

        $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID");
        $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['ID'];

        echo json_encode(['success' => true, 'message' => 'Nível cadastrado!', 'cd' => (int)$novoId], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
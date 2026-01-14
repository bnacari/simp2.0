<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
verificarPermissaoAjax('CADASTRO', ACESSO_ESCRITA);

include_once '../conexao.php';

try {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $cdSistema = isset($_POST['cd_sistema']) && $_POST['cd_sistema'] !== '' ? (int)$_POST['cd_sistema'] : null;
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $descricao = isset($_POST['descricao']) ? trim($_POST['descricao']) : null;
    $cdFormula = isset($_POST['cd_formula']) && $_POST['cd_formula'] !== '' ? (int)$_POST['cd_formula'] : null;
    $cdEntidadeValorId = isset($_POST['cd_entidade_valor_id']) && $_POST['cd_entidade_valor_id'] !== '' ? (int)$_POST['cd_entidade_valor_id'] : null;
    $metaDia = isset($_POST['meta_dia']) && $_POST['meta_dia'] !== '' ? (float)$_POST['meta_dia'] : null;
    $cdUsuario = getIdUsuario();
    
    if (empty($cdSistema)) {
        throw new Exception('Sistema de água é obrigatório. Valor recebido: ' . var_export($_POST['cd_sistema'] ?? 'não enviado', true));
    }
    if (empty($nome)) {
        throw new Exception('Nome é obrigatório');
    }
    
    // Descrição pode ser null ou string vazia
    if ($descricao === '') {
        $descricao = null;
    }
    
    if ($id) {
        $sql = "UPDATE SIMP.dbo.ETA 
                SET CD_SISTEMA_AGUA = :sistema, 
                    DS_NOME = :nome, 
                    DS_DESCRICAO = :descricao, 
                    CD_FORMULA_VOLUME_DISTRIBUIDO = :formula,
                    CD_ENTIDADE_VALOR_ID = :entidade,
                    VL_META_DIA = :meta, 
                    DT_ULTIMA_ATUALIZACAO = GETDATE(), 
                    CD_USUARIO_ULTIMA_ATUALIZACAO = :usuario
                WHERE CD_CHAVE = :id";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':sistema' => $cdSistema, 
            ':nome' => $nome, 
            ':descricao' => $descricao, 
            ':formula' => $cdFormula,
            ':entidade' => $cdEntidadeValorId,
            ':meta' => $metaDia, 
            ':usuario' => $cdUsuario,
            ':id' => $id
        ]);
        $msg = 'ETA atualizada com sucesso!';
    } else {
        $sql = "INSERT INTO SIMP.dbo.ETA (CD_SISTEMA_AGUA, DS_NOME, DS_DESCRICAO, CD_FORMULA_VOLUME_DISTRIBUIDO, CD_ENTIDADE_VALOR_ID, VL_META_DIA, DT_ULTIMA_ATUALIZACAO, CD_USUARIO_ULTIMA_ATUALIZACAO) 
                VALUES (:sistema, :nome, :descricao, :formula, :entidade, :meta, GETDATE(), :usuario)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':sistema' => $cdSistema, 
            ':nome' => $nome, 
            ':descricao' => $descricao, 
            ':formula' => $cdFormula,
            ':entidade' => $cdEntidadeValorId,
            ':meta' => $metaDia, 
            ':usuario' => $cdUsuario
        ]);
        $msg = 'ETA cadastrada com sucesso!';
    }
    
    echo json_encode(['success' => true, 'message' => $msg]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

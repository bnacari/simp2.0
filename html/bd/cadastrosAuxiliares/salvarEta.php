<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
verificarPermissaoAjax('CADASTRO', ACESSO_ESCRITA);

include_once '../conexao.php';
@include_once '../logHelper.php';

try {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $cdSistema = isset($_POST['cd_sistema']) && $_POST['cd_sistema'] !== '' ? (int)$_POST['cd_sistema'] : null;
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $descricao = isset($_POST['descricao']) ? trim($_POST['descricao']) : null;
    $cdFormula = isset($_POST['cd_formula']) && $_POST['cd_formula'] !== '' ? (int)$_POST['cd_formula'] : null;
    $cdEntidadeValorId = isset($_POST['cd_entidade_valor_id']) && $_POST['cd_entidade_valor_id'] !== '' ? (int)$_POST['cd_entidade_valor_id'] : null;
    $metaDia = isset($_POST['meta_dia']) && $_POST['meta_dia'] !== '' ? (float)$_POST['meta_dia'] : null;
    $cdUsuario = isset($_SESSION['cd_usuario']) ? (int)$_SESSION['cd_usuario'] : null;
    
    if (empty($cdSistema)) throw new Exception('Sistema de água é obrigatório');
    if (empty($nome)) throw new Exception('Nome é obrigatório');
    if ($descricao === '') $descricao = null;
    
    if ($id) {
        $dadosAnteriores = null;
        try { $stmtAnt = $pdoSIMP->prepare("SELECT CD_SISTEMA_AGUA, DS_NOME, DS_DESCRICAO, CD_FORMULA_VOLUME_DISTRIBUIDO, CD_ENTIDADE_VALOR_ID, VL_META_DIA FROM SIMP.dbo.ETA WHERE CD_CHAVE = :id"); $stmtAnt->execute([':id' => $id]); $dadosAnteriores = $stmtAnt->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
        
        $sql = "UPDATE SIMP.dbo.ETA SET CD_SISTEMA_AGUA = :sistema, DS_NOME = :nome, DS_DESCRICAO = :descricao, CD_FORMULA_VOLUME_DISTRIBUIDO = :formula, CD_ENTIDADE_VALOR_ID = :entidade, VL_META_DIA = :meta, DT_ULTIMA_ATUALIZACAO = GETDATE(), CD_USUARIO_ULTIMA_ATUALIZACAO = :usuario WHERE CD_CHAVE = :id";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':sistema' => $cdSistema, ':nome' => $nome, ':descricao' => $descricao, ':formula' => $cdFormula, ':entidade' => $cdEntidadeValorId, ':meta' => $metaDia, ':usuario' => $cdUsuario, ':id' => $id]);
        
        if (function_exists('registrarLogUpdate')) { try { registrarLogUpdate('Cadastros Auxiliares', 'ETA', $id, $nome, ['anterior' => $dadosAnteriores, 'novo' => ['CD_SISTEMA_AGUA' => $cdSistema, 'DS_NOME' => $nome, 'DS_DESCRICAO' => $descricao, 'CD_FORMULA_VOLUME_DISTRIBUIDO' => $cdFormula, 'CD_ENTIDADE_VALOR_ID' => $cdEntidadeValorId, 'VL_META_DIA' => $metaDia]]); } catch (Exception $e) {} }
        
        $msg = 'ETA atualizada com sucesso!';
    } else {
        $sql = "INSERT INTO SIMP.dbo.ETA (CD_SISTEMA_AGUA, DS_NOME, DS_DESCRICAO, CD_FORMULA_VOLUME_DISTRIBUIDO, CD_ENTIDADE_VALOR_ID, VL_META_DIA, DT_ULTIMA_ATUALIZACAO, CD_USUARIO_ULTIMA_ATUALIZACAO) VALUES (:sistema, :nome, :descricao, :formula, :entidade, :meta, GETDATE(), :usuario)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':sistema' => $cdSistema, ':nome' => $nome, ':descricao' => $descricao, ':formula' => $cdFormula, ':entidade' => $cdEntidadeValorId, ':meta' => $metaDia, ':usuario' => $cdUsuario]);
        
        $novoId = null;
        try { $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID"); $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['ID']; } catch (Exception $e) {}
        
        if (function_exists('registrarLogInsert')) { try { registrarLogInsert('Cadastros Auxiliares', 'ETA', $novoId, $nome, ['CD_SISTEMA_AGUA' => $cdSistema, 'DS_NOME' => $nome, 'DS_DESCRICAO' => $descricao, 'CD_FORMULA_VOLUME_DISTRIBUIDO' => $cdFormula, 'CD_ENTIDADE_VALOR_ID' => $cdEntidadeValorId, 'VL_META_DIA' => $metaDia]); } catch (Exception $e) {} }
        
        $msg = 'ETA cadastrada com sucesso!';
    }
    
    echo json_encode(['success' => true, 'message' => $msg]);
} catch (Exception $e) {
    if (function_exists('registrarLogErro')) { try { registrarLogErro('Cadastros Auxiliares', 'SALVAR', $e->getMessage(), $_POST); } catch (Exception $ex) {} }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
<?php
// bd/grupoUsuario/excluirGrupoUsuario.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';
@include_once '../logHelper.php';

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID não informado']);
        exit;
    }
    
    // Buscar dados para log antes de excluir
    $dadosExcluidos = null;
    $nomeGrupo = '';
    try {
        $stmtDados = $pdoSIMP->prepare("SELECT DS_NOME FROM SIMP.dbo.GRUPO_USUARIO WHERE CD_GRUPO_USUARIO = :id");
        $stmtDados->execute([':id' => $id]);
        $dadosExcluidos = $stmtDados->fetch(PDO::FETCH_ASSOC);
        $nomeGrupo = $dadosExcluidos['DS_NOME'] ?? '';
    } catch (Exception $e) {}
    
    // Verificar se existem usuários vinculados
    $sqlCheckUsuarios = "SELECT COUNT(*) as total FROM SIMP.dbo.USUARIO WHERE CD_GRUPO_USUARIO = :id";
    $stmtCheck = $pdoSIMP->prepare($sqlCheckUsuarios);
    $stmtCheck->execute([':id' => $id]);
    $totalUsuarios = $stmtCheck->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($totalUsuarios > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "Não é possível excluir. Existem {$totalUsuarios} usuário(s) vinculado(s) a este grupo."
        ]);
        exit;
    }
    
    // Contar permissões que serão excluídas para log
    $qtdPermissoes = 0;
    try {
        $stmtCount = $pdoSIMP->prepare("SELECT COUNT(*) as total FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE WHERE CD_GRUPO_USUARIO = :id");
        $stmtCount->execute([':id' => $id]);
        $qtdPermissoes = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {}
    
    // Excluir permissões vinculadas
    $sqlDeletePermissoes = "DELETE FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE WHERE CD_GRUPO_USUARIO = :id";
    $stmtPerm = $pdoSIMP->prepare($sqlDeletePermissoes);
    $stmtPerm->execute([':id' => $id]);
    
    // Excluir grupo
    $sql = "DELETE FROM SIMP.dbo.GRUPO_USUARIO WHERE CD_GRUPO_USUARIO = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    if ($stmt->rowCount() > 0) {
        // Log (isolado)
        try {
            if (function_exists('registrarLogDelete')) {
                $dadosExcluidos['permissoes_excluidas'] = $qtdPermissoes;
                registrarLogDelete('Cadastros Administrativos', 'Grupo de Usuário', $id, $nomeGrupo, $dadosExcluidos);
            }
        } catch (Exception $logEx) {}
        
        echo json_encode(['success' => true, 'message' => 'Grupo de usuário excluído com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
    }

} catch (PDOException $e) {
    // Log de erro (isolado)
    try {
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Cadastros Administrativos', 'DELETE', $e->getMessage(), ['id' => $id ?? '']);
        }
    } catch (Exception $logEx) {}
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao excluir grupo de usuário: ' . $e->getMessage()
    ]);
}
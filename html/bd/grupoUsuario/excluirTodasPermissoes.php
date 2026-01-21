<?php
// bd/grupoUsuario/excluirTodasPermissoes.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';
@include_once '../logHelper.php';

try {
    $cdGrupo = isset($_POST['cd_grupo']) ? (int)$_POST['cd_grupo'] : 0;
    
    if ($cdGrupo <= 0) {
        throw new Exception('Grupo não informado');
    }
    
    // Buscar nome do grupo e permissões para log
    $nomeGrupo = '';
    $permissoesExcluidas = [];
    try {
        $stmtGrupo = $pdoSIMP->prepare("SELECT DS_NOME FROM SIMP.dbo.GRUPO_USUARIO WHERE CD_GRUPO_USUARIO = :id");
        $stmtGrupo->execute([':id' => $cdGrupo]);
        $nomeGrupo = $stmtGrupo->fetch(PDO::FETCH_ASSOC)['DS_NOME'] ?? '';
        
        // Buscar permissões que serão excluídas
        $sqlPerms = "SELECT f.DS_NOME FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE guf
                     LEFT JOIN SIMP.dbo.FUNCIONALIDADE f ON f.CD_FUNCIONALIDADE = guf.CD_FUNCIONALIDADE
                     WHERE guf.CD_GRUPO_USUARIO = :grupo";
        $stmtPerms = $pdoSIMP->prepare($sqlPerms);
        $stmtPerms->execute([':grupo' => $cdGrupo]);
        while ($row = $stmtPerms->fetch(PDO::FETCH_ASSOC)) {
            $permissoesExcluidas[] = $row['DS_NOME'];
        }
    } catch (Exception $e) {}
    
    // Contar antes de excluir
    $sqlCount = "SELECT COUNT(*) as total FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE WHERE CD_GRUPO_USUARIO = :grupo";
    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute([':grupo' => $cdGrupo]);
    $totalAntes = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($totalAntes === 0) {
        echo json_encode(['success' => true, 'message' => 'Este grupo não possui permissões para excluir.']);
        exit;
    }
    
    // DELETE
    $sql = "DELETE FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE WHERE CD_GRUPO_USUARIO = :grupo";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':grupo' => $cdGrupo]);
    $excluidos = $stmt->rowCount();
    
    // Log (isolado)
    try {
        if (function_exists('registrarLogAlteracaoMassa')) {
            registrarLogAlteracaoMassa('Cadastros Administrativos', 'Permissão', $excluidos, 
                "Exclusão em massa de todas as permissões do grupo '$nomeGrupo'",
                ['CD_GRUPO_USUARIO' => $cdGrupo, 'DS_GRUPO' => $nomeGrupo,
                 'funcionalidades_excluidas' => $permissoesExcluidas]);
        }
    } catch (Exception $logEx) {}
    
    echo json_encode([
        'success' => true, 
        'message' => "$excluidos permissão(ões) removida(s) com sucesso!"
    ]);

} catch (PDOException $e) {
    // Log de erro (isolado)
    try {
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Cadastros Administrativos', 'DELETE_MASSA', $e->getMessage(), ['cd_grupo' => $cdGrupo ?? '']);
        }
    } catch (Exception $logEx) {}
    
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir permissões: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
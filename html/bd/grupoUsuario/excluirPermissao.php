<?php
// bd/grupoUsuario/excluirPermissao.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';
@include_once '../logHelper.php';

try {
    $cdChave = isset($_POST['cd_chave']) ? (int)$_POST['cd_chave'] : 0;
    
    if ($cdChave <= 0) {
        throw new Exception('Permissão não informada');
    }
    
    // Buscar dados para log antes de excluir
    $dadosExcluidos = null;
    $nomeGrupo = '';
    $nomeFuncionalidade = '';
    try {
        $sqlDados = "SELECT guf.*, g.DS_NOME AS DS_GRUPO, f.DS_NOME AS DS_FUNCIONALIDADE 
                     FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE guf
                     LEFT JOIN SIMP.dbo.GRUPO_USUARIO g ON g.CD_GRUPO_USUARIO = guf.CD_GRUPO_USUARIO
                     LEFT JOIN SIMP.dbo.FUNCIONALIDADE f ON f.CD_FUNCIONALIDADE = guf.CD_FUNCIONALIDADE
                     WHERE guf.CD_CHAVE = :id";
        $stmtDados = $pdoSIMP->prepare($sqlDados);
        $stmtDados->execute([':id' => $cdChave]);
        $dadosExcluidos = $stmtDados->fetch(PDO::FETCH_ASSOC);
        $nomeGrupo = $dadosExcluidos['DS_GRUPO'] ?? '';
        $nomeFuncionalidade = $dadosExcluidos['DS_FUNCIONALIDADE'] ?? '';
    } catch (Exception $e) {}
    
    // DELETE
    $sql = "DELETE FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE WHERE CD_CHAVE = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $cdChave]);
    
    if ($stmt->rowCount() > 0) {
        // Log (isolado)
        try {
            if (function_exists('registrarLogDelete')) {
                registrarLogDelete('Cadastros Administrativos', 'Permissão', $cdChave, 
                    "$nomeGrupo -> $nomeFuncionalidade", $dadosExcluidos);
            }
        } catch (Exception $logEx) {}
        
        echo json_encode(['success' => true, 'message' => 'Permissão removida com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
    }

} catch (PDOException $e) {
    // Log de erro (isolado)
    try {
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Cadastros Administrativos', 'DELETE', $e->getMessage(), ['cd_chave' => $cdChave ?? '']);
        }
    } catch (Exception $logEx) {}
    
    echo json_encode(['success' => false, 'message' => 'Erro ao remover permissão: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
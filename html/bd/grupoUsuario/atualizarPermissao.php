<?php
// bd/grupoUsuario/atualizarPermissao.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';
@include_once '../logHelper.php';

try {
    $cdChave = isset($_POST['cd_chave']) ? (int)$_POST['cd_chave'] : 0;
    $tipoAcesso = isset($_POST['tipo_acesso']) ? (int)$_POST['tipo_acesso'] : 1;
    
    if ($cdChave <= 0) {
        throw new Exception('Permissão não informada');
    }
    
    // Buscar dados anteriores para log
    $dadosAnteriores = null;
    $nomeGrupo = '';
    $nomeFuncionalidade = '';
    try {
        $sqlAnt = "SELECT guf.*, g.DS_NOME AS DS_GRUPO, f.DS_NOME AS DS_FUNCIONALIDADE 
                   FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE guf
                   LEFT JOIN SIMP.dbo.GRUPO_USUARIO g ON g.CD_GRUPO_USUARIO = guf.CD_GRUPO_USUARIO
                   LEFT JOIN SIMP.dbo.FUNCIONALIDADE f ON f.CD_FUNCIONALIDADE = guf.CD_FUNCIONALIDADE
                   WHERE guf.CD_CHAVE = :id";
        $stmtAnt = $pdoSIMP->prepare($sqlAnt);
        $stmtAnt->execute([':id' => $cdChave]);
        $dadosAnteriores = $stmtAnt->fetch(PDO::FETCH_ASSOC);
        $nomeGrupo = $dadosAnteriores['DS_GRUPO'] ?? '';
        $nomeFuncionalidade = $dadosAnteriores['DS_FUNCIONALIDADE'] ?? '';
    } catch (Exception $e) {}
    
    // UPDATE
    $sql = "UPDATE SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE SET ID_TIPO_ACESSO = :acesso WHERE CD_CHAVE = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':acesso' => $tipoAcesso, ':id' => $cdChave]);
    
    // Log (isolado)
    try {
        if (function_exists('registrarLogUpdate')) {
            $tipoAnterior = $dadosAnteriores['ID_TIPO_ACESSO'] ?? '';
            $tipoAnteriorDesc = $tipoAnterior == 1 ? 'Somente Leitura' : 'Acesso Total';
            $tipoNovoDesc = $tipoAcesso == 1 ? 'Somente Leitura' : 'Acesso Total';
            registrarLogUpdate('Cadastros Administrativos', 'Permissão', $cdChave, 
                "$nomeGrupo -> $nomeFuncionalidade", 
                ['anterior' => ['ID_TIPO_ACESSO' => $tipoAnterior, 'DS_TIPO_ACESSO' => $tipoAnteriorDesc],
                 'novo' => ['ID_TIPO_ACESSO' => $tipoAcesso, 'DS_TIPO_ACESSO' => $tipoNovoDesc]]);
        }
    } catch (Exception $logEx) {}
    
    echo json_encode(['success' => true, 'message' => 'Tipo de acesso atualizado!']);

} catch (PDOException $e) {
    // Log de erro (isolado)
    try {
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Cadastros Administrativos', 'UPDATE', $e->getMessage(), ['cd_chave' => $cdChave ?? '']);
        }
    } catch (Exception $logEx) {}
    
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar permissão: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
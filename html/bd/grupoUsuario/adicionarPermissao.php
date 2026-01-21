<?php
// bd/grupoUsuario/salvarPermissao.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';
@include_once '../logHelper.php';

try {
    $cdGrupo = isset($_POST['cd_grupo']) ? (int)$_POST['cd_grupo'] : 0;
    $cdFuncionalidade = isset($_POST['cd_funcionalidade']) ? (int)$_POST['cd_funcionalidade'] : 0;
    $tipoAcesso = isset($_POST['tipo_acesso']) ? (int)$_POST['tipo_acesso'] : 1;
    
    if ($cdGrupo <= 0) {
        throw new Exception('Grupo não informado');
    }
    
    if ($cdFuncionalidade <= 0) {
        throw new Exception('Funcionalidade não informada');
    }
    
    // Verificar se já existe
    $sqlCheck = "SELECT CD_CHAVE FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE 
                 WHERE CD_GRUPO_USUARIO = :grupo AND CD_FUNCIONALIDADE = :func";
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $stmtCheck->execute([':grupo' => $cdGrupo, ':func' => $cdFuncionalidade]);
    
    if ($stmtCheck->fetch()) {
        throw new Exception('Esta funcionalidade já está vinculada ao grupo');
    }
    
    // Buscar nomes para o log
    $nomeGrupo = '';
    $nomeFuncionalidade = '';
    try {
        $stmtGrupo = $pdoSIMP->prepare("SELECT DS_NOME FROM SIMP.dbo.GRUPO_USUARIO WHERE CD_GRUPO_USUARIO = :id");
        $stmtGrupo->execute([':id' => $cdGrupo]);
        $nomeGrupo = $stmtGrupo->fetch(PDO::FETCH_ASSOC)['DS_NOME'] ?? '';
        
        $stmtFunc = $pdoSIMP->prepare("SELECT DS_NOME FROM SIMP.dbo.FUNCIONALIDADE WHERE CD_FUNCIONALIDADE = :id");
        $stmtFunc->execute([':id' => $cdFuncionalidade]);
        $nomeFuncionalidade = $stmtFunc->fetch(PDO::FETCH_ASSOC)['DS_NOME'] ?? '';
    } catch (Exception $e) {}
    
    // INSERT - tentar sem CD_CHAVE primeiro (caso seja IDENTITY)
    try {
        $sql = "INSERT INTO SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE 
                (CD_GRUPO_USUARIO, CD_FUNCIONALIDADE, ID_TIPO_ACESSO) 
                VALUES (:grupo, :func, :acesso)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':grupo' => $cdGrupo, ':func' => $cdFuncionalidade, ':acesso' => $tipoAcesso]);
        
        // Buscar ID inserido
        $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID");
        $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['ID'];
    } catch (PDOException $e) {
        // Se falhou, tentar com CD_CHAVE manual
        $stmtMax = $pdoSIMP->query("SELECT ISNULL(MAX(CD_CHAVE), 0) + 1 AS NOVO_ID FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE");
        $novoId = $stmtMax->fetch(PDO::FETCH_ASSOC)['NOVO_ID'];
        
        $sql = "INSERT INTO SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE 
                (CD_CHAVE, CD_GRUPO_USUARIO, CD_FUNCIONALIDADE, ID_TIPO_ACESSO) 
                VALUES (:id, :grupo, :func, :acesso)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([':id' => $novoId, ':grupo' => $cdGrupo, ':func' => $cdFuncionalidade, ':acesso' => $tipoAcesso]);
    }
    
    // Log (isolado)
    try {
        if (function_exists('registrarLogInsert')) {
            $tipoAcessoDesc = $tipoAcesso == 1 ? 'Somente Leitura' : 'Acesso Total';
            registrarLogInsert('Cadastros Administrativos', 'Permissão', $novoId, 
                "$nomeGrupo -> $nomeFuncionalidade", 
                ['CD_GRUPO_USUARIO' => $cdGrupo, 'DS_GRUPO' => $nomeGrupo,
                 'CD_FUNCIONALIDADE' => $cdFuncionalidade, 'DS_FUNCIONALIDADE' => $nomeFuncionalidade,
                 'ID_TIPO_ACESSO' => $tipoAcesso, 'DS_TIPO_ACESSO' => $tipoAcessoDesc]);
        }
    } catch (Exception $logEx) {}
    
    echo json_encode(['success' => true, 'message' => 'Permissão adicionada com sucesso!']);

} catch (PDOException $e) {
    // Log de erro (isolado)
    try {
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Cadastros Administrativos', 'INSERT', $e->getMessage(), 
                ['cd_grupo' => $cdGrupo ?? '', 'cd_funcionalidade' => $cdFuncionalidade ?? '']);
        }
    } catch (Exception $logEx) {}
    
    echo json_encode(['success' => false, 'message' => 'Erro ao adicionar permissão: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
<?php
// bd/grupoUsuario/incluirTodasPermissoes.php
header('Content-Type: application/json');
session_start();
include_once '../conexao.php';
@include_once '../logHelper.php';

try {
    $cdGrupo = isset($_POST['cd_grupo']) ? (int)$_POST['cd_grupo'] : 0;
    $tipoAcesso = isset($_POST['tipo_acesso']) ? (int)$_POST['tipo_acesso'] : 1;
    
    if ($cdGrupo <= 0) {
        throw new Exception('Grupo não informado');
    }
    
    // Buscar nome do grupo para log
    $nomeGrupo = '';
    try {
        $stmtGrupo = $pdoSIMP->prepare("SELECT DS_NOME FROM SIMP.dbo.GRUPO_USUARIO WHERE CD_GRUPO_USUARIO = :id");
        $stmtGrupo->execute([':id' => $cdGrupo]);
        $nomeGrupo = $stmtGrupo->fetch(PDO::FETCH_ASSOC)['DS_NOME'] ?? '';
    } catch (Exception $e) {}
    
    // Buscar funcionalidades que ainda não estão vinculadas
    $sqlFuncs = "SELECT CD_FUNCIONALIDADE, DS_NOME FROM SIMP.dbo.FUNCIONALIDADE 
                 WHERE CD_FUNCIONALIDADE NOT IN (
                     SELECT CD_FUNCIONALIDADE FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE 
                     WHERE CD_GRUPO_USUARIO = :grupo
                 )";
    $stmtFuncs = $pdoSIMP->prepare($sqlFuncs);
    $stmtFuncs->execute([':grupo' => $cdGrupo]);
    $funcionalidades = $stmtFuncs->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($funcionalidades) === 0) {
        echo json_encode(['success' => true, 'message' => 'Todas as funcionalidades já estão vinculadas a este grupo.']);
        exit;
    }
    
    // Inserir todas - tentar sem CD_CHAVE primeiro (caso seja IDENTITY)
    $inseridos = 0;
    $funcionalidadesInseridas = [];
    
    try {
        $sqlInsert = "INSERT INTO SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE 
                      (CD_GRUPO_USUARIO, CD_FUNCIONALIDADE, ID_TIPO_ACESSO) 
                      VALUES (:grupo, :func, :acesso)";
        $stmtInsert = $pdoSIMP->prepare($sqlInsert);
        
        foreach ($funcionalidades as $func) {
            $stmtInsert->execute([':grupo' => $cdGrupo, ':func' => $func['CD_FUNCIONALIDADE'], ':acesso' => $tipoAcesso]);
            $inseridos++;
            $funcionalidadesInseridas[] = $func['DS_NOME'];
        }
    } catch (PDOException $e) {
        // Se falhou, tentar com CD_CHAVE manual
        $stmtMax = $pdoSIMP->query("SELECT ISNULL(MAX(CD_CHAVE), 0) AS MAX_ID FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE");
        $proximoId = $stmtMax->fetch(PDO::FETCH_ASSOC)['MAX_ID'] + 1;
        
        $sqlInsert = "INSERT INTO SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE 
                      (CD_CHAVE, CD_GRUPO_USUARIO, CD_FUNCIONALIDADE, ID_TIPO_ACESSO) 
                      VALUES (:id, :grupo, :func, :acesso)";
        $stmtInsert = $pdoSIMP->prepare($sqlInsert);
        
        $inseridos = 0;
        $funcionalidadesInseridas = [];
        foreach ($funcionalidades as $func) {
            $stmtInsert->execute([':id' => $proximoId, ':grupo' => $cdGrupo, ':func' => $func['CD_FUNCIONALIDADE'], ':acesso' => $tipoAcesso]);
            $inseridos++;
            $proximoId++;
            $funcionalidadesInseridas[] = $func['DS_NOME'];
        }
    }
    
    // Log (isolado)
    try {
        if (function_exists('registrarLogAlteracaoMassa')) {
            $tipoAcessoDesc = $tipoAcesso == 1 ? 'Somente Leitura' : 'Acesso Total';
            registrarLogAlteracaoMassa('Cadastros Administrativos', 'Permissão', $inseridos, 
                "Inclusão em massa de permissões para o grupo '$nomeGrupo' com tipo de acesso '$tipoAcessoDesc'",
                ['CD_GRUPO_USUARIO' => $cdGrupo, 'DS_GRUPO' => $nomeGrupo, 
                 'ID_TIPO_ACESSO' => $tipoAcesso, 'DS_TIPO_ACESSO' => $tipoAcessoDesc,
                 'funcionalidades' => $funcionalidadesInseridas]);
        }
    } catch (Exception $logEx) {}
    
    echo json_encode([
        'success' => true, 
        'message' => "$inseridos funcionalidade(s) adicionada(s) com sucesso!"
    ]);

} catch (PDOException $e) {
    // Log de erro (isolado)
    try {
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Cadastros Administrativos', 'INSERT_MASSA', $e->getMessage(), ['cd_grupo' => $cdGrupo ?? '']);
        }
    } catch (Exception $logEx) {}
    
    echo json_encode(['success' => false, 'message' => 'Erro ao incluir permissões: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
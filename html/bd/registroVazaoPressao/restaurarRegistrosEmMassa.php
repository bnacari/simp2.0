<?php
/**
 * SIMP - Registro de Vazão e Pressão
 * Endpoint: Restauração em Massa
 * VERSÃO DEBUG v2
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

$debug = [];
$debug['etapa'] = 'inicio';

try {
    $debug['etapa'] = 'verificarAuth';
    require_once '../verificarAuth.php';
    verificarPermissaoAjax('REGISTRO DE VAZÃO', ACESSO_ESCRITA);
    $debug['auth'] = 'OK';

    $debug['etapa'] = 'conexao';
    include_once '../conexao.php';
    $debug['conexao'] = isset($pdoSIMP) ? 'OK' : 'FALHOU';
    
    @include_once '../logHelper.php';

    $debug['etapa'] = 'leitura_dados';
    
    $chaves = [];
    
    $input = file_get_contents('php://input');
    $debug['raw_input'] = $input;
    
    if (!empty($input)) {
        $data = json_decode($input, true);
        $debug['json_decode'] = $data;
        
        if ($data && isset($data['chaves']) && is_array($data['chaves'])) {
            $chaves = $data['chaves'];
        }
    }
    
    if (empty($chaves) && isset($_POST['chaves'])) {
        $debug['usando_POST'] = true;
        if (is_array($_POST['chaves'])) {
            $chaves = $_POST['chaves'];
        } else {
            $chaves = json_decode($_POST['chaves'], true);
        }
    }
    
    $debug['chaves_recebidas'] = $chaves;
    
    if (empty($chaves) || !is_array($chaves)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Nenhum registro selecionado para restauração',
            'debug' => $debug
        ]);
        exit;
    }
    
    $chavesValidas = [];
    foreach ($chaves as $chave) {
        if (is_numeric($chave) && $chave > 0) {
            $chavesValidas[] = (int)$chave;
        }
    }
    
    $debug['chaves_validas'] = $chavesValidas;
    
    if (empty($chavesValidas)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Nenhuma chave válida encontrada',
            'debug' => $debug
        ]);
        exit;
    }
    
    // PRIMEIRO: Buscar registros para ver estado atual
    $debug['etapa'] = 'buscar_registros';
    $placeholders = implode(',', array_fill(0, count($chavesValidas), '?'));
    $sqlBusca = "SELECT CD_CHAVE, ID_SITUACAO FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO WHERE CD_CHAVE IN ($placeholders)";
    $stmtBusca = $pdoSIMP->prepare($sqlBusca);
    $stmtBusca->execute($chavesValidas);
    $registros = $stmtBusca->fetchAll(PDO::FETCH_ASSOC);
    
    $debug['registros_encontrados'] = count($registros);
    $debug['registros_dados'] = $registros;
    
    // Contar por situação
    $porSituacao = [];
    foreach ($registros as $reg) {
        $sit = $reg['ID_SITUACAO'];
        if (!isset($porSituacao[$sit])) $porSituacao[$sit] = 0;
        $porSituacao[$sit]++;
    }
    $debug['por_situacao_antes'] = $porSituacao;
    
    $cdUsuario = $_SESSION['cd_usuario'] ?? null;
    $debug['cd_usuario'] = $cdUsuario;
    
    $debug['etapa'] = 'iniciar_transacao';
    $pdoSIMP->beginTransaction();
    
    $restaurados = 0;
    
    $sqlUpdate = "UPDATE SIMP.dbo.REGISTRO_VAZAO_PRESSAO 
                  SET ID_SITUACAO = 1,
                      DT_ULTIMA_ATUALIZACAO = GETDATE(),
                      CD_USUARIO_ULTIMA_ATUALIZACAO = ?
                  WHERE CD_CHAVE IN ($placeholders)";
    
    $debug['sql_update'] = $sqlUpdate;
    
    $stmtUpdate = $pdoSIMP->prepare($sqlUpdate);
    
    $params = array_merge([$cdUsuario], $chavesValidas);
    $debug['params'] = $params;
    
    $resultado = $stmtUpdate->execute($params);
    $debug['execute'] = $resultado ? 'OK' : 'FALHOU';
    $debug['rowCount'] = $stmtUpdate->rowCount();
    $debug['errorInfo'] = $stmtUpdate->errorInfo();
    
    $restaurados = $stmtUpdate->rowCount();
    
    $debug['etapa'] = 'commit';
    $pdoSIMP->commit();
    $debug['commit'] = 'OK';
    
    // Verificar após UPDATE
    $debug['etapa'] = 'verificar_apos';
    $stmtVerifica = $pdoSIMP->prepare($sqlBusca);
    $stmtVerifica->execute($chavesValidas);
    $registrosApos = $stmtVerifica->fetchAll(PDO::FETCH_ASSOC);
    
    $porSituacaoApos = [];
    foreach ($registrosApos as $reg) {
        $sit = $reg['ID_SITUACAO'];
        if (!isset($porSituacaoApos[$sit])) $porSituacaoApos[$sit] = 0;
        $porSituacaoApos[$sit]++;
    }
    $debug['por_situacao_apos'] = $porSituacaoApos;
    $debug['registros_apos'] = $registrosApos;
    
    echo json_encode([
        'success' => true,
        'message' => "$restaurados registro(s) restaurado(s) com sucesso",
        'restaurados' => $restaurados,
        'solicitados' => count($chavesValidas),
        'debug' => $debug
    ]);
    
} catch (PDOException $e) {
    if (isset($pdoSIMP) && $pdoSIMP->inTransaction()) {
        $pdoSIMP->rollBack();
        $debug['rollback'] = 'executado';
    }
    
    $debug['erro_tipo'] = 'PDOException';
    $debug['erro_msg'] = $e->getMessage();
    
    echo json_encode([
        'success' => false, 
        'message' => 'Erro PDO: ' . $e->getMessage(),
        'debug' => $debug
    ]);

} catch (Exception $e) {
    if (isset($pdoSIMP) && $pdoSIMP->inTransaction()) {
        $pdoSIMP->rollBack();
        $debug['rollback'] = 'executado';
    }
    
    $debug['erro_tipo'] = 'Exception';
    $debug['erro_msg'] = $e->getMessage();
    
    echo json_encode([
        'success' => false, 
        'message' => 'Erro: ' . $e->getMessage(),
        'debug' => $debug
    ]);
}
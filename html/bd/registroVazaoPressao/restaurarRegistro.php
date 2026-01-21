<?php
/**
 * SIMP - Registro de Vazão e Pressão
 * Endpoint: Restaurar Registro Individual
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

    $debug['etapa'] = 'leitura_post';
    $debug['POST'] = $_POST;
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $debug['id_recebido'] = $id;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido', 'debug' => $debug]);
        exit;
    }

    // PRIMEIRO: Buscar o registro para ver se existe e qual estado
    $debug['etapa'] = 'buscar_registro';
    $sqlBusca = "SELECT CD_CHAVE, ID_SITUACAO, CD_PONTO_MEDICAO, DT_LEITURA FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO WHERE CD_CHAVE = ?";
    $stmtBusca = $pdoSIMP->prepare($sqlBusca);
    $stmtBusca->execute([$id]);
    $registro = $stmtBusca->fetch(PDO::FETCH_ASSOC);
    
    $debug['registro_encontrado'] = $registro ? 'SIM' : 'NAO';
    $debug['registro_dados'] = $registro;
    
    if (!$registro) {
        echo json_encode([
            'success' => false, 
            'message' => "Registro CD_CHAVE=$id não encontrado na tabela",
            'debug' => $debug
        ]);
        exit;
    }
    
    $debug['id_situacao_atual'] = $registro['ID_SITUACAO'];

    // Tentar UPDATE
    $debug['etapa'] = 'executar_update';
    $cdUsuario = $_SESSION['cd_usuario'] ?? null;
    $debug['cd_usuario'] = $cdUsuario;
    
    $sql = "UPDATE SIMP.dbo.REGISTRO_VAZAO_PRESSAO 
            SET ID_SITUACAO = 1, 
                DT_ULTIMA_ATUALIZACAO = GETDATE(),
                CD_USUARIO_ULTIMA_ATUALIZACAO = ?
            WHERE CD_CHAVE = ?";
    
    $debug['sql'] = $sql;
    $debug['params'] = [$cdUsuario, $id];
    
    $stmt = $pdoSIMP->prepare($sql);
    $debug['prepare'] = 'OK';
    
    $resultado = $stmt->execute([$cdUsuario, $id]);
    
    $debug['execute'] = $resultado ? 'OK' : 'FALHOU';
    $debug['rowCount'] = $stmt->rowCount();
    $debug['errorInfo'] = $stmt->errorInfo();

    // Verificar se mudou
    $debug['etapa'] = 'verificar_apos_update';
    $stmtVerifica = $pdoSIMP->prepare("SELECT ID_SITUACAO FROM SIMP.dbo.REGISTRO_VAZAO_PRESSAO WHERE CD_CHAVE = ?");
    $stmtVerifica->execute([$id]);
    $registroApos = $stmtVerifica->fetch(PDO::FETCH_ASSOC);
    $debug['id_situacao_apos'] = $registroApos ? $registroApos['ID_SITUACAO'] : 'NAO ENCONTRADO';

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'Registro restaurado com sucesso',
            'debug' => $debug
        ]);
    } else {
        // rowCount = 0 pode significar que o valor já era 1
        if ($registro['ID_SITUACAO'] == 1) {
            echo json_encode([
                'success' => false, 
                'message' => 'Registro já está com ID_SITUACAO = 1 (ativo)',
                'debug' => $debug
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'UPDATE executou mas rowCount=0. Verifique triggers ou permissões.',
                'debug' => $debug
            ]);
        }
    }

} catch (PDOException $e) {
    $debug['erro_tipo'] = 'PDOException';
    $debug['erro_msg'] = $e->getMessage();
    $debug['erro_code'] = $e->getCode();
    $debug['erro_linha'] = $e->getLine();
    
    echo json_encode([
        'success' => false, 
        'message' => 'Erro PDO: ' . $e->getMessage(),
        'debug' => $debug
    ]);

} catch (Exception $e) {
    $debug['erro_tipo'] = 'Exception';
    $debug['erro_msg'] = $e->getMessage();
    $debug['erro_code'] = $e->getCode();
    $debug['erro_linha'] = $e->getLine();
    
    echo json_encode([
        'success' => false, 
        'message' => 'Erro: ' . $e->getMessage(),
        'debug' => $debug
    ]);
}
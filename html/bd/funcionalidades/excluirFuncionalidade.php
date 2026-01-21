<?php
// bd/funcionalidades/excluirFuncionalidade.php
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
    $nomeFuncionalidade = '';
    try {
        $stmtDados = $pdoSIMP->prepare("SELECT DS_NOME FROM SIMP.dbo.FUNCIONALIDADE WHERE CD_FUNCIONALIDADE = :id");
        $stmtDados->execute([':id' => $id]);
        $dadosExcluidos = $stmtDados->fetch(PDO::FETCH_ASSOC);
        $nomeFuncionalidade = $dadosExcluidos['DS_NOME'] ?? '';
    } catch (Exception $e) {}
    
    // Verificar se existem permissões vinculadas (GRUPO_USUARIO_X_FUNCIONALIDADE)
    $sqlCheck = "SELECT COUNT(*) as total FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE WHERE CD_FUNCIONALIDADE = :id";
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $stmtCheck->execute([':id' => $id]);
    $totalVinculados = $stmtCheck->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($totalVinculados > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "Não é possível excluir. Existem {$totalVinculados} permissão(ões) vinculada(s) a esta funcionalidade."
        ]);
        exit;
    }
    
    // Excluir
    $sql = "DELETE FROM SIMP.dbo.FUNCIONALIDADE WHERE CD_FUNCIONALIDADE = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    if ($stmt->rowCount() > 0) {
        // Log (isolado)
        try {
            if (function_exists('registrarLogDelete')) {
                registrarLogDelete('Cadastros Administrativos', 'Funcionalidade', $id, $nomeFuncionalidade, $dadosExcluidos);
            }
        } catch (Exception $logEx) {}
        
        echo json_encode(['success' => true, 'message' => 'Funcionalidade excluída com sucesso!']);
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
        'message' => 'Erro ao excluir funcionalidade: ' . $e->getMessage()
    ]);
}
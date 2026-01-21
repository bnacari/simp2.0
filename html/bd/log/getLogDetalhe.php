<?php
/**
 * SIMP - Buscar Detalhes de um Log Específico
 * Endpoint para consulta completa de um registro de log
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
    require_once '../conexao.php';

    // Verifica permissão
    verificarPermissaoAjax('Consultar Log', ACESSO_LEITURA);

    // Parâmetro obrigatório
    $cdLog = isset($_GET['cdLog']) ? (int)$_GET['cdLog'] : 0;

    if ($cdLog <= 0) {
        throw new Exception('Código do log não informado');
    }

    // Buscar log com todos os detalhes
    $sql = "
        SELECT 
            L.CD_LOG,
            L.CD_USUARIO,
            L.CD_FUNCIONALIDADE,
            L.CD_UNIDADE,
            L.DT_LOG,
            L.TP_LOG,
            L.NM_LOG,
            CAST(L.DS_LOG AS VARCHAR(MAX)) AS DS_LOG,
            L.DS_VERSAO,
            L.NM_SERVIDOR,
            U.DS_NOME AS DS_USUARIO,
            U.DS_LOGIN AS DS_LOGIN_USUARIO,
            F.DS_NOME AS DS_FUNCIONALIDADE,
            UN.DS_NOME AS DS_UNIDADE
        FROM SIMP.dbo.LOG L
        LEFT JOIN SIMP.dbo.USUARIO U ON L.CD_USUARIO = U.CD_USUARIO
        LEFT JOIN SIMP.dbo.FUNCIONALIDADE F ON L.CD_FUNCIONALIDADE = F.CD_FUNCIONALIDADE
        LEFT JOIN SIMP.dbo.UNIDADE UN ON L.CD_UNIDADE = UN.CD_UNIDADE
        WHERE L.CD_LOG = :cdLog
    ";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->bindValue(':cdLog', $cdLog, PDO::PARAM_INT);
    $stmt->execute();
    
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        throw new Exception('Registro de log não encontrado');
    }

    echo json_encode([
        'success' => true,
        'data' => $log
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar detalhes do log: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

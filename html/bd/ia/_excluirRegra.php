<?php
/**
 * SIMP - API para Excluir Regra da IA
 * Remove uma regra do banco de dados
 * 
 * @author Bruno
 * @version 1.0
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';

    if (!isset($pdoSIMP)) {
        throw new Exception('Conexão com banco de dados não estabelecida');
    }

    // Receber dados JSON
    $rawInput = file_get_contents('php://input');
    $dados = json_decode($rawInput, true);

    if (!$dados || !isset($dados['cdChave'])) {
        throw new Exception('ID da regra não informado');
    }

    $cdChave = (int)$dados['cdChave'];

    if ($cdChave <= 0) {
        throw new Exception('ID da regra inválido');
    }

    // Verificar se regra existe
    $sqlCheck = "SELECT DS_TITULO FROM SIMP.dbo.IA_REGRAS WHERE CD_CHAVE = :cdChave";
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $stmtCheck->execute([':cdChave' => $cdChave]);
    $regra = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$regra) {
        throw new Exception('Regra não encontrada');
    }

    // Excluir regra
    $sql = "DELETE FROM SIMP.dbo.IA_REGRAS WHERE CD_CHAVE = :cdChave";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':cdChave' => $cdChave]);

    echo json_encode([
        'success' => true,
        'message' => 'Regra "' . $regra['DS_TITULO'] . '" excluída com sucesso!'
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro de banco de dados: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

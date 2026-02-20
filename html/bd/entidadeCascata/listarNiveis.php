<?php
/**
 * SIMP - Listar Níveis de Entidade (Cascata Genérica)
 * Retorna todos os níveis cadastrados para popular selects.
 * 
 * @author Bruno - CESAN
 * @version 1.0
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';

    if (!isset($pdoSIMP)) {
        throw new Exception('Conexão não estabelecida');
    }

    $sql = "SELECT 
                CD_CHAVE,
                DS_NOME,
                DS_ICONE,
                DS_COR,
                NR_ORDEM,
                OP_PERMITE_PONTO,
                OP_EH_SISTEMA,
                OP_ATIVO
            FROM SIMP.dbo.ENTIDADE_NIVEL
            WHERE OP_ATIVO = 1
            ORDER BY NR_ORDEM, DS_NOME";

    $stmt = $pdoSIMP->query($sql);
    $niveis = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'niveis'  => $niveis
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
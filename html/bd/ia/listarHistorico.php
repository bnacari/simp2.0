<?php
/**
 * SIMP - API para Listar Histórico de Versões das Regras da IA
 * Retorna as últimas 50 versões com informações resumidas
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

    $sql = "SELECT TOP 50
                R.CD_CHAVE,
                LEN(CAST(R.DS_CONTEUDO AS VARCHAR(MAX))) AS caracteres,
                R.CD_USUARIO_CRIACAO,
                R.DT_CRIACAO,
                U.DS_NOME AS NM_USUARIO
            FROM SIMP.dbo.IA_REGRAS R
            LEFT JOIN SIMP.dbo.USUARIO U ON R.CD_USUARIO_CRIACAO = U.CD_USUARIO
            ORDER BY R.CD_CHAVE DESC";

    $stmt = $pdoSIMP->query($sql);
    $versoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $resultado = [];
    foreach ($versoes as $v) {
        $resultado[] = [
            'cdChave' => $v['CD_CHAVE'],
            'caracteres' => (int)$v['caracteres'],
            'usuario' => $v['NM_USUARIO'] ?? 'Sistema',
            'dtCriacao' => $v['DT_CRIACAO']
        ];
    }

    echo json_encode([
        'success' => true,
        'versoes' => $resultado
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Invalid object name') !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Tabela IA_REGRAS não encontrada.'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erro de banco de dados: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

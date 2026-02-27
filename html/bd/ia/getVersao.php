<?php
/**
 * SIMP - API para Buscar Conteúdo de uma Versão das Regras da IA
 * Retorna o conteúdo completo de uma versão específica
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

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('ID da versão não informado ou inválido');
    }

    $id = (int)$_GET['id'];

    $sql = "SELECT
                R.CD_CHAVE,
                CAST(R.DS_CONTEUDO AS VARCHAR(MAX)) AS DS_CONTEUDO,
                R.CD_USUARIO_CRIACAO,
                R.DT_CRIACAO,
                U.DS_NOME AS NM_USUARIO
            FROM SIMP.dbo.IA_REGRAS R
            LEFT JOIN SIMP.dbo.USUARIO U ON R.CD_USUARIO_CRIACAO = U.CD_USUARIO
            WHERE R.CD_CHAVE = :id";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    $versao = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$versao) {
        throw new Exception('Versão não encontrada');
    }

    echo json_encode([
        'success' => true,
        'versao' => [
            'cdChave' => $versao['CD_CHAVE'],
            'conteudo' => $versao['DS_CONTEUDO'],
            'usuario' => $versao['NM_USUARIO'] ?? 'Sistema',
            'dtCriacao' => $versao['DT_CRIACAO']
        ]
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

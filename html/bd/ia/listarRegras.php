<?php
/**
 * SIMP - API para Listar Regras da IA
 * Retorna o conteúdo único de instruções do banco
 * 
 * @author Bruno
 * @version 2.1 - Sem fallback para arquivo
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';

    if (!isset($pdoSIMP)) {
        throw new Exception('Conexão com banco de dados não estabelecida');
    }

    // Buscar registro único
    $sql = "SELECT TOP 1 
                CD_CHAVE,
                CAST(DS_CONTEUDO AS VARCHAR(MAX)) AS DS_CONTEUDO,
                CD_USUARIO_CRIACAO,
                DT_CRIACAO,
                CD_USUARIO_ATUALIZACAO,
                DT_ATUALIZACAO
            FROM SIMP.dbo.IA_REGRAS 
            ORDER BY CD_CHAVE DESC";
    
    $stmt = $pdoSIMP->query($sql);
    $regra = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($regra) {
        echo json_encode([
            'success' => true,
            'regra' => [
                'cdChave' => $regra['CD_CHAVE'],
                'conteudo' => $regra['DS_CONTEUDO'],
                'dtCriacao' => $regra['DT_CRIACAO'],
                'dtAtualizacao' => $regra['DT_ATUALIZACAO'],
                'caracteres' => strlen($regra['DS_CONTEUDO']),
                'linhas' => substr_count($regra['DS_CONTEUDO'], "\n") + 1
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Nenhum registro no banco
        echo json_encode([
            'success' => true,
            'regra' => [
                'cdChave' => null,
                'conteudo' => '',
                'dtCriacao' => null,
                'dtAtualizacao' => null,
                'caracteres' => 0,
                'linhas' => 0
            ],
            'aviso' => 'Nenhuma regra cadastrada. Insira as instruções e salve.'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    // Verificar se é erro de tabela inexistente
    if (strpos($e->getMessage(), 'Invalid object name') !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Tabela IA_REGRAS não encontrada. Execute o script SQL para criar a tabela.'
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

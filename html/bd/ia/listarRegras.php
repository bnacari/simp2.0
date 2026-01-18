<?php
/**
 * SIMP - API para Listar Regras da IA
 * Retorna todas as regras com estatísticas
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

    // Verificar se tabela existe
    $checkTable = $pdoSIMP->query("SELECT TOP 1 1 FROM SIMP.dbo.IA_REGRAS");
    
    // Buscar todas as regras ordenadas
    $sql = "SELECT 
                CD_CHAVE,
                DS_TITULO,
                DS_CATEGORIA,
                DS_CONTEUDO,
                NR_ORDEM,
                OP_ATIVO,
                CD_USUARIO_CRIACAO,
                DT_CRIACAO,
                CD_USUARIO_ATUALIZACAO,
                DT_ATUALIZACAO
            FROM SIMP.dbo.IA_REGRAS
            ORDER BY NR_ORDEM ASC, CD_CHAVE ASC";
    
    $stmt = $pdoSIMP->query($sql);
    $regras = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular estatísticas
    $totalRegras = count($regras);
    $regrasAtivas = 0;
    $categorias = [];
    $totalCaracteres = 0;

    foreach ($regras as $regra) {
        if ($regra['OP_ATIVO'] == 1) {
            $regrasAtivas++;
        }
        if (!empty($regra['DS_CATEGORIA']) && !in_array($regra['DS_CATEGORIA'], $categorias)) {
            $categorias[] = $regra['DS_CATEGORIA'];
        }
        $totalCaracteres += strlen($regra['DS_CONTEUDO'] ?? '');
    }

    echo json_encode([
        'success' => true,
        'regras' => $regras,
        'estatisticas' => [
            'total' => $totalRegras,
            'ativas' => $regrasAtivas,
            'categorias' => count($categorias),
            'caracteres' => $totalCaracteres
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // Verificar se é erro de tabela inexistente
    if (strpos($e->getMessage(), 'Invalid object name') !== false || 
        strpos($e->getMessage(), 'IA_REGRAS') !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Tabela IA_REGRAS não encontrada. Execute o script SQL para criar a tabela.',
            'regras' => [],
            'estatisticas' => ['total' => 0, 'ativas' => 0, 'categorias' => 0, 'caracteres' => 0]
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

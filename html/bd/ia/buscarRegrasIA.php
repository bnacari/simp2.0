<?php
/**
 * SIMP - API para Buscar Regras da IA como Texto
 * Retorna todas as regras ativas concatenadas para uso no prompt da IA
 * 
 * @author Bruno
 * @version 1.0
 */

/**
 * Busca regras do banco e retorna como string formatada
 * @param PDO $pdo Conexão com o banco
 * @return string Regras concatenadas
 */
function buscarRegrasIA($pdo) {
    try {
        // Buscar regras ativas ordenadas
        $sql = "SELECT 
                    DS_TITULO,
                    DS_CATEGORIA,
                    DS_CONTEUDO
                FROM SIMP.dbo.IA_REGRAS
                WHERE OP_ATIVO = 1
                ORDER BY NR_ORDEM ASC, CD_CHAVE ASC";
        
        $stmt = $pdo->query($sql);
        $regras = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($regras)) {
            return '';
        }

        // Montar texto formatado
        $texto = "=== INSTRUÇÕES DO ASSISTENTE ===\n\n";

        foreach ($regras as $index => $regra) {
            $numero = $index + 1;
            $categoria = $regra['DS_CATEGORIA'] ? " [{$regra['DS_CATEGORIA']}]" : '';
            
            $texto .= "--- {$numero}. {$regra['DS_TITULO']}{$categoria} ---\n";
            $texto .= $regra['DS_CONTEUDO'] . "\n\n";
        }

        return $texto;

    } catch (PDOException $e) {
        // Se tabela não existir, retornar vazio
        if (strpos($e->getMessage(), 'Invalid object name') !== false) {
            return '';
        }
        // Log do erro mas não interrompe
        error_log('Erro ao buscar regras IA: ' . $e->getMessage());
        return '';
    } catch (Exception $e) {
        error_log('Erro ao buscar regras IA: ' . $e->getMessage());
        return '';
    }
}

/**
 * Busca regras do banco ou do arquivo de fallback
 * @param PDO|null $pdo Conexão com o banco (opcional)
 * @return string Regras concatenadas
 */
function obterRegrasIA($pdo = null) {
    // Se tiver conexão, tentar buscar do banco primeiro
    if ($pdo) {
        $regrasBanco = buscarRegrasIA($pdo);
        if (!empty($regrasBanco)) {
            return $regrasBanco;
        }
    }

    // Fallback: buscar do arquivo ia_regras.php
    $regrasFile = __DIR__ . '/../config/ia_regras.php';
    if (file_exists($regrasFile)) {
        $regras = require $regrasFile;
        if (!empty($regras)) {
            return $regras;
        }
    }

    return '';
}

// Se chamado diretamente, retornar JSON
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        include_once '../conexao.php';
        
        if (!isset($pdoSIMP)) {
            throw new Exception('Conexão não estabelecida');
        }

        $regras = buscarRegrasIA($pdoSIMP);
        
        echo json_encode([
            'success' => true,
            'regras' => $regras,
            'caracteres' => strlen($regras)
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'regras' => ''
        ], JSON_UNESCAPED_UNICODE);
    }
}

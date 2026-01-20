<?php
/**
 * SIMP - Buscar Regras da IA
 * Retorna as instruções do banco de dados para uso no prompt da IA
 * 
 * @author Bruno
 * @version 2.0
 */

/**
 * Busca instruções do banco de dados
 * @param PDO $pdo Conexão com o banco
 * @return string Instruções ou string vazia
 */
function buscarRegrasIA($pdo) {
    try {
        $sql = "SELECT TOP 1 CAST(DS_CONTEUDO AS VARCHAR(MAX)) AS DS_CONTEUDO 
                FROM SIMP.dbo.IA_REGRAS 
                ORDER BY CD_CHAVE DESC";
        $stmt = $pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? $row['DS_CONTEUDO'] : '';
        
    } catch (PDOException $e) {
        // Se tabela não existir, retornar vazio
        if (strpos($e->getMessage(), 'Invalid object name') !== false) {
            return '';
        }
        error_log('Erro ao buscar regras IA: ' . $e->getMessage());
        return '';
    } catch (Exception $e) {
        error_log('Erro ao buscar regras IA: ' . $e->getMessage());
        return '';
    }
}

/**
 * Busca instruções do banco ou do arquivo de fallback
 * @param PDO|null $pdo Conexão com o banco (opcional)
 * @return string Instruções
 */
function obterRegrasIA($pdo = null) {
    // Se tiver conexão, tentar buscar do banco primeiro
    if ($pdo) {
        $regras = buscarRegrasIA($pdo);
        if (!empty($regras)) {
            return $regras;
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
            'caracteres' => strlen($regras),
            'fonte' => !empty($regras) ? 'banco' : 'vazio'
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'regras' => ''
        ], JSON_UNESCAPED_UNICODE);
    }
}

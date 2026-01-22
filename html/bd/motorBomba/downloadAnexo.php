<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Download de Anexo de Conjunto Motor-Bomba
 * 
 * Utiliza tabela genérica SIMP.dbo.ANEXO com armazenamento BLOB
 * 
 * @author SIMP
 * @version 2.0
 */

ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
include_once '../conexao.php';

try {
    $cdAnexo = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($cdAnexo <= 0) {
        throw new Exception('ID do anexo não informado');
    }

    // Buscar anexo
    $sql = "SELECT DS_NOME, DS_FILENAME, VB_ANEXO 
            FROM SIMP.dbo.ANEXO 
            WHERE CD_ANEXO = :id";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $cdAnexo]);
    $anexo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$anexo) {
        throw new Exception('Anexo não encontrado');
    }

    $nomeArquivo = $anexo['DS_FILENAME'];
    $conteudo = $anexo['VB_ANEXO'];

    if (empty($conteudo)) {
        throw new Exception('Arquivo vazio ou corrompido');
    }

    // Determinar Content-Type baseado na extensão
    $extensao = strtolower(pathinfo($nomeArquivo, PATHINFO_EXTENSION));
    $contentTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        '7z' => 'application/x-7z-compressed',
        'txt' => 'text/plain',
        'csv' => 'text/csv'
    ];

    $contentType = $contentTypes[$extensao] ?? 'application/octet-stream';

    // Limpar buffers
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Headers para download
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    header('Content-Length: ' . strlen($conteudo));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Enviar conteúdo
    echo $conteudo;
    exit;

} catch (Exception $e) {
    // Em caso de erro, retornar JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
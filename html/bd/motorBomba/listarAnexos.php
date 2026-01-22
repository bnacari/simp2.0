<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Listar Anexos de Conjunto Motor-Bomba
 * 
 * Utiliza tabela genérica SIMP.dbo.ANEXO
 * 
 * @author SIMP
 * @version 2.0
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
include_once '../conexao.php';

try {
    $cdConjunto = isset($_GET['cd_conjunto']) ? (int)$_GET['cd_conjunto'] : 0;

    if ($cdConjunto <= 0) {
        throw new Exception('ID do conjunto não informado');
    }

    // Buscar CD_FUNCIONALIDADE para "Cadastro de Conjunto Motor-Bomba"
    $sqlFunc = "SELECT TOP 1 CD_FUNCIONALIDADE, DS_NOME FROM SIMP.dbo.FUNCIONALIDADE 
                WHERE DS_NOME LIKE '%Motor%Bomba%'
                   OR DS_NOME LIKE '%Conjunto%Motor%'
                   OR DS_NOME LIKE '%CMB%'
                ORDER BY 
                    CASE WHEN DS_NOME LIKE '%Conjunto%Motor%Bomba%' THEN 1
                         WHEN DS_NOME LIKE '%Motor-Bomba%' THEN 2
                         WHEN DS_NOME LIKE '%Motor Bomba%' THEN 3
                         ELSE 4 END";
    $stmtFunc = $pdoSIMP->query($sqlFunc);
    $funcionalidade = $stmtFunc->fetch(PDO::FETCH_ASSOC);
    
    if (!$funcionalidade) {
        // Se não encontrou, retorna lista vazia (funcionalidade pode não estar cadastrada ainda)
        echo json_encode([
            'success' => true,
            'data' => [],
            'total' => 0
        ]);
        exit;
    }

    $cdFuncionalidade = $funcionalidade['CD_FUNCIONALIDADE'];

    // Buscar anexos
    $sql = "SELECT 
                A.CD_ANEXO,
                A.DS_NOME,
                A.DS_FILENAME,
                A.DS_OBSERVACAO,
                A.DT_INCLUSAO,
                A.DT_ULTIMA_ATUALIZACAO,
                DATALENGTH(A.VB_ANEXO) AS VL_TAMANHO_BYTES,
                U.DS_NOME AS DS_USUARIO_UPLOAD
            FROM SIMP.dbo.ANEXO A
            LEFT JOIN SIMP.dbo.USUARIO U ON A.CD_USUARIO_RESPONSAVEL = U.CD_USUARIO
            WHERE A.CD_FUNCIONALIDADE = :cd_funcionalidade
              AND A.CD_CHAVE_FUNCIONALIDADE = :cd_conjunto
            ORDER BY A.DT_INCLUSAO DESC";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([
        ':cd_funcionalidade' => $cdFuncionalidade,
        ':cd_conjunto' => $cdConjunto
    ]);
    $anexos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatar dados
    $resultado = [];
    foreach ($anexos as $anexo) {
        // Determinar ícone baseado na extensão
        $extensao = strtolower(pathinfo($anexo['DS_FILENAME'], PATHINFO_EXTENSION));
        if (in_array($extensao, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
            $icone = 'image-outline';
            $tipoIcone = 'imagem';
        } elseif ($extensao === 'pdf') {
            $icone = 'document-text-outline';
            $tipoIcone = 'pdf';
        } elseif (in_array($extensao, ['doc', 'docx'])) {
            $icone = 'document-outline';
            $tipoIcone = 'documento';
        } elseif (in_array($extensao, ['xls', 'xlsx'])) {
            $icone = 'grid-outline';
            $tipoIcone = 'planilha';
        } elseif (in_array($extensao, ['zip', 'rar', '7z'])) {
            $icone = 'archive-outline';
            $tipoIcone = 'arquivo';
        } else {
            $icone = 'document-outline';
            $tipoIcone = 'outro';
        }

        // Formatar tamanho
        $bytes = (int)$anexo['VL_TAMANHO_BYTES'];
        if ($bytes >= 1048576) {
            $tamanhoFormatado = number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        } elseif ($bytes >= 1024) {
            $tamanhoFormatado = number_format($bytes / 1024, 2, ',', '.') . ' KB';
        } else {
            $tamanhoFormatado = $bytes . ' bytes';
        }

        $resultado[] = [
            'CD_ANEXO' => $anexo['CD_ANEXO'],
            'DS_NOME' => $anexo['DS_NOME'],
            'DS_FILENAME' => $anexo['DS_FILENAME'],
            'DS_OBSERVACAO' => $anexo['DS_OBSERVACAO'],
            'DT_INCLUSAO' => $anexo['DT_INCLUSAO'],
            'VL_TAMANHO_BYTES' => $bytes,
            'VL_TAMANHO_FORMATADO' => $tamanhoFormatado,
            'DS_USUARIO_UPLOAD' => $anexo['DS_USUARIO_UPLOAD'],
            'DS_ICONE' => $icone,
            'DS_TIPO_ICONE' => $tipoIcone
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $resultado,
        'total' => count($resultado)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
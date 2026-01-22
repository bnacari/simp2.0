<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Upload de Anexo de Conjunto Motor-Bomba
 * 
 * Utiliza tabela genérica SIMP.dbo.ANEXO com armazenamento BLOB
 * CD_CHAVE_FUNCIONALIDADE = CD_CHAVE da tabela CONJUNTO_MOTOR_BOMBA
 * 
 * @author SIMP
 * @version 2.1
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
@include_once '../logHelper.php';
include_once '../conexao.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    // Validar dados
    $cdConjunto = isset($_POST['cd_conjunto']) ? (int) $_POST['cd_conjunto'] : 0;
    $observacao = isset($_POST['observacao']) ? trim($_POST['observacao']) : null;

    if ($cdConjunto <= 0) {
        throw new Exception('ID do conjunto não informado');
    }

    if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        $erros = [
            UPLOAD_ERR_INI_SIZE => 'Arquivo excede o tamanho máximo permitido pelo servidor',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o tamanho máximo permitido',
            UPLOAD_ERR_PARTIAL => 'Upload parcial do arquivo',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão'
        ];
        $erro = $_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE;
        throw new Exception($erros[$erro] ?? 'Erro no upload do arquivo');
    }

    $arquivo = $_FILES['arquivo'];
    $nomeOriginal = $arquivo['name'];
    $tamanho = $arquivo['size'];
    $tmpName = $arquivo['tmp_name'];

    // Validar extensão
    $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', '7z', 'txt', 'csv'];
    $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

    if (!in_array($extensao, $extensoesPermitidas)) {
        throw new Exception('Tipo de arquivo não permitido. Extensões aceitas: ' . implode(', ', $extensoesPermitidas));
    }

    // Validar tamanho (máximo 10MB)
    $tamanhoMaximo = 10 * 1024 * 1024;
    if ($tamanho > $tamanhoMaximo) {
        throw new Exception('Arquivo muito grande. Tamanho máximo: 10MB');
    }

    // Buscar CD_FUNCIONALIDADE para "Cadastro de Conjunto Motor-Bomba"
    // Tenta vários padrões de nome
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
        // Listar funcionalidades disponíveis para debug
        $sqlLista = "SELECT TOP 10 CD_FUNCIONALIDADE, DS_NOME FROM SIMP.dbo.FUNCIONALIDADE ORDER BY DS_NOME";
        $stmtLista = $pdoSIMP->query($sqlLista);
        $lista = $stmtLista->fetchAll(PDO::FETCH_COLUMN, 1);
        throw new Exception('Funcionalidade de Motor-Bomba não encontrada. Cadastre na tabela FUNCIONALIDADE. Existentes: ' . implode(', ', $lista));
    }

    $cdFuncionalidade = $funcionalidade['CD_FUNCIONALIDADE'];

    // Obter usuário logado
    $cdUsuario = $_SESSION['cd_usuario'] ?? null;
    if (!$cdUsuario) {
        throw new Exception('Usuário não identificado. Faça login novamente.');
    }

    // Ler conteúdo do arquivo como binário
    $conteudoBinario = file_get_contents($tmpName);
    if ($conteudoBinario === false) {
        throw new Exception('Falha ao ler o arquivo');
    }

    // Gerar nome descritivo
    $dsNome = pathinfo($nomeOriginal, PATHINFO_FILENAME);
    if ($observacao) {
        $dsNome = $observacao;
    }

    // Inserir no banco
    // CD_CHAVE_FUNCIONALIDADE = CD_CHAVE da tabela CONJUNTO_MOTOR_BOMBA
    // Para SQL Server, usar 0x + hex para dados binários
    $hexData = '0x' . bin2hex($conteudoBinario);

    $sql = "INSERT INTO SIMP.dbo.ANEXO (
                CD_FUNCIONALIDADE,
                CD_CHAVE_FUNCIONALIDADE,
                DS_NOME,
                DS_FILENAME,
                DS_OBSERVACAO,
                VB_ANEXO,
                CD_USUARIO_RESPONSAVEL,
                CD_USUARIO_ULTIMA_ATUALIZACAO,
                DT_INCLUSAO,
                DT_ULTIMA_ATUALIZACAO
            ) VALUES (
                :cd_funcionalidade,
                :cd_chave_funcionalidade,
                :ds_nome,
                :ds_filename,
                :ds_observacao,
                CONVERT(VARBINARY(MAX), {$hexData}),
                :cd_usuario,
                :cd_usuario2,
                GETDATE(),
                GETDATE()
            )";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->bindValue(':cd_funcionalidade', $cdFuncionalidade, PDO::PARAM_INT);
    $stmt->bindValue(':cd_chave_funcionalidade', $cdConjunto, PDO::PARAM_INT);
    $stmt->bindValue(':ds_nome', $dsNome, PDO::PARAM_STR);
    $stmt->bindValue(':ds_filename', $nomeOriginal, PDO::PARAM_STR);
    $stmt->bindValue(':ds_observacao', $observacao, PDO::PARAM_STR);
    $stmt->bindValue(':cd_usuario', $cdUsuario, PDO::PARAM_INT);
    $stmt->bindValue(':cd_usuario2', $cdUsuario, PDO::PARAM_INT);

    if (!$stmt->execute()) {
        $errorInfo = $stmt->errorInfo();
        throw new Exception('Erro ao inserir anexo: ' . ($errorInfo[2] ?? 'Erro desconhecido'));
    }

    // Obter ID inserido
    $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID");
    $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['ID'];

    // Registrar log
    if (function_exists('registrarLogInsert')) {
        try {
            registrarLogInsert('Conjunto Motor-Bomba', 'Anexo', $novoId, $nomeOriginal, [
                'DS_FILENAME' => $nomeOriginal,
                'VL_TAMANHO' => $tamanho,
                'CD_CONJUNTO_MOTOR_BOMBA' => $cdConjunto
            ]);
        } catch (Exception $logEx) {
            error_log('Erro ao registrar log de upload anexo: ' . $logEx->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Arquivo enviado com sucesso!',
        'data' => [
            'CD_ANEXO' => $novoId,
            'DS_FILENAME' => $nomeOriginal
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // Registrar log de erro
    if (function_exists('registrarLogErro')) {
        try {
            registrarLogErro('Conjunto Motor-Bomba', 'UPLOAD_ANEXO', $e->getMessage(), [
                'cd_conjunto' => $cdConjunto ?? null,
                'arquivo' => $nomeOriginal ?? null
            ]);
        } catch (Exception $logEx) {
        }
    }

    echo json_encode([
        'success' => false,
        'message' => 'Erro no banco de dados: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
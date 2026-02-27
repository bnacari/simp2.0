<?php
/**
 * SIMP - API para Salvar Regras da IA
 * Sempre cria nova versão (INSERT) e mantém no máximo 50 versões
 *
 * @author Bruno
 * @version 3.0 - Versionamento de instruções
 */

header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../conexao.php';

    // Iniciar sessão para pegar usuário logado
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($pdoSIMP)) {
        throw new Exception('Conexão com banco de dados não estabelecida');
    }

    // Receber dados JSON
    $rawInput = file_get_contents('php://input');
    $dados = json_decode($rawInput, true);

    if (!$dados || !isset($dados['conteudo'])) {
        throw new Exception('Conteúdo não informado');
    }

    $conteudo = trim($dados['conteudo']);

    if (empty($conteudo)) {
        throw new Exception('O conteúdo das instruções é obrigatório');
    }

    // Usuário logado
    $cdUsuario = isset($_SESSION['cd_usuario']) ? (int)$_SESSION['cd_usuario'] : null;

    // INSERT - Sempre criar nova versão
    $sql = "INSERT INTO SIMP.dbo.IA_REGRAS
            (DS_CONTEUDO, CD_USUARIO_CRIACAO, DT_CRIACAO)
            VALUES
            (:conteudo, :usuario, GETDATE())";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([
        ':conteudo' => $conteudo,
        ':usuario' => $cdUsuario
    ]);

    // Pegar ID gerado
    $cdChave = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS id")->fetch(PDO::FETCH_ASSOC)['id'];

    // Limpar versões antigas, mantendo apenas as 50 mais recentes
    $sqlClean = "DELETE FROM SIMP.dbo.IA_REGRAS
                 WHERE CD_CHAVE NOT IN (
                     SELECT TOP 50 CD_CHAVE FROM SIMP.dbo.IA_REGRAS ORDER BY CD_CHAVE DESC
                 )";
    $pdoSIMP->exec($sqlClean);

    // Log de INSERT (isolado)
    try {
        @include_once '../logHelper.php';
        if (function_exists('registrarLogInsert')) {
            registrarLogInsert(
                'Treinamento IA',
                'Instruções da IA',
                $cdChave,
                'Regras de comportamento',
                ['caracteres' => strlen($conteudo)]
            );
        }
    } catch (Exception $logEx) {
        error_log('Erro ao registrar log de INSERT em IA_REGRAS: ' . $logEx->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Instruções salvas com sucesso!',
        'cdChave' => $cdChave
    ], JSON_UNESCAPED_UNICODE);

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

<?php
/**
 * SIMP - API para Salvar Regra da IA
 * Insere nova regra ou atualiza existente
 * 
 * @author Bruno
 * @version 1.0
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

    if (!$dados) {
        throw new Exception('Dados inválidos');
    }

    // Extrair campos
    $cdChave = isset($dados['cdChave']) && $dados['cdChave'] !== '' ? (int)$dados['cdChave'] : null;
    $titulo = trim($dados['titulo'] ?? '');
    $categoria = trim($dados['categoria'] ?? '') ?: null;
    $ordem = (int)($dados['ordem'] ?? 0);
    $ativo = (int)($dados['ativo'] ?? 1);
    $conteudo = trim($dados['conteudo'] ?? '');

    // Validações
    if (empty($titulo)) {
        throw new Exception('O título é obrigatório');
    }

    if (empty($conteudo)) {
        throw new Exception('O conteúdo é obrigatório');
    }

    if (strlen($titulo) > 200) {
        throw new Exception('O título deve ter no máximo 200 caracteres');
    }

    if ($categoria && strlen($categoria) > 100) {
        throw new Exception('A categoria deve ter no máximo 100 caracteres');
    }

    // Usuário logado
    $cdUsuario = isset($_SESSION['cd_usuario']) ? (int)$_SESSION['cd_usuario'] : null;

    if ($cdChave) {
        // UPDATE - Atualizar regra existente
        $sql = "UPDATE SIMP.dbo.IA_REGRAS SET
                    DS_TITULO = :titulo,
                    DS_CATEGORIA = :categoria,
                    DS_CONTEUDO = :conteudo,
                    NR_ORDEM = :ordem,
                    OP_ATIVO = :ativo,
                    CD_USUARIO_ATUALIZACAO = :usuario,
                    DT_ATUALIZACAO = GETDATE()
                WHERE CD_CHAVE = :cdChave";

        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':titulo' => $titulo,
            ':categoria' => $categoria,
            ':conteudo' => $conteudo,
            ':ordem' => $ordem,
            ':ativo' => $ativo,
            ':usuario' => $cdUsuario,
            ':cdChave' => $cdChave
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Regra não encontrada ou nenhuma alteração realizada');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Regra atualizada com sucesso!',
            'id' => $cdChave
        ], JSON_UNESCAPED_UNICODE);

    } else {
        // INSERT - Nova regra
        $sql = "INSERT INTO SIMP.dbo.IA_REGRAS 
                (DS_TITULO, DS_CATEGORIA, DS_CONTEUDO, NR_ORDEM, OP_ATIVO, CD_USUARIO_CRIACAO, DT_CRIACAO)
                VALUES 
                (:titulo, :categoria, :conteudo, :ordem, :ativo, :usuario, GETDATE())";

        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':titulo' => $titulo,
            ':categoria' => $categoria,
            ':conteudo' => $conteudo,
            ':ordem' => $ordem,
            ':ativo' => $ativo,
            ':usuario' => $cdUsuario
        ]);

        // Recuperar ID gerado
        $sqlId = "SELECT SCOPE_IDENTITY() AS novo_id";
        $stmtId = $pdoSIMP->query($sqlId);
        $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['novo_id'];

        echo json_encode([
            'success' => true,
            'message' => 'Regra criada com sucesso!',
            'id' => $novoId
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro de banco de dados: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

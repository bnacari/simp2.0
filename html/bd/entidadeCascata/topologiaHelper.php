<?php
/**
 * SIMP 2.0 — Helper: Disparo automatico de snapshot de topologia
 * 
 * Incluir nos endpoints que alteram a topologia do flowchart:
 *   - salvarNodo.php
 *   - salvarConexao.php
 *   - excluirNodo.php (se existir)
 *   - excluirConexao.php (se existir)
 * 
 * USO:
 *   Apos o INSERT/UPDATE/DELETE bem-sucedido, chamar:
 *   dispararSnapshotTopologia($pdoSIMP, 'Nó adicionado: NomeDoNo');
 * 
 * O snapshot so grava nova versao se o hash mudou.
 * Execucao isolada (try-catch) — nunca impacta a resposta principal.
 * 
 * Caminho: html/bd/entidadeCascata/topologiaHelper.php
 * 
 * @author Bruno - CESAN
 * @version 1.0
 * @date 2026-02
 */

/**
 * Dispara geracao de snapshot da topologia apos alteracao no flowchart.
 * Execucao isolada — erros nao impactam o endpoint principal.
 * 
 * @param PDO         $pdo        Conexao PDO com o banco SIMP
 * @param string|null $descricao  Descricao da mudanca (ex: 'No adicionado: ETA Norte')
 * @return array|null Resultado do snapshot ou null se falhou silenciosamente
 */
function dispararSnapshotTopologia($pdo, $descricao = null)
{
    try {
        // Obter usuario da sessao
        $cdUsuario = null;
        if (session_status() !== PHP_SESSION_NONE && isset($_SESSION['cd_usuario'])) {
            $cdUsuario = intval($_SESSION['cd_usuario']);
        }

        // Incluir endpoint de governanca (apenas funcoes, sem executar roteamento)
        // Usamos chamada direta a SP para evitar include circular
        $resultado = _executarSnapshotDireto($pdo, $cdUsuario, $descricao);

        return $resultado;

    } catch (Exception $e) {
        // Silencioso — snapshot e funcionalidade auxiliar, nao deve impactar operacao principal
        error_log("SIMP TOPOLOGIA: Erro ao disparar snapshot - " . $e->getMessage());
        return null;
    }
}

/**
 * Executa snapshot diretamente via SP (sem include do endpoint).
 * Mais leve e sem risco de efeitos colaterais.
 * 
 * @param PDO         $pdo        Conexao PDO
 * @param int|null    $cdUsuario  Usuario
 * @param string|null $descricao  Descricao
 * @return array Resultado
 */
function _executarSnapshotDireto($pdo, $cdUsuario = null, $descricao = null)
{
    try {
        // Verificar se a SP existe
        $sqlCheck = "SELECT 1 FROM sys.procedures WHERE name = 'SP_GERAR_HASH_TOPOLOGIA'";
        $stmtCheck = $pdo->query($sqlCheck);

        if ($stmtCheck->fetch()) {
            // Executar via SP (mais performatico)
            $sql = "
                DECLARE @NOVA BIT;
                EXEC dbo.SP_GERAR_HASH_TOPOLOGIA 
                    @CD_SISTEMA = NULL, 
                    @CD_USUARIO = :cdUsuario, 
                    @DS_DESCRICAO = :descricao,
                    @NOVA_VERSAO = @NOVA OUTPUT;
                SELECT @NOVA AS NOVA_VERSAO;
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':cdUsuario' => $cdUsuario,
                ':descricao' => $descricao ?? 'Snapshot automatico'
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'success'     => true,
                'nova_versao' => ($result && intval($result['NOVA_VERSAO']) === 1)
            ];
        } else {
            // SP nao existe ainda — executar via PHP (fallback)
            // Calcular hash no PHP (mesmo algoritmo da SP)
            return _snapshotViaPHP($pdo, $cdUsuario, $descricao);
        }

    } catch (Exception $e) {
        error_log("SIMP TOPOLOGIA: Erro snapshot direto - " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Fallback: calcula snapshot via PHP quando a SP ainda nao existe.
 * Mesmo algoritmo da SP_GERAR_HASH_TOPOLOGIA.
 * 
 * @param PDO         $pdo
 * @param int|null    $cdUsuario
 * @param string|null $descricao
 * @return array
 */
function _snapshotViaPHP($pdo, $cdUsuario = null, $descricao = null)
{
    try {
        // Verificar se a tabela VERSAO_TOPOLOGIA existe
        $sqlCheck = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'VERSAO_TOPOLOGIA'";
        if (!$pdo->query($sqlCheck)->fetch()) {
            // Tabela nao existe — fase A1 ainda nao implantada, ignorar silenciosamente
            return ['success' => true, 'nova_versao' => false, 'message' => 'Tabela VERSAO_TOPOLOGIA nao encontrada'];
        }

        // Buscar nos e conexoes
        $nos = $pdo->query("
            SELECT CD_CHAVE, CD_ENTIDADE_NIVEL, CD_PONTO_MEDICAO, OP_ATIVO
            FROM SIMP.dbo.ENTIDADE_NODO WHERE OP_ATIVO = 1 ORDER BY CD_CHAVE
        ")->fetchAll(PDO::FETCH_ASSOC);

        $conexoes = $pdo->query("
            SELECT CD_NODO_ORIGEM, CD_NODO_DESTINO, OP_ATIVO
            FROM SIMP.dbo.ENTIDADE_NODO_CONEXAO WHERE OP_ATIVO = 1 ORDER BY CD_CHAVE
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Montar string canonica
        $topoString = '';
        foreach ($nos as $n) {
            $ponto = $n['CD_PONTO_MEDICAO'] ?? 'NULL';
            $topoString .= "N:{$n['CD_CHAVE']}|{$n['CD_ENTIDADE_NIVEL']}|{$ponto}|{$n['OP_ATIVO']};";
        }
        foreach ($conexoes as $c) {
            $topoString .= "C:{$c['CD_NODO_ORIGEM']}|{$c['CD_NODO_DESTINO']}|{$c['OP_ATIVO']};";
        }

        // Hash SHA-256
        $hash = strtoupper(hash('sha256', $topoString));

        // Buscar ultimo hash
        $stmtUltimo = $pdo->query("
            SELECT TOP 1 DS_HASH_TOPOLOGIA 
            FROM SIMP.dbo.VERSAO_TOPOLOGIA 
            ORDER BY DT_CADASTRO DESC
        ");
        $ultimoHash = $stmtUltimo->fetch(PDO::FETCH_ASSOC);
        $ultimoHash = $ultimoHash ? $ultimoHash['DS_HASH_TOPOLOGIA'] : null;

        if ($ultimoHash === null || $hash !== $ultimoHash) {
            // Gravar nova versao
            $qtdNos      = count($nos);
            $qtdConexoes = count($conexoes);
            $qtdComPonto = count(array_filter($nos, fn($n) => !empty($n['CD_PONTO_MEDICAO'])));

            $stmt = $pdo->prepare("
                INSERT INTO SIMP.dbo.VERSAO_TOPOLOGIA 
                    (DS_HASH_TOPOLOGIA, QTD_NOS_ATIVOS, QTD_CONEXOES_ATIVAS, QTD_NOS_COM_PONTO, DS_DESCRICAO, CD_USUARIO, DT_CADASTRO)
                VALUES 
                    (:hash, :nos, :cx, :pontos, :desc, :user, GETDATE())
            ");
            $stmt->execute([
                ':hash'   => $hash,
                ':nos'    => $qtdNos,
                ':cx'     => $qtdConexoes,
                ':pontos' => $qtdComPonto,
                ':desc'   => $descricao ?? 'Snapshot automatico (via PHP)',
                ':user'   => $cdUsuario
            ]);

            return ['success' => true, 'nova_versao' => true, 'hash' => $hash];
        }

        return ['success' => true, 'nova_versao' => false, 'hash' => $hash];

    } catch (Exception $e) {
        error_log("SIMP TOPOLOGIA: Erro snapshot PHP - " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
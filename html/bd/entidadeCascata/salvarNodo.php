<?php
/**
 * SIMP - Salvar/Atualizar Nodo (Cascata Genérica)
 * 
 * POST params:
 *   - cd (int|null)          : CD_CHAVE do nodo (null = novo)
 *   - cdPai (int|null)       : CD_CHAVE do pai (null = raiz)
 *   - cdNivel (int)          : CD_ENTIDADE_NIVEL
 *   - nome (string)          : DS_NOME
 *   - identificador (string) : DS_IDENTIFICADOR (código externo)
 *   - ordem (int)            : NR_ORDEM
 *   - cdPonto (int|null)     : CD_PONTO_MEDICAO (nós-folha)
 *   - operacao (int|null)    : ID_OPERACAO (1=soma, 2=subtração)
 *   - fluxo (int|null)       : ID_FLUXO
 *   - dtInicio (string|null) : DT_INICIO
 *   - dtFim (string|null)    : DT_FIM
 *   - observacao (string)    : DS_OBSERVACAO
 * 
 * @author Bruno - CESAN
 * @version 1.0
 */

header('Content-Type: application/json; charset=utf-8');

// Verificar permissão de edição
require_once __DIR__ . '/../../includes/auth.php';
if (!podeEditarTela('Cadastro de Entidade')) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão para esta operação']);
    exit;
}

try {
    include_once '../conexao.php';

    if (!isset($pdoSIMP)) {
        throw new Exception('Conexão não estabelecida');
    }

    // --------------------------------------------------
    // Receber parâmetros
    // --------------------------------------------------
    $cd            = isset($_POST['cd']) && $_POST['cd'] !== '' ? (int)$_POST['cd'] : null;
    $cdPai         = isset($_POST['cdPai']) && $_POST['cdPai'] !== '' ? (int)$_POST['cdPai'] : null;
    $cdNivel       = isset($_POST['cdNivel']) ? (int)$_POST['cdNivel'] : 0;
    $nome          = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $identificador = isset($_POST['identificador']) ? trim($_POST['identificador']) : null;
    $ordem         = isset($_POST['ordem']) ? (int)$_POST['ordem'] : 0;
    $cdPonto       = isset($_POST['cdPonto']) && $_POST['cdPonto'] !== '' ? (int)$_POST['cdPonto'] : null;
    $cdSistemaAgua = isset($_POST['cdSistemaAgua']) && $_POST['cdSistemaAgua'] !== '' ? (int)$_POST['cdSistemaAgua'] : null;
    $operacao      = isset($_POST['operacao']) && $_POST['operacao'] !== '' ? (int)$_POST['operacao'] : null;
    $fluxo         = isset($_POST['fluxo']) && $_POST['fluxo'] !== '' ? (int)$_POST['fluxo'] : null;
    $dtInicio      = isset($_POST['dtInicio']) && $_POST['dtInicio'] !== '' ? $_POST['dtInicio'] : null;
    $dtFim         = isset($_POST['dtFim']) && $_POST['dtFim'] !== '' ? $_POST['dtFim'] : null;
    $observacao    = isset($_POST['observacao']) ? trim($_POST['observacao']) : null;
    $posX          = isset($_POST['posX']) && $_POST['posX'] !== '' ? (int)$_POST['posX'] : null;
    $posY          = isset($_POST['posY']) && $_POST['posY'] !== '' ? (int)$_POST['posY'] : null;

    // --------------------------------------------------
    // Validações
    // --------------------------------------------------
    if ($cdNivel <= 0) {
        throw new Exception('Nível é obrigatório');
    }
    if ($nome === '') {
        throw new Exception('Nome é obrigatório');
    }

    // Validar referência circular (nó não pode ser pai de si mesmo)
    if ($cd !== null && $cdPai === $cd) {
        throw new Exception('Um nó não pode ser pai de si mesmo');
    }

    // Validar que o pai não é descendente do nó (evitar loop)
    if ($cd !== null && $cdPai !== null) {
        $sqlCheck = "
            WITH CTE AS (
                SELECT CD_CHAVE, CD_PAI FROM SIMP.dbo.ENTIDADE_NODO WHERE CD_CHAVE = :cdPai
                UNION ALL
                SELECT N.CD_CHAVE, N.CD_PAI FROM SIMP.dbo.ENTIDADE_NODO N
                INNER JOIN CTE C ON C.CD_PAI = N.CD_CHAVE
            )
            SELECT COUNT(*) AS QTD FROM CTE WHERE CD_CHAVE = :cd
        ";
        // Não precisa verificar - a FK cuida. Mas o loop precisa de CTE reversa.
        // Simplificando: verificar se cdPai é descendente de cd
        $sqlDescendentes = "
            WITH CTE AS (
                SELECT CD_CHAVE FROM SIMP.dbo.ENTIDADE_NODO WHERE CD_PAI = :cd
                UNION ALL
                SELECT N.CD_CHAVE FROM SIMP.dbo.ENTIDADE_NODO N
                INNER JOIN CTE C ON N.CD_PAI = C.CD_CHAVE
            )
            SELECT COUNT(*) AS QTD FROM CTE WHERE CD_CHAVE = :cdPai
        ";
        $stmtCheck = $pdoSIMP->prepare($sqlDescendentes);
        $stmtCheck->execute([':cd' => $cd, ':cdPai' => $cdPai]);
        $resultCheck = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if ($resultCheck && (int)$resultCheck['QTD'] > 0) {
            throw new Exception('Referência circular detectada: o pai selecionado é descendente deste nó');
        }
    }

    // --------------------------------------------------
    // Se ordem não informada, calcular próxima
    // --------------------------------------------------
    if ($ordem <= 0 && $cd === null) {
        $sqlOrdem = "SELECT ISNULL(MAX(NR_ORDEM), 0) + 1 AS PROX 
                     FROM SIMP.dbo.ENTIDADE_NODO 
                     WHERE " . ($cdPai !== null ? "CD_PAI = :pai" : "CD_PAI IS NULL");
        $stmtOrdem = $pdoSIMP->prepare($sqlOrdem);
        if ($cdPai !== null) {
            $stmtOrdem->execute([':pai' => $cdPai]);
        } else {
            $stmtOrdem->execute();
        }
        $ordem = (int)$stmtOrdem->fetch(PDO::FETCH_ASSOC)['PROX'];
    }

    // --------------------------------------------------
    // INSERT ou UPDATE
    // --------------------------------------------------
    if ($cd !== null) {
        // UPDATE
        $sql = "UPDATE SIMP.dbo.ENTIDADE_NODO SET
                    CD_PAI            = :cdPai,
                    CD_ENTIDADE_NIVEL = :cdNivel,
                    DS_NOME           = :nome,
                    DS_IDENTIFICADOR  = :identificador,
                    NR_ORDEM          = :ordem,
                    CD_PONTO_MEDICAO  = :cdPonto,
                    CD_SISTEMA_AGUA   = :cdSistemaAgua,
                    ID_OPERACAO       = :operacao,
                    ID_FLUXO          = :fluxo,
                    DT_INICIO         = :dtInicio,
                    DT_FIM            = :dtFim,
                    DS_OBSERVACAO     = :observacao,
                    NR_POS_X          = :posX,
                    NR_POS_Y          = :posY,
                    DT_ATUALIZACAO    = GETDATE()
                WHERE CD_CHAVE = :cd";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':cdPai'         => $cdPai,
            ':cdNivel'       => $cdNivel,
            ':nome'          => $nome,
            ':identificador' => $identificador,
            ':ordem'         => $ordem,
            ':cdPonto'       => $cdPonto,
            ':cdSistemaAgua' => $cdSistemaAgua,
            ':operacao'      => $operacao,
            ':fluxo'         => $fluxo,
            ':dtInicio'      => $dtInicio,
            ':dtFim'         => $dtFim,
            ':observacao'    => $observacao,
            ':posX'          => $posX,
            ':posY'          => $posY,
            ':cd'            => $cd
        ]);

        // Log isolado
        try {
            @include_once '../logHelper.php';
            if (function_exists('registrarLogUpdate')) {
                registrarLogUpdate('Cadastro Cascata', 'Nodo', $cd, $nome, $_POST);
            }
        } catch (Exception $logEx) {}

        echo json_encode([
            'success' => true,
            'message' => 'Nó atualizado com sucesso!',
            'cd'      => $cd
        ], JSON_UNESCAPED_UNICODE);

    } else {
        // INSERT
        $sql = "INSERT INTO SIMP.dbo.ENTIDADE_NODO 
                (CD_PAI, CD_ENTIDADE_NIVEL, DS_NOME, DS_IDENTIFICADOR, NR_ORDEM, 
                 CD_PONTO_MEDICAO, CD_SISTEMA_AGUA, ID_OPERACAO, ID_FLUXO, DT_INICIO, DT_FIM, DS_OBSERVACAO,
                 NR_POS_X, NR_POS_Y)
                VALUES 
                (:cdPai, :cdNivel, :nome, :identificador, :ordem,
                 :cdPonto, :cdSistemaAgua, :operacao, :fluxo, :dtInicio, :dtFim, :observacao,
                 :posX, :posY)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':cdPai'         => $cdPai,
            ':cdNivel'       => $cdNivel,
            ':nome'          => $nome,
            ':identificador' => $identificador,
            ':ordem'         => $ordem,
            ':cdPonto'       => $cdPonto,
            ':cdSistemaAgua' => $cdSistemaAgua,
            ':operacao'      => $operacao,
            ':fluxo'         => $fluxo,
            ':dtInicio'      => $dtInicio,
            ':dtFim'         => $dtFim,
            ':observacao'    => $observacao,
            ':posX'          => $posX,
            ':posY'          => $posY
        ]);

        // Recuperar ID gerado
        $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID");
        $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['ID'];

        // Log isolado
        try {
            @include_once '../logHelper.php';
            if (function_exists('registrarLogInsert')) {
                registrarLogInsert('Cadastro Cascata', 'Nodo', $novoId, $nome, $_POST);
            }
        } catch (Exception $logEx) {}

        echo json_encode([
            'success' => true,
            'message' => 'Nó cadastrado com sucesso!',
            'cd'      => (int)$novoId
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    // Log de erro isolado
    try {
        @include_once '../logHelper.php';
        if (function_exists('registrarLogErro')) {
            registrarLogErro('Cadastro Cascata', $cd !== null ? 'UPDATE' : 'INSERT', $e->getMessage(), $_POST);
        }
    } catch (Exception $logEx) {}

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
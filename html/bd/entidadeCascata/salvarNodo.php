<?php
/**
 * SIMP - Salvar/Atualizar Nodo (Cascata Genérica)
 * 
 * POST params:
 *   - cd (int|null)          : CD_CHAVE do nodo (null = novo)
 *   - cdNivel (int)          : CD_ENTIDADE_NIVEL
 *   - nome (string)          : DS_NOME
 *   - identificador (string) : DS_IDENTIFICADOR (código externo)
 *   - ordem (int)            : NR_ORDEM
 *   - cdPonto (int|null)     : CD_PONTO_MEDICAO (quando OP_PERMITE_PONTO=1)
 *   - cdSistemaAgua (int|null): CD_SISTEMA_AGUA (quando OP_EH_SISTEMA=1)
 *   - operacao (int|null)    : ID_OPERACAO (1=soma, 2=subtração)
 *   - fluxo (int|null)       : ID_FLUXO (1=Entrada, 2=Saída, etc.)
 *   - observacao (string)    : DS_OBSERVACAO
 *   - posX (int|null)        : NR_POS_X (posição no canvas)
 *   - posY (int|null)        : NR_POS_Y (posição no canvas)
 * 
 * @author  Bruno - CESAN
 * @version 3.0
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
    $cdNivel       = isset($_POST['cdNivel']) ? (int)$_POST['cdNivel'] : 0;
    $nome          = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $identificador = isset($_POST['identificador']) ? trim($_POST['identificador']) : null;
    $ordem         = isset($_POST['ordem']) ? (int)$_POST['ordem'] : 0;
    $cdPonto       = isset($_POST['cdPonto']) && $_POST['cdPonto'] !== '' ? (int)$_POST['cdPonto'] : null;
    $cdSistemaAgua = isset($_POST['cdSistemaAgua']) && $_POST['cdSistemaAgua'] !== '' ? (int)$_POST['cdSistemaAgua'] : null;
    $operacao      = isset($_POST['operacao']) && $_POST['operacao'] !== '' ? (int)$_POST['operacao'] : null;
    $fluxo         = isset($_POST['fluxo']) && $_POST['fluxo'] !== '' ? (int)$_POST['fluxo'] : null;
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

    // --------------------------------------------------
    // Se ordem não informada, calcular próxima
    // --------------------------------------------------
    if ($ordem <= 0 && $cd === null) {
        $sqlOrdem = "SELECT ISNULL(MAX(NR_ORDEM), 0) + 1 AS PROX 
                     FROM SIMP.dbo.ENTIDADE_NODO 
                     WHERE CD_ENTIDADE_NIVEL = :cdNivel";
        $stmtOrdem = $pdoSIMP->prepare($sqlOrdem);
        $stmtOrdem->execute([':cdNivel' => $cdNivel]);
        $ordem = (int)$stmtOrdem->fetch(PDO::FETCH_ASSOC)['PROX'];
    }

    // --------------------------------------------------
    // INSERT ou UPDATE
    // --------------------------------------------------
    if ($cd !== null) {
        // UPDATE
        $sql = "UPDATE SIMP.dbo.ENTIDADE_NODO SET
                    CD_ENTIDADE_NIVEL = :cdNivel,
                    DS_NOME           = :nome,
                    DS_IDENTIFICADOR  = :identificador,
                    NR_ORDEM          = :ordem,
                    CD_PONTO_MEDICAO  = :cdPonto,
                    CD_SISTEMA_AGUA   = :cdSistemaAgua,
                    ID_OPERACAO       = :operacao,
                    ID_FLUXO          = :fluxo,
                    DS_OBSERVACAO     = :observacao,
                    NR_POS_X          = :posX,
                    NR_POS_Y          = :posY,
                    DT_ATUALIZACAO    = GETDATE()
                WHERE CD_CHAVE = :cd";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':cdNivel'       => $cdNivel,
            ':nome'          => $nome,
            ':identificador' => $identificador,
            ':ordem'         => $ordem,
            ':cdPonto'       => $cdPonto,
            ':cdSistemaAgua' => $cdSistemaAgua,
            ':operacao'      => $operacao,
            ':fluxo'         => $fluxo,
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
                (CD_ENTIDADE_NIVEL, DS_NOME, DS_IDENTIFICADOR, NR_ORDEM, 
                 CD_PONTO_MEDICAO, CD_SISTEMA_AGUA, ID_OPERACAO, ID_FLUXO, DS_OBSERVACAO,
                 NR_POS_X, NR_POS_Y)
                VALUES 
                (:cdNivel, :nome, :identificador, :ordem,
                 :cdPonto, :cdSistemaAgua, :operacao, :fluxo, :observacao,
                 :posX, :posY)";
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute([
            ':cdNivel'       => $cdNivel,
            ':nome'          => $nome,
            ':identificador' => $identificador,
            ':ordem'         => $ordem,
            ':cdPonto'       => $cdPonto,
            ':cdSistemaAgua' => $cdSistemaAgua,
            ':operacao'      => $operacao,
            ':fluxo'         => $fluxo,
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
<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Salvar Dados Específicos do Medidor
 */

header('Content-Type: application/json; charset=utf-8');

// DEBUG: Log para arquivo
$debugLog = __DIR__ . '/debug_equipamento.log';
$debugData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'POST' => $_POST
];
file_put_contents($debugLog, print_r($debugData, true) . "\n" . str_repeat('-', 50) . "\n", FILE_APPEND);

// Capturar erros
$errors = [];
set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$errors) {
    $errors[] = "[$errno] $errstr em $errfile:$errline";
    return true;
});

ini_set('display_errors', 0);
error_reporting(E_ALL);

// Verificação de autenticação e permissão
require_once '../verificarAuth.php';
verificarPermissaoAjax('CADASTRO DE PONTO', ACESSO_ESCRITA);

include_once '../conexao.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    $cdPontoMedicao = isset($_POST['cd_ponto_medicao']) && $_POST['cd_ponto_medicao'] !== '' ? (int) $_POST['cd_ponto_medicao'] : null;
    $idTipoMedidor = isset($_POST['id_tipo_medidor']) && $_POST['id_tipo_medidor'] !== '' ? (int) $_POST['id_tipo_medidor'] : null;
    $cdChave = isset($_POST['cd_chave']) && $_POST['cd_chave'] !== '' ? (int) $_POST['cd_chave'] : null;

    // DEBUG
    file_put_contents($debugLog, "cdPontoMedicao: $cdPontoMedicao, idTipoMedidor: $idTipoMedidor, cdChave: $cdChave\n", FILE_APPEND);

    if (empty($cdPontoMedicao)) {
        throw new Exception('Ponto de medição não informado');
    }

    if (empty($idTipoMedidor)) {
        throw new Exception('Tipo de medidor não informado');
    }

    // Constante para indicar que campo foi enviado vazio (diferente de não enviado)
    if (!defined('CAMPO_VAZIO')) {
        define('CAMPO_VAZIO', '__CAMPO_VAZIO__');
    }

    // Função auxiliar para obter valor do POST
    // Retorna CAMPO_VAZIO se enviado vazio, null se não enviado, ou o valor
    function getPost($key, $default = null)
    {
        if (!isset($_POST[$key])) {
            return $default; // Campo não enviado
        }
        $valor = is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key];
        if ($valor === '') {
            return CAMPO_VAZIO; // Campo enviado vazio
        }
        return $valor; // Campo com valor (já sem espaços)
    }

    // Função para formatar data
    function formatDate($date)
    {
        if (empty($date) || $date === CAMPO_VAZIO)
            return null;
        return date('Y-m-d', strtotime($date));
    }

    // Determina tabela e monta dados baseado no tipo
    $tabela = '';
    $campos = [];
    $defaultsNotNull = []; // Defaults para campos NOT NULL (usados apenas no INSERT)

    switch ($idTipoMedidor) {
        case 1: // Macromedidor
            $tabela = 'MACROMEDIDOR';
            $defaultsNotNull = ['CD_TIPO_MEDIDOR' => 1, 'VL_DIAMETRO' => 0, 'ID_PRODUTO' => 1];
            $campos = [
                'CD_PONTO_MEDICAO' => $cdPontoMedicao,
                'CD_TIPO_MEDIDOR' => getPost('cd_tipo_medidor_equip'),
                'DS_MARCA' => getPost('ds_marca'),
                'DS_MODELO' => getPost('ds_modelo'),
                'DS_SERIE' => getPost('ds_serie'),
                'DS_TAG' => getPost('ds_tag'),
                'DT_FABRICACAO' => formatDate(getPost('dt_fabricacao')),
                'DS_PATRIMONIO_PRIMARIO' => getPost('ds_patrimonio_primario'),
                'DS_PATRIMONIO_SECUNDARIO' => getPost('ds_patrimonio_secundario'),
                'VL_DIAMETRO' => getPost('vl_diametro'),
                'DS_REVESTIMENTO' => getPost('ds_revestimento'),
                'VL_PERDA_CARGA_FABRICANTE' => getPost('vl_perda_carga_fabricante'),
                'VL_CAPACIDADE_NOMINAL' => getPost('vl_capacidade_nominal'),
                'VL_K_FABRICANTE' => getPost('vl_k_fabricante'),
                'VL_VAZAO_ESPERADA' => getPost('vl_vazao_esperada'),
                'VL_PRESSAO_MAXIMA' => getPost('vl_pressao_maxima'),
                'DS_TIPO_FLANGE' => getPost('ds_tipo_flange'),
                'DS_ALTURA_SOLEIRA' => getPost('ds_altura_soleira'),
                'DS_NATUREZA_PAREDE' => getPost('ds_natureza_parede'),
                'DS_LARGURA_RELATIVA' => getPost('ds_largura_relativa'),
                'DS_LARGURA_GARGANTA' => getPost('ds_largura_garganta'),
                'VL_DIAMETRO_REDE' => getPost('vl_diametro_rede'),
                'VL_COTA' => getPost('vl_cota'),
                'PROT_COMUN' => getPost('prot_comun'),
                'ID_PRODUTO' => getPost('id_produto'),
                'CD_ESTACAO_PITOMETRICA' => getPost('cd_estacao_pitometrica')
            ];
            break;

        case 2: // Estação Pitométrica
            $tabela = 'ESTACAO_PITOMETRICA';
            $defaultsNotNull = ['VL_COTA_GEOGRAFICA' => 0, 'VL_DIAMETRO' => 0, 'TP_PERIODICIDADE_LEVANTAMENTO' => 1];
            $campos = [
                'CD_PONTO_MEDICAO' => $cdPontoMedicao,
                'VL_COTA_GEOGRAFICA' => getPost('vl_cota_geografica'),
                'VL_DIAMETRO' => getPost('vl_diametro'),
                'DS_LINHA' => getPost('ds_linha'),
                'DS_SISTEMA' => getPost('ds_sistema'),
                'DS_REVESTIMENTO' => getPost('ds_revestimento'),
                'TP_PERIODICIDADE_LEVANTAMENTO' => getPost('tp_periodicidade_levantamento'),
                'VL_DIAMETRO_REDE' => getPost('vl_diametro_rede')
            ];
            break;

        case 4: // Medidor Pressão
            $tabela = 'MEDIDOR_PRESSAO';
            $defaultsNotNull = []; // Nenhum campo NOT NULL além de CD_PONTO_MEDICAO
            $campos = [
                'CD_PONTO_MEDICAO' => $cdPontoMedicao,
                'DS_MATRICULA_USUARIO' => getPost('ds_matricula_usuario'),
                'DS_NUMERO_SERIE_EQUIPAMENTO' => getPost('ds_numero_serie_equipamento'),
                'VL_DIAMETRO' => getPost('vl_diametro'),
                'VL_DIAMETRO_REDE' => getPost('vl_diametro_rede'),
                'DS_MATERIAL' => getPost('ds_material'),
                'DS_COTA' => getPost('ds_cota'),
                'OP_TELEMETRIA' => getPost('op_telemetria'),
                'DS_ENDERECO' => getPost('ds_endereco'),
                'DT_INSTALACAO' => formatDate(getPost('dt_instalacao')),
                'DS_COORDENADAS' => getPost('ds_coordenadas')
            ];
            break;

        case 6: // Nível Reservatório
            $tabela = 'NIVEL_RESERVATORIO';
            $defaultsNotNull = [
                'CD_TIPO_MEDIDOR' => 1,
                'DT_INSTALACAO' => date('Y-m-d'),
                'ID_PRODUTO' => 1,
                'CD_TIPO_RESERVATORIO' => 1
            ];
            $campos = [
                'CD_PONTO_MEDICAO' => $cdPontoMedicao,
                'CD_TIPO_MEDIDOR' => getPost('cd_tipo_medidor_equip'),
                'DS_MARCA' => getPost('ds_marca'),
                'DS_MODELO' => getPost('ds_modelo'),
                'DS_SERIE' => getPost('ds_serie'),
                'DS_TAG' => getPost('ds_tag'),
                'DT_FABRICACAO' => formatDate(getPost('dt_fabricacao')),
                'DT_INSTALACAO' => formatDate(getPost('dt_instalacao')),
                'DS_PATRIMONIO_PRIMARIO' => getPost('ds_patrimonio_primario'),
                'DS_PATRIMONIO_SECUNDARIO' => getPost('ds_patrimonio_secundario'),
                'COTA_EXTRAVASAMENTO_M' => getPost('cota_extravasamento_m'),
                'COTA_EXTRAVASAMENTO_P' => getPost('cota_extravasamento_p'),
                'VL_PRESSAO_MAXIMA_SUCCAO' => getPost('vl_pressao_maxima_succao'),
                'VL_PRESSAO_MAXIMA_RECALQUE' => getPost('vl_pressao_maxima_recalque'),
                'VL_VOLUME_TOTAL' => getPost('vl_volume_total'),
                'VL_VOLUME_CAMARA_A' => getPost('vl_volume_camara_a'),
                'VL_VOLUME_CAMARA_B' => getPost('vl_volume_camara_b'),
                'ID_PRODUTO' => getPost('id_produto'),
                'CD_TIPO_RESERVATORIO' => getPost('cd_tipo_reservatorio'),
                'DS_ALTURA_MAXIMA' => getPost('ds_altura_maxima'),
                'VL_NA' => getPost('vl_na'),
                'VL_COTA' => getPost('vl_cota')
            ];
            break;

        case 8: // Hidrômetro
            $tabela = 'HIDROMETRO';
            $defaultsNotNull = ['ID_TEMPO_OPERACAO' => 1, 'VL_LEITURA_LIMITE' => 0];
            $campos = [
                'CD_PONTO_MEDICAO' => $cdPontoMedicao,
                'DS_MATRICULA_USUARIO' => getPost('ds_matricula_usuario'),
                'DS_NUMERO_SERIE_EQUIPAMENTO' => getPost('ds_numero_serie_equipamento'),
                'VL_DIAMETRO' => getPost('vl_diametro'),
                'VL_DIAMETRO_REDE' => getPost('vl_diametro_rede'),
                'DS_MATERIAL' => getPost('ds_material'),
                'DS_COTA' => getPost('ds_cota'),
                'DS_ENDERECO' => getPost('ds_endereco'),
                'DT_INSTALACAO' => formatDate(getPost('dt_instalacao')),
                'DS_COORDENADAS' => getPost('ds_coordenadas'),
                'ID_TEMPO_OPERACAO' => getPost('id_tempo_operacao'),
                'VL_LEITURA_LIMITE' => getPost('vl_leitura_limite'),
                'VL_MULTIPLICADOR' => getPost('vl_multiplicador')
            ];
            break;

        default:
            throw new Exception('Tipo de medidor não suportado: ' . $idTipoMedidor);
    }

    // Verifica se já existe registro
    $sqlCheck = "SELECT CD_CHAVE FROM SIMP.dbo.{$tabela} WHERE CD_PONTO_MEDICAO = ?";
    $stmtCheck = $pdoSIMP->prepare($sqlCheck);
    $stmtCheck->execute([$cdPontoMedicao]);
    $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existe) {
        // UPDATE - Atualiza campos enviados (incluindo vazios para limpar)
        // null = campo não enviado (preservar valor existente)
        // CAMPO_VAZIO = campo enviado vazio (limpar no banco, setar NULL - exceto campos NOT NULL)
        // valor = campo com valor (atualizar)

        // Campos NOT NULL que não podem ser setados como NULL no UPDATE
        $camposNotNull = [
            'MACROMEDIDOR' => ['CD_TIPO_MEDIDOR', 'VL_DIAMETRO', 'ID_PRODUTO'],
            'ESTACAO_PITOMETRICA' => ['VL_COTA_GEOGRAFICA', 'VL_DIAMETRO', 'TP_PERIODICIDADE_LEVANTAMENTO'],
            'HIDROMETRO' => ['ID_TEMPO_OPERACAO', 'VL_LEITURA_LIMITE'],
            'NIVEL_RESERVATORIO' => ['CD_TIPO_MEDIDOR', 'DT_INSTALACAO', 'ID_PRODUTO', 'CD_TIPO_RESERVATORIO'],
            'MEDIDOR_PRESSAO' => []
        ];
        $notNullFields = $camposNotNull[$tabela] ?? [];

        $setClauses = [];
        $params = [];

        foreach ($campos as $campo => $valor) {
            if ($campo === 'CD_PONTO_MEDICAO')
                continue; // Nunca atualizar PK

            if ($valor === null) {
                // Campo não foi enviado - preservar valor existente
                continue;
            }

            if ($valor === CAMPO_VAZIO) {
                // Campo foi enviado vazio
                if (in_array($campo, $notNullFields)) {
                    // Campo NOT NULL - não pode setar NULL, ignorar
                    continue;
                }
                // Campo permite NULL - limpar no banco
                $setClauses[] = "{$campo} = NULL";
            } else {
                // Campo com valor - atualizar
                $setClauses[] = "{$campo} = ?";
                $params[] = $valor;
            }
        }

        if (empty($setClauses)) {
            echo json_encode([
                'success' => true,
                'message' => 'Nenhum campo para atualizar'
            ]);
            exit;
        }

        $sql = "UPDATE SIMP.dbo.{$tabela} SET " . implode(', ', $setClauses) . " WHERE CD_CHAVE = ?";
        $params[] = $existe['CD_CHAVE'];

        // DEBUG
        file_put_contents($debugLog, "SQL UPDATE: $sql\nParams: " . print_r($params, true) . "\n", FILE_APPEND);

        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute($params);

        file_put_contents($debugLog, "UPDATE executado! Rows: " . $stmt->rowCount() . "\n", FILE_APPEND);

        $msg = 'Dados do medidor atualizados com sucesso!';
    } else {
        // INSERT - Aplicar defaults para campos NOT NULL que não foram preenchidos
        foreach ($defaultsNotNull as $campo => $valorDefault) {
            if (!isset($campos[$campo]) || $campos[$campo] === null || $campos[$campo] === CAMPO_VAZIO) {
                $campos[$campo] = $valorDefault;
            }
        }

        // Filtra campos NULL e CAMPO_VAZIO
        $camposFiltrados = array_filter($campos, function ($valor) {
            return $valor !== null && $valor !== CAMPO_VAZIO;
        });

        $colunas = array_keys($camposFiltrados);
        $placeholders = array_fill(0, count($colunas), '?');
        $params = array_values($camposFiltrados);

        $sql = "INSERT INTO SIMP.dbo.{$tabela} (" . implode(', ', $colunas) . ") VALUES (" . implode(', ', $placeholders) . ")";

        // DEBUG
        file_put_contents($debugLog, "SQL INSERT: $sql\nParams: " . print_r($params, true) . "\n", FILE_APPEND);

        $stmt = $pdoSIMP->prepare($sql);
        $stmt->execute($params);

        // Pegar ID inserido
        $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID");
        $newId = $stmtId->fetch(PDO::FETCH_ASSOC)['ID'];

        file_put_contents($debugLog, "INSERT executado! ID: $newId\n", FILE_APPEND);

        $msg = 'Dados do medidor cadastrados com sucesso!';
    }

    $response = [
        'success' => true,
        'message' => $msg
    ];

    if (!empty($errors)) {
        $response['warnings'] = $errors;
    }

    file_put_contents($debugLog, "SUCESSO!\n" . str_repeat('=', 50) . "\n", FILE_APPEND);

    echo json_encode($response);

} catch (PDOException $e) {
    file_put_contents($debugLog, "PDO ERROR: " . $e->getMessage() . "\nCode: " . $e->getCode() . "\n", FILE_APPEND);

    // Traduzir erros comuns do SQL Server
    $mensagem = $e->getMessage();

    // FK violation
    if (strpos($mensagem, 'FOREIGN KEY constraint') !== false) {
        if (strpos($mensagem, 'TIPO_MEDIDOR') !== false) {
            $mensagem = 'Tipo de Medidor inválido ou não selecionado';
        } elseif (strpos($mensagem, 'PRODUTO') !== false) {
            $mensagem = 'Produto inválido ou não selecionado';
        } else {
            $mensagem = 'Valor inválido para um dos campos de referência';
        }
    }
    // NOT NULL violation
    elseif (strpos($mensagem, 'Cannot insert the value NULL') !== false) {
        preg_match("/column '(\w+)'/", $mensagem, $matches);
        $campo = $matches[1] ?? 'desconhecido';
        $mensagem = "Campo obrigatório não preenchido: $campo";
    }

    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar: ' . $mensagem,
        'error_code' => $e->getCode()
    ]);
} catch (Exception $e) {
    file_put_contents($debugLog, "EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
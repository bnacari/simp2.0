<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Salvar/Atualizar Conjunto Motor-Bomba
 * COM REGISTRO DE LOG
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../verificarAuth.php';
require_once '../logHelper.php';

include_once '../conexao.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    $cdChave = isset($_POST['cd_chave']) && $_POST['cd_chave'] !== '' ? (int)$_POST['cd_chave'] : null;
    $cdLocalidade = isset($_POST['cd_localidade']) ? (int)$_POST['cd_localidade'] : null;
    $dsCodigo = isset($_POST['ds_codigo']) ? trim($_POST['ds_codigo']) : '';
    $dsNome = isset($_POST['ds_nome']) ? trim($_POST['ds_nome']) : '';
    $dsLocalizacao = isset($_POST['ds_localizacao']) ? trim($_POST['ds_localizacao']) : '';
    $cdUsuarioResponsavel = isset($_POST['cd_usuario_responsavel']) ? (int)$_POST['cd_usuario_responsavel'] : null;
    $tpEixo = isset($_POST['tp_eixo']) ? trim($_POST['tp_eixo']) : '';
    $dsObservacao = isset($_POST['ds_observacao']) ? trim($_POST['ds_observacao']) : null;
    
    // Dados Bomba
    $dsFabricanteBomba = isset($_POST['ds_fabricante_bomba']) && $_POST['ds_fabricante_bomba'] !== '' ? trim($_POST['ds_fabricante_bomba']) : null;
    $dsTipoBomba = isset($_POST['ds_tipo_bomba']) && $_POST['ds_tipo_bomba'] !== '' ? trim($_POST['ds_tipo_bomba']) : null;
    $dsSerieBomba = isset($_POST['ds_serie_bomba']) && $_POST['ds_serie_bomba'] !== '' ? trim($_POST['ds_serie_bomba']) : null;
    $vlDiametroRotorBomba = isset($_POST['vl_diametro_rotor_bomba']) && $_POST['vl_diametro_rotor_bomba'] !== '' ? (float)$_POST['vl_diametro_rotor_bomba'] : null;
    $vlVazaoBomba = isset($_POST['vl_vazao_bomba']) && $_POST['vl_vazao_bomba'] !== '' ? (float)$_POST['vl_vazao_bomba'] : null;
    $vlAlturaManometricaBomba = isset($_POST['vl_altura_manometrica_bomba']) && $_POST['vl_altura_manometrica_bomba'] !== '' ? (float)$_POST['vl_altura_manometrica_bomba'] : null;
    $vlRotacaoBomba = isset($_POST['vl_rotacao_bomba']) && $_POST['vl_rotacao_bomba'] !== '' ? (float)$_POST['vl_rotacao_bomba'] : null;
    $vlAreaSuccaoBomba = isset($_POST['vl_area_succao_bomba']) && $_POST['vl_area_succao_bomba'] !== '' ? (float)$_POST['vl_area_succao_bomba'] : null;
    $vlAreaRecalqueBomba = isset($_POST['vl_area_recalque_bomba']) && $_POST['vl_area_recalque_bomba'] !== '' ? (float)$_POST['vl_area_recalque_bomba'] : null;
    
    // Dados Motor
    $dsFabricanteMotor = isset($_POST['ds_fabricante_motor']) && $_POST['ds_fabricante_motor'] !== '' ? trim($_POST['ds_fabricante_motor']) : null;
    $dsTipoMotor = isset($_POST['ds_tipo_motor']) && $_POST['ds_tipo_motor'] !== '' ? trim($_POST['ds_tipo_motor']) : null;
    $dsSerieMotor = isset($_POST['ds_serie_motor']) && $_POST['ds_serie_motor'] !== '' ? trim($_POST['ds_serie_motor']) : null;
    $vlTensaoMotor = isset($_POST['vl_tensao_motor']) && $_POST['vl_tensao_motor'] !== '' ? (float)$_POST['vl_tensao_motor'] : null;
    $vlCorrenteEletricaMotor = isset($_POST['vl_corrente_eletrica_motor']) && $_POST['vl_corrente_eletrica_motor'] !== '' ? (float)$_POST['vl_corrente_eletrica_motor'] : null;
    $vlPotenciaMotor = isset($_POST['vl_potencia_motor']) && $_POST['vl_potencia_motor'] !== '' ? (float)$_POST['vl_potencia_motor'] : null;
    $vlRotacaoMotor = isset($_POST['vl_rotacao_motor']) && $_POST['vl_rotacao_motor'] !== '' ? (float)$_POST['vl_rotacao_motor'] : null;

    // Validações
    if (empty($cdLocalidade)) {
        throw new Exception('Localidade é obrigatória');
    }
    if (empty($dsCodigo)) {
        throw new Exception('Código é obrigatório');
    }
    if (empty($dsNome)) {
        throw new Exception('Nome é obrigatório');
    }
    if (empty($tpEixo)) {
        throw new Exception('Tipo de Eixo é obrigatório');
    }

    // Obter usuário logado
    $cdUsuario = $_SESSION['cd_usuario'] ?? null;
    if (!$cdUsuario) {
        throw new Exception('Usuário não identificado');
    }

    // Buscar CD_UNIDADE da localidade para log
    $cdUnidadeLog = null;
    try {
        $stmtUnidade = $pdoSIMP->prepare("SELECT CD_UNIDADE FROM SIMP.dbo.LOCALIDADE WHERE CD_CHAVE = :cd");
        $stmtUnidade->execute([':cd' => $cdLocalidade]);
        $cdUnidadeLog = $stmtUnidade->fetchColumn();
    } catch (Exception $e) {}

    $isEdicao = $cdChave !== null && $cdChave > 0;

    if ($isEdicao) {
        // Buscar dados anteriores para log
        $dadosAnteriores = null;
        try {
            $stmtAnt = $pdoSIMP->prepare("SELECT * FROM SIMP.dbo.CONJUNTO_MOTOR_BOMBA WHERE CD_CHAVE = :id");
            $stmtAnt->execute([':id' => $cdChave]);
            $dadosAnteriores = $stmtAnt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // UPDATE
        $sql = "UPDATE SIMP.dbo.CONJUNTO_MOTOR_BOMBA SET 
                    CD_LOCALIDADE = :cd_localidade,
                    DS_CODIGO = :ds_codigo,
                    DS_NOME = :ds_nome,
                    DS_LOCALIZACAO = :ds_localizacao,
                    CD_USUARIO_RESPONSAVEL = :cd_usuario_responsavel,
                    TP_EIXO = :tp_eixo,
                    DS_OBSERVACAO = :ds_observacao,
                    DS_FABRICANTE_BOMBA = :ds_fabricante_bomba,
                    DS_TIPO_BOMBA = :ds_tipo_bomba,
                    DS_SERIE_BOMBA = :ds_serie_bomba,
                    VL_DIAMETRO_ROTOR_BOMBA = :vl_diametro_rotor_bomba,
                    VL_VAZAO_BOMBA = :vl_vazao_bomba,
                    VL_ALTURA_MANOMETRICA_BOMBA = :vl_altura_manometrica_bomba,
                    VL_ROTACAO_BOMBA = :vl_rotacao_bomba,
                    VL_AREA_SUCCAO_BOMBA = :vl_area_succao_bomba,
                    VL_AREA_RECALQUE_BOMBA = :vl_area_recalque_bomba,
                    DS_FABRICANTE_MOTOR = :ds_fabricante_motor,
                    DS_TIPO_MOTOR = :ds_tipo_motor,
                    DS_SERIE_MOTOR = :ds_serie_motor,
                    VL_TENSAO_MOTOR = :vl_tensao_motor,
                    VL_CORRENTE_ELETRICA_MOTOR = :vl_corrente_eletrica_motor,
                    VL_POTENCIA_MOTOR = :vl_potencia_motor,
                    VL_ROTACAO_MOTOR = :vl_rotacao_motor,
                    CD_USUARIO_ULTIMA_ATUALIZACAO = :cd_usuario,
                    DT_ULTIMA_ATUALIZACAO = GETDATE()
                WHERE CD_CHAVE = :cd_chave";
        
        $stmt = $pdoSIMP->prepare($sql);
        $stmt->bindValue(':cd_chave', $cdChave, PDO::PARAM_INT);
        $msg = 'Registro atualizado com sucesso!';

        // Log de UPDATE (isolado)
        try {
            $alteracoes = [
                'dados_novos' => [
                    'DS_CODIGO' => $dsCodigo,
                    'DS_NOME' => $dsNome,
                    'TP_EIXO' => $tpEixo,
                    'VL_POTENCIA_MOTOR' => $vlPotenciaMotor,
                    'VL_VAZAO_BOMBA' => $vlVazaoBomba
                ]
            ];
            if ($dadosAnteriores) {
                $alteracoes['dados_anteriores'] = [
                    'DS_CODIGO' => $dadosAnteriores['DS_CODIGO'] ?? null,
                    'DS_NOME' => $dadosAnteriores['DS_NOME'] ?? null,
                    'TP_EIXO' => $dadosAnteriores['TP_EIXO'] ?? null,
                    'VL_POTENCIA_MOTOR' => $dadosAnteriores['VL_POTENCIA_MOTOR'] ?? null,
                    'VL_VAZAO_BOMBA' => $dadosAnteriores['VL_VAZAO_BOMBA'] ?? null
                ];
            }
            registrarLogUpdate('Conjunto Motor-Bomba', 'Motor-Bomba', $cdChave, "$dsCodigo - $dsNome", $alteracoes, $cdUnidadeLog);
        } catch (Exception $logEx) {
            error_log('Erro ao registrar log de UPDATE Motor-Bomba: ' . $logEx->getMessage());
        }

    } else {
        // INSERT
        $sql = "INSERT INTO SIMP.dbo.CONJUNTO_MOTOR_BOMBA (
                    CD_LOCALIDADE, DS_CODIGO, DS_NOME, DS_LOCALIZACAO, CD_USUARIO_RESPONSAVEL,
                    TP_EIXO, DS_OBSERVACAO, DS_FABRICANTE_BOMBA, DS_TIPO_BOMBA, DS_SERIE_BOMBA,
                    VL_DIAMETRO_ROTOR_BOMBA, VL_VAZAO_BOMBA, VL_ALTURA_MANOMETRICA_BOMBA,
                    VL_ROTACAO_BOMBA, VL_AREA_SUCCAO_BOMBA, VL_AREA_RECALQUE_BOMBA,
                    DS_FABRICANTE_MOTOR, DS_TIPO_MOTOR, DS_SERIE_MOTOR, VL_TENSAO_MOTOR,
                    VL_CORRENTE_ELETRICA_MOTOR, VL_POTENCIA_MOTOR, VL_ROTACAO_MOTOR,
                    CD_USUARIO_ULTIMA_ATUALIZACAO, DT_ULTIMA_ATUALIZACAO
                ) VALUES (
                    :cd_localidade, :ds_codigo, :ds_nome, :ds_localizacao, :cd_usuario_responsavel,
                    :tp_eixo, :ds_observacao, :ds_fabricante_bomba, :ds_tipo_bomba, :ds_serie_bomba,
                    :vl_diametro_rotor_bomba, :vl_vazao_bomba, :vl_altura_manometrica_bomba,
                    :vl_rotacao_bomba, :vl_area_succao_bomba, :vl_area_recalque_bomba,
                    :ds_fabricante_motor, :ds_tipo_motor, :ds_serie_motor, :vl_tensao_motor,
                    :vl_corrente_eletrica_motor, :vl_potencia_motor, :vl_rotacao_motor,
                    :cd_usuario, GETDATE()
                )";
        
        $stmt = $pdoSIMP->prepare($sql);
        $msg = 'Registro cadastrado com sucesso!';
    }

    $stmt->bindValue(':cd_localidade', $cdLocalidade, PDO::PARAM_INT);
    $stmt->bindValue(':ds_codigo', $dsCodigo);
    $stmt->bindValue(':ds_nome', $dsNome);
    $stmt->bindValue(':ds_localizacao', $dsLocalizacao);
    $stmt->bindValue(':cd_usuario_responsavel', $cdUsuarioResponsavel, PDO::PARAM_INT);
    $stmt->bindValue(':tp_eixo', $tpEixo);
    $stmt->bindValue(':ds_observacao', $dsObservacao);
    $stmt->bindValue(':ds_fabricante_bomba', $dsFabricanteBomba);
    $stmt->bindValue(':ds_tipo_bomba', $dsTipoBomba);
    $stmt->bindValue(':ds_serie_bomba', $dsSerieBomba);
    $stmt->bindValue(':vl_diametro_rotor_bomba', $vlDiametroRotorBomba);
    $stmt->bindValue(':vl_vazao_bomba', $vlVazaoBomba);
    $stmt->bindValue(':vl_altura_manometrica_bomba', $vlAlturaManometricaBomba);
    $stmt->bindValue(':vl_rotacao_bomba', $vlRotacaoBomba);
    $stmt->bindValue(':vl_area_succao_bomba', $vlAreaSuccaoBomba);
    $stmt->bindValue(':vl_area_recalque_bomba', $vlAreaRecalqueBomba);
    $stmt->bindValue(':ds_fabricante_motor', $dsFabricanteMotor);
    $stmt->bindValue(':ds_tipo_motor', $dsTipoMotor);
    $stmt->bindValue(':ds_serie_motor', $dsSerieMotor);
    $stmt->bindValue(':vl_tensao_motor', $vlTensaoMotor);
    $stmt->bindValue(':vl_corrente_eletrica_motor', $vlCorrenteEletricaMotor);
    $stmt->bindValue(':vl_potencia_motor', $vlPotenciaMotor);
    $stmt->bindValue(':vl_rotacao_motor', $vlRotacaoMotor);
    $stmt->bindValue(':cd_usuario', $cdUsuario, PDO::PARAM_INT);
    
    $stmt->execute();

    // Se foi INSERT, pegar o ID e registrar log
    if (!$isEdicao) {
        try {
            $stmtId = $pdoSIMP->query("SELECT SCOPE_IDENTITY() AS ID");
            $novoId = $stmtId->fetch(PDO::FETCH_ASSOC)['ID'];
            
            $dadosInseridos = [
                'DS_CODIGO' => $dsCodigo,
                'DS_NOME' => $dsNome,
                'TP_EIXO' => $tpEixo,
                'VL_POTENCIA_MOTOR' => $vlPotenciaMotor,
                'VL_VAZAO_BOMBA' => $vlVazaoBomba,
                'DS_LOCALIZACAO' => $dsLocalizacao
            ];
            registrarLogInsert('Conjunto Motor-Bomba', 'Motor-Bomba', $novoId, "$dsCodigo - $dsNome", $dadosInseridos, $cdUnidadeLog);
        } catch (Exception $logEx) {
            error_log('Erro ao registrar log de INSERT Motor-Bomba: ' . $logEx->getMessage());
        }
    }

    echo json_encode(['success' => true, 'message' => $msg]);

} catch (PDOException $e) {
    // Registrar log de erro
    try {
        registrarLogErro('Conjunto Motor-Bomba', $isEdicao ? 'UPDATE' : 'INSERT', $e->getMessage(), ['cd_chave' => $cdChave ?? null, 'ds_nome' => $dsNome ?? null]);
    } catch (Exception $logEx) {}

    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);

} catch (Exception $e) {
    // Registrar log de erro
    try {
        registrarLogErro('Conjunto Motor-Bomba', $isEdicao ?? false ? 'UPDATE' : 'INSERT', $e->getMessage(), ['cd_chave' => $cdChave ?? null]);
    } catch (Exception $logEx) {}

    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
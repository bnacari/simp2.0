<?php
/**
 * getDadosHistoriador.php
 * 
 * Busca dados de telemetria do Historiador CCO para um ponto de medição.
 * Retorna médias da HORA ANTERIOR e HORA ATUAL para o gráfico de validação.
 * 
 * CORREÇÕES:
 *   - Query SIMPLES sem wwRetrievalMode/Cyclic (retorna null neste ambiente)
 *   - Sem JOIN com tabela Tag (desnecessário, só History basta)
 *   - Query direta com quote() (linked server não funciona com prepared statements)
 *   - Filtra registros com vValue null (sensor offline)
 *   - Agrupa por hora e calcula AVG no PHP
 *   - Compatível com PHP 8.3+
 * 
 * Parâmetros:
 *   cdPonto - Código do ponto de medição
 *   data    - Data no formato YYYY-MM-DD (default: hoje)
 * 
 * @author SIMP - Sistema Integrado de Macromedição e Pitometria
 */

// Output buffering para capturar saídas indesejadas
ob_start();

header('Content-Type: application/json; charset=utf-8');

// PHP 8.3: capturar erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_level() > 0) { ob_end_clean(); }
        if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
        echo json_encode([
            'success' => false,
            'erro' => 'Erro fatal no servidor: ' . $error['message']
        ]);
    }
});

set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): bool {
    return true;
});

ini_set('display_errors', '0');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../conexao.php';

// conexao.php sobrescreve Content-Type
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

try {
    if (!isset($pdoSIMP) || !($pdoSIMP instanceof PDO)) {
        throw new Exception('Conexão com banco de dados não estabelecida');
    }

    // Validar parâmetros (data default = hoje)
    $cdPonto = (int)($_GET['cdPonto'] ?? 0);
    $data = trim((string)($_GET['data'] ?? date('Y-m-d')));
    
    if ($cdPonto <= 0) {
        throw new Exception('Código do ponto de medição inválido');
    }
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        throw new Exception('Data inválida');
    }
    
    // Verificar se é o dia atual
    $dataAtual = date('Y-m-d');
    if ($data !== $dataAtual) {
        if (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode([
            'success' => true,
            'is_dia_atual' => false,
            'dados' => [],
            'mensagem' => 'Dados do historiador disponíveis apenas para o dia atual'
        ]);
        exit;
    }
    
    // ========================================
    // BUSCAR TAG DO PONTO (consulta local — prepared statement OK)
    // ========================================
    $sqlTag = "SELECT 
                    CD_PONTO_MEDICAO, DS_NOME, ID_TIPO_MEDIDOR,
                    DS_TAG_VAZAO, DS_TAG_PRESSAO, DS_TAG_VOLUME, DS_TAG_RESERVATORIO
               FROM SIMP.dbo.PONTO_MEDICAO 
               WHERE CD_PONTO_MEDICAO = :cdPonto";
    
    $stmtTag = $pdoSIMP->prepare($sqlTag);
    $stmtTag->execute([':cdPonto' => $cdPonto]);
    $pontoMedicao = $stmtTag->fetch(PDO::FETCH_ASSOC);
    
    if (!is_array($pontoMedicao)) {
        throw new Exception('Ponto de medição não encontrado');
    }
    
    // Determinar TAG pelo tipo de medidor
    $tagName = null;
    $tipoTag = null;
    $tipoMedidor = (int)($pontoMedicao['ID_TIPO_MEDIDOR'] ?? 0);
    
    if ($tipoMedidor === 6 && !empty($pontoMedicao['DS_TAG_RESERVATORIO'])) {
        $tagName = $pontoMedicao['DS_TAG_RESERVATORIO']; $tipoTag = 'reservatorio';
    } elseif ($tipoMedidor === 3 && !empty($pontoMedicao['DS_TAG_PRESSAO'])) {
        $tagName = $pontoMedicao['DS_TAG_PRESSAO']; $tipoTag = 'pressao';
    } elseif ($tipoMedidor === 5 && !empty($pontoMedicao['DS_TAG_VOLUME'])) {
        $tagName = $pontoMedicao['DS_TAG_VOLUME']; $tipoTag = 'volume';
    } elseif (!empty($pontoMedicao['DS_TAG_VAZAO'])) {
        $tagName = $pontoMedicao['DS_TAG_VAZAO']; $tipoTag = 'vazao';
    } elseif (!empty($pontoMedicao['DS_TAG_RESERVATORIO'])) {
        $tagName = $pontoMedicao['DS_TAG_RESERVATORIO']; $tipoTag = 'reservatorio';
    } elseif (!empty($pontoMedicao['DS_TAG_PRESSAO'])) {
        $tagName = $pontoMedicao['DS_TAG_PRESSAO']; $tipoTag = 'pressao';
    } elseif (!empty($pontoMedicao['DS_TAG_VOLUME'])) {
        $tagName = $pontoMedicao['DS_TAG_VOLUME']; $tipoTag = 'volume';
    }
    
    if (empty($tagName)) {
        if (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode([
            'success' => true,
            'is_dia_atual' => true,
            'dados' => [],
            'tag' => null,
            'mensagem' => 'Ponto de medição não possui TAG configurada para o Historiador'
        ]);
        exit;
    }
    
    // ========================================
    // BUSCAR HORA ANTERIOR + HORA ATUAL DO HISTORIADOR
    // 
    // Query SIMPLES sem wwRetrievalMode/Cyclic — o modo Cyclic retorna
    // vValue=null neste ambiente do Historian. Query direta na tabela
    // History com filtro de data/hora funciona corretamente.
    //
    // Usa quote() para SQL direto (linked server não funciona com
    // prepared statements, confirmado no debug).
    // ========================================
    $horaAtual = (int)date('H');
    $horaAnterior = max(0, $horaAtual - 1);
    $dataInicio = sprintf('%s %02d:00:00', $data, $horaAnterior);
    $dataFim = sprintf('%s %02d:59:59', $data, $horaAtual);
    
    // Escapar com quote()
    $tagNameEsc = $pdoSIMP->quote($tagName);
    $dataInicioEsc = $pdoSIMP->quote($dataInicio);
    $dataFimEsc = $pdoSIMP->quote($dataFim);
    
    // Query simples — sem Cyclic, sem JOIN com Tag
    $sqlHistoriador = "
        SELECT 
            DateTime = CONVERT(nvarchar, DateTime, 21),
            vValue
        FROM [HISTORIADOR_CCO].Runtime.dbo.History
        WHERE TagName = {$tagNameEsc}
          AND DateTime >= {$dataInicioEsc}
          AND DateTime <= {$dataFimEsc}
        ORDER BY DateTime
    ";
    
    // Consulta ao Historiador — isolada
    $dadosHistoriador = [];
    try {
        $stmtHist = $pdoSIMP->query($sqlHistoriador);
        $dadosHistoriador = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if (ob_get_level() > 0) { ob_end_clean(); }
        echo json_encode([
            'success' => true,
            'is_dia_atual' => true,
            'dados' => [],
            'tag' => $tagName,
            'erro_conexao' => true,
            'mensagem' => 'Erro ao consultar Historiador CCO: ' . $e->getMessage()
        ]);
        exit;
    }
    
    // ========================================
    // CALCULAR MÉDIA (AVG) POR HORA NO PHP
    // Agrupa registros brutos por hora e calcula média, min, max
    // Filtra registros com vValue null (sensor offline)
    // ========================================
    $porHora = [];
    
    // Inicializar 24 horas como null (compatibilidade com frontend)
    for ($h = 0; $h < 24; $h++) {
        $porHora[$h] = [
            'hora' => $h,
            'media' => null,
            'min' => null,
            'max' => null,
            'qtd' => 0
        ];
    }
    
    // Agrupar registros por hora — filtrando nulls
    $valoresPorHora = [];
    $totalComValor = 0;
    $totalNull = 0;
    
    foreach ($dadosHistoriador as $registro) {
        $dateTime = (string)($registro['DateTime'] ?? '');
        if (strlen($dateTime) < 13) { continue; }
        $hora = (int)substr($dateTime, 11, 2);
        
        // Filtrar vValue null
        $rawValue = $registro['vValue'] ?? null;
        if ($rawValue === null) {
            $totalNull++;
            continue;
        }
        if (!is_numeric($rawValue)) { continue; }
        
        $valor = (float)$rawValue;
        
        if (!isset($valoresPorHora[$hora])) {
            $valoresPorHora[$hora] = [];
        }
        $valoresPorHora[$hora][] = $valor;
        $totalComValor++;
    }
    
    // Calcular AVG para cada hora
    foreach ($valoresPorHora as $h => $valores) {
        if (count($valores) > 0) {
            $media = array_sum($valores) / count($valores);
            if ($media != 0) {
                $porHora[$h] = [
                    'hora' => $h,
                    'media' => round($media, 2),
                    'min' => round(min($valores), 2),
                    'max' => round(max($valores), 2),
                    'qtd' => count($valores)
                ];
            }
        }
    }
    
    // Limpar output buffer
    if (ob_get_level() > 0) { ob_end_clean(); }
    
    // Retornar dados
    echo json_encode([
        'success' => true,
        'is_dia_atual' => true,
        'tag' => $tagName,
        'tipo_tag' => $tipoTag,
        'hora_atual' => $horaAtual,
        'hora_anterior' => $horaAnterior,
        'ponto' => [
            'codigo' => $pontoMedicao['CD_PONTO_MEDICAO'],
            'nome' => $pontoMedicao['DS_NOME'],
            'tipo_medidor' => $tipoMedidor
        ],
        'dados' => array_values($porHora),
        'total_registros' => count($dadosHistoriador),
        'total_com_valor' => $totalComValor,
        'total_null' => $totalNull,
        'horas_com_dados' => array_keys($valoresPorHora),
        'mensagem' => $totalComValor > 0 
            ? "Historiador: {$totalComValor} registros (horas " . str_pad((string)$horaAnterior, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string)$horaAtual, 2, '0', STR_PAD_LEFT) . ')'
            : 'Sem dados válidos no Historiador para as últimas horas (sensor possivelmente offline)'
    ]);
    
} catch (\Throwable $e) {
    if (ob_get_level() > 0) { ob_end_clean(); }
    if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'erro' => $e->getMessage()
    ]);
}

restore_error_handler();
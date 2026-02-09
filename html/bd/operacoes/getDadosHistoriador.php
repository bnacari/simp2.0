<?php
/**
 * getDadosHistoriador.php
 * 
 * Busca dados de telemetria do Historiador CCO para um ponto de medição
 * Retorna valores da HORA ANTERIOR e HORA ATUAL com média (AVG) para o gráfico
 * traçar uma linha com 2 pontos. Horas passadas já possuem dados no SIMP via integração CCO.
 * 
 * ALTERADO: Antes buscava 1440 registros (dia inteiro), agora busca 120 (2 horas)
 * e calcula a média com AVG. Horas passadas já possuem dados no SIMP via integração CCO.
 * 
 * Parâmetros:
 *   cdPonto - Código do ponto de medição
 *   data    - Data no formato YYYY-MM-DD
 * 
 * @author SIMP - Sistema Integrado de Macromedição e Pitometria
 */

header('Content-Type: application/json; charset=utf-8');

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir conexão com banco SIMP
require_once __DIR__ . '/../conexao.php';

try {
    // Validar parâmetros
    $cdPonto = isset($_GET['cdPonto']) ? (int)$_GET['cdPonto'] : 0;
    $data = isset($_GET['data']) ? trim($_GET['data']) : '';
    
    if ($cdPonto <= 0) {
        throw new Exception('Código do ponto de medição inválido');
    }
    
    if (empty($data) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        throw new Exception('Data inválida');
    }
    
    // Verificar se é o dia atual
    $dataAtual = date('Y-m-d');
    if ($data !== $dataAtual) {
        // Não é o dia atual, retornar vazio (não buscar historiador)
        echo json_encode([
            'success' => true,
            'is_dia_atual' => false,
            'dados' => [],
            'mensagem' => 'Dados do historiador disponíveis apenas para o dia atual'
        ]);
        exit;
    }
    
    // Buscar a TAG do ponto de medição no SIMP
    $sqlTag = "SELECT 
                    CD_PONTO_MEDICAO,
                    DS_NOME,
                    ID_TIPO_MEDIDOR,
                    DS_TAG_VAZAO,
                    DS_TAG_PRESSAO,
                    DS_TAG_VOLUME,
                    DS_TAG_RESERVATORIO
               FROM SIMP.dbo.PONTO_MEDICAO 
               WHERE CD_PONTO_MEDICAO = :cdPonto";
    
    $stmtTag = $pdoSIMP->prepare($sqlTag);
    $stmtTag->execute([':cdPonto' => $cdPonto]);
    $pontoMedicao = $stmtTag->fetch(PDO::FETCH_ASSOC);
    
    if (!$pontoMedicao) {
        throw new Exception('Ponto de medição não encontrado');
    }
    
    // Determinar qual TAG usar baseado no tipo de medidor
    $tagName = null;
    $tipoTag = null;
    
    $tipoMedidor = (int)$pontoMedicao['ID_TIPO_MEDIDOR'];
    
    // Tipos: 1=Macromedidor, 2=Estação Pitométrica, 3=Ponto Pressão, 4=Hidrometro, 5=Volume, 6=Nível Reservatório
    if ($tipoMedidor === 6 && !empty($pontoMedicao['DS_TAG_RESERVATORIO'])) {
        $tagName = $pontoMedicao['DS_TAG_RESERVATORIO'];
        $tipoTag = 'reservatorio';
    } elseif ($tipoMedidor === 3 && !empty($pontoMedicao['DS_TAG_PRESSAO'])) {
        $tagName = $pontoMedicao['DS_TAG_PRESSAO'];
        $tipoTag = 'pressao';
    } elseif ($tipoMedidor === 5 && !empty($pontoMedicao['DS_TAG_VOLUME'])) {
        $tagName = $pontoMedicao['DS_TAG_VOLUME'];
        $tipoTag = 'volume';
    } elseif (!empty($pontoMedicao['DS_TAG_VAZAO'])) {
        $tagName = $pontoMedicao['DS_TAG_VAZAO'];
        $tipoTag = 'vazao';
    } elseif (!empty($pontoMedicao['DS_TAG_RESERVATORIO'])) {
        $tagName = $pontoMedicao['DS_TAG_RESERVATORIO'];
        $tipoTag = 'reservatorio';
    } elseif (!empty($pontoMedicao['DS_TAG_PRESSAO'])) {
        $tagName = $pontoMedicao['DS_TAG_PRESSAO'];
        $tipoTag = 'pressao';
    } elseif (!empty($pontoMedicao['DS_TAG_VOLUME'])) {
        $tagName = $pontoMedicao['DS_TAG_VOLUME'];
        $tipoTag = 'volume';
    }
    
    if (empty($tagName)) {
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
    // Traz 2 horas para que o gráfico desenhe um traço (2 pontos)
    // wwCycleCount = 120 → 1 registro por minuto em 2 horas
    // ========================================
    $horaAtual = (int)date('H');
    $horaAnterior = max(0, $horaAtual - 1); // Não pode ser negativo
    $dataInicio = sprintf('%s %02d:00:00', $data, $horaAnterior);
    $dataFim = sprintf('%s %02d:59:59', $data, $horaAtual);
    
    // Calcular wwCycleCount: 120 minutos (2 horas) ou 60 se hora atual for 0
    $cycleCount = ($horaAtual === 0) ? 60 : 120;
    
    $sqlHistoriador = "
        SELECT 
            TagName = History.TagName,
            Description,
            DateTime = CONVERT(nvarchar, DATEADD(mi, 0, DateTime), 21), 
            vValue
        FROM [HISTORIADOR_CCO].Runtime.dbo.Tag, [HISTORIADOR_CCO].Runtime.dbo.History
        WHERE 
            History.TagName IN (:tagName)
            AND Tag.TagName = History.TagName
            AND wwRetrievalMode = 'Cyclic'
            AND wwCycleCount = $cycleCount
            AND wwVersion = 'Latest'
            AND DateTime >= :dataInicio 
            AND DateTime <= :dataFim
        ORDER BY DateTime
    ";
    
    try {
        $stmtHist = $pdoSIMP->prepare($sqlHistoriador);
        $stmtHist->execute([
            ':tagName' => $tagName,
            ':dataInicio' => $dataInicio,
            ':dataFim' => $dataFim
        ]);
    } catch (PDOException $e) {
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
    
    $dadosHistoriador = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
    
    // ========================================
    // CALCULAR MÉDIA (AVG) POR HORA (anterior + atual)
    // ========================================
    $porHora = [];
    
    // Inicializar todas as 24 horas como null (compatibilidade com frontend)
    for ($h = 0; $h < 24; $h++) {
        $porHora[$h] = [
            'hora' => $h,
            'media' => null,
            'min' => null,
            'max' => null,
            'qtd' => 0
        ];
    }
    
    // Agrupar registros por hora
    $valoresPorHora = [];
    foreach ($dadosHistoriador as $registro) {
        $hora = (int)substr($registro['DateTime'], 11, 2);
        $valor = (float)$registro['vValue'];
        if (!isset($valoresPorHora[$hora])) {
            $valoresPorHora[$hora] = [];
        }
        $valoresPorHora[$hora][] = $valor;
    }
    
    // Calcular AVG para cada hora (anterior + atual)
    foreach ($valoresPorHora as $h => $valores) {
        if (count($valores) > 0) {
            $media = array_sum($valores) / count($valores);
            
            // Só incluir se a média não é zero (evitar dados espúrios)
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
        'mensagem' => count($dadosHistoriador) > 0 
            ? 'Dados do Historiador carregados (horas: ' . str_pad($horaAnterior, 2, '0', STR_PAD_LEFT) . '-' . str_pad($horaAtual, 2, '0', STR_PAD_LEFT) . ')' 
            : 'Sem dados disponíveis no Historiador para as últimas horas'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'erro' => $e->getMessage()
    ]);
}
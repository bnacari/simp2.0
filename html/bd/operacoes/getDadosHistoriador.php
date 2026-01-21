<?php
/**
 * getDadosHistoriador.php
 * 
 * Busca dados de telemetria do Historiador CCO para um ponto de medição
 * Retorna valores hora a hora para comparação no gráfico de validação
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
    // Prioridade: TAG que estiver preenchida
    $tagName = null;
    $tipoTag = null;
    
    // Verificar tags na ordem de prioridade baseada no tipo de medidor
    $tipoMedidor = (int)$pontoMedicao['ID_TIPO_MEDIDOR'];
    
    // Tipos: 1=Macromedidor, 2=Estação Pitométrica, 3=Ponto Pressão, 4=Hidrometro, 5=Volume, 6=Nível Reservatório
    if ($tipoMedidor === 6 && !empty($pontoMedicao['DS_TAG_RESERVATORIO'])) {
        // Nível de reservatório - prioriza tag de reservatório
        $tagName = $pontoMedicao['DS_TAG_RESERVATORIO'];
        $tipoTag = 'reservatorio';
    } elseif ($tipoMedidor === 3 && !empty($pontoMedicao['DS_TAG_PRESSAO'])) {
        // Ponto de pressão - prioriza tag de pressão
        $tagName = $pontoMedicao['DS_TAG_PRESSAO'];
        $tipoTag = 'pressao';
    } elseif ($tipoMedidor === 5 && !empty($pontoMedicao['DS_TAG_VOLUME'])) {
        // Volume - prioriza tag de volume
        $tagName = $pontoMedicao['DS_TAG_VOLUME'];
        $tipoTag = 'volume';
    } elseif (!empty($pontoMedicao['DS_TAG_VAZAO'])) {
        // Padrão: tag de vazão
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
        // Ponto sem TAG configurada
        echo json_encode([
            'success' => true,
            'is_dia_atual' => true,
            'dados' => [],
            'tag' => null,
            'mensagem' => 'Ponto de medição não possui TAG configurada para o Historiador'
        ]);
        exit;
    }
    
    // Buscar dados do Historiador CCO via Linked Server
    // A conexão é feita pelo mesmo servidor do SIMP que possui o linked server [HISTORIADOR_CCO] configurado
    $dataInicio = $data . ' 00:00:00';
    $dataFim = $data . ' 23:59:59';
    
    // Query usando linked server [HISTORIADOR_CCO] conforme executado no CCO
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
            AND wwCycleCount = 1440
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
        // Erro ao consultar Historiador via linked server
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
    
    // Agrupar dados por hora para facilitar exibição no gráfico
    // Calcular média, mínimo e máximo por hora
    $porHora = [];
    for ($h = 0; $h < 24; $h++) {
        $porHora[$h] = [
            'hora' => $h,
            'valores' => [],
            'media' => null,
            'min' => null,
            'max' => null,
            'qtd' => 0
        ];
    }
    
    foreach ($dadosHistoriador as $registro) {
        // Extrair hora do DateTime (formato: YYYY-MM-DD HH:MM:SS.mmm)
        $dateTime = $registro['DateTime'];
        $hora = (int)substr($dateTime, 11, 2);
        $valor = (float)$registro['vValue'];
        
        if ($hora >= 0 && $hora < 24) {
            $porHora[$hora]['valores'][] = $valor;
        }
    }
    
    // Calcular estatísticas por hora
    // Também filtrar: horas futuras com valor zero não devem ser incluídas
    $horaAtual = (int)date('H');
    
    foreach ($porHora as $h => &$dadosHora) {
        if (count($dadosHora['valores']) > 0) {
            $dadosHora['qtd'] = count($dadosHora['valores']);
            $dadosHora['media'] = round(array_sum($dadosHora['valores']) / $dadosHora['qtd'], 2);
            $dadosHora['min'] = round(min($dadosHora['valores']), 2);
            $dadosHora['max'] = round(max($dadosHora['valores']), 2);
            
            // Se é hora futura e média é zero, não incluir (setar como null)
            if ($h > $horaAtual && $dadosHora['media'] == 0) {
                $dadosHora['media'] = null;
                $dadosHora['min'] = null;
                $dadosHora['max'] = null;
                $dadosHora['qtd'] = 0;
            }
        }
        // Remover array de valores para não sobrecarregar a resposta
        unset($dadosHora['valores']);
    }
    unset($dadosHora);
    
    // Retornar dados
    echo json_encode([
        'success' => true,
        'is_dia_atual' => true,
        'tag' => $tagName,
        'tipo_tag' => $tipoTag,
        'ponto' => [
            'codigo' => $pontoMedicao['CD_PONTO_MEDICAO'],
            'nome' => $pontoMedicao['DS_NOME'],
            'tipo_medidor' => $tipoMedidor
        ],
        'dados' => array_values($porHora),
        'total_registros' => count($dadosHistoriador),
        'mensagem' => count($dadosHistoriador) > 0 
            ? 'Dados do Historiador carregados com sucesso' 
            : 'Sem dados disponíveis no Historiador para este período'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'erro' => $e->getMessage()
    ]);
}
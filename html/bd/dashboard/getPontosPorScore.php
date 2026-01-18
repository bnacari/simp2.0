<?php
/**
 * SIMP - Dashboard de Saúde
 * Endpoint: Pontos por Score de Saúde
 * 
 * Suporta filtros: tipo_medidor, cd_unidade, status, problema, busca
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../verificarAuth.php';
    include_once '../conexao.php';
    
    // Parâmetros de filtro
    $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 500;
    $limite = max(1, min(1000, $limite));
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $tipoMedidor = isset($_GET['tipo_medidor']) && $_GET['tipo_medidor'] !== '' ? (int)$_GET['tipo_medidor'] : null;
    $cdUnidade = isset($_GET['cd_unidade']) && $_GET['cd_unidade'] !== '' ? (int)$_GET['cd_unidade'] : null;
    $problema = isset($_GET['problema']) ? trim($_GET['problema']) : '';
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    $ordenar = isset($_GET['ordenar']) ? trim($_GET['ordenar']) : 'score';
    $ordem = isset($_GET['ordem']) && strtoupper($_GET['ordem']) === 'DESC' ? 'DESC' : 'ASC';
    
    // Buscar última data disponível nos dados
    $ultimaDataDisponivel = null;
    $dataInicio = date('Y-m-d', strtotime('-7 days'));
    $dataFim = date('Y-m-d');
    
    try {
        $sqlMaxData = "SELECT MAX(DT_MEDICAO) AS DATA_MAX FROM MEDICAO_RESUMO_DIARIO";
        $stmtMax = $pdoSIMP->query($sqlMaxData);
        $rowMax = $stmtMax->fetch(PDO::FETCH_ASSOC);
        if ($rowMax && $rowMax['DATA_MAX']) {
            $ultimaDataDisponivel = $rowMax['DATA_MAX'];
            $dataFim = $ultimaDataDisponivel;
            $dataInicio = date('Y-m-d', strtotime('-7 days', strtotime($ultimaDataDisponivel)));
        }
    } catch (Exception $e) {
        // Tabela não existe
    }
    
    // Construir query dinâmica
    $where = ["MRD.DT_MEDICAO >= :dataInicio", "MRD.DT_MEDICAO <= :dataFim"];
    $params = [':dataInicio' => $dataInicio, ':dataFim' => $dataFim];
    
    // Filtro de tipo de medidor
    if ($tipoMedidor !== null) {
        $where[] = "PM.ID_TIPO_MEDIDOR = :tipoMedidor";
        $params[':tipoMedidor'] = $tipoMedidor;
    }
    
    // Filtro de unidade
    if ($cdUnidade !== null) {
        $where[] = "L.CD_UNIDADE = :cdUnidade";
        $params[':cdUnidade'] = $cdUnidade;
    }
    
    // Filtro de busca (nome ou código)
    if (!empty($busca)) {
        $where[] = "(PM.DS_NOME LIKE :busca OR CAST(PM.CD_PONTO_MEDICAO AS VARCHAR) LIKE :busca2)";
        $params[':busca'] = "%{$busca}%";
        $params[':busca2'] = "%{$busca}%";
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $where);
    
    // HAVING para filtros de status e problema (após agregação)
    $having = [];
    
    if (!empty($status)) {
        $statusList = explode(',', strtoupper($status));
        $statusConditions = [];
        foreach ($statusList as $s) {
            $s = trim($s);
            if ($s === 'SAUDAVEL') {
                $statusConditions[] = "AVG(CAST(MRD.VL_SCORE_SAUDE AS DECIMAL(5,2))) >= 8";
            } elseif ($s === 'ALERTA') {
                $statusConditions[] = "(AVG(CAST(MRD.VL_SCORE_SAUDE AS DECIMAL(5,2))) >= 5 AND AVG(CAST(MRD.VL_SCORE_SAUDE AS DECIMAL(5,2))) < 8)";
            } elseif ($s === 'CRITICO') {
                $statusConditions[] = "AVG(CAST(MRD.VL_SCORE_SAUDE AS DECIMAL(5,2))) < 5";
            }
        }
        if (!empty($statusConditions)) {
            $having[] = '(' . implode(' OR ', $statusConditions) . ')';
        }
    }
    
    // Filtro de problema (suporta múltiplos separados por vírgula)
    // IMPORTANTE: Manter consistente com getResumoGeral.php
    if (!empty($problema)) {
        $problemas = array_map('trim', explode(',', strtoupper($problema)));
        $problemaConditions = [];
        
        foreach ($problemas as $prob) {
            if ($prob === 'COMUNICACAO') {
                $problemaConditions[] = "SUM(CASE WHEN MRD.FL_SEM_COMUNICACAO = 1 THEN 1 ELSE 0 END) > 0";
            } elseif ($prob === 'MEDIDOR') {
                // Inclui FL_VALOR_CONSTANTE e FL_PERFIL_ANOMALO (igual ao dashboard)
                $problemaConditions[] = "SUM(CASE WHEN MRD.FL_VALOR_CONSTANTE = 1 OR MRD.FL_PERFIL_ANOMALO = 1 THEN 1 ELSE 0 END) > 0";
            } elseif ($prob === 'HIDRAULICO') {
                $problemaConditions[] = "(SUM(CASE WHEN MRD.FL_VALOR_NEGATIVO = 1 THEN 1 ELSE 0 END) > 0 OR SUM(CASE WHEN MRD.FL_FORA_FAIXA = 1 THEN 1 ELSE 0 END) > 0 OR SUM(CASE WHEN MRD.FL_SPIKE = 1 THEN 1 ELSE 0 END) > 0)";
            } elseif ($prob === 'VERIFICAR') {
                $problemaConditions[] = "SUM(CASE WHEN MRD.FL_ZEROS_SUSPEITOS = 1 THEN 1 ELSE 0 END) > 0";
            } elseif ($prob === 'TRATAMENTO') {
                $problemaConditions[] = "SUM(ISNULL(MRD.QTD_TRATAMENTOS, 0)) > 3";
            }
        }
        
        if (!empty($problemaConditions)) {
            // Usar OR para permitir qualquer um dos problemas selecionados
            $having[] = '(' . implode(' OR ', $problemaConditions) . ')';
        }
    }
    
    $havingClause = !empty($having) ? 'HAVING ' . implode(' AND ', $having) : '';
    
    // Ordenação
    $orderBy = 'SCORE_MEDIO ASC';
    if ($ordenar === 'anomalias') {
        $orderBy = "DIAS_COM_ANOMALIA {$ordem}";
    } elseif ($ordenar === 'nome') {
        $orderBy = "NOME_PONTO {$ordem}";
    } elseif ($ordenar === 'tratamento') {
        $orderBy = "QTD_TRATAMENTOS DESC";
    } elseif ($ordem === 'DESC') {
        $orderBy = 'SCORE_MEDIO DESC';
    }
    
    $sql = "
        SELECT TOP {$limite}
            MRD.CD_PONTO_MEDICAO,
            PM.DS_NOME AS NOME_PONTO,
            PM.ID_TIPO_MEDIDOR,
            L.CD_UNIDADE,
            CASE PM.ID_TIPO_MEDIDOR
                WHEN 1 THEN 'M - Macromedidor'
                WHEN 2 THEN 'E - Estação Pitométrica'
                WHEN 4 THEN 'P - Medidor Pressão'
                WHEN 6 THEN 'R - Nível Reservatório'
                WHEN 8 THEN 'H - Hidrômetro'
                ELSE 'X - Desconhecido'
            END AS TIPO_MEDIDOR,
            ROUND(AVG(CAST(MRD.VL_SCORE_SAUDE AS DECIMAL(5,2))), 2) AS SCORE_MEDIO,
            MIN(MRD.VL_SCORE_SAUDE) AS SCORE_MINIMO,
            CASE 
                WHEN AVG(CAST(MRD.VL_SCORE_SAUDE AS DECIMAL(5,2))) >= 8 THEN 'SAUDAVEL'
                WHEN AVG(CAST(MRD.VL_SCORE_SAUDE AS DECIMAL(5,2))) >= 5 THEN 'ALERTA'
                ELSE 'CRITICO'
            END AS STATUS_SAUDE,
            CASE 
                WHEN AVG(CAST(MRD.VL_SCORE_SAUDE AS DECIMAL(5,2))) >= 8 THEN '#22c55e'
                WHEN AVG(CAST(MRD.VL_SCORE_SAUDE AS DECIMAL(5,2))) >= 5 THEN '#f59e0b'
                ELSE '#dc2626'
            END AS COR_STATUS,
            COUNT(*) AS DIAS_ANALISADOS,
            SUM(CASE WHEN MRD.FL_SEM_COMUNICACAO = 1 THEN 1 ELSE 0 END) AS DIAS_SEM_COMUNICACAO,
            SUM(CASE WHEN MRD.FL_VALOR_CONSTANTE = 1 THEN 1 ELSE 0 END) AS DIAS_VALOR_CONSTANTE,
            SUM(CASE WHEN MRD.FL_PERFIL_ANOMALO = 1 THEN 1 ELSE 0 END) AS DIAS_PERFIL_ANOMALO,
            SUM(CASE WHEN MRD.FL_VALOR_CONSTANTE = 1 OR MRD.FL_PERFIL_ANOMALO = 1 THEN 1 ELSE 0 END) AS DIAS_PROBLEMA_MEDIDOR,
            SUM(CASE WHEN MRD.FL_VALOR_NEGATIVO = 1 THEN 1 ELSE 0 END) AS DIAS_VALOR_NEGATIVO,
            SUM(CASE WHEN MRD.FL_FORA_FAIXA = 1 THEN 1 ELSE 0 END) AS DIAS_FORA_FAIXA,
            SUM(CASE WHEN MRD.FL_SPIKE = 1 THEN 1 ELSE 0 END) AS DIAS_COM_SPIKE,
            SUM(CASE WHEN MRD.FL_ZEROS_SUSPEITOS = 1 THEN 1 ELSE 0 END) AS DIAS_ZEROS_SUSPEITOS,
            SUM(CASE WHEN MRD.FL_ANOMALIA = 1 THEN 1 ELSE 0 END) AS DIAS_COM_ANOMALIA,
            SUM(ISNULL(MRD.QTD_TRATAMENTOS, 0)) AS QTD_TRATAMENTOS,
            ROUND(AVG(CAST(MRD.QTD_REGISTROS AS DECIMAL(10,2))), 0) AS REGISTROS_MEDIO,
            ROUND(AVG(MRD.VL_MEDIA_DIARIA), 2) AS MEDIA_PERIODO
        FROM SIMP.dbo.MEDICAO_RESUMO_DIARIO MRD
        INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = MRD.CD_PONTO_MEDICAO
        LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
        {$whereClause}
        GROUP BY MRD.CD_PONTO_MEDICAO, PM.DS_NOME, PM.ID_TIPO_MEDIDOR, L.CD_UNIDADE
        {$havingClause}
        ORDER BY {$orderBy}
    ";
    
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $dados,
        'total' => count($dados),
        'ultima_data' => $ultimaDataDisponivel,
        'filtros_aplicados' => [
            'tipo_medidor' => $tipoMedidor,
            'cd_unidade' => $cdUnidade,
            'status' => $status,
            'problema' => $problema,
            'busca' => $busca
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ], JSON_UNESCAPED_UNICODE);
}
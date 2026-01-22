<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint: Listar Conjuntos Motor-Bomba
 * 
 * ATUALIZADO: Incluído VL_ALTURA_MANOMETRICA_BOMBA e DS_LOCALIZACAO
 * 
 * @author SIMP
 * @version 1.1
 */

include_once '../conexao.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // ============================================
    // Parâmetros de filtro
    // ============================================
    $cdUnidade = isset($_GET['cd_unidade']) && $_GET['cd_unidade'] !== '' ? (int)$_GET['cd_unidade'] : null;
    $cdLocalidade = isset($_GET['cd_localidade']) && $_GET['cd_localidade'] !== '' ? (int)$_GET['cd_localidade'] : null;
    $tipoEixo = isset($_GET['tipo_eixo']) && $_GET['tipo_eixo'] !== '' ? trim($_GET['tipo_eixo']) : null;
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    
    // ============================================
    // Parâmetros de paginação
    // ============================================
    $pagina = isset($_GET['pagina']) && $_GET['pagina'] !== '' ? (int)$_GET['pagina'] : 1;
    $porPagina = 20;
    $offset = ($pagina - 1) * $porPagina;

    // ============================================
    // Parâmetros de ordenação
    // ============================================
    $ordenarPor = isset($_GET['ordenar_por']) && $_GET['ordenar_por'] !== '' ? $_GET['ordenar_por'] : 'CMB.DS_NOME';
    $ordenarDirecao = isset($_GET['ordenar_direcao']) && strtoupper($_GET['ordenar_direcao']) === 'DESC' ? 'DESC' : 'ASC';

    // Colunas permitidas para ordenação (incluindo novas colunas)
    $colunasPermitidas = [
        'UNIDADE', 'LOCALIDADE', 'DS_CODIGO', 'DS_NOME', 'TP_EIXO', 
        'VL_POTENCIA_MOTOR', 'VL_VAZAO_BOMBA', 'VL_ALTURA_MANOMETRICA_BOMBA', 'DS_LOCALIZACAO'
    ];

    if (!in_array($ordenarPor, $colunasPermitidas)) {
        $ordenarPor = 'CMB.DS_NOME';
    } else {
        if ($ordenarPor === 'UNIDADE') $ordenarPor = 'U.DS_NOME';
        elseif ($ordenarPor === 'LOCALIDADE') $ordenarPor = 'L.DS_NOME';
        else $ordenarPor = 'CMB.' . $ordenarPor;
    }

    // ============================================
    // Verificar se há filtro
    // ============================================
    $temFiltro = ($cdUnidade !== null || $cdLocalidade !== null || $tipoEixo !== null || $busca !== '');

    if (!$temFiltro) {
        echo json_encode([
            'success' => true,
            'total' => 0,
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'totalPaginas' => 0,
            'data' => [],
            'message' => 'Preencha ao menos um filtro para realizar a busca'
        ]);
        exit;
    }

    // ============================================
    // Construir cláusulas WHERE
    // ============================================
    $where = [];
    $params = [];

    if ($cdUnidade !== null) {
        $where[] = "L.CD_UNIDADE = :cd_unidade";
        $params[':cd_unidade'] = $cdUnidade;
    }

    if ($cdLocalidade !== null) {
        $where[] = "CMB.CD_LOCALIDADE = :cd_localidade";
        $params[':cd_localidade'] = $cdLocalidade;
    }

    if ($tipoEixo !== null) {
        $where[] = "CMB.TP_EIXO = :tipo_eixo";
        $params[':tipo_eixo'] = $tipoEixo;
    }

    if ($busca !== '') {
        $buscaTermo = '%' . $busca . '%';
        $where[] = "(CMB.DS_CODIGO LIKE :busca OR CMB.DS_NOME LIKE :busca2 OR CMB.DS_LOCALIZACAO LIKE :busca3)";
        $params[':busca'] = $buscaTermo;
        $params[':busca2'] = $buscaTermo;
        $params[':busca3'] = $buscaTermo;
    }

    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    // ============================================
    // Contagem total
    // ============================================
    $sqlCount = "SELECT COUNT(*) AS total 
                 FROM SIMP.dbo.CONJUNTO_MOTOR_BOMBA CMB
                 INNER JOIN SIMP.dbo.LOCALIDADE L ON CMB.CD_LOCALIDADE = L.CD_CHAVE
                 INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
                 $whereClause";
    
    $stmtCount = $pdoSIMP->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

    // ============================================
    // Buscar dados (ATUALIZADO: incluído VL_ALTURA_MANOMETRICA_BOMBA e DS_LOCALIZACAO)
    // ============================================
    $sql = "SELECT 
                CMB.CD_CHAVE,
                CMB.DS_CODIGO,
                CMB.DS_NOME,
                CMB.DS_LOCALIZACAO,
                CMB.TP_EIXO,
                CMB.VL_POTENCIA_MOTOR,
                CMB.VL_VAZAO_BOMBA,
                CMB.VL_ALTURA_MANOMETRICA_BOMBA,
                U.CD_CODIGO AS CD_UNIDADE_CODIGO,
                U.DS_NOME AS DS_UNIDADE,
                L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
                L.DS_NOME AS DS_LOCALIDADE
            FROM SIMP.dbo.CONJUNTO_MOTOR_BOMBA CMB
            INNER JOIN SIMP.dbo.LOCALIDADE L ON CMB.CD_LOCALIDADE = L.CD_CHAVE
            INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
            $whereClause
            ORDER BY $ordenarPor $ordenarDirecao
            OFFSET $offset ROWS FETCH NEXT $porPagina ROWS ONLY";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tentar contar anexos (usando tabela genérica ANEXO)
    // Primeiro buscar CD_FUNCIONALIDADE para Motor-Bomba
    $cdFuncAnexo = null;
    try {
        $sqlFunc = "SELECT CD_FUNCIONALIDADE FROM SIMP.dbo.FUNCIONALIDADE 
                    WHERE DS_NOME LIKE '%Conjunto Motor%Bomba%' OR DS_NOME LIKE '%Motor-Bomba%'";
        $stmtFunc = $pdoSIMP->query($sqlFunc);
        $func = $stmtFunc->fetch(PDO::FETCH_ASSOC);
        if ($func) {
            $cdFuncAnexo = $func['CD_FUNCIONALIDADE'];
        }
    } catch (Exception $e) {
        // Funcionalidade não encontrada
    }

    foreach ($dados as &$row) {
        $row['QTD_ANEXOS'] = 0;
        if ($cdFuncAnexo) {
            try {
                $sqlAnexos = "SELECT COUNT(*) AS QTD FROM SIMP.dbo.ANEXO 
                              WHERE CD_FUNCIONALIDADE = :cd_func AND CD_CHAVE_FUNCIONALIDADE = :cd";
                $stmtAnexos = $pdoSIMP->prepare($sqlAnexos);
                $stmtAnexos->execute([':cd_func' => $cdFuncAnexo, ':cd' => $row['CD_CHAVE']]);
                $row['QTD_ANEXOS'] = (int)$stmtAnexos->fetch(PDO::FETCH_ASSOC)['QTD'];
            } catch (Exception $e) {
                // Erro ao contar anexos, ignorar
            }
        }
    }
    unset($row);

    // ============================================
    // Retornar resultado
    // ============================================
    echo json_encode([
        'success' => true,
        'total' => $total,
        'pagina' => $pagina,
        'porPagina' => $porPagina,
        'totalPaginas' => ceil($total / $porPagina),
        'data' => $dados
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro no banco de dados: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

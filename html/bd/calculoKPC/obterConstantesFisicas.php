<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Endpoint para obter constantes físicas para cálculo de KPC
 * 
 * Busca valores de Sef (Área Efetiva), Kp (Correção Projeção TAP) e Densidade
 * baseado no sistema legado (CalculoPitometria.cs)
 * 
 * ALINHAMENTO COM LEGADO:
 *   - Sef: GetDataBySef(VL_DIAMETRO_NOMINAL)
 *   - Kp:  GetDataByFiltro(projecao_tap, diametro_nominal, "Kp") | DN >= 301 → 1
 *   - Densidade: GetDataByFiltro(temperatura, null, "Densidade")
 * 
 * CORREÇÃO v2.1 - As queries agora usam JOIN com CONSTANTE_FISICA para
 * resolver DS_NOME, e usam VL_REFERENCIA (não VL_REFERENCIA_A) na tabela
 * CONSTANTE_FISICA_TABELA conforme estrutura real do banco:
 *   - CONSTANTE_FISICA: CD_CHAVE, DS_NOME, DS_UNIDADE_REFERENCIA, DS_UNIDADE_REFERENCIA_B, DS_UNIDADE_VALOR
 *   - CONSTANTE_FISICA_TABELA: CD_CHAVE, CD_CONSTANTE_FISICA, VL_REFERENCIA, VL_REFERENCIA_B, VL_VALOR
 * 
 * @author Bruno - SIMP
 * @version 2.1 - Corrigido mapeamento de colunas do banco
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'verificarAuth.php';
    include_once 'conexao.php';

    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
    $diametroNominal = isset($_GET['diametro_nominal']) ? (float)$_GET['diametro_nominal'] : 0;
    $projecaoTap = isset($_GET['projecao_tap']) ? (float)$_GET['projecao_tap'] : 0;
    $temperatura = isset($_GET['temperatura']) ? (float)$_GET['temperatura'] : 25;

    $resultado = ['success' => false, 'valor' => null];

    switch ($tipo) {
        case 'sef':
            $resultado = obterSef($pdoSIMP, $diametroNominal);
            break;

        case 'kp':
            $resultado = obterKp($pdoSIMP, $projecaoTap, $diametroNominal);
            break;

        case 'densidade':
            $resultado = obterDensidade($pdoSIMP, $temperatura);
            break;

        case 'todos':
            $sef = obterSef($pdoSIMP, $diametroNominal);
            $kp = obterKp($pdoSIMP, $projecaoTap, $diametroNominal);
            $densidade = obterDensidade($pdoSIMP, $temperatura);
            
            $resultado = [
                'success' => true,
                'sef' => $sef['valor'],
                'sef_fonte' => $sef['fonte'],
                'kp' => $kp['valor'],
                'kp_fonte' => $kp['fonte'],
                'densidade' => $densidade['valor'],
                'densidade_fonte' => $densidade['fonte'],
                // Flag para o JS saber o formato da densidade
                'densidade_formato' => $densidade['formato'] ?? 'adimensional'
            ];
            break;

        default:
            throw new Exception('Tipo de constante inválido. Use: sef, kp, densidade ou todos');
    }

    echo json_encode($resultado);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Obtém a Área Efetiva (Sef) baseada no diâmetro nominal
 * Legado: objConstanteFisicaTabelaTableAdapter.GetDataBySef(VL_DIAMETRO_NOMINAL)
 * 
 * CORREÇÃO v2.1: A tabela CONSTANTE_FISICA_TABELA não possui coluna DS_NOME
 * nem VL_REFERENCIA_A. A estrutura real é:
 *   CD_CONSTANTE_FISICA (FK → CONSTANTE_FISICA.CD_CHAVE) e VL_REFERENCIA
 * Necessário JOIN com CONSTANTE_FISICA para filtrar por DS_NOME = 'Sef'
 */
function obterSef($pdo, $diametroNominal) {
    // 1. Tenta buscar na tabela CONSTANTE_FISICA_TABELA via JOIN com CONSTANTE_FISICA
    try {
        $sql = "SELECT cft.VL_VALOR 
                FROM SIMP.dbo.CONSTANTE_FISICA_TABELA cft
                INNER JOIN SIMP.dbo.CONSTANTE_FISICA cf ON cf.CD_CHAVE = cft.CD_CONSTANTE_FISICA
                WHERE cf.DS_NOME = 'Sef' AND cft.VL_REFERENCIA = :dn";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':dn' => $diametroNominal]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            return ['success' => true, 'valor' => (float)$row['VL_VALOR'], 'fonte' => 'banco'];
        }
    } catch (Exception $e) {
        error_log('obterSef - Erro ao buscar no banco: ' . $e->getMessage());
    }

    // 2. Tabela padrão pitométrica (em m²) - fallback apenas se o banco não retornar
    //    NOTA: Estes valores são teóricos (π × (DN/2000)²). O banco pode ter valores
    //    calibrados/reais diferentes. Priorizar sempre o banco.
    $tabelaSef = [
        50 => 0.001963, 75 => 0.004418, 100 => 0.007854, 150 => 0.017671,
        200 => 0.031416, 250 => 0.049087, 300 => 0.070686, 350 => 0.096211,
        400 => 0.125664, 450 => 0.159043, 500 => 0.196350, 600 => 0.282743,
        700 => 0.384845, 800 => 0.502655, 900 => 0.636173, 1000 => 0.785398,
        1100 => 0.950332, 1200 => 1.130973
    ];

    if (isset($tabelaSef[$diametroNominal])) {
        return ['success' => true, 'valor' => $tabelaSef[$diametroNominal], 'fonte' => 'tabela_padrao'];
    }

    // 3. Calcula: Sef = π × (DN/2000)²
    $sef = M_PI * pow($diametroNominal / 2000, 2);
    return ['success' => true, 'valor' => $sef, 'fonte' => 'calculado'];
}

/**
 * Obtém a Correção de Projeção TAP (Kp)
 * Legado: se DN >= 301 → 1; senão GetDataByFiltro(projecao_tap, diametro_nominal, "Kp")
 * 
 * CORREÇÃO v2.1: Mesma correção de colunas - usar JOIN e VL_REFERENCIA/VL_REFERENCIA_B
 */
function obterKp($pdo, $projecaoTap, $diametroNominal) {
    // Regra do legado: DN >= 301 retorna 1
    if ($diametroNominal >= 301) {
        return ['success' => true, 'valor' => 1.0, 'fonte' => 'regra_dn_301'];
    }

    // 1. Busca exata no banco (mesmo comportamento do legado)
    try {
        $sql = "SELECT cft.VL_VALOR 
                FROM SIMP.dbo.CONSTANTE_FISICA_TABELA cft
                INNER JOIN SIMP.dbo.CONSTANTE_FISICA cf ON cf.CD_CHAVE = cft.CD_CONSTANTE_FISICA
                WHERE cf.DS_NOME = 'Kp' 
                AND cft.VL_REFERENCIA = :pt 
                AND cft.VL_REFERENCIA_B = :dn";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':pt' => $projecaoTap, ':dn' => $diametroNominal]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            return ['success' => true, 'valor' => (float)$row['VL_VALOR'], 'fonte' => 'banco'];
        }
    } catch (Exception $e) {
        error_log('obterKp - Erro ao buscar no banco: ' . $e->getMessage());
    }

    // 2. Tabela de fallback local
    $tabelaKp = [
        25 => [50 => 0.98, 75 => 0.99, 100 => 0.995, 150 => 0.998, 200 => 0.999, 250 => 1.0, 300 => 1.0],
        30 => [50 => 0.97, 75 => 0.98, 100 => 0.99, 150 => 0.995, 200 => 0.998, 250 => 0.999, 300 => 1.0],
        35 => [50 => 0.96, 75 => 0.97, 100 => 0.98, 150 => 0.99, 200 => 0.995, 250 => 0.998, 300 => 1.0],
        40 => [50 => 0.95, 75 => 0.96, 100 => 0.97, 150 => 0.98, 200 => 0.99, 250 => 0.995, 300 => 1.0],
        45 => [50 => 0.94, 75 => 0.95, 100 => 0.96, 150 => 0.97, 200 => 0.98, 250 => 0.99, 300 => 1.0],
        50 => [50 => 0.93, 75 => 0.94, 100 => 0.95, 150 => 0.96, 200 => 0.97, 250 => 0.98, 300 => 1.0]
    ];

    // Encontra a projeção mais próxima
    $ptProxima = 25;
    $menorDif = abs($projecaoTap - 25);
    foreach ($tabelaKp as $p => $valores) {
        $dif = abs($projecaoTap - $p);
        if ($dif < $menorDif) {
            $menorDif = $dif;
            $ptProxima = $p;
        }
    }

    if (isset($tabelaKp[$ptProxima])) {
        // Encontra DN mais próximo
        $dnProximo = 50;
        $menorDifDn = abs($diametroNominal - 50);
        foreach ($tabelaKp[$ptProxima] as $d => $valor) {
            $difDn = abs($diametroNominal - $d);
            if ($difDn < $menorDifDn) {
                $menorDifDn = $difDn;
                $dnProximo = $d;
            }
        }
        return ['success' => true, 'valor' => $tabelaKp[$ptProxima][$dnProximo], 'fonte' => 'tabela_padrao'];
    }

    return ['success' => true, 'valor' => 1.0, 'fonte' => 'padrao'];
}

/**
 * Obtém a Densidade baseada na temperatura
 * Legado: GetDataByFiltro(temperatura, null, "Densidade")
 * 
 * CORREÇÃO v2.1: Mesma correção de colunas - usar JOIN e VL_REFERENCIA
 * 
 * Esta função NORMALIZA o retorno para ser usado direto na fórmula:
 *   - Se valor do banco > 10 → divide por 1000 (era kg/m³)
 *   - Se valor do banco <= 10 → usa direto (já é adimensional)
 */
function obterDensidade($pdo, $temperatura) {
    // 1. Busca exata no banco
    try {
        $sql = "SELECT cft.VL_VALOR 
                FROM SIMP.dbo.CONSTANTE_FISICA_TABELA cft
                INNER JOIN SIMP.dbo.CONSTANTE_FISICA cf ON cf.CD_CHAVE = cft.CD_CONSTANTE_FISICA
                WHERE cf.DS_NOME = 'Densidade' 
                AND cft.VL_REFERENCIA = :temp";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':temp' => $temperatura]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $valor = (float)$row['VL_VALOR'];
            // Normaliza: se está em kg/m³ (>10), converte para adimensional
            $formato = 'adimensional';
            if ($valor > 10) {
                $valor = $valor / 1000;
                $formato = 'normalizado_de_kgm3';
            }
            return ['success' => true, 'valor' => $valor, 'fonte' => 'banco', 'formato' => $formato];
        }
    } catch (Exception $e) {
        error_log('obterDensidade - Erro ao buscar no banco: ' . $e->getMessage());
    }

    // 2. Busca por interpolação no banco (para temperaturas intermediárias)
    try {
        $sql = "SELECT TOP 1 cft.VL_REFERENCIA AS temp, cft.VL_VALOR 
                FROM SIMP.dbo.CONSTANTE_FISICA_TABELA cft
                INNER JOIN SIMP.dbo.CONSTANTE_FISICA cf ON cf.CD_CHAVE = cft.CD_CONSTANTE_FISICA
                WHERE cf.DS_NOME = 'Densidade' AND cft.VL_REFERENCIA <= :temp
                ORDER BY cft.VL_REFERENCIA DESC";
        $stmtInf = $pdo->prepare($sql);
        $stmtInf->execute([':temp' => $temperatura]);
        $rowInf = $stmtInf->fetch(PDO::FETCH_ASSOC);

        $sql = "SELECT TOP 1 cft.VL_REFERENCIA AS temp, cft.VL_VALOR 
                FROM SIMP.dbo.CONSTANTE_FISICA_TABELA cft
                INNER JOIN SIMP.dbo.CONSTANTE_FISICA cf ON cf.CD_CHAVE = cft.CD_CONSTANTE_FISICA
                WHERE cf.DS_NOME = 'Densidade' AND cft.VL_REFERENCIA >= :temp
                ORDER BY cft.VL_REFERENCIA ASC";
        $stmtSup = $pdo->prepare($sql);
        $stmtSup->execute([':temp' => $temperatura]);
        $rowSup = $stmtSup->fetch(PDO::FETCH_ASSOC);

        if ($rowInf && $rowSup && $rowInf['temp'] != $rowSup['temp']) {
            $fator = ($temperatura - $rowInf['temp']) / ($rowSup['temp'] - $rowInf['temp']);
            $valor = $rowInf['VL_VALOR'] + $fator * ($rowSup['VL_VALOR'] - $rowInf['VL_VALOR']);
            
            $formato = 'adimensional';
            if ($valor > 10) {
                $valor = $valor / 1000;
                $formato = 'normalizado_de_kgm3';
            }
            return ['success' => true, 'valor' => $valor, 'fonte' => 'banco_interpolado', 'formato' => $formato];
        }
        
        if ($rowInf) {
            $valor = (float)$rowInf['VL_VALOR'];
            $formato = 'adimensional';
            if ($valor > 10) {
                $valor = $valor / 1000;
                $formato = 'normalizado_de_kgm3';
            }
            return ['success' => true, 'valor' => $valor, 'fonte' => 'banco_aproximado', 'formato' => $formato];
        }
    } catch (Exception $e) {
        error_log('obterDensidade - Erro na interpolação: ' . $e->getMessage());
    }

    // 3. Tabela de fallback local (em kg/m³ - será normalizada pelo JS)
    $tabelaDensidade = [
        0 => 999.84, 5 => 999.96, 10 => 999.70, 15 => 999.10, 20 => 998.20,
        25 => 997.05, 30 => 995.65, 35 => 994.03, 40 => 992.22, 45 => 990.21, 50 => 988.03
    ];

    if (isset($tabelaDensidade[$temperatura])) {
        $valor = $tabelaDensidade[$temperatura] / 1000; // Normaliza para adimensional
        return ['success' => true, 'valor' => $valor, 'fonte' => 'tabela_padrao', 'formato' => 'normalizado_de_kgm3'];
    }

    // Interpolação local
    $temps = array_keys($tabelaDensidade);
    sort($temps);
    $tempInf = $temps[0];
    $tempSup = $temps[count($temps) - 1];

    foreach ($temps as $t) {
        if ($t <= $temperatura) $tempInf = $t;
        if ($t >= $temperatura) { $tempSup = $t; break; }
    }

    if ($tempInf !== $tempSup) {
        $fator = ($temperatura - $tempInf) / ($tempSup - $tempInf);
        $valor = $tabelaDensidade[$tempInf] + $fator * ($tabelaDensidade[$tempSup] - $tabelaDensidade[$tempInf]);
    } else {
        $valor = $tabelaDensidade[$tempInf] ?? 997.05;
    }

    $valor = $valor / 1000; // Normaliza para adimensional
    return ['success' => true, 'valor' => $valor, 'fonte' => 'tabela_padrao_interpolada', 'formato' => 'normalizado_de_kgm3'];
}
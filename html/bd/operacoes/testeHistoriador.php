<?php
/**
 * testeHistoriador.php
 * 
 * Script de teste para verificar conexão e dados do Historiador CCO via Linked Server
 * 
 * http://vdeskadds007.cesan.com.br:9461/bd/operacoes/testeHistoriador.php?tag=CP014_TM8_84_MED&data=2026-01-20
 * 
 * @author SIMP
 */

header('Content-Type: text/html; charset=utf-8');

$tagName = isset($_GET['tag']) ? trim($_GET['tag']) : '';
$data = isset($_GET['data']) ? trim($_GET['data']) : date('Y-m-d');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Teste Historiador CCO</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #1e3a5f; }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
        .info { color: #3b82f6; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        th, td { padding: 6px 10px; text-align: left; border: 1px solid #e5e7eb; }
        th { background: #f8fafc; }
        tr:nth-child(even) { background: #f8fafc; }
        pre { background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 6px; overflow-x: auto; font-size: 12px; }
        code { font-family: 'Consolas', monospace; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type=text] { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; width: 300px; }
        button { background: #3b82f6; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px; }
        button:hover { background: #2563eb; }
        .tag-link { color: #3b82f6; cursor: pointer; text-decoration: underline; }
        .debug { background: #fef3c7; border: 1px solid #f59e0b; padding: 10px; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔌 Teste Historiador CCO - Debug</h1>
    
    <div class='card'>
        <h3>Parâmetros</h3>
        <form method='GET'>
            <div class='form-group'>
                <label>TAG:</label>
                <input type='text' name='tag' value='{$tagName}' placeholder='Selecione da lista abaixo'>
            </div>
            <div class='form-group'>
                <label>Data (YYYY-MM-DD):</label>
                <input type='text' name='data' value='{$data}'>
            </div>
            <button type='submit'>Testar</button>
            <button type='submit' name='debug' value='1'>Testar com Debug Completo</button>
        </form>
    </div>";

require_once __DIR__ . '/../conexao.php';

$debug = isset($_GET['debug']);

// Listar TAGs do SIMP
echo "<div class='card'>
    <h3>🏷️ TAGs Configuradas no SIMP</h3>";

try {
    $sqlPontos = "SELECT TOP 30
                    CD_PONTO_MEDICAO,
                    DS_NOME,
                    ID_TIPO_MEDIDOR,
                    DS_TAG_VAZAO,
                    DS_TAG_PRESSAO,
                    DS_TAG_VOLUME,
                    DS_TAG_RESERVATORIO
                  FROM SIMP.dbo.PONTO_MEDICAO
                  WHERE DS_TAG_VAZAO IS NOT NULL 
                     OR DS_TAG_PRESSAO IS NOT NULL 
                     OR DS_TAG_VOLUME IS NOT NULL 
                     OR DS_TAG_RESERVATORIO IS NOT NULL
                  ORDER BY DS_NOME";
    
    $stmtPontos = $pdoSIMP->query($sqlPontos);
    $pontos = $stmtPontos->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($pontos) > 0) {
        echo "<table>
                <tr><th>Cód</th><th>Nome</th><th>TAG Vazão</th><th>TAG Pressão</th><th>TAG Reserv.</th></tr>";
        
        foreach ($pontos as $p) {
            $tagV = $p['DS_TAG_VAZAO'] ? "<a class='tag-link' href='?tag={$p['DS_TAG_VAZAO']}&data={$data}'>{$p['DS_TAG_VAZAO']}</a>" : '-';
            $tagP = $p['DS_TAG_PRESSAO'] ? "<a class='tag-link' href='?tag={$p['DS_TAG_PRESSAO']}&data={$data}'>{$p['DS_TAG_PRESSAO']}</a>" : '-';
            $tagR = $p['DS_TAG_RESERVATORIO'] ? "<a class='tag-link' href='?tag={$p['DS_TAG_RESERVATORIO']}&data={$data}'>{$p['DS_TAG_RESERVATORIO']}</a>" : '-';
            
            echo "<tr><td>{$p['CD_PONTO_MEDICAO']}</td><td>{$p['DS_NOME']}</td><td>{$tagV}</td><td>{$tagP}</td><td>{$tagR}</td></tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='error'>Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// Se TAG informada, fazer testes
if (!empty($tagName)) {
    echo "<div class='card'>
        <h3>📊 Testes para TAG: <code>{$tagName}</code></h3>";
    
    // ========================================
    // TESTE 1: Verificar se TAG existe
    // ========================================
    echo "<h4>1️⃣ Verificando se TAG existe no Historiador...</h4>";
    try {
        $sqlVerifica = "SELECT TagName, Description 
                        FROM [HISTORIADOR_CCO].Runtime.dbo.Tag 
                        WHERE TagName = :tagName";
        $stmtVerifica = $pdoSIMP->prepare($sqlVerifica);
        $stmtVerifica->execute([':tagName' => $tagName]);
        $tagInfo = $stmtVerifica->fetch(PDO::FETCH_ASSOC);
        
        if ($tagInfo) {
            echo "<p class='success'>✅ TAG encontrada!</p>";
            echo "<p>Description: <strong>{$tagInfo['Description']}</strong></p>";
        } else {
            echo "<p class='error'>❌ TAG não encontrada na tabela Tag do Historiador</p>";
            
            // Buscar TAGs similares
            echo "<p>Buscando TAGs similares...</p>";
            $partes = explode('_', $tagName);
            $busca = '%' . ($partes[0] ?? $tagName) . '%';
            
            $sqlSimilar = "SELECT TOP 10 TagName, Description 
                           FROM [HISTORIADOR_CCO].Runtime.dbo.Tag 
                           WHERE TagName LIKE :busca
                           ORDER BY TagName";
            $stmtSimilar = $pdoSIMP->prepare($sqlSimilar);
            $stmtSimilar->execute([':busca' => $busca]);
            $similares = $stmtSimilar->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($similares) > 0) {
                echo "<table><tr><th>TAGs similares</th><th>Description</th></tr>";
                foreach ($similares as $s) {
                    echo "<tr><td><a class='tag-link' href='?tag={$s['TagName']}&data={$data}'>{$s['TagName']}</a></td><td>{$s['Description']}</td></tr>";
                }
                echo "</table>";
            }
        }
    } catch (Exception $e) {
        echo "<p class='error'>Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // ========================================
    // TESTE 2: Buscar últimos registros (sem filtro de data)
    // ========================================
    echo "<h4>2️⃣ Últimos 10 registros desta TAG (qualquer data)...</h4>";
    try {
        $sqlUltimos = "SELECT TOP 10
                        TagName = History.TagName,
                        DateTime = CONVERT(nvarchar, DateTime, 21), 
                        vValue,
                        Quality
                       FROM [HISTORIADOR_CCO].Runtime.dbo.History
                       WHERE TagName = :tagName
                       ORDER BY DateTime DESC";
        $stmtUltimos = $pdoSIMP->prepare($sqlUltimos);
        $stmtUltimos->execute([':tagName' => $tagName]);
        $ultimos = $stmtUltimos->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($ultimos) > 0) {
            echo "<p class='success'>✅ Encontrados " . count($ultimos) . " registros</p>";
            echo "<table><tr><th>DateTime</th><th>vValue</th><th>Quality</th></tr>";
            foreach ($ultimos as $u) {
                $valorClass = ($u['vValue'] == 0) ? "style='color:#ef4444;font-weight:bold;'" : "";
                echo "<tr><td>{$u['DateTime']}</td><td {$valorClass}>{$u['vValue']}</td><td>{$u['Quality']}</td></tr>";
            }
            echo "</table>";
            
            if ($ultimos[0]['vValue'] == 0) {
                echo "<div class='debug'>⚠️ <strong>ATENÇÃO:</strong> Os valores mais recentes são ZERO. Isso pode indicar problema no sensor ou na coleta de dados.</div>";
            }
        } else {
            echo "<p class='warning'>⚠️ Nenhum registro encontrado para esta TAG</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // ========================================
    // TESTE 3: Query com parâmetros Cyclic (como usamos)
    // ========================================
    echo "<h4>3️⃣ Query com wwRetrievalMode='Cyclic' para data {$data}...</h4>";
    
    $dataInicio = $data . ' 00:00:00';
    $dataFim = $data . ' 23:59:59';
    
    try {
        $sqlCyclic = "SELECT TOP 20
                        TagName = History.TagName,
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
                      ORDER BY DateTime";
        
        $stmtCyclic = $pdoSIMP->prepare($sqlCyclic);
        $stmtCyclic->execute([
            ':tagName' => $tagName, 
            ':dataInicio' => $dataInicio, 
            ':dataFim' => $dataFim
        ]);
        $dadosCyclic = $stmtCyclic->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>Registros retornados: <strong>" . count($dadosCyclic) . "</strong></p>";
        
        if (count($dadosCyclic) > 0) {
            // Contar zeros
            $zeros = 0;
            $naoZeros = 0;
            foreach ($dadosCyclic as $d) {
                if ($d['vValue'] == 0) $zeros++;
                else $naoZeros++;
            }
            
            echo "<p>Valores = 0: <strong style='color:" . ($zeros > 0 ? '#ef4444' : '#10b981') . "'>{$zeros}</strong> | ";
            echo "Valores ≠ 0: <strong style='color:#10b981'>{$naoZeros}</strong></p>";
            
            echo "<table><tr><th>DateTime</th><th>vValue</th></tr>";
            foreach (array_slice($dadosCyclic, 0, 20) as $d) {
                $valorClass = ($d['vValue'] == 0) ? "style='color:#ef4444;'" : "style='color:#10b981;font-weight:bold;'";
                echo "<tr><td>{$d['DateTime']}</td><td {$valorClass}>{$d['vValue']}</td></tr>";
            }
            echo "</table>";
            
            if ($zeros > 0 && $naoZeros == 0) {
                echo "<div class='debug'>
                    ⚠️ <strong>TODOS os valores são ZERO!</strong><br>
                    Possíveis causas:<br>
                    - Sensor desligado ou com problema<br>
                    - Ponto de medição sem comunicação<br>
                    - TAG errada (valores estão em outra TAG)<br>
                    - Dados ainda não foram coletados para hoje
                </div>";
            }
        } else {
            echo "<p class='warning'>⚠️ Query não retornou registros</p>";
            echo "<div class='debug'>
                Isso pode significar:<br>
                - Não há dados para a data {$data}<br>
                - Os parâmetros wwRetrievalMode/wwCycleCount não são suportados<br>
                - Tente uma data anterior para verificar
            </div>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // ========================================
    // TESTE 4: Query simples sem parâmetros especiais
    // ========================================
    echo "<h4>4️⃣ Query simples (sem wwRetrievalMode) para comparação...</h4>";
    try {
        $sqlSimples = "SELECT TOP 20
                        TagName,
                        DateTime = CONVERT(nvarchar, DateTime, 21), 
                        vValue
                       FROM [HISTORIADOR_CCO].Runtime.dbo.History
                       WHERE TagName = :tagName
                         AND DateTime >= :dataInicio
                         AND DateTime <= :dataFim
                       ORDER BY DateTime";
        
        $stmtSimples = $pdoSIMP->prepare($sqlSimples);
        $stmtSimples->execute([
            ':tagName' => $tagName, 
            ':dataInicio' => $dataInicio, 
            ':dataFim' => $dataFim
        ]);
        $dadosSimples = $stmtSimples->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>Registros: <strong>" . count($dadosSimples) . "</strong></p>";
        
        if (count($dadosSimples) > 0) {
            echo "<table><tr><th>DateTime</th><th>vValue</th></tr>";
            foreach (array_slice($dadosSimples, 0, 10) as $d) {
                $valorClass = ($d['vValue'] == 0) ? "style='color:#ef4444;'" : "style='color:#10b981;font-weight:bold;'";
                echo "<tr><td>{$d['DateTime']}</td><td {$valorClass}>{$d['vValue']}</td></tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "</div>";
}

echo "</div></body></html>";
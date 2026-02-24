<?php
/**
 * SIMP - Sincronização Flowchart → Relações ML
 * 
 * Compara a topologia do sistema de água (ENTIDADE_NODO + ENTIDADE_NODO_CONEXAO)
 * com as relações da tabela AUX_RELACAO_PONTOS_MEDICAO usadas pelo treinamento ML.
 * 
 * Regras de derivação (baseadas em engenharia hidráulica):
 *   - Vizinhos lógicos: BFS bidirecional que ATRAVESSA nós sem ponto de medição
 *     (ETA, Manancial, Booster sem sensor, junções). Max 4 hops físicos.
 *   - Irmãos lógicos: nós que compartilham ancestral ou descendente comum,
 *     mesmo que o nó intermediário não tenha sensor.
 *   - Tipos permitidos: Macromedidor(1), Pitométrica(2), Pressão(4), Reservatório(6)
 *   - Excluídos: Hidrômetro(8) — micromedição, escala/granularidade incompatível
 * 
 * Ações:
 *   - sync_check:  Compara flowchart × tabela ML, retorna diff (novas/removidas/inalteradas)
 *   - sync_apply:  Aplica as alterações selecionadas na AUX_RELACAO_PONTOS_MEDICAO
 *   - sync_preview: Retorna preview das relações derivadas do flowchart (sem gravar)
 * 
 * @author Bruno - CESAN
 * @version 1.1 — Travessia de nós passantes (sem CD_PONTO_MEDICAO)
 * @date 2026-02
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

/**
 * Retorna JSON limpo e encerra execução.
 * @param array $data Dados para serializar
 */
function retornarJSON_FC($data)
{
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Capturar erros fatais
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Erro PHP: ' . $error['message']
        ], JSON_UNESCAPED_UNICODE);
    }
});

try {
    // ========================================
    // Conexão e autenticação
    // ========================================
    @include_once __DIR__ . '/../conexao.php';

    if (!isset($pdoSIMP)) {
        retornarJSON_FC(['success' => false, 'error' => 'Conexão com banco não estabelecida']);
    }

    // Receber dados
    $rawInput = file_get_contents('php://input');
    $dados = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $dados = $_GET;
    }

    $acao = $dados['acao'] ?? $_GET['acao'] ?? '';

    if (empty($acao)) {
        retornarJSON_FC([
            'success' => false,
            'error' => 'Parâmetro "acao" obrigatório. Valores: sync_check, sync_apply, sync_preview'
        ]);
    }

    // ========================================
    // Roteamento por ação
    // ========================================
    switch ($acao) {

        // ----------------------------------------
        // SYNC_CHECK — Comparar flowchart com tabela ML
        // ----------------------------------------
        case 'sync_check':
            $cdSistema = isset($dados['cd_sistema']) ? intval($dados['cd_sistema']) : 0;
            $resultado = executarSyncCheck($pdoSIMP, $cdSistema);
            retornarJSON_FC($resultado);
            break;

        // ----------------------------------------
        // SYNC_PREVIEW — Preview das relações derivadas (sem gravar)
        // ----------------------------------------
        case 'sync_preview':
            $cdSistema = isset($dados['cd_sistema']) ? intval($dados['cd_sistema']) : 0;
            $relacoesFlowchart = derivarRelacoesDoFlowchart($pdoSIMP, $cdSistema);
            retornarJSON_FC([
                'success' => true,
                'relacoes_flowchart' => $relacoesFlowchart,
                'total_principais' => count($relacoesFlowchart)
            ]);
            break;

        // ----------------------------------------
        // SYNC_APPLY — Aplicar alterações selecionadas
        // ----------------------------------------
        case 'sync_apply':
            session_start();
            @include_once __DIR__ . '/../verificarAuth.php';
            if (function_exists('podeEditarTela') && !podeEditarTela('Treinamento IA')) {
                retornarJSON_FC(['success' => false, 'error' => 'Permissão negada.']);
            }

            $adicionar = $dados['adicionar'] ?? [];   // [{ tag_principal, tag_auxiliar }]
            $remover   = $dados['remover'] ?? [];      // [{ tag_principal, tag_auxiliar }]
            $resultado = executarSyncApply($pdoSIMP, $adicionar, $remover);
            retornarJSON_FC($resultado);
            break;

        default:
            retornarJSON_FC(['success' => false, 'error' => "Ação desconhecida: $acao"]);
    }

} catch (Exception $e) {
    retornarJSON_FC(['success' => false, 'error' => $e->getMessage()]);
}


// ============================================
// FUNÇÕES PRINCIPAIS
// ============================================

/**
 * Compara relações derivadas do flowchart com a tabela AUX_RELACAO_PONTOS_MEDICAO.
 * Retorna diff categorizado: novas, removidas, inalteradas.
 * 
 * @param PDO $pdo         Conexão PDO com SIMP
 * @param int $cdSistema   CD_CHAVE do nó raiz (0 = todos os sistemas)
 * @return array           Resultado com categorias do diff
 */
function executarSyncCheck(PDO $pdo, int $cdSistema = 0): array
{
    // 1. Derivar relações do flowchart
    $relacoesFlowchart = derivarRelacoesDoFlowchart($pdo, $cdSistema);

    // 2. Buscar relações atuais da tabela ML
    $relacoesML = buscarRelacoesML($pdo);

    // 3. Gerar conjuntos para comparação (pares "principal|auxiliar")
    $paresFlowchart = [];
    foreach ($relacoesFlowchart as $principal => $auxiliares) {
        foreach ($auxiliares as $aux) {
            $paresFlowchart[] = $principal . '|' . $aux;
        }
    }

    $paresML = [];
    foreach ($relacoesML as $principal => $auxiliares) {
        foreach ($auxiliares as $aux) {
            $paresML[] = $principal . '|' . $aux;
        }
    }

    // 4. Calcular diff
    $novas      = array_diff($paresFlowchart, $paresML);     // No flowchart mas não na tabela ML
    $removidas  = array_diff($paresML, $paresFlowchart);       // Na tabela ML mas não no flowchart
    $inalteradas = array_intersect($paresFlowchart, $paresML); // Em ambos

    // 5. Buscar mapa TAG → info do ponto (código formatado, nome, tipo)
    $todasTags = [];
    foreach (array_merge($paresFlowchart, $paresML) as $par) {
        list($p, $a) = explode('|', $par);
        $todasTags[$p] = true;
        $todasTags[$a] = true;
    }
    $mapaTagInfo = buscarInfoPontosPorTag($pdo, array_keys($todasTags));

    // 6. Converter pares de volta para arrays estruturados (com info do ponto)
    $formatarPares = function (array $pares) use ($mapaTagInfo) {
        $resultado = [];
        foreach ($pares as $par) {
            list($principal, $auxiliar) = explode('|', $par);
            $infoPrincipal = $mapaTagInfo[$principal] ?? null;
            $infoAuxiliar  = $mapaTagInfo[$auxiliar] ?? null;
            $resultado[] = [
                'tag_principal'        => $principal,
                'tag_auxiliar'         => $auxiliar,
                'codigo_principal'     => $infoPrincipal['codigo_formatado'] ?? '',
                'nome_principal'       => $infoPrincipal['ds_nome'] ?? '',
                'tipo_principal'       => $infoPrincipal['id_tipo_medidor'] ?? 0,
                'codigo_auxiliar'      => $infoAuxiliar['codigo_formatado'] ?? '',
                'nome_auxiliar'        => $infoAuxiliar['ds_nome'] ?? '',
                'tipo_auxiliar'        => $infoAuxiliar['id_tipo_medidor'] ?? 0,
            ];
        }
        // Ordenar por código do principal, depois do auxiliar
        usort($resultado, function ($a, $b) {
            $cmp = strcmp($a['codigo_principal'], $b['codigo_principal']);
            return $cmp !== 0 ? $cmp : strcmp($a['codigo_auxiliar'], $b['codigo_auxiliar']);
        });
        return $resultado;
    };

    $temDivergencia = count($novas) > 0 || count($removidas) > 0;

    return [
        'success'         => true,
        'tem_divergencia'  => $temDivergencia,
        'novas'           => $formatarPares($novas),
        'removidas'       => $formatarPares($removidas),
        'inalteradas'     => $formatarPares($inalteradas),
        'resumo' => [
            'total_novas'      => count($novas),
            'total_removidas'  => count($removidas),
            'total_inalteradas' => count($inalteradas),
            'total_flowchart'  => count($paresFlowchart),
            'total_ml'         => count($paresML)
        ],
        'cd_sistema' => $cdSistema
    ];
}


/**
 * Aplica as alterações selecionadas na tabela AUX_RELACAO_PONTOS_MEDICAO.
 * 
 * @param PDO   $pdo       Conexão PDO com SIMP
 * @param array $adicionar Array de pares { tag_principal, tag_auxiliar } para inserir
 * @param array $remover   Array de pares { tag_principal, tag_auxiliar } para excluir
 * @return array           Resultado com contagem de operações
 */
function executarSyncApply(PDO $pdo, array $adicionar, array $remover): array
{
    $inseridos = 0;
    $excluidos = 0;
    $erros = [];

    // --- Inserir novas relações ---
    if (!empty($adicionar)) {
        $sqlInsert = "
            INSERT INTO SIMP.dbo.AUX_RELACAO_PONTOS_MEDICAO (TAG_PRINCIPAL, TAG_AUXILIAR, DT_CADASTRO)
            SELECT :tagP, :tagA, GETDATE()
            WHERE NOT EXISTS (
                SELECT 1 FROM SIMP.dbo.AUX_RELACAO_PONTOS_MEDICAO
                WHERE LTRIM(RTRIM(TAG_PRINCIPAL)) = :tagP2
                  AND LTRIM(RTRIM(TAG_AUXILIAR)) = :tagA2
            )
        ";
        $stmtInsert = $pdo->prepare($sqlInsert);

        foreach ($adicionar as $par) {
            $tagP = trim($par['tag_principal'] ?? '');
            $tagA = trim($par['tag_auxiliar'] ?? '');
            if (!$tagP || !$tagA || $tagP === $tagA) continue;

            try {
                $stmtInsert->execute([
                    ':tagP'  => $tagP,
                    ':tagA'  => $tagA,
                    ':tagP2' => $tagP,
                    ':tagA2' => $tagA
                ]);
                $inseridos++;
            } catch (PDOException $e) {
                $erros[] = "Erro ao inserir $tagP → $tagA: " . $e->getMessage();
            }
        }
    }

    // --- Remover relações ---
    if (!empty($remover)) {
        $sqlDelete = "
            DELETE FROM SIMP.dbo.AUX_RELACAO_PONTOS_MEDICAO
            WHERE LTRIM(RTRIM(TAG_PRINCIPAL)) = :tagP
              AND LTRIM(RTRIM(TAG_AUXILIAR)) = :tagA
        ";
        $stmtDelete = $pdo->prepare($sqlDelete);

        foreach ($remover as $par) {
            $tagP = trim($par['tag_principal'] ?? '');
            $tagA = trim($par['tag_auxiliar'] ?? '');
            if (!$tagP || !$tagA) continue;

            try {
                $stmtDelete->execute([':tagP' => $tagP, ':tagA' => $tagA]);
                $excluidos++;
            } catch (PDOException $e) {
                $erros[] = "Erro ao remover $tagP → $tagA: " . $e->getMessage();
            }
        }
    }

    // Log da operação
    try {
        if (function_exists('registrarLog') && isset($_SESSION['cd_usuario'])) {
            registrarLog(
                $pdo,
                'FLOWCHART_SYNC',
                'SINCRONIZACAO',
                "Inseridos: $inseridos, Excluídos: $excluidos",
                empty($erros) ? 'SUCESSO' : 'PARCIAL',
                $_SESSION['cd_usuario']
            );
        }
    } catch (Exception $logEx) {
        // Log silencioso
    }

    return [
        'success'   => empty($erros),
        'inseridos' => $inseridos,
        'excluidos' => $excluidos,
        'erros'     => $erros,
        'message'   => "Sincronização concluída: $inseridos inseridos, $excluidos removidos"
                     . (!empty($erros) ? ' (' . count($erros) . ' erros)' : '')
    ];
}


// ============================================
// FUNÇÕES DE TRAVESSIA DE NÓS PASSANTES (v1.1)
// ============================================

/**
 * Encontra vizinhos "lógicos" de um nó, atravessando nós sem ponto de medição.
 * 
 * Nós passantes (ETA, Manancial, Booster sem sensor, junções) não possuem
 * CD_PONTO_MEDICAO mas são reais na topologia. A busca não para neles —
 * continua caminhando até encontrar nós com ponto de medição.
 * 
 * Exemplo real (Guarapari):
 *   1393 (captação Rio Jabuti) → ETA Guarapari (sem ponto) → RAP ETA 2249 (com ponto)
 *   Sem esta função: 1393 não vê 2249 (ETA bloqueia a 1 hop)
 *   Com esta função:  1393 vê 2249 (ETA é atravessada como nó passante)
 * 
 * @param int   $cdNodoInicial   CD_CHAVE do nó de partida
 * @param array $vizinhos        Grafo de adjacência bidirecional [CD_NODO => [vizinhos]]
 * @param array $mapaNodo        Mapa dos nós que possuem ponto de medição
 * @param int   $maxHops         Máximo de saltos físicos (default 4, evita explosão)
 * @return array                 Lista de CD_NODOs com ponto de medição alcançáveis
 */
function encontrarVizinhosLogicos(int $cdNodoInicial, array $vizinhos, array $mapaNodo, int $maxHops = 4): array
{
    $encontrados = [];   // CD_NODOs com ponto de medição encontrados
    $visitados = [];     // Controle de ciclos (evita loop infinito)
    
    // BFS com controle de profundidade
    // Cada item na fila: [cd_nodo, profundidade_atual]
    $fila = [];
    
    // Iniciar BFS a partir dos vizinhos diretos do nó inicial
    $vizinhosDoNo = $vizinhos[$cdNodoInicial] ?? [];
    foreach ($vizinhosDoNo as $cdVizinho) {
        $fila[] = [$cdVizinho, 1];
    }
    $visitados[$cdNodoInicial] = true; // Não voltar para si mesmo
    
    while (!empty($fila)) {
        list($cdAtual, $profundidade) = array_shift($fila);
        
        // Evitar revisitar nós (grafo pode ter ciclos)
        if (isset($visitados[$cdAtual])) {
            continue;
        }
        $visitados[$cdAtual] = true;
        
        // Se o nó tem ponto de medição → encontrado! Não continua por ele.
        if (isset($mapaNodo[$cdAtual])) {
            $encontrados[] = $cdAtual;
            continue; // Para aqui — já encontrou sensor
        }
        
        // Nó SEM ponto de medição (passante) → continuar caminhando
        if ($profundidade < $maxHops) {
            $vizinhosPassante = $vizinhos[$cdAtual] ?? [];
            foreach ($vizinhosPassante as $cdProximo) {
                if (!isset($visitados[$cdProximo])) {
                    $fila[] = [$cdProximo, $profundidade + 1];
                }
            }
        }
    }
    
    return $encontrados;
}


/**
 * Encontra irmãos "lógicos": nós que compartilham ancestral ou descendente
 * comum através de nós passantes (sem ponto de medição).
 * 
 * Exemplo (Guarapari):
 *   1393 → ETA → RAP ETA, 1394 → ETA → RAP ETA
 *   1393 e 1394 são irmãos (mesmo destino "ETA", mesmo que ETA não tenha sensor)
 * 
 * @param int   $cdNodoInicial  CD_CHAVE do nó de partida
 * @param array $conexoes       Lista de conexões [CD_NODO_ORIGEM, CD_NODO_DESTINO]
 * @param array $vizinhos       Grafo de adjacência bidirecional
 * @param array $mapaNodo       Mapa dos nós com ponto de medição
 * @param int   $maxHops        Máximo de saltos para encontrar nó intermediário
 * @return array                Lista de CD_NODOs irmãos com ponto de medição
 */
function encontrarIrmaosLogicos(int $cdNodoInicial, array $conexoes, array $vizinhos, array $mapaNodo, int $maxHops = 3): array
{
    $irmaos = [];
    
    // --- Coletar nós intermediários (passantes) alcançáveis a partir do nó ---
    // BFS curta só para encontrar nós passantes conectados ao nó inicial
    $nosIntermediarios = [$cdNodoInicial]; // O próprio nó também conta como "origem"
    $visitados = [$cdNodoInicial => true];
    $fila = [];
    
    // Vizinhos diretos sem ponto → são intermediários
    foreach (($vizinhos[$cdNodoInicial] ?? []) as $v) {
        if (!isset($mapaNodo[$v])) {
            $fila[] = [$v, 1];
        }
    }
    
    while (!empty($fila)) {
        list($cdAtual, $prof) = array_shift($fila);
        if (isset($visitados[$cdAtual])) continue;
        $visitados[$cdAtual] = true;
        
        // Só nós passantes (sem ponto) são intermediários
        if (!isset($mapaNodo[$cdAtual])) {
            $nosIntermediarios[] = $cdAtual;
            if ($prof < $maxHops) {
                foreach (($vizinhos[$cdAtual] ?? []) as $prox) {
                    if (!isset($visitados[$prox])) {
                        $fila[] = [$prox, $prof + 1];
                    }
                }
            }
        }
    }
    
    // --- Para cada nó intermediário, buscar quem mais se conecta a ele ---
    foreach ($nosIntermediarios as $cdInterm) {
        foreach ($conexoes as $cx) {
            $dest = intval($cx['CD_NODO_DESTINO']);
            $orig = intval($cx['CD_NODO_ORIGEM']);
            
            // Irmãos por destino comum: quem mais aponta para cdInterm?
            if ($dest === $cdInterm && $orig !== $cdNodoInicial) {
                if (isset($mapaNodo[$orig])) {
                    // Irmão direto com ponto de medição
                    $irmaos[] = $orig;
                } else {
                    // Sem ponto → buscar quem está atrás dele (upstream com ponto)
                    $atras = encontrarVizinhosLogicos($orig, $vizinhos, $mapaNodo, 2);
                    foreach ($atras as $a) {
                        if ($a !== $cdNodoInicial) {
                            $irmaos[] = $a;
                        }
                    }
                }
            }
            
            // Irmãos por origem comum: quem mais recebe de cdInterm?
            if ($orig === $cdInterm && $dest !== $cdNodoInicial) {
                if (isset($mapaNodo[$dest])) {
                    // Irmão direto com ponto de medição
                    $irmaos[] = $dest;
                } else {
                    // Sem ponto → buscar quem está adiante dele (downstream com ponto)
                    $adiante = encontrarVizinhosLogicos($dest, $vizinhos, $mapaNodo, 2);
                    foreach ($adiante as $a) {
                        if ($a !== $cdNodoInicial) {
                            $irmaos[] = $a;
                        }
                    }
                }
            }
        }
    }
    
    return array_unique($irmaos);
}


// ============================================
// FUNÇÕES DE DERIVAÇÃO DO FLOWCHART
// ============================================

/**
 * Deriva relações TAG_PRINCIPAL → [TAG_AUXILIAR] a partir da topologia do flowchart.
 * 
 * Regra hidráulica (vizinhança lógica + irmãos lógicos):
 *   Para cada nó N com ponto de medição (tipo IN 1,2,4,6):
 *     1. Vizinhos lógicos: BFS bidirecional que ATRAVESSA nós sem ponto de medição
 *        (ETA, Manancial, Booster sem sensor, junções). Max 4 hops físicos.
 *     2. Irmãos lógicos: nós que compartilham ancestral ou descendente comum,
 *        mesmo que o nó intermediário não tenha sensor.
 *     3. Filtrar: só nós com CD_PONTO_MEDICAO e tipo permitido (1,2,4,6)
 *     4. Resolver TAG via PONTO_MEDICAO
 * 
 * Exemplo (Guarapari):
 *   1393 (captação Rio Jabuti) → ETA Guarapari (sem ponto) → RAP ETA 2249 (com ponto)
 *   Antes (v1.0): 1393 não via 2249 (ETA bloqueava por não ter CD_PONTO_MEDICAO)
 *   Agora (v1.1): 1393 vê 2249 (ETA é atravessada como nó passante)
 * 
 * @param PDO $pdo        Conexão PDO com SIMP
 * @param int $cdSistema  CD_CHAVE do nó raiz (0 = todos)
 * @return array          Mapa tag_principal => [tag_auxiliar, ...]
 */
function derivarRelacoesDoFlowchart(PDO $pdo, int $cdSistema = 0): array
{
    // --- 1. Buscar todos os nós com ponto de medição e tipos permitidos ---
    $sqlNos = "
        SELECT 
            EN.CD_CHAVE AS CD_NODO,
            EN.DS_NOME AS DS_NODO,
            EN.CD_PONTO_MEDICAO,
            PM.ID_TIPO_MEDIDOR,
            PM.DS_NOME AS DS_PONTO,
            COALESCE(PM.DS_TAG_VAZAO, PM.DS_TAG_PRESSAO, PM.DS_TAG_RESERVATORIO) AS TAG
        FROM SIMP.dbo.ENTIDADE_NODO EN
        INNER JOIN SIMP.dbo.PONTO_MEDICAO PM 
            ON PM.CD_PONTO_MEDICAO = EN.CD_PONTO_MEDICAO
        WHERE EN.OP_ATIVO = 1
          AND EN.CD_PONTO_MEDICAO IS NOT NULL
          AND PM.ID_TIPO_MEDIDOR IN (1, 2, 4, 6)  -- Macro, Pito, Pressão, Reservatório
          AND (PM.DT_DESATIVACAO IS NULL OR PM.DT_DESATIVACAO > GETDATE())
          AND COALESCE(PM.DS_TAG_VAZAO, PM.DS_TAG_PRESSAO, PM.DS_TAG_RESERVATORIO) IS NOT NULL
    ";
    $stmtNos = $pdo->query($sqlNos);
    $nosComPonto = $stmtNos->fetchAll(PDO::FETCH_ASSOC);

    if (empty($nosComPonto)) {
        return [];
    }

    // Mapa CD_NODO → { TAG, CD_PONTO_MEDICAO, ... }
    $mapaNodo = [];
    foreach ($nosComPonto as $no) {
        $tag = trim($no['TAG']);
        if (!$tag) continue;
        $mapaNodo[intval($no['CD_NODO'])] = [
            'tag'              => $tag,
            'cd_ponto_medicao' => intval($no['CD_PONTO_MEDICAO']),
            'tipo_medidor'     => intval($no['ID_TIPO_MEDIDOR']),
            'ds_nodo'          => $no['DS_NODO'],
            'ds_ponto'         => $no['DS_PONTO']
        ];
    }

    $cdNodosValidos = array_keys($mapaNodo);

    // --- 2. Buscar todas as conexões ativas ---
    $sqlConexoes = "
        SELECT CD_NODO_ORIGEM, CD_NODO_DESTINO
        FROM SIMP.dbo.ENTIDADE_NODO_CONEXAO
        WHERE OP_ATIVO = 1
    ";
    $stmtCx = $pdo->query($sqlConexoes);
    $conexoes = $stmtCx->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. Se filtrar por sistema, obter descendentes do nó raiz ---
    $cdNodosFiltrados = null;
    if ($cdSistema > 0) {
        $cdNodosFiltrados = obterDescendentesRecursivo($pdo, $cdSistema, $conexoes);
    }

    // --- 4. Construir grafo de adjacência bidirecional ---
    // Inclui TODOS os nós (com e sem ponto de medição) para permitir travessia
    $vizinhos = [];       // CD_NODO => [CD_NODO vizinhos]

    foreach ($conexoes as $cx) {
        $origem  = intval($cx['CD_NODO_ORIGEM']);
        $destino = intval($cx['CD_NODO_DESTINO']);

        // Bidirecional: cada nó vê o outro como vizinho
        if (!isset($vizinhos[$origem]))  $vizinhos[$origem] = [];
        if (!isset($vizinhos[$destino])) $vizinhos[$destino] = [];

        $vizinhos[$origem][]  = $destino;
        $vizinhos[$destino][] = $origem;
    }

    // --- 5. Para cada nó com ponto, derivar auxiliares (com travessia de nós passantes) ---
    $relacoes = [];

    foreach ($mapaNodo as $cdNodo => $infoNodo) {
        // Se filtrando por sistema, verificar se o nó pertence ao sistema
        if ($cdNodosFiltrados !== null && !in_array($cdNodo, $cdNodosFiltrados)) {
            continue;
        }

        $tagPrincipal = $infoNodo['tag'];
        $auxiliares = [];

        // 5a. Vizinhos lógicos (atravessa nós sem ponto de medição, max 4 hops)
        //     Substitui o antigo "1-hop direto" que parava em ETA/Manancial/Booster
        $vizinhosLogicos = encontrarVizinhosLogicos($cdNodo, $vizinhos, $mapaNodo, 4);
        foreach ($vizinhosLogicos as $cdVizinho) {
            if ($cdVizinho !== $cdNodo && isset($mapaNodo[$cdVizinho])) {
                $auxiliares[] = $mapaNodo[$cdVizinho]['tag'];
            }
        }

        // 5b. Irmãos lógicos (compartilham nó intermediário, mesmo que passante)
        //     Substitui os antigos "irmãos por pai" e "irmãos por destino"
        //     que não enxergavam através de ETA/Manancial sem sensor
        $irmaosLogicos = encontrarIrmaosLogicos($cdNodo, $conexoes, $vizinhos, $mapaNodo, 3);
        foreach ($irmaosLogicos as $cdIrmao) {
            if ($cdIrmao !== $cdNodo && isset($mapaNodo[$cdIrmao])) {
                $auxiliares[] = $mapaNodo[$cdIrmao]['tag'];
            }
        }

        // Deduplicar e remover a própria tag
        $auxiliares = array_unique($auxiliares);
        $auxiliares = array_filter($auxiliares, function ($t) use ($tagPrincipal) {
            return $t !== $tagPrincipal;
        });
        $auxiliares = array_values($auxiliares);
        sort($auxiliares);

        // Só incluir se tem pelo menos 1 auxiliar
        if (!empty($auxiliares)) {
            $relacoes[$tagPrincipal] = $auxiliares;
        }
    }

    // Ordenar por tag principal
    ksort($relacoes);

    return $relacoes;
}


/**
 * Busca relações atuais da tabela AUX_RELACAO_PONTOS_MEDICAO.
 * 
 * @param PDO $pdo Conexão PDO com SIMP
 * @return array   Mapa tag_principal => [tag_auxiliar, ...]
 */
function buscarRelacoesML(PDO $pdo): array
{
    $sql = "
        SELECT 
            LTRIM(RTRIM(TAG_PRINCIPAL)) AS TAG_PRINCIPAL,
            LTRIM(RTRIM(TAG_AUXILIAR)) AS TAG_AUXILIAR
        FROM SIMP.dbo.AUX_RELACAO_PONTOS_MEDICAO
        WHERE LTRIM(RTRIM(TAG_PRINCIPAL)) <> LTRIM(RTRIM(TAG_AUXILIAR))
        ORDER BY TAG_PRINCIPAL, TAG_AUXILIAR
    ";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $relacoes = [];
    foreach ($rows as $row) {
        $principal = $row['TAG_PRINCIPAL'];
        $auxiliar  = $row['TAG_AUXILIAR'];
        if (!isset($relacoes[$principal])) {
            $relacoes[$principal] = [];
        }
        $relacoes[$principal][] = $auxiliar;
    }

    return $relacoes;
}


/**
 * Obtém todos os descendentes de um nó raiz (BFS via conexões + CD_PAI).
 * Usado para filtrar por sistema de abastecimento.
 * 
 * @param PDO   $pdo      Conexão PDO com SIMP
 * @param int   $cdRaiz   CD_CHAVE do nó raiz
 * @param array $conexoes Array de conexões já carregadas
 * @return array           IDs de todos os nós descendentes (inclui o raiz)
 */
function obterDescendentesRecursivo(PDO $pdo, int $cdRaiz, array $conexoes): array
{
    $ids = [$cdRaiz];
    $fila = [$cdRaiz];

    // Buscar filhos por CD_PAI (hierarquia)
    $sqlFilhos = "SELECT CD_CHAVE FROM SIMP.dbo.ENTIDADE_NODO WHERE OP_ATIVO = 1";
    $stmtFilhos = $pdo->query($sqlFilhos);
    $todosNos = $stmtFilhos->fetchAll(PDO::FETCH_ASSOC);

    // Mapa CD_PAI → [filhos]
    $sqlPais = "SELECT CD_CHAVE, CD_PAI FROM SIMP.dbo.ENTIDADE_NODO WHERE OP_ATIVO = 1 AND CD_PAI IS NOT NULL";
    $stmtPais = $pdo->query($sqlPais);
    $mapaPais = [];
    while ($row = $stmtPais->fetch(PDO::FETCH_ASSOC)) {
        $pai = intval($row['CD_PAI']);
        if (!isset($mapaPais[$pai])) $mapaPais[$pai] = [];
        $mapaPais[$pai][] = intval($row['CD_CHAVE']);
    }

    // BFS
    while (!empty($fila)) {
        $atual = array_shift($fila);

        // 1. Filhos por conexão (setas no canvas)
        foreach ($conexoes as $cx) {
            $destino = intval($cx['CD_NODO_DESTINO']);
            if (intval($cx['CD_NODO_ORIGEM']) === $atual && !in_array($destino, $ids)) {
                $ids[] = $destino;
                $fila[] = $destino;
            }
        }

        // 2. Filhos por CD_PAI (hierarquia da árvore)
        if (isset($mapaPais[$atual])) {
            foreach ($mapaPais[$atual] as $filho) {
                if (!in_array($filho, $ids)) {
                    $ids[] = $filho;
                    $fila[] = $filho;
                }
            }
        }
    }

    return $ids;
}


/**
 * Busca informações dos pontos de medição a partir de uma lista de TAGs.
 * Retorna código formatado no padrão SIMP: LOCALIDADE-CD_PONTO(6 dígitos)-LETRA-UNIDADE
 * Exemplo: 5200-000888-E-4
 * 
 * @param PDO   $pdo  Conexão PDO com SIMP
 * @param array $tags Lista de TAGs para buscar
 * @return array      Mapa TAG => { codigo_formatado, cd_ponto_medicao, ds_nome, id_tipo_medidor, ... }
 */
function buscarInfoPontosPorTag(PDO $pdo, array $tags): array
{
    if (empty($tags)) return [];

    // Letras por tipo de medidor (padrão SIMP)
    $letrasTipo = [1 => 'M', 2 => 'E', 4 => 'P', 6 => 'R', 8 => 'H'];

    $sql = "
        SELECT 
            PM.CD_PONTO_MEDICAO,
            PM.DS_NOME,
            PM.ID_TIPO_MEDIDOR,
            PM.DS_TAG_VAZAO,
            PM.DS_TAG_PRESSAO,
            PM.DS_TAG_RESERVATORIO,
            L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
            L.DS_NOME AS DS_LOCALIDADE,
            L.CD_UNIDADE,
            U.DS_NOME AS DS_UNIDADE
        FROM SIMP.dbo.PONTO_MEDICAO PM
        LEFT JOIN SIMP.dbo.LOCALIDADE L ON L.CD_CHAVE = PM.CD_LOCALIDADE
        LEFT JOIN SIMP.dbo.UNIDADE U ON U.CD_UNIDADE = L.CD_UNIDADE
        WHERE PM.DT_DESATIVACAO IS NULL
          AND (
              PM.DS_TAG_VAZAO IS NOT NULL OR
              PM.DS_TAG_PRESSAO IS NOT NULL OR
              PM.DS_TAG_RESERVATORIO IS NOT NULL
          )
    ";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Montar mapa TAG → info
    $mapa = [];
    foreach ($rows as $row) {
        $cdPonto     = intval($row['CD_PONTO_MEDICAO']);
        $tipoMedidor = intval($row['ID_TIPO_MEDIDOR']);
        $letra       = $letrasTipo[$tipoMedidor] ?? 'X';
        $cdLoc       = $row['CD_LOCALIDADE_CODIGO'] ?? '000';
        $cdUnidade   = $row['CD_UNIDADE'] ?? '00';

        // Código formatado: LOCALIDADE-CD_PONTO(6 dígitos)-LETRA-UNIDADE
        $codigoFormatado = $cdLoc . '-' . str_pad($cdPonto, 6, '0', STR_PAD_LEFT) . '-' . $letra . '-' . $cdUnidade;

        $info = [
            'cd_ponto_medicao' => $cdPonto,
            'ds_nome'          => $row['DS_NOME'],
            'id_tipo_medidor'  => $tipoMedidor,
            'letra_tipo'       => $letra,
            'cd_localidade'    => $cdLoc,
            'ds_localidade'    => $row['DS_LOCALIDADE'],
            'cd_unidade'       => $cdUnidade,
            'ds_unidade'       => $row['DS_UNIDADE'],
            'codigo_formatado' => $codigoFormatado
        ];

        // Mapear cada TAG presente nesse ponto
        $tagVazao = trim($row['DS_TAG_VAZAO'] ?? '');
        $tagPressao = trim($row['DS_TAG_PRESSAO'] ?? '');
        $tagReserv = trim($row['DS_TAG_RESERVATORIO'] ?? '');

        if ($tagVazao && in_array($tagVazao, $tags))    $mapa[$tagVazao] = $info;
        if ($tagPressao && in_array($tagPressao, $tags)) $mapa[$tagPressao] = $info;
        if ($tagReserv && in_array($tagReserv, $tags))   $mapa[$tagReserv] = $info;
    }

    return $mapa;
}
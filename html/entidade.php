<?php
/**
 * SIMP - Gerenciamento de Unidades Operacionais
 * Estrutura hierárquica: ENTIDADE_TIPO -> ENTIDADE_VALOR -> ENTIDADE_VALOR_ITEM
 */

include_once 'includes/header.inc.php';
include_once 'bd/conexao.php';

// Agora verificar permissão
// Recarregar permissões do banco (garante que estão atualizadas)
recarregarPermissoesUsuario();

// Agora verificar permissão
exigePermissaoTela('Cadastro de Entidade', ACESSO_LEITURA);

// Permissão do usuário para este módulo
$podeEditar = podeEditarTela('Cadastro de Entidade');

include_once 'includes/menu.inc.php';

// Inicializa arrays
$entidades = [];
$pontosMedicao = [];
$totalTipos = 0;
$totalValores = 0;
$totalItens = 0;

// Buscar favoritos do usuário logado
$favoritos = [];
try {
    $cdUsuarioLogado = isset($_SESSION['cd_usuario']) ? (int) $_SESSION['cd_usuario'] : 0;
    if ($cdUsuarioLogado > 0) {
        $sqlFav = "SELECT CD_ENTIDADE_VALOR FROM SIMP.dbo.ENTIDADE_VALOR_FAVORITO WHERE CD_USUARIO = :cdUsuario";
        $stmtFav = $pdoSIMP->prepare($sqlFav);
        $stmtFav->execute([':cdUsuario' => $cdUsuarioLogado]);
        while ($rowFav = $stmtFav->fetch(PDO::FETCH_ASSOC)) {
            $favoritos[] = (int) $rowFav['CD_ENTIDADE_VALOR'];
        }
    }
} catch (Exception $e) {
    // Tabela pode não existir ainda
    $favoritos = [];
}

$erroMsg = '';

// Buscar dados hierárquicos
// Verificar se coluna NR_ORDEM existe
$temNrOrdem = false;
try {
    $checkCol = $pdoSIMP->query("SELECT TOP 1 NR_ORDEM FROM SIMP.dbo.ENTIDADE_VALOR_ITEM");
    $temNrOrdem = true;
} catch (Exception $e) {
    $temNrOrdem = false;
}

try {
    if ($temNrOrdem) {
        $sql = "
            SELECT 
                ET.CD_CHAVE AS TIPO_CD,
                ET.DS_NOME AS TIPO_NOME,
                ET.CD_ENTIDADE_TIPO_ID AS TIPO_ID,
                ET.DESCARTE AS TIPO_DESCARTE,
                ET.DT_EXC_ENTIDADE_TIPO AS TIPO_DT_EXC,
                EV.CD_CHAVE AS VALOR_CD,
                EV.DS_NOME AS VALOR_NOME,
                EV.CD_ENTIDADE_VALOR_ID AS VALOR_ID,
                EV.ID_FLUXO,
                EVI.CD_CHAVE AS ITEM_CD,
                EVI.CD_PONTO_MEDICAO AS ITEM_PONTO,
                EVI.DT_INICIO AS ITEM_DT_INICIO,
                EVI.DT_FIM AS ITEM_DT_FIM,
                EVI.ID_OPERACAO AS ITEM_OPERACAO,
                EVI.NR_ORDEM AS ITEM_ORDEM,
                PM.DS_NOME AS PONTO_NOME,
                PM.ID_TIPO_MEDIDOR,
                PM.DS_TAG_VAZAO,
                PM.DS_TAG_PRESSAO,
                PM.DS_TAG_VOLUME,
                PM.DS_TAG_RESERVATORIO,
                PM.DS_TAG_TEMP_AGUA,
                PM.DS_TAG_TEMP_AMBIENTE,
                L.CD_LOCALIDADE,
                L.CD_UNIDADE
            FROM SIMP.dbo.ENTIDADE_TIPO ET
            LEFT JOIN SIMP.dbo.ENTIDADE_VALOR EV ON EV.CD_ENTIDADE_TIPO = ET.CD_CHAVE
            LEFT JOIN SIMP.dbo.ENTIDADE_VALOR_ITEM EVI ON EVI.CD_ENTIDADE_VALOR = EV.CD_CHAVE
            LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = EVI.CD_PONTO_MEDICAO
            LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            ORDER BY ET.DS_NOME, EV.DS_NOME, ISNULL(EVI.NR_ORDEM, 999999), PM.DS_NOME
        ";
    } else {
        $sql = "
            SELECT 
                ET.CD_CHAVE AS TIPO_CD,
                ET.DS_NOME AS TIPO_NOME,
                ET.CD_ENTIDADE_TIPO_ID AS TIPO_ID,
                ET.DESCARTE AS TIPO_DESCARTE,
                ET.DT_EXC_ENTIDADE_TIPO AS TIPO_DT_EXC,
                EV.CD_CHAVE AS VALOR_CD,
                EV.DS_NOME AS VALOR_NOME,
                EV.CD_ENTIDADE_VALOR_ID AS VALOR_ID,
                EV.ID_FLUXO,
                EVI.CD_CHAVE AS ITEM_CD,
                EVI.CD_PONTO_MEDICAO AS ITEM_PONTO,
                EVI.DT_INICIO AS ITEM_DT_INICIO,
                EVI.DT_FIM AS ITEM_DT_FIM,
                EVI.ID_OPERACAO AS ITEM_OPERACAO,
                NULL AS ITEM_ORDEM,
                PM.DS_NOME AS PONTO_NOME,
                PM.ID_TIPO_MEDIDOR,
                PM.DS_TAG_VAZAO,
                PM.DS_TAG_PRESSAO,
                PM.DS_TAG_VOLUME,
                PM.DS_TAG_RESERVATORIO,
                PM.DS_TAG_TEMP_AGUA,
                PM.DS_TAG_TEMP_AMBIENTE,
                L.CD_LOCALIDADE,
                L.CD_UNIDADE
            FROM SIMP.dbo.ENTIDADE_TIPO ET
            LEFT JOIN SIMP.dbo.ENTIDADE_VALOR EV ON EV.CD_ENTIDADE_TIPO = ET.CD_CHAVE
            LEFT JOIN SIMP.dbo.ENTIDADE_VALOR_ITEM EVI ON EVI.CD_ENTIDADE_VALOR = EV.CD_CHAVE
            LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = EVI.CD_PONTO_MEDICAO
            LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            ORDER BY ET.DS_NOME, EV.DS_NOME, PM.DS_NOME
        ";
    }

    $queryEntidades = $pdoSIMP->query($sql);

    if ($queryEntidades === false) {
        throw new Exception("Erro ao executar query de entidades");
    }

    $entidadesTemp = [];
    while ($row = $queryEntidades->fetch(PDO::FETCH_ASSOC)) {
        $tipoId = $row['TIPO_CD'];
        $valorId = $row['VALOR_CD'];

        if (!isset($entidadesTemp[$tipoId])) {
            $entidadesTemp[$tipoId] = [
                'cd' => $tipoId,
                'nome' => $row['TIPO_NOME'],
                'id' => $row['TIPO_ID'],
                'descarte' => !empty($row['TIPO_DESCARTE']),
                'dtExc' => $row['TIPO_DT_EXC'],
                'valores' => []
            ];
        }

        if ($valorId && !isset($entidadesTemp[$tipoId]['valores'][$valorId])) {
            $entidadesTemp[$tipoId]['valores'][$valorId] = [
                'cd' => $valorId,
                'nome' => $row['VALOR_NOME'],
                'id' => $row['VALOR_ID'],
                'fluxo' => $row['ID_FLUXO'],
                'itens' => []
            ];
        }

        if ($row['ITEM_CD'] && $valorId && isset($entidadesTemp[$tipoId]['valores'][$valorId])) {
            // Gerar código do ponto formatado
            $letrasTipo = [1 => 'M', 2 => 'E', 4 => 'P', 6 => 'R', 8 => 'H'];
            $letraTipo = $letrasTipo[$row['ID_TIPO_MEDIDOR']] ?? 'X';
            $codigoPonto = $row['CD_LOCALIDADE'] . '-' .
                str_pad($row['ITEM_PONTO'], 6, '0', STR_PAD_LEFT) . '-' .
                $letraTipo . '-' .
                $row['CD_UNIDADE'];

            $entidadesTemp[$tipoId]['valores'][$valorId]['itens'][] = [
                'cd' => $row['ITEM_CD'],
                'cdPonto' => $row['ITEM_PONTO'],
                'pontoNome' => $row['PONTO_NOME'],
                'pontoCodigo' => $codigoPonto,
                'dtInicio' => $row['ITEM_DT_INICIO'],
                'dtFim' => $row['ITEM_DT_FIM'],
                'operacao' => $row['ITEM_OPERACAO'],
                'ordem' => $row['ITEM_ORDEM'],
                'tagVazao' => $row['DS_TAG_VAZAO'] ?? '',
                'tagPressao' => $row['DS_TAG_PRESSAO'] ?? '',
                'tagVolume' => $row['DS_TAG_VOLUME'] ?? '',
                'tagReservatorio' => $row['DS_TAG_RESERVATORIO'] ?? '',
                'tagTempAgua' => $row['DS_TAG_TEMP_AGUA'] ?? '',
                'tagTempAmbiente' => $row['DS_TAG_TEMP_AMBIENTE'] ?? ''
            ];
        }
    }

    // Converte para array indexado
    $entidades = array_values($entidadesTemp);

    // Ordenar alfabeticamente
    usort($entidades, function ($a, $b) {
        return strcasecmp($a['nome'], $b['nome']);
    });

    // Ordena valores dentro de cada tipo
    foreach ($entidades as &$tipo) {
        $tipo['valores'] = array_values($tipo['valores']);
        usort($tipo['valores'], function ($a, $b) {
            return strcasecmp($a['nome'], $b['nome']);
        });
    }
    unset($tipo);

    // Contadores
    $totalTipos = 0;
    foreach ($entidades as $t) {
        if (empty($t['dtExc']))
            $totalTipos++; // Conta apenas os ativos
        $totalValores += count($t['valores']);
        foreach ($t['valores'] as $v) {
            $totalItens += count($v['itens']);
        }
    }

} catch (Exception $e) {
    $erroMsg = $e->getMessage();
    $entidades = [];
}
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- css da tela -->
<link rel="stylesheet" href="style/css/entidade.css">

<div class="entidade-container">
    <!-- Page Header -->
    <div class="entidade-header">
        <div class="entidade-header-content">
            <div class="entidade-header-icon">
                <ion-icon name="layers-outline"></ion-icon>
            </div>
            <div>
                <h1>Gerenciamento de Unidades Operacionais</h1>
                <p>Gerencie tipos, valores e itens de unidades operacionais do sistema</p>
            </div>
        </div>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar">
        <div class="stat-card">
            <div class="stat-icon tipo">
                <ion-icon name="folder-outline"></ion-icon>
            </div>
            <div class="stat-info">
                <h3><?= $totalTipos ?></h3>
                <p>Tipos Ativos</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon valor">
                <ion-icon name="albums-outline"></ion-icon>
            </div>
            <div class="stat-info">
                <h3><?= $totalValores ?></h3>
                <p>Unidades Operacionais</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon item">
                <ion-icon name="location-outline"></ion-icon>
            </div>
            <div class="stat-info">
                <h3><?= $totalItens ?></h3>
                <p>Pontos Vinculados</p>
            </div>
        </div>
    </div>

    <!-- Área de Filtros -->
    <div class="filtros-container">
        <div class="filtro-busca">
            <ion-icon name="search-outline"></ion-icon>
            <input type="text" id="filtroBusca" class="form-control"
                placeholder="Buscar por tipo, valor, ponto de medição ou TAG...">
            <button type="button" id="btnLimparBusca" class="btn-limpar-busca" style="display: none;"
                onclick="limparFiltros()">
                <ion-icon name="close-circle"></ion-icon>
            </button>
        </div>
        <div class="filtro-descarte">
            <span class="filtro-label">Descarte:</span>
            <div class="radio-group-filtro">
                <label class="radio-filtro">
                    <input type="radio" name="filtroDescarte" value="todos" checked>
                    <span>Todos</span>
                </label>
                <label class="radio-filtro">
                    <input type="radio" name="filtroDescarte" value="sim">
                    <span>Sim</span>
                </label>
                <label class="radio-filtro">
                    <input type="radio" name="filtroDescarte" value="nao">
                    <span>Não</span>
                </label>
            </div>
        </div>
        <div class="filtro-favoritos">
            <span class="filtro-label">Favoritos:</span>
            <div class="radio-group-filtro">
                <label class="radio-filtro">
                    <input type="radio" name="filtroFavoritos" value="todos" checked>
                    <span>Todos</span>
                </label>
                <label class="radio-filtro favorito">
                    <input type="radio" name="filtroFavoritos" value="favoritos">
                    <span><ion-icon name="star"></ion-icon> Favoritos</span>
                </label>
            </div>
        </div>
        <div class="filtro-acoes">
            <button type="button" class="btn-acao-filtro" id="btnToggleAcordeoes" onclick="toggleTodosAcordeoes()"
                title="Expandir/Recolher todos">
                <ion-icon name="expand-outline" id="iconToggleAcordeoes"></ion-icon>
                <span id="textToggleAcordeoes">Expandir todos</span>
            </button>
        </div>
        <div class="filtro-resultado" id="filtroResultado" style="display: none;">
            <span id="filtroContagem"></span>
            <button type="button" class="btn-limpar-filtro" onclick="limparFiltros()">
                <ion-icon name="close-outline"></ion-icon> Limpar filtros
            </button>
        </div>
    </div>

    <?php if ($podeEditar): ?>
        <!-- Add Tipo Button -->
        <button class="add-tipo-btn" onclick="abrirModalTipo()">
            <ion-icon name="add-circle-outline"></ion-icon>
            Adicionar Novo Tipo de Unidade Operacional
        </button>
    <?php endif; ?>

    <!-- Tipo Cards -->
    <?php if (empty($entidades)): ?>
        <div class="empty-state">
            <ion-icon name="layers-outline"></ion-icon>
            <h3>Nenhuma entidade cadastrada</h3>
            <p>Clique no botão acima para adicionar o primeiro tipo de unidade operacional</p>
        </div>
    <?php else: ?>
        <?php foreach ($entidades as $tipo):
            $isInativo = !empty($tipo['dtExc']);
            ?>
            <div class="tipo-card <?= $tipo['descarte'] ? 'descarte' : '' ?> <?= $isInativo ? 'inativo' : '' ?>"
                id="tipo-<?= $tipo['cd'] ?>" data-tipo-nome="<?= htmlspecialchars(strtolower($tipo['nome'])) ?>"
                data-tipo-id="<?= htmlspecialchars(strtolower($tipo['id'] ?? '')) ?>"
                data-descarte="<?= $tipo['descarte'] ? '1' : '0' ?>">
                <!-- Tipo Header -->
                <div class="tipo-card-header" onclick="toggleTipo(<?= $tipo['cd'] ?>)">
                    <div class="tipo-card-header-left">
                        <div class="tipo-icon">
                            <ion-icon name="folder-outline"></ion-icon>
                        </div>
                        <div class="tipo-info">
                            <h3>
                                <?= htmlspecialchars($tipo['nome']) ?>
                                <?php if ($tipo['descarte']): ?>
                                    <span class="status-badge descarte">Descarte</span>
                                <?php endif; ?>
                                <?php if ($isInativo): ?>
                                    <span class="status-badge inativo">Inativo</span>
                                <?php endif; ?>
                            </h3>
                            <span>ID: <?= htmlspecialchars($tipo['id'] ?? '-') ?></span>
                        </div>
                    </div>
                    <div class="tipo-card-header-right">
                        <span class="badge-count"><?= count($tipo['valores']) ?> Unidades Operacionais</span>
                        <?php if ($podeEditar): ?>
                            <div class="header-actions" onclick="event.stopPropagation();">
                                <button class="btn-action add" onclick="abrirModalValor(null, '', '', <?= $tipo['cd'] ?>)"
                                    title="Adicionar Unidade Operacional">
                                    <ion-icon name="add-outline"></ion-icon>
                                </button>
                                <button class="btn-action edit"
                                    onclick="abrirModalTipo(<?= $tipo['cd'] ?>, <?= htmlspecialchars(json_encode($tipo['nome']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($tipo['id'] ?? ''), ENT_QUOTES) ?>, <?= $tipo['descarte'] ? '1' : '0' ?>)"
                                    title="Editar">
                                    <ion-icon name="pencil-outline"></ion-icon>
                                </button>
                                <?php if ($isInativo): ?>
                                    <button class="btn-action restore" onclick="alterarStatusTipo(<?= $tipo['cd'] ?>, 'ativar')"
                                        title="Ativar">
                                        <ion-icon name="checkmark-circle-outline"></ion-icon>
                                    </button>
                                <?php else: ?>
                                    <button class="btn-action delete" onclick="alterarStatusTipo(<?= $tipo['cd'] ?>, 'desativar')"
                                        title="Desativar">
                                        <ion-icon name="trash-outline"></ion-icon>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="expand-icon">
                            <ion-icon name="chevron-down-outline"></ion-icon>
                        </div>
                    </div>
                </div>

                <!-- Tipo Body (Valores) -->
                <div class="tipo-card-body">
                    <?php if (empty($tipo['valores'])): ?>
                        <div class="empty-state" style="padding: 32px;">
                            <ion-icon name="albums-outline"></ion-icon>
                            <h3>Nenhum valor cadastrado</h3>
                            <p>Adicione unidades operacionais a este tipo</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($tipo['valores'] as $valor): ?>
                            <div class="valor-card" id="valor-<?= $valor['cd'] ?>"
                                data-valor-nome="<?= htmlspecialchars(strtolower($valor['nome'])) ?>"
                                data-valor-id="<?= htmlspecialchars(strtolower($valor['id'] ?? '')) ?>"
                                data-favorito="<?= in_array($valor['cd'], $favoritos) ? '1' : '0' ?>">
                                <!-- Valor Header -->
                                <?php
                                // Mapeamento de fluxo
                                $fluxosTexto = [
                                    1 => 'Entrada',
                                    2 => 'Saída',
                                    3 => 'Municipal',
                                    4 => 'Não se Aplica'
                                ];
                                $fluxoTexto = $fluxosTexto[$valor['fluxo']] ?? '-';
                                ?>
                                <div class="valor-card-header" onclick="toggleValor(<?= $valor['cd'] ?>)">
                                    <div class="valor-card-header-left">
                                        <div class="valor-icon">
                                            <ion-icon name="albums-outline"></ion-icon>
                                        </div>
                                        <div class="valor-info">
                                            <h4><?= htmlspecialchars($valor['nome']) ?></h4>
                                            <div class="valor-meta">
                                                <span class="valor-id">ID: <?= htmlspecialchars($valor['id'] ?? '-') ?></span>
                                                <span class="fluxo-badge fluxo-<?= $valor['fluxo'] ?? 0 ?>"><?= $fluxoTexto ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="valor-card-header-right">
                                        <span class="valor-badge-count"><?= count($valor['itens']) ?> Ponto(s) de Medição</span>
                                        <div class="header-actions" onclick="event.stopPropagation();">
                                            <button class="btn-action favorito <?= in_array($valor['cd'], $favoritos) ? 'ativo' : '' ?>"
                                                onclick="toggleFavorito(<?= $valor['cd'] ?>, this)"
                                                title="<?= in_array($valor['cd'], $favoritos) ? 'Remover dos favoritos' : 'Adicionar aos favoritos' ?>">
                                                <ion-icon
                                                    name="<?= in_array($valor['cd'], $favoritos) ? 'star' : 'star-outline' ?>"></ion-icon>
                                            </button>
                                            <button class="btn-action chart"
                                                onclick="abrirGraficoValor(<?= $tipo['cd'] ?>, <?= $valor['cd'] ?>, '<?= $valor['id'] ?? '' ?>')"
                                                title="Ver Gráfico">
                                                <ion-icon name="stats-chart-outline"></ion-icon>
                                            </button>
                                            <?php if ($podeEditar): ?>
                                                <button class="btn-action add"
                                                    onclick="abrirModalItem(null, null, '', '', <?= $valor['cd'] ?>)"
                                                    title="Vincular Ponto">
                                                    <ion-icon name="add-outline"></ion-icon>
                                                </button>
                                                <button class="btn-action edit"
                                                    onclick="abrirModalValor(<?= $valor['cd'] ?>, <?= htmlspecialchars(json_encode($valor['nome']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($valor['id'] ?? ''), ENT_QUOTES) ?>, <?= $tipo['cd'] ?>, '<?= $valor['fluxo'] ?? '' ?>')"
                                                    title="Editar">
                                                    <ion-icon name="pencil-outline"></ion-icon>
                                                </button>
                                                <button class="btn-action delete" onclick="excluirValor(<?= $valor['cd'] ?>)"
                                                    title="Excluir">
                                                    <ion-icon name="trash-outline"></ion-icon>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="valor-expand-icon">
                                            <ion-icon name="chevron-down-outline"></ion-icon>
                                        </div>
                                    </div>
                                </div>

                                <!-- Valor Body (Itens) -->
                                <div class="valor-card-body">
                                    <?php if (empty($valor['itens'])): ?>
                                        <div class="empty-state" style="padding: 24px;">
                                            <ion-icon name="location-outline"></ion-icon>
                                            <h3>Nenhum ponto vinculado</h3>
                                            <p>Adicione pontos de medição a esta unidade operacional</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="itens-sortable" data-valor-cd="<?= $valor['cd'] ?>">
                                            <?php foreach ($valor['itens'] as $item):
                                                // Usar DateTime para evitar problema do Y2K38 com strtotime
                                                $expirado = false;
                                                if (!empty($item['dtFim'])) {
                                                    try {
                                                        $dtFimObj = new DateTime($item['dtFim']);
                                                        $hoje = new DateTime();
                                                        $expirado = $dtFimObj < $hoje;
                                                    } catch (Exception $e) {
                                                        $expirado = false;
                                                    }
                                                }
                                                $pontoLabel = ($item['pontoCodigo'] ?? '') . ' ' . ($item['pontoNome'] ?? '');
                                                $operacaoAtual = $item['operacao'];
                                                ?>
                                                <div class="item-row <?= $expirado ? 'expired' : '' ?>" data-item-cd="<?= $item['cd'] ?>" <?php if ($podeEditar && $temNrOrdem): ?>draggable="true" <?php endif; ?>
                                                    data-ponto-nome="<?= htmlspecialchars(strtolower($item['pontoNome'] ?? '')) ?>"
                                                    data-ponto-codigo="<?= htmlspecialchars(strtolower($item['pontoCodigo'] ?? '')) ?>"
                                                    data-tag-vazao="<?= htmlspecialchars(strtolower($item['tagVazao'] ?? '')) ?>"
                                                    data-tag-pressao="<?= htmlspecialchars(strtolower($item['tagPressao'] ?? '')) ?>"
                                                    data-tag-volume="<?= htmlspecialchars(strtolower($item['tagVolume'] ?? '')) ?>"
                                                    data-tag-reservatorio="<?= htmlspecialchars(strtolower($item['tagReservatorio'] ?? '')) ?>"
                                                    data-tag-temp-agua="<?= htmlspecialchars(strtolower($item['tagTempAgua'] ?? '')) ?>"
                                                    data-tag-temp-ambiente="<?= htmlspecialchars(strtolower($item['tagTempAmbiente'] ?? '')) ?>">
                                                    <?php if ($podeEditar && $temNrOrdem): ?>
                                                        <div class="item-drag-handle" title="Arraste para reordenar">
                                                            <ion-icon name="reorder-three-outline"></ion-icon>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="item-row-left">
                                                        <div class="item-icons">
                                                            <ion-icon name="location-outline" class="icon-location"></ion-icon>
                                                            <?php if ($operacaoAtual !== null && $operacaoAtual !== ''): ?>
                                                                <ion-icon name="<?= $operacaoAtual == 1 ? 'add-circle' : 'remove-circle' ?>"
                                                                    class="icon-operacao operacao-<?= $operacaoAtual ?>"></ion-icon>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="item-info">
                                                            <h5>
                                                                <strong><?= htmlspecialchars($item['pontoCodigo'] ?? '') ?></strong>
                                                                <?= htmlspecialchars($item['pontoNome'] ?? 'Ponto #' . $item['cdPonto']) ?>
                                                            </h5>
                                                            <span>
                                                                <?php
                                                                $periodo = [];
                                                                if (!empty($item['dtInicio'])) {
                                                                    $periodo[] = 'Início: ' . date('d/m/Y', strtotime($item['dtInicio']));
                                                                }
                                                                if (!empty($item['dtFim'])) {
                                                                    $periodo[] = 'Fim: ' . date('d/m/Y', strtotime($item['dtFim']));
                                                                }
                                                                echo implode(' | ', $periodo) ?: 'Período não definido';
                                                                ?>
                                                            </span>
                                                            <?php
                                                            // Coletar TAGs não vazias
                                                            $tags = [];
                                                            if (!empty($item['tagVazao']))
                                                                $tags[] = 'V: ' . $item['tagVazao'];
                                                            if (!empty($item['tagPressao']))
                                                                $tags[] = 'P: ' . $item['tagPressao'];
                                                            if (!empty($item['tagVolume']))
                                                                $tags[] = 'Vol: ' . $item['tagVolume'];
                                                            if (!empty($item['tagReservatorio']))
                                                                $tags[] = 'R: ' . $item['tagReservatorio'];
                                                            if (!empty($item['tagTempAgua']))
                                                                $tags[] = 'TA: ' . $item['tagTempAgua'];
                                                            if (!empty($item['tagTempAmbiente']))
                                                                $tags[] = 'TAm: ' . $item['tagTempAmbiente'];

                                                            if (!empty($tags)):
                                                                ?>
                                                                <span class="item-tags" title="Tags SCADA configuradas">
                                                                    <ion-icon name="pricetag-outline"></ion-icon>
                                                                    <?= htmlspecialchars(implode(' | ', $tags)) ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="item-row-right">
                                                        <a href="pontoMedicaoView.php?id=<?= $item['cdPonto'] ?>" class="btn-action view"
                                                            title="Ver Cadastro do Ponto">
                                                            <ion-icon name="eye-outline"></ion-icon>
                                                        </a>
                                                        <button class="btn-action chart"
                                                            onclick="abrirGraficoPonto(<?= $tipo['cd'] ?>, <?= $valor['cd'] ?>, '<?= $valor['id'] ?? '' ?>', <?= $item['cdPonto'] ?>)"
                                                            title="Ver Gráfico">
                                                            <ion-icon name="stats-chart-outline"></ion-icon>
                                                        </button>
                                                        <?php if ($podeEditar): ?>
                                                            <button class="btn-action edit"
                                                                onclick="abrirModalItem(<?= $item['cd'] ?>, <?= $item['cdPonto'] ?>, '<?= $item['dtInicio'] ? date('Y-m-d', strtotime($item['dtInicio'])) : '' ?>', '<?= $item['dtFim'] ? date('Y-m-d', strtotime($item['dtFim'])) : '' ?>', <?= $valor['cd'] ?>, <?= htmlspecialchars(json_encode($pontoLabel), ENT_QUOTES) ?>, '<?= $operacaoAtual ?? '' ?>')"
                                                                title="Editar">
                                                                <ion-icon name="pencil-outline"></ion-icon>
                                                            </button>
                                                            <button class="btn-action delete"
                                                                onclick="excluirItem(<?= $item['cd'] ?>, <?= $valor['cd'] ?>)" title="Excluir">
                                                                <ion-icon name="trash-outline"></ion-icon>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div><!-- .itens-sortable -->
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($podeEditar): ?>
    <!-- Modal: Tipo de Unidade Operacional -->
    <div class="modal-overlay" id="modalTipo">
        <div class="modal">
            <div class="modal-header">
                <h3>
                    <ion-icon name="folder-outline"></ion-icon>
                    <span id="modalTipoTitle">Novo Tipo de Unidade Operacional</span>
                </h3>
                <button class="modal-close" onclick="fecharModal('modalTipo')">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>
            <form id="formTipo" onsubmit="salvarTipo(event)">
                <input type="hidden" name="cd" id="inputTipoCd">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">
                            <ion-icon name="text-outline"></ion-icon>
                            Nome <span class="required">*</span>
                        </label>
                        <input type="text" name="nome" id="inputTipoNome" class="form-control" placeholder="Ex: Setor"
                            required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <ion-icon name="finger-print-outline"></ion-icon>
                            Identificador <span class="required">*</span>
                        </label>
                        <input type="text" name="idExterno" id="inputTipoId" class="form-control"
                            placeholder="Identificador externo" required>
                    </div>
                    <div class="form-group" style="display: none;">
                        <label class="form-label">
                            <ion-icon name="swap-horizontal-outline"></ion-icon>
                            Descarte
                        </label>
                        <div class="radio-group-inline">
                            <input type="radio" name="descarte" id="descarteNao" value="0">
                            <label for="descarteNao">Não</label>
                            <input type="radio" name="descarte" id="descarteSim" value="1" checked>
                            <label for="descarteSim">Sim</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="fecharModal('modalTipo')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <ion-icon name="checkmark-outline"></ion-icon>
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Valor de Entidade -->
    <div class="modal-overlay" id="modalValor">
        <div class="modal">
            <div class="modal-header">
                <h3>
                    <ion-icon name="albums-outline"></ion-icon>
                    <span id="modalValorTitle">Nova Unidade Operacional</span>
                </h3>
                <button class="modal-close" onclick="fecharModal('modalValor')">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>
            <form id="formValor" onsubmit="salvarValor(event)">
                <input type="hidden" name="cd" id="inputValorCd">
                <input type="hidden" name="cdTipo" id="inputValorTipo">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">
                            <ion-icon name="text-outline"></ion-icon>
                            Nome <span class="required">*</span>
                        </label>
                        <input type="text" name="nome" id="inputValorNome" class="form-control"
                            placeholder="Ex: Setor Norte" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <ion-icon name="finger-print-outline"></ion-icon>
                            ID Externo <span class="required">*</span>
                        </label>
                        <div class="autocomplete-container">
                            <input type="text" id="inputValorIdText" class="form-control"
                                placeholder="Digite ou selecione um ID existente..." autocomplete="off">
                            <input type="hidden" name="idExterno" id="inputValorId">
                            <div id="inputValorIdDropdown" class="autocomplete-dropdown"></div>
                            <button type="button" id="btnLimparIdExterno" class="btn-limpar-autocomplete"
                                style="display: none;" title="Limpar" onclick="limparIdExterno()">
                                <ion-icon name="close-circle"></ion-icon>
                            </button>
                        </div>
                        <small class="form-hint">Selecione um ID existente ou digite um novo</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <ion-icon name="swap-horizontal-outline"></ion-icon>
                            Fluxo <span class="required">*</span>
                        </label>
                        <select name="fluxo" id="inputValorFluxo" class="form-control" required>
                            <option value="">Selecione...</option>
                            <option value="1">Entrada</option>
                            <option value="2">Saída</option>
                            <option value="3">Municipal</option>
                            <option value="4">Não se Aplica</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="fecharModal('modalValor')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <ion-icon name="checkmark-outline"></ion-icon>
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Item (Ponto de Medição) -->
    <div class="modal-overlay" id="modalItem">
        <div class="modal">
            <div class="modal-header">
                <h3>
                    <ion-icon name="location-outline"></ion-icon>
                    <span id="modalItemTitle">Vincular Ponto de Medição</span>
                </h3>
                <button class="modal-close" onclick="fecharModal('modalItem')">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>
            <form id="formItem" onsubmit="salvarItem(event)">
                <input type="hidden" name="cd" id="inputItemCd">
                <input type="hidden" name="cdValor" id="inputItemValor">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">
                            <ion-icon name="location-outline"></ion-icon>
                            Ponto de Medição <span class="required">*</span>
                        </label>
                        <div class="autocomplete-container">
                            <input type="text" id="inputItemPontoText" class="form-control"
                                placeholder="Clique para selecionar ou digite para filtrar..." autocomplete="off">
                            <input type="hidden" name="cdPonto" id="inputItemPonto" required>
                            <div id="inputItemPontoDropdown" class="autocomplete-dropdown"></div>
                            <button type="button" id="btnLimparPontoItem" class="btn-limpar-autocomplete"
                                style="display: none;" title="Limpar" onclick="limparPontoItem()">
                                <ion-icon name="close-circle"></ion-icon>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <ion-icon name="calendar-outline"></ion-icon>
                            Data Início <span class="required">*</span>
                        </label>
                        <input type="date" name="dtInicio" id="inputItemDtInicio" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <ion-icon name="calendar-outline"></ion-icon>
                            Data Fim <span class="required">*</span>
                        </label>
                        <input type="date" name="dtFim" id="inputItemDtFim" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <ion-icon name="calculator-outline"></ion-icon>
                            Operação <span class="required">*</span>
                        </label>
                        <div class="radio-group-operacao">
                            <label class="radio-operacao radio-mais">
                                <input type="radio" name="operacao" id="operacaoMais" value="1">
                                <span class="radio-label">+</span>
                            </label>
                            <label class="radio-operacao radio-menos">
                                <input type="radio" name="operacao" id="operacaoMenos" value="2">
                                <span class="radio-label">−</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="fecharModal('modalItem')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <ion-icon name="checkmark-outline"></ion-icon>
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<style>
    /* Autocomplete para Ponto de Medição */
    .autocomplete-container {
        position: relative;
        width: 100%;
        box-sizing: border-box;
    }

    .autocomplete-container input.form-control {
        padding-right: 35px;
        width: 100%;
        box-sizing: border-box;
    }

    .autocomplete-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #e2e8f0;
        border-top: none;
        border-radius: 0 0 10px 10px;
        max-height: 250px;
        overflow-y: auto;
        z-index: 1001;
        display: none;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .autocomplete-dropdown.active {
        display: block;
    }

    .autocomplete-item {
        padding: 10px 14px;
        cursor: pointer;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }

    .autocomplete-item:last-child {
        border-bottom: none;
    }

    .autocomplete-item:hover,
    .autocomplete-item.highlighted {
        background-color: #1e3a5f;
        color: white;
    }

    .autocomplete-item .item-code {
        font-family: 'SF Mono', Monaco, monospace;
        font-size: 12px;
        color: #64748b;
    }

    .autocomplete-item:hover .item-code,
    .autocomplete-item.highlighted .item-code {
        color: rgba(255, 255, 255, 0.8);
    }

    .autocomplete-item .item-name {
        display: block;
        margin-top: 2px;
    }

    .autocomplete-loading {
        padding: 12px;
        text-align: center;
        color: #64748b;
        font-size: 13px;
    }

    .autocomplete-empty {
        padding: 12px;
        text-align: center;
        color: #94a3b8;
        font-size: 13px;
    }

    .btn-limpar-autocomplete {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        padding: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-limpar-autocomplete:hover {
        color: #ef4444;
    }

    .btn-limpar-autocomplete ion-icon {
        font-size: 18px;
    }

    .form-hint {
        display: block;
        margin-top: 4px;
        font-size: 11px;
        color: #94a3b8;
        font-style: italic;
    }

    /* Radio Group Operação (+/-) */
    .radio-group-operacao {
        display: flex;
        gap: 12px;
    }

    .radio-operacao {
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .radio-operacao input[type="radio"] {
        display: none;
    }

    .radio-operacao .radio-label {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        border-radius: 8px;
        font-size: 28px;
        font-weight: 700;
        border: 2px solid #e2e8f0;
        background: #f8fafc;
        color: #64748b;
        transition: all 0.2s ease;
    }

    .radio-operacao input[type="radio"]:checked+.radio-label {
        border-color: transparent;
        color: #ffffff;
    }

    .radio-mais input[type="radio"]:checked+.radio-label {
        background: #22c55e;
        box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3);
    }

    .radio-menos input[type="radio"]:checked+.radio-label {
        background: #ef4444;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }

    .radio-operacao:hover .radio-label {
        border-color: #cbd5e1;
        background: #f1f5f9;
    }

    .radio-mais:hover .radio-label {
        border-color: #86efac;
    }

    .radio-menos:hover .radio-label {
        border-color: #fca5a5;
    }
</style>

<script>
    // ============================================
    // Estado dos Acordeões (localStorage)
    // ============================================
    const STORAGE_KEY = 'entidade_expanded_state';
    const STORAGE_KEY_FILTROS = 'entidade_filtros_state';

    function saveExpandedState() {
        const expandedTipos = [];
        const expandedValores = [];

        document.querySelectorAll('.tipo-card.expanded').forEach(card => {
            const id = card.id.replace('tipo-', '');
            expandedTipos.push(id);
        });

        document.querySelectorAll('.valor-card.expanded').forEach(card => {
            const id = card.id.replace('valor-', '');
            expandedValores.push(id);
        });

        localStorage.setItem(STORAGE_KEY, JSON.stringify({
            tipos: expandedTipos,
            valores: expandedValores
        }));
    }

    function restoreExpandedState() {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                const state = JSON.parse(saved);

                if (state.tipos) {
                    state.tipos.forEach(id => {
                        const card = document.getElementById('tipo-' + id);
                        if (card) card.classList.add('expanded');
                    });
                }

                if (state.valores) {
                    state.valores.forEach(id => {
                        const card = document.getElementById('valor-' + id);
                        if (card) card.classList.add('expanded');
                    });
                }
            }
        } catch (e) {
            console.error('Erro ao restaurar estado:', e);
        }
    }

    // ============================================
    // Estado dos Filtros (localStorage)
    // ============================================
    function saveFiltrosState() {
        const busca = document.getElementById('filtroBusca').value;
        const descarte = document.querySelector('input[name="filtroDescarte"]:checked')?.value || 'todos';
        const favoritos = document.querySelector('input[name="filtroFavoritos"]:checked')?.value || 'todos';

        localStorage.setItem(STORAGE_KEY_FILTROS, JSON.stringify({
            busca: busca,
            descarte: descarte
        }));
    }

    function restoreFiltrosState() {
        try {
            const saved = localStorage.getItem(STORAGE_KEY_FILTROS);
            if (saved) {
                const state = JSON.parse(saved);

                if (state.busca) {
                    document.getElementById('filtroBusca').value = state.busca;
                    document.getElementById('btnLimparBusca').style.display = 'flex';
                }

                if (state.descarte) {
                    const radio = document.querySelector(`input[name="filtroDescarte"][value="${state.descarte}"]`);
                    if (radio) radio.checked = true;
                }

                // Aplicar filtros se houver algum salvo
                if (state.busca || state.descarte !== 'todos') {
                    aplicarFiltros();
                }

                if (state.favoritos) {
                    const radio = document.querySelector(`input[name="filtroFavoritos"][value="${state.favoritos}"]`);
                    if (radio) radio.checked = true;
                }
            }
        } catch (e) {
            console.error('Erro ao restaurar filtros:', e);
        }
    }

    function clearFiltrosState() {
        localStorage.removeItem(STORAGE_KEY_FILTROS);
    }

    // Restaurar estado ao carregar a página
    document.addEventListener('DOMContentLoaded', function () {
        restoreExpandedState();
        restoreFiltrosState();
    });

    // ============================================
    // Toggle Acordeão
    // ============================================
    function toggleTipo(id) {
        const card = document.getElementById('tipo-' + id);
        card.classList.toggle('expanded');
        saveExpandedState();
        verificarEstadoAcordeoes();
    }

    function toggleValor(id) {
        event.stopPropagation();
        const card = document.getElementById('valor-' + id);
        card.classList.toggle('expanded');
        saveExpandedState();
        verificarEstadoAcordeoes();
    }

    function toggleTodosAcordeoes() {
        const tipoCards = document.querySelectorAll('.tipo-card:not(.filtro-oculto)');
        const valorCards = document.querySelectorAll('.valor-card:not(.filtro-oculto)');

        // Verifica se a maioria está expandida
        let expandidos = 0;
        let total = 0;

        tipoCards.forEach(card => {
            total++;
            if (card.classList.contains('expanded')) expandidos++;
        });

        valorCards.forEach(card => {
            total++;
            if (card.classList.contains('expanded')) expandidos++;
        });

        const devemExpandir = expandidos < total / 2;

        // Aplica a ação
        tipoCards.forEach(card => {
            if (devemExpandir) {
                card.classList.add('expanded');
            } else {
                card.classList.remove('expanded');
            }
        });

        valorCards.forEach(card => {
            if (devemExpandir) {
                card.classList.add('expanded');
            } else {
                card.classList.remove('expanded');
            }
        });

        // Atualiza o botão (se expandiu, mostrar "Recolher"; se recolheu, mostrar "Expandir")
        atualizarBotaoToggle(devemExpandir);

        // Salva estado
        saveExpandedState();
    }

    function atualizarBotaoToggle(todosExpandidos) {
        const icon = document.getElementById('iconToggleAcordeoes');
        const text = document.getElementById('textToggleAcordeoes');

        if (todosExpandidos) {
            icon.setAttribute('name', 'contract-outline');
            text.textContent = 'Recolher todos';
        } else {
            icon.setAttribute('name', 'expand-outline');
            text.textContent = 'Expandir todos';
        }
    }

    // Atualizar botão ao carregar e quando mudar estado
    function verificarEstadoAcordeoes() {
        const tipoCards = document.querySelectorAll('.tipo-card:not(.filtro-oculto)');
        const valorCards = document.querySelectorAll('.valor-card:not(.filtro-oculto)');

        let expandidos = 0;
        let total = 0;

        tipoCards.forEach(card => {
            total++;
            if (card.classList.contains('expanded')) expandidos++;
        });

        valorCards.forEach(card => {
            total++;
            if (card.classList.contains('expanded')) expandidos++;
        });

        atualizarBotaoToggle(expandidos > total / 2);
    }

    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(verificarEstadoAcordeoes, 100);
    });

    // ============================================
    // Sistema de Filtros
    // ============================================
    let filtroTimeout = null;

    function initFiltros() {
        const inputBusca = document.getElementById('filtroBusca');
        const btnLimpar = document.getElementById('btnLimparBusca');

        // Evento de digitação na busca
        inputBusca.addEventListener('input', function () {
            const termo = this.value.trim();
            btnLimpar.style.display = termo ? 'flex' : 'none';

            clearTimeout(filtroTimeout);
            filtroTimeout = setTimeout(() => {
                aplicarFiltros();
                saveFiltrosState();
            }, 300);
        });

        // Evento de mudança no filtro de descarte
        const radiosDescarte = document.querySelectorAll('input[name="filtroDescarte"]');
        radiosDescarte.forEach(radio => {
            radio.addEventListener('change', function () {
                aplicarFiltros();
                saveFiltrosState();
            });
        });

        // Evento de mudança no filtro de favoritos
        const radiosFavoritos = document.querySelectorAll('input[name="filtroFavoritos"]');
        radiosFavoritos.forEach(radio => {
            radio.addEventListener('change', function () {
                aplicarFiltros();
                saveFiltrosState();
            });
        });
    }

    // Função para normalizar texto removendo acentos
    function normalizarTexto(texto) {
        return texto
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    // ============================================
    // Sistema de Filtros - VERSÃO 3 CORRIGIDA
    // Substituir a função aplicarFiltros() em entidade.php
    // ============================================

    function aplicarFiltros() {
        const termoBusca = normalizarTexto(document.getElementById('filtroBusca').value);
        const filtroDescarte = document.querySelector('input[name="filtroDescarte"]:checked')?.value || 'todos';
        const filtroFavoritos = document.querySelector('input[name="filtroFavoritos"]:checked')?.value || 'todos';

        let totalTiposVisiveis = 0;
        let totalValoresVisiveis = 0;
        let totalItensVisiveis = 0;

        const tipoCards = document.querySelectorAll('.tipo-card');

        // Limpar todos os badges de match anteriores
        document.querySelectorAll('.badge-match').forEach(b => b.remove());
        // Limpar todas as classes de match
        document.querySelectorAll('.filtro-match, .filtro-match-direto').forEach(el => {
            el.classList.remove('filtro-match', 'filtro-match-direto');
        });

        tipoCards.forEach(tipoCard => {
            const tipoNome = normalizarTexto(tipoCard.dataset.tipoNome || '');
            const tipoId = normalizarTexto(tipoCard.dataset.tipoId || '');
            const tipoDescarte = tipoCard.dataset.descarte;

            // Filtro de descarte no tipo
            if (filtroDescarte === 'sim' && tipoDescarte !== '1') {
                tipoCard.classList.add('filtro-oculto');
                return;
            }
            if (filtroDescarte === 'nao' && tipoDescarte === '1') {
                tipoCard.classList.add('filtro-oculto');
                return;
            }

            // Se não há busca textual e não há filtro de favoritos, mostrar tipo e processar valores
            if (!termoBusca && filtroFavoritos === 'todos' && filtroDescarte === 'todos') {
                tipoCard.classList.remove('filtro-oculto');
                totalTiposVisiveis++;

                tipoCard.querySelectorAll('.valor-card').forEach(v => {
                    v.classList.remove('filtro-oculto');
                    totalValoresVisiveis++;
                });
                tipoCard.querySelectorAll('.item-row').forEach(i => {
                    i.classList.remove('filtro-oculto');
                    totalItensVisiveis++;
                });
                return;
            }

            // Verificar se o Tipo dá match direto
            let tipoMatchDireto = termoBusca && (tipoNome.includes(termoBusca) || tipoId.includes(termoBusca));
            let tipoTemFilhoVisivel = false;

            const valorCards = tipoCard.querySelectorAll('.valor-card');
            valorCards.forEach(valorCard => {
                const valorNome = normalizarTexto(valorCard.dataset.valorNome || '');
                const valorId = normalizarTexto(valorCard.dataset.valorId || '');
                const isFavorito = valorCard.dataset.favorito === '1';

                // Filtro de favoritos
                if (filtroFavoritos === 'favoritos' && !isFavorito) {
                    valorCard.classList.add('filtro-oculto');
                    return;
                }

                // Verificar se Valor/Unidade dá match direto
                let valorMatchDireto = termoBusca && (valorNome.includes(termoBusca) || valorId.includes(termoBusca));
                let valorTemFilhoVisivel = false;

                const itemRows = valorCard.querySelectorAll('.item-row');
                itemRows.forEach(itemRow => {
                    const pontoNome = normalizarTexto(itemRow.dataset.pontoNome || '');
                    const pontoCodigo = normalizarTexto(itemRow.dataset.pontoCodigo || '');
                    const tagVazao = normalizarTexto(itemRow.dataset.tagVazao || '');
                    const tagPressao = normalizarTexto(itemRow.dataset.tagPressao || '');
                    const tagVolume = normalizarTexto(itemRow.dataset.tagVolume || '');
                    const tagReservatorio = normalizarTexto(itemRow.dataset.tagReservatorio || '');
                    const tagTempAgua = normalizarTexto(itemRow.dataset.tagTempAgua || '');
                    const tagTempAmbiente = normalizarTexto(itemRow.dataset.tagTempAmbiente || '');

                    // Verificar se Ponto dá match direto
                    let pontoMatchDireto = termoBusca && (
                        pontoNome.includes(termoBusca) ||
                        pontoCodigo.includes(termoBusca) ||
                        tagVazao.includes(termoBusca) ||
                        tagPressao.includes(termoBusca) ||
                        tagVolume.includes(termoBusca) ||
                        tagReservatorio.includes(termoBusca) ||
                        tagTempAgua.includes(termoBusca) ||
                        tagTempAmbiente.includes(termoBusca)
                    );

                    // Determinar visibilidade: match direto OU herdado do pai (tipo ou valor)
                    let itemVisivel = pontoMatchDireto || valorMatchDireto || tipoMatchDireto || !termoBusca;

                    if (itemVisivel) {
                        itemRow.classList.remove('filtro-oculto');
                        totalItensVisiveis++;
                        valorTemFilhoVisivel = true;

                        // Adicionar badge indicando onde foi o match
                        if (termoBusca) {
                            if (pontoMatchDireto) {
                                itemRow.classList.add('filtro-match', 'filtro-match-direto');
                                adicionarBadgeMatchItem(itemRow, 'ponto', getMatchField(itemRow, termoBusca));
                            } else if (valorMatchDireto) {
                                itemRow.classList.add('filtro-match');
                                adicionarBadgeMatchItem(itemRow, 'unidade');
                            } else if (tipoMatchDireto) {
                                itemRow.classList.add('filtro-match');
                                adicionarBadgeMatchItem(itemRow, 'tipo');
                            }
                        }
                    } else {
                        itemRow.classList.add('filtro-oculto');
                    }
                });

                // Valor visível se: ele mesmo deu match OU tem filho visível OU tipo deu match
                let valorVisivel = valorMatchDireto || valorTemFilhoVisivel || tipoMatchDireto || !termoBusca;

                // Mas apenas se passar pelo filtro de favoritos
                if (filtroFavoritos === 'favoritos' && !isFavorito) {
                    valorVisivel = false;
                }

                if (valorVisivel) {
                    valorCard.classList.remove('filtro-oculto');
                    totalValoresVisiveis++;
                    tipoTemFilhoVisivel = true;

                    if (termoBusca) {
                        if (valorMatchDireto) {
                            valorCard.classList.add('filtro-match', 'filtro-match-direto');
                            adicionarBadgeMatchValor(valorCard, 'unidade');
                        } else if (tipoMatchDireto) {
                            valorCard.classList.add('filtro-match');
                            adicionarBadgeMatchValor(valorCard, 'tipo');
                        }
                    }

                    // Expandir valor se tem item com match
                    if (valorTemFilhoVisivel && termoBusca) {
                        valorCard.classList.add('expanded');
                    }
                } else {
                    valorCard.classList.add('filtro-oculto');
                }
            });

            // Tipo visível se: ele mesmo deu match ou tem filho visível
            if (tipoMatchDireto || tipoTemFilhoVisivel) {
                tipoCard.classList.remove('filtro-oculto');
                totalTiposVisiveis++;

                if (termoBusca && tipoMatchDireto) {
                    tipoCard.classList.add('filtro-match', 'filtro-match-direto');
                    adicionarBadgeMatchTipo(tipoCard, 'tipo');
                }

                // Expandir tipo se tem valor visível
                if (tipoTemFilhoVisivel && termoBusca) {
                    tipoCard.classList.add('expanded');
                }
            } else {
                tipoCard.classList.add('filtro-oculto');
            }
        });

        // Atualizar contagem de resultados
        atualizarContagemFiltro(termoBusca, filtroDescarte, filtroFavoritos, totalTiposVisiveis, totalValoresVisiveis, totalItensVisiveis);
    }

    // Criar elemento badge
    function criarBadge(nivel, campo = null) {
        const badge = document.createElement('span');
        badge.className = 'badge-match badge-match-' + nivel;

        const icones = {
            'tipo': 'folder-outline',
            'unidade': 'albums-outline',
            'ponto': 'location-outline'
        };

        const textos = {
            'tipo': 'Match no Tipo',
            'unidade': 'Match na Unidade',
            'ponto': campo ? `Match: ${campo}` : 'Match no Ponto'
        };

        badge.innerHTML = `<ion-icon name="${icones[nivel]}"></ion-icon> ${textos[nivel]}`;
        badge.title = textos[nivel];

        return badge;
    }

    // Adicionar badge no TIPO (dentro do h3)
    function adicionarBadgeMatchTipo(tipoCard, nivel) {
        const h3 = tipoCard.querySelector('.tipo-info h3');
        if (h3 && !h3.querySelector('.badge-match')) {
            const badge = criarBadge(nivel);
            h3.appendChild(badge);
        }
    }

    // Adicionar badge no VALOR/UNIDADE (dentro do h4)
    function adicionarBadgeMatchValor(valorCard, nivel) {
        const h4 = valorCard.querySelector('.valor-info h4');
        if (h4 && !h4.querySelector('.badge-match')) {
            const badge = criarBadge(nivel);
            h4.appendChild(badge);
        }
    }

    // Adicionar badge no ITEM/PONTO (após o h5 ou dentro de item-info)
    function adicionarBadgeMatchItem(itemRow, nivel, campo = null) {
        const itemInfo = itemRow.querySelector('.item-info');
        if (itemInfo && !itemInfo.querySelector('.badge-match')) {
            const badge = criarBadge(nivel, campo);
            // Inserir após o primeiro elemento (h5)
            const h5 = itemInfo.querySelector('h5');
            if (h5) {
                h5.insertAdjacentElement('afterend', badge);
            } else {
                itemInfo.appendChild(badge);
            }
        }
    }

    // Função auxiliar para identificar qual campo deu match
    function getMatchField(itemRow, termo) {
        const pontoNome = normalizarTexto(itemRow.dataset.pontoNome || '');
        const pontoCodigo = normalizarTexto(itemRow.dataset.pontoCodigo || '');
        const tagVazao = normalizarTexto(itemRow.dataset.tagVazao || '');
        const tagPressao = normalizarTexto(itemRow.dataset.tagPressao || '');
        const tagVolume = normalizarTexto(itemRow.dataset.tagVolume || '');
        const tagReservatorio = normalizarTexto(itemRow.dataset.tagReservatorio || '');
        const tagTempAgua = normalizarTexto(itemRow.dataset.tagTempAgua || '');
        const tagTempAmbiente = normalizarTexto(itemRow.dataset.tagTempAmbiente || '');

        if (pontoNome.includes(termo)) return 'Nome';
        if (pontoCodigo.includes(termo)) return 'Código';
        if (tagVazao.includes(termo)) return 'TAG Vazão';
        if (tagPressao.includes(termo)) return 'TAG Pressão';
        if (tagVolume.includes(termo)) return 'TAG Volume';
        if (tagReservatorio.includes(termo)) return 'TAG Reservatório';
        if (tagTempAgua.includes(termo)) return 'TAG Temp. Água';
        if (tagTempAmbiente.includes(termo)) return 'TAG Temp. Ambiente';

        return null;
    }

    // Atualizar função limparFiltros para remover badges
    function limparFiltros() {
        document.getElementById('filtroBusca').value = '';
        document.getElementById('btnLimparBusca').style.display = 'none';
        document.querySelector('input[name="filtroDescarte"][value="todos"]').checked = true;
        document.querySelector('input[name="filtroFavoritos"][value="todos"]').checked = true;

        // Remover classes de filtro
        document.querySelectorAll('.tipo-card, .valor-card, .item-row').forEach(el => {
            el.classList.remove('filtro-oculto', 'filtro-match', 'filtro-match-direto');
        });

        // Remover badges de match
        document.querySelectorAll('.badge-match').forEach(b => b.remove());

        document.getElementById('filtroResultado').style.display = 'none';

        // Limpar estado salvo dos filtros
        clearFiltrosState();

        // Restaurar estado salvo dos acordeões
        restoreExpandedState();

        // Atualizar botão de expandir/recolher
        setTimeout(verificarEstadoAcordeoes, 50);
    }

    function atualizarContagemFiltro(termoBusca, filtroDescarte, filtroFavoritos, tipos, valores, itens) {
        const divResultado = document.getElementById('filtroResultado');
        const spanContagem = document.getElementById('filtroContagem');

        if (termoBusca || filtroDescarte !== 'todos' || filtroFavoritos !== 'todos') {
            divResultado.style.display = 'flex';

            let texto = [];
            if (tipos > 0) texto.push(`<strong>${tipos}</strong> tipo${tipos !== 1 ? 's' : ''}`);
            if (valores > 0) texto.push(`<strong>${valores}</strong> valor${valores !== 1 ? 'es' : ''}`);
            if (itens > 0) texto.push(`<strong>${itens}</strong> ponto${itens !== 1 ? 's' : ''}`);

            if (texto.length > 0) {
                spanContagem.innerHTML = `Exibindo: ${texto.join(', ')}`;
            } else {
                spanContagem.innerHTML = 'Nenhum resultado encontrado';
            }
        } else {
            divResultado.style.display = 'none';
        }

        verificarEstadoAcordeoes();
    }

    // Inicializar filtros
    document.addEventListener('DOMContentLoaded', initFiltros);

    // ============================================
    // Modal Functions
    // ============================================
    function fecharModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }

    function abrirModalTipo(cd = null, nome = '', idExterno = '', descarte = 0) {
        event && event.stopPropagation();
        const modal = document.getElementById('modalTipo');

        document.getElementById('modalTipoTitle').textContent = cd ? 'Editar Tipo de Unidade Operacional' : 'Novo Tipo de Unidade Operacional';
        document.getElementById('inputTipoCd').value = cd || '';
        document.getElementById('inputTipoNome').value = nome;
        document.getElementById('inputTipoId').value = idExterno;

        // Selecionar radio button de descarte
        if (descarte == 1) {
            document.getElementById('descarteSim').checked = true;
        } else {
            document.getElementById('descarteNao').checked = true;
        }

        modal.classList.add('active');
        document.getElementById('inputTipoNome').focus();
    }

    function abrirModalValor(cd = null, nome = '', idExterno = '', cdTipo = null, fluxo = '') {
        event && event.stopPropagation();
        const modal = document.getElementById('modalValor');

        document.getElementById('modalValorTitle').textContent = cd ? 'Editar Unidade Operacional' : 'Nova Unidade Operacional';
        document.getElementById('inputValorCd').value = cd || '';
        document.getElementById('inputValorTipo').value = cdTipo || '';
        document.getElementById('inputValorNome').value = nome;

        // ID Externo - tanto no campo hidden quanto no texto
        document.getElementById('inputValorId').value = idExterno || '';
        document.getElementById('inputValorIdText').value = idExterno || '';
        document.getElementById('btnLimparIdExterno').style.display = idExterno ? 'flex' : 'none';
        document.getElementById('inputValorIdDropdown').classList.remove('active');

        document.getElementById('inputValorFluxo').value = fluxo || '';

        modal.classList.add('active');
        document.getElementById('inputValorNome').focus();
    }

    // ============================================
    // Autocomplete de ID Externo
    // ============================================
    let autocompleteIdExternoTimeout = null;
    let autocompleteIdExternoIndex = -1;

    function initAutocompleteIdExterno() {
        const input = document.getElementById('inputValorIdText');
        const hidden = document.getElementById('inputValorId');
        const dropdown = document.getElementById('inputValorIdDropdown');
        const btnLimpar = document.getElementById('btnLimparIdExterno');

        // Evento de foco - abre dropdown
        input.addEventListener('focus', function () {
            const cdTipo = document.getElementById('inputValorTipo').value;
            if (cdTipo) {
                buscarIdExternoAutocomplete('');
            }
        });

        // Evento de digitação
        input.addEventListener('input', function () {
            const termo = this.value.trim();

            // Atualiza o campo hidden com o valor digitado (permite valor livre)
            hidden.value = termo;
            btnLimpar.style.display = termo ? 'flex' : 'none';
            autocompleteIdExternoIndex = -1;

            // Debounce
            clearTimeout(autocompleteIdExternoTimeout);
            autocompleteIdExternoTimeout = setTimeout(() => {
                const cdTipo = document.getElementById('inputValorTipo').value;
                if (cdTipo) {
                    buscarIdExternoAutocomplete(termo);
                }
            }, 300);
        });

        // Navegação por teclado
        input.addEventListener('keydown', function (e) {
            const items = dropdown.querySelectorAll('.autocomplete-item');

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                autocompleteIdExternoIndex = Math.min(autocompleteIdExternoIndex + 1, items.length - 1);
                atualizarHighlightIdExterno(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                autocompleteIdExternoIndex = Math.max(autocompleteIdExternoIndex - 1, 0);
                atualizarHighlightIdExterno(items);
            } else if (e.key === 'Enter' && autocompleteIdExternoIndex >= 0) {
                e.preventDefault();
                if (items[autocompleteIdExternoIndex]) {
                    items[autocompleteIdExternoIndex].click();
                }
            } else if (e.key === 'Escape') {
                dropdown.classList.remove('active');
            }
        });

        // Fechar dropdown ao clicar fora
        document.addEventListener('click', function (e) {
            if (!e.target.closest('#inputValorIdText') && !e.target.closest('#inputValorIdDropdown')) {
                dropdown.classList.remove('active');
            }
        });
    }

    function atualizarHighlightIdExterno(items) {
        items.forEach((item, index) => {
            item.classList.toggle('highlighted', index === autocompleteIdExternoIndex);
        });

        if (autocompleteIdExternoIndex >= 0 && items[autocompleteIdExternoIndex]) {
            items[autocompleteIdExternoIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    function buscarIdExternoAutocomplete(termo) {
        const dropdown = document.getElementById('inputValorIdDropdown');
        const cdTipo = document.getElementById('inputValorTipo').value;

        if (!cdTipo) {
            dropdown.innerHTML = '<div class="autocomplete-empty">Selecione um tipo primeiro</div>';
            dropdown.classList.add('active');
            return;
        }

        dropdown.innerHTML = '<div class="autocomplete-loading">Buscando...</div>';
        dropdown.classList.add('active');

        const params = new URLSearchParams({ cdTipo: cdTipo, busca: termo });

        fetch(`bd/entidade/buscarCD_ENTIDADE_VALOR_ID.php?${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    let html = '';
                    data.data.forEach(item => {
                        html += `
                        <div class="autocomplete-item" 
                             data-value="${item.id}" 
                             data-label="${item.label}">
                            <span class="item-code">${item.id}</span>
                            <span class="item-name">${item.nome} <span class="fluxo-badge fluxo-${item.fluxo}">${item.fluxoNome}</span></span>
                        </div>
                    `;
                    });
                    dropdown.innerHTML = html;

                    dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
                        item.addEventListener('click', function () {
                            selecionarIdExterno(this.dataset.value);
                        });
                    });
                } else {
                    dropdown.innerHTML = '<div class="autocomplete-empty">Nenhum ID encontrado. Digite um novo.</div>';
                }
            })
            .catch(error => {
                console.error('Erro ao buscar IDs:', error);
                dropdown.innerHTML = '<div class="autocomplete-empty">Erro ao buscar</div>';
            });
    }

    function selecionarIdExterno(value) {
        document.getElementById('inputValorId').value = value;
        document.getElementById('inputValorIdText').value = value;
        document.getElementById('inputValorIdDropdown').classList.remove('active');
        document.getElementById('btnLimparIdExterno').style.display = 'flex';
        autocompleteIdExternoIndex = -1;
    }

    function limparIdExterno() {
        document.getElementById('inputValorId').value = '';
        document.getElementById('inputValorIdText').value = '';
        document.getElementById('btnLimparIdExterno').style.display = 'none';
        document.getElementById('inputValorIdText').focus();
    }

    // Inicializar autocomplete de ID Externo
    document.addEventListener('DOMContentLoaded', initAutocompleteIdExterno);

    // Variáveis do autocomplete de ponto
    let autocompletePontoTimeout = null;
    let autocompletePontoIndex = -1;
    const letrasTipoMedidor = { 1: 'M', 2: 'E', 4: 'P', 6: 'R', 8: 'H' };

    function abrirModalItem(cd = null, cdPonto = null, dtInicio = '', dtFim = '', cdValor = null, pontoLabel = '', operacao = '') {
        event && event.stopPropagation();
        const modal = document.getElementById('modalItem');

        document.getElementById('modalItemTitle').textContent = cd ? 'Editar Vínculo' : 'Vincular Ponto de Medição';
        document.getElementById('inputItemCd').value = cd || '';
        document.getElementById('inputItemValor').value = cdValor || '';
        document.getElementById('inputItemDtInicio').value = dtInicio;
        document.getElementById('inputItemDtFim').value = dtFim;

        // Operação (+/-)
        // Para novos cadastros (cd vazio), marca '+' por padrão
        // Para edição, usa o valor que veio do banco (pode ser vazio)
        const op = cd ? operacao : (operacao || '1');
        document.getElementById('operacaoMais').checked = (op == '1');
        document.getElementById('operacaoMenos').checked = (op == '2');

        // Limpar autocomplete
        document.getElementById('inputItemPonto').value = cdPonto || '';
        document.getElementById('inputItemPontoText').value = pontoLabel || '';
        document.getElementById('btnLimparPontoItem').style.display = pontoLabel ? 'flex' : 'none';
        document.getElementById('inputItemPontoDropdown').classList.remove('active');

        modal.classList.add('active');
        document.getElementById('inputItemPontoText').focus();
    }

    // ============================================
    // Autocomplete de Ponto de Medição
    // ============================================
    function initAutocompleteItem() {
        const input = document.getElementById('inputItemPontoText');
        const hidden = document.getElementById('inputItemPonto');
        const dropdown = document.getElementById('inputItemPontoDropdown');
        const btnLimpar = document.getElementById('btnLimparPontoItem');

        // Evento de foco - abre dropdown
        input.addEventListener('focus', function () {
            if (!hidden.value) {
                buscarPontosAutocomplete('');
            }
        });

        // Evento de digitação
        input.addEventListener('input', function () {
            const termo = this.value.trim();

            // Limpa seleção anterior
            hidden.value = '';
            btnLimpar.style.display = 'none';
            autocompletePontoIndex = -1;

            // Debounce
            clearTimeout(autocompletePontoTimeout);
            autocompletePontoTimeout = setTimeout(() => {
                buscarPontosAutocomplete(termo);
            }, 300);
        });

        // Navegação por teclado
        input.addEventListener('keydown', function (e) {
            const items = dropdown.querySelectorAll('.autocomplete-item');

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                autocompletePontoIndex = Math.min(autocompletePontoIndex + 1, items.length - 1);
                atualizarHighlightPonto(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                autocompletePontoIndex = Math.max(autocompletePontoIndex - 1, 0);
                atualizarHighlightPonto(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (autocompletePontoIndex >= 0 && items[autocompletePontoIndex]) {
                    items[autocompletePontoIndex].click();
                }
            } else if (e.key === 'Escape') {
                dropdown.classList.remove('active');
            }
        });

        // Fechar dropdown ao clicar fora
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.autocomplete-container')) {
                dropdown.classList.remove('active');
            }
        });
    }

    function atualizarHighlightPonto(items) {
        items.forEach((item, index) => {
            item.classList.toggle('highlighted', index === autocompletePontoIndex);
        });

        // Scroll para item visível
        if (autocompletePontoIndex >= 0 && items[autocompletePontoIndex]) {
            items[autocompletePontoIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    function buscarPontosAutocomplete(termo) {
        const dropdown = document.getElementById('inputItemPontoDropdown');

        dropdown.innerHTML = '<div class="autocomplete-loading">Buscando...</div>';
        dropdown.classList.add('active');

        const params = new URLSearchParams({ busca: termo });

        fetch(`bd/pontoMedicao/buscarPontosMedicao.php?${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    let html = '';
                    data.data.forEach(item => {
                        const letraTipo = letrasTipoMedidor[item.ID_TIPO_MEDIDOR] || 'X';
                        const codigoPonto = item.CD_LOCALIDADE + '-' +
                            String(item.CD_PONTO_MEDICAO).padStart(6, '0') + '-' +
                            letraTipo + '-' +
                            item.CD_UNIDADE;
                        html += `
                        <div class="autocomplete-item" 
                             data-value="${item.CD_PONTO_MEDICAO}" 
                             data-label="${codigoPonto} - ${item.DS_NOME}">
                            <span class="item-code">${codigoPonto}</span>
                            <span class="item-name">${item.DS_NOME}</span>
                        </div>
                    `;
                    });
                    dropdown.innerHTML = html;

                    // Adiciona eventos de clique
                    dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
                        item.addEventListener('click', function () {
                            selecionarPontoAutocomplete(this.dataset.value, this.dataset.label);
                        });
                    });
                } else {
                    dropdown.innerHTML = '<div class="autocomplete-empty">Nenhum ponto encontrado</div>';
                }
            })
            .catch(error => {
                console.error('Erro ao buscar pontos:', error);
                dropdown.innerHTML = '<div class="autocomplete-empty">Erro ao buscar</div>';
            });
    }

    function selecionarPontoAutocomplete(value, label) {
        document.getElementById('inputItemPonto').value = value;
        document.getElementById('inputItemPontoText').value = label;
        document.getElementById('inputItemPontoDropdown').classList.remove('active');
        document.getElementById('btnLimparPontoItem').style.display = 'flex';
        autocompletePontoIndex = -1;
    }

    function limparPontoItem() {
        document.getElementById('inputItemPonto').value = '';
        document.getElementById('inputItemPontoText').value = '';
        document.getElementById('btnLimparPontoItem').style.display = 'none';
        document.getElementById('inputItemPontoText').focus();
    }

    // Inicializar autocomplete quando DOM estiver pronto
    document.addEventListener('DOMContentLoaded', initAutocompleteItem);

    // Fechar modal ao clicar fora
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function (e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });

    // Fechar modal com ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                modal.classList.remove('active');
            });
        }
    });

    // ============================================
    // CRUD Functions
    // ============================================
    function salvarTipo(event) {
        event.preventDefault();

        const cd = document.getElementById('inputTipoCd').value;
        const nome = document.getElementById('inputTipoNome').value.trim();
        const idExterno = document.getElementById('inputTipoId').value.trim();
        const descarte = document.querySelector('input[name="descarte"]:checked').value;

        if (!nome) {
            showToast('O nome é obrigatório', 'erro');
            return;
        }

        if (!idExterno) {
            showToast('O ID Externo é obrigatório', 'erro');
            return;
        }

        $.ajax({
            url: 'bd/entidade/salvarTipo.php',
            type: 'POST',
            data: { cd, nome, idExterno, descarte },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showToast(response.message, 'sucesso');
                    saveExpandedState();
                    setTimeout(() => location.reload(), 200);
                } else {
                    showToast(response.message || 'Erro ao salvar', 'erro');
                }
            },
            error: function () {
                showToast('Erro ao comunicar com o servidor', 'erro');
            }
        });
    }

    function alterarStatusTipo(cd, acao) {
        const mensagem = acao === 'ativar' ? 'Deseja ativar este tipo de unidade operacional?' : 'Deseja desativar este tipo de unidade operacional?';
        if (!confirm(mensagem)) return;

        $.ajax({
            url: 'bd/entidade/alterarStatusTipo.php',
            type: 'POST',
            data: { cd, acao },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showToast(response.message, 'sucesso');
                    saveExpandedState();
                    setTimeout(() => location.reload(), 200);
                } else {
                    showToast(response.message || 'Erro ao alterar status', 'erro');
                }
            },
            error: function () {
                showToast('Erro ao comunicar com o servidor', 'erro');
            }
        });
    }

    function salvarValor(event) {
        event.preventDefault();

        const cd = document.getElementById('inputValorCd').value;
        const cdTipo = document.getElementById('inputValorTipo').value;
        const nome = document.getElementById('inputValorNome').value.trim();
        // Pegar do campo de texto para permitir valor livre
        const idExterno = document.getElementById('inputValorIdText').value.trim();
        const fluxo = document.getElementById('inputValorFluxo').value;

        if (!nome) {
            showToast('O nome é obrigatório', 'erro');
            return;
        }

        if (!idExterno) {
            showToast('O ID Externo é obrigatório', 'erro');
            return;
        }

        if (!fluxo) {
            showToast('O fluxo é obrigatório', 'erro');
            return;
        }

        $.ajax({
            url: 'bd/entidade/salvarValor.php',
            type: 'POST',
            data: { cd, cdTipo, nome, idExterno, fluxo },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showToast(response.message, 'sucesso');
                    saveExpandedState();
                    setTimeout(() => location.reload(), 200);
                } else {
                    showToast(response.message || 'Erro ao salvar', 'erro');
                }
            },
            error: function () {
                showToast('Erro ao comunicar com o servidor', 'erro');
            }
        });
    }

    function salvarItem(event) {
        event.preventDefault();

        const cd = document.getElementById('inputItemCd').value;
        const cdValor = document.getElementById('inputItemValor').value;
        const cdPonto = document.getElementById('inputItemPonto').value;
        const dtInicio = document.getElementById('inputItemDtInicio').value;
        const dtFim = document.getElementById('inputItemDtFim').value;
        const operacaoEl = document.querySelector('input[name="operacao"]:checked');
        const operacao = operacaoEl ? operacaoEl.value : '';

        if (!cdPonto) {
            showToast('Selecione um ponto de medição', 'erro');
            return;
        }

        if (!dtInicio) {
            showToast('A data de início é obrigatória', 'erro');
            return;
        }

        if (!dtFim) {
            showToast('A data de fim é obrigatória', 'erro');
            return;
        }

        if (!operacao) {
            showToast('Selecione a operação (+ ou -)', 'erro');
            return;
        }

        $.ajax({
            url: 'bd/entidade/salvarItem.php',
            type: 'POST',
            data: { cd, cdValor, cdPonto, dtInicio, dtFim, operacao },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showToast(response.message, 'sucesso');
                    saveExpandedState();
                    setTimeout(() => location.reload(), 200);
                } else {
                    showToast(response.message || 'Erro ao salvar', 'erro');
                }
            },
            error: function () {
                showToast('Erro ao comunicar com o servidor', 'erro');
            }
        });
    }

    // ============================================
    // Funções para Gráficos
    // ============================================
    function abrirGraficoValor(tipoId, valorId, valorEntidadeId) {
        event && event.stopPropagation();
        const mesAtual = new Date().getMonth() + 1;
        const anoAtual = new Date().getFullYear();
        window.location.href = `operacoes.php?tipo=${tipoId}&valor=${valorId}&valorEntidadeId=${valorEntidadeId}&mes=${mesAtual}&ano=${anoAtual}`;
    }

    function abrirGraficoPonto(tipoId, valorId, valorEntidadeId, pontoId) {
        event && event.stopPropagation();
        const mesAtual = new Date().getMonth() + 1;
        const anoAtual = new Date().getFullYear();
        window.location.href = `operacoes.php?tipo=${tipoId}&valor=${valorId}&valorEntidadeId=${valorEntidadeId}&ponto=${pontoId}&mes=${mesAtual}&ano=${anoAtual}`;
    }

    function excluirValor(cd) {
        if (!confirm('Deseja excluir esta unidade operacional?\n\nTodos os pontos vinculados também serão removidos.')) return;

        $.ajax({
            url: 'bd/entidade/excluirValor.php',
            type: 'POST',
            data: { cd: cd },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showToast(response.message, 'sucesso');
                    saveExpandedState();
                    setTimeout(() => location.reload(), 200);
                } else {
                    showToast(response.message || 'Erro ao excluir', 'erro');
                }
            },
            error: function (xhr, status, error) {
                console.error('Erro AJAX excluirValor:', status, error);
                console.error('Resposta:', xhr.responseText);

                // Tentar extrair mensagem de erro da resposta
                let mensagem = 'Erro ao comunicar com o servidor';
                try {
                    if (xhr.responseText) {
                        const resp = JSON.parse(xhr.responseText);
                        if (resp.message) mensagem = resp.message;
                    }
                } catch (e) {
                    // Se não for JSON, mostrar parte da resposta
                    if (xhr.responseText && xhr.responseText.length < 200) {
                        mensagem = 'Erro: ' + xhr.responseText;
                    }
                }
                showToast(mensagem, 'erro');
            }
        });
    }

    function excluirItem(cd, cdValor) {
        if (!confirm('Deseja remover este ponto de medição da unidade operacional?')) return;

        // Garantir que o valor pai continue expandido
        const valorCard = document.getElementById('valor-' + cdValor);
        if (valorCard && !valorCard.classList.contains('expanded')) {
            valorCard.classList.add('expanded');
        }

        $.ajax({
            url: 'bd/entidade/excluirItem.php',
            type: 'POST',
            data: { cd },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showToast(response.message, 'sucesso');
                    saveExpandedState();
                    setTimeout(() => location.reload(), 200);
                } else {
                    showToast(response.message || 'Erro ao excluir', 'erro');
                }
            },
            error: function (xhr, status, error) {
                console.error('Erro AJAX:', xhr.responseText);
                showToast('Erro ao comunicar com o servidor', 'erro');
            }
        });
    }

    // ============================================
    // Drag and Drop para reordenar pontos de medição
    // ============================================
    let draggedItem = null;
    let dragSourceContainer = null;

    function initDragAndDrop() {
        const containers = document.querySelectorAll('.itens-sortable');

        containers.forEach(container => {
            const items = container.querySelectorAll('.item-row');

            items.forEach(item => {
                // Eventos de arrastar
                item.addEventListener('dragstart', handleDragStart);
                item.addEventListener('dragend', handleDragEnd);
                item.addEventListener('dragover', handleDragOver);
                item.addEventListener('dragenter', handleDragEnter);
                item.addEventListener('dragleave', handleDragLeave);
                item.addEventListener('drop', handleDrop);
            });
        });
    }

    function handleDragStart(e) {
        draggedItem = this;
        dragSourceContainer = this.parentElement;

        // Adicionar classe de arrastar após um pequeno delay para efeito visual
        setTimeout(() => {
            this.classList.add('dragging');
        }, 0);

        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', this.dataset.itemCd);
    }

    function handleDragEnd(e) {
        this.classList.remove('dragging');

        // Remover classes de todos os itens
        document.querySelectorAll('.item-row').forEach(item => {
            item.classList.remove('drag-over');
        });

        draggedItem = null;
        dragSourceContainer = null;
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }

    function handleDragEnter(e) {
        e.preventDefault();

        // Só permitir drop no mesmo container
        if (this !== draggedItem && this.parentElement === dragSourceContainer) {
            this.classList.add('drag-over');
        }
    }

    function handleDragLeave(e) {
        this.classList.remove('drag-over');
    }

    function handleDrop(e) {
        e.preventDefault();
        this.classList.remove('drag-over');

        // Só permitir drop no mesmo container
        if (this === draggedItem || this.parentElement !== dragSourceContainer) {
            return;
        }

        const container = this.parentElement;
        const items = Array.from(container.querySelectorAll('.item-row'));
        const draggedIndex = items.indexOf(draggedItem);
        const dropIndex = items.indexOf(this);

        // Reordenar no DOM
        if (draggedIndex < dropIndex) {
            this.parentNode.insertBefore(draggedItem, this.nextSibling);
        } else {
            this.parentNode.insertBefore(draggedItem, this);
        }

        // Salvar nova ordem no servidor
        salvarOrdemItens(container);
    }

    function salvarOrdemItens(container) {
        const valorCd = container.dataset.valorCd;
        const items = container.querySelectorAll('.item-row');
        const itens = Array.from(items).map(item => item.dataset.itemCd);

        fetch('bd/entidade/reordenarItens.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ itens: itens })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Ordem atualizada!', 'sucesso');
                } else {
                    showToast(data.message || 'Erro ao reordenar', 'erro');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro ao comunicar com o servidor', 'erro');
            });
    }

    // ============================================
    // Sistema de Favoritos
    // ============================================

    /**
     * Alterna favorito de uma unidade operacional
     */
    function toggleFavorito(cdEntidadeValor, btn) {
        event.stopPropagation();

        // Feedback visual imediato
        btn.classList.toggle('ativo');
        const icon = btn.querySelector('ion-icon');
        const isAtivo = btn.classList.contains('ativo');
        icon.setAttribute('name', isAtivo ? 'star' : 'star-outline');
        btn.title = isAtivo ? 'Remover dos favoritos' : 'Adicionar aos favoritos';

        // Atualizar data-favorito no card
        const valorCard = document.getElementById('valor-' + cdEntidadeValor);
        if (valorCard) {
            valorCard.dataset.favorito = isAtivo ? '1' : '0';
        }

        // Chamada AJAX
        $.ajax({
            url: 'bd/entidade/toggleFavorito.php',
            type: 'POST',
            data: { cdEntidadeValor: cdEntidadeValor },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showToast(response.message, 'sucesso');
                    // Reaplicar filtros se estiver filtrando por favoritos
                    const filtroFav = document.querySelector('input[name="filtroFavoritos"]:checked')?.value;
                    if (filtroFav === 'favoritos') {
                        aplicarFiltros();
                    }
                } else {
                    // Reverter visual em caso de erro
                    btn.classList.toggle('ativo');
                    icon.setAttribute('name', btn.classList.contains('ativo') ? 'star' : 'star-outline');
                    if (valorCard) {
                        valorCard.dataset.favorito = btn.classList.contains('ativo') ? '1' : '0';
                    }
                    showToast(response.message || 'Erro ao atualizar favorito', 'erro');
                }
            },
            error: function () {
                // Reverter visual em caso de erro
                btn.classList.toggle('ativo');
                icon.setAttribute('name', btn.classList.contains('ativo') ? 'star' : 'star-outline');
                if (valorCard) {
                    valorCard.dataset.favorito = btn.classList.contains('ativo') ? '1' : '0';
                }
                showToast('Erro ao comunicar com o servidor', 'erro');
            }
        });
    }

    // Inicializar drag and drop após carregar a página
    document.addEventListener('DOMContentLoaded', initDragAndDrop);


</script>

<?php include_once 'includes/footer.inc.php'; ?>
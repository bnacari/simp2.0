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
    // ============================================
    // Query ULTRA LEVE: Carrega apenas TIPOS (sem valores)
    // Valores são carregados via AJAX ao expandir cada tipo
    // ============================================
    $sql = "
        SELECT 
            ET.CD_CHAVE AS TIPO_CD,
            ET.DS_NOME AS TIPO_NOME,
            ET.CD_ENTIDADE_TIPO_ID AS TIPO_ID,
            ET.DESCARTE AS TIPO_DESCARTE,
            ET.DT_EXC_ENTIDADE_TIPO AS TIPO_DT_EXC,
            (SELECT COUNT(*) FROM SIMP.dbo.ENTIDADE_VALOR 
             WHERE CD_ENTIDADE_TIPO = ET.CD_CHAVE) AS TOTAL_VALORES
        FROM SIMP.dbo.ENTIDADE_TIPO ET
        ORDER BY ET.DS_NOME
    ";

    $queryEntidades = $pdoSIMP->query($sql);

    if ($queryEntidades === false) {
        throw new Exception("Erro ao executar query de entidades");
    }

    $entidades = [];
    while ($row = $queryEntidades->fetch(PDO::FETCH_ASSOC)) {
        $entidades[] = [
            'cd' => $row['TIPO_CD'],
            'nome' => $row['TIPO_NOME'],
            'id' => $row['TIPO_ID'],
            'descarte' => !empty($row['TIPO_DESCARTE']),
            'dtExc' => $row['TIPO_DT_EXC'],
            'totalValores' => (int) $row['TOTAL_VALORES']
        ];
    }

    // Contadores
    $totalTipos = 0;
    foreach ($entidades as $t) {
        if (empty($t['dtExc'])) $totalTipos++;
        $totalValores += $t['totalValores'];
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
        <!-- <div class="filtro-descarte">
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
        </div> -->
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
                                <?php if ($isInativo): ?>
                                    <span class="status-badge inativo">Inativo</span>
                                <?php endif; ?>
                            </h3>
                            <span>ID: <?= htmlspecialchars($tipo['id'] ?? '-') ?></span>
                        </div>
                    </div>
                    <div class="tipo-card-header-right">
                        <span class="badge-count"><?= $tipo['totalValores'] ?> Unidades Operacionais</span>
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
                <!-- Tipo Body (Valores) - Carregado via AJAX (lazy load) -->
                <div class="tipo-card-body" id="tipo-body-<?= $tipo['cd'] ?>" data-loaded="0"
                    data-total="<?= $tipo['totalValores'] ?>">
                    <div class="lazy-load-placeholder" style="padding: 24px; text-align: center; color: #94a3b8;">
                        <ion-icon name="hourglass-outline"
                            style="font-size: 24px; animation: spin 1s linear infinite;"></ion-icon>
                        <p style="margin-top: 8px; font-size: 12px;">Carregando unidades operacionais...</p>
                    </div>
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

    /* Highlight amarelo para itens que também atendem ao filtro */
    .item-row.item-match-highlight {
        background: #fef9c3 !important;
        border-color: #facc15 !important;
        box-shadow: 0 0 0 1px #facc15;
    }

    .item-row.item-match-highlight:hover {
        background: #fef08a !important;
    }

    /* Spinner para lazy load */
    @keyframes spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }
</style>

<script>
    // ============================================
    // Estado dos Acordeões (localStorage)
    // ============================================
    const STORAGE_KEY = 'entidade_expanded_state';
    const STORAGE_KEY_FILTROS = 'entidade_filtros_state';

    // Flag para controle de reload após inclusão contínua de itens
    let _itemAdicionado = false;

    // Cache de itens já carregados por valor (evita requisições repetidas)
    const _itensCache = {};

    // Permissão de edição (passada do PHP)
    const _podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;

    // Flag de NR_ORDEM
    const _temNrOrdem = <?= $temNrOrdem ? 'true' : 'false' ?>;

    // Cache de valores carregados por tipo (evita re-fetch)
    const _valoresCache = {};

    // Mapeamento de fluxo para texto e classe CSS
    const _fluxosTexto = { 1: 'Entrada', 2: 'Saída', 3: 'Municipal', 4: 'Não se Aplica' };

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

    /**
     * Restaura o estado expandido dos acordeões salvos no localStorage.
     * Carrega valores em fila (máx 2 simultâneos) para não sobrecarregar.
     */
    function restoreExpandedState() {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (!saved) return;

            const state = JSON.parse(saved);

            // Expandir tipos (apenas visual, sem carregar valores ainda)
            if (state.tipos) {
                state.tipos.forEach(id => {
                    const card = document.getElementById('tipo-' + id);
                    if (card) card.classList.add('expanded');
                });
            }

            // Fila de carregamento: tipos primeiro, depois valores
            const filaTipos = (state.tipos || []).slice();
            const filaValores = (state.valores || []).slice();
            let ativo = 0;
            const MAX_CONCORRENTE = 2;

            function processarFila() {
                // Prioridade: tipos primeiro
                while (ativo < MAX_CONCORRENTE && filaTipos.length > 0) {
                    const id = filaTipos.shift();
                    ativo++;
                    carregarValoresTipo(id, false, function () {
                        ativo--;
                        // Após carregar valores do tipo, expandir os valores salvos que pertencem a ele
                        filaValores.forEach(vid => {
                            const card = document.getElementById('valor-' + vid);
                            if (card) card.classList.add('expanded');
                        });
                        processarFila();
                    });
                }

                // Depois carregar itens dos valores expandidos
                while (ativo < MAX_CONCORRENTE && filaValores.length > 0) {
                    const id = filaValores.shift();
                    const card = document.getElementById('valor-' + id);
                    if (card && card.classList.contains('expanded')) {
                        const body = document.getElementById('valor-body-' + id);
                        if (body && body.dataset.loaded !== '1') {
                            ativo++;
                            // Usar callback manual para controlar a fila
                            const bodyEl = body;
                            const origSuccess = null;
                            $.ajax({
                                url: 'bd/entidade/getItensValor.php',
                                type: 'GET',
                                data: { cdValor: id },
                                dataType: 'json',
                                success: function (response) {
                                    if (response.success) {
                                        _itensCache[id] = response.data;
                                        renderizarItensValor(id, response.data, response.temNrOrdem);
                                        bodyEl.dataset.loaded = '1';
                                        const badge = document.getElementById('badge-count-' + id);
                                        if (badge) badge.textContent = response.total + ' Ponto(s) de Medição';
                                        initDragAndDrop();
                                    }
                                    ativo--;
                                    processarFila();
                                },
                                error: function () {
                                    ativo--;
                                    processarFila();
                                }
                            });
                        }
                    }
                }
            }

            processarFila();

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


    /**
     * Toggle de expansão do tipo.
     * Ao expandir, carrega os valores via AJAX (lazy load).
     * @param {int} id - CD_CHAVE do ENTIDADE_TIPO
     */
    function toggleTipo(id) {
        const card = document.getElementById('tipo-' + id);
        card.classList.toggle('expanded');

        // Lazy load: carregar valores ao expandir
        if (card.classList.contains('expanded')) {
            const body = document.getElementById('tipo-body-' + id);
            if (body && body.dataset.loaded !== '1') {
                carregarValoresTipo(id);
            }
        }

        saveExpandedState();
        verificarEstadoAcordeoes();
    }

    /**
     * Toggle de expansão do valor (unidade operacional).
     * Ao expandir, carrega os itens via AJAX (lazy load).
     */
    function toggleValor(id) {
        event.stopPropagation();
        const card = document.getElementById('valor-' + id);
        if (!card) return;

        card.classList.toggle('expanded');

        // Lazy load: carregar itens ao expandir
        if (card.classList.contains('expanded')) {
            const body = document.getElementById('valor-body-' + id);
            console.log('[toggleValor] Expandindo valor:', id, 'body:', body, 'loaded:', body ? body.dataset.loaded : 'N/A');

            // Forçar carregamento se nunca carregou
            if (body && body.dataset.loaded !== '1') {
                carregarItensValor(id);
            } else if (body && body.dataset.loaded === '1') {
                console.log('[toggleValor] Já carregado, itens no cache:', _itensCache[id] ? _itensCache[id].length : 0);
            }
        }

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

        // Expandir/recolher valores (que já foram carregados no DOM)
        valorCards.forEach(card => {
            if (devemExpandir) {
                card.classList.add('expanded');
                const cdValor = card.id.replace('valor-', '');
                carregarItensValor(cdValor);
            } else {
                card.classList.remove('expanded');
            }
        });

        // Se expandindo, carregar valores dos tipos que ainda não foram carregados
        if (devemExpandir) {
            tipoCards.forEach(card => {
                const cdTipo = card.id.replace('tipo-', '');
                carregarValoresTipo(cdTipo);
            });
        }

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
                // Se o termo tem 2+ caracteres, buscar itens no servidor
                // para encontrar valores que contêm itens matching
                if (termo.length >= 2) {
                    buscarItensServidor(termo, function () {
                        aplicarFiltros();
                        saveFiltrosState();
                    });
                } else {
                    aplicarFiltros();
                    saveFiltrosState();
                }
            }, 400);
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
    // FUNCAO 1: aplicarFiltros - SUBSTITUIR TODA
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
        // Limpar highlight amarelo dos itens
        document.querySelectorAll('.item-match-highlight').forEach(el => {
            el.classList.remove('item-match-highlight');
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

            // Se nao ha busca textual e nao ha filtro de favoritos, mostrar tipo e processar valores
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

            // Verificar se o Tipo da match direto
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

                // Verificar se Valor/Unidade da match direto
                let valorMatchDireto = termoBusca && (valorNome.includes(termoBusca) || valorId.includes(termoBusca));
                let valorTemFilhoVisivel = false;

                // Verificar itens dentro do valor (se ja carregados via lazy load)
                const cdValor = valorCard.id.replace('valor-', '');
                const body = document.getElementById('valor-body-' + cdValor);
                const itensCarregados = body && body.dataset.loaded === '1';
                const itens = valorCard.querySelectorAll('.item-row');

                // Se itens ja carregados, processar visibilidade e highlight
                if (itensCarregados && itens.length > 0) {
                    // Match herdado do pai (tipo ou valor)?
                    const matchPai = valorMatchDireto || tipoMatchDireto;

                    itens.forEach(itemRow => {
                        itemRow.classList.remove('filtro-oculto', 'filtro-match', 'filtro-match-direto', 'item-match-highlight');
                        itemRow.querySelectorAll('.badge-match').forEach(b => b.remove());

                        if (!termoBusca) {
                            // Sem busca: todos visiveis
                            valorTemFilhoVisivel = true;
                            totalItensVisiveis++;
                            return;
                        }

                        const itemMatch = verificarMatchItem(itemRow, termoBusca);

                        if (matchPai) {
                            // Match no pai: TODOS os itens ficam visiveis
                            valorTemFilhoVisivel = true;
                            totalItensVisiveis++;

                            // Se o item TAMBEM atende ao filtro, highlight amarelo
                            if (itemMatch) {
                                itemRow.classList.add('item-match-highlight', 'filtro-match', 'filtro-match-direto');
                                const campo = getMatchField(itemRow, termoBusca);
                                adicionarBadgeMatchItem(itemRow, 'ponto', campo);
                            }
                        } else if (itemMatch) {
                            // Match direto no item (sem match no pai)
                            itemRow.classList.add('filtro-match', 'filtro-match-direto', 'item-match-highlight');
                            const campo = getMatchField(itemRow, termoBusca);
                            adicionarBadgeMatchItem(itemRow, 'ponto', campo);
                            valorTemFilhoVisivel = true;
                            totalItensVisiveis++;
                        } else {
                            // Sem match: marca para decisão posterior
                            itemRow.classList.add('filtro-sem-match');
                        }
                    });

                    // Se algum item deu match, mostrar TODOS os irmãos (contexto completo)
                    // Caso contrário, esconder todos
                    if (valorTemFilhoVisivel) {
                        // Contabilizar os itens sem match que ficaram visíveis
                        valorCard.querySelectorAll('.item-row.filtro-sem-match').forEach(ir => {
                            ir.classList.remove('filtro-sem-match');
                            totalItensVisiveis++;
                        });
                    } else {
                        valorCard.querySelectorAll('.item-row.filtro-sem-match').forEach(ir => {
                            ir.classList.remove('filtro-sem-match');
                            ir.classList.add('filtro-oculto');
                        });
                    }
                }

                // Verificar se servidor indicou match de itens neste valor (lazy load)
                const itemMatchServidor = valorCard.hasAttribute('data-item-match');
                if (itemMatchServidor && termoBusca) {
                    valorTemFilhoVisivel = true;
                    if (!itensCarregados) {
                        const totalMatchServidor = parseInt(valorCard.getAttribute('data-item-match') || '0');
                        totalItensVisiveis += totalMatchServidor;
                    }
                }

                // Valor visivel se: ele mesmo deu match OU tem filho visivel OU tipo deu match
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
                            // Match direto no valor: expandir
                            valorCard.classList.add('expanded');
                        } else if (tipoMatchDireto) {
                            valorCard.classList.add('filtro-match');
                            adicionarBadgeMatchValor(valorCard, 'tipo');
                            // Match no tipo: NAO expandir (pode ter centenas)
                        }

                        // Match nos itens (server-side): expandir
                        if (itemMatchServidor) {
                            valorCard.classList.add('expanded');
                        }
                    }
                } else {
                    valorCard.classList.add('filtro-oculto');
                }
            });

            // Tipo visivel se: ele mesmo deu match ou tem filho visivel
            if (tipoMatchDireto || tipoTemFilhoVisivel) {
                tipoCard.classList.remove('filtro-oculto');
                totalTiposVisiveis++;

                if (termoBusca && tipoMatchDireto) {
                    tipoCard.classList.add('filtro-match', 'filtro-match-direto');
                    adicionarBadgeMatchTipo(tipoCard, 'tipo');
                }

                // Expandir tipo se tem valor visivel
                if (tipoTemFilhoVisivel && termoBusca) {
                    tipoCard.classList.add('expanded');
                }
            } else {
                tipoCard.classList.add('filtro-oculto');
            }
        });

        // Atualizar contagem de resultados
        atualizarContagemFiltro(termoBusca, filtroDescarte, filtroFavoritos, totalTiposVisiveis, totalValoresVisiveis, totalItensVisiveis);

        // PASSO FINAL: Disparar lazy load para todos os valores expandidos que ainda nao carregaram
        // Executa apos um pequeno delay para garantir que o DOM ja foi atualizado
        setTimeout(carregarItensValoresExpandidos, 50);
    }

    // ============================================
    // FUNCAO 2: carregarItensValoresExpandidos - ADICIONAR LOGO APOS aplicarFiltros
    // ============================================

    /**
     * Percorre todos os valor-cards expandidos e dispara o lazy load
     * para os que ainda nao tiveram seus itens carregados.
     * Chamado ao final de aplicarFiltros() para garantir carregamento.
     */
    function carregarItensValoresExpandidos() {
        const valoresExpandidos = document.querySelectorAll('.valor-card.expanded:not(.filtro-oculto)');
        valoresExpandidos.forEach(valorCard => {
            const cdValor = valorCard.id.replace('valor-', '');
            const body = document.getElementById('valor-body-' + cdValor);
            if (body && body.dataset.loaded !== '1') {
                carregarItensValor(cdValor);
            }
        });
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

    function limparFiltros() {
        document.getElementById('filtroBusca').value = '';
        document.getElementById('btnLimparBusca').style.display = 'none';
        // Null-safe: filtroDescarte pode estar comentado no HTML
        const radioDescarte = document.querySelector('input[name="filtroDescarte"][value="todos"]');
        if (radioDescarte) radioDescarte.checked = true;
        const radioFavoritos = document.querySelector('input[name="filtroFavoritos"][value="todos"]');
        if (radioFavoritos) radioFavoritos.checked = true;

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

    /**
         * Busca no servidor quais valores contêm itens que correspondem ao termo.
         * Marca os valor-cards com data-item-match para o filtro saber que tem match de item.
         * @param {string} termo - Termo de busca
         * @param {function} callback - Função a chamar após a busca
         */
    function buscarItensServidor(termo, callback) {
        // Limpar marcações anteriores
        document.querySelectorAll('.valor-card').forEach(vc => {
            vc.removeAttribute('data-item-match');
        });

        $.ajax({
            url: 'bd/entidade/buscarItensEntidade.php',
            type: 'GET',
            data: { busca: termo },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data.length > 0) {
                    response.data.forEach(function (r) {
                        const vc = document.getElementById('valor-' + r.cdValor);
                        if (vc) {
                            // Marcar que este valor tem itens matching
                            vc.setAttribute('data-item-match', r.totalMatch);
                        }
                    });
                }
                if (typeof callback === 'function') callback();
            },
            error: function () {
                // Em caso de erro, continua com filtro local
                if (typeof callback === 'function') callback();
            }
        });
    }

    /**
     * Fecha o modal e, se for o modalItem com itens adicionados,
     * recarrega a página uma única vez preservando filtros e acordeões.
     */
    function fecharModal(modalId) {
        document.getElementById(modalId).classList.remove('active');

        // Se houve inclusão de itens, recarrega apenas os itens do valor afetado
        if (modalId === 'modalItem' && _itemAdicionado) {
            _itemAdicionado = false;
            const cdValorAfetado = document.getElementById('inputItemValor').value;
            if (cdValorAfetado) {
                delete _itensCache[cdValorAfetado];
                carregarItensValor(cdValorAfetado, true);
            }
        }
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
                    fecharModal('modalTipo');
                    // Recarrega a página pois tipos são renderizados server-side
                    // TODO: migrar tipos para AJAX em fase futura
                    saveExpandedState();
                    saveFiltrosState();
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
                    saveFiltrosState();
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
                    fecharModal('modalValor');
                    // Recarrega apenas os valores do tipo afetado (sem reload da página)
                    const cdTipoAfetado = document.getElementById('inputValorTipo').value;
                    if (cdTipoAfetado) {
                        delete _valoresCache[cdTipoAfetado];
                        carregarValoresTipo(cdTipoAfetado, true);
                    }
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

        // Desabilitar botão de salvar para evitar duplo clique
        const btnSalvar = document.querySelector('#formItem button[type="submit"]');
        if (btnSalvar) btnSalvar.disabled = true;

        $.ajax({
            url: 'bd/entidade/salvarItem.php',
            type: 'POST',
            data: { cd, cdValor, cdPonto, dtInicio, dtFim, operacao },
            dataType: 'json',
            success: function (response) {
                if (btnSalvar) btnSalvar.disabled = false;

                if (response.success) {
                    showToast(response.message, 'sucesso');

                    if (cd) {
                        // EDIÇÃO: fecha modal e recarrega apenas os itens do valor afetado
                        fecharModal('modalItem');
                        const cdValorAfetado = document.getElementById('inputItemValor').value;
                        if (cdValorAfetado) {
                            delete _itensCache[cdValorAfetado];
                            carregarItensValor(cdValorAfetado, true);
                        }
                    } else {
                        // INCLUSÃO: marca flag e limpa apenas o campo de ponto
                        // para permitir adicionar outro item sem recarregar
                        _itemAdicionado = true;

                        // Limpar somente o ponto de medição (mantém datas e operação)
                        document.getElementById('inputItemPonto').value = '';
                        document.getElementById('inputItemPontoText').value = '';
                        document.getElementById('btnLimparPontoItem').style.display = 'none';
                        document.getElementById('inputItemPontoDropdown').classList.remove('active');

                        // Foco no campo de ponto para inclusão rápida
                        document.getElementById('inputItemPontoText').focus();
                    }
                } else {
                    showToast(response.message || 'Erro ao salvar', 'erro');
                }
            },
            error: function () {
                if (btnSalvar) btnSalvar.disabled = false;
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
                    saveFiltrosState();
                    // Recarrega valores do tipo pai sem reload da página
                    const tipoCard = document.getElementById('valor-' + cd)?.closest('.tipo-card');
                    const cdTipoAfetado = tipoCard ? tipoCard.id.replace('tipo-', '') : null;
                    if (cdTipoAfetado) {
                        delete _valoresCache[cdTipoAfetado];
                        carregarValoresTipo(cdTipoAfetado, true);
                    }
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
                    if (response.success) {
                        showToast(response.message, 'sucesso');
                        // Recarrega apenas os itens do valor afetado
                        if (cdValor) {
                            delete _itensCache[cdValor];
                            carregarItensValor(cdValor, true);
                        }
                    }
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
    // Lazy Load de Valores (Unidades Operacionais)
    // ============================================

    /**
     * Carrega os valores de um tipo via AJAX.
     * Usa cache para não recarregar se já foi carregado.
     * @param {int} cdTipo - CD_CHAVE do ENTIDADE_TIPO
     * @param {boolean} forceReload - Forçar recarga ignorando cache
     * @param {function} callback - Função chamada após o carregamento (opcional)
     */
    function carregarValoresTipo(cdTipo, forceReload = false, callback = null) {
        const body = document.getElementById('tipo-body-' + cdTipo);
        if (!body) return;

        // Se já carregou e não é forceReload, executa callback e sai
        if (body.dataset.loaded === '1' && !forceReload) {
            if (callback) callback();
            return;
        }

        // Se já está no cache e não é forceReload, renderiza do cache
        if (_valoresCache[cdTipo] && !forceReload) {
            renderizarValoresTipo(cdTipo, _valoresCache[cdTipo]);
            body.dataset.loaded = '1';
            if (callback) callback();
            return;
        }

        // Mostrar loading
        body.innerHTML = `
            <div class="lazy-load-placeholder" style="padding: 24px; text-align: center; color: #94a3b8;">
                <ion-icon name="hourglass-outline" style="font-size: 24px; animation: spin 1s linear infinite;"></ion-icon>
                <p style="margin-top: 8px; font-size: 12px;">Carregando unidades operacionais...</p>
            </div>`;

        $.ajax({
            url: 'bd/entidade/getValoresTipo.php',
            type: 'GET',
            data: { cdTipo: cdTipo },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    _valoresCache[cdTipo] = response.data;
                    renderizarValoresTipo(cdTipo, response.data);
                    body.dataset.loaded = '1';

                    // Atualizar badge de contagem no header do tipo
                    const tipoCard = document.getElementById('tipo-' + cdTipo);
                    if (tipoCard) {
                        const badge = tipoCard.querySelector('.badge-count');
                        if (badge) {
                            badge.textContent = response.total + ' Unidades Operacionais';
                        }
                    }

                    // Re-aplicar filtros se houver busca ativa
                    const filtroBusca = document.getElementById('filtroBusca');
                    if (filtroBusca && filtroBusca.value.trim()) {
                        aplicarFiltros();
                    }

                    if (callback) callback();
                } else {
                    body.innerHTML = `
                        <div class="empty-state" style="padding: 24px;">
                            <ion-icon name="alert-circle-outline" style="color: #ef4444;"></ion-icon>
                            <h3>Erro ao carregar</h3>
                            <p>${response.message || 'Tente novamente'}</p>
                        </div>`;
                }
            },
            error: function () {
                body.innerHTML = `
                    <div class="empty-state" style="padding: 24px;">
                        <ion-icon name="wifi-outline" style="color: #ef4444;"></ion-icon>
                        <h3>Erro de comunicação</h3>
                        <p>Não foi possível carregar as unidades operacionais</p>
                    </div>`;
            }
        });
    }

    /**
     * Renderiza os valores (unidades operacionais) dentro do tipo-card-body.
     * Gera o mesmo HTML que antes era gerado pelo PHP no foreach de valores.
     * @param {int} cdTipo - CD_CHAVE do ENTIDADE_TIPO
     * @param {Array} valores - Array de objetos retornados pelo endpoint
     */
    function renderizarValoresTipo(cdTipo, valores) {
        const body = document.getElementById('tipo-body-' + cdTipo);
        if (!body) return;

        if (!valores || valores.length === 0) {
            body.innerHTML = `
                <div class="empty-state" style="padding: 32px;">
                    <ion-icon name="albums-outline"></ion-icon>
                    <h3>Nenhum valor cadastrado</h3>
                    <p>Adicione unidades operacionais a este tipo</p>
                </div>`;
            return;
        }

        let html = '';

        valores.forEach(function (valor) {
            const fluxoTexto = _fluxosTexto[valor.fluxo] || '-';
            const isFavorito = valor.favorito === 1;
            const favClasse = isFavorito ? 'ativo' : '';
            const favIcon = isFavorito ? 'star' : 'star-outline';
            const favTitle = isFavorito ? 'Remover dos favoritos' : 'Adicionar aos favoritos';
            const nomeEscapado = valor.nome.replace(/'/g, "\\'").replace(/"/g, '&quot;');
            const idEscapado = (valor.id || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
            // Escapa para uso seguro dentro de atributo HTML onclick="..."
            const nomeJson = JSON.stringify(valor.nome).replace(/"/g, '&quot;');
            const idJson = JSON.stringify(valor.id || '').replace(/"/g, '&quot;');

            html += `
                <div class="valor-card" id="valor-${valor.cd}"
                     data-valor-nome="${valor.nome.toLowerCase().replace(/"/g, '&quot;')}"
                     data-valor-id="${(valor.id || '').toLowerCase().replace(/"/g, '&quot;')}"
                     data-favorito="${valor.favorito}">
                    <!-- Valor Header -->
                    <div class="valor-card-header" onclick="toggleValor(${valor.cd})">
                        <div class="valor-card-header-left">
                            <div class="valor-icon">
                                <ion-icon name="albums-outline"></ion-icon>
                            </div>
                            <div class="valor-info">
                                <h4>${valor.nome}</h4>
                                <div class="valor-meta">
                                    <span class="valor-id">ID: ${valor.id || '-'}</span>
                                    <span class="fluxo-badge fluxo-${valor.fluxo}">${fluxoTexto}</span>
                                </div>
                            </div>
                        </div>
                        <div class="valor-card-header-right">
                            <span class="valor-badge-count" id="badge-count-${valor.cd}">${valor.totalItens} Ponto(s) de Medição</span>
                            <div class="header-actions" onclick="event.stopPropagation();">
                                <button class="btn-action favorito ${favClasse}"
                                        onclick="toggleFavorito(${valor.cd}, this)"
                                        title="${favTitle}">
                                    <ion-icon name="${favIcon}"></ion-icon>
                                </button>
                                <button class="btn-action chart"
                                        onclick="abrirGraficoValor(${cdTipo}, ${valor.cd}, '${idEscapado}')"
                                        title="Gráficos">
                                    <ion-icon name="bar-chart-outline"></ion-icon>
                                </button>`;

            // Botões de edição (se tem permissão)
            if (_podeEditar) {
                html += `
                                <button class="btn-action edit"
                                        onclick="abrirModalValor(${valor.cd}, ${nomeJson}, ${idJson}, ${cdTipo}, '${valor.fluxo || ''}')"
                                        title="Editar">
                                    <ion-icon name="pencil-outline"></ion-icon>
                                </button>
                                <button class="btn-action delete" onclick="excluirValor(${valor.cd})"
                                        title="Excluir">
                                    <ion-icon name="trash-outline"></ion-icon>
                                </button>`;
            }

            html += `
                            </div>
                            <div class="valor-expand-icon">
                                <ion-icon name="chevron-down-outline"></ion-icon>
                            </div>
                        </div>
                    </div>

                    <!-- Valor Body (Itens) - Carregado via AJAX (lazy load) -->
                    <div class="valor-card-body" id="valor-body-${valor.cd}" data-loaded="0"
                         data-total="${valor.totalItens}">
                        <div class="lazy-load-placeholder" style="padding: 24px; text-align: center; color: #94a3b8;">
                            <ion-icon name="hourglass-outline" style="font-size: 24px; animation: spin 1s linear infinite;"></ion-icon>
                            <p style="margin-top: 8px; font-size: 12px;">Carregando pontos de medição...</p>
                        </div>
                    </div>
                </div>`;
        });

        body.innerHTML = html;
    }

    // ============================================
    // Lazy Load de Itens (Pontos de Medição)
    // ============================================

    /**
     * Carrega os itens de um valor via AJAX.
     * Usa cache para não recarregar se já foi carregado.
     * @param {int} cdValor - CD_CHAVE do ENTIDADE_VALOR
     * @param {boolean} forceReload - Forçar recarga ignorando cache
     */
    function carregarItensValor(cdValor, forceReload = false) {
        console.log('[carregarItensValor] Chamado para valor:', cdValor, 'forceReload:', forceReload);
        const body = document.getElementById('valor-body-' + cdValor);
        if (!body) {
            console.error('[carregarItensValor] BODY NÃO ENCONTRADO: valor-body-' + cdValor); return;
        }

        // Se já carregou e não é forceReload, não faz nada
        if (body.dataset.loaded === '1' && !forceReload) return;

        // Mostrar loading
        body.innerHTML = `
            <div class="lazy-load-placeholder" style="padding: 24px; text-align: center; color: #94a3b8;">
                <ion-icon name="hourglass-outline" style="font-size: 24px; animation: spin 1s linear infinite;"></ion-icon>
                <p style="margin-top: 8px; font-size: 12px;">Carregando pontos de medição...</p>
            </div>`;

        $.ajax({
            url: 'bd/entidade/getItensValor.php',
            type: 'GET',
            data: { cdValor: cdValor },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // Guardar no cache
                    _itensCache[cdValor] = response.data;
                    // Renderizar
                    renderizarItensValor(cdValor, response.data, response.temNrOrdem);
                    body.dataset.loaded = '1';

                    // Atualizar badge de contagem
                    const badge = document.getElementById('badge-count-' + cdValor);
                    if (badge) {
                        badge.textContent = response.total + ' Ponto(s) de Medição';
                    }

                    // Re-inicializar drag and drop para os novos elementos
                    initDragAndDrop();

                    // Re-aplicar filtros se houver um ativo
                    const filtroBusca = document.getElementById('filtroBusca');
                    if (filtroBusca && filtroBusca.value.trim()) {
                        aplicarFiltrosItensCarregados(cdValor);
                    }
                } else {
                    body.innerHTML = `
                        <div class="empty-state" style="padding: 24px;">
                            <ion-icon name="alert-circle-outline" style="color: #ef4444;"></ion-icon>
                            <h3>Erro ao carregar</h3>
                            <p>${response.message || 'Tente novamente'}</p>
                        </div>`;
                }
            },
            error: function () {
                body.innerHTML = `
                    <div class="empty-state" style="padding: 24px;">
                        <ion-icon name="wifi-outline" style="color: #ef4444;"></ion-icon>
                        <h3>Erro de comunicação</h3>
                        <p>Não foi possível carregar os pontos de medição</p>
                    </div>`;
            }
        });
    }

    /**
     * Renderiza os itens (pontos de medição) dentro do valor-card-body.
     * Gera o mesmo HTML que antes era gerado pelo PHP.
     * @param {int} cdValor - CD_CHAVE do ENTIDADE_VALOR
     * @param {Array} itens - Array de objetos com dados dos itens
     * @param {boolean} temNrOrdem - Se a coluna NR_ORDEM existe
     */
    function renderizarItensValor(cdValor, itens, temNrOrdem) {
        const body = document.getElementById('valor-body-' + cdValor);
        if (!body) return;

        if (!itens || itens.length === 0) {
            body.innerHTML = `
                <div class="empty-state" style="padding: 24px;">
                    <ion-icon name="location-outline"></ion-icon>
                    <h3>Nenhum ponto vinculado</h3>
                    <p>Adicione pontos de medição a esta unidade operacional</p>
                </div>`;
            return;
        }

        // Buscar o cdTipo do card pai para o botão de gráfico
        const valorCard = document.getElementById('valor-' + cdValor);
        const tipoCard = valorCard ? valorCard.closest('.tipo-card') : null;
        const cdTipo = tipoCard ? tipoCard.id.replace('tipo-', '') : '';
        const valorEntidadeId = valorCard ? (valorCard.querySelector('.valor-id')?.textContent?.replace('ID: ', '').trim() || '') : '';

        let html = '<div class="itens-sortable" data-valor-cd="' + cdValor + '">';

        itens.forEach(function (item) {
            const pontoLabel = (item.pontoCodigo || '') + ' ' + (item.pontoNome || '');
            const pontoLabelJson = JSON.stringify(pontoLabel).replace(/'/g, "\\'");
            const operacao = item.operacao;
            const draggable = (_podeEditar && temNrOrdem) ? 'draggable="true"' : '';

            html += `
                <div class="item-row ${item.expirado ? 'expired' : ''}" data-item-cd="${item.cd}" ${draggable}
                    data-ponto-nome="${(item.pontoNome || '').toLowerCase()}"
                    data-ponto-codigo="${(item.pontoCodigo || '').toLowerCase()}"
                    data-tag-vazao="${(item.tagVazao || '').toLowerCase()}"
                    data-tag-pressao="${(item.tagPressao || '').toLowerCase()}"
                    data-tag-volume="${(item.tagVolume || '').toLowerCase()}"
                    data-tag-reservatorio="${(item.tagReservatorio || '').toLowerCase()}"
                    data-tag-temp-agua="${(item.tagTempAgua || '').toLowerCase()}"
                    data-tag-temp-ambiente="${(item.tagTempAmbiente || '').toLowerCase()}">`;

            // Drag handle
            if (_podeEditar && temNrOrdem) {
                html += `<div class="item-drag-handle" title="Arraste para reordenar">
                            <ion-icon name="reorder-three-outline"></ion-icon>
                         </div>`;
            }

            html += `<div class="item-row-left">
                        <div class="item-icons">
                            <ion-icon name="location-outline" class="icon-location"></ion-icon>`;

            // Ícone de operação (+/-)
            if (operacao !== null && operacao !== '' && operacao !== undefined) {
                const iconName = operacao == 1 ? 'add-circle' : 'remove-circle';
                html += `<ion-icon name="${iconName}" class="icon-operacao operacao-${operacao}"></ion-icon>`;
            }

            html += `</div>
                     <div class="item-info">
                        <h5>
                            <strong>${escapeHtml(item.pontoCodigo || '')}</strong>
                            ${escapeHtml(item.pontoNome || 'Ponto #' + item.cdPonto)}
                        </h5>
                        <span>${item.periodoTexto}</span>`;

            // TAGs
            if (item.tags && item.tags.length > 0) {
                html += `<div class="item-tags">
                            <ion-icon name="pricetag-outline"></ion-icon>
                            ${escapeHtml(item.tags.join(' | '))}
                         </div>`;
            }

            html += `</div></div>`; // fecha item-row-left

            // Botões de ação
            html += `<div class="item-row-right">
                        <button class="btn-action view"
                            onclick="window.open('pontoMedicaoView.php?id=${item.cdPonto}', '_blank')"
                            title="Ver Ponto">
                            <ion-icon name="eye-outline"></ion-icon>
                        </button>
                        <button class="btn-action chart"
                            onclick="abrirGraficoPonto(${cdTipo}, ${cdValor}, '${valorEntidadeId}', ${item.cdPonto})"
                            title="Ver Gráfico">
                            <ion-icon name="stats-chart-outline"></ion-icon>
                        </button>`;

            if (_podeEditar) {
                html += `<button class="btn-action edit"
                            onclick="abrirModalItem(${item.cd}, ${item.cdPonto}, '${item.dtInicioVal || ''}', '${item.dtFimVal || ''}', ${cdValor}, ${pontoLabelJson}, '${operacao || ''}')"
                            title="Editar">
                            <ion-icon name="pencil-outline"></ion-icon>
                         </button>
                         <button class="btn-action delete"
                            onclick="excluirItem(${item.cd}, ${cdValor})"
                            title="Excluir">
                            <ion-icon name="trash-outline"></ion-icon>
                         </button>`;
            }

            html += `</div>`; // fecha item-row-right
            html += `</div>`; // fecha item-row
        });

        html += '</div>'; // fecha itens-sortable
        body.innerHTML = html;
    }

    /**
     * Escape de HTML para prevenir XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // ============================================
    // FUNCAO 3: aplicarFiltrosItensCarregados - SUBSTITUIR TODA
    // ============================================

    /**
     * Aplica filtros nos itens recem-carregados de um valor especifico.
     * Se o match foi no tipo ou valor pai, TODOS os itens ficam visiveis.
     * Itens que TAMBEM atendem ao filtro recebem highlight amarelo.
     */
    function aplicarFiltrosItensCarregados(cdValor) {
        const termo = normalizarTexto(document.getElementById('filtroBusca').value.trim());
        if (!termo) return;

        const valorCard = document.getElementById('valor-' + cdValor);
        if (!valorCard) return;

        // Verificar se o match eh no valor ou no tipo pai (heranca)
        const valorNome = normalizarTexto(valorCard.dataset.valorNome || '');
        const valorId = normalizarTexto(valorCard.dataset.valorId || '');
        const tipoCard = valorCard.closest('.tipo-card');
        const tipoNome = tipoCard ? normalizarTexto(tipoCard.dataset.tipoNome || '') : '';
        const tipoId = tipoCard ? normalizarTexto(tipoCard.dataset.tipoId || '') : '';

        const matchNoValor = valorNome.includes(termo) || valorId.includes(termo);
        const matchNoTipo = tipoNome.includes(termo) || tipoId.includes(termo);
        const matchPai = matchNoValor || matchNoTipo;

        const itensRows = valorCard.querySelectorAll('.item-row');

        itensRows.forEach(itemRow => {
            // Limpar estado anterior
            itemRow.classList.remove('filtro-oculto', 'filtro-match', 'filtro-match-direto', 'item-match-highlight');
            itemRow.querySelectorAll('.badge-match').forEach(b => b.remove());

            const itemMatch = verificarMatchItem(itemRow, termo);

            if (matchPai) {
                // Match herdado do pai: TODOS os itens ficam visiveis
                // Se o item TAMBEM atende ao filtro, highlight amarelo
                if (itemMatch) {
                    itemRow.classList.add('item-match-highlight', 'filtro-match', 'filtro-match-direto');
                    const campo = getMatchField(itemRow, termo);
                    adicionarBadgeMatchItem(itemRow, 'ponto', campo);
                }
            } else if (itemMatch) {
                // Match direto no item
                itemRow.classList.add('item-match-highlight', 'filtro-match', 'filtro-match-direto');
                const campo = getMatchField(itemRow, termo);
                adicionarBadgeMatchItem(itemRow, 'ponto', campo);
            } else {
                // Sem match direto: marca para decisão posterior
                itemRow.classList.add('filtro-sem-match');
            }
        });

        // Se algum item deu match OU match no pai, mostrar TODOS os irmãos
        const algumMatch = matchPai || valorCard.querySelector('.item-row.item-match-highlight');
        if (algumMatch) {
            valorCard.querySelectorAll('.item-row.filtro-sem-match').forEach(ir => {
                ir.classList.remove('filtro-sem-match');
            });
        } else {
            valorCard.querySelectorAll('.item-row.filtro-sem-match').forEach(ir => {
                ir.classList.remove('filtro-sem-match');
                ir.classList.add('filtro-oculto');
            });
        }
    }

    /**
     * Verifica se um item-row corresponde ao termo de busca.
     * @param {HTMLElement} itemRow - Elemento .item-row
     * @param {string} termo - Termo normalizado
     * @returns {boolean}
     */
    function verificarMatchItem(itemRow, termo) {
        const campos = [
            'pontoNome', 'pontoCodigo', 'tagVazao', 'tagPressao',
            'tagVolume', 'tagReservatorio', 'tagTempAgua', 'tagTempAmbiente'
        ];
        return campos.some(c => normalizarTexto(itemRow.dataset[c] || '').includes(termo));
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
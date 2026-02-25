<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Modelos de Machine Learning (XGBoost)
 * 
 * Tela para gerenciar modelos treinados:
 *  - Listar modelos existentes com métricas
 *  - Treinar novos modelos por ponto de medição
 *  - Retreinar modelos existentes (force)
 *  - Visualizar métricas detalhadas (MAE, RMSE, R², MAPE, features)
 * 
 * Usa endpoints existentes do predicaoTensorFlow.php:
 *  - acao=status  → lista modelos
 *  - acao=train   → treina/retreina modelo
 *  - acao=health  → verifica serviço online
 * 
 * @author Bruno - CESAN
 * @version 1.0
 * @date 2026-02
 */

$paginaAtual = 'modelosML';

include_once 'includes/header.inc.php';
include_once 'bd/conexao.php';

// Recarregar permissões do banco
recarregarPermissoesUsuario();

// Permissão (usa mesma permissão de Treinamento IA)
exigePermissaoTela('Treinamento IA', ACESSO_LEITURA);
$podeEditar = podeEditarTela('Treinamento IA');

include_once 'includes/menu.inc.php';

// Buscar pontos de medição ativos (para dropdown de treino)
$pontosMedicao = [];
try {
    $sqlPontos = "
        SELECT 
            PM.CD_PONTO_MEDICAO,
            PM.DS_NOME,
            PM.ID_TIPO_MEDIDOR,
            TM.DS_NOME AS DS_TIPO_MEDIDOR,
            L.DS_NOME AS DS_LOCALIDADE,
            U.DS_NOME AS DS_UNIDADE,
            COALESCE(PM.DS_TAG_VAZAO, PM.DS_TAG_PRESSAO, PM.DS_TAG_RESERVATORIO, PM.DS_TAG_VOLUME, PM.DS_TAG_TEMP_AGUA, PM.DS_TAG_TEMP_AMBIENTE) AS DS_TAG
        FROM SIMP.dbo.PONTO_MEDICAO PM
        LEFT JOIN SIMP.dbo.TIPO_MEDIDOR TM ON TM.CD_CHAVE = PM.ID_TIPO_MEDIDOR
        LEFT JOIN SIMP.dbo.LOCALIDADE L ON L.CD_CHAVE = PM.CD_LOCALIDADE
        LEFT JOIN SIMP.dbo.UNIDADE U ON U.CD_UNIDADE = L.CD_UNIDADE
        WHERE (PM.DT_DESATIVACAO IS NULL OR PM.DT_DESATIVACAO > GETDATE())
          AND EXISTS (
              SELECT 1 FROM SIMP.dbo.AUX_RELACAO_PONTOS_MEDICAO R
              WHERE R.TAG_PRINCIPAL IN (PM.DS_TAG_VAZAO, PM.DS_TAG_PRESSAO, PM.DS_TAG_RESERVATORIO, PM.DS_TAG_VOLUME, PM.DS_TAG_TEMP_AGUA, PM.DS_TAG_TEMP_AMBIENTE)
                 OR R.TAG_AUXILIAR IN (PM.DS_TAG_VAZAO, PM.DS_TAG_PRESSAO, PM.DS_TAG_RESERVATORIO, PM.DS_TAG_VOLUME, PM.DS_TAG_TEMP_AGUA, PM.DS_TAG_TEMP_AMBIENTE)
          )
        ORDER BY PM.DS_NOME
    ";
    $stmtPontos = $pdoSIMP->query($sqlPontos);
    $pontosMedicao = $stmtPontos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pontosMedicao = [];
}

// Buscar sistemas de água do flowchart (nós cujo nível tem OP_EH_SISTEMA = 1)
$sistemasFlowchart = [];
try {
    $sqlSistemas = "
        SELECT 
            EN.CD_CHAVE,
            EN.DS_NOME AS DS_SISTEMA_AGUA
        FROM SIMP.dbo.ENTIDADE_NODO EN
        INNER JOIN SIMP.dbo.ENTIDADE_NIVEL NV 
            ON NV.CD_CHAVE = EN.CD_ENTIDADE_NIVEL
        WHERE EN.OP_ATIVO = 1
          AND NV.OP_EH_SISTEMA = 1
        ORDER BY EN.DS_NOME
    ";
    $stmtSis = $pdoSIMP->query($sqlSistemas);
    $sistemasFlowchart = $stmtSis->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sistemasFlowchart = [];
}
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<link rel="stylesheet" href="style/css/modelosML.css">

<div class="page-container">

    <!-- ============================================
         Page Header
         ============================================ -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="hardware-chip"></ion-icon>
                </div>
                <div>
                    <h1>Modelos de Machine Learning</h1>
                    <p class="page-header-subtitle">Gerenciamento de modelos XGBoost treinados para predição</p>
                </div>
            </div>
            <div class="page-header-actions">
                <!-- Status do serviço TensorFlow -->
                <div class="service-badge" id="serviceBadge">
                    <span class="status-dot" id="statusDot"></span>
                    <span id="serviceStatusText">Verificando...</span>
                </div>
                <?php if ($podeEditar): ?>
                    <button type="button" class="btn-novo-treino" onclick="abrirModalTreino()">
                        <ion-icon name="add-outline"></ion-icon>
                        Novo Treino
                    </button>
                    <button type="button" class="btn-novo-treino" onclick="abrirModalAssociacoes()"
                        style="background: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.2);">
                        <ion-icon name="git-network-outline"></ion-icon>
                        Associações
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================================
         Stats Cards
         ============================================ -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-card-icon total">
                <ion-icon name="cube-outline"></ion-icon>
            </div>
            <div class="stat-card-info">
                <h3 id="statTotal">-</h3>
                <p>Modelos treinados</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon xgboost">
                <ion-icon name="git-branch-outline"></ion-icon>
            </div>
            <div class="stat-card-info">
                <h3 id="statXgboost">-</h3>
                <p>XGBoost v5.0</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon good">
                <ion-icon name="checkmark-circle-outline"></ion-icon>
            </div>
            <div class="stat-card-info">
                <h3 id="statGood">-</h3>
                <p>R² &ge; 0.70</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon bad">
                <ion-icon name="alert-circle-outline"></ion-icon>
            </div>
            <div class="stat-card-info">
                <h3 id="statBad">-</h3>
                <p>R² &lt; 0.70</p>
            </div>
        </div>
    </div>

    <!-- Painel de progresso do treino em background -->
    <div class="train-progress-panel" id="trainProgressPanel" style="display:none;">
        <div class="train-progress-header">
            <div class="train-progress-title">
                <ion-icon name="sync-outline" class="spin-icon" id="trainProgressIcon"></ion-icon>
                <strong id="trainProgressTitulo">Treino em andamento...</strong>
            </div>
            <button class="train-progress-close" id="btnFecharProgresso" onclick="fecharPainelProgresso()"
                title="Fechar" style="display:none;">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="train-progress-body">
            <div class="train-progress-bar-wrapper">
                <div class="train-progress-bar" id="trainProgressBar" style="width:0%"></div>
            </div>
            <div class="train-progress-stats">
                <span class="tps-item sucesso"><ion-icon name="checkmark-circle-outline"></ion-icon> <span
                        id="trainSucesso">0</span> sucesso</span>
                <span class="tps-item falha"><ion-icon name="close-circle-outline"></ion-icon> <span
                        id="trainFalha">0</span> falha</span>
                <span class="tps-item total"><ion-icon name="layers-outline"></ion-icon> <span id="trainTotal">0</span>
                    total</span>
                <span class="tps-item tempo" id="trainTempo"></span>
            </div>
            <div class="train-progress-msg" id="trainProgressMsg">Aguardando...</div>
        </div>
    </div>

    <!-- ============================================
     Banner de Sincronização Flowchart ↔ ML
     ============================================ -->
    <div class="sync-banner" id="syncBanner">
        <div class="sync-banner-icon">
            <ion-icon name="git-network-outline" id="syncBannerIcon"></ion-icon>
        </div>
        <div class="sync-banner-text">
            <h4 id="syncBannerTitle">Verificando...</h4>
            <p id="syncBannerMsg">Comparando topologia do Flowchart com as relações ML</p>
        </div>
        <div class="sync-banner-actions">
            <button type="button" class="btn-sync rules" onclick="abrirModalRegras()" title="Ver regras de derivação">
                <ion-icon name="help-circle-outline"></ion-icon> Regras
            </button>
            <button type="button" class="btn-sync primary" id="btnRevisarSync" onclick="abrirModalSync()"
                style="display:none;">
                <ion-icon name="sync-outline"></ion-icon> Revisar Sincronização
            </button>
        </div>
    </div>


    <!-- ============================================
     Banner de Governanca de Topologia (Fase A1)
     Mostra alerta quando modelos estao desatualizados
     em relacao a topologia do flowchart.
     ============================================ -->
    <div class="gov-banner" id="govBanner">
        <div class="gov-banner-icon">
            <ion-icon name="shield-checkmark-outline" id="govBannerIcon"></ion-icon>
        </div>
        <div class="gov-banner-text">
            <h4 id="govBannerTitle">Verificando governan&ccedil;a...</h4>
            <p id="govBannerMsg">Comparando vers&atilde;o da topologia com modelos treinados</p>
        </div>
        <div class="gov-banner-actions">
            <button class="btn-gov secondary" onclick="abrirModalTimeline()" title="Hist&oacute;rico de vers&otilde;es">
                <ion-icon name="time-outline"></ion-icon> Hist&oacute;rico
            </button>
        </div>
    </div>

    <!-- ============================================
     Modal de Sincronização Flowchart → ML
     ============================================ -->
    <div class="sync-modal-overlay" id="modalSync" onclick="if(event.target===this) fecharModalSync()">
        <div class="sync-modal">
            <!-- Header -->
            <div class="sync-modal-header">
                <h3>
                    <ion-icon name="git-compare-outline"></ion-icon>
                    Sincronizar Flowchart → Relações ML
                </h3>
                <button class="modal-close" onclick="fecharModalSync()">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>

            <!-- Body -->
            <div class="sync-modal-body">
                <!-- Filtro por sistema -->
                <!-- Filtro por sistema -->
                <div class="sync-filter-row">
                    <label>
                        <ion-icon name="water-outline" style="vertical-align:middle;margin-right:4px;"></ion-icon>
                        Sistema de Água:
                    </label>
                    <div class="sync-dropdown-wrapper" id="syncDropdownWrapper">
                        <div class="sync-dropdown-selected" onclick="toggleSyncDropdown()">
                            <span id="syncDropdownLabel">— Todos os Sistemas —</span>
                            <ion-icon name="chevron-down-outline" class="sync-dropdown-arrow"></ion-icon>
                        </div>
                        <div class="sync-dropdown-panel" id="syncDropdownPanel">
                            <input type="text" class="sync-dropdown-search" id="syncDropdownSearch"
                                placeholder="Buscar sistema..." oninput="filtrarSyncSistemas(this.value)">
                            <div class="sync-dropdown-options" id="syncDropdownOptions">
                                <div class="sync-dropdown-option selected" data-value="0"
                                    onclick="selecionarSyncSistema(0, '— Todos os Sistemas —')">
                                    — Todos os Sistemas —
                                </div>
                                <?php foreach ($sistemasFlowchart as $sis): ?>
                                    <div class="sync-dropdown-option" data-value="<?= $sis['CD_CHAVE'] ?>"
                                        onclick="selecionarSyncSistema(<?= $sis['CD_CHAVE'] ?>, '<?= addslashes(htmlspecialchars($sis['DS_SISTEMA_AGUA'], ENT_QUOTES, 'UTF-8')) ?>')">
                                        <?= htmlspecialchars($sis['DS_SISTEMA_AGUA'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Conteúdo dinâmico (preenchido via JS) -->
                <div id="syncConteudo">
                    <div class="sync-loading">
                        <ion-icon name="sync-outline"></ion-icon>
                        <p>Analisando divergências...</p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="sync-modal-footer">
                <div style="font-size:11px;color:#94a3b8;">
                    <ion-icon name="information-circle-outline" style="vertical-align:middle;"></ion-icon>
                    Selecione os itens e clique em Aplicar
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="btn-sync secondary" onclick="fecharModalSync()">
                        Cancelar
                    </button>
                    <button type="button" class="btn-sync primary" id="btnAplicarSync" onclick="aplicarSync()" disabled>
                        <ion-icon name="checkmark-outline"></ion-icon> Aplicar Selecionados
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================
     Modal de Regras de Derivação
     ============================================ -->
    <div class="sync-modal-overlay" id="modalRegras" onclick="if(event.target===this) fecharModalRegras()">
        <div class="sync-modal" style="max-width:650px;">
            <!-- Header -->
            <div class="sync-modal-header" style="background:linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);">
                <h3>
                    <ion-icon name="book-outline"></ion-icon>
                    Regras de Derivação — Flowchart → ML
                </h3>
                <button class="modal-close" onclick="fecharModalRegras()">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>

            <!-- Body -->
            <div class="sync-modal-body">
                <div class="regras-content">

                    <h4><ion-icon name="fitness-outline"></ion-icon> Princípio Hidráulico</h4>
                    <p>
                        Em sistemas de abastecimento de água, pressão e vazão são grandezas acopladas.
                        <strong>Tudo ao redor de um ponto o influencia:</strong> montante (upstream),
                        jusante (downstream) e pontos irmãos na mesma estrutura.
                    </p>

                    <div class="regras-destaque">
                        O modelo XGBoost de cada ponto usa dados dos <strong>vizinhos diretos</strong>
                        (1 hop) como features de entrada para prever o comportamento do ponto principal.
                    </div>

                    <h4><ion-icon name="git-network-outline"></ion-icon> Regra de Vizinhança (1 Hop Bidirecional +
                        Irmãos)</h4>
                    <p>Para cada nó com ponto de medição no Flowchart, são considerados como auxiliares:</p>
                    <div class="regras-diagrama">1. <strong>Vizinhos diretos</strong> — nós conectados por setas
                        (upstream e downstream)
                        Exemplo: ETA ←→ Macro Saída (ambos se veem)

                        2. <strong>Irmãos por origem</strong> — nós que recebem do mesmo pai
                        Exemplo: ETA → Saída 1, ETA → Saída 2 (Saída 1 e 2 são irmãs)

                        3. <strong>Irmãos por destino</strong> — nós que alimentam o mesmo destino
                        Exemplo: Macro A → Reserv., Macro B → Reserv. (A e B são irmãos)</div>

                    <h4><ion-icon name="funnel-outline"></ion-icon> Tipos de Medidor Considerados</h4>
                    <div class="regras-tipo-grid">
                        <div class="regras-tipo-item incluido">
                            <ion-icon name="checkmark-circle-outline"></ion-icon>
                            Macromedidor (Tipo 1)
                        </div>
                        <div class="regras-tipo-item incluido">
                            <ion-icon name="checkmark-circle-outline"></ion-icon>
                            Estação Pitométrica (Tipo 2)
                        </div>
                        <div class="regras-tipo-item incluido">
                            <ion-icon name="checkmark-circle-outline"></ion-icon>
                            Medidor de Pressão (Tipo 4)
                        </div>
                        <div class="regras-tipo-item incluido">
                            <ion-icon name="checkmark-circle-outline"></ion-icon>
                            Nível Reservatório (Tipo 6)
                        </div>
                        <div class="regras-tipo-item excluido">
                            <ion-icon name="close-circle-outline"></ion-icon>
                            Hidrômetro (Tipo 8) — Excluído
                        </div>
                    </div>
                    <p style="font-size:12px;color:#64748b;">
                        <strong>Por que excluir hidrômetros?</strong> Micromedição possui escala (m³/mês)
                        e granularidade temporal (leitura mensal) incompatíveis com macromedição (L/s contínuo).
                        A correlação com macromedidores é muito baixa, gerando ruído no modelo.
                    </p>

                    <h4><ion-icon name="flash-outline"></ion-icon> Exemplo Prático</h4>
                    <div class="regras-diagrama">Manancial ──→ <strong>Macro Captação</strong> ──→ ETA
                        ├──→ <strong>Macro Saída 1</strong>
                        ├──→ <strong>Macro Saída 2</strong>
                        └──→ <strong>Reservatório (Nível)</strong>
                        └──→ <strong>Pressão Saída</strong>

                        Para prever <strong>Macro Saída 1</strong>, auxiliares são:
                        • ETA (vizinho upstream)
                        • Macro Saída 2 (irmã — mesmo pai)
                        • Reservatório Nível (vizinho downstream, se conectado)
                        • Macro Captação (vizinho indireto via ETA)</div>

                </div>
            </div>

            <!-- Footer -->
            <div class="sync-modal-footer" style="justify-content:flex-end;">
                <button type="button" class="btn-sync secondary" onclick="fecharModalRegras()">
                    Fechar
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================
         Modal de Diagnóstico ML
         ============================================ -->
    <div class="diag-modal-overlay" id="modalDiagnostico">
        <div class="diag-modal">
            <div class="diag-modal-header">
                <h3>
                    <ion-icon name="medkit-outline"></ion-icon>
                    Diagnóstico de Treino ML
                </h3>
                <button class="diag-close" onclick="fecharModalDiag()">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>
            <!-- Info do ponto (preenchido via JS) -->
            <div class="diag-ponto-info" id="diagPontoInfo">
                <span class="codigo" id="diagPontoCodigo">—</span>
                <span class="nome" id="diagPontoNome">—</span>
            </div>
            <!-- Corpo: timeline de etapas -->
            <div class="diag-modal-body" id="diagModalBody">
                <div class="diag-loading" id="diagLoading">
                    <ion-icon name="sync-outline"></ion-icon>
                    <p>Executando diagnóstico...</p>
                </div>
                <div id="diagConteudo" style="display:none;"></div>
            </div>
            <!-- Footer -->
            <div class="diag-modal-footer">
                <button class="diag-btn-fechar" onclick="fecharModalDiag()">Fechar</button>
                <button class="diag-btn-treinar" id="diagBtnTreinar" disabled onclick="treinarAposDiag()"
                    style="display:none;">
                    <ion-icon name="rocket-outline" style="vertical-align:middle;margin-right:4px;"></ion-icon>
                    Treinar
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================
         Filtros
         ============================================ -->
    <div class="filters-bar">
        <div class="search-input-wrapper">
            <ion-icon name="search-outline"></ion-icon>
            <input type="text" class="search-input" id="searchInput" placeholder="Buscar por ponto, TAG ou código..."
                oninput="filtrarModelos()">
        </div>
        <select class="filter-select" id="filterQuality" onchange="filtrarModelos()">
            <option value="">Todas as qualidades</option>
            <option value="excellent">Excelente (R² &ge; 0.90)</option>
            <option value="good">Bom (R² &ge; 0.70)</option>
            <option value="fair">Regular (R² &ge; 0.50)</option>
            <option value="poor">Baixo (R² &lt; 0.50)</option>
        </select>
        <select class="filter-select" id="filterTipo" onchange="filtrarModelos()">
            <option value="">Todos os tipos</option>
            <option value="xgboost">XGBoost</option>
        </select>
        <button type="button" class="btn-refresh" id="btnRefresh" onclick="carregarModelos()">
            <ion-icon name="refresh-outline"></ion-icon>
            Atualizar
        </button>
    </div>

    <!-- ============================================
         Grid de Modelos
         ============================================ -->
    <div id="modelsContainer">
        <!-- Preenchido via JavaScript -->
        <div class="empty-state">
            <div class="empty-state-icon">
                <ion-icon name="sync-outline" style="animation: spin 1.5s linear infinite;"></ion-icon>
            </div>
            <h3>Carregando modelos...</h3>
            <p>Conectando ao serviço TensorFlow</p>
        </div>
    </div>
</div>

<!-- ============================================
     Modal: Detalhes do Modelo
     ============================================ -->
<div class="modal-overlay" id="modalDetalhes" onclick="fecharModalDetalhes(event)">
    <div class="modal-container" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h2>
                <ion-icon name="analytics-outline"></ion-icon>
                <span id="modalDetalhesTitle">Detalhes do Modelo</span>
            </h2>
            <button class="btn-close" onclick="fecharModalDetalhes()">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-body" id="modalDetalhesBody">
            <!-- Preenchido via JavaScript -->
        </div>
    </div>
</div>

<!-- ============================================
     Modal: Novo Treino
     ============================================ -->
<?php if ($podeEditar): ?>
    <div class="modal-overlay" id="modalTreino" onclick="fecharModalTreino(event)">
        <div class="modal-container" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h2>
                    <ion-icon name="fitness-outline"></ion-icon>
                    Treinar Novo Modelo
                </h2>
                <button class="btn-close" onclick="fecharModalTreino()">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>
            <div class="modal-body">
                <!-- Modo de treino -->
                <div class="train-form-group">
                    <label>
                        <ion-icon name="options-outline"></ion-icon>
                        Modo de Treino
                    </label>
                    <select id="selectModoTreino" onchange="toggleModoPonto()">
                        <option value="unico">Ponto específico</option>
                        <option value="todos">Todos os pontos (período fixo)</option>
                        <option value="otimizado">Todos os pontos (otimizado por R²)</option>
                    </select>
                    <div class="train-form-hint" id="hintModo">
                        Treinar modelo para um único ponto de medição
                    </div>
                </div>

                <!-- Ponto de Medição (visível só no modo único) -->
                <div class="train-form-group" id="grupoPonto">
                    <label>
                        <ion-icon name="pin-outline"></ion-icon>
                        Ponto de Medição
                    </label>
                    <select id="selectPontoTreino" style="width: 100%;">
                        <option value="">Selecione um ponto de medição...</option>
                        <?php foreach ($pontosMedicao as $pm): ?>
                            <option value="<?= $pm['CD_PONTO_MEDICAO'] ?>" data-tipo="<?= $pm['ID_TIPO_MEDIDOR'] ?>"
                                data-tag="<?= htmlspecialchars($pm['DS_TAG'] ?? '') ?>">
                                <?= htmlspecialchars(
                                    $pm['CD_PONTO_MEDICAO'] . ' - ' .
                                    $pm['DS_NOME'] .
                                    ($pm['DS_TIPO_MEDIDOR'] ? ' (' . $pm['DS_TIPO_MEDIDOR'] . ')' : '') .
                                    ($pm['DS_UNIDADE'] ? ' - ' . $pm['DS_UNIDADE'] : '')
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Semanas de histórico -->
                <div class="train-form-group">
                    <label>
                        <ion-icon name="calendar-outline"></ion-icon>
                        Semanas de Histórico
                    </label>
                    <select id="selectSemanas">
                        <option value="12">12 semanas (3 meses)</option>
                        <option value="24" selected>24 semanas (6 meses)</option>
                        <option value="36">36 semanas (9 meses)</option>
                        <option value="52">52 semanas (1 ano)</option>
                    </select>
                    <div class="train-form-hint">
                        Mais semanas = modelo mais robusto, porém treino mais demorado
                    </div>
                </div>

                <!-- Forçar retreino -->
                <div class="train-form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="chkForce" style="width: auto;">
                        <span>Forçar retreino (sobrescrever modelo existente)</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal btn-cancel" onclick="fecharModalTreino()">
                    Cancelar
                </button>
                <button type="button" class="btn-modal btn-primary" id="btnIniciarTreino" onclick="iniciarTreino()">
                    <ion-icon name="flash-outline"></ion-icon>
                    Iniciar Treinamento
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ============================================
     Modal: Retreinar Modelo
     ============================================ -->
<?php if ($podeEditar): ?>
    <div class="modal-overlay" id="modalRetreino" onclick="fecharModalRetreino(event)">
        <div class="modal-container" style="max-width: 440px;" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h2>
                    <ion-icon name="refresh-outline"></ion-icon>
                    Retreinar Modelo <span id="retreinoPonto"></span>
                </h2>
                <button class="btn-close" onclick="fecharModalRetreino()">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>
            <div class="modal-body">
                <!-- Campos ocultos -->
                <input type="hidden" id="retreinoCdPonto">
                <input type="hidden" id="retreinoTipoMedidor">

                <!-- Período de histórico -->
                <div class="train-form-group">
                    <label>
                        <ion-icon name="calendar-outline"></ion-icon>
                        Período de histórico
                    </label>
                    <select id="selectSemanasRetreino">
                        <option value="12">12 semanas (3 meses)</option>
                        <option value="24" selected>24 semanas (6 meses)</option>
                        <option value="36">36 semanas (9 meses)</option>
                        <option value="52">52 semanas (1 ano)</option>
                        <option value="78">78 semanas (1 ano e meio)</option>
                        <option value="104">104 semanas (2 anos)</option>
                    </select>
                    <div class="train-form-hint">
                        Mais semanas = modelo mais robusto, porém treino mais demorado
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal btn-cancel" onclick="fecharModalRetreino()">
                    Cancelar
                </button>
                <button type="button" class="btn-modal btn-primary" onclick="confirmarRetreino()">
                    <ion-icon name="flash-outline"></ion-icon>
                    Retreinar
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ============================================
     Modal: Associações TAG Principal → Auxiliares
     ============================================ -->
<?php if ($podeEditar): ?>
    <div class="modal-overlay" id="modalAssociacoes" onclick="fecharModalAssociacoes(event)">
        <div class="modal-container" style="max-width: 920px;" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h2>
                    <ion-icon name="git-network-outline"></ion-icon>
                    Associações de Tags (Principal → Auxiliares)
                </h2>
                <button class="btn-close" onclick="fecharModalAssociacoes()">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>
            <div class="modal-body">
                <!-- Nova associação -->
                <div class="assoc-new-section">
                    <label>
                        <ion-icon name="add-circle-outline"></ion-icon>
                        Adicionar nova TAG principal
                    </label>
                    <div class="assoc-new-row">
                        <select id="selectNovaTagPrincipal" style="width:100%;">
                            <option value="">Selecione um ponto / TAG...</option>
                        </select>
                        <button type="button" class="btn-new-assoc" onclick="criarNovaPrincipal()">
                            <ion-icon name="add-outline"></ion-icon>
                            Criar
                        </button>
                    </div>
                </div>

                <!-- Layout principal -->
                <div class="assoc-layout">
                    <!-- Lista de TAGs principais -->
                    <div class="assoc-lista-panel">
                        <div class="assoc-lista-header">
                            <ion-icon name="pricetag-outline"></ion-icon>
                            TAGs Principais
                            <span id="assocTotalPrincipais" style="margin-left:auto; font-size:10px; color:#94a3b8;"></span>
                        </div>
                        <div class="assoc-lista-search">
                            <input type="text" id="assocSearchPrincipal" placeholder="Filtrar TAGs..."
                                oninput="filtrarPrincipais()">
                        </div>
                        <div class="assoc-lista-body" id="assocListaPrincipais">
                            <div class="assoc-lista-empty">Carregando...</div>
                        </div>
                    </div>

                    <!-- Detalhe das auxiliares -->
                    <div class="assoc-detail-panel">
                        <div class="assoc-detail-header" id="assocDetailHeader" style="display:none;">
                            <div class="assoc-detail-title">
                                Auxiliares de <span class="tag-highlight" id="assocTagSelecionada"></span>
                            </div>
                            <button type="button" class="btn-excluir-tag" onclick="excluirPrincipal()">
                                <ion-icon name="trash-outline"></ion-icon>
                                Excluir TAG
                            </button>
                        </div>
                        <div class="assoc-add-row" id="assocAddRow" style="display:none;">
                            <select id="selectNovaAuxiliar" style="width:100%;">
                                <option value="">Selecione uma TAG auxiliar...</option>
                            </select>
                            <button type="button" class="btn-add-aux" onclick="adicionarAuxiliar()">
                                <ion-icon name="add-outline"></ion-icon>
                                Adicionar
                            </button>
                        </div>
                        <div class="assoc-detail-body" id="assocDetailBody">
                            <div class="assoc-detail-empty">
                                <ion-icon name="arrow-back-outline"></ion-icon>
                                Selecione uma TAG principal para ver suas auxiliares
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ============================================
     Loading Overlay (para treino)
     ============================================ -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-box">
        <ion-icon name="sync-outline"></ion-icon>
        <p id="loadingText">Treinando modelo...</p>
        <div class="loading-sub" id="loadingSub">Isso pode levar alguns minutos</div>
    </div>
</div>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    /**
     * SIMP - Modelos de Machine Learning
     * Gerenciamento frontend dos modelos XGBoost
     * 
     * @author Bruno - CESAN
     * @version 1.0
     */

    // ============================================
    // Variáveis globais
    // ============================================

    /** Lista completa de modelos carregados do serviço */
    let todosModelos = [];

    /** Indica se o serviço TensorFlow está online */
    let servicoOnline = false;

    /** Permissão de edição do usuário */
    const podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;

    // ============================================
    // Inicialização
    // ============================================

    document.addEventListener('DOMContentLoaded', function () {
        // Inicializar Select2 no modal de treino (com busca)
        if (document.getElementById('selectPontoTreino')) {
            /**
             * Matcher customizado: pesquisa por código, nome, tipo e TAG
             */
            function matcherPontoTreino(params, data) {
                if (!params.term || params.term.trim() === '') return data;
                if (!data.element) return null;

                var termo = params.term.toLowerCase();
                var tag = (data.element.dataset.tag || '').toLowerCase();
                var texto = (data.text || '').toLowerCase();

                // Pesquisa no texto visível e na TAG
                if (texto.indexOf(termo) > -1 || tag.indexOf(termo) > -1) {
                    return data;
                }
                return null;
            }

            $('#selectPontoTreino').select2({
                placeholder: 'Buscar por código, nome ou TAG...',
                allowClear: true,
                dropdownParent: $('#modalTreino'),
                width: '100%',
                matcher: matcherPontoTreino,
                language: {
                    noResults: function () { return 'Nenhum ponto encontrado'; },
                    searching: function () { return 'Buscando...'; }
                }
            });

            // Autofocus no campo de busca ao abrir
            $('#selectPontoTreino').on('select2:open', function () {
                setTimeout(function () {
                    var campo = document.querySelector('.select2-container--open .select2-search__field');
                    if (campo) campo.focus();
                }, 0);
            });
        }

        // Verificar status do serviço e carregar modelos
        verificarServico();
        carregarModelos();
        verificarSyncFlowchart();

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                fecharModalDetalhes();
                fecharModalTreino();
                fecharModalRetreino();
                fecharModalAssociacoes();
            }
        });
    });

    // ============================================
    // Verificar status do serviço TensorFlow
    // ============================================

    /**
     * Chama o health check do serviço TensorFlow.
     * Atualiza o badge de status no header.
     */
    function verificarServico() {
        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'health' })
        })
            .then(r => r.json())
            .then(data => {
                const dot = document.getElementById('statusDot');
                const text = document.getElementById('serviceStatusText');

                if (data.success && data.tensorflow && data.tensorflow.status === 'ok') {
                    servicoOnline = true;
                    dot.className = 'status-dot online';
                    text.textContent = 'TensorFlow Online';
                } else {
                    servicoOnline = false;
                    dot.className = 'status-dot offline';
                    text.textContent = 'TensorFlow Offline';
                }
            })
            .catch(() => {
                servicoOnline = false;
                document.getElementById('statusDot').className = 'status-dot offline';
                document.getElementById('serviceStatusText').textContent = 'TensorFlow Offline';
            });
    }

    // ============================================
    // Carregar modelos do serviço
    // ============================================

    /**
     * Busca a lista de modelos treinados via predicaoTensorFlow.php?acao=status.
     * Atualiza os cards e estatísticas na tela.
     */
    function carregarModelos() {
        const btn = document.getElementById('btnRefresh');
        btn.classList.add('loading');

        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'status' })
        })
            .then(r => r.json())
            .then(data => {
                btn.classList.remove('loading');

                if (data.success && data.modelos) {
                    todosModelos = data.modelos;
                    atualizarEstatisticas();
                    filtrarModelos();
                } else {
                    // Serviço offline ou erro
                    todosModelos = [];
                    atualizarEstatisticas();
                    document.getElementById('modelsContainer').innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <ion-icon name="cloud-offline-outline"></ion-icon>
                        </div>
                        <h3>Serviço indisponível</h3>
                        <p>${data.error || 'Não foi possível conectar ao serviço TensorFlow'}</p>
                    </div>
                `;
                }
            })
            .catch(err => {
                btn.classList.remove('loading');
                todosModelos = [];
                atualizarEstatisticas();
                document.getElementById('modelsContainer').innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <ion-icon name="warning-outline"></ion-icon>
                    </div>
                    <h3>Erro de conexão</h3>
                    <p>${err.message}</p>
                </div>
            `;
            });
    }

    // ============================================
    // Atualizar estatísticas
    // ============================================

    /**
     * Atualiza os cards de resumo (total, XGBoost, bons, ruins).
     */
    function atualizarEstatisticas() {
        const total = todosModelos.length;
        const xgboost = todosModelos.filter(m => getModeloTipo(m) === 'xgboost').length;
        const good = todosModelos.filter(m => getR2(m) >= 0.70).length;
        const bad = todosModelos.filter(m => getR2(m) < 0.70 && getR2(m) !== null).length;

        document.getElementById('statTotal').textContent = total;
        document.getElementById('statXgboost').textContent = xgboost;
        document.getElementById('statGood').textContent = good;
        document.getElementById('statBad').textContent = bad;
    }

    // ============================================
    // Filtrar e renderizar modelos
    // ============================================

    /**
     * Filtra os modelos baseado nos inputs de busca e selects.
     * Renderiza os cards filtrados.
     */
    function filtrarModelos() {
        const busca = (document.getElementById('searchInput').value || '').toLowerCase();
        const qualidade = document.getElementById('filterQuality').value;
        const tipo = document.getElementById('filterTipo').value;

        let filtrados = todosModelos.filter(m => {
            // Filtro de busca (ponto, tag, código)
            if (busca) {
                const cdPonto = String(m.cd_ponto || '');
                const tag = (m.metricas?.tag_principal || '').toLowerCase();
                const tags = (m.metricas?.tags_auxiliares || []).join(' ').toLowerCase();
                if (!cdPonto.includes(busca) && !tag.includes(busca) && !tags.includes(busca)) {
                    return false;
                }
            }

            // Filtro de qualidade
            if (qualidade) {
                const r2 = getR2(m);
                if (qualidade === 'excellent' && r2 < 0.90) return false;
                if (qualidade === 'good' && (r2 < 0.70 || r2 >= 0.90)) return false;
                if (qualidade === 'fair' && (r2 < 0.50 || r2 >= 0.70)) return false;
                if (qualidade === 'poor' && r2 >= 0.50) return false;
            }

            // Filtro de tipo
            if (tipo && getModeloTipo(m) !== tipo) {
                return false;
            }

            return true;
        });

        // Ordenar por R² decrescente
        filtrados.sort((a, b) => (getR2(b) || 0) - (getR2(a) || 0));

        renderizarModelos(filtrados);
    }

    /**
     * Renderiza os cards de modelos no container.
     * @param {Array} modelos - Lista filtrada de modelos
     */
    function renderizarModelos(modelos) {
        const container = document.getElementById('modelsContainer');

        if (modelos.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <ion-icon name="cube-outline"></ion-icon>
                    </div>
                    <h3>Nenhum modelo encontrado</h3>
                    <p>Nenhum modelo corresponde aos filtros aplicados</p>
                </div>
            `;
            return;
        }

        let html = '<div class="models-grid">';

        modelos.forEach(m => {
            const cdPonto = m.cd_ponto || '?';
            const metricas = m.metricas || {};
            const tipoModelo = getModeloTipo(m);
            const r2 = getR2(m);
            const mae = metricas.mae != null ? parseFloat(metricas.mae).toFixed(4) : '-';
            const rmse = metricas.rmse != null ? parseFloat(metricas.rmse).toFixed(4) : '-';
            const r2Display = r2 != null ? parseFloat(r2).toFixed(4) : '-';
            const mape = metricas.mape_pct != null ? parseFloat(metricas.mape_pct).toFixed(1) + '%' : '-';
            const correlacao = metricas.correlacao != null ? parseFloat(metricas.correlacao).toFixed(4) : '-';
            const tagPrincipal = metricas.tag_principal || '-';
            const nArvores = metricas.n_arvores || '-';
            const nFeatures = metricas.n_features || '-';
            const treinadoEm = metricas.treinado_em ? formatarData(metricas.treinado_em) : '-';
            const versao = metricas.versao_treino || '-';

            // Qualidade baseada no R²
            const qualidade = getQualidade(r2);
            const r2Pct = r2 != null ? Math.round(r2 * 100) : 0;

            html += `
                <div class="model-card" data-ponto="${cdPonto}">
                    <div class="model-card-header">
                        <div class="model-card-title">
                            <span class="ponto-id">#${cdPonto}</span>
                            <span class="ponto-nome" title="${escapeHtml(tagPrincipal)}">${escapeHtml(tagPrincipal)}</span>
                        </div>
                        <span class="model-card-tipo ${tipoModelo}">${tipoModelo.toUpperCase()} v${versao}</span>
                    </div>

                    <div class="model-card-body">
                        <!-- Métricas principais -->
                        <div class="metrics-grid">
                            <div class="metric-item">
                                <div class="metric-value">${r2Display}</div>
                                <div class="metric-label">R²</div>
                            </div>
                            <div class="metric-item">
                                <div class="metric-value">${mae}</div>
                                <div class="metric-label">MAE</div>
                            </div>
                            <div class="metric-item">
                                <div class="metric-value">${mape}</div>
                                <div class="metric-label">MAPE</div>
                            </div>
                        </div>

                        <!-- Barra de qualidade -->
                        <div class="model-quality">
                            <div class="quality-bar">
                                <div class="quality-bar-fill ${qualidade.classe}" style="width: ${r2Pct}%"></div>
                            </div>
                            <span class="quality-label ${qualidade.classe}">${qualidade.texto}</span>
                        </div>
                        <div class="model-info-row">
                            <span class="info-label">
                                <ion-icon name="calendar-outline"></ion-icon>
                                Histórico
                            </span>
                            <span class="info-value">${metricas.semanas_historico ? metricas.semanas_historico + ' semanas' : '—'}</span>
                        </div>
                        <!-- Informações extras -->
                        <div class="model-info-row">
                            <span class="info-label">
                                <ion-icon name="git-branch-outline"></ion-icon>
                                Árvores
                            </span>
                            <span class="info-value">${nArvores}</span>
                        </div>
                        <div class="model-info-row">
                            <span class="info-label">
                                <ion-icon name="layers-outline"></ion-icon>
                                Features
                            </span>
                            <span class="info-value">${nFeatures}</span>
                        </div>
                        <div class="model-info-row">
                            <span class="info-label">
                                <ion-icon name="time-outline"></ion-icon>
                                Treinado em
                            </span>
                            <span class="info-value">${treinadoEm}</span>
                        </div>
                    </div>

                    <div class="model-card-footer">
                        <button type="button" class="btn-model-action btn-details"
                            onclick="abrirDetalhes(${cdPonto})" title="Ver detalhes">
                            <ion-icon name="eye-outline"></ion-icon>
                            Detalhes
                        </button>
                        <button type="button" class="btn-model-action btn-diag"
                            onclick="abrirDiagnostico(${cdPonto})" title="Diagnosticar pré-requisitos">
                            <ion-icon name="medkit-outline"></ion-icon>
                            Diagnóstico
                        </button>
                        ${podeEditar ? `
                        <button type="button" class="btn-model-action btn-retrain"
                            onclick="retreinar(${cdPonto}, ${metricas.tipo_medidor || 1})" title="Retreinar modelo">
                            <ion-icon name="refresh-outline"></ion-icon>
                            Retreinar
                        </button>
                        <button type="button" class="btn-model-action btn-delete"
                            onclick="excluirModelo(${cdPonto})" title="Excluir modelo">
                            <ion-icon name="trash-outline"></ion-icon>
                            Excluir
                        </button>
                        ` : ''}
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;
    }

    // ============================================
    // Funções auxiliares
    // ============================================

    /**
     * Retorna o tipo do modelo (xgboost ou lstm).
     */
    function getModeloTipo(m) {
        return m.metricas?.modelo_tipo || (m.metricas?.versao_treino ? 'xgboost' : 'lstm');
    }

    /**
     * Retorna o R² do modelo (ou null).
     */
    function getR2(m) {
        const r2 = m.metricas?.r2;
        return r2 != null ? parseFloat(r2) : null;
    }

    /**
     * Retorna a classificação de qualidade baseada no R².
     * @param {number|null} r2 - Valor do R²
     * @returns {{classe: string, texto: string}}
     */
    function getQualidade(r2) {
        if (r2 === null || r2 === undefined) return { classe: 'poor', texto: 'Sem dados' };
        if (r2 >= 0.90) return { classe: 'excellent', texto: 'Excelente' };
        if (r2 >= 0.70) return { classe: 'good', texto: 'Bom' };
        if (r2 >= 0.50) return { classe: 'fair', texto: 'Regular' };
        return { classe: 'poor', texto: 'Baixo' };
    }

    /**
     * Formata data ISO para exibição (dd/mm/yyyy HH:mm).
     */
    function formatarData(isoString) {
        if (!isoString) return '-';
        try {
            const d = new Date(isoString);
            return d.toLocaleDateString('pt-BR') + ' ' +
                d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        } catch (e) {
            return isoString;
        }
    }

    /**
     * Escapa HTML para prevenir XSS.
     */
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ============================================
    // Modal de Detalhes
    // ============================================

    /**
     * Abre o modal com detalhes completos do modelo.
     * @param {number} cdPonto - Código do ponto
     */
    function abrirDetalhes(cdPonto) {
        const modelo = todosModelos.find(m => m.cd_ponto == cdPonto);
        if (!modelo) return;

        const metricas = modelo.metricas || {};
        const fi = metricas.feature_importance || {};
        const tagsAux = metricas.tags_auxiliares || [];

        // Título
        document.getElementById('modalDetalhesTitle').textContent =
            `Ponto #${cdPonto} - ${metricas.tag_principal || 'N/A'}`;

        // Montar corpo do modal
        let html = '';

        // Seção: Métricas de Performance
        html += `
            <div class="modal-section">
                <div class="modal-section-title">
                    <ion-icon name="speedometer-outline"></ion-icon>
                    Métricas de Performance
                </div>
                <div class="modal-metrics-grid">
                    <div class="modal-metric">
                        <div class="value">${fmt(metricas.r2)}</div>
                        <div class="label">R² (Determinação)</div>
                        <div class="hint">Ideal: &ge; 0.90</div>
                    </div>
                    <div class="modal-metric">
                        <div class="value">${fmt(metricas.mae)}</div>
                        <div class="label">MAE</div>
                        <div class="hint">Erro Absoluto Médio</div>
                    </div>
                    <div class="modal-metric">
                        <div class="value">${fmt(metricas.rmse)}</div>
                        <div class="label">RMSE</div>
                        <div class="hint">Raiz do Erro Quadrático</div>
                    </div>
                    <div class="modal-metric">
                        <div class="value">${metricas.mape_pct != null ? parseFloat(metricas.mape_pct).toFixed(1) + '%' : '-'}</div>
                        <div class="label">MAPE</div>
                        <div class="hint">Erro Percentual Médio</div>
                    </div>
                    <div class="modal-metric">
                        <div class="value">${fmt(metricas.correlacao)}</div>
                        <div class="label">Correlação</div>
                        <div class="hint">Ideal: &ge; 0.95</div>
                    </div>
                    <div class="modal-metric">
                        <div class="value">${metricas.n_arvores || '-'}</div>
                        <div class="label">Árvores</div>
                        <div class="hint">Early stopping</div>
                    </div>
                </div>
            </div>
        `;

        // Seção: Informações Gerais
        html += `
            <div class="modal-section">
                <div class="modal-section-title">
                    <ion-icon name="information-circle-outline"></ion-icon>
                    Informações do Modelo
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Tipo do Modelo</span>
                        <span class="value">${(metricas.modelo_tipo || 'N/A').toUpperCase()} v${metricas.versao_treino || '?'}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Tipo de Target</span>
                        <span class="value">${metricas.target_tipo || 'N/A'}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Amostras Treino</span>
                        <span class="value">${metricas.amostras_treino ? metricas.amostras_treino.toLocaleString('pt-BR') : '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Amostras Validação</span>
                        <span class="value">${metricas.amostras_validacao ? metricas.amostras_validacao.toLocaleString('pt-BR') : '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Learning Rate</span>
                        <span class="value">${metricas.learning_rate || '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Max Depth</span>
                        <span class="value">${metricas.max_depth || '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Features</span>
                        <span class="value">${metricas.n_features || '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Treinado em</span>
                        <span class="value">${formatarData(metricas.treinado_em)}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Lags utilizados</span>
                        <span class="value">${metricas.lags ? metricas.lags.join(', ') + 'h' : '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Banco de Treino</span>
                        <span class="value" style="font-size:11px;">${metricas.banco_treino || '-'}</span>
                    </div>
                </div>
            </div>
        `;

        // Seção: Tags Auxiliares
        if (tagsAux.length > 0) {
            html += `
                <div class="modal-section">
                    <div class="modal-section-title">
                        <ion-icon name="pricetags-outline"></ion-icon>
                        Tags Auxiliares (${tagsAux.length})
                    </div>
                    <div class="tags-list">
                        ${tagsAux.map(t => `<span class="tag-badge">${escapeHtml(t)}</span>`).join('')}
                    </div>
                </div>
            `;
        }

        // Seção: Feature Importance (top 10)
        const fiEntries = Object.entries(fi);
        if (fiEntries.length > 0) {
            const top10 = fiEntries.slice(0, 10);
            const maxImportance = top10.length > 0 ? top10[0][1] : 1;

            html += `
                <div class="modal-section">
                    <div class="modal-section-title">
                        <ion-icon name="bar-chart-outline"></ion-icon>
                        Feature Importance (Top 10)
                    </div>
                    <ul class="feature-list">
            `;

            top10.forEach((entry, idx) => {
                const [name, importance] = entry;
                const pct = maxImportance > 0 ? (importance / maxImportance * 100) : 0;
                html += `
                    <li class="feature-item">
                        <span class="feature-rank ${idx < 3 ? 'top' : ''}">${idx + 1}</span>
                        <span class="feature-name">${escapeHtml(name)}</span>
                        <div class="feature-bar-wrapper">
                            <div class="feature-bar">
                                <div class="feature-bar-fill" style="width: ${pct}%"></div>
                            </div>
                        </div>
                        <span class="feature-value">${parseFloat(importance).toFixed(4)}</span>
                    </li>
                `;
            });

            html += '</ul></div>';
        }

        document.getElementById('modalDetalhesBody').innerHTML = html;
        document.getElementById('modalDetalhes').classList.add('active');
    }

    /**
     * Formata valor numérico para exibição.
     */
    function fmt(val) {
        if (val == null || val === '') return '-';
        return parseFloat(val).toFixed(4);
    }

    /**
     * Fecha o modal de detalhes.
     */
    function fecharModalDetalhes(event) {
        if (event && event.target !== event.currentTarget) return;
        document.getElementById('modalDetalhes').classList.remove('active');
    }

    // ============================================
    // Modal de Treino
    // ============================================

    /**
     * Abre o modal para treinar um novo modelo.
     */
    function abrirModalTreino() {
        if (!servicoOnline) {
            showToast('Serviço TensorFlow está offline. Verifique o container.', 'erro');
            return;
        }
        // Resetar formulário
        $('#selectPontoTreino').val('').trigger('change');
        document.getElementById('selectSemanas').value = '24';
        document.getElementById('chkForce').checked = false;
        document.getElementById('btnIniciarTreino').disabled = false;

        document.getElementById('modalTreino').classList.add('active');

        // Focar no Select2
        setTimeout(() => $('#selectPontoTreino').select2('open'), 300);
    }

    /**
     * Fecha o modal de treino.
     */
    function fecharModalTreino(event) {
        if (event && event.target !== event.currentTarget) return;
        document.getElementById('modalTreino').classList.remove('active');
    }

    // ============================================
    // Treinar / Retreinar modelo
    // ============================================

    /**
     * Alterna visibilidade dos campos conforme o modo de treino.
     * Modos: 'unico', 'todos' (período fixo) e 'otimizado' (melhor R² anterior).
     */
    function toggleModoPonto() {
        const modo = document.getElementById('selectModoTreino').value;
        const grupoPonto = document.getElementById('grupoPonto');
        const grupoSemanas = document.getElementById('selectSemanas').closest('.train-form-group');
        const hint = document.getElementById('hintModo');
        const chkForce = document.getElementById('chkForce');

        if (modo === 'unico') {
            grupoPonto.style.display = '';
            if (grupoSemanas) grupoSemanas.style.display = '';
            hint.textContent = 'Treinar modelo para um único ponto de medição';
            chkForce.parentElement.style.display = '';
        } else if (modo === 'todos') {
            grupoPonto.style.display = 'none';
            if (grupoSemanas) grupoSemanas.style.display = '';
            hint.textContent = 'Treinar todos os pontos com o mesmo período. Pode levar vários minutos.';
            chkForce.checked = true;
            chkForce.parentElement.style.display = 'none';
        } else if (modo === 'otimizado') {
            grupoPonto.style.display = 'none';
            if (grupoSemanas) grupoSemanas.style.display = 'none';
            hint.innerHTML = '<ion-icon name="sparkles-outline" style="font-size:13px;vertical-align:middle;color:#f59e0b;"></ion-icon> ' +
                'Cada ponto será treinado com o período que obteve melhor R² no treino anterior. ' +
                'Pontos sem modelo usarão 24 semanas como padrão.';
            chkForce.checked = true;
            chkForce.parentElement.style.display = 'none';
        }
    }

    /**
     * Inicia o treinamento (ponto único, todos fixo, ou otimizado).
     */
    function iniciarTreino() {
        const modo = document.getElementById('selectModoTreino').value;
        const semanas = parseInt(document.getElementById('selectSemanas').value);

        if (modo === 'todos') {
            if (!confirm('Isso irá treinar/retreinar TODOS os pontos com ' + semanas + ' semanas.\n\nO processo pode levar vários minutos. Deseja continuar?')) {
                return;
            }
            fecharModalTreino();
            executarTreinoTodos(semanas, 'fixo');
        } else if (modo === 'otimizado') {
            if (!confirm('Isso irá treinar/retreinar TODOS os pontos usando o período otimizado por melhor R² de cada um.\n\nO processo pode levar vários minutos. Deseja continuar?')) {
                return;
            }
            fecharModalTreino();
            executarTreinoTodos(24, 'otimizado'); // 24 como fallback
        } else {
            const cdPonto = $('#selectPontoTreino').val();
            if (!cdPonto) {
                showToast('Selecione um ponto de medição', 'aviso');
                return;
            }
            const force = document.getElementById('chkForce').checked;
            const tipoMedidor = parseInt($('#selectPontoTreino option:selected').data('tipo') || 1);

            fecharModalTreino();
            executarTreino(parseInt(cdPonto), tipoMedidor, semanas, force);
        }
    }

    /**
     * Timer do polling de progresso do treino em background.
     */
    let _trainAllPolling = null;
    /** Timestamp de início para calcular tempo decorrido */
    let _trainAllInicio = null;

    /**
     * Dispara treino de todos os pontos em background.
     * @param {number} semanas - Semanas de histórico (usado como fallback no modo otimizado)
     * @param {string} modo    - 'fixo' (período único) ou 'otimizado' (melhor R² por ponto)
     */
    function executarTreinoTodos(semanas, modo) {
        // Mostrar painel de progresso (não bloqueante)
        mostrarPainelProgresso('running', 'Iniciando treino de todos os pontos...', 0, 0, 0);
        _trainAllInicio = Date.now();

        // Disparar treino (retorna imediato com job_id)
        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'train_all',
                semanas: semanas,
                modo: modo || 'fixo'
            })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Iniciar polling de progresso a cada 5 segundos
                    iniciarPollingTreino();
                } else {
                    mostrarPainelProgresso('error', data.error || 'Erro ao iniciar treino', 0, 0, 0);
                }
            })
            .catch(err => {
                mostrarPainelProgresso('error', 'Erro de conexão: ' + err.message, 0, 0, 0);
            });
    }

    /**
     * Mostra/atualiza o painel de progresso não-bloqueante.
     * @param {string} status  - 'running', 'completed', 'error'
     * @param {string} msg     - Mensagem de detalhe
     * @param {number} sucesso - Qtd de pontos treinados com sucesso
     * @param {number} falha   - Qtd de pontos com falha
     * @param {number} total   - Total esperado de pontos
     */
    function mostrarPainelProgresso(status, msg, sucesso, falha, total) {
        const painel = document.getElementById('trainProgressPanel');
        const icon = document.getElementById('trainProgressIcon');
        const titulo = document.getElementById('trainProgressTitulo');
        const bar = document.getElementById('trainProgressBar');
        const btnFechar = document.getElementById('btnFecharProgresso');
        const msgEl = document.getElementById('trainProgressMsg');
        const tempoEl = document.getElementById('trainTempo');

        // Exibir painel
        painel.style.display = '';

        // Classes de estado
        painel.className = 'train-progress-panel ' + status;

        // Ícone e título
        if (status === 'running') {
            icon.setAttribute('name', 'sync-outline');
            icon.classList.add('spin-icon');
            titulo.textContent = 'Treino em andamento...';
            btnFechar.style.display = 'none';
        } else if (status === 'completed') {
            icon.setAttribute('name', 'checkmark-circle-outline');
            icon.classList.remove('spin-icon');
            titulo.textContent = 'Treino finalizado!';
            btnFechar.style.display = 'flex';
        } else {
            icon.setAttribute('name', 'alert-circle-outline');
            icon.classList.remove('spin-icon');
            titulo.textContent = 'Treino encerrado com erros';
            btnFechar.style.display = 'flex';
        }

        // Contadores
        document.getElementById('trainSucesso').textContent = sucesso;
        document.getElementById('trainFalha').textContent = falha;
        document.getElementById('trainTotal').textContent = total || (sucesso + falha);

        // Barra de progresso
        const processados = sucesso + falha;
        const pct = total > 0 ? Math.min(100, Math.round((processados / total) * 100)) : 0;
        bar.style.width = (status === 'completed' ? 100 : pct) + '%';

        // Mensagem de detalhe
        if (msgEl) msgEl.textContent = msg || '';

        // Tempo decorrido
        if (_trainAllInicio && tempoEl) {
            const seg = Math.round((Date.now() - _trainAllInicio) / 1000);
            const min = Math.floor(seg / 60);
            const s = seg % 60;
            tempoEl.innerHTML = '<ion-icon name="time-outline"></ion-icon> ' +
                (min > 0 ? min + 'min ' : '') + s + 's';
        }

        // Scroll suave para o painel
        if (status === 'running' && processados === 0) {
            painel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    /**
     * Fecha o painel de progresso (só quando finalizado).
     */
    function fecharPainelProgresso() {
        document.getElementById('trainProgressPanel').style.display = 'none';
    }

    /**
     * Polling: consulta progresso do treino a cada 5s.
     * Atualiza o painel de progresso com informações em tempo real.
     * Para automaticamente quando o treino finaliza.
     */
    function iniciarPollingTreino() {
        // Limpar polling anterior se houver
        if (_trainAllPolling) clearInterval(_trainAllPolling);

        _trainAllPolling = setInterval(function () {
            fetch('bd/operacoes/predicaoTensorFlow.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ acao: 'train_all_status' })
            })
                .then(r => r.json())
                .then(prog => {
                    if (!prog.success && !prog.status) return;

                    const status = prog.status;
                    const sucesso = prog.sucesso || 0;
                    const falha = prog.falha || 0;
                    const total = prog.total || (sucesso + falha);
                    const msgDetalhe = prog.ponto_atual || prog.message || '';

                    if (status === 'running') {
                        mostrarPainelProgresso('running', msgDetalhe, sucesso, falha, total);

                    } else if (status === 'completed' || status === 'error') {
                        // Treino finalizado — parar polling
                        clearInterval(_trainAllPolling);
                        _trainAllPolling = null;

                        mostrarPainelProgresso(status, prog.resumo || prog.message || '', sucesso, falha, total);

                        if (status === 'completed') {
                            showToast(
                                `Treino finalizado! ✅ ${sucesso} sucesso, ❌ ${falha} falha`,
                                falha > 0 ? 'alerta' : 'sucesso'
                            );
                        } else {
                            showToast(prog.message || 'Treino encerrado com erros', 'erro');
                        }

                        // Recarregar lista de modelos
                        carregarModelos();

                    } else if (status === 'idle') {
                        clearInterval(_trainAllPolling);
                        _trainAllPolling = null;
                    }
                })
                .catch(() => {
                    // Erro de rede temporário — manter polling
                });
        }, 5000);
    }

    /**
     * Ao carregar a página, verificar se há treino em andamento
     * (caso o usuário tenha navegado e voltou).
     */
    (function verificarTreinoEmAndamento() {
        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'train_all_status' })
        })
            .then(r => r.json())
            .then(prog => {
                if (prog.status === 'running') {
                    // Treino em andamento — retomar polling
                    _trainAllInicio = prog.inicio ? new Date(prog.inicio).getTime() : Date.now();
                    mostrarPainelProgresso('running', prog.ponto_atual || prog.message || '', prog.sucesso || 0, prog.falha || 0, prog.total || 0);
                    iniciarPollingTreino();
                } else if (prog.status === 'completed' || prog.status === 'error') {
                    // Último treino finalizou — mostrar resultado
                    _trainAllInicio = prog.inicio ? new Date(prog.inicio).getTime() : Date.now();
                    mostrarPainelProgresso(prog.status, prog.resumo || prog.message || '', prog.sucesso || 0, prog.falha || 0, prog.total || 0);
                }
            })
            .catch(() => { /* silencioso */ });
    })();
    /**
     * Abre modal de retreino com seleção de período.
     * @param {number} cdPonto - Código do ponto
     * @param {number} tipoMedidor - Tipo do medidor
     */
    function retreinar(cdPonto, tipoMedidor) {
        if (!servicoOnline) {
            showToast('Serviço TensorFlow está offline', 'erro');
            return;
        }

        // Preencher modal de retreino
        document.getElementById('retreinoPonto').textContent = '#' + cdPonto;
        document.getElementById('retreinoCdPonto').value = cdPonto;
        document.getElementById('retreinoTipoMedidor').value = tipoMedidor;
        document.getElementById('selectSemanasRetreino').value = '24';
        document.getElementById('modalRetreino').classList.add('active');
    }

    /**
     * Fecha o modal de retreino.
     */
    function fecharModalRetreino(event) {
        if (event && event.target !== event.currentTarget) return;
        document.getElementById('modalRetreino').classList.remove('active');
    }

    /**
     * Confirma e executa o retreino a partir do modal.
     */
    function confirmarRetreino() {
        const cdPonto = parseInt(document.getElementById('retreinoCdPonto').value);
        const tipoMedidor = parseInt(document.getElementById('retreinoTipoMedidor').value);
        const semanas = parseInt(document.getElementById('selectSemanasRetreino').value);

        fecharModalRetreino();
        executarTreino(cdPonto, tipoMedidor, semanas, true);
    }

    /**
     * Executa o treinamento chamando o endpoint predicaoTensorFlow.php.
     * @param {number} cdPonto - Código do ponto
     * @param {number} tipoMedidor - Tipo do medidor
     * @param {number} semanas - Semanas de histórico
     * @param {boolean} force - Forçar retreino
     */
    function executarTreino(cdPonto, tipoMedidor, semanas, force) {
        // Mostrar loading
        document.getElementById('loadingText').textContent = `Treinando modelo para ponto #${cdPonto}...`;
        document.getElementById('loadingSub').textContent =
            `${semanas} semanas de histórico | Isso pode levar alguns minutos`;
        document.getElementById('loadingOverlay').classList.add('active');

        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'train',
                cd_ponto: cdPonto,
                semanas: semanas,
                tipo_medidor: tipoMedidor,
                force: force
            })
        })
            .then(r => r.json())
            .then(data => {
                document.getElementById('loadingOverlay').classList.remove('active');

                if (data.success) {
                    showToast(data.message || `Modelo treinado com sucesso para ponto #${cdPonto}`, 'sucesso');

                    // Recarregar lista de modelos
                    carregarModelos();
                } else {
                    showToast(data.error || 'Erro ao treinar modelo', 'erro');
                }
            })
            .catch(err => {
                document.getElementById('loadingOverlay').classList.remove('active');
                showToast('Erro de conexão: ' + err.message, 'erro');
            });
    }

    // ============================================
    // Associações TAG Principal → Auxiliares
    // ============================================

    /** Dados de associações: { TAG: [auxiliares] } */
    let associacoes = {};
    /** TAG principal selecionada */
    let tagPrincipalSelecionada = null;
    /** Tags disponíveis: [{ TAG, NM_PONTO_MEDICAO, CD_PONTO_MEDICAO, ID_TIPO_MEDIDOR }] */
    let tagsDisponiveis = [];
    /** Mapa rápido TAG → info do ponto */
    let tagInfoMap = {};
    /** Select2 já inicializados */
    let select2Assoc = false;

    /**
     * Abre modal e carrega dados.
     */
    function abrirModalAssociacoes() {
        document.getElementById('modalAssociacoes').classList.add('active');
        carregarAssociacoes();
        carregarTagsDisponiveis();
    }

    /**
     * Fecha modal e destrói Select2 (evitar conflitos de z-index).
     */
    function fecharModalAssociacoes(event) {
        if (event && event.target !== event.currentTarget) return;
        document.getElementById('modalAssociacoes').classList.remove('active');
        // Destruir Select2 ao fechar para evitar overlays órfãos
        if (select2Assoc) {
            try {
                $('#selectNovaTagPrincipal').select2('destroy');
                $('#selectNovaAuxiliar').select2('destroy');
            } catch (e) { }
            select2Assoc = false;
        }
    }

    /**
     * Inicializa Select2 com matcher que busca por TAG, CD_PONTO e nome.
     */
    function initSelect2Assoc() {
        if (select2Assoc) return;

        const parentModal = $('#modalAssociacoes .modal-container');

        // Matcher customizado: pesquisa em TAG, CD_PONTO e nome do ponto
        function matcherAssoc(params, data) {
            // Se não há termo de busca, retorna tudo
            if (!params.term || params.term.trim() === '') return data;
            if (!data.element) return null;

            const termo = params.term.toLowerCase();
            const tag = (data.element.dataset.tag || '').toLowerCase();
            const cd = (data.element.dataset.cd || '').toLowerCase();
            const nome = (data.element.dataset.nome || '').toLowerCase();
            const texto = (data.text || '').toLowerCase();

            // Pesquisa em qualquer campo
            if (tag.includes(termo) || cd.includes(termo) || nome.includes(termo) || texto.includes(termo)) {
                return data;
            }

            return null;
        }

        $('#selectNovaTagPrincipal').select2({
            placeholder: 'Buscar por código, TAG ou nome...',
            allowClear: true,
            width: '100%',
            dropdownParent: parentModal,
            matcher: matcherAssoc,
            language: { noResults: () => 'Nenhuma TAG encontrada' }
        });

        $('#selectNovaAuxiliar').select2({
            placeholder: 'Buscar por código, TAG ou nome...',
            allowClear: true,
            width: '100%',
            dropdownParent: parentModal,
            matcher: matcherAssoc,
            language: { noResults: () => 'Nenhuma TAG encontrada' }
        });

        // Autofocus ao abrir os dropdowns
        $('#selectNovaTagPrincipal').on('select2:open', function () {
            setTimeout(() => document.querySelector('.select2-container--open .select2-search__field')?.focus(), 0);
        });

        $('#selectNovaAuxiliar').on('select2:open', function () {
            setTimeout(() => document.querySelector('.select2-container--open .select2-search__field')?.focus(), 0);
        });

        select2Assoc = true;
    }

    /**
     * Carrega associações existentes.
     */
    function carregarAssociacoes() {
        document.getElementById('assocListaPrincipais').innerHTML =
            '<div class="assoc-lista-empty"><ion-icon name="sync-outline" style="animation:spin 1s linear infinite;font-size:18px;"></ion-icon><br>Carregando...</div>';

        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'list_relations' })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.relacoes) {
                    associacoes = data.relacoes;
                    renderizarListaPrincipais();
                } else {
                    document.getElementById('assocListaPrincipais').innerHTML =
                        `<div class="assoc-lista-empty">${data.error || 'Erro ao carregar'}</div>`;
                }
            })
            .catch(err => {
                document.getElementById('assocListaPrincipais').innerHTML =
                    `<div class="assoc-lista-empty">Erro: ${err.message}</div>`;
            });
    }

    /**
     * Carrega TAGs disponíveis e popula os Select2.
     */
    function carregarTagsDisponiveis() {
        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'list_tags' })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.tags) {
                    tagsDisponiveis = data.tags;

                    // Montar mapa TAG → info
                    tagInfoMap = {};
                    tagsDisponiveis.forEach(t => {
                        tagInfoMap[t.TAG] = t;
                    });

                    preencherSelectTags('selectNovaTagPrincipal', tagsDisponiveis);
                    preencherSelectTags('selectNovaAuxiliar', tagsDisponiveis);

                    // Inicializar Select2 após popular
                    initSelect2Assoc();
                }
            })
            .catch(() => { });
    }

    /**
     * Preenche um <select> com TAGs.
     * O value é sempre a TAG (string), o texto mostra código + TAG + nome.
     */
    function preencherSelectTags(selectId, tags) {
        const select = document.getElementById(selectId);
        select.innerHTML = '<option value="">Selecione...</option>';
        tags.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.TAG;                          // Valor salvo = TAG
            opt.dataset.cd = t.CD_PONTO_MEDICAO;
            opt.dataset.tag = t.TAG;
            opt.dataset.nome = t.NM_PONTO_MEDICAO || '';
            opt.textContent = `#${t.CD_PONTO_MEDICAO} - ${t.TAG} (${t.NM_PONTO_MEDICAO || 'Sem nome'})`;
            select.appendChild(opt);
        });
    }

    /**
     * Retorna info complementar da TAG (CD_PONTO e nome).
     */
    function getTagInfo(tag) {
        const info = tagInfoMap[tag];
        if (info) return `#${info.CD_PONTO_MEDICAO} - ${info.NM_PONTO_MEDICAO || ''}`;
        return '';
    }

    /**
     * Renderiza lista lateral de TAGs principais.
     */
    function renderizarListaPrincipais() {
        const container = document.getElementById('assocListaPrincipais');
        const busca = (document.getElementById('assocSearchPrincipal').value || '').toLowerCase();

        const tags = Object.keys(associacoes).sort();
        const filtradas = busca
            ? tags.filter(t => {
                const info = getTagInfo(t).toLowerCase();
                return t.toLowerCase().includes(busca) || info.includes(busca);
            })
            : tags;

        document.getElementById('assocTotalPrincipais').textContent = `(${tags.length})`;

        if (filtradas.length === 0) {
            container.innerHTML = '<div class="assoc-lista-empty">Nenhuma associação encontrada</div>';
            return;
        }

        container.innerHTML = filtradas.map(tag => {
            const count = associacoes[tag] ? associacoes[tag].length : 0;
            const isActive = tag === tagPrincipalSelecionada;
            const info = getTagInfo(tag);
            return `
                <div class="assoc-lista-item ${isActive ? 'active' : ''}" onclick="selecionarPrincipal('${escapeHtml(tag)}')">
                    <div class="tag-info">
                        <span class="tag-name" title="${escapeHtml(tag)}">${escapeHtml(tag)}</span>
                        ${info ? `<span class="tag-ponto">${escapeHtml(info)}</span>` : ''}
                    </div>
                    <span class="tag-count">${count}</span>
                </div>
            `;
        }).join('');
    }

    /**
     * Filtra lista de principais.
     */
    function filtrarPrincipais() {
        renderizarListaPrincipais();
    }

    /**
     * Seleciona principal e mostra auxiliares.
     */
    function selecionarPrincipal(tag) {
        tagPrincipalSelecionada = tag;
        renderizarListaPrincipais();

        document.getElementById('assocDetailHeader').style.display = '';
        document.getElementById('assocAddRow').style.display = '';
        document.getElementById('assocTagSelecionada').textContent = tag;

        renderizarAuxiliares();
    }

    /**
     * Renderiza lista de auxiliares.
     */
    function renderizarAuxiliares() {
        const container = document.getElementById('assocDetailBody');
        const auxs = associacoes[tagPrincipalSelecionada] || [];

        if (auxs.length === 0) {
            container.innerHTML = `
                <div class="assoc-detail-empty">
                    <ion-icon name="link-outline"></ion-icon>
                    Nenhuma TAG auxiliar associada
                </div>`;
            return;
        }

        container.innerHTML = auxs.sort().map(aux => {
            const info = getTagInfo(aux);
            return `
                <div class="assoc-aux-item">
                    <div class="assoc-aux-info">
                        <span class="aux-tag">${escapeHtml(aux)}</span>
                        ${info ? `<span class="aux-ponto">${escapeHtml(info)}</span>` : ''}
                    </div>
                    <button type="button" class="btn-remove-aux" onclick="removerAuxiliar('${escapeHtml(aux)}')" title="Remover">
                        <ion-icon name="close-outline"></ion-icon>
                    </button>
                </div>
            `;
        }).join('');
    }

    /**
     * Cria nova TAG principal.
     */
    function criarNovaPrincipal() {
        const tag = $('#selectNovaTagPrincipal').val();
        if (!tag) {
            showToast('Selecione uma TAG', 'aviso');
            return;
        }
        if (associacoes[tag]) {
            showToast('Esta TAG já existe como principal', 'aviso');
            selecionarPrincipal(tag);
            return;
        }
        associacoes[tag] = [];
        renderizarListaPrincipais();
        selecionarPrincipal(tag);
        $('#selectNovaTagPrincipal').val('').trigger('change');
        showToast(`TAG ${tag} adicionada. Agora adicione as auxiliares.`, 'sucesso');
    }

    /**
     * Adiciona auxiliar à principal selecionada.
     */
    function adicionarAuxiliar() {
        const tagAux = $('#selectNovaAuxiliar').val();

        if (!tagAux) {
            showToast('Selecione uma TAG auxiliar', 'aviso');
            return;
        }
        if (tagAux === tagPrincipalSelecionada) {
            showToast('A TAG auxiliar não pode ser igual à principal', 'aviso');
            return;
        }
        if ((associacoes[tagPrincipalSelecionada] || []).includes(tagAux)) {
            showToast('Esta TAG auxiliar já está associada', 'aviso');
            return;
        }

        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'add_relation',
                tag_principal: tagPrincipalSelecionada,
                tag_auxiliar: tagAux
            })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (!associacoes[tagPrincipalSelecionada]) associacoes[tagPrincipalSelecionada] = [];
                    associacoes[tagPrincipalSelecionada].push(tagAux);
                    renderizarAuxiliares();
                    renderizarListaPrincipais();
                    $('#selectNovaAuxiliar').val('').trigger('change');
                    showToast(`TAG ${tagAux} associada com sucesso`, 'sucesso');
                } else {
                    showToast(data.error || 'Erro ao adicionar', 'erro');
                }
            })
            .catch(err => showToast('Erro: ' + err.message, 'erro'));
    }

    /**
     * Remove uma auxiliar.
     */
    function removerAuxiliar(tagAux) {
        if (!confirm(`Remover a associação "${tagAux}" de "${tagPrincipalSelecionada}"?`)) return;

        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'delete_relation',
                tag_principal: tagPrincipalSelecionada,
                tag_auxiliar: tagAux
            })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    associacoes[tagPrincipalSelecionada] = (associacoes[tagPrincipalSelecionada] || [])
                        .filter(t => t !== tagAux);
                    renderizarAuxiliares();
                    renderizarListaPrincipais();
                    showToast('Associação removida', 'sucesso');
                } else {
                    showToast(data.error || 'Erro ao remover', 'erro');
                }
            })
            .catch(err => showToast('Erro: ' + err.message, 'erro'));
    }

    /**
     * Exclui todas as associações de uma principal.
     */
    function excluirPrincipal() {
        const tag = tagPrincipalSelecionada;
        if (!confirm(`Excluir TODAS as associações de "${tag}"?\n\nIsso removerá a TAG principal e todas suas auxiliares.`)) return;

        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'delete_all_relations',
                tag_principal: tag
            })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    delete associacoes[tag];
                    tagPrincipalSelecionada = null;
                    renderizarListaPrincipais();
                    document.getElementById('assocDetailHeader').style.display = 'none';
                    document.getElementById('assocAddRow').style.display = 'none';
                    document.getElementById('assocDetailBody').innerHTML = `
                    <div class="assoc-detail-empty">
                        <ion-icon name="arrow-back-outline"></ion-icon>
                        Selecione uma TAG principal
                    </div>`;
                    showToast(`Associações de ${tag} excluídas`, 'sucesso');
                } else {
                    showToast(data.error || 'Erro ao excluir', 'erro');
                }
            })
            .catch(err => showToast('Erro: ' + err.message, 'erro'));
    }

    /**
     * Exclui o modelo treinado de um ponto (remove pasta inteira).
     * @param {number} cdPonto - Código do ponto
     */
    function excluirModelo(cdPonto) {
        if (!confirm(`Excluir o modelo treinado do ponto #${cdPonto}?\n\nIsso removerá todos os arquivos do modelo (model.json, metricas.json).\nO ponto precisará ser retreinado.`)) return;

        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'delete_model', cd_ponto: cdPonto })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(`Modelo do ponto #${cdPonto} excluído`, 'sucesso');
                    carregarModelos();
                } else {
                    showToast(data.error || 'Erro ao excluir', 'erro');
                }
            })
            .catch(err => showToast('Erro: ' + err.message, 'erro'));
    }

    // ============================================
    // Sincronização Flowchart ↔ ML
    // ============================================

    /** Cache do último sync check */
    let ultimoSyncCheck = null;

    /**
     * Verifica divergências ao carregar a página.
     * Chamado automaticamente no carregamento.
     */
    function verificarSyncFlowchart() {
        fetch('bd/operacoes/flowchartSync.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'sync_check', cd_sistema: 0 })
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    // Silencioso — se falhar, não mostra banner
                    console.warn('Sync check falhou:', data.error);
                    return;
                }
                atualizarBannerSync(data);
            })
            .catch(err => {
                console.warn('Erro ao verificar sync:', err.message);
            });
    }

    /**
     * Atualiza o banner de sincronização com base no resultado do check.
     * @param {Object} data - Resultado do sync_check
     */
    function atualizarBannerSync(data) {
        const banner = document.getElementById('syncBanner');
        const titulo = document.getElementById('syncBannerTitle');
        const msg = document.getElementById('syncBannerMsg');
        const icon = document.getElementById('syncBannerIcon');
        const btnRevisar = document.getElementById('btnRevisarSync');

        if (data.tem_divergencia) {
            const r = data.resumo;
            banner.className = 'sync-banner divergente';
            icon.setAttribute('name', 'warning-outline');
            titulo.textContent = 'Flowchart desatualizado em relação às relações ML';
            let partes = [];
            if (r.total_novas > 0) partes.push(`${r.total_novas} nova(s)`);
            if (r.total_removidas > 0) partes.push(`${r.total_removidas} removida(s)`);
            msg.textContent = `Divergências encontradas: ${partes.join(', ')}. Revise a sincronização.`;
            btnRevisar.style.display = '';
        } else {
            banner.className = 'sync-banner sincronizado';
            icon.setAttribute('name', 'checkmark-circle-outline');
            titulo.textContent = 'Relações ML sincronizadas com o Flowchart';
            msg.textContent = `${data.resumo.total_inalteradas} relação(ões) ativas — tudo alinhado.`;
            btnRevisar.style.display = 'none';
        }
    }

    /**
     * Abre o modal de sincronização e executa o check.
     */
    function abrirModalSync() {
        document.getElementById('modalSync').classList.add('active');
        executarSyncCheck();
    }

    /** Fecha o modal de sincronização. */
    function fecharModalSync() {
        document.getElementById('modalSync').classList.remove('active');
    }

    /** Abre o modal de regras. */
    function abrirModalRegras() {
        document.getElementById('modalRegras').classList.add('active');
    }

    /** Fecha o modal de regras. */
    function fecharModalRegras() {
        document.getElementById('modalRegras').classList.remove('active');
    }

    /**
     * Executa o sync_check com o sistema selecionado e renderiza o diff no modal.
     */
    function executarSyncCheck() {
        const cdSistema = syncSistemaSelecionado || 0;
        const conteudo = document.getElementById('syncConteudo');
        const btnAplicar = document.getElementById('btnAplicarSync');
        btnAplicar.disabled = true;

        // Loading
        conteudo.innerHTML = `
            <div class="sync-loading">
                <ion-icon name="sync-outline"></ion-icon>
                <p>Analisando divergências...</p>
            </div>
        `;

        fetch('bd/operacoes/flowchartSync.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'sync_check', cd_sistema: cdSistema })
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    conteudo.innerHTML = `
                    <div class="sync-vazio">
                        <ion-icon name="alert-circle-outline"></ion-icon>
                        <p>${escapeHtml(data.error || 'Erro ao verificar')}</p>
                    </div>`;
                    return;
                }
                ultimoSyncCheck = data;
                renderizarDiffSync(data);
            })
            .catch(err => {
                conteudo.innerHTML = `
                <div class="sync-vazio">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    <p>Erro de conexão: ${escapeHtml(err.message)}</p>
                </div>`;
            });
    }

    /**
     * Renderiza o diff de sincronização no modal.
     * @param {Object} data - Resultado do sync_check
     */
    function renderizarDiffSync(data) {
        const conteudo = document.getElementById('syncConteudo');
        const r = data.resumo;

        let html = '';

        // --- Resumo em cards ---
        html += `
            <div class="sync-resumo">
                <div class="sync-resumo-item flowchart">
                    <div class="valor">${r.total_flowchart}</div>
                    <div class="rotulo">No Flowchart</div>
                </div>
                <div class="sync-resumo-item novas">
                    <div class="valor">${r.total_novas}</div>
                    <div class="rotulo">Novas</div>
                </div>
                <div class="sync-resumo-item removidas">
                    <div class="valor">${r.total_removidas}</div>
                    <div class="rotulo">Removidas</div>
                </div>
                <div class="sync-resumo-item inalteradas">
                    <div class="valor">${r.total_inalteradas}</div>
                    <div class="rotulo">Inalteradas</div>
                </div>
            </div>
        `;

        // --- Seção: Novas (a adicionar) ---
        if (data.novas.length > 0) {
            html += renderizarSecaoDiff('novas', 'Novas relações (adicionar)', data.novas, true);
        }

        // --- Seção: Removidas (a excluir) ---
        if (data.removidas.length > 0) {
            html += renderizarSecaoDiff('removidas', 'Relações removidas (excluir da tabela ML)', data.removidas, true);
        }

        // --- Seção: Inalteradas (informativo) ---
        if (data.inalteradas.length > 0) {
            html += `
                <div class="sync-section collapsed" id="secInalteradas">
                    <div class="sync-section-header inalteradas" onclick="toggleSecaoSync('secInalteradas')">
                        <ion-icon name="chevron-down-outline" class="toggle"></ion-icon>
                        <ion-icon name="checkmark-circle-outline"></ion-icon>
                        <h4>Relações inalteradas (já sincronizadas)</h4>
                        <span class="badge">${data.inalteradas.length}</span>
                    </div>
                    <div class="sync-section-body">
                        ${data.inalteradas.map(p => `
                            <div class="sync-row" style="opacity:0.6;">
                                <span class="tag-principal">
                                    <span class="codigo-ponto">${escapeHtml(p.codigo_principal || '—')}</span>
                                    <span class="tag-nome" title="${escapeHtml(p.tag_principal)}">${escapeHtml(p.tag_principal)}</span>
                                </span>
                                <span class="seta">→</span>
                                <span class="tag-auxiliar">
                                    <span class="codigo-ponto">${escapeHtml(p.codigo_auxiliar || '—')}</span>
                                    <span class="tag-nome" title="${escapeHtml(p.tag_auxiliar)}">${escapeHtml(p.tag_auxiliar)}</span>
                                </span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        // Sem divergências
        if (data.novas.length === 0 && data.removidas.length === 0) {
            html += `
                <div class="sync-vazio">
                    <ion-icon name="checkmark-circle-outline" style="color:#22c55e;"></ion-icon>
                    <p style="color:#166534;font-weight:600;">Tudo sincronizado!</p>
                    <p>As relações ML correspondem à topologia do Flowchart.</p>
                </div>
            `;
        }

        conteudo.innerHTML = html;
        atualizarBotaoAplicarSync();
    }

    /**
     * Renderiza uma seção do diff (novas ou removidas) com checkboxes.
     * @param {string} tipo    - 'novas' ou 'removidas'
     * @param {string} titulo  - Título da seção
     * @param {Array}  pares   - Array de { tag_principal, tag_auxiliar }
     * @param {boolean} checado - Se checkboxes vêm marcados por padrão
     * @returns {string} HTML
     */
    function renderizarSecaoDiff(tipo, titulo, pares, checado) {
        const icone = tipo === 'novas' ? 'add-circle-outline' : 'remove-circle-outline';
        let html = `
            <div class="sync-section" id="sec_${tipo}">
                <div class="sync-section-header ${tipo}" onclick="toggleSecaoSync('sec_${tipo}')">
                    <ion-icon name="chevron-down-outline" class="toggle"></ion-icon>
                    <ion-icon name="${icone}"></ion-icon>
                    <h4>${titulo}</h4>
                    <span class="badge">${pares.length}</span>
                </div>
                <div class="sync-section-body">
                    <div class="sync-select-all">
                        <input type="checkbox" id="chkAll_${tipo}" ${checado ? 'checked' : ''} 
                               onchange="toggleTodosSync('${tipo}', this.checked)">
                        <label for="chkAll_${tipo}">Selecionar todos</label>
                    </div>
        `;

        pares.forEach((p, idx) => {
            const codPrincipal = p.codigo_principal || '—';
            const codAuxiliar = p.codigo_auxiliar || '—';
            html += `
                <div class="sync-row">
                    <input type="checkbox" class="chk-sync chk-${tipo}" 
                           data-tipo="${tipo}"
                           data-principal="${escapeHtml(p.tag_principal)}" 
                           data-auxiliar="${escapeHtml(p.tag_auxiliar)}"
                           ${checado ? 'checked' : ''}
                           onchange="atualizarBotaoAplicarSync()">
                    <span class="tag-principal">
                        <span class="codigo-ponto">${escapeHtml(codPrincipal)}</span>
                        <span class="tag-nome" title="${escapeHtml(p.tag_principal)}">${escapeHtml(p.tag_principal)}</span>
                    </span>
                    <span class="seta">→</span>
                    <span class="tag-auxiliar">
                        <span class="codigo-ponto">${escapeHtml(codAuxiliar)}</span>
                        <span class="tag-nome" title="${escapeHtml(p.tag_auxiliar)}">${escapeHtml(p.tag_auxiliar)}</span>
                    </span>
                </div>
            `;
        });

        html += `</div></div>`;
        return html;
    }

    /**
     * Alterna expansão/colapso de uma seção do diff.
     * @param {string} idSecao - ID do elemento da seção
     */
    function toggleSecaoSync(idSecao) {
        document.getElementById(idSecao).classList.toggle('collapsed');
    }

    /**
     * Marca/desmarca todos os checkboxes de uma seção.
     * @param {string}  tipo    - 'novas' ou 'removidas'
     * @param {boolean} marcado - Estado desejado
     */
    function toggleTodosSync(tipo, marcado) {
        document.querySelectorAll(`.chk-${tipo}`).forEach(chk => {
            chk.checked = marcado;
        });
        atualizarBotaoAplicarSync();
    }

    /**
     * Atualiza estado do botão "Aplicar" baseado em checkboxes selecionados.
     */
    function atualizarBotaoAplicarSync() {
        const totalSelecionados = document.querySelectorAll('.chk-sync:checked').length;
        const btn = document.getElementById('btnAplicarSync');
        btn.disabled = totalSelecionados === 0;
        btn.innerHTML = totalSelecionados > 0
            ? `<ion-icon name="checkmark-outline"></ion-icon> Aplicar ${totalSelecionados} alteração(ões)`
            : `<ion-icon name="checkmark-outline"></ion-icon> Aplicar Selecionados`;
    }

    /**
     * Aplica as alterações selecionadas (novas + removidas).
     */
    function aplicarSync() {
        const adicionar = [];
        const remover = [];

        // Coletar checkboxes marcados
        document.querySelectorAll('.chk-sync:checked').forEach(chk => {
            const par = {
                tag_principal: chk.dataset.principal,
                tag_auxiliar: chk.dataset.auxiliar
            };
            if (chk.dataset.tipo === 'novas') {
                adicionar.push(par);
            } else if (chk.dataset.tipo === 'removidas') {
                remover.push(par);
            }
        });

        if (adicionar.length === 0 && remover.length === 0) {
            showToast('Nenhuma alteração selecionada', 'aviso');
            return;
        }

        // Confirmação
        const msgConfirm = [];
        if (adicionar.length > 0) msgConfirm.push(`Adicionar ${adicionar.length} relação(ões)`);
        if (remover.length > 0) msgConfirm.push(`Remover ${remover.length} relação(ões)`);
        if (!confirm(`Confirmar sincronização?\n\n${msgConfirm.join('\n')}\n\nEsta ação altera a tabela de relações ML.`)) {
            return;
        }

        // Desabilitar botão durante operação
        const btn = document.getElementById('btnAplicarSync');
        btn.disabled = true;
        btn.innerHTML = '<ion-icon name="sync-outline" style="animation:spin 1s linear infinite;"></ion-icon> Aplicando...';

        fetch('bd/operacoes/flowchartSync.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'sync_apply',
                adicionar: adicionar,
                remover: remover
            })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Sincronização aplicada com sucesso!', 'sucesso');
                    fecharModalSync();
                    // Re-verificar para atualizar banner
                    verificarSyncFlowchart();
                    // Recarregar associações se o modal de associações estiver ativo
                    if (typeof carregarAssociacoes === 'function') {
                        try { carregarAssociacoes(); } catch (e) { }
                    }
                } else {
                    showToast(data.error || 'Erro ao aplicar sincronização', 'erro');
                    btn.disabled = false;
                    btn.innerHTML = '<ion-icon name="checkmark-outline"></ion-icon> Aplicar Selecionados';
                }
            })
            .catch(err => {
                showToast('Erro de conexão: ' + err.message, 'erro');
                btn.disabled = false;
                btn.innerHTML = '<ion-icon name="checkmark-outline"></ion-icon> Aplicar Selecionados';
            });
    }

    // --- Verificar sincronização ao carregar a página ---
    // Adicionar esta chamada dentro do DOMContentLoaded ou no final do script:
    verificarSyncFlowchart();

    // ============================================
    // Governanca de Topologia (Fase A1)
    // ============================================

    /** Dados do ultimo status de governanca */
    var ultimoStatusGov = null;

    /**
     * Verifica status dos modelos em relacao a topologia.
     * Chamado automaticamente no carregamento da pagina.
     * Alimenta o banner de governanca (govBanner).
     */
    function verificarGovernancaTopologia() {
        fetch('bd/operacoes/governancaTopologia.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'verificar_status' })
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    console.warn('Governanca check falhou:', data.error);
                    // Mostrar banner informativo se a view nao existe ainda
                    if (data.message && data.message.includes('VW_MODELO_STATUS')) {
                        atualizarBannerGov('info', 'Governan\u00e7a n\u00e3o configurada', 'Execute o script SQL da Fase A1 para ativar o versionamento de topologia.');
                    }
                    return;
                }
                ultimoStatusGov = data;
                renderizarBannerGov(data);
            })
            .catch(err => {
                console.warn('Erro ao verificar governan\u00e7a:', err.message);
            });
    }

    /**
     * Renderiza o banner de governanca com base no status retornado.
     * @param {Object} data - Retorno de verificar_status
     */
    function renderizarBannerGov(data) {
        const c = data.contadores || {};
        const totalProblemas = (c.desatualizados || 0) + (c.sla_vencido || 0);

        if (data.tem_alerta) {
            // Montar mensagem detalhada
            let partes = [];
            if (c.sla_vencido > 0) partes.push(c.sla_vencido + ' com SLA vencido');
            if (c.desatualizados > 0) partes.push(c.desatualizados + ' desatualizado(s)');
            const msg = 'Modelos afetados: ' + partes.join(', ') + '. Topologia foi alterada desde o \u00faltimo treino.';

            atualizarBannerGov('alerta', totalProblemas + ' modelo(s) precisam de retreino', msg);
            document.getElementById('btnRetreinarGov').style.display = '';
        } else if (c.total > 0) {
            atualizarBannerGov('ok', 'Todos os modelos atualizados', c.atualizados + ' modelo(s) vinculado(s) \u00e0 topologia vigente.');
            document.getElementById('btnRetreinarGov').style.display = 'none';
        } else if (c.sem_versao > 0) {
            atualizarBannerGov('info', 'Modelos sem v\u00eds\u00e3o de topologia', c.sem_versao + ' modelo(s) n\u00e3o possuem vers\u00e3o vinculada. Registre os modelos ap\u00f3s o pr\u00f3ximo treino.');
            document.getElementById('btnRetreinarGov').style.display = 'none';
        } else {
            // Nenhum modelo registrado ainda
            atualizarBannerGov('info', 'Nenhum modelo registrado', 'Treine modelos para ativar o monitoramento de governan\u00e7a.');
            document.getElementById('btnRetreinarGov').style.display = 'none';
        }
    }

    /**
     * Atualiza visual do banner de governanca.
     * @param {string} tipo    - 'alerta', 'ok' ou 'info'
     * @param {string} titulo  - Titulo do banner
     * @param {string} mensagem - Mensagem descritiva
     */
    function atualizarBannerGov(tipo, titulo, mensagem) {
        const banner = document.getElementById('govBanner');
        const icon = document.getElementById('govBannerIcon');
        const tit = document.getElementById('govBannerTitle');
        const msg = document.getElementById('govBannerMsg');

        banner.className = 'gov-banner ' + tipo;

        switch (tipo) {
            case 'alerta':
                icon.setAttribute('name', 'warning-outline');
                break;
            case 'ok':
                icon.setAttribute('name', 'shield-checkmark-outline');
                break;
            case 'info':
                icon.setAttribute('name', 'information-circle-outline');
                break;
        }

        tit.textContent = titulo;
        msg.textContent = mensagem;
    }

    /**
     * Abre o modal de timeline de versoes e carrega dados.
     */
    function abrirModalTimeline() {
        document.getElementById('modalTimeline').classList.add('active');
        document.getElementById('timelineLoading').style.display = 'block';
        document.getElementById('timelineConteudo').style.display = 'none';
        carregarTimelineVersoes();
    }

    /** Fecha o modal de timeline. */
    function fecharModalTimeline() {
        document.getElementById('modalTimeline').classList.remove('active');
    }

    /**
     * Carrega historico de versoes da topologia via AJAX.
     */
    function carregarTimelineVersoes() {
        fetch('bd/operacoes/governancaTopologia.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'historico_versoes', limite: 20 })
        })
            .then(r => r.json())
            .then(data => {
                document.getElementById('timelineLoading').style.display = 'none';
                document.getElementById('timelineConteudo').style.display = 'block';

                if (!data.success || !data.versoes || data.versoes.length === 0) {
                    document.getElementById('timelineSubtitulo').textContent = 'Nenhuma vers\u00e3o registrada';
                    document.getElementById('timelineLista').innerHTML = `
                    <div class="timeline-vazio">
                        <ion-icon name="git-branch-outline"></ion-icon>
                        <p style="font-size:14px;font-weight:600;color:#334155;">Sem hist\u00f3rico</p>
                        <p style="font-size:12px;">Salve um n\u00f3 ou conex\u00e3o no flowchart para gerar a primeira vers\u00e3o.</p>
                    </div>
                `;
                    return;
                }

                document.getElementById('timelineSubtitulo').textContent = data.total + ' vers\u00e3o(\u00f5es) registrada(s)';
                renderizarTimeline(data.versoes);
            })
            .catch(err => {
                document.getElementById('timelineLoading').style.display = 'none';
                document.getElementById('timelineConteudo').style.display = 'block';
                document.getElementById('timelineLista').innerHTML = `
                <div class="timeline-vazio">
                    <ion-icon name="wifi-outline" style="color:#ef4444;"></ion-icon>
                    <p>Erro de conex\u00e3o: ${escapeHtml(err.message)}</p>
                </div>
            `;
            });
    }

    /**
     * Renderiza a timeline de versoes no modal.
     * Layout padrão SIMP com badges de modelo e contadores de treinamento.
     *
     * @param {Array} versoes - Lista de versoes retornadas pelo backend
     */
    function renderizarTimeline(versoes) {
        // Atualizar subheader com contadores
        const resumoDiv = document.getElementById('timelineResumo');
        if (resumoDiv) resumoDiv.style.display = 'flex';

        const subtituloEl = document.getElementById('timelineSubtitulo');
        if (subtituloEl) subtituloEl.textContent = versoes.length + ' vers\u00e3o(\u00f5es) registrada(s)';

        // Pegar info de treinamento da versão mais recente (campo global)
        const treinadosEl = document.getElementById('timelineTreinados');
        if (treinadosEl && versoes.length > 0) {
            const v0 = versoes[0];
            const treinados = parseInt(v0.QTD_PONTOS_TREINADOS) || 0;
            const comPonto = parseInt(v0.QTD_NOS_COM_PONTO) || 0;
            if (comPonto > 0) {
                treinadosEl.innerHTML = '<ion-icon name="hardware-chip-outline" style="font-size:14px;vertical-align:middle;margin-right:4px;color:#2563eb;"></ion-icon>' +
                    treinados + ' de ' + comPonto + ' pontos com modelo treinado';
            }
        }

        let html = '';

        versoes.forEach((v, idx) => {
            // Formatar data
            const dtCadastro = v.DT_CADASTRO || '';
            let dataFormatada = dtCadastro;
            if (dtCadastro) {
                try {
                    const d = new Date(dtCadastro);
                    dataFormatada = d.toLocaleDateString('pt-BR') + ' ' +
                        d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
                } catch (e) { /* fallback: dtCadastro puro */ }
            }

            // Diff (se houver)
            const diff = v.diff || null;
            let diffHtml = '';
            let descricao = v.DS_DESCRICAO || '';

            // Se tiver diff com detalhes, montar descrição legível
            if (diff) {
                // Descrição legível do que mudou
                if (diff.tipo_mudanca) {
                    const tipos = {
                        'nodo_criado': 'N\u00f3 adicionado',
                        'nodo_atualizado': 'N\u00f3 atualizado',
                        'nodo_removido': 'N\u00f3 removido',
                        'conexao_criada': 'Conex\u00e3o criada',
                        'conexao_removida': 'Conex\u00e3o removida'
                    };
                    descricao = tipos[diff.tipo_mudanca] || descricao;
                }

                // ID do nó/conexão afetado
                if (diff.cd_nodo) {
                    descricao += ': ' + diff.cd_nodo;
                }
                if (diff.cd_conexao) {
                    descricao += ': ' + diff.cd_conexao;
                }

                // Deltas
                const deltaNos = (diff.nos_depois || 0) - (diff.nos_antes || 0);
                const deltaCx = (diff.conexoes_depois || 0) - (diff.conexoes_antes || 0);

                if (deltaNos !== 0) {
                    diffHtml += '<span class="timeline-stat" style="color:' +
                        (deltaNos > 0 ? '#22c55e' : '#ef4444') + ';">' +
                        '<ion-icon name="' + (deltaNos > 0 ? 'add-circle-outline' : 'remove-circle-outline') +
                        '"></ion-icon> ' + Math.abs(deltaNos) + ' n\u00f3(s)</span>';
                }
                if (deltaCx !== 0) {
                    diffHtml += '<span class="timeline-stat" style="color:' +
                        (deltaCx > 0 ? '#22c55e' : '#ef4444') + ';">' +
                        '<ion-icon name="' + (deltaCx > 0 ? 'add-circle-outline' : 'remove-circle-outline') +
                        '"></ion-icon> ' + Math.abs(deltaCx) + ' conex\u00e3o(\u00f5es)</span>';
                }
            }

            // Badge de modelos vinculados a esta versão
            const modelosAtivos = parseInt(v.QTD_MODELOS_ATIVOS) || 0;
            const modelosBadge = modelosAtivos > 0
                ? '<span class="timeline-badge modelos"><ion-icon name="hardware-chip-outline" style="font-size:10px;vertical-align:middle;margin-right:2px;"></ion-icon> ' +
                  modelosAtivos + ' modelo(s) treinado(s) nesta vers\u00e3o</span>'
                : '';

            // Badge "ATUAL" apenas na primeira versão
            const badgeAtual = idx === 0
                ? '<span class="timeline-badge" style="background:#dcfce7;color:#166534;">ATUAL</span>'
                : '';

            html += '<div class="timeline-item">' +
                '<div class="timeline-dot"></div>' +
                '<div class="timeline-body">' +
                    // Linha 1: Hash + Data + Badges
                    '<div class="timeline-header">' +
                        '<span class="timeline-hash">' + escapeHtml(v.hash_curto || '') + '</span>' +
                        '<span class="timeline-data">' + escapeHtml(dataFormatada) + '</span>' +
                        badgeAtual +
                        modelosBadge +
                    '</div>' +
                    // Linha 2: Descrição legível
                    '<div class="timeline-desc">' + escapeHtml(descricao || 'Sem descri\u00e7\u00e3o') + '</div>' +
                    // Linha 3: Contadores
                    '<div class="timeline-stats">' +
                        '<span class="timeline-stat">' +
                            '<ion-icon name="git-network-outline"></ion-icon> ' +
                            (v.QTD_NOS_ATIVOS || 0) + ' n\u00f3s' +
                        '</span>' +
                        '<span class="timeline-stat">' +
                            '<ion-icon name="arrow-forward-outline"></ion-icon> ' +
                            (v.QTD_CONEXOES_ATIVAS || 0) + ' conex\u00f5es' +
                        '</span>' +
                        '<span class="timeline-stat">' +
                            '<ion-icon name="speedometer-outline"></ion-icon> ' +
                            (v.QTD_NOS_COM_PONTO || 0) + ' com ponto' +
                        '</span>' +
                        diffHtml +
                    '</div>' +
                '</div>' +
            '</div>';
        });

        document.getElementById('timelineLista').innerHTML = html;
    }

    /**
     * Acao do botao "Retreinar" no banner de governanca.
     * Lista os modelos desatualizados e sugere retreino.
     */
    function retreinarDesatualizados() {
        if (!ultimoStatusGov || !ultimoStatusGov.modelos || ultimoStatusGov.modelos.length === 0) {
            showToast('Nenhum modelo desatualizado encontrado', 'info');
            return;
        }

        // Listar pontos afetados
        const pontos = ultimoStatusGov.modelos.map(m =>
            (m.DS_PONTO_NOME || '') + ' (R\u00b2: ' + (m.VL_R2 != null ? parseFloat(m.VL_R2).toFixed(3) : '-') + ')'
        );
        const msg = 'Modelos desatualizados:\n\n' + pontos.join('\n') +
            '\n\nDeseja iniciar o retreino de todos os modelos?';

        if (confirm(msg)) {
            // Disparar treino geral (usa funcao existente se disponivel)
            if (typeof treinarTodosModelos === 'function') {
                treinarTodosModelos();
            } else if (typeof abrirModalTreinarTodos === 'function') {
                abrirModalTreinarTodos();
            } else {
                showToast('Use o bot\u00e3o "Treinar Todos" na barra de ferramentas', 'info');
            }
        }
    }

    // --- Verificar governanca ao carregar a pagina ---
    verificarGovernancaTopologia();


    fecharModalDiag();
    // ============================================
    // Dropdown customizado de Sistemas (sem Select2)
    // ============================================

    /** Valor selecionado no dropdown de sistema */
    let syncSistemaSelecionado = 0;

    /**
     * Abre/fecha o dropdown de sistemas.
     */
    function toggleSyncDropdown() {
        const wrapper = document.getElementById('syncDropdownWrapper');
        const isOpen = wrapper.classList.contains('open');
        if (isOpen) {
            fecharSyncDropdown();
        } else {
            wrapper.classList.add('open');
            // Focar no campo de busca
            setTimeout(() => {
                document.getElementById('syncDropdownSearch').focus();
            }, 50);
        }
    }

    /**
     * Fecha o dropdown de sistemas.
     */
    function fecharSyncDropdown() {
        const wrapper = document.getElementById('syncDropdownWrapper');
        wrapper.classList.remove('open');
        // Limpar busca
        const search = document.getElementById('syncDropdownSearch');
        if (search) {
            search.value = '';
            filtrarSyncSistemas('');
        }
    }

    /**
     * Seleciona um sistema no dropdown e dispara o sync check.
     * @param {number} valor - CD_CHAVE do sistema (0 = todos)
     * @param {string} label - Nome para exibir
     */
    function selecionarSyncSistema(valor, label) {
        syncSistemaSelecionado = valor;
        document.getElementById('syncDropdownLabel').textContent = label;

        // Atualizar visual: marcar o selecionado
        document.querySelectorAll('#syncDropdownOptions .sync-dropdown-option').forEach(opt => {
            opt.classList.toggle('selected', parseInt(opt.dataset.value) === valor);
        });

        fecharSyncDropdown();
        executarSyncCheck();
    }

    /**
     * Filtra as opções do dropdown por texto digitado.
     * @param {string} texto - Termo de busca
     */
    function filtrarSyncSistemas(texto) {
        const termo = texto.toLowerCase().trim();
        document.querySelectorAll('#syncDropdownOptions .sync-dropdown-option').forEach(opt => {
            if (parseInt(opt.dataset.value) === 0) {
                // "Todos os Sistemas" sempre visível
                opt.classList.remove('hidden');
                return;
            }
            const nome = opt.textContent.toLowerCase();
            opt.classList.toggle('hidden', termo !== '' && !nome.includes(termo));
        });
    }

    // Fechar dropdown ao clicar fora
    document.addEventListener('click', function (e) {
        const wrapper = document.getElementById('syncDropdownWrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            fecharSyncDropdown();
        }
    });

    // ============================================
    // Diagnóstico ML
    // ============================================

    /** Dados do último diagnóstico executado */
    var ultimoDiagnostico = null;

    /**
     * Abre o modal de diagnóstico e executa verificação para um ponto.
     * Pode ser chamado a partir do card do modelo ou do modal de novo treino.
     * @param {number} cdPonto - Código do ponto de medição
     */
    function abrirDiagnostico(cdPonto) {
        ultimoDiagnostico = null;

        // Abrir modal
        document.getElementById('modalDiagnostico').classList.add('active');
        document.getElementById('diagLoading').style.display = 'block';
        document.getElementById('diagConteudo').style.display = 'none';
        document.getElementById('diagBtnTreinar').style.display = 'none';
        document.getElementById('diagBtnTreinar').disabled = true;
        document.getElementById('diagPontoCodigo').textContent = '#' + cdPonto;
        document.getElementById('diagPontoNome').textContent = 'Carregando...';

        // Executar diagnóstico
        fetch('bd/operacoes/diagnosticoML.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'diagnostico', cd_ponto: cdPonto })
        })
            .then(r => r.json())
            .then(data => {
                document.getElementById('diagLoading').style.display = 'none';
                document.getElementById('diagConteudo').style.display = 'block';

                if (!data.success) {
                    document.getElementById('diagConteudo').innerHTML = `
                    <div style="text-align:center;padding:30px;color:#ef4444;">
                        <ion-icon name="alert-circle-outline" style="font-size:32px;"></ion-icon>
                        <p style="margin-top:10px;">${escapeHtml(data.error || 'Erro desconhecido')}</p>
                    </div>`;
                    return;
                }

                ultimoDiagnostico = data;

                // Atualizar info do ponto
                document.getElementById('diagPontoCodigo').textContent = data.codigo_formatado || ('#' + cdPonto);
                document.getElementById('diagPontoNome').textContent = data.ds_nome || '';

                // Renderizar etapas
                renderizarDiagnostico(data);
            })
            .catch(err => {
                document.getElementById('diagLoading').style.display = 'none';
                document.getElementById('diagConteudo').style.display = 'block';
                document.getElementById('diagConteudo').innerHTML = `
                <div style="text-align:center;padding:30px;color:#ef4444;">
                    <ion-icon name="wifi-outline" style="font-size:32px;"></ion-icon>
                    <p style="margin-top:10px;">Erro de conexão: ${escapeHtml(err.message)}</p>
                </div>`;
            });
    }

    /**
     * Renderiza o resultado do diagnóstico como timeline.
     * @param {Object} data - Resposta do endpoint diagnosticoML.php
     */
    function renderizarDiagnostico(data) {
        const etapas = data.etapas || [];
        const resumo = data.resumo || {};

        let html = '<div class="diag-timeline">';

        etapas.forEach(etapa => {
            // Ícone por status
            const icones = {
                ok: 'checkmark-outline',
                alerta: 'alert-outline',
                erro: 'close-outline'
            };
            const icone = icones[etapa.status] || 'help-outline';

            // Detalhes (colapsável)
            const temDetalhes = etapa.detalhes && etapa.detalhes.length > 0;
            const expandidoInicial = etapa.status !== 'ok'; // Expandir alertas/erros

            html += `
                <div class="diag-etapa ${expandidoInicial ? 'expanded' : ''}"
                     id="diagEtapa_${etapa.numero}">
                    <!-- Ícone na timeline -->
                    <div class="diag-etapa-icon ${etapa.status}">
                        <ion-icon name="${icone}"></ion-icon>
                    </div>
                    <!-- Header clicável -->
                    <div class="diag-etapa-header"
                         onclick="${temDetalhes ? `toggleDiagEtapa(${etapa.numero})` : ''}">
                        <span class="etapa-numero">${etapa.numero}.</span>
                        <span class="etapa-titulo">${escapeHtml(etapa.titulo)}</span>
                        <span class="etapa-msg ${etapa.status}">${escapeHtml(etapa.mensagem)}</span>
                        ${temDetalhes ? '<ion-icon name="chevron-down-outline" class="etapa-toggle"></ion-icon>' : ''}
                    </div>`;

            // Corpo com detalhes
            if (temDetalhes) {
                html += '<div class="diag-etapa-body">';
                etapa.detalhes.forEach(d => {
                    html += `
                        <div class="diag-detalhe-row">
                            <span class="dl-label">${escapeHtml(d.label)}</span>
                            <span class="dl-valor">${escapeHtml(d.valor)}</span>
                        </div>`;
                });
                html += '</div>';
            }

            html += '</div>';
        });

        html += '</div>';

        // Resumo / veredicto
        if (resumo.bloqueios > 0) {
            html += `
                <div class="diag-resumo bloqueado">
                    <ion-icon name="close-circle-outline"></ion-icon>
                    <div>
                        <div class="resumo-texto">${escapeHtml(resumo.veredicto)}</div>
                        <div class="resumo-detalhe">${resumo.ok} ok · ${resumo.alertas} alerta(s) · ${resumo.bloqueios} bloqueio(s)</div>
                    </div>
                </div>`;
        } else if (resumo.alertas > 0) {
            html += `
                <div class="diag-resumo viavel-alerta">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    <div>
                        <div class="resumo-texto">${escapeHtml(resumo.veredicto)}</div>
                        <div class="resumo-detalhe">${resumo.ok} ok · ${resumo.alertas} alerta(s) — modelo pode treinar mas com qualidade reduzida</div>
                    </div>
                </div>`;
        } else {
            html += `
                <div class="diag-resumo viavel">
                    <ion-icon name="checkmark-circle-outline"></ion-icon>
                    <div>
                        <div class="resumo-texto">${escapeHtml(resumo.veredicto)}</div>
                        <div class="resumo-detalhe">Todos os pré-requisitos atendidos</div>
                    </div>
                </div>`;
        }

        document.getElementById('diagConteudo').innerHTML = html;

        // Mostrar botão "Treinar" se viável e o usuário tiver permissão
        const btnTreinar = document.getElementById('diagBtnTreinar');
        if (resumo.viavel && typeof podeEditar !== 'undefined' && podeEditar) {
            btnTreinar.style.display = 'inline-flex';
            btnTreinar.disabled = false;
        }
    }

    /**
     * Expande/colapsa uma etapa do diagnóstico.
     * @param {number} numero - Número da etapa
     */
    function toggleDiagEtapa(numero) {
        const etapa = document.getElementById('diagEtapa_' + numero);
        if (etapa) etapa.classList.toggle('expanded');
    }

    /**
     * Fecha o modal de diagnóstico.
     */
    function fecharModalDiag() {
        document.getElementById('modalDiagnostico').classList.remove('active');
        ultimoDiagnostico = null;
    }

    /**
     * Treina o ponto direto do modal de diagnóstico.
     * Reutiliza a função retreinar() já existente.
     */
    function treinarAposDiag() {
        if (!ultimoDiagnostico) return;
        const cdPonto = ultimoDiagnostico.cd_ponto;
        // Buscar tipo do medidor a partir do diagnóstico (etapa 2)
        const etapaTipo = (ultimoDiagnostico.etapas || []).find(e => e.numero === 2);
        let tipoMedidor = 1;
        if (etapaTipo && etapaTipo.mensagem) {
            const match = etapaTipo.mensagem.match(/Tipo (\d+)/);
            if (match) tipoMedidor = parseInt(match[1]);
        }
        fecharModalDiag();
        retreinar(cdPonto, tipoMedidor);
    }
</script>

<!-- ============================================
     Modal: Timeline de Versoes da Topologia (Fase A1)
     ============================================ -->
<div class="diag-modal-overlay" id="modalTimeline">
    <div class="diag-modal" style="max-width:720px;">
        <div class="diag-modal-header">
            <h3><ion-icon name="git-branch-outline"></ion-icon> Hist&oacute;rico de Vers&otilde;es da Topologia</h3>
            <button class="diag-close" onclick="fecharModalTimeline()"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <div class="diag-ponto-info" id="timelineResumo" style="display:none;">
            <span class="codigo" id="timelineSubtitulo" style="font-family:Arial,sans-serif;font-size:12px;">0 vers&otilde;es</span>
            <span class="nome" id="timelineTreinados" style="font-size:12px;color:#64748b;"></span>
        </div>
        <div class="diag-modal-body" style="padding:20px;">
            <div id="timelineLoading" style="text-align:center;padding:40px;color:#64748b;">
                <ion-icon name="sync-outline" style="font-size:32px;animation:spin 1s linear infinite;display:block;margin:0 auto 12px;color:#3b82f6;"></ion-icon>
                <p>Carregando hist&oacute;rico...</p>
            </div>
            <div id="timelineConteudo" style="display:none;">
                <div class="timeline-lista" id="timelineLista"></div>
            </div>
        </div>
        <div class="diag-modal-footer">
            <button type="button" class="diag-btn-fechar" onclick="fecharModalTimeline()">Fechar</button>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.inc.php'; ?>
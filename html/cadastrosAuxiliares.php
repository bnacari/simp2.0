<?php

/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Cadastros Auxiliares
 */

include_once 'includes/header.inc.php';
include_once 'bd/conexao.php';

// Agora verificar permissão
// Recarregar permissões do banco (garante que estão atualizadas)
recarregarPermissoesUsuario();

// Agora verificar permissão
exigePermissaoTela('CADASTRO', ACESSO_LEITURA);

// Permissão do usuário para este módulo
$podeEditar = podeEditarTela('CADASTRO');

include_once 'includes/menu.inc.php';
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<?php
// Buscar Unidades para selects
$sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME, CD_CODIGO FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

// Buscar Sistemas de Água para select de ETA (tabela correta: SISTEMA_AGUA)
$sqlSistemas = $pdoSIMP->query("SELECT CD_CHAVE, DS_NOME FROM SIMP.dbo.SISTEMA_AGUA ORDER BY DS_NOME");
$sistemasAgua = $sqlSistemas->fetchAll(PDO::FETCH_ASSOC);

// Buscar Fórmulas para select de ETA
$sqlFormulas = $pdoSIMP->query("SELECT CD_CHAVE, DS_NOME FROM SIMP.dbo.FORMULA ORDER BY DS_NOME");
$formulas = $sqlFormulas->fetchAll(PDO::FETCH_ASSOC);

// Buscar Localidades para select de Sistema de Água
$sqlLocalidades = $pdoSIMP->query("SELECT CD_CHAVE, CD_LOCALIDADE, DS_NOME FROM SIMP.dbo.LOCALIDADE ORDER BY DS_NOME");
$localidades = $sqlLocalidades->fetchAll(PDO::FETCH_ASSOC);

// Tipos de Cálculo para Tipo Medidor
$tiposCalculo = [
    1 => 'Vazão',
    2 => 'Volume',
    3 => 'Pressão',
    4 => 'Nível'
];
?>

<!-- Choices.js CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- css da tela -->
<link rel="stylesheet" href="style/css/cadastrosAuxiliares.css">

<div class="page-container">
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="settings-outline"></ion-icon>
                </div>
                <div>
                    <h1>Cadastros Gerais</h1>
                    <p class="page-header-subtitle">Gerencie os cadastros gerais do sistema</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="tabs-navigation">
        <button type="button" class="tab-btn active" data-tab="tipoMedidor">
            <ion-icon name="speedometer-outline"></ion-icon>
            Tipo Medidor
        </button>
        <button type="button" class="tab-btn" data-tab="tipoReservatorio">
            <ion-icon name="water-outline"></ion-icon>
            Tipo Reservatório
        </button>
        <button type="button" class="tab-btn" data-tab="areaInfluencia">
            <ion-icon name="map-outline"></ion-icon>
            Área de Influência
        </button>
        <button type="button" class="tab-btn" data-tab="unidade">
            <ion-icon name="business-outline"></ion-icon>
            Unidade
        </button>
        <button type="button" class="tab-btn" data-tab="localidade">
            <ion-icon name="location-outline"></ion-icon>
            Localidade
        </button>
        <button type="button" class="tab-btn" data-tab="sistemaAgua">
            <ion-icon name="git-network-outline"></ion-icon>
            Sistema de Água
        </button>
        <!-- <button type="button" class="tab-btn" data-tab="eta">
            <ion-icon name="flask-outline"></ion-icon>
            ETA
        </button> -->
        <button type="button" class="tab-btn" data-tab="instrumentos">
            <ion-icon name="hardware-chip-outline"></ion-icon>
            Instrumentos
        </button>
    </div>

    <!-- ========================================
         TAB: TIPO MEDIDOR
         ======================================== -->
    <div class="tab-content active" id="tab-tipoMedidor">
        <div class="filters-card">
            <div class="filters-header">
                <div class="filters-title">
                    <ion-icon name="speedometer-outline"></ion-icon>
                    Tipos de Medidor
                </div>
                <?php if ($podeEditar): ?>
                    <button type="button" class="btn-novo" onclick="abrirModalTipoMedidor()">
                        <ion-icon name="add-outline"></ion-icon>
                        Novo Tipo
                    </button>
                <?php endif; ?>
            </div>
            <div class="filters-grid">
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="search-outline"></ion-icon>
                        Buscar
                    </label>
                    <div class="input-search-wrapper">
                        <ion-icon name="search-outline" class="input-search-icon"></ion-icon>
                        <input type="text" id="filtroTipoMedidor" class="form-control input-search"
                            placeholder="Digite para buscar...">
                        <button type="button" class="btn-search-clear"
                            onclick="limparFiltro('filtroTipoMedidor', buscarTiposMedidor)">
                            <ion-icon name="close-circle"></ion-icon>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="results-info">
            <div class="results-count">
                <ion-icon name="list-outline"></ion-icon>
                <span id="countTipoMedidor">Carregando...</span>
            </div>
        </div>

        <div class="table-container">
            <div class="loading-overlay" id="loadingTipoMedidor">
                <div class="loading-spinner"></div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Código</th>
                            <th>Nome</th>
                            <th style="width: 150px;">Tipo Cálculo</th>
                            <th style="width: 100px;" class="no-sort">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaTipoMedidor"></tbody>
                </table>
            </div>
        </div>

        <div class="pagination-container" id="paginacaoTipoMedidor"></div>
    </div>

    <!-- ========================================
         TAB: TIPO RESERVATÓRIO
         ======================================== -->
    <div class="tab-content" id="tab-tipoReservatorio">
        <div class="filters-card">
            <div class="filters-header">
                <div class="filters-title">
                    <ion-icon name="water-outline"></ion-icon>
                    Tipos de Reservatório
                </div>
                <?php if ($podeEditar): ?>
                    <button type="button" class="btn-novo" onclick="abrirModalTipoReservatorio()">
                        <ion-icon name="add-outline"></ion-icon>
                        Novo Tipo
                    </button>
                <?php endif; ?>
            </div>
            <div class="filters-grid">
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="search-outline"></ion-icon>
                        Buscar
                    </label>
                    <div class="input-search-wrapper">
                        <ion-icon name="search-outline" class="input-search-icon"></ion-icon>
                        <input type="text" id="filtroTipoReservatorio" class="form-control input-search"
                            placeholder="Digite para buscar...">
                        <button type="button" class="btn-search-clear"
                            onclick="limparFiltro('filtroTipoReservatorio', buscarTiposReservatorio)">
                            <ion-icon name="close-circle"></ion-icon>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="results-info">
            <div class="results-count">
                <ion-icon name="list-outline"></ion-icon>
                <span id="countTipoReservatorio">Carregando...</span>
            </div>
        </div>

        <div class="table-container">
            <div class="loading-overlay" id="loadingTipoReservatorio">
                <div class="loading-spinner"></div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Código</th>
                            <th>Nome</th>
                            <th style="width: 100px;" class="no-sort">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaTipoReservatorio"></tbody>
                </table>
            </div>
        </div>

        <div class="pagination-container" id="paginacaoTipoReservatorio"></div>
    </div>

    <!-- ========================================
         TAB: ÁREA DE INFLUÊNCIA
         ======================================== -->
    <div class="tab-content" id="tab-areaInfluencia">
        <div class="filters-card">
            <div class="filters-header">
                <div class="filters-title">
                    <ion-icon name="map-outline"></ion-icon>
                    Áreas de Influência
                </div>
                <?php if ($podeEditar): ?>
                    <button type="button" class="btn-novo" onclick="abrirModalAreaInfluencia()">
                        <ion-icon name="add-outline"></ion-icon>
                        Nova Área
                    </button>
                <?php endif; ?>
            </div>
            <div class="filters-grid">
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="search-outline"></ion-icon>
                        Buscar por Município
                    </label>
                    <div class="input-search-wrapper">
                        <ion-icon name="search-outline" class="input-search-icon"></ion-icon>
                        <input type="text" id="filtroAreaInfluencia" class="form-control input-search"
                            placeholder="Digite para buscar...">
                        <button type="button" class="btn-search-clear"
                            onclick="limparFiltro('filtroAreaInfluencia', buscarAreasInfluencia)">
                            <ion-icon name="close-circle"></ion-icon>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="results-info">
            <div class="results-count">
                <ion-icon name="list-outline"></ion-icon>
                <span id="countAreaInfluencia">Carregando...</span>
            </div>
        </div>

        <div class="table-container">
            <div class="loading-overlay" id="loadingAreaInfluencia">
                <div class="loading-spinner"></div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Código</th>
                            <th>Município</th>
                            <th style="width: 140px;">Taxa Ocupação</th>
                            <th style="width: 180px;">Densidade Demográfica</th>
                            <th style="width: 100px;">Bairros</th>
                            <th style="width: 100px;" class="no-sort">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaAreaInfluencia"></tbody>
                </table>
            </div>
        </div>

        <div class="pagination-container" id="paginacaoAreaInfluencia"></div>
    </div>

    <!-- ========================================
         TAB: UNIDADE
         ======================================== -->
    <div class="tab-content" id="tab-unidade">
        <div class="filters-card">
            <div class="filters-header">
                <div class="filters-title">
                    <ion-icon name="business-outline"></ion-icon>
                    Unidades
                </div>
                <?php if ($podeEditar): ?>
                    <button type="button" class="btn-novo" onclick="abrirModalUnidade()">
                        <ion-icon name="add-outline"></ion-icon>
                        Nova Unidade
                    </button>
                <?php endif; ?>
            </div>
            <div class="filters-grid">
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="search-outline"></ion-icon>
                        Buscar
                    </label>
                    <div class="input-search-wrapper">
                        <ion-icon name="search-outline" class="input-search-icon"></ion-icon>
                        <input type="text" id="filtroUnidade" class="form-control input-search"
                            placeholder="Digite para buscar...">
                        <button type="button" class="btn-search-clear"
                            onclick="limparFiltro('filtroUnidade', buscarUnidades)">
                            <ion-icon name="close-circle"></ion-icon>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="results-info">
            <div class="results-count">
                <ion-icon name="list-outline"></ion-icon>
                <span id="countUnidade">Carregando...</span>
            </div>
        </div>

        <div class="table-container">
            <div class="loading-overlay" id="loadingUnidade">
                <div class="loading-spinner"></div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th style="width: 120px;">Código</th>
                            <th>Nome</th>
                            <th style="width: 100px;" class="no-sort">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaUnidade"></tbody>
                </table>
            </div>
        </div>

        <div class="pagination-container" id="paginacaoUnidade"></div>
    </div>

    <!-- ========================================
         TAB: LOCALIDADE
         ======================================== -->
    <div class="tab-content" id="tab-localidade">
        <div class="filters-card">
            <div class="filters-header">
                <div class="filters-title">
                    <ion-icon name="location-outline"></ion-icon>
                    Localidades
                </div>
                <?php if ($podeEditar): ?>
                    <button type="button" class="btn-novo" onclick="abrirModalLocalidade()">
                        <ion-icon name="add-outline"></ion-icon>
                        Nova Localidade
                    </button>
                <?php endif; ?>
            </div>
            <div class="filters-grid">
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="business-outline"></ion-icon>
                        Unidade
                    </label>
                    <select id="filtroLocalidadeUnidade" class="form-control">
                        <option value="">Todas as Unidades</option>
                        <?php foreach ($unidades as $u): ?>
                            <option value="<?= $u['CD_UNIDADE'] ?>">
                                <?= htmlspecialchars($u['CD_CODIGO'] . ' - ' . $u['DS_NOME']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="search-outline"></ion-icon>
                        Buscar
                    </label>
                    <div class="input-search-wrapper">
                        <ion-icon name="search-outline" class="input-search-icon"></ion-icon>
                        <input type="text" id="filtroLocalidade" class="form-control input-search"
                            placeholder="Digite para buscar...">
                        <button type="button" class="btn-search-clear"
                            onclick="limparFiltro('filtroLocalidade', buscarLocalidades)">
                            <ion-icon name="close-circle"></ion-icon>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="results-info">
            <div class="results-count">
                <ion-icon name="list-outline"></ion-icon>
                <span id="countLocalidade">Carregando...</span>
            </div>
        </div>

        <div class="table-container">
            <div class="loading-overlay" id="loadingLocalidade">
                <div class="loading-spinner"></div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Código</th>
                            <th style="width: 120px;">Cód. Localidade</th>
                            <th>Nome</th>
                            <th style="width: 200px;">Unidade</th>
                            <th style="width: 100px;" class="no-sort">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaLocalidade"></tbody>
                </table>
            </div>
        </div>

        <div class="pagination-container" id="paginacaoLocalidade"></div>
    </div>

    <!-- ========================================
         TAB: SISTEMA DE ÁGUA
         ======================================== -->
    <div class="tab-content" id="tab-sistemaAgua">
        <div class="filters-card">
            <div class="filters-header">
                <div class="filters-title">
                    <ion-icon name="git-network-outline"></ion-icon>
                    Sistemas de Água
                </div>
                <?php if ($podeEditar): ?>
                    <button type="button" class="btn-novo" onclick="abrirModalSistemaAgua()">
                        <ion-icon name="add-outline"></ion-icon>
                        Novo Sistema
                    </button>
                <?php endif; ?>
            </div>
            <div class="filters-grid">
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="search-outline"></ion-icon>
                        Buscar
                    </label>
                    <div class="input-search-wrapper">
                        <ion-icon name="search-outline" class="input-search-icon"></ion-icon>
                        <input type="text" id="filtroSistemaAgua" class="form-control input-search"
                            placeholder="Digite para buscar...">
                        <button type="button" class="btn-search-clear"
                            onclick="limparFiltro('filtroSistemaAgua', buscarSistemasAgua)">
                            <ion-icon name="close-circle"></ion-icon>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="results-info">
            <div class="results-count">
                <ion-icon name="list-outline"></ion-icon>
                <span id="countSistemaAgua">Carregando...</span>
            </div>
        </div>

        <div class="table-container">
            <div class="loading-overlay" id="loadingSistemaAgua">
                <div class="loading-spinner"></div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Código</th>
                            <th style="width: 220px;">Localidade</th>
                            <th>Nome</th>
                            <th style="width: 250px;">Descrição</th>
                            <th style="width: 150px;">Última Atualização</th>
                            <th style="width: 100px;" class="no-sort">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaSistemaAgua"></tbody>
                </table>
            </div>
        </div>

        <div class="pagination-container" id="paginacaoSistemaAgua"></div>
    </div>

    <!-- ========================================
         TAB: ETA
         ======================================== -->
    <div class="tab-content" id="tab-eta">
        <div class="filters-card">
            <div class="filters-header">
                <div class="filters-title">
                    <ion-icon name="business-outline"></ion-icon>
                    ETAs - Estações de Tratamento de Água
                </div>
                <?php if ($podeEditar): ?>
                    <button type="button" class="btn-novo" onclick="abrirModalEta()">
                        <ion-icon name="add-outline"></ion-icon>
                        Nova ETA
                    </button>
                <?php endif; ?>
            </div>
            <div class="filters-grid">
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="git-network-outline"></ion-icon>
                        Sistema de Água
                    </label>
                    <select id="filtroEtaSistema" class="form-control">
                        <option value="">Todos os Sistemas</option>
                        <?php foreach ($sistemasAgua as $s): ?>
                            <option value="<?= $s['CD_CHAVE'] ?>"><?= htmlspecialchars($s['DS_NOME']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="search-outline"></ion-icon>
                        Buscar
                    </label>
                    <div class="input-search-wrapper">
                        <ion-icon name="search-outline" class="input-search-icon"></ion-icon>
                        <input type="text" id="filtroEta" class="form-control input-search"
                            placeholder="Digite para buscar...">
                        <button type="button" class="btn-search-clear" onclick="limparFiltro('filtroEta', buscarEtas)">
                            <ion-icon name="close-circle"></ion-icon>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="results-info">
            <div class="results-count">
                <ion-icon name="list-outline"></ion-icon>
                <span id="countEta">Carregando...</span>
            </div>
        </div>

        <div class="table-container">
            <div class="loading-overlay" id="loadingEta">
                <div class="loading-spinner"></div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Código</th>
                            <th>Nome</th>
                            <th style="width: 200px;">Sistema de Água</th>
                            <th style="width: 120px;">Meta/Dia (m³)</th>
                            <th style="width: 150px;">Última Atualização</th>
                            <th style="width: 100px;" class="no-sort">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaEta"></tbody>
                </table>
            </div>
        </div>

        <div class="pagination-container" id="paginacaoEta"></div>
    </div>

    <!-- ========================================
         TAB: INSTRUMENTOS
         ======================================== -->
    <div class="tab-content" id="tab-instrumentos">
        <div class="filters-card">
            <div class="filters-header">
                <div class="filters-title">
                    <ion-icon name="hardware-chip-outline"></ion-icon>
                    Instrumentos
                </div>
                <?php if ($podeEditar): ?>
                    <button type="button" class="btn-novo" onclick="abrirModalInstrumento()">
                        <ion-icon name="add-outline"></ion-icon>
                        Novo Instrumento
                    </button>
                <?php endif; ?>
            </div>
            <div class="filters-grid">
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="speedometer-outline"></ion-icon>
                        Tipo de Medidor
                    </label>
                    <select id="filtroInstrumentoTipo" class="form-control">
                        <option value="">Todos</option>
                        <option value="1">Macromedidor</option>
                        <option value="2">Estação Pitométrica</option>
                        <option value="4">Medidor de Pressão</option>
                        <option value="6">Nível Reservatório</option>
                        <option value="8">Hidrômetro</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="link-outline"></ion-icon>
                        Vínculo
                    </label>
                    <select id="filtroInstrumentoVinculo" class="form-control">
                        <option value="">Todos</option>
                        <option value="vinculado">Vinculados</option>
                        <option value="disponivel">Sem vínculo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="search-outline"></ion-icon>
                        Buscar
                    </label>
                    <div class="input-search-wrapper">
                        <ion-icon name="search-outline" class="input-search-icon"></ion-icon>
                        <input type="text" id="filtroInstrumento" class="form-control input-search"
                            placeholder="Código, marca, modelo, série...">
                        <button type="button" class="btn-search-clear"
                            onclick="limparFiltro('filtroInstrumento', function(){ buscarInstrumentos(1); })">
                            <ion-icon name="close-circle"></ion-icon>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="results-info">
            <div class="results-count">
                <ion-icon name="list-outline"></ion-icon>
                <span id="countInstrumentos">Carregando...</span>
            </div>
        </div>

        <div class="table-container">
            <div class="loading-overlay" id="loadingInstrumentos">
                <div class="loading-spinner"></div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 70px; cursor:pointer;" onclick="ordenarInstrumentos('CD_CHAVE')"
                                id="thInstrCd">
                                Código <ion-icon name="swap-vertical-outline"
                                    style="font-size:12px;vertical-align:middle;color:#94a3b8;"></ion-icon>
                            </th>
                            <th id="thInstrTipo" style="width: 140px; display:none;">Tipo</th>
                            <th style="cursor:pointer;" onclick="ordenarInstrumentos('col1')" id="thInstrCol1">
                                Marca <ion-icon name="swap-vertical-outline"
                                    style="font-size:12px;vertical-align:middle;color:#94a3b8;"></ion-icon>
                            </th>
                            <th style="cursor:pointer;" onclick="ordenarInstrumentos('col2')" id="thInstrCol2">
                                Modelo / Linha <ion-icon name="swap-vertical-outline"
                                    style="font-size:12px;vertical-align:middle;color:#94a3b8;"></ion-icon>
                            </th>
                            <th style="cursor:pointer;" onclick="ordenarInstrumentos('col3')" id="thInstrCol3">
                                Série / S.N. <ion-icon name="swap-vertical-outline"
                                    style="font-size:12px;vertical-align:middle;color:#94a3b8;"></ion-icon>
                            </th>
                            <th style="width: 120px; cursor:pointer;" onclick="ordenarInstrumentos('col4')"
                                id="thInstrCol4">
                                Diâmetro <ion-icon name="swap-vertical-outline"
                                    style="font-size:12px;vertical-align:middle;color:#94a3b8;"></ion-icon>
                            </th>
                            <th style="cursor:pointer;" onclick="ordenarInstrumentos('DS_TAG')" id="thInstrTag">
                                TAG <ion-icon name="swap-vertical-outline"
                                    style="font-size:12px;vertical-align:middle;color:#94a3b8;"></ion-icon>
                            </th>
                            <th style="width: 200px; cursor:pointer;"
                                onclick="ordenarInstrumentos('DS_PONTO_VINCULADO')">
                                Ponto Vinculado <ion-icon name="swap-vertical-outline"
                                    style="font-size:12px;vertical-align:middle;color:#94a3b8;"></ion-icon>
                            </th>
                            <th style="width: 90px;" class="no-sort">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaInstrumentos"></tbody>
                </table>
            </div>
        </div>

        <div class="pagination-container" id="paginacaoInstrumentos"></div>
    </div>

    <!-- ========================================
         MODAL: INSTRUMENTO
         ======================================== -->
    <div class="modal-overlay" id="modalInstrumento">
        <div class="modal" style="max-width: 700px;">
            <div class="modal-header">
                <h3 class="modal-title" id="modalInstrumentoTitulo">Novo Instrumento</h3>
                <button type="button" class="modal-close" onclick="fecharModal('modalInstrumento')">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="instrCdChave">

                <!-- Tipo de Medidor (só visível no novo) -->
                <div class="form-group" id="grupoTipoInstr" style="margin-bottom: 16px;">
                    <label class="form-label">
                        <ion-icon name="speedometer-outline"></ion-icon>
                        Tipo de Medidor <span class="required">*</span>
                    </label>
                    <select id="instrTipoMedidor" class="form-control" onchange="atualizarCamposModal()">
                        <option value="1">Macromedidor</option>
                        <option value="2">Estação Pitométrica</option>
                        <option value="4">Medidor de Pressão</option>
                        <option value="6">Nível Reservatório</option>
                        <option value="8">Hidrômetro</option>
                    </select>
                </div>

                <!-- Container dinâmico de campos -->
                <div id="instrCampos"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    onclick="fecharModal('modalInstrumento')">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarInstrumento()">
                    <ion-icon name="save-outline"></ion-icon>
                    Salvar
                </button>
            </div>
        </div>
    </div>

</div>

<!-- ========================================
     MODAIS
     ======================================== -->

<!-- Modal Tipo Medidor -->
<div class="modal-overlay" id="modalTipoMedidor">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalTipoMedidorTitulo">Novo Tipo de Medidor</span>
            <button type="button" class="modal-close" onclick="fecharModal('modalTipoMedidor')">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="tipoMedidorId">
            <div class="form-group-modal">
                <label>Nome <span class="required">*</span></label>
                <input type="text" class="form-control" id="tipoMedidorNome" maxlength="100">
            </div>
            <div class="form-group-modal">
                <label>Tipo de Cálculo <span class="required">*</span></label>
                <select class="form-control choices-select" id="tipoMedidorCalculo">
                    <option value="">Selecione...</option>
                    <?php foreach ($tiposCalculo as $id => $nome): ?>
                        <option value="<?= $id ?>"><?= $nome ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fecharModal('modalTipoMedidor')">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="salvarTipoMedidor()">
                <ion-icon name="checkmark-outline"></ion-icon>
                Salvar
            </button>
        </div>
    </div>
</div>

<!-- Modal Tipo Reservatório -->
<div class="modal-overlay" id="modalTipoReservatorio">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalTipoReservatorioTitulo">Novo Tipo de Reservatório</span>
            <button type="button" class="modal-close" onclick="fecharModal('modalTipoReservatorio')">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="tipoReservatorioId">
            <div class="form-group-modal">
                <label>Nome <span class="required">*</span></label>
                <input type="text" class="form-control" id="tipoReservatorioNome" maxlength="100">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary"
                onclick="fecharModal('modalTipoReservatorio')">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="salvarTipoReservatorio()">
                <ion-icon name="checkmark-outline"></ion-icon>
                Salvar
            </button>
        </div>
    </div>
</div>

<!-- Modal Área de Influência -->
<div class="modal-overlay" id="modalAreaInfluencia">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalAreaInfluenciaTitulo">Nova Área de Influência</span>
            <button type="button" class="modal-close" onclick="fecharModal('modalAreaInfluencia')">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="areaInfluenciaId">
            <div class="form-group-modal">
                <label>Município <span class="required">*</span></label>
                <input type="text" class="form-control" id="areaInfluenciaMunicipio" maxlength="100">
            </div>
            <div class="form-group-modal">
                <label>Taxa de Ocupação</label>
                <input type="number" step="0.01" class="form-control" id="areaInfluenciaTaxa">
            </div>
            <div class="form-group-modal">
                <label>Densidade Demográfica</label>
                <input type="number" step="0.01" class="form-control" id="areaInfluenciaDensidade">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary"
                onclick="fecharModal('modalAreaInfluencia')">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="salvarAreaInfluencia()">
                <ion-icon name="checkmark-outline"></ion-icon>
                Salvar
            </button>
        </div>
    </div>
</div>

<!-- Modal Unidade -->
<div class="modal-overlay" id="modalUnidade">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalUnidadeTitulo">Nova Unidade</span>
            <button type="button" class="modal-close" onclick="fecharModal('modalUnidade')">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="unidadeId">
            <div class="form-group-modal">
                <label>Código <span class="required">*</span></label>
                <input type="text" class="form-control" id="unidadeCodigo" maxlength="20">
            </div>
            <div class="form-group-modal">
                <label>Nome <span class="required">*</span></label>
                <input type="text" class="form-control" id="unidadeNome" maxlength="200">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fecharModal('modalUnidade')">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="salvarUnidade()">
                <ion-icon name="checkmark-outline"></ion-icon>
                Salvar
            </button>
        </div>
    </div>
</div>

<!-- Modal Localidade -->
<div class="modal-overlay" id="modalLocalidade">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalLocalidadeTitulo">Nova Localidade</span>
            <button type="button" class="modal-close" onclick="fecharModal('modalLocalidade')">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="localidadeId">
            <div class="form-group-modal">
                <label>Unidade <span class="required">*</span></label>
                <select class="form-control choices-select" id="localidadeUnidade">
                    <option value="">Selecione...</option>
                    <?php foreach ($unidades as $u): ?>
                        <option value="<?= $u['CD_UNIDADE'] ?>">
                            <?= htmlspecialchars($u['CD_CODIGO'] . ' - ' . $u['DS_NOME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group-modal">
                <label>Código Localidade <span class="required">*</span></label>
                <input type="text" class="form-control" id="localidadeCodigo" maxlength="20">
            </div>
            <div class="form-group-modal">
                <label>Nome <span class="required">*</span></label>
                <input type="text" class="form-control" id="localidadeNome" maxlength="200">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fecharModal('modalLocalidade')">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="salvarLocalidade()">
                <ion-icon name="checkmark-outline"></ion-icon>
                Salvar
            </button>
        </div>
    </div>
</div>

<!-- Modal Sistema de Água -->
<div class="modal-overlay" id="modalSistemaAgua">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalSistemaAguaTitulo">Novo Sistema de Água</span>
            <button type="button" class="modal-close" onclick="fecharModal('modalSistemaAgua')">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="sistemaAguaId">
            <div class="form-group-modal">
                <label>Localidade <span class="required">*</span></label>
                <select class="form-control choices-select" id="sistemaAguaLocalidade">
                    <option value="">Selecione...</option>
                    <?php foreach ($localidades as $loc): ?>
                        <option value="<?= htmlspecialchars($loc['CD_CHAVE']) ?>">
                            <?= htmlspecialchars($loc['CD_LOCALIDADE'] . ' - ' . $loc['DS_NOME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group-modal">
                <label>Nome <span class="required">*</span></label>
                <input type="text" class="form-control" id="sistemaAguaNome" maxlength="200">
            </div>
            <div class="form-group-modal">
                <label>Descrição</label>
                <textarea class="form-control" id="sistemaAguaDescricao" rows="3"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fecharModal('modalSistemaAgua')">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="salvarSistemaAgua()">
                <ion-icon name="checkmark-outline"></ion-icon>
                Salvar
            </button>
        </div>
    </div>
</div>

<!-- Modal ETA -->
<div class="modal-overlay" id="modalEta">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalEtaTitulo">Nova ETA</span>
            <button type="button" class="modal-close" onclick="fecharModal('modalEta')">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="etaId">
            <div class="form-group-modal">
                <label>Sistema de Água <span class="required">*</span></label>
                <select class="form-control choices-select" id="etaSistema">
                    <option value="">Selecione...</option>
                    <?php foreach ($sistemasAgua as $s): ?>
                        <option value="<?= $s['CD_CHAVE'] ?>"><?= htmlspecialchars($s['DS_NOME']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group-modal">
                <label>Nome <span class="required">*</span></label>
                <input type="text" class="form-control" id="etaNome" maxlength="200">
            </div>
            <div class="form-group-modal">
                <label>Descrição</label>
                <textarea class="form-control" id="etaDescricao" rows="3"></textarea>
            </div>
            <div class="form-group-modal">
                <label>Fórmula Volume Distribuído</label>
                <select class="form-control choices-select" id="etaFormula">
                    <option value="">Selecione...</option>
                    <?php foreach ($formulas as $f): ?>
                        <option value="<?= $f['CD_CHAVE'] ?>"><?= htmlspecialchars($f['DS_NOME']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group-modal">
                <label>Código Entidade Valor ID</label>
                <input type="number" class="form-control" id="etaEntidadeValorId">
            </div>
            <div class="form-group-modal">
                <label>Meta por Dia (m³)</label>
                <input type="number" step="0.01" class="form-control" id="etaMetaDia">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fecharModal('modalEta')">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="salvarEta()">
                <ion-icon name="checkmark-outline"></ion-icon>
                Salvar
            </button>
        </div>
    </div>
</div>

<script>
    // ============================================
    // Configurações Globais
    // ============================================
    const podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;
    const tiposCalculo = <?= json_encode($tiposCalculo) ?>;
    const porPagina = 20;
    let debounceTimers = {};

    // Estado de paginação para cada aba
    let estadoPaginacao = {
        tipoMedidor: {
            pagina: 1,
            total: 0,
            totalPaginas: 0
        },
        tipoReservatorio: {
            pagina: 1,
            total: 0,
            totalPaginas: 0
        },
        areaInfluencia: {
            pagina: 1,
            total: 0,
            totalPaginas: 0
        },
        unidade: {
            pagina: 1,
            total: 0,
            totalPaginas: 0
        },
        localidade: {
            pagina: 1,
            total: 0,
            totalPaginas: 0
        },
        sistemaAgua: {
            pagina: 1,
            total: 0,
            totalPaginas: 0
        },
        instrumentos: {
            pagina: 1,
            total: 0,
            totalPaginas: 0
        },
        eta: {
            pagina: 1,
            total: 0,
            totalPaginas: 0
        }
    };

    // ============================================
    // Navegação de Abas
    // ============================================
    function ativarAba(nomeAba) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

        const btn = document.querySelector(`.tab-btn[data-tab="${nomeAba}"]`);
        if (btn) {
            btn.classList.add('active');
            document.getElementById('tab-' + nomeAba).classList.add('active');
        }
    }

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const tab = this.dataset.tab;
            ativarAba(tab);

            // Atualizar URL sem recarregar a página
            const url = new URL(window.location);
            url.searchParams.set('aba', tab);
            history.replaceState(null, '', url);
        });
    });

    // ============================================
    // Toast
    // ============================================
    function showToast(message, type = 'sucesso') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <span class="toast-icon">
                <ion-icon name="${type === 'sucesso' ? 'checkmark' : 'alert'}-outline"></ion-icon>
            </span>
            <span class="toast-message">${message}</span>
        `;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    }

    // ============================================
    // Modal Functions
    // ============================================
    function fecharModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }

    function abrirModal(modalId) {
        document.getElementById(modalId).classList.add('active');
    }

    // Fechar modal ao clicar fora
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function (e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });

    // ============================================
    // Limpar Filtro
    // ============================================
    function limparFiltro(inputId, callback) {
        document.getElementById(inputId).value = '';
        document.querySelector(`#${inputId}`).parentElement.querySelector('.btn-search-clear').classList.remove('visible');
        callback(1);
    }

    // Toggle botão limpar
    document.querySelectorAll('.input-search').forEach(input => {
        input.addEventListener('input', function () {
            const clearBtn = this.parentElement.querySelector('.btn-search-clear');
            if (this.value.length > 0) {
                clearBtn.classList.add('visible');
            } else {
                clearBtn.classList.remove('visible');
            }
        });
    });

    // ============================================
    // Debounce para busca
    // ============================================
    function debounce(func, wait, timerKey) {
        return function executedFunction(...args) {
            clearTimeout(debounceTimers[timerKey]);
            debounceTimers[timerKey] = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // ============================================
    // Renderizar Paginação
    // ============================================
    function renderizarPaginacao(containerId, estado, callbackBusca) {
        const container = document.getElementById(containerId);
        if (estado.totalPaginas <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '<div class="pagination">';

        // Anterior
        html += `<button class="btn-page ${estado.pagina === 1 ? 'disabled' : ''}" 
                  onclick="${estado.pagina > 1 ? callbackBusca + '(' + (estado.pagina - 1) + ')' : ''}"
                  ${estado.pagina === 1 ? 'disabled' : ''}>
                    <ion-icon name="chevron-back-outline"></ion-icon>
                 </button>`;

        // Páginas
        let startPage = Math.max(1, estado.pagina - 2);
        let endPage = Math.min(estado.totalPaginas, estado.pagina + 2);

        if (startPage > 1) {
            html += `<button class="btn-page" onclick="${callbackBusca}(1)">1</button>`;
            if (startPage > 2) html += '<span class="page-ellipsis">...</span>';
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="btn-page ${i === estado.pagina ? 'active' : ''}" 
                      onclick="${callbackBusca}(${i})">${i}</button>`;
        }

        if (endPage < estado.totalPaginas) {
            if (endPage < estado.totalPaginas - 1) html += '<span class="page-ellipsis">...</span>';
            html += `<button class="btn-page" onclick="${callbackBusca}(${estado.totalPaginas})">${estado.totalPaginas}</button>`;
        }

        // Próximo
        html += `<button class="btn-page ${estado.pagina === estado.totalPaginas ? 'disabled' : ''}" 
                  onclick="${estado.pagina < estado.totalPaginas ? callbackBusca + '(' + (estado.pagina + 1) + ')' : ''}"
                  ${estado.pagina === estado.totalPaginas ? 'disabled' : ''}>
                    <ion-icon name="chevron-forward-outline"></ion-icon>
                 </button>`;

        html += '</div>';
        html += `<div class="page-info">Página ${estado.pagina} de ${estado.totalPaginas} (${estado.total} registros)</div>`;

        container.innerHTML = html;
    }

    // ============================================
    // TIPO MEDIDOR
    // ============================================
    function buscarTiposMedidor(pagina = 1) {
        const filtro = document.getElementById('filtroTipoMedidor').value;
        const loading = document.getElementById('loadingTipoMedidor');
        const tbody = document.getElementById('tabelaTipoMedidor');

        loading.classList.add('active');

        $.get('bd/cadastrosAuxiliares/getTiposMedidor.php', {
            busca: filtro,
            pagina: pagina,
            porPagina: porPagina
        }, function (response) {
            loading.classList.remove('active');

            if (response.success) {
                estadoPaginacao.tipoMedidor = {
                    pagina: response.pagina,
                    total: response.total,
                    totalPaginas: response.totalPaginas
                };

                document.getElementById('countTipoMedidor').innerHTML =
                    `<strong>${response.total}</strong> registro(s) encontrado(s)`;

                let html = '';
                if (response.data.length > 0) {
                    response.data.forEach(item => {
                        html += `
                            <tr>
                                <td class="code">${item.CD_CHAVE}</td>
                                <td class="name">${item.DS_NOME || ''}</td>
                                <td><span class="badge badge-info">${tiposCalculo[item.ID_TIPO_CALCULO] || '-'}</span></td>
                                <td>
                                    <div class="table-actions">
                                        ${podeEditar ? `
                                        <button type="button" class="btn-action" onclick="editarTipoMedidor(${item.CD_CHAVE})" title="Editar">
                                            <ion-icon name="create-outline"></ion-icon>
                                        </button>
                                        <button type="button" class="btn-action delete" onclick="excluirTipoMedidor(${item.CD_CHAVE})" title="Excluir">
                                            <ion-icon name="trash-outline"></ion-icon>
                                        </button>
                                        ` : ''}
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    html = `<tr><td colspan="4">
                        <div class="empty-state">
                            <div class="empty-state-icon"><ion-icon name="folder-open-outline"></ion-icon></div>
                            <h3>Nenhum registro encontrado</h3>
                            <p>Tente ajustar os filtros de busca</p>
                        </div>
                    </td></tr>`;
                }

                tbody.innerHTML = html;
                renderizarPaginacao('paginacaoTipoMedidor', estadoPaginacao.tipoMedidor, 'buscarTiposMedidor');
            }
        }, 'json').fail(function () {
            loading.classList.remove('active');
            showToast('Erro ao buscar tipos de medidor', 'erro');
        });
    }

    function abrirModalTipoMedidor() {
        document.getElementById('tipoMedidorId').value = '';
        document.getElementById('tipoMedidorNome').value = '';
        clearChoices('tipoMedidorCalculo');
        document.getElementById('modalTipoMedidorTitulo').textContent = 'Novo Tipo de Medidor';
        abrirModal('modalTipoMedidor');
    }

    function editarTipoMedidor(id) {
        $.get('bd/cadastrosAuxiliares/getTipoMedidor.php', {
            id: id
        }, function (response) {
            if (response.success && response.data) {
                document.getElementById('tipoMedidorId').value = response.data.CD_CHAVE;
                document.getElementById('tipoMedidorNome').value = response.data.DS_NOME || '';
                setChoicesValue('tipoMedidorCalculo', response.data.ID_TIPO_CALCULO || '');
                document.getElementById('modalTipoMedidorTitulo').textContent = 'Editar Tipo de Medidor';
                abrirModal('modalTipoMedidor');
            } else {
                showToast('Erro ao carregar dados', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function salvarTipoMedidor() {
        const dados = {
            id: document.getElementById('tipoMedidorId').value,
            nome: document.getElementById('tipoMedidorNome').value,
            tipo_calculo: document.getElementById('tipoMedidorCalculo').value
        };

        if (!dados.nome || !dados.tipo_calculo) {
            showToast('Preencha todos os campos obrigatórios', 'erro');
            return;
        }

        $.post('bd/cadastrosAuxiliares/salvarTipoMedidor.php', dados, function (response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                fecharModal('modalTipoMedidor');
                buscarTiposMedidor(estadoPaginacao.tipoMedidor.pagina);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function excluirTipoMedidor(id) {
        if (!confirm('Deseja realmente excluir este registro?')) return;

        $.post('bd/cadastrosAuxiliares/excluirTipoMedidor.php', {
            id: id
        }, function (response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                buscarTiposMedidor(estadoPaginacao.tipoMedidor.pagina);
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // TIPO RESERVATÓRIO
    // ============================================
    function buscarTiposReservatorio(pagina = 1) {
        const filtro = document.getElementById('filtroTipoReservatorio').value;
        const loading = document.getElementById('loadingTipoReservatorio');
        const tbody = document.getElementById('tabelaTipoReservatorio');

        loading.classList.add('active');

        $.get('bd/cadastrosAuxiliares/getTiposReservatorio.php', {
            busca: filtro,
            pagina: pagina,
            porPagina: porPagina
        }, function (response) {
            loading.classList.remove('active');

            if (response.success) {
                estadoPaginacao.tipoReservatorio = {
                    pagina: response.pagina,
                    total: response.total,
                    totalPaginas: response.totalPaginas
                };

                document.getElementById('countTipoReservatorio').innerHTML =
                    `<strong>${response.total}</strong> registro(s) encontrado(s)`;

                let html = '';
                if (response.data.length > 0) {
                    response.data.forEach(item => {
                        html += `
                            <tr>
                                <td class="code">${item.CD_CHAVE}</td>
                                <td class="name">${item.NOME || ''}</td>
                                <td>
                                    <div class="table-actions">
                                        ${podeEditar ? `
                                        <button type="button" class="btn-action" onclick="editarTipoReservatorio(${item.CD_CHAVE})" title="Editar">
                                            <ion-icon name="create-outline"></ion-icon>
                                        </button>
                                        <button type="button" class="btn-action delete" onclick="excluirTipoReservatorio(${item.CD_CHAVE})" title="Excluir">
                                            <ion-icon name="trash-outline"></ion-icon>
                                        </button>
                                        ` : ''}
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    html = `<tr><td colspan="3">
                        <div class="empty-state">
                            <div class="empty-state-icon"><ion-icon name="folder-open-outline"></ion-icon></div>
                            <h3>Nenhum registro encontrado</h3>
                            <p>Tente ajustar os filtros de busca</p>
                        </div>
                    </td></tr>`;
                }

                tbody.innerHTML = html;
                renderizarPaginacao('paginacaoTipoReservatorio', estadoPaginacao.tipoReservatorio, 'buscarTiposReservatorio');
            }
        }, 'json').fail(function () {
            loading.classList.remove('active');
            showToast('Erro ao buscar tipos de reservatório', 'erro');
        });
    }

    function abrirModalTipoReservatorio() {
        document.getElementById('tipoReservatorioId').value = '';
        document.getElementById('tipoReservatorioNome').value = '';
        document.getElementById('modalTipoReservatorioTitulo').textContent = 'Novo Tipo de Reservatório';
        abrirModal('modalTipoReservatorio');
    }

    function editarTipoReservatorio(id) {
        $.get('bd/cadastrosAuxiliares/getTipoReservatorio.php', {
            id: id
        }, function (response) {
            if (response.success && response.data) {
                document.getElementById('tipoReservatorioId').value = response.data.CD_CHAVE;
                document.getElementById('tipoReservatorioNome').value = response.data.NOME || '';
                document.getElementById('modalTipoReservatorioTitulo').textContent = 'Editar Tipo de Reservatório';
                abrirModal('modalTipoReservatorio');
            } else {
                showToast('Erro ao carregar dados', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function salvarTipoReservatorio() {
        const dados = {
            id: document.getElementById('tipoReservatorioId').value,
            nome: document.getElementById('tipoReservatorioNome').value
        };

        if (!dados.nome) {
            showToast('Preencha o nome', 'erro');
            return;
        }

        $.post('bd/cadastrosAuxiliares/salvarTipoReservatorio.php', dados, function (response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                fecharModal('modalTipoReservatorio');
                buscarTiposReservatorio(estadoPaginacao.tipoReservatorio.pagina);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function excluirTipoReservatorio(id) {
        if (!confirm('Deseja realmente excluir este registro?')) return;

        $.post('bd/cadastrosAuxiliares/excluirTipoReservatorio.php', {
            id: id
        }, function (response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                buscarTiposReservatorio(estadoPaginacao.tipoReservatorio.pagina);
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // ÁREA DE INFLUÊNCIA
    // ============================================
    function buscarAreasInfluencia(pagina = 1) {
        const filtro = document.getElementById('filtroAreaInfluencia').value;
        const loading = document.getElementById('loadingAreaInfluencia');
        const tbody = document.getElementById('tabelaAreaInfluencia');

        loading.classList.add('active');

        $.get('bd/cadastrosAuxiliares/getAreasInfluencia.php', {
            busca: filtro,
            pagina: pagina,
            porPagina: porPagina
        }, function (response) {
            loading.classList.remove('active');

            if (response.success) {
                estadoPaginacao.areaInfluencia = {
                    pagina: response.pagina,
                    total: response.total,
                    totalPaginas: response.totalPaginas
                };

                document.getElementById('countAreaInfluencia').innerHTML =
                    `<strong>${response.total}</strong> registro(s) encontrado(s)`;

                let html = '';
                if (response.data.length > 0) {
                    response.data.forEach(item => {
                        html += `
                            <tr>
                                <td class="code">${item.CD_AREA_INFLUENCIA}</td>
                                <td class="name">${item.DS_MUNICIPIO || ''}</td>
                                <td>${item.VL_TAXA_OCUPACAO || '-'}</td>
                                <td>${item.VL_DENSIDADE_DEMOGRAFICA || '-'}</td>
                                <td><span class="badge badge-success">${item.QTD_BAIRROS || 0}</span></td>
                                <td>
                                    <div class="table-actions">
                                        ${podeEditar ? `
                                        <button type="button" class="btn-action" onclick="editarAreaInfluencia(${item.CD_AREA_INFLUENCIA})" title="Editar">
                                            <ion-icon name="create-outline"></ion-icon>
                                        </button>
                                        <button type="button" class="btn-action delete" onclick="excluirAreaInfluencia(${item.CD_AREA_INFLUENCIA})" title="Excluir">
                                            <ion-icon name="trash-outline"></ion-icon>
                                        </button>
                                        ` : ''}
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    html = `<tr><td colspan="6">
                        <div class="empty-state">
                            <div class="empty-state-icon"><ion-icon name="folder-open-outline"></ion-icon></div>
                            <h3>Nenhum registro encontrado</h3>
                            <p>Tente ajustar os filtros de busca</p>
                        </div>
                    </td></tr>`;
                }

                tbody.innerHTML = html;
                renderizarPaginacao('paginacaoAreaInfluencia', estadoPaginacao.areaInfluencia, 'buscarAreasInfluencia');
            }
        }, 'json').fail(function () {
            loading.classList.remove('active');
            showToast('Erro ao buscar áreas de influência', 'erro');
        });
    }

    function abrirModalAreaInfluencia() {
        document.getElementById('areaInfluenciaId').value = '';
        document.getElementById('areaInfluenciaMunicipio').value = '';
        document.getElementById('areaInfluenciaTaxa').value = '';
        document.getElementById('areaInfluenciaDensidade').value = '';
        document.getElementById('modalAreaInfluenciaTitulo').textContent = 'Nova Área de Influência';
        abrirModal('modalAreaInfluencia');
    }

    function editarAreaInfluencia(id) {
        $.get('bd/cadastrosAuxiliares/getAreaInfluencia.php', {
            id: id
        }, function (response) {
            if (response.success && response.data) {
                document.getElementById('areaInfluenciaId').value = response.data.CD_AREA_INFLUENCIA;
                document.getElementById('areaInfluenciaMunicipio').value = response.data.DS_MUNICIPIO || '';
                document.getElementById('areaInfluenciaTaxa').value = response.data.VL_TAXA_OCUPACAO || '';
                document.getElementById('areaInfluenciaDensidade').value = response.data.VL_DENSIDADE_DEMOGRAFICA || '';
                document.getElementById('modalAreaInfluenciaTitulo').textContent = 'Editar Área de Influência';
                abrirModal('modalAreaInfluencia');
            } else {
                showToast('Erro ao carregar dados', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function salvarAreaInfluencia() {
        const dados = {
            id: document.getElementById('areaInfluenciaId').value,
            municipio: document.getElementById('areaInfluenciaMunicipio').value,
            taxa_ocupacao: document.getElementById('areaInfluenciaTaxa').value,
            densidade: document.getElementById('areaInfluenciaDensidade').value
        };

        if (!dados.municipio) {
            showToast('Preencha o município', 'erro');
            return;
        }

        $.post('bd/cadastrosAuxiliares/salvarAreaInfluencia.php', dados, function (response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                fecharModal('modalAreaInfluencia');
                buscarAreasInfluencia(estadoPaginacao.areaInfluencia.pagina);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function excluirAreaInfluencia(id) {
        if (!confirm('Deseja realmente excluir este registro e seus bairros vinculados?')) return;

        $.post('bd/cadastrosAuxiliares/excluirAreaInfluencia.php', {
            id: id
        }, function (response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                buscarAreasInfluencia(estadoPaginacao.areaInfluencia.pagina);
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // UNIDADE
    // ============================================
    function buscarUnidades(pagina = 1) {
        const filtro = document.getElementById('filtroUnidade').value;
        const loading = document.getElementById('loadingUnidade');
        const tbody = document.getElementById('tabelaUnidade');

        loading.classList.add('active');

        $.get('bd/cadastrosAuxiliares/getUnidades.php', {
            busca: filtro,
            pagina: pagina,
            porPagina: porPagina
        }, function (response) {
            loading.classList.remove('active');

            if (response.success) {
                estadoPaginacao.unidade = {
                    pagina: response.pagina,
                    total: response.total,
                    totalPaginas: response.totalPaginas
                };

                document.getElementById('countUnidade').innerHTML =
                    `<strong>${response.total}</strong> registro(s) encontrado(s)`;

                let html = '';
                if (response.data.length > 0) {
                    response.data.forEach(item => {
                        html += `
                            <tr>
                                <td class="code">${item.CD_UNIDADE}</td>
                                <td><span class="badge badge-info">${item.CD_CODIGO || ''}</span></td>
                                <td class="name">${item.DS_NOME || ''}</td>
                                <td>
                                    <div class="table-actions">
                                        ${podeEditar ? `
                                        <button type="button" class="btn-action" onclick="editarUnidade(${item.CD_UNIDADE})" title="Editar">
                                            <ion-icon name="create-outline"></ion-icon>
                                        </button>
                                        <button type="button" class="btn-action delete" onclick="excluirUnidade(${item.CD_UNIDADE})" title="Excluir">
                                            <ion-icon name="trash-outline"></ion-icon>
                                        </button>
                                        ` : ''}
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    html = `<tr><td colspan="4">
                        <div class="empty-state">
                            <div class="empty-state-icon"><ion-icon name="folder-open-outline"></ion-icon></div>
                            <h3>Nenhum registro encontrado</h3>
                            <p>Tente ajustar os filtros de busca</p>
                        </div>
                    </td></tr>`;
                }

                tbody.innerHTML = html;
                renderizarPaginacao('paginacaoUnidade', estadoPaginacao.unidade, 'buscarUnidades');
            }
        }, 'json').fail(function () {
            loading.classList.remove('active');
            showToast('Erro ao buscar unidades', 'erro');
        });
    }

    function abrirModalUnidade() {
        document.getElementById('unidadeId').value = '';
        document.getElementById('unidadeCodigo').value = '';
        document.getElementById('unidadeNome').value = '';
        document.getElementById('modalUnidadeTitulo').textContent = 'Nova Unidade';
        abrirModal('modalUnidade');
    }

    function editarUnidade(id) {
        $.get('bd/cadastrosAuxiliares/getUnidade.php', {
            id: id
        }, function (response) {
            if (response.success && response.data) {
                document.getElementById('unidadeId').value = response.data.CD_UNIDADE;
                document.getElementById('unidadeCodigo').value = response.data.CD_CODIGO || '';
                document.getElementById('unidadeNome').value = response.data.DS_NOME || '';
                document.getElementById('modalUnidadeTitulo').textContent = 'Editar Unidade';
                abrirModal('modalUnidade');
            } else {
                showToast('Erro ao carregar dados', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function salvarUnidade() {
        const dados = {
            id: document.getElementById('unidadeId').value,
            codigo: document.getElementById('unidadeCodigo').value,
            nome: document.getElementById('unidadeNome').value
        };

        if (!dados.codigo || !dados.nome) {
            showToast('Preencha todos os campos obrigatórios', 'erro');
            return;
        }

        $.post('bd/cadastrosAuxiliares/salvarUnidade.php', dados, function (response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                fecharModal('modalUnidade');
                buscarUnidades(estadoPaginacao.unidade.pagina);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function excluirUnidade(id) {
        if (!confirm('Deseja realmente excluir este registro?')) return;

        $.post('bd/cadastrosAuxiliares/excluirUnidade.php', {
            id: id
        }, function (response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                buscarUnidades(estadoPaginacao.unidade.pagina);
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // LOCALIDADE
    // ============================================
    function buscarLocalidades(pagina = 1) {
        const filtro = document.getElementById('filtroLocalidade').value;
        const unidade = document.getElementById('filtroLocalidadeUnidade').value;
        const loading = document.getElementById('loadingLocalidade');
        const tbody = document.getElementById('tabelaLocalidade');

        loading.classList.add('active');

        $.get('bd/cadastrosAuxiliares/getLocalidades.php', {
            busca: filtro,
            cd_unidade: unidade,
            pagina: pagina,
            porPagina: porPagina
        }, function (response) {
            loading.classList.remove('active');

            if (response.success) {
                estadoPaginacao.localidade = {
                    pagina: response.pagina,
                    total: response.total,
                    totalPaginas: response.totalPaginas
                };

                document.getElementById('countLocalidade').innerHTML =
                    `<strong>${response.total}</strong> registro(s) encontrado(s)`;

                let html = '';
                if (response.data.length > 0) {
                    response.data.forEach(item => {
                        html += `
                            <tr>
                                <td class="code">${item.CD_CHAVE}</td>
                                <td>${item.CD_LOCALIDADE || ''}</td>
                                <td class="name truncate">${item.DS_NOME || ''}</td>
                                <td class="truncate">${item.DS_UNIDADE || '-'}</td>
                                <td>
                                    <div class="table-actions">
                                        ${podeEditar ? `
                                        <button type="button" class="btn-action" onclick="editarLocalidade(${item.CD_CHAVE})" title="Editar">
                                            <ion-icon name="create-outline"></ion-icon>
                                        </button>
                                        <button type="button" class="btn-action delete" onclick="excluirLocalidade(${item.CD_CHAVE})" title="Excluir">
                                            <ion-icon name="trash-outline"></ion-icon>
                                        </button>
                                        ` : ''}
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    html = `<tr><td colspan="5">
                        <div class="empty-state">
                            <div class="empty-state-icon"><ion-icon name="folder-open-outline"></ion-icon></div>
                            <h3>Nenhum registro encontrado</h3>
                            <p>Tente ajustar os filtros de busca</p>
                        </div>
                    </td></tr>`;
                }

                tbody.innerHTML = html;
                renderizarPaginacao('paginacaoLocalidade', estadoPaginacao.localidade, 'buscarLocalidades');
            }
        }, 'json').fail(function () {
            loading.classList.remove('active');
            showToast('Erro ao buscar localidades', 'erro');
        });
    }

    function abrirModalLocalidade() {
        document.getElementById('localidadeId').value = '';
        clearChoices('localidadeUnidade');
        document.getElementById('localidadeCodigo').value = '';
        document.getElementById('localidadeNome').value = '';
        document.getElementById('modalLocalidadeTitulo').textContent = 'Nova Localidade';
        abrirModal('modalLocalidade');
    }

    function editarLocalidade(id) {
        $.get('bd/cadastrosAuxiliares/getLocalidade.php', {
            id: id
        }, function (response) {
            if (response.success && response.data) {
                document.getElementById('localidadeId').value = response.data.CD_CHAVE;
                setChoicesValue('localidadeUnidade', response.data.CD_UNIDADE || '');
                document.getElementById('localidadeCodigo').value = response.data.CD_LOCALIDADE || '';
                document.getElementById('localidadeNome').value = response.data.DS_NOME || '';
                document.getElementById('modalLocalidadeTitulo').textContent = 'Editar Localidade';
                abrirModal('modalLocalidade');
            } else {
                showToast('Erro ao carregar dados', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function salvarLocalidade() {
        const dados = {
            id: document.getElementById('localidadeId').value,
            cd_unidade: document.getElementById('localidadeUnidade').value,
            cd_localidade: document.getElementById('localidadeCodigo').value,
            nome: document.getElementById('localidadeNome').value
        };

        if (!dados.cd_unidade || !dados.cd_localidade || !dados.nome) {
            showToast('Preencha todos os campos obrigatórios', 'erro');
            return;
        }

        $.post('bd/cadastrosAuxiliares/salvarLocalidade.php', dados, function (response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                fecharModal('modalLocalidade');
                buscarLocalidades(estadoPaginacao.localidade.pagina);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function excluirLocalidade(id) {
        if (!confirm('Deseja realmente excluir este registro?')) return;

        $.post('bd/cadastrosAuxiliares/excluirLocalidade.php', {
            id: id
        }, function (response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                buscarLocalidades(estadoPaginacao.localidade.pagina);
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // SISTEMA DE ÁGUA
    // ============================================
    function buscarSistemasAgua(pagina = 1) {
        const filtro = document.getElementById('filtroSistemaAgua').value;
        const loading = document.getElementById('loadingSistemaAgua');
        const tbody = document.getElementById('tabelaSistemaAgua');

        loading.classList.add('active');

        $.get('bd/cadastrosAuxiliares/getSistemasAgua.php', {
            busca: filtro,
            pagina: pagina,
            porPagina: porPagina
        }, function (response) {
            loading.classList.remove('active');

            if (response.success) {
                estadoPaginacao.sistemaAgua = {
                    pagina: response.pagina,
                    total: response.total,
                    totalPaginas: response.totalPaginas
                };

                document.getElementById('countSistemaAgua').innerHTML =
                    `<strong>${response.total}</strong> registro(s) encontrado(s)`;

                let html = '';
                if (response.data.length > 0) {
                    response.data.forEach(item => {
                        const dataAtualizacao = item.DT_ULTIMA_ATUALIZACAO ?
                            new Date(item.DT_ULTIMA_ATUALIZACAO).toLocaleDateString('pt-BR') : '-';

                        html += `
                            <tr>
                                <td class="code">${item.CD_CHAVE}</td>
                                <td class="truncate">${item.DS_LOCALIDADE_FORMATADA || '-'}</td>
                                <td class="name truncate">${item.DS_NOME || ''}</td>
                                <td class="truncate">${item.DS_DESCRICAO || '-'}</td>
                                <td>${dataAtualizacao}</td>
                                <td>
                                    <div class="table-actions">
                                        ${podeEditar ? `
                                        <button type="button" class="btn-action" onclick="editarSistemaAgua(${item.CD_CHAVE})" title="Editar">
                                            <ion-icon name="create-outline"></ion-icon>
                                        </button>
                                        <button type="button" class="btn-action delete" onclick="excluirSistemaAgua(${item.CD_CHAVE})" title="Excluir">
                                            <ion-icon name="trash-outline"></ion-icon>
                                        </button>
                                        ` : ''}
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    html = `<tr><td colspan="6">
                        <div class="empty-state">
                            <div class="empty-state-icon"><ion-icon name="folder-open-outline"></ion-icon></div>
                            <h3>Nenhum registro encontrado</h3>
                            <p>Tente ajustar os filtros de busca</p>
                        </div>
                    </td></tr>`;
                }

                tbody.innerHTML = html;
                renderizarPaginacao('paginacaoSistemaAgua', estadoPaginacao.sistemaAgua, 'buscarSistemasAgua');
            }
        }, 'json').fail(function () {
            loading.classList.remove('active');
            showToast('Erro ao buscar sistemas de água', 'erro');
        });
    }

    function abrirModalSistemaAgua() {
        document.getElementById('sistemaAguaId').value = '';
        clearChoices('sistemaAguaLocalidade');
        document.getElementById('sistemaAguaNome').value = '';
        document.getElementById('sistemaAguaDescricao').value = '';
        document.getElementById('modalSistemaAguaTitulo').textContent = 'Novo Sistema de Água';
        abrirModal('modalSistemaAgua');
    }

    function editarSistemaAgua(id) {
        $.get('bd/cadastrosAuxiliares/getSistemaAgua.php', {
            id: id
        }, function (response) {
            if (response.success && response.data) {
                document.getElementById('sistemaAguaId').value = response.data.CD_CHAVE;
                setChoicesValue('sistemaAguaLocalidade', response.data.CD_LOCALIDADE || '');
                document.getElementById('sistemaAguaNome').value = response.data.DS_NOME || '';
                document.getElementById('sistemaAguaDescricao').value = response.data.DS_DESCRICAO || '';
                document.getElementById('modalSistemaAguaTitulo').textContent = 'Editar Sistema de Água';
                abrirModal('modalSistemaAgua');
            } else {
                showToast('Erro ao carregar dados', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function salvarSistemaAgua() {
        const dados = {
            id: document.getElementById('sistemaAguaId').value,
            cd_localidade: document.getElementById('sistemaAguaLocalidade').value,
            nome: document.getElementById('sistemaAguaNome').value,
            descricao: document.getElementById('sistemaAguaDescricao').value
        };

        if (!dados.cd_localidade || !dados.nome) {
            showToast('Preencha todos os campos obrigatórios', 'erro');
            return;
        }

        $.post('bd/cadastrosAuxiliares/salvarSistemaAgua.php', dados, function (response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                fecharModal('modalSistemaAgua');
                buscarSistemasAgua(estadoPaginacao.sistemaAgua.pagina);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function excluirSistemaAgua(id) {
        if (!confirm('Deseja realmente excluir este registro?')) return;

        $.post('bd/cadastrosAuxiliares/excluirSistemaAgua.php', {
            id: id
        }, function (response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                buscarSistemasAgua(estadoPaginacao.sistemaAgua.pagina);
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // ETA
    // ============================================
    function buscarEtas(pagina = 1) {
        const filtro = document.getElementById('filtroEta').value;
        const sistema = document.getElementById('filtroEtaSistema').value;
        const loading = document.getElementById('loadingEta');
        const tbody = document.getElementById('tabelaEta');

        loading.classList.add('active');

        $.get('bd/cadastrosAuxiliares/getEtas.php', {
            busca: filtro,
            cd_sistema: sistema,
            pagina: pagina,
            porPagina: porPagina
        }, function (response) {
            loading.classList.remove('active');

            if (response.success) {
                estadoPaginacao.eta = {
                    pagina: response.pagina,
                    total: response.total,
                    totalPaginas: response.totalPaginas
                };

                document.getElementById('countEta').innerHTML =
                    `<strong>${response.total}</strong> registro(s) encontrado(s)`;

                let html = '';
                if (response.data.length > 0) {
                    response.data.forEach(item => {
                        const dataAtualizacao = item.DT_ULTIMA_ATUALIZACAO ?
                            new Date(item.DT_ULTIMA_ATUALIZACAO).toLocaleDateString('pt-BR') : '-';

                        html += `
                            <tr>
                                <td class="code">${item.CD_CHAVE}</td>
                                <td class="name truncate">${item.DS_NOME || ''}</td>
                                <td class="truncate">${item.DS_SISTEMA_AGUA || '-'}</td>
                                <td>${item.VL_META_DIA ? parseFloat(item.VL_META_DIA).toLocaleString('pt-BR') : '-'}</td>
                                <td>${dataAtualizacao}</td>
                                <td>
                                    <div class="table-actions">
                                        ${podeEditar ? `
                                        <button type="button" class="btn-action" onclick="editarEta(${item.CD_CHAVE})" title="Editar">
                                            <ion-icon name="create-outline"></ion-icon>
                                        </button>
                                        <button type="button" class="btn-action delete" onclick="excluirEta(${item.CD_CHAVE})" title="Excluir">
                                            <ion-icon name="trash-outline"></ion-icon>
                                        </button>
                                        ` : ''}
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    html = `<tr><td colspan="6">
                        <div class="empty-state">
                            <div class="empty-state-icon"><ion-icon name="folder-open-outline"></ion-icon></div>
                            <h3>Nenhum registro encontrado</h3>
                            <p>Tente ajustar os filtros de busca</p>
                        </div>
                    </td></tr>`;
                }

                tbody.innerHTML = html;
                renderizarPaginacao('paginacaoEta', estadoPaginacao.eta, 'buscarEtas');
            }
        }, 'json').fail(function () {
            loading.classList.remove('active');
            showToast('Erro ao buscar ETAs', 'erro');
        });
    }

    function abrirModalEta() {
        document.getElementById('etaId').value = '';
        clearChoices('etaSistema');
        document.getElementById('etaNome').value = '';
        document.getElementById('etaDescricao').value = '';
        clearChoices('etaFormula');
        document.getElementById('etaEntidadeValorId').value = '';
        document.getElementById('etaMetaDia').value = '';
        document.getElementById('modalEtaTitulo').textContent = 'Nova ETA';
        abrirModal('modalEta');
    }

    function editarEta(id) {
        $.get('bd/cadastrosAuxiliares/getEta.php', {
            id: id
        }, function (response) {
            if (response.success && response.data) {
                document.getElementById('etaId').value = response.data.CD_CHAVE;
                setChoicesValue('etaSistema', response.data.CD_SISTEMA_AGUA || '');
                document.getElementById('etaNome').value = response.data.DS_NOME || '';
                document.getElementById('etaDescricao').value = response.data.DS_DESCRICAO || '';
                setChoicesValue('etaFormula', response.data.CD_FORMULA_VOLUME_DISTRIBUIDO || '');
                document.getElementById('etaEntidadeValorId').value = response.data.CD_ENTIDADE_VALOR_ID || '';
                document.getElementById('etaMetaDia').value = response.data.VL_META_DIA || '';
                document.getElementById('modalEtaTitulo').textContent = 'Editar ETA';
                abrirModal('modalEta');
            } else {
                showToast('Erro ao carregar dados', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function salvarEta() {
        const dados = {
            id: document.getElementById('etaId').value,
            cd_sistema: document.getElementById('etaSistema').value,
            nome: document.getElementById('etaNome').value,
            descricao: document.getElementById('etaDescricao').value,
            cd_formula: document.getElementById('etaFormula').value,
            cd_entidade_valor_id: document.getElementById('etaEntidadeValorId').value,
            meta_dia: document.getElementById('etaMetaDia').value
        };

        if (!dados.cd_sistema || !dados.nome) {
            showToast('Preencha todos os campos obrigatórios', 'erro');
            return;
        }

        $.post('bd/cadastrosAuxiliares/salvarEta.php', dados, function (response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                fecharModal('modalEta');
                buscarEtas(estadoPaginacao.eta.pagina);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function excluirEta(id) {
        if (!confirm('Deseja realmente excluir este registro?')) return;

        $.post('bd/cadastrosAuxiliares/excluirEta.php', {
            id: id
        }, function (response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                buscarEtas(estadoPaginacao.eta.pagina);
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // Event Listeners - Busca ao digitar (debounce)
    // ============================================
    document.getElementById('filtroTipoMedidor').addEventListener('input',
        debounce(function () {
            buscarTiposMedidor(1);
        }, 400, 'tipoMedidor'));

    document.getElementById('filtroTipoReservatorio').addEventListener('input',
        debounce(function () {
            buscarTiposReservatorio(1);
        }, 400, 'tipoReservatorio'));

    document.getElementById('filtroAreaInfluencia').addEventListener('input',
        debounce(function () {
            buscarAreasInfluencia(1);
        }, 400, 'areaInfluencia'));

    document.getElementById('filtroUnidade').addEventListener('input',
        debounce(function () {
            buscarUnidades(1);
        }, 400, 'unidade'));

    document.getElementById('filtroLocalidade').addEventListener('input',
        debounce(function () {
            buscarLocalidades(1);
        }, 400, 'localidade'));
    document.getElementById('filtroLocalidadeUnidade').addEventListener('change', function () {
        buscarLocalidades(1);
    });

    document.getElementById('filtroSistemaAgua').addEventListener('input',
        debounce(function () {
            buscarSistemasAgua(1);
        }, 400, 'sistemaAgua'));

    // Instrumentos - busca ao digitar
    document.getElementById('filtroInstrumento').addEventListener('input',
        debounce(function () {
            buscarInstrumentos(1);
        }, 400, 'instrumentos'));

    // Instrumentos - busca ao mudar tipo
    document.getElementById('filtroInstrumentoTipo').addEventListener('change', function () {
        buscarInstrumentos(1);
    });



    // Instrumentos - filtro por vínculo
    $(document).on('change', '#filtroInstrumentoVinculo', function () {
        buscarInstrumentos(1);
    });

    // Autofocus no campo de busca ao abrir o dropdown do filtro
    $('.select2-filtro-ponto').on('select2:open', function () {
        setTimeout(function () {
            var searchField = document.querySelector('.select2-container--open .select2-search__field');
            if (searchField) searchField.focus();
        }, 10);
    });



    document.getElementById('filtroEta').addEventListener('input',
        debounce(function () {
            buscarEtas(1);
        }, 400, 'eta'));
    document.getElementById('filtroEtaSistema').addEventListener('change', function () {
        buscarEtas(1);
    });

    // ============================================
    // INSTRUMENTOS - CRUD
    // ============================================

    // Estado de ordenação dos instrumentos (var para hoisting - chamado via onclick do HTML)
    var instrOrdenarPor = 'CD_CHAVE';
    var instrOrdenarDir = 'DESC';

    /**
     * Nomes dos tipos de medidor para referência no JS
     */
    const tiposMedidorInstrNomes = {
        1: 'Macromedidor',
        2: 'Estação Pitométrica',
        4: 'Medidor de Pressão',
        6: 'Nível Reservatório',
        8: 'Hidrômetro'
    };

    /**
     * Busca instrumentos com paginação e filtros.
     * Usa o endpoint listarInstrumentos.php
     * @param {int} pagina - Página a buscar
     */
    function buscarInstrumentos(pagina = 1) {
        const tipo = document.getElementById('filtroInstrumentoTipo').value;
        const filtro = document.getElementById('filtroInstrumento').value;
        const loading = document.getElementById('loadingInstrumentos');
        const tbody = document.getElementById('tabelaInstrumentos');

        loading.classList.add('active');

        // Atualiza cabeçalhos da tabela conforme o tipo
        atualizarCabecalhoTabela(parseInt(tipo));

        if (!tipo) {
            document.getElementById('thInstrCol1').textContent = 'Coluna 1';
            document.getElementById('thInstrCol2').textContent = 'Coluna 2';
            document.getElementById('thInstrCol3').textContent = 'Coluna 3';
            document.getElementById('thInstrCol4').textContent = 'Coluna 4';
            document.getElementById('thInstrTag').style.display = '';
            document.getElementById('thInstrTipo').style.display = '';
        } else {
            document.getElementById('thInstrTipo').style.display = 'none';
            document.getElementById('thInstrTag').style.display = '';
        }

        var vinculo = document.getElementById('filtroInstrumentoVinculo').value;

        $.get('bd/cadastrosAuxiliares/listarInstrumentos.php', {
            id_tipo_medidor: tipo,
            busca: filtro,
            vinculo: vinculo,
            pagina: pagina,
            por_pagina: porPagina,
            ordenar_por: instrOrdenarPor,
            ordenar_dir: instrOrdenarDir
        }, function (response) {
            loading.classList.remove('active');

            if (response.success) {
                estadoPaginacao.instrumentos = {
                    pagina: response.pagina,
                    total: response.total,
                    totalPaginas: response.totalPaginas
                };

                document.getElementById('countInstrumentos').innerHTML =
                    `<strong>${response.total}</strong> registro(s) encontrado(s)`;

                let html = '';
                if (response.data.length > 0) {
                    const tipoInt = parseInt(tipo);
                    const isTodos = response.todos || false;
                    response.data.forEach(item => {
                        // Colunas dinâmicas conforme tipo
                        var cols;
                        var itemTipo = tipoInt;
                        if (isTodos) {
                            // Modo "Todos" — usa colunas genéricas do UNION
                            cols = {
                                col1: item.COL1 || '<span style="color:#94a3b8;">-</span>',
                                col2: item.COL2 || '<span style="color:#94a3b8;">-</span>',
                                col3: item.COL3 || '<span style="color:#94a3b8;">-</span>',
                                col4: item.COL4 || '<span style="color:#94a3b8;">-</span>'
                            };
                            itemTipo = parseInt(item.ID_TIPO_MEDIDOR);
                        } else {
                            cols = obterColunasTabela(item, tipoInt);
                        }

                        // Badge de tipo (só no modo Todos)
                        var tipoCol = isTodos ? '<td><span style="font-size:11px;color:#0369a1;background:#e0f2fe;padding:3px 8px;border-radius:6px;white-space:nowrap;">' + item.DS_TIPO + '</span></td>' : '';

                        var tagValue = '';
                        if (isTodos) {
                            tagValue = item.DS_TAG && item.DS_TAG !== ''
                                ? '<span style="font-family:monospace;font-size:11px;color:#475569;">' + item.DS_TAG + '</span>'
                                : '<span style="color:#94a3b8;">-</span>';
                        } else {
                            tagValue = item.DS_TAG
                                ? '<span style="font-family:monospace;font-size:11px;color:#475569;">' + item.DS_TAG + '</span>'
                                : '<span style="color:#94a3b8;">-</span>';
                        }

                        // Badge de vínculo
                        const vinculoBadge = item.CD_PONTO_MEDICAO
                            ? `<a href="pontoMedicaoView.php?id=${item.CD_PONTO_MEDICAO}" class="link-ponto-vinculado" title="Ver ponto de medição">${item.DS_PONTO_VINCULADO || 'Ponto'} <span style="color:#64748b;">(${item.CD_PONTO_MEDICAO})</span></a>`
                            : '<span style="color: #94a3b8; font-size: 11px;">Não vinculado</span>';

                        html += `
                            <tr>
                                <td class="code">${item.CD_CHAVE}</td>
                                ${tipoCol}
                                <td>${cols.col1}</td>
                                <td>${cols.col2}</td>
                                <td>${cols.col3}</td>
                                <td>${cols.col4}</td>
                                <td>${tagValue}</td>
                                <td>${vinculoBadge}</td>
                                <td>
                                    <div class="table-actions">
                                        ${podeEditar ? `
                                        <button type="button" class="btn-action edit" onclick="editarInstrumento(${item.CD_CHAVE}, ${itemTipo})" title="Editar">
                                            <ion-icon name="create-outline"></ion-icon>
                                        </button>
                                        <button type="button" class="btn-action delete" onclick="excluirInstrumento(${item.CD_CHAVE}, ${itemTipo})" title="Excluir">
                                            <ion-icon name="trash-outline"></ion-icon>
                                        </button>
                                        ` : ''}
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    var totalCols = isTodos ? 9 : 8;
                    html = `<tr><td colspan="${totalCols}">
                        <div class="empty-state">
                            <div class="empty-state-icon"><ion-icon name="hardware-chip-outline"></ion-icon></div>
                            <h3>Nenhum instrumento encontrado</h3>
                            <p>Tente ajustar os filtros ou cadastre um novo instrumento</p>
                        </div>
                    </td></tr>`;
                }

                tbody.innerHTML = html;
                renderizarPaginacao('paginacaoInstrumentos', estadoPaginacao.instrumentos, 'buscarInstrumentos');
            } else {
                showToast(response.message || 'Erro ao buscar instrumentos', 'erro');
            }
        }, 'json').fail(function () {
            loading.classList.remove('active');
            showToast('Erro ao buscar instrumentos', 'erro');
        });
    }

    /**
     * Mapeamento de colunas genéricas (col1..col4) para colunas reais do banco
     * conforme o tipo de medidor selecionado.
     */
    function obterColunaReal(colGenerica, tipo) {
        const mapa = {
            1: { col1: 'DS_MARCA', col2: 'DS_MODELO', col3: 'DS_SERIE', col4: 'VL_DIAMETRO' },
            2: { col1: 'DS_LINHA', col2: 'DS_SISTEMA', col3: 'DS_REVESTIMENTO', col4: 'VL_DIAMETRO' },
            4: { col1: 'DS_MATRICULA_USUARIO', col2: 'DS_NUMERO_SERIE_EQUIPAMENTO', col3: 'DS_MATERIAL', col4: 'VL_DIAMETRO' },
            6: { col1: 'DS_MARCA', col2: 'DS_MODELO', col3: 'DS_SERIE', col4: 'VL_VOLUME_TOTAL' },
            8: { col1: 'DS_MATRICULA_USUARIO', col2: 'DS_NUMERO_SERIE_EQUIPAMENTO', col3: 'DS_MATERIAL', col4: 'VL_DIAMETRO' }
        };
        // Se é coluna direta (CD_CHAVE, DS_PONTO_VINCULADO), retorna como está
        if (!colGenerica.startsWith('col')) return colGenerica;
        return mapa[tipo] ? mapa[tipo][colGenerica] || 'CD_CHAVE' : 'CD_CHAVE';
    }

    /**
     * Ordena instrumentos por coluna clicada.
     * Alterna ASC/DESC se clicar na mesma coluna.
     * @param {string} coluna - Nome genérico (col1..col4) ou nome real (CD_CHAVE, DS_PONTO_VINCULADO)
     */
    function ordenarInstrumentos(coluna) {
        const tipo = parseInt(document.getElementById('filtroInstrumentoTipo').value);
        const colunaReal = obterColunaReal(coluna, tipo);

        // Alterna direção se mesma coluna
        if (instrOrdenarPor === colunaReal) {
            instrOrdenarDir = instrOrdenarDir === 'ASC' ? 'DESC' : 'ASC';
        } else {
            instrOrdenarPor = colunaReal;
            instrOrdenarDir = 'ASC';
        }

        buscarInstrumentos(1);
    }

    /**
     * Atualiza os cabeçalhos da tabela conforme o tipo de medidor selecionado,
     * para que as colunas façam sentido para cada tipo.
     * @param {int} tipo - ID do tipo de medidor
     */
    function atualizarCabecalhoTabela(tipo) {
        switch (tipo) {
            case 1: // Macromedidor
                document.getElementById('thInstrCol1').textContent = 'Marca';
                document.getElementById('thInstrCol2').textContent = 'Modelo';
                document.getElementById('thInstrCol3').textContent = 'Série';
                document.getElementById('thInstrCol4').textContent = 'Diâmetro';
                break;
            case 2: // Estação Pitométrica
                document.getElementById('thInstrCol1').textContent = 'Linha';
                document.getElementById('thInstrCol2').textContent = 'Sistema';
                document.getElementById('thInstrCol3').textContent = 'Revestimento';
                document.getElementById('thInstrCol4').textContent = 'Diâmetro';
                break;
            case 4: // Medidor Pressão
                document.getElementById('thInstrCol1').textContent = 'Matrícula';
                document.getElementById('thInstrCol2').textContent = 'Nº Série';
                document.getElementById('thInstrCol3').textContent = 'Material';
                document.getElementById('thInstrCol4').textContent = 'Diâmetro';
                break;
            case 6: // Nível Reservatório
                document.getElementById('thInstrCol1').textContent = 'Marca';
                document.getElementById('thInstrCol2').textContent = 'Modelo';
                document.getElementById('thInstrCol3').textContent = 'Série';
                document.getElementById('thInstrCol4').textContent = 'Volume (m³)';
                break;
            case 8: // Hidrômetro
                document.getElementById('thInstrCol1').textContent = 'Matrícula';
                document.getElementById('thInstrCol2').textContent = 'Nº Série';
                document.getElementById('thInstrCol3').textContent = 'Material';
                document.getElementById('thInstrCol4').textContent = 'Diâmetro';
                break;
        }
    }

    /**
     * Retorna os valores para as 4 colunas dinâmicas da tabela.
     * @param {object} item - Dados do instrumento
     * @param {int} tipo - ID do tipo de medidor
     * @returns {object} { col1, col2, col3, col4 }
     */
    function obterColunasTabela(item, tipo) {
        const vazio = '<span style="color:#94a3b8;">-</span>';
        const fmtNum = (v) => v !== null && v !== undefined && v !== '' ? parseFloat(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : null;

        switch (tipo) {
            case 1: // Macromedidor
                return {
                    col1: item.DS_MARCA || vazio,
                    col2: item.DS_MODELO || vazio,
                    col3: item.DS_SERIE || vazio,
                    col4: fmtNum(item.VL_DIAMETRO) || vazio
                };
            case 2: // Estação Pitométrica
                return {
                    col1: item.DS_LINHA || vazio,
                    col2: item.DS_SISTEMA || vazio,
                    col3: item.DS_REVESTIMENTO || vazio,
                    col4: fmtNum(item.VL_DIAMETRO) || vazio
                };
            case 4: // Medidor Pressão
                return {
                    col1: item.DS_MATRICULA_USUARIO || vazio,
                    col2: item.DS_NUMERO_SERIE_EQUIPAMENTO || vazio,
                    col3: item.DS_MATERIAL || vazio,
                    col4: fmtNum(item.VL_DIAMETRO) || vazio
                };
            case 6: // Nível Reservatório
                return {
                    col1: item.DS_MARCA || vazio,
                    col2: item.DS_MODELO || vazio,
                    col3: item.DS_SERIE || vazio,
                    col4: fmtNum(item.VL_VOLUME_TOTAL) || vazio
                };
            case 8: // Hidrômetro
                return {
                    col1: item.DS_MATRICULA_USUARIO || vazio,
                    col2: item.DS_NUMERO_SERIE_EQUIPAMENTO || vazio,
                    col3: item.DS_MATERIAL || vazio,
                    col4: fmtNum(item.VL_DIAMETRO) || vazio
                };
            default:
                return { col1: vazio, col2: vazio, col3: vazio, col4: vazio };
        }
    }

    // ============================================
    // Modal: Campos dinâmicos por tipo de medidor
    // ============================================

    /**
     * Helper para gerar campo input no modal.
     * Usa o padrão form-row + form-group + col do sistema.
     * @param {string} name - Nome do campo
     * @param {string} label - Rótulo
     * @param {string} icon - Ícone Ionicons
     * @param {string} type - Tipo do input (text, number, date)
     * @param {string} placeholder - Placeholder
     * @param {int} col - Tamanho da coluna (4, 6, 8, 12)
     * @param {string} step - Step para inputs numéricos
     */
    function instrInput(name, label, icon, type = 'text', placeholder = '', col = 4, step = '') {
        const stepAttr = step ? `step="${step}"` : '';
        return `
            <div class="form-group col-${col}">
                <label class="form-label">
                    <ion-icon name="${icon}"></ion-icon>
                    ${label}
                </label>
                <input type="${type}" name="${name}" id="instr_${name}" class="form-control" placeholder="${placeholder}" ${stepAttr}>
            </div>
        `;
    }

    /**
     * Helper para gerar campo select no modal.
     * Adiciona classe select2-instr para inicialização posterior.
     */
    function instrSelect(name, label, icon, options, colSize = '50%') {
        let optsHtml = '';
        options.forEach(function (o) {
            optsHtml += '<option value="' + o.value + '">' + o.text + '</option>';
        });
        return '<div class="form-group" style="width: ' + colSize + ';">' +
            '<label class="form-label">' +
            '<ion-icon name="' + icon + '"></ion-icon> ' + label +
            '</label>' +
            '<select name="' + name + '" id="instr_' + name + '" class="form-control select2-instr">' +
            optsHtml +
            '</select>' +
            '</div>';
    }

    /**
     * Atualiza os campos do modal conforme o tipo de medidor selecionado.
     * Usa o padrão form-row do sistema para layout em 3 colunas.
     */
    function atualizarCamposModal() {
        var tipoRaw = document.getElementById('instrTipoMedidor').value;
        var tipo = tipoRaw ? parseInt(tipoRaw) : 1;
        let html = '';

        switch (tipo) {
            case 1: // Macromedidor
                html = `
                    ${instrInput('ds_tag', 'Tag', 'pricetag-outline', 'text', 'Tag de identificação', '33.33%')}
                    ${instrSelect('cd_tipo_medidor_equip', 'Tipo de Medidor', 'speedometer-outline', tiposMedidorEquip, '33.33%')}
                    ${instrInput('ds_marca', 'Marca', 'pricetag-outline', 'text', 'Ex: CONAUT', '33.33%')}
                    ${instrInput('ds_modelo', 'Modelo', 'cube-outline', 'text', 'Ex: OPTIFLUX KC2000', '33.33%')}
                    ${instrInput('ds_serie', 'Série', 'barcode-outline', 'text', 'Número de série', '33.33%')}
                    ${instrInput('dt_fabricacao', 'Data Fabricação', 'calendar-outline', 'date', '', '33.33%')}
                    ${instrInput('ds_patrimonio_primario', 'Patrimônio Primário', 'document-outline', 'text', '', '33.33%')}
                    ${instrInput('ds_patrimonio_secundario', 'Patrimônio Secundário', 'document-outline', 'text', '', '33.33%')}
                    ${instrInput('vl_diametro', 'Diâmetro (mm)', 'resize-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('vl_diametro_rede', 'Diâmetro Rede (mm)', 'git-branch-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('ds_revestimento', 'Revestimento', 'layers-outline', 'text', '', '33.33%')}
                    ${instrInput('vl_capacidade_nominal', 'Capacidade Nominal', 'speedometer-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('vl_k_fabricante', 'K Fabricante', 'calculator-outline', 'number', '0.0000', '33.33%', '0.0001')}
                    ${instrInput('vl_perda_carga_fabricante', 'Perda Carga Fabricante', 'trending-down-outline', 'number', '0.0000', '33.33%', '0.0001')}
                    ${instrInput('vl_pressao_maxima', 'Pressão Máxima (mca)', 'arrow-up-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('vl_vazao_esperada', 'Vazão Esperada', 'water-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('ds_tipo_flange', 'Forma / Tipo Flange', 'ellipse-outline', 'text', '', '33.33%')}
                    ${instrInput('ds_altura_soleira', 'Altura da Soleira', 'resize-outline', 'text', '', '33.33%')}
                    ${instrInput('ds_natureza_parede', 'Natureza da Parede', 'construct-outline', 'text', '', '33.33%')}
                    ${instrInput('ds_largura_relativa', 'Largura Relativa', 'swap-horizontal-outline', 'text', '', '33.33%')}
                    ${instrInput('ds_largura_garganta', 'Largura da Garganta', 'code-outline', 'text', '', '33.33%')}
                    ${instrInput('vl_cota', 'Cota (m)', 'analytics-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('prot_comun', 'Protocolo Comunicação', 'radio-outline', 'text', 'Ex: Modbus', '33.33%')}
                    ${instrSelect('id_produto', 'Tipo de Fluido', 'water-outline', tiposFluido, '33.33%')}
                    ${instrSelect('cd_estacao_pitometrica', 'Código da EP', 'link-outline', estacoesDisponiveis, '100%')}           
                `;
                break;

            case 2: // Estação Pitométrica
                html = `
                    ${instrInput('ds_tag', 'TAG', 'pricetag-outline', 'text', 'Ex: CP004_TM192_17_MED', '33.33%')}
                    ${instrInput('vl_cota_geografica', 'Cota (m)', 'location-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('ds_sistema', 'Sistema', 'apps-outline', 'text', 'Sistema', '33.33%')}
                    ${instrInput('vl_diametro', 'Diâmetro do Trecho (mm)', 'resize-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('vl_diametro_rede', 'Diâmetro Rede (mm)', 'git-branch-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('ds_revestimento', 'Revestimento', 'layers-outline', 'text', '', '33.33%')}
                    ${instrInput('ds_linha', 'Linha', 'git-commit-outline', 'text', 'Identificação da linha', '33.33%')}
                    ${instrSelect('tp_periodicidade_levantamento', 'Periodicidade Levantamento Kpc', 'time-outline', periodicidadesKpc, '33.33%')}
                `;
                break;

            case 4: // Medidor Pressão
                html = `
                    ${instrInput('ds_tag', 'TAG', 'pricetag-outline', 'text', 'Ex: CP004_TM192_17_MED', '33.33%')}
                    ${instrInput('ds_matricula_usuario', 'Matrícula Usuário', 'person-outline', 'text', '', '33.33%')}
                    ${instrInput('vl_diametro', 'Diâmetro (mm)', 'resize-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('ds_numero_serie_equipamento', 'Nº Série Equipamento', 'barcode-outline', 'text', '', '33.33%')}
                    ${instrInput('vl_diametro_rede', 'Diâmetro Rede (mm)', 'git-branch-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('dt_instalacao', 'Data Instalação', 'calendar-outline', 'date', '', '33.33%')}
                    ${instrInput('ds_cota', 'Cota (m)', 'analytics-outline', 'text', '', '33.33%')}
                    ${instrSelect('op_telemetria', 'Telemetria', 'radio-outline', [
                    { value: '', text: 'Indiferente' }, { value: '1', text: 'Sim' }, { value: '0', text: 'Não' }
                ], '33.33%')}
                    ${instrInput('ds_coordenadas', 'Coordenadas', 'navigate-outline', 'text', 'Lat, Long', '33.33%')}
                    ${instrInput('ds_material', 'Material', 'construct-outline', 'text', '', '33.33%')}
                    ${instrInput('ds_endereco', 'Endereço', 'location-outline', 'text', 'Endereço completo', '100%')}
                `;
                break;

            case 6: // Nível Reservatório
                html = `
                    ${instrInput('ds_tag', 'Tag', 'pricetag-outline', 'text', '', '33.33%')}
                    ${instrSelect('cd_tipo_medidor_equip', 'Tipo de Medidor', 'speedometer-outline', tiposMedidorEquip, '33.33%')}
                    ${instrInput('ds_marca', 'Marca', 'pricetag-outline', 'text', '', '33.33%')}
                    ${instrInput('dt_fabricacao', 'Data Fabricação', 'calendar-outline', 'date', '', '33.33%')}
                    ${instrInput('ds_modelo', 'Modelo', 'cube-outline', 'text', '', '33.33%')}
                    ${instrInput('dt_instalacao', 'Data Instalação', 'calendar-outline', 'date', '', '33.33%')}
                    ${instrInput('ds_serie', 'Série', 'barcode-outline', 'text', '', '33.33%')}
                    ${instrInput('ds_patrimonio_primario', 'Patrimônio Primário', 'document-outline', 'text', '', '33.33%')}
                    ${instrInput('ds_patrimonio_secundario', 'Patrimônio Secundário', 'document-outline', 'text', '', '33.33%')}
                    ${instrSelect('cd_tipo_reservatorio', 'Reservatório', 'home-outline', tiposReservatorio, '33.33%')}
                    ${instrInput('cota_extravasamento_m', 'Cota Extrav. (m)', 'arrow-up-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('ds_altura_maxima', 'Altura Máxima (m)', 'resize-outline', 'text', '', '33.33%')}
                    ${instrInput('cota_extravasamento_p', 'Cota Extrav. (%)', 'arrow-up-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('vl_cota', 'Cota Fundo', 'analytics-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('vl_pressao_maxima_succao', 'Pressão Máx. Sucção (mca)', 'arrow-down-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('vl_na', 'NA (m)', 'water-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('vl_pressao_maxima_recalque', 'Pressão Máx. Recalque (mca)', 'arrow-up-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('vl_volume_total', 'Volume Total (m³)', 'cube-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('vl_volume_camara_a', 'Volume Câmara A (m³)', 'cube-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('vl_volume_camara_b', 'Volume Câmara B (m³)', 'cube-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrSelect('id_produto', 'Tipo de Fluido', 'water-outline', tiposFluido, '33.33%')}
                `;
                break;

            case 8: // Hidrômetro
                html = `
                    ${instrInput('ds_tag', 'TAG', 'pricetag-outline', 'text', 'Ex: CP004_TM192_17_MED', '33.33%')}
                    ${instrInput('ds_matricula_usuario', 'Matrícula Usuário', 'person-outline', 'text', '', '33.33%')}
                    ${instrInput('vl_diametro', 'Diâmetro (mm)', 'resize-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('ds_numero_serie_equipamento', 'Nº Série Equipamento', 'barcode-outline', 'text', '', '33.33%')}
                    ${instrInput('vl_diametro_rede', 'Diâmetro Rede (mm)', 'git-branch-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('dt_instalacao', 'Data Instalação', 'calendar-outline', 'date', '', '33.33%')}
                    ${instrInput('ds_cota', 'Cota (m)', 'analytics-outline', 'text', '', '33.33%')}
                    ${instrSelect('id_tempo_operacao', 'Tempo de Operação', 'timer-outline', temposOperacao, '33.33%')}
                    ${instrInput('ds_coordenadas', 'Coordenadas', 'navigate-outline', 'text', 'Lat, Long', '33.33%')}
                    ${instrInput('vl_leitura_limite', 'Leitura Limite', 'speedometer-outline', 'number', '0.00', '33.33%', '0.01')}
                    ${instrInput('ds_material', 'Material', 'construct-outline', 'text', '', '33.33%')}
                    ${instrInput('vl_multiplicador', 'Multiplicador', 'calculator-outline', 'number', '0.0000', '33.33%', '0.0001')}
                    ${instrInput('ds_endereco', 'Endereço', 'location-outline', 'text', 'Endereço completo', '100%')}
                    
                `;
                break;
        }

        document.getElementById('instrCampos').innerHTML = html;

        // Inicializa Select2 com pesquisa nos selects do modal (padrão do sistema)
        setTimeout(function () {
            // Destroy anterior se existir (evita duplicação)
            $('#instrCampos .select2-instr').each(function () {
                if ($(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2('destroy');
                }
            });
            // Inicializa Select2 seguindo o padrão pontoMedicao.php
            $('#instrCampos .select2-instr').select2({
                dropdownParent: $('#modalInstrumento .modal-body'),
                width: '100%',
                placeholder: 'Selecione...',
                allowClear: true,
                minimumResultsForSearch: 0,
                language: {
                    noResults: function () { return 'Nenhum resultado encontrado'; },
                    searching: function () { return 'Pesquisando...'; }
                }
            });

            // Autofocus no campo de busca ao abrir o dropdown
            $('#instrCampos .select2-instr').on('select2:open', function () {
                setTimeout(function () {
                    var searchField = document.querySelector('.select2-container--open .select2-search__field');
                    if (searchField) {
                        searchField.focus();
                    }
                }, 10);
            });
        }, 50);
    }

    /**
         * Listener para mudança de tipo do medidor durante edição.
         * Se o instrumento está vinculado a um ponto e o tipo mudou,
         * avisa que será desvinculado por incompatibilidade de tipo.
         */
    document.getElementById('instrTipoMedidor').addEventListener('change', function () {
        const tipoOriginal = this.dataset.tipoOriginal;
        const cdChaveEdit = this.dataset.cdChaveEdit;
        const tipoNovo = this.value;

        // Modo criação: apenas atualiza campos
        if (!tipoOriginal || !cdChaveEdit) {
            atualizarCamposModal();
            return;
        }

        // Voltou ao tipo original: só atualiza campos
        if (tipoNovo === tipoOriginal) {
            atualizarCamposModal();
            return;
        }

        // Verifica se está vinculado a um ponto de medição
        let cdPontoVinculado = null;

        $.ajax({
            url: 'bd/cadastrosAuxiliares/listarInstrumentos.php',
            type: 'GET',
            data: {
                id_tipo_medidor: tipoOriginal,
                busca: cdChaveEdit,
                pagina: 1,
                limite: 1
            },
            dataType: 'json',
            async: false,
            success: function (response) {
                if (response.success && response.dados && response.dados.length > 0) {
                    const item = response.dados[0];
                    if (item.CD_PONTO_MEDICAO != null && item.CD_PONTO_MEDICAO !== '') {
                        cdPontoVinculado = item.CD_PONTO_MEDICAO;
                    }
                }
            }
        });

        if (cdPontoVinculado) {
            const confirmou = confirm(
                'ATENÇÃO: Este instrumento está vinculado a um Ponto de Medição.\n\n' +
                'Alterar o tipo do medidor irá DESVINCULAR o instrumento do ponto, ' +
                'pois o tipo não será mais compatível.\n\n' +
                'Deseja continuar?'
            );

            if (!confirmou) {
                // Reverte para o tipo original
                this.value = tipoOriginal;
                return;
            }

            // Desvincula o instrumento
            $.ajax({
                url: 'bd/pontoMedicao/desvincularInstrumento.php',
                type: 'POST',
                data: {
                    cd_chave: cdChaveEdit,
                    cd_ponto_medicao: cdPontoVinculado,
                    id_tipo_medidor: tipoOriginal
                },
                dataType: 'json',
                async: false,
                success: function (resp) {
                    if (resp.success) {
                        showToast('Instrumento desvinculado do ponto de medição', 'aviso');
                    } else {
                        showToast(resp.message || 'Erro ao desvincular', 'erro');
                    }
                },
                error: function () {
                    showToast('Erro ao comunicar com o servidor', 'erro');
                }
            });
        }

        // Atualiza campos para o novo tipo
        atualizarCamposModal();
    });

    /**
      * Abre o modal para criar novo instrumento.
      * Limpa campos e define tipo conforme filtro atual.
      */
    function abrirModalInstrumento() {
        // Limpa cd_chave (é novo, não edição)
        document.getElementById('instrCdChave').value = '';
        var btnSalvar = document.querySelector('#modalInstrumento .btn-primary');
        if (btnSalvar) {
            delete btnSalvar.dataset.cdChave;
        }

        document.getElementById('modalInstrumentoTitulo').textContent = 'Novo Instrumento';

        // Habilita seleção do tipo
        document.getElementById('instrTipoMedidor').disabled = false;
        document.getElementById('grupoTipoInstr').style.display = 'block';

        // Sincroniza tipo com filtro da aba (se "Todos", usa Macromedidor como padrão)
        var tipoFiltro = document.getElementById('filtroInstrumentoTipo').value;
        document.getElementById('instrTipoMedidor').value = tipoFiltro || '1';

        // Monta campos
        atualizarCamposModal();

        abrirModal('modalInstrumento');
    }

    /**
      * Abre o modal para editar instrumento existente.
      * Busca dados via listarInstrumentos.php e preenche campos.
      * @param {int} cdChave - CD_CHAVE do instrumento
      * @param {int} tipo - ID do tipo de medidor
      */
    function editarInstrumento(cdChave, tipo) {

        // Seta cd_chave no hidden E no data-attr do botão salvar (redundância)
        document.getElementById('instrCdChave').value = cdChave;
        var btnSalvar = document.querySelector('#modalInstrumento .btn-primary');
        if (btnSalvar) {
            btnSalvar.dataset.cdChave = cdChave;
        }

        // Configura título e tipo
        document.getElementById('instrTipoMedidor').value = tipo;
        document.getElementById('modalInstrumentoTitulo').textContent = 'Editar Instrumento (CD: ' + cdChave + ')';

        // Mostra o tipo mas desabilita (não pode mudar na edição)
        document.getElementById('instrTipoMedidor').disabled = true;
        document.getElementById('grupoTipoInstr').style.display = 'block';

        // Monta campos para o tipo
        atualizarCamposModal();

        // Mapeamento: coluna BD (lowercase) → name do campo no form
        var fieldMap = {
            'cd_tipo_medidor': 'cd_tipo_medidor_equip'
        };

        // Busca dados do instrumento
        $.get('bd/cadastrosAuxiliares/listarInstrumentos.php', {
            id_tipo_medidor: tipo,
            busca: cdChave,
            pagina: 1,
            por_pagina: 1
        }, function (response) {
            if (response.success && response.data.length > 0) {
                var dados = response.data[0];


                // Preenche campos do modal
                Object.keys(dados).forEach(function (key) {
                    var fieldName = key.toLowerCase();

                    // Aplica mapeamento se existir
                    if (fieldMap[fieldName]) {
                        fieldName = fieldMap[fieldName];
                    }

                    var field = document.getElementById('instr_' + fieldName);
                    if (field) {
                        if (field.type === 'date' && dados[key]) {
                            field.value = dados[key].substring(0, 10);
                        } else {
                            field.value = (dados[key] !== null && dados[key] !== undefined) ? dados[key] : '';
                        }
                    }
                });

                // Atualiza Select2 (não responde a .value, precisa de .val().trigger)
                $('#instrCampos .select2-instr').each(function () {
                    var fieldName = $(this).attr('name');
                    var dbKey = fieldName.toUpperCase();

                    // Mapeamento inverso: form name → coluna BD
                    if (fieldName === 'cd_tipo_medidor_equip') {
                        dbKey = 'CD_TIPO_MEDIDOR';
                    }

                    if (dados[dbKey] !== undefined && dados[dbKey] !== null) {
                        $(this).val(dados[dbKey]).trigger('change');
                    } else {
                        $(this).val('').trigger('change');
                    }
                });

                // RE-SETA cd_chave (garante que não foi perdido)
                document.getElementById('instrCdChave').value = cdChave;

                abrirModal('modalInstrumento');
            } else {
                showToast('Instrumento não encontrado', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao carregar dados do instrumento', 'erro');
        });
    }

    /**
     * Salva (cria ou atualiza) instrumento.
     * Coleta todos os campos do modal e envia ao endpoint.
     * Usa data-attr como fallback para cd_chave (segurança contra perda do hidden).
     */
    function salvarInstrumento() {
        // Lê cd_chave do hidden field
        var cdChave = document.getElementById('instrCdChave').value;

        // Fallback: tenta ler do data-attribute do botão salvar
        if (!cdChave) {
            var btnSalvar = document.querySelector('#modalInstrumento .btn-primary');
            if (btnSalvar && btnSalvar.dataset.cdChave) {
                cdChave = btnSalvar.dataset.cdChave;
            }
        }

        var tipo = document.getElementById('instrTipoMedidor').value;


        // Coleta todos os campos do container dinâmico
        var formData = {
            cd_chave: cdChave || '',
            id_tipo_medidor: tipo
        };

        // Coleta inputs e selects do container dinâmico
        document.querySelectorAll('#instrCampos input, #instrCampos select').forEach(function (el) {
            if (el.name) {
                formData[el.name] = el.value;
            }
        });


        $.post('bd/cadastrosAuxiliares/salvarInstrumento.php', formData, function (response) {

            if (response.success) {
                showToast(response.message, 'sucesso');
                fecharModal('modalInstrumento');
                buscarInstrumentos(estadoPaginacao.instrumentos ? estadoPaginacao.instrumentos.pagina : 1);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        }, 'json').fail(function (xhr) {
            console.log('[salvarInstrumento] FAIL:', xhr.responseText);
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    /**
     * Exclui instrumento após confirmação.
     * Só permite exclusão se não estiver vinculado (backend valida também).
     * @param {int} cdChave - CD_CHAVE do instrumento
     * @param {int} tipo - ID do tipo de medidor
     */
    function excluirInstrumento(cdChave, tipo) {
        if (!confirm('Deseja realmente excluir este instrumento?\n\nNão será possível excluir instrumentos vinculados a um ponto de medição.')) return;

        $.post('bd/cadastrosAuxiliares/excluirInstrumento.php', {
            cd_chave: cdChave,
            id_tipo_medidor: tipo
        }, function (response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                buscarInstrumentos(estadoPaginacao.instrumentos ? estadoPaginacao.instrumentos.pagina : 1);
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }


    // ============================================
    // Carregar dados ao iniciar
    // ============================================
    function carregarDadosAba(nomeAba) {
        if (!abasCarregadas[nomeAba]) {
            abasCarregadas[nomeAba] = true;
            switch (nomeAba) {
                case 'tipoMedidor': buscarTiposMedidor(1); break;
                case 'tipoReservatorio': buscarTiposReservatorio(1); break;
                case 'areaInfluencia': buscarAreasInfluencia(1); break;
                case 'unidade': buscarUnidades(1); break;
                case 'localidade': buscarLocalidades(1); break;
                case 'sistemaAgua': buscarSistemasAgua(1); break;
                case 'instrumentos': buscarInstrumentos(1); break;
                case 'eta': buscarEtas(1); break;
            }
        }
    }

    let abasCarregadas = {};

    document.addEventListener('DOMContentLoaded', function () {
        const urlParams = new URLSearchParams(window.location.search);
        // Suporta tanto ?aba= quanto ?tab= (compatibilidade)
        const abaParam = urlParams.get('aba') || urlParams.get('tab');
        const tipoParam = urlParams.get('tipo');
        const editarParam = urlParams.get('editar');

        // Sempre carregar a aba padrão
        abasCarregadas['tipoMedidor'] = true;
        buscarTiposMedidor(1);

        if (abaParam && document.querySelector(`.tab-btn[data-tab="${abaParam}"]`)) {
            ativarAba(abaParam);
            carregarDadosAba(abaParam);

            // Se é aba instrumentos com parâmetros de edição
            if (abaParam === 'instrumentos' && tipoParam) {
                setTimeout(function () {
                    document.getElementById('filtroInstrumentoTipo').value = tipoParam;
                    buscarInstrumentos(1);

                    if (editarParam) {
                        setTimeout(function () {
                            editarInstrumento(parseInt(editarParam), parseInt(tipoParam));
                        }, 600);
                    }
                }, 200);
            }
        }
    });

    // Carregar dados ao trocar de aba (apenas se não carregou ainda)
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            carregarDadosAba(this.dataset.tab);
        });
    });
</script>

<!-- Choices.js (não precisa de jQuery) -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<!-- Dados para dropdowns de Instrumentos (gerados via PHP) -->
<script>
    // Tipos de Medidor (tabela TIPO_MEDIDOR)
    var tiposMedidorEquip = [];
    // Tipos de Reservatório (tabela TIPO_RESERVATORIO)
    var tiposReservatorio = [];
    // Tipos de Fluido (ID_PRODUTO)
    var tiposFluido = [
        { value: '', text: 'Indiferente' },
        { value: '1', text: 'Água Tratada' },
        { value: '3', text: 'Água Bruta' },
        { value: '2', text: 'Esgoto' }
    ];
    // Periodicidade do levantamento do Kpc
    var periodicidadesKpc = [
        { value: '', text: 'Indiferente' },
        { value: '1', text: 'Segundo' },
        { value: '2', text: 'Minuto' },
        { value: '3', text: 'Hora' },
        { value: '4', text: 'Diário' },
        { value: '5', text: 'Semanal' },
        { value: '6', text: 'Mensal' },
        { value: '7', text: 'Bimestral' },
        { value: '8', text: 'Semestral' },
        { value: '9', text: 'Anual' },
        { value: '10', text: 'Bienal' }
    ];
    // Tempo de Operação (Hidrômetro)
    var temposOperacao = [
        { value: '', text: 'Indiferente' },
        { value: '1', text: 'Até 12hs' },
        { value: '2', text: '24hs' }
    ];

    <?php
    // Carrega Tipos de Medidor do banco
    try {
        $sqlTM = $pdoSIMP->query("SELECT CD_CHAVE, DS_NOME FROM SIMP.dbo.TIPO_MEDIDOR ORDER BY DS_NOME");
        $listaTM = $sqlTM->fetchAll(PDO::FETCH_ASSOC);
        echo "tiposMedidorEquip = [{value:'', text:'Indiferente'},";
        foreach ($listaTM as $tm) {
            $nome = addslashes($tm['DS_NOME']);
            echo "{value:'{$tm['CD_CHAVE']}', text:'{$nome}'},";
        }
        echo "];\n";
    } catch (Exception $e) {
        echo "tiposMedidorEquip = [{value:'', text:'Indiferente'},{value:'1', text:'Tipo 1'}];\n";
    }

    // Carrega Tipos de Reservatório do banco
    try {
        $sqlTR = $pdoSIMP->query("SELECT CD_CHAVE, NOME FROM SIMP.dbo.TIPO_RESERVATORIO ORDER BY NOME");
        $listaTR = $sqlTR->fetchAll(PDO::FETCH_ASSOC);
        echo "tiposReservatorio = [{value:'', text:'Selecione...'},";
        foreach ($listaTR as $tr) {
            $nome = addslashes($tr['NOME']);
            echo "{value:'{$tr['CD_CHAVE']}', text:'{$nome}'},";
        }
        echo "];\n";
    } catch (Exception $e) {
        echo "tiposReservatorio = [{value:'', text:'Selecione...'}];\n";
    }
    ?>
    // Carrega Estações Pitométricas (Pontos de Medição tipo EP)
    var estacoesDisponiveis = [];
    <?php
    try {
        $sqlEP = $pdoSIMP->query("
                SELECT PM.CD_PONTO_MEDICAO, PM.DS_NOME, L.DS_NOME AS DS_LOCALIDADE
                FROM SIMP.dbo.PONTO_MEDICAO PM
                LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
                WHERE PM.ID_TIPO_MEDIDOR = 2
                  AND PM.DT_DESATIVACAO IS NULL
                ORDER BY PM.DS_NOME
            ");
        $listaEP = $sqlEP->fetchAll(PDO::FETCH_ASSOC);
        echo "estacoesDisponiveis = [{value:'', text:'Indiferente'},";
        foreach ($listaEP as $ep) {
            $nome = addslashes(trim($ep['DS_NOME']));
            $loc = addslashes(trim($ep['DS_LOCALIDADE'] ?? ''));
            $label = $ep['CD_PONTO_MEDICAO'] . ' - ' . $nome . ($loc ? ' (' . $loc . ')' : '');
            echo "{value:'{$ep['CD_PONTO_MEDICAO']}', text:'{$label}'},";
        }
        echo "];\n";
    } catch (Exception $e) {
        echo "estacoesDisponiveis = [{value:'', text:'Indiferente'}];\n";
    }
    ?>

</script>

<script>
    // ============================================
    // Choices.js Inicialização
    // ============================================
    const choicesInstances = {};

    function initChoices() {
        document.querySelectorAll('.choices-select').forEach(function (select) {
            if (choicesInstances[select.id]) {
                return; // Já inicializado
            }

            choicesInstances[select.id] = new Choices(select, {
                searchEnabled: true,
                searchPlaceholderValue: 'Digite para buscar...',
                noResultsText: 'Nenhum resultado encontrado',
                noChoicesText: 'Nenhuma opção disponível',
                itemSelectText: '',
                placeholder: true,
                placeholderValue: 'Selecione...',
                removeItemButton: false,
                shouldSort: false,
                searchResultLimit: 50,
                fuseOptions: {
                    threshold: 0.3
                }
            });
        });
    }

    // Função para setar valor no Choices
    function setChoicesValue(selectId, value) {
        if (choicesInstances[selectId]) {
            choicesInstances[selectId].setChoiceByValue(String(value));
        }
    }

    // Função para limpar Choices
    function clearChoices(selectId) {
        if (choicesInstances[selectId]) {
            choicesInstances[selectId].setChoiceByValue('');
        }
    }

    // Função para obter valor do Choices
    function getChoicesValue(selectId) {
        if (choicesInstances[selectId]) {
            return choicesInstances[selectId].getValue(true);
        }
        return document.getElementById(selectId).value;
    }

    // Inicializar quando DOM estiver pronto
    document.addEventListener('DOMContentLoaded', function () {
        initChoices();
    });
</script>

<?php include_once 'includes/footer.inc.php'; ?>
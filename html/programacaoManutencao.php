<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Programação de Calibração e Manutenção
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão
exigePermissaoTela('Programação de Manutenção', ACESSO_LEITURA);

// Permissão do usuário para este módulo
$podeEditar = podeEditarTela('Programação de Manutenção');

// Buscar Unidades para dropdown
$sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME, CD_CODIGO FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

// Tipos de Programação
$tiposProgramacao = [
    1 => 'Calibração',
    2 => 'Manutenção'
];

// Situações
$situacoes = [
    1 => 'Prevista',
    2 => 'Executada',
    4 => 'Cancelada'
];
?>

<!-- Choices.js CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

<style>
    /* ============================================
       Choices.js Customização
       ============================================ */
    .choices {
        margin-bottom: 0;
        width: 100%;
    }

    .choices__inner {
        min-height: 44px;
        padding: 6px 12px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        width: 100%;
        box-sizing: border-box;
    }

    .choices__inner:focus,
    .is-focused .choices__inner,
    .is-open .choices__inner {
        background-color: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .choices__list--single {
        padding: 4px 16px 4px 4px;
    }

    .choices__list--single .choices__item {
        color: #1e293b;
        font-size: 14px;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .choices__list--single .choices__item--selectable {
        color: #1e293b !important;
    }

    .choices__placeholder {
        color: #94a3b8;
        opacity: 1;
    }

    .choices__list--dropdown {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        margin-top: 4px;
        z-index: 9999 !important;
    }

    .choices__list--dropdown .choices__item {
        padding: 12px 16px;
        font-size: 14px;
        color: #334155;
        background-color: #ffffff;
    }

    .choices__list--dropdown .choices__item--selectable:hover,
    .choices__list--dropdown .choices__item--selectable.is-highlighted,
    .choices__list--dropdown .choices__item.is-highlighted {
        background-color: #3b82f6 !important;
        color: #ffffff !important;
    }

    .choices__list--dropdown .choices__item--selectable.is-selected {
        background-color: #eff6ff;
        color: #3b82f6;
    }

    .choices[data-type*="select-one"] .choices__input {
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        margin: 8px;
        width: calc(100% - 16px) !important;
        max-width: calc(100% - 16px) !important;
        box-sizing: border-box;
    }

    .choices[data-type*="select-one"] .choices__input:focus {
        border-color: #3b82f6;
        outline: none;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .choices[data-type*="select-one"]::after {
        border-color: #64748b transparent transparent transparent;
        right: 14px;
    }

    /* ============================================
       Page Container
       ============================================ */
    .page-container {
        padding: 24px;
        max-width: 1800px;
        margin: 0 auto;
    }

    /* ============================================
       Page Header
       ============================================ */
    .page-header {
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        border-radius: 16px;
        padding: 28px 32px;
        margin-bottom: 24px;
        color: white;
    }

    .page-header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }

    .page-header-info {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .page-header-icon {
        width: 52px;
        height: 52px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }

    .page-header h1 {
        font-size: 22px;
        font-weight: 700;
        margin: 0 0 4px 0;
        color: white;
    }

    .page-header-subtitle {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.7);
        margin: 0;
    }

    /* ============================================
       Filters Card
       ============================================ */
    .filters-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 20px;
    }

    .filters-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .filters-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
        font-weight: 600;
        color: #1e293b;
    }

    .filters-title ion-icon {
        font-size: 20px;
        color: #3b82f6;
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
    }

    .filters-grid-6 {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 16px;
    }

    .filters-grid-6 .span-2 {
        grid-column: span 2;
    }

    .filters-grid-6 .span-3 {
        grid-column: span 3;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 600;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .form-label ion-icon {
        font-size: 14px;
        color: #94a3b8;
    }

    .form-control {
        width: 100%;
        padding: 12px 14px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-family: inherit;
        font-size: 13px;
        color: #334155;
        transition: all 0.2s ease;
        box-sizing: border-box;
    }

    .form-control:focus {
        outline: none;
        background-color: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Checkbox Group */
    .checkbox-group {
        display: flex;
        gap: 4px;
        background: #f1f5f9;
        padding: 4px;
        border-radius: 10px;
        flex-wrap: wrap;
    }

    .checkbox-item {
        display: flex;
        align-items: center;
        cursor: pointer;
        margin: 0;
    }

    .checkbox-item input[type="checkbox"] {
        display: none;
    }

    .checkbox-item .checkbox-label {
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 500;
        color: #64748b;
        border-radius: 8px;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .checkbox-item input[type="checkbox"]:checked + .checkbox-label {
        background: white;
        color: #1e293b;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .checkbox-item:hover .checkbox-label {
        color: #1e293b;
    }

    /* ============================================
       Botões de Ação
       ============================================ */
    .btn-buscar {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background: #3b82f6;
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-buscar:hover {
        background: #2563eb;
    }

    .btn-buscar ion-icon {
        font-size: 18px;
    }

    .btn-limpar {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background: #f1f5f9;
        color: #475569;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-limpar:hover {
        background: #e2e8f0;
    }

    .btn-novo {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        background: #22c55e;
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .btn-novo:hover {
        background: #16a34a;
    }

    .btn-novo ion-icon {
        font-size: 18px;
    }

    .filters-actions {
        display: flex;
        gap: 12px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
    }

    /* ============================================
       Results Info
       ============================================ */
    .results-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        padding: 0 4px;
    }

    .results-count {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: #64748b;
    }

    .results-count ion-icon {
        font-size: 16px;
        color: #94a3b8;
    }

    .results-count strong {
        color: #1e293b;
        font-weight: 600;
    }

    /* ============================================
       Table Container
       ============================================ */
    .table-container {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        position: relative;
    }

    .table-wrapper {
        overflow-x: auto;
    }

    .data-table {
        width: 100%;
        min-width: 1000px;
        border-collapse: collapse;
    }

    .data-table thead {
        position: sticky;
        top: 0;
        z-index: 5;
    }

    .data-table th {
        background: #f8fafc;
        padding: 14px 16px;
        text-align: left;
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }

    .data-table th.sortable {
        cursor: pointer;
        user-select: none;
        transition: background 0.15s ease;
    }

    .data-table th.sortable:hover {
        background: #e2e8f0;
    }

    .data-table th.sortable::after {
        content: '';
        display: inline-block;
        width: 0;
        height: 0;
        margin-left: 8px;
        vertical-align: middle;
        border-left: 4px solid transparent;
        border-right: 4px solid transparent;
        border-bottom: 5px solid #cbd5e1;
        opacity: 0.5;
    }

    .data-table th.sortable.asc::after {
        border-bottom: 5px solid #3b82f6;
        border-top: none;
        opacity: 1;
    }

    .data-table th.sortable.desc::after {
        border-bottom: none;
        border-top: 5px solid #3b82f6;
        opacity: 1;
    }

    .data-table tbody tr {
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.15s ease;
    }

    .data-table tbody tr:hover {
        background: #f8fafc;
    }

    .data-table tbody tr:last-child {
        border-bottom: none;
    }

    .data-table td {
        padding: 14px 16px;
        font-size: 13px;
        color: #475569;
        vertical-align: middle;
    }

    .data-table td.code {
        font-family: 'SF Mono', Monaco, 'Consolas', monospace;
        font-size: 12px;
        color: #3b82f6;
        font-weight: 600;
    }

    .data-table td.name {
        font-weight: 500;
        color: #1e293b;
    }

    .data-table td.truncate {
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Badges */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
    }

    .badge-calibracao {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .badge-manutencao {
        background: #f3e8ff;
        color: #7c3aed;
    }

    .badge-prevista {
        background: #fef3c7;
        color: #b45309;
    }

    .badge-executada {
        background: #dcfce7;
        color: #15803d;
    }

    .badge-cancelada {
        background: #fee2e2;
        color: #b91c1c;
    }

    .badge ion-icon {
        font-size: 12px;
    }

    /* Actions */
    .table-actions {
        display: flex;
        gap: 6px;
    }

    .btn-action {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .btn-action:hover {
        background: #eff6ff;
        border-color: #3b82f6;
        color: #3b82f6;
    }

    .btn-action.delete:hover {
        background: #fef2f2;
        border-color: #ef4444;
        color: #ef4444;
    }

    .btn-action ion-icon {
        font-size: 16px;
    }

    /* ============================================
       Empty State
       ============================================ */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }

    .empty-state-icon {
        width: 80px;
        height: 80px;
        background: #f1f5f9;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 36px;
        color: #94a3b8;
    }

    .empty-state h3 {
        font-size: 16px;
        font-weight: 600;
        color: #475569;
        margin: 0 0 8px 0;
    }

    .empty-state p {
        font-size: 13px;
        color: #94a3b8;
        margin: 0;
    }

    /* ============================================
       Loading State
       ============================================ */
    .loading-overlay {
        display: none;
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.9);
        z-index: 10;
        justify-content: center;
        align-items: center;
    }

    .loading-overlay.active {
        display: flex;
    }

    .loading-spinner {
        width: 48px;
        height: 48px;
        border: 4px solid #e2e8f0;
        border-top-color: #3b82f6;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    /* ============================================
       Pagination
       ============================================ */
    .pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        border-top: 1px solid #e2e8f0;
        flex-wrap: wrap;
        gap: 12px;
    }

    .pagination-info {
        font-size: 13px;
        color: #64748b;
        order: 2;
    }

    .pagination-controls {
        display: flex;
        gap: 4px;
        order: 1;
    }

    .btn-page {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        padding: 0 10px;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        color: #475569;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-page:hover:not(.disabled):not(.active) {
        background: #f8fafc;
        border-color: #cbd5e1;
    }

    .btn-page.active {
        background: #3b82f6;
        border-color: #3b82f6;
        color: white;
    }

    .btn-page.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .btn-page ion-icon {
        font-size: 16px;
    }

    /* ============================================
       Responsive
       ============================================ */
    @media (max-width: 1400px) {
        .filters-grid-6 {
            grid-template-columns: repeat(3, 1fr);
        }
        .filters-grid-6 .span-2 {
            grid-column: span 2;
        }
        .filters-grid-6 .span-3 {
            grid-column: span 3;
        }
    }

    @media (max-width: 1024px) {
        .filters-grid-6 {
            grid-template-columns: repeat(2, 1fr);
        }
        .filters-grid-6 .span-2,
        .filters-grid-6 .span-3 {
            grid-column: span 2;
        }
    }

    @media (max-width: 768px) {
        .page-container {
            padding: 16px;
        }

        .page-header {
            padding: 20px;
            border-radius: 12px;
        }

        .page-header-content {
            flex-direction: column;
            align-items: flex-start;
        }

        .page-header h1 {
            font-size: 18px;
        }

        .filters-card {
            padding: 16px;
        }

        .filters-grid-6 {
            grid-template-columns: 1fr;
        }

        .filters-grid-6 .span-2,
        .filters-grid-6 .span-3 {
            grid-column: span 1;
        }

        .filters-actions {
            flex-direction: column;
        }

        .btn-buscar, .btn-limpar {
            width: 100%;
            justify-content: center;
        }

        .pagination {
            flex-direction: column;
            text-align: center;
        }
    }

    /* Autocomplete para Ponto de Medição */
    .autocomplete-container {
        position: relative;
    }

    .autocomplete-container input.form-control {
        padding-right: 35px;
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
        z-index: 1000;
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
        background-color: #3b82f6;
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
</style>

<div class="page-container">
    <!-- Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="calendar-outline"></ion-icon>
                </div>
                <div>
                    <h1>Programação de Calibração e Manutenção</h1>
                    <p class="page-header-subtitle">Gerencie as programações de calibração e manutenção dos pontos de medição</p>
                </div>
            </div>
            <?php if ($podeEditar): ?>
            <a href="programacaoManutencaoForm.php" class="btn-novo">
                <ion-icon name="add-outline"></ion-icon>
                Nova Programação
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <div class="filters-header">
            <div class="filters-title">
                <ion-icon name="filter-outline"></ion-icon>
                Consultar Programação de Calibração e Manutenção
            </div>
        </div>

        <div class="filters-grid-6">
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="business-outline"></ion-icon>
                    Unidade
                </label>
                <select id="filtroUnidade" class="form-control choices-select">
                    <option value="">Indiferente</option>
                    <?php foreach ($unidades as $u): ?>
                    <option value="<?= $u['CD_UNIDADE'] ?>"><?= htmlspecialchars($u['CD_CODIGO'] . ' - ' . $u['DS_NOME']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="location-outline"></ion-icon>
                    Localidade
                </label>
                <select id="filtroLocalidade" class="form-control choices-select">
                    <option value="">Indiferente</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="speedometer-outline"></ion-icon>
                    Ponto de Medição
                </label>
                <div class="autocomplete-container">
                    <input type="text" id="filtroPontoMedicaoInput" class="form-control" placeholder="Clique para selecionar ou digite para filtrar..." autocomplete="off">
                    <input type="hidden" id="filtroPontoMedicao" value="">
                    <div id="filtroPontoMedicaoDropdown" class="autocomplete-dropdown"></div>
                    <button type="button" id="btnLimparPonto" class="btn-limpar-autocomplete" style="display: none;" title="Limpar">
                        <ion-icon name="close-circle"></ion-icon>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="construct-outline"></ion-icon>
                    Tipo da Programação
                </label>
                <div class="checkbox-group">
                    <?php foreach ($tiposProgramacao as $id => $nome): ?>
                    <label class="checkbox-item">
                        <input type="checkbox" name="tipos[]" value="<?= $id ?>">
                        <span class="checkbox-label"><?= $nome ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group span-2">
                <label class="form-label">
                    <ion-icon name="flag-outline"></ion-icon>
                    Situação da Programação
                </label>
                <div class="checkbox-group">
                    <?php foreach ($situacoes as $id => $nome): ?>
                    <label class="checkbox-item">
                        <input type="checkbox" name="situacoes[]" value="<?= $id ?>">
                        <span class="checkbox-label"><?= $nome ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Data Solicitação Inicial
                </label>
                <input type="date" id="filtroSolicitacaoInicial" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Data Solicitação Final
                </label>
                <input type="date" id="filtroSolicitacaoFinal" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Data Programação Inicial
                </label>
                <input type="date" id="filtroProgramacaoInicial" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Data Programação Final
                </label>
                <input type="date" id="filtroProgramacaoFinal" class="form-control">
            </div>

            <div class="form-group span-2">
                <label class="form-label">
                    <ion-icon name="search-outline"></ion-icon>
                    Busca Geral
                </label>
                <input type="text" id="filtroBuscaGeral" class="form-control" placeholder="Cód. ponto, responsável, solicitante, código, descrição...">
            </div>
        </div>

        <div class="filters-actions">
            <button type="button" class="btn-buscar" onclick="buscarProgramacoes()">
                <ion-icon name="search-outline"></ion-icon>
                Filtrar
            </button>
            <button type="button" class="btn-limpar" onclick="limparFiltros()">
                <ion-icon name="refresh-outline"></ion-icon>
                Limpar Filtros
            </button>
        </div>
    </div>

    <!-- Results Info -->
    <div class="results-info">
        <div class="results-count">
            <ion-icon name="list-outline"></ion-icon>
            <span id="countRegistros">Utilize os filtros acima para buscar programações</span>
        </div>
    </div>

    <!-- Tabela -->
    <div class="table-container">
        <div class="loading-overlay" id="loadingRegistros">
            <div class="loading-spinner"></div>
        </div>
        <div class="table-wrapper">
            <table class="data-table" id="tabelaDados">
                <thead>
                    <tr>
                        <th style="width: 90px;" class="sortable" data-column="CD_CODIGO">Código</th>
                        <th style="width: 200px;" class="sortable" data-column="CD_LOCALIDADE_CODIGO">Código Ponto</th>
                        <th style="width: 220px;" class="sortable" data-column="DS_PONTO_MEDICAO">Ponto de Medição</th>
                        <th style="width: 160px;" class="sortable" data-column="DS_RESPONSAVEL">Responsável</th>
                        <th style="width: 140px;" class="sortable" data-column="DT_PROGRAMACAO">Data Programação</th>
                        <th style="width: 100px;" class="sortable" data-column="ID_TIPO_PROGRAMACAO">Tipo</th>
                        <th style="width: 100px;" class="sortable" data-column="ID_SITUACAO">Situação</th>
                        <th style="width: 100px;">Ações</th>
                    </tr>
                </thead>
                <tbody id="tabelaRegistros">
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <ion-icon name="search-outline"></ion-icon>
                                </div>
                                <h3>Nenhuma pesquisa realizada</h3>
                                <p>Utilize os filtros acima e clique em "Filtrar" para consultar as programações</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <div class="pagination" id="paginacao" style="display: none;">
            <div class="pagination-info" id="paginacaoInfo">
                Exibindo 0 - 0 de 0 registros
            </div>
            <div class="pagination-controls" id="paginacaoControles">
            </div>
        </div>
    </div>
</div>

<!-- Choices.js -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<script>
    // Variáveis globais
    let paginaAtual = 1;
    const itensPorPagina = 15;
    let totalRegistros = 0;
    let ordenacao = { campo: 'DT_PROGRAMACAO', direcao: 'DESC' };
    const podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;

    // Instâncias Choices.js
    let choicesUnidade, choicesLocalidade;

    // Mapeamentos
    const tiposProgramacao = {
        1: { nome: 'Calibração', classe: 'badge-calibracao' },
        2: { nome: 'Manutenção', classe: 'badge-manutencao' }
    };

    const situacoes = {
        1: { nome: 'Prevista', classe: 'badge-prevista', icone: 'time-outline' },
        2: { nome: 'Executada', classe: 'badge-executada', icone: 'checkmark-circle-outline' },
        4: { nome: 'Cancelada', classe: 'badge-cancelada', icone: 'close-circle-outline' }
    };

    // Mapeamento de letras por tipo de medidor
    const letrasTipoMedidor = {
        1: 'M', // Macromedidor
        2: 'E', // Estação Pitométrica
        4: 'P', // Medidor Pressão
        6: 'R', // Nível Reservatório
        8: 'H'  // Hidrômetro
    };

    document.addEventListener('DOMContentLoaded', function() {
        // Inicializa Choices.js
        const configChoices = {
            searchEnabled: true,
            searchPlaceholderValue: 'Pesquisar...',
            itemSelectText: '',
            noResultsText: 'Nenhum resultado encontrado',
            noChoicesText: 'Sem opções disponíveis',
            placeholder: true,
            placeholderValue: 'Selecione...'
        };

        choicesUnidade = new Choices('#filtroUnidade', configChoices);
        choicesLocalidade = new Choices('#filtroLocalidade', { ...configChoices, searchEnabled: true });

        // Eventos de mudança
        document.getElementById('filtroUnidade').addEventListener('change', function() {
            carregarLocalidades(this.value);
        });

        // Inicializa autocomplete de Ponto de Medição
        initAutocompletePontoMedicao();

        // Ordenação nas colunas
        document.querySelectorAll('.data-table th.sortable').forEach(th => {
            th.addEventListener('click', function() {
                const coluna = this.dataset.column;
                ordenar(coluna);
            });
        });

        // Carrega dados iniciais
        buscarProgramacoes();
    });

    // Autocomplete para Ponto de Medição
    let autocompletePontoTimeout = null;
    let autocompletePontoIndex = -1;

    function initAutocompletePontoMedicao() {
        const input = document.getElementById('filtroPontoMedicaoInput');
        const hidden = document.getElementById('filtroPontoMedicao');
        const dropdown = document.getElementById('filtroPontoMedicaoDropdown');
        const btnLimpar = document.getElementById('btnLimparPonto');

        // Evento de foco - abre dropdown
        input.addEventListener('focus', function() {
            if (!hidden.value) {
                buscarPontosMedicao('');
            }
        });

        // Evento de digitação
        input.addEventListener('input', function() {
            const termo = this.value.trim();
            
            // Limpa seleção anterior
            hidden.value = '';
            btnLimpar.style.display = 'none';
            autocompletePontoIndex = -1;

            // Debounce
            clearTimeout(autocompletePontoTimeout);
            autocompletePontoTimeout = setTimeout(() => {
                buscarPontosMedicao(termo);
            }, 300);
        });

        // Navegação por teclado
        input.addEventListener('keydown', function(e) {
            const items = dropdown.querySelectorAll('.autocomplete-item');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                autocompletePontoIndex = Math.min(autocompletePontoIndex + 1, items.length - 1);
                atualizarHighlight(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                autocompletePontoIndex = Math.max(autocompletePontoIndex - 1, 0);
                atualizarHighlight(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (autocompletePontoIndex >= 0 && items[autocompletePontoIndex]) {
                    items[autocompletePontoIndex].click();
                }
            } else if (e.key === 'Escape') {
                dropdown.classList.remove('active');
            }
        });

        // Fecha ao clicar fora
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.autocomplete-container')) {
                dropdown.classList.remove('active');
            }
        });

        // Botão limpar
        btnLimpar.addEventListener('click', function() {
            input.value = '';
            hidden.value = '';
            btnLimpar.style.display = 'none';
            dropdown.classList.remove('active');
            input.focus();
        });
    }

    function atualizarHighlight(items) {
        items.forEach((item, index) => {
            if (index === autocompletePontoIndex) {
                item.classList.add('highlighted');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('highlighted');
            }
        });
    }

    function buscarPontosMedicao(termo) {
        const dropdown = document.getElementById('filtroPontoMedicaoDropdown');
        const cdUnidade = document.getElementById('filtroUnidade').value;
        const cdLocalidade = document.getElementById('filtroLocalidade').value;

        dropdown.innerHTML = '<div class="autocomplete-loading">Buscando...</div>';
        dropdown.classList.add('active');

        const params = new URLSearchParams({ busca: termo });
        if (cdUnidade) params.append('cd_unidade', cdUnidade);
        if (cdLocalidade) params.append('cd_localidade', cdLocalidade);

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
                        item.addEventListener('click', function() {
                            selecionarPontoMedicao(this.dataset.value, this.dataset.label);
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

    function selecionarPontoMedicao(value, label) {
        const input = document.getElementById('filtroPontoMedicaoInput');
        const hidden = document.getElementById('filtroPontoMedicao');
        const dropdown = document.getElementById('filtroPontoMedicaoDropdown');
        const btnLimpar = document.getElementById('btnLimparPonto');

        input.value = label;
        hidden.value = value;
        dropdown.classList.remove('active');
        btnLimpar.style.display = 'flex';
    }

    function carregarLocalidades(cdUnidade) {
        choicesLocalidade.clearStore();
        choicesLocalidade.setChoices([{ value: '', label: 'Indiferente', selected: true }], 'value', 'label', true);

        if (!cdUnidade) return;

        fetch(`bd/pontoMedicao/getLocalidades.php?cd_unidade=${cdUnidade}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    const choices = [{ value: '', label: 'Indiferente', selected: true }];
                    data.data.forEach(item => {
                        choices.push({ value: item.CD_CHAVE, label: `${item.CD_LOCALIDADE} - ${item.DS_NOME}` });
                    });
                    choicesLocalidade.setChoices(choices, 'value', 'label', true);
                }
            });
    }

    function buscarProgramacoes(pagina = 1) {
        paginaAtual = pagina;
        document.getElementById('loadingRegistros').classList.add('active');

        // Coletar tipos selecionados
        const tiposSelecionados = [];
        document.querySelectorAll('input[name="tipos[]"]:checked').forEach(cb => {
            tiposSelecionados.push(cb.value);
        });

        // Coletar situações selecionadas
        const situacoesSelecionadas = [];
        document.querySelectorAll('input[name="situacoes[]"]:checked').forEach(cb => {
            situacoesSelecionadas.push(cb.value);
        });

        const params = new URLSearchParams({
            cd_unidade: document.getElementById('filtroUnidade').value,
            cd_localidade: document.getElementById('filtroLocalidade').value,
            cd_ponto_medicao: document.getElementById('filtroPontoMedicao').value,
            busca_geral: document.getElementById('filtroBuscaGeral').value,
            tipos: tiposSelecionados.join(','),
            situacoes: situacoesSelecionadas.join(','),
            solicitacao_inicial: document.getElementById('filtroSolicitacaoInicial').value,
            solicitacao_final: document.getElementById('filtroSolicitacaoFinal').value,
            programacao_inicial: document.getElementById('filtroProgramacaoInicial').value,
            programacao_final: document.getElementById('filtroProgramacaoFinal').value,
            pagina: pagina,
            itens_por_pagina: itensPorPagina,
            ordem_campo: ordenacao.campo,
            ordem_direcao: ordenacao.direcao
        });

        fetch(`bd/programacaoManutencao/listarProgramacoes.php?${params}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingRegistros').classList.remove('active');
                
                if (data.success) {
                    totalRegistros = data.total;
                    renderizarTabela(data.data);
                    renderizarPaginacao();
                    document.getElementById('countRegistros').innerHTML = `<strong>${totalRegistros}</strong> registros encontrados`;
                } else {
                    showToast(data.message || 'Erro ao buscar dados', 'erro');
                }
            })
            .catch(error => {
                document.getElementById('loadingRegistros').classList.remove('active');
                showToast('Erro ao comunicar com o servidor', 'erro');
                console.error(error);
            });
    }

    function renderizarTabela(dados) {
        const tbody = document.getElementById('tabelaRegistros');

        if (!dados || dados.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <ion-icon name="calendar-outline"></ion-icon>
                            </div>
                            <h3>Nenhuma programação encontrada</h3>
                            <p>Ajuste os filtros ou cadastre uma nova programação</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        dados.forEach(item => {
            // Formato: CD_CODIGO-CD_ANO/ID_TIPO_PROGRAMACAO
            const codigo = String(item.CD_CODIGO).padStart(3, '0') + '-' + item.CD_ANO + '/' + item.ID_TIPO_PROGRAMACAO;
            const tipo = tiposProgramacao[item.ID_TIPO_PROGRAMACAO] || { nome: 'N/A', classe: '' };
            const situacao = situacoes[item.ID_SITUACAO] || { nome: 'N/A', classe: '', icone: 'help-outline' };
            const dtProgramacao = formatarData(item.DT_PROGRAMACAO);
            
            // Código formatado do ponto: LOCALIDADE-ID_PONTO-LETRA-CD_UNIDADE
            const letraTipo = letrasTipoMedidor[item.ID_TIPO_MEDIDOR] || 'X';
            const codigoPonto = item.CD_LOCALIDADE_CODIGO + '-' + 
                               String(item.CD_PONTO_MEDICAO).padStart(6, '0') + '-' + 
                               letraTipo + '-' + 
                               item.CD_UNIDADE;

            html += `
                <tr>
                    <td class="code">${codigo}</td>
                    <td class="code" title="${codigoPonto}">${codigoPonto}</td>
                    <td class="name truncate" title="${item.DS_PONTO_MEDICAO || ''}">${item.DS_PONTO_MEDICAO || '-'}</td>
                    <td class="truncate" title="${item.DS_RESPONSAVEL || ''}">${item.DS_RESPONSAVEL || '-'}</td>
                    <td>${dtProgramacao}</td>
                    <td><span class="badge ${tipo.classe}">${tipo.nome}</span></td>
                    <td>
                        <span class="badge ${situacao.classe}">
                            <ion-icon name="${situacao.icone}"></ion-icon>
                            ${situacao.nome}
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="programacaoManutencaoView.php?id=${item.CD_CHAVE}" class="btn-action" title="Visualizar">
                                <ion-icon name="eye-outline"></ion-icon>
                            </a>
                            ${podeEditar ? `
                            <a href="programacaoManutencaoForm.php?id=${item.CD_CHAVE}" class="btn-action" title="Editar">
                                <ion-icon name="create-outline"></ion-icon>
                            </a>
                            <button type="button" class="btn-action delete" title="Excluir" onclick="confirmarExclusao(${item.CD_CHAVE}, '${codigo}')">
                                <ion-icon name="trash-outline"></ion-icon>
                            </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    function renderizarPaginacao() {
        const totalPaginas = Math.ceil(totalRegistros / itensPorPagina);
        const inicio = totalRegistros > 0 ? (paginaAtual - 1) * itensPorPagina + 1 : 0;
        const fim = Math.min(paginaAtual * itensPorPagina, totalRegistros);

        if (totalRegistros === 0) {
            document.getElementById('paginacao').style.display = 'none';
            return;
        }

        document.getElementById('paginacao').style.display = 'flex';
        document.getElementById('paginacaoInfo').textContent = `Exibindo ${inicio}-${fim} de ${totalRegistros}`;

        if (totalPaginas <= 1) {
            document.getElementById('paginacaoControles').innerHTML = '';
            return;
        }

        let html = '';
        
        // Botão Anterior
        html += `<button class="btn-page ${paginaAtual === 1 ? 'disabled' : ''}" onclick="buscarProgramacoes(${paginaAtual - 1})" ${paginaAtual === 1 ? 'disabled' : ''}>
            <ion-icon name="chevron-back-outline"></ion-icon>
        </button>`;

        // Páginas
        const maxPaginas = 5;
        let startPage = Math.max(1, paginaAtual - Math.floor(maxPaginas / 2));
        let endPage = Math.min(totalPaginas, startPage + maxPaginas - 1);
        
        if (endPage - startPage < maxPaginas - 1) {
            startPage = Math.max(1, endPage - maxPaginas + 1);
        }

        if (startPage > 1) {
            html += `<button class="btn-page" onclick="buscarProgramacoes(1)">1</button>`;
            if (startPage > 2) {
                html += `<span style="padding: 0 8px; color: #94a3b8;">...</span>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="btn-page ${i === paginaAtual ? 'active' : ''}" onclick="buscarProgramacoes(${i})">${i}</button>`;
        }

        if (endPage < totalPaginas) {
            if (endPage < totalPaginas - 1) {
                html += `<span style="padding: 0 8px; color: #94a3b8;">...</span>`;
            }
            html += `<button class="btn-page" onclick="buscarProgramacoes(${totalPaginas})">${totalPaginas}</button>`;
        }

        // Botão Próximo
        html += `<button class="btn-page ${paginaAtual === totalPaginas ? 'disabled' : ''}" onclick="buscarProgramacoes(${paginaAtual + 1})" ${paginaAtual === totalPaginas ? 'disabled' : ''}>
            <ion-icon name="chevron-forward-outline"></ion-icon>
        </button>`;

        document.getElementById('paginacaoControles').innerHTML = html;
    }

    function ordenar(campo) {
        // Remove classes de todas as colunas
        document.querySelectorAll('.data-table th.sortable').forEach(th => {
            th.classList.remove('asc', 'desc');
        });

        // Define nova ordenação
        if (ordenacao.campo === campo) {
            ordenacao.direcao = ordenacao.direcao === 'ASC' ? 'DESC' : 'ASC';
        } else {
            ordenacao.campo = campo;
            ordenacao.direcao = 'ASC';
        }

        // Adiciona classe na coluna atual
        const th = document.querySelector(`.data-table th[data-column="${campo}"]`);
        if (th) {
            th.classList.add(ordenacao.direcao.toLowerCase());
        }

        buscarProgramacoes(1);
    }

    function limparFiltros() {
        choicesUnidade.setChoiceByValue('');
        choicesLocalidade.clearStore();
        choicesLocalidade.setChoices([{ value: '', label: 'Indiferente', selected: true }], 'value', 'label', true);
        
        // Limpar autocomplete de Ponto de Medição
        document.getElementById('filtroPontoMedicaoInput').value = '';
        document.getElementById('filtroPontoMedicao').value = '';
        document.getElementById('filtroPontoMedicaoDropdown').classList.remove('active');
        document.getElementById('btnLimparPonto').style.display = 'none';

        // Limpar busca geral
        document.getElementById('filtroBuscaGeral').value = '';
        
        // Limpar checkboxes
        document.querySelectorAll('input[name="tipos[]"]').forEach(cb => cb.checked = false);
        document.querySelectorAll('input[name="situacoes[]"]').forEach(cb => cb.checked = false);
        
        // Limpar datas
        document.getElementById('filtroSolicitacaoInicial').value = '';
        document.getElementById('filtroSolicitacaoFinal').value = '';
        document.getElementById('filtroProgramacaoInicial').value = '';
        document.getElementById('filtroProgramacaoFinal').value = '';

        buscarProgramacoes(1);
    }

    function confirmarExclusao(cdChave, codigo) {
        if (confirm(`Deseja realmente excluir a programação ${codigo}?`)) {
            fetch('bd/programacaoManutencao/excluirProgramacao.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `cd_chave=${cdChave}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'sucesso');
                    buscarProgramacoes(paginaAtual);
                } else {
                    showToast(data.message || 'Erro ao excluir', 'erro');
                }
            })
            .catch(error => {
                showToast('Erro ao comunicar com o servidor', 'erro');
            });
        }
    }

    function formatarData(dataStr) {
        if (!dataStr) return '-';
        const data = new Date(dataStr);
        return data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }
</script>

<?php include_once 'includes/footer.inc.php'; ?>
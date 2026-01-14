<?php

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão para acessar Ponto de Medição (busca por nome na tabela FUNCIONALIDADE)
exigePermissaoTela('Cadastro de Ponto de Medição', ACESSO_LEITURA);

// Permissão do usuário para este módulo
$podeEditar = podeEditarTela('Cadastro de Ponto de Medição');

// Buscar Unidades
$sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME, CD_CODIGO FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

// Tipos de Medidor (fixo)
$tiposMedidor = [
    ['value' => '', 'text' => 'Todos os Tipos'],
    ['value' => '1', 'text' => 'M - Macromedidor'],
    ['value' => '2', 'text' => 'E - Estação Pitométrica'],
    ['value' => '4', 'text' => 'P - Medidor Pressão'],
    ['value' => '8', 'text' => 'H - Hidrômetro'],
    ['value' => '6', 'text' => 'R - Nível Reservatório'],
];

// Tipos de Leitura (fixo)
$tiposLeitura = [
    ['value' => '', 'text' => 'Todos os Tipos'],
    ['value' => '2', 'text' => 'Manual'],
    ['value' => '4', 'text' => 'Planilha'],
    ['value' => '8', 'text' => 'Integração CCO'],
    ['value' => '6', 'text' => 'Integração CesanLims'],
];
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
    /* ============================================
       Reset e Box Sizing
       ============================================ */
    *, *::before, *::after {
        box-sizing: border-box;
    }

    /* ============================================
       Page Container
       ============================================ */
    .page-container {
        padding: 24px;
        max-width: 1800px;
        margin: 0 auto;
        overflow-x: hidden;
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
        overflow: hidden;
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

    .btn-novo {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        background: white;
        color: #1e3a5f;
        border: none;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        max-width: 100%;
    }

    .btn-novo:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-novo ion-icon {
        font-size: 18px;
    }

    /* ============================================
       Filters Card
       ============================================ */
    .filters-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        overflow: hidden;
    }

    .filters-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid #f1f5f9;
    }

    .filters-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        font-weight: 600;
        color: #334155;
    }

    .filters-title ion-icon {
        font-size: 18px;
        color: #64748b;
    }

    .btn-clear-filters {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        background: #f8fafc;
        color: #64748b;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-clear-filters:hover {
        background: #f1f5f9;
        color: #475569;
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        width: 100%;
    }

    /* ============================================
       Form Controls
       ============================================ */
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-width: 0; /* Permite que flexbox encolha */
        width: 100%;
    }

    .form-group-span-2 {
        grid-column: span 2;
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
        max-width: 100%;
        padding: 12px 14px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-family: inherit;
        font-size: 13px;
        color: #334155;
        transition: all 0.2s ease;
    }

    .form-control:focus {
        outline: none;
        background-color: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Select2 Custom Styling */
    .select2-container--default .select2-selection--single {
        height: 44px;
        padding: 8px 14px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        transition: all 0.2s ease;
    }

    .select2-container--default.select2-container--focus .select2-selection--single,
    .select2-container--default.select2-container--open .select2-selection--single {
        background-color: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #334155;
        font-size: 13px;
        line-height: 26px;
        padding-left: 0;
    }

    .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #94a3b8;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px;
        right: 10px;
    }

    .select2-dropdown {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        margin-top: 4px;
        overflow: hidden;
    }

    .select2-container--default .select2-search--dropdown .select2-search__field {
        padding: 10px 14px;
        border: none;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }

    .select2-container--default .select2-results__option {
        padding: 10px 14px;
        font-size: 13px;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #eff6ff;
        color: #3b82f6;
    }

    .select2-container--default .select2-results__option[aria-selected=true] {
        background-color: #3b82f6;
        color: white;
    }

    /* ============================================
       Input Search com ícone
       ============================================ */
    .input-search-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        width: 100%;
    }

    .input-search-icon {
        position: absolute;
        left: 14px;
        font-size: 18px;
        color: #94a3b8;
        pointer-events: none;
        transition: color 0.2s ease;
    }

    .input-search-wrapper:focus-within .input-search-icon {
        color: #3b82f6;
    }

    .input-search {
        padding-left: 44px !important;
        padding-right: 40px !important;
    }

    .btn-search-clear {
        position: absolute;
        right: 10px;
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        padding: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: color 0.2s ease;
    }

    .btn-search-clear:hover {
        color: #64748b;
    }

    .btn-search-clear ion-icon {
        font-size: 18px;
    }

    /* ============================================
       Radio Button Group
       ============================================ */
    .radio-group {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        width: 100%;
    }

    .radio-item {
        display: flex;
        align-items: center;
        cursor: pointer;
        flex: 1;
        min-width: 0;
    }

    .radio-item input[type="radio"] {
        display: none;
    }

    .radio-label {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        padding: 10px 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 500;
        color: #64748b;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .radio-item:hover .radio-label {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    .radio-item input[type="radio"]:checked + .radio-label {
        background: #eff6ff;
        border-color: #3b82f6;
        color: #3b82f6;
        font-weight: 600;
    }

    .radio-item input[type="radio"]:checked + .radio-label.ativo {
        background: #dcfce7;
        border-color: #22c55e;
        color: #15803d;
    }

    .radio-item input[type="radio"]:checked + .radio-label.inativo {
        background: #fee2e2;
        border-color: #ef4444;
        color: #b91c1c;
    }

    /* ============================================
       Results Info
       ============================================ */
    .results-info {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .results-count {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: #64748b;
    }

    .results-count strong {
        color: #334155;
        font-weight: 600;
    }

    /* ============================================
       Data Table with Sorting
       ============================================ */
    .table-container {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        position: relative;
    }

    .table-wrapper {
        overflow-x: auto;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1200px;
    }

    .data-table thead {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        position: sticky;
        top: 0;
        z-index: 5;
    }

    .data-table th {
        padding: 14px 12px;
        text-align: left;
        font-size: 10px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
        cursor: pointer;
        user-select: none;
        transition: background 0.15s ease;
        position: relative;
    }

    .data-table th:hover {
        background: #f1f5f9;
    }

    .data-table th.no-sort {
        cursor: default;
    }

    .data-table th.no-sort:hover {
        background: #f8fafc;
    }

    /* Sort Indicator */
    .sort-indicator {
        display: inline-flex;
        flex-direction: column;
        margin-left: 6px;
        opacity: 0.3;
        transition: opacity 0.2s ease;
    }

    .data-table th:hover .sort-indicator {
        opacity: 0.6;
    }

    .sort-indicator ion-icon {
        font-size: 10px;
        line-height: 1;
    }

    .sort-indicator .sort-asc,
    .sort-indicator .sort-desc {
        height: 8px;
        display: flex;
        align-items: center;
    }

    .data-table th.sorted-asc .sort-indicator,
    .data-table th.sorted-desc .sort-indicator {
        opacity: 1;
    }

    .data-table th.sorted-asc .sort-indicator .sort-asc ion-icon,
    .data-table th.sorted-desc .sort-indicator .sort-desc ion-icon {
        color: #3b82f6;
    }

    .data-table th.sorted-asc .sort-indicator .sort-desc,
    .data-table th.sorted-desc .sort-indicator .sort-asc {
        opacity: 0.2;
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
        padding: 12px;
        font-size: 12px;
        color: #475569;
        vertical-align: middle;
    }

    .data-table td.code {
        font-family: 'SF Mono', Monaco, 'Consolas', monospace;
        font-size: 11px;
        color: #3b82f6;
        font-weight: 600;
    }

    .data-table td.name {
        font-weight: 500;
        color: #1e293b;
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .data-table td.truncate {
        max-width: 180px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Badges */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 600;
        white-space: nowrap;
    }

    .badge-tipo {
        background: #eff6ff;
        color: #3b82f6;
    }

    .badge-tipo.macromedidor { background: #dcfce7; color: #15803d; }
    .badge-tipo.estacao { background: #fef3c7; color: #b45309; }
    .badge-tipo.pressao { background: #fee2e2; color: #b91c1c; }
    .badge-tipo.hidrometro { background: #e0e7ff; color: #4338ca; }
    .badge-tipo.reservatorio { background: #cffafe; color: #0e7490; }

    .badge-leitura {
        background: #f1f5f9;
        color: #475569;
    }

    .badge-status {
        padding: 4px 10px;
    }

    .badge-status.ativo {
        background: #dcfce7;
        color: #15803d;
    }

    .badge-status.inativo {
        background: #fee2e2;
        color: #b91c1c;
    }

    /* Actions */
    .table-actions {
        display: flex;
        gap: 4px;
    }

    .btn-action {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s ease;
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

    .btn-action.activate {
        color: #22c55e;
    }

    .btn-action.activate:hover {
        background: #f0fdf4;
        border-color: #22c55e;
        color: #22c55e;
    }

    .btn-action ion-icon {
        font-size: 14px;
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
        align-items: center;
        justify-content: center;
        z-index: 10;
        border-radius: 16px;
    }

    .loading-overlay.active {
        display: flex;
    }

    .loading-spinner {
        width: 40px;
        height: 40px;
        border: 3px solid #e2e8f0;
        border-top-color: #3b82f6;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* ============================================
       Paginação
       ============================================ */
    .pagination-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
        margin-top: 20px;
        padding: 16px 20px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
    }

    .pagination {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .btn-page {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 10px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        color: #475569;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-page:hover:not(.disabled):not(.active) {
        background: #eff6ff;
        border-color: #3b82f6;
        color: #3b82f6;
    }

    .btn-page.active {
        background: #3b82f6;
        border-color: #3b82f6;
        color: white;
        font-weight: 600;
    }

    .btn-page.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .btn-page ion-icon {
        font-size: 16px;
    }

    .page-ellipsis {
        color: #94a3b8;
        padding: 0 4px;
    }

    .page-info {
        font-size: 13px;
        color: #64748b;
    }

    /* ============================================
       Responsive
       ============================================ */
    @media (max-width: 1200px) {
        .filters-grid {
            grid-template-columns: repeat(2, 1fr) !important;
        }

        .form-group-span-2 {
            grid-column: span 2;
        }
    }

    @media (max-width: 768px) {
        .page-container {
            padding: 12px;
        }

        .page-header {
            padding: 16px;
            border-radius: 12px;
        }

        .page-header-content {
            flex-direction: column;
            align-items: stretch;
            gap: 16px;
        }

        .page-header-info {
            flex-direction: column;
            text-align: center;
            gap: 12px;
        }

        .page-header-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto;
        }

        .page-header h1 {
            font-size: 18px;
        }

        .page-header-subtitle {
            font-size: 12px;
        }

        .btn-novo {
            width: 100%;
            justify-content: center;
            padding: 12px 20px;
            box-sizing: border-box;
        }

        .filters-card {
            padding: 16px;
            border-radius: 12px;
        }

        .filters-header {
            flex-direction: column;
            gap: 12px;
            align-items: stretch;
        }

        .filters-title {
            justify-content: center;
        }

        .btn-clear-filters {
            width: 100%;
            justify-content: center;
        }

        .filters-grid {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 16px;
        }

        .filters-grid .form-group {
            grid-column: span 1 !important;
            width: 100% !important;
        }

        .filters-grid .form-group-span-2 {
            grid-column: span 1 !important;
        }

        .form-group[style*="grid-column: span 2"] {
            grid-column: span 1 !important;
        }

        .form-control {
            width: 100%;
            box-sizing: border-box;
        }

        .radio-group {
            display: flex !important;
            flex-direction: column !important;
            width: 100% !important;
            gap: 8px;
        }

        .radio-item {
            width: 100% !important;
        }

        .radio-label {
            width: 100% !important;
            text-align: center;
            padding: 10px 16px;
            box-sizing: border-box;
        }

        .table-container {
            border-radius: 12px;
        }

        .results-info {
            flex-direction: column;
            gap: 8px;
            text-align: center;
        }

        /* Select2 responsivo */
        .select2-container {
            width: 100% !important;
            box-sizing: border-box !important;
        }

        /* Input search wrapper */
        .input-search-wrapper {
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        .page-container {
            padding: 8px;
        }

        .page-header {
            padding: 14px;
        }

        .page-header h1 {
            font-size: 16px;
        }

        .filters-card {
            padding: 12px;
        }

        .data-table th,
        .data-table td {
            padding: 10px 8px;
            font-size: 12px;
        }
    }
</style>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="pin-outline"></ion-icon>
                </div>
                <div>
                    <h1>Ponto de Medição</h1>
                    <p class="page-header-subtitle">Gerencie os pontos de medição do sistema</p>
                </div>
            </div>
            <?php if ($podeEditar): ?>
            <a href="pontoMedicaoForm.php" class="btn-novo">
                <ion-icon name="add-outline"></ion-icon>
                Novo Ponto
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
        <div class="filters-header">
            <div class="filters-title">
                <ion-icon name="filter-outline"></ion-icon>
                Filtros de Pesquisa
            </div>
            <button type="button" class="btn-clear-filters" onclick="limparFiltros()">
                <ion-icon name="refresh-outline"></ion-icon>
                Limpar Filtros
            </button>
        </div>

        <div class="filters-grid">
            <!-- Unidade -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="business-outline"></ion-icon>
                    Unidade
                </label>
                <select id="selectUnidade" class="form-control select2-unidade">
                    <option value="">Todas as Unidades</option>
                    <?php foreach ($unidades as $unidade): ?>
                        <option value="<?= $unidade['CD_UNIDADE'] ?>">
                            <?= htmlspecialchars($unidade['CD_CODIGO'] . ' - ' . $unidade['DS_NOME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Localidade -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="location-outline"></ion-icon>
                    Localidade
                </label>
                <select id="selectLocalidade" class="form-control select2-localidade" disabled>
                    <option value="">Selecione uma Unidade primeiro</option>
                </select>
            </div>

            <!-- Tipo Medidor -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="speedometer-outline"></ion-icon>
                    Tipo Medidor
                </label>
                <select id="selectTipoMedidor" class="form-control select2-default">
                    <?php foreach ($tiposMedidor as $tipo): ?>
                        <option value="<?= $tipo['value'] ?>"><?= $tipo['text'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Tipo Leitura -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="reader-outline"></ion-icon>
                    Tipo de Leitura
                </label>
                <select id="selectTipoLeitura" class="form-control select2-default">
                    <?php foreach ($tiposLeitura as $tipo): ?>
                        <option value="<?= $tipo['value'] ?>"><?= $tipo['text'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Campo de Busca Geral -->
            <div class="form-group form-group-span-2">
                <label class="form-label">
                    <ion-icon name="search-outline"></ion-icon>
                    Busca Geral
                </label>
                <div class="input-search-wrapper">
                    <ion-icon name="search-outline" class="input-search-icon"></ion-icon>
                    <input type="text" 
                           id="inputBusca" 
                           class="form-control input-search" 
                           placeholder="Buscar por código, nome, TAG, localização, observação...">
                    <button type="button" class="btn-search-clear" id="btnLimparBusca" style="display: none;">
                        <ion-icon name="close-circle"></ion-icon>
                    </button>
                </div>
            </div>

            <!-- Radio Button Status -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="toggle-outline"></ion-icon>
                    Status
                </label>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="status" value="" checked>
                        <span class="radio-label">Todos</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="status" value="ativo">
                        <span class="radio-label ativo">Ativos</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="status" value="inativo">
                        <span class="radio-label inativo">Inativos</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Info -->
    <div class="results-info">
        <div class="results-count">
            <ion-icon name="list-outline"></ion-icon>
            <span><strong id="totalRegistros">0</strong> registros encontrados</span>
        </div>
    </div>

    <!-- Data Table -->
    <div class="table-container">
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-spinner"></div>
        </div>

        <div class="table-wrapper">
            <table class="data-table" id="dataTable">
                <thead>
                    <tr>
                        <th data-sort="UNIDADE">
                            Unidade
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="LOCALIDADE">
                            Localidade
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="CODIGO">
                            Código
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="DS_NOME">
                            Nome
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="CODIGO_TAG">
                            Código TAG
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="ID_TIPO_MEDIDOR">
                            Tipo Medidor
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="ID_TIPO_LEITURA">
                            Tipo Leitura
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="ATIVO">
                            Status
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th class="no-sort">Ações</th>
                    </tr>
                </thead>
                <tbody id="tabelaResultados">
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <ion-icon name="filter-outline"></ion-icon>
                                </div>
                                <h3>Preencha ao menos um filtro</h3>
                                <p>Selecione uma unidade, localidade, tipo ou digite algo na busca geral</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginação -->
    <div class="pagination-container" id="paginacao"></div>
</div>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    // ============================================
    // Variáveis Globais
    // ============================================
    let dadosTabela = [];
    let sortColumn = null;
    let sortDirection = 'asc';
    let paginaAtual = 1;
    let totalPaginas = 0;
    let totalRegistros = 0;

    // Permissão do usuário (vindo do PHP)
    const permissoes = {
        podeEditar: <?= $podeEditar ? 'true' : 'false' ?>
    };

    // ============================================
    // Mapeamentos de Tipos
    // ============================================
    const tiposMedidorMap = {
        '1': { text: 'Macromedidor', class: 'macromedidor', abbr: 'M' },
        '2': { text: 'Est. Pitométrica', class: 'estacao', abbr: 'E' },
        '4': { text: 'Med. Pressão', class: 'pressao', abbr: 'P' },
        '6': { text: 'Nív. Reservatório', class: 'reservatorio', abbr: 'R' },
        '8': { text: 'Hidrômetro', class: 'hidrometro', abbr: 'H' }
    };

    const tiposLeituraMap = {
        '2': 'Manual',
        '4': 'Planilha',
        '6': 'Int. CesanLims',
        '8': 'Int. CCO'
    };

    // ============================================
    // Inicialização Select2
    // ============================================
    $(document).ready(function() {
        // Select2 para Unidade
        $('.select2-unidade').select2({
            placeholder: 'Digite para buscar...',
            allowClear: true,
            language: {
                noResults: function() { return "Nenhuma unidade encontrada"; },
                searching: function() { return "Buscando..."; }
            }
        });

        // Select2 para Localidade
        $('.select2-localidade').select2({
            placeholder: 'Selecione uma Unidade primeiro',
            allowClear: true,
            language: {
                noResults: function() { return "Nenhuma localidade encontrada"; }
            }
        });

        // Select2 para dropdowns simples
        $('.select2-default').select2({
            minimumResultsForSearch: Infinity
        });

        // Events
        $('#selectUnidade').on('change', function() {
            carregarLocalidades($(this).val());
            buscarPontosMedicao(1); // Reset para página 1
        });

        $('#selectLocalidade, #selectTipoMedidor, #selectTipoLeitura').on('change', function() {
            buscarPontosMedicao(1); // Reset para página 1
        });

        // Event: Campo de busca com debounce
        let buscaTimeout;
        $('#inputBusca').on('input', function() {
            const valor = $(this).val();
            
            // Mostra/esconde botão limpar
            $('#btnLimparBusca').toggle(valor.length > 0);
            
            // Debounce de 400ms
            clearTimeout(buscaTimeout);
            buscaTimeout = setTimeout(function() {
                buscarPontosMedicao(1); // Reset para página 1
            }, 400);
        });

        // Event: Limpar campo de busca
        $('#btnLimparBusca').on('click', function() {
            $('#inputBusca').val('').focus();
            $(this).hide();
            buscarPontosMedicao(1); // Reset para página 1
        });

        // Event: Radio buttons de status
        $('input[name="status"]').on('change', function() {
            buscarPontosMedicao(1); // Reset para página 1
        });

        // Inicializa ordenação
        initSorting();

        // Não carrega dados automaticamente - aguarda preenchimento de filtro
    });

    // ============================================
    // Sistema de Ordenação
    // ============================================
    function initSorting() {
        document.querySelectorAll('.data-table th[data-sort]').forEach(th => {
            th.addEventListener('click', function() {
                const column = this.getAttribute('data-sort');
                
                // Remove classes de outros headers
                document.querySelectorAll('.data-table th').forEach(h => {
                    h.classList.remove('sorted-asc', 'sorted-desc');
                });

                // Alterna direção se mesma coluna
                if (sortColumn === column) {
                    sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    sortColumn = column;
                    sortDirection = 'asc';
                }

                // Adiciona classe ao header atual
                this.classList.add(sortDirection === 'asc' ? 'sorted-asc' : 'sorted-desc');

                // Reordena e renderiza
                sortData();
                renderizarTabela(dadosTabela);
            });
        });
    }

    function sortData() {
        if (!sortColumn || dadosTabela.length === 0) return;

        dadosTabela.sort((a, b) => {
            let valA = a[sortColumn] || '';
            let valB = b[sortColumn] || '';

            // Converte para string para comparação
            valA = String(valA).toLowerCase();
            valB = String(valB).toLowerCase();

            // Tenta comparar como número se possível
            const numA = parseFloat(valA);
            const numB = parseFloat(valB);
            
            if (!isNaN(numA) && !isNaN(numB)) {
                return sortDirection === 'asc' ? numA - numB : numB - numA;
            }

            // Comparação de string
            if (valA < valB) return sortDirection === 'asc' ? -1 : 1;
            if (valA > valB) return sortDirection === 'asc' ? 1 : -1;
            return 0;
        });
    }

    // ============================================
    // Carregar Localidades via AJAX
    // ============================================
    function carregarLocalidades(cdUnidade) {
        const select = $('#selectLocalidade');
        
        if (!cdUnidade) {
            select.prop('disabled', true);
            select.html('<option value="">Selecione uma Unidade primeiro</option>');
            select.trigger('change');
            return;
        }

        select.prop('disabled', true);
        select.html('<option value="">Carregando...</option>');

        $.ajax({
            url: 'bd/pontoMedicao/getLocalidades.php',
            type: 'GET',
            data: { cd_unidade: cdUnidade },
            dataType: 'json',
            success: function(response) {
                let options = '<option value="">Todas as Localidades</option>';
                
                if (response.success && response.data.length > 0) {
                    response.data.forEach(function(item) {
                        // Usa CD_CHAVE como value (é a FK em PONTO_MEDICAO.CD_LOCALIDADE)
                        options += `<option value="${item.CD_CHAVE}">${item.CD_LOCALIDADE} - ${item.DS_NOME}</option>`;
                    });
                }
                
                select.html(options);
                select.prop('disabled', false);
                select.trigger('change.select2');
            },
            error: function() {
                select.html('<option value="">Erro ao carregar</option>');
                showToast('Erro ao carregar localidades', 'erro');
            }
        });
    }

    // ============================================
    // Buscar Pontos de Medição via AJAX
    // ============================================
    function buscarPontosMedicao(pagina = 1) {
        const cdUnidade = $('#selectUnidade').val() || '';
        const cdLocalidade = $('#selectLocalidade').val() || '';
        const tipoMedidor = $('#selectTipoMedidor').val() || '';
        const tipoLeitura = $('#selectTipoLeitura').val() || '';
        const busca = document.getElementById('inputBusca').value.trim();
        const status = $('input[name="status"]:checked').val() || '';

        // Verifica se pelo menos um filtro foi preenchido
        const temFiltro = cdUnidade !== '' || cdLocalidade !== '' || tipoMedidor !== '' || 
                          tipoLeitura !== '' || busca !== '' || status !== '';

        if (!temFiltro) {
            dadosTabela = [];
            paginaAtual = 1;
            totalPaginas = 0;
            totalRegistros = 0;
            $('#tabelaResultados').html(`
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <ion-icon name="filter-outline"></ion-icon>
                            </div>
                            <h3>Preencha ao menos um filtro</h3>
                            <p>Selecione uma unidade, localidade, tipo ou digite algo na busca geral</p>
                        </div>
                    </td>
                </tr>
            `);
            $('#totalRegistros').text('0');
            renderizarPaginacao();
            return;
        }

        paginaAtual = pagina;
        $('#loadingOverlay').addClass('active');

        $.ajax({
            url: 'bd/pontoMedicao/getPontosMedicao.php',
            type: 'GET',
            data: {
                cd_unidade: cdUnidade,
                cd_localidade: cdLocalidade,
                tipo_medidor: tipoMedidor,
                tipo_leitura: tipoLeitura,
                busca: busca,
                status: status,
                pagina: pagina
            },
            dataType: 'json',
            success: function(response) {
                $('#loadingOverlay').removeClass('active');
                
                if (response.success && response.data && response.data.length > 0) {
                    dadosTabela = response.data;
                    totalRegistros = response.total;
                    totalPaginas = response.totalPaginas;
                    
                    if (sortColumn) {
                        sortData();
                    }
                    
                    renderizarTabela(dadosTabela);
                    $('#totalRegistros').text(totalRegistros);
                    renderizarPaginacao();
                } else {
                    dadosTabela = [];
                    totalRegistros = 0;
                    totalPaginas = 0;
                    
                    const mensagem = response.message || 'Nenhum registro encontrado';
                    $('#tabelaResultados').html(`
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <ion-icon name="file-tray-outline"></ion-icon>
                                    </div>
                                    <h3>${mensagem}</h3>
                                    <p>Tente ajustar os filtros de pesquisa</p>
                                </div>
                            </td>
                        </tr>
                    `);
                    $('#totalRegistros').text('0');
                    renderizarPaginacao();
                }
            },
            error: function(xhr, status, error) {
                $('#loadingOverlay').removeClass('active');
                console.error('Erro AJAX:', { xhr, status, error });
                showToast('Erro ao buscar pontos de medição', 'erro');
            }
        });
    }

    // ============================================
    // Renderizar Paginação
    // ============================================
    function renderizarPaginacao() {
        const container = $('#paginacao');
        
        if (totalPaginas <= 1) {
            container.html('');
            return;
        }

        let html = '<div class="pagination">';
        
        // Botão Anterior
        html += `<button type="button" class="btn-page ${paginaAtual === 1 ? 'disabled' : ''}" 
                  onclick="irParaPagina(${paginaAtual - 1})" ${paginaAtual === 1 ? 'disabled' : ''}>
                    <ion-icon name="chevron-back-outline"></ion-icon>
                 </button>`;

        // Números das páginas
        const maxPaginas = 5;
        let inicio = Math.max(1, paginaAtual - Math.floor(maxPaginas / 2));
        let fim = Math.min(totalPaginas, inicio + maxPaginas - 1);
        
        if (fim - inicio + 1 < maxPaginas) {
            inicio = Math.max(1, fim - maxPaginas + 1);
        }

        if (inicio > 1) {
            html += `<button type="button" class="btn-page" onclick="irParaPagina(1)">1</button>`;
            if (inicio > 2) {
                html += `<span class="page-ellipsis">...</span>`;
            }
        }

        for (let i = inicio; i <= fim; i++) {
            html += `<button type="button" class="btn-page ${i === paginaAtual ? 'active' : ''}" 
                      onclick="irParaPagina(${i})">${i}</button>`;
        }

        if (fim < totalPaginas) {
            if (fim < totalPaginas - 1) {
                html += `<span class="page-ellipsis">...</span>`;
            }
            html += `<button type="button" class="btn-page" onclick="irParaPagina(${totalPaginas})">${totalPaginas}</button>`;
        }

        // Botão Próximo
        html += `<button type="button" class="btn-page ${paginaAtual === totalPaginas ? 'disabled' : ''}" 
                  onclick="irParaPagina(${paginaAtual + 1})" ${paginaAtual === totalPaginas ? 'disabled' : ''}>
                    <ion-icon name="chevron-forward-outline"></ion-icon>
                 </button>`;

        html += '</div>';
        
        // Info da página
        const inicio_reg = ((paginaAtual - 1) * 20) + 1;
        const fim_reg = Math.min(paginaAtual * 20, totalRegistros);
        html += `<div class="page-info">Exibindo ${inicio_reg}-${fim_reg} de ${totalRegistros}</div>`;

        container.html(html);
    }

    function irParaPagina(pagina) {
        if (pagina < 1 || pagina > totalPaginas || pagina === paginaAtual) return;
        buscarPontosMedicao(pagina);
        // Scroll para o topo da tabela
        document.querySelector('.table-container').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ============================================
    // Renderizar Tabela
    // ============================================
    function renderizarTabela(dados) {
        if (!dados || dados.length === 0) {
            $('#tabelaResultados').html(`
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <ion-icon name="file-tray-outline"></ion-icon>
                            </div>
                            <h3>Nenhum registro encontrado</h3>
                            <p>Tente ajustar os filtros de pesquisa ou realizar uma nova busca</p>
                        </div>
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';

        dados.forEach(function(item) {
            const tipoMedidor = tiposMedidorMap[item.ID_TIPO_MEDIDOR] || { text: '-', class: '', abbr: '-' };
            const tipoLeitura = tiposLeituraMap[item.ID_TIPO_LEITURA] || '-';
            const isAtivo = item.ATIVO == 1;

            html += `
                <tr>
                    <td class="truncate" title="${item.UNIDADE || ''}">${item.UNIDADE || '-'}</td>
                    <td class="truncate" title="${item.LOCALIDADE || ''}">${item.LOCALIDADE || '-'}</td>
                    <td class="code">${item.CODIGO || '-'}</td>
                    <td class="name" title="${item.DS_NOME || ''}">${item.DS_NOME || '-'}</td>
                    <td class="code">${item.CODIGO_TAG || '-'}</td>
                    <td>
                        <span class="badge badge-tipo ${tipoMedidor.class}" title="${tipoMedidor.text}">
                            ${tipoMedidor.abbr} - ${tipoMedidor.text}
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-leitura">${tipoLeitura}</span>
                    </td>
                    <td>
                        <span class="badge badge-status ${isAtivo ? 'ativo' : 'inativo'}">
                            ${isAtivo ? 'Ativo' : 'Inativo'}
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <button type="button" class="btn-action" onclick="visualizar(${item.CD_PONTO_MEDICAO})" title="Visualizar">
                                <ion-icon name="eye-outline"></ion-icon>
                            </button>
                            ${permissoes.podeEditar ? `
                            <button type="button" class="btn-action" onclick="editar(${item.CD_PONTO_MEDICAO})" title="Editar">
                                <ion-icon name="create-outline"></ion-icon>
                            </button>
                            ` : ''}
                            ${permissoes.podeEditar && isAtivo ? `
                            <button type="button" class="btn-action delete" onclick="excluir(${item.CD_PONTO_MEDICAO})" title="Desativar">
                                <ion-icon name="trash-outline"></ion-icon>
                            </button>
                            ` : ''}
                            ${permissoes.podeEditar && !isAtivo ? `
                            <button type="button" class="btn-action activate" onclick="ativar(${item.CD_PONTO_MEDICAO})" title="Ativar">
                                <ion-icon name="checkmark-circle-outline"></ion-icon>
                            </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });

        $('#tabelaResultados').html(html);
    }

    // ============================================
    // Ações
    // ============================================
    function limparFiltros() {
        // Reset ordenação
        sortColumn = null;
        sortDirection = 'asc';
        document.querySelectorAll('.data-table th').forEach(h => {
            h.classList.remove('sorted-asc', 'sorted-desc');
        });

        // Reset paginação
        paginaAtual = 1;
        totalPaginas = 0;
        totalRegistros = 0;

        // Reset filtros select
        $('#selectUnidade').val('').trigger('change.select2');
        $('#selectLocalidade').val('').prop('disabled', true).html('<option value="">Selecione uma Unidade primeiro</option>').trigger('change.select2');
        $('#selectTipoMedidor').val('').trigger('change.select2');
        $('#selectTipoLeitura').val('').trigger('change.select2');

        // Reset campo de busca
        $('#inputBusca').val('');
        $('#btnLimparBusca').hide();

        // Reset radio buttons (seleciona "Todos")
        $('input[name="status"][value=""]').prop('checked', true);

        // Limpa tabela e paginação
        $('#tabelaResultados').html(`
            <tr>
                <td colspan="9">
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <ion-icon name="filter-outline"></ion-icon>
                        </div>
                        <h3>Preencha ao menos um filtro</h3>
                        <p>Selecione uma unidade, localidade, tipo ou digite algo na busca geral</p>
                    </div>
                </td>
            </tr>
        `);
        $('#totalRegistros').text('0');
        $('#paginacao').html('');
    }

    function visualizar(id) {
        window.location.href = `pontoMedicaoView.php?id=${id}`;
    }

    function editar(id) {
        window.location.href = `pontoMedicaoForm.php?id=${id}`;
    }

    function excluir(id) {
        if (confirm('Tem certeza que deseja desativar este ponto de medição?')) {
            $.ajax({
                url: 'bd/pontoMedicao/excluirPontoMedicao.php',
                type: 'POST',
                data: { cd_ponto_medicao: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showToast(response.message, 'sucesso');
                        buscarPontosMedicao(paginaAtual);
                    } else {
                        showToast(response.message || 'Erro ao desativar', 'erro');
                    }
                },
                error: function() {
                    showToast('Erro ao comunicar com o servidor', 'erro');
                }
            });
        }
    }

    function ativar(id) {
        if (confirm('Deseja reativar este ponto de medição?')) {
            $.ajax({
                url: 'bd/pontoMedicao/ativarPontoMedicao.php',
                type: 'POST',
                data: { cd_ponto_medicao: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showToast(response.message, 'sucesso');
                        buscarPontosMedicao(paginaAtual);
                    } else {
                        showToast(response.message || 'Erro ao ativar', 'erro');
                    }
                },
                error: function() {
                    showToast('Erro ao comunicar com o servidor', 'erro');
                }
            });
        }
    }
</script>

<?php include_once 'includes/footer.inc.php'; ?>
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

<style>
    /* ============================================
   Choices.js Customização - CORRIGIDO Z-INDEX
   ============================================ */
    .choices {
        margin-bottom: 0;
        position: relative;
        z-index: 1;
    }

    .choices.is-open {
        z-index: 99999;
    }

    .choices__inner {
        min-height: 44px;
        padding: 6px 12px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 13px;
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
        color: #334155;
    }

    /* Item selecionado - cor cinza escuro */
    .choices__list--single .choices__item--selectable {
        color: #334155 !important;
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
        z-index: 99999 !important;
        position: absolute !important;
    }

    .choices__list--dropdown .choices__item {
        padding: 10px 14px;
        font-size: 13px;
    }

    .choices__list--dropdown .choices__item--selectable.is-highlighted {
        background-color: #dbeafe;
        color: #1e293b;
    }

    .choices__list--dropdown .choices__item--selectable.is-selected {
        background-color: #eff6ff;
        color: #3b82f6;
    }

    .choices[data-type*="select-one"] .choices__input {
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
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

    /* Choices.js dentro de modais - controle de largura e z-index */
    .modal-body .choices {
        width: 100% !important;
        max-width: 100% !important;
    }

    .modal-body .choices.is-open {
        z-index: 100000 !important;
    }


    .modal-body .choices__inner {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box;
        z-index: 100000 !important;

    }

    .modal-body .choices__list--dropdown {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box;
        z-index: 100001 !important;
    }

    /* Z-index elevado para dropdowns dentro de modais */
    .modal-overlay .choices.is-open,
    .modal .choices.is-open {
        z-index: 100000 !important;
    }

    .modal-overlay .choices__list--dropdown,
    .modal .choices__list--dropdown {
        z-index: 100001 !important;
    }

    .modal-overlay .choices.is-open {
        z-index: 100000 !important;
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
   Tabs Navigation
   ============================================ */
    .tabs-navigation {
        display: flex;
        gap: 4px;
        background: #f8fafc;
        padding: 6px;
        border-radius: 12px;
        margin-bottom: 24px;
        overflow-x: auto;
        border: 1px solid #e2e8f0;
    }

    .tabs-navigation::-webkit-scrollbar {
        height: 6px;
    }

    .tabs-navigation::-webkit-scrollbar-track {
        background: transparent;
    }

    .tabs-navigation::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
    }

    .tab-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        background: transparent;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .tab-btn:hover {
        background: white;
        color: #334155;
    }

    .tab-btn.active {
        background: white;
        color: #3b82f6;
        font-weight: 600;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .tab-btn ion-icon {
        font-size: 18px;
    }

    /* ============================================
   Tab Content
   ============================================ */
    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
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

    .btn-novo {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        background: #3b82f6;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-novo:hover {
        background: #2563eb;
        transform: translateY(-1px);
    }

    .btn-novo ion-icon {
        font-size: 16px;
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    /* ============================================
   Form Controls
   ============================================ */
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
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

    /* ============================================
   Input Search com ícone
   ============================================ */
    .input-search-wrapper {
        position: relative;
        display: flex;
        align-items: center;
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
        display: none;
        align-items: center;
        justify-content: center;
        transition: color 0.2s ease;
    }

    .btn-search-clear.visible {
        display: flex;
    }

    .btn-search-clear:hover {
        color: #64748b;
    }

    .btn-search-clear ion-icon {
        font-size: 18px;
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
    }

    .results-count ion-icon {
        font-size: 16px;
    }

    /* ============================================
   Table Container
   ============================================ */
    .table-container {
        background: #ffffff;
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
        border-collapse: collapse;
    }

    .data-table th {
        padding: 14px 16px;
        text-align: left;
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }

    .data-table td {
        padding: 14px 16px;
        font-size: 13px;
        color: #334155;
        border-bottom: 1px solid #f1f5f9;
    }

    .data-table tbody tr:hover {
        background: #f8fafc;
    }

    .data-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Loading Overlay */
    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10;
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
        to {
            transform: rotate(360deg);
        }
    }

    /* ============================================
   Table Actions
   ============================================ */
    .table-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-action {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-action.edit {
        background: #eff6ff;
        color: #3b82f6;
    }

    .btn-action.edit:hover {
        background: #3b82f6;
        color: white;
    }

    .btn-action.delete {
        background: #fef2f2;
        color: #ef4444;
    }

    .btn-action.delete:hover {
        background: #ef4444;
        color: white;
    }

    .btn-action ion-icon {
        font-size: 16px;
    }

    /* ============================================
   Pagination
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

    .page-info {
        font-size: 13px;
        color: #64748b;
    }

    /* ============================================
   Modal
   ============================================ */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(15, 23, 42, 0.6);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 20px;
        backdrop-filter: blur(4px);
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal {
        background: white;
        border-radius: 16px;
        width: 100%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15);
        animation: modalIn 0.2s ease;
        overflow: visible !important;

    }

    @keyframes modalIn {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(-10px);
        }

        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 1px solid #e2e8f0;
    }

    .modal-title {
        font-size: 18px;
        font-weight: 600;
        color: #1e293b;
    }

    .modal-close {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f1f5f9;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        color: #64748b;
        transition: all 0.2s ease;
    }

    .modal-close:hover {
        background: #e2e8f0;
        color: #334155;

    }

    .modal-body {
        padding: 24px;
        overflow: visible !important;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 16px 24px;
        border-top: 1px solid #e2e8f0;
        background: #f8fafc;
        border-radius: 0 0 16px 16px;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 18px;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-secondary {
        background: #f1f5f9;
        color: #475569;
    }

    .btn-secondary:hover {
        background: #e2e8f0;
    }

    .btn-primary {
        background: #3b82f6;
        color: white;
    }

    .btn-primary:hover {
        background: #2563eb;
    }

    .form-group-modal {
        margin-bottom: 16px;
        overflow: visible !important;

    }

    .form-group-modal:last-child {
        margin-bottom: 0;
    }

    .form-group-modal label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        margin-bottom: 8px;
    }

    .form-group-modal label .required {
        color: #ef4444;
    }

    .form-group-modal .form-control {
        width: 100%;
        box-sizing: border-box;
    }

    .modal-body .form-control {
        max-width: 100%;
    }

    .modal-body textarea.form-control {
        resize: vertical;
        min-height: 80px;
    }

    .modal-body select.form-control {
        cursor: pointer;
    }

    /* ============================================
   Toast
   ============================================ */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
    }

    .toast {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 20px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
        margin-bottom: 10px;
        animation: toastIn 0.3s ease;
        min-width: 280px;
    }

    @keyframes toastIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .toast.sucesso {
        border-left: 4px solid #22c55e;
    }

    .toast.erro {
        border-left: 4px solid #ef4444;
    }

    .toast-icon {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }

    .toast.sucesso .toast-icon {
        background: #dcfce7;
        color: #22c55e;
    }

    .toast.erro .toast-icon {
        background: #fee2e2;
        color: #ef4444;
    }

    .toast-message {
        flex: 1;
        font-size: 13px;
        color: #334155;
        font-weight: 500;
    }

    /* ============================================
   Responsive
   ============================================ */
    @media (max-width: 1024px) {
        .filters-grid {
            grid-template-columns: repeat(2, 1fr);
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

        .tabs-navigation {
            gap: 2px;
            padding: 4px;
        }

        .tab-btn {
            padding: 10px 14px;
            font-size: 12px;
        }

        .tab-btn ion-icon {
            font-size: 16px;
        }

        .filters-card {
            padding: 16px;
        }

        .filters-grid {
            grid-template-columns: 1fr;
        }

        .filters-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }

        .table-container {
            border-radius: 12px;
        }

        .pagination-container {
            flex-direction: column;
            text-align: center;
        }

        .modal {
            margin: 10px;
            max-height: 95vh;
        }
    }
</style>

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
        <button type="button" class="tab-btn" data-tab="eta">
            <ion-icon name="flask-outline"></ion-icon>
            ETA
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
                        <input type="text" id="filtroTipoMedidor" class="form-control input-search" placeholder="Digite para buscar...">
                        <button type="button" class="btn-search-clear" onclick="limparFiltro('filtroTipoMedidor', buscarTiposMedidor)">
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
                        <input type="text" id="filtroTipoReservatorio" class="form-control input-search" placeholder="Digite para buscar...">
                        <button type="button" class="btn-search-clear" onclick="limparFiltro('filtroTipoReservatorio', buscarTiposReservatorio)">
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
                        <input type="text" id="filtroAreaInfluencia" class="form-control input-search" placeholder="Digite para buscar...">
                        <button type="button" class="btn-search-clear" onclick="limparFiltro('filtroAreaInfluencia', buscarAreasInfluencia)">
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
                        <input type="text" id="filtroUnidade" class="form-control input-search" placeholder="Digite para buscar...">
                        <button type="button" class="btn-search-clear" onclick="limparFiltro('filtroUnidade', buscarUnidades)">
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
                            <option value="<?= $u['CD_UNIDADE'] ?>"><?= htmlspecialchars($u['CD_CODIGO'] . ' - ' . $u['DS_NOME']) ?></option>
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
                        <input type="text" id="filtroLocalidade" class="form-control input-search" placeholder="Digite para buscar...">
                        <button type="button" class="btn-search-clear" onclick="limparFiltro('filtroLocalidade', buscarLocalidades)">
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
                        <input type="text" id="filtroSistemaAgua" class="form-control input-search" placeholder="Digite para buscar...">
                        <button type="button" class="btn-search-clear" onclick="limparFiltro('filtroSistemaAgua', buscarSistemasAgua)">
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
                        <input type="text" id="filtroEta" class="form-control input-search" placeholder="Digite para buscar...">
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
            <button type="button" class="btn btn-secondary" onclick="fecharModal('modalTipoReservatorio')">Cancelar</button>
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
            <button type="button" class="btn btn-secondary" onclick="fecharModal('modalAreaInfluencia')">Cancelar</button>
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
                        <option value="<?= $u['CD_UNIDADE'] ?>"><?= htmlspecialchars($u['CD_CODIGO'] . ' - ' . $u['DS_NOME']) ?></option>
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
                        <option value="<?= htmlspecialchars($loc['CD_CHAVE']) ?>"><?= htmlspecialchars($loc['CD_LOCALIDADE'] . ' - ' . $loc['DS_NOME']) ?></option>
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
        eta: {
            pagina: 1,
            total: 0,
            totalPaginas: 0
        }
    };

    // ============================================
    // Navegação de Abas
    // ============================================
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            this.classList.add('active');
            document.getElementById('tab-' + this.dataset.tab).classList.add('active');
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
        overlay.addEventListener('click', function(e) {
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
        input.addEventListener('input', function() {
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
        }, function(response) {
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
        }, 'json').fail(function() {
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
        }, function(response) {
            if (response.success && response.data) {
                document.getElementById('tipoMedidorId').value = response.data.CD_CHAVE;
                document.getElementById('tipoMedidorNome').value = response.data.DS_NOME || '';
                setChoicesValue('tipoMedidorCalculo', response.data.ID_TIPO_CALCULO || '');
                document.getElementById('modalTipoMedidorTitulo').textContent = 'Editar Tipo de Medidor';
                abrirModal('modalTipoMedidor');
            } else {
                showToast('Erro ao carregar dados', 'erro');
            }
        }, 'json').fail(function() {
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

        $.post('bd/cadastrosAuxiliares/salvarTipoMedidor.php', dados, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                fecharModal('modalTipoMedidor');
                buscarTiposMedidor(estadoPaginacao.tipoMedidor.pagina);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function excluirTipoMedidor(id) {
        if (!confirm('Deseja realmente excluir este registro?')) return;

        $.post('bd/cadastrosAuxiliares/excluirTipoMedidor.php', {
            id: id
        }, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                buscarTiposMedidor(estadoPaginacao.tipoMedidor.pagina);
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        }, 'json').fail(function() {
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
        }, function(response) {
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
        }, 'json').fail(function() {
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
        }, function(response) {
            if (response.success && response.data) {
                document.getElementById('tipoReservatorioId').value = response.data.CD_CHAVE;
                document.getElementById('tipoReservatorioNome').value = response.data.NOME || '';
                document.getElementById('modalTipoReservatorioTitulo').textContent = 'Editar Tipo de Reservatório';
                abrirModal('modalTipoReservatorio');
            } else {
                showToast('Erro ao carregar dados', 'erro');
            }
        }, 'json').fail(function() {
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

        $.post('bd/cadastrosAuxiliares/salvarTipoReservatorio.php', dados, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                fecharModal('modalTipoReservatorio');
                buscarTiposReservatorio(estadoPaginacao.tipoReservatorio.pagina);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function excluirTipoReservatorio(id) {
        if (!confirm('Deseja realmente excluir este registro?')) return;

        $.post('bd/cadastrosAuxiliares/excluirTipoReservatorio.php', {
            id: id
        }, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                buscarTiposReservatorio(estadoPaginacao.tipoReservatorio.pagina);
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        }, 'json').fail(function() {
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
        }, function(response) {
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
        }, 'json').fail(function() {
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
        }, function(response) {
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
        }, 'json').fail(function() {
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

        $.post('bd/cadastrosAuxiliares/salvarAreaInfluencia.php', dados, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                fecharModal('modalAreaInfluencia');
                buscarAreasInfluencia(estadoPaginacao.areaInfluencia.pagina);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function excluirAreaInfluencia(id) {
        if (!confirm('Deseja realmente excluir este registro e seus bairros vinculados?')) return;

        $.post('bd/cadastrosAuxiliares/excluirAreaInfluencia.php', {
            id: id
        }, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                buscarAreasInfluencia(estadoPaginacao.areaInfluencia.pagina);
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        }, 'json').fail(function() {
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
        }, function(response) {
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
        }, 'json').fail(function() {
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
        }, function(response) {
            if (response.success && response.data) {
                document.getElementById('unidadeId').value = response.data.CD_UNIDADE;
                document.getElementById('unidadeCodigo').value = response.data.CD_CODIGO || '';
                document.getElementById('unidadeNome').value = response.data.DS_NOME || '';
                document.getElementById('modalUnidadeTitulo').textContent = 'Editar Unidade';
                abrirModal('modalUnidade');
            } else {
                showToast('Erro ao carregar dados', 'erro');
            }
        }, 'json').fail(function() {
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

        $.post('bd/cadastrosAuxiliares/salvarUnidade.php', dados, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                fecharModal('modalUnidade');
                buscarUnidades(estadoPaginacao.unidade.pagina);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function excluirUnidade(id) {
        if (!confirm('Deseja realmente excluir este registro?')) return;

        $.post('bd/cadastrosAuxiliares/excluirUnidade.php', {
            id: id
        }, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                buscarUnidades(estadoPaginacao.unidade.pagina);
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        }, 'json').fail(function() {
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
        }, function(response) {
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
        }, 'json').fail(function() {
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
        }, function(response) {
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
        }, 'json').fail(function() {
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

        $.post('bd/cadastrosAuxiliares/salvarLocalidade.php', dados, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                fecharModal('modalLocalidade');
                buscarLocalidades(estadoPaginacao.localidade.pagina);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function excluirLocalidade(id) {
        if (!confirm('Deseja realmente excluir este registro?')) return;

        $.post('bd/cadastrosAuxiliares/excluirLocalidade.php', {
            id: id
        }, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                buscarLocalidades(estadoPaginacao.localidade.pagina);
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        }, 'json').fail(function() {
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
        }, function(response) {
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
        }, 'json').fail(function() {
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
        }, function(response) {
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
        }, 'json').fail(function() {
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

        $.post('bd/cadastrosAuxiliares/salvarSistemaAgua.php', dados, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                fecharModal('modalSistemaAgua');
                buscarSistemasAgua(estadoPaginacao.sistemaAgua.pagina);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function excluirSistemaAgua(id) {
        if (!confirm('Deseja realmente excluir este registro?')) return;

        $.post('bd/cadastrosAuxiliares/excluirSistemaAgua.php', {
            id: id
        }, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                buscarSistemasAgua(estadoPaginacao.sistemaAgua.pagina);
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        }, 'json').fail(function() {
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
        }, function(response) {
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
        }, 'json').fail(function() {
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
        }, function(response) {
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
        }, 'json').fail(function() {
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

        $.post('bd/cadastrosAuxiliares/salvarEta.php', dados, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                fecharModal('modalEta');
                buscarEtas(estadoPaginacao.eta.pagina);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function excluirEta(id) {
        if (!confirm('Deseja realmente excluir este registro?')) return;

        $.post('bd/cadastrosAuxiliares/excluirEta.php', {
            id: id
        }, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                buscarEtas(estadoPaginacao.eta.pagina);
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // Event Listeners - Busca ao digitar (debounce)
    // ============================================
    document.getElementById('filtroTipoMedidor').addEventListener('input',
        debounce(function() {
            buscarTiposMedidor(1);
        }, 400, 'tipoMedidor'));

    document.getElementById('filtroTipoReservatorio').addEventListener('input',
        debounce(function() {
            buscarTiposReservatorio(1);
        }, 400, 'tipoReservatorio'));

    document.getElementById('filtroAreaInfluencia').addEventListener('input',
        debounce(function() {
            buscarAreasInfluencia(1);
        }, 400, 'areaInfluencia'));

    document.getElementById('filtroUnidade').addEventListener('input',
        debounce(function() {
            buscarUnidades(1);
        }, 400, 'unidade'));

    document.getElementById('filtroLocalidade').addEventListener('input',
        debounce(function() {
            buscarLocalidades(1);
        }, 400, 'localidade'));
    document.getElementById('filtroLocalidadeUnidade').addEventListener('change', function() {
        buscarLocalidades(1);
    });

    document.getElementById('filtroSistemaAgua').addEventListener('input',
        debounce(function() {
            buscarSistemasAgua(1);
        }, 400, 'sistemaAgua'));

    document.getElementById('filtroEta').addEventListener('input',
        debounce(function() {
            buscarEtas(1);
        }, 400, 'eta'));
    document.getElementById('filtroEtaSistema').addEventListener('change', function() {
        buscarEtas(1);
    });

    // ============================================
    // Carregar dados ao iniciar
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        buscarTiposMedidor(1);
    });

    // Carregar dados ao trocar de aba (apenas se não carregou ainda)
    let abasCarregadas = {
        tipoMedidor: true
    };
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.dataset.tab;
            if (!abasCarregadas[tab]) {
                abasCarregadas[tab] = true;
                switch (tab) {
                    case 'tipoReservatorio':
                        buscarTiposReservatorio(1);
                        break;
                    case 'areaInfluencia':
                        buscarAreasInfluencia(1);
                        break;
                    case 'unidade':
                        buscarUnidades(1);
                        break;
                    case 'localidade':
                        buscarLocalidades(1);
                        break;
                    case 'sistemaAgua':
                        buscarSistemasAgua(1);
                        break;
                    case 'eta':
                        buscarEtas(1);
                        break;
                }
            }
        });
    });
</script>

<!-- Choices.js (não precisa de jQuery) -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script>
    // ============================================
    // Choices.js Inicialização
    // ============================================
    const choicesInstances = {};

    function initChoices() {
        document.querySelectorAll('.choices-select').forEach(function(select) {
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
    document.addEventListener('DOMContentLoaded', function() {
        initChoices();
    });
</script>

<?php include_once 'includes/footer.inc.php'; ?>
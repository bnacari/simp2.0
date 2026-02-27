<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Cadastros Administrativos
 */

include_once 'includes/header.inc.php';
include_once 'bd/conexao.php';

// Agora verificar permissão
// Recarregar permissões do banco (garante que estão atualizadas)
recarregarPermissoesUsuario();

// Agora verificar permissão
exigePermissaoTela('CADASTROS ADMINISTRATIVOS', ACESSO_LEITURA);

// Verifica se pode editar (para ocultar/desabilitar botões)
$podeEditar = podeEditarTela('CADASTROS ADMINISTRATIVOS');

include_once 'includes/menu.inc.php';

// Buscar grupos de usuário para select
$sqlGrupos = $pdoSIMP->query("SELECT CD_GRUPO_USUARIO, DS_NOME FROM SIMP.dbo.GRUPO_USUARIO ORDER BY DS_NOME");
$gruposUsuario = $sqlGrupos->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    /* ============================================
       Page Container
       ============================================ */
    .page-container {
        padding: 24px;
        max-width: 1400px;
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
       Radio Group (Filtros)
       ============================================ */
    .radio-group {
        display: flex;
        gap: 4px;
        background: #f1f5f9;
        padding: 4px;
        border-radius: 10px;
    }

    .radio-item {
        display: flex;
        align-items: center;
        cursor: pointer;
        margin: 0;
    }

    .radio-item input[type="radio"] {
        display: none;
    }

    .radio-item .radio-label {
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 500;
        color: #64748b;
        border-radius: 8px;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .radio-item input[type="radio"]:checked + .radio-label {
        background: white;
        color: #1e293b;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .radio-item:hover .radio-label {
        color: #1e293b;
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
        color: #3b82f6;
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
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
        font-weight: 600;
    }

    /* ============================================
       Data Table
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
    }

    .data-table thead {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        position: sticky;
        top: 0;
        z-index: 5;
    }

    .data-table th {
        padding: 14px 16px;
        text-align: left;
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
    }

    /* Colunas ordenáveis */
    .data-table th.sortable {
        cursor: pointer;
        user-select: none;
        transition: all 0.2s ease;
    }

    .data-table th.sortable:hover {
        background: #e2e8f0;
        color: #1e293b;
    }

    .data-table th.sortable .th-content {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .data-table th.sortable .sort-icon {
        font-size: 14px;
        opacity: 0.3;
        transition: all 0.2s ease;
    }

    .data-table th.sortable:hover .sort-icon {
        opacity: 0.6;
    }

    .data-table th.sortable.asc .sort-icon,
    .data-table th.sortable.desc .sort-icon {
        opacity: 1;
        color: #3b82f6;
    }

    .data-table th.sortable.asc .sort-icon {
        transform: rotate(180deg);
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

    .badge-info {
        background: #eff6ff;
        color: #3b82f6;
    }

    .badge-success {
        background: #dcfce7;
        color: #15803d;
    }

    .badge-warning {
        background: #fef3c7;
        color: #b45309;
    }

    .badge-clickable {
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .badge-clickable:hover {
        transform: scale(1.05);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    .badge-clickable ion-icon {
        font-size: 12px;
    }

    /* ============================================
       Modal Permissões (Maior)
       ============================================ */
    .modal.modal-lg {
        max-width: 750px;
    }

    /* Seção Adicionar Permissão */
    .adicionar-permissao-section {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 20px;
    }

    .adicionar-permissao-header {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        margin-bottom: 12px;
    }

    .adicionar-permissao-header ion-icon {
        font-size: 18px;
        color: #10b981;
    }

    .form-row-permissao {
        display: flex;
        gap: 12px;
        align-items: flex-end;
        flex-wrap: wrap;
    }

    .form-group-funcionalidade {
        flex: 1;
        min-width: 200px;
    }

    .form-group-tipo-acesso {
        flex-shrink: 0;
    }

    .form-group-btn-add {
        flex-shrink: 0;
    }

    /* Autocomplete Dropdown */
    .autocomplete-container {
        position: relative;
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
        max-height: 200px;
        overflow-y: auto;
        z-index: 100;
        display: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .autocomplete-dropdown.active {
        display: block;
    }

    .autocomplete-item {
        padding: 10px 14px;
        cursor: pointer;
        font-size: 13px;
        color: #334155;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.15s ease;
    }

    .autocomplete-item:last-child {
        border-bottom: none;
    }

    .autocomplete-item:hover,
    .autocomplete-item.highlighted {
        background: #eff6ff;
        color: #3b82f6;
    }

    .autocomplete-item.no-results {
        color: #94a3b8;
        font-style: italic;
        cursor: default;
    }

    .autocomplete-item.no-results:hover {
        background: transparent;
        color: #94a3b8;
    }

    /* Seção Permissões Cadastradas */
    .permissoes-cadastradas-section {
        margin-top: 16px;
    }

    .permissoes-cadastradas-header {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid #e2e8f0;
    }

    .permissoes-cadastradas-header ion-icon {
        font-size: 18px;
        color: #3b82f6;
    }

    .permissoes-count {
        color: #64748b;
        font-weight: 500;
    }

    .permissoes-search-box {
        margin-bottom: 12px;
    }

    .permissoes-search-box .input-search-wrapper {
        position: relative;
    }

    .permissoes-search-box .form-control {
        padding-left: 40px;
        padding-right: 36px;
        font-size: 13px;
    }

    .permissoes-search-box .input-search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 16px;
        color: #94a3b8;
        pointer-events: none;
    }

    .permissoes-search-box .btn-search-clear {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        padding: 4px;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .permissoes-search-box .btn-search-clear.visible {
        display: flex;
    }

    .permissoes-search-box .btn-search-clear:hover {
        color: #64748b;
    }

    .btn-add-permissao {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 16px;
        background: #10b981;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-add-permissao:hover {
        background: #059669;
    }

    .btn-add-permissao ion-icon {
        font-size: 16px;
    }

    /* Ações em Massa */
    .acoes-massa {
        display: flex;
        gap: 8px;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px dashed #e2e8f0;
    }

    .btn-acao-massa {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        background: white;
    }

    .btn-acao-massa ion-icon {
        font-size: 14px;
    }

    .btn-incluir-todas {
        color: #059669;
        border-color: #a7f3d0;
    }

    .btn-incluir-todas:hover {
        background: #ecfdf5;
        border-color: #059669;
    }

    .btn-excluir-todas {
        color: #dc2626;
        border-color: #fecaca;
    }

    .btn-excluir-todas:hover {
        background: #fef2f2;
        border-color: #dc2626;
    }

    .permissoes-list {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
    }

    .permissao-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        border-bottom: 1px solid #f1f5f9;
        gap: 12px;
    }

    .permissao-item:last-child {
        border-bottom: none;
    }

    .permissao-item:hover {
        background: #f8fafc;
    }

    .permissao-info {
        flex: 1;
        min-width: 0;
    }

    .permissao-nome {
        font-weight: 500;
        color: #1e293b;
        font-size: 13px;
    }

    .permissao-acoes {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    /* Radio Group Inline - Estilo Pills */
    .radio-group-inline {
        display: flex;
        background: #f1f5f9;
        border-radius: 8px;
        padding: 4px;
        gap: 4px;
    }

    .radio-group-inline .radio-item {
        display: flex;
        align-items: center;
        cursor: pointer;
    }

    .radio-group-inline .radio-item input[type="radio"] {
        display: none;
    }

    .radio-group-inline .radio-item .radio-label {
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
        color: #64748b;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .radio-group-inline .radio-item input[type="radio"]:checked + .radio-label {
        background: white;
        color: #3b82f6;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .radio-group-inline .radio-item:hover .radio-label {
        color: #334155;
    }

    .btn-remove-permissao {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 6px;
        color: #ef4444;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-remove-permissao:hover {
        background: #fee2e2;
        border-color: #ef4444;
    }

    .btn-remove-permissao ion-icon {
        font-size: 14px;
    }

    .permissoes-empty {
        text-align: center;
        padding: 40px 20px;
        color: #94a3b8;
    }

    .permissoes-empty ion-icon {
        font-size: 48px;
        margin-bottom: 12px;
        display: block;
    }

    /* Badge Tipo Acesso */
    .badge-tipo-acesso {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
    }

    .badge-leitura {
        background: #fef3c7;
        color: #b45309;
    }

    .badge-total {
        background: #dcfce7;
        color: #15803d;
    }

    .badge-warning {
        background: #fef3c7;
        color: #b45309;
    }

    .badge-success {
        background: #dcfce7;
        color: #15803d;
    }

    /* Responsive para modal permissões */
    @media (max-width: 640px) {
        .form-row-permissao {
            flex-direction: column;
            align-items: stretch;
        }

        .form-group-funcionalidade,
        .form-group-tipo-acesso,
        .form-group-btn-add {
            width: 100%;
        }

        .btn-add-permissao {
            width: 100%;
            justify-content: center;
        }

        .permissao-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }

        .permissao-acoes {
            width: 100%;
            justify-content: space-between;
        }
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
        max-width: 450px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15);
        animation: modalIn 0.2s ease;
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
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        border-radius: 16px 16px 0 0;
    }

    .modal-title {
        font-size: 18px;
        font-weight: 600;
        color: white;
    }

    .modal-close {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.2);
        border: none;
        border-radius: 8px;
        cursor: pointer;
        color: white;
        transition: all 0.2s ease;
    }

    .modal-close:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    .modal-body {
        padding: 24px;
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

    /* ============================================
       Responsive
       ============================================ */
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
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="shield-checkmark-outline"></ion-icon>
                </div>
                <div>
                    <h1>Cadastros Administrativos</h1>
                    <p class="page-header-subtitle">Gerencie funcionalidades, grupos de usuário e usuários do sistema</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="tabs-navigation">
        <button type="button" class="tab-btn active" data-tab="funcionalidades">
            <ion-icon name="apps-outline"></ion-icon>
            Funcionalidades
        </button>
        <button type="button" class="tab-btn" data-tab="grupoUsuario">
            <ion-icon name="people-outline"></ion-icon>
            Grupo de Usuário
        </button>
        <button type="button" class="tab-btn" data-tab="usuarios">
            <ion-icon name="person-outline"></ion-icon>
            Usuários
        </button>
    </div>

    <!-- ========================================
         TAB: FUNCIONALIDADES
         ======================================== -->
    <div class="tab-content active" id="tab-funcionalidades">
        <div class="filters-card">
            <div class="filters-header">
                <div class="filters-title">
                    <ion-icon name="apps-outline"></ion-icon>
                    Lista de Funcionalidades
                </div>
                <?php if ($podeEditar): ?>
                <button type="button" class="btn-novo" onclick="abrirModalFuncionalidade()">
                    <ion-icon name="add-outline"></ion-icon>
                    Nova Funcionalidade
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
                        <input type="text" id="filtroFuncionalidade" class="form-control input-search" placeholder="Digite para buscar...">
                        <button type="button" class="btn-search-clear" onclick="limparFiltro('filtroFuncionalidade', buscarFuncionalidades)">
                            <ion-icon name="close-circle"></ion-icon>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="results-info">
            <div class="results-count">
                <ion-icon name="list-outline"></ion-icon>
                <span id="countFuncionalidade">Carregando...</span>
            </div>
        </div>

        <div class="table-container">
            <div class="loading-overlay" id="loadingFuncionalidade">
                <div class="loading-spinner"></div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Código</th>
                            <th>Nome</th>
                            <th style="width: 100px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaFuncionalidade"></tbody>
                </table>
            </div>
        </div>

        <div class="pagination-container" id="paginacaoFuncionalidade"></div>
    </div>

    <!-- ========================================
         TAB: GRUPO DE USUÁRIO
         ======================================== -->
    <div class="tab-content" id="tab-grupoUsuario">
        <div class="filters-card">
            <div class="filters-header">
                <div class="filters-title">
                    <ion-icon name="people-outline"></ion-icon>
                    Lista de Grupos de Usuário
                </div>
                <?php if ($podeEditar): ?>
                <button type="button" class="btn-novo" onclick="abrirModalGrupoUsuario()">
                    <ion-icon name="add-outline"></ion-icon>
                    Novo Grupo
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
                        <input type="text" id="filtroGrupoUsuario" class="form-control input-search" placeholder="Digite para buscar...">
                        <button type="button" class="btn-search-clear" onclick="limparFiltro('filtroGrupoUsuario', buscarGruposUsuario)">
                            <ion-icon name="close-circle"></ion-icon>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="results-info">
            <div class="results-count">
                <ion-icon name="list-outline"></ion-icon>
                <span id="countGrupoUsuario">Carregando...</span>
            </div>
        </div>

        <div class="table-container">
            <div class="loading-overlay" id="loadingGrupoUsuario">
                <div class="loading-spinner"></div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Código</th>
                            <th>Nome</th>
                            <th style="width: 120px;">Usuários</th>
                            <th style="width: 120px;">Permissões</th>
                            <th style="width: 100px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaGrupoUsuario"></tbody>
                </table>
            </div>
        </div>

        <div class="pagination-container" id="paginacaoGrupoUsuario"></div>
    </div>

    <!-- ========================================
         TAB: USUÁRIOS
         ======================================== -->
    <div class="tab-content" id="tab-usuarios">
        <div class="filters-card">
            <div class="filters-header">
                <div class="filters-title">
                    <ion-icon name="person-outline"></ion-icon>
                    Lista de Usuários
                </div>
                <?php if ($podeEditar): ?>
                <button type="button" class="btn-novo" onclick="abrirModalUsuario()">
                    <ion-icon name="add-outline"></ion-icon>
                    Novo Usuário
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
                        <input type="text" id="filtroUsuario" class="form-control input-search" placeholder="Nome, login, e-mail ou matrícula...">
                        <button type="button" class="btn-search-clear" onclick="limparFiltro('filtroUsuario', buscarUsuarios)">
                            <ion-icon name="close-circle"></ion-icon>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="people-outline"></ion-icon>
                        Grupo
                    </label>
                    <select id="filtroUsuarioGrupo" class="form-control">
                        <option value="">Todos os grupos</option>
                        <?php foreach ($gruposUsuario as $g): ?>
                        <option value="<?= $g['CD_GRUPO_USUARIO'] ?>"><?= htmlspecialchars($g['DS_NOME']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="toggle-outline"></ion-icon>
                        Status
                    </label>
                    <div class="radio-group">
                        <label class="radio-item">
                            <input type="radio" name="filtroUsuarioStatus" value="" checked>
                            <span class="radio-label">Todos</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="filtroUsuarioStatus" value="ativo">
                            <span class="radio-label">Ativos</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="filtroUsuarioStatus" value="bloqueado">
                            <span class="radio-label">Inativos</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="results-info">
            <div class="results-count">
                <ion-icon name="list-outline"></ion-icon>
                <span id="countUsuario">Carregando...</span>
            </div>
        </div>

        <div class="table-container">
            <div class="loading-overlay" id="loadingUsuario">
                <div class="loading-spinner"></div>
            </div>
            <div class="table-wrapper">
                <table class="data-table" id="tabelaUsuariosTable">
                    <thead>
                        <tr>
                            <th style="width: 80px;" class="sortable" data-column="CD_USUARIO" onclick="ordenarUsuarios('CD_USUARIO')">
                                <span class="th-content">Código <ion-icon name="chevron-down-outline" class="sort-icon"></ion-icon></span>
                            </th>
                            <th style="width: 120px;" class="sortable" data-column="DS_MATRICULA" onclick="ordenarUsuarios('DS_MATRICULA')">
                                <span class="th-content">Matrícula <ion-icon name="chevron-down-outline" class="sort-icon"></ion-icon></span>
                            </th>
                            <th style="width: 120px;" class="sortable" data-column="DS_LOGIN" onclick="ordenarUsuarios('DS_LOGIN')">
                                <span class="th-content">Login <ion-icon name="chevron-down-outline" class="sort-icon"></ion-icon></span>
                            </th>
                            <th class="sortable asc" data-column="DS_NOME" onclick="ordenarUsuarios('DS_NOME')">
                                <span class="th-content">Nome <ion-icon name="chevron-down-outline" class="sort-icon"></ion-icon></span>
                            </th>
                            <th style="width: 200px;" class="sortable" data-column="DS_EMAIL" onclick="ordenarUsuarios('DS_EMAIL')">
                                <span class="th-content">E-mail <ion-icon name="chevron-down-outline" class="sort-icon"></ion-icon></span>
                            </th>
                            <th style="width: 150px;" class="sortable" data-column="DS_GRUPO" onclick="ordenarUsuarios('DS_GRUPO')">
                                <span class="th-content">Grupo <ion-icon name="chevron-down-outline" class="sort-icon"></ion-icon></span>
                            </th>
                            <th style="width: 100px;" class="sortable" data-column="OP_BLOQUEADO" onclick="ordenarUsuarios('OP_BLOQUEADO')">
                                <span class="th-content">Status <ion-icon name="chevron-down-outline" class="sort-icon"></ion-icon></span>
                            </th>
                            <th style="width: 100px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaUsuario"></tbody>
                </table>
            </div>
        </div>

        <div class="pagination-container" id="paginacaoUsuario"></div>
    </div>
</div>

<!-- ========================================
     MODAIS
     ======================================== -->

<!-- Modal Funcionalidade -->
<div class="modal-overlay" id="modalFuncionalidade">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalFuncionalidadeTitulo">Nova Funcionalidade</span>
            <button type="button" class="modal-close" onclick="fecharModal('modalFuncionalidade')">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="funcionalidadeId">
            <div class="form-group-modal">
                <label>Nome <span class="required">*</span></label>
                <input type="text" class="form-control" id="funcionalidadeNome" maxlength="100" placeholder="Digite o nome da funcionalidade">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fecharModal('modalFuncionalidade')">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="salvarFuncionalidade()">
                <ion-icon name="checkmark-outline"></ion-icon>
                Salvar
            </button>
        </div>
    </div>
</div>

<!-- Modal Grupo de Usuário -->
<div class="modal-overlay" id="modalGrupoUsuario">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalGrupoUsuarioTitulo">Novo Grupo de Usuário</span>
            <button type="button" class="modal-close" onclick="fecharModal('modalGrupoUsuario')">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="grupoUsuarioId">
            <div class="form-group-modal">
                <label>Nome <span class="required">*</span></label>
                <input type="text" class="form-control" id="grupoUsuarioNome" maxlength="100" placeholder="Digite o nome do grupo">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fecharModal('modalGrupoUsuario')">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="salvarGrupoUsuario()">
                <ion-icon name="checkmark-outline"></ion-icon>
                Salvar
            </button>
        </div>
    </div>
</div>

<!-- Modal Permissões do Grupo -->
<div class="modal-overlay" id="modalPermissoes">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span class="modal-title" id="modalPermissoesTitulo">Permissões do Grupo</span>
            <button type="button" class="modal-close" onclick="fecharModal('modalPermissoes')">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="permissoesGrupoId">
            
            <?php if ($podeEditar): ?>
            <!-- Seção para adicionar nova funcionalidade -->
            <div class="adicionar-permissao-section">
                <div class="adicionar-permissao-header">
                    <ion-icon name="add-circle-outline"></ion-icon>
                    Adicionar Funcionalidade
                </div>
                <div class="adicionar-permissao-form">
                    <div class="form-row-permissao">
                        <div class="form-group-funcionalidade">
                            <label class="form-label">Funcionalidade</label>
                            <div class="autocomplete-container" id="autocompleteFuncionalidade">
                                <input type="text" 
                                       id="inputFuncionalidade" 
                                       class="form-control" 
                                       placeholder="Clique ou digite para buscar..."
                                       autocomplete="off">
                                <input type="hidden" id="inputFuncionalidadeId">
                                <div class="autocomplete-dropdown" id="dropdownFuncionalidade"></div>
                            </div>
                        </div>
                        <div class="form-group-tipo-acesso">
                            <label class="form-label">Tipo de Acesso</label>
                            <div class="radio-group-inline">
                                <label class="radio-item">
                                    <input type="radio" name="tipoAcessoNovo" value="1" checked>
                                    <span class="radio-label">Somente Leitura</span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" name="tipoAcessoNovo" value="2">
                                    <span class="radio-label">Acesso Total</span>
                                </label>
                            </div>
                        </div>
                        <div class="form-group-btn-add">
                            <button type="button" class="btn-add-permissao" onclick="adicionarFuncionalidadeAoGrupo()">
                                <ion-icon name="add-outline"></ion-icon>
                                Adicionar
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Ações em massa -->
                <div class="acoes-massa">
                    <button type="button" class="btn-acao-massa btn-incluir-todas" onclick="incluirTodasFuncionalidades()">
                        <ion-icon name="checkbox-outline"></ion-icon>
                        Incluir Todas
                    </button>
                    <button type="button" class="btn-acao-massa btn-excluir-todas" onclick="excluirTodasFuncionalidades()">
                        <ion-icon name="trash-outline"></ion-icon>
                        Excluir Todas
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Seção de permissões já cadastradas -->
            <div class="permissoes-cadastradas-section">
                <div class="permissoes-cadastradas-header">
                    <ion-icon name="key-outline"></ion-icon>
                    Funcionalidades Vinculadas
                    <span class="permissoes-count" id="permissoesCount">(0)</span>
                </div>
                <div class="permissoes-search-box">
                    <div class="input-search-wrapper">
                        <ion-icon name="search-outline" class="input-search-icon"></ion-icon>
                        <input type="text" id="filtroPermissoesVinculadas" class="form-control input-search" placeholder="Pesquisar funcionalidades vinculadas...">
                        <button type="button" class="btn-search-clear" id="btnLimparFiltroPermissoes" onclick="limparFiltroPermissoes()">
                            <ion-icon name="close-circle"></ion-icon>
                        </button>
                    </div>
                </div>
                <div class="permissoes-list" id="listaPermissoes">
                    <!-- Preenchido via JavaScript -->
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fecharModal('modalPermissoes')">Fechar</button>
        </div>
    </div>
</div>

<!-- Modal Usuário -->
<div class="modal-overlay" id="modalUsuario">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <span class="modal-title" id="modalUsuarioTitulo">Novo Usuário</span>
            <button type="button" class="modal-close" onclick="fecharModal('modalUsuario')">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="usuarioId">
            
            <!-- Campo Login com busca no AD -->
            <div class="form-group-modal">
                <label>Login (AD) <span class="required">*</span></label>
                <div class="input-group-ad">
                    <input type="text" class="form-control" id="usuarioLogin" maxlength="50" placeholder="Digite o login do usuário">
                    <button type="button" class="btn-buscar-ad" id="btnBuscarAD" onclick="buscarUsuarioAD()">
                        <ion-icon name="search-outline"></ion-icon>
                        Buscar no AD
                    </button>
                </div>
                <small class="form-hint" id="hintAD">Digite o login e clique em "Buscar no AD" para preencher os dados</small>
            </div>

            <!-- Matrícula -->
            <div class="form-group-modal">
                <label>Matrícula</label>
                <input type="text" class="form-control" id="usuarioMatricula" maxlength="20" placeholder="Preenchido automaticamente do AD" readonly>
            </div>

            <!-- Nome -->
            <div class="form-group-modal">
                <label>Nome <span class="required">*</span></label>
                <input type="text" class="form-control" id="usuarioNome" maxlength="200" placeholder="Preenchido automaticamente do AD" readonly>
            </div>

            <!-- E-mail -->
            <div class="form-group-modal">
                <label>E-mail</label>
                <input type="email" class="form-control" id="usuarioEmail" maxlength="200" placeholder="Preenchido automaticamente do AD" readonly>
            </div>

            <!-- Grupo de Usuário -->
            <div class="form-group-modal">
                <label>Grupo de Usuário <span class="required">*</span></label>
                <select class="form-control" id="usuarioGrupo">
                    <option value="">Selecione...</option>
                    <?php foreach ($gruposUsuario as $g): ?>
                    <option value="<?= $g['CD_GRUPO_USUARIO'] ?>"><?= htmlspecialchars($g['DS_NOME']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status -->
            <div class="form-group-modal">
                <label>Status</label>
                <div class="status-toggle">
                    <label class="toggle-option">
                        <input type="radio" name="usuarioStatus" value="0" checked>
                        <span class="toggle-label ativo">
                            <ion-icon name="checkmark-circle-outline"></ion-icon>
                            Ativo
                        </span>
                    </label>
                    <label class="toggle-option">
                        <input type="radio" name="usuarioStatus" value="1">
                        <span class="toggle-label inativo">
                            <ion-icon name="ban-outline"></ion-icon>
                            Inativo
                        </span>
                    </label>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fecharModal('modalUsuario')">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="salvarUsuario()">
                <ion-icon name="checkmark-outline"></ion-icon>
                Salvar
            </button>
        </div>
    </div>
</div>

<!-- Estilos adicionais para o modal de Usuário -->
<style>
    .input-group-ad {
        display: flex;
        gap: 10px;
    }
    
    .input-group-ad .form-control {
        flex: 1;
    }
    
    .btn-buscar-ad {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 16px;
        background: #059669;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    
    .btn-buscar-ad:hover {
        background: #047857;
    }
    
    .btn-buscar-ad:disabled {
        background: #9ca3af;
        cursor: not-allowed;
    }
    
    .btn-buscar-ad ion-icon {
        font-size: 16px;
    }
    
    .form-hint {
        display: block;
        font-size: 11px;
        color: #64748b;
        margin-top: 6px;
    }
    
    .form-hint.success {
        color: #059669;
    }
    
    .form-hint.error {
        color: #dc2626;
    }
    
    /* Campos readonly - preenchidos pelo AD */
    .modal-body .form-control[readonly] {
        background-color: #f1f5f9;
        color: #64748b;
        cursor: not-allowed;
        border-style: dashed;
    }
    
    .status-toggle {
        display: flex;
        gap: 12px;
    }
    
    .toggle-option {
        cursor: pointer;
    }
    
    .toggle-option input {
        display: none;
    }
    
    .toggle-label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.2s ease;
        border: 2px solid #e2e8f0;
        background: #f8fafc;
        color: #64748b;
    }
    
    .toggle-option input:checked + .toggle-label.ativo {
        background: #dcfce7;
        border-color: #22c55e;
        color: #15803d;
    }
    
    .toggle-option input:checked + .toggle-label.inativo {
        background: #fee2e2;
        border-color: #ef4444;
        color: #dc2626;
    }
    
    .toggle-label ion-icon {
        font-size: 16px;
    }
    
    /* Badge de status na tabela */
    .badge-ativo {
        background: #dcfce7;
        color: #15803d;
    }
    
    .badge-inativo {
        background: #fee2e2;
        color: #dc2626;
    }
    
    /* Botão de bloquear/desbloquear */
    .btn-action.block:hover {
        background: #fef3c7;
        border-color: #f59e0b;
        color: #d97706;
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // ============================================
    // Configurações Globais
    // ============================================
    const podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;
    const porPagina = 20;
    let debounceTimers = {};

    // Estado de paginação para cada aba
    let estadoPaginacao = {
        funcionalidades: { pagina: 1, total: 0, totalPaginas: 0 },
        grupoUsuario: { pagina: 1, total: 0, totalPaginas: 0 },
        usuarios: { pagina: 1, total: 0, totalPaginas: 0 }
    };

    // Estado de ordenação para cada aba
    let estadoOrdenacao = {
        usuarios: { coluna: 'DS_NOME', ordem: 'ASC' }
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
        btn.addEventListener('click', function() {
            const tab = this.dataset.tab;
            ativarAba(tab);

            // Atualizar URL sem recarregar a página
            const url = new URL(window.location);
            url.searchParams.set('aba', tab);
            history.replaceState(null, '', url);
        });
    });

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
    // FUNCIONALIDADES
    // ============================================
    function buscarFuncionalidades(pagina = 1) {
        const filtro = document.getElementById('filtroFuncionalidade').value;
        const loading = document.getElementById('loadingFuncionalidade');
        const tbody = document.getElementById('tabelaFuncionalidade');
        
        loading.classList.add('active');
        
        $.get('bd/funcionalidades/getFuncionalidades.php', { 
            busca: filtro, 
            pagina: pagina,
            porPagina: porPagina
        }, function(response) {
            loading.classList.remove('active');
            
            if (response.success) {
                estadoPaginacao.funcionalidades = {
                    pagina: response.pagina,
                    total: response.total,
                    totalPaginas: response.totalPaginas
                };
                
                document.getElementById('countFuncionalidade').innerHTML = 
                    `<strong>${response.total}</strong> registro(s) encontrado(s)`;
                
                let html = '';
                if (response.data.length > 0) {
                    response.data.forEach(item => {
                        html += `
                            <tr>
                                <td class="code">${item.CD_FUNCIONALIDADE}</td>
                                <td class="name">${item.DS_NOME || ''}</td>
                                <td>
                                    <div class="table-actions">
                                        <button type="button" class="btn-action" onclick="verGruposDaFuncionalidade('${(item.DS_NOME || '').replace(/'/g, "\\'")}')" title="Ver grupos com acesso">
                                            <ion-icon name="people-outline"></ion-icon>
                                        </button>
                                        ${podeEditar ? `
                                        <button type="button" class="btn-action" onclick="editarFuncionalidade(${item.CD_FUNCIONALIDADE})" title="Editar">
                                            <ion-icon name="create-outline"></ion-icon>
                                        </button>
                                        <button type="button" class="btn-action delete" onclick="excluirFuncionalidade(${item.CD_FUNCIONALIDADE})" title="Excluir">
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
                renderizarPaginacao('paginacaoFuncionalidade', estadoPaginacao.funcionalidades, 'buscarFuncionalidades');
            }
        }, 'json').fail(function() {
            loading.classList.remove('active');
            showToast('Erro ao buscar funcionalidades', 'erro');
        });
    }

    function verGruposDaFuncionalidade(nomeFuncionalidade) {
        ativarAba('grupoUsuario');
        const url = new URL(window.location);
        url.searchParams.set('aba', 'grupoUsuario');
        history.replaceState(null, '', url);
        document.getElementById('filtroGrupoUsuario').value = nomeFuncionalidade;
        abasCarregadas['grupoUsuario'] = true;
        buscarGruposUsuario(1);
    }

    function abrirModalFuncionalidade() {
        document.getElementById('funcionalidadeId').value = '';
        document.getElementById('funcionalidadeNome').value = '';
        document.getElementById('modalFuncionalidadeTitulo').textContent = 'Nova Funcionalidade';
        abrirModal('modalFuncionalidade');
        document.getElementById('funcionalidadeNome').focus();
    }

    function editarFuncionalidade(id) {
        $.get('bd/funcionalidades/getFuncionalidade.php', { id: id }, function(response) {
            if (response.success && response.data) {
                document.getElementById('funcionalidadeId').value = response.data.CD_FUNCIONALIDADE;
                document.getElementById('funcionalidadeNome').value = response.data.DS_NOME || '';
                document.getElementById('modalFuncionalidadeTitulo').textContent = 'Editar Funcionalidade';
                abrirModal('modalFuncionalidade');
                document.getElementById('funcionalidadeNome').focus();
            } else {
                showToast('Erro ao carregar dados', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function salvarFuncionalidade() {
        const dados = {
            id: document.getElementById('funcionalidadeId').value,
            nome: document.getElementById('funcionalidadeNome').value.trim()
        };
        
        if (!dados.nome) {
            showToast('Preencha o nome da funcionalidade', 'erro');
            document.getElementById('funcionalidadeNome').focus();
            return;
        }
        
        $.post('bd/funcionalidades/salvarFuncionalidade.php', dados, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                fecharModal('modalFuncionalidade');
                buscarFuncionalidades(estadoPaginacao.funcionalidades.pagina);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function excluirFuncionalidade(id) {
        if (!confirm('Deseja realmente excluir esta funcionalidade?')) return;
        
        $.post('bd/funcionalidades/excluirFuncionalidade.php', { id: id }, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                buscarFuncionalidades(estadoPaginacao.funcionalidades.pagina);
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // GRUPO DE USUÁRIO
    // ============================================
    function buscarGruposUsuario(pagina = 1) {
        const filtro = document.getElementById('filtroGrupoUsuario').value;
        const loading = document.getElementById('loadingGrupoUsuario');
        const tbody = document.getElementById('tabelaGrupoUsuario');
        
        loading.classList.add('active');
        
        $.get('bd/grupoUsuario/getGruposUsuario.php', { 
            busca: filtro, 
            pagina: pagina,
            porPagina: porPagina
        }, function(response) {
            loading.classList.remove('active');
            
            if (response.success) {
                estadoPaginacao.grupoUsuario = {
                    pagina: response.pagina,
                    total: response.total,
                    totalPaginas: response.totalPaginas
                };
                
                document.getElementById('countGrupoUsuario').innerHTML = 
                    `<strong>${response.total}</strong> registro(s) encontrado(s)`;
                
                let html = '';
                if (response.data.length > 0) {
                    response.data.forEach(item => {
                        html += `
                            <tr>
                                <td class="code">${item.CD_GRUPO_USUARIO}</td>
                                <td class="name">${item.DS_NOME || ''}</td>
                                <td><span class="badge badge-info badge-clickable" onclick="navegarParaUsuariosDoGrupo(${item.CD_GRUPO_USUARIO})" title="Ver usuários deste grupo"><ion-icon name="people-outline"></ion-icon> ${item.QTD_USUARIOS || 0}</span></td>
                                <td>
                                    <span class="badge badge-success badge-clickable" onclick="abrirModalPermissoes(${item.CD_GRUPO_USUARIO}, '${(item.DS_NOME || '').replace(/'/g, "\\'")}')">
                                        <ion-icon name="key-outline"></ion-icon>
                                        ${item.QTD_PERMISSOES || 0}
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        ${podeEditar ? `
                                        <button type="button" class="btn-action" onclick="abrirModalPermissoes(${item.CD_GRUPO_USUARIO}, '${(item.DS_NOME || '').replace(/'/g, "\\'")}')" title="Permissões">
                                            <ion-icon name="key-outline"></ion-icon>
                                        </button>
                                        <button type="button" class="btn-action" onclick="editarGrupoUsuario(${item.CD_GRUPO_USUARIO})" title="Editar">
                                            <ion-icon name="create-outline"></ion-icon>
                                        </button>
                                        <button type="button" class="btn-action delete" onclick="excluirGrupoUsuario(${item.CD_GRUPO_USUARIO})" title="Excluir">
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
                renderizarPaginacao('paginacaoGrupoUsuario', estadoPaginacao.grupoUsuario, 'buscarGruposUsuario');
            }
        }, 'json').fail(function() {
            loading.classList.remove('active');
            showToast('Erro ao buscar grupos de usuário', 'erro');
        });
    }

    function navegarParaUsuariosDoGrupo(cdGrupo) {
        // Ativar a aba Usuários
        ativarAba('usuarios');

        // Atualizar URL
        const url = new URL(window.location);
        url.searchParams.set('aba', 'usuarios');
        history.replaceState(null, '', url);

        // Setar o filtro de grupo
        document.getElementById('filtroUsuarioGrupo').value = cdGrupo;

        // Limpar outros filtros
        document.getElementById('filtroUsuario').value = '';
        const radioTodos = document.querySelector('input[name="filtroUsuarioStatus"][value=""]');
        if (radioTodos) radioTodos.checked = true;

        // Marcar aba como carregada e buscar usuários filtrados
        abasCarregadas['usuarios'] = true;
        buscarUsuarios(1);
    }

    function abrirModalGrupoUsuario() {
        document.getElementById('grupoUsuarioId').value = '';
        document.getElementById('grupoUsuarioNome').value = '';
        document.getElementById('modalGrupoUsuarioTitulo').textContent = 'Novo Grupo de Usuário';
        abrirModal('modalGrupoUsuario');
        document.getElementById('grupoUsuarioNome').focus();
    }

    function editarGrupoUsuario(id) {
        $.get('bd/grupoUsuario/getGrupoUsuario.php', { id: id }, function(response) {
            if (response.success && response.data) {
                document.getElementById('grupoUsuarioId').value = response.data.CD_GRUPO_USUARIO;
                document.getElementById('grupoUsuarioNome').value = response.data.DS_NOME || '';
                document.getElementById('modalGrupoUsuarioTitulo').textContent = 'Editar Grupo de Usuário';
                abrirModal('modalGrupoUsuario');
                document.getElementById('grupoUsuarioNome').focus();
            } else {
                showToast('Erro ao carregar dados', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function salvarGrupoUsuario() {
        const dados = {
            id: document.getElementById('grupoUsuarioId').value,
            nome: document.getElementById('grupoUsuarioNome').value.trim()
        };
        
        if (!dados.nome) {
            showToast('Preencha o nome do grupo', 'erro');
            document.getElementById('grupoUsuarioNome').focus();
            return;
        }
        
        $.post('bd/grupoUsuario/salvarGrupoUsuario.php', dados, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                fecharModal('modalGrupoUsuario');
                buscarGruposUsuario(estadoPaginacao.grupoUsuario.pagina);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function excluirGrupoUsuario(id) {
        if (!confirm('Deseja realmente excluir este grupo de usuário?')) return;
        
        $.post('bd/grupoUsuario/excluirGrupoUsuario.php', { id: id }, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                buscarGruposUsuario(estadoPaginacao.grupoUsuario.pagina);
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
    document.getElementById('filtroFuncionalidade').addEventListener('input', 
        debounce(function() { buscarFuncionalidades(1); }, 400, 'funcionalidades'));

    document.getElementById('filtroGrupoUsuario').addEventListener('input', 
        debounce(function() { buscarGruposUsuario(1); }, 400, 'grupoUsuario'));

    // Enter para salvar nos modais
    document.getElementById('funcionalidadeNome').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') salvarFuncionalidade();
    });

    document.getElementById('grupoUsuarioNome').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') salvarGrupoUsuario();
    });

    // ============================================
    // USUÁRIOS
    // ============================================
    function buscarUsuarios(pagina = 1) {
        const filtro = document.getElementById('filtroUsuario').value;
        const grupo = document.getElementById('filtroUsuarioGrupo').value;
        const status = document.querySelector('input[name="filtroUsuarioStatus"]:checked').value;
        const loading = document.getElementById('loadingUsuario');
        const tbody = document.getElementById('tabelaUsuario');
        
        loading.classList.add('active');
        
        $.get('bd/usuarios/getUsuarios.php', { 
            busca: filtro, 
            cd_grupo: grupo,
            status: status,
            pagina: pagina,
            porPagina: porPagina,
            ordenarPor: estadoOrdenacao.usuarios.coluna,
            ordem: estadoOrdenacao.usuarios.ordem
        }, function(response) {
            loading.classList.remove('active');
            
            if (response.success) {
                estadoPaginacao.usuarios = {
                    pagina: response.pagina,
                    total: response.total,
                    totalPaginas: response.totalPaginas
                };
                
                document.getElementById('countUsuario').innerHTML = 
                    `<strong>${response.total}</strong> usuário(s) encontrado(s)`;
                
                // Atualizar indicadores visuais de ordenação
                atualizarIndicadorOrdenacao('tabelaUsuariosTable', estadoOrdenacao.usuarios.coluna, estadoOrdenacao.usuarios.ordem);
                
                let html = '';
                if (response.data.length > 0) {
                    response.data.forEach(item => {
                        const statusClass = item.OP_BLOQUEADO == 1 ? 'badge-inativo' : 'badge-ativo';
                        const statusTexto = item.OP_BLOQUEADO == 1 ? 'Inativo' : 'Ativo';
                        const statusIcon = item.OP_BLOQUEADO == 1 ? 'ban-outline' : 'checkmark-circle-outline';
                        
                        html += `
                            <tr>
                                <td class="code">${item.CD_USUARIO}</td>
                                <td>${item.DS_MATRICULA || '-'}</td>
                                <td><strong>${item.DS_LOGIN || '-'}</strong></td>
                                <td class="name">${item.DS_NOME || ''}</td>
                                <td class="truncate">${item.DS_EMAIL || '-'}</td>
                                <td>${item.DS_GRUPO || '-'}</td>
                                <td>
                                    <span class="badge ${statusClass}">
                                        <ion-icon name="${statusIcon}"></ion-icon>
                                        ${statusTexto}
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        ${podeEditar ? `
                                        <button type="button" class="btn-action" onclick="editarUsuario(${item.CD_USUARIO})" title="Editar">
                                            <ion-icon name="create-outline"></ion-icon>
                                        </button>
                                        <button type="button" class="btn-action block" onclick="alternarBloqueioUsuario(${item.CD_USUARIO})" title="${item.OP_BLOQUEADO == 1 ? 'Ativar' : 'Desativar'}">
                                            <ion-icon name="${item.OP_BLOQUEADO == 1 ? 'checkmark-circle-outline' : 'ban-outline'}"></ion-icon>
                                        </button>
                                        ` : ''}
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    html = `<tr><td colspan="8">
                        <div class="empty-state">
                            <div class="empty-state-icon"><ion-icon name="person-outline"></ion-icon></div>
                            <h3>Nenhum usuário encontrado</h3>
                            <p>Tente ajustar os filtros de busca</p>
                        </div>
                    </td></tr>`;
                }
                
                tbody.innerHTML = html;
                renderizarPaginacao('paginacaoUsuario', estadoPaginacao.usuarios, 'buscarUsuarios');
            }
        }, 'json').fail(function() {
            loading.classList.remove('active');
            showToast('Erro ao buscar usuários', 'erro');
        });
    }

    // Função para ordenar usuários
    function ordenarUsuarios(coluna) {
        // Se clicar na mesma coluna, inverte a ordem
        if (estadoOrdenacao.usuarios.coluna === coluna) {
            estadoOrdenacao.usuarios.ordem = estadoOrdenacao.usuarios.ordem === 'ASC' ? 'DESC' : 'ASC';
        } else {
            estadoOrdenacao.usuarios.coluna = coluna;
            estadoOrdenacao.usuarios.ordem = 'ASC';
        }
        
        // Voltar para a primeira página ao ordenar
        buscarUsuarios(1);
    }

    // Função para atualizar indicadores visuais de ordenação
    function atualizarIndicadorOrdenacao(tabelaId, coluna, ordem) {
        const tabela = document.getElementById(tabelaId);
        if (!tabela) return;
        
        // Remover classes de todas as colunas
        tabela.querySelectorAll('th.sortable').forEach(th => {
            th.classList.remove('asc', 'desc');
        });
        
        // Adicionar classe na coluna ativa
        const colunaAtiva = tabela.querySelector(`th[data-column="${coluna}"]`);
        if (colunaAtiva) {
            colunaAtiva.classList.add(ordem.toLowerCase());
        }
    }

    function abrirModalUsuario() {
        document.getElementById('usuarioId').value = '';
        document.getElementById('usuarioLogin').value = '';
        document.getElementById('usuarioLogin').readOnly = false;
        document.getElementById('usuarioMatricula').value = '';
        document.getElementById('usuarioMatricula').readOnly = true;
        document.getElementById('usuarioNome').value = '';
        document.getElementById('usuarioNome').readOnly = true;
        document.getElementById('usuarioEmail').value = '';
        document.getElementById('usuarioEmail').readOnly = true;
        document.getElementById('usuarioGrupo').value = '';
        document.querySelector('input[name="usuarioStatus"][value="0"]').checked = true;
        document.getElementById('btnBuscarAD').style.display = 'inline-flex';
        document.getElementById('hintAD').textContent = 'Digite o login e clique em "Buscar no AD" para preencher os dados';
        document.getElementById('hintAD').className = 'form-hint';
        document.getElementById('modalUsuarioTitulo').textContent = 'Novo Usuário';
        abrirModal('modalUsuario');
    }

    function buscarUsuarioAD() {
        const login = document.getElementById('usuarioLogin').value.trim();
        const usuarioId = document.getElementById('usuarioId').value;
        
        if (!login) {
            showToast('Digite o login do usuário', 'erro');
            return;
        }
        
        const btn = document.getElementById('btnBuscarAD');
        const hintEl = document.getElementById('hintAD');
        
        btn.disabled = true;
        btn.innerHTML = '<ion-icon name="hourglass-outline"></ion-icon> Buscando...';
        hintEl.textContent = 'Buscando no Active Directory...';
        hintEl.className = 'form-hint';
        
        // Passar o ID do usuário atual para ignorar na verificação de duplicidade
        $.get('bd/usuarios/buscarUsuarioAD.php', { login: login, id_atual: usuarioId }, function(response) {
            btn.disabled = false;
            btn.innerHTML = '<ion-icon name="search-outline"></ion-icon> Buscar no AD';
            
            if (response.success) {
                // Preencher campos
                document.getElementById('usuarioMatricula').value = response.data.matricula || '';
                document.getElementById('usuarioNome').value = response.data.nome || '';
                document.getElementById('usuarioEmail').value = response.data.email || '';
                
                hintEl.textContent = '✓ Dados carregados do Active Directory';
                hintEl.className = 'form-hint success';
                
                showToast('Dados carregados do AD com sucesso!', 'sucesso');
            } else {
                hintEl.textContent = response.message;
                hintEl.className = 'form-hint error';
                
                // Se tiver sugestões, mostrar
                if (response.sugestoes && response.sugestoes.length > 0) {
                    let sugestaoTexto = 'Sugestões: ' + response.sugestoes.map(s => s.login).join(', ');
                    hintEl.textContent = response.message + ' ' + sugestaoTexto;
                }
            }
        }, 'json').fail(function() {
            btn.disabled = false;
            btn.innerHTML = '<ion-icon name="search-outline"></ion-icon> Buscar no AD';
            hintEl.textContent = 'Erro ao comunicar com o servidor';
            hintEl.className = 'form-hint error';
        });
    }

    function editarUsuario(id) {
        $.get('bd/usuarios/getUsuario.php', { id: id }, function(response) {
            if (response.success && response.data) {
                document.getElementById('usuarioId').value = response.data.CD_USUARIO;
                document.getElementById('usuarioLogin').value = response.data.DS_LOGIN || '';
                document.getElementById('usuarioLogin').readOnly = false; // Permite editar login
                document.getElementById('usuarioMatricula').value = response.data.DS_MATRICULA || '';
                document.getElementById('usuarioMatricula').readOnly = true; // Somente leitura
                document.getElementById('usuarioNome').value = response.data.DS_NOME || '';
                document.getElementById('usuarioNome').readOnly = true; // Somente leitura
                document.getElementById('usuarioEmail').value = response.data.DS_EMAIL || '';
                document.getElementById('usuarioEmail').readOnly = true; // Somente leitura
                document.getElementById('usuarioGrupo').value = response.data.CD_GRUPO_USUARIO || '';
                
                const status = response.data.OP_BLOQUEADO == 1 ? '1' : '0';
                document.querySelector(`input[name="usuarioStatus"][value="${status}"]`).checked = true;
                
                document.getElementById('btnBuscarAD').style.display = 'inline-flex';
                document.getElementById('hintAD').textContent = 'Para alterar o login, digite o novo login e clique em "Buscar no AD"';
                document.getElementById('hintAD').className = 'form-hint';
                
                document.getElementById('modalUsuarioTitulo').textContent = 'Editar Usuário';
                abrirModal('modalUsuario');
            } else {
                showToast('Erro ao carregar dados do usuário', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function salvarUsuario() {
        const dados = {
            id: document.getElementById('usuarioId').value,
            login: document.getElementById('usuarioLogin').value,
            matricula: document.getElementById('usuarioMatricula').value,
            nome: document.getElementById('usuarioNome').value,
            email: document.getElementById('usuarioEmail').value,
            cd_grupo: document.getElementById('usuarioGrupo').value,
            bloqueado: document.querySelector('input[name="usuarioStatus"]:checked').value
        };
        
        if (!dados.login) {
            showToast('Preencha o login do usuário', 'erro');
            return;
        }
        
        if (!dados.nome) {
            showToast('Preencha o nome do usuário', 'erro');
            return;
        }
        
        if (!dados.cd_grupo) {
            showToast('Selecione o grupo do usuário', 'erro');
            return;
        }
        
        $.post('bd/usuarios/salvarUsuario.php', dados, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                fecharModal('modalUsuario');
                buscarUsuarios(estadoPaginacao.usuarios.pagina);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function alternarBloqueioUsuario(id) {
        if (!confirm('Deseja alterar o status deste usuário?')) return;
        
        $.post('bd/usuarios/alternarBloqueio.php', { id: id }, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                buscarUsuarios(estadoPaginacao.usuarios.pagina);
            } else {
                showToast(response.message || 'Erro ao alterar status', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // Event Listeners de busca para Usuários
    document.getElementById('filtroUsuario').addEventListener('input', 
        debounce(function() { buscarUsuarios(1); }, 400, 'usuarios'));
    
    document.getElementById('filtroUsuarioGrupo').addEventListener('change', function() { 
        buscarUsuarios(1); 
    });
    
    document.querySelectorAll('input[name="filtroUsuarioStatus"]').forEach(radio => {
        radio.addEventListener('change', function() { 
            buscarUsuarios(1); 
        });
    });

    // ============================================
    // Carregar dados ao iniciar
    // ============================================
    function carregarDadosAba(nomeAba) {
        if (!abasCarregadas[nomeAba]) {
            abasCarregadas[nomeAba] = true;
            switch(nomeAba) {
                case 'funcionalidades': buscarFuncionalidades(1); break;
                case 'grupoUsuario': buscarGruposUsuario(1); break;
                case 'usuarios': buscarUsuarios(1); break;
            }
        }
    }

    let abasCarregadas = {};

    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const abaParam = urlParams.get('aba');

        if (abaParam && document.querySelector(`.tab-btn[data-tab="${abaParam}"]`)) {
            ativarAba(abaParam);
            // Carregar a aba padrão (funcionalidades) em segundo plano + a aba solicitada
            abasCarregadas['funcionalidades'] = true;
            buscarFuncionalidades(1);
            carregarDadosAba(abaParam);
        } else {
            abasCarregadas['funcionalidades'] = true;
            buscarFuncionalidades(1);
        }
    });

    // Carregar dados ao trocar de aba (apenas se não carregou ainda)
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            carregarDadosAba(this.dataset.tab);
        });
    });

    // ============================================
    // PERMISSÕES DO GRUPO
    // ============================================
    let grupoAtualId = null;
    let grupoAtualNome = '';
    let highlightedIndex = -1;
    let funcionalidadesDisponiveis = [];
    let permissoesCarregadas = []; // Guarda todas as permissões para filtrar localmente

    function abrirModalPermissoes(cdGrupo, nomeGrupo) {
        grupoAtualId = cdGrupo;
        grupoAtualNome = nomeGrupo;
        document.getElementById('permissoesGrupoId').value = cdGrupo;
        document.getElementById('modalPermissoesTitulo').textContent = (podeEditar ? 'Permissões - ' : 'Visualizar Permissões - ') + nomeGrupo;
        
        // Limpar campos de edição (se existirem)
        if (podeEditar) {
            document.getElementById('inputFuncionalidade').value = '';
            document.getElementById('inputFuncionalidadeId').value = '';
            document.querySelector('input[name="tipoAcessoNovo"][value="1"]').checked = true;
        }
        
        // Limpar filtro de permissões vinculadas
        document.getElementById('filtroPermissoesVinculadas').value = '';
        document.getElementById('btnLimparFiltroPermissoes').classList.remove('visible');
        
        abrirModal('modalPermissoes');
        carregarPermissoes();
    }

    function carregarPermissoes() {
        const lista = document.getElementById('listaPermissoes');
        
        lista.innerHTML = '<div class="permissoes-empty"><ion-icon name="hourglass-outline"></ion-icon>Carregando...</div>';
        
        $.get('bd/grupoUsuario/getPermissoesGrupo.php', {
            cd_grupo: grupoAtualId
        }, function(response) {
            if (response.success) {
                permissoesCarregadas = response.data; // Guarda para filtrar
                document.getElementById('permissoesCount').textContent = '(' + response.data.length + ')';
                
                // Limpa o filtro ao recarregar
                document.getElementById('filtroPermissoesVinculadas').value = '';
                document.getElementById('btnLimparFiltroPermissoes').classList.remove('visible');
                
                renderizarPermissoes(response.data);
            } else {
                lista.innerHTML = '<div class="permissoes-empty"><ion-icon name="alert-circle-outline"></ion-icon>Erro ao carregar permissões</div>';
            }
        }, 'json').fail(function() {
            lista.innerHTML = '<div class="permissoes-empty"><ion-icon name="alert-circle-outline"></ion-icon>Erro ao carregar permissões</div>';
        });
    }

    function renderizarPermissoes(dados) {
        const lista = document.getElementById('listaPermissoes');
        
        if (dados.length > 0) {
            let html = '';
            dados.forEach(item => {
                const tipoAcessoLabel = item.ID_TIPO_ACESSO == 2 ? 'Acesso Total' : 'Somente Leitura';
                const tipoAcessoBadge = item.ID_TIPO_ACESSO == 2 ? 'badge-success' : 'badge-warning';
                
                if (podeEditar) {
                    // Modo edição: mostra radios e botão excluir
                    html += `
                        <div class="permissao-item" data-cd-chave="${item.CD_CHAVE}">
                            <div class="permissao-info">
                                <span class="permissao-nome">${item.DS_FUNCIONALIDADE}</span>
                            </div>
                            <div class="permissao-acoes">
                                <div class="radio-group-inline">
                                    <label class="radio-item">
                                        <input type="radio" name="acesso_${item.CD_CHAVE}" value="1" 
                                               ${item.ID_TIPO_ACESSO == 1 ? 'checked' : ''} 
                                               onchange="atualizarTipoAcesso(${item.CD_CHAVE}, 1)">
                                        <span class="radio-label">Leitura</span>
                                    </label>
                                    <label class="radio-item">
                                        <input type="radio" name="acesso_${item.CD_CHAVE}" value="2" 
                                               ${item.ID_TIPO_ACESSO == 2 ? 'checked' : ''} 
                                               onchange="atualizarTipoAcesso(${item.CD_CHAVE}, 2)">
                                        <span class="radio-label">Total</span>
                                    </label>
                                </div>
                                <button type="button" class="btn-remove-permissao" onclick="removerPermissao(${item.CD_CHAVE})" title="Remover">
                                    <ion-icon name="trash-outline"></ion-icon>
                                </button>
                            </div>
                        </div>
                    `;
                } else {
                    // Modo visualização: mostra apenas badge com tipo de acesso
                    html += `
                        <div class="permissao-item" data-cd-chave="${item.CD_CHAVE}">
                            <div class="permissao-info">
                                <span class="permissao-nome">${item.DS_FUNCIONALIDADE}</span>
                            </div>
                            <div class="permissao-acoes">
                                <span class="badge ${tipoAcessoBadge}">${tipoAcessoLabel}</span>
                            </div>
                        </div>
                    `;
                }
            });
            lista.innerHTML = html;
        } else {
            lista.innerHTML = '<div class="permissoes-empty"><ion-icon name="key-outline"></ion-icon>Nenhuma permissão encontrada</div>';
        }
    }

    function filtrarPermissoesVinculadas() {
        const termo = document.getElementById('filtroPermissoesVinculadas').value.toLowerCase().trim();
        const btnLimpar = document.getElementById('btnLimparFiltroPermissoes');
        
        // Mostrar/ocultar botão limpar
        if (termo.length > 0) {
            btnLimpar.classList.add('visible');
        } else {
            btnLimpar.classList.remove('visible');
        }
        
        // Filtrar dados
        if (termo === '') {
            renderizarPermissoes(permissoesCarregadas);
        } else {
            const filtradas = permissoesCarregadas.filter(item => 
                item.DS_FUNCIONALIDADE.toLowerCase().includes(termo)
            );
            renderizarPermissoes(filtradas);
        }
    }

    function limparFiltroPermissoes() {
        document.getElementById('filtroPermissoesVinculadas').value = '';
        document.getElementById('btnLimparFiltroPermissoes').classList.remove('visible');
        renderizarPermissoes(permissoesCarregadas);
    }

    // Event listener para filtro de permissões vinculadas
    document.getElementById('filtroPermissoesVinculadas').addEventListener('input', 
        debounce(function() { filtrarPermissoesVinculadas(); }, 200, 'filtroPermissoes'));

    function atualizarTipoAcesso(cdChave, tipoAcesso) {
        $.post('bd/grupoUsuario/atualizarPermissao.php', {
            cd_chave: cdChave,
            tipo_acesso: tipoAcesso
        }, function(response) {
            if (response.success) {
                // Atualiza também no array local
                const item = permissoesCarregadas.find(p => p.CD_CHAVE == cdChave);
                if (item) item.ID_TIPO_ACESSO = tipoAcesso;
                
                showToast('Tipo de acesso atualizado!', 'sucesso');
            } else {
                showToast(response.message || 'Erro ao atualizar', 'erro');
                carregarPermissoes(); // Recarrega para reverter
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
            carregarPermissoes();
        });
    }

    function removerPermissao(cdChave) {
        if (!confirm('Deseja realmente remover esta permissão?')) return;
        
        $.post('bd/grupoUsuario/excluirPermissao.php', {
            cd_chave: cdChave
        }, function(response) {
            if (response.success) {
                showToast('Permissão removida!', 'sucesso');
                carregarPermissoes();
                buscarGruposUsuario(estadoPaginacao.grupoUsuario.pagina); // Atualiza contador
            } else {
                showToast(response.message || 'Erro ao remover', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // AÇÕES EM MASSA
    // ============================================
    function incluirTodasFuncionalidades() {
        const tipoAcesso = document.querySelector('input[name="tipoAcessoNovo"]:checked').value;
        
        if (!confirm('Deseja incluir TODAS as funcionalidades com o tipo de acesso "' + 
            (tipoAcesso == 1 ? 'Somente Leitura' : 'Acesso Total') + '"?')) return;
        
        $.post('bd/grupoUsuario/incluirTodasPermissoes.php', {
            cd_grupo: grupoAtualId,
            tipo_acesso: tipoAcesso
        }, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                carregarPermissoes();
                buscarGruposUsuario(estadoPaginacao.grupoUsuario.pagina);
            } else {
                showToast(response.message || 'Erro ao incluir', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function excluirTodasFuncionalidades() {
        if (!confirm('Deseja realmente EXCLUIR TODAS as permissões deste grupo?')) return;
        
        $.post('bd/grupoUsuario/excluirTodasPermissoes.php', {
            cd_grupo: grupoAtualId
        }, function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                carregarPermissoes();
                buscarGruposUsuario(estadoPaginacao.grupoUsuario.pagina);
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // AUTOCOMPLETE FUNCIONALIDADES
    // ============================================
    function buscarFuncionalidadesDisponiveis(termo) {
        const dropdown = document.getElementById('dropdownFuncionalidade');
        
        $.get('bd/grupoUsuario/getFuncionalidadesDisponiveis.php', {
            cd_grupo: grupoAtualId,
            busca: termo || ''
        }, function(response) {
            if (response.success) {
                funcionalidadesDisponiveis = response.data;
                highlightedIndex = -1;
                
                if (response.data.length > 0) {
                    let html = '';
                    response.data.forEach((item, index) => {
                        html += `<div class="autocomplete-item" data-index="${index}" 
                                     data-cd="${item.CD_FUNCIONALIDADE}" 
                                     data-nome="${item.DS_NOME}"
                                     onclick="selecionarFuncionalidade(${item.CD_FUNCIONALIDADE}, '${item.DS_NOME.replace(/'/g, "\\'")}')">
                                    ${item.DS_NOME}
                                </div>`;
                    });
                    dropdown.innerHTML = html;
                } else {
                    dropdown.innerHTML = '<div class="autocomplete-item no-results">Todas as funcionalidades já foram adicionadas</div>';
                }
                dropdown.classList.add('active');
            }
        }, 'json');
    }

    function selecionarFuncionalidade(cdFuncionalidade, nome) {
        document.getElementById('inputFuncionalidade').value = nome;
        document.getElementById('inputFuncionalidadeId').value = cdFuncionalidade;
        document.getElementById('dropdownFuncionalidade').classList.remove('active');
    }

    function adicionarFuncionalidadeAoGrupo() {
        const cdFuncionalidade = document.getElementById('inputFuncionalidadeId').value;
        const nomeFuncionalidade = document.getElementById('inputFuncionalidade').value;
        
        if (!cdFuncionalidade || !nomeFuncionalidade) {
            showToast('Selecione uma funcionalidade', 'erro');
            document.getElementById('inputFuncionalidade').focus();
            return;
        }
        
        const tipoAcesso = document.querySelector('input[name="tipoAcessoNovo"]:checked').value;
        
        $.post('bd/grupoUsuario/adicionarPermissao.php', {
            cd_grupo: grupoAtualId,
            cd_funcionalidade: cdFuncionalidade,
            tipo_acesso: tipoAcesso
        }, function(response) {
            if (response.success) {
                showToast('Funcionalidade adicionada!', 'sucesso');
                
                // Limpar campo
                document.getElementById('inputFuncionalidade').value = '';
                document.getElementById('inputFuncionalidadeId').value = '';
                document.querySelector('input[name="tipoAcessoNovo"][value="1"]').checked = true;
                
                carregarPermissoes();
                buscarGruposUsuario(estadoPaginacao.grupoUsuario.pagina); // Atualiza contador
            } else {
                showToast(response.message || 'Erro ao adicionar', 'erro');
            }
        }, 'json').fail(function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // Event listeners para autocomplete (só se pode editar)
    if (podeEditar) {
        document.getElementById('inputFuncionalidade').addEventListener('input', 
            debounce(function(e) { 
                buscarFuncionalidadesDisponiveis(e.target.value); 
            }, 300, 'autocomplete'));

        // Mostrar dropdown ao focar no campo (mesmo vazio)
        document.getElementById('inputFuncionalidade').addEventListener('focus', function() {
            buscarFuncionalidadesDisponiveis(this.value);
        });

        document.getElementById('inputFuncionalidade').addEventListener('keydown', function(e) {
            const dropdown = document.getElementById('dropdownFuncionalidade');
            const items = dropdown.querySelectorAll('.autocomplete-item:not(.no-results)');
            
            if (!dropdown.classList.contains('active') || items.length === 0) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    adicionarFuncionalidadeAoGrupo();
                }
                return;
            }
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1);
                atualizarHighlight(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightedIndex = Math.max(highlightedIndex - 1, 0);
                atualizarHighlight(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (highlightedIndex >= 0 && items[highlightedIndex]) {
                    const item = items[highlightedIndex];
                    selecionarFuncionalidade(item.dataset.cd, item.dataset.nome);
                }
            } else if (e.key === 'Escape') {
                dropdown.classList.remove('active');
            }
        });

        // Fechar dropdown ao clicar fora
        document.addEventListener('click', function(e) {
            const container = document.getElementById('autocompleteFuncionalidade');
            if (container && !container.contains(e.target)) {
                document.getElementById('dropdownFuncionalidade').classList.remove('active');
            }
        });

        // Limpar seleção se alterar texto manualmente
        document.getElementById('inputFuncionalidade').addEventListener('input', function() {
            document.getElementById('inputFuncionalidadeId').value = '';
        });
    }

    function atualizarHighlight(items) {
        items.forEach((item, index) => {
            item.classList.toggle('highlighted', index === highlightedIndex);
        });
        
        if (highlightedIndex >= 0 && items[highlightedIndex]) {
            items[highlightedIndex].scrollIntoView({ block: 'nearest' });
        }
    }
</script>

<?php include_once 'includes/footer.inc.php'; ?>
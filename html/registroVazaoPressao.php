<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Registro de Vazão e Pressão
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão
exigePermissaoTela('Registro de Vazão e Pressão', ACESSO_LEITURA);

// Permissão do usuário para este módulo
$podeEditar = podeEditarTela('Registro de Vazão e Pressão');

// Buscar Unidades para dropdown
$sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME, CD_CODIGO FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

// Tipos de Registro
$tiposRegistro = [
    1 => 'Vazão',
    2 => 'Pressão',
    3 => 'Volume',
    4 => 'Nível'
];

// Tipos de Vazão
$tiposVazao = [
    1 => 'Estimada',
    2 => 'Macromedida',
    3 => 'Calculada'
];

// Descarte (ID_SITUACAO: 1 = Não, 2 = Sim)
$descartes = [
    1 => 'Não',
    2 => 'Sim'
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
        font-size: 13px;
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
        color: #334155;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

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
        z-index: 9999 !important;
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

    /* Radio Buttons Group */
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

    .radio-item input[type="radio"]:checked+.radio-label {
        background: white;
        color: #1e293b;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .radio-item:hover .radio-label {
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
        margin-bottom: 16px;
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
        font-size: 18px;
    }

    .results-count strong {
        color: #1e293b;
        font-weight: 600;
    }

    .results-actions {
        display: flex;
        gap: 8px;
    }

    .btn-expand {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        background: #f8fafc;
        color: #475569;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-expand:hover {
        background: #eff6ff;
        border-color: #3b82f6;
        color: #3b82f6;
    }

    .btn-expand ion-icon {
        font-size: 16px;
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
        min-width: 1200px;
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

    /* Colunas ordenáveis */
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

    .data-table td.number {
        text-align: right;
        font-family: 'SF Mono', Monaco, 'Consolas', monospace;
        font-size: 12px;
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

    .badge-danger {
        background: #fee2e2;
        color: #dc2626;
    }

    .badge-secondary {
        background: #f1f5f9;
        color: #64748b;
    }

    /* Accordion - Agrupamento por Dia */
    .row-group-header {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        cursor: pointer;
        transition: all 0.2s ease;
        user-select: none;
    }

    .row-group-header:hover {
        background: linear-gradient(135deg, #e2e8f0 0%, #dbeafe 100%);
    }

    .row-group-header td {
        padding: 0 !important;
        border-bottom: 2px solid #e2e8f0;
    }

    .row-group-header .group-content {
        padding: 12px 16px;
    }

    .row-group-header .group-header-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .row-group-header .group-toggle {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }

    .row-group-header .group-toggle ion-icon {
        font-size: 18px;
        color: #3b82f6;
        transition: transform 0.3s ease;
    }

    .row-group-header.expanded .group-toggle ion-icon {
        transform: rotate(90deg);
    }

    .row-group-header .group-date {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        white-space: nowrap;
    }

    .row-group-header .group-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 24px;
        padding: 0 8px;
        background: #3b82f6;
        color: white;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }

    .row-group-header .group-summary {
        font-size: 11px;
        color: #64748b;
        font-weight: 400;
        margin-left: 2px;
        white-space: nowrap;
    }

    .row-group-header .group-alert {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-left: 6px;
        color: #f59e0b;
        font-size: 16px;
    }

    .row-group-header.grupo-incompleto {
        background: linear-gradient(135deg, #fefce8 0%, #fef3c7 100%);
        border-left: 4px solid #f59e0b;
    }

    .row-group-header.grupo-incompleto:hover {
        background: linear-gradient(135deg, #fef9c3 0%, #fde68a 100%);
    }

    .row-group-header .group-stats {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;
    }

    .row-group-header .stat-item {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        color: #64748b;
        background: rgba(255, 255, 255, 0.7);
        padding: 3px 8px;
        border-radius: 4px;
        white-space: nowrap;
    }

    .row-group-header .stat-item ion-icon {
        font-size: 13px;
        color: #3b82f6;
    }

    .row-group-header .stat-item.stat-valid {
        background: rgba(34, 197, 94, 0.1);
        color: #15803d;
    }

    .row-group-header .stat-item.stat-valid ion-icon {
        color: #22c55e;
    }

    .row-group-header.grupo-incompleto .stat-item.stat-valid {
        background: rgba(245, 158, 11, 0.15);
        color: #b45309;
    }

    .row-group-header.grupo-incompleto .stat-item.stat-valid ion-icon {
        color: #f59e0b;
    }

    /* Subgrupo - Ponto de Medição */
    .row-subgroup-header {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        cursor: pointer;
        transition: all 0.2s ease;
        user-select: none;
    }

    .row-subgroup-header:hover {
        background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    }

    .row-subgroup-header td {
        padding: 0 !important;
        border-bottom: 1px solid #e2e8f0;
    }

    .row-subgroup-header .subgroup-content {
        padding: 10px 16px 10px 40px;
    }

    .row-subgroup-header .subgroup-header-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .row-subgroup-header .subgroup-toggle {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }

    .row-subgroup-header .subgroup-toggle ion-icon {
        font-size: 16px;
        color: #6366f1;
        transition: transform 0.3s ease;
    }

    .row-subgroup-header.expanded .subgroup-toggle ion-icon {
        transform: rotate(90deg);
    }

    .row-subgroup-header .subgroup-name {
        font-size: 12px;
        font-weight: 600;
        color: #334155;
        white-space: nowrap;
    }

    .row-subgroup-header .subgroup-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px;
        height: 22px;
        padding: 0 6px;
        background: #6366f1;
        color: white;
        border-radius: 11px;
        font-size: 10px;
        font-weight: 600;
    }

    .row-subgroup-header .subgroup-stats {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }

    .row-subgroup-header .stat-item {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: 10px;
        color: #64748b;
        background: rgba(241, 245, 249, 0.8);
        padding: 2px 6px;
        border-radius: 4px;
        white-space: nowrap;
    }

    .row-subgroup-header .stat-item ion-icon {
        font-size: 12px;
        color: #6366f1;
    }

    .row-subgroup-header .stat-item.stat-valid {
        background: rgba(34, 197, 94, 0.1);
        color: #15803d;
    }

    .row-subgroup-header .stat-item.stat-valid ion-icon {
        color: #22c55e;
    }

    /* Badges de tipo no subgrupo */
    .subgroup-badges {
        display: flex;
        gap: 6px;
        margin-left: 8px;
    }

    .badge-tipo {
        display: inline-flex;
        align-items: center;
        padding: 2px 6px;
        font-size: 9px;
        font-weight: 600;
        border-radius: 4px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    /* Cores por Tipo de Medidor */
    .badge-tipo-medidor {
        background: rgba(99, 102, 241, 0.15);
        color: #4f46e5;
    }

    /* M - Macromedidor (ID=1) - Azul */
    .badge-medidor-1 {
        background: rgba(59, 130, 246, 0.15);
        color: #2563eb;
    }

    /* E - Estação Pitométrica (ID=2) - Verde */
    .badge-medidor-2 {
        background: rgba(34, 197, 94, 0.15);
        color: #16a34a;
    }

    /* P - Medidor Pressão (ID=4) - Laranja */
    .badge-medidor-4 {
        background: rgba(249, 115, 22, 0.15);
        color: #ea580c;
    }

    /* R - Nível Reservatório (ID=6) - Ciano */
    .badge-medidor-6 {
        background: rgba(6, 182, 212, 0.15);
        color: #0891b2;
    }

    /* H - Hidrômetro (ID=8) - Roxo */
    .badge-medidor-8 {
        background: rgba(168, 85, 247, 0.15);
        color: #9333ea;
    }

    .badge-tipo-leitura {
        background: rgba(14, 165, 233, 0.15);
        color: #0284c7;
    }

    .row-subgroup-header .subgroup-alert {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-left: 4px;
        font-size: 14px;
    }

    .row-subgroup-header .btn-chart {
        width: 24px;
        height: 24px;
        margin-left: 6px;
    }

    .row-subgroup-header .btn-chart ion-icon {
        font-size: 14px;
    }

    /* Botão de gráfico no agregador */
    .btn-chart {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 6px;
        color: #3b82f6;
        cursor: pointer;
        transition: all 0.2s ease;
        margin-left: 8px;
    }

    .btn-chart:hover {
        background: #3b82f6;
        color: white;
    }

    .btn-chart ion-icon {
        font-size: 16px;
    }

    /* Checkboxes de seleção para exclusão em massa */
    .checkbox-selecao {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #ef4444;
        margin-right: 8px;
        flex-shrink: 0;
    }

    .checkbox-selecao:hover {
        transform: scale(1.1);
    }

    .row-group-header .checkbox-selecao,
    .row-subgroup-header .checkbox-selecao {
        width: 16px;
        height: 16px;
    }

    /* Barra de ações de exclusão */
    .barra-exclusao {
        display: none;
        position: sticky;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        border-top: 2px solid #ef4444;
        padding: 12px 20px;
        z-index: 100;
        box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.15);
    }

    .barra-exclusao.visivel {
        display: flex;
        align-items: center;
        justify-content: space-between;
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from {
            transform: translateY(100%);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .barra-exclusao-info {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #991b1b;
        font-weight: 500;
    }

    .barra-exclusao-info ion-icon {
        font-size: 24px;
        color: #ef4444;
    }

    .barra-exclusao-count {
        background: #ef4444;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
    }

    .barra-exclusao-acoes {
        display: flex;
        gap: 12px;
    }

    .btn-cancelar-selecao {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        background: white;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        color: #374151;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-cancelar-selecao:hover {
        background: #f3f4f6;
    }

    .btn-excluir-selecionados {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        border: none;
        border-radius: 8px;
        color: white;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-excluir-selecionados:hover {
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
    }

    /* Linha selecionada */
    .row-data.selecionado {
        background: rgba(239, 68, 68, 0.1) !important;
    }

    .row-data.selecionado:hover {
        background: rgba(239, 68, 68, 0.15) !important;
    }

    /* Modal Gráfico */
    .modal-grafico {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 10000;
        justify-content: center;
        align-items: center;
    }

    .modal-grafico.active {
        display: flex;
    }

    .modal-grafico-content {
        background: white;
        border-radius: 16px;
        width: 90%;
        max-width: 900px;
        max-height: 90vh;
        overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    .modal-grafico-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }

    .modal-grafico-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-grafico-header h3 ion-icon {
        color: #3b82f6;
    }

    .modal-grafico-close {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        background: transparent;
        border: none;
        border-radius: 8px;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .modal-grafico-close:hover {
        background: #fee2e2;
        color: #dc2626;
    }

    .modal-grafico-close ion-icon {
        font-size: 24px;
    }

    .modal-grafico-body {
        padding: 24px;
    }

    .grafico-info {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .grafico-info-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        background: #f8fafc;
        border-radius: 8px;
        font-size: 13px;
        color: #475569;
    }

    .grafico-info-item ion-icon {
        font-size: 18px;
        color: #3b82f6;
    }

    .grafico-info-item strong {
        color: #1e293b;
    }

    .grafico-legenda {
        display: flex;
        gap: 20px;
        margin-bottom: 16px;
        font-size: 12px;
        color: #64748b;
    }

    .grafico-legenda-item {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .grafico-legenda-item .dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
    }

    .grafico-legenda-item .dot.complete {
        background: #3b82f6;
    }

    .grafico-legenda-item .dot.incomplete {
        background: #ef4444;
    }

    .grafico-container {
        width: 100%;
        height: 300px;
        background: #f8fafc;
        border-radius: 12px;
        padding: 20px;
        box-sizing: border-box;
    }

    .grafico-svg {
        width: 100%;
        height: 100%;
    }

    .grafico-line {
        fill: none;
        stroke: #3b82f6;
        stroke-width: 2.5;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .grafico-line-dynamic {
        fill: none;
        stroke-width: 2.5;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .grafico-area {
        fill: url(#gradienteArea);
        opacity: 0.3;
    }

    .grafico-area-dynamic {
        opacity: 0.3;
    }

    .grafico-point {
        stroke: white;
        stroke-width: 2;
        cursor: pointer;
        transition: r 0.2s ease;
    }

    .grafico-point.complete {
        fill: #3b82f6;
    }

    .grafico-point.incomplete {
        fill: #ef4444;
    }

    .grafico-grid {
        stroke: #e2e8f0;
        stroke-width: 1;
    }

    .grafico-axis-label {
        font-size: 11px;
        fill: #64748b;
        text-anchor: middle;
    }

    .grafico-value-label {
        font-size: 10px;
        fill: #94a3b8;
        text-anchor: end;
    }

    .row-data {
        display: none;
    }

    .row-data.visible {
        display: table-row;
    }

    .row-data td:first-child {
        padding-left: 40px !important;
    }

    /* Loading e Error inline */
    .loading-cell {
        padding: 20px !important;
        text-align: center;
    }

    .loading-inline {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        color: #64748b;
        font-size: 14px;
    }

    .loading-inline ion-icon.spin {
        font-size: 20px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    .error-inline {
        color: #dc2626;
        font-size: 14px;
        text-align: center;
        padding: 10px;
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
        background: rgba(255, 255, 255, 0.8);
        z-index: 100;
        align-items: center;
        justify-content: center;
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
       Pagination
       ============================================ */
    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }

    .pagination-buttons {
        display: flex;
        gap: 6px;
    }

    .btn-page {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 10px;
        background: white;
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
       Toast
       ============================================ */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 99999;
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
    @media (max-width: 1400px) {
        .filters-grid-6 {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 1024px) {
        .filters-grid-6 {
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

        .filters-card {
            padding: 16px;
        }

        .filters-grid-6 {
            grid-template-columns: 1fr;
        }

        .filters-actions {
            flex-direction: column;
        }

        .btn-buscar,
        .btn-limpar {
            width: 100%;
            justify-content: center;
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
</style>

<div class="page-container">
    <!-- Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="analytics-outline"></ion-icon>
                </div>
                <div>
                    <h1>Registro de Vazão e Pressão</h1>
                    <p class="page-header-subtitle">Consulta e gerenciamento de registros de medição</p>
                </div>
            </div>
            <?php if ($podeEditar): ?>
                <button type="button" class="btn-novo" onclick="abrirModalNovo()">
                    <ion-icon name="add-outline"></ion-icon>
                    Novo Registro
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros -->
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

        <div class="filters-grid-6">
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="business-outline"></ion-icon>
                    Unidade
                </label>
                <select id="filtroUnidade" class="form-control choices-select">
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
                    <ion-icon name="location-outline"></ion-icon>
                    Localidade
                </label>
                <select id="filtroLocalidade" class="form-control choices-select">
                    <option value="">Todas as Localidades</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="speedometer-outline"></ion-icon>
                    Ponto de Medição
                </label>
                <div class="autocomplete-container">
                    <input type="text" id="filtroPontoMedicaoInput" class="form-control"
                        placeholder="Clique para selecionar ou digite para filtrar..." autocomplete="off">
                    <input type="hidden" id="filtroPontoMedicao" value="">
                    <div id="filtroPontoMedicaoDropdown" class="autocomplete-dropdown"></div>
                    <button type="button" id="btnLimparPonto" class="btn-limpar-autocomplete" style="display: none;"
                        title="Limpar">
                        <ion-icon name="close-circle"></ion-icon>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Data Início
                </label>
                <input type="date" id="filtroDataInicio" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Data Fim
                </label>
                <input type="date" id="filtroDataFim" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="hardware-chip-outline"></ion-icon>
                    Tipo Medidor
                </label>
                <select id="filtroTipoMedidor" class="form-control">
                    <option value="">Todos os Tipos</option>
                    <option value="1">M - Macromedidor</option>
                    <option value="2">E - Estação Pitométrica</option>
                    <option value="4">P - Medidor Pressão</option>
                    <option value="8">H - Hidrômetro</option>
                    <option value="6">R - Nível Reservatório</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="reader-outline"></ion-icon>
                    Tipo Leitura
                </label>
                <select id="filtroTipoLeitura" class="form-control">
                    <option value="">Todos os Tipos</option>
                    <option value="2">Manual</option>
                    <option value="4">Planilha</option>
                    <option value="8">Integração CCO</option>
                    <option value="6">Integração CesanLims</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="trash-outline"></ion-icon>
                    Descarte
                </label>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="filtroDescarte" value="">
                        <span class="radio-label">Todos</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="filtroDescarte" value="1" checked>
                        <span class="radio-label">Não</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="filtroDescarte" value="2">
                        <span class="radio-label">Sim</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="grid-outline"></ion-icon>
                    Agrupar por
                </label>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="modoExibicao" value="ponto" checked>
                        <span class="radio-label">Ponto</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="modoExibicao" value="data">
                        <span class="radio-label">Data</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="filters-actions">
            <button type="button" class="btn-buscar" onclick="buscarRegistros()">
                <ion-icon name="search-outline"></ion-icon>
                Buscar
            </button>
            <button type="button" class="btn-importar" onclick="abrirModalImportacao()">
                <ion-icon name="cloud-upload-outline"></ion-icon>
                Importar Planilha
            </button>
        </div>
    </div>

    <!-- Results Info -->
    <div class="results-info">
        <div class="results-count">
            <ion-icon name="list-outline"></ion-icon>
            <span id="countRegistros">Utilize os filtros acima para buscar registros</span>
        </div>
        <div class="results-actions" id="botoesExpandir" style="display: none;">
            <button type="button" class="btn-expand" onclick="expandirTodos()">
                <ion-icon name="chevron-down-outline"></ion-icon>
                Expandir Todos
            </button>
            <button type="button" class="btn-expand" onclick="colapsarTodos()">
                <ion-icon name="chevron-up-outline"></ion-icon>
                Colapsar Todos
            </button>
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
                        <th style="width: 120px;" class="sortable" data-column="DS_UNIDADE">Unidade</th>
                        <th style="width: 150px;" class="sortable" data-column="DS_LOCALIDADE">Localidade</th>
                        <th style="width: 200px;" class="sortable" data-column="DS_PONTO_MEDICAO">Ponto Medição</th>
                        <th style="width: 100px;" class="sortable" data-column="ID_TIPO_REGISTRO">Tipo Registro</th>
                        <th style="width: 100px;" class="sortable" data-column="ID_TIPO_VAZAO">Tipo Vazão</th>
                        <th style="width: 140px;" class="sortable" data-column="DT_LEITURA">Data Leitura</th>
                        <th style="width: 110px;" class="sortable" data-column="VL_VAZAO_EFETIVA">Vazão (l/s)</th>
                        <th style="width: 100px;" class="sortable" data-column="VL_PRESSAO">Pressão (mca)</th>
                        <th style="width: 100px;" class="sortable" data-column="VL_RESERVATORIO">Nível Reserv.</th>
                        <th style="width: 90px;" class="sortable" data-column="ID_SITUACAO">Descarte</th>
                        <th style="width: 80px;">Ações</th>
                    </tr>
                </thead>
                <tbody id="tabelaRegistros">
                    <tr>
                        <td colspan="11">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <ion-icon name="search-outline"></ion-icon>
                                </div>
                                <h3>Nenhuma pesquisa realizada</h3>
                                <p>Utilize os filtros acima e clique em "Buscar" para consultar os registros</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Barra de Exclusão em Massa -->
        <?php if ($podeEditar): ?>
            <div class="barra-exclusao" id="barraExclusao">
                <div class="barra-exclusao-info">
                    <ion-icon name="trash-outline"></ion-icon>
                    <span><span class="barra-exclusao-count" id="countSelecionados">0</span> registro(s)
                        selecionado(s)</span>
                </div>
                <div class="barra-exclusao-acoes">
                    <button type="button" class="btn-cancelar-selecao" onclick="cancelarSelecao()">
                        <ion-icon name="close-outline"></ion-icon>
                        Cancelar
                    </button>
                    <button type="button" class="btn-excluir-selecionados" onclick="excluirSelecionados()">
                        <ion-icon name="trash-outline"></ion-icon>
                        Descartar Selecionados
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <div class="pagination-container" id="paginacaoRegistros" style="display: none;"></div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Modal Gráfico por Hora -->
<div class="modal-grafico" id="modalGrafico" onclick="fecharModalGrafico(event)">
    <div class="modal-grafico-content" onclick="event.stopPropagation()">
        <div class="modal-grafico-header">
            <h3>
                <ion-icon name="analytics-outline"></ion-icon>
                <span id="modalGraficoTitulo">Gráfico de Vazão por Hora</span>
            </h3>
            <button type="button" class="modal-grafico-close" onclick="fecharModalGrafico()">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-grafico-body">
            <div class="grafico-info" id="graficoInfo"></div>
            <div class="grafico-legenda">
                <div class="grafico-legenda-item">
                    <span class="dot complete"></span>
                    <span>Hora completa (60+ registros)</span>
                </div>
                <div class="grafico-legenda-item">
                    <span class="dot incomplete"></span>
                    <span>Hora incompleta (&lt;60 registros)</span>
                </div>
            </div>
            <div class="grafico-container" id="graficoContainer"></div>
        </div>
    </div>
</div>

<!-- Choices.js -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<script>
    // ============================================
    // Configurações Globais
    // ============================================
    const podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;

    // Tipos de Registro
    const tiposRegistro = <?= json_encode($tiposRegistro) ?>;

    // Tipos de Vazão
    const tiposVazao = <?= json_encode($tiposVazao) ?>;

    // Descarte (ID_SITUACAO: 1 = Não, 2 = Sim)
    const descartes = <?= json_encode($descartes) ?>;

    // Instâncias do Choices.js
    let choicesUnidade, choicesLocalidade;

    // ============================================
    // Inicialização
    // ============================================
    document.addEventListener('DOMContentLoaded', function () {
        initChoices();

        // Definir data padrão (últimos 30 dias)
        const hoje = new Date();
        const inicio = new Date();
        inicio.setDate(hoje.getDate() - 30);

        document.getElementById('filtroDataFim').value = formatDateInput(hoje);
        document.getElementById('filtroDataInicio').value = formatDateInput(inicio);
    });

    function formatDateInput(date) {
        return date.toISOString().split('T')[0];
    }

    // ============================================
    // Choices.js Inicialização
    // ============================================
    function initChoices() {
        // Unidade
        choicesUnidade = new Choices('#filtroUnidade', {
            searchEnabled: true,
            searchPlaceholderValue: 'Digite para buscar...',
            noResultsText: 'Nenhum resultado encontrado',
            noChoicesText: 'Nenhuma opção disponível',
            itemSelectText: '',
            placeholder: true,
            placeholderValue: 'Todas as Unidades',
            shouldSort: false,
            searchResultLimit: 50
        });

        // Localidade
        choicesLocalidade = new Choices('#filtroLocalidade', {
            searchEnabled: true,
            searchPlaceholderValue: 'Digite para buscar...',
            noResultsText: 'Nenhum resultado encontrado',
            noChoicesText: 'Selecione uma unidade primeiro',
            itemSelectText: '',
            placeholder: true,
            placeholderValue: 'Todas as Localidades',
            shouldSort: false,
            searchResultLimit: 50
        });

        // Event listeners para carregar dados em cascata
        document.getElementById('filtroUnidade').addEventListener('change', function () {
            carregarLocalidades(this.value);
        });

        // Inicializa autocomplete de Ponto de Medição
        initAutocompletePontoMedicao();
    }

    // ============================================
    // Autocomplete para Ponto de Medição
    // ============================================
    let autocompletePontoTimeout = null;
    let autocompletePontoIndex = -1;

    // Mapeamento de letras por tipo de medidor
    const letrasTipoMedidor = {
        1: 'M', // Macromedidor
        2: 'E', // Estação Pitométrica
        4: 'P', // Medidor Pressão
        6: 'R', // Nível Reservatório
        8: 'H'  // Hidrômetro
    };

    function initAutocompletePontoMedicao() {
        const input = document.getElementById('filtroPontoMedicaoInput');
        const hidden = document.getElementById('filtroPontoMedicao');
        const dropdown = document.getElementById('filtroPontoMedicaoDropdown');
        const btnLimpar = document.getElementById('btnLimparPonto');

        // Evento de foco - abre dropdown
        input.addEventListener('focus', function () {
            if (!hidden.value) {
                buscarPontosMedicaoAutocomplete('');
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
                buscarPontosMedicaoAutocomplete(termo);
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

        // Fecha ao clicar fora
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.autocomplete-container')) {
                dropdown.classList.remove('active');
            }
        });

        // Botão limpar
        btnLimpar.addEventListener('click', function () {
            input.value = '';
            hidden.value = '';
            btnLimpar.style.display = 'none';
            dropdown.classList.remove('active');
            input.focus();
        });
    }

    function atualizarHighlightPonto(items) {
        items.forEach((item, index) => {
            if (index === autocompletePontoIndex) {
                item.classList.add('highlighted');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('highlighted');
            }
        });
    }

    function buscarPontosMedicaoAutocomplete(termo) {
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
                        item.addEventListener('click', function () {
                            selecionarPontoMedicaoAutocomplete(this.dataset.value, this.dataset.label);
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

    function selecionarPontoMedicaoAutocomplete(value, label) {
        const input = document.getElementById('filtroPontoMedicaoInput');
        const hidden = document.getElementById('filtroPontoMedicao');
        const dropdown = document.getElementById('filtroPontoMedicaoDropdown');
        const btnLimpar = document.getElementById('btnLimparPonto');

        input.value = label;
        hidden.value = value;
        dropdown.classList.remove('active');
        btnLimpar.style.display = 'flex';
    }

    // ============================================
    // Carregar Localidades
    // ============================================
    function carregarLocalidades(cdUnidade) {
        choicesLocalidade.clearStore();

        if (!cdUnidade) {
            choicesLocalidade.setChoices([{ value: '', label: 'Todas as Localidades', selected: true }], 'value', 'label', true);
            return;
        }

        $.get('bd/registroVazaoPressao/getLocalidades.php', { cd_unidade: cdUnidade }, function (response) {
            if (response.success) {
                let options = [{ value: '', label: 'Todas as Localidades', selected: true }];
                response.data.forEach(item => {
                    options.push({
                        value: item.CD_CHAVE,
                        label: item.CD_LOCALIDADE + ' - ' + item.DS_NOME
                    });
                });
                choicesLocalidade.setChoices(options, 'value', 'label', true);
            }
        }, 'json');
    }

    // ============================================
    // Buscar Registros (apenas agregadores)
    // ============================================
    function buscarRegistros() {
        // Limpar seleção anterior
        if (typeof registrosSelecionados !== 'undefined') {
            registrosSelecionados.clear();
            atualizarBarraExclusao();
        }

        const filtros = {
            cd_unidade: document.getElementById('filtroUnidade').value,
            cd_localidade: document.getElementById('filtroLocalidade').value,
            cd_ponto_medicao: document.getElementById('filtroPontoMedicao').value,
            data_inicio: document.getElementById('filtroDataInicio').value,
            data_fim: document.getElementById('filtroDataFim').value,
            tipo_medidor: document.getElementById('filtroTipoMedidor').value,
            tipo_leitura: document.getElementById('filtroTipoLeitura').value,
            descarte: document.querySelector('input[name="filtroDescarte"]:checked').value
        };

        // Armazenar filtros para uso posterior (carregamento sob demanda)
        filtrosAtuais = filtros;

        // Verificar modo de exibição
        const modoExibicao = document.querySelector('input[name="modoExibicao"]:checked').value;

        const loading = document.getElementById('loadingRegistros');
        loading.classList.add('active');

        if (modoExibicao === 'ponto') {
            // Modo: Ponto de Medição => Dia => Registros
            $.get('bd/registroVazaoPressao/getRegistrosPorPonto.php', filtros, function (response) {
                loading.classList.remove('active');

                if (response.success) {
                    document.getElementById('countRegistros').innerHTML =
                        `<strong>${response.total}</strong> registro(s) em <strong>${response.totalPontos}</strong> ponto(s)`;

                    // Armazenar estatísticas por ponto
                    estatisticasPonto = response.estatisticasPonto || {};
                    estatisticasDia = {}; // Limpar modo anterior

                    // Limpar caches
                    registrosCarregados = {};
                    pontosMedicaoCarregados = {};
                    diasPontoCarregados = {};

                    // Renderizar agregadores por ponto
                    renderizarAgregadoresPorPonto();

                    // Mostrar/ocultar botões expandir/colapsar
                    const botoesExpandir = document.getElementById('botoesExpandir');
                    if (Object.keys(estatisticasPonto).length > 0) {
                        botoesExpandir.style.display = 'flex';
                    } else {
                        botoesExpandir.style.display = 'none';
                    }
                } else {
                    showToast(response.message || 'Erro ao buscar registros', 'erro');
                    document.getElementById('botoesExpandir').style.display = 'none';
                }
            }, 'json').fail(function () {
                loading.classList.remove('active');
                showToast('Erro ao comunicar com o servidor', 'erro');
                document.getElementById('botoesExpandir').style.display = 'none';
            });
        } else {
            // Modo: Data => Ponto de Medição => Registros (modo original)
            $.get('bd/registroVazaoPressao/getRegistros.php', filtros, function (response) {
                loading.classList.remove('active');

                if (response.success) {
                    document.getElementById('countRegistros').innerHTML =
                        `<strong>${response.total}</strong> registro(s) em <strong>${response.totalDias}</strong> dia(s)`;

                    // Armazenar estatísticas por dia
                    estatisticasDia = response.estatisticasDia || {};
                    estatisticasPonto = {}; // Limpar modo anterior

                    // Limpar caches
                    registrosCarregados = {};
                    pontosMedicaoCarregados = {};
                    diasPontoCarregados = {};

                    // Renderizar apenas agregadores
                    renderizarAgregadores();

                    // Mostrar/ocultar botões expandir/colapsar
                    const botoesExpandir = document.getElementById('botoesExpandir');
                    if (Object.keys(estatisticasDia).length > 0) {
                        botoesExpandir.style.display = 'flex';
                    } else {
                        botoesExpandir.style.display = 'none';
                    }
                } else {
                    showToast(response.message || 'Erro ao buscar registros', 'erro');
                    document.getElementById('botoesExpandir').style.display = 'none';
                }
            }, 'json').fail(function () {
                loading.classList.remove('active');
                showToast('Erro ao comunicar com o servidor', 'erro');
                document.getElementById('botoesExpandir').style.display = 'none';
            });
        }
    }

    // ============================================
    // Limpar Filtros
    // ============================================
    function limparFiltros() {
        // Limpar Choices
        choicesUnidade.setChoiceByValue('');
        choicesLocalidade.clearStore();
        choicesLocalidade.setChoices([{ value: '', label: 'Todas as Localidades', selected: true }], 'value', 'label', true);

        // Limpar autocomplete de Ponto de Medição
        document.getElementById('filtroPontoMedicaoInput').value = '';
        document.getElementById('filtroPontoMedicao').value = '';
        document.getElementById('filtroPontoMedicaoDropdown').classList.remove('active');
        document.getElementById('btnLimparPonto').style.display = 'none';

        // Resetar datas (últimos 30 dias)
        const hoje = new Date();
        const inicio = new Date();
        inicio.setDate(hoje.getDate() - 30);
        document.getElementById('filtroDataFim').value = formatDateInput(hoje);
        document.getElementById('filtroDataInicio').value = formatDateInput(inicio);

        // Resetar demais filtros
        document.getElementById('filtroTipoMedidor').value = '';
        document.getElementById('filtroTipoLeitura').value = '';
        document.querySelector('input[name="filtroDescarte"][value="1"]').checked = true;

        // Limpar dados e ordenação
        dadosTabela = [];
        estatisticasDia = {};
        registrosCarregados = {};
        pontosMedicaoCarregados = {};
        filtrosAtuais = {};
        document.querySelectorAll('.data-table th.sortable').forEach(th => {
            th.classList.remove('asc', 'desc');
        });

        // Ocultar botões expandir/colapsar
        document.getElementById('botoesExpandir').style.display = 'none';

        // Limpar tabela
        document.getElementById('tabelaRegistros').innerHTML = `
            <tr>
                <td colspan="11">
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <ion-icon name="search-outline"></ion-icon>
                        </div>
                        <h3>Nenhuma pesquisa realizada</h3>
                        <p>Utilize os filtros acima e clique em "Buscar" para consultar os registros</p>
                    </div>
                </td>
            </tr>
        `;
        document.getElementById('countRegistros').innerHTML = 'Utilize os filtros acima para buscar registros';
    }

    // ============================================
    // Visualizar Registro
    // ============================================
    function visualizarRegistro(id) {
        // TODO: Implementar modal de visualização
        showToast('Função de visualização será implementada', 'info');
    }

    // ============================================
    // Excluir Registro
    // ============================================
    function excluirRegistro(id) {
        if (!confirm('Deseja realmente descartar este registro?')) return;

        console.log('Descartando registro ID:', id);

        $.post('bd/registroVazaoPressao/excluirRegistro.php', { id: id }, function (response) {
            console.log('Resposta descarte:', response);
            if (response.success) {
                showToast(response.message, 'sucesso');
                buscarRegistros();
            } else {
                showToast(response.message || 'Erro ao descartar', 'erro');
            }
        }, 'json').fail(function (xhr, status, error) {
            console.error('Erro descarte:', status, error);
            console.error('Response:', xhr.responseText);
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // Exclusão em Massa - Gerenciamento de Seleção
    // ============================================

    // Armazenar registros selecionados
    let registrosSelecionados = new Set();

    // Atualizar barra de exclusão
    function atualizarBarraExclusao() {
        const barra = document.getElementById('barraExclusao');
        const count = document.getElementById('countSelecionados');

        if (!barra) return;

        if (registrosSelecionados.size > 0) {
            barra.classList.add('visivel');
            count.textContent = registrosSelecionados.size;
        } else {
            barra.classList.remove('visivel');
        }

        // Atualizar classe de linha selecionada
        document.querySelectorAll('.row-data').forEach(row => {
            const chave = row.dataset.chave;
            if (chave && registrosSelecionados.has(parseInt(chave))) {
                row.classList.add('selecionado');
            } else {
                row.classList.remove('selecionado');
            }
        });
    }

    // Selecionar/deselecionar registro individual
    function selecionarRegistro(cdChave, checked) {
        if (checked) {
            registrosSelecionados.add(cdChave);
        } else {
            registrosSelecionados.delete(cdChave);
        }
        atualizarBarraExclusao();
    }

    // Selecionar todos os registros de um ponto de medição
    function selecionarPonto(dataChave, cdPonto, checked) {
        // Buscar todos os registros do ponto
        const params = {
            data: dataChave,
            cd_ponto_medicao: cdPonto,
            cd_unidade: filtrosAtuais.cd_unidade || '',
            cd_localidade: filtrosAtuais.cd_localidade || '',
            tipo_medidor: filtrosAtuais.tipo_medidor || '',
            tipo_leitura: filtrosAtuais.tipo_leitura || '',
            descarte: filtrosAtuais.descarte || '',
            apenas_chaves: 1  // Flag para retornar apenas as chaves
        };

        $.get('bd/registroVazaoPressao/getRegistrosPonto.php', params, function (response) {
            if (response.success && response.chaves) {
                response.chaves.forEach(chave => {
                    if (checked) {
                        registrosSelecionados.add(chave);
                    } else {
                        registrosSelecionados.delete(chave);
                    }
                });

                // Atualizar checkboxes dos registros visíveis
                document.querySelectorAll(`.checkbox-registro[data-dia="${dataChave}"][data-ponto="${cdPonto}"]`).forEach(cb => {
                    cb.checked = checked;
                });

                atualizarBarraExclusao();
            }
        }, 'json');
    }

    // Selecionar todos os registros de um dia
    function selecionarDia(dataChave, checked) {
        // Buscar todos os registros do dia
        const params = {
            data: dataChave,
            cd_unidade: filtrosAtuais.cd_unidade || '',
            cd_localidade: filtrosAtuais.cd_localidade || '',
            cd_ponto_medicao: filtrosAtuais.cd_ponto_medicao || '',
            tipo_medidor: filtrosAtuais.tipo_medidor || '',
            tipo_leitura: filtrosAtuais.tipo_leitura || '',
            descarte: filtrosAtuais.descarte || '',
            apenas_chaves: 1  // Flag para retornar apenas as chaves
        };

        $.get('bd/registroVazaoPressao/getRegistrosDia.php', params, function (response) {
            if (response.success && response.chaves) {
                response.chaves.forEach(chave => {
                    if (checked) {
                        registrosSelecionados.add(chave);
                    } else {
                        registrosSelecionados.delete(chave);
                    }
                });

                // Atualizar checkboxes dos registros e pontos visíveis
                document.querySelectorAll(`.checkbox-registro[data-dia="${dataChave}"]`).forEach(cb => {
                    cb.checked = checked;
                });
                document.querySelectorAll(`.checkbox-ponto[data-dia="${dataChave}"]`).forEach(cb => {
                    cb.checked = checked;
                });

                atualizarBarraExclusao();
            }
        }, 'json');
    }

    // Cancelar seleção
    function cancelarSelecao() {
        registrosSelecionados.clear();

        // Desmarcar todos os checkboxes
        document.querySelectorAll('.checkbox-selecao').forEach(cb => {
            cb.checked = false;
        });

        atualizarBarraExclusao();
    }

    // Descartar registros selecionados (exclusão lógica)
    function excluirSelecionados() {
        const quantidade = registrosSelecionados.size;

        if (quantidade === 0) {
            showToast('Nenhum registro selecionado', 'erro');
            return;
        }

        if (!confirm(`Deseja realmente descartar ${quantidade} registro(s)?\n\nOs registros serão marcados como descartados.`)) {
            return;
        }

        // Converter Set para Array
        const chaves = Array.from(registrosSelecionados);

        console.log('Descartando chaves:', chaves);

        // Mostrar loading
        const btnExcluir = document.querySelector('.btn-excluir-selecionados');
        const textoOriginal = btnExcluir.innerHTML;
        btnExcluir.innerHTML = '<ion-icon name="sync-outline" class="spin"></ion-icon> Descartando...';
        btnExcluir.disabled = true;

        $.ajax({
            url: 'bd/registroVazaoPressao/excluirRegistrosEmMassa.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ chaves: chaves }),
            dataType: 'json',
            success: function (response) {
                console.log('Resposta:', response);
                if (response.success) {
                    showToast(`${response.excluidos} registro(s) descartado(s) com sucesso!`, 'sucesso');
                    cancelarSelecao();
                    buscarRegistros();
                } else {
                    showToast(response.message || 'Erro ao descartar registros', 'erro');
                }
            },
            error: function (xhr, status, error) {
                console.error('Erro AJAX:', status, error);
                console.error('Response:', xhr.responseText);

                // Tentar parsear resposta mesmo em caso de erro
                try {
                    const resp = JSON.parse(xhr.responseText);
                    if (resp.message) {
                        showToast(resp.message, 'erro');
                        return;
                    }
                } catch (e) { }

                showToast('Erro ao comunicar com o servidor. Verifique o console.', 'erro');
            },
            complete: function () {
                btnExcluir.innerHTML = textoOriginal;
                btnExcluir.disabled = false;
            }
        });
    }

    // ============================================
    // Abrir Modal Novo
    // ============================================
    function abrirModalNovo() {
        // TODO: Implementar modal de novo registro
        showToast('Função de novo registro será implementada', 'info');
    }

    // ============================================
    // Toast
    // ============================================
    function showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <ion-icon name="${type === 'sucesso' ? 'checkmark' : type === 'erro' ? 'close' : 'information'}-outline"></ion-icon>
            </div>
            <span class="toast-message">${message}</span>
        `;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // ============================================
    // Ordenação da Tabela
    // ============================================
    let dadosTabela = [];
    let estatisticasDia = {};
    let estatisticasPonto = {}; // Estatísticas por ponto de medição
    let registrosCarregados = {}; // Cache de registros carregados por dia+ponto
    let pontosMedicaoCarregados = {}; // Cache de pontos de medição carregados por dia
    let diasPontoCarregados = {}; // Cache de dias carregados por ponto
    let filtrosAtuais = {}; // Filtros atuais para carregamento sob demanda
    let ordenacaoAtual = { coluna: 'DT_LEITURA', direcao: 'desc' };

    // Ordenação desabilitada (carregamento sob demanda não suporta ordenação client-side)
    // TODO: Implementar ordenação no backend se necessário
    function ordenarTabela(coluna) {
        showToast('Ordenação não disponível nesta visualização', 'info');
    }

    function renderizarAgregadores() {
        const tbody = document.getElementById('tabelaRegistros');

        const dias = Object.keys(estatisticasDia);

        if (dias.length === 0) {
            tbody.innerHTML = `<tr><td colspan="11">
                <div class="empty-state">
                    <div class="empty-state-icon"><ion-icon name="folder-open-outline"></ion-icon></div>
                    <h3>Nenhum registro encontrado</h3>
                    <p>Tente ajustar os filtros de busca</p>
                </div>
            </td></tr>`;
            return;
        }

        // Ordenar dias (mais recente primeiro)
        const diasOrdenados = dias.sort((a, b) => b.localeCompare(a));

        let html = '';
        diasOrdenados.forEach((dataChave) => {
            const estatDia = estatisticasDia[dataChave];

            // Formatar data
            const dataObj = new Date(dataChave + 'T12:00:00');
            const dataFormatada = dataObj.toLocaleDateString('pt-BR', {
                weekday: 'long',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
            const dataCapitalizada = dataFormatada.charAt(0).toUpperCase() + dataFormatada.slice(1);

            const totalExibir = estatDia.total || 0;
            const totalNaoDescarte = estatDia.totalNaoDescarte || 0;
            const totalDescarte = estatDia.totalDescarte || 0;
            const qtdPontos = estatDia.qtdPontos || 1;
            const mediaVazao = estatDia.mediaVazao;
            const mediaPressao = estatDia.mediaPressao;
            const mediaNivel = estatDia.mediaNivel;

            // Verificar se está incompleto (menos de 1440 registros com ID_SITUACAO = 1)
            const incompleto = totalNaoDescarte < 1440;
            const classeIncompleto = incompleto ? 'grupo-incompleto' : '';

            // Formatar médias
            const formatarMedia = (valor, unidade) => {
                if (valor === null || valor === undefined) return '-';
                return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + unidade;
            };

            // Linha de cabeçalho do grupo (dia)
            html += `
                <tr class="row-group-header ${classeIncompleto}" data-group="${dataChave}" onclick="toggleGrupo('${dataChave}')">
                    <td colspan="11">
                        <div class="group-content">
                            <div class="group-header-row">
                                <div class="group-toggle">
                                    ${podeEditar ? `<input type="checkbox" class="checkbox-selecao checkbox-dia" data-dia="${dataChave}" onclick="event.stopPropagation(); selecionarDia('${dataChave}', this.checked)" title="Selecionar todos os registros deste dia">` : ''}
                                    <ion-icon name="chevron-forward-outline"></ion-icon>
                                    <span class="group-date">${dataCapitalizada}</span>
                                    <span class="group-count">${totalExibir}</span>
                                    <span class="group-summary">registro${totalExibir > 1 ? 's' : ''} ${qtdPontos > 1 ? `em ${qtdPontos} pontos` : ''}</span>
                                    ${incompleto ? '<span class="group-alert" title="Menos de 1440 registros válidos"><ion-icon name="warning-outline"></ion-icon></span>' : ''}
                                    ${qtdPontos === 1 ? `<button type="button" class="btn-chart" onclick="event.stopPropagation(); abrirGrafico('${dataChave}')" title="Ver gráfico por hora">
                                        <ion-icon name="analytics-outline"></ion-icon>
                                    </button>` : ''}
                                </div>
                                <div class="group-stats">
                                    <span class="stat-item" title="Média Vazão Efetiva">
                                        <ion-icon name="water-outline"></ion-icon>
                                        ${formatarMedia(mediaVazao, 'l/s')}
                                    </span>
                                    <span class="stat-item" title="Média Pressão">
                                        <ion-icon name="speedometer-outline"></ion-icon>
                                        ${formatarMedia(mediaPressao, 'mca')}
                                    </span>
                                    <span class="stat-item" title="Média Nível Reservatório">
                                        <ion-icon name="layers-outline"></ion-icon>
                                        ${formatarMedia(mediaNivel, 'm')}
                                    </span>
                                    <span class="stat-item stat-valid" title="Registros válidos (não descartados)">
                                        <ion-icon name="checkmark-circle-outline"></ion-icon>
                                        ${totalNaoDescarte}
                                    </span>
                                    ${totalDescarte > 0 ? `
                                    <span class="stat-item stat-descarte" title="Registros descartados" style="background: rgba(239, 68, 68, 0.15); color: #b91c1c;">
                                        <ion-icon name="close-circle-outline" style="color: #ef4444;"></ion-icon>
                                        ${totalDescarte}
                                    </span>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            `;

            // Placeholder para registros (serão carregados sob demanda)
            html += `<tr class="row-data-placeholder" data-group="${dataChave}" style="display: none;">
                <td colspan="11" class="loading-cell">
                    <div class="loading-inline"><ion-icon name="sync-outline" class="spin"></ion-icon> Carregando registros...</div>
                </td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }

    // ============================================
    // Renderizar Agregadores por Ponto de Medição
    // ============================================
    function renderizarAgregadoresPorPonto() {
        const tbody = document.getElementById('tabelaRegistros');

        const pontos = Object.keys(estatisticasPonto);

        if (pontos.length === 0) {
            tbody.innerHTML = `<tr><td colspan="11">
                <div class="empty-state">
                    <div class="empty-state-icon"><ion-icon name="folder-open-outline"></ion-icon></div>
                    <h3>Nenhum registro encontrado</h3>
                    <p>Tente ajustar os filtros de busca</p>
                </div>
            </td></tr>`;
            return;
        }

        // Ordenar pontos por nome
        const pontosOrdenados = pontos.sort((a, b) => {
            const nomeA = estatisticasPonto[a].dsPontoMedicao || '';
            const nomeB = estatisticasPonto[b].dsPontoMedicao || '';
            return nomeA.localeCompare(nomeB);
        });

        let html = '';
        pontosOrdenados.forEach((cdPonto) => {
            const estatPonto = estatisticasPonto[cdPonto];

            const totalExibir = estatPonto.total || 0;
            const totalNaoDescarte = estatPonto.totalNaoDescarte || 0;
            const totalDescarte = estatPonto.totalDescarte || 0;
            const qtdDias = estatPonto.qtdDias || 0;
            const mediaVazao = estatPonto.mediaVazao;
            const mediaPressao = estatPonto.mediaPressao;
            const nivelValor = estatPonto.nivelValor;
            const idTipoMedidor = estatPonto.idTipoMedidor;
            const codigoFormatado = estatPonto.codigoFormatado || cdPonto;

            // Determinar cores e ícone baseado no tipo de medidor
            let cores = { bg: '#f8fafc', bgHover: '#f1f5f9', border: '#cbd5e1' };
            let iconeMedidor = 'water-outline';
            let classeMedidor = 'badge-tipo-medidor';

            if (idTipoMedidor == 4) {
                cores = { bg: 'rgba(249, 115, 22, 0.05)', bgHover: 'rgba(249, 115, 22, 0.1)', border: '#f97316' };
                iconeMedidor = 'speedometer-outline';
                classeMedidor = 'badge-tipo-pressao';
            } else if (idTipoMedidor == 6) {
                cores = { bg: 'rgba(6, 182, 212, 0.05)', bgHover: 'rgba(6, 182, 212, 0.1)', border: '#06b6d4' };
                iconeMedidor = 'layers-outline';
                classeMedidor = 'badge-tipo-reservatorio';
            }

            // Verificar se está incompleto
            const incompleto = totalNaoDescarte < 1440;
            const classeIncompleto = incompleto ? 'grupo-incompleto' : '';

            // Formatar médias
            const formatarMedia = (valor, unidade) => {
                if (valor === null || valor === undefined) return '-';
                return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + unidade;
            };

            // Linha de cabeçalho do grupo (ponto de medição)
            html += `
                <tr class="row-group-header ${classeIncompleto}" data-ponto="${cdPonto}" onclick="toggleGrupoPontoMedicao('${cdPonto}')" style="background: linear-gradient(135deg, ${cores.bg} 0%, ${cores.bgHover} 100%); border-left: 3px solid ${cores.border};">
                    <td colspan="11">
                        <div class="group-content">
                            <div class="group-header-row">
                                <div class="group-toggle">
                                    ${podeEditar ? `<input type="checkbox" class="checkbox-selecao checkbox-ponto-principal" data-ponto="${cdPonto}" onclick="event.stopPropagation(); selecionarPontoMedicao('${cdPonto}', this.checked)" title="Selecionar todos os registros deste ponto">` : ''}
                                    <ion-icon name="chevron-forward-outline"></ion-icon>
                                    <span class="group-date">${codigoFormatado}</span>
                                    <span class="group-count">${totalExibir}</span>
                                    <span class="group-summary">registro${totalExibir > 1 ? 's' : ''} em ${qtdDias} dia${qtdDias > 1 ? 's' : ''}</span>
                                    <div class="subgroup-badges" style="margin-left: 8px;">
                                        <span class="badge-tipo ${classeMedidor}" title="Tipo de Medidor">
                                            <ion-icon name="${iconeMedidor}" style="font-size: 10px; margin-right: 3px;"></ion-icon>
                                            ${estatPonto.dsTipoMedidor || '-'}
                                        </span>
                                        <span class="badge-tipo badge-tipo-leitura" title="Tipo de Leitura">${estatPonto.dsTipoLeitura || '-'}</span>
                                    </div>
                                </div>
                                <div class="group-stats">
                                    <span class="stat-item" title="Média Vazão Efetiva">
                                        <ion-icon name="water-outline"></ion-icon>
                                        ${formatarMedia(mediaVazao, 'l/s')}
                                    </span>
                                    <span class="stat-item" title="Média Pressão">
                                        <ion-icon name="speedometer-outline"></ion-icon>
                                        ${formatarMedia(mediaPressao, 'mca')}
                                    </span>
                                    <span class="stat-item" title="Nível Reservatório">
                                        <ion-icon name="layers-outline"></ion-icon>
                                        ${formatarMedia(nivelValor, 'm')}
                                    </span>
                                    <span class="stat-item stat-valid" title="Registros válidos (não descartados)">
                                        <ion-icon name="checkmark-circle-outline"></ion-icon>
                                        ${totalNaoDescarte}
                                    </span>
                                    ${totalDescarte > 0 ? `
                                    <span class="stat-item stat-descarte" title="Registros descartados" style="background: rgba(239, 68, 68, 0.15); color: #b91c1c;">
                                        <ion-icon name="close-circle-outline" style="color: #ef4444;"></ion-icon>
                                        ${totalDescarte}
                                    </span>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            `;

            // Placeholder para dias (serão carregados sob demanda)
            html += `<tr class="row-dia-placeholder" data-ponto="${cdPonto}" style="display: none;">
                <td colspan="11" class="loading-cell">
                    <div class="loading-inline"><ion-icon name="sync-outline" class="spin"></ion-icon> Carregando dias...</div>
                </td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }

    // ============================================
    // Toggle Grupo de Ponto de Medição (modo Ponto => Dia => Registros)
    // ============================================
    function toggleGrupoPontoMedicao(cdPonto) {
        const header = document.querySelector(`.row-group-header[data-ponto="${cdPonto}"]`);
        const isExpanded = header.classList.contains('expanded');

        if (isExpanded) {
            // Colapsar
            header.classList.remove('expanded');
            document.querySelectorAll(`.row-subgroup-dia[data-ponto="${cdPonto}"]`).forEach(row => {
                row.style.display = 'none';
            });
            document.querySelectorAll(`.row-data[data-ponto="${cdPonto}"]`).forEach(row => {
                row.style.display = 'none';
            });
        } else {
            // Expandir
            header.classList.add('expanded');

            // Verificar se já carregou os dias
            if (diasPontoCarregados[cdPonto]) {
                document.querySelectorAll(`.row-subgroup-dia[data-ponto="${cdPonto}"]`).forEach(row => {
                    row.style.display = 'table-row';
                });
            } else {
                // Carregar dias sob demanda
                carregarDiasPonto(cdPonto);
            }
        }
    }

    // ============================================
    // Carregar dias de um ponto de medição
    // ============================================
    function carregarDiasPonto(cdPonto) {
        const placeholder = document.querySelector(`.row-dia-placeholder[data-ponto="${cdPonto}"]`);
        if (placeholder) {
            placeholder.style.display = 'table-row';
        }

        $.get('bd/registroVazaoPressao/getDiasPonto.php', {
            cd_ponto_medicao: cdPonto,
            data_inicio: filtrosAtuais.data_inicio || '',
            data_fim: filtrosAtuais.data_fim || '',
            descarte: filtrosAtuais.descarte || ''
        }, function (response) {
            if (response.success) {
                diasPontoCarregados[cdPonto] = response.diasPonto;
                renderizarDiasPonto(cdPonto, response.diasPonto);
            } else {
                showToast(response.message || 'Erro ao carregar dias', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // Renderizar dias de um ponto de medição
    // ============================================
    function renderizarDiasPonto(cdPonto, diasPonto) {
        const placeholder = document.querySelector(`.row-dia-placeholder[data-ponto="${cdPonto}"]`);
        if (!placeholder) return;

        const estatPonto = estatisticasPonto[cdPonto];
        const idTipoMedidor = estatPonto ? estatPonto.idTipoMedidor : 1;

        // Determinar cores baseado no tipo
        let cores = { bg: '#f8fafc', bgHover: '#f1f5f9', border: '#cbd5e1' };
        if (idTipoMedidor == 4) {
            cores = { bg: 'rgba(249, 115, 22, 0.05)', bgHover: 'rgba(249, 115, 22, 0.1)', border: '#f97316' };
        } else if (idTipoMedidor == 6) {
            cores = { bg: 'rgba(6, 182, 212, 0.05)', bgHover: 'rgba(6, 182, 212, 0.1)', border: '#06b6d4' };
        }

        // Formatar médias
        const formatarMedia = (valor, unidade) => {
            if (valor === null || valor === undefined) return '-';
            return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + unidade;
        };

        let html = '';
        const diasOrdenados = Object.keys(diasPonto).sort((a, b) => b.localeCompare(a));

        diasOrdenados.forEach(dataChave => {
            const estatDia = diasPonto[dataChave];

            // Formatar data
            const dataObj = new Date(dataChave + 'T12:00:00');
            const dataFormatada = dataObj.toLocaleDateString('pt-BR', {
                weekday: 'short',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });

            const totalExibir = estatDia.total || 0;
            const totalNaoDescarte = estatDia.totalNaoDescarte || 0;
            const totalDescarte = estatDia.totalDescarte || 0;
            const percentualCompleto = Math.min(100, Math.round((totalNaoDescarte / 1440) * 100));
            const corAlerta = totalNaoDescarte >= 1440 ? '#22c55e' : '#ef4444';

            html += `
                <tr class="row-subgroup-header row-subgroup-dia" data-ponto="${cdPonto}" data-dia="${dataChave}" onclick="toggleGrupoDiaPonto('${cdPonto}', '${dataChave}')" style="display: table-row; background: linear-gradient(135deg, ${cores.bg} 0%, ${cores.bgHover} 100%); border-left: 3px solid ${cores.border};">
                    <td colspan="11" style="padding-left: 40px;">
                        <div class="subgroup-content">
                            <div class="subgroup-header-row">
                                <div class="subgroup-toggle">
                                    ${podeEditar ? `<input type="checkbox" class="checkbox-selecao checkbox-dia-ponto" data-ponto="${cdPonto}" data-dia="${dataChave}" onclick="event.stopPropagation(); selecionarDiaPonto('${cdPonto}', '${dataChave}', this.checked)" title="Selecionar todos os registros deste dia">` : ''}
                                    <ion-icon name="chevron-forward-outline"></ion-icon>
                                    <span class="subgroup-name">${dataFormatada}</span>
                                    <span class="subgroup-count" title="Total de registros">${totalExibir}</span>
                                    <span class="subgroup-alert" title="${percentualCompleto}% completo (${totalNaoDescarte}/1440 registros válidos)" style="color: ${corAlerta};">
                                        <ion-icon name="${totalNaoDescarte >= 1440 ? 'checkmark-circle' : 'alert-circle'}-outline"></ion-icon>
                                    </span>
                                    <button type="button" class="btn-chart" onclick="event.stopPropagation(); abrirGraficoDiaPonto('${cdPonto}', '${dataChave}')" title="Ver gráfico por hora">
                                        <ion-icon name="analytics-outline"></ion-icon>
                                    </button>
                                </div>
                                <div class="subgroup-stats">
                                    <span class="stat-item" title="Média Vazão Efetiva">
                                        <ion-icon name="water-outline"></ion-icon>
                                        ${formatarMedia(estatDia.mediaVazao, 'l/s')}
                                    </span>
                                    <span class="stat-item" title="Média Pressão">
                                        <ion-icon name="speedometer-outline"></ion-icon>
                                        ${formatarMedia(estatDia.mediaPressao, 'mca')}
                                    </span>
                                    <span class="stat-item stat-valid" title="Registros válidos">
                                        <ion-icon name="checkmark-circle-outline"></ion-icon>
                                        ${totalNaoDescarte}
                                    </span>
                                    ${totalDescarte > 0 ? `
                                    <span class="stat-item stat-descarte" title="Registros descartados" style="background: rgba(239, 68, 68, 0.15); color: #b91c1c;">
                                        <ion-icon name="close-circle-outline" style="color: #ef4444;"></ion-icon>
                                        ${totalDescarte}
                                    </span>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            `;

            // Placeholder para registros
            html += `<tr class="row-data-placeholder-dia" data-ponto="${cdPonto}" data-dia="${dataChave}" style="display: none;">
                <td colspan="11" class="loading-cell" style="padding-left: 60px;">
                    <div class="loading-inline"><ion-icon name="sync-outline" class="spin"></ion-icon> Carregando registros...</div>
                </td>
            </tr>`;
        });

        // Inserir após o placeholder e remover o placeholder
        placeholder.insertAdjacentHTML('afterend', html);
        placeholder.remove();
    }

    // ============================================
    // Toggle Grupo de Dia dentro de Ponto (modo Ponto => Dia => Registros)
    // ============================================
    function toggleGrupoDiaPonto(cdPonto, dataChave) {
        const header = document.querySelector(`.row-subgroup-dia[data-ponto="${cdPonto}"][data-dia="${dataChave}"]`);
        const isExpanded = header.classList.contains('expanded');
        const cacheKey = `${cdPonto}_${dataChave}`;

        if (isExpanded) {
            // Colapsar
            header.classList.remove('expanded');
            document.querySelectorAll(`.row-data[data-ponto="${cdPonto}"][data-dia="${dataChave}"]`).forEach(row => {
                row.style.display = 'none';
            });
        } else {
            // Expandir
            header.classList.add('expanded');

            // Verificar se já carregou
            if (registrosCarregados[cacheKey]) {
                document.querySelectorAll(`.row-data[data-ponto="${cdPonto}"][data-dia="${dataChave}"]`).forEach(row => {
                    row.style.display = 'table-row';
                });
            } else {
                // Carregar registros
                carregarRegistrosDiaPonto(cdPonto, dataChave);
            }
        }
    }

    // ============================================
    // Carregar registros de um dia de um ponto (modo Ponto => Dia => Registros)
    // ============================================
    function carregarRegistrosDiaPonto(cdPonto, dataChave) {
        const placeholder = document.querySelector(`.row-data-placeholder-dia[data-ponto="${cdPonto}"][data-dia="${dataChave}"]`);
        if (placeholder) {
            placeholder.style.display = 'table-row';
        }

        $.get('bd/registroVazaoPressao/getRegistrosPonto.php', {
            data: dataChave,
            cd_ponto_medicao: cdPonto,
            descarte: filtrosAtuais.descarte || ''
        }, function (response) {
            if (response.success) {
                const cacheKey = `${cdPonto}_${dataChave}`;
                registrosCarregados[cacheKey] = response.data;
                renderizarRegistrosDiaPonto(cdPonto, dataChave, response.data);
            } else {
                showToast(response.message || 'Erro ao carregar registros', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // Renderizar registros de um dia de um ponto (modo Ponto => Dia => Registros)
    // ============================================
    function renderizarRegistrosDiaPonto(cdPonto, dataChave, registros) {
        const placeholder = document.querySelector(`.row-data-placeholder-dia[data-ponto="${cdPonto}"][data-dia="${dataChave}"]`);
        if (!placeholder) return;

        let html = '';
        registros.forEach(item => {
            const dataLeitura = item.DT_LEITURA ?
                new Date(item.DT_LEITURA).toLocaleString('pt-BR') : '-';

            // Tipo Registro
            const tipoRegistro = tiposRegistro[item.ID_TIPO_REGISTRO] || '-';

            // Tipo Vazão
            const tipoVazao = tiposVazao[item.ID_TIPO_VAZAO] || '-';

            // Descarte com badge
            let descarteBadge = '-';
            if (item.ID_SITUACAO) {
                const descarteNome = descartes[item.ID_SITUACAO] || '-';
                let badgeClass = 'badge-secondary';
                if (item.ID_SITUACAO == 1) badgeClass = 'badge-success';
                else if (item.ID_SITUACAO == 2) badgeClass = 'badge-danger';
                descarteBadge = `<span class="badge ${badgeClass}">${descarteNome}</span>`;
            }

            // Formatação dos valores numéricos
            const vazao = item.VL_VAZAO_EFETIVA !== null && item.VL_VAZAO_EFETIVA !== '' ?
                parseFloat(item.VL_VAZAO_EFETIVA).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';
            const pressao = item.VL_PRESSAO !== null && item.VL_PRESSAO !== '' ?
                parseFloat(item.VL_PRESSAO).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';
            const nivelReserv = item.VL_RESERVATORIO !== null && item.VL_RESERVATORIO !== '' ?
                parseFloat(item.VL_RESERVATORIO).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';

            html += `
                <tr class="row-data visible" data-ponto="${cdPonto}" data-dia="${dataChave}" data-chave="${item.CD_CHAVE}" style="padding-left: 60px;">
                    <td style="padding-left: 60px;">
                        ${podeEditar ? `<input type="checkbox" class="checkbox-selecao checkbox-registro" data-chave="${item.CD_CHAVE}" data-ponto="${cdPonto}" data-dia="${dataChave}" onclick="selecionarRegistro(${item.CD_CHAVE}, this.checked)" title="Selecionar registro">` : ''}
                        ${item.DS_UNIDADE || '-'}
                    </td>
                    <td class="truncate">${item.DS_LOCALIDADE || '-'}</td>
                    <td class="truncate">${item.DS_PONTO_MEDICAO || '-'}</td>
                    <td>${tipoRegistro}</td>
                    <td>${tipoVazao}</td>
                    <td>${dataLeitura}</td>
                    <td class="number">${vazao}</td>
                    <td class="number">${pressao}</td>
                    <td class="number">${nivelReserv}</td>
                    <td>${descarteBadge}</td>
                    <td>
                        <div class="table-actions">
                            <button type="button" class="btn-action" onclick="visualizarRegistro(${item.CD_CHAVE})" title="Visualizar">
                                <ion-icon name="eye-outline"></ion-icon>
                            </button>
                            ${podeEditar ? `
                            <button type="button" class="btn-action delete" onclick="excluirRegistro(${item.CD_CHAVE})" title="Descartar">
                                <ion-icon name="trash-outline"></ion-icon>
                            </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });

        // Inserir registros após o placeholder e remover o placeholder
        placeholder.insertAdjacentHTML('afterend', html);
        placeholder.remove();
    }

    // ============================================
    // Abrir gráfico de um dia de um ponto (modo Ponto => Dia => Registros)
    // ============================================
    function abrirGraficoDiaPonto(cdPonto, dataChave) {
        const estatPonto = estatisticasPonto[cdPonto];
        const diasPonto = diasPontoCarregados[cdPonto];

        if (!estatPonto || !diasPonto || !diasPonto[dataChave]) {
            showToast('Dados não disponíveis para este dia', 'erro');
            return;
        }

        const estatDia = diasPonto[dataChave];
        const idTipoMedidor = estatPonto.idTipoMedidor;
        const codigoFormatado = estatPonto.codigoFormatado || cdPonto;

        // Determinar título, campo e unidade baseado no tipo de medidor
        let tituloGrafico = 'Gráfico de Vazão';
        let campoValor = 'mediaVazao';
        let unidade = 'l/s';
        let corGradiente = '#3b82f6';

        if (idTipoMedidor == 4) {
            tituloGrafico = 'Gráfico de Pressão';
            campoValor = 'mediaPressao';
            unidade = 'mca';
            corGradiente = '#f59e0b';
        } else if (idTipoMedidor == 6) {
            tituloGrafico = 'Gráfico de Nível de Reservatório';
            campoValor = 'nivelValor';
            unidade = 'm';
            corGradiente = '#06b6d4';
        }

        // Formatar data para o título
        const dataObj = new Date(dataChave + 'T12:00:00');
        const dataFormatada = dataObj.toLocaleDateString('pt-BR', {
            weekday: 'long',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
        const dataCapitalizada = dataFormatada.charAt(0).toUpperCase() + dataFormatada.slice(1);

        // Atualizar título
        document.getElementById('modalGraficoTitulo').textContent = `${tituloGrafico} - ${codigoFormatado} (${dataCapitalizada})`;

        // Atualizar informações
        const formatarNumero = (val) => val !== null ? val.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';

        document.getElementById('graficoInfo').innerHTML = `
            <div class="grafico-info-item">
                <ion-icon name="location-outline"></ion-icon>
                <span>Ponto: <strong>${codigoFormatado}</strong></span>
            </div>
            <div class="grafico-info-item">
                <ion-icon name="document-text-outline"></ion-icon>
                <span>Total: <strong>${estatDia.totalGeral || estatDia.total}</strong> registros</span>
            </div>
            <div class="grafico-info-item">
                <ion-icon name="checkmark-circle-outline"></ion-icon>
                <span>Válidos: <strong>${estatDia.totalNaoDescarte}</strong></span>
            </div>
            ${estatDia.totalDescarte > 0 ? `
            <div class="grafico-info-item" style="color: #dc2626;">
                <ion-icon name="close-circle-outline"></ion-icon>
                <span>Descartados: <strong>${estatDia.totalDescarte}</strong></span>
            </div>
            ` : ''}
            <div class="grafico-info-item">
                <ion-icon name="water-outline"></ion-icon>
                <span>Média Vazão: <strong>${formatarNumero(estatDia.mediaVazao)} l/s</strong></span>
            </div>
            <div class="grafico-info-item">
                <ion-icon name="speedometer-outline"></ion-icon>
                <span>Média Pressão: <strong>${formatarNumero(estatDia.mediaPressao)} mca</strong></span>
            </div>
        `;

        // Renderizar gráfico
        renderizarGraficoModal(estatDia.porHora || {}, campoValor, unidade, corGradiente);

        // Abrir modal
        document.getElementById('modalGrafico').classList.add('active');
    }

    // ============================================
    // Funções de seleção para modo Ponto => Dia => Registros
    // ============================================
    function selecionarPontoMedicao(cdPonto, checked) {
        // Buscar todos os registros do ponto de medição
        const params = {
            cd_ponto_medicao: cdPonto,
            data_inicio: filtrosAtuais.data_inicio || '',
            data_fim: filtrosAtuais.data_fim || '',
            descarte: filtrosAtuais.descarte || '',
            apenas_chaves: 1
        };

        $.get('bd/registroVazaoPressao/getRegistrosPontoMedicao.php', params, function (response) {
            if (response.success && response.chaves) {
                response.chaves.forEach(chave => {
                    if (checked) {
                        registrosSelecionados.add(chave);
                    } else {
                        registrosSelecionados.delete(chave);
                    }
                });

                // Atualizar checkboxes visíveis
                document.querySelectorAll(`.checkbox-registro[data-ponto="${cdPonto}"]`).forEach(cb => {
                    cb.checked = checked;
                });
                document.querySelectorAll(`.checkbox-dia-ponto[data-ponto="${cdPonto}"]`).forEach(cb => {
                    cb.checked = checked;
                });

                atualizarBarraExclusao();
            }
        }, 'json');
    }

    function selecionarDiaPonto(cdPonto, dataChave, checked) {
        // Buscar todos os registros do dia dentro do ponto
        const params = {
            data: dataChave,
            cd_ponto_medicao: cdPonto,
            descarte: filtrosAtuais.descarte || '',
            apenas_chaves: 1
        };

        $.get('bd/registroVazaoPressao/getRegistrosPonto.php', params, function (response) {
            if (response.success && response.chaves) {
                response.chaves.forEach(chave => {
                    if (checked) {
                        registrosSelecionados.add(chave);
                    } else {
                        registrosSelecionados.delete(chave);
                    }
                });

                // Atualizar checkboxes dos registros visíveis
                document.querySelectorAll(`.checkbox-registro[data-ponto="${cdPonto}"][data-dia="${dataChave}"]`).forEach(cb => {
                    cb.checked = checked;
                });

                atualizarBarraExclusao();
            }
        }, 'json');
    }

    // ============================================
    // Renderizar registros de um dia
    // ============================================
    function renderizarRegistrosDia(dataChave, registros) {
        const placeholder = document.querySelector(`.row-data-placeholder[data-group="${dataChave}"]`);
        if (!placeholder) return;

        let html = '';
        registros.forEach(item => {
            const dataLeitura = item.DT_LEITURA ?
                new Date(item.DT_LEITURA).toLocaleString('pt-BR') : '-';

            // Tipo Registro
            const tipoRegistro = tiposRegistro[item.ID_TIPO_REGISTRO] || '-';

            // Tipo Vazão
            const tipoVazao = tiposVazao[item.ID_TIPO_VAZAO] || '-';

            // Descarte com badge (ID_SITUACAO: 1 = Não, 2 = Sim)
            let descarteBadge = '-';
            if (item.ID_SITUACAO) {
                const descarteNome = descartes[item.ID_SITUACAO] || '-';
                let badgeClass = 'badge-secondary';
                if (item.ID_SITUACAO == 1) badgeClass = 'badge-success';
                else if (item.ID_SITUACAO == 2) badgeClass = 'badge-danger';
                descarteBadge = `<span class="badge ${badgeClass}">${descarteNome}</span>`;
            }

            // Formatação dos valores numéricos
            const vazao = item.VL_VAZAO_EFETIVA !== null && item.VL_VAZAO_EFETIVA !== '' ?
                parseFloat(item.VL_VAZAO_EFETIVA).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';
            const pressao = item.VL_PRESSAO !== null && item.VL_PRESSAO !== '' ?
                parseFloat(item.VL_PRESSAO).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';
            const nivelReserv = item.VL_RESERVATORIO !== null && item.VL_RESERVATORIO !== '' ?
                parseFloat(item.VL_RESERVATORIO).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';

            html += `
                <tr class="row-data visible" data-group="${dataChave}">
                    <td class="truncate">${item.DS_UNIDADE || '-'}</td>
                    <td class="truncate">${item.DS_LOCALIDADE || '-'}</td>
                    <td class="truncate">${item.DS_PONTO_MEDICAO || '-'}</td>
                    <td>${tipoRegistro}</td>
                    <td>${tipoVazao}</td>
                    <td>${dataLeitura}</td>
                    <td class="number">${vazao}</td>
                    <td class="number">${pressao}</td>
                    <td class="number">${nivelReserv}</td>
                    <td>${descarteBadge}</td>
                    <td>
                        <div class="table-actions">
                            <button type="button" class="btn-action" onclick="visualizarRegistro(${item.CD_CHAVE})" title="Visualizar">
                                <ion-icon name="eye-outline"></ion-icon>
                            </button>
                            ${podeEditar ? `
                            <button type="button" class="btn-action delete" onclick="excluirRegistro(${item.CD_CHAVE})" title="Descartar">
                                <ion-icon name="trash-outline"></ion-icon>
                            </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });

        // Inserir registros após o placeholder e remover o placeholder
        placeholder.insertAdjacentHTML('afterend', html);
        placeholder.remove();
    }

    // ============================================
    // Toggle Grupo (Expandir/Colapsar) com carregamento sob demanda
    // ============================================
    function toggleGrupo(dataChave) {
        const header = document.querySelector(`.row-group-header[data-group="${dataChave}"]`);
        const isExpanded = header.classList.contains('expanded');

        if (isExpanded) {
            // Colapsar
            header.classList.remove('expanded');
            // Ocultar subgrupos e registros
            document.querySelectorAll(`.row-subgroup-header[data-group="${dataChave}"]`).forEach(row => {
                row.style.display = 'none';
                row.classList.remove('expanded');
            });
            document.querySelectorAll(`.row-data[data-group="${dataChave}"]`).forEach(row => {
                row.classList.remove('visible');
            });
            document.querySelectorAll(`.row-data-placeholder[data-group="${dataChave}"]`).forEach(row => {
                row.style.display = 'none';
            });
        } else {
            // Expandir
            header.classList.add('expanded');

            // Verificar se filtrou por ponto de medição específico
            const filtrouPorPonto = filtrosAtuais.cd_ponto_medicao && filtrosAtuais.cd_ponto_medicao !== '';

            if (filtrouPorPonto) {
                // Filtrou por ponto específico - carrega registros diretamente
                if (registrosCarregados[dataChave]) {
                    document.querySelectorAll(`.row-data[data-group="${dataChave}"]`).forEach(row => {
                        row.classList.add('visible');
                    });
                } else {
                    carregarRegistrosDia(dataChave);
                }
            } else {
                // Não filtrou por ponto - carrega pontos de medição
                if (pontosMedicaoCarregados[dataChave]) {
                    // Já carregou, apenas mostrar subgrupos
                    document.querySelectorAll(`.row-subgroup-header[data-group="${dataChave}"]`).forEach(row => {
                        row.style.display = 'table-row';
                    });
                } else {
                    // Carregar pontos de medição
                    carregarPontosMedicaoDia(dataChave);
                }
            }
        }
    }

    // ============================================
    // Carregar pontos de medição de um dia
    // ============================================
    function carregarPontosMedicaoDia(dataChave) {
        const placeholder = document.querySelector(`.row-data-placeholder[data-group="${dataChave}"]`);
        if (placeholder) {
            placeholder.style.display = 'table-row';
        }

        const params = {
            data: dataChave,
            cd_unidade: filtrosAtuais.cd_unidade || '',
            cd_localidade: filtrosAtuais.cd_localidade || '',
            tipo_medidor: filtrosAtuais.tipo_medidor || '',
            tipo_leitura: filtrosAtuais.tipo_leitura || '',
            descarte: filtrosAtuais.descarte || ''
        };

        $.get('bd/registroVazaoPressao/getPontosMedicaoDia.php', params, function (response) {
            if (response.success) {
                pontosMedicaoCarregados[dataChave] = response.pontosMedicao;
                renderizarPontosMedicaoDia(dataChave, response.pontosMedicao);
            } else {
                if (placeholder) {
                    placeholder.innerHTML = `<td colspan="11"><div class="error-inline">Erro ao carregar pontos de medição</div></td>`;
                }
                showToast(response.message || 'Erro ao carregar pontos de medição', 'erro');
            }
        }, 'json').fail(function () {
            if (placeholder) {
                placeholder.innerHTML = `<td colspan="11"><div class="error-inline">Erro ao comunicar com o servidor</div></td>`;
            }
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // Renderizar pontos de medição de um dia
    // ============================================
    function renderizarPontosMedicaoDia(dataChave, pontosMedicao) {
        const placeholder = document.querySelector(`.row-data-placeholder[data-group="${dataChave}"]`);
        if (!placeholder) return;

        const formatarMedia = (valor, unidade) => {
            if (valor === null || valor === undefined) return '-';
            return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + unidade;
        };

        // Função para calcular cor do gradiente (vermelho claro → verde claro)
        // 0 registros = vermelho, 1440 registros = verde
        const calcularCorGradiente = (registrosValidos) => {
            const percentual = Math.min(registrosValidos / 1440, 1); // 0 a 1

            // Cores: vermelho claro (0%) → amarelo (50%) → verde claro (100%)
            let r, g, b;

            if (percentual < 0.5) {
                // Vermelho para amarelo (0% a 50%)
                const t = percentual * 2; // 0 a 1
                r = 254; // Mantém vermelho alto
                g = Math.round(202 + (227 - 202) * t); // 202 → 227
                b = Math.round(202 + (183 - 202) * t); // 202 → 183
            } else {
                // Amarelo para verde (50% a 100%)
                const t = (percentual - 0.5) * 2; // 0 a 1
                r = Math.round(254 - (254 - 220) * t); // 254 → 220
                g = Math.round(227 + (252 - 227) * t); // 227 → 252
                b = Math.round(183 + (231 - 183) * t); // 183 → 231
            }

            return {
                bg: `rgba(${r}, ${g}, ${b}, 0.4)`,
                bgHover: `rgba(${r}, ${g}, ${b}, 0.6)`,
                border: `rgb(${Math.max(0, r - 40)}, ${Math.max(0, g - 40)}, ${Math.max(0, b - 40)})`
            };
        };

        // Função para cor do ícone de alerta baseado na completude
        const calcularCorAlerta = (registrosValidos) => {
            const percentual = Math.min(registrosValidos / 1440, 1);
            if (percentual >= 1) return '#22c55e'; // Verde
            if (percentual >= 0.7) return '#84cc16'; // Lima
            if (percentual >= 0.5) return '#eab308'; // Amarelo
            if (percentual >= 0.3) return '#f97316'; // Laranja
            return '#ef4444'; // Vermelho
        };

        // Função para obter ícone por tipo de medidor
        const getIconeMedidor = (idTipoMedidor) => {
            const icones = {
                1: 'speedometer-outline',      // M - Macromedidor
                2: 'analytics-outline',        // E - Estação Pitométrica
                4: 'thermometer-outline',      // P - Medidor Pressão
                6: 'water-outline',            // R - Nível Reservatório
                8: 'home-outline'              // H - Hidrômetro
            };
            return icones[idTipoMedidor] || 'hardware-chip-outline';
        };

        let html = '';
        Object.keys(pontosMedicao).forEach(cdPonto => {
            const ponto = pontosMedicao[cdPonto];
            const totalExibir = ponto.total || 0;
            const totalNaoDescarte = ponto.totalNaoDescarte || 0;
            const totalDescarte = ponto.totalDescarte || 0;
            const cores = calcularCorGradiente(totalNaoDescarte);
            const corAlerta = calcularCorAlerta(totalNaoDescarte);
            const percentualCompleto = Math.min(Math.round((totalNaoDescarte / 1440) * 100), 100);

            // Determinar label e valor do nível (Média ou Máx para Reservatório)
            const nivelLabel = ponto.nivelTipo === 'max' ? 'Máx. Nível Reservatório' : 'Média Nível Reservatório';
            const nivelIcon = ponto.nivelTipo === 'max' ? 'arrow-up-outline' : 'layers-outline';

            // Classe e ícone do tipo de medidor
            const idTipoMedidor = ponto.idTipoMedidor || '';
            const classeMedidor = idTipoMedidor ? `badge-medidor-${idTipoMedidor}` : 'badge-tipo-medidor';
            const iconeMedidor = getIconeMedidor(idTipoMedidor);

            html += `
                <tr class="row-subgroup-header" data-group="${dataChave}" data-ponto="${cdPonto}" onclick="toggleGrupoPonto('${dataChave}', '${cdPonto}')" style="display: table-row; background: linear-gradient(135deg, ${cores.bg} 0%, ${cores.bgHover} 100%); border-left: 3px solid ${cores.border};">
                    <td colspan="11">
                        <div class="subgroup-content">
                            <div class="subgroup-header-row">
                                <div class="subgroup-toggle">
                                    ${podeEditar ? `<input type="checkbox" class="checkbox-selecao checkbox-ponto" data-dia="${dataChave}" data-ponto="${cdPonto}" onclick="event.stopPropagation(); selecionarPonto('${dataChave}', '${cdPonto}', this.checked)" title="Selecionar todos os registros deste ponto">` : ''}
                                    <ion-icon name="chevron-forward-outline"></ion-icon>
                                    <span class="subgroup-name">${ponto.codigoFormatado || cdPonto} - ${ponto.dsPontoMedicao || ''}</span>
                                    <div class="subgroup-badges">
                                        <span class="badge-tipo ${classeMedidor}" title="Tipo de Medidor">
                                            <ion-icon name="${iconeMedidor}" style="font-size: 10px; margin-right: 3px;"></ion-icon>
                                            ${ponto.dsTipoMedidor || '-'}
                                        </span>
                                        <span class="badge-tipo badge-tipo-leitura" title="Tipo de Leitura">${ponto.dsTipoLeitura || '-'}</span>
                                    </div>
                                    <span class="subgroup-count" title="Total de registros (filtrado)">${totalExibir}</span>
                                    <span class="subgroup-alert" title="${percentualCompleto}% completo (${totalNaoDescarte}/1440 registros válidos)" style="color: ${corAlerta};">
                                        <ion-icon name="${totalNaoDescarte >= 1440 ? 'checkmark-circle' : 'alert-circle'}-outline"></ion-icon>
                                    </span>
                                    <button type="button" class="btn-chart" onclick="event.stopPropagation(); abrirGraficoPonto('${dataChave}', '${cdPonto}')" title="Ver gráfico por hora">
                                        <ion-icon name="analytics-outline"></ion-icon>
                                    </button>
                                </div>
                                <div class="subgroup-stats">
                                    <span class="stat-item" title="Média Vazão Efetiva">
                                        <ion-icon name="water-outline"></ion-icon>
                                        ${formatarMedia(ponto.mediaVazao, 'l/s')}
                                    </span>
                                    <span class="stat-item" title="Média Pressão">
                                        <ion-icon name="speedometer-outline"></ion-icon>
                                        ${formatarMedia(ponto.mediaPressao, 'mca')}
                                    </span>
                                    <span class="stat-item" title="${nivelLabel}">
                                        <ion-icon name="${nivelIcon}"></ion-icon>
                                        ${ponto.nivelTipo === 'max' ? 'Máx: ' : ''}${formatarMedia(ponto.nivelValor, 'm')}
                                    </span>
                                    <span class="stat-item stat-valid" title="Registros válidos (não descartados)" style="background: rgba(34, 197, 94, 0.15); color: #15803d;">
                                        <ion-icon name="checkmark-circle-outline" style="color: #22c55e;"></ion-icon>
                                        ${totalNaoDescarte}
                                    </span>
                                    ${totalDescarte > 0 ? `
                                    <span class="stat-item stat-descarte" title="Registros descartados" style="background: rgba(239, 68, 68, 0.15); color: #b91c1c;">
                                        <ion-icon name="close-circle-outline" style="color: #ef4444;"></ion-icon>
                                        ${totalDescarte}
                                    </span>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            `;

            // Placeholder para registros do ponto
            html += `<tr class="row-data-placeholder" data-group="${dataChave}" data-ponto="${cdPonto}" style="display: none;">
                <td colspan="11" class="loading-cell">
                    <div class="loading-inline"><ion-icon name="sync-outline" class="spin"></ion-icon> Carregando registros...</div>
                </td>
            </tr>`;
        });

        // Inserir após o placeholder e remover o placeholder original
        placeholder.insertAdjacentHTML('afterend', html);
        placeholder.remove();
    }

    // ============================================
    // Toggle Subgrupo (Ponto de Medição)
    // ============================================
    function toggleGrupoPonto(dataChave, cdPonto) {
        const header = document.querySelector(`.row-subgroup-header[data-group="${dataChave}"][data-ponto="${cdPonto}"]`);
        const isExpanded = header.classList.contains('expanded');
        const cacheKey = `${dataChave}_${cdPonto}`;

        if (isExpanded) {
            // Colapsar
            header.classList.remove('expanded');
            document.querySelectorAll(`.row-data[data-group="${dataChave}"][data-ponto="${cdPonto}"]`).forEach(row => {
                row.classList.remove('visible');
            });
        } else {
            // Expandir
            header.classList.add('expanded');

            if (registrosCarregados[cacheKey]) {
                // Já carregou, apenas mostrar
                document.querySelectorAll(`.row-data[data-group="${dataChave}"][data-ponto="${cdPonto}"]`).forEach(row => {
                    row.classList.add('visible');
                });
            } else {
                // Carregar registros do ponto
                carregarRegistrosPonto(dataChave, cdPonto);
            }
        }
    }

    // ============================================
    // Carregar registros de um dia e ponto específico
    // ============================================
    function carregarRegistrosPonto(dataChave, cdPonto) {
        const placeholder = document.querySelector(`.row-data-placeholder[data-group="${dataChave}"][data-ponto="${cdPonto}"]`);
        if (placeholder) {
            placeholder.style.display = 'table-row';
        }

        const params = {
            data: dataChave,
            cd_ponto_medicao: cdPonto,
            descarte: filtrosAtuais.descarte || ''
        };

        $.get('bd/registroVazaoPressao/getRegistrosDia.php', params, function (response) {
            if (response.success) {
                const cacheKey = `${dataChave}_${cdPonto}`;
                registrosCarregados[cacheKey] = response.data;
                renderizarRegistrosPonto(dataChave, cdPonto, response.data);
            } else {
                if (placeholder) {
                    placeholder.innerHTML = `<td colspan="11"><div class="error-inline">Erro ao carregar registros</div></td>`;
                }
                showToast(response.message || 'Erro ao carregar registros', 'erro');
            }
        }, 'json').fail(function () {
            if (placeholder) {
                placeholder.innerHTML = `<td colspan="11"><div class="error-inline">Erro ao comunicar com o servidor</div></td>`;
            }
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // Renderizar registros de um ponto de medição
    // ============================================
    function renderizarRegistrosPonto(dataChave, cdPonto, registros) {
        const placeholder = document.querySelector(`.row-data-placeholder[data-group="${dataChave}"][data-ponto="${cdPonto}"]`);
        if (!placeholder) return;

        let html = '';
        registros.forEach(item => {
            const dataLeitura = item.DT_LEITURA ?
                new Date(item.DT_LEITURA).toLocaleString('pt-BR') : '-';

            const tipoRegistro = tiposRegistro[item.ID_TIPO_REGISTRO] || '-';
            const tipoVazao = tiposVazao[item.ID_TIPO_VAZAO] || '-';

            let descarteBadge = '-';
            if (item.ID_SITUACAO) {
                const descarteNome = descartes[item.ID_SITUACAO] || '-';
                let badgeClass = 'badge-secondary';
                if (item.ID_SITUACAO == 1) badgeClass = 'badge-success';
                else if (item.ID_SITUACAO == 2) badgeClass = 'badge-danger';
                descarteBadge = `<span class="badge ${badgeClass}">${descarteNome}</span>`;
            }

            const vazao = item.VL_VAZAO_EFETIVA !== null && item.VL_VAZAO_EFETIVA !== '' ?
                parseFloat(item.VL_VAZAO_EFETIVA).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';
            const pressao = item.VL_PRESSAO !== null && item.VL_PRESSAO !== '' ?
                parseFloat(item.VL_PRESSAO).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';
            const nivelReserv = item.VL_RESERVATORIO !== null && item.VL_RESERVATORIO !== '' ?
                parseFloat(item.VL_RESERVATORIO).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';

            html += `
                <tr class="row-data visible" data-group="${dataChave}" data-ponto="${cdPonto}" data-chave="${item.CD_CHAVE}">
                    <td class="truncate">
                        ${podeEditar ? `<input type="checkbox" class="checkbox-selecao checkbox-registro" data-chave="${item.CD_CHAVE}" data-dia="${dataChave}" data-ponto="${cdPonto}" onclick="selecionarRegistro(${item.CD_CHAVE}, this.checked)" title="Selecionar registro">` : ''}
                        ${item.DS_UNIDADE || '-'}
                    </td>
                    <td class="truncate">${item.DS_LOCALIDADE || '-'}</td>
                    <td class="truncate">${item.DS_PONTO_MEDICAO || '-'}</td>
                    <td>${tipoRegistro}</td>
                    <td>${tipoVazao}</td>
                    <td>${dataLeitura}</td>
                    <td class="number">${vazao}</td>
                    <td class="number">${pressao}</td>
                    <td class="number">${nivelReserv}</td>
                    <td>${descarteBadge}</td>
                    <td>
                        <div class="table-actions">
                            <button type="button" class="btn-action" onclick="visualizarRegistro(${item.CD_CHAVE})" title="Visualizar">
                                <ion-icon name="eye-outline"></ion-icon>
                            </button>
                            ${podeEditar ? `
                            <button type="button" class="btn-action delete" onclick="excluirRegistro(${item.CD_CHAVE})" title="Descartar">
                                <ion-icon name="trash-outline"></ion-icon>
                            </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });

        placeholder.insertAdjacentHTML('afterend', html);
        placeholder.remove();
    }

    // ============================================
    // Carregar registros de um dia (quando filtrou por ponto específico)
    // ============================================
    function carregarRegistrosDia(dataChave) {
        const placeholder = document.querySelector(`.row-data-placeholder[data-group="${dataChave}"]`);
        if (placeholder) {
            placeholder.style.display = 'table-row';
        }

        const params = {
            data: dataChave,
            cd_unidade: filtrosAtuais.cd_unidade || '',
            cd_localidade: filtrosAtuais.cd_localidade || '',
            cd_ponto_medicao: filtrosAtuais.cd_ponto_medicao || '',
            tipo_medidor: filtrosAtuais.tipo_medidor || '',
            tipo_leitura: filtrosAtuais.tipo_leitura || '',
            descarte: filtrosAtuais.descarte || ''
        };

        $.get('bd/registroVazaoPressao/getRegistrosDia.php', params, function (response) {
            if (response.success) {
                registrosCarregados[dataChave] = response.data;
                renderizarRegistrosDia(dataChave, response.data);
            } else {
                if (placeholder) {
                    placeholder.innerHTML = `<td colspan="11"><div class="error-inline">Erro ao carregar registros</div></td>`;
                }
                showToast(response.message || 'Erro ao carregar registros', 'erro');
            }
        }, 'json').fail(function () {
            if (placeholder) {
                placeholder.innerHTML = `<td colspan="11"><div class="error-inline">Erro ao comunicar com o servidor</div></td>`;
            }
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // Expandir/Colapsar Todos
    // ============================================
    function expandirTodos() {
        // Apenas expandir o primeiro nível (dias)
        document.querySelectorAll('.row-group-header').forEach(header => {
            const dataChave = header.dataset.group;
            if (!header.classList.contains('expanded')) {
                toggleGrupo(dataChave);
            }
        });
    }

    function colapsarTodos() {
        document.querySelectorAll('.row-group-header').forEach(header => {
            header.classList.remove('expanded');
        });
        document.querySelectorAll('.row-subgroup-header').forEach(header => {
            header.classList.remove('expanded');
            header.style.display = 'none';
        });
        document.querySelectorAll('.row-data').forEach(row => {
            row.classList.remove('visible');
        });
        document.querySelectorAll('.row-data-placeholder').forEach(row => {
            row.style.display = 'none';
        });
    }

    // ============================================
    // Modal Gráfico por Hora
    // ============================================
    function abrirGrafico(dataChave) {
        const estatDia = estatisticasDia[dataChave];
        if (!estatDia) {
            showToast('Dados não disponíveis para este dia', 'erro');
            return;
        }

        // Formatar data para o título
        const dataObj = new Date(dataChave + 'T12:00:00');
        const dataFormatada = dataObj.toLocaleDateString('pt-BR', {
            weekday: 'long',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
        const dataCapitalizada = dataFormatada.charAt(0).toUpperCase() + dataFormatada.slice(1);

        // Atualizar título
        document.getElementById('modalGraficoTitulo').textContent = `Gráfico de Vazão - ${dataCapitalizada}`;

        // Atualizar informações
        const formatarNumero = (val) => val !== null ? val.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';

        document.getElementById('graficoInfo').innerHTML = `
            <div class="grafico-info-item">
                <ion-icon name="document-text-outline"></ion-icon>
                <span>Total: <strong>${estatDia.total}</strong> registros</span>
            </div>
            <div class="grafico-info-item">
                <ion-icon name="checkmark-circle-outline"></ion-icon>
                <span>Válidos: <strong>${estatDia.totalNaoDescarte}</strong></span>
            </div>
            <div class="grafico-info-item">
                <ion-icon name="water-outline"></ion-icon>
                <span>Média Vazão: <strong>${formatarNumero(estatDia.mediaVazao)} l/s</strong></span>
            </div>
            <div class="grafico-info-item">
                <ion-icon name="speedometer-outline"></ion-icon>
                <span>Média Pressão: <strong>${formatarNumero(estatDia.mediaPressao)} mca</strong></span>
            </div>
        `;

        // Renderizar gráfico
        renderizarGraficoModal(estatDia.porHora || {});

        // Abrir modal
        document.getElementById('modalGrafico').classList.add('active');
    }

    // ============================================
    // Modal Gráfico por Hora - Ponto de Medição
    // ============================================
    function abrirGraficoPonto(dataChave, cdPonto) {
        const pontosDia = pontosMedicaoCarregados[dataChave];
        if (!pontosDia || !pontosDia[cdPonto]) {
            showToast('Dados não disponíveis para este ponto de medição', 'erro');
            return;
        }

        const ponto = pontosDia[cdPonto];
        const idTipoMedidor = ponto.idTipoMedidor;

        // Determinar título, campo e unidade baseado no tipo de medidor
        let tituloGrafico = 'Gráfico de Vazão';
        let campoValor = 'mediaVazao';
        let unidade = 'l/s';
        let icone = 'water-outline';
        let corGradiente = '#3b82f6'; // Azul

        if (idTipoMedidor == 4) {
            // Medidor de Pressão
            tituloGrafico = 'Gráfico de Pressão';
            campoValor = 'mediaPressao';
            unidade = 'mca';
            icone = 'speedometer-outline';
            corGradiente = '#f59e0b'; // Laranja
        } else if (idTipoMedidor == 6) {
            // Nível Reservatório
            tituloGrafico = 'Gráfico de Nível de Reservatório';
            campoValor = 'nivelValor';
            unidade = 'm';
            icone = 'layers-outline';
            corGradiente = '#06b6d4'; // Ciano
        }

        // Formatar data para o título
        const dataObj = new Date(dataChave + 'T12:00:00');
        const dataFormatada = dataObj.toLocaleDateString('pt-BR', {
            weekday: 'long',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
        const dataCapitalizada = dataFormatada.charAt(0).toUpperCase() + dataFormatada.slice(1);

        // Atualizar título com código formatado do ponto
        const codigoExibir = ponto.codigoFormatado || cdPonto;
        document.getElementById('modalGraficoTitulo').textContent = `${tituloGrafico} - ${codigoExibir} (${dataCapitalizada})`;

        // Atualizar informações
        const formatarNumero = (val) => val !== null ? val.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';

        // Label para nível (Média ou Máx)
        const nivelLabel = ponto.nivelTipo === 'max' ? 'Máx. Nível' : 'Média Nível';

        document.getElementById('graficoInfo').innerHTML = `
            <div class="grafico-info-item">
                <ion-icon name="location-outline"></ion-icon>
                <span>Ponto: <strong>${codigoExibir}</strong></span>
            </div>
            <div class="grafico-info-item">
                <ion-icon name="document-text-outline"></ion-icon>
                <span>Total: <strong>${ponto.totalGeral || ponto.total}</strong> registros</span>
            </div>
            <div class="grafico-info-item">
                <ion-icon name="checkmark-circle-outline"></ion-icon>
                <span>Válidos: <strong>${ponto.totalNaoDescarte}</strong></span>
            </div>
            ${ponto.totalDescarte > 0 ? `
            <div class="grafico-info-item" style="color: #dc2626;">
                <ion-icon name="close-circle-outline"></ion-icon>
                <span>Descartados: <strong>${ponto.totalDescarte}</strong></span>
            </div>
            ` : ''}
            <div class="grafico-info-item">
                <ion-icon name="water-outline"></ion-icon>
                <span>Média Vazão: <strong>${formatarNumero(ponto.mediaVazao)} l/s</strong></span>
            </div>
            <div class="grafico-info-item">
                <ion-icon name="speedometer-outline"></ion-icon>
                <span>Média Pressão: <strong>${formatarNumero(ponto.mediaPressao)} mca</strong></span>
            </div>
            ${ponto.nivelValor !== null ? `
            <div class="grafico-info-item">
                <ion-icon name="${ponto.nivelTipo === 'max' ? 'arrow-up-outline' : 'layers-outline'}"></ion-icon>
                <span>${nivelLabel}: <strong>${formatarNumero(ponto.nivelValor)} m</strong></span>
            </div>
            ` : ''}
        `;

        // Renderizar gráfico com parâmetros dinâmicos
        renderizarGraficoModal(ponto.porHora || {}, campoValor, unidade, corGradiente);

        // Abrir modal
        document.getElementById('modalGrafico').classList.add('active');
    }

    function fecharModalGrafico(event) {
        if (event && event.target !== event.currentTarget) return;
        document.getElementById('modalGrafico').classList.remove('active');
    }

    function renderizarGraficoModal(porHora, campoValor = 'mediaVazao', unidade = 'l/s', corGradiente = '#3b82f6') {
        const container = document.getElementById('graficoContainer');

        // Dimensões do gráfico
        const largura = 860;
        const altura = 260;
        const paddingLeft = 60;
        const paddingRight = 20;
        const paddingTop = 20;
        const paddingBottom = 40;
        const areaLargura = largura - paddingLeft - paddingRight;
        const areaAltura = altura - paddingTop - paddingBottom;

        // Coletar dados das 24 horas
        let pontos = [];
        let maxValor = 0;
        let minValor = Infinity;

        for (let h = 0; h < 24; h++) {
            const dadosHora = porHora[h];
            const valor = dadosHora ? dadosHora[campoValor] : null;

            if (dadosHora && valor !== null && valor !== undefined) {
                pontos.push({
                    hora: h,
                    valor: valor,
                    validos: dadosHora.validos || 0,
                    total: dadosHora.total || 0,
                    incompleto: (dadosHora.validos || 0) < 60
                });
                if (valor > maxValor) maxValor = valor;
                if (valor < minValor) minValor = valor;
            } else {
                pontos.push({
                    hora: h,
                    valor: null,
                    validos: 0,
                    total: 0,
                    incompleto: true
                });
            }
        }

        // Se não houver dados
        if (maxValor === 0 && minValor === Infinity) {
            container.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #94a3b8;">Sem dados disponíveis para este dia</div>';
            return;
        }

        // Adicionar margem ao range
        const range = maxValor - minValor || 1;
        const marginRange = range * 0.1;
        minValor = Math.max(0, minValor - marginRange);
        maxValor = maxValor + marginRange;
        const rangeAjustado = maxValor - minValor;

        // Calcular posições
        const stepX = areaLargura / 23;

        // Função para converter valor em Y
        const valorParaY = (val) => paddingTop + areaAltura * (1 - (val - minValor) / rangeAjustado);
        const horaParaX = (h) => paddingLeft + h * stepX;

        // Gerar linhas de grade horizontais
        let gradeH = '';
        const numLinhas = 5;
        for (let i = 0; i <= numLinhas; i++) {
            const y = paddingTop + (areaAltura / numLinhas) * i;
            const valor = maxValor - (rangeAjustado / numLinhas) * i;
            gradeH += `<line x1="${paddingLeft}" y1="${y}" x2="${largura - paddingRight}" y2="${y}" class="grafico-grid"/>`;
            gradeH += `<text x="${paddingLeft - 10}" y="${y + 4}" class="grafico-value-label">${valor.toFixed(1)}</text>`;
        }

        // Gerar path da linha e área
        let pathD = '';
        let areaD = '';
        let pontosValidos = pontos.filter(p => p.valor !== null);

        if (pontosValidos.length > 0) {
            // Iniciar área
            areaD = `M ${horaParaX(pontosValidos[0].hora)} ${paddingTop + areaAltura}`;

            pontosValidos.forEach((p, i) => {
                const x = horaParaX(p.hora);
                const y = valorParaY(p.valor);
                if (i === 0) {
                    pathD += `M ${x} ${y}`;
                    areaD += ` L ${x} ${y}`;
                } else {
                    pathD += ` L ${x} ${y}`;
                    areaD += ` L ${x} ${y}`;
                }
            });

            // Fechar área
            areaD += ` L ${horaParaX(pontosValidos[pontosValidos.length - 1].hora)} ${paddingTop + areaAltura} Z`;
        }

        // Gerar círculos dos pontos
        let circulos = '';
        pontos.forEach(p => {
            if (p.valor !== null) {
                const x = horaParaX(p.hora);
                const y = valorParaY(p.valor);
                const classe = p.incompleto ? 'grafico-point incomplete' : 'grafico-point complete';
                const titulo = `${String(p.hora).padStart(2, '0')}h: ${p.valor.toFixed(2)} ${unidade} (${p.validos}/${p.total} registros)`;
                circulos += `<circle cx="${x}" cy="${y}" r="5" class="${classe}"><title>${titulo}</title></circle>`;
            }
        });

        // Gerar labels das horas
        let labels = '';
        for (let h = 0; h < 24; h++) {
            const x = horaParaX(h);
            const p = pontos[h];
            const classe = p.incompleto ? 'grafico-axis-label' : 'grafico-axis-label';
            const cor = p.incompleto ? '#ef4444' : '#64748b';
            labels += `<text x="${x}" y="${altura - 10}" class="${classe}" fill="${cor}">${String(h).padStart(2, '0')}h</text>`;
        }

        // Criar ID único para o gradiente
        const gradienteId = 'gradienteArea_' + Date.now();

        container.innerHTML = `
            <svg class="grafico-svg" viewBox="0 0 ${largura} ${altura}">
                <defs>
                    <linearGradient id="${gradienteId}" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" style="stop-color:${corGradiente};stop-opacity:0.3"/>
                        <stop offset="100%" style="stop-color:${corGradiente};stop-opacity:0.05"/>
                    </linearGradient>
                </defs>
                ${gradeH}
                <line x1="${paddingLeft}" y1="${paddingTop}" x2="${paddingLeft}" y2="${paddingTop + areaAltura}" class="grafico-grid"/>
                <path d="${areaD}" fill="url(#${gradienteId})" class="grafico-area-dynamic"/>
                <path d="${pathD}" stroke="${corGradiente}" class="grafico-line-dynamic"/>
                ${circulos}
                ${labels}
            </svg>
        `;
    }

    // Fechar modal com ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            fecharModalGrafico();
            fecharModalImportacao();
        }
    });
</script>

<!-- Biblioteca SheetJS para ler Excel -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<!-- Modal de Importação de Planilha -->
<div id="modalImportacao" class="modal-importacao" style="display: none;">
    <div class="modal-importacao-content">
        <div class="modal-importacao-header">
            <h3><ion-icon name="cloud-upload-outline"></ion-icon> Importar Planilha</h3>
            <button type="button" class="modal-close-btn" onclick="fecharModalImportacao()">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>

        <div class="modal-importacao-body">
            <!-- Aviso importante -->
            <div class="import-warning">
                <ion-icon name="information-circle-outline"></ion-icon>
                <p>A coluna <strong>PONTO MEDIÇÃO</strong> na PLANILHA de importação deve ser preenchida somente com o
                    número inteiro do código.<br>
                    <strong>Exemplo:</strong> Se o código do PONTO DE MEDIÇÃO for <code>4000-001492-M-1</code>, deve-se
                    preencher somente <code>1492</code>.<br>
                    Caso seja <code>3500-000002-M-1</code>, deve-se preencher somente <code>2</code>.
                </p>
            </div>

            <form id="formImportacao">
                <!-- Data do Evento de Medição -->
                <div class="form-group-import">
                    <label class="form-label-import" for="dataEventoMedicao">Data do Evento de Medição: <span
                            class="required">*</span></label>
                    <input type="date" id="dataEventoMedicao" name="dataEventoMedicao" class="form-input-import"
                        required value="<?= date('Y-m-d') ?>">
                </div>

                <!-- Tipo de Vazão -->
                <div class="form-group-import">
                    <label class="form-label-import">Tipo de Vazão e/ou Pressão: <span class="required">*</span></label>
                    <div class="radio-group-import">
                        <label class="radio-item-import">
                            <input type="radio" name="tipoVazao" value="1" checked>
                            <span class="radio-label-import">Estimada</span>
                        </label>
                        <label class="radio-item-import">
                            <input type="radio" name="tipoVazao" value="2">
                            <span class="radio-label-import">Macromedida</span>
                        </label>
                    </div>
                </div>

                <!-- N° OS -->
                <div class="form-group-import">
                    <label class="form-label-import" for="numOS">N° OS:</label>
                    <input type="text" id="numOS" name="numOS" class="form-input-import"
                        placeholder="Número da Ordem de Serviço">
                </div>

                <!-- Houve Ocorrência -->
                <div class="form-group-import">
                    <label class="checkbox-item-import">
                        <input type="checkbox" id="houveOcorrencia" name="houveOcorrencia" value="1">
                        <span class="checkbox-label-import">Houve ocorrência durante medição?</span>
                    </label>
                </div>

                <!-- Observação -->
                <div class="form-group-import">
                    <label class="form-label-import" for="observacao">Observação:</label>
                    <textarea id="observacao" name="observacao" class="form-textarea-import" rows="3"
                        placeholder="Observações gerais sobre a importação"></textarea>
                </div>

                <!-- Upload de arquivo -->
                <div class="form-group-import">
                    <label class="form-label-import">Arquivo da Planilha: <span class="required">*</span></label>
                    <div class="file-upload-area" id="fileUploadArea">
                        <input type="file" id="arquivoPlanilha" name="arquivoPlanilha" accept=".xls,.xlsx"
                            style="display: none;">
                        <div class="file-upload-placeholder"
                            onclick="document.getElementById('arquivoPlanilha').click()">
                            <ion-icon name="document-outline"></ion-icon>
                            <p>Clique para selecionar ou arraste o arquivo</p>
                            <span class="file-types">Formatos aceitos: .xls, .xlsx</span>
                        </div>
                        <div class="file-selected" id="fileSelected" style="display: none;">
                            <ion-icon name="document-text-outline"></ion-icon>
                            <span id="fileName"></span>
                            <button type="button" class="btn-remove-file" onclick="removerArquivo()">
                                <ion-icon name="close-circle-outline"></ion-icon>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Preview dos dados -->
                <div id="previewDados" class="preview-dados" style="display: none;">
                    <h4><ion-icon name="eye-outline"></ion-icon> Preview dos dados</h4>
                    <div id="previewContent"></div>
                </div>
            </form>

            <!-- Resultado da importação -->
            <div id="resultadoImportacao" class="resultado-importacao" style="display: none;"></div>
        </div>

        <div class="modal-importacao-footer">
            <button type="button" class="btn-cancelar-import" onclick="fecharModalImportacao()">
                <ion-icon name="close-outline"></ion-icon>
                Cancelar
            </button>
            <button type="button" class="btn-importar-submit" onclick="executarImportacao()" id="btnImportar">
                <ion-icon name="cloud-upload-outline"></ion-icon>
                Importar
            </button>
        </div>
    </div>
</div>

<style>
    /* Botão Importar */
    .btn-importar {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-importar:hover {
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }

    /* Modal de Importação */
    .modal-importacao {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(5px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        padding: 20px;
    }

    .modal-importacao-content {
        background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
        border-radius: 16px;
        width: 100%;
        max-width: 650px;
        max-height: 90vh;
        overflow: hidden;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        flex-direction: column;
    }

    .modal-importacao-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 24px;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.1) 100%);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        flex-shrink: 0;
    }

    .modal-importacao-header h3 {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
        color: #10b981;
        font-size: 18px;
    }

    .modal-close-btn {
        background: rgba(255, 255, 255, 0.1);
        border: none;
        border-radius: 8px;
        padding: 8px;
        cursor: pointer;
        color: #94a3b8;
        transition: all 0.2s;
    }

    .modal-close-btn:hover {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
    }

    .modal-importacao-body {
        padding: 24px;
        overflow-y: auto;
        flex: 1;
        min-height: 0;
    }

    /* Aviso */
    .import-warning {
        display: flex;
        gap: 12px;
        padding: 16px;
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid rgba(59, 130, 246, 0.3);
        border-radius: 10px;
        margin-bottom: 24px;
    }

    .import-warning ion-icon {
        font-size: 24px;
        color: #3b82f6;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .import-warning p {
        margin: 0;
        color: #cbd5e1;
        font-size: 13px;
        line-height: 1.6;
    }

    .import-warning code {
        background: rgba(59, 130, 246, 0.2);
        padding: 2px 6px;
        border-radius: 4px;
        color: #60a5fa;
        font-family: monospace;
    }

    /* Form Groups */
    .form-group-import {
        margin-bottom: 20px;
    }

    .form-label-import {
        display: block;
        color: #e2e8f0;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 8px;
    }

    .form-label-import .required {
        color: #ef4444;
    }

    .form-input-import,
    .form-textarea-import {
        width: 100%;
        padding: 12px 16px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        color: #e2e8f0;
        font-size: 14px;
        transition: all 0.2s;
        box-sizing: border-box;
    }

    .form-input-import:focus,
    .form-textarea-import:focus {
        outline: none;
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
    }

    .form-textarea-import {
        resize: vertical;
        min-height: 80px;
    }

    /* Radio Group */
    .radio-group-import {
        display: flex;
        gap: 20px;
    }

    .radio-item-import {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }

    .radio-item-import input[type="radio"] {
        width: 18px;
        height: 18px;
        accent-color: #10b981;
    }

    .radio-label-import {
        color: #e2e8f0;
        font-size: 14px;
    }

    /* Checkbox */
    .checkbox-item-import {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
    }

    .checkbox-item-import input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: #10b981;
    }

    .checkbox-label-import {
        color: #e2e8f0;
        font-size: 14px;
    }

    /* File Upload */
    .file-upload-area {
        border: 2px dashed rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        transition: all 0.3s;
    }

    .file-upload-area.drag-over {
        border-color: #10b981;
        background: rgba(16, 185, 129, 0.1);
    }

    .file-upload-placeholder {
        padding: 30px;
        text-align: center;
        cursor: pointer;
    }

    .file-upload-placeholder ion-icon {
        font-size: 48px;
        color: #64748b;
        margin-bottom: 12px;
    }

    .file-upload-placeholder p {
        margin: 0 0 8px 0;
        color: #94a3b8;
        font-size: 14px;
    }

    .file-upload-placeholder .file-types {
        color: #64748b;
        font-size: 12px;
    }

    .file-selected {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        background: rgba(16, 185, 129, 0.1);
        border-radius: 8px;
    }

    .file-selected ion-icon {
        font-size: 28px;
        color: #10b981;
    }

    .file-selected span {
        flex: 1;
        color: #e2e8f0;
        font-size: 14px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .btn-remove-file {
        background: none;
        border: none;
        padding: 4px;
        cursor: pointer;
        color: #ef4444;
        font-size: 24px;
        display: flex;
    }

    .btn-remove-file:hover {
        color: #dc2626;
    }

    /* Preview */
    .preview-dados {
        margin-top: 20px;
        padding: 16px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 10px;
    }

    .preview-dados h4 {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 12px 0;
        color: #94a3b8;
        font-size: 14px;
    }

    .preview-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    .preview-table th,
    .preview-table td {
        padding: 8px 10px;
        text-align: left;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .preview-table th {
        background: rgba(255, 255, 255, 0.05);
        color: #94a3b8;
        font-weight: 500;
    }

    .preview-table td {
        color: #e2e8f0;
    }

    .preview-info {
        margin-top: 10px;
        color: #64748b;
        font-size: 12px;
    }

    /* Resultado */
    .resultado-importacao {
        margin-top: 20px;
        padding: 16px;
        border-radius: 10px;
    }

    .resultado-importacao.sucesso {
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.3);
    }

    .resultado-importacao.erro {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
    }

    .resultado-importacao h4 {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 12px 0;
        font-size: 15px;
    }

    .resultado-importacao.sucesso h4 {
        color: #10b981;
    }

    .resultado-importacao.erro h4 {
        color: #ef4444;
    }

    .resultado-importacao ul {
        margin: 0;
        padding-left: 20px;
        color: #cbd5e1;
        font-size: 13px;
    }

    .resultado-importacao li {
        margin-bottom: 4px;
    }

    .resultado-importacao p {
        margin: 0;
        color: #cbd5e1;
        font-size: 13px;
    }

    /* Footer */
    .modal-importacao-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 16px 24px;
        background: rgba(0, 0, 0, 0.2);
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        flex-shrink: 0;
    }

    .btn-cancelar-import,
    .btn-importar-submit {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-cancelar-import {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #94a3b8;
    }

    .btn-cancelar-import:hover {
        background: rgba(255, 255, 255, 0.15);
        color: #e2e8f0;
    }

    .btn-importar-submit {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border: none;
        color: white;
    }

    .btn-importar-submit:hover {
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        transform: translateY(-1px);
    }

    .btn-importar-submit:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    /* Loading no botão */
    .btn-importar-submit.loading {
        pointer-events: none;
    }

    .btn-importar-submit.loading ion-icon {
        animation: spin 1s linear infinite;
    }

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
    // === Funções do Modal de Importação ===

    let dadosPlanilha = null; // Armazena dados lidos da planilha

    function abrirModalImportacao() {
        document.getElementById('modalImportacao').style.display = 'flex';
        document.getElementById('formImportacao').reset();
        document.getElementById('resultadoImportacao').style.display = 'none';
        document.getElementById('previewDados').style.display = 'none';
        document.getElementById('fileSelected').style.display = 'none';
        document.querySelector('.file-upload-placeholder').style.display = 'block';
        dadosPlanilha = null;
    }

    function fecharModalImportacao() {
        document.getElementById('modalImportacao').style.display = 'none';
    }

    // Drag and drop para upload
    const fileUploadArea = document.getElementById('fileUploadArea');
    const arquivoInput = document.getElementById('arquivoPlanilha');

    if (fileUploadArea) {
        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('drag-over');
        });

        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.classList.remove('drag-over');
        });

        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('drag-over');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileSelect(files[0]);
            }
        });
    }

    if (arquivoInput) {
        arquivoInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });
    }

    function handleFileSelect(file) {
        const allowedExtensions = ['.xls', '.xlsx'];
        const extension = '.' + file.name.split('.').pop().toLowerCase();

        if (!allowedExtensions.includes(extension)) {
            showToast('Formato inválido! Selecione um arquivo .xls ou .xlsx', 'error');
            return;
        }

        document.getElementById('fileName').textContent = file.name;
        document.querySelector('.file-upload-placeholder').style.display = 'none';
        document.getElementById('fileSelected').style.display = 'flex';

        // Ler arquivo usando SheetJS
        lerPlanilha(file);
    }

    function lerPlanilha(file) {
        const reader = new FileReader();

        reader.onload = function (e) {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array', cellDates: false }); // cellDates: false para obter números

                // Pegar primeira planilha
                const sheetName = workbook.SheetNames[0];
                const sheet = workbook.Sheets[sheetName];

                // Converter para JSON com valores brutos
                const jsonData = XLSX.utils.sheet_to_json(sheet, { header: 1, raw: true, defval: null });

                console.log('=== DEBUG PLANILHA ===');
                console.log('Primeiras linhas:', jsonData.slice(0, 5));

                if (jsonData.length < 2) {
                    showToast('Planilha vazia ou sem dados', 'error');
                    return;
                }

                // Mapear colunas
                const cabecalho = jsonData[0].map(c => (c || '').toString().toUpperCase().trim());
                console.log('Cabeçalho:', cabecalho);

                const colMap = {};
                cabecalho.forEach((nome, idx) => {
                    const nomeNorm = nome.replace(/\s+/g, ' ');

                    if (nomeNorm.includes('DATA') && !nomeNorm.includes('HORA')) {
                        colMap['DATA'] = idx;
                    } else if (nomeNorm === 'HORA') {
                        colMap['HORA'] = idx;
                    } else if (nomeNorm.includes('PONTO') || nomeNorm.includes('MEDICAO') || nomeNorm.includes('MEDIÇÃO')) {
                        colMap['PONTO_MEDICAO'] = idx;
                    } else if ((nomeNorm.includes('TEMP') && nomeNorm.includes('ÁGUA')) || (nomeNorm.includes('TEMP') && nomeNorm.includes('AGUA'))) {
                        colMap['TEMP_AGUA'] = idx;
                    } else if (nomeNorm.includes('TEMP') && nomeNorm.includes('AMB')) {
                        colMap['TEMP_AMB'] = idx;
                    } else if (nomeNorm.includes('PRESSÃO') || nomeNorm.includes('PRESSAO')) {
                        colMap['PRESSAO'] = idx;
                    } else if (nomeNorm.includes('VOLUME')) {
                        colMap['VOLUME'] = idx;
                    } else if (nomeNorm.includes('PERÍODO') || nomeNorm.includes('PERIODO')) {
                        colMap['PERIODO'] = idx;
                    }
                });

                console.log('Mapeamento de colunas:', colMap);

                // Validar colunas obrigatórias
                const colunasObrigatorias = ['DATA', 'HORA', 'PONTO_MEDICAO'];
                const colunasFaltando = colunasObrigatorias.filter(col => colMap[col] === undefined);

                if (colunasFaltando.length > 0) {
                    showToast('Colunas obrigatórias não encontradas: ' + colunasFaltando.join(', '), 'error');
                    return;
                }

                // Função para converter data do Excel (número) para string dd/mm/yyyy
                function excelDateToString(excelDate) {
                    if (excelDate === null || excelDate === undefined || excelDate === '') {
                        return null;
                    }

                    // Se já é string, retorna como está
                    if (typeof excelDate === 'string') {
                        return excelDate.trim();
                    }

                    // Se é número, converter de data do Excel
                    if (typeof excelDate === 'number') {
                        // Data base do Excel: 30/12/1899 (Windows) ou 01/01/1904 (Mac)
                        // Ajuste para o bug do Excel que considera 1900 como bissexto
                        const excelEpoch = new Date(1899, 11, 30);
                        const msPerDay = 24 * 60 * 60 * 1000;
                        const date = new Date(excelEpoch.getTime() + excelDate * msPerDay);

                        const dia = String(date.getDate()).padStart(2, '0');
                        const mes = String(date.getMonth() + 1).padStart(2, '0');
                        const ano = date.getFullYear();

                        return `${dia}/${mes}/${ano}`;
                    }

                    return String(excelDate);
                }

                // Função para converter hora do Excel (fração do dia) para string HH:mm
                function excelTimeToString(excelTime) {
                    if (excelTime === null || excelTime === undefined || excelTime === '') {
                        return null;
                    }

                    // Se já é string, retorna como está
                    if (typeof excelTime === 'string') {
                        return excelTime.trim();
                    }

                    // Se é número, converter de hora do Excel (fração do dia)
                    if (typeof excelTime === 'number') {
                        // Se for > 1, pode ser data+hora, pegar só a parte fracionária
                        let timeFraction = excelTime;
                        if (excelTime >= 1) {
                            timeFraction = excelTime % 1;
                        }

                        const totalMinutes = Math.round(timeFraction * 24 * 60);
                        const hours = Math.floor(totalMinutes / 60);
                        const minutes = totalMinutes % 60;

                        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
                    }

                    return String(excelTime);
                }

                // Processar dados
                const registros = [];
                const pontosSet = new Set();

                for (let i = 1; i < jsonData.length; i++) {
                    const linha = jsonData[i];

                    // Obter valores brutos
                    const dataRaw = linha[colMap['DATA']];
                    const horaRaw = linha[colMap['HORA']];
                    const pontoMedicao = linha[colMap['PONTO_MEDICAO']];

                    // Converter data e hora
                    const dataConvertida = excelDateToString(dataRaw);
                    const horaConvertida = excelTimeToString(horaRaw);

                    console.log(`Linha ${i + 1}: dataRaw=${dataRaw} (${typeof dataRaw}) -> ${dataConvertida}, horaRaw=${horaRaw} (${typeof horaRaw}) -> ${horaConvertida}, ponto=${pontoMedicao}`);

                    // Pular linhas vazias
                    if (!dataConvertida && !horaConvertida && !pontoMedicao) continue;

                    // Validar obrigatórios
                    if (!dataConvertida) {
                        showToast(`Linha ${i + 1}: Coluna DATA é obrigatória (valor: ${dataRaw})`, 'error');
                        return;
                    }
                    if (!horaConvertida && horaConvertida !== '0' && horaConvertida !== '00:00') {
                        showToast(`Linha ${i + 1}: Coluna HORA é obrigatória (valor: ${horaRaw})`, 'error');
                        return;
                    }
                    if (pontoMedicao === null || pontoMedicao === undefined || pontoMedicao === '') {
                        showToast(`Linha ${i + 1}: Coluna PONTO MEDIÇÃO é obrigatória`, 'error');
                        return;
                    }

                    const cdPonto = parseInt(pontoMedicao);
                    pontosSet.add(cdPonto);

                    registros.push({
                        linha: i + 1,
                        data: dataConvertida,
                        hora: horaConvertida,
                        pontoMedicao: cdPonto,
                        tempAgua: colMap['TEMP_AGUA'] !== undefined ? parseFloat(linha[colMap['TEMP_AGUA']]) || null : null,
                        tempAmb: colMap['TEMP_AMB'] !== undefined ? parseFloat(linha[colMap['TEMP_AMB']]) || null : null,
                        pressao: colMap['PRESSAO'] !== undefined ? parseFloat(linha[colMap['PRESSAO']]) || null : null,
                        volume: colMap['VOLUME'] !== undefined ? parseFloat(linha[colMap['VOLUME']]) || null : null,
                        periodo: colMap['PERIODO'] !== undefined ? parseFloat(linha[colMap['PERIODO']]) || null : null
                    });
                }

                if (registros.length === 0) {
                    showToast('Nenhum dado válido encontrado na planilha', 'error');
                    return;
                }

                console.log('Registros processados:', registros);

                dadosPlanilha = {
                    registros: registros,
                    pontos: Array.from(pontosSet),
                    colMap: colMap
                };

                // Mostrar preview
                mostrarPreview(registros, cabecalho, colMap);

                showToast(`${registros.length} registro(s) lido(s) de ${pontosSet.size} ponto(s)`, 'success');

            } catch (error) {
                console.error('Erro ao ler planilha:', error);
                showToast('Erro ao ler planilha: ' + error.message, 'error');
            }
        };

        reader.readAsArrayBuffer(file);
    }

    function mostrarPreview(registros, cabecalho, colMap) {
        const previewDiv = document.getElementById('previewDados');
        const contentDiv = document.getElementById('previewContent');

        // Mostrar até 5 registros
        const amostra = registros.slice(0, 5);

        let html = '<table class="preview-table"><thead><tr>';
        html += '<th>Data</th><th>Hora</th><th>Ponto</th>';
        if (colMap['TEMP_AGUA'] !== undefined) html += '<th>Temp Água</th>';
        if (colMap['TEMP_AMB'] !== undefined) html += '<th>Temp Amb</th>';
        if (colMap['PRESSAO'] !== undefined) html += '<th>Pressão</th>';
        if (colMap['VOLUME'] !== undefined) html += '<th>Volume</th>';
        if (colMap['PERIODO'] !== undefined) html += '<th>Período</th>';
        html += '</tr></thead><tbody>';

        amostra.forEach(reg => {
            html += '<tr>';
            html += `<td>${reg.data || ''}</td>`;
            html += `<td>${reg.hora || ''}</td>`;
            html += `<td>${reg.pontoMedicao}</td>`;
            if (colMap['TEMP_AGUA'] !== undefined) html += `<td>${reg.tempAgua ?? ''}</td>`;
            if (colMap['TEMP_AMB'] !== undefined) html += `<td>${reg.tempAmb ?? ''}</td>`;
            if (colMap['PRESSAO'] !== undefined) html += `<td>${reg.pressao ?? ''}</td>`;
            if (colMap['VOLUME'] !== undefined) html += `<td>${reg.volume ?? ''}</td>`;
            if (colMap['PERIODO'] !== undefined) html += `<td>${reg.periodo ?? ''}</td>`;
            html += '</tr>';
        });

        html += '</tbody></table>';

        if (registros.length > 5) {
            html += `<p class="preview-info">Mostrando 5 de ${registros.length} registros</p>`;
        }

        // Contar por ponto
        const porPonto = {};
        registros.forEach(r => {
            porPonto[r.pontoMedicao] = (porPonto[r.pontoMedicao] || 0) + 1;
        });

        html += '<p class="preview-info"><strong>Pontos de medição:</strong> ';
        html += Object.entries(porPonto).map(([p, c]) => `${p} (${c})`).join(', ');
        html += '</p>';

        contentDiv.innerHTML = html;
        previewDiv.style.display = 'block';
    }

    function removerArquivo() {
        arquivoInput.value = '';
        document.getElementById('fileSelected').style.display = 'none';
        document.querySelector('.file-upload-placeholder').style.display = 'block';
        document.getElementById('previewDados').style.display = 'none';
        dadosPlanilha = null;
    }

    function executarImportacao() {
        // Limpar resultado da importação anterior
        const resultadoDiv = document.getElementById('resultadoImportacao');
        resultadoDiv.style.display = 'none';
        resultadoDiv.innerHTML = '';
        resultadoDiv.className = 'resultado-importacao';

        if (!dadosPlanilha || !dadosPlanilha.registros || dadosPlanilha.registros.length === 0) {
            showToast('Selecione um arquivo válido para importar', 'error');
            return;
        }

        const dataEventoMedicao = document.getElementById('dataEventoMedicao').value;
        if (!dataEventoMedicao) {
            showToast('Informe a Data do Evento de Medição', 'error');
            document.getElementById('dataEventoMedicao').focus();
            return;
        }

        const payload = {
            registros: dadosPlanilha.registros,
            dataEventoMedicao: dataEventoMedicao,
            tipoVazao: document.querySelector('input[name="tipoVazao"]:checked').value,
            numOS: document.getElementById('numOS').value,
            houveOcorrencia: document.getElementById('houveOcorrencia').checked ? 1 : 0,
            observacao: document.getElementById('observacao').value
        };

        const btnImportar = document.getElementById('btnImportar');
        const iconOriginal = btnImportar.innerHTML;
        btnImportar.innerHTML = '<ion-icon name="sync-outline"></ion-icon> Importando...';
        btnImportar.classList.add('loading');
        btnImportar.disabled = true;

        console.log('Payload:', payload);

        fetch('bd/registroVazaoPressao/importarPlanilha.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);

                // Primeiro obter o texto da resposta
                return response.text().then(text => {
                    console.log('Response text (primeiros 500 chars):', text.substring(0, 500));

                    // Tentar fazer parse do JSON
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        // Se não for JSON válido, mostrar o erro
                        throw new Error('Resposta inválida do servidor: ' + text.substring(0, 200));
                    }
                });
            })
            .then(data => {
                console.log('Data:', data);
                const resultadoDiv = document.getElementById('resultadoImportacao');
                resultadoDiv.style.display = 'block';

                if (data.success) {
                    resultadoDiv.className = 'resultado-importacao sucesso';
                    let html = `<h4><ion-icon name="checkmark-circle-outline"></ion-icon> Importação realizada com sucesso!</h4>`;

                    if (data.resumo && data.resumo.length > 0) {
                        html += '<ul>';
                        data.resumo.forEach(item => {
                            let texto = `<strong>${item.ponto}</strong>: ${item.registros} registro(s) importado(s)`;
                            if (item.duplicados && item.duplicados > 0) {
                                texto += ` <span style="color: #f59e0b;">(${item.duplicados} já existente(s))</span>`;
                            }
                            if (item.rejeitados && item.rejeitados > 0) {
                                texto += ` <span style="color: #ef4444;">(${item.rejeitados} rejeitado(s) por erro de validação)</span>`;
                            }
                            html += `<li>${texto}</li>`;
                        });
                        html += '</ul>';
                    }

                    if (data.totalRegistros) {
                        html += `<p style="margin-top: 12px;"><strong>Total:</strong> ${data.totalRegistros} registro(s)</p>`;
                    }

                    if (data.avisos && data.avisos.length > 0) {
                        html += '<p style="margin-top: 12px; color: #f59e0b;"><strong>Avisos:</strong></p><ul>';
                        data.avisos.forEach(aviso => {
                            html += `<li style="color: #f59e0b;">${aviso}</li>`;
                        });
                        html += '</ul>';
                    }

                    // Exibir erros de validação (registros que não puderam ser importados)
                    if (data.erros && data.erros.length > 0) {
                        html += '<div style="margin-top: 16px; padding: 12px; background: rgba(239, 68, 68, 0.1); border-radius: 8px; border-left: 3px solid #ef4444;">';
                        html += '<p style="color: #dc2626; font-weight: 600; margin-bottom: 8px;"><ion-icon name="warning-outline" style="vertical-align: middle;"></ion-icon> Registros não importados:</p>';
                        html += '<ul style="color: #dc2626; font-size: 13px;">';
                        data.erros.forEach(erro => {
                            html += `<li>${erro}</li>`;
                        });
                        html += '</ul></div>';
                    }

                    resultadoDiv.innerHTML = html;
                    showToast('Importação concluída com sucesso!', 'success');

                    // Limpar formulário
                    removerArquivo();

                } else {
                    resultadoDiv.className = 'resultado-importacao erro';
                    let html = `<h4><ion-icon name="alert-circle-outline"></ion-icon> Erro na importação</h4>`;

                    if (data.erros && data.erros.length > 0) {
                        html += '<ul>';
                        data.erros.forEach(erro => {
                            html += `<li>${erro}</li>`;
                        });
                        html += '</ul>';
                    } else if (data.message) {
                        html += `<p>${data.message}</p>`;
                    }

                    resultadoDiv.innerHTML = html;
                    showToast('Erro na importação. Verifique os detalhes.', 'error');
                }
            })
            .catch(error => {
                console.error('Erro completo:', error);
                showToast('Erro ao processar importação', 'error');

                const resultadoDiv = document.getElementById('resultadoImportacao');
                resultadoDiv.style.display = 'block';
                resultadoDiv.className = 'resultado-importacao erro';
                resultadoDiv.innerHTML = `<h4><ion-icon name="alert-circle-outline"></ion-icon> Erro</h4><p>${error.message || 'Erro de comunicação com o servidor.'}</p>`;
            })
            .finally(() => {
                btnImportar.innerHTML = iconOriginal;
                btnImportar.classList.remove('loading');
                btnImportar.disabled = false;
            });
    }

    // Fechar modal clicando fora
    document.getElementById('modalImportacao')?.addEventListener('click', function (e) {
        if (e.target === this) {
            fecharModalImportacao();
        }
    });
</script>

<?php include_once 'includes/footer.inc.php'; ?>
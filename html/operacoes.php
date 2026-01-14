<?php
/**
 * SIMP - Operações
 * Visualização de dados de medição por unidade operacional
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Buscar tipos de entidade para o dropdown
$tiposEntidade = [];
try {
    $sqlTipos = "SELECT CD_CHAVE, DS_NOME, CD_ENTIDADE_TIPO_ID 
                 FROM SIMP.dbo.ENTIDADE_TIPO 
                 WHERE DT_EXC_ENTIDADE_TIPO IS NULL 
                 ORDER BY DS_NOME";
    $stmtTipos = $pdoSIMP->query($sqlTipos);
    $tiposEntidade = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tiposEntidade = [];
}

// Parâmetros recebidos via GET (para links externos)
$tipoIdGet = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$valorIdGet = isset($_GET['valor']) ? $_GET['valor'] : '';
$valorEntidadeIdGet = isset($_GET['valorEntidadeId']) ? $_GET['valorEntidadeId'] : '';
$pontoIdGet = isset($_GET['ponto']) ? $_GET['ponto'] : '';
$mesGet = isset($_GET['mes']) ? $_GET['mes'] : date('n'); // Mês atual se não informado
$anoGet = isset($_GET['ano']) ? $_GET['ano'] : date('Y'); // Ano atual se não informado

// Mapeamento de letras por tipo de medidor
$letrasTipoMedidor = [
    1 => 'M', // Macromedidor
    2 => 'E', // Estação Pitométrica
    4 => 'P', // Medidor Pressão
    6 => 'R', // Nível Reservatório
    8 => 'H'  // Hidrômetro
];
?>

<!-- Choices.js CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

<style>
    /* ============================================
       Regras globais para evitar extrapolação
       ============================================ */
    *, *::before, *::after {
        box-sizing: border-box;
    }
    
    input, textarea, select {
        max-width: 100%;
    }

    /* ============================================
       Choices.js Customização
       ============================================ */
    .choices {
        margin-bottom: 0;
        width: 100%;
    }

    .choices__inner {
        min-height: 42px;
        padding: 6px 12px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
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
        border-radius: 8px;
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
        border-radius: 6px;
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
    .operacoes-container {
        padding: 24px;
        max-width: 1800px;
        margin: 0 auto;
    }

    /* ============================================
       Page Header
       ============================================ */
    .operacoes-header {
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        border-radius: 16px;
        padding: 28px 32px;
        margin-bottom: 24px;
        color: white;
    }

    .operacoes-header-content {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .operacoes-header-icon {
        width: 56px;
        height: 56px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .operacoes-header-icon ion-icon {
        font-size: 28px;
        color: white;
    }

    .operacoes-header h1 {
        font-size: 22px;
        font-weight: 700;
        margin: 0 0 4px 0;
    }

    .operacoes-header p {
        font-size: 14px;
        opacity: 0.8;
        margin: 0;
    }

    /* ============================================
       Filters Card
       ============================================ */
    .filtros-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 20px;
    }

    .filtros-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid #f1f5f9;
    }

    .filtros-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        font-weight: 600;
        color: #334155;
    }

    .filtros-title ion-icon {
        font-size: 18px;
        color: #64748b;
    }

    .filtros-header-right {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .filtros-header-right .form-label-inline {
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        margin-right: 8px;
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
        padding: 8px 16px;
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

    /* Grid de Filtros */
    .filtros-grid {
        display: grid;
        grid-template-columns: 1.5fr 1.5fr 1fr 90px 200px;
        gap: 16px;
        align-items: end;
    }

    .filtros-grid.modo-datas {
        grid-template-columns: 1.5fr 1.5fr 1fr 150px 150px;
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
        padding: 10px 12px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
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

    input[type="date"].form-control {
        min-width: 140px;
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
        border-radius: 0 0 8px 8px;
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

    .filtros-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
    }

    .btn-filtrar {
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

    .btn-filtrar:hover {
        background: #2563eb;
    }

    .btn-filtrar ion-icon {
        font-size: 18px;
    }

    .btn-analise-ia {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-analise-ia:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
    }

    .btn-analise-ia ion-icon {
        font-size: 18px;
    }

    /* Modal Análise IA - seguindo padrão do modal de validação */
    .modal-analise-ia {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }

    .modal-analise-ia.active {
        display: flex;
    }

    .modal-analise-ia-content {
        background: white;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        width: 90%;
        max-width: 900px;
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-sizing: border-box;
    }

    .modal-analise-ia-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #1e3a5f 0%, #2d4a6f 100%);
        color: white;
    }

    .modal-analise-ia-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .modal-analise-ia-header .subtitulo {
        font-size: 12px;
        opacity: 0.8;
        margin-top: 2px;
    }

    .modal-analise-ia-header .btn-fechar {
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        font-size: 20px;
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 4px;
        line-height: 1;
    }

    .modal-analise-ia-header .btn-fechar:hover {
        background: rgba(255,255,255,0.3);
    }

    .modal-analise-ia-body {
        padding: 20px;
        overflow-y: auto;
        overflow-x: hidden;
        flex: 1;
        box-sizing: border-box;
    }

    /* Acordeões */
    .analise-loading {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        color: #64748b;
    }

    .analise-loading ion-icon {
        font-size: 48px;
        animation: spin 1s linear infinite;
        margin-bottom: 16px;
        color: #8b5cf6;
    }

    .analise-vazia {
        text-align: center;
        padding: 60px 20px;
        color: #64748b;
    }

    .analise-vazia ion-icon {
        font-size: 48px;
        margin-bottom: 16px;
        color: #cbd5e1;
    }

    /* Acordeão Tipo */
    .acordeao-tipo {
        border-bottom: 1px solid #e2e8f0;
    }

    .acordeao-tipo-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 24px;
        cursor: pointer;
        background: #f8fafc;
        transition: background 0.2s;
    }

    .acordeao-tipo-header:hover {
        background: #f1f5f9;
    }

    .acordeao-tipo-header .icone-toggle {
        font-size: 20px;
        color: #64748b;
        transition: transform 0.3s;
    }

    .acordeao-tipo.aberto .acordeao-tipo-header .icone-toggle {
        transform: rotate(90deg);
    }

    .acordeao-tipo-header .icone-tipo {
        font-size: 20px;
        color: #8b5cf6;
    }

    .acordeao-tipo-header h4 {
        margin: 0;
        font-size: 15px;
        font-weight: 600;
        color: #334155;
        flex: 1;
    }

    .acordeao-tipo-header .badge-count {
        background: #e0e7ff;
        color: #4f46e5;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
    }

    .acordeao-tipo-content {
        display: none;
        padding-left: 20px;
    }

    .acordeao-tipo.aberto .acordeao-tipo-content {
        display: block;
    }

    /* Acordeão Valor */
    .acordeao-valor {
        border-bottom: 1px solid #f1f5f9;
    }

    .acordeao-valor-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 20px;
        cursor: pointer;
        transition: background 0.2s;
    }

    .acordeao-valor-header:hover {
        background: #faf5ff;
    }

    .acordeao-valor-header .icone-toggle {
        font-size: 16px;
        color: #94a3b8;
        transition: transform 0.3s;
    }

    .acordeao-valor.aberto .acordeao-valor-header .icone-toggle {
        transform: rotate(90deg);
    }

    .acordeao-valor-header h5 {
        margin: 0;
        font-size: 13px;
        font-weight: 500;
        color: #475569;
        flex: 1;
    }

    .acordeao-valor-header .badge-count {
        background: #f3e8ff;
        color: #7c3aed;
        padding: 2px 6px;
        border-radius: 8px;
        font-size: 10px;
        font-weight: 600;
    }

    .acordeao-valor-content {
        display: none;
        padding-left: 16px;
    }

    .acordeao-valor.aberto .acordeao-valor-content {
        display: block;
    }

    /* Acordeão Ponto */
    .acordeao-ponto {
        border-bottom: 1px solid #f8fafc;
    }

    .acordeao-ponto-header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        cursor: pointer;
        transition: background 0.2s;
    }

    .acordeao-ponto-header:hover {
        background: #fefce8;
    }

    .acordeao-ponto-header .icone-toggle {
        font-size: 14px;
        color: #cbd5e1;
        transition: transform 0.3s;
    }

    .acordeao-ponto.aberto .acordeao-ponto-header .icone-toggle {
        transform: rotate(90deg);
    }

    .acordeao-ponto-header .icone-ponto {
        font-size: 14px;
        color: #f59e0b;
    }

    .acordeao-ponto-header .ponto-info {
        flex: 1;
    }

    .acordeao-ponto-header .ponto-info strong {
        font-size: 11px;
        color: #64748b;
    }

    .acordeao-ponto-header .ponto-info span {
        font-size: 12px;
        color: #334155;
        margin-left: 6px;
    }

    .acordeao-ponto-content {
        display: none;
        padding: 8px 16px 16px 40px;
    }

    .acordeao-ponto.aberto .acordeao-ponto-content {
        display: block;
    }

    .acordeao-ponto-loading {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #64748b;
        font-size: 12px;
        padding: 12px 0;
    }

    .acordeao-ponto-loading ion-icon {
        animation: spin 1s linear infinite;
    }

    /* Lista de Datas */
    .lista-datas {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .data-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        background: #f8fafc;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .data-item:hover {
        background: #e0f2fe;
    }

    .data-item.selecionada {
        background: #dbeafe;
        border-left: 3px solid #3b82f6;
    }

    .data-item .data-info {
        flex: 1;
    }

    .data-item .data-info .data-texto {
        font-size: 12px;
        font-weight: 600;
        color: #334155;
    }

    .data-item .data-info .data-resumo {
        font-size: 10px;
        color: #64748b;
        margin-top: 2px;
    }

    .data-item .status-badge {
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 9px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .data-item .status-badge.completo {
        background: #dcfce7;
        color: #16a34a;
    }

    .data-item .status-badge.incompleto {
        background: #fef3c7;
        color: #d97706;
    }

    .data-item .status-badge.critico {
        background: #fee2e2;
        color: #dc2626;
    }

    .data-item .badge-anomalia {
        background: #fef2f2;
        color: #ef4444;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 9px;
        font-weight: 600;
    }

    /* Wrapper de data com área inline */
    .data-item-wrapper {
        margin-bottom: 6px;
    }

    .data-item-wrapper .data-item {
        position: relative;
        margin-bottom: 0;
    }

    .data-item-wrapper .data-item .icone-expandir {
        font-size: 16px;
        color: #94a3b8;
        transition: transform 0.3s;
        margin-left: 8px;
    }

    .data-item-wrapper.expandido .data-item {
        background: #dbeafe;
        border-left: 3px solid #3b82f6;
        border-radius: 8px 8px 0 0;
    }

    .data-item-wrapper.expandido .data-item .icone-expandir {
        transform: rotate(180deg);
        color: #3b82f6;
    }

    /* Área de análise inline */
    .analise-area-inline {
        background: linear-gradient(135deg, #f0f9ff 0%, #faf5ff 100%);
        border: 1px solid #c7d2fe;
        border-top: none;
        border-radius: 0 0 8px 8px;
        padding: 12px;
        margin-bottom: 6px;
    }

    .analise-inline-metricas {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 10px;
    }

    .metrica-inline {
        background: white;
        padding: 6px 10px;
        border-radius: 6px;
        text-align: center;
        min-width: 60px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }

    .metrica-inline .valor {
        display: block;
        font-size: 14px;
        font-weight: 700;
        color: #334155;
    }

    .metrica-inline .label {
        font-size: 9px;
        color: #64748b;
    }

    .metrica-inline.positivo .valor { color: #16a34a; }
    .metrica-inline.negativo .valor { color: #dc2626; }
    .metrica-inline.alerta .valor { color: #d97706; }

    .analise-inline-acoes {
        margin-bottom: 10px;
    }

    .btn-detectar-inline {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 10px;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        border: none;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-detectar-inline:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(245, 158, 11, 0.4);
    }

    .btn-detectar-inline:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .btn-detectar-inline ion-icon {
        font-size: 12px;
    }

    .btn-validar-inline {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 10px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border: none;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-validar-inline:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(59, 130, 246, 0.4);
    }

    .btn-validar-inline ion-icon {
        font-size: 12px;
    }

    .analise-inline-texto {
        font-size: 12px;
        line-height: 1.5;
        color: #334155;
        background: white;
        padding: 10px;
        border-radius: 6px;
    }

    .analise-erro {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #dc2626;
        font-size: 12px;
        padding: 8px;
        background: #fef2f2;
        border-radius: 6px;
    }

    /* Anomalias inline */
    .anomalias-inline-container {
        margin-top: 10px;
    }

    .anomalias-inline-ok {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #16a34a;
        font-size: 11px;
        padding: 8px;
        background: #f0fdf4;
        border-radius: 6px;
    }

    .anomalias-inline-ok ion-icon {
        font-size: 16px;
    }

    .anomalias-inline-header {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #f59e0b;
        font-size: 11px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .anomalias-inline-header ion-icon {
        font-size: 14px;
    }

    .anomalias-inline-lista {
        display: flex;
        flex-direction: column;
        gap: 4px;
        max-height: 150px;
        overflow-y: auto;
    }

    .anomalia-inline-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 11px;
        padding: 6px 8px;
        background: #fffbeb;
        border-radius: 4px;
        border-left: 3px solid #f59e0b;
    }

    .anomalia-inline-item.critico {
        background: #fef2f2;
        border-left-color: #dc2626;
    }

    .anomalia-inline-item.erro {
        background: #fef2f2;
        border-left-color: #b91c1c;
    }

    .anomalia-inline-item .hora {
        font-weight: 700;
        color: #64748b;
        min-width: 45px;
    }

    .anomalia-inline-item .msg {
        color: #334155;
        flex: 1;
    }

    .anomalias-loading {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #64748b;
        font-size: 11px;
        padding: 8px;
    }

    .anomalias-loading ion-icon {
        animation: spin 1s linear infinite;
    }

    /* Área de Análise */
    .analise-area {
        margin-top: 12px;
        padding: 16px;
        background: linear-gradient(135deg, #faf5ff 0%, #f0f9ff 100%);
        border-radius: 10px;
        border: 1px solid #e9d5ff;
    }

    .analise-area-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        color: #7c3aed;
        font-weight: 600;
        font-size: 13px;
    }

    .analise-area-header ion-icon {
        font-size: 18px;
    }

    .analise-area-content {
        font-size: 13px;
        line-height: 1.6;
        color: #334155;
    }

    .analise-area-loading {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #7c3aed;
        font-size: 12px;
    }

    .analise-area-loading ion-icon {
        animation: spin 1s linear infinite;
    }

    .analise-metricas {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 10px;
        margin-bottom: 12px;
    }

    .metrica-item {
        background: white;
        padding: 10px;
        border-radius: 8px;
        text-align: center;
    }

    .metrica-item .valor {
        font-size: 16px;
        font-weight: 700;
        color: #334155;
    }

    .metrica-item .label {
        font-size: 10px;
        color: #64748b;
        margin-top: 2px;
    }

    .metrica-item.positivo .valor { color: #16a34a; }
    .metrica-item.negativo .valor { color: #dc2626; }
    .metrica-item.alerta .valor { color: #d97706; }

    /* Botões de ação na análise */
    .analise-acoes {
        display: flex;
        gap: 10px;
        margin: 12px 0;
        flex-wrap: wrap;
    }

    .btn-detectar-anomalias {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-detectar-anomalias:hover {
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(245, 158, 11, 0.4);
    }

    .btn-detectar-anomalias:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .btn-detectar-anomalias ion-icon {
        font-size: 14px;
    }

    /* Lista de anomalias */
    .anomalias-container {
        margin-top: 16px;
        border-top: 1px solid #e2e8f0;
        padding-top: 16px;
    }

    .anomalias-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        color: #f59e0b;
        font-weight: 600;
        font-size: 13px;
    }

    .anomalias-header ion-icon {
        font-size: 18px;
    }

    .anomalias-resumo {
        display: flex;
        gap: 16px;
        margin-bottom: 12px;
        font-size: 12px;
        color: #64748b;
    }

    .anomalias-resumo span {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .anomalias-lista {
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-height: 300px;
        overflow-y: auto;
    }

    .anomalia-hora-card {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 8px;
        padding: 10px 12px;
    }

    .anomalia-hora-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        font-weight: 600;
        font-size: 13px;
        color: #92400e;
    }

    .anomalia-hora-header .badge-count {
        background: #fde68a;
        color: #92400e;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 10px;
        margin-left: auto;
    }

    .anomalia-item {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        padding: 6px 0;
        font-size: 12px;
        border-bottom: 1px dashed #fde68a;
    }

    .anomalia-item:last-child {
        border-bottom: none;
    }

    .anomalia-item .icone {
        flex-shrink: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-size: 12px;
    }

    .anomalia-item .icone.critico {
        background: #fee2e2;
        color: #dc2626;
    }

    .anomalia-item .icone.erro {
        background: #fecaca;
        color: #b91c1c;
    }

    .anomalia-item .icone.alerta {
        background: #fef3c7;
        color: #d97706;
    }

    .anomalia-item .icone.info {
        background: #dbeafe;
        color: #2563eb;
    }

    .anomalia-item .conteudo {
        flex: 1;
    }

    .anomalia-item .mensagem {
        color: #334155;
        font-weight: 500;
    }

    .anomalia-item .sugestao {
        color: #64748b;
        font-size: 11px;
        margin-top: 2px;
    }

    .anomalias-vazio {
        text-align: center;
        padding: 20px;
        color: #16a34a;
        font-size: 13px;
    }

    .anomalias-vazio ion-icon {
        font-size: 32px;
        margin-bottom: 8px;
        display: block;
    }

    /* Navegação de Mês */
    .mes-navegacao {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .mes-navegacao .form-control {
        flex: 1;
        min-width: 120px;
    }

    .btn-nav-mes {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 42px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .btn-nav-mes:hover {
        background: #e2e8f0;
        color: #1e3a5f;
        border-color: #cbd5e1;
    }

    .btn-nav-mes:active {
        background: #cbd5e1;
    }

    .btn-nav-mes ion-icon {
        font-size: 18px;
    }

    /* ============================================
       Resultado
       ============================================ */
    .resultado-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .resultado-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }

    .resultado-header h3 {
        font-size: 16px;
        font-weight: 600;
        color: #1e3a5f;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .resultado-info {
        font-size: 13px;
        color: #64748b;
    }

    .resultado-info strong {
        color: #1e3a5f;
    }

    .tipo-medidor-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
        border: 1px solid #7dd3fc;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        color: #0369a1;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin-right: 8px;
    }

    .tipo-medidor-badge:empty {
        display: none;
    }

    /* Gráfico */
    .grafico-container {
        width: 100%;
        height: 400px;
        margin-bottom: 24px;
    }

    /* Tabela de Dados */
    .tabela-container {
        overflow-x: auto;
    }

    .tabela-dados {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .tabela-dados th {
        background: #f8fafc;
        padding: 12px 14px;
        text-align: center;
        font-weight: 600;
        color: #475569;
        border: 1px solid #e2e8f0;
        white-space: nowrap;
    }

    .tabela-dados th.header-group {
        background: #1e3a5f;
        color: white;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .tabela-dados th.header-group.entrada {
        background: #16a34a;
    }

    .tabela-dados th.header-group.saida {
        background: #dc2626;
    }

    .tabela-dados th.header-group.resultado {
        background: #1e3a5f;
        min-width: 100px;
    }

    .tabela-dados th.header-subtotal {
        background: #e2e8f0;
        font-size: 11px;
        font-weight: 700;
        min-width: 90px;
        white-space: nowrap;
    }

    .tabela-dados th.header-subtotal.entrada {
        background: #bbf7d0;
        color: #166534;
    }

    .tabela-dados th.header-subtotal.saida {
        background: #fecaca;
        color: #991b1b;
    }

    .tabela-dados th.header-ponto {
        background: #f1f5f9;
        font-size: 11px;
        max-width: 150px;
        white-space: normal;
        line-height: 1.3;
    }

    .tabela-dados th.header-ponto .icone-op {
        font-weight: bold;
        font-size: 13px;
    }

    .tabela-dados th.header-ponto .icone-op.soma {
        color: #16a34a;
    }

    .tabela-dados th.header-ponto .icone-op.subtracao {
        color: #dc2626;
    }

    .tabela-dados th.header-ponto {
        position: relative;
    }

    .btn-grafico-popup {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        padding: 0;
        margin-left: 6px;
        background: #e0f2fe;
        border: 1px solid #7dd3fc;
        border-radius: 4px;
        color: #0284c7;
        cursor: pointer;
        transition: all 0.2s;
        vertical-align: middle;
    }

    .btn-grafico-popup:hover {
        background: #0284c7;
        border-color: #0284c7;
        color: #fff;
    }

    .btn-grafico-popup ion-icon {
        font-size: 12px;
    }

    /* Popup do gráfico */
    .grafico-popup {
        display: none;
        position: fixed;
        z-index: 9999;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.25);
        padding: 16px;
        width: 520px;
        max-width: 95vw;
    }

    .grafico-popup.active {
        display: block;
    }

    .grafico-popup-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid #e2e8f0;
    }

    .grafico-popup-titulo {
        font-size: 13px;
        font-weight: 600;
        color: #1e3a5f;
    }

    .grafico-popup-codigo {
        font-size: 11px;
        color: #64748b;
        font-weight: 500;
    }

    .grafico-popup-fechar {
        background: none;
        border: none;
        font-size: 20px;
        color: #94a3b8;
        cursor: pointer;
        padding: 0;
        line-height: 1;
    }

    .grafico-popup-fechar:hover {
        color: #64748b;
    }

    .grafico-popup-container {
        height: 220px;
    }

    .grafico-popup-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 200px;
        color: #64748b;
        font-size: 13px;
    }

    /* Override z-index do toast para ficar acima do modal */
    .toast-container {
        z-index: 10001 !important;
    }

    /* Modal de Validação de Dados */
    .modal-validacao-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }

    .modal-validacao-overlay.active {
        display: flex;
    }

    .modal-validacao {
        background: white;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        width: 90%;
        max-width: 900px;
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-sizing: border-box;
    }

    .modal-validacao-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #1e3a5f 0%, #2d4a6f 100%);
        color: white;
    }

    .modal-validacao-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
    }

    .modal-validacao-header .subtitulo {
        font-size: 12px;
        opacity: 0.8;
        margin-top: 2px;
    }

    .modal-validacao-close {
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        font-size: 20px;
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 4px;
        line-height: 1;
    }

    .modal-validacao-close:hover {
        background: rgba(255,255,255,0.3);
    }

    .modal-validacao-body {
        padding: 20px;
        overflow-y: auto;
        overflow-x: hidden;
        flex: 1;
        box-sizing: border-box;
    }

    .validacao-grafico-container {
        height: 200px;
        margin-bottom: 20px;
        background: #f8fafc;
        border-radius: 8px;
        padding: 10px;
    }

    .validacao-tabela-container {
        margin-bottom: 20px;
        overflow-x: auto;
        width: 100%;
        box-sizing: border-box;
    }

    .validacao-tabela {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        table-layout: auto;
    }

    .validacao-tabela th {
        background: #f1f5f9;
        padding: 10px 8px;
        text-align: center;
        border: 1px solid #e2e8f0;
        font-weight: 600;
        color: #334155;
    }

    .validacao-tabela td {
        padding: 8px;
        text-align: center;
        border: 1px solid #e2e8f0;
    }

    .validacao-tabela tr:hover {
        background: #f8fafc;
    }

    .validacao-tabela tr.selecionada {
        background: #dbeafe;
        border-color: #3b82f6;
    }

    .validacao-tabela tr.selecionada td {
        border-color: #93c5fd;
    }

    .validacao-tabela .hora-col {
        font-weight: 600;
        background: #f8fafc;
        width: 60px;
    }

    .validacao-tabela .btn-selecionar {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 4px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 11px;
    }

    .validacao-tabela .btn-selecionar:hover {
        background: #2563eb;
    }

    .validacao-tabela .btn-selecionar.selecionado {
        background: #16a34a;
    }

    .validacao-form {
        background: #f8fafc;
        border-radius: 8px;
        padding: 16px;
        margin-top: 16px;
        box-sizing: border-box;
        overflow: hidden;
        width: 100%;
    }

    .validacao-form h4 {
        margin: 0 0 12px 0;
        font-size: 14px;
        color: #1e3a5f;
    }

    .validacao-form-row {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        width: 100%;
        box-sizing: border-box;
    }

    .validacao-form-group {
        flex: 1;
        min-width: 100px;
        max-width: 100%;
        box-sizing: border-box;
    }

    .validacao-form-group label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 4px;
    }

    .validacao-form-group input,
    .validacao-form-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 13px;
        box-sizing: border-box;
        max-width: 100%;
    }

    .validacao-form-group textarea {
        resize: vertical;
        min-height: 40px;
        max-width: 100%;
    }

    .validacao-form-group input:focus,
    .validacao-form-group textarea:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .validacao-form-group input:disabled {
        background: #e2e8f0;
        color: #64748b;
    }

    .modal-validacao-footer {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border-top: 1px solid #e2e8f0;
        background: #f8fafc;
    }
    
    .modal-validacao-footer .footer-spacer {
        flex: 1;
    }

    .modal-validacao-footer .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
    }
    
    .modal-validacao-footer .btn-sugerir {
        background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
        color: white;
    }
    
    .modal-validacao-footer .btn-sugerir:hover {
        background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
    }
    
    .modal-validacao-footer .btn-sugerir:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .modal-validacao-footer .btn-cancelar {
        background: #e2e8f0;
        color: #64748b;
    }

    .modal-validacao-footer .btn-cancelar:hover {
        background: #cbd5e1;
    }

    .modal-validacao-footer .btn-salvar {
        background: #16a34a;
        color: white;
    }

    .modal-validacao-footer .btn-salvar:hover {
        background: #15803d;
    }

    .modal-validacao-footer .btn-salvar:disabled {
        background: #9ca3af;
        cursor: not-allowed;
    }

    /* Indicador de célula clicável */
    .tabela-dados td.valor-entrada,
    .tabela-dados td.valor-saida {
        cursor: pointer;
        transition: all 0.15s;
    }

    .tabela-dados td.valor-entrada:hover,
    .tabela-dados td.valor-saida:hover {
        background: rgba(59, 130, 246, 0.1) !important;
        box-shadow: inset 0 0 0 2px #3b82f6;
    }

    .tabela-dados td.sem-dados {
        cursor: pointer;
        color: #94a3b8;
    }

    .tabela-dados td.sem-dados:hover {
        background: rgba(59, 130, 246, 0.1) !important;
        box-shadow: inset 0 0 0 2px #3b82f6;
        color: #3b82f6;
    }

    .validacao-info {
        background: #fef3c7;
        border: 1px solid #f59e0b;
        border-radius: 6px;
        padding: 10px 14px;
        margin-bottom: 16px;
        font-size: 12px;
        color: #92400e;
    }

    .validacao-info ion-icon {
        vertical-align: middle;
        margin-right: 6px;
    }

    .validacao-legenda {
        display: flex;
        gap: 20px;
        margin-bottom: 12px;
        font-size: 12px;
        color: #64748b;
    }

    .validacao-legenda .legenda-item {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .validacao-legenda .legenda-cor {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
    }

    .validacao-resumo {
        display: flex;
        gap: 20px;
        margin-bottom: 16px;
        padding: 12px 16px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .validacao-resumo .resumo-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        padding: 8px;
        background: #fff;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
    }

    .validacao-resumo .resumo-label {
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }

    .validacao-resumo .resumo-valor {
        font-size: 18px;
        font-weight: 600;
        color: #1e293b;
    }

    .validacao-resumo .resumo-item:first-child .resumo-valor {
        color: #2563eb;
    }

    .validacao-resumo .resumo-item:nth-child(2) .resumo-valor {
        color: #16a34a;
    }

    .validacao-resumo .resumo-item:last-child .resumo-valor {
        color: #dc2626;
    }

    /* Modo somente leitura para medidor de pressão */
    .validacao-tabela.somente-leitura input[type="checkbox"] {
        display: none;
    }

    .validacao-tabela.somente-leitura th:first-child label {
        justify-content: flex-start;
        padding-left: 8px;
    }

    .validacao-tabela.somente-leitura .hora-col label {
        padding-left: 8px;
    }

    .btn-acao-rapida {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        background: #e2e8f0;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 12px;
        color: #475569;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-acao-rapida:hover {
        background: #cbd5e1;
        border-color: #94a3b8;
    }

    .btn-acao-rapida ion-icon {
        font-size: 14px;
    }

    .validacao-tabela input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
        accent-color: #3b82f6;
    }

    .validacao-tabela .hora-col label {
        margin: 0;
        font-weight: 600;
    }

    .validacao-tabela tr.tratado {
        background: rgba(59, 130, 246, 0.08);
    }

    .validacao-tabela tr.tratado:hover {
        background: rgba(59, 130, 246, 0.15);
    }

    .badge-tratado {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        background: #3b82f6;
        color: #fff;
        border-radius: 50%;
        font-size: 10px;
        font-weight: bold;
        margin-left: 4px;
    }

    /* Radio buttons para formulário de validação - mesmo estilo de Mês/Datas */
    .radio-group-validacao {
        display: flex;
        gap: 4px;
        background: #f1f5f9;
        padding: 4px;
        border-radius: 10px;
        width: fit-content;
    }

    .radio-item-validacao {
        display: flex;
        align-items: center;
        cursor: pointer;
        margin: 0;
    }

    .radio-item-validacao input[type="radio"] {
        display: none;
    }

    .radio-item-validacao .radio-label {
        padding: 8px 20px;
        font-size: 13px;
        font-weight: 500;
        color: #64748b;
        border-radius: 8px;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .radio-item-validacao input[type="radio"]:checked + .radio-label {
        background: white;
        color: #1e293b;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .radio-item-validacao:hover .radio-label {
        color: #1e293b;
    }

    /* Resumo para tipo 6 (sem média, com total extravasou) */
    .validacao-resumo .resumo-item.extravasou .resumo-valor {
        color: #dc2626;
    }

    /* Painel de Análise com IA */
    .ia-panel {
        background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
        border: 1px solid #bfdbfe;
        border-radius: 12px;
        padding: 16px;
        margin-top: 16px;
        box-sizing: border-box;
        width: 100%;
        overflow: hidden;
    }

    .ia-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }

    .ia-panel-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
        color: #1e40af;
        font-size: 14px;
    }

    .ia-panel-title ion-icon {
        font-size: 20px;
    }

    /* Chat com IA no modal de validação */
    .ia-chat-container {
        margin-top: 16px;
        border-top: 1px solid #e2e8f0;
        padding-top: 16px;
        box-sizing: border-box;
        width: 100%;
    }

    .ia-chat-titulo {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 12px;
    }

    .ia-chat-titulo ion-icon {
        color: #8b5cf6;
    }

    .ia-chat-mensagens {
        max-height: 200px;
        overflow-y: auto;
        overflow-x: hidden;
        background: #f8fafc;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 12px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        box-sizing: border-box;
        width: 100%;
    }

    .ia-chat-mensagens:empty {
        display: none;
    }

    .ia-chat-msg {
        display: flex;
        gap: 8px;
        max-width: 90%;
        box-sizing: border-box;
    }

    .ia-chat-msg.user {
        align-self: flex-end;
        flex-direction: row-reverse;
    }

    .ia-chat-msg.ia {
        align-self: flex-start;
    }

    .ia-chat-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 14px;
    }

    .ia-chat-msg.user .ia-chat-avatar {
        background: #3b82f6;
        color: white;
    }

    .ia-chat-msg.ia .ia-chat-avatar {
        background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%);
        color: white;
    }

    .ia-chat-bubble {
        padding: 10px 12px;
        border-radius: 12px;
        font-size: 13px;
        line-height: 1.4;
        word-wrap: break-word;
        overflow-wrap: break-word;
        max-width: 100%;
    }

    .ia-chat-msg.user .ia-chat-bubble {
        background: #3b82f6;
        color: white;
        border-bottom-right-radius: 4px;
    }

    .ia-chat-msg.ia .ia-chat-bubble {
        background: white;
        color: #334155;
        border: 1px solid #e2e8f0;
        border-bottom-left-radius: 4px;
    }

    .ia-chat-input-container {
        display: flex;
        gap: 8px;
        width: 100%;
        box-sizing: border-box;
    }

    .ia-chat-input {
        flex: 1;
        min-width: 0;
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        outline: none;
        transition: border-color 0.2s;
        box-sizing: border-box;
    }

    .ia-chat-input:focus {
        border-color: #8b5cf6;
    }

    .ia-chat-input::placeholder {
        color: #94a3b8;
    }

    .btn-enviar-chat {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-enviar-chat:hover {
        transform: scale(1.05);
    }

    .btn-enviar-chat:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    .btn-enviar-chat ion-icon {
        font-size: 18px;
    }

    .ia-chat-typing {
        display: flex;
        gap: 4px;
        padding: 8px;
    }

    .ia-chat-typing span {
        width: 6px;
        height: 6px;
        background: #94a3b8;
        border-radius: 50%;
        animation: chatTyping 1.4s infinite ease-in-out;
    }

    .ia-chat-typing span:nth-child(2) { animation-delay: 0.2s; }
    .ia-chat-typing span:nth-child(3) { animation-delay: 0.4s; }

    @keyframes chatTyping {
        0%, 60%, 100% { transform: translateY(0); }
        30% { transform: translateY(-4px); }
    }

    .ia-chat-sugestoes {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 12px;
        width: 100%;
        box-sizing: border-box;
    }

    .ia-chat-sugestao {
        padding: 6px 10px;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        font-size: 11px;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .ia-chat-sugestao:hover {
        border-color: #8b5cf6;
        color: #8b5cf6;
        background: #faf5ff;
    }

    /* Botão sugerir valores */
    .btn-sugerir-valores {
        background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%) !important;
        color: white !important;
        border: none !important;
    }
    
    .btn-sugerir-valores:hover {
        background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%) !important;
    }

    /* Container de valores sugeridos */
    .valores-sugeridos-container {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border: 1px solid #f59e0b;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 16px;
        box-sizing: border-box;
    }
    
    .valores-sugeridos-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .valores-sugeridos-header h4 {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        color: #92400e;
        font-size: 14px;
    }
    
    .valores-sugeridos-info {
        font-size: 12px;
        color: #b45309;
        background: rgba(255,255,255,0.7);
        padding: 4px 10px;
        border-radius: 12px;
    }
    
    .valores-sugeridos-tabela-wrapper {
        background: white;
        border-radius: 8px;
        overflow-x: auto;
        margin-bottom: 12px;
    }
    
    .valores-sugeridos-tabela {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    
    .valores-sugeridos-tabela th {
        background: #fef3c7;
        padding: 10px 12px;
        text-align: center;
        border: 1px solid #fcd34d;
        font-weight: 600;
        color: #92400e;
        white-space: nowrap;
    }
    
    .valores-sugeridos-tabela td {
        padding: 8px 12px;
        text-align: center;
        border: 1px solid #fde68a;
    }
    
    .valores-sugeridos-tabela tr:hover {
        background: #fffbeb;
    }
    
    .valores-sugeridos-tabela .valor-sugerido {
        font-weight: 600;
        color: #059669;
        background: #d1fae5;
    }
    
    .valores-sugeridos-acoes {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }
    
    .btn-aplicar-sugeridos {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 20px;
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-aplicar-sugeridos:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.4);
    }
    
    .btn-aplicar-sugeridos:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
    .btn-cancelar-sugeridos {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 20px;
        background: #f1f5f9;
        color: #64748b;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-cancelar-sugeridos:hover {
        background: #e2e8f0;
        color: #475569;
    }

    /* Botões de aplicar valores IA */
    .ia-valores-aplicar {
        margin-top: 12px;
        padding: 12px;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 8px;
    }
    
    .btn-aplicar-valores-ia {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 8px 16px;
        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-aplicar-valores-ia:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(34, 197, 94, 0.4);
    }
    
    .btn-aplicar-valores-ia:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
    .btn-cancelar-valores-ia {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 8px 16px;
        background: #f1f5f9;
        color: #64748b;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-cancelar-valores-ia:hover {
        background: #fee2e2;
        border-color: #fecaca;
        color: #991b1b;
    }
    
    .btn-cancelar-valores-ia:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .tabela-dados th.header-dia {
        background: #f8fafc;
        text-align: center;
        width: 50px;
    }

    .tabela-dados td {
        padding: 10px 14px;
        border: 1px solid #e2e8f0;
        color: #334155;
        text-align: center;
    }

    .tabela-dados td.dia-col {
        text-align: center;
        font-weight: 600;
        background: #f8fafc;
        white-space: nowrap;
        width: 50px;
    }

    .tabela-dados td.valor-entrada {
        background: rgba(22, 163, 74, 0.05);
    }

    .tabela-dados td.valor-saida {
        background: rgba(220, 38, 38, 0.05);
    }

    .tabela-dados td.valor-resultado {
        background: #f8fafc;
        font-weight: 600;
        min-width: 100px;
    }

    .tabela-dados td.valor-subtotal {
        font-weight: 600;
        min-width: 90px;
    }

    .tabela-dados td.valor-subtotal.entrada {
        background: rgba(22, 163, 74, 0.08);
    }

    .tabela-dados td.valor-subtotal.entrada.positivo {
        background: rgba(22, 163, 74, 0.15);
        color: #16a34a;
    }

    .tabela-dados td.valor-subtotal.entrada.negativo {
        background: rgba(220, 38, 38, 0.1);
        color: #dc2626;
    }

    .tabela-dados td.valor-subtotal.saida {
        background: rgba(220, 38, 38, 0.08);
    }

    .tabela-dados td.valor-subtotal.saida.positivo {
        background: rgba(22, 163, 74, 0.1);
        color: #16a34a;
    }

    .tabela-dados td.valor-subtotal.saida.negativo {
        background: rgba(220, 38, 38, 0.15);
        color: #dc2626;
    }

    .tabela-dados td.valor-resultado.positivo {
        background: rgba(22, 163, 74, 0.1);
        color: #16a34a;
    }

    .tabela-dados td.valor-resultado.negativo {
        background: rgba(220, 38, 38, 0.1);
        color: #dc2626;
    }

    .tabela-dados td.incompleto {
        background: rgba(251, 146, 60, 0.25) !important;
        position: relative;
    }

    .tabela-dados td.incompleto::after {
        content: '';
        position: absolute;
        top: 3px;
        right: 3px;
        width: 6px;
        height: 6px;
        background: #f97316;
        border-radius: 50%;
    }

    .tabela-dados td.tratado {
        background: rgba(59, 130, 246, 0.2) !important;
        position: relative;
    }

    .tabela-dados td.tratado::before {
        content: '';
        position: absolute;
        top: 3px;
        right: 3px;
        width: 6px;
        height: 6px;
        background: #3b82f6;
        border-radius: 50%;
    }

    /* Nível crítico (>= 100%) para medidores de nível */
    .tabela-dados td.nivel-critico {
        background: rgba(239, 68, 68, 0.25) !important;
    }

    /* Quando tem ambos, fundo azul (tratado tem prioridade) com bolinhas lado a lado */
    .tabela-dados td.incompleto.tratado {
        background: rgba(59, 130, 246, 0.2) !important;
    }

    .tabela-dados td.incompleto.tratado::after {
        right: 12px;
    }

    .tabela-dados td.incompleto.tratado::before {
        right: 3px;
    }

    .tabela-dados tbody tr:hover td {
        background: #eff6ff;
    }

    .tabela-dados tbody tr:hover td.dia-col {
        background: #dbeafe;
    }

    .tabela-dados tbody tr:hover td.valor-resultado.positivo {
        background: rgba(22, 163, 74, 0.15);
    }

    .tabela-dados tbody tr:hover td.valor-resultado.negativo {
        background: rgba(220, 38, 38, 0.15);
    }

    .tabela-dados tbody tr:hover td.valor-subtotal.entrada {
        background: rgba(22, 163, 74, 0.2);
    }

    .tabela-dados tbody tr:hover td.valor-subtotal.saida {
        background: rgba(220, 38, 38, 0.2);
    }

    .tabela-dados .sem-dados {
        color: #94a3b8;
        font-style: italic;
    }

    /* Legenda da tabela */
    .legenda-tabela {
        margin-top: 20px;
        padding: 16px 20px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }

    .legenda-titulo {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        margin-bottom: 12px;
    }

    .legenda-titulo ion-icon {
        font-size: 18px;
        color: #64748b;
    }

    .legenda-itens {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 12px;
    }

    .legenda-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: #475569;
    }

    .legenda-cor {
        width: 20px;
        height: 20px;
        border-radius: 4px;
        border: 1px solid #e2e8f0;
    }

    .legenda-cor.incompleto {
        background: rgba(251, 146, 60, 0.25);
        position: relative;
    }

    .legenda-cor.incompleto::after {
        content: '';
        position: absolute;
        top: 2px;
        right: 2px;
        width: 5px;
        height: 5px;
        background: #f97316;
        border-radius: 50%;
    }

    .legenda-cor.tratado {
        background: rgba(59, 130, 246, 0.2);
        position: relative;
    }

    .legenda-cor.tratado::before {
        content: '';
        position: absolute;
        top: 2px;
        right: 2px;
        width: 5px;
        height: 5px;
        background: #3b82f6;
        border-radius: 50%;
    }

    .legenda-cor.nivel-critico {
        background: rgba(239, 68, 68, 0.25);
    }

    .legenda-icone {
        font-size: 16px;
        font-weight: bold;
    }

    .legenda-icone.soma {
        color: #16a34a;
    }

    .legenda-icone.subtracao {
        color: #dc2626;
    }

    .legenda-info {
        font-size: 12px;
        color: #64748b;
        line-height: 1.5;
        padding-top: 12px;
        border-top: 1px solid #e2e8f0;
    }

    .legenda-info strong {
        color: #334155;
    }

    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #64748b;
    }

    .empty-state ion-icon {
        font-size: 64px;
        color: #cbd5e1;
        margin-bottom: 16px;
    }

    .empty-state h3 {
        font-size: 18px;
        font-weight: 600;
        color: #475569;
        margin: 0 0 8px 0;
    }

    .empty-state p {
        font-size: 14px;
        margin: 0;
    }

    /* Loading */
    .loading-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .loading-overlay.active {
        display: flex;
    }

    .loading-spinner {
        width: 48px;
        height: 48px;
        border: 4px solid #e2e8f0;
        border-top-color: #1e3a5f;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Tabs para alternar entre gráfico e tabela */
    .resultado-tabs {
        display: flex;
        gap: 4px;
        background: #f1f5f9;
        padding: 4px;
        border-radius: 8px;
        margin-bottom: 20px;
        width: fit-content;
    }

    .resultado-tab {
        padding: 8px 16px;
        border: none;
        background: transparent;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .resultado-tab:hover {
        color: #1e3a5f;
    }

    .resultado-tab.active {
        background: white;
        color: #1e3a5f;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    /* Resumo */
    .resumo-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .resumo-card {
        background: #f8fafc;
        border-radius: 10px;
        padding: 16px;
        text-align: center;
    }

    .resumo-card-label {
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }

    .resumo-card-value {
        font-size: 24px;
        font-weight: 700;
        color: #1e3a5f;
    }

    .resumo-card-unit {
        font-size: 12px;
        color: #64748b;
    }

    @media (max-width: 1400px) {
        .filtros-grid {
            grid-template-columns: 1fr 1fr 1fr;
        }
        .filtros-grid.modo-datas {
            grid-template-columns: 1fr 1fr 1fr;
        }
    }

    @media (max-width: 900px) {
        .filtros-grid {
            grid-template-columns: 1fr 1fr;
        }
        .filtros-grid.modo-datas {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 768px) {
        .operacoes-container { padding: 16px; }
        .operacoes-header { padding: 20px; }
        .operacoes-header h1 { font-size: 18px; }
        .filtros-grid { grid-template-columns: 1fr; }
        .filtros-grid.modo-datas { grid-template-columns: 1fr; }
        .filtros-actions { flex-direction: column; }
        .grafico-container { height: 300px; }
        .filtros-header { flex-direction: column; align-items: flex-start; gap: 12px; }
    }
</style>

<div class="operacoes-container">
    <!-- Header -->
    <div class="operacoes-header">
        <div class="operacoes-header-content">
            <div class="operacoes-header-icon">
                <ion-icon name="stats-chart-outline"></ion-icon>
            </div>
            <div>
                <h1>Operações</h1>
                <p>Visualização de dados de medição por unidade operacional</p>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filtros-card">
        <div class="filtros-header">
            <div class="filtros-title">
                <ion-icon name="options-outline"></ion-icon>
                Filtros de Pesquisa
            </div>
            <div class="filtros-header-right">
                <span class="form-label-inline">Período:</span>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="tipoPeriodo" value="mes" checked onchange="alternarTipoPeriodo('mes')">
                        <span class="radio-label">Mês</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="tipoPeriodo" value="datas" onchange="alternarTipoPeriodo('datas')">
                        <span class="radio-label">Datas</span>
                    </label>
                </div>
                <button type="button" class="btn-clear-filters" onclick="limparFiltros()">
                    <ion-icon name="refresh-outline"></ion-icon>
                    Limpar
                </button>
            </div>
        </div>

        <!-- Grid de filtros em uma linha -->
        <div class="filtros-grid" id="filtrosGrid">
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="folder-outline"></ion-icon>
                    Tipo de Unidade Operacional
                </label>
                <select id="selectTipoEntidade" class="form-control choices-select">
                    <option value="">Selecione o tipo de unidade...</option>
                    <?php foreach ($tiposEntidade as $tipo): ?>
                    <option value="<?= $tipo['CD_CHAVE'] ?>">
                        <?= htmlspecialchars($tipo['CD_ENTIDADE_TIPO_ID'] . ' - ' . $tipo['DS_NOME']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="albums-outline"></ion-icon>
                    Unidade Operacional
                </label>
                <select id="selectValorEntidade" class="form-control choices-select-valor">
                    <option value="">Selecione o tipo de unidade primeiro</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="speedometer-outline"></ion-icon>
                    Ponto de Medição
                </label>
                <div class="autocomplete-container">
                    <input type="text" id="filtroPontoMedicaoInput" class="form-control"
                        placeholder="Digite para buscar um ponto..." autocomplete="off">
                    <input type="hidden" id="filtroPontoMedicao" value="">
                    <input type="hidden" id="valorEntidadeIdHidden" value="">
                    <div id="filtroPontoMedicaoDropdown" class="autocomplete-dropdown"></div>
                    <button type="button" id="btnLimparPonto" class="btn-limpar-autocomplete" style="display: none;"
                        title="Limpar">
                        <ion-icon name="close-circle"></ion-icon>
                    </button>
                </div>
            </div>

            <div class="form-group" id="grupoAno">
                <label class="form-label">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Ano
                </label>
                <select id="selectAno" class="form-control">
                    <?php 
                    $anoAtual = date('Y');
                    for ($ano = $anoAtual; $ano >= $anoAtual - 10; $ano--): 
                    ?>
                    <option value="<?= $ano ?>" <?= $ano == $anoGet ? 'selected' : '' ?>><?= $ano ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group" id="grupoMes">
                <label class="form-label">
                    <ion-icon name="calendar-number-outline"></ion-icon>
                    Mês
                </label>
                <div class="mes-navegacao">
                    <button type="button" class="btn-nav-mes" onclick="navegarMes(-1)" title="Mês anterior">
                        <ion-icon name="chevron-back-outline"></ion-icon>
                    </button>
                    <select id="selectMes" class="form-control">
                        <option value="">Selecione...</option>
                        <option value="1" <?= $mesGet == 1 ? 'selected' : '' ?>>Janeiro</option>
                        <option value="2" <?= $mesGet == 2 ? 'selected' : '' ?>>Fevereiro</option>
                        <option value="3" <?= $mesGet == 3 ? 'selected' : '' ?>>Março</option>
                        <option value="4" <?= $mesGet == 4 ? 'selected' : '' ?>>Abril</option>
                        <option value="5" <?= $mesGet == 5 ? 'selected' : '' ?>>Maio</option>
                        <option value="6" <?= $mesGet == 6 ? 'selected' : '' ?>>Junho</option>
                        <option value="7" <?= $mesGet == 7 ? 'selected' : '' ?>>Julho</option>
                        <option value="8" <?= $mesGet == 8 ? 'selected' : '' ?>>Agosto</option>
                        <option value="9" <?= $mesGet == 9 ? 'selected' : '' ?>>Setembro</option>
                        <option value="10" <?= $mesGet == 10 ? 'selected' : '' ?>>Outubro</option>
                        <option value="11" <?= $mesGet == 11 ? 'selected' : '' ?>>Novembro</option>
                        <option value="12" <?= $mesGet == 12 ? 'selected' : '' ?>>Dezembro</option>
                    </select>
                    <button type="button" class="btn-nav-mes" onclick="navegarMes(1)" title="Próximo mês">
                        <ion-icon name="chevron-forward-outline"></ion-icon>
                    </button>
                </div>
            </div>

            <div class="form-group" id="grupoDataInicio" style="display: none;">
                <label class="form-label">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Data Início
                </label>
                <input type="date" id="inputDataInicio" class="form-control">
            </div>

            <div class="form-group" id="grupoDataFim" style="display: none;">
                <label class="form-label">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Data Fim
                </label>
                <input type="date" id="inputDataFim" class="form-control">
            </div>
        </div>

        <div class="filtros-actions">
            <button type="button" class="btn-filtrar" id="btnFiltrar" onclick="buscarDados()">
                <ion-icon name="search-outline"></ion-icon>
                Buscar Dados
            </button>
            <button type="button" class="btn-analise-ia" id="btnAnaliseIA" onclick="abrirModalAnaliseIA()">
                <ion-icon name="analytics-outline"></ion-icon>
                Analisar Dados
            </button>
        </div>
    </div>

    <!-- Resultado -->
    <div class="resultado-card" id="resultadoCard" style="display: none;">
        <div class="resultado-header">
            <h3>
                <ion-icon name="bar-chart-outline"></ion-icon>
                <span id="resultadoTipoMedidor" class="tipo-medidor-badge"></span>
                <span id="resultadoTitulo">Resultados</span>
            </h3>
            <span class="resultado-info" id="resultadoInfo"></span>
        </div>

        <!-- Resumo -->
        <div class="resumo-cards" id="resumoCards"></div>

        <!-- Tabs -->
        <div class="resultado-tabs">
            <button type="button" class="resultado-tab" onclick="alternarTab('grafico')">
                <ion-icon name="stats-chart-outline"></ion-icon>
                Gráfico
            </button>
            <button type="button" class="resultado-tab active" onclick="alternarTab('tabela')">
                <ion-icon name="grid-outline"></ion-icon>
                Tabela
            </button>
        </div>

        <!-- Gráfico -->
        <div class="tab-content" id="tabGrafico">
            <div class="grafico-container">
                <canvas id="graficoOperacoes"></canvas>
            </div>
        </div>

        <!-- Tabela -->
        <div class="tab-content active" id="tabTabela">
            <div class="tabela-container">
                <table class="tabela-dados" id="tabelaDados">
                    <thead id="tabelaHead"></thead>
                    <tbody id="tabelaBody"></tbody>
                </table>
            </div>
            
            <!-- Legenda -->
            <div class="legenda-tabela">
                <div class="legenda-titulo">
                    <ion-icon name="information-circle-outline"></ion-icon>
                    Legenda e Regras de Cálculo
                </div>
                <div class="legenda-itens">
                    <div class="legenda-item">
                        <span class="legenda-cor incompleto"></span>
                        <span class="legenda-texto">Dia com menos de 1440 registros (dados incompletos)</span>
                    </div>
                    <div class="legenda-item">
                        <span class="legenda-cor tratado"></span>
                        <span class="legenda-texto">Dia com registros tratados manualmente</span>
                    </div>
                    <div class="legenda-item">
                        <span class="legenda-cor nivel-critico"></span>
                        <span class="legenda-texto">Nível crítico (≥ 100%)</span>
                    </div>
                    <div class="legenda-item">
                        <span class="legenda-icone soma">⊕</span>
                        <span class="legenda-texto">Operação de soma</span>
                    </div>
                    <div class="legenda-item">
                        <span class="legenda-icone subtracao">⊖</span>
                        <span class="legenda-texto">Operação de subtração</span>
                    </div>
                </div>
                <div class="legenda-info">
                    <strong>Cálculo da média diária:</strong> A média é calculada dividindo a soma dos valores por <strong>1440</strong> (quantidade de minutos em um dia), 
                    independentemente da quantidade real de registros. Isso garante que dias com menos registros apresentem valores proporcionalmente menores.
                    <br><br>
                    <strong>Medidores de nível:</strong> Para medidores de nível, é exibido o <strong>valor máximo</strong> registrado no dia. Passe o mouse sobre a célula para ver o horário em que o valor foi registrado.
                    <br><br>
                    <strong>Gráfico rápido:</strong> Passe o mouse sobre o <strong>nome da coluna</strong> (ponto de medição) para visualizar um gráfico com os valores do mês. Os pontos vermelhos mostram a média diária, e as barras verticais indicam a faixa entre mínimo e máximo.
                    <br><br>
                    <strong>Validação de dados:</strong> Clique em qualquer <strong>célula de valor</strong> para abrir o painel de validação. Você poderá visualizar os dados hora a hora e corrigir valores inconsistentes.
                </div>
            </div>
        </div>
    </div>

    <!-- Empty State -->
    <div class="resultado-card" id="emptyState">
        <div class="empty-state">
            <ion-icon name="analytics-outline"></ion-icon>
            <h3>Selecione os filtros</h3>
            <p>Escolha o tipo de unidade operacional, unidade, ano e período para visualizar os dados</p>
        </div>
    </div>
</div>

<!-- Popup do Gráfico -->
<div class="grafico-popup" id="graficoPopup">
    <div class="grafico-popup-header">
        <div>
            <div class="grafico-popup-titulo" id="graficoPopupTitulo">-</div>
            <div class="grafico-popup-codigo" id="graficoPopupCodigo">-</div>
        </div>
        <button type="button" class="grafico-popup-fechar" onclick="fecharGraficoPopup()">×</button>
    </div>
    <div class="grafico-popup-container">
        <canvas id="graficoPopupCanvas"></canvas>
    </div>
</div>

<!-- Modal de Análise IA -->
<div class="modal-analise-ia" id="modalAnaliseIA">
    <div class="modal-analise-ia-content">
        <div class="modal-analise-ia-header">
            <div>
                <h3>
                    <ion-icon name="analytics-outline"></ion-icon>
                    Análise de Dados com IA
                </h3>
                <div class="subtitulo" id="analiseIASubtitulo">-</div>
            </div>
            <button type="button" class="btn-fechar" onclick="fecharModalAnaliseIA()">×</button>
        </div>
        <div class="modal-analise-ia-body" id="analiseIABody">
            <div class="analise-loading">
                <ion-icon name="sync-outline"></ion-icon>
                <span>Carregando estrutura...</span>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Validação de Dados -->
<div class="modal-validacao-overlay" id="modalValidacao">
    <div class="modal-validacao">
        <div class="modal-validacao-header">
            <div>
                <h3 id="validacaoTitulo"><ion-icon name="checkmark-circle-outline"></ion-icon> Validação de Dados</h3>
                <div class="subtitulo" id="validacaoSubtitulo">-</div>
            </div>
            <button type="button" class="modal-validacao-close" onclick="fecharModalValidacao()">×</button>
        </div>
        <div class="modal-validacao-body">
            <div class="validacao-info">
                <ion-icon name="information-circle-outline"></ion-icon>
                <span id="validacaoInfoTexto">Marque uma ou mais horas na tabela para inserir/corrigir valores. Informe o novo valor e clique em "Validar" para aplicar.</span>
            </div>
            
            <!-- Resumo do dia -->
            <div class="validacao-resumo" id="validacaoResumo">
                <div class="resumo-item">
                    <span class="resumo-label">Mínima</span>
                    <span class="resumo-valor" id="resumoMinima">-</span>
                </div>
                <div class="resumo-item">
                    <span class="resumo-label">Média</span>
                    <span class="resumo-valor" id="resumoMedia">-</span>
                </div>
                <div class="resumo-item">
                    <span class="resumo-label">Máxima</span>
                    <span class="resumo-valor" id="resumoMaxima">-</span>
                </div>
            </div>
            
            <!-- Legenda de cores -->
            <div class="validacao-legenda">
                <span class="legenda-item"><span class="legenda-cor" style="background:#dc2626;"></span> Dados originais</span>
                <span class="legenda-item"><span class="legenda-cor" style="background:#3b82f6;"></span> Dados validados</span>
            </div>
            
            <!-- Gráfico Error Bar Chart -->
            <div class="validacao-grafico-container">
                <canvas id="validacaoGrafico"></canvas>
            </div>
            
            <!-- Botões de ação rápida -->
            <div style="display:flex;gap:10px;margin-bottom:12px;" id="validacaoAcoesRapidas">
                <button type="button" class="btn-acao-rapida" onclick="toggleTodasHoras()">
                    <ion-icon name="checkbox-outline"></ion-icon> Selecionar Todas
                </button>
                <button type="button" class="btn-acao-rapida" onclick="selecionarHorasSemDados()">
                    <ion-icon name="add-circle-outline"></ion-icon> Selecionar Horas Vazias
                </button>
            </div>
            
            <!-- Área de valores sugeridos -->
            <div class="valores-sugeridos-container" id="valoresSugeridosContainer" style="display: none;">
                <div class="valores-sugeridos-header">
                    <h4><ion-icon name="analytics-outline"></ion-icon> Valores Sugeridos</h4>
                    <span class="valores-sugeridos-info" id="valoresSugeridosInfo"></span>
                </div>
                <div class="valores-sugeridos-tabela-wrapper">
                    <table class="valores-sugeridos-tabela" id="valoresSugeridosTabela">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Valor Atual</th>
                                <th>Média Histórica</th>
                                <th>Fator</th>
                                <th>Valor Sugerido</th>
                            </tr>
                        </thead>
                        <tbody id="valoresSugeridosBody">
                        </tbody>
                    </table>
                </div>
                <div class="valores-sugeridos-acoes">
                    <button type="button" class="btn btn-aplicar-sugeridos" onclick="aplicarValoresSugeridos()">
                        <ion-icon name="checkmark-circle-outline"></ion-icon> Aplicar Valores Sugeridos
                    </button>
                    <button type="button" class="btn btn-cancelar-sugeridos" onclick="fecharValoresSugeridos()">
                        <ion-icon name="close-circle-outline"></ion-icon> Cancelar
                    </button>
                </div>
            </div>
            
            <!-- Tabela de dados horários -->
            <div class="validacao-tabela-container">
                <table class="validacao-tabela" id="validacaoTabela">
                    <thead>
                        <tr>
                            <th style="width:100px;">
                                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                    <input type="checkbox" id="checkboxTodos" onchange="toggleTodasHoras()">
                                    Hora
                                </label>
                            </th>
                            <th>Média</th>
                            <th>Mínimo</th>
                            <th>Máximo</th>
                            <th>Registros</th>
                        </tr>
                    </thead>
                    <tbody id="validacaoTabelaBody">
                        <!-- Preenchido via JS -->
                    </tbody>
                </table>
            </div>
            
            <!-- Formulário de validação padrão -->
            <div class="validacao-form" id="validacaoForm" style="display: none;">
                <h4><ion-icon name="create-outline"></ion-icon> Inserir/Corrigir valores</h4>
                <div class="validacao-form-row">
                    <div class="validacao-form-group">
                        <label>Horas Selecionadas</label>
                        <input type="text" id="validacaoHoraSelecionada" disabled>
                    </div>
                    <div class="validacao-form-group">
                        <label>Valor Atual</label>
                        <input type="text" id="validacaoValorAtual" disabled>
                    </div>
                    <div class="validacao-form-group">
                        <label>Novo Valor *</label>
                        <input type="number" id="validacaoNovoValor" step="0.01" placeholder="Digite o novo valor">
                    </div>
                </div>
                <div class="validacao-form-row" style="margin-top: 12px;">
                    <div class="validacao-form-group" style="flex: 2;">
                        <label>Observação (opcional)</label>
                        <textarea id="validacaoObservacao" rows="2" placeholder="Motivo da correção..."></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Formulário de validação para Nível Reservatório (tipo 6) -->
            <div class="validacao-form" id="validacaoFormNivel" style="display: none;">
                <h4><ion-icon name="water-outline"></ion-icon> Atualizar dados de nível</h4>
                <div class="validacao-form-row">
                    <div class="validacao-form-group">
                        <label>Horas Selecionadas</label>
                        <input type="text" id="validacaoHoraSelecionadaNivel" disabled>
                    </div>
                    <div class="validacao-form-group">
                        <label>Min >= 100 Atual</label>
                        <input type="text" id="validacaoExtravasouAtual" disabled>
                    </div>
                    <div class="validacao-form-group">
                        <label>Minutos >= 100 *</label>
                        <input type="number" id="validacaoMinutosExtravasou" min="0" max="60" step="1" placeholder="0 a 60">
                    </div>
                </div>
                <div class="validacao-form-row" style="margin-top: 12px;">
                    <div class="validacao-form-group">
                        <label>Motivo *</label>
                        <div class="radio-group-validacao">
                            <label class="radio-item-validacao">
                                <input type="radio" name="validacaoMotivo" value="1">
                                <span class="radio-label">Falha</span>
                            </label>
                            <label class="radio-item-validacao">
                                <input type="radio" name="validacaoMotivo" value="2">
                                <span class="radio-label">Extravasou</span>
                            </label>
                        </div>
                    </div>
                    <div class="validacao-form-group" style="flex: 2;">
                        <label>Observação (opcional)</label>
                        <textarea id="validacaoObservacaoNivel" rows="2" placeholder="Observação..."></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Painel de Análise com IA -->
            <div class="ia-panel" id="iaPanelValidacao">
                <div class="ia-panel-header">
                    <div class="ia-panel-title">
                        <ion-icon name="sparkles"></ion-icon>
                        Análise Inteligente
                    </div>
                </div>
                
                <!-- Chat com IA -->
                <div class="ia-chat-container">
                    <div class="ia-chat-titulo">
                        <ion-icon name="chatbubbles-outline"></ion-icon>
                        Pergunte sobre os dados
                    </div>
                    
                    <div class="ia-chat-sugestoes" id="iaChatSugestoes">
                        <button class="ia-chat-sugestao" onclick="enviarPerguntaChat('Qual a média de vazão das últimas 4 semanas do mesmo dia da semana?')">Média 4 semanas</button>
                        <button class="ia-chat-sugestao" onclick="enviarPerguntaHorasSelecionadas()">Sugerir p/ horas selecionadas</button>
                        <button class="ia-chat-sugestao" onclick="enviarPerguntaChat('Há alguma anomalia ou valor suspeito nos dados?')">Detectar anomalias</button>
                    </div>
                    
                    <div class="ia-chat-mensagens" id="iaChatMensagens"></div>
                    
                    <div class="ia-chat-input-container">
                        <input type="text" class="ia-chat-input" id="iaChatInput" 
                               placeholder="Ex: Qual a vazão média? Compare com ontem..." 
                               onkeydown="if(event.key==='Enter') enviarPerguntaChat()">
                        <button type="button" class="btn-enviar-chat" id="btnEnviarChat" onclick="enviarPerguntaChat()">
                            <ion-icon name="send"></ion-icon>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-validacao-footer">
            <button type="button" class="btn btn-sugerir" id="btnSugerirValores" onclick="buscarValoresSugeridos()">
                <ion-icon name="bulb-outline"></ion-icon> Sugerir Valores
            </button>
            <div class="footer-spacer"></div>
            <button type="button" class="btn btn-cancelar" onclick="fecharModalValidacao()">Fechar</button>
            <button type="button" class="btn btn-salvar" id="btnValidar" onclick="executarValidacao()" disabled>
                <ion-icon name="checkmark-outline"></ion-icon> Validar Dados
            </button>
        </div>
    </div>
</div>

<!-- Loading -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
<!-- Choices.js -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<script>
let grafico = null;
let choicesTipo, choicesValor;
let dadosResultado = null;
let valorEntidadeIdSelecionado = ''; // CD_ENTIDADE_VALOR_ID para buscar pontos
let valoresEntidadeMap = {}; // Mapa de CD_CHAVE -> CD_ENTIDADE_VALOR_ID
let periodoDataInicio = ''; // Data início do período selecionado
let periodoDataFim = ''; // Data fim do período selecionado

// Variáveis para modal de validação
let validacaoGrafico = null;
let validacaoDadosAtuais = null;
let validacaoHorasSelecionadas = []; // Array de horas selecionadas
let validacaoPontoAtual = null;
let validacaoDataAtual = null;
let validacaoTipoMedidorAtual = null;
let validacaoUnidadeAtual = null;

// Mapeamento de letras por tipo de medidor
const letrasTipoMedidor = <?= json_encode($letrasTipoMedidor) ?>;

// Parâmetros recebidos via GET
const paramTipo = '<?= $tipoIdGet ?>';
const paramValor = '<?= $valorIdGet ?>';
const paramValorEntidadeId = '<?= $valorEntidadeIdGet ?>';
const paramPonto = '<?= $pontoIdGet ?>';
const paramMes = '<?= $mesGet ?>';
const paramAno = '<?= $anoGet ?>';

// ============================================
// Inicialização
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    initChoices();
    initAutocompletePontoMedicao();
    
    // Se recebeu parâmetros via GET, preencher filtros e buscar dados
    if (paramTipo && paramValor) {
        setTimeout(() => {
            choicesTipo.setChoiceByValue(paramTipo);
            
            carregarValoresEntidade().then(() => {
                setTimeout(() => {
                    choicesValor.setChoiceByValue(paramValor);
                    
                    // Guardar o valorEntidadeId
                    if (paramValorEntidadeId) {
                        valorEntidadeIdSelecionado = paramValorEntidadeId;
                        document.getElementById('valorEntidadeIdHidden').value = paramValorEntidadeId;
                    }
                    
                    // Buscar dados automaticamente após um breve delay
                    setTimeout(() => {
                        buscarDados();
                    }, 300);
                }, 300);
            });
        }, 100);
    }
});

// ============================================
// Choices.js Inicialização
// ============================================
function initChoices() {
    // Tipo de Unidade Operacional
    choicesTipo = new Choices('#selectTipoEntidade', {
        searchEnabled: true,
        searchPlaceholderValue: 'Digite para buscar...',
        noResultsText: 'Nenhum resultado encontrado',
        noChoicesText: 'Nenhuma opção disponível',
        itemSelectText: '',
        placeholder: true,
        placeholderValue: 'Selecione o tipo de unidade...',
        shouldSort: false,
        searchResultLimit: 50
    });
    
    // Unidade Operacional
    choicesValor = new Choices('#selectValorEntidade', {
        searchEnabled: true,
        searchPlaceholderValue: 'Digite para buscar...',
        noResultsText: 'Nenhum resultado encontrado',
        noChoicesText: 'Selecione o tipo de unidade primeiro',
        itemSelectText: '',
        placeholder: true,
        placeholderValue: 'Selecione a unidade...',
        shouldSort: false,
        searchResultLimit: 50
    });
    
    // Event listener para carregar valores
    document.getElementById('selectTipoEntidade').addEventListener('change', function() {
        carregarValoresEntidade();
        limparPontoMedicao();
    });
    
    // Event listener para quando selecionar valor - guardar o valorEntidadeId e buscar
    const selectValorElement = document.getElementById('selectValorEntidade');
    
    selectValorElement.addEventListener('addItem', function(event) {
        const selectedValue = event.detail.value;
        
        if (selectedValue && valoresEntidadeMap[selectedValue]) {
            valorEntidadeIdSelecionado = valoresEntidadeMap[selectedValue];
            document.getElementById('valorEntidadeIdHidden').value = valorEntidadeIdSelecionado;
            // Buscar automaticamente se tiver mês e ano selecionados
            tentarBuscaAutomatica();
        } else {
            valorEntidadeIdSelecionado = '';
            document.getElementById('valorEntidadeIdHidden').value = '';
        }
        limparPontoMedicao();
    });
    
    // Também ouve o change para limpar quando necessário
    selectValorElement.addEventListener('change', function() {
        if (!this.value) {
            valorEntidadeIdSelecionado = '';
            document.getElementById('valorEntidadeIdHidden').value = '';
        }
    });
    
    // Event listeners para busca automática ao mudar mês ou ano
    document.getElementById('selectMes').addEventListener('change', function() {
        tentarBuscaAutomatica();
    });
    
    document.getElementById('selectAno').addEventListener('change', function() {
        tentarBuscaAutomatica();
    });
}

// Função para tentar busca automática se filtros estiverem preenchidos
function tentarBuscaAutomatica() {
    const tipoId = document.getElementById('selectTipoEntidade').value;
    const valorId = document.getElementById('selectValorEntidade').value;
    const pontoId = document.getElementById('filtroPontoMedicao').value;
    const tipoPeriodo = document.querySelector('input[name="tipoPeriodo"]:checked').value;
    
    if (tipoPeriodo === 'mes') {
        const mes = document.getElementById('selectMes').value;
        const ano = document.getElementById('selectAno').value;
        
        // Busca se tem (tipo + valor OU ponto) E tem mês E ano
        if (((tipoId && valorId) || pontoId) && mes && ano) {
            buscarDados();
        }
    }
}

function limparPontoMedicao() {
    document.getElementById('filtroPontoMedicaoInput').value = '';
    document.getElementById('filtroPontoMedicao').value = '';
    document.getElementById('btnLimparPonto').style.display = 'none';
}

// ============================================
// Autocomplete para Ponto de Medição
// ============================================
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
            buscarPontosMedicaoAutocomplete('');
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
            buscarPontosMedicaoAutocomplete(termo);
        }, 300);
    });
    
    // Navegação por teclado
    input.addEventListener('keydown', function(e) {
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
    
    dropdown.innerHTML = '<div class="autocomplete-loading">Buscando...</div>';
    dropdown.classList.add('active');
    
    const tipoId = document.getElementById('selectTipoEntidade').value;
    const valorId = document.getElementById('selectValorEntidade').value;
    
    let url;
    let params = new URLSearchParams({ busca: termo });
    
    // Se tem valor selecionado, filtra por CD_ENTIDADE_VALOR_ID
    if (valorEntidadeIdSelecionado) {
        params.append('valor_id', valorEntidadeIdSelecionado);
        url = `bd/operacoes/getPontosMedicaoValor.php?${params}`;
    }
    // Se tem apenas tipo selecionado, filtra por tipo
    else if (tipoId) {
        params.append('tipo_id', tipoId);
        url = `bd/operacoes/getPontosMedicaoValor.php?${params}`;
    }
    // Senão, busca todos os pontos
    else {
        url = `bd/operacoes/getPontosMedicaoSimples.php?${params}`;
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data && data.data.length > 0) {
                let html = '';
                data.data.forEach(item => {
                    const letraTipo = letrasTipoMedidor[item.ID_TIPO_MEDIDOR] || 'X';
                    const codigoPonto = (item.CD_LOCALIDADE || '000') + '-' + 
                        String(item.CD_PONTO_MEDICAO).padStart(6, '0') + '-' + 
                        letraTipo + '-' + 
                        (item.CD_UNIDADE || '00');
                    const nomePonto = item.DS_NOME;
                    const cdPonto = item.CD_PONTO_MEDICAO;
                    
                    html += `
                        <div class="autocomplete-item" 
                             data-value="${cdPonto}" 
                             data-label="${codigoPonto} - ${nomePonto}">
                            <span class="item-code">${codigoPonto}</span>
                            <span class="item-name">${nomePonto}</span>
                        </div>
                    `;
                });
                dropdown.innerHTML = html;
                
                dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
                    item.addEventListener('click', function() {
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

function alternarTipoPeriodo(tipo) {
    const grid = document.getElementById('filtrosGrid');
    const grupoAno = document.getElementById('grupoAno');
    const grupoMes = document.getElementById('grupoMes');
    const grupoDataInicio = document.getElementById('grupoDataInicio');
    const grupoDataFim = document.getElementById('grupoDataFim');
    
    if (tipo === 'mes') {
        grupoAno.style.display = 'flex';
        grupoMes.style.display = 'flex';
        grupoDataInicio.style.display = 'none';
        grupoDataFim.style.display = 'none';
        grid.classList.remove('modo-datas');
    } else {
        grupoAno.style.display = 'none';
        grupoMes.style.display = 'none';
        grupoDataInicio.style.display = 'flex';
        grupoDataFim.style.display = 'flex';
        grid.classList.add('modo-datas');
    }
}

// Função para navegar entre meses (+1 ou -1) e buscar automaticamente
function navegarMes(direcao) {
    const selectMes = document.getElementById('selectMes');
    const selectAno = document.getElementById('selectAno');
    
    // Se mês não selecionado, usar mês atual
    let mes = parseInt(selectMes.value);
    if (isNaN(mes) || mes === 0) {
        mes = new Date().getMonth() + 1;
    }
    
    let ano = parseInt(selectAno.value) || new Date().getFullYear();
    
    // Calcular novo mês/ano
    mes += direcao;
    
    // Ajustar virada de ano
    if (mes > 12) {
        mes = 1;
        ano = ano + 1;
    } else if (mes < 1) {
        mes = 12;
        ano = ano - 1;
    }
    
    // Verificar se o ano está disponível no select
    const anoOption = selectAno.querySelector(`option[value="${ano}"]`);
    if (!anoOption) {
        showToast('Ano fora do intervalo disponível', 'alerta');
        return;
    }
    
    // Atualizar valores nos selects
    selectAno.value = String(ano);
    selectMes.value = String(mes);
    
    // Buscar dados automaticamente se já tiver filtros preenchidos
    const tipoId = document.getElementById('selectTipoEntidade').value;
    const valorId = document.getElementById('selectValorEntidade').value;
    const pontoId = document.getElementById('filtroPontoMedicao').value;
    
    // Busca se tem (tipo + valor) OU se tem ponto
    if ((tipoId && valorId) || pontoId) {
        buscarDados();
    }
}

function alternarTab(tab) {
    document.querySelectorAll('.resultado-tab').forEach(btn => {
        btn.classList.toggle('active', btn.textContent.toLowerCase().includes(tab));
    });
    
    document.getElementById('tabGrafico').classList.toggle('active', tab === 'grafico');
    document.getElementById('tabTabela').classList.toggle('active', tab === 'tabela');
}

async function carregarValoresEntidade() {
    const tipoId = document.getElementById('selectTipoEntidade').value;
    
    choicesValor.clearChoices();
    choicesValor.clearStore();
    valorEntidadeIdSelecionado = '';
    valoresEntidadeMap = {};
    document.getElementById('valorEntidadeIdHidden').value = '';
    
    if (!tipoId) {
        choicesValor.setChoices([{ value: '', label: 'Selecione o tipo de unidade primeiro', selected: true }], 'value', 'label', true);
        return;
    }
    
    try {
        const response = await fetch(`bd/operacoes/getValoresEntidade.php?tipoId=${tipoId}`);
        const data = await response.json();
        
        if (data.success && data.valores.length > 0) {
            let options = [{ value: '', label: 'Selecione a unidade...', selected: true }];
            data.valores.forEach(valor => {
                valoresEntidadeMap[valor.cd] = valor.id;
                
                options.push({
                    value: valor.cd,
                    label: valor.id + ' - ' + valor.nome
                });
            });
            choicesValor.setChoices(options, 'value', 'label', true);
        }
    } catch (error) {
        console.error('Erro:', error);
    }
}

function limparFiltros() {
    choicesTipo.setChoiceByValue('');
    choicesValor.clearChoices();
    choicesValor.clearStore();
    choicesValor.setChoices([{ value: '', label: 'Selecione o tipo de unidade primeiro', selected: true }], 'value', 'label', true);
    valorEntidadeIdSelecionado = '';
    valoresEntidadeMap = {};
    document.getElementById('valorEntidadeIdHidden').value = '';
    
    // Limpar autocomplete de ponto
    limparPontoMedicao();
    
    document.getElementById('selectAno').value = new Date().getFullYear();
    document.getElementById('selectMes').value = new Date().getMonth() + 1;
    document.getElementById('inputDataInicio').value = '';
    document.getElementById('inputDataFim').value = '';
    
    // Resetar radio button para "Mês"
    document.querySelector('input[name="tipoPeriodo"][value="mes"]').checked = true;
    alternarTipoPeriodo('mes');
    
    document.getElementById('resultadoCard').style.display = 'none';
    document.getElementById('emptyState').style.display = 'block';
}

async function buscarDados() {
    const tipoId = document.getElementById('selectTipoEntidade').value;
    const valorId = document.getElementById('selectValorEntidade').value;
    const pontoId = document.getElementById('filtroPontoMedicao').value;
    const ano = document.getElementById('selectAno').value;
    const tipoPeriodo = document.querySelector('input[name="tipoPeriodo"]:checked').value;
    
    // Validações - se não tem ponto, exige tipo e valor
    if (!pontoId) {
        if (!tipoId) {
            showToast('Selecione o tipo de unidade operacional ou um ponto de medição', 'alerta');
            return;
        }
        if (!valorId) {
            showToast('Selecione a unidade operacional ou um ponto de medição', 'alerta');
            return;
        }
    }
    
    let dataInicio, dataFim;
    
    if (tipoPeriodo === 'mes') {
        if (!ano) {
            showToast('Selecione o ano', 'alerta');
            return;
        }
        const mes = document.getElementById('selectMes').value;
        if (!mes) {
            showToast('Selecione o mês', 'alerta');
            return;
        }
        dataInicio = `${ano}-${String(mes).padStart(2, '0')}-01`;
        const ultimoDia = new Date(ano, mes, 0).getDate();
        dataFim = `${ano}-${String(mes).padStart(2, '0')}-${ultimoDia}`;
    } else {
        dataInicio = document.getElementById('inputDataInicio').value;
        dataFim = document.getElementById('inputDataFim').value;
        
        if (!dataInicio || !dataFim) {
            showToast('Informe as datas de início e fim', 'alerta');
            return;
        }
        
        // Validar período máximo de 31 dias
        const dtInicio = new Date(dataInicio);
        const dtFim = new Date(dataFim);
        const diffTime = Math.abs(dtFim - dtInicio);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays > 31) {
            showToast('O período máximo permitido é de 31 dias', 'alerta');
            return;
        }
        
        if (dtInicio > dtFim) {
            showToast('A data de início deve ser anterior à data de fim', 'alerta');
            return;
        }
    }
    
    // Mostrar loading
    document.getElementById('loadingOverlay').classList.add('active');
    
    // Armazenar datas globalmente para uso na tabela
    periodoDataInicio = dataInicio;
    periodoDataFim = dataFim;
    
    try {
        // Usar o valorEntidadeIdSelecionado para buscar os dados
        const valorEntidadeId = valorEntidadeIdSelecionado || document.getElementById('valorEntidadeIdHidden').value;
        
        const params = new URLSearchParams({
            valorId: valorId || '',
            valorEntidadeId: valorEntidadeId || '',
            pontoId: pontoId || '',
            dataInicio: dataInicio,
            dataFim: dataFim
        });
        
        const response = await fetch(`bd/operacoes/getDadosOperacoes.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            dadosResultado = data;
            exibirResultados(data);
        } else {
            showToast(data.message || 'Erro ao buscar dados', 'erro');
        }
    } catch (error) {
        console.error('Erro:', error);
        showToast('Erro ao comunicar com o servidor', 'erro');
    } finally {
        document.getElementById('loadingOverlay').classList.remove('active');
    }
}

function exibirResultados(data) {
    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('resultadoCard').style.display = 'block';
    
    // Tipo de medidor
    document.getElementById('resultadoTipoMedidor').textContent = data.tiposMedidor || '';
    
    // Título
    const selectValor = document.getElementById('selectValorEntidade');
    const valorNome = selectValor.options[selectValor.selectedIndex]?.text || 'Resultados';
    document.getElementById('resultadoTitulo').textContent = valorNome;
    
    // Info
    document.getElementById('resultadoInfo').innerHTML = `<strong>${data.dados.length}</strong> registros encontrados`;
    
    // Resumo
    exibirResumo(data);
    
    // Gráfico
    exibirGrafico(data);
    
    // Tabela
    exibirTabela(data);
}

function exibirResumo(data) {
    const resumo = data.resumo || {};
    const unidade = data.unidade || '';
    
    let html = `
        <div class="resumo-card">
            <div class="resumo-card-label">Total</div>
            <div class="resumo-card-value">${formatarNumero(resumo.total || 0)}</div>
            <div class="resumo-card-unit">${unidade}</div>
        </div>
        <div class="resumo-card">
            <div class="resumo-card-label">Média</div>
            <div class="resumo-card-value">${formatarNumero(resumo.media || 0)}</div>
            <div class="resumo-card-unit">${unidade}</div>
        </div>
        <div class="resumo-card">
            <div class="resumo-card-label">Mínimo</div>
            <div class="resumo-card-value">${formatarNumero(resumo.minimo || 0)}</div>
            <div class="resumo-card-unit">${unidade}</div>
        </div>
        <div class="resumo-card">
            <div class="resumo-card-label">Máximo</div>
            <div class="resumo-card-value">${formatarNumero(resumo.maximo || 0)}</div>
            <div class="resumo-card-unit">${unidade}</div>
        </div>
    `;
    
    document.getElementById('resumoCards').innerHTML = html;
}

function exibirGrafico(data) {
    const ctx = document.getElementById('graficoOperacoes').getContext('2d');
    
    // Destruir gráfico anterior
    if (grafico) {
        grafico.destroy();
    }
    
    // Separar por fluxo (entrada/saída) e por ponto
    const pontosEntrada = {};
    const pontosSaida = {};
    
    data.dados.forEach(item => {
        const pontoKey = item.ponto_codigo || item.ponto_nome;
        const target = item.fluxo === 'Entrada' ? pontosEntrada : pontosSaida;
        
        if (!target[pontoKey]) {
            target[pontoKey] = [];
        }
        
        // Tratar a data corretamente
        let dataObj;
        if (item.data) {
            const dataStr = item.data.split('T')[0].split(' ')[0];
            dataObj = new Date(dataStr + 'T12:00:00');
        }
        
        if (dataObj && !isNaN(dataObj)) {
            target[pontoKey].push({
                x: dataObj,
                y: parseFloat(item.valor) || 0
            });
        }
    });
    
    // Cores
    const coresEntrada = ['#22c55e', '#16a34a', '#15803d', '#166534', '#14532d'];
    const coresSaida = ['#ef4444', '#dc2626', '#b91c1c', '#991b1b', '#7f1d1d'];
    
    const datasets = [];
    let idxEntrada = 0;
    let idxSaida = 0;
    
    // Datasets de entrada primeiro
    Object.keys(pontosEntrada).forEach(ponto => {
        datasets.push({
            label: `⊕ ${ponto}`,
            data: pontosEntrada[ponto].sort((a, b) => a.x - b.x),
            borderColor: coresEntrada[idxEntrada % coresEntrada.length],
            backgroundColor: coresEntrada[idxEntrada % coresEntrada.length] + '20',
            fill: false,
            tension: 0.3,
            pointRadius: 3,
            pointHoverRadius: 5
        });
        idxEntrada++;
    });
    
    // Datasets de saída
    Object.keys(pontosSaida).forEach(ponto => {
        datasets.push({
            label: `⊖ ${ponto}`,
            data: pontosSaida[ponto].sort((a, b) => a.x - b.x),
            borderColor: coresSaida[idxSaida % coresSaida.length],
            backgroundColor: coresSaida[idxSaida % coresSaida.length] + '20',
            fill: false,
            tension: 0.3,
            pointRadius: 3,
            pointHoverRadius: 5
        });
        idxSaida++;
    });
    
    grafico = new Chart(ctx, {
        type: 'line',
        data: { datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${formatarNumero(context.parsed.y)} ${data.unidade}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'day',
                        displayFormats: {
                            day: 'dd/MM'
                        }
                    },
                    title: {
                        display: true,
                        text: 'Data'
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: data.unidade || 'Valor'
                    }
                }
            }
        }
    });
}

function exibirTabela(data) {
    const thead = document.getElementById('tabelaHead');
    const tbody = document.getElementById('tabelaBody');
    
    // Gerar todos os dias do período selecionado
    const dias = gerarDiasPeriodo(periodoDataInicio, periodoDataFim);
    
    if (dias.length === 0) {
        thead.innerHTML = '<tr><th>Dia</th><th>Valor</th></tr>';
        tbody.innerHTML = '<tr><td colspan="2" class="sem-dados">Período inválido</td></tr>';
        return;
    }
    
    // Organizar dados por dia e por ponto, separando entrada e saída
    // Agora também guarda a operação de cada ponto
    const pontosEntrada = {};
    const pontosSaida = {};
    const operacoesPorPonto = {}; // { pontoKey: operacao (1=soma, 2=subtrai) }
    
    // Se há dados, organizar por ponto e dia
    if (data.dados && data.dados.length > 0) {
        data.dados.forEach(item => {
            // Extrair apenas a parte da data (YYYY-MM-DD)
            let dataStr = item.data;
            if (!dataStr) return;
            
            // Tratar diferentes formatos de data
            if (dataStr.includes('T')) {
                dataStr = dataStr.split('T')[0];
            } else if (dataStr.includes(' ')) {
                dataStr = dataStr.split(' ')[0];
            }
            
            const pontoKey = item.ponto_codigo || item.ponto_nome;
            const target = item.fluxo === 'Entrada' ? pontosEntrada : pontosSaida;
            
            // Guardar a operação do ponto
            if (item.operacao) {
                operacoesPorPonto[pontoKey] = parseInt(item.operacao);
            }
            
            if (!target[pontoKey]) {
                target[pontoKey] = {
                    cd_ponto: item.cd_ponto,
                    nome: item.ponto_nome,
                    codigo: item.ponto_codigo,
                    tipo_medidor: item.tipo_medidor,
                    operacao: item.operacao,
                    ordem: item.ordem || 999,
                    valores: {}
                };
            }
            
            // Acumular valores por dia para calcular média
            if (!target[pontoKey].valores[dataStr]) {
                target[pontoKey].valores[dataStr] = { soma: 0, count: 0, qtd_registros: 0, qtd_tratados: 0, horario_max: null, tipo_medidor: null, valor_min: null, valor_max: null, cd_ponto: item.cd_ponto };
            }
            target[pontoKey].valores[dataStr].soma += parseFloat(item.valor) || 0;
            target[pontoKey].valores[dataStr].count++;
            target[pontoKey].valores[dataStr].qtd_registros = item.qtd_registros || 0;
            target[pontoKey].valores[dataStr].qtd_tratados = item.qtd_tratados || 0;
            target[pontoKey].valores[dataStr].horario_max = item.horario_max || null;
            target[pontoKey].valores[dataStr].tipo_medidor = item.tipo_medidor || null;
            target[pontoKey].valores[dataStr].valor_min = item.valor_min !== undefined ? item.valor_min : null;
            target[pontoKey].valores[dataStr].valor_max = item.valor_max !== undefined ? item.valor_max : null;
        });
    }
    
    // Adicionar pontos que não têm dados (vieram em pontosInfo)
    if (data.pontosInfo && data.pontosInfo.length > 0) {
        data.pontosInfo.forEach(item => {
            const pontoKey = item.ponto_codigo || item.ponto_nome;
            const target = item.fluxo === 'Entrada' ? pontosEntrada : pontosSaida;
            
            // Guardar a operação do ponto
            if (item.operacao) {
                operacoesPorPonto[pontoKey] = parseInt(item.operacao);
            }
            
            if (!target[pontoKey]) {
                target[pontoKey] = {
                    cd_ponto: item.cd_ponto,
                    nome: item.ponto_nome,
                    codigo: item.ponto_codigo,
                    tipo_medidor: item.tipo_medidor,
                    operacao: item.operacao,
                    ordem: item.ordem || 999,
                    valores: {}
                };
            }
        });
    }
    
    // Ordenar por ordem definida pelo usuário (não alfabética)
    const keysEntrada = Object.keys(pontosEntrada).sort((a, b) => {
        return (pontosEntrada[a].ordem || 999) - (pontosEntrada[b].ordem || 999);
    });
    const keysSaida = Object.keys(pontosSaida).sort((a, b) => {
        return (pontosSaida[a].ordem || 999) - (pontosSaida[b].ordem || 999);
    });
    
    // Se não há pontos, mostrar mensagem
    if (keysEntrada.length === 0 && keysSaida.length === 0) {
        thead.innerHTML = '<tr><th>Dia</th><th>Valor</th></tr>';
        tbody.innerHTML = '<tr><td colspan="2" class="sem-dados">Nenhum ponto de medição encontrado</td></tr>';
        return;
    }
    
    // Construir cabeçalho
    let headerHtml = '';
    
    // Calcular colspans (pontos + coluna de subtotal)
    const colspanEntrada = keysEntrada.length > 0 ? keysEntrada.length + 1 : 0;
    const colspanSaida = keysSaida.length > 0 ? keysSaida.length + 1 : 0;
    
    // Linha superior: grupos Entrada, Saída e Resultado
    headerHtml += '<tr><th rowspan="2" class="header-dia">Dia</th>';
    if (keysEntrada.length > 0) {
        headerHtml += `<th colspan="${colspanEntrada}" class="header-group entrada">⊕ Entrada</th>`;
    }
    if (keysSaida.length > 0) {
        headerHtml += `<th colspan="${colspanSaida}" class="header-group saida">⊖ Saída</th>`;
    }
    headerHtml += '<th rowspan="2" class="header-group resultado">Resultado</th>';
    headerHtml += '</tr>';
    
    // Linha com nomes dos pontos e subtotais
    headerHtml += '<tr>';
    // Pontos de entrada
    keysEntrada.forEach(key => {
        const ponto = pontosEntrada[key];
        const op = parseInt(ponto.operacao) || 1;
        const iconeOp = op == 1 ? '<span class="icone-op soma">⊕</span>' : '<span class="icone-op subtracao">⊖</span>';
        headerHtml += `<th class="header-ponto">${iconeOp} ${ponto.codigo || ''}<br><small>${ponto.nome || ''}</small><button type="button" class="btn-grafico-popup" onclick="mostrarGraficoPopup('${key}', 'entrada')" title="Ver gráfico"><ion-icon name="stats-chart-outline"></ion-icon></button></th>`;
    });
    // Subtotal de entrada
    if (keysEntrada.length > 0) {
        headerHtml += '<th class="header-subtotal entrada">Σ Entrada</th>';
    }
    // Pontos de saída
    keysSaida.forEach(key => {
        const ponto = pontosSaida[key];
        const op = parseInt(ponto.operacao) || 1;
        const iconeOp = op == 1 ? '<span class="icone-op soma">⊕</span>' : '<span class="icone-op subtracao">⊖</span>';
        headerHtml += `<th class="header-ponto">${iconeOp} ${ponto.codigo || ''}<br><small>${ponto.nome || ''}</small><button type="button" class="btn-grafico-popup" onclick="mostrarGraficoPopup('${key}', 'saida')" title="Ver gráfico"><ion-icon name="stats-chart-outline"></ion-icon></button></th>`;
    });
    // Subtotal de saída
    if (keysSaida.length > 0) {
        headerHtml += '<th class="header-subtotal saida">Σ Saída</th>';
    }
    headerHtml += '</tr>';
    
    thead.innerHTML = headerHtml;
    
    // Guardar dados para o gráfico de hover
    window.dadosGraficoHover = {
        pontosEntrada: pontosEntrada,
        pontosSaida: pontosSaida,
        dias: dias,
        unidade: data.unidade || ''
    };
    
    // Construir corpo - mostrar TODOS os dias do período
    let bodyHtml = '';
    dias.forEach(diaStr => {
        // Extrair apenas o dia (DD) do formato YYYY-MM-DD
        const partes = diaStr.split('-');
        let diaNum = diaStr;
        if (partes.length === 3) {
            diaNum = parseInt(partes[2], 10);
        }
        
        bodyHtml += `<tr><td class="dia-col">${diaNum}</td>`;
        
        // Variáveis para calcular subtotais e resultado
        let subtotalEntrada = 0;
        let temValorEntrada = false;
        let subtotalSaida = 0;
        let temValorSaida = false;
        
        // Valores de entrada
        keysEntrada.forEach(key => {
            const ponto = pontosEntrada[key];
            const dados = ponto.valores[diaStr];
            const cdPonto = ponto.cd_ponto;
            const tipoMedidor = ponto.tipo_medidor || (dados ? dados.tipo_medidor : null);
            const pontoNome = ponto.nome || '';
            const pontoCodigo = ponto.codigo || '';
            
            if (dados && dados.count > 0) {
                const media = dados.soma / dados.count;
                // Se tipo_medidor = 6, não considera incompleto (é valor máximo)
                const incompleto = (dados.tipo_medidor != 6 && dados.qtd_registros < 1440) ? ' incompleto' : '';
                const tratado = dados.qtd_tratados > 0 ? ' tratado' : '';
                // Nível crítico: tipo_medidor = 6 e valor >= 100
                const nivelCritico = (dados.tipo_medidor == 6 && media >= 100) ? ' nivel-critico' : '';
                let tooltipParts = [];
                if (dados.tipo_medidor == 6 && dados.horario_max) {
                    tooltipParts.push(`Horário: ${dados.horario_max}`);
                }
                if (incompleto) tooltipParts.push(`Registros: ${dados.qtd_registros}/1440`);
                if (tratado) tooltipParts.push(`Tratados: ${dados.qtd_tratados}`);
                tooltipParts.push('Clique para validar');
                const titulo = ` title="${tooltipParts.join(' | ')}"`;
                const onclickAttr = cdPonto ? ` onclick="abrirModalValidacao(${cdPonto}, '${diaStr}', ${tipoMedidor || 1}, '${pontoNome.replace(/'/g, "\\'")}', '${pontoCodigo}')"` : '';
                bodyHtml += `<td class="valor-entrada${incompleto}${tratado}${nivelCritico}"${titulo}${onclickAttr}>${formatarNumero(media)}</td>`;
                
                // Aplicar operação ao subtotal de entrada
                // Se operação não definida, assume soma (1) como padrão
                const operacao = ponto.operacao || operacoesPorPonto[key] || 1;
                if (operacao == 2) {
                    subtotalEntrada -= media; // Subtrai
                } else {
                    subtotalEntrada += media; // Soma (padrão)
                }
                temValorEntrada = true;
            } else {
                const onclickAttr = cdPonto ? ` onclick="abrirModalValidacao(${cdPonto}, '${diaStr}', ${tipoMedidor || 1}, '${pontoNome.replace(/'/g, "\\'")}', '${pontoCodigo}')"` : '';
                bodyHtml += `<td class="valor-entrada sem-dados"${onclickAttr} title="Clique para inserir dados">-</td>`;
            }
        });
        
        // Coluna de subtotal de entrada
        if (keysEntrada.length > 0) {
            if (temValorEntrada) {
                const classeSubtotal = subtotalEntrada >= 0 ? 'valor-subtotal entrada positivo' : 'valor-subtotal entrada negativo';
                bodyHtml += `<td class="${classeSubtotal}">${formatarNumero(subtotalEntrada)}</td>`;
            } else {
                bodyHtml += `<td class="valor-subtotal entrada sem-dados">-</td>`;
            }
        }
        
        // Valores de saída
        keysSaida.forEach(key => {
            const ponto = pontosSaida[key];
            const dados = ponto.valores[diaStr];
            const cdPonto = ponto.cd_ponto;
            const tipoMedidor = ponto.tipo_medidor || (dados ? dados.tipo_medidor : null);
            const pontoNome = ponto.nome || '';
            const pontoCodigo = ponto.codigo || '';
            
            if (dados && dados.count > 0) {
                const media = dados.soma / dados.count;
                // Se tipo_medidor = 6, não considera incompleto (é valor máximo)
                const incompleto = (dados.tipo_medidor != 6 && dados.qtd_registros < 1440) ? ' incompleto' : '';
                const tratado = dados.qtd_tratados > 0 ? ' tratado' : '';
                // Nível crítico: tipo_medidor = 6 e valor >= 100
                const nivelCritico = (dados.tipo_medidor == 6 && media >= 100) ? ' nivel-critico' : '';
                let tooltipParts = [];
                if (dados.tipo_medidor == 6 && dados.horario_max) {
                    tooltipParts.push(`Horário: ${dados.horario_max}`);
                }
                if (incompleto) tooltipParts.push(`Registros: ${dados.qtd_registros}/1440`);
                if (tratado) tooltipParts.push(`Tratados: ${dados.qtd_tratados}`);
                tooltipParts.push('Clique para validar');
                const titulo = ` title="${tooltipParts.join(' | ')}"`;
                const onclickAttr = cdPonto ? ` onclick="abrirModalValidacao(${cdPonto}, '${diaStr}', ${tipoMedidor || 1}, '${pontoNome.replace(/'/g, "\\'")}', '${pontoCodigo}')"` : '';
                bodyHtml += `<td class="valor-saida${incompleto}${tratado}${nivelCritico}"${titulo}${onclickAttr}>${formatarNumero(media)}</td>`;
                
                // Aplicar operação ao subtotal de saída
                // Se operação não definida, assume soma (1) como padrão
                const operacao = ponto.operacao || operacoesPorPonto[key] || 1;
                if (operacao == 2) {
                    subtotalSaida -= media; // Subtrai
                } else {
                    subtotalSaida += media; // Soma (padrão)
                }
                temValorSaida = true;
            } else {
                const onclickAttr = cdPonto ? ` onclick="abrirModalValidacao(${cdPonto}, '${diaStr}', ${tipoMedidor || 1}, '${pontoNome.replace(/'/g, "\\'")}', '${pontoCodigo}')"` : '';
                bodyHtml += `<td class="valor-saida sem-dados"${onclickAttr} title="Clique para inserir dados">-</td>`;
            }
        });
        
        // Coluna de subtotal de saída
        if (keysSaida.length > 0) {
            if (temValorSaida) {
                const classeSubtotal = subtotalSaida >= 0 ? 'valor-subtotal saida positivo' : 'valor-subtotal saida negativo';
                bodyHtml += `<td class="${classeSubtotal}">${formatarNumero(subtotalSaida)}</td>`;
            } else {
                bodyHtml += `<td class="valor-subtotal saida sem-dados">-</td>`;
            }
        }
        
        // Coluna de resultado final (subtotal entrada + subtotal saída)
        // O subtotal de entrada já está calculado com operações, o de saída também
        // O resultado é a soma dos dois subtotais
        const temValorNoDia = temValorEntrada || temValorSaida;
        if (temValorNoDia) {
            const resultadoDia = subtotalEntrada + subtotalSaida;
            const classeResultado = resultadoDia >= 0 ? 'valor-resultado positivo' : 'valor-resultado negativo';
            bodyHtml += `<td class="${classeResultado}">${formatarNumero(resultadoDia)}</td>`;
        } else {
            bodyHtml += `<td class="valor-resultado sem-dados">-</td>`;
        }
        
        bodyHtml += '</tr>';
    });
    
    tbody.innerHTML = bodyHtml;
}

// Função auxiliar para gerar todos os dias entre duas datas
function gerarDiasPeriodo(dataInicio, dataFim) {
    const dias = [];
    
    if (!dataInicio || !dataFim) return dias;
    
    const inicio = new Date(dataInicio + 'T12:00:00');
    const fim = new Date(dataFim + 'T12:00:00');
    
    if (isNaN(inicio) || isNaN(fim) || inicio > fim) return dias;
    
    const atual = new Date(inicio);
    while (atual <= fim) {
        const ano = atual.getFullYear();
        const mes = String(atual.getMonth() + 1).padStart(2, '0');
        const dia = String(atual.getDate()).padStart(2, '0');
        dias.push(`${ano}-${mes}-${dia}`);
        atual.setDate(atual.getDate() + 1);
    }
    
    return dias;
}

// ============================================
// Popup com Gráfico do Ponto
// ============================================
let graficoPopupInstance = null;

function mostrarGraficoPopup(pontoKey, fluxo) {
    const dados = window.dadosGraficoHover;
    if (!dados) return;
    
    const pontos = fluxo === 'entrada' ? dados.pontosEntrada : dados.pontosSaida;
    const ponto = pontos[pontoKey];
    if (!ponto) return;
    
    // Atualizar títulos
    document.getElementById('graficoPopupTitulo').textContent = ponto.nome || '-';
    document.getElementById('graficoPopupCodigo').textContent = ponto.codigo || '-';
    
    // Posicionar popup no centro da tela
    const popup = document.getElementById('graficoPopup');
    const popupWidth = 520;
    const popupHeight = 300;
    
    const left = (window.innerWidth - popupWidth) / 2;
    const top = (window.innerHeight - popupHeight) / 2;
    
    popup.style.left = left + 'px';
    popup.style.top = top + 'px';
    popup.classList.add('active');
    
    // Gerar gráfico
    gerarGraficoPopup(ponto, dados.dias, dados.unidade);
}

function gerarGraficoPopup(ponto, dias, unidade) {
    const ctx = document.getElementById('graficoPopupCanvas').getContext('2d');
    
    // Destruir gráfico anterior
    if (graficoPopupInstance) {
        graficoPopupInstance.destroy();
    }
    
    // Preparar dados
    const labels = [];
    const valoresMedia = [];
    const valoresMin = [];
    const valoresMax = [];
    
    dias.forEach(diaStr => {
        const partes = diaStr.split('-');
        const diaNum = partes.length === 3 ? parseInt(partes[2], 10) : diaStr;
        labels.push(diaNum);
        
        const dadosDia = ponto.valores[diaStr];
        if (dadosDia && dadosDia.count > 0) {
            const media = dadosDia.soma / dadosDia.count;
            valoresMedia.push(media);
            valoresMin.push(dadosDia.valor_min !== null ? dadosDia.valor_min : media);
            valoresMax.push(dadosDia.valor_max !== null ? dadosDia.valor_max : media);
        } else {
            valoresMedia.push(null);
            valoresMin.push(null);
            valoresMax.push(null);
        }
    });
    
    // Plugin para desenhar as error bars
    const errorBarsPlugin = {
        id: 'errorBars',
        afterDatasetsDraw: function(chart) {
            const ctx = chart.ctx;
            const meta = chart.getDatasetMeta(0);
            
            ctx.save();
            ctx.strokeStyle = '#1e3a5f';
            ctx.lineWidth = 2;
            
            meta.data.forEach((point, index) => {
                if (valoresMin[index] === null || valoresMax[index] === null) return;
                
                const x = point.x;
                const yMin = chart.scales.y.getPixelForValue(valoresMin[index]);
                const yMax = chart.scales.y.getPixelForValue(valoresMax[index]);
                const capWidth = 6;
                
                // Linha vertical (min-max)
                ctx.beginPath();
                ctx.moveTo(x, yMin);
                ctx.lineTo(x, yMax);
                ctx.stroke();
                
                // Cap superior (max)
                ctx.beginPath();
                ctx.moveTo(x - capWidth, yMax);
                ctx.lineTo(x + capWidth, yMax);
                ctx.stroke();
                
                // Cap inferior (min)
                ctx.beginPath();
                ctx.moveTo(x - capWidth, yMin);
                ctx.lineTo(x + capWidth, yMin);
                ctx.stroke();
            });
            
            ctx.restore();
        }
    };
    
    graficoPopupInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Média',
                data: valoresMedia,
                borderColor: '#dc2626',
                backgroundColor: '#dc2626',
                borderWidth: 2,
                tension: 0,
                pointRadius: 5,
                pointBackgroundColor: '#dc2626',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverRadius: 7,
                spanGaps: true,
                fill: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        padding: 8,
                        font: { size: 10 },
                        generateLabels: function(chart) {
                            return [
                                {
                                    text: 'Média',
                                    fillStyle: '#dc2626',
                                    strokeStyle: '#dc2626',
                                    lineWidth: 2,
                                    hidden: false,
                                    index: 0
                                },
                                {
                                    text: 'Mín/Máx',
                                    fillStyle: '#1e3a5f',
                                    strokeStyle: '#1e3a5f',
                                    lineWidth: 2,
                                    hidden: false,
                                    index: 1
                                }
                            ];
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const idx = context.dataIndex;
                            let lines = [];
                            lines.push('Média: ' + formatarNumero(context.raw) + ' ' + unidade);
                            if (valoresMin[idx] !== null && valoresMax[idx] !== null) {
                                lines.push('Mín: ' + formatarNumero(valoresMin[idx]) + ' ' + unidade);
                                lines.push('Máx: ' + formatarNumero(valoresMax[idx]) + ' ' + unidade);
                            }
                            return lines;
                        }
                    }
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Dia',
                        font: { size: 10 }
                    },
                    ticks: {
                        font: { size: 9 }
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: unidade,
                        font: { size: 10 }
                    },
                    ticks: {
                        font: { size: 9 },
                        callback: function(value) {
                            return formatarNumero(value);
                        }
                    },
                    beginAtZero: false
                }
            }
        },
        plugins: [errorBarsPlugin]
    });
}

function fecharGraficoPopup() {
    document.getElementById('graficoPopup').classList.remove('active');
}

// Fechar popup ao clicar fora
document.addEventListener('click', function(e) {
    const popup = document.getElementById('graficoPopup');
    const isButton = e.target.closest('.btn-grafico-popup');
    if (popup && !popup.contains(e.target) && !isButton) {
        fecharGraficoPopup();
    }
});

// Fechar popup ao pressionar ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharGraficoPopup();
        fecharModalValidacao();
    }
});

// ============================================
// Funções de Validação de Dados
// ============================================

function abrirModalValidacao(cdPonto, data, tipoMedidor, pontoNome, pontoCodigo) {
    validacaoPontoAtual = cdPonto;
    validacaoDataAtual = data;
    validacaoTipoMedidorAtual = tipoMedidor;
    validacaoHorasSelecionadas = []; // Reset array de horas
    
    // Fechar área de valores sugeridos (se estiver aberta)
    const valoresSugeridosContainer = document.getElementById('valoresSugeridosContainer');
    if (valoresSugeridosContainer) valoresSugeridosContainer.style.display = 'none';
    valoresSugeridosAtual = [];
    
    // Limpar dados da IA para recarregar
    dadosCompletosIA = null;
    
    // Formatar data para exibição
    const dataFormatada = data.split('-').reverse().join('/');
    const subtituloEl = document.getElementById('validacaoSubtitulo');
    if (subtituloEl) {
        subtituloEl.textContent = `${pontoCodigo} - ${pontoNome} | ${dataFormatada}`;
    }
    
    // Verificar se é medidor de pressão (tipo 4) - apenas visualização
    const apenasVisualizacao = tipoMedidor === 4;
    const isTipoNivel = tipoMedidor === 6;
    
    // Mostrar/ocultar elementos de edição
    const acoesRapidas = document.getElementById('validacaoAcoesRapidas');
    if (acoesRapidas) acoesRapidas.style.display = apenasVisualizacao ? 'none' : 'flex';
    
    const validacaoForm = document.getElementById('validacaoForm');
    if (validacaoForm) validacaoForm.style.display = 'none';
    
    const validacaoFormNivel = document.getElementById('validacaoFormNivel');
    if (validacaoFormNivel) validacaoFormNivel.style.display = 'none';
    
    const btnValidar = document.getElementById('btnValidar');
    if (btnValidar) btnValidar.style.display = apenasVisualizacao ? 'none' : 'inline-flex';
    
    const btnSugerirValores = document.getElementById('btnSugerirValores');
    if (btnSugerirValores) btnSugerirValores.style.display = apenasVisualizacao ? 'none' : 'inline-flex';
    
    // Ajustar header da tabela para modo visualização
    const headerHora = document.querySelector('#validacaoTabela thead th:first-child');
    if (headerHora) {
        if (apenasVisualizacao) {
            headerHora.innerHTML = 'Hora';
            headerHora.style.paddingLeft = '12px';
        } else {
            headerHora.innerHTML = `<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                <input type="checkbox" id="checkboxTodos" onchange="toggleTodasHoras()">
                Hora
            </label>`;
        }
    }
    
    // Atualizar título do modal
    const tituloEl = document.getElementById('validacaoTitulo');
    if (tituloEl) {
        tituloEl.innerHTML = apenasVisualizacao 
            ? '<ion-icon name="eye-outline"></ion-icon> Visualização de Dados'
            : '<ion-icon name="checkmark-circle-outline"></ion-icon> Validação de Dados';
    }
    
    // Atualizar mensagem de info
    let infoTexto = 'Marque uma ou mais horas na tabela para inserir/corrigir valores. Informe o novo valor e clique em "Validar" para aplicar.';
    if (apenasVisualizacao) {
        infoTexto = 'Visualização dos dados hora a hora. Medidores de pressão não permitem validação manual.';
    } else if (isTipoNivel) {
        infoTexto = 'Marque uma ou mais horas para registrar extravasamento. Informe a quantidade de minutos >= 100% e o motivo (Falha ou Extravasou).';
    }
    const infoEl = document.getElementById('validacaoInfoTexto');
    if (infoEl) infoEl.textContent = infoTexto;
    
    // Mostrar/ocultar checkboxes na tabela
    const tabelaValidacao = document.getElementById('validacaoTabela');
    if (tabelaValidacao) tabelaValidacao.classList.toggle('somente-leitura', apenasVisualizacao);
    
    // Limpar formulários
    const novoValorEl = document.getElementById('validacaoNovoValor');
    if (novoValorEl) novoValorEl.value = '';
    
    const observacaoEl = document.getElementById('validacaoObservacao');
    if (observacaoEl) observacaoEl.value = '';
    
    const minutosExtravasouEl = document.getElementById('validacaoMinutosExtravasou');
    if (minutosExtravasouEl) minutosExtravasouEl.value = '';
    
    const observacaoNivelEl = document.getElementById('validacaoObservacaoNivel');
    if (observacaoNivelEl) observacaoNivelEl.value = '';
    
    document.querySelectorAll('input[name="validacaoMotivo"]').forEach(r => r.checked = false);
    
    if (btnValidar) btnValidar.disabled = true;
    
    // Limpar chat da IA
    limparChatIA();
    
    // Mostrar/ocultar painel de IA (oculto para tipo 4 - apenas visualização)
    const iaPanel = document.getElementById('iaPanelValidacao');
    if (iaPanel) iaPanel.style.display = apenasVisualizacao ? 'none' : 'block';
    
    // Mostrar modal
    const modal = document.getElementById('modalValidacao');
    if (modal) modal.classList.add('active');
    
    // Carregar dados horários
    carregarDadosHorarios(cdPonto, data, tipoMedidor);
}

function fecharModalValidacao() {
    document.getElementById('modalValidacao').classList.remove('active');
    
    // Destruir gráfico
    if (validacaoGrafico) {
        validacaoGrafico.destroy();
        validacaoGrafico = null;
    }
    
    // Limpar dados
    validacaoDadosAtuais = null;
    validacaoHorasSelecionadas = [];
    
    // Fechar área de valores sugeridos
    fecharValoresSugeridos();
}

/**
 * Abre modal de validação e executa análise de anomalias automaticamente
 */
function abrirValidacaoComAnalise(cdPonto, data, tipoMedidor, pontoNome, pontoCodigo) {
    // Abrir modal de validação normalmente
    abrirModalValidacao(cdPonto, data, tipoMedidor, pontoNome, pontoCodigo);
    
    // Aguardar o modal carregar e fazer scroll para área de IA
    setTimeout(() => {
        // Scroll para o painel de Análise Inteligente
        const iaPanel = document.getElementById('iaPanelValidacao');
        if (iaPanel) {
            iaPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        
        // Enviar a pergunta de análise
        setTimeout(() => {
            enviarPerguntaChat('Há alguma anomalia ou valor suspeito nos dados?');
        }, 300);
    }, 1000);
}

function carregarDadosHorarios(cdPonto, data, tipoMedidor) {
    const tbody = document.getElementById('validacaoTabelaBody');
    if (!tbody) return;
    
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;">Carregando...</td></tr>';
    
    // Resetar resumo - recriar HTML padrão primeiro
    const resumoContainer = document.getElementById('validacaoResumo');
    if (resumoContainer) {
        resumoContainer.innerHTML = `
            <div class="resumo-item">
                <span class="resumo-label">Mínima</span>
                <span class="resumo-valor" id="resumoMinima">-</span>
            </div>
            <div class="resumo-item">
                <span class="resumo-label">Média</span>
                <span class="resumo-valor" id="resumoMedia">-</span>
            </div>
            <div class="resumo-item">
                <span class="resumo-label">Máxima</span>
                <span class="resumo-valor" id="resumoMaxima">-</span>
            </div>
        `;
    }
    
    fetch(`bd/operacoes/getDadosHorarios.php?cdPonto=${cdPonto}&data=${data}&tipoMedidor=${tipoMedidor}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                validacaoDadosAtuais = data;
                validacaoUnidadeAtual = data.unidade;
                renderizarTabelaHoraria(data.dados, data.unidade);
                renderizarGraficoValidacao(data.dados, data.unidade);
                atualizarResumoDia(data.dados, data.unidade);
            } else {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:#dc2626;">${data.message || 'Erro ao carregar dados'}</td></tr>`;
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#dc2626;">Erro de comunicação</td></tr>';
        });
}

function atualizarResumoDia(dados, unidade) {
    const isTipoNivel = validacaoTipoMedidorAtual === 6;
    const resumoContainer = document.getElementById('validacaoResumo');
    if (!resumoContainer) return;
    
    let minGlobal = null;
    let maxGlobal = null;
    let somaValores = 0;
    let totalRegistros = 0;
    let totalExtravasou = 0;
    
    dados.forEach(d => {
        if (d.qtd_registros > 0) {
            // Mínima global
            if (d.min !== null && (minGlobal === null || d.min < minGlobal)) {
                minGlobal = d.min;
            }
            // Máxima global
            if (d.max !== null && (maxGlobal === null || d.max > maxGlobal)) {
                maxGlobal = d.max;
            }
            // Soma para média diária - apenas se não for tipo 6
            if (!isTipoNivel && d.media !== null) {
                somaValores += d.media * 60;
                totalRegistros += d.qtd_registros;
            }
            // Para tipo 6, somar extravasou
            if (isTipoNivel && d.soma_extravasou !== undefined) {
                totalExtravasou += d.soma_extravasou;
            }
        }
    });
    
    // Reconstruir HTML do resumo conforme o tipo
    if (isTipoNivel) {
        // Para tipo 6: Mínima, Máxima, Total Min >= 100
        resumoContainer.innerHTML = `
            <div class="resumo-item">
                <span class="resumo-label">Mínima</span>
                <span class="resumo-valor">${minGlobal !== null ? formatarNumero(minGlobal) + ' ' + unidade : '-'}</span>
            </div>
            <div class="resumo-item">
                <span class="resumo-label">Máxima</span>
                <span class="resumo-valor">${maxGlobal !== null ? formatarNumero(maxGlobal) + ' ' + unidade : '-'}</span>
            </div>
            <div class="resumo-item extravasou">
                <span class="resumo-label">Total Min >= 100</span>
                <span class="resumo-valor" style="color:#dc2626;">${totalExtravasou} min</span>
            </div>
        `;
    } else {
        // Para outros tipos: Mínima, Média, Máxima
        const mediaGlobal = somaValores > 0 ? somaValores / 1440 : null;
        resumoContainer.innerHTML = `
            <div class="resumo-item">
                <span class="resumo-label">Mínima</span>
                <span class="resumo-valor">${minGlobal !== null ? formatarNumero(minGlobal) + ' ' + unidade : '-'}</span>
            </div>
            <div class="resumo-item">
                <span class="resumo-label">Média</span>
                <span class="resumo-valor">${mediaGlobal !== null ? formatarNumero(mediaGlobal) + ' ' + unidade : '-'}</span>
            </div>
            <div class="resumo-item">
                <span class="resumo-label">Máxima</span>
                <span class="resumo-valor">${maxGlobal !== null ? formatarNumero(maxGlobal) + ' ' + unidade : '-'}</span>
            </div>
        `;
    }
}

function renderizarTabelaHoraria(dados, unidade) {
    const tbody = document.getElementById('validacaoTabelaBody');
    const thead = document.querySelector('#validacaoTabela thead tr');
    const apenasVisualizacao = validacaoTipoMedidorAtual === 4;
    const isTipoNivel = validacaoTipoMedidorAtual === 6;
    
    // Atualizar cabeçalho da tabela conforme o tipo
    if (isTipoNivel) {
        // Para tipo 6: Hora, Mínimo, Máximo, Min >= 100, Registros
        thead.innerHTML = `
            <th style="width:100px;">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                    <input type="checkbox" id="checkboxTodos" onchange="toggleTodasHoras()">
                    Hora
                </label>
            </th>
            <th>Mínimo</th>
            <th>Máximo</th>
            <th>Min >= 100</th>
            <th>Registros</th>
        `;
    } else if (apenasVisualizacao) {
        thead.innerHTML = `
            <th style="width:100px;padding-left:12px;">Hora</th>
            <th>Média</th>
            <th>Mínimo</th>
            <th>Máximo</th>
            <th>Registros</th>
        `;
    } else {
        thead.innerHTML = `
            <th style="width:100px;">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                    <input type="checkbox" id="checkboxTodos" onchange="toggleTodasHoras()">
                    Hora
                </label>
            </th>
            <th>Média</th>
            <th>Mínimo</th>
            <th>Máximo</th>
            <th>Registros</th>
        `;
    }
    
    // Criar array com todas as 24 horas
    const horasMap = {};
    dados.forEach(d => {
        horasMap[d.hora] = d;
    });
    
    let html = '';
    for (let h = 0; h < 24; h++) {
        const d = horasMap[h] || { hora: h, media: null, min: null, max: null, qtd_registros: 0, tratado: false, soma_extravasou: 0 };
        const horaStr = String(h).padStart(2, '0') + ':00';
        const temDados = d.qtd_registros > 0;
        const selecionada = validacaoHorasSelecionadas.includes(h);
        const tratado = d.tratado || false;
        
        if (isTipoNivel) {
            // Layout para tipo 6: Mínimo, Máximo, Min >= 100, Registros
            html += `<tr data-hora="${h}" class="${selecionada ? 'selecionada' : ''} ${tratado ? 'tratado' : ''}">
                <td class="hora-col">
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                        <input type="checkbox" class="hora-checkbox" value="${h}" 
                               ${selecionada ? 'checked' : ''} 
                               onchange="toggleHora(${h}, ${d.soma_extravasou || 0})">
                        ${horaStr}
                        ${tratado ? '<span class="badge-tratado" title="Dados validados">✓</span>' : ''}
                    </label>
                </td>
                <td>${temDados ? formatarNumero(d.min) + ' ' + unidade : '<span style="color:#94a3b8">-</span>'}</td>
                <td>${temDados ? formatarNumero(d.max) + ' ' + unidade : '<span style="color:#94a3b8">-</span>'}</td>
                <td style="${d.soma_extravasou > 0 ? 'color:#dc2626;font-weight:600;' : ''}">${temDados ? (d.soma_extravasou || 0) + ' min' : '<span style="color:#94a3b8">0</span>'}</td>
                <td>${d.qtd_registros > 0 ? d.qtd_registros : '<span style="color:#94a3b8">0</span>'}</td>
            </tr>`;
        } else {
            // Layout padrão: Média, Mínimo, Máximo, Registros
            html += `<tr data-hora="${h}" class="${selecionada ? 'selecionada' : ''} ${tratado ? 'tratado' : ''}">
                <td class="hora-col">
                    <label style="display:flex;align-items:center;gap:6px;cursor:${apenasVisualizacao ? 'default' : 'pointer'};">
                        ${!apenasVisualizacao ? `<input type="checkbox" class="hora-checkbox" value="${h}" 
                               ${selecionada ? 'checked' : ''} 
                               onchange="toggleHora(${h}, ${d.media !== null ? d.media : 'null'})">` : ''}
                        ${horaStr}
                        ${tratado ? '<span class="badge-tratado" title="Dados validados">✓</span>' : ''}
                    </label>
                </td>
                <td>${temDados ? formatarNumero(d.media) + ' ' + unidade : '<span style="color:#94a3b8">-</span>'}</td>
                <td>${temDados ? formatarNumero(d.min) + ' ' + unidade : '<span style="color:#94a3b8">-</span>'}</td>
                <td>${temDados ? formatarNumero(d.max) + ' ' + unidade : '<span style="color:#94a3b8">-</span>'}</td>
                <td>${d.qtd_registros > 0 ? d.qtd_registros : '<span style="color:#94a3b8">0</span>'}</td>
            </tr>`;
        }
    }
    
    tbody.innerHTML = html;
    
    // Atualizar cabeçalho com checkbox "selecionar todos"
    if (!apenasVisualizacao) {
        atualizarCheckboxTodos();
    }
}

function renderizarGraficoValidacao(dados, unidade) {
    const ctx = document.getElementById('validacaoGrafico').getContext('2d');
    const isTipoNivel = validacaoTipoMedidorAtual === 6;
    
    if (validacaoGrafico) {
        validacaoGrafico.destroy();
    }
    
    // Preparar dados para todas as 24 horas
    const labels = [];
    const valoresPrincipais = []; // Média ou Máximo dependendo do tipo
    const valoresMin = [];
    const valoresMax = [];
    const coresPontos = [];
    const tratados = [];
    
    const horasMap = {};
    dados.forEach(d => {
        horasMap[d.hora] = d;
    });
    
    for (let h = 0; h < 24; h++) {
        labels.push(String(h).padStart(2, '0') + 'h');
        const d = horasMap[h];
        if (d && d.qtd_registros > 0) {
            // Para tipo 6, usar máximo como valor principal
            valoresPrincipais.push(isTipoNivel ? d.max : d.media);
            valoresMin.push(d.min);
            valoresMax.push(d.max);
            tratados.push(d.tratado || false);
            coresPontos.push(d.tratado ? '#3b82f6' : '#dc2626');
        } else {
            valoresPrincipais.push(null);
            valoresMin.push(null);
            valoresMax.push(null);
            tratados.push(false);
            coresPontos.push('#dc2626');
        }
    }
    
    // Plugin para error bars
    const errorBarsPlugin = {
        id: 'errorBarsValidacao',
        afterDatasetsDraw: function(chart) {
            const ctx = chart.ctx;
            const meta = chart.getDatasetMeta(0);
            
            ctx.save();
            ctx.lineWidth = 2;
            
            meta.data.forEach((point, index) => {
                if (valoresMin[index] === null || valoresMax[index] === null) return;
                
                ctx.strokeStyle = tratados[index] ? '#1d4ed8' : '#1e3a5f';
                
                const x = point.x;
                const yMin = chart.scales.y.getPixelForValue(valoresMin[index]);
                const yMax = chart.scales.y.getPixelForValue(valoresMax[index]);
                const capWidth = 4;
                
                ctx.beginPath();
                ctx.moveTo(x, yMin);
                ctx.lineTo(x, yMax);
                ctx.stroke();
                
                ctx.beginPath();
                ctx.moveTo(x - capWidth, yMax);
                ctx.lineTo(x + capWidth, yMax);
                ctx.stroke();
                
                ctx.beginPath();
                ctx.moveTo(x - capWidth, yMin);
                ctx.lineTo(x + capWidth, yMin);
                ctx.stroke();
            });
            
            ctx.restore();
        }
    };
    
    validacaoGrafico = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: isTipoNivel ? 'Máximo' : 'Média',
                data: valoresPrincipais,
                borderColor: '#dc2626',
                backgroundColor: '#dc2626',
                borderWidth: 2,
                tension: 0,
                pointRadius: 5,
                pointBackgroundColor: coresPontos,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                spanGaps: false,
                fill: false,
                segment: {
                    borderColor: function(ctx) {
                        const prev = ctx.p0DataIndex;
                        const curr = ctx.p1DataIndex;
                        if (tratados[prev] && tratados[curr]) {
                            return '#3b82f6';
                        }
                        return '#dc2626';
                    }
                }
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const idx = context.dataIndex;
                            let lines = [];
                            if (context.raw !== null) {
                                lines.push((isTipoNivel ? 'Máx: ' : 'Média: ') + formatarNumero(context.raw) + ' ' + unidade);
                            }
                            if (!isTipoNivel && valoresMin[idx] !== null) {
                                lines.push('Mín: ' + formatarNumero(valoresMin[idx]) + ' ' + unidade);
                            }
                            if (!isTipoNivel && valoresMax[idx] !== null) {
                                lines.push('Máx: ' + formatarNumero(valoresMax[idx]) + ' ' + unidade);
                            }
                            if (isTipoNivel && valoresMin[idx] !== null) {
                                lines.push('Mín: ' + formatarNumero(valoresMin[idx]) + ' ' + unidade);
                            }
                            if (tratados[idx]) {
                                lines.push('✓ Dados validados');
                            }
                            return lines;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 9 } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#e5e7eb' },
                    ticks: {
                        font: { size: 9 },
                        callback: function(value) {
                            return formatarNumero(value);
                        }
                    }
                }
            }
        },
        plugins: [errorBarsPlugin]
    });
}

function toggleHora(hora, valorAtual) {
    const index = validacaoHorasSelecionadas.indexOf(hora);
    if (index > -1) {
        // Remover da seleção
        validacaoHorasSelecionadas.splice(index, 1);
    } else {
        // Adicionar à seleção
        validacaoHorasSelecionadas.push(hora);
    }
    
    // Ordenar array
    validacaoHorasSelecionadas.sort((a, b) => a - b);
    
    // Atualizar visual da linha
    const tr = document.querySelector(`#validacaoTabelaBody tr[data-hora="${hora}"]`);
    if (tr) {
        tr.classList.toggle('selecionada', validacaoHorasSelecionadas.includes(hora));
    }
    
    // Mostrar/ocultar formulário correto baseado no tipo
    const isTipoNivel = validacaoTipoMedidorAtual === 6;
    if (validacaoHorasSelecionadas.length > 0) {
        if (isTipoNivel) {
            document.getElementById('validacaoForm').style.display = 'none';
            document.getElementById('validacaoFormNivel').style.display = 'block';
        } else {
            document.getElementById('validacaoForm').style.display = 'block';
            document.getElementById('validacaoFormNivel').style.display = 'none';
        }
        atualizarFormularioHoras();
    } else {
        document.getElementById('validacaoForm').style.display = 'none';
        document.getElementById('validacaoFormNivel').style.display = 'none';
    }
    
    atualizarCheckboxTodos();
    atualizarBotaoValidar();
}

function atualizarFormularioHoras() {
    const isTipoNivel = validacaoTipoMedidorAtual === 6;
    
    // Mostrar horas selecionadas no formulário
    const horasTexto = validacaoHorasSelecionadas.map(h => 
        String(h).padStart(2, '0') + ':00'
    ).join(', ');
    
    const horasDisplay = validacaoHorasSelecionadas.length === 1 
        ? horasTexto + ' - ' + String(validacaoHorasSelecionadas[0]).padStart(2, '0') + ':59'
        : horasTexto + ` (${validacaoHorasSelecionadas.length} horas)`;
    
    if (isTipoNivel) {
        // Formulário para tipo 6 (Nível Reservatório)
        document.getElementById('validacaoHoraSelecionadaNivel').value = horasDisplay;
        
        // Mostrar valor atual de extravasou se houver apenas uma hora selecionada
        if (validacaoHorasSelecionadas.length === 1 && validacaoDadosAtuais) {
            const hora = validacaoHorasSelecionadas[0];
            const dadoHora = validacaoDadosAtuais.dados.find(d => d.hora === hora);
            document.getElementById('validacaoExtravasouAtual').value = 
                dadoHora ? (dadoHora.soma_extravasou || 0) + ' min' : '0 min';
        } else {
            document.getElementById('validacaoExtravasouAtual').value = 
                validacaoHorasSelecionadas.length > 1 ? 'Múltiplas horas' : '0 min';
        }
    } else {
        // Formulário padrão
        document.getElementById('validacaoHoraSelecionada').value = horasDisplay;
        
        // Mostrar valor atual se houver apenas uma hora selecionada
        if (validacaoHorasSelecionadas.length === 1 && validacaoDadosAtuais) {
            const hora = validacaoHorasSelecionadas[0];
            const dadoHora = validacaoDadosAtuais.dados.find(d => d.hora === hora);
            document.getElementById('validacaoValorAtual').value = 
                dadoHora && dadoHora.media !== null 
                    ? formatarNumero(dadoHora.media) + ' ' + validacaoUnidadeAtual 
                    : 'Sem dados';
        } else {
            document.getElementById('validacaoValorAtual').value = 
                validacaoHorasSelecionadas.length > 1 ? 'Múltiplas horas' : 'Sem dados';
        }
    }
}

function atualizarCheckboxTodos() {
    const checkboxTodos = document.getElementById('checkboxTodos');
    if (checkboxTodos) {
        checkboxTodos.checked = validacaoHorasSelecionadas.length === 24;
        checkboxTodos.indeterminate = validacaoHorasSelecionadas.length > 0 && validacaoHorasSelecionadas.length < 24;
    }
}

function toggleTodasHoras() {
    const checkboxTodos = document.getElementById('checkboxTodos');
    if (validacaoHorasSelecionadas.length === 24) {
        // Desmarcar todas
        validacaoHorasSelecionadas = [];
    } else {
        // Marcar todas
        validacaoHorasSelecionadas = Array.from({length: 24}, (_, i) => i);
    }
    
    // Atualizar visual
    document.querySelectorAll('#validacaoTabelaBody tr').forEach(tr => {
        const hora = parseInt(tr.dataset.hora);
        tr.classList.toggle('selecionada', validacaoHorasSelecionadas.includes(hora));
        const checkbox = tr.querySelector('.hora-checkbox');
        if (checkbox) checkbox.checked = validacaoHorasSelecionadas.includes(hora);
    });
    
    // Mostrar/ocultar formulário correto baseado no tipo
    const isTipoNivel = validacaoTipoMedidorAtual === 6;
    if (validacaoHorasSelecionadas.length > 0) {
        if (isTipoNivel) {
            document.getElementById('validacaoForm').style.display = 'none';
            document.getElementById('validacaoFormNivel').style.display = 'block';
        } else {
            document.getElementById('validacaoForm').style.display = 'block';
            document.getElementById('validacaoFormNivel').style.display = 'none';
        }
        atualizarFormularioHoras();
    } else {
        document.getElementById('validacaoForm').style.display = 'none';
        document.getElementById('validacaoFormNivel').style.display = 'none';
    }
    
    atualizarCheckboxTodos();
    atualizarBotaoValidar();
}

function selecionarHorasSemDados() {
    // Selecionar apenas horas sem registros
    validacaoHorasSelecionadas = [];
    
    if (validacaoDadosAtuais && validacaoDadosAtuais.dados) {
        const horasComDados = validacaoDadosAtuais.dados
            .filter(d => d.qtd_registros > 0)
            .map(d => d.hora);
        
        for (let h = 0; h < 24; h++) {
            if (!horasComDados.includes(h)) {
                validacaoHorasSelecionadas.push(h);
            }
        }
    } else {
        // Se não há dados, selecionar todas
        validacaoHorasSelecionadas = Array.from({length: 24}, (_, i) => i);
    }
    
    // Atualizar visual
    document.querySelectorAll('#validacaoTabelaBody tr').forEach(tr => {
        const hora = parseInt(tr.dataset.hora);
        tr.classList.toggle('selecionada', validacaoHorasSelecionadas.includes(hora));
        const checkbox = tr.querySelector('.hora-checkbox');
        if (checkbox) checkbox.checked = validacaoHorasSelecionadas.includes(hora);
    });
    
    // Mostrar/ocultar formulário correto baseado no tipo
    const isTipoNivel = validacaoTipoMedidorAtual === 6;
    if (validacaoHorasSelecionadas.length > 0) {
        if (isTipoNivel) {
            document.getElementById('validacaoForm').style.display = 'none';
            document.getElementById('validacaoFormNivel').style.display = 'block';
        } else {
            document.getElementById('validacaoForm').style.display = 'block';
            document.getElementById('validacaoFormNivel').style.display = 'none';
        }
        atualizarFormularioHoras();
    } else {
        document.getElementById('validacaoForm').style.display = 'none';
        document.getElementById('validacaoFormNivel').style.display = 'none';
        showToast('Todas as horas já possuem dados', 'info');
    }
    
    atualizarCheckboxTodos();
    atualizarBotaoValidar();
}

// ==========================================
// VALORES SUGERIDOS (HISTÓRICO + TENDÊNCIA)
// ==========================================

let valoresSugeridosAtual = []; // Armazena valores sugeridos para aplicar

/**
 * Busca valores sugeridos para as horas selecionadas
 */
function buscarValoresSugeridos() {
    // Verificar se há horas selecionadas
    const checkboxes = document.querySelectorAll('#validacaoTabelaBody input[type="checkbox"]:checked');
    
    if (checkboxes.length === 0) {
        showToast('Selecione ao menos uma hora na tabela', 'aviso');
        return;
    }
    
    // Coletar as horas selecionadas
    const horasSelecionadas = [];
    checkboxes.forEach(cb => {
        const hora = parseInt(cb.value);
        if (!isNaN(hora)) {
            horasSelecionadas.push(hora);
        }
    });
    
    if (horasSelecionadas.length === 0) {
        showToast('Nenhuma hora válida selecionada', 'aviso');
        return;
    }
    
    // Verificar dados do ponto
    if (!validacaoPontoAtual || !validacaoDataAtual) {
        showToast('Dados do ponto não disponíveis', 'erro');
        return;
    }
    
    // Verificar se temos os dados da IA carregados
    if (!dadosCompletosIA || !dadosCompletosIA.historico_por_hora) {
        // Buscar dados da IA
        showToast('Carregando dados históricos...', 'info');
        
        const url = `bd/operacoes/consultarDadosIA.php?cdPonto=${validacaoPontoAtual}&data=${validacaoDataAtual}`;
        
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                if (!text || text.trim() === '') {
                    showToast('Resposta vazia do servidor', 'erro');
                    return;
                }
                
                try {
                    const data = JSON.parse(text);
                    
                    if (data.success) {
                        dadosCompletosIA = data.dados;
                        exibirValoresSugeridos(horasSelecionadas);
                    } else {
                        showToast('Erro: ' + (data.error || data.message || 'Falha ao carregar dados'), 'erro');
                    }
                } catch (e) {
                    showToast('Erro ao processar resposta do servidor', 'erro');
                }
            })
            .catch(error => {
                showToast('Erro ao carregar dados: ' + error.message, 'erro');
            });
    } else {
        exibirValoresSugeridos(horasSelecionadas);
    }
}

/**
 * Exibe os valores sugeridos na tabela
 */
function exibirValoresSugeridos(horasSelecionadas) {
    const container = document.getElementById('valoresSugeridosContainer');
    const tbody = document.getElementById('valoresSugeridosBody');
    const info = document.getElementById('valoresSugeridosInfo');
    
    if (!dadosCompletosIA || !dadosCompletosIA.historico_por_hora) {
        showToast('Dados históricos não disponíveis', 'erro');
        return;
    }
    
    const histHora = dadosCompletosIA.historico_por_hora;
    const fator = histHora.fator_tendencia || 1;
    const tendenciaPct = histHora.tendencia_percentual || 0;
    
    // Atualizar info
    const direcao = tendenciaPct >= 0 ? 'acima' : 'abaixo';
    info.textContent = `Tendência do dia: ${Math.abs(tendenciaPct).toFixed(1)}% ${direcao} do normal (fator: ${fator.toFixed(2)})`;
    
    // Limpar tabela
    tbody.innerHTML = '';
    valoresSugeridosAtual = [];
    
    // Ordenar horas
    horasSelecionadas.sort((a, b) => a - b);
    
    // Preencher tabela
    horasSelecionadas.forEach(hora => {
        const dadosHora = histHora.horas[hora];
        
        if (!dadosHora) return;
        
        // Buscar valor atual da hora
        let valorAtual = '-';
        if (validacaoDadosAtuais && validacaoDadosAtuais.dados) {
            const dadoAtual = validacaoDadosAtuais.dados.find(d => d.hora === hora);
            if (dadoAtual && dadoAtual.media !== null) {
                valorAtual = parseFloat(dadoAtual.media).toFixed(2);
            }
        }
        
        const mediaHist = dadosHora.media_historica || 0;
        const valorSugerido = dadosHora.valor_sugerido || 0;
        
        // Só adicionar se tiver dados históricos válidos
        if (mediaHist > 0) {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${String(hora).padStart(2, '0')}:00</strong></td>
                <td>${valorAtual} L/s</td>
                <td>${mediaHist.toFixed(2)} L/s</td>
                <td>×${fator.toFixed(2)}</td>
                <td class="valor-sugerido">${valorSugerido.toFixed(2)} L/s</td>
            `;
            tbody.appendChild(tr);
            
            // Armazenar para aplicar depois
            valoresSugeridosAtual.push({
                hora: hora,
                valor: valorSugerido
            });
        } else {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${String(hora).padStart(2, '0')}:00</strong></td>
                <td>${valorAtual} L/s</td>
                <td colspan="3" style="color:#94a3b8;font-style:italic;">Sem dados históricos suficientes</td>
            `;
            tbody.appendChild(tr);
        }
    });
    
    // Mostrar container
    container.style.display = 'block';
    
    // Resetar estado dos botões
    const btnAplicar = document.querySelector('.btn-aplicar-sugeridos');
    const btnCancelar = document.querySelector('.btn-cancelar-sugeridos');
    if (btnAplicar) btnAplicar.disabled = false;
    if (btnCancelar) btnCancelar.disabled = false;
    
    // Scroll para o container
    container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    
    if (valoresSugeridosAtual.length === 0) {
        showToast('Nenhuma hora com dados históricos suficientes', 'aviso');
    }
}

/**
 * Aplica os valores sugeridos
 */
function aplicarValoresSugeridos() {
    if (!valoresSugeridosAtual || valoresSugeridosAtual.length === 0) {
        showToast('Nenhum valor para aplicar', 'erro');
        return;
    }
    
    if (!validacaoPontoAtual || !validacaoDataAtual) {
        showToast('Dados do ponto não disponíveis', 'erro');
        return;
    }
    
    // Confirmar antes de aplicar
    const qtdHoras = valoresSugeridosAtual.length;
    if (!confirm(`Tem certeza que deseja aplicar os valores sugeridos para ${qtdHoras} hora(s)?`)) {
        return;
    }
    
    // Desabilitar botões
    const btnAplicar = document.querySelector('.btn-aplicar-sugeridos');
    const btnCancelar = document.querySelector('.btn-cancelar-sugeridos');
    if (btnAplicar) btnAplicar.disabled = true;
    if (btnCancelar) btnCancelar.disabled = true;
    
    const payload = {
        cdPonto: validacaoPontoAtual,
        data: validacaoDataAtual,
        tipoMedidor: validacaoTipoMedidorAtual || 1,
        valores: valoresSugeridosAtual,
        observacao: 'Valor sugerido aplicado (histórico + tendência)'
    };
    
    fetch('bd/operacoes/validarDadosIA.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'sucesso');
            
            // Fechar área de valores sugeridos
            fecharValoresSugeridos();
            
            // Limpar cache da IA para forçar recarregar dados atualizados
            dadosCompletosIA = null;
            
            // Desmarcar checkboxes e limpar seleção
            limparSelecaoHoras();
            
            // Esconder formulário de validação manual
            document.getElementById('validacaoForm').style.display = 'none';
            document.getElementById('validacaoFormNivel').style.display = 'none';
            
            // Recarregar dados do modal
            if (typeof carregarDadosHorarios === 'function') {
                carregarDadosHorarios(validacaoPontoAtual, validacaoDataAtual);
            }
            
            // Limpar valores pendentes
            valoresSugeridosAtual = [];
            
        } else {
            showToast(data.message || 'Erro ao aplicar valores', 'erro');
            if (btnAplicar) btnAplicar.disabled = false;
            if (btnCancelar) btnCancelar.disabled = false;
        }
    })
    .catch(error => {
        console.error('Erro ao aplicar valores:', error);
        showToast('Erro ao aplicar valores: ' + error.message, 'erro');
        if (btnAplicar) btnAplicar.disabled = false;
        if (btnCancelar) btnCancelar.disabled = false;
    });
}

/**
 * Limpa a seleção de horas (checkboxes, visual, array)
 */
function limparSelecaoHoras() {
    // Desmarcar checkboxes
    document.querySelectorAll('#validacaoTabelaBody input[type="checkbox"]:checked').forEach(cb => {
        cb.checked = false;
    });
    
    // Limpar array de horas selecionadas
    validacaoHorasSelecionadas = [];
    
    // Remover classe de seleção das linhas
    document.querySelectorAll('#validacaoTabelaBody tr.selecionada').forEach(tr => {
        tr.classList.remove('selecionada');
    });
    
    // Resetar checkbox "todos"
    const checkboxTodos = document.getElementById('checkboxTodos');
    if (checkboxTodos) {
        checkboxTodos.checked = false;
        checkboxTodos.indeterminate = false;
    }
    
    // Atualizar botão validar
    atualizarBotaoValidar();
}

/**
 * Fecha a área de valores sugeridos
 */
function fecharValoresSugeridos() {
    const container = document.getElementById('valoresSugeridosContainer');
    if (container) {
        container.style.display = 'none';
    }
    valoresSugeridosAtual = [];
    
    // Resetar estado dos botões para próxima abertura
    const btnAplicar = document.querySelector('.btn-aplicar-sugeridos');
    const btnCancelar = document.querySelector('.btn-cancelar-sugeridos');
    if (btnAplicar) btnAplicar.disabled = false;
    if (btnCancelar) btnCancelar.disabled = false;
}

function atualizarBotaoValidar() {
    const isTipoNivel = validacaoTipoMedidorAtual === 6;
    const btn = document.getElementById('btnValidar');
    
    if (validacaoHorasSelecionadas.length === 0) {
        btn.disabled = true;
        return;
    }
    
    if (isTipoNivel) {
        // Validação para tipo 6 (Nível Reservatório)
        const minutosExtravasou = document.getElementById('validacaoMinutosExtravasou').value;
        const motivo = document.querySelector('input[name="validacaoMotivo"]:checked');
        
        btn.disabled = minutosExtravasou === '' || 
                       isNaN(parseInt(minutosExtravasou)) || 
                       parseInt(minutosExtravasou) < 0 || 
                       parseInt(minutosExtravasou) > 60 ||
                       !motivo;
    } else {
        // Validação padrão
        const novoValor = document.getElementById('validacaoNovoValor').value;
        btn.disabled = novoValor === '' || isNaN(parseFloat(novoValor));
    }
}

// Listeners para habilitar/desabilitar botão
document.getElementById('validacaoNovoValor')?.addEventListener('input', atualizarBotaoValidar);
document.getElementById('validacaoMinutosExtravasou')?.addEventListener('input', atualizarBotaoValidar);
document.querySelectorAll('input[name="validacaoMotivo"]').forEach(radio => {
    radio.addEventListener('change', atualizarBotaoValidar);
});

function executarValidacao() {
    if (validacaoHorasSelecionadas.length === 0) {
        showToast('Selecione pelo menos uma hora', 'erro');
        return;
    }
    
    const isTipoNivel = validacaoTipoMedidorAtual === 6;
    let payload = {
        cdPonto: validacaoPontoAtual,
        data: validacaoDataAtual,
        horas: validacaoHorasSelecionadas,
        tipoMedidor: validacaoTipoMedidorAtual
    };
    
    // Formatar horas para exibição
    const horasTexto = validacaoHorasSelecionadas.map(h => 
        String(h).padStart(2, '0') + ':00'
    ).join(', ');
    
    if (isTipoNivel) {
        // Validação para tipo 6 (Nível Reservatório)
        const minutosExtravasou = parseInt(document.getElementById('validacaoMinutosExtravasou').value);
        const motivoEl = document.querySelector('input[name="validacaoMotivo"]:checked');
        
        if (isNaN(minutosExtravasou) || minutosExtravasou < 0 || minutosExtravasou > 60) {
            showToast('Minutos >= 100 deve ser entre 0 e 60', 'erro');
            return;
        }
        
        if (!motivoEl) {
            showToast('Selecione o motivo (Falha ou Extravasou)', 'erro');
            return;
        }
        
        const motivo = parseInt(motivoEl.value);
        const motivoTexto = motivo === 1 ? 'Falha' : 'Extravasou';
        const observacao = document.getElementById('validacaoObservacaoNivel').value.trim();
        
        payload.minutosExtravasou = minutosExtravasou;
        payload.motivo = motivo;
        payload.observacao = observacao;
        
        // Confirmação
        const totalNovosRegistros = validacaoHorasSelecionadas.length * 60;
        if (!confirm(`Confirma a validação?\n\nHoras: ${horasTexto}\nMinutos >= 100: ${minutosExtravasou} por hora\nMotivo: ${motivoTexto}\n\nEsta ação irá:\n- Descartar registros existentes nas horas selecionadas\n- Criar ${totalNovosRegistros} novos registros (60 por hora) com Nível=100%\n- Distribuir NR_EXTRAVASOU=1 aleatoriamente em ${minutosExtravasou} registros por hora`)) {
            return;
        }
    } else {
        // Validação padrão
        const novoValor = parseFloat(document.getElementById('validacaoNovoValor').value);
        if (isNaN(novoValor)) {
            showToast('Informe um valor válido', 'erro');
            return;
        }
        
        const observacao = document.getElementById('validacaoObservacao').value.trim();
        
        payload.novoValor = novoValor;
        payload.observacao = observacao;
        
        // Confirmação
        const totalRegistros = validacaoHorasSelecionadas.length * 60;
        if (!confirm(`Confirma a validação?\n\nHoras: ${horasTexto}\nNovo valor: ${formatarNumero(novoValor)} ${validacaoUnidadeAtual}\n\nEsta ação irá:\n- Inativar registros existentes nas horas selecionadas\n- Criar ${totalRegistros} novos registros (60 por hora)`)) {
            return;
        }
    }
    
    // Desabilitar botão
    const btn = document.getElementById('btnValidar');
    btn.disabled = true;
    btn.innerHTML = '<ion-icon name="hourglass-outline"></ion-icon> Processando...';
    
    fetch('bd/operacoes/validarDados.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'sucesso');
            
            // Recarregar dados do modal
            carregarDadosHorarios(validacaoPontoAtual, validacaoDataAtual, validacaoTipoMedidorAtual);
            
            // Limpar seleção de horas (checkboxes, visual, array)
            limparSelecaoHoras();
            
            // Esconder formulários
            document.getElementById('validacaoForm').style.display = 'none';
            document.getElementById('validacaoFormNivel').style.display = 'none';
            
            // Limpar campos
            if (isTipoNivel) {
                document.getElementById('validacaoMinutosExtravasou').value = '';
                document.getElementById('validacaoObservacaoNivel').value = '';
                document.querySelectorAll('input[name="validacaoMotivo"]').forEach(r => r.checked = false);
            } else {
                document.getElementById('validacaoNovoValor').value = '';
                document.getElementById('validacaoObservacao').value = '';
            }
            
            // Recarregar dados da tabela principal após 1 segundo
            setTimeout(() => {
                buscarDados();
            }, 1000);
        } else {
            showToast(data.message || 'Erro ao validar dados', 'erro');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showToast('Erro de comunicação com o servidor', 'erro');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<ion-icon name="checkmark-outline"></ion-icon> Validar Dados';
        atualizarBotaoValidar();
    });
}

// ==================== ANÁLISE COM IA ====================

// ==================== CHAT COM IA ====================

let dadosCompletosIA = null; // Cache dos dados do banco
let iaChatHistorico = []; // Histórico de mensagens do chat

/**
 * Lê as horas selecionadas nos checkboxes e envia pergunta para a IA
 */
function enviarPerguntaHorasSelecionadas() {
    // Buscar checkboxes marcados
    const checkboxes = document.querySelectorAll('#validacaoTabelaBody input[type="checkbox"]:checked');
    
    if (checkboxes.length === 0) {
        showToast('Selecione ao menos uma hora na tabela', 'aviso');
        return;
    }
    
    // Coletar as horas selecionadas (usa .value do checkbox)
    const horasSelecionadas = [];
    checkboxes.forEach(cb => {
        const hora = parseInt(cb.value);
        if (!isNaN(hora)) {
            horasSelecionadas.push(hora);
        }
    });
    
    if (horasSelecionadas.length === 0) {
        showToast('Nenhuma hora válida selecionada', 'aviso');
        return;
    }
    
    // Ordenar as horas
    horasSelecionadas.sort((a, b) => a - b);
    
    // Formatar as horas para exibição
    const horasFormatadas = horasSelecionadas.map(h => String(h).padStart(2, '0') + ':00').join(', ');
    
    // Construir a pergunta
    const pergunta = `Qual o valor sugerido para as horas ${horasFormatadas}? Analise os dados das últimas 12 semanas do mesmo dia para cada hora e mostre os cálculos.`;
    
    // Enviar a pergunta
    enviarPerguntaChat(pergunta);
}

function enviarPerguntaChat(perguntaFixa = null) {
    const input = document.getElementById('iaChatInput');
    const btn = document.getElementById('btnEnviarChat');
    const mensagens = document.getElementById('iaChatMensagens');
    const sugestoes = document.getElementById('iaChatSugestoes');
    
    const pergunta = perguntaFixa || input.value.trim();
    if (!pergunta) return;
    
    // Verificar se dados foram carregados
    if (!validacaoDadosAtuais || !validacaoDadosAtuais.dados || validacaoDadosAtuais.dados.length === 0) {
        mensagens.innerHTML += `
            <div class="ia-chat-msg ia">
                <div class="ia-chat-avatar">
                    <ion-icon name="sparkles"></ion-icon>
                </div>
                <div class="ia-chat-bubble" style="background:#fef3c7;border-color:#fde68a;color:#92400e;">
                    Aguarde os dados carregarem antes de fazer perguntas.
                </div>
            </div>
        `;
        mensagens.scrollTop = mensagens.scrollHeight;
        return;
    }
    
    // Limpar input
    if (!perguntaFixa) input.value = '';
    
    // Adicionar mensagem do usuário
    mensagens.innerHTML += `
        <div class="ia-chat-msg user">
            <div class="ia-chat-avatar">
                <ion-icon name="person"></ion-icon>
            </div>
            <div class="ia-chat-bubble">${escapeHtmlChat(pergunta)}</div>
        </div>
    `;
    
    // Adicionar indicador de digitação
    const typingId = 'typing-' + Date.now();
    mensagens.innerHTML += `
        <div class="ia-chat-msg ia" id="${typingId}">
            <div class="ia-chat-avatar">
                <ion-icon name="sparkles"></ion-icon>
            </div>
            <div class="ia-chat-bubble">
                <div class="ia-chat-typing">
                    <span></span><span></span><span></span>
                </div>
            </div>
        </div>
    `;
    
    mensagens.scrollTop = mensagens.scrollHeight;
    btn.disabled = true;
    
    // Buscar dados completos do banco se ainda não tiver
    buscarDadosCompletosIA()
        .then(dadosCompletos => {
            // Construir contexto com dados do banco (sempre enviar)
            let contextoSistema = construirContextoCompletoChat(dadosCompletos);
            
            // Adicionar pergunta ao histórico
            iaChatHistorico.push({
                role: 'user',
                content: pergunta
            });
            
            // Preparar payload com histórico
            const payload = {
                contexto: contextoSistema,
                historico: iaChatHistorico
            };
            
            // Enviar para API da IA
            return fetch('bd/operacoes/testarIA.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
        })
        .then(response => {
            return response.text();
        })
        .then(text => {
            try {
                return JSON.parse(text);
            } catch(e) {
                throw new Error('JSON inválido: ' + text.substring(0, 200));
            }
        })
        .then(data => {
            document.getElementById(typingId)?.remove();
            
            if (data.success) {
                // Adicionar resposta ao histórico
                iaChatHistorico.push({
                    role: 'assistant',
                    content: data.resposta
                });
                
                mensagens.innerHTML += `
                    <div class="ia-chat-msg ia">
                        <div class="ia-chat-avatar">
                            <ion-icon name="sparkles"></ion-icon>
                        </div>
                        <div class="ia-chat-bubble">${formatarRespostaChat(data.resposta)}</div>
                    </div>
                `;
            } else {
                // Remover última pergunta do histórico se houve erro
                iaChatHistorico.pop();
                
                mensagens.innerHTML += `
                    <div class="ia-chat-msg ia">
                        <div class="ia-chat-avatar">
                            <ion-icon name="sparkles"></ion-icon>
                        </div>
                        <div class="ia-chat-bubble" style="background:#fee2e2;border-color:#fecaca;color:#991b1b;">
                            Erro: ${data.error || 'Falha na comunicação'}
                        </div>
                    </div>
                `;
            }
            
            mensagens.scrollTop = mensagens.scrollHeight;
        })
        .catch(error => {
            console.error('Erro no chat IA:', error);
            document.getElementById(typingId)?.remove();
            
            // Remover última pergunta do histórico se houve erro
            if (iaChatHistorico.length > 0 && iaChatHistorico[iaChatHistorico.length - 1].role === 'user') {
                iaChatHistorico.pop();
            }
            
            mensagens.innerHTML += `
                <div class="ia-chat-msg ia">
                    <div class="ia-chat-avatar">
                        <ion-icon name="sparkles"></ion-icon>
                    </div>
                    <div class="ia-chat-bubble" style="background:#fee2e2;border-color:#fecaca;color:#991b1b;">
                        Erro: ${error.message || 'Falha na comunicação'}
                    </div>
                </div>
            `;
            
            mensagens.scrollTop = mensagens.scrollHeight;
        })
        .finally(() => {
            btn.disabled = false;
            input.focus();
        });
}

/**
 * Busca dados completos do banco para análise
 */
function buscarDadosCompletosIA() {
    // Verificar se temos os dados necessários
    if (!validacaoPontoAtual || !validacaoDataAtual) {
        return Promise.reject(new Error('Ponto ou data não definidos'));
    }
    
    // Se já tem dados em cache para o mesmo ponto/data, usar cache
    if (dadosCompletosIA && 
        dadosCompletosIA.cdPonto === validacaoPontoAtual && 
        dadosCompletosIA.data === validacaoDataAtual) {
        return Promise.resolve(dadosCompletosIA.dados);
    }
    
    const payload = {
        cdPonto: parseInt(validacaoPontoAtual),
        data: String(validacaoDataAtual)
    };
    
    return fetch('bd/operacoes/consultarDadosIA.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(response => {
        return response.text();
    })
    .then(text => {
        if (!text || text.trim() === '') {
            throw new Error('Resposta vazia do servidor');
        }
        try {
            return JSON.parse(text);
        } catch(e) {
            throw new Error('JSON inválido: ' + text.substring(0, 200));
        }
    })
    .then(data => {
        if (data.success) {
            // Guardar em cache
            dadosCompletosIA = {
                cdPonto: validacaoPontoAtual,
                data: validacaoDataAtual,
                dados: data.dados
            };
            return data.dados;
        } else {
            throw new Error(data.error || 'Erro ao buscar dados');
        }
    });
}

/**
 * Constrói contexto completo com dados do banco
 */
function construirContextoCompletoChat(dados) {
    let contexto = '=== DADOS DO SISTEMA DE ABASTECIMENTO DE ÁGUA ===\n\n';
    
    // Função auxiliar para formatar número
    const formatNum = (val, decimais = 2) => {
        if (val === null || val === undefined || val === '') return '-';
        const num = parseFloat(val);
        return isNaN(num) ? '-' : num.toFixed(decimais);
    };
    
    // Informações completas do ponto de medição
    if (dados.ponto && Object.keys(dados.ponto).length > 0) {
        const p = dados.ponto;
        contexto += `========================================\n`;
        contexto += `INFORMAÇÕES DO PONTO DE MEDIÇÃO\n`;
        contexto += `========================================\n`;
        contexto += `Código do Ponto: ${p.CD_PONTO_MEDICAO || 'N/A'}\n`;
        contexto += `Identificador: ${p.CD_IDENTIFICADOR || 'N/A'}\n`;
        contexto += `Nome: ${p.DS_NOME || 'N/A'}\n`;
        contexto += `Localização: ${p.DS_LOCALIZACAO || 'N/A'}\n`;
        contexto += `Unidade Operacional: ${p.UNIDADE_OPERACIONAL || 'N/A'}\n`;
        contexto += `Código Localidade: ${p.CD_LOCALIDADE || 'N/A'}\n`;
        contexto += `\n`;
        contexto += `TIPO E CONFIGURAÇÃO:\n`;
        contexto += `- Tipo de Medidor: ${p.DS_TIPO_MEDIDOR || 'N/A'} (ID: ${p.ID_TIPO_MEDIDOR || 'N/A'})\n`;
        contexto += `- Tipo de Leitura: ${p.DS_TIPO_LEITURA || 'N/A'} (ID: ${p.ID_TIPO_LEITURA || 'N/A'})\n`;
        contexto += `- Periodicidade de Leitura: ${p.OP_PERIODICIDADE_LEITURA || 'N/A'}\n`;
        contexto += `- Tipo de Instalação: ${p.TIPO_INSTALACAO_DESCRICAO || 'N/A'}\n`;
        if (p.MOTIVO_TIPO_INSTALACAO) {
            contexto += `- Motivo Tipo Instalação: ${p.MOTIVO_TIPO_INSTALACAO}\n`;
        }
        contexto += `\n`;
        contexto += `DATAS:\n`;
        contexto += `- Data de Ativação: ${p.DT_ATIVACAO_FORMATADA || 'N/A'}\n`;
        if (p.DT_DESATIVACAO_FORMATADA) {
            contexto += `- Data de Desativação: ${p.DT_DESATIVACAO_FORMATADA}\n`;
        }
        contexto += `\n`;
        contexto += `PARÂMETROS TÉCNICOS:\n`;
        if (p.VL_LIMITE_INFERIOR_VAZAO !== null && p.VL_LIMITE_INFERIOR_VAZAO !== undefined) {
            contexto += `- Limite Inferior de Vazão: ${formatNum(p.VL_LIMITE_INFERIOR_VAZAO)} L/s\n`;
        }
        if (p.VL_LIMITE_SUPERIOR_VAZAO !== null && p.VL_LIMITE_SUPERIOR_VAZAO !== undefined) {
            contexto += `- Limite Superior de Vazão: ${formatNum(p.VL_LIMITE_SUPERIOR_VAZAO)} L/s\n`;
        }
        if (p.VL_FATOR_CORRECAO_VAZAO !== null && p.VL_FATOR_CORRECAO_VAZAO !== undefined) {
            contexto += `- Fator de Correção de Vazão: ${formatNum(p.VL_FATOR_CORRECAO_VAZAO, 4)}\n`;
        }
        contexto += `\n`;
        contexto += `TAGS SCADA:\n`;
        if (p.DS_TAG_VAZAO) contexto += `- Tag Vazão: ${p.DS_TAG_VAZAO}\n`;
        if (p.DS_TAG_PRESSAO) contexto += `- Tag Pressão: ${p.DS_TAG_PRESSAO}\n`;
        if (p.DS_TAG_VOLUME) contexto += `- Tag Volume: ${p.DS_TAG_VOLUME}\n`;
        if (p.DS_TAG_RESERVATORIO) contexto += `- Tag Reservatório: ${p.DS_TAG_RESERVATORIO}\n`;
        if (p.DS_TAG_TEMP_AGUA) contexto += `- Tag Temp. Água: ${p.DS_TAG_TEMP_AGUA}\n`;
        if (p.DS_TAG_TEMP_AMBIENTE) contexto += `- Tag Temp. Ambiente: ${p.DS_TAG_TEMP_AMBIENTE}\n`;
        contexto += `\n`;
        contexto += `INFORMAÇÕES COMPLEMENTARES:\n`;
        if (p.VL_QUANTIDADE_LIGACOES) {
            contexto += `- Quantidade de Ligações: ${p.VL_QUANTIDADE_LIGACOES}\n`;
        }
        if (p.VL_QUANTIDADE_ECONOMIAS) {
            contexto += `- Quantidade de Economias: ${p.VL_QUANTIDADE_ECONOMIAS}\n`;
        }
        if (p.CD_ESTACAO_PITOMETRICA) {
            contexto += `- Estação Pitométrica: ${p.CD_ESTACAO_PITOMETRICA}\n`;
        }
        if (p.COORDENADAS) {
            contexto += `- Coordenadas: ${p.COORDENADAS}\n`;
        }
        if (p.LOC_INST_SAP) {
            contexto += `- Local Instalação SAP: ${p.LOC_INST_SAP}\n`;
        }
        if (p.RESPONSAVEL_NOME) {
            contexto += `- Responsável: ${p.RESPONSAVEL_NOME}\n`;
        }
        if (p.DS_OBSERVACAO) {
            contexto += `- Observações: ${p.DS_OBSERVACAO}\n`;
        }
        contexto += `========================================\n\n`;
    }
    
    // Data atual
    const dataFormatada = validacaoDataAtual ? validacaoDataAtual.split('-').reverse().join('/') : 'N/A';
    contexto += `DATA EM ANÁLISE: ${dataFormatada}\n\n`;
    
    // Dados do dia atual com cálculos
    if (dados.dia_atual && dados.dia_atual.length > 0) {
        let totalRegistrosDia = 0;
        let somaVazaoDia = 0;
        let somaPressaoDia = 0;
        let horasComVazao = 0;
        let horasComPressao = 0;
        
        contexto += `DADOS HORÁRIOS DO DIA:\n`;
        contexto += `Hora  | Média Vazão(L/s) | Soma Hora(L/s) | Registros | Min     | Máx\n`;
        contexto += `------|------------------|----------------|-----------|---------|--------\n`;
        
        dados.dia_atual.forEach(h => {
            const hora = String(h.HORA).padStart(2, '0') + ':00';
            const mediaVazao = parseFloat(h.MEDIA_VAZAO) || 0;
            const registros = parseInt(h.QTD_REGISTROS) || 0;
            const somaHora = mediaVazao * registros; // Soma = média × registros
            const minVazao = formatNum(h.MIN_VAZAO);
            const maxVazao = formatNum(h.MAX_VAZAO);
            
            totalRegistrosDia += registros;
            somaVazaoDia += somaHora;
            
            if (mediaVazao > 0) horasComVazao++;
            
            if (h.MEDIA_PRESSAO !== null) {
                somaPressaoDia += (parseFloat(h.MEDIA_PRESSAO) || 0) * registros;
                horasComPressao++;
            }
            
            contexto += `${hora} | ${formatNum(mediaVazao).padStart(16)} | ${formatNum(somaHora).padStart(14)} | ${String(registros).padStart(9)} | ${minVazao.padStart(7)} | ${maxVazao}\n`;
        });
        
        // Usar cálculos do backend (já calculados corretamente com divisão por 1440)
        const calculos = dados.calculos || {};
        const mediaDiariaVazao = calculos.media_diaria_vazao || 0;
        const mediaDiariaPressao = calculos.media_diaria_pressao || 0;
        const somaTotalVazao = calculos.soma_total_vazao || 0;
        
        contexto += `\n========================================\n`;
        contexto += `RESUMO DO DIA (VALORES OFICIAIS):\n`;
        contexto += `========================================\n`;
        contexto += `- Total de registros: ${calculos.total_registros || totalRegistrosDia} de 1440\n`;
        contexto += `- Horas com dados: ${calculos.horas_com_dados || dados.dia_atual.length}/24\n`;
        contexto += `- Soma total de todas as vazões: ${formatNum(somaTotalVazao)} L/s\n`;
        contexto += `\n`;
        contexto += `>>> MÉDIA DIÁRIA DE VAZÃO: ${formatNum(mediaDiariaVazao)} L/s <<<\n`;
        contexto += `    (Cálculo oficial: ${formatNum(somaTotalVazao)} ÷ 1440 = ${formatNum(mediaDiariaVazao)})\n`;
        if (mediaDiariaPressao > 0) {
            contexto += `>>> MÉDIA DIÁRIA DE PRESSÃO: ${formatNum(mediaDiariaPressao)} mca <<<\n`;
        }
        contexto += `\n`;
        contexto += `IMPORTANTE: Use EXATAMENTE o valor ${formatNum(mediaDiariaVazao)} L/s como média diária.\n`;
        contexto += `Este valor já foi calculado corretamente (soma ÷ 1440).\n`;
        contexto += `========================================\n`;
        contexto += '\n';
    }
    
    // Estatísticas do mês
    if (dados.estatisticas_mes) {
        const e = dados.estatisticas_mes;
        contexto += `ESTATÍSTICAS DO MÊS (${e.MES_REFERENCIA || 'N/A'}):\n`;
        contexto += `- Dias com dados: ${e.DIAS_COM_DADOS || 0}\n`;
        contexto += `- Total de registros no mês: ${e.TOTAL_REGISTROS || 0}\n`;
        if (e.MEDIA_VAZAO_MES !== null && e.MEDIA_VAZAO_MES !== undefined) {
            contexto += `- Vazão média mensal: ${formatNum(e.MEDIA_VAZAO_MES)} L/s\n`;
            contexto += `- Vazão mínima: ${formatNum(e.MIN_VAZAO_MES)} L/s\n`;
            contexto += `- Vazão máxima: ${formatNum(e.MAX_VAZAO_MES)} L/s\n`;
        }
        if (e.MEDIA_PRESSAO_MES !== null && e.MEDIA_PRESSAO_MES !== undefined && parseFloat(e.MEDIA_PRESSAO_MES) > 0) {
            contexto += `- Pressão média mensal: ${formatNum(e.MEDIA_PRESSAO_MES)} mca\n`;
        }
        if (parseInt(e.TOTAL_EXTRAVASOU_MES) > 0) {
            contexto += `- Total de minutos com extravasamento: ${e.TOTAL_EXTRAVASOU_MES}\n`;
        }
        contexto += '\n';
    }
    
    // Histórico dos últimos 7 dias
    if (dados.historico_7dias && dados.historico_7dias.length > 0) {
        contexto += `HISTÓRICO DOS ÚLTIMOS 7 DIAS:\n`;
        contexto += `Data       | Registros | Média Vazão | Vazão Mín | Vazão Máx\n`;
        contexto += `-----------|-----------|-------------|-----------|----------\n`;
        
        dados.historico_7dias.forEach(d => {
            const data = d.DATA || 'N/A';
            const registros = d.QTD_REGISTROS || '0';
            const vazaoMedia = formatNum(d.MEDIA_VAZAO);
            const vazaoMin = formatNum(d.MIN_VAZAO);
            const vazaoMax = formatNum(d.MAX_VAZAO);
            contexto += `${data} | ${String(registros).padStart(9)} | ${vazaoMedia.padStart(11)} | ${vazaoMin.padStart(9)} | ${vazaoMax}\n`;
        });
        contexto += '\n';
    }
    
    // Média do mesmo dia da semana
    if (dados.media_mesmo_dia_semana && dados.media_mesmo_dia_semana.media_geral_vazao !== null) {
        const m = dados.media_mesmo_dia_semana;
        contexto += `COMPARATIVO - MÉDIA DO MESMO DIA DA SEMANA (últimas ${m.semanas_analisadas} semanas):\n`;
        contexto += `- Vazão média esperada: ${formatNum(m.media_geral_vazao)} L/s\n`;
        if (m.media_geral_pressao !== null && m.media_geral_pressao !== 0) {
            contexto += `- Pressão média esperada: ${formatNum(m.media_geral_pressao)} mca\n`;
        }
        contexto += '\n';
    }
    
    // ==========================================
    // HISTÓRICO DO MESMO DIA DA SEMANA (para cálculos)
    // ==========================================
    if (dados.historico_mesmo_dia && dados.historico_mesmo_dia.medias_por_dia) {
        const hist = dados.historico_mesmo_dia;
        const diasComDados = hist.medias_por_dia.filter(d => d.tem_dados);
        
        contexto += `========================================\n`;
        contexto += `HISTÓRICO DO MESMO DIA DA SEMANA (${hist.dia_semana}):\n`;
        contexto += `========================================\n`;
        contexto += `Semanas com dados disponíveis: ${hist.semanas_disponiveis}\n\n`;
        contexto += `DADOS POR SEMANA (use para calcular médias):\n`;
        
        hist.medias_por_dia.forEach((dia, idx) => {
            if (dia.tem_dados) {
                contexto += `  Semana ${idx + 1} (${dia.data_formatada}): ${formatNum(dia.media_vazao)} L/s\n`;
            }
        });
        
        contexto += `\nPARA CALCULAR MÉDIA DE X SEMANAS:\n`;
        contexto += `- Some as médias das X primeiras semanas com dados\n`;
        contexto += `- Divida pela quantidade de semanas somadas\n`;
        contexto += `- Apresente o cálculo detalhado quando solicitado\n`;
        contexto += `========================================\n\n`;
    }
    
    // ==========================================
    // HISTÓRICO POR HORA (para sugestões específicas)
    // ==========================================
    if (dados.historico_por_hora && dados.historico_por_hora.horas) {
        const histHora = dados.historico_por_hora;
        
        contexto += `========================================\n`;
        contexto += `ANÁLISE PARA SUGESTÃO DE VALORES:\n`;
        contexto += `========================================\n`;
        contexto += `Dia da semana: ${histHora.dia_semana}\n`;
        contexto += `Semanas analisadas: ${histHora.semanas_analisadas}\n\n`;
        
        // Fator de tendência
        const fator = histHora.fator_tendencia || 1;
        const tendenciaPct = histHora.tendencia_percentual || 0;
        const horasUsadas = histHora.horas_usadas_tendencia || 0;
        
        contexto += `FATOR DE TENDÊNCIA DO DIA ATUAL:\n`;
        if (horasUsadas >= 3) {
            const direcao = tendenciaPct >= 0 ? 'acima' : 'abaixo';
            contexto += `- Baseado em ${horasUsadas} horas com dados válidos\n`;
            contexto += `- O dia atual está ${Math.abs(tendenciaPct).toFixed(1)}% ${direcao} do padrão histórico\n`;
            contexto += `- Fator de ajuste: ${fator.toFixed(4)}\n\n`;
        } else {
            contexto += `- Dados insuficientes para calcular tendência (mínimo 3 horas)\n`;
            contexto += `- Usando fator = 1.0 (sem ajuste)\n\n`;
        }
        
        contexto += `FÓRMULA: valor_sugerido = média_histórica × fator_tendência\n\n`;
        
        contexto += `DADOS POR HORA:\n`;
        contexto += `Hora  | Média Histórica | Fator | Valor Sugerido | Valor Atual\n`;
        contexto += `------|-----------------|-------|----------------|------------\n`;
        
        for (let hora = 0; hora < 24; hora++) {
            const dadosHora = histHora.horas[hora];
            if (dadosHora && dadosHora.semanas_com_dados > 0) {
                const horaFmt = dadosHora.hora_formatada;
                const mediaHist = formatNum(dadosHora.media_historica);
                const valorSug = formatNum(dadosHora.valor_sugerido);
                const valorAtual = dadosHora.valor_dia_atual ? formatNum(dadosHora.valor_dia_atual) : '-';
                
                contexto += `${horaFmt} | ${mediaHist.padStart(15)} | ${fator.toFixed(2).padStart(5)} | ${valorSug.padStart(14)} | ${valorAtual}\n`;
            }
        }
        
        contexto += `\n`;
        contexto += `DETALHAMENTO POR SEMANA (últimas ${histHora.semanas_analisadas} semanas):\n`;
        
        for (let hora = 0; hora < 24; hora++) {
            const dadosHora = histHora.horas[hora];
            if (dadosHora && dadosHora.semanas_com_dados > 0) {
                contexto += `\nHORA ${dadosHora.hora_formatada}:\n`;
                dadosHora.valores_por_semana.forEach(sem => {
                    if (sem.tem_dados) {
                        contexto += `  - ${sem.data_formatada}: ${formatNum(sem.media_vazao)} L/s\n`;
                    }
                });
                contexto += `  → Média histórica: ${formatNum(dadosHora.media_historica)} L/s\n`;
                contexto += `  → Com ajuste (×${fator.toFixed(2)}): ${formatNum(dadosHora.valor_sugerido)} L/s\n`;
            }
        }
        contexto += `\n========================================\n\n`;
    }
    
    // Alertas detectados
    if (dados.alertas && dados.alertas.length > 0) {
        contexto += `ALERTAS DETECTADOS:\n`;
        dados.alertas.forEach(a => {
            const icone = a.severidade === 'alta' ? '🔴 CRÍTICO:' : (a.severidade === 'media' ? '🟡 ATENÇÃO:' : '🟢 INFO:');
            contexto += `${icone} ${a.mensagem}\n`;
        });
        contexto += '\n';
    }
    
    return contexto;
}

function escapeHtmlChat(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatarRespostaChat(texto) {
    // Verificar se há bloco de aplicar valores
    const regexAplicar = /\[APLICAR_VALORES\]([\s\S]*?)\[\/APLICAR_VALORES\]/;
    const matchAplicar = texto.match(regexAplicar);
    
    if (matchAplicar) {
        // Extrair os valores do bloco
        const blocoValores = matchAplicar[1].trim();
        const linhas = blocoValores.split('\n').filter(l => l.trim());
        
        // Parsear valores (formato: HH:00=VALOR)
        const valoresParaAplicar = [];
        linhas.forEach(linha => {
            const match = linha.match(/(\d{1,2}):00\s*=\s*([\d.]+)/);
            if (match) {
                valoresParaAplicar.push({
                    hora: parseInt(match[1]),
                    valor: parseFloat(match[2])
                });
            }
        });
        
        // Armazenar valores globalmente para uso no botão
        window.valoresIAParaAplicar = valoresParaAplicar;
        
        // Remover o bloco do texto e adicionar botão
        texto = texto.replace(regexAplicar, '');
        texto = texto.replace('Aguarde enquanto os dados são atualizados...', '');
        
        // Criar resumo dos valores
        let resumoHtml = '<div class="ia-valores-aplicar">';
        resumoHtml += '<strong>Valores a serem aplicados:</strong><br>';
        valoresParaAplicar.forEach(v => {
            resumoHtml += `• ${String(v.hora).padStart(2, '0')}:00 → <strong>${v.valor.toFixed(2)} L/s</strong><br>`;
        });
        resumoHtml += '<br><button class="btn-aplicar-valores-ia" onclick="aplicarValoresIA()">✓ Confirmar e Aplicar</button>';
        resumoHtml += ' <button class="btn-cancelar-valores-ia" onclick="cancelarValoresIA()">✗ Cancelar</button>';
        resumoHtml += '</div>';
        
        texto += resumoHtml;
    }
    
    // Escapar HTML (exceto o que acabamos de adicionar)
    const partes = texto.split(/(<div class="ia-valores-aplicar">[\s\S]*?<\/div>)/);
    texto = partes.map((parte, i) => {
        if (parte.startsWith('<div class="ia-valores-aplicar">')) {
            return parte; // Não escapar o HTML dos botões
        }
        return escapeHtmlChat(parte);
    }).join('');
    
    // Negrito **texto**
    texto = texto.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    
    // Itálico *texto*
    texto = texto.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    
    // Quebras de linha
    texto = texto.replace(/\n/g, '<br>');
    
    return texto;
}

/**
 * Aplica os valores sugeridos pela IA
 */
function aplicarValoresIA() {
    if (!window.valoresIAParaAplicar || window.valoresIAParaAplicar.length === 0) {
        showToast('Nenhum valor para aplicar', 'erro');
        return;
    }
    
    if (!validacaoPontoAtual || !validacaoDataAtual) {
        showToast('Dados do ponto não disponíveis', 'erro');
        return;
    }
    
    // Desabilitar botões
    const btnAplicar = document.querySelector('.btn-aplicar-valores-ia');
    const btnCancelar = document.querySelector('.btn-cancelar-valores-ia');
    if (btnAplicar) btnAplicar.disabled = true;
    if (btnCancelar) btnCancelar.disabled = true;
    
    const payload = {
        cdPonto: validacaoPontoAtual,
        data: validacaoDataAtual,
        tipoMedidor: validacaoTipoMedidorAtual || 1,
        valores: window.valoresIAParaAplicar,
        observacao: 'Valor sugerido e aplicado via IA'
    };
    
    fetch('bd/operacoes/validarDadosIA.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'sucesso');
            
            // Adicionar mensagem de sucesso no chat
            const mensagens = document.getElementById('iaChatMensagens');
            if (mensagens) {
                mensagens.innerHTML += `
                    <div class="ia-chat-msg ia">
                        <div class="ia-chat-avatar">
                            <ion-icon name="checkmark-circle"></ion-icon>
                        </div>
                        <div class="ia-chat-bubble" style="background:#dcfce7;border-color:#bbf7d0;color:#166534;">
                            ✓ ${data.message}
                        </div>
                    </div>
                `;
                mensagens.scrollTop = mensagens.scrollHeight;
            }
            
            // Remover botões
            const divBotoes = document.querySelector('.ia-valores-aplicar');
            if (divBotoes) {
                divBotoes.innerHTML = '<span style="color:#166534;">✓ Valores aplicados com sucesso!</span>';
            }
            
            // Limpar cache da IA
            dadosCompletosIA = null;
            
            // Desmarcar checkboxes e limpar seleção
            limparSelecaoHoras();
            
            // Recarregar dados do modal
            if (typeof carregarDadosHorarios === 'function') {
                carregarDadosHorarios(validacaoPontoAtual, validacaoDataAtual);
            }
            
            // Limpar valores pendentes
            window.valoresIAParaAplicar = null;
            
        } else {
            showToast(data.message || 'Erro ao aplicar valores', 'erro');
            if (btnAplicar) btnAplicar.disabled = false;
            if (btnCancelar) btnCancelar.disabled = false;
        }
    })
    .catch(error => {
        console.error('Erro ao aplicar valores:', error);
        showToast('Erro ao aplicar valores: ' + error.message, 'erro');
        if (btnAplicar) btnAplicar.disabled = false;
        if (btnCancelar) btnCancelar.disabled = false;
    });
}

/**
 * Cancela a aplicação dos valores
 */
function cancelarValoresIA() {
    window.valoresIAParaAplicar = null;
    
    const divBotoes = document.querySelector('.ia-valores-aplicar');
    if (divBotoes) {
        divBotoes.innerHTML = '<span style="color:#991b1b;">✗ Operação cancelada</span>';
    }
    
    // Adicionar mensagem no chat
    const mensagens = document.getElementById('iaChatMensagens');
    if (mensagens) {
        mensagens.innerHTML += `
            <div class="ia-chat-msg ia">
                <div class="ia-chat-avatar">
                    <ion-icon name="close-circle"></ion-icon>
                </div>
                <div class="ia-chat-bubble" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">
                    Operação cancelada. Os dados não foram alterados.
                </div>
            </div>
        `;
        mensagens.scrollTop = mensagens.scrollHeight;
    }
}

function limparChatIA() {
    const mensagens = document.getElementById('iaChatMensagens');
    const sugestoes = document.getElementById('iaChatSugestoes');
    if (mensagens) mensagens.innerHTML = '';
    if (sugestoes) sugestoes.style.display = 'flex';
    dadosCompletosIA = null; // Limpar cache de dados do banco
    iaChatHistorico = []; // Limpar histórico de conversa
}

function formatarNumero(valor) {
    if (valor === null || valor === undefined) return '-';
    return parseFloat(valor).toLocaleString('pt-BR', { 
        minimumFractionDigits: 2,
        maximumFractionDigits: 2 
    });
}

// =====================================================
// MODAL DE ANÁLISE IA
// =====================================================

let estruturaAnaliseIA = null;
let analiseIACache = {};

/**
 * Abre o modal de análise IA
 */
function abrirModalAnaliseIA() {
    const tipoId = document.getElementById('selectTipoEntidade').value;
    const valorId = document.getElementById('selectValorEntidade').value;
    
    if (!tipoId || !valorId) {
        showToast('Selecione o tipo e a unidade operacional primeiro', 'aviso');
        return;
    }
    
    // Verificar se temos o CD_ENTIDADE_VALOR_ID
    const valorEntidadeId = valorEntidadeIdSelecionado || document.getElementById('valorEntidadeIdHidden').value || valoresEntidadeMap[valorId];
    
    if (!valorEntidadeId) {
        showToast('Não foi possível identificar a unidade operacional. Selecione novamente.', 'erro');
        return;
    }
    
    // Obter nomes selecionados para o subtítulo
    const selectTipo = document.getElementById('selectTipoEntidade');
    const selectValor = document.getElementById('selectValorEntidade');
    const tipoNome = selectTipo.options[selectTipo.selectedIndex]?.text || '-';
    const valorNome = selectValor.options[selectValor.selectedIndex]?.text || '-';
    
    // Preencher subtítulo
    const subtituloEl = document.getElementById('analiseIASubtitulo');
    if (subtituloEl) {
        subtituloEl.textContent = `${tipoNome} | ${valorNome}`;
    }
    
    // Mostrar modal
    document.getElementById('modalAnaliseIA').classList.add('active');
    
    // Carregar estrutura
    carregarEstruturaAnaliseIA();
}

/**
 * Fecha o modal de análise IA
 */
function fecharModalAnaliseIA() {
    document.getElementById('modalAnaliseIA').classList.remove('active');
}

/**
 * Carrega estrutura hierárquica para análise
 */
function carregarEstruturaAnaliseIA() {
    const body = document.getElementById('analiseIABody');
    const valorCdChave = document.getElementById('selectValorEntidade').value;
    
    // Tentar várias fontes para obter o CD_ENTIDADE_VALOR_ID
    let valorEntidadeId = valorEntidadeIdSelecionado 
        || document.getElementById('valorEntidadeIdHidden').value 
        || valoresEntidadeMap[valorCdChave]
        || '';
    
    if (!valorEntidadeId) {
        body.innerHTML = `
            <div class="analise-vazia">
                <ion-icon name="alert-circle-outline"></ion-icon>
                <p>Não foi possível identificar a unidade operacional</p>
            </div>
        `;
        return;
    }
    
    // Obter período filtrado
    let dataInicio, dataFim;
    const tipoPeriodo = document.querySelector('input[name="tipoPeriodo"]:checked')?.value || 'mes';
    
    if (tipoPeriodo === 'mes') {
        const ano = document.getElementById('selectAno').value;
        const mes = document.getElementById('selectMes').value;
        if (ano && mes) {
            dataInicio = `${ano}-${String(mes).padStart(2, '0')}-01`;
            const ultimoDia = new Date(ano, mes, 0).getDate();
            dataFim = `${ano}-${String(mes).padStart(2, '0')}-${String(ultimoDia).padStart(2, '0')}`;
        } else {
            const hoje = new Date();
            dataInicio = `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}-01`;
            const ultimoDia = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0).getDate();
            dataFim = `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}-${String(ultimoDia).padStart(2, '0')}`;
        }
    } else {
        dataInicio = document.getElementById('inputDataInicio').value || new Date().toISOString().split('T')[0];
        dataFim = document.getElementById('inputDataFim').value || dataInicio;
    }
    
    body.innerHTML = `
        <div class="analise-loading">
            <ion-icon name="sync-outline"></ion-icon>
            <span>Carregando estrutura...</span>
        </div>
    `;
    
    fetch(`bd/operacoes/getEstruturaAnaliseIA.php?valorEntidadeId=${encodeURIComponent(valorEntidadeId)}&dataInicio=${dataInicio}&dataFim=${dataFim}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                estruturaAnaliseIA = data.estrutura;
                renderizarEstruturaAnaliseIA(data.estrutura, dataInicio, dataFim);
            } else {
                body.innerHTML = `
                    <div class="analise-vazia">
                        <ion-icon name="alert-circle-outline"></ion-icon>
                        <p>${data.error || 'Erro ao carregar estrutura'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            body.innerHTML = `
                <div class="analise-vazia">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    <p>Erro de conexão: ${error.message}</p>
                </div>
            `;
        });
}

/**
 * Renderiza a estrutura hierárquica em acordeões
 */
function renderizarEstruturaAnaliseIA(estrutura, dataInicio, dataFim) {
    const body = document.getElementById('analiseIABody');
    
    if (!estrutura || estrutura.length === 0) {
        body.innerHTML = `
            <div class="analise-vazia">
                <ion-icon name="folder-open-outline"></ion-icon>
                <p>Nenhuma estrutura encontrada para esta unidade operacional</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    
    // Como geralmente temos apenas 1 tipo e 1 valor, já abrir expandido
    const abrirTipoAutomatico = estrutura.length === 1;
    
    estrutura.forEach((tipo, tipoIdx) => {
        const totalPontos = tipo.valores.reduce((acc, v) => acc + v.pontos.length, 0);
        const classeAbertoTipo = abrirTipoAutomatico ? ' aberto' : '';
        
        html += `
            <div class="acordeao-tipo${classeAbertoTipo}" id="acordeaoTipo${tipoIdx}">
                <div class="acordeao-tipo-header" onclick="toggleAcordeaoTipo(${tipoIdx})">
                    <ion-icon name="chevron-forward-outline" class="icone-toggle"></ion-icon>
                    <ion-icon name="folder-outline" class="icone-tipo"></ion-icon>
                    <h4>${tipo.nome}</h4>
                    <span class="badge-count">${totalPontos} pontos</span>
                </div>
                <div class="acordeao-tipo-content">
        `;
        
        // Se tipo está aberto e tem apenas 1 valor, também abrir o valor
        const abrirValorAutomatico = abrirTipoAutomatico && tipo.valores.length === 1;
        
        tipo.valores.forEach((valor, valorIdx) => {
            const classeAbertoValor = abrirValorAutomatico ? ' aberto' : '';
            
            html += `
                <div class="acordeao-valor${classeAbertoValor}" id="acordeaoValor${tipoIdx}_${valorIdx}">
                    <div class="acordeao-valor-header" onclick="toggleAcordeaoValor(${tipoIdx}, ${valorIdx})">
                        <ion-icon name="chevron-forward-outline" class="icone-toggle"></ion-icon>
                        <h5>${valor.nome}</h5>
                        <span class="badge-count">${valor.pontos.length}</span>
                    </div>
                    <div class="acordeao-valor-content">
            `;
            
            valor.pontos.forEach((ponto, pontoIdx) => {
                html += `
                    <div class="acordeao-ponto" id="acordeaoPonto${tipoIdx}_${valorIdx}_${pontoIdx}" 
                         data-cd-ponto="${ponto.cd}" 
                         data-data-inicio="${dataInicio}" 
                         data-data-fim="${dataFim}">
                        <div class="acordeao-ponto-header" onclick="toggleAcordeaoPonto(${tipoIdx}, ${valorIdx}, ${pontoIdx})">
                            <ion-icon name="chevron-forward-outline" class="icone-toggle"></ion-icon>
                            <ion-icon name="location-outline" class="icone-ponto"></ion-icon>
                            <div class="ponto-info">
                                <strong>${ponto.codigo}</strong>
                                <span>${ponto.nome}</span>
                            </div>
                        </div>
                        <div class="acordeao-ponto-content" id="pontoDatas${tipoIdx}_${valorIdx}_${pontoIdx}">
                            <div class="acordeao-ponto-loading">
                                <ion-icon name="sync-outline"></ion-icon>
                                Clique para carregar as datas...
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += `
                    </div>
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    });
    
    body.innerHTML = html;
}

/**
 * Toggle acordeão de tipo
 */
function toggleAcordeaoTipo(tipoIdx) {
    const el = document.getElementById(`acordeaoTipo${tipoIdx}`);
    el.classList.toggle('aberto');
}

/**
 * Toggle acordeão de valor
 */
function toggleAcordeaoValor(tipoIdx, valorIdx) {
    const el = document.getElementById(`acordeaoValor${tipoIdx}_${valorIdx}`);
    el.classList.toggle('aberto');
}

/**
 * Toggle acordeão de ponto (carrega datas sob demanda)
 */
function toggleAcordeaoPonto(tipoIdx, valorIdx, pontoIdx) {
    const el = document.getElementById(`acordeaoPonto${tipoIdx}_${valorIdx}_${pontoIdx}`);
    const container = document.getElementById(`pontoDatas${tipoIdx}_${valorIdx}_${pontoIdx}`);
    const cdPonto = el.dataset.cdPonto;
    const dataInicio = el.dataset.dataInicio;
    const dataFim = el.dataset.dataFim;
    
    // Toggle visual
    const estaAberto = el.classList.toggle('aberto');
    
    // Se abriu e ainda não carregou, carregar datas
    if (estaAberto && !el.dataset.carregado) {
        carregarDatasPonto(cdPonto, dataInicio, dataFim, container, el);
    }
}

/**
 * Carrega datas com dados de um ponto
 */
function carregarDatasPonto(cdPonto, dataInicio, dataFim, container, acordeaoEl) {
    container.innerHTML = `
        <div class="acordeao-ponto-loading">
            <ion-icon name="sync-outline"></ion-icon>
            Carregando datas...
        </div>
    `;
    
    fetch(`bd/operacoes/getDatasAnaliseIA.php?cdPonto=${cdPonto}&dataInicio=${dataInicio}&dataFim=${dataFim}`)
        .then(response => response.json())
        .then(data => {
            acordeaoEl.dataset.carregado = 'true';
            
            if (data.success && data.datas.length > 0) {
                let html = '<div class="lista-datas">';
                
                data.datas.forEach((d, idx) => {
                    const anomaliaHtml = d.temAnomalia ? 
                        `<span class="badge-anomalia">⚠️ ${d.anomalias.join(', ')}</span>` : '';
                    
                    html += `
                        <div class="data-item-wrapper" id="dataWrapper${cdPonto}_${idx}">
                            <div class="data-item" onclick="selecionarDataAnalise(${cdPonto}, '${d.data}', this, ${idx})">
                                <div class="data-info">
                                    <div class="data-texto">${d.dataFormatada} - ${d.diaSemana}</div>
                                    <div class="data-resumo">
                                        ${d.totalRegistros} registros | Média: ${d.mediaVazao} L/s
                                    </div>
                                </div>
                                <span class="status-badge ${d.status}">${d.percentualCompleto}%</span>
                                ${anomaliaHtml}
                                <ion-icon name="chevron-down-outline" class="icone-expandir"></ion-icon>
                            </div>
                            <div class="analise-area-inline" id="analiseInline${cdPonto}_${idx}" style="display:none;"></div>
                        </div>
                    `;
                });
                
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = `
                    <div class="acordeao-ponto-loading" style="color:#64748b;">
                        <ion-icon name="calendar-outline"></ion-icon>
                        Nenhum dado encontrado no período
                    </div>
                `;
            }
        })
        .catch(error => {
            container.innerHTML = `
                <div class="acordeao-ponto-loading" style="color:#dc2626;">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    Erro ao carregar: ${error.message}
                </div>
            `;
        });
}

/**
 * Seleciona uma data para análise (expande inline abaixo da data)
 */
function selecionarDataAnalise(cdPonto, data, elemento, idx) {
    const wrapper = document.getElementById(`dataWrapper${cdPonto}_${idx}`);
    const analiseArea = document.getElementById(`analiseInline${cdPonto}_${idx}`);
    const icone = elemento.querySelector('.icone-expandir');
    
    // Se já está expandido, fecha
    if (wrapper.classList.contains('expandido')) {
        wrapper.classList.remove('expandido');
        analiseArea.style.display = 'none';
        if (icone) icone.name = 'chevron-down-outline';
        return;
    }
    
    // Fechar outros expandidos do mesmo ponto
    const container = elemento.closest('.lista-datas');
    container.querySelectorAll('.data-item-wrapper.expandido').forEach(el => {
        el.classList.remove('expandido');
        el.querySelector('.analise-area-inline').style.display = 'none';
        const ic = el.querySelector('.icone-expandir');
        if (ic) ic.name = 'chevron-down-outline';
    });
    
    // Expandir este
    wrapper.classList.add('expandido');
    analiseArea.style.display = 'block';
    if (icone) icone.name = 'chevron-up-outline';
    
    analiseArea.innerHTML = `
        <div class="analise-area-loading">
            <ion-icon name="sync-outline"></ion-icon>
            Gerando análise...
        </div>
    `;
    
    // Scroll para área de análise
    setTimeout(() => {
        analiseArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 100);
    
    // Verificar cache
    const cacheKey = `${cdPonto}_${data}`;
    if (analiseIACache[cacheKey]) {
        renderizarAnaliseInline(analiseArea, analiseIACache[cacheKey]);
        return;
    }
    
    // Buscar análise
    fetch(`bd/operacoes/getAnaliseIA.php?cdPonto=${cdPonto}&data=${data}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                analiseIACache[cacheKey] = result;
                renderizarAnaliseInline(analiseArea, result);
            } else {
                analiseArea.innerHTML = `
                    <div class="analise-erro">
                        <ion-icon name="alert-circle-outline"></ion-icon>
                        ${result.error || 'Erro ao gerar análise'}
                    </div>
                `;
            }
        })
        .catch(error => {
            analiseArea.innerHTML = `
                <div class="analise-erro">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    Erro de conexão: ${error.message}
                </div>
            `;
        });
}

/**
 * Renderiza a análise na área
 */
function renderizarAnalise(container, data) {
    const resumo = data.resumo;
    
    // Função auxiliar para formatar número
    const fmt = (val) => {
        if (val === null || val === undefined) return '-';
        return parseFloat(val).toFixed(2);
    };
    
    // Determinar classes para métricas
    let variacaoClasse = '';
    if (resumo.variacaoHistorico !== null) {
        if (resumo.variacaoHistorico > 10) variacaoClasse = 'negativo';
        else if (resumo.variacaoHistorico < -10) variacaoClasse = 'alerta';
        else variacaoClasse = 'positivo';
    }
    
    let completudeClasse = 'positivo';
    if (resumo.percentualCompleto < 50) completudeClasse = 'negativo';
    else if (resumo.percentualCompleto < 90) completudeClasse = 'alerta';
    
    let html = `
        <div class="analise-area-header">
            <ion-icon name="sparkles-outline"></ion-icon>
            Análise IA - ${data.dataFormatada} (${data.diaSemana})
        </div>
        <div class="analise-metricas">
            <div class="metrica-item ${completudeClasse}">
                <div class="valor">${fmt(resumo.percentualCompleto)}%</div>
                <div class="label">Completude</div>
            </div>
            <div class="metrica-item">
                <div class="valor">${fmt(resumo.mediaDiaria)}</div>
                <div class="label">Média (L/s)</div>
            </div>
            <div class="metrica-item">
                <div class="valor">${fmt(resumo.minVazao)}</div>
                <div class="label">Mínima</div>
            </div>
            <div class="metrica-item">
                <div class="valor">${fmt(resumo.maxVazao)}</div>
                <div class="label">Máxima</div>
            </div>
    `;
    
    if (resumo.variacaoHistorico !== null) {
        const sinal = resumo.variacaoHistorico >= 0 ? '+' : '';
        html += `
            <div class="metrica-item ${variacaoClasse}">
                <div class="valor">${sinal}${fmt(resumo.variacaoHistorico)}%</div>
                <div class="label">vs Histórico</div>
            </div>
        `;
    }
    
    html += `
        </div>
        <div class="analise-acoes">
            <button type="button" class="btn-detectar-anomalias" onclick="detectarAnomalias(${data.ponto.cd}, '${data.data}', this)">
                <ion-icon name="warning-outline"></ion-icon>
                Detectar Anomalias
            </button>
        </div>
        <div class="analise-area-content">
            ${data.analiseIA}
        </div>
        <div class="anomalias-container" id="anomaliasContainer${data.ponto.cd}" style="display:none;"></div>
    `;
    
    container.innerHTML = html;
}

/**
 * Renderiza a análise inline (versão compacta abaixo da data)
 */
function renderizarAnaliseInline(container, data) {
    const resumo = data.resumo;
    const ponto = data.ponto;
    
    // Função auxiliar para formatar número
    const fmt = (val) => {
        if (val === null || val === undefined) return '-';
        return parseFloat(val).toFixed(2);
    };
    
    // Escapar strings para uso em onclick
    const pontoNomeEscapado = (ponto.nome || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    const pontoCodigoEscapado = (ponto.codigo || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    
    // Determinar classes para métricas
    let completudeClasse = 'positivo';
    if (resumo.percentualCompleto < 50) completudeClasse = 'negativo';
    else if (resumo.percentualCompleto < 90) completudeClasse = 'alerta';
    
    let variacaoHtml = '';
    if (resumo.variacaoHistorico !== null) {
        const sinal = resumo.variacaoHistorico >= 0 ? '+' : '';
        let variacaoClasse = 'positivo';
        if (resumo.variacaoHistorico > 10) variacaoClasse = 'negativo';
        else if (resumo.variacaoHistorico < -10) variacaoClasse = 'alerta';
        variacaoHtml = `
            <div class="metrica-inline ${variacaoClasse}">
                <span class="valor">${sinal}${fmt(resumo.variacaoHistorico)}%</span>
                <span class="label">vs Hist.</span>
            </div>
        `;
    }
    
    // Verificar se é medidor de pressão (tipo 4) - apenas visualização
    const apenasVisualizacao = ponto.tipoMedidor === 4;
    const btnValidarHtml = apenasVisualizacao ? '' : `
        <button type="button" class="btn-validar-inline" onclick="abrirModalValidacao(${ponto.cd}, '${data.data}', ${ponto.tipoMedidor}, '${pontoNomeEscapado}', '${pontoCodigoEscapado}')">
            <ion-icon name="create-outline"></ion-icon>
            Validar Dados
        </button>
    `;
    
    // Botão de anomalias abre modal de validação e executa análise
    const btnAnomaliasHtml = `
        <button type="button" class="btn-detectar-inline" onclick="abrirValidacaoComAnalise(${ponto.cd}, '${data.data}', ${ponto.tipoMedidor}, '${pontoNomeEscapado}', '${pontoCodigoEscapado}')">
            <ion-icon name="warning-outline"></ion-icon>
            Detectar Anomalias
        </button>
    `;
    
    let html = `
        <div class="analise-inline-metricas">
            <div class="metrica-inline ${completudeClasse}">
                <span class="valor">${fmt(resumo.percentualCompleto)}%</span>
                <span class="label">Complet.</span>
            </div>
            <div class="metrica-inline">
                <span class="valor">${fmt(resumo.mediaDiaria)}</span>
                <span class="label">Média</span>
            </div>
            <div class="metrica-inline">
                <span class="valor">${fmt(resumo.minVazao)}</span>
                <span class="label">Mín</span>
            </div>
            <div class="metrica-inline">
                <span class="valor">${fmt(resumo.maxVazao)}</span>
                <span class="label">Máx</span>
            </div>
            ${variacaoHtml}
        </div>
        <div class="analise-inline-acoes">
            ${btnValidarHtml}
            ${btnAnomaliasHtml}
        </div>
        <div class="analise-inline-texto">
            ${data.analiseIA}
        </div>
    `;
    
    container.innerHTML = html;
}

/**
 * Detecta anomalias (versão inline)
 */
function detectarAnomaliasInline(cdPonto, data, btnElement) {
    const containerId = `anomaliasInline${cdPonto}_${data.replace(/-/g, '')}`;
    const container = document.getElementById(containerId);
    
    // Desabilitar botão
    btnElement.disabled = true;
    btnElement.innerHTML = '<ion-icon name="sync-outline" style="animation: spin 1s linear infinite;"></ion-icon> Analisando...';
    
    // Mostrar container
    container.style.display = 'block';
    container.innerHTML = `
        <div class="anomalias-loading">
            <ion-icon name="hourglass-outline"></ion-icon>
            Analisando...
        </div>
    `;
    
    fetch(`bd/operacoes/detectarAnomaliasIA.php?cdPonto=${cdPonto}&data=${data}`)
        .then(response => response.json())
        .then(result => {
            btnElement.disabled = false;
            btnElement.innerHTML = '<ion-icon name="warning-outline"></ion-icon> Detectar Anomalias';
            
            if (result.success) {
                renderizarAnomaliasInline(container, result);
            } else {
                container.innerHTML = `<div class="analise-erro"><ion-icon name="alert-circle-outline"></ion-icon> ${result.error}</div>`;
            }
        })
        .catch(error => {
            btnElement.disabled = false;
            btnElement.innerHTML = '<ion-icon name="warning-outline"></ion-icon> Detectar Anomalias';
            container.innerHTML = `<div class="analise-erro"><ion-icon name="alert-circle-outline"></ion-icon> ${error.message}</div>`;
        });
}

/**
 * Renderiza anomalias (versão inline compacta)
 */
function renderizarAnomaliasInline(container, data) {
    const { resumo, anomaliasPorHora } = data;
    
    if (anomaliasPorHora.length === 0) {
        container.innerHTML = `
            <div class="anomalias-inline-ok">
                <ion-icon name="checkmark-circle"></ion-icon>
                Nenhuma anomalia detectada
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="anomalias-inline-header">
            <ion-icon name="warning"></ion-icon>
            ${resumo.totalAnomalias} anomalia${resumo.totalAnomalias > 1 ? 's' : ''} em ${resumo.horasComAnomalias} hora${resumo.horasComAnomalias > 1 ? 's' : ''}
        </div>
        <div class="anomalias-inline-lista">
    `;
    
    anomaliasPorHora.forEach(hora => {
        hora.anomalias.forEach(anomalia => {
            html += `
                <div class="anomalia-inline-item ${anomalia.tipo}">
                    <span class="hora">${hora.horaFormatada}</span>
                    <span class="msg">${anomalia.mensagem}</span>
                </div>
            `;
        });
    });
    
    html += '</div>';
    container.innerHTML = html;
}

/**
 * Detecta anomalias por hora
 */
function detectarAnomalias(cdPonto, data, btnElement) {
    const container = document.getElementById(`anomaliasContainer${cdPonto}`);
    
    // Desabilitar botão
    btnElement.disabled = true;
    btnElement.innerHTML = '<ion-icon name="sync-outline" style="animation: spin 1s linear infinite;"></ion-icon> Analisando...';
    
    // Mostrar container
    container.style.display = 'block';
    container.innerHTML = `
        <div class="anomalias-header">
            <ion-icon name="hourglass-outline"></ion-icon>
            Analisando dados hora a hora...
        </div>
    `;
    
    fetch(`bd/operacoes/detectarAnomaliasIA.php?cdPonto=${cdPonto}&data=${data}`)
        .then(response => response.json())
        .then(result => {
            btnElement.disabled = false;
            btnElement.innerHTML = '<ion-icon name="warning-outline"></ion-icon> Detectar Anomalias';
            
            if (result.success) {
                renderizarAnomalias(container, result);
            } else {
                container.innerHTML = `
                    <div class="anomalias-header" style="color:#dc2626;">
                        <ion-icon name="alert-circle-outline"></ion-icon>
                        Erro ao detectar anomalias
                    </div>
                    <p style="color:#dc2626; font-size:12px;">${result.error}</p>
                `;
            }
        })
        .catch(error => {
            btnElement.disabled = false;
            btnElement.innerHTML = '<ion-icon name="warning-outline"></ion-icon> Detectar Anomalias';
            container.innerHTML = `
                <div class="anomalias-header" style="color:#dc2626;">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    Erro de conexão
                </div>
                <p style="color:#dc2626; font-size:12px;">${error.message}</p>
            `;
        });
}

/**
 * Renderiza as anomalias detectadas
 */
function renderizarAnomalias(container, data) {
    const { resumo, anomaliasPorHora } = data;
    
    let html = `
        <div class="anomalias-header">
            <ion-icon name="warning-outline"></ion-icon>
            Anomalias Detectadas - ${data.dataFormatada}
        </div>
        <div class="anomalias-resumo">
            <span><ion-icon name="time-outline"></ion-icon> ${resumo.horasAnalisadas} horas analisadas</span>
            <span><ion-icon name="alert-circle-outline"></ion-icon> ${resumo.horasComAnomalias} horas com anomalias</span>
            <span><ion-icon name="warning-outline"></ion-icon> ${resumo.totalAnomalias} anomalias total</span>
        </div>
    `;
    
    if (anomaliasPorHora.length === 0) {
        html += `
            <div class="anomalias-vazio">
                <ion-icon name="checkmark-circle-outline"></ion-icon>
                Nenhuma anomalia detectada!<br>
                <small>Todos os dados estão dentro dos parâmetros normais.</small>
            </div>
        `;
    } else {
        html += '<div class="anomalias-lista">';
        
        anomaliasPorHora.forEach(hora => {
            html += `
                <div class="anomalia-hora-card">
                    <div class="anomalia-hora-header">
                        <ion-icon name="time-outline"></ion-icon>
                        ${hora.horaFormatada}
                        <span class="badge-count">${hora.totalAnomalias} anomalia${hora.totalAnomalias > 1 ? 's' : ''}</span>
                    </div>
            `;
            
            hora.anomalias.forEach(anomalia => {
                html += `
                    <div class="anomalia-item">
                        <div class="icone ${anomalia.tipo}">
                            <ion-icon name="${anomalia.icone}-outline"></ion-icon>
                        </div>
                        <div class="conteudo">
                            <div class="mensagem">${anomalia.mensagem}</div>
                            <div class="sugestao">💡 ${anomalia.sugestao}</div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        });
        
        html += '</div>';
    }
    
    container.innerHTML = html;
}
</script>

<?php include_once 'includes/footer.inc.php'; ?>
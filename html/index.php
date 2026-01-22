<?php
/**
 * SIMP - Sistema Integrado de Macromedicao e Pitometria
 * Dashboard de Metricas IA
 * 
 * Exibe metricas consolidadas da tabela IA_METRICAS_DIARIAS
 * Data base: MAX(DT_REFERENCIA) da tabela (nao GETDATE)
 * 
 * @author Bruno
 * @version 1.0
 * @date 2026-01-22
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// exigePermissaoTela('Análise dos Dados', ACESSO_LEITURA);

// Verifica permissao de acesso

// exigePermissaoTela('DASHBOARD METRICAS IA', ACESSO_LEITURA);

// Buscar unidades para filtro
$sqlUnidades = $pdoSIMP->query("
    SELECT DISTINCT U.CD_UNIDADE, U.DS_NOME 
    FROM SIMP.dbo.UNIDADE U
    INNER JOIN SIMP.dbo.LOCALIDADE L ON U.CD_UNIDADE = L.CD_UNIDADE
    INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON L.CD_CHAVE = PM.CD_LOCALIDADE
    ORDER BY U.DS_NOME
");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

// Buscar tipos de medidor
$tiposMedidor = [
    1 => 'Macromedidor',
    2 => 'Est. Pitometrica',
    4 => 'Pressao',
    6 => 'Nivel',
    8 => 'Hidrometro'
];

// Buscar ultima data disponivel
$sqlUltimaData = $pdoSIMP->query("SELECT MAX(DT_REFERENCIA) AS ULTIMA_DATA FROM SIMP.dbo.IA_METRICAS_DIARIAS");
$ultimaData = $sqlUltimaData->fetch(PDO::FETCH_ASSOC)['ULTIMA_DATA'] ?? date('Y-m-d');
?>

<style>
    /* ============================================
       Page Container
       ============================================ */
    .page-container {
        padding: 24px;
        max-width: 1600px;
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

    .header-data-ref {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        color: white;
    }

    .header-data-ref ion-icon {
        font-size: 24px;
    }

    .header-data-ref-label {
        font-size: 11px;
        opacity: 0.8;
        display: block;
    }

    .header-data-ref-value {
        font-size: 14px;
        font-weight: 600;
        display: block;
    }

    /* ============================================
       Stats Cards
       ============================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 24px;
    }

    @media (max-width: 1200px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 640px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }

    .stat-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 20px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
    }

    .stat-card.primary::before {
        background: linear-gradient(90deg, #3b82f6, #60a5fa);
    }

    .stat-card.success::before {
        background: linear-gradient(90deg, #10b981, #34d399);
    }

    .stat-card.warning::before {
        background: linear-gradient(90deg, #f59e0b, #fbbf24);
    }

    .stat-card.danger::before {
        background: linear-gradient(90deg, #ef4444, #f87171);
    }

    .stat-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }

    .stat-card-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    .stat-card.primary .stat-card-icon {
        background: #eff6ff;
        color: #3b82f6;
    }

    .stat-card.success .stat-card-icon {
        background: #ecfdf5;
        color: #10b981;
    }

    .stat-card.warning .stat-card-icon {
        background: #fffbeb;
        color: #f59e0b;
    }

    .stat-card.danger .stat-card-icon {
        background: #fef2f2;
        color: #ef4444;
    }

    .stat-card-value {
        font-size: 28px;
        font-weight: 700;
        color: #1e293b;
        line-height: 1;
        margin-bottom: 4px;
    }

    .stat-card-label {
        font-size: 13px;
        color: #64748b;
    }

    .stat-card-trend {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        margin-top: 8px;
    }

    .stat-card-trend.up {
        color: #10b981;
    }

    .stat-card-trend.down {
        color: #ef4444;
    }

    .stat-card-trend.neutral {
        color: #64748b;
    }

    /* ============================================
   Filters Card - Padrão do Sistema
   ============================================ */
    /* ============================================
   FIX: Select2 - Z-index e Posicionamento
   ============================================ */
    .filters-card {
        position: relative;
        z-index: 10;
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 16px;
        align-items: end;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
        position: relative;
    }

    /* Fix para Select2 dentro do grid */
    .select2-container {
        width: 100% !important;
        min-width: 0;
    }

    .select2-container--default .select2-selection--single {
        height: 44px;
        padding: 6px 12px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        display: flex;
        align-items: center;
        transition: all 0.2s ease;
        box-sizing: border-box;
    }

    .select2-container--default .select2-selection--single:hover {
        border-color: #cbd5e1;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 1;
        padding-left: 0;
        padding-right: 28px;
        color: #334155;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #94a3b8;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 100%;
        right: 10px;
        top: 0;
        display: flex;
        align-items: center;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #64748b transparent transparent transparent;
        border-width: 5px 5px 0 5px;
    }

    .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b {
        border-color: transparent transparent #64748b transparent;
        border-width: 0 5px 5px 5px;
    }

    .select2-container--default.select2-container--focus .select2-selection--single,
    .select2-container--default.select2-container--open .select2-selection--single {
        background-color: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        outline: none;
    }

    /* Dropdown do Select2 - Z-index alto para sobrepor outros elementos */
    .select2-dropdown {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        overflow: hidden;
        margin-top: 4px;
        z-index: 9999 !important;
    }

    .select2-container--open .select2-dropdown {
        z-index: 9999 !important;
    }

    .select2-container--open .select2-dropdown--below {
        border-top: 1px solid #e2e8f0;
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
    }

    /* Search dentro do dropdown */
    .select2-search--dropdown {
        padding: 10px;
    }

    .select2-search--dropdown .select2-search__field {
        padding: 10px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        font-family: inherit;
        outline: none;
        transition: all 0.2s ease;
        width: 100%;
        box-sizing: border-box;
    }

    .select2-search--dropdown .select2-search__field:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Resultados */
    .select2-results__options {
        max-height: 250px;
        overflow-y: auto;
    }

    .select2-container--default .select2-results__option {
        padding: 10px 14px;
        font-size: 14px;
        color: #334155;
        transition: background 0.15s ease;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #3b82f6;
        color: #ffffff;
    }

    .select2-container--default .select2-results__option[aria-selected="true"] {
        background-color: #eff6ff;
        color: #1e40af;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected="true"] {
        background-color: #3b82f6;
        color: #ffffff;
    }

    /* Mensagem de "nenhum resultado" */
    .select2-results__message {
        padding: 12px 14px;
        color: #64748b;
        font-size: 13px;
    }

    /* Clear button (X) */
    .select2-container--default .select2-selection--single .select2-selection__clear {
        color: #94a3b8;
        font-size: 18px;
        font-weight: normal;
        margin-right: 8px;
        cursor: pointer;
        position: relative;
        z-index: 1;
    }

    .select2-container--default .select2-selection--single .select2-selection__clear:hover {
        color: #ef4444;
    }

    /* Responsividade para filtros */
    @media (max-width: 1400px) {
        .filters-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 1024px) {
        .filters-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .filters-grid {
            grid-template-columns: 1fr;
        }

        .select2-container--default .select2-selection--single {
            height: 44px;
        }

        .select2-dropdown {
            border-radius: 8px;
        }
    }

    .filters-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }

    .filters-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 600;
        color: #1e293b;
    }

    .filters-title ion-icon {
        font-size: 18px;
        color: #3b82f6;
    }

    .btn-clear-filters {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        background: #f1f5f9;
        color: #64748b;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-clear-filters:hover {
        background: #e2e8f0;
        color: #475569;
    }

    .btn-clear-filters ion-icon {
        font-size: 16px;
    }



    .form-label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .form-label ion-icon {
        font-size: 14px;
        color: #94a3b8;
    }

    .form-control {
        width: 100%;
        height: 44px;
        padding: 10px 14px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-family: inherit;
        font-size: 14px;
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

    .btn-filtrar {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        height: 44px;
        padding: 0 20px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: #ffffff;
        border: none;
        border-radius: 10px;
        font-family: inherit;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-filtrar:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
    }

    .btn-filtrar ion-icon {
        font-size: 18px;
    }

    /* ============================================
   Select2 - Customização Padrão SIMP
   ============================================ */
    .select2-container {
        width: 100% !important;
    }

    .select2-container--default .select2-selection--single {
        height: 44px;
        padding: 6px 12px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        display: flex;
        align-items: center;
        transition: all 0.2s ease;
    }

    .select2-container--default .select2-selection--single:hover {
        border-color: #cbd5e1;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 1;
        padding-left: 0;
        padding-right: 28px;
        color: #334155;
    }

    .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #94a3b8;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px;
        right: 10px;
        top: 0;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #64748b transparent transparent transparent;
        border-width: 5px 5px 0 5px;
    }

    .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b {
        border-color: transparent transparent #64748b transparent;
        border-width: 0 5px 5px 5px;
    }

    .select2-container--default.select2-container--focus .select2-selection--single,
    .select2-container--default.select2-container--open .select2-selection--single {
        background-color: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        outline: none;
    }

    /* Dropdown */
    .select2-dropdown {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
        overflow: hidden;
        margin-top: 4px;
    }

    .select2-container--open .select2-dropdown--below {
        border-top: 1px solid #e2e8f0;
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
    }

    /* Search dentro do dropdown */
    .select2-search--dropdown {
        padding: 10px;
    }

    .select2-search--dropdown .select2-search__field {
        padding: 10px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        font-family: inherit;
        outline: none;
        transition: all 0.2s ease;
    }

    .select2-search--dropdown .select2-search__field:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Resultados */
    .select2-results__options {
        max-height: 250px;
    }

    .select2-container--default .select2-results__option {
        padding: 10px 14px;
        font-size: 14px;
        color: #334155;
        transition: background 0.15s ease;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #3b82f6;
        color: #ffffff;
    }

    .select2-container--default .select2-results__option[aria-selected="true"] {
        background-color: #eff6ff;
        color: #1e40af;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected="true"] {
        background-color: #3b82f6;
        color: #ffffff;
    }

    /* Mensagem de "nenhum resultado" */
    .select2-results__message {
        padding: 12px 14px;
        color: #64748b;
        font-size: 13px;
    }

    /* Clear button (X) */
    .select2-container--default .select2-selection--single .select2-selection__clear {
        color: #94a3b8;
        font-size: 18px;
        font-weight: normal;
        margin-right: 8px;
        cursor: pointer;
    }

    .select2-container--default .select2-selection--single .select2-selection__clear:hover {
        color: #ef4444;
    }

    /* Loading */
    .select2-container--default .select2-results__option.loading-results {
        padding: 12px 14px;
        color: #64748b;
    }

    /* Responsividade */
    @media (max-width: 768px) {
        .select2-container--default .select2-selection--single {
            height: 44px;
        }

        .select2-dropdown {
            border-radius: 8px;
        }
    }

    /* ============================================
   Responsividade
   ============================================ */
    @media (max-width: 1400px) {
        .filters-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 1024px) {
        .filters-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .filters-card {
            padding: 16px;
            border-radius: 12px;
        }

        .filters-header {
            flex-direction: column;
            gap: 12px;
            align-items: stretch;
        }

        .btn-clear-filters {
            width: 100%;
            justify-content: center;
        }

        .filters-grid {
            grid-template-columns: 1fr;
        }

        .select2-container {
            width: 100% !important;
        }
    }

    /* ============================================
       Content Card
       ============================================ */
    .content-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        margin-bottom: 24px;
    }

    .content-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .content-card-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 600;
        color: #1e293b;
    }

    .content-card-title ion-icon {
        font-size: 18px;
        color: #3b82f6;
    }

    .content-card-body {
        padding: 0;
    }

    /* ============================================
       Tabela de Dados
       ============================================ */
    .tabela-container {
        overflow-x: auto;
    }

    .tabela-metricas {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .tabela-metricas th {
        background: #f8fafc;
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        color: #475569;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
        position: sticky;
        top: 0;
    }

    .tabela-metricas td {
        padding: 12px 16px;
        border-bottom: 1px solid #f1f5f9;
        color: #1e293b;
        vertical-align: middle;
    }

    .tabela-metricas tbody tr:hover {
        background: #f8fafc;
    }

    .tabela-metricas tbody tr.critico {
        background: #fef2f2;
    }

    .tabela-metricas tbody tr.atencao {
        background: #fffbeb;
    }

    /* Status Badge */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 100px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-badge.ok {
        background: #ecfdf5;
        color: #059669;
    }

    .status-badge.atencao {
        background: #fffbeb;
        color: #d97706;
    }

    .status-badge.critico {
        background: #fef2f2;
        color: #dc2626;
    }

    /* Cobertura */
    .cobertura-bar {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .cobertura-bar-track {
        flex: 1;
        height: 8px;
        background: #e2e8f0;
        border-radius: 4px;
        overflow: hidden;
        min-width: 60px;
    }

    .cobertura-bar-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.3s;
    }

    .cobertura-bar-fill.alta {
        background: #10b981;
    }

    .cobertura-bar-fill.media {
        background: #f59e0b;
    }

    .cobertura-bar-fill.baixa {
        background: #ef4444;
    }

    .cobertura-value {
        font-size: 12px;
        font-weight: 600;
        min-width: 40px;
        text-align: right;
    }

    /* Ponto Info */
    .ponto-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .ponto-nome {
        font-weight: 600;
        color: #1e293b;
    }

    .ponto-tipo {
        font-size: 11px;
        color: #64748b;
    }

    /* Tendencia */
    .tendencia {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
    }

    .tendencia.subindo {
        color: #10b981;
    }

    .tendencia.descendo {
        color: #ef4444;
    }

    .tendencia.estavel {
        color: #64748b;
    }

    /* Flags */
    .flags-container {
        display: flex;
        gap: 4px;
    }

    .flag-icon {
        width: 20px;
        height: 20px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
    }

    .flag-icon.ativo {
        background: #fef2f2;
        color: #dc2626;
    }

    .flag-icon.inativo {
        background: #f1f5f9;
        color: #cbd5e1;
    }

    /* Acoes */
    .btn-acao {
        padding: 6px 10px;
        background: #eff6ff;
        color: #3b82f6;
        border: none;
        border-radius: 6px;
        font-size: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 4px;
        transition: background 0.2s;
    }

    .btn-acao:hover {
        background: #dbeafe;
    }

    /* ============================================
       Dashboard Grid
       ============================================ */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
    }

    @media (max-width: 1200px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }

    /* ============================================
       Grafico Container
       ============================================ */
    .grafico-container {
        padding: 20px;
        min-height: 300px;
    }

    /* ============================================
       Lista Ranking
       ============================================ */
    .ranking-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .ranking-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.2s;
    }

    .ranking-item:hover {
        background: #f8fafc;
    }

    .ranking-item:last-child {
        border-bottom: none;
    }

    .ranking-position {
        width: 28px;
        height: 28px;
        background: #f1f5f9;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
    }

    .ranking-item:nth-child(1) .ranking-position {
        background: #fef3c7;
        color: #d97706;
    }

    .ranking-item:nth-child(2) .ranking-position {
        background: #e2e8f0;
        color: #475569;
    }

    .ranking-item:nth-child(3) .ranking-position {
        background: #fed7aa;
        color: #c2410c;
    }

    .ranking-info {
        flex: 1;
        min-width: 0;
    }

    .ranking-nome {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ranking-detalhe {
        font-size: 11px;
        color: #64748b;
    }

    .ranking-valor {
        font-size: 14px;
        font-weight: 700;
    }

    .ranking-valor.critico {
        color: #dc2626;
    }

    .ranking-valor.atencao {
        color: #d97706;
    }

    .ranking-valor.ok {
        color: #059669;
    }

    /* ============================================
       Loading e Empty State
       ============================================ */
    .loading-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        color: #64748b;
    }

    .loading-container ion-icon {
        font-size: 48px;
        margin-bottom: 16px;
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

    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        color: #64748b;
    }

    .empty-state ion-icon {
        font-size: 48px;
        margin-bottom: 16px;
        opacity: 0.5;
    }

    .empty-state h3 {
        font-size: 16px;
        font-weight: 600;
        color: #475569;
        margin: 0 0 8px 0;
    }

    .empty-state p {
        font-size: 13px;
        margin: 0;
    }

    /* ============================================
       Select2 Customizacao
       ============================================ */
    .select2-container--default .select2-selection--single {
        height: 40px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 38px;
        padding-left: 12px;
        color: #1e293b;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 38px;
    }

    .select2-dropdown {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background: #3b82f6;
    }

    /* ============================================
       Responsividade
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
            text-align: center;
        }

        .page-header-info {
            flex-direction: column;
        }

        .filtros-grid {
            grid-template-columns: 1fr;
        }

        .content-card-header {
            flex-direction: column;
            gap: 12px;
            align-items: flex-start;
        }

        .tabela-metricas th,
        .tabela-metricas td {
            padding: 10px 12px;
        }
    }

    /* ============================================
   Badges de Tipo de Medidor
   ============================================ */
    .badge-tipo-medidor {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }

    /* Macromedidor (1) - Azul */
    .badge-tipo-medidor.tipo-1 {
        background: #dbeafe;
        color: #1d4ed8;
        border: 1px solid #93c5fd;
    }

    /* Estação Pitométrica (2) - Verde */
    .badge-tipo-medidor.tipo-2 {
        background: #dcfce7;
        color: #15803d;
        border: 1px solid #86efac;
    }

    /* Pressão (4) - Laranja */
    .badge-tipo-medidor.tipo-4 {
        background: #ffedd5;
        color: #c2410c;
        border: 1px solid #fdba74;
    }

    /* Nível Reservatório (6) - Roxo */
    .badge-tipo-medidor.tipo-6 {
        background: #f3e8ff;
        color: #7e22ce;
        border: 1px solid #d8b4fe;
    }

    /* Hidrômetro (8) - Ciano */
    .badge-tipo-medidor.tipo-8 {
        background: #cffafe;
        color: #0e7490;
        border: 1px solid #67e8f9;
    }

    /* Ícones dos tipos */
    .badge-tipo-medidor ion-icon {
        font-size: 12px;
    }

    /* Ajuste no ponto-info para acomodar o badge */
    .ponto-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .ponto-info-header {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .ponto-nome {
        font-weight: 600;
        color: #1e293b;
    }

    .ponto-descricao {
        font-size: 11px;
        color: #64748b;
    }

    /* Ranking - badge menor */
    .ranking-item .badge-tipo-medidor {
        font-size: 9px;
        padding: 2px 6px;
    }

    .ranking-info {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .ranking-header {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    /* ============================================
   Colunas Ordenáveis na Tabela
   ============================================ */
    .tabela-metricas th.sortable {
        cursor: pointer;
        user-select: none;
        transition: all 0.2s ease;
        position: relative;
    }

    .tabela-metricas th.sortable:hover {
        background: #e2e8f0;
        color: #1e293b;
    }

    .tabela-metricas th.sortable ion-icon {
        font-size: 14px;
        margin-left: 4px;
        opacity: 0.4;
        vertical-align: middle;
        transition: all 0.2s ease;
    }

    .tabela-metricas th.sortable:hover ion-icon {
        opacity: 0.7;
    }

    .tabela-metricas th.sortable.asc ion-icon,
    .tabela-metricas th.sortable.desc ion-icon {
        opacity: 1;
        color: #3b82f6;
    }

    .tabela-metricas th.sortable.asc ion-icon {
        transform: rotate(180deg);
    }

    .tabela-metricas th.sortable.desc ion-icon {
        transform: rotate(0deg);
    }

    /* ============================================
   FIX CRÍTICO: Select2 Dropdowns
   ============================================ */

    /* Reset completo do Select2 */
    .filters-card .select2-container {
        width: 100% !important;
        min-width: 0;
        box-sizing: border-box;
    }

    .filters-card .select2-container--default .select2-selection--single {
        height: 44px;
        padding: 0 12px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        display: flex;
        align-items: center;
        box-sizing: border-box;
        position: relative;
    }

    .filters-card .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #334155;
        line-height: 1;
        padding-left: 0;
        padding-right: 30px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .filters-card .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #94a3b8;
    }

    .filters-card .select2-container--default .select2-selection--single .select2-selection__arrow {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        height: auto;
        width: auto;
    }

    .filters-card .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #64748b transparent transparent transparent;
        border-style: solid;
        border-width: 5px 4px 0 4px;
        margin: 0;
        position: static;
    }

    .filters-card .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b {
        border-color: transparent transparent #64748b transparent;
        border-width: 0 4px 5px 4px;
    }

    /* Focus state */
    .filters-card .select2-container--default.select2-container--focus .select2-selection--single,
    .filters-card .select2-container--default.select2-container--open .select2-selection--single {
        background-color: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        outline: none;
    }

    /* Dropdown */
    .select2-dropdown {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        margin-top: 4px;
        z-index: 9999 !important;
        overflow: hidden;
    }

    .select2-container--open .select2-dropdown {
        z-index: 9999 !important;
    }

    .select2-search--dropdown .select2-search__field {
        padding: 10px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        outline: none;
    }

    .select2-search--dropdown .select2-search__field:focus {
        border-color: #3b82f6;
    }

    .select2-results__options {
        max-height: 250px;
        overflow-y: auto;
    }

    .select2-container--default .select2-results__option {
        padding: 10px 14px;
        font-size: 14px;
        color: #334155;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #3b82f6;
        color: #ffffff;
    }

    .select2-container--default .select2-results__option[aria-selected="true"] {
        background-color: #eff6ff;
        color: #1e40af;
    }

    /* Card Info (Tratamento Manual) */
    .stat-card.info::before {
        background: linear-gradient(90deg, #8b5cf6, #a78bfa);
    }

    .stat-card.info .stat-card-icon {
        background: #f5f3ff;
        color: #8b5cf6;
    }

    /* Ajuste do grid para 5 cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 20px;
    }

    @media (max-width: 1400px) {
        .stats-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 900px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 600px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Badge de tratamento */
    .ranking-tratados-badge {
        background: #f5f3ff;
        color: #8b5cf6;
        width: 28px;
        height: 28px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }

    .tratados-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
    }

    .tratados-badge.alto {
        background: #fef2f2;
        color: #dc2626;
    }

    .tratados-badge.medio {
        background: #fffbeb;
        color: #d97706;
    }

    .tratados-badge.baixo {
        background: #f5f3ff;
        color: #8b5cf6;
    }

    /* ============================================
   Dashboard Grid - Novo Layout
   ============================================ */
    .dashboard-grid-novo {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
        align-items: stretch;
        margin-bottom: 24px;
    }

    .dashboard-col-left {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .dashboard-col-right {
        display: flex;
        flex-direction: column;
    }

    /* Card com altura total (Pontos Críticos) */
    .dashboard-col-right .card-full-height {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .dashboard-col-right .card-full-height .content-card-body {
        flex: 1;
        overflow-y: auto;
        max-height: none;
    }

    /* Ajuste para lista de ranking preencher o espaço */
    .dashboard-col-right .ranking-list {
        min-height: 100%;
    }

    /* Responsividade */
    @media (max-width: 1200px) {
        .dashboard-grid-novo {
            grid-template-columns: 1fr;
        }

        .dashboard-col-right .card-full-height {
            max-height: 400px;
        }

        .dashboard-col-right .card-full-height .content-card-body {
            max-height: 340px;
        }
    }
</style>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="analytics-outline"></ion-icon>
                </div>
                <div>
                    <h1>Dashboard Metricas IA</h1>
                    <p class="page-header-subtitle">Monitoramento inteligente dos pontos de medicao</p>
                </div>
            </div>
            <div class="header-data-ref">
                <ion-icon name="calendar-outline"></ion-icon>
                <div>
                    <span class="header-data-ref-label">Ultima atualizacao</span>
                    <span class="header-data-ref-value"
                        id="dataReferencia"><?= date('d/m/Y', strtotime($ultimaData)) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid" id="statsGrid">
        <div class="stat-card primary" onclick="filtrarPorStatus('')">
            <div class="stat-card-header">
                <div class="stat-card-icon">
                    <ion-icon name="speedometer-outline"></ion-icon>
                </div>
            </div>
            <div class="stat-card-value" id="statTotal">-</div>
            <div class="stat-card-label">Total de Pontos</div>
            <div class="stat-card-trend neutral" id="statTotalTrend">
                <ion-icon name="remove-outline"></ion-icon>
                <span>Carregando...</span>
            </div>
        </div>

        <div class="stat-card success" onclick="filtrarPorStatus('OK')">
            <div class="stat-card-header">
                <div class="stat-card-icon">
                    <ion-icon name="checkmark-circle-outline"></ion-icon>
                </div>
            </div>
            <div class="stat-card-value" id="statOk">-</div>
            <div class="stat-card-label">Status OK</div>
            <div class="stat-card-trend up" id="statOkTrend">
                <ion-icon name="trending-up-outline"></ion-icon>
                <span>-</span>
            </div>
        </div>

        <div class="stat-card warning" onclick="filtrarPorStatus('ATENCAO')">
            <div class="stat-card-header">
                <div class="stat-card-icon">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                </div>
            </div>
            <div class="stat-card-value" id="statAtencao">-</div>
            <div class="stat-card-label">Atencao</div>
            <div class="stat-card-trend neutral" id="statAtencaoTrend">
                <ion-icon name="remove-outline"></ion-icon>
                <span>-</span>
            </div>
        </div>

        <div class="stat-card danger" onclick="filtrarPorStatus('CRITICO')">
            <div class="stat-card-header">
                <div class="stat-card-icon">
                    <ion-icon name="warning-outline"></ion-icon>
                </div>
            </div>
            <div class="stat-card-value" id="statCritico">-</div>
            <div class="stat-card-label">Criticos</div>
            <div class="stat-card-trend down" id="statCriticoTrend">
                <ion-icon name="trending-down-outline"></ion-icon>
                <span>-</span>
            </div>
        </div>

        <div class="stat-card info" onclick="mostrarPontosTratados()">
            <div class="stat-card-header">
                <div class="stat-card-icon">
                    <ion-icon name="construct-outline"></ion-icon>
                </div>
            </div>
            <div class="stat-card-value" id="statTratados">-</div>
            <div class="stat-card-label">Tratamento Manual</div>
            <div class="stat-card-trend neutral" id="statTratadosTrend">
                <ion-icon name="hand-right-outline"></ion-icon>
                <span>Registros com ID_SITUACAO = 2</span>
            </div>
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

        <div class="filters-grid">
            <!-- Período -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Período
                </label>
                <select id="filtroPeriodo" class="form-control">
                    <option value="1">Último dia</option>
                    <option value="7" selected>Últimos 7 dias</option>
                    <option value="15">Últimos 15 dias</option>
                    <option value="30">Últimos 30 dias</option>
                </select>
            </div>

            <!-- Unidade -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="business-outline"></ion-icon>
                    Unidade
                </label>
                <select id="filtroUnidade" class="form-control select2-default">
                    <option value="">Todas as Unidades</option>
                    <?php foreach ($unidades as $u): ?>
                        <option value="<?= $u['CD_UNIDADE'] ?>">
                            <?= htmlspecialchars($u['DS_NOME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Tipo Medidor -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="hardware-chip-outline"></ion-icon>
                    Tipo Medidor
                </label>
                <select id="filtroTipo" class="form-control select2-default">
                    <option value="">Todos os Tipos</option>
                    <?php foreach ($tiposMedidor as $id => $nome): ?>
                        <option value="<?= $id ?>"><?= $nome ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="pulse-outline"></ion-icon>
                    Status
                </label>
                <select id="filtroStatus" class="form-control">
                    <option value="">Todos</option>
                    <option value="OK">OK</option>
                    <option value="ATENCAO">Atenção</option>
                    <option value="CRITICO">Crítico</option>
                </select>
            </div>

            <!-- Exibir -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="list-outline"></ion-icon>
                    Exibir
                </label>
                <select id="filtroLimite" class="form-control">
                    <option value="50">50 registros</option>
                    <option value="100" selected>100 registros</option>
                    <option value="200">200 registros</option>
                    <option value="500">500 registros</option>
                </select>
            </div>

            <!-- Botão Filtrar -->
            <div class="form-group">
                <label class="form-label">&nbsp;</label>
                <button type="button" class="btn-filtrar" onclick="carregarDados()">
                    <ion-icon name="search-outline"></ion-icon>
                    Filtrar
                </button>
            </div>
        </div>
        <!-- Legenda de Tipos -->
        <div class="legenda-tipos"
            style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0;">
            <span style="font-size: 12px; color: #64748b; margin-right: 8px;">Tipos:</span>
            <span class="badge-tipo-medidor tipo-1"><ion-icon name="speedometer-outline"></ion-icon> Macro</span>
            <span class="badge-tipo-medidor tipo-2"><ion-icon name="pulse-outline"></ion-icon> Pito</span>
            <span class="badge-tipo-medidor tipo-4"><ion-icon name="thermometer-outline"></ion-icon> Pressão</span>
            <span class="badge-tipo-medidor tipo-6"><ion-icon name="water-outline"></ion-icon> Nível</span>
            <span class="badge-tipo-medidor tipo-8"><ion-icon name="watch-outline"></ion-icon> Hidro</span>
        </div>
    </div>



    <!-- Dashboard Grid - NOVO LAYOUT -->
    <div class="dashboard-grid-novo">
        <!-- Coluna Esquerda: Gráfico + Maior Esforço -->
        <div class="dashboard-col-left">
            <!-- Gráfico -->
            <div class="content-card">
                <div class="content-card-header">
                    <div class="content-card-title">
                        <ion-icon name="trending-up-outline"></ion-icon>
                        Evolucao da Cobertura
                    </div>
                </div>
                <div class="content-card-body">
                    <div class="grafico-container">
                        <canvas id="graficoEvolucao"></canvas>
                    </div>
                </div>
            </div>

            <!-- Ranking Tratamento Manual (abaixo do gráfico) -->
            <div class="content-card">
                <div class="content-card-header">
                    <div class="content-card-title">
                        <ion-icon name="construct-outline"></ion-icon>
                        Maior Esforco Operacional
                    </div>
                    <span class="badge"
                        style="background:#f5f3ff;color:#8b5cf6;font-size:11px;padding:4px 8px;border-radius:6px;">
                        ID_SITUACAO = 2
                    </span>
                </div>
                <div class="content-card-body">
                    <ul class="ranking-list" id="rankingTratados">
                        <li class="loading-container">
                            <ion-icon name="sync-outline"></ion-icon>
                            <span>Carregando...</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Coluna Direita: Pontos Críticos (altura alinhada) -->
        <div class="dashboard-col-right">
            <div class="content-card card-full-height">
                <div class="content-card-header">
                    <div class="content-card-title">
                        <ion-icon name="warning-outline"></ion-icon>
                        Pontos Criticos
                    </div>
                </div>
                <div class="content-card-body">
                    <ul class="ranking-list" id="rankingCriticos">
                        <li class="loading-container">
                            <ion-icon name="sync-outline"></ion-icon>
                            <span>Carregando...</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela de Dados -->
    <div class="content-card">
        <div class="content-card-header">
            <div class="content-card-title">
                <ion-icon name="list-outline"></ion-icon>
                Detalhamento por Ponto
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn-acao" onclick="exportarCSV()">
                    <ion-icon name="download-outline"></ion-icon>
                    Exportar
                </button>
            </div>
        </div>
        <div class="content-card-body">
            <div class="tabela-container">
                <table class="tabela-metricas">
                    <thead>
                        <tr>
                            <th class="sortable" data-column="DS_NOME">
                                Ponto <ion-icon name="swap-vertical-outline"></ion-icon>
                            </th>
                            <th class="sortable" data-column="DT_REFERENCIA">
                                Data <ion-icon name="swap-vertical-outline"></ion-icon>
                            </th>
                            <th class="sortable" data-column="PERC_COBERTURA">
                                Cobertura <ion-icon name="swap-vertical-outline"></ion-icon>
                            </th>
                            <th class="sortable" data-column="VL_MEDIA">
                                Media <ion-icon name="swap-vertical-outline"></ion-icon>
                            </th>
                            <th class="sortable" data-column="VL_MEDIA_HIST_4SEM">
                                Med 4 Sem <ion-icon name="swap-vertical-outline"></ion-icon>
                            </th>
                            <th class="sortable" data-column="VL_DESVIO_HIST_PERC">
                                Desvio % <ion-icon name="swap-vertical-outline"></ion-icon>
                            </th>
                            <th class="sortable" data-column="VL_TENDENCIA_7D">
                                Tendencia <ion-icon name="swap-vertical-outline"></ion-icon>
                            </th>
                            <th>Flags</th>
                            <th class="sortable" data-column="DS_STATUS">
                                Status <ion-icon name="swap-vertical-outline"></ion-icon>
                            </th>
                            <th title="Registros que necessitaram tratamento manual (ID_SITUACAO = 2)">
                                <ion-icon name="construct-outline"></ion-icon> Tratados
                            </th>
                            <th>Acoes</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaBody">
                        <tr>
                            <td colspan="10">
                                <div class="loading-container">
                                    <ion-icon name="sync-outline"></ion-icon>
                                    <span>Carregando dados...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    /**
  * ============================================
  * SIMP - Dashboard Métricas IA
  * JavaScript Atualizado com Tratamento Manual
  * ============================================
  * 
  * SUBSTITUIR as funções existentes no index.php
  * pelas versões abaixo.
  */

    // Variáveis globais (manter as existentes)
    let dadosAtuais = [];
    let graficoEvolucao = null;
    let colunaOrdenacao = null;
    let direcaoOrdenacao = 'asc';

    const tiposMedidor = {
        1: { nome: 'Macro', nomeCompleto: 'Macromedidor', icone: 'speedometer-outline' },
        2: { nome: 'Pito', nomeCompleto: 'Estação Pitométrica', icone: 'pulse-outline' },
        4: { nome: 'Pressão', nomeCompleto: 'Pressão', icone: 'thermometer-outline' },
        6: { nome: 'Nível', nomeCompleto: 'Nível Reservatório', icone: 'water-outline' },
        8: { nome: 'Hidro', nomeCompleto: 'Hidrômetro', icone: 'stopwatch-outline' }
    };

    const unidadesMedidor = {
        1: 'l/s',
        2: 'l/s',
        4: 'mca',
        6: '%',
        8: 'l/s'
    };

    /**
     * Inicializacao
     */
    $(document).ready(function () {
        // Inicializar Select2
        $('.select2-default').select2({
            placeholder: 'Selecione...',
            allowClear: true,
            width: '100%',
            language: {
                noResults: function () { return "Nenhum resultado encontrado"; },
                searching: function () { return "Buscando..."; }
            }
        });

        // Carregar dados iniciais
        carregarDados();
    });

    /**
     * Gera o badge HTML do tipo de medidor
     */
    function getBadgeTipoMedidor(idTipo) {
        const tipo = tiposMedidor[idTipo];
        if (!tipo) return '';

        return `<span class="badge-tipo-medidor tipo-${idTipo}" title="${tipo.nomeCompleto}">
        <ion-icon name="${tipo.icone}"></ion-icon>
        ${tipo.nome}
    </span>`;
    }

    /**
     * Carrega dados do servidor
     */
    function carregarDados() {
        const periodo = $('#filtroPeriodo').val() || 7;
        const unidade = $('#filtroUnidade').val() || '';
        const tipo = $('#filtroTipo').val() || '';
        const status = $('#filtroStatus').val() || '';
        const limite = $('#filtroLimite').val() || 100;

        // Loading nos cards
        $('#statTotal').text('-');
        $('#statOk').text('-');
        $('#statAtencao').text('-');
        $('#statCritico').text('-');
        $('#statTratados').text('-');
        $('#statTotalTrend span').text('Carregando...');
        $('#statOkTrend span').text('-');
        $('#statAtencaoTrend span').text('-');
        $('#statCriticoTrend span').text('-');
        $('#statTratadosTrend span').text('-');

        // Loading na tabela
        $('#tabelaBody').html(`
        <tr>
            <td colspan="11">
                <div class="loading-container">
                    <ion-icon name="sync-outline"></ion-icon>
                    <span>Carregando dados...</span>
                </div>
            </td>
        </tr>
    `);

        // Loading nos rankings
        $('#rankingCriticos').html(`
        <li class="loading-container">
            <ion-icon name="sync-outline"></ion-icon>
            <span>Carregando...</span>
        </li>
    `);

        $('#rankingTratados').html(`
        <li class="loading-container">
            <ion-icon name="sync-outline"></ion-icon>
            <span>Carregando...</span>
        </li>
    `);

        $.ajax({
            url: 'bd/dashboard/getMetricasIA.php',
            method: 'GET',
            data: {
                acao: 'listar',
                periodo: periodo,
                unidade: unidade,
                tipo: tipo,
                status: status,
                limite: limite
            },
            dataType: 'json',
            success: function (response) {
                console.log('Resposta do servidor:', response);

                if (response.success) {
                    dadosAtuais = response.dados || response.data || [];

                    // Atualizar data de referencia
                    if (response.dataReferencia) {
                        $('#dataReferencia').text(response.dataReferencia);
                    }

                    // Atualizar cards
                    atualizarCards(response.resumo);

                    // Atualizar tabela
                    atualizarTabela(dadosAtuais);

                    // Atualizar grafico
                    atualizarGrafico(response.evolucao);

                    // Atualizar ranking críticos
                    atualizarRanking(response.criticos);

                    // NOVO: Atualizar ranking de tratados
                    atualizarRankingTratados(response.maisTratados);

                } else {
                    mostrarErro(response.message || 'Erro ao carregar dados');
                }
            },
            error: function (xhr, status, error) {
                console.error('Erro AJAX:', error, xhr.responseText);
                mostrarErro('Erro de conexão com o servidor');
            }
        });
    }

    /**
     * Atualiza os cards de estatísticas (ATUALIZADO)
     */
    function atualizarCards(resumo) {
        if (!resumo) {
            console.warn('Resumo vazio');
            return;
        }

        $('#statTotal').text(resumo.total || 0);
        $('#statOk').text(resumo.ok || 0);
        $('#statAtencao').text(resumo.atencao || 0);
        $('#statCritico').text(resumo.critico || 0);

        // NOVO: Card de tratamento manual
        $('#statTratados').text(resumo.pontosComTratamento || 0);

        // Percentuais
        const total = resumo.total || 1;
        $('#statOkTrend span').text(Math.round((resumo.ok / total) * 100) + '% do total');
        $('#statAtencaoTrend span').text(Math.round((resumo.atencao / total) * 100) + '% do total');
        $('#statCriticoTrend span').text(Math.round((resumo.critico / total) * 100) + '% do total');
        $('#statTotalTrend span').text('Cobertura média: ' + (resumo.coberturaMedia || 0).toFixed(1) + '%');

        // NOVO: Trend de tratamento manual com indicadores visuais
        const percTratado = resumo.percentualTratado || 0;
        const totalTratados = resumo.totalRegistrosTratados || 0;

        $('#statTratadosTrend span').text(
            totalTratados.toLocaleString('pt-BR') + ' reg. (' + percTratado.toFixed(2) + '%)'
        );

        // Indicador de cor baseado no percentual de tratamento
        // Mais tratamento = mais esforço operacional = indicador negativo
        if (percTratado > 5) {
            $('#statTratadosTrend').removeClass('neutral up').addClass('down');
            $('#statTratadosTrend ion-icon').attr('name', 'alert-circle-outline');
        } else if (percTratado > 1) {
            $('#statTratadosTrend').removeClass('up down').addClass('neutral');
            $('#statTratadosTrend ion-icon').attr('name', 'hand-right-outline');
        } else {
            $('#statTratadosTrend').removeClass('neutral down').addClass('up');
            $('#statTratadosTrend ion-icon').attr('name', 'checkmark-circle-outline');
        }
    }

    /**
     * Atualiza a tabela de dados (ATUALIZADO COM COLUNA TRATADOS)
     */
    function atualizarTabela(dados) {
        if (!dados || dados.length === 0) {
            $('#tabelaBody').html(`
            <tr>
                <td colspan="11">
                    <div class="empty-state">
                        <ion-icon name="file-tray-outline"></ion-icon>
                        <h3>Nenhum dado encontrado</h3>
                        <p>Ajuste os filtros e tente novamente</p>
                    </div>
                </td>
            </tr>
        `);
            return;
        }

        let html = '';
        dados.forEach(item => {
            const statusClass = item.DS_STATUS ? item.DS_STATUS.toLowerCase() : '';
            const cobertura = parseFloat(item.PERC_COBERTURA) || 0;
            const coberturaClass = cobertura >= 95 ? 'alta' : (cobertura >= 50 ? 'media' : 'baixa');
            const tendenciaClass = item.VL_TENDENCIA_7D ? item.VL_TENDENCIA_7D.toLowerCase() : 'estavel';

            const unidade = unidadesMedidor[item.ID_TIPO_MEDIDOR] || '';
            const media = parseFloat(item.VL_MEDIA) || 0;
            const hist = parseFloat(item.VL_MEDIA_HIST_4SEM) || 0;
            const desvio = parseFloat(item.VL_DESVIO_HIST_PERC) || 0;

            // NOVO: Dados de tratamento manual
            const qtdTratados = parseInt(item.QTD_TRATADOS_MANUAL) || 0;
            const qtdRegistros = parseInt(item.QTD_REGISTROS) || 1;
            const percTratados = ((qtdTratados / qtdRegistros) * 100).toFixed(1);

            // Classificação do nível de tratamento
            let tratadosClass = 'baixo';
            if (qtdTratados > 200 || percTratados > 20) {
                tratadosClass = 'alto';
            } else if (qtdTratados > 50 || percTratados > 5) {
                tratadosClass = 'medio';
            }

            html += `
            <tr onclick="verDetalhes(${item.CD_PONTO_MEDICAO}, '${item.DT_REFERENCIA}')" style="cursor:pointer;">
                <td>
                    <div class="ponto-info">
                        <span class="ponto-nome">${item.DS_NOME || 'Ponto ' + item.CD_PONTO_MEDICAO}</span>
                        <span class="ponto-tipo">${getBadgeTipoMedidor(item.ID_TIPO_MEDIDOR)}</span>
                    </div>
                </td>
                <td>${new Date(item.DT_REFERENCIA).toLocaleDateString('pt-BR')}</td>
                <td>
                    <div class="cobertura-bar ${coberturaClass}">
                        <div class="cobertura-fill" style="width: ${Math.min(cobertura, 100)}%"></div>
                    </div>
                    <span class="cobertura-text">${cobertura.toFixed(0)}%</span>
                </td>
                <td>${media.toFixed(2)} ${unidade}</td>
                <td>${hist > 0 ? hist.toFixed(2) + ' ' + unidade : '-'}</td>
                <td>
                    <span class="desvio ${desvio > 30 ? 'alto' : (desvio < -30 ? 'baixo' : '')}">
                        ${desvio !== 0 ? (desvio > 0 ? '+' : '') + desvio.toFixed(0) + '%' : '-'}
                    </span>
                </td>
                <td>
                    <span class="tendencia ${tendenciaClass}">
                        <ion-icon name="${tendenciaClass === 'subindo' ? 'trending-up-outline' :
                    (tendenciaClass === 'descendo' ? 'trending-down-outline' : 'remove-outline')}"></ion-icon>
                        ${item.VL_TENDENCIA_7D || 'ESTAVEL'}
                    </span>
                </td>
                <td>
                    <div class="flags-container">
                        <span class="flag-icon ${item.FL_COBERTURA_BAIXA == 1 ? 'ativo' : 'inativo'}" 
                              title="Cobertura baixa">
                            <ion-icon name="wifi-outline"></ion-icon>
                        </span>
                        <span class="flag-icon ${item.FL_SENSOR_PROBLEMA == 1 ? 'ativo' : 'inativo'}" 
                              title="Problema no sensor">
                            <ion-icon name="hardware-chip-outline"></ion-icon>
                        </span>
                        <span class="flag-icon ${item.FL_VALOR_ANOMALO == 1 ? 'ativo' : 'inativo'}" 
                              title="Valor anômalo">
                            <ion-icon name="alert-outline"></ion-icon>
                        </span>
                        <span class="flag-icon ${item.FL_DESVIO_SIGNIFICATIVO == 1 ? 'ativo' : 'inativo'}" 
                              title="Desvio significativo">
                            <ion-icon name="stats-chart-outline"></ion-icon>
                        </span>
                    </div>
                </td>
                <td>
                    ${qtdTratados > 0
                    ? `<span class="tratados-badge ${tratadosClass}" 
                                 title="${percTratados}% dos ${qtdRegistros.toLocaleString('pt-BR')} registros">
                             <ion-icon name="construct-outline"></ion-icon>
                             ${qtdTratados.toLocaleString('pt-BR')}
                           </span>`
                    : '<span style="color:#cbd5e1">-</span>'}
                </td>
                <td>
                    <span class="status-badge ${statusClass}">${item.DS_STATUS}</span>
                </td>
            </tr>
        `;
        });

        $('#tabelaBody').html(html);
    }

    /**
 * Ordena os dados da tabela
 * @param {string} coluna - Nome da coluna (data-column)
 */
    function ordenarDados(coluna) {
        if (!dadosAtuais || dadosAtuais.length === 0) {
            console.warn('Nenhum dado para ordenar');
            return;
        }

        // Alterna direção se mesma coluna
        if (colunaOrdenacao === coluna) {
            direcaoOrdenacao = direcaoOrdenacao === 'asc' ? 'desc' : 'asc';
        } else {
            colunaOrdenacao = coluna;
            direcaoOrdenacao = 'asc';
        }

        // Atualiza visual das colunas
        document.querySelectorAll('.tabela-metricas th.sortable').forEach(th => {
            th.classList.remove('asc', 'desc');
        });
        const thAtual = document.querySelector(`.tabela-metricas th[data-column="${coluna}"]`);
        if (thAtual) {
            thAtual.classList.add(direcaoOrdenacao);
        }

        // Ordena o array
        dadosAtuais.sort((a, b) => {
            let valorA, valorB;

            // Campos especiais
            if (coluna === 'DS_STATUS') {
                const ordemStatus = { 'CRITICO': 1, 'ATENCAO': 2, 'OK': 3 };
                valorA = ordemStatus[(a.DS_STATUS || '').toUpperCase()] || 4;
                valorB = ordemStatus[(b.DS_STATUS || '').toUpperCase()] || 4;
            } else if (coluna === 'VL_TENDENCIA_7D') {
                const ordemTend = { 'SUBINDO': 1, 'DESCENDO': 2, 'ESTAVEL': 3 };
                valorA = ordemTend[(a.VL_TENDENCIA_7D || 'ESTAVEL').toUpperCase()] || 4;
                valorB = ordemTend[(b.VL_TENDENCIA_7D || 'ESTAVEL').toUpperCase()] || 4;
            } else if (coluna === 'DS_NOME') {
                valorA = (a.DS_NOME || '').toLowerCase();
                valorB = (b.DS_NOME || '').toLowerCase();
            } else if (coluna === 'DT_REFERENCIA') {
                valorA = a.DT_REFERENCIA || '';
                valorB = b.DT_REFERENCIA || '';
            } else {
                // Campos numéricos (PERC_COBERTURA, VL_MEDIA, VL_MEDIA_HIST_4SEM, VL_DESVIO_HIST_PERC)
                valorA = parseFloat(a[coluna]) || 0;
                valorB = parseFloat(b[coluna]) || 0;
            }

            // Comparação
            if (typeof valorA === 'string') {
                return direcaoOrdenacao === 'asc'
                    ? valorA.localeCompare(valorB)
                    : valorB.localeCompare(valorA);
            } else {
                return direcaoOrdenacao === 'asc'
                    ? valorA - valorB
                    : valorB - valorA;
            }
        });

        // Re-renderiza a tabela
        atualizarTabela(dadosAtuais);
    }


    // ============================================
    // ADICIONAR ESTE EVENT LISTENER DENTRO DO $(document).ready
    // (após a inicialização do Select2)
    // ============================================

    // Event listener para ordenação nas colunas da tabela
    $(document).on('click', '.tabela-metricas th.sortable', function () {
        const coluna = $(this).data('column');
        if (coluna) {
            ordenarDados(coluna);
        }
    });

    /**
     * Atualiza o ranking de críticos
     */
    function atualizarRanking(criticos) {
        if (!criticos || criticos.length === 0) {
            $('#rankingCriticos').html(`
            <li class="empty-state" style="padding: 20px; text-align: center;">
                <ion-icon name="checkmark-circle-outline" style="font-size: 32px; color: #10b981;"></ion-icon>
                <p style="margin: 8px 0 0; color: #64748b; font-size: 13px;">
                    Nenhum ponto crítico ou em atenção
                </p>
            </li>
        `);
            return;
        }

        let html = '';
        criticos.forEach((item, index) => {
            const statusClass = item.DS_STATUS ? item.DS_STATUS.toLowerCase() : '';
            const cobertura = parseFloat(item.PERC_COBERTURA) || 0;

            html += `
            <li class="ranking-item" onclick="verDetalhes(${item.CD_PONTO_MEDICAO}, '${item.DT_REFERENCIA}')">
                <span class="ranking-position">${index + 1}</span>
                <div class="ranking-info">
                    <div class="ranking-header">
                        <span class="ranking-nome">${item.DS_NOME || 'Ponto ' + item.CD_PONTO_MEDICAO}</span>
                        ${getBadgeTipoMedidor(item.ID_TIPO_MEDIDOR)}
                    </div>
                    <div class="ranking-detalhe">${cobertura.toFixed(0)}% cobertura</div>
                </div>
                <span class="ranking-valor ${statusClass}">${item.DS_STATUS}</span>
            </li>
        `;
        });

        $('#rankingCriticos').html(html);
    }

    /**
     * NOVO: Atualiza o ranking de pontos com maior tratamento manual
     */
    function atualizarRankingTratados(maisTratados) {
        const container = $('#rankingTratados');

        if (!maisTratados || maisTratados.length === 0) {
            container.html(`
            <li class="empty-state" style="padding: 20px; text-align: center;">
                <ion-icon name="checkmark-circle-outline" style="font-size: 32px; color: #10b981;"></ion-icon>
                <p style="margin: 8px 0 0; color: #64748b; font-size: 13px;">
                    Nenhum ponto necessitou de tratamento manual hoje
                </p>
            </li>
        `);
            return;
        }

        let html = '';
        maisTratados.forEach((item, index) => {
            const qtdTratados = parseInt(item.QTD_TRATADOS_MANUAL) || 0;
            const percTratado = parseFloat(item.PERC_TRATADO) || 0;

            // Cor baseada na quantidade
            let badgeColor = '#8b5cf6'; // roxo padrão
            let bgColor = '#f5f3ff';
            if (qtdTratados > 200 || percTratado > 20) {
                badgeColor = '#dc2626'; // vermelho
                bgColor = '#fef2f2';
            } else if (qtdTratados > 100 || percTratado > 10) {
                badgeColor = '#d97706'; // laranja
                bgColor = '#fffbeb';
            }

            html += `
            <li class="ranking-item" onclick="verDetalhes(${item.CD_PONTO_MEDICAO}, '${item.DT_REFERENCIA}')">
                <span class="ranking-position">${index + 1}</span>
                <div class="ranking-info">
                    <div class="ranking-header">
                        <span class="ranking-nome">${item.DS_NOME || 'Ponto ' + item.CD_PONTO_MEDICAO}</span>
                        ${getBadgeTipoMedidor(item.ID_TIPO_MEDIDOR)}
                    </div>
                    <div class="ranking-detalhe" style="color: ${badgeColor};">
                        <strong>${qtdTratados.toLocaleString('pt-BR')}</strong> registros (${percTratado.toFixed(1)}%)
                    </div>
                </div>
                <span class="ranking-tratados-badge" style="background: ${bgColor}; color: ${badgeColor};">
                    <ion-icon name="construct-outline"></ion-icon>
                </span>
            </li>
        `;
        });

        container.html(html);
    }

    /**
     * NOVO: Destaca o card de tratados e scroll
     */
    function mostrarPontosTratados() {
        const elemento = document.getElementById('rankingTratados');
        if (elemento) {
            elemento.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Highlight temporário no card
            const card = elemento.closest('.content-card');
            if (card) {
                card.style.boxShadow = '0 0 0 3px #8b5cf6';
                card.style.transition = 'box-shadow 0.3s';
                setTimeout(() => {
                    card.style.boxShadow = '';
                }, 2000);
            }
        }
    }

    /**
     * Ver detalhes do ponto
     */
    function verDetalhes(cdPonto, data) {
        const partes = data.split('-');
        const ano = partes[0];
        const mes = parseInt(partes[1], 10);
        window.location.href = `operacoes.php?abrirValidacao=1&cdPonto=${cdPonto}&dataValidacao=${data}&mes=${mes}&ano=${ano}`;
    }

    /**
     * Filtrar por status (clique nos cards)
     */
    function filtrarPorStatus(status) {
        $('#filtroStatus').val(status);
        carregarDados();
    }

    /**
     * Limpa todos os filtros
     */
    function limparFiltros() {
        $('#filtroUnidade').val('').trigger('change');
        $('#filtroTipo').val('').trigger('change');
        $('#filtroPeriodo').val('7');
        $('#filtroStatus').val('');
        $('#filtroLimite').val('100');
        carregarDados();
    }

    /**
     * Exportar para CSV (ATUALIZADO COM TRATADOS)
     */
    function exportarCSV() {
        if (!dadosAtuais || dadosAtuais.length === 0) {
            alert('Nenhum dado para exportar');
            return;
        }

        // Cabeçalho atualizado com coluna de tratados
        let csv = 'Ponto;Nome;Tipo;Data;Cobertura;Media;Historico;Desvio%;Tendencia;Tratados;%Tratado;Status;Resumo\n';

        dadosAtuais.forEach(item => {
            const tipo = tiposMedidor[item.ID_TIPO_MEDIDOR];
            const qtdTratados = parseInt(item.QTD_TRATADOS_MANUAL) || 0;
            const qtdRegistros = parseInt(item.QTD_REGISTROS) || 1;
            const percTratados = ((qtdTratados / qtdRegistros) * 100).toFixed(2);

            csv += `${item.CD_PONTO_MEDICAO};`;
            csv += `"${item.DS_NOME || ''}";`;
            csv += `"${tipo ? tipo.nomeCompleto : ''}";`;
            csv += `${item.DT_REFERENCIA};`;
            csv += `${parseFloat(item.PERC_COBERTURA || 0).toFixed(1)};`;
            csv += `${parseFloat(item.VL_MEDIA || 0).toFixed(2)};`;
            csv += `${parseFloat(item.VL_MEDIA_HIST_4SEM || 0).toFixed(2)};`;
            csv += `${parseFloat(item.VL_DESVIO_HIST_PERC || 0).toFixed(1)};`;
            csv += `${item.VL_TENDENCIA_7D || ''};`;
            csv += `${qtdTratados};`;
            csv += `${percTratados};`;
            csv += `${item.DS_STATUS || ''};`;
            csv += `"${(item.DS_RESUMO || '').replace(/"/g, '""')}"\n`;
        });

        // Download
        const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `metricas_ia_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    /**
     * Mostra erro na interface
     */
    function mostrarErro(mensagem) {
        $('#tabelaBody').html(`
        <tr>
            <td colspan="11">
                <div class="empty-state" style="color: #dc2626;">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    <h3>Erro ao carregar dados</h3>
                    <p>${mensagem}</p>
                </div>
            </td>
        </tr>
    `);
    }

    /**
     * Atualiza gráfico de evolução
     */
    function atualizarGrafico(evolucao) {
        const ctx = document.getElementById('graficoEvolucao');
        if (!ctx) return;

        // Destruir gráfico anterior se existir
        if (graficoEvolucao) {
            graficoEvolucao.destroy();
        }

        if (!evolucao || evolucao.length === 0) {
            return;
        }

        const labels = evolucao.map(e => {
            const data = new Date(e.DT_REFERENCIA);
            return data.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
        });

        graficoEvolucao = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'OK',
                        data: evolucao.map(e => e.QTD_OK),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Atenção',
                        data: evolucao.map(e => e.QTD_ATENCAO),
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Crítico',
                        data: evolucao.map(e => e.QTD_CRITICO),
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Tratados',
                        data: evolucao.map(e => e.PONTOS_COM_TRATAMENTO || 0),
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        fill: false,
                        tension: 0.4,
                        borderDash: [5, 5] // Linha pontilhada para diferenciar
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
</script>

<?php include_once 'includes/footer.inc.php'; ?>
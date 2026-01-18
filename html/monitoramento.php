<?php
/**
 * SIMP - Monitoramento em Tempo Real
 * Lista de pontos de medição com status de saúde e filtros
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão de acesso
exigePermissaoTela('Dashboard Saúde', ACESSO_LEITURA);

// Buscar unidades para filtro
$unidades = [];
try {
    $sqlUnidades = "SELECT CD_UNIDADE, DS_NOME FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME";
    $stmtUnidades = $pdoSIMP->query($sqlUnidades);
    $unidades = $stmtUnidades->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $unidades = [];
}

// Buscar tipos de medidor para filtro
$tiposMedidor = [
    ['ID' => 1, 'NOME' => 'Macromedidor', 'LETRA' => 'M'],
    ['ID' => 2, 'NOME' => 'Estação Pitométrica', 'LETRA' => 'E'],
    ['ID' => 4, 'NOME' => 'Medidor Pressão', 'LETRA' => 'P'],
    ['ID' => 6, 'NOME' => 'Nível Reservatório', 'LETRA' => 'R'],
    ['ID' => 8, 'NOME' => 'Hidrômetro', 'LETRA' => 'H']
];

// Parâmetros GET para navegação do Dashboard
$filtroProblemaGet = isset($_GET['problema']) ? htmlspecialchars($_GET['problema']) : '';
$filtroTipoGet = isset($_GET['tipo_medidor']) ? htmlspecialchars($_GET['tipo_medidor']) : '';
$filtroUnidadeGet = isset($_GET['cd_unidade']) ? htmlspecialchars($_GET['cd_unidade']) : '';
$filtroStatusGet = isset($_GET['status']) ? htmlspecialchars($_GET['status']) : '';
$filtroBuscaGet = isset($_GET['busca']) ? htmlspecialchars($_GET['busca']) : '';
?>

<style>
/* ============================================
   Monitoramento - Estilos
   ============================================ */

.monitoramento-container {
    padding: 24px;
    max-width: 1600px;
    margin: 0 auto;
    background: #f8fafc;
    min-height: calc(100vh - 60px);
}

/* Header */
.page-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0d9488 100%);
    border-radius: 16px;
    padding: 24px 28px;
    margin-bottom: 24px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.page-header-info {
    display: flex;
    align-items: center;
    gap: 14px;
}

.page-header-icon {
    width: 48px;
    height: 48px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.page-header h1 {
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 2px 0;
}

.page-header-subtitle {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.7);
    margin: 0;
}

.header-stats {
    display: flex;
    gap: 20px;
}

.header-stat {
    text-align: center;
    padding: 0 16px;
    border-left: 1px solid rgba(255,255,255,0.2);
}

.header-stat:first-child {
    border-left: none;
}

.header-stat-value {
    font-size: 24px;
    font-weight: 700;
}

.header-stat-label {
    font-size: 11px;
    color: rgba(255,255,255,0.7);
    text-transform: uppercase;
}

.header-stat.saudavel .header-stat-value { color: #4ade80; }
.header-stat.alerta .header-stat-value { color: #fbbf24; }
.header-stat.critico .header-stat-value { color: #f87171; }

/* Filtros */
.filtros-card {
    background: white;
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border: 1px solid #e2e8f0;
}

.filtros-row {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filtro-group {
    flex: 1;
    min-width: 200px;
    max-width: 280px;
}

.filtro-group.busca {
    min-width: 280px;
    max-width: 350px;
}

.filtro-group label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filtro-group select,
.filtro-group input {
    width: 100%;
    height: 42px;
    padding: 0 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    color: #374151;
    background: #f9fafb;
    transition: all 0.2s;
    box-sizing: border-box;
}

.filtro-group select {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 10px center;
    background-repeat: no-repeat;
    background-size: 20px;
    padding-right: 36px;
}

.filtro-group select:focus,
.filtro-group input:focus {
    outline: none;
    border-color: #3b82f6;
    background: white;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.filtro-group input::placeholder {
    color: #9ca3af;
}

.filtros-actions {
    display: flex;
    gap: 10px;
    padding-top: 2px;
}

.btn-filtrar {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px;
    background: linear-gradient(135deg, #1e3a5f 0%, #0d9488 100%);
    border: none;
    border-radius: 8px;
    color: white;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-filtrar:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(13, 148, 136, 0.3);
}

.btn-limpar {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    color: #64748b;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-limpar:hover {
    background: #e2e8f0;
}

/* Filtros de Tipo de Problema (Checkboxes) */
.filtros-problema-row {
    display: flex;
    align-items: center;
    gap: 16px;
    padding-top: 16px;
    margin-top: 16px;
    border-top: 1px solid #e2e8f0;
    flex-wrap: wrap;
}

.filtros-problema-label {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    user-select: none;
}

.checkbox-item:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.checkbox-item:has(input:checked) {
    background: #eff6ff;
    border-color: #3b82f6;
}

.checkbox-item input[type="checkbox"] {
    display: none;
}

.checkbox-custom {
    width: 18px;
    height: 18px;
    border: 2px solid #cbd5e1;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
}

.checkbox-item:has(input:checked) .checkbox-custom {
    background: #3b82f6;
    border-color: #3b82f6;
}

.checkbox-custom::after {
    content: '';
    width: 5px;
    height: 9px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg) scale(0);
    transition: transform 0.15s ease;
}

.checkbox-item:has(input:checked) .checkbox-custom::after {
    transform: rotate(45deg) scale(1);
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    font-weight: 500;
    color: #475569;
}

.checkbox-label ion-icon {
    font-size: 16px;
    color: #64748b;
}

.checkbox-item:has(input:checked) .checkbox-label {
    color: #1e40af;
}

.checkbox-item:has(input:checked) .checkbox-label ion-icon {
    color: #3b82f6;
}

/* Status Tabs */
.status-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.status-tab {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s;
}

.status-tab:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
}

.status-tab.active {
    border-color: #3b82f6;
    background: #eff6ff;
    color: #1e40af;
}

.status-tab .count {
    padding: 2px 8px;
    background: #e2e8f0;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
}

.status-tab.active .count {
    background: #3b82f6;
    color: white;
}

.status-tab.saudavel.active { border-color: #22c55e; background: #dcfce7; color: #166534; }
.status-tab.saudavel.active .count { background: #22c55e; color: white; }

.status-tab.alerta.active { border-color: #f59e0b; background: #fef3c7; color: #92400e; }
.status-tab.alerta.active .count { background: #f59e0b; color: white; }

.status-tab.critico.active { border-color: #dc2626; background: #fee2e2; color: #991b1b; }
.status-tab.critico.active .count { background: #dc2626; color: white; }

/* Grid de Pontos */
.pontos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}

/* Card do Ponto */
.ponto-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    overflow: visible;
    transition: all 0.2s;
    cursor: pointer;
    position: relative;
    z-index: 1;
}

.ponto-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    border-color: #cbd5e1;
    z-index: 100;
}

.ponto-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    border-bottom: 1px solid #f1f5f9;
    border-radius: 12px 12px 0 0;
    background: white;
}

.ponto-status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
}

.ponto-status-indicator.saudavel { 
    background: #22c55e; 
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.2);
}
.ponto-status-indicator.alerta { 
    background: #f59e0b; 
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
    animation: pulse-yellow 2s infinite;
}
.ponto-status-indicator.critico { 
    background: #dc2626; 
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2);
    animation: pulse-red 1.5s infinite;
}

@keyframes pulse-yellow {
    0%, 100% { box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2); }
    50% { box-shadow: 0 0 0 6px rgba(245, 158, 11, 0.1); }
}

@keyframes pulse-red {
    0%, 100% { box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.3); }
    50% { box-shadow: 0 0 0 8px rgba(220, 38, 38, 0.1); }
}

.ponto-info {
    flex: 1;
    min-width: 0;
}

.ponto-nome {
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 2px;
}

.ponto-codigo {
    font-size: 11px;
    color: #64748b;
    font-family: 'Monaco', 'Consolas', monospace;
}

.ponto-score {
    text-align: right;
}

.ponto-score-value {
    font-size: 24px;
    font-weight: 700;
    line-height: 1;
}

.ponto-score-value.saudavel { color: #22c55e; }
.ponto-score-value.alerta { color: #f59e0b; }
.ponto-score-value.critico { color: #dc2626; }

.ponto-score-label {
    font-size: 10px;
    color: #94a3b8;
    text-transform: uppercase;
}

.ponto-card-body {
    padding: 16px;
    background: white;
}

.ponto-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 12px;
}

.ponto-stat {
    text-align: center;
}

.ponto-stat-value {
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
}

.ponto-stat-label {
    font-size: 10px;
    color: #94a3b8;
    text-transform: uppercase;
}

.ponto-flags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.ponto-flag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}

.ponto-flag.comunicacao { background: #ede9fe; color: #7c3aed; }
.ponto-flag.medidor { background: #cffafe; color: #0891b2; }
.ponto-flag.hidraulico { background: #ffedd5; color: #ea580c; }
.ponto-flag.verificar { background: #fef3c7; color: #d97706; }

/* Tooltips */
.tooltip-container {
    position: relative;
    display: inline-flex;
}

.tooltip-container .tooltip-text {
    visibility: hidden;
    opacity: 0;
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    background: #1e293b;
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 400;
    white-space: nowrap;
    z-index: 99999;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    max-width: 250px;
    white-space: normal;
    text-align: center;
    line-height: 1.4;
    pointer-events: none;
}

.tooltip-container .tooltip-text::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 6px solid transparent;
    border-top-color: #1e293b;
}

.tooltip-container:hover .tooltip-text {
    visibility: visible;
    opacity: 1;
}

/* Flag de tratamento recorrente */
.ponto-flag.tratamento { background: #fce7f3; color: #be185d; }

.ponto-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
    border-radius: 0 0 12px 12px;
}

.ponto-tipo {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    color: #64748b;
}

.ponto-tipo-badge {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
    color: white;
}

.ponto-tipo-badge.M { background: #3b82f6; }
.ponto-tipo-badge.E { background: #8b5cf6; }
.ponto-tipo-badge.P { background: #06b6d4; }
.ponto-tipo-badge.R { background: #10b981; }
.ponto-tipo-badge.H { background: #f59e0b; }

.ponto-ultima-leitura {
    font-size: 11px;
    color: #94a3b8;
}

/* Loading */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255,255,255,0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid #e2e8f0;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Empty State */
.empty-state {
    grid-column: 1 / -1;
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
    color: #475569;
    margin: 0 0 8px 0;
}

.empty-state p {
    font-size: 14px;
    margin: 0;
}

.btn-limpar-empty {
    margin-top: 16px;
    padding: 10px 20px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.btn-limpar-empty:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.btn-limpar-empty ion-icon {
    font-size: 16px;
}

/* Loading State */
.loading-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px 20px;
    color: #64748b;
}

.loading-spinner {
    width: 48px;
    height: 48px;
    border: 4px solid #e2e8f0;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.loading-state p {
    font-size: 14px;
    margin: 0;
}

/* Modal de Detalhes */
.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-backdrop.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 800px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
}

.modal-header-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.modal-status {
    width: 14px;
    height: 14px;
    border-radius: 50%;
}

.modal-status.saudavel { background: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.2); }
.modal-status.alerta { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2); }
.modal-status.critico { background: #dc2626; box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2); }

.modal-title {
    font-size: 18px;
    font-weight: 600;
    color: #0f172a;
    margin: 0;
}

.modal-subtitle {
    font-size: 12px;
    color: #64748b;
}

.modal-close {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: none;
    background: #f1f5f9;
    color: #64748b;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    transition: all 0.2s;
}

.modal-close:hover {
    background: #e2e8f0;
    color: #0f172a;
}

.modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}

/* Modal Stats Grid */
.modal-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.modal-stat-card {
    background: #f8fafc;
    border-radius: 10px;
    padding: 16px;
    text-align: center;
}

.modal-stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1;
    margin-bottom: 4px;
}

.modal-stat-value.saudavel { color: #22c55e; }
.modal-stat-value.alerta { color: #f59e0b; }
.modal-stat-value.critico { color: #dc2626; }

.modal-stat-label {
    font-size: 11px;
    color: #64748b;
    text-transform: uppercase;
}

/* Modal Sections */
.modal-section {
    margin-bottom: 24px;
}

.modal-section:last-child {
    margin-bottom: 0;
}

.modal-section-title {
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
    margin: 0 0 12px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-section-title ion-icon {
    color: #3b82f6;
}

/* Flags List */
.modal-flags-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.modal-flag {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
}

.modal-flag.active {
    background: #fee2e2;
    color: #dc2626;
}

.modal-flag.inactive {
    background: #f1f5f9;
    color: #94a3b8;
}

/* Tooltip no modal */
.modal-flags-list .tooltip-container {
    display: inline-block;
}

.modal-flags-list .tooltip-text {
    bottom: auto;
    top: calc(100% + 8px);
}

.modal-flags-list .tooltip-text::after {
    top: auto;
    bottom: 100%;
    border-top-color: transparent;
    border-bottom-color: #1e293b;
}

/* Chart container no modal */
.modal-chart-container {
    height: 200px;
    margin-top: 12px;
}

/* Modal Footer */
.modal-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
}

.modal-footer-info {
    font-size: 12px;
    color: #64748b;
}

.modal-footer-actions {
    display: flex;
    gap: 8px;
}

.btn-modal {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-modal-secondary {
    background: white;
    border: 1px solid #e2e8f0;
    color: #475569;
}

.btn-modal-secondary:hover {
    background: #f8fafc;
}

.btn-modal-primary {
    background: linear-gradient(135deg, #1e3a5f 0%, #0d9488 100%);
    border: none;
    color: white;
}

.btn-modal-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(13, 148, 136, 0.3);
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 24px;
    padding: 16px;
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.pagination-info {
    font-size: 13px;
    color: #64748b;
}

.pagination-buttons {
    display: flex;
    gap: 4px;
}

.pagination-btn {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    background: white;
    border-radius: 6px;
    font-size: 13px;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s;
}

.pagination-btn:hover:not(:disabled) {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.pagination-btn.active {
    background: #3b82f6;
    border-color: #3b82f6;
    color: white;
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Responsivo */
@media (max-width: 768px) {
    .monitoramento-container { padding: 16px; }
    .page-header { flex-direction: column; text-align: center; }
    .header-stats { justify-content: center; }
    .filtros-row { flex-direction: column; }
    .filtro-group { min-width: 100%; }
    .filtros-problema-row { flex-direction: column; align-items: flex-start; }
    .checkbox-group { width: 100%; }
    .checkbox-item { flex: 1; min-width: calc(50% - 4px); justify-content: flex-start; }
    .pontos-grid { grid-template-columns: 1fr; }
    .modal-stats-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div class="monitoramento-container">
    <!-- Header -->
    <div class="page-header">
        <div class="page-header-info">
            <div class="page-header-icon">
                <ion-icon name="radio-outline"></ion-icon>
            </div>
            <div>
                <h1>Monitoramento em Tempo Real</h1>
                <p class="page-header-subtitle">Status de comunicação e saúde dos pontos de medição</p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <button class="btn-header" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); padding: 8px 14px; border-radius: 8px; color: white; cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 13px;" onclick="window.location.href='dashboardSaude.php'">
                <ion-icon name="speedometer-outline"></ion-icon>
                Dashboard
            </button>
            <div class="header-stats">
            <div class="header-stat saudavel">
                <div class="header-stat-value" id="headerSaudaveis">-</div>
                <div class="header-stat-label">Saudáveis</div>
            </div>
            <div class="header-stat alerta">
                <div class="header-stat-value" id="headerAlerta">-</div>
                <div class="header-stat-label">Em Alerta</div>
            </div>
            <div class="header-stat critico">
                <div class="header-stat-value" id="headerCriticos">-</div>
                <div class="header-stat-label">Críticos</div>
            </div>
        </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filtros-card">
        <div class="filtros-row">
            <div class="filtro-group busca">
                <label>Buscar</label>
                <input type="text" id="filtroBusca" placeholder="Nome ou código do ponto..." value="<?= $filtroBuscaGet ?>">
            </div>
            <div class="filtro-group">
                <label>Tipo de Medidor</label>
                <select id="filtroTipo">
                    <option value="">Todos</option>
                    <?php foreach ($tiposMedidor as $tipo): ?>
                        <option value="<?= $tipo['ID'] ?>" <?= $filtroTipoGet == $tipo['ID'] ? 'selected' : '' ?>><?= $tipo['LETRA'] ?> - <?= $tipo['NOME'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtro-group">
                <label>Unidade</label>
                <select id="filtroUnidade">
                    <option value="">Todas</option>
                    <?php foreach ($unidades as $unidade): ?>
                        <option value="<?= $unidade['CD_UNIDADE'] ?>" <?= $filtroUnidadeGet == $unidade['CD_UNIDADE'] ? 'selected' : '' ?>><?= htmlspecialchars($unidade['DS_NOME']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtros-actions">
                <button class="btn-filtrar" onclick="carregarPontosComFiltros()">
                    <ion-icon name="search-outline"></ion-icon>
                    Filtrar
                </button>
                <button class="btn-limpar" onclick="limparFiltros()">
                    <ion-icon name="close-outline"></ion-icon>
                    Limpar
                </button>
            </div>
        </div>
        
        <!-- Filtros de Tipo de Problema (Checkboxes) -->
        <div class="filtros-problema-row">
            <label class="filtros-problema-label">Tipo de Problema:</label>
            <div class="checkbox-group">
                <label class="checkbox-item">
                    <input type="checkbox" id="chkComunicacao" value="COMUNICACAO" <?= strpos($filtroProblemaGet, 'COMUNICACAO') !== false ? 'checked' : '' ?>>
                    <span class="checkbox-custom"></span>
                    <span class="checkbox-label"><ion-icon name="wifi-outline"></ion-icon> Comunicação</span>
                </label>
                <label class="checkbox-item">
                    <input type="checkbox" id="chkMedidor" value="MEDIDOR" <?= strpos($filtroProblemaGet, 'MEDIDOR') !== false ? 'checked' : '' ?>>
                    <span class="checkbox-custom"></span>
                    <span class="checkbox-label"><ion-icon name="speedometer-outline"></ion-icon> Medidor</span>
                </label>
                <label class="checkbox-item">
                    <input type="checkbox" id="chkHidraulico" value="HIDRAULICO" <?= strpos($filtroProblemaGet, 'HIDRAULICO') !== false ? 'checked' : '' ?>>
                    <span class="checkbox-custom"></span>
                    <span class="checkbox-label"><ion-icon name="water-outline"></ion-icon> Hidráulico</span>
                </label>
                <label class="checkbox-item">
                    <input type="checkbox" id="chkVerificar" value="VERIFICAR" <?= strpos($filtroProblemaGet, 'VERIFICAR') !== false ? 'checked' : '' ?>>
                    <span class="checkbox-custom"></span>
                    <span class="checkbox-label"><ion-icon name="help-circle-outline"></ion-icon> A Verificar</span>
                </label>
                <label class="checkbox-item">
                    <input type="checkbox" id="chkTratamento" value="TRATAMENTO" <?= strpos($filtroProblemaGet, 'TRATAMENTO') !== false ? 'checked' : '' ?>>
                    <span class="checkbox-custom"></span>
                    <span class="checkbox-label"><ion-icon name="repeat-outline"></ion-icon> Tratamento Recorrente</span>
                </label>
            </div>
        </div>
    </div>

    <!-- Status Tabs -->
    <div class="status-tabs">
        <div class="status-tab active" data-status="" onclick="filtrarPorStatus(this, '')">
            <ion-icon name="apps-outline"></ion-icon>
            Todos
            <span class="count" id="countTodos">0</span>
        </div>
        <div class="status-tab saudavel" data-status="SAUDAVEL" onclick="filtrarPorStatus(this, 'SAUDAVEL')">
            <ion-icon name="checkmark-circle-outline"></ion-icon>
            Saudáveis
            <span class="count" id="countSaudaveis">0</span>
        </div>
        <div class="status-tab alerta" data-status="ALERTA" onclick="filtrarPorStatus(this, 'ALERTA')">
            <ion-icon name="warning-outline"></ion-icon>
            Em Alerta
            <span class="count" id="countAlerta">0</span>
        </div>
        <div class="status-tab critico" data-status="CRITICO" onclick="filtrarPorStatus(this, 'CRITICO')">
            <ion-icon name="alert-circle-outline"></ion-icon>
            Críticos
            <span class="count" id="countCriticos">0</span>
        </div>
    </div>

    <!-- Grid de Pontos -->
    <div class="pontos-grid" id="pontosGrid">
        <div class="empty-state">
            <ion-icon name="hourglass-outline"></ion-icon>
            <h3>Carregando pontos...</h3>
            <p>Aguarde enquanto buscamos os dados</p>
        </div>
    </div>

    <!-- Paginação -->
    <div class="pagination-container" id="paginationContainer" style="display: none;">
        <div class="pagination-info">
            Mostrando <span id="paginationStart">1</span> - <span id="paginationEnd">20</span> de <span id="paginationTotal">0</span> pontos
        </div>
        <div class="pagination-buttons" id="paginationButtons">
            <!-- Gerado via JS -->
        </div>
    </div>
</div>

<!-- Modal de Detalhes -->
<div class="modal-backdrop" id="modalDetalhes">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-header-info">
                <div class="modal-status" id="modalStatus"></div>
                <div>
                    <h2 class="modal-title" id="modalTitulo">-</h2>
                    <div class="modal-subtitle" id="modalSubtitulo">-</div>
                </div>
            </div>
            <button class="modal-close" onclick="fecharModal()">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-body">
            <!-- Stats -->
            <div class="modal-stats-grid">
                <div class="modal-stat-card">
                    <div class="modal-stat-value" id="modalScore">-</div>
                    <div class="modal-stat-label">Score Saúde</div>
                </div>
                <div class="modal-stat-card">
                    <div class="modal-stat-value" id="modalRegistros">-</div>
                    <div class="modal-stat-label">Registros/Dia</div>
                </div>
                <div class="modal-stat-card">
                    <div class="modal-stat-value" id="modalMedia">-</div>
                    <div class="modal-stat-label">Média Período</div>
                </div>
                <div class="modal-stat-card">
                    <div class="modal-stat-value" id="modalDiasAnomalia">-</div>
                    <div class="modal-stat-label">Dias c/ Anomalia</div>
                </div>
            </div>

            <!-- Flags de Status -->
            <div class="modal-section">
                <h3 class="modal-section-title">
                    <ion-icon name="flag-outline"></ion-icon>
                    Indicadores de Status
                </h3>
                <div class="modal-flags-list" id="modalFlags">
                    <!-- Gerado via JS -->
                </div>
            </div>

            <!-- Gráfico de Evolução -->
            <div class="modal-section">
                <h3 class="modal-section-title">
                    <ion-icon name="analytics-outline"></ion-icon>
                    Média Diária (Últimos 7 dias)
                </h3>
                <div class="modal-chart-container">
                    <canvas id="modalChart"></canvas>
                </div>
            </div>

            <!-- Anomalias Recentes -->
            <div class="modal-section">
                <h3 class="modal-section-title">
                    <ion-icon name="warning-outline"></ion-icon>
                    Anomalias Detectadas
                </h3>
                <div id="modalAnomalias">
                    <!-- Gerado via JS -->
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <div class="modal-footer-info" id="modalFooterInfo">
                Última atualização: -
            </div>
            <div class="modal-footer-actions">
                <button class="btn-modal btn-modal-secondary" onclick="abrirHistorico()">
                    <ion-icon name="time-outline"></ion-icon>
                    Ver Histórico
                </button>
                <button class="btn-modal btn-modal-primary" onclick="abrirAnaliseIA()">
                    <ion-icon name="analytics-outline"></ion-icon>
                    Analisar com IA
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
/**
 * Monitoramento em Tempo Real - JavaScript
 */

// Variáveis globais
let todosPontos = [];
let pontosFiltrados = [];
let pontoAtual = null;
let modalChart = null;
let paginaAtual = 1;
const porPagina = 20;
let statusFiltro = '';
let ultimaDataDisponivel = null; // Data mais recente com dados

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se há parâmetros GET para aplicar filtros iniciais
    const urlParams = new URLSearchParams(window.location.search);
    const hasFilters = urlParams.has('problema') || urlParams.has('tipo_medidor') || 
                       urlParams.has('cd_unidade') || urlParams.has('status') || urlParams.has('busca');
    
    if (hasFilters) {
        // Carregar com filtros da URL
        carregarPontosComFiltros();
        
        // Atualizar tab ativa se status estiver definido
        const statusParam = urlParams.get('status');
        if (statusParam) {
            statusFiltro = statusParam.toUpperCase();
            document.querySelectorAll('.status-tab').forEach(tab => {
                tab.classList.remove('active');
                if (tab.dataset.status === statusFiltro) {
                    tab.classList.add('active');
                }
            });
        }
    } else {
        carregarPontos();
    }
    
    // Listener para busca em tempo real (Enter)
    document.getElementById('filtroBusca').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            carregarPontosComFiltros();
        }
    });
    
    // Listeners para selects - busca automática ao mudar
    document.getElementById('filtroTipo').addEventListener('change', carregarPontosComFiltros);
    document.getElementById('filtroUnidade').addEventListener('change', carregarPontosComFiltros);
    
    // Listeners para checkboxes de problema
    document.querySelectorAll('.checkbox-group input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('change', carregarPontosComFiltros);
    });
    
    // Debounce para campo de busca (digitar)
    let debounceTimer;
    document.getElementById('filtroBusca').addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            if (this.value.length >= 3 || this.value.length === 0) {
                carregarPontosComFiltros();
            }
        }, 500);
    });
    
    // Fechar modal com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            fecharModal();
        }
    });
    
    // Fechar modal clicando fora
    document.getElementById('modalDetalhes').addEventListener('click', function(e) {
        if (e.target === this) {
            fecharModal();
        }
    });
});

// Carregar todos os pontos (sem filtros)
async function carregarPontos() {
    mostrarLoading(true);
    console.log('Carregando todos os pontos sem filtros...');
    
    try {
        const response = await fetch('bd/dashboard/getPontosPorScore.php?limite=500');
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        console.log('Resultado:', result);
        
        if (result.success && result.data) {
            todosPontos = result.data;
            pontosFiltrados = [...todosPontos];
            
            // Armazenar a última data disponível nos dados
            if (result.ultima_data) {
                ultimaDataDisponivel = result.ultima_data;
                console.log('Última data disponível nos dados:', ultimaDataDisponivel);
            } else {
                console.log('ATENÇÃO: ultima_data não retornada pelo endpoint');
            }
            
            atualizarContadores();
            renderizarPontos();
        }
    } catch (error) {
        console.error('Erro ao carregar pontos:', error);
        document.getElementById('pontosGrid').innerHTML = `
            <div class="empty-state">
                <ion-icon name="alert-circle-outline"></ion-icon>
                <h3>Erro ao carregar dados</h3>
                <p>${error.message}</p>
            </div>
        `;
    } finally {
        mostrarLoading(false);
    }
}

// Obter tipos de problema selecionados (checkboxes)
function getProblemasSelecionados() {
    const checkboxes = document.querySelectorAll('.checkbox-group input[type="checkbox"]:checked');
    return Array.from(checkboxes).map(cb => cb.value).join(',');
}

// Carregar pontos com filtros via AJAX
async function carregarPontosComFiltros() {
    mostrarLoading(true);
    
    const busca = document.getElementById('filtroBusca').value.trim();
    const tipo = document.getElementById('filtroTipo').value;
    const unidade = document.getElementById('filtroUnidade').value;
    const problema = getProblemasSelecionados();
    
    // Construir URL com parâmetros
    const params = new URLSearchParams();
    params.append('limite', '500');
    if (busca) params.append('busca', busca);
    if (tipo) params.append('tipo_medidor', tipo);
    if (unidade) params.append('cd_unidade', unidade);
    if (problema) params.append('problema', problema);
    if (statusFiltro) params.append('status', statusFiltro);
    
    console.log('Carregando com filtros:', {busca, tipo, unidade, problema, statusFiltro});
    console.log('URL:', `bd/dashboard/getPontosPorScore.php?${params.toString()}`);
    
    try {
        const response = await fetch(`bd/dashboard/getPontosPorScore.php?${params.toString()}`);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        console.log('Resultado:', result);
        
        if (result.success && result.data) {
            todosPontos = result.data;
            pontosFiltrados = [...todosPontos];
            
            // Armazenar a última data disponível nos dados
            if (result.ultima_data) {
                ultimaDataDisponivel = result.ultima_data;
            }
            
            atualizarContadores();
            paginaAtual = 1;
            renderizarPontos();
            
            // Atualizar URL sem recarregar página
            const newUrl = params.toString() ? `?${params.toString()}` : window.location.pathname;
            window.history.replaceState({}, '', newUrl);
            
            // Mostrar quantidade encontrada
            console.log(`${todosPontos.length} pontos encontrados`);
        } else {
            throw new Error(result.message || 'Erro ao carregar dados');
        }
    } catch (error) {
        console.error('Erro ao carregar pontos:', error);
        document.getElementById('pontosGrid').innerHTML = `
            <div class="empty-state">
                <ion-icon name="alert-circle-outline"></ion-icon>
                <h3>Erro ao carregar dados</h3>
                <p>${error.message}</p>
            </div>
        `;
    } finally {
        mostrarLoading(false);
    }
}

// Mostrar/ocultar loading
function mostrarLoading(show) {
    const grid = document.getElementById('pontosGrid');
    if (show) {
        grid.innerHTML = `
            <div class="loading-state">
                <div class="loading-spinner"></div>
                <p>Carregando pontos de medição...</p>
            </div>
        `;
    }
}

// Atualizar contadores
function atualizarContadores() {
    const saudaveis = todosPontos.filter(p => p.STATUS_SAUDE === 'SAUDAVEL').length;
    const alerta = todosPontos.filter(p => p.STATUS_SAUDE === 'ALERTA').length;
    const criticos = todosPontos.filter(p => p.STATUS_SAUDE === 'CRITICO').length;
    const total = todosPontos.length;
    
    // Header
    document.getElementById('headerSaudaveis').textContent = saudaveis;
    document.getElementById('headerAlerta').textContent = alerta;
    document.getElementById('headerCriticos').textContent = criticos;
    
    // Tabs
    document.getElementById('countTodos').textContent = total;
    document.getElementById('countSaudaveis').textContent = saudaveis;
    document.getElementById('countAlerta').textContent = alerta;
    document.getElementById('countCriticos').textContent = criticos;
}

// Aplicar filtros localmente (para filtro rápido após carregar dados)
function aplicarFiltros() {
    const busca = document.getElementById('filtroBusca').value.toLowerCase();
    const tipo = document.getElementById('filtroTipo').value;
    const unidade = document.getElementById('filtroUnidade').value;
    const problemasSelecionados = getProblemasSelecionados().split(',').filter(p => p);
    
    pontosFiltrados = todosPontos.filter(ponto => {
        // Filtro de busca
        if (busca && !ponto.NOME_PONTO.toLowerCase().includes(busca) && 
            !String(ponto.CD_PONTO_MEDICAO).includes(busca)) {
            return false;
        }
        
        // Filtro de tipo
        if (tipo && ponto.ID_TIPO_MEDIDOR != tipo) {
            return false;
        }
        
        // Filtro de unidade
        if (unidade && ponto.CD_UNIDADE != unidade) {
            return false;
        }
        
        // Filtro de status
        if (statusFiltro && ponto.STATUS_SAUDE !== statusFiltro) {
            return false;
        }
        
        // Filtro de problemas (múltiplos - OR entre eles)
        if (problemasSelecionados.length > 0) {
            let temAlgumProblema = false;
            
            for (const problema of problemasSelecionados) {
                if (problema === 'COMUNICACAO' && ponto.DIAS_SEM_COMUNICACAO > 0) temAlgumProblema = true;
                if (problema === 'MEDIDOR' && (ponto.DIAS_PROBLEMA_MEDIDOR || ponto.DIAS_VALOR_CONSTANTE || 0) > 0) temAlgumProblema = true;
                if (problema === 'HIDRAULICO' && (ponto.DIAS_VALOR_NEGATIVO > 0 || ponto.DIAS_FORA_FAIXA > 0 || ponto.DIAS_COM_SPIKE > 0)) temAlgumProblema = true;
                if (problema === 'VERIFICAR' && ponto.DIAS_ZEROS_SUSPEITOS > 0) temAlgumProblema = true;
                if (problema === 'TRATAMENTO' && (ponto.QTD_TRATAMENTOS || 0) > 3) temAlgumProblema = true;
            }
            
            if (!temAlgumProblema) return false;
        }
        
        return true;
    });
    
    paginaAtual = 1;
    renderizarPontos();
}

// Limpar filtros
function limparFiltros() {
    document.getElementById('filtroBusca').value = '';
    document.getElementById('filtroTipo').value = '';
    document.getElementById('filtroUnidade').value = '';
    
    // Desmarcar todos os checkboxes de problema
    document.querySelectorAll('.checkbox-group input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
    
    statusFiltro = '';
    
    // Resetar tabs
    document.querySelectorAll('.status-tab').forEach(tab => tab.classList.remove('active'));
    document.querySelector('.status-tab[data-status=""]').classList.add('active');
    
    // Limpar URL
    window.history.replaceState({}, '', window.location.pathname);
    
    // Recarregar sem filtros
    carregarPontos();
}

// Filtrar por status (tabs)
function filtrarPorStatus(element, status) {
    document.querySelectorAll('.status-tab').forEach(tab => tab.classList.remove('active'));
    element.classList.add('active');
    statusFiltro = status;
    carregarPontosComFiltros();
}

// Renderizar pontos
function renderizarPontos() {
    const grid = document.getElementById('pontosGrid');
    const inicio = (paginaAtual - 1) * porPagina;
    const fim = inicio + porPagina;
    const pontosPagina = pontosFiltrados.slice(inicio, fim);
    
    if (pontosPagina.length === 0) {
        // Verificar se há filtros aplicados
        const busca = document.getElementById('filtroBusca').value;
        const tipo = document.getElementById('filtroTipo').value;
        const unidade = document.getElementById('filtroUnidade').value;
        const problema = document.getElementById('filtroProblema').value;
        const temFiltros = busca || tipo || unidade || problema || statusFiltro;
        
        grid.innerHTML = `
            <div class="empty-state">
                <ion-icon name="${temFiltros ? 'filter-outline' : 'search-outline'}"></ion-icon>
                <h3>Nenhum ponto encontrado</h3>
                <p>${temFiltros ? 'Nenhum resultado para os filtros selecionados.' : 'Não há pontos de medição cadastrados.'}</p>
                ${temFiltros ? '<button class="btn-limpar-empty" onclick="limparFiltros()"><ion-icon name="refresh-outline"></ion-icon> Limpar Filtros</button>' : ''}
            </div>
        `;
        document.getElementById('paginationContainer').style.display = 'none';
        return;
    }
    
    grid.innerHTML = pontosPagina.map(ponto => {
        const score = parseFloat(ponto.SCORE_MEDIO || 0);
        let statusClasse = 'saudavel';
        if (score < 5) statusClasse = 'critico';
        else if (score < 8) statusClasse = 'alerta';
        
        const tipoLetra = getTipoLetra(ponto.ID_TIPO_MEDIDOR);
        const tipoNome = getTipoNome(ponto.ID_TIPO_MEDIDOR);
        
        // Flags com tooltips explicativos
        let flags = [];
        if (ponto.DIAS_SEM_COMUNICACAO > 0) flags.push({ 
            classe: 'comunicacao', 
            texto: 'Comunicação', 
            tooltip: `Falha de comunicação em ${ponto.DIAS_SEM_COMUNICACAO} dia(s). O equipamento não enviou dados suficientes (menos de 50% dos registros esperados).`
        });
        // Usa DIAS_PROBLEMA_MEDIDOR que combina valor constante + perfil anômalo
        const diasMedidor = ponto.DIAS_PROBLEMA_MEDIDOR || ponto.DIAS_VALOR_CONSTANTE || 0;
        if (diasMedidor > 0) flags.push({ 
            classe: 'medidor', 
            texto: 'Problema Medidor', 
            tooltip: `Problema de medidor em ${diasMedidor} dia(s). Valor constante ou perfil anômalo detectado, indicando possível travamento ou defeito no sensor.`
        });
        if (ponto.DIAS_VALOR_NEGATIVO > 0 || ponto.DIAS_FORA_FAIXA > 0) flags.push({ 
            classe: 'hidraulico', 
            texto: 'Hidráulico', 
            tooltip: `Problema hidráulico detectado. ${ponto.DIAS_VALOR_NEGATIVO > 0 ? `Valores negativos em ${ponto.DIAS_VALOR_NEGATIVO} dia(s). ` : ''}${ponto.DIAS_FORA_FAIXA > 0 ? `Valores fora da faixa em ${ponto.DIAS_FORA_FAIXA} dia(s).` : ''}`
        });
        if (ponto.DIAS_ZEROS_SUSPEITOS > 0) flags.push({ 
            classe: 'verificar', 
            texto: 'Verificar', 
            tooltip: `Zeros suspeitos em ${ponto.DIAS_ZEROS_SUSPEITOS} dia(s). Quantidade anormal de leituras zeradas que podem indicar problema no sensor ou na rede.`
        });
        if (ponto.DIAS_COM_SPIKE > 0) flags.push({ 
            classe: 'hidraulico', 
            texto: 'Spikes', 
            tooltip: `Picos de leitura em ${ponto.DIAS_COM_SPIKE} dia(s). Variações bruscas que podem indicar vazamento, manobra na rede ou problema no sensor.`
        });
        if (ponto.QTD_TRATAMENTOS > 3) flags.push({ 
            classe: 'tratamento', 
            texto: 'Tratamento Recorrente', 
            tooltip: `Este ponto precisou de ${ponto.QTD_TRATAMENTOS} tratamentos manuais no período. Considere investigar a causa raiz do problema.`
        });
        
        return `
            <div class="ponto-card" onclick="abrirDetalhes(${ponto.CD_PONTO_MEDICAO})">
                <div class="ponto-card-header">
                    <div class="ponto-status-indicator ${statusClasse}"></div>
                    <div class="ponto-info">
                        <div class="ponto-nome">${ponto.NOME_PONTO || 'Ponto ' + ponto.CD_PONTO_MEDICAO}</div>
                        <div class="ponto-codigo">ID: ${ponto.CD_PONTO_MEDICAO}</div>
                    </div>
                    <div class="ponto-score">
                        <div class="ponto-score-value ${statusClasse}">${score.toFixed(1)}</div>
                        <div class="ponto-score-label">Score</div>
                    </div>
                </div>
                <div class="ponto-card-body">
                    <div class="ponto-stats">
                        <div class="ponto-stat">
                            <div class="ponto-stat-value">${ponto.DIAS_ANALISADOS || 0}</div>
                            <div class="ponto-stat-label">Dias</div>
                        </div>
                        <div class="ponto-stat">
                            <div class="ponto-stat-value">${Math.round(ponto.REGISTROS_MEDIO || 0)}</div>
                            <div class="ponto-stat-label">Reg/Dia</div>
                        </div>
                        <div class="ponto-stat">
                            <div class="ponto-stat-value">${ponto.DIAS_COM_ANOMALIA || 0}</div>
                            <div class="ponto-stat-label">Anomalias</div>
                        </div>
                    </div>
                    ${flags.length > 0 ? `
                        <div class="ponto-flags">
                            ${flags.map(f => `
                                <span class="tooltip-container">
                                    <span class="ponto-flag ${f.classe}">${f.texto}</span>
                                    <span class="tooltip-text">${f.tooltip}</span>
                                </span>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
                <div class="ponto-card-footer">
                    <div class="ponto-tipo">
                        <span class="ponto-tipo-badge ${tipoLetra}">${tipoLetra}</span>
                        ${tipoNome}
                    </div>
                    <div class="ponto-ultima-leitura">
                        Média: ${formatarValor(ponto.MEDIA_PERIODO)}
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    // Atualizar paginação
    atualizarPaginacao();
}

// Atualizar paginação
function atualizarPaginacao() {
    const total = pontosFiltrados.length;
    const totalPaginas = Math.ceil(total / porPagina);
    const inicio = (paginaAtual - 1) * porPagina + 1;
    const fim = Math.min(paginaAtual * porPagina, total);
    
    document.getElementById('paginationStart').textContent = inicio;
    document.getElementById('paginationEnd').textContent = fim;
    document.getElementById('paginationTotal').textContent = total;
    
    const container = document.getElementById('paginationContainer');
    const buttons = document.getElementById('paginationButtons');
    
    if (totalPaginas <= 1) {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'flex';
    
    let html = `
        <button class="pagination-btn" onclick="irParaPagina(${paginaAtual - 1})" ${paginaAtual === 1 ? 'disabled' : ''}>
            <ion-icon name="chevron-back-outline"></ion-icon>
        </button>
    `;
    
    for (let i = 1; i <= totalPaginas; i++) {
        if (i === 1 || i === totalPaginas || (i >= paginaAtual - 2 && i <= paginaAtual + 2)) {
            html += `<button class="pagination-btn ${i === paginaAtual ? 'active' : ''}" onclick="irParaPagina(${i})">${i}</button>`;
        } else if (i === paginaAtual - 3 || i === paginaAtual + 3) {
            html += `<button class="pagination-btn" disabled>...</button>`;
        }
    }
    
    html += `
        <button class="pagination-btn" onclick="irParaPagina(${paginaAtual + 1})" ${paginaAtual === totalPaginas ? 'disabled' : ''}>
            <ion-icon name="chevron-forward-outline"></ion-icon>
        </button>
    `;
    
    buttons.innerHTML = html;
}

function irParaPagina(pagina) {
    const totalPaginas = Math.ceil(pontosFiltrados.length / porPagina);
    if (pagina < 1 || pagina > totalPaginas) return;
    paginaAtual = pagina;
    renderizarPontos();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Abrir modal de detalhes
async function abrirDetalhes(cdPonto) {
    pontoAtual = todosPontos.find(p => p.CD_PONTO_MEDICAO == cdPonto);
    if (!pontoAtual) return;
    
    const score = parseFloat(pontoAtual.SCORE_MEDIO || 0);
    let statusClasse = 'saudavel';
    if (score < 5) statusClasse = 'critico';
    else if (score < 8) statusClasse = 'alerta';
    
    // Preencher modal
    document.getElementById('modalStatus').className = `modal-status ${statusClasse}`;
    document.getElementById('modalTitulo').textContent = pontoAtual.NOME_PONTO || 'Ponto ' + cdPonto;
    document.getElementById('modalSubtitulo').textContent = `ID: ${cdPonto} • ${pontoAtual.TIPO_MEDIDOR}`;
    
    // Stats
    document.getElementById('modalScore').textContent = score.toFixed(1);
    document.getElementById('modalScore').className = `modal-stat-value ${statusClasse}`;
    document.getElementById('modalRegistros').textContent = Math.round(pontoAtual.REGISTROS_MEDIO || 0);
    document.getElementById('modalMedia').textContent = formatarValor(pontoAtual.MEDIA_PERIODO);
    document.getElementById('modalDiasAnomalia').textContent = pontoAtual.DIAS_COM_ANOMALIA || 0;
    
    // Flags com tooltips
    const flagsHtml = `
        <div class="tooltip-container">
            <div class="modal-flag ${pontoAtual.DIAS_SEM_COMUNICACAO > 0 ? 'active' : 'inactive'}">
                <ion-icon name="wifi-outline"></ion-icon>
                Comunicação (${pontoAtual.DIAS_SEM_COMUNICACAO || 0} dias)
            </div>
            <span class="tooltip-text">Falha de comunicação: equipamento não enviou dados suficientes (menos de 50% dos registros esperados)</span>
        </div>
        <div class="tooltip-container">
            <div class="modal-flag ${pontoAtual.DIAS_VALOR_CONSTANTE > 0 ? 'active' : 'inactive'}">
                <ion-icon name="pause-circle-outline"></ion-icon>
                Valor Constante (${pontoAtual.DIAS_VALOR_CONSTANTE || 0} dias)
            </div>
            <span class="tooltip-text">Sensor travado: reportando sempre o mesmo valor, indicando possível defeito</span>
        </div>
        <div class="tooltip-container">
            <div class="modal-flag ${pontoAtual.DIAS_VALOR_NEGATIVO > 0 ? 'active' : 'inactive'}">
                <ion-icon name="remove-circle-outline"></ion-icon>
                Valor Negativo (${pontoAtual.DIAS_VALOR_NEGATIVO || 0} dias)
            </div>
            <span class="tooltip-text">Valores negativos detectados: pode indicar fluxo reverso ou problema no sensor</span>
        </div>
        <div class="tooltip-container">
            <div class="modal-flag ${pontoAtual.DIAS_FORA_FAIXA > 0 ? 'active' : 'inactive'}">
                <ion-icon name="trending-up-outline"></ion-icon>
                Fora da Faixa (${pontoAtual.DIAS_FORA_FAIXA || 0} dias)
            </div>
            <span class="tooltip-text">Valores fora dos limites operacionais configurados para este tipo de medidor</span>
        </div>
        <div class="tooltip-container">
            <div class="modal-flag ${pontoAtual.DIAS_COM_SPIKE > 0 ? 'active' : 'inactive'}">
                <ion-icon name="flash-outline"></ion-icon>
                Spikes (${pontoAtual.DIAS_COM_SPIKE || 0} dias)
            </div>
            <span class="tooltip-text">Picos de leitura: variações bruscas que podem indicar vazamento, manobra na rede ou problema no sensor</span>
        </div>
        <div class="tooltip-container">
            <div class="modal-flag ${pontoAtual.DIAS_ZEROS_SUSPEITOS > 0 ? 'active' : 'inactive'}">
                <ion-icon name="alert-outline"></ion-icon>
                Zeros Suspeitos (${pontoAtual.DIAS_ZEROS_SUSPEITOS || 0} dias)
            </div>
            <span class="tooltip-text">Quantidade anormal de leituras zeradas que podem indicar problema no sensor</span>
        </div>
        <div class="tooltip-container">
            <div class="modal-flag ${(pontoAtual.QTD_TRATAMENTOS || 0) > 3 ? 'active' : 'inactive'}" style="${(pontoAtual.QTD_TRATAMENTOS || 0) > 3 ? 'background: #fce7f3; color: #be185d;' : ''}">
                <ion-icon name="construct-outline"></ion-icon>
                Tratamentos (${pontoAtual.QTD_TRATAMENTOS || 0})
            </div>
            <span class="tooltip-text">Quantidade de vezes que registros deste ponto foram descartados manualmente. Muitos tratamentos indicam problema persistente</span>
        </div>
    `;
    document.getElementById('modalFlags').innerHTML = flagsHtml;
    
    // Anomalias
    document.getElementById('modalAnomalias').innerHTML = `
        <p style="color: #64748b; font-size: 13px;">
            ${pontoAtual.DIAS_COM_ANOMALIA > 0 
                ? `Este ponto apresentou anomalias em ${pontoAtual.DIAS_COM_ANOMALIA} dos últimos ${pontoAtual.DIAS_ANALISADOS} dias analisados.`
                : 'Nenhuma anomalia detectada no período analisado.'
            }
        </p>
    `;
    
    // Footer
    document.getElementById('modalFooterInfo').textContent = `Período analisado: ${pontoAtual.DIAS_ANALISADOS} dias`;
    
    // Carregar gráfico
    await carregarGraficoModal(cdPonto);
    
    // Mostrar modal
    document.getElementById('modalDetalhes').classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Carregar gráfico no modal
async function carregarGraficoModal(cdPonto) {
    try {
        const response = await fetch(`bd/dashboard/getEvolucaoPonto.php?cd_ponto=${cdPonto}&dias=7`);
        const result = await response.json();
        
        const ctx = document.getElementById('modalChart').getContext('2d');
        
        if (modalChart) {
            modalChart.destroy();
        }
        
        let labels = [];
        let valores = [];
        
        if (result.success && result.data && result.data.length > 0) {
            labels = result.data.map(d => formatarData(d.DT_MEDICAO, true));
            valores = result.data.map(d => parseFloat(d.VL_MEDIA_DIARIA || 0));
        } else {
            // Dados simulados se não houver dados
            labels = ['D-6', 'D-5', 'D-4', 'D-3', 'D-2', 'D-1', 'Hoje'];
            const mediaPeriodo = parseFloat(pontoAtual.MEDIA_PERIODO || 0);
            valores = labels.map(() => Math.max(0, mediaPeriodo + (Math.random() - 0.5) * mediaPeriodo * 0.2));
        }
        
        // Calcular min/max para escala dinâmica
        const minVal = Math.min(...valores);
        const maxVal = Math.max(...valores);
        const range = maxVal - minVal;
        const yMin = Math.max(0, minVal - range * 0.1);
        const yMax = maxVal + range * 0.1;
        
        // Determinar unidade baseada no tipo de medidor
        const tipoMedidor = pontoAtual.ID_TIPO_MEDIDOR;
        let unidade = 'L/s'; // Padrão para vazão
        if (tipoMedidor == 4) unidade = 'mca'; // Pressão
        else if (tipoMedidor == 6) unidade = 'm'; // Nível reservatório
        
        modalChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: `Média Diária (${unidade})`,
                    data: valores,
                    borderColor: '#0d9488',
                    backgroundColor: 'rgba(13, 148, 136, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#0d9488',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.parsed.y.toFixed(2)} ${unidade}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        min: yMin,
                        max: yMax,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: {
                            callback: function(value) {
                                return value.toFixed(1);
                            }
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    } catch (error) {
        console.error('Erro ao carregar gráfico:', error);
    }
}

// Fechar modal
function fecharModal() {
    document.getElementById('modalDetalhes').classList.remove('active');
    document.body.style.overflow = '';
    pontoAtual = null;
}

// Abrir análise com IA
async function abrirAnaliseIA() {
    if (!pontoAtual) return;
    
    try {
        // Buscar parâmetros completos do ponto (tipo, valor de entidade, etc)
        const response = await fetch(`bd/dashboard/getParametrosPonto.php?cd_ponto=${pontoAtual.CD_PONTO_MEDICAO}`);
        const result = await response.json();
        
        if (result.success && result.url_operacoes) {
            // Adicionar mês/ano baseado na última data disponível
            let url = result.url_operacoes;
            const partes = result.ultima_data.split('-');
            if (partes.length >= 2) {
                url += `&mes=${parseInt(partes[1])}&ano=${partes[0]}`;
            }
            window.location.href = url;
        } else {
            // Fallback: usar URL simples
            const hoje = new Date();
            const ontem = new Date(hoje);
            ontem.setDate(ontem.getDate() - 1);
            const dataValidacao = ontem.toISOString().split('T')[0];
            const partes = dataValidacao.split('-');
            window.location.href = `operacoes.php?abrirValidacao=1&cdPonto=${pontoAtual.CD_PONTO_MEDICAO}&dataValidacao=${dataValidacao}&mes=${parseInt(partes[1])}&ano=${partes[0]}`;
        }
    } catch (error) {
        console.error('Erro ao buscar parâmetros:', error);
        // Fallback em caso de erro
        const hoje = new Date();
        window.location.href = `operacoes.php?abrirValidacao=1&cdPonto=${pontoAtual.CD_PONTO_MEDICAO}&mes=${hoje.getMonth() + 1}&ano=${hoje.getFullYear()}`;
    }
}

// Abrir histórico
function abrirHistorico() {
    if (!pontoAtual) return;
    
    console.log('abrirHistorico - ultimaDataDisponivel:', ultimaDataDisponivel);
    
    // Usar a última data disponível nos dados (já carregada)
    let dataFim, dataInicio;
    
    if (ultimaDataDisponivel) {
        dataFim = ultimaDataDisponivel;
        const dataFimObj = new Date(dataFim + 'T12:00:00');
        const dataInicioObj = new Date(dataFimObj);
        dataInicioObj.setDate(dataInicioObj.getDate() - 7);
        dataInicio = dataInicioObj.toISOString().split('T')[0];
    } else {
        // Fallback apenas se não tiver dados carregados
        const hoje = new Date();
        const inicio = new Date(hoje);
        inicio.setDate(inicio.getDate() - 7);
        dataFim = hoje.toISOString().split('T')[0];
        dataInicio = inicio.toISOString().split('T')[0];
    }
    
    window.location.href = `registroVazaoPressao.php?cdPonto=${pontoAtual.CD_PONTO_MEDICAO}&data_inicio=${dataInicio}&data_fim=${dataFim}`;
}

// Funções auxiliares
function getTipoLetra(idTipo) {
    const letras = { 1: 'M', 2: 'E', 4: 'P', 6: 'R', 8: 'H' };
    return letras[idTipo] || 'X';
}

function getTipoNome(idTipo) {
    const nomes = { 
        1: 'Macromedidor', 
        2: 'Est. Pitométrica', 
        4: 'Med. Pressão', 
        6: 'Nível Reserv.', 
        8: 'Hidrômetro' 
    };
    return nomes[idTipo] || 'Desconhecido';
}

function formatarValor(valor) {
    if (valor === null || valor === undefined) return '-';
    return parseFloat(valor).toFixed(2);
}

function formatarData(dataStr, curto = false) {
    if (!dataStr) return '-';
    
    // Evitar problema de timezone: adicionar T12:00:00 para datas sem hora
    // Isso evita que '2025-11-21' seja interpretado como UTC e "volte" 1 dia
    let dataAjustada = dataStr;
    if (dataStr.length === 10) {
        dataAjustada = dataStr + 'T12:00:00';
    }
    
    const data = new Date(dataAjustada);
    if (curto) {
        return data.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    }
    return data.toLocaleDateString('pt-BR');
}
</script>

<?php include_once 'includes/footer.inc.php'; ?>
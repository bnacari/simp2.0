<?php
/**
 * SIMP - Dashboard de Saúde dos Medidores
 * Visão executiva do status de comunicação e funcionamento dos equipamentos
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão de acesso
exigePermissaoTela('Dashboard Saúde', ACESSO_LEITURA);
?>

<style>
/* ============================================
   Dashboard de Saúde - Estilos
   ============================================ */

.dashboard-container {
    padding: 24px;
    max-width: 1600px;
    margin: 0 auto;
    background: #f8fafc;
    min-height: calc(100vh - 60px);
}

/* Header do Dashboard */
.dashboard-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0d9488 100%);
    border-radius: 16px;
    padding: 28px 32px;
    margin-bottom: 24px;
    color: white;
    position: relative;
    overflow: hidden;
}

.dashboard-header::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 300px;
    height: 100%;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    opacity: 0.5;
}

.dashboard-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 1;
}

.dashboard-header-info {
    display: flex;
    align-items: center;
    gap: 16px;
}

.dashboard-header-icon {
    width: 56px;
    height: 56px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.dashboard-header h1 {
    font-size: 24px;
    font-weight: 700;
    margin: 0 0 4px 0;
    color: white;
}

.dashboard-header-subtitle {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.7);
    margin: 0;
}

.dashboard-header-actions {
    display: flex;
    gap: 12px;
}

.btn-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    color: white;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-header:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-1px);
}

.btn-header ion-icon {
    font-size: 18px;
}

/* Período de análise */
.periodo-info {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    font-size: 13px;
}

.periodo-info ion-icon {
    font-size: 16px;
    color: rgba(255, 255, 255, 0.7);
}

/* ============================================
   KPI Cards
   ============================================ */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.kpi-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
}

.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.kpi-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--kpi-color, #3b82f6);
}

.kpi-card.saudavel { --kpi-color: #22c55e; }
.kpi-card.alerta { --kpi-color: #f59e0b; }
.kpi-card.critico { --kpi-color: #dc2626; }
.kpi-card.comunicacao { --kpi-color: #8b5cf6; }
.kpi-card.medidor { --kpi-color: #06b6d4; }
.kpi-card.hidraulico { --kpi-color: #f97316; }

.kpi-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.kpi-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    background: var(--kpi-bg, #eff6ff);
    color: var(--kpi-color, #3b82f6);
}

.kpi-card.saudavel .kpi-icon { background: #dcfce7; }
.kpi-card.alerta .kpi-icon { background: #fef3c7; }
.kpi-card.critico .kpi-icon { background: #fee2e2; }
.kpi-card.comunicacao .kpi-icon { background: #ede9fe; }
.kpi-card.medidor .kpi-icon { background: #cffafe; }
.kpi-card.hidraulico .kpi-icon { background: #ffedd5; }

.kpi-trend {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 20px;
}

.kpi-trend.up { background: #dcfce7; color: #16a34a; }
.kpi-trend.down { background: #fee2e2; color: #dc2626; }
.kpi-trend.neutral { background: #f1f5f9; color: #64748b; }

.kpi-value {
    font-size: 32px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1;
    margin-bottom: 4px;
}

.kpi-label {
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
}

/* ============================================
   Score Gauge
   ============================================ */
.score-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
    text-align: center;
}

.score-gauge {
    position: relative;
    width: 160px;
    height: 80px;
    margin: 0 auto 16px;
}

.score-gauge-bg {
    position: absolute;
    width: 160px;
    height: 80px;
    border-radius: 80px 80px 0 0;
    background: #e2e8f0;
    overflow: hidden;
}

.score-gauge-fill {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 160px;
    height: 80px;
    border-radius: 80px 80px 0 0;
    background: linear-gradient(90deg, #dc2626 0%, #f59e0b 50%, #22c55e 100%);
    transform-origin: bottom center;
    transform: rotate(calc((1 - var(--score, 0.7)) * -180deg));
    transition: transform 1s ease-out;
}

.score-gauge-cover {
    position: absolute;
    bottom: 0;
    left: 20px;
    width: 120px;
    height: 60px;
    border-radius: 60px 60px 0 0;
    background: white;
}

.score-gauge-value {
    position: absolute;
    bottom: 5px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 28px;
    font-weight: 700;
    color: #0f172a;
}

.score-label {
    font-size: 14px;
    color: #64748b;
    margin-bottom: 8px;
}

.score-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.score-status.saudavel { background: #dcfce7; color: #16a34a; }
.score-status.alerta { background: #fef3c7; color: #d97706; }
.score-status.critico { background: #fee2e2; color: #dc2626; }

/* ============================================
   Dashboard Grid Layout
   ============================================ */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 24px;
    margin-bottom: 24px;
}

.dashboard-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid #e2e8f0;
}

.card-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 15px;
    font-weight: 600;
    color: #0f172a;
}

.card-title ion-icon {
    font-size: 20px;
    color: #3b82f6;
}

.card-actions {
    display: flex;
    gap: 8px;
}

.btn-card {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #f1f5f9;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-card:hover {
    background: #e2e8f0;
}

.btn-card.active {
    background: #3b82f6;
    color: white;
}

.card-body {
    padding: 20px;
}

/* ============================================
   Gráfico de Evolução
   ============================================ */
.chart-container {
    height: 300px;
    position: relative;
}

/* ============================================
   Lista de Pontos Críticos
   ============================================ */
.pontos-list {
    max-height: 400px;
    overflow-y: auto;
}

.ponto-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: all 0.2s;
}

.ponto-item:hover {
    background: #f8fafc;
}

.ponto-item:last-child {
    border-bottom: none;
}

.ponto-status {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

.ponto-status.saudavel { background: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.2); }
.ponto-status.alerta { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2); }
.ponto-status.critico { background: #dc2626; box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2); animation: pulse-red 2s infinite; }

@keyframes pulse-red {
    0%, 100% { box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2); }
    50% { box-shadow: 0 0 0 6px rgba(220, 38, 38, 0.1); }
}

.ponto-info {
    flex: 1;
    min-width: 0;
}

.ponto-nome {
    font-size: 13px;
    font-weight: 500;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ponto-tipo {
    font-size: 11px;
    color: #64748b;
    margin-top: 2px;
}

.ponto-score {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
}

.ponto-score-value {
    font-size: 18px;
    font-weight: 700;
}

.ponto-score-value.saudavel { color: #22c55e; }
.ponto-score-value.alerta { color: #f59e0b; }
.ponto-score-value.critico { color: #dc2626; }

.ponto-score-label {
    font-size: 10px;
    color: #94a3b8;
    text-transform: uppercase;
}

/* ============================================
   Distribuição de Problemas
   ============================================ */
.problemas-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.problema-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px;
    background: #f8fafc;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.2s;
}

.problema-card:hover {
    background: #f1f5f9;
    transform: translateY(-1px);
}

.problema-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.problema-card.comunicacao .problema-icon { background: #ede9fe; color: #8b5cf6; }
.problema-card.medidor .problema-icon { background: #cffafe; color: #06b6d4; }
.problema-card.hidraulico .problema-icon { background: #ffedd5; color: #f97316; }
.problema-card.verificar .problema-icon { background: #fef3c7; color: #d97706; }

.problema-info h4 {
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
    margin: 0 0 2px 0;
}

.problema-info p {
    font-size: 11px;
    color: #64748b;
    margin: 0;
}

.problema-count {
    margin-left: auto;
    font-size: 24px;
    font-weight: 700;
    color: #0f172a;
}

/* ============================================
   Tabela de Anomalias
   ============================================ */
.anomalias-section {
    margin-top: 24px;
}

.table-anomalias {
    width: 100%;
    border-collapse: collapse;
}

.table-anomalias th {
    text-align: left;
    padding: 12px 16px;
    background: #f8fafc;
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e2e8f0;
}

.table-anomalias td {
    padding: 14px 16px;
    font-size: 13px;
    color: #334155;
    border-bottom: 1px solid #f1f5f9;
}

.table-anomalias tbody tr:hover {
    background: #f8fafc;
}

.badge-problema {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.badge-problema.comunicacao { background: #ede9fe; color: #7c3aed; }
.badge-problema.medidor { background: #cffafe; color: #0891b2; }
.badge-problema.hidraulico { background: #ffedd5; color: #ea580c; }
.badge-problema.verificar { background: #fef3c7; color: #d97706; }

.badge-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.badge-status.pendente { background: #fee2e2; color: #dc2626; }
.badge-status.tratado { background: #dcfce7; color: #16a34a; }

.btn-analisar {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: linear-gradient(135deg, #1e3a5f 0%, #0d9488 100%);
    border: none;
    border-radius: 6px;
    color: white;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-analisar:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(13, 148, 136, 0.3);
}

/* ============================================
   Loading States
   ============================================ */
.loading-skeleton {
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    border-radius: 4px;
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.skeleton-text {
    height: 20px;
    margin-bottom: 8px;
}

.skeleton-text.sm { height: 14px; width: 60%; }
.skeleton-text.lg { height: 32px; width: 40%; }

/* ============================================
   Responsivo
   ============================================ */
@media (max-width: 1200px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .dashboard-container { padding: 16px; }
    .dashboard-header { padding: 20px; }
    .dashboard-header-content { flex-direction: column; gap: 16px; text-align: center; }
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .problemas-grid { grid-template-columns: 1fr; }
}

/* ============================================
   Empty State
   ============================================ */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #64748b;
}

.empty-state ion-icon {
    font-size: 48px;
    color: #cbd5e1;
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 16px;
    color: #475569;
    margin: 0 0 8px 0;
}

.empty-state p {
    font-size: 13px;
    margin: 0;
}
</style>

<div class="dashboard-container">
    <!-- Header do Dashboard -->
    <div class="dashboard-header">
        <div class="dashboard-header-content">
            <div class="dashboard-header-info">
                <div class="dashboard-header-icon">
                    <ion-icon name="pulse-outline"></ion-icon>
                </div>
                <div>
                    <h1>Dashboard de Saúde dos Medidores</h1>
                    <p class="dashboard-header-subtitle">Monitoramento de comunicação e funcionamento dos equipamentos</p>
                </div>
            </div>
            <div class="dashboard-header-actions">
                <div class="periodo-info">
                    <ion-icon name="calendar-outline"></ion-icon>
                    <span id="periodoLabel">Últimos 7 dias</span>
                </div>
                <button class="btn-header" onclick="window.location.href='monitoramento.php'">
                    <ion-icon name="grid-outline"></ion-icon>
                    Ver Todos os Pontos
                </button>
                <button class="btn-header" onclick="atualizarDashboard()">
                    <ion-icon name="refresh-outline"></ion-icon>
                    Atualizar
                </button>
                <button class="btn-header" onclick="exportarRelatorio()">
                    <ion-icon name="download-outline"></ion-icon>
                    Exportar
                </button>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-grid">
        <!-- Total de Pontos -->
        <div class="kpi-card">
            <div class="kpi-header">
                <div class="kpi-icon">
                    <ion-icon name="location-outline"></ion-icon>
                </div>
            </div>
            <div class="kpi-value" id="kpiTotalPontos">-</div>
            <div class="kpi-label">Pontos Monitorados</div>
        </div>

        <!-- Saudáveis -->
        <div class="kpi-card saudavel">
            <div class="kpi-header">
                <div class="kpi-icon">
                    <ion-icon name="checkmark-circle-outline"></ion-icon>
                </div>
                <div class="kpi-trend up" id="trendSaudaveis">
                    <ion-icon name="arrow-up"></ion-icon>
                    <span>-</span>
                </div>
            </div>
            <div class="kpi-value" id="kpiSaudaveis">-</div>
            <div class="kpi-label">Saudáveis (Score ≥ 8)</div>
        </div>

        <!-- Alerta -->
        <div class="kpi-card alerta">
            <div class="kpi-header">
                <div class="kpi-icon">
                    <ion-icon name="warning-outline"></ion-icon>
                </div>
            </div>
            <div class="kpi-value" id="kpiAlerta">-</div>
            <div class="kpi-label">Em Alerta (Score 5-7)</div>
        </div>

        <!-- Críticos -->
        <div class="kpi-card critico">
            <div class="kpi-header">
                <div class="kpi-icon">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                </div>
            </div>
            <div class="kpi-value" id="kpiCriticos">-</div>
            <div class="kpi-label">Críticos (Score &lt; 5)</div>
        </div>

        <!-- Prob. Comunicação -->
        <div class="kpi-card comunicacao">
            <div class="kpi-header">
                <div class="kpi-icon">
                    <ion-icon name="wifi-outline"></ion-icon>
                </div>
            </div>
            <div class="kpi-value" id="kpiComunicacao">-</div>
            <div class="kpi-label">Falhas de Comunicação</div>
        </div>

        <!-- Prob. Medidor -->
        <div class="kpi-card medidor">
            <div class="kpi-header">
                <div class="kpi-icon">
                    <ion-icon name="speedometer-outline"></ion-icon>
                </div>
            </div>
            <div class="kpi-value" id="kpiMedidor">-</div>
            <div class="kpi-label">Problemas de Medidor</div>
        </div>
    </div>

    <!-- Grid Principal -->
    <div class="dashboard-grid">
        <!-- Coluna Esquerda - Gráfico de Evolução -->
        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-title">
                    <ion-icon name="trending-up-outline"></ion-icon>
                    Evolução do Score de Saúde
                </div>
                <div class="card-actions">
                    <button class="btn-card active" data-periodo="7">7 dias</button>
                    <button class="btn-card" data-periodo="15">15 dias</button>
                    <button class="btn-card" data-periodo="30">30 dias</button>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="chartEvolucao"></canvas>
                </div>
            </div>
        </div>

        <!-- Coluna Direita - Score e Pontos Críticos -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <!-- Score Médio -->
            <div class="score-card">
                <div class="score-label">Score Médio de Saúde</div>
                <div class="score-gauge">
                    <div class="score-gauge-bg"></div>
                    <div class="score-gauge-fill" id="scoreFill" style="--score: 0.7;"></div>
                    <div class="score-gauge-cover"></div>
                    <div class="score-gauge-value" id="scoreValue">-</div>
                </div>
                <div class="score-status saudavel" id="scoreStatus">
                    <ion-icon name="checkmark-circle"></ion-icon>
                    <span>Carregando...</span>
                </div>
            </div>

            <!-- Pontos Críticos -->
            <div class="dashboard-card" style="flex: 1;">
                <div class="card-header">
                    <div class="card-title">
                        <ion-icon name="alert-circle-outline"></ion-icon>
                        Pontos que Requerem Atenção
                    </div>
                </div>
                <div class="pontos-list" id="pontosCriticos">
                    <!-- Preenchido via JS -->
                    <div class="empty-state">
                        <ion-icon name="hourglass-outline"></ion-icon>
                        <h3>Carregando...</h3>
                        <p>Buscando pontos críticos</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Distribuição de Problemas -->
    <div class="dashboard-card">
        <div class="card-header">
            <div class="card-title">
                <ion-icon name="pie-chart-outline"></ion-icon>
                Distribuição por Tipo de Problema
            </div>
        </div>
        <div class="card-body">
            <div class="problemas-grid">
                <div class="problema-card comunicacao" onclick="filtrarPorProblema('COMUNICACAO')">
                    <div class="problema-icon">
                        <ion-icon name="wifi-outline"></ion-icon>
                    </div>
                    <div class="problema-info">
                        <h4>Comunicação</h4>
                        <p>Falha de rádio, bateria, datalogger</p>
                    </div>
                    <div class="problema-count" id="countComunicacao">-</div>
                </div>
                <div class="problema-card medidor" onclick="filtrarPorProblema('MEDIDOR')">
                    <div class="problema-icon">
                        <ion-icon name="speedometer-outline"></ion-icon>
                    </div>
                    <div class="problema-info">
                        <h4>Medidor</h4>
                        <p>Sensor travado, conversor A/D</p>
                    </div>
                    <div class="problema-count" id="countMedidor">-</div>
                </div>
                <div class="problema-card hidraulico" onclick="filtrarPorProblema('HIDRAULICO')">
                    <div class="problema-icon">
                        <ion-icon name="water-outline"></ion-icon>
                    </div>
                    <div class="problema-info">
                        <h4>Hidráulico</h4>
                        <p>Fora da faixa, negativos, spikes</p>
                    </div>
                    <div class="problema-count" id="countHidraulico">-</div>
                </div>
                <div class="problema-card verificar" onclick="filtrarPorProblema('VERIFICAR')">
                    <div class="problema-icon">
                        <ion-icon name="search-outline"></ion-icon>
                    </div>
                    <div class="problema-info">
                        <h4>A Verificar</h4>
                        <p>Zeros suspeitos, desvio histórico</p>
                    </div>
                    <div class="problema-count" id="countVerificar">-</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela de Anomalias Recentes -->
    <div class="dashboard-card anomalias-section">
        <div class="card-header">
            <div class="card-title">
                <ion-icon name="list-outline"></ion-icon>
                Anomalias Recentes
            </div>
            <div class="card-actions">
                <button class="btn-card" onclick="filtrarAnomalias('todas')">Todas</button>
                <button class="btn-card active" onclick="filtrarAnomalias('pendentes')">Pendentes</button>
            </div>
        </div>
        <div class="card-body" style="padding: 0;">
            <div style="overflow-x: auto;">
                <table class="table-anomalias">
                    <thead>
                        <tr>
                            <th>Ponto de Medição</th>
                            <th>Data</th>
                            <th>Tipo Problema</th>
                            <th>Score</th>
                            <th>Anomalias</th>
                            <th>Status</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaAnomalias">
                        <tr>
                            <td colspan="7" class="empty-state">
                                <ion-icon name="hourglass-outline"></ion-icon>
                                <h3>Carregando anomalias...</h3>
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

<script>
/**
 * Dashboard de Saúde - JavaScript
 */

// Variáveis globais
let chartEvolucao = null;
let dadosResumo = null;
let dadosPontos = [];
let dadosAnomalias = [];

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    carregarDashboard();
    
    // Listeners para botões de período do gráfico
    document.querySelectorAll('.card-actions .btn-card[data-periodo]').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.card-actions .btn-card[data-periodo]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            carregarEvolucao(this.dataset.periodo);
        });
    });
});

// Carregar todos os dados do dashboard
async function carregarDashboard() {
    try {
        await Promise.all([
            carregarResumoGeral(),
            carregarPontosPorScore(),
            carregarEvolucao(7),
            carregarAnomalias()
        ]);
    } catch (error) {
        console.error('Erro ao carregar dashboard:', error);
        showToast('Erro ao carregar dados do dashboard', 'error');
    }
}

// Carregar resumo geral (KPIs)
async function carregarResumoGeral() {
    try {
        const response = await fetch('bd/dashboard/getResumoGeral.php');
        const result = await response.json();
        
        if (result.success && result.data) {
            dadosResumo = result.data;
            atualizarKPIs(dadosResumo);
        }
    } catch (error) {
        console.error('Erro ao carregar resumo:', error);
    }
}

// Atualizar KPIs na tela
function atualizarKPIs(dados) {
    // Total de pontos
    animarNumero('kpiTotalPontos', dados.TOTAL_PONTOS || 0);
    
    // Saudáveis
    animarNumero('kpiSaudaveis', dados.PONTOS_SAUDAVEIS || 0);
    
    // Alerta
    animarNumero('kpiAlerta', dados.PONTOS_ALERTA || 0);
    
    // Críticos
    animarNumero('kpiCriticos', dados.PONTOS_CRITICOS || 0);
    
    // Comunicação
    animarNumero('kpiComunicacao', dados.PROB_COMUNICACAO || 0);
    
    // Medidor
    animarNumero('kpiMedidor', dados.PROB_MEDIDOR || 0);
    
    // Score médio
    const scoreMedio = parseFloat(dados.SCORE_MEDIO || 0);
    atualizarScoreGauge(scoreMedio);
    
    // Distribuição de problemas
    document.getElementById('countComunicacao').textContent = dados.PROB_COMUNICACAO || 0;
    document.getElementById('countMedidor').textContent = dados.PROB_MEDIDOR || 0;
    document.getElementById('countHidraulico').textContent = dados.PROB_HIDRAULICO || 0;
    document.getElementById('countVerificar').textContent = dados.TOTAL_ANOMALIAS - (dados.PROB_COMUNICACAO + dados.PROB_MEDIDOR + dados.PROB_HIDRAULICO) || 0;
    
    // Período
    if (dados.DATA_INICIO && dados.DATA_FIM) {
        document.getElementById('periodoLabel').textContent = 
            `${formatarData(dados.DATA_INICIO)} a ${formatarData(dados.DATA_FIM)}`;
    }
}

// Animar número
function animarNumero(elementId, valorFinal) {
    const elemento = document.getElementById(elementId);
    const valorInicial = parseInt(elemento.textContent) || 0;
    const duracao = 800;
    const inicio = performance.now();
    
    function atualizar(tempoAtual) {
        const progresso = Math.min((tempoAtual - inicio) / duracao, 1);
        const valorAtual = Math.round(valorInicial + (valorFinal - valorInicial) * easeOutQuart(progresso));
        elemento.textContent = valorAtual.toLocaleString('pt-BR');
        
        if (progresso < 1) {
            requestAnimationFrame(atualizar);
        }
    }
    
    requestAnimationFrame(atualizar);
}

function easeOutQuart(x) {
    return 1 - Math.pow(1 - x, 4);
}

// Atualizar gauge do score
function atualizarScoreGauge(score) {
    const fillElement = document.getElementById('scoreFill');
    const valueElement = document.getElementById('scoreValue');
    const statusElement = document.getElementById('scoreStatus');
    
    // Normalizar score para 0-1
    const scoreNormalizado = Math.max(0, Math.min(10, score)) / 10;
    fillElement.style.setProperty('--score', scoreNormalizado);
    
    // Valor
    valueElement.textContent = score.toFixed(1);
    
    // Status
    let status, classe, icone;
    if (score >= 8) {
        status = 'Saudável';
        classe = 'saudavel';
        icone = 'checkmark-circle';
    } else if (score >= 5) {
        status = 'Atenção';
        classe = 'alerta';
        icone = 'warning';
    } else {
        status = 'Crítico';
        classe = 'critico';
        icone = 'alert-circle';
    }
    
    statusElement.className = `score-status ${classe}`;
    statusElement.innerHTML = `<ion-icon name="${icone}"></ion-icon><span>${status}</span>`;
}

// Carregar pontos por score
async function carregarPontosPorScore() {
    try {
        const response = await fetch('bd/dashboard/getPontosPorScore.php?limite=10&status=CRITICO,ALERTA');
        const result = await response.json();
        
        if (result.success && result.data) {
            dadosPontos = result.data;
            renderizarPontosCriticos(dadosPontos);
        }
    } catch (error) {
        console.error('Erro ao carregar pontos:', error);
    }
}

// Renderizar lista de pontos críticos
function renderizarPontosCriticos(pontos) {
    const container = document.getElementById('pontosCriticos');
    
    if (!pontos || pontos.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <ion-icon name="checkmark-circle-outline"></ion-icon>
                <h3>Nenhum ponto crítico!</h3>
                <p>Todos os medidores estão funcionando bem</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = pontos.map(ponto => {
        const score = parseFloat(ponto.SCORE_MEDIO || 0);
        let statusClasse = 'saudavel';
        if (score < 5) statusClasse = 'critico';
        else if (score < 8) statusClasse = 'alerta';
        
        return `
            <div class="ponto-item" onclick="abrirAnalisePonto(${ponto.CD_PONTO_MEDICAO})">
                <div class="ponto-status ${statusClasse}"></div>
                <div class="ponto-info">
                    <div class="ponto-nome">${ponto.NOME_PONTO || 'Ponto ' + ponto.CD_PONTO_MEDICAO}</div>
                    <div class="ponto-tipo">${ponto.TIPO_MEDIDOR || ''} • ${ponto.DIAS_COM_ANOMALIA || 0} dias com anomalia</div>
                </div>
                <div class="ponto-score">
                    <div class="ponto-score-value ${statusClasse}">${score.toFixed(1)}</div>
                    <div class="ponto-score-label">Score</div>
                </div>
            </div>
        `;
    }).join('');
}

// Carregar evolução temporal
async function carregarEvolucao(dias = 7) {
    try {
        const response = await fetch(`bd/dashboard/getEvolucaoDiaria.php?dias=${dias}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            renderizarGraficoEvolucao(result.data);
        }
    } catch (error) {
        console.error('Erro ao carregar evolução:', error);
    }
}

// Renderizar gráfico de evolução
function renderizarGraficoEvolucao(dados) {
    const ctx = document.getElementById('chartEvolucao').getContext('2d');
    
    // Destruir gráfico anterior se existir
    if (chartEvolucao) {
        chartEvolucao.destroy();
    }
    
    const labels = dados.map(d => formatarData(d.DT_MEDICAO, true));
    const scores = dados.map(d => parseFloat(d.SCORE_MEDIO || 0));
    const saudaveis = dados.map(d => parseInt(d.QTD_SAUDAVEIS || 0));
    const alertas = dados.map(d => parseInt(d.QTD_ALERTA || 0));
    const criticos = dados.map(d => parseInt(d.QTD_CRITICOS || 0));
    
    chartEvolucao = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Score Médio',
                    data: scores,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Críticos',
                    data: criticos,
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220, 38, 38, 0.8)',
                    borderWidth: 0,
                    type: 'bar',
                    yAxisID: 'y1',
                    order: 1
                },
                {
                    label: 'Alerta',
                    data: alertas,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.8)',
                    borderWidth: 0,
                    type: 'bar',
                    yAxisID: 'y1',
                    order: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    titleFont: { size: 13 },
                    bodyFont: { size: 12 },
                    padding: 12,
                    cornerRadius: 8
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 } }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    min: 0,
                    max: 10,
                    title: {
                        display: true,
                        text: 'Score',
                        font: { size: 12 }
                    },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    min: 0,
                    title: {
                        display: true,
                        text: 'Qtd. Pontos',
                        font: { size: 12 }
                    },
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });
}

// Carregar anomalias
async function carregarAnomalias(filtro = 'pendentes') {
    try {
        const response = await fetch(`bd/dashboard/getAnomalias.php?filtro=${filtro}&limite=20`);
        const result = await response.json();
        
        if (result.success && result.data) {
            dadosAnomalias = result.data;
            renderizarTabelaAnomalias(dadosAnomalias);
        }
    } catch (error) {
        console.error('Erro ao carregar anomalias:', error);
    }
}

// Renderizar tabela de anomalias
function renderizarTabelaAnomalias(anomalias) {
    const tbody = document.getElementById('tabelaAnomalias');
    
    if (!anomalias || anomalias.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7">
                    <div class="empty-state">
                        <ion-icon name="checkmark-done-outline"></ion-icon>
                        <h3>Nenhuma anomalia pendente!</h3>
                        <p>Todos os registros foram tratados</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = anomalias.map(a => {
        const tipoProblema = (a.DS_TIPO_PROBLEMA || 'VERIFICAR').toLowerCase();
        const statusTratamento = a.ID_SITUACAO == 2 ? 'tratado' : 'pendente';
        const score = parseFloat(a.VL_SCORE_SAUDE || 0);
        let scoreClasse = 'saudavel';
        if (score < 5) scoreClasse = 'critico';
        else if (score < 8) scoreClasse = 'alerta';
        
        return `
            <tr>
                <td>
                    <strong>${a.NOME_PONTO || 'Ponto ' + a.CD_PONTO_MEDICAO}</strong>
                </td>
                <td>${formatarData(a.DT_MEDICAO)}</td>
                <td>
                    <span class="badge-problema ${tipoProblema}">${a.DS_TIPO_PROBLEMA || 'VERIFICAR'}</span>
                </td>
                <td>
                    <span class="ponto-score-value ${scoreClasse}" style="font-size: 16px;">${score.toFixed(1)}</span>
                </td>
                <td style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${a.DS_ANOMALIAS || ''}">
                    ${a.DS_ANOMALIAS || '-'}
                </td>
                <td>
                    <span class="badge-status ${statusTratamento}">${statusTratamento === 'tratado' ? 'Tratado' : 'Pendente'}</span>
                </td>
                <td>
                    <button class="btn-analisar" onclick="abrirAnalisePonto(${a.CD_PONTO_MEDICAO}, '${a.DT_MEDICAO}')" title="Analisar com IA">
                        <ion-icon name="analytics-outline"></ion-icon>
                        Analisar
                    </button>
                    <button class="btn-analisar" onclick="abrirHistorico(${a.CD_PONTO_MEDICAO}, '${a.DT_MEDICAO}')" title="Ver registros detalhados" style="background: #64748b; margin-left: 4px;">
                        <ion-icon name="time-outline"></ion-icon>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// Filtrar anomalias
function filtrarAnomalias(filtro) {
    // Atualizar botões
    document.querySelectorAll('.anomalias-section .btn-card').forEach(btn => {
        btn.classList.remove('active');
        if (btn.textContent.toLowerCase().includes(filtro === 'todas' ? 'todas' : 'pendentes')) {
            btn.classList.add('active');
        }
    });
    
    carregarAnomalias(filtro === 'todas' ? 'todas' : 'pendentes');
}

// Filtrar por tipo de problema
function filtrarPorProblema(tipo) {
    // TODO: Navegar para tela de monitoramento filtrada
    console.log('Filtrar por problema:', tipo);
    showToast(`Filtrando por: ${tipo}`, 'info');
}

// Abrir análise de ponto
async function abrirAnalisePonto(cdPonto, data = null) {
    try {
        // Buscar parâmetros completos do ponto (tipo, valor de entidade, etc)
        const response = await fetch(`bd/dashboard/getParametrosPonto.php?cd_ponto=${cdPonto}`);
        const result = await response.json();
        
        if (result.success && result.url_operacoes) {
            // Se tiver uma data específica, substituir na URL
            let url = result.url_operacoes;
            if (data) {
                url = url.replace(/dataValidacao=[^&]+/, `dataValidacao=${data}`);
                // Atualizar mês/ano também
                const partes = data.split('-');
                if (partes.length >= 2) {
                    url += `&mes=${parseInt(partes[1])}&ano=${partes[0]}`;
                }
            } else {
                // Adicionar mês/ano baseado na última data disponível
                const partes = result.ultima_data.split('-');
                if (partes.length >= 2) {
                    url += `&mes=${parseInt(partes[1])}&ano=${partes[0]}`;
                }
            }
            window.location.href = url;
        } else {
            // Fallback: usar URL simples
            const dataValidacao = data || new Date().toISOString().split('T')[0];
            const partes = dataValidacao.split('-');
            window.location.href = `operacoes.php?abrirValidacao=1&cdPonto=${cdPonto}&dataValidacao=${dataValidacao}&mes=${parseInt(partes[1])}&ano=${partes[0]}`;
        }
    } catch (error) {
        console.error('Erro ao buscar parâmetros:', error);
        // Fallback em caso de erro
        const dataValidacao = data || new Date().toISOString().split('T')[0];
        const partes = dataValidacao.split('-');
        window.location.href = `operacoes.php?abrirValidacao=1&cdPonto=${cdPonto}&dataValidacao=${dataValidacao}&mes=${parseInt(partes[1])}&ano=${partes[0]}`;
    }
}

// Abrir histórico de registros
function abrirHistorico(cdPonto, data = null) {
    // Navegar para registroVazaoPressao com o ponto e data específica
    let url = `registroVazaoPressao.php?cdPonto=${cdPonto}`;
    
    if (data) {
        // Usar a data específica da anomalia
        url += `&data_inicio=${data}&data_fim=${data}`;
    } else {
        // Usar os últimos 7 dias
        const hoje = new Date();
        const inicio = new Date(hoje);
        inicio.setDate(inicio.getDate() - 7);
        url += `&data_inicio=${inicio.toISOString().split('T')[0]}&data_fim=${hoje.toISOString().split('T')[0]}`;
    }
    
    window.location.href = url;
}

// Atualizar dashboard
function atualizarDashboard() {
    showToast('Atualizando dados...', 'info');
    carregarDashboard();
}

// Exportar relatório
function exportarRelatorio() {
    // TODO: Implementar exportação
    showToast('Funcionalidade em desenvolvimento', 'info');
}

// Funções auxiliares
function formatarData(dataStr, curto = false) {
    if (!dataStr) return '-';
    const data = new Date(dataStr);
    if (curto) {
        return data.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    }
    return data.toLocaleDateString('pt-BR');
}

function showToast(mensagem, tipo = 'info') {
    // Usar toast do sistema se disponível
    if (typeof window.showToast === 'function') {
        window.showToast(mensagem, tipo);
    } else {
        console.log(`[${tipo.toUpperCase()}] ${mensagem}`);
    }
}
</script>

<?php include_once 'includes/footer.inc.php'; ?>
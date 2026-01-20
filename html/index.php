<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Dashboard / Página Inicial
 * 
 * @version 2.0 - Inclui análise de qualidade dos dados
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// ============================================
// CONSULTAS BÁSICAS DO DASHBOARD
// ============================================
try {
    // Total de pontos de medição (ativos = sem data de desativação ou data futura)
    $stmtPontos = $pdoSIMP->query("
        SELECT COUNT(*) as total 
        FROM SIMP.dbo.PONTO_MEDICAO 
        WHERE DT_DESATIVACAO IS NULL OR DT_DESATIVACAO > GETDATE()
    ");
    $totalPontos = $stmtPontos->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Manutenções previstas (ID_SITUACAO = 1)
    $stmtManutencoes = $pdoSIMP->query("
        SELECT COUNT(*) as total 
        FROM SIMP.dbo.PROGRAMACAO_MANUTENCAO 
        WHERE ID_SITUACAO = 1
    ");
    $totalManutencoes = $stmtManutencoes->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Calibrações previstas (ID_TIPO_PROGRAMACAO = 1 AND ID_SITUACAO = 1)
    $stmtCalibracoes = $pdoSIMP->query("
        SELECT COUNT(*) as total 
        FROM SIMP.dbo.PROGRAMACAO_MANUTENCAO 
        WHERE ID_TIPO_PROGRAMACAO = 1 AND ID_SITUACAO = 1
    ");
    $totalCalibracoes = $stmtCalibracoes->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Manutenções do tipo 2 (Manutenção) previstas
    $stmtManutencoesTipo2 = $pdoSIMP->query("
        SELECT COUNT(*) as total 
        FROM SIMP.dbo.PROGRAMACAO_MANUTENCAO 
        WHERE ID_TIPO_PROGRAMACAO = 2 AND ID_SITUACAO = 1
    ");
    $totalManutencoesTipo2 = $stmtManutencoesTipo2->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Últimas programações de manutenção
    $stmtUltimasManut = $pdoSIMP->query("
        SELECT TOP 5
            PM.CD_CODIGO,
            PM.CD_ANO,
            PM.ID_TIPO_PROGRAMACAO,
            PM.ID_SITUACAO,
            PM.DT_PROGRAMACAO,
            P.DS_NOME AS DS_PONTO
        FROM SIMP.dbo.PROGRAMACAO_MANUTENCAO PM
        LEFT JOIN SIMP.dbo.PONTO_MEDICAO P ON P.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
        ORDER BY PM.DT_CADASTRO DESC
    ");
    $ultimasManutencoes = $stmtUltimasManut->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $totalPontos = 0;
    $totalManutencoes = 0;
    $totalCalibracoes = 0;
    $totalManutencoesTipo2 = 0;
    $ultimasManutencoes = [];
}

// Dia da semana
$diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
$diaSemana = $diasSemana[date('w')];

// Situações e tipos
$situacoes = [
    1 => ['nome' => 'Prevista', 'cor' => 'warning', 'icone' => 'time-outline'],
    2 => ['nome' => 'Realizada', 'cor' => 'success', 'icone' => 'checkmark-circle-outline'],
    3 => ['nome' => 'Cancelada', 'cor' => 'danger', 'icone' => 'close-circle-outline']
];

$tiposProgramacao = [
    1 => 'Calibração',
    2 => 'Manutenção'
];

// Buscar unidades para filtro
$unidades = [];
try {
    $sqlUnidades = "SELECT CD_UNIDADE, DS_NOME FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME";
    $stmtUnidades = $pdoSIMP->query($sqlUnidades);
    $unidades = $stmtUnidades->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $unidades = [];
}
?>

<style>
    /* ============================================
       Reset e Base
       ============================================ */
    .page-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 24px;
        background: #f1f5f9;
        min-height: 100vh;
    }

    /* ============================================
       Page Header (mesmo padrão filters-header)
       ============================================ */
    .page-header {
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        border-radius: 16px;
        padding: 24px 32px;
        margin-bottom: 24px;
        box-shadow: 0 4px 20px rgba(30, 58, 95, 0.3);
    }

    .page-header-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    }

    .page-header-info {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .page-header-icon {
        width: 64px;
        height: 64px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        color: white;
    }

    .page-header h1 {
        font-size: 22px;
        font-weight: 700;
        color: white;
        margin: 0 0 4px 0;
    }

    .page-header-subtitle {
        font-size: 14px;
        color: rgba(255, 255, 255, 0.8);
        margin: 0;
    }

    .header-date {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        color: white;
    }

    .header-date ion-icon {
        font-size: 24px;
    }

    .header-date-day {
        font-size: 14px;
        font-weight: 600;
        display: block;
    }

    .header-date-full {
        font-size: 12px;
        opacity: 0.8;
    }

    /* ============================================
       Stats Cards (cards superiores)
       ============================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 24px;
    }

    @media (max-width: 1200px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }
    
    @media (max-width: 640px) {
        .stats-grid { grid-template-columns: 1fr; }
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

    .stat-card.primary::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
    .stat-card.success::before { background: linear-gradient(90deg, #10b981, #34d399); }
    .stat-card.warning::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
    .stat-card.danger::before { background: linear-gradient(90deg, #ef4444, #f87171); }

    .stat-card-content {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .stat-info h3 {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        margin: 0 0 8px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-value {
        font-size: 32px;
        font-weight: 700;
        color: #1e293b;
        line-height: 1;
        margin-bottom: 8px;
    }

    .stat-label {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        color: #64748b;
    }

    .stat-label ion-icon {
        font-size: 14px;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: white;
    }

    .stat-icon.primary { background: linear-gradient(135deg, #3b82f6, #60a5fa); }
    .stat-icon.success { background: linear-gradient(135deg, #10b981, #34d399); }
    .stat-icon.warning { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
    .stat-icon.danger { background: linear-gradient(135deg, #ef4444, #f87171); }

    /* ============================================
       Seção de Análise de Qualidade
       ============================================ */
    .analise-section {
        margin-bottom: 24px;
    }

    .analise-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .analise-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
        font-weight: 600;
        color: #1e293b;
    }

    .analise-title ion-icon {
        font-size: 24px;
        color: #3b82f6;
    }

    .analise-filtros {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .analise-filtros select {
        padding: 8px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        color: #475569;
        background: white;
        cursor: pointer;
    }

    .analise-filtros select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .btn-carregar {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-carregar:hover {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    .btn-carregar:disabled {
        background: #94a3b8;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .btn-carregar ion-icon {
        font-size: 16px;
    }

    /* ============================================
       Cards de Resumo da Análise
       ============================================ */
    .analise-resumo-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    @media (max-width: 1200px) {
        .analise-resumo-grid { grid-template-columns: repeat(3, 1fr); }
    }
    
    @media (max-width: 768px) {
        .analise-resumo-grid { grid-template-columns: repeat(2, 1fr); }
    }
    
    @media (max-width: 480px) {
        .analise-resumo-grid { grid-template-columns: 1fr; }
    }

    .analise-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        text-align: center;
        transition: all 0.2s;
    }

    .analise-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .analise-card-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        font-size: 18px;
    }

    .analise-card-icon.bom { background: #dcfce7; color: #16a34a; }
    .analise-card-icon.regular { background: #fef3c7; color: #d97706; }
    .analise-card-icon.pessimo { background: #fee2e2; color: #dc2626; }
    .analise-card-icon.info { background: #dbeafe; color: #2563eb; }
    .analise-card-icon.roxo { background: #ede9fe; color: #7c3aed; }

    .analise-card-valor {
        font-size: 24px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 4px;
    }

    .analise-card-label {
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* ============================================
       Dashboard Grid Principal
       ============================================ */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
    }

    @media (max-width: 1024px) {
        .dashboard-grid { grid-template-columns: 1fr; }
    }

    /* ============================================
       Content Cards (padrão SIMP)
       ============================================ */
    .content-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
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
        padding: 16px 20px;
    }

    .btn-ver-todos {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        color: #3b82f6;
        text-decoration: none;
        padding: 6px 12px;
        border-radius: 6px;
        transition: background 0.2s;
    }

    .btn-ver-todos:hover {
        background: #eff6ff;
    }

    /* ============================================
       Lista de Ranking
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
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.2s;
    }

    .ranking-item:last-child {
        border-bottom: none;
    }

    .ranking-item:hover {
        background: #f8fafc;
        margin: 0 -20px;
        padding-left: 20px;
        padding-right: 20px;
    }

    .ranking-posicao {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .ranking-posicao.top1 { background: #fef3c7; color: #b45309; }
    .ranking-posicao.top2 { background: #e5e7eb; color: #4b5563; }
    .ranking-posicao.top3 { background: #fed7aa; color: #c2410c; }
    .ranking-posicao.normal { background: #f1f5f9; color: #64748b; }

    .ranking-tipo {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
    }

    .ranking-tipo.M { background: linear-gradient(135deg, #3b82f6, #60a5fa); }
    .ranking-tipo.E { background: linear-gradient(135deg, #8b5cf6, #a78bfa); }
    .ranking-tipo.P { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
    .ranking-tipo.R { background: linear-gradient(135deg, #06b6d4, #22d3ee); }
    .ranking-tipo.H { background: linear-gradient(135deg, #10b981, #34d399); }

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

    .ranking-local {
        font-size: 11px;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ranking-stats {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 2px;
    }

    .ranking-valor {
        font-size: 14px;
        font-weight: 700;
    }

    .ranking-valor.bom { color: #16a34a; }
    .ranking-valor.regular { color: #d97706; }
    .ranking-valor.pessimo { color: #dc2626; }

    .ranking-detalhe {
        font-size: 10px;
        color: #94a3b8;
    }

    /* ============================================
       Barra de Progresso
       ============================================ */
    .progress-bar-container {
        width: 80px;
        height: 6px;
        background: #e2e8f0;
        border-radius: 3px;
        overflow: hidden;
    }

    .progress-bar-fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.5s ease;
    }

    .progress-bar-fill.bom { background: linear-gradient(90deg, #10b981, #34d399); }
    .progress-bar-fill.regular { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
    .progress-bar-fill.pessimo { background: linear-gradient(90deg, #ef4444, #f87171); }

    /* ============================================
       Badge de Classificação
       ============================================ */
    .badge-classificacao {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .badge-classificacao.bom { background: #dcfce7; color: #16a34a; }
    .badge-classificacao.regular { background: #fef3c7; color: #d97706; }
    .badge-classificacao.pessimo { background: #fee2e2; color: #dc2626; }

    /* ============================================
       Tabela de Dados
       ============================================ */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .data-table th,
    .data-table td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid #f1f5f9;
    }

    .data-table th {
        font-weight: 600;
        color: #64748b;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: #f8fafc;
    }

    .data-table tr:hover td {
        background: #f8fafc;
    }

    .data-table .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 500;
    }

    .data-table .badge.warning { background: #fef3c7; color: #b45309; }
    .data-table .badge.success { background: #dcfce7; color: #15803d; }
    .data-table .badge.danger { background: #fee2e2; color: #b91c1c; }
    .data-table .badge.calibracao { background: #dbeafe; color: #1d4ed8; }
    .data-table .badge.manutencao { background: #ede9fe; color: #7c3aed; }

    /* ============================================
       Loading e Empty States
       ============================================ */
    .loading-state,
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #64748b;
    }

    .loading-state ion-icon,
    .empty-state ion-icon {
        font-size: 48px;
        margin-bottom: 12px;
        opacity: 0.5;
    }

    .loading-spinner {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    /* ============================================
       Gráfico Container
       ============================================ */
    .grafico-container {
        position: relative;
        height: 250px;
        margin-top: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .grafico-container .empty-state {
        position: absolute;
        width: 100%;
    }

    /* ============================================
       Legenda do Gráfico
       ============================================ */
    .grafico-legenda {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-top: 12px;
        font-size: 12px;
    }

    .grafico-legenda-item {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .grafico-legenda-cor {
        width: 12px;
        height: 12px;
        border-radius: 3px;
    }

    /* ============================================
       Responsivo
       ============================================ */
    @media (max-width: 768px) {
        .page-container { padding: 16px; }
        .page-header { padding: 16px 20px; }
        .page-header-icon { display: none; }
        .page-header h1 { font-size: 18px; }
        .stat-value { font-size: 24px; }
        .content-card-header { padding: 12px 16px; }
        .content-card-body { padding: 12px 16px; }
    }
</style>

<div class="page-container">
    <!-- Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="speedometer-outline"></ion-icon>
                </div>
                <div>
                    <h1>Olá, <?= htmlspecialchars($_SESSION['DS_NOME'] ?? 'Usuário') ?>!</h1>
                    <p class="page-header-subtitle">Bem-vindo ao SIMP - Sistema Integrado de Macromedição e Pitometria</p>
                </div>
            </div>
            <div class="header-date">
                <ion-icon name="calendar-outline"></ion-icon>
                <div class="header-date-info">
                    <span class="header-date-day"><?= $diaSemana ?></span>
                    <span class="header-date-full"><?= date('d/m/Y') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card primary" onclick="window.location.href='pontoMedicao.php'">
            <div class="stat-card-content">
                <div class="stat-info">
                    <h3>Pontos de Medição</h3>
                    <div class="stat-value"><?= number_format($totalPontos, 0, ',', '.') ?></div>
                    <div class="stat-label">
                        <ion-icon name="checkmark-circle"></ion-icon>
                        Ativos no sistema
                    </div>
                </div>
                <div class="stat-icon primary">
                    <ion-icon name="location-outline"></ion-icon>
                </div>
            </div>
        </div>

        <div class="stat-card warning" onclick="window.location.href='programacaoManutencao.php'">
            <div class="stat-card-content">
                <div class="stat-info">
                    <h3>Programações Previstas</h3>
                    <div class="stat-value"><?= number_format($totalManutencoes, 0, ',', '.') ?></div>
                    <div class="stat-label">
                        <ion-icon name="time-outline"></ion-icon>
                        Aguardando execução
                    </div>
                </div>
                <div class="stat-icon warning">
                    <ion-icon name="clipboard-outline"></ion-icon>
                </div>
            </div>
        </div>

        <div class="stat-card success" onclick="window.location.href='programacaoManutencao.php?tipo=1'">
            <div class="stat-card-content">
                <div class="stat-info">
                    <h3>Calibrações Previstas</h3>
                    <div class="stat-value"><?= number_format($totalCalibracoes, 0, ',', '.') ?></div>
                    <div class="stat-label">
                        <ion-icon name="analytics-outline"></ion-icon>
                        Tipo Calibração
                    </div>
                </div>
                <div class="stat-icon success">
                    <ion-icon name="analytics-outline"></ion-icon>
                </div>
            </div>
        </div>

        <div class="stat-card danger" onclick="window.location.href='programacaoManutencao.php?tipo=2'">
            <div class="stat-card-content">
                <div class="stat-info">
                    <h3>Manutenções Previstas</h3>
                    <div class="stat-value"><?= number_format($totalManutencoesTipo2, 0, ',', '.') ?></div>
                    <div class="stat-label">
                        <ion-icon name="build-outline"></ion-icon>
                        Tipo Manutenção
                    </div>
                </div>
                <div class="stat-icon danger">
                    <ion-icon name="construct-outline"></ion-icon>
                </div>
            </div>
        </div>
    </div>

    <!-- Seção de Análise de Qualidade dos Dados -->
    <div class="analise-section">
        <div class="analise-header">
            <div class="analise-title">
                <ion-icon name="pulse-outline"></ion-icon>
                Análise de Qualidade dos Dados
            </div>
            <div class="analise-filtros">
                <select id="filtro-dias">
                    <option value="7" selected>Últimos 7 dias</option>
                    <option value="14">Últimos 14 dias</option>
                    <option value="30">Últimos 30 dias</option>
                    <option value="60">Últimos 60 dias</option>
                </select>
                <select id="filtro-unidade">
                    <option value="">Todas as Unidades</option>
                    <?php foreach ($unidades as $uni): ?>
                    <option value="<?= $uni['CD_UNIDADE'] ?>"><?= htmlspecialchars($uni['DS_NOME']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="btn-carregar-analise" class="btn-carregar" onclick="carregarAnalise()">
                    <ion-icon name="analytics-outline"></ion-icon>
                    Carregar Análise
                </button>
            </div>
        </div>

        <!-- Cards de Resumo -->
        <div class="analise-resumo-grid" id="analise-resumo">
            <div class="analise-card">
                <div class="analise-card-icon info">
                    <ion-icon name="document-text-outline"></ion-icon>
                </div>
                <div class="analise-card-valor" id="total-registros">-</div>
                <div class="analise-card-label">Registros</div>
            </div>
            <div class="analise-card">
                <div class="analise-card-icon roxo">
                    <ion-icon name="trash-outline"></ion-icon>
                </div>
                <div class="analise-card-valor" id="taxa-descarte">-</div>
                <div class="analise-card-label">Taxa Descarte</div>
            </div>
            <div class="analise-card">
                <div class="analise-card-icon bom">
                    <ion-icon name="checkmark-circle-outline"></ion-icon>
                </div>
                <div class="analise-card-valor" id="dias-bom">-</div>
                <div class="analise-card-label">Dias Bom (≥80%)</div>
            </div>
            <div class="analise-card">
                <div class="analise-card-icon regular">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                </div>
                <div class="analise-card-valor" id="dias-regular">-</div>
                <div class="analise-card-label">Dias Regular (≥50%)</div>
            </div>
            <div class="analise-card">
                <div class="analise-card-icon pessimo">
                    <ion-icon name="close-circle-outline"></ion-icon>
                </div>
                <div class="analise-card-valor" id="dias-pessimo">-</div>
                <div class="analise-card-label">Dias Péssimo (&lt;50%)</div>
            </div>
        </div>
    </div>

    <!-- Dashboard Grid com Rankings -->
    <div class="dashboard-grid">
        <!-- Pontos com Maior Taxa de Descarte -->
        <div class="content-card">
            <div class="content-card-header">
                <div class="content-card-title">
                    <ion-icon name="trending-down-outline"></ion-icon>
                    Maior Taxa de Descarte
                </div>
                <a href="registroVazaoPressao.php" class="btn-ver-todos">
                    <ion-icon name="eye-outline"></ion-icon>
                    Ver Todos
                </a>
            </div>
            <div class="content-card-body">
                <ul class="ranking-list" id="ranking-descarte">
                    <li class="empty-state">
                        <ion-icon name="analytics-outline"></ion-icon>
                        <p>Clique em "Carregar Análise" para ver os dados</p>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Pontos com Pior Integridade -->
        <div class="content-card">
            <div class="content-card-header">
                <div class="content-card-title">
                    <ion-icon name="cellular-outline"></ion-icon>
                    Menor Integridade de Dados
                </div>
                <a href="monitoramento.php" class="btn-ver-todos">
                    <ion-icon name="eye-outline"></ion-icon>
                    Monitorar
                </a>
            </div>
            <div class="content-card-body">
                <ul class="ranking-list" id="ranking-integridade">
                    <li class="empty-state">
                        <ion-icon name="cellular-outline"></ion-icon>
                        <p>Clique em "Carregar Análise" para ver os dados</p>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Evolução Diária -->
        <div class="content-card">
            <div class="content-card-header">
                <div class="content-card-title">
                    <ion-icon name="bar-chart-outline"></ion-icon>
                    Evolução Diária - Registros vs Descartes
                </div>
            </div>
            <div class="content-card-body">
                <div class="grafico-container" id="grafico-container">
                    <div class="empty-state" id="grafico-placeholder">
                        <ion-icon name="bar-chart-outline"></ion-icon>
                        <p>Clique em "Carregar Análise" para ver o gráfico</p>
                    </div>
                    <canvas id="grafico-evolucao" style="display: none;"></canvas>
                </div>
                <div class="grafico-legenda" id="grafico-legenda" style="display: none;">
                    <div class="grafico-legenda-item">
                        <div class="grafico-legenda-cor" style="background: #3b82f6;"></div>
                        <span>Registros Válidos</span>
                    </div>
                    <div class="grafico-legenda-item">
                        <div class="grafico-legenda-cor" style="background: #ef4444;"></div>
                        <span>Descartados</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Últimas Programações -->
        <div class="content-card">
            <div class="content-card-header">
                <div class="content-card-title">
                    <ion-icon name="list-outline"></ion-icon>
                    Últimas Programações
                </div>
                <a href="programacaoManutencao.php" class="btn-ver-todos">
                    <ion-icon name="eye-outline"></ion-icon>
                    Ver Todas
                </a>
            </div>
            <div class="content-card-body">
                <?php if (!empty($ultimasManutencoes)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Ponto</th>
                            <th>Tipo</th>
                            <th>Data</th>
                            <th>Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimasManutencoes as $manut): 
                            $sit = $situacoes[$manut['ID_SITUACAO']] ?? ['nome' => 'Desconhecida', 'cor' => 'secondary', 'icone' => 'help-outline'];
                            $tipo = $tiposProgramacao[$manut['ID_TIPO_PROGRAMACAO']] ?? 'Outro';
                            $tipoClasse = $manut['ID_TIPO_PROGRAMACAO'] == 1 ? 'calibracao' : 'manutencao';
                            $codigoFormatado = str_pad($manut['CD_CODIGO'], 3, '0', STR_PAD_LEFT) . '-' . $manut['CD_ANO'] . '/' . $manut['ID_TIPO_PROGRAMACAO'];
                        ?>
                        <tr>
                            <td><strong><?= $codigoFormatado ?></strong></td>
                            <td><?= htmlspecialchars(mb_substr($manut['DS_PONTO'] ?? 'N/I', 0, 25)) ?></td>
                            <td><span class="badge <?= $tipoClasse ?>"><?= $tipo ?></span></td>
                            <td><?= date('d/m/Y', strtotime($manut['DT_PROGRAMACAO'])) ?></td>
                            <td>
                                <span class="badge <?= $sit['cor'] ?>">
                                    <ion-icon name="<?= $sit['icone'] ?>"></ion-icon>
                                    <?= $sit['nome'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <ion-icon name="calendar-outline"></ion-icon>
                    <p>Nenhuma programação encontrada</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    alert('teste');
// Variável global do gráfico
let graficoEvolucao = null;
let dadosCarregados = false;

// Caminho da API
const API_BASE = '/bd/dashboard/getAnaliseQualidade.php';

/**
 * Formata número com separador de milhares
 */
function formatarNumero(num) {
    return new Intl.NumberFormat('pt-BR').format(num);
}

/**
 * Carrega todos os dados de análise
 */
async function carregarAnalise() {
    // Mostrar loading
    document.getElementById('btn-carregar-analise').disabled = true;
    document.getElementById('btn-carregar-analise').innerHTML = '<ion-icon name="sync-outline" class="loading-spinner"></ion-icon> Carregando...';
    
    const dias = document.getElementById('filtro-dias').value;
    const unidade = document.getElementById('filtro-unidade').value;
    
    // Construir parâmetros
    let params = `dias=${dias}`;
    if (unidade) params += `&cd_unidade=${unidade}`;
    
    try {
        // Carregar em paralelo
        await Promise.all([
            carregarResumo(params),
            carregarRankingDescarte(params),
            carregarRankingIntegridade(params),
            carregarEvolucao(params)
        ]);
        
        dadosCarregados = true;
    } catch (error) {
        console.error('Erro ao carregar análise:', error);
    } finally {
        // Restaurar botão
        document.getElementById('btn-carregar-analise').disabled = false;
        document.getElementById('btn-carregar-analise').innerHTML = '<ion-icon name="refresh-outline"></ion-icon> Atualizar Análise';
    }
}

/**
 * Carrega resumo geral
 */
async function carregarResumo(params) {
    // Mostrar loading nos cards
    document.getElementById('total-registros').textContent = '...';
    document.getElementById('taxa-descarte').textContent = '...';
    document.getElementById('dias-bom').textContent = '...';
    document.getElementById('dias-regular').textContent = '...';
    document.getElementById('dias-pessimo').textContent = '...';
    
    try {
        const response = await fetch(`${API_BASE}?tipo=resumo&${params}`);
        const text = await response.text();
        
        // Debug - mostrar resposta bruta se não for JSON válido
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Resposta não é JSON válido:', text);
            return;
        }
        
        if (data.success) {
            document.getElementById('total-registros').textContent = formatarNumero(data.totais.total_registros);
            document.getElementById('taxa-descarte').textContent = (data.totais.taxa_descarte_geral || 0).toFixed(1) + '%';
            document.getElementById('dias-bom').textContent = formatarNumero(data.integridade_diaria.BOM || 0);
            document.getElementById('dias-regular').textContent = formatarNumero(data.integridade_diaria.REGULAR || 0);
            document.getElementById('dias-pessimo').textContent = formatarNumero(data.integridade_diaria.PESSIMO || 0);
        } else {
            console.error('Erro no resumo:', data.message);
        }
    } catch (error) {
        console.error('Erro ao carregar resumo:', error);
    }
}

/**
 * Carrega ranking de descarte
 */
async function carregarRankingDescarte(params) {
    const container = document.getElementById('ranking-descarte');
    
    // Mostrar loading
    container.innerHTML = `<li class="loading-state"><ion-icon name="sync-outline" class="loading-spinner"></ion-icon><p>Carregando...</p></li>`;
    
    try {
        const response = await fetch(`${API_BASE}?tipo=descarte&limite=5&${params}`);
        const text = await response.text();
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Resposta descarte não é JSON:', text);
            container.innerHTML = `<li class="empty-state"><ion-icon name="alert-circle-outline"></ion-icon><p>Erro: resposta inválida</p></li>`;
            return;
        }
        
        if (data.success && data.data && data.data.length > 0) {
            container.innerHTML = data.data.map((item, idx) => `
                <li class="ranking-item" onclick="window.location.href='registroVazaoPressao.php?cdPonto=${item.CD_PONTO_MEDICAO}'">
                    <div class="ranking-posicao ${idx < 3 ? 'top' + (idx + 1) : 'normal'}">${idx + 1}</div>
                    <div class="ranking-tipo ${item.TIPO_MEDIDOR_LETRA}">${item.TIPO_MEDIDOR_LETRA}</div>
                    <div class="ranking-info">
                        <div class="ranking-nome">${item.CD_PONTO_MEDICAO} - ${item.DS_PONTO || 'N/I'}</div>
                        <div class="ranking-local">${item.DS_UNIDADE || ''} ${item.DS_LOCALIDADE ? '• ' + item.DS_LOCALIDADE : ''}</div>
                    </div>
                    <div class="ranking-stats">
                        <div class="ranking-valor pessimo">${(item.TAXA_DESCARTE || 0).toFixed(1)}%</div>
                        <div class="ranking-detalhe">${formatarNumero(item.REGISTROS_DESCARTADOS || 0)} descartados</div>
                    </div>
                </li>
            `).join('');
        } else {
            container.innerHTML = `
                <li class="empty-state">
                    <ion-icon name="checkmark-circle-outline"></ion-icon>
                    <p>${data.message || 'Nenhum descarte no período'}</p>
                </li>
            `;
        }
    } catch (error) {
        console.error('Erro ao carregar ranking descarte:', error);
        container.innerHTML = `
            <li class="empty-state">
                <ion-icon name="alert-circle-outline"></ion-icon>
                <p>Erro ao carregar dados</p>
            </li>
        `;
    }
}

/**
 * Carrega ranking de integridade (piores)
 */
async function carregarRankingIntegridade(params) {
    const container = document.getElementById('ranking-integridade');
    
    // Mostrar loading
    container.innerHTML = `<li class="loading-state"><ion-icon name="sync-outline" class="loading-spinner"></ion-icon><p>Carregando...</p></li>`;
    
    try {
        const response = await fetch(`${API_BASE}?tipo=integridade&limite=5&ordem=piores&${params}`);
        const text = await response.text();
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Resposta integridade não é JSON:', text);
            container.innerHTML = `<li class="empty-state"><ion-icon name="alert-circle-outline"></ion-icon><p>Erro: resposta inválida</p></li>`;
            return;
        }
        
        if (data.success && data.data && data.data.length > 0) {
            container.innerHTML = data.data.map((item, idx) => {
                const classificacao = (item.CLASSIFICACAO || 'PESSIMO').toLowerCase();
                const percentual = item.PERCENTUAL_INTEGRIDADE || 0;
                
                return `
                <li class="ranking-item" onclick="window.location.href='registroVazaoPressao.php?cdPonto=${item.CD_PONTO_MEDICAO}'">
                    <div class="ranking-posicao ${idx < 3 ? 'top' + (idx + 1) : 'normal'}">${idx + 1}</div>
                    <div class="ranking-tipo ${item.TIPO_MEDIDOR_LETRA}">${item.TIPO_MEDIDOR_LETRA}</div>
                    <div class="ranking-info">
                        <div class="ranking-nome">${item.CD_PONTO_MEDICAO} - ${item.DS_PONTO || 'N/I'}</div>
                        <div class="ranking-local">${item.DS_UNIDADE || ''} ${item.DS_LOCALIDADE ? '• ' + item.DS_LOCALIDADE : ''}</div>
                    </div>
                    <div class="ranking-stats">
                        <div class="ranking-valor ${classificacao}">${percentual.toFixed(1)}%</div>
                        <div class="ranking-detalhe">
                            <span class="badge-classificacao ${classificacao}">${item.CLASSIFICACAO || 'N/I'}</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill ${classificacao}" style="width: ${Math.min(percentual, 100)}%"></div>
                        </div>
                    </div>
                </li>
            `}).join('');
        } else {
            container.innerHTML = `
                <li class="empty-state">
                    <ion-icon name="analytics-outline"></ion-icon>
                    <p>${data.message || 'Sem dados no período'}</p>
                </li>
            `;
        }
    } catch (error) {
        console.error('Erro ao carregar ranking integridade:', error);
        container.innerHTML = `
            <li class="empty-state">
                <ion-icon name="alert-circle-outline"></ion-icon>
                <p>Erro ao carregar dados</p>
            </li>
        `;
    }
}

/**
 * Carrega e renderiza gráfico de evolução
 */
async function carregarEvolucao(params) {
    try {
        const response = await fetch(`${API_BASE}?tipo=evolucao&${params}`);
        const text = await response.text();
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Resposta evolução não é JSON:', text);
            return;
        }
        
        if (data.success && data.data && data.data.length > 0) {
            renderizarGrafico(data.data);
        } else {
            console.log('Sem dados para gráfico:', data.message);
        }
    } catch (error) {
        console.error('Erro ao carregar evolução:', error);
    }
}

/**
 * Renderiza gráfico com Chart.js
 */
function renderizarGrafico(dados) {
    // Esconder placeholder e mostrar canvas
    const placeholder = document.getElementById('grafico-placeholder');
    const canvas = document.getElementById('grafico-evolucao');
    const legenda = document.getElementById('grafico-legenda');
    
    if (placeholder) placeholder.style.display = 'none';
    if (canvas) canvas.style.display = 'block';
    if (legenda) legenda.style.display = 'flex';
    
    const ctx = canvas.getContext('2d');
    
    // Destruir gráfico anterior se existir
    if (graficoEvolucao) {
        graficoEvolucao.destroy();
    }
    
    const labels = dados.map(d => {
        const dt = new Date(d.DT_DIA);
        return dt.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    });
    
    const validos = dados.map(d => d.REGISTROS_VALIDOS);
    const descartados = dados.map(d => d.REGISTROS_DESCARTADOS);
    
    graficoEvolucao = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Registros Válidos',
                    data: validos,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: '#3b82f6',
                    borderWidth: 1,
                    borderRadius: 4
                },
                {
                    label: 'Descartados',
                    data: descartados,
                    backgroundColor: 'rgba(239, 68, 68, 0.8)',
                    borderColor: '#ef4444',
                    borderWidth: 1,
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleFont: { size: 12 },
                    bodyFont: { size: 11 },
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + formatarNumero(context.raw);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { 
                        font: { size: 10 },
                        color: '#64748b'
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: { 
                        font: { size: 10 },
                        color: '#64748b',
                        callback: function(value) {
                            return formatarNumero(value);
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
}

// Inicialização - NÃO carrega dados automaticamente (consultas pesadas)
document.addEventListener('DOMContentLoaded', function() {
    // Página pronta - dados serão carregados ao clicar no botão
    console.log('Página carregada. Clique em "Carregar Análise" para buscar os dados.');
});
</script>

<?php include_once 'includes/footer.inc.php'; ?>
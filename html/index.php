<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Dashboard / Página Inicial
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Buscar dados para o dashboard
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
    
    // Últimas programações
    $stmtUltimasManutencoes = $pdoSIMP->query("
        SELECT TOP 5 
            PM.CD_CHAVE,
            PM.CD_CODIGO,
            PM.CD_ANO,
            PM.DT_PROGRAMACAO,
            PM.ID_SITUACAO,
            PM.ID_TIPO_PROGRAMACAO,
            P.DS_NOME AS PONTO_NOME
        FROM SIMP.dbo.PROGRAMACAO_MANUTENCAO PM
        INNER JOIN SIMP.dbo.PONTO_MEDICAO P ON PM.CD_PONTO_MEDICAO = P.CD_PONTO_MEDICAO
        ORDER BY PM.DT_PROGRAMACAO DESC
    ");
    $ultimasManutencoes = $stmtUltimasManutencoes->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Valores padrão em caso de erro
    $totalPontos = $totalManutencoes = $totalCalibracoes = $totalManutencoesTipo2 = 0;
    $ultimasManutencoes = [];
}

// Mapear situações (conforme seu sistema)
$situacoes = [
    1 => ['nome' => 'Prevista', 'cor' => 'warning', 'icone' => 'time-outline'],
    2 => ['nome' => 'Executada', 'cor' => 'success', 'icone' => 'checkmark-circle-outline'],
    4 => ['nome' => 'Cancelada', 'cor' => 'danger', 'icone' => 'close-circle-outline'],
];

$tiposProgramacao = [
    1 => 'Calibração',
    2 => 'Manutenção',
];

// Dia da semana em português
$diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
$diaSemana = $diasSemana[date('w')];
?>

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
       Page Header (mesmo padrão do pontoMedicao)
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

    .header-date {
        display: flex;
        align-items: center;
        gap: 12px;
        background: rgba(255, 255, 255, 0.1);
        padding: 12px 20px;
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .header-date ion-icon {
        font-size: 20px;
        color: rgba(255, 255, 255, 0.8);
    }

    .header-date-info {
        display: flex;
        flex-direction: column;
    }

    .header-date-day {
        font-size: 14px;
        font-weight: 600;
        color: white;
    }

    .header-date-full {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.7);
    }

    /* ============================================
       Stats Cards
       ============================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px;
        position: relative;
        overflow: hidden;
        transition: all 0.2s ease;
        cursor: pointer;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        border-color: #cbd5e1;
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
       Dashboard Grid (2 colunas)
       ============================================ */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
    }

    /* ============================================
       Content Cards (mesmo padrão filters-card)
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
        padding: 20px 24px;
        border-bottom: 1px solid #f1f5f9;
    }

    .content-card-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        font-weight: 600;
        color: #334155;
    }

    .content-card-title ion-icon {
        font-size: 18px;
        color: #3b82f6;
    }

    .btn-ver-todos {
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
        text-decoration: none;
    }

    .btn-ver-todos:hover {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
    }

    .content-card-body {
        padding: 0;
    }

    /* ============================================
       Data Table (mesmo padrão do sistema)
       ============================================ */
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table thead th {
        text-align: left;
        padding: 14px 16px;
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .data-table tbody tr {
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.2s ease;
        cursor: pointer;
    }

    .data-table tbody tr:hover {
        background: #f8fafc;
    }

    .data-table tbody tr:last-child {
        border-bottom: none;
    }

    .data-table tbody td {
        padding: 16px;
        color: #334155;
        font-size: 13px;
    }

    .data-table .code {
        font-family: 'SF Mono', Monaco, monospace;
        font-weight: 600;
        color: #1e293b;
    }

    /* ============================================
       Badges (mesmo padrão do sistema)
       ============================================ */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 500;
    }

    .badge-tipo {
        background: #f0f9ff;
        color: #0369a1;
    }

    .badge-tipo.calibracao {
        background: #faf5ff;
        color: #7c3aed;
    }

    .badge-tipo.manutencao {
        background: #f0f9ff;
        color: #0369a1;
    }

    .badge-status {
        padding: 5px 12px;
        border-radius: 20px;
    }

    .badge-status.warning {
        background: #fffbeb;
        color: #b45309;
    }

    .badge-status.success {
        background: #ecfdf5;
        color: #047857;
    }

    .badge-status.danger {
        background: #fef2f2;
        color: #b91c1c;
    }

    /* ============================================
       Empty State
       ============================================ */
    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 48px 24px;
        text-align: center;
    }

    .empty-state-icon {
        width: 64px;
        height: 64px;
        background: #f1f5f9;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
    }

    .empty-state-icon ion-icon {
        font-size: 28px;
        color: #94a3b8;
    }

    .empty-state h3 {
        font-size: 14px;
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
       Quick Actions
       ============================================ */
    .quick-actions {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 20px 24px;
    }

    .quick-action-btn {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        color: #334155;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .quick-action-btn:hover {
        background: #3b82f6;
        border-color: #3b82f6;
        color: white;
    }

    .quick-action-btn:hover .action-icon {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }

    .quick-action-btn:hover .action-text p {
        color: rgba(255, 255, 255, 0.8);
    }

    .quick-action-btn:hover .action-arrow {
        color: white;
    }

    .action-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        background: #e0f2fe;
        color: #0369a1;
        transition: all 0.2s ease;
    }

    .action-text {
        flex: 1;
    }

    .action-text h4 {
        margin: 0 0 2px 0;
        font-size: 13px;
        font-weight: 600;
    }

    .action-text p {
        margin: 0;
        font-size: 11px;
        color: #64748b;
        transition: color 0.2s ease;
    }

    .action-arrow {
        font-size: 18px;
        color: #94a3b8;
        transition: all 0.2s ease;
    }

    /* ============================================
       Status Widget
       ============================================ */
    .status-widget {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 20px 24px;
    }

    .status-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        background: #f8fafc;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
    }

    .status-item-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    .status-dot.success { background: #10b981; }
    .status-dot.warning { background: #f59e0b; }
    .status-dot.danger { background: #ef4444; }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .status-item-label {
        font-size: 13px;
        color: #475569;
    }

    .status-item-value {
        font-size: 13px;
        font-weight: 600;
        color: #334155;
    }

    .status-item-value.success { color: #059669; }
    .status-item-value.warning { color: #d97706; }

    /* ============================================
       Responsive
       ============================================ */
    @media (max-width: 1200px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
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

        .header-date {
            justify-content: center;
        }

        .stats-grid {
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .stat-card {
            padding: 20px;
        }

        .stat-value {
            font-size: 28px;
        }

        .data-table th,
        .data-table td {
            padding: 12px;
            font-size: 12px;
        }

        .content-card-header {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }

        .btn-ver-todos {
            width: 100%;
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .page-container {
            padding: 12px;
        }

        .page-header {
            padding: 16px;
        }

        .page-header h1 {
            font-size: 16px;
        }

        .stat-card {
            padding: 16px;
        }

        .stat-value {
            font-size: 24px;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            font-size: 18px;
        }
    }
</style>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="speedometer-outline"></ion-icon>
                </div>
                <div>
                    <h1>Olá, <?= htmlspecialchars($_SESSION['nome'] ?? 'Usuário') ?>!</h1>
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

    <!-- Dashboard Grid -->
    <div class="dashboard-grid">
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
                            // Código formatado: CD_CODIGO-CD_ANO/ID_TIPO_PROGRAMACAO
                            $codigoFormatado = str_pad($manut['CD_CODIGO'], 3, '0', STR_PAD_LEFT) . '-' . $manut['CD_ANO'] . '/' . $manut['ID_TIPO_PROGRAMACAO'];
                        ?>
                        <tr onclick="window.location.href='programacaoManutencaoView.php?id=<?= $manut['CD_CHAVE'] ?>'">
                            <td class="code"><?= $codigoFormatado ?></td>
                            <td><?= htmlspecialchars($manut['PONTO_NOME'] ?? 'N/A') ?></td>
                            <td>
                                <span class="badge badge-tipo <?= $tipoClasse ?>">
                                    <ion-icon name="<?= $tipoClasse == 'calibracao' ? 'analytics-outline' : 'build-outline' ?>"></ion-icon>
                                    <?= $tipo ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($manut['DT_PROGRAMACAO'])) ?></td>
                            <td>
                                <span class="badge badge-status <?= $sit['cor'] ?>">
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
                    <div class="empty-state-icon">
                        <ion-icon name="calendar-outline"></ion-icon>
                    </div>
                    <h3>Nenhuma programação encontrada</h3>
                    <p>As programações de manutenção e calibração aparecerão aqui</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Coluna Direita -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <!-- Ações Rápidas -->
            <div class="content-card">
                <div class="content-card-header">
                    <div class="content-card-title">
                        <ion-icon name="flash-outline"></ion-icon>
                        Ações Rápidas
                    </div>
                </div>
                <div class="quick-actions">
                    <a href="programacaoManutencaoForm.php" class="quick-action-btn">
                        <div class="action-icon">
                            <ion-icon name="add-outline"></ion-icon>
                        </div>
                        <div class="action-text">
                            <h4>Nova Programação</h4>
                            <p>Criar manutenção ou calibração</p>
                        </div>
                        <ion-icon name="chevron-forward-outline" class="action-arrow"></ion-icon>
                    </a>

                    <a href="pontoMedicaoForm.php" class="quick-action-btn">
                        <div class="action-icon">
                            <ion-icon name="location-outline"></ion-icon>
                        </div>
                        <div class="action-text">
                            <h4>Novo Ponto</h4>
                            <p>Cadastrar ponto de medição</p>
                        </div>
                        <ion-icon name="chevron-forward-outline" class="action-arrow"></ion-icon>
                    </a>

                    <a href="cadastrosAuxiliares.php" class="quick-action-btn">
                        <div class="action-icon">
                            <ion-icon name="settings-outline"></ion-icon>
                        </div>
                        <div class="action-text">
                            <h4>Configurações</h4>
                            <p>Cadastros auxiliares</p>
                        </div>
                        <ion-icon name="chevron-forward-outline" class="action-arrow"></ion-icon>
                    </a>
                </div>
            </div>

            <!-- Status do Sistema -->
            <div class="content-card">
                <div class="content-card-header">
                    <div class="content-card-title">
                        <ion-icon name="pulse-outline"></ion-icon>
                        Status do Sistema
                    </div>
                </div>
                <div class="status-widget">
                    <div class="status-item">
                        <div class="status-item-info">
                            <span class="status-dot success"></span>
                            <span class="status-item-label">Banco de Dados</span>
                        </div>
                        <span class="status-item-value success">Online</span>
                    </div>

                    <div class="status-item">
                        <div class="status-item-info">
                            <span class="status-dot success"></span>
                            <span class="status-item-label">Servidor LDAP</span>
                        </div>
                        <span class="status-item-value success">Conectado</span>
                    </div>

                    <div class="status-item">
                        <div class="status-item-info">
                            <span class="status-dot <?= $totalManutencoes > 0 ? 'warning' : 'success' ?>"></span>
                            <span class="status-item-label">Programações Previstas</span>
                        </div>
                        <span class="status-item-value <?= $totalManutencoes > 0 ? 'warning' : 'success' ?>">
                            <?= $totalManutencoes > 0 ? $totalManutencoes . ' pendente(s)' : 'Nenhuma' ?>
                        </span>
                    </div>

                    <div class="status-item">
                        <div class="status-item-info">
                            <span class="status-dot success"></span>
                            <span class="status-item-label">Seu Grupo</span>
                        </div>
                        <span class="status-item-value"><?= htmlspecialchars($_SESSION['grupo'] ?? 'N/A') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.inc.php'; ?>
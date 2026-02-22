<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Modelos de Machine Learning (XGBoost)
 * 
 * Tela para gerenciar modelos treinados:
 *  - Listar modelos existentes com métricas
 *  - Treinar novos modelos por ponto de medição
 *  - Retreinar modelos existentes (force)
 *  - Visualizar métricas detalhadas (MAE, RMSE, R², MAPE, features)
 * 
 * Usa endpoints existentes do predicaoTensorFlow.php:
 *  - acao=status  → lista modelos
 *  - acao=train   → treina/retreina modelo
 *  - acao=health  → verifica serviço online
 * 
 * @author Bruno - CESAN
 * @version 1.0
 * @date 2026-02
 */

$paginaAtual = 'modelosML';

include_once 'includes/header.inc.php';
include_once 'bd/conexao.php';

// Recarregar permissões do banco
recarregarPermissoesUsuario();

// Permissão (usa mesma permissão de Treinamento IA)
exigePermissaoTela('Treinamento IA', ACESSO_LEITURA);
$podeEditar = podeEditarTela('Treinamento IA');

include_once 'includes/menu.inc.php';

// Buscar pontos de medição ativos (para dropdown de treino)
$pontosMedicao = [];
try {
    $sqlPontos = "
        SELECT 
            PM.CD_PONTO_MEDICAO,
            PM.DS_NOME,
            PM.ID_TIPO_MEDIDOR,
            TM.DS_NOME AS DS_TIPO_MEDIDOR,
            L.DS_NOME AS DS_LOCALIDADE,
            U.DS_NOME AS DS_UNIDADE
        FROM SIMP.dbo.PONTO_MEDICAO PM
        LEFT JOIN SIMP.dbo.TIPO_MEDIDOR TM ON TM.CD_CHAVE = PM.ID_TIPO_MEDIDOR
        LEFT JOIN SIMP.dbo.LOCALIDADE L ON L.CD_CHAVE = PM.CD_LOCALIDADE
        LEFT JOIN SIMP.dbo.UNIDADE U ON U.CD_UNIDADE = L.CD_UNIDADE
        WHERE PM.DT_DESATIVACAO IS NULL
        ORDER BY PM.DS_NOME
    ";
    $stmtPontos = $pdoSIMP->query($sqlPontos);
    $pontosMedicao = $stmtPontos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pontosMedicao = [];
}

// Buscar sistemas de água do flowchart (nós cujo nível tem OP_EH_SISTEMA = 1)
$sistemasFlowchart = [];
try {
    $sqlSistemas = "
        SELECT 
            EN.CD_CHAVE,
            EN.DS_NOME AS DS_SISTEMA_AGUA
        FROM SIMP.dbo.ENTIDADE_NODO EN
        INNER JOIN SIMP.dbo.ENTIDADE_NIVEL NV 
            ON NV.CD_CHAVE = EN.CD_ENTIDADE_NIVEL
        WHERE EN.OP_ATIVO = 1
          AND NV.OP_EH_SISTEMA = 1
        ORDER BY EN.DS_NOME
    ";
    $stmtSis = $pdoSIMP->query($sqlSistemas);
    $sistemasFlowchart = $stmtSis->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sistemasFlowchart = [];
}
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

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
       Page Header (padrão SIMP)
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

    .page-header-actions {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    /* Badge de status do serviço TensorFlow */
    .service-badge {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, 0.15);
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 500;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .service-badge .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #94a3b8;
        animation: pulse-dot 2s infinite;
    }

    .service-badge .status-dot.online {
        background: #22c55e;
    }

    .service-badge .status-dot.offline {
        background: #ef4444;
        animation: none;
    }

    @keyframes pulse-dot {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }
    }

    /* Botão novo treino */
    .btn-novo-treino {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-novo-treino:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-1px);
    }

    .btn-novo-treino ion-icon {
        font-size: 18px;
    }

    /* ============================================
       Stats Cards (resumo)
       ============================================ */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .stat-card-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    .stat-card-icon.total {
        background: #eff6ff;
        color: #3b82f6;
    }

    .stat-card-icon.xgboost {
        background: #fef3c7;
        color: #f59e0b;
    }

    .stat-card-icon.good {
        background: #dcfce7;
        color: #16a34a;
    }

    .stat-card-icon.bad {
        background: #fee2e2;
        color: #dc2626;
    }

    .stat-card-info h3 {
        font-size: 22px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
    }

    .stat-card-info p {
        font-size: 12px;
        color: #64748b;
        margin: 2px 0 0 0;
    }

    /* ============================================
       Filtros
       ============================================ */
    .filters-bar {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 20px;
        background: white;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        margin-bottom: 24px;
    }

    .search-input-wrapper {
        position: relative;
        flex: 1;
        min-width: 200px;
        flex-shrink: 1;
    }

    .search-input-wrapper ion-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 18px;
    }

    .search-input {
        width: 100%;
        padding: 10px 12px 10px 40px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 13px;
        background: white;
        transition: all 0.2s ease;
        box-sizing: border-box;
    }

    .search-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .filter-select {
        padding: 10px 32px 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 13px;
        background: white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 10px center;
        -webkit-appearance: none;
        appearance: none;
        cursor: pointer;
        min-width: 170px;
        flex-shrink: 0;
    }

    .filter-select:focus {
        outline: none;
        border-color: #3b82f6;
    }

    .btn-refresh {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 500;
        color: #475569;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .btn-refresh:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    @media (max-width: 768px) {
        .filters-bar {
            flex-wrap: wrap;
        }

        .search-input-wrapper {
            width: 100%;
        }

        .filter-select {
            flex: 1;
            min-width: 0;
        }

        .btn-refresh {
            width: 100%;
            justify-content: center;
            margin-left: 0;
        }
    }

    .search-input-wrapper {
        position: relative;
        flex: 1;
        min-width: 200px;
        max-width: 400px;
    }

    .search-input-wrapper ion-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 18px;
    }

    .search-input {
        width: 100%;
        padding: 10px 12px 10px 40px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 13px;
        background: white;
        transition: all 0.2s ease;
    }

    .search-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .filter-select {
        padding: 10px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 13px;
        background: white;
        cursor: pointer;
        min-width: 160px;
    }

    .btn-refresh {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 500;
        color: #475569;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-refresh:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    .btn-refresh.loading ion-icon {
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

    /* ============================================
       Grid de Modelos
       ============================================ */
    .models-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
        gap: 16px;
    }

    .model-card {
        background: white;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
        overflow: hidden;
        transition: all 0.2s ease;
    }

    .model-card:hover {
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        transform: translateY(-2px);
    }

    /* Cabeçalho do card */
    .model-card-header {
        padding: 16px 20px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .model-card-title {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .model-card-title .ponto-id {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
        font-size: 11px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 6px;
        font-family: 'SF Mono', Monaco, monospace;
    }

    .model-card-title .ponto-nome {
        font-size: 13px;
        font-weight: 600;
        color: #0f172a;
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .model-card-tipo {
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        padding: 3px 8px;
        border-radius: 4px;
        letter-spacing: 0.5px;
    }

    .model-card-tipo.xgboost {
        background: #fef3c7;
        color: #92400e;
    }

    .model-card-tipo.lstm {
        background: #fce7f3;
        color: #9d174d;
    }

    /* Corpo do card - métricas */
    .model-card-body {
        padding: 16px 20px;
    }

    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 14px;
    }

    .metric-item {
        text-align: center;
    }

    .metric-item .metric-value {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
    }

    .metric-item .metric-label {
        font-size: 10px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 2px;
    }

    /* Qualidade do modelo (barra colorida) */
    .model-quality {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 14px;
    }

    .quality-bar {
        flex: 1;
        height: 6px;
        background: #f1f5f9;
        border-radius: 3px;
        overflow: hidden;
    }

    .quality-bar-fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.5s ease;
    }

    .quality-bar-fill.excellent {
        background: linear-gradient(90deg, #22c55e, #16a34a);
    }

    .quality-bar-fill.good {
        background: linear-gradient(90deg, #3b82f6, #2563eb);
    }

    .quality-bar-fill.fair {
        background: linear-gradient(90deg, #f59e0b, #d97706);
    }

    .quality-bar-fill.poor {
        background: linear-gradient(90deg, #ef4444, #dc2626);
    }

    .quality-label {
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
    }

    .quality-label.excellent {
        color: #16a34a;
    }

    .quality-label.good {
        color: #2563eb;
    }

    .quality-label.fair {
        color: #d97706;
    }

    .quality-label.poor {
        color: #dc2626;
    }

    /* Informações extras */
    .model-info-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 6px 0;
        font-size: 12px;
        color: #64748b;
        border-top: 1px solid #f1f5f9;
    }

    .model-info-row:first-child {
        border-top: none;
    }

    .model-info-row .info-label {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .model-info-row .info-label ion-icon {
        font-size: 14px;
        color: #94a3b8;
    }

    .model-info-row .info-value {
        font-weight: 600;
        color: #334155;
    }

    /* Footer do card - ações */
    .model-card-footer {
        padding: 10px 16px;
        background: #fafbfc;
        border-top: 1px solid #e2e8f0;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .btn-model-action {
        flex: 1 1 auto;
        min-width: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        padding: 7px 8px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    .btn-model-action ion-icon {
        font-size: 14px;
        flex-shrink: 0;
    }

    .btn-model-action {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid transparent;
    }

    .btn-model-action.btn-details {
        background: #f1f5f9;
        color: #475569;
        border-color: #e2e8f0;
    }

    .btn-model-action.btn-details:hover {
        background: #e2e8f0;
    }

    .btn-model-action.btn-retrain {
        background: #eff6ff;
        color: #2563eb;
        border-color: #bfdbfe;
    }

    .btn-model-action.btn-retrain:hover {
        background: #dbeafe;
    }

    .btn-model-action.btn-delete {
        background: #fef2f2;
        color: #dc2626;
        border-color: #fecaca;
    }

    .btn-model-action.btn-delete:hover {
        background: #fee2e2;
    }

    .btn-model-action.btn-retrain.loading {
        opacity: 0.7;
        pointer-events: none;
    }

    .btn-model-action ion-icon {
        font-size: 15px;
    }

    /* ============================================
       Empty State
       ============================================ */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
    }

    .empty-state-icon {
        width: 64px;
        height: 64px;
        background: #f1f5f9;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
        font-size: 28px;
        color: #94a3b8;
    }

    .empty-state h3 {
        font-size: 16px;
        font-weight: 600;
        color: #334155;
        margin: 0 0 8px 0;
    }

    .empty-state p {
        font-size: 13px;
        color: #94a3b8;
        margin: 0;
    }

    /* ============================================
       Loading Overlay
       ============================================ */
    .loading-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.3);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }

    .loading-overlay.active {
        display: flex;
    }

    .loading-box {
        background: white;
        border-radius: 16px;
        padding: 40px;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    }

    .loading-box ion-icon {
        font-size: 36px;
        color: #3b82f6;
        animation: spin 1.5s linear infinite;
    }

    .loading-box p {
        margin: 12px 0 0 0;
        font-size: 14px;
        color: #475569;
        font-weight: 500;
    }

    .loading-box .loading-sub {
        font-size: 12px;
        color: #94a3b8;
        margin-top: 4px;
    }

    /* ============================================
       Modal de Detalhes
       ============================================ */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.4);
        z-index: 9998;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-container {
        background: white;
        border-radius: 16px;
        width: 100%;
        max-width: 700px;
        max-height: 85vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
        padding: 20px 24px;
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-radius: 16px 16px 0 0;
    }

    .modal-header h2 {
        font-size: 16px;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-header .btn-close {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        transition: all 0.2s ease;
    }

    .modal-header .btn-close:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    .modal-body {
        padding: 24px;
    }

    .modal-section {
        margin-bottom: 20px;
    }

    .modal-section-title {
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .modal-section-title ion-icon {
        font-size: 16px;
    }

    /* Métricas no modal */
    .modal-metrics-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
    }

    .modal-metric {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 14px;
        text-align: center;
    }

    .modal-metric .value {
        font-size: 20px;
        font-weight: 700;
        color: #0f172a;
    }

    .modal-metric .label {
        font-size: 11px;
        color: #94a3b8;
        margin-top: 2px;
    }

    .modal-metric .hint {
        font-size: 10px;
        color: #cbd5e1;
        margin-top: 4px;
    }

    /* Feature Importance no modal */
    .feature-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .feature-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .feature-item:last-child {
        border-bottom: none;
    }

    .feature-rank {
        width: 22px;
        height: 22px;
        border-radius: 6px;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: 700;
        color: #64748b;
        flex-shrink: 0;
    }

    .feature-rank.top {
        background: #fef3c7;
        color: #92400e;
    }

    .feature-name {
        flex: 1;
        font-size: 12px;
        color: #334155;
        font-family: 'SF Mono', Monaco, monospace;
    }

    .feature-bar-wrapper {
        width: 120px;
        flex-shrink: 0;
    }

    .feature-bar {
        height: 6px;
        background: #f1f5f9;
        border-radius: 3px;
        overflow: hidden;
    }

    .feature-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #3b82f6, #2563eb);
        border-radius: 3px;
    }

    .feature-value {
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        width: 50px;
        text-align: right;
        flex-shrink: 0;
    }

    /* Informações gerais no modal */
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .info-item {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .info-item .label {
        font-size: 11px;
        color: #94a3b8;
    }

    .info-item .value {
        font-size: 13px;
        font-weight: 600;
        color: #334155;
    }

    /* Tags auxiliares no modal */
    .tags-list {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .tag-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 11px;
        font-family: 'SF Mono', Monaco, monospace;
        color: #475569;
    }

    /* ============================================
       Modal de Treino
       ============================================ */
    .train-form-group {
        margin-bottom: 16px;
    }

    .train-form-group label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #334155;
        margin-bottom: 6px;
    }

    .train-form-group label ion-icon {
        font-size: 14px;
        vertical-align: middle;
        margin-right: 4px;
    }

    .train-form-group select,
    .train-form-group input {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 13px;
        background: white;
        transition: all 0.2s ease;
    }

    .train-form-group select:focus,
    .train-form-group input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .train-form-hint {
        font-size: 11px;
        color: #94a3b8;
        margin-top: 4px;
    }

    .modal-footer {
        padding: 16px 24px;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .btn-modal {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 20px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
    }

    .btn-modal.btn-cancel {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .btn-modal.btn-cancel:hover {
        background: #e2e8f0;
    }

    .btn-modal.btn-primary {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
    }

    .btn-modal.btn-primary:hover {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        transform: translateY(-1px);
    }

    .btn-modal.btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    /* ============================================
       Select2 customização (padrão SIMP)
       ============================================ */
    .select2-container--default .select2-selection--single {
        height: 42px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 6px 14px;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 28px;
        font-size: 13px;
        color: #334155;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px;
    }

    .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
        background-color: #3b82f6;
    }

    .select2-dropdown {
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    }

    .select2-search__field {
        border-radius: 8px !important;
        padding: 8px 12px !important;
    }

    /* ============================================
       Responsivo
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

        .page-header-actions {
            width: 100%;
            flex-wrap: wrap;
        }

        .stats-row {
            grid-template-columns: repeat(2, 1fr);
        }

        .models-grid {
            grid-template-columns: 1fr;
        }


        .search-input-wrapper {
            min-width: 100%;
        }

        .filter-select {
            width: 100%;
        }

        .modal-container {
            max-height: 95vh;
        }

        .modal-metrics-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .metrics-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .info-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        .stats-row {
            grid-template-columns: 1fr;
        }

        .modal-metrics-grid {
            grid-template-columns: 1fr;
        }
    }

    /* ============================================
       Modal de Associações
       ============================================ */
    .assoc-layout {
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 16px;
        min-height: 420px;
    }

    .assoc-lista-panel {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .assoc-lista-header {
        padding: 12px 16px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        font-weight: 600;
        color: #334155;
    }

    .assoc-lista-header ion-icon {
        font-size: 16px;
        color: #3b82f6;
    }

    .assoc-lista-search {
        padding: 8px 12px;
        border-bottom: 1px solid #e2e8f0;
    }

    .assoc-lista-search input {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 12px;
        outline: none;
        box-sizing: border-box;
    }

    .assoc-lista-search input:focus {
        border-color: #3b82f6;
    }

    .assoc-lista-body {
        flex: 1;
        overflow-y: auto;
        max-height: 380px;
    }

    .assoc-lista-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 12px;
        cursor: pointer;
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.15s ease;
        font-size: 12px;
        gap: 8px;
    }

    .assoc-lista-item:hover {
        background: #f8fafc;
    }

    .assoc-lista-item.active {
        background: #eff6ff;
        border-left: 3px solid #3b82f6;
    }

    .assoc-lista-item .tag-info {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        flex: 1;
        min-width: 0;
    }

    .assoc-lista-item .tag-name {
        font-family: 'SF Mono', Monaco, monospace;
        font-weight: 500;
        color: #334155;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 11px;
    }

    .assoc-lista-item .tag-ponto {
        font-size: 10px;
        color: #94a3b8;
        margin-top: 1px;
    }

    .assoc-lista-item .tag-count {
        background: #e2e8f0;
        color: #475569;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 10px;
        flex-shrink: 0;
    }

    .assoc-lista-item.active .tag-count {
        background: #3b82f6;
        color: white;
    }

    .assoc-lista-empty {
        padding: 30px 16px;
        text-align: center;
        color: #94a3b8;
        font-size: 12px;
    }

    /* Painel de auxiliares */
    .assoc-detail-panel {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .assoc-detail-header {
        padding: 10px 16px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
    }

    .assoc-detail-title {
        font-size: 12px;
        font-weight: 600;
        color: #334155;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .assoc-detail-title .tag-highlight {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
        padding: 3px 10px;
        border-radius: 6px;
        font-family: 'SF Mono', Monaco, monospace;
        font-size: 11px;
    }

    .assoc-add-row {
        padding: 10px 16px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        gap: 8px;
        align-items: flex-start;
    }

    .assoc-add-row .select2-container {
        flex: 1;
        min-width: 0;
    }

    .assoc-add-row .btn-add-aux {
        flex-shrink: 0;
        margin-top: 4px;
    }

    /* Select2 do modal sync — dropdown acima de tudo */
    .select2-container--open .select2-dropdown[id*="selectSyncSistema"],
    body>.select2-container--open .select2-dropdown {
        z-index: 10010 !important;
    }

    .assoc-add-row .select2-container .select2-selection--single {
        height: 36px;
        display: flex;
        align-items: center;
    }

    .assoc-new-row .select2-container .select2-selection--single {
        height: 36px;
        display: flex;
        align-items: center;
    }

    .btn-add-aux {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 8px 14px;
        background: #3b82f6;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .btn-add-aux:hover {
        background: #2563eb;
    }

    .assoc-detail-body {
        flex: 1;
        overflow-y: auto;
        max-height: 340px;
    }

    .assoc-aux-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 16px;
        border-bottom: 1px solid #f1f5f9;
        gap: 8px;
    }

    .assoc-aux-info {
        display: flex;
        flex-direction: column;
        min-width: 0;
        flex: 1;
    }

    .assoc-aux-info .aux-tag {
        font-family: 'SF Mono', Monaco, monospace;
        font-size: 12px;
        color: #334155;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .assoc-aux-info .aux-ponto {
        font-size: 10px;
        color: #94a3b8;
        margin-top: 1px;
    }

    .btn-remove-aux {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 6px;
        background: #fee2e2;
        color: #dc2626;
        border: none;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .btn-remove-aux:hover {
        background: #fecaca;
    }

    .btn-excluir-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #fee2e2;
        color: #dc2626;
        border: none;
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.2s ease;
    }

    .btn-excluir-tag:hover {
        background: #fecaca;
    }

    .assoc-detail-empty {
        padding: 40px 16px;
        text-align: center;
        color: #94a3b8;
        font-size: 13px;
    }

    .assoc-detail-empty ion-icon {
        font-size: 32px;
        display: block;
        margin: 0 auto 8px;
    }

    .assoc-new-section {
        padding: 14px 16px;
        background: #fffbeb;
        border: 1px dashed #f59e0b;
        border-radius: 10px;
        margin-bottom: 16px;
    }

    .assoc-new-section label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #92400e;
        margin-bottom: 8px;
    }

    .assoc-new-row {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .assoc-new-row .select2-container {
        flex: 1;
    }

    .btn-new-assoc {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        background: #f59e0b;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .btn-new-assoc:hover {
        background: #d97706;
    }

    /* Select2 dentro do modal — z-index acima do modal */
    .modal-overlay .select2-container--open .select2-dropdown {
        z-index: 10001;
    }

    @media (max-width: 768px) {
        .assoc-layout {
            grid-template-columns: 1fr;
        }

        .assoc-lista-body,
        .assoc-detail-body {
            max-height: 250px;
        }

        .assoc-add-row {
            flex-wrap: wrap;
        }

        .assoc-new-row {
            flex-wrap: wrap;
        }
    }

    /* ============================================
   Banner de Sincronização Flowchart
   ============================================ */
    .sync-banner {
        display: none;
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 20px;
        animation: fadeIn 0.3s ease;
    }

    .sync-banner.divergente {
        display: flex;
        background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
        border: 1px solid #f59e0b;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
    }

    .sync-banner.sincronizado {
        display: flex;
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        border: 1px solid #22c55e;
        align-items: center;
        gap: 14px;
    }

    .sync-banner-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        flex-shrink: 0;
    }

    .sync-banner.divergente .sync-banner-icon {
        background: rgba(245, 158, 11, 0.15);
        color: #d97706;
    }

    .sync-banner.sincronizado .sync-banner-icon {
        background: rgba(34, 197, 94, 0.15);
        color: #16a34a;
    }

    .sync-banner-text {
        flex: 1;
        min-width: 200px;
    }

    .sync-banner-text h4 {
        margin: 0 0 2px;
        font-size: 14px;
        font-weight: 600;
    }

    .sync-banner.divergente .sync-banner-text h4 {
        color: #92400e;
    }

    .sync-banner.sincronizado .sync-banner-text h4 {
        color: #166534;
    }

    .sync-banner-text p {
        margin: 0;
        font-size: 12px;
        color: #64748b;
    }

    .sync-banner-actions {
        display: flex;
        gap: 8px;
        flex-shrink: 0;
        flex-wrap: wrap;
    }

    .btn-sync {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-sync.primary {
        background: #f59e0b;
        color: #fff;
    }

    .btn-sync.primary:hover {
        background: #d97706;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }

    .btn-sync.secondary {
        background: rgba(100, 116, 139, 0.1);
        color: #475569;
    }

    .btn-sync.secondary:hover {
        background: rgba(100, 116, 139, 0.2);
    }

    .btn-sync.rules {
        background: rgba(59, 130, 246, 0.1);
        color: #2563eb;
    }

    .btn-sync.rules:hover {
        background: rgba(59, 130, 246, 0.2);
    }

    /* ============================================
   Modal de Sincronização
   ============================================ */
    .sync-modal-overlay {
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
        padding: 20px;
        animation: fadeIn 0.2s ease;
    }

    .sync-modal-overlay.active {
        display: flex;
    }

    .sync-modal {
        background: #fff;
        border-radius: 16px;
        width: 100%;
        max-width: 800px;
        max-height: 85vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from {
            transform: translateY(20px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .sync-modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        border-radius: 16px 16px 0 0;
        color: #fff;
    }

    .sync-modal-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .sync-modal-header .modal-close {
        background: rgba(255, 255, 255, 0.15);
        border: none;
        color: #fff;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
    }

    .sync-modal-header .modal-close:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    .sync-modal-body {
        padding: 20px 24px;
        overflow-y: auto;
        flex: 1;
    }

    .sync-modal-footer {
        padding: 16px 24px;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    /* Filtro de sistema no modal */
    .sync-filter-row {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        padding-bottom: 16px;
        border-bottom: 1px solid #f1f5f9;
        flex-wrap: wrap;
    }

    .sync-filter-row label {
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        white-space: nowrap;
    }

    .sync-filter-row select {
        flex: 1;
        min-width: 200px;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 13px;
        outline: none;
    }

    .sync-filter-row select:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* ============================================
   Dropdown customizado (substitui Select2 no modal)
   ============================================ */
    .sync-dropdown-wrapper {
        position: relative;
        flex: 1;
        min-width: 200px;
    }

    .sync-dropdown-selected {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 13px;
        color: #334155;
        background: #fff;
        cursor: pointer;
        transition: border-color 0.2s, box-shadow 0.2s;
        min-height: 38px;
    }

    .sync-dropdown-selected:hover {
        border-color: #93c5fd;
    }

    .sync-dropdown-wrapper.open .sync-dropdown-selected {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .sync-dropdown-arrow {
        font-size: 16px;
        color: #94a3b8;
        transition: transform 0.2s;
        flex-shrink: 0;
    }

    .sync-dropdown-wrapper.open .sync-dropdown-arrow {
        transform: rotate(180deg);
    }

    .sync-dropdown-panel {
        display: none;
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        z-index: 100;
        overflow: hidden;
    }

    .sync-dropdown-wrapper.open .sync-dropdown-panel {
        display: block;
    }

    .sync-dropdown-search {
        width: 100%;
        padding: 10px 12px;
        border: none;
        border-bottom: 1px solid #e2e8f0;
        font-size: 13px;
        outline: none;
        box-sizing: border-box;
    }

    .sync-dropdown-search:focus {
        background: #f8fafc;
    }

    .sync-dropdown-options {
        max-height: 220px;
        overflow-y: auto;
    }

    .sync-dropdown-option {
        padding: 8px 12px;
        font-size: 13px;
        color: #334155;
        cursor: pointer;
        transition: background 0.1s;
    }

    .sync-dropdown-option:hover {
        background: #eff6ff;
    }

    .sync-dropdown-option.selected {
        background: #eff6ff;
        color: #1e40af;
        font-weight: 600;
    }

    .sync-dropdown-option.hidden {
        display: none;
    }

    .sync-modal-body {
        overflow-y: auto;
        overflow-x: hidden;
    }

    /* Resumo do diff */
    .sync-resumo {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 10px;
        margin-bottom: 20px;
    }

    .sync-resumo-item {
        text-align: center;
        padding: 12px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
    }

    .sync-resumo-item .valor {
        font-size: 22px;
        font-weight: 700;
        line-height: 1.2;
    }

    .sync-resumo-item .rotulo {
        font-size: 11px;
        color: #64748b;
        margin-top: 2px;
    }

    .sync-resumo-item.novas {
        background: #f0fdf4;
        border-color: #86efac;
    }

    .sync-resumo-item.novas .valor {
        color: #16a34a;
    }

    .sync-resumo-item.removidas {
        background: #fef2f2;
        border-color: #fecaca;
    }

    .sync-resumo-item.removidas .valor {
        color: #dc2626;
    }

    .sync-resumo-item.inalteradas {
        background: #f8fafc;
    }

    .sync-resumo-item.inalteradas .valor {
        color: #64748b;
    }

    .sync-resumo-item.flowchart {
        background: #eff6ff;
        border-color: #bfdbfe;
    }

    .sync-resumo-item.flowchart .valor {
        color: #2563eb;
    }

    /* Seção de diff (novas / removidas) */
    .sync-section {
        margin-bottom: 16px;
    }

    .sync-section-header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 8px;
        margin-bottom: 8px;
        cursor: pointer;
        user-select: none;
        transition: background 0.2s;
    }

    .sync-section-header.novas {
        background: #f0fdf4;
        color: #166534;
    }

    .sync-section-header.removidas {
        background: #fef2f2;
        color: #991b1b;
    }

    .sync-section-header.inalteradas {
        background: #f8fafc;
        color: #475569;
    }

    .sync-section-header:hover {
        filter: brightness(0.97);
    }

    .sync-section-header h4 {
        margin: 0;
        font-size: 13px;
        font-weight: 600;
        flex: 1;
    }

    .sync-section-header .badge {
        font-size: 11px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 100px;
        background: rgba(0, 0, 0, 0.08);
    }

    .sync-section-header ion-icon.toggle {
        font-size: 16px;
        transition: transform 0.2s;
    }

    .sync-section.collapsed .sync-section-body {
        display: none;
    }

    .sync-section.collapsed ion-icon.toggle {
        transform: rotate(-90deg);
    }

    /* Checkbox selecionar tudo */
    .sync-select-all {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        font-size: 11px;
        color: #64748b;
        border-bottom: 1px solid #f1f5f9;
    }

    .sync-select-all input[type="checkbox"] {
        accent-color: #3b82f6;
    }

    /* Linha individual do diff */
    .sync-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 10px;
        border-bottom: 1px solid #f8fafc;
        font-size: 12px;
        transition: background 0.15s;
    }

    .sync-row:hover {
        background: #f8fafc;
    }

    .sync-row:last-child {
        border-bottom: none;
    }

    .sync-row input[type="checkbox"] {
        accent-color: #3b82f6;
        flex-shrink: 0;
    }

    .sync-row .tag-principal {
        min-width: 200px;
        display: flex;
        flex-direction: column;
        gap: 1px;
    }

    .sync-row .seta {
        color: #94a3b8;
        font-size: 14px;
        flex-shrink: 0;
    }

    .sync-row .tag-auxiliar {
        display: flex;
        flex-direction: column;
        gap: 1px;
    }

    .sync-row .codigo-ponto {
        font-size: 12px;
        font-weight: 700;
        color: #1e3a5f;
        font-family: 'Courier New', monospace;
        letter-spacing: 0.3px;
    }

    .sync-row .tag-nome {
        font-size: 10px;
        color: #94a3b8;
        font-family: 'Courier New', monospace;
        max-width: 240px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Estado vazio */
    .sync-vazio {
        text-align: center;
        padding: 40px 20px;
        color: #94a3b8;
    }

    .sync-vazio ion-icon {
        font-size: 40px;
        margin-bottom: 10px;
        display: block;
    }

    .sync-vazio p {
        margin: 4px 0;
        font-size: 13px;
    }

    /* Loading no modal */
    .sync-loading {
        text-align: center;
        padding: 40px;
        color: #64748b;
    }

    .sync-loading ion-icon {
        font-size: 32px;
        animation: spin 1s linear infinite;
        display: block;
        margin: 0 auto 12px;
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    /* ============================================
   Modal de Regras
   ============================================ */
    .regras-content {
        font-size: 13px;
        color: #334155;
        line-height: 1.6;
    }

    .regras-content h4 {
        margin: 16px 0 8px;
        font-size: 14px;
        color: #1e3a5f;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .regras-content h4:first-child {
        margin-top: 0;
    }

    .regras-tipo-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin: 8px 0 16px;
    }

    .regras-tipo-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 500;
    }

    .regras-tipo-item.incluido {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
    }

    .regras-tipo-item.excluido {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }

    .regras-tipo-item ion-icon {
        font-size: 16px;
        flex-shrink: 0;
    }

    .regras-diagrama {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 16px;
        margin: 12px 0;
        font-family: 'Courier New', monospace;
        font-size: 12px;
        line-height: 1.8;
        color: #475569;
        white-space: pre-line;
    }

    .regras-destaque {
        background: #eff6ff;
        border-left: 3px solid #3b82f6;
        padding: 10px 14px;
        border-radius: 0 8px 8px 0;
        margin: 12px 0;
        font-size: 12px;
        color: #1e40af;
    }

    /* Responsivo */
    @media (max-width: 768px) {
        .sync-modal {
            max-width: 100%;
            margin: 10px;
        }

        .sync-resumo {
            grid-template-columns: 1fr 1fr;
        }

        .sync-row .tag-principal {
            min-width: 140px;
        }

        .sync-row .codigo-ponto {
            font-size: 11px;
        }

        .sync-row .tag-nome {
            font-size: 9px;
            max-width: 150px;
        }

        .regras-tipo-grid {
            grid-template-columns: 1fr;
        }

        .sync-banner {
            flex-direction: column;
            text-align: center;
        }

        .sync-banner-actions {
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .sync-resumo {
            grid-template-columns: 1fr;
        }

        .sync-filter-row {
            flex-direction: column;
            align-items: stretch;
        }

        .sync-row {
            flex-wrap: wrap;
        }

        .sync-row .tag-nome {
            max-width: 120px;
        }
    }

    /* ============================================
       Modal de Diagnóstico ML
       ============================================ */
    .diag-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        z-index: 10000;
        justify-content: center;
        align-items: flex-start;
        padding: 40px 20px;
        overflow-y: auto;
    }

    .diag-modal-overlay.active {
        display: flex;
    }

    .diag-modal {
        background: #fff;
        border-radius: 16px;
        width: 100%;
        max-width: 640px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        overflow: hidden;
        animation: diagSlideIn 0.25s ease-out;
    }

    @keyframes diagSlideIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .diag-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 24px;
        background: linear-gradient(135deg, #1e3a5f, #2563eb);
        color: #fff;
    }

    .diag-modal-header h3 {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 15px;
        font-weight: 600;
        margin: 0;
    }

    .diag-modal-header h3 ion-icon {
        font-size: 20px;
    }

    .diag-modal-header .diag-close {
        background: rgba(255, 255, 255, 0.15);
        border: none;
        color: #fff;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
    }

    .diag-modal-header .diag-close:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    .diag-modal-header .diag-close ion-icon {
        font-size: 18px;
    }

    /* Subheader com info do ponto */
    .diag-ponto-info {
        padding: 14px 24px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .diag-ponto-info .codigo {
        font-family: 'Courier New', monospace;
        font-size: 13px;
        font-weight: 700;
        color: #1e3a5f;
        background: #e0eaff;
        padding: 4px 10px;
        border-radius: 6px;
    }

    .diag-ponto-info .nome {
        font-size: 12px;
        color: #64748b;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Corpo com as etapas */
    .diag-modal-body {
        padding: 20px 24px;
        max-height: 60vh;
        overflow-y: auto;
    }

    /* Loading */
    .diag-loading {
        text-align: center;
        padding: 40px 0;
        color: #64748b;
    }

    .diag-loading ion-icon {
        font-size: 32px;
        color: #3b82f6;
        animation: diagSpin 1s linear infinite;
    }

    @keyframes diagSpin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    .diag-loading p {
        margin-top: 12px;
        font-size: 13px;
    }

    /* Timeline das etapas */
    .diag-timeline {
        position: relative;
        padding-left: 32px;
    }

    .diag-timeline::before {
        content: '';
        position: absolute;
        left: 11px;
        top: 8px;
        bottom: 8px;
        width: 2px;
        background: #e2e8f0;
        border-radius: 1px;
    }

    .diag-etapa {
        position: relative;
        margin-bottom: 4px;
    }

    .diag-etapa:last-child {
        margin-bottom: 0;
    }

    /* Ícone do status na timeline */
    .diag-etapa-icon {
        position: absolute;
        left: -32px;
        top: 12px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1;
    }

    .diag-etapa-icon ion-icon {
        font-size: 14px;
        color: #fff;
    }

    .diag-etapa-icon.ok {
        background: #22c55e;
    }

    .diag-etapa-icon.alerta {
        background: #f59e0b;
    }

    .diag-etapa-icon.erro {
        background: #ef4444;
    }

    /* Cabeçalho clicável da etapa */
    .diag-etapa-header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 10px;
        cursor: pointer;
        transition: background 0.15s;
        border: 1px solid transparent;
    }

    .diag-etapa-header:hover {
        background: #f8fafc;
    }

    .diag-etapa.expanded .diag-etapa-header {
        background: #f8fafc;
        border-color: #e2e8f0;
        border-bottom-left-radius: 0;
        border-bottom-right-radius: 0;
    }

    .diag-etapa-header .etapa-numero {
        font-size: 11px;
        font-weight: 600;
        color: #94a3b8;
        min-width: 16px;
    }

    .diag-etapa-header .etapa-titulo {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
    }

    .diag-etapa-header .etapa-msg {
        font-size: 12px;
        color: #64748b;
        margin-left: auto;
        text-align: right;
        max-width: 50%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .diag-etapa-header .etapa-msg.ok {
        color: #16a34a;
    }

    .diag-etapa-header .etapa-msg.alerta {
        color: #d97706;
    }

    .diag-etapa-header .etapa-msg.erro {
        color: #dc2626;
    }

    /* Seta de expansão */
    .diag-etapa-header .etapa-toggle {
        font-size: 14px;
        color: #94a3b8;
        transition: transform 0.2s;
        flex-shrink: 0;
    }

    .diag-etapa.expanded .diag-etapa-header .etapa-toggle {
        transform: rotate(180deg);
    }

    /* Corpo expandido da etapa */
    .diag-etapa-body {
        display: none;
        padding: 12px 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-top: none;
        border-bottom-left-radius: 10px;
        border-bottom-right-radius: 10px;
        margin-bottom: 4px;
    }

    .diag-etapa.expanded .diag-etapa-body {
        display: block;
    }

    .diag-detalhe-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        padding: 5px 0;
        font-size: 12px;
        border-bottom: 1px solid #eef2f7;
        gap: 12px;
    }

    .diag-detalhe-row:last-child {
        border-bottom: none;
    }

    .diag-detalhe-row .dl-label {
        color: #64748b;
        flex-shrink: 0;
        min-width: 120px;
    }

    .diag-detalhe-row .dl-valor {
        color: #1e293b;
        font-weight: 500;
        text-align: right;
        word-break: break-all;
    }

    /* Resumo / veredicto */
    .diag-resumo {
        margin-top: 16px;
        padding: 14px 18px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .diag-resumo ion-icon {
        font-size: 24px;
        flex-shrink: 0;
    }

    .diag-resumo .resumo-texto {
        font-size: 13px;
        font-weight: 600;
    }

    .diag-resumo .resumo-detalhe {
        font-size: 11px;
        font-weight: 400;
        color: inherit;
        opacity: 0.8;
        margin-top: 2px;
    }

    .diag-resumo.viavel {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
    }

    .diag-resumo.viavel-alerta {
        background: #fffbeb;
        border: 1px solid #fde68a;
        color: #92400e;
    }

    .diag-resumo.bloqueado {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }

    /* Footer do modal */
    .diag-modal-footer {
        padding: 14px 24px;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        background: #fafbfc;
    }

    .diag-modal-footer button {
        padding: 8px 18px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        border: 1px solid transparent;
        transition: all 0.2s;
    }

    .diag-btn-fechar {
        background: #f1f5f9;
        color: #475569;
        border-color: #e2e8f0;
    }

    .diag-btn-fechar:hover {
        background: #e2e8f0;
    }

    .diag-btn-treinar {
        background: #2563eb;
        color: #fff;
    }

    .diag-btn-treinar:hover {
        background: #1d4ed8;
    }

    .diag-btn-treinar:disabled {
        background: #94a3b8;
        cursor: not-allowed;
    }

    /* Botão de diagnóstico no card */
    .btn-model-action.btn-diag {
        background: #faf5ff;
        color: #7c3aed;
        border-color: #ddd6fe;
    }

    .btn-model-action.btn-diag:hover {
        background: #ede9fe;
    }

    /* Responsivo */
    @media (max-width: 768px) {
        .diag-modal {
            max-width: 100%;
            margin: 10px;
        }

        .diag-modal-body {
            max-height: 55vh;
        }

        .diag-etapa-header .etapa-msg {
            max-width: 40%;
            font-size: 11px;
        }

        .diag-ponto-info {
            flex-wrap: wrap;
        }

        .diag-detalhe-row {
            flex-direction: column;
            gap: 2px;
        }

        .diag-detalhe-row .dl-valor {
            text-align: left;
        }
    }

    @media (max-width: 480px) {
        .diag-modal-body {
            padding: 14px 16px;
        }

        .diag-etapa-header .etapa-msg {
            display: none;
        }
    }
</style>

<div class="page-container">

    <!-- ============================================
         Page Header
         ============================================ -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="hardware-chip"></ion-icon>
                </div>
                <div>
                    <h1>Modelos de Machine Learning</h1>
                    <p class="page-header-subtitle">Gerenciamento de modelos XGBoost treinados para predição</p>
                </div>
            </div>
            <div class="page-header-actions">
                <!-- Status do serviço TensorFlow -->
                <div class="service-badge" id="serviceBadge">
                    <span class="status-dot" id="statusDot"></span>
                    <span id="serviceStatusText">Verificando...</span>
                </div>
                <?php if ($podeEditar): ?>
                    <button type="button" class="btn-novo-treino" onclick="abrirModalTreino()">
                        <ion-icon name="add-outline"></ion-icon>
                        Novo Treino
                    </button>
                    <button type="button" class="btn-novo-treino" onclick="abrirModalAssociacoes()"
                        style="background: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.2);">
                        <ion-icon name="git-network-outline"></ion-icon>
                        Associações
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================================
         Stats Cards
         ============================================ -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-card-icon total">
                <ion-icon name="cube-outline"></ion-icon>
            </div>
            <div class="stat-card-info">
                <h3 id="statTotal">-</h3>
                <p>Modelos treinados</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon xgboost">
                <ion-icon name="git-branch-outline"></ion-icon>
            </div>
            <div class="stat-card-info">
                <h3 id="statXgboost">-</h3>
                <p>XGBoost v5.0</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon good">
                <ion-icon name="checkmark-circle-outline"></ion-icon>
            </div>
            <div class="stat-card-info">
                <h3 id="statGood">-</h3>
                <p>R² &ge; 0.70</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon bad">
                <ion-icon name="alert-circle-outline"></ion-icon>
            </div>
            <div class="stat-card-info">
                <h3 id="statBad">-</h3>
                <p>R² &lt; 0.70</p>
            </div>
        </div>
    </div>

    <!-- ============================================
     Banner de Sincronização Flowchart ↔ ML
     ============================================ -->
    <div class="sync-banner" id="syncBanner">
        <div class="sync-banner-icon">
            <ion-icon name="git-network-outline" id="syncBannerIcon"></ion-icon>
        </div>
        <div class="sync-banner-text">
            <h4 id="syncBannerTitle">Verificando...</h4>
            <p id="syncBannerMsg">Comparando topologia do Flowchart com as relações ML</p>
        </div>
        <div class="sync-banner-actions">
            <button type="button" class="btn-sync rules" onclick="abrirModalRegras()" title="Ver regras de derivação">
                <ion-icon name="help-circle-outline"></ion-icon> Regras
            </button>
            <button type="button" class="btn-sync primary" id="btnRevisarSync" onclick="abrirModalSync()"
                style="display:none;">
                <ion-icon name="sync-outline"></ion-icon> Revisar Sincronização
            </button>
        </div>
    </div>

    <!-- ============================================
     Modal de Sincronização Flowchart → ML
     ============================================ -->
    <div class="sync-modal-overlay" id="modalSync" onclick="if(event.target===this) fecharModalSync()">
        <div class="sync-modal">
            <!-- Header -->
            <div class="sync-modal-header">
                <h3>
                    <ion-icon name="git-compare-outline"></ion-icon>
                    Sincronizar Flowchart → Relações ML
                </h3>
                <button class="modal-close" onclick="fecharModalSync()">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>

            <!-- Body -->
            <div class="sync-modal-body">
                <!-- Filtro por sistema -->
                <!-- Filtro por sistema -->
                <div class="sync-filter-row">
                    <label>
                        <ion-icon name="water-outline" style="vertical-align:middle;margin-right:4px;"></ion-icon>
                        Sistema de Água:
                    </label>
                    <div class="sync-dropdown-wrapper" id="syncDropdownWrapper">
                        <div class="sync-dropdown-selected" onclick="toggleSyncDropdown()">
                            <span id="syncDropdownLabel">— Todos os Sistemas —</span>
                            <ion-icon name="chevron-down-outline" class="sync-dropdown-arrow"></ion-icon>
                        </div>
                        <div class="sync-dropdown-panel" id="syncDropdownPanel">
                            <input type="text" class="sync-dropdown-search" id="syncDropdownSearch"
                                placeholder="Buscar sistema..." oninput="filtrarSyncSistemas(this.value)">
                            <div class="sync-dropdown-options" id="syncDropdownOptions">
                                <div class="sync-dropdown-option selected" data-value="0"
                                    onclick="selecionarSyncSistema(0, '— Todos os Sistemas —')">
                                    — Todos os Sistemas —
                                </div>
                                <?php foreach ($sistemasFlowchart as $sis): ?>
                                    <div class="sync-dropdown-option" data-value="<?= $sis['CD_CHAVE'] ?>"
                                        onclick="selecionarSyncSistema(<?= $sis['CD_CHAVE'] ?>, '<?= addslashes(htmlspecialchars($sis['DS_SISTEMA_AGUA'], ENT_QUOTES, 'UTF-8')) ?>')">
                                        <?= htmlspecialchars($sis['DS_SISTEMA_AGUA'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Conteúdo dinâmico (preenchido via JS) -->
                <div id="syncConteudo">
                    <div class="sync-loading">
                        <ion-icon name="sync-outline"></ion-icon>
                        <p>Analisando divergências...</p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="sync-modal-footer">
                <div style="font-size:11px;color:#94a3b8;">
                    <ion-icon name="information-circle-outline" style="vertical-align:middle;"></ion-icon>
                    Selecione os itens e clique em Aplicar
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="btn-sync secondary" onclick="fecharModalSync()">
                        Cancelar
                    </button>
                    <button type="button" class="btn-sync primary" id="btnAplicarSync" onclick="aplicarSync()" disabled>
                        <ion-icon name="checkmark-outline"></ion-icon> Aplicar Selecionados
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================
     Modal de Regras de Derivação
     ============================================ -->
    <div class="sync-modal-overlay" id="modalRegras" onclick="if(event.target===this) fecharModalRegras()">
        <div class="sync-modal" style="max-width:650px;">
            <!-- Header -->
            <div class="sync-modal-header" style="background:linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);">
                <h3>
                    <ion-icon name="book-outline"></ion-icon>
                    Regras de Derivação — Flowchart → ML
                </h3>
                <button class="modal-close" onclick="fecharModalRegras()">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>

            <!-- Body -->
            <div class="sync-modal-body">
                <div class="regras-content">

                    <h4><ion-icon name="fitness-outline"></ion-icon> Princípio Hidráulico</h4>
                    <p>
                        Em sistemas de abastecimento de água, pressão e vazão são grandezas acopladas.
                        <strong>Tudo ao redor de um ponto o influencia:</strong> montante (upstream),
                        jusante (downstream) e pontos irmãos na mesma estrutura.
                    </p>

                    <div class="regras-destaque">
                        O modelo XGBoost de cada ponto usa dados dos <strong>vizinhos diretos</strong>
                        (1 hop) como features de entrada para prever o comportamento do ponto principal.
                    </div>

                    <h4><ion-icon name="git-network-outline"></ion-icon> Regra de Vizinhança (1 Hop Bidirecional +
                        Irmãos)</h4>
                    <p>Para cada nó com ponto de medição no Flowchart, são considerados como auxiliares:</p>
                    <div class="regras-diagrama">1. <strong>Vizinhos diretos</strong> — nós conectados por setas
                        (upstream e downstream)
                        Exemplo: ETA ←→ Macro Saída (ambos se veem)

                        2. <strong>Irmãos por origem</strong> — nós que recebem do mesmo pai
                        Exemplo: ETA → Saída 1, ETA → Saída 2 (Saída 1 e 2 são irmãs)

                        3. <strong>Irmãos por destino</strong> — nós que alimentam o mesmo destino
                        Exemplo: Macro A → Reserv., Macro B → Reserv. (A e B são irmãos)</div>

                    <h4><ion-icon name="funnel-outline"></ion-icon> Tipos de Medidor Considerados</h4>
                    <div class="regras-tipo-grid">
                        <div class="regras-tipo-item incluido">
                            <ion-icon name="checkmark-circle-outline"></ion-icon>
                            Macromedidor (Tipo 1)
                        </div>
                        <div class="regras-tipo-item incluido">
                            <ion-icon name="checkmark-circle-outline"></ion-icon>
                            Estação Pitométrica (Tipo 2)
                        </div>
                        <div class="regras-tipo-item incluido">
                            <ion-icon name="checkmark-circle-outline"></ion-icon>
                            Medidor de Pressão (Tipo 4)
                        </div>
                        <div class="regras-tipo-item incluido">
                            <ion-icon name="checkmark-circle-outline"></ion-icon>
                            Nível Reservatório (Tipo 6)
                        </div>
                        <div class="regras-tipo-item excluido">
                            <ion-icon name="close-circle-outline"></ion-icon>
                            Hidrômetro (Tipo 8) — Excluído
                        </div>
                    </div>
                    <p style="font-size:12px;color:#64748b;">
                        <strong>Por que excluir hidrômetros?</strong> Micromedição possui escala (m³/mês)
                        e granularidade temporal (leitura mensal) incompatíveis com macromedição (L/s contínuo).
                        A correlação com macromedidores é muito baixa, gerando ruído no modelo.
                    </p>

                    <h4><ion-icon name="flash-outline"></ion-icon> Exemplo Prático</h4>
                    <div class="regras-diagrama">Manancial ──→ <strong>Macro Captação</strong> ──→ ETA
                        ├──→ <strong>Macro Saída 1</strong>
                        ├──→ <strong>Macro Saída 2</strong>
                        └──→ <strong>Reservatório (Nível)</strong>
                        └──→ <strong>Pressão Saída</strong>

                        Para prever <strong>Macro Saída 1</strong>, auxiliares são:
                        • ETA (vizinho upstream)
                        • Macro Saída 2 (irmã — mesmo pai)
                        • Reservatório Nível (vizinho downstream, se conectado)
                        • Macro Captação (vizinho indireto via ETA)</div>

                </div>
            </div>

            <!-- Footer -->
            <div class="sync-modal-footer" style="justify-content:flex-end;">
                <button type="button" class="btn-sync secondary" onclick="fecharModalRegras()">
                    Fechar
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================
         Modal de Diagnóstico ML
         ============================================ -->
    <div class="diag-modal-overlay" id="modalDiagnostico">
        <div class="diag-modal">
            <div class="diag-modal-header">
                <h3>
                    <ion-icon name="medkit-outline"></ion-icon>
                    Diagnóstico de Treino ML
                </h3>
                <button class="diag-close" onclick="fecharModalDiag()">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>
            <!-- Info do ponto (preenchido via JS) -->
            <div class="diag-ponto-info" id="diagPontoInfo">
                <span class="codigo" id="diagPontoCodigo">—</span>
                <span class="nome" id="diagPontoNome">—</span>
            </div>
            <!-- Corpo: timeline de etapas -->
            <div class="diag-modal-body" id="diagModalBody">
                <div class="diag-loading" id="diagLoading">
                    <ion-icon name="sync-outline"></ion-icon>
                    <p>Executando diagnóstico...</p>
                </div>
                <div id="diagConteudo" style="display:none;"></div>
            </div>
            <!-- Footer -->
            <div class="diag-modal-footer">
                <button class="diag-btn-fechar" onclick="fecharModalDiag()">Fechar</button>
                <button class="diag-btn-treinar" id="diagBtnTreinar" disabled onclick="treinarAposDiag()"
                    style="display:none;">
                    <ion-icon name="rocket-outline" style="vertical-align:middle;margin-right:4px;"></ion-icon>
                    Treinar
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================
         Filtros
         ============================================ -->
    <div class="filters-bar">
        <div class="search-input-wrapper">
            <ion-icon name="search-outline"></ion-icon>
            <input type="text" class="search-input" id="searchInput" placeholder="Buscar por ponto, TAG ou código..."
                oninput="filtrarModelos()">
        </div>
        <select class="filter-select" id="filterQuality" onchange="filtrarModelos()">
            <option value="">Todas as qualidades</option>
            <option value="excellent">Excelente (R² &ge; 0.90)</option>
            <option value="good">Bom (R² &ge; 0.70)</option>
            <option value="fair">Regular (R² &ge; 0.50)</option>
            <option value="poor">Baixo (R² &lt; 0.50)</option>
        </select>
        <select class="filter-select" id="filterTipo" onchange="filtrarModelos()">
            <option value="">Todos os tipos</option>
            <option value="xgboost">XGBoost</option>
            <option value="lstm">LSTM (legado)</option>
        </select>
        <button type="button" class="btn-refresh" id="btnRefresh" onclick="carregarModelos()">
            <ion-icon name="refresh-outline"></ion-icon>
            Atualizar
        </button>
    </div>

    <!-- ============================================
         Grid de Modelos
         ============================================ -->
    <div id="modelsContainer">
        <!-- Preenchido via JavaScript -->
        <div class="empty-state">
            <div class="empty-state-icon">
                <ion-icon name="sync-outline" style="animation: spin 1.5s linear infinite;"></ion-icon>
            </div>
            <h3>Carregando modelos...</h3>
            <p>Conectando ao serviço TensorFlow</p>
        </div>
    </div>
</div>

<!-- ============================================
     Modal: Detalhes do Modelo
     ============================================ -->
<div class="modal-overlay" id="modalDetalhes" onclick="fecharModalDetalhes(event)">
    <div class="modal-container" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h2>
                <ion-icon name="analytics-outline"></ion-icon>
                <span id="modalDetalhesTitle">Detalhes do Modelo</span>
            </h2>
            <button class="btn-close" onclick="fecharModalDetalhes()">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-body" id="modalDetalhesBody">
            <!-- Preenchido via JavaScript -->
        </div>
    </div>
</div>

<!-- ============================================
     Modal: Novo Treino
     ============================================ -->
<?php if ($podeEditar): ?>
    <div class="modal-overlay" id="modalTreino" onclick="fecharModalTreino(event)">
        <div class="modal-container" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h2>
                    <ion-icon name="fitness-outline"></ion-icon>
                    Treinar Novo Modelo
                </h2>
                <button class="btn-close" onclick="fecharModalTreino()">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>
            <div class="modal-body">
                <!-- Modo de treino -->
                <div class="train-form-group">
                    <label>
                        <ion-icon name="options-outline"></ion-icon>
                        Modo de Treino
                    </label>
                    <select id="selectModoTreino" onchange="toggleModoPonto()">
                        <option value="unico">Ponto específico</option>
                        <option value="todos">Todos os pontos</option>
                    </select>
                    <div class="train-form-hint" id="hintModo">
                        Treinar modelo para um único ponto de medição
                    </div>
                </div>

                <!-- Ponto de Medição (visível só no modo único) -->
                <div class="train-form-group" id="grupoPonto">
                    <label>
                        <ion-icon name="pin-outline"></ion-icon>
                        Ponto de Medição
                    </label>
                    <select id="selectPontoTreino" style="width: 100%;">
                        <option value="">Selecione um ponto de medição...</option>
                        <?php foreach ($pontosMedicao as $pm): ?>
                            <option value="<?= $pm['CD_PONTO_MEDICAO'] ?>" data-tipo="<?= $pm['ID_TIPO_MEDIDOR'] ?>">
                                <?= htmlspecialchars(
                                    $pm['CD_PONTO_MEDICAO'] . ' - ' .
                                    $pm['DS_NOME'] .
                                    ($pm['DS_TIPO_MEDIDOR'] ? ' (' . $pm['DS_TIPO_MEDIDOR'] . ')' : '') .
                                    ($pm['DS_UNIDADE'] ? ' - ' . $pm['DS_UNIDADE'] : '')
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Semanas de histórico -->
                <div class="train-form-group">
                    <label>
                        <ion-icon name="calendar-outline"></ion-icon>
                        Semanas de Histórico
                    </label>
                    <select id="selectSemanas">
                        <option value="12">12 semanas (3 meses)</option>
                        <option value="24" selected>24 semanas (6 meses)</option>
                        <option value="36">36 semanas (9 meses)</option>
                        <option value="52">52 semanas (1 ano)</option>
                    </select>
                    <div class="train-form-hint">
                        Mais semanas = modelo mais robusto, porém treino mais demorado
                    </div>
                </div>

                <!-- Forçar retreino -->
                <div class="train-form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="chkForce" style="width: auto;">
                        <span>Forçar retreino (sobrescrever modelo existente)</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal btn-cancel" onclick="fecharModalTreino()">
                    Cancelar
                </button>
                <button type="button" class="btn-modal btn-primary" id="btnIniciarTreino" onclick="iniciarTreino()">
                    <ion-icon name="flash-outline"></ion-icon>
                    Iniciar Treinamento
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ============================================
     Modal: Retreinar Modelo
     ============================================ -->
<?php if ($podeEditar): ?>
    <div class="modal-overlay" id="modalRetreino" onclick="fecharModalRetreino(event)">
        <div class="modal-container" style="max-width: 440px;" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h2>
                    <ion-icon name="refresh-outline"></ion-icon>
                    Retreinar Modelo <span id="retreinoPonto"></span>
                </h2>
                <button class="btn-close" onclick="fecharModalRetreino()">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>
            <div class="modal-body">
                <!-- Campos ocultos -->
                <input type="hidden" id="retreinoCdPonto">
                <input type="hidden" id="retreinoTipoMedidor">

                <!-- Período de histórico -->
                <div class="train-form-group">
                    <label>
                        <ion-icon name="calendar-outline"></ion-icon>
                        Período de histórico
                    </label>
                    <select id="selectSemanasRetreino">
                        <option value="12">12 semanas (3 meses)</option>
                        <option value="24" selected>24 semanas (6 meses)</option>
                        <option value="36">36 semanas (9 meses)</option>
                        <option value="52">52 semanas (1 ano)</option>
                        <option value="78">78 semanas (1 ano e meio)</option>
                        <option value="104">104 semanas (2 anos)</option>
                    </select>
                    <div class="train-form-hint">
                        Mais semanas = modelo mais robusto, porém treino mais demorado
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal btn-cancel" onclick="fecharModalRetreino()">
                    Cancelar
                </button>
                <button type="button" class="btn-modal btn-primary" onclick="confirmarRetreino()">
                    <ion-icon name="flash-outline"></ion-icon>
                    Retreinar
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ============================================
     Modal: Associações TAG Principal → Auxiliares
     ============================================ -->
<?php if ($podeEditar): ?>
    <div class="modal-overlay" id="modalAssociacoes" onclick="fecharModalAssociacoes(event)">
        <div class="modal-container" style="max-width: 920px;" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h2>
                    <ion-icon name="git-network-outline"></ion-icon>
                    Associações de Tags (Principal → Auxiliares)
                </h2>
                <button class="btn-close" onclick="fecharModalAssociacoes()">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>
            <div class="modal-body">
                <!-- Nova associação -->
                <div class="assoc-new-section">
                    <label>
                        <ion-icon name="add-circle-outline"></ion-icon>
                        Adicionar nova TAG principal
                    </label>
                    <div class="assoc-new-row">
                        <select id="selectNovaTagPrincipal" style="width:100%;">
                            <option value="">Selecione um ponto / TAG...</option>
                        </select>
                        <button type="button" class="btn-new-assoc" onclick="criarNovaPrincipal()">
                            <ion-icon name="add-outline"></ion-icon>
                            Criar
                        </button>
                    </div>
                </div>

                <!-- Layout principal -->
                <div class="assoc-layout">
                    <!-- Lista de TAGs principais -->
                    <div class="assoc-lista-panel">
                        <div class="assoc-lista-header">
                            <ion-icon name="pricetag-outline"></ion-icon>
                            TAGs Principais
                            <span id="assocTotalPrincipais" style="margin-left:auto; font-size:10px; color:#94a3b8;"></span>
                        </div>
                        <div class="assoc-lista-search">
                            <input type="text" id="assocSearchPrincipal" placeholder="Filtrar TAGs..."
                                oninput="filtrarPrincipais()">
                        </div>
                        <div class="assoc-lista-body" id="assocListaPrincipais">
                            <div class="assoc-lista-empty">Carregando...</div>
                        </div>
                    </div>

                    <!-- Detalhe das auxiliares -->
                    <div class="assoc-detail-panel">
                        <div class="assoc-detail-header" id="assocDetailHeader" style="display:none;">
                            <div class="assoc-detail-title">
                                Auxiliares de <span class="tag-highlight" id="assocTagSelecionada"></span>
                            </div>
                            <button type="button" class="btn-excluir-tag" onclick="excluirPrincipal()">
                                <ion-icon name="trash-outline"></ion-icon>
                                Excluir TAG
                            </button>
                        </div>
                        <div class="assoc-add-row" id="assocAddRow" style="display:none;">
                            <select id="selectNovaAuxiliar" style="width:100%;">
                                <option value="">Selecione uma TAG auxiliar...</option>
                            </select>
                            <button type="button" class="btn-add-aux" onclick="adicionarAuxiliar()">
                                <ion-icon name="add-outline"></ion-icon>
                                Adicionar
                            </button>
                        </div>
                        <div class="assoc-detail-body" id="assocDetailBody">
                            <div class="assoc-detail-empty">
                                <ion-icon name="arrow-back-outline"></ion-icon>
                                Selecione uma TAG principal para ver suas auxiliares
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ============================================
     Loading Overlay (para treino)
     ============================================ -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-box">
        <ion-icon name="sync-outline"></ion-icon>
        <p id="loadingText">Treinando modelo...</p>
        <div class="loading-sub" id="loadingSub">Isso pode levar alguns minutos</div>
    </div>
</div>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    /**
     * SIMP - Modelos de Machine Learning
     * Gerenciamento frontend dos modelos XGBoost
     * 
     * @author Bruno - CESAN
     * @version 1.0
     */

    // ============================================
    // Variáveis globais
    // ============================================

    /** Lista completa de modelos carregados do serviço */
    let todosModelos = [];

    /** Indica se o serviço TensorFlow está online */
    let servicoOnline = false;

    /** Permissão de edição do usuário */
    const podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;

    // ============================================
    // Inicialização
    // ============================================

    document.addEventListener('DOMContentLoaded', function () {
        // Inicializar Select2 no modal de treino (com busca)
        if (document.getElementById('selectPontoTreino')) {
            $('#selectPontoTreino').select2({
                placeholder: 'Selecione um ponto de medição...',
                allowClear: true,
                dropdownParent: $('#modalTreino'),
                width: '100%',
                language: {
                    noResults: function () { return 'Nenhum ponto encontrado'; },
                    searching: function () { return 'Buscando...'; }
                }
            });
        }

        // Verificar status do serviço e carregar modelos
        verificarServico();
        carregarModelos();
        verificarSyncFlowchart();

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                fecharModalDetalhes();
                fecharModalTreino();
                fecharModalRetreino();
                fecharModalAssociacoes();
            }
        });
    });

    // ============================================
    // Verificar status do serviço TensorFlow
    // ============================================

    /**
     * Chama o health check do serviço TensorFlow.
     * Atualiza o badge de status no header.
     */
    function verificarServico() {
        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'health' })
        })
            .then(r => r.json())
            .then(data => {
                const dot = document.getElementById('statusDot');
                const text = document.getElementById('serviceStatusText');

                if (data.success && data.tensorflow && data.tensorflow.status === 'ok') {
                    servicoOnline = true;
                    dot.className = 'status-dot online';
                    text.textContent = 'TensorFlow Online';
                } else {
                    servicoOnline = false;
                    dot.className = 'status-dot offline';
                    text.textContent = 'TensorFlow Offline';
                }
            })
            .catch(() => {
                servicoOnline = false;
                document.getElementById('statusDot').className = 'status-dot offline';
                document.getElementById('serviceStatusText').textContent = 'TensorFlow Offline';
            });
    }

    // ============================================
    // Carregar modelos do serviço
    // ============================================

    /**
     * Busca a lista de modelos treinados via predicaoTensorFlow.php?acao=status.
     * Atualiza os cards e estatísticas na tela.
     */
    function carregarModelos() {
        const btn = document.getElementById('btnRefresh');
        btn.classList.add('loading');

        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'status' })
        })
            .then(r => r.json())
            .then(data => {
                btn.classList.remove('loading');

                if (data.success && data.modelos) {
                    todosModelos = data.modelos;
                    atualizarEstatisticas();
                    filtrarModelos();
                } else {
                    // Serviço offline ou erro
                    todosModelos = [];
                    atualizarEstatisticas();
                    document.getElementById('modelsContainer').innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <ion-icon name="cloud-offline-outline"></ion-icon>
                        </div>
                        <h3>Serviço indisponível</h3>
                        <p>${data.error || 'Não foi possível conectar ao serviço TensorFlow'}</p>
                    </div>
                `;
                }
            })
            .catch(err => {
                btn.classList.remove('loading');
                todosModelos = [];
                atualizarEstatisticas();
                document.getElementById('modelsContainer').innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <ion-icon name="warning-outline"></ion-icon>
                    </div>
                    <h3>Erro de conexão</h3>
                    <p>${err.message}</p>
                </div>
            `;
            });
    }

    // ============================================
    // Atualizar estatísticas
    // ============================================

    /**
     * Atualiza os cards de resumo (total, XGBoost, bons, ruins).
     */
    function atualizarEstatisticas() {
        const total = todosModelos.length;
        const xgboost = todosModelos.filter(m => getModeloTipo(m) === 'xgboost').length;
        const good = todosModelos.filter(m => getR2(m) >= 0.70).length;
        const bad = todosModelos.filter(m => getR2(m) < 0.70 && getR2(m) !== null).length;

        document.getElementById('statTotal').textContent = total;
        document.getElementById('statXgboost').textContent = xgboost;
        document.getElementById('statGood').textContent = good;
        document.getElementById('statBad').textContent = bad;
    }

    // ============================================
    // Filtrar e renderizar modelos
    // ============================================

    /**
     * Filtra os modelos baseado nos inputs de busca e selects.
     * Renderiza os cards filtrados.
     */
    function filtrarModelos() {
        const busca = (document.getElementById('searchInput').value || '').toLowerCase();
        const qualidade = document.getElementById('filterQuality').value;
        const tipo = document.getElementById('filterTipo').value;

        let filtrados = todosModelos.filter(m => {
            // Filtro de busca (ponto, tag, código)
            if (busca) {
                const cdPonto = String(m.cd_ponto || '');
                const tag = (m.metricas?.tag_principal || '').toLowerCase();
                const tags = (m.metricas?.tags_auxiliares || []).join(' ').toLowerCase();
                if (!cdPonto.includes(busca) && !tag.includes(busca) && !tags.includes(busca)) {
                    return false;
                }
            }

            // Filtro de qualidade
            if (qualidade) {
                const r2 = getR2(m);
                if (qualidade === 'excellent' && r2 < 0.90) return false;
                if (qualidade === 'good' && (r2 < 0.70 || r2 >= 0.90)) return false;
                if (qualidade === 'fair' && (r2 < 0.50 || r2 >= 0.70)) return false;
                if (qualidade === 'poor' && r2 >= 0.50) return false;
            }

            // Filtro de tipo
            if (tipo && getModeloTipo(m) !== tipo) {
                return false;
            }

            return true;
        });

        // Ordenar por R² decrescente
        filtrados.sort((a, b) => (getR2(b) || 0) - (getR2(a) || 0));

        renderizarModelos(filtrados);
    }

    /**
     * Renderiza os cards de modelos no container.
     * @param {Array} modelos - Lista filtrada de modelos
     */
    function renderizarModelos(modelos) {
        const container = document.getElementById('modelsContainer');

        if (modelos.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <ion-icon name="cube-outline"></ion-icon>
                    </div>
                    <h3>Nenhum modelo encontrado</h3>
                    <p>Nenhum modelo corresponde aos filtros aplicados</p>
                </div>
            `;
            return;
        }

        let html = '<div class="models-grid">';

        modelos.forEach(m => {
            const cdPonto = m.cd_ponto || '?';
            const metricas = m.metricas || {};
            const tipoModelo = getModeloTipo(m);
            const r2 = getR2(m);
            const mae = metricas.mae != null ? parseFloat(metricas.mae).toFixed(4) : '-';
            const rmse = metricas.rmse != null ? parseFloat(metricas.rmse).toFixed(4) : '-';
            const r2Display = r2 != null ? parseFloat(r2).toFixed(4) : '-';
            const mape = metricas.mape_pct != null ? parseFloat(metricas.mape_pct).toFixed(1) + '%' : '-';
            const correlacao = metricas.correlacao != null ? parseFloat(metricas.correlacao).toFixed(4) : '-';
            const tagPrincipal = metricas.tag_principal || '-';
            const nArvores = metricas.n_arvores || '-';
            const nFeatures = metricas.n_features || '-';
            const treinadoEm = metricas.treinado_em ? formatarData(metricas.treinado_em) : '-';
            const versao = metricas.versao_treino || '-';

            // Qualidade baseada no R²
            const qualidade = getQualidade(r2);
            const r2Pct = r2 != null ? Math.round(r2 * 100) : 0;

            html += `
                <div class="model-card" data-ponto="${cdPonto}">
                    <div class="model-card-header">
                        <div class="model-card-title">
                            <span class="ponto-id">#${cdPonto}</span>
                            <span class="ponto-nome" title="${escapeHtml(tagPrincipal)}">${escapeHtml(tagPrincipal)}</span>
                        </div>
                        <span class="model-card-tipo ${tipoModelo}">${tipoModelo.toUpperCase()} v${versao}</span>
                    </div>

                    <div class="model-card-body">
                        <!-- Métricas principais -->
                        <div class="metrics-grid">
                            <div class="metric-item">
                                <div class="metric-value">${r2Display}</div>
                                <div class="metric-label">R²</div>
                            </div>
                            <div class="metric-item">
                                <div class="metric-value">${mae}</div>
                                <div class="metric-label">MAE</div>
                            </div>
                            <div class="metric-item">
                                <div class="metric-value">${mape}</div>
                                <div class="metric-label">MAPE</div>
                            </div>
                        </div>

                        <!-- Barra de qualidade -->
                        <div class="model-quality">
                            <div class="quality-bar">
                                <div class="quality-bar-fill ${qualidade.classe}" style="width: ${r2Pct}%"></div>
                            </div>
                            <span class="quality-label ${qualidade.classe}">${qualidade.texto}</span>
                        </div>
                        <div class="model-info-row">
                            <span class="info-label">
                                <ion-icon name="calendar-outline"></ion-icon>
                                Histórico
                            </span>
                            <span class="info-value">${metricas.semanas_historico ? metricas.semanas_historico + ' semanas' : '—'}</span>
                        </div>
                        <!-- Informações extras -->
                        <div class="model-info-row">
                            <span class="info-label">
                                <ion-icon name="git-branch-outline"></ion-icon>
                                Árvores
                            </span>
                            <span class="info-value">${nArvores}</span>
                        </div>
                        <div class="model-info-row">
                            <span class="info-label">
                                <ion-icon name="layers-outline"></ion-icon>
                                Features
                            </span>
                            <span class="info-value">${nFeatures}</span>
                        </div>
                        <div class="model-info-row">
                            <span class="info-label">
                                <ion-icon name="time-outline"></ion-icon>
                                Treinado em
                            </span>
                            <span class="info-value">${treinadoEm}</span>
                        </div>
                    </div>

                    <div class="model-card-footer">
                        <button type="button" class="btn-model-action btn-details"
                            onclick="abrirDetalhes(${cdPonto})" title="Ver detalhes">
                            <ion-icon name="eye-outline"></ion-icon>
                            Detalhes
                        </button>
                        <button type="button" class="btn-model-action btn-diag"
                            onclick="abrirDiagnostico(${cdPonto})" title="Diagnosticar pré-requisitos">
                            <ion-icon name="medkit-outline"></ion-icon>
                            Diagnóstico
                        </button>
                        ${podeEditar ? `
                        <button type="button" class="btn-model-action btn-retrain"
                            onclick="retreinar(${cdPonto}, ${metricas.tipo_medidor || 1})" title="Retreinar modelo">
                            <ion-icon name="refresh-outline"></ion-icon>
                            Retreinar
                        </button>
                        <button type="button" class="btn-model-action btn-delete"
                            onclick="excluirModelo(${cdPonto})" title="Excluir modelo">
                            <ion-icon name="trash-outline"></ion-icon>
                            Excluir
                        </button>
                        ` : ''}
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;
    }

    // ============================================
    // Funções auxiliares
    // ============================================

    /**
     * Retorna o tipo do modelo (xgboost ou lstm).
     */
    function getModeloTipo(m) {
        return m.metricas?.modelo_tipo || (m.metricas?.versao_treino ? 'xgboost' : 'lstm');
    }

    /**
     * Retorna o R² do modelo (ou null).
     */
    function getR2(m) {
        const r2 = m.metricas?.r2;
        return r2 != null ? parseFloat(r2) : null;
    }

    /**
     * Retorna a classificação de qualidade baseada no R².
     * @param {number|null} r2 - Valor do R²
     * @returns {{classe: string, texto: string}}
     */
    function getQualidade(r2) {
        if (r2 === null || r2 === undefined) return { classe: 'poor', texto: 'Sem dados' };
        if (r2 >= 0.90) return { classe: 'excellent', texto: 'Excelente' };
        if (r2 >= 0.70) return { classe: 'good', texto: 'Bom' };
        if (r2 >= 0.50) return { classe: 'fair', texto: 'Regular' };
        return { classe: 'poor', texto: 'Baixo' };
    }

    /**
     * Formata data ISO para exibição (dd/mm/yyyy HH:mm).
     */
    function formatarData(isoString) {
        if (!isoString) return '-';
        try {
            const d = new Date(isoString);
            return d.toLocaleDateString('pt-BR') + ' ' +
                d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        } catch (e) {
            return isoString;
        }
    }

    /**
     * Escapa HTML para prevenir XSS.
     */
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ============================================
    // Modal de Detalhes
    // ============================================

    /**
     * Abre o modal com detalhes completos do modelo.
     * @param {number} cdPonto - Código do ponto
     */
    function abrirDetalhes(cdPonto) {
        const modelo = todosModelos.find(m => m.cd_ponto == cdPonto);
        if (!modelo) return;

        const metricas = modelo.metricas || {};
        const fi = metricas.feature_importance || {};
        const tagsAux = metricas.tags_auxiliares || [];

        // Título
        document.getElementById('modalDetalhesTitle').textContent =
            `Ponto #${cdPonto} - ${metricas.tag_principal || 'N/A'}`;

        // Montar corpo do modal
        let html = '';

        // Seção: Métricas de Performance
        html += `
            <div class="modal-section">
                <div class="modal-section-title">
                    <ion-icon name="speedometer-outline"></ion-icon>
                    Métricas de Performance
                </div>
                <div class="modal-metrics-grid">
                    <div class="modal-metric">
                        <div class="value">${fmt(metricas.r2)}</div>
                        <div class="label">R² (Determinação)</div>
                        <div class="hint">Ideal: &ge; 0.90</div>
                    </div>
                    <div class="modal-metric">
                        <div class="value">${fmt(metricas.mae)}</div>
                        <div class="label">MAE</div>
                        <div class="hint">Erro Absoluto Médio</div>
                    </div>
                    <div class="modal-metric">
                        <div class="value">${fmt(metricas.rmse)}</div>
                        <div class="label">RMSE</div>
                        <div class="hint">Raiz do Erro Quadrático</div>
                    </div>
                    <div class="modal-metric">
                        <div class="value">${metricas.mape_pct != null ? parseFloat(metricas.mape_pct).toFixed(1) + '%' : '-'}</div>
                        <div class="label">MAPE</div>
                        <div class="hint">Erro Percentual Médio</div>
                    </div>
                    <div class="modal-metric">
                        <div class="value">${fmt(metricas.correlacao)}</div>
                        <div class="label">Correlação</div>
                        <div class="hint">Ideal: &ge; 0.95</div>
                    </div>
                    <div class="modal-metric">
                        <div class="value">${metricas.n_arvores || '-'}</div>
                        <div class="label">Árvores</div>
                        <div class="hint">Early stopping</div>
                    </div>
                </div>
            </div>
        `;

        // Seção: Informações Gerais
        html += `
            <div class="modal-section">
                <div class="modal-section-title">
                    <ion-icon name="information-circle-outline"></ion-icon>
                    Informações do Modelo
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Tipo do Modelo</span>
                        <span class="value">${(metricas.modelo_tipo || 'N/A').toUpperCase()} v${metricas.versao_treino || '?'}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Tipo de Target</span>
                        <span class="value">${metricas.target_tipo || 'N/A'}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Amostras Treino</span>
                        <span class="value">${metricas.amostras_treino ? metricas.amostras_treino.toLocaleString('pt-BR') : '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Amostras Validação</span>
                        <span class="value">${metricas.amostras_validacao ? metricas.amostras_validacao.toLocaleString('pt-BR') : '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Learning Rate</span>
                        <span class="value">${metricas.learning_rate || '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Max Depth</span>
                        <span class="value">${metricas.max_depth || '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Features</span>
                        <span class="value">${metricas.n_features || '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Treinado em</span>
                        <span class="value">${formatarData(metricas.treinado_em)}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Lags utilizados</span>
                        <span class="value">${metricas.lags ? metricas.lags.join(', ') + 'h' : '-'}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Banco de Treino</span>
                        <span class="value" style="font-size:11px;">${metricas.banco_treino || '-'}</span>
                    </div>
                </div>
            </div>
        `;

        // Seção: Tags Auxiliares
        if (tagsAux.length > 0) {
            html += `
                <div class="modal-section">
                    <div class="modal-section-title">
                        <ion-icon name="pricetags-outline"></ion-icon>
                        Tags Auxiliares (${tagsAux.length})
                    </div>
                    <div class="tags-list">
                        ${tagsAux.map(t => `<span class="tag-badge">${escapeHtml(t)}</span>`).join('')}
                    </div>
                </div>
            `;
        }

        // Seção: Feature Importance (top 10)
        const fiEntries = Object.entries(fi);
        if (fiEntries.length > 0) {
            const top10 = fiEntries.slice(0, 10);
            const maxImportance = top10.length > 0 ? top10[0][1] : 1;

            html += `
                <div class="modal-section">
                    <div class="modal-section-title">
                        <ion-icon name="bar-chart-outline"></ion-icon>
                        Feature Importance (Top 10)
                    </div>
                    <ul class="feature-list">
            `;

            top10.forEach((entry, idx) => {
                const [name, importance] = entry;
                const pct = maxImportance > 0 ? (importance / maxImportance * 100) : 0;
                html += `
                    <li class="feature-item">
                        <span class="feature-rank ${idx < 3 ? 'top' : ''}">${idx + 1}</span>
                        <span class="feature-name">${escapeHtml(name)}</span>
                        <div class="feature-bar-wrapper">
                            <div class="feature-bar">
                                <div class="feature-bar-fill" style="width: ${pct}%"></div>
                            </div>
                        </div>
                        <span class="feature-value">${parseFloat(importance).toFixed(4)}</span>
                    </li>
                `;
            });

            html += '</ul></div>';
        }

        document.getElementById('modalDetalhesBody').innerHTML = html;
        document.getElementById('modalDetalhes').classList.add('active');
    }

    /**
     * Formata valor numérico para exibição.
     */
    function fmt(val) {
        if (val == null || val === '') return '-';
        return parseFloat(val).toFixed(4);
    }

    /**
     * Fecha o modal de detalhes.
     */
    function fecharModalDetalhes(event) {
        if (event && event.target !== event.currentTarget) return;
        document.getElementById('modalDetalhes').classList.remove('active');
    }

    // ============================================
    // Modal de Treino
    // ============================================

    /**
     * Abre o modal para treinar um novo modelo.
     */
    function abrirModalTreino() {
        if (!servicoOnline) {
            showToast('Serviço TensorFlow está offline. Verifique o container.', 'erro');
            return;
        }
        // Resetar formulário
        $('#selectPontoTreino').val('').trigger('change');
        document.getElementById('selectSemanas').value = '24';
        document.getElementById('chkForce').checked = false;
        document.getElementById('btnIniciarTreino').disabled = false;

        document.getElementById('modalTreino').classList.add('active');

        // Focar no Select2
        setTimeout(() => $('#selectPontoTreino').select2('open'), 300);
    }

    /**
     * Fecha o modal de treino.
     */
    function fecharModalTreino(event) {
        if (event && event.target !== event.currentTarget) return;
        document.getElementById('modalTreino').classList.remove('active');
    }

    // ============================================
    // Treinar / Retreinar modelo
    // ============================================

    /**
     * Alterna visibilidade do campo Ponto conforme o modo de treino.
     */
    function toggleModoPonto() {
        const modo = document.getElementById('selectModoTreino').value;
        const grupoPonto = document.getElementById('grupoPonto');
        const hint = document.getElementById('hintModo');
        const chkForce = document.getElementById('chkForce');

        if (modo === 'todos') {
            grupoPonto.style.display = 'none';
            hint.textContent = 'Treinar todos os pontos. Pode levar vários minutos.';
            // Forçar force=true para todos (sobrescrever existentes)
            chkForce.checked = true;
            chkForce.parentElement.style.display = 'none';
        } else {
            grupoPonto.style.display = '';
            hint.textContent = 'Treinar modelo para um único ponto de medição';
            chkForce.parentElement.style.display = '';
        }
    }

    /**
     * Inicia o treinamento (ponto único ou todos).
     */
    function iniciarTreino() {
        const modo = document.getElementById('selectModoTreino').value;
        const semanas = parseInt(document.getElementById('selectSemanas').value);

        if (modo === 'todos') {
            if (!confirm('Isso irá treinar/retreinar TODOS os pontos.\n\nO processo pode levar vários minutos. Deseja continuar?')) {
                return;
            }
            fecharModalTreino();
            executarTreinoTodos(semanas);
        } else {
            const cdPonto = $('#selectPontoTreino').val();
            if (!cdPonto) {
                showToast('Selecione um ponto de medição', 'aviso');
                return;
            }
            const force = document.getElementById('chkForce').checked;
            const tipoMedidor = parseInt($('#selectPontoTreino option:selected').data('tipo') || 1);

            fecharModalTreino();
            executarTreino(parseInt(cdPonto), tipoMedidor, semanas, force);
        }
    }

    /**
     * Executa treino de TODOS os pontos via endpoint.
     * Chama: python3 treinar_modelos.py --semanas N --output /app/models
     * @param {number} semanas - Semanas de histórico
     */
    function executarTreinoTodos(semanas) {
        document.getElementById('loadingText').textContent = 'Treinando todos os pontos...';
        document.getElementById('loadingSub').textContent =
            `${semanas} semanas de histórico | Isso pode levar vários minutos`;
        document.getElementById('loadingOverlay').classList.add('active');

        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'train_all',
                semanas: semanas
            })
        })
            .then(r => r.json())
            .then(data => {
                document.getElementById('loadingOverlay').classList.remove('active');

                if (data.success) {
                    showToast(data.message || 'Treino de todos os pontos finalizado', 'sucesso');
                    carregarModelos();
                } else {
                    showToast(data.error || 'Erro ao treinar', 'erro');
                }
            })
            .catch(err => {
                document.getElementById('loadingOverlay').classList.remove('active');
                showToast('Erro de conexão: ' + err.message, 'erro');
            });
    }
    /**
     * Abre modal de retreino com seleção de período.
     * @param {number} cdPonto - Código do ponto
     * @param {number} tipoMedidor - Tipo do medidor
     */
    function retreinar(cdPonto, tipoMedidor) {
        if (!servicoOnline) {
            showToast('Serviço TensorFlow está offline', 'erro');
            return;
        }

        // Preencher modal de retreino
        document.getElementById('retreinoPonto').textContent = '#' + cdPonto;
        document.getElementById('retreinoCdPonto').value = cdPonto;
        document.getElementById('retreinoTipoMedidor').value = tipoMedidor;
        document.getElementById('selectSemanasRetreino').value = '24';
        document.getElementById('modalRetreino').classList.add('active');
    }

    /**
     * Fecha o modal de retreino.
     */
    function fecharModalRetreino(event) {
        if (event && event.target !== event.currentTarget) return;
        document.getElementById('modalRetreino').classList.remove('active');
    }

    /**
     * Confirma e executa o retreino a partir do modal.
     */
    function confirmarRetreino() {
        const cdPonto = parseInt(document.getElementById('retreinoCdPonto').value);
        const tipoMedidor = parseInt(document.getElementById('retreinoTipoMedidor').value);
        const semanas = parseInt(document.getElementById('selectSemanasRetreino').value);

        fecharModalRetreino();
        executarTreino(cdPonto, tipoMedidor, semanas, true);
    }

    /**
     * Executa o treinamento chamando o endpoint predicaoTensorFlow.php.
     * @param {number} cdPonto - Código do ponto
     * @param {number} tipoMedidor - Tipo do medidor
     * @param {number} semanas - Semanas de histórico
     * @param {boolean} force - Forçar retreino
     */
    function executarTreino(cdPonto, tipoMedidor, semanas, force) {
        // Mostrar loading
        document.getElementById('loadingText').textContent = `Treinando modelo para ponto #${cdPonto}...`;
        document.getElementById('loadingSub').textContent =
            `${semanas} semanas de histórico | Isso pode levar alguns minutos`;
        document.getElementById('loadingOverlay').classList.add('active');

        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'train',
                cd_ponto: cdPonto,
                semanas: semanas,
                tipo_medidor: tipoMedidor,
                force: force
            })
        })
            .then(r => r.json())
            .then(data => {
                document.getElementById('loadingOverlay').classList.remove('active');

                if (data.success) {
                    showToast(data.message || `Modelo treinado com sucesso para ponto #${cdPonto}`, 'sucesso');

                    // Recarregar lista de modelos
                    carregarModelos();
                } else {
                    showToast(data.error || 'Erro ao treinar modelo', 'erro');
                }
            })
            .catch(err => {
                document.getElementById('loadingOverlay').classList.remove('active');
                showToast('Erro de conexão: ' + err.message, 'erro');
            });
    }

    // ============================================
    // Associações TAG Principal → Auxiliares
    // ============================================

    /** Dados de associações: { TAG: [auxiliares] } */
    let associacoes = {};
    /** TAG principal selecionada */
    let tagPrincipalSelecionada = null;
    /** Tags disponíveis: [{ TAG, NM_PONTO_MEDICAO, CD_PONTO_MEDICAO, ID_TIPO_MEDIDOR }] */
    let tagsDisponiveis = [];
    /** Mapa rápido TAG → info do ponto */
    let tagInfoMap = {};
    /** Select2 já inicializados */
    let select2Assoc = false;

    /**
     * Abre modal e carrega dados.
     */
    function abrirModalAssociacoes() {
        document.getElementById('modalAssociacoes').classList.add('active');
        carregarAssociacoes();
        carregarTagsDisponiveis();
    }

    /**
     * Fecha modal e destrói Select2 (evitar conflitos de z-index).
     */
    function fecharModalAssociacoes(event) {
        if (event && event.target !== event.currentTarget) return;
        document.getElementById('modalAssociacoes').classList.remove('active');
        // Destruir Select2 ao fechar para evitar overlays órfãos
        if (select2Assoc) {
            try {
                $('#selectNovaTagPrincipal').select2('destroy');
                $('#selectNovaAuxiliar').select2('destroy');
            } catch (e) { }
            select2Assoc = false;
        }
    }

    /**
     * Inicializa Select2 com matcher que busca por TAG, CD_PONTO e nome.
     */
    function initSelect2Assoc() {
        if (select2Assoc) return;

        const parentModal = $('#modalAssociacoes .modal-container');

        // Matcher customizado: pesquisa em TAG, CD_PONTO e nome do ponto
        function matcherAssoc(params, data) {
            // Se não há termo de busca, retorna tudo
            if (!params.term || params.term.trim() === '') return data;
            if (!data.element) return null;

            const termo = params.term.toLowerCase();
            const tag = (data.element.dataset.tag || '').toLowerCase();
            const cd = (data.element.dataset.cd || '').toLowerCase();
            const nome = (data.element.dataset.nome || '').toLowerCase();
            const texto = (data.text || '').toLowerCase();

            // Pesquisa em qualquer campo
            if (tag.includes(termo) || cd.includes(termo) || nome.includes(termo) || texto.includes(termo)) {
                return data;
            }

            return null;
        }

        $('#selectNovaTagPrincipal').select2({
            placeholder: 'Buscar por código, TAG ou nome...',
            allowClear: true,
            width: '100%',
            dropdownParent: parentModal,
            matcher: matcherAssoc,
            language: { noResults: () => 'Nenhuma TAG encontrada' }
        });

        $('#selectNovaAuxiliar').select2({
            placeholder: 'Buscar por código, TAG ou nome...',
            allowClear: true,
            width: '100%',
            dropdownParent: parentModal,
            matcher: matcherAssoc,
            language: { noResults: () => 'Nenhuma TAG encontrada' }
        });

        // Autofocus ao abrir os dropdowns
        $('#selectNovaTagPrincipal').on('select2:open', function () {
            setTimeout(() => document.querySelector('.select2-container--open .select2-search__field')?.focus(), 0);
        });

        $('#selectNovaAuxiliar').on('select2:open', function () {
            setTimeout(() => document.querySelector('.select2-container--open .select2-search__field')?.focus(), 0);
        });

        select2Assoc = true;
    }

    /**
     * Carrega associações existentes.
     */
    function carregarAssociacoes() {
        document.getElementById('assocListaPrincipais').innerHTML =
            '<div class="assoc-lista-empty"><ion-icon name="sync-outline" style="animation:spin 1s linear infinite;font-size:18px;"></ion-icon><br>Carregando...</div>';

        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'list_relations' })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.relacoes) {
                    associacoes = data.relacoes;
                    renderizarListaPrincipais();
                } else {
                    document.getElementById('assocListaPrincipais').innerHTML =
                        `<div class="assoc-lista-empty">${data.error || 'Erro ao carregar'}</div>`;
                }
            })
            .catch(err => {
                document.getElementById('assocListaPrincipais').innerHTML =
                    `<div class="assoc-lista-empty">Erro: ${err.message}</div>`;
            });
    }

    /**
     * Carrega TAGs disponíveis e popula os Select2.
     */
    function carregarTagsDisponiveis() {
        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'list_tags' })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.tags) {
                    tagsDisponiveis = data.tags;

                    // Montar mapa TAG → info
                    tagInfoMap = {};
                    tagsDisponiveis.forEach(t => {
                        tagInfoMap[t.TAG] = t;
                    });

                    preencherSelectTags('selectNovaTagPrincipal', tagsDisponiveis);
                    preencherSelectTags('selectNovaAuxiliar', tagsDisponiveis);

                    // Inicializar Select2 após popular
                    initSelect2Assoc();
                }
            })
            .catch(() => { });
    }

    /**
     * Preenche um <select> com TAGs.
     * O value é sempre a TAG (string), o texto mostra código + TAG + nome.
     */
    function preencherSelectTags(selectId, tags) {
        const select = document.getElementById(selectId);
        select.innerHTML = '<option value="">Selecione...</option>';
        tags.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.TAG;                          // Valor salvo = TAG
            opt.dataset.cd = t.CD_PONTO_MEDICAO;
            opt.dataset.tag = t.TAG;
            opt.dataset.nome = t.NM_PONTO_MEDICAO || '';
            opt.textContent = `#${t.CD_PONTO_MEDICAO} - ${t.TAG} (${t.NM_PONTO_MEDICAO || 'Sem nome'})`;
            select.appendChild(opt);
        });
    }

    /**
     * Retorna info complementar da TAG (CD_PONTO e nome).
     */
    function getTagInfo(tag) {
        const info = tagInfoMap[tag];
        if (info) return `#${info.CD_PONTO_MEDICAO} - ${info.NM_PONTO_MEDICAO || ''}`;
        return '';
    }

    /**
     * Renderiza lista lateral de TAGs principais.
     */
    function renderizarListaPrincipais() {
        const container = document.getElementById('assocListaPrincipais');
        const busca = (document.getElementById('assocSearchPrincipal').value || '').toLowerCase();

        const tags = Object.keys(associacoes).sort();
        const filtradas = busca
            ? tags.filter(t => {
                const info = getTagInfo(t).toLowerCase();
                return t.toLowerCase().includes(busca) || info.includes(busca);
            })
            : tags;

        document.getElementById('assocTotalPrincipais').textContent = `(${tags.length})`;

        if (filtradas.length === 0) {
            container.innerHTML = '<div class="assoc-lista-empty">Nenhuma associação encontrada</div>';
            return;
        }

        container.innerHTML = filtradas.map(tag => {
            const count = associacoes[tag] ? associacoes[tag].length : 0;
            const isActive = tag === tagPrincipalSelecionada;
            const info = getTagInfo(tag);
            return `
                <div class="assoc-lista-item ${isActive ? 'active' : ''}" onclick="selecionarPrincipal('${escapeHtml(tag)}')">
                    <div class="tag-info">
                        <span class="tag-name" title="${escapeHtml(tag)}">${escapeHtml(tag)}</span>
                        ${info ? `<span class="tag-ponto">${escapeHtml(info)}</span>` : ''}
                    </div>
                    <span class="tag-count">${count}</span>
                </div>
            `;
        }).join('');
    }

    /**
     * Filtra lista de principais.
     */
    function filtrarPrincipais() {
        renderizarListaPrincipais();
    }

    /**
     * Seleciona principal e mostra auxiliares.
     */
    function selecionarPrincipal(tag) {
        tagPrincipalSelecionada = tag;
        renderizarListaPrincipais();

        document.getElementById('assocDetailHeader').style.display = '';
        document.getElementById('assocAddRow').style.display = '';
        document.getElementById('assocTagSelecionada').textContent = tag;

        renderizarAuxiliares();
    }

    /**
     * Renderiza lista de auxiliares.
     */
    function renderizarAuxiliares() {
        const container = document.getElementById('assocDetailBody');
        const auxs = associacoes[tagPrincipalSelecionada] || [];

        if (auxs.length === 0) {
            container.innerHTML = `
                <div class="assoc-detail-empty">
                    <ion-icon name="link-outline"></ion-icon>
                    Nenhuma TAG auxiliar associada
                </div>`;
            return;
        }

        container.innerHTML = auxs.sort().map(aux => {
            const info = getTagInfo(aux);
            return `
                <div class="assoc-aux-item">
                    <div class="assoc-aux-info">
                        <span class="aux-tag">${escapeHtml(aux)}</span>
                        ${info ? `<span class="aux-ponto">${escapeHtml(info)}</span>` : ''}
                    </div>
                    <button type="button" class="btn-remove-aux" onclick="removerAuxiliar('${escapeHtml(aux)}')" title="Remover">
                        <ion-icon name="close-outline"></ion-icon>
                    </button>
                </div>
            `;
        }).join('');
    }

    /**
     * Cria nova TAG principal.
     */
    function criarNovaPrincipal() {
        const tag = $('#selectNovaTagPrincipal').val();
        if (!tag) {
            showToast('Selecione uma TAG', 'aviso');
            return;
        }
        if (associacoes[tag]) {
            showToast('Esta TAG já existe como principal', 'aviso');
            selecionarPrincipal(tag);
            return;
        }
        associacoes[tag] = [];
        renderizarListaPrincipais();
        selecionarPrincipal(tag);
        $('#selectNovaTagPrincipal').val('').trigger('change');
        showToast(`TAG ${tag} adicionada. Agora adicione as auxiliares.`, 'sucesso');
    }

    /**
     * Adiciona auxiliar à principal selecionada.
     */
    function adicionarAuxiliar() {
        const tagAux = $('#selectNovaAuxiliar').val();

        if (!tagAux) {
            showToast('Selecione uma TAG auxiliar', 'aviso');
            return;
        }
        if (tagAux === tagPrincipalSelecionada) {
            showToast('A TAG auxiliar não pode ser igual à principal', 'aviso');
            return;
        }
        if ((associacoes[tagPrincipalSelecionada] || []).includes(tagAux)) {
            showToast('Esta TAG auxiliar já está associada', 'aviso');
            return;
        }

        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'add_relation',
                tag_principal: tagPrincipalSelecionada,
                tag_auxiliar: tagAux
            })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (!associacoes[tagPrincipalSelecionada]) associacoes[tagPrincipalSelecionada] = [];
                    associacoes[tagPrincipalSelecionada].push(tagAux);
                    renderizarAuxiliares();
                    renderizarListaPrincipais();
                    $('#selectNovaAuxiliar').val('').trigger('change');
                    showToast(`TAG ${tagAux} associada com sucesso`, 'sucesso');
                } else {
                    showToast(data.error || 'Erro ao adicionar', 'erro');
                }
            })
            .catch(err => showToast('Erro: ' + err.message, 'erro'));
    }

    /**
     * Remove uma auxiliar.
     */
    function removerAuxiliar(tagAux) {
        if (!confirm(`Remover a associação "${tagAux}" de "${tagPrincipalSelecionada}"?`)) return;

        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'delete_relation',
                tag_principal: tagPrincipalSelecionada,
                tag_auxiliar: tagAux
            })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    associacoes[tagPrincipalSelecionada] = (associacoes[tagPrincipalSelecionada] || [])
                        .filter(t => t !== tagAux);
                    renderizarAuxiliares();
                    renderizarListaPrincipais();
                    showToast('Associação removida', 'sucesso');
                } else {
                    showToast(data.error || 'Erro ao remover', 'erro');
                }
            })
            .catch(err => showToast('Erro: ' + err.message, 'erro'));
    }

    /**
     * Exclui todas as associações de uma principal.
     */
    function excluirPrincipal() {
        const tag = tagPrincipalSelecionada;
        if (!confirm(`Excluir TODAS as associações de "${tag}"?\n\nIsso removerá a TAG principal e todas suas auxiliares.`)) return;

        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'delete_all_relations',
                tag_principal: tag
            })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    delete associacoes[tag];
                    tagPrincipalSelecionada = null;
                    renderizarListaPrincipais();
                    document.getElementById('assocDetailHeader').style.display = 'none';
                    document.getElementById('assocAddRow').style.display = 'none';
                    document.getElementById('assocDetailBody').innerHTML = `
                    <div class="assoc-detail-empty">
                        <ion-icon name="arrow-back-outline"></ion-icon>
                        Selecione uma TAG principal
                    </div>`;
                    showToast(`Associações de ${tag} excluídas`, 'sucesso');
                } else {
                    showToast(data.error || 'Erro ao excluir', 'erro');
                }
            })
            .catch(err => showToast('Erro: ' + err.message, 'erro'));
    }

    /**
     * Exclui o modelo treinado de um ponto (remove pasta inteira).
     * @param {number} cdPonto - Código do ponto
     */
    function excluirModelo(cdPonto) {
        if (!confirm(`Excluir o modelo treinado do ponto #${cdPonto}?\n\nIsso removerá todos os arquivos do modelo (model.json, metricas.json).\nO ponto precisará ser retreinado.`)) return;

        fetch('bd/operacoes/predicaoTensorFlow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'delete_model', cd_ponto: cdPonto })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(`Modelo do ponto #${cdPonto} excluído`, 'sucesso');
                    carregarModelos();
                } else {
                    showToast(data.error || 'Erro ao excluir', 'erro');
                }
            })
            .catch(err => showToast('Erro: ' + err.message, 'erro'));
    }

    // ============================================
    // Sincronização Flowchart ↔ ML
    // ============================================

    /** Cache do último sync check */
    let ultimoSyncCheck = null;

    /**
     * Verifica divergências ao carregar a página.
     * Chamado automaticamente no carregamento.
     */
    function verificarSyncFlowchart() {
        fetch('bd/operacoes/flowchartSync.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'sync_check', cd_sistema: 0 })
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    // Silencioso — se falhar, não mostra banner
                    console.warn('Sync check falhou:', data.error);
                    return;
                }
                atualizarBannerSync(data);
            })
            .catch(err => {
                console.warn('Erro ao verificar sync:', err.message);
            });
    }

    /**
     * Atualiza o banner de sincronização com base no resultado do check.
     * @param {Object} data - Resultado do sync_check
     */
    function atualizarBannerSync(data) {
        const banner = document.getElementById('syncBanner');
        const titulo = document.getElementById('syncBannerTitle');
        const msg = document.getElementById('syncBannerMsg');
        const icon = document.getElementById('syncBannerIcon');
        const btnRevisar = document.getElementById('btnRevisarSync');

        if (data.tem_divergencia) {
            const r = data.resumo;
            banner.className = 'sync-banner divergente';
            icon.setAttribute('name', 'warning-outline');
            titulo.textContent = 'Flowchart desatualizado em relação às relações ML';
            let partes = [];
            if (r.total_novas > 0) partes.push(`${r.total_novas} nova(s)`);
            if (r.total_removidas > 0) partes.push(`${r.total_removidas} removida(s)`);
            msg.textContent = `Divergências encontradas: ${partes.join(', ')}. Revise a sincronização.`;
            btnRevisar.style.display = '';
        } else {
            banner.className = 'sync-banner sincronizado';
            icon.setAttribute('name', 'checkmark-circle-outline');
            titulo.textContent = 'Relações ML sincronizadas com o Flowchart';
            msg.textContent = `${data.resumo.total_inalteradas} relação(ões) ativas — tudo alinhado.`;
            btnRevisar.style.display = 'none';
        }
    }

    /**
     * Abre o modal de sincronização e executa o check.
     */
    function abrirModalSync() {
        document.getElementById('modalSync').classList.add('active');
        executarSyncCheck();
    }

    /** Fecha o modal de sincronização. */
    function fecharModalSync() {
        document.getElementById('modalSync').classList.remove('active');
    }

    /** Abre o modal de regras. */
    function abrirModalRegras() {
        document.getElementById('modalRegras').classList.add('active');
    }

    /** Fecha o modal de regras. */
    function fecharModalRegras() {
        document.getElementById('modalRegras').classList.remove('active');
    }

    /**
     * Executa o sync_check com o sistema selecionado e renderiza o diff no modal.
     */
    function executarSyncCheck() {
        const cdSistema = syncSistemaSelecionado || 0;
        const conteudo = document.getElementById('syncConteudo');
        const btnAplicar = document.getElementById('btnAplicarSync');
        btnAplicar.disabled = true;

        // Loading
        conteudo.innerHTML = `
            <div class="sync-loading">
                <ion-icon name="sync-outline"></ion-icon>
                <p>Analisando divergências...</p>
            </div>
        `;

        fetch('bd/operacoes/flowchartSync.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'sync_check', cd_sistema: cdSistema })
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    conteudo.innerHTML = `
                    <div class="sync-vazio">
                        <ion-icon name="alert-circle-outline"></ion-icon>
                        <p>${escapeHtml(data.error || 'Erro ao verificar')}</p>
                    </div>`;
                    return;
                }
                ultimoSyncCheck = data;
                renderizarDiffSync(data);
            })
            .catch(err => {
                conteudo.innerHTML = `
                <div class="sync-vazio">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    <p>Erro de conexão: ${escapeHtml(err.message)}</p>
                </div>`;
            });
    }

    /**
     * Renderiza o diff de sincronização no modal.
     * @param {Object} data - Resultado do sync_check
     */
    function renderizarDiffSync(data) {
        const conteudo = document.getElementById('syncConteudo');
        const r = data.resumo;

        let html = '';

        // --- Resumo em cards ---
        html += `
            <div class="sync-resumo">
                <div class="sync-resumo-item flowchart">
                    <div class="valor">${r.total_flowchart}</div>
                    <div class="rotulo">No Flowchart</div>
                </div>
                <div class="sync-resumo-item novas">
                    <div class="valor">${r.total_novas}</div>
                    <div class="rotulo">Novas</div>
                </div>
                <div class="sync-resumo-item removidas">
                    <div class="valor">${r.total_removidas}</div>
                    <div class="rotulo">Removidas</div>
                </div>
                <div class="sync-resumo-item inalteradas">
                    <div class="valor">${r.total_inalteradas}</div>
                    <div class="rotulo">Inalteradas</div>
                </div>
            </div>
        `;

        // --- Seção: Novas (a adicionar) ---
        if (data.novas.length > 0) {
            html += renderizarSecaoDiff('novas', 'Novas relações (adicionar)', data.novas, true);
        }

        // --- Seção: Removidas (a excluir) ---
        if (data.removidas.length > 0) {
            html += renderizarSecaoDiff('removidas', 'Relações removidas (excluir da tabela ML)', data.removidas, true);
        }

        // --- Seção: Inalteradas (informativo) ---
        if (data.inalteradas.length > 0) {
            html += `
                <div class="sync-section collapsed" id="secInalteradas">
                    <div class="sync-section-header inalteradas" onclick="toggleSecaoSync('secInalteradas')">
                        <ion-icon name="chevron-down-outline" class="toggle"></ion-icon>
                        <ion-icon name="checkmark-circle-outline"></ion-icon>
                        <h4>Relações inalteradas (já sincronizadas)</h4>
                        <span class="badge">${data.inalteradas.length}</span>
                    </div>
                    <div class="sync-section-body">
                        ${data.inalteradas.map(p => `
                            <div class="sync-row" style="opacity:0.6;">
                                <span class="tag-principal">
                                    <span class="codigo-ponto">${escapeHtml(p.codigo_principal || '—')}</span>
                                    <span class="tag-nome" title="${escapeHtml(p.tag_principal)}">${escapeHtml(p.tag_principal)}</span>
                                </span>
                                <span class="seta">→</span>
                                <span class="tag-auxiliar">
                                    <span class="codigo-ponto">${escapeHtml(p.codigo_auxiliar || '—')}</span>
                                    <span class="tag-nome" title="${escapeHtml(p.tag_auxiliar)}">${escapeHtml(p.tag_auxiliar)}</span>
                                </span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        // Sem divergências
        if (data.novas.length === 0 && data.removidas.length === 0) {
            html += `
                <div class="sync-vazio">
                    <ion-icon name="checkmark-circle-outline" style="color:#22c55e;"></ion-icon>
                    <p style="color:#166534;font-weight:600;">Tudo sincronizado!</p>
                    <p>As relações ML correspondem à topologia do Flowchart.</p>
                </div>
            `;
        }

        conteudo.innerHTML = html;
        atualizarBotaoAplicarSync();
    }

    /**
     * Renderiza uma seção do diff (novas ou removidas) com checkboxes.
     * @param {string} tipo    - 'novas' ou 'removidas'
     * @param {string} titulo  - Título da seção
     * @param {Array}  pares   - Array de { tag_principal, tag_auxiliar }
     * @param {boolean} checado - Se checkboxes vêm marcados por padrão
     * @returns {string} HTML
     */
    function renderizarSecaoDiff(tipo, titulo, pares, checado) {
        const icone = tipo === 'novas' ? 'add-circle-outline' : 'remove-circle-outline';
        let html = `
            <div class="sync-section" id="sec_${tipo}">
                <div class="sync-section-header ${tipo}" onclick="toggleSecaoSync('sec_${tipo}')">
                    <ion-icon name="chevron-down-outline" class="toggle"></ion-icon>
                    <ion-icon name="${icone}"></ion-icon>
                    <h4>${titulo}</h4>
                    <span class="badge">${pares.length}</span>
                </div>
                <div class="sync-section-body">
                    <div class="sync-select-all">
                        <input type="checkbox" id="chkAll_${tipo}" ${checado ? 'checked' : ''} 
                               onchange="toggleTodosSync('${tipo}', this.checked)">
                        <label for="chkAll_${tipo}">Selecionar todos</label>
                    </div>
        `;

        pares.forEach((p, idx) => {
            const codPrincipal = p.codigo_principal || '—';
            const codAuxiliar = p.codigo_auxiliar || '—';
            html += `
                <div class="sync-row">
                    <input type="checkbox" class="chk-sync chk-${tipo}" 
                           data-tipo="${tipo}"
                           data-principal="${escapeHtml(p.tag_principal)}" 
                           data-auxiliar="${escapeHtml(p.tag_auxiliar)}"
                           ${checado ? 'checked' : ''}
                           onchange="atualizarBotaoAplicarSync()">
                    <span class="tag-principal">
                        <span class="codigo-ponto">${escapeHtml(codPrincipal)}</span>
                        <span class="tag-nome" title="${escapeHtml(p.tag_principal)}">${escapeHtml(p.tag_principal)}</span>
                    </span>
                    <span class="seta">→</span>
                    <span class="tag-auxiliar">
                        <span class="codigo-ponto">${escapeHtml(codAuxiliar)}</span>
                        <span class="tag-nome" title="${escapeHtml(p.tag_auxiliar)}">${escapeHtml(p.tag_auxiliar)}</span>
                    </span>
                </div>
            `;
        });

        html += `</div></div>`;
        return html;
    }

    /**
     * Alterna expansão/colapso de uma seção do diff.
     * @param {string} idSecao - ID do elemento da seção
     */
    function toggleSecaoSync(idSecao) {
        document.getElementById(idSecao).classList.toggle('collapsed');
    }

    /**
     * Marca/desmarca todos os checkboxes de uma seção.
     * @param {string}  tipo    - 'novas' ou 'removidas'
     * @param {boolean} marcado - Estado desejado
     */
    function toggleTodosSync(tipo, marcado) {
        document.querySelectorAll(`.chk-${tipo}`).forEach(chk => {
            chk.checked = marcado;
        });
        atualizarBotaoAplicarSync();
    }

    /**
     * Atualiza estado do botão "Aplicar" baseado em checkboxes selecionados.
     */
    function atualizarBotaoAplicarSync() {
        const totalSelecionados = document.querySelectorAll('.chk-sync:checked').length;
        const btn = document.getElementById('btnAplicarSync');
        btn.disabled = totalSelecionados === 0;
        btn.innerHTML = totalSelecionados > 0
            ? `<ion-icon name="checkmark-outline"></ion-icon> Aplicar ${totalSelecionados} alteração(ões)`
            : `<ion-icon name="checkmark-outline"></ion-icon> Aplicar Selecionados`;
    }

    /**
     * Aplica as alterações selecionadas (novas + removidas).
     */
    function aplicarSync() {
        const adicionar = [];
        const remover = [];

        // Coletar checkboxes marcados
        document.querySelectorAll('.chk-sync:checked').forEach(chk => {
            const par = {
                tag_principal: chk.dataset.principal,
                tag_auxiliar: chk.dataset.auxiliar
            };
            if (chk.dataset.tipo === 'novas') {
                adicionar.push(par);
            } else if (chk.dataset.tipo === 'removidas') {
                remover.push(par);
            }
        });

        if (adicionar.length === 0 && remover.length === 0) {
            showToast('Nenhuma alteração selecionada', 'aviso');
            return;
        }

        // Confirmação
        const msgConfirm = [];
        if (adicionar.length > 0) msgConfirm.push(`Adicionar ${adicionar.length} relação(ões)`);
        if (remover.length > 0) msgConfirm.push(`Remover ${remover.length} relação(ões)`);
        if (!confirm(`Confirmar sincronização?\n\n${msgConfirm.join('\n')}\n\nEsta ação altera a tabela de relações ML.`)) {
            return;
        }

        // Desabilitar botão durante operação
        const btn = document.getElementById('btnAplicarSync');
        btn.disabled = true;
        btn.innerHTML = '<ion-icon name="sync-outline" style="animation:spin 1s linear infinite;"></ion-icon> Aplicando...';

        fetch('bd/operacoes/flowchartSync.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'sync_apply',
                adicionar: adicionar,
                remover: remover
            })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Sincronização aplicada com sucesso!', 'sucesso');
                    fecharModalSync();
                    // Re-verificar para atualizar banner
                    verificarSyncFlowchart();
                    // Recarregar associações se o modal de associações estiver ativo
                    if (typeof carregarAssociacoes === 'function') {
                        try { carregarAssociacoes(); } catch (e) { }
                    }
                } else {
                    showToast(data.error || 'Erro ao aplicar sincronização', 'erro');
                    btn.disabled = false;
                    btn.innerHTML = '<ion-icon name="checkmark-outline"></ion-icon> Aplicar Selecionados';
                }
            })
            .catch(err => {
                showToast('Erro de conexão: ' + err.message, 'erro');
                btn.disabled = false;
                btn.innerHTML = '<ion-icon name="checkmark-outline"></ion-icon> Aplicar Selecionados';
            });
    }

    // --- Verificar sincronização ao carregar a página ---
    // Adicionar esta chamada dentro do DOMContentLoaded ou no final do script:
    verificarSyncFlowchart();
    fecharModalDiag();
    // ============================================
    // Dropdown customizado de Sistemas (sem Select2)
    // ============================================

    /** Valor selecionado no dropdown de sistema */
    let syncSistemaSelecionado = 0;

    /**
     * Abre/fecha o dropdown de sistemas.
     */
    function toggleSyncDropdown() {
        const wrapper = document.getElementById('syncDropdownWrapper');
        const isOpen = wrapper.classList.contains('open');
        if (isOpen) {
            fecharSyncDropdown();
        } else {
            wrapper.classList.add('open');
            // Focar no campo de busca
            setTimeout(() => {
                document.getElementById('syncDropdownSearch').focus();
            }, 50);
        }
    }

    /**
     * Fecha o dropdown de sistemas.
     */
    function fecharSyncDropdown() {
        const wrapper = document.getElementById('syncDropdownWrapper');
        wrapper.classList.remove('open');
        // Limpar busca
        const search = document.getElementById('syncDropdownSearch');
        if (search) {
            search.value = '';
            filtrarSyncSistemas('');
        }
    }

    /**
     * Seleciona um sistema no dropdown e dispara o sync check.
     * @param {number} valor - CD_CHAVE do sistema (0 = todos)
     * @param {string} label - Nome para exibir
     */
    function selecionarSyncSistema(valor, label) {
        syncSistemaSelecionado = valor;
        document.getElementById('syncDropdownLabel').textContent = label;

        // Atualizar visual: marcar o selecionado
        document.querySelectorAll('#syncDropdownOptions .sync-dropdown-option').forEach(opt => {
            opt.classList.toggle('selected', parseInt(opt.dataset.value) === valor);
        });

        fecharSyncDropdown();
        executarSyncCheck();
    }

    /**
     * Filtra as opções do dropdown por texto digitado.
     * @param {string} texto - Termo de busca
     */
    function filtrarSyncSistemas(texto) {
        const termo = texto.toLowerCase().trim();
        document.querySelectorAll('#syncDropdownOptions .sync-dropdown-option').forEach(opt => {
            if (parseInt(opt.dataset.value) === 0) {
                // "Todos os Sistemas" sempre visível
                opt.classList.remove('hidden');
                return;
            }
            const nome = opt.textContent.toLowerCase();
            opt.classList.toggle('hidden', termo !== '' && !nome.includes(termo));
        });
    }

    // Fechar dropdown ao clicar fora
    document.addEventListener('click', function (e) {
        const wrapper = document.getElementById('syncDropdownWrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            fecharSyncDropdown();
        }
    });

    // ============================================
    // Diagnóstico ML
    // ============================================

    /** Dados do último diagnóstico executado */
    var ultimoDiagnostico = null;

    /**
     * Abre o modal de diagnóstico e executa verificação para um ponto.
     * Pode ser chamado a partir do card do modelo ou do modal de novo treino.
     * @param {number} cdPonto - Código do ponto de medição
     */
    function abrirDiagnostico(cdPonto) {
        ultimoDiagnostico = null;

        // Abrir modal
        document.getElementById('modalDiagnostico').classList.add('active');
        document.getElementById('diagLoading').style.display = 'block';
        document.getElementById('diagConteudo').style.display = 'none';
        document.getElementById('diagBtnTreinar').style.display = 'none';
        document.getElementById('diagBtnTreinar').disabled = true;
        document.getElementById('diagPontoCodigo').textContent = '#' + cdPonto;
        document.getElementById('diagPontoNome').textContent = 'Carregando...';

        // Executar diagnóstico
        fetch('bd/operacoes/diagnosticoML.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'diagnostico', cd_ponto: cdPonto })
        })
            .then(r => r.json())
            .then(data => {
                document.getElementById('diagLoading').style.display = 'none';
                document.getElementById('diagConteudo').style.display = 'block';

                if (!data.success) {
                    document.getElementById('diagConteudo').innerHTML = `
                    <div style="text-align:center;padding:30px;color:#ef4444;">
                        <ion-icon name="alert-circle-outline" style="font-size:32px;"></ion-icon>
                        <p style="margin-top:10px;">${escapeHtml(data.error || 'Erro desconhecido')}</p>
                    </div>`;
                    return;
                }

                ultimoDiagnostico = data;

                // Atualizar info do ponto
                document.getElementById('diagPontoCodigo').textContent = data.codigo_formatado || ('#' + cdPonto);
                document.getElementById('diagPontoNome').textContent = data.ds_nome || '';

                // Renderizar etapas
                renderizarDiagnostico(data);
            })
            .catch(err => {
                document.getElementById('diagLoading').style.display = 'none';
                document.getElementById('diagConteudo').style.display = 'block';
                document.getElementById('diagConteudo').innerHTML = `
                <div style="text-align:center;padding:30px;color:#ef4444;">
                    <ion-icon name="wifi-outline" style="font-size:32px;"></ion-icon>
                    <p style="margin-top:10px;">Erro de conexão: ${escapeHtml(err.message)}</p>
                </div>`;
            });
    }

    /**
     * Renderiza o resultado do diagnóstico como timeline.
     * @param {Object} data - Resposta do endpoint diagnosticoML.php
     */
    function renderizarDiagnostico(data) {
        const etapas = data.etapas || [];
        const resumo = data.resumo || {};

        let html = '<div class="diag-timeline">';

        etapas.forEach(etapa => {
            // Ícone por status
            const icones = {
                ok: 'checkmark-outline',
                alerta: 'alert-outline',
                erro: 'close-outline'
            };
            const icone = icones[etapa.status] || 'help-outline';

            // Detalhes (colapsável)
            const temDetalhes = etapa.detalhes && etapa.detalhes.length > 0;
            const expandidoInicial = etapa.status !== 'ok'; // Expandir alertas/erros

            html += `
                <div class="diag-etapa ${expandidoInicial ? 'expanded' : ''}"
                     id="diagEtapa_${etapa.numero}">
                    <!-- Ícone na timeline -->
                    <div class="diag-etapa-icon ${etapa.status}">
                        <ion-icon name="${icone}"></ion-icon>
                    </div>
                    <!-- Header clicável -->
                    <div class="diag-etapa-header"
                         onclick="${temDetalhes ? `toggleDiagEtapa(${etapa.numero})` : ''}">
                        <span class="etapa-numero">${etapa.numero}.</span>
                        <span class="etapa-titulo">${escapeHtml(etapa.titulo)}</span>
                        <span class="etapa-msg ${etapa.status}">${escapeHtml(etapa.mensagem)}</span>
                        ${temDetalhes ? '<ion-icon name="chevron-down-outline" class="etapa-toggle"></ion-icon>' : ''}
                    </div>`;

            // Corpo com detalhes
            if (temDetalhes) {
                html += '<div class="diag-etapa-body">';
                etapa.detalhes.forEach(d => {
                    html += `
                        <div class="diag-detalhe-row">
                            <span class="dl-label">${escapeHtml(d.label)}</span>
                            <span class="dl-valor">${escapeHtml(d.valor)}</span>
                        </div>`;
                });
                html += '</div>';
            }

            html += '</div>';
        });

        html += '</div>';

        // Resumo / veredicto
        if (resumo.bloqueios > 0) {
            html += `
                <div class="diag-resumo bloqueado">
                    <ion-icon name="close-circle-outline"></ion-icon>
                    <div>
                        <div class="resumo-texto">${escapeHtml(resumo.veredicto)}</div>
                        <div class="resumo-detalhe">${resumo.ok} ok · ${resumo.alertas} alerta(s) · ${resumo.bloqueios} bloqueio(s)</div>
                    </div>
                </div>`;
        } else if (resumo.alertas > 0) {
            html += `
                <div class="diag-resumo viavel-alerta">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    <div>
                        <div class="resumo-texto">${escapeHtml(resumo.veredicto)}</div>
                        <div class="resumo-detalhe">${resumo.ok} ok · ${resumo.alertas} alerta(s) — modelo pode treinar mas com qualidade reduzida</div>
                    </div>
                </div>`;
        } else {
            html += `
                <div class="diag-resumo viavel">
                    <ion-icon name="checkmark-circle-outline"></ion-icon>
                    <div>
                        <div class="resumo-texto">${escapeHtml(resumo.veredicto)}</div>
                        <div class="resumo-detalhe">Todos os pré-requisitos atendidos</div>
                    </div>
                </div>`;
        }

        document.getElementById('diagConteudo').innerHTML = html;

        // Mostrar botão "Treinar" se viável e o usuário tiver permissão
        const btnTreinar = document.getElementById('diagBtnTreinar');
        if (resumo.viavel && typeof podeEditar !== 'undefined' && podeEditar) {
            btnTreinar.style.display = 'inline-flex';
            btnTreinar.disabled = false;
        }
    }

    /**
     * Expande/colapsa uma etapa do diagnóstico.
     * @param {number} numero - Número da etapa
     */
    function toggleDiagEtapa(numero) {
        const etapa = document.getElementById('diagEtapa_' + numero);
        if (etapa) etapa.classList.toggle('expanded');
    }

    /**
     * Fecha o modal de diagnóstico.
     */
    function fecharModalDiag() {
        document.getElementById('modalDiagnostico').classList.remove('active');
        ultimoDiagnostico = null;
    }

    /**
     * Treina o ponto direto do modal de diagnóstico.
     * Reutiliza a função retreinar() já existente.
     */
    function treinarAposDiag() {
        if (!ultimoDiagnostico) return;
        const cdPonto = ultimoDiagnostico.cd_ponto;
        // Buscar tipo do medidor a partir do diagnóstico (etapa 2)
        const etapaTipo = (ultimoDiagnostico.etapas || []).find(e => e.numero === 2);
        let tipoMedidor = 1;
        if (etapaTipo && etapaTipo.mensagem) {
            const match = etapaTipo.mensagem.match(/Tipo (\d+)/);
            if (match) tipoMedidor = parseInt(match[1]);
        }
        fecharModalDiag();
        retreinar(cdPonto, tipoMedidor);
    }
</script>

<?php include_once 'includes/footer.inc.php'; ?>
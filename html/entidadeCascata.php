<?php
/**
 * SIMP - Cadastro em Cascata v3 — Flowchart Visual
 * Canvas interativo com Drawflow + lista lateral read-only
 * @author Bruno - CESAN
 * @version 3.0
 */
include_once 'includes/header.inc.php';
include_once 'bd/conexao.php';
recarregarPermissoesUsuario();
exigePermissaoTela('flowchart', ACESSO_LEITURA);
$podeEditar = podeEditarTela('flowchart');
include_once 'includes/menu.inc.php';

/* Pontos de medição para select */
$pontosMedicao = [];
try {
    $sql = "SELECT PM.CD_PONTO_MEDICAO, PM.DS_NOME, PM.ID_TIPO_MEDIDOR, L.CD_LOCALIDADE, L.CD_UNIDADE,
                   COALESCE(PM.DS_TAG_VAZAO, PM.DS_TAG_PRESSAO, PM.DS_TAG_RESERVATORIO, PM.DS_TAG_VOLUME, PM.DS_TAG_TEMP_AGUA, PM.DS_TAG_TEMP_AMBIENTE) AS DS_TAG
            FROM SIMP.dbo.PONTO_MEDICAO PM
            LEFT JOIN SIMP.dbo.LOCALIDADE L ON L.CD_CHAVE = PM.CD_LOCALIDADE
            WHERE (PM.DT_DESATIVACAO IS NULL OR PM.DT_DESATIVACAO > GETDATE())
            ORDER BY PM.DS_NOME";
    $stmt = $pdoSIMP->query($sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $letras = [1 => 'M', 2 => 'E', 4 => 'P', 6 => 'R', 8 => 'H'];
        $letra = $letras[$row['ID_TIPO_MEDIDOR']] ?? 'X';
        $codigo = ($row['CD_LOCALIDADE'] ?? '0') . '-' . str_pad($row['CD_PONTO_MEDICAO'], 6, '0', STR_PAD_LEFT) . '-' . $letra . '-' . ($row['CD_UNIDADE'] ?? '0');
        $pontosMedicao[] = ['cd' => $row['CD_PONTO_MEDICAO'], 'codigo' => $codigo, 'nome' => $row['DS_NOME'], 'tag' => $row['DS_TAG'] ?? ''];
    }
} catch (Exception $e) {
}

/* Sistemas de Água para select */
$sistemasAgua = [];
try {
    $sql = "SELECT SA.CD_CHAVE, SA.DS_NOME, SA.DS_DESCRICAO,
                   LOC.DS_NOME AS DS_LOCALIDADE
            FROM SIMP.dbo.SISTEMA_AGUA SA
            LEFT JOIN SIMP.dbo.LOCALIDADE LOC ON LOC.CD_CHAVE = SA.CD_LOCALIDADE
            ORDER BY SA.DS_NOME";
    $stmt = $pdoSIMP->query($sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sistemasAgua[] = [
            'cd' => (int) $row['CD_CHAVE'],
            'nome' => $row['DS_NOME'],
            'loc' => $row['DS_LOCALIDADE']
        ];
    }
} catch (Exception $e) {
}
?>

<!-- ============================================
     Drawflow CSS + JS (CDN)
     ============================================ -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jerosoler/Drawflow@0.0.59/dist/drawflow.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
    /* ============================================
   SIMP — Cadastro em Cascata v3 (Flowchart)
   Padrão visual SIMP (slate/navy)
   ============================================ */

    /* --- Container --- */
    .page-container {
        padding: 24px;
        max-width: 1800px;
        margin: 0 auto
    }

    /* --- Header padrão SIMP --- */
    .page-header {
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        border-radius: 16px;
        padding: 24px 28px;
        margin-bottom: 24px;
        color: #fff
    }

    .page-header-content {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap
    }

    .page-header-icon {
        width: 52px;
        height: 52px;
        background: rgba(255, 255, 255, .15);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        flex-shrink: 0
    }

    .page-header h1 {
        font-size: 20px;
        font-weight: 700;
        margin: 0 0 2px;
        color: #fff
    }

    .page-header-subtitle {
        font-size: 12px;
        color: rgba(255, 255, 255, .7);
        margin: 0
    }

    /* --- Stats --- */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 14px;
        margin-bottom: 20px
    }

    .stat-card {
        background: #fff;
        border-radius: 12px;
        padding: 14px;
        display: flex;
        align-items: center;
        gap: 12px;
        border: 1px solid #e2e8f0;
        transition: transform .2s, box-shadow .2s
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, .08)
    }

    .stat-card-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: #fff;
        flex-shrink: 0
    }

    .stat-card-icon.nos {
        background: linear-gradient(135deg, #3b82f6, #2563eb)
    }

    .stat-card-icon.raizes {
        background: linear-gradient(135deg, #10b981, #059669)
    }

    .stat-card-icon.pontos {
        background: linear-gradient(135deg, #f59e0b, #d97706)
    }

    .stat-card-icon.conexoes {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed)
    }

    .stat-card-icon.niveis {
        background: linear-gradient(135deg, #06b6d4, #0891b2)
    }

    .stat-card-info h3 {
        font-size: 22px;
        font-weight: 700;
        margin: 0;
        color: #1e293b;
        line-height: 1
    }

    .stat-card-info p {
        font-size: 11px;
        margin: 3px 0 0;
        color: #64748b
    }

    /* --- Layout principal: sidebar + canvas --- */
    .flow-layout {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 0;
        height: calc(100vh - 280px);
        min-height: 500px;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        background: #fff
    }

    /* --- Sidebar esquerda --- */
    .flow-sidebar {
        background: #f8fafc;
        border-right: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        overflow: hidden
    }

    .sidebar-header {
        padding: 12px 14px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 8px;
        background: #fff
    }

    .sidebar-header h3 {
        margin: 0;
        font-size: 13px;
        color: #1e293b;
        font-weight: 600;
        flex: 1
    }

    .sidebar-search {
        padding: 8px 12px;
        border-bottom: 1px solid #e2e8f0
    }

    .sidebar-search input {
        width: 100%;
        padding: 7px 10px 7px 30px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 12px;
        outline: none;
        background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'/%3E%3C/svg%3E") 8px center no-repeat;
        transition: border-color .2s;
        box-sizing: border-box
    }

    .sidebar-search input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, .1)
    }

    .sidebar-list {
        flex: 1;
        overflow-y: auto;
        padding: 6px
    }

    .sidebar-node {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        border-radius: 8px;
        cursor: pointer;
        transition: background .15s;
        margin-bottom: 1px;
        font-size: 12px
    }

    .sidebar-node:hover {
        background: #e2e8f0
    }

    .sidebar-node.selected {
        background: #dbeafe;
        border: 1px solid #93c5fd
    }

    .sidebar-node.inactive {
        opacity: .35;
        text-decoration: line-through
    }

    .sidebar-node .sn-icon {
        width: 22px;
        height: 22px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        color: #fff;
        flex-shrink: 0
    }

    .sidebar-node .sn-name {
        flex: 1;
        color: #334155;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: 500
    }

    .sidebar-node .sn-badge {
        font-size: 9px;
        padding: 1px 6px;
        border-radius: 100px;
        background: #e2e8f0;
        color: #64748b;
        white-space: nowrap
    }

    .sidebar-node .sn-indent {
        flex-shrink: 0
    }

    /* --- Toolbar canvas --- */
    .canvas-toolbar {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-bottom: 1px solid #e2e8f0;
        background: #fff;
        flex-wrap: wrap
    }

    .btn-cv {
        padding: 7px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        cursor: pointer;
        font-size: 12px;
        display: flex;
        align-items: center;
        gap: 5px;
        color: #475569;
        transition: all .2s;
        white-space: nowrap;
        font-weight: 500
    }

    .btn-cv:hover {
        background: #f1f5f9;
        border-color: #3b82f6;
        color: #2563eb
    }

    .btn-cv.primary {
        background: linear-gradient(135deg, #1e3a5f, #2d5a87);
        color: #fff;
        border-color: transparent
    }

    .btn-cv.primary:hover {
        box-shadow: 0 4px 12px rgba(30, 58, 95, .3);
        transform: translateY(-1px)
    }

    .btn-cv.danger {
        color: #dc2626;
        border-color: #fecaca
    }

    .btn-cv.danger:hover {
        background: #fef2f2;
        border-color: #dc2626
    }

    .toolbar-sep {
        width: 1px;
        height: 24px;
        background: #e2e8f0;
        flex-shrink: 0
    }

    .toolbar-zoom {
        display: flex;
        align-items: center;
        gap: 2px;
        margin-left: auto
    }

    .toolbar-zoom button {
        width: 28px;
        height: 28px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        background: #fff;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b
    }

    .toolbar-zoom button:hover {
        background: #f1f5f9;
        border-color: #3b82f6;
        color: #2563eb
    }

    .toolbar-zoom span {
        font-size: 11px;
        color: #64748b;
        min-width: 40px;
        text-align: center
    }

    /* --- Canvas Drawflow --- */
    .canvas-area {
        flex: 1;
        position: relative;
        overflow: hidden
    }

    .canvas-wrapper {
        display: flex;
        flex-direction: column;
        height: 100%
    }

    /* Override estilos Drawflow para padrão SIMP */
    #drawflowCanvas {
        height: 100%;
        width: 100%;
        background-color: #f8fafc;
        background-image: radial-gradient(circle, #d1d5db 1px, transparent 1px);
        background-size: 24px 24px
    }

    /* Nó customizado Drawflow */
    .drawflow .drawflow-node {
        background: #fff;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        min-width: 200px;
        max-width: 260px;
        padding: 0;
        font-family: 'Inter', sans-serif;
        box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
        transition: border-color .2s, box-shadow .2s
    }

    .drawflow .drawflow-node:hover {
        border-color: #3b82f6;
        box-shadow: 0 4px 16px rgba(59, 130, 246, .15)
    }

    .drawflow .drawflow-node.selected {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .2)
    }

    .drawflow .drawflow-node .drawflow_content_node {
        padding: 0;
        display: block
    }

    /* Cabeçalho do nó (colorido) */
    .df-node-head {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 10px 10px 0 0;
        color: #fff;
        font-size: 13px;
        font-weight: 600;
        overflow: hidden
    }

    .df-node-head ion-icon {
        font-size: 16px;
        flex-shrink: 0;
        opacity: .85
    }

    .df-node-head span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap
    }

    /* Corpo do nó */
    .df-node-body {
        padding: 8px 12px 10px
    }

    /* Linha de informação */
    .df-node-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
        padding: 3px 0;
        border-bottom: 1px solid #f1f5f9
    }

    .df-node-row:last-child {
        border-bottom: none
    }

    .df-label {
        font-size: 10px;
        color: #94a3b8;
        font-weight: 500;
        flex-shrink: 0
    }

    .df-value {
        font-size: 11px;
        color: #334155;
        font-weight: 500;
        text-align: right;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap
    }

    .df-tag {
        font-size: 9px;
        color: #fff;
        padding: 2px 8px;
        border-radius: 100px;
        font-weight: 600;
        letter-spacing: .3px
    }

    /* Bloco ponto de medição */
    .df-node-ponto {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 8px;
        padding: 6px 8px;
        margin: 6px 0 2px
    }

    .df-ponto-head {
        font-size: 9px;
        font-weight: 600;
        color: #15803d;
        display: flex;
        align-items: center;
        gap: 4px;
        margin-bottom: 3px
    }

    .df-ponto-head ion-icon {
        font-size: 11px
    }

    .df-ponto-codigo {
        font-size: 11px;
        font-weight: 700;
        color: #166534;
        font-family: 'Courier New', monospace;
        letter-spacing: .5px
    }

    .df-ponto-nome {
        font-size: 10px;
        color: #4ade80;
        margin-top: 1px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap
    }

    /* Bloco Sistema de Água no nó */
    .df-node-sistema {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        padding: 6px 8px;
        margin: 6px 0 2px
    }

    .df-sistema-head {
        font-size: 9px;
        font-weight: 600;
        color: #1e40af;
        display: flex;
        align-items: center;
        gap: 4px;
        margin-bottom: 3px
    }

    .df-sistema-head ion-icon {
        font-size: 11px
    }

    .df-sistema-nome {
        font-size: 10px;
        font-weight: 700;
        color: #1e3a8a
    }

    /* Badge inativo */
    .df-node-inactive {
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 6px;
        padding: 4px 8px;
        margin-top: 6px;
        font-size: 10px;
        color: #dc2626;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 4px;
        justify-content: center
    }

    .df-node-inactive ion-icon {
        font-size: 13px
    }

    /* Nó inativo: opacidade */
    .drawflow .drawflow-node.inactive {
        opacity: .5
    }

    .drawflow .drawflow-node.inactive:hover {
        opacity: .75
    }

    /* Portas de conexão Drawflow */
    .drawflow .drawflow-node .input,
    .drawflow .drawflow-node .output {
        width: 14px;
        height: 14px;
        border: 2px solid #94a3b8;
        background: #fff;
        border-radius: 50%;
        transition: all .2s
    }

    .drawflow .drawflow-node .input:hover,
    .drawflow .drawflow-node .output:hover {
        border-color: #2563eb;
        background: #dbeafe;
        transform: scale(1.3)
    }

    .drawflow .drawflow-node .output {
        border-color: #3b82f6;
        background: #eff6ff
    }

    /* Linhas de conexão */
    .drawflow .connection .main-path {
        stroke: #93c5fd;
        stroke-width: 2.5;
        fill: none
    }

    .drawflow .connection .main-path:hover {
        stroke: #2563eb;
        stroke-width: 3.5
    }

    /* --- Painel editor lateral (dentro do canvas) --- */
    .node-editor {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 320px;
        max-height: calc(100% - 20px);
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, .12);
        z-index: 100;
        overflow-y: auto;
        display: none
    }

    .node-editor.visible {
        display: block;
        animation: slideIn .2s ease
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(10px)
        }

        to {
            opacity: 1;
            transform: translateX(0)
        }
    }

    .ne-header {
        padding: 12px 14px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f8fafc;
        border-radius: 12px 12px 0 0
    }

    .ne-header h4 {
        margin: 0;
        font-size: 13px;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 600
    }

    .ne-close {
        width: 28px;
        height: 28px;
        border: none;
        background: transparent;
        cursor: pointer;
        font-size: 16px;
        color: #94a3b8;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center
    }

    .ne-close:hover {
        background: #e2e8f0;
        color: #334155
    }

    .ne-body {
        padding: 14px
    }

    .ne-body .fg {
        margin-bottom: 12px
    }

    .ne-body .fg label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 3px
    }

    .ne-body .fg input,
    .ne-body .fg select,
    .ne-body .fg textarea {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 12px;
        outline: none;
        transition: border-color .2s;
        box-sizing: border-box;
        color: #334155
    }

    .ne-body .fg input:focus,
    .ne-body .fg select:focus,
    .ne-body .fg textarea:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, .1)
    }

    .ne-body .fr {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px
    }

    .ne-footer {
        padding: 10px 14px;
        border-top: 1px solid #e2e8f0;
        display: flex;
        gap: 6px;
        justify-content: flex-end
    }

    .ne-footer button {
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
        transition: all .2s;
        font-weight: 500;
        border: 1px solid #e2e8f0
    }

    .ne-footer .btn-save {
        background: linear-gradient(135deg, #1e3a5f, #2d5a87);
        color: #fff;
        border-color: transparent
    }

    .ne-footer .btn-save:hover {
        box-shadow: 0 4px 12px rgba(30, 58, 95, .3)
    }

    .ne-footer .btn-cancel {
        background: #fff;
        color: #64748b
    }

    .ne-footer .btn-cancel:hover {
        background: #f1f5f9
    }

    .ponto-box {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 8px;
        padding: 10px;
        margin-top: 4px
    }

    .ponto-box legend {
        font-size: 10px;
        font-weight: 600;
        color: #15803d;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 4px
    }

    /* --- Modal padrão SIMP --- */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, .5);
        z-index: 10000;
        align-items: center;
        justify-content: center
    }

    .modal-overlay.active {
        display: flex
    }

    .modal-content {
        background: #fff;
        border-radius: 16px;
        width: 90%;
        max-width: 600px;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .2)
    }

    .modal-header {
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: linear-gradient(135deg, #1e3a5f, #2d5a87);
        color: #fff;
        border-radius: 16px 16px 0 0
    }

    .modal-header h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 600
    }

    .modal-close {
        width: 32px;
        height: 32px;
        border: none;
        background: rgba(255, 255, 255, .15);
        cursor: pointer;
        font-size: 18px;
        color: #fff;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center
    }

    .modal-close:hover {
        background: rgba(255, 255, 255, .25)
    }

    .modal-body {
        padding: 16px 20px
    }

    .nivel-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        margin-bottom: 8px;
        background: #fff;
        transition: all .2s
    }

    .nivel-item:hover {
        background: #f8fafc
    }

    .nivel-item .ni-icon {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 16px;
        flex-shrink: 0
    }

    .nivel-item .ni-info {
        flex: 1
    }

    .nivel-item .ni-info strong {
        font-size: 13px;
        color: #1e293b
    }

    .nivel-item .ni-info small {
        display: block;
        color: #64748b;
        font-size: 11px;
        margin-top: 2px
    }

    .nivel-form {
        border: 2px dashed #e2e8f0;
        border-radius: 10px;
        padding: 14px;
        margin-top: 14px
    }

    .nivel-form .nf-row {
        display: grid;
        grid-template-columns: 1fr 100px 70px;
        gap: 8px;
        align-items: end
    }

    .nivel-form input,
    .nivel-form select {
        padding: 8px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        color: #334155
    }

    /* Wizard */
    .wizard-templates {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px
    }

    .wizard-card {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        cursor: pointer;
        transition: all .2s;
        background: #fff
    }

    .wizard-card:hover {
        border-color: #3b82f6;
        box-shadow: 0 4px 14px rgba(59, 130, 246, .12);
        transform: translateY(-2px)
    }

    .wizard-card.selected {
        border-color: #2563eb;
        background: #eff6ff
    }

    .wizard-card-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: #fff;
        margin-bottom: 8px
    }

    .wizard-card h4 {
        margin: 0 0 4px;
        font-size: 13px;
        color: #1e293b;
        font-weight: 600
    }

    .wizard-card p {
        margin: 0;
        font-size: 10px;
        color: #64748b;
        line-height: 1.4
    }

    .wizard-card .wizard-preview {
        margin-top: 8px;
        padding: 6px;
        background: #f8fafc;
        border-radius: 6px;
        font-size: 9px;
        color: #475569;
        font-family: monospace;
        line-height: 1.5;
        white-space: pre
    }

    .wizard-footer {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        padding: 14px 20px;
        border-top: 1px solid #e2e8f0
    }

    .wizard-footer button {
        padding: 9px 20px;
        border-radius: 8px;
        font-size: 13px;
        cursor: pointer;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all .2s
    }

    .wizard-footer .btn-wiz-ok {
        background: linear-gradient(135deg, #1e3a5f, #2d5a87);
        color: #fff;
        border: none
    }

    .wizard-footer .btn-wiz-cancel {
        background: #fff;
        color: #64748b;
        border: 1px solid #e2e8f0
    }

    /* --- Toast --- */
    .toast-box {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 99999
    }

    .toast-msg {
        padding: 12px 20px;
        border-radius: 10px;
        margin-bottom: 8px;
        font-size: 13px;
        color: #fff;
        box-shadow: 0 4px 14px rgba(0, 0, 0, .15);
        animation: tIn .3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 500
    }

    .toast-msg.ok {
        background: #059669
    }

    .toast-msg.err {
        background: #dc2626
    }

    .toast-msg.inf {
        background: #2563eb
    }

    @keyframes tIn {
        from {
            opacity: 0;
            transform: translateY(-8px)
        }

        to {
            opacity: 1;
            transform: translateY(0)
        }
    }

    /* --- Select2 --- */
    .select2-container {
        width: 100% !important
    }

    .select2-container--default .select2-selection--single {
        height: 34px !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 32px !important;
        font-size: 12px;
        color: #334155
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 32px !important
    }

    .select2-container--default .select2-selection--multiple {
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        min-height: 34px !important;
        font-size: 12px
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background: #eff6ff !important;
        border: 1px solid #bfdbfe !important;
        border-radius: 6px !important;
        font-size: 11px;
        color: #1e40af;
        padding: 2px 6px
    }

    /* --- Toolbar seletor sistema (Select2) --- */
    .toolbar-sistema {
        min-width: 260px
    }

    .toolbar-sistema .select2-container {
        width: 100% !important
    }

    .toolbar-sistema .select2-container--default .select2-selection--single {
        height: 36px !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        background: #fff !important
    }

    .toolbar-sistema .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 34px !important;
        font-size: 12px;
        color: #334155;
        padding-left: 12px
    }

    .toolbar-sistema .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 34px !important
    }

    /* Dropdown aberto */
    .select2-results__option {
        font-size: 13px;
        padding: 8px 12px;
        color: #334155
    }

    .select2-results__option--highlighted {
        background: #1e3a5f !important;
        color: #fff !important
    }

    .select2-results__option .sis-opt-nome {
        font-weight: 600;
        display: block;
        line-height: 1.3
    }

    .select2-results__option .sis-opt-qtd {
        font-size: 11px;
        color: #94a3b8;
        font-weight: 400
    }

    .select2-results__option--highlighted .sis-opt-qtd {
        color: #cbd5e1
    }

    .select2-search--dropdown .select2-search__field {
        padding: 8px 12px !important;
        font-size: 13px !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 6px !important
    }

    /* --- Sidebar filtros --- */
    .sidebar-filters {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 4px;
        padding: 6px 12px;
        border-bottom: 1px solid #e2e8f0
    }

    .sidebar-filters select {
        padding: 5px 6px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 10px;
        color: #475569;
        background: #fff;
        cursor: pointer;
        outline: none
    }

    .sidebar-filters select:focus {
        border-color: #3b82f6
    }

    /* Nó compartilhado: destaque visual quando recebe conexão de outro sistema */
    .drawflow .drawflow-node.shared-node {
        border-style: dashed;
        border-color: #f59e0b
    }

    /* --- Responsivo --- */
    @media(max-width:1024px) {
        .flow-layout {
            grid-template-columns: 1fr;
            height: auto
        }

        .flow-sidebar {
            max-height: 200px
        }

        .node-editor {
            position: static;
            width: 100%;
            max-height: none;
            border-radius: 0;
            border: none;
            border-top: 1px solid #e2e8f0
        }
    }

    @media(max-width:768px) {
        .page-container {
            padding: 14px
        }

        .page-header {
            padding: 18px
        }

        .stats-grid {
            grid-template-columns: 1fr 1fr
        }

        .canvas-toolbar {
            flex-direction: column;
            align-items: stretch
        }

        .toolbar-zoom {
            margin-left: 0;
            justify-content: center
        }
    }

    @media(max-width:480px) {
        .stats-grid {
            grid-template-columns: 1fr
        }

        .page-header h1 {
            font-size: 17px
        }
    }

    /* --- Modo Maximizado (fullscreen do flowchart) --- */
    body.flowchart-maximizado {
        padding: 0 !important;
        overflow: hidden;
    }

    body.flowchart-maximizado .modern-header {
        display: none !important;
    }

    body.flowchart-maximizado #modernSidebar {
        display: none !important;
    }

    body.flowchart-maximizado .page-container {
        padding: 0;
        max-width: none;
        margin: 0;
    }

    body.flowchart-maximizado .page-header {
        display: none !important;
    }

    body.flowchart-maximizado .stats-grid {
        display: none !important;
    }

    body.flowchart-maximizado .flow-layout {
        height: 100vh !important;
        border-radius: 0;
        border: none;
    }

    /* Botão maximizar */
    .btn-cv.maximizar {
        color: #64748b;
        border-color: #e2e8f0;
    }

    .btn-cv.maximizar:hover {
        color: #2563eb;
        border-color: #3b82f6;
        background: #eff6ff;
    }

    body.flowchart-maximizado .btn-cv.maximizar {
        color: #ef4444;
        border-color: #fecaca;
        background: #fef2f2;
    }

    body.flowchart-maximizado .btn-cv.maximizar:hover {
        color: #dc2626;
        border-color: #ef4444;
        background: #fee2e2;
    }
</style>

<!-- ============================================
     HTML
     ============================================ -->
<div class="page-container">

    <!-- Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-icon"><ion-icon name="git-network-outline"></ion-icon></div>
            <div>
                <h1>Flowchart</h1>
                <p class="page-header-subtitle">Fluxo físico da água — arraste para posicionar, conecte para definir o
                    caminho</p>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon nos"><ion-icon name="git-network-outline"></ion-icon></div>
            <div class="stat-card-info">
                <h3 id="stNos">0</h3>
                <p>Total de Nós</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon raizes"><ion-icon name="git-branch-outline"></ion-icon></div>
            <div class="stat-card-info">
                <h3 id="stRaizes">0</h3>
                <p>Nós Raiz</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon pontos"><ion-icon name="speedometer-outline"></ion-icon></div>
            <div class="stat-card-info">
                <h3 id="stPontos">0</h3>
                <p>Com Ponto Medição</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon conexoes"><ion-icon name="swap-horizontal-outline"></ion-icon></div>
            <div class="stat-card-info">
                <h3 id="stConexoes">0</h3>
                <p>Conexões Fluxo</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon niveis"><ion-icon name="layers-outline"></ion-icon></div>
            <div class="stat-card-info">
                <h3 id="stNiveis">0</h3>
                <p>Níveis Cadastrados</p>
            </div>
        </div>
    </div>

    <!-- Layout principal -->
    <div class="flow-layout">

        <!-- Sidebar: lista read-only -->
        <div class="flow-sidebar">
            <div class="sidebar-header">
                <ion-icon name="list-outline" style="font-size:16px;color:#64748b;"></ion-icon>
                <h3>Navegação</h3>
                <button class="btn-cv" onclick="abrirModalNiveis()" title="Níveis"
                    style="padding:4px 8px;font-size:11px;"><ion-icon name="settings-outline"></ion-icon></button>
            </div>
            <div class="sidebar-search">
                <input type="text" id="sidebarBusca" placeholder="Buscar nó...">
            </div>
            <div class="sidebar-filters" id="sidebarFilters">
                <select id="filtNivel" onchange="filtrarSidebar()" title="Filtrar por nível">
                    <option value="">Todos os níveis</option>
                </select>
                <select id="filtStatus" onchange="filtrarSidebar()">
                    <option value="">Todos</option>
                    <option value="1">Ativos</option>
                    <option value="0">Inativos</option>
                </select>
                <select id="filtPonto" onchange="filtrarSidebar()">
                    <option value="">Todos</option>
                    <option value="1">Com PM</option>
                    <option value="0">Sem PM</option>
                </select>
            </div>
            <div class="sidebar-list" id="sidebarList">
                <p style="text-align:center;color:#94a3b8;font-size:12px;padding:20px;">Carregando...</p>
            </div>
        </div>

        <!-- Área do canvas -->
        <div class="canvas-wrapper">
            <!-- Toolbar -->
            <div class="canvas-toolbar">
                <div class="toolbar-sistema">
                    <select id="selSistema">
                        <option value="">— Todos os Sistemas —</option>
                    </select>
                </div>
                <?php if ($podeEditar): ?>
                    <button class="btn-cv primary" onclick="abrirModalCriarNo()"><ion-icon name="add-outline"></ion-icon>
                        Novo Nó</button>
                    <button class="btn-cv" onclick="abrirWizard()"><ion-icon name="flash-outline"></ion-icon>
                        Wizard</button>
                    <span class="toolbar-sep"></span>
                    <button class="btn-cv danger" onclick="excluirSelecionado()" id="btnExcluir"
                        style="display:none;"><ion-icon name="trash-outline"></ion-icon> Excluir</button>
                    <button class="btn-cv" onclick="restaurarSelecionado()" id="btnRestaurar"
                        style="display:none;"><ion-icon name="refresh-outline"></ion-icon> Restaurar</button>
                <?php endif; ?>
                <button class="btn-cv maximizar" onclick="toggleMaximizarFlowchart()" id="btnMaximizar"
                    title="Maximizar/Restaurar">
                    <ion-icon name="expand-outline" id="iconMaximizar"></ion-icon>
                </button>
                <div class="toolbar-zoom">
                    <button onclick="zoomCanvas(1)" title="Zoom +"><ion-icon name="add-outline"></ion-icon></button>
                    <span id="zoomLabel">100%</span>
                    <button onclick="zoomCanvas(-1)" title="Zoom −"><ion-icon name="remove-outline"></ion-icon></button>
                    <button onclick="zoomCanvas(0)" title="Resetar"><ion-icon name="scan-outline"></ion-icon></button>
                </div>
            </div>

            <!-- Canvas Drawflow -->
            <div class="canvas-area">
                <div id="drawflowCanvas"></div>

                <!-- Painel editor do nó selecionado -->
                <div class="node-editor" id="nodeEditor">
                    <div class="ne-header">
                        <h4><ion-icon name="create-outline"></ion-icon> <span id="neTitle">Editar Nó</span></h4>
                        <button class="ne-close" onclick="fecharEditor()"><ion-icon
                                name="close-outline"></ion-icon></button>
                    </div>
                    <div class="ne-body">
                        <input type="hidden" id="neCd">
                        <div class="fg"><label>Nível *</label><select id="neNivel"></select></div>
                        <div class="fg"><label>Nome *</label><input type="text" id="neNome" maxlength="200"
                                placeholder="Nome do nó"></div>
                        <div class="fr">
                            <div class="fg"><label>Fluxo</label><select id="neFluxo">
                                    <option value="">— Nenhum —</option>
                                    <option value="1">Entrada</option>
                                    <option value="2">Saída</option>
                                    <option value="3">Municipal</option>
                                    <option value="4">N/A</option>
                                </select></div>
                            <div class="fg"><label>Operação</label><select id="neOp">
                                    <option value="">— Nenhuma —</option>
                                    <option value="1">Soma (+)</option>
                                    <option value="2">Subtração (−)</option>
                                </select></div>
                        </div>
                        <div class="ponto-box" id="nePontoBox" style="display:none;">
                            <legend><ion-icon name="speedometer-outline"></ion-icon> Ponto de Medição</legend>
                            <div class="fg"><label>Ponto</label><select id="nePonto" style="width:100%;"></select></div>
                        </div>
                        <div class="ponto-box" id="neSistemaAguaBox" style="display:none;">
                            <legend><ion-icon name="git-network-outline"></ion-icon> Sistema de Água</legend>
                            <div class="fg"><label>Sistema</label><select id="neSistemaAgua"
                                    style="width:100%;"></select></div>
                        </div>
                        <div class="fg"><label>Observação</label><textarea id="neObs" rows="2" maxlength="500"
                                placeholder="Opcional"></textarea></div>
                    </div>
                    <?php if ($podeEditar): ?>
                        <div class="ne-footer">
                            <button class="btn-cancel" onclick="fecharEditor()"><ion-icon name="close-outline"></ion-icon>
                                Fechar</button>
                            <button class="btn-save" onclick="salvarNoEditor()"><ion-icon
                                    name="checkmark-outline"></ion-icon> Salvar</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CRIAR NÓ (rápido) -->
<div class="modal-overlay" id="modalCriarNo">
    <div class="modal-content" style="max-width:420px;">
        <div class="modal-header">
            <h3><ion-icon name="add-circle-outline"></ion-icon> Novo Nó</h3>
            <button class="modal-close" onclick="fecharModalCriarNo()"><ion-icon
                    name="close-outline"></ion-icon></button>
        </div>
        <div class="modal-body">
            <div class="fg"><label style="font-size:12px;font-weight:600;color:#475569;">Nível *</label><select
                    id="mcNivel"
                    style="width:100%;padding:9px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;"></select>
            </div>
            <div class="fg" style="margin-top:12px;"><label style="font-size:12px;font-weight:600;color:#475569;">Nome
                    *</label><input type="text" id="mcNome" maxlength="200" placeholder="Nome do nó"
                    style="width:100%;padding:9px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;">
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
                <button class="btn-cv" onclick="fecharModalCriarNo()">Cancelar</button>
                <button class="btn-cv primary" onclick="criarNoRapido()"><ion-icon name="checkmark-outline"></ion-icon>
                    Criar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL NÍVEIS -->
<div class="modal-overlay" id="modalNiveis">
    <div class="modal-content" style="max-width:550px;">
        <div class="modal-header">
            <h3><ion-icon name="layers-outline"></ion-icon> Configurar Níveis</h3><button class="modal-close"
                onclick="fecharModalNiveis()"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <div class="modal-body">
            <div id="listaNiveis"></div>
            <?php if ($podeEditar): ?>
                <div class="nivel-form" id="nivelForm">
                    <h4 id="nvFormTitle"
                        style="margin:0 0 10px;font-size:13px;color:#475569;display:flex;align-items:center;gap:6px;">
                        <ion-icon name="add-circle-outline"></ion-icon> <span>Novo Nível</span>
                    </h4>
                    <input type="hidden" id="nvCd">
                    <div style="display:grid;grid-template-columns:1fr 80px;gap:8px;margin-bottom:8px;">
                        <input type="text" id="nvNome" placeholder="Nome do nível" maxlength="100"
                            style="padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;">
                        <input type="color" id="nvCor" value="#607D8B"
                            style="height:38px;cursor:pointer;border:1px solid #e2e8f0;border-radius:8px;">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;">
                        <select id="nvIcone" style="padding:8px;border:1px solid #e2e8f0;border-radius:8px;font-size:12px;">
                            <option value="ellipse-outline">● Padrão</option>
                            <option value="water-outline">💧 Água</option>
                            <option value="flask-outline">🧪 ETA</option>
                            <option value="cube-outline">📦 Reservatório</option>
                            <option value="speedometer-outline">⏱ Medidor</option>
                            <option value="location-outline">📍 Local</option>
                            <option value="business-outline">🏢 Unidade</option>
                            <option value="git-branch-outline">🔀 Ramificação</option>
                            <option value="pulse-outline">📊 Pressão</option>
                            <option value="thermometer-outline">🌡 Temperatura</option>
                            <option value="git-network-outline">🌐 Sistema</option>
                        </select>
                        <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;">
                            <input type="checkbox" id="nvPonto" style="accent-color:#2563eb;width:16px;height:16px;">
                            Permite Ponto
                        </label>
                    </div>
                    <div style="margin-bottom:10px;">
                        <label
                            style="display:flex;align-items:center;gap:8px;font-size:12px;cursor:pointer;padding:8px 12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;">
                            <input type="checkbox" id="nvSistema" style="accent-color:#2563eb;width:16px;height:16px;">
                            <div>
                                <strong style="color:#1e40af;">É Sistema de Abastecimento</strong>
                                <div style="font-size:10px;color:#64748b;margin-top:2px;">Nós deste nível aparecem no filtro
                                    de sistemas</div>
                            </div>
                        </label>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button class="btn-cv primary" onclick="salvarNivel()"
                            style="flex:1;justify-content:center;"><ion-icon name="checkmark-outline"></ion-icon>
                            Salvar</button>
                        <button class="btn-cv" onclick="limparFormNivel()" id="nvBtnCancelar"
                            style="display:none;"><ion-icon name="close-outline"></ion-icon> Cancelar</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL WIZARD -->
<div class="modal-overlay" id="modalWizard">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header">
            <h3><ion-icon name="flash-outline"></ion-icon> Wizard — Estrutura Pré-Pronta</h3><button class="modal-close"
                onclick="fecharWizard()"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <div class="modal-body">
            <p style="font-size:12px;color:#64748b;margin:0 0 14px;">Escolha um template para criar automaticamente:</p>
            <div class="wizard-templates" id="wizardTemplates"></div>
        </div>
        <div class="wizard-footer">
            <button class="btn-wiz-cancel" onclick="fecharWizard()">Cancelar</button>
            <button class="btn-wiz-ok" onclick="executarWizard()"><ion-icon name="flash-outline"></ion-icon>
                Criar</button>
        </div>
    </div>
</div>

<div class="toast-box" id="toastBox"></div>

<!-- ============================================
     Scripts
     ============================================ -->
<script src="https://cdn.jsdelivr.net/gh/jerosoler/Drawflow@0.0.59/dist/drawflow.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    /**
     * SIMP — Cadastro em Cascata v3 (Flowchart)
     * Canvas Drawflow + Sidebar read-only + CRUD backend
     */

    /* ============================================
       Variáveis globais
       ============================================ */
    var editor = null;                // Instância Drawflow
    var flat = [];                    // Todos os nós (array do backend)
    var niveis = [];                  // Níveis cadastrados
    var conexoes = [];                // Conexões do backend
    var sistemaAtual = '';            // CD_CHAVE do nó raiz selecionado como sistema ('' = todos)
    var podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;
    var pontosJson = <?= json_encode($pontosMedicao, JSON_UNESCAPED_UNICODE) ?>;
    var sistemasAguaJson = <?= json_encode($sistemasAgua, JSON_UNESCAPED_UNICODE) ?>;
    var dfToSimp = {};                // Drawflow ID → SIMP CD_CHAVE
    var simpToDf = {};                // SIMP CD_CHAVE → Drawflow ID
    var noSelecionadoCd = null;       // CD_CHAVE do nó selecionado no editor
    var sincronizando = false;        // Flag para evitar loops de eventos
    var posDebounce = {};             // Debounce para salvar posição

    /* ============================================
       Inicialização
       ============================================ */
    document.addEventListener('DOMContentLoaded', function () {
        inicializarDrawflow();
        inicializarBuscaSidebar();
        inicializarSelectPonto();
        inicializarSelectSistemaAgua();
        carregarNiveis(function () {
            carregarDados();
        });
    });

    /** Cria instância Drawflow e registra eventos */
    function inicializarDrawflow() {
        var container = document.getElementById('drawflowCanvas');
        editor = new Drawflow(container);
        editor.reroute = true;        // Setas curvadas
        editor.reroute_fix_curvature = true;
        editor.force_first_input = false;

        if (!podeEditar) {
            editor.editor_mode = 'view'; // Somente leitura
        }

        editor.start();
        editor.zoom_max = 2;      // Limite máximo 200%
        editor.zoom_min = 0.2;    // Limite mínimo 20%
        editor.zoom_value = 0.04; // Passo menor para zoom suave

        /* --- Interceptar remoção nativa (botão X do Drawflow) ---
           O Drawflow remove o nó só visualmente. Precisamos:
           1. Confirmar com o usuário (informando descendentes)
           2. Chamar soft delete no backend
           3. Só então remover do canvas (ou recarregar tudo)
        */
        var _originalRemoveNode = editor.removeNodeId.bind(editor);
        editor.removeNodeId = function (idStr) {
            if (!podeEditar) return;
            var numId = parseInt(idStr.replace('node-', ''));
            var cd = dfToSimp[numId];
            if (!cd) { _originalRemoveNode(idStr); return; }

            var no = flat.find(function (n) { return n.CD_CHAVE == cd; });
            if (!no) { _originalRemoveNode(idStr); return; }

            /* Contar descendentes via conexões do grafo */
            var descIds = obterDescendentes(cd);
            descIds.splice(descIds.indexOf(parseInt(cd)), 1); /* remover o próprio nó */

            var msg = 'Desativar "' + no.DS_NOME + '"?';
            if (descIds.length > 0) {
                msg += '\n\n⚠ Este nó possui ' + descIds.length + ' nó(s) conectado(s).';
            }

            if (!confirm(msg)) return; /* Cancelou: não remove nada */

            /* Chamar backend (soft delete) */
            var fd = new FormData();
            fd.append('cd', cd);
            fd.append('modo', 'soft');
            fetch('bd/entidadeCascata/excluirNodo.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.success) {
                        toast(d.message, 'ok');
                        fecharEditor();
                        carregarDados(); /* Recarrega tudo do banco */
                    } else {
                        toast(d.message, 'err');
                    }
                }).catch(function () { toast('Erro ao excluir', 'err'); });
        };

        /* --- Eventos --- */

        /* Nó selecionado: abrir editor lateral */
        editor.on('nodeSelected', function (dfId) {
            var cd = dfToSimp[dfId];
            if (cd) abrirEditor(cd);
            atualizarBotoesToolbar(cd);
        });

        /* Nó deselecionado: fechar editor */
        editor.on('nodeUnselected', function () {
            fecharEditor();
            atualizarBotoesToolbar(null);
        });

        /* Nó movido: salvar posição com debounce */
        editor.on('nodeMoved', function (dfId) {
            if (sincronizando) return;
            var cd = dfToSimp[dfId];
            if (!cd) return;
            var info = editor.getNodeFromId(dfId);
            /* Debounce 500ms por nó */
            clearTimeout(posDebounce[cd]);
            posDebounce[cd] = setTimeout(function () {
                salvarPosicao(cd, Math.round(info.pos_x), Math.round(info.pos_y));
            }, 500);
        });

        /* Conexão criada: salvar no backend */
        editor.on('connectionCreated', function (info) {
            if (sincronizando) return;
            var cdOrigem = dfToSimp[info.output_id];
            var cdDestino = dfToSimp[info.input_id];
            if (!cdOrigem || !cdDestino) return;
            salvarConexaoBackend(cdOrigem, cdDestino);
        });

        /* Conexão removida: excluir no backend */
        editor.on('connectionRemoved', function (info) {
            if (sincronizando) return;
            var cdOrigem = dfToSimp[info.output_id];
            var cdDestino = dfToSimp[info.input_id];
            if (!cdOrigem || !cdDestino) return;
            excluirConexaoBackend(cdOrigem, cdDestino);
        });

        /* Duplo clique no canvas vazio: criar nó */
        container.addEventListener('dblclick', function (ev) {
            if (!podeEditar) return;
            /* Só se clicou no fundo (não em um nó) */
            if (ev.target.closest('.drawflow-node')) return;
            abrirModalCriarNo();
        });
    }

    /* ============================================
       Carregamento de dados
       ============================================ */
    function carregarNiveis(cb) {
        fetch('bd/entidadeCascata/listarNiveis.php').then(function (r) { return r.json() }).then(function (d) {
            if (d.success) {
                niveis = d.niveis;
                popularSelectsNivel();
                document.getElementById('stNiveis').textContent = niveis.length;
                if (cb) cb();
            } else toast(d.message, 'err');
        }).catch(function () { toast('Erro ao carregar níveis', 'err') });
    }

    /** Carregar dropdown de Sistemas com Select2 + contagem dinâmica */
    function popularDropdownSistemas() {
        var sel = document.getElementById('selSistema');
        var valAtual = $(sel).val();

        /* Destruir Select2 anterior se existir */
        if ($(sel).hasClass('select2-hidden-accessible')) {
            $(sel).select2('destroy');
        }

        sel.innerHTML = '<option value="">— Todos os Sistemas —</option>';

        /* Filtrar nós cujo nível é marcado como sistema */
        var nosSistema = flat.filter(function (n) {
            return n.OP_EH_SISTEMA == 1 && n.OP_ATIVO == 1;
        });

        nosSistema.forEach(function (r) {
            var qtdDesc = contarDescendentes(r.CD_CHAVE);
            var o = document.createElement('option');
            o.value = r.CD_CHAVE;
            var label = r.DS_SISTEMA_AGUA ? r.DS_SISTEMA_AGUA : r.DS_NOME;
            o.textContent = label + ' — ' + qtdDesc + ' nó(s)';
            sel.appendChild(o);
        });

        /* Inicializar Select2 com pesquisa e template formatado */
        $(sel).select2({
            placeholder: '— Todos os Sistemas —',
            allowClear: true,
            width: '100%',
            templateResult: formatarOpcaoSistema,
            language: {
                noResults: function () { return 'Nenhum sistema encontrado'; },
                searching: function () { return 'Buscando...'; }
            }
        }).off('select2:select select2:clear select2:open')
            .on('select2:select select2:clear', function () {
                filtrarPorSistema();
            }).on('select2:open', function () {
                /* Autofocus no campo de busca */
                setTimeout(function () { document.querySelector('.select2-search__field').focus(); }, 50);
            });

        /* Restaurar valor anterior se existia */
        if (valAtual) $(sel).val(valAtual).trigger('change.select2');
    }

    /** Atualizar contagem no dropdown sem recriar Select2 */
    function atualizarContagemSistemas() {
        var sel = document.getElementById('selSistema');
        for (var i = 1; i < sel.options.length; i++) {
            var cd = sel.options[i].value;
            var qtd = contarDescendentes(cd);
            var no = flat.find(function (n) { return n.CD_CHAVE == cd; });
            var label = (no && no.DS_SISTEMA_AGUA) ? no.DS_SISTEMA_AGUA : (no ? no.DS_NOME : '');
            sel.options[i].textContent = label + ' — ' + qtd + ' nó(s)';
        }
    }

    /** Formatar opção do Select2 de sistemas (nome + contagem) */
    function formatarOpcaoSistema(opt) {
        if (!opt.id) return opt.text; /* placeholder */
        var parts = opt.text.split(' — ');
        var nome = parts[0] || opt.text;
        var qtd = parts[1] || '';
        var el = document.createElement('div');
        el.innerHTML = '<span class="sis-opt-nome">' + esc(nome) + '</span>' +
            (qtd ? '<span class="sis-opt-qtd">' + esc(qtd) + '</span>' : '');
        return el;
    }

    /** Contar descendentes percorrendo conexões do grafo */
    function contarDescendentes(cdRaiz) {
        return obterDescendentes(cdRaiz).length - 1; // -1 pq inclui ele mesmo
    }

    /** Obter IDs de todos os nós alcançáveis a partir de um nó (via conexões) */
    function obterDescendentes(cdRaiz) {
        var ids = [parseInt(cdRaiz)];
        var fila = [parseInt(cdRaiz)];

        while (fila.length > 0) {
            var atual = fila.shift();

            /* Percorrer conexões (setas no canvas) */
            conexoes.forEach(function (cx) {
                var destino = parseInt(cx.CD_NODO_DESTINO);
                if (parseInt(cx.CD_NODO_ORIGEM) === atual && ids.indexOf(destino) === -1) {
                    ids.push(destino);
                    fila.push(destino);
                }
            });
        }
        return ids;
    }

    /** Filtrar canvas pelo sistema selecionado */
    function filtrarPorSistema() {
        sistemaAtual = $('#selSistema').val() || '';
        renderizarCanvas();
        renderizarSidebar();
        atualizarContagemSistemas();
    }

    function carregarDados() {
        Promise.all([
            fetch('bd/entidadeCascata/listarArvore.php?ativos_only=0').then(function (r) { return r.json() }),
            fetch('bd/entidadeCascata/listarConexoes.php').then(function (r) { return r.json() })
        ]).then(function (results) {
            var dArvore = results[0], dConexoes = results[1];
            if (dArvore.success) {
                flat = dArvore.flat;
                atualizarStats(dArvore.stats);
            }
            if (dConexoes.success) {
                conexoes = dConexoes.conexoes;
                document.getElementById('stConexoes').textContent = dConexoes.total || conexoes.length;
            }
            popularDropdownSistemas();
            renderizarCanvas();
            renderizarSidebar();
        }).catch(function () { toast('Erro ao carregar dados', 'err') });
    }

    function atualizarStats(s) {
        document.getElementById('stNos').textContent = s.totalNos || 0;
        document.getElementById('stRaizes').textContent = s.totalRaizes || 0;
        document.getElementById('stPontos').textContent = s.totalComPonto || 0;
    }

    /* ============================================
       Renderizar Canvas (Drawflow)
       ============================================ */
    function renderizarCanvas() {
        sincronizando = true;
        editor.clear();
        dfToSimp = {};
        simpToDf = {};

        /* Determinar nós visíveis (filtro por nó raiz = sistema) */
        var nosVisiveis = flat;
        if (sistemaAtual) {
            var idsDesc = obterDescendentes(sistemaAtual);
            nosVisiveis = flat.filter(function (no) {
                return idsDesc.indexOf(parseInt(no.CD_CHAVE)) >= 0;
            });
        }

        /* Calcular posição auto para nós sem posição salva */
        var autoX = 80, autoY = 80;

        nosVisiveis.forEach(function (no) {
            var cor = no.DS_COR || '#607D8B';
            var icone = no.DS_ICONE || 'ellipse-outline';
            var posX = no.NR_POS_X !== null ? parseInt(no.NR_POS_X) : autoX;
            var posY = no.NR_POS_Y !== null ? parseInt(no.NR_POS_Y) : autoY;

            if (no.NR_POS_X === null) {
                autoX += 260;
                if (autoX > 900) { autoX = 80; autoY += 180; }
            }

            /* HTML do nó — layout completo com todas as informações */
            var fluxoLabels = { '1': 'Entrada', '2': 'Saída', '3': 'Municipal', '4': 'N/A' };
            var fluxoCores = { '1': '#059669', '2': '#dc2626', '3': '#2563eb', '4': '#64748b' };
            var opLabels = { '1': 'Soma (+)', '2': 'Subtração (−)' };
            var fluxoTxt = no.ID_FLUXO ? fluxoLabels[no.ID_FLUXO] || '' : '';
            var fluxoCor = no.ID_FLUXO ? fluxoCores[no.ID_FLUXO] || '#64748b' : '';
            var opTxt = no.ID_OPERACAO ? opLabels[no.ID_OPERACAO] || '' : '';

            /* Buscar código do ponto de medição no array local */
            var pontoInfo = null;
            if (no.CD_PONTO_MEDICAO) {
                pontoInfo = pontosJson.find(function (p) { return p.cd == no.CD_PONTO_MEDICAO; });
            }

            var html = '<div class="df-node-inner" data-cd="' + no.CD_CHAVE + '">';
            /* Cabeçalho: ícone + nome */
            html += '<div class="df-node-head" style="background:' + esc(cor) + '">';
            html += '<ion-icon name="' + esc(icone) + '"></ion-icon>';
            html += '<span>' + esc(no.DS_NOME) + '</span>';
            html += '</div>';
            /* Corpo: detalhes */
            html += '<div class="df-node-body">';
            html += '<div class="df-node-row"><span class="df-label">Nível</span><span class="df-value">' + esc(no.DS_NIVEL) + '</span></div>';
            if (fluxoTxt) {
                html += '<div class="df-node-row"><span class="df-label">Fluxo</span><span class="df-tag" style="background:' + fluxoCor + '">' + fluxoTxt + '</span></div>';
            }
            if (opTxt) {
                html += '<div class="df-node-row"><span class="df-label">Operação</span><span class="df-value">' + opTxt + '</span></div>';
            }
            if (pontoInfo) {
                html += '<div class="df-node-ponto">';
                html += '<div class="df-ponto-head"><ion-icon name="speedometer-outline"></ion-icon> Ponto de Medição</div>';
                html += '<div class="df-ponto-codigo">' + esc(pontoInfo.codigo) + '</div>';
                html += '<div class="df-ponto-nome">' + esc(pontoInfo.nome) + '</div>';
                html += '</div>';
            }
            /* Sistema de Água vinculado */
            if (no.DS_SISTEMA_AGUA) {
                html += '<div class="df-node-sistema">';
                html += '<div class="df-sistema-head"><ion-icon name="git-network-outline"></ion-icon> Sistema de Água</div>';
                html += '<div class="df-sistema-nome">' + esc(no.DS_SISTEMA_AGUA) + '</div>';
                html += '</div>';
            }
            if (no.DS_IDENTIFICADOR) {
                html += '<div class="df-node-row"><span class="df-label">Tag</span><span class="df-value" style="font-family:monospace;font-size:10px;">' + esc(no.DS_IDENTIFICADOR) + '</span></div>';
            }
            if (no.OP_ATIVO == 0) {
                html += '<div class="df-node-inactive"><ion-icon name="close-circle-outline"></ion-icon> Inativo</div>';
            }
            html += '</div></div>';

            /* Definir classe extra */
            var extraClass = 'simp-node';
            if (no.OP_ATIVO == 0) extraClass += ' inactive';

            /* addNode(name, inputs, outputs, posX, posY, className, data, html) */
            var dfId = editor.addNode(
                'simp_' + no.CD_CHAVE,   // name
                1,                        // num inputs
                1,                        // num outputs
                posX, posY,               // posição
                extraClass,
                { cd: no.CD_CHAVE },      // data
                html                      // HTML
            );

            dfToSimp[dfId] = no.CD_CHAVE;
            simpToDf[no.CD_CHAVE] = dfId;
        });

        /* Criar conexões (só entre nós visíveis) */
        conexoes.forEach(function (cx) {
            var dfOut = simpToDf[cx.CD_NODO_ORIGEM];
            var dfIn = simpToDf[cx.CD_NODO_DESTINO];
            if (dfOut && dfIn) {
                try {
                    editor.addConnection(dfOut, dfIn, 'output_1', 'input_1');
                } catch (e) { /* Conexão duplicada ou inválida, ignorar */ }
            }
        });

        sincronizando = false;
    }

    /* ============================================
       Sidebar read-only (lista navegável)
       ============================================ */
    function renderizarSidebar() {
        var c = document.getElementById('sidebarList');

        /* Popular filtro de nível (uma vez) */
        var filtNivel = document.getElementById('filtNivel');
        if (filtNivel.options.length <= 1) {
            niveis.forEach(function (n) {
                var o = document.createElement('option');
                o.value = n.CD_CHAVE;
                o.textContent = n.DS_NOME;
                filtNivel.appendChild(o);
            });
        }

        /* Filtrar nós */
        var nosVisiveis = flat;
        if (sistemaAtual) {
            var idsDesc = obterDescendentes(sistemaAtual);
            nosVisiveis = nosVisiveis.filter(function (no) {
                return idsDesc.indexOf(parseInt(no.CD_CHAVE)) >= 0;
            });
        }

        if (!nosVisiveis.length) {
            c.innerHTML = '<p style="text-align:center;color:#94a3b8;font-size:12px;padding:20px;">Nenhum nó' + (sistemaAtual ? ' neste sistema' : ' cadastrado') + '.<br>Clique "Novo Nó" para começar.</p>';
            return;
        }
        var h = '';
        nosVisiveis.forEach(function (no) {
            var prof = parseInt(no.NR_PROFUNDIDADE) || 0;
            var cor = no.DS_COR || '#607D8B';
            var icone = no.DS_ICONE || 'ellipse-outline';
            h += '<div class="sidebar-node' + (no.OP_ATIVO == 0 ? ' inactive' : '') + '"';
            h += ' data-cd="' + no.CD_CHAVE + '"';
            h += ' data-nivel="' + no.CD_ENTIDADE_NIVEL + '"';
            h += ' data-ativo="' + no.OP_ATIVO + '"';
            h += ' data-ponto="' + (no.CD_PONTO_MEDICAO ? '1' : '0') + '"';
            h += ' onclick="navParaNo(' + no.CD_CHAVE + ')">';
            if (prof > 0) h += '<span class="sn-indent" style="width:' + (prof * 14) + 'px;"></span>';
            h += '<span class="sn-icon" style="background:' + cor + '"><ion-icon name="' + icone + '"></ion-icon></span>';
            h += '<span class="sn-name">' + esc(no.DS_NOME) + '</span>';
            h += '<span class="sn-badge">' + esc(no.DS_NIVEL) + '</span>';
            h += '</div>';
        });
        c.innerHTML = h;
    }

    /** Filtrar sidebar por nível, status, ponto */
    function filtrarSidebar() {
        var fNivel = document.getElementById('filtNivel').value;
        var fStatus = document.getElementById('filtStatus').value;
        var fPonto = document.getElementById('filtPonto').value;

        document.querySelectorAll('.sidebar-node').forEach(function (el) {
            var show = true;
            if (fNivel && el.dataset.nivel !== fNivel) show = false;
            if (fStatus && el.dataset.ativo !== fStatus) show = false;
            if (fPonto === '1' && el.dataset.ponto !== '1') show = false;
            if (fPonto === '0' && el.dataset.ponto !== '0') show = false;
            el.style.display = show ? '' : 'none';
        });
    }

    /** Clicar na sidebar: centralizar nó no canvas e selecioná-lo */
    function navParaNo(cd) {
        var dfId = simpToDf[cd];
        if (!dfId) return;

        /* Destacar na sidebar */
        document.querySelectorAll('.sidebar-node.selected').forEach(function (e) { e.classList.remove('selected') });
        var el = document.querySelector('.sidebar-node[data-cd="' + cd + '"]');
        if (el) { el.classList.add('selected'); el.scrollIntoView({ block: 'nearest' }); }

        /* Selecionar no Drawflow */
        var nodeInfo = editor.getNodeFromId(dfId);
        if (nodeInfo) {
            /* Centralizar canvas no nó */
            var container = document.getElementById('drawflowCanvas');
            var cW = container.offsetWidth, cH = container.offsetHeight;
            var zoom = editor.zoom;
            var newX = -(nodeInfo.pos_x * zoom) + cW / 2 - 80;
            var newY = -(nodeInfo.pos_y * zoom) + cH / 2 - 40;
            editor.canvas_x = newX;
            editor.canvas_y = newY;
            var precanvas = container.querySelector('.drawflow');
            if (precanvas) {
                precanvas.style.transform = 'translate(' + newX + 'px, ' + newY + 'px) scale(' + zoom + ')';
            }
        }

        /* Disparar seleção */
        editor.selectNode('node-' + dfId);
        abrirEditor(cd);
        atualizarBotoesToolbar(cd);
    }

    /** Busca na sidebar */
    function inicializarBuscaSidebar() {
        var t = null;
        document.getElementById('sidebarBusca').addEventListener('input', function () {
            clearTimeout(t);
            var v = this.value.trim().toLowerCase();
            t = setTimeout(function () {
                document.querySelectorAll('.sidebar-node').forEach(function (el) {
                    if (!v) { el.style.display = ''; return; }
                    var nome = (el.querySelector('.sn-name') || {}).textContent || '';
                    el.style.display = nome.toLowerCase().indexOf(v) >= 0 ? '' : 'none';
                });
            }, 200);
        });
    }

    /* ============================================
       Editor lateral do nó
       ============================================ */
    function abrirEditor(cd) {
        var no = flat.find(function (n) { return n.CD_CHAVE == cd });
        if (!no) return;
        noSelecionadoCd = cd;

        document.getElementById('neCd').value = cd;
        document.getElementById('neNome').value = no.DS_NOME;
        document.getElementById('neNivel').value = no.CD_ENTIDADE_NIVEL;
        document.getElementById('neFluxo').value = no.ID_FLUXO || '';
        document.getElementById('neOp').value = no.ID_OPERACAO || '';
        document.getElementById('neObs').value = no.DS_OBSERVACAO || '';

        var permitePonto = no.OP_PERMITE_PONTO == 1;
        document.getElementById('nePontoBox').style.display = permitePonto ? 'block' : 'none';
        $('#nePonto').val(no.CD_PONTO_MEDICAO || '').trigger('change');

        /* Sistema de Água — exibir se nível for marcado como sistema */
        var ehSistema = no.OP_EH_SISTEMA == 1;
        document.getElementById('neSistemaAguaBox').style.display = ehSistema ? 'block' : 'none';
        $('#neSistemaAgua').val(no.CD_SISTEMA_AGUA || '').trigger('change');

        document.getElementById('neTitle').textContent = 'Editar: ' + no.DS_NOME;
        document.getElementById('nodeEditor').classList.add('visible');
    }

    function fecharEditor() {
        document.getElementById('nodeEditor').classList.remove('visible');
        noSelecionadoCd = null;
    }

    /** Atualiza nível → mostra/esconde ponto de medição e sistema de água */
    document.addEventListener('change', function (ev) {
        if (ev.target.id === 'neNivel') {
            var opt = ev.target.options[ev.target.selectedIndex];
            var permite = opt && opt.dataset.permitePonto === '1';
            var ehSistema = opt && opt.dataset.ehSistema === '1';
            document.getElementById('nePontoBox').style.display = permite ? 'block' : 'none';
            document.getElementById('neSistemaAguaBox').style.display = ehSistema ? 'block' : 'none';
        }
    });

    /* ============================================
       CRUD — Nós
       ============================================ */

    /** Salvar nó pelo editor lateral */
    function salvarNoEditor() {
        var cd = document.getElementById('neCd').value;
        var nv = document.getElementById('neNivel').value;
        var nm = document.getElementById('neNome').value.trim();
        if (!nv) { toast('Selecione o nível', 'err'); return; }
        if (!nm) { toast('Informe o nome', 'err'); return; }

        var no = flat.find(function (n) { return n.CD_CHAVE == cd });
        var fd = new FormData();
        fd.append('cd', cd);
        fd.append('cdNivel', nv);
        fd.append('nome', nm);
        fd.append('identificador', no ? (no.DS_IDENTIFICADOR || '') : '');
        fd.append('ordem', no ? (no.NR_ORDEM || 0) : 0);
        fd.append('fluxo', document.getElementById('neFluxo').value);
        fd.append('operacao', document.getElementById('neOp').value);
        fd.append('observacao', document.getElementById('neObs').value.trim());
        /* Manter posição */
        if (no && no.NR_POS_X !== null) { fd.append('posX', no.NR_POS_X); fd.append('posY', no.NR_POS_Y); }
        /* Ponto de medição */
        if (document.getElementById('nePontoBox').style.display !== 'none') {
            fd.append('cdPonto', $('#nePonto').val() || '');
        }
        /* Sistema de água */
        if (document.getElementById('neSistemaAguaBox').style.display !== 'none') {
            fd.append('cdSistemaAgua', $('#neSistemaAgua').val() || '');
        }

        fetch('bd/entidadeCascata/salvarNodo.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d.success) { toast(d.message, 'ok'); fecharEditor(); carregarDados(); }
                else toast(d.message, 'err');
            }).catch(function () { toast('Erro de conexão', 'err') });
    }

    /** Criar nó rápido (modal simples) */
    function criarNoRapido() {
        var nv = document.getElementById('mcNivel').value;
        var nm = document.getElementById('mcNome').value.trim();
        if (!nv) { toast('Selecione o nível', 'err'); return; }
        if (!nm) { toast('Informe o nome', 'err'); return; }

        /* Calcular posição no centro visível do canvas */
        var container = document.getElementById('drawflowCanvas');
        var zoom = editor.zoom || 1;
        var posX = Math.round((-editor.canvas_x + container.offsetWidth / 2) / zoom - 80);
        var posY = Math.round((-editor.canvas_y + container.offsetHeight / 2) / zoom - 40);

        var fd = new FormData();
        fd.append('cdNivel', nv);
        fd.append('nome', nm);
        fd.append('identificador', '');
        fd.append('ordem', 0);
        fd.append('fluxo', '');
        fd.append('operacao', '');
        fd.append('observacao', '');
        fd.append('posX', posX);
        fd.append('posY', posY);

        fetch('bd/entidadeCascata/salvarNodo.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d.success) { toast('Nó criado!', 'ok'); fecharModalCriarNo(); carregarDados(); }
                else toast(d.message, 'err');
            }).catch(function () { toast('Erro', 'err') });
    }

    /** Excluir nó ou conexão selecionada */
    function excluirSelecionado() {
        if (!noSelecionadoCd) { toast('Selecione um nó primeiro', 'inf'); return; }
        var no = flat.find(function (n) { return n.CD_CHAVE == noSelecionadoCd });
        if (!no) return;

        var jaInativo = no.OP_ATIVO == 0;
        var msg = jaInativo
            ? 'EXCLUIR PERMANENTEMENTE "' + no.DS_NOME + '" e seus descendentes?\n\nEssa ação não pode ser desfeita!'
            : 'Desativar "' + no.DS_NOME + '" e seus descendentes?';

        if (!confirm(msg)) return;

        var fd = new FormData();
        fd.append('cd', noSelecionadoCd);
        fd.append('modo', jaInativo ? 'cascade' : 'soft');
        fetch('bd/entidadeCascata/excluirNodo.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d.success) { toast(d.message, 'ok'); fecharEditor(); carregarDados(); }
                else toast(d.message, 'err');
            }).catch(function () { toast('Erro', 'err') });
    }

    /** Restaurar nó desativado */
    function restaurarSelecionado() {
        if (!noSelecionadoCd) { toast('Selecione um nó primeiro', 'inf'); return; }
        var no = flat.find(function (n) { return n.CD_CHAVE == noSelecionadoCd });
        if (!no || !confirm('Restaurar "' + no.DS_NOME + '" e seus descendentes?')) return;

        var fd = new FormData();
        fd.append('cd', noSelecionadoCd);
        fd.append('incluirDescendentes', 1);
        fetch('bd/entidadeCascata/restaurarNodo.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d.success) { toast(d.message, 'ok'); fecharEditor(); carregarDados(); }
                else toast(d.message, 'err');
            }).catch(function () { toast('Erro', 'err') });
    }

    /** Botões Excluir/Restaurar na toolbar */
    function atualizarBotoesToolbar(cd) {
        var btnExcl = document.getElementById('btnExcluir');
        var btnRest = document.getElementById('btnRestaurar');
        if (!btnExcl) return;
        if (!cd) {
            btnExcl.style.display = 'none';
            btnRest.style.display = 'none';
            return;
        }
        var no = flat.find(function (n) { return n.CD_CHAVE == cd });
        if (!no) return;
        if (no.OP_ATIVO == 0) {
            btnExcl.style.display = 'flex';
            btnExcl.innerHTML = '<ion-icon name="trash-outline"></ion-icon> Excluir Definitivo';
            btnExcl.title = 'Exclusão física permanente';
            btnRest.style.display = 'flex';
        } else {
            btnExcl.style.display = 'flex';
            btnExcl.innerHTML = '<ion-icon name="trash-outline"></ion-icon> Excluir';
            btnExcl.title = 'Desativar nó';
            btnRest.style.display = 'none';
        }
    }

    /* ============================================
       CRUD — Posição
       ============================================ */
    function salvarPosicao(cd, x, y) {
        /* Atualizar flat local */
        var no = flat.find(function (n) { return n.CD_CHAVE == cd });
        if (no) { no.NR_POS_X = x; no.NR_POS_Y = y; }

        var fd = new FormData();
        fd.append('cd', cd);
        fd.append('posX', x);
        fd.append('posY', y);
        fetch('bd/entidadeCascata/salvarPosicao.php', { method: 'POST', body: fd }).catch(function () { });
    }

    /* ============================================
       CRUD — Conexões
       ============================================ */
    function salvarConexaoBackend(cdOrigem, cdDestino) {
        /* Verificar duplicata local */
        var existe = conexoes.find(function (c) { return c.CD_NODO_ORIGEM == cdOrigem && c.CD_NODO_DESTINO == cdDestino });
        if (existe) return;

        var fd = new FormData();
        fd.append('cdOrigem', cdOrigem);
        fd.append('cdDestino', cdDestino);
        fd.append('rotulo', '');
        fetch('bd/entidadeCascata/salvarConexao.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d.success) { toast('Conexão criada', 'ok'); recarregarConexoes(); }
                else { toast(d.message, 'err'); carregarDados(); /* reverter visual */ }
            }).catch(function () { toast('Erro ao criar conexão', 'err') });
    }

    function excluirConexaoBackend(cdOrigem, cdDestino) {
        /* Encontrar CD_CHAVE da conexão */
        var cx = conexoes.find(function (c) { return c.CD_NODO_ORIGEM == cdOrigem && c.CD_NODO_DESTINO == cdDestino });
        if (!cx) return;

        var fd = new FormData();
        fd.append('cd', cx.CD_CHAVE);
        fetch('bd/entidadeCascata/excluirConexao.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d.success) { toast('Conexão removida', 'ok'); recarregarConexoes(); }
                else toast(d.message, 'err');
            }).catch(function () { toast('Erro', 'err') });
    }

    function recarregarConexoes() {
        fetch('bd/entidadeCascata/listarConexoes.php').then(function (r) { return r.json() }).then(function (d) {
            if (d.success) {
                conexoes = d.conexoes;
                document.getElementById('stConexoes').textContent = d.total || conexoes.length;
                atualizarContagemSistemas();
            }
        }).catch(function () { });
    }

    /* ============================================
       Zoom
       ============================================ */
    function zoomCanvas(dir) {
        if (dir === 0) {
            editor.zoom_reset();
        } else if (dir > 0) {
            editor.zoom_in();
        } else {
            editor.zoom_out();
        }
        document.getElementById('zoomLabel').textContent = Math.round(editor.zoom * 100) + '%';
    }

    /* ============================================
   Maximizar/Restaurar Flowchart
   Oculta cabeçalho, cards e menu para foco total no canvas.
   ============================================ */
    var flowchartMaximizado = false;

    function toggleMaximizarFlowchart() {
        flowchartMaximizado = !flowchartMaximizado;
        var body = document.body;
        var icone = document.getElementById('iconMaximizar');
        var btn = document.getElementById('btnMaximizar');

        if (flowchartMaximizado) {
            // Guardar estado do sidebar para restaurar depois
            var sidebar = document.getElementById('modernSidebar');
            window._sidebarEstavaColapsado = sidebar ? sidebar.classList.contains('collapsed') : false;

            body.classList.add('flowchart-maximizado');
            if (icone) icone.setAttribute('name', 'contract-outline');
            if (btn) btn.title = 'Restaurar visualização';
        } else {
            body.classList.remove('flowchart-maximizado');
            if (icone) icone.setAttribute('name', 'expand-outline');
            if (btn) btn.title = 'Maximizar';

            // Restaurar estado anterior do sidebar
            var sidebar = document.getElementById('modernSidebar');
            if (sidebar && window._sidebarEstavaColapsado) {
                sidebar.classList.add('collapsed');
                body.classList.add('sidebar-collapsed');
            } else if (sidebar && !window._sidebarEstavaColapsado) {
                sidebar.classList.remove('collapsed');
                body.classList.remove('sidebar-collapsed');
            }
        }

        // Forçar Drawflow a recalcular dimensões após transição
        setTimeout(function () {
            if (editor) {
                editor.zoom_refresh();
            }
        }, 100);
    }

    // ESC para sair do modo maximizado
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && flowchartMaximizado) {
            toggleMaximizarFlowchart();
        }
    });

    /* Zoom com scroll do mouse (sem precisar de Ctrl) */
    document.getElementById('drawflowCanvas').addEventListener('wheel', function (e) {
        e.preventDefault();
        if (e.deltaY < 0) {
            editor.zoom_in();
        } else {
            editor.zoom_out();
        }
        document.getElementById('zoomLabel').textContent = Math.round(editor.zoom * 100) + '%';
    }, { passive: false });

    /* ============================================
       Selects de Nível
       ============================================ */
    function popularSelectsNivel() {
        ['neNivel', 'mcNivel'].forEach(function (id) {
            var s = document.getElementById(id);
            if (!s) return;
            s.innerHTML = '<option value="">— Selecione —</option>';
            niveis.forEach(function (n) {
                var o = document.createElement('option');
                o.value = n.CD_CHAVE;
                o.textContent = n.DS_NOME;
                o.dataset.permitePonto = n.OP_PERMITE_PONTO;
                o.dataset.ehSistema = n.OP_EH_SISTEMA;
                s.appendChild(o);
            });
        });
    }

    /* ============================================
       Select Ponto de Medição (Select2)
       ============================================ */
    function inicializarSelectPonto() {
        var s = document.getElementById('nePonto');
        s.innerHTML = '<option value="">— Selecione —</option>';
        pontosJson.forEach(function (p) {
            var o = document.createElement('option');
            o.value = p.cd;
            o.textContent = p.codigo + ' - ' + p.nome;
            o.dataset.tag = p.tag || '';
            s.appendChild(o);
        });

        /** Matcher customizado: pesquisa por código, nome e TAG */
        function matcherPonto(params, data) {
            if (!params.term || params.term.trim() === '') return data;
            if (!data.element) return null;
            var termo = params.term.toLowerCase();
            var tag = (data.element.dataset.tag || '').toLowerCase();
            var texto = (data.text || '').toLowerCase();
            if (texto.indexOf(termo) > -1 || tag.indexOf(termo) > -1) {
                return data;
            }
            return null;
        }

        $('#nePonto').select2({
            placeholder: '— Buscar por código, nome ou TAG —',
            allowClear: true,
            width: '100%',
            matcher: matcherPonto,
            language: {
                noResults: function () { return 'Nenhum ponto encontrado'; }
            }
        });


        /* Autofocus no campo de busca ao abrir qualquer Select2 da página */
        $(document).on('select2:open', function () {
            setTimeout(function () {
                var campo = document.querySelector('.select2-container--open .select2-search__field');
                if (campo) campo.focus();
            }, 0);
        });
    }

    /** Select2 para Sistema de Água */
    function inicializarSelectSistemaAgua() {
        var s = document.getElementById('neSistemaAgua');
        s.innerHTML = '<option value="">— Selecione —</option>';
        sistemasAguaJson.forEach(function (sa) {
            var o = document.createElement('option');
            o.value = sa.cd;
            o.textContent = sa.nome + (sa.loc ? ' (' + sa.loc + ')' : '');
            s.appendChild(o);
        });
        $('#neSistemaAgua').select2({ placeholder: '— Selecione um sistema —', allowClear: true, width: '100%' });
    }

    /* ============================================
       Modais
       ============================================ */
    function abrirModalCriarNo() {
        document.getElementById('mcNome').value = '';
        document.getElementById('mcNivel').value = '';
        document.getElementById('modalCriarNo').classList.add('active');
        setTimeout(function () { document.getElementById('mcNome').focus() }, 100);
    }
    function fecharModalCriarNo() { document.getElementById('modalCriarNo').classList.remove('active'); }

    function abrirModalNiveis() { document.getElementById('modalNiveis').classList.add('active'); renderListaNiveis(); limparFormNivel(); }
    function fecharModalNiveis() { document.getElementById('modalNiveis').classList.remove('active'); limparFormNivel(); }

    /** Renderiza lista de níveis com botões editar/excluir */
    function renderListaNiveis() {
        var c = document.getElementById('listaNiveis');
        if (!niveis.length) { c.innerHTML = '<p style="color:#94a3b8;text-align:center;padding:12px;">Nenhum nível cadastrado.</p>'; return; }
        c.innerHTML = niveis.map(function (n) {
            /* Contar nós usando este nível */
            var qtdNos = flat.filter(function (f) { return f.CD_ENTIDADE_NIVEL == n.CD_CHAVE }).length;
            return '<div class="nivel-item">' +
                '<span class="ni-icon" style="background:' + (n.DS_COR || '#607D8B') + '"><ion-icon name="' + (n.DS_ICONE || 'ellipse-outline') + '"></ion-icon></span>' +
                '<div class="ni-info"><strong>' + esc(n.DS_NOME) + '</strong>' +
                '<small>Ordem: ' + n.NR_ORDEM + ' | Ponto: ' + (n.OP_PERMITE_PONTO == 1 ? 'Sim' : 'Não') + ' | ' + qtdNos + ' nó(s)' + (n.OP_EH_SISTEMA == 1 ? ' | <strong style="color:#1e40af;">🌐 Sistema</strong>' : '') + '</small></div>' +
                (podeEditar ?
                    '<div style="display:flex;gap:4px;">' +
                    '<button onclick="editarNivel(' + n.CD_CHAVE + ')" title="Editar" style="width:28px;height:28px;border:none;background:transparent;border-radius:6px;cursor:pointer;font-size:15px;color:#64748b;display:flex;align-items:center;justify-content:center;" onmouseover="this.style.background=\'#eff6ff\';this.style.color=\'#2563eb\'" onmouseout="this.style.background=\'transparent\';this.style.color=\'#64748b\'"><ion-icon name="create-outline"></ion-icon></button>' +
                    '<button onclick="excluirNivel(' + n.CD_CHAVE + ')" title="Excluir" style="width:28px;height:28px;border:none;background:transparent;border-radius:6px;cursor:pointer;font-size:15px;color:#64748b;display:flex;align-items:center;justify-content:center;" onmouseover="this.style.background=\'#fef2f2\';this.style.color=\'#dc2626\'" onmouseout="this.style.background=\'transparent\';this.style.color=\'#64748b\'"><ion-icon name="trash-outline"></ion-icon></button>' +
                    '</div>' : '') +
                '</div>';
        }).join('');
    }

    /** Preencher formulário para edição */
    function editarNivel(cd) {
        var nv = niveis.find(function (n) { return n.CD_CHAVE == cd });
        if (!nv) return;
        document.getElementById('nvCd').value = nv.CD_CHAVE;
        document.getElementById('nvNome').value = nv.DS_NOME;
        document.getElementById('nvCor').value = nv.DS_COR || '#607D8B';
        document.getElementById('nvIcone').value = nv.DS_ICONE || 'ellipse-outline';
        document.getElementById('nvPonto').checked = nv.OP_PERMITE_PONTO == 1;
        document.getElementById('nvSistema').checked = nv.OP_EH_SISTEMA == 1;
        document.getElementById('nvFormTitle').innerHTML = '<ion-icon name="create-outline"></ion-icon> <span>Editar: ' + esc(nv.DS_NOME) + '</span>';
        document.getElementById('nvBtnCancelar').style.display = 'flex';
        document.getElementById('nvNome').focus();
    }

    /** Limpar formulário (volta ao modo criação) */
    function limparFormNivel() {
        document.getElementById('nvCd').value = '';
        document.getElementById('nvNome').value = '';
        document.getElementById('nvCor').value = '#607D8B';
        document.getElementById('nvIcone').value = 'ellipse-outline';
        document.getElementById('nvPonto').checked = false;
        document.getElementById('nvSistema').checked = false;
        document.getElementById('nvFormTitle').innerHTML = '<ion-icon name="add-circle-outline"></ion-icon> <span>Novo Nível</span>';
        document.getElementById('nvBtnCancelar').style.display = 'none';
    }

    /** Salvar nível (criar ou atualizar) */
    function salvarNivel() {
        var nm = document.getElementById('nvNome').value.trim();
        if (!nm) { toast('Informe o nome', 'err'); return; }
        var fd = new FormData();
        var cd = document.getElementById('nvCd').value;
        if (cd) fd.append('cd', cd);
        fd.append('nome', nm);
        fd.append('cor', document.getElementById('nvCor').value);
        fd.append('icone', document.getElementById('nvIcone').value);
        fd.append('permitePonto', document.getElementById('nvPonto').checked ? 1 : 0);
        fd.append('ehSistema', document.getElementById('nvSistema').checked ? 1 : 0);
        fetch('bd/entidadeCascata/salvarNivel.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d.success) {
                    toast(d.message, 'ok');
                    limparFormNivel();
                    carregarNiveis(function () { renderListaNiveis(); carregarDados(); });
                } else toast(d.message, 'err');
            }).catch(function () { toast('Erro', 'err') });
    }

    /** Excluir nível (com confirmação) */
    function excluirNivel(cd) {
        var nv = niveis.find(function (n) { return n.CD_CHAVE == cd });
        if (!nv) return;
        var qtdNos = flat.filter(function (f) { return f.CD_ENTIDADE_NIVEL == cd }).length;
        if (qtdNos > 0) {
            toast('Este nível está sendo usado por ' + qtdNos + ' nó(s). Remova os nós primeiro.', 'err');
            return;
        }
        if (!confirm('Excluir o nível "' + nv.DS_NOME + '"?')) return;
        var fd = new FormData();
        fd.append('cd', cd);
        fetch('bd/entidadeCascata/excluirNivel.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d.success) {
                    toast(d.message, 'ok');
                    limparFormNivel();
                    carregarNiveis(function () { renderListaNiveis(); carregarDados(); });
                } else toast(d.message, 'err');
            }).catch(function () { toast('Erro', 'err') });
    }

    /* ============================================
       Wizard
       ============================================ */
    var wizardSel = null;
    var wizardTpls = [
        {
            id: 'capt_eta', nome: 'Captação → ETA', icone: 'water-outline', cor: '#2563eb',
            desc: 'Manancial com macro alimentando ETA',
            preview: 'Manancial\n └→ Macro Capt.\n   └→ ETA',
            niveis: ['Manancial/Captação', 'Ponto de Medição', 'Unidade Operacional'],
            nos: [{ nome: 'Manancial', nivel: 0 }, { nome: 'Macro Captação', nivel: 1, pai: 0 }, { nome: 'ETA', nivel: 2, pai: 1 }],
            conexoes: [[0, 1], [1, 2]]
        },
        {
            id: 'eta_res', nome: 'ETA → Reservatório', icone: 'layers-outline', cor: '#059669',
            desc: 'ETA com saídas alimentando reservatório',
            preview: 'ETA\n ├→ Saída 1\n ├→ Saída 2\n └→ Reservatório\n     └→ Nível',
            niveis: ['Unidade Operacional', 'Ponto de Medição', 'Reservatório'],
            nos: [{ nome: 'ETA', nivel: 0 }, { nome: 'Saída ETA 1', nivel: 1, pai: 0 }, { nome: 'Saída ETA 2', nivel: 1, pai: 0 }, { nome: 'Reservatório', nivel: 2, pai: 0 }, { nome: 'Nível Reservatório', nivel: 1, pai: 3 }],
            conexoes: [[0, 1], [0, 2], [0, 3], [3, 4]]
        },
        {
            id: 'completo', nome: 'Captação → ETA → Reservatório', icone: 'git-network-outline', cor: '#7c3aed',
            desc: 'Fluxo completo: captação, tratamento e reservação',
            preview: 'Manancial\n └→ Macro Capt.\n   └→ ETA\n     ├→ Macro Saída\n     └→ Reservatório\n         └→ Nível',
            niveis: ['Manancial/Captação', 'Ponto de Medição', 'Unidade Operacional', 'Reservatório'],
            nos: [{ nome: 'Manancial', nivel: 0 }, { nome: 'Macro Captação', nivel: 1, pai: 0 }, { nome: 'ETA', nivel: 2, pai: 1 }, { nome: 'Macro Saída ETA', nivel: 1, pai: 2 }, { nome: 'Reservatório', nivel: 3, pai: 3 }, { nome: 'Nível Reservatório', nivel: 1, pai: 4 }],
            conexoes: [[0, 1], [1, 2], [2, 3], [3, 4], [4, 5]]
        }
    ];

    function abrirWizard() {
        wizardSel = null;
        var c = document.getElementById('wizardTemplates');
        c.innerHTML = wizardTpls.map(function (t) {
            return '<div class="wizard-card" data-id="' + t.id + '" onclick="selWizard(\'' + t.id + '\')">' +
                '<div class="wizard-card-icon" style="background:' + t.cor + '"><ion-icon name="' + t.icone + '"></ion-icon></div>' +
                '<h4>' + esc(t.nome) + '</h4><p>' + esc(t.desc) + '</p>' +
                '<div class="wizard-preview">' + esc(t.preview) + '</div></div>';
        }).join('');
        document.getElementById('modalWizard').classList.add('active');
    }
    function fecharWizard() { document.getElementById('modalWizard').classList.remove('active'); }
    function selWizard(id) {
        wizardSel = id;
        document.querySelectorAll('.wizard-card').forEach(function (c) { c.classList.remove('selected') });
        var el = document.querySelector('.wizard-card[data-id="' + id + '"]');
        if (el) el.classList.add('selected');
    }
    function executarWizard() {
        if (!wizardSel) { toast('Selecione um template', 'err'); return; }
        var tpl = wizardTpls.find(function (t) { return t.id === wizardSel });
        if (!tpl) return;
        fecharWizard();
        toast('Criando estrutura...', 'inf');
        var cdsCriados = {};
        criarNoWiz(tpl, 0, cdsCriados);
    }
    function criarNoWiz(tpl, idx, cds) {
        if (idx >= tpl.nos.length) { criarCxWiz(tpl.conexoes, 0, cds); return; }
        var item = tpl.nos[idx];
        var nivelEnc = niveis.find(function (n) { return n.DS_NOME === tpl.niveis[item.nivel] });
        if (!nivelEnc) { toast('Nível "' + tpl.niveis[item.nivel] + '" não encontrado. Crie-o primeiro.', 'err'); return; }

        /* Posição automática em grid */
        var baseX = 120, baseY = 100;
        var posX = baseX + (idx % 3) * 250;
        var posY = baseY + Math.floor(idx / 3) * 160;

        var fd = new FormData();
        fd.append('cdNivel', nivelEnc.CD_CHAVE);
        fd.append('nome', item.nome);
        fd.append('identificador', '');
        fd.append('ordem', idx + 1);
        fd.append('fluxo', '');
        fd.append('operacao', '');
        fd.append('observacao', 'Criado por Wizard');
        fd.append('posX', posX);
        fd.append('posY', posY);

        fetch('bd/entidadeCascata/salvarNodo.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d.success) { cds[idx] = d.cd; criarNoWiz(tpl, idx + 1, cds); }
                else toast('Erro: ' + d.message, 'err');
            }).catch(function () { toast('Erro de conexão', 'err') });
    }
    function criarCxWiz(cxList, idx, cds) {
        if (idx >= cxList.length) { toast('Estrutura criada!', 'ok'); carregarDados(); return; }
        var cx = cxList[idx];
        var fd = new FormData();
        fd.append('cdOrigem', cds[cx[0]]);
        fd.append('cdDestino', cds[cx[1]]);
        fd.append('rotulo', '');
        fetch('bd/entidadeCascata/salvarConexao.php', { method: 'POST', body: fd })
            .then(function () { criarCxWiz(cxList, idx + 1, cds) })
            .catch(function () { criarCxWiz(cxList, idx + 1, cds) });
    }

    /* ============================================
       Utilitários
       ============================================ */
    function toast(msg, tipo) {
        var c = document.getElementById('toastBox');
        var ic = tipo === 'ok' ? 'checkmark-circle-outline' : tipo === 'err' ? 'alert-circle-outline' : 'information-circle-outline';
        var d = document.createElement('div');
        d.className = 'toast-msg ' + tipo;
        d.innerHTML = '<ion-icon name="' + ic + '"></ion-icon> ' + esc(msg);
        c.appendChild(d);
        setTimeout(function () { d.remove() }, 4000);
    }
    function esc(t) { if (!t) return ''; var d = document.createElement('div'); d.appendChild(document.createTextNode(t)); return d.innerHTML; }

    /* Fechar modais com ESC */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(function (m) { m.classList.remove('active') });
            fecharEditor();
        }
        /* Delete/Backspace: excluir nó selecionado */
        if ((e.key === 'Delete' || e.key === 'Backspace') && noSelecionadoCd && podeEditar) {
            /* Não excluir se foco em input */
            if (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA' || document.activeElement.tagName === 'SELECT') return;
            excluirSelecionado();
        }
    });

    /* Fechar modais ao clicar fora */
    document.querySelectorAll('.modal-overlay').forEach(function (m) {
        m.addEventListener('click', function (e) { if (e.target === this) this.classList.remove('active'); });
    });
</script>

<?php include_once 'includes/footer.inc.php'; ?>
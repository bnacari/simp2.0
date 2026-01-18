<?php
/**
 * SIMP - Gerenciamento de Regras da IA
 * Permite que usuários treinem a IA com instruções personalizadas
 * 
 * @author Bruno
 * @version 1.0
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão de acesso à tela (mínimo leitura)
exigePermissaoTela('Treinamento IA', ACESSO_LEITURA);

// Verifica se pode editar (para ocultar/desabilitar botões)
$podeEditar = podeEditarTela('Treinamento IA');

// Buscar categorias existentes para o filtro
$categorias = [];
try {
    $sqlCat = "SELECT DISTINCT DS_CATEGORIA FROM SIMP.dbo.IA_REGRAS WHERE DS_CATEGORIA IS NOT NULL ORDER BY DS_CATEGORIA";
    $stmtCat = $pdoSIMP->query($sqlCat);
    $categorias = $stmtCat->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Tabela pode não existir ainda
}

// Buscar configuração atual da IA
$configIA = [];
try {
    $configFile = __DIR__ . '/bd/config/ia_config.php';
    if (file_exists($configFile)) {
        $configIA = require $configFile;
    }
} catch (Exception $e) {
    // Ignorar
}
$providerAtual = $configIA['provider'] ?? 'deepseek';
?>

<style>
    /* ============================================
       Estilos específicos da tela de Regras IA
       ============================================ */
    
    .page-container {
        padding: 24px;
        max-width: 1600px;
        margin: 0 auto;
    }

    /* Page Header */
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
    }

    .page-header-subtitle {
        font-size: 14px;
        opacity: 0.9;
        margin: 0;
    }

    .page-header-actions {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .provider-badge {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, 0.2);
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
    }

    .provider-badge ion-icon {
        font-size: 18px;
    }

    /* Cards de estatísticas */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border: 1px solid #e2e8f0;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    .stat-icon.purple { background: #dbeafe; color: #2563eb; }
    .stat-icon.green { background: #dcfce7; color: #16a34a; }
    .stat-icon.blue { background: #dbeafe; color: #2563eb; }
    .stat-icon.orange { background: #ffedd5; color: #ea580c; }

    .stat-info h3 {
        font-size: 24px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    .stat-info p {
        font-size: 13px;
        color: #64748b;
        margin: 4px 0 0 0;
    }

    /* Filtros */
    .filters-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border: 1px solid #e2e8f0;
    }

    .filters-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .filters-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 15px;
        font-weight: 600;
        color: #1e293b;
    }

    .filters-title ion-icon {
        font-size: 20px;
        color: #3b82f6;
    }

    .filters-grid {
        display: grid;
        grid-template-columns: 1fr 200px auto;
        gap: 16px;
        align-items: end;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-label {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .form-control {
        padding: 10px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        color: #1e293b;
        background: white;
        transition: all 0.2s;
    }

    .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .btn-novo {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .btn-novo:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .btn-novo ion-icon {
        font-size: 18px;
    }

    /* Lista de Regras */
    .regras-container {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .regra-card {
        background: white;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        transition: all 0.2s;
    }

    .regra-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .regra-card.inativa {
        opacity: 0.6;
        background: #f8fafc;
    }

    .regra-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        cursor: pointer;
    }

    .regra-header:hover {
        background: #f1f5f9;
    }

    .regra-info {
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 1;
    }

    .regra-ordem {
        width: 32px;
        height: 32px;
        background: #3b82f6;
        color: white;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .regra-titulo {
        font-size: 15px;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }

    .regra-categoria {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        background: #dbeafe;
        color: #2563eb;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .regra-status {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }

    .status-badge.ativa {
        background: #dcfce7;
        color: #16a34a;
    }

    .status-badge.inativa {
        background: #fee2e2;
        color: #dc2626;
    }

    .regra-toggle {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        transition: transform 0.2s;
    }

    .regra-card.expanded .regra-toggle {
        transform: rotate(180deg);
    }

    .regra-content {
        display: none;
        padding: 20px;
        border-top: 1px solid #e2e8f0;
    }

    .regra-card.expanded .regra-content {
        display: block;
    }

    .regra-texto {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 16px;
        font-family: 'Monaco', 'Consolas', monospace;
        font-size: 13px;
        line-height: 1.6;
        color: #334155;
        white-space: pre-wrap;
        max-height: 300px;
        overflow-y: auto;
    }

    .regra-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid #e2e8f0;
        flex-wrap: wrap;
        gap: 12px;
    }

    .regra-meta-info {
        display: flex;
        align-items: center;
        gap: 16px;
        font-size: 12px;
        color: #64748b;
    }

    .regra-meta-info span {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .regra-acoes {
        display: flex;
        gap: 8px;
    }

    .btn-acao {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border: none;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-acao.editar {
        background: #dbeafe;
        color: #2563eb;
    }

    .btn-acao.editar:hover {
        background: #bfdbfe;
    }

    .btn-acao.duplicar {
        background: #dbeafe;
        color: #2563eb;
    }

    .btn-acao.duplicar:hover {
        background: #bfdbfe;
    }

    .btn-acao.excluir {
        background: #fee2e2;
        color: #dc2626;
    }

    .btn-acao.excluir:hover {
        background: #fecaca;
    }

    .btn-acao ion-icon {
        font-size: 16px;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 12px;
        border: 2px dashed #e2e8f0;
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
        color: #94a3b8;
        margin: 0;
    }

    /* Modal */
    .modal-overlay {
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
        padding: 20px;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal {
        background: white;
        border-radius: 16px;
        width: 100%;
        max-width: 700px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 24px;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        border-radius: 16px 16px 0 0;
        color: white;
    }

    .modal-title {
        font-size: 18px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-title ion-icon {
        font-size: 22px;
    }

    .modal-close {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.2);
        border: none;
        border-radius: 8px;
        color: white;
        cursor: pointer;
        transition: all 0.2s;
    }

    .modal-close:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    .modal-close ion-icon {
        font-size: 20px;
    }

    .modal-body {
        padding: 24px;
        overflow-y: auto;
        flex: 1;
    }

    .modal-body .form-group {
        margin-bottom: 20px;
    }

    .modal-body .form-group:last-child {
        margin-bottom: 0;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .form-control-textarea {
        min-height: 200px;
        font-family: 'Monaco', 'Consolas', monospace;
        font-size: 13px;
        line-height: 1.5;
        resize: vertical;
    }

    .form-help {
        font-size: 12px;
        color: #64748b;
        margin-top: 6px;
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
        gap: 8px;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-secondary {
        background: #e2e8f0;
        color: #475569;
    }

    .btn-secondary:hover {
        background: #cbd5e1;
    }

    .btn-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .btn ion-icon {
        font-size: 18px;
    }

    /* Toggle Switch */
    .toggle-switch {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .toggle-switch input {
        display: none;
    }

    .toggle-slider {
        width: 48px;
        height: 26px;
        background: #cbd5e1;
        border-radius: 26px;
        position: relative;
        cursor: pointer;
        transition: all 0.3s;
    }

    .toggle-slider::after {
        content: '';
        position: absolute;
        width: 22px;
        height: 22px;
        background: white;
        border-radius: 50%;
        top: 2px;
        left: 2px;
        transition: all 0.3s;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .toggle-switch input:checked + .toggle-slider {
        background: #16a34a;
    }

    .toggle-switch input:checked + .toggle-slider::after {
        left: 24px;
    }

    .toggle-label {
        font-size: 14px;
        color: #475569;
    }

    /* Toast */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10001;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .toast {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 20px;
        border-radius: 10px;
        background: white;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        animation: slideIn 0.3s ease;
        min-width: 300px;
    }

    .toast.sucesso {
        border-left: 4px solid #16a34a;
    }

    .toast.erro {
        border-left: 4px solid #dc2626;
    }

    .toast.aviso {
        border-left: 4px solid #f59e0b;
    }

    .toast ion-icon {
        font-size: 22px;
    }

    .toast.sucesso ion-icon { color: #16a34a; }
    .toast.erro ion-icon { color: #dc2626; }
    .toast.aviso ion-icon { color: #f59e0b; }

    .toast-message {
        flex: 1;
        font-size: 14px;
        color: #1e293b;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
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
        z-index: 9999;
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
        border-top-color: #3b82f6;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Responsivo */
    @media (max-width: 768px) {
        .page-container {
            padding: 16px;
        }

        .page-header {
            padding: 20px;
        }

        .page-header-content {
            flex-direction: column;
            align-items: flex-start;
        }

        .filters-grid {
            grid-template-columns: 1fr;
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .regra-header {
            flex-wrap: wrap;
        }

        .regra-meta {
            flex-direction: column;
            align-items: flex-start;
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
                    <ion-icon name="sparkles"></ion-icon>
                </div>
                <div>
                    <h1>Treinamento da IA</h1>
                    <p class="page-header-subtitle">Configure instruções e regras para personalizar o comportamento da IA</p>
                </div>
            </div>
            <div class="page-header-actions">
                <div class="provider-badge">
                    <ion-icon name="hardware-chip-outline"></ion-icon>
                    Provider: <?= ucfirst($providerAtual) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon purple">
                <ion-icon name="document-text-outline"></ion-icon>
            </div>
            <div class="stat-info">
                <h3 id="statTotal">-</h3>
                <p>Total de Regras</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">
                <ion-icon name="checkmark-circle-outline"></ion-icon>
            </div>
            <div class="stat-info">
                <h3 id="statAtivas">-</h3>
                <p>Regras Ativas</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">
                <ion-icon name="folder-outline"></ion-icon>
            </div>
            <div class="stat-info">
                <h3 id="statCategorias">-</h3>
                <p>Categorias</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">
                <ion-icon name="text-outline"></ion-icon>
            </div>
            <div class="stat-info">
                <h3 id="statCaracteres">-</h3>
                <p>Total de Caracteres</p>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <div class="filters-header">
            <div class="filters-title">
                <ion-icon name="list-outline"></ion-icon>
                Lista de Regras
            </div>
            <?php if ($podeEditar): ?>
            <button type="button" class="btn-novo" onclick="abrirModalRegra()">
                <ion-icon name="add-outline"></ion-icon>
                Nova Regra
            </button>
            <?php endif; ?>
        </div>
        <div class="filters-grid">
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="search-outline"></ion-icon>
                    Buscar
                </label>
                <input type="text" class="form-control" id="filtroTexto" placeholder="Buscar por título ou conteúdo..." oninput="filtrarRegras()">
            </div>
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="folder-outline"></ion-icon>
                    Categoria
                </label>
                <select class="form-control" id="filtroCategoria" onchange="filtrarRegras()">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">&nbsp;</label>
                <button type="button" class="btn btn-secondary" onclick="carregarRegras()">
                    <ion-icon name="refresh-outline"></ion-icon>
                    Atualizar
                </button>
            </div>
        </div>
    </div>

    <!-- Lista de Regras -->
    <div class="regras-container" id="regrasContainer">
        <div class="empty-state">
            <ion-icon name="hourglass-outline"></ion-icon>
            <h3>Carregando...</h3>
            <p>Aguarde enquanto as regras são carregadas</p>
        </div>
    </div>
</div>

<!-- Modal de Regra -->
<div class="modal-overlay" id="modalRegra">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">
                <ion-icon name="create-outline"></ion-icon>
                <span id="modalRegraTitulo">Nova Regra</span>
            </span>
            <button type="button" class="modal-close" onclick="fecharModal()">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="regraCdChave" value="">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Título *</label>
                    <input type="text" class="form-control" id="regraTitulo" placeholder="Ex: Regras de Cálculo de Média" maxlength="200">
                </div>
                <div class="form-group">
                    <label class="form-label">Categoria</label>
                    <input type="text" class="form-control" id="regraCategoria" placeholder="Ex: Cálculos, Formato, Referência" maxlength="100" list="listaCategorias">
                    <datalist id="listaCategorias">
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Ordem</label>
                    <input type="number" class="form-control" id="regraOrdem" placeholder="0" min="0" value="0">
                    <p class="form-help">Regras com menor número aparecem primeiro</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div class="toggle-switch">
                        <input type="checkbox" id="regraAtivo" checked>
                        <label class="toggle-slider" for="regraAtivo"></label>
                        <span class="toggle-label">Regra ativa</span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Conteúdo da Regra *</label>
                <textarea class="form-control form-control-textarea" id="regraConteudo" placeholder="Digite as instruções para a IA..."></textarea>
                <p class="form-help">Use **texto** para negrito. Seja claro e objetivo nas instruções.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fecharModal()">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="salvarRegra()">
                <ion-icon name="checkmark-outline"></ion-icon>
                Salvar
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Loading -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<script>
    // ============================================
    // Variáveis globais
    // ============================================
    let regrasData = [];
    const podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;

    // ============================================
    // Inicialização
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        carregarRegras();
    });

    // ============================================
    // Funções de carregamento
    // ============================================
    
    /**
     * Carrega todas as regras do banco de dados
     */
    function carregarRegras() {
        mostrarLoading(true);
        
        fetch('bd/ia/listarRegras.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    regrasData = data.regras;
                    atualizarEstatisticas(data.estatisticas);
                    renderizarRegras(regrasData);
                } else {
                    showToast(data.message || 'Erro ao carregar regras', 'erro');
                    renderizarRegras([]);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro de comunicação com o servidor', 'erro');
                renderizarRegras([]);
            })
            .finally(() => {
                mostrarLoading(false);
            });
    }

    /**
     * Atualiza os cards de estatísticas
     */
    function atualizarEstatisticas(stats) {
        document.getElementById('statTotal').textContent = stats.total || 0;
        document.getElementById('statAtivas').textContent = stats.ativas || 0;
        document.getElementById('statCategorias').textContent = stats.categorias || 0;
        document.getElementById('statCaracteres').textContent = formatarNumero(stats.caracteres || 0);
    }

    /**
     * Renderiza a lista de regras
     */
    function renderizarRegras(regras) {
        const container = document.getElementById('regrasContainer');
        
        if (!regras || regras.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <ion-icon name="document-text-outline"></ion-icon>
                    <h3>Nenhuma regra encontrada</h3>
                    <p>Clique em "Nova Regra" para adicionar instruções para a IA</p>
                </div>
            `;
            return;
        }

        let html = '';
        regras.forEach((regra, index) => {
            const isAtiva = regra.OP_ATIVO == 1;
            const dtCriacao = regra.DT_CRIACAO ? formatarData(regra.DT_CRIACAO) : '-';
            const dtAtualizacao = regra.DT_ATUALIZACAO ? formatarData(regra.DT_ATUALIZACAO) : '-';
            
            html += `
                <div class="regra-card ${!isAtiva ? 'inativa' : ''}" data-id="${regra.CD_CHAVE}">
                    <div class="regra-header" onclick="toggleRegra(this)">
                        <div class="regra-info">
                            <span class="regra-ordem">${regra.NR_ORDEM || index + 1}</span>
                            <div>
                                <h4 class="regra-titulo">${escapeHtml(regra.DS_TITULO)}</h4>
                                ${regra.DS_CATEGORIA ? `<span class="regra-categoria">${escapeHtml(regra.DS_CATEGORIA)}</span>` : ''}
                            </div>
                        </div>
                        <div class="regra-status">
                            <span class="status-badge ${isAtiva ? 'ativa' : 'inativa'}">
                                <ion-icon name="${isAtiva ? 'checkmark-circle' : 'close-circle'}-outline"></ion-icon>
                                ${isAtiva ? 'Ativa' : 'Inativa'}
                            </span>
                            <div class="regra-toggle">
                                <ion-icon name="chevron-down-outline"></ion-icon>
                            </div>
                        </div>
                    </div>
                    <div class="regra-content">
                        <div class="regra-texto">${escapeHtml(regra.DS_CONTEUDO)}</div>
                        <div class="regra-meta">
                            <div class="regra-meta-info">
                                <span><ion-icon name="calendar-outline"></ion-icon> Criado: ${dtCriacao}</span>
                                <span><ion-icon name="create-outline"></ion-icon> Atualizado: ${dtAtualizacao}</span>
                                <span><ion-icon name="text-outline"></ion-icon> ${regra.DS_CONTEUDO.length} caracteres</span>
                            </div>
                            ${podeEditar ? `
                            <div class="regra-acoes">
                                <button type="button" class="btn-acao editar" onclick="editarRegra(${regra.CD_CHAVE})">
                                    <ion-icon name="create-outline"></ion-icon>
                                    Editar
                                </button>
                                <button type="button" class="btn-acao duplicar" onclick="duplicarRegra(${regra.CD_CHAVE})">
                                    <ion-icon name="copy-outline"></ion-icon>
                                    Duplicar
                                </button>
                                <button type="button" class="btn-acao excluir" onclick="excluirRegra(${regra.CD_CHAVE})">
                                    <ion-icon name="trash-outline"></ion-icon>
                                    Excluir
                                </button>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    // ============================================
    // Funções de filtro
    // ============================================
    
    /**
     * Filtra as regras por texto e categoria
     */
    function filtrarRegras() {
        const texto = document.getElementById('filtroTexto').value.toLowerCase();
        const categoria = document.getElementById('filtroCategoria').value;

        const regrasFiltradas = regrasData.filter(regra => {
            const matchTexto = !texto || 
                regra.DS_TITULO.toLowerCase().includes(texto) || 
                regra.DS_CONTEUDO.toLowerCase().includes(texto);
            
            const matchCategoria = !categoria || regra.DS_CATEGORIA === categoria;

            return matchTexto && matchCategoria;
        });

        renderizarRegras(regrasFiltradas);
    }

    // ============================================
    // Funções do modal
    // ============================================
    
    /**
     * Abre modal para nova regra
     */
    function abrirModalRegra() {
        document.getElementById('modalRegraTitulo').textContent = 'Nova Regra';
        document.getElementById('regraCdChave').value = '';
        document.getElementById('regraTitulo').value = '';
        document.getElementById('regraCategoria').value = '';
        document.getElementById('regraOrdem').value = regrasData.length + 1;
        document.getElementById('regraAtivo').checked = true;
        document.getElementById('regraConteudo').value = '';
        
        document.getElementById('modalRegra').classList.add('active');
    }

    /**
     * Fecha o modal
     */
    function fecharModal() {
        document.getElementById('modalRegra').classList.remove('active');
    }

    /**
     * Edita uma regra existente
     */
    function editarRegra(cdChave) {
        const regra = regrasData.find(r => r.CD_CHAVE == cdChave);
        if (!regra) {
            showToast('Regra não encontrada', 'erro');
            return;
        }

        document.getElementById('modalRegraTitulo').textContent = 'Editar Regra';
        document.getElementById('regraCdChave').value = regra.CD_CHAVE;
        document.getElementById('regraTitulo').value = regra.DS_TITULO;
        document.getElementById('regraCategoria').value = regra.DS_CATEGORIA || '';
        document.getElementById('regraOrdem').value = regra.NR_ORDEM || 0;
        document.getElementById('regraAtivo').checked = regra.OP_ATIVO == 1;
        document.getElementById('regraConteudo').value = regra.DS_CONTEUDO;

        document.getElementById('modalRegra').classList.add('active');
    }

    /**
     * Duplica uma regra
     */
    function duplicarRegra(cdChave) {
        const regra = regrasData.find(r => r.CD_CHAVE == cdChave);
        if (!regra) {
            showToast('Regra não encontrada', 'erro');
            return;
        }

        document.getElementById('modalRegraTitulo').textContent = 'Nova Regra (Cópia)';
        document.getElementById('regraCdChave').value = '';
        document.getElementById('regraTitulo').value = regra.DS_TITULO + ' (Cópia)';
        document.getElementById('regraCategoria').value = regra.DS_CATEGORIA || '';
        document.getElementById('regraOrdem').value = regrasData.length + 1;
        document.getElementById('regraAtivo').checked = true;
        document.getElementById('regraConteudo').value = regra.DS_CONTEUDO;

        document.getElementById('modalRegra').classList.add('active');
    }

    /**
     * Salva a regra (nova ou edição)
     */
    function salvarRegra() {
        const cdChave = document.getElementById('regraCdChave').value;
        const titulo = document.getElementById('regraTitulo').value.trim();
        const categoria = document.getElementById('regraCategoria').value.trim();
        const ordem = parseInt(document.getElementById('regraOrdem').value) || 0;
        const ativo = document.getElementById('regraAtivo').checked ? 1 : 0;
        const conteudo = document.getElementById('regraConteudo').value.trim();

        // Validações
        if (!titulo) {
            showToast('Informe o título da regra', 'aviso');
            document.getElementById('regraTitulo').focus();
            return;
        }

        if (!conteudo) {
            showToast('Informe o conteúdo da regra', 'aviso');
            document.getElementById('regraConteudo').focus();
            return;
        }

        mostrarLoading(true);

        const payload = {
            cdChave: cdChave || null,
            titulo: titulo,
            categoria: categoria || null,
            ordem: ordem,
            ativo: ativo,
            conteudo: conteudo
        };

        fetch('bd/ia/salvarRegra.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Regra salva com sucesso!', 'sucesso');
                fecharModal();
                carregarRegras();
            } else {
                showToast(data.message || 'Erro ao salvar regra', 'erro');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showToast('Erro de comunicação com o servidor', 'erro');
        })
        .finally(() => {
            mostrarLoading(false);
        });
    }

    /**
     * Exclui uma regra
     */
    function excluirRegra(cdChave) {
        const regra = regrasData.find(r => r.CD_CHAVE == cdChave);
        if (!regra) return;

        if (!confirm(`Deseja realmente excluir a regra "${regra.DS_TITULO}"?`)) {
            return;
        }

        mostrarLoading(true);

        fetch('bd/ia/excluirRegra.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cdChave: cdChave })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Regra excluída com sucesso!', 'sucesso');
                carregarRegras();
            } else {
                showToast(data.message || 'Erro ao excluir regra', 'erro');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showToast('Erro de comunicação com o servidor', 'erro');
        })
        .finally(() => {
            mostrarLoading(false);
        });
    }

    // ============================================
    // Funções auxiliares
    // ============================================
    
    /**
     * Expande/colapsa uma regra
     */
    function toggleRegra(header) {
        const card = header.closest('.regra-card');
        card.classList.toggle('expanded');
    }

    /**
     * Mostra/oculta loading
     */
    function mostrarLoading(show) {
        document.getElementById('loadingOverlay').classList.toggle('active', show);
    }

    /**
     * Exibe toast de notificação
     */
    function showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        const icons = {
            sucesso: 'checkmark-circle',
            erro: 'alert-circle',
            aviso: 'warning'
        };

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <ion-icon name="${icons[type] || 'information-circle'}-outline"></ion-icon>
            <span class="toast-message">${message}</span>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    /**
     * Escapa HTML para evitar XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Formata número com separador de milhar
     */
    function formatarNumero(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    /**
     * Formata data para exibição
     */
    function formatarData(dataStr) {
        if (!dataStr) return '-';
        const data = new Date(dataStr);
        return data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }
</script>

<?php include_once 'includes/footer.inc.php'; ?>
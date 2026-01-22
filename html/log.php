<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Consulta de Log do Sistema
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão de acesso à tela
exigePermissaoTela('Consultar Log', ACESSO_LEITURA);

// Buscar usuários para filtro
$sqlUsuarios = $pdoSIMP->query("SELECT CD_USUARIO, DS_NOME, DS_LOGIN FROM SIMP.dbo.USUARIO ORDER BY DS_NOME");
$usuarios = $sqlUsuarios->fetchAll(PDO::FETCH_ASSOC);

// Buscar funcionalidades para filtro
$sqlFuncionalidades = $pdoSIMP->query("SELECT CD_FUNCIONALIDADE, DS_NOME FROM SIMP.dbo.FUNCIONALIDADE ORDER BY DS_NOME");
$funcionalidades = $sqlFuncionalidades->fetchAll(PDO::FETCH_ASSOC);

// Buscar unidades para filtro
$sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

// Tipos de Log (ajustar conforme sua necessidade)
$tiposLog = [
    1 => 'Informação',
    2 => 'Aviso',
    3 => 'Erro',
    4 => 'Debug'
];
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

    /* ============================================
       Filtros
       ============================================ */
    .filtros-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border: 1px solid #e2e8f0;
    }

    .filtros-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e2e8f0;
    }

    .filtros-header ion-icon {
        font-size: 20px;
        color: #3b82f6;
    }

    .filtros-header h3 {
        font-size: 15px;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }

    .filtros-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
    }

    .filtro-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .filtro-group label {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .filtro-group input,
    .filtro-group select {
        padding: 10px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        color: #1e293b;
        background: #f8fafc;
        transition: all 0.2s ease;
    }

    .filtro-group input:focus,
    .filtro-group select:focus {
        outline: none;
        border-color: #3b82f6;
        background: white;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .filtros-actions {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }

    .btn-filtrar {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 10px 20px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-filtrar:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .btn-limpar {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 10px 16px;
        background: #f1f5f9;
        color: #64748b;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-limpar:hover {
        background: #e2e8f0;
        color: #475569;
    }

    /* ============================================
       Tabela
       ============================================ */
    .table-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }

    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .table-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .table-count {
        font-size: 13px;
        color: #64748b;
    }

    .table-count strong {
        color: #1e293b;
    }

    .table-container {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    thead th {
        background: #f1f5f9;
        padding: 14px 16px;
        text-align: left;
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }

    tbody td {
        padding: 14px 16px;
        font-size: 14px;
        color: #334155;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: top;
    }

    tbody tr:hover {
        background: #f8fafc;
    }

    .td-code {
        font-family: 'JetBrains Mono', 'Fira Code', monospace;
        font-size: 12px;
        color: #64748b;
    }

    .td-datetime {
        white-space: nowrap;
        font-size: 13px;
    }

    .td-usuario {
        max-width: 150px;
    }

    .td-funcionalidade {
        max-width: 180px;
    }

    .td-nome-log {
        max-width: 200px;
        font-weight: 500;
    }

    .td-descricao {
        max-width: 350px;
    }

    .descricao-truncate {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        cursor: pointer;
    }

    .descricao-truncate:hover {
        color: #3b82f6;
    }

    /* Badges de Tipo */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .badge-info {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .badge-warning {
        background: #fef3c7;
        color: #b45309;
    }

    .badge-error {
        background: #fee2e2;
        color: #dc2626;
    }

    .badge-debug {
        background: #f3e8ff;
        color: #7c3aed;
    }

    .badge-default {
        background: #f1f5f9;
        color: #64748b;
    }

    /* ============================================
       Paginação
       ============================================ */
    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-top: 1px solid #e2e8f0;
        background: #fafbfc;
    }

    .pagination-info {
        font-size: 13px;
        color: #64748b;
    }

    .pagination {
        display: flex;
        gap: 4px;
    }

    .page-btn {
        min-width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 10px;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 13px;
        color: #64748b;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .page-btn:hover:not(:disabled) {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #334155;
    }

    .page-btn.active {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border-color: #2563eb;
        color: white;
    }

    .page-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* ============================================
       Loading
       ============================================ */
    .loading-overlay {
        display: none;
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        z-index: 10;
        align-items: center;
        justify-content: center;
    }

    .loading-overlay.active {
        display: flex;
    }

    .spinner {
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
       Modal Detalhes
       ============================================ */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: 16px;
        width: 100%;
        max-width: 700px;
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        color: white;
    }

    .modal-header h3 {
        font-size: 18px;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-close {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.15);
        border: none;
        border-radius: 8px;
        color: white;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .modal-close:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    .modal-body {
        padding: 24px;
        overflow-y: auto;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }

    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .detail-item.full-width {
        grid-column: 1 / -1;
    }

    .detail-label {
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .detail-value {
        font-size: 14px;
        color: #1e293b;
        padding: 10px 14px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .detail-value.description {
        white-space: pre-wrap;
        word-break: break-word;
        max-height: 300px;
        overflow-y: auto;
        font-family: 'JetBrains Mono', 'Fira Code', monospace;
        font-size: 13px;
    }

    /* ============================================
       Empty State
       ============================================ */
    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        text-align: center;
    }

    .empty-state ion-icon {
        font-size: 64px;
        color: #cbd5e1;
        margin-bottom: 16px;
    }

    .empty-state h4 {
        font-size: 18px;
        font-weight: 600;
        color: #64748b;
        margin: 0 0 8px 0;
    }

    .empty-state p {
        font-size: 14px;
        color: #94a3b8;
        margin: 0;
    }

    /* ============================================
       Responsive
       ============================================ */
    @media (max-width: 768px) {
        .page-container {
            padding: 16px;
        }

        .page-header {
            padding: 20px;
        }

        .page-header h1 {
            font-size: 18px;
        }

        .filtros-grid {
            grid-template-columns: 1fr;
        }

        .filtros-actions {
            flex-direction: column;
            width: 100%;
        }

        .filtros-actions button {
            width: 100%;
            justify-content: center;
        }

        .detail-grid {
            grid-template-columns: 1fr;
        }

        .pagination-container {
            flex-direction: column;
            gap: 12px;
        }
    }
</style>

<div class="page-container">
    <!-- Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="document-text-outline"></ion-icon>
                </div>
                <div>
                    <h1>Consulta de Log</h1>
                    <p class="page-header-subtitle">Visualize os registros de atividades do sistema</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filtros-card">
        <div class="filtros-header">
            <ion-icon name="filter-outline"></ion-icon>
            <h3>Filtros de Pesquisa</h3>
        </div>
        <div class="filtros-grid">
            <div class="filtro-group">
                <label>Data/Hora Início</label>
                <input type="datetime-local" id="filtroDataInicio">
            </div>
            <div class="filtro-group">
                <label>Data/Hora Fim</label>
                <input type="datetime-local" id="filtroDataFim">
            </div>
            <div class="filtro-group">
                <label>Usuário</label>
                <select id="filtroUsuario">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $usuario): ?>
                        <option value="<?= $usuario['CD_USUARIO'] ?>">
                            <?= htmlspecialchars($usuario['DS_NOME'] ?: $usuario['DS_LOGIN']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtro-group">
                <label>Funcionalidade</label>
                <select id="filtroFuncionalidade">
                    <option value="">Todas</option>
                    <?php foreach ($funcionalidades as $func): ?>
                        <option value="<?= $func['CD_FUNCIONALIDADE'] ?>">
                            <?= htmlspecialchars($func['DS_NOME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtro-group">
                <label>Unidade</label>
                <select id="filtroUnidade">
                    <option value="">Todas</option>
                    <?php foreach ($unidades as $unidade): ?>
                        <option value="<?= $unidade['CD_UNIDADE'] ?>">
                            <?= htmlspecialchars($unidade['DS_NOME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtro-group">
                <label>Tipo</label>
                <select id="filtroTipo">
                    <option value="">Todos</option>
                    <?php foreach ($tiposLog as $key => $nome): ?>
                        <option value="<?= $key ?>"><?= $nome ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtro-group">
                <label>Nome/Descrição</label>
                <input type="text" id="filtroBusca" placeholder="Buscar por texto...">
            </div>
            <div class="filtros-actions">
                <button type="button" class="btn-filtrar" onclick="buscarLogs(1)">
                    <ion-icon name="search-outline"></ion-icon>
                    Pesquisar
                </button>
                <button type="button" class="btn-limpar" onclick="limparFiltros()">
                    <ion-icon name="refresh-outline"></ion-icon>
                    Limpar
                </button>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="table-card" style="position: relative;">
        <div class="loading-overlay" id="loadingOverlay">
            <div class="spinner"></div>
        </div>

        <div class="table-header">
            <div class="table-info">
                <span class="table-count" id="countRegistros">Carregando...</span>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 80px;">Código</th>
                        <th style="width: 150px;">Data/Hora</th>
                        <th>Usuário</th>
                        <th>Funcionalidade</th>
                        <th style="width: 80px;">Tipo</th>
                        <th>Nome</th>
                        <th>Descrição</th>
                        <th style="width: 100px;">Versão</th>
                        <th style="width: 60px;"></th>
                    </tr>
                </thead>
                <tbody id="tabelaLogs">
                    <!-- Dados via AJAX -->
                </tbody>
            </table>
        </div>

        <div class="pagination-container">
            <div class="pagination-info" id="paginationInfo">-</div>
            <div class="pagination" id="pagination"></div>
        </div>
    </div>
</div>

<!-- Modal Detalhes -->
<div class="modal-overlay" id="modalDetalhes" onclick="fecharModalDetalhes(event)">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3>
                <ion-icon name="document-text-outline"></ion-icon>
                Detalhes do Log
            </h3>
            <button class="modal-close" onclick="fecharModalDetalhes()">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Código</span>
                    <span class="detail-value" id="detCodigo">-</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Data/Hora</span>
                    <span class="detail-value" id="detData">-</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Usuário</span>
                    <span class="detail-value" id="detUsuario">-</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Funcionalidade</span>
                    <span class="detail-value" id="detFuncionalidade">-</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Unidade</span>
                    <span class="detail-value" id="detUnidade">-</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Tipo</span>
                    <span class="detail-value" id="detTipo">-</span>
                </div>
                <div class="detail-item full-width">
                    <span class="detail-label">Nome</span>
                    <span class="detail-value" id="detNome">-</span>
                </div>
                <div class="detail-item full-width">
                    <span class="detail-label">Descrição</span>
                    <span class="detail-value description" id="detDescricao">-</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Versão</span>
                    <span class="detail-value" id="detVersao">-</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Servidor</span>
                    <span class="detail-value" id="detServidor">-</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
    // ============================================
    // SIMP - Consulta de Log
    // Variáveis de estado e paginação
    // ============================================
    let estadoPaginacao = {
        pagina: 1,
        total: 0,
        totalPaginas: 0
    };

    const porPagina = 20;

    // Tipos de Log para exibição
    const tiposLog = {
        1: { nome: 'Informação', classe: 'badge-info' },
        2: { nome: 'Aviso', classe: 'badge-warning' },
        3: { nome: 'Erro', classe: 'badge-error' },
        4: { nome: 'Debug', classe: 'badge-debug' }
    };

    // ============================================
    // Inicialização Select2 nos dropdowns
    // ============================================
    function initSelect2() {
        // Usuário
        $('#filtroUsuario').select2({
            placeholder: 'Todos',
            allowClear: true,
            width: '100%',
            language: {
                noResults: function () { return 'Nenhum resultado encontrado'; },
                searching: function () { return 'Buscando...'; }
            }
        });

        // Funcionalidade
        $('#filtroFuncionalidade').select2({
            placeholder: 'Todas',
            allowClear: true,
            width: '100%',
            language: {
                noResults: function () { return 'Nenhum resultado encontrado'; },
                searching: function () { return 'Buscando...'; }
            }
        });

        // Unidade
        $('#filtroUnidade').select2({
            placeholder: 'Todas',
            allowClear: true,
            width: '100%',
            language: {
                noResults: function () { return 'Nenhum resultado encontrado'; },
                searching: function () { return 'Buscando...'; }
            }
        });

        // Tipo
        $('#filtroTipo').select2({
            placeholder: 'Todos',
            allowClear: true,
            width: '100%',
            minimumResultsForSearch: Infinity // Desabilita busca (poucos itens)
        });
    }

    // ============================================
    // Funções auxiliares de data/hora
    // ============================================

    // ============================================
    // Funções auxiliares de data/hora
    // ============================================

    /**
     * Retorna datetime-local string para 2 dias atrás às 00:00:00
     */
    function getDataInicioPadrao() {
        const data = new Date();
        data.setDate(data.getDate() - 2);
        data.setHours(0, 0, 0, 0);
        return formatDateTimeLocal(data);
    }

    /**
     * Retorna datetime-local string para hoje às 23:59:59
     */
    function getDataFimPadrao() {
        const data = new Date();
        data.setHours(23, 59, 59, 0);
        return formatDateTimeLocal(data);
    }

    /**
     * Formata Date para string datetime-local (YYYY-MM-DDTHH:MM)
     * Nota: datetime-local não aceita segundos no value
     */
    function formatDateTimeLocal(date) {
        const ano = date.getFullYear();
        const mes = String(date.getMonth() + 1).padStart(2, '0');
        const dia = String(date.getDate()).padStart(2, '0');
        const hora = String(date.getHours()).padStart(2, '0');
        const min = String(date.getMinutes()).padStart(2, '0');
        return `${ano}-${mes}-${dia}T${hora}:${min}`;
    }

    /**
     * Formata data/hora para exibição pt-BR
     */
    function formatarDataHora(dataStr) {
        if (!dataStr) return '-';
        const data = new Date(dataStr);
        return data.toLocaleString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }

    // ============================================
    // Buscar logs via AJAX
    // ============================================
    function buscarLogs(pagina = 1) {
        const loading = document.getElementById('loadingOverlay');
        const tbody = document.getElementById('tabelaLogs');

        loading.classList.add('active');

        // Monta parâmetros - datetime-local já vem no formato correto
        const params = {
            pagina: pagina,
            porPagina: porPagina,
            dataInicio: document.getElementById('filtroDataInicio').value.replace('T', ' '),
            dataFim: document.getElementById('filtroDataFim').value.replace('T', ' '),
            cdUsuario: $('#filtroUsuario').val() || '',
            cdFuncionalidade: $('#filtroFuncionalidade').val() || '',
            cdUnidade: $('#filtroUnidade').val() || '',
            tipo: $('#filtroTipo').val() || '',
            busca: document.getElementById('filtroBusca').value
        };

        $.get('bd/log/getLogs.php', params, function (response) {
            loading.classList.remove('active');

            if (response.success) {
                estadoPaginacao = {
                    pagina: response.pagina,
                    total: response.total,
                    totalPaginas: response.totalPaginas
                };

                document.getElementById('countRegistros').innerHTML =
                    `<strong>${response.total.toLocaleString('pt-BR')}</strong> registro(s) encontrado(s)`;

                if (response.data.length > 0) {
                    let html = '';
                    response.data.forEach(item => {
                        const tipo = tiposLog[item.TP_LOG] || { nome: 'Desconhecido', classe: 'badge-default' };
                        const dataFormatada = formatarDataHora(item.DT_LOG);

                        html += `
                        <tr>
                            <td class="td-code">${item.CD_LOG}</td>
                            <td class="td-datetime">${dataFormatada}</td>
                            <td class="td-usuario">${escapeHtml(item.DS_USUARIO) || '-'}</td>
                            <td class="td-funcionalidade">${escapeHtml(item.DS_FUNCIONALIDADE) || '-'}</td>
                            <td class="td-unidade">${escapeHtml(item.DS_UNIDADE) || '-'}</td>
                            <td class="td-tipo"><span class="badge ${tipo.classe}">${tipo.nome}</span></td>
                            <td class="td-nome">${escapeHtml(item.NM_LOG) || '-'}</td>
                            <td class="td-acoes">
                                <button class="btn-icon" onclick="abrirDetalhes(${item.CD_LOG})" title="Ver detalhes">
                                    <ion-icon name="eye-outline"></ion-icon>
                                </button>
                            </td>
                        </tr>
                    `;
                    });
                    tbody.innerHTML = html;
                } else {
                    tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="empty-state">
                            <ion-icon name="document-text-outline"></ion-icon>
                            <p>Nenhum registro encontrado</p>
                        </td>
                    </tr>
                `;
                }
                renderizarPaginacao();
            } else {
                showToast(response.message || 'Erro ao buscar logs', 'erro');
            }
        }, 'json').fail(function (xhr) {
            loading.classList.remove('active');
            showToast('Erro ao comunicar com o servidor', 'erro');
            console.error('Erro AJAX:', xhr);
        });
    }

    // ============================================
    // Cache e detalhes do log
    // ============================================
    let logsCache = {};

    function abrirDetalhes(cdLog) {
        $.get('bd/log/getLogDetalhe.php', { cdLog: cdLog }, function (response) {
            if (response.success && response.data) {
                const log = response.data;
                const tipo = tiposLog[log.TP_LOG] || { nome: 'Desconhecido', classe: 'badge-default' };

                document.getElementById('detCodigo').textContent = log.CD_LOG;
                document.getElementById('detData').textContent = formatarDataHora(log.DT_LOG);
                document.getElementById('detUsuario').textContent = log.DS_USUARIO || '-';
                document.getElementById('detFuncionalidade').textContent = log.DS_FUNCIONALIDADE || '-';
                document.getElementById('detUnidade').textContent = log.DS_UNIDADE || '-';
                document.getElementById('detTipo').innerHTML = `<span class="badge ${tipo.classe}">${tipo.nome}</span>`;
                document.getElementById('detNome').textContent = log.NM_LOG || '-';
                document.getElementById('detDescricao').textContent = log.DS_LOG || '-';
                document.getElementById('detVersao').textContent = log.DS_VERSAO || '-';
                document.getElementById('detServidor').textContent = log.NM_SERVIDOR || '-';

                document.getElementById('modalDetalhes').classList.add('active');
            } else {
                showToast('Erro ao carregar detalhes', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    function fecharModalDetalhes(event) {
        if (!event || event.target === event.currentTarget) {
            document.getElementById('modalDetalhes').classList.remove('active');
        }
    }

    // ============================================
    // Paginação
    // ============================================
    function renderizarPaginacao() {
        const { pagina, total, totalPaginas } = estadoPaginacao;
        const inicio = Math.min((pagina - 1) * porPagina + 1, total);
        const fim = Math.min(pagina * porPagina, total);

        document.getElementById('paginationInfo').textContent =
            total > 0 ? `Mostrando ${inicio} a ${fim} de ${total.toLocaleString('pt-BR')}` : 'Nenhum registro';

        let paginationHtml = '';

        // Botão anterior
        paginationHtml += `
        <button class="page-btn" onclick="buscarLogs(${pagina - 1})" ${pagina <= 1 ? 'disabled' : ''}>
            <ion-icon name="chevron-back-outline"></ion-icon>
        </button>
    `;

        // Páginas
        const maxPages = 5;
        let startPage = Math.max(1, pagina - Math.floor(maxPages / 2));
        let endPage = Math.min(totalPaginas, startPage + maxPages - 1);

        if (endPage - startPage + 1 < maxPages) {
            startPage = Math.max(1, endPage - maxPages + 1);
        }

        if (startPage > 1) {
            paginationHtml += `<button class="page-btn" onclick="buscarLogs(1)">1</button>`;
            if (startPage > 2) {
                paginationHtml += `<span style="padding: 0 8px; color: #94a3b8;">...</span>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            paginationHtml += `
            <button class="page-btn ${i === pagina ? 'active' : ''}" onclick="buscarLogs(${i})">${i}</button>
        `;
        }

        if (endPage < totalPaginas) {
            if (endPage < totalPaginas - 1) {
                paginationHtml += `<span style="padding: 0 8px; color: #94a3b8;">...</span>`;
            }
            paginationHtml += `<button class="page-btn" onclick="buscarLogs(${totalPaginas})">${totalPaginas}</button>`;
        }

        // Botão próximo
        paginationHtml += `
        <button class="page-btn" onclick="buscarLogs(${pagina + 1})" ${pagina >= totalPaginas ? 'disabled' : ''}>
            <ion-icon name="chevron-forward-outline"></ion-icon>
        </button>
    `;

        document.getElementById('pagination').innerHTML = paginationHtml;
    }

    // ============================================
    // Limpar filtros (resetando Select2)
    // ============================================
    function limparFiltros() {
        // Resetar datas para padrão
        document.getElementById('filtroDataInicio').value = getDataInicioPadrao();
        document.getElementById('filtroDataFim').value = getDataFimPadrao();

        // Resetar Select2
        $('#filtroUsuario').val('').trigger('change');
        $('#filtroFuncionalidade').val('').trigger('change');
        $('#filtroUnidade').val('').trigger('change');
        $('#filtroTipo').val('').trigger('change');

        // Limpar campo de busca
        document.getElementById('filtroBusca').value = '';

        buscarLogs(1);
    }

    // ============================================
    // Funções auxiliares
    // ============================================
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
        <ion-icon name="${type === 'sucesso' ? 'checkmark-circle' : type === 'erro' ? 'alert-circle' : 'information-circle'}-outline"></ion-icon>
        <span>${message}</span>
    `;
        container.appendChild(toast);

        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // ============================================
    // Event listeners
    // ============================================
    document.getElementById('filtroBusca').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') buscarLogs(1);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            fecharModalDetalhes();
        }
    });

    // ============================================
    // Inicialização ao carregar a página
    // ============================================
    // Inicialização
    document.addEventListener('DOMContentLoaded', function () {
        // Inicializa Select2 nos dropdowns
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $('#filtroUsuario, #filtroFuncionalidade, #filtroUnidade, #filtroTipo').select2({
                placeholder: 'Todos',
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function () { return 'Nenhum resultado encontrado'; }
                }
            });
        }

        // Data fim: hoje às 23:59
        const hoje = new Date();
        const anoFim = hoje.getFullYear();
        const mesFim = String(hoje.getMonth() + 1).padStart(2, '0');
        const diaFim = String(hoje.getDate()).padStart(2, '0');
        document.getElementById('filtroDataFim').value = anoFim + '-' + mesFim + '-' + diaFim + 'T23:59';

        // Data início: 2 dias atrás às 00:00
        const inicio = new Date();
        inicio.setDate(inicio.getDate() - 2);
        const anoIni = inicio.getFullYear();
        const mesIni = String(inicio.getMonth() + 1).padStart(2, '0');
        const diaIni = String(inicio.getDate()).padStart(2, '0');
        document.getElementById('filtroDataInicio').value = anoIni + '-' + mesIni + '-' + diaIni + 'T00:00';

        buscarLogs(1);
    });
</script>

</body>

</html>
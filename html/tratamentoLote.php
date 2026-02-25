<?php
/**
 * SIMP 2.0 - Fase A2: Tratamento em Lote
 *
 * Tela para operadores revisarem e tratarem anomalias detectadas
 * pelo motor batch. Prioriza por impacto hidraulico e confianca.
 *
 * Funcionalidades:
 *   - Cards de resumo (pendentes, tratadas, tecnicas, operacionais)
 *   - Filtros avancados (data, status, classe, tipo, medidor, confianca)
 *   - Tabela com acoes rapidas (aprovar, ajustar, ignorar)
 *   - Acoes em massa (aprovar/ignorar selecionados)
 *   - Modal de detalhe com scores individuais
 *   - Reserva de area para contexto GNN (Fase B)
 *
 * @author  Bruno - CESAN
 * @version 1.0 - Fase A2
 * @date    2026-02
 */

$paginaAtual = 'tratamentoLote';

include_once 'includes/header.inc.php';
include_once 'bd/conexao.php';

// Permissao (mesma de Operacoes)
recarregarPermissoesUsuario();
exigePermissaoTela('Registro de Vazão', ACESSO_LEITURA);
$podeEditar = podeEditarTela('Registro de Vazão');

include_once 'includes/menu.inc.php';

// Buscar unidades para filtro
$unidades = [];
try {
    $stmtU = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME FROM SIMP.dbo.UNIDADE WHERE OP_ATIVO = 1 ORDER BY DS_NOME");
    $unidades = $stmtU->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* silencioso */
}
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
    /* ============================================
   TRATAMENTO EM LOTE - CSS
   ============================================ */
    .page-container {
        padding: 20px;
        max-width: 1600px;
        margin: 0 auto;
    }

    /* Header */
    .page-header {
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        border-radius: 14px;
        padding: 20px 24px;
        margin-bottom: 20px;
        color: white;
    }

    .page-header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }

    .page-header-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .page-header-icon {
        width: 42px;
        height: 42px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .page-header h1 {
        font-size: 18px;
        font-weight: 700;
        margin: 0 0 2px 0;
    }

    .page-header p {
        font-size: 11px;
        color: rgba(255, 255, 255, 0.7);
        margin: 0;
    }

    .header-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-header {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        background: rgba(255, 255, 255, 0.15);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 8px;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        transition: all .2s;
    }

    .btn-header:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    .btn-header.primary {
        background: rgba(34, 197, 94, 0.3);
        border-color: rgba(34, 197, 94, 0.5);
    }

    .btn-header.danger {
        background: rgba(239, 68, 68, 0.3);
        border-color: rgba(239, 68, 68, 0.5);
    }

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 16px 20px;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all .2s;
    }

    .stat-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        transform: translateY(-1px);
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .stat-icon.pendentes {
        background: #fef3c7;
        color: #d97706;
    }

    .stat-icon.tratadas {
        background: #dcfce7;
        color: #16a34a;
    }

    .stat-icon.tecnicas {
        background: #dbeafe;
        color: #2563eb;
    }

    .stat-icon.operacionais {
        background: #fee2e2;
        color: #dc2626;
    }

    .stat-icon.confianca {
        background: #f3e8ff;
        color: #7c3aed;
    }

    .stat-icon.pontos {
        background: #e0f2fe;
        color: #0284c7;
    }

    .stat-info h3 {
        font-size: 22px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        line-height: 1;
    }

    .stat-info p {
        font-size: 11px;
        color: #64748b;
        margin: 2px 0 0;
    }

    /* Filtros */
    .filtros-card {
        background: white;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        padding: 16px 20px;
        margin-bottom: 16px;
    }

    .filtros-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
        gap: 8px;
    }

    .filtros-header h3 {
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .filtros-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 12px;
        align-items: end;
    }

    .form-group label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .form-control {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        background: white;
        transition: all .2s;
        box-sizing: border-box;
    }

    .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .filtros-actions {
        display: flex;
        gap: 8px;
        align-items: end;
    }

    .btn-filtrar {
        padding: 8px 16px;
        background: linear-gradient(135deg, #1e3a5f, #2d5a87);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
        transition: all .2s;
    }

    .btn-filtrar:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(30, 58, 95, 0.3);
    }

    .btn-limpar {
        padding: 8px 12px;
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 12px;
        cursor: pointer;
        white-space: nowrap;
        transition: all .2s;
    }

    .btn-limpar:hover {
        background: #e2e8f0;
    }

    /* Barra de acoes em massa */
    .massa-bar {
        display: none;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 10px;
        padding: 10px 16px;
        margin-bottom: 12px;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .massa-bar.ativa {
        display: flex;
    }

    .massa-bar .sel-count {
        font-size: 13px;
        font-weight: 600;
        color: #1e40af;
    }

    .btn-massa {
        padding: 6px 14px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 4px;
        border: 1px solid transparent;
        transition: all .2s;
    }

    .btn-massa.aprovar {
        background: #dcfce7;
        color: #166534;
        border-color: #bbf7d0;
    }

    .btn-massa.aprovar:hover {
        background: #bbf7d0;
    }

    .btn-massa.ignorar {
        background: #fee2e2;
        color: #991b1b;
        border-color: #fecaca;
    }

    .btn-massa.ignorar:hover {
        background: #fecaca;
    }

    .btn-massa.limpar {
        background: #f1f5f9;
        color: #475569;
        border-color: #e2e8f0;
    }

    /* Tabela */
    .tabela-card {
        background: white;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }

    .tabela-header {
        padding: 12px 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8fafc;
    }

    .tabela-header h3 {
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        margin: 0;
    }

    .tabela-info {
        font-size: 11px;
        color: #94a3b8;
    }

    .tabela-wrapper {
        overflow-x: auto;
    }

    table.tbl-tratamento {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    table.tbl-tratamento thead th {
        padding: 10px 12px;
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        font-weight: 600;
        color: #475569;
        text-align: left;
        white-space: nowrap;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    table.tbl-tratamento tbody td {
        padding: 10px 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    table.tbl-tratamento tbody tr:hover {
        background: #f8fafc;
    }

    table.tbl-tratamento tbody tr.selecionada {
        background: #eff6ff;
    }

    /* Checkbox */
    .chk-sel {
        width: 16px;
        height: 16px;
        cursor: pointer;
        accent-color: #3b82f6;
    }

    /* Badges */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 3px 8px;
        border-radius: 100px;
        font-size: 10px;
        font-weight: 600;
        white-space: nowrap;
    }

    .badge.critica {
        background: #fee2e2;
        color: #991b1b;
    }

    .badge.alta {
        background: #ffedd5;
        color: #9a3412;
    }

    .badge.media {
        background: #fef3c7;
        color: #92400e;
    }

    .badge.baixa {
        background: #f0fdf4;
        color: #166534;
    }

    .badge.tecnica {
        background: #dbeafe;
        color: #1e40af;
    }

    .badge.operacional {
        background: #fce7f3;
        color: #9d174d;
    }

    .badge.conf-alta {
        background: #dcfce7;
        color: #166534;
    }

    .badge.conf-confiavel {
        background: #dbeafe;
        color: #1e40af;
    }

    .badge.conf-atencao {
        background: #fef3c7;
        color: #92400e;
    }

    .badge.pendente {
        background: #fef3c7;
        color: #92400e;
    }

    .badge.aprovada {
        background: #dcfce7;
        color: #166534;
    }

    .badge.ajustada {
        background: #dbeafe;
        color: #1e40af;
    }

    .badge.ignorada {
        background: #f1f5f9;
        color: #64748b;
    }

    /* Acoes rapidas */
    .acoes-rapidas {
        display: flex;
        gap: 4px;
    }

    .btn-acao {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        background: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        transition: all .2s;
        color: #64748b;
    }

    .btn-acao:hover {
        transform: scale(1.1);
    }

    .btn-acao.aprovar:hover {
        background: #dcfce7;
        color: #16a34a;
        border-color: #86efac;
    }

    .btn-acao.ajustar:hover {
        background: #dbeafe;
        color: #2563eb;
        border-color: #93c5fd;
    }

    .btn-acao.ignorar:hover {
        background: #fee2e2;
        color: #dc2626;
        border-color: #fca5a5;
    }

    .btn-acao.detalhe:hover {
        background: #f3e8ff;
        color: #7c3aed;
        border-color: #c4b5fd;
    }

    /* Paginacao */
    .paginacao {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 20px;
        border-top: 1px solid #e2e8f0;
        flex-wrap: wrap;
        gap: 8px;
    }

    .paginacao-info {
        font-size: 12px;
        color: #64748b;
    }

    .paginacao-btns {
        display: flex;
        gap: 4px;
    }

    .btn-pag {
        padding: 6px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        background: white;
        font-size: 12px;
        cursor: pointer;
        transition: all .2s;
        color: #475569;
    }

    .btn-pag:hover {
        background: #f1f5f9;
        border-color: #3b82f6;
        color: #2563eb;
    }

    .btn-pag.ativa {
        background: #1e3a5f;
        color: white;
        border-color: #1e3a5f;
    }

    .btn-pag:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    /* Valor real vs sugerido */
    .valor-comparacao {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .vl-real {
        font-weight: 600;
        color: #dc2626;
        font-family: 'SF Mono', monospace;
        font-size: 12px;
    }

    .vl-sugerido {
        font-weight: 500;
        color: #16a34a;
        font-family: 'SF Mono', monospace;
        font-size: 11px;
    }

    .vl-sugerido::before {
        content: '→ ';
        color: #94a3b8;
    }

    /* Score bar */
    .score-bar {
        width: 60px;
        height: 6px;
        background: #e2e8f0;
        border-radius: 3px;
        overflow: hidden;
        display: inline-block;
        vertical-align: middle;
        margin-right: 4px;
    }

    .score-bar-fill {
        height: 100%;
        border-radius: 3px;
        transition: width .3s;
    }

    .score-bar-fill.alta {
        background: #22c55e;
    }

    .score-bar-fill.confiavel {
        background: #3b82f6;
    }

    .score-bar-fill.atencao {
        background: #eab308;
    }

    /* Prioridade hidraulica */
    .prioridade-icon {
        font-size: 14px;
        margin-right: 2px;
    }

    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    }

    .empty-state ion-icon {
        font-size: 48px;
        margin-bottom: 12px;
    }

    .empty-state h3 {
        font-size: 16px;
        color: #475569;
        margin: 0 0 4px;
    }

    .empty-state p {
        font-size: 13px;
        margin: 0;
    }

    /* Loading */
    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 2px solid #e2e8f0;
        border-top-color: #3b82f6;
        border-radius: 50%;
        animation: spin .8s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
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
        z-index: 9000;
        align-items: center;
        justify-content: center;
    }

    .modal-overlay.ativo {
        display: flex;
    }

    .modal-box {
        background: white;
        border-radius: 14px;
        width: 95%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    .modal-header {
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        background: white;
        z-index: 1;
    }

    .modal-header h3 {
        font-size: 15px;
        font-weight: 600;
        color: #0f172a;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .modal-close {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        background: #f1f5f9;
        cursor: pointer;
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        transition: all .2s;
    }

    .modal-close:hover {
        background: #fee2e2;
        color: #dc2626;
    }

    .modal-body {
        padding: 20px;
    }

    .modal-footer {
        padding: 12px 20px;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        position: sticky;
        bottom: 0;
        background: white;
    }

    .btn-modal {
        padding: 8px 18px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        border: 1px solid transparent;
        transition: all .2s;
    }

    .btn-modal.cancelar {
        background: #f1f5f9;
        color: #475569;
        border-color: #e2e8f0;
    }

    .btn-modal.confirmar {
        background: linear-gradient(135deg, #1e3a5f, #2d5a87);
        color: white;
    }

    .btn-modal.confirmar:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(30, 58, 95, 0.3);
    }

    /* Detalhe - scores */
    .scores-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin: 12px 0;
    }

    .score-item {
        background: #f8fafc;
        border-radius: 8px;
        padding: 10px 14px;
    }

    .score-item label {
        font-size: 10px;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.3px;
        display: block;
        margin-bottom: 4px;
    }

    .score-item .score-valor {
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
    }

    /* Reserva GNN */
    .gnn-placeholder {
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
        padding: 16px;
        text-align: center;
        color: #94a3b8;
        margin-top: 12px;
    }

    .gnn-placeholder ion-icon {
        font-size: 24px;
        display: block;
        margin: 0 auto 6px;
    }

    .gnn-placeholder p {
        font-size: 11px;
        margin: 0;
    }

    /* Tipo medidor icones */
    .tipo-medidor-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: 500;
    }

    .tipo-medidor-badge.reservatorio {
        background: #dbeafe;
        color: #1e40af;
    }

    .tipo-medidor-badge.macro {
        background: #fef3c7;
        color: #92400e;
    }

    .tipo-medidor-badge.pitometrica {
        background: #fce7f3;
        color: #9d174d;
    }

    .tipo-medidor-badge.pressao {
        background: #e0f2fe;
        color: #0369a1;
    }

    .tipo-medidor-badge.hidrometro {
        background: #f0fdf4;
        color: #166534;
    }

    /* Textarea justificativa */
    .textarea-just {
        width: 100%;
        min-height: 80px;
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        resize: vertical;
        box-sizing: border-box;
        font-family: inherit;
    }

    .textarea-just:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Input valor ajuste */
    .input-valor {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        font-family: 'SF Mono', monospace;
        box-sizing: border-box;
    }

    .input-valor:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Info detalhe */
    .detalhe-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-bottom: 12px;
    }

    .detalhe-item {
        padding: 8px 12px;
        background: #f8fafc;
        border-radius: 6px;
    }

    .detalhe-item label {
        font-size: 10px;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 600;
        display: block;
        margin-bottom: 2px;
    }

    .detalhe-item span {
        font-size: 13px;
        font-weight: 500;
        color: #0f172a;
    }

    /* Select2 ajustes */
    .select2-container {
        width: 100% !important;
    }

    .select2-container .select2-selection--single {
        height: 36px !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
    }

    .select2-container .select2-selection--single .select2-selection__rendered {
        line-height: 36px !important;
        font-size: 13px;
        padding-left: 12px;
    }

    .select2-container .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
    }

    .select2-dropdown {
        border-radius: 8px !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1) !important;
    }

    .select2-search--dropdown .select2-search__field {
        border-radius: 6px !important;
        padding: 8px 10px !important;
    }

    .select2-container--open .select2-dropdown {
        z-index: 9999;
    }

    /* Responsivo */
    @media (max-width: 1024px) {
        .filtros-grid {
            grid-template-columns: repeat(3, 1fr);
        }

        .stats-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 768px) {
        .page-container {
            padding: 14px;
        }

        .page-header-content {
            flex-direction: column;
            align-items: stretch;
            text-align: center;
        }

        .header-actions {
            justify-content: center;
        }

        .filtros-grid {
            grid-template-columns: 1fr 1fr;
        }

        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }

        .massa-bar {
            flex-direction: column;
            text-align: center;
        }

        .paginacao {
            flex-direction: column;
            text-align: center;
        }

        .detalhe-grid,
        .scores-grid {
            grid-template-columns: 1fr;
        }

        .modal-box {
            width: 98%;
            max-width: none;
            margin: 8px;
        }
    }

    @media (max-width: 480px) {
        .filtros-grid {
            grid-template-columns: 1fr;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .filtros-actions {
            flex-direction: column;
        }

        .filtros-actions button {
            width: 100%;
            justify-content: center;
        }
    }

    /* Colunas ordenaveis */
    .th-sort {
        cursor: pointer;
        user-select: none;
        white-space: nowrap;
    }

    .th-sort:hover {
        color: #2563eb;
        background: #eff6ff;
    }

    .th-sort .sort-icon {
        font-size: 12px;
        vertical-align: middle;
        opacity: 0.4;
        margin-left: 2px;
    }

    .th-sort.asc .sort-icon,
    .th-sort.desc .sort-icon {
        opacity: 1;
        color: #2563eb;
    }

    /* Icone modelo treinado */
    .modelo-badge {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        font-size: 10px;
        color: #16a34a;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        padding: 1px 6px;
        border-radius: 4px;
        margin-left: 4px;
        vertical-align: middle;
    }

    .modelo-badge ion-icon {
        font-size: 11px;
    }

    /* Botao validacao */
    .btn-acao.validacao {
        background: #fffbeb;
        color: #b45309;
        border-color: #fde68a;
    }

    .btn-acao.validacao:hover {
        background: #fef3c7;
        color: #d97706;
        border-color: #fde68a;
    }

    /* Badge numerico de ordem de ordenacao multi-coluna */
    .sort-order {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: #2563eb;
        color: #fff;
        font-size: 9px;
        font-weight: 700;
        margin-left: 3px;
        vertical-align: middle;
    }

    /* Modal regras - padrao SIMP */
    .regra-tab {
        flex: 1;
        padding: 10px 16px;
        background: transparent;
        border: none;
        font-size: 13px;
        font-weight: 500;
        color: #64748b;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: all .2s;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
    }

    .regra-tab:hover {
        color: #1e3a5f;
        background: #eff6ff;
    }

    .regra-tab.active {
        color: #1e3a5f;
        font-weight: 600;
        border-bottom-color: #1e3a5f;
    }

    .regra-tab ion-icon {
        font-size: 16px;
    }

    .regra-secao {
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid #f1f5f9;
    }

    .regra-secao:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }

    .regra-secao h4 {
        margin: 0 0 10px;
        font-size: 14px;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .regra-formula {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 14px;
        font-family: 'SF Mono', Consolas, monospace;
        font-size: 12px;
        color: #334155;
        margin-bottom: 12px;
        text-align: center;
        font-weight: 500;
    }

    .regra-tabela {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    .regra-tabela th {
        background: #f8fafc;
        color: #475569;
        font-weight: 600;
        text-align: left;
        padding: 8px 10px;
        border-bottom: 2px solid #e2e8f0;
    }

    .regra-tabela td {
        padding: 7px 10px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        vertical-align: top;
    }

    .regra-tabela tr:hover td {
        background: #f8fafc;
    }

    .regra-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
    }

    .regra-badge.amarelo {
        background: #fef3c7;
        color: #92400e;
    }

    .regra-badge.vermelho {
        background: #fee2e2;
        color: #991b1b;
    }

    .regra-badge.azul {
        background: #dbeafe;
        color: #1e40af;
    }

    .regra-badge.roxo {
        background: #ede9fe;
        color: #5b21b6;
    }

    .regra-badge.verde {
        background: #dcfce7;
        color: #166534;
    }

    .regra-badge.laranja {
        background: #ffedd5;
        color: #9a3412;
    }

    .regra-badge.cinza {
        background: #f1f5f9;
        color: #475569;
    }

    .regra-badge-info {
        font-size: 11px;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 500;
    }

    .regra-badge-info.verde {
        background: #dcfce7;
        color: #166534;
    }

    .regra-badge-info.azul {
        background: #dbeafe;
        color: #1e40af;
    }

    .regra-badge-info.amarelo {
        background: #fef3c7;
        color: #92400e;
    }

    /* Glossario */
    .glossario-item {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        margin-bottom: 10px;
        overflow: hidden;
        transition: all .2s;
    }

    .glossario-item:hover {
        border-color: #93c5fd;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.08);
    }

    .glossario-termo {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        padding: 10px 14px;
        font-size: 13px;
        font-weight: 700;
        color: #1e3a5f;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .glossario-def {
        padding: 10px 14px;
    }

    .glossario-def p {
        font-size: 12px;
        color: #475569;
        margin: 0 0 6px;
        line-height: 1.5;
    }

    .glossario-def p:last-child {
        margin-bottom: 0;
    }

    .glossario-formula {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 6px 12px;
        font-family: 'SF Mono', Consolas, monospace;
        font-size: 12px;
        color: #334155;
        margin: 6px 0;
        display: inline-block;
    }

    #modalRegras .modal-container {
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: modalSlideIn 0.3s ease;
    overflow: hidden;
}

    .modal-overlay.active {
        display: flex;
    }
</style>

<div class="page-container">

    <!-- ============================================
         HEADER
         ============================================ -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="checkmark-done-outline"></ion-icon>
                </div>
                <div>
                    <h1>Tratamento em Lote</h1>
                    <p>Revisao e tratamento de anomalias detectadas pelo motor de analise</p>
                </div>
            </div>
            <div class="header-actions">
                <?php if ($podeEditar): ?>
                    <span id="infoBatch" style="font-size:11px;color:#64748b;margin-right:12px;display:none;">
                        <ion-icon name="time-outline" style="vertical-align:middle;margin-right:2px;"></ion-icon>
                        Ultimo batch: <strong id="dtUltimoBatch">-</strong>
                    </span>
                    <button class="btn-header" onclick="abrirRegras()" title="Regras de deteccao e classificacao">
                        <ion-icon name="book-outline"></ion-icon>
                        Regras
                    </button>
                    <button class="btn-header primary" onclick="executarBatch()" id="btnExecutarBatch"
                        title="Executar motor de analise para ontem">
                        <ion-icon name="play-outline"></ion-icon>
                        Executar Batch
                    </button>
                <?php endif; ?>
                <button class="btn-header" onclick="carregarEstatisticas(); carregarPendencias();"
                    title="Atualizar dados">
                    <ion-icon name="refresh-outline"></ion-icon>
                    Atualizar
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================
         CARDS DE RESUMO
         ============================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon pendentes"><ion-icon name="alert-circle-outline"></ion-icon></div>
            <div class="stat-info">
                <h3 id="stPendentes">-</h3>
                <p>Pendentes</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon tratadas"><ion-icon name="checkmark-circle-outline"></ion-icon></div>
            <div class="stat-info">
                <h3 id="stTratadas">-</h3>
                <p>Tratadas</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon tecnicas"><ion-icon name="construct-outline"></ion-icon></div>
            <div class="stat-info">
                <h3 id="stTecnicas">-</h3>
                <p>Correcao Tecnica</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon operacionais"><ion-icon name="warning-outline"></ion-icon></div>
            <div class="stat-info">
                <h3 id="stOperacionais">-</h3>
                <p>Evento Operacional</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon confianca"><ion-icon name="shield-checkmark-outline"></ion-icon></div>
            <div class="stat-info">
                <h3 id="stConfianca">-</h3>
                <p>Confianca Media</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon pontos"><ion-icon name="pin-outline"></ion-icon></div>
            <div class="stat-info">
                <h3 id="stPontos">-</h3>
                <p>Pontos Afetados</p>
            </div>
        </div>
    </div>

    <!-- ============================================
         FILTROS
         ============================================ -->
    <div class="filtros-card">
        <div class="filtros-header" style="display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;"><ion-icon name="funnel-outline"></ion-icon> Filtros</h3>
            <button class="btn-limpar" onclick="limparFiltros()" style="margin:0;">
                <ion-icon name="refresh-outline"></ion-icon> Limpar
            </button>
        </div>
        <div class="filtros-grid">
            <!-- Data -->
            <div class="form-group">
                <label>Data Referencia</label>
                <select id="filtroData" class="form-control filtro-select2">
                    <option value="">Carregando...</option>
                </select>
            </div>
            <!-- Status -->
            <div class="form-group">
                <label>Status</label>
                <select id="filtroStatus" class="form-control filtro-select2">
                    <option value="0">Pendentes</option>
                    <option value="todos">Todos</option>
                    <option value="1">Aprovadas</option>
                    <option value="2">Ajustadas</option>
                    <option value="3">Ignoradas</option>
                </select>
            </div>
            <!-- Classe -->
            <div class="form-group">
                <label>Classificacao</label>
                <select id="filtroClasse" class="form-control filtro-select2">
                    <option value="">Todas</option>
                    <option value="1">Correcao Tecnica</option>
                    <option value="2">Evento Operacional</option>
                </select>
            </div>
            <!-- Tipo anomalia -->
            <div class="form-group">
                <label>Tipo Anomalia</label>
                <select id="filtroTipoAnomalia" class="form-control filtro-select2">
                    <option value="">Todos</option>
                    <option value="1">Valor zerado</option>
                    <option value="2">Sensor travado</option>
                    <option value="3">Spike</option>
                    <option value="4">Desvio estatistico</option>
                    <option value="5">Padrao incomum</option>
                    <option value="6">Desvio modelo</option>
                    <option value="7">Gap comunicacao</option>
                    <option value="8">Fora de faixa</option>
                </select>
            </div>
            <!-- Tipo medidor -->
            <div class="form-group">
                <label>Tipo Medidor</label>
                <select id="filtroTipoMedidor" class="form-control filtro-select2">
                    <option value="">Todos</option>
                    <option value="6">Reservatorio</option>
                    <option value="1">Macromedidor</option>
                    <option value="2">Pitometrica</option>
                    <option value="4">Pressao</option>
                    <option value="8">Hidrometro</option>
                </select>
            </div>
            <!-- Unidade -->
            <div class="form-group">
                <label>Unidade</label>
                <select id="filtroUnidade" class="form-control filtro-select2">
                    <option value="">Todas</option>
                    <?php foreach ($unidades as $u): ?>
                        <option value="<?= $u['CD_UNIDADE'] ?>"><?= htmlspecialchars($u['DS_NOME']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Confianca minima -->
            <div class="form-group">
                <label>Confianca Minima</label>
                <select id="filtroConfianca" class="form-control filtro-select2">
                    <option value="">Qualquer</option>
                    <option value="0.95">Alta (>= 95%)</option>
                    <option value="0.85">Confiavel (>= 85%)</option>
                    <option value="0.70">Atencao (>= 70%)</option>
                </select>
            </div>
            <!-- Busca -->
            <div class="form-group">
                <label>Busca</label>
                <input type="text" id="filtroBusca" class="form-control" placeholder="Nome do ponto...">
            </div>
        </div>
    </div>

    <!-- ============================================
         BARRA DE ACOES EM MASSA
         ============================================ -->
    <?php if ($podeEditar): ?>
        <div class="massa-bar" id="massaBar">
            <span class="sel-count"><span id="massaCount">0</span> selecionado(s)</span>
            <button class="btn-massa aprovar" onclick="aprovarMassa()">
                <ion-icon name="checkmark-outline"></ion-icon> Aprovar
            </button>
            <button class="btn-massa ignorar" onclick="abrirIgnorarMassa()">
                <ion-icon name="close-outline"></ion-icon> Ignorar
            </button>
            <button class="btn-massa limpar" onclick="limparSelecao()">
                <ion-icon name="remove-circle-outline"></ion-icon> Limpar
            </button>
        </div>
    <?php endif; ?>

    <!-- ============================================
         TABELA DE PENDENCIAS
         ============================================ -->
    <div class="tabela-card">
        <div class="tabela-header">
            <h3 id="tabelaTitulo">Pendencias</h3>
            <span class="tabela-info" id="tabelaInfo">Carregando...</span>
        </div>
        <div class="tabela-wrapper">
            <table class="tbl-tratamento">
                <thead>
                    <tr>
                        <?php if ($podeEditar): ?>
                            <th style="width:36px"><input type="checkbox" class="chk-sel" id="chkTodos"
                                    onchange="toggleTodos(this)"></th>
                        <?php endif; ?>
                        <th class="th-sort" onclick="ordenarPor('ponto', event)">Ponto <ion-icon
                                name="swap-vertical-outline" class="sort-icon"></ion-icon></th>
                        <th class="th-sort" onclick="ordenarPor('data', event)">Data/Hora <ion-icon
                                name="swap-vertical-outline" class="sort-icon"></ion-icon></th>
                        <th class="th-sort" onclick="ordenarPor('tipo', event)">Tipo <ion-icon
                                name="swap-vertical-outline" class="sort-icon"></ion-icon></th>
                        <th>Classe</th>
                        <th class="th-sort" onclick="ordenarPor('severidade', event)">Severidade <ion-icon
                                name="swap-vertical-outline" class="sort-icon"></ion-icon></th>
                        <th>Valor Real / Sugerido</th>
                        <th class="th-sort" onclick="ordenarPor('confianca', event)">Confianca <ion-icon
                                name="swap-vertical-outline" class="sort-icon"></ion-icon></th>
                        <th class="th-sort" onclick="ordenarPor('status', event)">Status <ion-icon
                                name="swap-vertical-outline" class="sort-icon"></ion-icon></th>
                        <?php if ($podeEditar): ?>
                            <th style="width:160px">Acoes</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="tabelaBody">
                    <tr>
                        <td colspan="10" style="text-align:center;padding:40px;">
                            <div class="loading-spinner"></div>
                            <p style="margin:8px 0 0;color:#94a3b8;font-size:12px;">Carregando pendencias...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <!-- Paginacao -->
        <div class="paginacao" id="paginacao" style="display:none">
            <span class="paginacao-info" id="pagInfo"></span>
            <div class="paginacao-btns" id="pagBtns"></div>
        </div>
    </div>

    <!-- ============================================
         RESERVA AREA GNN (Fase B)
         ============================================ -->
    <div class="gnn-placeholder" style="margin-top:16px;">
        <ion-icon name="git-network-outline"></ion-icon>
        <p><strong>Contexto GNN (Fase B)</strong> — Aqui sera exibida a coerencia sistemica,
            nos vizinhos anomalos e propagacao de eventos quando o Graph Neural Network estiver ativo.</p>
    </div>

</div><!-- .page-container -->


<!-- ============================================
     MODAL: DETALHE DA PENDENCIA
     ============================================ -->
<div class="modal-overlay" id="modalDetalhe">
    <div class="modal-box" style="max-width:700px;">
        <div class="modal-header">
            <h3><ion-icon name="information-circle-outline"></ion-icon> Detalhe da Pendencia</h3>
            <button class="modal-close" onclick="fecharModal('modalDetalhe')">&times;</button>
        </div>
        <div class="modal-body" id="modalDetalheBody">
            <div style="text-align:center;padding:20px;">
                <div class="loading-spinner"></div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     MODAL: AJUSTAR VALOR
     ============================================ -->
<div class="modal-overlay" id="modalAjustar">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header">
            <h3><ion-icon name="create-outline"></ion-icon> Ajustar Valor</h3>
            <button class="modal-close" onclick="fecharModal('modalAjustar')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:12px;color:#64748b;margin:0 0 8px;">Ponto: <strong id="ajustarPonto"></strong> | Hora:
                <strong id="ajustarHora"></strong>
            </p>
            <div style="display:flex;gap:12px;margin-bottom:12px;">
                <div style="flex:1;background:#fee2e2;border-radius:8px;padding:10px;text-align:center;">
                    <div style="font-size:10px;color:#991b1b;text-transform:uppercase;font-weight:600;">Valor Real</div>
                    <div style="font-size:18px;font-weight:700;color:#dc2626;" id="ajustarVlReal">-</div>
                </div>
                <div style="flex:1;background:#dcfce7;border-radius:8px;padding:10px;text-align:center;">
                    <div style="font-size:10px;color:#166534;text-transform:uppercase;font-weight:600;">Sugerido</div>
                    <div style="font-size:18px;font-weight:700;color:#16a34a;" id="ajustarVlSugerido">-</div>
                </div>
            </div>
            <label style="font-size:12px;font-weight:600;color:#334155;display:block;margin-bottom:4px;">Novo
                valor:</label>
            <input type="number" step="0.0001" class="input-valor" id="ajustarValorInput"
                placeholder="Informe o valor corrigido">
        </div>
        <div class="modal-footer">
            <button class="btn-modal cancelar" onclick="fecharModal('modalAjustar')">Cancelar</button>
            <button class="btn-modal confirmar" onclick="confirmarAjuste()">Aplicar Ajuste</button>
        </div>
    </div>
</div>

<!-- ============================================
     MODAL: IGNORAR (justificativa)
     ============================================ -->
<div class="modal-overlay" id="modalIgnorar">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header">
            <h3><ion-icon name="eye-off-outline"></ion-icon> Ignorar Pendencia</h3>
            <button class="modal-close" onclick="fecharModal('modalIgnorar')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:12px;color:#64748b;margin:0 0 8px;" id="ignorarInfo"></p>
            <label style="font-size:12px;font-weight:600;color:#334155;display:block;margin-bottom:4px;">Justificativa
                (obrigatoria):</label>
            <textarea class="textarea-just" id="ignorarJustificativa" placeholder="Descreva o motivo..."></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn-modal cancelar" onclick="fecharModal('modalIgnorar')">Cancelar</button>
            <button class="btn-modal confirmar" onclick="confirmarIgnorar()">Confirmar</button>
        </div>
    </div>
</div>

<!-- ============================================
         MODAL: REGRAS DE DETECCAO E GLOSSARIO
         ============================================ -->
<div class="modal-overlay" id="modalRegras" onclick="if(event.target===this)fecharRegras()">
    <div class="modal-container" style="max-width:760px;max-height:90vh;display:flex;flex-direction:column;">

        <!-- Header padrao SIMP -->
        <div class="modal-header">
            <h3>
                <ion-icon name="book-outline"></ion-icon>
                Regras de Deteccao e Glossario
            </h3>
            <button class="modal-close" onclick="fecharRegras()">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>

        <!-- Tabs -->
        <div style="display:flex;gap:0;border-bottom:2px solid #e2e8f0;background:#f8fafc;">
            <button class="regra-tab active" onclick="trocarAbaRegra('regras', this)">
                <ion-icon name="shield-checkmark-outline"></ion-icon> Regras
            </button>
            <button class="regra-tab" onclick="trocarAbaRegra('glossario', this)">
                <ion-icon name="library-outline"></ion-icon> Glossario
            </button>
        </div>

        <!-- Body scrollavel -->
        <div class="modal-body" style="overflow-y:auto;flex:1;">

            <!-- ========== ABA REGRAS ========== -->
            <div id="abaRegras">

                <!-- Tipos de Anomalia -->
                <div class="regra-secao">
                    <h4><ion-icon name="warning-outline" style="color:#f59e0b;"></ion-icon> Tipos de Anomalia</h4>
                    <table class="regra-tabela">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Descricao</th>
                                <th>Criterio de Deteccao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="regra-badge amarelo">1</span></td>
                                <td>Valor zerado</td>
                                <td>Vazao = 0 em hora com historico &gt; 0 (exceto reservatorio)</td>
                            </tr>
                            <tr>
                                <td><span class="regra-badge amarelo">2</span></td>
                                <td>Sensor travado</td>
                                <td>Valor constante por 3+ horas consecutivas (desvio padrao = 0)</td>
                            </tr>
                            <tr>
                                <td><span class="regra-badge vermelho">3</span></td>
                                <td>Spike (extremo)</td>
                                <td>Valor &gt; 3x a media historica da hora ou variacao &gt; 200% entre horas</td>
                            </tr>
                            <tr>
                                <td><span class="regra-badge azul">4</span></td>
                                <td>Desvio estatistico</td>
                                <td>Z-score &gt; limiar dinamico (4.0 - sensibilidade x 2.5)</td>
                            </tr>
                            <tr>
                                <td><span class="regra-badge roxo">5</span></td>
                                <td>Padrao incomum</td>
                                <td>Autoencoder: erro de reconstrucao acima do threshold treinado</td>
                            </tr>
                            <tr>
                                <td><span class="regra-badge azul">6</span></td>
                                <td>Desvio do modelo</td>
                                <td>Diferenca entre valor real e predicao XGBoost &gt; 2x o MAE do modelo</td>
                            </tr>
                            <tr>
                                <td><span class="regra-badge cinza">7</span></td>
                                <td>Gap comunicacao</td>
                                <td>Sem registros na hora (0 de 60 minutos esperados)</td>
                            </tr>
                            <tr>
                                <td><span class="regra-badge vermelho">8</span></td>
                                <td>Fora de faixa</td>
                                <td>Valor fora dos limites operacionais configurados no ponto</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Classificacao -->
                <div class="regra-secao">
                    <h4><ion-icon name="git-branch-outline" style="color:#8b5cf6;"></ion-icon> Classificacao da Anomalia
                    </h4>
                    <table class="regra-tabela">
                        <thead>
                            <tr>
                                <th>Classe</th>
                                <th>Criterio</th>
                                <th>Acao Recomendada</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="regra-badge azul">Tecnica</span></td>
                                <td>Apenas este ponto diverge; vizinhos no grafo estao normais</td>
                                <td>Aprovar valor sugerido (falha isolada do sensor)</td>
                            </tr>
                            <tr>
                                <td><span class="regra-badge laranja">Operacional</span></td>
                                <td>Multiplos vizinhos tambem apresentam anomalia simultanea</td>
                                <td>Investigar antes de tratar (pode ser evento real na rede)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Score de Confianca -->
                <div class="regra-secao">
                    <h4><ion-icon name="shield-checkmark-outline" style="color:#16a34a;"></ion-icon> Score de Confianca
                        Composto</h4>
                    <div class="regra-formula">
                        0.30 &times; Estatistico + 0.30 &times; Modelo + 0.20 &times; Topologico + 0.10 &times;
                        Historico + 0.10 &times; Padrao
                    </div>
                    <table class="regra-tabela">
                        <thead>
                            <tr>
                                <th style="width:25%">Componente</th>
                                <th>Peso</th>
                                <th>O que mede</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Estatistico</strong></td>
                                <td>30%</td>
                                <td>Z-score normalizado — quanto o valor desvia da distribuicao historica</td>
                            </tr>
                            <tr>
                                <td><strong>Modelo</strong></td>
                                <td>30%</td>
                                <td>Diferenca entre valor real e predicao XGBoost (se modelo treinado)</td>
                            </tr>
                            <tr>
                                <td><strong>Topologico</strong></td>
                                <td>20%</td>
                                <td>Consistencia com vizinhos no grafo — se vizinhos estao normais, aumenta confianca
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Historico</strong></td>
                                <td>10%</td>
                                <td>Frequencia deste tipo de anomalia neste ponto (anomalias recorrentes = score maior)
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Padrao</strong></td>
                                <td>10%</td>
                                <td>Se anomalia similar ja foi aprovada antes no mesmo contexto</td>
                            </tr>
                        </tbody>
                    </table>
                    <div style="display:flex;gap:12px;margin-top:10px;flex-wrap:wrap;">
                        <div class="regra-badge-info verde">&ge; 95% — Alta (altissima certeza)</div>
                        <div class="regra-badge-info azul">&ge; 85% — Confiavel (recomendado aprovar)</div>
                        <div class="regra-badge-info amarelo">&ge; 70% — Atencao (revisar antes)</div>
                    </div>
                </div>

                <!-- Severidade -->
                <div class="regra-secao">
                    <h4><ion-icon name="flame-outline" style="color:#ef4444;"></ion-icon> Niveis de Severidade</h4>
                    <table class="regra-tabela">
                        <thead>
                            <tr>
                                <th>Severidade</th>
                                <th>Criterio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="regra-badge vermelho">Critica</span></td>
                                <td>Z-score &gt; 6 ou gap total de comunicacao</td>
                            </tr>
                            <tr>
                                <td><span class="regra-badge laranja">Alta</span></td>
                                <td>Z-score entre 4 e 6 ou spike extremo</td>
                            </tr>
                            <tr>
                                <td><span class="regra-badge amarelo">Media</span></td>
                                <td>Z-score entre 2.5 e 4 ou desvio moderado</td>
                            </tr>
                            <tr>
                                <td><span class="regra-badge cinza">Baixa</span></td>
                                <td>Z-score &lt; 2.5, desvio leve detectado pelo autoencoder</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Valor Sugerido -->
                <div class="regra-secao">
                    <h4><ion-icon name="calculator-outline" style="color:#0891b2;"></ion-icon> Calculo do Valor Sugerido
                    </h4>
                    <table class="regra-tabela">
                        <thead>
                            <tr>
                                <th style="width:15%">Prioridade</th>
                                <th>Metodo</th>
                                <th>Quando e usado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>1a</strong></td>
                                <td>Predicao XGBoost</td>
                                <td>Se o ponto possui modelo treinado com R&sup2; &gt; 0.5</td>
                            </tr>
                            <tr>
                                <td><strong>2a</strong></td>
                                <td>Media historica da hora</td>
                                <td>Fallback se nao ha modelo ou predicao indisponivel</td>
                            </tr>
                            <tr>
                                <td><strong>3a</strong></td>
                                <td>Valor esperado (detector)</td>
                                <td>Ultimo recurso — estimativa do detector de anomalias</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Prioridade Hidraulica -->
                <div class="regra-secao">
                    <h4><ion-icon name="water-outline" style="color:#2563eb;"></ion-icon> Prioridade Hidraulica</h4>
                    <table class="regra-tabela">
                        <thead>
                            <tr>
                                <th>Prioridade</th>
                                <th>Tipo</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>1a</strong></td>
                                <td>Reservatorio</td>
                                <td>Impacto direto no abastecimento — nivel critico afeta toda a rede</td>
                            </tr>
                            <tr>
                                <td><strong>2a</strong></td>
                                <td>Macromedidor</td>
                                <td>Grande volume — erro afeta balanco hidrico regional</td>
                            </tr>
                            <tr>
                                <td><strong>3a</strong></td>
                                <td>Pitometrica</td>
                                <td>Monitoramento de rede — referencia para calibracao</td>
                            </tr>
                            <tr>
                                <td><strong>4a</strong></td>
                                <td>Pressao</td>
                                <td>Indicador indireto — complementa analise de vazao</td>
                            </tr>
                            <tr>
                                <td><strong>5a</strong></td>
                                <td>Hidrometro</td>
                                <td>Ponto final da rede — impacto localizado</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Acoes de Tratamento -->
                <div class="regra-secao">
                    <h4><ion-icon name="checkmark-done-outline" style="color:#16a34a;"></ion-icon> Acoes de Tratamento
                    </h4>
                    <table class="regra-tabela">
                        <thead>
                            <tr>
                                <th>Acao</th>
                                <th>O que acontece</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="regra-badge verde">Aprovar</span></td>
                                <td>Inativa 60 registros da hora + insere 60 novos com valor sugerido. Registra usuario
                                    e observacao.</td>
                            </tr>
                            <tr>
                                <td><span class="regra-badge azul">Ajustar</span></td>
                                <td>Mesmo processo, mas com valor informado manualmente pelo operador.</td>
                            </tr>
                            <tr>
                                <td><span class="regra-badge cinza">Ignorar</span></td>
                                <td>Mantem dados originais. Justificativa obrigatoria para auditoria.</td>
                            </tr>
                        </tbody>
                    </table>
                    <p style="font-size:11px;color:#94a3b8;margin-top:8px;">
                        <ion-icon name="information-circle-outline" style="vertical-align:middle;"></ion-icon>
                        Todas as acoes registram: usuario, data/hora, observacao detalhada. Registros tratados recebem
                        ID_TIPO_REGISTRO = 2 (manual/IA).
                    </p>
                </div>
            </div>

            <!-- ========== ABA GLOSSARIO ========== -->
            <div id="abaGlossario" style="display:none;">

                <div class="regra-secao">
                    <h4><ion-icon name="library-outline" style="color:#6366f1;"></ion-icon> Dicionario de Termos
                        Tecnicos</h4>
                    <p style="font-size:12px;color:#64748b;margin-bottom:12px;">
                        Explicacao dos termos estatisticos e de inteligencia artificial utilizados nesta tela.
                    </p>
                </div>

                <div class="glossario-item">
                    <div class="glossario-termo">Z-Score</div>
                    <div class="glossario-def">
                        <p>Mede quantos <strong>desvios padrao</strong> um valor esta distante da media. Exemplo: se a
                            vazao media as 14h e 50 L/s com desvio padrao 5, um valor de 65 L/s tem Z-score = 3.0 (esta
                            3 desvios acima da media).</p>
                        <div class="glossario-formula">Z = (valor_real - media) / desvio_padrao</div>
                        <p><strong>De onde vem:</strong> calculado sobre o historico de 12+ semanas da mesma hora do
                            mesmo ponto. Quanto maior o Z-score, mais anomalo e o valor.</p>
                        <p><strong>Limiar dinamico:</strong> <code>4.0 - sensibilidade &times; 2.5</code>. Com
                            sensibilidade padrao (0.5), o limiar fica em 2.75.</p>
                    </div>
                </div>

                <div class="glossario-item">
                    <div class="glossario-termo">Threshold (Limiar)</div>
                    <div class="glossario-def">
                        <p>Valor de corte que separa "normal" de "anomalo". Se uma metrica ultrapassa o threshold, o
                            sistema classifica como anomalia. Cada metodo tem seu proprio threshold:</p>
                        <ul style="margin:6px 0;padding-left:20px;font-size:12px;color:#475569;">
                            <li><strong>Z-score:</strong> limiar dinamico (tipicamente 2.75)</li>
                            <li><strong>Autoencoder:</strong> percentil 95 do erro de reconstrucao no treino</li>
                            <li><strong>XGBoost:</strong> 2x o MAE historico do modelo</li>
                        </ul>
                    </div>
                </div>

                <div class="glossario-item">
                    <div class="glossario-termo">Desvio Padrao</div>
                    <div class="glossario-def">
                        <p>Medida de <strong>dispersao</strong> dos dados em relacao a media. Desvio padrao baixo =
                            dados concentrados; alto = dados espalhados. E a base do calculo do Z-score.</p>
                    </div>
                </div>

                <div class="glossario-item">
                    <div class="glossario-termo">XGBoost</div>
                    <div class="glossario-def">
                        <p>Algoritmo de <strong>machine learning</strong> baseado em arvores de decisao. Aprende padroes
                            historicos de cada ponto (hora do dia, dia da semana, correlacoes com vizinhos) para prever
                            o valor esperado. Se o valor real diverge muito da predicao, e detectada anomalia.</p>
                        <p><strong>Versao atual:</strong> v6.1 com features de shift temporal, delta e encoding ciclico.
                        </p>
                    </div>
                </div>

                <div class="glossario-item">
                    <div class="glossario-termo">Autoencoder</div>
                    <div class="glossario-def">
                        <p>Rede neural que aprende a <strong>comprimir e reconstruir</strong> dados normais. Quando
                            recebe um dado anomalo, nao consegue reconstrui-lo bem, gerando erro de reconstrucao alto.
                            Esse erro e comparado com o threshold treinado.</p>
                    </div>
                </div>

                <div class="glossario-item">
                    <div class="glossario-termo">R&sup2; (R-quadrado)</div>
                    <div class="glossario-def">
                        <p>Metrica de qualidade do modelo, varia de 0 a 1. Indica quanto da variacao dos dados o modelo
                            consegue explicar. R&sup2; = 0.85 significa que o modelo explica 85% da variabilidade. Acima
                            de 0.7 e considerado bom para dados de telemetria.</p>
                    </div>
                </div>

                <div class="glossario-item">
                    <div class="glossario-termo">MAE (Erro Medio Absoluto)</div>
                    <div class="glossario-def">
                        <p>Media das diferencas absolutas entre predicao e valor real. Exemplo: MAE = 3.5 L/s significa
                            que o modelo erra em media 3.5 L/s. Usado como referencia para definir o threshold de
                            anomalia do XGBoost (2x MAE).</p>
                    </div>
                </div>

                <div class="glossario-item">
                    <div class="glossario-termo">MAPE (Erro Percentual Medio)</div>
                    <div class="glossario-def">
                        <p>Mesma ideia do MAE, mas em <strong>percentual</strong>. MAPE = 15% significa que o modelo
                            erra em media 15% do valor real. Util para comparar performance entre pontos com escalas
                            diferentes.</p>
                    </div>
                </div>

                <div class="glossario-item">
                    <div class="glossario-termo">Score Topologico</div>
                    <div class="glossario-def">
                        <p>Analisa os <strong>vizinhos no grafo hidraulico</strong> (flowchart). Se apenas este ponto
                            esta anomalo mas vizinhos a montante e jusante estao normais, e provavel falha do sensor
                            (score alto). Se vizinhos tambem divergem, pode ser evento real na rede (score baixo).</p>
                    </div>
                </div>

                <div class="glossario-item">
                    <div class="glossario-termo">Fallback Estatistico</div>
                    <div class="glossario-def">
                        <p>Quando o ponto <strong>nao possui modelo ML treinado</strong>, o sistema usa media historica
                            e interpolacao como metodo alternativo de predicao. Indicado pelo badge sem o icone <span
                                class="modelo-badge" style="font-size:10px;"><ion-icon
                                    name="checkmark-circle"></ion-icon> ML</span>.</p>
                    </div>
                </div>

                <div class="glossario-item">
                    <div class="glossario-termo">Batch (Lote)</div>
                    <div class="glossario-def">
                        <p>Processamento automatico que analisa <strong>todos os pontos ativos</strong> de uma vez para
                            uma data especifica. Percorre cada ponto, chama o detector de anomalias + predicao,
                            classifica e gera pendencias. Tempo tipico: 2-5 minutos para ~53 pontos.</p>
                    </div>
                </div>

                <div class="glossario-item">
                    <div class="glossario-termo">MERGE / UPSERT</div>
                    <div class="glossario-def">
                        <p>Operacao de banco de dados que <strong>insere ou atualiza</strong>. Se ja existe pendencia
                            para o mesmo ponto+data+hora+tipo, atualiza os valores; se nao existe, insere nova. Garante
                            idempotencia — rodar o batch 2x nao duplica dados.</p>
                    </div>
                </div>

                <div class="glossario-item">
                    <div class="glossario-termo">Idempotente</div>
                    <div class="glossario-def">
                        <p>Operacao que pode ser executada <strong>multiplas vezes</strong> sem alterar o resultado alem
                            da primeira execucao. O batch e idempotente: rodar 3x para a mesma data gera o mesmo
                            resultado que rodar 1x.</p>
                    </div>
                </div>
            </div>

        </div><!-- /modal-body -->
    </div><!-- /modal-container -->
</div>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    /**
     * SIMP 2.0 - Tratamento em Lote (Frontend)
     * @author Bruno - CESAN
     * @version 1.0 - Fase A2
     */

    // ============================================
    // Variaveis globais
    // ============================================

    /** Permissao de edicao */
    const podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;

    /** Pagina atual */
    let paginaAtual = 1;
    let totalPaginas = 1;
    let totalRegistros = 0;
    const limitePorPagina = 50;

    /** Ordenacao atual (multi-coluna: Shift+click adiciona, click simples substitui) */
    let ordenacoes = [{ campo: 'prioridade', direcao: 'DESC' }];

    /** IDs selecionados para acao em massa */
    let idsSelecionados = [];

    /** Pontos com modelo ML treinado (carregado via TensorFlow) */
    var pontosComModelo = new Set();

    /** Pendencia sendo editada (modais) */
    let pendenciaAtual = null;

    /** Modo massa: 'individual' ou 'massa' */
    let modoIgnorar = 'individual';

    // ============================================
    // Inicializacao
    // ============================================

    document.addEventListener('DOMContentLoaded', function () {
        // Iniciar Select2 em todos os dropdowns de filtro
        inicializarSelect2();

        // Carregar dados iniciais
        carregarEstatisticas();

        // Carregar pontos com modelo treinado (silencioso)
        try {
            fetch('bd/operacoes/predicaoTensorFlow.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ acao: 'status' })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.modelos) {
                        data.modelos.forEach(m => {
                            if (m.cd_ponto) pontosComModelo.add(parseInt(m.cd_ponto));
                        });
                    }
                })
                .catch(() => { });
        } catch (e) { }

        // Busca com debounce (filtra automaticamente ao digitar)
        let buscaTimeout = null;
        document.getElementById('filtroBusca').addEventListener('input', function () {
            clearTimeout(buscaTimeout);
            buscaTimeout = setTimeout(function () {
                paginaAtual = 1;
                carregarPendencias();
            }, 400);
        });
    });

    function inicializarSelect2() {
        $('.filtro-select2').select2({
            width: '100%',
            minimumResultsForSearch: 0,
            language: {
                noResults: function () { return 'Nenhum resultado'; }
            }
        });

        // Autofocus no input de pesquisa ao abrir qualquer dropdown Select2
        $('.filtro-select2').on('select2:open', function () {
            setTimeout(function () {
                var searchField = document.querySelector('.select2-container--open .select2-search__field');
                if (searchField) searchField.focus();
            }, 50);
        });

        // Disparar filtro automaticamente ao mudar qualquer select
        $('.filtro-select2').on('change', function () {
            paginaAtual = 1;
            carregarPendencias();
        });
    }


    // ============================================
    // Carregar Estatisticas
    // ============================================

    /**
     * Busca estatisticas e preenche cards + dropdown de datas.
     */
    function carregarEstatisticas() {
        fetch('bd/operacoes/tratamentoLote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'estatisticas' })
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;

                const r = data.resumo || {};

                // Cards
                document.getElementById('stPendentes').textContent = r.PENDENTES || 0;
                document.getElementById('stTratadas').textContent = r.TRATADAS || 0;
                document.getElementById('stTecnicas').textContent = r.TECNICAS_PENDENTES || 0;
                document.getElementById('stOperacionais').textContent = r.OPERACIONAIS_PENDENTES || 0;
                document.getElementById('stConfianca').textContent = r.CONFIANCA_MEDIA_PENDENTES
                    ? (parseFloat(r.CONFIANCA_MEDIA_PENDENTES) * 100).toFixed(1) + '%' : '-';
                document.getElementById('stPontos').textContent = r.PONTOS_AFETADOS || 0;

                // Data do ultimo batch
                if (data.dt_ultimo_batch) {
                    const dtBatch = new Date(data.dt_ultimo_batch);
                    const pad = n => String(n).padStart(2, '0');
                    document.getElementById('dtUltimoBatch').textContent =
                        pad(dtBatch.getDate()) + '/' + pad(dtBatch.getMonth() + 1) + '/' + dtBatch.getFullYear() +
                        ' ' + pad(dtBatch.getHours()) + ':' + pad(dtBatch.getMinutes());
                    document.getElementById('infoBatch').style.display = 'inline-flex';
                }

                // Dropdown de datas
                const selData = document.getElementById('filtroData');
                const dataAtual = $(selData).val();

                // Destruir Select2 temporariamente para atualizar options
                $(selData).select2('destroy');
                selData.innerHTML = '';

                if (data.datas_disponiveis && data.datas_disponiveis.length > 0) {
                    data.datas_disponiveis.forEach(d => {
                        const dt = d.DT_REFERENCIA ? d.DT_REFERENCIA.split('T')[0] : d.DT_REFERENCIA;
                        const opt = document.createElement('option');
                        opt.value = dt;
                        opt.textContent = formatarData(dt) + ' (' + d.QTD + ')';
                        selData.appendChild(opt);
                    });
                    // Manter data selecionada ou selecionar a primeira
                    if (dataAtual && selData.querySelector('option[value="' + dataAtual + '"]')) {
                        selData.value = dataAtual;
                    }
                } else {
                    selData.innerHTML = '<option value="">Nenhuma data</option>';
                }

                // Reiniciar Select2
                $(selData).select2({
                    width: '100%',
                    minimumResultsForSearch: 0,
                    language: { noResults: function () { return 'Nenhum resultado'; } }
                });
                $(selData).on('select2:open', function () {
                    setTimeout(function () {
                        var sf = document.querySelector('.select2-container--open .select2-search__field');
                        if (sf) sf.focus();
                    }, 50);
                });

                // Carregar pendencias com a data selecionada
                carregarPendencias();
            })
            .catch(err => {
                console.error('Erro ao carregar estatisticas:', err);
            });
    }


    // ============================================
    // Carregar Pendencias
    // ============================================

    /**
     * Busca pendencias com filtros e paginacao.
     */
    function carregarPendencias() {
        const filtros = {
            acao: 'listar',
            data: $('#filtroData').val() || '',
            status: $('#filtroStatus').val() || '0',
            classe: $('#filtroClasse').val() || '',
            tipo_anomalia: $('#filtroTipoAnomalia').val() || '',
            tipo_medidor: $('#filtroTipoMedidor').val() || '',
            unidade: $('#filtroUnidade').val() || '',
            confianca_min: $('#filtroConfianca').val() || '',
            busca: document.getElementById('filtroBusca').value || '',
            pagina: paginaAtual,
            limite: limitePorPagina,
            ordenar: ordenacoes.map(o => o.campo).join(','),
            direcao: ordenacoes.map(o => o.direcao).join(',')
        };

        // Loading
        document.getElementById('tabelaBody').innerHTML = `
        <tr><td colspan="10" style="text-align:center;padding:40px;">
            <div class="loading-spinner"></div>
            <p style="margin:8px 0 0;color:#94a3b8;font-size:12px;">Buscando pendencias...</p>
        </td></tr>
    `;

        fetch('bd/operacoes/tratamentoLote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(filtros)
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    document.getElementById('tabelaBody').innerHTML = `
                <tr><td colspan="10" class="empty-state">
                    <ion-icon name="warning-outline"></ion-icon>
                    <h3>Erro</h3><p>${data.error || 'Erro ao carregar'}</p>
                </td></tr>
            `;
                    return;
                }

                totalRegistros = data.total || 0;
                totalPaginas = data.paginas || 1;

                document.getElementById('tabelaTitulo').textContent =
                    'Pendencias' + (filtros.data ? ' — ' + formatarData(filtros.data) : '');
                document.getElementById('tabelaInfo').textContent =
                    totalRegistros + ' registro(s)';

                renderizarTabela(data.pendencias || []);
                renderizarPaginacao();
                limparSelecao();
            })
            .catch(err => {
                document.getElementById('tabelaBody').innerHTML = `
            <tr><td colspan="10" class="empty-state">
                <ion-icon name="cloud-offline-outline"></ion-icon>
                <h3>Erro de conexao</h3><p>${err.message}</p>
            </td></tr>
        `;
            });
    }


    // ============================================
    // Renderizar Tabela
    // ============================================

    /**
     * Monta o HTML da tabela com as pendencias.
     * @param {Array} pendencias - Lista de pendencias do backend
     */
    function renderizarTabela(pendencias) {
        const tbody = document.getElementById('tabelaBody');

        if (!pendencias.length) {
            tbody.innerHTML = `
            <tr><td colspan="10" class="empty-state">
                <ion-icon name="checkmark-done-outline" style="color:#22c55e;"></ion-icon>
                <h3>Nenhuma pendencia</h3>
                <p>Nenhum registro encontrado com os filtros aplicados</p>
            </td></tr>
        `;
            return;
        }

        let html = '';
        pendencias.forEach(p => {
            const cd = p.CD_CHAVE;
            const selecionada = idsSelecionados.includes(cd) ? ' selecionada' : '';

            // Tipo medidor badge
            const tipoMedBadge = getTipoMedidorBadge(p.ID_TIPO_MEDIDOR);

            // Severidade badge
            const sevBadge = `<span class="badge ${p.DS_SEVERIDADE}">${ucfirst(p.DS_SEVERIDADE)}</span>`;

            // Classe badge
            const classeBadge = p.ID_CLASSE_ANOMALIA == 1
                ? '<span class="badge tecnica">Tecnica</span>'
                : '<span class="badge operacional">Operacional</span>';

            // Confianca
            const conf = parseFloat(p.VL_CONFIANCA || 0);
            const confPct = (conf * 100).toFixed(1);
            const confNivel = conf >= 0.95 ? 'alta' : (conf >= 0.85 ? 'confiavel' : 'atencao');
            const confBadge = `<span class="badge conf-${confNivel}">${confPct}%</span>`;
            const confBar = `<div class="score-bar"><div class="score-bar-fill ${confNivel}" style="width:${confPct}%"></div></div>`;

            // Status
            const statusMap = { 0: 'pendente', 1: 'aprovada', 2: 'ajustada', 3: 'ignorada' };
            const statusNome = statusMap[p.ID_STATUS] || 'pendente';
            const statusBadge = `<span class="badge ${statusNome}">${ucfirst(statusNome)}</span>`;

            // Valores
            const vlReal = p.VL_REAL != null ? parseFloat(p.VL_REAL).toFixed(2) : '-';
            const vlSug = p.VL_SUGERIDO != null ? parseFloat(p.VL_SUGERIDO).toFixed(2) : '-';

            // Hora formatada
            const hora = p.DS_HORA_FORMATADA || (String(p.NR_HORA).padStart(2, '0') + ':00');

            // Tipo anomalia
            const tipoAnom = p.DS_TIPO_ANOMALIA || 'Outro';

            html += `<tr class="${selecionada}" data-cd="${cd}">`;

            if (podeEditar) {
                html += `<td><input type="checkbox" class="chk-sel chk-item" data-cd="${cd}" ${idsSelecionados.includes(cd) ? 'checked' : ''} onchange="toggleSelecao(${cd}, this.checked)"></td>`;
            }

            // Verifica modelo treinado via TensorFlow (mesmo padrao do operacoes.php)
            const temModelo = pontosComModelo.has(parseInt(p.CD_PONTO_MEDICAO));
            const modeloBadge = temModelo
                ? '<span class="modelo-badge" title="Modelo ML treinado"><ion-icon name="checkmark-circle"></ion-icon> ML</span>'
                : '';

            html += `
            <td>
                ${tipoMedBadge} ${modeloBadge}
                <div style="font-weight:600;font-size:12px;margin-top:2px;" title="${p.DS_PONTO_NOME || ''}">${truncar(p.DS_PONTO_NOME || '-', 30)}</div>
                <div style="font-size:10px;color:#94a3b8;font-family:monospace;">${p.DS_CODIGO_FORMATADO || ''}</div>
            </td>
            <td>
                <div style="font-weight:500;">${formatarData(p.DT_REFERENCIA)}</div>
                <div style="font-size:12px;color:#475569;font-weight:600;">${hora}</div>
            </td>
            <td><span style="font-size:11px;">${tipoAnom}</span></td>
            <td>${classeBadge}</td>
            <td>${sevBadge}</td>
            <td>
                <div class="valor-comparacao">
                    <span class="vl-real">${vlReal}</span>
                    ${vlSug !== '-' ? '<span class="vl-sugerido">' + vlSug + '</span>' : ''}
                </div>
            </td>
            <td>${confBar} ${confBadge}</td>
            <td>${statusBadge}</td>
        `;

            if (podeEditar) {
                const desabilitado = p.ID_STATUS != 0 ? ' style="opacity:0.3;pointer-events:none;"' : '';
                html += `
                <td>
                    <div class="acoes-rapidas"${desabilitado}>
                        <button class="btn-acao aprovar" onclick="aprovarUm(${cd})" title="Aprovar (aplicar valor sugerido)">
                            <ion-icon name="checkmark-outline"></ion-icon>
                        </button>
                        <button class="btn-acao ajustar" onclick="abrirAjustar(${cd})" title="Ajustar valor">
                            <ion-icon name="create-outline"></ion-icon>
                        </button>
                        <button class="btn-acao ignorar" onclick="abrirIgnorar(${cd})" title="Ignorar">
                            <ion-icon name="eye-off-outline"></ion-icon>
                        </button>
                        <button class="btn-acao detalhe" onclick="abrirDetalhe(${cd})" title="Ver detalhes" style="opacity:1;pointer-events:auto;">
                            <ion-icon name="information-circle-outline"></ion-icon>
                        </button>
                        <button class="btn-acao validacao" onclick="irParaValidacao(${p.CD_PONTO_MEDICAO}, '${(p.DT_REFERENCIA || '').split('T')[0]}')" title="Abrir Validacao de Dados" style="opacity:1;pointer-events:auto;">
                            <ion-icon name="open-outline"></ion-icon>
                        </button>
                    </div>
                </td>
            `;
            }

            html += '</tr>';
        });

        tbody.innerHTML = html;
    }


    // ============================================
    // Paginacao
    // ============================================

    function renderizarPaginacao() {
        const container = document.getElementById('paginacao');
        if (totalPaginas <= 1) { container.style.display = 'none'; return; }
        container.style.display = 'flex';

        const ini = (paginaAtual - 1) * limitePorPagina + 1;
        const fim = Math.min(paginaAtual * limitePorPagina, totalRegistros);
        document.getElementById('pagInfo').textContent = `${ini}-${fim} de ${totalRegistros}`;

        let btnsHtml = '';
        btnsHtml += `<button class="btn-pag" onclick="irPagina(${paginaAtual - 1})" ${paginaAtual <= 1 ? 'disabled' : ''}>&laquo;</button>`;

        const maxBtns = 5;
        let inicio = Math.max(1, paginaAtual - Math.floor(maxBtns / 2));
        let fimPag = Math.min(totalPaginas, inicio + maxBtns - 1);
        if (fimPag - inicio < maxBtns - 1) inicio = Math.max(1, fimPag - maxBtns + 1);

        for (let i = inicio; i <= fimPag; i++) {
            btnsHtml += `<button class="btn-pag ${i === paginaAtual ? 'ativa' : ''}" onclick="irPagina(${i})">${i}</button>`;
        }

        btnsHtml += `<button class="btn-pag" onclick="irPagina(${paginaAtual + 1})" ${paginaAtual >= totalPaginas ? 'disabled' : ''}>&raquo;</button>`;

        document.getElementById('pagBtns').innerHTML = btnsHtml;
    }

    function irPagina(p) {
        if (p < 1 || p > totalPaginas) return;
        paginaAtual = p;
        carregarPendencias();
    }

    /**
     * Ordenar por coluna.
     * Click simples = substitui ordenacao.
     * Shift+Click = adiciona/altera coluna secundaria.
     */
    function ordenarPor(campo, event) {
        if (event && event.shiftKey) {
            // Shift+Click: adicionar ou alternar coluna existente
            const idx = ordenacoes.findIndex(o => o.campo === campo);
            if (idx >= 0) {
                ordenacoes[idx].direcao = ordenacoes[idx].direcao === 'DESC' ? 'ASC' : 'DESC';
            } else {
                ordenacoes.push({ campo: campo, direcao: 'DESC' });
            }
        } else {
            // Click simples: se ja e a unica, alterna direcao; senao substitui
            if (ordenacoes.length === 1 && ordenacoes[0].campo === campo) {
                ordenacoes[0].direcao = ordenacoes[0].direcao === 'DESC' ? 'ASC' : 'DESC';
            } else {
                ordenacoes = [{ campo: campo, direcao: 'DESC' }];
            }
        }

        // Atualizar visual dos headers
        document.querySelectorAll('.th-sort').forEach(th => {
            th.classList.remove('asc', 'desc');
            const badge = th.querySelector('.sort-order');
            if (badge) badge.remove();
        });
        ordenacoes.forEach((o, i) => {
            const th = document.querySelector(`.th-sort[onclick*="'${o.campo}'"]`);
            if (th) {
                th.classList.add(o.direcao.toLowerCase());
                if (ordenacoes.length > 1) {
                    const badge = document.createElement('span');
                    badge.className = 'sort-order';
                    badge.textContent = i + 1;
                    th.appendChild(badge);
                }
            }
        });

        paginaAtual = 1;
        carregarPendencias();
    }


    // ============================================
    // Selecao em massa
    // ============================================

    function toggleSelecao(cd, checked) {
        if (checked && !idsSelecionados.includes(cd)) {
            idsSelecionados.push(cd);
        } else if (!checked) {
            idsSelecionados = idsSelecionados.filter(x => x !== cd);
        }
        atualizarMassaBar();
        // Highlight na linha
        const tr = document.querySelector(`tr[data-cd="${cd}"]`);
        if (tr) tr.classList.toggle('selecionada', checked);
    }

    function toggleTodos(chkTodos) {
        document.querySelectorAll('.chk-item').forEach(chk => {
            const cd = parseInt(chk.dataset.cd);
            chk.checked = chkTodos.checked;
            toggleSelecao(cd, chkTodos.checked);
        });
    }

    function limparSelecao() {
        idsSelecionados = [];
        document.querySelectorAll('.chk-item').forEach(chk => chk.checked = false);
        document.querySelectorAll('tr.selecionada').forEach(tr => tr.classList.remove('selecionada'));
        const chkTodos = document.getElementById('chkTodos');
        if (chkTodos) chkTodos.checked = false;
        atualizarMassaBar();
    }

    function atualizarMassaBar() {
        const bar = document.getElementById('massaBar');
        if (!bar) return;
        if (idsSelecionados.length > 0) {
            bar.classList.add('ativa');
            document.getElementById('massaCount').textContent = idsSelecionados.length;
        } else {
            bar.classList.remove('ativa');
        }
    }


    // ============================================
    // Acoes individuais
    // ============================================

    /**
     * Aprova uma pendencia (aplica valor sugerido).
     */
    function aprovarUm(cd) {
        if (!confirm('Aprovar esta pendencia? O valor sugerido sera aplicado.')) return;
        chamarTratamento({ acao: 'aprovar', cd_pendencia: cd });
    }

    /**
     * Abre modal para ajustar valor.
     */
    function abrirAjustar(cd) {
        // Buscar dados da linha
        const tr = document.querySelector(`tr[data-cd="${cd}"]`);
        pendenciaAtual = cd;

        // Preencher modal
        document.getElementById('ajustarPonto').textContent = tr ? tr.querySelector('td:nth-child(2) div').textContent : cd;
        document.getElementById('ajustarHora').textContent = tr ? tr.querySelector('td:nth-child(3) div:nth-child(2)').textContent : '';

        const vlRealEl = tr ? tr.querySelector('.vl-real') : null;
        const vlSugEl = tr ? tr.querySelector('.vl-sugerido') : null;
        document.getElementById('ajustarVlReal').textContent = vlRealEl ? vlRealEl.textContent : '-';
        document.getElementById('ajustarVlSugerido').textContent = vlSugEl ? vlSugEl.textContent : '-';

        // Pre-preencher input com sugerido
        const vlSug = vlSugEl ? vlSugEl.textContent.replace('→ ', '') : '';
        document.getElementById('ajustarValorInput').value = vlSug !== '-' ? vlSug : '';

        abrirModal('modalAjustar');
        setTimeout(() => document.getElementById('ajustarValorInput').focus(), 200);
    }

    function confirmarAjuste() {
        const valor = parseFloat(document.getElementById('ajustarValorInput').value);
        if (isNaN(valor)) { alert('Informe um valor valido'); return; }
        fecharModal('modalAjustar');
        chamarTratamento({ acao: 'ajustar', cd_pendencia: pendenciaAtual, valor: valor });
    }

    /**
     * Abre modal para ignorar (justificativa obrigatoria).
     */
    function abrirIgnorar(cd) {
        modoIgnorar = 'individual';
        pendenciaAtual = cd;
        document.getElementById('ignorarInfo').textContent = 'Pendencia #' + cd;
        document.getElementById('ignorarJustificativa').value = '';
        abrirModal('modalIgnorar');
        setTimeout(() => document.getElementById('ignorarJustificativa').focus(), 200);
    }

    function confirmarIgnorar() {
        const just = document.getElementById('ignorarJustificativa').value.trim();
        if (!just) { alert('Justificativa obrigatoria'); return; }
        fecharModal('modalIgnorar');

        if (modoIgnorar === 'massa') {
            // Ignorar em massa
            fetch('bd/operacoes/tratamentoLote.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ acao: 'ignorar_massa', ids: idsSelecionados, justificativa: just })
            })
                .then(r => r.json())
                .then(d => {
                    if (d.success) { toast(d.message, 'ok'); limparSelecao(); carregarEstatisticas(); }
                    else toast(d.error || 'Erro', 'err');
                })
                .catch(() => toast('Erro de conexao', 'err'));
        } else {
            chamarTratamento({ acao: 'ignorar', cd_pendencia: pendenciaAtual, justificativa: just });
        }
    }


    // ============================================
    // Acoes em massa
    // ============================================

    function aprovarMassa() {
        if (!idsSelecionados.length) return;
        if (!confirm(`Aprovar ${idsSelecionados.length} pendencia(s)?`)) return;

        fetch('bd/operacoes/tratamentoLote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'aprovar_massa', ids: idsSelecionados })
        })
            .then(r => r.json())
            .then(d => {
                if (d.success) { toast(d.message, 'ok'); limparSelecao(); carregarEstatisticas(); }
                else toast(d.error || 'Erro', 'err');
            })
            .catch(() => toast('Erro de conexao', 'err'));
    }

    function abrirIgnorarMassa() {
        if (!idsSelecionados.length) return;
        modoIgnorar = 'massa';
        document.getElementById('ignorarInfo').textContent = idsSelecionados.length + ' pendencia(s) selecionada(s)';
        document.getElementById('ignorarJustificativa').value = '';
        abrirModal('modalIgnorar');
        setTimeout(() => document.getElementById('ignorarJustificativa').focus(), 200);
    }


    // ============================================
    // Chamar tratamento generico
    // ============================================

    function chamarTratamento(dados) {
        fetch('bd/operacoes/tratamentoLote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    toast(d.message || 'Tratamento aplicado', 'ok');
                    carregarEstatisticas(); // Recarrega stats + tabela
                } else {
                    toast(d.error || 'Erro ao aplicar tratamento', 'err');
                }
            })
            .catch(() => toast('Erro de conexao', 'err'));
    }


    // ============================================
    // Detalhe
    // ============================================

    function abrirDetalhe(cd) {
        abrirModal('modalDetalhe');
        document.getElementById('modalDetalheBody').innerHTML =
            '<div style="text-align:center;padding:30px;"><div class="loading-spinner"></div></div>';

        fetch('bd/operacoes/tratamentoLote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'detalhe', cd_pendencia: cd })
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    document.getElementById('modalDetalheBody').innerHTML =
                        '<p style="color:#dc2626;text-align:center;">' + (data.error || 'Erro') + '</p>';
                    return;
                }

                const p = data.pendencia || {};
                const s = data.scores || {};
                const outras = data.outras_horas || [];

                let html = '';

                // Info basica
                html += '<div class="detalhe-grid">';
                html += detalheItem('Ponto', p.DS_PONTO_NOME || '-');
                html += detalheItem('Codigo', p.DS_CODIGO_FORMATADO || '-');
                html += detalheItem('Data', formatarData(p.DT_REFERENCIA));
                html += detalheItem('Hora', (String(p.NR_HORA || 0).padStart(2, '0')) + ':00');
                html += detalheItem('Tipo Anomalia', p.DS_TIPO_ANOMALIA || '-');
                html += detalheItem('Classe', p.DS_CLASSE_ANOMALIA || '-');
                html += detalheItem('Severidade', ucfirst(p.DS_SEVERIDADE || '-'));
                html += detalheItem('Metodo', ucfirst(p.DS_METODO_DETECCAO || '-'));
                html += detalheItem('Valor Real', fmtNum(p.VL_REAL));
                html += detalheItem('Valor Sugerido', fmtNum(p.VL_SUGERIDO));
                html += detalheItem('Media Historica', fmtNum(p.VL_MEDIA_HISTORICA));
                html += detalheItem('Predicao XGBoost', fmtNum(p.VL_PREDICAO_XGBOOST));
                html += detalheItem('Z-Score', fmtNum(p.VL_ZSCORE, 2));
                html += detalheItem('Vizinhos Anomalos', p.QTD_VIZINHOS_ANOMALOS || 0);
                html += '</div>';

                // Descricao
                if (p.DS_DESCRICAO) {
                    html += `<div style="background:#f8fafc;border-radius:8px;padding:10px 14px;margin-bottom:12px;">
                <label style="font-size:10px;color:#64748b;text-transform:uppercase;font-weight:600;">Descricao</label>
                <p style="font-size:12px;color:#334155;margin:4px 0 0;">${escapeHtml(p.DS_DESCRICAO)}</p>
            </div>`;
                }

                // Scores
                html += '<h4 style="font-size:13px;font-weight:600;color:#334155;margin:12px 0 8px;">Score de Confianca Composto</h4>';
                html += '<div class="scores-grid">';
                html += scoreItem('Estatistico (30%)', s.VL_SCORE_ESTATISTICO);
                html += scoreItem('Modelo (30%)', s.VL_SCORE_MODELO);
                html += scoreItem('Topologico (20%)', s.VL_SCORE_TOPOLOGICO);
                html += scoreItem('Historico (10%)', s.VL_SCORE_HISTORICO);
                html += scoreItem('Padrao (10%)', s.VL_SCORE_PADRAO);
                html += scoreItem('CONFIANCA FINAL', p.VL_CONFIANCA, true);
                html += '</div>';

                // Outras horas
                if (outras.length > 0) {
                    html += '<h4 style="font-size:13px;font-weight:600;color:#334155;margin:12px 0 8px;">Outras anomalias no mesmo dia</h4>';
                    html += '<div style="display:flex;flex-wrap:wrap;gap:6px;">';
                    outras.forEach(o => {
                        const statusCor = o.ID_STATUS == 0 ? '#fbbf24' : (o.ID_STATUS == 1 || o.ID_STATUS == 2 ? '#22c55e' : '#94a3b8');
                        html += `<span style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:4px 8px;font-size:11px;">
                    <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:${statusCor};margin-right:4px;"></span>
                    ${o.DS_HORA_FORMATADA} — ${ucfirst(o.DS_SEVERIDADE)}
                </span>`;
                    });
                    html += '</div>';
                }

                // Reserva GNN
                html += `<div class="gnn-placeholder" style="margin-top:16px;">
            <ion-icon name="git-network-outline"></ion-icon>
            <p><strong>Contexto GNN (Fase B)</strong> — Coerencia sistemica e propagacao de eventos.</p>
        </div>`;

                document.getElementById('modalDetalheBody').innerHTML = html;
            })
            .catch(err => {
                document.getElementById('modalDetalheBody').innerHTML =
                    '<p style="color:#dc2626;text-align:center;">Erro: ' + err.message + '</p>';
            });
    }


    // ============================================
    // Executar Batch
    // ============================================

    function executarBatch() {
        if (!confirm('Executar motor de analise batch?\nIsso pode levar alguns minutos.')) return;

        const btn = document.getElementById('btnExecutarBatch');
        const textoOriginal = btn.innerHTML;
        btn.innerHTML = '<div class="loading-spinner" style="width:14px;height:14px;border-width:2px;"></div> Processando...';
        btn.disabled = true;

        fetch('bd/operacoes/motorBatchTratamento.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'executar_batch' })
        })
            .then(r => r.json())
            .then(data => {
                btn.innerHTML = textoOriginal;
                btn.disabled = false;

                if (data.success) {
                    toast(`Batch concluido: ${data.processados} pontos, ${data.pendencias_geradas} pendencias, ${data.tempo_segundos}s`, 'ok');
                    carregarEstatisticas();
                } else {
                    toast(data.error || 'Erro no batch', 'err');
                }
            })
            .catch(err => {
                btn.innerHTML = textoOriginal;
                btn.disabled = false;
                toast('Erro de conexao: ' + err.message, 'err');
            });
    }

    /** Abre modal de regras */
    function abrirRegras() {
        document.getElementById('modalRegras').classList.add('active');
    }

    /** Fecha modal de regras */
    function fecharRegras() {
        document.getElementById('modalRegras').classList.remove('active');
    }

    /** Trocar aba Regras/Glossario */
    function trocarAbaRegra(aba, btn) {
        document.getElementById('abaRegras').style.display = aba === 'regras' ? '' : 'none';
        document.getElementById('abaGlossario').style.display = aba === 'glossario' ? '' : 'none';
        document.querySelectorAll('.regra-tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') fecharRegras();
    });
    /**
     * Redireciona para a tela de validacao (operacoes.php) com o ponto e data pre-selecionados.
     * Abre em nova aba para nao perder o contexto do tratamento em lote.
     */
    function irParaValidacao(cdPonto, data) {
        if (!cdPonto || !data) return;
        const partes = data.split('-');
        const mes = partes.length >= 2 ? parseInt(partes[1]) : new Date().getMonth() + 1;
        const ano = partes.length >= 1 ? partes[0] : new Date().getFullYear();
        window.open(`operacoes.php?abrirValidacao=1&cdPonto=${cdPonto}&dataValidacao=${data}&mes=${mes}&ano=${ano}`, '_blank');
    }


    // ============================================
    // Utilitarios
    // ============================================

    /** Abre modal pelo ID */
    function abrirModal(id) { document.getElementById(id).classList.add('ativo'); }

    /** Fecha modal pelo ID */
    function fecharModal(id) { document.getElementById(id).classList.remove('ativo'); }

    /** Fechar modal ao clicar no overlay */
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('modal-overlay')) {
            e.target.classList.remove('ativo');
        }
    });

    /** Fechar modal com ESC */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.ativo').forEach(m => m.classList.remove('ativo'));
        }
    });

    /** Limpar filtros */
    function limparFiltros() {
        $('#filtroStatus').val('0').trigger('change');
        $('#filtroClasse').val('').trigger('change');
        $('#filtroTipoAnomalia').val('').trigger('change');
        $('#filtroTipoMedidor').val('').trigger('change');
        $('#filtroUnidade').val('').trigger('change');
        $('#filtroConfianca').val('').trigger('change');
        document.getElementById('filtroBusca').value = '';
        paginaAtual = 1;
        carregarPendencias();
    }

    /** Badge do tipo de medidor */
    function getTipoMedidorBadge(tipo) {
        const map = {
            1: { cls: 'macro', icon: 'water-outline', nome: 'Macro' },
            2: { cls: 'pitometrica', icon: 'speedometer-outline', nome: 'Pito' },
            4: { cls: 'pressao', icon: 'thermometer-outline', nome: 'Pressao' },
            6: { cls: 'reservatorio', icon: 'cube-outline', nome: 'Reserv.' },
            8: { cls: 'hidrometro', icon: 'reader-outline', nome: 'Hidro' },
        };
        const m = map[tipo] || { cls: 'macro', icon: 'help-outline', nome: 'Outro' };
        return `<span class="tipo-medidor-badge ${m.cls}"><ion-icon name="${m.icon}"></ion-icon> ${m.nome}</span>`;
    }

    /** Formatar data YYYY-MM-DD para DD/MM/YYYY */
    function formatarData(dt) {
        if (!dt) return '-';
        const d = dt.split('T')[0].split('-');
        return d.length === 3 ? d[2] + '/' + d[1] + '/' + d[0] : dt;
    }

    /** Uppercase first */
    function ucfirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }

    /** Truncar string */
    function truncar(s, max) { return s && s.length > max ? s.substring(0, max) + '...' : s; }

    /** Formatar numero */
    function fmtNum(v, dec) {
        if (v == null || v === '') return '-';
        return parseFloat(v).toFixed(dec !== undefined ? dec : 4);
    }

    /** Escape HTML */
    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    /** Item de detalhe */
    function detalheItem(label, valor) {
        return `<div class="detalhe-item"><label>${label}</label><span>${valor}</span></div>`;
    }

    /** Item de score */
    function scoreItem(label, valor, destaque) {
        const v = valor != null ? (parseFloat(valor) * 100).toFixed(1) + '%' : '-';
        const est = destaque ? 'background:#1e3a5f;color:white;' : '';
        const estV = destaque ? 'color:white;' : '';
        return `<div class="score-item" style="${est}"><label style="${destaque ? 'color:rgba(255,255,255,0.7);' : ''}">${label}</label><div class="score-valor" style="${estV}">${v}</div></div>`;
    }

    /** Toast (usa funcao global do sistema se existir) */
    function toast(msg, tipo) {
        if (typeof window.toast === 'function' && window.toast !== toast) {
            window.toast(msg, tipo);
            return;
        }
        // Fallback simples
        const cores = { ok: '#22c55e', err: '#ef4444', inf: '#3b82f6' };
        const div = document.createElement('div');
        div.style.cssText = `position:fixed;top:80px;right:20px;z-index:99999;background:${cores[tipo] || cores.inf};color:white;padding:12px 20px;border-radius:10px;font-size:13px;box-shadow:0 4px 12px rgba(0,0,0,0.15);max-width:400px;transition:opacity .3s;`;
        div.textContent = msg;
        document.body.appendChild(div);
        setTimeout(() => { div.style.opacity = '0'; setTimeout(() => div.remove(), 300); }, 4000);
    }
</script>

<?php include_once 'includes/footer.inc.php'; ?>
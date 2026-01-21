<?php
include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão para editar Ponto de Medição (busca por nome na tabela FUNCIONALIDADE)
exigePermissaoTela('Cadastro de Ponto de Medição', ACESSO_ESCRITA);

// Verifica se é edição
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdicao = $id > 0;
$pontoMedicao = null;

// Se for edição, busca os dados
if ($isEdicao) {
    $sql = "SELECT 
                PM.*,
                L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
                L.DS_NOME AS DS_LOCALIDADE,
                L.CD_UNIDADE,
                U.DS_NOME AS DS_UNIDADE,
                U.CD_CODIGO AS CD_UNIDADE_CODIGO,
                UA.DS_NOME AS DS_USUARIO_ATUALIZACAO
            FROM SIMP.dbo.PONTO_MEDICAO PM
            INNER JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
            LEFT JOIN SIMP.dbo.USUARIO UA ON PM.CD_USUARIO_ULTIMA_ATUALIZACAO = UA.CD_USUARIO
            WHERE PM.CD_PONTO_MEDICAO = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    $pontoMedicao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pontoMedicao) {
        $_SESSION['msg'] = 'Ponto de medição não encontrado.';
        $_SESSION['msg_tipo'] = 'erro';
        header('Location: pontoMedicao.php');
        exit;
    }
}

// Buscar Unidades
$sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME, CD_CODIGO FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

// Buscar Usuários para Responsável (OP_BLOQUEADO = 2 = usuários ativos)
// Também inclui o usuário responsável atual mesmo que esteja com status diferente
$sqlUsuariosBase = "SELECT CD_USUARIO, DS_NOME, DS_MATRICULA FROM SIMP.dbo.USUARIO WHERE OP_BLOQUEADO = 2";
if ($isEdicao && !empty($pontoMedicao['CD_USUARIO_RESPONSAVEL'])) {
    $sqlUsuariosBase .= " OR CD_USUARIO = " . (int)$pontoMedicao['CD_USUARIO_RESPONSAVEL'];
}
$sqlUsuariosBase .= " ORDER BY DS_NOME";
$sqlUsuarios = $pdoSIMP->query($sqlUsuariosBase);
$usuarios = $sqlUsuarios->fetchAll(PDO::FETCH_ASSOC);

// Tipos de Medidor
$tiposMedidor = [
    ['value' => '1', 'text' => 'M - Macromedidor'],
    ['value' => '2', 'text' => 'E - Estação Pitométrica'],
    ['value' => '4', 'text' => 'P - Medidor Pressão'],
    ['value' => '8', 'text' => 'H - Hidrômetro'],
    ['value' => '6', 'text' => 'R - Nível Reservatório'],
];

// Mapeamento de tipo de medidor para letra
$letrasTipoMedidor = [
    1 => 'M', // Macromedidor
    2 => 'E', // Estação Pitométrica
    4 => 'P', // Medidor Pressão
    6 => 'R', // Nível Reservatório
    8 => 'H'  // Hidrômetro
];

// Tipos de Leitura
$tiposLeitura = [
    ['value' => '2', 'text' => 'Manual'],
    ['value' => '4', 'text' => 'Planilha'],
    ['value' => '8', 'text' => 'Integração CCO'],
    ['value' => '6', 'text' => 'Integração CesanLims'],
];

// Buscar Tipos de Reservatório
$sqlTiposReservatorio = $pdoSIMP->query("SELECT CD_CHAVE, NOME FROM SIMP.dbo.TIPO_RESERVATORIO ORDER BY NOME");
$tiposReservatorio = $sqlTiposReservatorio->fetchAll(PDO::FETCH_ASSOC);

// Tipos de Fluido (ID_PRODUTO)
$tiposFluido = [
    ['value' => '', 'text' => 'Indiferente'],
    ['value' => '1', 'text' => 'Água Tratada'],
    ['value' => '3', 'text' => 'Água Bruta'],
    ['value' => '2', 'text' => 'Esgoto'],
];

// Periodicidades
$periodicidades = [
    ['value' => '', 'text' => 'Indiferente'],
    ['value' => '2', 'text' => 'Minuto - 2'],
    ['value' => '3', 'text' => 'Hora - 3'],
    ['value' => '4', 'text' => 'Diário - 4'],
];

// Tipos de Instalação (tinyint no banco)
$tiposInstalacao = [
    ['value' => '1', 'text' => 'Exposto'],
    ['value' => '2', 'text' => 'Poço de Visita'],
    ['value' => '3', 'text' => 'Inacessível'],
];
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
    /* Reset e box-sizing global */
    .page-container *,
    .page-container *::before,
    .page-container *::after {
        box-sizing: border-box;
    }

    /* ============================================
       Page Container
       ============================================ */
    .page-container {
        padding: 20px;
        max-width: 1400px;
        margin: 0 auto;
        overflow-x: hidden;
        display: flex;
        flex-direction: column;
    }
    
    .page-container > .page-header { order: 1; }
    .page-container > #formPontoMedicao { order: 2; }
    .page-container > #tabsContainer { order: 3; }

    /* ============================================
       Page Header
       ============================================ */
    .page-header {
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        border-radius: 12px;
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
        font-size: 16px;
        font-weight: 700;
        margin: 0 0 4px 0;
        color: white;
    }

    .page-header-subtitle {
        font-size: 11px;
        color: rgba(255, 255, 255, 0.7);
        margin: 0;
    }

    .header-actions {
        display: flex;
        gap: 10px;
    }

    .btn-voltar {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        background: rgba(255, 255, 255, 0.15);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .btn-voltar:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    .btn-voltar ion-icon {
        font-size: 14px;
    }

    /* ============================================
       Form Card
       ============================================ */
    .form-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        margin-bottom: 12px;
    }

    .form-card-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 20px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .form-card-header ion-icon {
        font-size: 16px;
        color: #3b82f6;
    }

    .form-card-header h2 {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }

    .form-card-body {
        padding: 16px 20px;
        overflow: hidden;
    }
    
    /* Form também como flex */
    #formPontoMedicao {
        display: flex;
        flex-direction: column;
    }
    
    #formPontoMedicao > .form-card { order: 1; }
    #formPontoMedicao > #secaoObservacoes { order: 2; }

    /* ============================================
       Form Grid - Sistema de Grid Robusto
       ============================================ */
    .form-row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -8px 12px -8px;
    }

    .form-row:last-child {
        margin-bottom: 0;
    }

    .form-group {
        padding: 0 8px;
        margin-bottom: 0;
        box-sizing: border-box;
    }

    /* Colunas baseadas em porcentagem */
    .col-12 { width: 100%; }
    .col-8 { width: 66.666667%; }
    .col-6 { width: 50%; }
    .col-4 { width: 33.333333%; }
    .col-3 { width: 25%; }
    .col-2 { width: 16.666667%; }

    /* ============================================
       Form Controls
       ============================================ */
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .form-group .form-control,
    .form-group .select2-container {
        width: 100% !important;
        box-sizing: border-box;
    }

    .form-label {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 10px;
        font-weight: 600;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .form-label .required {
        color: #ef4444;
    }

    .form-label ion-icon {
        font-size: 12px;
        color: #94a3b8;
    }

    .form-control {
        width: 100%;
        max-width: 100%;
        padding: 8px 12px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-family: inherit;
        font-size: 12px;
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

    .form-control:disabled,
    .form-control[readonly] {
        background-color: #f1f5f9;
        color: #64748b;
        cursor: not-allowed;
    }

    .form-control::placeholder {
        color: #94a3b8;
    }

    textarea.form-control {
        min-height: 60px;
        resize: vertical;
    }

    /* Input with Action Button */
    .input-with-action {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .input-with-action .form-control {
        flex: 1;
    }

    .btn-map {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .btn-map:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }

    .btn-map:disabled {
        background: #cbd5e1;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .btn-map ion-icon {
        font-size: 18px;
    }

    /* Select2 Styling */
    .select2-container {
        width: 100% !important;
        max-width: 100%;
    }
    
    .select2-container--default .select2-selection--single {
        height: 36px;
        padding: 4px 12px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        width: 100%;
    }

    .select2-container--default.select2-container--focus .select2-selection--single,
    .select2-container--default.select2-container--open .select2-selection--single {
        background-color: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #334155;
        font-size: 12px;
        line-height: 26px;
        padding-left: 0;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 34px;
        right: 8px;
    }

    .select2-dropdown {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        margin-top: 4px;
    }

    .select2-container--default .select2-results__option {
        padding: 8px 12px;
        font-size: 12px;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #eff6ff;
        color: #3b82f6;
    }

    /* Info Badge */
    .info-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 8px 12px;
        background: #f1f5f9;
        border-radius: 6px;
        font-size: 12px;
        color: #475569;
    }

    .info-badge.codigo {
        background: #eff6ff;
        color: #3b82f6;
        font-family: 'SF Mono', Monaco, monospace;
        font-weight: 600;
    }

    /* ============================================
       Form Actions
       ============================================ */
    .form-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        padding: 12px 20px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
        text-decoration: none;
    }

    .btn ion-icon {
        font-size: 14px;
    }

    .btn-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
    }

    .btn-secondary {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .btn-secondary:hover {
        background: #e2e8f0;
    }

    .btn-danger {
        background: #fee2e2;
        color: #b91c1c;
    }

    .btn-danger:hover {
        background: #fecaca;
    }

    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
    }

    /* ============================================
       Divider
       ============================================ */
    .form-divider {
        height: 1px;
        background: linear-gradient(to right, transparent, #e2e8f0, transparent);
        margin: 16px 0;
    }

    /* ============================================
       Status Badge
       ============================================ */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }

    .status-badge.ativo {
        background: #dcfce7;
        color: #15803d;
    }

    .status-badge.inativo {
        background: #fee2e2;
        color: #b91c1c;
    }

    .status-badge ion-icon {
        font-size: 12px;
    }

    /* ============================================
       Responsive
       ============================================ */
    @media (max-width: 1200px) {
        .col-3 { width: 33.333333%; }
        .col-2 { width: 25%; }
    }

    @media (max-width: 992px) {
        .col-3 { width: 50%; }
        .col-4 { width: 50%; }
        .col-8 { width: 100%; }
        .col-2 { width: 33.333333%; }
    }

    @media (max-width: 768px) {
        .page-container {
            padding: 12px;
        }

        .page-header {
            padding: 16px;
        }

        .page-header-content {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }

        .page-header-info {
            flex-direction: column;
            text-align: center;
            gap: 10px;
        }

        .page-header-icon {
            margin: 0 auto;
        }

        .page-header h1 {
            font-size: 16px;
        }

        .btn-voltar {
            width: 100%;
            justify-content: center;
            box-sizing: border-box;
        }

        .form-card-header {
            padding: 10px 16px;
        }

        .form-card-body {
            padding: 12px 16px;
        }

        .form-row {
            margin: 0 -4px 10px -4px;
        }

        .form-group {
            padding: 0 4px;
        }

        .col-2, .col-3, .col-4, .col-6, .col-8 {
            width: 100%;
        }

        .form-actions {
            flex-direction: column;
            padding: 12px 16px;
        }

        .form-actions .btn {
            width: 100%;
        }
    }

    /* Fix para campos dinâmicos */
    #camposEquipamento .form-row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -8px 12px -8px;
    }
    
    #camposEquipamento .form-group {
        padding: 0 8px;
        box-sizing: border-box;
    }
    
    #camposEquipamento .col-4 { width: 33.333333%; }
    #camposEquipamento .col-3 { width: 25%; }
    
    @media (max-width: 992px) {
        #camposEquipamento .col-4,
        #camposEquipamento .col-3 {
            width: 50%;
        }
    }
    
    @media (max-width: 768px) {
        #camposEquipamento .form-row {
            margin: 0 -4px 10px -4px;
        }
        
        #camposEquipamento .form-group {
            padding: 0 4px;
        }
        
        #camposEquipamento .col-4,
        #camposEquipamento .col-3 {
            width: 100%;
        }
    }
    
    #camposEquipamento .form-control {
        width: 100%;
        box-sizing: border-box;
    }

    /* ============================================
       Sistema de Abas
       ============================================ */
    .tabs-container {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
        overflow: hidden;
    }

    .tabs-header {
        display: flex;
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        border-bottom: 1px solid #e2e8f0;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .tabs-header::-webkit-scrollbar {
        height: 4px;
    }

    .tabs-header::-webkit-scrollbar-track {
        background: rgba(255,255,255,0.1);
    }

    .tabs-header::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.3);
        border-radius: 2px;
    }

    .tab-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 14px 20px;
        background: transparent;
        border: none;
        color: rgba(255, 255, 255, 0.7);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
        position: relative;
    }

    .tab-btn:hover {
        color: white;
        background: rgba(255, 255, 255, 0.1);
    }

    .tab-btn.active {
        color: white;
        background: rgba(255, 255, 255, 0.15);
    }

    .tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: #3b82f6;
    }

    .tab-btn ion-icon {
        font-size: 18px;
    }

    .tab-btn .tab-badge {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 10px;
        font-weight: 700;
    }

    .tab-btn.active .tab-badge {
        background: #3b82f6;
    }

    .tabs-content {
        padding: 0;
    }

    .tab-pane {
        display: none;
        padding: 20px;
    }

    .tab-pane.active {
        display: block;
    }

    /* Responsivo para abas */
    @media (max-width: 768px) {
        .tabs-header {
            flex-wrap: nowrap;
        }

        .tab-btn {
            padding: 12px 16px;
            font-size: 12px;
            flex: 1;
            justify-content: center;
            min-width: 120px;
        }

        .tab-btn ion-icon {
            font-size: 16px;
        }

        .tab-pane {
            padding: 16px;
        }
    }

    @media (max-width: 480px) {
        .tab-btn {
            padding: 10px 12px;
            font-size: 11px;
            min-width: 100px;
        }

        .tab-btn span.tab-text {
            display: none;
        }

        .tab-btn ion-icon {
            font-size: 20px;
        }
    }

    /* ============================================
       Meta Mensal - Tabela e Modal
       ============================================ */
    .meta-filtro {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .table-container-meta {
        overflow-x: auto;
    }

    .data-table-meta {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    .data-table-meta th {
        background: #f8fafc;
        padding: 10px 12px;
        text-align: left;
        font-weight: 600;
        color: #475569;
        border-bottom: 1px solid #e2e8f0;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .data-table-meta td {
        padding: 10px 12px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
    }

    .data-table-meta tr:hover {
        background: #f8fafc;
    }

    .data-table-meta .btn-action {
        width: 28px;
        height: 28px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f1f5f9;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        color: #64748b;
    }

    .data-table-meta .btn-action:hover {
        background: #e2e8f0;
        color: #334155;
    }

    .data-table-meta .btn-action.delete:hover {
        background: #fee2e2;
        color: #b91c1c;
    }

    .data-table-meta .btn-action ion-icon {
        font-size: 14px;
    }

    .empty-state-mini {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 24px;
        color: #94a3b8;
        font-size: 12px;
    }

    .empty-state-mini ion-icon {
        font-size: 18px;
    }

    .btn-sm {
        padding: 6px 12px !important;
        font-size: 11px !important;
    }

    .btn-sm ion-icon {
        font-size: 12px !important;
    }

    /* Modal */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        padding: 20px;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: 12px;
        width: 100%;
        max-width: 500px;
        max-height: 90vh;
        overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .modal-header h3 {
        font-size: 14px;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }

    .modal-close {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        color: #64748b;
        transition: all 0.2s ease;
    }

    .modal-close:hover {
        background: #e2e8f0;
        color: #334155;
    }

    .modal-close ion-icon {
        font-size: 20px;
    }

    .modal-body {
        padding: 20px;
        overflow-y: auto;
        max-height: calc(90vh - 140px);
    }

    .modal-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        padding: 16px 20px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }

    .aviso-ano-inteiro {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        background: #fef3c7;
        border: 1px solid #fcd34d;
        border-radius: 8px;
        color: #92400e;
        font-size: 11px;
        margin-top: 16px;
    }

    .aviso-ano-inteiro ion-icon {
        font-size: 16px;
        flex-shrink: 0;
    }

    .mes-nome {
        font-weight: 500;
    }

    /* Radio Buttons Inline */
    .radio-group-inline {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        background: #f1f5f9;
        padding: 6px;
        border-radius: 10px;
    }

    .radio-item-inline {
        display: flex;
        align-items: center;
        cursor: pointer;
        margin: 0;
    }

    .radio-item-inline input[type="radio"] {
        display: none;
    }

    .radio-label-inline {
        padding: 10px 16px;
        font-size: 13px;
        font-weight: 500;
        color: #64748b;
        border-radius: 8px;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .radio-item-inline input[type="radio"]:checked + .radio-label-inline {
        background: white;
        color: #1e293b;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .radio-item-inline:hover .radio-label-inline {
        color: #1e293b;
    }
</style>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="<?= $isEdicao ? 'create-outline' : 'add-outline' ?>"></ion-icon>
                </div>
                <div>
                    <h1><?= $isEdicao ? 'Editar Ponto de Medição' : 'Novo Ponto de Medição' ?></h1>
                    <p class="page-header-subtitle">
                        <?= $isEdicao ? 'Atualize as informações do ponto de medição' : 'Cadastre um novo ponto de medição no sistema' ?>
                    </p>
                </div>
            </div>
            <div class="header-actions">
                <a href="pontoMedicao.php" class="btn-voltar">
                    <ion-icon name="arrow-back-outline"></ion-icon>
                    Voltar
                </a>
            </div>
        </div>
    </div>

    <form id="formPontoMedicao" method="POST" action="bd/pontoMedicao/salvarPontoMedicao.php">
        <input type="hidden" name="cd_ponto_medicao" value="<?= $id ?>">

        <!-- Identificação -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="information-circle-outline"></ion-icon>
                <h2>Identificação</h2>
                <?php if ($isEdicao): ?>
                    <span class="status-badge <?= empty($pontoMedicao['DT_DESATIVACAO']) ? 'ativo' : 'inativo' ?>" style="margin-left: auto;">
                        <ion-icon name="<?= empty($pontoMedicao['DT_DESATIVACAO']) ? 'checkmark-circle' : 'close-circle' ?>"></ion-icon>
                        <?= empty($pontoMedicao['DT_DESATIVACAO']) ? 'Ativo' : 'Inativo' ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <!-- Unidade -->
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="business-outline"></ion-icon>
                            Unidade <span class="required">*</span>
                        </label>
                        <select id="selectUnidade" name="cd_unidade" class="form-control select2" required>
                            <option value="">Selecione a Unidade</option>
                            <?php foreach ($unidades as $unidade): ?>
                                <option value="<?= $unidade['CD_UNIDADE'] ?>" 
                                    <?= ($isEdicao && $pontoMedicao['CD_UNIDADE'] == $unidade['CD_UNIDADE']) ? 'selected' : '' ?>>
                                    <?= $unidade['CD_CODIGO'] . ' - ' . $unidade['DS_NOME'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Localidade -->
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="location-outline"></ion-icon>
                            Localidade <span class="required">*</span>
                        </label>
                        <select id="selectLocalidade" name="cd_localidade" class="form-control select2" required <?= !$isEdicao ? 'disabled' : '' ?>>
                            <?php if ($isEdicao): ?>
                                <option value="<?= $pontoMedicao['CD_LOCALIDADE'] ?>" selected>
                                    <?= $pontoMedicao['CD_LOCALIDADE_CODIGO'] . ' - ' . $pontoMedicao['DS_LOCALIDADE'] ?>
                                </option>
                            <?php else: ?>
                                <option value="">Selecione a Unidade primeiro</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Código -->
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="barcode-outline"></ion-icon>
                            Código
                        </label>
                        <?php if ($isEdicao): ?>
                            <div class="info-badge codigo">
                                <?= $pontoMedicao['CD_LOCALIDADE_CODIGO'] . '-' . str_pad($pontoMedicao['CD_PONTO_MEDICAO'], 6, '0', STR_PAD_LEFT) . '-' . ($letrasTipoMedidor[$pontoMedicao['ID_TIPO_MEDIDOR']] ?? 'X') . '-' . $pontoMedicao['CD_UNIDADE'] ?>
                            </div>
                        <?php else: ?>
                            <div class="info-badge">Será gerado automaticamente</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Tipo Medidor -->
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="speedometer-outline"></ion-icon>
                            Tipo de Medidor <span class="required">*</span>
                        </label>
                        <select id="selectTipoMedidor" name="id_tipo_medidor" class="form-control select2" required>
                            <option value="">Selecione o Tipo</option>
                            <?php foreach ($tiposMedidor as $tipo): ?>
                                <option value="<?= $tipo['value'] ?>"
                                    <?= ($isEdicao && $pontoMedicao['ID_TIPO_MEDIDOR'] == $tipo['value']) ? 'selected' : '' ?>>
                                    <?= $tipo['text'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Nome -->
                    <div class="form-group col-8">
                        <label class="form-label">
                            <ion-icon name="text-outline"></ion-icon>
                            Nome <span class="required">*</span>
                        </label>
                        <input type="text" name="ds_nome" class="form-control" 
                               value="<?= $isEdicao ? htmlspecialchars($pontoMedicao['DS_NOME']) : '' ?>"
                               placeholder="Digite o nome do ponto de medição" maxlength="100" required>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Localização -->
                    <div class="form-group col-12">
                        <label class="form-label">
                            <ion-icon name="map-outline"></ion-icon>
                            Localização
                        </label>
                        <textarea name="ds_localizacao" class="form-control" 
                                  placeholder="Descreva a localização física do ponto de medição (endereço, coordenadas, referências)"><?= $isEdicao ? htmlspecialchars($pontoMedicao['DS_LOCALIZACAO']) : '' ?></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Coordenadas -->
                    <div class="form-group col-6">
                        <label class="form-label">
                            <ion-icon name="navigate-outline"></ion-icon>
                            Coordenadas
                        </label>
                        <div class="input-with-action">
                            <input type="text" name="coordenadas" id="inputCoordenadas" class="form-control"
                                   value="<?= $isEdicao ? htmlspecialchars($pontoMedicao['COORDENADAS'] ?? '') : '' ?>"
                                   placeholder="Ex: -20.315789, -40.312456">
                            <button type="button" class="btn-map" onclick="abrirMapaCoordenadas()" title="Abrir no Google Maps">
                                <ion-icon name="map-outline"></ion-icon>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Configuração de Leitura -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="settings-outline"></ion-icon>
                <h2>Configuração de Leitura</h2>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <!-- Tipo de Leitura -->
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="reader-outline"></ion-icon>
                            Tipo de Leitura <span class="required">*</span>
                        </label>
                        <select name="id_tipo_leitura" class="form-control select2" required>
                            <option value="">Selecione</option>
                            <?php foreach ($tiposLeitura as $tipo): ?>
                                <option value="<?= $tipo['value'] ?>"
                                    <?= ($isEdicao && $pontoMedicao['ID_TIPO_LEITURA'] == $tipo['value']) ? 'selected' : '' ?>>
                                    <?= $tipo['text'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Periodicidade -->
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="time-outline"></ion-icon>
                            Periodicidade de Leitura
                        </label>
                        <select name="op_periodicidade_leitura" class="form-control select2">
                            <option value="">Selecione</option>
                            <?php foreach ($periodicidades as $p): ?>
                                <option value="<?= $p['value'] ?>"
                                    <?= ($isEdicao && $pontoMedicao['OP_PERIODICIDADE_LEITURA'] == $p['value']) ? 'selected' : '' ?>>
                                    <?= $p['text'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Responsável -->
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="person-outline"></ion-icon>
                            Responsável <span class="required">*</span>
                        </label>
                        <select id="selectResponsavel" name="cd_usuario_responsavel" class="form-control select2" required>
                            <option value="">Selecione</option>
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?= $usuario['CD_USUARIO'] ?>"
                                    <?= ($isEdicao && $pontoMedicao['CD_USUARIO_RESPONSAVEL'] == $usuario['CD_USUARIO']) ? 'selected' : '' ?>>
                                    <?= $usuario['DS_MATRICULA'] . ' - ' . $usuario['DS_NOME'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tipo Instalação -->
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="construct-outline"></ion-icon>
                            Tipo de Instalação
                        </label>
                        <select name="tipo_instalacao" class="form-control select2">
                            <option value="">Selecione</option>
                            <?php foreach ($tiposInstalacao as $tipo): ?>
                                <option value="<?= $tipo['value'] ?>"
                                    <?= ($isEdicao && $pontoMedicao['TIPO_INSTALACAO'] == $tipo['value']) ? 'selected' : '' ?>>
                                    <?= $tipo['text'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <!-- Data de Ativação -->
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="calendar-outline"></ion-icon>
                            Data de Ativação
                        </label>
                        <input type="datetime-local" name="dt_ativacao" class="form-control"
                               value="<?= $isEdicao && $pontoMedicao['DT_ATIVACAO'] ? date('Y-m-d\TH:i', strtotime($pontoMedicao['DT_ATIVACAO'])) : '' ?>">
                    </div>

                    <!-- Data de Desativação -->
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="calendar-outline"></ion-icon>
                            Data de Desativação
                        </label>
                        <input type="datetime-local" name="dt_desativacao" class="form-control"
                               value="<?= $isEdicao && $pontoMedicao['DT_DESATIVACAO'] ? date('Y-m-d\TH:i', strtotime($pontoMedicao['DT_DESATIVACAO'])) : '' ?>">
                    </div>

                    <!-- Quantidade Ligações -->
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="git-branch-outline"></ion-icon>
                            Qtd. Ligações
                        </label>
                        <input type="number" name="vl_quantidade_ligacoes" class="form-control" min="0"
                               value="<?= $isEdicao ? $pontoMedicao['VL_QUANTIDADE_LIGACOES'] : '' ?>"
                               placeholder="0">
                    </div>

                    <!-- Quantidade Economias -->
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="home-outline"></ion-icon>
                            Qtd. Economias
                        </label>
                        <input type="number" name="vl_quantidade_economias" class="form-control" min="0"
                               value="<?= $isEdicao ? $pontoMedicao['VL_QUANTIDADE_ECONOMIAS'] : '' ?>"
                               placeholder="0">
                    </div>
                </div>
            </div>
        </div>

        <!-- Tags de Integração -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="pricetag-outline"></ion-icon>
                <h2>Tags de Integração</h2>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <!-- Tag Vazão -->
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="water-outline"></ion-icon>
                            Tag Vazão
                        </label>
                        <input type="text" name="ds_tag_vazao" class="form-control"
                               value="<?= $isEdicao ? htmlspecialchars($pontoMedicao['DS_TAG_VAZAO']) : '' ?>"
                               placeholder="Ex: CP004_TM192_17_MED">
                    </div>

                    <!-- Tag Pressão -->
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="speedometer-outline"></ion-icon>
                            Tag Pressão
                        </label>
                        <input type="text" name="ds_tag_pressao" class="form-control"
                               value="<?= $isEdicao ? htmlspecialchars($pontoMedicao['DS_TAG_PRESSAO']) : '' ?>"
                               placeholder="Tag de pressão">
                    </div>

                    <!-- Tag Volume -->
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="cube-outline"></ion-icon>
                            Tag Volume
                        </label>
                        <input type="text" name="ds_tag_volume" class="form-control"
                               value="<?= $isEdicao ? htmlspecialchars($pontoMedicao['DS_TAG_VOLUME']) : '' ?>"
                               placeholder="Tag de volume">
                    </div>
                </div>

                <div class="form-row">
                    <!-- Tag Nível Reservatório -->
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="analytics-outline"></ion-icon>
                            Tag Nível Reservatório
                        </label>
                        <input type="text" name="ds_tag_reservatorio" class="form-control"
                               value="<?= $isEdicao ? htmlspecialchars($pontoMedicao['DS_TAG_RESERVATORIO']) : '' ?>"
                               placeholder="Tag de nível do reservatório">
                    </div>

                    <!-- Tag Temperatura Água -->
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="thermometer-outline"></ion-icon>
                            Tag Temperatura da Água
                        </label>
                        <input type="text" name="ds_tag_temp_agua" class="form-control"
                               value="<?= $isEdicao ? htmlspecialchars($pontoMedicao['DS_TAG_TEMP_AGUA']) : '' ?>"
                               placeholder="Tag de temperatura da água">
                    </div>

                    <!-- Tag Temperatura Ambiente -->
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="sunny-outline"></ion-icon>
                            Tag Temperatura do Ambiente
                        </label>
                        <input type="text" name="ds_tag_temp_ambiente" class="form-control"
                               value="<?= $isEdicao ? htmlspecialchars($pontoMedicao['DS_TAG_TEMP_AMBIENTE']) : '' ?>"
                               placeholder="Tag de temperatura ambiente">
                    </div>
                </div>

                <div class="form-row">
                    <!-- Local Instalação SAP -->
                    <div class="form-group col-6">
                        <label class="form-label">
                            <ion-icon name="business-outline"></ion-icon>
                            Local Instalação SAP
                        </label>
                        <input type="text" name="loc_inst_sap" class="form-control"
                               value="<?= $isEdicao ? htmlspecialchars($pontoMedicao['LOC_INST_SAP'] ?? '') : '' ?>"
                               placeholder="Local de instalação no SAP">
                    </div>
                </div>
            </div>
        </div>

        <!-- Parâmetros de Medição -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="options-outline"></ion-icon>
                <h2>Parâmetros de Medição</h2>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <!-- Fator Correção Vazão -->
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="calculator-outline"></ion-icon>
                            Fator Correção Vazão
                        </label>
                        <input type="number" step="0.0001" name="vl_fator_correcao_vazao" class="form-control"
                               value="<?= $isEdicao ? $pontoMedicao['VL_FATOR_CORRECAO_VAZAO'] : '' ?>"
                               placeholder="Ex: 1.0000">
                    </div>

                    <!-- Limite Inferior -->
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="trending-down-outline"></ion-icon>
                            Limite Inferior Vazão (l/s) ou Pressão (mca)
                        </label>
                        <input type="number" step="0.01" name="vl_limite_inferior_vazao" class="form-control"
                               value="<?= $isEdicao ? $pontoMedicao['VL_LIMITE_INFERIOR_VAZAO'] : '' ?>"
                               placeholder="0.00">
                    </div>

                    <!-- Limite Superior -->
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="trending-up-outline"></ion-icon>
                            Limite Superior Vazão (l/s) ou Pressão (mca)
                        </label>
                        <input type="number" step="0.01" name="vl_limite_superior_vazao" class="form-control"
                               value="<?= $isEdicao ? $pontoMedicao['VL_LIMITE_SUPERIOR_VAZAO'] : '' ?>"
                               placeholder="0.00">
                    </div>
                </div>
            </div>
        </div>

        <!-- Observações -->
        <div class="form-card" id="secaoObservacoes">
            <div class="form-card-header">
                <ion-icon name="document-text-outline"></ion-icon>
                <h2>Observações</h2>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group col-12">
                        <label class="form-label">
                            <ion-icon name="chatbox-outline"></ion-icon>
                            Observações Gerais
                        </label>
                        <textarea name="ds_observacao" class="form-control" rows="4"
                                  placeholder="Digite observações adicionais sobre este ponto de medição"><?= $isEdicao ? htmlspecialchars($pontoMedicao['DS_OBSERVACAO']) : '' ?></textarea>
                    </div>
                </div>

                <?php if ($isEdicao && $pontoMedicao['DT_ULTIMA_ATUALIZACAO']): ?>
                <div class="form-divider"></div>
                <div class="form-row">
                    <div class="form-group col-12">
                        <small style="color: #94a3b8; font-size: 12px;">
                            <ion-icon name="time-outline" style="vertical-align: middle;"></ion-icon>
                            Última atualização: <?= date('d/m/Y H:i', strtotime($pontoMedicao['DT_ULTIMA_ATUALIZACAO'])) ?>
                            <?php if ($pontoMedicao['DS_USUARIO_ATUALIZACAO']): ?>
                                por <?= htmlspecialchars($pontoMedicao['DS_USUARIO_ATUALIZACAO']) ?>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <a href="pontoMedicao.php" class="btn btn-secondary">
                    <ion-icon name="close-outline"></ion-icon>
                    Cancelar
                </a>
                <?php if ($isEdicao): ?>
                <button type="button" class="btn btn-danger" onclick="confirmarExclusao()">
                    <ion-icon name="trash-outline"></ion-icon>
                    Desativar
                </button>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary" id="btnSalvar">
                    <ion-icon name="save-outline"></ion-icon>
                    <?= $isEdicao ? 'Salvar Alterações' : 'Cadastrar Ponto' ?>
                </button>
            </div>
        </div>
    </form>

    <?php if ($isEdicao): ?>
    <!-- Container de Abas: Equipamento e Meta Mensal -->
    <div class="tabs-container" id="tabsContainer" style="display: none;">
        <div class="tabs-header">
            <button type="button" class="tab-btn active" data-tab="equipamento" id="tabEquipamento">
                <ion-icon name="hardware-chip-outline"></ion-icon>
                <span class="tab-text">Dados do Equipamento</span>
            </button>
            <button type="button" class="tab-btn" data-tab="metas" id="tabMetas">
                <ion-icon name="flag-outline"></ion-icon>
                <span class="tab-text">Meta Mensal</span>
                <span class="tab-badge" id="badgeMetas">0</span>
            </button>
        </div>
        <div class="tabs-content">
            <!-- Aba: Dados do Equipamento -->
            <div class="tab-pane active" id="paneEquipamento">
                <input type="hidden" name="equip_cd_chave" id="equipCdChave">
                <div id="camposEquipamento">
                    <!-- Campos gerados dinamicamente pelo JavaScript -->
                </div>
                <div class="form-actions" style="border-top: 1px solid #e2e8f0; margin-top: 20px; padding-top: 20px;">
                    <button type="button" class="btn btn-primary" onclick="salvarEquipamento()" id="btnSalvarEquip">
                        <ion-icon name="save-outline"></ion-icon>
                        Salvar Dados do Equipamento
                    </button>
                </div>
            </div>

            <!-- Aba: Meta Mensal -->
            <div class="tab-pane" id="paneMetas">
                <div class="meta-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                    <!-- Filtro por ano -->
                    <div class="meta-filtro" style="margin-bottom: 0;">
                        <label class="form-label" style="margin-bottom: 0;">
                            <ion-icon name="calendar-outline"></ion-icon>
                            Filtrar Ano:
                        </label>
                        <select id="filtroAnoMeta" class="form-control" style="width: 120px;">
                            <option value="">Todos</option>
                            <?php for ($ano = date('Y') + 1; $ano >= date('Y') - 5; $ano--): ?>
                                <option value="<?= $ano ?>" <?= $ano == date('Y') ? 'selected' : '' ?>><?= $ano ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="abrirModalMeta()">
                        <ion-icon name="add-outline"></ion-icon>
                        Nova Meta
                    </button>
                </div>

                <!-- Tabela de metas -->
                <div class="table-container-meta">
                    <table class="data-table-meta">
                        <thead id="theadMetas">
                            <!-- Cabeçalho dinâmico -->
                        </thead>
                        <tbody id="tabelaMetas">
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state-mini">
                                        <ion-icon name="hourglass-outline"></ion-icon>
                                        Carregando metas...
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Meta Mensal -->
    <div class="modal-overlay" id="modalMeta">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalMetaTitulo">Nova Meta</h3>
                <button type="button" class="modal-close" onclick="fecharModalMeta()">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>
            <div class="modal-body">
                <form id="formMeta">
                    <input type="hidden" name="cd_chave" id="metaCdChave">
                    <input type="hidden" name="cd_ponto_medicao" value="<?= $id ?>">
                    <input type="hidden" name="id_tipo_medidor" id="metaTipoMedidor">

                    <div class="form-row">
                        <div class="form-group col-6">
                            <label class="form-label">
                                <ion-icon name="calendar-outline"></ion-icon>
                                Ano <span class="required">*</span>
                            </label>
                            <select name="ano_meta" id="metaAno" class="form-control" required>
                                <?php for ($ano = date('Y') + 1; $ano >= date('Y') - 5; $ano--): ?>
                                    <option value="<?= $ano ?>" <?= $ano == date('Y') ? 'selected' : '' ?>><?= $ano ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group col-6">
                            <label class="form-label">
                                <ion-icon name="calendar-number-outline"></ion-icon>
                                Mês
                            </label>
                            <select name="mes_meta" id="metaMes" class="form-control">
                                <option value="">Ano Inteiro</option>
                                <option value="1">Janeiro</option>
                                <option value="2">Fevereiro</option>
                                <option value="3">Março</option>
                                <option value="4">Abril</option>
                                <option value="5">Maio</option>
                                <option value="6">Junho</option>
                                <option value="7">Julho</option>
                                <option value="8">Agosto</option>
                                <option value="9">Setembro</option>
                                <option value="10">Outubro</option>
                                <option value="11">Novembro</option>
                                <option value="12">Dezembro</option>
                            </select>
                        </div>
                    </div>

                    <!-- Campos dinâmicos por tipo de medidor -->
                    <div id="camposMetaDinamicos"></div>

                    <div class="aviso-ano-inteiro" id="avisoAnoInteiro" style="display: none;">
                        <ion-icon name="information-circle-outline"></ion-icon>
                        <span>A meta será aplicada para todos os 12 meses do ano selecionado.</span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModalMeta()">
                    <ion-icon name="close-outline"></ion-icon>
                    Cancelar
                </button>
                <button type="button" class="btn btn-primary" onclick="salvarMeta()" id="btnSalvarMeta">
                    <ion-icon name="save-outline"></ion-icon>
                    Salvar Meta
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    // Dados PHP convertidos para JavaScript
    const tiposReservatorioJS = <?= json_encode($tiposReservatorio) ?>;
    const tiposFluidoJS = <?= json_encode($tiposFluido) ?>;

    // Funções para gerar opções
    function getTipoReservatorioOptions() {
        let options = '<option value="">Selecione...</option>';
        tiposReservatorioJS.forEach(function(item) {
            options += `<option value="${item.CD_CHAVE}">${item.NOME}</option>`;
        });
        return options;
    }

    function getTipoFluidoRadios() {
        let radios = '';
        tiposFluidoJS.forEach(function(item, index) {
            const checked = index === 0 ? 'checked' : '';
            radios += `
                <label class="radio-item-inline">
                    <input type="radio" name="id_produto" value="${item.value}" ${checked}>
                    <span class="radio-label-inline">${item.text}</span>
                </label>
            `;
        });
        return radios;
    }

    $(document).ready(function() {
        // Inicializa Select2
        $('.select2').select2({
            width: '100%',
            placeholder: function() {
                return $(this).data('placeholder') || 'Selecione...';
            }
        });

        <?php if ($isEdicao): ?>
        // Força os valores dos Select2 após inicializar
        $('#selectTipoMedidor').val('<?= $pontoMedicao['ID_TIPO_MEDIDOR'] ?>').trigger('change');
        $('#selectResponsavel').val('<?= $pontoMedicao['CD_USUARIO_RESPONSAVEL'] ?>').trigger('change');
        $('select[name="id_tipo_leitura"]').val('<?= $pontoMedicao['ID_TIPO_LEITURA'] ?>').trigger('change');
        $('select[name="op_periodicidade_leitura"]').val('<?= $pontoMedicao['OP_PERIODICIDADE_LEITURA'] ?>').trigger('change');
        $('select[name="tipo_instalacao"]').val('<?= $pontoMedicao['TIPO_INSTALACAO'] ?>').trigger('change');
        <?php endif; ?>

        // Event: Mudança de Unidade - Carrega Localidades
        $('#selectUnidade').on('change', function() {
            const cdUnidade = $(this).val();
            const selectLocalidade = $('#selectLocalidade');
            
            if (!cdUnidade) {
                selectLocalidade.prop('disabled', true);
                selectLocalidade.html('<option value="">Selecione a Unidade primeiro</option>');
                selectLocalidade.trigger('change');
                return;
            }

            selectLocalidade.prop('disabled', true);
            selectLocalidade.html('<option value="">Carregando...</option>');

            $.ajax({
                url: 'bd/pontoMedicao/getLocalidades.php',
                type: 'GET',
                data: { cd_unidade: cdUnidade },
                dataType: 'json',
                success: function(response) {
                    let options = '<option value="">Selecione a Localidade</option>';
                    
                    if (response.success && response.data.length > 0) {
                        response.data.forEach(function(item) {
                            options += `<option value="${item.CD_CHAVE}">${item.CD_LOCALIDADE} - ${item.DS_NOME}</option>`;
                        });
                    }
                    
                    selectLocalidade.html(options);
                    selectLocalidade.prop('disabled', false);
                    selectLocalidade.trigger('change');
                },
                error: function() {
                    selectLocalidade.html('<option value="">Erro ao carregar</option>');
                    showToast('Erro ao carregar localidades', 'erro');
                }
            });
        });

        // Submit do formulário
        $('#formPontoMedicao').on('submit', function(e) {
            e.preventDefault();
            
            const btnSalvar = $('#btnSalvar');
            btnSalvar.prop('disabled', true).html('<ion-icon name="hourglass-outline"></ion-icon> Salvando...');

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showToast(response.message, 'sucesso');
                        setTimeout(function() {
                            window.location.href = 'pontoMedicao.php';
                        }, 1500);
                    } else {
                        showToast(response.message || 'Erro ao salvar', 'erro');
                        btnSalvar.prop('disabled', false).html('<ion-icon name="save-outline"></ion-icon> Salvar');
                    }
                },
                error: function(xhr, status, error) {
                    showToast('Erro ao comunicar com o servidor', 'erro');
                    btnSalvar.prop('disabled', false).html('<ion-icon name="save-outline"></ion-icon> Salvar');
                }
            });
        });
    });

    // Função para abrir mapa com coordenadas do input
    function abrirMapaCoordenadas() {
        const coordenadas = $('#inputCoordenadas').val();
        
        if (!coordenadas || coordenadas.trim() === '') {
            showToast('Informe as coordenadas primeiro', 'aviso');
            return;
        }

        // Limpa espaços e formata coordenadas
        let coords = coordenadas.trim();
        
        // Remove espaços extras e padroniza separadores
        coords = coords.replace(/\s+/g, '').replace(/;/g, ',');
        
        // Extrai latitude e longitude
        const partes = coords.split(',');
        if (partes.length < 2) {
            showToast('Formato de coordenadas inválido. Use: latitude, longitude', 'erro');
            return;
        }

        const lat = parseFloat(partes[0]);
        const lng = parseFloat(partes[1]);

        if (isNaN(lat) || isNaN(lng)) {
            showToast('Coordenadas inválidas', 'erro');
            return;
        }

        // Validação básica de range
        if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
            showToast('Coordenadas fora do intervalo válido', 'erro');
            return;
        }

        // Abre Google Maps em nova aba
        const url = `https://www.google.com/maps?q=${lat},${lng}&z=17`;
        window.open(url, '_blank');
    }

    // Confirmar exclusão
    function confirmarExclusao() {
        if (confirm('Tem certeza que deseja desativar este ponto de medição?')) {
            $.ajax({
                url: 'bd/pontoMedicao/excluirPontoMedicao.php',
                type: 'POST',
                data: { cd_ponto_medicao: <?= $id ?> },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showToast(response.message, 'sucesso');
                        setTimeout(function() {
                            window.location.href = 'pontoMedicao.php';
                        }, 1500);
                    } else {
                        showToast(response.message || 'Erro ao desativar', 'erro');
                    }
                },
                error: function() {
                    showToast('Erro ao comunicar com o servidor', 'erro');
                }
            });
        }
    }

    <?php if ($isEdicao): ?>
    // ============================================
    // Dados do Equipamento - CRUD Dinâmico
    // ============================================
    let equipamentoData = null;
    
    const tiposMedidorNomes = {
        1: 'Macromedidor',
        2: 'Estação Pitométrica',
        4: 'Medidor de Pressão',
        6: 'Nível Reservatório',
        8: 'Hidrômetro'
    };

    // Lista de tipos de medidor (para select CD_TIPO_MEDIDOR)
    let tiposMedidorEquipLista = [];

    // Carrega tipos de medidor de equipamento ao iniciar
    function carregarTiposMedidorEquip() {
        $.ajax({
            url: 'bd/pontoMedicao/getTiposMedidorEquipamento.php',
            type: 'GET',
            dataType: 'json',
            async: false, // Síncrono para garantir que carrega antes de montar o form
            success: function(response) {
                if (response.success && response.data) {
                    tiposMedidorEquipLista = response.data;
                }
            }
        });
    }
    carregarTiposMedidorEquip();

    // Gera options HTML para select de tipo medidor
    function getTipoMedidorOptions() {
        let options = '<option value="">Selecione o tipo...</option>';
        tiposMedidorEquipLista.forEach(t => {
            options += `<option value="${t.CD_CHAVE}">${t.DS_NOME}</option>`;
        });
        return options;
    }

    function atualizarSecaoEquipamento(tipo) {
        const tabsContainer = $('#tabsContainer');
        
        if (tipo && tipo > 0) {
            tabsContainer.slideDown();
            atualizarCamposEquipamento(tipo);
            carregarEquipamento(tipo);
        } else {
            tabsContainer.slideUp();
            equipamentoData = null;
        }
    }

    function atualizarCamposEquipamento(tipo) {
        let html = '';
        
        switch (tipo) {
            case 1: // Macromedidor
                html = camposMacromedidor();
                break;
            case 2: // Estação Pitométrica
                html = camposEstacaoPitometrica();
                break;
            case 4: // Medidor Pressão
                html = camposMedidorPressao();
                break;
            case 6: // Nível Reservatório
                html = camposNivelReservatorio();
                break;
            case 8: // Hidrômetro
                html = camposHidrometro();
                break;
            default:
                html = '<p class="text-muted">Tipo de equipamento não suportado</p>';
        }
        
        $('#camposEquipamento').html(html);
    }

    function inputField(name, label, icon, type = 'text', placeholder = '', colSize = 4, step = '') {
        const stepAttr = step ? `step="${step}"` : '';
        return `
            <div class="form-group col-${colSize}">
                <label class="form-label">
                    <ion-icon name="${icon}"></ion-icon>
                    ${label}
                </label>
                <input type="${type}" name="${name}" id="equip_${name}" class="form-control" 
                       placeholder="${placeholder}" ${stepAttr}>
            </div>
        `;
    }

    function selectField(name, label, icon, options, colSize = 4) {
        let optionsHtml = '<option value="">Selecione...</option>';
        options.forEach(opt => {
            optionsHtml += `<option value="${opt.value}">${opt.text}</option>`;
        });
        return `
            <div class="form-group col-${colSize}">
                <label class="form-label">
                    <ion-icon name="${icon}"></ion-icon>
                    ${label}
                </label>
                <select name="${name}" id="equip_${name}" class="form-control">
                    ${optionsHtml}
                </select>
            </div>
        `;
    }

    function camposMacromedidor() {
        return `
            <div class="form-row">
                <div class="form-group col-4">
                    <label class="form-label">
                        <ion-icon name="settings-outline"></ion-icon>
                        Tipo de Medidor <span class="required">*</span>
                    </label>
                    <select name="cd_tipo_medidor_equip" id="equip_cd_tipo_medidor" class="form-control" required>
                        ${getTipoMedidorOptions()}
                    </select>
                </div>
                ${inputField('ds_marca', 'Marca', 'pricetag-outline', 'text', 'Ex: Siemens')}
                ${inputField('ds_modelo', 'Modelo', 'cube-outline', 'text', 'Ex: MAG5100W')}
            </div>
            <div class="form-row">
                ${inputField('ds_serie', 'Série', 'barcode-outline', 'text', 'Número de série')}
                ${inputField('ds_tag', 'Tag', 'pricetag-outline', 'text', 'Tag de identificação')}
                ${inputField('dt_fabricacao', 'Data Fabricação', 'calendar-outline', 'date')}
            </div>
            <div class="form-row">
                ${inputField('ds_patrimonio_primario', 'Patrimônio Primário', 'document-outline', 'text', 'Código patrimônio')}
                ${inputField('ds_patrimonio_secundario', 'Patrimônio Secundário', 'document-outline', 'text', 'Código secundário')}
                ${inputField('vl_diametro', 'Diâmetro (mm)', 'resize-outline', 'number', '0.00', 4, '0.01')}
            </div>
            <div class="form-row">
                ${inputField('vl_diametro_rede', 'Diâmetro Rede (mm)', 'git-branch-outline', 'number', '0.00', 4, '0.01')}
                ${inputField('ds_revestimento', 'Revestimento', 'layers-outline', 'text', 'Tipo de revestimento')}
                ${inputField('vl_perda_carga_fabricante', 'Perda Carga Fabricante', 'trending-down-outline', 'number', '0.0000', 4, '0.0001')}
            </div>
            <div class="form-row">
                ${inputField('vl_capacidade_nominal', 'Capacidade Nominal', 'speedometer-outline', 'number', '0.00', 4, '0.01')}
                ${inputField('vl_k_fabricante', 'K Fabricante', 'calculator-outline', 'number', '0.0000', 4, '0.0001')}
                ${inputField('vl_vazao_esperada', 'Vazão Esperada', 'water-outline', 'number', '0.00', 4, '0.01')}
            </div>
            <div class="form-row">
                ${inputField('vl_pressao_maxima', 'Pressão Máxima', 'arrow-up-outline', 'number', '0.00', 4, '0.01')}
                ${inputField('ds_tipo_flange', 'Tipo Flange', 'ellipse-outline', 'text', 'Tipo do flange')}
                ${inputField('ds_altura_soleira', 'Altura Soleira', 'resize-outline', 'text', 'Altura da soleira')}
            </div>
            <div class="form-row">
                ${inputField('ds_natureza_parede', 'Natureza Parede', 'square-outline', 'text', 'Natureza da parede')}
                ${inputField('ds_largura_relativa', 'Largura Relativa', 'resize-outline', 'text', 'Largura relativa')}
                ${inputField('ds_largura_garganta', 'Largura Garganta', 'resize-outline', 'text', 'Largura garganta')}
            </div>
            <div class="form-row">
                ${inputField('vl_cota', 'Cota', 'analytics-outline', 'number', '0.00', 4, '0.01')}
                ${inputField('prot_comun', 'Protocolo de Comunicação', 'radio-outline', 'text', 'Ex: Modbus, HART')}
            </div>
        `;
    }

    function camposEstacaoPitometrica() {
        return `
            <div class="form-row">
                ${inputField('vl_cota_geografica', 'Cota Geográfica', 'location-outline', 'number', '0.00', 4, '0.01')}
                ${inputField('vl_diametro', 'Diâmetro (mm)', 'resize-outline', 'number', '0.00', 4, '0.01')}
                ${inputField('vl_diametro_rede', 'Diâmetro Rede (mm)', 'git-branch-outline', 'number', '0.00', 4, '0.01')}
            </div>
            <div class="form-row">
                ${inputField('ds_linha', 'Linha', 'git-commit-outline', 'text', 'Identificação da linha')}
                ${inputField('ds_sistema', 'Sistema', 'apps-outline', 'text', 'Sistema')}
                ${inputField('ds_revestimento', 'Revestimento', 'layers-outline', 'text', 'Tipo de revestimento')}
            </div>
            <div class="form-row">
                ${inputField('tp_periodicidade_levantamento', 'Periodicidade Levantamento', 'time-outline', 'text', 'Periodicidade', 4)}
            </div>
        `;
    }

    function camposMedidorPressao() {
        return `
            <div class="form-row">
                ${inputField('ds_matricula_usuario', 'Matrícula Usuário', 'person-outline', 'text', 'Matrícula')}
                ${inputField('ds_numero_serie_equipamento', 'Nº Série Equipamento', 'barcode-outline', 'text', 'Número de série')}
                ${inputField('vl_diametro', 'Diâmetro (mm)', 'resize-outline', 'number', '0.00', 4, '0.01')}
            </div>
            <div class="form-row">
                ${inputField('vl_diametro_rede', 'Diâmetro Rede (mm)', 'git-branch-outline', 'number', '0.00', 4, '0.01')}
                ${inputField('ds_material', 'Material', 'construct-outline', 'text', 'Material')}
                ${inputField('ds_cota', 'Cota', 'analytics-outline', 'text', 'Cota')}
            </div>
            <div class="form-row">
                ${selectField('op_telemetria', 'Telemetria', 'radio-outline', [
                    {value: '1', text: 'Sim'},
                    {value: '0', text: 'Não'}
                ])}
                ${inputField('dt_instalacao', 'Data Instalação', 'calendar-outline', 'date')}
                ${inputField('ds_coordenadas', 'Coordenadas', 'navigate-outline', 'text', 'Lat, Long')}
            </div>
            <div class="form-row">
                ${inputField('ds_endereco', 'Endereço', 'location-outline', 'text', 'Endereço completo', 12)}
            </div>
        `;
    }

    function camposNivelReservatorio() {
        return `
            <div class="form-row">
                <div class="form-group col-4">
                    <label class="form-label">
                        <ion-icon name="settings-outline"></ion-icon>
                        Tipo de Medidor <span class="required">*</span>
                    </label>
                    <select name="cd_tipo_medidor_equip" id="equip_cd_tipo_medidor" class="form-control" required>
                        ${getTipoMedidorOptions()}
                    </select>
                </div>
                <div class="form-group col-4">
                    <label class="form-label">
                        <ion-icon name="server-outline"></ion-icon>
                        Tipo de Reservatório
                    </label>
                    <select name="cd_tipo_reservatorio" id="equip_cd_tipo_reservatorio" class="form-control">
                        ${getTipoReservatorioOptions()}
                    </select>
                </div>
                ${inputField('ds_marca', 'Marca', 'pricetag-outline', 'text', 'Ex: Vega')}
            </div>
            <div class="form-row">
                ${inputField('ds_modelo', 'Modelo', 'cube-outline', 'text', 'Modelo')}
                ${inputField('ds_serie', 'Série', 'barcode-outline', 'text', 'Número de série')}
                ${inputField('ds_tag', 'Tag', 'pricetag-outline', 'text', 'Tag de identificação')}
            </div>
            <div class="form-row">
                ${inputField('dt_fabricacao', 'Data Fabricação', 'calendar-outline', 'date')}
                ${inputField('dt_instalacao', 'Data Instalação', 'calendar-outline', 'date')}
                ${inputField('ds_patrimonio_primario', 'Patrimônio Primário', 'document-outline', 'text', 'Código patrimônio')}
            </div>
            <div class="form-row">
                ${inputField('ds_patrimonio_secundario', 'Patrimônio Secundário', 'document-outline', 'text', 'Código secundário')}
                ${inputField('ds_altura_maxima', 'Altura Máxima (m)', 'resize-outline', 'text', 'Altura máxima')}
                ${inputField('vl_na', 'NA', 'water-outline', 'number', '0.00', 4, '0.01')}
            </div>
            <div class="form-row">
                ${inputField('vl_cota', 'Cota', 'analytics-outline', 'number', '0.00', 4, '0.01')}
                ${inputField('cota_extravasamento_m', 'Cota Extravasamento (m)', 'arrow-up-outline', 'number', '0.00', 4, '0.01')}
                ${inputField('cota_extravasamento_p', 'Cota Extravasamento (%)', 'arrow-up-outline', 'number', '0.00', 4, '0.01')}
            </div>
            <div class="form-row">
                ${inputField('vl_volume_total', 'Volume Total (m³)', 'cube-outline', 'number', '0.00', 4, '0.01')}
                ${inputField('vl_volume_camara_a', 'Volume Câmara A (m³)', 'cube-outline', 'number', '0.00', 4, '0.01')}
                ${inputField('vl_volume_camara_b', 'Volume Câmara B (m³)', 'cube-outline', 'number', '0.00', 4, '0.01')}
            </div>
            <div class="form-row">
                ${inputField('vl_pressao_maxima_succao', 'Pressão Máx. Sucção', 'arrow-down-outline', 'number', '0.00', 4, '0.01')}
                ${inputField('vl_pressao_maxima_recalque', 'Pressão Máx. Recalque', 'arrow-up-outline', 'number', '0.00', 4, '0.01')}
            </div>
            <div class="form-row">
                <div class="form-group col-12">
                    <label class="form-label">
                        <ion-icon name="water-outline"></ion-icon>
                        Tipo de Fluido
                    </label>
                    <div class="radio-group-inline">
                        ${getTipoFluidoRadios()}
                    </div>
                </div>
            </div>
        `;
    }

    function camposHidrometro() {
        return `
            <div class="form-row">
                ${inputField('ds_matricula_usuario', 'Matrícula Usuário', 'person-outline', 'text', 'Matrícula')}
                ${inputField('ds_numero_serie_equipamento', 'Nº Série Equipamento', 'barcode-outline', 'text', 'Número de série')}
                ${inputField('vl_diametro', 'Diâmetro (mm)', 'resize-outline', 'number', '0.00', 4, '0.01')}
            </div>
            <div class="form-row">
                ${inputField('vl_diametro_rede', 'Diâmetro Rede (mm)', 'git-branch-outline', 'number', '0.00', 4, '0.01')}
                ${inputField('ds_material', 'Material', 'construct-outline', 'text', 'Material')}
                ${inputField('ds_cota', 'Cota', 'analytics-outline', 'text', 'Cota')}
            </div>
            <div class="form-row">
                ${inputField('dt_instalacao', 'Data Instalação', 'calendar-outline', 'date')}
                ${inputField('ds_coordenadas', 'Coordenadas', 'navigate-outline', 'text', 'Lat, Long')}
                ${inputField('vl_leitura_limite', 'Leitura Limite', 'speedometer-outline', 'number', '0.00', 4, '0.01')}
            </div>
            <div class="form-row">
                ${inputField('vl_multiplicador', 'Multiplicador', 'calculator-outline', 'number', '0.0000', 4, '0.0001')}
                ${inputField('ds_endereco', 'Endereço', 'location-outline', 'text', 'Endereço completo', 8)}
            </div>
        `;
    }

    function carregarEquipamento(tipo) {
        $.ajax({
            url: 'bd/pontoMedicao/getDadosMedidor.php',
            type: 'GET',
            data: { 
                cd_ponto_medicao: <?= $id ?>,
                id_tipo_medidor: tipo
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    equipamentoData = response.data;
                    preencherCamposEquipamento(response.data);
                } else {
                    equipamentoData = null;
                    $('#equipCdChave').val('');
                }
            },
            error: function() {
                showToast('Erro ao carregar dados do equipamento', 'erro');
            }
        });
    }

    function preencherCamposEquipamento(dados) {
        $('#equipCdChave').val(dados.CD_CHAVE || '');
        
        // Preenche todos os campos que existirem
        Object.keys(dados).forEach(function(key) {
            const fieldName = key.toLowerCase();
            const $field = $(`#equip_${fieldName}`);
            
            if ($field.length) {
                let valor = dados[key];
                
                // Formata datas
                if (fieldName.startsWith('dt_') && valor) {
                    valor = valor.split('T')[0]; // Remove parte de hora se existir
                    valor = valor.split(' ')[0]; // Remove hora se formato SQL Server
                }
                
                $field.val(valor);
            }
            
            // Trata radio buttons (ID_PRODUTO / tipo de fluido)
            if (fieldName === 'id_produto') {
                $(`input[name="id_produto"][value="${dados[key] || ''}"]`).prop('checked', true);
            }
        });
    }

    function salvarEquipamento() {
        const btnSalvar = $('#btnSalvarEquip');
        btnSalvar.prop('disabled', true).html('<ion-icon name="hourglass-outline"></ion-icon> Salvando...');

        // Coleta todos os campos do equipamento
        const formData = {
            cd_chave: $('#equipCdChave').val(),
            cd_ponto_medicao: <?= $id ?>,
            id_tipo_medidor: tipoMedidor
        };

        // Adiciona todos os campos com prefixo equip_
        $('#camposEquipamento input:not([type="radio"]), #camposEquipamento select').each(function() {
            const name = $(this).attr('name');
            if (name) {
                formData[name] = $(this).val();
            }
        });

        // Adiciona radio buttons (pega apenas o selecionado)
        $('#camposEquipamento input[type="radio"]:checked').each(function() {
            const name = $(this).attr('name');
            if (name) {
                formData[name] = $(this).val();
            }
        });

        $.ajax({
            url: 'bd/pontoMedicao/salvarDadosMedidor.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                btnSalvar.prop('disabled', false).html('<ion-icon name="save-outline"></ion-icon> Salvar Dados do Equipamento');
                
                if (response.success) {
                    showToast(response.message, 'sucesso');
                    if (response.cd_chave) {
                        $('#equipCdChave').val(response.cd_chave);
                    }
                } else {
                    showToast(response.message || 'Erro ao salvar', 'erro');
                }
            },
            error: function(xhr, status, error) {
                btnSalvar.prop('disabled', false).html('<ion-icon name="save-outline"></ion-icon> Salvar Dados do Equipamento');
                showToast('Erro ao comunicar com o servidor', 'erro');
            }
        });
    }

    // ============================================
    // Meta Mensal - CRUD Dinâmico
    // ============================================
    let tipoMedidor = <?= $pontoMedicao['ID_TIPO_MEDIDOR'] ?? 0 ?>;
    const mesesNomes = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    let metasData = [];

    // Carregar metas ao iniciar
    $(document).ready(function() {
        // Verifica se tipo de medidor é válido
        atualizarSecaoMetas(tipoMedidor);
        atualizarSecaoEquipamento(tipoMedidor);

        // Filtro por ano
        $('#filtroAnoMeta').on('change', function() {
            renderizarTabelaMetas();
        });

        // Mostrar aviso quando não selecionar mês
        $('#metaMes').on('change', function() {
            const aviso = $('#avisoAnoInteiro');
            if ($(this).val() === '') {
                aviso.slideDown();
            } else {
                aviso.slideUp();
            }
        });

        // Evento: Mudança de Tipo de Medidor
        $('#selectTipoMedidor').on('change', function() {
            tipoMedidor = parseInt($(this).val()) || 0;
            atualizarSecaoMetas(tipoMedidor);
            atualizarSecaoEquipamento(tipoMedidor);
        });
    });

    // Handler para navegação entre abas
    $('.tab-btn').on('click', function() {
        const tabId = $(this).data('tab');
        
        // Atualiza botões
        $('.tab-btn').removeClass('active');
        $(this).addClass('active');
        
        // Atualiza painéis
        $('.tab-pane').removeClass('active');
        $('#pane' + tabId.charAt(0).toUpperCase() + tabId.slice(1)).addClass('active');
    });

    function atualizarSecaoMetas(tipo) {
        // Atualiza a aba de metas mesmo sem mostrar/esconder
        if (tipo > 0) {
            atualizarCabecalhoTabela(tipo);
            atualizarCamposModal(tipo);
            $('#metaTipoMedidor').val(tipo);
            carregarMetas();
        } else {
            metasData = [];
            atualizarBadgeMetas();
        }
    }
    
    function atualizarBadgeMetas() {
        $('#badgeMetas').text(metasData.length);
    }

    function atualizarCabecalhoTabela(tipo) {
        let html = '<tr><th style="width: 80px;">Ano</th><th style="width: 80px;">Mês</th>';
        
        if (tipo == 1 || tipo == 2 || tipo == 8) {
            // Macromedidor, Estação Pitométrica, Hidrômetro
            html += '<th>Meta L/S</th>';
        } else if (tipo == 4) {
            // Medidor Pressão
            html += '<th>Pressão Alta (mca)</th><th>Pressão Baixa (mca)</th>';
        } else if (tipo == 6) {
            // Nível Reservatório
            html += '<th>Nível Extrav. %</th><th>Nível Alto</th><th>Nível Baixo</th>';
        } else {
            // Padrão
            html += '<th>Meta L/S</th>';
        }
        
        html += '<th style="width: 100px;">Ações</th></tr>';
        $('#theadMetas').html(html);
    }

    function atualizarCamposModal(tipo) {
        let html = '';
        
        if (tipo == 1 || tipo == 2 || tipo == 8) {
            // Macromedidor, Estação Pitométrica, Hidrômetro
            html = `
                <div class="form-row">
                    <div class="form-group col-12">
                        <label class="form-label">
                            <ion-icon name="water-outline"></ion-icon>
                            Meta L/S <span class="required">*</span>
                        </label>
                        <input type="number" step="0.01" name="vl_meta_l_s" id="metaLS" class="form-control" 
                               placeholder="Ex: 150.00" required>
                    </div>
                </div>
            `;
        } else if (tipo == 4) {
            html = `
                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="form-label">
                            <ion-icon name="arrow-up-outline"></ion-icon>
                            Pressão Alta (mca) <span class="required">*</span>
                        </label>
                        <input type="number" step="0.01" name="vl_meta_pressao_alta" id="metaPressaoAlta" class="form-control" 
                               placeholder="Ex: 40.00" required>
                    </div>
                    <div class="form-group col-6">
                        <label class="form-label">
                            <ion-icon name="arrow-down-outline"></ion-icon>
                            Pressão Baixa (mca) <span class="required">*</span>
                        </label>
                        <input type="number" step="0.01" name="vl_meta_pressao_baixa" id="metaPressaoBaixa" class="form-control" 
                               placeholder="Ex: 10.00" required>
                    </div>
                </div>
            `;
        } else if (tipo == 6) {
            html = `
                <div class="form-row">
                    <div class="form-group col-12">
                        <label class="form-label">
                            <ion-icon name="analytics-outline"></ion-icon>
                            Nível de Extravasamento % <span class="required">*</span>
                        </label>
                        <input type="number" step="0.01" name="vl_meta_nivel_reservatorio" id="metaNivelReservatorio" class="form-control" 
                               placeholder="Ex: 95.00" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="form-label">
                            <ion-icon name="arrow-up-outline"></ion-icon>
                            Nível Alto <span class="required">*</span>
                        </label>
                        <input type="number" step="0.01" name="vl_meta_reservatorio_alta" id="metaReservatorioAlta" class="form-control" 
                               placeholder="Ex: 90.00" required>
                    </div>
                    <div class="form-group col-6">
                        <label class="form-label">
                            <ion-icon name="arrow-down-outline"></ion-icon>
                            Nível Baixo <span class="required">*</span>
                        </label>
                        <input type="number" step="0.01" name="vl_meta_reservatorio_baixa" id="metaReservatorioBaixa" class="form-control" 
                               placeholder="Ex: 20.00" required>
                    </div>
                </div>
            `;
        } else {
            // Padrão - Meta L/S
            html = `
                <div class="form-row">
                    <div class="form-group col-12">
                        <label class="form-label">
                            <ion-icon name="water-outline"></ion-icon>
                            Meta L/S <span class="required">*</span>
                        </label>
                        <input type="number" step="0.01" name="vl_meta_l_s" id="metaLS" class="form-control" 
                               placeholder="Ex: 150.00" required>
                    </div>
                </div>
            `;
        }
        
        $('#camposMetaDinamicos').html(html);
    }

    function carregarMetas() {
        $.ajax({
            url: 'bd/pontoMedicao/getMetasMensais.php',
            type: 'GET',
            data: { cd_ponto_medicao: <?= $id ?> },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Carrega todas as metas do ponto de medição
                    metasData = response.data;
                    renderizarTabelaMetas();
                    atualizarBadgeMetas();
                } else {
                    showToast('Erro ao carregar metas', 'erro');
                }
            },
            error: function() {
                showToast('Erro ao comunicar com o servidor', 'erro');
            }
        });
    }

    function renderizarTabelaMetas() {
        const filtroAno = $('#filtroAnoMeta').val();
        const tbody = $('#tabelaMetas');
        
        // Filtra por ano se selecionado
        let dadosFiltrados = metasData;
        if (filtroAno) {
            dadosFiltrados = metasData.filter(m => m.ANO_META == filtroAno);
        }

        if (dadosFiltrados.length === 0) {
            const colspan = (tipoMedidor == 1 || tipoMedidor == 2 || tipoMedidor == 8) ? 4 : (tipoMedidor == 4 ? 5 : 6);
            tbody.html(`
                <tr>
                    <td colspan="${colspan}">
                        <div class="empty-state-mini">
                            <ion-icon name="flag-outline"></ion-icon>
                            Nenhuma meta cadastrada
                        </div>
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        dadosFiltrados.forEach(meta => {
            html += '<tr>';
            html += `<td>${meta.ANO_META}</td>`;
            html += `<td><span class="mes-nome">${mesesNomes[meta.MES_META] || '-'}</span></td>`;
            
            if (tipoMedidor == 1 || tipoMedidor == 2 || tipoMedidor == 8) {
                html += `<td>${formatNumber(meta.VL_META_L_S)}</td>`;
            } else if (tipoMedidor == 4) {
                html += `<td>${formatNumber(meta.VL_META_PRESSAO_ALTA)}</td>`;
                html += `<td>${formatNumber(meta.VL_META_PRESSAO_BAIXA)}</td>`;
            } else if (tipoMedidor == 6) {
                html += `<td>${formatNumber(meta.VL_META_NIVEL_RESERVATORIO)}</td>`;
                html += `<td>${formatNumber(meta.VL_META_RESERVATORIO_ALTA)}</td>`;
                html += `<td>${formatNumber(meta.VL_META_RESERVATORIO_BAIXA)}</td>`;
            } else {
                // Padrão
                html += `<td>${formatNumber(meta.VL_META_L_S)}</td>`;
            }

            html += `
                <td>
                    <button type="button" class="btn-action" onclick="editarMeta(${meta.CD_CHAVE})" title="Editar">
                        <ion-icon name="create-outline"></ion-icon>
                    </button>
                    <button type="button" class="btn-action delete" onclick="excluirMeta(${meta.CD_CHAVE})" title="Excluir">
                        <ion-icon name="trash-outline"></ion-icon>
                    </button>
                </td>
            `;
            html += '</tr>';
        });

        tbody.html(html);
    }

    function formatNumber(value) {
        if (value === null || value === undefined || value === '') return '-';
        return parseFloat(value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function abrirModalMeta() {
        // Limpa o formulário
        $('#formMeta')[0].reset();
        $('#metaCdChave').val('');
        $('#metaTipoMedidor').val(tipoMedidor);
        $('#metaAno').val(new Date().getFullYear());
        $('#metaMes').val('');
        $('#avisoAnoInteiro').show();
        $('#modalMetaTitulo').text('Nova Meta');
        $('#modalMeta').addClass('active');
    }

    function fecharModalMeta() {
        $('#modalMeta').removeClass('active');
    }

    function editarMeta(cdChave) {
        const meta = metasData.find(m => m.CD_CHAVE == cdChave);
        if (!meta) return;

        $('#metaCdChave').val(meta.CD_CHAVE);
        $('#metaTipoMedidor').val(tipoMedidor);
        $('#metaAno').val(meta.ANO_META);
        $('#metaMes').val(meta.MES_META || '');
        $('#avisoAnoInteiro').toggle(meta.MES_META === null || meta.MES_META === '');

        if (tipoMedidor == 1 || tipoMedidor == 2 || tipoMedidor == 8) {
            $('#metaLS').val(meta.VL_META_L_S);
        } else if (tipoMedidor == 4) {
            $('#metaPressaoAlta').val(meta.VL_META_PRESSAO_ALTA);
            $('#metaPressaoBaixa').val(meta.VL_META_PRESSAO_BAIXA);
        } else if (tipoMedidor == 6) {
            $('#metaNivelReservatorio').val(meta.VL_META_NIVEL_RESERVATORIO);
            $('#metaReservatorioAlta').val(meta.VL_META_RESERVATORIO_ALTA);
            $('#metaReservatorioBaixa').val(meta.VL_META_RESERVATORIO_BAIXA);
        } else {
            // Padrão
            $('#metaLS').val(meta.VL_META_L_S);
        }

        $('#modalMetaTitulo').text('Editar Meta');
        $('#modalMeta').addClass('active');
    }

    function salvarMeta() {
        const form = $('#formMeta');
        const btnSalvar = $('#btnSalvarMeta');
        
        // Validação básica
        const ano = $('#metaAno').val();
        if (!ano) {
            showToast('Selecione o ano', 'erro');
            return;
        }

        // Confirmação se for ano inteiro
        const mes = $('#metaMes').val();
        if (!mes) {
            if (!confirm('A meta será aplicada para todos os 12 meses do ano ' + ano + '. Deseja continuar?')) {
                return;
            }
        }

        btnSalvar.prop('disabled', true).html('<ion-icon name="hourglass-outline"></ion-icon> Salvando...');

        $.ajax({
            url: 'bd/pontoMedicao/salvarMetaMensal.php',
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                btnSalvar.prop('disabled', false).html('<ion-icon name="save-outline"></ion-icon> Salvar Meta');
                
                if (response.success) {
                    showToast(response.message, 'sucesso');
                    fecharModalMeta();
                    carregarMetas();
                } else {
                    showToast(response.message || 'Erro ao salvar', 'erro');
                }
            },
            error: function() {
                btnSalvar.prop('disabled', false).html('<ion-icon name="save-outline"></ion-icon> Salvar Meta');
                showToast('Erro ao comunicar com o servidor', 'erro');
            }
        });
    }

    function excluirMeta(cdChave) {
        if (!confirm('Tem certeza que deseja excluir esta meta?')) {
            return;
        }

        $.ajax({
            url: 'bd/pontoMedicao/excluirMetaMensal.php',
            type: 'POST',
            data: { cd_chave: cdChave },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast(response.message, 'sucesso');
                    carregarMetas();
                } else {
                    showToast(response.message || 'Erro ao excluir', 'erro');
                }
            },
            error: function() {
                showToast('Erro ao comunicar com o servidor', 'erro');
            }
        });
    }

    // Fechar modal ao clicar fora
    $('#modalMeta').on('click', function(e) {
        if (e.target === this) {
            fecharModalMeta();
        }
    });

    // Fechar modal com ESC
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#modalMeta').hasClass('active')) {
            fecharModalMeta();
        }
    });
    <?php endif; ?>
</script>

<?php include_once 'includes/footer.inc.php'; ?>
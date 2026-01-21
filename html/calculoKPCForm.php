<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Formulário de Cálculo de KPC
 * 
 * Tela para inclusão e edição de cálculos de KPC (Constante Pitométrica).
 * Utiliza metodologia de medição pitométrica com leituras em múltiplas posições.
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão para editar Cálculo de KPC
$temPermissao = temPermissaoTela('Cálculo do KPC', ACESSO_ESCRITA);

if (!$temPermissao) {
    $_SESSION['msg'] = 'Você não tem permissão para acessar esta funcionalidade.';
    header('Location: calculoKPC.php');
    exit;
}

// Verifica se é edição
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdicao = $id > 0;
$calculoKPC = null;
$leituras = [];

// Se for edição, busca os dados
if ($isEdicao) {
    // Buscar dados do cálculo
    $sql = "SELECT 
                CK.*,
                PM.DS_NOME AS DS_PONTO_MEDICAO,
                PM.ID_TIPO_MEDIDOR, 
                L.CD_CHAVE AS CD_CHAVE_LOCALIDADE,
                L.CD_LOCALIDADE,
                L.DS_NOME AS DS_LOCALIDADE,
                U.CD_UNIDADE,
                U.DS_NOME AS DS_UNIDADE,
                TR.DS_NOME AS DS_TECNICO_RESPONSAVEL,
                UR.DS_NOME AS DS_USUARIO_RESPONSAVEL,
                UA.DS_NOME AS DS_USUARIO_ATUALIZACAO
            FROM SIMP.dbo.CALCULO_KPC CK
            INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON CK.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
            INNER JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
            LEFT JOIN SIMP.dbo.USUARIO TR ON CK.CD_TECNICO_RESPONSAVEL = TR.CD_USUARIO
            LEFT JOIN SIMP.dbo.USUARIO UR ON CK.CD_USUARIO_RESPONSAVEL = UR.CD_USUARIO
            LEFT JOIN SIMP.dbo.USUARIO UA ON CK.CD_USUARIO_ULTIMA_ATUALIZACAO = UA.CD_USUARIO
            WHERE CK.CD_CHAVE = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    $calculoKPC = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$calculoKPC) {
        $_SESSION['msg'] = 'Cálculo de KPC não encontrado.';
        $_SESSION['msg_tipo'] = 'erro';
        header('Location: calculoKPC.php');
        exit;
    }

    // Buscar leituras
    $sqlLeituras = "SELECT * FROM SIMP.dbo.CALCULO_KPC_LEITURA WHERE CD_CALCULO_KPC = :id ORDER BY CD_POSICAO_LEITURA, CD_ORDEM_LEITURA";
    $stmtLeituras = $pdoSIMP->prepare($sqlLeituras);
    $stmtLeituras->execute([':id' => $id]);
    $leituras = $stmtLeituras->fetchAll(PDO::FETCH_ASSOC);
}

// Buscar Unidades
$sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

// Buscar Técnicos (usuários ativos que podem ser técnicos)
$sqlTecnicos = $pdoSIMP->query("SELECT CD_USUARIO, DS_NOME, DS_MATRICULA FROM SIMP.dbo.USUARIO WHERE OP_BLOQUEADO = 2 ORDER BY DS_NOME");
$tecnicos = $sqlTecnicos->fetchAll(PDO::FETCH_ASSOC);

// Buscar Usuários para Responsável
$sqlUsuarios = $pdoSIMP->query("SELECT CD_USUARIO, DS_NOME, DS_MATRICULA FROM SIMP.dbo.USUARIO WHERE OP_BLOQUEADO = 2 ORDER BY DS_NOME");
$usuarios = $sqlUsuarios->fetchAll(PDO::FETCH_ASSOC);

// Métodos de medição
$metodos = [
    1 => 'Digital',
    2 => 'Convencional'
];

// Código formatado
$codigoFormatado = $isEdicao ? $calculoKPC['CD_CODIGO'] . '-' . $calculoKPC['CD_ANO'] : 'Novo';

// Código formatado do ponto (para edição)
$codigoPontoFormatado = '';
if ($isEdicao) {
    // Mapeamento de tipos para letras
    $letrasTipoMedidor = [
        1 => 'M', // Macromedidor
        2 => 'E', // Estação Pitométrica
        4 => 'P', // Medidor Pressão
        6 => 'R', // Nível Reservatório
        8 => 'H'  // Hidrômetro
    ];

    // Obtém a letra do tipo de medidor (necessário buscar PM.ID_TIPO_MEDIDOR na query de edição)
    // Se não tiver ID_TIPO_MEDIDOR, consulte a tabela PONTO_MEDICAO
    $letraTipo = $letrasTipoMedidor[$calculoKPC['ID_TIPO_MEDIDOR']] ?? 'X';

    $codigoPontoFormatado = $calculoKPC['CD_LOCALIDADE'] . '-' .
        str_pad($calculoKPC['CD_PONTO_MEDICAO'], 6, '0', STR_PAD_LEFT) . '-' .
        $letraTipo . '-' .
        $calculoKPC['CD_UNIDADE'];
}
?>

<!-- Select2 para busca nos selects -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

<style>
    /* ============================================
       Select2 Customização
       ============================================ */
    .select2-container {
        width: 100% !important;
    }

    .select2-container--default .select2-selection--single {
        height: 42px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 6px 12px;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 28px;
        color: #1e293b;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px;
    }

    .select2-container--default.select2-container--focus .select2-selection--single {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .select2-dropdown {
        border-color: #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .select2-search--dropdown .select2-search__field {
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 8px 12px;
    }

    .select2-results__option--highlighted[aria-selected] {
        background-color: #3b82f6;
    }

    /* ============================================
       Page Container
       ============================================ */
    .page-container {
        padding: 20px;
        max-width: 1400px;
        margin: 0 auto;
    }

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
    }

    .page-header-subtitle {
        font-size: 11px;
        color: rgba(255, 255, 255, 0.7);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .page-header-subtitle .codigo {
        background: rgba(255, 255, 255, 0.2);
        padding: 3px 8px;
        border-radius: 4px;
        font-family: monospace;
        font-weight: 600;
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

    /* ============================================
       Form Cards
       ============================================ */
    .form-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: visible;
        margin-bottom: 16px;
    }

    .form-card-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 20px;
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
        padding: 20px;
    }

    /* ============================================
       Form Grid
       ============================================ */
    .form-row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -8px 16px -8px;
    }

    .form-row:last-child {
        margin-bottom: 0;
    }

    .form-group {
        padding: 0 8px;
        margin-bottom: 0;
        display: flex;
        flex-direction: column;
        gap: 6px;
        box-sizing: border-box;
    }

    .col-12 {
        width: 100%;
    }

    .col-8 {
        width: 66.666667%;
    }

    .col-6 {
        width: 50%;
    }

    .col-4 {
        width: 33.333333%;
    }

    .col-3 {
        width: 25%;
    }

    .col-2 {
        width: 16.666667%;
    }

    .form-label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 600;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .form-label .required {
        color: #ef4444;
    }

    .form-label ion-icon {
        font-size: 14px;
        color: #94a3b8;
    }

    .form-control {
        width: 100%;
        padding: 10px 12px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-family: inherit;
        font-size: 13px;
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

    .form-control.resultado {
        background-color: #ecfdf5;
        border-color: #10b981;
        color: #047857;
        font-weight: 600;
    }

    textarea.form-control {
        min-height: 60px;
        resize: vertical;
    }

    /* ============================================
       Autocomplete Ponto de Medição
       ============================================ */
    .autocomplete-container {
        position: relative;
    }

    .autocomplete-container input.form-control {
        padding-right: 40px;
    }

    .autocomplete-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #e2e8f0;
        border-top: none;
        border-radius: 0 0 8px 8px;
        max-height: 250px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .autocomplete-dropdown.active {
        display: block;
    }

    .autocomplete-item {
        padding: 10px 14px;
        cursor: pointer;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }

    .autocomplete-item:last-child {
        border-bottom: none;
    }

    .autocomplete-item:hover,
    .autocomplete-item.highlighted {
        background-color: #3b82f6;
        color: white;
    }

    .autocomplete-item .item-code {
        font-family: 'SF Mono', Monaco, monospace;
        font-size: 12px;
        color: #64748b;
        display: block;
    }

    .autocomplete-item:hover .item-code,
    .autocomplete-item.highlighted .item-code {
        color: rgba(255, 255, 255, 0.8);
    }

    .autocomplete-item .item-name {
        display: block;
        margin-top: 2px;
    }

    .autocomplete-loading,
    .autocomplete-empty {
        padding: 12px;
        text-align: center;
        color: #94a3b8;
        font-size: 13px;
    }

    .btn-limpar-autocomplete {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        padding: 4px;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .btn-limpar-autocomplete:hover {
        color: #ef4444;
    }

    .btn-limpar-autocomplete ion-icon {
        font-size: 18px;
    }

    /* ============================================
       Tabela de Leituras
       ============================================ */
    .leituras-container {
        overflow-x: auto;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }

    .leituras-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
        min-width: 900px;
    }

    .leituras-table th,
    .leituras-table td {
        padding: 4px 6px;
        border: 1px solid #d1d5db;
        text-align: center;
        white-space: nowrap;
    }

    .leituras-table thead th {
        background: #dbeafe;
        font-weight: 600;
        color: #1e40af;
        font-size: 11px;
    }

    .leituras-table thead tr.row-posicao th {
        background: #eff6ff;
        color: #3b82f6;
        font-weight: 500;
        font-size: 10px;
    }

    .leituras-table .col-pontos,
    .leituras-table .leitura-label,
    .leituras-table .media-label {
        background: #f1f5f9;
        font-weight: 600;
        color: #475569;
        text-align: right;
        padding-right: 10px;
        min-width: 100px;
    }

    .leituras-table .media-row td {
        background: #fef3c7;
    }

    .leituras-table .media-label {
        background: #fcd34d;
        color: #92400e;
    }

    .leituras-table input.leitura-input {
        width: 60px;
        padding: 4px 6px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        text-align: right;
        font-size: 11px;
        background: #fff;
    }

    .leituras-table input.leitura-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
    }

    .leituras-table input.media-input {
        width: 60px;
        padding: 4px 6px;
        border: 1px solid #f59e0b;
        border-radius: 4px;
        text-align: right;
        font-size: 11px;
        font-weight: 600;
        background: #fef3c7;
        color: #92400e;
    }

    /* ============================================
       Botões de Ação
       ============================================ */
    .form-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        padding: 20px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        border-radius: 0 0 12px 12px;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
        text-decoration: none;
    }

    .btn-primary {
        background: #3b82f6;
        color: white;
    }

    .btn-primary:hover {
        background: #2563eb;
    }

    .btn-success {
        background: #10b981;
        color: white;
    }

    .btn-success:hover {
        background: #059669;
    }

    .btn-secondary {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .btn-secondary:hover {
        background: #e2e8f0;
    }

    .btn-calcular {
        background: #8b5cf6;
        color: white;
    }

    .btn-calcular:hover {
        background: #7c3aed;
    }

    /* ============================================
       Campo de Método Convencional
       ============================================ */
    .campo-convencional {
        display: none;
    }

    .campo-convencional.visivel {
        display: flex;
    }

    /* ============================================
       Toast Styles
       ============================================ */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .toast {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        min-width: 300px;
        max-width: 400px;
        transform: translateX(120%);
        transition: transform 0.3s ease;
    }

    .toast.show {
        transform: translateX(0);
    }

    .toast.sucesso {
        border-left: 4px solid #10b981;
    }

    .toast.sucesso .toast-icon {
        color: #10b981;
    }

    .toast.erro {
        border-left: 4px solid #ef4444;
    }

    .toast.erro .toast-icon {
        color: #ef4444;
    }

    .toast.alerta {
        border-left: 4px solid #f59e0b;
    }

    .toast.alerta .toast-icon {
        color: #f59e0b;
    }

    .toast.info {
        border-left: 4px solid #3b82f6;
    }

    .toast.info .toast-icon {
        color: #3b82f6;
    }

    .toast-icon {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .toast-content {
        flex: 1;
    }

    .toast-message {
        margin: 0;
        font-size: 14px;
        color: #1e293b;
    }

    .toast-close {
        background: none;
        border: none;
        padding: 4px;
        cursor: pointer;
        color: #94a3b8;
        font-size: 18px;
    }

    .toast-close:hover {
        color: #475569;
    }

    /* ============================================
       Responsivo
       ============================================ */
    @media (max-width: 992px) {

        .col-2,
        .col-3 {
            width: 33.333333%;
        }

        .col-4 {
            width: 50%;
        }
    }

    @media (max-width: 768px) {
        .page-container {
            padding: 12px;
        }

        .col-2,
        .col-3,
        .col-4,
        .col-6 {
            width: 100%;
        }

        .form-actions {
            flex-direction: column;
        }

        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }

    /* Botão Ver Gráfico */
    .btn-grafico {
        background: #c9a227;
        color: white;
    }

    .btn-grafico:hover {
        background: #b8931f;
    }

    /* Modal do Gráfico */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-overlay.ativo {
        display: flex;
    }

    .modal-grafico {
        background: white;
        border-radius: 12px;
        width: 100%;
        max-width: 800px;
        max-height: 90vh;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: modalEntrada 0.3s ease;
    }

    @keyframes modalEntrada {
        from {
            opacity: 0;
            transform: scale(0.9) translateY(-20px);
        }

        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .modal-grafico-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        background: linear-gradient(135deg, #c9a227 0%, #b8931f 100%);
        color: white;
    }

    .modal-grafico-header h3 {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
        font-size: 18px;
        font-weight: 600;
    }

    .modal-grafico-header h3 ion-icon {
        font-size: 22px;
    }

    .modal-grafico-fechar {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
    }

    .modal-grafico-fechar:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    .modal-grafico-fechar ion-icon {
        font-size: 20px;
    }

    .modal-grafico-body {
        padding: 20px;
    }

    .modal-grafico-body .grafico-container {
        height: 400px;
        position: relative;
    }

    .modal-grafico-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }

    .grafico-legenda {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: #64748b;
    }

    .legenda-ponto {
        width: 12px;
        height: 12px;
        background: #c9a227;
        border-radius: 50%;
        border: 2px solid white;
        box-shadow: 0 0 0 1px #c9a227;
    }
</style>

<div class="page-container">
    <!-- Header da Página -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="calculator-outline"></ion-icon>
                </div>
                <div>
                    <h1><?= $isEdicao ? 'Editar Cálculo de KPC' : 'Novo Cálculo de KPC' ?></h1>
                    <p class="page-header-subtitle">
                        <?php if ($isEdicao): ?>
                            Código: <span class="codigo"><?= $codigoFormatado ?></span>
                        <?php else: ?>
                            Preencha os dados para realizar o cálculo
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <a href="calculoKPC.php" class="btn-voltar">
                <ion-icon name="arrow-back-outline"></ion-icon>
                Voltar
            </a>
        </div>
    </div>

    <form id="formCalculoKPC" method="POST">
        <input type="hidden" name="cd_chave" value="<?= $isEdicao ? $calculoKPC['CD_CHAVE'] : 0 ?>">
        <input type="hidden" name="cd_ponto_medicao" id="cdPontoMedicao"
            value="<?= $isEdicao ? $calculoKPC['CD_PONTO_MEDICAO'] : '' ?>">

        <!-- Dados do Ponto de Medição -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="location-outline"></ion-icon>
                <h2>Ponto de Medição</h2>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="business-outline"></ion-icon>
                            Unidade <span class="required">*</span>
                        </label>
                        <select id="selectUnidade" class="form-control" <?= $isEdicao ? 'disabled' : '' ?>>
                            <option value="">Selecione...</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?= $u['CD_UNIDADE'] ?>" <?= ($isEdicao && $calculoKPC['CD_UNIDADE'] == $u['CD_UNIDADE']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['DS_NOME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="map-outline"></ion-icon>
                            Localidade <span class="required">*</span>
                        </label>
                        <select id="selectLocalidade" class="form-control" <?= $isEdicao ? 'disabled' : '' ?>>
                            <option value="">Selecione a unidade primeiro</option>
                            <?php if ($isEdicao): ?>
                                <option value="<?= $calculoKPC['CD_CHAVE_LOCALIDADE'] ?>" selected>
                                    <?= $calculoKPC['CD_LOCALIDADE'] ?> -
                                    <?= htmlspecialchars($calculoKPC['DS_LOCALIDADE']) ?>
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="speedometer-outline"></ion-icon>
                            Ponto de Medição <span class="required">*</span>
                        </label>
                        <?php if ($isEdicao): ?>
                            <input type="text" class="form-control" readonly
                                value="<?= $codigoPontoFormatado ?> - <?= htmlspecialchars($calculoKPC['DS_PONTO_MEDICAO']) ?>">
                        <?php else: ?>
                            <div class="autocomplete-container">
                                <input type="text" class="form-control" id="inputPontoMedicao"
                                    placeholder="Digite para buscar..." autocomplete="off">
                                <button type="button" class="btn-limpar-autocomplete" id="btnLimparPonto">
                                    <ion-icon name="close-circle"></ion-icon>
                                </button>
                                <div class="autocomplete-dropdown" id="pontoMedicaoDropdown"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="calendar-outline"></ion-icon>
                            Data da Leitura <span class="required">*</span>
                        </label>
                        <input type="date" name="dt_leitura" class="form-control" required
                            value="<?= $isEdicao ? date('Y-m-d', strtotime($calculoKPC['DT_LEITURA'])) : date('Y-m-d') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Parâmetros do Cálculo -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="options-outline"></ion-icon>
                <h2>Parâmetros do Cálculo</h2>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="git-branch-outline"></ion-icon>
                            Método <span class="required">*</span>
                        </label>
                        <select id="selectMetodo" name="id_metodo" class="form-control" required
                            onchange="toggleCampoConvencional()">
                            <?php foreach ($metodos as $id => $nome): ?>
                                <option value="<?= $id ?>" <?= ($isEdicao && $calculoKPC['ID_METODO'] == $id) ? 'selected' : '' ?>>
                                    <?= $nome ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="resize-outline"></ion-icon>
                            Diâmetro Nominal (mm) <span class="required">*</span>
                        </label>
                        <input type="number" step="0.01" name="vl_diametro_nominal" id="vlDiametroNominal"
                            class="form-control" required
                            value="<?= $isEdicao ? $calculoKPC['VL_DIAMETRO_NOMINAL'] : '' ?>" onchange="calcular()">
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="resize-outline"></ion-icon>
                            Diâmetro Real (mm) <span class="required">*</span>
                        </label>
                        <input type="number" step="0.01" name="vl_diametro_real" id="vlDiametroReal"
                            class="form-control" required
                            value="<?= $isEdicao ? $calculoKPC['VL_DIAMETRO_REAL'] : '' ?>"
                            onchange="atualizarPosicoes(); calcular()">
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="analytics-outline"></ion-icon>
                            Projeção da TAP (mm) <span class="required">*</span>
                        </label>
                        <input type="number" step="0.01" name="vl_projecao_tap" id="vlProjecaoTap" class="form-control"
                            required value="<?= $isEdicao ? $calculoKPC['VL_PROJECAO_TAP'] : '' ?>"
                            onchange="calcular()">
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="ellipse-outline"></ion-icon>
                            Raio do TIP (mm) <span class="required">*</span>
                        </label>
                        <input type="number" step="0.01" name="vl_raio_tip" id="vlRaioTip" class="form-control" required
                            value="<?= $isEdicao ? $calculoKPC['VL_RAIO_TIP'] : '' ?>" onchange="calcular()">
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">
                            <ion-icon name="thermometer-outline"></ion-icon>
                            Temperatura (°C) <span class="required">*</span>
                        </label>
                        <input type="number" step="0.1" name="vl_temperatura" id="vlTemperatura" class="form-control"
                            required value="<?= $isEdicao ? $calculoKPC['VL_TEMPERATURA'] : '25' ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-2" id="campoUltimaPosicao">
                        <label class="form-label">
                            <ion-icon name="locate-outline"></ion-icon>
                            Última Posição (mm) <span class="required">*</span>
                        </label>
                        <?php
                        $ultimaPosicao = '';
                        if ($isEdicao && !empty($leituras)) {
                            foreach ($leituras as $l) {
                                if ($l['CD_POSICAO_LEITURA'] == 11 && !empty($l['VL_POSICAO'])) {
                                    $ultimaPosicao = $l['VL_POSICAO'];
                                    break;
                                }
                            }
                        }
                        ?>
                        <input type="number" step="0.01" name="vl_ultima_posicao" id="vlUltimaPosicao"
                            class="form-control" required value="<?= $ultimaPosicao ?>" onchange="atualizarPosicoes()">
                    </div>
                </div>
            </div>
        </div>

        <!-- Responsáveis -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="people-outline"></ion-icon>
                <h2>Responsáveis</h2>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="construct-outline"></ion-icon>
                            Técnico Responsável <span class="required">*</span>
                        </label>
                        <select name="cd_tecnico_responsavel" id="selectTecnico" class="form-control select2-tecnico"
                            required>
                            <option value="">Selecione...</option>
                            <?php foreach ($tecnicos as $t): ?>
                                <option value="<?= $t['CD_USUARIO'] ?>" <?= ($isEdicao && $calculoKPC['CD_TECNICO_RESPONSAVEL'] == $t['CD_USUARIO']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['DS_NOME']) ?>
                                    <?= $t['DS_MATRICULA'] ? '(' . $t['DS_MATRICULA'] . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="person-outline"></ion-icon>
                            Usuário Responsável <span class="required">*</span>
                        </label>
                        <select name="cd_usuario_responsavel" id="selectUsuario" class="form-control select2-usuario"
                            required>
                            <option value="">Selecione...</option>
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?= $u['CD_USUARIO'] ?>" <?= ($isEdicao && $calculoKPC['CD_USUARIO_RESPONSAVEL'] == $u['CD_USUARIO']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['DS_NOME']) ?>
                                    <?= $u['DS_MATRICULA'] ? '(' . $u['DS_MATRICULA'] . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="chatbox-outline"></ion-icon>
                            Observação
                        </label>
                        <input type="text" name="ds_observacao" class="form-control" maxlength="200"
                            value="<?= $isEdicao ? htmlspecialchars($calculoKPC['DS_OBSERVACAO']) : '' ?>"
                            placeholder="Observações adicionais...">
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela de Leituras -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="list-outline"></ion-icon>
                <h2>Leituras da Deflexão (mm)</h2>
            </div>
            <div class="form-card-body">
                <div class="leituras-container">
                    <table class="leituras-table" id="tabelaLeituras">
                        <thead>
                            <tr>
                                <th class="col-pontos">Pontos:</th>
                                <?php for ($p = 1; $p <= 11; $p++): ?>
                                    <th class="col-ponto"><?= $p ?></th>
                                <?php endfor; ?>
                            </tr>
                            <tr class="row-posicao">
                                <th>Posição(mm):</th>
                                <?php
                                $diametroReal = $isEdicao ? $calculoKPC['VL_DIAMETRO_REAL'] : 0;
                                $ultimaPosicaoValor = $ultimaPosicao ?: 0;

                                for ($p = 1; $p <= 11; $p++):
                                    if ($p == 1) {
                                        $posCalc = 0;
                                    } elseif ($p == 11) {
                                        $posCalc = $ultimaPosicaoValor;
                                    } else {
                                        $posCalc = ($diametroReal / 10) * ($p - 1);
                                    }
                                    ?>
                                    <th id="posicao_<?= $p ?>"><?= number_format($posCalc, 2, ',', '.') ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $leiturasOrganizadas = [];
                            foreach ($leituras as $l) {
                                $leiturasOrganizadas[$l['CD_ORDEM_LEITURA']][$l['CD_POSICAO_LEITURA']] = $l['VL_DEFLEXAO_MEDIDA'];
                            }

                            for ($leitura = 1; $leitura <= 20; $leitura++):
                                ?>
                                <tr>
                                    <td class="leitura-label">Leitura <?= $leitura ?>:</td>
                                    <?php for ($ponto = 1; $ponto <= 11; $ponto++): ?>
                                        <td>
                                            <input type="number" step="0.01" name="leitura[<?= $leitura ?>][<?= $ponto ?>]"
                                                class="leitura-input" data-leitura="<?= $leitura ?>" data-ponto="<?= $ponto ?>"
                                                value="<?= isset($leiturasOrganizadas[$leitura][$ponto]) ? number_format($leiturasOrganizadas[$leitura][$ponto], 2, '.', '') : '' ?>"
                                                onchange="calcularMediaPonto(<?= $ponto ?>)">
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endfor; ?>
                            <tr class="media-row">
                                <td class="media-label">Média das Deflexões:</td>
                                <?php for ($ponto = 1; $ponto <= 11; $ponto++): ?>
                                    <td>
                                        <input type="text" name="media[<?= $ponto ?>]" id="media_<?= $ponto ?>"
                                            class="media-input" readonly
                                            value="<?= isset($leiturasOrganizadas[21][$ponto]) ? number_format($leiturasOrganizadas[21][$ponto], 2, '.', '') : '' ?>">
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 16px; text-align: right; display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-grafico" onclick="abrirGraficoCurvaVelocidade()">
                        <ion-icon name="stats-chart-outline"></ion-icon>
                        Ver Gráfico
                    </button>
                    <button type="button" class="btn btn-calcular" onclick="calcular()">
                        <ion-icon name="calculator-outline"></ion-icon>
                        Calcular KPC
                    </button>
                </div>
            </div>
        </div>

        <!-- Resultados do Cálculo -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="analytics-outline"></ion-icon>
                <h2>Resultados do Cálculo</h2>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group col-2">
                        <label class="form-label">Fator de Velocidade</label>
                        <input type="text" name="vl_fator_velocidade" id="vlFatorVelocidade"
                            class="form-control resultado" readonly
                            value="<?= $isEdicao ? number_format($calculoKPC['VL_FATOR_VELOCIDADE'], 10, '.', '') : '' ?>">
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">Correção Diâmetro</label>
                        <input type="text" name="vl_correcao_diametro" id="vlCorrecaoDiametro"
                            class="form-control resultado" readonly
                            value="<?= $isEdicao ? number_format($calculoKPC['VL_CORRECAO_DIAMETRO'], 6, '.', '') : '' ?>">
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">Correção Projeção TAP</label>
                        <input type="text" name="vl_correcao_projecao_tap" id="vlCorrecaoProjecaoTap"
                            class="form-control resultado" readonly
                            value="<?= $isEdicao ? number_format($calculoKPC['VL_CORRECAO_PROJECAO_TAP'], 2, '.', '') : '' ?>">
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">Área Efetiva (m²)</label>
                        <input type="text" name="vl_area_efetiva" id="vlAreaEfetiva" class="form-control resultado"
                            readonly
                            value="<?= $isEdicao ? number_format($calculoKPC['VL_AREA_EFETIVA'], 6, '.', '') : '' ?>">
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">KPC</label>
                        <input type="text" name="vl_kpc" id="vlKPC" class="form-control resultado" readonly
                            value="<?= $isEdicao ? number_format($calculoKPC['VL_KPC'], 10, '.', '') : '' ?>">
                    </div>
                    <div class="form-group col-2">
                        <label class="form-label">Vazão (L/s)</label>
                        <input type="text" name="vl_vazao" id="vlVazao" class="form-control resultado" readonly
                            value="<?= $isEdicao && $calculoKPC['VL_VAZAO'] ? number_format($calculoKPC['VL_VAZAO'], 2, '.', '') : '' ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Ações -->
        <div class="form-card">
            <div class="form-actions">
                <a href="calculoKPC.php" class="btn btn-secondary">
                    <ion-icon name="close-outline"></ion-icon>
                    Cancelar
                </a>
                <button type="submit" class="btn btn-success">
                    <ion-icon name="save-outline"></ion-icon>
                    Salvar
                </button>
            </div>
        </div>
    </form>
</div>

<div id="toastContainer" class="toast-container"></div>

<!-- Modal do Gráfico Curva de Velocidade -->
<div class="modal-overlay" id="modalGraficoOverlay" onclick="fecharModalGrafico(event)">
    <div class="modal-grafico" onclick="event.stopPropagation()">
        <div class="modal-grafico-header">
            <h3>
                <ion-icon name="stats-chart-outline"></ion-icon>
                Curva de Velocidade
            </h3>
            <button type="button" class="modal-grafico-fechar" onclick="fecharModalGrafico()">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-grafico-body">
            <div class="grafico-container">
                <canvas id="graficoVelocidade"></canvas>
            </div>
        </div>
        <div class="modal-grafico-footer">
            <span class="grafico-legenda">
                <span class="legenda-ponto"></span>
                Posição x Deflexão Média
            </span>
            <button type="button" class="btn btn-secondary" onclick="fecharModalGrafico()">
                Fechar
            </button>
        </div>
    </div>
</div>

<script>
    /**
    * ============================================================
    * calculoKPCForm.php - JAVASCRIPT COMPLETO
    * ============================================================
    * 
    * Substitua TODO o conteúdo da tag <script> pelo código abaixo.
    * 
    * Inclui:
    * - Funções de cálculo KPC (fórmula corrigida do sistema legado)
    * - Funções do gráfico Curva de Velocidade
    * - Funções auxiliares (autocomplete, posições, etc.)
    * 
    * ============================================================
    */

    const PI = Math.PI;
    const isEdicao = <?= $isEdicao ? 'true' : 'false' ?>;
    let graficoVelocidade = null;

    // ============================================================
    // AUTOCOMPLETE - PONTO DE MEDIÇÃO
    // ============================================================

    function initAutocomplete() {
        const input = document.getElementById('inputPontoMedicao');
        const dropdown = document.getElementById('pontoMedicaoDropdown');
        const btnLimpar = document.getElementById('btnLimparPonto');
        let debounce = null;
        let idx = -1;

        input.addEventListener('input', e => {
            clearTimeout(debounce);
            debounce = setTimeout(() => {
                const termo = e.target.value.trim();
                if (termo.length >= 2) buscarPontosMedicao(termo);
                else dropdown.classList.remove('active');
            }, 300);
        });

        input.addEventListener('keydown', e => {
            const items = dropdown.querySelectorAll('.autocomplete-item');
            if (e.key === 'ArrowDown') { e.preventDefault(); idx = Math.min(idx + 1, items.length - 1); highlight(items); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); idx = Math.max(idx - 1, 0); highlight(items); }
            else if (e.key === 'Enter' && idx >= 0 && items[idx]) { e.preventDefault(); items[idx].click(); }
            else if (e.key === 'Escape') dropdown.classList.remove('active');
        });

        function highlight(items) { items.forEach((it, i) => it.classList.toggle('highlighted', i === idx)); }

        document.addEventListener('click', e => { if (!input.contains(e.target) && !dropdown.contains(e.target)) dropdown.classList.remove('active'); });
        btnLimpar.addEventListener('click', limparPontoMedicao);
    }

    function buscarPontosMedicao(termo) {
        const dropdown = document.getElementById('pontoMedicaoDropdown');
        const cdLocalidade = document.getElementById('selectLocalidade').value || '';
        const cdUnidade = document.getElementById('selectUnidade').value || '';

        dropdown.innerHTML = '<div class="autocomplete-loading">Buscando...</div>';
        dropdown.classList.add('active');

        const params = new URLSearchParams({ busca: termo });
        if (cdUnidade) params.append('cd_unidade', cdUnidade);
        if (cdLocalidade) params.append('cd_localidade', cdLocalidade);

        fetch('bd/pontoMedicao/buscarPontosMedicao.php?' + params)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    dropdown.innerHTML = data.data.map(p => `
                    <div class="autocomplete-item" data-id="${p.CD_PONTO_MEDICAO}" data-nome="${p.DS_NOME}"
                         data-localidade="${p.CD_LOCALIDADE}" data-unidade="${p.CD_UNIDADE}">
                        <strong>${p.DS_NOME}</strong>
                        <small>${p.DS_LOCALIDADE} - ${p.DS_UNIDADE}</small>
                    </div>
                `).join('');
                    dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
                        item.addEventListener('click', () => selecionarPontoMedicao(item));
                    });
                } else {
                    dropdown.innerHTML = '<div class="autocomplete-empty">Nenhum ponto encontrado</div>';
                }
            })
            .catch(() => {
                dropdown.innerHTML = '<div class="autocomplete-empty">Erro ao buscar</div>';
            });
    }

    function selecionarPontoMedicao(item) {
        document.getElementById('cdPontoMedicao').value = item.dataset.id;
        document.getElementById('inputPontoMedicao').value = item.dataset.nome;
        document.getElementById('pontoMedicaoDropdown').classList.remove('active');

        // Atualiza selects
        const unidade = item.dataset.unidade;
        const localidade = item.dataset.localidade;
        if (unidade) {
            document.getElementById('selectUnidade').value = unidade;
            carregarLocalidades(unidade, localidade);
        }
    }

    function limparPontoMedicao() {
        document.getElementById('cdPontoMedicao').value = '';
        document.getElementById('inputPontoMedicao').value = '';
    }

    function carregarLocalidades(cdUnidade, cdLocalidadeSelecionada = '') {
        const select = document.getElementById('selectLocalidade');
        select.innerHTML = '<option value="">Carregando...</option>';

        fetch('bd/localidade/listarLocalidades.php?cd_unidade=' + cdUnidade)
            .then(r => r.json())
            .then(data => {
                select.innerHTML = '<option value="">Todas</option>';
                if (data.success) {
                    data.data.forEach(l => {
                        const selected = l.CD_CHAVE == cdLocalidadeSelecionada ? 'selected' : '';
                        select.innerHTML += `<option value="${l.CD_CHAVE}" ${selected}>${l.CD_LOCALIDADE} - ${l.DS_NOME}</option>`;
                    });
                }
            });
    }

    // ============================================================
    // POSIÇÕES E MÉTODO
    // ============================================================

    function calcularPosicoesPontos() {
        const metodo = parseInt(document.getElementById('selectMetodo').value) || 1;
        const dr = parseFloat(document.getElementById('vlDiametroReal').value) || 0;
        const up = parseFloat(document.getElementById('vlUltimaPosicao')?.value) || dr;

        const pos = {};
        for (let p = 1; p <= 11; p++) {
            pos[p] = (p === 1) ? 0 : (p === 11) ? (metodo === 2 ? up : dr) : (dr / 10) * (p - 1);
        }
        return pos;
    }

    function atualizarPosicoes() {
        const pos = calcularPosicoesPontos();
        for (let p = 1; p <= 11; p++) {
            const th = document.getElementById('posicao_' + p);
            if (th) th.textContent = pos[p].toFixed(2).replace('.', ',');
        }
    }

    function toggleCampoConvencional() {
        const campo = document.getElementById('campoUltimaPosicao');
        if (campo) {
            campo.classList.toggle('visivel', document.getElementById('selectMetodo').value == '2');
        }
    }

    // ============================================================
    // CÁLCULO DAS MÉDIAS
    // ============================================================

    function calcularMediaPonto(ponto) {
        let soma = 0, count = 0;
        for (let l = 1; l <= 20; l++) {
            const input = document.querySelector('input[data-leitura="' + l + '"][data-ponto="' + ponto + '"]');
            if (input && input.value !== '' && !isNaN(parseFloat(input.value))) {
                soma += parseFloat(input.value);
                count++;
            }
        }
        const mediaInput = document.getElementById('media_' + ponto);
        if (mediaInput) {
            mediaInput.value = count > 0 ? (soma / count).toFixed(2) : '';
        }
    }

    function obterMediasDeflexoes() {
        const medias = [];
        for (let p = 1; p <= 11; p++) {
            const mediaInput = document.getElementById('media_' + p);
            const valor = mediaInput ? parseFloat(mediaInput.value) || 0 : 0;
            medias.push(valor);
        }
        return medias;
    }

    // ============================================================
    // FUNÇÕES DE CÁLCULO KPC - FÓRMULA CORRIGIDA (SISTEMA LEGADO)
    // ============================================================

    /**
     * Fator de Velocidade - MÉTODO PADRÃO (Digital)
     * FV = (Σ √médias exceto central) / (10 × √média_central)
     */
    function calcularFatorVelocidadePadrao(medias) {
        const indiceCentral = 5; // Ponto 6
        const mediaCentral = medias[indiceCentral];

        if (mediaCentral <= 0) return 0;

        let somaRaizes = 0;
        for (let i = 0; i < medias.length; i++) {
            if (i !== indiceCentral && medias[i] > 0) {
                somaRaizes += Math.sqrt(medias[i]);
            }
        }

        return somaRaizes / (10 * Math.sqrt(mediaCentral));
    }

    /**
     * Fator de Velocidade - MÉTODO CONVENCIONAL
     * Usa interpolação com coeficientes específicos
     */
    function calcularFatorVelocidadeConvencional(medias) {
        const deflexao = new Array(11);

        deflexao[0] = (medias[1] - medias[0]) * 0.2565835 + medias[0];
        deflexao[1] = (medias[1] - deflexao[0]) * 0.8166999 + medias[0];
        deflexao[2] = (medias[2] - medias[1]) * 0.4644661 + medias[1];
        deflexao[3] = (medias[3] - medias[2]) * 0.2613872 + medias[2];
        deflexao[4] = (medias[4] - medias[3]) * 0.4188612 + medias[3];
        deflexao[5] = medias[5];
        deflexao[6] = (medias[6] - medias[7]) * 0.4188612 + medias[7];
        deflexao[7] = (medias[7] - medias[8]) * 0.2613872 + medias[8];
        deflexao[8] = (medias[8] - medias[9]) * 0.4644661 + medias[9];
        deflexao[9] = (medias[9] - medias[10]) * 0.8166999 + medias[10];
        deflexao[10] = (medias[9] - medias[10]) * 0.2565835 + medias[10];

        let fv = 0;
        for (let i = 0; i < deflexao.length; i++) {
            if (deflexao[i] > 0) fv += Math.sqrt(deflexao[i]);
        }

        if (deflexao[5] > 0) {
            fv -= Math.sqrt(deflexao[5]);
            fv = fv / (10 * Math.sqrt(deflexao[5]));
        } else {
            fv = 0;
        }

        return fv;
    }

    /**
     * Área Efetiva = π × (DR / 2000)²
     * Usa Diâmetro REAL
     */
    function obterAreaEfetiva(dr) {
        return PI * Math.pow(dr / 2000, 2);
    }

    /**
     * Correção de Projeção TAP (Kp)
     * Se DN >= 301, retorna 1
     */
    function obterCorrecaoProjecaoTap(pt, dn) {
        if (dn >= 301) return 1.0;

        const tabelaKp = {
            25: { 50: 0.98, 75: 0.99, 100: 0.995, 150: 0.998, 200: 0.999, 250: 1.0, 300: 1.0 },
            30: { 50: 0.97, 75: 0.98, 100: 0.99, 150: 0.995, 200: 0.998, 250: 0.999, 300: 1.0 },
            35: { 50: 0.96, 75: 0.97, 100: 0.98, 150: 0.99, 200: 0.995, 250: 0.998, 300: 1.0 },
            40: { 50: 0.95, 75: 0.96, 100: 0.97, 150: 0.98, 200: 0.99, 250: 0.995, 300: 1.0 },
            45: { 50: 0.94, 75: 0.95, 100: 0.96, 150: 0.97, 200: 0.98, 250: 0.99, 300: 1.0 },
            50: { 50: 0.93, 75: 0.94, 100: 0.95, 150: 0.96, 200: 0.97, 250: 0.98, 300: 1.0 }
        };

        let ptProxima = 25, menorDif = Math.abs(pt - 25);
        for (const p of Object.keys(tabelaKp)) {
            const dif = Math.abs(pt - parseInt(p));
            if (dif < menorDif) { menorDif = dif; ptProxima = parseInt(p); }
        }

        if (tabelaKp[ptProxima]) {
            let dnProximo = 50;
            menorDif = Math.abs(dn - 50);
            for (const d of Object.keys(tabelaKp[ptProxima])) {
                const dif = Math.abs(dn - parseInt(d));
                if (dif < menorDif) { menorDif = dif; dnProximo = parseInt(d); }
            }
            return tabelaKp[ptProxima][dnProximo];
        }

        return 1.0;
    }

    /**
     * Densidade da água por temperatura
     */
    function obterDensidade(temp) {
        const tabelaDensidade = {
            0: 999.84, 5: 999.96, 10: 999.70, 15: 999.10, 20: 998.20,
            25: 997.05, 30: 995.65, 35: 994.03, 40: 992.22, 45: 990.21, 50: 988.03
        };

        if (tabelaDensidade[temp]) return tabelaDensidade[temp];

        const temps = Object.keys(tabelaDensidade).map(Number).sort((a, b) => a - b);
        let tempInf = temps[0], tempSup = temps[temps.length - 1];

        for (const t of temps) {
            if (t <= temp) tempInf = t;
            if (t >= temp) { tempSup = t; break; }
        }

        if (tempInf !== tempSup) {
            const fator = (temp - tempInf) / (tempSup - tempInf);
            return tabelaDensidade[tempInf] + fator * (tabelaDensidade[tempSup] - tabelaDensidade[tempInf]);
        }

        return 997.05;
    }

    /**
     * FUNÇÃO PRINCIPAL DE CÁLCULO DO KPC
     * Fórmula: KPC = FV × CD × CP × AE
     */
    function calcular() {
        // 1. Calcula as médias de cada ponto
        for (let p = 1; p <= 11; p++) calcularMediaPonto(p);

        // 2. Obtém os parâmetros
        const dn = parseFloat(document.getElementById('vlDiametroNominal').value) || 0;
        const dr = parseFloat(document.getElementById('vlDiametroReal').value) || 0;
        const pt = parseFloat(document.getElementById('vlProjecaoTap').value) || 0;
        const temperatura = parseFloat(document.getElementById('vlTemperatura').value) || 25;
        const metodo = parseInt(document.getElementById('selectMetodo').value) || 1;

        if (dn <= 0 || dr <= 0) {
            alert('Preencha os parâmetros do cálculo (Diâmetro Nominal e Real)');
            return;
        }

        // 3. Obtém as médias das deflexões
        const medias = obterMediasDeflexoes();

        if (!medias.some(m => m > 0)) {
            alert('Preencha ao menos uma leitura de deflexão');
            return;
        }

        // 4. Fator de Velocidade
        let fv = (metodo === 2) ? calcularFatorVelocidadeConvencional(medias) : calcularFatorVelocidadePadrao(medias);

        // 5. Correção de Diâmetro = (DR / DN)² (AO QUADRADO!)
        const cd = Math.pow(dr / dn, 2);

        // 6. Área Efetiva = π × (DR / 2000)² (USA DIÂMETRO REAL!)
        const ae = obterAreaEfetiva(dr);

        // 7. Correção Projeção TAP
        let cp = obterCorrecaoProjecaoTap(pt, dn);
        if (cp === 0) cp = 1;

        // 8. Densidade
        const densidade = obterDensidade(temperatura);

        // 9. KPC = FV × CD × CP × AE
        const kpc = fv * cd * cp * ae;

        // 10. Velocidade e Vazão
        const mediaCentral = medias[5];
        let velocidade = 0;
        if (mediaCentral > 0 && densidade > 0) {
            velocidade = Math.pow(mediaCentral / 1000, 0.4931) * 3.8078 * (densidade / 1000);
        }
        const vazao = kpc * velocidade * 1000;

        // 11. Atualiza campos
        document.getElementById('vlFatorVelocidade').value = fv.toFixed(10);
        document.getElementById('vlCorrecaoDiametro').value = cd.toFixed(6);
        document.getElementById('vlCorrecaoProjecaoTap').value = cp.toFixed(2);
        document.getElementById('vlAreaEfetiva').value = ae.toFixed(6);
        document.getElementById('vlKPC').value = kpc.toFixed(10);
        document.getElementById('vlVazao').value = vazao.toFixed(2);

        console.log('Cálculo KPC:', { metodo: metodo === 1 ? 'Padrão' : 'Convencional', dn, dr, pt, fv, cd, cp, ae, kpc, vazao });
    }

    // ============================================================
    // GRÁFICO - CURVA DE VELOCIDADE
    // ============================================================

    /**
     * Abre o modal do gráfico da Curva de Velocidade
     */
    function abrirGraficoCurvaVelocidade() {
        // Primeiro calcula as médias
        for (let p = 1; p <= 11; p++) calcularMediaPonto(p);

        // Obtém posições e médias
        const posicoes = calcularPosicoesPontos();
        const dadosGrafico = [];

        for (let p = 1; p <= 11; p++) {
            const mediaInput = document.getElementById('media_' + p);
            const deflexao = mediaInput ? parseFloat(mediaInput.value) || 0 : 0;
            const posicao = posicoes[p] || 0;

            if (deflexao > 0) {
                dadosGrafico.push({ posicao: posicao, deflexao: deflexao, ponto: p });
            }
        }

        // Verifica se há dados
        if (dadosGrafico.length === 0) {
            alert('Preencha ao menos uma leitura de deflexão para visualizar o gráfico.');
            return;
        }

        // Ordena por posição
        dadosGrafico.sort((a, b) => a.posicao - b.posicao);

        // Abre o modal
        document.getElementById('modalGraficoOverlay').classList.add('ativo');
        document.body.style.overflow = 'hidden';

        // Cria o gráfico após o modal renderizar
        setTimeout(() => criarGraficoVelocidade(dadosGrafico), 100);
    }

    /**
     * Fecha o modal do gráfico
     */
    function fecharModalGrafico(event) {
        if (event && event.target !== event.currentTarget) return;

        document.getElementById('modalGraficoOverlay').classList.remove('ativo');
        document.body.style.overflow = '';

        // Destroi o gráfico
        if (graficoVelocidade) {
            graficoVelocidade.destroy();
            graficoVelocidade = null;
        }
    }

    /**
     * Cria o gráfico da Curva de Velocidade
     */
    function criarGraficoVelocidade(dadosGrafico) {
        if (graficoVelocidade) {
            graficoVelocidade.destroy();
            graficoVelocidade = null;
        }

        const canvas = document.getElementById('graficoVelocidade');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');

        // Calcula limites dos eixos
        const maxPosicao = Math.max(...dadosGrafico.map(d => d.posicao));
        const maxDeflexao = Math.max(...dadosGrafico.map(d => d.deflexao));
        const limiteY = Math.ceil(maxPosicao / 10) * 10 + 10;
        const limiteX = Math.ceil(maxDeflexao / 10) * 10 + 10;

        // Registra plugin de datalabels
        if (typeof ChartDataLabels !== 'undefined') {
            Chart.register(ChartDataLabels);
        }

        graficoVelocidade = new Chart(ctx, {
            type: 'scatter',
            data: {
                datasets: [{
                    label: 'Curva de Velocidade',
                    data: dadosGrafico.map(d => ({ x: d.deflexao, y: d.posicao, ponto: d.ponto })),
                    borderColor: '#c9a227',
                    backgroundColor: '#c9a227',
                    showLine: true,
                    tension: 0.4,
                    pointRadius: 6,
                    pointBackgroundColor: '#c9a227',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 9,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: { top: 20, right: 80, bottom: 20, left: 20 }
                },
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: 'Curva de Velocidade',
                        font: { size: 16, weight: 'bold' },
                        color: '#1e293b'
                    },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        callbacks: {
                            label: function (context) {
                                return [
                                    `Ponto: ${context.raw.ponto}`,
                                    `Posição: ${context.raw.y.toFixed(2)} mm`,
                                    `Deflexão: ${context.raw.x.toFixed(4)} mm`
                                ];
                            }
                        }
                    },
                    datalabels: {
                        display: true,
                        align: 'right',
                        anchor: 'end',
                        offset: 6,
                        color: '#475569',
                        font: { size: 10, weight: 'bold' },
                        formatter: function (value) {
                            return value.y.toFixed(0) + ': ' + value.x.toFixed(2);
                        },
                        backgroundColor: 'rgba(255, 255, 255, 0.9)',
                        borderRadius: 3,
                        padding: { top: 2, bottom: 2, left: 4, right: 4 }
                    }
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Deflexão Média (mm)', font: { size: 12, weight: 'bold' }, color: '#475569' },
                        min: 0, max: limiteX,
                        grid: { color: '#e5e7eb' },
                        ticks: { color: '#64748b' }
                    },
                    y: {
                        title: { display: true, text: 'Posição (mm)', font: { size: 12, weight: 'bold' }, color: '#475569' },
                        min: 0, max: limiteY,
                        grid: { color: '#e5e7eb' },
                        ticks: { color: '#64748b' }
                    }
                }
            }
        });
    }

    // Fecha modal com ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('modalGraficoOverlay');
            if (modal && modal.classList.contains('ativo')) {
                fecharModalGrafico();
            }
        }
    });

    // ============================================================
    // SUBMIT DO FORMULÁRIO
    // ============================================================

    document.getElementById('formCalculoKPC').addEventListener('submit', function (e) {
        e.preventDefault();

        const cdPonto = document.getElementById('cdPontoMedicao').value;
        if (!cdPonto) { showToast('Selecione um Ponto de Medição', 'alerta'); return; }

        const kpc = document.getElementById('vlKPC').value;
        if (!kpc || parseFloat(kpc) === 0) {
            if (!confirm('O KPC não foi calculado. Deseja continuar mesmo assim?')) return;
        }

        // Monta os dados - IMPORTANTE: usar mesmos nomes que o PHP espera
        const leituras = [];
        const posicoes = calcularPosicoesPontos();

        for (let l = 1; l <= 20; l++) {
            for (let p = 1; p <= 11; p++) {
                const input = document.querySelector(`input[data-leitura="${l}"][data-ponto="${p}"]`);
                if (input && input.value !== '' && !isNaN(parseFloat(input.value))) {
                    leituras.push({
                        leitura: l,           // PHP espera 'leitura'
                        ponto: p,             // PHP espera 'ponto'
                        deflexao: parseFloat(input.value),  // PHP espera 'deflexao'
                        posicao: posicoes[p]  // PHP espera 'posicao'
                    });
                }
            }
        }

        // Adiciona as médias (ordem 21)
        for (let p = 1; p <= 11; p++) {
            const media = parseFloat(document.getElementById('media_' + p).value) || 0;
            if (media > 0) {
                leituras.push({
                    leitura: 21,          // PHP espera 'leitura'
                    ponto: p,             // PHP espera 'ponto'
                    deflexao: media,      // PHP espera 'deflexao'
                    posicao: posicoes[p]  // PHP espera 'posicao'
                });
            }
        }

        const dados = {
            cd_chave: parseInt(document.querySelector('input[name="cd_chave"]').value) || 0,
            cd_ponto_medicao: parseInt(cdPonto),
            dt_leitura: document.querySelector('input[name="dt_leitura"]').value,
            id_metodo: parseInt(document.getElementById('selectMetodo').value) || 1,
            vl_diametro_nominal: parseFloat(document.getElementById('vlDiametroNominal').value) || 0,
            vl_diametro_real: parseFloat(document.getElementById('vlDiametroReal').value) || 0,
            vl_projecao_tap: parseFloat(document.getElementById('vlProjecaoTap').value) || 0,
            vl_raio_tip: parseFloat(document.getElementById('vlRaioTip').value) || 0,
            vl_temperatura: parseFloat(document.getElementById('vlTemperatura').value) || 25,
            vl_fator_velocidade: parseFloat(document.getElementById('vlFatorVelocidade').value) || 0,
            vl_correcao_diametro: parseFloat(document.getElementById('vlCorrecaoDiametro').value) || 0,
            vl_correcao_projecao_tap: parseFloat(document.getElementById('vlCorrecaoProjecaoTap').value) || 0,
            vl_area_efetiva: parseFloat(document.getElementById('vlAreaEfetiva').value) || 0,
            vl_kpc: parseFloat(document.getElementById('vlKPC').value) || 0,
            vl_vazao: parseFloat(document.getElementById('vlVazao').value) || null,
            cd_tecnico_responsavel: parseInt(document.getElementById('selectTecnico').value) || 0,
            cd_usuario_responsavel: parseInt(document.getElementById('selectUsuario').value) || 0,
            ds_observacao: document.querySelector('input[name="ds_observacao"]')?.value || '',
            leituras: leituras
        };

        // Envia para o servidor
        fetch('bd/calculoKPC/salvarCalculoKPC.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'sucesso');
                    setTimeout(() => window.location.href = 'calculoKPC.php', 1500);
                } else {
                    showToast(data.message || 'Erro ao salvar', 'erro');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Erro ao salvar', 'erro');
            });
    });

    // ============================================================
    // TOAST
    // ============================================================

    function showToast(msg, tipo = 'info') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = 'toast toast-' + tipo;
        toast.innerHTML = `<span>${msg}</span>`;
        container.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // ============================================================
    // INICIALIZAÇÃO
    // ============================================================

    document.addEventListener('DOMContentLoaded', function () {
        initAutocomplete();
        toggleCampoConvencional();

        // Select2
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $('.select2-tecnico, .select2-usuario').select2({
                placeholder: 'Selecione...',
                allowClear: true,
                width: '100%'
            });
        }

        // Eventos de alteração
        document.getElementById('selectUnidade').addEventListener('change', function () {
            carregarLocalidades(this.value);
            limparPontoMedicao();
        });

        document.getElementById('selectLocalidade').addEventListener('change', limparPontoMedicao);
    });
</script>

<?php include_once 'includes/footer.inc.php'; ?>
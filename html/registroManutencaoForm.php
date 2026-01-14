<?php
// registroManutencaoForm.php
include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão para editar
exigePermissaoTela('Registro de Manutenção', ACESSO_ESCRITA);

// Verifica se é edição
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cdChaveRegistro = $id; // Guarda o valor para uso posterior no JavaScript
$isEdicao = $id > 0;
$registro = null;

// Situações
$situacoes = [
    1 => 'Prevista',
    2 => 'Executada',
    4 => 'Cancelada'
];

// Classificações de Manutenção
$classificacoes = [
    1 => 'Preventiva',
    2 => 'Corretiva'
];

// Tipos de Calibração
$tiposCalibracao = [
    4 => 'Resultado de levantamento de constante K',
    8 => 'Resultado de levantamento de desvio'
];

// Se for edição, busca os dados
if ($isEdicao) {
    $sql = "SELECT 
                R.*,
                P.CD_CODIGO AS CD_CODIGO_PROG,
                P.CD_ANO AS CD_ANO_PROG,
                P.CD_PONTO_MEDICAO,
                P.ID_TIPO_PROGRAMACAO,
                P.CD_USUARIO_RESPONSAVEL,
                P.DS_SOLICITANTE,
                P.DS_SOLICITACAO,
                P.DT_PROGRAMACAO,
                PM.DS_NOME AS DS_PONTO_MEDICAO,
                PM.ID_TIPO_MEDIDOR,
                L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
                L.DS_NOME AS DS_LOCALIDADE,
                L.CD_CHAVE AS CD_LOCALIDADE_CHAVE,
                L.CD_UNIDADE,
                U.DS_NOME AS DS_UNIDADE,
                U.CD_CODIGO AS CD_UNIDADE_CODIGO,
                COALESCE(UT.DS_NOME, T.DS_NOME) AS DS_TECNICO,
                UA.DS_NOME AS DS_USUARIO_ATUALIZACAO,
                UR.DS_NOME AS DS_RESPONSAVEL
            FROM SIMP.dbo.REGISTRO_MANUTENCAO R
            INNER JOIN SIMP.dbo.PROGRAMACAO_MANUTENCAO P ON R.CD_PROGRAMACAO = P.CD_CHAVE
            INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON P.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
            INNER JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
            LEFT JOIN SIMP.dbo.USUARIO UT ON R.CD_TECNICO = UT.CD_USUARIO
            LEFT JOIN SIMP.dbo.TECNICO T ON R.CD_TECNICO = T.CD_TECNICO
            LEFT JOIN SIMP.dbo.USUARIO UA ON R.CD_USUARIO_ULTIMA_ATUALIZACAO = UA.CD_USUARIO
            LEFT JOIN SIMP.dbo.USUARIO UR ON P.CD_USUARIO_RESPONSAVEL = UR.CD_USUARIO
            WHERE R.CD_CHAVE = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registro) {
        $_SESSION['msg'] = 'Registro não encontrado.';
        $_SESSION['msg_tipo'] = 'erro';
        header('Location: registroManutencao.php');
        exit;
    }
}

// Buscar Unidades
$sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME, CD_CODIGO FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

// Buscar Técnicos (da tabela USUARIO)
$sqlTecnicos = $pdoSIMP->query("SELECT CD_USUARIO, DS_NOME FROM SIMP.dbo.USUARIO WHERE OP_BLOQUEADO = 2 ORDER BY DS_NOME");
$tecnicos = $sqlTecnicos->fetchAll(PDO::FETCH_ASSOC);

// Mapeamento de letras por tipo de medidor
$letrasTipoMedidor = [
    1 => 'M', // Macromedidor
    2 => 'E', // Estação Pitométrica
    4 => 'P', // Medidor Pressão
    6 => 'R', // Nível Reservatório
    8 => 'H'  // Hidrômetro
];

// Código formatado da programação: CD_CODIGO-CD_ANO/ID_TIPO_PROGRAMACAO
$codigoProgFormatado = '';
if ($isEdicao) {
    $codigoProgFormatado = str_pad($registro['CD_CODIGO_PROG'], 3, '0', STR_PAD_LEFT) . '-' . $registro['CD_ANO_PROG'] . '/' . $registro['ID_TIPO_PROGRAMACAO'];
}
?>

<!-- Choices.js CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

<style>
    /* Choices.js Customização */
    .choices {
        margin-bottom: 0;
        width: 100%;
    }

    .choices__inner {
        min-height: 44px;
        padding: 6px 12px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        width: 100%;
        box-sizing: border-box;
    }

    .choices__inner:focus,
    .is-focused .choices__inner,
    .is-open .choices__inner {
        background-color: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .choices__list--single {
        padding: 4px 16px 4px 4px;
    }

    .choices__list--single .choices__item {
        color: #1e293b;
        font-size: 14px;
        font-weight: 500;
    }

    .choices__placeholder {
        color: #94a3b8;
        opacity: 1;
    }

    .choices__list--dropdown {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        margin-top: 4px;
        z-index: 99999 !important;
        position: absolute !important;
    }

    .choices__list--dropdown .choices__item {
        padding: 12px 16px;
        font-size: 14px;
        color: #334155;
        background-color: #ffffff;
    }

    .choices__list--dropdown .choices__item--selectable:hover,
    .choices__list--dropdown .choices__item--selectable.is-highlighted,
    .choices__list--dropdown .choices__item.is-highlighted {
        background-color: #3b82f6 !important;
        color: #ffffff !important;
    }

    .choices__list--dropdown .choices__item--selectable.is-selected {
        background-color: #eff6ff;
        color: #3b82f6;
    }

    .choices[data-type*="select-one"] .choices__input {
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        margin: 8px;
        width: calc(100% - 16px) !important;
    }

    .choices {
        position: relative;
        z-index: 1;
    }

    .choices.is-open {
        z-index: 99999;
    }

    .page-container {
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
    }

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
        overflow: visible;
    }

    .form-row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -8px 16px -8px;
        position: relative;
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
    }

    .col-12 { width: 100%; }
    .col-6 { width: 50%; }
    .col-4 { width: 33.333333%; }
    .col-3 { width: 25%; }

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
        padding: 12px 14px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-family: inherit;
        font-size: 14px;
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

    .form-control:disabled {
        background-color: #f1f5f9;
        color: #64748b;
        cursor: not-allowed;
    }

    textarea.form-control {
        min-height: 100px;
        resize: vertical;
    }

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

    /* Radio Button Group - Estilo igual aos filtros */
    .radio-group {
        display: flex;
        gap: 4px;
        background: #f1f5f9;
        padding: 4px;
        border-radius: 10px;
        flex-wrap: wrap;
    }

    .radio-item {
        display: flex;
        align-items: center;
        cursor: pointer;
        margin: 0;
    }

    .radio-item input[type="radio"] {
        display: none;
    }

    .radio-item .radio-label {
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 500;
        color: #64748b;
        border-radius: 8px;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .radio-item input[type="radio"]:checked + .radio-label {
        background: #ffffff;
        color: #1e293b;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .radio-item:hover .radio-label {
        color: #1e293b;
    }

    .form-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        padding: 14px 20px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 10px 18px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
        text-decoration: none;
    }

    .btn ion-icon {
        font-size: 16px;
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

    .form-divider {
        height: 1px;
        background: linear-gradient(to right, transparent, #e2e8f0, transparent);
        margin: 16px 0;
    }

    /* Info da Programação selecionada */
    .programacao-info {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 16px;
        margin-top: 16px;
    }

    .programacao-info-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
    }

    .programacao-info-item {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .programacao-info-item.full-width {
        grid-column: 1 / -1;
    }

    .programacao-info-label {
        font-size: 10px;
        font-weight: 600;
        color: #94a3b8;
        text-transform: uppercase;
    }

    .programacao-info-value {
        font-size: 13px;
        font-weight: 500;
        color: #1e293b;
    }

    @media (max-width: 992px) {
        .col-3 { width: 50%; }
        .col-4 { width: 50%; }
        .programacao-info-grid {
            grid-template-columns: repeat(2, 1fr);
        }
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
            padding: 12px 14px;
        }

        .header-actions {
            flex-direction: column !important;
            width: 100%;
        }

        .form-card-body {
            padding: 16px;
        }

        .col-3, .col-4, .col-6 {
            width: 100%;
        }

        .form-actions {
            flex-direction: column;
            padding: 16px;
        }

        .form-actions .btn {
            width: 100%;
        }

        .programacao-info-grid {
            grid-template-columns: 1fr;
        }
    }

    /* ============================================
       Botão Leituras
       ============================================ */
    .btn-leituras {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .btn-leituras:hover {
        background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
    }

    .btn-leituras ion-icon {
        font-size: 16px;
    }

    .btn-leituras:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    /* ============================================
       Modal Leituras
       ============================================ */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        z-index: 1000;
        justify-content: center;
        align-items: center;
        padding: 20px;
        box-sizing: border-box;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-container {
        background: white;
        border-radius: 16px;
        width: 100%;
        max-width: 800px;
        max-height: 90vh;
        overflow: hidden;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
        display: flex;
        flex-direction: column;
    }

    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: white;
    }

    .modal-header-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
        font-weight: 600;
    }

    .modal-header-title ion-icon {
        font-size: 20px;
    }

    .modal-close {
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
        transition: all 0.2s ease;
    }

    .modal-close:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    .modal-close ion-icon {
        font-size: 20px;
    }

    .modal-body {
        padding: 20px;
        overflow-y: auto;
        flex: 1;
    }

    .modal-section {
        margin-bottom: 20px;
    }

    .modal-section:last-child {
        margin-bottom: 0;
    }

    .modal-section-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 2px solid #e2e8f0;
    }

    .modal-section-title ion-icon {
        font-size: 16px;
        color: #8b5cf6;
    }

    .modal-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .modal-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .modal-field.full-width {
        grid-column: 1 / -1;
    }

    .modal-label {
        font-size: 10px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .modal-input {
        padding: 10px 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        color: #1e293b;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .modal-input:focus {
        outline: none;
        background: #ffffff;
        border-color: #8b5cf6;
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
    }

    .modal-checkbox {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .modal-checkbox input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: #8b5cf6;
    }

    .modal-checkbox-label {
        font-size: 13px;
        color: #1e293b;
        font-weight: 500;
    }

    .modal-empty {
        text-align: center;
        padding: 40px 20px;
        color: #64748b;
    }

    .modal-empty ion-icon {
        font-size: 48px;
        color: #cbd5e1;
        margin-bottom: 12px;
    }

    .modal-empty p {
        font-size: 14px;
        margin: 0;
    }

    /* Tabela de Leituras */
    .leituras-table-container {
        overflow-x: auto;
        margin-top: 8px;
    }

    .leituras-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        table-layout: fixed;
    }

    .leituras-table th {
        background: #f1f5f9;
        color: #475569;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 9px;
        letter-spacing: 0.03em;
        padding: 8px 4px;
        text-align: center;
        border: 1px solid #e2e8f0;
        white-space: nowrap;
    }

    .leituras-table th:nth-child(1) { width: 50px; }
    .leituras-table th:nth-child(2) { width: 25%; }
    .leituras-table th:nth-child(3) { width: 25%; }
    .leituras-table th:nth-child(4) { width: 25%; }
    .leituras-table th:nth-child(5) { width: 45px; }

    .leituras-table td {
        padding: 4px 3px;
        border: 1px solid #e2e8f0;
        text-align: center;
        vertical-align: middle;
    }

    .leituras-table .col-leitura {
        width: 50px;
        font-weight: 600;
        color: #475569;
        background: #f8fafc;
        font-size: 11px;
    }

    .leituras-table .col-acao {
        width: 45px;
        padding: 4px;
    }

    .leituras-table input {
        width: 100%;
        padding: 6px 4px;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        font-size: 11px;
        text-align: right;
        background: #fff;
        transition: all 0.2s ease;
        box-sizing: border-box;
    }

    .leituras-table input:focus {
        outline: none;
        border-color: #8b5cf6;
        box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.1);
    }

    .leituras-table input::placeholder {
        color: #cbd5e1;
        font-size: 10px;
    }

    .btn-add-leitura {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 8px 14px;
        background: #10b981;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        margin-top: 10px;
    }

    .btn-add-leitura:hover {
        background: #059669;
    }

    .btn-add-leitura ion-icon {
        font-size: 14px;
    }

    .btn-add-leitura:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .btn-remove-leitura {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        background: #fee2e2;
        color: #dc2626;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-remove-leitura:hover {
        background: #fecaca;
    }

    .btn-remove-leitura ion-icon {
        font-size: 16px;
    }

    .modal-footer {
        padding: 16px 20px;
        background: #f8fafc;
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
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
    }

    .btn-modal.secondary {
        background: #e2e8f0;
        color: #475569;
    }

    .btn-modal.secondary:hover {
        background: #cbd5e1;
    }

    .btn-modal.primary {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: white;
    }

    .btn-modal.primary:hover {
        background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    }

    .btn-modal.calcular {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
    }

    .btn-modal.calcular:hover {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    }

    .btn-modal:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .modal-input.readonly {
        background: #f1f5f9;
        color: #475569;
        cursor: not-allowed;
    }

    @media (max-width: 768px) {
        .modal-container {
            max-height: 85vh;
        }

        .modal-grid {
            grid-template-columns: 1fr;
        }

        .modal-header {
            padding: 14px 16px;
        }

        .modal-body {
            padding: 16px;
        }

        .btn-leituras {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="page-container">
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="<?= $isEdicao ? 'create-outline' : 'add-outline' ?>"></ion-icon>
                </div>
                <div>
                    <h1><?= $isEdicao ? 'Editar Registro' : 'Novo Registro' ?></h1>
                    <p class="page-header-subtitle">
                        <?= $isEdicao ? 'Atualize as informações do registro de manutenção' : 'Cadastre um novo registro de manutenção' ?>
                    </p>
                </div>
            </div>
            <div class="header-actions" style="display: flex; gap: 10px; align-items: center;">
                <?php if ($isEdicao): ?>
                <button type="button" class="btn-leituras" onclick="abrirModalLeituras()">
                    <ion-icon name="analytics-outline"></ion-icon>
                    Leituras
                </button>
                <?php endif; ?>
                <a href="registroManutencao.php" class="btn-voltar">
                    <ion-icon name="arrow-back-outline"></ion-icon>
                    Voltar
                </a>
            </div>
        </div>
    </div>

    <form id="formRegistro" method="POST" action="bd/registroManutencao/salvarRegistro.php">
        <input type="hidden" name="cd_chave" value="<?= $cdChaveRegistro ?>">

        <!-- Seleção da Programação -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="calendar-outline"></ion-icon>
                <h2>Programação</h2>
                <?php if ($isEdicao): ?>
                    <span class="info-badge codigo" style="margin-left: auto;">Ocorrência: <?= $registro['CD_OCORRENCIA'] ?></span>
                <?php endif; ?>
            </div>
            <div class="form-card-body">
                <!-- Linha 1: Filtros em cascata -->
                <div class="form-row">
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="business-outline"></ion-icon>
                            Unidade
                        </label>
                        <select id="selectUnidade">
                            <option value="">Todas as Unidades</option>
                            <?php foreach ($unidades as $unidade): ?>
                                <option value="<?= $unidade['CD_UNIDADE'] ?>" 
                                    <?= ($isEdicao && $registro['CD_UNIDADE'] == $unidade['CD_UNIDADE']) ? 'selected' : '' ?>>
                                    <?= $unidade['CD_CODIGO'] . ' - ' . $unidade['DS_NOME'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="location-outline"></ion-icon>
                            Localidade
                        </label>
                        <select id="selectLocalidade">
                            <option value="">Todas as Localidades</option>
                        </select>
                    </div>

                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="speedometer-outline"></ion-icon>
                            Ponto de Medição
                        </label>
                        <select id="selectPontoMedicao">
                            <option value="">Todos os Pontos</option>
                        </select>
                    </div>

                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="document-outline"></ion-icon>
                            Programação <span class="required">*</span>
                        </label>
                        <select id="selectProgramacao" name="cd_programacao" required>
                            <option value="">Selecione a Programação</option>
                            <?php if ($isEdicao): ?>
                                <option value="<?= $registro['CD_PROGRAMACAO'] ?>" selected>
                                    <?= $codigoProgFormatado ?> - <?= $registro['DS_PONTO_MEDICAO'] ?>
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <!-- Info da programação selecionada -->
                <div class="programacao-info" id="programacaoInfo" style="<?= $isEdicao ? '' : 'display: none;' ?>">
                    <div class="programacao-info-grid">
                        <div class="programacao-info-item">
                            <span class="programacao-info-label">Código</span>
                            <span class="programacao-info-value" id="infoCodigo"><?= $isEdicao ? $codigoProgFormatado : '-' ?></span>
                        </div>
                        <div class="programacao-info-item">
                            <span class="programacao-info-label">Ponto de Medição</span>
                            <span class="programacao-info-value" id="infoPonto"><?= $isEdicao ? $registro['DS_PONTO_MEDICAO'] : '-' ?></span>
                        </div>
                        <div class="programacao-info-item">
                            <span class="programacao-info-label">Localidade</span>
                            <span class="programacao-info-value" id="infoLocalidade"><?= $isEdicao ? $registro['CD_LOCALIDADE_CODIGO'] . ' - ' . $registro['DS_LOCALIDADE'] : '-' ?></span>
                        </div>
                        <div class="programacao-info-item">
                            <span class="programacao-info-label">Tipo de Programação</span>
                            <span class="programacao-info-value" id="infoTipoProgramacao">-</span>
                        </div>
                        <div class="programacao-info-item">
                            <span class="programacao-info-label">Responsável</span>
                            <span class="programacao-info-value" id="infoResponsavel">-</span>
                        </div>
                        <div class="programacao-info-item">
                            <span class="programacao-info-label">Solicitante</span>
                            <span class="programacao-info-value" id="infoSolicitante">-</span>
                        </div>
                        <div class="programacao-info-item full-width">
                            <span class="programacao-info-label">Solicitação</span>
                            <span class="programacao-info-value" id="infoSolicitacao">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dados do Registro -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="clipboard-outline"></ion-icon>
                <h2>Dados do Registro</h2>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="person-outline"></ion-icon>
                            Técnico <span class="required">*</span>
                        </label>
                        <select id="selectTecnico" name="cd_tecnico" required>
                            <option value="">Selecione o Técnico</option>
                            <?php foreach ($tecnicos as $tecnico): ?>
                                <option value="<?= $tecnico['CD_USUARIO'] ?>"
                                    <?= ($isEdicao && $registro['CD_TECNICO'] == $tecnico['CD_USUARIO']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tecnico['DS_NOME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="calendar-outline"></ion-icon>
                            Data de Realização <span class="required">*</span>
                        </label>
                        <input type="datetime-local" name="dt_realizado" class="form-control" required
                               value="<?= $isEdicao && $registro['DT_REALIZADO'] ? date('Y-m-d\TH:i', strtotime($registro['DT_REALIZADO'])) : '' ?>">
                    </div>

                    <div class="form-group col-4">
                        <label class="form-label">
                            <ion-icon name="flag-outline"></ion-icon>
                            Situação <span class="required">*</span>
                        </label>
                        <div class="radio-group">
                            <?php foreach ($situacoes as $id => $nome): ?>
                            <label class="radio-item">
                                <input type="radio" name="id_situacao" value="<?= $id ?>" 
                                    <?= ($isEdicao && $registro['ID_SITUACAO'] == $id) ? 'checked' : (!$isEdicao && $id == 2 ? 'checked' : '') ?> required>
                                <span class="radio-label"><?= $nome ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="form-label">
                            <ion-icon name="construct-outline"></ion-icon>
                            Classificação da Manutenção
                        </label>
                        <div class="radio-group">
                            <label class="radio-item">
                                <input type="radio" name="id_classificacao_manutencao" value="" 
                                    <?= ($isEdicao && empty($registro['ID_CLASSIFICACAO_MANUTENCAO'])) || !$isEdicao ? 'checked' : '' ?>>
                                <span class="radio-label">N/A</span>
                            </label>
                            <?php foreach ($classificacoes as $id => $nome): ?>
                            <label class="radio-item">
                                <input type="radio" name="id_classificacao_manutencao" value="<?= $id ?>" 
                                    <?= ($isEdicao && $registro['ID_CLASSIFICACAO_MANUTENCAO'] == $id) ? 'checked' : '' ?>>
                                <span class="radio-label"><?= $nome ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group col-6">
                        <label class="form-label">
                            <ion-icon name="analytics-outline"></ion-icon>
                            Tipo de Calibração
                        </label>
                        <div class="radio-group">
                            <label class="radio-item">
                                <input type="radio" name="id_tipo_calibracao" value="" 
                                    <?= ($isEdicao && empty($registro['ID_TIPO_CALIBRACAO'])) || !$isEdicao ? 'checked' : '' ?>>
                                <span class="radio-label">N/A</span>
                            </label>
                            <?php foreach ($tiposCalibracao as $id => $nome): ?>
                            <label class="radio-item">
                                <input type="radio" name="id_tipo_calibracao" value="<?= $id ?>" 
                                    <?= ($isEdicao && $registro['ID_TIPO_CALIBRACAO'] == $id) ? 'checked' : '' ?>>
                                <span class="radio-label"><?= $nome ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-12">
                        <label class="form-label">
                            <ion-icon name="document-text-outline"></ion-icon>
                            Parecer Técnico
                        </label>
                        <textarea name="ds_realizado" class="form-control" rows="3"
                                  placeholder="Descreva o parecer técnico" maxlength="5000"><?= $isEdicao ? htmlspecialchars($registro['DS_REALIZADO']) : '' ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Desvios -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="options-outline"></ion-icon>
                <h2>Desvios</h2>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group col-12">
                        <label class="form-label">
                            <ion-icon name="layers-outline"></ion-icon>
                            Desvio EP x Macro <span class="required">*</span>
                        </label>
                        <textarea name="ds_condicao_primario" class="form-control" rows="3" required
                                  placeholder="Informe o desvio EP x Macro" maxlength="5000"><?= $isEdicao ? htmlspecialchars($registro['DS_CONDICAO_PRIMARIO']) : '' ?></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-12">
                        <label class="form-label">
                            <ion-icon name="layers-outline"></ion-icon>
                            Desvio Macro x CCO <span class="required">*</span>
                        </label>
                        <textarea name="ds_condicao_secundario" class="form-control" rows="3" required
                                  placeholder="Informe o desvio Macro x CCO" maxlength="5000"><?= $isEdicao ? htmlspecialchars($registro['DS_CONDICAO_SECUNDARIO']) : '' ?></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-12">
                        <label class="form-label">
                            <ion-icon name="layers-outline"></ion-icon>
                            Desvio Total <span class="required">*</span>
                        </label>
                        <textarea name="ds_condicao_terciario" class="form-control" rows="3" required
                                  placeholder="Informe o desvio total" maxlength="5000"><?= $isEdicao ? htmlspecialchars($registro['DS_CONDICAO_TERCIARIO']) : '' ?></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-12">
                        <label class="form-label">
                            <ion-icon name="chatbox-outline"></ion-icon>
                            Protocolo de Comunicação
                        </label>
                        <textarea name="ds_observacao" class="form-control" rows="3"
                                  placeholder="Informe o protocolo de comunicação" maxlength="5000"><?= $isEdicao ? htmlspecialchars($registro['DS_OBSERVACAO']) : '' ?></textarea>
                    </div>
                </div>

                <?php if ($isEdicao && $registro['DT_ULTIMA_ATUALIZACAO']): ?>
                <div class="form-divider"></div>
                <div class="form-row">
                    <div class="form-group col-12">
                        <small style="color: #94a3b8; font-size: 11px;">
                            <ion-icon name="time-outline" style="vertical-align: middle;"></ion-icon>
                            Última atualização: <?= date('d/m/Y H:i', strtotime($registro['DT_ULTIMA_ATUALIZACAO'])) ?>
                            <?php if ($registro['DS_USUARIO_ATUALIZACAO']): ?>
                                por <?= htmlspecialchars($registro['DS_USUARIO_ATUALIZACAO']) ?>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <a href="registroManutencao.php" class="btn btn-secondary">
                    <ion-icon name="close-outline"></ion-icon>
                    Cancelar
                </a>
                <?php if ($isEdicao): ?>
                <button type="button" class="btn btn-danger" onclick="confirmarExclusao()">
                    <ion-icon name="trash-outline"></ion-icon>
                    Excluir
                </button>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary" id="btnSalvar">
                    <ion-icon name="save-outline"></ion-icon>
                    <?= $isEdicao ? 'Salvar Alterações' : 'Cadastrar Registro' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Choices.js -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<script>
    // Variáveis globais
    let choicesUnidade, choicesLocalidade, choicesPontoMedicao, choicesProgramacao, choicesTecnico;
    const isEdicao = <?= $isEdicao ? 'true' : 'false' ?>;
    
    // Dados da edição (se aplicável)
    const dadosEdicao = <?php if ($isEdicao): ?>{
        cdUnidade: <?= (int)$registro['CD_UNIDADE'] ?>,
        cdLocalidadeChave: <?= (int)$registro['CD_LOCALIDADE_CHAVE'] ?>,
        cdLocalidadeCodigo: '<?= $registro['CD_LOCALIDADE_CODIGO'] ?>',
        dsLocalidade: '<?= addslashes($registro['DS_LOCALIDADE']) ?>',
        cdPontoMedicao: <?= (int)$registro['CD_PONTO_MEDICAO'] ?>,
        dsPontoMedicao: '<?= addslashes($registro['DS_PONTO_MEDICAO']) ?>',
        idTipoMedidor: <?= (int)$registro['ID_TIPO_MEDIDOR'] ?>,
        cdProgramacao: <?= (int)$registro['CD_PROGRAMACAO'] ?>,
        cdCodigoProg: <?= (int)$registro['CD_CODIGO_PROG'] ?>,
        cdAnoProg: '<?= $registro['CD_ANO_PROG'] ?>',
        idTipoProgramacao: <?= (int)$registro['ID_TIPO_PROGRAMACAO'] ?>,
        dtProgramacao: '<?= $registro['DT_PROGRAMACAO'] ?>',
        dsResponsavel: '<?= addslashes($registro['DS_RESPONSAVEL'] ?? '') ?>',
        dsSolicitante: '<?= addslashes($registro['DS_SOLICITANTE'] ?? '') ?>',
        dsSolicitacao: '<?= addslashes($registro['DS_SOLICITACAO'] ?? '') ?>'
    }<?php else: ?>null<?php endif; ?>;
    
    // Mapeamentos
    const tiposProgramacao = {
        1: 'Calibração',
        2: 'Manutenção'
    };

    // Mapeamento de letras por tipo de medidor
    const letrasTipoMedidor = {
        1: 'M', // Macromedidor
        2: 'E', // Estação Pitométrica
        4: 'P', // Medidor Pressão
        6: 'R', // Nível Reservatório
        8: 'H'  // Hidrômetro
    };

    // Função para formatar código da programação: CD_CODIGO-CD_ANO/ID_TIPO_PROGRAMACAO
    function formatarCodigoProgramacao(cdCodigo, cdAno, idTipo) {
        return String(cdCodigo).padStart(3, '0') + '-' + cdAno + '/' + idTipo;
    }

    // Função para formatar data
    function formatarData(dataStr) {
        if (!dataStr) return '';
        const data = new Date(dataStr);
        if (isNaN(data.getTime())) return '';
        const dia = String(data.getDate()).padStart(2, '0');
        const mes = String(data.getMonth() + 1).padStart(2, '0');
        const ano = data.getFullYear();
        const hora = String(data.getHours()).padStart(2, '0');
        const min = String(data.getMinutes()).padStart(2, '0');
        return `${dia}/${mes}/${ano} ${hora}:${min}`;
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Configuração base do Choices.js
        const configChoices = {
            searchEnabled: true,
            searchPlaceholderValue: 'Pesquisar...',
            itemSelectText: '',
            noResultsText: 'Nenhum resultado encontrado',
            noChoicesText: 'Sem opções disponíveis',
            placeholder: true,
            placeholderValue: 'Selecione...',
            shouldSort: false
        };

        // Inicializa Choices.js
        choicesUnidade = new Choices('#selectUnidade', configChoices);
        choicesLocalidade = new Choices('#selectLocalidade', { ...configChoices, searchEnabled: true });
        choicesPontoMedicao = new Choices('#selectPontoMedicao', { ...configChoices, searchEnabled: true });
        choicesProgramacao = new Choices('#selectProgramacao', { ...configChoices, searchEnabled: true });
        choicesTecnico = new Choices('#selectTecnico', configChoices);

        // Evento de mudança na Unidade
        document.getElementById('selectUnidade').addEventListener('change', function() {
            const cdUnidade = this.value;
            carregarLocalidades(cdUnidade);
            carregarProgramacoes();
        });

        // Evento de mudança na Localidade
        document.getElementById('selectLocalidade').addEventListener('change', function() {
            const cdLocalidade = this.value;
            carregarPontosMedicao(cdLocalidade);
            carregarProgramacoes();
        });

        // Evento de mudança no Ponto de Medição
        document.getElementById('selectPontoMedicao').addEventListener('change', function() {
            carregarProgramacoes();
        });

        // Evento de mudança na Programação
        document.getElementById('selectProgramacao').addEventListener('change', function() {
            atualizarInfoProgramacao(this.value);
        });

        // Se for edição, carrega dados em cascata e preenche info
        if (isEdicao && dadosEdicao) {
            // Carrega localidades da unidade selecionada
            carregarLocalidadesEdicao(dadosEdicao.cdUnidade, dadosEdicao.cdLocalidadeChave);
        } else {
            // Carrega programações iniciais (todas disponíveis)
            carregarProgramacoes();
        }

        // Submit do formulário
        document.getElementById('formRegistro').addEventListener('submit', function(e) {
            e.preventDefault();
            salvarRegistro();
        });
    });

    function carregarLocalidadesEdicao(cdUnidade, cdLocalidadeSelecionada) {
        if (!cdUnidade) {
            preencherInfoEdicao();
            return;
        }

        fetch(`bd/pontoMedicao/getLocalidades.php?cd_unidade=${cdUnidade}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    const choices = [{ value: '', label: 'Todas as Localidades' }];
                    data.data.forEach(item => {
                        choices.push({ 
                            value: item.CD_CHAVE, 
                            label: `${item.CD_LOCALIDADE} - ${item.DS_NOME}`,
                            selected: item.CD_CHAVE == cdLocalidadeSelecionada
                        });
                    });
                    choicesLocalidade.clearStore();
                    choicesLocalidade.setChoices(choices, 'value', 'label', true);
                    
                    // Carrega pontos de medição
                    if (cdLocalidadeSelecionada) {
                        carregarPontosMedicaoEdicao(cdLocalidadeSelecionada, dadosEdicao.cdPontoMedicao);
                    }
                }
            })
            .catch(error => {
                console.error('Erro ao carregar localidades:', error);
                preencherInfoEdicao();
            });
    }

    function carregarPontosMedicaoEdicao(cdLocalidade, cdPontoSelecionado) {
        if (!cdLocalidade) {
            preencherInfoEdicao();
            return;
        }

        fetch(`bd/programacaoManutencao/getPontosMedicaoFormatado.php?cd_localidade=${cdLocalidade}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    const choices = [{ value: '', label: 'Todos os Pontos' }];
                    data.data.forEach(item => {
                        const letraTipo = letrasTipoMedidor[item.ID_TIPO_MEDIDOR] || 'X';
                        const codigoPonto = item.CD_LOCALIDADE + '-' + 
                                           String(item.CD_PONTO_MEDICAO).padStart(6, '0') + '-' + 
                                           letraTipo + '-' + 
                                           item.CD_UNIDADE;
                        choices.push({ 
                            value: item.CD_PONTO_MEDICAO, 
                            label: `${codigoPonto} - ${item.DS_NOME}`,
                            selected: item.CD_PONTO_MEDICAO == cdPontoSelecionado
                        });
                    });
                    choicesPontoMedicao.clearStore();
                    choicesPontoMedicao.setChoices(choices, 'value', 'label', true);
                }
                // Preenche info da programação
                preencherInfoEdicao();
            })
            .catch(error => {
                console.error('Erro ao carregar pontos:', error);
                preencherInfoEdicao();
            });
    }

    function preencherInfoEdicao() {
        if (!dadosEdicao) return;

        // Preenche os campos de informação da programação
        const codigo = formatarCodigoProgramacao(dadosEdicao.cdCodigoProg, dadosEdicao.cdAnoProg, dadosEdicao.idTipoProgramacao);
        
        document.getElementById('infoCodigo').textContent = codigo;
        document.getElementById('infoPonto').textContent = dadosEdicao.dsPontoMedicao || '-';
        document.getElementById('infoLocalidade').textContent = dadosEdicao.cdLocalidadeCodigo + ' - ' + dadosEdicao.dsLocalidade;
        document.getElementById('infoTipoProgramacao').textContent = tiposProgramacao[dadosEdicao.idTipoProgramacao] || '-';
        document.getElementById('infoResponsavel').textContent = dadosEdicao.dsResponsavel || '-';
        document.getElementById('infoSolicitante').textContent = dadosEdicao.dsSolicitante || '-';
        document.getElementById('infoSolicitacao').textContent = dadosEdicao.dsSolicitacao || '-';
        
        document.getElementById('programacaoInfo').style.display = 'block';
    }

    function carregarLocalidades(cdUnidade) {
        choicesLocalidade.clearStore();
        choicesLocalidade.setChoices([{ value: '', label: 'Todas as Localidades', selected: true }], 'value', 'label', true);
        
        // Limpa Ponto de Medição também
        choicesPontoMedicao.clearStore();
        choicesPontoMedicao.setChoices([{ value: '', label: 'Todos os Pontos', selected: true }], 'value', 'label', true);

        if (!cdUnidade) return;

        fetch(`bd/pontoMedicao/getLocalidades.php?cd_unidade=${cdUnidade}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    const choices = [{ value: '', label: 'Todas as Localidades', selected: true }];
                    data.data.forEach(item => {
                        choices.push({ value: item.CD_CHAVE, label: `${item.CD_LOCALIDADE} - ${item.DS_NOME}` });
                    });
                    choicesLocalidade.setChoices(choices, 'value', 'label', true);
                }
            })
            .catch(error => {
                console.error('Erro ao carregar localidades:', error);
            });
    }

    function carregarPontosMedicao(cdLocalidade) {
        choicesPontoMedicao.clearStore();
        choicesPontoMedicao.setChoices([{ value: '', label: 'Todos os Pontos', selected: true }], 'value', 'label', true);

        if (!cdLocalidade) return;

        fetch(`bd/programacaoManutencao/getPontosMedicaoFormatado.php?cd_localidade=${cdLocalidade}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    const choices = [{ value: '', label: 'Todos os Pontos', selected: true }];
                    data.data.forEach(item => {
                        const letraTipo = letrasTipoMedidor[item.ID_TIPO_MEDIDOR] || 'X';
                        const codigoPonto = item.CD_LOCALIDADE + '-' + 
                                           String(item.CD_PONTO_MEDICAO).padStart(6, '0') + '-' + 
                                           letraTipo + '-' + 
                                           item.CD_UNIDADE;
                        choices.push({ value: item.CD_PONTO_MEDICAO, label: `${codigoPonto} - ${item.DS_NOME}` });
                    });
                    choicesPontoMedicao.setChoices(choices, 'value', 'label', true);
                }
            })
            .catch(error => {
                console.error('Erro ao carregar pontos:', error);
            });
    }

    function carregarProgramacoes() {
        choicesProgramacao.clearStore();
        choicesProgramacao.setChoices([{ value: '', label: 'Carregando...', selected: true }], 'value', 'label', true);

        const cdUnidade = document.getElementById('selectUnidade').value;
        const cdLocalidade = document.getElementById('selectLocalidade').value;
        const cdPontoMedicao = document.getElementById('selectPontoMedicao').value;

        const params = new URLSearchParams();
        if (cdUnidade) params.append('cd_unidade', cdUnidade);
        if (cdLocalidade) params.append('cd_localidade', cdLocalidade);
        if (cdPontoMedicao) params.append('cd_ponto_medicao', cdPontoMedicao);

        fetch(`bd/registroManutencao/getProgramacoes.php?${params}`)
            .then(response => response.json())
            .then(data => {
                const choices = [{ value: '', label: 'Selecione a Programação', selected: true }];
                
                if (data.success && data.data.length > 0) {
                    data.data.forEach(item => {
                        // Formato: CD_CODIGO-CD_ANO/ID_TIPO_PROGRAMACAO - DT_PROGRAMACAO
                        const codigo = formatarCodigoProgramacao(item.CD_CODIGO, item.CD_ANO, item.ID_TIPO_PROGRAMACAO);
                        const dtProg = formatarData(item.DT_PROGRAMACAO);
                        const labelText = dtProg ? `${codigo} - ${dtProg}` : codigo;
                        choices.push({
                            value: item.CD_CHAVE,
                            label: labelText,
                            customProperties: item
                        });
                    });
                }
                
                choicesProgramacao.clearStore();
                choicesProgramacao.setChoices(choices, 'value', 'label', true);
            })
            .catch(error => {
                console.error('Erro ao carregar programações:', error);
                choicesProgramacao.clearStore();
                choicesProgramacao.setChoices([{ value: '', label: 'Erro ao carregar', selected: true }], 'value', 'label', true);
            });
    }

    function atualizarInfoProgramacao(cdProgramacao) {
        const infoDiv = document.getElementById('programacaoInfo');
        
        if (!cdProgramacao) {
            infoDiv.style.display = 'none';
            return;
        }

        // Busca os dados da programação selecionada
        const selected = choicesProgramacao.getValue();
        if (selected && selected.customProperties) {
            const prog = selected.customProperties;
            const codigo = formatarCodigoProgramacao(prog.CD_CODIGO, prog.CD_ANO, prog.ID_TIPO_PROGRAMACAO);
            
            // Preenche os campos de informação
            document.getElementById('infoCodigo').textContent = codigo;
            document.getElementById('infoPonto').textContent = prog.DS_PONTO_MEDICAO || '-';
            document.getElementById('infoLocalidade').textContent = prog.CD_LOCALIDADE + ' - ' + prog.DS_LOCALIDADE;
            document.getElementById('infoTipoProgramacao').textContent = tiposProgramacao[prog.ID_TIPO_PROGRAMACAO] || '-';
            document.getElementById('infoResponsavel').textContent = prog.DS_RESPONSAVEL || '-';
            document.getElementById('infoSolicitante').textContent = prog.DS_SOLICITANTE || '-';
            document.getElementById('infoSolicitacao').textContent = prog.DS_SOLICITACAO || '-';
            
            infoDiv.style.display = 'block';
        }
    }

    function salvarRegistro() {
        const btnSalvar = document.getElementById('btnSalvar');
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = '<ion-icon name="hourglass-outline"></ion-icon> Salvando...';

        const formData = new FormData(document.getElementById('formRegistro'));

        fetch('bd/registroManutencao/salvarRegistro.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                
                // DEBUG - Mostra no console
                console.log('=== DEBUG SALVAR REGISTRO ===');
                console.log('Resposta completa:', data);
                if (data.debug_sql) {
                    console.log('SQL executado:', data.debug_sql);
                }
                if (data.debug_params) {
                    console.log('Parâmetros:', data.debug_params);
                }
                console.log('=============================');
                
                if (data.success) {
                    showToast(data.message, 'sucesso');
                    // Aguarda mais tempo para ver o console
                    setTimeout(function() {
                        window.location.href = 'registroManutencao.php';
                    }, 3000);
                } else {
                    showToast(data.message || 'Erro ao salvar', 'erro');
                    btnSalvar.disabled = false;
                    btnSalvar.innerHTML = '<ion-icon name="save-outline"></ion-icon> Salvar';
                }
            } catch (e) {
                console.error('Erro ao parsear JSON:', e);
                console.error('Texto recebido:', text);
                showToast('Erro na resposta do servidor', 'erro');
                btnSalvar.disabled = false;
                btnSalvar.innerHTML = '<ion-icon name="save-outline"></ion-icon> Salvar';
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showToast('Erro ao comunicar com o servidor', 'erro');
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = '<ion-icon name="save-outline"></ion-icon> Salvar';
        });
    }

    function confirmarExclusao() {
        if (confirm('Tem certeza que deseja excluir este registro?')) {
            fetch('bd/registroManutencao/excluirRegistro.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'cd_chave=<?= $cdChaveRegistro ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'sucesso');
                    setTimeout(function() {
                        window.location.href = 'registroManutencao.php';
                    }, 1500);
                } else {
                    showToast(data.message || 'Erro ao excluir', 'erro');
                }
            });
        }
    }

    // ============================================
    // Modal Leituras (Calibração de Deprimogênio)
    // ============================================
    <?php if ($isEdicao): ?>
    const cdRegistroManutencao = <?= $cdChaveRegistro ?>;
    let calibracaoCdChave = 0;
    let leiturasData = [];

    function abrirModalLeituras() {
        const modal = document.getElementById('modalLeituras');
        const body = document.getElementById('modalLeiturasBody');
        
        modal.classList.add('active');
        body.innerHTML = `
            <div class="modal-empty">
                <ion-icon name="hourglass-outline"></ion-icon>
                <p>Carregando dados...</p>
            </div>
        `;

        fetch(`bd/calibracaoDeprimogenio/getCalibracaoDeprimogenio.php?cd_registro_manutencao=${cdRegistroManutencao}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.exists && data.calibracao) {
                        calibracaoCdChave = data.calibracao.CD_CHAVE;
                        leiturasData = data.leituras || [];
                        renderizarFormCalibracao(data.calibracao, leiturasData);
                    } else {
                        calibracaoCdChave = 0;
                        leiturasData = [];
                        renderizarFormCalibracao(null, []);
                    }
                } else {
                    body.innerHTML = `
                        <div class="modal-empty">
                            <ion-icon name="alert-circle-outline"></ion-icon>
                            <p>${data.message || 'Erro ao carregar dados'}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                body.innerHTML = `
                    <div class="modal-empty">
                        <ion-icon name="alert-circle-outline"></ion-icon>
                        <p>Erro ao comunicar com o servidor</p>
                    </div>
                `;
            });
    }

    function renderizarFormCalibracao(calibracao, leituras) {
        const body = document.getElementById('modalLeiturasBody');
        
        const getValue = (campo) => {
            if (!calibracao || calibracao[campo] === null || calibracao[campo] === undefined) return '';
            return calibracao[campo];
        };

        // Se não houver leituras, cria uma linha vazia
        if (!leituras || leituras.length === 0) {
            leituras = [{ CD_PONTO_LEITURA: 1, VL_VAZAO_EP: '', VL_PERCENTUAL_INCERTEZA_EP: '', VL_K_DEPRIMOGENIO: '' }];
        }

        // Gera linhas de leituras existentes
        let leiturasRows = '';
        leituras.forEach((leitura, index) => {
            const pontoLeitura = leitura.CD_PONTO_LEITURA || (index + 1);
            leiturasRows += criarLinhaLeitura(pontoLeitura, leitura.VL_VAZAO_EP || '', leitura.VL_PERCENTUAL_INCERTEZA_EP || '', leitura.VL_K_DEPRIMOGENIO || '');
        });

        body.innerHTML = `
            <form id="formCalibracao">
                <input type="hidden" name="cd_chave" value="${calibracaoCdChave}">
                <input type="hidden" name="cd_registro_manutencao" value="${cdRegistroManutencao}">
                
                <!-- Seção de Leituras -->
                <div class="modal-section">
                    <div class="modal-section-title">
                        <ion-icon name="list-outline"></ion-icon>
                        Leituras
                    </div>
                    <div class="leituras-table-container">
                        <table class="leituras-table">
                            <thead>
                                <tr>
                                    <th>Leitura</th>
                                    <th>Vazão EP (m³/s)</th>
                                    <th>Incerteza EP (%)</th>
                                    <th>K Deprimogênio</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody id="tabelaLeituras">
                                ${leiturasRows}
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn-add-leitura" onclick="adicionarLeitura()">
                        <ion-icon name="add-outline"></ion-icon>
                        Adicionar Leitura
                    </button>
                </div>

                <!-- Seção de Dados da Calibração -->
                <div class="modal-section">
                    <div class="modal-section-title">
                        <ion-icon name="calculator-outline"></ion-icon>
                        Dados da Calibração
                    </div>
                    <div class="modal-grid">
                        <div class="modal-field">
                            <label class="modal-label">Constante K Média (l/seg)/√(pol H2O)</label>
                            <input type="text" name="vl_k_medio" id="vl_k_medio" class="modal-input readonly" value="${getValue('VL_K_MEDIO')}" placeholder="0,00" readonly>
                        </div>
                        <div class="modal-field">
                            <label class="modal-label">Constante K Anterior (l/seg)/√(pol H2O)</label>
                            <input type="text" name="vl_k_anterior" id="vl_k_anterior" class="modal-input readonly" value="${getValue('VL_K_ANTERIOR')}" placeholder="0,00" readonly>
                        </div>
                        <div class="modal-field">
                            <label class="modal-label">Desvio (%)</label>
                            <input type="text" name="vl_desvio" id="vl_desvio" class="modal-input readonly" value="${getValue('VL_DESVIO')}" placeholder="0,00" readonly>
                        </div>
                        <div class="modal-field">
                            <label class="modal-label">Percentual de Acréscimo (%)</label>
                            <input type="text" name="vl_percentual_acrescimo" id="vl_percentual_acrescimo" class="modal-input" value="${getValue('VL_PERCENTUAL_ACRESCIMO')}" placeholder="0,00">
                        </div>
                        <div class="modal-field">
                            <label class="modal-label">Vazão Máxima de Teste (l/s)</label>
                            <input type="text" name="vl_vazao_maxima" id="vl_vazao_maxima" class="modal-input readonly" value="${getValue('VL_VAZAO_MAXIMA')}" placeholder="0,00" readonly>
                        </div>
                        <div class="modal-field">
                            <label class="modal-label">Dif. Pressão Máximo de Teste (mm H2O)</label>
                            <input type="text" name="vl_pressao_maxima" id="vl_pressao_maxima" class="modal-input readonly" value="${getValue('VL_PRESSAO_MAXIMA')}" placeholder="0,00" readonly>
                        </div>
                        <div class="modal-field full-width">
                            <div class="modal-checkbox">
                                <input type="checkbox" name="op_atualiza_k" id="opAtualizaK" value="1" ${calibracao && calibracao.OP_ATUALIZA_K == 1 ? 'checked' : ''}>
                                <label class="modal-checkbox-label" for="opAtualizaK">Atualiza a Constante K do Ponto de Medição</label>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        `;
        
        atualizarNumeracaoLeituras();
    }

    function criarLinhaLeitura(pontoLeitura, vazao, incerteza, k) {
        return `
            <tr class="linha-leitura">
                <td class="col-leitura">${String(pontoLeitura).padStart(2, '0')}:</td>
                <td><input type="text" class="input-vazao" value="${vazao}" placeholder="0,000000"></td>
                <td><input type="text" class="input-incerteza" value="${incerteza}" placeholder="0,000000"></td>
                <td><input type="text" class="input-k" value="${k}" placeholder="0,0000000000"></td>
                <td class="col-acao">
                    <button type="button" class="btn-remove-leitura" onclick="removerLeitura(this)" title="Remover leitura">
                        <ion-icon name="trash-outline"></ion-icon>
                    </button>
                </td>
            </tr>
        `;
    }

    function adicionarLeitura() {
        const tbody = document.getElementById('tabelaLeituras');
        if (!tbody) return;
        
        const linhas = tbody.querySelectorAll('.linha-leitura');
        if (linhas.length >= 10) {
            showToast('Máximo de 10 leituras permitido', 'erro');
            return;
        }
        
        const novaLinha = document.createElement('tr');
        novaLinha.className = 'linha-leitura';
        novaLinha.innerHTML = `
            <td class="col-leitura">00:</td>
            <td><input type="text" class="input-vazao" value="" placeholder="0,000000"></td>
            <td><input type="text" class="input-incerteza" value="" placeholder="0,000000"></td>
            <td><input type="text" class="input-k" value="" placeholder="0,0000000000"></td>
            <td class="col-acao">
                <button type="button" class="btn-remove-leitura" onclick="removerLeitura(this)" title="Remover leitura">
                    <ion-icon name="trash-outline"></ion-icon>
                </button>
            </td>
        `;
        tbody.appendChild(novaLinha);
        atualizarNumeracaoLeituras();
        
        // Foco no primeiro campo da nova linha
        novaLinha.querySelector('.input-vazao').focus();
    }

    function removerLeitura(btn) {
        const tbody = document.getElementById('tabelaLeituras');
        const linhas = tbody.querySelectorAll('.linha-leitura');
        
        if (linhas.length <= 1) {
            showToast('É necessário manter pelo menos uma leitura', 'erro');
            return;
        }
        
        const linha = btn.closest('tr');
        linha.remove();
        atualizarNumeracaoLeituras();
    }

    function atualizarNumeracaoLeituras() {
        const tbody = document.getElementById('tabelaLeituras');
        if (!tbody) return;
        
        const linhas = tbody.querySelectorAll('.linha-leitura');
        linhas.forEach((linha, index) => {
            const colLeitura = linha.querySelector('.col-leitura');
            if (colLeitura) {
                colLeitura.textContent = String(index + 1).padStart(2, '0') + ':';
            }
        });
        
        // Desabilita botão de adicionar se atingiu o limite
        const btnAdd = document.querySelector('.btn-add-leitura');
        if (btnAdd) {
            btnAdd.disabled = linhas.length >= 10;
        }
    }

    function calcularCalibracao(silencioso = false) {
        // Coleta as leituras da tabela
        const leituras = coletarLeiturasParaCalculo();
        
        if (leituras.length === 0) {
            if (!silencioso) showToast('Informe pelo menos uma leitura para calcular', 'erro');
            return false;
        }

        // Obtém o percentual de acréscimo
        const percentualAcrescimo = parseFloat(document.getElementById('vl_percentual_acrescimo')?.value?.replace(',', '.')) || 0;
        const kAnterior = parseFloat(document.getElementById('vl_k_anterior')?.value?.replace(',', '.')) || 0;

        // Valida se todas as leituras têm os campos preenchidos
        for (const leitura of leituras) {
            if (leitura.vl_k_deprimogenio === 0 || leitura.vl_vazao_ep === 0 || leitura.vl_percentual_incerteza_ep === 0) {
                if (!silencioso) showToast('Preencha todos os campos das leituras (Vazão EP, Incerteza EP e K Deprimogênio)', 'erro');
                return false;
            }
        }

        if (percentualAcrescimo === 0) {
            if (!silencioso) showToast('Informe o Percentual de Acréscimo', 'erro');
            return false;
        }

        // Variáveis para o cálculo
        let valorVazaoMaxima = Number.MIN_VALUE;
        let valorMedia = 0;
        let quantidadeMedia = 0;

        // Para cada leitura
        for (const leitura of leituras) {
            // Acumula para a média
            valorMedia += leitura.vl_k_deprimogenio;
            quantidadeMedia++;
            
            // Verifica se o valor é maior que o máximo
            if (leitura.vl_vazao_ep > valorVazaoMaxima) {
                valorVazaoMaxima = leitura.vl_vazao_ep;
            }
        }

        // Verifica se houve leituras
        if (quantidadeMedia === 0) {
            if (!silencioso) showToast('Calibração sem leitura válida', 'erro');
            return false;
        }

        // Calcula o K médio
        // VL_K_MEDIO = (soma dos K / quantidade) / 0.0062747
        const vlKMedio = (valorMedia / quantidadeMedia) / 0.0062747;

        // Calcula a vazão máxima com o percentual de acréscimo
        // VL_VAZAO_MAXIMA = valorVazaoMaxima * (1 + (VL_PERCENTUAL_ACRESCIMO / 100)) * 1000
        const vlVazaoMaxima = valorVazaoMaxima * (1 + (percentualAcrescimo / 100)) * 1000;

        // Calcula a variação de pressão máxima em mm H2O
        // VL_PRESSAO_MAXIMA = ((VL_VAZAO_MAXIMA / VL_K_MEDIO) ^ 2) * 25.4
        const vlPressaoMaxima = Math.pow((vlVazaoMaxima / vlKMedio), 2) * 25.4;

        // Calcula o percentual de desvio do K (se K anterior informado)
        let vlDesvio = '';
        if (kAnterior > 0) {
            // VL_DESVIO = ((VL_K_MEDIO - VL_K_ANTERIOR) / VL_K_ANTERIOR) * 100
            vlDesvio = ((vlKMedio - kAnterior) / kAnterior) * 100;
        }

        // Atualiza os campos na tela
        document.getElementById('vl_k_medio').value = vlKMedio;
        document.getElementById('vl_vazao_maxima').value = vlVazaoMaxima;
        document.getElementById('vl_pressao_maxima').value = vlPressaoMaxima;
        
        if (vlDesvio !== '') {
            document.getElementById('vl_desvio').value = vlDesvio;
        }

        if (!silencioso) showToast('Cálculo realizado com sucesso!', 'sucesso');
        return true;
    }

    function coletarLeiturasParaCalculo() {
        const leituras = [];
        const tbody = document.getElementById('tabelaLeituras');
        if (!tbody) return leituras;
        
        const linhas = tbody.querySelectorAll('.linha-leitura');
        linhas.forEach((linha, index) => {
            const vazaoStr = linha.querySelector('.input-vazao')?.value || '';
            const incertezaStr = linha.querySelector('.input-incerteza')?.value || '';
            const kStr = linha.querySelector('.input-k')?.value || '';
            
            const vazao = parseFloat(vazaoStr.replace(',', '.')) || 0;
            const incerteza = parseFloat(incertezaStr.replace(',', '.')) || 0;
            const k = parseFloat(kStr.replace(',', '.')) || 0;
            
            // Só adiciona se pelo menos um campo estiver preenchido
            if (vazao > 0 || incerteza > 0 || k > 0) {
                leituras.push({
                    cd_ponto_leitura: index + 1,
                    vl_vazao_ep: vazao,
                    vl_percentual_incerteza_ep: incerteza,
                    vl_k_deprimogenio: k
                });
            }
        });
        return leituras;
    }

    function coletarLeituras() {
        const leituras = [];
        const tbody = document.getElementById('tabelaLeituras');
        if (!tbody) return leituras;
        
        const linhas = tbody.querySelectorAll('.linha-leitura');
        linhas.forEach((linha, index) => {
            const vazao = linha.querySelector('.input-vazao')?.value || '';
            const incerteza = linha.querySelector('.input-incerteza')?.value || '';
            const k = linha.querySelector('.input-k')?.value || '';
            
            // Só adiciona se pelo menos um campo estiver preenchido
            if (vazao || incerteza || k) {
                leituras.push({
                    cd_ponto_leitura: index + 1,
                    vl_vazao_ep: vazao,
                    vl_percentual_incerteza_ep: incerteza,
                    vl_k_deprimogenio: k
                });
            }
        });
        return leituras;
    }

    function salvarCalibracao() {
        const form = document.getElementById('formCalibracao');
        if (!form) {
            showToast('Formulário não encontrado', 'erro');
            return;
        }

        // Executa o cálculo antes de salvar (silencioso = false para mostrar erros)
        if (!calcularCalibracao(false)) {
            return; // Se o cálculo falhar, não salva
        }

        const btnSalvar = document.getElementById('btnSalvarCalibracao');
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = '<ion-icon name="hourglass-outline"></ion-icon> Salvando...';

        const formData = new FormData(form);
        
        // Adiciona o checkbox se não estiver marcado
        if (!form.querySelector('[name="op_atualiza_k"]').checked) {
            formData.set('op_atualiza_k', '0');
        }

        // Primeiro salva a calibração
        fetch('bd/calibracaoDeprimogenio/salvarCalibracaoDeprimogenio.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                calibracaoCdChave = data.cd_chave;
                
                // Depois salva as leituras
                const leituras = coletarLeituras();
                const leiturasData = new FormData();
                leiturasData.append('cd_calibracao_deprimogenio', calibracaoCdChave);
                leiturasData.append('leituras', JSON.stringify(leituras));

                return fetch('bd/calibracaoDeprimogenio/salvarLeituras.php', {
                    method: 'POST',
                    body: leiturasData
                }).then(response => response.json());
            } else {
                throw new Error(data.message || 'Erro ao salvar calibração');
            }
        })
        .then(dataLeituras => {
            if (dataLeituras && dataLeituras.success) {
                showToast('Calibração e leituras salvas com sucesso!', 'sucesso');
                // Fecha o modal após salvar
                setTimeout(function() {
                    fecharModalLeituras();
                }, 500);
            } else if (dataLeituras) {
                showToast(dataLeituras.message || 'Erro ao salvar leituras', 'erro');
            }
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = '<ion-icon name="save-outline"></ion-icon> Salvar';
        })
        .catch(error => {
            console.error('Erro:', error);
            showToast(error.message || 'Erro ao comunicar com o servidor', 'erro');
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = '<ion-icon name="save-outline"></ion-icon> Salvar';
        });
    }

    function fecharModalLeituras() {
        document.getElementById('modalLeituras').classList.remove('active');
    }

    // Fechar modal ao clicar fora
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('modalLeituras');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModalLeituras();
                }
            });
        }
    });

    // Fechar modal com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('modalLeituras');
            if (modal && modal.classList.contains('active')) {
                fecharModalLeituras();
            }
        }
    });
    <?php endif; ?>
</script>

<?php if ($isEdicao): ?>
<!-- Modal Leituras -->
<div id="modalLeituras" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <ion-icon name="analytics-outline"></ion-icon>
                <span>Calibração de Deprimogênio</span>
            </div>
            <button type="button" class="modal-close" onclick="fecharModalLeituras()">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-body" id="modalLeiturasBody">
            <div class="modal-empty">
                <ion-icon name="hourglass-outline"></ion-icon>
                <p>Carregando dados...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal secondary" onclick="fecharModalLeituras()">
                <ion-icon name="close-outline"></ion-icon>
                Cancelar
            </button>
            <button type="button" id="btnCalcular" class="btn-modal calcular" onclick="calcularCalibracao()">
                <ion-icon name="calculator-outline"></ion-icon>
                Calcular
            </button>
            <button type="button" id="btnSalvarCalibracao" class="btn-modal primary" onclick="salvarCalibracao()">
                <ion-icon name="save-outline"></ion-icon>
                Salvar
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include_once 'includes/footer.inc.php'; ?>
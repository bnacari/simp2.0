<?php
include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

exigePermissaoTela('Cadastro de Conjunto Motor-Bomba', ACESSO_ESCRITA);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdicao = $id > 0;
$motorBomba = null;

if ($isEdicao) {
    $sql = "SELECT CMB.*, L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO, L.DS_NOME AS DS_LOCALIDADE, L.CD_UNIDADE,
                   U.DS_NOME AS DS_UNIDADE, U.CD_CODIGO AS CD_UNIDADE_CODIGO
            FROM SIMP.dbo.CONJUNTO_MOTOR_BOMBA CMB
            INNER JOIN SIMP.dbo.LOCALIDADE L ON CMB.CD_LOCALIDADE = L.CD_CHAVE
            INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
            WHERE CMB.CD_CHAVE = :id";
    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([':id' => $id]);
    $motorBomba = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$motorBomba) {
        $_SESSION['msg'] = 'Registro não encontrado.';
        $_SESSION['msg_tipo'] = 'erro';
        header('Location: motorBomba.php');
        exit;
    }
}

$sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME, CD_CODIGO FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

$sqlUsuarios = $pdoSIMP->query("SELECT CD_USUARIO, DS_NOME, DS_MATRICULA FROM SIMP.dbo.USUARIO WHERE OP_BLOQUEADO = 2 ORDER BY DS_NOME");
$usuarios = $sqlUsuarios->fetchAll(PDO::FETCH_ASSOC);

$tiposEixo = [['value' => 'H', 'text' => 'Horizontal'], ['value' => 'V', 'text' => 'Vertical']];
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

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
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
        overflow-x: hidden;
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
        display: flex;
        flex-direction: column;
        gap: 4px;
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

    /* Select2 Custom Styling */
    .select2-container--default .select2-selection--single {
        height: 36px;
        padding: 4px 8px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        transition: all 0.2s ease;
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

    .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #94a3b8;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 34px;
        right: 8px;
    }

    .select2-dropdown {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #3b82f6;
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

    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
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

    /* ============================================
       Anexos
       ============================================ */
    .badge-count {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 10px;
        margin-left: auto;
    }

    .upload-area {
        border: 2px dashed #cbd5e1;
        border-radius: 10px;
        background: #f8fafc;
        margin-bottom: 16px;
        transition: all 0.3s ease;
    }

    .upload-area.drag-over {
        border-color: #3b82f6;
        background: #eff6ff;
    }

    .upload-placeholder {
        padding: 30px 20px;
        text-align: center;
        cursor: pointer;
    }

    .upload-placeholder:hover {
        background: #f1f5f9;
    }

    .upload-placeholder ion-icon {
        font-size: 40px;
        color: #3b82f6;
        margin-bottom: 8px;
    }

    .upload-placeholder p {
        margin: 0 0 4px 0;
        color: #475569;
        font-size: 13px;
        font-weight: 500;
    }

    .upload-hint {
        color: #94a3b8;
        font-size: 11px;
    }

    .upload-selected {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        padding: 12px;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 10px;
        align-items: center;
        margin-bottom: 16px;
    }

    .selected-file {
        display: flex;
        align-items: center;
        gap: 8px;
        background: white;
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
    }

    .selected-file ion-icon {
        font-size: 18px;
        color: #3b82f6;
    }

    .selected-file span {
        font-size: 12px;
        color: #334155;
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .btn-remove-file {
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
        display: flex;
    }

    .btn-remove-file ion-icon {
        font-size: 18px;
        color: #ef4444;
    }

    .anexos-lista {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .loading-anexos {
        text-align: center;
        padding: 20px;
        color: #64748b;
        font-size: 12px;
    }

    .anexo-vazio {
        text-align: center;
        padding: 30px 20px;
        color: #94a3b8;
        font-size: 13px;
    }

    .anexo-vazio ion-icon {
        font-size: 32px;
        display: block;
        margin: 0 auto 8px;
    }

    .anexo-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        transition: all 0.2s ease;
    }

    .anexo-item:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    .anexo-icon {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .anexo-icon ion-icon {
        font-size: 18px;
        color: white;
    }

    .anexo-info {
        flex: 1;
        min-width: 0;
    }

    .anexo-nome {
        font-size: 12px;
        font-weight: 600;
        color: #334155;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .anexo-meta {
        font-size: 10px;
        color: #94a3b8;
        margin-top: 2px;
    }

    .anexo-acoes {
        display: flex;
        gap: 6px;
    }

    .anexo-acoes .btn-acao {
        width: 30px;
        height: 30px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        background: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .anexo-acoes .btn-acao ion-icon {
        font-size: 14px;
        color: #64748b;
    }

    .anexo-acoes .btn-acao:hover {
        background: #f1f5f9;
    }

    .anexo-acoes .btn-acao.download:hover {
        background: #dbeafe;
        border-color: #3b82f6;
    }

    .anexo-acoes .btn-acao.download:hover ion-icon {
        color: #3b82f6;
    }

    .anexo-acoes .btn-acao.delete:hover {
        background: #fee2e2;
        border-color: #ef4444;
    }

    .anexo-acoes .btn-acao.delete:hover ion-icon {
        color: #ef4444;
    }

    @media (max-width: 768px) {
        .upload-selected {
            flex-direction: column;
        }
        
        .upload-selected .form-control {
            width: 100%;
        }
        
        .anexo-item {
            flex-wrap: wrap;
        }
        
        .anexo-acoes {
            width: 100%;
            justify-content: flex-end;
            margin-top: 8px;
        }
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
                    <h1><?= $isEdicao ? 'Editar Conjunto Motor Bomba' : 'Novo Conjunto Motor Bomba' ?></h1>
                    <p class="page-header-subtitle">
                        <?= $isEdicao ? 'Atualize as informações do conjunto' : 'Cadastre um novo conjunto motor bomba no sistema' ?>
                    </p>
                </div>
            </div>
            <div class="header-actions">
                <a href="motorBomba.php" class="btn-voltar">
                    <ion-icon name="arrow-back-outline"></ion-icon>
                    Voltar
                </a>
            </div>
        </div>
    </div>

    <form id="formMotorBomba" method="post">
        <input type="hidden" name="cd_chave" value="<?= $id ?>">

        <!-- Identificação -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="information-circle-outline"></ion-icon>
                <h2>Identificação</h2>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="business-outline"></ion-icon>
                            Unidade <span class="required">*</span>
                        </label>
                        <select id="selectUnidade" name="cd_unidade" class="form-control select2" required>
                            <option value="">Selecione a Unidade</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?= $u['CD_UNIDADE'] ?>" <?= ($isEdicao && $motorBomba['CD_UNIDADE'] == $u['CD_UNIDADE']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['CD_CODIGO'] . ' - ' . $u['DS_NOME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="location-outline"></ion-icon>
                            Localidade <span class="required">*</span>
                        </label>
                        <select id="selectLocalidade" name="cd_localidade" class="form-control select2" required <?= !$isEdicao ? 'disabled' : '' ?>>
                            <?php if ($isEdicao): ?>
                                <option value="<?= $motorBomba['CD_LOCALIDADE'] ?>" selected>
                                    <?= htmlspecialchars($motorBomba['CD_LOCALIDADE_CODIGO'] . ' - ' . $motorBomba['DS_LOCALIDADE']) ?>
                                </option>
                            <?php else: ?>
                                <option value="">Selecione a Unidade primeiro</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="pricetag-outline"></ion-icon>
                            Nº do Conjunto <span class="required">*</span>
                        </label>
                        <input type="text" name="ds_codigo" class="form-control" maxlength="20" required 
                               value="<?= $isEdicao ? htmlspecialchars($motorBomba['DS_CODIGO']) : '' ?>" placeholder="Ex: CMB-001">
                    </div>
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="git-compare-outline"></ion-icon>
                            Tipo de Eixo <span class="required">*</span>
                        </label>
                        <select name="tp_eixo" class="form-control select2" required>
                            <option value="">Selecione</option>
                            <?php foreach ($tiposEixo as $t): ?>
                                <option value="<?= $t['value'] ?>" <?= ($isEdicao && $motorBomba['TP_EIXO'] == $t['value']) ? 'selected' : '' ?>><?= $t['text'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="form-label">
                            <ion-icon name="text-outline"></ion-icon>
                            Nome <span class="required">*</span>
                        </label>
                        <input type="text" name="ds_nome" class="form-control" maxlength="50" required 
                               value="<?= $isEdicao ? htmlspecialchars($motorBomba['DS_NOME']) : '' ?>" placeholder="Nome do conjunto">
                    </div>
                    <div class="form-group col-6">
                        <label class="form-label">
                            <ion-icon name="person-outline"></ion-icon>
                            Responsável <span class="required">*</span>
                        </label>
                        <select name="cd_usuario_responsavel" class="form-control select2" required>
                            <option value="">Selecione</option>
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?= $u['CD_USUARIO'] ?>" <?= ($isEdicao && $motorBomba['CD_USUARIO_RESPONSAVEL'] == $u['CD_USUARIO']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['DS_MATRICULA'] . ' - ' . $u['DS_NOME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-12">
                        <label class="form-label">
                            <ion-icon name="navigate-outline"></ion-icon>
                            Localização <span class="required">*</span>
                        </label>
                        <input type="text" name="ds_localizacao" class="form-control" maxlength="200" required 
                               value="<?= $isEdicao ? htmlspecialchars($motorBomba['DS_LOCALIZACAO']) : '' ?>" placeholder="Descrição da localização física">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-12">
                        <label class="form-label">
                            <ion-icon name="chatbox-outline"></ion-icon>
                            Observação
                        </label>
                        <textarea name="ds_observacao" class="form-control" rows="2" maxlength="200" 
                                  placeholder="Observações adicionais"><?= $isEdicao ? htmlspecialchars($motorBomba['DS_OBSERVACAO'] ?? '') : '' ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dados da Bomba -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="water-outline"></ion-icon>
                <h2>Dados da Bomba</h2>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="construct-outline"></ion-icon>
                            Fabricante
                        </label>
                        <input type="text" name="ds_fabricante_bomba" class="form-control" maxlength="20" 
                               value="<?= $isEdicao ? htmlspecialchars($motorBomba['DS_FABRICANTE_BOMBA'] ?? '') : '' ?>">
                    </div>
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="options-outline"></ion-icon>
                            Tipo
                        </label>
                        <input type="text" name="ds_tipo_bomba" class="form-control" maxlength="20" 
                               value="<?= $isEdicao ? htmlspecialchars($motorBomba['DS_TIPO_BOMBA'] ?? '') : '' ?>">
                    </div>
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="barcode-outline"></ion-icon>
                            Série
                        </label>
                        <input type="text" name="ds_serie_bomba" class="form-control" maxlength="20" 
                               value="<?= $isEdicao ? htmlspecialchars($motorBomba['DS_SERIE_BOMBA'] ?? '') : '' ?>">
                    </div>
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="disc-outline"></ion-icon>
                            Diâmetro Rotor (mm) <span class="required">*</span>
                        </label>
                        <input type="number" name="vl_diametro_rotor_bomba" class="form-control" step="0.01" required 
                               value="<?= $isEdicao ? $motorBomba['VL_DIAMETRO_ROTOR_BOMBA'] : '' ?>" placeholder="0.00">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="speedometer-outline"></ion-icon>
                            Vazão (L/s)
                        </label>
                        <input type="number" name="vl_vazao_bomba" class="form-control" step="0.01" 
                               value="<?= $isEdicao ? $motorBomba['VL_VAZAO_BOMBA'] : '' ?>" placeholder="0.00">
                    </div>
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="trending-up-outline"></ion-icon>
                            Altura Manométrica (mca) <span class="required">*</span>
                        </label>
                        <input type="number" name="vl_altura_manometrica_bomba" class="form-control" step="0.01" required 
                               value="<?= $isEdicao ? $motorBomba['VL_ALTURA_MANOMETRICA_BOMBA'] : '' ?>" placeholder="0.00">
                    </div>
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="sync-outline"></ion-icon>
                            Rotação (rpm)
                        </label>
                        <input type="number" name="vl_rotacao_bomba" class="form-control" step="0.01" 
                               value="<?= $isEdicao ? $motorBomba['VL_ROTACAO_BOMBA'] : '' ?>" placeholder="0">
                    </div>
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="enter-outline"></ion-icon>
                            Área Sucção (mm²)
                        </label>
                        <input type="number" name="vl_area_succao_bomba" class="form-control" step="0.01" readonly 
                               value="<?= $isEdicao ? $motorBomba['VL_AREA_SUCCAO_BOMBA'] : '' ?>" placeholder="Calculado">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="exit-outline"></ion-icon>
                            Área Recalque (mm²)
                        </label>
                        <input type="number" name="vl_area_recalque_bomba" class="form-control" step="0.01" readonly 
                               value="<?= $isEdicao ? $motorBomba['VL_AREA_RECALQUE_BOMBA'] : '' ?>" placeholder="Calculado">
                    </div>
                </div>
            </div>
        </div>

        <!-- Dados do Motor -->
        <div class="form-card">
            <div class="form-card-header">
                <ion-icon name="flash-outline"></ion-icon>
                <h2>Dados do Motor</h2>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="construct-outline"></ion-icon>
                            Fabricante
                        </label>
                        <input type="text" name="ds_fabricante_motor" class="form-control" maxlength="20" 
                               value="<?= $isEdicao ? htmlspecialchars($motorBomba['DS_FABRICANTE_MOTOR'] ?? '') : '' ?>">
                    </div>
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="options-outline"></ion-icon>
                            Tipo
                        </label>
                        <input type="text" name="ds_tipo_motor" class="form-control" maxlength="20" 
                               value="<?= $isEdicao ? htmlspecialchars($motorBomba['DS_TIPO_MOTOR'] ?? '') : '' ?>">
                    </div>
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="barcode-outline"></ion-icon>
                            Série
                        </label>
                        <input type="text" name="ds_serie_motor" class="form-control" maxlength="20" 
                               value="<?= $isEdicao ? htmlspecialchars($motorBomba['DS_SERIE_MOTOR'] ?? '') : '' ?>">
                    </div>
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="pulse-outline"></ion-icon>
                            Tensão (V) <span class="required">*</span>
                        </label>
                        <input type="number" name="vl_tensao_motor" class="form-control" step="0.01" required 
                               value="<?= $isEdicao ? $motorBomba['VL_TENSAO_MOTOR'] : '' ?>" placeholder="0">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="flash-outline"></ion-icon>
                            Corrente Elétrica (A) <span class="required">*</span>
                        </label>
                        <input type="number" name="vl_corrente_eletrica_motor" class="form-control" step="0.01" required 
                               value="<?= $isEdicao ? $motorBomba['VL_CORRENTE_ELETRICA_MOTOR'] : '' ?>" placeholder="0.00">
                    </div>
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="fitness-outline"></ion-icon>
                            Potência (CV) <span class="required">*</span>
                        </label>
                        <input type="number" name="vl_potencia_motor" class="form-control" step="0.01" required 
                               value="<?= $isEdicao ? $motorBomba['VL_POTENCIA_MOTOR'] : '' ?>" placeholder="0.00">
                    </div>
                    <div class="form-group col-3">
                        <label class="form-label">
                            <ion-icon name="sync-outline"></ion-icon>
                            Rotação (rpm)
                        </label>
                        <input type="number" name="vl_rotacao_motor" class="form-control" step="0.01" 
                               value="<?= $isEdicao ? $motorBomba['VL_ROTACAO_MOTOR'] : '' ?>" placeholder="0">
                    </div>
                </div>
            </div>
        </div>

        <?php if ($isEdicao): ?>
        <!-- Anexos (somente em edição) -->
        <div class="form-card" id="cardAnexos">
            <div class="form-card-header">
                <ion-icon name="attach-outline"></ion-icon>
                <h2>Anexos</h2>
                <span class="badge-count" id="badgeAnexosCount">0</span>
            </div>
            <div class="form-card-body">
                <!-- Área de Upload -->
                <div class="upload-area" id="uploadArea">
                    <input type="file" id="inputAnexo" style="display: none;" 
                           accept=".jpg,.jpeg,.png,.gif,.bmp,.webp,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.7z,.txt,.csv">
                    <div class="upload-placeholder" onclick="document.getElementById('inputAnexo').click()">
                        <ion-icon name="cloud-upload-outline"></ion-icon>
                        <p>Clique para selecionar ou arraste o arquivo</p>
                        <span class="upload-hint">Formatos: Imagens, PDF, DOC, XLS, ZIP (máx. 10MB)</span>
                    </div>
                </div>
                
                <!-- Arquivo selecionado -->
                <div class="upload-selected" id="uploadSelected" style="display: none;">
                    <div class="selected-file">
                        <ion-icon name="document-outline" id="selectedFileIcon"></ion-icon>
                        <span id="selectedFileName"></span>
                        <button type="button" class="btn-remove-file" onclick="limparArquivoSelecionado()">
                            <ion-icon name="close-circle-outline"></ion-icon>
                        </button>
                    </div>
                    <input type="text" id="inputObservacaoAnexo" class="form-control" 
                           placeholder="Observação (opcional)" maxlength="500" style="flex: 1;">
                    <button type="button" class="btn btn-primary" onclick="enviarAnexo()">
                        <ion-icon name="cloud-upload-outline"></ion-icon>
                        Enviar
                    </button>
                </div>

                <!-- Lista de Anexos -->
                <div class="anexos-lista" id="listaAnexos">
                    <div class="loading-anexos">Carregando anexos...</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Botões de Ação -->
        <div class="form-card">
            <div class="form-actions">
                <a href="motorBomba.php" class="btn btn-secondary">
                    <ion-icon name="close-outline"></ion-icon>
                    Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <ion-icon name="checkmark-outline"></ion-icon>
                    Salvar
                </button>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2').select2({
        width: '100%',
        placeholder: 'Selecione...',
        allowClear: true
    });

    $('#selectUnidade').on('change', function() {
        const cdUnidade = $(this).val();
        if (cdUnidade) {
            carregarLocalidades(cdUnidade);
        } else {
            $('#selectLocalidade').val('').prop('disabled', true)
                .html('<option value="">Selecione a Unidade primeiro</option>')
                .trigger('change.select2');
        }
    });

    $('#formMotorBomba').on('submit', function(e) {
        e.preventDefault();
        salvar();
    });
});

function carregarLocalidades(cdUnidade) {
    $('#selectLocalidade').prop('disabled', true).html('<option value="">Carregando...</option>');
    
    $.ajax({
        url: 'bd/pontoMedicao/getLocalidades.php',
        method: 'GET',
        data: { cd_unidade: cdUnidade },
        dataType: 'json',
        success: function(response) {
            let options = '<option value="">Selecione a Localidade</option>';
            if (response.success && response.data) {
                response.data.forEach(function(loc) {
                    options += `<option value="${loc.CD_CHAVE}">${loc.CD_LOCALIDADE} - ${loc.DS_NOME}</option>`;
                });
            }
            $('#selectLocalidade').html(options).prop('disabled', false).trigger('change.select2');
        },
        error: function() {
            $('#selectLocalidade').html('<option value="">Erro ao carregar</option>');
            showToast('Erro ao carregar localidades', 'erro');
        }
    });
}

function salvar() {
    const formData = new FormData(document.getElementById('formMotorBomba'));
    
    $.ajax({
        url: 'bd/motorBomba/salvarMotorBomba.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                setTimeout(function() {
                    window.location.href = 'motorBomba.php';
                }, 1500);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        },
        error: function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        }
    });
}

<?php if ($isEdicao): ?>
// ============================================
// Gerenciamento de Anexos
// ============================================
let arquivoSelecionado = null;
const cdConjunto = <?= $id ?>;

$(document).ready(function() {
    carregarAnexos();
    
    // Drag and drop
    const uploadArea = document.getElementById('uploadArea');
    if (uploadArea) {
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadArea.classList.add('drag-over');
        });
        
        uploadArea.addEventListener('dragleave', function() {
            uploadArea.classList.remove('drag-over');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
            if (e.dataTransfer.files.length > 0) {
                selecionarArquivo(e.dataTransfer.files[0]);
            }
        });
    }
    
    document.getElementById('inputAnexo').addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            selecionarArquivo(e.target.files[0]);
        }
    });
});

function selecionarArquivo(file) {
    const extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', '7z', 'txt', 'csv'];
    const extensao = file.name.split('.').pop().toLowerCase();
    
    if (!extensoesPermitidas.includes(extensao)) {
        showToast('Tipo de arquivo não permitido', 'erro');
        return;
    }
    
    if (file.size > 10 * 1024 * 1024) {
        showToast('Arquivo muito grande (máx. 10MB)', 'erro');
        return;
    }
    
    arquivoSelecionado = file;
    document.getElementById('selectedFileName').textContent = file.name;
    
    // Ícone baseado no tipo
    const icones = {
        'pdf': 'document-text-outline',
        'doc': 'document-text-outline',
        'docx': 'document-text-outline',
        'xls': 'grid-outline',
        'xlsx': 'grid-outline',
        'zip': 'archive-outline',
        'rar': 'archive-outline',
        '7z': 'archive-outline'
    };
    document.getElementById('selectedFileIcon').setAttribute('name', icones[extensao] || 'image-outline');
    
    document.getElementById('uploadArea').style.display = 'none';
    document.getElementById('uploadSelected').style.display = 'flex';
}

function limparArquivoSelecionado() {
    arquivoSelecionado = null;
    document.getElementById('inputAnexo').value = '';
    document.getElementById('inputObservacaoAnexo').value = '';
    document.getElementById('uploadArea').style.display = 'block';
    document.getElementById('uploadSelected').style.display = 'none';
}

function enviarAnexo() {
    if (!arquivoSelecionado) {
        showToast('Selecione um arquivo', 'alerta');
        return;
    }
    
    const formData = new FormData();
    formData.append('arquivo', arquivoSelecionado);
    formData.append('cd_conjunto', cdConjunto);
    formData.append('observacao', document.getElementById('inputObservacaoAnexo').value);
    
    $.ajax({
        url: 'bd/motorBomba/uploadAnexo.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast('Anexo enviado com sucesso', 'sucesso');
                limparArquivoSelecionado();
                carregarAnexos();
            } else {
                showToast(response.message || 'Erro ao enviar anexo', 'erro');
            }
        },
        error: function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        }
    });
}

function carregarAnexos() {
    $.ajax({
        url: 'bd/motorBomba/listarAnexos.php',
        method: 'GET',
        data: { cd_conjunto: cdConjunto },
        dataType: 'json',
        success: function(response) {
            renderizarAnexos(response.data || []);
        },
        error: function() {
            document.getElementById('listaAnexos').innerHTML = '<div class="anexo-vazio">Erro ao carregar anexos</div>';
        }
    });
}

function renderizarAnexos(anexos) {
    const container = document.getElementById('listaAnexos');
    document.getElementById('badgeAnexosCount').textContent = anexos.length;
    
    if (anexos.length === 0) {
        container.innerHTML = `
            <div class="anexo-vazio">
                <ion-icon name="folder-open-outline"></ion-icon>
                Nenhum anexo cadastrado
            </div>
        `;
        return;
    }
    
    let html = '';
    anexos.forEach(function(anexo) {
        html += `
            <div class="anexo-item">
                <div class="anexo-icon">
                    <ion-icon name="${anexo.DS_ICONE || 'document-outline'}"></ion-icon>
                </div>
                <div class="anexo-info">
                    <div class="anexo-nome">${anexo.DS_FILENAME}</div>
                    <div class="anexo-meta">${anexo.VL_TAMANHO_FORMATADO || ''}</div>
                </div>
                <div class="anexo-acoes">
                    <a href="bd/motorBomba/downloadAnexo.php?id=${anexo.CD_ANEXO}" class="btn-acao download" title="Download">
                        <ion-icon name="download-outline"></ion-icon>
                    </a>
                    <button type="button" class="btn-acao delete" title="Excluir" onclick="excluirAnexo(${anexo.CD_ANEXO}, '${anexo.DS_FILENAME.replace(/'/g, "\\'")}')">
                        <ion-icon name="trash-outline"></ion-icon>
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function excluirAnexo(id, nome) {
    if (!confirm('Deseja excluir o anexo "' + nome + '"?')) {
        return;
    }
    
    $.ajax({
        url: 'bd/motorBomba/excluirAnexo.php',
        method: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast('Anexo excluído com sucesso', 'sucesso');
                carregarAnexos();
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        },
        error: function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        }
    });
}
<?php endif; ?>
</script>

<?php include_once 'includes/footer.inc.php'; ?>
<?php
include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

exigePermissaoTela('Cadastro de Conjunto Motor-Bomba', ACESSO_LEITURA);
$podeEditar = podeEditarTela('Cadastro de Conjunto Motor-Bomba');

$id = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['msg'] = 'ID inválido.';
    $_SESSION['msg_tipo'] = 'erro';
    header('Location: motorBomba.php');
    exit;
}

// Buscar dados do Motor-Bomba
$sql = "SELECT CMB.*, L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO, L.DS_NOME AS DS_LOCALIDADE, L.CD_UNIDADE,
               U.DS_NOME AS DS_UNIDADE, U.CD_CODIGO AS CD_UNIDADE_CODIGO,
               UA.DS_NOME AS DS_USUARIO_ATUALIZACAO, UR.DS_NOME AS DS_USUARIO_RESPONSAVEL_NOME, UR.DS_MATRICULA AS DS_MATRICULA_RESPONSAVEL
        FROM SIMP.dbo.CONJUNTO_MOTOR_BOMBA CMB
        INNER JOIN SIMP.dbo.LOCALIDADE L ON CMB.CD_LOCALIDADE = L.CD_CHAVE
        INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
        LEFT JOIN SIMP.dbo.USUARIO UA ON CMB.CD_USUARIO_ULTIMA_ATUALIZACAO = UA.CD_USUARIO
        LEFT JOIN SIMP.dbo.USUARIO UR ON CMB.CD_USUARIO_RESPONSAVEL = UR.CD_USUARIO
        WHERE CMB.CD_CHAVE = :id";
$stmt = $pdoSIMP->prepare($sql);
$stmt->execute([':id' => $id]);
$mb = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mb) {
    $_SESSION['msg'] = 'Registro não encontrado.';
    $_SESSION['msg_tipo'] = 'erro';
    header('Location: motorBomba.php');
    exit;
}

// Buscar anexos
$anexos = [];
try {
    $sqlFunc = "SELECT TOP 1 CD_FUNCIONALIDADE FROM SIMP.dbo.FUNCIONALIDADE 
                WHERE DS_NOME LIKE '%Motor%Bomba%' OR DS_NOME LIKE '%Conjunto%Motor%'";
    $stmtFunc = $pdoSIMP->query($sqlFunc);
    $funcionalidade = $stmtFunc->fetch(PDO::FETCH_ASSOC);
    
    if ($funcionalidade) {
        $sqlAnexos = "SELECT A.CD_ANEXO, A.DS_NOME, A.DS_FILENAME, DATALENGTH(A.VB_ANEXO) AS VL_TAMANHO_BYTES,
                             A.DS_OBSERVACAO, A.DT_INCLUSAO, U.DS_NOME AS DS_USUARIO_UPLOAD
                      FROM SIMP.dbo.ANEXO A
                      LEFT JOIN SIMP.dbo.USUARIO U ON A.CD_USUARIO_RESPONSAVEL = U.CD_USUARIO
                      WHERE A.CD_FUNCIONALIDADE = :cd_func AND A.CD_CHAVE_FUNCIONALIDADE = :id
                      ORDER BY A.DT_INCLUSAO DESC";
        $stmtAnexos = $pdoSIMP->prepare($sqlAnexos);
        $stmtAnexos->execute([':cd_func' => $funcionalidade['CD_FUNCIONALIDADE'], ':id' => $id]);
        $anexos = $stmtAnexos->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $anexos = [];
}

$tiposEixo = ['H' => 'Horizontal', 'V' => 'Vertical'];

function formatarTamanho($bytes) {
    if (!$bytes) return '-';
    $bytes = (int)$bytes;
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2, ',', '.') . ' KB';
    return $bytes . ' bytes';
}

function getIcone($nome) {
    $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) return 'image-outline';
    if ($ext === 'pdf') return 'document-text-outline';
    if (in_array($ext, ['doc', 'docx'])) return 'document-outline';
    if (in_array($ext, ['xls', 'xlsx'])) return 'grid-outline';
    if (in_array($ext, ['zip', 'rar', '7z'])) return 'archive-outline';
    return 'document-outline';
}
?>

<style>
    *, *::before, *::after { box-sizing: border-box; }

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

    .header-actions {
        display: flex;
        gap: 10px;
    }

    .btn-voltar, .btn-editar {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        border: none;
    }

    .btn-voltar {
        background: rgba(255, 255, 255, 0.15);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .btn-voltar:hover { background: rgba(255, 255, 255, 0.25); }

    .btn-editar {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
    }

    .btn-editar:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
    }

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

    .badge-count {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 10px;
        margin-left: auto;
    }

    .form-card-body {
        padding: 16px 20px;
    }

    .form-row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -8px 12px -8px;
    }

    .form-row:last-child { margin-bottom: 0; }

    .form-group {
        padding: 0 8px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .col-12 { width: 100%; }
    .col-6 { width: 50%; }
    .col-4 { width: 33.333333%; }
    .col-3 { width: 25%; }

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

    .form-label ion-icon {
        font-size: 12px;
        color: #94a3b8;
    }

    .form-value {
        padding: 8px 12px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 12px;
        color: #334155;
        min-height: 36px;
        display: flex;
        align-items: center;
    }

    .form-value.highlight {
        color: #2563eb;
        font-weight: 600;
    }

    .form-value .badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        margin-right: 8px;
    }

    .badge-unidade {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .badge-horizontal {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .badge-vertical {
        background: #fef3c7;
        color: #b45309;
    }

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

    .btn ion-icon { font-size: 14px; }

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

    .btn-secondary:hover { background: #e2e8f0; }

    /* Anexos */
    .anexos-lista {
        display: flex;
        flex-direction: column;
        gap: 8px;
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
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .anexo-acoes .btn-acao ion-icon {
        font-size: 14px;
        color: #64748b;
    }

    .anexo-acoes .btn-acao:hover {
        background: #dbeafe;
        border-color: #3b82f6;
    }

    .anexo-acoes .btn-acao:hover ion-icon {
        color: #3b82f6;
    }

    @media (max-width: 992px) {
        .col-3 { width: 50%; }
        .col-4 { width: 50%; }
    }

    @media (max-width: 768px) {
        .page-container { padding: 12px; }
        .page-header { padding: 16px; }
        .page-header-content { flex-direction: column; align-items: stretch; }
        .page-header-info { flex-direction: column; text-align: center; }
        .page-header-icon { margin: 0 auto; }
        .header-actions { flex-direction: column; }
        .btn-voltar, .btn-editar { width: 100%; justify-content: center; }
        .col-3, .col-4, .col-6 { width: 100%; }
        .form-row { margin: 0 -4px 10px -4px; }
        .form-group { padding: 0 4px; margin-bottom: 8px; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; }
    }
</style>

<div class="page-container">
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="eye-outline"></ion-icon>
                </div>
                <div>
                    <h1>Visualizar Conjunto Motor Bomba</h1>
                    <p class="page-header-subtitle">Detalhes do conjunto: <?= htmlspecialchars($mb['DS_CODIGO'] . ' - ' . $mb['DS_NOME']) ?></p>
                </div>
            </div>
            <div class="header-actions">
                <a href="motorBomba.php" class="btn-voltar">
                    <ion-icon name="arrow-back-outline"></ion-icon>
                    Voltar
                </a>
                <?php if ($podeEditar): ?>
                <a href="motorBombaForm.php?id=<?= $id ?>" class="btn-editar">
                    <ion-icon name="create-outline"></ion-icon>
                    Editar
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Identificação -->
    <div class="form-card">
        <div class="form-card-header">
            <ion-icon name="information-circle-outline"></ion-icon>
            <h2>Identificação</h2>
        </div>
        <div class="form-card-body">
            <div class="form-row">
                <div class="form-group col-3">
                    <label class="form-label"><ion-icon name="business-outline"></ion-icon> Unidade</label>
                    <div class="form-value">
                        <span class="badge badge-unidade"><?= htmlspecialchars($mb['CD_UNIDADE_CODIGO']) ?></span>
                        <?= htmlspecialchars($mb['DS_UNIDADE']) ?>
                    </div>
                </div>
                <div class="form-group col-3">
                    <label class="form-label"><ion-icon name="location-outline"></ion-icon> Localidade</label>
                    <div class="form-value"><?= htmlspecialchars($mb['CD_LOCALIDADE_CODIGO'] . ' - ' . $mb['DS_LOCALIDADE']) ?></div>
                </div>
                <div class="form-group col-3">
                    <label class="form-label"><ion-icon name="pricetag-outline"></ion-icon> Nº do Conjunto</label>
                    <div class="form-value highlight"><?= htmlspecialchars($mb['DS_CODIGO'] ?? '-') ?></div>
                </div>
                <div class="form-group col-3">
                    <label class="form-label"><ion-icon name="git-compare-outline"></ion-icon> Tipo de Eixo</label>
                    <div class="form-value">
                        <span class="badge <?= $mb['TP_EIXO'] == 'H' ? 'badge-horizontal' : 'badge-vertical' ?>">
                            <?= $tiposEixo[$mb['TP_EIXO']] ?? '-' ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-6">
                    <label class="form-label"><ion-icon name="text-outline"></ion-icon> Nome</label>
                    <div class="form-value"><?= htmlspecialchars($mb['DS_NOME'] ?? '-') ?></div>
                </div>
                <div class="form-group col-6">
                    <label class="form-label"><ion-icon name="person-outline"></ion-icon> Responsável</label>
                    <div class="form-value">
                        <?= $mb['DS_USUARIO_RESPONSAVEL_NOME'] ? htmlspecialchars($mb['DS_MATRICULA_RESPONSAVEL'] . ' - ' . $mb['DS_USUARIO_RESPONSAVEL_NOME']) : '-' ?>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-12">
                    <label class="form-label"><ion-icon name="navigate-outline"></ion-icon> Localização</label>
                    <div class="form-value"><?= htmlspecialchars($mb['DS_LOCALIZACAO'] ?? '-') ?></div>
                </div>
            </div>
            <?php if ($mb['DS_OBSERVACAO']): ?>
            <div class="form-row">
                <div class="form-group col-12">
                    <label class="form-label"><ion-icon name="chatbox-outline"></ion-icon> Observação</label>
                    <div class="form-value"><?= nl2br(htmlspecialchars($mb['DS_OBSERVACAO'])) ?></div>
                </div>
            </div>
            <?php endif; ?>
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
                    <label class="form-label"><ion-icon name="construct-outline"></ion-icon> Fabricante</label>
                    <div class="form-value"><?= htmlspecialchars($mb['DS_FABRICANTE_BOMBA'] ?? '-') ?></div>
                </div>
                <div class="form-group col-3">
                    <label class="form-label"><ion-icon name="options-outline"></ion-icon> Tipo</label>
                    <div class="form-value"><?= htmlspecialchars($mb['DS_TIPO_BOMBA'] ?? '-') ?></div>
                </div>
                <div class="form-group col-3">
                    <label class="form-label"><ion-icon name="barcode-outline"></ion-icon> Série</label>
                    <div class="form-value"><?= htmlspecialchars($mb['DS_SERIE_BOMBA'] ?? '-') ?></div>
                </div>
                <div class="form-group col-3">
                    <label class="form-label"><ion-icon name="disc-outline"></ion-icon> Diâmetro Rotor (mm)</label>
                    <div class="form-value"><?= $mb['VL_DIAMETRO_ROTOR_BOMBA'] ? number_format($mb['VL_DIAMETRO_ROTOR_BOMBA'], 2, ',', '.') : '-' ?></div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-3">
                    <label class="form-label"><ion-icon name="speedometer-outline"></ion-icon> Vazão (L/s)</label>
                    <div class="form-value"><?= $mb['VL_VAZAO_BOMBA'] ? number_format($mb['VL_VAZAO_BOMBA'], 2, ',', '.') : '-' ?></div>
                </div>
                <div class="form-group col-3">
                    <label class="form-label"><ion-icon name="trending-up-outline"></ion-icon> Altura Manométrica (mca)</label>
                    <div class="form-value"><?= $mb['VL_ALTURA_MANOMETRICA_BOMBA'] ? number_format($mb['VL_ALTURA_MANOMETRICA_BOMBA'], 2, ',', '.') : '-' ?></div>
                </div>
                <div class="form-group col-3">
                    <label class="form-label"><ion-icon name="sync-outline"></ion-icon> Rotação (rpm)</label>
                    <div class="form-value"><?= $mb['VL_ROTACAO_BOMBA'] ? number_format($mb['VL_ROTACAO_BOMBA'], 0, ',', '.') : '-' ?></div>
                </div>
                <div class="form-group col-3">
                    <label class="form-label"><ion-icon name="enter-outline"></ion-icon> Área Sucção (mm²)</label>
                    <div class="form-value"><?= $mb['VL_AREA_SUCCAO_BOMBA'] ? number_format($mb['VL_AREA_SUCCAO_BOMBA'], 2, ',', '.') : '-' ?></div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-3">
                    <label class="form-label"><ion-icon name="exit-outline"></ion-icon> Área Recalque (mm²)</label>
                    <div class="form-value"><?= $mb['VL_AREA_RECALQUE_BOMBA'] ? number_format($mb['VL_AREA_RECALQUE_BOMBA'], 2, ',', '.') : '-' ?></div>
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
                    <label class="form-label"><ion-icon name="construct-outline"></ion-icon> Fabricante</label>
                    <div class="form-value"><?= htmlspecialchars($mb['DS_FABRICANTE_MOTOR'] ?? '-') ?></div>
                </div>
                <div class="form-group col-3">
                    <label class="form-label"><ion-icon name="options-outline"></ion-icon> Tipo</label>
                    <div class="form-value"><?= htmlspecialchars($mb['DS_TIPO_MOTOR'] ?? '-') ?></div>
                </div>
                <div class="form-group col-3">
                    <label class="form-label"><ion-icon name="barcode-outline"></ion-icon> Série</label>
                    <div class="form-value"><?= htmlspecialchars($mb['DS_SERIE_MOTOR'] ?? '-') ?></div>
                </div>
                <div class="form-group col-3">
                    <label class="form-label"><ion-icon name="pulse-outline"></ion-icon> Tensão (V)</label>
                    <div class="form-value"><?= $mb['VL_TENSAO_MOTOR'] ? number_format($mb['VL_TENSAO_MOTOR'], 0, ',', '.') : '-' ?></div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-3">
                    <label class="form-label"><ion-icon name="flash-outline"></ion-icon> Corrente Elétrica (A)</label>
                    <div class="form-value"><?= $mb['VL_CORRENTE_ELETRICA_MOTOR'] ? number_format($mb['VL_CORRENTE_ELETRICA_MOTOR'], 2, ',', '.') : '-' ?></div>
                </div>
                <div class="form-group col-3">
                    <label class="form-label"><ion-icon name="fitness-outline"></ion-icon> Potência (CV)</label>
                    <div class="form-value"><?= $mb['VL_POTENCIA_MOTOR'] ? number_format($mb['VL_POTENCIA_MOTOR'], 2, ',', '.') : '-' ?></div>
                </div>
                <div class="form-group col-3">
                    <label class="form-label"><ion-icon name="sync-outline"></ion-icon> Rotação (rpm)</label>
                    <div class="form-value"><?= $mb['VL_ROTACAO_MOTOR'] ? number_format($mb['VL_ROTACAO_MOTOR'], 0, ',', '.') : '-' ?></div>
                </div>
                <div class="form-group col-3">
                    <label class="form-label"><ion-icon name="time-outline"></ion-icon> Última Atualização</label>
                    <div class="form-value">
                        <?= $mb['DT_ULTIMA_ATUALIZACAO'] ? date('d/m/Y H:i', strtotime($mb['DT_ULTIMA_ATUALIZACAO'])) : '-' ?>
                        <?php if ($mb['DS_USUARIO_ATUALIZACAO']): ?>
                            <small style="color:#64748b;margin-left:6px;">por <?= htmlspecialchars($mb['DS_USUARIO_ATUALIZACAO']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Anexos -->
    <div class="form-card">
        <div class="form-card-header">
            <ion-icon name="attach-outline"></ion-icon>
            <h2>Anexos</h2>
            <span class="badge-count"><?= count($anexos) ?></span>
        </div>
        <div class="form-card-body">
            <div class="anexos-lista">
                <?php if (count($anexos) === 0): ?>
                    <div class="anexo-vazio">
                        <ion-icon name="folder-open-outline"></ion-icon>
                        Nenhum anexo cadastrado
                    </div>
                <?php else: ?>
                    <?php foreach ($anexos as $anexo): ?>
                    <div class="anexo-item">
                        <div class="anexo-icon">
                            <ion-icon name="<?= getIcone($anexo['DS_FILENAME']) ?>"></ion-icon>
                        </div>
                        <div class="anexo-info">
                            <div class="anexo-nome"><?= htmlspecialchars($anexo['DS_FILENAME']) ?></div>
                            <div class="anexo-meta">
                                <?= formatarTamanho($anexo['VL_TAMANHO_BYTES']) ?> • 
                                <?= $anexo['DT_INCLUSAO'] ? date('d/m/Y H:i', strtotime($anexo['DT_INCLUSAO'])) : '' ?>
                                <?php if ($anexo['DS_USUARIO_UPLOAD']): ?>
                                    • <?= htmlspecialchars($anexo['DS_USUARIO_UPLOAD']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="anexo-acoes">
                            <a href="bd/motorBomba/downloadAnexo.php?id=<?= $anexo['CD_ANEXO'] ?>" class="btn-acao" title="Download">
                                <ion-icon name="download-outline"></ion-icon>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Botões -->
    <div class="form-card">
        <div class="form-actions">
            <a href="motorBomba.php" class="btn btn-secondary">
                <ion-icon name="arrow-back-outline"></ion-icon>
                Voltar
            </a>
            <?php if ($podeEditar): ?>
            <a href="motorBombaForm.php?id=<?= $id ?>" class="btn btn-primary">
                <ion-icon name="create-outline"></ion-icon>
                Editar
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.inc.php'; ?>
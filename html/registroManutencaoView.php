<?php
// registroManutencaoView.php
include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão
$temPermissao = temPermissaoTela('Registro de Manutenção', ACESSO_LEITURA);
$podeEditar = temPermissaoTela('Registro de Manutenção', ACESSO_ESCRITA);

if (!$temPermissao) {
    $_SESSION['msg'] = 'Você não tem permissão para acessar esta funcionalidade.';
    $_SESSION['msg_tipo'] = 'erro';
    header('Location: index.php');
    exit;
}

// Verifica ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['msg'] = 'Registro não informado.';
    $_SESSION['msg_tipo'] = 'erro';
    header('Location: registroManutencao.php');
    exit;
}

// Busca dados do registro
$sql = "SELECT 
            R.*,
            P.CD_CODIGO AS CD_CODIGO_PROG,
            P.CD_ANO AS CD_ANO_PROG,
            P.CD_PONTO_MEDICAO,
            P.ID_TIPO_PROGRAMACAO,
            P.DS_SOLICITANTE,
            P.DT_PROGRAMACAO,
            PM.DS_NOME AS DS_PONTO_MEDICAO,
            PM.ID_TIPO_MEDIDOR,
            L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
            L.DS_NOME AS DS_LOCALIDADE,
            L.CD_UNIDADE,
            U.DS_NOME AS DS_UNIDADE,
            U.CD_CODIGO AS CD_UNIDADE_CODIGO,
            COALESCE(UT.DS_NOME, T.DS_NOME) AS DS_TECNICO,
            UA.DS_NOME AS DS_USUARIO_ATUALIZACAO
        FROM SIMP.dbo.REGISTRO_MANUTENCAO R
        INNER JOIN SIMP.dbo.PROGRAMACAO_MANUTENCAO P ON R.CD_PROGRAMACAO = P.CD_CHAVE
        INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON P.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
        INNER JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
        INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
        LEFT JOIN SIMP.dbo.USUARIO UT ON R.CD_TECNICO = UT.CD_USUARIO
        LEFT JOIN SIMP.dbo.TECNICO T ON R.CD_TECNICO = T.CD_TECNICO
        LEFT JOIN SIMP.dbo.USUARIO UA ON R.CD_USUARIO_ULTIMA_ATUALIZACAO = UA.CD_USUARIO
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

// Mapeamentos
$situacoes = [
    1 => ['nome' => 'Prevista', 'classe' => 'badge-prevista', 'icone' => 'time-outline'],
    2 => ['nome' => 'Executada', 'classe' => 'badge-executada', 'icone' => 'checkmark-circle-outline'],
    4 => ['nome' => 'Cancelada', 'classe' => 'badge-cancelada', 'icone' => 'close-circle-outline']
];

$tiposProgramacao = [
    1 => 'Calibração',
    2 => 'Manutenção'
];

$classificacoes = [
    1 => 'Preventiva',
    2 => 'Corretiva'
];

$tiposCalibracao = [
    4 => 'Resultado de levantamento de constante K',
    8 => 'Resultado de levantamento de desvio'
];

$letrasTipoMedidor = [
    1 => 'M',
    2 => 'E',
    4 => 'P',
    6 => 'R',
    8 => 'H'
];

// Código formatado: CD_CODIGO-CD_ANO/ID_TIPO_PROGRAMACAO
$codigoProgFormatado = str_pad($registro['CD_CODIGO_PROG'], 3, '0', STR_PAD_LEFT) . '-' . $registro['CD_ANO_PROG'] . '/' . $registro['ID_TIPO_PROGRAMACAO'];
$letraTipo = $letrasTipoMedidor[$registro['ID_TIPO_MEDIDOR']] ?? 'X';
$codigoPontoFormatado = $registro['CD_LOCALIDADE_CODIGO'] . '-' . 
                        str_pad($registro['CD_PONTO_MEDICAO'], 6, '0', STR_PAD_LEFT) . '-' . 
                        $letraTipo . '-' . 
                        $registro['CD_UNIDADE'];
$situacao = $situacoes[$registro['ID_SITUACAO']] ?? ['nome' => 'N/A', 'classe' => '', 'icone' => 'help-outline'];
$tipoProgramacao = $tiposProgramacao[$registro['ID_TIPO_PROGRAMACAO']] ?? 'N/A';
$classificacao = $classificacoes[$registro['ID_CLASSIFICACAO_MANUTENCAO']] ?? 'N/A';
$tipoCalibracao = $tiposCalibracao[$registro['ID_TIPO_CALIBRACAO']] ?? 'N/A';
?>

<style>
    /* Reset e Box Sizing */
    *, *::before, *::after {
        box-sizing: border-box;
    }

    .page-container {
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
        overflow-x: hidden;
    }

    .page-header {
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        border-radius: 12px;
        padding: 20px 24px;
        margin-bottom: 20px;
        color: white;
        overflow: hidden;
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
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .btn-header:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    .btn-header.primary {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border: none;
    }

    .view-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 16px;
    }

    .view-card-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 20px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .view-card-header ion-icon {
        font-size: 16px;
        color: #3b82f6;
    }

    .view-card-header h2 {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }

    .view-card-body {
        padding: 20px;
    }

    .view-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }

    .view-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .view-item.full {
        grid-column: 1 / -1;
    }

    .view-label {
        font-size: 10px;
        font-weight: 600;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .view-value {
        font-size: 14px;
        font-weight: 500;
        color: #1e293b;
    }

    .view-value.code {
        font-family: 'SF Mono', Monaco, monospace;
        color: #3b82f6;
        font-weight: 600;
    }

    .view-value.text-block {
        background: #f8fafc;
        padding: 12px 14px;
        border-radius: 8px;
        font-size: 13px;
        color: #475569;
        white-space: pre-wrap;
        line-height: 1.6;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        width: fit-content;
    }

    .badge ion-icon {
        font-size: 12px;
    }

    .badge-prevista {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-executada {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-cancelada {
        background: #fee2e2;
        color: #991b1b;
    }

    .badge-info {
        background: #dbeafe;
        color: #1e40af;
    }

    .view-divider {
        height: 1px;
        background: linear-gradient(to right, transparent, #e2e8f0, transparent);
        margin: 16px 0;
        grid-column: 1 / -1;
    }

    .meta-info {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 11px;
        color: #94a3b8;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid #f1f5f9;
    }

    .meta-info ion-icon {
        font-size: 14px;
    }

    @media (max-width: 992px) {
        .view-grid {
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
            gap: 16px;
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

        .page-header-subtitle {
            justify-content: center;
            flex-wrap: wrap;
        }

        .view-grid {
            grid-template-columns: 1fr;
        }

        .header-actions {
            display: flex;
            flex-direction: column;
            width: 100%;
            gap: 8px;
        }

        .btn-header {
            width: 100%;
            justify-content: center;
            box-sizing: border-box;
            padding: 12px 14px;
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

    .modal-value {
        font-size: 13px;
        color: #1e293b;
        font-weight: 500;
        padding: 10px 12px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .modal-value.highlight {
        background: #eff6ff;
        border-color: #bfdbfe;
        color: #1e40af;
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

    /* Tabela de Leituras (somente visualização) */
    .leituras-table-container {
        overflow-x: auto;
        margin-top: 8px;
    }

    .leituras-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    .leituras-table th {
        background: #f1f5f9;
        color: #475569;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 10px;
        letter-spacing: 0.05em;
        padding: 10px 8px;
        text-align: center;
        border: 1px solid #e2e8f0;
    }

    .leituras-table td {
        padding: 8px 10px;
        border: 1px solid #e2e8f0;
        text-align: center;
        vertical-align: middle;
    }

    .leituras-table .col-leitura {
        width: 60px;
        font-weight: 600;
        color: #475569;
        background: #f8fafc;
    }

    .leituras-table .col-valor {
        font-family: 'Monaco', 'Consolas', monospace;
        font-size: 11px;
        color: #1e293b;
    }

    .leituras-table tbody tr:hover {
        background: #f8fafc;
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
    }
</style>

<div class="page-container">
    <!-- Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="clipboard-outline"></ion-icon>
                </div>
                <div>
                    <h1>Registro de Manutenção</h1>
                    <p class="page-header-subtitle">
                        Programação <?= $codigoProgFormatado ?> | Ocorrência <?= $registro['CD_OCORRENCIA'] ?>
                    </p>
                </div>
            </div>
            <div class="header-actions">
                <button type="button" class="btn-leituras" onclick="abrirModalLeituras()">
                    <ion-icon name="analytics-outline"></ion-icon>
                    Leituras
                </button>
                <a href="registroManutencao.php" class="btn-header">
                    <ion-icon name="arrow-back-outline"></ion-icon>
                    Voltar
                </a>
                <?php if ($podeEditar): ?>
                <a href="registroManutencaoForm.php?id=<?= $id ?>" class="btn-header primary">
                    <ion-icon name="create-outline"></ion-icon>
                    Editar
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Programação -->
    <div class="view-card">
        <div class="view-card-header">
            <ion-icon name="calendar-outline"></ion-icon>
            <h2>Dados da Programação</h2>
        </div>
        <div class="view-card-body">
            <div class="view-grid">
                <div class="view-item">
                    <span class="view-label">Código da Programação</span>
                    <span class="view-value code"><?= $codigoProgFormatado ?></span>
                </div>
                <div class="view-item">
                    <span class="view-label">Tipo de Programação</span>
                    <span class="view-value">
                        <span class="badge badge-info"><?= $tipoProgramacao ?></span>
                    </span>
                </div>
                <div class="view-item">
                    <span class="view-label">Data Programada</span>
                    <span class="view-value"><?= $registro['DT_PROGRAMACAO'] ? date('d/m/Y H:i', strtotime($registro['DT_PROGRAMACAO'])) : '-' ?></span>
                </div>
                <div class="view-item">
                    <span class="view-label">Unidade</span>
                    <span class="view-value"><?= $registro['CD_UNIDADE_CODIGO'] ?> - <?= htmlspecialchars($registro['DS_UNIDADE']) ?></span>
                </div>
                <div class="view-item">
                    <span class="view-label">Localidade</span>
                    <span class="view-value"><?= $registro['CD_LOCALIDADE_CODIGO'] ?> - <?= htmlspecialchars($registro['DS_LOCALIDADE']) ?></span>
                </div>
                <div class="view-item">
                    <span class="view-label">Código do Ponto</span>
                    <span class="view-value code"><?= $codigoPontoFormatado ?></span>
                </div>
                <div class="view-item">
                    <span class="view-label">Ponto de Medição</span>
                    <span class="view-value"><?= htmlspecialchars($registro['DS_PONTO_MEDICAO']) ?></span>
                </div>
                <div class="view-item">
                    <span class="view-label">Solicitante</span>
                    <span class="view-value"><?= htmlspecialchars($registro['DS_SOLICITANTE']) ?: '-' ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Dados do Registro -->
    <div class="view-card">
        <div class="view-card-header">
            <ion-icon name="clipboard-outline"></ion-icon>
            <h2>Dados do Registro</h2>
        </div>
        <div class="view-card-body">
            <div class="view-grid">
                <div class="view-item">
                    <span class="view-label">Ocorrência</span>
                    <span class="view-value code"><?= $registro['CD_OCORRENCIA'] ?></span>
                </div>
                <div class="view-item">
                    <span class="view-label">Situação</span>
                    <span class="view-value">
                        <span class="badge <?= $situacao['classe'] ?>">
                            <ion-icon name="<?= $situacao['icone'] ?>"></ion-icon>
                            <?= $situacao['nome'] ?>
                        </span>
                    </span>
                </div>
                <div class="view-item">
                    <span class="view-label">Técnico</span>
                    <span class="view-value"><?= htmlspecialchars($registro['DS_TECNICO']) ?: '-' ?></span>
                </div>
                <div class="view-item">
                    <span class="view-label">Data de Realização</span>
                    <span class="view-value"><?= date('d/m/Y H:i', strtotime($registro['DT_REALIZADO'])) ?></span>
                </div>
                <div class="view-item">
                    <span class="view-label">Classificação da Manutenção</span>
                    <span class="view-value"><?= $classificacao ?></span>
                </div>
                <div class="view-item">
                    <span class="view-label">Tipo de Calibração</span>
                    <span class="view-value"><?= $tipoCalibracao ?></span>
                </div>

                <?php if (!empty($registro['DS_REALIZADO'])): ?>
                <div class="view-divider"></div>
                <div class="view-item full">
                    <span class="view-label">Parecer Técnico</span>
                    <span class="view-value text-block"><?= nl2br(htmlspecialchars($registro['DS_REALIZADO'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Desvios -->
    <div class="view-card">
        <div class="view-card-header">
            <ion-icon name="options-outline"></ion-icon>
            <h2>Desvios</h2>
        </div>
        <div class="view-card-body">
            <div class="view-grid">
                <div class="view-item full">
                    <span class="view-label">Desvio EP x Macro</span>
                    <span class="view-value text-block"><?= nl2br(htmlspecialchars($registro['DS_CONDICAO_PRIMARIO'])) ?></span>
                </div>
                <div class="view-item full">
                    <span class="view-label">Desvio Macro x CCO</span>
                    <span class="view-value text-block"><?= nl2br(htmlspecialchars($registro['DS_CONDICAO_SECUNDARIO'])) ?></span>
                </div>
                <div class="view-item full">
                    <span class="view-label">Desvio Total</span>
                    <span class="view-value text-block"><?= nl2br(htmlspecialchars($registro['DS_CONDICAO_TERCIARIO'])) ?></span>
                </div>

                <?php if (!empty($registro['DS_OBSERVACAO'])): ?>
                <div class="view-divider"></div>
                <div class="view-item full">
                    <span class="view-label">Protocolo de Comunicação</span>
                    <span class="view-value text-block"><?= nl2br(htmlspecialchars($registro['DS_OBSERVACAO'])) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($registro['DT_ULTIMA_ATUALIZACAO']): ?>
            <div class="meta-info">
                <ion-icon name="time-outline"></ion-icon>
                Última atualização: <?= date('d/m/Y H:i', strtotime($registro['DT_ULTIMA_ATUALIZACAO'])) ?>
                <?php if ($registro['DS_USUARIO_ATUALIZACAO']): ?>
                    por <?= htmlspecialchars($registro['DS_USUARIO_ATUALIZACAO']) ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

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
                Fechar
            </button>
        </div>
    </div>
</div>

<script>
const cdRegistroManutencao = <?= $id ?>;

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
                    renderizarCalibracao(data.calibracao, data.leituras || []);
                } else {
                    body.innerHTML = `
                        <div class="modal-empty">
                            <ion-icon name="document-outline"></ion-icon>
                            <p>Nenhuma calibração de deprimogênio cadastrada para este registro.</p>
                        </div>
                    `;
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

function renderizarCalibracao(calibracao, leituras) {
    const body = document.getElementById('modalLeiturasBody');
    
    const formatarNumero = (valor) => {
        if (valor === null || valor === undefined || valor === '') return '-';
        return parseFloat(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 10 });
    };

    // Gera linhas de leituras
    let leiturasRows = '';
    if (leituras && leituras.length > 0) {
        leituras.forEach(leitura => {
            leiturasRows += `
                <tr>
                    <td class="col-leitura">${String(leitura.CD_PONTO_LEITURA).padStart(2, '0')}:</td>
                    <td class="col-valor">${formatarNumero(leitura.VL_VAZAO_EP)}</td>
                    <td class="col-valor">${formatarNumero(leitura.VL_PERCENTUAL_INCERTEZA_EP)}</td>
                    <td class="col-valor">${formatarNumero(leitura.VL_K_DEPRIMOGENIO)}</td>
                </tr>
            `;
        });
    } else {
        leiturasRows = `
            <tr>
                <td colspan="4" style="color: #64748b; font-style: italic; padding: 20px;">
                    Nenhuma leitura cadastrada
                </td>
            </tr>
        `;
    }

    body.innerHTML = `
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
                            <th>Vazão da EP (m³/s)</th>
                            <th>Incerteza da EP (m³/s) / (%)</th>
                            <th>Constante K do Deprimogênio m³/[Seg.√(mH2O)]</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${leiturasRows}
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Seção de Dados da Calibração -->
        <div class="modal-section">
            <div class="modal-section-title">
                <ion-icon name="calculator-outline"></ion-icon>
                Dados da Calibração
            </div>
            <div class="modal-grid">
                <div class="modal-field">
                    <span class="modal-label">Constante K Média (l/seg)/√(pol H2O)</span>
                    <span class="modal-value highlight">${formatarNumero(calibracao.VL_K_MEDIO)}</span>
                </div>
                <div class="modal-field">
                    <span class="modal-label">Constante K Anterior (l/seg)/√(pol H2O)</span>
                    <span class="modal-value">${formatarNumero(calibracao.VL_K_ANTERIOR)}</span>
                </div>
                <div class="modal-field">
                    <span class="modal-label">Desvio (%)</span>
                    <span class="modal-value">${formatarNumero(calibracao.VL_DESVIO)}</span>
                </div>
                <div class="modal-field">
                    <span class="modal-label">Percentual de Acréscimo (%)</span>
                    <span class="modal-value">${formatarNumero(calibracao.VL_PERCENTUAL_ACRESCIMO)}</span>
                </div>
                <div class="modal-field">
                    <span class="modal-label">Vazão Máxima de Teste (l/s)</span>
                    <span class="modal-value">${formatarNumero(calibracao.VL_VAZAO_MAXIMA)}</span>
                </div>
                <div class="modal-field">
                    <span class="modal-label">Dif. Pressão Máximo de Teste (mm H2O)</span>
                    <span class="modal-value">${formatarNumero(calibracao.VL_PRESSAO_MAXIMA)}</span>
                </div>
                <div class="modal-field full-width">
                    <div class="modal-checkbox">
                        <input type="checkbox" id="viewOpAtualizaK" ${calibracao.OP_ATUALIZA_K == 1 ? 'checked' : ''} disabled>
                        <label class="modal-checkbox-label" for="viewOpAtualizaK">Atualiza a Constante K do Ponto de Medição</label>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function fecharModalLeituras() {
    document.getElementById('modalLeituras').classList.remove('active');
}

// Fechar modal ao clicar fora
document.getElementById('modalLeituras').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalLeituras();
    }
});

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModalLeituras();
    }
});
</script>

<?php include_once 'includes/footer.inc.php'; ?>
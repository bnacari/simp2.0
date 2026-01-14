<?php
//programacaoManutencaoView.php
include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão para visualizar Programação de Manutenção
exigePermissaoTela('Programação de Manutenção', ACESSO_LEITURA);

// Permissões do usuário
$podeEditar = podeEditarTela('Programação de Manutenção');

// Tipos de Programação
$tiposProgramacao = [
    1 => 'Calibração',
    2 => 'Manutenção'
];

// Situações com cores
$situacoes = [
    1 => ['nome' => 'Prevista', 'classe' => 'prevista', 'icone' => 'time-outline'],
    2 => ['nome' => 'Executada', 'classe' => 'executada', 'icone' => 'checkmark-circle-outline'],
    4 => ['nome' => 'Cancelada', 'classe' => 'cancelada', 'icone' => 'close-circle-outline']
];

// Verifica se foi passado o ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['msg'] = 'Programação não informada.';
    $_SESSION['msg_tipo'] = 'erro';
    header('Location: programacaoManutencao.php');
    exit;
}

// Busca os dados da programação
$sql = "SELECT 
            P.*,
            PM.DS_NOME AS DS_PONTO_MEDICAO,
            PM.CD_LOCALIDADE,
            PM.ID_TIPO_MEDIDOR,
            L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
            L.DS_NOME AS DS_LOCALIDADE,
            L.CD_UNIDADE,
            U.DS_NOME AS DS_UNIDADE,
            U.CD_CODIGO AS CD_UNIDADE_CODIGO,
            UR.DS_NOME AS DS_RESPONSAVEL,
            UR.DS_MATRICULA AS DS_MATRICULA_RESPONSAVEL,
            UA.DS_NOME AS DS_USUARIO_ATUALIZACAO
        FROM SIMP.dbo.PROGRAMACAO_MANUTENCAO P
        INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON P.CD_PONTO_MEDICAO = PM.CD_PONTO_MEDICAO
        INNER JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
        INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
        LEFT JOIN SIMP.dbo.USUARIO UR ON P.CD_USUARIO_RESPONSAVEL = UR.CD_USUARIO
        LEFT JOIN SIMP.dbo.USUARIO UA ON P.CD_USUARIO_ULTIMA_ATUALIZACAO = UA.CD_USUARIO
        WHERE P.CD_CHAVE = :id";

$stmt = $pdoSIMP->prepare($sql);
$stmt->execute([':id' => $id]);
$prog = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prog) {
    $_SESSION['msg'] = 'Programação não encontrada.';
    $_SESSION['msg_tipo'] = 'erro';
    header('Location: programacaoManutencao.php');
    exit;
}

// Código formatado: CD_CODIGO-CD_ANO/ID_TIPO_PROGRAMACAO
$codigoFormatado = str_pad($prog['CD_CODIGO'], 3, '0', STR_PAD_LEFT) . '-' . $prog['CD_ANO'] . '/' . $prog['ID_TIPO_PROGRAMACAO'];

// Situação atual
$situacaoAtual = $situacoes[$prog['ID_SITUACAO']] ?? ['nome' => 'Desconhecida', 'classe' => '', 'icone' => 'help-outline'];

// Tipo de programação
$tipoProgramacao = $tiposProgramacao[$prog['ID_TIPO_PROGRAMACAO']] ?? 'Não informado';

// Mapeamento de tipo de medidor para letra
$letrasTipoMedidor = [
    1 => 'M',
    2 => 'E',
    4 => 'P',
    6 => 'R',
    8 => 'H'
];
$letraTipo = $letrasTipoMedidor[$prog['ID_TIPO_MEDIDOR']] ?? 'X';

// Código do ponto de medição formatado
$codigoPontoMedicao = $prog['CD_LOCALIDADE_CODIGO'] . '-' . str_pad($prog['CD_PONTO_MEDICAO'], 6, '0', STR_PAD_LEFT) . '-' . $letraTipo . '-' . $prog['CD_UNIDADE'];
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
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .page-header-subtitle .codigo {
        background: rgba(255, 255, 255, 0.2);
        padding: 3px 8px;
        border-radius: 4px;
        font-family: 'SF Mono', Monaco, monospace;
        font-weight: 600;
        font-size: 11px;
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
        background: rgba(255, 255, 255, 0.95);
        color: #1e3a5f;
        border-color: transparent;
    }

    .btn-header.primary:hover {
        background: white;
    }

    .btn-header ion-icon {
        font-size: 14px;
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
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 16px;
    }

    .view-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .view-item.full-width {
        grid-column: 1 / -1;
    }

    .view-label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 10px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .view-label ion-icon {
        font-size: 12px;
        color: #94a3b8;
    }

    .view-value {
        font-size: 12px;
        color: #1e293b;
        font-weight: 500;
        padding: 8px 12px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        min-height: 36px;
        display: flex;
        align-items: center;
    }

    .view-value.empty {
        color: #94a3b8;
        font-style: italic;
        font-weight: 400;
    }

    .view-value.codigo {
        font-family: 'SF Mono', Monaco, monospace;
        background: #eff6ff;
        border-color: #bfdbfe;
        color: #1d4ed8;
    }

    .view-value.multiline {
        white-space: pre-wrap;
        align-items: flex-start;
        min-height: 60px;
    }

    .view-value a {
        color: #3b82f6;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .view-value a:hover {
        text-decoration: underline;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }

    .status-badge.prevista {
        background: #fef3c7;
        color: #b45309;
    }

    .status-badge.executada {
        background: #dcfce7;
        color: #15803d;
    }

    .status-badge.cancelada {
        background: #fee2e2;
        color: #b91c1c;
    }

    .status-badge ion-icon {
        font-size: 14px;
    }

    .tipo-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
    }

    .tipo-badge.calibracao {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .tipo-badge.manutencao {
        background: #f3e8ff;
        color: #7c3aed;
    }

    .tipo-badge ion-icon {
        font-size: 14px;
    }

    .timeline-container {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .timeline-item {
        flex: 1;
        min-width: 200px;
        padding: 16px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        text-align: center;
    }

    .timeline-item-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        color: white;
        font-size: 18px;
    }

    .timeline-item-label {
        font-size: 10px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 4px;
    }

    .timeline-item-value {
        font-size: 14px;
        font-weight: 600;
        color: #1e293b;
    }

    .view-divider {
        height: 1px;
        background: linear-gradient(to right, transparent, #e2e8f0, transparent);
        margin: 16px 0;
        grid-column: 1 / -1;
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

        .view-grid {
            grid-template-columns: 1fr;
        }

        .timeline-container {
            flex-direction: column;
        }

        .timeline-item {
            min-width: 100%;
        }
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
                    <h1>Programação de <?= $tipoProgramacao ?></h1>
                    <p class="page-header-subtitle">
                        <span class="codigo"><?= $codigoFormatado ?></span>
                        <span class="status-badge <?= $situacaoAtual['classe'] ?>">
                            <ion-icon name="<?= $situacaoAtual['icone'] ?>"></ion-icon>
                            <?= $situacaoAtual['nome'] ?>
                        </span>
                    </p>
                </div>
            </div>
            <div class="header-actions">
                <a href="programacaoManutencao.php" class="btn-header">
                    <ion-icon name="arrow-back-outline"></ion-icon>
                    Voltar
                </a>
                <?php if ($podeEditar): ?>
                    <a href="programacaoManutencaoForm.php?id=<?= $id ?>" class="btn-header primary">
                        <ion-icon name="create-outline"></ion-icon>
                        Editar
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Timeline de Datas -->
    <div class="view-card">
        <div class="view-card-body" style="padding: 16px 20px;">
            <div class="timeline-container">
                <div class="timeline-item">
                    <div class="timeline-item-icon">
                        <ion-icon name="document-text-outline"></ion-icon>
                    </div>
                    <div class="timeline-item-label">Data da Solicitação</div>
                    <div class="timeline-item-value">
                        <?= date('d/m/Y H:i', strtotime($prog['DT_SOLICITACAO'])) ?>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-item-icon">
                        <ion-icon name="calendar-outline"></ion-icon>
                    </div>
                    <div class="timeline-item-label">Data da Programação</div>
                    <div class="timeline-item-value">
                        <?= date('d/m/Y H:i', strtotime($prog['DT_PROGRAMACAO'])) ?>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-item-icon" style="background: <?= $situacaoAtual['classe'] == 'executada' ? 'linear-gradient(135deg, #22c55e 0%, #16a34a 100%)' : ($situacaoAtual['classe'] == 'cancelada' ? 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)' : 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)') ?>;">
                        <ion-icon name="<?= $situacaoAtual['icone'] ?>"></ion-icon>
                    </div>
                    <div class="timeline-item-label">Situação Atual</div>
                    <div class="timeline-item-value">
                        <?= $situacaoAtual['nome'] ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ponto de Medição -->
    <div class="view-card">
        <div class="view-card-header">
            <ion-icon name="speedometer-outline"></ion-icon>
            <h2>Ponto de Medição</h2>
        </div>
        <div class="view-card-body">
            <div class="view-grid">
                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="business-outline"></ion-icon>
                        Unidade
                    </span>
                    <div class="view-value">
                        <?= $prog['CD_UNIDADE_CODIGO'] . ' - ' . htmlspecialchars($prog['DS_UNIDADE']) ?>
                    </div>
                </div>

                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="location-outline"></ion-icon>
                        Localidade
                    </span>
                    <div class="view-value">
                        <?= $prog['CD_LOCALIDADE_CODIGO'] . ' - ' . htmlspecialchars($prog['DS_LOCALIDADE']) ?>
                    </div>
                </div>

                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="barcode-outline"></ion-icon>
                        Código do Ponto
                    </span>
                    <div class="view-value codigo">
                        <?= $codigoPontoMedicao ?>
                    </div>
                </div>

                <div class="view-item full-width">
                    <span class="view-label">
                        <ion-icon name="text-outline"></ion-icon>
                        Nome do Ponto de Medição
                    </span>
                    <div class="view-value">
                        <a href="pontoMedicaoView.php?id=<?= $prog['CD_PONTO_MEDICAO'] ?>">
                            <?= htmlspecialchars($prog['DS_PONTO_MEDICAO']) ?>
                            <ion-icon name="open-outline"></ion-icon>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dados da Programação -->
    <div class="view-card">
        <div class="view-card-header">
            <ion-icon name="calendar-outline"></ion-icon>
            <h2>Dados da Programação</h2>
        </div>
        <div class="view-card-body">
            <div class="view-grid">
                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="barcode-outline"></ion-icon>
                        Código
                    </span>
                    <div class="view-value codigo">
                        <?= $codigoFormatado ?>
                    </div>
                </div>

                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="construct-outline"></ion-icon>
                        Tipo de Programação
                    </span>
                    <div class="view-value">
                        <span class="tipo-badge <?= $prog['ID_TIPO_PROGRAMACAO'] == 1 ? 'calibracao' : 'manutencao' ?>">
                            <ion-icon name="<?= $prog['ID_TIPO_PROGRAMACAO'] == 1 ? 'analytics-outline' : 'build-outline' ?>"></ion-icon>
                            <?= $tipoProgramacao ?>
                        </span>
                    </div>
                </div>

                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="flag-outline"></ion-icon>
                        Situação
                    </span>
                    <div class="view-value">
                        <span class="status-badge <?= $situacaoAtual['classe'] ?>">
                            <ion-icon name="<?= $situacaoAtual['icone'] ?>"></ion-icon>
                            <?= $situacaoAtual['nome'] ?>
                        </span>
                    </div>
                </div>

                <div class="view-divider"></div>

                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="calendar-outline"></ion-icon>
                        Data da Programação
                    </span>
                    <div class="view-value">
                        <?= date('d/m/Y H:i', strtotime($prog['DT_PROGRAMACAO'])) ?>
                    </div>
                </div>

                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="person-outline"></ion-icon>
                        Responsável
                    </span>
                    <div class="view-value">
                        <?= $prog['DS_MATRICULA_RESPONSAVEL'] . ' - ' . htmlspecialchars($prog['DS_RESPONSAVEL']) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dados da Solicitação -->
    <div class="view-card">
        <div class="view-card-header">
            <ion-icon name="document-text-outline"></ion-icon>
            <h2>Dados da Solicitação</h2>
        </div>
        <div class="view-card-body">
            <div class="view-grid">
                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="person-outline"></ion-icon>
                        Solicitante
                    </span>
                    <div class="view-value">
                        <?= htmlspecialchars($prog['DS_SOLICITANTE']) ?>
                    </div>
                </div>

                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="calendar-outline"></ion-icon>
                        Data da Solicitação
                    </span>
                    <div class="view-value">
                        <?= date('d/m/Y H:i', strtotime($prog['DT_SOLICITACAO'])) ?>
                    </div>
                </div>

                <div class="view-item full-width">
                    <span class="view-label">
                        <ion-icon name="chatbox-outline"></ion-icon>
                        Descrição da Solicitação
                    </span>
                    <div class="view-value multiline <?= empty($prog['DS_SOLICITACAO']) ? 'empty' : '' ?>">
                        <?= !empty($prog['DS_SOLICITACAO']) ? htmlspecialchars($prog['DS_SOLICITACAO']) : 'Nenhuma descrição informada' ?>
                    </div>
                </div>

                <?php if (!empty($prog['DT_ULTIMA_ATUALIZACAO'])): ?>
                    <div class="view-divider"></div>
                    <div class="view-item full-width">
                        <small style="color: #94a3b8; font-size: 11px;">
                            <ion-icon name="time-outline" style="vertical-align: middle;"></ion-icon>
                            Última atualização: <?= date('d/m/Y H:i', strtotime($prog['DT_ULTIMA_ATUALIZACAO'])) ?>
                            <?php if (!empty($prog['DS_USUARIO_ATUALIZACAO'])): ?>
                                por <?= htmlspecialchars($prog['DS_USUARIO_ATUALIZACAO']) ?>
                            <?php endif; ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.inc.php'; ?>
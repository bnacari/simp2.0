<?php
include_once 'includes/header.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão para visualizar Ponto de Medição (busca por nome na tabela FUNCIONALIDADE)
// Recarregar permissões do banco (garante que estão atualizadas)
recarregarPermissoesUsuario();

// Agora verificar permissão
exigePermissaoTela('Cadastro de Ponto de Medição', ACESSO_LEITURA);

// Permissões do usuário
$podeEditar = podeEditarTela('Cadastro de Ponto de Medição');

include_once 'includes/menu.inc.php';

// Mapeamentos
$tiposInstalacao = [
    1 => 'Exposto',
    2 => 'Poço de Visita',
    3 => 'Inacessível'
];

$tiposMedidor = [
    1 => 'M - Macromedidor',
    2 => 'E - Estação Pitométrica',
    4 => 'P - Medidor Pressão',
    6 => 'R - Nível Reservatório',
    8 => 'H - Hidrômetro'
];

$tiposLeitura = [
    2 => 'Manual',
    4 => 'Planilha',
    6 => 'Integração CesanLims',
    8 => 'Integração CCO'
];

$periodicidades = [
    2 => 'Minuto',
    3 => 'Hora',
    4 => 'Diário'
];

// Buscar Tipos de Reservatório
$sqlTiposReservatorio = $pdoSIMP->query("SELECT CD_CHAVE, NOME FROM SIMP.dbo.TIPO_RESERVATORIO ORDER BY NOME");
$tiposReservatorioArray = $sqlTiposReservatorio->fetchAll(PDO::FETCH_ASSOC);
$tiposReservatorio = [];
foreach ($tiposReservatorioArray as $tr) {
    $tiposReservatorio[$tr['CD_CHAVE']] = $tr['NOME'];
}

// Tipos de Fluido (ID_PRODUTO)
$tiposFluido = [
    '' => 'Indiferente',
    '1' => 'Água Tratada',
    '3' => 'Água Bruta',
    '2' => 'Esgoto'
];

// Verifica se foi passado o ID
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['msg'] = 'Ponto de medição não informado.';
    $_SESSION['msg_tipo'] = 'erro';
    header('Location: pontoMedicao.php');
    exit;
}

// Busca os dados do ponto de medição
$sql = "SELECT 
            PM.*,
            L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
            L.DS_NOME AS DS_LOCALIDADE,
            L.CD_UNIDADE,
            U.DS_NOME AS DS_UNIDADE,
            U.CD_CODIGO AS CD_UNIDADE_CODIGO,
            UR.DS_NOME AS DS_RESPONSAVEL,
            UR.DS_MATRICULA AS DS_MATRICULA_RESPONSAVEL,
            UA.DS_NOME AS DS_USUARIO_ATUALIZACAO
        FROM SIMP.dbo.PONTO_MEDICAO PM
        INNER JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
        INNER JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
        LEFT JOIN SIMP.dbo.USUARIO UR ON PM.CD_USUARIO_RESPONSAVEL = UR.CD_USUARIO
        LEFT JOIN SIMP.dbo.USUARIO UA ON PM.CD_USUARIO_ULTIMA_ATUALIZACAO = UA.CD_USUARIO
        WHERE PM.CD_PONTO_MEDICAO = :id";

$stmt = $pdoSIMP->prepare($sql);
$stmt->execute([':id' => $id]);
$pm = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pm) {
    $_SESSION['msg'] = 'Ponto de medição não encontrado.';
    $_SESSION['msg_tipo'] = 'erro';
    header('Location: pontoMedicao.php');
    exit;
}

// Mapeamento de tipos
$tiposMedidor = [
    '1' => 'M - Macromedidor',
    '2' => 'E - Estação Pitométrica',
    '4' => 'P - Medidor Pressão',
    '8' => 'H - Hidrômetro',
    '6' => 'R - Nível Reservatório',
];

$tiposLeitura = [
    '2' => 'Manual',
    '4' => 'Planilha',
    '8' => 'Integração CCO',
    '6' => 'Integração CesanLims',
];

$periodicidades = [
    '' => 'Indiferente',
    '2' => 'Minuto - 2',
    '3' => 'Hora - 3',
    '4' => 'Diário - 4',
];

// Mapeamento de tipo de medidor para letra
$letrasTipoMedidor = [
    1 => 'M', // Macromedidor
    2 => 'E', // Estação Pitométrica
    4 => 'P', // Medidor Pressão
    6 => 'R', // Nível Reservatório
    8 => 'H'  // Hidrômetro
];
$letraTipo = $letrasTipoMedidor[$pm['ID_TIPO_MEDIDOR']] ?? 'X';

// Código formatado: LOCALIDADE-ID_PONTO-LETRA-CD_UNIDADE
$codigoFormatado = $pm['CD_LOCALIDADE_CODIGO'] . '-' . str_pad($pm['CD_PONTO_MEDICAO'], 6, '0', STR_PAD_LEFT) . '-' . $letraTipo . '-' . $pm['CD_UNIDADE'];
$isAtivo = empty($pm['DT_DESATIVACAO']);

// Trim nos campos de texto
$dsLocalizacao = isset($pm['DS_LOCALIZACAO']) ? trim($pm['DS_LOCALIZACAO']) : '';
$dsObservacao = isset($pm['DS_OBSERVACAO']) ? trim($pm['DS_OBSERVACAO']) : '';
?>

<style>
    /* ============================================
       Reset e Box Sizing
       ============================================ */
    *,
    *::before,
    *::after {
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

    /* ============================================
       View Card
       ============================================ */
    .view-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
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

    /* ============================================
       View Grid
       ============================================ */
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

    /* ============================================
       View Value with Action Button
       ============================================ */
    .view-value-with-action {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: #1e293b;
        font-weight: 500;
        padding: 8px 12px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        min-height: 36px;
    }

    .view-value-with-action.empty {
        color: #94a3b8;
        font-style: italic;
        font-weight: 400;
    }

    .view-value-with-action .value-text {
        flex: 1;
    }

    .btn-map {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .btn-map:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }

    .btn-map ion-icon {
        font-size: 16px;
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
       Type Badge
       ============================================ */
    .type-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
    }

    .type-badge.macromedidor {
        background: #dcfce7;
        color: #15803d;
    }

    .type-badge.estacao {
        background: #fef3c7;
        color: #b45309;
    }

    .type-badge.pressao {
        background: #fee2e2;
        color: #b91c1c;
    }

    .type-badge.hidrometro {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .type-badge.reservatorio {
        background: #cffafe;
        color: #0891b2;
    }

    /* ============================================
       Info Grid (for compact display)
       ============================================ */
    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }

    @media (max-width: 768px) {
        .info-grid {
            grid-template-columns: 1fr;
        }
    }

    /* ============================================
       Divider
       ============================================ */
    .view-divider {
        height: 1px;
        background: linear-gradient(to right, transparent, #e2e8f0, transparent);
        margin: 16px 0;
        grid-column: 1 / -1;
    }

    /* ============================================
       Responsive
       ============================================ */
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

        .view-card-body {
            padding: 16px;
        }

        .view-grid {
            grid-template-columns: 1fr;
        }
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
        background: rgba(255, 255, 255, 0.1);
    }

    .tabs-header::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
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
       Meta Mensal - Filtro e Tabela
       ============================================ */
    .meta-filtro {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .meta-filtro .form-label {
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        margin: 0;
    }

    .meta-filtro .form-control {
        padding: 8px 12px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 12px;
        color: #334155;
    }

    .meta-filtro .form-control:focus {
        outline: none;
        background-color: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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

    .mes-nome {
        font-weight: 500;
    }

    /* Paginação */
    .pagination-container-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 16px;
        margin-top: 16px;
        border-top: 1px solid #f1f5f9;
    }

    .page-info-meta {
        font-size: 11px;
        color: #64748b;
    }

    .page-buttons-meta {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .btn-page-meta {
        min-width: 32px;
        height: 32px;
        padding: 0 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
        color: #475569;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-page-meta:hover:not(.disabled):not(.active) {
        background: #e2e8f0;
        border-color: #cbd5e1;
    }

    .btn-page-meta.active {
        background: #3b82f6;
        border-color: #3b82f6;
        color: white;
    }

    .btn-page-meta.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .btn-page-meta ion-icon {
        font-size: 14px;
    }

    .page-ellipsis-meta {
        padding: 0 4px;
        color: #94a3b8;
        font-size: 12px;
    }
</style>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="eye-outline"></ion-icon>
                </div>
                <div>
                    <h1><?= htmlspecialchars($pm['DS_NOME']) ?></h1>
                    <p class="page-header-subtitle">
                        <span class="codigo"><?= $codigoFormatado ?></span>
                        <span class="status-badge <?= $isAtivo ? 'ativo' : 'inativo' ?>">
                            <ion-icon name="<?= $isAtivo ? 'checkmark-circle' : 'close-circle' ?>"></ion-icon>
                            <?= $isAtivo ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </p>
                </div>
            </div>
            <div class="header-actions">
                <a href="operacoes.php?ponto=<?= $id ?>&mes=<?= date('n') ?>&ano=<?= date('Y') ?>" class="btn-header"
                    title="Ver dados de medição deste ponto">
                    <ion-icon name="stats-chart-outline"></ion-icon>
                    Operações
                </a>
                <a href="pontoMedicao.php" class="btn-header">
                    <ion-icon name="arrow-back-outline"></ion-icon>
                    Voltar
                </a>
                <?php if ($podeEditar): ?>
                    <a href="pontoMedicaoForm.php?id=<?= $id ?>" class="btn-header primary">
                        <ion-icon name="create-outline"></ion-icon>
                        Editar
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Identificação -->
    <div class="view-card">
        <div class="view-card-header">
            <ion-icon name="information-circle-outline"></ion-icon>
            <h2>Identificação</h2>
        </div>
        <div class="view-card-body">
            <div class="view-grid">
                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="business-outline"></ion-icon>
                        Unidade
                    </span>
                    <div class="view-value">
                        <?= $pm['CD_UNIDADE_CODIGO'] . ' - ' . htmlspecialchars($pm['DS_UNIDADE']) ?>
                    </div>
                </div>

                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="location-outline"></ion-icon>
                        Localidade
                    </span>
                    <div class="view-value">
                        <?= $pm['CD_LOCALIDADE_CODIGO'] . ' - ' . htmlspecialchars($pm['DS_LOCALIDADE']) ?>
                    </div>
                </div>

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
                        <ion-icon name="speedometer-outline"></ion-icon>
                        Tipo de Medidor
                    </span>
                    <div class="view-value">
                        <?php
                        $tipoMedidor = $pm['ID_TIPO_MEDIDOR'];
                        $classeTipo = '';
                        switch ($tipoMedidor) {
                            case 1:
                                $classeTipo = 'macromedidor';
                                break;
                            case 2:
                                $classeTipo = 'estacao';
                                break;
                            case 4:
                                $classeTipo = 'pressao';
                                break;
                            case 8:
                                $classeTipo = 'hidrometro';
                                break;
                            case 6:
                                $classeTipo = 'reservatorio';
                                break;
                        }
                        ?>
                        <span class="type-badge <?= $classeTipo ?>">
                            <?= $tiposMedidor[$tipoMedidor] ?? 'Não informado' ?>
                        </span>
                    </div>
                </div>

                <div class="view-item full-width">
                    <span class="view-label">
                        <ion-icon name="text-outline"></ion-icon>
                        Nome
                    </span>
                    <div class="view-value">
                        <?= htmlspecialchars($pm['DS_NOME']) ?>
                    </div>
                </div>

                <div class="view-item full-width">
                    <span class="view-label">
                        <ion-icon name="map-outline"></ion-icon>
                        Localização
                    </span>
                    <div class="view-value multiline <?= empty($dsLocalizacao) ? 'empty' : '' ?>">
                        <?= !empty($dsLocalizacao) ? htmlspecialchars($dsLocalizacao) : 'Não informado' ?>
                    </div>
                </div>

                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="navigate-outline"></ion-icon>
                        Coordenadas
                    </span>
                    <div class="view-value-with-action <?= empty($pm['COORDENADAS']) ? 'empty' : '' ?>">
                        <span
                            class="value-text"><?= !empty($pm['COORDENADAS']) ? htmlspecialchars($pm['COORDENADAS']) : 'Não informado' ?></span>
                        <?php if (!empty($pm['COORDENADAS'])): ?>
                            <button type="button" class="btn-map"
                                onclick="abrirMapa('<?= htmlspecialchars($pm['COORDENADAS']) ?>')"
                                title="Abrir no Google Maps">
                                <ion-icon name="map-outline"></ion-icon>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuração de Leitura -->
    <div class="view-card">
        <div class="view-card-header">
            <ion-icon name="settings-outline"></ion-icon>
            <h2>Configuração de Leitura</h2>
        </div>
        <div class="view-card-body">
            <div class="view-grid">
                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="reader-outline"></ion-icon>
                        Tipo de Leitura
                    </span>
                    <div class="view-value">
                        <?= $tiposLeitura[$pm['ID_TIPO_LEITURA']] ?? 'Não informado' ?>
                    </div>
                </div>

                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="time-outline"></ion-icon>
                        Periodicidade de Leitura
                    </span>
                    <div class="view-value <?= empty($pm['OP_PERIODICIDADE_LEITURA']) ? 'empty' : '' ?>">
                        <?= isset($periodicidades[$pm['OP_PERIODICIDADE_LEITURA']]) ? $periodicidades[$pm['OP_PERIODICIDADE_LEITURA']] : 'Não informado' ?>
                    </div>
                </div>

                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="person-outline"></ion-icon>
                        Responsável
                    </span>
                    <div class="view-value <?= empty($pm['DS_RESPONSAVEL']) ? 'empty' : '' ?>">
                        <?= !empty($pm['DS_RESPONSAVEL']) ? $pm['DS_MATRICULA_RESPONSAVEL'] . ' - ' . htmlspecialchars($pm['DS_RESPONSAVEL']) : 'Não informado' ?>
                    </div>
                </div>

                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="construct-outline"></ion-icon>
                        Tipo de Instalação
                    </span>
                    <div class="view-value <?= empty($pm['TIPO_INSTALACAO']) ? 'empty' : '' ?>">
                        <?= !empty($pm['TIPO_INSTALACAO']) ? ($tiposInstalacao[$pm['TIPO_INSTALACAO']] ?? $pm['TIPO_INSTALACAO']) : 'Não informado' ?>
                    </div>
                </div>

                <div class="view-divider"></div>

                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="calendar-outline"></ion-icon>
                        Data de Ativação
                    </span>
                    <div class="view-value <?= empty($pm['DT_ATIVACAO']) ? 'empty' : '' ?>">
                        <?= !empty($pm['DT_ATIVACAO']) ? date('d/m/Y H:i', strtotime($pm['DT_ATIVACAO'])) : 'Não informado' ?>
                    </div>
                </div>

                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="calendar-outline"></ion-icon>
                        Data de Desativação
                    </span>
                    <div class="view-value <?= empty($pm['DT_DESATIVACAO']) ? 'empty' : '' ?>">
                        <?= !empty($pm['DT_DESATIVACAO']) ? date('d/m/Y H:i', strtotime($pm['DT_DESATIVACAO'])) : 'Não informado' ?>
                    </div>
                </div>

                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="git-branch-outline"></ion-icon>
                        Quantidade de Ligações
                    </span>
                    <div class="view-value <?= empty($pm['VL_QUANTIDADE_LIGACOES']) ? 'empty' : '' ?>">
                        <?= !empty($pm['VL_QUANTIDADE_LIGACOES']) ? number_format($pm['VL_QUANTIDADE_LIGACOES'], 0, ',', '.') : 'Não informado' ?>
                    </div>
                </div>

                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="home-outline"></ion-icon>
                        Quantidade de Economias
                    </span>
                    <div class="view-value <?= empty($pm['VL_QUANTIDADE_ECONOMIAS']) ? 'empty' : '' ?>">
                        <?= !empty($pm['VL_QUANTIDADE_ECONOMIAS']) ? number_format($pm['VL_QUANTIDADE_ECONOMIAS'], 0, ',', '.') : 'Não informado' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tags de Integração -->
    <div class="view-card">
        <div class="view-card-header">
            <ion-icon name="pricetag-outline"></ion-icon>
            <h2>Tags de Integração</h2>
        </div>
        <div class="view-card-body">
            <div class="view-grid">
                <?php
                // Determinar qual TAG principal exibir baseado no Tipo de Medidor
                // 1,2,8 → Vazão | 4 → Pressão | 6 → Reservatório
                $tipoMedidor = (int) ($pm['ID_TIPO_MEDIDOR'] ?? 0);
                $tagPrincipalLabel = null;
                $tagPrincipalValor = null;
                $tagPrincipalIcon = null;

                switch ($tipoMedidor) {
                    case 1: // Macromedidor
                    case 2: // Estação Pitométrica
                    case 8: // Hidrômetro
                        $tagPrincipalLabel = 'Tag Vazão';
                        $tagPrincipalValor = $pm['DS_TAG_VAZAO'] ?? null;
                        $tagPrincipalIcon = 'water-outline';
                        break;
                    case 4: // Medidor Pressão
                        $tagPrincipalLabel = 'Tag Pressão';
                        $tagPrincipalValor = $pm['DS_TAG_PRESSAO'] ?? null;
                        $tagPrincipalIcon = 'speedometer-outline';
                        break;
                    case 6: // Nível Reservatório
                        $tagPrincipalLabel = 'Tag Nível Reservatório';
                        $tagPrincipalValor = $pm['DS_TAG_RESERVATORIO'] ?? null;
                        $tagPrincipalIcon = 'analytics-outline';
                        break;
                }
                ?>

                <?php if ($tagPrincipalLabel): ?>
                    <div class="view-item">
                        <span class="view-label">
                            <ion-icon name="<?= $tagPrincipalIcon ?>"></ion-icon>
                            <?= $tagPrincipalLabel ?>
                        </span>
                        <div class="view-value <?= empty($tagPrincipalValor) ? 'empty' : 'codigo' ?>">
                            <?= !empty($tagPrincipalValor) ? htmlspecialchars($tagPrincipalValor) : 'Não informado' ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($pm['DS_TAG_TEMP_AGUA'])): ?>
                    <div class="view-item">
                        <span class="view-label">
                            <ion-icon name="thermometer-outline"></ion-icon>
                            Tag Temperatura da Água
                        </span>
                        <div class="view-value codigo">
                            <?= htmlspecialchars($pm['DS_TAG_TEMP_AGUA']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($pm['DS_TAG_TEMP_AMBIENTE'])): ?>
                    <div class="view-item">
                        <span class="view-label">
                            <ion-icon name="sunny-outline"></ion-icon>
                            Tag Temperatura do Ambiente
                        </span>
                        <div class="view-value codigo">
                            <?= htmlspecialchars($pm['DS_TAG_TEMP_AMBIENTE']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="business-outline"></ion-icon>
                        Local Instalação SAP
                    </span>
                    <div class="view-value <?= empty($pm['LOC_INST_SAP']) ? 'empty' : '' ?>">
                        <?= !empty($pm['LOC_INST_SAP']) ? htmlspecialchars($pm['LOC_INST_SAP']) : 'Não informado' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Parâmetros de Medição -->
    <div class="view-card">
        <div class="view-card-header">
            <ion-icon name="options-outline"></ion-icon>
            <h2>Parâmetros de Medição</h2>
        </div>
        <div class="view-card-body">
            <div class="view-grid">
                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="calculator-outline"></ion-icon>
                        Fator Correção Vazão
                    </span>
                    <div class="view-value <?= empty($pm['VL_FATOR_CORRECAO_VAZAO']) ? 'empty' : '' ?>">
                        <?= !empty($pm['VL_FATOR_CORRECAO_VAZAO']) ? number_format($pm['VL_FATOR_CORRECAO_VAZAO'], 4, ',', '.') : 'Não informado' ?>
                    </div>
                </div>

                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="trending-down-outline"></ion-icon>
                        Limite Inferior Vazão (l/s) ou Pressão (mca)
                    </span>
                    <div class="view-value <?= empty($pm['VL_LIMITE_INFERIOR_VAZAO']) ? 'empty' : '' ?>">
                        <?= isset($pm['VL_LIMITE_INFERIOR_VAZAO']) && $pm['VL_LIMITE_INFERIOR_VAZAO'] !== null ? number_format($pm['VL_LIMITE_INFERIOR_VAZAO'], 2, ',', '.') : 'Não informado' ?>
                    </div>
                </div>

                <div class="view-item">
                    <span class="view-label">
                        <ion-icon name="trending-up-outline"></ion-icon>
                        Limite Superior Vazão (l/s) ou Pressão (mca)
                    </span>
                    <div class="view-value <?= empty($pm['VL_LIMITE_SUPERIOR_VAZAO']) ? 'empty' : '' ?>">
                        <?= isset($pm['VL_LIMITE_SUPERIOR_VAZAO']) && $pm['VL_LIMITE_SUPERIOR_VAZAO'] !== null ? number_format($pm['VL_LIMITE_SUPERIOR_VAZAO'], 2, ',', '.') : 'Não informado' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Observações -->
    <div class="view-card">
        <div class="view-card-header">
            <ion-icon name="document-text-outline"></ion-icon>
            <h2>Observações</h2>
        </div>
        <div class="view-card-body">
            <div class="view-grid">
                <div class="view-item full-width">
                    <span class="view-label">
                        <ion-icon name="chatbox-outline"></ion-icon>
                        Observações Gerais
                    </span>
                    <div class="view-value multiline <?= empty($dsObservacao) ? 'empty' : '' ?>">
                        <?= !empty($dsObservacao) ? htmlspecialchars($dsObservacao) : 'Nenhuma observação registrada' ?>
                    </div>
                </div>

                <?php if (!empty($pm['DT_ULTIMA_ATUALIZACAO'])): ?>
                    <div class="view-divider"></div>
                    <div class="view-item full-width">
                        <small style="color: #94a3b8; font-size: 12px;">
                            <ion-icon name="time-outline" style="vertical-align: middle;"></ion-icon>
                            Última atualização: <?= date('d/m/Y H:i', strtotime($pm['DT_ULTIMA_ATUALIZACAO'])) ?>
                            <?php if (!empty($pm['DS_USUARIO_ATUALIZACAO'])): ?>
                                por <?= htmlspecialchars($pm['DS_USUARIO_ATUALIZACAO']) ?>
                            <?php endif; ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Container de Abas: Equipamento e Meta Mensal -->
    <div class="tabs-container" id="tabsContainerView">
        <div class="tabs-header">
            <button type="button" class="tab-btn active" data-tab="equipamento">
                <ion-icon name="hardware-chip-outline"></ion-icon>
                <span class="tab-text">Dados do Equipamento</span>
            </button>
            <button type="button" class="tab-btn" data-tab="metas">
                <ion-icon name="flag-outline"></ion-icon>
                <span class="tab-text">Meta Mensal</span>
                <span class="tab-badge" id="badgeMetasView">0</span>
            </button>
        </div>
        <div class="tabs-content">
            <!-- Aba: Dados do Equipamento -->
            <div class="tab-pane active" id="paneEquipamento">
                <div id="conteudoEquipamentoView" class="view-grid">
                    <div class="view-item full-width">
                        <div class="empty-state-mini">
                            <ion-icon name="hourglass-outline"></ion-icon>
                            Carregando dados do equipamento...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Aba: Meta Mensal -->
            <div class="tab-pane" id="paneMetas">
                <!-- Filtro por ano -->
                <div class="meta-filtro" style="margin-bottom: 16px;">
                    <label class="form-label" style="margin-bottom: 0; display: flex; align-items: center; gap: 4px;">
                        <ion-icon name="calendar-outline"></ion-icon>
                        Filtrar Ano:
                    </label>
                    <select id="filtroAnoMetaView" class="form-control" style="width: 120px;"
                        onchange="renderizarTabelaMetas()">
                        <option value="">Todos</option>
                        <?php for ($ano = date('Y') + 1; $ano >= date('Y') - 5; $ano--): ?>
                            <option value="<?= $ano ?>" <?= $ano == date('Y') ? 'selected' : '' ?>><?= $ano ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="table-container-meta">
                    <table class="data-table-meta">
                        <thead id="theadMetasView">
                            <tr>
                                <th style="width: 80px;">Ano</th>
                                <th style="width: 100px;">Mês</th>
                                <?php if ($pm['ID_TIPO_MEDIDOR'] == 1 || $pm['ID_TIPO_MEDIDOR'] == 2 || $pm['ID_TIPO_MEDIDOR'] == 8): ?>
                                    <th>Meta L/S</th>
                                <?php elseif ($pm['ID_TIPO_MEDIDOR'] == 4): ?>
                                    <th>Pressão Alta (mca)</th>
                                    <th>Pressão Baixa (mca)</th>
                                <?php elseif ($pm['ID_TIPO_MEDIDOR'] == 6): ?>
                                    <th>Nível Extrav. %</th>
                                    <th>Nível Alto</th>
                                    <th>Nível Baixo</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="tabelaMetas">
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state-mini">
                                        <ion-icon name="hourglass-outline"></ion-icon>
                                        Carregando metas...
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginação -->
                <div id="paginacaoMetas" class="pagination-container-meta"></div>
            </div>
        </div>
    </div>
</div>

<!-- Script para Dados do Equipamento -->
<script>
    const tipoMedidorEquip = <?= $pm['ID_TIPO_MEDIDOR'] ?>;
    const cdPontoMedicaoEquip = <?= $id ?>;

    // Função para abrir mapa com coordenadas
    function abrirMapa(coordenadas) {
        if (!coordenadas) {
            showToast('Coordenadas não informadas', 'erro');
            return;
        }

        // Limpa espaços e formata coordenadas
        let coords = coordenadas.trim();

        // Remove espaços extras e padroniza separadores
        coords = coords.replace(/\s+/g, '').replace(/;/g, ',');

        // Extrai latitude e longitude
        const partes = coords.split(',');
        if (partes.length < 2) {
            showToast('Formato de coordenadas inválido', 'erro');
            return;
        }

        const lat = parseFloat(partes[0]);
        const lng = parseFloat(partes[1]);

        if (isNaN(lat) || isNaN(lng)) {
            showToast('Coordenadas inválidas', 'erro');
            return;
        }

        // Abre Google Maps em nova aba
        const url = `https://www.google.com/maps?q=${lat},${lng}&z=17`;
        window.open(url, '_blank');
    }

    const tiposMedidorNomes = {
        1: 'Macromedidor',
        2: 'Estação Pitométrica',
        4: 'Medidor de Pressão',
        6: 'Nível Reservatório',
        8: 'Hidrômetro'
    };

    // Mapeamento de tipos de reservatório
    const tiposReservatorioNomes = <?= json_encode($tiposReservatorio) ?>;

    // Mapeamento de tipos de fluido
    const tiposFluidoNomes = <?= json_encode($tiposFluido) ?>;

    // Mapeamento de tipos de medidor de equipamento (tabela TIPO_MEDIDOR)
    let tiposMedidorEquipNomes = {};

    let metasData = [];

    $(document).ready(function () {
        // Carrega tipos de medidor de equipamento primeiro, depois carrega o resto
        $.ajax({
            url: 'bd/pontoMedicao/getTiposMedidorEquipamento.php',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    response.data.forEach(t => {
                        tiposMedidorEquipNomes[t.CD_CHAVE] = t.DS_NOME;
                    });
                }
                // Agora carrega os dados do equipamento
                carregarEquipamento();
            },
            error: function () {
                // Mesmo com erro, tenta carregar equipamento
                carregarEquipamento();
            }
        });

        carregarMetas();

        // Handler para navegação entre abas
        $('.tab-btn').on('click', function () {
            const tabId = $(this).data('tab');

            // Atualiza botões
            $('.tab-btn').removeClass('active');
            $(this).addClass('active');

            // Atualiza painéis
            $('.tab-pane').removeClass('active');
            if (tabId === 'equipamento') {
                $('#paneEquipamento').addClass('active');
            } else if (tabId === 'metas') {
                $('#paneMetas').addClass('active');
            }
        });
    });

    function carregarMetas() {
        $.ajax({
            url: 'bd/pontoMedicao/getMetasMensais.php',
            type: 'GET',
            data: { cd_ponto_medicao: cdPontoMedicaoEquip },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    metasData = response.data;
                    renderizarTabelaMetas();
                    $('#badgeMetasView').text(metasData.length);
                }
            }
        });
    }

    function renderizarTabelaMetas() {
        const tbody = $('#tabelaMetas');
        const filtroAno = $('#filtroAnoMetaView').val();

        // Filtra por ano se selecionado
        let dadosFiltrados = metasData;
        if (filtroAno) {
            dadosFiltrados = metasData.filter(m => m.ANO_META == filtroAno);
        }

        // Atualiza badge com total (não filtrado)
        $('#badgeMetasView').text(metasData.length);

        if (dadosFiltrados.length === 0) {
            tbody.html(`
                <tr>
                    <td colspan="5">
                        <div class="empty-state-mini">
                            <ion-icon name="alert-circle-outline"></ion-icon>
                            ${metasData.length === 0 ? 'Nenhuma meta cadastrada' : 'Nenhuma meta encontrada para o ano selecionado'}
                        </div>
                    </td>
                </tr>
            `);
            return;
        }

        const meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
            'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

        let html = '';
        dadosFiltrados.forEach(meta => {
            html += '<tr>';
            html += `<td>${meta.ANO_META}</td>`;
            html += `<td>${meses[meta.MES_META] || 'Ano Inteiro'}</td>`;

            if (tipoMedidorEquip == 4) {
                html += `<td>${formatarNumero(meta.VL_META_PRESSAO_ALTA)}</td>`;
                html += `<td>${formatarNumero(meta.VL_META_PRESSAO_BAIXA)}</td>`;
            } else if (tipoMedidorEquip == 6) {
                html += `<td>${formatarNumero(meta.VL_META_NIVEL_RESERVATORIO)}</td>`;
                html += `<td>${formatarNumero(meta.VL_META_RESERVATORIO_ALTA)}</td>`;
                html += `<td>${formatarNumero(meta.VL_META_RESERVATORIO_BAIXA)}</td>`;
            } else {
                html += `<td>${formatarNumero(meta.VL_META_L_S)}</td>`;
            }

            html += '</tr>';
        });

        tbody.html(html);
    }

    function formatarNumero(value) {
        if (value === null || value === undefined || value === '') return '-';
        return parseFloat(value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function carregarEquipamento() {
        $.ajax({
            url: 'bd/pontoMedicao/getDadosMedidor.php',
            type: 'GET',
            data: {
                cd_ponto_medicao: cdPontoMedicaoEquip,
                id_tipo_medidor: tipoMedidorEquip
            },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    renderizarEquipamento(response.data, tipoMedidorEquip);
                } else {
                    $('#conteudoEquipamentoView').html(`
                        <div class="view-item full-width">
                            <div class="empty-state-mini">
                                <ion-icon name="alert-circle-outline"></ion-icon>
                                Nenhum dado de equipamento cadastrado
                            </div>
                        </div>
                    `);
                }
            },
            error: function () {
                $('#conteudoEquipamentoView').html(`
                    <div class="view-item full-width">
                        <div class="empty-state-mini">
                            <ion-icon name="alert-circle-outline"></ion-icon>
                            Erro ao carregar dados do equipamento
                        </div>
                    </div>
                `);
            }
        });
    }

    function renderizarEquipamento(dados, tipo) {
        let html = '';

        switch (tipo) {
            case 1: // Macromedidor
                html = renderMacromedidor(dados);
                break;
            case 2: // Estação Pitométrica
                html = renderEstacaoPitometrica(dados);
                break;
            case 4: // Medidor Pressão
                html = renderMedidorPressao(dados);
                break;
            case 6: // Nível Reservatório
                html = renderNivelReservatorio(dados);
                break;
            case 8: // Hidrômetro
                html = renderHidrometro(dados);
                break;
            default:
                html = '<div class="view-item full-width"><div class="empty-state-mini">Tipo de equipamento não suportado</div></div>';
        }

        $('#conteudoEquipamentoView').html(html);
    }

    function viewItem(label, value, icon, isCode = false) {
        const isEmpty = !value || value === '' || value === null;
        const displayValue = isEmpty ? 'Não informado' : value;
        const classes = isEmpty ? 'empty' : (isCode ? 'codigo' : '');

        return `
            <div class="view-item">
                <span class="view-label">
                    <ion-icon name="${icon}"></ion-icon>
                    ${label}
                </span>
                <div class="view-value ${classes}">${displayValue}</div>
            </div>
        `;
    }

    function formatDate(dateStr) {
        if (!dateStr) return null;
        const date = new Date(dateStr);
        return date.toLocaleDateString('pt-BR');
    }

    function formatNum(value, decimals = 2) {
        if (value === null || value === undefined || value === '') return null;
        return parseFloat(value).toLocaleString('pt-BR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    }

    function renderMacromedidor(d) {
        return `
            ${viewItem('Tipo de Medidor', tiposMedidorEquipNomes[d.CD_TIPO_MEDIDOR] || '-', 'settings-outline')}
            ${viewItem('Marca', d.DS_MARCA, 'pricetag-outline')}
            ${viewItem('Modelo', d.DS_MODELO, 'cube-outline')}
            ${viewItem('Série', d.DS_SERIE, 'barcode-outline', true)}
            ${viewItem('Tag', d.DS_TAG, 'pricetag-outline', true)}
            ${viewItem('Data Fabricação', formatDate(d.DT_FABRICACAO), 'calendar-outline')}
            ${viewItem('Patrimônio Primário', d.DS_PATRIMONIO_PRIMARIO, 'document-outline')}
            ${viewItem('Patrimônio Secundário', d.DS_PATRIMONIO_SECUNDARIO, 'document-outline')}
            ${viewItem('Diâmetro (mm)', formatNum(d.VL_DIAMETRO), 'resize-outline')}
            ${viewItem('Diâmetro Rede (mm)', formatNum(d.VL_DIAMETRO_REDE), 'git-branch-outline')}
            ${viewItem('Revestimento', d.DS_REVESTIMENTO, 'layers-outline')}
            ${viewItem('Perda Carga Fabricante', formatNum(d.VL_PERDA_CARGA_FABRICANTE, 4), 'trending-down-outline')}
            ${viewItem('Capacidade Nominal', formatNum(d.VL_CAPACIDADE_NOMINAL), 'speedometer-outline')}
            ${viewItem('K Fabricante', formatNum(d.VL_K_FABRICANTE, 4), 'calculator-outline')}
            ${viewItem('Vazão Esperada', formatNum(d.VL_VAZAO_ESPERADA), 'water-outline')}
            ${viewItem('Pressão Máxima', formatNum(d.VL_PRESSAO_MAXIMA), 'arrow-up-outline')}
            ${viewItem('Tipo Flange', d.DS_TIPO_FLANGE, 'ellipse-outline')}
            ${viewItem('Altura Soleira', d.DS_ALTURA_SOLEIRA, 'resize-outline')}
            ${viewItem('Natureza Parede', d.DS_NATUREZA_PAREDE, 'square-outline')}
            ${viewItem('Largura Relativa', d.DS_LARGURA_RELATIVA, 'resize-outline')}
            ${viewItem('Largura Garganta', d.DS_LARGURA_GARGANTA, 'resize-outline')}
            ${viewItem('Cota', formatNum(d.VL_COTA), 'analytics-outline')}
            ${viewItem('Protocolo de Comunicação', d.PROT_COMUN, 'radio-outline')}
        `;
    }

    function renderEstacaoPitometrica(d) {
        return `
            ${viewItem('Cota Geográfica', formatNum(d.VL_COTA_GEOGRAFICA), 'location-outline')}
            ${viewItem('Diâmetro (mm)', formatNum(d.VL_DIAMETRO), 'resize-outline')}
            ${viewItem('Diâmetro Rede (mm)', formatNum(d.VL_DIAMETRO_REDE), 'git-branch-outline')}
            ${viewItem('Linha', d.DS_LINHA, 'git-commit-outline')}
            ${viewItem('Sistema', d.DS_SISTEMA, 'apps-outline')}
            ${viewItem('Revestimento', d.DS_REVESTIMENTO, 'layers-outline')}
            ${viewItem('Periodicidade Levantamento', d.TP_PERIODICIDADE_LEVANTAMENTO, 'time-outline')}
        `;
    }

    function renderMedidorPressao(d) {
        return `
            ${viewItem('Matrícula Usuário', d.DS_MATRICULA_USUARIO, 'person-outline')}
            ${viewItem('Nº Série Equipamento', d.DS_NUMERO_SERIE_EQUIPAMENTO, 'barcode-outline', true)}
            ${viewItem('Diâmetro (mm)', formatNum(d.VL_DIAMETRO), 'resize-outline')}
            ${viewItem('Diâmetro Rede (mm)', formatNum(d.VL_DIAMETRO_REDE), 'git-branch-outline')}
            ${viewItem('Material', d.DS_MATERIAL, 'construct-outline')}
            ${viewItem('Cota', d.DS_COTA, 'analytics-outline')}
            ${viewItem('Telemetria', d.OP_TELEMETRIA == 1 ? 'Sim' : (d.OP_TELEMETRIA == 0 ? 'Não' : null), 'radio-outline')}
            ${viewItem('Endereço', d.DS_ENDERECO, 'location-outline')}
            ${viewItem('Data Instalação', formatDate(d.DT_INSTALACAO), 'calendar-outline')}
            ${viewItem('Coordenadas', d.DS_COORDENADAS, 'navigate-outline', true)}
        `;
    }

    function renderNivelReservatorio(d) {
        // Traduz tipo de reservatório e tipo de fluido
        const tipoReservatorioNome = d.CD_TIPO_RESERVATORIO ? (tiposReservatorioNomes[d.CD_TIPO_RESERVATORIO] || '-') : null;
        const tipoFluidoNome = d.ID_PRODUTO !== null && d.ID_PRODUTO !== undefined ? (tiposFluidoNomes[d.ID_PRODUTO] || tiposFluidoNomes[String(d.ID_PRODUTO)] || 'Indiferente') : null;

        return `
            ${viewItem('Tipo de Medidor', tiposMedidorEquipNomes[d.CD_TIPO_MEDIDOR] || '-', 'settings-outline')}
            ${viewItem('Tipo de Reservatório', tipoReservatorioNome, 'server-outline')}
            ${viewItem('Marca', d.DS_MARCA, 'pricetag-outline')}
            ${viewItem('Modelo', d.DS_MODELO, 'cube-outline')}
            ${viewItem('Série', d.DS_SERIE, 'barcode-outline', true)}
            ${viewItem('Tag', d.DS_TAG, 'pricetag-outline', true)}
            ${viewItem('Data Fabricação', formatDate(d.DT_FABRICACAO), 'calendar-outline')}
            ${viewItem('Data Instalação', formatDate(d.DT_INSTALACAO), 'calendar-outline')}
            ${viewItem('Patrimônio Primário', d.DS_PATRIMONIO_PRIMARIO, 'document-outline')}
            ${viewItem('Patrimônio Secundário', d.DS_PATRIMONIO_SECUNDARIO, 'document-outline')}
            ${viewItem('Altura Máxima (m)', d.DS_ALTURA_MAXIMA, 'resize-outline')}
            ${viewItem('NA', formatNum(d.VL_NA), 'water-outline')}
            ${viewItem('Cota', formatNum(d.VL_COTA), 'analytics-outline')}
            ${viewItem('Cota Extravasamento (m)', formatNum(d.COTA_EXTRAVASAMENTO_M), 'arrow-up-outline')}
            ${viewItem('Cota Extravasamento (%)', formatNum(d.COTA_EXTRAVASAMENTO_P), 'arrow-up-outline')}
            ${viewItem('Volume Total (m³)', formatNum(d.VL_VOLUME_TOTAL), 'cube-outline')}
            ${viewItem('Volume Câmara A (m³)', formatNum(d.VL_VOLUME_CAMARA_A), 'cube-outline')}
            ${viewItem('Volume Câmara B (m³)', formatNum(d.VL_VOLUME_CAMARA_B), 'cube-outline')}
            ${viewItem('Pressão Máx. Sucção', formatNum(d.VL_PRESSAO_MAXIMA_SUCCAO), 'arrow-down-outline')}
            ${viewItem('Pressão Máx. Recalque', formatNum(d.VL_PRESSAO_MAXIMA_RECALQUE), 'arrow-up-outline')}
            ${viewItem('Tipo de Fluido', tipoFluidoNome, 'water-outline')}
        `;
    }

    function renderHidrometro(d) {
        return `
            ${viewItem('Matrícula Usuário', d.DS_MATRICULA_USUARIO, 'person-outline')}
            ${viewItem('Nº Série Equipamento', d.DS_NUMERO_SERIE_EQUIPAMENTO, 'barcode-outline', true)}
            ${viewItem('Diâmetro (mm)', formatNum(d.VL_DIAMETRO), 'resize-outline')}
            ${viewItem('Diâmetro Rede (mm)', formatNum(d.VL_DIAMETRO_REDE), 'git-branch-outline')}
            ${viewItem('Material', d.DS_MATERIAL, 'construct-outline')}
            ${viewItem('Cota', d.DS_COTA, 'analytics-outline')}
            ${viewItem('Endereço', d.DS_ENDERECO, 'location-outline')}
            ${viewItem('Data Instalação', formatDate(d.DT_INSTALACAO), 'calendar-outline')}
            ${viewItem('Coordenadas', d.DS_COORDENADAS, 'navigate-outline', true)}
            ${viewItem('Leitura Limite', formatNum(d.VL_LEITURA_LIMITE), 'speedometer-outline')}
            ${viewItem('Multiplicador', formatNum(d.VL_MULTIPLICADOR, 4), 'calculator-outline')}
        `;
    }
</script>

<?php include_once 'includes/footer.inc.php'; ?>
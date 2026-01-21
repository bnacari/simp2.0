<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Visualização de Cálculo de KPC
 * 
 * Exibe os detalhes completos de um cálculo de KPC com gráfico da curva de velocidade.
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão para visualizar Cálculo de KPC
$temPermissao = temPermissaoTela('Cálculo do KPC', ACESSO_LEITURA);
$podeEditar = temPermissaoTela('Cálculo do KPC', ACESSO_ESCRITA);

if (!$temPermissao) {
    $_SESSION['msg'] = 'Você não tem permissão para acessar esta funcionalidade.';
    header('Location: home.php');
    exit;
}

// Verifica se foi passado o ID
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['msg'] = 'Cálculo de KPC não informado.';
    $_SESSION['msg_tipo'] = 'erro';
    header('Location: calculoKPC.php');
    exit;
}

// Buscar dados do cálculo
$sql = "SELECT 
            CK.*,
            PM.DS_NOME AS DS_PONTO_MEDICAO,
            PM.CD_PONTO_MEDICAO,
            L.CD_CHAVE AS CD_CHAVE_LOCALIDADE,
            L.CD_LOCALIDADE,
            L.DS_NOME AS DS_LOCALIDADE,
            U.CD_UNIDADE,
            U.DS_NOME AS DS_UNIDADE,
            TR.DS_NOME AS DS_TECNICO_RESPONSAVEL,
            TR.DS_MATRICULA AS DS_MATRICULA_TECNICO,
            UR.DS_NOME AS DS_USUARIO_RESPONSAVEL,
            UR.DS_MATRICULA AS DS_MATRICULA_USUARIO,
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

// Organiza as leituras em array bidimensional
$leiturasOrganizadas = [];
foreach ($leituras as $l) {
    $leiturasOrganizadas[$l['CD_POSICAO_LEITURA']][$l['CD_ORDEM_LEITURA']] = [
        'deflexao' => $l['VL_DEFLEXAO_MEDIDA'],
        'posicao' => $l['VL_POSICAO']
    ];
}

// Situações
$situacoes = [
    1 => ['nome' => 'Ativo', 'classe' => 'badge-ativo'],
    2 => ['nome' => 'Cancelado', 'classe' => 'badge-cancelado']
];

// Métodos
$metodos = [
    1 => 'Digital',
    2 => 'Convencional'
];

// Organiza as leituras em array bidimensional [leitura][ponto]
$leiturasOrganizadas = [];
$ultimaPosicaoValor = 0;
foreach ($leituras as $l) {
    // CD_ORDEM_LEITURA = número da leitura (1-20 ou 21 para média)
    // CD_POSICAO_LEITURA = número do ponto (1-11)
    $leiturasOrganizadas[$l['CD_ORDEM_LEITURA']][$l['CD_POSICAO_LEITURA']] = [
        'deflexao' => $l['VL_DEFLEXAO_MEDIDA'],
        'posicao' => $l['VL_POSICAO']
    ];
    // Pegar última posição (ponto 11)
    if ($l['CD_POSICAO_LEITURA'] == 11 && !empty($l['VL_POSICAO'])) {
        $ultimaPosicaoValor = $l['VL_POSICAO'];
    }
}

// Calcular posições baseadas no Diâmetro Real
// Posição 1 = 0
// Posições 2-10 = (Diâmetro Real / 10) * (posição - 1)
// Posição 11 = Última Posição
$diametroReal = $calculoKPC['VL_DIAMETRO_REAL'];
$posicoesCalculadas = [];
for ($p = 1; $p <= 11; $p++) {
    if ($p == 1) {
        $posicoesCalculadas[$p] = 0;
    } elseif ($p == 11) {
        $posicoesCalculadas[$p] = $ultimaPosicaoValor;
    } else {
        $posicoesCalculadas[$p] = ($diametroReal / 10) * ($p - 1);
    }
}

// Código formatado
$codigoFormatado = $calculoKPC['CD_CODIGO'] . '-' . $calculoKPC['CD_ANO'];
$isAtivo = $calculoKPC['ID_SITUACAO'] == 1;
?>

<style>
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

    .btn-header.warning {
        background: #f59e0b;
        border-color: #f59e0b;
    }

    .btn-header.warning:hover {
        background: #d97706;
    }

    /* ============================================
       Info Cards
       ============================================ */
    .info-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 16px;
    }

    .info-card-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 20px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .info-card-header ion-icon {
        font-size: 16px;
        color: #3b82f6;
    }

    .info-card-header h2 {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }

    .info-card-body {
        padding: 20px;
    }

    /* ============================================
       Info Grid
       ============================================ */
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px;
    }

    .info-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .info-item.destaque {
        background: #ecfdf5;
        padding: 12px;
        border-radius: 8px;
        border: 1px solid #10b981;
    }

    .info-label {
        font-size: 10px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .info-label ion-icon {
        font-size: 12px;
        color: #94a3b8;
    }

    .info-value {
        font-size: 14px;
        color: #1e293b;
        font-weight: 500;
    }

    .info-value.mono {
        font-family: monospace;
    }

    .info-value.grande {
        font-size: 18px;
        font-weight: 700;
        color: #047857;
    }

    /* ============================================
       Badges
       ============================================ */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }

    .badge-ativo {
        background: #dcfce7;
        color: #166534;
    }

    .badge-cancelado {
        background: #fee2e2;
        color: #991b1b;
    }

    .badge-metodo {
        background: #e0e7ff;
        color: #3730a3;
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
        padding: 4px 8px;
        border: 1px solid #d1d5db;
        text-align: center;
    }

    .leituras-table th {
        background: #dbeafe;
        font-weight: 600;
        color: #1e40af;
        font-size: 11px;
    }

    .leituras-table th.posicao {
        background: #dbeafe;
        color: #1e40af;
        text-align: right;
        padding-right: 12px;
    }

    .leituras-table th.posicao-valor {
        background: #eff6ff;
        color: #3b82f6;
        font-weight: 500;
        font-size: 10px;
    }

    .leituras-table td.posicao-label {
        background: #f1f5f9;
        font-weight: 600;
        color: #475569;
        text-align: right;
        padding-right: 12px;
        min-width: 100px;
    }

    .leituras-table td.media-row {
        background: #fef3c7;
        font-weight: 600;
        color: #92400e;
    }

    /* ============================================
       Gráfico
       ============================================ */
    .grafico-container {
        height: 400px;
        position: relative;
    }

    /* ============================================
       Responsivo
       ============================================ */
    @media (max-width: 768px) {
        .page-container {
            padding: 12px;
        }

        .info-grid {
            grid-template-columns: 1fr 1fr;
        }

        .header-actions {
            flex-direction: column;
            width: 100%;
        }

        .btn-header {
            justify-content: center;
        }
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
                    <h1>Detalhes do Cálculo de KPC</h1>
                    <p class="page-header-subtitle">
                        Código: <span class="codigo"><?= $codigoFormatado ?></span>
                        <span class="badge <?= $situacoes[$calculoKPC['ID_SITUACAO']]['classe'] ?>">
                            <?= $situacoes[$calculoKPC['ID_SITUACAO']]['nome'] ?>
                        </span>
                    </p>
                </div>
            </div>
            <div class="header-actions">
                <?php if ($podeEditar && $isAtivo): ?>
                    <a href="calculoKPCForm.php?id=<?= $id ?>" class="btn-header warning">
                        <ion-icon name="create-outline"></ion-icon>
                        Editar
                    </a>
                <?php endif; ?>
                <a href="calculoKPC.php" class="btn-header">
                    <ion-icon name="arrow-back-outline"></ion-icon>
                    Voltar
                </a>
            </div>
        </div>
    </div>

    <!-- Dados do Ponto de Medição -->
    <div class="info-card">
        <div class="info-card-header">
            <ion-icon name="location-outline"></ion-icon>
            <h2>Ponto de Medição</h2>
        </div>
        <div class="info-card-body">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label"><ion-icon name="business-outline"></ion-icon> Unidade</span>
                    <span class="info-value"><?= htmlspecialchars($calculoKPC['DS_UNIDADE']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><ion-icon name="map-outline"></ion-icon> Localidade</span>
                    <span class="info-value"><?= $calculoKPC['CD_LOCALIDADE'] ?> -
                        <?= htmlspecialchars($calculoKPC['DS_LOCALIDADE']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><ion-icon name="speedometer-outline"></ion-icon> Ponto de Medição</span>
                    <span class="info-value"><?= htmlspecialchars($calculoKPC['DS_PONTO_MEDICAO']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><ion-icon name="calendar-outline"></ion-icon> Data da Leitura</span>
                    <span class="info-value"><?= date('d/m/Y', strtotime($calculoKPC['DT_LEITURA'])) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><ion-icon name="git-branch-outline"></ion-icon> Método</span>
                    <span class="info-value">
                        <span class="badge badge-metodo"><?= $metodos[$calculoKPC['ID_METODO']] ?? 'N/A' ?></span>
                    </span>
                </div>

            </div>
        </div>
    </div>

    <!-- Parâmetros do Cálculo -->
    <div class="info-card">
        <div class="info-card-header">
            <ion-icon name="options-outline"></ion-icon>
            <h2>Parâmetros do Cálculo</h2>
        </div>
        <div class="info-card-body">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label"><ion-icon name="resize-outline"></ion-icon> Diâmetro Nominal</span>
                    <span class="info-value mono"><?= number_format($calculoKPC['VL_DIAMETRO_NOMINAL'], 2, ',', '.') ?>
                        mm</span>
                </div>
                <div class="info-item">
                    <span class="info-label"><ion-icon name="resize-outline"></ion-icon> Diâmetro Real</span>
                    <span class="info-value mono"><?= number_format($calculoKPC['VL_DIAMETRO_REAL'], 2, ',', '.') ?>
                        mm</span>
                </div>
                <div class="info-item">
                    <span class="info-label"><ion-icon name="locate-outline"></ion-icon> Última Posição</span>
                    <span class="info-value mono">
                        <?= number_format($ultimaPosicaoValor, 2, ',', '.') ?> mm
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label"><ion-icon name="analytics-outline"></ion-icon> Projeção da TAP</span>
                    <span class="info-value mono"><?= number_format($calculoKPC['VL_PROJECAO_TAP'], 2, ',', '.') ?>
                        mm</span>
                </div>
                <div class="info-item">
                    <span class="info-label"><ion-icon name="ellipse-outline"></ion-icon> Raio do TIP</span>
                    <span class="info-value mono"><?= number_format($calculoKPC['VL_RAIO_TIP'], 2, ',', '.') ?>
                        mm</span>
                </div>
                <div class="info-item">
                    <span class="info-label"><ion-icon name="thermometer-outline"></ion-icon> Temperatura</span>
                    <span class="info-value mono"><?= number_format($calculoKPC['VL_TEMPERATURA'], 1, ',', '.') ?>
                        °C</span>
                </div>

            </div>
        </div>
    </div>

    <!-- Resultados do Cálculo -->
    <div class="info-card">
        <div class="info-card-header">
            <ion-icon name="analytics-outline"></ion-icon>
            <h2>Resultados do Cálculo</h2>
        </div>
        <div class="info-card-body">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label"><ion-icon name="pulse-outline"></ion-icon> Fator de Velocidade</span>
                    <span
                        class="info-value mono"><?= number_format($calculoKPC['VL_FATOR_VELOCIDADE'], 10, '.', '') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><ion-icon name="expand-outline"></ion-icon> Correção Diâmetro</span>
                    <span
                        class="info-value mono"><?= number_format($calculoKPC['VL_CORRECAO_DIAMETRO'], 6, '.', '') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><ion-icon name="git-merge-outline"></ion-icon> Correção Projeção TAP</span>
                    <span
                        class="info-value mono"><?= number_format($calculoKPC['VL_CORRECAO_PROJECAO_TAP'], 2, '.', '') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><ion-icon name="scan-outline"></ion-icon> Área Efetiva</span>
                    <span class="info-value mono"><?= number_format($calculoKPC['VL_AREA_EFETIVA'], 6, '.', '') ?>
                        m²</span>
                </div>
                <div class="info-item destaque">
                    <span class="info-label"><ion-icon name="star-outline"></ion-icon> KPC</span>
                    <span class="info-value grande"><?= number_format($calculoKPC['VL_KPC'], 10, '.', '') ?></span>
                </div>
                <div class="info-item destaque">
                    <span class="info-label"><ion-icon name="water-outline"></ion-icon> Vazão</span>
                    <span
                        class="info-value grande"><?= $calculoKPC['VL_VAZAO'] ? number_format($calculoKPC['VL_VAZAO'], 2, ',', '.') . ' L/s' : 'N/A' ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Responsáveis -->
    <div class="info-card">
        <div class="info-card-header">
            <ion-icon name="people-outline"></ion-icon>
            <h2>Responsáveis</h2>
        </div>
        <div class="info-card-body">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label"><ion-icon name="construct-outline"></ion-icon> Técnico Responsável</span>
                    <span class="info-value">
                        <?= htmlspecialchars($calculoKPC['DS_TECNICO_RESPONSAVEL']) ?>
                        <?= $calculoKPC['DS_MATRICULA_TECNICO'] ? '(' . $calculoKPC['DS_MATRICULA_TECNICO'] . ')' : '' ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label"><ion-icon name="person-outline"></ion-icon> Usuário Responsável</span>
                    <span class="info-value">
                        <?= htmlspecialchars($calculoKPC['DS_USUARIO_RESPONSAVEL']) ?>
                        <?= $calculoKPC['DS_MATRICULA_USUARIO'] ? '(' . $calculoKPC['DS_MATRICULA_USUARIO'] . ')' : '' ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label"><ion-icon name="time-outline"></ion-icon> Última Atualização</span>
                    <span class="info-value">
                        <?= date('d/m/Y H:i', strtotime($calculoKPC['DT_ULTIMA_ATUALIZACAO'])) ?>
                        <?= $calculoKPC['DS_USUARIO_ATUALIZACAO'] ? '- ' . htmlspecialchars($calculoKPC['DS_USUARIO_ATUALIZACAO']) : '' ?>
                    </span>
                </div>
                <?php if ($calculoKPC['DS_OBSERVACAO']): ?>
                    <div class="info-item" style="grid-column: span 2;">
                        <span class="info-label"><ion-icon name="chatbox-outline"></ion-icon> Observação</span>
                        <span class="info-value"><?= htmlspecialchars($calculoKPC['DS_OBSERVACAO']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabela de Leituras -->
    <div class="info-card">
        <div class="info-card-header">
            <ion-icon name="list-outline"></ion-icon>
            <h2>Leituras da Deflexão (mm)</h2>
        </div>
        <div class="info-card-body">
            <div class="leituras-container">
                <table class="leituras-table">
                    <thead>
                        <tr>
                            <th class="posicao" style="min-width: 100px;">Pontos:</th>
                            <?php for ($ponto = 1; $ponto <= 11; $ponto++): ?>
                                <th><?= $ponto ?></th>
                            <?php endfor; ?>
                        </tr>
                        <tr>
                            <th class="posicao">Posição(mm):</th>
                            <?php foreach ($posicoesCalculadas as $pos): ?>
                                <th class="posicao-valor"><?= number_format($pos, 2, ',', '.') ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // 20 linhas de leitura
                        for ($leitura = 1; $leitura <= 20; $leitura++):
                            ?>
                            <tr>
                                <td class="posicao-label">Leitura <?= $leitura ?>:</td>
                                <?php for ($ponto = 1; $ponto <= 11; $ponto++): ?>
                                    <td>
                                        <?php if (isset($leiturasOrganizadas[$leitura][$ponto])): ?>
                                            <?= number_format($leiturasOrganizadas[$leitura][$ponto]['deflexao'], 2, ',', '.') ?>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endfor; ?>
                        <!-- Linha de Médias das Deflexões -->
                        <tr>
                            <td class="media-row">Média das<br>Deflexões:</td>
                            <?php for ($ponto = 1; $ponto <= 11; $ponto++): ?>
                                <td class="media-row">
                                    <?php if (isset($leiturasOrganizadas[21][$ponto])): ?>
                                        <?= number_format($leiturasOrganizadas[21][$ponto]['deflexao'], 2, ',', '.') ?>
                                    <?php else: ?>
                                        <span style="color: #92400e;">-</span>
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Gráfico da Curva de Velocidade -->
    <div class="info-card">
        <div class="info-card-header">
            <ion-icon name="stats-chart-outline"></ion-icon>
            <h2>Curva de Velocidade</h2>
        </div>
        <div class="info-card-body">
            <div class="grafico-container">
                <canvas id="graficoVelocidade"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js e Plugin Datalabels -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

<script>
    // ============================================
    // Dados para o Gráfico - Curva de Velocidade
    // ============================================
    // Coletar médias das deflexões por ponto
    const dadosGrafico = [];
    <?php
    // Percorre os 11 pontos e pega a média de cada um (linha 21)
    for ($ponto = 1; $ponto <= 11; $ponto++) {
        if (isset($leiturasOrganizadas[21][$ponto])) {
            $posicao = $posicoesCalculadas[$ponto];
            $deflexao = $leiturasOrganizadas[21][$ponto]['deflexao'];
            echo "dadosGrafico.push({ posicao: {$posicao}, deflexao: {$deflexao} });\n";
        }
    }
    ?>

    // Ordenar por posição (para garantir curva correta)
    dadosGrafico.sort((a, b) => a.posicao - b.posicao);

    // Calcular limites dinâmicos dos eixos
    const maxPosicao = Math.max(...dadosGrafico.map(d => d.posicao));
    const maxDeflexao = Math.max(...dadosGrafico.map(d => d.deflexao));

    // Arredondar para cima para ter margem
    const limiteY = Math.ceil(maxPosicao / 10) * 10 + 10;
    const limiteX = Math.ceil(maxDeflexao / 10) * 10 + 10;

    // Registrar plugin de datalabels
    Chart.register(ChartDataLabels);

    // Criar gráfico Scatter com linha e labels nos pontos
    const ctx = document.getElementById('graficoVelocidade').getContext('2d');
    new Chart(ctx, {
        type: 'scatter',
        data: {
            datasets: [{
                label: 'Curva de Velocidade',
                data: dadosGrafico.map(d => ({ x: d.deflexao, y: d.posicao })),
                borderColor: '#c9a227',
                backgroundColor: '#c9a227',
                showLine: true,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: '#c9a227',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: {
                    top: 20,
                    right: 100,
                    bottom: 20,
                    left: 20
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: 'Curva de Velocidade',
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                },
                subtitle: {
                    display: true,
                    text: 'Deflexão Média (mm)',
                    font: {
                        size: 12
                    }
                },
                tooltip: {
                    enabled: false
                },
                datalabels: {
                    display: true,
                    align: 'right',
                    anchor: 'end',
                    offset: 6,
                    color: '#666',
                    font: {
                        size: 10,
                        weight: 'bold'
                    },
                    formatter: function (value, context) {
                        // Formato: "posição: deflexão"
                        return value.y.toFixed(0) + ': ' + value.x.toFixed(4);
                    },
                    backgroundColor: 'rgba(255, 255, 255, 0.8)',
                    borderRadius: 3,
                    padding: {
                        top: 2,
                        bottom: 2,
                        left: 4,
                        right: 4
                    }
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Deflexão Média (mm)',
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    },
                    min: 0,
                    max: limiteX,
                    grid: {
                        color: '#e5e7eb'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Posição (mm)',
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    },
                    min: 0,
                    max: limiteY,
                    grid: {
                        color: '#e5e7eb'
                    }
                }
            }
        }
    });
</script>

<?php include_once 'includes/footer.inc.php'; ?>
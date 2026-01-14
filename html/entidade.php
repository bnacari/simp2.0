<?php
/**
 * SIMP - Gerenciamento de Unidades Operacionais
 * Estrutura hierárquica: ENTIDADE_TIPO -> ENTIDADE_VALOR -> ENTIDADE_VALOR_ITEM
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão de acesso à tela (mínimo leitura)
exigePermissaoTela('Cadastro de Entidade', ACESSO_LEITURA);

// Permissão do usuário para este módulo
$podeEditar = podeEditarTela('Cadastro de Entidade');

// Inicializa arrays
$entidades = [];
$pontosMedicao = [];
$totalTipos = 0;
$totalValores = 0;
$totalItens = 0;
$erroMsg = '';

// Buscar dados hierárquicos
// Verificar se coluna NR_ORDEM existe
$temNrOrdem = false;
try {
    $checkCol = $pdoSIMP->query("SELECT TOP 1 NR_ORDEM FROM SIMP.dbo.ENTIDADE_VALOR_ITEM");
    $temNrOrdem = true;
} catch (Exception $e) {
    $temNrOrdem = false;
}

try {
    if ($temNrOrdem) {
        $sql = "
            SELECT 
                ET.CD_CHAVE AS TIPO_CD,
                ET.DS_NOME AS TIPO_NOME,
                ET.CD_ENTIDADE_TIPO_ID AS TIPO_ID,
                ET.DESCARTE AS TIPO_DESCARTE,
                ET.DT_EXC_ENTIDADE_TIPO AS TIPO_DT_EXC,
                EV.CD_CHAVE AS VALOR_CD,
                EV.DS_NOME AS VALOR_NOME,
                EV.CD_ENTIDADE_VALOR_ID AS VALOR_ID,
                EV.ID_FLUXO,
                EVI.CD_CHAVE AS ITEM_CD,
                EVI.CD_PONTO_MEDICAO AS ITEM_PONTO,
                EVI.DT_INICIO AS ITEM_DT_INICIO,
                EVI.DT_FIM AS ITEM_DT_FIM,
                EVI.ID_OPERACAO AS ITEM_OPERACAO,
                EVI.NR_ORDEM AS ITEM_ORDEM,
                PM.DS_NOME AS PONTO_NOME,
                PM.ID_TIPO_MEDIDOR,
                PM.DS_TAG_VAZAO,
                PM.DS_TAG_PRESSAO,
                PM.DS_TAG_VOLUME,
                PM.DS_TAG_RESERVATORIO,
                PM.DS_TAG_TEMP_AGUA,
                PM.DS_TAG_TEMP_AMBIENTE,
                L.CD_LOCALIDADE,
                L.CD_UNIDADE
            FROM SIMP.dbo.ENTIDADE_TIPO ET
            LEFT JOIN SIMP.dbo.ENTIDADE_VALOR EV ON EV.CD_ENTIDADE_TIPO = ET.CD_CHAVE
            LEFT JOIN SIMP.dbo.ENTIDADE_VALOR_ITEM EVI ON EVI.CD_ENTIDADE_VALOR = EV.CD_CHAVE
            LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = EVI.CD_PONTO_MEDICAO
            LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            ORDER BY ET.DS_NOME, EV.DS_NOME, ISNULL(EVI.NR_ORDEM, 999999), PM.DS_NOME
        ";
    } else {
        $sql = "
            SELECT 
                ET.CD_CHAVE AS TIPO_CD,
                ET.DS_NOME AS TIPO_NOME,
                ET.CD_ENTIDADE_TIPO_ID AS TIPO_ID,
                ET.DESCARTE AS TIPO_DESCARTE,
                ET.DT_EXC_ENTIDADE_TIPO AS TIPO_DT_EXC,
                EV.CD_CHAVE AS VALOR_CD,
                EV.DS_NOME AS VALOR_NOME,
                EV.CD_ENTIDADE_VALOR_ID AS VALOR_ID,
                EV.ID_FLUXO,
                EVI.CD_CHAVE AS ITEM_CD,
                EVI.CD_PONTO_MEDICAO AS ITEM_PONTO,
                EVI.DT_INICIO AS ITEM_DT_INICIO,
                EVI.DT_FIM AS ITEM_DT_FIM,
                EVI.ID_OPERACAO AS ITEM_OPERACAO,
                NULL AS ITEM_ORDEM,
                PM.DS_NOME AS PONTO_NOME,
                PM.ID_TIPO_MEDIDOR,
                PM.DS_TAG_VAZAO,
                PM.DS_TAG_PRESSAO,
                PM.DS_TAG_VOLUME,
                PM.DS_TAG_RESERVATORIO,
                PM.DS_TAG_TEMP_AGUA,
                PM.DS_TAG_TEMP_AMBIENTE,
                L.CD_LOCALIDADE,
                L.CD_UNIDADE
            FROM SIMP.dbo.ENTIDADE_TIPO ET
            LEFT JOIN SIMP.dbo.ENTIDADE_VALOR EV ON EV.CD_ENTIDADE_TIPO = ET.CD_CHAVE
            LEFT JOIN SIMP.dbo.ENTIDADE_VALOR_ITEM EVI ON EVI.CD_ENTIDADE_VALOR = EV.CD_CHAVE
            LEFT JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = EVI.CD_PONTO_MEDICAO
            LEFT JOIN SIMP.dbo.LOCALIDADE L ON PM.CD_LOCALIDADE = L.CD_CHAVE
            ORDER BY ET.DS_NOME, EV.DS_NOME, PM.DS_NOME
        ";
    }
    
    $queryEntidades = $pdoSIMP->query($sql);
    
    if ($queryEntidades === false) {
        throw new Exception("Erro ao executar query de entidades");
    }

    $entidadesTemp = [];
    while ($row = $queryEntidades->fetch(PDO::FETCH_ASSOC)) {
        $tipoId = $row['TIPO_CD'];
        $valorId = $row['VALOR_CD'];
        
        if (!isset($entidadesTemp[$tipoId])) {
            $entidadesTemp[$tipoId] = [
                'cd' => $tipoId,
                'nome' => $row['TIPO_NOME'],
                'id' => $row['TIPO_ID'],
                'descarte' => !empty($row['TIPO_DESCARTE']),
                'dtExc' => $row['TIPO_DT_EXC'],
                'valores' => []
            ];
        }
        
        if ($valorId && !isset($entidadesTemp[$tipoId]['valores'][$valorId])) {
            $entidadesTemp[$tipoId]['valores'][$valorId] = [
                'cd' => $valorId,
                'nome' => $row['VALOR_NOME'],
                'id' => $row['VALOR_ID'],
                'fluxo' => $row['ID_FLUXO'],
                'itens' => []
            ];
        }
        
        if ($row['ITEM_CD'] && $valorId && isset($entidadesTemp[$tipoId]['valores'][$valorId])) {
            // Gerar código do ponto formatado
            $letrasTipo = [1 => 'M', 2 => 'E', 4 => 'P', 6 => 'R', 8 => 'H'];
            $letraTipo = $letrasTipo[$row['ID_TIPO_MEDIDOR']] ?? 'X';
            $codigoPonto = $row['CD_LOCALIDADE'] . '-' . 
                          str_pad($row['ITEM_PONTO'], 6, '0', STR_PAD_LEFT) . '-' . 
                          $letraTipo . '-' . 
                          $row['CD_UNIDADE'];
            
            $entidadesTemp[$tipoId]['valores'][$valorId]['itens'][] = [
                'cd' => $row['ITEM_CD'],
                'cdPonto' => $row['ITEM_PONTO'],
                'pontoNome' => $row['PONTO_NOME'],
                'pontoCodigo' => $codigoPonto,
                'dtInicio' => $row['ITEM_DT_INICIO'],
                'dtFim' => $row['ITEM_DT_FIM'],
                'operacao' => $row['ITEM_OPERACAO'],
                'ordem' => $row['ITEM_ORDEM'],
                'tagVazao' => $row['DS_TAG_VAZAO'] ?? '',
                'tagPressao' => $row['DS_TAG_PRESSAO'] ?? '',
                'tagVolume' => $row['DS_TAG_VOLUME'] ?? '',
                'tagReservatorio' => $row['DS_TAG_RESERVATORIO'] ?? '',
                'tagTempAgua' => $row['DS_TAG_TEMP_AGUA'] ?? '',
                'tagTempAmbiente' => $row['DS_TAG_TEMP_AMBIENTE'] ?? ''
            ];
        }
    }

    // Converte para array indexado
    $entidades = array_values($entidadesTemp);
    
    // Ordenar alfabeticamente
    usort($entidades, function($a, $b) {
        return strcasecmp($a['nome'], $b['nome']);
    });
    
    // Ordena valores dentro de cada tipo
    foreach ($entidades as &$tipo) {
        $tipo['valores'] = array_values($tipo['valores']);
        usort($tipo['valores'], function($a, $b) {
            return strcasecmp($a['nome'], $b['nome']);
        });
    }
    unset($tipo);

    // Contadores
    $totalTipos = 0;
    foreach ($entidades as $t) {
        if (empty($t['dtExc'])) $totalTipos++; // Conta apenas os ativos
        $totalValores += count($t['valores']);
        foreach ($t['valores'] as $v) {
            $totalItens += count($v['itens']);
        }
    }
    
} catch (Exception $e) {
    $erroMsg = $e->getMessage();
    $entidades = [];
}
?>

<style>
    .entidade-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 24px;
    }

    .entidade-header {
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        border-radius: 16px;
        padding: 28px 32px;
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }

    .entidade-header-content {
        display: flex;
        align-items: center;
        gap: 16px;
        position: relative;
        z-index: 1;
    }

    .entidade-header-icon {
        width: 52px;
        height: 52px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 24px;
    }

    .entidade-header h1 {
        color: #ffffff;
        font-size: 22px;
        font-weight: 700;
        margin: 0 0 4px 0;
    }

    .entidade-header p {
        color: rgba(255, 255, 255, 0.7);
        font-size: 13px;
        margin: 0;
    }

    .stats-bar {
        display: flex;
        gap: 16px;
        margin-bottom: 20px;
    }

    .stat-card {
        flex: 1;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all 0.2s ease;
    }

    .stat-card:hover {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    .stat-icon.tipo { background: #dbeafe; color: #1e3a5f; }
    .stat-icon.valor { background: #dcfce7; color: #15803d; }
    .stat-icon.item { background: #fef3c7; color: #b45309; }

    .stat-info h3 {
        font-size: 24px;
        font-weight: 700;
        color: #334155;
        margin: 0;
        line-height: 1;
    }

    .stat-info p {
        font-size: 12px;
        color: #64748b;
        margin: 4px 0 0 0;
    }

    .add-tipo-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 14px;
        background: #ffffff;
        border: 2px dashed #cbd5e1;
        border-radius: 10px;
        color: #64748b;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        margin-bottom: 16px;
    }

    .add-tipo-btn:hover {
        background: #f8fafc;
        border-color: #1e3a5f;
        color: #1e3a5f;
    }

    .add-tipo-btn ion-icon { font-size: 18px; }

    /* Tipo Card (Level 1) */
    .tipo-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        margin-bottom: 10px;
        overflow: hidden;
        transition: box-shadow 0.2s ease;
    }

    .tipo-card:hover {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .tipo-card.descarte .tipo-card-header {
        background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    }

    .tipo-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 18px;
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        cursor: pointer;
        transition: background 0.2s ease;
    }

    .tipo-card-header:hover {
        background: linear-gradient(135deg, #2d5a87 0%, #3b6d99 100%);
    }

    .tipo-card.descarte .tipo-card-header:hover {
        background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
    }

    .tipo-card-header-left {
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 1;
        min-width: 0;
    }

    .tipo-icon {
        width: 36px;
        height: 36px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 18px;
        flex-shrink: 0;
    }

    .tipo-info { min-width: 0; flex: 1; }

    .tipo-info h3 {
        color: #ffffff;
        font-size: 14px;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .tipo-info span {
        color: rgba(255, 255, 255, 0.7);
        font-size: 11px;
        display: block;
        margin-top: 2px;
    }

    .tipo-card-header-right {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
        margin-left: 12px;
    }

    .badge-count {
        padding: 4px 10px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 100px;
        color: rgba(255, 255, 255, 0.9);
        font-size: 11px;
        font-weight: 600;
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .header-actions .btn-action {
        width: 30px;
        height: 30px;
        background: rgba(255, 255, 255, 0.15);
        color: rgba(255, 255, 255, 0.9);
        border: none;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .header-actions .btn-action:hover {
        background: rgba(255, 255, 255, 0.25);
        color: #ffffff;
    }

    .header-actions .btn-action.delete:hover { background: #ef4444; }
    .header-actions .btn-action.restore:hover { background: #22c55e; }
    .header-actions .btn-action.add { background: rgba(34, 197, 94, 0.3); }
    .header-actions .btn-action.add:hover { background: #22c55e; }
    .header-actions .btn-action.chart { background: rgba(59, 130, 246, 0.3); }
    .header-actions .btn-action.chart:hover { background: #3b82f6; }
    .header-actions .btn-action ion-icon { font-size: 15px; }

    .expand-icon {
        width: 26px;
        height: 26px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgba(255, 255, 255, 0.7);
        font-size: 18px;
        transition: transform 0.2s ease;
    }

    .tipo-card.expanded .expand-icon {
        transform: rotate(180deg);
    }

    .tipo-card-body {
        display: none;
        padding: 16px;
        background: #f8fafc;
    }

    .tipo-card.expanded .tipo-card-body {
        display: block;
    }

    /* Valor Card (Level 2) */
    .valor-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        margin-bottom: 8px;
        overflow: hidden;
    }

    .valor-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 14px;
        background: #004993;
        cursor: pointer;
        transition: background 0.2s ease;
    }

    .valor-card-header:hover {
        background: #003d7a;
    }

    .valor-card-header-left {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
        min-width: 0;
    }

    .valor-icon {
        width: 28px;
        height: 28px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 14px;
        flex-shrink: 0;
    }

    .valor-info { min-width: 0; flex: 1; }

    .valor-info h4 {
        color: #ffffff;
        font-size: 13px;
        font-weight: 600;
        margin: 0;
    }

    .valor-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 2px;
    }

    .valor-id {
        color: rgba(255, 255, 255, 0.7);
        font-size: 10px;
    }

    .valor-card-header-right {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
        margin-left: 10px;
    }

    .valor-badge-count {
        padding: 3px 8px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 100px;
        color: rgba(255, 255, 255, 0.9);
        font-size: 10px;
        font-weight: 600;
    }

    /* Badge de Fluxo - Compacto */
    .fluxo-badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 9px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: #ffffff;
        white-space: nowrap;
    }
    
    .fluxo-badge.fluxo-1 { /* Entrada */
        background: #22c55e;
    }
    
    .fluxo-badge.fluxo-2 { /* Saída */
        background: #ef4444;
    }
    
    .fluxo-badge.fluxo-3 { /* Municipal */
        background: #3b82f6;
    }
    
    .fluxo-badge.fluxo-4 { /* Não se Aplica */
        background: #94a3b8;
    }
    
    .fluxo-badge.fluxo-0 { /* Não definido */
        background: #cbd5e1;
        color: #64748b;
    }

    .valor-expand-icon {
        width: 22px;
        height: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgba(255, 255, 255, 0.7);
        font-size: 16px;
        transition: transform 0.2s ease;
    }

    .valor-card.expanded .valor-expand-icon {
        transform: rotate(180deg);
    }

    .valor-card-body {
        display: none;
        padding: 12px;
        background: #ffffff;
    }

    .valor-card.expanded .valor-card-body {
        display: block;
    }

    /* Item Row (Level 3) */
    .item-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        margin-bottom: 6px;
        transition: all 0.2s ease;
    }

    .item-row:hover {
        border-color: #cbd5e1;
        background: #f1f5f9;
    }

    .item-row:last-child { margin-bottom: 0; }

    .item-row.expired {
        background: #fef2f2;
        border-color: #fecaca;
    }

    /* Drag and Drop */
    .item-drag-handle {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        cursor: grab;
        color: #94a3b8;
        margin-right: 8px;
        border-radius: 4px;
        transition: all 0.2s;
        flex-shrink: 0;
    }

    .item-drag-handle:hover {
        color: #64748b;
        background: #e2e8f0;
    }

    .item-drag-handle:active {
        cursor: grabbing;
    }

    .item-drag-handle ion-icon {
        font-size: 20px;
    }

    .item-row.dragging {
        opacity: 0.5;
        background: #e2e8f0;
        border-style: dashed;
    }

    .item-row.drag-over {
        border-color: #3b82f6;
        border-width: 2px;
        background: #eff6ff;
    }

    .itens-sortable {
        min-height: 10px;
    }

    .item-row-left {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
        min-width: 0;
    }

    .item-icons {
        display: flex;
        align-items: center;
        gap: 2px;
        flex-shrink: 0;
    }

    .item-icons .icon-location {
        width: 28px;
        height: 28px;
        background: #e2e8f0;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        font-size: 16px;
    }

    .item-icons .icon-operacao {
        font-size: 16px;
    }

    .item-icons .icon-operacao.operacao-1 {
        color: #4ade80;
    }

    .item-icons .icon-operacao.operacao-2 {
        color: #f87171;
    }

    .item-info { min-width: 0; flex: 1; }

    .item-info h5 {
        font-size: 12px;
        font-weight: 600;
        color: #334155;
        margin: 0;
    }

    .item-info span {
        font-size: 10px;
        color: #64748b;
        display: block;
        margin-top: 1px;
    }

    .item-tags {
        font-size: 9px;
        color: #8b5cf6;
        display: flex;
        align-items: center;
        gap: 4px;
        margin-top: 2px;
        padding: 2px 6px;
        background: #f5f3ff;
        border-radius: 3px;
        max-width: fit-content;
    }

    .item-tags ion-icon {
        font-size: 10px;
        flex-shrink: 0;
    }

    .item-row-right {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-shrink: 0;
        margin-left: 10px;
    }

    .item-row-right .btn-action {
        width: 26px;
        height: 26px;
        border-radius: 5px;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .item-row-right .btn-action ion-icon { font-size: 13px; }

    .item-row-right .btn-action.edit {
        background: #dbeafe;
        color: #1e3a5f;
    }

    .item-row-right .btn-action.edit:hover {
        background: #1e3a5f;
        color: #ffffff;
    }

    .item-row-right .btn-action.delete {
        background: #fee2e2;
        color: #dc2626;
    }

    .item-row-right .btn-action.delete:hover {
        background: #ef4444;
        color: #ffffff;
    }

    .item-row-right .btn-action.chart {
        background: #dbeafe;
        color: #3b82f6;
    }

    .item-row-right .btn-action.chart:hover {
        background: #3b82f6;
        color: #ffffff;
    }

    .item-row-right .btn-action.view {
        background: #f0fdf4;
        color: #16a34a;
        text-decoration: none;
    }

    .item-row-right .btn-action.view:hover {
        background: #16a34a;
        color: #ffffff;
    }

    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        border-radius: 100px;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .status-badge.descarte {
        background: #dcfce7;
        color: #15803d;
    }

    .status-badge.inativo {
        background: #fee2e2;
        color: #dc2626;
    }

    /* Tipo Card Inativo */
    .tipo-card.inativo .tipo-card-header {
        background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
    }

    .tipo-card.inativo .tipo-card-header:hover {
        background: linear-gradient(135deg, #64748b 0%, #005596 100%);
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 48px 24px;
        color: #64748b;
    }

    .empty-state ion-icon {
        font-size: 48px;
        margin-bottom: 16px;
        opacity: 0.4;
    }

    .empty-state h3 {
        font-size: 16px;
        font-weight: 600;
        color: #475569;
        margin: 0 0 8px 0;
    }

    .empty-state p {
        font-size: 14px;
        color: #64748b;
        margin: 0;
    }

    /* Área de Filtros */
    .filtros-container {
        background: #ffffff;
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        align-items: center;
    }

    .filtro-busca {
        position: relative;
        flex: 1;
        min-width: 280px;
    }

    .filtro-busca ion-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 18px;
    }

    .filtro-busca input {
        padding-left: 42px;
        padding-right: 36px;
        width: 100%;
        height: 42px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
    }

    .filtro-busca input:focus {
        border-color: #1e3a5f;
        box-shadow: 0 0 0 3px rgba(30, 58, 95, 0.1);
        outline: none;
    }

    .btn-limpar-busca {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        padding: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-limpar-busca:hover {
        color: #ef4444;
    }

    .btn-limpar-busca ion-icon {
        position: static;
        transform: none;
        font-size: 18px;
    }

    .filtro-descarte {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .filtro-label {
        font-size: 13px;
        font-weight: 500;
        color: #475569;
    }

    .radio-group-filtro {
        display: flex;
        gap: 4px;
        background: #f1f5f9;
        border-radius: 10px;
        padding: 4px;
    }

    .radio-filtro {
        cursor: pointer;
        margin: 0;
    }

    .radio-filtro input {
        display: none;
    }

    .radio-filtro span {
        display: block;
        padding: 8px 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        color: #64748b;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .radio-filtro input:checked + span {
        background: white;
        color: #1e293b;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .radio-filtro:hover span {
        color: #1e293b;
    }

    .radio-filtro input:checked + span:hover {
        color: #1e293b;
    }

    .filtro-acoes {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-left: auto;
    }

    .btn-acao-filtro {
        display: flex;
        align-items: center;
        gap: 6px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        padding: 8px 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        color: #475569;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-acao-filtro:hover {
        background: #e2e8f0;
        border-color: #cbd5e1;
        color: #1e3a5f;
    }

    .btn-acao-filtro ion-icon {
        font-size: 16px;
    }

    .filtro-resultado {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 12px;
        border-top: 1px solid #e2e8f0;
        margin-top: 4px;
    }

    .filtro-resultado span {
        font-size: 13px;
        color: #64748b;
    }

    .filtro-resultado strong {
        color: #1e3a5f;
    }

    .btn-limpar-filtro {
        display: flex;
        align-items: center;
        gap: 4px;
        background: none;
        border: 1px solid #e2e8f0;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-limpar-filtro:hover {
        border-color: #ef4444;
        color: #ef4444;
        background: #fef2f2;
    }

    .btn-limpar-filtro ion-icon {
        font-size: 14px;
    }

    /* Estados de filtro nos cards */
    .tipo-card.filtro-oculto,
    .valor-card.filtro-oculto,
    .item-row.filtro-oculto {
        display: none !important;
    }

    .tipo-card.filtro-match .tipo-card-header,
    .valor-card.filtro-match .valor-card-header {
        box-shadow: inset 0 0 0 2px #fbbf24;
    }

    .item-row.filtro-match {
        box-shadow: inset 0 0 0 2px #fbbf24;
        background: #fffbeb;
    }

    /* Expirado tem prioridade sobre filtro-match */
    .item-row.expired.filtro-match {
        background: #fef2f2;
        box-shadow: inset 0 0 0 2px #fbbf24;
    }

    /* Modal */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 24px;
    }

    .modal-overlay.active { display: flex; }

    .modal {
        background: #ffffff;
        border-radius: 12px;
        width: 100%;
        max-width: 480px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        overflow: hidden;
        animation: modalIn 0.25s ease;
    }

    @keyframes modalIn {
        from { opacity: 0; transform: scale(0.95) translateY(-10px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }

    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 20px;
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
    }

    .modal-header h3 {
        color: #ffffff;
        font-size: 16px;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .modal-header h3 ion-icon { font-size: 20px; }

    .modal-close {
        width: 32px;
        height: 32px;
        background: rgba(255, 255, 255, 0.15);
        border: none;
        border-radius: 6px;
        color: #ffffff;
        font-size: 18px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .modal-close:hover { background: rgba(255, 255, 255, 0.25); }

    .modal-body { padding: 20px; }

    .modal-body .form-group { margin-bottom: 16px; }
    .modal-body .form-group:last-child { margin-bottom: 0; }

    .form-label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 600;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 6px;
    }

    .form-label ion-icon { font-size: 14px; color: #64748b; }
    .form-label .required { color: #ef4444; }

    .form-control {
        width: 100%;
        height: 44px;
        padding: 0 14px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        color: #334155;
        background: #ffffff;
        transition: all 0.2s ease;
        box-sizing: border-box;
    }

    .form-control:hover { border-color: #cbd5e1; }

    .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-control::placeholder { color: #94a3b8; }

    .form-select {
        width: 100%;
        height: 44px;
        padding: 0 40px 0 14px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        color: #334155;
        background: #ffffff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E") no-repeat right 14px center;
        appearance: none;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .form-select:hover { border-color: #cbd5e1; }

    .form-select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Radio Group Inline */
    .radio-group-inline {
        display: flex;
        gap: 0;
        background: #f1f5f9;
        border-radius: 8px;
        padding: 4px;
        border: 1px solid #e2e8f0;
    }

    .radio-group-inline label {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 10px 16px;
        font-size: 12px;
        font-weight: 500;
        color: #64748b;
        cursor: pointer;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .radio-group-inline label:hover {
        color: #334155;
    }

    .radio-group-inline input[type="radio"] {
        display: none;
    }

    .radio-group-inline input[type="radio"]:checked + label {
        background: #ffffff;
        color: #1e3a5f;
        font-weight: 600;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .modal-footer {
        display: flex;
        gap: 10px;
        padding: 16px 20px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }

    .modal-footer .btn {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        height: 44px;
        padding: 0 20px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .modal-footer .btn ion-icon { font-size: 16px; }

    .modal-footer .btn-primary {
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        color: #ffffff;
    }

    .modal-footer .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .modal-footer .btn-secondary {
        background: #e2e8f0;
        color: #475569;
        flex: 0 0 auto;
    }

    .modal-footer .btn-secondary:hover { background: #cbd5e1; }

    @media (max-width: 768px) {
        .entidade-container { padding: 16px; }
        .entidade-header { padding: 20px; }
        .entidade-header h1 { font-size: 18px; }
        .entidade-header-content { flex-direction: column; text-align: center; }
        .stats-bar { flex-direction: column; }
        .stat-card { flex: 1 1 100%; }
        .tipo-card-header { padding: 12px 14px; }
        .tipo-info h3 { font-size: 13px; }
        .modal { margin: 16px; max-width: calc(100% - 32px); }
    }
</style>

<div class="entidade-container">
    <!-- Page Header -->
    <div class="entidade-header">
        <div class="entidade-header-content">
            <div class="entidade-header-icon">
                <ion-icon name="layers-outline"></ion-icon>
            </div>
            <div>
                <h1>Gerenciamento de Unidades Operacionais</h1>
                <p>Gerencie tipos, valores e itens de unidades operacionais do sistema</p>
            </div>
        </div>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar">
        <div class="stat-card">
            <div class="stat-icon tipo">
                <ion-icon name="folder-outline"></ion-icon>
            </div>
            <div class="stat-info">
                <h3><?= $totalTipos ?></h3>
                <p>Tipos Ativos</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon valor">
                <ion-icon name="albums-outline"></ion-icon>
            </div>
            <div class="stat-info">
                <h3><?= $totalValores ?></h3>
                <p>Unidades Operacionais</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon item">
                <ion-icon name="location-outline"></ion-icon>
            </div>
            <div class="stat-info">
                <h3><?= $totalItens ?></h3>
                <p>Pontos Vinculados</p>
            </div>
        </div>
    </div>

    <!-- Área de Filtros -->
    <div class="filtros-container">
        <div class="filtro-busca">
            <ion-icon name="search-outline"></ion-icon>
            <input type="text" id="filtroBusca" class="form-control" placeholder="Buscar por tipo, valor, ponto de medição ou TAG...">
            <button type="button" id="btnLimparBusca" class="btn-limpar-busca" style="display: none;" onclick="limparFiltros()">
                <ion-icon name="close-circle"></ion-icon>
            </button>
        </div>
        <div class="filtro-descarte">
            <span class="filtro-label">Descarte:</span>
            <div class="radio-group-filtro">
                <label class="radio-filtro">
                    <input type="radio" name="filtroDescarte" value="todos" checked>
                    <span>Todos</span>
                </label>
                <label class="radio-filtro">
                    <input type="radio" name="filtroDescarte" value="sim">
                    <span>Sim</span>
                </label>
                <label class="radio-filtro">
                    <input type="radio" name="filtroDescarte" value="nao">
                    <span>Não</span>
                </label>
            </div>
        </div>
        <div class="filtro-acoes">
            <button type="button" class="btn-acao-filtro" id="btnToggleAcordeoes" onclick="toggleTodosAcordeoes()" title="Expandir/Recolher todos">
                <ion-icon name="expand-outline" id="iconToggleAcordeoes"></ion-icon>
                <span id="textToggleAcordeoes">Expandir todos</span>
            </button>
        </div>
        <div class="filtro-resultado" id="filtroResultado" style="display: none;">
            <span id="filtroContagem"></span>
            <button type="button" class="btn-limpar-filtro" onclick="limparFiltros()">
                <ion-icon name="close-outline"></ion-icon> Limpar filtros
            </button>
        </div>
    </div>

    <?php if ($podeEditar): ?>
    <!-- Add Tipo Button -->
    <button class="add-tipo-btn" onclick="abrirModalTipo()">
        <ion-icon name="add-circle-outline"></ion-icon>
        Adicionar Novo Tipo de Unidade Operacional
    </button>
    <?php endif; ?>

    <!-- Tipo Cards -->
    <?php if (empty($entidades)) : ?>
        <div class="empty-state">
            <ion-icon name="layers-outline"></ion-icon>
            <h3>Nenhuma entidade cadastrada</h3>
            <p>Clique no botão acima para adicionar o primeiro tipo de unidade operacional</p>
        </div>
    <?php else : ?>
        <?php foreach ($entidades as $tipo) : 
            $isInativo = !empty($tipo['dtExc']);
        ?>
        <div class="tipo-card <?= $tipo['descarte'] ? 'descarte' : '' ?> <?= $isInativo ? 'inativo' : '' ?>" 
             id="tipo-<?= $tipo['cd'] ?>"
             data-tipo-nome="<?= htmlspecialchars(strtolower($tipo['nome'])) ?>"
             data-tipo-id="<?= htmlspecialchars(strtolower($tipo['id'] ?? '')) ?>"
             data-descarte="<?= $tipo['descarte'] ? '1' : '0' ?>">
            <!-- Tipo Header -->
            <div class="tipo-card-header" onclick="toggleTipo(<?= $tipo['cd'] ?>)">
                <div class="tipo-card-header-left">
                    <div class="tipo-icon">
                        <ion-icon name="folder-outline"></ion-icon>
                    </div>
                    <div class="tipo-info">
                        <h3>
                            <?= htmlspecialchars($tipo['nome']) ?>
                            <?php if ($tipo['descarte']) : ?>
                                <span class="status-badge descarte">Descarte</span>
                            <?php endif; ?>
                            <?php if ($isInativo) : ?>
                                <span class="status-badge inativo">Inativo</span>
                            <?php endif; ?>
                        </h3>
                        <span>ID: <?= htmlspecialchars($tipo['id'] ?? '-') ?></span>
                    </div>
                </div>
                <div class="tipo-card-header-right">
                    <span class="badge-count"><?= count($tipo['valores']) ?> valores</span>
                    <?php if ($podeEditar): ?>
                    <div class="header-actions" onclick="event.stopPropagation();">
                        <button class="btn-action add" onclick="abrirModalValor(null, '', '', <?= $tipo['cd'] ?>)" title="Adicionar Unidade Operacional">
                            <ion-icon name="add-outline"></ion-icon>
                        </button>
                        <button class="btn-action" onclick="abrirModalTipo(<?= $tipo['cd'] ?>, <?= htmlspecialchars(json_encode($tipo['nome']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($tipo['id'] ?? ''), ENT_QUOTES) ?>, <?= $tipo['descarte'] ? '1' : '0' ?>)" title="Editar">
                            <ion-icon name="pencil-outline"></ion-icon>
                        </button>
                        <?php if ($isInativo) : ?>
                            <button class="btn-action restore" onclick="alterarStatusTipo(<?= $tipo['cd'] ?>, 'ativar')" title="Ativar">
                                <ion-icon name="checkmark-circle-outline"></ion-icon>
                            </button>
                        <?php else : ?>
                            <button class="btn-action delete" onclick="alterarStatusTipo(<?= $tipo['cd'] ?>, 'desativar')" title="Desativar">
                                <ion-icon name="close-circle-outline"></ion-icon>
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="expand-icon">
                        <ion-icon name="chevron-down-outline"></ion-icon>
                    </div>
                </div>
            </div>

            <!-- Tipo Body (Valores) -->
            <div class="tipo-card-body">
                <?php if (empty($tipo['valores'])) : ?>
                    <div class="empty-state" style="padding: 32px;">
                        <ion-icon name="albums-outline"></ion-icon>
                        <h3>Nenhum valor cadastrado</h3>
                        <p>Adicione unidades operacionais a este tipo</p>
                    </div>
                <?php else : ?>
                    <?php foreach ($tipo['valores'] as $valor) : ?>
                    <div class="valor-card" id="valor-<?= $valor['cd'] ?>"
                         data-valor-nome="<?= htmlspecialchars(strtolower($valor['nome'])) ?>"
                         data-valor-id="<?= htmlspecialchars(strtolower($valor['id'] ?? '')) ?>">
                        <!-- Valor Header -->
                        <?php 
                            // Mapeamento de fluxo
                            $fluxosTexto = [
                                1 => 'Entrada',
                                2 => 'Saída',
                                3 => 'Municipal',
                                4 => 'Não se Aplica'
                            ];
                            $fluxoTexto = $fluxosTexto[$valor['fluxo']] ?? '-';
                        ?>
                        <div class="valor-card-header" onclick="toggleValor(<?= $valor['cd'] ?>)">
                            <div class="valor-card-header-left">
                                <div class="valor-icon">
                                    <ion-icon name="albums-outline"></ion-icon>
                                </div>
                                <div class="valor-info">
                                    <h4><?= htmlspecialchars($valor['nome']) ?></h4>
                                    <div class="valor-meta">
                                        <span class="valor-id">ID: <?= htmlspecialchars($valor['id'] ?? '-') ?></span>
                                        <span class="fluxo-badge fluxo-<?= $valor['fluxo'] ?? 0 ?>"><?= $fluxoTexto ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="valor-card-header-right">
                                <span class="valor-badge-count"><?= count($valor['itens']) ?> pontos</span>
                                <div class="header-actions" onclick="event.stopPropagation();">
                                    <button class="btn-action chart" onclick="abrirGraficoValor(<?= $tipo['cd'] ?>, <?= $valor['cd'] ?>, '<?= $valor['id'] ?? '' ?>')" title="Ver Gráfico">
                                        <ion-icon name="stats-chart-outline"></ion-icon>
                                    </button>
                                    <?php if ($podeEditar): ?>
                                    <button class="btn-action add" onclick="abrirModalItem(null, null, '', '', <?= $valor['cd'] ?>)" title="Vincular Ponto">
                                        <ion-icon name="add-outline"></ion-icon>
                                    </button>
                                    <button class="btn-action" onclick="abrirModalValor(<?= $valor['cd'] ?>, <?= htmlspecialchars(json_encode($valor['nome']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($valor['id'] ?? ''), ENT_QUOTES) ?>, <?= $tipo['cd'] ?>, '<?= $valor['fluxo'] ?? '' ?>')" title="Editar">
                                        <ion-icon name="pencil-outline"></ion-icon>
                                    </button>
                                    <button class="btn-action delete" onclick="excluirValor(<?= $valor['cd'] ?>)" title="Excluir">
                                        <ion-icon name="trash-outline"></ion-icon>
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <div class="valor-expand-icon">
                                    <ion-icon name="chevron-down-outline"></ion-icon>
                                </div>
                            </div>
                        </div>

                        <!-- Valor Body (Itens) -->
                        <div class="valor-card-body">
                            <?php if (empty($valor['itens'])) : ?>
                                <div class="empty-state" style="padding: 24px;">
                                    <ion-icon name="location-outline"></ion-icon>
                                    <h3>Nenhum ponto vinculado</h3>
                                    <p>Adicione pontos de medição a esta unidade operacional</p>
                                </div>
                            <?php else : ?>
                                <div class="itens-sortable" data-valor-cd="<?= $valor['cd'] ?>">
                                <?php foreach ($valor['itens'] as $item) : 
                                    // Usar DateTime para evitar problema do Y2K38 com strtotime
                                    $expirado = false;
                                    if (!empty($item['dtFim'])) {
                                        try {
                                            $dtFimObj = new DateTime($item['dtFim']);
                                            $hoje = new DateTime();
                                            $expirado = $dtFimObj < $hoje;
                                        } catch (Exception $e) {
                                            $expirado = false;
                                        }
                                    }
                                    $pontoLabel = ($item['pontoCodigo'] ?? '') . ' ' . ($item['pontoNome'] ?? '');
                                    $operacaoAtual = $item['operacao'];
                                ?>
                                <div class="item-row <?= $expirado ? 'expired' : '' ?>"
                                     data-item-cd="<?= $item['cd'] ?>"
                                     <?php if ($podeEditar && $temNrOrdem): ?>draggable="true"<?php endif; ?>
                                     data-ponto-nome="<?= htmlspecialchars(strtolower($item['pontoNome'] ?? '')) ?>"
                                     data-ponto-codigo="<?= htmlspecialchars(strtolower($item['pontoCodigo'] ?? '')) ?>"
                                     data-tag-vazao="<?= htmlspecialchars(strtolower($item['tagVazao'] ?? '')) ?>"
                                     data-tag-pressao="<?= htmlspecialchars(strtolower($item['tagPressao'] ?? '')) ?>"
                                     data-tag-volume="<?= htmlspecialchars(strtolower($item['tagVolume'] ?? '')) ?>"
                                     data-tag-reservatorio="<?= htmlspecialchars(strtolower($item['tagReservatorio'] ?? '')) ?>"
                                     data-tag-temp-agua="<?= htmlspecialchars(strtolower($item['tagTempAgua'] ?? '')) ?>"
                                     data-tag-temp-ambiente="<?= htmlspecialchars(strtolower($item['tagTempAmbiente'] ?? '')) ?>">
                                    <?php if ($podeEditar && $temNrOrdem): ?>
                                    <div class="item-drag-handle" title="Arraste para reordenar">
                                        <ion-icon name="reorder-three-outline"></ion-icon>
                                    </div>
                                    <?php endif; ?>
                                    <div class="item-row-left">
                                        <div class="item-icons">
                                            <ion-icon name="location-outline" class="icon-location"></ion-icon>
                                            <?php if ($operacaoAtual !== null && $operacaoAtual !== '') : ?>
                                            <ion-icon name="<?= $operacaoAtual == 1 ? 'add-circle' : 'remove-circle' ?>" class="icon-operacao operacao-<?= $operacaoAtual ?>"></ion-icon>
                                            <?php endif; ?>
                                        </div>
                                        <div class="item-info">
                                            <h5>
                                                <strong><?= htmlspecialchars($item['pontoCodigo'] ?? '') ?></strong> <?= htmlspecialchars($item['pontoNome'] ?? 'Ponto #' . $item['cdPonto']) ?>
                                            </h5>
                                            <span>
                                                <?php
                                                $periodo = [];
                                                if (!empty($item['dtInicio'])) {
                                                    $periodo[] = 'Início: ' . date('d/m/Y', strtotime($item['dtInicio']));
                                                }
                                                if (!empty($item['dtFim'])) {
                                                    $periodo[] = 'Fim: ' . date('d/m/Y', strtotime($item['dtFim']));
                                                }
                                                echo implode(' | ', $periodo) ?: 'Período não definido';
                                                ?>
                                            </span>
                                            <?php 
                                            // Coletar TAGs não vazias
                                            $tags = [];
                                            if (!empty($item['tagVazao'])) $tags[] = 'V: ' . $item['tagVazao'];
                                            if (!empty($item['tagPressao'])) $tags[] = 'P: ' . $item['tagPressao'];
                                            if (!empty($item['tagVolume'])) $tags[] = 'Vol: ' . $item['tagVolume'];
                                            if (!empty($item['tagReservatorio'])) $tags[] = 'R: ' . $item['tagReservatorio'];
                                            if (!empty($item['tagTempAgua'])) $tags[] = 'TA: ' . $item['tagTempAgua'];
                                            if (!empty($item['tagTempAmbiente'])) $tags[] = 'TAm: ' . $item['tagTempAmbiente'];
                                            
                                            if (!empty($tags)):
                                            ?>
                                            <span class="item-tags" title="Tags SCADA configuradas">
                                                <ion-icon name="pricetag-outline"></ion-icon>
                                                <?= htmlspecialchars(implode(' | ', $tags)) ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="item-row-right">
                                        <a href="pontoMedicaoView.php?id=<?= $item['cdPonto'] ?>" class="btn-action view" title="Ver Cadastro do Ponto">
                                            <ion-icon name="eye-outline"></ion-icon>
                                        </a>
                                        <button class="btn-action chart" onclick="abrirGraficoPonto(<?= $tipo['cd'] ?>, <?= $valor['cd'] ?>, '<?= $valor['id'] ?? '' ?>', <?= $item['cdPonto'] ?>)" title="Ver Gráfico">
                                            <ion-icon name="stats-chart-outline"></ion-icon>
                                        </button>
                                        <?php if ($podeEditar): ?>
                                        <button class="btn-action edit" onclick="abrirModalItem(<?= $item['cd'] ?>, <?= $item['cdPonto'] ?>, '<?= $item['dtInicio'] ? date('Y-m-d', strtotime($item['dtInicio'])) : '' ?>', '<?= $item['dtFim'] ? date('Y-m-d', strtotime($item['dtFim'])) : '' ?>', <?= $valor['cd'] ?>, <?= htmlspecialchars(json_encode($pontoLabel), ENT_QUOTES) ?>, '<?= $operacaoAtual ?? '' ?>')" title="Editar">
                                            <ion-icon name="pencil-outline"></ion-icon>
                                        </button>
                                        <button class="btn-action delete" onclick="excluirItem(<?= $item['cd'] ?>, <?= $valor['cd'] ?>)" title="Excluir">
                                            <ion-icon name="trash-outline"></ion-icon>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                </div><!-- .itens-sortable -->
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($podeEditar): ?>
<!-- Modal: Tipo de Unidade Operacional -->
<div class="modal-overlay" id="modalTipo">
    <div class="modal">
        <div class="modal-header">
            <h3>
                <ion-icon name="folder-outline"></ion-icon>
                <span id="modalTipoTitle">Novo Tipo de Unidade Operacional</span>
            </h3>
            <button class="modal-close" onclick="fecharModal('modalTipo')">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <form id="formTipo" onsubmit="salvarTipo(event)">
            <input type="hidden" name="cd" id="inputTipoCd">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="text-outline"></ion-icon>
                        Nome <span class="required">*</span>
                    </label>
                    <input type="text" name="nome" id="inputTipoNome" class="form-control" placeholder="Ex: Setor" required>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="finger-print-outline"></ion-icon>
                        ID Externo <span class="required">*</span>
                    </label>
                    <input type="text" name="idExterno" id="inputTipoId" class="form-control" placeholder="Identificador externo" required>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="swap-horizontal-outline"></ion-icon>
                        Descarte
                    </label>
                    <div class="radio-group-inline">
                        <input type="radio" name="descarte" id="descarteNao" value="0" checked>
                        <label for="descarteNao">Não</label>
                        <input type="radio" name="descarte" id="descarteSim" value="1">
                        <label for="descarteSim">Sim</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModal('modalTipo')">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <ion-icon name="checkmark-outline"></ion-icon>
                    Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Valor de Entidade -->
<div class="modal-overlay" id="modalValor">
    <div class="modal">
        <div class="modal-header">
            <h3>
                <ion-icon name="albums-outline"></ion-icon>
                <span id="modalValorTitle">Nova Unidade Operacional</span>
            </h3>
            <button class="modal-close" onclick="fecharModal('modalValor')">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <form id="formValor" onsubmit="salvarValor(event)">
            <input type="hidden" name="cd" id="inputValorCd">
            <input type="hidden" name="cdTipo" id="inputValorTipo">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="text-outline"></ion-icon>
                        Nome <span class="required">*</span>
                    </label>
                    <input type="text" name="nome" id="inputValorNome" class="form-control" placeholder="Ex: Setor Norte" required>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="finger-print-outline"></ion-icon>
                        ID Externo <span class="required">*</span>
                    </label>
                    <div class="autocomplete-container">
                        <input type="text" id="inputValorIdText" class="form-control" placeholder="Digite ou selecione um ID existente..." autocomplete="off">
                        <input type="hidden" name="idExterno" id="inputValorId">
                        <div id="inputValorIdDropdown" class="autocomplete-dropdown"></div>
                        <button type="button" id="btnLimparIdExterno" class="btn-limpar-autocomplete" style="display: none;" title="Limpar" onclick="limparIdExterno()">
                            <ion-icon name="close-circle"></ion-icon>
                        </button>
                    </div>
                    <small class="form-hint">Selecione um ID existente ou digite um novo</small>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="swap-horizontal-outline"></ion-icon>
                        Fluxo <span class="required">*</span>
                    </label>
                    <select name="fluxo" id="inputValorFluxo" class="form-control" required>
                        <option value="">Selecione...</option>
                        <option value="1">Entrada</option>
                        <option value="2">Saída</option>
                        <option value="3">Municipal</option>
                        <option value="4">Não se Aplica</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModal('modalValor')">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <ion-icon name="checkmark-outline"></ion-icon>
                    Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Item (Ponto de Medição) -->
<div class="modal-overlay" id="modalItem">
    <div class="modal">
        <div class="modal-header">
            <h3>
                <ion-icon name="location-outline"></ion-icon>
                <span id="modalItemTitle">Vincular Ponto de Medição</span>
            </h3>
            <button class="modal-close" onclick="fecharModal('modalItem')">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <form id="formItem" onsubmit="salvarItem(event)">
            <input type="hidden" name="cd" id="inputItemCd">
            <input type="hidden" name="cdValor" id="inputItemValor">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="location-outline"></ion-icon>
                        Ponto de Medição <span class="required">*</span>
                    </label>
                    <div class="autocomplete-container">
                        <input type="text" id="inputItemPontoText" class="form-control" placeholder="Clique para selecionar ou digite para filtrar..." autocomplete="off">
                        <input type="hidden" name="cdPonto" id="inputItemPonto" required>
                        <div id="inputItemPontoDropdown" class="autocomplete-dropdown"></div>
                        <button type="button" id="btnLimparPontoItem" class="btn-limpar-autocomplete" style="display: none;" title="Limpar" onclick="limparPontoItem()">
                            <ion-icon name="close-circle"></ion-icon>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="calendar-outline"></ion-icon>
                        Data Início <span class="required">*</span>
                    </label>
                    <input type="date" name="dtInicio" id="inputItemDtInicio" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="calendar-outline"></ion-icon>
                        Data Fim <span class="required">*</span>
                    </label>
                    <input type="date" name="dtFim" id="inputItemDtFim" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <ion-icon name="calculator-outline"></ion-icon>
                        Operação <span class="required">*</span>
                    </label>
                    <div class="radio-group-operacao">
                        <label class="radio-operacao radio-mais">
                            <input type="radio" name="operacao" id="operacaoMais" value="1">
                            <span class="radio-label">+</span>
                        </label>
                        <label class="radio-operacao radio-menos">
                            <input type="radio" name="operacao" id="operacaoMenos" value="2">
                            <span class="radio-label">−</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModal('modalItem')">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <ion-icon name="checkmark-outline"></ion-icon>
                    Salvar
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<style>
    /* Autocomplete para Ponto de Medição */
    .autocomplete-container {
        position: relative;
        width: 100%;
        box-sizing: border-box;
    }

    .autocomplete-container input.form-control {
        padding-right: 35px;
        width: 100%;
        box-sizing: border-box;
    }

    .autocomplete-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #e2e8f0;
        border-top: none;
        border-radius: 0 0 10px 10px;
        max-height: 250px;
        overflow-y: auto;
        z-index: 1001;
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
        background-color: #1e3a5f;
        color: white;
    }

    .autocomplete-item .item-code {
        font-family: 'SF Mono', Monaco, monospace;
        font-size: 12px;
        color: #64748b;
    }

    .autocomplete-item:hover .item-code,
    .autocomplete-item.highlighted .item-code {
        color: rgba(255, 255, 255, 0.8);
    }

    .autocomplete-item .item-name {
        display: block;
        margin-top: 2px;
    }

    .autocomplete-loading {
        padding: 12px;
        text-align: center;
        color: #64748b;
        font-size: 13px;
    }

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
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-limpar-autocomplete:hover {
        color: #ef4444;
    }

    .btn-limpar-autocomplete ion-icon {
        font-size: 18px;
    }

    .form-hint {
        display: block;
        margin-top: 4px;
        font-size: 11px;
        color: #94a3b8;
        font-style: italic;
    }

    /* Radio Group Operação (+/-) */
    .radio-group-operacao {
        display: flex;
        gap: 12px;
    }

    .radio-operacao {
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .radio-operacao input[type="radio"] {
        display: none;
    }

    .radio-operacao .radio-label {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        border-radius: 8px;
        font-size: 28px;
        font-weight: 700;
        border: 2px solid #e2e8f0;
        background: #f8fafc;
        color: #64748b;
        transition: all 0.2s ease;
    }

    .radio-operacao input[type="radio"]:checked + .radio-label {
        border-color: transparent;
        color: #ffffff;
    }

    .radio-mais input[type="radio"]:checked + .radio-label {
        background: #22c55e;
        box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3);
    }

    .radio-menos input[type="radio"]:checked + .radio-label {
        background: #ef4444;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }

    .radio-operacao:hover .radio-label {
        border-color: #cbd5e1;
        background: #f1f5f9;
    }

    .radio-mais:hover .radio-label {
        border-color: #86efac;
    }

    .radio-menos:hover .radio-label {
        border-color: #fca5a5;
    }
</style>

<script>
// ============================================
// Estado dos Acordeões (localStorage)
// ============================================
const STORAGE_KEY = 'entidade_expanded_state';
const STORAGE_KEY_FILTROS = 'entidade_filtros_state';

function saveExpandedState() {
    const expandedTipos = [];
    const expandedValores = [];
    
    document.querySelectorAll('.tipo-card.expanded').forEach(card => {
        const id = card.id.replace('tipo-', '');
        expandedTipos.push(id);
    });
    
    document.querySelectorAll('.valor-card.expanded').forEach(card => {
        const id = card.id.replace('valor-', '');
        expandedValores.push(id);
    });
    
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
        tipos: expandedTipos,
        valores: expandedValores
    }));
}

function restoreExpandedState() {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved) {
            const state = JSON.parse(saved);
            
            if (state.tipos) {
                state.tipos.forEach(id => {
                    const card = document.getElementById('tipo-' + id);
                    if (card) card.classList.add('expanded');
                });
            }
            
            if (state.valores) {
                state.valores.forEach(id => {
                    const card = document.getElementById('valor-' + id);
                    if (card) card.classList.add('expanded');
                });
            }
        }
    } catch (e) {
        console.error('Erro ao restaurar estado:', e);
    }
}

// ============================================
// Estado dos Filtros (localStorage)
// ============================================
function saveFiltrosState() {
    const busca = document.getElementById('filtroBusca').value;
    const descarte = document.querySelector('input[name="filtroDescarte"]:checked')?.value || 'todos';
    
    localStorage.setItem(STORAGE_KEY_FILTROS, JSON.stringify({
        busca: busca,
        descarte: descarte
    }));
}

function restoreFiltrosState() {
    try {
        const saved = localStorage.getItem(STORAGE_KEY_FILTROS);
        if (saved) {
            const state = JSON.parse(saved);
            
            if (state.busca) {
                document.getElementById('filtroBusca').value = state.busca;
                document.getElementById('btnLimparBusca').style.display = 'flex';
            }
            
            if (state.descarte) {
                const radio = document.querySelector(`input[name="filtroDescarte"][value="${state.descarte}"]`);
                if (radio) radio.checked = true;
            }
            
            // Aplicar filtros se houver algum salvo
            if (state.busca || state.descarte !== 'todos') {
                aplicarFiltros();
            }
        }
    } catch (e) {
        console.error('Erro ao restaurar filtros:', e);
    }
}

function clearFiltrosState() {
    localStorage.removeItem(STORAGE_KEY_FILTROS);
}

// Restaurar estado ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    restoreExpandedState();
    restoreFiltrosState();
});

// ============================================
// Toggle Acordeão
// ============================================
function toggleTipo(id) {
    const card = document.getElementById('tipo-' + id);
    card.classList.toggle('expanded');
    saveExpandedState();
    verificarEstadoAcordeoes();
}

function toggleValor(id) {
    event.stopPropagation();
    const card = document.getElementById('valor-' + id);
    card.classList.toggle('expanded');
    saveExpandedState();
    verificarEstadoAcordeoes();
}

function toggleTodosAcordeoes() {
    const tipoCards = document.querySelectorAll('.tipo-card:not(.filtro-oculto)');
    const valorCards = document.querySelectorAll('.valor-card:not(.filtro-oculto)');
    
    // Verifica se a maioria está expandida
    let expandidos = 0;
    let total = 0;
    
    tipoCards.forEach(card => {
        total++;
        if (card.classList.contains('expanded')) expandidos++;
    });
    
    valorCards.forEach(card => {
        total++;
        if (card.classList.contains('expanded')) expandidos++;
    });
    
    const devemExpandir = expandidos < total / 2;
    
    // Aplica a ação
    tipoCards.forEach(card => {
        if (devemExpandir) {
            card.classList.add('expanded');
        } else {
            card.classList.remove('expanded');
        }
    });
    
    valorCards.forEach(card => {
        if (devemExpandir) {
            card.classList.add('expanded');
        } else {
            card.classList.remove('expanded');
        }
    });
    
    // Atualiza o botão (se expandiu, mostrar "Recolher"; se recolheu, mostrar "Expandir")
    atualizarBotaoToggle(devemExpandir);
    
    // Salva estado
    saveExpandedState();
}

function atualizarBotaoToggle(todosExpandidos) {
    const icon = document.getElementById('iconToggleAcordeoes');
    const text = document.getElementById('textToggleAcordeoes');
    
    if (todosExpandidos) {
        icon.setAttribute('name', 'contract-outline');
        text.textContent = 'Recolher todos';
    } else {
        icon.setAttribute('name', 'expand-outline');
        text.textContent = 'Expandir todos';
    }
}

// Atualizar botão ao carregar e quando mudar estado
function verificarEstadoAcordeoes() {
    const tipoCards = document.querySelectorAll('.tipo-card:not(.filtro-oculto)');
    const valorCards = document.querySelectorAll('.valor-card:not(.filtro-oculto)');
    
    let expandidos = 0;
    let total = 0;
    
    tipoCards.forEach(card => {
        total++;
        if (card.classList.contains('expanded')) expandidos++;
    });
    
    valorCards.forEach(card => {
        total++;
        if (card.classList.contains('expanded')) expandidos++;
    });
    
    atualizarBotaoToggle(expandidos > total / 2);
}

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(verificarEstadoAcordeoes, 100);
});

// ============================================
// Sistema de Filtros
// ============================================
let filtroTimeout = null;

function initFiltros() {
    const inputBusca = document.getElementById('filtroBusca');
    const btnLimpar = document.getElementById('btnLimparBusca');
    const radiosDescarte = document.querySelectorAll('input[name="filtroDescarte"]');

    // Evento de digitação na busca
    inputBusca.addEventListener('input', function() {
        const termo = this.value.trim();
        btnLimpar.style.display = termo ? 'flex' : 'none';
        
        // Debounce
        clearTimeout(filtroTimeout);
        filtroTimeout = setTimeout(() => {
            aplicarFiltros();
        }, 300);
    });

    // Eventos dos radio buttons de descarte
    radiosDescarte.forEach(radio => {
        radio.addEventListener('change', aplicarFiltros);
    });
}

// Função para normalizar texto removendo acentos
function normalizarTexto(texto) {
    return texto
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function aplicarFiltros() {
    const termoBusca = normalizarTexto(document.getElementById('filtroBusca').value.trim());
    const filtroDescarte = document.querySelector('input[name="filtroDescarte"]:checked').value;
    
    // Salvar estado dos filtros
    saveFiltrosState();
    
    const tipoCards = document.querySelectorAll('.tipo-card');
    let totalTiposVisiveis = 0;
    let totalValoresVisiveis = 0;
    let totalItensVisiveis = 0;

    tipoCards.forEach(tipoCard => {
        const tipoNome = normalizarTexto(tipoCard.dataset.tipoNome || '');
        const tipoId = normalizarTexto(tipoCard.dataset.tipoId || '');
        const descarte = tipoCard.dataset.descarte;
        
        // Filtro de descarte
        let passaFiltroDescarte = true;
        if (filtroDescarte === 'sim' && descarte !== '1') passaFiltroDescarte = false;
        if (filtroDescarte === 'nao' && descarte !== '0') passaFiltroDescarte = false;

        if (!passaFiltroDescarte) {
            tipoCard.classList.add('filtro-oculto');
            tipoCard.classList.remove('filtro-match');
            return;
        }

        // Se não há termo de busca, mostra o tipo
        if (!termoBusca) {
            tipoCard.classList.remove('filtro-oculto', 'filtro-match');
            totalTiposVisiveis++;
            
            // Mostrar todos os valores e itens
            tipoCard.querySelectorAll('.valor-card').forEach(v => {
                v.classList.remove('filtro-oculto', 'filtro-match');
                totalValoresVisiveis++;
            });
            tipoCard.querySelectorAll('.item-row').forEach(i => {
                i.classList.remove('filtro-oculto', 'filtro-match');
                totalItensVisiveis++;
            });
            return;
        }

        // Busca textual
        let tipoMatch = tipoNome.includes(termoBusca) || tipoId.includes(termoBusca);
        let tipoTemFilhoVisivel = false;

        const valorCards = tipoCard.querySelectorAll('.valor-card');
        valorCards.forEach(valorCard => {
            const valorNome = normalizarTexto(valorCard.dataset.valorNome || '');
            const valorId = normalizarTexto(valorCard.dataset.valorId || '');
            
            let valorMatch = valorNome.includes(termoBusca) || valorId.includes(termoBusca);
            let valorTemFilhoVisivel = false;

            const itemRows = valorCard.querySelectorAll('.item-row');
            itemRows.forEach(itemRow => {
                const pontoNome = normalizarTexto(itemRow.dataset.pontoNome || '');
                const pontoCodigo = normalizarTexto(itemRow.dataset.pontoCodigo || '');
                const tagVazao = normalizarTexto(itemRow.dataset.tagVazao || '');
                const tagPressao = normalizarTexto(itemRow.dataset.tagPressao || '');
                const tagVolume = normalizarTexto(itemRow.dataset.tagVolume || '');
                const tagReservatorio = normalizarTexto(itemRow.dataset.tagReservatorio || '');
                const tagTempAgua = normalizarTexto(itemRow.dataset.tagTempAgua || '');
                const tagTempAmbiente = normalizarTexto(itemRow.dataset.tagTempAmbiente || '');
                
                // Busca por nome, código ou qualquer TAG
                let itemMatch = pontoNome.includes(termoBusca) || 
                               pontoCodigo.includes(termoBusca) ||
                               tagVazao.includes(termoBusca) ||
                               tagPressao.includes(termoBusca) ||
                               tagVolume.includes(termoBusca) ||
                               tagReservatorio.includes(termoBusca) ||
                               tagTempAgua.includes(termoBusca) ||
                               tagTempAmbiente.includes(termoBusca);

                if (itemMatch) {
                    itemRow.classList.remove('filtro-oculto');
                    itemRow.classList.add('filtro-match');
                    valorTemFilhoVisivel = true;
                    totalItensVisiveis++;
                } else if (tipoMatch || valorMatch) {
                    // Se pai deu match, mostra filho sem highlight
                    itemRow.classList.remove('filtro-oculto', 'filtro-match');
                    totalItensVisiveis++;
                } else {
                    itemRow.classList.add('filtro-oculto');
                    itemRow.classList.remove('filtro-match');
                }
            });

            // Valor visível se: ele mesmo deu match, ou tem filho visível, ou tipo deu match
            if (valorMatch || valorTemFilhoVisivel || tipoMatch) {
                valorCard.classList.remove('filtro-oculto');
                tipoTemFilhoVisivel = true;
                totalValoresVisiveis++;

                if (valorMatch && !tipoMatch) {
                    valorCard.classList.add('filtro-match');
                } else {
                    valorCard.classList.remove('filtro-match');
                }

                // Expandir valor se tem item com match
                if (valorTemFilhoVisivel) {
                    valorCard.classList.add('expanded');
                }
            } else {
                valorCard.classList.add('filtro-oculto');
                valorCard.classList.remove('filtro-match');
            }
        });

        // Tipo visível se: ele mesmo deu match ou tem filho visível
        if (tipoMatch || tipoTemFilhoVisivel) {
            tipoCard.classList.remove('filtro-oculto');
            totalTiposVisiveis++;

            if (tipoMatch) {
                tipoCard.classList.add('filtro-match');
            } else {
                tipoCard.classList.remove('filtro-match');
            }

            // Expandir tipo se tem valor com match
            if (tipoTemFilhoVisivel || termoBusca) {
                tipoCard.classList.add('expanded');
            }
        } else {
            tipoCard.classList.add('filtro-oculto');
            tipoCard.classList.remove('filtro-match');
        }
    });

    // Atualizar contagem de resultados
    atualizarContagemFiltro(termoBusca, filtroDescarte, totalTiposVisiveis, totalValoresVisiveis, totalItensVisiveis);
}

function atualizarContagemFiltro(termoBusca, filtroDescarte, tipos, valores, itens) {
    const divResultado = document.getElementById('filtroResultado');
    const spanContagem = document.getElementById('filtroContagem');
    
    if (termoBusca || filtroDescarte !== 'todos') {
        divResultado.style.display = 'flex';
        
        let texto = [];
        if (tipos > 0) texto.push(`<strong>${tipos}</strong> tipo${tipos !== 1 ? 's' : ''}`);
        if (valores > 0) texto.push(`<strong>${valores}</strong> valor${valores !== 1 ? 'es' : ''}`);
        if (itens > 0) texto.push(`<strong>${itens}</strong> ponto${itens !== 1 ? 's' : ''}`);
        
        if (texto.length > 0) {
            spanContagem.innerHTML = `Exibindo: ${texto.join(', ')}`;
        } else {
            spanContagem.innerHTML = 'Nenhum resultado encontrado';
        }
    } else {
        divResultado.style.display = 'none';
    }
    
    // Atualizar botão de expandir/recolher
    verificarEstadoAcordeoes();
}

function limparFiltros() {
    document.getElementById('filtroBusca').value = '';
    document.getElementById('btnLimparBusca').style.display = 'none';
    document.querySelector('input[name="filtroDescarte"][value="todos"]').checked = true;
    
    // Remover classes de filtro
    document.querySelectorAll('.tipo-card, .valor-card, .item-row').forEach(el => {
        el.classList.remove('filtro-oculto', 'filtro-match');
    });
    
    document.getElementById('filtroResultado').style.display = 'none';
    
    // Limpar estado salvo dos filtros
    clearFiltrosState();
    
    // Restaurar estado salvo dos acordeões
    restoreExpandedState();
    
    // Atualizar botão de expandir/recolher
    setTimeout(verificarEstadoAcordeoes, 50);
}

// Inicializar filtros
document.addEventListener('DOMContentLoaded', initFiltros);

// ============================================
// Modal Functions
// ============================================
function fecharModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function abrirModalTipo(cd = null, nome = '', idExterno = '', descarte = 0) {
    event && event.stopPropagation();
    const modal = document.getElementById('modalTipo');
    
    document.getElementById('modalTipoTitle').textContent = cd ? 'Editar Tipo de Unidade Operacional' : 'Novo Tipo de Unidade Operacional';
    document.getElementById('inputTipoCd').value = cd || '';
    document.getElementById('inputTipoNome').value = nome;
    document.getElementById('inputTipoId').value = idExterno;
    
    // Selecionar radio button de descarte
    if (descarte == 1) {
        document.getElementById('descarteSim').checked = true;
    } else {
        document.getElementById('descarteNao').checked = true;
    }
    
    modal.classList.add('active');
    document.getElementById('inputTipoNome').focus();
}

function abrirModalValor(cd = null, nome = '', idExterno = '', cdTipo = null, fluxo = '') {
    event && event.stopPropagation();
    const modal = document.getElementById('modalValor');
    
    document.getElementById('modalValorTitle').textContent = cd ? 'Editar Unidade Operacional' : 'Nova Unidade Operacional';
    document.getElementById('inputValorCd').value = cd || '';
    document.getElementById('inputValorTipo').value = cdTipo || '';
    document.getElementById('inputValorNome').value = nome;
    
    // ID Externo - tanto no campo hidden quanto no texto
    document.getElementById('inputValorId').value = idExterno || '';
    document.getElementById('inputValorIdText').value = idExterno || '';
    document.getElementById('btnLimparIdExterno').style.display = idExterno ? 'flex' : 'none';
    document.getElementById('inputValorIdDropdown').classList.remove('active');
    
    document.getElementById('inputValorFluxo').value = fluxo || '';
    
    modal.classList.add('active');
    document.getElementById('inputValorNome').focus();
}

// ============================================
// Autocomplete de ID Externo
// ============================================
let autocompleteIdExternoTimeout = null;
let autocompleteIdExternoIndex = -1;

function initAutocompleteIdExterno() {
    const input = document.getElementById('inputValorIdText');
    const hidden = document.getElementById('inputValorId');
    const dropdown = document.getElementById('inputValorIdDropdown');
    const btnLimpar = document.getElementById('btnLimparIdExterno');

    // Evento de foco - abre dropdown
    input.addEventListener('focus', function() {
        const cdTipo = document.getElementById('inputValorTipo').value;
        if (cdTipo) {
            buscarIdExternoAutocomplete('');
        }
    });

    // Evento de digitação
    input.addEventListener('input', function() {
        const termo = this.value.trim();
        
        // Atualiza o campo hidden com o valor digitado (permite valor livre)
        hidden.value = termo;
        btnLimpar.style.display = termo ? 'flex' : 'none';
        autocompleteIdExternoIndex = -1;

        // Debounce
        clearTimeout(autocompleteIdExternoTimeout);
        autocompleteIdExternoTimeout = setTimeout(() => {
            const cdTipo = document.getElementById('inputValorTipo').value;
            if (cdTipo) {
                buscarIdExternoAutocomplete(termo);
            }
        }, 300);
    });

    // Navegação por teclado
    input.addEventListener('keydown', function(e) {
        const items = dropdown.querySelectorAll('.autocomplete-item');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            autocompleteIdExternoIndex = Math.min(autocompleteIdExternoIndex + 1, items.length - 1);
            atualizarHighlightIdExterno(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            autocompleteIdExternoIndex = Math.max(autocompleteIdExternoIndex - 1, 0);
            atualizarHighlightIdExterno(items);
        } else if (e.key === 'Enter' && autocompleteIdExternoIndex >= 0) {
            e.preventDefault();
            if (items[autocompleteIdExternoIndex]) {
                items[autocompleteIdExternoIndex].click();
            }
        } else if (e.key === 'Escape') {
            dropdown.classList.remove('active');
        }
    });

    // Fechar dropdown ao clicar fora
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#inputValorIdText') && !e.target.closest('#inputValorIdDropdown')) {
            dropdown.classList.remove('active');
        }
    });
}

function atualizarHighlightIdExterno(items) {
    items.forEach((item, index) => {
        item.classList.toggle('highlighted', index === autocompleteIdExternoIndex);
    });
    
    if (autocompleteIdExternoIndex >= 0 && items[autocompleteIdExternoIndex]) {
        items[autocompleteIdExternoIndex].scrollIntoView({ block: 'nearest' });
    }
}

function buscarIdExternoAutocomplete(termo) {
    const dropdown = document.getElementById('inputValorIdDropdown');
    const cdTipo = document.getElementById('inputValorTipo').value;

    if (!cdTipo) {
        dropdown.innerHTML = '<div class="autocomplete-empty">Selecione um tipo primeiro</div>';
        dropdown.classList.add('active');
        return;
    }

    dropdown.innerHTML = '<div class="autocomplete-loading">Buscando...</div>';
    dropdown.classList.add('active');

    const params = new URLSearchParams({ cdTipo: cdTipo, busca: termo });

    fetch(`bd/entidade/buscarCD_ENTIDADE_VALOR_ID.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                let html = '';
                data.data.forEach(item => {
                    html += `
                        <div class="autocomplete-item" 
                             data-value="${item.id}" 
                             data-label="${item.label}">
                            <span class="item-code">${item.id}</span>
                            <span class="item-name">${item.nome} <span class="fluxo-badge fluxo-${item.fluxo}">${item.fluxoNome}</span></span>
                        </div>
                    `;
                });
                dropdown.innerHTML = html;

                dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
                    item.addEventListener('click', function() {
                        selecionarIdExterno(this.dataset.value);
                    });
                });
            } else {
                dropdown.innerHTML = '<div class="autocomplete-empty">Nenhum ID encontrado. Digite um novo.</div>';
            }
        })
        .catch(error => {
            console.error('Erro ao buscar IDs:', error);
            dropdown.innerHTML = '<div class="autocomplete-empty">Erro ao buscar</div>';
        });
}

function selecionarIdExterno(value) {
    document.getElementById('inputValorId').value = value;
    document.getElementById('inputValorIdText').value = value;
    document.getElementById('inputValorIdDropdown').classList.remove('active');
    document.getElementById('btnLimparIdExterno').style.display = 'flex';
    autocompleteIdExternoIndex = -1;
}

function limparIdExterno() {
    document.getElementById('inputValorId').value = '';
    document.getElementById('inputValorIdText').value = '';
    document.getElementById('btnLimparIdExterno').style.display = 'none';
    document.getElementById('inputValorIdText').focus();
}

// Inicializar autocomplete de ID Externo
document.addEventListener('DOMContentLoaded', initAutocompleteIdExterno);

// Variáveis do autocomplete de ponto
let autocompletePontoTimeout = null;
let autocompletePontoIndex = -1;
const letrasTipoMedidor = {1: 'M', 2: 'E', 4: 'P', 6: 'R', 8: 'H'};

function abrirModalItem(cd = null, cdPonto = null, dtInicio = '', dtFim = '', cdValor = null, pontoLabel = '', operacao = '') {
    event && event.stopPropagation();
    const modal = document.getElementById('modalItem');
    
    document.getElementById('modalItemTitle').textContent = cd ? 'Editar Vínculo' : 'Vincular Ponto de Medição';
    document.getElementById('inputItemCd').value = cd || '';
    document.getElementById('inputItemValor').value = cdValor || '';
    document.getElementById('inputItemDtInicio').value = dtInicio;
    document.getElementById('inputItemDtFim').value = dtFim;
    
    // Operação (+/-)
    // Para novos cadastros (cd vazio), marca '+' por padrão
    // Para edição, usa o valor que veio do banco (pode ser vazio)
    const op = cd ? operacao : (operacao || '1');
    document.getElementById('operacaoMais').checked = (op == '1');
    document.getElementById('operacaoMenos').checked = (op == '2');
    
    // Limpar autocomplete
    document.getElementById('inputItemPonto').value = cdPonto || '';
    document.getElementById('inputItemPontoText').value = pontoLabel || '';
    document.getElementById('btnLimparPontoItem').style.display = pontoLabel ? 'flex' : 'none';
    document.getElementById('inputItemPontoDropdown').classList.remove('active');
    
    modal.classList.add('active');
    document.getElementById('inputItemPontoText').focus();
}

// ============================================
// Autocomplete de Ponto de Medição
// ============================================
function initAutocompleteItem() {
    const input = document.getElementById('inputItemPontoText');
    const hidden = document.getElementById('inputItemPonto');
    const dropdown = document.getElementById('inputItemPontoDropdown');
    const btnLimpar = document.getElementById('btnLimparPontoItem');

    // Evento de foco - abre dropdown
    input.addEventListener('focus', function() {
        if (!hidden.value) {
            buscarPontosAutocomplete('');
        }
    });

    // Evento de digitação
    input.addEventListener('input', function() {
        const termo = this.value.trim();
        
        // Limpa seleção anterior
        hidden.value = '';
        btnLimpar.style.display = 'none';
        autocompletePontoIndex = -1;

        // Debounce
        clearTimeout(autocompletePontoTimeout);
        autocompletePontoTimeout = setTimeout(() => {
            buscarPontosAutocomplete(termo);
        }, 300);
    });

    // Navegação por teclado
    input.addEventListener('keydown', function(e) {
        const items = dropdown.querySelectorAll('.autocomplete-item');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            autocompletePontoIndex = Math.min(autocompletePontoIndex + 1, items.length - 1);
            atualizarHighlightPonto(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            autocompletePontoIndex = Math.max(autocompletePontoIndex - 1, 0);
            atualizarHighlightPonto(items);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (autocompletePontoIndex >= 0 && items[autocompletePontoIndex]) {
                items[autocompletePontoIndex].click();
            }
        } else if (e.key === 'Escape') {
            dropdown.classList.remove('active');
        }
    });

    // Fechar dropdown ao clicar fora
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.autocomplete-container')) {
            dropdown.classList.remove('active');
        }
    });
}

function atualizarHighlightPonto(items) {
    items.forEach((item, index) => {
        item.classList.toggle('highlighted', index === autocompletePontoIndex);
    });
    
    // Scroll para item visível
    if (autocompletePontoIndex >= 0 && items[autocompletePontoIndex]) {
        items[autocompletePontoIndex].scrollIntoView({ block: 'nearest' });
    }
}

function buscarPontosAutocomplete(termo) {
    const dropdown = document.getElementById('inputItemPontoDropdown');

    dropdown.innerHTML = '<div class="autocomplete-loading">Buscando...</div>';
    dropdown.classList.add('active');

    const params = new URLSearchParams({ busca: termo });

    fetch(`bd/pontoMedicao/buscarPontosMedicao.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                let html = '';
                data.data.forEach(item => {
                    const letraTipo = letrasTipoMedidor[item.ID_TIPO_MEDIDOR] || 'X';
                    const codigoPonto = item.CD_LOCALIDADE + '-' + 
                                       String(item.CD_PONTO_MEDICAO).padStart(6, '0') + '-' + 
                                       letraTipo + '-' + 
                                       item.CD_UNIDADE;
                    html += `
                        <div class="autocomplete-item" 
                             data-value="${item.CD_PONTO_MEDICAO}" 
                             data-label="${codigoPonto} - ${item.DS_NOME}">
                            <span class="item-code">${codigoPonto}</span>
                            <span class="item-name">${item.DS_NOME}</span>
                        </div>
                    `;
                });
                dropdown.innerHTML = html;

                // Adiciona eventos de clique
                dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
                    item.addEventListener('click', function() {
                        selecionarPontoAutocomplete(this.dataset.value, this.dataset.label);
                    });
                });
            } else {
                dropdown.innerHTML = '<div class="autocomplete-empty">Nenhum ponto encontrado</div>';
            }
        })
        .catch(error => {
            console.error('Erro ao buscar pontos:', error);
            dropdown.innerHTML = '<div class="autocomplete-empty">Erro ao buscar</div>';
        });
}

function selecionarPontoAutocomplete(value, label) {
    document.getElementById('inputItemPonto').value = value;
    document.getElementById('inputItemPontoText').value = label;
    document.getElementById('inputItemPontoDropdown').classList.remove('active');
    document.getElementById('btnLimparPontoItem').style.display = 'flex';
    autocompletePontoIndex = -1;
}

function limparPontoItem() {
    document.getElementById('inputItemPonto').value = '';
    document.getElementById('inputItemPontoText').value = '';
    document.getElementById('btnLimparPontoItem').style.display = 'none';
    document.getElementById('inputItemPontoText').focus();
}

// Inicializar autocomplete quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', initAutocompleteItem);

// Fechar modal ao clicar fora
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});

// ============================================
// CRUD Functions
// ============================================
function salvarTipo(event) {
    event.preventDefault();
    
    const cd = document.getElementById('inputTipoCd').value;
    const nome = document.getElementById('inputTipoNome').value.trim();
    const idExterno = document.getElementById('inputTipoId').value.trim();
    const descarte = document.querySelector('input[name="descarte"]:checked').value;
    
    if (!nome) {
        showToast('O nome é obrigatório', 'erro');
        return;
    }
    
    if (!idExterno) {
        showToast('O ID Externo é obrigatório', 'erro');
        return;
    }
    
    $.ajax({
        url: 'bd/entidade/salvarTipo.php',
        type: 'POST',
        data: { cd, nome, idExterno, descarte },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                saveExpandedState();
                setTimeout(() => location.reload(), 500);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        },
        error: function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        }
    });
}

function alterarStatusTipo(cd, acao) {
    const mensagem = acao === 'ativar' ? 'Deseja ativar este tipo de unidade operacional?' : 'Deseja desativar este tipo de unidade operacional?';
    if (!confirm(mensagem)) return;
    
    $.ajax({
        url: 'bd/entidade/alterarStatusTipo.php',
        type: 'POST',
        data: { cd, acao },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                saveExpandedState();
                setTimeout(() => location.reload(), 500);
            } else {
                showToast(response.message || 'Erro ao alterar status', 'erro');
            }
        },
        error: function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        }
    });
}

function salvarValor(event) {
    event.preventDefault();
    
    const cd = document.getElementById('inputValorCd').value;
    const cdTipo = document.getElementById('inputValorTipo').value;
    const nome = document.getElementById('inputValorNome').value.trim();
    // Pegar do campo de texto para permitir valor livre
    const idExterno = document.getElementById('inputValorIdText').value.trim();
    const fluxo = document.getElementById('inputValorFluxo').value;
    
    if (!nome) {
        showToast('O nome é obrigatório', 'erro');
        return;
    }
    
    if (!idExterno) {
        showToast('O ID Externo é obrigatório', 'erro');
        return;
    }
    
    if (!fluxo) {
        showToast('O fluxo é obrigatório', 'erro');
        return;
    }
    
    $.ajax({
        url: 'bd/entidade/salvarValor.php',
        type: 'POST',
        data: { cd, cdTipo, nome, idExterno, fluxo },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                saveExpandedState();
                setTimeout(() => location.reload(), 500);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        },
        error: function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        }
    });
}

function salvarItem(event) {
    event.preventDefault();
    
    const cd = document.getElementById('inputItemCd').value;
    const cdValor = document.getElementById('inputItemValor').value;
    const cdPonto = document.getElementById('inputItemPonto').value;
    const dtInicio = document.getElementById('inputItemDtInicio').value;
    const dtFim = document.getElementById('inputItemDtFim').value;
    const operacaoEl = document.querySelector('input[name="operacao"]:checked');
    const operacao = operacaoEl ? operacaoEl.value : '';
    
    if (!cdPonto) {
        showToast('Selecione um ponto de medição', 'erro');
        return;
    }
    
    if (!dtInicio) {
        showToast('A data de início é obrigatória', 'erro');
        return;
    }
    
    if (!dtFim) {
        showToast('A data de fim é obrigatória', 'erro');
        return;
    }
    
    if (!operacao) {
        showToast('Selecione a operação (+ ou -)', 'erro');
        return;
    }
    
    $.ajax({
        url: 'bd/entidade/salvarItem.php',
        type: 'POST',
        data: { cd, cdValor, cdPonto, dtInicio, dtFim, operacao },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                saveExpandedState();
                setTimeout(() => location.reload(), 500);
            } else {
                showToast(response.message || 'Erro ao salvar', 'erro');
            }
        },
        error: function() {
            showToast('Erro ao comunicar com o servidor', 'erro');
        }
    });
}

// ============================================
// Funções para Gráficos
// ============================================
function abrirGraficoValor(tipoId, valorId, valorEntidadeId) {
    event && event.stopPropagation();
    const mesAtual = new Date().getMonth() + 1;
    const anoAtual = new Date().getFullYear();
    window.location.href = `operacoes.php?tipo=${tipoId}&valor=${valorId}&valorEntidadeId=${valorEntidadeId}&mes=${mesAtual}&ano=${anoAtual}`;
}

function abrirGraficoPonto(tipoId, valorId, valorEntidadeId, pontoId) {
    event && event.stopPropagation();
    const mesAtual = new Date().getMonth() + 1;
    const anoAtual = new Date().getFullYear();
    window.location.href = `operacoes.php?tipo=${tipoId}&valor=${valorId}&valorEntidadeId=${valorEntidadeId}&ponto=${pontoId}&mes=${mesAtual}&ano=${anoAtual}`;
}

function excluirValor(cd) {
    if (!confirm('Deseja excluir esta unidade operacional?\n\nTodos os pontos vinculados também serão removidos.')) return;
    
    $.ajax({
        url: 'bd/entidade/excluirValor.php',
        type: 'POST',
        data: { cd: cd },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                saveExpandedState();
                setTimeout(() => location.reload(), 500);
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro AJAX excluirValor:', status, error);
            console.error('Resposta:', xhr.responseText);
            
            // Tentar extrair mensagem de erro da resposta
            let mensagem = 'Erro ao comunicar com o servidor';
            try {
                if (xhr.responseText) {
                    const resp = JSON.parse(xhr.responseText);
                    if (resp.message) mensagem = resp.message;
                }
            } catch(e) {
                // Se não for JSON, mostrar parte da resposta
                if (xhr.responseText && xhr.responseText.length < 200) {
                    mensagem = 'Erro: ' + xhr.responseText;
                }
            }
            showToast(mensagem, 'erro');
        }
    });
}

function excluirItem(cd, cdValor) {
    if (!confirm('Deseja remover este ponto de medição da unidade operacional?')) return;
    
    // Garantir que o valor pai continue expandido
    const valorCard = document.getElementById('valor-' + cdValor);
    if (valorCard && !valorCard.classList.contains('expanded')) {
        valorCard.classList.add('expanded');
    }
    
    $.ajax({
        url: 'bd/entidade/excluirItem.php',
        type: 'POST',
        data: { cd },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast(response.message, 'sucesso');
                saveExpandedState();
                setTimeout(() => location.reload(), 500);
            } else {
                showToast(response.message || 'Erro ao excluir', 'erro');
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro AJAX:', xhr.responseText);
            showToast('Erro ao comunicar com o servidor', 'erro');
        }
    });
}

// ============================================
// Drag and Drop para reordenar pontos de medição
// ============================================
let draggedItem = null;
let dragSourceContainer = null;

function initDragAndDrop() {
    const containers = document.querySelectorAll('.itens-sortable');
    
    containers.forEach(container => {
        const items = container.querySelectorAll('.item-row');
        
        items.forEach(item => {
            // Eventos de arrastar
            item.addEventListener('dragstart', handleDragStart);
            item.addEventListener('dragend', handleDragEnd);
            item.addEventListener('dragover', handleDragOver);
            item.addEventListener('dragenter', handleDragEnter);
            item.addEventListener('dragleave', handleDragLeave);
            item.addEventListener('drop', handleDrop);
        });
    });
}

function handleDragStart(e) {
    draggedItem = this;
    dragSourceContainer = this.parentElement;
    
    // Adicionar classe de arrastar após um pequeno delay para efeito visual
    setTimeout(() => {
        this.classList.add('dragging');
    }, 0);
    
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', this.dataset.itemCd);
}

function handleDragEnd(e) {
    this.classList.remove('dragging');
    
    // Remover classes de todos os itens
    document.querySelectorAll('.item-row').forEach(item => {
        item.classList.remove('drag-over');
    });
    
    draggedItem = null;
    dragSourceContainer = null;
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

function handleDragEnter(e) {
    e.preventDefault();
    
    // Só permitir drop no mesmo container
    if (this !== draggedItem && this.parentElement === dragSourceContainer) {
        this.classList.add('drag-over');
    }
}

function handleDragLeave(e) {
    this.classList.remove('drag-over');
}

function handleDrop(e) {
    e.preventDefault();
    this.classList.remove('drag-over');
    
    // Só permitir drop no mesmo container
    if (this === draggedItem || this.parentElement !== dragSourceContainer) {
        return;
    }
    
    const container = this.parentElement;
    const items = Array.from(container.querySelectorAll('.item-row'));
    const draggedIndex = items.indexOf(draggedItem);
    const dropIndex = items.indexOf(this);
    
    // Reordenar no DOM
    if (draggedIndex < dropIndex) {
        this.parentNode.insertBefore(draggedItem, this.nextSibling);
    } else {
        this.parentNode.insertBefore(draggedItem, this);
    }
    
    // Salvar nova ordem no servidor
    salvarOrdemItens(container);
}

function salvarOrdemItens(container) {
    const valorCd = container.dataset.valorCd;
    const items = container.querySelectorAll('.item-row');
    const itens = Array.from(items).map(item => item.dataset.itemCd);
    
    fetch('bd/entidade/reordenarItens.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ itens: itens })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Ordem atualizada!', 'sucesso');
        } else {
            showToast(data.message || 'Erro ao reordenar', 'erro');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showToast('Erro ao comunicar com o servidor', 'erro');
    });
}

// Inicializar drag and drop após carregar a página
document.addEventListener('DOMContentLoaded', initDragAndDrop);
</script>

<?php include_once 'includes/footer.inc.php'; ?>
<?php
/**
 * SIMP - Operações
 * Visualização de dados de medição por unidade operacional
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão
exigePermissaoTela('Validação dos Dados', ACESSO_LEITURA);

// Permissão do usuário para este módulo
$podeEditar = podeEditarTela('Validação dos Dados');

// Buscar tipos de entidade para o dropdown
$tiposEntidade = [];
try {
    $sqlTipos = "SELECT CD_CHAVE, DS_NOME, CD_ENTIDADE_TIPO_ID 
                 FROM SIMP.dbo.ENTIDADE_TIPO 
                 WHERE DT_EXC_ENTIDADE_TIPO IS NULL 
                 ORDER BY DS_NOME";
    $stmtTipos = $pdoSIMP->query($sqlTipos);
    $tiposEntidade = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tiposEntidade = [];
}

// Parâmetros recebidos via GET (para links externos)
$tipoIdGet = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$valorIdGet = isset($_GET['valor']) ? $_GET['valor'] : '';
$valorEntidadeIdGet = isset($_GET['valorEntidadeId']) ? $_GET['valorEntidadeId'] : '';
$pontoIdGet = isset($_GET['ponto']) ? $_GET['ponto'] : '';
$mesGet = isset($_GET['mes']) && $_GET['mes'] !== '' ? intval($_GET['mes']) : intval(date('n')); // Mês atual se não informado
$anoGet = isset($_GET['ano']) && $_GET['ano'] !== '' ? intval($_GET['ano']) : intval(date('Y')); // Ano atual se não informado

// Parâmetros para abrir modal de validação automaticamente
$abrirValidacaoGet = isset($_GET['abrirValidacao']) ? $_GET['abrirValidacao'] : '';
$dataValidacaoGet = isset($_GET['dataValidacao']) ? $_GET['dataValidacao'] : '';
$cdPontoValidacaoGet = isset($_GET['cdPonto']) ? $_GET['cdPonto'] : '';

// Mapeamento de letras por tipo de medidor
$letrasTipoMedidor = [
    1 => 'M', // Macromedidor
    2 => 'E', // Estação Pitométrica
    4 => 'P', // Medidor Pressão
    6 => 'R', // Nível Reservatório
    8 => 'H'  // Hidrômetro
];
?>

<link rel="stylesheet" href="/style/css/operacoes.css" />
<!-- Choices.js CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

<div class="operacoes-container">
    <!-- Header -->
    <div class="operacoes-header">
        <div class="operacoes-header-content">
            <div class="operacoes-header-icon">
                <ion-icon name="stats-chart-outline"></ion-icon>
            </div>
            <div>
                <h1>Operações</h1>
                <p>Visualização de dados de medição por unidade operacional</p>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filtros-card">
        <div class="filtros-header">
            <div class="filtros-title">
                <ion-icon name="options-outline"></ion-icon>
                Filtros de Pesquisa
            </div>
            <div class="filtros-header-right">
                <span class="form-label-inline">Período:</span>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="tipoPeriodo" value="mes" checked
                            onchange="alternarTipoPeriodo('mes')">
                        <span class="radio-label">Mês</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="tipoPeriodo" value="datas" onchange="alternarTipoPeriodo('datas')">
                        <span class="radio-label">Datas</span>
                    </label>
                </div>
                <button type="button" class="btn-clear-filters" onclick="limparFiltros()">
                    <ion-icon name="refresh-outline"></ion-icon>
                    Limpar
                </button>
            </div>
        </div>

        <!-- Grid de filtros em uma linha -->
        <div class="filtros-grid" id="filtrosGrid">
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="folder-outline"></ion-icon>
                    Tipo de Unidade Operacional
                </label>
                <select id="selectTipoEntidade" class="form-control choices-select">
                    <option value="">Selecione o tipo de unidade...</option>
                    <?php foreach ($tiposEntidade as $tipo): ?>
                        <option value="<?= $tipo['CD_CHAVE'] ?>">
                            <?= htmlspecialchars($tipo['CD_ENTIDADE_TIPO_ID'] . ' - ' . $tipo['DS_NOME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="albums-outline"></ion-icon>
                    Unidade Operacional
                </label>
                <select id="selectValorEntidade" class="form-control choices-select-valor">
                    <option value="">Selecione o tipo de unidade primeiro</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="speedometer-outline"></ion-icon>
                    Ponto de Medição
                </label>
                <div class="autocomplete-container">
                    <input type="text" id="filtroPontoMedicaoInput" class="form-control"
                        placeholder="Digite para buscar um ponto..." autocomplete="off">
                    <input type="hidden" id="filtroPontoMedicao" value="">
                    <input type="hidden" id="valorEntidadeIdHidden" value="">
                    <div id="filtroPontoMedicaoDropdown" class="autocomplete-dropdown"></div>
                    <button type="button" id="btnLimparPonto" class="btn-limpar-autocomplete" style="display: none;"
                        title="Limpar">
                        <ion-icon name="close-circle"></ion-icon>
                    </button>
                </div>
            </div>

            <div class="form-group" id="grupoAno">
                <label class="form-label">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Ano
                </label>
                <select id="selectAno" class="form-control">
                    <?php
                    $anoAtual = date('Y');
                    for ($ano = $anoAtual; $ano >= $anoAtual - 10; $ano--):
                        ?>
                        <option value="<?= $ano ?>" <?= $ano == $anoGet ? 'selected' : '' ?>><?= $ano ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group" id="grupoMes">
                <label class="form-label">
                    <ion-icon name="calendar-number-outline"></ion-icon>
                    Mês
                </label>
                <div class="mes-navegacao">
                    <button type="button" class="btn-nav-mes" onclick="navegarMes(-1)" title="Mês anterior">
                        <ion-icon name="chevron-back-outline"></ion-icon>
                    </button>
                    <select id="selectMes" class="form-control">
                        <option value="">Selecione...</option>
                        <option value="1" <?= $mesGet == 1 ? 'selected' : '' ?>>Janeiro</option>
                        <option value="2" <?= $mesGet == 2 ? 'selected' : '' ?>>Fevereiro</option>
                        <option value="3" <?= $mesGet == 3 ? 'selected' : '' ?>>Março</option>
                        <option value="4" <?= $mesGet == 4 ? 'selected' : '' ?>>Abril</option>
                        <option value="5" <?= $mesGet == 5 ? 'selected' : '' ?>>Maio</option>
                        <option value="6" <?= $mesGet == 6 ? 'selected' : '' ?>>Junho</option>
                        <option value="7" <?= $mesGet == 7 ? 'selected' : '' ?>>Julho</option>
                        <option value="8" <?= $mesGet == 8 ? 'selected' : '' ?>>Agosto</option>
                        <option value="9" <?= $mesGet == 9 ? 'selected' : '' ?>>Setembro</option>
                        <option value="10" <?= $mesGet == 10 ? 'selected' : '' ?>>Outubro</option>
                        <option value="11" <?= $mesGet == 11 ? 'selected' : '' ?>>Novembro</option>
                        <option value="12" <?= $mesGet == 12 ? 'selected' : '' ?>>Dezembro</option>
                    </select>
                    <button type="button" class="btn-nav-mes" onclick="navegarMes(1)" title="Próximo mês">
                        <ion-icon name="chevron-forward-outline"></ion-icon>
                    </button>
                </div>
            </div>

            <div class="form-group" id="grupoDataInicio" style="display: none;">
                <label class="form-label">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Data Início
                </label>
                <input type="date" id="inputDataInicio" class="form-control">
            </div>

            <div class="form-group" id="grupoDataFim" style="display: none;">
                <label class="form-label">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Data Fim
                </label>
                <input type="date" id="inputDataFim" class="form-control">
            </div>
        </div>

        <div class="filtros-actions">
            <button type="button" class="btn-filtrar" id="btnFiltrar" onclick="buscarDados()">
                <ion-icon name="search-outline"></ion-icon>
                Buscar Dados
            </button>
            <button type="button" class="btn-analise-ia" id="btnAnaliseIA" onclick="abrirModalAnaliseIA()"
                <?= !$podeEditar ? 'style="display:none;"' : '' ?>>
                <ion-icon name="analytics-outline"></ion-icon>
                Analisar Dados
            </button>
        </div>
    </div>

    <!-- Resultado -->
    <div class="resultado-card" id="resultadoCard" style="display: none;">
        <div class="resultado-header">
            <h3>
                <ion-icon name="bar-chart-outline"></ion-icon>
                <span id="resultadoTipoMedidor" class="tipo-medidor-badge"></span>
                <span id="resultadoTitulo">Resultados</span>
            </h3>
            <span class="resultado-info" id="resultadoInfo"></span>
        </div>

        <!-- Resumo -->
        <div class="resumo-cards" id="resumoCards" style="display: none;"></div>

        <!-- Tabs -->
        <div class="resultado-tabs">
            <button type="button" class="resultado-tab" onclick="alternarTab('grafico')">
                <ion-icon name="stats-chart-outline"></ion-icon>
                Gráfico
            </button>
            <button type="button" class="resultado-tab active" onclick="alternarTab('tabela')">
                <ion-icon name="grid-outline"></ion-icon>
                Tabela
            </button>
        </div>

        <!-- Gráfico -->
        <div class="tab-content" id="tabGrafico">
            <div class="grafico-container">
                <canvas id="graficoOperacoes"></canvas>
            </div>
        </div>

        <!-- Tabela -->
        <div class="tab-content active" id="tabTabela">
            <div class="tabela-container">
                <table class="tabela-dados" id="tabelaDados">
                    <thead id="tabelaHead"></thead>
                    <tbody id="tabelaBody"></tbody>
                    <tfoot id="tabelaFoot"></tfoot>
                </table>
            </div>

            <!-- Legenda -->
            <div class="legenda-tabela">
                <div class="legenda-titulo">
                    <ion-icon name="information-circle-outline"></ion-icon>
                    Legenda e Regras de Cálculo
                </div>
                <div class="legenda-itens">
                    <div class="legenda-item">
                        <span class="legenda-cor incompleto"></span>
                        <span class="legenda-texto">Dia com menos de 1440 registros (dados incompletos)</span>
                    </div>
                    <div class="legenda-item">
                        <span class="legenda-cor tratado"></span>
                        <span class="legenda-texto">Dia com registros tratados manualmente</span>
                    </div>
                    <div class="legenda-item">
                        <span class="legenda-cor nivel-critico"></span>
                        <span class="legenda-texto">Nível crítico (≥ 100%)</span>
                    </div>
                    <div class="legenda-item">
                        <span class="legenda-icone soma">⊕</span>
                        <span class="legenda-texto">Operação de soma</span>
                    </div>
                    <div class="legenda-item">
                        <span class="legenda-icone subtracao">⊖</span>
                        <span class="legenda-texto">Operação de subtração</span>
                    </div>
                </div>
                <div class="legenda-info">
                    <strong>Cálculo da média diária:</strong> A média é calculada dividindo a soma dos valores por
                    <strong>1440</strong> (quantidade de minutos em um dia),
                    independentemente da quantidade real de registros. Isso garante que dias com menos registros
                    apresentem valores proporcionalmente menores.
                    <br><br>
                    <strong>Medidores de nível:</strong> Para medidores de nível, é exibido o <strong>valor
                        máximo</strong> registrado no dia. Passe o mouse sobre a célula para ver o horário em que o
                    valor foi registrado.
                    <br><br>
                    <strong>Gráfico rápido:</strong> Passe o mouse sobre o <strong>nome da coluna</strong> (ponto de
                    medição) para visualizar um gráfico com os valores do mês. Os pontos vermelhos mostram a média
                    diária, e as barras verticais indicam a faixa entre mínimo e máximo.
                    <br><br>
                    <strong>Validação de dados:</strong> Clique em qualquer <strong>célula de valor</strong> para abrir
                    o painel de validação. Você poderá visualizar os dados hora a hora e corrigir valores
                    inconsistentes.
                </div>
            </div>
        </div>
    </div>

    <!-- Empty State -->
    <div class="resultado-card" id="emptyState">
        <div class="empty-state">
            <ion-icon name="analytics-outline"></ion-icon>
            <h3>Selecione os filtros</h3>
            <p>Escolha o tipo de unidade operacional, unidade, ano e período para visualizar os dados</p>
        </div>
    </div>
</div>

<!-- Popup do Gráfico -->
<div class="grafico-popup" id="graficoPopup">
    <div class="grafico-popup-header">
        <div>
            <div class="grafico-popup-titulo" id="graficoPopupTitulo">-</div>
            <div class="grafico-popup-codigo" id="graficoPopupCodigo">-</div>
        </div>
        <button type="button" class="grafico-popup-fechar" onclick="fecharGraficoPopup()">X</button>
    </div>
    <div class="grafico-popup-container">
        <canvas id="graficoPopupCanvas"></canvas>
    </div>
</div>

<!-- Modal de Análise IA -->
<div class="modal-analise-ia" id="modalAnaliseIA">
    <div class="modal-analise-ia-content">
        <div class="modal-analise-ia-header">
            <div>
                <h3>
                    <ion-icon name="analytics-outline"></ion-icon>
                    Análise de Dados com IA
                </h3>
                <div class="subtitulo" id="analiseIASubtitulo">-</div>
            </div>
            <button type="button" class="btn-fechar" onclick="fecharModalAnaliseIA()">X</button>
        </div>
        <div class="modal-analise-ia-body" id="analiseIABody">
            <div class="analise-loading">
                <ion-icon name="sync-outline"></ion-icon>
                <span>Carregando estrutura...</span>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Validação de Dados -->
<div class="modal-validacao-overlay" id="modalValidacao">
    <div class="modal-validacao">
        <div class="modal-validacao-header">
            <div>
                <h3 id="validacaoTitulo"><ion-icon name="checkmark-circle-outline"></ion-icon> Validação de Dados</h3>
                <div class="subtitulo" id="validacaoSubtitulo">-</div>
            </div>
            <button type="button" class="modal-validacao-close" onclick="fecharModalValidacao()">X</button>
        </div>
        <div class="modal-validacao-body">
            <!-- Coluna Principal (Esquerda) -->
            <div class="validacao-coluna-principal">
                <div class="validacao-info">
                    <ion-icon name="information-circle-outline"></ion-icon>
                    <span id="validacaoInfoTexto">Marque uma ou mais horas na tabela para inserir/corrigir valores.
                        Informe o novo valor e clique em "Validar" para aplicar.</span>
                </div>

                <!-- Resumo do dia -->
                <div class="validacao-resumo" id="validacaoResumo">
                    <div class="resumo-item">
                        <span class="resumo-label">Mínima</span>
                        <span class="resumo-valor" id="resumoMinima">-</span>
                    </div>
                    <div class="resumo-item">
                        <span class="resumo-label">Média</span>
                        <span class="resumo-valor" id="resumoMedia">-</span>
                    </div>
                    <div class="resumo-item">
                        <span class="resumo-label">Máxima</span>
                        <span class="resumo-valor" id="resumoMaxima">-</span>
                    </div>
                </div>

                <!-- Legenda de cores -->
                <div class="validacao-legenda">
                    <span class="legenda-item"><span class="legenda-cor" style="background:#dc2626;"></span> Dados
                        originais</span>
                    <span class="legenda-item"><span class="legenda-cor" style="background:#3b82f6;"></span> Dados
                        validados</span>
                </div>

                <!-- Controles do Gráfico -->
                <div class="grafico-controles" id="graficoControles">
                    <span class="grafico-controles-titulo">Exibir:</span>
                    <label class="grafico-controle-item">
                        <input type="checkbox" id="chkLinhaPrincipal" checked
                            onchange="toggleLinhaGrafico('principal')">
                        <span class="controle-cor media"></span>
                        <span class="controle-label" id="lblLinhaPrincipal">Média</span>
                    </label>
                    <label class="grafico-controle-item">
                        <input type="checkbox" id="chkErrorBars" checked onchange="toggleLinhaGrafico('errorbars')">
                        <span class="controle-cor minmax"></span>
                        <span class="controle-label">Min/Max</span>
                    </label>
                    <label class="grafico-controle-item" id="controleValoresSugeridos" style="display:none;">
                        <input type="checkbox" id="chkValoresSugeridos" checked
                            onchange="toggleLinhaGrafico('sugeridos')">
                        <span class="controle-cor sugeridos"></span>
                        <span class="controle-label">Valores Sugeridos</span>
                    </label>
                    <label class="grafico-controle-item" id="controleValoresExcluidos" style="display:none;">
                        <input type="checkbox" id="chkValoresExcluidos" checked
                            onchange="toggleLinhaGrafico('excluidos')">
                        <span class="controle-cor excluidos"></span>
                        <span class="controle-label">Excluídos</span>
                    </label>
                    <label class="grafico-controle-item" id="controleMediaDiaria">
                        <input type="checkbox" id="chkMediaDiaria" checked onchange="toggleLinhaGrafico('mediadiaria')">
                        <span class="controle-cor mediadiaria"></span>
                        <span class="controle-label">Média Diária</span>
                    </label>
                </div>

                <!-- Gráfico Error Bar Chart -->
                <div class="validacao-grafico-container">
                    <canvas id="validacaoGrafico"></canvas>
                </div>

                <!-- Botões de ação rápida -->
                <div style="display:flex;gap:10px;margin-bottom:12px;" id="validacaoAcoesRapidas">
                    <button type="button" class="btn-acao-rapida" onclick="toggleTodasHoras()" <?= !$podeEditar ? 'disabled' : '' ?>>
                        <ion-icon name="checkbox-outline"></ion-icon> Selecionar Todas
                    </button>
                    <button type="button" class="btn-acao-rapida" onclick="selecionarHorasSemDados()" <?= !$podeEditar ? 'disabled' : '' ?>>
                        <ion-icon name="add-circle-outline"></ion-icon> Selecionar Horas Vazias
                    </button>
                </div>

                <!-- àârea de valores sugeridos -->
                <div class="valores-sugeridos-container" id="valoresSugeridosContainer" style="display: none;">
                    <div class="valores-sugeridos-header">
                        <h4><ion-icon name="analytics-outline"></ion-icon> Valores Sugeridos</h4>
                        <span class="valores-sugeridos-info" id="valoresSugeridosInfo"></span>
                    </div>
                    <div class="valores-sugeridos-tabela-wrapper">
                        <table class="valores-sugeridos-tabela" id="valoresSugeridosTabela">
                            <thead>
                                <tr>
                                    <th>Hora</th>
                                    <th>Valor Atual</th>
                                    <th>Média Histórica</th>
                                    <th>Fator</th>
                                    <th>Valor Sugerido</th>
                                </tr>
                            </thead>
                            <tbody id="valoresSugeridosBody">
                            </tbody>
                        </table>
                    </div>
                    <div class="valores-sugeridos-acoes">
                        <button type="button" class="btn btn-aplicar-sugeridos" onclick="aplicarValoresSugeridos()"
                            <?= !$podeEditar ? 'disabled' : '' ?>>
                            <ion-icon name="checkmark-circle-outline"></ion-icon> Aplicar Valores Sugeridos
                        </button>
                        <button type="button" class="btn btn-cancelar-sugeridos" onclick="fecharValoresSugeridos()">
                            <ion-icon name="close-circle-outline"></ion-icon> Cancelar
                        </button>
                    </div>
                </div>

                <!-- Tabela de dados horários -->
                <div class="validacao-tabela-container">
                    <table class="validacao-tabela" id="validacaoTabela">
                        <thead>
                            <tr>
                                <th style="width:100px;">
                                    <label
                                        style="display:flex;align-items:center;gap:6px;cursor:<?= !$podeEditar ? 'default' : 'pointer' ?>;">
                                        <input type="checkbox" id="checkboxTodos" onchange="toggleTodasHoras()"
                                            <?= !$podeEditar ? 'disabled' : '' ?>>
                                        Hora
                                    </label>
                                </th>
                                <th>Média</th>
                                <th>Mínimo</th>
                                <th>Máximo</th>
                                <th>Registros</th>
                            </tr>
                        </thead>
                        <tbody id="validacaoTabelaBody">
                            <!-- Preenchido via JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Formulário de validação padrão -->
                <div class="validacao-form" id="validacaoForm" style="display: none;">
                    <h4><ion-icon name="create-outline"></ion-icon> Inserir/Corrigir valores</h4>
                    <div class="validacao-form-row">
                        <div class="validacao-form-group">
                            <label>Horas Selecionadas</label>
                            <input type="text" id="validacaoHoraSelecionada" disabled>
                        </div>
                        <div class="validacao-form-group">
                            <label>Valor Atual</label>
                            <input type="text" id="validacaoValorAtual" disabled>
                        </div>
                        <div class="validacao-form-group">
                            <label>Novo Valor *</label>
                            <input type="number" id="validacaoNovoValor" step="0.01" placeholder="Digite o novo valor"
                                <?= !$podeEditar ? 'disabled' : '' ?>>
                        </div>
                    </div>
                    <div class="validacao-form-row" style="margin-top: 12px;">
                        <div class="validacao-form-group" style="flex: 2;">
                            <label>Observação (opcional)</label>
                            <textarea id="validacaoObservacao" rows="2" placeholder="Motivo da correção..."
                                <?= !$podeEditar ? 'disabled' : '' ?>></textarea>
                        </div>
                    </div>
                </div>

                <!-- Formulário de validação para Nível Reservatório (tipo 6) -->
                <div class="validacao-form" id="validacaoFormNivel" style="display: none;">
                    <h4><ion-icon name="water-outline"></ion-icon> Atualizar dados de nível</h4>
                    <div class="validacao-form-row">
                        <div class="validacao-form-group">
                            <label>Horas Selecionadas</label>
                            <input type="text" id="validacaoHoraSelecionadaNivel" disabled>
                        </div>
                        <div class="validacao-form-group">
                            <label>Min >= 100 Atual</label>
                            <input type="text" id="validacaoExtravasouAtual" disabled>
                        </div>
                        <div class="validacao-form-group">
                            <label>Minutos >= 100 *</label>
                            <input type="number" id="validacaoMinutosExtravasou" min="0" max="60" step="1"
                                placeholder="0 a 60" <?= !$podeEditar ? 'disabled' : '' ?>>
                        </div>
                    </div>
                    <div class="validacao-form-row" style="margin-top: 12px;">
                        <div class="validacao-form-group">
                            <label>Motivo *</label>
                            <div class="radio-group-validacao">
                                <label class="radio-item-validacao">
                                    <input type="radio" name="validacaoMotivo" value="1" <?= !$podeEditar ? 'disabled' : '' ?>>
                                    <span class="radio-label">Falha</span>
                                </label>
                                <label class="radio-item-validacao">
                                    <input type="radio" name="validacaoMotivo" value="2" <?= !$podeEditar ? 'disabled' : '' ?>>
                                    <span class="radio-label">Extravasou</span>
                                </label>
                            </div>
                        </div>
                        <div class="validacao-form-group" style="flex: 2;">
                            <label>Observação (opcional)</label>
                            <textarea id="validacaoObservacaoNivel" rows="2" placeholder="Observação..." <?= !$podeEditar ? 'disabled' : '' ?>></textarea>
                        </div>
                    </div>
                </div>
            </div><!-- Fim da coluna principal -->

            <!-- Coluna de IA (Direita) -->
            <div class="validacao-coluna-ia" id="iaPanelValidacao">
                <div class="ia-panel-header">
                    <div class="ia-panel-title">
                        <ion-icon name="sparkles"></ion-icon>
                        Análise Inteligente
                    </div>
                </div>

                <!-- Chat com IA -->
                <div class="ia-chat-container">
                    <div class="ia-chat-titulo">
                        <ion-icon name="chatbubbles-outline"></ion-icon>
                        Pergunte sobre os dados
                    </div>

                    <div class="ia-chat-sugestoes" id="iaChatSugestoes">
                        <button class="ia-chat-sugestao"
                            onclick="enviarPerguntaChat('Qual a média de vazão das últimas 4 semanas do mesmo dia da semana?')"
                            <?= !$podeEditar ? 'disabled' : '' ?>>Média 4 semanas</button>
                        <button class="ia-chat-sugestao" onclick="enviarPerguntaHorasSelecionadas()" <?= !$podeEditar ? 'disabled' : '' ?>>Sugerir p/ horas selecionadas</button>
                        <button class="ia-chat-sugestao"
                            onclick="enviarPerguntaChat('Há alguma anomalia ou valor suspeito nos dados?')"
                            <?= !$podeEditar ? 'disabled' : '' ?>>Detectar anomalias</button>
                    </div>

                    <div class="ia-chat-mensagens" id="iaChatMensagens"></div>

                    <div class="ia-chat-input-container">
                        <input type="text" class="ia-chat-input" id="iaChatInput"
                            placeholder="Ex: Qual a vazão média? Compare com ontem..."
                            onkeydown="if(event.key==='Enter') enviarPerguntaChat()" <?= !$podeEditar ? 'disabled' : '' ?>>
                        <button type="button" class="btn-enviar-chat" id="btnEnviarChat" onclick="enviarPerguntaChat()"
                            <?= !$podeEditar ? 'disabled' : '' ?>>
                            <ion-icon name="send"></ion-icon>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-validacao-footer">
            <button type="button" class="btn btn-sugerir" id="btnSugerirValores" onclick="buscarValoresSugeridos()"
                <?= !$podeEditar ? 'disabled' : '' ?>>
                <ion-icon name="bulb-outline"></ion-icon> Sugerir Valores
            </button>
            <div class="footer-spacer"></div>
            <button type="button" class="btn btn-cancelar" onclick="fecharModalValidacao()">Fechar</button>
            <button type="button" class="btn btn-salvar" id="btnValidar" onclick="executarValidacao()" disabled
                <?= !$podeEditar ? 'style="display:none;"' : '' ?>>
                <ion-icon name="checkmark-outline"></ion-icon> Validar Dados
            </button>
        </div>
    </div>
</div>

<!-- Loading -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
<!-- Choices.js -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<script>
    let grafico = null;
    let choicesTipo, choicesValor;
    let dadosResultado = null;
    let valorEntidadeIdSelecionado = ''; // CD_ENTIDADE_VALOR_ID para buscar pontos
    let valoresEntidadeMap = {}; // Mapa de CD_CHAVE -> CD_ENTIDADE_VALOR_ID
    let periodoDataInicio = ''; // Data início do período selecionado
    let periodoDataFim = ''; // Data fim do período selecionado

    // Variáveis para modal de validação
    let validacaoGrafico = null;
    let validacaoDadosAtuais = null;
    let validacaoHorasSelecionadas = []; // Array de horas selecionadas
    let validacaoPontoAtual = null;
    let validacaoDataAtual = null;
    let validacaoTipoMedidorAtual = null;
    let validacaoUnidadeAtual = null;

    // Controles de visibilidade do gráfico de validação
    // Controles de visibilidade do gráfico de validação
    let graficoControlesEstado = {
        principal: true,
        errorbars: true,
        sugeridos: true,
        excluidos: true,
        mediadiaria: true  // Controle para linha de média diária

    };
    let errorBarsPluginAtivo = true;

    // Mapeamento de letras por tipo de medidor
    const letrasTipoMedidor = <?= json_encode($letrasTipoMedidor) ?>;

    // Permissão de edição do usuário
    const podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;

    // Parâmetros recebidos via GET
    const paramTipo = '<?= $tipoIdGet ?>';
    const paramValor = '<?= $valorIdGet ?>';
    const paramValorEntidadeId = '<?= $valorEntidadeIdGet ?>';
    const paramPonto = '<?= $pontoIdGet ?>';
    const paramMes = '<?= $mesGet ?>';
    const paramAno = '<?= $anoGet ?>';

    // Parâmetros para abrir modal de validação automaticamente
    const paramAbrirValidacao = '<?= $abrirValidacaoGet ?>';
    const paramDataValidacao = '<?= $dataValidacaoGet ?>';
    const paramCdPontoValidacao = '<?= $cdPontoValidacaoGet ?>';

    // ============================================
    // Inicialização
    // ============================================
    document.addEventListener('DOMContentLoaded', function () {
        initChoices();
        initAutocompletePontoMedicao();

        // Se recebeu parâmetros via GET, preencher filtros e buscar dados
        if (paramTipo && paramValor) {
            setTimeout(() => {
                choicesTipo.setChoiceByValue(paramTipo);

                carregarValoresEntidade().then(() => {
                    setTimeout(() => {
                        choicesValor.setChoiceByValue(paramValor);

                        // Guardar o valorEntidadeId
                        if (paramValorEntidadeId) {
                            valorEntidadeIdSelecionado = paramValorEntidadeId;
                            document.getElementById('valorEntidadeIdHidden').value = paramValorEntidadeId;
                        }

                        // Buscar dados automaticamente após um breve delay
                        setTimeout(() => {
                            buscarDados();

                            // Se deve abrir modal de validação, aguardar dados carregarem
                            if (paramAbrirValidacao === '1' && paramDataValidacao && paramCdPontoValidacao) {
                                setTimeout(() => {
                                    abrirValidacaoPorParametros(paramCdPontoValidacao, paramDataValidacao);
                                }, 1000);
                            }
                        }, 300);
                    }, 300);
                });
            }, 100);
        }
        // Se apenas parâmetros de validação foram passados (sem tipo/valor)
        else if (paramAbrirValidacao === '1' && paramDataValidacao && paramCdPontoValidacao) {
            setTimeout(() => {
                abrirValidacaoPorParametros(paramCdPontoValidacao, paramDataValidacao);
            }, 500);
        }
    });

    // ============================================
    // Abrir modal de validação via parâmetros da URL
    // ============================================
    function abrirValidacaoPorParametros(cdPonto, data) {
        // Os campos de ano e mês já são preenchidos pelo PHP via $mesGet e $anoGet
        // Não precisa setar via JavaScript, pois o PHP já coloca o 'selected' correto

        // Buscar informações do ponto de medição e preencher autocomplete
        buscarEPreencherPontoAutocomplete(cdPonto, function (pontoInfo) {
            // Buscar dados após preencher o ponto
            buscarDados();

            // Aguardar os dados carregarem antes de abrir o modal
            setTimeout(() => {
                const tipoMedidor = pontoInfo.tipoMedidor || 1;
                const pontoNome = pontoInfo.pontoNome || 'Ponto de Medição';
                const pontoCodigo = pontoInfo.pontoCodigo || cdPonto;

                abrirModalValidacao(cdPonto, data, tipoMedidor, pontoNome, pontoCodigo);
            }, 800);
        });
    }

    // ============================================
    // Buscar ponto no autocomplete e selecionar
    // ============================================
    function buscarEPreencherPontoAutocomplete(cdPonto, callback) {
        const input = document.getElementById('filtroPontoMedicaoInput');
        const hidden = document.getElementById('filtroPontoMedicao');
        const dropdown = document.getElementById('filtroPontoMedicaoDropdown');
        const btnLimpar = document.getElementById('btnLimparPonto');

        // Buscar no autocomplete pelo código do ponto
        fetch(`bd/operacoes/getPontosMedicaoSimples.php?busca=${cdPonto}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.length > 0) {
                    // Procurar o ponto exato pelo CD_PONTO_MEDICAO
                    const pontoEncontrado = data.data.find(item =>
                        String(item.CD_PONTO_MEDICAO) === String(cdPonto)
                    );

                    if (pontoEncontrado) {
                        const letraTipo = letrasTipoMedidor[pontoEncontrado.ID_TIPO_MEDIDOR] || 'X';
                        const codigoPonto = (pontoEncontrado.CD_LOCALIDADE || '000') + '-' +
                            String(pontoEncontrado.CD_PONTO_MEDICAO).padStart(6, '0') + '-' +
                            letraTipo + '-' +
                            (pontoEncontrado.CD_UNIDADE || '00');
                        const nomePonto = pontoEncontrado.DS_NOME || '';
                        const label = `${codigoPonto} - ${nomePonto}`;

                        // Preencher o autocomplete
                        input.value = label;
                        hidden.value = cdPonto;
                        if (btnLimpar) {
                            btnLimpar.style.display = 'flex';
                        }
                        dropdown.classList.remove('active');

                        // Chamar callback com informações do ponto
                        if (callback) {
                            callback({
                                tipoMedidor: parseInt(pontoEncontrado.ID_TIPO_MEDIDOR) || 1,
                                pontoNome: nomePonto,
                                pontoCodigo: codigoPonto
                            });
                        }
                    } else {
                        // Ponto não encontrado na busca, usar valores básicos
                        preencherPontoBasico(cdPonto, callback);
                    }
                } else {
                    // Nenhum resultado, usar valores básicos
                    preencherPontoBasico(cdPonto, callback);
                }
            })
            .catch(error => {
                console.error('Erro ao buscar ponto:', error);
                preencherPontoBasico(cdPonto, callback);
            });
    }

    // ============================================
    // Preencher ponto com valores básicos (fallback)
    // ============================================
    function preencherPontoBasico(cdPonto, callback) {
        const input = document.getElementById('filtroPontoMedicaoInput');
        const hidden = document.getElementById('filtroPontoMedicao');
        const btnLimpar = document.getElementById('btnLimparPonto');

        // Buscar informações detalhadas do ponto
        $.get('bd/pontoMedicao/getDadosMedidor.php', { cd_ponto_medicao: cdPonto }, function (response) {
            if (response.success && response.data) {
                const ponto = response.data;
                const pontoCodigo = ponto.CD_CODIGO_FORMATADO || cdPonto;
                const pontoNome = ponto.DS_PONTO_MEDICAO || '';
                const label = `${pontoCodigo} - ${pontoNome}`;

                input.value = label;
                hidden.value = cdPonto;
                if (btnLimpar) {
                    btnLimpar.style.display = 'flex';
                }

                if (callback) {
                    callback({
                        tipoMedidor: parseInt(ponto.ID_TIPO_MEDIDOR) || 1,
                        pontoNome: pontoNome,
                        pontoCodigo: pontoCodigo
                    });
                }
            } else {
                input.value = `Ponto ${cdPonto}`;
                hidden.value = cdPonto;
                if (btnLimpar) {
                    btnLimpar.style.display = 'flex';
                }

                if (callback) {
                    callback({
                        tipoMedidor: 1,
                        pontoNome: 'Ponto de Medição',
                        pontoCodigo: cdPonto
                    });
                }
            }
        }, 'json').fail(function () {
            input.value = `Ponto ${cdPonto}`;
            hidden.value = cdPonto;
            if (btnLimpar) {
                btnLimpar.style.display = 'flex';
            }

            if (callback) {
                callback({
                    tipoMedidor: 1,
                    pontoNome: 'Ponto de Medição',
                    pontoCodigo: cdPonto
                });
            }
        });
    }

    // ============================================
    // Preencher dropdown de ponto de medição programaticamente
    // ============================================
    function preencherPontoMedicaoAutocomplete(value, label) {
        const input = document.getElementById('filtroPontoMedicaoInput');
        const hidden = document.getElementById('filtroPontoMedicao');
        const btnLimpar = document.getElementById('btnLimparPonto');

        if (input && hidden) {
            input.value = label;
            hidden.value = value;
            if (btnLimpar) {
                btnLimpar.style.display = 'flex';
            }
        }
    }

    // ============================================
    // Choices.js Inicialização
    // ============================================
    function initChoices() {
        // Tipo de Unidade Operacional
        choicesTipo = new Choices('#selectTipoEntidade', {
            searchEnabled: true,
            searchPlaceholderValue: 'Digite para buscar...',
            noResultsText: 'Nenhum resultado encontrado',
            noChoicesText: 'Nenhuma opção disponível',
            itemSelectText: '',
            placeholder: true,
            placeholderValue: 'Selecione o tipo de unidade...',
            shouldSort: false,
            searchResultLimit: 50
        });

        // Unidade Operacional
        choicesValor = new Choices('#selectValorEntidade', {
            searchEnabled: true,
            searchPlaceholderValue: 'Digite para buscar...',
            noResultsText: 'Nenhum resultado encontrado',
            noChoicesText: 'Selecione o tipo de unidade primeiro',
            itemSelectText: '',
            placeholder: true,
            placeholderValue: 'Selecione a unidade...',
            shouldSort: false,
            searchResultLimit: 50
        });

        // Event listener para carregar valores
        document.getElementById('selectTipoEntidade').addEventListener('change', function () {
            carregarValoresEntidade();
            limparPontoMedicao();
        });

        // Event listener para quando selecionar valor - guardar o valorEntidadeId e buscar
        const selectValorElement = document.getElementById('selectValorEntidade');

        selectValorElement.addEventListener('addItem', function (event) {
            const selectedValue = event.detail.value;

            if (selectedValue && valoresEntidadeMap[selectedValue]) {
                valorEntidadeIdSelecionado = valoresEntidadeMap[selectedValue];
                document.getElementById('valorEntidadeIdHidden').value = valorEntidadeIdSelecionado;
                // Buscar automaticamente se tiver mês e ano selecionados
                tentarBuscaAutomatica();
            } else {
                valorEntidadeIdSelecionado = '';
                document.getElementById('valorEntidadeIdHidden').value = '';
            }
            limparPontoMedicao();
        });

        // Também ouve o change para limpar quando necessário
        selectValorElement.addEventListener('change', function () {
            if (!this.value) {
                valorEntidadeIdSelecionado = '';
                document.getElementById('valorEntidadeIdHidden').value = '';
            }
        });

        // Event listeners para busca automática ao mudar mês ou ano
        document.getElementById('selectMes').addEventListener('change', function () {
            tentarBuscaAutomatica();
        });

        document.getElementById('selectAno').addEventListener('change', function () {
            tentarBuscaAutomatica();
        });
    }

    // Função para tentar busca automática se filtros estiverem preenchidos
    function tentarBuscaAutomatica() {
        const tipoId = document.getElementById('selectTipoEntidade').value;
        const valorId = document.getElementById('selectValorEntidade').value;
        const pontoId = document.getElementById('filtroPontoMedicao').value;
        const tipoPeriodo = document.querySelector('input[name="tipoPeriodo"]:checked').value;

        if (tipoPeriodo === 'mes') {
            const mes = document.getElementById('selectMes').value;
            const ano = document.getElementById('selectAno').value;

            // Busca se tem (tipo + valor OU ponto) E tem mês E ano
            if (((tipoId && valorId) || pontoId) && mes && ano) {
                buscarDados();
            }
        }
    }

    function limparPontoMedicao() {
        document.getElementById('filtroPontoMedicaoInput').value = '';
        document.getElementById('filtroPontoMedicao').value = '';
        document.getElementById('btnLimparPonto').style.display = 'none';
    }

    // ============================================
    // Autocomplete para Ponto de Medição
    // ============================================
    let autocompletePontoTimeout = null;
    let autocompletePontoIndex = -1;

    function initAutocompletePontoMedicao() {
        const input = document.getElementById('filtroPontoMedicaoInput');
        const hidden = document.getElementById('filtroPontoMedicao');
        const dropdown = document.getElementById('filtroPontoMedicaoDropdown');
        const btnLimpar = document.getElementById('btnLimparPonto');

        // Evento de foco - abre dropdown
        input.addEventListener('focus', function () {
            if (!hidden.value) {
                buscarPontosMedicaoAutocomplete('');
            }
        });

        // Evento de digitação
        input.addEventListener('input', function () {
            const termo = this.value.trim();

            // Limpa seleção anterior
            hidden.value = '';
            btnLimpar.style.display = 'none';
            autocompletePontoIndex = -1;

            // Debounce
            clearTimeout(autocompletePontoTimeout);
            autocompletePontoTimeout = setTimeout(() => {
                buscarPontosMedicaoAutocomplete(termo);
            }, 300);
        });

        // Navegação por teclado
        input.addEventListener('keydown', function (e) {
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

        // Fecha ao clicar fora
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.autocomplete-container')) {
                dropdown.classList.remove('active');
            }
        });

        // Botão limpar
        btnLimpar.addEventListener('click', function () {
            input.value = '';
            hidden.value = '';
            btnLimpar.style.display = 'none';
            dropdown.classList.remove('active');
            input.focus();
        });
    }

    function atualizarHighlightPonto(items) {
        items.forEach((item, index) => {
            if (index === autocompletePontoIndex) {
                item.classList.add('highlighted');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('highlighted');
            }
        });
    }

    function buscarPontosMedicaoAutocomplete(termo) {
        const dropdown = document.getElementById('filtroPontoMedicaoDropdown');

        dropdown.innerHTML = '<div class="autocomplete-loading">Buscando...</div>';
        dropdown.classList.add('active');

        const tipoId = document.getElementById('selectTipoEntidade').value;
        const valorId = document.getElementById('selectValorEntidade').value;

        let url;
        let params = new URLSearchParams({ busca: termo });

        // Se tem valor selecionado, filtra por CD_ENTIDADE_VALOR_ID
        if (valorEntidadeIdSelecionado) {
            params.append('valor_id', valorEntidadeIdSelecionado);
            url = `bd/operacoes/getPontosMedicaoValor.php?${params}`;
        }
        // Se tem apenas tipo selecionado, filtra por tipo
        else if (tipoId) {
            params.append('tipo_id', tipoId);
            url = `bd/operacoes/getPontosMedicaoValor.php?${params}`;
        }
        // Senão, busca todos os pontos
        else {
            url = `bd/operacoes/getPontosMedicaoSimples.php?${params}`;
        }

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.length > 0) {
                    let html = '';
                    data.data.forEach(item => {
                        const letraTipo = letrasTipoMedidor[item.ID_TIPO_MEDIDOR] || 'X';
                        const codigoPonto = (item.CD_LOCALIDADE || '000') + '-' +
                            String(item.CD_PONTO_MEDICAO).padStart(6, '0') + '-' +
                            letraTipo + '-' +
                            (item.CD_UNIDADE || '00');
                        const nomePonto = item.DS_NOME;
                        const cdPonto = item.CD_PONTO_MEDICAO;

                        html += `
                        <div class="autocomplete-item" 
                             data-value="${cdPonto}" 
                             data-label="${codigoPonto} - ${nomePonto}">
                            <span class="item-code">${codigoPonto}</span>
                            <span class="item-name">${nomePonto}</span>
                        </div>
                    `;
                    });
                    dropdown.innerHTML = html;

                    dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
                        item.addEventListener('click', function () {
                            selecionarPontoMedicaoAutocomplete(this.dataset.value, this.dataset.label);
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

    function selecionarPontoMedicaoAutocomplete(value, label) {
        const input = document.getElementById('filtroPontoMedicaoInput');
        const hidden = document.getElementById('filtroPontoMedicao');
        const dropdown = document.getElementById('filtroPontoMedicaoDropdown');
        const btnLimpar = document.getElementById('btnLimparPonto');

        input.value = label;
        hidden.value = value;
        dropdown.classList.remove('active');
        btnLimpar.style.display = 'flex';
    }

    function alternarTipoPeriodo(tipo) {
        const grid = document.getElementById('filtrosGrid');
        const grupoAno = document.getElementById('grupoAno');
        const grupoMes = document.getElementById('grupoMes');
        const grupoDataInicio = document.getElementById('grupoDataInicio');
        const grupoDataFim = document.getElementById('grupoDataFim');

        if (tipo === 'mes') {
            grupoAno.style.display = 'flex';
            grupoMes.style.display = 'flex';
            grupoDataInicio.style.display = 'none';
            grupoDataFim.style.display = 'none';
            grid.classList.remove('modo-datas');
        } else {
            grupoAno.style.display = 'none';
            grupoMes.style.display = 'none';
            grupoDataInicio.style.display = 'flex';
            grupoDataFim.style.display = 'flex';
            grid.classList.add('modo-datas');
        }
    }

    // Função para navegar entre meses (+1 ou -1) e buscar automaticamente
    function navegarMes(direcao) {
        const selectMes = document.getElementById('selectMes');
        const selectAno = document.getElementById('selectAno');

        // Se mês não selecionado, usar mês atual
        let mes = parseInt(selectMes.value);
        if (isNaN(mes) || mes === 0) {
            mes = new Date().getMonth() + 1;
        }

        let ano = parseInt(selectAno.value) || new Date().getFullYear();

        // Calcular novo mês/ano
        mes += direcao;

        // Ajustar virada de ano
        if (mes > 12) {
            mes = 1;
            ano = ano + 1;
        } else if (mes < 1) {
            mes = 12;
            ano = ano - 1;
        }

        // Verificar se o ano está disponível no select
        const anoOption = selectAno.querySelector(`option[value="${ano}"]`);
        if (!anoOption) {
            showToast('Ano fora do intervalo disponível', 'alerta');
            return;
        }

        // Atualizar valores nos selects
        selectAno.value = String(ano);
        selectMes.value = String(mes);

        // Buscar dados automaticamente se já tiver filtros preenchidos
        const tipoId = document.getElementById('selectTipoEntidade').value;
        const valorId = document.getElementById('selectValorEntidade').value;
        const pontoId = document.getElementById('filtroPontoMedicao').value;

        // Busca se tem (tipo + valor) OU se tem ponto
        if ((tipoId && valorId) || pontoId) {
            buscarDados();
        }
    }

    function alternarTab(tab) {
        document.querySelectorAll('.resultado-tab').forEach(btn => {
            btn.classList.toggle('active', btn.textContent.toLowerCase().includes(tab));
        });

        document.getElementById('tabGrafico').classList.toggle('active', tab === 'grafico');
        document.getElementById('tabTabela').classList.toggle('active', tab === 'tabela');
    }

    async function carregarValoresEntidade() {
        const tipoId = document.getElementById('selectTipoEntidade').value;

        choicesValor.clearChoices();
        choicesValor.clearStore();
        valorEntidadeIdSelecionado = '';
        valoresEntidadeMap = {};
        document.getElementById('valorEntidadeIdHidden').value = '';

        if (!tipoId) {
            choicesValor.setChoices([{ value: '', label: 'Selecione o tipo de unidade primeiro', selected: true }], 'value', 'label', true);
            return;
        }

        try {
            const response = await fetch(`bd/operacoes/getValoresEntidade.php?tipoId=${tipoId}`);
            const data = await response.json();

            if (data.success && data.valores.length > 0) {
                let options = [{ value: '', label: 'Selecione a unidade...', selected: true }];
                data.valores.forEach(valor => {
                    valoresEntidadeMap[valor.cd] = valor.id;

                    options.push({
                        value: valor.cd,
                        label: valor.id + ' - ' + valor.nome
                    });
                });
                choicesValor.setChoices(options, 'value', 'label', true);
            }
        } catch (error) {
            console.error('Erro:', error);
        }
    }

    function limparFiltros() {
        choicesTipo.setChoiceByValue('');
        choicesValor.clearChoices();
        choicesValor.clearStore();
        choicesValor.setChoices([{ value: '', label: 'Selecione o tipo de unidade primeiro', selected: true }], 'value', 'label', true);
        valorEntidadeIdSelecionado = '';
        valoresEntidadeMap = {};
        document.getElementById('valorEntidadeIdHidden').value = '';

        // Limpar autocomplete de ponto
        limparPontoMedicao();

        document.getElementById('selectAno').value = new Date().getFullYear();
        document.getElementById('selectMes').value = new Date().getMonth() + 1;
        document.getElementById('inputDataInicio').value = '';
        document.getElementById('inputDataFim').value = '';

        // Resetar radio button para "Mês"
        document.querySelector('input[name="tipoPeriodo"][value="mes"]').checked = true;
        alternarTipoPeriodo('mes');

        document.getElementById('resultadoCard').style.display = 'none';
        document.getElementById('emptyState').style.display = 'block';
    }

    async function buscarDados() {
        const tipoId = document.getElementById('selectTipoEntidade').value;
        const valorId = document.getElementById('selectValorEntidade').value;
        const pontoId = document.getElementById('filtroPontoMedicao').value;
        const ano = document.getElementById('selectAno').value;
        const tipoPeriodo = document.querySelector('input[name="tipoPeriodo"]:checked').value;

        // Validações - se não tem ponto, exige tipo e valor
        if (!pontoId) {
            if (!tipoId) {
                showToast('Selecione o tipo de unidade operacional ou um ponto de medição', 'alerta');
                return;
            }
            if (!valorId) {
                showToast('Selecione a unidade operacional ou um ponto de medição', 'alerta');
                return;
            }
        }

        let dataInicio, dataFim;

        if (tipoPeriodo === 'mes') {
            if (!ano) {
                showToast('Selecione o ano', 'alerta');
                return;
            }
            const mes = document.getElementById('selectMes').value;
            if (!mes) {
                showToast('Selecione o mês', 'alerta');
                return;
            }
            dataInicio = `${ano}-${String(mes).padStart(2, '0')}-01`;
            const ultimoDia = new Date(ano, mes, 0).getDate();
            dataFim = `${ano}-${String(mes).padStart(2, '0')}-${ultimoDia}`;
        } else {
            dataInicio = document.getElementById('inputDataInicio').value;
            dataFim = document.getElementById('inputDataFim').value;

            if (!dataInicio || !dataFim) {
                showToast('Informe as datas de início e fim', 'alerta');
                return;
            }

            // Validar período máximo de 31 dias
            const dtInicio = new Date(dataInicio);
            const dtFim = new Date(dataFim);
            const diffTime = Math.abs(dtFim - dtInicio);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            if (diffDays > 31) {
                showToast('O período máximo permitido é de 31 dias', 'alerta');
                return;
            }

            if (dtInicio > dtFim) {
                showToast('A data de início deve ser anterior à  data de fim', 'alerta');
                return;
            }
        }

        // Mostrar loading
        document.getElementById('loadingOverlay').classList.add('active');

        // Armazenar datas globalmente para uso na tabela
        periodoDataInicio = dataInicio;
        periodoDataFim = dataFim;

        try {
            // Usar o valorEntidadeIdSelecionado para buscar os dados
            const valorEntidadeId = valorEntidadeIdSelecionado || document.getElementById('valorEntidadeIdHidden').value;

            const params = new URLSearchParams({
                valorId: valorId || '',
                valorEntidadeId: valorEntidadeId || '',
                pontoId: pontoId || '',
                dataInicio: dataInicio,
                dataFim: dataFim
            });

            const response = await fetch(`bd/operacoes/getDadosOperacoes.php?${params}`);
            const data = await response.json();

            if (data.success) {
                dadosResultado = data;
                exibirResultados(data);
            } else {
                showToast(data.message || 'Erro ao buscar dados', 'erro');
            }
        } catch (error) {
            console.error('Erro:', error);
            showToast('Erro ao comunicar com o servidor', 'erro');
        } finally {
            document.getElementById('loadingOverlay').classList.remove('active');
        }
    }

    function exibirResultados(data) {
        document.getElementById('emptyState').style.display = 'none';
        document.getElementById('resultadoCard').style.display = 'block';

        // Tipo de medidor
        document.getElementById('resultadoTipoMedidor').textContent = data.tiposMedidor || '';

        // Título
        const selectValor = document.getElementById('selectValorEntidade');
        const valorNome = selectValor.options[selectValor.selectedIndex]?.text || 'Resultados';
        document.getElementById('resultadoTitulo').textContent = valorNome;

        // Info
        document.getElementById('resultadoInfo').innerHTML = `<strong>${data.dados.length}</strong> registros encontrados`;

        // Resumo
        exibirResumo(data);

        // Gráfico
        exibirGrafico(data);

        // Tabela
        exibirTabela(data);
    }

    function exibirResumo(data) {
        const resumo = data.resumo || {};
        const unidade = data.unidade || '';

        let html = `
        <div class="resumo-card">
            <div class="resumo-card-label">Total</div>
            <div class="resumo-card-value">${formatarNumero(resumo.total || 0)}</div>
            <div class="resumo-card-unit">${unidade}</div>
        </div>
        <div class="resumo-card">
            <div class="resumo-card-label">Média</div>
            <div class="resumo-card-value">${formatarNumero(resumo.media || 0)}</div>
            <div class="resumo-card-unit">${unidade}</div>
        </div>
        <div class="resumo-card">
            <div class="resumo-card-label">Mínimo</div>
            <div class="resumo-card-value">${formatarNumero(resumo.minimo || 0)}</div>
            <div class="resumo-card-unit">${unidade}</div>
        </div>
        <div class="resumo-card">
            <div class="resumo-card-label">Máximo</div>
            <div class="resumo-card-value">${formatarNumero(resumo.maximo || 0)}</div>
            <div class="resumo-card-unit">${unidade}</div>
        </div>
    `;

        document.getElementById('resumoCards').innerHTML = html;
    }

    function exibirGrafico(data) {
        const ctx = document.getElementById('graficoOperacoes').getContext('2d');

        // Destruir gráfico anterior
        if (grafico) {
            grafico.destroy();
        }

        // Separar por fluxo (entrada/saída) e por ponto
        const pontosEntrada = {};
        const pontosSaida = {};

        data.dados.forEach(item => {
            const pontoKey = item.ponto_codigo || item.ponto_nome;
            const target = item.fluxo === 'Entrada' ? pontosEntrada : pontosSaida;

            if (!target[pontoKey]) {
                target[pontoKey] = [];
            }

            // Tratar a data corretamente
            let dataObj;
            if (item.data) {
                const dataStr = item.data.split('T')[0].split(' ')[0];
                dataObj = new Date(dataStr + 'T12:00:00');
            }

            if (dataObj && !isNaN(dataObj)) {
                target[pontoKey].push({
                    x: dataObj,
                    y: parseFloat(item.valor) || 0
                });
            }
        });

        // Cores
        const coresEntrada = ['#22c55e', '#16a34a', '#15803d', '#166534', '#14532d'];
        const coresSaida = ['#ef4444', '#dc2626', '#b91c1c', '#991b1b', '#7f1d1d'];

        const datasets = [];
        let idxEntrada = 0;
        let idxSaida = 0;

        // Datasets de entrada primeiro
        Object.keys(pontosEntrada).forEach(ponto => {
            datasets.push({
                label: `⊕ ${ponto}`,
                data: pontosEntrada[ponto].sort((a, b) => a.x - b.x),
                borderColor: coresEntrada[idxEntrada % coresEntrada.length],
                backgroundColor: coresEntrada[idxEntrada % coresEntrada.length] + '20',
                fill: false,
                tension: 0.3,
                pointRadius: 3,
                pointHoverRadius: 5
            });
            idxEntrada++;
        });

        // Datasets de saída
        Object.keys(pontosSaida).forEach(ponto => {
            datasets.push({
                label: `⊖ ${ponto}`,
                data: pontosSaida[ponto].sort((a, b) => a.x - b.x),
                borderColor: coresSaida[idxSaida % coresSaida.length],
                backgroundColor: coresSaida[idxSaida % coresSaida.length] + '20',
                fill: false,
                tension: 0.3,
                pointRadius: 3,
                pointHoverRadius: 5
            });
            idxSaida++;
        });

        grafico = new Chart(ctx, {
            type: 'line',
            data: { datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return `${context.dataset.label}: ${formatarNumero(context.parsed.y)} ${data.unidade}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            displayFormats: {
                                day: 'dd/MM'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Data'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: data.unidade || 'Valor'
                        }
                    }
                }
            }
        });
    }

    function exibirTabela(data) {
        const thead = document.getElementById('tabelaHead');
        const tbody = document.getElementById('tabelaBody');

        // Gerar todos os dias do período selecionado
        const dias = gerarDiasPeriodo(periodoDataInicio, periodoDataFim);

        if (dias.length === 0) {
            thead.innerHTML = '<tr><th>Dia</th><th>Valor</th></tr>';
            tbody.innerHTML = '<tr><td colspan="2" class="sem-dados">Perà­odo inválido</td></tr>';
            return;
        }

        // Organizar dados por dia e por ponto, separando entrada e saída
        // Agora também guarda a operação de cada ponto
        const pontosEntrada = {};
        const pontosSaida = {};
        const operacoesPorPonto = {}; // { pontoKey: operacao (1=soma, 2=subtrai) }

        // Se há dados, organizar por ponto e dia
        if (data.dados && data.dados.length > 0) {
            data.dados.forEach(item => {
                // Extrair apenas a parte da data (YYYY-MM-DD)
                let dataStr = item.data;
                if (!dataStr) return;

                // Tratar diferentes formatos de data
                if (dataStr.includes('T')) {
                    dataStr = dataStr.split('T')[0];
                } else if (dataStr.includes(' ')) {
                    dataStr = dataStr.split(' ')[0];
                }

                const pontoKey = item.ponto_codigo || item.ponto_nome;
                const target = item.fluxo === 'Entrada' ? pontosEntrada : pontosSaida;

                // Guardar a operação do ponto
                if (item.operacao) {
                    operacoesPorPonto[pontoKey] = parseInt(item.operacao);
                }

                if (!target[pontoKey]) {
                    target[pontoKey] = {
                        cd_ponto: item.cd_ponto,
                        nome: item.ponto_nome,
                        codigo: item.ponto_codigo,
                        tipo_medidor: item.tipo_medidor,
                        operacao: item.operacao,
                        ordem: item.ordem || 999,
                        valores: {}
                    };
                }

                // Acumular valores por dia para calcular média
                if (!target[pontoKey].valores[dataStr]) {
                    target[pontoKey].valores[dataStr] = { soma: 0, count: 0, qtd_registros: 0, qtd_tratados: 0, horario_max: null, tipo_medidor: null, valor_min: null, valor_max: null, cd_ponto: item.cd_ponto };
                }
                target[pontoKey].valores[dataStr].soma += parseFloat(item.valor) || 0;
                target[pontoKey].valores[dataStr].count++;
                target[pontoKey].valores[dataStr].qtd_registros = item.qtd_registros || 0;
                target[pontoKey].valores[dataStr].qtd_tratados = item.qtd_tratados || 0;
                target[pontoKey].valores[dataStr].horario_max = item.horario_max || null;
                target[pontoKey].valores[dataStr].tipo_medidor = item.tipo_medidor || null;
                target[pontoKey].valores[dataStr].valor_min = item.valor_min !== undefined ? item.valor_min : null;
                target[pontoKey].valores[dataStr].valor_max = item.valor_max !== undefined ? item.valor_max : null;
            });
        }

        // Adicionar pontos que não têm dados (vieram em pontosInfo)
        if (data.pontosInfo && data.pontosInfo.length > 0) {
            data.pontosInfo.forEach(item => {
                const pontoKey = item.ponto_codigo || item.ponto_nome;
                const target = item.fluxo === 'Entrada' ? pontosEntrada : pontosSaida;

                // Guardar a operação do ponto
                if (item.operacao) {
                    operacoesPorPonto[pontoKey] = parseInt(item.operacao);
                }

                if (!target[pontoKey]) {
                    target[pontoKey] = {
                        cd_ponto: item.cd_ponto,
                        nome: item.ponto_nome,
                        codigo: item.ponto_codigo,
                        tipo_medidor: item.tipo_medidor,
                        operacao: item.operacao,
                        ordem: item.ordem || 999,
                        valores: {}
                    };
                }
            });
        }

        // Ordenar por ordem definida pelo usuário (não alfabética)
        const keysEntrada = Object.keys(pontosEntrada).sort((a, b) => {
            return (pontosEntrada[a].ordem || 999) - (pontosEntrada[b].ordem || 999);
        });
        const keysSaida = Object.keys(pontosSaida).sort((a, b) => {
            return (pontosSaida[a].ordem || 999) - (pontosSaida[b].ordem || 999);
        });

        // Se não há pontos, mostrar mensagem
        if (keysEntrada.length === 0 && keysSaida.length === 0) {
            thead.innerHTML = '<tr><th>Dia</th><th>Valor</th></tr>';
            tbody.innerHTML = '<tr><td colspan="2" class="sem-dados">Nenhum ponto de medição encontrado</td></tr>';
            return;
        }

        // Construir cabeçalho
        let headerHtml = '';

        // Calcular colspans (pontos + coluna de subtotal)
        const colspanEntrada = keysEntrada.length > 0 ? keysEntrada.length + 1 : 0;
        const colspanSaida = keysSaida.length > 0 ? keysSaida.length + 1 : 0;

        // Linha superior: grupos Entrada, Saída e Resultado
        headerHtml += '<tr><th rowspan="2" class="header-dia">Dia</th>';
        if (keysEntrada.length > 0) {
            headerHtml += `<th colspan="${colspanEntrada}" class="header-group entrada">⊕ Entrada</th>`;
        }
        if (keysSaida.length > 0) {
            headerHtml += `<th colspan="${colspanSaida}" class="header-group saida">⊖ Saída</th>`;
        }
        headerHtml += '</tr>';

        // Linha com nomes dos pontos e subtotais
        headerHtml += '<tr>';
        // Pontos de entrada
        keysEntrada.forEach(key => {
            const ponto = pontosEntrada[key];
            const op = parseInt(ponto.operacao) || 1;
            const iconeOp = op == 1 ? '<span class="icone-op soma">⊕</span>' : '<span class="icone-op subtracao">⊖</span>';
            headerHtml += `<th class="header-ponto">${iconeOp} ${ponto.codigo || ''}<br><small>${ponto.nome || ''}</small><button type="button" class="btn-grafico-popup" onclick="mostrarGraficoPopup('${key}', 'entrada')" title="Ver gráfico"><ion-icon name="stats-chart-outline"></ion-icon></button></th>`;
        });
        // Subtotal de entrada
        if (keysEntrada.length > 0) {
            headerHtml += '<th class="header-subtotal entrada">Σ Entrada</th>';
        }
        // Pontos de saída
        keysSaida.forEach(key => {
            const ponto = pontosSaida[key];
            const op = parseInt(ponto.operacao) || 1;
            const iconeOp = op == 1 ? '<span class="icone-op soma">⊕</span>' : '<span class="icone-op subtracao">⊖</span>';
            headerHtml += `<th class="header-ponto">${iconeOp} ${ponto.codigo || ''}<br><small>${ponto.nome || ''}</small><button type="button" class="btn-grafico-popup" onclick="mostrarGraficoPopup('${key}', 'saida')" title="Ver gráfico"><ion-icon name="stats-chart-outline"></ion-icon></button></th>`;
        });
        // Subtotal de saída
        if (keysSaida.length > 0) {
            headerHtml += '<th class="header-subtotal saida">Σ Saída</th>';
        }
        headerHtml += '</tr>';

        thead.innerHTML = headerHtml;

        // Guardar dados para o gráfico de hover
        window.dadosGraficoHover = {
            pontosEntrada: pontosEntrada,
            pontosSaida: pontosSaida,
            dias: dias,
            unidade: data.unidade || ''
        };

        // Construir corpo - mostrar TODOS os dias do período
        let bodyHtml = '';
        dias.forEach(diaStr => {
            // Extrair apenas o dia (DD) do formato YYYY-MM-DD
            const partes = diaStr.split('-');
            let diaNum = diaStr;
            if (partes.length === 3) {
                diaNum = parseInt(partes[2], 10);
            }

            bodyHtml += `<tr><td class="dia-col">${diaNum}</td>`;

            // Variáveis para calcular subtotais e resultado
            let subtotalEntrada = 0;
            let temValorEntrada = false;
            let subtotalSaida = 0;
            let temValorSaida = false;

            // Valores de entrada
            keysEntrada.forEach(key => {
                const ponto = pontosEntrada[key];
                const dados = ponto.valores[diaStr];
                const cdPonto = ponto.cd_ponto;
                const tipoMedidor = ponto.tipo_medidor || (dados ? dados.tipo_medidor : null);
                const pontoNome = ponto.nome || '';
                const pontoCodigo = ponto.codigo || '';

                if (dados && dados.count > 0) {
                    const media = dados.soma / dados.count;
                    // Se tipo_medidor = 6, não considera incompleto (é valor máximo)
                    const incompleto = (dados.tipo_medidor != 6 && dados.qtd_registros < 1440) ? ' incompleto' : '';
                    const tratado = dados.qtd_tratados > 0 ? ' tratado' : '';
                    // Nível crítico: tipo_medidor = 6 e valor >= 100
                    const nivelCritico = (dados.tipo_medidor == 6 && media >= 100) ? ' nivel-critico' : '';
                    let tooltipParts = [];
                    if (dados.tipo_medidor == 6 && dados.horario_max) {
                        tooltipParts.push(`Horário: ${dados.horario_max}`);
                    }
                    if (incompleto) tooltipParts.push(`Registros: ${dados.qtd_registros}/1440`);
                    if (tratado) tooltipParts.push(`Tratados: ${dados.qtd_tratados}`);
                    tooltipParts.push('Clique para validar');
                    const titulo = ` title="${tooltipParts.join(' | ')}"`;
                    const onclickAttr = cdPonto ? ` onclick="abrirModalValidacao(${cdPonto}, '${diaStr}', ${tipoMedidor || 1}, '${pontoNome.replace(/'/g, "\\'")}', '${pontoCodigo}')"` : '';
                    bodyHtml += `<td class="valor-entrada${incompleto}${tratado}${nivelCritico}"${titulo}${onclickAttr}>${formatarNumero(media)}</td>`;

                    // Aplicar operação ao subtotal de entrada
                    // Se operação não definida, assume soma (1) como padrão
                    const operacao = ponto.operacao || operacoesPorPonto[key] || 1;
                    if (operacao == 2) {
                        subtotalEntrada -= media; // Subtrai
                    } else {
                        subtotalEntrada += media; // Soma (padrão)
                    }
                    temValorEntrada = true;
                } else {
                    const onclickAttr = cdPonto ? ` onclick="abrirModalValidacao(${cdPonto}, '${diaStr}', ${tipoMedidor || 1}, '${pontoNome.replace(/'/g, "\\'")}', '${pontoCodigo}')"` : '';
                    bodyHtml += `<td class="valor-entrada sem-dados"${onclickAttr} title="Clique para inserir dados">-</td>`;
                }
            });

            // Coluna de subtotal de entrada
            if (keysEntrada.length > 0) {
                if (temValorEntrada) {
                    const classeSubtotal = subtotalEntrada >= 0 ? 'valor-subtotal entrada positivo' : 'valor-subtotal entrada negativo';
                    bodyHtml += `<td class="${classeSubtotal}">${formatarNumero(subtotalEntrada)}</td>`;
                } else {
                    bodyHtml += `<td class="valor-subtotal entrada sem-dados">-</td>`;
                }
            }

            // Valores de saída
            keysSaida.forEach(key => {
                const ponto = pontosSaida[key];
                const dados = ponto.valores[diaStr];
                const cdPonto = ponto.cd_ponto;
                const tipoMedidor = ponto.tipo_medidor || (dados ? dados.tipo_medidor : null);
                const pontoNome = ponto.nome || '';
                const pontoCodigo = ponto.codigo || '';

                if (dados && dados.count > 0) {
                    const media = dados.soma / dados.count;
                    // Se tipo_medidor = 6, não considera incompleto (é valor máximo)
                    const incompleto = (dados.tipo_medidor != 6 && dados.qtd_registros < 1440) ? ' incompleto' : '';
                    const tratado = dados.qtd_tratados > 0 ? ' tratado' : '';
                    // Nível crítico: tipo_medidor = 6 e valor >= 100
                    const nivelCritico = (dados.tipo_medidor == 6 && media >= 100) ? ' nivel-critico' : '';
                    let tooltipParts = [];
                    if (dados.tipo_medidor == 6 && dados.horario_max) {
                        tooltipParts.push(`Horário: ${dados.horario_max}`);
                    }
                    if (incompleto) tooltipParts.push(`Registros: ${dados.qtd_registros}/1440`);
                    if (tratado) tooltipParts.push(`Tratados: ${dados.qtd_tratados}`);
                    tooltipParts.push('Clique para validar');
                    const titulo = ` title="${tooltipParts.join(' | ')}"`;
                    const onclickAttr = cdPonto ? ` onclick="abrirModalValidacao(${cdPonto}, '${diaStr}', ${tipoMedidor || 1}, '${pontoNome.replace(/'/g, "\\'")}', '${pontoCodigo}')"` : '';
                    bodyHtml += `<td class="valor-saida${incompleto}${tratado}${nivelCritico}"${titulo}${onclickAttr}>${formatarNumero(media)}</td>`;

                    // Aplicar operação ao subtotal de saída
                    // Se operação não definida, assume soma (1) como padrão
                    const operacao = ponto.operacao || operacoesPorPonto[key] || 1;
                    if (operacao == 2) {
                        subtotalSaida -= media; // Subtrai
                    } else {
                        subtotalSaida += media; // Soma (padrão)
                    }
                    temValorSaida = true;
                } else {
                    const onclickAttr = cdPonto ? ` onclick="abrirModalValidacao(${cdPonto}, '${diaStr}', ${tipoMedidor || 1}, '${pontoNome.replace(/'/g, "\\'")}', '${pontoCodigo}')"` : '';
                    bodyHtml += `<td class="valor-saida sem-dados"${onclickAttr} title="Clique para inserir dados">-</td>`;
                }
            });

            // Coluna de subtotal de saída
            if (keysSaida.length > 0) {
                if (temValorSaida) {
                    const classeSubtotal = subtotalSaida >= 0 ? 'valor-subtotal saida positivo' : 'valor-subtotal saida negativo';
                    bodyHtml += `<td class="${classeSubtotal}">${formatarNumero(subtotalSaida)}</td>`;
                } else {
                    bodyHtml += `<td class="valor-subtotal saida sem-dados">-</td>`;
                }
            }

            bodyHtml += '</tr>';
        });

        tbody.innerHTML = bodyHtml;

        // Calcular estatísticas por ponto e renderizar footer
        const tfoot = document.getElementById('tabelaFoot');
        let footHtml = '';

        // Função auxiliar para calcular min/média/max/total baseado nos valores exibidos (médias diárias)
        function calcularEstatisticas(ponto, dias) {
            let mediasExibidas = [];
            let somaTotal = 0;
            dias.forEach(diaStr => {
                const dados = ponto.valores[diaStr];
                if (dados && dados.count > 0) {
                    const media = dados.soma / dados.count;
                    mediasExibidas.push(media);
                    somaTotal += media;
                }
            });

            if (mediasExibidas.length === 0) {
                return { media: '-', minimo: '-', maximo: '-', totalM3: '-' };
            }

            const minimo = Math.min(...mediasExibidas);
            const maximo = Math.max(...mediasExibidas);
            const media = mediasExibidas.reduce((a, b) => a + b, 0) / mediasExibidas.length;
            // Conversão para m³: L/s × 86.4 = m³/dia (86400 segundos / 1000 litros)
            const totalM3 = somaTotal * 86.4;

            return {
                minimo: formatarNumero(minimo),
                media: formatarNumero(media),
                maximo: formatarNumero(maximo),
                totalM3: formatarNumero(totalM3)
            };
        }

        // Linha de Mínimo
        footHtml += '<tr><td class="dia-col"><span class="stat-label">Mínimo</span></td>';
        keysEntrada.forEach(key => {
            const ponto = pontosEntrada[key];
            const stats = calcularEstatisticas(ponto, dias);
            footHtml += `<td>${stats.minimo}</td>`;
        });
        if (keysEntrada.length > 0) {
            footHtml += `<td>-</td>`; // Coluna de subtotal não tem cálculo
        }
        keysSaida.forEach(key => {
            const ponto = pontosSaida[key];
            const stats = calcularEstatisticas(ponto, dias);
            footHtml += `<td>${stats.minimo}</td>`;
        });
        if (keysSaida.length > 0) {
            footHtml += `<td>-</td>`; // Coluna de subtotal não tem cálculo
        }
        footHtml += '</tr>';

        // Linha de Média
        footHtml += '<tr><td class="dia-col"><span class="stat-label">Média</span></td>';
        keysEntrada.forEach(key => {
            const ponto = pontosEntrada[key];
            const stats = calcularEstatisticas(ponto, dias);
            footHtml += `<td>${stats.media}</td>`;
        });
        if (keysEntrada.length > 0) {
            footHtml += `<td>-</td>`;
        }
        keysSaida.forEach(key => {
            const ponto = pontosSaida[key];
            const stats = calcularEstatisticas(ponto, dias);
            footHtml += `<td>${stats.media}</td>`;
        });
        if (keysSaida.length > 0) {
            footHtml += `<td>-</td>`;
        }
        footHtml += '</tr>';

        // Linha de Máximo
        footHtml += '<tr><td class="dia-col"><span class="stat-label">Máximo</span></td>';
        keysEntrada.forEach(key => {
            const ponto = pontosEntrada[key];
            const stats = calcularEstatisticas(ponto, dias);
            footHtml += `<td>${stats.maximo}</td>`;
        });
        if (keysEntrada.length > 0) {
            footHtml += `<td>-</td>`;
        }
        keysSaida.forEach(key => {
            const ponto = pontosSaida[key];
            const stats = calcularEstatisticas(ponto, dias);
            footHtml += `<td>${stats.maximo}</td>`;
        });
        if (keysSaida.length > 0) {
            footHtml += `<td>-</td>`;
        }
        footHtml += '</tr>';

        // Linha de Total m³ do período
        let totalEntradaM3 = 0;
        let totalSaidaM3 = 0;
        footHtml += '<tr style="background:#e0f2fe;font-weight:600;"><td class="dia-col" style="background:#0284c7;color:white;"><span class="stat-label" style="color:white;">Total m³</span></td>';
        keysEntrada.forEach(key => {
            const ponto = pontosEntrada[key];
            const stats = calcularEstatisticas(ponto, dias);
            const totalM3Num = stats.totalM3 !== '-' ? parseFloat(stats.totalM3.replace(/\./g, '').replace(',', '.')) : 0;
            // Aplicar operação ao total
            const operacao = ponto.operacao || operacoesPorPonto[key] || 1;
            if (operacao == 2) {
                totalEntradaM3 -= totalM3Num;
            } else {
                totalEntradaM3 += totalM3Num;
            }
            footHtml += `<td style="color:#0369a1;">${stats.totalM3}</td>`;
        });
        if (keysEntrada.length > 0) {
            const classeTotal = totalEntradaM3 >= 0 ? 'color:#16a34a;' : 'color:#dc2626;';
            footHtml += `<td style="${classeTotal}font-weight:700;">${formatarNumero(totalEntradaM3)}</td>`;
        }
        keysSaida.forEach(key => {
            const ponto = pontosSaida[key];
            const stats = calcularEstatisticas(ponto, dias);
            const totalM3Num = stats.totalM3 !== '-' ? parseFloat(stats.totalM3.replace(/\./g, '').replace(',', '.')) : 0;
            // Aplicar operação ao total
            const operacao = ponto.operacao || operacoesPorPonto[key] || 1;
            if (operacao == 2) {
                totalSaidaM3 -= totalM3Num;
            } else {
                totalSaidaM3 += totalM3Num;
            }
            footHtml += `<td style="color:#0369a1;">${stats.totalM3}</td>`;
        });
        if (keysSaida.length > 0) {
            const classeTotal = totalSaidaM3 >= 0 ? 'color:#16a34a;' : 'color:#dc2626;';
            footHtml += `<td style="${classeTotal}font-weight:700;">${formatarNumero(totalSaidaM3)}</td>`;
        }
        footHtml += '</tr>';

        tfoot.innerHTML = footHtml;
    }

    // Função auxiliar para gerar todos os dias entre duas datas
    function gerarDiasPeriodo(dataInicio, dataFim) {
        const dias = [];

        if (!dataInicio || !dataFim) return dias;

        const inicio = new Date(dataInicio + 'T12:00:00');
        const fim = new Date(dataFim + 'T12:00:00');

        if (isNaN(inicio) || isNaN(fim) || inicio > fim) return dias;

        const atual = new Date(inicio);
        while (atual <= fim) {
            const ano = atual.getFullYear();
            const mes = String(atual.getMonth() + 1).padStart(2, '0');
            const dia = String(atual.getDate()).padStart(2, '0');
            dias.push(`${ano}-${mes}-${dia}`);
            atual.setDate(atual.getDate() + 1);
        }

        return dias;
    }

    // ============================================
    // Popup com Gráfico do Ponto
    // ============================================
    let graficoPopupInstance = null;

    function mostrarGraficoPopup(pontoKey, fluxo) {
        const dados = window.dadosGraficoHover;
        if (!dados) return;

        const pontos = fluxo === 'entrada' ? dados.pontosEntrada : dados.pontosSaida;
        const ponto = pontos[pontoKey];
        if (!ponto) return;

        // Atualizar tà­tulos
        document.getElementById('graficoPopupTitulo').textContent = ponto.nome || '-';
        document.getElementById('graficoPopupCodigo').textContent = ponto.codigo || '-';

        // Posicionar popup no centro da tela
        const popup = document.getElementById('graficoPopup');
        const popupWidth = 520;
        const popupHeight = 300;

        const left = (window.innerWidth - popupWidth) / 2;
        const top = (window.innerHeight - popupHeight) / 2;

        popup.style.left = left + 'px';
        popup.style.top = top + 'px';
        popup.classList.add('active');

        // Gerar gráfico
        gerarGraficoPopup(ponto, dados.dias, dados.unidade);
    }

    function gerarGraficoPopup(ponto, dias, unidade) {
        const ctx = document.getElementById('graficoPopupCanvas').getContext('2d');

        // Destruir gráfico anterior
        if (graficoPopupInstance) {
            graficoPopupInstance.destroy();
        }

        // Preparar dados
        const labels = [];
        const valoresMedia = [];
        const valoresMin = [];
        const valoresMax = [];

        dias.forEach(diaStr => {
            const partes = diaStr.split('-');
            const diaNum = partes.length === 3 ? parseInt(partes[2], 10) : diaStr;
            labels.push(diaNum);

            const dadosDia = ponto.valores[diaStr];
            if (dadosDia && dadosDia.count > 0) {
                const media = dadosDia.soma / dadosDia.count;
                valoresMedia.push(media);
                valoresMin.push(dadosDia.valor_min !== null ? dadosDia.valor_min : media);
                valoresMax.push(dadosDia.valor_max !== null ? dadosDia.valor_max : media);
            } else {
                valoresMedia.push(null);
                valoresMin.push(null);
                valoresMax.push(null);
            }
        });

        // Plugin para desenhar as error bars
        const errorBarsPlugin = {
            id: 'errorBars',
            afterDatasetsDraw: function (chart) {
                const ctx = chart.ctx;
                const meta = chart.getDatasetMeta(0);

                ctx.save();
                ctx.strokeStyle = '#1e3a5f';
                ctx.lineWidth = 2;

                meta.data.forEach((point, index) => {
                    if (valoresMin[index] === null || valoresMax[index] === null) return;

                    const x = point.x;
                    const yMin = chart.scales.y.getPixelForValue(valoresMin[index]);
                    const yMax = chart.scales.y.getPixelForValue(valoresMax[index]);
                    const capWidth = 6;

                    // Linha vertical (min-max)
                    ctx.beginPath();
                    ctx.moveTo(x, yMin);
                    ctx.lineTo(x, yMax);
                    ctx.stroke();

                    // Cap superior (max)
                    ctx.beginPath();
                    ctx.moveTo(x - capWidth, yMax);
                    ctx.lineTo(x + capWidth, yMax);
                    ctx.stroke();

                    // Cap inferior (min)
                    ctx.beginPath();
                    ctx.moveTo(x - capWidth, yMin);
                    ctx.lineTo(x + capWidth, yMin);
                    ctx.stroke();
                });

                ctx.restore();
            }
        };

        graficoPopupInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Média',
                    data: valoresMedia,
                    borderColor: '#dc2626',
                    backgroundColor: '#dc2626',
                    borderWidth: 2,
                    tension: 0,
                    pointRadius: 5,
                    pointBackgroundColor: '#dc2626',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7,
                    spanGaps: true,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            padding: 8,
                            font: { size: 10 },
                            generateLabels: function (chart) {
                                return [
                                    {
                                        text: 'Média',
                                        fillStyle: '#dc2626',
                                        strokeStyle: '#dc2626',
                                        lineWidth: 2,
                                        hidden: false,
                                        index: 0
                                    },
                                    {
                                        text: 'Mín/Máx',
                                        fillStyle: '#1e3a5f',
                                        strokeStyle: '#1e3a5f',
                                        lineWidth: 2,
                                        hidden: false,
                                        index: 1
                                    }
                                ];
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const idx = context.dataIndex;
                                let lines = [];
                                lines.push('Média: ' + formatarNumero(context.raw) + ' ' + unidade);
                                if (valoresMin[idx] !== null && valoresMax[idx] !== null) {
                                    lines.push('Mín: ' + formatarNumero(valoresMin[idx]) + ' ' + unidade);
                                    lines.push('Máx: ' + formatarNumero(valoresMax[idx]) + ' ' + unidade);
                                }
                                return lines;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Dia',
                            font: { size: 10 }
                        },
                        ticks: {
                            font: { size: 9 }
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: unidade,
                            font: { size: 10 }
                        },
                        ticks: {
                            font: { size: 9 },
                            callback: function (value) {
                                return formatarNumero(value);
                            }
                        },
                        beginAtZero: false
                    }
                }
            },
            plugins: [errorBarsPlugin]
        });
    }

    function fecharGraficoPopup() {
        document.getElementById('graficoPopup').classList.remove('active');
    }

    // Fechar popup ao clicar fora
    document.addEventListener('click', function (e) {
        const popup = document.getElementById('graficoPopup');
        const isButton = e.target.closest('.btn-grafico-popup');
        if (popup && !popup.contains(e.target) && !isButton) {
            fecharGraficoPopup();
        }
    });

    // Fechar popup ao pressionar ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            fecharGraficoPopup();
            fecharModalValidacao();
        }
    });

    // ============================================
    // Funções de Validação de Dados
    // ============================================

    function abrirModalValidacao(cdPonto, data, tipoMedidor, pontoNome, pontoCodigo) {
        validacaoPontoAtual = cdPonto;
        validacaoDataAtual = data;
        validacaoTipoMedidorAtual = tipoMedidor;
        validacaoHorasSelecionadas = []; // Reset array de horas

        // Fechar área de valores sugeridos (se estiver aberta)
        const valoresSugeridosContainer = document.getElementById('valoresSugeridosContainer');
        if (valoresSugeridosContainer) valoresSugeridosContainer.style.display = 'none';
        valoresSugeridosAtual = [];

        // Limpar dados da IA para recarregar
        dadosCompletosIA = null;

        // Formatar data para exibição
        const dataFormatada = data.split('-').reverse().join('/');
        const subtituloEl = document.getElementById('validacaoSubtitulo');
        if (subtituloEl) {
            subtituloEl.textContent = `${pontoCodigo} - ${pontoNome} | ${dataFormatada}`;
        }

        // Verificar se é medidor de pressão (tipo 4) - apenas visualização
        const apenasVisualizacao = tipoMedidor === 4;
        const isTipoNivel = tipoMedidor === 6;
        const desabilitarCheckbox = !podeEditar || apenasVisualizacao;

        // Mostrar/ocultar elementos de edição
        const acoesRapidas = document.getElementById('validacaoAcoesRapidas');
        if (acoesRapidas) acoesRapidas.style.display = desabilitarCheckbox ? 'none' : 'flex';

        const validacaoForm = document.getElementById('validacaoForm');
        if (validacaoForm) validacaoForm.style.display = 'none';

        const validacaoFormNivel = document.getElementById('validacaoFormNivel');
        if (validacaoFormNivel) validacaoFormNivel.style.display = 'none';

        const btnValidar = document.getElementById('btnValidar');
        if (btnValidar) btnValidar.style.display = desabilitarCheckbox ? 'none' : 'inline-flex';

        const btnSugerirValores = document.getElementById('btnSugerirValores');
        if (btnSugerirValores) btnSugerirValores.style.display = desabilitarCheckbox ? 'none' : 'inline-flex';

        // Ajustar header da tabela para modo visualização
        const headerHora = document.querySelector('#validacaoTabela thead th:first-child');
        if (headerHora) {
            if (desabilitarCheckbox) {
                headerHora.innerHTML = 'Hora';
                headerHora.style.paddingLeft = '12px';
            } else {
                headerHora.innerHTML = `<label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                <input type="checkbox" id="checkboxTodos" onchange="toggleTodasHoras()">
                Hora
            </label>`;
            }
        }

        // Atualizar tà­tulo do modal
        const tituloEl = document.getElementById('validacaoTitulo');
        if (tituloEl) {
            tituloEl.innerHTML = apenasVisualizacao
                ? '<ion-icon name="eye-outline"></ion-icon> Visualização de Dados'
                : '<ion-icon name="checkmark-circle-outline"></ion-icon> Validação de Dados';
        }

        // Atualizar mensagem de info
        let infoTexto = 'Marque uma ou mais horas na tabela para inserir/corrigir valores. Informe o novo valor e clique em "Validar" para aplicar.';
        if (apenasVisualizacao) {
            infoTexto = 'Visualização dos dados hora a hora. Medidores de pressão não permitem validação manual.';
        } else if (isTipoNivel) {
            infoTexto = 'Marque uma ou mais horas para registrar extravasamento. Informe a quantidade de minutos >= 100% e o motivo (Falha ou Extravasou).';
        }
        const infoEl = document.getElementById('validacaoInfoTexto');
        if (infoEl) infoEl.textContent = infoTexto;

        // Mostrar/ocultar checkboxes na tabela
        const tabelaValidacao = document.getElementById('validacaoTabela');
        if (tabelaValidacao) tabelaValidacao.classList.toggle('somente-leitura', apenasVisualizacao);

        // Limpar formulários
        const novoValorEl = document.getElementById('validacaoNovoValor');
        if (novoValorEl) novoValorEl.value = '';

        const observacaoEl = document.getElementById('validacaoObservacao');
        if (observacaoEl) observacaoEl.value = '';

        const minutosExtravasouEl = document.getElementById('validacaoMinutosExtravasou');
        if (minutosExtravasouEl) minutosExtravasouEl.value = '';

        const observacaoNivelEl = document.getElementById('validacaoObservacaoNivel');
        if (observacaoNivelEl) observacaoNivelEl.value = '';

        document.querySelectorAll('input[name="validacaoMotivo"]').forEach(r => r.checked = false);

        if (btnValidar) btnValidar.disabled = true;

        // Limpar chat da IA
        limparChatIA();

        // Mostrar/ocultar painel de IA (oculto para tipo 4 ou sem permissão de edição)
        const iaPanel = document.getElementById('iaPanelValidacao');
        if (iaPanel) iaPanel.style.display = (apenasVisualizacao || !podeEditar) ? 'none' : 'block';

        // Mostrar modal
        const modal = document.getElementById('modalValidacao');
        if (modal) modal.classList.add('active');

        // Carregar dados horários
        carregarDadosHorarios(cdPonto, data, tipoMedidor);
    }

    function fecharModalValidacao() {
        document.getElementById('modalValidacao').classList.remove('active');

        // Destruir gráfico
        if (validacaoGrafico) {
            validacaoGrafico.destroy();
            validacaoGrafico = null;
        }

        // Limpar dados
        validacaoDadosAtuais = null;
        validacaoHorasSelecionadas = [];

        // Fechar área de valores sugeridos
        fecharValoresSugeridos();
    }

    /**
     * Abre modal de validação e executa análise de anomalias automaticamente
     */
    function abrirValidacaoComAnalise(cdPonto, data, tipoMedidor, pontoNome, pontoCodigo) {
        // Abrir modal de validação normalmente
        abrirModalValidacao(cdPonto, data, tipoMedidor, pontoNome, pontoCodigo);

        // Aguardar o modal carregar e fazer scroll para área de IA
        setTimeout(() => {
            // Scroll para o painel de Análise Inteligente
            const iaPanel = document.getElementById('iaPanelValidacao');
            if (iaPanel) {
                iaPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            // Enviar a pergunta de análise
            setTimeout(() => {
                enviarPerguntaChat('Há alguma anomalia ou valor suspeito nos dados?');
            }, 300);
        }, 1000);
    }

    function carregarDadosHorarios(cdPonto, data, tipoMedidor) {
        const tbody = document.getElementById('validacaoTabelaBody');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;">Carregando...</td></tr>';

        // Resetar resumo - recriar HTML padrão primeiro
        const resumoContainer = document.getElementById('validacaoResumo');
        if (resumoContainer) {
            resumoContainer.innerHTML = `
            <div class="resumo-item">
                <span class="resumo-label">Mínima</span>
                <span class="resumo-valor" id="resumoMinima">-</span>
            </div>
            <div class="resumo-item">
                <span class="resumo-label">Média</span>
                <span class="resumo-valor" id="resumoMedia">-</span>
            </div>
            <div class="resumo-item">
                <span class="resumo-label">Máxima</span>
                <span class="resumo-valor" id="resumoMaxima">-</span>
            </div>
        `;
        }

        fetch(`bd/operacoes/getDadosHorarios.php?cdPonto=${cdPonto}&data=${data}&tipoMedidor=${tipoMedidor}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    validacaoDadosAtuais = data;
                    validacaoUnidadeAtual = data.unidade;
                    renderizarTabelaHoraria(data.dados, data.unidade);
                    renderizarGraficoValidacao(data.dados, data.unidade);
                    atualizarResumoDia(data.dados, data.unidade);

                    // Carregar dados históricos da IA automaticamente
                    carregarDadosHistoricosIA();
                } else {
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:#dc2626;">${data.message || 'Erro ao carregar dados'}</td></tr>`;
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#dc2626;">Erro de comunicação</td></tr>';
            });
    }

    function atualizarResumoDia(dados, unidade) {
        const isTipoNivel = validacaoTipoMedidorAtual === 6;
        const resumoContainer = document.getElementById('validacaoResumo');
        if (!resumoContainer) return;

        let minGlobal = null;
        let maxGlobal = null;
        let somaValores = 0;
        let totalRegistros = 0;
        let totalExtravasou = 0;

        dados.forEach(d => {
            if (d.qtd_registros > 0) {
                // Mínima global
                if (d.min !== null && (minGlobal === null || d.min < minGlobal)) {
                    minGlobal = d.min;
                }
                // Máxima global
                if (d.max !== null && (maxGlobal === null || d.max > maxGlobal)) {
                    maxGlobal = d.max;
                }
                // Soma para média diária - apenas se não for tipo 6
                if (!isTipoNivel && d.media !== null) {
                    somaValores += d.media * 60;
                    totalRegistros += d.qtd_registros;
                }
                // Para tipo 6, somar extravasou
                if (isTipoNivel && d.soma_extravasou !== undefined) {
                    totalExtravasou += d.soma_extravasou;
                }
            }
        });

        // Reconstruir HTML do resumo conforme o tipo
        if (isTipoNivel) {
            // Para tipo 6: Mínima, Máxima, Total Min >= 100
            resumoContainer.innerHTML = `
            <div class="resumo-item">
                <span class="resumo-label">Mínima</span>
                <span class="resumo-valor">${minGlobal !== null ? formatarNumero(minGlobal) + ' ' + unidade : '-'}</span>
            </div>
            <div class="resumo-item">
                <span class="resumo-label">Máxima</span>
                <span class="resumo-valor">${maxGlobal !== null ? formatarNumero(maxGlobal) + ' ' + unidade : '-'}</span>
            </div>
            <div class="resumo-item extravasou">
                <span class="resumo-label">Total Min >= 100</span>
                <span class="resumo-valor" style="color:#dc2626;">${totalExtravasou} min</span>
            </div>
        `;
        } else {
            // Para outros tipos: Mínima, Média, Máxima
            const mediaGlobal = somaValores > 0 ? somaValores / 1440 : null;
            resumoContainer.innerHTML = `
            <div class="resumo-item">
                <span class="resumo-label">Mínima</span>
                <span class="resumo-valor">${minGlobal !== null ? formatarNumero(minGlobal) + ' ' + unidade : '-'}</span>
            </div>
            <div class="resumo-item">
                <span class="resumo-label">Média</span>
                <span class="resumo-valor">${mediaGlobal !== null ? formatarNumero(mediaGlobal) + ' ' + unidade : '-'}</span>
            </div>
            <div class="resumo-item">
                <span class="resumo-label">Máxima</span>
                <span class="resumo-valor">${maxGlobal !== null ? formatarNumero(maxGlobal) + ' ' + unidade : '-'}</span>
            </div>
        `;
        }
    }

    function renderizarTabelaHoraria(dados, unidade) {
        const tbody = document.getElementById('validacaoTabelaBody');
        const thead = document.querySelector('#validacaoTabela thead tr');
        const apenasVisualizacao = validacaoTipoMedidorAtual === 4;
        const isTipoNivel = validacaoTipoMedidorAtual === 6;
        const desabilitarCheckbox = !podeEditar || apenasVisualizacao;

        // Atualizar cabeçalho da tabela conforme o tipo
        if (isTipoNivel && !desabilitarCheckbox) {
            // Para tipo 6: Hora, Mínimo, Máximo, Min >= 100, Registros
            thead.innerHTML = `
            <th style="width:100px;">
                <label style="display:flex;align-items:center;gap:6px;cursor:${desabilitarCheckbox ? 'default' : 'pointer'};">
                    <input type="checkbox" id="checkboxTodos" onchange="toggleTodasHoras()">
                    Hora
                </label>
            </th>
            <th>Mínimo</th>
            <th>Máximo</th>
            <th>Min >= 100</th>
            <th>Registros</th>
        `;
        } else if (isTipoNivel && desabilitarCheckbox) {
            thead.innerHTML = `
            <th style="width:100px;padding-left:12px;">Hora</th>
            <th>Mínimo</th>
            <th>Máximo</th>
            <th>Min >= 100</th>
            <th>Registros</th>
        `;
        } else if (desabilitarCheckbox) {
            thead.innerHTML = `
            <th style="width:100px;padding-left:12px;">Hora</th>
            <th>Média</th>
            <th>Mínimo</th>
            <th>Máximo</th>
            <th>Registros</th>
        `;
        } else {
            thead.innerHTML = `
            <th style="width:100px;">
                <label style="display:flex;align-items:center;gap:6px;cursor:${desabilitarCheckbox ? 'default' : 'pointer'};">
                    <input type="checkbox" id="checkboxTodos" onchange="toggleTodasHoras()">
                    Hora
                </label>
            </th>
            <th>Média</th>
            <th>Mínimo</th>
            <th>Máximo</th>
            <th>Registros</th>
        `;
        }

        // Criar array com todas as 24 horas
        const horasMap = {};
        dados.forEach(d => {
            horasMap[d.hora] = d;
        });

        let html = '';
        for (let h = 0; h < 24; h++) {
            const d = horasMap[h] || { hora: h, media: null, min: null, max: null, qtd_registros: 0, tratado: false, soma_extravasou: 0 };
            const horaStr = String(h).padStart(2, '0') + ':00';
            const temDados = d.qtd_registros > 0;
            const selecionada = validacaoHorasSelecionadas.includes(h);
            const tratado = d.tratado || false;

            if (isTipoNivel) {
                // Layout para tipo 6: Mínimo, Máximo, Min >= 100, Registros
                html += `<tr data-hora="${h}" class="${selecionada ? 'selecionada' : ''} ${tratado ? 'tratado' : ''}">
                <td class="hora-col">
                    <label style="display:flex;align-items:center;gap:6px;cursor:${desabilitarCheckbox ? 'default' : 'pointer'};">
                        ${!desabilitarCheckbox ? `<input type="checkbox" class="hora-checkbox" value="${h}" 
                               ${selecionada ? 'checked' : ''} 
                               onchange="toggleHora(${h}, ${d.soma_extravasou || 0})">` : ''}
                        ${horaStr}
                        ${tratado ? '<span class="badge-tratado" title="Dados validados">✓</span>' : ''}
                    </label>
                </td>
                <td>${temDados ? formatarNumero(d.min) + ' ' + unidade : '<span style="color:#94a3b8">-</span>'}</td>
                <td>${temDados ? formatarNumero(d.max) + ' ' + unidade : '<span style="color:#94a3b8">-</span>'}</td>
                <td style="${d.soma_extravasou > 0 ? 'color:#dc2626;font-weight:600;' : ''}">${temDados ? (d.soma_extravasou || 0) + ' min' : '<span style="color:#94a3b8">0</span>'}</td>
                <td>${d.qtd_registros > 0 ? d.qtd_registros : '<span style="color:#94a3b8">0</span>'}</td>
            </tr>`;
            } else {
                // Layout padrão: Média, Mínimo, Máximo, Registros
                html += `<tr data-hora="${h}" class="${selecionada ? 'selecionada' : ''} ${tratado ? 'tratado' : ''}">
                <td class="hora-col">
                    <label style="display:flex;align-items:center;gap:6px;cursor:${desabilitarCheckbox ? 'default' : 'pointer'};">
                        ${!desabilitarCheckbox ? `<input type="checkbox" class="hora-checkbox" value="${h}" 
                               ${selecionada ? 'checked' : ''} 
                               onchange="toggleHora(${h}, ${d.media !== null ? d.media : 'null'})">` : ''}
                        ${horaStr}
                        ${tratado ? '<span class="badge-tratado" title="Dados validados">✓</span>' : ''}
                    </label>
                </td>
                <td>${temDados ? formatarNumero(d.media) + ' ' + unidade : '<span style="color:#94a3b8">-</span>'}</td>
                <td>${temDados ? formatarNumero(d.min) + ' ' + unidade : '<span style="color:#94a3b8">-</span>'}</td>
                <td>${temDados ? formatarNumero(d.max) + ' ' + unidade : '<span style="color:#94a3b8">-</span>'}</td>
                <td>${d.qtd_registros > 0 ? d.qtd_registros : '<span style="color:#94a3b8">0</span>'}</td>
            </tr>`;
            }
        }

        tbody.innerHTML = html;

        // Atualizar cabeçalho com checkbox "selecionar todos"
        if (!desabilitarCheckbox) {
            atualizarCheckboxTodos();
        }
    }

    /**
     * Renderiza o gráfico de validação com dados horários
     * Inclui: linha principal (média/máximo), error bars (min/max), 
     * valores sugeridos, valores excluídos e média diária
     * @param {Array} dados - Array com dados horários
     * @param {string} unidade - Unidade de medida (l/s, mca, %)
     */
    function renderizarGraficoValidacao(dados, unidade) {
        const ctx = document.getElementById('validacaoGrafico').getContext('2d');
        const isTipoNivel = validacaoTipoMedidorAtual === 6;

        // Destruir gráfico existente se houver
        if (validacaoGrafico) {
            validacaoGrafico.destroy();
        }

        // Arrays para armazenar dados do gráfico
        const labels = [];
        const valoresPrincipais = []; // Média ou Máximo dependendo do tipo
        const valoresMin = [];
        const valoresMax = [];
        const coresPontos = [];
        const tratados = [];
        const valoresSugeridos = []; // Array para valores sugeridos (histórico)
        const valoresInativos = []; // Array para dados inativos (id_situacao = 2)

        // Mapear dados por hora para acesso rápido
        const horasMap = {};
        dados.forEach(d => {
            horasMap[d.hora] = d;
        });

        // Verificar se há valores sugeridos disponíveis
        let temValoresSugeridos = false;
        if (dadosCompletosIA && dadosCompletosIA.historico_por_hora) {
            temValoresSugeridos = true;
        }

        // Verificar se há dados inativos (excluídos)
        let temValoresInativos = false;

        // =====================================================
        // Calcular média diária ponderada pelos registros
        // Fórmula: SUM(media * qtd_registros) / SUM(qtd_registros)
        // =====================================================
        let somaValoresPonderados = 0;
        let totalRegistros = 0;
        for (let h = 0; h < 24; h++) {
            const d = horasMap[h];
            if (d && d.qtd_registros > 0) {
                // Para tipo nível (6), usar máximo; para outros, usar média
                const valorHora = isTipoNivel ? d.max : d.media;
                somaValoresPonderados += (valorHora * d.qtd_registros);
                totalRegistros += d.qtd_registros;
            }
        }
        // Média diária: soma ponderada dividida pelo total de registros
        const mediaDiaria = totalRegistros > 0 ? somaValoresPonderados / totalRegistros : null;
        const temMediaDiaria = mediaDiaria !== null;

        // Preencher arrays para todas as 24 horas
        for (let h = 0; h < 24; h++) {
            labels.push(String(h).padStart(2, '0') + 'h');
            const d = horasMap[h];

            if (d && d.qtd_registros > 0) {
                // Hora com dados válidos
                valoresPrincipais.push(isTipoNivel ? d.max : d.media);
                valoresMin.push(d.min);
                valoresMax.push(d.max);
                tratados.push(d.tratado || false);
                coresPontos.push(d.tratado ? '#3b82f6' : '#dc2626');
            } else {
                // Hora sem dados
                valoresPrincipais.push(null);
                valoresMin.push(null);
                valoresMax.push(null);
                tratados.push(false);
                coresPontos.push('#dc2626');
            }

            // Valores inativos (excluídos/descartados)
            if (d && d.media_inativos !== null && d.media_inativos !== undefined) {
                valoresInativos.push(d.media_inativos);
                temValoresInativos = true;
            } else {
                valoresInativos.push(null);
            }

            // Valores sugeridos baseados no histórico
            if (temValoresSugeridos) {
                const dadosHora = dadosCompletosIA.historico_por_hora.horas[h];
                if (dadosHora && dadosHora.valor_sugerido) {
                    valoresSugeridos.push(dadosHora.valor_sugerido);
                } else {
                    valoresSugeridos.push(null);
                }
            }
        }

        // Array da média diária (linha horizontal em todas as horas)
        const valoresMediaDiaria = temMediaDiaria ? Array(24).fill(mediaDiaria) : [];

        // =====================================================
        // Plugin customizado para desenhar error bars (min/max)
        // =====================================================
        const errorBarsPlugin = {
            id: 'errorBarsValidacao',
            afterDatasetsDraw: function (chart) {
                // Verificar se error bars estão ativos
                if (!errorBarsPluginAtivo) return;

                const ctx = chart.ctx;
                const meta = chart.getDatasetMeta(0);

                ctx.save();
                ctx.lineWidth = 2;

                meta.data.forEach((point, index) => {
                    if (valoresMin[index] === null || valoresMax[index] === null) return;

                    // Cor diferente para dados tratados
                    ctx.strokeStyle = tratados[index] ? '#1d4ed8' : '#1e3a5f';

                    const x = point.x;
                    const yMin = chart.scales.y.getPixelForValue(valoresMin[index]);
                    const yMax = chart.scales.y.getPixelForValue(valoresMax[index]);
                    const capWidth = 4;

                    // Linha vertical (min até max)
                    ctx.beginPath();
                    ctx.moveTo(x, yMin);
                    ctx.lineTo(x, yMax);
                    ctx.stroke();

                    // Cap superior (max)
                    ctx.beginPath();
                    ctx.moveTo(x - capWidth, yMax);
                    ctx.lineTo(x + capWidth, yMax);
                    ctx.stroke();

                    // Cap inferior (min)
                    ctx.beginPath();
                    ctx.moveTo(x - capWidth, yMin);
                    ctx.lineTo(x + capWidth, yMin);
                    ctx.stroke();
                });

                ctx.restore();
            }
        };

        // =====================================================
        // Criar gráfico Chart.js
        // =====================================================
        validacaoGrafico = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    // Dataset 0: Linha principal (Média ou Máximo)
                    {
                        label: isTipoNivel ? 'Máximo' : 'Média',
                        data: valoresPrincipais,
                        borderColor: '#dc2626',
                        backgroundColor: '#dc2626',
                        borderWidth: 2,
                        tension: 0,
                        pointRadius: 5,
                        pointBackgroundColor: coresPontos,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        spanGaps: false,
                        fill: false,
                        // Cor do segmento muda se ambos pontos são tratados
                        segment: {
                            borderColor: function (ctx) {
                                const prev = ctx.p0DataIndex;
                                const curr = ctx.p1DataIndex;
                                if (tratados[prev] && tratados[curr]) {
                                    return '#3b82f6';
                                }
                                return '#dc2626';
                            }
                        }
                    },
                    // Dataset condicional: Valores Sugeridos (histórico)
                    ...(temValoresSugeridos ? [{
                        label: 'Valores Sugeridos',
                        data: valoresSugeridos,
                        borderColor: '#16a34a',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        pointRadius: 4,
                        pointBackgroundColor: '#16a34a',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 1,
                        tension: 0.3,
                        spanGaps: true,
                        fill: false
                    }] : []),
                    // Dataset condicional: Valores Excluídos (inativos)
                    ...(temValoresInativos ? [{
                        label: 'Excluídos',
                        data: valoresInativos,
                        borderColor: '#f97316',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [3, 3],
                        pointRadius: 4,
                        pointBackgroundColor: '#f97316',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 1,
                        tension: 0,
                        spanGaps: true,
                        fill: false
                    }] : []),
                    // Dataset condicional: Média Diária (linha horizontal)
                    ...(temMediaDiaria ? [{
                        label: 'Média Diária',
                        data: valoresMediaDiaria,
                        borderColor: '#8b5cf6',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [8, 4],
                        pointRadius: 0,  // Sem pontos, apenas linha
                        tension: 0,
                        spanGaps: true,
                        fill: false
                    }] : [])
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: { size: 11 },
                            generateLabels: function (chart) {
                                return chart.data.datasets.map((dataset, i) => {
                                    return {
                                        text: dataset.label,
                                        fillStyle: dataset.borderColor || '#dc2626',
                                        hidden: false,
                                        index: i
                                    };
                                });
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const idx = context.dataIndex;
                                let lines = [];

                                // Identificar qual dataset está sendo exibido
                                const datasetLabel = context.dataset.label;

                                if (context.raw !== null) {
                                    if (datasetLabel === 'Média Diária') {
                                        // Tooltip especial para média diária
                                        lines.push('Média Diária: ' + formatarNumero(context.raw) + ' ' + unidade);
                                    } else if (datasetLabel === 'Valores Sugeridos') {
                                        lines.push('Sugerido: ' + formatarNumero(context.raw) + ' ' + unidade);
                                    } else if (datasetLabel === 'Excluídos') {
                                        lines.push('Excluído: ' + formatarNumero(context.raw) + ' ' + unidade);
                                    } else {
                                        // Dataset principal (Média ou Máximo)
                                        lines.push((isTipoNivel ? 'Máx: ' : 'Média: ') + formatarNumero(context.raw) + ' ' + unidade);
                                        if (!isTipoNivel && valoresMin[idx] !== null) {
                                            lines.push('Mín: ' + formatarNumero(valoresMin[idx]) + ' ' + unidade);
                                        }
                                        if (!isTipoNivel && valoresMax[idx] !== null) {
                                            lines.push('Máx: ' + formatarNumero(valoresMax[idx]) + ' ' + unidade);
                                        }
                                        if (isTipoNivel && valoresMin[idx] !== null) {
                                            lines.push('Mín: ' + formatarNumero(valoresMin[idx]) + ' ' + unidade);
                                        }
                                        if (tratados[idx]) {
                                            lines.push('✓ Dados validados');
                                        }
                                    }
                                }
                                return lines;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 9 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#e5e7eb' },
                        ticks: {
                            font: { size: 9 },
                            callback: function (value) {
                                return formatarNumero(value);
                            }
                        }
                    }
                }
            },
            plugins: [errorBarsPlugin]
        });

        // =====================================================
        // Atualizar controles da interface
        // =====================================================

        // Atualizar label do checkbox principal baseado no tipo de medidor
        const lblPrincipal = document.getElementById('lblLinhaPrincipal');
        if (lblPrincipal) {
            lblPrincipal.textContent = isTipoNivel ? 'Máximo' : 'Média';
        }

        // Mostrar/ocultar checkbox de valores sugeridos
        const controleValoresSugeridos = document.getElementById('controleValoresSugeridos');
        if (controleValoresSugeridos) {
            controleValoresSugeridos.style.display = temValoresSugeridos ? 'flex' : 'none';
        }

        // Mostrar/ocultar checkbox de valores excluídos
        const controleValoresExcluidos = document.getElementById('controleValoresExcluidos');
        if (controleValoresExcluidos) {
            controleValoresExcluidos.style.display = temValoresInativos ? 'flex' : 'none';
        }

        // Mostrar/ocultar checkbox de média diária (sempre visível se houver dados)
        const controleMediaDiaria = document.getElementById('controleMediaDiaria');
        if (controleMediaDiaria) {
            controleMediaDiaria.style.display = temMediaDiaria ? 'flex' : 'none';
        }

        // Resetar checkboxes para estado checked
        const chkPrincipal = document.getElementById('chkLinhaPrincipal');
        const chkErrorBars = document.getElementById('chkErrorBars');
        const chkSugeridos = document.getElementById('chkValoresSugeridos');
        const chkExcluidos = document.getElementById('chkValoresExcluidos');
        const chkMediaDiaria = document.getElementById('chkMediaDiaria');

        if (chkPrincipal) chkPrincipal.checked = true;
        if (chkErrorBars) chkErrorBars.checked = true;
        if (chkSugeridos) chkSugeridos.checked = true;
        if (chkExcluidos) chkExcluidos.checked = true;
        if (chkMediaDiaria) chkMediaDiaria.checked = true;

        // Resetar estado dos controles
        graficoControlesEstado = {
            principal: true,
            errorbars: true,
            sugeridos: true,
            excluidos: true,
            mediadiaria: true
        };
        errorBarsPluginAtivo = true;
    }

    // Função para alternar visibilidade das linhas do gráfico
    /**
    * Alterna a visibilidade das linhas do gráfico de validação
    * @param {string} tipo - Tipo do controle: 'principal', 'errorbars', 'sugeridos', 'excluidos', 'mediadiaria'
    */
    function toggleLinhaGrafico(tipo) {
        if (!validacaoGrafico) return;

        // Inverter estado do controle
        graficoControlesEstado[tipo] = !graficoControlesEstado[tipo];

        if (tipo === 'principal') {
            // Mostrar/ocultar linha principal (dataset 0)
            validacaoGrafico.data.datasets[0].hidden = !graficoControlesEstado.principal;
        } else if (tipo === 'errorbars') {
            // Controlar error bars via plugin
            errorBarsPluginAtivo = graficoControlesEstado.errorbars;
        } else if (tipo === 'sugeridos' || tipo === 'excluidos' || tipo === 'mediadiaria') {
            // Encontrar dataset pelo label
            const labelMap = {
                'sugeridos': 'Valores Sugeridos',
                'excluidos': 'Excluídos',
                'mediadiaria': 'Média Diária'
            };
            const labelBusca = labelMap[tipo];
            const datasetIndex = validacaoGrafico.data.datasets.findIndex(ds => ds.label === labelBusca);
            if (datasetIndex > -1) {
                validacaoGrafico.data.datasets[datasetIndex].hidden = !graficoControlesEstado[tipo];
            }
        }

        // Atualizar gráfico
        validacaoGrafico.update();
    }

    function toggleHora(hora, valorAtual) {
        const index = validacaoHorasSelecionadas.indexOf(hora);
        if (index > -1) {
            // Remover da seleção
            validacaoHorasSelecionadas.splice(index, 1);
        } else {
            // Adicionar à  seleção
            validacaoHorasSelecionadas.push(hora);
        }

        // Ordenar array
        validacaoHorasSelecionadas.sort((a, b) => a - b);

        // Atualizar visual da linha
        const tr = document.querySelector(`#validacaoTabelaBody tr[data-hora="${hora}"]`);
        if (tr) {
            tr.classList.toggle('selecionada', validacaoHorasSelecionadas.includes(hora));
        }

        // Mostrar/ocultar formulário correto baseado no tipo
        const isTipoNivel = validacaoTipoMedidorAtual === 6;
        if (validacaoHorasSelecionadas.length > 0) {
            if (isTipoNivel) {
                document.getElementById('validacaoForm').style.display = 'none';
                document.getElementById('validacaoFormNivel').style.display = 'block';
            } else {
                document.getElementById('validacaoForm').style.display = 'block';
                document.getElementById('validacaoFormNivel').style.display = 'none';
            }
            atualizarFormularioHoras();
        } else {
            document.getElementById('validacaoForm').style.display = 'none';
            document.getElementById('validacaoFormNivel').style.display = 'none';
        }

        atualizarCheckboxTodos();
        atualizarBotaoValidar();
    }

    function atualizarFormularioHoras() {
        const isTipoNivel = validacaoTipoMedidorAtual === 6;

        // Mostrar horas selecionadas no formulário
        const horasTexto = validacaoHorasSelecionadas.map(h =>
            String(h).padStart(2, '0') + ':00'
        ).join(', ');

        const horasDisplay = validacaoHorasSelecionadas.length === 1
            ? horasTexto + ' - ' + String(validacaoHorasSelecionadas[0]).padStart(2, '0') + ':59'
            : horasTexto + ` (${validacaoHorasSelecionadas.length} horas)`;

        if (isTipoNivel) {
            // Formulário para tipo 6 (Nível Reservatório)
            document.getElementById('validacaoHoraSelecionadaNivel').value = horasDisplay;

            // Mostrar valor atual de extravasou se houver apenas uma hora selecionada
            if (validacaoHorasSelecionadas.length === 1 && validacaoDadosAtuais) {
                const hora = validacaoHorasSelecionadas[0];
                const dadoHora = validacaoDadosAtuais.dados.find(d => d.hora === hora);
                document.getElementById('validacaoExtravasouAtual').value =
                    dadoHora ? (dadoHora.soma_extravasou || 0) + ' min' : '0 min';
            } else {
                document.getElementById('validacaoExtravasouAtual').value =
                    validacaoHorasSelecionadas.length > 1 ? 'Múltiplas horas' : '0 min';
            }
        } else {
            // Formulário padrão
            document.getElementById('validacaoHoraSelecionada').value = horasDisplay;

            // Mostrar valor atual se houver apenas uma hora selecionada
            if (validacaoHorasSelecionadas.length === 1 && validacaoDadosAtuais) {
                const hora = validacaoHorasSelecionadas[0];
                const dadoHora = validacaoDadosAtuais.dados.find(d => d.hora === hora);
                document.getElementById('validacaoValorAtual').value =
                    dadoHora && dadoHora.media !== null
                        ? formatarNumero(dadoHora.media) + ' ' + validacaoUnidadeAtual
                        : 'Sem dados';
            } else {
                document.getElementById('validacaoValorAtual').value =
                    validacaoHorasSelecionadas.length > 1 ? 'Múltiplas horas' : 'Sem dados';
            }
        }
    }

    function atualizarCheckboxTodos() {
        const checkboxTodos = document.getElementById('checkboxTodos');
        if (checkboxTodos) {
            checkboxTodos.checked = validacaoHorasSelecionadas.length === 24;
            checkboxTodos.indeterminate = validacaoHorasSelecionadas.length > 0 && validacaoHorasSelecionadas.length < 24;
        }
    }

    function toggleTodasHoras() {
        const checkboxTodos = document.getElementById('checkboxTodos');
        if (validacaoHorasSelecionadas.length === 24) {
            // Desmarcar todas
            validacaoHorasSelecionadas = [];
        } else {
            // Marcar todas
            validacaoHorasSelecionadas = Array.from({ length: 24 }, (_, i) => i);
        }

        // Atualizar visual
        document.querySelectorAll('#validacaoTabelaBody tr').forEach(tr => {
            const hora = parseInt(tr.dataset.hora);
            tr.classList.toggle('selecionada', validacaoHorasSelecionadas.includes(hora));
            const checkbox = tr.querySelector('.hora-checkbox');
            if (checkbox) checkbox.checked = validacaoHorasSelecionadas.includes(hora);
        });

        // Mostrar/ocultar formulário correto baseado no tipo
        const isTipoNivel = validacaoTipoMedidorAtual === 6;
        if (validacaoHorasSelecionadas.length > 0) {
            if (isTipoNivel) {
                document.getElementById('validacaoForm').style.display = 'none';
                document.getElementById('validacaoFormNivel').style.display = 'block';
            } else {
                document.getElementById('validacaoForm').style.display = 'block';
                document.getElementById('validacaoFormNivel').style.display = 'none';
            }
            atualizarFormularioHoras();
        } else {
            document.getElementById('validacaoForm').style.display = 'none';
            document.getElementById('validacaoFormNivel').style.display = 'none';
        }

        atualizarCheckboxTodos();
        atualizarBotaoValidar();
    }

    function selecionarHorasSemDados() {
        // Selecionar apenas horas sem registros
        validacaoHorasSelecionadas = [];

        if (validacaoDadosAtuais && validacaoDadosAtuais.dados) {
            const horasComDados = validacaoDadosAtuais.dados
                .filter(d => d.qtd_registros > 0)
                .map(d => d.hora);

            for (let h = 0; h < 24; h++) {
                if (!horasComDados.includes(h)) {
                    validacaoHorasSelecionadas.push(h);
                }
            }
        } else {
            // Se não há dados, selecionar todas
            validacaoHorasSelecionadas = Array.from({ length: 24 }, (_, i) => i);
        }

        // Atualizar visual
        document.querySelectorAll('#validacaoTabelaBody tr').forEach(tr => {
            const hora = parseInt(tr.dataset.hora);
            tr.classList.toggle('selecionada', validacaoHorasSelecionadas.includes(hora));
            const checkbox = tr.querySelector('.hora-checkbox');
            if (checkbox) checkbox.checked = validacaoHorasSelecionadas.includes(hora);
        });

        // Mostrar/ocultar formulário correto baseado no tipo
        const isTipoNivel = validacaoTipoMedidorAtual === 6;
        if (validacaoHorasSelecionadas.length > 0) {
            if (isTipoNivel) {
                document.getElementById('validacaoForm').style.display = 'none';
                document.getElementById('validacaoFormNivel').style.display = 'block';
            } else {
                document.getElementById('validacaoForm').style.display = 'block';
                document.getElementById('validacaoFormNivel').style.display = 'none';
            }
            atualizarFormularioHoras();
        } else {
            document.getElementById('validacaoForm').style.display = 'none';
            document.getElementById('validacaoFormNivel').style.display = 'none';
            showToast('Todas as horas já possuem dados', 'info');
        }

        atualizarCheckboxTodos();
        atualizarBotaoValidar();
    }

    // ==========================================
    // VALORES SUGERIDOS (HISTà“RICO + TENDàŠNCIA)
    // ==========================================

    let valoresSugeridosAtual = []; // Armazena valores sugeridos para aplicar

    /**
     * Carrega dados históricos da IA automaticamente ao abrir modal
     */
    function carregarDadosHistoricosIA() {
        if (!validacaoPontoAtual || !validacaoDataAtual) {
            return;
        }

        // Se já foram carregados, re-renderizar gráfico apenas
        if (dadosCompletosIA && dadosCompletosIA.historico_por_hora && validacaoDadosAtuais) {
            renderizarGraficoValidacao(validacaoDadosAtuais.dados, validacaoDadosAtuais.unidade);
            return;
        }

        const url = `bd/operacoes/consultarDadosIA.php?cdPonto=${validacaoPontoAtual}&data=${validacaoDataAtual}`;

        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                if (!text || text.trim() === '') {
                    return;
                }

                try {
                    const data = JSON.parse(text);

                    if (data.success) {
                        dadosCompletosIA = data.dados;
                        // Re-renderizar gráfico com os valores sugeridos
                        if (validacaoDadosAtuais) {
                            renderizarGraficoValidacao(validacaoDadosAtuais.dados, validacaoDadosAtuais.unidade);
                        }
                    }
                } catch (e) {
                    // Silenciosamente ignorar erros de carregamento histórico
                }
            })
            .catch(error => {
                // Silenciosamente ignorar erros de conexão
            });
    }

    /**
     * Busca valores sugeridos para as horas selecionadas
     */
    function buscarValoresSugeridos() {
        // Verificar se há horas selecionadas
        const checkboxes = document.querySelectorAll('#validacaoTabelaBody input[type="checkbox"]:checked');

        if (checkboxes.length === 0) {
            showToast('Selecione ao menos uma hora na tabela', 'aviso');
            return;
        }

        // Coletar as horas selecionadas
        const horasSelecionadas = [];
        checkboxes.forEach(cb => {
            const hora = parseInt(cb.value);
            if (!isNaN(hora)) {
                horasSelecionadas.push(hora);
            }
        });

        if (horasSelecionadas.length === 0) {
            showToast('Nenhuma hora válida selecionada', 'aviso');
            return;
        }

        // Verificar dados do ponto
        if (!validacaoPontoAtual || !validacaoDataAtual) {
            showToast('Dados do ponto não disponà­veis', 'erro');
            return;
        }

        // Verificar se temos os dados da IA carregados
        if (!dadosCompletosIA || !dadosCompletosIA.historico_por_hora) {
            // Buscar dados da IA
            showToast('Carregando dados históricos...', 'info');

            const url = `bd/operacoes/consultarDadosIA.php?cdPonto=${validacaoPontoAtual}&data=${validacaoDataAtual}`;

            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    return response.text();
                })
                .then(text => {
                    if (!text || text.trim() === '') {
                        showToast('Resposta vazia do servidor', 'erro');
                        return;
                    }

                    try {
                        const data = JSON.parse(text);

                        if (data.success) {
                            dadosCompletosIA = data.dados;
                            exibirValoresSugeridos(horasSelecionadas);
                        } else {
                            showToast('Erro: ' + (data.error || data.message || 'Falha ao carregar dados'), 'erro');
                        }
                    } catch (e) {
                        showToast('Erro ao processar resposta do servidor', 'erro');
                    }
                })
                .catch(error => {
                    showToast('Erro ao carregar dados: ' + error.message, 'erro');
                });
        } else {
            exibirValoresSugeridos(horasSelecionadas);
        }
    }

    /**
     * Exibe os valores sugeridos na tabela
     */
    function exibirValoresSugeridos(horasSelecionadas) {
        const container = document.getElementById('valoresSugeridosContainer');
        const tbody = document.getElementById('valoresSugeridosBody');
        const info = document.getElementById('valoresSugeridosInfo');

        if (!dadosCompletosIA || !dadosCompletosIA.historico_por_hora) {
            showToast('Dados históricos não disponà­veis', 'erro');
            return;
        }

        const histHora = dadosCompletosIA.historico_por_hora;
        const fator = histHora.fator_tendencia || 1;
        const tendenciaPct = histHora.tendencia_percentual || 0;

        // Atualizar info
        const direcao = tendenciaPct >= 0 ? 'acima' : 'abaixo';
        info.textContent = `Tendência do dia: ${Math.abs(tendenciaPct).toFixed(1)}% ${direcao} do normal (fator: ${fator.toFixed(2)})`;

        // Limpar tabela
        tbody.innerHTML = '';
        valoresSugeridosAtual = [];

        // Ordenar horas
        horasSelecionadas.sort((a, b) => a - b);

        // Preencher tabela
        horasSelecionadas.forEach(hora => {
            const dadosHora = histHora.horas[hora];

            if (!dadosHora) return;

            // Buscar valor atual da hora
            let valorAtual = '-';
            if (validacaoDadosAtuais && validacaoDadosAtuais.dados) {
                const dadoAtual = validacaoDadosAtuais.dados.find(d => d.hora === hora);
                if (dadoAtual && dadoAtual.media !== null) {
                    valorAtual = parseFloat(dadoAtual.media).toFixed(2);
                }
            }

            const mediaHist = dadosHora.media_historica || 0;
            const valorSugerido = dadosHora.valor_sugerido || 0;

            // Só adicionar se tiver dados históricos válidos
            if (mediaHist > 0) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td><strong>${String(hora).padStart(2, '0')}:00</strong></td>
                <td>${valorAtual} L/s</td>
                <td>${mediaHist.toFixed(2)} L/s</td>
                <td>×${fator.toFixed(2)}</td>
                <td class="valor-sugerido">${valorSugerido.toFixed(2)} L/s</td>
            `;
                tbody.appendChild(tr);

                // Armazenar para aplicar depois
                valoresSugeridosAtual.push({
                    hora: hora,
                    valor: valorSugerido
                });
            } else {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td><strong>${String(hora).padStart(2, '0')}:00</strong></td>
                <td>${valorAtual} L/s</td>
                <td colspan="3" style="color:#94a3b8;font-style:italic;">Sem dados históricos suficientes</td>
            `;
                tbody.appendChild(tr);
            }
        });

        // Mostrar container
        container.style.display = 'block';

        // Resetar estado dos botões
        const btnAplicar = document.querySelector('.btn-aplicar-sugeridos');
        const btnCancelar = document.querySelector('.btn-cancelar-sugeridos');
        if (btnAplicar) btnAplicar.disabled = false;
        if (btnCancelar) btnCancelar.disabled = false;

        // Scroll para o container
        container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        if (valoresSugeridosAtual.length === 0) {
            showToast('Nenhuma hora com dados históricos suficientes', 'aviso');
        }
    }

    /**
     * Aplica os valores sugeridos
     */
    function aplicarValoresSugeridos() {
        if (!valoresSugeridosAtual || valoresSugeridosAtual.length === 0) {
            showToast('Nenhum valor para aplicar', 'erro');
            return;
        }

        if (!validacaoPontoAtual || !validacaoDataAtual) {
            showToast('Dados do ponto não disponà­veis', 'erro');
            return;
        }

        // Confirmar antes de aplicar
        const qtdHoras = valoresSugeridosAtual.length;
        if (!confirm(`Tem certeza que deseja aplicar os valores sugeridos para ${qtdHoras} hora(s)?`)) {
            return;
        }

        // Desabilitar botões
        const btnAplicar = document.querySelector('.btn-aplicar-sugeridos');
        const btnCancelar = document.querySelector('.btn-cancelar-sugeridos');
        if (btnAplicar) btnAplicar.disabled = true;
        if (btnCancelar) btnCancelar.disabled = true;

        const payload = {
            cdPonto: validacaoPontoAtual,
            data: validacaoDataAtual,
            tipoMedidor: validacaoTipoMedidorAtual || 1,
            valores: valoresSugeridosAtual,
            observacao: 'Valor sugerido aplicado (histórico + tendência)'
        };

        fetch('bd/operacoes/validarDadosIA.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'sucesso');

                    // Fechar área de valores sugeridos
                    fecharValoresSugeridos();

                    // Limpar cache da IA para forçar recarregar dados atualizados
                    dadosCompletosIA = null;

                    // Desmarcar checkboxes e limpar seleção
                    limparSelecaoHoras();

                    // Esconder formulário de validação manual
                    document.getElementById('validacaoForm').style.display = 'none';
                    document.getElementById('validacaoFormNivel').style.display = 'none';

                    // Recarregar dados do modal
                    if (typeof carregarDadosHorarios === 'function') {
                        carregarDadosHorarios(validacaoPontoAtual, validacaoDataAtual);
                    }

                    // Limpar valores pendentes
                    valoresSugeridosAtual = [];

                } else {
                    showToast(data.message || 'Erro ao aplicar valores', 'erro');
                    if (btnAplicar) btnAplicar.disabled = false;
                    if (btnCancelar) btnCancelar.disabled = false;
                }
            })
            .catch(error => {
                console.error('Erro ao aplicar valores:', error);
                showToast('Erro ao aplicar valores: ' + error.message, 'erro');
                if (btnAplicar) btnAplicar.disabled = false;
                if (btnCancelar) btnCancelar.disabled = false;
            });
    }

    /**
     * Limpa a seleção de horas (checkboxes, visual, array)
     */
    function limparSelecaoHoras() {
        // Desmarcar checkboxes
        document.querySelectorAll('#validacaoTabelaBody input[type="checkbox"]:checked').forEach(cb => {
            cb.checked = false;
        });

        // Limpar array de horas selecionadas
        validacaoHorasSelecionadas = [];

        // Remover classe de seleção das linhas
        document.querySelectorAll('#validacaoTabelaBody tr.selecionada').forEach(tr => {
            tr.classList.remove('selecionada');
        });

        // Resetar checkbox "todos"
        const checkboxTodos = document.getElementById('checkboxTodos');
        if (checkboxTodos) {
            checkboxTodos.checked = false;
            checkboxTodos.indeterminate = false;
        }

        // Atualizar botão validar
        atualizarBotaoValidar();
    }

    /**
     * Fecha a área de valores sugeridos
     */
    function fecharValoresSugeridos() {
        const container = document.getElementById('valoresSugeridosContainer');
        if (container) {
            container.style.display = 'none';
        }
        valoresSugeridosAtual = [];

        // Resetar estado dos botões para próxima abertura
        const btnAplicar = document.querySelector('.btn-aplicar-sugeridos');
        const btnCancelar = document.querySelector('.btn-cancelar-sugeridos');
        if (btnAplicar) btnAplicar.disabled = false;
        if (btnCancelar) btnCancelar.disabled = false;
    }

    function atualizarBotaoValidar() {
        const isTipoNivel = validacaoTipoMedidorAtual === 6;
        const btn = document.getElementById('btnValidar');

        // Se não tem permissão de edição, sempre desabilitar
        if (!podeEditar) {
            btn.disabled = true;
            return;
        }

        if (validacaoHorasSelecionadas.length === 0) {
            btn.disabled = true;
            return;
        }

        if (isTipoNivel) {
            // Validação para tipo 6 (Nível Reservatório)
            const minutosExtravasou = document.getElementById('validacaoMinutosExtravasou').value;
            const motivo = document.querySelector('input[name="validacaoMotivo"]:checked');

            btn.disabled = minutosExtravasou === '' ||
                isNaN(parseInt(minutosExtravasou)) ||
                parseInt(minutosExtravasou) < 0 ||
                parseInt(minutosExtravasou) > 60 ||
                !motivo;
        } else {
            // Validação padrão
            const novoValor = document.getElementById('validacaoNovoValor').value;
            btn.disabled = novoValor === '' || isNaN(parseFloat(novoValor));
        }
    }

    // Listeners para habilitar/desabilitar botão
    document.getElementById('validacaoNovoValor')?.addEventListener('input', atualizarBotaoValidar);
    document.getElementById('validacaoMinutosExtravasou')?.addEventListener('input', atualizarBotaoValidar);
    document.querySelectorAll('input[name="validacaoMotivo"]').forEach(radio => {
        radio.addEventListener('change', atualizarBotaoValidar);
    });

    function executarValidacao() {
        if (validacaoHorasSelecionadas.length === 0) {
            showToast('Selecione pelo menos uma hora', 'erro');
            return;
        }

        const isTipoNivel = validacaoTipoMedidorAtual === 6;
        let payload = {
            cdPonto: validacaoPontoAtual,
            data: validacaoDataAtual,
            horas: validacaoHorasSelecionadas,
            tipoMedidor: validacaoTipoMedidorAtual
        };

        // Formatar horas para exibição
        const horasTexto = validacaoHorasSelecionadas.map(h =>
            String(h).padStart(2, '0') + ':00'
        ).join(', ');

        if (isTipoNivel) {
            // Validação para tipo 6 (Nível Reservatório)
            const minutosExtravasou = parseInt(document.getElementById('validacaoMinutosExtravasou').value);
            const motivoEl = document.querySelector('input[name="validacaoMotivo"]:checked');

            if (isNaN(minutosExtravasou) || minutosExtravasou < 0 || minutosExtravasou > 60) {
                showToast('Minutos >= 100 deve ser entre 0 e 60', 'erro');
                return;
            }

            if (!motivoEl) {
                showToast('Selecione o motivo (Falha ou Extravasou)', 'erro');
                return;
            }

            const motivo = parseInt(motivoEl.value);
            const motivoTexto = motivo === 1 ? 'Falha' : 'Extravasou';
            const observacao = document.getElementById('validacaoObservacaoNivel').value.trim();

            payload.minutosExtravasou = minutosExtravasou;
            payload.motivo = motivo;
            payload.observacao = observacao;

            // Confirmação
            const totalNovosRegistros = validacaoHorasSelecionadas.length * 60;
            if (!confirm(`Confirma a validação?\n\nHoras: ${horasTexto}\nMinutos >= 100: ${minutosExtravasou} por hora\nMotivo: ${motivoTexto}\n\nEsta ação irá:\n- Descartar registros existentes nas horas selecionadas\n- Criar ${totalNovosRegistros} novos registros (60 por hora) com Nível=100%\n- Distribuir NR_EXTRAVASOU=1 aleatoriamente em ${minutosExtravasou} registros por hora`)) {
                return;
            }
        } else {
            // Validação padrão
            const novoValor = parseFloat(document.getElementById('validacaoNovoValor').value);
            if (isNaN(novoValor)) {
                showToast('Informe um valor válido', 'erro');
                return;
            }

            const observacao = document.getElementById('validacaoObservacao').value.trim();

            payload.novoValor = novoValor;
            payload.observacao = observacao;

            // Confirmação
            const totalRegistros = validacaoHorasSelecionadas.length * 60;
            if (!confirm(`Confirma a validação?\n\nHoras: ${horasTexto}\nNovo valor: ${formatarNumero(novoValor)} ${validacaoUnidadeAtual}\n\nEsta ação irá:\n- Inativar registros existentes nas horas selecionadas\n- Criar ${totalRegistros} novos registros (60 por hora)`)) {
                return;
            }
        }

        // Desabilitar botão
        const btn = document.getElementById('btnValidar');
        btn.disabled = true;
        btn.innerHTML = '<ion-icon name="hourglass-outline"></ion-icon> Processando...';

        fetch('bd/operacoes/validarDados.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'sucesso');

                    // Recarregar dados do modal
                    carregarDadosHorarios(validacaoPontoAtual, validacaoDataAtual, validacaoTipoMedidorAtual);

                    // Limpar seleção de horas (checkboxes, visual, array)
                    limparSelecaoHoras();

                    // Esconder formulários
                    document.getElementById('validacaoForm').style.display = 'none';
                    document.getElementById('validacaoFormNivel').style.display = 'none';

                    // Limpar campos
                    if (isTipoNivel) {
                        document.getElementById('validacaoMinutosExtravasou').value = '';
                        document.getElementById('validacaoObservacaoNivel').value = '';
                        document.querySelectorAll('input[name="validacaoMotivo"]').forEach(r => r.checked = false);
                    } else {
                        document.getElementById('validacaoNovoValor').value = '';
                        document.getElementById('validacaoObservacao').value = '';
                    }

                    // Recarregar dados da tabela principal após 1 segundo
                    setTimeout(() => {
                        buscarDados();
                    }, 1000);
                } else {
                    showToast(data.message || 'Erro ao validar dados', 'erro');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro de comunicação com o servidor', 'erro');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<ion-icon name="checkmark-outline"></ion-icon> Validar Dados';
                atualizarBotaoValidar();
            });
    }

    // ==================== ANàâLISE COM IA ====================

    // ==================== CHAT COM IA ====================

    let dadosCompletosIA = null; // Cache dos dados do banco
    let iaChatHistorico = []; // Histórico de mensagens do chat

    /**
     * Lê as horas selecionadas nos checkboxes e envia pergunta para a IA
     */
    function enviarPerguntaHorasSelecionadas() {
        // Buscar checkboxes marcados
        const checkboxes = document.querySelectorAll('#validacaoTabelaBody input[type="checkbox"]:checked');

        if (checkboxes.length === 0) {
            showToast('Selecione ao menos uma hora na tabela', 'aviso');
            return;
        }

        // Coletar as horas selecionadas (usa .value do checkbox)
        const horasSelecionadas = [];
        checkboxes.forEach(cb => {
            const hora = parseInt(cb.value);
            if (!isNaN(hora)) {
                horasSelecionadas.push(hora);
            }
        });

        if (horasSelecionadas.length === 0) {
            showToast('Nenhuma hora válida selecionada', 'aviso');
            return;
        }

        // Ordenar as horas
        horasSelecionadas.sort((a, b) => a - b);

        // Formatar as horas para exibição
        const horasFormatadas = horasSelecionadas.map(h => String(h).padStart(2, '0') + ':00').join(', ');

        // Construir a pergunta
        const pergunta = `Qual o valor sugerido para as horas ${horasFormatadas}? Analise os dados das últimas 12 semanas do mesmo dia para cada hora e mostre os cálculos.`;

        // Enviar a pergunta
        enviarPerguntaChat(pergunta);
    }

    function enviarPerguntaChat(perguntaFixa = null) {
        const input = document.getElementById('iaChatInput');
        const btn = document.getElementById('btnEnviarChat');
        const mensagens = document.getElementById('iaChatMensagens');
        const sugestoes = document.getElementById('iaChatSugestoes');

        const pergunta = perguntaFixa || input.value.trim();
        if (!pergunta) return;

        // Verificar se dados foram carregados
        if (!validacaoDadosAtuais || !validacaoDadosAtuais.dados || validacaoDadosAtuais.dados.length === 0) {
            mensagens.innerHTML += `
            <div class="ia-chat-msg ia">
                <div class="ia-chat-avatar">
                    <ion-icon name="sparkles"></ion-icon>
                </div>
                <div class="ia-chat-bubble" style="background:#fef3c7;border-color:#fde68a;color:#92400e;">
                    Aguarde os dados carregarem antes de fazer perguntas.
                </div>
            </div>
        `;
            mensagens.scrollTop = mensagens.scrollHeight;
            return;
        }

        // Limpar input
        if (!perguntaFixa) input.value = '';

        // Adicionar mensagem do usuário
        mensagens.innerHTML += `
        <div class="ia-chat-msg user">
            <div class="ia-chat-avatar">
                <ion-icon name="person"></ion-icon>
            </div>
            <div class="ia-chat-bubble">${escapeHtmlChat(pergunta)}</div>
        </div>
    `;

        // Adicionar indicador de digitação
        const typingId = 'typing-' + Date.now();
        mensagens.innerHTML += `
        <div class="ia-chat-msg ia" id="${typingId}">
            <div class="ia-chat-avatar">
                <ion-icon name="sparkles"></ion-icon>
            </div>
            <div class="ia-chat-bubble">
                <div class="ia-chat-typing">
                    <span></span><span></span><span></span>
                </div>
            </div>
        </div>
    `;

        mensagens.scrollTop = mensagens.scrollHeight;
        btn.disabled = true;

        // Buscar dados completos do banco se ainda não tiver
        buscarDadosCompletosIA()
            .then(dadosCompletos => {
                // Construir contexto com dados do banco (sempre enviar)
                let contextoSistema = construirContextoCompletoChat(dadosCompletos);

                // Adicionar pergunta ao histórico
                iaChatHistorico.push({
                    role: 'user',
                    content: pergunta
                });

                // Preparar payload com histórico
                const payload = {
                    contexto: contextoSistema,
                    historico: iaChatHistorico
                };

                // Enviar para API da IA
                return fetch('bd/operacoes/analiseIA.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
            })
            .then(response => {
                return response.text();
            })
            .then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('JSON inválido: ' + text.substring(0, 200));
                }
            })
            .then(data => {
                document.getElementById(typingId)?.remove();

                if (data.success) {
                    // Adicionar resposta ao histórico
                    iaChatHistorico.push({
                        role: 'assistant',
                        content: data.resposta
                    });

                    mensagens.innerHTML += `
                    <div class="ia-chat-msg ia">
                        <div class="ia-chat-avatar">
                            <ion-icon name="sparkles"></ion-icon>
                        </div>
                        <div class="ia-chat-bubble">${formatarRespostaChat(data.resposta)}</div>
                    </div>
                `;
                } else {
                    // Remover àºltima pergunta do histórico se houve erro
                    iaChatHistorico.pop();

                    mensagens.innerHTML += `
                    <div class="ia-chat-msg ia">
                        <div class="ia-chat-avatar">
                            <ion-icon name="sparkles"></ion-icon>
                        </div>
                        <div class="ia-chat-bubble" style="background:#fee2e2;border-color:#fecaca;color:#991b1b;">
                            Erro: ${data.error || 'Falha na comunicação'}
                        </div>
                    </div>
                `;
                }

                mensagens.scrollTop = mensagens.scrollHeight;
            })
            .catch(error => {
                console.error('Erro no chat IA:', error);
                document.getElementById(typingId)?.remove();

                // Remover àºltima pergunta do histórico se houve erro
                if (iaChatHistorico.length > 0 && iaChatHistorico[iaChatHistorico.length - 1].role === 'user') {
                    iaChatHistorico.pop();
                }

                mensagens.innerHTML += `
                <div class="ia-chat-msg ia">
                    <div class="ia-chat-avatar">
                        <ion-icon name="sparkles"></ion-icon>
                    </div>
                    <div class="ia-chat-bubble" style="background:#fee2e2;border-color:#fecaca;color:#991b1b;">
                        Erro: ${error.message || 'Falha na comunicação'}
                    </div>
                </div>
            `;

                mensagens.scrollTop = mensagens.scrollHeight;
            })
            .finally(() => {
                btn.disabled = false;
                input.focus();
            });
    }

    /**
     * Busca dados completos do banco para análise
     */
    function buscarDadosCompletosIA() {
        // Verificar se temos os dados necessários
        if (!validacaoPontoAtual || !validacaoDataAtual) {
            return Promise.reject(new Error('Ponto ou data não definidos'));
        }

        // Se já tem dados em cache para o mesmo ponto/data, usar cache
        if (dadosCompletosIA &&
            dadosCompletosIA.cdPonto === validacaoPontoAtual &&
            dadosCompletosIA.data === validacaoDataAtual) {
            return Promise.resolve(dadosCompletosIA.dados);
        }

        const payload = {
            cdPonto: parseInt(validacaoPontoAtual),
            data: String(validacaoDataAtual)
        };

        return fetch('bd/operacoes/consultarDadosIA.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(response => {
                return response.text();
            })
            .then(text => {
                if (!text || text.trim() === '') {
                    throw new Error('Resposta vazia do servidor');
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('JSON inválido: ' + text.substring(0, 200));
                }
            })
            .then(data => {
                if (data.success) {
                    // Guardar em cache
                    dadosCompletosIA = {
                        cdPonto: validacaoPontoAtual,
                        data: validacaoDataAtual,
                        dados: data.dados
                    };
                    return data.dados;
                } else {
                    throw new Error(data.error || 'Erro ao buscar dados');
                }
            });
    }

    /**
     * Constrói contexto completo com dados do banco
     */
    /**
      * Constrói contexto completo com dados do banco para a IA
      * 
      * @param {Object} dados - Dados retornados pelo consultarDadosIA.php
      * @returns {string} - Contexto formatado para enviar à IA
      * 
      * @version 2.1 - Adicionado suporte a registros descartados (ID_SITUACAO)
      */
    /**
      * Constrói contexto completo com dados do banco para a IA
      * 
      * @param {Object} dados - Dados retornados pelo consultarDadosIA.php
      * @returns {string} - Contexto formatado para enviar à IA
      * 
      * @version 2.1 - Adicionado suporte a registros descartados (ID_SITUACAO)
      */
    function construirContextoCompletoChat(dados) {
        let contexto = '=== DADOS DO SISTEMA DE ABASTECIMENTO DE ÁGUA ===\n\n';

        // Função auxiliar para formatar número
        const formatNum = (val, decimais = 2) => {
            if (val === null || val === undefined || val === '') return '-';
            const num = parseFloat(val);
            return isNaN(num) ? '-' : num.toFixed(decimais);
        };

        // ==========================================
        // INFORMAÇÕES DO PONTO DE MEDIÇÃO
        // ==========================================
        if (dados.ponto && Object.keys(dados.ponto).length > 0) {
            const p = dados.ponto;
            contexto += `========================================\n`;
            contexto += `INFORMAÇÕES DO PONTO DE MEDIÇÃO\n`;
            contexto += `========================================\n`;
            contexto += `Código do Ponto: ${p.CD_PONTO_MEDICAO || 'N/A'}\n`;
            contexto += `Identificador: ${p.CD_IDENTIFICADOR || 'N/A'}\n`;
            contexto += `Nome: ${p.DS_NOME || 'N/A'}\n`;
            contexto += `Localização: ${p.DS_LOCALIZACAO || 'N/A'}\n`;
            contexto += `Unidade Operacional: ${p.UNIDADE_OPERACIONAL || 'N/A'}\n`;
            contexto += `Código Localidade: ${p.CD_LOCALIDADE || 'N/A'}\n`;
            contexto += `\n`;
            contexto += `TIPO E CONFIGURAÇÃO:\n`;
            contexto += `- Tipo de Medidor: ${p.DS_TIPO_MEDIDOR || 'N/A'} (ID: ${p.ID_TIPO_MEDIDOR || 'N/A'})\n`;
            contexto += `- Tipo de Leitura: ${p.DS_TIPO_LEITURA || 'N/A'} (ID: ${p.ID_TIPO_LEITURA || 'N/A'})\n`;
            contexto += `- Periodicidade de Leitura: ${p.OP_PERIODICIDADE_LEITURA || 'N/A'}\n`;
            contexto += `- Tipo de Instalação: ${p.TIPO_INSTALACAO_DESCRICAO || 'N/A'}\n`;
            if (p.MOTIVO_TIPO_INSTALACAO) {
                contexto += `- Motivo Tipo Instalação: ${p.MOTIVO_TIPO_INSTALACAO}\n`;
            }
            contexto += `\n`;
            contexto += `DATAS:\n`;
            contexto += `- Data de Ativação: ${p.DT_ATIVACAO_FORMATADA || 'N/A'}\n`;
            if (p.DT_DESATIVACAO_FORMATADA) {
                contexto += `- Data de Desativação: ${p.DT_DESATIVACAO_FORMATADA}\n`;
            }
            contexto += `\n`;
            contexto += `PARÂMETROS TÉCNICOS:\n`;
            if (p.VL_LIMITE_INFERIOR_VAZAO !== null && p.VL_LIMITE_INFERIOR_VAZAO !== undefined) {
                contexto += `- Limite Inferior de Vazão: ${formatNum(p.VL_LIMITE_INFERIOR_VAZAO)} L/s\n`;
            }
            if (p.VL_LIMITE_SUPERIOR_VAZAO !== null && p.VL_LIMITE_SUPERIOR_VAZAO !== undefined) {
                contexto += `- Limite Superior de Vazão: ${formatNum(p.VL_LIMITE_SUPERIOR_VAZAO)} L/s\n`;
            }
            if (p.VL_FATOR_CORRECAO_VAZAO !== null && p.VL_FATOR_CORRECAO_VAZAO !== undefined) {
                contexto += `- Fator de Correção de Vazão: ${formatNum(p.VL_FATOR_CORRECAO_VAZAO, 4)}\n`;
            }
            contexto += `\n`;
            contexto += `TAGS SCADA:\n`;
            if (p.DS_TAG_VAZAO) contexto += `- Tag Vazão: ${p.DS_TAG_VAZAO}\n`;
            if (p.DS_TAG_PRESSAO) contexto += `- Tag Pressão: ${p.DS_TAG_PRESSAO}\n`;
            if (p.DS_TAG_VOLUME) contexto += `- Tag Volume: ${p.DS_TAG_VOLUME}\n`;
            if (p.DS_TAG_RESERVATORIO) contexto += `- Tag Reservatório: ${p.DS_TAG_RESERVATORIO}\n`;
            if (p.DS_TAG_TEMP_AGUA) contexto += `- Tag Temp. Água: ${p.DS_TAG_TEMP_AGUA}\n`;
            if (p.DS_TAG_TEMP_AMBIENTE) contexto += `- Tag Temp. Ambiente: ${p.DS_TAG_TEMP_AMBIENTE}\n`;
            contexto += `\n`;
            contexto += `INFORMAÇÕES COMPLEMENTARES:\n`;
            if (p.VL_QUANTIDADE_LIGACOES) {
                contexto += `- Quantidade de Ligações: ${p.VL_QUANTIDADE_LIGACOES}\n`;
            }
            if (p.VL_QUANTIDADE_ECONOMIAS) {
                contexto += `- Quantidade de Economias: ${p.VL_QUANTIDADE_ECONOMIAS}\n`;
            }
            if (p.CD_ESTACAO_PITOMETRICA) {
                contexto += `- Estação Pitométrica: ${p.CD_ESTACAO_PITOMETRICA}\n`;
            }
            if (p.COORDENADAS) {
                contexto += `- Coordenadas: ${p.COORDENADAS}\n`;
            }
            if (p.LOC_INST_SAP) {
                contexto += `- Local Instalação SAP: ${p.LOC_INST_SAP}\n`;
            }
            if (p.RESPONSAVEL_NOME) {
                contexto += `- Responsável: ${p.RESPONSAVEL_NOME}\n`;
            }
            if (p.DS_OBSERVACAO) {
                contexto += `- Observações: ${p.DS_OBSERVACAO}\n`;
            }
            contexto += `========================================\n\n`;
        }

        // Data atual
        const dataFormatada = validacaoDataAtual ? validacaoDataAtual.split('-').reverse().join('/') : 'N/A';
        contexto += `DATA EM ANÁLISE: ${dataFormatada}\n\n`;

        // ==========================================
        // DADOS HORÁRIOS DO DIA (com descartados)
        // ==========================================
        if (dados.dia_atual && dados.dia_atual.length > 0) {
            let totalRegistrosDia = 0;
            let totalValidosDia = 0;
            let totalDescartadosDia = 0;
            let somaVazaoDia = 0;
            let somaPressaoDia = 0;
            let horasComVazao = 0;
            let horasComPressao = 0;
            let horasComDescarte = [];

            contexto += `DADOS HORÁRIOS DO DIA:\n`;
            contexto += `Hora  | Válidos | Descart. | Média Vazão | Min     | Máx\n`;
            contexto += `------|---------|----------|-------------|---------|--------\n`;

            dados.dia_atual.forEach(h => {
                const hora = String(h.HORA).padStart(2, '0') + ':00';
                const mediaVazao = parseFloat(h.MEDIA_VAZAO) || 0;

                // Suporte a novo formato com válidos/descartados
                const qtdValidos = parseInt(h.QTD_VALIDOS) || parseInt(h.QTD_REGISTROS) || 0;
                const qtdDescartados = parseInt(h.QTD_DESCARTADOS) || 0;
                const qtdTotal = parseInt(h.QTD_REGISTROS_TOTAL) || (qtdValidos + qtdDescartados);

                const somaHora = parseFloat(h.SOMA_VAZAO) || (mediaVazao * 60);
                const minVazao = formatNum(h.MIN_VAZAO);
                const maxVazao = formatNum(h.MAX_VAZAO);

                totalRegistrosDia += qtdTotal;
                totalValidosDia += qtdValidos;
                totalDescartadosDia += qtdDescartados;
                somaVazaoDia += somaHora;

                if (mediaVazao > 0) horasComVazao++;

                if (h.MEDIA_PRESSAO !== null) {
                    somaPressaoDia += (parseFloat(h.MEDIA_PRESSAO) || 0) * 60;
                    horasComPressao++;
                }

                // Marcar horas com descarte
                const marcaDescarte = qtdDescartados > 0 ? ' ⚠️' : '';
                if (qtdDescartados > 0) {
                    horasComDescarte.push({ hora: hora, qtd: qtdDescartados });
                }

                contexto += `${hora} | ${String(qtdValidos).padStart(7)} | ${String(qtdDescartados).padStart(8)} | ${formatNum(mediaVazao).padStart(11)} | ${minVazao.padStart(7)} | ${maxVazao}${marcaDescarte}\n`;
            });

            // Usar cálculos do backend (já calculados corretamente)
            const calculos = dados.calculos || {};
            const mediaDiariaVazao = calculos.media_diaria_vazao || (somaVazaoDia / 1440);
            const mediaDiariaPressao = calculos.media_diaria_pressao || 0;
            const somaTotalVazao = calculos.soma_total_vazao || somaVazaoDia;
            const totalValidos = calculos.total_validos || totalValidosDia;
            const totalDescartados = calculos.total_descartados || totalDescartadosDia;
            const horasComDescarteBackend = calculos.horas_com_descarte || horasComDescarte;

            contexto += `\n========================================\n`;
            contexto += `RESUMO DO DIA (VALORES OFICIAIS):\n`;
            contexto += `========================================\n`;
            contexto += `- Total de registros: ${calculos.total_registros || totalRegistrosDia}\n`;
            contexto += `- Registros válidos (ID_SITUACAO=1): ${totalValidos}\n`;
            contexto += `- Registros descartados/corrigidos (ID_SITUACAO=2): ${totalDescartados}\n`;

            // Se houve descarte, informar as horas
            if (totalDescartados > 0) {
                contexto += `\n⚠️ HORAS COM REGISTROS DESCARTADOS:\n`;
                if (horasComDescarteBackend && horasComDescarteBackend.length > 0) {
                    horasComDescarteBackend.forEach(h => {
                        const horaFmt = h.hora_formatada || h.hora;
                        const qtd = h.qtd_descartados || h.qtd;
                        contexto += `  - ${horaFmt}: ${qtd} registro(s) descartado(s)\n`;
                    });
                } else if (horasComDescarte.length > 0) {
                    horasComDescarte.forEach(h => {
                        contexto += `  - ${h.hora}: ${h.qtd} registro(s) descartado(s)\n`;
                    });
                }
                contexto += `  NOTA: Descartados são registros revisados pelo operador e NÃO entram nos cálculos de média.\n`;
            }

            contexto += `\n- Horas com dados válidos: ${calculos.horas_com_dados || dados.dia_atual.length}/24\n`;
            contexto += `- Soma total de vazões (válidos): ${formatNum(somaTotalVazao)} L/s\n`;
            contexto += `\n`;
            contexto += `>>> MÉDIA DIÁRIA DE VAZÃO: ${formatNum(mediaDiariaVazao)} L/s <<<\n`;
            contexto += `    (Cálculo oficial: ${formatNum(somaTotalVazao)} ÷ 1440 = ${formatNum(mediaDiariaVazao)})\n`;
            contexto += `    (Usa APENAS registros válidos - ID_SITUACAO=1)\n`;
            if (mediaDiariaPressao > 0) {
                contexto += `>>> MÉDIA DIÁRIA DE PRESSÃO: ${formatNum(mediaDiariaPressao)} mca <<<\n`;
            }
            contexto += `\n`;
            contexto += `IMPORTANTE: Use EXATAMENTE o valor ${formatNum(mediaDiariaVazao)} L/s como média diária.\n`;
            contexto += `========================================\n`;
            contexto += '\n';
        }

        // ==========================================
        // ESTATÍSTICAS DO MÊS
        // ==========================================
        if (dados.estatisticas_mes) {
            const e = dados.estatisticas_mes;
            contexto += `ESTATÍSTICAS DO MÊS (${e.MES_REFERENCIA || 'N/A'}):\n`;
            contexto += `- Dias com dados: ${e.DIAS_COM_DADOS || 0}\n`;
            contexto += `- Total de registros no mês: ${e.TOTAL_REGISTROS || 0}\n`;
            if (e.TOTAL_VALIDOS !== undefined) {
                contexto += `- Registros válidos: ${e.TOTAL_VALIDOS || 0}\n`;
                contexto += `- Registros descartados: ${e.TOTAL_DESCARTADOS || 0}\n`;
            }
            if (e.MEDIA_VAZAO_MES !== null && e.MEDIA_VAZAO_MES !== undefined) {
                contexto += `- Vazão média mensal: ${formatNum(e.MEDIA_VAZAO_MES)} L/s\n`;
                contexto += `- Vazão mínima: ${formatNum(e.MIN_VAZAO_MES)} L/s\n`;
                contexto += `- Vazão máxima: ${formatNum(e.MAX_VAZAO_MES)} L/s\n`;
            }
            if (e.MEDIA_PRESSAO_MES !== null && e.MEDIA_PRESSAO_MES !== undefined && parseFloat(e.MEDIA_PRESSAO_MES) > 0) {
                contexto += `- Pressão média mensal: ${formatNum(e.MEDIA_PRESSAO_MES)} mca\n`;
            }
            if (parseInt(e.TOTAL_EXTRAVASOU_MES) > 0) {
                contexto += `- Total de minutos com extravasamento: ${e.TOTAL_EXTRAVASOU_MES}\n`;
            }
            contexto += '\n';
        }

        // ==========================================
        // HISTÓRICO DOS ÚLTIMOS 7 DIAS
        // ==========================================
        if (dados.historico_7dias && dados.historico_7dias.length > 0) {
            contexto += `HISTÓRICO DOS ÚLTIMOS 7 DIAS:\n`;
            contexto += `Data       | Válidos | Descart. | Média Vazão | Vazão Mín | Vazão Máx\n`;
            contexto += `-----------|---------|----------|-------------|-----------|----------\n`;

            dados.historico_7dias.forEach(d => {
                const data = d.DATA || 'N/A';
                const validos = d.QTD_VALIDOS || d.QTD_REGISTROS || '0';
                const descartados = d.QTD_DESCARTADOS || '0';
                const vazaoMedia = formatNum(d.MEDIA_VAZAO);
                const vazaoMin = formatNum(d.MIN_VAZAO);
                const vazaoMax = formatNum(d.MAX_VAZAO);
                contexto += `${data} | ${String(validos).padStart(7)} | ${String(descartados).padStart(8)} | ${vazaoMedia.padStart(11)} | ${vazaoMin.padStart(9)} | ${vazaoMax}\n`;
            });
            contexto += '\n';
        }

        // ==========================================
        // MÉDIA DO MESMO DIA DA SEMANA
        // ==========================================
        if (dados.media_mesmo_dia_semana && dados.media_mesmo_dia_semana.media_geral_vazao !== null) {
            const m = dados.media_mesmo_dia_semana;
            contexto += `COMPARATIVO - MÉDIA DO MESMO DIA DA SEMANA (últimas ${m.semanas_analisadas} semanas):\n`;
            contexto += `- Vazão média esperada: ${formatNum(m.media_geral_vazao)} L/s\n`;
            if (m.media_geral_pressao !== null && m.media_geral_pressao !== 0) {
                contexto += `- Pressão média esperada: ${formatNum(m.media_geral_pressao)} mca\n`;
            }
            contexto += '\n';
        }

        // ==========================================
        // HISTÓRICO DO MESMO DIA DA SEMANA (para cálculos)
        // ==========================================
        if (dados.historico_mesmo_dia && dados.historico_mesmo_dia.medias_por_dia) {
            const hist = dados.historico_mesmo_dia;
            const diasComDados = hist.medias_por_dia.filter(d => d.tem_dados);

            contexto += `========================================\n`;
            contexto += `HISTÓRICO DO MESMO DIA DA SEMANA (${hist.dia_semana}):\n`;
            contexto += `========================================\n`;
            contexto += `Semanas com dados disponíveis: ${hist.semanas_disponiveis}\n\n`;
            contexto += `DADOS POR SEMANA (use para calcular médias):\n`;

            hist.medias_por_dia.forEach((dia, idx) => {
                if (dia.tem_dados) {
                    const descInfo = dia.total_descartados > 0 ? ` [${dia.total_descartados} descartados]` : '';
                    contexto += `  Semana ${idx + 1} (${dia.data_formatada}): ${formatNum(dia.media_vazao)} L/s${descInfo}\n`;
                }
            });

            contexto += `\nPARA CALCULAR MÉDIA DE X SEMANAS:\n`;
            contexto += `- Some as médias das X primeiras semanas com dados\n`;
            contexto += `- Divida pela quantidade de semanas somadas\n`;
            contexto += `- Apresente o cálculo detalhado quando solicitado\n`;
            contexto += `========================================\n\n`;
        }

        // ==========================================
        // HISTÓRICO POR HORA (para sugestões específicas)
        // ==========================================
        if (dados.historico_por_hora && dados.historico_por_hora.horas) {
            const histHora = dados.historico_por_hora;

            contexto += `========================================\n`;
            contexto += `ANÁLISE PARA SUGESTÃO DE VALORES:\n`;
            contexto += `========================================\n`;
            contexto += `Dia da semana: ${histHora.dia_semana}\n`;
            contexto += `Semanas analisadas: ${histHora.semanas_analisadas}\n\n`;

            // Fator de tendência
            const fator = histHora.fator_tendencia || 1;
            const tendenciaPct = histHora.tendencia_percentual || 0;
            const horasUsadas = histHora.horas_usadas_tendencia || 0;

            contexto += `FATOR DE TENDÊNCIA DO DIA ATUAL:\n`;
            if (horasUsadas >= 3) {
                const direcao = tendenciaPct >= 0 ? 'acima' : 'abaixo';
                contexto += `- Baseado em ${horasUsadas} horas com dados válidos\n`;
                contexto += `- O dia atual está ${Math.abs(tendenciaPct).toFixed(1)}% ${direcao} do padrão histórico\n`;
                contexto += `- Fator de ajuste: ${fator.toFixed(4)}\n\n`;
            } else {
                contexto += `- Dados insuficientes para calcular tendência (mínimo 3 horas)\n`;
                contexto += `- Usando fator = 1.0 (sem ajuste)\n\n`;
            }

            contexto += `FÓRMULA: valor_sugerido = média_histórica × fator_tendência\n\n`;

            contexto += `DADOS POR HORA:\n`;
            contexto += `Hora  | Média Histórica | Fator | Valor Sugerido | Valor Atual\n`;
            contexto += `------|-----------------|-------|----------------|------------\n`;

            for (let hora = 0; hora < 24; hora++) {
                const dadosHora = histHora.horas[hora];
                if (dadosHora && dadosHora.semanas_com_dados > 0) {
                    const horaFmt = dadosHora.hora_formatada;
                    const mediaHist = formatNum(dadosHora.media_historica);
                    const valorSug = formatNum(dadosHora.valor_sugerido);
                    const valorAtual = dadosHora.valor_dia_atual ? formatNum(dadosHora.valor_dia_atual) : '-';

                    contexto += `${horaFmt} | ${mediaHist.padStart(15)} | ${fator.toFixed(2).padStart(5)} | ${valorSug.padStart(14)} | ${valorAtual}\n`;
                }
            }

            contexto += `\n`;
            contexto += `DETALHAMENTO POR SEMANA (últimas ${histHora.semanas_analisadas} semanas):\n`;

            for (let hora = 0; hora < 24; hora++) {
                const dadosHora = histHora.horas[hora];
                if (dadosHora && dadosHora.semanas_com_dados > 0) {
                    contexto += `\nHORA ${dadosHora.hora_formatada}:\n`;
                    dadosHora.valores_por_semana.forEach(sem => {
                        if (sem.tem_dados) {
                            contexto += `  - ${sem.data_formatada}: ${formatNum(sem.media_vazao)} L/s\n`;
                        }
                    });
                    contexto += `  → Média histórica: ${formatNum(dadosHora.media_historica)} L/s\n`;
                    contexto += `  → Com ajuste (×${fator.toFixed(2)}): ${formatNum(dadosHora.valor_sugerido)} L/s\n`;
                }
            }
            contexto += `\n========================================\n\n`;
        }

        // ==========================================
        // ALERTAS DETECTADOS
        // ==========================================
        if (dados.alertas && dados.alertas.length > 0) {
            contexto += `ALERTAS DETECTADOS:\n`;
            dados.alertas.forEach(a => {
                const icone = a.severidade === 'alta' ? '[CRÍTICO]' : (a.severidade === 'media' ? '[ATENÇÃO]' : '[INFO]');
                contexto += `${icone} ${a.mensagem}\n`;
            });
            contexto += '\n';
        }

        return contexto;
    }

    function escapeHtmlChat(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatarRespostaChat(texto) {
        // Verificar se há bloco de aplicar valores
        const regexAplicar = /\[APLICAR_VALORES\]([\s\S]*?)\[\/APLICAR_VALORES\]/;
        const matchAplicar = texto.match(regexAplicar);

        if (matchAplicar) {
            // Extrair os valores do bloco
            const blocoValores = matchAplicar[1].trim();
            const linhas = blocoValores.split('\n').filter(l => l.trim());

            // Parsear valores (formato: HH:00=VALOR)
            const valoresParaAplicar = [];
            linhas.forEach(linha => {
                const match = linha.match(/(\d{1,2}):00\s*=\s*([\d.]+)/);
                if (match) {
                    valoresParaAplicar.push({
                        hora: parseInt(match[1]),
                        valor: parseFloat(match[2])
                    });
                }
            });

            // Armazenar valores globalmente para uso no botão
            window.valoresIAParaAplicar = valoresParaAplicar;

            // Remover o bloco do texto e adicionar botão
            texto = texto.replace(regexAplicar, '');
            texto = texto.replace('Aguarde enquanto os dados são atualizados...', '');

            // Criar resumo dos valores
            let resumoHtml = '<div class="ia-valores-aplicar">';
            resumoHtml += '<strong>Valores a serem aplicados:</strong><br>';
            valoresParaAplicar.forEach(v => {
                resumoHtml += `â€¢ ${String(v.hora).padStart(2, '0')}:00 â†’ <strong>${v.valor.toFixed(2)} L/s</strong><br>`;
            });
            resumoHtml += '<br><button class="btn-aplicar-valores-ia" onclick="aplicarValoresIA()">✓ Confirmar e Aplicar</button>';
            resumoHtml += ' <button class="btn-cancelar-valores-ia" onclick="cancelarValoresIA()">✗ Cancelar</button>';
            resumoHtml += '</div>';

            texto += resumoHtml;
        }

        // Escapar HTML (exceto o que acabamos de adicionar)
        const partes = texto.split(/(<div class="ia-valores-aplicar">[\s\S]*?<\/div>)/);
        texto = partes.map((parte, i) => {
            if (parte.startsWith('<div class="ia-valores-aplicar">')) {
                return parte; // Não escapar o HTML dos botões
            }
            return escapeHtmlChat(parte);
        }).join('');

        // Negrito **texto**
        texto = texto.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');

        // Itálico *texto*
        texto = texto.replace(/\*([^*]+)\*/g, '<em>$1</em>');

        // Quebras de linha
        texto = texto.replace(/\n/g, '<br>');

        return texto;
    }

    /**
     * Aplica os valores sugeridos pela IA
     */
    function aplicarValoresIA() {
        if (!window.valoresIAParaAplicar || window.valoresIAParaAplicar.length === 0) {
            showToast('Nenhum valor para aplicar', 'erro');
            return;
        }

        if (!validacaoPontoAtual || !validacaoDataAtual) {
            showToast('Dados do ponto não disponà­veis', 'erro');
            return;
        }

        // Desabilitar botões
        const btnAplicar = document.querySelector('.btn-aplicar-valores-ia');
        const btnCancelar = document.querySelector('.btn-cancelar-valores-ia');
        if (btnAplicar) btnAplicar.disabled = true;
        if (btnCancelar) btnCancelar.disabled = true;

        const payload = {
            cdPonto: validacaoPontoAtual,
            data: validacaoDataAtual,
            tipoMedidor: validacaoTipoMedidorAtual || 1,
            valores: window.valoresIAParaAplicar,
            observacao: 'Valor sugerido e aplicado via IA'
        };

        fetch('bd/operacoes/validarDadosIA.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'sucesso');

                    // Adicionar mensagem de sucesso no chat
                    const mensagens = document.getElementById('iaChatMensagens');
                    if (mensagens) {
                        mensagens.innerHTML += `
                    <div class="ia-chat-msg ia">
                        <div class="ia-chat-avatar">
                            <ion-icon name="checkmark-circle"></ion-icon>
                        </div>
                        <div class="ia-chat-bubble" style="background:#dcfce7;border-color:#bbf7d0;color:#166534;">
                            ✓ ${data.message}
                        </div>
                    </div>
                `;
                        mensagens.scrollTop = mensagens.scrollHeight;
                    }

                    // Remover botões
                    const divBotoes = document.querySelector('.ia-valores-aplicar');
                    if (divBotoes) {
                        divBotoes.innerHTML = '<span style="color:#166534;">✓ Valores aplicados com sucesso!</span>';
                    }

                    // Limpar cache da IA
                    dadosCompletosIA = null;

                    // Desmarcar checkboxes e limpar seleção
                    limparSelecaoHoras();

                    // Recarregar dados do modal
                    if (typeof carregarDadosHorarios === 'function') {
                        carregarDadosHorarios(validacaoPontoAtual, validacaoDataAtual);
                    }

                    // Limpar valores pendentes
                    window.valoresIAParaAplicar = null;

                } else {
                    showToast(data.message || 'Erro ao aplicar valores', 'erro');
                    if (btnAplicar) btnAplicar.disabled = false;
                    if (btnCancelar) btnCancelar.disabled = false;
                }
            })
            .catch(error => {
                console.error('Erro ao aplicar valores:', error);
                showToast('Erro ao aplicar valores: ' + error.message, 'erro');
                if (btnAplicar) btnAplicar.disabled = false;
                if (btnCancelar) btnCancelar.disabled = false;
            });
    }

    /**
     * Cancela a aplicação dos valores
     */
    function cancelarValoresIA() {
        window.valoresIAParaAplicar = null;

        const divBotoes = document.querySelector('.ia-valores-aplicar');
        if (divBotoes) {
            divBotoes.innerHTML = '<span style="color:#991b1b;">✗ Operação cancelada</span>';
        }

        // Adicionar mensagem no chat
        const mensagens = document.getElementById('iaChatMensagens');
        if (mensagens) {
            mensagens.innerHTML += `
            <div class="ia-chat-msg ia">
                <div class="ia-chat-avatar">
                    <ion-icon name="close-circle"></ion-icon>
                </div>
                <div class="ia-chat-bubble" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">
                    Operação cancelada. Os dados não foram alterados.
                </div>
            </div>
        `;
            mensagens.scrollTop = mensagens.scrollHeight;
        }
    }

    function limparChatIA() {
        const mensagens = document.getElementById('iaChatMensagens');
        const sugestoes = document.getElementById('iaChatSugestoes');
        if (mensagens) mensagens.innerHTML = '';
        if (sugestoes) sugestoes.style.display = 'flex';
        dadosCompletosIA = null; // Limpar cache de dados do banco
        iaChatHistorico = []; // Limpar histórico de conversa
    }

    function formatarNumero(valor) {
        if (valor === null || valor === undefined) return '-';
        return parseFloat(valor).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // =====================================================
    // MODAL DE ANàâLISE IA
    // =====================================================

    let estruturaAnaliseIA = null;
    let analiseIACache = {};

    /**
     * Abre o modal de análise IA
     */
    function abrirModalAnaliseIA() {
        const tipoId = document.getElementById('selectTipoEntidade').value;
        const valorId = document.getElementById('selectValorEntidade').value;

        if (!tipoId || !valorId) {
            showToast('Selecione o tipo e a unidade operacional primeiro', 'aviso');
            return;
        }

        // Verificar se temos o CD_ENTIDADE_VALOR_ID
        const valorEntidadeId = valorEntidadeIdSelecionado || document.getElementById('valorEntidadeIdHidden').value || valoresEntidadeMap[valorId];

        if (!valorEntidadeId) {
            showToast('Não foi possà­vel identificar a unidade operacional. Selecione novamente.', 'erro');
            return;
        }

        // Obter nomes selecionados para o subtà­tulo
        const selectTipo = document.getElementById('selectTipoEntidade');
        const selectValor = document.getElementById('selectValorEntidade');
        const tipoNome = selectTipo.options[selectTipo.selectedIndex]?.text || '-';
        const valorNome = selectValor.options[selectValor.selectedIndex]?.text || '-';

        // Preencher subtà­tulo
        const subtituloEl = document.getElementById('analiseIASubtitulo');
        if (subtituloEl) {
            subtituloEl.textContent = `${tipoNome} | ${valorNome}`;
        }

        // Mostrar modal
        document.getElementById('modalAnaliseIA').classList.add('active');

        // Carregar estrutura
        carregarEstruturaAnaliseIA();
    }

    /**
     * Fecha o modal de análise IA
     */
    function fecharModalAnaliseIA() {
        document.getElementById('modalAnaliseIA').classList.remove('active');
    }

    /**
     * Carrega estrutura hierárquica para análise
     */
    function carregarEstruturaAnaliseIA() {
        const body = document.getElementById('analiseIABody');
        const valorCdChave = document.getElementById('selectValorEntidade').value;

        // Tentar várias fontes para obter o CD_ENTIDADE_VALOR_ID
        let valorEntidadeId = valorEntidadeIdSelecionado
            || document.getElementById('valorEntidadeIdHidden').value
            || valoresEntidadeMap[valorCdChave]
            || '';

        if (!valorEntidadeId) {
            body.innerHTML = `
            <div class="analise-vazia">
                <ion-icon name="alert-circle-outline"></ion-icon>
                <p>Não foi possà­vel identificar a unidade operacional</p>
            </div>
        `;
            return;
        }

        // Obter período filtrado
        let dataInicio, dataFim;
        const tipoPeriodo = document.querySelector('input[name="tipoPeriodo"]:checked')?.value || 'mes';

        if (tipoPeriodo === 'mes') {
            const ano = document.getElementById('selectAno').value;
            const mes = document.getElementById('selectMes').value;
            if (ano && mes) {
                dataInicio = `${ano}-${String(mes).padStart(2, '0')}-01`;
                const ultimoDia = new Date(ano, mes, 0).getDate();
                dataFim = `${ano}-${String(mes).padStart(2, '0')}-${String(ultimoDia).padStart(2, '0')}`;
            } else {
                const hoje = new Date();
                dataInicio = `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}-01`;
                const ultimoDia = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0).getDate();
                dataFim = `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}-${String(ultimoDia).padStart(2, '0')}`;
            }
        } else {
            dataInicio = document.getElementById('inputDataInicio').value || new Date().toISOString().split('T')[0];
            dataFim = document.getElementById('inputDataFim').value || dataInicio;
        }

        body.innerHTML = `
        <div class="analise-loading">
            <ion-icon name="sync-outline"></ion-icon>
            <span>Carregando estrutura...</span>
        </div>
    `;

        fetch(`bd/operacoes/getEstruturaAnaliseIA.php?valorEntidadeId=${encodeURIComponent(valorEntidadeId)}&dataInicio=${dataInicio}&dataFim=${dataFim}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    estruturaAnaliseIA = data.estrutura;
                    renderizarEstruturaAnaliseIA(data.estrutura, dataInicio, dataFim);
                } else {
                    body.innerHTML = `
                    <div class="analise-vazia">
                        <ion-icon name="alert-circle-outline"></ion-icon>
                        <p>${data.error || 'Erro ao carregar estrutura'}</p>
                    </div>
                `;
                }
            })
            .catch(error => {
                body.innerHTML = `
                <div class="analise-vazia">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    <p>Erro de conexão: ${error.message}</p>
                </div>
            `;
            });
    }

    /**
     * Renderiza a estrutura hierárquica em acordeões
     */
    function renderizarEstruturaAnaliseIA(estrutura, dataInicio, dataFim) {
        const body = document.getElementById('analiseIABody');

        if (!estrutura || estrutura.length === 0) {
            body.innerHTML = `
            <div class="analise-vazia">
                <ion-icon name="folder-open-outline"></ion-icon>
                <p>Nenhuma estrutura encontrada para esta unidade operacional</p>
            </div>
        `;
            return;
        }

        let html = '';

        // Como geralmente temos apenas 1 tipo e 1 valor, já abrir expandido
        const abrirTipoAutomatico = estrutura.length === 1;

        estrutura.forEach((tipo, tipoIdx) => {
            const totalPontos = tipo.valores.reduce((acc, v) => acc + v.pontos.length, 0);
            const classeAbertoTipo = abrirTipoAutomatico ? ' aberto' : '';

            html += `
            <div class="acordeao-tipo${classeAbertoTipo}" id="acordeaoTipo${tipoIdx}">
                <div class="acordeao-tipo-header" onclick="toggleAcordeaoTipo(${tipoIdx})">
                    <ion-icon name="chevron-forward-outline" class="icone-toggle"></ion-icon>
                    <ion-icon name="folder-outline" class="icone-tipo"></ion-icon>
                    <h4>${tipo.nome}</h4>
                    <span class="badge-count">${totalPontos} pontos</span>
                </div>
                <div class="acordeao-tipo-content">
        `;

            // Se tipo está aberto e tem apenas 1 valor, também abrir o valor
            const abrirValorAutomatico = abrirTipoAutomatico && tipo.valores.length === 1;

            tipo.valores.forEach((valor, valorIdx) => {
                const classeAbertoValor = abrirValorAutomatico ? ' aberto' : '';

                html += `
                <div class="acordeao-valor${classeAbertoValor}" id="acordeaoValor${tipoIdx}_${valorIdx}">
                    <div class="acordeao-valor-header" onclick="toggleAcordeaoValor(${tipoIdx}, ${valorIdx})">
                        <ion-icon name="chevron-forward-outline" class="icone-toggle"></ion-icon>
                        <h5>${valor.nome}</h5>
                        <span class="badge-count">${valor.pontos.length}</span>
                    </div>
                    <div class="acordeao-valor-content">
            `;

                valor.pontos.forEach((ponto, pontoIdx) => {
                    html += `
                    <div class="acordeao-ponto" id="acordeaoPonto${tipoIdx}_${valorIdx}_${pontoIdx}" 
                         data-cd-ponto="${ponto.cd}" 
                         data-data-inicio="${dataInicio}" 
                         data-data-fim="${dataFim}">
                        <div class="acordeao-ponto-header" onclick="toggleAcordeaoPonto(${tipoIdx}, ${valorIdx}, ${pontoIdx})">
                            <ion-icon name="chevron-forward-outline" class="icone-toggle"></ion-icon>
                            <ion-icon name="location-outline" class="icone-ponto"></ion-icon>
                            <div class="ponto-info">
                                <strong>${ponto.codigo}</strong>
                                <span>${ponto.nome}</span>
                            </div>
                        </div>
                        <div class="acordeao-ponto-content" id="pontoDatas${tipoIdx}_${valorIdx}_${pontoIdx}">
                            <div class="acordeao-ponto-loading">
                                <ion-icon name="sync-outline"></ion-icon>
                                Clique para carregar as datas...
                            </div>
                        </div>
                    </div>
                `;
                });

                html += `
                    </div>
                </div>
            `;
            });

            html += `
                </div>
            </div>
        `;
        });

        body.innerHTML = html;
    }

    /**
     * Toggle acordeão de tipo
     */
    function toggleAcordeaoTipo(tipoIdx) {
        const el = document.getElementById(`acordeaoTipo${tipoIdx}`);
        el.classList.toggle('aberto');
    }

    /**
     * Toggle acordeão de valor
     */
    function toggleAcordeaoValor(tipoIdx, valorIdx) {
        const el = document.getElementById(`acordeaoValor${tipoIdx}_${valorIdx}`);
        el.classList.toggle('aberto');
    }

    /**
     * Toggle acordeão de ponto (carrega datas sob demanda)
     */
    function toggleAcordeaoPonto(tipoIdx, valorIdx, pontoIdx) {
        const el = document.getElementById(`acordeaoPonto${tipoIdx}_${valorIdx}_${pontoIdx}`);
        const container = document.getElementById(`pontoDatas${tipoIdx}_${valorIdx}_${pontoIdx}`);
        const cdPonto = el.dataset.cdPonto;
        const dataInicio = el.dataset.dataInicio;
        const dataFim = el.dataset.dataFim;

        // Toggle visual
        const estaAberto = el.classList.toggle('aberto');

        // Se abriu e ainda não carregou, carregar datas
        if (estaAberto && !el.dataset.carregado) {
            carregarDatasPonto(cdPonto, dataInicio, dataFim, container, el);
        }
    }

    /**
     * Carrega datas com dados de um ponto
     */
    function carregarDatasPonto(cdPonto, dataInicio, dataFim, container, acordeaoEl) {
        container.innerHTML = `
        <div class="acordeao-ponto-loading">
            <ion-icon name="sync-outline"></ion-icon>
            Carregando datas...
        </div>
    `;

        fetch(`bd/operacoes/getDatasAnaliseIA.php?cdPonto=${cdPonto}&dataInicio=${dataInicio}&dataFim=${dataFim}`)
            .then(response => response.json())
            .then(data => {
                acordeaoEl.dataset.carregado = 'true';

                if (data.success && data.datas.length > 0) {
                    let html = '<div class="lista-datas">';

                    data.datas.forEach((d, idx) => {
                        const anomaliaHtml = d.temAnomalia ?
                            `<span class="badge-anomalia">⚠ ${d.anomalias.join(', ')}</span>` : '';

                        html += `
                        <div class="data-item-wrapper" id="dataWrapper${cdPonto}_${idx}">
                            <div class="data-item" onclick="selecionarDataAnalise(${cdPonto}, '${d.data}', this, ${idx})">
                                <div class="data-info">
                                    <div class="data-texto">${d.dataFormatada} - ${d.diaSemana}</div>
                                    <div class="data-resumo">
                                        ${d.totalRegistros} registros | Média: ${d.mediaVazao} L/s
                                    </div>
                                </div>
                                <span class="status-badge ${d.status}">${d.percentualCompleto}%</span>
                                ${anomaliaHtml}
                                <ion-icon name="chevron-down-outline" class="icone-expandir"></ion-icon>
                            </div>
                            <div class="analise-area-inline" id="analiseInline${cdPonto}_${idx}" style="display:none;"></div>
                        </div>
                    `;
                    });

                    html += '</div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = `
                    <div class="acordeao-ponto-loading" style="color:#64748b;">
                        <ion-icon name="calendar-outline"></ion-icon>
                        Nenhum dado encontrado no período
                    </div>
                `;
                }
            })
            .catch(error => {
                container.innerHTML = `
                <div class="acordeao-ponto-loading" style="color:#dc2626;">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    Erro ao carregar: ${error.message}
                </div>
            `;
            });
    }

    /**
     * Seleciona uma data para análise (expande inline abaixo da data)
     */
    function selecionarDataAnalise(cdPonto, data, elemento, idx) {
        const wrapper = document.getElementById(`dataWrapper${cdPonto}_${idx}`);
        const analiseArea = document.getElementById(`analiseInline${cdPonto}_${idx}`);
        const icone = elemento.querySelector('.icone-expandir');

        // Se já está expandido, fecha
        if (wrapper.classList.contains('expandido')) {
            wrapper.classList.remove('expandido');
            analiseArea.style.display = 'none';
            if (icone) icone.name = 'chevron-down-outline';
            return;
        }

        // Fechar outros expandidos do mesmo ponto
        const container = elemento.closest('.lista-datas');
        container.querySelectorAll('.data-item-wrapper.expandido').forEach(el => {
            el.classList.remove('expandido');
            el.querySelector('.analise-area-inline').style.display = 'none';
            const ic = el.querySelector('.icone-expandir');
            if (ic) ic.name = 'chevron-down-outline';
        });

        // Expandir este
        wrapper.classList.add('expandido');
        analiseArea.style.display = 'block';
        if (icone) icone.name = 'chevron-up-outline';

        analiseArea.innerHTML = `
        <div class="analise-area-loading">
            <ion-icon name="sync-outline"></ion-icon>
            Gerando análise...
        </div>
    `;

        // Scroll para área de análise
        setTimeout(() => {
            analiseArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);

        // Verificar cache
        const cacheKey = `${cdPonto}_${data}`;
        if (analiseIACache[cacheKey]) {
            renderizarAnaliseInline(analiseArea, analiseIACache[cacheKey]);
            return;
        }

        // Buscar análise
        fetch(`bd/operacoes/getAnaliseIA.php?cdPonto=${cdPonto}&data=${data}`)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    analiseIACache[cacheKey] = result;
                    renderizarAnaliseInline(analiseArea, result);
                } else {
                    analiseArea.innerHTML = `
                    <div class="analise-erro">
                        <ion-icon name="alert-circle-outline"></ion-icon>
                        ${result.error || 'Erro ao gerar análise'}
                    </div>
                `;
                }
            })
            .catch(error => {
                analiseArea.innerHTML = `
                <div class="analise-erro">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    Erro de conexão: ${error.message}
                </div>
            `;
            });
    }

    /**
     * Renderiza a análise na área
     */
    function renderizarAnalise(container, data) {
        const resumo = data.resumo;

        // Função auxiliar para formatar nàºmero
        const fmt = (val) => {
            if (val === null || val === undefined) return '-';
            return parseFloat(val).toFixed(2);
        };

        // Determinar classes para métricas
        let variacaoClasse = '';
        if (resumo.variacaoHistorico !== null) {
            if (resumo.variacaoHistorico > 10) variacaoClasse = 'negativo';
            else if (resumo.variacaoHistorico < -10) variacaoClasse = 'alerta';
            else variacaoClasse = 'positivo';
        }

        let completudeClasse = 'positivo';
        if (resumo.percentualCompleto < 50) completudeClasse = 'negativo';
        else if (resumo.percentualCompleto < 90) completudeClasse = 'alerta';

        let html = `
        <div class="analise-area-header">
            <ion-icon name="sparkles-outline"></ion-icon>
            Análise IA - ${data.dataFormatada} (${data.diaSemana})
        </div>
        <div class="analise-metricas">
            <div class="metrica-item ${completudeClasse}">
                <div class="valor">${fmt(resumo.percentualCompleto)}%</div>
                <div class="label">Completude</div>
            </div>
            <div class="metrica-item">
                <div class="valor">${fmt(resumo.mediaDiaria)}</div>
                <div class="label">Média (L/s)</div>
            </div>
            <div class="metrica-item">
                <div class="valor">${fmt(resumo.minVazao)}</div>
                <div class="label">Mínima</div>
            </div>
            <div class="metrica-item">
                <div class="valor">${fmt(resumo.maxVazao)}</div>
                <div class="label">Máxima</div>
            </div>
    `;

        if (resumo.variacaoHistorico !== null) {
            const sinal = resumo.variacaoHistorico >= 0 ? '+' : '';
            html += `
            <div class="metrica-item ${variacaoClasse}">
                <div class="valor">${sinal}${fmt(resumo.variacaoHistorico)}%</div>
                <div class="label">vs Histórico</div>
            </div>
        `;
        }

        html += `
        </div>
        <div class="analise-acoes">
            <button type="button" class="btn-detectar-anomalias" onclick="detectarAnomalias(${data.ponto.cd}, '${data.data}', this)">
                <ion-icon name="warning-outline"></ion-icon>
                Detectar Anomalias
            </button>
        </div>
        <div class="analise-area-content">
            ${data.analiseIA}
        </div>
        <div class="anomalias-container" id="anomaliasContainer${data.ponto.cd}" style="display:none;"></div>
    `;

        container.innerHTML = html;
    }

    /**
     * Renderiza a análise inline (versão compacta abaixo da data)
     */
    function renderizarAnaliseInline(container, data) {
        const resumo = data.resumo;
        const ponto = data.ponto;

        // Função auxiliar para formatar nàºmero
        const fmt = (val) => {
            if (val === null || val === undefined) return '-';
            return parseFloat(val).toFixed(2);
        };

        // Escapar strings para uso em onclick
        const pontoNomeEscapado = (ponto.nome || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
        const pontoCodigoEscapado = (ponto.codigo || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');

        // Determinar classes para métricas
        let completudeClasse = 'positivo';
        if (resumo.percentualCompleto < 50) completudeClasse = 'negativo';
        else if (resumo.percentualCompleto < 90) completudeClasse = 'alerta';

        let variacaoHtml = '';
        if (resumo.variacaoHistorico !== null) {
            const sinal = resumo.variacaoHistorico >= 0 ? '+' : '';
            let variacaoClasse = 'positivo';
            if (resumo.variacaoHistorico > 10) variacaoClasse = 'negativo';
            else if (resumo.variacaoHistorico < -10) variacaoClasse = 'alerta';
            variacaoHtml = `
            <div class="metrica-inline ${variacaoClasse}">
                <span class="valor">${sinal}${fmt(resumo.variacaoHistorico)}%</span>
                <span class="label">vs Hist.</span>
            </div>
        `;
        }

        // Verificar se é medidor de pressão (tipo 4) - apenas visualização
        const apenasVisualizacao = ponto.tipoMedidor === 4;
        const btnValidarHtml = (apenasVisualizacao || !podeEditar) ? '' : `
        <button type="button" class="btn-validar-inline" onclick="abrirModalValidacao(${ponto.cd}, '${data.data}', ${ponto.tipoMedidor}, '${pontoNomeEscapado}', '${pontoCodigoEscapado}')">
            <ion-icon name="create-outline"></ion-icon>
            Validar Dados
        </button>
    `;

        // Botão de anomalias abre modal de validação e executa análise
        const btnAnomaliasHtml = !podeEditar ? '' : `
        <button type="button" class="btn-detectar-inline" onclick="abrirValidacaoComAnalise(${ponto.cd}, '${data.data}', ${ponto.tipoMedidor}, '${pontoNomeEscapado}', '${pontoCodigoEscapado}')">
            <ion-icon name="warning-outline"></ion-icon>
            Detectar Anomalias
        </button>
    `;

        let html = `
        <div class="analise-inline-metricas">
            <div class="metrica-inline ${completudeClasse}">
                <span class="valor">${fmt(resumo.percentualCompleto)}%</span>
                <span class="label">Complet.</span>
            </div>
            <div class="metrica-inline">
                <span class="valor">${fmt(resumo.mediaDiaria)}</span>
                <span class="label">Média</span>
            </div>
            <div class="metrica-inline">
                <span class="valor">${fmt(resumo.minVazao)}</span>
                <span class="label">Mín</span>
            </div>
            <div class="metrica-inline">
                <span class="valor">${fmt(resumo.maxVazao)}</span>
                <span class="label">Máx</span>
            </div>
            ${variacaoHtml}
        </div>
        <div class="analise-inline-acoes">
            ${btnValidarHtml}
            ${btnAnomaliasHtml}
        </div>
        <div class="analise-inline-texto">
            ${data.analiseIA}
        </div>
    `;

        container.innerHTML = html;
    }

    /**
     * Detecta anomalias (versão inline)
     */
    function detectarAnomaliasInline(cdPonto, data, btnElement) {
        const containerId = `anomaliasInline${cdPonto}_${data.replace(/-/g, '')}`;
        const container = document.getElementById(containerId);

        // Desabilitar botão
        btnElement.disabled = true;
        btnElement.innerHTML = '<ion-icon name="sync-outline" style="animation: spin 1s linear infinite;"></ion-icon> Analisando...';

        // Mostrar container
        container.style.display = 'block';
        container.innerHTML = `
        <div class="anomalias-loading">
            <ion-icon name="hourglass-outline"></ion-icon>
            Analisando...
        </div>
    `;

        fetch(`bd/operacoes/detectarAnomaliasIA.php?cdPonto=${cdPonto}&data=${data}`)
            .then(response => response.json())
            .then(result => {
                btnElement.disabled = false;
                btnElement.innerHTML = '<ion-icon name="warning-outline"></ion-icon> Detectar Anomalias';

                if (result.success) {
                    renderizarAnomaliasInline(container, result);
                } else {
                    container.innerHTML = `<div class="analise-erro"><ion-icon name="alert-circle-outline"></ion-icon> ${result.error}</div>`;
                }
            })
            .catch(error => {
                btnElement.disabled = false;
                btnElement.innerHTML = '<ion-icon name="warning-outline"></ion-icon> Detectar Anomalias';
                container.innerHTML = `<div class="analise-erro"><ion-icon name="alert-circle-outline"></ion-icon> ${error.message}</div>`;
            });
    }

    /**
     * Renderiza anomalias (versão inline compacta)
     */
    function renderizarAnomaliasInline(container, data) {
        const { resumo, anomaliasPorHora } = data;

        if (anomaliasPorHora.length === 0) {
            container.innerHTML = `
            <div class="anomalias-inline-ok">
                <ion-icon name="checkmark-circle"></ion-icon>
                Nenhuma anomalia detectada
            </div>
        `;
            return;
        }

        let html = `
        <div class="anomalias-inline-header">
            <ion-icon name="warning"></ion-icon>
            ${resumo.totalAnomalias} anomalia${resumo.totalAnomalias > 1 ? 's' : ''} em ${resumo.horasComAnomalias} hora${resumo.horasComAnomalias > 1 ? 's' : ''}
        </div>
        <div class="anomalias-inline-lista">
    `;

        anomaliasPorHora.forEach(hora => {
            hora.anomalias.forEach(anomalia => {
                html += `
                <div class="anomalia-inline-item ${anomalia.tipo}">
                    <span class="hora">${hora.horaFormatada}</span>
                    <span class="msg">${anomalia.mensagem}</span>
                </div>
            `;
            });
        });

        html += '</div>';
        container.innerHTML = html;
    }

    /**
     * Detecta anomalias por hora
     */
    function detectarAnomalias(cdPonto, data, btnElement) {
        const container = document.getElementById(`anomaliasContainer${cdPonto}`);

        // Desabilitar botão
        btnElement.disabled = true;
        btnElement.innerHTML = '<ion-icon name="sync-outline" style="animation: spin 1s linear infinite;"></ion-icon> Analisando...';

        // Mostrar container
        container.style.display = 'block';
        container.innerHTML = `
        <div class="anomalias-header">
            <ion-icon name="hourglass-outline"></ion-icon>
            Analisando dados hora a hora...
        </div>
    `;

        fetch(`bd/operacoes/detectarAnomaliasIA.php?cdPonto=${cdPonto}&data=${data}`)
            .then(response => response.json())
            .then(result => {
                btnElement.disabled = false;
                btnElement.innerHTML = '<ion-icon name="warning-outline"></ion-icon> Detectar Anomalias';

                if (result.success) {
                    renderizarAnomalias(container, result);
                } else {
                    container.innerHTML = `
                    <div class="anomalias-header" style="color:#dc2626;">
                        <ion-icon name="alert-circle-outline"></ion-icon>
                        Erro ao detectar anomalias
                    </div>
                    <p style="color:#dc2626; font-size:12px;">${result.error}</p>
                `;
                }
            })
            .catch(error => {
                btnElement.disabled = false;
                btnElement.innerHTML = '<ion-icon name="warning-outline"></ion-icon> Detectar Anomalias';
                container.innerHTML = `
                <div class="anomalias-header" style="color:#dc2626;">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    Erro de conexão
                </div>
                <p style="color:#dc2626; font-size:12px;">${error.message}</p>
            `;
            });
    }

    /**
     * Renderiza as anomalias detectadas
     */
    function renderizarAnomalias(container, data) {
        const { resumo, anomaliasPorHora } = data;

        let html = `
        <div class="anomalias-header">
            <ion-icon name="warning-outline"></ion-icon>
            Anomalias Detectadas - ${data.dataFormatada}
        </div>
        <div class="anomalias-resumo">
            <span><ion-icon name="time-outline"></ion-icon> ${resumo.horasAnalisadas} horas analisadas</span>
            <span><ion-icon name="alert-circle-outline"></ion-icon> ${resumo.horasComAnomalias} horas com anomalias</span>
            <span><ion-icon name="warning-outline"></ion-icon> ${resumo.totalAnomalias} anomalias total</span>
        </div>
        `;

        if (anomaliasPorHora.length === 0) {
            html += `
            <div class="anomalias-vazio">
                <ion-icon name="checkmark-circle-outline"></ion-icon>
                Nenhuma anomalia detectada!<br>
                <small>Todos os dados estão dentro dos parâmetros normais.</small>
            </div>
        `;
        } else {
            html += '<div class="anomalias-lista">';

            anomaliasPorHora.forEach(hora => {
                html += `
                <div class="anomalia-hora-card">
                    <div class="anomalia-hora-header">
                        <ion-icon name="time-outline"></ion-icon>
                        ${hora.horaFormatada}
                        <span class="badge-count">${hora.totalAnomalias} anomalia${hora.totalAnomalias > 1 ? 's' : ''}</span>
                    </div>
            `;

                hora.anomalias.forEach(anomalia => {
                    html += `
                    <div class="anomalia-item">
                        <div class="icone ${anomalia.tipo}">
                            <ion-icon name="${anomalia.icone}-outline"></ion-icon>
                        </div>
                        <div class="conteudo">
                            <div class="mensagem">${anomalia.mensagem}</div>
                            <div class="sugestao">💡 ${anomalia.sugestao}</div>
                        </div>
                    </div>
                `;
                });

                html += '</div>';
            });

            html += '</div>';
        }

        container.innerHTML = html;
    }
</script>

<?php include_once 'includes/footer.inc.php'; ?>
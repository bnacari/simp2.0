<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Registro de Vazão e Pressão
 */

include_once 'includes/header.inc.php';
include_once 'bd/conexao.php';


header('Content-Type: text/html; charset=UTF-8');

// Verifica permissão
// Recarregar permissões do banco (garante que estão atualizadas)
recarregarPermissoesUsuario();

// Agora verificar permissão
exigePermissaoTela('Registro de Vazão e Pressão', ACESSO_LEITURA);

// Permissão do usuário para este módulo
$podeEditar = podeEditarTela('Registro de Vazão e Pressão');

include_once 'includes/menu.inc.php';

// ========== INÍCIO - Parâmetros GET para navegação externa ==========
$cdPontoGet = isset($_GET['cdPonto']) ? $_GET['cdPonto'] : '';
$dataInicioGet = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$dataFimGet = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';
$dataGet = isset($_GET['data']) ? $_GET['data'] : '';
if (!empty($dataGet) && empty($dataInicioGet) && empty($dataFimGet)) {
    $dataInicioGet = $dataGet;
    $dataFimGet = $dataGet;
}
// ========== FIM - Parâmetros GET ==========

// Buscar Unidades para dropdown
$sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME, CD_CODIGO FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

// Tipos de Registro (ID_TIPO_REGISTRO)
$tiposRegistro = [
    2 => '2 - Sem Cálculo',
    4 => '4 - Planilha',
    6 => '6 - CesanLims',
    8 => '8 - CCO'
];

// Tipos de Vazão (ID_TIPO_VAZAO)
$tiposVazao = [
    1 => '1 - Estimado',
    2 => '2 - Macromedido'
];

// Descarte (ID_SITUACAO: 1 = Não, 2 = Sim)
$descartes = [
    1 => 'Não',
    2 => 'Sim'
];
?>

<!-- Choices.js CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

<link rel="stylesheet" href="style/css/registroVazaoPressao.css" />

<div class="page-container">
    <!-- Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="analytics-outline"></ion-icon>
                </div>
                <div>
                    <h1>Registro de Vazão e Pressão</h1>
                    <p class="page-header-subtitle">Consulta e gerenciamento de registros de medição</p>
                </div>
            </div>
            <?php if ($podeEditar): ?>
                <button type="button" class="btn-novo" onclick="abrirModalNovo()">
                    <ion-icon name="add-outline"></ion-icon>
                    Novo Registro
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <div class="filters-header">
            <div class="filters-title">
                <ion-icon name="filter-outline"></ion-icon>
                Filtros de Pesquisa
            </div>
            <button type="button" class="btn-clear-filters" onclick="limparFiltros()">
                <ion-icon name="refresh-outline"></ion-icon>
                Limpar Filtros
            </button>
        </div>

        <div class="filters-grid-6">
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="business-outline"></ion-icon>
                    Unidade
                </label>
                <select id="filtroUnidade" class="form-control choices-select">
                    <option value="">Todas as Unidades</option>
                    <?php foreach ($unidades as $u): ?>
                        <option value="<?= $u['CD_UNIDADE'] ?>">
                            <?= htmlspecialchars($u['CD_CODIGO'] . ' - ' . $u['DS_NOME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="location-outline"></ion-icon>
                    Localidade
                </label>
                <select id="filtroLocalidade" class="form-control choices-select">
                    <option value="">Todas as Localidades</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="speedometer-outline"></ion-icon>
                    Ponto de Medição
                </label>
                <div class="autocomplete-container">
                    <input type="text" id="filtroPontoMedicaoInput" class="form-control"
                        placeholder="Clique para selecionar ou digite para filtrar..." autocomplete="off">
                    <input type="hidden" id="filtroPontoMedicao" value="">
                    <div id="filtroPontoMedicaoDropdown" class="autocomplete-dropdown"></div>
                    <button type="button" id="btnLimparPonto" class="btn-limpar-autocomplete" style="display: none;"
                        title="Limpar">
                        <ion-icon name="close-circle"></ion-icon>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Data Início
                </label>
                <input type="date" id="filtroDataInicio" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Data Fim
                </label>
                <input type="date" id="filtroDataFim" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="hardware-chip-outline"></ion-icon>
                    Tipo Medidor
                </label>
                <select id="filtroTipoMedidor" class="form-control">
                    <option value="">Todos os Tipos</option>
                    <option value="1">M - Macromedidor</option>
                    <option value="2">E - Estação Pitométrica</option>
                    <option value="4">P - Medidor Pressão</option>
                    <option value="8">H - Hidrômetro</option>
                    <option value="6">R - Nível Reservatório</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="reader-outline"></ion-icon>
                    Tipo Leitura
                </label>
                <select id="filtroTipoLeitura" class="form-control">
                    <option value="">Todos os Tipos</option>
                    <option value="2">Manual</option>
                    <option value="4">Planilha</option>
                    <option value="8">Integração CCO</option>
                    <option value="6">Integração CesanLims</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="trash-outline"></ion-icon>
                    Descarte
                </label>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="filtroDescarte" value="">
                        <span class="radio-label">Todos</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="filtroDescarte" value="1" checked>
                        <span class="radio-label">Não</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="filtroDescarte" value="2">
                        <span class="radio-label">Sim</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="grid-outline"></ion-icon>
                    Agrupar por
                </label>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="modoExibicao" value="ponto" checked>
                        <span class="radio-label">Ponto</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="modoExibicao" value="data">
                        <span class="radio-label">Data</span>
                    </label>
                </div>
            </div>
        </div>

        <script>
            // Declarações antecipadas das funções de modal
            function abrirModalImportacao() {
                // Usar window.podeEditar para evitar conflito
                if (window.podeEditar === false) {
                    showToast('Você não tem permissão para importar planilhas', 'error');
                    return;
                }
                document.getElementById('modalImportacao').style.display = 'flex';
                var form = document.getElementById('formImportacao');
                if (form) form.reset();
                var resultado = document.getElementById('resultadoImportacao');
                if (resultado) resultado.style.display = 'none';
                var preview = document.getElementById('previewDados');
                if (preview) preview.style.display = 'none';
                var fileSelected = document.getElementById('fileSelected');
                if (fileSelected) fileSelected.style.display = 'none';
                var placeholder = document.querySelector('.file-upload-placeholder');
                if (placeholder) placeholder.style.display = 'block';
                window.dadosPlanilha = null;
            }

            function fecharModalImportacao() {
                document.getElementById('modalImportacao').style.display = 'none';
            }
        </script>

        <div class="filters-actions">
            <button type="button" class="btn-buscar" onclick="buscarRegistros()">
                <ion-icon name="search-outline"></ion-icon>
                Buscar
            </button>
            <button type="button" class="btn-importar" onclick="abrirModalImportacao()" <?= !$podeEditar ? 'disabled title="Você não tem permissão para importar"' : '' ?>>
                <ion-icon name="cloud-upload-outline"></ion-icon>
                Importar Planilha
            </button>
            <a href="https://compras.cesan.com.br/uploads/anexos/PlanilhaPadraoSIMP.xlsx" class="btn-obter-planilha"
                target="_blank">
                <ion-icon name="download-outline"></ion-icon>
                Obter Planilha
            </a>
        </div>
    </div>

    <!-- Results Info -->
    <div class="results-info">
        <div class="results-count">
            <ion-icon name="list-outline"></ion-icon>
            <span id="countRegistros">Utilize os filtros acima para buscar registros</span>
        </div>
        <div class="results-actions" id="botoesExpandir" style="display: none;">
            <button type="button" class="btn-expand" onclick="expandirTodos()">
                <ion-icon name="chevron-down-outline"></ion-icon>
                Expandir Todos
            </button>
            <button type="button" class="btn-expand" onclick="colapsarTodos()">
                <ion-icon name="chevron-up-outline"></ion-icon>
                Colapsar Todos
            </button>
        </div>
    </div>

    <!-- Tabela -->
    <div class="table-container">
        <div class="loading-overlay" id="loadingRegistros">
            <div class="loading-spinner"></div>
        </div>
        <div class="table-wrapper">
            <table class="data-table" id="tabelaDados">
                <thead>
                    <tr>
                        <th style="width: 120px;" class="sortable" data-column="DS_UNIDADE">Unidade</th>
                        <th style="width: 150px;" class="sortable" data-column="DS_LOCALIDADE">Localidade</th>
                        <th style="width: 200px;" class="sortable" data-column="DS_PONTO_MEDICAO">Ponto Medição</th>
                        <th style="width: 100px;" class="sortable" data-column="ID_TIPO_REGISTRO">Tipo Registro</th>
                        <th style="width: 100px;" class="sortable" data-column="ID_TIPO_VAZAO">Tipo Vazão</th>
                        <th style="width: 140px;" class="sortable" data-column="DT_LEITURA">Data Leitura</th>
                        <th style="width: 110px;" class="sortable" data-column="VL_VAZAO_EFETIVA">Vazão (l/s)</th>
                        <th style="width: 100px;" class="sortable" data-column="VL_PRESSAO">Pressão (mca)</th>
                        <th style="width: 100px;" class="sortable" data-column="VL_RESERVATORIO">Nível Reserv.</th>
                        <th style="width: 90px;" class="sortable" data-column="ID_SITUACAO">Descarte</th>
                        <th style="width: 80px;">Ações</th>
                    </tr>
                </thead>
                <tbody id="tabelaRegistros">
                    <tr>
                        <td colspan="11">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <ion-icon name="search-outline"></ion-icon>
                                </div>
                                <h3>Nenhuma pesquisa realizada</h3>
                                <p>Utilize os filtros acima e clique em "Buscar" para consultar os registros</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Barra de Exclusão em Massa -->
        <?php if ($podeEditar): ?>
            <div class="barra-exclusao" id="barraExclusao">
                <div class="barra-exclusao-info">
                    <ion-icon name="trash-outline"></ion-icon>
                    <span><span class="barra-exclusao-count" id="countSelecionados">0</span> registro(s)
                        selecionado(s)</span>
                </div>
                <div class="barra-exclusao-acoes">
                    <button type="button" class="btn-cancelar-selecao" onclick="cancelarSelecao()">
                        <ion-icon name="close-outline"></ion-icon>
                        Cancelar
                    </button>
                    <button type="button" class="btn-excluir-selecionados" onclick="excluirSelecionados()">
                        <ion-icon name="trash-outline"></ion-icon>
                        Descartar Selecionados
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <div class="pagination-container" id="paginacaoRegistros" style="display: none;"></div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Modal Gráfico por Hora -->
<div class="modal-grafico" id="modalGrafico" onclick="fecharModalGrafico(event)">
    <div class="modal-grafico-content" onclick="event.stopPropagation()">
        <div class="modal-grafico-header">
            <h3>
                <ion-icon name="analytics-outline"></ion-icon>
                <span id="modalGraficoTitulo">Gráfico de Vazão por Hora</span>
            </h3>
            <button type="button" class="modal-grafico-close" onclick="fecharModalGrafico()">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-grafico-body">
            <div class="grafico-info" id="graficoInfo"></div>
            <div class="grafico-legenda">
                <div class="grafico-legenda-item">
                    <span class="dot complete"></span>
                    <span>Hora completa (60+ registros)</span>
                </div>
                <div class="grafico-legenda-item">
                    <span class="dot incomplete"></span>
                    <span>Hora incompleta (&lt;60 registros)</span>
                </div>
            </div>
            <div class="grafico-container" id="graficoContainer"></div>
        </div>
    </div>
</div>

<!-- Choices.js -->
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<script>
    // ============================================
    // Configurações Globais
    // ============================================
    window.podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;
    
    // Tipos de Registro
    const tiposRegistro = <?= json_encode($tiposRegistro) ?>;

    // Tipos de Vazão
    const tiposVazao = <?= json_encode($tiposVazao) ?>;

    // Descarte (ID_SITUACAO: 1 = Não, 2 = Sim)
    const descartes = <?= json_encode($descartes) ?>;

    // Instâncias do Choices.js
    let choicesUnidade, choicesLocalidade;

    // ========== INÍCIO - Parâmetros GET ==========
    const paramCdPonto = '<?= $cdPontoGet ?>';
    const paramDataInicio = '<?= $dataInicioGet ?>';
    const paramDataFim = '<?= $dataFimGet ?>';
    // ========== FIM - Parâmetros GET ==========

    // ============================================
    // Inicialização
    // ============================================
    document.addEventListener('DOMContentLoaded', function () {
        initChoices();

        // Verificar se recebeu parâmetros via GET (navegação do Dashboard)
        if (paramCdPonto || paramDataInicio || paramDataFim) {
            // Preencher datas dos parâmetros
            if (paramDataInicio) {
                document.getElementById('filtroDataInicio').value = paramDataInicio;
            } else {
                const ref = paramDataFim ? new Date(paramDataFim + 'T12:00:00') : new Date();
                const inicio = new Date(ref);
                inicio.setDate(inicio.getDate() - 7);
                document.getElementById('filtroDataInicio').value = formatDateInput(inicio);
            }

            if (paramDataFim) {
                document.getElementById('filtroDataFim').value = paramDataFim;
            } else {
                document.getElementById('filtroDataFim').value = formatDateInput(new Date());
            }

            // Se tem ponto, preencher o autocomplete e buscar
            if (paramCdPonto) {
                setTimeout(() => {
                    preencherPontoMedicaoViaGet(paramCdPonto);
                }, 300);
            }
        } else {
            // Comportamento padrão: últimos 30 dias
            const hoje = new Date();
            const inicio = new Date();
            inicio.setDate(hoje.getDate() - 30);
            document.getElementById('filtroDataFim').value = formatDateInput(hoje);
            document.getElementById('filtroDataInicio').value = formatDateInput(inicio);
        }
    });

    function formatDateInput(date) {
        return date.toISOString().split('T')[0];
    }

    // ============================================
    // Choices.js Inicialização
    // ============================================
    function initChoices() {
        // Unidade
        choicesUnidade = new Choices('#filtroUnidade', {
            searchEnabled: true,
            searchPlaceholderValue: 'Digite para buscar...',
            noResultsText: 'Nenhum resultado encontrado',
            noChoicesText: 'Nenhuma opção disponível',
            itemSelectText: '',
            placeholder: true,
            placeholderValue: 'Todas as Unidades',
            shouldSort: false,
            searchResultLimit: 50
        });

        // Localidade
        choicesLocalidade = new Choices('#filtroLocalidade', {
            searchEnabled: true,
            searchPlaceholderValue: 'Digite para buscar...',
            noResultsText: 'Nenhum resultado encontrado',
            noChoicesText: 'Selecione uma unidade primeiro',
            itemSelectText: '',
            placeholder: true,
            placeholderValue: 'Todas as Localidades',
            shouldSort: false,
            searchResultLimit: 50
        });

        // Event listeners para carregar dados em cascata
        document.getElementById('filtroUnidade').addEventListener('change', function () {
            carregarLocalidades(this.value);
        });

        // Inicializa autocomplete de Ponto de Medição
        initAutocompletePontoMedicao();
    }

    // ============================================
    // Autocomplete para Ponto de Medição
    // ============================================
    let autocompletePontoTimeout = null;
    let autocompletePontoIndex = -1;

    // Mapeamento de letras por tipo de medidor
    const letrasTipoMedidor = {
        1: 'M', // Macromedidor
        2: 'E', // Estação Pitométrica
        4: 'P', // Medidor Pressão
        6: 'R', // Nível Reservatório
        8: 'H'  // Hidrômetro
    };

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
        const cdUnidade = document.getElementById('filtroUnidade').value;
        const cdLocalidade = document.getElementById('filtroLocalidade').value;

        dropdown.innerHTML = '<div class="autocomplete-loading">Buscando...</div>';
        dropdown.classList.add('active');

        const params = new URLSearchParams({ busca: termo });
        if (cdUnidade) params.append('cd_unidade', cdUnidade);
        if (cdLocalidade) params.append('cd_localidade', cdLocalidade);

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

    /**
         * Preencher ponto de medição via parâmetro GET (navegação do Dashboard)
         */
    /**
     * Preencher ponto de medição via parâmetro GET (navegação do Dashboard)
     */
    function preencherPontoMedicaoViaGet(cdPonto) {

        const input = document.getElementById('filtroPontoMedicaoInput');
        const hidden = document.getElementById('filtroPontoMedicao');
        const btnLimpar = document.getElementById('btnLimparPonto');

        fetch(`bd/registroVazaoPressao/getPontoMedicaoInfo.php?cd_ponto=${cdPonto}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.ponto) {
                    const ponto = data.ponto;
                    const letrasTipo = { 1: 'M', 2: 'E', 4: 'P', 6: 'R', 8: 'H' };
                    const letraTipo = letrasTipo[ponto.ID_TIPO_MEDIDOR] || 'X';

                    const codigoPonto = (ponto.CD_LOCALIDADE || '000') + '-' +
                        String(ponto.CD_PONTO_MEDICAO).padStart(6, '0') + '-' +
                        letraTipo + '-' +
                        (ponto.CD_UNIDADE || '00');

                    const label = `${codigoPonto} - ${ponto.DS_NOME || ''}`;

                    input.value = label;
                    hidden.value = cdPonto;
                    if (btnLimpar) btnLimpar.style.display = 'flex';

                    setTimeout(() => buscarRegistros(), 200);
                } else {
                    input.value = `Ponto ${cdPonto}`;
                    hidden.value = cdPonto;
                    if (btnLimpar) btnLimpar.style.display = 'flex';
                    setTimeout(() => buscarRegistros(), 200);
                }
            })
            .catch(error => {
                console.error('Erro ao buscar ponto:', error);
                input.value = `Ponto ${cdPonto}`;
                hidden.value = cdPonto;
                if (btnLimpar) btnLimpar.style.display = 'flex';
                setTimeout(() => buscarRegistros(), 200);
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

    // ============================================
    // Carregar Localidades
    // ============================================
    function carregarLocalidades(cdUnidade) {
        choicesLocalidade.clearStore();

        if (!cdUnidade) {
            choicesLocalidade.setChoices([{ value: '', label: 'Todas as Localidades', selected: true }], 'value', 'label', true);
            return;
        }

        $.get('bd/registroVazaoPressao/getLocalidades.php', { cd_unidade: cdUnidade }, function (response) {
            if (response.success) {
                let options = [{ value: '', label: 'Todas as Localidades', selected: true }];
                response.data.forEach(item => {
                    options.push({
                        value: item.CD_CHAVE,
                        label: item.CD_LOCALIDADE + ' - ' + item.DS_NOME
                    });
                });
                choicesLocalidade.setChoices(options, 'value', 'label', true);
            }
        }, 'json');
    }

    // ============================================
    // Buscar Registros (apenas agregadores)
    // ============================================
    function buscarRegistros(restaurarEstado = false) {
        // Limpar seleção anterior
        if (typeof registrosSelecionados !== 'undefined') {
            registrosSelecionados.clear();
            atualizarBarraExclusao();
        }

        const filtros = {
            cd_unidade: document.getElementById('filtroUnidade').value,
            cd_localidade: document.getElementById('filtroLocalidade').value,
            cd_ponto_medicao: document.getElementById('filtroPontoMedicao').value,
            data_inicio: document.getElementById('filtroDataInicio').value,
            data_fim: document.getElementById('filtroDataFim').value,
            tipo_medidor: document.getElementById('filtroTipoMedidor').value,
            tipo_leitura: document.getElementById('filtroTipoLeitura').value,
            descarte: document.querySelector('input[name="filtroDescarte"]:checked').value
        };

        // Validar: pelo menos um dos filtros principais deve estar preenchido
        if (!filtros.cd_unidade && !filtros.cd_localidade && !filtros.cd_ponto_medicao) {
            showToast('Para realizar a busca, preencha pelo menos um dos filtros: Unidade, Localidade ou Ponto de Medição', 'warning');
            return;
        }

        // Alertar se apenas a unidade estiver preenchida (pode trazer muito volume de dados)
        if (filtros.cd_unidade && !filtros.cd_localidade && !filtros.cd_ponto_medicao) {
            if (!confirm('A consulta apenas por Unidade pode trazer um grande volume de dados e demorar para ser processada.\n\nDeseja continuar mesmo assim?')) {
                return;
            }
        }

        // Armazenar filtros para uso posterior (carregamento sob demanda)
        filtrosAtuais = filtros;

        // Verificar modo de exibição
        const modoExibicao = document.querySelector('input[name="modoExibicao"]:checked').value;

        const loading = document.getElementById('loadingRegistros');
        loading.classList.add('active');

        if (modoExibicao === 'ponto') {
            // Modo: Ponto de Medição => Dia => Registros
            $.get('bd/registroVazaoPressao/getRegistrosPorPonto.php', filtros, function (response) {
                loading.classList.remove('active');

                if (response.success) {
                    document.getElementById('countRegistros').innerHTML =
                        `<strong>${response.total}</strong> registro(s) em <strong>${response.totalPontos}</strong> ponto(s)`;

                    // Armazenar estatísticas por ponto
                    estatisticasPonto = response.estatisticasPonto || {};
                    estatisticasDia = {}; // Limpar modo anterior

                    // Limpar caches
                    registrosCarregados = {};
                    pontosMedicaoCarregados = {};
                    diasPontoCarregados = {};

                    // Renderizar agregadores por ponto
                    renderizarAgregadoresPorPonto();

                    // Mostrar/ocultar botões expandir/colapsar
                    const botoesExpandir = document.getElementById('botoesExpandir');
                    if (Object.keys(estatisticasPonto).length > 0) {
                        botoesExpandir.style.display = 'flex';
                    } else {
                        botoesExpandir.style.display = 'none';
                    }

                    // Restaurar estado dos grupos se solicitado
                    if (restaurarEstado) {
                        setTimeout(() => restaurarEstadoGrupos(), 100);
                    }
                } else {
                    showToast(response.message || 'Erro ao buscar registros', 'erro');
                    document.getElementById('botoesExpandir').style.display = 'none';
                }
            }, 'json').fail(function () {
                loading.classList.remove('active');
                showToast('Erro ao comunicar com o servidor', 'erro');
                document.getElementById('botoesExpandir').style.display = 'none';
            });
        } else {
            // Modo: Data => Ponto de Medição => Registros (modo original)
            $.get('bd/registroVazaoPressao/getRegistros.php', filtros, function (response) {
                loading.classList.remove('active');

                if (response.success) {
                    document.getElementById('countRegistros').innerHTML =
                        `<strong>${response.total}</strong> registro(s) em <strong>${response.totalDias}</strong> dia(s)`;

                    // Armazenar estatísticas por dia
                    estatisticasDia = response.estatisticasDia || {};
                    estatisticasPonto = {}; // Limpar modo anterior

                    // Limpar caches
                    registrosCarregados = {};
                    pontosMedicaoCarregados = {};
                    diasPontoCarregados = {};

                    // Renderizar apenas agregadores
                    renderizarAgregadores();

                    // Mostrar/ocultar botões expandir/colapsar
                    const botoesExpandir = document.getElementById('botoesExpandir');
                    if (Object.keys(estatisticasDia).length > 0) {
                        botoesExpandir.style.display = 'flex';
                    } else {
                        botoesExpandir.style.display = 'none';
                    }

                    // Restaurar estado dos grupos se solicitado
                    if (restaurarEstado) {
                        setTimeout(() => restaurarEstadoGrupos(), 100);
                    }
                } else {
                    showToast(response.message || 'Erro ao buscar registros', 'erro');
                    document.getElementById('botoesExpandir').style.display = 'none';
                }
            }, 'json').fail(function () {
                loading.classList.remove('active');
                showToast('Erro ao comunicar com o servidor', 'erro');
                document.getElementById('botoesExpandir').style.display = 'none';
            });
        }
    }

    // ============================================
    // Limpar Filtros
    // ============================================
    function limparFiltros() {
        // Limpar Choices
        choicesUnidade.setChoiceByValue('');
        choicesLocalidade.clearStore();
        choicesLocalidade.setChoices([{ value: '', label: 'Todas as Localidades', selected: true }], 'value', 'label', true);

        // Limpar autocomplete de Ponto de Medição
        document.getElementById('filtroPontoMedicaoInput').value = '';
        document.getElementById('filtroPontoMedicao').value = '';
        document.getElementById('filtroPontoMedicaoDropdown').classList.remove('active');
        document.getElementById('btnLimparPonto').style.display = 'none';

        // Resetar datas (últimos 30 dias)
        const hoje = new Date();
        const inicio = new Date();
        inicio.setDate(hoje.getDate() - 30);
        document.getElementById('filtroDataFim').value = formatDateInput(hoje);
        document.getElementById('filtroDataInicio').value = formatDateInput(inicio);

        // Resetar demais filtros
        document.getElementById('filtroTipoMedidor').value = '';
        document.getElementById('filtroTipoLeitura').value = '';
        document.querySelector('input[name="filtroDescarte"][value="1"]').checked = true;

        // Limpar dados e ordenação
        dadosTabela = [];
        estatisticasDia = {};
        registrosCarregados = {};
        pontosMedicaoCarregados = {};
        filtrosAtuais = {};
        document.querySelectorAll('.data-table th.sortable').forEach(th => {
            th.classList.remove('asc', 'desc');
        });

        // Ocultar botões expandir/colapsar
        document.getElementById('botoesExpandir').style.display = 'none';

        // Limpar tabela
        document.getElementById('tabelaRegistros').innerHTML = `
            <tr>
                <td colspan="11">
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <ion-icon name="search-outline"></ion-icon>
                        </div>
                        <h3>Nenhuma pesquisa realizada</h3>
                        <p>Utilize os filtros acima e clique em "Buscar" para consultar os registros</p>
                    </div>
                </td>
            </tr>
        `;
        document.getElementById('countRegistros').innerHTML = 'Utilize os filtros acima para buscar registros';
    }

    // ============================================
    // Excluir Registro
    // ============================================
    function excluirRegistro(id) {
        // Modal de confirmação customizado
        const modalExclusao = document.createElement('div');
        modalExclusao.className = 'modal-exclusao-confirmacao';
        modalExclusao.id = 'modalExclusaoIndividual_' + id;

        const htmlConteudo = `
            <div class="modal-exclusao-overlay" onclick="fecharModalExclusao(event)">
                <div class="modal-exclusao-container" onclick="event.stopPropagation()">
                    <!-- Cabeçalho -->
                    <div class="modal-exclusao-header">
                        <ion-icon name="warning-outline" class="icon-warning"></ion-icon>
                        <h3>Confirmar Exclusão de Registro</h3>
                        <button type="button" class="btn-fechar-modal" onclick="fecharModalExclusao(event)">
                            <ion-icon name="close-outline"></ion-icon>
                        </button>
                    </div>

                    <!-- Corpo -->
                    <div class="modal-exclusao-body">
                        <div class="info-box info-soft-delete">
                            <div class="info-header">
                                <ion-icon name="archive-outline"></ion-icon>
                                <h4>O que acontecerá:</h4>
                            </div>
                            <ul class="info-lista">
                                <li>
                                    <ion-icon name="checkmark-circle-outline"></ion-icon>
                                    <span>Registro será <strong>DESCARTADO</strong> (Soft Delete)</span>
                                </li>
                                <li>
                                    <ion-icon name="eye-off-outline"></ion-icon>
                                    <span>Desaparecerá das listagens normais</span>
                                </li>
                                <li>
                                    <ion-icon name="refresh-outline"></ion-icon>
                                    <span>Poderá ser <strong>RECUPERADO</strong> posteriormente</span>
                                </li>
                                <li>
                                    <ion-icon name="timer-outline"></ion-icon>
                                    <span>Será salvo no banco com status "Descartado"</span>
                                </li>
                            </ul>
                        </div>

                        <div class="info-box info-proxima-acao" style="margin-top: 16px;">
                            <div class="info-header">
                                <ion-icon name="arrow-forward-outline"></ion-icon>
                                <h4>Próxima ação (se necessário):</h4>
                            </div>
                            <p>Se quiser <strong>remover permanentemente</strong> do banco, clique em Descartar novamente quando o registro aparecer marcado como descartado.</p>
                        </div>

                        <div class="aviso-importante">
                            <ion-icon name="alert-circle-outline"></ion-icon>
                            <p><strong>Aviso:</strong> Esta ação é <strong>reversível</strong> nesta etapa.</p>
                        </div>
                    </div>

                    <!-- Rodapé -->
                    <div class="modal-exclusao-footer">
                        <button type="button" class="btn-modal-cancelar" onclick="fecharModalExclusao(event)">
                            <ion-icon name="close-outline"></ion-icon>
                            Cancelar
                        </button>
                        <button type="button" class="btn-modal-confirmar" onclick="executarExclusaoIndividual(${id}, 'modalExclusaoIndividual_${id}')">
                            <ion-icon name="trash-outline"></ion-icon>
                            Sim, Descartar
                        </button>
                    </div>
                </div>
            </div>
        `;

        modalExclusao.innerHTML = htmlConteudo;
        document.body.appendChild(modalExclusao);

        // Mostrar com animação
        setTimeout(() => {
            modalExclusao.classList.add('active');
        }, 10);
    }

    // Executar exclusão individual
    function executarExclusaoIndividual(id, modalId) {
        console.log('Descartando registro ID:', id);

        $.post('bd/registroVazaoPressao/excluirRegistro.php', { id: id }, function (response) {
            console.log('Resposta descarte:', response);

            // Fechar modal
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
                setTimeout(() => modal.remove(), 300);
            }

            if (response.success) {
                // Mensagens customizadas conforme tipo
                let mensagem = response.message;

                if (response.tipo === 'soft_delete') {
                    mensagem = 'Registro transformado em descartado!\n\n';
                    mensagem += 'ID_SITUACAO alterado de 1 para 2\n';
                    mensagem += ' Desaparecerá das listagens normais\n';
                    mensagem += 'Poderá ser recuperado se necessário';
                } else if (response.tipo === 'hard_delete') {
                    mensagem = 'Registro removido permanentemente!\n\n';
                    mensagem += 'ID_SITUACAO = 2 foi deletado do banco\n';
                    mensagem += 'Esta ação é irreversível\n';
                    mensagem += 'Recuperação requer backup do banco';
                }

                showToast(mensagem, 'sucesso');
                buscarRegistrosPreservandoEstado();
            } else {
                showToast(response.message || 'Erro ao processar exclusão', 'erro');
            }
        }, 'json').fail(function (xhr, status, error) {
            console.error('Erro descarte:', status, error);
            console.error('Response:', xhr.responseText);

            // Fechar modal
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
                setTimeout(() => modal.remove(), 300);
            }

            showToast('Erro ao comunicar com o servidor. Verifique sua conexão.', 'erro');
        });
    }

    // Fechar modal de exclusão
    function fecharModalExclusao(event) {
        let modal = event.target;

        if (modal.classList.contains('modal-exclusao-overlay')) {
            modal = modal;
        } else if (modal.classList.contains('btn-fechar-modal') || modal.tagName === 'ION-ICON') {
            modal = event.target.closest('.modal-exclusao-confirmacao');
        } else {
            modal = event.target.closest('.modal-exclusao-confirmacao');
        }

        if (modal) {
            modal.classList.remove('active');
            setTimeout(() => modal.remove(), 300);
        }
    }

    // ============================================
    // Exclusão em Massa - Gerenciamento de Seleção
    // ============================================

    // Armazenar registros selecionados
    let registrosSelecionados = new Set();

    // Atualizar barra de exclusão
    function atualizarBarraExclusao() {
        const barra = document.getElementById('barraExclusao');
        const count = document.getElementById('countSelecionados');

        if (!barra) return;

        if (registrosSelecionados.size > 0) {
            barra.classList.add('visivel');
            count.textContent = registrosSelecionados.size;
        } else {
            barra.classList.remove('visivel');
        }

        // Atualizar classe de linha selecionada
        document.querySelectorAll('.row-data').forEach(row => {
            const chave = row.dataset.chave;
            if (chave && registrosSelecionados.has(parseInt(chave))) {
                row.classList.add('selecionado');
            } else {
                row.classList.remove('selecionado');
            }
        });
    }

    // Selecionar/deselecionar registro individual
    function selecionarRegistro(cdChave, checked) {
        if (checked) {
            registrosSelecionados.add(cdChave);
        } else {
            registrosSelecionados.delete(cdChave);
        }

        // Atualizar estado dos botões de exclusão
        atualizarBotoesExclusao();
        atualizarBarraExclusao();
    }

    // Atualizar botões de exclusão e restauração baseado nos registros selecionados
    function atualizarBotoesExclusao() {
        // Desabilitar todos primeiro
        document.querySelectorAll('.btn-trash-ponto, .btn-trash-dia, .btn-restore-ponto, .btn-restore-dia').forEach(btn => {
            btn.disabled = true;
        });

        // Se há registros selecionados, habilitar os botões correspondentes
        if (registrosSelecionados.size > 0) {
            // Verificar checkboxes de registros individuais marcados
            document.querySelectorAll('.checkbox-registro:checked').forEach(cb => {
                const cdPonto = cb.dataset.ponto;
                const dataDia = cb.dataset.dia;

                // Habilitar botões do ponto
                const btnTrashPonto = document.querySelector(`.btn-trash-ponto[data-ponto="${cdPonto}"]`);
                if (btnTrashPonto) btnTrashPonto.disabled = false;
                const btnRestorePonto = document.querySelector(`.btn-restore-ponto[data-ponto="${cdPonto}"]`);
                if (btnRestorePonto) btnRestorePonto.disabled = false;

                // Habilitar botões do dia
                const btnTrashDia = document.querySelector(`.btn-trash-dia[data-ponto="${cdPonto}"][data-dia="${dataDia}"]`);
                if (btnTrashDia) btnTrashDia.disabled = false;
                const btnRestoreDia = document.querySelector(`.btn-restore-dia[data-ponto="${cdPonto}"][data-dia="${dataDia}"]`);
                if (btnRestoreDia) btnRestoreDia.disabled = false;
            });

            // Verificar checkboxes de ponto principal marcados
            document.querySelectorAll('.checkbox-ponto-principal:checked').forEach(cb => {
                const cdPonto = cb.dataset.ponto;
                const btnTrashPonto = document.querySelector(`.btn-trash-ponto[data-ponto="${cdPonto}"]`);
                if (btnTrashPonto) btnTrashPonto.disabled = false;
                const btnRestorePonto = document.querySelector(`.btn-restore-ponto[data-ponto="${cdPonto}"]`);
                if (btnRestorePonto) btnRestorePonto.disabled = false;

                // Habilitar todos os botões de dia deste ponto
                document.querySelectorAll(`.btn-trash-dia[data-ponto="${cdPonto}"], .btn-restore-dia[data-ponto="${cdPonto}"]`).forEach(btn => {
                    btn.disabled = false;
                });
            });

            // Verificar checkboxes de dia marcados
            document.querySelectorAll('.checkbox-dia-ponto:checked').forEach(cb => {
                const cdPonto = cb.dataset.ponto;
                const dataDia = cb.dataset.dia;

                // Habilitar botões do ponto
                const btnTrashPonto = document.querySelector(`.btn-trash-ponto[data-ponto="${cdPonto}"]`);
                if (btnTrashPonto) btnTrashPonto.disabled = false;
                const btnRestorePonto = document.querySelector(`.btn-restore-ponto[data-ponto="${cdPonto}"]`);
                if (btnRestorePonto) btnRestorePonto.disabled = false;

                // Habilitar botões do dia
                const btnTrashDia = document.querySelector(`.btn-trash-dia[data-ponto="${cdPonto}"][data-dia="${dataDia}"]`);
                if (btnTrashDia) btnTrashDia.disabled = false;
                const btnRestoreDia = document.querySelector(`.btn-restore-dia[data-ponto="${cdPonto}"][data-dia="${dataDia}"]`);
                if (btnRestoreDia) btnRestoreDia.disabled = false;
            });
        }
    }

    // Selecionar todos os registros de um ponto de medição
    function selecionarPonto(dataChave, cdPonto, checked) {
        // Buscar todos os registros do ponto
        const params = {
            data: dataChave,
            cd_ponto_medicao: cdPonto,
            cd_unidade: filtrosAtuais.cd_unidade || '',
            cd_localidade: filtrosAtuais.cd_localidade || '',
            tipo_medidor: filtrosAtuais.tipo_medidor || '',
            tipo_leitura: filtrosAtuais.tipo_leitura || '',
            descarte: filtrosAtuais.descarte || '',
            apenas_chaves: 1  // Flag para retornar apenas as chaves
        };

        $.ajax({
            url: 'bd/registroVazaoPressao/getRegistrosPonto.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            timeout: 0,
            success: function (response) {
                if (response.success && response.chaves) {
                    response.chaves.forEach(chave => {
                        if (checked) {
                            registrosSelecionados.add(chave);
                        } else {
                            registrosSelecionados.delete(chave);
                        }
                    });

                    // Atualizar checkboxes dos registros visíveis
                    document.querySelectorAll(`.checkbox-registro[data-dia="${dataChave}"][data-ponto="${cdPonto}"]`).forEach(cb => {
                        cb.checked = checked;
                    });

                    atualizarBotoesExclusao();
                    atualizarBarraExclusao();
                }
            }
        });
    }

    // Selecionar todos os registros de um dia
    function selecionarDia(dataChave, checked) {
        // Buscar todos os registros do dia
        const params = {
            data: dataChave,
            cd_unidade: filtrosAtuais.cd_unidade || '',
            cd_localidade: filtrosAtuais.cd_localidade || '',
            cd_ponto_medicao: filtrosAtuais.cd_ponto_medicao || '',
            tipo_medidor: filtrosAtuais.tipo_medidor || '',
            tipo_leitura: filtrosAtuais.tipo_leitura || '',
            descarte: filtrosAtuais.descarte || '',
            apenas_chaves: 1  // Flag para retornar apenas as chaves
        };

        $.ajax({
            url: 'bd/registroVazaoPressao/getRegistrosDia.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            timeout: 0,
            success: function (response) {
                if (response.success && response.chaves) {
                    response.chaves.forEach(chave => {
                        if (checked) {
                            registrosSelecionados.add(chave);
                        } else {
                            registrosSelecionados.delete(chave);
                        }
                    });

                    // Atualizar checkboxes dos registros e pontos visíveis
                    document.querySelectorAll(`.checkbox-registro[data-dia="${dataChave}"]`).forEach(cb => {
                        cb.checked = checked;
                    });
                    document.querySelectorAll(`.checkbox-ponto[data-dia="${dataChave}"]`).forEach(cb => {
                        cb.checked = checked;
                    });

                    atualizarBotoesExclusao();
                    atualizarBarraExclusao();
                }
            }
        });
    }

    // Cancelar seleção
    function cancelarSelecao() {
        registrosSelecionados.clear();

        // Desmarcar todos os checkboxes
        document.querySelectorAll('.checkbox-selecao').forEach(cb => {
            cb.checked = false;
        });

        // Desabilitar todos os botões de exclusão
        atualizarBotoesExclusao();
        atualizarBarraExclusao();
    }

    // Descartar registros selecionados (exclusão lógica)
    function excluirSelecionados() {
        const quantidade = registrosSelecionados.size;

        if (quantidade === 0) {
            showToast('Nenhum registro selecionado', 'erro');
            return;
        }

        // Modal de confirmação customizado para exclusão em massa
        const modalExclusao = document.createElement('div');
        modalExclusao.className = 'modal-exclusao-confirmacao modal-exclusao-massa';
        modalExclusao.id = 'modalExclusaoMassa';

        const htmlConteudo = `
            <div class="modal-exclusao-overlay" onclick="fecharModalExclusaoMassa(event)">
                <div class="modal-exclusao-container modal-container-grande" onclick="event.stopPropagation()">
                    <!-- Cabeçalho -->
                    <div class="modal-exclusao-header">
                        <ion-icon name="warning-outline" class="icon-warning"></ion-icon>
                        <h3>Confirmar Exclusão em Massa</h3>
                        <button type="button" class="btn-fechar-modal" onclick="fecharModalExclusaoMassa(event)">
                            <ion-icon name="close-outline"></ion-icon>
                        </button>
                    </div>

                    <!-- Corpo -->
                    <div class="modal-exclusao-body">
                        <!-- Resumo -->
                        <div class="info-box info-resumo">
                            <div class="resumo-item">
                                <ion-icon name="document-outline"></ion-icon>
                                <span><strong>${quantidade}</strong> registro(s) selecionado(s)</span>
                            </div>
                        </div>

                        <!-- O que acontecerá -->
                        <div class="info-box info-soft-delete">
                            <div class="info-header">
                                <ion-icon name="arrow-down-outline"></ion-icon>
                                <h4>Como funcionará a exclusão:</h4>
                            </div>
                            
                            <div class="etapa-exclusao">
                                <div class="etapa-numero">1</div>
                                <div class="etapa-conteudo">
                                    <h5>Registros Ativos (ID_SITUACAO = 1)</h5>
                                    <p class="etapa-descricao">Serão <strong>TRANSFORMADOS</strong> em descartados</p>
                                    <ul class="info-lista-pequena">
                                        <li><ion-icon name="arrow-forward-outline"></ion-icon> Mudarão de ID_SITUACAO = 1 para 2</li>
                                        <li><ion-icon name="eye-off-outline"></ion-icon> Desaparecerão da listagem normal</li>
                                        <li><ion-icon name="refresh-outline"></ion-icon> Poderão ser recuperados se necessário</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="etapa-exclusao">
                                <div class="etapa-numero">2</div>
                                <div class="etapa-conteudo">
                                    <h5>Registros Já Descartados (ID_SITUACAO = 2)</h5>
                                    <p class="etapa-descricao">Serão <strong>REMOVIDOS PERMANENTEMENTE</strong></p>
                                    <ul class="info-lista-pequena">
                                        <li><ion-icon name="trash-outline"></ion-icon> Deletados fisicamente do banco</li>
                                        <li><ion-icon name="close-circle-outline"></ion-icon> Ação IRREVERSÍVEL</li>
                                        <li><ion-icon name="alert-circle-outline"></ion-icon> Não poderão ser recuperados</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Aviso -->
                        <div class="aviso-critico">
                            <ion-icon name="alert-outline"></ion-icon>
                            <div>
                                <p><strong>-> Importante:</strong> O resultado dependerá do status de cada registro:</p>
                                <ul style="margin-top: 8px; margin-left: 20px;">
                                    <li><strong>Se Ativo (1):</strong> Será marcado como descartado (2) -> RECUPERÁVEL</li>
                                    <li><strong>Se Descartado (2):</strong> Será removido do banco -> IRREVERSÍVEL</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Rodapé -->
                    <div class="modal-exclusao-footer">
                        <button type="button" class="btn-modal-cancelar" onclick="fecharModalExclusaoMassa(event)">
                            <ion-icon name="close-outline"></ion-icon>
                            Cancelar
                        </button>
                        <button type="button" class="btn-modal-confirmar" onclick="executarExclusaoEmMassa('modalExclusaoMassa')">
                            <ion-icon name="checkmark-outline"></ion-icon>
                            Confirmar Exclusão
                        </button>
                    </div>
                </div>
            </div>
        `;

        modalExclusao.innerHTML = htmlConteudo;
        document.body.appendChild(modalExclusao);

        // Mostrar com animação
        setTimeout(() => {
            modalExclusao.classList.add('active');
        }, 10);
    }

    // Executar exclusão em massa
    function executarExclusaoEmMassa(modalId) {
        const chaves = Array.from(registrosSelecionados);
        console.log('Descartando chaves:', chaves);
        console.log('Total de chaves a enviar:', chaves.length);

        // Mostrar loading
        const btnExcluir = document.querySelector('.btn-modal-confirmar');
        const textoOriginal = btnExcluir.innerHTML;
        btnExcluir.innerHTML = '<ion-icon name="sync-outline" class="spin"></ion-icon> Processando...';
        btnExcluir.disabled = true;

        $.ajax({
            url: 'bd/registroVazaoPressao/excluirRegistrosEmMassa.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ chaves: chaves }),
            dataType: 'json',
            timeout: 0,
            success: function (response) {
                console.log('Resposta completa:', response);
                if (response.debug) {
                    console.log('DEBUG - Chaves recebidas:', response.debug.chaves_recebidas);
                    console.log('DEBUG - Chaves válidas:', response.debug.chaves_validas);
                    console.log('DEBUG - Registros encontrados:', response.debug.registros_encontrados);
                    console.log('DEBUG - Soft delete candidatos:', response.debug.soft_delete_candidatos);
                    console.log('DEBUG - Hard delete candidatos:', response.debug.hard_delete_candidatos);
                }

                // Fechar modal
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.remove('active');
                    setTimeout(() => modal.remove(), 300);
                }

                if (response.success) {
                    // Construir mensagem detalhada conforme o que foi processado
                    let toastMsg = 'Exclusão realizada com sucesso!\n\n';

                    if (response.descartados > 0) {
                        toastMsg += `${response.descartados} registro(s) transformado(s) em descartado(s)\n`;
                        toastMsg += '   (Poderão ser recuperados se necessário)\n\n';
                    }

                    if (response.deletados > 0) {
                        toastMsg += `${response.deletados} registro(s) removido(s) permanentemente\n`;
                        toastMsg += '   (Ação irreversível)';
                    }

                    if (response.descartados === 0 && response.deletados === 0) {
                        toastMsg = 'Nenhum registro foi processado (todos já estavam no mesmo estado)';
                    }

                    showToast(toastMsg, 'sucesso');
                    cancelarSelecao();
                    buscarRegistrosPreservandoEstado();
                } else {
                    showToast(response.message || 'Erro ao processar exclusão', 'erro');
                }
            },
            error: function (xhr, status, error) {
                console.error('Erro AJAX:', status, error);
                console.error('Response:', xhr.responseText);

                // Fechar modal
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.remove('active');
                    setTimeout(() => modal.remove(), 300);
                }

                // Tentar parsear resposta mesmo em caso de erro
                try {
                    const resp = JSON.parse(xhr.responseText);
                    if (resp.message) {
                        showToast(resp.message, 'erro');
                        return;
                    }
                } catch (e) { }

                showToast('Erro ao comunicar com o servidor. Verifique o console.', 'erro');
            },
            complete: function () {
                const btnExcluir = document.querySelector('.btn-modal-confirmar');
                if (btnExcluir) {
                    btnExcluir.innerHTML = textoOriginal;
                    btnExcluir.disabled = false;
                }
            }
        });
    }

    // Fechar modal de exclusão em massa
    function fecharModalExclusaoMassa(event) {
        let modal = event.target;

        if (modal.classList.contains('modal-exclusao-overlay')) {
            modal = modal;
        } else if (modal.classList.contains('btn-fechar-modal') || modal.tagName === 'ION-ICON') {
            modal = event.target.closest('.modal-exclusao-confirmacao');
        } else {
            modal = event.target.closest('.modal-exclusao-confirmacao');
        }

        if (modal) {
            modal.classList.remove('active');
            setTimeout(() => modal.remove(), 300);
        }
    }

    // ============================================
    // Abrir Modal Novo
    // ============================================
    function abrirModalNovo() {
        // TODO: Implementar modal de novo registro
        showToast('Função de novo registro será implementada', 'info');
    }

    // ============================================
    // Toast
    // ============================================
    function showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <ion-icon name="${type === 'sucesso' ? 'checkmark' : type === 'erro' ? 'close' : 'information'}-outline"></ion-icon>
            </div>
            <span class="toast-message">${message}</span>
        `;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // ============================================
    // Ordenação da Tabela
    // ============================================
    let dadosTabela = [];
    let estatisticasDia = {};
    let estatisticasPonto = {}; // Estatísticas por ponto de medição
    let registrosCarregados = {}; // Cache de registros carregados por dia+ponto
    let pontosMedicaoCarregados = {}; // Cache de pontos de medição carregados por dia
    let diasPontoCarregados = {}; // Cache de dias carregados por ponto
    let filtrosAtuais = {}; // Filtros atuais para carregamento sob demanda
    let ordenacaoAtual = { coluna: 'DT_LEITURA', direcao: 'desc' };
    let estadoGruposAbertos = null; // Estado dos grupos abertos para restaurar após ações

    // ============================================
    // Funções para Preservar Estado dos Grupos
    // ============================================

    // Salvar o estado atual dos grupos abertos
    function salvarEstadoGrupos() {
        estadoGruposAbertos = {
            // Grupos de nível 1 (dias ou pontos) expandidos
            gruposExpandidos: [],
            // Subgrupos expandidos (pontos dentro de dias ou dias dentro de pontos)
            subgruposExpandidos: []
        };

        const modoExibicao = document.querySelector('input[name="modoExibicao"]:checked').value;

        // Salvar headers de grupos expandidos
        document.querySelectorAll('.row-group-header.expanded').forEach(header => {
            const grupoId = header.dataset.group || header.dataset.ponto;
            if (grupoId) {
                estadoGruposAbertos.gruposExpandidos.push(grupoId);
            }
        });

        // Salvar headers de subgrupos expandidos
        if (modoExibicao === 'ponto') {
            // Modo Ponto => Dia: subgrupos usam classe row-subgroup-dia
            document.querySelectorAll('.row-subgroup-dia.expanded').forEach(header => {
                const pontoId = header.dataset.ponto;
                const diaId = header.dataset.dia;
                if (pontoId && diaId) {
                    estadoGruposAbertos.subgruposExpandidos.push({
                        ponto: pontoId,
                        dia: diaId
                    });
                }
            });
        } else {
            // Modo Data => Ponto: subgrupos usam classe row-subgroup-header
            document.querySelectorAll('.row-subgroup-header.expanded').forEach(header => {
                const pontoId = header.dataset.ponto;
                const diaId = header.dataset.group;
                if (pontoId && diaId) {
                    estadoGruposAbertos.subgruposExpandidos.push({
                        ponto: pontoId,
                        dia: diaId
                    });
                }
            });
        }

        console.log('Estado dos grupos salvo:', estadoGruposAbertos);
        return estadoGruposAbertos;
    }

    // Restaurar o estado dos grupos após recarregar dados
    function restaurarEstadoGrupos() {
        if (!estadoGruposAbertos) {
            console.log('Nenhum estado de grupos para restaurar');
            return;
        }

        console.log('Restaurando estado dos grupos:', estadoGruposAbertos);

        const modoExibicao = document.querySelector('input[name="modoExibicao"]:checked').value;

        // Restaurar grupos de nível 1
        estadoGruposAbertos.gruposExpandidos.forEach(grupoId => {
            const header = document.querySelector(`.row-group-header[data-group="${grupoId}"], .row-group-header[data-ponto="${grupoId}"]`);
            if (header && !header.classList.contains('expanded')) {
                // Simular clique para expandir
                if (modoExibicao === 'ponto') {
                    toggleGrupoPontoMedicao(grupoId);
                } else {
                    toggleGrupo(grupoId);
                }
            }
        });

        // Restaurar subgrupos (com pequeno delay para garantir que o nível 1 já carregou)
        setTimeout(() => {
            estadoGruposAbertos.subgruposExpandidos.forEach(sub => {
                if (modoExibicao === 'ponto') {
                    const selector = `.row-subgroup-dia[data-ponto="${sub.ponto}"][data-dia="${sub.dia}"]`;
                    const header = document.querySelector(selector);
                    if (header && !header.classList.contains('expanded')) {
                        toggleGrupoDiaPonto(sub.ponto, sub.dia);
                    }
                } else {
                    const selector = `.row-subgroup-header[data-ponto="${sub.ponto}"][data-group="${sub.dia}"]`;
                    const header = document.querySelector(selector);
                    if (header && !header.classList.contains('expanded')) {
                        toggleGrupoPonto(sub.dia, sub.ponto);
                    }
                }
            });

            // Limpar estado após restaurar
            estadoGruposAbertos = null;
        }, 500);
    }

    // Buscar registros preservando o estado dos grupos abertos
    function buscarRegistrosPreservandoEstado() {
        salvarEstadoGrupos();
        buscarRegistros(true);
    }

    // Ordenação desabilitada (carregamento sob demanda não suporta ordenação client-side)
    // TODO: Implementar ordenação no backend se necessário
    function ordenarTabela(coluna) {
        showToast('Ordenação não disponível nesta visualização', 'info');
    }


    function renderizarAgregadores() {
        const tbody = document.getElementById('tabelaRegistros');

        const dias = Object.keys(estatisticasDia);

        if (dias.length === 0) {
            tbody.innerHTML = `<tr><td colspan="11">
                <div class="empty-state">
                    <div class="empty-state-icon"><ion-icon name="folder-open-outline"></ion-icon></div>
                    <h3>Nenhum registro encontrado</h3>
                    <p>Tente ajustar os filtros de busca</p>
                </div>
            </td></tr>`;
            return;
        }

        // Ordenar dias (mais recente primeiro)
        const diasOrdenados = dias.sort((a, b) => b.localeCompare(a));

        let html = '';
        diasOrdenados.forEach((dataChave) => {
            const estatDia = estatisticasDia[dataChave];

            // Formatar data
            const dataObj = new Date(dataChave + 'T12:00:00');
            const dataFormatada = dataObj.toLocaleDateString('pt-BR', {
                weekday: 'long',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
            const dataCapitalizada = dataFormatada.charAt(0).toUpperCase() + dataFormatada.slice(1);

            const totalExibir = estatDia.total || 0;
            const totalNaoDescarte = estatDia.totalNaoDescarte || 0;
            const totalDescarte = estatDia.totalDescarte || 0;
            const qtdPontos = estatDia.qtdPontos || 1;
            const mediaVazao = estatDia.mediaVazao;
            const mediaPressao = estatDia.mediaPressao;
            const mediaNivel = estatDia.mediaNivel;

            // Verificar se está incompleto (menos de 1440 registros com ID_SITUACAO = 1)
            const incompleto = totalNaoDescarte < 1440;
            const classeIncompleto = incompleto ? 'grupo-incompleto' : '';

            // Formatar médias
            const formatarMedia = (valor, unidade) => {
                if (valor === null || valor === undefined) return '-';
                return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + unidade;
            };

            // Linha de cabeçalho do grupo (dia)
            html += `
                <tr class="row-group-header ${classeIncompleto}" data-group="${dataChave}" onclick="toggleGrupo('${dataChave}')">
                    <td colspan="11">
                        <div class="group-content">
                            <div class="group-header-row">
                                <div class="group-toggle">
                                    ${podeEditar ? `<input type="checkbox" class="checkbox-selecao checkbox-dia" data-dia="${dataChave}" onclick="event.stopPropagation(); selecionarDia('${dataChave}', this.checked)" title="Selecionar todos os registros deste dia">` : ''}
                                    <ion-icon name="chevron-forward-outline"></ion-icon>
                                    <span class="group-date">${dataCapitalizada}</span>
                                    <span class="group-count">${totalExibir}</span>
                                    <span class="group-summary">registro${totalExibir > 1 ? 's' : ''} ${qtdPontos > 1 ? `em ${qtdPontos} pontos` : ''}</span>
                                    ${incompleto ? '<span class="group-alert" title="Menos de 1440 registros válidos"><ion-icon name="warning-outline"></ion-icon></span>' : ''}
                                    <div class="group-actions">
                                        ${qtdPontos === 1 ? `<button type="button" class="btn-chart" onclick="event.stopPropagation(); abrirGrafico('${dataChave}')" title="Validação de dados">
                                            <ion-icon name="analytics-outline"></ion-icon>
                                        </button>` : ''}
                                        ${podeEditar ? `<button type="button" class="btn-chart btn-trash" onclick="event.stopPropagation(); excluirSelecionados()" title="Descartar selecionados">
                                            <ion-icon name="trash-outline"></ion-icon>
                                        </button>` : ''}
                                    </div>
                                </div>
                                <div class="group-stats">
                                    <span class="stat-item" title="Média Vazão Efetiva">
                                        <ion-icon name="water-outline"></ion-icon>
                                        ${formatarMedia(mediaVazao, 'l/s')}
                                    </span>
                                    <span class="stat-item" title="Média Pressão">
                                        <ion-icon name="speedometer-outline"></ion-icon>
                                        ${formatarMedia(mediaPressao, 'mca')}
                                    </span>
                                    <span class="stat-item" title="Média Nível Reservatório">
                                        <ion-icon name="layers-outline"></ion-icon>
                                        ${formatarMedia(mediaNivel, 'm')}
                                    </span>
                                    <span class="stat-item stat-valid" title="Registros válidos (não descartados)">
                                        <ion-icon name="checkmark-circle-outline"></ion-icon>
                                        ${totalNaoDescarte}
                                    </span>
                                    ${totalDescarte > 0 ? `
                                    <span class="stat-item stat-descarte" title="Registros descartados" style="background: rgba(239, 68, 68, 0.15); color: #b91c1c;">
                                        <ion-icon name="close-circle-outline" style="color: #ef4444;"></ion-icon>
                                        ${totalDescarte}
                                    </span>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            `;

            // Placeholder para registros (serão carregados sob demanda)
            html += `<tr class="row-data-placeholder" data-group="${dataChave}" style="display: none;">
                <td colspan="11" class="loading-cell">
                    <div class="loading-inline"><ion-icon name="sync-outline" class="spin"></ion-icon> Carregando registros...</div>
                </td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }

    // ============================================
    // Renderizar Agregadores por Ponto de Medição
    // ============================================
    function renderizarAgregadoresPorPonto() {
        const tbody = document.getElementById('tabelaRegistros');

        const pontos = Object.keys(estatisticasPonto);

        if (pontos.length === 0) {
            tbody.innerHTML = `<tr><td colspan="11">
                <div class="empty-state">
                    <div class="empty-state-icon"><ion-icon name="folder-open-outline"></ion-icon></div>
                    <h3>Nenhum registro encontrado</h3>
                    <p>Tente ajustar os filtros de busca</p>
                </div>
            </td></tr>`;
            return;
        }

        // Ordenar pontos por nome
        const pontosOrdenados = pontos.sort((a, b) => {
            const nomeA = estatisticasPonto[a].dsPontoMedicao || '';
            const nomeB = estatisticasPonto[b].dsPontoMedicao || '';
            return nomeA.localeCompare(nomeB);
        });

        let html = '';
        pontosOrdenados.forEach((cdPonto) => {
            const estatPonto = estatisticasPonto[cdPonto];

            const totalExibir = estatPonto.total || 0;
            const totalNaoDescarte = estatPonto.totalNaoDescarte || 0;
            const totalDescarte = estatPonto.totalDescarte || 0;
            const qtdDias = estatPonto.qtdDias || 0;
            const mediaVazao = estatPonto.mediaVazao;
            const mediaPressao = estatPonto.mediaPressao;
            const nivelValor = estatPonto.nivelValor;
            const idTipoMedidor = estatPonto.idTipoMedidor;
            const codigoFormatado = estatPonto.codigoFormatado || cdPonto;

            // Determinar cores e ícone baseado no tipo de medidor
            let cores = { bg: '#f8fafc', bgHover: '#f1f5f9', border: '#cbd5e1' };
            let iconeMedidor = 'water-outline';
            let classeMedidor = 'badge-tipo-medidor';

            if (idTipoMedidor == 4) {
                cores = { bg: 'rgba(249, 115, 22, 0.05)', bgHover: 'rgba(249, 115, 22, 0.1)', border: '#f97316' };
                iconeMedidor = 'speedometer-outline';
                classeMedidor = 'badge-tipo-pressao';
            } else if (idTipoMedidor == 6) {
                cores = { bg: 'rgba(6, 182, 212, 0.05)', bgHover: 'rgba(6, 182, 212, 0.1)', border: '#06b6d4' };
                iconeMedidor = 'layers-outline';
                classeMedidor = 'badge-tipo-reservatorio';
            }

            // Verificar se está incompleto
            const incompleto = totalNaoDescarte < 1440;
            const classeIncompleto = incompleto ? 'grupo-incompleto' : '';

            // Formatar médias
            const formatarMedia = (valor, unidade) => {
                if (valor === null || valor === undefined) return '-';
                return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + unidade;
            };

            // Linha de cabeçalho do grupo (ponto de medição)
            html += `
                <tr class="row-group-header ${classeIncompleto}" data-ponto="${cdPonto}" onclick="toggleGrupoPontoMedicao('${cdPonto}')" style="background: linear-gradient(135deg, ${cores.bg} 0%, ${cores.bgHover} 100%); border-left: 3px solid ${cores.border};">
                    <td colspan="11">
                        <div class="group-content">
                            <div class="group-header-row">
                                <div class="group-toggle">
                                    ${podeEditar ? `<input type="checkbox" class="checkbox-selecao checkbox-ponto-principal" data-ponto="${cdPonto}" onclick="event.stopPropagation(); selecionarPontoMedicao('${cdPonto}', this.checked)" title="Selecionar todos os registros deste ponto">` : ''}
                                    <ion-icon name="chevron-forward-outline"></ion-icon>
                                    <span class="group-date">${codigoFormatado}</span>
                                    <span class="group-count">${totalExibir}</span>
                                    <span class="group-summary">registro${totalExibir > 1 ? 's' : ''} em ${qtdDias} dia${qtdDias > 1 ? 's' : ''}</span>
                                    <div class="subgroup-badges" style="margin-left: 8px;">
                                        <span class="badge-tipo ${classeMedidor}" title="Tipo de Medidor">
                                            <ion-icon name="${iconeMedidor}" style="font-size: 10px; margin-right: 3px;"></ion-icon>
                                            ${estatPonto.dsTipoMedidor || '-'}
                                        </span>
                                        <span class="badge-tipo badge-tipo-leitura" title="Tipo de Leitura">${estatPonto.dsTipoLeitura || '-'}</span>
                                    </div>
                                    ${podeEditar ? `<button type="button" class="btn-chart btn-trash btn-trash-ponto" data-ponto="${cdPonto}" onclick="event.stopPropagation(); excluirTodosPonto('${cdPonto}')" title="Descartar registros selecionados deste ponto" disabled>
                                        <ion-icon name="trash-outline"></ion-icon>
                                    </button>
                                    <button type="button" class="btn-chart btn-restore btn-restore-ponto" data-ponto="${cdPonto}" onclick="event.stopPropagation(); restaurarTodosPonto('${cdPonto}')" title="Restaurar registros selecionados deste ponto" disabled>
                                        <ion-icon name="refresh-outline"></ion-icon>
                                    </button>` : ''}
                                </div>
                                <div class="group-stats">
                                    <span class="stat-item" title="Média Vazão Efetiva">
                                        <ion-icon name="water-outline"></ion-icon>
                                        ${formatarMedia(mediaVazao, 'l/s')}
                                    </span>
                                    <span class="stat-item" title="Média Pressão">
                                        <ion-icon name="speedometer-outline"></ion-icon>
                                        ${formatarMedia(mediaPressao, 'mca')}
                                    </span>
                                    <span class="stat-item" title="Nível Reservatório">
                                        <ion-icon name="layers-outline"></ion-icon>
                                        ${formatarMedia(nivelValor, 'm')}
                                    </span>
                                    <span class="stat-item stat-valid" title="Registros válidos (não descartados)">
                                        <ion-icon name="checkmark-circle-outline"></ion-icon>
                                        ${totalNaoDescarte}
                                    </span>
                                    ${totalDescarte > 0 ? `
                                    <span class="stat-item stat-descarte" title="Registros descartados" style="background: rgba(239, 68, 68, 0.15); color: #b91c1c;">
                                        <ion-icon name="close-circle-outline" style="color: #ef4444;"></ion-icon>
                                        ${totalDescarte}
                                    </span>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            `;

            // Placeholder para dias (serão carregados sob demanda)
            html += `<tr class="row-dia-placeholder" data-ponto="${cdPonto}" style="display: none;">
                <td colspan="11" class="loading-cell">
                    <div class="loading-inline"><ion-icon name="sync-outline" class="spin"></ion-icon> Carregando dias...</div>
                </td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }

    // ============================================
    // Toggle Grupo de Ponto de Medição (modo Ponto => Dia => Registros)
    // ============================================
    function toggleGrupoPontoMedicao(cdPonto) {
        const header = document.querySelector(`.row-group-header[data-ponto="${cdPonto}"]`);
        const isExpanded = header.classList.contains('expanded');

        if (isExpanded) {
            // Colapsar
            header.classList.remove('expanded');
            document.querySelectorAll(`.row-subgroup-dia[data-ponto="${cdPonto}"]`).forEach(row => {
                row.style.display = 'none';
            });
            document.querySelectorAll(`.row-data[data-ponto="${cdPonto}"]`).forEach(row => {
                row.style.display = 'none';
            });
        } else {
            // Expandir
            header.classList.add('expanded');

            // Verificar se já carregou os dias
            if (diasPontoCarregados[cdPonto]) {
                document.querySelectorAll(`.row-subgroup-dia[data-ponto="${cdPonto}"]`).forEach(row => {
                    row.style.display = 'table-row';
                });
            } else {
                // Carregar dias sob demanda
                carregarDiasPonto(cdPonto);
            }
        }
    }

    // ============================================
    // Carregar dias de um ponto de medição
    // ============================================
    function carregarDiasPonto(cdPonto) {
        const placeholder = document.querySelector(`.row-dia-placeholder[data-ponto="${cdPonto}"]`);
        if (placeholder) {
            placeholder.style.display = 'table-row';
        }

        $.get('bd/registroVazaoPressao/getDiasPonto.php', {
            cd_ponto_medicao: cdPonto,
            data_inicio: filtrosAtuais.data_inicio || '',
            data_fim: filtrosAtuais.data_fim || '',
            descarte: filtrosAtuais.descarte || ''
        }, function (response) {
            if (response.success) {
                // CORREÇÃO: Garantir que diasPonto seja sempre um objeto válido
                const diasPonto = response.diasPonto || {};
                diasPontoCarregados[cdPonto] = diasPonto;
                renderizarDiasPonto(cdPonto, diasPonto);
            } else {
                showToast(response.message || 'Erro ao carregar dias', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // Renderizar dias de um ponto de medição
    // ============================================
    function renderizarDiasPonto(cdPonto, diasPonto) {
        const placeholder = document.querySelector(`.row-dia-placeholder[data-ponto="${cdPonto}"]`);
        if (!placeholder) return;

        // CORREÇÃO: Verificar se diasPonto é válido
        if (!diasPonto || typeof diasPonto !== 'object') {
            console.error('diasPonto inválido para ponto:', cdPonto, diasPonto);
            placeholder.innerHTML = `<td colspan="11">
                <div class="empty-inline" style="padding: 20px; text-align: center; color: #94a3b8;">
                    <ion-icon name="alert-circle-outline" style="font-size: 24px; margin-bottom: 8px; display: block;"></ion-icon>
                    Erro ao carregar dados
                </div>
            </td>`;
            return;
        }

        if (Object.keys(diasPonto).length === 0) {
            placeholder.innerHTML = `<td colspan="11">
                <div class="empty-inline" style="padding: 20px; text-align: center; color: #94a3b8;">
                    <ion-icon name="calendar-outline" style="font-size: 24px; margin-bottom: 8px; display: block;"></ion-icon>
                    Nenhum dia encontrado no período filtrado
                </div>
            </td>`;
            return;
        }

        const estatPonto = estatisticasPonto[cdPonto];
        const idTipoMedidor = estatPonto ? estatPonto.idTipoMedidor : 1;

        // Determinar cores baseado no tipo
        let cores = { bg: '#f8fafc', bgHover: '#f1f5f9', border: '#cbd5e1' };
        if (idTipoMedidor == 4) {
            cores = { bg: 'rgba(249, 115, 22, 0.05)', bgHover: 'rgba(249, 115, 22, 0.1)', border: '#f97316' };
        } else if (idTipoMedidor == 6) {
            cores = { bg: 'rgba(6, 182, 212, 0.05)', bgHover: 'rgba(6, 182, 212, 0.1)', border: '#06b6d4' };
        }

        // Formatar médias
        const formatarMedia = (valor, unidade) => {
            if (valor === null || valor === undefined) return '-';
            return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + unidade;
        };

        let html = '';
        const diasOrdenados = Object.keys(diasPonto).sort((a, b) => b.localeCompare(a));

        diasOrdenados.forEach(dataChave => {
            const estatDia = diasPonto[dataChave];

            // Formatar data
            const dataObj = new Date(dataChave + 'T12:00:00');
            const dataFormatada = dataObj.toLocaleDateString('pt-BR', {
                weekday: 'short',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });

            const totalExibir = estatDia.total || 0;
            const totalNaoDescarte = estatDia.totalNaoDescarte || 0;
            const totalDescarte = estatDia.totalDescarte || 0;
            const percentualCompleto = Math.min(100, Math.round((totalNaoDescarte / 1440) * 100));
            const corAlerta = totalNaoDescarte >= 1440 ? '#22c55e' : '#ef4444';

            html += `
                <tr class="row-subgroup-header row-subgroup-dia" data-ponto="${cdPonto}" data-dia="${dataChave}" onclick="toggleGrupoDiaPonto('${cdPonto}', '${dataChave}')" style="display: table-row; background: linear-gradient(135deg, ${cores.bg} 0%, ${cores.bgHover} 100%); border-left: 3px solid ${cores.border};">
                    <td colspan="11" style="padding-left: 40px;">
                        <div class="subgroup-content">
                            <div class="subgroup-header-row">
                                <div class="subgroup-toggle">
                                    ${podeEditar ? `<input type="checkbox" class="checkbox-selecao checkbox-dia-ponto" data-ponto="${cdPonto}" data-dia="${dataChave}" onclick="event.stopPropagation(); selecionarDiaPonto('${cdPonto}', '${dataChave}', this.checked)" title="Selecionar todos os registros deste dia">` : ''}
                                    <ion-icon name="chevron-forward-outline"></ion-icon>
                                    <span class="subgroup-name">${dataFormatada}</span>
                                    <span class="subgroup-count" title="Total de registros">${totalExibir}</span>
                                    <span class="subgroup-alert" title="${percentualCompleto}% completo (${totalNaoDescarte}/1440 registros válidos)" style="color: ${corAlerta};">
                                        <ion-icon name="${totalNaoDescarte >= 1440 ? 'checkmark-circle' : 'alert-circle'}-outline"></ion-icon>
                                    </span>
                                    <button type="button" class="btn-chart" onclick="event.stopPropagation(); abrirGraficoDiaPonto('${cdPonto}', '${dataChave}')" title="Validação de dados">
                                        <ion-icon name="analytics-outline"></ion-icon>
                                    </button>
                                    ${podeEditar ? `<button type="button" class="btn-chart btn-trash btn-trash-dia" data-ponto="${cdPonto}" data-dia="${dataChave}" onclick="event.stopPropagation(); excluirTodosDia('${cdPonto}', '${dataChave}')" title="Descartar registros selecionados deste dia" disabled>
                                        <ion-icon name="trash-outline"></ion-icon>
                                    </button>
                                    <button type="button" class="btn-chart btn-restore btn-restore-dia" data-ponto="${cdPonto}" data-dia="${dataChave}" onclick="event.stopPropagation(); restaurarTodosDia('${cdPonto}', '${dataChave}')" title="Restaurar registros selecionados deste dia" disabled>
                                        <ion-icon name="refresh-outline"></ion-icon>
                                    </button>` : ''}
                                </div>
                                <div class="subgroup-stats">
                                    <span class="stat-item" title="Média Vazão Efetiva">
                                        <ion-icon name="water-outline"></ion-icon>
                                        ${formatarMedia(estatDia.mediaVazao, 'l/s')}
                                    </span>
                                    <span class="stat-item" title="Média Pressão">
                                        <ion-icon name="speedometer-outline"></ion-icon>
                                        ${formatarMedia(estatDia.mediaPressao, 'mca')}
                                    </span>
                                    <span class="stat-item stat-valid" title="Registros válidos">
                                        <ion-icon name="checkmark-circle-outline"></ion-icon>
                                        ${totalNaoDescarte}
                                    </span>
                                    ${totalDescarte > 0 ? `
                                    <span class="stat-item stat-descarte" title="Registros descartados" style="background: rgba(239, 68, 68, 0.15); color: #b91c1c;">
                                        <ion-icon name="close-circle-outline" style="color: #ef4444;"></ion-icon>
                                        ${totalDescarte}
                                    </span>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            `;

            // Placeholder para registros
            html += `<tr class="row-data-placeholder-dia" data-ponto="${cdPonto}" data-dia="${dataChave}" style="display: none;">
                <td colspan="11" class="loading-cell" style="padding-left: 60px;">
                    <div class="loading-inline"><ion-icon name="sync-outline" class="spin"></ion-icon> Carregando registros...</div>
                </td>
            </tr>`;
        });

        // Inserir após o placeholder e remover o placeholder
        placeholder.insertAdjacentHTML('afterend', html);
        placeholder.remove();
    }

    // ============================================
    // Toggle Grupo de Dia dentro de Ponto (modo Ponto => Dia => Registros)
    // ============================================
    function toggleGrupoDiaPonto(cdPonto, dataChave) {
        const header = document.querySelector(`.row-subgroup-dia[data-ponto="${cdPonto}"][data-dia="${dataChave}"]`);
        const isExpanded = header.classList.contains('expanded');
        const cacheKey = `${cdPonto}_${dataChave}`;

        if (isExpanded) {
            // Colapsar
            header.classList.remove('expanded');
            document.querySelectorAll(`.row-data[data-ponto="${cdPonto}"][data-dia="${dataChave}"]`).forEach(row => {
                row.style.display = 'none';
            });
        } else {
            // Expandir
            header.classList.add('expanded');

            // Verificar se já carregou
            if (registrosCarregados[cacheKey]) {
                document.querySelectorAll(`.row-data[data-ponto="${cdPonto}"][data-dia="${dataChave}"]`).forEach(row => {
                    row.style.display = 'table-row';
                });
            } else {
                // Carregar registros
                carregarRegistrosDiaPonto(cdPonto, dataChave);
            }
        }
    }

    // ============================================
    // Carregar registros de um dia de um ponto (modo Ponto => Dia => Registros)
    // ============================================
    function carregarRegistrosDiaPonto(cdPonto, dataChave) {
        const placeholder = document.querySelector(`.row-data-placeholder-dia[data-ponto="${cdPonto}"][data-dia="${dataChave}"]`);
        if (placeholder) {
            placeholder.style.display = 'table-row';
        }

        $.get('bd/registroVazaoPressao/getRegistrosPonto.php', {
            data: dataChave,
            cd_ponto_medicao: cdPonto,
            descarte: filtrosAtuais.descarte || ''
        }, function (response) {
            if (response.success) {
                const cacheKey = `${cdPonto}_${dataChave}`;
                registrosCarregados[cacheKey] = response.data;
                renderizarRegistrosDiaPonto(cdPonto, dataChave, response.data);
            } else {
                showToast(response.message || 'Erro ao carregar registros', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // Renderizar registros de um dia de um ponto (modo Ponto => Dia => Registros)
    // ============================================
    function renderizarRegistrosDiaPonto(cdPonto, dataChave, registros) {
        const placeholder = document.querySelector(`.row-data-placeholder-dia[data-ponto="${cdPonto}"][data-dia="${dataChave}"]`);
        if (!placeholder) return;

        let html = '';
        registros.forEach(item => {
            const dataLeitura = item.DT_LEITURA ?
                new Date(item.DT_LEITURA).toLocaleString('pt-BR') : '-';

            // Tipo Registro
            const tipoRegistro = tiposRegistro[item.ID_TIPO_REGISTRO] || '-';

            // Tipo Vazão
            const tipoVazao = tiposVazao[item.ID_TIPO_VAZAO] || '-';

            // Descarte com badge
            let descarteBadge = '-';
            if (item.ID_SITUACAO) {
                const descarteNome = descartes[item.ID_SITUACAO] || '-';
                let badgeClass = 'badge-secondary';
                if (item.ID_SITUACAO == 1) badgeClass = 'badge-success';
                else if (item.ID_SITUACAO == 2) badgeClass = 'badge-danger';
                descarteBadge = `<span class="badge ${badgeClass}">${descarteNome}</span>`;
            }

            // Formatação dos valores numéricos
            const vazao = item.VL_VAZAO_EFETIVA !== null && item.VL_VAZAO_EFETIVA !== '' ?
                parseFloat(item.VL_VAZAO_EFETIVA).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';
            const pressao = item.VL_PRESSAO !== null && item.VL_PRESSAO !== '' ?
                parseFloat(item.VL_PRESSAO).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';
            const nivelReserv = item.VL_RESERVATORIO !== null && item.VL_RESERVATORIO !== '' ?
                parseFloat(item.VL_RESERVATORIO).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';

            html += `
                <tr class="row-data visible" data-ponto="${cdPonto}" data-dia="${dataChave}" data-chave="${item.CD_CHAVE}" style="padding-left: 60px;">
                    <td style="padding-left: 60px;">
                        ${podeEditar ? `<input type="checkbox" class="checkbox-selecao checkbox-registro" data-chave="${item.CD_CHAVE}" data-ponto="${cdPonto}" data-dia="${dataChave}" onclick="selecionarRegistro(${item.CD_CHAVE}, this.checked)" title="Selecionar registro">` : ''}
                        ${item.DS_UNIDADE || '-'}
                    </td>
                    <td class="truncate">${item.DS_LOCALIDADE || '-'}</td>
                    <td class="truncate">${item.DS_PONTO_MEDICAO || '-'}</td>
                    <td>${tipoRegistro}</td>
                    <td>${tipoVazao}</td>
                    <td>${dataLeitura}</td>
                    <td class="number">${vazao}</td>
                    <td class="number">${pressao}</td>
                    <td class="number">${nivelReserv}</td>
                    <td>${descarteBadge}</td>
                    <td>
                        <div class="table-actions">
                            ${podeEditar ? `
                            <button type="button" class="btn-action delete" onclick="excluirRegistro(${item.CD_CHAVE})" title="Descartar">
                                <ion-icon name="trash-outline"></ion-icon>
                            </button>
                            ${item.ID_SITUACAO == 2 ? `<button type="button" class="btn-action restore" onclick="restaurarRegistro(${item.CD_CHAVE})" title="Restaurar">
                                <ion-icon name="refresh-outline"></ion-icon>
                            </button>` : ''}
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });

        // Inserir registros após o placeholder e remover o placeholder
        placeholder.insertAdjacentHTML('afterend', html);
        placeholder.remove();
    }

    // ============================================
    // Abrir gráfico de um dia de um ponto (modo Ponto => Dia => Registros)
    // Redireciona para operacoes.php com modal de validação
    // ============================================
    function abrirGraficoDiaPonto(cdPonto, dataChave) {
        // Extrair mês e ano da dataChave (formato: YYYY-MM-DD)
        let mes = '';
        let ano = '';
        if (dataChave) {
            const partes = dataChave.split('-');
            if (partes.length >= 2) {
                ano = partes[0];
                mes = parseInt(partes[1]);
            }
        }

        // Redirecionar para operacoes.php com parâmetros para abrir modal de validação
        const url = `operacoes.php?abrirValidacao=1&cdPonto=${cdPonto}&dataValidacao=${dataChave}&mes=${mes}&ano=${ano}`;
        window.location.href = url;
    }

    // ============================================
    // Funções de seleção para modo Ponto => Dia => Registros
    // ============================================
    function selecionarPontoMedicao(cdPonto, checked) {
        // Buscar todos os registros do ponto de medição
        const params = {
            cd_ponto_medicao: cdPonto,
            data_inicio: filtrosAtuais.data_inicio || '',
            data_fim: filtrosAtuais.data_fim || '',
            descarte: filtrosAtuais.descarte || '',
            apenas_chaves: 1
        };

        $.ajax({
            url: 'bd/registroVazaoPressao/getRegistrosPontoMedicao.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            timeout: 0,
            success: function (response) {
                if (response.success && response.chaves) {
                    response.chaves.forEach(chave => {
                        if (checked) {
                            registrosSelecionados.add(chave);
                        } else {
                            registrosSelecionados.delete(chave);
                        }
                    });

                    // Atualizar checkboxes visíveis
                    document.querySelectorAll(`.checkbox-registro[data-ponto="${cdPonto}"]`).forEach(cb => {
                        cb.checked = checked;
                    });
                    document.querySelectorAll(`.checkbox-dia-ponto[data-ponto="${cdPonto}"]`).forEach(cb => {
                        cb.checked = checked;
                    });

                    atualizarBotoesExclusao();
                    atualizarBarraExclusao();
                }
            }
        });
    }

    function selecionarDiaPonto(cdPonto, dataChave, checked) {
        // Buscar todos os registros do dia dentro do ponto
        const params = {
            data: dataChave,
            cd_ponto_medicao: cdPonto,
            descarte: filtrosAtuais.descarte || '',
            apenas_chaves: 1
        };

        $.ajax({
            url: 'bd/registroVazaoPressao/getRegistrosPonto.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            timeout: 0,
            success: function (response) {
                if (response.success && response.chaves) {
                    response.chaves.forEach(chave => {
                        if (checked) {
                            registrosSelecionados.add(chave);
                        } else {
                            registrosSelecionados.delete(chave);
                        }
                    });

                    // Atualizar checkboxes dos registros visíveis
                    document.querySelectorAll(`.checkbox-registro[data-ponto="${cdPonto}"][data-dia="${dataChave}"]`).forEach(cb => {
                        cb.checked = checked;
                    });

                    atualizarBotoesExclusao();
                    atualizarBarraExclusao();
                }
            }
        });
    }

    // ============================================
    // Excluir registros selecionados de um ponto
    // ============================================
    function excluirTodosPonto(cdPonto) {
        const estatPonto = estatisticasPonto[cdPonto];
        const nomePonto = estatPonto ? (estatPonto.codigoFormatado || cdPonto) : cdPonto;
        const quantidade = registrosSelecionados.size;

        if (quantidade === 0) {
            showToast('Nenhum registro selecionado', 'warning');
            return;
        }

        if (!confirm(`Deseja realmente descartar os ${quantidade} registro(s) selecionados do ponto ${nomePonto}?`)) {
            return;
        }

        const chaves = Array.from(registrosSelecionados);
        executarDescarteDireto(chaves);
    }

    // ============================================
    // Excluir registros selecionados de um dia específico de um ponto
    // ============================================
    function excluirTodosDia(cdPonto, dataChave) {
        const dataObj = new Date(dataChave + 'T12:00:00');
        const dataFormatada = dataObj.toLocaleDateString('pt-BR');
        const quantidade = registrosSelecionados.size;

        if (quantidade === 0) {
            showToast('Nenhum registro selecionado', 'warning');
            return;
        }

        if (!confirm(`Deseja realmente descartar os ${quantidade} registro(s) selecionados do dia ${dataFormatada}?`)) {
            return;
        }

        const chaves = Array.from(registrosSelecionados);
        executarDescarteDireto(chaves);
    }

    // ============================================
    // Executar descarte direto (sem modal)
    // ============================================
    function executarDescarteDireto(chaves) {
        showToast(`Descartando ${chaves.length} registro(s)...`, 'info');

        $.ajax({
            url: 'bd/registroVazaoPressao/excluirRegistrosEmMassa.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ chaves: chaves }),
            dataType: 'json',
            timeout: 0,
            success: function (response) {
                console.log('Resposta descarte:', response);

                if (response.success) {
                    let msg = '';
                    if (response.descartados > 0) {
                        msg += `${response.descartados} registro(s) descartado(s)`;
                    }
                    if (response.deletados > 0) {
                        if (msg) msg += ', ';
                        msg += `${response.deletados} removido(s) permanentemente`;
                    }
                    showToast(msg || 'Operação concluída', 'sucesso');
                    cancelarSelecao();
                    buscarRegistrosPreservandoEstado();
                } else {
                    showToast(response.message || 'Erro ao descartar registros', 'erro');
                }
            },
            error: function (xhr, status, error) {
                console.error('Erro AJAX:', status, error);
                console.error('Response:', xhr.responseText);

                try {
                    const resp = JSON.parse(xhr.responseText);
                    if (resp.message) {
                        showToast(resp.message, 'erro');
                        return;
                    }
                } catch (e) { }

                showToast('Erro ao comunicar com o servidor', 'erro');
            }
        });
    }

    // ============================================
    // Restaurar registro individual
    // ============================================
    function restaurarRegistro(id) {
        if (!confirm('Deseja restaurar este registro?')) {
            return;
        }

        $.ajax({
            url: 'bd/registroVazaoPressao/restaurarRegistro.php',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showToast('Registro restaurado com sucesso!', 'sucesso');
                    buscarRegistrosPreservandoEstado();
                } else {
                    showToast(response.message || 'Erro ao restaurar registro', 'erro');
                }
            },
            error: function () {
                showToast('Erro ao comunicar com o servidor', 'erro');
            }
        });
    }

    // ============================================
    // Restaurar registros selecionados de um ponto
    // ============================================
    function restaurarTodosPonto(cdPonto) {
        const estatPonto = estatisticasPonto[cdPonto];
        const nomePonto = estatPonto ? (estatPonto.codigoFormatado || cdPonto) : cdPonto;
        const quantidade = registrosSelecionados.size;

        if (quantidade === 0) {
            showToast('Nenhum registro selecionado', 'warning');
            return;
        }

        if (!confirm(`Deseja realmente restaurar os ${quantidade} registro(s) selecionados do ponto ${nomePonto}?`)) {
            return;
        }

        const chaves = Array.from(registrosSelecionados);
        executarRestauracaoDireto(chaves);
    }

    // ============================================
    // Restaurar registros selecionados de um dia específico
    // ============================================
    function restaurarTodosDia(cdPonto, dataChave) {
        const dataObj = new Date(dataChave + 'T12:00:00');
        const dataFormatada = dataObj.toLocaleDateString('pt-BR');
        const quantidade = registrosSelecionados.size;

        if (quantidade === 0) {
            showToast('Nenhum registro selecionado', 'warning');
            return;
        }

        if (!confirm(`Deseja realmente restaurar os ${quantidade} registro(s) selecionados do dia ${dataFormatada}?`)) {
            return;
        }

        const chaves = Array.from(registrosSelecionados);
        executarRestauracaoDireto(chaves);
    }

    // ============================================
    // Executar restauração em massa
    // ============================================
    function executarRestauracaoDireto(chaves) {
        showToast(`Restaurando ${chaves.length} registro(s)...`, 'info');

        $.ajax({
            url: 'bd/registroVazaoPressao/restaurarRegistrosEmMassa.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ chaves: chaves }),
            dataType: 'json',
            success: function (response) {
                console.log('Resposta restauração:', response);

                if (response.success) {
                    showToast(`${response.restaurados || chaves.length} registro(s) restaurado(s) com sucesso!`, 'sucesso');
                    cancelarSelecao();
                    buscarRegistrosPreservandoEstado();
                } else {
                    showToast(response.message || 'Erro ao restaurar registros', 'erro');
                }
            },
            error: function (xhr, status, error) {
                console.error('Erro AJAX:', status, error);
                console.error('Response:', xhr.responseText);

                try {
                    const resp = JSON.parse(xhr.responseText);
                    if (resp.message) {
                        showToast(resp.message, 'erro');
                        return;
                    }
                } catch (e) { }

                showToast('Erro ao comunicar com o servidor', 'erro');
            }
        });
    }

    // ============================================
    // Renderizar registros de um dia
    // ============================================
    function renderizarRegistrosDia(dataChave, registros) {
        const placeholder = document.querySelector(`.row-data-placeholder[data-group="${dataChave}"]`);
        if (!placeholder) return;

        let html = '';
        registros.forEach(item => {
            const dataLeitura = item.DT_LEITURA ?
                new Date(item.DT_LEITURA).toLocaleString('pt-BR') : '-';

            // Tipo Registro
            const tipoRegistro = tiposRegistro[item.ID_TIPO_REGISTRO] || '-';

            // Tipo Vazão
            const tipoVazao = tiposVazao[item.ID_TIPO_VAZAO] || '-';

            // Descarte com badge (ID_SITUACAO: 1 = Não, 2 = Sim)
            let descarteBadge = '-';
            if (item.ID_SITUACAO) {
                const descarteNome = descartes[item.ID_SITUACAO] || '-';
                let badgeClass = 'badge-secondary';
                if (item.ID_SITUACAO == 1) badgeClass = 'badge-success';
                else if (item.ID_SITUACAO == 2) badgeClass = 'badge-danger';
                descarteBadge = `<span class="badge ${badgeClass}">${descarteNome}</span>`;
            }

            // Formatação dos valores numéricos
            const vazao = item.VL_VAZAO_EFETIVA !== null && item.VL_VAZAO_EFETIVA !== '' ?
                parseFloat(item.VL_VAZAO_EFETIVA).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';
            const pressao = item.VL_PRESSAO !== null && item.VL_PRESSAO !== '' ?
                parseFloat(item.VL_PRESSAO).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';
            const nivelReserv = item.VL_RESERVATORIO !== null && item.VL_RESERVATORIO !== '' ?
                parseFloat(item.VL_RESERVATORIO).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';

            html += `
                <tr class="row-data visible" data-group="${dataChave}">
                    <td class="truncate">${item.DS_UNIDADE || '-'}</td>
                    <td class="truncate">${item.DS_LOCALIDADE || '-'}</td>
                    <td class="truncate">${item.DS_PONTO_MEDICAO || '-'}</td>
                    <td>${tipoRegistro}</td>
                    <td>${tipoVazao}</td>
                    <td>${dataLeitura}</td>
                    <td class="number">${vazao}</td>
                    <td class="number">${pressao}</td>
                    <td class="number">${nivelReserv}</td>
                    <td>${descarteBadge}</td>
                    <td>
                        <div class="table-actions">
                            ${podeEditar ? `
                            <button type="button" class="btn-action delete" onclick="excluirRegistro(${item.CD_CHAVE})" title="Descartar">
                                <ion-icon name="trash-outline"></ion-icon>
                            </button>
                            ${item.ID_SITUACAO == 2 ? `<button type="button" class="btn-action restore" onclick="restaurarRegistro(${item.CD_CHAVE})" title="Restaurar">
                                <ion-icon name="refresh-outline"></ion-icon>
                            </button>` : ''}
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });

        // Inserir registros após o placeholder e remover o placeholder
        placeholder.insertAdjacentHTML('afterend', html);
        placeholder.remove();
    }

    // ============================================
    // Toggle Grupo (Expandir/Colapsar) com carregamento sob demanda
    // ============================================
    function toggleGrupo(dataChave) {
        const header = document.querySelector(`.row-group-header[data-group="${dataChave}"]`);
        const isExpanded = header.classList.contains('expanded');

        if (isExpanded) {
            // Colapsar
            header.classList.remove('expanded');
            // Ocultar subgrupos e registros
            document.querySelectorAll(`.row-subgroup-header[data-group="${dataChave}"]`).forEach(row => {
                row.style.display = 'none';
                row.classList.remove('expanded');
            });
            document.querySelectorAll(`.row-data[data-group="${dataChave}"]`).forEach(row => {
                row.classList.remove('visible');
            });
            document.querySelectorAll(`.row-data-placeholder[data-group="${dataChave}"]`).forEach(row => {
                row.style.display = 'none';
            });
        } else {
            // Expandir
            header.classList.add('expanded');

            // Verificar se filtrou por ponto de medição específico
            const filtrouPorPonto = filtrosAtuais.cd_ponto_medicao && filtrosAtuais.cd_ponto_medicao !== '';

            if (filtrouPorPonto) {
                // Filtrou por ponto específico - carrega registros diretamente
                if (registrosCarregados[dataChave]) {
                    document.querySelectorAll(`.row-data[data-group="${dataChave}"]`).forEach(row => {
                        row.classList.add('visible');
                    });
                } else {
                    carregarRegistrosDia(dataChave);
                }
            } else {
                // Não filtrou por ponto - carrega pontos de medição
                if (pontosMedicaoCarregados[dataChave]) {
                    // Já carregou, apenas mostrar subgrupos
                    document.querySelectorAll(`.row-subgroup-header[data-group="${dataChave}"]`).forEach(row => {
                        row.style.display = 'table-row';
                    });
                } else {
                    // Carregar pontos de medição
                    carregarPontosMedicaoDia(dataChave);
                }
            }
        }
    }

    // ============================================
    // Carregar pontos de medição de um dia
    // ============================================
    function carregarPontosMedicaoDia(dataChave) {
        const placeholder = document.querySelector(`.row-data-placeholder[data-group="${dataChave}"]`);
        if (placeholder) {
            placeholder.style.display = 'table-row';
        }

        const params = {
            data: dataChave,
            cd_unidade: filtrosAtuais.cd_unidade || '',
            cd_localidade: filtrosAtuais.cd_localidade || '',
            tipo_medidor: filtrosAtuais.tipo_medidor || '',
            tipo_leitura: filtrosAtuais.tipo_leitura || '',
            descarte: filtrosAtuais.descarte || ''
        };

        $.get('bd/registroVazaoPressao/getPontosMedicaoDia.php', params, function (response) {
            if (response.success) {
                pontosMedicaoCarregados[dataChave] = response.pontosMedicao;
                renderizarPontosMedicaoDia(dataChave, response.pontosMedicao);
            } else {
                if (placeholder) {
                    placeholder.innerHTML = `<td colspan="11"><div class="error-inline">Erro ao carregar pontos de medição</div></td>`;
                }
                showToast(response.message || 'Erro ao carregar pontos de medição', 'erro');
            }
        }, 'json').fail(function () {
            if (placeholder) {
                placeholder.innerHTML = `<td colspan="11"><div class="error-inline">Erro ao comunicar com o servidor</div></td>`;
            }
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // Renderizar pontos de medição de um dia
    // ============================================
    function renderizarPontosMedicaoDia(dataChave, pontosMedicao) {
        const placeholder = document.querySelector(`.row-data-placeholder[data-group="${dataChave}"]`);
        if (!placeholder) return;

        const formatarMedia = (valor, unidade) => {
            if (valor === null || valor === undefined) return '-';
            return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + unidade;
        };

        // Função para calcular cor do gradiente (vermelho claro -> verde claro)
        // 0 registros = vermelho, 1440 registros = verde
        const calcularCorGradiente = (registrosValidos) => {
            const percentual = Math.min(registrosValidos / 1440, 1); // 0 a 1

            // Cores: vermelho claro (0%) -> amarelo (50%) -> verde claro (100%)
            let r, g, b;

            if (percentual < 0.5) {
                // Vermelho para amarelo (0% a 50%)
                const t = percentual * 2; // 0 a 1
                r = 254; // Mantém vermelho alto
                g = Math.round(202 + (227 - 202) * t); // 202 -> 227
                b = Math.round(202 + (183 - 202) * t); // 202 -> 183
            } else {
                // Amarelo para verde (50% a 100%)
                const t = (percentual - 0.5) * 2; // 0 a 1
                r = Math.round(254 - (254 - 220) * t); // 254 -> 220
                g = Math.round(227 + (252 - 227) * t); // 227 -> 252
                b = Math.round(183 + (231 - 183) * t); // 183 -> 231
            }

            return {
                bg: `rgba(${r}, ${g}, ${b}, 0.4)`,
                bgHover: `rgba(${r}, ${g}, ${b}, 0.6)`,
                border: `rgb(${Math.max(0, r - 40)}, ${Math.max(0, g - 40)}, ${Math.max(0, b - 40)})`
            };
        };

        // Função para cor do ícone de alerta baseado na completude
        const calcularCorAlerta = (registrosValidos) => {
            const percentual = Math.min(registrosValidos / 1440, 1);
            if (percentual >= 1) return '#22c55e'; // Verde
            if (percentual >= 0.7) return '#84cc16'; // Lima
            if (percentual >= 0.5) return '#eab308'; // Amarelo
            if (percentual >= 0.3) return '#f97316'; // Laranja
            return '#ef4444'; // Vermelho
        };

        // Função para obter ícone por tipo de medidor
        const getIconeMedidor = (idTipoMedidor) => {
            const icones = {
                1: 'speedometer-outline',      // M - Macromedidor
                2: 'analytics-outline',        // E - Estação Pitométrica
                4: 'thermometer-outline',      // P - Medidor Pressão
                6: 'water-outline',            // R - Nível Reservatório
                8: 'home-outline'              // H - Hidrômetro
            };
            return icones[idTipoMedidor] || 'hardware-chip-outline';
        };

        let html = '';
        Object.keys(pontosMedicao).forEach(cdPonto => {
            const ponto = pontosMedicao[cdPonto];
            const totalExibir = ponto.total || 0;
            const totalNaoDescarte = ponto.totalNaoDescarte || 0;
            const totalDescarte = ponto.totalDescarte || 0;
            const cores = calcularCorGradiente(totalNaoDescarte);
            const corAlerta = calcularCorAlerta(totalNaoDescarte);
            const percentualCompleto = Math.min(Math.round((totalNaoDescarte / 1440) * 100), 100);

            // Determinar label e valor do nível (Média ou Máx para Reservatório)
            const nivelLabel = ponto.nivelTipo === 'max' ? 'Máx. Nível Reservatório' : 'Média Nível Reservatório';
            const nivelIcon = ponto.nivelTipo === 'max' ? 'arrow-up-outline' : 'layers-outline';

            // Classe e ícone do tipo de medidor
            const idTipoMedidor = ponto.idTipoMedidor || '';
            const classeMedidor = idTipoMedidor ? `badge-medidor-${idTipoMedidor}` : 'badge-tipo-medidor';
            const iconeMedidor = getIconeMedidor(idTipoMedidor);

            html += `
                <tr class="row-subgroup-header" data-group="${dataChave}" data-ponto="${cdPonto}" onclick="toggleGrupoPonto('${dataChave}', '${cdPonto}')" style="display: table-row; background: linear-gradient(135deg, ${cores.bg} 0%, ${cores.bgHover} 100%); border-left: 3px solid ${cores.border};">
                    <td colspan="11">
                        <div class="subgroup-content">
                            <div class="subgroup-header-row">
                                <div class="subgroup-toggle">
                                    ${podeEditar ? `<input type="checkbox" class="checkbox-selecao checkbox-ponto" data-dia="${dataChave}" data-ponto="${cdPonto}" onclick="event.stopPropagation(); selecionarPonto('${dataChave}', '${cdPonto}', this.checked)" title="Selecionar todos os registros deste ponto">` : ''}
                                    <ion-icon name="chevron-forward-outline"></ion-icon>
                                    <span class="subgroup-name">${ponto.codigoFormatado || cdPonto} - ${ponto.dsPontoMedicao || ''}</span>
                                    <div class="subgroup-badges">
                                        <span class="badge-tipo ${classeMedidor}" title="Tipo de Medidor">
                                            <ion-icon name="${iconeMedidor}" style="font-size: 10px; margin-right: 3px;"></ion-icon>
                                            ${ponto.dsTipoMedidor || '-'}
                                        </span>
                                        <span class="badge-tipo badge-tipo-leitura" title="Tipo de Leitura">${ponto.dsTipoLeitura || '-'}</span>
                                    </div>
                                    <span class="subgroup-count" title="Total de registros (filtrado)">${totalExibir}</span>
                                    <span class="subgroup-alert" title="${percentualCompleto}% completo (${totalNaoDescarte}/1440 registros válidos)" style="color: ${corAlerta};">
                                        <ion-icon name="${totalNaoDescarte >= 1440 ? 'checkmark-circle' : 'alert-circle'}-outline"></ion-icon>
                                    </span>
                                    <button type="button" class="btn-chart" onclick="event.stopPropagation(); abrirGraficoPonto('${dataChave}', '${cdPonto}')" title="Validação de dados">
                                        <ion-icon name="analytics-outline"></ion-icon>
                                    </button>
                                </div>
                                <div class="subgroup-stats">
                                    <span class="stat-item" title="Média Vazão Efetiva">
                                        <ion-icon name="water-outline"></ion-icon>
                                        ${formatarMedia(ponto.mediaVazao, 'l/s')}
                                    </span>
                                    <span class="stat-item" title="Média Pressão">
                                        <ion-icon name="speedometer-outline"></ion-icon>
                                        ${formatarMedia(ponto.mediaPressao, 'mca')}
                                    </span>
                                    <span class="stat-item" title="${nivelLabel}">
                                        <ion-icon name="${nivelIcon}"></ion-icon>
                                        ${ponto.nivelTipo === 'max' ? 'Máx: ' : ''}${formatarMedia(ponto.nivelValor, 'm')}
                                    </span>
                                    <span class="stat-item stat-valid" title="Registros válidos (não descartados)" style="background: rgba(34, 197, 94, 0.15); color: #15803d;">
                                        <ion-icon name="checkmark-circle-outline" style="color: #22c55e;"></ion-icon>
                                        ${totalNaoDescarte}
                                    </span>
                                    ${totalDescarte > 0 ? `
                                    <span class="stat-item stat-descarte" title="Registros descartados" style="background: rgba(239, 68, 68, 0.15); color: #b91c1c;">
                                        <ion-icon name="close-circle-outline" style="color: #ef4444;"></ion-icon>
                                        ${totalDescarte}
                                    </span>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            `;

            // Placeholder para registros do ponto
            html += `<tr class="row-data-placeholder" data-group="${dataChave}" data-ponto="${cdPonto}" style="display: none;">
                <td colspan="11" class="loading-cell">
                    <div class="loading-inline"><ion-icon name="sync-outline" class="spin"></ion-icon> Carregando registros...</div>
                </td>
            </tr>`;
        });

        // Inserir após o placeholder e remover o placeholder original
        placeholder.insertAdjacentHTML('afterend', html);
        placeholder.remove();
    }

    // ============================================
    // Toggle Subgrupo (Ponto de Medição)
    // ============================================
    function toggleGrupoPonto(dataChave, cdPonto) {
        const header = document.querySelector(`.row-subgroup-header[data-group="${dataChave}"][data-ponto="${cdPonto}"]`);
        const isExpanded = header.classList.contains('expanded');
        const cacheKey = `${dataChave}_${cdPonto}`;

        if (isExpanded) {
            // Colapsar
            header.classList.remove('expanded');
            document.querySelectorAll(`.row-data[data-group="${dataChave}"][data-ponto="${cdPonto}"]`).forEach(row => {
                row.classList.remove('visible');
            });
        } else {
            // Expandir
            header.classList.add('expanded');

            if (registrosCarregados[cacheKey]) {
                // Já carregou, apenas mostrar
                document.querySelectorAll(`.row-data[data-group="${dataChave}"][data-ponto="${cdPonto}"]`).forEach(row => {
                    row.classList.add('visible');
                });
            } else {
                // Carregar registros do ponto
                carregarRegistrosPonto(dataChave, cdPonto);
            }
        }
    }

    // ============================================
    // Carregar registros de um dia e ponto específico
    // ============================================
    function carregarRegistrosPonto(dataChave, cdPonto) {
        const placeholder = document.querySelector(`.row-data-placeholder[data-group="${dataChave}"][data-ponto="${cdPonto}"]`);
        if (placeholder) {
            placeholder.style.display = 'table-row';
        }

        const params = {
            data: dataChave,
            cd_ponto_medicao: cdPonto,
            descarte: filtrosAtuais.descarte || ''
        };

        $.get('bd/registroVazaoPressao/getRegistrosDia.php', params, function (response) {
            if (response.success) {
                const cacheKey = `${dataChave}_${cdPonto}`;
                registrosCarregados[cacheKey] = response.data;
                renderizarRegistrosPonto(dataChave, cdPonto, response.data);
            } else {
                if (placeholder) {
                    placeholder.innerHTML = `<td colspan="11"><div class="error-inline">Erro ao carregar registros</div></td>`;
                }
                showToast(response.message || 'Erro ao carregar registros', 'erro');
            }
        }, 'json').fail(function () {
            if (placeholder) {
                placeholder.innerHTML = `<td colspan="11"><div class="error-inline">Erro ao comunicar com o servidor</div></td>`;
            }
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // Renderizar registros de um ponto de medição
    // ============================================
    function renderizarRegistrosPonto(dataChave, cdPonto, registros) {
        const placeholder = document.querySelector(`.row-data-placeholder[data-group="${dataChave}"][data-ponto="${cdPonto}"]`);
        if (!placeholder) return;

        let html = '';
        registros.forEach(item => {
            const dataLeitura = item.DT_LEITURA ?
                new Date(item.DT_LEITURA).toLocaleString('pt-BR') : '-';

            const tipoRegistro = tiposRegistro[item.ID_TIPO_REGISTRO] || '-';
            const tipoVazao = tiposVazao[item.ID_TIPO_VAZAO] || '-';

            let descarteBadge = '-';
            if (item.ID_SITUACAO) {
                const descarteNome = descartes[item.ID_SITUACAO] || '-';
                let badgeClass = 'badge-secondary';
                if (item.ID_SITUACAO == 1) badgeClass = 'badge-success';
                else if (item.ID_SITUACAO == 2) badgeClass = 'badge-danger';
                descarteBadge = `<span class="badge ${badgeClass}">${descarteNome}</span>`;
            }

            const vazao = item.VL_VAZAO_EFETIVA !== null && item.VL_VAZAO_EFETIVA !== '' ?
                parseFloat(item.VL_VAZAO_EFETIVA).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';
            const pressao = item.VL_PRESSAO !== null && item.VL_PRESSAO !== '' ?
                parseFloat(item.VL_PRESSAO).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';
            const nivelReserv = item.VL_RESERVATORIO !== null && item.VL_RESERVATORIO !== '' ?
                parseFloat(item.VL_RESERVATORIO).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';

            html += `
                <tr class="row-data visible" data-group="${dataChave}" data-ponto="${cdPonto}" data-chave="${item.CD_CHAVE}">
                    <td class="truncate">
                        ${podeEditar ? `<input type="checkbox" class="checkbox-selecao checkbox-registro" data-chave="${item.CD_CHAVE}" data-dia="${dataChave}" data-ponto="${cdPonto}" onclick="selecionarRegistro(${item.CD_CHAVE}, this.checked)" title="Selecionar registro">` : ''}
                        ${item.DS_UNIDADE || '-'}
                    </td>
                    <td class="truncate">${item.DS_LOCALIDADE || '-'}</td>
                    <td class="truncate">${item.DS_PONTO_MEDICAO || '-'}</td>
                    <td>${tipoRegistro}</td>
                    <td>${tipoVazao}</td>
                    <td>${dataLeitura}</td>
                    <td class="number">${vazao}</td>
                    <td class="number">${pressao}</td>
                    <td class="number">${nivelReserv}</td>
                    <td>${descarteBadge}</td>
                    <td>
                        <div class="table-actions">
                            ${podeEditar ? `
                            <button type="button" class="btn-action delete" onclick="excluirRegistro(${item.CD_CHAVE})" title="Descartar">
                                <ion-icon name="trash-outline"></ion-icon>
                            </button>
                            ${item.ID_SITUACAO == 2 ? `<button type="button" class="btn-action restore" onclick="restaurarRegistro(${item.CD_CHAVE})" title="Restaurar">
                                <ion-icon name="refresh-outline"></ion-icon>
                            </button>` : ''}
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });

        placeholder.insertAdjacentHTML('afterend', html);
        placeholder.remove();
    }

    // ============================================
    // Carregar registros de um dia (quando filtrou por ponto específico)
    // ============================================
    function carregarRegistrosDia(dataChave) {
        const placeholder = document.querySelector(`.row-data-placeholder[data-group="${dataChave}"]`);
        if (placeholder) {
            placeholder.style.display = 'table-row';
        }

        const params = {
            data: dataChave,
            cd_unidade: filtrosAtuais.cd_unidade || '',
            cd_localidade: filtrosAtuais.cd_localidade || '',
            cd_ponto_medicao: filtrosAtuais.cd_ponto_medicao || '',
            tipo_medidor: filtrosAtuais.tipo_medidor || '',
            tipo_leitura: filtrosAtuais.tipo_leitura || '',
            descarte: filtrosAtuais.descarte || ''
        };

        $.get('bd/registroVazaoPressao/getRegistrosDia.php', params, function (response) {
            if (response.success) {
                registrosCarregados[dataChave] = response.data;
                renderizarRegistrosDia(dataChave, response.data);
            } else {
                if (placeholder) {
                    placeholder.innerHTML = `<td colspan="11"><div class="error-inline">Erro ao carregar registros</div></td>`;
                }
                showToast(response.message || 'Erro ao carregar registros', 'erro');
            }
        }, 'json').fail(function () {
            if (placeholder) {
                placeholder.innerHTML = `<td colspan="11"><div class="error-inline">Erro ao comunicar com o servidor</div></td>`;
            }
            showToast('Erro ao comunicar com o servidor', 'erro');
        });
    }

    // ============================================
    // Expandir/Colapsar Todos
    // ============================================
    function expandirTodos() {
        // Apenas expandir o primeiro nível (dias)
        document.querySelectorAll('.row-group-header').forEach(header => {
            const dataChave = header.dataset.group;
            if (!header.classList.contains('expanded')) {
                toggleGrupo(dataChave);
            }
        });
    }

    function colapsarTodos() {
        document.querySelectorAll('.row-group-header').forEach(header => {
            header.classList.remove('expanded');
        });
        document.querySelectorAll('.row-subgroup-header').forEach(header => {
            header.classList.remove('expanded');
            header.style.display = 'none';
        });
        document.querySelectorAll('.row-data').forEach(row => {
            row.classList.remove('visible');
        });
        document.querySelectorAll('.row-data-placeholder').forEach(row => {
            row.style.display = 'none';
        });
    }

    // ============================================
    // Modal Gráfico por Hora - Redireciona para operacoes.php
    // ============================================
    function abrirGrafico(dataChave) {
        const estatDia = estatisticasDia[dataChave];
        if (!estatDia) {
            showToast('Dados não disponíveis para este dia', 'erro');
            return;
        }

        // Extrair mês e ano da dataChave (formato: YYYY-MM-DD)
        let mes = '';
        let ano = '';
        if (dataChave) {
            const partes = dataChave.split('-');
            if (partes.length >= 2) {
                ano = partes[0];
                mes = parseInt(partes[1]);
            }
        }

        // Buscar o ponto de medição do dia
        const params = {
            data: dataChave,
            cd_unidade: filtrosAtuais.cd_unidade || '',
            cd_localidade: filtrosAtuais.cd_localidade || '',
            cd_ponto_medicao: filtrosAtuais.cd_ponto_medicao || '',
            tipo_medidor: filtrosAtuais.tipo_medidor || '',
            tipo_leitura: filtrosAtuais.tipo_leitura || '',
            descarte: filtrosAtuais.descarte || ''
        };

        $.get('bd/registroVazaoPressao/getPontosMedicaoDia.php', params, function (response) {
            if (response.success && response.pontosMedicao && Object.keys(response.pontosMedicao).length > 0) {
                // Pegar o primeiro ponto (quando há apenas 1)
                const cdPonto = Object.keys(response.pontosMedicao)[0];
                const url = `operacoes.php?abrirValidacao=1&cdPonto=${cdPonto}&dataValidacao=${dataChave}&mes=${mes}&ano=${ano}`;
                window.location.href = url;
            } else {
                showToast('Não foi possível identificar o ponto de medição', 'erro');
            }
        }, 'json').fail(function () {
            showToast('Erro ao buscar dados do ponto de medição', 'erro');
        });
    }

    // ============================================
    // Modal Gráfico por Hora - Ponto de Medição
    // Redireciona para operacoes.php
    // ============================================
    function abrirGraficoPonto(dataChave, cdPonto) {
        // Extrair mês e ano da dataChave (formato: YYYY-MM-DD)
        let mes = '';
        let ano = '';
        if (dataChave) {
            const partes = dataChave.split('-');
            if (partes.length >= 2) {
                ano = partes[0];
                mes = parseInt(partes[1]);
            }
        }

        // Redirecionar para operacoes.php com parâmetros para abrir modal de validação
        const url = `operacoes.php?abrirValidacao=1&cdPonto=${cdPonto}&dataValidacao=${dataChave}&mes=${mes}&ano=${ano}`;
        window.location.href = url;
    }

    function fecharModalGrafico(event) {
        if (event && event.target !== event.currentTarget) return;
        document.getElementById('modalGrafico').classList.remove('active');
    }

    function renderizarGraficoModal(porHora, campoValor = 'mediaVazao', unidade = 'l/s', corGradiente = '#3b82f6') {
        const container = document.getElementById('graficoContainer');

        // Dimensões do gráfico
        const largura = 860;
        const altura = 260;
        const paddingLeft = 60;
        const paddingRight = 20;
        const paddingTop = 20;
        const paddingBottom = 40;
        const areaLargura = largura - paddingLeft - paddingRight;
        const areaAltura = altura - paddingTop - paddingBottom;

        // Coletar dados das 24 horas
        let pontos = [];
        let maxValor = 0;
        let minValor = Infinity;

        for (let h = 0; h < 24; h++) {
            const dadosHora = porHora[h];
            const valor = dadosHora ? dadosHora[campoValor] : null;

            if (dadosHora && valor !== null && valor !== undefined) {
                pontos.push({
                    hora: h,
                    valor: valor,
                    validos: dadosHora.validos || 0,
                    total: dadosHora.total || 0,
                    incompleto: (dadosHora.validos || 0) < 60
                });
                if (valor > maxValor) maxValor = valor;
                if (valor < minValor) minValor = valor;
            } else {
                pontos.push({
                    hora: h,
                    valor: null,
                    validos: 0,
                    total: 0,
                    incompleto: true
                });
            }
        }

        // Se não houver dados
        if (maxValor === 0 && minValor === Infinity) {
            container.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #94a3b8;">Sem dados disponíveis para este dia</div>';
            return;
        }

        // Adicionar margem ao range
        const range = maxValor - minValor || 1;
        const marginRange = range * 0.1;
        minValor = Math.max(0, minValor - marginRange);
        maxValor = maxValor + marginRange;
        const rangeAjustado = maxValor - minValor;

        // Calcular posições
        const stepX = areaLargura / 23;

        // Função para converter valor em Y
        const valorParaY = (val) => paddingTop + areaAltura * (1 - (val - minValor) / rangeAjustado);
        const horaParaX = (h) => paddingLeft + h * stepX;

        // Gerar linhas de grade horizontais
        let gradeH = '';
        const numLinhas = 5;
        for (let i = 0; i <= numLinhas; i++) {
            const y = paddingTop + (areaAltura / numLinhas) * i;
            const valor = maxValor - (rangeAjustado / numLinhas) * i;
            gradeH += `<line x1="${paddingLeft}" y1="${y}" x2="${largura - paddingRight}" y2="${y}" class="grafico-grid"/>`;
            gradeH += `<text x="${paddingLeft - 10}" y="${y + 4}" class="grafico-value-label">${valor.toFixed(1)}</text>`;
        }

        // Gerar path da linha e área
        let pathD = '';
        let areaD = '';
        let pontosValidos = pontos.filter(p => p.valor !== null);

        if (pontosValidos.length > 0) {
            // Iniciar área
            areaD = `M ${horaParaX(pontosValidos[0].hora)} ${paddingTop + areaAltura}`;

            pontosValidos.forEach((p, i) => {
                const x = horaParaX(p.hora);
                const y = valorParaY(p.valor);
                if (i === 0) {
                    pathD += `M ${x} ${y}`;
                    areaD += ` L ${x} ${y}`;
                } else {
                    pathD += ` L ${x} ${y}`;
                    areaD += ` L ${x} ${y}`;
                }
            });

            // Fechar área
            areaD += ` L ${horaParaX(pontosValidos[pontosValidos.length - 1].hora)} ${paddingTop + areaAltura} Z`;
        }

        // Gerar círculos dos pontos
        let circulos = '';
        pontos.forEach(p => {
            if (p.valor !== null) {
                const x = horaParaX(p.hora);
                const y = valorParaY(p.valor);
                const classe = p.incompleto ? 'grafico-point incomplete' : 'grafico-point complete';
                const titulo = `${String(p.hora).padStart(2, '0')}h: ${p.valor.toFixed(2)} ${unidade} (${p.validos}/${p.total} registros)`;
                circulos += `<circle cx="${x}" cy="${y}" r="5" class="${classe}"><title>${titulo}</title></circle>`;
            }
        });

        // Gerar labels das horas
        let labels = '';
        for (let h = 0; h < 24; h++) {
            const x = horaParaX(h);
            const p = pontos[h];
            const classe = p.incompleto ? 'grafico-axis-label' : 'grafico-axis-label';
            const cor = p.incompleto ? '#ef4444' : '#64748b';
            labels += `<text x="${x}" y="${altura - 10}" class="${classe}" fill="${cor}">${String(h).padStart(2, '0')}h</text>`;
        }

        // Criar ID único para o gradiente
        const gradienteId = 'gradienteArea_' + Date.now();

        container.innerHTML = `
            <svg class="grafico-svg" viewBox="0 0 ${largura} ${altura}">
                <defs>
                    <linearGradient id="${gradienteId}" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" style="stop-color:${corGradiente};stop-opacity:0.3"/>
                        <stop offset="100%" style="stop-color:${corGradiente};stop-opacity:0.05"/>
                    </linearGradient>
                </defs>
                ${gradeH}
                <line x1="${paddingLeft}" y1="${paddingTop}" x2="${paddingLeft}" y2="${paddingTop + areaAltura}" class="grafico-grid"/>
                <path d="${areaD}" fill="url(#${gradienteId})" class="grafico-area-dynamic"/>
                <path d="${pathD}" stroke="${corGradiente}" class="grafico-line-dynamic"/>
                ${circulos}
                ${labels}
            </svg>
        `;
    }

    // Fechar modal com ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            fecharModalGrafico();
            fecharModalImportacao();
        }
    });
</script>

<!-- Biblioteca SheetJS para ler Excel -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<!-- Modal de Importação de Planilha -->
<div id="modalImportacao" class="modal-importacao" style="display: none;">
    <div class="modal-importacao-content">
        <div class="modal-importacao-header">
            <h3><ion-icon name="cloud-upload-outline"></ion-icon> Importar Planilha</h3>
            <button type="button" class="modal-close-btn" onclick="fecharModalImportacao()">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>

        <div class="modal-importacao-body">
            <!-- Aviso importante -->
            <div class="import-warning">
                <ion-icon name="information-circle-outline"></ion-icon>
                <p>A coluna <strong>PONTO MEDICAO</strong> na PLANILHA de importação deve ser preenchida somente com o
                    número inteiro do código.<br>
                    <strong>Exemplo:</strong> Se o código do PONTO DE MEDICAO for <code>4000-001492-M-1</code>, deve-se
                    preencher somente <code>1492</code>.<br>
                    Caso seja <code>3500-000002-M-1</code>, deve-se preencher somente <code>2</code>.
                </p>
            </div>

            <form id="formImportacao">
                <!-- Data do Evento de Medição -->
                <div class="form-group-import">
                    <label class="form-label-import" for="dataEventoMedicao">Data do Evento de Medição: <span
                            class="required">*</span></label>
                    <input type="date" id="dataEventoMedicao" name="dataEventoMedicao" class="form-input-import"
                        required value="<?= date('Y-m-d') ?>">
                </div>

                <!-- Tipo de Vazão -->
                <div class="form-group-import">
                    <label class="form-label-import">Tipo de Vazão e/ou Pressão: <span class="required">*</span></label>
                    <div class="radio-group-import">
                        <label class="radio-item-import">
                            <input type="radio" name="tipoVazao" value="1" checked>
                            <span class="radio-label-import">Estimada</span>
                        </label>
                        <label class="radio-item-import">
                            <input type="radio" name="tipoVazao" value="2">
                            <span class="radio-label-import">Macromedida</span>
                        </label>
                    </div>
                </div>

                <!-- N° OS -->
                <div class="form-group-import">
                    <label class="form-label-import" for="numOS">N° OS:</label>
                    <input type="text" id="numOS" name="numOS" class="form-input-import"
                        placeholder="Número da Ordem de Serviço">
                </div>

                <!-- Houve Ocorrência -->
                <div class="form-group-import">
                    <label class="checkbox-item-import">
                        <input type="checkbox" id="houveOcorrencia" name="houveOcorrencia" value="1">
                        <span class="checkbox-label-import">Houve ocorrência durante medição?</span>
                    </label>
                </div>

                <!-- Observação -->
                <div class="form-group-import">
                    <label class="form-label-import" for="observacao">Observação:</label>
                    <textarea id="observacao" name="observacao" class="form-textarea-import" rows="3"
                        placeholder="Observações gerais sobre a importação"></textarea>
                </div>

                <!-- Upload de arquivo -->
                <div class="form-group-import">
                    <label class="form-label-import">Arquivo da Planilha: <span class="required">*</span></label>
                    <div class="file-upload-area" id="fileUploadArea">
                        <input type="file" id="arquivoPlanilha" name="arquivoPlanilha" accept=".xls,.xlsx"
                            style="display: none;">
                        <div class="file-upload-placeholder"
                            onclick="document.getElementById('arquivoPlanilha').click()">
                            <ion-icon name="document-outline"></ion-icon>
                            <p>Clique para selecionar ou arraste o arquivo</p>
                            <span class="file-types">Formatos aceitos: .xls, .xlsx</span>
                        </div>
                        <div class="file-selected" id="fileSelected" style="display: none;">
                            <ion-icon name="document-text-outline"></ion-icon>
                            <span id="fileName"></span>
                            <button type="button" class="btn-remove-file" onclick="removerArquivo()">
                                <ion-icon name="close-circle-outline"></ion-icon>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Preview dos dados -->
                <div id="previewDados" class="preview-dados" style="display: none;">
                    <h4><ion-icon name="eye-outline"></ion-icon> Preview dos dados</h4>
                    <div id="previewContent"></div>
                </div>
            </form>

            <!-- Resultado da importação -->
            <div id="resultadoImportacao" class="resultado-importacao" style="display: none;"></div>
        </div>

        <div class="modal-importacao-footer">
            <button type="button" class="btn-cancelar-import" onclick="fecharModalImportacao()">
                <ion-icon name="close-outline"></ion-icon>
                Cancelar
            </button>
            <button type="button" class="btn-importar-submit" onclick="executarImportacao()" id="btnImportar">
                <ion-icon name="cloud-upload-outline"></ion-icon>
                Importar
            </button>
        </div>
    </div>
</div>

<!-- ===============================================================
     PARTE 1: MODAL DE CONFLITOS (adicionar antes de </body>)
     =============================================================== -->
<div id="modalConflitos" class="modal-conflitos" style="display: none;">
    <div class="modal-conflitos-content">
        <div class="modal-conflitos-header">
            <h3><ion-icon name="warning-outline"></ion-icon> Conflitos Encontrados</h3>
            <button type="button" class="modal-close-btn" onclick="fecharModalConflitos()">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>

        <div class="modal-conflitos-body">
            <div class="conflito-alert">
                <ion-icon name="alert-circle-outline"></ion-icon>
                <div class="conflito-alert-text">
                    <strong>Atenção!</strong> Foram encontrados <span id="totalConflitosText">0</span> registro(s)
                    que já existem no banco de dados com situação <strong>válida</strong>.
                </div>
            </div>

            <div class="conflitos-resumo">
                <h4><ion-icon name="list-outline"></ion-icon> Resumo por Ponto de Medição</h4>
                <div id="listaConflitosResumo" class="lista-conflitos-resumo"></div>
            </div>

            <div class="conflitos-detalhes">
                <button type="button" class="btn-toggle-detalhes" onclick="toggleDetalhesConflitos()">
                    <ion-icon name="chevron-down-outline" id="iconToggleDetalhes"></ion-icon>
                    <span id="textToggleDetalhes">Mostrar detalhes dos conflitos</span>
                </button>
                <div id="tabelaConflitosWrapper" class="tabela-conflitos-wrapper" style="display: none;">
                    <table class="tabela-conflitos">
                        <thead>
                            <tr>
                                <th>Ponto</th>
                                <th>Data/Hora</th>
                                <th>Valor Existente</th>
                                <th>Valor Novo</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody id="tabelaConflitosBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="conflitos-opcoes">
                <h4><ion-icon name="options-outline"></ion-icon> O que deseja fazer?</h4>
                <div class="opcoes-radio">
                    <label class="opcao-radio">
                        <input type="radio" name="acaoConflito" value="ignorar" checked>
                        <div class="opcao-content">
                            <ion-icon name="close-circle-outline" class="icon-ignorar"></ion-icon>
                            <div>
                                <strong>Ignorar conflitos</strong>
                                <p>Manter os dados existentes e importar apenas os novos registros sem conflito.</p>
                            </div>
                        </div>
                    </label>
                    <label class="opcao-radio">
                        <input type="radio" name="acaoConflito" value="sobrescrever">
                        <div class="opcao-content">
                            <ion-icon name="swap-horizontal-outline" class="icon-sobrescrever"></ion-icon>
                            <div>
                                <strong>Sobrescrever todos</strong>
                                <p>Descartar os dados existentes (ID_SITUACAO = 2) e inserir os novos valores.</p>
                            </div>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <div class="modal-conflitos-footer">
            <button type="button" class="btn-cancelar-conflito" onclick="fecharModalConflitos()">
                <ion-icon name="close-outline"></ion-icon>
                Cancelar
            </button>
            <button type="button" class="btn-confirmar-conflito" onclick="confirmarImportacaoComConflitos()">
                <ion-icon name="checkmark-outline"></ion-icon>
                Confirmar Importação
            </button>
        </div>
    </div>
</div>

<script>
    // === Funções do Modal de Importação ===
    const podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;

    let dadosPlanilha = null; // Armazena dados lidos da planilha
    let parametrosImportacao = null;

    function abrirModalImportacao() {
        if (!podeEditar) {
            showToast('Você não tem permissão para importar planilhas', 'error');
            return;
        }
        document.getElementById('modalImportacao').style.display = 'flex';
        document.getElementById('formImportacao').reset();
        document.getElementById('resultadoImportacao').style.display = 'none';
        document.getElementById('previewDados').style.display = 'none';
        document.getElementById('fileSelected').style.display = 'none';
        document.querySelector('.file-upload-placeholder').style.display = 'block';
        dadosPlanilha = null;
    }

    function fecharModalImportacao() {
        document.getElementById('modalImportacao').style.display = 'none';
    }

    // Drag and drop para upload
    const fileUploadArea = document.getElementById('fileUploadArea');
    const arquivoInput = document.getElementById('arquivoPlanilha');

    if (fileUploadArea) {
        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('drag-over');
        });

        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.classList.remove('drag-over');
        });

        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('drag-over');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileSelect(files[0]);
            }
        });
    }

    if (arquivoInput) {
        arquivoInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });
    }

    function handleFileSelect(file) {
        const allowedExtensions = ['.xls', '.xlsx'];
        const extension = '.' + file.name.split('.').pop().toLowerCase();

        if (!allowedExtensions.includes(extension)) {
            showToast('Formato inválido! Selecione um arquivo .xls ou .xlsx', 'error');
            return;
        }

        document.getElementById('fileName').textContent = file.name;
        document.querySelector('.file-upload-placeholder').style.display = 'none';
        document.getElementById('fileSelected').style.display = 'flex';

        // Ler arquivo usando SheetJS
        lerPlanilha(file);
    }

    function lerPlanilha(file) {
        const reader = new FileReader();

        reader.onload = function (e) {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array', cellDates: false }); // cellDates: false para obter números

                // Pegar primeira planilha
                const sheetName = workbook.SheetNames[0];
                const sheet = workbook.Sheets[sheetName];

                // Converter para JSON com valores brutos
                const jsonData = XLSX.utils.sheet_to_json(sheet, { header: 1, raw: true, defval: null });

                console.log('=== DEBUG PLANILHA ===');
                console.log('Primeiras linhas:', jsonData.slice(0, 5));

                if (jsonData.length < 2) {
                    showToast('Planilha vazia ou sem dados', 'error');
                    return;
                }

                // Mapear colunas
                const cabecalho = jsonData[0].map(c => (c || '').toString().toUpperCase().trim());
                console.log('Cabeçalho:', cabecalho);

                const colMap = {};
                cabecalho.forEach((nome, idx) => {
                    const nomeNorm = nome.replace(/\s+/g, ' ');

                    if (nomeNorm.includes('DATA') && !nomeNorm.includes('HORA')) {
                        colMap['DATA'] = idx;
                    } else if (nomeNorm === 'HORA') {
                        colMap['HORA'] = idx;
                    } else if (nomeNorm.includes('PONTO') || nomeNorm.includes('MEDICAO') || nomeNorm.includes('MEDICAO')) {
                        colMap['PONTO_MEDICAO'] = idx;
                    } else if ((nomeNorm.includes('TEMP') && nomeNorm.includes('ÁGUA')) || (nomeNorm.includes('TEMP') && nomeNorm.includes('AGUA'))) {
                        colMap['TEMP_AGUA'] = idx;
                    } else if (nomeNorm.includes('TEMP') && nomeNorm.includes('AMB')) {
                        colMap['TEMP_AMB'] = idx;
                    } else if (nomeNorm.includes('PRESSAO') || nomeNorm.includes('PRESSAO')) {
                        colMap['PRESSAO'] = idx;
                    } else if (nomeNorm.includes('VOLUME')) {
                        colMap['VOLUME'] = idx;
                    } else if (nomeNorm.includes('PERIODO') || nomeNorm.includes('PERIODO')) {
                        colMap['PERIODO'] = idx;
                    }
                });

                console.log('Mapeamento de colunas:', colMap);

                // Validar colunas obrigatórias
                const colunasObrigatorias = ['DATA', 'HORA', 'PONTO_MEDICAO'];
                const colunasFaltando = colunasObrigatorias.filter(col => colMap[col] === undefined);

                if (colunasFaltando.length > 0) {
                    showToast('Colunas obrigatórias não encontradas: ' + colunasFaltando.join(', '), 'error');
                    return;
                }

                // Função para converter data do Excel (número) para string dd/mm/yyyy
                function excelDateToString(excelDate) {
                    if (excelDate === null || excelDate === undefined || excelDate === '') {
                        return null;
                    }

                    // Se já é string, retorna como está
                    if (typeof excelDate === 'string') {
                        return excelDate.trim();
                    }

                    // Se é número, converter de data do Excel
                    if (typeof excelDate === 'number') {
                        // Data base do Excel: 30/12/1899 (Windows) ou 01/01/1904 (Mac)
                        // Ajuste para o bug do Excel que considera 1900 como bissexto
                        const excelEpoch = new Date(1899, 11, 30);
                        const msPerDay = 24 * 60 * 60 * 1000;
                        const date = new Date(excelEpoch.getTime() + excelDate * msPerDay);

                        const dia = String(date.getDate()).padStart(2, '0');
                        const mes = String(date.getMonth() + 1).padStart(2, '0');
                        const ano = date.getFullYear();

                        return `${dia}/${mes}/${ano}`;
                    }

                    return String(excelDate);
                }

                // Função para converter hora do Excel (fração do dia) para string HH:mm
                function excelTimeToString(excelTime) {
                    if (excelTime === null || excelTime === undefined || excelTime === '') {
                        return null;
                    }

                    // Se já é string, retorna como está
                    if (typeof excelTime === 'string') {
                        return excelTime.trim();
                    }

                    // Se é número, converter de hora do Excel (fração do dia)
                    if (typeof excelTime === 'number') {
                        // Se for > 1, pode ser data+hora, pegar só a parte fracionária
                        let timeFraction = excelTime;
                        if (excelTime >= 1) {
                            timeFraction = excelTime % 1;
                        }

                        const totalMinutes = Math.round(timeFraction * 24 * 60);
                        const hours = Math.floor(totalMinutes / 60);
                        const minutes = totalMinutes % 60;

                        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
                    }

                    return String(excelTime);
                }

                // Processar dados
                const registros = [];
                const pontosSet = new Set();

                for (let i = 1; i < jsonData.length; i++) {
                    const linha = jsonData[i];

                    // Obter valores brutos
                    const dataRaw = linha[colMap['DATA']];
                    const horaRaw = linha[colMap['HORA']];
                    const pontoMedicao = linha[colMap['PONTO_MEDICAO']];

                    // Converter data e hora
                    const dataConvertida = excelDateToString(dataRaw);
                    const horaConvertida = excelTimeToString(horaRaw);

                    console.log(`Linha ${i + 1}: dataRaw=${dataRaw} (${typeof dataRaw}) -> ${dataConvertida}, horaRaw=${horaRaw} (${typeof horaRaw}) -> ${horaConvertida}, ponto=${pontoMedicao}`);

                    // Pular linhas vazias
                    if (!dataConvertida && !horaConvertida && !pontoMedicao) continue;

                    // Validar obrigatórios
                    if (!dataConvertida) {
                        showToast(`Linha ${i + 1}: Coluna DATA é obrigatória (valor: ${dataRaw})`, 'error');
                        return;
                    }
                    if (!horaConvertida && horaConvertida !== '0' && horaConvertida !== '00:00') {
                        showToast(`Linha ${i + 1}: Coluna HORA é obrigatória (valor: ${horaRaw})`, 'error');
                        return;
                    }
                    if (pontoMedicao === null || pontoMedicao === undefined || pontoMedicao === '') {
                        showToast(`Linha ${i + 1}: Coluna PONTO MEDICAO é obrigatória`, 'error');
                        return;
                    }

                    const cdPonto = parseInt(pontoMedicao);
                    pontosSet.add(cdPonto);

                    registros.push({
                        linha: i + 1,
                        data: dataConvertida,
                        hora: horaConvertida,
                        pontoMedicao: cdPonto,
                        tempAgua: colMap['TEMP_AGUA'] !== undefined ? parseFloat(linha[colMap['TEMP_AGUA']]) || null : null,
                        tempAmb: colMap['TEMP_AMB'] !== undefined ? parseFloat(linha[colMap['TEMP_AMB']]) || null : null,
                        pressao: colMap['PRESSAO'] !== undefined ? parseFloat(linha[colMap['PRESSAO']]) || null : null,
                        volume: colMap['VOLUME'] !== undefined ? parseFloat(linha[colMap['VOLUME']]) || null : null,
                        periodo: colMap['PERIODO'] !== undefined ? parseFloat(linha[colMap['PERIODO']]) || null : null
                    });
                }

                if (registros.length === 0) {
                    showToast('Nenhum dado válido encontrado na planilha', 'error');
                    return;
                }

                console.log('Registros processados:', registros);

                dadosPlanilha = {
                    registros: registros,
                    pontos: Array.from(pontosSet),
                    colMap: colMap
                };

                // Mostrar preview
                mostrarPreview(registros, cabecalho, colMap);

                showToast(`${registros.length} registro(s) lido(s) de ${pontosSet.size} ponto(s)`, 'success');

            } catch (error) {
                console.error('Erro ao ler planilha:', error);
                showToast('Erro ao ler planilha: ' + error.message, 'error');
            }
        };

        reader.readAsArrayBuffer(file);
    }

    function mostrarPreview(registros, cabecalho, colMap) {
        const previewDiv = document.getElementById('previewDados');
        const contentDiv = document.getElementById('previewContent');

        // Mostrar até 5 registros
        const amostra = registros.slice(0, 5);

        let html = '<table class="preview-table"><thead><tr>';
        html += '<th>Data</th><th>Hora</th><th>Ponto</th>';
        if (colMap['TEMP_AGUA'] !== undefined) html += '<th>Temp Água</th>';
        if (colMap['TEMP_AMB'] !== undefined) html += '<th>Temp Amb</th>';
        if (colMap['PRESSAO'] !== undefined) html += '<th>Pressão</th>';
        if (colMap['VOLUME'] !== undefined) html += '<th>Volume</th>';
        if (colMap['PERIODO'] !== undefined) html += '<th>Período</th>';
        html += '</tr></thead><tbody>';

        amostra.forEach(reg => {
            html += '<tr>';
            html += `<td>${reg.data || ''}</td>`;
            html += `<td>${reg.hora || ''}</td>`;
            html += `<td>${reg.pontoMedicao}</td>`;
            if (colMap['TEMP_AGUA'] !== undefined) html += `<td>${reg.tempAgua ?? ''}</td>`;
            if (colMap['TEMP_AMB'] !== undefined) html += `<td>${reg.tempAmb ?? ''}</td>`;
            if (colMap['PRESSAO'] !== undefined) html += `<td>${reg.pressao ?? ''}</td>`;
            if (colMap['VOLUME'] !== undefined) html += `<td>${reg.volume ?? ''}</td>`;
            if (colMap['PERIODO'] !== undefined) html += `<td>${reg.periodo ?? ''}</td>`;
            html += '</tr>';
        });

        html += '</tbody></table>';

        if (registros.length > 5) {
            html += `<p class="preview-info">Mostrando 5 de ${registros.length} registros</p>`;
        }

        // Contar por ponto
        const porPonto = {};
        registros.forEach(r => {
            porPonto[r.pontoMedicao] = (porPonto[r.pontoMedicao] || 0) + 1;
        });

        html += '<p class="preview-info"><strong>Pontos de medição:</strong> ';
        html += Object.entries(porPonto).map(([p, c]) => `${p} (${c})`).join(', ');
        html += '</p>';

        contentDiv.innerHTML = html;
        previewDiv.style.display = 'block';
    }

    function removerArquivo() {
        arquivoInput.value = '';
        document.getElementById('fileSelected').style.display = 'none';
        document.querySelector('.file-upload-placeholder').style.display = 'block';
        document.getElementById('previewDados').style.display = 'none';
        dadosPlanilha = null;
    }

    function executarImportacao() {
        // Limpar resultado anterior
        const resultadoDiv = document.getElementById('resultadoImportacao');
        resultadoDiv.style.display = 'none';
        resultadoDiv.innerHTML = '';
        resultadoDiv.className = 'resultado-importacao';

        // Validar dados da planilha
        if (!dadosPlanilha || !dadosPlanilha.registros || dadosPlanilha.registros.length === 0) {
            showToast('Selecione um arquivo válido para importar', 'error');
            return;
        }

        // Validar data do evento
        const dataEventoMedicao = document.getElementById('dataEventoMedicao').value;
        if (!dataEventoMedicao) {
            showToast('Informe a Data do Evento de Medição', 'error');
            document.getElementById('dataEventoMedicao').focus();
            return;
        }

        // Guardar parâmetros para uso posterior
        parametrosImportacao = {
            registros: dadosPlanilha.registros,
            dataEventoMedicao: dataEventoMedicao,
            tipoVazao: document.querySelector('input[name="tipoVazao"]:checked').value,
            numOS: document.getElementById('numOS').value,
            houveOcorrencia: document.getElementById('houveOcorrencia').checked ? 1 : 0,
            observacao: document.getElementById('observacao').value
        };

        // Mostrar loading no botão
        const btnImportar = document.getElementById('btnImportar');
        btnImportar.classList.add('loading');
        btnImportar.disabled = true;
        btnImportar.innerHTML = '<ion-icon name="sync-outline"></ion-icon> Verificando...';

        // Verificar conflitos antes de importar
        fetch('bd/registroVazaoPressao/verificarConflitosImportacao.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ registros: dadosPlanilha.registros })
        })
            .then(response => response.text())
            .then(text => {
                console.log('Resposta verificação:', text);

                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error('Resposta inválida do servidor');
                }

                // Restaurar botão
                btnImportar.classList.remove('loading');
                btnImportar.disabled = false;
                btnImportar.innerHTML = '<ion-icon name="cloud-upload-outline"></ion-icon> Importar';

                if (data.success) {
                    if (data.temConflitos) {
                        // Existem conflitos - abrir modal para decisão
                        abrirModalConflitos(data, dadosPlanilha, parametrosImportacao);
                    } else {
                        // Sem conflitos - importar direto
                        executarImportacaoFinal(false);
                    }
                } else {
                    showToast(data.message || 'Erro ao verificar conflitos', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                btnImportar.classList.remove('loading');
                btnImportar.disabled = false;
                btnImportar.innerHTML = '<ion-icon name="cloud-upload-outline"></ion-icon> Importar';
                showToast('Erro ao verificar: ' + error.message, 'error');
            });
    }

    // Fechar modal clicando fora
    document.getElementById('modalImportacao')?.addEventListener('click', function (e) {
        if (e.target === this) {
            fecharModalImportacao();
        }
    });

    function executarImportacaoFinal(sobrescrever) {
        const resultadoDiv = document.getElementById('resultadoImportacao');
        const btnImportar = document.getElementById('btnImportar');

        // Mostrar loading
        btnImportar.classList.add('loading');
        btnImportar.disabled = true;
        btnImportar.innerHTML = '<ion-icon name="sync-outline"></ion-icon> Importando...';

        // Adicionar flag de sobrescrita
        const payload = {
            ...parametrosImportacao,
            sobrescrever: sobrescrever
        };

        fetch('bd/registroVazaoPressao/importarPlanilha.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(response => response.json())
            .then(data => {
                // Restaurar botão
                btnImportar.classList.remove('loading');
                btnImportar.disabled = false;
                btnImportar.innerHTML = '<ion-icon name="cloud-upload-outline"></ion-icon> Importar';

                if (data.success) {
                    // Montar mensagem de sucesso
                    let html = '<div class="resultado-sucesso">';
                    html += '<ion-icon name="checkmark-circle-outline"></ion-icon>';
                    html += `<strong>Importação concluída!</strong> ${data.totalRegistros} registro(s) importado(s).`;

                    if (data.totalSobrescritos && data.totalSobrescritos > 0) {
                        html += `<br><small>${data.totalSobrescritos} registro(s) foram sobrescritos.</small>`;
                    }
                    html += '</div>';

                    // Resumo por ponto
                    if (data.resumo && data.resumo.length > 0) {
                        html += '<div class="resultado-resumo"><strong>Resumo por ponto:</strong><ul>';
                        data.resumo.forEach(r => {
                            let detalhes = [];
                            if (r.duplicados > 0) detalhes.push(`${r.duplicados} ignorado(s)`);
                            if (r.sobrescritos > 0) detalhes.push(`${r.sobrescritos} sobrescrito(s)`);
                            if (r.rejeitados > 0) detalhes.push(`${r.rejeitados} rejeitado(s)`);

                            html += `<li><strong>${r.ponto}:</strong> ${r.registros} importado(s)`;
                            if (detalhes.length > 0) {
                                html += ` (${detalhes.join(', ')})`;
                            }
                            html += '</li>';
                        });
                        html += '</ul></div>';
                    }

                    // Avisos
                    if (data.avisos && data.avisos.length > 0) {
                        html += '<div class="resultado-avisos"><strong>Avisos:</strong><ul>';
                        data.avisos.forEach(a => {
                            html += `<li>${a}</li>`;
                        });
                        html += '</ul></div>';
                    }

                    resultadoDiv.innerHTML = html;
                    resultadoDiv.className = 'resultado-importacao sucesso';
                    resultadoDiv.style.display = 'block';

                    showToast(`${data.totalRegistros} registro(s) importado(s) com sucesso!`, 'success');
                } else {
                    // Erro
                    let html = '<div class="resultado-erro">';
                    html += '<ion-icon name="alert-circle-outline"></ion-icon>';
                    html += `<strong>Falha na importação:</strong> ${data.message || 'Erro desconhecido'}`;
                    html += '</div>';

                    if (data.erros && data.erros.length > 0) {
                        html += '<div class="resultado-erros-lista"><strong>Erros encontrados:</strong><ul>';
                        data.erros.slice(0, 10).forEach(e => {
                            html += `<li>${e}</li>`;
                        });
                        if (data.erros.length > 10) {
                            html += `<li>... e mais ${data.erros.length - 10} erro(s)</li>`;
                        }
                        html += '</ul></div>';
                    }

                    resultadoDiv.innerHTML = html;
                    resultadoDiv.className = 'resultado-importacao erro';
                    resultadoDiv.style.display = 'block';

                    showToast(data.message || 'Erro na importação', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                btnImportar.classList.remove('loading');
                btnImportar.disabled = false;
                btnImportar.innerHTML = '<ion-icon name="cloud-upload-outline"></ion-icon> Importar';
                showToast('Erro na importação: ' + error.message, 'error');
            });
    }

    /**
     * Abre o modal de conflitos
     */
    function abrirModalConflitos(conflitos, dados, params) {
        // Atualizar total
        document.getElementById('totalConflitosText').textContent = conflitos.totalConflitos;

        // Renderizar resumo e tabela
        renderizarResumoPontos(conflitos.pontosConflito);
        renderizarTabelaConflitos(conflitos.conflitos);

        // Resetar opção
        document.querySelector('input[name="acaoConflito"][value="ignorar"]').checked = true;

        // Esconder detalhes
        document.getElementById('tabelaConflitosWrapper').style.display = 'none';
        document.getElementById('textToggleDetalhes').textContent = 'Mostrar detalhes dos conflitos';

        // Exibir modal
        document.getElementById('modalConflitos').style.display = 'flex';
    }

    /**
     * Fecha o modal de conflitos
     */
    function fecharModalConflitos() {
        document.getElementById('modalConflitos').style.display = 'none';
    }

    /**
     * Renderiza o resumo de pontos com conflito
     */
    function renderizarResumoPontos(pontosConflito) {
        const container = document.getElementById('listaConflitosResumo');
        let html = '';

        pontosConflito.forEach(ponto => {
            html += `
            <div class="conflito-ponto-card">
                <div class="ponto-info">
                    <div class="ponto-codigo">${ponto.codigo}</div>
                    <div class="ponto-qtd">${ponto.quantidade} conflito(s)</div>
                </div>
                <button type="button" class="btn-visualizar-ponto" 
                        onclick="visualizarDadosPonto('${ponto.cdPonto}', '${ponto.primeiraData}')"
                        title="Visualizar dados existentes">
                    <ion-icon name="eye-outline"></ion-icon>
                </button>
            </div>
        `;
        });

        container.innerHTML = html;
    }

    /**
     * Renderiza a tabela detalhada de conflitos
     */
    function renderizarTabelaConflitos(conflitos) {
        const tbody = document.getElementById('tabelaConflitosBody');
        const conflitosExibir = conflitos.slice(0, 50);
        let html = '';

        conflitosExibir.forEach(c => {
            // Formatar valores existentes
            let valorExistente = '-';
            if (c.valorExistente.vazao !== null && c.valorExistente.vazao !== undefined) {
                valorExistente = `Vazão: ${parseFloat(c.valorExistente.vazao).toFixed(2)} L/s`;
            } else if (c.valorExistente.pressao !== null && c.valorExistente.pressao !== undefined) {
                valorExistente = `Pressão: ${parseFloat(c.valorExistente.pressao).toFixed(2)} mca`;
            }

            // Formatar valores novos
            let valorNovo = '-';
            if (c.valorNovo.vazao !== null && c.valorNovo.vazao !== undefined) {
                valorNovo = `Vazão: ${parseFloat(c.valorNovo.vazao).toFixed(2)} L/s`;
            } else if (c.valorNovo.pressao !== null && c.valorNovo.pressao !== undefined) {
                valorNovo = `Pressão: ${parseFloat(c.valorNovo.pressao).toFixed(2)} mca`;
            }

            html += `
            <tr>
                <td>${c.codigoPonto}</td>
                <td>${c.dtLeitura}</td>
                <td class="valor-existente">${valorExistente}</td>
                <td class="valor-novo">${valorNovo}</td>
                <td>
                    <button type="button" class="btn-ver-registro" 
                            onclick="visualizarDadosPonto('${c.cdPonto}', '${c.data}')">
                        <ion-icon name="eye-outline"></ion-icon>
                    </button>
                </td>
            </tr>
        `;
        });

        if (conflitos.length > 50) {
            html += `
            <tr>
                <td colspan="5" style="text-align: center; color: #94a3b8; font-style: italic;">
                    ... e mais ${conflitos.length - 50} conflito(s) não exibidos
                </td>
            </tr>
        `;
        }

        tbody.innerHTML = html;
    }

    /**
     * Toggle para mostrar/esconder detalhes
     */
    function toggleDetalhesConflitos() {
        const wrapper = document.getElementById('tabelaConflitosWrapper');
        const texto = document.getElementById('textToggleDetalhes');

        if (wrapper.style.display === 'none') {
            wrapper.style.display = 'block';
            texto.textContent = 'Ocultar detalhes dos conflitos';
        } else {
            wrapper.style.display = 'none';
            texto.textContent = 'Mostrar detalhes dos conflitos';
        }
    }

    /**
     * Abre a tela de visualização em nova aba
     */
    function visualizarDadosPonto(cdPonto, data) {
        const url = `registroVazaoPressao.php?cdPonto=${cdPonto}&data=${data}&autoLoad=1`;
        window.open(url, '_blank');
    }

    /**
     * Confirma a importação após decisão sobre conflitos
     */
    function confirmarImportacaoComConflitos() {
        const acaoSelecionada = document.querySelector('input[name="acaoConflito"]:checked').value;
        const sobrescrever = (acaoSelecionada === 'sobrescrever');

        fecharModalConflitos();
        executarImportacaoFinal(sobrescrever);
    }

    /**
     * Verifica parâmetros da URL para carregamento automático
     * Chamar no DOMContentLoaded
     */
    function verificarParametrosURL() {
        const urlParams = new URLSearchParams(window.location.search);
        const cdPonto = urlParams.get('cdPonto');
        const data = urlParams.get('data');
        const autoLoad = urlParams.get('autoLoad');

        if (cdPonto && data) {
            // Selecionar modo ponto
            const radioPonto = document.querySelector('input[name="modoExibicao"][value="ponto"]');
            if (radioPonto) radioPonto.checked = true;

            // Selecionar o ponto
            const selectPonto = document.getElementById('pontoMedicao');
            if (selectPonto) {
                for (let option of selectPonto.options) {
                    if (option.value == cdPonto) {
                        selectPonto.value = cdPonto;
                        break;
                    }
                }
            }

            // Preencher data
            const inputData = document.getElementById('dataFiltro');
            if (inputData) inputData.value = data;

            // Auto buscar
            if (autoLoad === '1') {
                setTimeout(() => {
                    if (typeof buscarRegistros === 'function') {
                        buscarRegistros();
                    }
                }, 500);
            }
        }
    }

    // Fechar modal com ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.getElementById('modalConflitos').style.display === 'flex') {
            fecharModalConflitos();
        }
    });

</script>

<?php include_once 'includes/footer.inc.php'; ?>
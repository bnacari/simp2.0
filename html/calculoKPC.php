<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Tela de Listagem de Cálculo de KPC
 * 
 * Permite consultar e gerenciar os cálculos de KPC (Constante Pitométrica)
 * realizados nas estações pitométricas.
 */

$paginaAtual = 'calculoKPC';

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão para acessar Cálculo de KPC
$temPermissao = temPermissaoTela('Cálculo do KPC', ACESSO_LEITURA);
$podeEditar = temPermissaoTela('Cálculo do KPC', ACESSO_ESCRITA);

if (!$temPermissao) {
    $_SESSION['msg'] = 'Você não tem permissão para acessar esta funcionalidade.';
    header('Location: home.php');
    exit;
}

// Buscar Unidades para filtro
try {
    $sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, CD_CODIGO, DS_NOME FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
    $unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $unidades = [];
}

// Situações
$situacoes = [
    1 => 'Ativo',
    2 => 'Cancelado'
];

// Métodos
$metodos = [
    1 => 'Digital',
    2 => 'Convencional'
];
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<link rel="stylesheet" href="/style/css/calculoKPC.css" />

<div class="page-container">
    <!-- Header da Página -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="calculator-outline"></ion-icon>
                </div>
                <div>
                    <h1>Cálculo de KPC</h1>
                    <p class="page-header-subtitle">Gerenciamento de cálculos da constante pitométrica</p>
                </div>
            </div>
            <?php if ($podeEditar): ?>
                <a href="calculoKPCForm.php" class="btn-novo">
                    <ion-icon name="add-outline"></ion-icon>
                    Novo Cálculo
                </a>
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

        <div class="filters-grid">
            <!-- Unidade -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="business-outline"></ion-icon>
                    Unidade
                </label>
                <select id="selectUnidade" class="form-control select2-unidade">
                    <option value="">Todas as Unidades</option>
                    <?php foreach ($unidades as $u): ?>
                        <option value="<?= $u['CD_UNIDADE'] ?>">
                            <?= htmlspecialchars($u['CD_CODIGO'] . ' - ' . $u['DS_NOME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Localidade -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="location-outline"></ion-icon>
                    Localidade
                </label>
                <select id="selectLocalidade" class="form-control select2-localidade" disabled>
                    <option value="">Selecione uma Unidade primeiro</option>
                </select>
            </div>

            <!-- Ponto de Medição (Autocomplete) -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="pin-outline"></ion-icon>
                    Ponto de Medição
                </label>
                <div class="autocomplete-container">
                    <input type="text" class="form-control" id="filtroPontoMedicaoInput"
                        placeholder="Clique ou digite para buscar..." autocomplete="off">
                    <input type="hidden" id="filtroPontoMedicao">
                    <button type="button" class="btn-limpar-autocomplete" id="btnLimparPonto"
                        onclick="limparPontoMedicao()">
                        <ion-icon name="close-circle"></ion-icon>
                    </button>
                    <div class="autocomplete-dropdown" id="filtroPontoMedicaoDropdown"></div>
                </div>
            </div>

            <!-- Situação (Radio Button) -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="checkmark-circle-outline"></ion-icon>
                    Situação
                </label>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="filtroSituacao" value="" checked>
                        <span class="radio-label">Todas</span>
                    </label>
                    <?php foreach ($situacoes as $id => $nome): ?>
                        <label class="radio-item">
                            <input type="radio" name="filtroSituacao" value="<?= $id ?>">
                            <span class="radio-label"><?= $nome ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Método (Radio Button) -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="options-outline"></ion-icon>
                    Método
                </label>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="filtroMetodo" value="" checked>
                        <span class="radio-label">Todos</span>
                    </label>
                    <?php foreach ($metodos as $id => $nome): ?>
                        <label class="radio-item">
                            <input type="radio" name="filtroMetodo" value="<?= $id ?>">
                            <span class="radio-label"><?= $nome ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Segunda linha: Códigos, Anos e Datas -->
        <div class="filters-grid-row2">
            <!-- Código Inicial -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="code-outline"></ion-icon>
                    Código Inicial
                </label>
                <input type="number" class="form-control" id="filtroCodigoInicial" placeholder="000">
            </div>

            <!-- Ano Inicial -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Ano Inicial
                </label>
                <input type="text" class="form-control" id="filtroAnoInicial" placeholder="AA" maxlength="2">
            </div>

            <!-- Código Final -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="code-outline"></ion-icon>
                    Código Final
                </label>
                <input type="number" class="form-control" id="filtroCodigoFinal" placeholder="999">
            </div>

            <!-- Ano Final -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Ano Final
                </label>
                <input type="text" class="form-control" id="filtroAnoFinal" placeholder="AA" maxlength="2">
            </div>

            <!-- Data Inicial -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="calendar-number-outline"></ion-icon>
                    Data Inicial
                </label>
                <input type="date" class="form-control" id="filtroDataInicial">
            </div>

            <!-- Data Final -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="calendar-number-outline"></ion-icon>
                    Data Final
                </label>
                <input type="date" class="form-control" id="filtroDataFinal">
            </div>
        </div>

        
    </div>

    <!-- Tabela de Dados -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-info">
                <strong id="totalRegistros">0</strong> registro(s) encontrado(s)
            </div>
            <div class="table-actions">
                <?php if ($podeEditar): ?>
                    <button type="button" class="btn-cancelar-selecionados" id="btnCancelarSelecionados" disabled
                        onclick="cancelarSelecionados()">
                        <ion-icon name="close-circle-outline"></ion-icon>
                        Cancelar Selecionados
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="checkAll" onchange="toggleCheckAll()"></th>
                        <th>Unidade</th>
                        <th>Localidade</th>
                        <th>Ponto Medição</th>
                        <th>Código</th>
                        <th>Situação</th>
                        <th>Data Leitura</th>
                        <th>Método</th>
                        <th>DN (mm)</th>
                        <th>KPC</th>
                        <th>Vazão (L/s)</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="tabelaBody">
                    <!-- Preenchido via JavaScript -->
                </tbody>
            </table>
        </div>
        <div class="pagination-container">
            <div class="pagination-info">
                Mostrando <span id="paginacaoInicio">0</span> a <span id="paginacaoFim">0</span> de <span
                    id="paginacaoTotal">0</span>
            </div>
            <div class="pagination" id="paginacao"></div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    // ============================================
    // Variáveis Globais
    // ============================================
    let paginaAtualKPC = 1;
    const registrosPorPagina = 20;
    let totalRegistros = 0;
    const podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;
    let buscaPontoTimeout;
    let filtroTimeout;
    
    // Chave para localStorage
    const STORAGE_KEY_FILTROS = 'calculoKPC_filtros_state';

    // ============================================
    // Persistência de Filtros (localStorage)
    // ============================================
    function salvarFiltros() {
        const state = {
            unidade: $('#selectUnidade').val() || '',
            localidade: $('#selectLocalidade').val() || '',
            ponto: document.getElementById('filtroPontoMedicao').value || '',
            pontoLabel: document.getElementById('filtroPontoMedicaoInput').value || '',
            situacao: $('input[name="filtroSituacao"]:checked').val() || '',
            metodo: $('input[name="filtroMetodo"]:checked').val() || '',
            codigoInicial: document.getElementById('filtroCodigoInicial').value || '',
            anoInicial: document.getElementById('filtroAnoInicial').value || '',
            codigoFinal: document.getElementById('filtroCodigoFinal').value || '',
            anoFinal: document.getElementById('filtroAnoFinal').value || '',
            dataInicial: document.getElementById('filtroDataInicial').value || '',
            dataFinal: document.getElementById('filtroDataFinal').value || '',
            pagina: paginaAtualKPC
        };
        
        localStorage.setItem(STORAGE_KEY_FILTROS, JSON.stringify(state));
    }

    function restaurarFiltros() {
        try {
            const saved = localStorage.getItem(STORAGE_KEY_FILTROS);
            if (!saved) return false;
            
            const state = JSON.parse(saved);
            
            // Unidade
            if (state.unidade) {
                $('#selectUnidade').val(state.unidade).trigger('change.select2');
                
                // Carrega localidades e restaura a selecionada
                carregarLocalidadesComCallback(state.unidade, function() {
                    if (state.localidade) {
                        $('#selectLocalidade').val(state.localidade).trigger('change.select2');
                    }
                });
            }
            
            // Ponto de Medição
            if (state.ponto && state.pontoLabel) {
                document.getElementById('filtroPontoMedicao').value = state.ponto;
                document.getElementById('filtroPontoMedicaoInput').value = state.pontoLabel;
                document.getElementById('btnLimparPonto').style.display = 'flex';
            }
            
            // Situação (radio)
            if (state.situacao !== undefined) {
                $('input[name="filtroSituacao"][value="' + state.situacao + '"]').prop('checked', true);
            }
            
            // Método (radio)
            if (state.metodo !== undefined) {
                $('input[name="filtroMetodo"][value="' + state.metodo + '"]').prop('checked', true);
            }
            
            // Campos de texto
            if (state.codigoInicial) document.getElementById('filtroCodigoInicial').value = state.codigoInicial;
            if (state.anoInicial) document.getElementById('filtroAnoInicial').value = state.anoInicial;
            if (state.codigoFinal) document.getElementById('filtroCodigoFinal').value = state.codigoFinal;
            if (state.anoFinal) document.getElementById('filtroAnoFinal').value = state.anoFinal;
            if (state.dataInicial) document.getElementById('filtroDataInicial').value = state.dataInicial;
            if (state.dataFinal) document.getElementById('filtroDataFinal').value = state.dataFinal;
            
            // Página
            if (state.pagina) paginaAtualKPC = parseInt(state.pagina);
            
            return true;
        } catch (e) {
            console.error('Erro ao restaurar filtros:', e);
            return false;
        }
    }

    function limparFiltrosSalvos() {
        localStorage.removeItem(STORAGE_KEY_FILTROS);
    }

    // ============================================
    // Inicialização
    // ============================================
    $(document).ready(function () {
        // Select2 para Unidade
        $('.select2-unidade').select2({
            placeholder: 'Digite para buscar...',
            allowClear: true,
            language: {
                noResults: function () { return "Nenhuma unidade encontrada"; },
                searching: function () { return "Buscando..."; }
            }
        });

        // Select2 para Localidade
        $('.select2-localidade').select2({
            placeholder: 'Selecione uma Unidade primeiro',
            allowClear: true,
            language: {
                noResults: function () { return "Nenhuma localidade encontrada"; }
            }
        });

        // Inicializa autocomplete de Ponto de Medição
        initAutocompletePontoMedicao();

        // Restaurar filtros salvos
        const temFiltrosSalvos = restaurarFiltros();

        // ============================================
        // Eventos de Filtro Automático
        // ============================================
        
        // Unidade - carrega localidades e filtra
        $('#selectUnidade').on('change', function () {
            const val = $(this).val();
            carregarLocalidades(val);
            limparPontoMedicao();
            paginaAtualKPC = 1;
            salvarFiltros();
            filtrar();
        });

        // Localidade - filtra
        $('#selectLocalidade').on('change', function () {
            limparPontoMedicao();
            paginaAtualKPC = 1;
            salvarFiltros();
            filtrar();
        });

        // Radio buttons - Situação e Método
        $('input[name="filtroSituacao"]').on('change', function () {
            paginaAtualKPC = 1;
            salvarFiltros();
            filtrar();
        });

        $('input[name="filtroMetodo"]').on('change', function () {
            paginaAtualKPC = 1;
            salvarFiltros();
            filtrar();
        });

        // Campos de texto com debounce (Código e Ano)
        $('#filtroCodigoInicial, #filtroCodigoFinal, #filtroAnoInicial, #filtroAnoFinal').on('input', function () {
            clearTimeout(filtroTimeout);
            filtroTimeout = setTimeout(function () {
                paginaAtualKPC = 1;
                salvarFiltros();
                filtrar();
            }, 500);
        });

        // Campos de data
        $('#filtroDataInicial, #filtroDataFinal').on('change', function () {
            paginaAtualKPC = 1;
            salvarFiltros();
            filtrar();
        });

        // Carrega dados iniciais
        filtrar();
    });

    // ============================================
    // Carregar Localidades via AJAX
    // ============================================
    function carregarLocalidades(cdUnidade) {
        carregarLocalidadesComCallback(cdUnidade, null);
    }

    function carregarLocalidadesComCallback(cdUnidade, callback) {
        const select = $('#selectLocalidade');

        if (!cdUnidade) {
            select.prop('disabled', true);
            select.html('<option value="">Selecione uma Unidade primeiro</option>');
            select.trigger('change.select2');
            if (callback) callback();
            return;
        }

        select.prop('disabled', true);
        select.html('<option value="">Carregando...</option>');

        $.ajax({
            url: 'bd/pontoMedicao/getLocalidades.php',
            type: 'GET',
            data: { cd_unidade: cdUnidade },
            dataType: 'json',
            success: function (response) {
                let options = '<option value="">Todas as Localidades</option>';

                if (response.success && response.data.length > 0) {
                    response.data.forEach(function (item) {
                        options += '<option value="' + item.CD_CHAVE + '">' + item.CD_LOCALIDADE + ' - ' + item.DS_NOME + '</option>';
                    });
                }

                select.html(options);
                select.prop('disabled', false);
                select.trigger('change.select2');
                
                if (callback) callback();
            },
            error: function () {
                select.html('<option value="">Erro ao carregar</option>');
                showToast('Erro ao carregar localidades', 'erro');
                if (callback) callback();
            }
        });
    }

    // ============================================
    // Autocomplete Ponto de Medição
    // ============================================
    function initAutocompletePontoMedicao() {
        const input = document.getElementById('filtroPontoMedicaoInput');
        const hidden = document.getElementById('filtroPontoMedicao');
        const dropdown = document.getElementById('filtroPontoMedicaoDropdown');
        const btnLimpar = document.getElementById('btnLimparPonto');
        let highlightedIndex = -1;

        // Ao focar no campo, carrega os pontos (mesmo sem digitar)
        input.addEventListener('focus', function () {
            if (!hidden.value) {
                buscarPontosMedicaoAutocomplete('');
            }
        });

        // Ao digitar, busca com debounce
        input.addEventListener('input', function () {
            const termo = this.value.trim();
            hidden.value = '';
            btnLimpar.style.display = 'none';
            highlightedIndex = -1;

            clearTimeout(buscaPontoTimeout);
            buscaPontoTimeout = setTimeout(function () {
                buscarPontosMedicaoAutocomplete(termo);
            }, 300);
        });

        // Navegação por teclado
        input.addEventListener('keydown', function (e) {
            const items = dropdown.querySelectorAll('.autocomplete-item');

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1);
                updateHighlight(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightedIndex = Math.max(highlightedIndex - 1, 0);
                updateHighlight(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (highlightedIndex >= 0 && items[highlightedIndex]) {
                    items[highlightedIndex].click();
                }
            } else if (e.key === 'Escape') {
                dropdown.classList.remove('active');
            }
        });

        function updateHighlight(items) {
            items.forEach((item, index) => {
                item.classList.toggle('highlighted', index === highlightedIndex);
            });
        }

        // Fecha dropdown ao clicar fora
        document.addEventListener('click', function (e) {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
    }

    function buscarPontosMedicaoAutocomplete(termo) {
        const dropdown = document.getElementById('filtroPontoMedicaoDropdown');
        const cdLocalidade = $('#selectLocalidade').val() || '';
        const cdUnidade = $('#selectUnidade').val() || '';

        dropdown.innerHTML = '<div class="autocomplete-loading">Buscando...</div>';
        dropdown.classList.add('active');

        const letrasTipoMedidor = {
            1: 'M',
            2: 'E',
            4: 'P',
            6: 'R',
            8: 'H'
        };

        // Monta os parâmetros
        let url = 'bd/pontoMedicao/buscarPontosMedicao.php?limite=50';
        if (termo) url += '&busca=' + encodeURIComponent(termo);
        if (cdUnidade) url += '&cd_unidade=' + cdUnidade;
        if (cdLocalidade) url += '&cd_localidade=' + cdLocalidade;

        fetch(url)
            .then(response => response.json())
            .then(response => {
                const data = response.data || response;
                
                if (data && data.length > 0) {
                    dropdown.innerHTML = data.map(function (ponto) {
                        const letra = letrasTipoMedidor[ponto.ID_TIPO_MEDIDOR] || 'X';
                        const codigoFormatado = ponto.CD_LOCALIDADE + '-' +
                            String(ponto.CD_PONTO_MEDICAO).padStart(6, '0') + '-' +
                            letra + '-' + ponto.CD_UNIDADE_CODIGO;
                        const label = codigoFormatado + ' | ' + ponto.DS_NOME;

                        return '<div class="autocomplete-item" onclick="selecionarPontoMedicao(\'' +
                            ponto.CD_PONTO_MEDICAO + '\', \'' + label.replace(/'/g, "\\'") + '\')">' +
                            '<span class="item-code">' + codigoFormatado + '</span>' +
                            '<span class="item-name">' + ponto.DS_NOME + '</span>' +
                            '</div>';
                    }).join('');
                } else {
                    dropdown.innerHTML = '<div class="autocomplete-empty">Nenhum ponto encontrado</div>';
                }
            })
            .catch(function (error) {
                console.error('Erro ao buscar pontos:', error);
                dropdown.innerHTML = '<div class="autocomplete-empty">Erro ao buscar</div>';
            });
    }

    function selecionarPontoMedicao(value, label) {
        const input = document.getElementById('filtroPontoMedicaoInput');
        const hidden = document.getElementById('filtroPontoMedicao');
        const dropdown = document.getElementById('filtroPontoMedicaoDropdown');
        const btnLimpar = document.getElementById('btnLimparPonto');

        input.value = label;
        hidden.value = value;
        dropdown.classList.remove('active');
        btnLimpar.style.display = 'flex';
        
        // Salva e filtra automaticamente
        paginaAtualKPC = 1;
        salvarFiltros();
        filtrar();
    }

    function limparPontoMedicao() {
        document.getElementById('filtroPontoMedicaoInput').value = '';
        document.getElementById('filtroPontoMedicao').value = '';
        document.getElementById('btnLimparPonto').style.display = 'none';
    }

    // ============================================
    // Funções de Filtro
    // ============================================
    function limparFiltros() {
        $('#selectUnidade').val('').trigger('change.select2');
        $('#selectLocalidade').prop('disabled', true).html('<option value="">Selecione uma Unidade primeiro</option>').trigger('change.select2');
        limparPontoMedicao();

        // Radio buttons - marcar "Todas/Todos"
        $('input[name="filtroSituacao"][value=""]').prop('checked', true);
        $('input[name="filtroMetodo"][value=""]').prop('checked', true);

        document.getElementById('filtroCodigoInicial').value = '';
        document.getElementById('filtroAnoInicial').value = '';
        document.getElementById('filtroCodigoFinal').value = '';
        document.getElementById('filtroAnoFinal').value = '';
        document.getElementById('filtroDataInicial').value = '';
        document.getElementById('filtroDataFinal').value = '';

        paginaAtualKPC = 1;
        
        // Limpa localStorage
        limparFiltrosSalvos();
        
        filtrar();
    }

    // ============================================
    // Função Principal de Filtrar (AJAX)
    // ============================================
    function filtrar() {
        mostrarLoading(true);

        const params = new URLSearchParams({
            pagina: paginaAtualKPC,
            limite: registrosPorPagina,
            unidade: $('#selectUnidade').val() || '',
            localidade: $('#selectLocalidade').val() || '',
            ponto: document.getElementById('filtroPontoMedicao').value || '',
            situacao: $('input[name="filtroSituacao"]:checked').val() || '',
            metodo: $('input[name="filtroMetodo"]:checked').val() || '',
            codigoInicial: document.getElementById('filtroCodigoInicial').value,
            anoInicial: document.getElementById('filtroAnoInicial').value,
            codigoFinal: document.getElementById('filtroCodigoFinal').value,
            anoFinal: document.getElementById('filtroAnoFinal').value,
            dataInicial: document.getElementById('filtroDataInicial').value,
            dataFinal: document.getElementById('filtroDataFinal').value
        });

        $.ajax({
            url: 'bd/calculoKPC/listarCalculoKPC.php?' + params.toString(),
            type: 'GET',
            dataType: 'json',
            success: function (data) {
                mostrarLoading(false);
                if (data.success) {
                    totalRegistros = data.total;
                    renderizarTabela(data.data);
                    atualizarPaginacao();
                } else {
                    showToast('Erro ao carregar dados: ' + data.message, 'erro');
                }
            },
            error: function (xhr, status, error) {
                mostrarLoading(false);
                console.error('Erro:', error);
                showToast('Erro ao carregar dados', 'erro');
            }
        });
    }

    // ============================================
    // Renderização da Tabela
    // ============================================
    function renderizarTabela(dados) {
        const tbody = document.getElementById('tabelaBody');
        document.getElementById('totalRegistros').textContent = totalRegistros;

        if (!dados || dados.length === 0) {
            tbody.innerHTML = `
            <tr>
                <td colspan="12">
                    <div class="empty-state">
                        <ion-icon name="document-outline"></ion-icon>
                        <h3>Nenhum registro encontrado</h3>
                        <p>Ajuste os filtros ou cadastre um novo cálculo de KPC.</p>
                    </div>
                </td>
            </tr>
        `;
            return;
        }

        let html = '';
        dados.forEach(function (item) {
            const isCancelado = item.ID_SITUACAO == 2;
            const metodo = item.ID_METODO == 1 ? 'Digital' : (item.ID_METODO == 2 ? 'Convencional' : '-');
            const codigo = item.CD_CODIGO + '-' + item.CD_ANO;
            const dataLeitura = item.DT_LEITURA ? formatarData(item.DT_LEITURA) : '-';
            const diametroNominal = item.VL_DIAMETRO_NOMINAL ? parseFloat(item.VL_DIAMETRO_NOMINAL).toFixed(0) : '-';
            const kpc = item.VL_KPC ? parseFloat(item.VL_KPC).toFixed(10) : '-';
            const vazao = item.VL_VAZAO ? parseFloat(item.VL_VAZAO).toFixed(2) : '-';

            // Código composto do ponto
            const letrasTipo = { 1: 'M', 2: 'E', 4: 'P', 6: 'R', 8: 'H' };
            const letraTipo = letrasTipo[item.ID_TIPO_MEDIDOR] || 'X';
            const codigoPonto = item.CD_LOCALIDADE + '-' +
                String(item.CD_PONTO_MEDICAO).padStart(6, '0') + '-' +
                letraTipo + '-' +
                item.CD_UNIDADE;

            html += `
            <tr class="${isCancelado ? 'cancelado' : ''}">
                <td>
                    <input type="checkbox" class="checkItem" value="${item.CD_CHAVE}" 
                           onchange="atualizarBotaoCancelar()" ${isCancelado ? 'disabled' : ''}>
                </td>
                <td>${item.DS_UNIDADE || '-'}</td>
                <td>${item.DS_LOCALIDADE || '-'}</td>
                <td>${codigoPonto}</td>
                <td><strong>${codigo}</strong></td>
                <td>
                    <span class="badge ${isCancelado ? 'badge-cancelado' : 'badge-ativo'}">
                        ${isCancelado ? 'Cancelado' : 'Ativo'}
                    </span>
                </td>
                <td>${dataLeitura}</td>
                <td>${metodo}</td>
                <td>${diametroNominal}</td>
                <td>${kpc}</td>
                <td>${vazao}</td>
                <td>
                    <div class="acoes-cell">
                        <a href="calculoKPCView.php?id=${item.CD_CHAVE}" class="btn-acao visualizar" title="Visualizar">
                            <ion-icon name="eye-outline"></ion-icon>
                        </a>
                        ${podeEditar && !isCancelado ? `
                        <a href="calculoKPCForm.php?id=${item.CD_CHAVE}" class="btn-acao editar" title="Editar">
                            <ion-icon name="create-outline"></ion-icon>
                        </a>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
        });

        tbody.innerHTML = html;
    }

    // ============================================
    // Paginação
    // ============================================
    function atualizarPaginacao() {
        const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);
        const inicio = (paginaAtualKPC - 1) * registrosPorPagina + 1;
        const fim = Math.min(paginaAtualKPC * registrosPorPagina, totalRegistros);

        document.getElementById('paginacaoInicio').textContent = totalRegistros > 0 ? inicio : 0;
        document.getElementById('paginacaoFim').textContent = fim;
        document.getElementById('paginacaoTotal').textContent = totalRegistros;

        const paginacao = document.getElementById('paginacao');
        let html = '';

        html += '<button onclick="irParaPagina(' + (paginaAtualKPC - 1) + ')" ' + (paginaAtualKPC === 1 ? 'disabled' : '') + '>' +
            '<ion-icon name="chevron-back-outline"></ion-icon></button>';

        const maxPaginas = 5;
        let startPage = Math.max(1, paginaAtualKPC - Math.floor(maxPaginas / 2));
        let endPage = Math.min(totalPaginas, startPage + maxPaginas - 1);

        if (endPage - startPage < maxPaginas - 1) {
            startPage = Math.max(1, endPage - maxPaginas + 1);
        }

        if (startPage > 1) {
            html += '<button onclick="irParaPagina(1)">1</button>';
            if (startPage > 2) html += '<button disabled>...</button>';
        }

        for (let i = startPage; i <= endPage; i++) {
            html += '<button onclick="irParaPagina(' + i + ')" class="' + (i === paginaAtualKPC ? 'active' : '') + '">' + i + '</button>';
        }

        if (endPage < totalPaginas) {
            if (endPage < totalPaginas - 1) html += '<button disabled>...</button>';
            html += '<button onclick="irParaPagina(' + totalPaginas + ')">' + totalPaginas + '</button>';
        }

        html += '<button onclick="irParaPagina(' + (paginaAtualKPC + 1) + ')" ' + 
            (paginaAtualKPC === totalPaginas || totalPaginas === 0 ? 'disabled' : '') + '>' +
            '<ion-icon name="chevron-forward-outline"></ion-icon></button>';

        paginacao.innerHTML = html;
    }

    function irParaPagina(pagina) {
        const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);
        if (pagina >= 1 && pagina <= totalPaginas) {
            paginaAtualKPC = pagina;
            salvarFiltros();
            filtrar();
        }
    }

    // ============================================
    // Seleção e Cancelamento
    // ============================================
    function toggleCheckAll() {
        const checkAll = document.getElementById('checkAll');
        document.querySelectorAll('.checkItem:not(:disabled)').forEach(function (cb) {
            cb.checked = checkAll.checked;
        });
        atualizarBotaoCancelar();
    }

    function atualizarBotaoCancelar() {
        const selecionados = document.querySelectorAll('.checkItem:checked').length;
        const btn = document.getElementById('btnCancelarSelecionados');
        if (btn) {
            btn.disabled = selecionados === 0;
        }
    }

    function cancelarSelecionados() {
        const selecionados = [];
        document.querySelectorAll('.checkItem:checked').forEach(function (cb) {
            selecionados.push(cb.value);
        });

        if (selecionados.length === 0) {
            showToast('Selecione ao menos um registro', 'alerta');
            return;
        }

        if (!confirm('Deseja realmente cancelar ' + selecionados.length + ' registro(s)?')) {
            return;
        }

        mostrarLoading(true);

        $.ajax({
            url: 'bd/calculoKPC/cancelarCalculoKPC.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ ids: selecionados }),
            dataType: 'json',
            success: function (data) {
                mostrarLoading(false);
                if (data.success) {
                    showToast('Registros cancelados com sucesso!', 'sucesso');
                    filtrar();
                } else {
                    showToast('Erro: ' + data.message, 'erro');
                }
            },
            error: function () {
                mostrarLoading(false);
                showToast('Erro ao cancelar registros', 'erro');
            }
        });
    }

    // ============================================
    // Utilitários
    // ============================================
    function mostrarLoading(show) {
        document.getElementById('loadingOverlay').classList.toggle('active', show);
    }

    function formatarData(dataStr) {
        if (!dataStr) return '-';
        const data = new Date(dataStr);
        return data.toLocaleDateString('pt-BR');
    }

    // ============================================
    // Toast System
    // ============================================
    function showToast(message, type, duration) {
        type = type || 'info';
        duration = duration || 5000;

        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const icons = {
            sucesso: 'checkmark-circle',
            erro: 'close-circle',
            alerta: 'alert-circle',
            info: 'information-circle'
        };

        const toast = document.createElement('div');
        toast.className = 'toast ' + type;
        toast.innerHTML =
            '<div class="toast-icon">' +
            '<ion-icon name="' + (icons[type] || icons.info) + '"></ion-icon>' +
            '</div>' +
            '<div class="toast-content">' +
            '<p class="toast-message">' + message + '</p>' +
            '</div>' +
            '<button class="toast-close" onclick="this.parentElement.remove()">' +
            '<ion-icon name="close"></ion-icon>' +
            '</button>';

        container.appendChild(toast);
        setTimeout(function () { toast.classList.add('show'); }, 10);
        setTimeout(function () {
            toast.classList.remove('show');
            setTimeout(function () { toast.remove(); }, 300);
        }, duration);
    }
</script>

<?php include_once 'includes/footer.inc.php'; ?>
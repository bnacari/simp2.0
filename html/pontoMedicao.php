<?php

include_once 'includes/header.inc.php';
include_once 'bd/conexao.php';

// Verifica permissao para acessar Ponto de Medicao (busca por nome na tabela FUNCIONALIDADE)
// Recarregar permissões do banco (garante que estão atualizadas)
recarregarPermissoesUsuario();

// Agora verificar permissão
exigePermissaoTela('Cadastro de Ponto de Medição', ACESSO_LEITURA);

// Permissao do usuario para este modulo
$podeEditar = podeEditarTela('Cadastro de Ponto de Medição');

include_once 'includes/menu.inc.php';

// Buscar Unidades
$sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME, CD_CODIGO FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

// Tipos de Medidor (fixo)
$tiposMedidor = [
    ['value' => '', 'text' => 'Todos os Tipos'],
    ['value' => '1', 'text' => 'M - Macromedidor'],
    ['value' => '2', 'text' => 'E - Estacao Pitometrica'],
    ['value' => '4', 'text' => 'P - Medidor Pressao'],
    ['value' => '8', 'text' => 'H - Hidrometro'],
    ['value' => '6', 'text' => 'R - Nivel Reservatorio'],
];

// Tipos de Leitura (fixo)
$tiposLeitura = [
    ['value' => '', 'text' => 'Todos os Tipos'],
    ['value' => '2', 'text' => 'Manual'],
    ['value' => '4', 'text' => 'Planilha'],
    ['value' => '8', 'text' => 'Integracao CCO'],
    ['value' => '6', 'text' => 'Integracao CesanLims'],
];
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- CSS da pagina -->
<link href="style/css/pontoMedicao.css?v=<?= time() ?>" rel="stylesheet" />

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="pin-outline"></ion-icon>
                </div>
                <div>
                    <h1>Ponto de Medição</h1>
                    <p class="page-header-subtitle">Gerencie os pontos de medição do sistema</p>
                </div>
            </div>
            <?php if ($podeEditar): ?>
                <a href="pontoMedicaoForm.php" class="btn-novo">
                    <ion-icon name="add-outline"></ion-icon>
                    Novo Ponto
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters -->
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
                    <?php foreach ($unidades as $unidade): ?>
                        <option value="<?= $unidade['CD_UNIDADE'] ?>">
                            <?= htmlspecialchars($unidade['CD_CODIGO'] . ' - ' . $unidade['DS_NOME']) ?>
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

            <!-- Ponto de Medicao -->
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

            <!-- Tipo Medidor -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="hardware-chip-outline"></ion-icon>
                    Tipo Medidor
                </label>
                <select id="selectTipoMedidor" class="form-control select2-default">
                    <?php foreach ($tiposMedidor as $tipo): ?>
                        <option value="<?= $tipo['value'] ?>"><?= $tipo['text'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Tipo Leitura -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="reader-outline"></ion-icon>
                    Tipo de Leitura
                </label>
                <select id="selectTipoLeitura" class="form-control select2-default">
                    <?php foreach ($tiposLeitura as $tipo): ?>
                        <option value="<?= $tipo['value'] ?>"><?= $tipo['text'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Campo de Busca Geral -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="search-outline"></ion-icon>
                    Busca Geral
                </label>
                <div class="input-search-wrapper">
                    <ion-icon name="search-outline" class="input-search-icon"></ion-icon>
                    <input type="text" id="inputBusca" class="form-control input-search"
                        placeholder="Buscar por nome, código...">
                    <button type="button" class="btn-search-clear" onclick="limparBusca()" style="display: none;">
                        <ion-icon name="close-circle"></ion-icon>
                    </button>
                </div>
            </div>

            <!-- Status -->
            <div class="form-group form-group-full">
                <label class="form-label">
                    <ion-icon name="toggle-outline"></ion-icon>
                    Status
                </label>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="status" value="">
                        <span class="radio-label">Todos</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="status" value="1" checked>
                        <span class="radio-label">Ativos</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="status" value="0">
                        <span class="radio-label">Inativos</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-container">
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-spinner"></div>
        </div>

        <div class="table-info">
            <div class="results-count">
                <ion-icon name="list-outline"></ion-icon>
                <span>Exibindo <strong id="resultsCount">0</strong> registros</span>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Unidade</th>
                        <th>Localidade</th>
                        <th class="sortable" data-column="CD_CODIGO">
                            Código <ion-icon name="swap-vertical-outline"></ion-icon>
                        </th>
                        <th class="sortable" data-column="DS_NOME">
                            Nome <ion-icon name="swap-vertical-outline"></ion-icon>
                        </th>
                        <th>Código TAG</th>
                        <th class="sortable" data-column="DS_TIPO_MEDIDOR">
                            Tipo Medidor <ion-icon name="swap-vertical-outline"></ion-icon>
                        </th>
                        <th>Tipo Leitura</th>
                        <th class="sortable" data-column="OP_SITUACAO">
                            Status <ion-icon name="swap-vertical-outline"></ion-icon>
                        </th>
                        <th class="th-actions">Ações</th>
                    </tr>
                </thead>
                <tbody id="tabelaResultados">
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <ion-icon name="filter-outline"></ion-icon>
                                </div>
                                <h3>Preencha ao menos um filtro</h3>
                                <p>Selecione uma unidade, localidade, tipo ou digite algo na busca geral</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination-container" id="paginationContainer" style="display: none;">
            <div class="page-info" id="pageInfo"></div>
            <div class="pagination" id="pagination"></div>
        </div>
    </div>
</div>

<!-- jQuery e Select2 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    // Variaveis globais
    let dadosTabela = [];
    let paginaAtual = 1;
    let totalPaginas = 0;
    let totalRegistros = 0;
    let colunaOrdenacao = 'CD_CODIGO';
    let direcaoOrdenacao = 'ASC';
    const registrosPorPagina = 20;
    const podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;

    // Mapeamento de letras por tipo de medidor
    const letrasTipoMedidor = {
        1: 'M',
        2: 'E',
        4: 'P',
        6: 'R',
        8: 'H'
    };

    // Autocomplete
    let autocompletePontoTimeout = null;
    let autocompletePontoIndex = -1;

    $(document).ready(function () {
        // Inicializa Select2
        $('.select2-unidade').select2({
            width: '100%',
            placeholder: 'Todas as Unidades',
            allowClear: true
        });

        $('.select2-localidade').select2({
            width: '100%',
            placeholder: 'Selecione uma Unidade primeiro',
            allowClear: true
        });

        $('.select2-default').select2({
            width: '100%',
            minimumResultsForSearch: -1
        });

        // Eventos
        $('#selectUnidade').on('change', function () {
            const cdUnidade = $(this).val();
            carregarLocalidades(cdUnidade);
            buscarPontosMedicao(1);
        });

        $('#selectLocalidade').on('change', function () {
            buscarPontosMedicao(1);
        });

        $('#selectTipoMedidor, #selectTipoLeitura').on('change', function () {
            buscarPontosMedicao(1);
        });

        $('input[name="status"]').on('change', function () {
            buscarPontosMedicao(1);
        });

        // Busca com debounce
        let searchTimeout;
        $('#inputBusca').on('input', function () {
            clearTimeout(searchTimeout);
            const valor = $(this).val();
            $('.btn-search-clear').toggle(valor.length > 0);
            searchTimeout = setTimeout(() => buscarPontosMedicao(1), 400);
        });

        // Ordenacao
        $('.data-table th.sortable').on('click', function () {
            const coluna = $(this).data('column');
            if (colunaOrdenacao === coluna) {
                direcaoOrdenacao = direcaoOrdenacao === 'ASC' ? 'DESC' : 'ASC';
            } else {
                colunaOrdenacao = coluna;
                direcaoOrdenacao = 'ASC';
            }

            $('.data-table th.sortable').removeClass('asc desc');
            $(this).addClass(direcaoOrdenacao.toLowerCase());

            buscarPontosMedicao(paginaAtual);
        });

        // Inicializa autocomplete de Ponto de Medicao
        initAutocompletePontoMedicao();
    });

    // ============================================
    // Autocomplete para Ponto de Medicao
    // ============================================
    function initAutocompletePontoMedicao() {
        const input = document.getElementById('filtroPontoMedicaoInput');
        const hidden = document.getElementById('filtroPontoMedicao');
        const dropdown = document.getElementById('filtroPontoMedicaoDropdown');
        const btnLimpar = document.getElementById('btnLimparPonto');

        input.addEventListener('focus', function () {
            if (!hidden.value) {
                buscarPontosMedicaoAutocomplete('');
            }
        });

        input.addEventListener('input', function () {
            const termo = this.value.trim();
            hidden.value = '';
            btnLimpar.style.display = 'none';
            autocompletePontoIndex = -1;

            clearTimeout(autocompletePontoTimeout);
            autocompletePontoTimeout = setTimeout(() => {
                buscarPontosMedicaoAutocomplete(termo);
            }, 300);
        });

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

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.autocomplete-container')) {
                dropdown.classList.remove('active');
            }
        });

        btnLimpar.addEventListener('click', function () {
            input.value = '';
            hidden.value = '';
            btnLimpar.style.display = 'none';
            dropdown.classList.remove('active');
            input.focus();
            buscarPontosMedicao(1);
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
        const cdUnidade = document.getElementById('selectUnidade').value;
        const cdLocalidade = document.getElementById('selectLocalidade').value;

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

        buscarPontosMedicao(1);
    }

    // ============================================
    // Carregar Localidades
    // ============================================
    function carregarLocalidades(cdUnidade) {
        const $select = $('#selectLocalidade');

        if (!cdUnidade) {
            $select.prop('disabled', true);
            $select.empty().append('<option value="">Selecione uma Unidade primeiro</option>');
            return;
        }

        $select.prop('disabled', false);
        $select.empty().append('<option value="">Carregando...</option>');

        $.get('bd/pontoMedicao/getLocalidades.php', { cd_unidade: cdUnidade }, function (response) {
            $select.empty().append('<option value="">Todas as Localidades</option>');

            if (response.success && response.data.length > 0) {
                response.data.forEach(function (item) {
                    $select.append(`<option value="${item.CD_CHAVE}">${item.CD_LOCALIDADE} - ${item.DS_NOME}</option>`);
                });
            }
        }, 'json').fail(function () {
            $select.empty().append('<option value="">Erro ao carregar</option>');
            showToast('Erro ao carregar localidades', 'erro');
        });
    }

    // ============================================
    // Buscar Pontos de Medicao via AJAX
    // ============================================
    function buscarPontosMedicao(pagina = 1) {
        const cdUnidade = $('#selectUnidade').val() || '';
        const cdLocalidade = $('#selectLocalidade').val() || '';
        const cdPontoMedicao = $('#filtroPontoMedicao').val() || '';
        const tipoMedidor = $('#selectTipoMedidor').val() || '';
        const tipoLeitura = $('#selectTipoLeitura').val() || '';
        const busca = document.getElementById('inputBusca').value.trim();
        const status = $('input[name="status"]:checked').val() || '';

        const temFiltro = cdUnidade !== '' || cdLocalidade !== '' || cdPontoMedicao !== '' ||
            tipoMedidor !== '' || tipoLeitura !== '' || busca !== '' || status !== '';

        if (!temFiltro) {
            dadosTabela = [];
            paginaAtual = 1;
            totalPaginas = 0;
            totalRegistros = 0;
            $('#tabelaResultados').html(`
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <ion-icon name="filter-outline"></ion-icon>
                            </div>
                            <h3>Preencha ao menos um filtro</h3>
                            <p>Selecione uma unidade, localidade, tipo ou digite algo na busca geral</p>
                        </div>
                    </td>
                </tr>
            `);
            $('#resultsCount').text('0');
            $('#paginationContainer').hide();
            return;
        }

        paginaAtual = pagina;
        $('#loadingOverlay').addClass('active');

        $.ajax({
            url: 'bd/pontoMedicao/getPontosMedicao.php',
            type: 'GET',
            data: {
                cd_unidade: cdUnidade,
                cd_localidade: cdLocalidade,
                cd_ponto_medicao: cdPontoMedicao,
                tipo_medidor: tipoMedidor,
                tipo_leitura: tipoLeitura,
                busca: busca,
                status: status,
                pagina: pagina,
                registros_por_pagina: registrosPorPagina,
                coluna_ordenacao: colunaOrdenacao,
                direcao_ordenacao: direcaoOrdenacao
            },
            dataType: 'json',
            success: function (response) {
                $('#loadingOverlay').removeClass('active');

                if (response.success) {
                    dadosTabela = response.data;
                    totalRegistros = response.total;
                    totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);

                    renderizarTabela();
                    renderizarPaginacao();

                    $('#resultsCount').text(totalRegistros);
                    $('#paginationContainer').toggle(totalPaginas > 1);
                } else {
                    showToast(response.message || 'Erro ao buscar dados', 'erro');
                }
            },
            error: function () {
                $('#loadingOverlay').removeClass('active');
                showToast('Erro ao comunicar com o servidor', 'erro');
            }
        });
    }

    // ============================================
    // Renderizar Tabela
    // ============================================
    function renderizarTabela() {
        if (dadosTabela.length === 0) {
            $('#tabelaResultados').html(`
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <ion-icon name="search-outline"></ion-icon>
                            </div>
                            <h3>Nenhum registro encontrado</h3>
                            <p>Tente ajustar os filtros de pesquisa</p>
                        </div>
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        dadosTabela.forEach(function (item) {
            const isAtivo = item.OP_SITUACAO == 1;
            const statusClass = isAtivo ? 'badge-ativo' : 'badge-inativo';
            const statusText = isAtivo ? 'Ativo' : 'Inativo';

            // Classes de cor baseadas no ID do tipo
            const tipoMedidorClass = item.ID_TIPO_MEDIDOR ? `badge-tipo-${item.ID_TIPO_MEDIDOR}` : 'badge-tipo';
            const tipoLeituraClass = item.ID_TIPO_LEITURA ? `badge-leitura-${item.ID_TIPO_LEITURA}` : 'badge-leitura';

            html += `
                <tr>
                    <td class="truncate" title="${item.DS_UNIDADE || ''}">${item.DS_UNIDADE || '-'}</td>
                    <td class="truncate" title="${item.DS_LOCALIDADE || ''}">${item.DS_LOCALIDADE || '-'}</td>
                    <td class="code">${item.CD_CODIGO || '-'}</td>
                    <td class="name" title="${item.DS_NOME || ''}">${item.DS_NOME || '-'}</td>
                    <td class="code">${item.CODIGO_TAG || '-'}</td>
                    <td><span class="badge ${tipoMedidorClass}">${item.DS_TIPO_MEDIDOR || '-'}</span></td>
                    <td><span class="badge ${tipoLeituraClass}">${item.DS_TIPO_LEITURA || '-'}</span></td>
                    <td><span class="badge ${statusClass}">${statusText}</span></td>
                    <td>
                        <div class="table-actions">
                             <button type="button" class="btn-action" onclick="visualizar(${item.CD_PONTO_MEDICAO})" title="Visualizar">
                                <ion-icon name="eye-outline"></ion-icon>
                            </button>
                            <button type="button" class="btn-action" onclick="abrirOperacoes(${item.CD_PONTO_MEDICAO})" title="Operações" style="background: #f0fdf4; color: #16a34a;">
                                <ion-icon name="stats-chart-outline"></ion-icon>
                            </button>
                            ${podeEditar ? `
                            <button type="button" class="btn-action" onclick="editar(${item.CD_PONTO_MEDICAO})" title="Editar">
                                <ion-icon name="create-outline"></ion-icon>
                            </button>
                            ` : ''}
                            ${podeEditar && isAtivo ? `
                            <button type="button" class="btn-action delete" onclick="desativar(${item.CD_PONTO_MEDICAO})" title="Desativar">
                                <ion-icon name="trash-outline"></ion-icon>
                            </button>
                            ` : ''}
                            ${podeEditar && !isAtivo ? `
                            <button type="button" class="btn-action activate" onclick="ativar(${item.CD_PONTO_MEDICAO})" title="Ativar">
                                <ion-icon name="checkmark-circle-outline"></ion-icon>
                            </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });

        $('#tabelaResultados').html(html);
    }

    // ============================================
    // Acoes da Tabela
    // ============================================
    function visualizar(id) {
        window.location.href = `pontoMedicaoView.php?id=${id}`;
    }

    function abrirOperacoes(id) {
        const mes = new Date().getMonth() + 1;
        const ano = new Date().getFullYear();
        window.location.href = `operacoes.php?ponto=${id}&mes=${mes}&ano=${ano}`;
    }

    function editar(id) {
        window.location.href = `pontoMedicaoForm.php?id=${id}`;
    }

    function desativar(id) {
        if (confirm('Tem certeza que deseja desativar este ponto de medição?')) {
            $.post('bd/pontoMedicao/excluirPontoMedicao.php', { cd_ponto_medicao: id }, function (response) {
                if (response.success) {
                    showToast(response.message || 'Ponto desativado com sucesso', 'sucesso');
                    buscarPontosMedicao(paginaAtual);
                } else {
                    showToast(response.message || 'Erro ao desativar', 'erro');
                }
            }, 'json').fail(function () {
                showToast('Erro ao comunicar com o servidor', 'erro');
            });
        }
    }

    function ativar(id) {
        if (confirm('Deseja reativar este ponto de medição?')) {
            $.post('bd/pontoMedicao/ativarPontoMedicao.php', { cd_ponto_medicao: id }, function (response) {
                if (response.success) {
                    showToast(response.message || 'Ponto ativado com sucesso', 'sucesso');
                    buscarPontosMedicao(paginaAtual);
                } else {
                    showToast(response.message || 'Erro ao ativar', 'erro');
                }
            }, 'json').fail(function () {
                showToast('Erro ao comunicar com o servidor', 'erro');
            });
        }
    }

    // ============================================
    // Renderizar Paginacao
    // ============================================
    function renderizarPaginacao() {
        if (totalPaginas <= 1) {
            $('#paginationContainer').hide();
            return;
        }

        let html = '';

        html += `<button type="button" class="btn-page ${paginaAtual === 1 ? 'disabled' : ''}" 
                  onclick="irParaPagina(${paginaAtual - 1})" ${paginaAtual === 1 ? 'disabled' : ''}>
                    <ion-icon name="chevron-back-outline"></ion-icon>
                </button>`;

        let inicio = Math.max(1, paginaAtual - 2);
        let fim = Math.min(totalPaginas, paginaAtual + 2);

        if (inicio > 1) {
            html += `<button type="button" class="btn-page" onclick="irParaPagina(1)">1</button>`;
            if (inicio > 2) {
                html += `<span class="page-ellipsis">...</span>`;
            }
        }

        for (let i = inicio; i <= fim; i++) {
            html += `<button type="button" class="btn-page ${i === paginaAtual ? 'active' : ''}" 
                      onclick="irParaPagina(${i})">${i}</button>`;
        }

        if (fim < totalPaginas) {
            if (fim < totalPaginas - 1) {
                html += `<span class="page-ellipsis">...</span>`;
            }
            html += `<button type="button" class="btn-page" onclick="irParaPagina(${totalPaginas})">${totalPaginas}</button>`;
        }

        html += `<button type="button" class="btn-page ${paginaAtual === totalPaginas ? 'disabled' : ''}" 
                  onclick="irParaPagina(${paginaAtual + 1})" ${paginaAtual === totalPaginas ? 'disabled' : ''}>
                    <ion-icon name="chevron-forward-outline"></ion-icon>
                </button>`;

        $('#pagination').html(html);

        const inicio_registro = ((paginaAtual - 1) * registrosPorPagina) + 1;
        const fim_registro = Math.min(paginaAtual * registrosPorPagina, totalRegistros);
        $('#pageInfo').text(`Exibindo ${inicio_registro} - ${fim_registro} de ${totalRegistros}`);
    }

    function irParaPagina(pagina) {
        if (pagina < 1 || pagina > totalPaginas || pagina === paginaAtual) return;
        buscarPontosMedicao(pagina);
    }

    // ============================================
    // Limpar Filtros
    // ============================================
    function limparFiltros() {
        $('#selectUnidade').val('').trigger('change');
        $('#selectLocalidade').prop('disabled', true).empty()
            .append('<option value="">Selecione uma Unidade primeiro</option>');
        $('#selectTipoMedidor').val('').trigger('change');
        $('#selectTipoLeitura').val('').trigger('change');
        $('#inputBusca').val('');
        $('.btn-search-clear').hide();
        $('input[name="status"][value="1"]').prop('checked', true);

        // Limpar autocomplete de ponto
        document.getElementById('filtroPontoMedicaoInput').value = '';
        document.getElementById('filtroPontoMedicao').value = '';
        document.getElementById('filtroPontoMedicaoDropdown').classList.remove('active');
        document.getElementById('btnLimparPonto').style.display = 'none';

        dadosTabela = [];
        paginaAtual = 1;
        totalPaginas = 0;
        totalRegistros = 0;

        $('#tabelaResultados').html(`
            <tr>
                <td colspan="9">
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <ion-icon name="filter-outline"></ion-icon>
                        </div>
                        <h3>Preencha ao menos um filtro</h3>
                        <p>Selecione uma unidade, localidade, tipo ou digite algo na busca geral</p>
                    </div>
                </td>
            </tr>
        `);
        $('#resultsCount').text('0');
        $('#paginationContainer').hide();
    }

    function limparBusca() {
        $('#inputBusca').val('');
        $('.btn-search-clear').hide();
        buscarPontosMedicao(1);
    }

    // ============================================
    // Toast
    // ============================================
    function showToast(message, type = 'info') {
        const container = document.getElementById('toast-container') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <ion-icon name="${type === 'sucesso' ? 'checkmark-circle' : type === 'erro' ? 'alert-circle' : 'information-circle'}"></ion-icon>
            </div>
            <span class="toast-message">${message}</span>
        `;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
        return container;
    }
</script>

<?php include_once 'includes/footer.inc.php'; ?>
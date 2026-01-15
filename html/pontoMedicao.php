<?php

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// Verifica permissão para acessar Ponto de Medição (busca por nome na tabela FUNCIONALIDADE)
exigePermissaoTela('Cadastro de Ponto de Medição', ACESSO_LEITURA);

// Permissão do usuário para este módulo
$podeEditar = podeEditarTela('Cadastro de Ponto de Medição');

// Buscar Unidades
$sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME, CD_CODIGO FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

// Tipos de Medidor (fixo)
$tiposMedidor = [
    ['value' => '', 'text' => 'Todos os Tipos'],
    ['value' => '1', 'text' => 'M - Macromedidor'],
    ['value' => '2', 'text' => 'E - Estação Pitométrica'],
    ['value' => '4', 'text' => 'P - Medidor Pressão'],
    ['value' => '8', 'text' => 'H - Hidrômetro'],
    ['value' => '6', 'text' => 'R - Nível Reservatório'],
];

// Tipos de Leitura (fixo)
$tiposLeitura = [
    ['value' => '', 'text' => 'Todos os Tipos'],
    ['value' => '2', 'text' => 'Manual'],
    ['value' => '4', 'text' => 'Planilha'],
    ['value' => '8', 'text' => 'Integração CCO'],
    ['value' => '6', 'text' => 'Integração CesanLims'],
];
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<link rel="stylesheet" href="style/css/pontoMedicao.css">

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

            <!-- Tipo Medidor -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="speedometer-outline"></ion-icon>
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
            <div class="form-group form-group-span-2">
                <label class="form-label">
                    <ion-icon name="search-outline"></ion-icon>
                    Busca Geral
                </label>
                <div class="input-search-wrapper">
                    <ion-icon name="search-outline" class="input-search-icon"></ion-icon>
                    <input type="text" 
                           id="inputBusca" 
                           class="form-control input-search" 
                           placeholder="Buscar por código, nome, TAG, localização, observação...">
                    <button type="button" class="btn-search-clear" id="btnLimparBusca" style="display: none;">
                        <ion-icon name="close-circle"></ion-icon>
                    </button>
                </div>
            </div>

            <!-- Radio Button Status -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="toggle-outline"></ion-icon>
                    Status
                </label>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="status" value="" checked>
                        <span class="radio-label">Todos</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="status" value="ativo">
                        <span class="radio-label ativo">Ativos</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="status" value="inativo">
                        <span class="radio-label inativo">Inativos</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Info -->
    <div class="results-info">
        <div class="results-count">
            <ion-icon name="list-outline"></ion-icon>
            <span><strong id="totalRegistros">0</strong> registros encontrados</span>
        </div>
    </div>

    <!-- Data Table -->
    <div class="table-container">
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-spinner"></div>
        </div>

        <div class="table-wrapper">
            <table class="data-table" id="dataTable">
                <thead>
                    <tr>
                        <th data-sort="UNIDADE">
                            Unidade
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="LOCALIDADE">
                            Localidade
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="CODIGO">
                            Código
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="DS_NOME">
                            Nome
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="CODIGO_TAG">
                            Código TAG
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="ID_TIPO_MEDIDOR">
                            Tipo Medidor
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="ID_TIPO_LEITURA">
                            Tipo Leitura
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="ATIVO">
                            Status
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th class="no-sort">Ações</th>
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
    </div>

    <!-- Paginação -->
    <div class="pagination-container" id="paginacao"></div>
</div>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    // ============================================
    // Variáveis Globais
    // ============================================
    let dadosTabela = [];
    let sortColumn = null;
    let sortDirection = 'asc';
    let paginaAtual = 1;
    let totalPaginas = 0;
    let totalRegistros = 0;

    // Permissão do usuário (vindo do PHP)
    const permissoes = {
        podeEditar: <?= $podeEditar ? 'true' : 'false' ?>
    };

    // ============================================
    // Mapeamentos de Tipos
    // ============================================
    const tiposMedidorMap = {
        '1': { text: 'Macromedidor', class: 'macromedidor', abbr: 'M' },
        '2': { text: 'Est. Pitométrica', class: 'estacao', abbr: 'E' },
        '4': { text: 'Med. Pressão', class: 'pressao', abbr: 'P' },
        '6': { text: 'Nív. Reservatório', class: 'reservatorio', abbr: 'R' },
        '8': { text: 'Hidrômetro', class: 'hidrometro', abbr: 'H' }
    };

    const tiposLeituraMap = {
        '2': 'Manual',
        '4': 'Planilha',
        '6': 'Int. CesanLims',
        '8': 'Int. CCO'
    };

    // ============================================
    // Inicialização Select2
    // ============================================
    $(document).ready(function() {
        // Select2 para Unidade
        $('.select2-unidade').select2({
            placeholder: 'Digite para buscar...',
            allowClear: true,
            language: {
                noResults: function() { return "Nenhuma unidade encontrada"; },
                searching: function() { return "Buscando..."; }
            }
        });

        // Select2 para Localidade
        $('.select2-localidade').select2({
            placeholder: 'Selecione uma Unidade primeiro',
            allowClear: true,
            language: {
                noResults: function() { return "Nenhuma localidade encontrada"; }
            }
        });

        // Select2 para dropdowns simples
        $('.select2-default').select2({
            minimumResultsForSearch: Infinity
        });

        // Events
        $('#selectUnidade').on('change', function() {
            carregarLocalidades($(this).val());
            buscarPontosMedicao(1); // Reset para página 1
        });

        $('#selectLocalidade, #selectTipoMedidor, #selectTipoLeitura').on('change', function() {
            buscarPontosMedicao(1); // Reset para página 1
        });

        // Event: Campo de busca com debounce
        let buscaTimeout;
        $('#inputBusca').on('input', function() {
            const valor = $(this).val();
            
            // Mostra/esconde botão limpar
            $('#btnLimparBusca').toggle(valor.length > 0);
            
            // Debounce de 400ms
            clearTimeout(buscaTimeout);
            buscaTimeout = setTimeout(function() {
                buscarPontosMedicao(1); // Reset para página 1
            }, 400);
        });

        // Event: Limpar campo de busca
        $('#btnLimparBusca').on('click', function() {
            $('#inputBusca').val('').focus();
            $(this).hide();
            buscarPontosMedicao(1); // Reset para página 1
        });

        // Event: Radio buttons de status
        $('input[name="status"]').on('change', function() {
            buscarPontosMedicao(1); // Reset para página 1
        });

        // Inicializa ordenação
        initSorting();

        // Não carrega dados automaticamente - aguarda preenchimento de filtro
    });

    // ============================================
    // Sistema de Ordenação
    // ============================================
    function initSorting() {
        document.querySelectorAll('.data-table th[data-sort]').forEach(th => {
            th.addEventListener('click', function() {
                const column = this.getAttribute('data-sort');
                
                // Remove classes de outros headers
                document.querySelectorAll('.data-table th').forEach(h => {
                    h.classList.remove('sorted-asc', 'sorted-desc');
                });

                // Alterna direção se mesma coluna
                if (sortColumn === column) {
                    sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    sortColumn = column;
                    sortDirection = 'asc';
                }

                // Adiciona classe ao header atual
                this.classList.add(sortDirection === 'asc' ? 'sorted-asc' : 'sorted-desc');

                // Reordena e renderiza
                sortData();
                renderizarTabela(dadosTabela);
            });
        });
    }

    function sortData() {
        if (!sortColumn || dadosTabela.length === 0) return;

        dadosTabela.sort((a, b) => {
            let valA = a[sortColumn] || '';
            let valB = b[sortColumn] || '';

            // Converte para string para comparação
            valA = String(valA).toLowerCase();
            valB = String(valB).toLowerCase();

            // Tenta comparar como número se possível
            const numA = parseFloat(valA);
            const numB = parseFloat(valB);
            
            if (!isNaN(numA) && !isNaN(numB)) {
                return sortDirection === 'asc' ? numA - numB : numB - numA;
            }

            // Comparação de string
            if (valA < valB) return sortDirection === 'asc' ? -1 : 1;
            if (valA > valB) return sortDirection === 'asc' ? 1 : -1;
            return 0;
        });
    }

    // ============================================
    // Carregar Localidades via AJAX
    // ============================================
    function carregarLocalidades(cdUnidade) {
        const select = $('#selectLocalidade');
        
        if (!cdUnidade) {
            select.prop('disabled', true);
            select.html('<option value="">Selecione uma Unidade primeiro</option>');
            select.trigger('change');
            return;
        }

        select.prop('disabled', true);
        select.html('<option value="">Carregando...</option>');

        $.ajax({
            url: 'bd/pontoMedicao/getLocalidades.php',
            type: 'GET',
            data: { cd_unidade: cdUnidade },
            dataType: 'json',
            success: function(response) {
                let options = '<option value="">Todas as Localidades</option>';
                
                if (response.success && response.data.length > 0) {
                    response.data.forEach(function(item) {
                        // Usa CD_CHAVE como value (é a FK em PONTO_MEDICAO.CD_LOCALIDADE)
                        options += `<option value="${item.CD_CHAVE}">${item.CD_LOCALIDADE} - ${item.DS_NOME}</option>`;
                    });
                }
                
                select.html(options);
                select.prop('disabled', false);
                select.trigger('change.select2');
            },
            error: function() {
                select.html('<option value="">Erro ao carregar</option>');
                showToast('Erro ao carregar localidades', 'erro');
            }
        });
    }

    // ============================================
    // Buscar Pontos de Medição via AJAX
    // ============================================
    function buscarPontosMedicao(pagina = 1) {
        const cdUnidade = $('#selectUnidade').val() || '';
        const cdLocalidade = $('#selectLocalidade').val() || '';
        const tipoMedidor = $('#selectTipoMedidor').val() || '';
        const tipoLeitura = $('#selectTipoLeitura').val() || '';
        const busca = document.getElementById('inputBusca').value.trim();
        const status = $('input[name="status"]:checked').val() || '';

        // Verifica se pelo menos um filtro foi preenchido
        const temFiltro = cdUnidade !== '' || cdLocalidade !== '' || tipoMedidor !== '' || 
                          tipoLeitura !== '' || busca !== '' || status !== '';

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
            $('#totalRegistros').text('0');
            renderizarPaginacao();
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
                tipo_medidor: tipoMedidor,
                tipo_leitura: tipoLeitura,
                busca: busca,
                status: status,
                pagina: pagina
            },
            dataType: 'json',
            success: function(response) {
                $('#loadingOverlay').removeClass('active');
                
                if (response.success && response.data && response.data.length > 0) {
                    dadosTabela = response.data;
                    totalRegistros = response.total;
                    totalPaginas = response.totalPaginas;
                    
                    if (sortColumn) {
                        sortData();
                    }
                    
                    renderizarTabela(dadosTabela);
                    $('#totalRegistros').text(totalRegistros);
                    renderizarPaginacao();
                } else {
                    dadosTabela = [];
                    totalRegistros = 0;
                    totalPaginas = 0;
                    
                    const mensagem = response.message || 'Nenhum registro encontrado';
                    $('#tabelaResultados').html(`
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <ion-icon name="file-tray-outline"></ion-icon>
                                    </div>
                                    <h3>${mensagem}</h3>
                                    <p>Tente ajustar os filtros de pesquisa</p>
                                </div>
                            </td>
                        </tr>
                    `);
                    $('#totalRegistros').text('0');
                    renderizarPaginacao();
                }
            },
            error: function(xhr, status, error) {
                $('#loadingOverlay').removeClass('active');
                console.error('Erro AJAX:', { xhr, status, error });
                showToast('Erro ao buscar pontos de medição', 'erro');
            }
        });
    }

    // ============================================
    // Renderizar Paginação
    // ============================================
    function renderizarPaginacao() {
        const container = $('#paginacao');
        
        if (totalPaginas <= 1) {
            container.html('');
            return;
        }

        let html = '<div class="pagination">';
        
        // Botão Anterior
        html += `<button type="button" class="btn-page ${paginaAtual === 1 ? 'disabled' : ''}" 
                  onclick="irParaPagina(${paginaAtual - 1})" ${paginaAtual === 1 ? 'disabled' : ''}>
                    <ion-icon name="chevron-back-outline"></ion-icon>
                 </button>`;

        // Números das páginas
        const maxPaginas = 5;
        let inicio = Math.max(1, paginaAtual - Math.floor(maxPaginas / 2));
        let fim = Math.min(totalPaginas, inicio + maxPaginas - 1);
        
        if (fim - inicio + 1 < maxPaginas) {
            inicio = Math.max(1, fim - maxPaginas + 1);
        }

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

        // Botão Próximo
        html += `<button type="button" class="btn-page ${paginaAtual === totalPaginas ? 'disabled' : ''}" 
                  onclick="irParaPagina(${paginaAtual + 1})" ${paginaAtual === totalPaginas ? 'disabled' : ''}>
                    <ion-icon name="chevron-forward-outline"></ion-icon>
                 </button>`;

        html += '</div>';
        
        // Info da página
        const inicio_reg = ((paginaAtual - 1) * 20) + 1;
        const fim_reg = Math.min(paginaAtual * 20, totalRegistros);
        html += `<div class="page-info">Exibindo ${inicio_reg}-${fim_reg} de ${totalRegistros}</div>`;

        container.html(html);
    }

    function irParaPagina(pagina) {
        if (pagina < 1 || pagina > totalPaginas || pagina === paginaAtual) return;
        buscarPontosMedicao(pagina);
        // Scroll para o topo da tabela
        document.querySelector('.table-container').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ============================================
    // Renderizar Tabela
    // ============================================
    function renderizarTabela(dados) {
        if (!dados || dados.length === 0) {
            $('#tabelaResultados').html(`
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <ion-icon name="file-tray-outline"></ion-icon>
                            </div>
                            <h3>Nenhum registro encontrado</h3>
                            <p>Tente ajustar os filtros de pesquisa ou realizar uma nova busca</p>
                        </div>
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';

        dados.forEach(function(item) {
            const tipoMedidor = tiposMedidorMap[item.ID_TIPO_MEDIDOR] || { text: '-', class: '', abbr: '-' };
            const tipoLeitura = tiposLeituraMap[item.ID_TIPO_LEITURA] || '-';
            const isAtivo = item.ATIVO == 1;

            html += `
                <tr>
                    <td class="truncate" title="${item.UNIDADE || ''}">${item.UNIDADE || '-'}</td>
                    <td class="truncate" title="${item.LOCALIDADE || ''}">${item.LOCALIDADE || '-'}</td>
                    <td class="code">${item.CODIGO || '-'}</td>
                    <td class="name" title="${item.DS_NOME || ''}">${item.DS_NOME || '-'}</td>
                    <td class="code">${item.CODIGO_TAG || '-'}</td>
                    <td>
                        <span class="badge badge-tipo ${tipoMedidor.class}" title="${tipoMedidor.text}">
                            ${tipoMedidor.abbr} - ${tipoMedidor.text}
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-leitura">${tipoLeitura}</span>
                    </td>
                    <td>
                        <span class="badge badge-status ${isAtivo ? 'ativo' : 'inativo'}">
                            ${isAtivo ? 'Ativo' : 'Inativo'}
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <button type="button" class="btn-action" onclick="visualizar(${item.CD_PONTO_MEDICAO})" title="Visualizar">
                                <ion-icon name="eye-outline"></ion-icon>
                            </button>
                            ${permissoes.podeEditar ? `
                            <button type="button" class="btn-action" onclick="editar(${item.CD_PONTO_MEDICAO})" title="Editar">
                                <ion-icon name="create-outline"></ion-icon>
                            </button>
                            ` : ''}
                            ${permissoes.podeEditar && isAtivo ? `
                            <button type="button" class="btn-action delete" onclick="excluir(${item.CD_PONTO_MEDICAO})" title="Desativar">
                                <ion-icon name="trash-outline"></ion-icon>
                            </button>
                            ` : ''}
                            ${permissoes.podeEditar && !isAtivo ? `
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
    // Ações
    // ============================================
    function limparFiltros() {
        // Reset ordenação
        sortColumn = null;
        sortDirection = 'asc';
        document.querySelectorAll('.data-table th').forEach(h => {
            h.classList.remove('sorted-asc', 'sorted-desc');
        });

        // Reset paginação
        paginaAtual = 1;
        totalPaginas = 0;
        totalRegistros = 0;

        // Reset filtros select
        $('#selectUnidade').val('').trigger('change.select2');
        $('#selectLocalidade').val('').prop('disabled', true).html('<option value="">Selecione uma Unidade primeiro</option>').trigger('change.select2');
        $('#selectTipoMedidor').val('').trigger('change.select2');
        $('#selectTipoLeitura').val('').trigger('change.select2');

        // Reset campo de busca
        $('#inputBusca').val('');
        $('#btnLimparBusca').hide();

        // Reset radio buttons (seleciona "Todos")
        $('input[name="status"][value=""]').prop('checked', true);

        // Limpa tabela e paginação
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
        $('#totalRegistros').text('0');
        $('#paginacao').html('');
    }

    function visualizar(id) {
        window.location.href = `pontoMedicaoView.php?id=${id}`;
    }

    function editar(id) {
        window.location.href = `pontoMedicaoForm.php?id=${id}`;
    }

    function excluir(id) {
        if (confirm('Tem certeza que deseja desativar este ponto de medição?')) {
            $.ajax({
                url: 'bd/pontoMedicao/excluirPontoMedicao.php',
                type: 'POST',
                data: { cd_ponto_medicao: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showToast(response.message, 'sucesso');
                        buscarPontosMedicao(paginaAtual);
                    } else {
                        showToast(response.message || 'Erro ao desativar', 'erro');
                    }
                },
                error: function() {
                    showToast('Erro ao comunicar com o servidor', 'erro');
                }
            });
        }
    }

    function ativar(id) {
        if (confirm('Deseja reativar este ponto de medição?')) {
            $.ajax({
                url: 'bd/pontoMedicao/ativarPontoMedicao.php',
                type: 'POST',
                data: { cd_ponto_medicao: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showToast(response.message, 'sucesso');
                        buscarPontosMedicao(paginaAtual);
                    } else {
                        showToast(response.message || 'Erro ao ativar', 'erro');
                    }
                },
                error: function() {
                    showToast('Erro ao comunicar com o servidor', 'erro');
                }
            });
        }
    }
</script>

<?php include_once 'includes/footer.inc.php'; ?>
<?php
include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

exigePermissaoTela('Cadastro de Conjunto Motor-Bomba', ACESSO_LEITURA);
$podeEditar = podeEditarTela('Cadastro de Conjunto Motor-Bomba');

$sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME, CD_CODIGO FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

$tiposEixo = [
    ['value' => '', 'text' => 'Todos os Tipos'],
    ['value' => 'H', 'text' => 'Horizontal'],
    ['value' => 'V', 'text' => 'Vertical'],
];
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- css da tela -->
<link rel="stylesheet" href="style/css/motorBomba.css">

<div class="page-container">
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="cog-outline"></ion-icon>
                </div>
                <div>
                    <h1>Conjunto Motor Bomba</h1>
                    <p class="page-header-subtitle">Gerencie os conjuntos motor bomba do sistema</p>
                </div>
            </div>
            <?php if ($podeEditar): ?>
            <a href="motorBombaForm.php" class="btn-novo">
                <ion-icon name="add-outline"></ion-icon>
                Novo Conjunto
            </a>
            <?php endif; ?>
        </div>
    </div>

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
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="business-outline"></ion-icon>
                    Unidade
                </label>
                <select id="selectUnidade" class="form-control select2-custom">
                    <option value="">Todas as Unidades</option>
                    <?php foreach ($unidades as $unidade): ?>
                        <option value="<?= $unidade['CD_UNIDADE'] ?>">
                            <?= htmlspecialchars($unidade['CD_CODIGO'] . ' - ' . $unidade['DS_NOME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="location-outline"></ion-icon>
                    Localidade
                </label>
                <select id="selectLocalidade" class="form-control select2-custom" disabled>
                    <option value="">Selecione uma Unidade primeiro</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="git-compare-outline"></ion-icon>
                    Tipo de Eixo
                </label>
                <select id="selectTipoEixo" class="form-control select2-custom">
                    <?php foreach ($tiposEixo as $tipo): ?>
                        <option value="<?= $tipo['value'] ?>"><?= $tipo['text'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="search-outline"></ion-icon>
                    Busca Geral
                </label>
                <div class="input-search-wrapper">
                    <input type="text" id="inputBusca" class="form-control" placeholder="Codigo, nome, localizacao...">
                    <button type="button" class="btn-limpar-busca" id="btnLimparBusca" onclick="limparBusca()">
                        <ion-icon name="close-circle"></ion-icon>
                    </button>
                    <button type="button" class="btn-buscar-input" onclick="buscarMotorBomba()">
                        <ion-icon name="search-outline"></ion-icon>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="results-info">
        <div class="results-count">
            <ion-icon name="list-outline"></ion-icon>
            <span>Total: <strong id="totalRegistros">0</strong> registro(s)</span>
        </div>
    </div>

    <div class="table-container">
        <div class="loading-overlay" id="loadingTabela">
            <div class="loading-spinner"></div>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
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
                        <th data-sort="DS_CODIGO">
                            Codigo
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
                        <th data-sort="TP_EIXO">
                            Eixo
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="VL_POTENCIA_MOTOR">
                            Potencia (CV)
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="VL_VAZAO_BOMBA">
                            Vazao (L/s)
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th class="no-sort">Acoes</th>
                    </tr>
                </thead>
                <tbody id="tabelaResultados">
                    <tr>
                        <td colspan="8">
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

    <div class="pagination-container" id="paginacao"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
let dadosTabela = [];
let sortColumn = null;
let sortDirection = 'asc';
let paginaAtual = 1;
let totalPaginas = 0;
let totalRegistros = 0;

const permissoes = {
    podeEditar: <?= $podeEditar ? 'true' : 'false' ?>
};

const tiposEixoMap = {
    'H': { text: 'Horizontal', class: 'horizontal' },
    'V': { text: 'Vertical', class: 'vertical' }
};

$(document).ready(function() {
    $('.select2-custom').select2({
        width: '100%',
        placeholder: 'Selecione...',
        allowClear: true
    });

    $('#selectUnidade').on('change', function() {
        const cdUnidade = $(this).val();
        if (cdUnidade) {
            carregarLocalidades(cdUnidade);
        } else {
            $('#selectLocalidade').val('').prop('disabled', true)
                .html('<option value="">Selecione uma Unidade primeiro</option>')
                .trigger('change.select2');
        }
        buscarMotorBomba();
    });

    $('#selectLocalidade, #selectTipoEixo').on('change', function() {
        buscarMotorBomba();
    });

    $('#inputBusca').on('input', function() {
        $('#btnLimparBusca').toggle($(this).val().length > 0);
    });

    $('#inputBusca').on('keypress', function(e) {
        if (e.which === 13) buscarMotorBomba();
    });

    $('.data-table th[data-sort]').on('click', function() {
        const column = $(this).data('sort');
        ordenarTabela(column);
    });
});

function carregarLocalidades(cdUnidade) {
    $('#selectLocalidade').prop('disabled', true).html('<option value="">Carregando...</option>');
    
    $.ajax({
        url: 'bd/pontoMedicao/getLocalidades.php',
        method: 'GET',
        data: { cd_unidade: cdUnidade },
        dataType: 'json',
        success: function(response) {
            let options = '<option value="">Todas as Localidades</option>';
            if (response.success && response.data) {
                response.data.forEach(function(loc) {
                    options += `<option value="${loc.CD_CHAVE}">${loc.CD_LOCALIDADE} - ${loc.DS_NOME}</option>`;
                });
            }
            $('#selectLocalidade').html(options).prop('disabled', false).trigger('change.select2');
        },
        error: function() {
            $('#selectLocalidade').html('<option value="">Erro ao carregar</option>');
            showToast('Erro ao carregar localidades', 'erro');
        }
    });
}

function buscarMotorBomba(pagina = 1) {
    const cdUnidade = $('#selectUnidade').val() || '';
    const cdLocalidade = $('#selectLocalidade').val() || '';
    const tipoEixo = $('#selectTipoEixo').val() || '';
    const busca = $('#inputBusca').val().trim();

    const temFiltro = cdUnidade !== '' || cdLocalidade !== '' || tipoEixo !== '' || busca !== '';

    if (!temFiltro) {
        dadosTabela = [];
        paginaAtual = 1;
        totalPaginas = 0;
        totalRegistros = 0;
        $('#tabelaResultados').html(`
            <tr>
                <td colspan="8">
                    <div class="empty-state">
                        <div class="empty-state-icon"><ion-icon name="filter-outline"></ion-icon></div>
                        <h3>Preencha ao menos um filtro</h3>
                        <p>Selecione uma unidade, localidade, tipo ou digite algo na busca geral</p>
                    </div>
                </td>
            </tr>
        `);
        $('#totalRegistros').text('0');
        $('#paginacao').html('');
        return;
    }

    $('#loadingTabela').addClass('active');

    $.ajax({
        url: 'bd/motorBomba/getMotorBomba.php',
        method: 'GET',
        data: {
            cd_unidade: cdUnidade,
            cd_localidade: cdLocalidade,
            tipo_eixo: tipoEixo,
            busca: busca,
            pagina: pagina,
            ordenar_por: sortColumn,
            ordenar_direcao: sortDirection
        },
        dataType: 'json',
        success: function(response) {
            $('#loadingTabela').removeClass('active');
            if (response.success) {
                dadosTabela = response.data || [];
                paginaAtual = response.pagina;
                totalPaginas = response.totalPaginas;
                totalRegistros = response.total;
                $('#totalRegistros').text(totalRegistros);
                renderizarTabela(dadosTabela);
                renderizarPaginacao();
            } else {
                showToast(response.message || 'Erro ao buscar dados', 'erro');
            }
        },
        error: function() {
            $('#loadingTabela').removeClass('active');
            showToast('Erro ao comunicar com o servidor', 'erro');
        }
    });
}

function ordenarTabela(column) {
    document.querySelectorAll('.data-table th').forEach(h => h.classList.remove('sorted-asc', 'sorted-desc'));

    if (sortColumn === column) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn = column;
        sortDirection = 'asc';
    }

    const th = document.querySelector(`.data-table th[data-sort="${column}"]`);
    if (th) th.classList.add(sortDirection === 'asc' ? 'sorted-asc' : 'sorted-desc');

    buscarMotorBomba(1);
}

function renderizarPaginacao() {
    const container = $('#paginacao');
    if (totalPaginas <= 1) {
        container.html('');
        return;
    }

    let html = '<div class="pagination-buttons">';
    html += `<button onclick="irParaPagina(${paginaAtual - 1})" ${paginaAtual === 1 ? 'disabled' : ''}>
                <ion-icon name="chevron-back-outline"></ion-icon>
             </button>`;

    let inicio = Math.max(1, paginaAtual - 2);
    let fim = Math.min(totalPaginas, paginaAtual + 2);

    if (inicio > 1) {
        html += `<button onclick="irParaPagina(1)">1</button>`;
        if (inicio > 2) html += `<button disabled>...</button>`;
    }

    for (let i = inicio; i <= fim; i++) {
        html += `<button class="${i === paginaAtual ? 'active' : ''}" onclick="irParaPagina(${i})">${i}</button>`;
    }

    if (fim < totalPaginas) {
        if (fim < totalPaginas - 1) html += `<button disabled>...</button>`;
        html += `<button onclick="irParaPagina(${totalPaginas})">${totalPaginas}</button>`;
    }

    html += `<button onclick="irParaPagina(${paginaAtual + 1})" ${paginaAtual === totalPaginas ? 'disabled' : ''}>
                <ion-icon name="chevron-forward-outline"></ion-icon>
             </button>`;
    html += '</div>';

    const inicio_reg = ((paginaAtual - 1) * 20) + 1;
    const fim_reg = Math.min(paginaAtual * 20, totalRegistros);
    html += `<div class="page-info">Exibindo ${inicio_reg}-${fim_reg} de ${totalRegistros}</div>`;

    container.html(html);
}

function irParaPagina(pagina) {
    if (pagina < 1 || pagina > totalPaginas || pagina === paginaAtual) return;
    buscarMotorBomba(pagina);
    document.querySelector('.table-container').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function renderizarTabela(dados) {
    if (!dados || dados.length === 0) {
        $('#tabelaResultados').html(`
            <tr>
                <td colspan="8">
                    <div class="empty-state">
                        <div class="empty-state-icon"><ion-icon name="file-tray-outline"></ion-icon></div>
                        <h3>Nenhum registro encontrado</h3>
                        <p>Tente ajustar os filtros de pesquisa</p>
                    </div>
                </td>
            </tr>
        `);
        return;
    }

    let html = '';
    dados.forEach(function(item) {
        const tipoEixo = tiposEixoMap[item.TP_EIXO] || { text: '-', class: '' };

        html += `
            <tr>
                <td class="truncate" title="${item.UNIDADE || ''}">${item.UNIDADE || '-'}</td>
                <td class="truncate" title="${item.LOCALIDADE || ''}">${item.LOCALIDADE || '-'}</td>
                <td class="code">${item.DS_CODIGO || '-'}</td>
                <td class="name" title="${item.DS_NOME || ''}">${item.DS_NOME || '-'}</td>
                <td>
                    <span class="badge badge-eixo ${tipoEixo.class}">${tipoEixo.text}</span>
                </td>
                <td>${item.VL_POTENCIA_MOTOR ? parseFloat(item.VL_POTENCIA_MOTOR).toFixed(2) : '-'}</td>
                <td>${item.VL_VAZAO_BOMBA ? parseFloat(item.VL_VAZAO_BOMBA).toFixed(2) : '-'}</td>
                <td>
                    <div class="actions-cell">
                        <button type="button" class="btn-action view" onclick="visualizar(${item.CD_CHAVE})" title="Visualizar">
                            <ion-icon name="eye-outline"></ion-icon>
                        </button>
                        ${permissoes.podeEditar ? `
                        <button type="button" class="btn-action edit" onclick="editar(${item.CD_CHAVE})" title="Editar">
                            <ion-icon name="create-outline"></ion-icon>
                        </button>
                        <button type="button" class="btn-action delete" onclick="excluir(${item.CD_CHAVE})" title="Excluir">
                            <ion-icon name="trash-outline"></ion-icon>
                        </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    });

    $('#tabelaResultados').html(html);
}

function limparFiltros() {
    sortColumn = null;
    sortDirection = 'asc';
    document.querySelectorAll('.data-table th').forEach(h => h.classList.remove('sorted-asc', 'sorted-desc'));
    paginaAtual = 1;

    $('#selectUnidade').val('').trigger('change.select2');
    $('#selectLocalidade').val('').prop('disabled', true).html('<option value="">Selecione uma Unidade primeiro</option>').trigger('change.select2');
    $('#selectTipoEixo').val('').trigger('change.select2');
    $('#inputBusca').val('');
    $('#btnLimparBusca').hide();

    $('#tabelaResultados').html(`
        <tr>
            <td colspan="8">
                <div class="empty-state">
                    <div class="empty-state-icon"><ion-icon name="filter-outline"></ion-icon></div>
                    <h3>Preencha ao menos um filtro</h3>
                    <p>Selecione uma unidade, localidade, tipo ou digite algo na busca geral</p>
                </div>
            </td>
        </tr>
    `);
    $('#totalRegistros').text('0');
    $('#paginacao').html('');
}

function limparBusca() {
    $('#inputBusca').val('');
    $('#btnLimparBusca').hide();
    buscarMotorBomba();
}

function visualizar(id) {
    if (!id || id === 'undefined' || id === 'null') {
        showToast('ID do registro não encontrado', 'erro');
        return;
    }
    window.location.href = `motorBombaView.php?id=${id}`;
}

function editar(id) {
    if (!id || id === 'undefined' || id === 'null') {
        showToast('ID do registro não encontrado', 'erro');
        return;
    }
    window.location.href = `motorBombaForm.php?id=${id}`;
}

function excluir(id) {
    if (!id || id === 'undefined' || id === 'null') {
        showToast('ID do registro não encontrado', 'erro');
        return;
    }
    if (confirm('Tem certeza que deseja excluir este conjunto motor bomba?')) {
        $.ajax({
            url: 'bd/motorBomba/excluirMotorBomba.php',
            method: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('Registro excluido com sucesso', 'sucesso');
                    buscarMotorBomba(paginaAtual);
                } else {
                    showToast(response.message || 'Erro ao excluir', 'erro');
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
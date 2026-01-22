<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Tela: Listagem de Conjunto Motor-Bomba
 * 
 * ATUALIZAÇÕES:
 * - Salvamento de filtros no localStorage (igual calculoKPC.php)
 * - Coluna 'ALTURA MANOMÉTRICA (M)' incluída
 * - Coluna 'Localização' incluída
 * - Termo 'CÓDIGO' alterado para 'Nº DO CONJUNTO'
 * - Botão ANEXAR nas ações
 * 
 * @author SIMP
 * @version 1.1
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

exigePermissaoTela('Cadastro de Conjunto Motor-Bomba', ACESSO_LEITURA);
$podeEditar = podeEditarTela('Cadastro de Conjunto Motor-Bomba');

// Buscar unidades para o filtro
$sqlUnidades = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME, CD_CODIGO FROM SIMP.dbo.UNIDADE ORDER BY DS_NOME");
$unidades = $sqlUnidades->fetchAll(PDO::FETCH_ASSOC);

// Tipos de eixo
$tiposEixo = [
    ['value' => '', 'text' => 'Todos os Tipos'],
    ['value' => 'H', 'text' => 'Horizontal'],
    ['value' => 'V', 'text' => 'Vertical'],
];
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- CSS da tela -->
<link rel="stylesheet" href="style/css/motorBomba.css">
<link rel="stylesheet" href="style/css/motorBombaAnexos.css">

<div class="page-container">
    <!-- Header da Página -->
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

    <!-- Card de Filtros -->
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
                <select id="selectUnidade" class="form-control select2-custom">
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
                <select id="selectLocalidade" class="form-control select2-custom" disabled>
                    <option value="">Selecione uma Unidade primeiro</option>
                </select>
            </div>

            <!-- Tipo de Eixo -->
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

            <!-- Busca Geral -->
            <div class="form-group">
                <label class="form-label">
                    <ion-icon name="search-outline"></ion-icon>
                    Busca Geral
                </label>
                <div class="input-with-clear">
                    <input type="text" id="inputBusca" class="form-control" placeholder="Nº do Conjunto, Nome ou Localização...">
                    <button type="button" class="btn-clear-input" id="btnLimparBusca" onclick="limparBusca()" title="Limpar busca">
                        <ion-icon name="close-outline"></ion-icon>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Informações do Resultado -->
    <div class="results-info">
        <div class="results-count">
            <ion-icon name="list-outline"></ion-icon>
            <span>Total: <strong id="totalRegistros">0</strong> registro(s)</span>
        </div>
    </div>

    <!-- Tabela de Resultados -->
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
                        <!-- ALTERADO: CÓDIGO para Nº DO CONJUNTO -->
                        <th data-sort="DS_CODIGO">
                            Nº do Conjunto
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
                        <!-- NOVO: Coluna Localização -->
                        <th data-sort="DS_LOCALIZACAO">
                            Localização
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
                            Potência (CV)
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <th data-sort="VL_VAZAO_BOMBA">
                            Vazão (L/s)
                            <span class="sort-indicator">
                                <span class="sort-asc"><ion-icon name="caret-up"></ion-icon></span>
                                <span class="sort-desc"><ion-icon name="caret-down"></ion-icon></span>
                            </span>
                        </th>
                        <!-- NOVO: Coluna Altura Manométrica -->
                        <th data-sort="VL_ALTURA_MANOMETRICA_BOMBA">
                            Altura Manom. (m)
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
                        <td colspan="10">
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

<!-- Modal de Anexos -->
<div class="modal-overlay" id="modalAnexos" style="display: none;">
    <div class="modal-container modal-lg">
        <div class="modal-header">
            <h3>
                <ion-icon name="attach-outline"></ion-icon>
                <span id="modalAnexosTitulo">Anexos do Conjunto</span>
            </h3>
            <button type="button" class="modal-close" onclick="fecharModalAnexos()">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-body">
            <div id="listaAnexosModal">
                <div class="loading-spinner"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="fecharModalAnexos()">
                <ion-icon name="close-outline"></ion-icon>
                Fechar
            </button>
        </div>
    </div>
</div>

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
let filtroTimeout;

// Chave para localStorage (persistência de filtros)
const STORAGE_KEY_FILTROS = 'motorBomba_filtros_state';

// Permissões
const permissoes = {
    podeEditar: <?= $podeEditar ? 'true' : 'false' ?>
};

// ============================================
// Persistência de Filtros (localStorage)
// ============================================

/**
 * Salva o estado atual dos filtros no localStorage
 */
function salvarFiltros() {
    const state = {
        unidade: $('#selectUnidade').val() || '',
        localidade: $('#selectLocalidade').val() || '',
        tipoEixo: $('#selectTipoEixo').val() || '',
        busca: $('#inputBusca').val() || '',
        pagina: paginaAtual,
        sortColumn: sortColumn,
        sortDirection: sortDirection
    };
    
    localStorage.setItem(STORAGE_KEY_FILTROS, JSON.stringify(state));
}

/**
 * Restaura os filtros salvos do localStorage
 * @returns {boolean} true se havia filtros salvos
 */
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
        
        // Tipo de Eixo
        if (state.tipoEixo) {
            $('#selectTipoEixo').val(state.tipoEixo).trigger('change.select2');
        }
        
        // Busca
        if (state.busca) {
            $('#inputBusca').val(state.busca);
            if (state.busca) {
                $('#btnLimparBusca').addClass('visible');
            }
        }
        
        // Paginação
        if (state.pagina) {
            paginaAtual = parseInt(state.pagina);
        }
        
        // Ordenação
        if (state.sortColumn) {
            sortColumn = state.sortColumn;
            sortDirection = state.sortDirection || 'asc';
        }
        
        return true;
    } catch (e) {
        console.error('Erro ao restaurar filtros:', e);
        return false;
    }
}

/**
 * Limpa os filtros salvos do localStorage
 */
function limparFiltrosSalvos() {
    localStorage.removeItem(STORAGE_KEY_FILTROS);
}

// ============================================
// Inicialização
// ============================================
$(document).ready(function() {
    // Inicializar Select2
    $('.select2-custom').select2({
        placeholder: 'Selecione...',
        allowClear: true,
        width: '100%',
        language: {
            noResults: function() { return "Nenhum resultado encontrado"; },
            searching: function() { return "Buscando..."; }
        }
    });

    // Restaurar filtros salvos
    const temFiltrosSalvos = restaurarFiltros();

    // Eventos de filtro
    $('#selectUnidade').on('change', function() {
        const cdUnidade = $(this).val();
        carregarLocalidades(cdUnidade);
        paginaAtual = 1;
        salvarFiltros();
        buscarMotorBomba();
    });

    $('#selectLocalidade').on('change', function() {
        paginaAtual = 1;
        salvarFiltros();
        buscarMotorBomba();
    });

    $('#selectTipoEixo').on('change', function() {
        paginaAtual = 1;
        salvarFiltros();
        buscarMotorBomba();
    });

    // Busca com debounce
    $('#inputBusca').on('input', function() {
        const val = $(this).val();
        if (val.length > 0) {
            $('#btnLimparBusca').addClass('visible');
        } else {
            $('#btnLimparBusca').removeClass('visible');
        }
        
        clearTimeout(filtroTimeout);
        filtroTimeout = setTimeout(function() {
            paginaAtual = 1;
            salvarFiltros();
            buscarMotorBomba();
        }, 500);
    });

    // Enter no campo de busca
    $('#inputBusca').on('keypress', function(e) {
        if (e.which === 13) {
            clearTimeout(filtroTimeout);
            paginaAtual = 1;
            salvarFiltros();
            buscarMotorBomba();
        }
    });

    // Ordenação da tabela
    $('.data-table th[data-sort]').on('click', function() {
        const coluna = $(this).data('sort');
        if (sortColumn === coluna) {
            sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            sortColumn = coluna;
            sortDirection = 'asc';
        }
        
        // Atualizar indicadores visuais
        $('.data-table th').removeClass('sorted-asc sorted-desc');
        $(this).addClass('sorted-' + sortDirection);
        
        salvarFiltros();
        buscarMotorBomba();
    });

    // Buscar dados iniciais se houver filtros salvos
    if (temFiltrosSalvos) {
        setTimeout(function() {
            buscarMotorBomba();
        }, 300);
    }
});

// ============================================
// Carregar Localidades
// ============================================
function carregarLocalidades(cdUnidade) {
    carregarLocalidadesComCallback(cdUnidade, null);
}

function carregarLocalidadesComCallback(cdUnidade, callback) {
    if (!cdUnidade) {
        $('#selectLocalidade')
            .html('<option value="">Selecione uma Unidade primeiro</option>')
            .prop('disabled', true)
            .trigger('change.select2');
        if (callback) callback();
        return;
    }

    $.ajax({
        url: 'bd/motorBomba/getLocalidades.php',
        type: 'GET',
        data: { cd_unidade: cdUnidade },
        dataType: 'json',
        success: function(response) {
            let options = '<option value="">Todas as Localidades</option>';
            if (response.success && response.data) {
                response.data.forEach(function(loc) {
                    options += `<option value="${loc.CD_CHAVE}">${loc.CD_LOCALIDADE} - ${loc.DS_NOME}</option>`;
                });
            }
            $('#selectLocalidade')
                .html(options)
                .prop('disabled', false)
                .trigger('change.select2');
            
            if (callback) callback();
        },
        error: function() {
            $('#selectLocalidade').html('<option value="">Erro ao carregar</option>');
            showToast('Erro ao carregar localidades', 'erro');
            if (callback) callback();
        }
    });
}

// ============================================
// Buscar Motor-Bomba
// ============================================
function buscarMotorBomba(pagina = null) {
    if (pagina !== null) {
        paginaAtual = pagina;
    }

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
                <td colspan="10">
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

    // Mostrar loading
    $('#loadingTabela').addClass('active');

    const params = {
        cd_unidade: cdUnidade,
        cd_localidade: cdLocalidade,
        tipo_eixo: tipoEixo,
        busca: busca,
        pagina: paginaAtual,
        ordenar_por: sortColumn || 'DS_NOME',
        ordenar_direcao: sortDirection
    };

    $.ajax({
        url: 'bd/motorBomba/getMotorBomba.php',
        type: 'GET',
        data: params,
        dataType: 'json',
        success: function(response) {
            $('#loadingTabela').removeClass('active');

            if (response.success) {
                dadosTabela = response.data || [];
                totalRegistros = response.total || 0;
                totalPaginas = response.totalPaginas || 0;
                
                renderizarTabela(dadosTabela);
                renderizarPaginacao();
                $('#totalRegistros').text(totalRegistros);
                
                salvarFiltros();
            } else {
                showToast(response.message || 'Erro ao buscar dados', 'erro');
            }
        },
        error: function(xhr, status, error) {
            $('#loadingTabela').removeClass('active');
            console.error('Erro:', error);
            showToast('Erro ao comunicar com o servidor', 'erro');
        }
    });
}

// ============================================
// Renderizar Tabela (ATUALIZADO: novas colunas e botão anexar)
// ============================================
function renderizarTabela(dados) {
    if (!dados || dados.length === 0) {
        $('#tabelaResultados').html(`
            <tr>
                <td colspan="10">
                    <div class="empty-state">
                        <div class="empty-state-icon"><ion-icon name="search-outline"></ion-icon></div>
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
        // Formatar eixo
        const eixo = item.TP_EIXO === 'H' ? 'Horizontal' : (item.TP_EIXO === 'V' ? 'Vertical' : '-');
        const eixoClass = item.TP_EIXO === 'H' ? 'badge-info' : 'badge-warning';

        // Formatar valores numéricos
        const potencia = item.VL_POTENCIA_MOTOR ? parseFloat(item.VL_POTENCIA_MOTOR).toFixed(2) : '-';
        const vazao = item.VL_VAZAO_BOMBA ? parseFloat(item.VL_VAZAO_BOMBA).toFixed(2) : '-';
        const alturaManometrica = item.VL_ALTURA_MANOMETRICA_BOMBA ? parseFloat(item.VL_ALTURA_MANOMETRICA_BOMBA).toFixed(2) : '-';

        // Badge de anexos
        const qtdAnexos = parseInt(item.QTD_ANEXOS) || 0;
        const badgeAnexos = qtdAnexos > 0 ? `<span class="badge-anexos">${qtdAnexos}</span>` : '';

        html += `
            <tr>
                <td>${item.DS_UNIDADE || '-'}</td>
                <td>${item.CD_LOCALIDADE_CODIGO || '-'} - ${item.DS_LOCALIDADE || '-'}</td>
                <td><strong>${item.DS_CODIGO || '-'}</strong></td>
                <td>${item.DS_NOME || '-'}</td>
                <td>${item.DS_LOCALIZACAO || '-'}</td>
                <td><span class="badge ${eixoClass}">${eixo}</span></td>
                <td class="text-right">${potencia}</td>
                <td class="text-right">${vazao}</td>
                <td class="text-right">${alturaManometrica}</td>
                <td>
                    <div class="actions-cell">
                        <button type="button" class="btn-action view" onclick="visualizar(${item.CD_CHAVE})" title="Visualizar">
                            <ion-icon name="eye-outline"></ion-icon>
                        </button>
                        <!-- NOVO: Botão Anexar -->
                        <button type="button" class="btn-action attach" onclick="abrirModalAnexos(${item.CD_CHAVE}, '${(item.DS_CODIGO || '').replace(/'/g, "\\'")} - ${(item.DS_NOME || '').replace(/'/g, "\\'")}')" title="Anexos">
                            <ion-icon name="attach-outline"></ion-icon>
                            ${badgeAnexos}
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

// ============================================
// Renderizar Paginação (padrão do sistema)
// ============================================
function renderizarPaginacao() {
    const container = document.getElementById('paginacao');
    
    if (totalRegistros === 0) {
        container.innerHTML = '';
        return;
    }

    const inicio = (paginaAtual - 1) * 20 + 1;
    const fim = Math.min(paginaAtual * 20, totalRegistros);

    let html = `
        <div class="pagination-info">
            Mostrando <span>${inicio}</span> a <span>${fim}</span> de <span>${totalRegistros}</span>
        </div>
        <div class="pagination">
    `;
    
    // Botão anterior
    html += `<button onclick="buscarMotorBomba(${paginaAtual - 1})" ${paginaAtual <= 1 ? 'disabled' : ''}>
        <ion-icon name="chevron-back-outline"></ion-icon>
    </button>`;

    if (totalPaginas > 1) {
        // Páginas
        const maxPaginas = 5;
        let inicio_p = Math.max(1, paginaAtual - Math.floor(maxPaginas / 2));
        let fim_p = Math.min(totalPaginas, inicio_p + maxPaginas - 1);
        
        if (fim_p - inicio_p + 1 < maxPaginas) {
            inicio_p = Math.max(1, fim_p - maxPaginas + 1);
        }

        if (inicio_p > 1) {
            html += `<button onclick="buscarMotorBomba(1)">1</button>`;
            if (inicio_p > 2) {
                html += `<button disabled>...</button>`;
            }
        }

        for (let i = inicio_p; i <= fim_p; i++) {
            html += `<button class="${i === paginaAtual ? 'active' : ''}" onclick="buscarMotorBomba(${i})">${i}</button>`;
        }

        if (fim_p < totalPaginas) {
            if (fim_p < totalPaginas - 1) {
                html += `<button disabled>...</button>`;
            }
            html += `<button onclick="buscarMotorBomba(${totalPaginas})">${totalPaginas}</button>`;
        }
    } else if (totalPaginas === 1) {
        // Uma única página
        html += `<button class="active" onclick="buscarMotorBomba(1)">1</button>`;
    }

    // Botão próximo
    html += `<button onclick="buscarMotorBomba(${paginaAtual + 1})" ${paginaAtual >= totalPaginas ? 'disabled' : ''}>
        <ion-icon name="chevron-forward-outline"></ion-icon>
    </button>`;

    html += '</div>';
    
    container.innerHTML = html;
}

// ============================================
// Funções de Ação
// ============================================
function limparFiltros() {
    sortColumn = null;
    sortDirection = 'asc';
    document.querySelectorAll('.data-table th').forEach(h => h.classList.remove('sorted-asc', 'sorted-desc'));
    paginaAtual = 1;

    $('#selectUnidade').val('').trigger('change.select2');
    $('#selectLocalidade').val('').prop('disabled', true).html('<option value="">Selecione uma Unidade primeiro</option>').trigger('change.select2');
    $('#selectTipoEixo').val('').trigger('change.select2');
    $('#inputBusca').val('');
    $('#btnLimparBusca').removeClass('visible');

    // Limpa localStorage
    limparFiltrosSalvos();

    $('#tabelaResultados').html(`
        <tr>
            <td colspan="10">
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
    $('#btnLimparBusca').removeClass('visible');
    paginaAtual = 1;
    salvarFiltros();
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
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast(response.message, 'sucesso');
                    buscarMotorBomba();
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

// ============================================
// Funções de Anexos
// ============================================
let anexoConjuntoAtual = null;

function abrirModalAnexos(cdConjunto, nomeConjunto) {
    anexoConjuntoAtual = cdConjunto;
    $('#modalAnexosTitulo').text('Anexos: ' + nomeConjunto);
    $('#modalAnexos').fadeIn(200);
    carregarAnexosModal(cdConjunto);
}

function fecharModalAnexos() {
    $('#modalAnexos').fadeOut(200);
    anexoConjuntoAtual = null;
}

function carregarAnexosModal(cdConjunto) {
    $('#listaAnexosModal').html('<div class="loading-spinner"></div>');

    $.ajax({
        url: 'bd/motorBomba/listarAnexos.php',
        type: 'GET',
        data: { cd_conjunto: cdConjunto },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderizarAnexosModal(response.data);
            } else {
                $('#listaAnexosModal').html(`
                    <div class="empty-state-small">
                        <ion-icon name="alert-circle-outline"></ion-icon>
                        <p>${response.message || 'Erro ao carregar anexos'}</p>
                    </div>
                `);
            }
        },
        error: function() {
            $('#listaAnexosModal').html(`
                <div class="empty-state-small">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    <p>Erro ao comunicar com o servidor</p>
                </div>
            `);
        }
    });
}

function renderizarAnexosModal(anexos) {
    if (!anexos || anexos.length === 0) {
        $('#listaAnexosModal').html(`
            <div class="empty-state-small">
                <ion-icon name="document-outline"></ion-icon>
                <p>Nenhum anexo cadastrado</p>
            </div>
        `);
        return;
    }

    let html = '<div class="anexos-list">';
    anexos.forEach(function(anexo) {
        const dataUpload = anexo.DT_INCLUSAO ? new Date(anexo.DT_INCLUSAO).toLocaleString('pt-BR') : '-';
        html += `
            <div class="anexo-item">
                <div class="anexo-icon">
                    <ion-icon name="${anexo.DS_ICONE}"></ion-icon>
                </div>
                <div class="anexo-info">
                    <div class="anexo-nome">${anexo.DS_FILENAME}</div>
                    <div class="anexo-meta">
                        <span>${anexo.VL_TAMANHO_FORMATADO}</span>
                        <span>•</span>
                        <span>${dataUpload}</span>
                        <span>•</span>
                        <span>${anexo.DS_USUARIO_UPLOAD || 'Não informado'}</span>
                    </div>
                    ${anexo.DS_OBSERVACAO ? `<div class="anexo-obs">${anexo.DS_OBSERVACAO}</div>` : ''}
                </div>
                <div class="anexo-acoes">
                    <a href="bd/motorBomba/downloadAnexo.php?id=${anexo.CD_ANEXO}" class="btn-anexo-download" title="Download">
                        <ion-icon name="download-outline"></ion-icon>
                    </a>
                </div>
            </div>
        `;
    });
    html += '</div>';

    $('#listaAnexosModal').html(html);
}

// Fechar modal ao clicar fora
$(document).on('click', '.modal-overlay', function(e) {
    if (e.target === this) {
        fecharModalAnexos();
    }
});
</script>

<style>
/* ============================================
   ESTILOS ADICIONAIS PARA ANEXOS
   ============================================ */

/* Badge de anexos no botão - Cor azul claro */
.btn-action.attach {
    position: relative;
    background: #E0EDFF;
    color: #3b82f6;
}

.btn-action.attach:hover {
    background: #dbeafe;
    color: #2563eb;
}

.btn-action.attach ion-icon {
    color: #3b82f6;
}

/* Input com botão de limpar */
.input-with-clear {
    position: relative;
    display: flex;
    align-items: center;
}

.input-with-clear .form-control {
    padding-right: 36px;
}

.btn-clear-input {
    position: absolute;
    right: 8px;
    width: 24px;
    height: 24px;
    border: none;
    background: #e2e8f0;
    border-radius: 50%;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.btn-clear-input ion-icon {
    font-size: 14px;
    color: #64748b;
}

.btn-clear-input:hover {
    background: #cbd5e1;
}

.btn-clear-input:hover ion-icon {
    color: #475569;
}

.input-with-clear .form-control:not(:placeholder-shown) + .btn-clear-input,
.btn-clear-input.visible {
    display: flex;
}

.badge-anexos {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #ef4444;
    color: white;
    font-size: 10px;
    font-weight: 600;
    padding: 2px 5px;
    border-radius: 10px;
    min-width: 16px;
    text-align: center;
}

/* Modal de Anexos */
.modal-lg {
    max-width: 700px;
}

.empty-state-small {
    padding: 40px 20px;
    text-align: center;
    color: #64748b;
}

.empty-state-small ion-icon {
    font-size: 48px;
    margin-bottom: 12px;
    color: #94a3b8;
}

.empty-state-small p {
    margin: 0;
    font-size: 14px;
}

/* Lista de Anexos */
.anexos-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.anexo-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    transition: all 0.2s ease;
}

.anexo-item:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.anexo-icon {
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    border-radius: 10px;
    flex-shrink: 0;
}

.anexo-icon ion-icon {
    font-size: 22px;
    color: white;
}

.anexo-info {
    flex: 1;
    min-width: 0;
}

.anexo-nome {
    font-weight: 600;
    color: #1e293b;
    font-size: 14px;
    margin-bottom: 4px;
    word-break: break-word;
}

.anexo-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    font-size: 12px;
    color: #64748b;
}

.anexo-obs {
    margin-top: 6px;
    font-size: 12px;
    color: #64748b;
    font-style: italic;
}

.anexo-acoes {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

.btn-anexo-download {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 8px;
    color: white;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-anexo-download:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.btn-anexo-download ion-icon {
    font-size: 18px;
}

/* Responsivo */
@media (max-width: 576px) {
    .anexo-item {
        flex-wrap: wrap;
    }
    
    .anexo-acoes {
        width: 100%;
        margin-top: 10px;
        justify-content: flex-end;
    }
}

/* Coluna de texto à direita */
.text-right {
    text-align: right;
}

/* ============================================
   PAGINAÇÃO - ESTILO IGUAL AO CALCULOKPC.PHP
   ============================================ */
.table-container {
    border-radius: 12px 12px 0 0;
}

.pagination-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 24px;
    border: 1px solid #e2e8f0;
    border-top: none;
    background: #fff;
    border-radius: 0 0 12px 12px;
}

.pagination-info {
    font-size: 13px;
    color: #64748b;
}

.pagination {
    display: flex;
    gap: 4px;
}

.pagination button {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    background: #fff;
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
    min-width: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pagination button:hover:not(:disabled) {
    background: #f8fafc;
    border-color: #3b82f6;
    color: #3b82f6;
}

.pagination button.active {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.pagination button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination button ion-icon {
    font-size: 16px;
}
</style>

<?php include_once 'includes/footer.inc.php'; ?>
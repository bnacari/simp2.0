<?php
/**
 * SIMP 2.0 - Fase A2: Tratamento em Lote
 *
 * Tela para operadores revisarem e tratarem anomalias detectadas
 * pelo motor batch. Prioriza por impacto hidraulico e confianca.
 *
 * Funcionalidades:
 *   - Cards de resumo (pendentes, tratadas, tecnicas, operacionais)
 *   - Filtros avancados (data, status, classe, tipo, medidor, confianca)
 *   - Tabela com acoes rapidas (aprovar, ajustar, ignorar)
 *   - Acoes em massa (aprovar/ignorar selecionados)
 *   - Modal de detalhe com scores individuais
 *   - Reserva de area para contexto GNN (Fase B)
 *
 * @author  Bruno - CESAN
 * @version 1.0 - Fase A2
 * @date    2026-02
 */

$paginaAtual = 'tratamentoLote';

include_once 'includes/header.inc.php';
include_once 'bd/conexao.php';

// Permissao (mesma de Operacoes)
recarregarPermissoesUsuario();
exigePermissaoTela('Registro de Vazão', ACESSO_LEITURA);
$podeEditar = podeEditarTela('Registro de Vazão');

include_once 'includes/menu.inc.php';

// Buscar unidades para filtro
$unidades = [];
try {
    $stmtU = $pdoSIMP->query("SELECT CD_UNIDADE, DS_NOME FROM SIMP.dbo.UNIDADE WHERE OP_ATIVO = 1 ORDER BY DS_NOME");
    $unidades = $stmtU->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* silencioso */
}
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<link rel="stylesheet" href="/style/css/treinamentoLote.css" />

<div class="page-container">

    <!-- ============================================
         HEADER
         ============================================ -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="checkmark-done-outline"></ion-icon>
                </div>
                <div>
                    <h1>Tratamento em Lote</h1>
                    <p>Revisao e tratamento de anomalias detectadas pelo motor de analise</p>
                </div>
            </div>
            <div class="header-actions">
                <?php if ($podeEditar): ?>
                    <span id="infoBatch" style="font-size:11px;color:#64748b;margin-right:12px;display:none;">
                        <ion-icon name="time-outline" style="vertical-align:middle;margin-right:2px;"></ion-icon>
                        Ultimo batch: <strong id="dtUltimoBatch">-</strong>
                    </span>

                    <button type="button" class="btn-header" onclick="abrirRegras()">
                        <ion-icon name="book-outline"></ion-icon> Regras
                    </button>

                    <button class="btn-header primary" onclick="executarBatch()" id="btnExecutarBatch"
                        title="Executar motor de analise para ontem">
                        <ion-icon name="play-outline"></ion-icon>
                        Executar Batch
                    </button>
                <?php endif; ?>
                <button class="btn-header" onclick="carregarEstatisticas(); carregarPendencias();"
                    title="Atualizar dados">
                    <ion-icon name="refresh-outline"></ion-icon>
                    Atualizar
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================
         CARDS DE RESUMO
         ============================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon pendentes"><ion-icon name="alert-circle-outline"></ion-icon></div>
            <div class="stat-info">
                <h3 id="stPendentes">-</h3>
                <p>Pendentes</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon tratadas"><ion-icon name="checkmark-circle-outline"></ion-icon></div>
            <div class="stat-info">
                <h3 id="stTratadas">-</h3>
                <p>Tratadas</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon tecnicas"><ion-icon name="construct-outline"></ion-icon></div>
            <div class="stat-info">
                <h3 id="stTecnicas">-</h3>
                <p>Correcao Tecnica</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon operacionais"><ion-icon name="warning-outline"></ion-icon></div>
            <div class="stat-info">
                <h3 id="stOperacionais">-</h3>
                <p>Evento Operacional</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon confianca"><ion-icon name="shield-checkmark-outline"></ion-icon></div>
            <div class="stat-info">
                <h3 id="stConfianca">-</h3>
                <p>Confianca Media</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon pontos"><ion-icon name="pin-outline"></ion-icon></div>
            <div class="stat-info">
                <h3 id="stPontos">-</h3>
                <p>Pontos Afetados</p>
            </div>
        </div>
    </div>

    <!-- ============================================
         FILTROS
         ============================================ -->
    <div class="filtros-card">
        <div class="filtros-header" style="display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;"><ion-icon name="funnel-outline"></ion-icon> Filtros</h3>
            <button class="btn-limpar" onclick="limparFiltros()" style="margin:0;">
                <ion-icon name="refresh-outline"></ion-icon> Limpar
            </button>
        </div>
        <div class="filtros-grid">
            <!-- Data -->
            <div class="form-group">
                <label>Data Referencia</label>
                <select id="filtroData" class="form-control filtro-select2">
                    <option value="">Carregando...</option>
                </select>
            </div>
            <!-- Status -->
            <div class="form-group">
                <label>Status</label>
                <select id="filtroStatus" class="form-control filtro-select2">
                    <option value="0">Pendentes</option>
                    <option value="todos">Todos</option>
                    <option value="1">Aprovadas</option>
                    <option value="2">Ajustadas</option>
                    <option value="3">Ignoradas</option>
                </select>
            </div>
            <!-- Classe -->
            <div class="form-group">
                <label>Classificacao</label>
                <select id="filtroClasse" class="form-control filtro-select2">
                    <option value="">Todas</option>
                    <option value="1">Correcao Tecnica</option>
                    <option value="2">Evento Operacional</option>
                </select>
            </div>
            <!-- Tipo anomalia -->
            <div class="form-group">
                <label>Tipo Anomalia</label>
                <select id="filtroTipoAnomalia" class="form-control filtro-select2">
                    <option value="">Todos</option>
                    <option value="1">Valor zerado</option>
                    <option value="2">Sensor travado</option>
                    <option value="3">Spike</option>
                    <option value="4">Desvio estatistico</option>
                    <option value="5">Padrao incomum</option>
                    <option value="6">Desvio modelo</option>
                    <option value="7">Gap comunicacao</option>
                    <option value="8">Fora de faixa</option>
                </select>
            </div>
            <!-- Tipo medidor -->
            <div class="form-group">
                <label>Tipo Medidor</label>
                <select id="filtroTipoMedidor" class="form-control filtro-select2">
                    <option value="">Todos</option>
                    <option value="6">Reservatorio</option>
                    <option value="1">Macromedidor</option>
                    <option value="2">Pitometrica</option>
                    <option value="4">Pressao</option>
                    <option value="8">Hidrometro</option>
                </select>
            </div>

            <!-- Confianca minima -->
            <div class="form-group">
                <label>Confianca Minima</label>
                <select id="filtroConfianca" class="form-control filtro-select2">
                    <option value="">Qualquer</option>
                    <option value="0.95">Alta (>= 95%)</option>
                    <option value="0.85">Confiavel (>= 85%)</option>
                    <option value="0.70">Atencao (>= 70%)</option>
                </select>
            </div>
            <!-- Busca -->
            <div class="form-group">
                <label>Busca</label>
                <input type="text" id="filtroBusca" class="form-control" placeholder="Nome do ponto...">
            </div>
        </div>
    </div>

    <!-- ============================================
         BARRA DE ACOES EM MASSA
         ============================================ -->
    <?php if ($podeEditar): ?>
        <div class="massa-bar" id="massaBar">
            <span class="sel-count"><span id="massaCount">0</span> selecionado(s)</span>
            <button class="btn-massa aprovar" onclick="aprovarMassa()">
                <ion-icon name="checkmark-outline"></ion-icon> Aprovar
            </button>
            <button class="btn-massa ignorar" onclick="abrirIgnorarMassa()">
                <ion-icon name="close-outline"></ion-icon> Ignorar
            </button>
            <button class="btn-massa limpar" onclick="limparSelecao()">
                <ion-icon name="remove-circle-outline"></ion-icon> Limpar
            </button>
        </div>
    <?php endif; ?>

    <!-- ============================================
         TABELA DE PENDENCIAS
         ============================================ -->
    <div class="tabela-card">
        <div class="tabela-header">
            <h3 id="tabelaTitulo">Pendencias</h3>
            <span class="tabela-info" id="tabelaInfo">Carregando...</span>
        </div>
        <div class="tabela-wrapper">
            <table class="tbl-tratamento">
                <thead>
                    <tr>
                        <?php if ($podeEditar): ?>
                            <th style="width:36px"><input type="checkbox" class="chk-sel" id="chkTodos"
                                    onchange="toggleTodos(this)"></th>
                        <?php endif; ?>
                        <th class="th-sort" onclick="ordenarPor('ponto', event)">Ponto <ion-icon
                                name="swap-vertical-outline" class="sort-icon"></ion-icon></th>
                        <th class="th-sort" onclick="ordenarPor('data', event)">Data <ion-icon
                                name="swap-vertical-outline" class="sort-icon"></ion-icon></th>
                        <th class="th-sort" onclick="ordenarPor('tipo', event)">Tipo <ion-icon
                                name="swap-vertical-outline" class="sort-icon"></ion-icon></th>
                        <th class="th-sort" onclick="ordenarPor('qtd_horas', event)">Horas Anomalas <ion-icon
                                name="swap-vertical-outline" class="sort-icon"></ion-icon></th>
                        <th>Classe</th>
                        <th class="th-sort" onclick="ordenarPor('severidade', event)">Severidade <ion-icon
                                name="swap-vertical-outline" class="sort-icon"></ion-icon></th>
                        <th class="th-sort" onclick="ordenarPor('confianca', event)">Confianca <ion-icon
                                name="swap-vertical-outline" class="sort-icon"></ion-icon></th>
                        <th>Status</th>
                        <?php if ($podeEditar): ?>
                            <th style="width:140px">Acoes</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="tabelaBody">
                    <tr>
                        <td colspan="10" style="text-align:center;padding:40px;">
                            <div class="loading-spinner"></div>
                            <p style="margin:8px 0 0;color:#94a3b8;font-size:12px;">Carregando pendencias...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <!-- Paginacao -->
        <div class="paginacao" id="paginacao" style="display:none">
            <span class="paginacao-info" id="pagInfo"></span>
            <div class="paginacao-btns" id="pagBtns"></div>
        </div>
    </div>

    <!-- ============================================
         RESERVA AREA GNN (Fase B)
         ============================================ -->
    <div class="gnn-placeholder" style="margin-top:16px;">
        <ion-icon name="git-network-outline"></ion-icon>
        <p><strong>Contexto GNN (Fase B)</strong> — Aqui sera exibida a coerencia sistemica,
            nos vizinhos anomalos e propagacao de eventos quando o Graph Neural Network estiver ativo.</p>
    </div>

    <!-- ============================================
         MODAL: REGRAS DE DETECCAO E GLOSSARIO
         ============================================ -->
    <div class="modal-overlay" id="modalRegras" onclick="if(event.target===this)fecharRegras()">
        <div class="modal-container" style="max-width:760px;max-height:90vh;display:flex;flex-direction:column;">

            <!-- Header padrao SIMP -->
            <div class="modal-header">
                <h3>
                    <ion-icon name="book-outline"></ion-icon>
                    Regras de Deteccao e Glossario
                </h3>
                <button class="modal-close" onclick="fecharRegras()">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>

            <!-- Tabs -->
            <div style="display:flex;gap:0;border-bottom:2px solid #e2e8f0;background:#f8fafc;">
                <button class="regra-tab active" onclick="trocarAbaRegra('regras', this)">
                    <ion-icon name="shield-checkmark-outline"></ion-icon> Regras
                </button>
                <button class="regra-tab" onclick="trocarAbaRegra('glossario', this)">
                    <ion-icon name="library-outline"></ion-icon> Glossario
                </button>
                <button class="regra-tab" onclick="trocarAbaRegra('metodos', this)">
                    <ion-icon name="git-compare-outline"></ion-icon> Metodos de Correcao
                </button>

            </div>

            <!-- Body scrollavel -->
            <div class="modal-body" style="overflow-y:auto;flex:1;">

                <!-- ========== ABA REGRAS ========== -->
                <div id="abaRegras">

                    <!-- Tipos de Anomalia -->
                    <div class="regra-secao">
                        <h4><ion-icon name="warning-outline" style="color:#f59e0b;"></ion-icon> Tipos de Anomalia</h4>
                        <table class="regra-tabela">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Descricao</th>
                                    <th>Criterio de Deteccao</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="regra-badge amarelo">1</span></td>
                                    <td>Valor zerado</td>
                                    <td>Vazao = 0 em hora com historico &gt; 0 (exceto reservatorio)</td>
                                </tr>
                                <tr>
                                    <td><span class="regra-badge amarelo">2</span></td>
                                    <td>Sensor travado</td>
                                    <td>Valor constante por 3+ horas consecutivas (desvio padrao = 0)</td>
                                </tr>
                                <tr>
                                    <td><span class="regra-badge vermelho">3</span></td>
                                    <td>Spike (extremo)</td>
                                    <td>Valor &gt; 3x a media historica da hora ou variacao &gt; 200% entre horas</td>
                                </tr>
                                <tr>
                                    <td><span class="regra-badge azul">4</span></td>
                                    <td>Desvio estatistico</td>
                                    <td>Z-score &gt; limiar dinamico (4.0 - sensibilidade x 2.5)</td>
                                </tr>
                                <tr>
                                    <td><span class="regra-badge roxo">5</span></td>
                                    <td>Padrao incomum</td>
                                    <td>Autoencoder: erro de reconstrucao acima do threshold treinado</td>
                                </tr>
                                <tr>
                                    <td><span class="regra-badge azul">6</span></td>
                                    <td>Desvio do modelo</td>
                                    <td>Diferenca entre valor real e predicao XGBoost &gt; 2x o MAE do modelo</td>
                                </tr>
                                <tr>
                                    <td><span class="regra-badge cinza">7</span></td>
                                    <td>Gap comunicacao</td>
                                    <td>Sem registros na hora (0 de 60 minutos esperados)</td>
                                </tr>
                                <tr>
                                    <td><span class="regra-badge vermelho">8</span></td>
                                    <td>Fora de faixa</td>
                                    <td>Valor fora dos limites operacionais configurados no ponto</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Classificacao -->
                    <div class="regra-secao">
                        <h4><ion-icon name="git-branch-outline" style="color:#8b5cf6;"></ion-icon> Classificacao da
                            Anomalia
                        </h4>
                        <table class="regra-tabela">
                            <thead>
                                <tr>
                                    <th>Classe</th>
                                    <th>Criterio</th>
                                    <th>Acao Recomendada</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="regra-badge azul">Tecnica</span></td>
                                    <td>Apenas este ponto diverge; vizinhos no grafo estao normais</td>
                                    <td>Aprovar valor sugerido (falha isolada do sensor)</td>
                                </tr>
                                <tr>
                                    <td><span class="regra-badge laranja">Operacional</span></td>
                                    <td>Multiplos vizinhos tambem apresentam anomalia simultanea</td>
                                    <td>Investigar antes de tratar (pode ser evento real na rede)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Score de Confianca -->
                    <div class="regra-secao">
                        <h4><ion-icon name="shield-checkmark-outline" style="color:#16a34a;"></ion-icon> Score de
                            Confianca
                            Composto</h4>
                        <div class="regra-formula">
                            0.30 &times; Estatistico + 0.30 &times; Modelo + 0.20 &times; Topologico + 0.10 &times;
                            Historico + 0.10 &times; Padrao
                        </div>
                        <table class="regra-tabela">
                            <thead>
                                <tr>
                                    <th style="width:25%">Componente</th>
                                    <th>Peso</th>
                                    <th>O que mede</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Estatistico</strong></td>
                                    <td>30%</td>
                                    <td>Z-score normalizado — quanto o valor desvia da distribuicao historica</td>
                                </tr>
                                <tr>
                                    <td><strong>Modelo</strong></td>
                                    <td>30%</td>
                                    <td>Diferenca entre valor real e predicao XGBoost (se modelo treinado)</td>
                                </tr>
                                <tr>
                                    <td><strong>Topologico</strong></td>
                                    <td>20%</td>
                                    <td>Consistencia com vizinhos no grafo — se vizinhos estao normais, aumenta
                                        confianca
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Historico</strong></td>
                                    <td>10%</td>
                                    <td>Frequencia deste tipo de anomalia neste ponto (anomalias recorrentes = score
                                        maior)
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Padrao</strong></td>
                                    <td>10%</td>
                                    <td>Se anomalia similar ja foi aprovada antes no mesmo contexto</td>
                                </tr>
                            </tbody>
                        </table>
                        <div style="display:flex;gap:12px;margin-top:10px;flex-wrap:wrap;">
                            <div class="regra-badge-info verde">&ge; 95% — Alta (altissima certeza)</div>
                            <div class="regra-badge-info azul">&ge; 85% — Confiavel (recomendado aprovar)</div>
                            <div class="regra-badge-info amarelo">&ge; 70% — Atencao (revisar antes)</div>
                        </div>
                    </div>

                    <!-- Severidade -->
                    <div class="regra-secao">
                        <h4><ion-icon name="flame-outline" style="color:#ef4444;"></ion-icon> Niveis de Severidade</h4>
                        <table class="regra-tabela">
                            <thead>
                                <tr>
                                    <th>Severidade</th>
                                    <th>Criterio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="regra-badge vermelho">Critica</span></td>
                                    <td>Z-score &gt; 6 ou gap total de comunicacao</td>
                                </tr>
                                <tr>
                                    <td><span class="regra-badge laranja">Alta</span></td>
                                    <td>Z-score entre 4 e 6 ou spike extremo</td>
                                </tr>
                                <tr>
                                    <td><span class="regra-badge amarelo">Media</span></td>
                                    <td>Z-score entre 2.5 e 4 ou desvio moderado</td>
                                </tr>
                                <tr>
                                    <td><span class="regra-badge cinza">Baixa</span></td>
                                    <td>Z-score &lt; 2.5, desvio leve detectado pelo autoencoder</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Valor Sugerido -->
                    <div class="regra-secao">
                        <h4><ion-icon name="calculator-outline" style="color:#0891b2;"></ion-icon> Calculo do Valor
                            Sugerido
                        </h4>
                        <table class="regra-tabela">
                            <thead>
                                <tr>
                                    <th style="width:15%">Prioridade</th>
                                    <th>Metodo</th>
                                    <th>Quando e usado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>1a</strong></td>
                                    <td>Predicao XGBoost</td>
                                    <td>Se o ponto possui modelo treinado com R&sup2; &gt; 0.5</td>
                                </tr>
                                <tr>
                                    <td><strong>2a</strong></td>
                                    <td>Media historica da hora</td>
                                    <td>Fallback se nao ha modelo ou predicao indisponivel</td>
                                </tr>
                                <tr>
                                    <td><strong>3a</strong></td>
                                    <td>Valor esperado (detector)</td>
                                    <td>Ultimo recurso — estimativa do detector de anomalias</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Prioridade Hidraulica -->
                    <div class="regra-secao">
                        <h4><ion-icon name="water-outline" style="color:#2563eb;"></ion-icon> Prioridade Hidraulica</h4>
                        <table class="regra-tabela">
                            <thead>
                                <tr>
                                    <th>Prioridade</th>
                                    <th>Tipo</th>
                                    <th>Motivo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>1a</strong></td>
                                    <td>Reservatorio</td>
                                    <td>Impacto direto no abastecimento — nivel critico afeta toda a rede</td>
                                </tr>
                                <tr>
                                    <td><strong>2a</strong></td>
                                    <td>Macromedidor</td>
                                    <td>Grande volume — erro afeta balanco hidrico regional</td>
                                </tr>
                                <tr>
                                    <td><strong>3a</strong></td>
                                    <td>Pitometrica</td>
                                    <td>Monitoramento de rede — referencia para calibracao</td>
                                </tr>
                                <tr>
                                    <td><strong>4a</strong></td>
                                    <td>Pressao</td>
                                    <td>Indicador indireto — complementa analise de vazao</td>
                                </tr>
                                <tr>
                                    <td><strong>5a</strong></td>
                                    <td>Hidrometro</td>
                                    <td>Ponto final da rede — impacto localizado</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Acoes de Tratamento -->
                    <div class="regra-secao">
                        <h4><ion-icon name="checkmark-done-outline" style="color:#16a34a;"></ion-icon> Acoes de
                            Tratamento
                        </h4>
                        <table class="regra-tabela">
                            <thead>
                                <tr>
                                    <th>Acao</th>
                                    <th>O que acontece</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="regra-badge verde">Aprovar</span></td>
                                    <td>Inativa 60 registros da hora + insere 60 novos com valor sugerido. Registra
                                        usuario
                                        e observacao.</td>
                                </tr>
                                <tr>
                                    <td><span class="regra-badge azul">Ajustar</span></td>
                                    <td>Mesmo processo, mas com valor informado manualmente pelo operador.</td>
                                </tr>
                                <tr>
                                    <td><span class="regra-badge cinza">Ignorar</span></td>
                                    <td>Mantem dados originais. Justificativa obrigatoria para auditoria.</td>
                                </tr>
                            </tbody>
                        </table>
                        <p style="font-size:11px;color:#94a3b8;margin-top:8px;">
                            <ion-icon name="information-circle-outline" style="vertical-align:middle;"></ion-icon>
                            Todas as acoes registram: usuario, data/hora, observacao detalhada. Registros tratados
                            recebem
                            ID_TIPO_REGISTRO = 2 (manual/IA).
                        </p>
                    </div>
                </div>

                <!-- ========== ABA GLOSSARIO ========== -->
                <div id="abaGlossario" style="display:none;">

                    <div class="regra-secao">
                        <h4><ion-icon name="library-outline" style="color:#6366f1;"></ion-icon> Dicionario de Termos
                            Tecnicos</h4>
                        <p style="font-size:12px;color:#64748b;margin-bottom:12px;">
                            Explicacao dos termos estatisticos e de inteligencia artificial utilizados nesta tela.
                        </p>
                    </div>

                    <div class="glossario-item">
                        <div class="glossario-termo">Z-Score</div>
                        <div class="glossario-def">
                            <p>Mede quantos <strong>desvios padrao</strong> um valor esta distante da media. Exemplo: se
                                a
                                vazao media as 14h e 50 L/s com desvio padrao 5, um valor de 65 L/s tem Z-score = 3.0
                                (esta
                                3 desvios acima da media).</p>
                            <div class="glossario-formula">Z = (valor_real - media) / desvio_padrao</div>
                            <p><strong>De onde vem:</strong> calculado sobre o historico de 12+ semanas da mesma hora do
                                mesmo ponto. Quanto maior o Z-score, mais anomalo e o valor.</p>
                            <p><strong>Limiar dinamico:</strong> <code>4.0 - sensibilidade &times; 2.5</code>. Com
                                sensibilidade padrao (0.5), o limiar fica em 2.75.</p>
                        </div>
                    </div>

                    <div class="glossario-item">
                        <div class="glossario-termo">Threshold (Limiar)</div>
                        <div class="glossario-def">
                            <p>Valor de corte que separa "normal" de "anomalo". Se uma metrica ultrapassa o threshold, o
                                sistema classifica como anomalia. Cada metodo tem seu proprio threshold:</p>
                            <ul style="margin:6px 0;padding-left:20px;font-size:12px;color:#475569;">
                                <li><strong>Z-score:</strong> limiar dinamico (tipicamente 2.75)</li>
                                <li><strong>Autoencoder:</strong> percentil 95 do erro de reconstrucao no treino</li>
                                <li><strong>XGBoost:</strong> 2x o MAE historico do modelo</li>
                            </ul>
                        </div>
                    </div>

                    <div class="glossario-item">
                        <div class="glossario-termo">Desvio Padrao</div>
                        <div class="glossario-def">
                            <p>Medida de <strong>dispersao</strong> dos dados em relacao a media. Desvio padrao baixo =
                                dados concentrados; alto = dados espalhados. E a base do calculo do Z-score.</p>
                        </div>
                    </div>

                    <div class="glossario-item">
                        <div class="glossario-termo">XGBoost</div>
                        <div class="glossario-def">
                            <p>Algoritmo de <strong>machine learning</strong> baseado em arvores de decisao. Aprende
                                padroes
                                historicos de cada ponto (hora do dia, dia da semana, correlacoes com vizinhos) para
                                prever
                                o valor esperado. Se o valor real diverge muito da predicao, e detectada anomalia.</p>
                            <p><strong>Versao atual:</strong> v6.1 com features de shift temporal, delta e encoding
                                ciclico.
                            </p>
                        </div>
                    </div>

                    <div class="glossario-item">
                        <div class="glossario-termo">Autoencoder</div>
                        <div class="glossario-def">
                            <p>Rede neural que aprende a <strong>comprimir e reconstruir</strong> dados normais. Quando
                                recebe um dado anomalo, nao consegue reconstrui-lo bem, gerando erro de reconstrucao
                                alto.
                                Esse erro e comparado com o threshold treinado.</p>
                        </div>
                    </div>

                    <div class="glossario-item">
                        <div class="glossario-termo">R&sup2; (R-quadrado)</div>
                        <div class="glossario-def">
                            <p>Metrica de qualidade do modelo, varia de 0 a 1. Indica quanto da variacao dos dados o
                                modelo
                                consegue explicar. R&sup2; = 0.85 significa que o modelo explica 85% da variabilidade.
                                Acima
                                de 0.7 e considerado bom para dados de telemetria.</p>
                        </div>
                    </div>

                    <div class="glossario-item">
                        <div class="glossario-termo">MAE (Erro Medio Absoluto)</div>
                        <div class="glossario-def">
                            <p>Media das diferencas absolutas entre predicao e valor real. Exemplo: MAE = 3.5 L/s
                                significa
                                que o modelo erra em media 3.5 L/s. Usado como referencia para definir o threshold de
                                anomalia do XGBoost (2x MAE).</p>
                        </div>
                    </div>

                    <div class="glossario-item">
                        <div class="glossario-termo">MAPE (Erro Percentual Medio)</div>
                        <div class="glossario-def">
                            <p>Mesma ideia do MAE, mas em <strong>percentual</strong>. MAPE = 15% significa que o modelo
                                erra em media 15% do valor real. Util para comparar performance entre pontos com escalas
                                diferentes.</p>
                        </div>
                    </div>

                    <div class="glossario-item">
                        <div class="glossario-termo">Score Topologico</div>
                        <div class="glossario-def">
                            <p>Analisa os <strong>vizinhos no grafo hidraulico</strong> (flowchart). Se apenas este
                                ponto
                                esta anomalo mas vizinhos a montante e jusante estao normais, e provavel falha do sensor
                                (score alto). Se vizinhos tambem divergem, pode ser evento real na rede (score baixo).
                            </p>
                        </div>
                    </div>

                    <div class="glossario-item">
                        <div class="glossario-termo">Fallback Estatistico</div>
                        <div class="glossario-def">
                            <p>Quando o ponto <strong>nao possui modelo ML treinado</strong>, o sistema usa media
                                historica
                                e interpolacao como metodo alternativo de predicao. Indicado pelo badge sem o icone
                                <span class="modelo-badge" style="font-size:10px;"><ion-icon
                                        name="checkmark-circle"></ion-icon> ML</span>.
                            </p>
                        </div>
                    </div>

                    <div class="glossario-item">
                        <div class="glossario-termo">Batch (Lote)</div>
                        <div class="glossario-def">
                            <p>Processamento automatico que analisa <strong>todos os pontos ativos</strong> de uma vez
                                para
                                uma data especifica. Percorre cada ponto, chama o detector de anomalias + predicao,
                                classifica e gera pendencias. Tempo tipico: 2-5 minutos para ~53 pontos.</p>
                        </div>
                    </div>

                    <div class="glossario-item">
                        <div class="glossario-termo">MERGE / UPSERT</div>
                        <div class="glossario-def">
                            <p>Operacao de banco de dados que <strong>insere ou atualiza</strong>. Se ja existe
                                pendencia
                                para o mesmo ponto+data+hora+tipo, atualiza os valores; se nao existe, insere nova.
                                Garante
                                idempotencia — rodar o batch 2x nao duplica dados.</p>
                        </div>
                    </div>

                    <div class="glossario-item">
                        <div class="glossario-termo">Idempotente</div>
                        <div class="glossario-def">
                            <p>Operacao que pode ser executada <strong>multiplas vezes</strong> sem alterar o resultado
                                alem
                                da primeira execucao. O batch e idempotente: rodar 3x para a mesma data gera o mesmo
                                resultado que rodar 1x.</p>
                        </div>
                    </div>
                </div>

                <!-- ========== ABA METODOS DE CORRECAO ========== -->
                <div id="abaMetodos" style="display:none;">

                    <!-- Fluxo visual -->
                    <div
                        style="display:flex;align-items:center;gap:8px;padding:12px;background:#f0f9ff;border-radius:8px;margin-bottom:16px;flex-wrap:wrap;justify-content:center;">
                        <span
                            style="background:#fee2e2;color:#991b1b;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;">
                            <ion-icon name="warning-outline" style="vertical-align:middle;"></ion-icon> Anomalia
                            Detectada
                        </span>
                        <ion-icon name="arrow-forward-outline" style="color:#94a3b8;"></ion-icon>
                        <span
                            style="background:#dbeafe;color:#1e40af;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;">
                            <ion-icon name="calculator-outline" style="vertical-align:middle;"></ion-icon> 6 Metodos
                            Calculados
                        </span>
                        <ion-icon name="arrow-forward-outline" style="color:#94a3b8;"></ion-icon>
                        <span
                            style="background:#dcfce7;color:#166534;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;">
                            <ion-icon name="checkmark-circle-outline" style="vertical-align:middle;"></ion-icon> Score
                            de Aderencia
                        </span>
                        <ion-icon name="arrow-forward-outline" style="color:#94a3b8;"></ion-icon>
                        <span
                            style="background:#fef3c7;color:#92400e;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;">
                            <ion-icon name="person-outline" style="vertical-align:middle;"></ion-icon> Operador Decide
                        </span>
                    </div>

                    <!-- METODO 1: PCHIP -->
                    <div class="regra-metodo-card" style="margin-bottom:12px;">
                        <div class="regra-metodo-header" onclick="this.parentElement.classList.toggle('aberto')"
                            style="cursor:pointer;display:flex;align-items:center;gap:10px;padding:12px;background:#fffbeb;border:1px solid #fbbf24;border-radius:8px;">
                            <ion-icon name="analytics-outline" style="font-size:20px;color:#f59e0b;"></ion-icon>
                            <div style="flex:1;">
                                <strong style="font-size:13px;color:#92400e;">PCHIP &mdash; Interpolacao
                                    Monotonica</strong>
                                <div style="font-size:11px;color:#a16207;">Execucao: PHP puro | Dependencia: nenhuma
                                </div>
                            </div>
                            <ion-icon name="chevron-down-outline"
                                style="color:#a16207;transition:transform 0.2s;"></ion-icon>
                        </div>
                        <div class="regra-metodo-body"
                            style="display:none;padding:12px;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 8px 8px;font-size:12px;color:#334155;">
                            <p><strong>O que faz:</strong> Usa as horas validas como ancoras e interpola as horas
                                anomalas usando curvas monotonica de Hermite (Fritsch-Carlson). O resultado passa
                                exatamente pelas ancoras.</p>
                            <p style="margin:8px 0;"><strong>Quando e melhor:</strong> Poucas horas anomalas isoladas
                                (gaps pequenos), pois a interpolacao respeita a monotonia entre ancoras vizinhas.</p>
                            <p style="margin:8px 0;"><strong>Limitacao:</strong> Se muitas horas consecutivas sao
                                anomalas (ex: 6h seguidas), a interpolacao pode ser imprecisa pois nao ha ancoras
                                proximas.</p>
                            <div style="background:#f8fafc;padding:8px;border-radius:6px;margin-top:8px;">
                                <strong>Score de Aderencia:</strong> Calculado via Leave-One-Out Cross-Validation
                                (LOO-CV). Para cada hora valida, remove-a das ancoras, recalcula o PCHIP sem ela e
                                compara com o valor real. Evita o score 10.0 artificial.
                            </div>
                        </div>
                    </div>

                    <!-- METODO 2: XGBoost Rede -->
                    <div class="regra-metodo-card" style="margin-bottom:12px;">
                        <div class="regra-metodo-header" onclick="this.parentElement.classList.toggle('aberto')"
                            style="cursor:pointer;display:flex;align-items:center;gap:10px;padding:12px;background:#eff6ff;border:1px solid #93c5fd;border-radius:8px;">
                            <ion-icon name="hardware-chip-outline" style="font-size:20px;color:#3b82f6;"></ion-icon>
                            <div style="flex:1;">
                                <strong style="font-size:13px;color:#1e40af;">XGBoost Rede &mdash; Predicao
                                    Multivariada</strong>
                                <div style="font-size:11px;color:#3b82f6;">Execucao: TensorFlow container | Dependencia:
                                    modelo treinado</div>
                            </div>
                            <ion-icon name="chevron-down-outline"
                                style="color:#3b82f6;transition:transform 0.2s;"></ion-icon>
                        </div>
                        <div class="regra-metodo-body"
                            style="display:none;padding:12px;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 8px 8px;font-size:12px;color:#334155;">
                            <p><strong>O que faz:</strong> Usa o modelo XGBoost treinado com dados do ponto + tags
                                auxiliares (vizinhanca topologica do grafo hidraulico). Cada predicao e independente
                                &mdash; sem feedback ou drift.</p>
                            <p style="margin:8px 0;"><strong>Quando e melhor:</strong> Pontos com modelo treinado (icone
                                verde). Melhor para anomalias complexas onde o contexto da rede importa (ex: reducao de
                                vazao por problema em ponto vizinho).</p>
                            <p style="margin:8px 0;"><strong>Limitacao:</strong> Requer modelo treinado. Se nao houver,
                                retorna null e e ignorado. Qualidade depende do R&sup2; do modelo.</p>
                            <div style="background:#f8fafc;padding:8px;border-radius:6px;margin-top:8px;">
                                <strong>Score de Aderencia:</strong> Compara predicoes vs valores reais nas horas
                                validas. Formula:
                                <code>(0.40&times;R&sup2; + 0.30&times;(1-MAE_norm) + 0.30&times;(1-RMSE_norm)) &times; 10</code>
                            </div>
                        </div>
                    </div>

                    <!-- METODO 3: Historico + Tendencia -->
                    <div class="regra-metodo-card" style="margin-bottom:12px;">
                        <div class="regra-metodo-header" onclick="this.parentElement.classList.toggle('aberto')"
                            style="cursor:pointer;display:flex;align-items:center;gap:10px;padding:12px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;">
                            <ion-icon name="analytics-outline" style="font-size:20px;color:#16a34a;"></ion-icon>
                            <div style="flex:1;">
                                <strong style="font-size:13px;color:#166534;">Historico + Tendencia &mdash; Media
                                    Semanal Ajustada</strong>
                                <div style="font-size:11px;color:#16a34a;">Execucao: PHP puro | Dependencia: 4+ semanas
                                    de historico</div>
                            </div>
                            <ion-icon name="chevron-down-outline"
                                style="color:#16a34a;transition:transform 0.2s;"></ion-icon>
                        </div>
                        <div class="regra-metodo-body"
                            style="display:none;padding:12px;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 8px 8px;font-size:12px;color:#334155;">
                            <p><strong>O que faz:</strong> Calcula a media das ultimas 4-8 semanas (mesmo dia da semana)
                                e ajusta pelo fator de tendencia dos ultimos 3 dias. Formula:
                                <code>valor = media_historica &times; fator_tendencia</code>.
                            </p>
                            <p style="margin:8px 0;"><strong>Quando e melhor:</strong> Pontos com padrao semanal estavel
                                e sem mudancas operacionais bruscas.</p>
                            <p style="margin:8px 0;"><strong>Limitacao:</strong> Nao capta alteracoes operacionais
                                recentes que fogem do padrao semanal.</p>
                        </div>
                    </div>

                    <!-- METODO 4: Tendencia da Rede -->
                    <div class="regra-metodo-card" style="margin-bottom:12px;">
                        <div class="regra-metodo-header" onclick="this.parentElement.classList.toggle('aberto')"
                            style="cursor:pointer;display:flex;align-items:center;gap:10px;padding:12px;background:#f0fdfa;border:1px solid #5eead4;border-radius:8px;">
                            <ion-icon name="git-network-outline" style="font-size:20px;color:#14b8a6;"></ion-icon>
                            <div style="flex:1;">
                                <strong style="font-size:13px;color:#115e59;">Tendencia da Rede &mdash; Fator de
                                    Variacao dos Vizinhos</strong>
                                <div style="font-size:11px;color:#14b8a6;">Execucao: PHP puro | Dependencia: 2+ pontos
                                    na rede</div>
                            </div>
                            <ion-icon name="chevron-down-outline"
                                style="color:#14b8a6;transition:transform 0.2s;"></ion-icon>
                        </div>
                        <div class="regra-metodo-body"
                            style="display:none;padding:12px;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 8px 8px;font-size:12px;color:#334155;">
                            <p><strong>O que faz:</strong> Analisa como os outros pontos da rede estao variando hoje vs
                                historico. Se a rede esta 8% acima do normal, aplica +8% no historico do ponto atual.
                            </p>
                            <p style="margin:8px 0;"><strong>Quando e melhor:</strong> Detecta contexto operacional (ex:
                                rede operando acima do normal por demanda).</p>
                            <p style="margin:8px 0;"><strong>Limitacao:</strong> Requer pelo menos 2 pontos na mesma
                                rede com dados validos.</p>
                        </div>
                    </div>

                    <!-- METODO 5: Proporcao Historica -->
                    <div class="regra-metodo-card" style="margin-bottom:12px;">
                        <div class="regra-metodo-header" onclick="this.parentElement.classList.toggle('aberto')"
                            style="cursor:pointer;display:flex;align-items:center;gap:10px;padding:12px;background:#fdf4ff;border:1px solid #e879f9;border-radius:8px;">
                            <ion-icon name="pie-chart-outline" style="font-size:20px;color:#d946ef;"></ion-icon>
                            <div style="flex:1;">
                                <strong style="font-size:13px;color:#86198f;">Proporcao Historica &mdash; Balanco
                                    Hidraulico</strong>
                                <div style="font-size:11px;color:#d946ef;">Execucao: PHP puro | Dependencia: 2+ pontos +
                                    historico</div>
                            </div>
                            <ion-icon name="chevron-down-outline"
                                style="color:#d946ef;transition:transform 0.2s;"></ion-icon>
                        </div>
                        <div class="regra-metodo-body"
                            style="display:none;padding:12px;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 8px 8px;font-size:12px;color:#334155;">
                            <p><strong>O que faz:</strong> Calcula a proporcao media que o ponto representa na rede nas
                                ultimas 4 semanas e aplica ao total da rede hoje.</p>
                            <p style="margin:8px 0;"><strong>Quando e melhor:</strong> Pontos com participacao estavel
                                na rede. Unico metodo que usa balanco hidraulico.</p>
                            <p style="margin:8px 0;"><strong>Limitacao:</strong> Se a rede mudou (novos pontos,
                                manobras), a proporcao historica pode nao refletir a realidade.</p>
                        </div>
                    </div>

                    <!-- METODO 6: Minimos Quadrados -->
                    <div class="regra-metodo-card" style="margin-bottom:12px;">
                        <div class="regra-metodo-header" onclick="this.parentElement.classList.toggle('aberto')"
                            style="cursor:pointer;display:flex;align-items:center;gap:10px;padding:12px;background:#fff7ed;border:1px solid #fdba74;border-radius:8px;">
                            <ion-icon name="trending-up-outline" style="font-size:20px;color:#f97316;"></ion-icon>
                            <div style="flex:1;">
                                <strong style="font-size:13px;color:#9a3412;">Minimos Quadrados &mdash; Regressao Linear
                                    Temporal</strong>
                                <div style="font-size:11px;color:#f97316;">Execucao: PHP puro | Dependencia: 3+ semanas
                                    de historico</div>
                            </div>
                            <ion-icon name="chevron-down-outline"
                                style="color:#f97316;transition:transform 0.2s;"></ion-icon>
                        </div>
                        <div class="regra-metodo-body"
                            style="display:none;padding:12px;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 8px 8px;font-size:12px;color:#334155;">
                            <p><strong>O que faz:</strong> Ajusta uma reta de tendencia sobre os dados das ultimas 6-8
                                semanas (mesmo dia) e projeta o valor de hoje. Formula:
                                <code>y = a + b &times; x</code>.
                            </p>
                            <p style="margin:8px 0;"><strong>Quando e melhor:</strong> Captura tendencias graduais como
                                desgaste de medidor, demanda sazonal crescente ou deriva de calibracao.</p>
                            <p style="margin:8px 0;"><strong>Limitacao:</strong> Assume tendencia linear. Nao detecta
                                mudancas bruscas ou sazonalidade complexa.</p>
                        </div>
                    </div>

                    <!-- SCORE DE ADERENCIA -->
                    <div
                        style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-bottom:12px;">
                        <h4
                            style="margin:0 0 8px;font-size:13px;color:#1e293b;display:flex;align-items:center;gap:6px;">
                            <ion-icon name="star-outline" style="color:#f59e0b;"></ion-icon>
                            Formula do Score de Aderencia (0-10)
                        </h4>
                        <div
                            style="background:white;padding:10px;border-radius:6px;font-family:monospace;font-size:12px;color:#475569;border:1px solid #e2e8f0;">
                            Score = (0.40 &times; R&sup2; + 0.30 &times; (1 - MAE<sub>norm</sub>) + 0.30 &times; (1 -
                            RMSE<sub>norm</sub>)) &times; 10
                        </div>
                        <div style="margin-top:8px;font-size:11px;color:#64748b;">
                            <p style="margin:4px 0;"><strong>R&sup2;:</strong> Coeficiente de determinacao (0 a 1).
                                Quanto maior, melhor a estimativa explica a variacao real.</p>
                            <p style="margin:4px 0;"><strong>MAE<sub>norm</sub>:</strong> Erro absoluto medio
                                normalizado pela amplitude (max-min) dos valores reais.</p>
                            <p style="margin:4px 0;"><strong>RMSE<sub>norm</sub>:</strong> Raiz do erro quadratico medio
                                normalizado. Penaliza erros grandes.</p>
                            <p style="margin:4px 0;"><strong>Amostras:</strong> Numero de horas validas usadas na
                                comparacao.</p>
                        </div>
                    </div>

                    <!-- AUTO -->
                    <div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:8px;padding:12px;">
                        <h4
                            style="margin:0 0 6px;font-size:13px;color:#1e40af;display:flex;align-items:center;gap:6px;">
                            <ion-icon name="flash-outline"></ion-icon>
                            Modo AUTO (Recomendado)
                        </h4>
                        <p style="font-size:12px;color:#334155;margin:0;">
                            Quando o operador seleciona <strong>AUTO</strong>, o sistema aplica automaticamente o metodo
                            com o <strong>maior score de aderencia</strong>.
                            O campo <code>metodo_recomendado</code> no retorno da API indica qual metodo foi
                            selecionado.
                            O botao <strong>&ldquo;Aprovar rapido&rdquo;</strong> (&#10003;&#10003;) na tabela tambem
                            usa AUTO sem abrir o modal.
                        </p>
                    </div>
                </div>

            </div><!-- /modal-body -->
        </div><!-- /modal-container -->

    </div><!-- .page-container -->


    <!-- ============================================
     MODAL: DETALHE DO GRUPO (PONTO/DIA)
     ============================================ -->
    <div class="modal-overlay" id="modalDetalheGrupo">
        <div class="modal-box" style="max-width:780px;">
            <div class="modal-header">
                <h3><ion-icon name="layers-outline"></ion-icon> Tratamento por Ponto/Dia</h3>
                <button class="modal-close" onclick="fecharModal('modalDetalheGrupo')">&times;</button>
            </div>
            <div class="modal-body" id="modalDetalheGrupoBody" style="max-height:70vh;overflow-y:auto;">
                <div style="text-align:center;padding:20px;">
                    <div class="loading-spinner"></div>
                </div>
            </div>
            <div class="modal-footer"
                style="display:flex;gap:10px;padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;">
                <button type="button" class="btn-grupo-acao todas" id="btnAplicarTodasGrupo"
                    onclick="aplicarTodasHorasGrupo()">
                    <ion-icon name="checkmark-done-outline"></ion-icon>
                    Aplicar a TODAS as horas
                </button>
                <button type="button" class="btn-grupo-acao selecionadas" id="btnAplicarSelGrupo"
                    onclick="aplicarHorasSelecionadasGrupo()">
                    <ion-icon name="checkmark-outline"></ion-icon>
                    Aplicar as selecionadas
                </button>
                <button type="button" class="btn-grupo-acao cancelar" onclick="fecharModal('modalDetalheGrupo')"
                    style="margin-left:auto;">
                    Fechar
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================
     MODAL: AJUSTAR VALOR
     ============================================ -->
    <div class="modal-overlay" id="modalAjustar">
        <div class="modal-box" style="max-width:440px;">
            <div class="modal-header">
                <h3><ion-icon name="create-outline"></ion-icon> Ajustar Valor</h3>
                <button class="modal-close" onclick="fecharModal('modalAjustar')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="font-size:12px;color:#64748b;margin:0 0 8px;">Ponto: <strong id="ajustarPonto"></strong> |
                    Hora:
                    <strong id="ajustarHora"></strong>
                </p>
                <div style="display:flex;gap:12px;margin-bottom:12px;">
                    <div style="flex:1;background:#fee2e2;border-radius:8px;padding:10px;text-align:center;">
                        <div style="font-size:10px;color:#991b1b;text-transform:uppercase;font-weight:600;">Valor Real
                        </div>
                        <div style="font-size:18px;font-weight:700;color:#dc2626;" id="ajustarVlReal">-</div>
                    </div>
                    <div style="flex:1;background:#dcfce7;border-radius:8px;padding:10px;text-align:center;">
                        <div style="font-size:10px;color:#166534;text-transform:uppercase;font-weight:600;">Sugerido
                        </div>
                        <div style="font-size:18px;font-weight:700;color:#16a34a;" id="ajustarVlSugerido">-</div>
                    </div>
                </div>
                <label style="font-size:12px;font-weight:600;color:#334155;display:block;margin-bottom:4px;">Novo
                    valor:</label>
                <input type="number" step="0.0001" class="input-valor" id="ajustarValorInput"
                    placeholder="Informe o valor corrigido">
            </div>
            <div class="modal-footer">
                <button class="btn-modal cancelar" onclick="fecharModal('modalAjustar')">Cancelar</button>
                <button class="btn-modal confirmar" onclick="confirmarAjuste()">Aplicar Ajuste</button>
            </div>
        </div>
    </div>

    <!-- ============================================
     MODAL: IGNORAR (justificativa)
     ============================================ -->
    <div class="modal-overlay" id="modalIgnorar">
        <div class="modal-box" style="max-width:440px;">
            <div class="modal-header">
                <h3><ion-icon name="eye-off-outline"></ion-icon> Ignorar Pendencia</h3>
                <button class="modal-close" onclick="fecharModal('modalIgnorar')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="font-size:12px;color:#64748b;margin:0 0 8px;" id="ignorarInfo"></p>
                <label
                    style="font-size:12px;font-weight:600;color:#334155;display:block;margin-bottom:4px;">Justificativa
                    (obrigatoria):</label>
                <textarea class="textarea-just" id="ignorarJustificativa" placeholder="Descreva o motivo..."></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn-modal cancelar" onclick="fecharModal('modalIgnorar')">Cancelar</button>
                <button class="btn-modal confirmar" onclick="confirmarIgnorar()">Confirmar</button>
            </div>
        </div>
    </div>

    <!-- ============================================
     MODAL: REGRAS DOS METODOS DE CORRECAO
     ============================================ -->
    <div class="modal-overlay" id="modalRegrasCorrecao"
        onclick="if(event.target===this)fecharModal('modalRegrasCorrecao')">
        <div class="modal-box" style="max-width:720px;">
            <div class="modal-header" style="background:linear-gradient(135deg,#1e40af,#3b82f6);color:white;">
                <h3 style="color:white;"><ion-icon name="book-outline"></ion-icon> Regras dos Metodos de Correcao</h3>
                <button class="modal-close" onclick="fecharModal('modalRegrasCorrecao')"
                    style="color:white;">&times;</button>
            </div>
            <div class="modal-body" style="max-height:70vh;overflow-y:auto;padding:16px;">

                <!-- Fluxo visual -->
                <div
                    style="display:flex;align-items:center;gap:8px;padding:12px;background:#f0f9ff;border-radius:8px;margin-bottom:16px;flex-wrap:wrap;justify-content:center;">
                    <span
                        style="background:#fee2e2;color:#991b1b;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;">
                        <ion-icon name="warning-outline" style="vertical-align:middle;"></ion-icon> Anomalia Detectada
                    </span>
                    <ion-icon name="arrow-forward-outline" style="color:#94a3b8;"></ion-icon>
                    <span
                        style="background:#dbeafe;color:#1e40af;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;">
                        <ion-icon name="git-compare-outline" style="vertical-align:middle;"></ion-icon> 4 Metodos
                        Calculados
                    </span>
                    <ion-icon name="arrow-forward-outline" style="color:#94a3b8;"></ion-icon>
                    <span
                        style="background:#dcfce7;color:#166534;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;">
                        <ion-icon name="checkmark-circle-outline" style="vertical-align:middle;"></ion-icon> Score de
                        Aderencia
                    </span>
                    <ion-icon name="arrow-forward-outline" style="color:#94a3b8;"></ion-icon>
                    <span
                        style="background:#fef3c7;color:#92400e;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;">
                        <ion-icon name="person-outline" style="vertical-align:middle;"></ion-icon> Operador Decide
                    </span>
                </div>

                <!-- METODO 1: PCHIP -->
                <div class="regra-metodo-card" style="margin-bottom:12px;">
                    <div class="regra-metodo-header" onclick="this.parentElement.classList.toggle('aberto')"
                        style="cursor:pointer;display:flex;align-items:center;gap:10px;padding:12px;background:#fffbeb;border:1px solid #fbbf24;border-radius:8px;">
                        <ion-icon name="analytics-outline" style="font-size:20px;color:#f59e0b;"></ion-icon>
                        <div style="flex:1;">
                            <strong style="font-size:13px;color:#92400e;">PCHIP — Interpolacao Monotonica</strong>
                            <div style="font-size:11px;color:#a16207;">Execucao: PHP puro | Dependencia: nenhuma</div>
                        </div>
                        <ion-icon name="chevron-down-outline"
                            style="color:#a16207;transition:transform 0.2s;"></ion-icon>
                    </div>
                    <div class="regra-metodo-body"
                        style="display:none;padding:12px;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 8px 8px;font-size:12px;color:#334155;">
                        <p><strong>O que faz:</strong> Usa as horas validas como ancoras e interpola as horas anomalas
                            usando curvas monotonica de Hermite (Fritsch-Carlson). O resultado passa exatamente pelas
                            ancoras.</p>
                        <p style="margin:8px 0;"><strong>Quando e melhor:</strong> Poucas horas anomalas isoladas (gaps
                            pequenos), pois a interpolacao respeita a monotonia entre ancoras vizinhas.</p>
                        <p style="margin:8px 0;"><strong>Limitacao:</strong> Se muitas horas consecutivas sao anomalas
                            (ex: 6h seguidas), a interpolacao pode ser imprecisa pois nao ha ancoras proximas.</p>
                        <div style="background:#f8fafc;padding:8px;border-radius:6px;margin-top:8px;">
                            <strong>Score de Aderencia:</strong> Calculado via Leave-One-Out Cross-Validation (LOO-CV).
                            Para cada hora valida, remove-a das ancoras, recalcula o PCHIP sem ela e compara com o valor
                            real. Evita o score 10.0 artificial.
                        </div>
                    </div>
                </div>

                <!-- METODO 2: XGBoost Rede -->
                <div class="regra-metodo-card" style="margin-bottom:12px;">
                    <div class="regra-metodo-header" onclick="this.parentElement.classList.toggle('aberto')"
                        style="cursor:pointer;display:flex;align-items:center;gap:10px;padding:12px;background:#eff6ff;border:1px solid #93c5fd;border-radius:8px;">
                        <ion-icon name="hardware-chip-outline" style="font-size:20px;color:#3b82f6;"></ion-icon>
                        <div style="flex:1;">
                            <strong style="font-size:13px;color:#1e40af;">XGBoost Rede — Predicao Multivariada</strong>
                            <div style="font-size:11px;color:#3b82f6;">Execucao: TensorFlow container | Dependencia:
                                modelo treinado</div>
                        </div>
                        <ion-icon name="chevron-down-outline"
                            style="color:#3b82f6;transition:transform 0.2s;"></ion-icon>
                    </div>
                    <div class="regra-metodo-body"
                        style="display:none;padding:12px;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 8px 8px;font-size:12px;color:#334155;">
                        <p><strong>O que faz:</strong> Usa o modelo XGBoost treinado com dados do ponto + tags
                            auxiliares (vizinhanca topologica do grafo hidraulico). Cada predicao e independente — sem
                            feedback ou drift.</p>
                        <p style="margin:8px 0;"><strong>Quando e melhor:</strong> Pontos com modelo treinado (icone
                            verde). Melhor para anomalias complexas onde o contexto da rede importa (ex: reducao de
                            vazao por problema em ponto vizinho).</p>
                        <p style="margin:8px 0;"><strong>Limitacao:</strong> Requer modelo treinado. Se nao houver,
                            retorna null e e ignorado. Qualidade depende do R² do modelo.</p>
                        <div style="background:#f8fafc;padding:8px;border-radius:6px;margin-top:8px;">
                            <strong>Score de Aderencia:</strong> Compara predicoes vs valores reais nas horas validas.
                            Formula: <code>(0.40×R² + 0.30×(1-MAE_norm) + 0.30×(1-RMSE_norm)) × 10</code>
                        </div>
                    </div>
                </div>

                <!-- METODO 3: Media Movel Ponderada -->
                <div class="regra-metodo-card" style="margin-bottom:12px;">
                    <div class="regra-metodo-header" onclick="this.parentElement.classList.toggle('aberto')"
                        style="cursor:pointer;display:flex;align-items:center;gap:10px;padding:12px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;">
                        <ion-icon name="trending-up-outline" style="font-size:20px;color:#22c55e;"></ion-icon>
                        <div style="flex:1;">
                            <strong style="font-size:13px;color:#166534;">Media Movel Ponderada — Historico
                                Semanal</strong>
                            <div style="font-size:11px;color:#16a34a;">Execucao: PHP puro | Dependencia: 7+ dias de
                                historico</div>
                        </div>
                        <ion-icon name="chevron-down-outline"
                            style="color:#16a34a;transition:transform 0.2s;"></ion-icon>
                    </div>
                    <div class="regra-metodo-body"
                        style="display:none;padding:12px;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 8px 8px;font-size:12px;color:#334155;">
                        <p><strong>O que faz:</strong> Calcula a media ponderada dos ultimos 7 dias para a mesma hora e
                            dia da semana. Dias mais recentes recebem peso maior (peso decrescente: 7/28, 6/28,
                            5/28...).</p>
                        <p style="margin:8px 0;"><strong>Quando e melhor:</strong> Pontos com padrao semanal estavel.
                            Ex: reservatorios com demanda previsivel por dia da semana.</p>
                        <p style="margin:8px 0;"><strong>Limitacao:</strong> Nao capta mudancas bruscas recentes. Se
                            houve uma alteracao operacional nos ultimos dias, a media historica pode nao refletir.</p>
                        <div style="background:#f8fafc;padding:8px;border-radius:6px;margin-top:8px;">
                            <strong>Score de Aderencia:</strong> Mesmo calculo padrao (R², MAE_norm, RMSE_norm)
                            comparando media historica vs valor real nas horas validas do dia.
                        </div>
                    </div>
                </div>

                <!-- METODO 4: Prophet -->
                <div class="regra-metodo-card" style="margin-bottom:12px;">
                    <div class="regra-metodo-header" onclick="this.parentElement.classList.toggle('aberto')"
                        style="cursor:pointer;display:flex;align-items:center;gap:10px;padding:12px;background:#fdf4ff;border:1px solid #d8b4fe;border-radius:8px;">
                        <ion-icon name="pulse-outline" style="font-size:20px;color:#a855f7;"></ion-icon>
                        <div style="flex:1;">
                            <strong style="font-size:13px;color:#6b21a8;">Prophet — Decomposicao Sazonal</strong>
                            <div style="font-size:11px;color:#9333ea;">Execucao: TensorFlow container | Dependencia:
                                endpoint /api/prophet</div>
                        </div>
                        <ion-icon name="chevron-down-outline"
                            style="color:#9333ea;transition:transform 0.2s;"></ion-icon>
                    </div>
                    <div class="regra-metodo-body"
                        style="display:none;padding:12px;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 8px 8px;font-size:12px;color:#334155;">
                        <p><strong>O que faz:</strong> Decompoe a serie temporal em tendencia + sazonalidade
                            (diaria/semanal) usando o algoritmo Prophet do Meta. Projeta valores respeitando padroes
                            sazonais complexos.</p>
                        <p style="margin:8px 0;"><strong>Quando e melhor:</strong> Pontos com sazonalidade forte e
                            previsivel (ex: estacoes que variam significativamente entre dia/noite ou dias da semana).
                        </p>
                        <p style="margin:8px 0;"><strong>Limitacao:</strong> Endpoint /api/prophet pode nao estar
                            disponivel. Se falhar, retorna null e e ignorado silenciosamente. Mais lento que os outros
                            metodos.</p>
                        <div style="background:#f8fafc;padding:8px;border-radius:6px;margin-top:8px;">
                            <strong>Score de Aderencia:</strong> Mesmo calculo padrao. Tende a ter bom score em pontos
                            com padroes sazonais regulares.
                        </div>
                    </div>
                </div>

                <!-- SCORE DE ADERENCIA -->
                <div
                    style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-bottom:12px;">
                    <h4 style="margin:0 0 8px;font-size:13px;color:#1e293b;display:flex;align-items:center;gap:6px;">
                        <ion-icon name="star-outline" style="color:#f59e0b;"></ion-icon>
                        Formula do Score de Aderencia (0-10)
                    </h4>
                    <div
                        style="background:white;padding:10px;border-radius:6px;font-family:monospace;font-size:12px;color:#475569;border:1px solid #e2e8f0;">
                        Score = (0.40 × R² + 0.30 × (1 - MAE<sub>norm</sub>) + 0.30 × (1 - RMSE<sub>norm</sub>)) × 10
                    </div>
                    <div style="margin-top:8px;font-size:11px;color:#64748b;">
                        <p style="margin:4px 0;"><strong>R²:</strong> Coeficiente de determinacao (0 a 1). Quanto maior,
                            melhor a estimativa explica a variacao real.</p>
                        <p style="margin:4px 0;"><strong>MAE<sub>norm</sub>:</strong> Erro absoluto medio normalizado
                            pela amplitude (max-min) dos valores reais.</p>
                        <p style="margin:4px 0;"><strong>RMSE<sub>norm</sub>:</strong> Raiz do erro quadratico medio
                            normalizado. Penaliza erros grandes.</p>
                        <p style="margin:4px 0;"><strong>Amostras:</strong> Numero de horas validas usadas na
                            comparacao.</p>
                    </div>
                </div>

                <!-- AUTO -->
                <div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:8px;padding:12px;">
                    <h4 style="margin:0 0 6px;font-size:13px;color:#1e40af;display:flex;align-items:center;gap:6px;">
                        <ion-icon name="flash-outline"></ion-icon>
                        Modo AUTO (Recomendado)
                    </h4>
                    <p style="font-size:12px;color:#334155;margin:0;">
                        Quando o operador seleciona <strong>AUTO</strong>, o sistema aplica automaticamente o metodo com
                        o <strong>maior score de aderencia</strong>.
                        O campo <code>metodo_recomendado</code> no retorno da API indica qual metodo foi selecionado.
                        O botao <strong>"Aprovar rapido"</strong> (✓✓) na tabela tambem usa AUTO sem abrir o modal.
                    </p>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Chart.js para grafico de metodos A2★ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
    /**
     * SIMP 2.0 - Tratamento em Lote (Frontend)
     * @author Bruno - CESAN
     * @version 1.0 - Fase A2
     */

    // ============================================
    // Variaveis globais
    // ============================================

    /** Permissao de edicao */
    const podeEditar = <?= $podeEditar ? 'true' : 'false' ?>;

    /** Pagina atual */
    let paginaAtual = 1;
    let totalPaginas = 1;
    let totalRegistros = 0;
    const limitePorPagina = 50;

    /** Ordenacao atual (multi-coluna: Shift+click adiciona, click simples substitui) */
    let ordenacoes = [{ campo: 'prioridade', direcao: 'DESC' }];

    /** IDs selecionados para acao em massa */
    let idsSelecionados = [];

    /** Pontos com modelo ML treinado (carregado via TensorFlow) */
    var pontosComModelo = new Set();

    /** Pendencia sendo editada (modais) */
    let pendenciaAtual = null;

    /** Modo massa: 'individual' ou 'massa' */
    let modoIgnorar = 'individual';

    // ============================================
    // Inicializacao
    // ============================================

    document.addEventListener('DOMContentLoaded', function () {
        // Iniciar Select2 em todos os dropdowns de filtro
        inicializarSelect2();

        // Carregar dados iniciais
        carregarEstatisticas();

        // Carregar pontos com modelo treinado (silencioso)
        try {
            fetch('bd/operacoes/predicaoTensorFlow.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ acao: 'status' })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.modelos) {
                        data.modelos.forEach(m => {
                            if (m.cd_ponto) pontosComModelo.add(parseInt(m.cd_ponto));
                        });
                    }
                })
                .catch(() => { });
        } catch (e) { }

        // Busca com debounce (filtra automaticamente ao digitar)
        let buscaTimeout = null;
        document.getElementById('filtroBusca').addEventListener('input', function () {
            clearTimeout(buscaTimeout);
            buscaTimeout = setTimeout(function () {
                paginaAtual = 1;
                carregarPendencias();
            }, 400);
        });
    });

    function inicializarSelect2() {
        $('.filtro-select2').select2({
            width: '100%',
            minimumResultsForSearch: 0,
            language: {
                noResults: function () { return 'Nenhum resultado'; }
            }
        });

        // Autofocus no input de pesquisa ao abrir qualquer dropdown Select2
        $('.filtro-select2').on('select2:open', function () {
            setTimeout(function () {
                var searchField = document.querySelector('.select2-container--open .select2-search__field');
                if (searchField) searchField.focus();
            }, 50);
        });

        // Disparar filtro automaticamente ao mudar qualquer select
        $('.filtro-select2').on('change', function () {
            paginaAtual = 1;
            carregarPendencias();
        });
    }


    // ============================================
    // Carregar Estatisticas
    // ============================================

    /**
     * Busca estatisticas e preenche cards + dropdown de datas.
     */
    function carregarEstatisticas() {
        fetch('bd/operacoes/tratamentoLote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'estatisticas' })
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;

                const r = data.resumo || {};

                // Cards
                document.getElementById('stPendentes').textContent = r.PENDENTES || 0;
                document.getElementById('stTratadas').textContent = r.TRATADAS || 0;
                document.getElementById('stTecnicas').textContent = r.TECNICAS_PENDENTES || 0;
                document.getElementById('stOperacionais').textContent = r.OPERACIONAIS_PENDENTES || 0;
                document.getElementById('stConfianca').textContent = r.CONFIANCA_MEDIA_PENDENTES
                    ? (parseFloat(r.CONFIANCA_MEDIA_PENDENTES) * 100).toFixed(1) + '%' : '-';
                document.getElementById('stPontos').textContent = r.PONTOS_AFETADOS || 0;

                // Data do ultimo batch
                if (data.dt_ultimo_batch) {
                    const dtBatch = new Date(data.dt_ultimo_batch);
                    const pad = n => String(n).padStart(2, '0');
                    document.getElementById('dtUltimoBatch').textContent =
                        pad(dtBatch.getDate()) + '/' + pad(dtBatch.getMonth() + 1) + '/' + dtBatch.getFullYear() +
                        ' ' + pad(dtBatch.getHours()) + ':' + pad(dtBatch.getMinutes());
                    document.getElementById('infoBatch').style.display = 'inline-flex';
                }

                // Dropdown de datas
                const selData = document.getElementById('filtroData');
                const dataAtual = $(selData).val();

                // Destruir Select2 temporariamente para atualizar options
                $(selData).select2('destroy');
                selData.innerHTML = '';

                if (data.datas_disponiveis && data.datas_disponiveis.length > 0) {
                    data.datas_disponiveis.forEach(d => {
                        const dt = d.DT_REFERENCIA ? d.DT_REFERENCIA.split('T')[0] : d.DT_REFERENCIA;
                        const opt = document.createElement('option');
                        opt.value = dt;
                        opt.textContent = formatarData(dt) + ' (' + d.QTD + ')';
                        selData.appendChild(opt);
                    });
                    // Manter data selecionada ou selecionar a primeira
                    if (dataAtual && selData.querySelector('option[value="' + dataAtual + '"]')) {
                        selData.value = dataAtual;
                    }
                } else {
                    selData.innerHTML = '<option value="">Nenhuma data</option>';
                }

                // Reiniciar Select2
                $(selData).select2({
                    width: '100%',
                    minimumResultsForSearch: 0,
                    language: { noResults: function () { return 'Nenhum resultado'; } }
                });
                $(selData).on('select2:open', function () {
                    setTimeout(function () {
                        var sf = document.querySelector('.select2-container--open .select2-search__field');
                        if (sf) sf.focus();
                    }, 50);
                });

                // Carregar pendencias com a data selecionada
                carregarPendencias();
            })
            .catch(err => {
                console.error('Erro ao carregar estatisticas:', err);
            });
    }


    // ============================================
    // Carregar Pendencias
    // ============================================

    /**
     * Busca pendencias com filtros e paginacao.
     */
    function carregarPendencias() {
        const filtros = {
            acao: 'listar_agrupado',
            data: $('#filtroData').val() || '',
            status: $('#filtroStatus').val() || '0',
            classe: $('#filtroClasse').val() || '',
            tipo_anomalia: $('#filtroTipoAnomalia').val() || '',
            tipo_medidor: $('#filtroTipoMedidor').val() || '',
            unidade: $('#filtroUnidade').val() || '',
            confianca_min: $('#filtroConfianca').val() || '',
            busca: document.getElementById('filtroBusca').value || '',
            pagina: paginaAtual,
            limite: limitePorPagina,
            ordenar: ordenacoes.map(o => o.campo).join(','),
            direcao: ordenacoes.map(o => o.direcao).join(',')
        };

        // Loading
        document.getElementById('tabelaBody').innerHTML = `
        <tr><td colspan="10" style="text-align:center;padding:40px;">
            <div class="loading-spinner"></div>
            <p style="margin:8px 0 0;color:#94a3b8;font-size:12px;">Buscando pendencias...</p>
        </td></tr>
    `;

        fetch('bd/operacoes/tratamentoLote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(filtros)
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    document.getElementById('tabelaBody').innerHTML = `
                <tr><td colspan="10" class="empty-state">
                    <ion-icon name="warning-outline"></ion-icon>
                    <h3>Erro</h3><p>${data.error || 'Erro ao carregar'}</p>
                </td></tr>
            `;
                    return;
                }

                totalRegistros = data.total || 0;
                totalPaginas = data.paginas || 1;

                document.getElementById('tabelaTitulo').textContent =
                    'Pendencias' + (filtros.data ? ' — ' + formatarData(filtros.data) : '');
                document.getElementById('tabelaInfo').textContent =
                    totalRegistros + ' registro(s)';

                renderizarTabelaAgrupada(data.grupos || []);
                renderizarPaginacao();
                limparSelecao();
            })
            .catch(err => {
                document.getElementById('tabelaBody').innerHTML = `
            <tr><td colspan="10" class="empty-state">
                <ion-icon name="cloud-offline-outline"></ion-icon>
                <h3>Erro de conexao</h3><p>${err.message}</p>
            </td></tr>
        `;
            });
    }


    // ============================================
    // Renderizar Tabela
    // ============================================

    /**
 * Renderiza tabela agrupada por ponto de medicao/dia.
 * Cada linha = 1 ponto + 1 dia, com badges das horas anomalas.
 *
 * @param {Array} grupos - Lista de grupos do backend (listar_agrupado)
 */
    function renderizarTabelaAgrupada(grupos) {
        const tbody = document.getElementById('tabelaBody');

        if (!grupos.length) {
            tbody.innerHTML = `
            <tr><td colspan="10" class="empty-state">
                <ion-icon name="checkmark-done-outline" style="color:#22c55e;"></ion-icon>
                <h3>Nenhuma pendencia</h3>
                <p>Nenhum registro encontrado com os filtros aplicados</p>
            </td></tr>
        `;
            return;
        }

        let html = '';
        grupos.forEach(g => {
            // Chave unica do grupo: ponto_data
            const grupoKey = g.CD_PONTO_MEDICAO + '_' + (g.DT_REFERENCIA || '').split('T')[0];

            // Parsear arrays concatenados
            const horas = (g.DS_HORAS || '').split(',').map(Number);
            const ids = (g.DS_IDS || '').split(',').map(Number);
            const statusHoras = (g.DS_STATUS_HORAS || '').split(',').map(Number);
            const tiposHoras = (g.DS_TIPOS_HORAS || '').split(',');

            // Verificar selecao
            const todosIds = ids;
            const selecionada = todosIds.some(id => idsSelecionados.includes(id)) ? ' selecionada' : '';

            // Data formatada
            const dtRef = (g.DT_REFERENCIA || '').split('T')[0];
            const dtFormatada = formatarData(dtRef);

            // Tipo medidor badge
            const tipoMedBadge = getTipoMedidorBadge(g.ID_TIPO_MEDIDOR);

            // Horas como badges coloridas por status
            let horasBadges = '';
            horas.forEach((h, i) => {
                const st = statusHoras[i] || 0;
                let corClasse = 'hora-pendente';    // amarelo
                if (st === 1 || st === 2) corClasse = 'hora-tratada';   // verde
                if (st === 3) corClasse = 'hora-ignorada';              // cinza
                horasBadges += `<span class="badge-hora ${corClasse}" title="ID #${ids[i]} - ${tiposHoras[i] || ''}">${String(h).padStart(2, '0')}h</span>`;
            });

            // Contadores de status
            const qtdPend = parseInt(g.QTD_PENDENTES) || 0;
            const qtdTrat = parseInt(g.QTD_TRATADAS) || 0;
            const qtdIgn = parseInt(g.QTD_IGNORADAS) || 0;
            const qtdTotal = parseInt(g.QTD_HORAS) || 0;

            // Status geral
            let statusGeral = '';
            if (qtdPend === 0 && qtdTotal > 0) {
                statusGeral = '<span class="badge aprovada">Concluido</span>';
            } else if (qtdPend === qtdTotal) {
                statusGeral = `<span class="badge pendente">${qtdPend} pendente(s)</span>`;
            } else {
                statusGeral = `<span class="badge pendente">${qtdPend}/${qtdTotal} pend.</span>`;
            }

            // Severidade
            const sevBadge = `<span class="badge ${g.DS_SEVERIDADE_MAX}">${ucfirst(g.DS_SEVERIDADE_MAX)}</span>`;

            // Classe predominante
            const classeBadge = parseInt(g.ID_CLASSE_PREDOMINANTE) === 2
                ? '<span class="badge operacional">Operacional</span>'
                : '<span class="badge tecnica">Tecnica</span>';

            // Confianca media
            const conf = parseFloat(g.VL_CONFIANCA_MEDIA || 0);
            const confPct = (conf * 100).toFixed(1);
            const confNivel = conf >= 0.95 ? 'alta' : (conf >= 0.85 ? 'confiavel' : 'atencao');
            const confBadge = `<span class="badge conf-${confNivel}">${confPct}%</span>`;
            const confBar = `<div class="score-bar"><div class="score-bar-fill ${confNivel}" style="width:${confPct}%"></div></div>`;

            // Montar linha
            html += `<tr class="${selecionada}" data-grupo="${grupoKey}" data-cd-ponto="${g.CD_PONTO_MEDICAO}" data-dt-ref="${dtRef}" data-ids="${ids.join(',')}">`;

            if (podeEditar) {
                html += `<td><input type="checkbox" class="chk-sel chk-grupo" data-ids="${ids.join(',')}" 
                onchange="toggleSelecaoGrupo(this)"></td>`;
            }

            // Ponto (com badge de modelo treinado)
            const temModelo = pontosComModelo.has(parseInt(g.CD_PONTO_MEDICAO));
            const modeloBadge = temModelo
                ? '<ion-icon name="hardware-chip-outline" title="Modelo XGBoost treinado" style="color:#22c55e;font-size:13px;vertical-align:middle;margin-left:4px;"></ion-icon>'
                : '<ion-icon name="hardware-chip-outline" title="Sem modelo treinado" style="color:#cbd5e1;font-size:13px;vertical-align:middle;margin-left:4px;"></ion-icon>';

            html += `
                <td>
                    <div style="font-weight:600;color:#1e293b;font-size:12px;" title="${g.DS_PONTO_NOME || ''}">${g.DS_CODIGO_FORMATADO || g.CD_PONTO_MEDICAO}${modeloBadge}</div>
                    <div style="font-size:11px;color:#64748b;margin-top:2px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${g.DS_PONTO_NOME || ''}">${g.DS_PONTO_NOME || '-'}</div>
                </td>
            `;

            // Data
            html += `<td><div style="font-weight:500;">${dtFormatada}</div></td>`;

            // Tipo medidor
            html += `<td>${tipoMedBadge}</td>`;

            // Horas anomalas (badges)
            html += `<td><div class="horas-container">${horasBadges}</div></td>`;

            // Classe
            html += `<td>${classeBadge}</td>`;

            // Severidade
            html += `<td>${sevBadge}</td>`;

            // Confianca
            html += `<td>${confBar} ${confBadge}</td>`;

            // Status
            html += `<td>${statusGeral}</td>`;

            // Acoes
            if (podeEditar) {
                const desabilitado = qtdPend === 0 ? ' style="opacity:0.3;pointer-events:none;"' : '';
                html += `
                <td>
                    <div class="acoes-rapidas"${desabilitado}>
                        <button class="btn-acao aprovar" onclick="aprovarGrupoRapido('${g.CD_PONTO_MEDICAO}', '${dtRef}', '${ids.join(',')}')" title="Aprovar todas com melhor metodo (AUTO)">
                            <ion-icon name="checkmark-done-outline"></ion-icon>
                        </button>
                        <button class="btn-acao detalhe" onclick="abrirDetalheGrupo('${g.CD_PONTO_MEDICAO}', '${dtRef}', '${g.DS_CODIGO_FORMATADO}', '${ids.join(',')}', '${horas.join(',')}', '${statusHoras.join(',')}')" title="Ver detalhes e escolher metodo" style="opacity:1;pointer-events:auto;">
                            <ion-icon name="eye-outline"></ion-icon>
                        </button>
                        <button class="btn-acao validacao" onclick="irParaValidacao(${g.CD_PONTO_MEDICAO}, '${dtRef}')" title="Abrir Validacao de Dados" style="opacity:1;pointer-events:auto;">
                            <ion-icon name="open-outline"></ion-icon>
                        </button>
                    </div>
                </td>
            `;
            }

            html += '</tr>';
        });

        tbody.innerHTML = html;
    }


    // ============================================
    // Paginacao
    // ============================================

    function renderizarPaginacao() {
        const container = document.getElementById('paginacao');
        if (totalPaginas <= 1) { container.style.display = 'none'; return; }
        container.style.display = 'flex';

        const ini = (paginaAtual - 1) * limitePorPagina + 1;
        const fim = Math.min(paginaAtual * limitePorPagina, totalRegistros);
        document.getElementById('pagInfo').textContent = `${ini}-${fim} de ${totalRegistros}`;

        let btnsHtml = '';
        btnsHtml += `<button class="btn-pag" onclick="irPagina(${paginaAtual - 1})" ${paginaAtual <= 1 ? 'disabled' : ''}>&laquo;</button>`;

        const maxBtns = 5;
        let inicio = Math.max(1, paginaAtual - Math.floor(maxBtns / 2));
        let fimPag = Math.min(totalPaginas, inicio + maxBtns - 1);
        if (fimPag - inicio < maxBtns - 1) inicio = Math.max(1, fimPag - maxBtns + 1);

        for (let i = inicio; i <= fimPag; i++) {
            btnsHtml += `<button class="btn-pag ${i === paginaAtual ? 'ativa' : ''}" onclick="irPagina(${i})">${i}</button>`;
        }

        btnsHtml += `<button class="btn-pag" onclick="irPagina(${paginaAtual + 1})" ${paginaAtual >= totalPaginas ? 'disabled' : ''}>&raquo;</button>`;

        document.getElementById('pagBtns').innerHTML = btnsHtml;
    }

    function irPagina(p) {
        if (p < 1 || p > totalPaginas) return;
        paginaAtual = p;
        carregarPendencias();
    }

    /**
     * Ordenar por coluna.
     * Click simples = substitui ordenacao.
     * Shift+Click = adiciona/altera coluna secundaria.
     */
    function ordenarPor(campo, event) {
        if (event && event.shiftKey) {
            // Shift+Click: adicionar ou alternar coluna existente
            const idx = ordenacoes.findIndex(o => o.campo === campo);
            if (idx >= 0) {
                ordenacoes[idx].direcao = ordenacoes[idx].direcao === 'DESC' ? 'ASC' : 'DESC';
            } else {
                ordenacoes.push({ campo: campo, direcao: 'DESC' });
            }
        } else {
            // Click simples: se ja e a unica, alterna direcao; senao substitui
            if (ordenacoes.length === 1 && ordenacoes[0].campo === campo) {
                ordenacoes[0].direcao = ordenacoes[0].direcao === 'DESC' ? 'ASC' : 'DESC';
            } else {
                ordenacoes = [{ campo: campo, direcao: 'DESC' }];
            }
        }

        // Atualizar visual dos headers
        document.querySelectorAll('.th-sort').forEach(th => {
            th.classList.remove('asc', 'desc');
            const badge = th.querySelector('.sort-order');
            if (badge) badge.remove();
        });
        ordenacoes.forEach((o, i) => {
            const th = document.querySelector(`.th-sort[onclick*="'${o.campo}'"]`);
            if (th) {
                th.classList.add(o.direcao.toLowerCase());
                if (ordenacoes.length > 1) {
                    const badge = document.createElement('span');
                    badge.className = 'sort-order';
                    badge.textContent = i + 1;
                    th.appendChild(badge);
                }
            }
        });

        paginaAtual = 1;
        carregarPendencias();
    }


    // ============================================
    // Selecao em massa
    // ============================================

    function toggleSelecao(cd, checked) {
        if (checked && !idsSelecionados.includes(cd)) {
            idsSelecionados.push(cd);
        } else if (!checked) {
            idsSelecionados = idsSelecionados.filter(x => x !== cd);
        }
        atualizarMassaBar();
        // Highlight na linha
        const tr = document.querySelector(`tr[data-cd="${cd}"]`);
        if (tr) tr.classList.toggle('selecionada', checked);
    }

    function toggleTodos(chkTodos) {
        document.querySelectorAll('.chk-item').forEach(chk => {
            const cd = parseInt(chk.dataset.cd);
            chk.checked = chkTodos.checked;
            toggleSelecao(cd, chkTodos.checked);
        });
    }

    function limparSelecao() {
        idsSelecionados = [];
        document.querySelectorAll('.chk-item').forEach(chk => chk.checked = false);
        document.querySelectorAll('tr.selecionada').forEach(tr => tr.classList.remove('selecionada'));
        const chkTodos = document.getElementById('chkTodos');
        if (chkTodos) chkTodos.checked = false;
        atualizarMassaBar();
    }

    function atualizarMassaBar() {
        const bar = document.getElementById('massaBar');
        if (!bar) return;
        if (idsSelecionados.length > 0) {
            bar.classList.add('ativa');
            document.getElementById('massaCount').textContent = idsSelecionados.length;
        } else {
            bar.classList.remove('ativa');
        }
    }


    // ============================================
    // Acoes individuais
    // ============================================

    /**
     * Aprova uma pendencia (aplica valor sugerido).
     */
    function aprovarUm(cd) {
        if (!confirm('Aprovar esta pendencia? O valor sugerido sera aplicado.')) return;
        chamarTratamento({ acao: 'aprovar', cd_pendencia: cd });
    }

    /**
     * Abre modal para ajustar valor.
     */
    function abrirAjustar(cd) {
        // Buscar dados da linha
        const tr = document.querySelector(`tr[data-cd="${cd}"]`);
        pendenciaAtual = cd;

        // Preencher modal
        document.getElementById('ajustarPonto').textContent = tr ? tr.querySelector('td:nth-child(2) div').textContent : cd;
        document.getElementById('ajustarHora').textContent = tr ? tr.querySelector('td:nth-child(3) div:nth-child(2)').textContent : '';

        const vlRealEl = tr ? tr.querySelector('.vl-real') : null;
        const vlSugEl = tr ? tr.querySelector('.vl-sugerido') : null;
        document.getElementById('ajustarVlReal').textContent = vlRealEl ? vlRealEl.textContent : '-';
        document.getElementById('ajustarVlSugerido').textContent = vlSugEl ? vlSugEl.textContent : '-';

        // Pre-preencher input com sugerido
        const vlSug = vlSugEl ? vlSugEl.textContent.replace('→ ', '') : '';
        document.getElementById('ajustarValorInput').value = vlSug !== '-' ? vlSug : '';

        abrirModal('modalAjustar');
        setTimeout(() => document.getElementById('ajustarValorInput').focus(), 200);
    }

    function confirmarAjuste() {
        const valor = parseFloat(document.getElementById('ajustarValorInput').value);
        if (isNaN(valor)) { alert('Informe um valor valido'); return; }
        fecharModal('modalAjustar');
        chamarTratamento({ acao: 'ajustar', cd_pendencia: pendenciaAtual, valor: valor });
    }

    /**
     * Abre modal para ignorar (justificativa obrigatoria).
     */
    function abrirIgnorar(cd) {
        modoIgnorar = 'individual';
        pendenciaAtual = cd;
        document.getElementById('ignorarInfo').textContent = 'Pendencia #' + cd;
        document.getElementById('ignorarJustificativa').value = '';
        abrirModal('modalIgnorar');
        setTimeout(() => document.getElementById('ignorarJustificativa').focus(), 200);
    }

    function confirmarIgnorar() {
        const just = document.getElementById('ignorarJustificativa').value.trim();
        if (!just) { alert('Justificativa obrigatoria'); return; }
        fecharModal('modalIgnorar');

        if (modoIgnorar === 'massa') {
            // Ignorar em massa
            fetch('bd/operacoes/tratamentoLote.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ acao: 'ignorar_massa', ids: idsSelecionados, justificativa: just })
            })
                .then(r => r.json())
                .then(d => {
                    if (d.success) { toast(d.message, 'ok'); limparSelecao(); carregarEstatisticas(); }
                    else toast(d.error || 'Erro', 'err');
                })
                .catch(() => toast('Erro de conexao', 'err'));
        } else {
            chamarTratamento({ acao: 'ignorar', cd_pendencia: pendenciaAtual, justificativa: just });
        }
    }


    // ============================================
    // Acoes em massa
    // ============================================

    function aprovarMassa() {
        if (!idsSelecionados.length) return;
        if (!confirm(`Aprovar ${idsSelecionados.length} pendencia(s)?`)) return;

        fetch('bd/operacoes/tratamentoLote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'aprovar_massa', ids: idsSelecionados })
        })
            .then(r => r.json())
            .then(d => {
                if (d.success) { toast(d.message, 'ok'); limparSelecao(); carregarEstatisticas(); }
                else toast(d.error || 'Erro', 'err');
            })
            .catch(() => toast('Erro de conexao', 'err'));
    }

    function abrirIgnorarMassa() {
        if (!idsSelecionados.length) return;
        modoIgnorar = 'massa';
        document.getElementById('ignorarInfo').textContent = idsSelecionados.length + ' pendencia(s) selecionada(s)';
        document.getElementById('ignorarJustificativa').value = '';
        abrirModal('modalIgnorar');
        setTimeout(() => document.getElementById('ignorarJustificativa').focus(), 200);
    }


    // ============================================
    // Chamar tratamento generico
    // ============================================

    function chamarTratamento(dados) {
        fetch('bd/operacoes/tratamentoLote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    toast(d.message || 'Tratamento aplicado', 'ok');
                    carregarEstatisticas(); // Recarrega stats + tabela
                } else {
                    toast(d.error || 'Erro ao aplicar tratamento', 'err');
                }
            })
            .catch(() => toast('Erro de conexao', 'err'));
    }


    // ============================================
    // A2★ — Variaveis globais de metodos de correcao
    // ============================================

    /** Cache dos metodos carregados por pendencia */
    var cacheMetodos = {};

    /** Instancia do Chart.js para o grafico de 3 curvas */
    var chartMetodos = null;

    /** Metodo atualmente selecionado no dropdown */
    var metodoSelecionado = null;

    /** Dados da pendencia atualmente aberta no modal */
    var pendenciaDetalheAtual = null;

    // ============================================
    // A2★ — Abrir detalhe com grafico e metodos
    // ============================================

    /**
     * Abre o modal de detalhe de uma pendencia.
     * Versao A2★: inclui grafico de 3 curvas + dropdown de metodos.
     *
     * @param {number} cd - CD_CHAVE da pendencia
     */
    function abrirDetalhe(cd) {
        abrirModal('modalDetalhe');
        pendenciaDetalheAtual = cd;
        cacheMetodos = {}; // Limpar cache ao abrir nova pendencia
        // Loading no body do modal
        document.getElementById('modalDetalheBody').innerHTML =
            '<div style="text-align:center;padding:40px 0;"><div class="loading-spinner"></div><p style="font-size:12px;color:#64748b;margin-top:12px;">Carregando detalhes...</p></div>';

        // Buscar dados da pendencia
        fetch('bd/operacoes/tratamentoLote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'detalhe', cd_pendencia: cd })
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    document.getElementById('modalDetalheBody').innerHTML =
                        '<p style="color:#dc2626;text-align:center;">Erro: ' + (data.error || 'Desconhecido') + '</p>';
                    return;
                }

                renderizarModalDetalheA2Star(data);
            })
            .catch(err => {
                document.getElementById('modalDetalheBody').innerHTML =
                    '<p style="color:#dc2626;text-align:center;">Erro: ' + err.message + '</p>';
            });
    }

    /**
     * Renderiza o conteudo do modal de detalhe (versao A2★).
     * Inclui info basica + grafico + dropdown + scores.
     *
     * @param {object} data - Resposta do endpoint 'detalhe'
     */
    function renderizarModalDetalheA2Star(data) {
        var p = data.pendencia || data;
        var html = '';

        // ------ Secao 1: Info basica (2 colunas) ------
        html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 16px;margin-bottom:16px;">';
        html += infoCell('Ponto', escapeHtml(p.DS_PONTO_NOME || '-'));
        html += infoCell('Codigo', '<code style="font-size:11px;background:#f1f5f9;padding:2px 6px;border-radius:4px;">' + escapeHtml(p.DS_CODIGO_FORMATADO || '-') + '</code>');
        html += infoCell('Data', formatarData(p.DT_REFERENCIA || '-'));
        html += infoCell('Hora', escapeHtml(p.DS_HORA_FORMATADA || (p.NR_HORA != null ? String(p.NR_HORA).padStart(2, '0') + ':00' : '-')));
        html += infoCell('Tipo Anomalia', escapeHtml(p.DS_TIPO_ANOMALIA || '-'));
        html += infoCell('Classe', escapeHtml(p.DS_CLASSE_ANOMALIA || '-'));
        html += infoCell('Severidade', '<span class="badge-severidade ' + (p.DS_SEVERIDADE || 'media') + '">' + ucfirst(p.DS_SEVERIDADE || 'media') + '</span>');
        html += infoCell('Confianca', fmtNum(p.VL_CONFIANCA * 100, 1) + '%');
        html += infoCell('Valor Real', '<span style="color:#dc2626;font-weight:700;">' + fmtNum(p.VL_REAL, 2) + '</span>');
        html += infoCell('Valor Sugerido', '<span style="color:#22c55e;font-weight:700;">' + fmtNum(p.VL_SUGERIDO, 2) + '</span>');
        html += infoCell('Media Historica', fmtNum(p.VL_MEDIA_HISTORICA, 2));
        html += infoCell('Predicao XGBoost', fmtNum(p.VL_PREDICAO_XGBOOST, 2));
        html += infoCell('Z-Score', fmtNum(p.VL_ZSCORE, 2));
        html += infoCell('Vizinhos Anomalos', (p.QTD_VIZINHOS_ANOMALOS || 0));
        html += '</div>';

        // Descricao
        if (p.DS_DESCRICAO) {
            html += '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;margin-bottom:16px;font-size:12px;color:#475569;">'
                + '<strong>Descri\u00e7\u00e3o:</strong> ' + escapeHtml(p.DS_DESCRICAO) + '</div>';
        }

        // ------ Secao 2: A2★ Grafico de metodos ------
        html += '<div style="border-top:1px solid #e2e8f0;padding-top:16px;margin-top:8px;">';
        html += '<h4 style="font-size:13px;font-weight:700;color:#1e293b;margin:0 0 12px;display:flex;align-items:center;gap:6px;">'
            + '<ion-icon name="bar-chart-outline" style="font-size:16px;color:#3b82f6;"></ion-icon>'
            + 'M\u00e9todos de Corre\u00e7\u00e3o</h4>';

        // Dropdown de metodos
        html += '<div class="metodo-selector-container">';
        html += '<label>M\u00e9todo:</label>';
        html += '<select id="selectMetodoCorrecao" style="width:100%;max-width:320px;"></select>';
        html += '<span id="badgeScoreMetodo" class="metodo-score-badge" style="display:none;"></span>';
        html += '</div>';

        // Metricas do metodo selecionado
        html += '<div class="metodo-metricas" id="metodoMetricas" style="display:none;"></div>';

        // Container do grafico
        html += '<div class="grafico-container" id="graficoMetodosContainer">';
        html += '<div class="grafico-loading" id="graficoLoading">';
        html += '<ion-icon name="sync-outline"></ion-icon>';
        html += '<p>Calculando m\u00e9todos...</p>';
        html += '</div>';
        html += '<canvas id="chartMetodosCanvas"></canvas>';
        html += '</div>';

        // Legenda
        html += '<div class="grafico-legenda">';
        html += '<div class="grafico-legenda-item"><div class="grafico-legenda-dot real"></div>Valor Real (sensor)</div>';
        html += '<div class="grafico-legenda-item"><div class="grafico-legenda-dot estimado"></div>Estimativa (m\u00e9todo)</div>';
        html += '<div class="grafico-legenda-item"><div class="grafico-legenda-dot corrigido"></div>Valor Corrigido</div>';
        html += '</div>';
        html += '</div>';

        // ------ Secao 3: Scores individuais ------
        if (p.VL_SCORE_ESTATISTICO != null) {
            html += '<div style="border-top:1px solid #e2e8f0;padding-top:16px;margin-top:16px;">';
            html += '<h4 style="font-size:13px;font-weight:700;color:#1e293b;margin:0 0 12px;">Scores de Confian\u00e7a</h4>';
            html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 12px;">';
            html += scoreCell('Estat\u00edstico (30%)', p.VL_SCORE_ESTATISTICO);
            html += scoreCell('Modelo (30%)', p.VL_SCORE_MODELO);
            html += scoreCell('Topol\u00f3gico (20%)', p.VL_SCORE_TOPOLOGICO);
            html += scoreCell('Hist\u00f3rico (10%)', p.VL_SCORE_HISTORICO);
            html += scoreCell('Padr\u00e3o (10%)', p.VL_SCORE_PADRAO);
            html += scoreCell('CONFIAN\u00c7A FINAL', p.VL_CONFIANCA, true);
            html += '</div></div>';
        }

        // ------ Secao 4: Outras anomalias no dia ------
        if (data.outras_horas && data.outras_horas.length > 0) {
            html += '<div style="border-top:1px solid #e2e8f0;padding-top:16px;margin-top:16px;">';
            html += '<h4 style="font-size:13px;font-weight:700;color:#1e293b;margin:0 0 8px;">Outras anomalias no mesmo dia</h4>';
            html += '<div style="display:flex;gap:6px;flex-wrap:wrap;">';
            data.outras_horas.forEach(function (o) {
                var statusCor = o.ID_STATUS == 0 ? '#fbbf24' : (o.ID_STATUS == 1 || o.ID_STATUS == 2 ? '#22c55e' : '#94a3b8');
                html += '<span style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:4px 8px;font-size:11px;">'
                    + '<span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:' + statusCor + ';margin-right:4px;"></span>'
                    + escapeHtml(o.DS_HORA_FORMATADA) + ' \u2014 ' + ucfirst(o.DS_SEVERIDADE)
                    + '</span>';
            });
            html += '</div></div>';
        }

        // Reserva GNN
        html += '<div class="gnn-placeholder" style="margin-top:16px;">'
            + '<ion-icon name="git-network-outline"></ion-icon>'
            + '<p><strong>Contexto GNN (Fase B)</strong> \u2014 Coer\u00eancia sist\u00eamica e propaga\u00e7\u00e3o de eventos.</p>'
            + '</div>';

        document.getElementById('modalDetalheBody').innerHTML = html;

        // Inicializar Select2 no dropdown de metodos
        setTimeout(function () {
            initSelectMetodos(p);
        }, 100);
    }

    /**
     * Helper: celula de info basica no modal.
     */
    function infoCell(label, value) {
        return '<div style="font-size:11px;"><span style="color:#94a3b8;display:block;">' + label + '</span>'
            + '<span style="color:#1e293b;font-weight:600;">' + value + '</span></div>';
    }

    /**
     * Helper: celula de score no modal.
     */
    function scoreCell(label, valor, destaque) {
        var pct = valor != null ? (valor * 100).toFixed(1) : '-';
        var cor = valor >= 0.85 ? '#22c55e' : (valor >= 0.70 ? '#3b82f6' : '#f59e0b');
        var bg = destaque ? 'background:#f0f9ff;border:1px solid #bfdbfe;' : 'background:#f8fafc;border:1px solid #e2e8f0;';
        return '<div style="' + bg + 'border-radius:8px;padding:8px 10px;display:flex;justify-content:space-between;align-items:center;">'
            + '<span style="font-size:11px;color:#64748b;">' + label + '</span>'
            + '<span style="font-size:' + (destaque ? '15' : '13') + 'px;font-weight:700;color:' + cor + ';">' + pct + '%</span>'
            + '</div>';
    }

    // ============================================
    // A2★ — Inicializar dropdown de metodos
    // ============================================

    /**
     * Inicializa o Select2 de metodos e dispara o calculo.
     *
     * @param {object} pendencia - Dados da pendencia
     */
    function initSelectMetodos(pendencia) {
        var sel = document.getElementById('selectMetodoCorrecao');
        if (!sel) return;

        // Opcao inicial de loading
        sel.innerHTML = '<option value="">Calculando m\u00e9todos...</option>';

        // Inicializar Select2
        $(sel).select2({
            dropdownParent: document.getElementById('modalDetalhe'),
            placeholder: 'Selecione um m\u00e9todo',
            minimumResultsForSearch: Infinity, // Sem busca — poucos itens
            width: '100%'
        });

        // Evento de troca
        $(sel).on('change', function () {
            var metodoId = $(this).val();
            if (metodoId) {
                selecionarMetodo(metodoId);
            }
        });

        // Disparar calculo dos metodos
        carregarMetodosCorrecao(pendencia);
    }

    /**
     * Chama o endpoint metodoCorrecao.php e popula o dropdown.
     *
     * @param {object} pendencia - Dados da pendencia
     */
    function carregarMetodosCorrecao(pendencia) {
        var cdPonto = pendencia.CD_PONTO_MEDICAO;
        var dtRef = pendencia.DT_REFERENCIA;
        var tipoMedidor = pendencia.ID_TIPO_MEDIDOR || 1;

        // Verificar cache
        var cacheKey = cdPonto + '_' + dtRef;
        if (cacheMetodos[cacheKey]) {
            popularDropdownMetodos(cacheMetodos[cacheKey]);
            return;
        }

        fetch('bd/operacoes/metodoCorrecao.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'calcular_metodos',
                cd_ponto: cdPonto,
                dt_referencia: dtRef,
                tipo_medidor: tipoMedidor
            })
        })
            .then(r => r.json())
            .then(data => {
                // Esconder loading do grafico
                var loading = document.getElementById('graficoLoading');
                if (loading) loading.style.display = 'none';

                if (!data.success || !data.metodos || data.metodos.length === 0) {
                    var sel = document.getElementById('selectMetodoCorrecao');
                    if (sel) {
                        sel.innerHTML = '<option value="">Nenhum m\u00e9todo dispon\u00edvel</option>';
                        $(sel).trigger('change.select2');
                    }
                    return;
                }

                // Guardar no cache
                cacheMetodos[cacheKey] = data;
                popularDropdownMetodos(data);
            })
            .catch(err => {
                var loading = document.getElementById('graficoLoading');
                if (loading) loading.style.display = 'none';
                console.error('Erro ao carregar metodos:', err);
            });
    }

    /**
     * Popula o dropdown de metodos com os resultados do calculo.
     *
     * @param {object} data - Resposta do metodoCorrecao.php
     */
    function popularDropdownMetodos(data) {
        var sel = document.getElementById('selectMetodoCorrecao');
        if (!sel) return;

        var html = '<option value="auto">AUTO \u2014 Melhor m\u00e9todo (' + escapeHtml(data.metodo_recomendado || '') + ')</option>';

        data.metodos.forEach(function (m) {
            var scoreLabel = m.score_aderencia != null ? ' (' + m.score_aderencia.toFixed(2) + ')' : '';
            html += '<option value="' + m.id + '">' + escapeHtml(m.nome) + scoreLabel + '</option>';
        });

        sel.innerHTML = html;

        // Atualizar Select2
        $(sel).trigger('change.select2');

        // Selecionar AUTO por default (que exibe o melhor metodo)
        $(sel).val('auto').trigger('change');
    }

    /**
     * Callback ao selecionar um metodo no dropdown.
     * Atualiza grafico + metricas + badge.
     *
     * @param {string} metodoId - ID do metodo selecionado ('auto', 'xgboost_rede', etc.)
     */
    function selecionarMetodo(metodoId) {
        var cdPonto = pendenciaDetalheAtual;
        // Buscar cache da pendencia atualmente aberta
        var data = null;
        for (var key in cacheMetodos) {
            // A chave é cdPonto_dtReferencia — pegar a mais recente
            data = cacheMetodos[key];
        }
        if (!data || !data.metodos) return;

        // Encontrar metodo
        var metodo;
        if (metodoId === 'auto') {
            metodo = data.metodos[0]; // Primeiro = maior score (ja ordenado)
        } else {
            metodo = data.metodos.find(function (m) { return m.id === metodoId; });
        }
        if (!metodo) return;

        metodoSelecionado = metodo;

        // Atualizar badge de score
        var badge = document.getElementById('badgeScoreMetodo');
        if (badge) {
            var scoreClasse = metodo.score_aderencia >= 8 ? 'alta' : (metodo.score_aderencia >= 5 ? 'media' : 'baixa');
            badge.className = 'metodo-score-badge ' + scoreClasse;
            badge.innerHTML = '<ion-icon name="star" style="font-size:10px;"></ion-icon> ' + metodo.score_aderencia.toFixed(2);
            badge.style.display = 'inline-flex';
        }

        // Atualizar metricas
        atualizarMetricasMetodo(metodo);

        // Atualizar grafico
        atualizarGraficoMetodos(data.valores_reais, metodo.valores, data.horas_anomalas);
    }

    /**
     * Atualiza os cards de metricas do metodo selecionado.
     */
    function atualizarMetricasMetodo(metodo) {
        var container = document.getElementById('metodoMetricas');
        if (!container) return;

        var m = metodo.metricas || {};
        var html = '';

        html += '<div class="metodo-metrica-item"><div class="mmv">' + (metodo.score_aderencia != null ? metodo.score_aderencia.toFixed(2) : '-') + '</div><div class="mml">Ader\u00eancia</div></div>';

        if (m.r2 != null) html += '<div class="metodo-metrica-item"><div class="mmv">' + (m.r2 * 100).toFixed(1) + '%</div><div class="mml">R\u00b2</div></div>';
        if (m.mae != null) html += '<div class="metodo-metrica-item"><div class="mmv">' + m.mae.toFixed(2) + '</div><div class="mml">MAE</div></div>';
        if (m.rmse != null) html += '<div class="metodo-metrica-item"><div class="mmv">' + m.rmse.toFixed(2) + '</div><div class="mml">RMSE</div></div>';
        if (m.amostras != null) html += '<div class="metodo-metrica-item"><div class="mmv">' + m.amostras + '</div><div class="mml">Amostras</div></div>';

        container.innerHTML = html;
        container.style.display = 'flex';
    }

    // ============================================
    // A2★ — Grafico Chart.js de 3 curvas
    // ============================================

    /**
     * Cria ou atualiza o grafico com 3 datasets:
     *   1. Laranja: valor real (sensor)
     *   2. Verde pontilhado: estimativa do metodo
     *   3. Azul: valor corrigido (estimativa nas horas anomalas, real nas demais)
     *
     * @param {object} valoresReais    Mapa hora => valor
     * @param {object} valoresEstimados Mapa hora => valor do metodo
     * @param {array}  horasAnomalas   Lista de horas anomalas
     */
    function atualizarGraficoMetodos(valoresReais, valoresEstimados, horasAnomalas) {
        var canvas = document.getElementById('chartMetodosCanvas');
        if (!canvas) return;

        var labels = [];
        var dataReal = [];
        var dataEstimado = [];
        var dataCorrigido = [];
        var bgColors = [];

        for (var h = 0; h < 24; h++) {
            labels.push(String(h).padStart(2, '0') + ':00');

            var vReal = valoresReais[h] != null ? valoresReais[h] : null;
            var vEst = valoresEstimados[h] != null ? valoresEstimados[h] : null;

            dataReal.push(vReal);
            dataEstimado.push(vEst);

            // Valor corrigido: nas horas anomalas usa estimativa, nas demais usa real
            if (horasAnomalas.indexOf(h) >= 0) {
                dataCorrigido.push(vEst); // Hora anomala → estimativa
                bgColors.push('rgba(239, 68, 68, 0.08)'); // Fundo vermelho claro
            } else {
                dataCorrigido.push(vReal); // Hora normal → real
                bgColors.push('transparent');
            }
        }

        // Destruir grafico anterior se existir
        if (chartMetodos) {
            chartMetodos.destroy();
            chartMetodos = null;
        }

        var ctx = canvas.getContext('2d');

        chartMetodos = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Valor Real',
                        data: dataReal,
                        borderColor: '#f97316',
                        backgroundColor: 'rgba(249, 115, 22, 0.1)',
                        borderWidth: 2,
                        pointRadius: 3,
                        pointBackgroundColor: '#f97316',
                        tension: 0.3,
                        fill: false,
                        order: 2
                    },
                    {
                        label: 'Estimativa',
                        data: dataEstimado,
                        borderColor: '#22c55e',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [6, 3],
                        pointRadius: 2,
                        pointBackgroundColor: '#22c55e',
                        tension: 0.3,
                        fill: false,
                        order: 1
                    },
                    {
                        label: 'Corrigido',
                        data: dataCorrigido,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.08)',
                        borderWidth: 2.5,
                        pointRadius: 3,
                        pointBackgroundColor: function (ctx) {
                            var idx = ctx.dataIndex;
                            return horasAnomalas.indexOf(idx) >= 0 ? '#3b82f6' : 'rgba(59, 130, 246, 0.3)';
                        },
                        tension: 0.3,
                        fill: false,
                        order: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: { display: false }, // Usamos legenda custom
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleFont: { size: 12 },
                        bodyFont: { size: 11 },
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            title: function (items) {
                                var h = items[0].dataIndex;
                                var ehAnomala = horasAnomalas.indexOf(h) >= 0;
                                return items[0].label + (ehAnomala ? ' \u26a0 ANOMALA' : '');
                            },
                            label: function (item) {
                                var val = item.raw != null ? item.raw.toFixed(2) : '-';
                                return '  ' + item.dataset.label + ': ' + val;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        ticks: { font: { size: 10 }, maxRotation: 0 }
                    },
                    y: {
                        grid: { color: 'rgba(0,0,0,0.06)' },
                        ticks: { font: { size: 10 } },
                        beginAtZero: false
                    }
                }
            }
        });
    }

    // ============================================
    // A2★ — Aprovar com metodo selecionado
    // ============================================

    /**
     * Sobrescreve aprovarUm para incluir metodo e score.
     * Chamar APOS a funcao original no arquivo.
     */
    var _aprovarUmOriginal = typeof aprovarUm === 'function' ? aprovarUm : null;

    /**
     * Aprova uma pendencia com metodo de correcao (A2★).
     * Se o modal de detalhe esta aberto e tem metodo selecionado, envia junto.
     *
     * @param {number} cd - CD_CHAVE da pendencia
     */
    function aprovarUm(cd) {
        // Montar payload com metodo se disponivel
        var payload = { acao: 'aprovar', cd_pendencia: cd };

        if (metodoSelecionado && pendenciaDetalheAtual === cd) {
            payload.metodo_correcao = metodoSelecionado.id;
            payload.score_aderencia = metodoSelecionado.score_aderencia;
        }

        if (!confirm('Aprovar esta pend\u00eancia?' +
            (payload.metodo_correcao ? '\nM\u00e9todo: ' + metodoSelecionado.nome + ' (score: ' + metodoSelecionado.score_aderencia.toFixed(2) + ')' : '')
        )) return;

        chamarTratamento(payload);
    }

    /**
     * Sobrescreve confirmarAjuste para incluir metodo.
     */
    var _confirmarAjusteOriginal = typeof confirmarAjuste === 'function' ? confirmarAjuste : null;

    function confirmarAjuste() {
        var valor = parseFloat(document.getElementById('ajustarValorInput').value);
        if (isNaN(valor)) { alert('Informe um valor v\u00e1lido'); return; }
        fecharModal('modalAjustar');

        var payload = {
            acao: 'ajustar',
            cd_pendencia: pendenciaAtual,
            valor: valor,
            metodo_correcao: 'manual'
        };

        // Se tinha metodo selecionado no detalhe, usar ele
        if (metodoSelecionado && pendenciaDetalheAtual === pendenciaAtual) {
            payload.metodo_correcao = metodoSelecionado.id;
            payload.score_aderencia = metodoSelecionado.score_aderencia;
        }

        chamarTratamento(payload);
    }

    // ============================================
    // A2★ — Cleanup ao fechar modal
    // ============================================

    /**
     * Extende o fecharModal para limpar grafico e cache.
     */
    var _fecharModalOriginal = typeof fecharModal === 'function' ? fecharModal : null;






    /**
 * Fecha o modal de Regras/Glossario/Metodos
 */
    function fecharRegras() {
        var modal = document.getElementById('modalRegras');
        if (modal) modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    /**
     * Abre o modal de Regras/Glossario/Metodos
     */
    function abrirRegras() {
        var modal = document.getElementById('modalRegras');
        if (modal) modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }


    // ============================================
    // Executar Batch
    // ============================================

    function executarBatch() {
        if (!confirm('Executar motor de analise batch?\nIsso pode levar alguns minutos.')) return;

        const btn = document.getElementById('btnExecutarBatch');
        const textoOriginal = btn.innerHTML;
        btn.innerHTML = '<div class="loading-spinner" style="width:14px;height:14px;border-width:2px;"></div> Processando...';
        btn.disabled = true;

        fetch('bd/operacoes/motorBatchTratamento.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'executar_batch' })
        })
            .then(r => r.json())
            .then(data => {
                btn.innerHTML = textoOriginal;
                btn.disabled = false;

                if (data.success) {
                    toast(`Batch concluido: ${data.processados} pontos, ${data.pendencias_geradas} pendencias, ${data.tempo_segundos}s`, 'ok');
                    carregarEstatisticas();
                } else {
                    toast(data.error || 'Erro no batch', 'err');
                }
            })
            .catch(err => {
                btn.innerHTML = textoOriginal;
                btn.disabled = false;
                toast('Erro de conexao: ' + err.message, 'err');
            });
    }

    /** Abre modal de regras */
    function abrirRegras() {
        document.getElementById('modalRegras').classList.add('active');
    }

    /** Fecha modal de regras */
    function fecharRegras() {
        document.getElementById('modalRegras').classList.remove('active');
    }

    /**
 * Troca aba do modal de Regras/Glossario/Metodos.
 * @param {string}      aba  Nome da aba (regras|glossario|metodos)
 * @param {HTMLElement} btn  Botao clicado
 */
    function trocarAbaRegra(aba, btn) {
        // Desativar todas
        document.querySelectorAll('.regra-tab').forEach(t => t.classList.remove('active'));
        document.getElementById('abaRegras').style.display = 'none';
        document.getElementById('abaGlossario').style.display = 'none';
        document.getElementById('abaMetodos').style.display = 'none';
        // Ativar a clicada
        btn.classList.add('active');
        if (aba === 'regras') document.getElementById('abaRegras').style.display = '';
        else if (aba === 'glossario') document.getElementById('abaGlossario').style.display = '';
        else if (aba === 'metodos') document.getElementById('abaMetodos').style.display = '';
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') fecharRegras();
    });
    /**
     * Redireciona para a tela de validacao (operacoes.php) com o ponto e data pre-selecionados.
     * Abre em nova aba para nao perder o contexto do tratamento em lote.
     */
    function irParaValidacao(cdPonto, data) {
        if (!cdPonto || !data) return;
        const partes = data.split('-');
        const mes = partes.length >= 2 ? parseInt(partes[1]) : new Date().getMonth() + 1;
        const ano = partes.length >= 1 ? partes[0] : new Date().getFullYear();
        window.open(`operacoes.php?abrirValidacao=1&cdPonto=${cdPonto}&dataValidacao=${data}&mes=${mes}&ano=${ano}`, '_blank');
    }


    // ============================================
    // Utilitarios
    // ============================================

    /** Abre modal pelo ID */
    function abrirModal(id) { document.getElementById(id).classList.add('ativo'); }

    /** Fecha modal pelo ID */
    function fecharModal(id) { document.getElementById(id).classList.remove('ativo'); }

    /** Fechar modal ao clicar no overlay */
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('modal-overlay')) {
            e.target.classList.remove('ativo');
        }
    });

    /** Fechar modal com ESC */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.ativo').forEach(m => m.classList.remove('ativo'));
        }
    });

    /** Limpar filtros */
    function limparFiltros() {
        $('#filtroStatus').val('0').trigger('change');
        $('#filtroClasse').val('').trigger('change');
        $('#filtroTipoAnomalia').val('').trigger('change');
        $('#filtroTipoMedidor').val('').trigger('change');
        $('#filtroUnidade').val('').trigger('change');
        $('#filtroConfianca').val('').trigger('change');
        document.getElementById('filtroBusca').value = '';
        paginaAtual = 1;
        carregarPendencias();
    }

    /** Badge do tipo de medidor */
    function getTipoMedidorBadge(tipo) {
        const map = {
            1: { cls: 'macro', icon: 'water-outline', nome: 'Macro' },
            2: { cls: 'pitometrica', icon: 'speedometer-outline', nome: 'Pito' },
            4: { cls: 'pressao', icon: 'thermometer-outline', nome: 'Pressao' },
            6: { cls: 'reservatorio', icon: 'cube-outline', nome: 'Reserv.' },
            8: { cls: 'hidrometro', icon: 'reader-outline', nome: 'Hidro' },
        };
        const m = map[tipo] || { cls: 'macro', icon: 'help-outline', nome: 'Outro' };
        return `<span class="tipo-medidor-badge ${m.cls}"><ion-icon name="${m.icon}"></ion-icon> ${m.nome}</span>`;
    }

    /** Formatar data YYYY-MM-DD para DD/MM/YYYY */
    function formatarData(dt) {
        if (!dt) return '-';
        const d = dt.split('T')[0].split('-');
        return d.length === 3 ? d[2] + '/' + d[1] + '/' + d[0] : dt;
    }

    /** Uppercase first */
    function ucfirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }

    /** Truncar string */
    function truncar(s, max) { return s && s.length > max ? s.substring(0, max) + '...' : s; }

    /** Formatar numero */
    function fmtNum(v, dec) {
        if (v == null || v === '') return '-';
        return parseFloat(v).toFixed(dec !== undefined ? dec : 4);
    }

    /** Escape HTML */
    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    /** Item de detalhe */
    function detalheItem(label, valor) {
        return `<div class="detalhe-item"><label>${label}</label><span>${valor}</span></div>`;
    }

    /** Item de score */
    function scoreItem(label, valor, destaque) {
        const v = valor != null ? (parseFloat(valor) * 100).toFixed(1) + '%' : '-';
        const est = destaque ? 'background:#1e3a5f;color:white;' : '';
        const estV = destaque ? 'color:white;' : '';
        return `<div class="score-item" style="${est}"><label style="${destaque ? 'color:rgba(255,255,255,0.7);' : ''}">${label}</label><div class="score-valor" style="${estV}">${v}</div></div>`;
    }

    /** Toast (usa funcao global do sistema se existir) */
    function toast(msg, tipo) {
        if (typeof window.toast === 'function' && window.toast !== toast) {
            window.toast(msg, tipo);
            return;
        }
        // Fallback simples
        const cores = { ok: '#22c55e', err: '#ef4444', inf: '#3b82f6' };
        const div = document.createElement('div');
        div.style.cssText = `position:fixed;top:80px;right:20px;z-index:99999;background:${cores[tipo] || cores.inf};color:white;padding:12px 20px;border-radius:10px;font-size:13px;box-shadow:0 4px 12px rgba(0,0,0,0.15);max-width:400px;transition:opacity .3s;`;
        div.textContent = msg;
        document.body.appendChild(div);
        setTimeout(() => { div.style.opacity = '0'; setTimeout(() => div.remove(), 300); }, 4000);
    }


    /**
     * Controle de grupo selecionado na tabela agrupada.
     * Marca/desmarca todos os IDs do grupo na selecao global.
     *
     * @param {HTMLInputElement} checkbox - Checkbox do grupo
     */
    function toggleSelecaoGrupo(checkbox) {
        const ids = checkbox.dataset.ids.split(',').map(Number);
        if (checkbox.checked) {
            ids.forEach(id => {
                if (!idsSelecionados.includes(id)) idsSelecionados.push(id);
            });
        } else {
            ids.forEach(id => {
                const idx = idsSelecionados.indexOf(id);
                if (idx > -1) idsSelecionados.splice(idx, 1);
            });
        }
        atualizarBarraMassa();
    }


    /**
     * Aprovacao rapida de TODAS as horas pendentes do grupo.
     * Usa metodo AUTO (melhor score) sem abrir modal.
     *
     * @param {string} cdPonto  - Codigo do ponto
     * @param {string} dtRef    - Data de referencia
     * @param {string} idsStr   - IDs concatenados por virgula
     */
    function aprovarGrupoRapido(cdPonto, dtRef, idsStr) {
        const ids = idsStr.split(',').map(Number);
        const qtd = ids.length;

        if (!confirm(`Aprovar todas as ${qtd} hora(s) anomalas deste ponto com metodo AUTO?`)) return;

        fetch('bd/operacoes/tratamentoLote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'aprovar_grupo',
                cd_ponto: parseInt(cdPonto),
                dt_referencia: dtRef,
                metodo: 'AUTO',
                ids: [] // vazio = todas as pendentes
            })
        })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    toast(d.message || 'Grupo aprovado', 'ok');
                    carregarEstatisticas();
                } else {
                    toast(d.error || 'Erro ao aprovar grupo', 'err');
                }
            })
            .catch(() => toast('Erro de conexao', 'err'));
    }


    // ---- Variaveis do modal de detalhe do grupo ----
    var grupoDetalheAtual = {
        cdPonto: null,
        dtRef: null,
        codigoFormatado: null,
        ids: [],
        horas: [],
        statusHoras: []
    };


    /**
     * Abre o modal de detalhe do grupo com lista de horas e opcoes.
     * Carrega metodos de correcao via metodoCorrecao.php.
     *
     * @param {string} cdPonto  - Codigo do ponto
     * @param {string} dtRef    - Data de referencia
     * @param {string} codFmt   - Codigo formatado
     * @param {string} idsStr   - IDs concatenados por virgula
     * @param {string} horasStr - Horas concatenadas por virgula
     * @param {string} statusStr- Status por hora concatenados
     */
    function abrirDetalheGrupo(cdPonto, dtRef, codFmt, idsStr, horasStr, statusStr) {
        // Salvar contexto do grupo
        grupoDetalheAtual = {
            cdPonto: parseInt(cdPonto),
            dtRef: dtRef,
            codigoFormatado: codFmt,
            ids: idsStr.split(',').map(Number),
            horas: horasStr.split(',').map(Number),
            statusHoras: statusStr.split(',').map(Number)
        };

        // Limpar cache de metodos ao abrir novo grupo
        cacheMetodos = {};

        // Montar corpo do modal
        const body = document.getElementById('modalDetalheGrupoBody');
        const statusMap = { 0: 'Pendente', 1: 'Aprovada', 2: 'Ajustada', 3: 'Ignorada' };
        const statusClasse = { 0: 'pendente', 1: 'aprovada', 2: 'ajustada', 3: 'ignorada' };

        // Cabecalho com info do ponto
        let htmlInfo = `
        <div class="grupo-info-header">
            <div class="grupo-info-item">
                <span class="grupo-label">Ponto</span>
                <span class="grupo-valor">${codFmt}</span>
            </div>
            <div class="grupo-info-item">
                <span class="grupo-label">Data</span>
                <span class="grupo-valor">${formatarData(dtRef)}</span>
            </div>
            <div class="grupo-info-item">
                <span class="grupo-label">Total horas</span>
                <span class="grupo-valor">${grupoDetalheAtual.horas.length}</span>
            </div>
        </div>
    `;

        // Lista de horas com checkboxes
        let htmlHoras = `
        <div class="grupo-horas-lista">
            <div class="grupo-horas-header">
                <label class="chk-label-all">
                    <input type="checkbox" id="chkTodasHorasGrupo" checked onchange="toggleTodasHorasGrupo(this)">
                    <span>Selecionar todas</span>
                </label>
                <span class="grupo-horas-count" id="grupoHorasCount">${grupoDetalheAtual.horas.length} selecionada(s)</span>
            </div>
            <div class="grupo-horas-grid">
    `;

        grupoDetalheAtual.horas.forEach((hora, i) => {
            const st = grupoDetalheAtual.statusHoras[i] || 0;
            const idPend = grupoDetalheAtual.ids[i];
            const isPend = st === 0;
            const stNome = statusMap[st] || 'Pendente';
            const stClass = statusClasse[st] || 'pendente';

            htmlHoras += `
            <label class="grupo-hora-item ${stClass} ${!isPend ? 'disabled' : ''}" title="${stNome}">
                <input type="checkbox" class="chk-hora-grupo" data-id="${idPend}" data-hora="${hora}"
                    ${isPend ? 'checked' : 'disabled'} onchange="atualizarContadorHorasGrupo()">
                <span class="hora-label">${String(hora).padStart(2, '0')}:00</span>
                <span class="badge ${stClass}" style="font-size:9px;">${stNome}</span>
            </label>
        `;
        });

        htmlHoras += `
            </div>
        </div>
    `;

        // Dropdown de metodo de correcao
        let htmlMetodo = `
        <div class="grupo-metodo-section">
            <label class="grupo-metodo-label">Metodo de correcao:</label>
            <select id="selectMetodoGrupo" class="select-metodo-grupo">
                <option value="AUTO">AUTO (melhor score)</option>
                <option value="XGBOOST">XGBoost Rede</option>
                <option value="PCHIP">PCHIP</option>
                <option value="HISTORICO">Hist. + Tendencia</option>
                <option value="TENDENCIA_REDE">Tendencia Rede</option>
                <option value="PROPORCAO">Proporcao Hist.</option>
                <option value="MINIMOS_QUADRADOS">Min. Quadrados</option>
            </select>
        </div>
    `;

        // Area do grafico (sera preenchida via AJAX)
        let htmlGrafico = `
        <div class="grupo-grafico-area" id="grupoGraficoArea">
            <div style="text-align:center;padding:20px;">
                <div class="loading-spinner"></div>
                <p style="margin:8px 0 0;color:#94a3b8;font-size:11px;">Carregando metodos de correcao...</p>
            </div>
        </div>
    `;

        body.innerHTML = htmlInfo + htmlHoras + htmlMetodo + htmlGrafico;

        // Inicializar Select2 no dropdown de metodo
        setTimeout(() => {
            $('#selectMetodoGrupo').select2({
                width: '100%',
                minimumResultsForSearch: Infinity, // sem busca (poucas opcoes)
                dropdownParent: $('#modalDetalheGrupo .modal-box')
            });
        }, 100);

        // Abrir modal
        abrirModal('modalDetalheGrupo');

        // Carregar grafico dos metodos de correcao (usa o endpoint metodoCorrecao.php existente)
        carregarMetodosGrupo(cdPonto, dtRef);
    }


    /**
     * Toggle todas as horas no modal do grupo.
     * So afeta checkboxes habilitados (pendentes).
     *
     * @param {HTMLInputElement} chkAll - Checkbox mestre
     */
    function toggleTodasHorasGrupo(chkAll) {
        document.querySelectorAll('.chk-hora-grupo:not(:disabled)').forEach(chk => {
            chk.checked = chkAll.checked;
        });
        atualizarContadorHorasGrupo();
    }


    /**
     * Atualiza o contador de horas selecionadas no modal.
     */
    function atualizarContadorHorasGrupo() {
        const total = document.querySelectorAll('.chk-hora-grupo:checked').length;
        const countEl = document.getElementById('grupoHorasCount');
        if (countEl) countEl.textContent = total + ' selecionada(s)';
    }


    /**
     * Carrega metodos de correcao para o grafico do grupo.
     * Chama metodoCorrecao.php com o ponto e data.
     *
     * @param {string} cdPonto - Codigo do ponto
     * @param {string} dtRef   - Data de referencia
     */
    function carregarMetodosGrupo(cdPonto, dtRef) {
        // Buscar primeiro ID pendente para obter tipo de medidor
        const primeiroPend = grupoDetalheAtual.ids[0];

        fetch('bd/operacoes/metodoCorrecao.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'calcular_metodos',
                cd_ponto: parseInt(cdPonto),
                dt_referencia: dtRef,
                cd_pendencia: primeiroPend
            })
        })
            .then(r => r.json())
            .then(data => {
                const area = document.getElementById('grupoGraficoArea');
                if (!data || !data.success) {
                    area.innerHTML = `
                    <div style="text-align:center;padding:20px;color:#94a3b8;">
                        <ion-icon name="analytics-outline" style="font-size:24px;"></ion-icon>
                        <p style="margin:8px 0 0;font-size:12px;">${data?.error || 'Sem dados de metodos de correcao'}</p>
                    </div>
                `;
                    return;
                }

                // Salvar no cache
                cacheMetodos[cdPonto + '_' + dtRef] = data;

                // Renderizar grafico
                area.innerHTML = '<canvas id="canvasGrupoMetodos" style="width:100%;height:220px;"></canvas>';
                renderizarGraficoGrupo(data);

                // Mapear dropdown para IDs reais dos metodos retornados
                var selMetodo = document.getElementById('selectMetodoGrupo');
                if (selMetodo) {
                    // Popular dropdown com metodos reais retornados
                    var optsHtml = '<option value="AUTO">AUTO (' + (data.metodo_recomendado || '-') + ')</option>';
                    (data.metodos || []).forEach(function (m) {
                        optsHtml += '<option value="' + m.id + '">' + m.nome + ' (score: ' + parseFloat(m.score_aderencia).toFixed(1) + ')</option>';
                    });
                    selMetodo.innerHTML = optsHtml;

                    // Reiniciar Select2
                    try { $(selMetodo).select2('destroy'); } catch (e) { }
                    $(selMetodo).select2({
                        width: '100%',
                        minimumResultsForSearch: Infinity,
                        dropdownParent: $('#modalDetalheGrupo .modal-box')
                    });

                    // Ao trocar metodo, redesenhar grafico
                    $(selMetodo).off('change').on('change', function () {
                        var val = $(this).val();
                        var cacheKey = grupoDetalheAtual.cdPonto + '_' + grupoDetalheAtual.dtRef;
                        var cachedData = cacheMetodos[cacheKey];
                        if (!cachedData) return;

                        // AUTO = usar metodo_recomendado
                        var metodoId = (val === 'AUTO') ? cachedData.metodo_recomendado : val;
                        renderizarGraficoGrupo(cachedData, metodoId);
                    });
                }
                // console.log('metodoCorrecao response:', JSON.stringify(data, null, 2));
            })
            .catch(err => {
                const area = document.getElementById('grupoGraficoArea');
                area.innerHTML = `<p style="text-align:center;color:#ef4444;font-size:12px;">Erro: ${err.message}</p>`;
            });
    }

    /**
     * Renderiza grafico Chart.js com as curvas do grupo.
     * Usa a estrutura real do metodoCorrecao.php.
     *
     * @param {Object} data   - Resposta do metodoCorrecao.php
     * @param {string} [metodoId] - ID do metodo a usar (default: metodo_recomendado)
     */
    var chartGrupoMetodos = null;

    function renderizarGraficoGrupo(data, metodoId) {
        const canvas = document.getElementById('canvasGrupoMetodos');
        if (!canvas) return;

        if (chartGrupoMetodos) {
            chartGrupoMetodos.destroy();
            chartGrupoMetodos = null;
        }

        // Valores reais: array indexado 0-23
        const reaisArr = data.valores_reais || [];

        // Encontrar metodo selecionado no array de metodos
        const metodoSel = metodoId || data.metodo_recomendado || '';
        const metodosArr = data.metodos || [];
        let metodoObj = metodosArr.find(m => m.id === metodoSel);
        if (!metodoObj && metodosArr.length > 0) metodoObj = metodosArr[0];
        const estimArr = metodoObj ? (metodoObj.valores || []) : [];

        // Horas anomalas
        const horasAnomSet = new Set(data.horas_anomalas || grupoDetalheAtual.horas);

        const labels = [];
        const valoresReais = [];
        const valoresEstimado = [];
        const valoresCorrigido = [];

        for (let h = 0; h < 24; h++) {
            labels.push(String(h).padStart(2, '0') + ':00');

            const real = reaisArr[h] != null ? parseFloat(reaisArr[h]) : null;
            const est = estimArr[h] != null ? parseFloat(estimArr[h]) : null;

            valoresReais.push(real);
            valoresEstimado.push(est);

            // Corrigido: nas horas anomalas usa estimativa, senao valor real
            valoresCorrigido.push(horasAnomSet.has(h) && est != null ? est : real);
        }

        // Score de aderencia no titulo
        const scoreText = metodoObj ? (' — Aderencia: ' + parseFloat(metodoObj.score_aderencia).toFixed(1) + '/10') : '';

        const ctx = canvas.getContext('2d');
        chartGrupoMetodos = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Valor Real',
                        data: valoresReais,
                        borderColor: '#f97316',
                        borderWidth: 2,
                        pointRadius: 3,
                        pointBackgroundColor: '#f97316',
                        tension: 0.3,
                        fill: false,
                        order: 2
                    },
                    {
                        label: 'Estimativa (' + (metodoObj ? metodoObj.nome : '-') + ')' + scoreText,
                        data: valoresEstimado,
                        borderColor: '#22c55e',
                        borderWidth: 2,
                        borderDash: [6, 3],
                        pointRadius: 2,
                        pointBackgroundColor: '#22c55e',
                        tension: 0.3,
                        fill: false,
                        order: 1
                    },
                    {
                        label: 'Valor Corrigido',
                        data: valoresCorrigido,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.08)',
                        borderWidth: 2.5,
                        pointRadius: 3,
                        pointBackgroundColor: '#3b82f6',
                        tension: 0.3,
                        fill: true,
                        order: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', labels: { font: { size: 11 }, usePointStyle: true, padding: 12 } },
                    tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + (ctx.parsed.y != null ? ctx.parsed.y.toFixed(2) : '-') } }
                },
                scales: {
                    x: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 } } },
                    y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 } }, beginAtZero: false }
                }
            },
            plugins: [{
                id: 'horasAnomalasBackground',
                beforeDraw(chart) {
                    const { ctx, chartArea, scales } = chart;
                    if (!chartArea) return;
                    const xScale = scales.x;
                    const barW = xScale.getPixelForValue(1) - xScale.getPixelForValue(0);
                    (data.horas_anomalas || []).forEach(h => {
                        ctx.fillStyle = 'rgba(239, 68, 68, 0.06)';
                        ctx.fillRect(xScale.getPixelForValue(h) - barW / 2, chartArea.top, barW, chartArea.bottom - chartArea.top);
                    });
                }
            }]
        });
    }


    /**
     * OPCAO 1: Aplicar tratamento a TODAS as horas pendentes do grupo.
     * Usa o metodo selecionado no dropdown.
     */
    function aplicarTodasHorasGrupo() {
        const metodo = $('#selectMetodoGrupo').val() || 'AUTO';
        const qtdPend = grupoDetalheAtual.statusHoras.filter(s => s === 0).length;

        if (qtdPend === 0) {
            toast('Nenhuma hora pendente neste grupo', 'info');
            return;
        }

        if (!confirm(`Aplicar metodo ${metodo} em TODAS as ${qtdPend} hora(s) pendentes?`)) return;

        executarAprovacaoGrupo(metodo, []); // vazio = todas
    }


    /**
     * OPCAO 2: Aplicar tratamento SOMENTE as horas selecionadas (checkboxes marcados).
     * Usa o metodo selecionado no dropdown.
     */
    function aplicarHorasSelecionadasGrupo() {
        const metodo = $('#selectMetodoGrupo').val() || 'AUTO';

        // Coletar IDs das horas marcadas
        const idsSel = [];
        document.querySelectorAll('.chk-hora-grupo:checked').forEach(chk => {
            idsSel.push(parseInt(chk.dataset.id));
        });

        if (idsSel.length === 0) {
            toast('Selecione pelo menos uma hora', 'info');
            return;
        }

        if (!confirm(`Aplicar metodo ${metodo} em ${idsSel.length} hora(s) selecionada(s)?`)) return;

        executarAprovacaoGrupo(metodo, idsSel);
    }


    /**
     * Executa a aprovacao do grupo via AJAX.
     * Envia para acao 'aprovar_grupo' no backend.
     *
     * @param {string} metodo   - Metodo de correcao
     * @param {Array}  idsHoras - Array de CD_CHAVE (vazio = todas)
     */
    function executarAprovacaoGrupo(metodo, idsHoras) {
        // Desabilitar botoes durante processamento
        const btnTodas = document.getElementById('btnAplicarTodasGrupo');
        const btnSel = document.getElementById('btnAplicarSelGrupo');
        if (btnTodas) btnTodas.disabled = true;
        if (btnSel) btnSel.disabled = true;

        fetch('bd/operacoes/tratamentoLote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                acao: 'aprovar_grupo',
                cd_ponto: grupoDetalheAtual.cdPonto,
                dt_referencia: grupoDetalheAtual.dtRef,
                metodo: metodo,
                ids: idsHoras
            })
        })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    toast(d.message || 'Tratamento aplicado', 'ok');
                    fecharModal('modalDetalheGrupo');
                    carregarEstatisticas(); // Recarrega stats + tabela
                } else {
                    toast(d.error || 'Erro ao aplicar tratamento', 'err');
                }

                if (btnTodas) btnTodas.disabled = false;
                if (btnSel) btnSel.disabled = false;
            })
            .catch(() => {
                toast('Erro de conexao', 'err');
                if (btnTodas) btnTodas.disabled = false;
                if (btnSel) btnSel.disabled = false;
            });
    }

    /**
 * Troca aba no modal de Regras/Glossario/Metodos.
 * @param {HTMLElement} btn  Botao clicado
 * @param {string}      tab  ID da aba (deteccao|glossario|metodos)
 */
    function trocarAbaRegras(btn, tab) {
        // Desativar todas as abas
        document.querySelectorAll('.regras-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.regras-tab-pane').forEach(p => { p.classList.remove('active'); p.style.display = 'none'; });
        // Ativar a aba clicada
        btn.classList.add('active');
        const pane = document.getElementById('pane' + tab.charAt(0).toUpperCase() + tab.slice(1));
        if (pane) { pane.classList.add('active'); pane.style.display = 'block'; }
    }
</script>

<?php include_once 'includes/footer.inc.php'; ?>
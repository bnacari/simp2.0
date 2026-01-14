<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Dashboard / Página Inicial
 */

include_once 'includes/header.inc.php';
include_once 'includes/menu.inc.php';
include_once 'bd/conexao.php';

// ========== DEBUG DE PERMISSÕES ==========
$debugPermissoes = [
    'usuario' => [
        'cd_usuario' => $_SESSION['cd_usuario'] ?? null,
        'nome' => $_SESSION['nome'] ?? null,
        'login' => $_SESSION['login'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'matricula' => $_SESSION['matricula'] ?? null,
        'cd_grupo' => $_SESSION['cd_grupo'] ?? null,
        'grupo' => $_SESSION['grupo'] ?? null,
    ],
    'constantes_acesso' => [
        'ACESSO_LEITURA' => ACESSO_LEITURA,
        'ACESSO_ESCRITA' => ACESSO_ESCRITA,
    ],
    'permissoes_por_nome' => $_SESSION['permissoes_nome'] ?? [],
    'permissoes_por_codigo' => $_SESSION['permissoes'] ?? [],
];

// Testa permissões para PONTO DE MEDIÇÃO
$testePermissoes = [
    "temPermissaoTela('CADASTRO DE PONTO')" => temPermissaoTela('CADASTRO DE PONTO'),
    "temPermissaoTela('CADASTRO DE PONTO', ACESSO_LEITURA)" => temPermissaoTela('CADASTRO DE PONTO', ACESSO_LEITURA),
    "temPermissaoTela('CADASTRO DE PONTO', ACESSO_ESCRITA)" => temPermissaoTela('CADASTRO DE PONTO', ACESSO_ESCRITA),
    "podeVisualizarTela('CADASTRO DE PONTO')" => podeVisualizarTela('CADASTRO DE PONTO'),
    "podeEditarTela('CADASTRO DE PONTO')" => podeEditarTela('CADASTRO DE PONTO'),
    "getNivelAcessoTela('CADASTRO DE PONTO')" => getNivelAcessoTela('CADASTRO DE PONTO'),
];
// ========== FIM DEBUG ==========
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-info">
                <div class="page-header-icon">
                    <ion-icon name="speedometer-outline"></ion-icon>
                </div>
                <div>
                    <h1>Dashboard</h1>
                    <p class="page-header-subtitle">Bem-vindo ao SIMP - Sistema Integrado de Macromedição e Pitometria</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== DEBUG DE PERMISSÕES ========== -->
    <div style="background: #1e293b; color: #e2e8f0; padding: 20px; margin: 20px 0; border-radius: 12px; font-family: 'Fira Code', 'Consolas', monospace; font-size: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
        <h2 style="color: #f59e0b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <ion-icon name="bug-outline" style="font-size: 24px;"></ion-icon>
            DEBUG DE PERMISSÕES (Nova Lógica por Nome)
        </h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px;">
            
            <!-- Usuário Logado -->
            <div style="background: #334155; padding: 15px; border-radius: 8px; border-left: 4px solid #3b82f6;">
                <h3 style="color: #60a5fa; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <ion-icon name="person-outline"></ion-icon>
                    Usuário Logado
                </h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <?php foreach ($debugPermissoes['usuario'] as $key => $value): ?>
                    <tr>
                        <td style="padding: 4px 8px; color: #94a3b8;"><?= $key ?></td>
                        <td style="padding: 4px 8px; color: #e2e8f0; font-weight: bold;"><?= $value ?? '<span style="color:#ef4444">NULL</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            
            <!-- Constantes de Acesso -->
            <div style="background: #334155; padding: 15px; border-radius: 8px; border-left: 4px solid #ec4899;">
                <h3 style="color: #f472b6; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <ion-icon name="key-outline"></ion-icon>
                    Constantes de Níveis de Acesso (auth.php)
                </h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <?php foreach ($debugPermissoes['constantes_acesso'] as $key => $value): ?>
                    <tr>
                        <td style="padding: 4px 8px; color: #94a3b8;"><?= $key ?></td>
                        <td style="padding: 4px 8px; color: #22c55e; font-weight: bold;"><?= $value ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            
            <!-- Permissões por Nome (NOVA!) -->
            <div style="background: #334155; padding: 15px; border-radius: 8px; border-left: 4px solid #14b8a6; grid-column: 1 / -1;">
                <h3 style="color: #2dd4bf; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <ion-icon name="shield-checkmark-outline"></ion-icon>
                    $_SESSION['permissoes_nome'] (carregado no login - NOVA LÓGICA)
                </h3>
                <?php if (!empty($debugPermissoes['permissoes_por_nome'])): ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="background: #475569;">
                        <th style="padding: 8px; text-align: left; color: #e2e8f0;">DS_NOME (Funcionalidade)</th>
                        <th style="padding: 8px; text-align: left; color: #e2e8f0;">CD_FUNCIONALIDADE</th>
                        <th style="padding: 8px; text-align: left; color: #e2e8f0;">ID_TIPO_ACESSO</th>
                        <th style="padding: 8px; text-align: left; color: #e2e8f0;">Nível</th>
                    </tr>
                    <?php foreach ($debugPermissoes['permissoes_por_nome'] as $nomeFuncionalidade => $dados): ?>
                    <tr style="border-bottom: 1px solid #475569;">
                        <td style="padding: 8px; color: #fbbf24; font-weight: bold;"><?= htmlspecialchars($nomeFuncionalidade) ?></td>
                        <td style="padding: 8px; color: #94a3b8;"><?= $dados['cd'] ?></td>
                        <td style="padding: 8px; color: #22c55e; font-weight: bold;"><?= $dados['acesso'] ?></td>
                        <td style="padding: 8px; color: #60a5fa;">
                            <?php
                            if ($dados['acesso'] >= 2) echo '✏️ ESCRITA (pode editar)';
                            elseif ($dados['acesso'] >= 1) echo '👁️ LEITURA (só visualiza)';
                            else echo '❌ NENHUM';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php else: ?>
                <div style="color: #ef4444; padding: 10px; background: #450a0a; border-radius: 4px;">
                    ⚠️ ARRAY VAZIO - Nenhuma permissão foi carregada no login!
                    <br><br>
                    <small>Verifique a consulta em bd/ldap.php que busca as funcionalidades do grupo.</small>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Teste de Permissões -->
            <div style="background: #334155; padding: 15px; border-radius: 8px; border-left: 4px solid #f59e0b; grid-column: 1 / -1;">
                <h3 style="color: #fbbf24; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <ion-icon name="checkmark-circle-outline"></ion-icon>
                    Teste de Funções de Permissão (buscando por 'PONTO')
                </h3>
                <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                    <?php foreach ($testePermissoes as $funcao => $resultado): ?>
                    <div style="background: <?= $resultado ? '#14532d' : '#7f1d1d' ?>; padding: 10px 15px; border-radius: 6px; display: flex; align-items: center; gap: 8px;">
                        <ion-icon name="<?= $resultado ? 'checkmark-circle' : 'close-circle' ?>" style="color: <?= $resultado ? '#22c55e' : '#ef4444' ?>; font-size: 18px;"></ion-icon>
                        <span style="color: #e2e8f0;"><?= htmlspecialchars($funcao) ?></span>
                        <span style="color: <?= $resultado ? '#22c55e' : '#ef4444' ?>; font-weight: bold;">
                            <?= is_bool($resultado) ? ($resultado ? 'TRUE' : 'FALSE') : ($resultado ?? 'NULL') ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
        </div>
        
        <!-- Explicação -->
        <div style="margin-top: 20px; padding: 15px; background: #475569; border-radius: 8px;">
            <h4 style="color: #fbbf24; margin-bottom: 10px;">💡 Como funciona a NOVA verificação de permissão:</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 15px;">
                <div>
                    <p style="margin: 0 0 10px 0; color: #e2e8f0;"><strong>1. No Login (ldap.php):</strong></p>
                    <code style="display: block; background: #1e293b; padding: 10px; border-radius: 4px; color: #60a5fa; font-size: 11px;">
                        $_SESSION['permissoes_nome']['PONTO DE MEDIÇÃO'] = ['cd' => 11, 'acesso' => 2]
                    </code>
                </div>
                <div>
                    <p style="margin: 0 0 10px 0; color: #e2e8f0;"><strong>2. Na Página (auth.php):</strong></p>
                    <code style="display: block; background: #1e293b; padding: 10px; border-radius: 4px; color: #60a5fa; font-size: 11px;">
                        exigePermissaoTela('PONTO', ACESSO_ESCRITA);<br>
                        // Busca funcionalidade que contenha 'PONTO' no nome
                    </code>
                </div>
            </div>
            <div style="margin-top: 15px; padding: 10px; background: #14532d; border-radius: 4px; color: #bbf7d0;">
                <strong>✅ Vantagem:</strong> Não precisa mais de constantes fixas (FUNC_PONTO_MEDICAO = 2). 
                O sistema busca automaticamente pelo nome na tabela FUNCIONALIDADE.
            </div>
        </div>
        
        <!-- Sessão Completa (colapsável) -->
        <details style="margin-top: 20px;">
            <summary style="cursor: pointer; color: #94a3b8; padding: 10px; background: #334155; border-radius: 4px;">
                📋 Ver $_SESSION completa (clique para expandir)
            </summary>
            <pre style="margin-top: 10px; background: #0f172a; padding: 15px; border-radius: 4px; overflow-x: auto; color: #94a3b8; font-size: 11px;"><?= htmlspecialchars(print_r($_SESSION, true)) ?></pre>
        </details>
    </div>
    <!-- ========== FIM DEBUG ========== -->


</div>

<style>
    a > div:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    }
</style>

<?php include_once 'includes/footer.inc.php'; ?>
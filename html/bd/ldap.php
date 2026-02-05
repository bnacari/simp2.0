<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Validação de Login via Active Directory (LDAP)
 * 
 * VERSÃO COM REGISTRO DE LOG DE ATIVIDADES
 */

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

session_start();

// ============================================
// CONEXÃO COM BANCO DE DADOS
// ============================================
include_once 'conexao.php';
include_once 'logHelper.php';  // <<< HELPER DE LOG

$pdo = $pdoSIMP;

// ============================================
// 1. CAPTURA E TRATAMENTO DO LOGIN
// ============================================
$login = filter_input(INPUT_POST, 'login', FILTER_SANITIZE_SPECIAL_CHARS);
$senha = trim($_POST['senha'] ?? '');

if (empty($login) || empty($senha)) {
    $_SESSION['sucesso'] = 0;
    $_SESSION['msg'] = 'Preencha usuário e senha.';
    header('Location: ../index.php');
    exit();
}

// Se o usuário digitou o email completo, pega apenas a parte antes do @
if (strpos($login, '@') !== false) {
    $login = explode('@', $login)[0];
}

// Converte para minúsculo para padronização
$login = strtolower(trim($login));

// ============================================
// 2. VERIFICA SE O USUÁRIO EXISTE NO SIMP
// ============================================
try {
    $stmt = $pdo->prepare("
        SELECT 
            U.CD_USUARIO,
            U.CD_GRUPO_USUARIO,
            U.DS_MATRICULA,
            U.DS_LOGIN,
            U.DS_NOME,
            U.DS_EMAIL,
            U.OP_BLOQUEADO,
            G.DS_NOME AS DS_GRUPO
        FROM SIMP.dbo.USUARIO U
        INNER JOIN SIMP.dbo.GRUPO_USUARIO G ON U.CD_GRUPO_USUARIO = G.CD_GRUPO_USUARIO
        WHERE U.DS_LOGIN = :login
    ");
    $stmt->execute([':login' => $login]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['sucesso'] = 0;
    $_SESSION['msg'] = 'Erro ao consultar usuário. Tente novamente.';
    header('Location: ../index.php');
    exit();
}

// Usuário não cadastrado no sistema
if (!$usuario) {
    // <<< REGISTRAR LOG DE FALHA >>>
    registrarLogLogin(null, $login, 'LDAP', false, 'Usuário não cadastrado no sistema');
    
    $_SESSION['sucesso'] = 0;
    $_SESSION['msg'] = 'Usuário não cadastrado no sistema. Entre em contato com o administrador.';
    header('Location: ../index.php');
    exit();
}

// Usuário bloqueado
if ($usuario['OP_BLOQUEADO'] == 1 || strtoupper($usuario['OP_BLOQUEADO']) == 'S') {
    // <<< REGISTRAR LOG DE FALHA >>>
    registrarLogLogin($usuario['CD_USUARIO'], $login, 'LDAP', false, 'Usuário bloqueado');
    
    $_SESSION['sucesso'] = 0;
    $_SESSION['msg'] = 'Usuário bloqueado. Entre em contato com o administrador.';
    header('Location: ../index.php');
    exit();
}

// ============================================
// 3. AUTENTICAÇÃO NO ACTIVE DIRECTORY (LDAP)
// ============================================
$ldap_server = 'cesan.com.br';
$ldap_porta = '389';
$dominio = '@cesan.com.br';

$ldapcon = @ldap_connect($ldap_server, $ldap_porta);

if (!$ldapcon) {
    $_SESSION['sucesso'] = 0;
    $_SESSION['msg'] = 'Erro ao conectar com o servidor de autenticação.';
    header('Location: ../index.php');
    exit();
}

// Configurações do LDAP
ldap_set_option($ldapcon, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldapcon, LDAP_OPT_REFERRALS, 0);

// Tenta autenticar no AD
$bind = @ldap_bind($ldapcon, $login . $dominio, $senha);

if (!$bind) {
    // <<< REGISTRAR LOG DE FALHA >>>
    registrarLogLogin($usuario['CD_USUARIO'], $login, 'LDAP', false, 'Senha inválida');
    
    $_SESSION['sucesso'] = 0;
    $_SESSION['msg'] = 'Usuário ou senha inválidos.';
    header('Location: ../index.php');
    exit();
}

// ============================================
// 4. BUSCA FUNCIONALIDADES/PERMISSÕES DO USUÁRIO
// ============================================
try {
    $stmtFunc = $pdo->prepare("
        SELECT 
            F.CD_FUNCIONALIDADE,
            F.DS_NOME AS DS_FUNCIONALIDADE,
            GF.ID_TIPO_ACESSO
        FROM SIMP.dbo.GRUPO_USUARIO_X_FUNCIONALIDADE GF
        INNER JOIN SIMP.dbo.FUNCIONALIDADE F ON GF.CD_FUNCIONALIDADE = F.CD_FUNCIONALIDADE
        WHERE GF.CD_GRUPO_USUARIO = :cdGrupo
    ");
    $stmtFunc->execute([':cdGrupo' => $usuario['CD_GRUPO_USUARIO']]);
    $funcionalidades = $stmtFunc->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $funcionalidades = [];
}

// Monta arrays de permissões
$permissoes = [];
$permissoesPorNome = [];
foreach ($funcionalidades as $func) {
    $permissoes[$func['CD_FUNCIONALIDADE']] = $func['ID_TIPO_ACESSO'];
    $permissoesPorNome[strtoupper($func['DS_FUNCIONALIDADE'])] = [
        'cd' => $func['CD_FUNCIONALIDADE'],
        'acesso' => $func['ID_TIPO_ACESSO']
    ];
}

// ============================================
// 5. CONFIGURA SESSÃO DO USUÁRIO
// ============================================
$_SESSION['sucesso'] = 1;
$_SESSION['msg'] = '';

// Dados do usuário
$_SESSION['cd_usuario'] = $usuario['CD_USUARIO'];
$_SESSION['login'] = $usuario['DS_LOGIN'];
$_SESSION['nome'] = $usuario['DS_NOME'];
$_SESSION['email'] = $usuario['DS_EMAIL'];
$_SESSION['matricula'] = $usuario['DS_MATRICULA'];

// Grupo e permissões
$_SESSION['cd_grupo'] = $usuario['CD_GRUPO_USUARIO'];
$_SESSION['grupo'] = $usuario['DS_GRUPO'];
$_SESSION['permissoes'] = $permissoes;
$_SESSION['permissoes_nome'] = $permissoesPorNome;

// ============================================
// 5.1 REGISTRAR LOG DE LOGIN BEM-SUCEDIDO
// ============================================
registrarLogLogin($usuario['CD_USUARIO'], $usuario['DS_LOGIN'], 'LDAP', true);

// Fecha conexão LDAP
@ldap_close($ldapcon);

// ============================================
// 6. REDIRECIONA PARA PÁGINA INICIAL
// ============================================
header('Location: ../index.php');
exit();
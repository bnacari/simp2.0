<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Funções de Autenticação e Permissões
 * 
 * IMPORTANTE: As permissões são verificadas pelo NOME da funcionalidade
 * cadastrada na tabela FUNCIONALIDADE (campo DS_NOME).
 * 
 * Exemplo: temPermissaoTela('PONTO') vai procurar funcionalidades
 * que contenham "PONTO" no nome (ex: "PONTO DE MEDIÇÃO").
 */

// ============================================
// CONSTANTES DE TIPOS DE ACESSO
// ============================================
// Correspondem ao campo ID_TIPO_ACESSO da tabela GRUPO_USUARIO_X_FUNCIONALIDADE
if (!defined('ACESSO_LEITURA')) define('ACESSO_LEITURA', 1);
if (!defined('ACESSO_ESCRITA')) define('ACESSO_ESCRITA', 2);

// ============================================
// CONFIGURAÇÕES DE SESSÃO
// ============================================
if (!defined('TEMPO_SESSAO')) define('TEMPO_SESSAO', 3600); // 1 hora em segundos

/**
 * Inicia sessão de forma segura
 */
function iniciarSessaoSegura()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Verifica se o usuário está logado
 * Redireciona para login se não estiver
 */
function verificaLogin()
{
    iniciarSessaoSegura();

    if (!isset($_SESSION['sucesso']) || $_SESSION['sucesso'] != 1) {
        $_SESSION['msg'] = 'Faça login para acessar o sistema.';
        $_SESSION['msg_tipo'] = 'alerta';
        header('Location: login.php');
        exit();
    }
}

// ============================================
// FUNÇÕES DE PERMISSÃO POR NOME DA TELA
// ============================================

/**
 * Remove acentos de uma string para comparação
 * Usa múltiplas abordagens para lidar com diferentes encodings
 * @param string $string
 * @return string
 */
function removerAcentos($string)
{
    // Tenta converter de diferentes encodings para ASCII
    if (function_exists('iconv')) {
        // Primeiro tenta detectar e converter para UTF-8
        $encodings = ['UTF-8', 'ISO-8859-1', 'Windows-1252'];
        foreach ($encodings as $encoding) {
            $converted = @iconv($encoding, 'ASCII//TRANSLIT//IGNORE', $string);
            if ($converted !== false && !empty($converted)) {
                return $converted;
            }
        }
    }

    // Fallback: substituição manual incluindo caracteres com problema de encoding
    $busca = [
        // UTF-8 padrão
        'á', 'à', 'ã', 'â', 'ä', 'Á', 'À', 'Ã', 'Â', 'Ä',
        'é', 'è', 'ê', 'ë', 'É', 'È', 'Ê', 'Ë',
        'í', 'ì', 'î', 'ï', 'Í', 'Ì', 'Î', 'Ï',
        'ó', 'ò', 'õ', 'ô', 'ö', 'Ó', 'Ò', 'Õ', 'Ô', 'Ö',
        'ú', 'ù', 'û', 'ü', 'Ú', 'Ù', 'Û', 'Ü',
        'ç', 'Ç', 'ñ', 'Ñ'
    ];
    $substitui = [
        'a', 'a', 'a', 'a', 'a', 'A', 'A', 'A', 'A', 'A',
        'e', 'e', 'e', 'e', 'E', 'E', 'E', 'E',
        'i', 'i', 'i', 'i', 'I', 'I', 'I', 'I',
        'o', 'o', 'o', 'o', 'o', 'O', 'O', 'O', 'O', 'O',
        'u', 'u', 'u', 'u', 'U', 'U', 'U', 'U',
        'c', 'C', 'n', 'N'
    ];

    $resultado = str_replace($busca, $substitui, $string);

    // Remove qualquer caractere não-ASCII restante
    $resultado = preg_replace('/[^\x20-\x7E]/', '', $resultado);

    return $resultado;
}

/**
 * Busca permissão pelo nome da funcionalidade (busca parcial, ignora acentos)
 * 
 * @param string $nomeTela - Termo a buscar no nome da funcionalidade (ex: 'PONTO', 'LEITURA')
 * @return array|null - ['cd' => código, 'acesso' => nível] ou null se não encontrar
 */
function buscarPermissaoPorNome($nomeTela)
{
    if (!isset($_SESSION['permissoes_nome']) || empty($_SESSION['permissoes_nome'])) {
        return null;
    }

    // Normaliza o termo de busca (maiúsculo, sem acentos)
    $nomeTelaNormalizado = strtoupper(removerAcentos(trim($nomeTela)));

    // Busca exata primeiro (com normalização)
    foreach ($_SESSION['permissoes_nome'] as $nomeFuncionalidade => $dados) {
        $nomeNormalizado = strtoupper(removerAcentos($nomeFuncionalidade));
        if ($nomeNormalizado === $nomeTelaNormalizado) {
            return $dados;
        }
    }

    // Busca parcial (contém o termo, com normalização)
    foreach ($_SESSION['permissoes_nome'] as $nomeFuncionalidade => $dados) {
        $nomeNormalizado = strtoupper(removerAcentos($nomeFuncionalidade));
        if (strpos($nomeNormalizado, $nomeTelaNormalizado) !== false) {
            return $dados;
        }
    }

    return null;
}

/**
 * Verifica se o usuário tem permissão para uma tela (pelo nome)
 * 
 * NOTA: Se o usuário não tem NENHUMA permissão cadastrada (permissoes_nome vazio),
 * retorna TRUE para permitir acesso durante a fase de implantação.
 * 
 * @param string $nomeTela - Nome ou parte do nome da funcionalidade (ex: 'PONTO', 'PONTO DE MEDIÇÃO')
 * @param int|null $tipoAcessoMinimo - Tipo de acesso mínimo (ACESSO_LEITURA ou ACESSO_ESCRITA)
 * @return bool
 */
function temPermissaoTela($nomeTela, $tipoAcessoMinimo = null)
{
    // Se não há permissões cadastradas para o usuário, permite acesso (modo implantação)
    if (!isset($_SESSION['permissoes_nome']) || empty($_SESSION['permissoes_nome'])) {
        return true;
    }
    
    $permissao = buscarPermissaoPorNome($nomeTela);

    if ($permissao === null) {
        return false;
    }

    // Se não especificou tipo mínimo, apenas verifica se tem acesso
    if ($tipoAcessoMinimo === null) {
        return true;
    }

    // Verifica se o nível de acesso é suficiente
    return $permissao['acesso'] >= $tipoAcessoMinimo;
}

/**
 * Verifica se o usuário pode editar na tela (escrita)
 * 
 * NOTA: Se o usuário não tem NENHUMA permissão cadastrada, retorna TRUE (modo implantação)
 * 
 * @param string $nomeTela
 * @return bool
 */
function podeEditarTela($nomeTela)
{
    // Se não há permissões cadastradas para o usuário, permite edição (modo implantação)
    if (!isset($_SESSION['permissoes_nome']) || empty($_SESSION['permissoes_nome'])) {
        return true;
    }
    
    return temPermissaoTela($nomeTela, ACESSO_ESCRITA);
}

/**
 * Exige permissão para acessar a tela, redireciona se não tiver
 * 
 * @param string $nomeTela - Nome ou parte do nome da funcionalidade
 * @param int|null $tipoAcessoMinimo - Tipo de acesso mínimo necessário
 * @param string $paginaRedirect - Página para redirecionar (padrão: index.php)
 */
function exigePermissaoTela($nomeTela, $tipoAcessoMinimo = null, $paginaRedirect = 'index.php')
{
    if (!temPermissaoTela($nomeTela, $tipoAcessoMinimo)) {
        $_SESSION['msg'] = 'Você não tem permissão para acessar esta funcionalidade.';
        $_SESSION['msg_tipo'] = 'erro';
        header("Location: $paginaRedirect");
        exit();
    }
}

/**
 * Verifica se o usuário pode visualizar a tela (leitura)
 * @param string $nomeTela
 * @return bool
 */
function podeVisualizarTela($nomeTela)
{
    return temPermissaoTela($nomeTela, ACESSO_LEITURA);
}

/**
 * Retorna o nível de acesso do usuário para uma tela
 * @param string $nomeTela
 * @return int|null - Nível de acesso ou null se não tiver permissão
 */
function getNivelAcessoTela($nomeTela)
{
    // Se não há permissões cadastradas para o usuário, retorna acesso total (modo implantação)
    if (!isset($_SESSION['permissoes_nome']) || empty($_SESSION['permissoes_nome'])) {
        return ACESSO_ESCRITA;
    }
    
    $permissao = buscarPermissaoPorNome($nomeTela);
    return $permissao ? $permissao['acesso'] : null;
}

// ============================================
// FUNÇÕES DE DADOS DO USUÁRIO
// ============================================

/**
 * Retorna o ID do usuário logado
 * @return int|null
 */
function getIdUsuario()
{
    return $_SESSION['cd_usuario'] ?? null;
}

/**
 * Retorna o nome do usuário logado
 * @return string
 */
function getNomeUsuario()
{
    return $_SESSION['nome'] ?? 'Usuário';
}

/**
 * Retorna as iniciais do nome do usuário (para avatar)
 * @return string
 */
function getIniciaisUsuario()
{
    $nome = $_SESSION['nome'] ?? 'US';
    $partes = explode(' ', $nome);

    if (count($partes) >= 2) {
        return strtoupper(substr($partes[0], 0, 1) . substr(end($partes), 0, 1));
    }

    return strtoupper(substr($nome, 0, 2));
}

/**
 * Retorna o login do usuário
 * @return string
 */
function getLoginUsuario()
{
    return $_SESSION['login'] ?? '';
}

/**
 * Retorna o email do usuário
 * @return string
 */
function getEmailUsuario()
{
    return $_SESSION['email'] ?? '';
}

/**
 * Retorna o código do grupo do usuário
 * @return int|null
 */
function getCdGrupoUsuario()
{
    return $_SESSION['cd_grupo'] ?? null;
}

/**
 * Retorna o nome do grupo do usuário
 * @return string
 */
function getGrupoUsuario()
{
    return $_SESSION['grupo'] ?? '';
}

/**
 * Retorna a matrícula do usuário
 * @return string
 */
function getMatriculaUsuario()
{
    return $_SESSION['matricula'] ?? '';
}

/**
 * Retorna todas as permissões do usuário (por código)
 * @return array
 */
function getPermissoesUsuario()
{
    return $_SESSION['permissoes'] ?? [];
}

/**
 * Retorna todas as permissões do usuário (por nome)
 * @return array
 */
function getPermissoesPorNome()
{
    return $_SESSION['permissoes_nome'] ?? [];
}

// ============================================
// FUNÇÕES DE SESSÃO
// ============================================

/**
 * Encerra a sessão do usuário (logout)
 */
function logout()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header('Location: login.php');
    exit();
}

/**
 * Exibe mensagem de sessão (flash message) e limpa
 * @return array|null ['msg' => string, 'tipo' => string]
 */
function getMensagemSessao()
{
    if (isset($_SESSION['msg']) && !empty($_SESSION['msg'])) {
        $msg = [
            'msg' => $_SESSION['msg'],
            'tipo' => $_SESSION['msg_tipo'] ?? 'info'
        ];

        unset($_SESSION['msg']);
        unset($_SESSION['msg_tipo']);

        return $msg;
    }

    return null;
}

/**
 * Define uma mensagem de sessão (flash message)
 * @param string $mensagem
 * @param string $tipo - 'sucesso', 'erro', 'alerta', 'info'
 */
function setMensagemSessao($mensagem, $tipo = 'info')
{
    $_SESSION['msg'] = $mensagem;
    $_SESSION['msg_tipo'] = $tipo;
}

// ============================================
// FUNÇÕES DE SEGURANÇA
// ============================================

/**
 * Gera token CSRF para formulários
 * @return string
 */
function gerarTokenCSRF()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida token CSRF
 * @param string $token
 * @return bool
 */
function validarTokenCSRF($token)
{
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verifica token CSRF e aborta se inválido
 */
function verificarCSRF()
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (!validarTokenCSRF($token)) {
        http_response_code(403);
        if (isAjaxRequest()) {
            echo json_encode(['success' => false, 'message' => 'Token de segurança inválido']);
        } else {
            setMensagemSessao('Token de segurança inválido. Recarregue a página.', 'erro');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
        }
        exit();
    }
}

/**
 * Verifica se é uma requisição AJAX
 * @return bool
 */
function isAjaxRequest()
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}
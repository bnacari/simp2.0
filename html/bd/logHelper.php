<?php
/**
 * SIMP - Sistema Integrado de Macromedição e Pitometria
 * Helper para Registro de Log de Atividades
 * 
 * COMO USAR:
 * -----------
 * 1. Incluir no início do endpoint:
 *    require_once 'bd/logHelper.php';       (se estiver na raiz)
 *    require_once '../logHelper.php';       (se estiver em bd/*)
 * 
 * 2. Registrar log de sucesso:
 *    registrarLog('Ponto de Medição', 'INSERT', 'Cadastrou ponto: ' . $nome, ['cd_ponto' => $id]);
 * 
 * 3. Registrar log de erro:
 *    registrarLogErro('Ponto de Medição', 'INSERT', $e->getMessage(), ['dados' => $_POST]);
 */

// Tipos de Log
if (!defined('LOG_INFO'))  define('LOG_INFO', 1);      // Informação (operações normais)
if (!defined('LOG_AVISO')) define('LOG_AVISO', 2);     // Aviso (situações que merecem atenção)
if (!defined('LOG_ERRO'))  define('LOG_ERRO', 3);      // Erro (falhas de operação)
if (!defined('LOG_DEBUG')) define('LOG_DEBUG', 4);     // Debug (para desenvolvimento)

// Versão do sistema (ajustar conforme necessário)
if (!defined('SIMP_VERSAO')) define('SIMP_VERSAO', '2.0.0');

/**
 * Obtém a conexão PDO com o banco SIMP
 * Tenta usar a global $pdoSIMP ou criar uma nova conexão
 * 
 * @return PDO|null
 */
function obterConexaoLog() {
    global $pdoSIMP;
    
    // Se já existe conexão global, usar ela
    if (isset($pdoSIMP) && $pdoSIMP instanceof PDO) {
        // Garantir que o PDO está em modo de exceção
        $pdoSIMP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdoSIMP;
    }
    
    // Tentar incluir conexão de diferentes caminhos possíveis
    $possiveisCaminhos = [
        __DIR__ . '/conexao.php',              // Se logHelper está em bd/
        __DIR__ . '/../bd/conexao.php',        // Se logHelper está em includes/ ou raiz
        dirname(__DIR__) . '/bd/conexao.php',  // Um nível acima + bd/
    ];
    
    foreach ($possiveisCaminhos as $caminho) {
        if (file_exists($caminho)) {
            try {
                include_once $caminho;
                if (isset($pdoSIMP) && $pdoSIMP instanceof PDO) {
                    // Garantir que o PDO está em modo de exceção
                    $pdoSIMP->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    return $pdoSIMP;
                }
            } catch (Exception $e) {
                error_log("SIMP LOG: Erro ao incluir conexão de $caminho: " . $e->getMessage());
            }
        }
    }
    
    return null;
}

/**
 * Registra um log de atividade no banco de dados
 * 
 * @param string $funcionalidade Nome da funcionalidade (deve corresponder ao cadastrado em FUNCIONALIDADE)
 * @param string $acao Ação realizada (INSERT, UPDATE, DELETE, LOGIN, LOGOUT, CONSULTA, etc.)
 * @param string $descricao Descrição detalhada da ação
 * @param array $dadosAdicionais Dados adicionais para incluir na descrição (opcional)
 * @param int $tipoLog Tipo do log (LOG_INFO, LOG_AVISO, LOG_ERRO, LOG_DEBUG)
 * @param int|null $cdUnidade Código da unidade relacionada (opcional)
 * @return bool Retorna true se registrou com sucesso
 */
function registrarLog($funcionalidade, $acao, $descricao, $dadosAdicionais = [], $tipoLog = LOG_INFO, $cdUnidade = null) {
    try {
        // Obter conexão
        $pdo = obterConexaoLog();
        if (!$pdo) {
            error_log("SIMP LOG: Não foi possível obter conexão com o banco");
            return false;
        }
        
        // Buscar código da funcionalidade (se informada)
        $cdFuncionalidade = null;
        if (!empty($funcionalidade)) {
            try {
                $sqlFunc = "SELECT CD_FUNCIONALIDADE FROM SIMP.dbo.FUNCIONALIDADE WHERE DS_NOME LIKE :nome";
                $stmtFunc = $pdo->prepare($sqlFunc);
                $stmtFunc->execute([':nome' => '%' . $funcionalidade . '%']);
                $rowFunc = $stmtFunc->fetch(PDO::FETCH_ASSOC);
                if ($rowFunc) {
                    $cdFuncionalidade = (int)$rowFunc['CD_FUNCIONALIDADE'];
                }
            } catch (Exception $e) {
                // Ignorar erro ao buscar funcionalidade - não é crítico
            }
        }
        
        // Obter código do usuário logado
        $cdUsuario = null;
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (isset($_SESSION['cd_usuario']) && !empty($_SESSION['cd_usuario'])) {
            $cdUsuario = (int)$_SESSION['cd_usuario'];
        }
        
        // Montar nome do log
        $nomeLog = strtoupper(substr($acao, 0, 100)); // Limitar a 100 caracteres
        
        // Montar descrição completa
        $descricaoCompleta = $descricao;
        if (!empty($dadosAdicionais)) {
            $descricaoCompleta .= "\n\n--- Dados Adicionais ---\n";
            $descricaoCompleta .= json_encode($dadosAdicionais, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        
        // Obter nome do servidor
        $servidor = $_SERVER['SERVER_NAME'] ?? gethostname() ?? 'DESCONHECIDO';
        $servidor = substr($servidor, 0, 50); // Limitar a 50 caracteres
        
        // Tratar CD_UNIDADE
        if ($cdUnidade !== null) {
            $cdUnidade = (int)$cdUnidade;
        }
        
        // Inserir log - usando bindValue para melhor controle de tipos
        $sql = "INSERT INTO SIMP.dbo.LOG 
                (CD_USUARIO, CD_FUNCIONALIDADE, CD_UNIDADE, DT_LOG, TP_LOG, NM_LOG, DS_LOG, DS_VERSAO, NM_SERVIDOR) 
                VALUES 
                (:cdUsuario, :cdFuncionalidade, :cdUnidade, GETDATE(), :tipoLog, :nomeLog, :descricao, :versao, :servidor)";
        
        $stmt = $pdo->prepare($sql);
        
        // Bind com tipos explícitos
        if ($cdUsuario === null) {
            $stmt->bindValue(':cdUsuario', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':cdUsuario', $cdUsuario, PDO::PARAM_INT);
        }
        
        if ($cdFuncionalidade === null) {
            $stmt->bindValue(':cdFuncionalidade', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':cdFuncionalidade', $cdFuncionalidade, PDO::PARAM_INT);
        }
        
        if ($cdUnidade === null) {
            $stmt->bindValue(':cdUnidade', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':cdUnidade', $cdUnidade, PDO::PARAM_INT);
        }
        
        $stmt->bindValue(':tipoLog', (int)$tipoLog, PDO::PARAM_INT);
        $stmt->bindValue(':nomeLog', $nomeLog, PDO::PARAM_STR);
        $stmt->bindValue(':descricao', $descricaoCompleta, PDO::PARAM_STR);
        $stmt->bindValue(':versao', SIMP_VERSAO, PDO::PARAM_STR);
        $stmt->bindValue(':servidor', $servidor, PDO::PARAM_STR);
        
        $result = $stmt->execute();
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            error_log("SIMP LOG: Erro ao inserir - " . print_r($errorInfo, true));
            return false;
        }
        
        return true;
        
    } catch (PDOException $e) {
        error_log("SIMP LOG PDO ERROR: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        // Em caso de erro no próprio log, apenas registra em arquivo (não interrompe a aplicação)
        error_log("SIMP LOG ERROR: " . $e->getMessage());
        return false;
    }
}

/**
 * Registra um log de erro
 * 
 * @param string $funcionalidade Nome da funcionalidade
 * @param string $acao Ação que falhou
 * @param string $mensagemErro Mensagem de erro
 * @param array $contexto Contexto adicional (dados que estavam sendo processados)
 * @param int|null $cdUnidade Código da unidade relacionada (opcional)
 * @return bool
 */
function registrarLogErro($funcionalidade, $acao, $mensagemErro, $contexto = [], $cdUnidade = null) {
    $descricao = "ERRO ao executar $acao: $mensagemErro";
    return registrarLog($funcionalidade, $acao . '_ERRO', $descricao, $contexto, LOG_ERRO, $cdUnidade);
}

/**
 * Registra log de INSERT
 * 
 * @param string $funcionalidade Nome da funcionalidade
 * @param string $entidade Nome da entidade (ex: "Ponto de Medição", "Usuário")
 * @param mixed $id ID do registro criado
 * @param string $identificador Identificador legível (ex: nome, código)
 * @param array $dados Dados inseridos (opcional)
 * @param int|null $cdUnidade Código da unidade (opcional)
 * @return bool
 */
function registrarLogInsert($funcionalidade, $entidade, $id, $identificador, $dados = [], $cdUnidade = null) {
    $descricao = "Cadastrou $entidade: $identificador (ID: $id)";
    return registrarLog($funcionalidade, 'INSERT', $descricao, $dados, LOG_INFO, $cdUnidade);
}

/**
 * Registra log de UPDATE
 * 
 * @param string $funcionalidade Nome da funcionalidade
 * @param string $entidade Nome da entidade
 * @param mixed $id ID do registro alterado
 * @param string $identificador Identificador legível
 * @param array $alteracoes Campos alterados (opcional)
 * @param int|null $cdUnidade Código da unidade (opcional)
 * @return bool
 */
function registrarLogUpdate($funcionalidade, $entidade, $id, $identificador, $alteracoes = [], $cdUnidade = null) {
    $descricao = "Alterou $entidade: $identificador (ID: $id)";
    return registrarLog($funcionalidade, 'UPDATE', $descricao, $alteracoes, LOG_INFO, $cdUnidade);
}

/**
 * Registra log de DELETE
 * 
 * @param string $funcionalidade Nome da funcionalidade
 * @param string $entidade Nome da entidade
 * @param mixed $id ID do registro excluído
 * @param string $identificador Identificador legível
 * @param array $dadosExcluidos Dados que foram excluídos (opcional, para auditoria)
 * @param int|null $cdUnidade Código da unidade (opcional)
 * @return bool
 */
function registrarLogDelete($funcionalidade, $entidade, $id, $identificador, $dadosExcluidos = [], $cdUnidade = null) {
    $descricao = "Excluiu $entidade: $identificador (ID: $id)";
    return registrarLog($funcionalidade, 'DELETE', $descricao, $dadosExcluidos, LOG_INFO, $cdUnidade);
}

/**
 * Registra log de LOGIN
 * 
 * @param int $cdUsuario ID do usuário
 * @param string $login Login do usuário
 * @param string $metodo Método de autenticação (LDAP, LOCAL, etc.)
 * @param bool $sucesso Se o login foi bem-sucedido
 * @param string $motivoFalha Motivo da falha (se aplicável)
 * @return bool
 */
function registrarLogLogin($cdUsuario, $login, $metodo = 'LDAP', $sucesso = true, $motivoFalha = '') {
    try {
        $pdo = obterConexaoLog();
        if (!$pdo) {
            error_log("SIMP LOG: Não foi possível obter conexão para registrar login");
            return false;
        }
        
        $servidor = $_SERVER['SERVER_NAME'] ?? gethostname() ?? 'DESCONHECIDO';
        $servidor = substr($servidor, 0, 50);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONHECIDO';
        
        if ($sucesso) {
            $tipoLog = LOG_INFO;
            $nomeLog = 'LOGIN';
            $descricao = "Login realizado com sucesso via $metodo\nIP: $ip\nUsuário: $login";
        } else {
            $tipoLog = LOG_AVISO;
            $nomeLog = 'LOGIN_FALHA';
            $descricao = "Tentativa de login falhou via $metodo\nIP: $ip\nUsuário: $login\nMotivo: $motivoFalha";
        }
        
        $sql = "INSERT INTO SIMP.dbo.LOG 
                (CD_USUARIO, CD_FUNCIONALIDADE, CD_UNIDADE, DT_LOG, TP_LOG, NM_LOG, DS_LOG, DS_VERSAO, NM_SERVIDOR) 
                VALUES 
                (:cdUsuario, NULL, NULL, GETDATE(), :tipoLog, :nomeLog, :descricao, :versao, :servidor)";
        
        $stmt = $pdo->prepare($sql);
        
        // Bind com tipos explícitos
        if ($sucesso && $cdUsuario !== null) {
            $stmt->bindValue(':cdUsuario', (int)$cdUsuario, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':cdUsuario', null, PDO::PARAM_NULL);
        }
        
        $stmt->bindValue(':tipoLog', (int)$tipoLog, PDO::PARAM_INT);
        $stmt->bindValue(':nomeLog', $nomeLog, PDO::PARAM_STR);
        $stmt->bindValue(':descricao', $descricao, PDO::PARAM_STR);
        $stmt->bindValue(':versao', SIMP_VERSAO, PDO::PARAM_STR);
        $stmt->bindValue(':servidor', $servidor, PDO::PARAM_STR);
        
        $result = $stmt->execute();
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            error_log("SIMP LOG LOGIN: Erro ao inserir - " . print_r($errorInfo, true));
            return false;
        }
        
        return true;
        
    } catch (PDOException $e) {
        error_log("SIMP LOG PDO ERROR (LOGIN): " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("SIMP LOG ERROR (LOGIN): " . $e->getMessage());
        return false;
    }
}

/**
 * Registra log de LOGOUT
 * 
 * @return bool
 */
function registrarLogLogout() {
    try {
        // Garantir que a sessão está iniciada para pegar os dados
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        $cdUsuario = $_SESSION['cd_usuario'] ?? null;
        $login = $_SESSION['login'] ?? 'DESCONHECIDO';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONHECIDO';
        
        $descricao = "Logout realizado\nUsuário: $login\nIP: $ip";
        
        return registrarLog('', 'LOGOUT', $descricao, [], LOG_INFO, null);
        
    } catch (Exception $e) {
        error_log("SIMP LOG ERROR (LOGOUT): " . $e->getMessage());
        return false;
    }
}

/**
 * Registra log de consulta/acesso a dados sensíveis
 * 
 * @param string $funcionalidade Nome da funcionalidade
 * @param string $descricao Descrição da consulta
 * @param array $filtros Filtros utilizados na consulta
 * @return bool
 */
function registrarLogConsulta($funcionalidade, $descricao, $filtros = []) {
    return registrarLog($funcionalidade, 'CONSULTA', $descricao, $filtros, LOG_INFO, null);
}

/**
 * Registra log de alteração em massa
 * 
 * @param string $funcionalidade Nome da funcionalidade
 * @param string $entidade Nome da entidade
 * @param int $quantidade Quantidade de registros afetados
 * @param string $descricaoAcao Descrição da ação em massa
 * @param array $contexto Contexto adicional
 * @return bool
 */
function registrarLogAlteracaoMassa($funcionalidade, $entidade, $quantidade, $descricaoAcao, $contexto = []) {
    $descricao = "Alteração em massa em $entidade: $descricaoAcao ($quantidade registro(s) afetado(s))";
    return registrarLog($funcionalidade, 'UPDATE_MASSA', $descricao, $contexto, LOG_INFO, null);
}
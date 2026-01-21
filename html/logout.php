<?php
/**
 * SIMP - Logout com Registro de Log
 */

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tentar registrar log de logout (com tratamento de erro)
try {
    $logHelperPath = __DIR__ . '/bd/logHelper.php';
    if (file_exists($logHelperPath)) {
        require_once $logHelperPath;
        
        if (isset($_SESSION['cd_usuario'])) {
            registrarLogLogout();
        }
    }
} catch (Exception $e) {
    error_log('Erro ao registrar log de logout: ' . $e->getMessage());
}

// Destruir sessão
$_SESSION = [];

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

session_destroy();

header('Location: login.php');
exit();
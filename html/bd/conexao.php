<?php
// Inicia sessão se necessário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se desenvolvedor forçou ambiente
$ambienteForcado = $_SESSION['ambiente_forcado'] ?? null;

// Determina qual banco usar
if ($ambienteForcado === 'HOMOLOGAÇÃO') {
    // Forçar homologação
    $serverName = "sgbd-hom-simp.sistemas.cesan.com.br\corporativo";
    $database   = "simp";
    $uid        = "simp";
    $pwd        = "wzJirU9kWK1LWzwFruGE";
} elseif ($ambienteForcado === 'PRODUÇÃO') {
    // Forçar produção
    $serverName = "sgbd-simp.sistemas.cesan.com.br\corporativo";
    $database   = "simp";
    $uid        = "simp";
    $pwd        = "cesan";
} else {
    // Comportamento automático original
    $serverName = getenv('DB_HOST');
    $database   = getenv('DB_NAME');
    $uid        = getenv('DB_USER');
    $pwd        = getenv('DB_PASS');
}

$utf8 = header('Content-Type: text/html; charset=utf-8');
$pdoSIMP = new PDO("sqlsrv:server=$serverName;Database=$database", $uid, $pwd);
?>
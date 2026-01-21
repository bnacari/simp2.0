<?php
// captura o host e a URL request (fallbacks)
$currentHost = $_SERVER['HTTP_HOST'] ?? '';
$requestUri  = $_SERVER['REQUEST_URI'] ?? '';

// verificar se contém o domínio de homologação
// alternativa compatível com PHP < 8.0:
// $isHomologacao = (strpos($currentHost, 'vdeskadds007.cesan.com.br') !== false);
$isHomologacao = (strpos($currentHost, '') !== false); //PRODUÇÃO

// se quiser checar a URL completa (protocolo + host + URI)
$urlCompleta = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . "://{$currentHost}{$requestUri}";
$isHomologacaoByUrl = (strpos($urlCompleta, 'http://vdeskadds007.cesan.com.br/') === 0);

// escolher a lógica que preferir (host ou url)
if ($isHomologacao || $isHomologacaoByUrl) {
    $serverName = "sgbd-hom-simp.sistemas.cesan.com.br\corporativo";
    $database   = "simp";
    $uid        = "simp";
    $pwd        = "wzJirU9kWK1LWzwFruGE";
} else {
    $serverName = getenv('DB_HOST');
    $database   = getenv('DB_NAME');
    $uid        = getenv('DB_USER');
    $pwd        = getenv('DB_PASS');
}

$utf8 = header('Content-Type: text/html; charset=utf-8');
$pdoSIMP = new PDO("sqlsrv:server=$serverName;Database=$database", $uid, $pwd);
?>
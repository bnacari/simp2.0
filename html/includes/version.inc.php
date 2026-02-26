<?php
/**
 * SIMP - Versão automática baseada nos commits do repositório
 * Formato: 2.0.{número_commits} (build {hash_curto})
 * Cache em arquivo para evitar chamadas git a cada request
 */

function getSimpVersion() {
    $cacheFile = __DIR__ . '/../.version_cache';
    $cacheTTL = 300; // 5 minutos

    // Verificar cache
    if (file_exists($cacheFile)) {
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        if ($cacheData && isset($cacheData['timestamp']) && (time() - $cacheData['timestamp']) < $cacheTTL) {
            return $cacheData;
        }
    }

    // Gerar versão a partir do git
    $rootDir = realpath(__DIR__ . '/../../');
    $commitCount = trim(shell_exec("cd {$rootDir} && git rev-list --count HEAD 2>/dev/null"));
    $shortHash = trim(shell_exec("cd {$rootDir} && git log -1 --format=%h 2>/dev/null"));
    $commitDate = trim(shell_exec("cd {$rootDir} && git log -1 --format=%ci 2>/dev/null"));

    if (!$commitCount || !$shortHash) {
        // Fallback: ler arquivo version na raiz
        $versionFile = $rootDir . '/version';
        $version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '0.0.0';
        return [
            'version' => $version,
            'hash' => '',
            'date' => '',
            'display' => "v{$version}"
        ];
    }

    $version = "2.0.{$commitCount}";
    $data = [
        'version' => $version,
        'hash' => $shortHash,
        'date' => $commitDate,
        'display' => "v{$version}",
        'timestamp' => time()
    ];

    // Salvar cache (silencioso se falhar)
    @file_put_contents($cacheFile, json_encode($data));

    return $data;
}

$simpVersion = getSimpVersion();

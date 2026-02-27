<?php
/**
 * SIMP - Versão automática baseada nos commits do repositório
 * Formato: 2.0.{número_commits} (build {hash_curto})
 *
 * A versão é gerada pelo hook pre-commit e salva em html/version.json.
 * Este arquivo é lido em runtime (funciona tanto em dev quanto em Docker).
 * Em ambiente de desenvolvimento com git disponível, recalcula dinamicamente.
 */

function getSimpVersion() {
    $cacheFile = __DIR__ . '/../.version_cache';
    $cacheTTL = 300; // 5 minutos

    // Verificar cache em memória/arquivo
    if (file_exists($cacheFile)) {
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        if ($cacheData && isset($cacheData['timestamp']) && (time() - $cacheData['timestamp']) < $cacheTTL) {
            return $cacheData;
        }
    }

    // Tentar gerar versão a partir do git (ambiente de desenvolvimento)
    $rootDir = realpath(__DIR__ . '/../../');
    $commitCount = trim(shell_exec("cd {$rootDir} && git rev-list --count HEAD 2>/dev/null"));
    $shortHash = trim(shell_exec("cd {$rootDir} && git log -1 --format=%h 2>/dev/null"));
    $commitDate = trim(shell_exec("cd {$rootDir} && git log -1 --format=%ci 2>/dev/null"));

    if ($commitCount && $shortHash) {
        // Baseline: 198 commits no momento da implantação do versionamento (v2.0.0)
        $patch = max(0, (int)$commitCount - 198);
        $version = "2.0.{$patch}";
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

    // Fallback: ler version.json gerado pelo pre-commit hook (Docker/produção)
    $versionJsonFile = __DIR__ . '/../version.json';
    if (file_exists($versionJsonFile)) {
        $data = json_decode(file_get_contents($versionJsonFile), true);
        if ($data && isset($data['version'])) {
            $data['timestamp'] = time();
            @file_put_contents($cacheFile, json_encode($data));
            return $data;
        }
    }

    // Último fallback
    return [
        'version' => '2.0.0',
        'hash' => '',
        'date' => '',
        'display' => 'v2.0.0'
    ];
}

$simpVersion = getSimpVersion();

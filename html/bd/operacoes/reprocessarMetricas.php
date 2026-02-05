<?php
/**
 * reprocessarMetricas.php
 * 
 * Helper para enfileirar reprocessamento de métricas (ASSÍNCRONO).
 * As solicitações são inseridas na tabela FILA_REPROCESSAMENTO_METRICAS
 * e processadas pelo Job do SQL Server Agent a cada 2 minutos.
 * 
 * Localização: html/bd/operacoes/reprocessarMetricas.php
 * 
 * Uso:
 *   @include_once 'reprocessarMetricas.php';
 *   if (function_exists('enfileirarReprocessamento')) {
 *       enfileirarReprocessamento($pdoSIMP, '2026-01-25', 'VALIDACAO', $cdUsuario);
 *   }
 */

/**
 * Enfileira uma data para reprocessamento assíncrono.
 * A SP_PROCESSAR_MEDICAO_V2 será executada pelo Job do SQL Server Agent.
 *
 * @param PDO    $pdo      Conexão PDO com o banco SIMP
 * @param string $data     Data no formato 'Y-m-d' (ex: '2026-01-25')
 * @param string $origem   Identificador da origem: 'VALIDACAO', 'VALIDACAO_IA', 'IMPORTACAO', 'EXCLUSAO', 'RESTAURACAO'
 * @param int    $cdUsuario Código do usuário que solicitou (opcional)
 * @return bool  true se enfileirou com sucesso, false se houve erro
 */
function enfileirarReprocessamento($pdo, $data, $origem = null, $cdUsuario = null) {
    try {
        if (empty($pdo) || empty($data)) {
            return false;
        }
        
        // Validar formato da data
        $dt = DateTime::createFromFormat('Y-m-d', $data);
        if (!$dt || $dt->format('Y-m-d') !== $data) {
            return false;
        }
        
        $stmt = $pdo->prepare("EXEC SP_ENFILEIRAR_REPROCESSAMENTO @DT_REFERENCIA = ?, @DS_ORIGEM = ?, @CD_USUARIO = ?");
        $stmt->execute([$data, $origem, $cdUsuario]);
        
        return true;
        
    } catch (Exception $e) {
        // Log silencioso - nunca deve impactar a operação principal
        error_log('[SIMP] Erro ao enfileirar reprocessamento para ' . $data . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * Enfileira múltiplas datas para reprocessamento assíncrono.
 *
 * @param PDO    $pdo      Conexão PDO com o banco SIMP
 * @param array  $datas    Array de datas no formato 'Y-m-d'
 * @param string $origem   Identificador da origem
 * @param int    $cdUsuario Código do usuário
 * @return array ['sucesso' => int, 'falha' => int]
 */
function enfileirarReprocessamentoMultiplo($pdo, $datas, $origem = null, $cdUsuario = null) {
    $resultado = ['sucesso' => 0, 'falha' => 0];
    
    // Remover duplicatas
    $datasUnicas = array_unique($datas);
    
    foreach ($datasUnicas as $data) {
        if (enfileirarReprocessamento($pdo, $data, $origem, $cdUsuario)) {
            $resultado['sucesso']++;
        } else {
            $resultado['falha']++;
        }
    }
    
    return $resultado;
}

// ============================================================
// ALIASES - Mantém compatibilidade com nomes antigos
// ============================================================

/**
 * @deprecated Use enfileirarReprocessamento()
 */
function reprocessarMetricasDiarias($pdo, $data) {
    return enfileirarReprocessamento($pdo, $data, 'LEGACY');
}

/**
 * @deprecated Use enfileirarReprocessamentoMultiplo()
 */
function reprocessarMetricasMultiplasDatas($pdo, $datas) {
    return enfileirarReprocessamentoMultiplo($pdo, $datas, 'LEGACY');
}
<?php
/**
 * SIMP - Sistema Integrado de Macromedicao e Pitometria
 * Endpoint: Buscar Sincronizacao dos Pontos com Historiador CCO
 * 
 * Retorna TODOS os pontos de uma vez. Filtros e ordenacao
 * sao aplicados client-side em JavaScript para resposta instantanea.
 * 
 * @author Bruno
 * @version 4.0
 * @date 2026-02-05
 */

header('Content-Type: application/json; charset=utf-8');

try {
    @include_once '../verificarAuth.php';
    include_once '../conexao.php';

    if (!isset($pdoSIMP)) {
        throw new Exception('Conexao nao estabelecida');
    }

    // ========================================
    // QUERY UNICA - Carrega tudo de uma vez
    // CTE para filtrar pontos antes do JOIN pesado
    // ========================================
    $sql = "
        ;WITH PONTOS_HIST AS (
            SELECT 
                p.CD_PONTO_MEDICAO,
                p.DS_NOME,
                p.DT_ATIVACAO,
                p.ID_TIPO_MEDIDOR,
                p.DS_TAG_VAZAO,
                p.DS_TAG_PRESSAO,
                p.DS_TAG_TEMP_AGUA,
                p.DS_TAG_TEMP_AMBIENTE,
                p.DS_TAG_VOLUME,
                p.DS_TAG_RESERVATORIO,
                L.CD_UNIDADE,
                L.CD_LOCALIDADE AS CD_LOCALIDADE_CODIGO,
                U.DS_NOME AS DS_UNIDADE
            FROM SIMP.dbo.PONTO_MEDICAO p
            LEFT JOIN SIMP.dbo.LOCALIDADE L ON p.CD_LOCALIDADE = L.CD_CHAVE
            LEFT JOIN SIMP.dbo.UNIDADE U ON L.CD_UNIDADE = U.CD_UNIDADE
            WHERE p.ID_TIPO_LEITURA = 8
              AND (p.DT_DESATIVACAO IS NULL OR p.DT_DESATIVACAO > GETDATE())
              AND p.DT_ATIVACAO IS NOT NULL
              AND (
                  (p.DS_TAG_VAZAO IS NOT NULL AND p.DS_TAG_VAZAO <> '') OR
                  (p.DS_TAG_PRESSAO IS NOT NULL AND p.DS_TAG_PRESSAO <> '') OR
                  (p.DS_TAG_TEMP_AGUA IS NOT NULL AND p.DS_TAG_TEMP_AGUA <> '') OR
                  (p.DS_TAG_TEMP_AMBIENTE IS NOT NULL AND p.DS_TAG_TEMP_AMBIENTE <> '') OR
                  (p.DS_TAG_VOLUME IS NOT NULL AND p.DS_TAG_VOLUME <> '') OR
                  (p.DS_TAG_RESERVATORIO IS NOT NULL AND p.DS_TAG_RESERVATORIO <> '')
              )
        )
        SELECT 
            ph.CD_PONTO_MEDICAO,
            ph.DS_NOME,
            ph.DT_ATIVACAO,
            ph.ID_TIPO_MEDIDOR,
            ph.DS_TAG_VAZAO,
            ph.DS_TAG_PRESSAO,
            ph.DS_TAG_TEMP_AGUA,
            ph.DS_TAG_TEMP_AMBIENTE,
            ph.DS_TAG_VOLUME,
            ph.DS_TAG_RESERVATORIO,
            ph.CD_UNIDADE,
            ph.CD_LOCALIDADE_CODIGO,
            ph.DS_UNIDADE,
            MAX(rvp.DT_LEITURA) AS ULTIMA_LEITURA,
            DATEDIFF(DAY, MAX(rvp.DT_LEITURA), GETDATE()) AS DIAS_SEM_LEITURA,
            CASE 
                WHEN MAX(rvp.DT_LEITURA) IS NULL THEN 'Nunca teve leitura'
                WHEN MAX(rvp.DT_LEITURA) >= DATEADD(DAY, -7, GETDATE()) THEN 'Ultima semana'
                WHEN MAX(rvp.DT_LEITURA) >= DATEADD(DAY, -30, GETDATE()) THEN 'Ultimo mes'
                WHEN MAX(rvp.DT_LEITURA) >= DATEADD(DAY, -60, GETDATE()) THEN 'Ultimos 60 dias'
                ELSE 'Mais de 60 dias'
            END AS SITUACAO
        FROM PONTOS_HIST ph
        LEFT JOIN SIMP.dbo.REGISTRO_VAZAO_PRESSAO rvp 
            ON ph.CD_PONTO_MEDICAO = rvp.CD_PONTO_MEDICAO 
            AND rvp.ID_SITUACAO = 1
        GROUP BY 
            ph.CD_PONTO_MEDICAO, ph.DS_NOME, ph.DT_ATIVACAO,
            ph.ID_TIPO_MEDIDOR,
            ph.DS_TAG_VAZAO, ph.DS_TAG_PRESSAO, ph.DS_TAG_TEMP_AGUA,
            ph.DS_TAG_TEMP_AMBIENTE, ph.DS_TAG_VOLUME, ph.DS_TAG_RESERVATORIO,
            ph.CD_UNIDADE, ph.CD_LOCALIDADE_CODIGO, ph.DS_UNIDADE
        ORDER BY DIAS_SEM_LEITURA DESC
    ";

    $stmt = $pdoSIMP->query($sql);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'dados' => $dados,
        'total' => count($dados),
        'gerado_em' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
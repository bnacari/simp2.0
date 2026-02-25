<?php
/**
 * SIMP - Buscar Valores (Unidades Operacionais) de um Tipo
 * Endpoint AJAX para lazy load dos valores em entidade.php
 * 
 * Carrega os valores de um tipo específico sob demanda,
 * evitando renderizar todos os valores no carregamento inicial da página.
 * 
 * Parâmetros GET:
 *   - cdTipo (int): CD_CHAVE do ENTIDADE_TIPO
 * 
 * Retorna JSON com array de valores contendo nome, ID, fluxo, total de itens e favorito.
 * 
 * @author Bruno / SIMP
 * @version 1.0
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../includes/auth.php';
exigePermissaoTela('Cadastro de Entidade', ACESSO_LEITURA);

try {
    include_once '../conexao.php';

    if (!isset($pdoSIMP)) {
        throw new Exception('Conexão não estabelecida');
    }

    $cdTipo = isset($_GET['cdTipo']) ? (int)$_GET['cdTipo'] : 0;

    if ($cdTipo <= 0) {
        throw new Exception('Código do tipo não informado');
    }

    // Buscar CD do usuário logado para favoritos
    $cdUsuario = isset($_SESSION['cd_usuario']) ? (int)$_SESSION['cd_usuario'] : 0;

    // Query: busca valores do tipo com contagem de itens e flag de favorito
    $sql = "SELECT 
                EV.CD_CHAVE AS VALOR_CD,
                EV.DS_NOME AS VALOR_NOME,
                EV.CD_ENTIDADE_VALOR_ID AS VALOR_ID,
                EV.ID_FLUXO,
                (SELECT COUNT(*) 
                 FROM SIMP.dbo.ENTIDADE_VALOR_ITEM 
                 WHERE CD_ENTIDADE_VALOR = EV.CD_CHAVE) AS TOTAL_ITENS,
                CASE WHEN FAV.CD_ENTIDADE_VALOR IS NOT NULL THEN 1 ELSE 0 END AS IS_FAVORITO
            FROM SIMP.dbo.ENTIDADE_VALOR EV
            LEFT JOIN SIMP.dbo.ENTIDADE_VALOR_FAVORITO FAV 
                ON FAV.CD_ENTIDADE_VALOR = EV.CD_CHAVE 
                AND FAV.CD_USUARIO = :cdUsuario
            WHERE EV.CD_ENTIDADE_TIPO = :cdTipo
            ORDER BY EV.DS_NOME";

    $stmt = $pdoSIMP->prepare($sql);
    $stmt->execute([
        ':cdTipo'    => $cdTipo,
        ':cdUsuario' => $cdUsuario
    ]);

    $valores = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $valores[] = [
            'cd'         => (int)$row['VALOR_CD'],
            'nome'       => $row['VALOR_NOME'],
            'id'         => $row['VALOR_ID'],
            'fluxo'      => (int)$row['ID_FLUXO'],
            'totalItens' => (int)$row['TOTAL_ITENS'],
            'favorito'   => (int)$row['IS_FAVORITO']
        ];
    }

    echo json_encode([
        'success' => true,
        'data'    => $valores,
        'total'   => count($valores)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
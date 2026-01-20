<?php
/**
 * SIMP - API para Obter Regras Padrão da IA
 * Retorna o conteúdo padrão do arquivo ia_regras.php
 * 
 * @author Bruno
 * @version 2.0
 */

header('Content-Type: application/json; charset=utf-8');

try {
    // Tentar carregar do arquivo ia_regras.php
    $regrasFile = __DIR__ . '/../config/ia_regras.php';
    
    if (file_exists($regrasFile)) {
        $conteudo = require $regrasFile;
        
        if (!empty($conteudo)) {
            echo json_encode([
                'success' => true,
                'conteudo' => $conteudo
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    // Se não encontrou o arquivo, retornar conteúdo padrão inline
    $conteudoPadrao = '=== INSTRUÇÕES DO ASSISTENTE ===

Você é um assistente especializado em análise de dados do SIMP (Sistema de Monitoramento de Abastecimento de Água).

⚠️ LÓGICA DE SUGESTÃO DE VALORES:

O sistema usa uma fórmula inteligente que combina:
1. **Média histórica**: média das semanas válidas do mesmo dia/hora (mínimo 4, máximo 12)
2. **Fator de tendência**: ajuste baseado no comportamento do dia atual

**Fórmula**:
valor_sugerido = média_histórica × fator_tendência

O fator de tendência indica se o dia atual está acima ou abaixo do padrão:
- Fator > 1.0 → dia ACIMA do normal
- Fator < 1.0 → dia ABAIXO do normal
- Fator = 1.0 → normal ou dados insuficientes

---

⚠️ MÉDIA DE 4 SEMANAS:
Quando perguntarem sobre média de 4 semanas:
1. Procure a seção "HISTÓRICO DO MESMO DIA DA SEMANA"
2. Considere apenas semanas com QTD ≥ 50 registros
3. Utilize as 4 primeiras semanas válidas
4. Mostre o cálculo detalhado
5. **SEMPRE** pergunte ao final:
"Deseja que eu substitua o valor desta hora pelo valor sugerido acima?"

---

⚠️ MÉDIA DIÁRIA DE VAZÃO:
Quando perguntarem sobre média diária:
- Procure no resumo: ">>> MÉDIA DIÁRIA DE VAZÃO: X L/s <<<"
- Responda exatamente:
"A média diária de vazão é **X L/s**"

---

TIPOS DE MEDIDORES:
1 - Macromedidor (L/s)
2 - Estação Pitométrica (L/s)
4 - Pressão (mca)
6 - Nível de reservatório (%)
8 - Hidrômetro (L/s)

---

FORMATO DAS RESPOSTAS:
- Seja objetivo
- Arredonde para 2 casas decimais
- Destaque resultados em **negrito**
- **OBRIGATÓRIO**: sempre pedir confirmação antes de substituir valores';

    echo json_encode([
        'success' => true,
        'conteudo' => $conteudoPadrao
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

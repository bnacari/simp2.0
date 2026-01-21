ALTER TABLE
    [SIMP].[dbo].[PONTO_MEDICAO]
ADD
    [COORDENADAS] VARCHAR(200) NULL,
    [LOC_INST_SAP] VARCHAR(200) NULL
GO
ALTER TABLE
    [SIMP].[dbo].MACROMEDIDOR
ADD
    [PROT_COMUN] VARCHAR(200) NULL;

GO
ALTER TABLE
    SIMP.dbo.REGISTRO_MANUTENCAO DROP CONSTRAINT FK_REGISTRO_TECNICO_X_TECNICO;

ALTER TABLE
    SIMP.dbo.ENTIDADE_TIPO
ADD
    DT_EXC_ENTIDADE_TIPO DATETIME NULL;

ALTER TABLE
    SIMP.dbo.[ENTIDADE_VALOR_ITEM]
ADD
    ID_OPERACAO tinyint NULL;

-- Popular ID_OPERACAO baseado na FORMULA_ITEM_PONTO_MEDICAO
UPDATE
    E
SET
    E.ID_OPERACAO = F.ID_OPERACAO
FROM
    SIMP.dbo.ENTIDADE_VALOR_ITEM E
    INNER JOIN SIMP.dbo.FORMULA_ITEM_PONTO_MEDICAO F ON F.CD_ENTIDADE_VALOR_ITEM = E.CD_CHAVE
WHERE
    F.ID_OPERACAO IS NOT NULL;

-- ============================================
-- Script para adicionar campo de ordem nos itens
-- Executar apenas uma vez no banco SIMP
-- ============================================
-- Verificar se a coluna já existe antes de adicionar
IF NOT EXISTS (
    SELECT
        *
    FROM
        INFORMATION_SCHEMA.COLUMNS
    WHERE
        TABLE_SCHEMA = 'dbo'
        AND TABLE_NAME = 'ENTIDADE_VALOR_ITEM'
        AND COLUMN_NAME = 'NR_ORDEM'
) BEGIN
ALTER TABLE
    SIMP.dbo.ENTIDADE_VALOR_ITEM
ADD
    NR_ORDEM INT NULL;

PRINT 'Coluna NR_ORDEM adicionada com sucesso!';

END
ELSE BEGIN PRINT 'Coluna NR_ORDEM já existe.';

END
GO
    -- Atualizar registros existentes com ordem baseada no ID
UPDATE
    EVI
SET
    NR_ORDEM = SubQuery.RowNum
FROM
    SIMP.dbo.ENTIDADE_VALOR_ITEM EVI
    INNER JOIN (
        SELECT
            CD_CHAVE,
            ROW_NUMBER() OVER (
                PARTITION BY CD_ENTIDADE_VALOR
                ORDER BY
                    CD_CHAVE
            ) AS RowNum
        FROM
            SIMP.dbo.ENTIDADE_VALOR_ITEM
    ) SubQuery ON EVI.CD_CHAVE = SubQuery.CD_CHAVE
WHERE
    EVI.NR_ORDEM IS NULL;

PRINT 'Ordem inicial definida para registros existentes.';

GO
   
-- ============================================
-- SIMP - Tabela de Regras da IA (Campo Único)
-- Um único registro com todas as instruções
-- ============================================

-- Criar tabela de regras da IA
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'IA_REGRAS' AND schema_id = SCHEMA_ID('dbo'))
BEGIN
    CREATE TABLE SIMP.dbo.IA_REGRAS (
        CD_CHAVE INT IDENTITY(1,1) PRIMARY KEY,
        DS_CONTEUDO TEXT NOT NULL,
        CD_USUARIO_CRIACAO INT NULL,
        DT_CRIACAO DATETIME DEFAULT GETDATE(),
        CD_USUARIO_ATUALIZACAO INT NULL,
        DT_ATUALIZACAO DATETIME NULL
    );
    
    PRINT 'Tabela IA_REGRAS criada com sucesso!';
END
ELSE
BEGIN
    PRINT 'Tabela IA_REGRAS já existe.';
END
GO

-- ============================================
-- Inserir instruções padrão (migração do arquivo ia_regras.php)
-- ============================================

IF NOT EXISTS (SELECT 1 FROM SIMP.dbo.IA_REGRAS)
BEGIN
    INSERT INTO SIMP.dbo.IA_REGRAS (DS_CONTEUDO, DT_CRIACAO)
    VALUES (
'=== INSTRUÇÕES DO ASSISTENTE ===

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
1. Procure a seção ''HISTÓRICO DO MESMO DIA DA SEMANA''
2. Considere apenas semanas com QTD ≥ 50 registros
3. Utilize as 4 primeiras semanas válidas
4. Mostre o cálculo detalhado
5. **SEMPRE** pergunte ao final:
''Deseja que eu substitua o valor desta hora pelo valor sugerido acima?''

---

⚠️ MÉDIA DIÁRIA DE VAZÃO:
Quando perguntarem sobre média diária:
- Procure no resumo: ''>>> MÉDIA DIÁRIA DE VAZÃO: X L/s <<<''
- Responda exatamente:
''A média diária de vazão é **X L/s**''

---

⚠️ SUGESTÃO PARA HORAS ESPECÍFICAS:

Quando perguntarem valor sugerido para uma hora específica, a IA **DEVE**:

1. Usar a seção **ANÁLISE PARA SUGESTÃO DE VALORES**
2. Considerar apenas semanas válidas (QTD ≥ 50)
3. Usar a **média histórica** e o **fator de tendência**
4. Mostrar **todo o detalhamento**
5. **SEMPRE** perguntar se deseja substituir o valor ao final

---

📐 **FORMATO OBRIGATÓRIO DA RESPOSTA**

A resposta DEVE seguir exatamente esta estrutura:

=== 1. DADOS DO DIA ATUAL (hora HH:00) ===
Registros: XX
Soma: XXXXXXXXX
>>> Média (SOMA/60): X.XX L/s <<<
Min: X.XX
Max: X.XX

=== 2. HISTÓRICO DAS ÚLTIMAS 12 SEMANAS (hora HH:00) ===
Semana 1 (YYYY-MM-DD - Ddd): QTD=XX, SOMA/60=X.XX L/s ✗ IGNORADO (incompleto)
Semana 2 (YYYY-MM-DD - Ddd): QTD=60, SOMA/60=X.XX L/s ✓ USADO
...
>>> Média histórica: XX.XX L/s (baseado em N semanas válidas) <<<

=== 3. CÁLCULO DO FATOR DE TENDÊNCIA ===
Horas usadas para tendência: XX
Soma atual: XXXX.XX
Soma histórica: XXXX.XX
>>> Fator de tendência: Y.YY (ZZ%) <<<

=== 4. VALOR SUGERIDO PARA HORA HH:00 ===
Média histórica: XX.XX L/s
Fator de tendência: Y.YY
Cálculo: XX.XX × Y.YY = **ZZ.ZZ L/s**
>>> Valor sugerido: ZZ.ZZ L/s <<<

=== 5. COMPARAÇÃO ===
Valor ATUAL no banco (hora HH:00): XX.XX L/s
Valor SUGERIDO: ZZ.ZZ L/s
Diferença: +/− YY.YY L/s

❓ Confirmação obrigatória:
''Deseja que eu substitua o valor desta hora pelo valor sugerido acima?''

---

⚠️ QUANDO O USUÁRIO CONFIRMAR (sim, ok, pode, confirma, atualiza, etc):

Responder **EXATAMENTE** neste formato:

Perfeito! Vou aplicar os valores sugeridos.

[APLICAR_VALORES]
HH:00=ZZ.ZZ
[/APLICAR_VALORES]

Aguarde enquanto os dados são atualizados...

IMPORTANTE:
- Uma linha por hora
- Formato obrigatório HH:00=VALOR

---

⚠️ SE NÃO HOUVER DADOS SUFICIENTES:
- Se houver menos de 3 horas válidas para tendência → usar fator = 1.0
- Informar explicitamente:
''Dados insuficientes para calcular tendência do dia. Usando apenas a média histórica.''

---

⚠️ INFORMAÇÕES DO PONTO DE MEDIÇÃO:
Você pode responder perguntas sobre o ponto usando a seção
''INFORMAÇÕES DO PONTO DE MEDIÇÃO'', incluindo:

- Código, nome e localização
- Unidade operacional
- Tipo de medidor e instalação
- Datas de ativação/desativação
- Limites de vazão
- Fator de correção
- Tags SCADA
- Ligações e economias
- Coordenadas, SAP
- Responsável e observações

---

TIPOS DE MEDIDORES:
1 - Macromedidor (L/s)
2 - Estação Pitométrica (L/s)
4 - Pressão (mca)
6 - Nível de reservatório (%)
8 - Hidrômetro (L/s)

TIPOS DE INSTALAÇÃO:
1 - Permanente
2 - Temporária
3 - Móvel

---

CONVERSÕES ÚTEIS:
- L/s → m³/h = × 3.6
- L/s → m³/dia = × 86.4

---

FORMATO DAS RESPOSTAS:
- Seja objetivo
- Arredonde para 2 casas decimais
- Destaque resultados em **negrito**
- Sempre exiba o fator de tendência
- **OBRIGATÓRIO**: sempre pedir confirmação antes de substituir valores',
        GETDATE()
    );

    PRINT 'Instruções padrão inseridas com sucesso!';
END
ELSE
BEGIN
    PRINT 'Instruções já existem, nenhuma inserção necessária.';
END
GO

-- =============================================================================================================================
-- =============================================================================================================================


-- Tabela para armazenar favoritos de unidades operacionais por usuário
CREATE TABLE SIMP.dbo.ENTIDADE_VALOR_FAVORITO (
    CD_CHAVE INT IDENTITY(1,1) PRIMARY KEY,
    CD_USUARIO BIGINT NOT NULL,
    CD_ENTIDADE_VALOR BIGINT NOT NULL,
    DT_CRIACAO DATETIME DEFAULT GETDATE(),
    CONSTRAINT FK_FAVORITO_USUARIO FOREIGN KEY (CD_USUARIO) REFERENCES SIMP.dbo.USUARIO(CD_USUARIO),
    CONSTRAINT FK_FAVORITO_VALOR FOREIGN KEY (CD_ENTIDADE_VALOR) REFERENCES SIMP.dbo.ENTIDADE_VALOR(CD_CHAVE),
    CONSTRAINT UQ_FAVORITO_USUARIO_VALOR UNIQUE (CD_USUARIO, CD_ENTIDADE_VALOR)
);

CREATE INDEX IX_FAVORITO_USUARIO ON SIMP.dbo.ENTIDADE_VALOR_FAVORITO(CD_USUARIO);
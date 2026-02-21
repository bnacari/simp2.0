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


-- =============================================================================================================================
-- =============================================================================================================================


use SIMP
ALTER TABLE SIMP.dbo.REGISTRO_VAZAO_PRESSAO
DISABLE TRIGGER TG_INSERT_UPDATE_REGISTRO_VAZAO_PRESSAO;


-- =============================================================================================================================
-- =============================================================================================================================


ALTER TABLE SIMP.dbo.MACROMEDIDOR ALTER COLUMN CD_PONTO_MEDICAO INT NULL;
GO

ALTER TABLE SIMP.dbo.ESTACAO_PITOMETRICA ALTER COLUMN CD_PONTO_MEDICAO INT NULL;
GO

ALTER TABLE SIMP.dbo.MEDIDOR_PRESSAO ALTER COLUMN CD_PONTO_MEDICAO INT NULL;
GO

ALTER TABLE SIMP.dbo.NIVEL_RESERVATORIO ALTER COLUMN CD_PONTO_MEDICAO INT NULL;
GO

ALTER TABLE SIMP.dbo.HIDROMETRO ALTER COLUMN CD_PONTO_MEDICAO INT NULL;
GO

PRINT 'CD_PONTO_MEDICAO alterado para NULL em todas as tabelas de equipamento.';

-- =============================================================================================================================
-- =============================================================================================================================


ALTER TABLE SIMP.dbo.ESTACAO_PITOMETRICA ADD DS_TAG VARCHAR(50) NULL;
ALTER TABLE SIMP.dbo.MEDIDOR_PRESSAO ADD DS_TAG VARCHAR(50) NULL;
ALTER TABLE SIMP.dbo.HIDROMETRO ADD DS_TAG VARCHAR(50) NULL;
ALTER TABLE SIMP.dbo.MACROMEDIDOR ALTER COLUMN DS_TAG VARCHAR(50) NULL;
ALTER TABLE SIMP.dbo.NIVEL_RESERVATORIO ALTER COLUMN DS_TAG VARCHAR(50) NULL;


-- =============================================================================================================================
-- =============================================================================================================================

BEGIN TRANSACTION;

UPDATE M SET M.DS_TAG = PM.DS_TAG_VAZAO
FROM SIMP.dbo.MACROMEDIDOR M
INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = M.CD_PONTO_MEDICAO
WHERE PM.DS_TAG_VAZAO IS NOT NULL AND PM.ID_TIPO_MEDIDOR = 1;
PRINT 'Macromedidor: ' + CAST(@@ROWCOUNT AS VARCHAR);

UPDATE M SET M.DS_TAG = PM.DS_TAG_VAZAO
FROM SIMP.dbo.ESTACAO_PITOMETRICA M
INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = M.CD_PONTO_MEDICAO
WHERE PM.DS_TAG_VAZAO IS NOT NULL AND PM.ID_TIPO_MEDIDOR = 2;
PRINT 'Estação Pitométrica: ' + CAST(@@ROWCOUNT AS VARCHAR);

UPDATE M SET M.DS_TAG = PM.DS_TAG_PRESSAO
FROM SIMP.dbo.MEDIDOR_PRESSAO M
INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = M.CD_PONTO_MEDICAO
WHERE PM.DS_TAG_PRESSAO IS NOT NULL AND PM.ID_TIPO_MEDIDOR = 4;
PRINT 'Medidor Pressão: ' + CAST(@@ROWCOUNT AS VARCHAR);

UPDATE M SET M.DS_TAG = PM.DS_TAG_RESERVATORIO
FROM SIMP.dbo.NIVEL_RESERVATORIO M
INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = M.CD_PONTO_MEDICAO
WHERE PM.DS_TAG_RESERVATORIO IS NOT NULL AND PM.ID_TIPO_MEDIDOR = 6;
PRINT 'Nível Reservatório: ' + CAST(@@ROWCOUNT AS VARCHAR);

UPDATE M SET M.DS_TAG = PM.DS_TAG_VAZAO
FROM SIMP.dbo.HIDROMETRO M
INNER JOIN SIMP.dbo.PONTO_MEDICAO PM ON PM.CD_PONTO_MEDICAO = M.CD_PONTO_MEDICAO
WHERE PM.DS_TAG_VAZAO IS NOT NULL AND PM.ID_TIPO_MEDIDOR = 8;
PRINT 'Hidrômetro: ' + CAST(@@ROWCOUNT AS VARCHAR);

COMMIT;


-- =============================================================================================================================
-- =============================================================================================================================


CREATE TABLE SIMP.dbo.AUX_RELACAO_PONTOS_MEDICAO (
    CD_CHAVE INT IDENTITY(1,1) PRIMARY KEY,
    TAG_PRINCIPAL VARCHAR(100) NOT NULL,
    TAG_AUXILIAR VARCHAR(100) NOT NULL,
    DT_CADASTRO DATETIME DEFAULT GETDATE(),
    CONSTRAINT UQ_RELACAO UNIQUE (TAG_PRINCIPAL, TAG_AUXILIAR)
);


INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP003_TM214_10_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP003_TM214_11_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP003_TM214_12_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP003_TM214_15_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP003_TM214_8_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP024_TM134_5_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP171_TM80_3_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP182_TM78_3_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP205_TM190_3_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP205_TM190_4_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP234_TM11_4_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP003_TM214_13_MED','CP234_TM11_5_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','CP013_TM30_117_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','CP013_TM30_126_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','CP013_TM30_128_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','CP013_TM30_83_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','CP217_TM06_13_CALC','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','CP236_TM12_11_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','CP236_TM12_12_CALC','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','CP237_TM1_2_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','GPRS173_TM2_142_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','GPRS173_TM2_143_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','GPRS174_M007_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','GPRS175_M007_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('CP013_TM30_127_MED','GPRS176_M007_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GPRS046_M029_CALC','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GPRS051_M021_CALC','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GPRS051_M022_CALC','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GPRS053_M024_CALC','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GPRS054_M025_CALC','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GPRS058_M052_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GPRS059_M053_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GPRS065_M034_MED','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GUA-RAT-013-M-C-CA-01','2026-02-19 11:18:53.397')
INSERT INTO AUX_RELACAO_PONTOS_MEDICAO VALUES('GPRS050_M010_MED','GUA-RAT-014-M-C-CA-01','2026-02-19 11:18:53.397')

-- =============================================================================================================================
-- =============================================================================================================================

-- ============================================
-- SIMP - Cadastro Genérico em Cascata + Grafo de Fluxo
-- 
-- Estrutura:
--   ENTIDADE_NIVEL         - Define tipos de camada (configurável)
--   ENTIDADE_NODO          - Nó genérico (hierarquia via conexões)
--   ENTIDADE_NODO_CONEXAO  - Grafo dirigido: fluxo físico entre nós
--   VW_ENTIDADE_ARVORE     - View flat com dados consolidados
--   VW_ENTIDADE_CONEXOES   - View de conexões com nomes resolvidos
--
-- A hierarquia é definida exclusivamente pelas conexões
-- (ENTIDADE_NODO_CONEXAO), não por parentesco direto.
--
-- @author  Bruno - CESAN
-- @version 3.0
-- @date    2026-02
-- ============================================


-- ============================================
-- 1. ENTIDADE_NIVEL
--    Define os "tipos" de cada camada.
--    OP_EH_SISTEMA = 1 → nós deste nível representam
--    um Sistema de Abastecimento (aparecem no filtro).
-- ============================================
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ENTIDADE_NIVEL' AND schema_id = SCHEMA_ID('dbo'))
BEGIN
    CREATE TABLE SIMP.dbo.ENTIDADE_NIVEL (
        CD_CHAVE         INT IDENTITY(1,1) PRIMARY KEY,
        DS_NOME          VARCHAR(100)  NOT NULL,            -- Ex: "Manancial", "ETA", "Reservatório"
        DS_ICONE         VARCHAR(50)   NULL,                -- Ionicon (ex: "water-outline")
        DS_COR           VARCHAR(20)   NULL,                -- Hex (ex: "#1565C0")
        NR_ORDEM         INT           NOT NULL DEFAULT 0,  -- Ordem de exibição
        OP_PERMITE_PONTO TINYINT       NOT NULL DEFAULT 0,  -- 1 = nós deste nível vinculam PONTO_MEDICAO
        OP_EH_SISTEMA    BIT           NOT NULL DEFAULT 0,  -- 1 = nós deste nível são Sistemas de Abastecimento
        OP_ATIVO         TINYINT       NOT NULL DEFAULT 1,
        DT_CADASTRO      DATETIME      DEFAULT GETDATE(),
        DT_ATUALIZACAO   DATETIME      NULL
    );

    PRINT 'Tabela ENTIDADE_NIVEL criada com sucesso!';

    -- Níveis padrão para o fluxo da água
    INSERT INTO SIMP.dbo.ENTIDADE_NIVEL (DS_NOME, DS_ICONE, DS_COR, NR_ORDEM, OP_PERMITE_PONTO, OP_EH_SISTEMA)
    VALUES 
        ('Manancial/Captação',       'water-outline',            '#0D47A1', 1, 0, 0),
        ('Unidade Operacional',      'business-outline',         '#E65100', 2, 0, 0),
        ('Fluxo',                    'swap-horizontal-outline',  '#6A1B9A', 3, 0, 0),
        ('Reservatório',             'cube-outline',             '#00695C', 4, 0, 0),
        ('Distribuição/Setor',       'git-branch-outline',       '#1B5E20', 5, 0, 0),
        ('Ponto de Medição',         'speedometer-outline',      '#2E7D32', 6, 1, 0),
        ('Sistema de Abastecimento', 'git-network-outline',      '#005596', 7, 0, 1);

    PRINT 'Níveis padrão inseridos.';
END
ELSE
BEGIN
    PRINT 'Tabela ENTIDADE_NIVEL já existe.';

    -- Garantir colunas que foram adicionadas em versões anteriores
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'ENTIDADE_NIVEL' AND COLUMN_NAME = 'OP_EH_SISTEMA')
    BEGIN
        ALTER TABLE SIMP.dbo.ENTIDADE_NIVEL ADD OP_EH_SISTEMA BIT NOT NULL DEFAULT 0;
        PRINT 'Coluna OP_EH_SISTEMA adicionada em ENTIDADE_NIVEL.';
    END
END
GO


-- ============================================
-- 2. ENTIDADE_NODO
--    Nó genérico. Hierarquia definida por conexões.
--    Nós com nível OP_PERMITE_PONTO=1 vinculam PONTO_MEDICAO.
--    Nós com nível OP_EH_SISTEMA=1 vinculam SISTEMA_AGUA.
-- ============================================
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ENTIDADE_NODO' AND schema_id = SCHEMA_ID('dbo'))
BEGIN
    CREATE TABLE SIMP.dbo.ENTIDADE_NODO (
        CD_CHAVE          INT IDENTITY(1,1) PRIMARY KEY,
        CD_ENTIDADE_NIVEL INT           NOT NULL,              -- FK → ENTIDADE_NIVEL
        DS_NOME           VARCHAR(200)  NOT NULL,
        DS_IDENTIFICADOR  VARCHAR(100)  NULL,                  -- Código externo (ex: "ETA-001")
        NR_ORDEM          INT           NOT NULL DEFAULT 0,    -- Ordem de exibição
        -- Vínculos opcionais (conforme flags do nível)
        CD_PONTO_MEDICAO  INT           NULL,                  -- FK → PONTO_MEDICAO (quando OP_PERMITE_PONTO=1)
        CD_SISTEMA_AGUA   INT           NULL,                  -- FK → SISTEMA_AGUA  (quando OP_EH_SISTEMA=1)
        ID_OPERACAO       TINYINT       NULL,                  -- 1=Soma(+), 2=Subtração(-)
        ID_FLUXO          TINYINT       NULL,                  -- 1=Entrada, 2=Saída, 3=Municipal, 4=N/A
        -- Posição no canvas (flowchart visual)
        NR_POS_X          INT           NULL,                  -- Coordenada X salva pelo editor
        NR_POS_Y          INT           NULL,                  -- Coordenada Y salva pelo editor
        -- Metadados
        DS_OBSERVACAO     VARCHAR(500)  NULL,
        OP_ATIVO          TINYINT       NOT NULL DEFAULT 1,
        DT_CADASTRO       DATETIME      DEFAULT GETDATE(),
        DT_ATUALIZACAO    DATETIME      NULL,
        -- Constraints
        CONSTRAINT FK_NODO_NIVEL        FOREIGN KEY (CD_ENTIDADE_NIVEL) REFERENCES SIMP.dbo.ENTIDADE_NIVEL(CD_CHAVE),
        CONSTRAINT FK_NODO_PONTO        FOREIGN KEY (CD_PONTO_MEDICAO)  REFERENCES SIMP.dbo.PONTO_MEDICAO(CD_PONTO_MEDICAO),
        CONSTRAINT FK_NODO_SISTEMA_AGUA FOREIGN KEY (CD_SISTEMA_AGUA)   REFERENCES SIMP.dbo.SISTEMA_AGUA(CD_CHAVE)
    );

    CREATE INDEX IX_NODO_NIVEL   ON SIMP.dbo.ENTIDADE_NODO(CD_ENTIDADE_NIVEL);
    CREATE INDEX IX_NODO_PONTO   ON SIMP.dbo.ENTIDADE_NODO(CD_PONTO_MEDICAO) WHERE CD_PONTO_MEDICAO IS NOT NULL;
    CREATE INDEX IX_NODO_SISTEMA ON SIMP.dbo.ENTIDADE_NODO(CD_SISTEMA_AGUA)  WHERE CD_SISTEMA_AGUA  IS NOT NULL;
    CREATE INDEX IX_NODO_ATIVO   ON SIMP.dbo.ENTIDADE_NODO(OP_ATIVO);

    PRINT 'Tabela ENTIDADE_NODO criada com sucesso!';
END
ELSE
BEGIN
    PRINT 'Tabela ENTIDADE_NODO já existe.';

    -- Garantir colunas que foram adicionadas em versões anteriores
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'ENTIDADE_NODO' AND COLUMN_NAME = 'NR_POS_X')
    BEGIN
        ALTER TABLE SIMP.dbo.ENTIDADE_NODO ADD NR_POS_X INT NULL;
        ALTER TABLE SIMP.dbo.ENTIDADE_NODO ADD NR_POS_Y INT NULL;
        PRINT 'Colunas NR_POS_X e NR_POS_Y adicionadas.';
    END

    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'ENTIDADE_NODO' AND COLUMN_NAME = 'CD_SISTEMA_AGUA')
    BEGIN
        ALTER TABLE SIMP.dbo.ENTIDADE_NODO ADD CD_SISTEMA_AGUA INT NULL;
        ALTER TABLE SIMP.dbo.ENTIDADE_NODO ADD CONSTRAINT FK_NODO_SISTEMA_AGUA FOREIGN KEY (CD_SISTEMA_AGUA) REFERENCES SIMP.dbo.SISTEMA_AGUA(CD_CHAVE);
        PRINT 'Coluna CD_SISTEMA_AGUA adicionada.';
    END
END
GO


-- ============================================
-- 3. ENTIDADE_NODO_CONEXAO
--    Grafo dirigido: fluxo físico entre nós.
--    Representa "a água sai do nó A e vai para o nó B".
--    Um nó pode ter múltiplas origens e múltiplos destinos.
-- ============================================
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'ENTIDADE_NODO_CONEXAO' AND schema_id = SCHEMA_ID('dbo'))
BEGIN
    CREATE TABLE SIMP.dbo.ENTIDADE_NODO_CONEXAO (
        CD_CHAVE        INT IDENTITY(1,1) PRIMARY KEY,
        CD_NODO_ORIGEM  INT           NOT NULL,                    -- FK → ENTIDADE_NODO (de onde sai)
        CD_NODO_DESTINO INT           NOT NULL,                    -- FK → ENTIDADE_NODO (para onde vai)
        DS_ROTULO       VARCHAR(100)  NULL,                        -- Rótulo (ex: "Adutora DN600")
        DS_COR          VARCHAR(20)   NULL DEFAULT '#1565C0',      -- Cor da linha no diagrama
        NR_ORDEM        INT           NOT NULL DEFAULT 0,
        OP_ATIVO        TINYINT       NOT NULL DEFAULT 1,
        DT_CADASTRO     DATETIME      DEFAULT GETDATE(),
        DT_ATUALIZACAO  DATETIME      NULL,
        -- Constraints
        CONSTRAINT FK_CONEXAO_ORIGEM  FOREIGN KEY (CD_NODO_ORIGEM)  REFERENCES SIMP.dbo.ENTIDADE_NODO(CD_CHAVE),
        CONSTRAINT FK_CONEXAO_DESTINO FOREIGN KEY (CD_NODO_DESTINO) REFERENCES SIMP.dbo.ENTIDADE_NODO(CD_CHAVE),
        CONSTRAINT CK_CONEXAO_DIFF    CHECK (CD_NODO_ORIGEM <> CD_NODO_DESTINO)
    );

    CREATE INDEX IX_CONEXAO_ORIGEM  ON SIMP.dbo.ENTIDADE_NODO_CONEXAO(CD_NODO_ORIGEM);
    CREATE INDEX IX_CONEXAO_DESTINO ON SIMP.dbo.ENTIDADE_NODO_CONEXAO(CD_NODO_DESTINO);

    PRINT 'Tabela ENTIDADE_NODO_CONEXAO criada com sucesso!';
END
ELSE
BEGIN
    PRINT 'Tabela ENTIDADE_NODO_CONEXAO já existe.';
END
GO


-- ============================================
-- 4. VW_ENTIDADE_ARVORE
--    View flat de todos os nós com dados do nível
--    e nome do sistema de água vinculado.
-- ============================================
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_ENTIDADE_ARVORE')
    DROP VIEW dbo.VW_ENTIDADE_ARVORE;
GO

CREATE VIEW dbo.VW_ENTIDADE_ARVORE AS
SELECT 
    N.CD_CHAVE,
    N.CD_ENTIDADE_NIVEL,
    N.DS_NOME,
    N.DS_IDENTIFICADOR,
    N.NR_ORDEM,
    N.CD_PONTO_MEDICAO,
    N.CD_SISTEMA_AGUA,
    N.ID_OPERACAO,
    N.ID_FLUXO,
    N.NR_POS_X,
    N.NR_POS_Y,
    N.DS_OBSERVACAO,
    N.OP_ATIVO,
    -- Dados do nível
    NV.DS_NOME          AS DS_NIVEL,
    NV.DS_ICONE,
    NV.DS_COR,
    NV.OP_PERMITE_PONTO,
    NV.OP_EH_SISTEMA,
    -- Sistema de água vinculado
    SA.DS_NOME           AS DS_SISTEMA_AGUA
FROM SIMP.dbo.ENTIDADE_NODO N
INNER JOIN SIMP.dbo.ENTIDADE_NIVEL NV ON NV.CD_CHAVE = N.CD_ENTIDADE_NIVEL
LEFT  JOIN SIMP.dbo.SISTEMA_AGUA SA   ON SA.CD_CHAVE = N.CD_SISTEMA_AGUA
GO

PRINT 'View VW_ENTIDADE_ARVORE criada (flat, sem CD_PAI).';
GO


-- ============================================
-- 5. VW_ENTIDADE_CONEXOES
--    View de conexões com nomes resolvidos.
-- ============================================
IF EXISTS (SELECT * FROM sys.views WHERE name = 'VW_ENTIDADE_CONEXOES')
    DROP VIEW dbo.VW_ENTIDADE_CONEXOES;
GO

CREATE VIEW dbo.VW_ENTIDADE_CONEXOES AS
SELECT 
    C.CD_CHAVE,
    C.CD_NODO_ORIGEM,
    C.CD_NODO_DESTINO,
    C.DS_ROTULO,
    C.DS_COR,
    C.NR_ORDEM,
    C.OP_ATIVO,
    NO_ORIG.DS_NOME            AS DS_ORIGEM,
    NO_ORIG.DS_IDENTIFICADOR   AS DS_ORIGEM_ID,
    NV_ORIG.DS_NOME            AS DS_NIVEL_ORIGEM,
    NV_ORIG.DS_COR             AS DS_COR_ORIGEM,
    NO_DEST.DS_NOME            AS DS_DESTINO,
    NO_DEST.DS_IDENTIFICADOR   AS DS_DESTINO_ID,
    NV_DEST.DS_NOME            AS DS_NIVEL_DESTINO,
    NV_DEST.DS_COR             AS DS_COR_DESTINO
FROM SIMP.dbo.ENTIDADE_NODO_CONEXAO C
INNER JOIN SIMP.dbo.ENTIDADE_NODO  NO_ORIG ON NO_ORIG.CD_CHAVE = C.CD_NODO_ORIGEM
INNER JOIN SIMP.dbo.ENTIDADE_NODO  NO_DEST ON NO_DEST.CD_CHAVE = C.CD_NODO_DESTINO
INNER JOIN SIMP.dbo.ENTIDADE_NIVEL NV_ORIG ON NV_ORIG.CD_CHAVE = NO_ORIG.CD_ENTIDADE_NIVEL
INNER JOIN SIMP.dbo.ENTIDADE_NIVEL NV_DEST ON NV_DEST.CD_CHAVE = NO_DEST.CD_ENTIDADE_NIVEL
GO

PRINT 'View VW_ENTIDADE_CONEXOES criada.';
GO


-- ============================================
-- 6. MIGRAÇÃO: Remover colunas obsoletas
--    Executar MANUALMENTE em bancos que já existem.
--    CD_PAI, DT_INICIO e DT_FIM não são mais utilizados;
--    a hierarquia é definida por ENTIDADE_NODO_CONEXAO.
-- ============================================

-- Passo 1: Remover FK e índice de CD_PAI
IF EXISTS (SELECT * FROM sys.foreign_keys WHERE name = 'FK_NODO_PAI')
    ALTER TABLE SIMP.dbo.ENTIDADE_NODO DROP CONSTRAINT FK_NODO_PAI;
GO
IF EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_NODO_PAI' AND object_id = OBJECT_ID('SIMP.dbo.ENTIDADE_NODO'))
    DROP INDEX IX_NODO_PAI ON SIMP.dbo.ENTIDADE_NODO;
GO

-- Passo 2: Remover colunas
ALTER TABLE SIMP.dbo.ENTIDADE_NODO DROP COLUMN CD_PAI;
ALTER TABLE SIMP.dbo.ENTIDADE_NODO DROP COLUMN DT_INICIO;
ALTER TABLE SIMP.dbo.ENTIDADE_NODO DROP COLUMN DT_FIM;
GO
PRINT 'Colunas CD_PAI, DT_INICIO e DT_FIM removidas.';
GO

-- =============================================================================================================================
-- =============================================================================================================================



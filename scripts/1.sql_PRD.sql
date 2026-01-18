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
    -- ============================================
    -- Índice para acelerar as consultas
    CREATE INDEX IX_MRD_DT_MEDICAO_PONTO ON MEDICAO_RESUMO_DIARIO (DT_MEDICAO, CD_PONTO_MEDICAO) INCLUDE (
        VL_SCORE_SAUDE,
        FL_SEM_COMUNICACAO,
        FL_VALOR_CONSTANTE,
        FL_PERFIL_ANOMALO,
        FL_VALOR_NEGATIVO,
        FL_FORA_FAIXA,
        FL_SPIKE,
        FL_ANOMALIA,
        ID_SITUACAO,
        QTD_TRATAMENTOS
    );

-- ============================================
-- SIMP - Tabela de Regras/Instruções da IA
-- Permite que usuários treinem a IA via interface
-- ============================================
-- Criar tabela de regras da IA
IF NOT EXISTS (
    SELECT
        *
    FROM
        sys.tables
    WHERE
        name = 'IA_REGRAS'
        AND schema_id = SCHEMA_ID('dbo')
) BEGIN CREATE TABLE SIMP.dbo.IA_REGRAS (
    CD_CHAVE INT IDENTITY(1, 1) PRIMARY KEY,
    DS_TITULO VARCHAR(200) NOT NULL,
    DS_CATEGORIA VARCHAR(100) NULL,
    DS_CONTEUDO TEXT NOT NULL,
    NR_ORDEM INT DEFAULT 0,
    OP_ATIVO BIT DEFAULT 1,
    CD_USUARIO_CRIACAO INT NULL,
    DT_CRIACAO DATETIME DEFAULT GETDATE(),
    CD_USUARIO_ATUALIZACAO INT NULL,
    DT_ATUALIZACAO DATETIME NULL
);

PRINT 'Tabela IA_REGRAS criada com sucesso!';

END
ELSE BEGIN PRINT 'Tabela IA_REGRAS já existe.';

END
GO
    -- Criar índice para categoria
    IF NOT EXISTS (
        SELECT
            *
        FROM
            sys.indexes
        WHERE
            name = 'IX_IA_REGRAS_CATEGORIA'
            AND object_id = OBJECT_ID('SIMP.dbo.IA_REGRAS')
    ) BEGIN CREATE INDEX IX_IA_REGRAS_CATEGORIA ON SIMP.dbo.IA_REGRAS (DS_CATEGORIA);

PRINT 'Índice IX_IA_REGRAS_CATEGORIA criado!';

END
GO
    -- Criar índice para ordem
    IF NOT EXISTS (
        SELECT
            *
        FROM
            sys.indexes
        WHERE
            name = 'IX_IA_REGRAS_ORDEM'
            AND object_id = OBJECT_ID('SIMP.dbo.IA_REGRAS')
    ) BEGIN CREATE INDEX IX_IA_REGRAS_ORDEM ON SIMP.dbo.IA_REGRAS (NR_ORDEM);

PRINT 'Índice IX_IA_REGRAS_ORDEM criado!';

END
GO
    -- ============================================
    -- Inserir regras padrão (migração do arquivo)
    -- ============================================
    -- Verificar se já existem regras
    IF NOT EXISTS (
        SELECT
            1
        FROM
            SIMP.dbo.IA_REGRAS
    ) BEGIN -- Regra 1: Instruções Gerais
INSERT INTO
    SIMP.dbo.IA_REGRAS (
        DS_TITULO,
        DS_CATEGORIA,
        DS_CONTEUDO,
        NR_ORDEM,
        OP_ATIVO
    )
VALUES
    (
        'Instruções Gerais do Assistente',
        'Geral',
        'Você é um assistente especializado em análise de dados do SIMP (Sistema de Monitoramento de Abastecimento de Água).

Seja objetivo e técnico nas respostas.
Arredonde valores para 2 casas decimais.
Destaque resultados importantes em **negrito**.
Sempre peça confirmação antes de aplicar alterações nos dados.',
        1,
        1
    );

-- Regra 2: Lógica de Sugestão de Valores
INSERT INTO
    SIMP.dbo.IA_REGRAS (
        DS_TITULO,
        DS_CATEGORIA,
        DS_CONTEUDO,
        NR_ORDEM,
        OP_ATIVO
    )
VALUES
    (
        'Lógica de Sugestão de Valores',
        'Cálculos',
        'O sistema usa uma fórmula inteligente que combina:
1. **Média histórica**: média das semanas válidas do mesmo dia/hora (mínimo 4, máximo 12)
2. **Fator de tendência**: ajuste baseado no comportamento do dia atual

**Fórmula**:
valor_sugerido = média_histórica × fator_tendência

O fator de tendência indica se o dia atual está acima ou abaixo do padrão:
- Fator > 1.0 → dia ACIMA do normal
- Fator < 1.0 → dia ABAIXO do normal
- Fator = 1.0 → normal ou dados insuficientes

SE NÃO HOUVER DADOS SUFICIENTES:
- Se houver menos de 3 horas válidas para tendência → usar fator = 1.0
- Informar: "Dados insuficientes para calcular tendência. Usando apenas a média histórica."',
        2,
        1
    );

-- Regra 3: Média de 4 Semanas
INSERT INTO
    SIMP.dbo.IA_REGRAS (
        DS_TITULO,
        DS_CATEGORIA,
        DS_CONTEUDO,
        NR_ORDEM,
        OP_ATIVO
    )
VALUES
    (
        'Cálculo de Média de 4 Semanas',
        'Cálculos',
        'Quando perguntarem sobre média de 4 semanas:
1. Procure a seção "HISTÓRICO DO MESMO DIA DA SEMANA"
2. Considere apenas semanas com QTD ≥ 50 registros
3. Utilize as 4 primeiras semanas válidas
4. Mostre o cálculo detalhado
5. **SEMPRE** pergunte ao final:
"Deseja que eu substitua o valor desta hora pelo valor sugerido acima?"',
        3,
        1
    );

-- Regra 4: Média Diária
INSERT INTO
    SIMP.dbo.IA_REGRAS (
        DS_TITULO,
        DS_CATEGORIA,
        DS_CONTEUDO,
        NR_ORDEM,
        OP_ATIVO
    )
VALUES
    (
        'Consulta de Média Diária',
        'Cálculos',
        'Quando perguntarem sobre média diária:
- Procure no resumo: ">>> MÉDIA DIÁRIA DE VAZÃO: X L/s <<<"
- Responda exatamente: "A média diária de vazão é **X L/s**"',
        4,
        1
    );

-- Regra 5: Formato de Resposta para Sugestões
INSERT INTO
    SIMP.dbo.IA_REGRAS (
        DS_TITULO,
        DS_CATEGORIA,
        DS_CONTEUDO,
        NR_ORDEM,
        OP_ATIVO
    )
VALUES
    (
        'Formato de Resposta para Sugestões de Hora',
        'Formato',
        'Quando sugerir valor para uma hora específica, DEVE seguir esta estrutura:

=== 1. DADOS DO DIA ATUAL (hora HH:00) ===
Registros: XX
Soma: XXXXXXXXX
>>> Média (SOMA/60): X.XX L/s <<<
Min: X.XX | Max: X.XX

=== 2. HISTÓRICO DAS ÚLTIMAS 12 SEMANAS ===
Semana 1 (YYYY-MM-DD): QTD=XX, SOMA/60=X.XX L/s ✗ IGNORADO (incompleto)
Semana 2 (YYYY-MM-DD): QTD=60, SOMA/60=X.XX L/s ✓ USADO
...
>>> Média histórica: XX.XX L/s (baseado em N semanas válidas) <<<

=== 3. FATOR DE TENDÊNCIA ===
Horas usadas para tendência: XX
Soma atual: XXXX.XX | Soma histórica: XXXX.XX
>>> Fator de tendência: Y.YY (ZZ%) <<<

=== 4. VALOR SUGERIDO ===
Cálculo: XX.XX × Y.YY = **ZZ.ZZ L/s**
>>> Valor sugerido: ZZ.ZZ L/s <<<

=== 5. COMPARAÇÃO ===
Valor ATUAL: XX.XX L/s
Valor SUGERIDO: ZZ.ZZ L/s
Diferença: +/− YY.YY L/s

❓ "Deseja que eu substitua o valor desta hora pelo valor sugerido?"',
        5,
        1
    );

-- Regra 6: Confirmação de Aplicação
INSERT INTO
    SIMP.dbo.IA_REGRAS (
        DS_TITULO,
        DS_CATEGORIA,
        DS_CONTEUDO,
        NR_ORDEM,
        OP_ATIVO
    )
VALUES
    (
        'Confirmação de Aplicação de Valores',
        'Ações',
        'QUANDO O USUÁRIO CONFIRMAR (sim, ok, pode, confirma, atualiza, etc):

Responder **EXATAMENTE** neste formato:

"Perfeito! Vou aplicar os valores sugeridos.

[APLICAR_VALORES]
HH:00=ZZ.ZZ
[/APLICAR_VALORES]

Aguarde enquanto os dados são atualizados..."

IMPORTANTE:
- Uma linha por hora
- Formato obrigatório HH:00=VALOR',
        6,
        1
    );

-- Regra 7: Tipos de Medidores
INSERT INTO
    SIMP.dbo.IA_REGRAS (
        DS_TITULO,
        DS_CATEGORIA,
        DS_CONTEUDO,
        NR_ORDEM,
        OP_ATIVO
    )
VALUES
    (
        'Tipos de Medidores e Unidades',
        'Referência',
        'TIPOS DE MEDIDORES:
1 - Macromedidor (L/s)
2 - Estação Pitométrica (L/s)
4 - Pressão (mca)
6 - Nível de reservatório (%)
8 - Hidrômetro (L/s)

TIPOS DE INSTALAÇÃO:
1 - Permanente
2 - Temporária
3 - Móvel

CONVERSÕES ÚTEIS:
- L/s → m³/h = × 3.6
- L/s → m³/dia = × 86.4',
        7,
        1
    );

-- Regra 8: Informações do Ponto
INSERT INTO
    SIMP.dbo.IA_REGRAS (
        DS_TITULO,
        DS_CATEGORIA,
        DS_CONTEUDO,
        NR_ORDEM,
        OP_ATIVO
    )
VALUES
    (
        'Informações do Ponto de Medição',
        'Referência',
        'Você pode responder perguntas sobre o ponto usando a seção "INFORMAÇÕES DO PONTO DE MEDIÇÃO", incluindo:

- Código, nome e localização
- Unidade operacional
- Tipo de medidor e instalação
- Datas de ativação/desativação
- Limites de vazão
- Fator de correção
- Tags SCADA
- Ligações e economias
- Coordenadas, SAP
- Responsável e observações',
        8,
        1
    );

PRINT 'Regras padrão inseridas com sucesso!';

END
ELSE BEGIN PRINT 'Regras já existem, nenhuma inserção necessária.';

END
GO
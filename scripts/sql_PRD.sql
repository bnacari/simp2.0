

ALTER TABLE [SIMP].[dbo].[PONTO_MEDICAO]
ADD 
    [COORDENADAS] VARCHAR(200) NULL,
    [LOC_INST_SAP] VARCHAR(200) NULL
GO


ALTER TABLE [SIMP].[dbo].MACROMEDIDOR
ADD 
    [PROT_COMUN] VARCHAR(200) NULL;
GO

ALTER TABLE SIMP.dbo.REGISTRO_MANUTENCAO 
DROP CONSTRAINT FK_REGISTRO_TECNICO_X_TECNICO;

ALTER TABLE SIMP.dbo.ENTIDADE_TIPO
ADD DT_EXC_ENTIDADE_TIPO DATETIME NULL;

ALTER TABLE SIMP.dbo.[ENTIDADE_VALOR_ITEM]
ADD ID_OPERACAO tinyint NULL;

-- Popular ID_OPERACAO baseado na FORMULA_ITEM_PONTO_MEDICAO
UPDATE E
SET E.ID_OPERACAO = F.ID_OPERACAO
FROM SIMP.dbo.ENTIDADE_VALOR_ITEM E
INNER JOIN SIMP.dbo.FORMULA_ITEM_PONTO_MEDICAO F 
    ON F.CD_ENTIDADE_VALOR_ITEM = E.CD_CHAVE
WHERE F.ID_OPERACAO IS NOT NULL;


-- ============================================
-- Script para adicionar campo de ordem nos itens
-- Executar apenas uma vez no banco SIMP
-- ============================================

-- Verificar se a coluna já existe antes de adicionar
IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'dbo' 
    AND TABLE_NAME = 'ENTIDADE_VALOR_ITEM' 
    AND COLUMN_NAME = 'NR_ORDEM'
)
BEGIN
    ALTER TABLE SIMP.dbo.ENTIDADE_VALOR_ITEM 
    ADD NR_ORDEM INT NULL;
    
    PRINT 'Coluna NR_ORDEM adicionada com sucesso!';
END
ELSE
BEGIN
    PRINT 'Coluna NR_ORDEM já existe.';
END
GO

-- Atualizar registros existentes com ordem baseada no ID
UPDATE EVI
SET NR_ORDEM = SubQuery.RowNum
FROM SIMP.dbo.ENTIDADE_VALOR_ITEM EVI
INNER JOIN (
    SELECT 
        CD_CHAVE,
        ROW_NUMBER() OVER (PARTITION BY CD_ENTIDADE_VALOR ORDER BY CD_CHAVE) AS RowNum
    FROM SIMP.dbo.ENTIDADE_VALOR_ITEM
) SubQuery ON EVI.CD_CHAVE = SubQuery.CD_CHAVE
WHERE EVI.NR_ORDEM IS NULL;

PRINT 'Ordem inicial definida para registros existentes.';
GO

-- ============================================
-- ============================================


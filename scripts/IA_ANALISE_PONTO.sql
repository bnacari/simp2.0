/**
 * SIMP 2.0 - Tabela IA_ANALISE_PONTO
 *
 * Armazena resumos gerados por IA para cada ponto de medicao,
 * cruzando observacoes historicas, vizinhanca do flowchart e metricas ML.
 *
 * Logica: 1 registro ativo (ID_SITUACAO=1) por ponto. Ao reanalisar,
 * faz UPDATE no registro existente (nao cria novo).
 *
 * @author  Bruno - CESAN
 * @date    2026-03
 */

-- ============================================================
-- TABELA PRINCIPAL
-- ============================================================
CREATE TABLE [dbo].[IA_ANALISE_PONTO] (
    CD_CHAVE                INT IDENTITY(1,1) PRIMARY KEY,
    CD_PONTO_MEDICAO        INT NOT NULL,            -- FK para PONTO_MEDICAO
    DT_PERIODO_INICIO       DATE NOT NULL,            -- Inicio do periodo analisado
    DT_PERIODO_FIM          DATE NOT NULL,            -- Fim do periodo analisado
    DS_ANALISE              VARCHAR(300) NOT NULL,     -- Texto resumido da IA (~200 chars)
    DS_PROVIDER             VARCHAR(30) NULL,          -- deepseek, groq, gemini
    QTD_OBSERVACOES         INT DEFAULT 0,             -- Quantas DS_OBSERVACAO foram analisadas
    QTD_VIZINHOS_ANOMALOS   INT DEFAULT 0,             -- Vizinhos com anomalias no periodo
    CD_USUARIO_SOLICITANTE  INT NULL,                  -- Quem pediu (NULL = batch)
    DT_GERACAO              DATETIME DEFAULT GETDATE(),
    ID_SITUACAO             TINYINT DEFAULT 1,         -- 1=ativo, 2=inativo

    CONSTRAINT FK_ANALISE_PONTO FOREIGN KEY (CD_PONTO_MEDICAO)
        REFERENCES PONTO_MEDICAO (CD_PONTO_MEDICAO)
);

-- Indice: busca rapida por ponto (1 analise ativa por ponto)
CREATE UNIQUE INDEX IX_ANALISE_PONTO_ATIVO
    ON IA_ANALISE_PONTO (CD_PONTO_MEDICAO)
    WHERE ID_SITUACAO = 1;

-- Indice: ordenacao por data de geracao
CREATE INDEX IX_ANALISE_PONTO_DATA
    ON IA_ANALISE_PONTO (DT_GERACAO DESC);

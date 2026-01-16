# SIMP - Sistema de Análise de Medição
## Documentação de Tabelas e Views

---

## 1. TABELAS DE DADOS BRUTOS

### 1.1 MEDICAO_RESUMO_HORARIO

**Descrição:** Armazena o resumo das medições agregadas por hora. Contém 24 registros por ponto de medição por dia.

**Atualização:** Diariamente via `SP_PROCESSAR_MEDICAO_DIARIA`

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `CD_CHAVE` | INT | Chave primária (auto-incremento) |
| `CD_PONTO_MEDICAO` | INT | Código do ponto de medição |
| `DT_HORA` | DATETIME | Data/hora truncada (ex: 2025-01-14 08:00:00) |
| `NR_HORA` | TINYINT | Número da hora (0-23) |
| `ID_TIPO_MEDIDOR` | INT | Tipo: 1=Macro, 2=Pitométrica, 4=Pressão, 6=Nível, 8=Hidrômetro |
| `QTD_REGISTROS` | INT | Quantidade de registros na hora (esperado: 60) |
| `QTD_ESPERADA` | INT | Quantidade esperada (default: 60) |
| `QTD_ZEROS` | INT | Quantidade de leituras zero |
| `QTD_VALORES_DISTINTOS` | INT | Valores únicos (1 = sensor travado) |
| `VL_MEDIA` | DECIMAL(18,4) | Média dos valores na hora |
| `VL_MIN` | DECIMAL(18,4) | Valor mínimo na hora |
| `VL_MAX` | DECIMAL(18,4) | Valor máximo na hora |
| `VL_SOMA` | DECIMAL(18,4) | Soma dos valores na hora |
| `VL_PRIMEIRO` | DECIMAL(18,4) | Primeiro valor da hora |
| `VL_ULTIMO` | DECIMAL(18,4) | Último valor da hora |
| `VL_VARIACAO_MAX` | DECIMAL(18,4) | Maior variação absoluta entre leituras |
| `VL_VARIACAO_PERC_MAX` | DECIMAL(18,4) | Maior variação percentual (detecta spikes) |
| `FL_VALOR_CONSTANTE` | BIT | Flag: sensor travado |
| `FL_VALOR_NEGATIVO` | BIT | Flag: valor negativo detectado |
| `FL_FORA_FAIXA` | BIT | Flag: valor fora dos limites |
| `FL_SPIKE` | BIT | Flag: salto abrupto (>200%) |
| `FL_ZEROS_SUSPEITOS` | BIT | Flag: zeros quando não deveria |
| `FL_TRATADO` | BIT | Flag: hora teve tratamento manual |
| `QTD_TRATADOS` | INT | Quantidade de registros tratados |
| `FL_ANOMALIA` | BIT | Flag geral: possui alguma anomalia |
| `DS_TIPO_ANOMALIA` | VARCHAR(500) | Descrição das anomalias detectadas |
| `VL_MEDIA_HISTORICA` | DECIMAL(18,4) | Média histórica (últimas 4 semanas, mesmo dia/hora) |
| `DT_PROCESSAMENTO` | DATETIME | Data/hora do processamento |

**Índices:**
- `PK_MEDICAO_RESUMO_HORARIO` - Chave primária (CD_CHAVE)
- `UK_MEDICAO_RESUMO_HORARIO` - Único (CD_PONTO_MEDICAO, DT_HORA)
- `IX_RESUMO_HORARIO_DATA` - Por data
- `IX_RESUMO_HORARIO_PONTO` - Por ponto
- `IX_RESUMO_HORARIO_ANOMALIA` - Por anomalia

---

### 1.2 MEDICAO_RESUMO_DIARIO

**Descrição:** Armazena o resumo consolidado do dia. Contém 1 registro por ponto de medição por dia.

**Atualização:** Diariamente via `SP_PROCESSAR_MEDICAO_DIARIA`

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `CD_CHAVE` | INT | Chave primária (auto-incremento) |
| `CD_PONTO_MEDICAO` | INT | Código do ponto de medição |
| `DT_MEDICAO` | DATE | Data da medição |
| `ID_TIPO_MEDIDOR` | INT | Tipo: 1=Macro, 2=Pitométrica, 4=Pressão, 6=Nível, 8=Hidrômetro |
| **Contagens** | | |
| `QTD_REGISTROS` | INT | Total de registros no dia (esperado: 1440) |
| `QTD_ESPERADA` | INT | Quantidade esperada (default: 1440) |
| `QTD_VALORES_DISTINTOS` | INT | Valores únicos no dia |
| `QTD_ZEROS` | INT | Quantidade de leituras zero |
| `QTD_HORAS_SEM_DADO` | INT | Horas sem nenhum registro (24 - horas com dado) |
| **Estatísticas** | | |
| `VL_MEDIA_DIARIA` | DECIMAL(18,4) | Média diária |
| `VL_MIN_DIARIO` | DECIMAL(18,4) | Valor mínimo do dia |
| `VL_MAX_DIARIO` | DECIMAL(18,4) | Valor máximo do dia |
| `VL_DESVIO_PADRAO` | DECIMAL(18,4) | Desvio padrão das médias horárias |
| `VL_SOMA_DIARIA` | DECIMAL(18,4) | Soma de todos os valores |
| **Limites** | | |
| `VL_LIMITE_INFERIOR` | DECIMAL(18,4) | Limite inferior aplicado |
| `VL_LIMITE_SUPERIOR` | DECIMAL(18,4) | Limite superior aplicado |
| `VL_CAPACIDADE_NOMINAL` | DECIMAL(18,4) | Capacidade nominal do equipamento |
| **Flags de Comunicação** | | |
| `FL_SEM_COMUNICACAO` | BIT | Menos de 50% dos registros esperados |
| `FL_VALOR_CONSTANTE` | BIT | Valor travado (≤5 valores distintos) |
| `FL_ZEROS_SUSPEITOS` | BIT | Zeros quando histórico não zera |
| **Flags Hidráulicas** | | |
| `FL_VALOR_NEGATIVO` | BIT | Valor negativo detectado |
| `FL_FORA_FAIXA` | BIT | Valor fora dos limites |
| `FL_SPIKE` | BIT | Saltos abruptos detectados |
| `FL_INCOMPATIVEL` | BIT | Incompatibilidade entre grandezas |
| `QTD_NEGATIVOS` | INT | Quantidade de horas com valor negativo |
| `QTD_FORA_FAIXA` | INT | Quantidade de horas fora da faixa |
| `QTD_SPIKES` | INT | Quantidade de horas com spike |
| **Flags Temporais** | | |
| `FL_PERFIL_ANOMALO` | BIT | Perfil diário anormal (linha reta) |
| `FL_DESVIO_HISTORICO` | BIT | Desvio > 50% do histórico |
| `VL_MEDIA_HISTORICA` | DECIMAL(18,4) | Média histórica (últimas 4 semanas) |
| `VL_DESVIO_HISTORICO` | DECIMAL(18,4) | Desvio percentual do histórico |
| **Tratamentos** | | |
| `ID_SITUACAO` | INT | 1=Normal, 2=Tratado |
| `QTD_TRATAMENTOS` | INT | Quantidade de registros tratados |
| `DS_HORAS_TRATADAS` | VARCHAR(100) | Horas tratadas (ex: "08,09,14") |
| **Score e Classificação** | | |
| `VL_SCORE_SAUDE` | INT | Score de saúde (0-10, maior=melhor) |
| `FL_ANOMALIA` | BIT | Flag geral: possui anomalia |
| `DS_ANOMALIAS` | VARCHAR(1000) | Descrição das anomalias |
| `DS_TIPO_PROBLEMA` | VARCHAR(50) | Classificação: COMUNICACAO, MEDIDOR, HIDRAULICO, VERIFICAR |
| **Controle** | | |
| `DT_PROCESSAMENTO` | DATETIME | Data/hora do processamento |

**Índices:**
- `PK_MEDICAO_RESUMO_DIARIO` - Chave primária (CD_CHAVE)
- `UK_MEDICAO_RESUMO_DIARIO` - Único (CD_PONTO_MEDICAO, DT_MEDICAO)
- `IX_RESUMO_DIARIO_DATA` - Por data
- `IX_RESUMO_DIARIO_PONTO` - Por ponto
- `IX_RESUMO_DIARIO_ANOMALIA` - Por anomalia
- `IX_RESUMO_DIARIO_SCORE` - Por score de saúde

---

### 1.3 LIMITES_PADRAO_TIPO_MEDIDOR

**Descrição:** Armazena os limites padrão por tipo de medidor (usado quando não há cadastro específico).

**Atualização:** Manual (configuração)

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `ID_TIPO_MEDIDOR` | INT | Chave primária - Tipo do medidor |
| `DS_TIPO_MEDIDOR` | VARCHAR(50) | Descrição do tipo |
| `DS_UNIDADE` | VARCHAR(10) | Unidade de medida |
| `VL_LIMITE_INFERIOR` | DECIMAL(18,4) | Limite inferior padrão |
| `VL_LIMITE_SUPERIOR` | DECIMAL(18,4) | Limite superior padrão |
| `VL_VARIACAO_MAX_PERC` | DECIMAL(18,4) | Variação máxima permitida (%) |
| `VL_ZEROS_MAX_PERC` | DECIMAL(18,4) | Percentual máximo de zeros aceitável |
| `DS_OBSERVACAO` | VARCHAR(500) | Observações |

**Valores padrão:**

| Tipo | Medidor | Unidade | Lim. Inf. | Lim. Sup. | Var. Máx. | Zeros Máx. |
|------|---------|---------|-----------|-----------|-----------|------------|
| 1 | Macromedidor | L/s | 0 | 500 | 200% | 25% |
| 2 | Estação Pitométrica | L/s | 0 | 300 | 200% | 25% |
| 4 | Medidor Pressão | mca | 0 | 80 | 50% | 10% |
| 6 | Nível Reservatório | % | 0 | 100 | 30% | 5% |
| 8 | Hidrômetro | L/s | 0 | 50 | 200% | 25% |

---

## 2. VIEWS DE DADOS FORMATADOS

### 2.1 VW_DASHBOARD_RESUMO_GERAL

**Descrição:** Visão consolidada para os cards principais do dashboard. Retorna 1 registro com totais dos últimos 7 dias.

**Uso:** Dashboard - Cards superiores

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `TOTAL_PONTOS` | INT | Total de pontos monitorados |
| `TOTAL_MEDICOES` | INT | Total de medições no período |
| `SCORE_MEDIO` | DECIMAL(5,2) | Score médio de saúde (0-10) |
| `SCORE_MINIMO` | INT | Menor score do período |
| `PONTOS_SAUDAVEIS` | INT | Pontos com score ≥ 8 |
| `PONTOS_ALERTA` | INT | Pontos com score 5-7 |
| `PONTOS_CRITICOS` | INT | Pontos com score < 5 |
| `PROB_COMUNICACAO` | INT | Problemas de comunicação |
| `PROB_MEDIDOR` | INT | Problemas de medidor |
| `PROB_HIDRAULICO` | INT | Problemas hidráulicos |
| `TOTAL_ANOMALIAS` | INT | Total de anomalias detectadas |
| `PONTOS_TRATADOS` | INT | Pontos com tratamento manual |
| `DATA_INICIO` | DATE | Data inicial do período |
| `DATA_FIM` | DATE | Data final do período |

**Query:**
```sql
SELECT * FROM VW_DASHBOARD_RESUMO_GERAL;
```

---

### 2.2 VW_PONTOS_POR_SCORE_SAUDE

**Descrição:** Ranking de pontos ordenados por score de saúde, com detalhamento de flags por tipo de problema.

**Uso:** Dashboard - Lista de pontos, Ranking de criticidade

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `CD_PONTO_MEDICAO` | INT | Código do ponto |
| `NOME_PONTO` | VARCHAR | Nome do ponto de medição |
| `ID_TIPO_MEDIDOR` | INT | Código do tipo de medidor |
| `TIPO_MEDIDOR` | VARCHAR | Descrição do tipo (ex: "M - Macromedidor") |
| `SCORE_MEDIO` | DECIMAL | Score médio no período |
| `SCORE_MINIMO` | INT | Menor score no período |
| `STATUS_SAUDE` | VARCHAR | Classificação: SAUDAVEL, ALERTA, CRITICO |
| `COR_STATUS` | VARCHAR | Cor hexadecimal (#22c55e, #f59e0b, #dc2626) |
| `ICONE_STATUS` | VARCHAR | Ícone sugerido (checkmark-circle, warning, alert-circle) |
| `DIAS_ANALISADOS` | INT | Quantidade de dias no período |
| `DIAS_SEM_COMUNICACAO` | INT | Dias com problema de comunicação |
| `DIAS_VALOR_CONSTANTE` | INT | Dias com valor travado |
| `DIAS_VALOR_NEGATIVO` | INT | Dias com valor negativo |
| `DIAS_FORA_FAIXA` | INT | Dias fora dos limites |
| `DIAS_COM_SPIKE` | INT | Dias com saltos abruptos |
| `DIAS_ZEROS_SUSPEITOS` | INT | Dias com zeros suspeitos |
| `DIAS_COM_ANOMALIA` | INT | Total de dias com alguma anomalia |
| `DIAS_TRATADOS` | INT | Dias com tratamento manual |
| `MEDIA_PERIODO` | DECIMAL | Média dos valores no período |
| `REGISTROS_MEDIO` | DECIMAL | Média de registros por dia |
| `DESVIO_HISTORICO_MEDIO` | DECIMAL | Desvio médio do histórico (%) |

**Query:**
```sql
-- Todos os pontos
SELECT * FROM VW_PONTOS_POR_SCORE_SAUDE ORDER BY SCORE_MEDIO;

-- Apenas críticos
SELECT * FROM VW_PONTOS_POR_SCORE_SAUDE WHERE STATUS_SAUDE = 'CRITICO';

-- Apenas com problema de comunicação
SELECT * FROM VW_PONTOS_POR_SCORE_SAUDE WHERE DIAS_SEM_COMUNICACAO > 0;
```

---

### 2.3 VW_ANOMALIAS_RECENTES

**Descrição:** Lista de anomalias detectadas nos últimos 7 dias com detalhes e status de tratamento.

**Uso:** Dashboard - Lista de anomalias, Gestão de pendências

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `CD_PONTO_MEDICAO` | INT | Código do ponto |
| `NOME_PONTO` | VARCHAR | Nome do ponto de medição |
| `DT_MEDICAO` | DATE | Data da anomalia |
| `ID_TIPO_MEDIDOR` | INT | Código do tipo de medidor |
| `DS_TIPO_PROBLEMA` | VARCHAR | Classificação: COMUNICACAO, MEDIDOR, HIDRAULICO, VERIFICAR |
| `DS_ANOMALIAS` | VARCHAR | Descrição detalhada das anomalias |
| `VL_SCORE_SAUDE` | INT | Score de saúde do dia |
| `VL_MEDIA_DIARIA` | DECIMAL | Média do dia |
| `VL_DESVIO_HISTORICO` | DECIMAL | Desvio do histórico (%) |
| `ID_SITUACAO` | INT | 1=Normal, 2=Tratado |
| `STATUS_TRATAMENTO` | VARCHAR | "Tratado" ou "Pendente" |

**Query:**
```sql
-- Todas as anomalias recentes
SELECT * FROM VW_ANOMALIAS_RECENTES ORDER BY DT_MEDICAO DESC;

-- Apenas pendentes
SELECT * FROM VW_ANOMALIAS_RECENTES WHERE STATUS_TRATAMENTO = 'Pendente';

-- Por tipo de problema
SELECT * FROM VW_ANOMALIAS_RECENTES WHERE DS_TIPO_PROBLEMA = 'COMUNICACAO';
```

---

### 2.4 VW_EVOLUCAO_DIARIA

**Descrição:** Série temporal com evolução diária do score e anomalias (últimos 30 dias).

**Uso:** Dashboard - Gráfico de evolução temporal

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `DT_MEDICAO` | DATE | Data |
| `TOTAL_PONTOS` | INT | Total de pontos processados |
| `SCORE_MEDIO` | DECIMAL(5,2) | Score médio do dia |
| `QTD_SAUDAVEIS` | INT | Pontos com score ≥ 8 |
| `QTD_ALERTA` | INT | Pontos com score 5-7 |
| `QTD_CRITICOS` | INT | Pontos com score < 5 |
| `TOTAL_ANOMALIAS` | INT | Total de anomalias no dia |
| `TOTAL_TRATAMENTOS` | INT | Total de tratamentos no dia |

**Query:**
```sql
-- Evolução completa
SELECT * FROM VW_EVOLUCAO_DIARIA ORDER BY DT_MEDICAO;

-- Para gráfico (últimos 7 dias)
SELECT * FROM VW_EVOLUCAO_DIARIA 
WHERE DT_MEDICAO >= DATEADD(DAY, -7, GETDATE())
ORDER BY DT_MEDICAO;
```

---

### 2.5 VW_PONTO_MEDICAO_LIMITES

**Descrição:** Consolida os limites de cada ponto de medição com hierarquia de fallback: Cadastro → Equipamento → Estatístico → Padrão.

**Uso:** Configuração, Auditoria de limites

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `CD_PONTO_MEDICAO` | INT | Código do ponto |
| `DS_NOME` | VARCHAR | Nome do ponto |
| `ID_TIPO_MEDIDOR` | INT | Tipo do medidor |
| `VL_LIMITE_INFERIOR` | DECIMAL | Limite inferior efetivo |
| `VL_LIMITE_SUPERIOR` | DECIMAL | Limite superior efetivo |
| `VL_CAPACIDADE_NOMINAL` | DECIMAL | Capacidade nominal do equipamento |
| `VL_VARIACAO_MAX_PERC` | DECIMAL | Variação máxima permitida (%) |
| `VL_ZEROS_MAX_PERC` | DECIMAL | Percentual máximo de zeros |
| `VL_MEDIA_HIST` | DECIMAL | Média histórica (30 dias) |
| `VL_DESVIO_HIST` | DECIMAL | Desvio padrão histórico |
| `VL_MIN_HIST` | DECIMAL | Mínimo histórico |
| `VL_MAX_HIST` | DECIMAL | Máximo histórico |
| `QTD_REGISTROS_HIST` | INT | Registros no histórico |
| `DS_ORIGEM_LIMITE_INF` | VARCHAR | Origem: CADASTRO, ESTATISTICO, PADRAO, DEFAULT |
| `DS_ORIGEM_LIMITE_SUP` | VARCHAR | Origem: CADASTRO, EQUIPAMENTO, ESTATISTICO, PADRAO, DEFAULT |
| `VL_FATOR_CORRECAO_VAZAO` | DECIMAL | Fator de correção |
| `VL_VAZAO_ESPERADA` | DECIMAL | Vazão esperada (macromedidores) |
| `FL_ATIVO` | BIT | Ponto ativo (1) ou desativado (0) |

**Query:**
```sql
-- Todos os pontos ativos
SELECT * FROM VW_PONTO_MEDICAO_LIMITES WHERE FL_ATIVO = 1;

-- Pontos usando limite estatístico
SELECT * FROM VW_PONTO_MEDICAO_LIMITES WHERE DS_ORIGEM_LIMITE_SUP = 'ESTATISTICO';
```

---

### 2.6 VW_PONTOS_SEM_CADASTRO

**Descrição:** Lista pontos que não possuem limites cadastrados manualmente (usando estatístico ou padrão).

**Uso:** Gestão de cadastro, Identificar configurações pendentes

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `CD_PONTO_MEDICAO` | INT | Código do ponto |
| `DS_NOME` | VARCHAR | Nome do ponto |
| `ID_TIPO_MEDIDOR` | INT | Tipo do medidor |
| `DS_TIPO_MEDIDOR` | VARCHAR | Descrição do tipo |
| `STATUS_CADASTRO` | VARCHAR | COMPLETO, ESTATISTICO ou PADRAO |
| `DS_ORIGEM_LIMITE_INF` | VARCHAR | Origem do limite inferior |
| `DS_ORIGEM_LIMITE_SUP` | VARCHAR | Origem do limite superior |
| `VL_LIMITE_INFERIOR` | DECIMAL | Limite inferior atual |
| `VL_LIMITE_SUPERIOR` | DECIMAL | Limite superior atual |
| `VL_CAPACIDADE_NOMINAL` | DECIMAL | Capacidade nominal |
| `VL_MEDIA_HIST` | DECIMAL | Média histórica |
| `VL_DESVIO_HIST` | DECIMAL | Desvio padrão histórico |
| `QTD_REGISTROS_HIST` | INT | Registros no histórico |
| `DS_SUGESTAO_LIMITES` | VARCHAR | Sugestão de limites baseada no histórico |

**Query:**
```sql
-- Todos os pontos sem cadastro completo
SELECT * FROM VW_PONTOS_SEM_CADASTRO;

-- Pontos usando limites padrão (prioridade para cadastrar)
SELECT * FROM VW_PONTOS_SEM_CADASTRO WHERE STATUS_CADASTRO = 'PADRAO';

-- Resumo por status
SELECT STATUS_CADASTRO, COUNT(*) AS QTD FROM VW_PONTOS_SEM_CADASTRO GROUP BY STATUS_CADASTRO;
```

---

## 3. STORED PROCEDURES

### 3.1 SP_PROCESSAR_MEDICAO_DIARIA

**Descrição:** Processa os dados brutos da tabela `REGISTRO_VAZAO_PRESSAO` e alimenta as tabelas `MEDICAO_RESUMO_HORARIO` e `MEDICAO_RESUMO_DIARIO`.

**Frequência:** Diária (após integração D-1)

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `@DT_PROCESSAMENTO` | DATE | Data a processar (default: D-1) |

**Execução:**
```sql
-- Processar D-1 (padrão)
EXEC SP_PROCESSAR_MEDICAO_DIARIA;

-- Processar data específica
EXEC SP_PROCESSAR_MEDICAO_DIARIA @DT_PROCESSAMENTO = '2025-01-14';

-- Processar range de datas
DECLARE @DATA DATE = '2025-01-01';
WHILE @DATA <= '2025-01-15'
BEGIN
    EXEC SP_PROCESSAR_MEDICAO_DIARIA @DT_PROCESSAMENTO = @DATA;
    SET @DATA = DATEADD(DAY, 1, @DATA);
END
```

---

### 3.2 SP_CONTEXTO_IA

**Descrição:** Gera texto formatado com contexto para análise por Inteligência Artificial.

**Frequência:** Sob demanda (quando usuário solicita análise)

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `@DIAS_ANALISE` | INT | Dias para análise (default: 7) |

**Execução:**
```sql
-- Contexto dos últimos 7 dias
EXEC SP_CONTEXTO_IA;

-- Contexto dos últimos 30 dias
EXEC SP_CONTEXTO_IA @DIAS_ANALISE = 30;
```

**Retorno:** Texto formatado contendo:
- Resumo geral (pontos, score médio, anomalias)
- Classificação de problemas (comunicação, medidor, hidráulico)
- Lista de pontos críticos (score < 5)

---

## 4. SCORE DE SAÚDE

### 4.1 Cálculo do Score

O score de saúde varia de 0 a 10, onde **maior é melhor**.

**Fórmula:**
```
Score = 10
  - 3 se FL_SEM_COMUNICACAO = 1
  - 2 se FL_VALOR_CONSTANTE = 1
  - 2 se FL_VALOR_NEGATIVO = 1
  - 2 se FL_FORA_FAIXA = 1
  - 1 se FL_ZEROS_SUSPEITOS = 1
  - 1 se FL_SPIKE = 1
  - 1 se FL_PERFIL_ANOMALO = 1
  - 1 se FL_DESVIO_HISTORICO = 1
```

### 4.2 Classificação

| Score | Status | Cor | Ação Recomendada |
|-------|--------|-----|------------------|
| 8-10 | 🟢 SAUDAVEL | #22c55e | Monitoramento normal |
| 5-7 | 🟡 ALERTA | #f59e0b | Verificar e acompanhar |
| 0-4 | 🔴 CRITICO | #dc2626 | Intervenção urgente |

---

## 5. CLASSIFICAÇÃO DE PROBLEMAS

| DS_TIPO_PROBLEMA | Causa Provável | Flags Relacionadas |
|------------------|----------------|-------------------|
| `COMUNICACAO` | Falha de rádio, bateria, datalogger | FL_SEM_COMUNICACAO |
| `MEDIDOR` | Sensor travado, conversor A/D defeituoso | FL_VALOR_CONSTANTE, FL_PERFIL_ANOMALO |
| `HIDRAULICO` | Configuração errada, limites incorretos | FL_VALOR_NEGATIVO, FL_FORA_FAIXA, FL_SPIKE |
| `VERIFICAR` | Requer análise adicional | FL_ZEROS_SUSPEITOS, FL_DESVIO_HISTORICO |

---

## 6. HIERARQUIA DE LIMITES

Quando o limite não está cadastrado, o sistema usa fallback automático:

```
1º CADASTRO      → VL_LIMITE_SUPERIOR_VAZAO (tabela PONTO_MEDICAO)
       ↓ (se NULL)
2º EQUIPAMENTO   → VL_CAPACIDADE_NOMINAL (tabela MACROMEDIDOR/HIDROMETRO)
       ↓ (se NULL)
3º ESTATÍSTICO   → Média ± 3 desvios (últimos 30 dias)
       ↓ (se sem histórico)
4º PADRÃO        → Tabela LIMITES_PADRAO_TIPO_MEDIDOR
```

---

## 7. FLUXO DE DADOS

```
┌─────────────────────────────────────────────────────────────┐
│  REGISTRO_VAZAO_PRESSAO (~1440 registros/ponto/dia)         │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
              ┌───────────────────────────────┐
              │  SP_PROCESSAR_MEDICAO_DIARIA  │
              │  (Execução diária D-1)        │
              └───────────────────────────────┘
                              │
              ┌───────────────┴───────────────┐
              ▼                               ▼
┌─────────────────────────┐     ┌─────────────────────────┐
│ MEDICAO_RESUMO_HORARIO  │     │ MEDICAO_RESUMO_DIARIO   │
│ (24 registros/ponto/dia)│     │ (1 registro/ponto/dia)  │
└─────────────────────────┘     └─────────────────────────┘
              │                               │
              └───────────────┬───────────────┘
                              ▼
              ┌───────────────────────────────┐
              │  VIEWS DO DASHBOARD           │
              │  (Consultas em tempo real)    │
              └───────────────────────────────┘
                              │
              ┌───────────────┴───────────────┐
              ▼                               ▼
┌─────────────────────────┐     ┌─────────────────────────┐
│  Dashboard (UI)         │     │  SP_CONTEXTO_IA         │
│  Cards, Gráficos, Lista │     │  (Análise por IA)       │
└─────────────────────────┘     └─────────────────────────┘
```

---

**Documento gerado em:** Janeiro/2025  
**Sistema:** SIMP - Sistema Integrado de Macromedição e Pitometria  
**Versão:** 2.0
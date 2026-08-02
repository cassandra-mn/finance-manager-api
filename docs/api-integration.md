# Guia de integração da API — Finance Manager

Este documento descreve os recursos disponíveis na API (`/api/v1`) para integração do frontend.

## Autenticação

A API usa Laravel Sanctum com token Bearer.

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/google`
- `POST /api/v1/auth/logout` *(autenticado)*
- `GET /api/v1/auth/me` *(autenticado)*

Todas as rotas abaixo exigem o header `Authorization: Bearer <token>` e operam isoladas por usuário: um recurso que pertence a outro usuário sempre retorna **404**, nunca 403.

### Login com Google (`POST /auth/google`)

Recebe o ID token (JWT) emitido pelo [Google Identity Services](https://developers.google.com/identity/gsi/web) no frontend e devolve o mesmo formato de resposta de `/auth/login`/`/auth/register`.

```json
POST /api/v1/auth/google

{ "credential": "<id_token JWT emitido pelo Google>" }
```

- O backend valida a assinatura do token contra as chaves públicas do Google (`https://www.googleapis.com/oauth2/v3/certs`, cacheadas por 6h), o emissor (`iss`) e a audiência (`aud`, que deve bater com a env `GOOGLE_CLIENT_ID`).
- Se já existir uma conta com o `email` do token, ela é vinculada ao Google (`google_id` preenchido) e usada para login — não cria conta duplicada.
- Se não existir, uma conta nova é criada (retorna `201`; contas existentes retornam `200`, mesmo comportamento automático do `JsonResource` usado em `/auth/register`).
- `credential` ausente, expirado, com assinatura inválida ou audiência incorreta retorna `422` com erro no campo `credential`.

## Recursos existentes (resumo)

- **Contas** (`/api/v1/accounts`) — contas financeiras do usuário.
- **Categorias** (`/api/v1/categories`) — categorias de receita/despesa do usuário.
- **Transações** (`/api/v1/transactions`) — lançamentos financeiros (`pay`, `cancel`, filtros e paginação).

## Recorrências (`/api/v1/recurrences`)

> **Importante:** esta feature implementa o **CRUD da regra de recorrência** e a **geração automática de transações** a partir dela. Dashboard, notificações, parcelamentos e relatórios **não fazem parte desta etapa**.

Uma recorrência representa uma regra que descreve uma receita ou despesa recorrente (ex.: salário mensal, aluguel mensal, assinatura mensal, academia semanal, pagamento quinzenal, seguro anual). Ela pertence ao usuário autenticado, referencia uma conta (obrigatória) e opcionalmente uma categoria.

### Geração automática de transações

Toda recorrência **ativa** (`is_active = true`, não pausada, não excluída) gera transações **pendentes** automaticamente, uma por data de ocorrência, de acordo com sua `frequency`. Pontos importantes para o frontend:

- **Onde aparecem:** as transações geradas são lançamentos normais e aparecem no endpoint já existente `GET /api/v1/transactions`, com os mesmos filtros e paginação. Não há um endpoint separado para "transações de recorrência".
- **`recurrence_id`:** toda transação gerada a partir de uma regra retorna `recurrence_id` (o `id` da recorrência de origem) no payload de `GET /transactions`. Transações criadas manualmente (`POST /transactions`) continuam com `recurrence_id = null`.
- **Status inicial:** toda ocorrência é criada com `status = pending`. Não há pagamento automático — o usuário confirma o pagamento normalmente via `POST /transactions/{id}/pay`.
- **Janela de geração:** o backend gera ocorrências **futuras** dentro de uma janela configurável (`FINANCE_RECURRENCES_GENERATION_DAYS`, padrão 60 dias) a partir da data atual — assim uma transação não aparece somente no dia exato do vencimento. Recorrências pausadas, encerradas (`end_date` já ultrapassada) ou excluídas (soft delete) não geram novas ocorrências.
- **Quem gera:** a geração acontece **apenas no backend**, via um processo agendado (scheduler diário) que roda o comando `finance:generate-recurring-transactions`. **Não existe endpoint público para forçar a geração manual.** Rodar o comando (ou o scheduler) mais de uma vez nunca duplica ocorrências — é seguro por design (idempotência garantida tanto na aplicação quanto por uma constraint única no banco).
- **Dia do mês inexistente:** uma recorrência `monthly` no dia 31 cai no último dia dos meses menores (ex.: 31/01 → 28 ou 29/02 → 31/03 — sem "ficar presa" em 28 nos meses seguintes). O mesmo vale para uma recorrência `yearly` em 29/02: em anos não bissextos ela cai em 28/02, voltando para 29/02 no próximo ano bissexto.
- **Campos herdados:** a transação gerada herda da regra: conta, categoria (quando houver), `type`, `entry_type`, `description`, `amount_cents`, `notes` e a data de ocorrência (como `due_date`).
- A API de recorrência (`GET /recurrences`) não expõe, por ora, campos como "próxima ocorrência gerada" ou "total de transações geradas" além dos já documentados abaixo (`next_due_date` já existente reflete o avanço da regra após cada execução do gerador).

### Enum `frequency`

| Valor | Label (pt-BR) |
| --- | --- |
| `weekly` | Semanal |
| `fortnightly` | Quinzenal |
| `monthly` | Mensal |
| `yearly` | Anual |

### Campos da regra

| Campo | Tipo | Observações |
| --- | --- | --- |
| `account_id` | int | Obrigatório. Deve pertencer ao usuário autenticado. |
| `category_id` | int\|null | Opcional. Se informado, deve pertencer ao usuário autenticado e ter `type` compatível com o `type` da recorrência. |
| `type` | `income`\|`expense` | Obrigatório. |
| `entry_type` | `fixed`\|`variable` | Obrigatório. **`single` não é permitido** para uma regra recorrente. |
| `description` | string | Obrigatório. |
| `amount_cents` | int | Obrigatório. Inteiro positivo (nunca float/double). |
| `frequency` | enum | Obrigatório. Ver tabela acima. |
| `start_date` | date | Obrigatório. |
| `next_due_date` | date | Obrigatório. Não pode ser anterior a `start_date`. |
| `end_date` | date\|null | Opcional. Quando informado, não pode ser anterior a `start_date`. |
| `notes` | string\|null | Opcional. |
| `is_active` | bool | Somente leitura no payload de criação/atualização — controlado pelos endpoints `pause`/`resume`. |

### Endpoints

Todos exigem autenticação (`Authorization: Bearer <token>`).

- `GET /api/v1/recurrences` — lista as recorrências do usuário autenticado (array simples, sem paginação — mesmo padrão usado por `accounts` e `categories`).
- `POST /api/v1/recurrences` — cria uma nova regra de recorrência.
- `GET /api/v1/recurrences/{id}` — exibe uma regra.
- `PATCH /api/v1/recurrences/{id}` — atualiza campos da regra (não altera `is_active`).
- `DELETE /api/v1/recurrences/{id}` — remove a regra (soft delete — o histórico é preservado).
- `POST /api/v1/recurrences/{id}/pause` — pausa uma regra ativa (`is_active` → `false`). Retorna `422` se a regra já estiver pausada.
- `POST /api/v1/recurrences/{id}/resume` — retoma uma regra pausada (`is_active` → `true`). Retorna `422` se a regra já estiver ativa.

### Filtros de listagem (`GET /recurrences`)

Combináveis via query string:

| Filtro | Valores |
| --- | --- |
| `account_id` | id da conta |
| `category_id` | id da categoria |
| `type` | `income` \| `expense` |
| `frequency` | `weekly` \| `fortnightly` \| `monthly` \| `yearly` |
| `is_active` | `true` \| `false` |
| `search` | busca case-insensitive por `description` |

Exemplo: `GET /api/v1/recurrences?type=expense&frequency=monthly&is_active=true`

### Payload de criação

```json
POST /api/v1/recurrences

{
  "account_id": 1,
  "category_id": 3,
  "type": "expense",
  "entry_type": "fixed",
  "description": "Aluguel",
  "amount_cents": 150000,
  "frequency": "monthly",
  "start_date": "2026-08-05",
  "next_due_date": "2026-08-05",
  "end_date": null,
  "notes": "Contrato residencial"
}
```

### Resposta

```json
{
  "id": 1,
  "account": {
    "id": 1,
    "name": "Conta Corrente",
    "type": "checking",
    "type_label": "Conta Corrente",
    "initial_balance_cents": 0,
    "current_balance_cents": 0,
    "color": null,
    "is_active": true,
    "created_at": "2026-07-23T00:00:00.000000Z",
    "updated_at": "2026-07-23T00:00:00.000000Z"
  },
  "category": {
    "id": 3,
    "name": "Moradia",
    "type": "expense",
    "type_label": "Despesa",
    "color": null,
    "icon": null,
    "created_at": "2026-07-23T00:00:00.000000Z",
    "updated_at": "2026-07-23T00:00:00.000000Z"
  },
  "type": "expense",
  "type_label": "Despesa",
  "entry_type": "fixed",
  "entry_type_label": "Fixo",
  "description": "Aluguel",
  "amount_cents": 150000,
  "frequency": "monthly",
  "frequency_label": "Mensal",
  "start_date": "2026-08-05",
  "next_due_date": "2026-08-05",
  "end_date": null,
  "is_active": true,
  "notes": "Contrato residencial",
  "created_at": "2026-07-23T00:00:00.000000Z",
  "updated_at": "2026-07-23T00:00:00.000000Z"
}
```

`category` é `null` quando a regra não possui categoria associada.

### Regras de integridade

- Toda recorrência pertence ao usuário autenticado; não é possível consultar, alterar, pausar, retomar ou excluir recorrências de outro usuário (retorna `404`).
- A conta informada deve pertencer ao usuário autenticado.
- A categoria, quando informada, deve pertencer ao usuário autenticado e ter `type` compatível com o `type` da recorrência.
- `amount_cents` deve ser um inteiro positivo.
- `entry_type` aceita apenas `fixed` ou `variable` — `single` é rejeitado.
- `end_date` (quando enviada) e `next_due_date` não podem ser anteriores a `start_date`.
- Pausar uma regra não a remove nem apaga seu histórico — apenas marca `is_active = false`, o que também interrompe a geração automática de novas transações até que a regra seja retomada.
- Excluir uma regra é soft delete: o registro não aparece mais nas listagens/consultas, mas não é removido fisicamente.

## Orçamentos (`/api/v1/budgets`)

> **Importante:** o **backend é a fonte oficial dos cálculos de orçamento** (gasto acumulado, restante, percentual de uso e status). O frontend não deve recalcular esses valores localmente — deve exibir exatamente o que a API retorna. Esta feature implementa apenas orçamento **mensal**, por categoria. Dashboard, notificações/alertas, recorrência automática de orçamento, parcelamentos e relatórios adicionais **não fazem parte desta etapa**.

Um orçamento representa um limite de gasto mensal para uma categoria de despesa (`type = expense`). Categorias de receita (`income`) não podem ter orçamento.

### Campos do orçamento

| Campo | Tipo | Observações |
| --- | --- | --- |
| `category_id` | int | Obrigatório. Deve pertencer ao usuário autenticado e ser uma categoria `expense`. |
| `amount_cents` | int | Obrigatório. Inteiro positivo em centavos (nunca float/double). |
| `reference_month` | int | Obrigatório. Entre `1` e `12`. |
| `reference_year` | int | Obrigatório. Ano com 4 dígitos. |

Não envie `user_id` no payload — ele é sempre resolvido a partir do usuário autenticado.

Só pode existir **um orçamento por usuário + categoria + mês + ano de referência**. Um orçamento excluído (soft delete) não bloqueia a criação de um novo orçamento equivalente para o mesmo período.

### Endpoints

Todos exigem autenticação (`Authorization: Bearer <token>`).

- `GET /api/v1/budgets` — lista os orçamentos do usuário autenticado (array simples, sem paginação — mesmo padrão usado por `accounts`, `categories` e `recurrences`).
- `POST /api/v1/budgets` — cria um novo orçamento.
- `GET /api/v1/budgets/{id}` — exibe um orçamento.
- `PATCH /api/v1/budgets/{id}` — atualiza campos do orçamento.
- `DELETE /api/v1/budgets/{id}` — remove o orçamento (soft delete — o histórico é preservado).
- `GET /api/v1/budgets/status?reference_date=YYYY-MM-DD` — retorna o consumo calculado de cada orçamento no mês de referência, pronto para exibição.

### Filtros de listagem (`GET /budgets`)

Combináveis via query string:

| Filtro | Valores |
| --- | --- |
| `category_id` | id da categoria |
| `reference_month` | `1`–`12` |
| `reference_year` | ano com 4 dígitos |
| `reference_date` | `YYYY-MM-DD` — alternativa conveniente a `reference_month`/`reference_year`; quando enviado, tem prioridade sobre os dois |

A listagem **não é paginada** (mesmo padrão de `accounts`/`categories`/`recurrences`) — não há parâmetro `per_page`.

### Payload de criação

```json
POST /api/v1/budgets

{
  "category_id": 3,
  "amount_cents": 80000,
  "reference_month": 8,
  "reference_year": 2026
}
```

### Resposta do orçamento (CRUD)

```json
{
  "id": 1,
  "category": {
    "id": 3,
    "name": "Alimentação",
    "type": "expense",
    "type_label": "Despesa",
    "color": "#f97316",
    "icon": null,
    "created_at": "2026-07-23T00:00:00.000000Z",
    "updated_at": "2026-07-23T00:00:00.000000Z"
  },
  "amount_cents": 80000,
  "reference_month": 8,
  "reference_year": 2026,
  "created_at": "2026-07-23T00:00:00.000000Z",
  "updated_at": "2026-07-23T00:00:00.000000Z"
}
```

### Regras de cálculo do consumo (`GET /budgets/status`)

Para cada orçamento do mês de referência, `spent_cents` soma o `amount_cents` das transações que atendem **todas** as condições:

- pertencem ao usuário autenticado;
- têm a mesma `category_id` do orçamento;
- são do tipo `expense`;
- o `status` é **diferente** de `cancelled` — ou seja, `pending`, `paid` e `overdue` (que é apenas `pending` com `due_date` no passado) contam como comprometimento do orçamento; `cancelled` nunca entra no cálculo;
- o `due_date` está dentro do mês/ano de referência (do primeiro ao último dia do mês).

A partir disso:

- `remaining_cents = amount_cents - spent_cents` (pode ser negativo quando excedido).
- `usage_percentage = round(spent_cents / amount_cents * 100, 2)` — **apenas um valor de apresentação** (decimal), nunca a fonte de verdade. Toda comparação de limite (`status`) é feita com aritmética inteira em centavos, para não haver erro de arredondamento exatamente em 80% ou 100%.

### Definição de `status`

| Status | Condição exata |
| --- | --- |
| `safe` | `spent_cents * 100 < amount_cents * 80` (uso **abaixo** de 80%) |
| `warning` | `spent_cents * 100 >= amount_cents * 80` **e** `spent_cents <= amount_cents` (uso entre 80% e 100%, **ambos os limites incluídos**) |
| `exceeded` | `spent_cents > amount_cents` (uso **acima** de 100%) |

Ou seja: exatamente **80%** de uso é `warning` (não `safe`); exatamente **100%** de uso ainda é `warning` (não `exceeded`); qualquer valor acima de 100% é `exceeded`.

### Resposta de status

```json
GET /api/v1/budgets/status?reference_date=2026-08-15

{
  "reference_period": {
    "month": 8,
    "year": 2026,
    "from": "2026-08-01",
    "to": "2026-08-31"
  },
  "data": [
    {
      "id": 1,
      "category": {
        "id": 3,
        "name": "Alimentação",
        "type": "expense",
        "type_label": "Despesa",
        "color": "#f97316",
        "icon": null,
        "created_at": "2026-07-23T00:00:00.000000Z",
        "updated_at": "2026-07-23T00:00:00.000000Z"
      },
      "amount_cents": 80000,
      "spent_cents": 64500,
      "remaining_cents": 15500,
      "usage_percentage": 80.63,
      "status": "warning",
      "status_label": "Atenção"
    }
  ],
  "summary": {
    "total_budget_cents": 80000,
    "total_spent_cents": 64500,
    "total_remaining_cents": 15500,
    "safe_count": 0,
    "warning_count": 1,
    "exceeded_count": 0
  }
}
```

`reference_date` é opcional — quando omitido, usa a data atual do servidor. O campo `category` reaproveita o mesmo formato do `CategoryResource` usado em todo o restante da API (por isso inclui `type`/`type_label`/`created_at`/`updated_at`, além de `id`/`name`/`color`/`icon`).

### Regras de integridade

- Todo orçamento pertence ao usuário autenticado; não é possível consultar, editar ou excluir orçamento de outro usuário (retorna `404`).
- A categoria deve pertencer ao usuário autenticado e ser do tipo `expense` — categoria `income` é rejeitada.
- `amount_cents` deve ser um inteiro positivo.
- Não pode haver dois orçamentos ativos (não excluídos) para a mesma combinação usuário + categoria + `reference_month` + `reference_year`.
- Excluir um orçamento é soft delete: preserva o histórico e não bloqueia a criação futura de um orçamento equivalente para o mesmo período.

## Insights (`/api/v1/insights`)

> **Importante:** as seções de Recorrências e Orçamentos, acima, deferiram explicitamente "dashboard/relatórios" para uma etapa futura — esta é essa etapa, mas só para três análises específicas: **comparação de períodos + top categorias**, **detecção de gasto anômalo** e **projeção de estouro de orçamento**. Previsão de fluxo de caixa, detecção de recorrência não cadastrada e comparação/sugestões baseadas em dados agregados entre usuários **não fazem parte desta etapa** (ver "Fora de escopo" no fim desta seção).

Todos os endpoints exigem autenticação (`Authorization: Bearer <token>`) e operam apenas sobre os dados do usuário autenticado.

### Endpoints

- `GET /api/v1/insights/spending-summary` — compara receita/despesa do período atual com o período anterior equivalente, e retorna o ranking das categorias que mais pesaram no gasto.
- `GET /api/v1/insights/anomalies` — para cada categoria com gasto no período atual, compara com a média histórica dos períodos anteriores e sinaliza desvios acima de um limiar.
- `GET /api/v1/insights/budget-projection` — estende `GET /budgets/status` com uma projeção linear do gasto até o fim do mês.

### `GET /insights/spending-summary`

Parâmetros (query string, todos opcionais):

| Parâmetro | Tipo | Padrão |
| --- | --- | --- |
| `period` | `week`\|`fortnight`\|`month`\|`quarter`\|`year` | `month` |
| `reference_date` | `YYYY-MM-DD` | hoje |
| `top_categories` | int, entre 1 e 20 | `INSIGHTS_TOP_CATEGORIES` (padrão 5) |

```json
GET /api/v1/insights/spending-summary?period=month&reference_date=2026-08-15

{
  "reference_period": {
    "period": "month",
    "current": { "from": "2026-08-01", "to": "2026-08-31" },
    "previous": { "from": "2026-07-01", "to": "2026-07-31" }
  },
  "data": {
    "current": { "income_cents": 500000, "expense_cents": 320000 },
    "previous": { "income_cents": 480000, "expense_cents": 290000 },
    "delta": { "income_cents": 20000, "expense_cents": 30000, "income_percentage": 4.17, "expense_percentage": 10.34 },
    "top_categories": [
      { "category_id": 3, "category_name": "Alimentação", "category_color": "#f97316", "amount_cents": 120000, "percentage_of_total": 37.5 }
    ],
    "others_cents": 120000
  },
  "summary": { "total_expense_cents": 320000, "top_categories_count": 5 }
}
```

`income_percentage`/`expense_percentage` vêm `null` quando não há dado no período anterior para comparar (divisão por zero indefinida, não reportada como `0%` ou `100%`). `others_cents` soma o gasto de todas as categorias fora do top N.

### `GET /insights/anomalies`

| Parâmetro | Tipo | Padrão |
| --- | --- | --- |
| `period` | mesmo enum acima | `month` |
| `reference_date` | `YYYY-MM-DD` | hoje |
| `lookback_periods` | int, entre 1 e 12 | `INSIGHTS_ANOMALY_LOOKBACK_PERIODS` (padrão 3) |
| `threshold_percentage` | int, mínimo 1 | `INSIGHTS_ANOMALY_THRESHOLD_PERCENTAGE` (padrão 40) |

```json
GET /api/v1/insights/anomalies?lookback_periods=3&threshold_percentage=40

{
  "reference_period": {
    "period": "month",
    "current": { "from": "2026-08-01", "to": "2026-08-31" },
    "lookback_periods": 3,
    "historical_window": { "from": "2026-05-01", "to": "2026-07-31" }
  },
  "data": [
    { "category_id": 3, "category_name": "Alimentação", "category_color": "#f97316", "current_cents": 120000, "average_cents": 70000, "deviation_percentage": 71.43, "is_anomalous": true, "is_new_category": false }
  ],
  "summary": { "threshold_percentage": 40, "anomalies_count": 1, "new_categories_count": 0 }
}
```

`data` traz **todas** as categorias com gasto no período (não só as anômalas) — cada uma com a flag `is_anomalous`, mesmo padrão de "lista tudo com status" já usado em `budgets/status`. Uma categoria sem nenhum histórico nos períodos anteriores (`average_cents = 0`) mas com gasto agora vem com `is_new_category = true` e `deviation_percentage = null` (não há base para calcular desvio), e é sempre marcada `is_anomalous = true`.

### `GET /insights/budget-projection`

Aceita `reference_date` (`YYYY-MM-DD`, opcional, padrão hoje). A projeção **só é calculada para o mês corrente** — para qualquer outro mês, `reference_period.projection_applicable` vem `false` e os campos `projected_*`/`is_projected_to_exceed` vêm `null` (a resposta ainda traz o status normal de orçamento, igual a `budgets/status`).

```json
GET /api/v1/insights/budget-projection?reference_date=2026-08-15

{
  "reference_period": {
    "month": 8, "year": 2026, "from": "2026-08-01", "to": "2026-08-31",
    "days_in_period": 31, "days_elapsed": 15, "days_remaining": 16,
    "projection_applicable": true
  },
  "data": [
    {
      "id": 1,
      "category": { "id": 3, "name": "Alimentação", "type": "expense", "type_label": "Despesa", "color": "#f97316", "icon": null, "created_at": "...", "updated_at": "..." },
      "amount_cents": 80000, "spent_cents": 45000, "remaining_cents": 35000,
      "usage_percentage": 56.25, "status": "safe", "status_label": "Dentro do limite",
      "projected_spent_cents": 93000, "projected_overrun_cents": 13000, "is_projected_to_exceed": true
    }
  ],
  "summary": { "total_budget_cents": 80000, "total_spent_cents": 45000, "total_projected_spent_cents": 93000, "budgets_projected_to_exceed_count": 1 }
}
```

### Regras de integridade

- Todos os cálculos usam a mesma convenção de "gasto" já usada em `budgets/status`: soma transações com `status` diferente de `cancelled` (ou seja, `pending` e `paid` contam, `cancelled` nunca conta).
- Toda comparação de limiar (`is_anomalous`, `is_projected_to_exceed`) é feita com aritmética inteira em centavos, nunca float — evita erro de arredondamento exatamente na borda do limiar. Campos como `usage_percentage`/`deviation_percentage`/`*_percentage` são só apresentação.
- A média histórica em `anomalies` arredonda para baixo (`intdiv`) — deliberado, deixa o limiar um pouco mais difícil de disparar.
- `period=quarter` e `period=year` também passaram a valer no filtro já existente `GET /transactions?period=`, como efeito colateral de terem sido adicionados ao enum `TransactionPeriod`.
- Todo endpoint é escopado ao usuário autenticado; dados de outro usuário nunca aparecem.

### Fora de escopo

Previsão de fluxo de caixa, detecção de recorrência não cadastrada (cobrança repetida que o usuário nunca cadastrou como `Recurrence`), e comparação/sugestões de economia baseadas em dados agregados entre usuários (exigiria infraestrutura de dados anônimos entre contas, inexistente hoje) **não fazem parte desta etapa**. Tela de dashboard no frontend consumindo estes endpoints também fica para uma etapa futura.

## Importação de extrato bancário (`/api/v1/accounts/{account}/statement-imports`)

> **Importante:** esta feature implementa **importar transações a partir de um arquivo de extrato (OFX ou CSV) exportado manualmente pelo usuário do internet banking/app do banco**, para uma conta já existente escolhida por ele. **Não há nenhuma integração automática/ao vivo com bancos** (nem Open Finance, nem agregadores como Pluggy/Belvo) — a atualização depende do usuário baixar e enviar o extrato periodicamente. Detecção/criação automática de conta a partir do arquivo, saldo bancário real e suporte a outros formatos de CSV (múltiplas colunas de débito/crédito, mapeamento salvo por banco) **não fazem parte desta etapa**.

### Fluxo

1. O usuário exporta o extrato do período desejado pelo internet banking/app do banco, em OFX (recomendado, formato padronizado) ou CSV.
2. O frontend envia o arquivo para `POST /api/v1/accounts/{account}/statement-imports`, indicando a conta local em que as transações devem entrar.
3. A API processa o arquivo de forma síncrona (sem fila/job) e responde com o resumo da importação: quantas transações foram criadas e quantas já existiam (reenviar o mesmo arquivo nunca duplica lançamentos).
4. `GET /api/v1/accounts/{account}/statement-imports` lista o histórico de importações da conta, para auditoria.

### Endpoints

Todos exigem autenticação (`Authorization: Bearer <token>`) e operam sobre uma conta do usuário autenticado (conta de outro usuário retorna `404`).

- `POST /api/v1/accounts/{account}/statement-imports` — envia um arquivo de extrato (`multipart/form-data`) e importa as transações nele contidas para a conta.
- `GET /api/v1/accounts/{account}/statement-imports` — lista o histórico de importações da conta (array simples, sem paginação — mesmo padrão de `accounts`/`categories`/`recurrences`/`budgets`), mais recente primeiro.

### Campos do upload (`POST`)

| Campo | Tipo | Observações |
| --- | --- | --- |
| `file` | arquivo | Obrigatório. Extensões aceitas: `ofx`, `csv`, `txt`. Tamanho máximo configurável (`STATEMENT_IMPORTS_MAX_FILE_SIZE_KB`, padrão 5 MB). |
| `format` | `ofx`\|`csv` | Obrigatório. Determina qual parser processa o arquivo — independente da extensão real do arquivo enviado. |
| `date_column` | int | Obrigatório quando `format = csv`. Posição (0-indexed) da coluna de data. |
| `description_column` | int | Obrigatório quando `format = csv`. Posição (0-indexed) da coluna de descrição. |
| `amount_column` | int | Obrigatório quando `format = csv`. Posição (0-indexed) da coluna de valor — uma única coluna com sinal (negativo = despesa, positivo = receita). |
| `date_format` | string | Obrigatório quando `format = csv`. Formato PHP `date()` da coluna de data (ex.: `d/m/Y` para `20/07/2026`). |
| `delimiter` | string | Opcional, `format = csv`. Um único caractere separador de colunas. Padrão `,`. |
| `has_header` | bool | Opcional, `format = csv`. Se `true` (padrão), a primeira linha é descartada como cabeçalho. |

Para `format = ofx`, nenhum campo de mapeamento é necessário — o parser lê diretamente os blocos `<STMTTRN>` do arquivo (suporta tanto OFX 1.x/SGML quanto OFX 2.x/XML).

### Resposta

```json
{
  "id": 1,
  "account_id": 3,
  "format": "ofx",
  "format_label": "OFX",
  "original_filename": "extrato-julho.ofx",
  "transactions_created": 42,
  "transactions_skipped": 3,
  "created_at": "2026-08-01T12:00:00.000000Z",
  "updated_at": "2026-08-01T12:00:00.000000Z"
}
```

`transactions_skipped` conta transações do arquivo que já haviam sido importadas anteriormente (dedup) — não é um sinal de erro.

### Transações importadas

- Cada transação importada aparece normalmente em `GET /api/v1/transactions`, com `origin = "statement_import"` (`"manual"` para lançamentos criados pelo usuário) e `external_id` preenchido — no OFX, o `FITID` do banco (prefixado com `ofx:`); no CSV, um hash determinístico de data+descrição+valor (prefixado com `csv:`), já que CSV não traz um identificador único nativo.
- Toda transação importada é criada com `status = "paid"` — representa um lançamento bancário já efetivado, diferente do fluxo `pending`/`paid` usado para lançamentos manuais/recorrências. No OFX, transações ainda `PENDING` do lado do banco (não liquidadas) são ignoradas.
- `category_id` é sempre `null` em transações recém-importadas — a categorização é manual, feita normalmente pelo usuário via `PATCH /api/v1/transactions/{id}`.
- Reenviar o mesmo arquivo (ou um arquivo com transações sobrepostas, ex.: extratos de períodos que se cruzam) nunca duplica lançamentos — idempotência garantida tanto na aplicação quanto por uma constraint única no banco (`account_id` + `external_id`).

### Regras de integridade

- A conta de destino deve pertencer ao usuário autenticado.
- Não há tentativa de detectar ou criar automaticamente uma conta a partir dos dados do arquivo — o usuário sempre escolhe a conta de destino explicitamente antes do upload.
- `format = csv` exige todos os campos de mapeamento (`date_column`, `description_column`, `amount_column`, `date_format`); sem eles, a requisição retorna `422`.
- Um arquivo com data ou valor ilegível numa linha específica não interrompe a importação — a linha é apenas ignorada e não conta em `transactions_created` nem `transactions_skipped`.

## WhatsApp (`/api/v1/whatsapp`)

> **Importante:** esta feature implementa um chatbot reativo (o usuário manda mensagem primeiro, o bot responde) usando a API oficial do WhatsApp Cloud API (Meta), sem nenhum revendedor pago (Twilio, Zenvia, 360dialog etc.). O menu v1 cobre apenas **consulta** (saldo, últimas transações, status do orçamento) e **lançamento via texto livre** (reaproveitando o assistente de IA já existente em `POST /assistant/quick-add`). Gerenciar categorias, recorrências ou contas pelo bot **não faz parte desta etapa**.

### Vincular o número de WhatsApp

1. O app chama `POST /api/v1/whatsapp/link-code` (autenticado) e recebe um código numérico de 6 dígitos, válido por 10 minutos.
2. O usuário envia esse código como mensagem de texto para o número de WhatsApp do bot.
3. O bot reconhece o código, vincula o número à conta do usuário e responde com o menu principal.
4. `DELETE /api/v1/whatsapp/link` (autenticado) desvincula o número — o histórico de contas/transações não é afetado, só o vínculo em si; qualquer confirmação pendente daquele número é descartada.

### Endpoints

- `POST /api/v1/whatsapp/link-code` *(autenticado)* → `{ "code": "123456", "expires_at": "..." }`.
- `DELETE /api/v1/whatsapp/link` *(autenticado)* → `204`.
- `GET /api/v1/whatsapp/webhook` — handshake de verificação exigido pela Meta ao cadastrar o webhook (não é destinado ao frontend).
- `POST /api/v1/whatsapp/webhook` — recebe as mensagens do WhatsApp (não é destinado ao frontend). Verificado via assinatura HMAC (`X-Hub-Signature-256`), nunca via autenticação de usuário.

### Menu principal (enviado ao vincular, ou ao receber "menu"/"oi"/"olá"/"ajuda")

| Opção | O que faz |
| --- | --- |
| Ver saldo | Lista as contas do usuário com o saldo atual (`AccountBalanceService`, mesma lógica de `current_balance_cents` já usada em `AccountResource`). Até 10 contas; o excedente vira "e mais N contas". |
| Últimas transações | As 5 transações mais recentes, uma linha por transação (data, descrição, valor). |
| Status do orçamento | Consumo dos orçamentos do mês corrente, mesma lógica de `GET /budgets/status`. |
| Adicionar transação | Pede para o usuário descrever o lançamento em texto livre (ex.: "gastei 50 reais no mercado") e interpreta via o mesmo assistente de IA do `quick-add`. |
| Ajuda | Texto estático de ajuda. |

### Lançar uma transação por texto livre

O texto do usuário é interpretado pelo mesmo mecanismo de `POST /assistant/quick-add` (Gemini + validação reaproveitando as regras de `StoreAccountRequest`/`StoreTransactionRequest`). Se o pedido gerar uma ou mais ações (ex.: um parcelamento vira várias transações), o bot envia um resumo com botões **Confirmar**/**Cancelar** cobrindo o lote inteiro — não há confirmação ação por ação. Só ao confirmar as transações/contas são de fato criadas; cancelar não grava nada. Enquanto uma confirmação estiver pendente, qualquer mensagem que não seja o botão de confirmar/cancelar reenvia o mesmo prompt (a ação pendente nunca é descartada silenciosamente).

### Regras de integridade

- Um número de WhatsApp só pode estar vinculado a um usuário por vez; vincular o mesmo número a outra conta exige desvincular primeiro.
- O código de vínculo é de uso único (consumido ao vincular com sucesso) e expira em 10 minutos.
- Uma sessão de conversa parada (mais de `WHATSAPP_SESSION_TTL_MINUTES`, padrão 15 minutos, sem resposta a uma confirmação pendente) é resetada automaticamente na próxima mensagem recebida daquele número.
- Transações lançadas pelo bot entram como `status = pending` (mesmo comportamento de um lançamento manual via `POST /transactions`), sem categoria (`category_id = null`) até o usuário categorizar.

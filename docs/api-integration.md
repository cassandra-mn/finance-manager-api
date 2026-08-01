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

## Open Finance (`/api/v1/open-finance`)

> **Importante:** esta feature implementa **conectar uma conta bancária e importar suas transações automaticamente**, usando o agregador [Pluggy](https://pluggy.ai) como provedor de Open Finance. **Sincronização de saldo bancário real, iniciação de pagamentos (Open Finance pagamentos) e suporte a múltiplos agregadores não fazem parte desta etapa.** O saldo exibido continua sendo sempre calculado a partir das transações (`current_balance_cents`, já existente em `AccountResource`), mesmo para contas importadas.

Uma **conexão bancária** (`BankConnection`) representa o vínculo entre o usuário e uma instituição financeira conectada via Pluggy. O backend nunca lida com credenciais bancárias diretamente — a Pluggy cuida disso — e guarda apenas o identificador da conexão (`pluggy_item_id`) retornado pelo widget de conexão.

### Fluxo de conexão

1. O frontend chama `POST /open-finance/connect-token` e recebe um `access_token` de curta duração.
2. O frontend usa esse token para abrir o [Pluggy Connect](https://docs.pluggy.ai/docs/pluggy-connect) (widget hospedado pela Pluggy, fora desta API), onde o usuário escolhe o banco e informa suas credenciais diretamente à Pluggy.
3. Ao concluir, o widget retorna um `item_id`. O frontend envia esse `item_id` para `POST /open-finance/connections`, que cria a `BankConnection` e dispara a primeira sincronização.
4. A partir daí, a sincronização acontece automaticamente em segundo plano — via job agendado (cadência configurável em `OPEN_FINANCE_SYNC_INTERVAL_HOURS`, padrão a cada 1h) e via webhook da Pluggy — sem exigir nova ação do frontend. `POST /open-finance/connections/{id}/resync` está disponível para o usuário forçar uma sincronização imediata (ex.: botão "Sincronizar agora").
5. Se a conexão cair em `status = login_error` (credenciais expiradas), o frontend deve pedir um novo `connect-token` passando o `item_id` existente (modo reconexão da Pluggy) e reabrir o widget.

### Endpoints

Todos exigem autenticação (`Authorization: Bearer <token>`), exceto o webhook.

- `POST /api/v1/open-finance/connect-token` — gera um token de curta duração para abrir o Pluggy Connect. Aceita `item_id` opcional no corpo para reconectar uma conexão existente (modo atualização de credenciais).
- `GET /api/v1/open-finance/connections` — lista as conexões bancárias do usuário autenticado (array simples, sem paginação — mesmo padrão de `accounts`/`categories`/`recurrences`/`budgets`).
- `POST /api/v1/open-finance/connections` — recebe `{ "item_id": "..." }` do widget e cria a conexão, disparando a sincronização inicial.
- `GET /api/v1/open-finance/connections/{id}` — exibe uma conexão.
- `POST /api/v1/open-finance/connections/{id}/resync` — força uma nova sincronização da conexão.
- `DELETE /api/v1/open-finance/connections/{id}` — desconecta o banco (remove o item na Pluggy e faz soft delete da conexão localmente).
- `POST /api/v1/open-finance/webhook` — endpoint público consumido pela Pluggy para avisar sobre mudanças (novo item, novas transações, etc.). Não é destinado ao frontend.

### Resposta de uma conexão

```json
{
  "id": 1,
  "pluggy_item_id": "5cf1e2f9-...",
  "institution_id": "201",
  "institution_name": "Banco Teste",
  "status": "updated",
  "status_label": "Atualizado",
  "last_synced_at": "2026-08-01T12:00:00.000000Z",
  "last_sync_error": null,
  "created_at": "2026-08-01T11:00:00.000000Z",
  "updated_at": "2026-08-01T12:00:00.000000Z"
}
```

`pluggy_item_id` é exposto de propósito: o frontend precisa dele para solicitar um `connect-token` em modo reconexão quando `status = login_error`.

### Enum `status`

| Valor | Label (pt-BR) | Significado |
| --- | --- | --- |
| `updating` | Sincronizando | Sincronização em andamento na Pluggy. |
| `updated` | Atualizado | Última sincronização concluída com sucesso. |
| `login_error` | Erro de login | Credenciais expiradas — é necessário reconectar via widget. |
| `outdated` | Desatualizado | Dados podem estar defasados; será tentado novamente automaticamente. |
| `waiting_user_input` | Aguardando ação do usuário | A instituição exige uma ação adicional (ex.: MFA) no widget. |
| `error` | Erro | Falha ao sincronizar; `last_sync_error` traz detalhes. |
| `deleted` | Removido no banco | O item não existe mais do lado da Pluggy. |

### Contas e transações importadas

- Cada conta bancária conectada vira um registro normal em `GET /api/v1/accounts`, com `bank_connection_id` e `external_account_id` preenchidos (`null` para contas manuais). O tipo (`checking`/`savings`/`credit_card`) é inferido a partir do tipo/subtipo retornado pela Pluggy.
- Cada transação importada aparece normalmente em `GET /api/v1/transactions`, com `origin = "open_finance"` (`"manual"` para lançamentos criados pelo usuário) e `external_id` preenchido com o identificador da transação na Pluggy.
- Toda transação importada é criada com `status = "paid"` — representa um lançamento bancário já efetivado, diferente do fluxo `pending`/`paid` usado para lançamentos manuais/recorrências. Transações ainda `PENDING` do lado do banco não são importadas; entram automaticamente numa sincronização futura, quando a Pluggy as marcar como efetivadas.
- `category_id` é sempre `null` em transações recém-importadas — a categorização é manual, feita normalmente pelo usuário via `PATCH /api/v1/transactions/{id}`.
- Reexecutar a sincronização (agendada, via webhook ou manual) nunca duplica contas nem transações já importadas — idempotência garantida tanto na aplicação quanto por constraints únicas no banco.
- Excluir (soft delete) uma conta importada localmente é respeitado: sincronizações futuras não a recriam.
- Uma conta com `bank_connection_id` preenchido não pode ser excluída (`DELETE /api/v1/accounts/{id}`) enquanto a conexão bancária estiver ativa — a API retorna `422` pedindo para desconectar o banco primeiro.

### Regras de integridade

- Toda conexão bancária pertence ao usuário autenticado; não é possível consultar, sincronizar ou desconectar uma conexão de outro usuário (retorna `404`).
- Um mesmo `item_id` da Pluggy só pode estar associado a uma conexão não excluída por vez — tentar registrar o mesmo `item_id` duas vezes retorna `422`.
- Desconectar um banco (`DELETE /connections/{id}`) é soft delete: **as contas e transações já importadas permanecem intactas** como dados locais normais, editáveis pelo usuário — apenas param de ser sincronizadas automaticamente.
- Não há tentativa de mesclar uma conta manual existente com uma conta recém-conectada do mesmo banco — cada conta da Pluggy sempre gera uma nova conta local.

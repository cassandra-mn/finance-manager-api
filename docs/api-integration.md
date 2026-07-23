# Guia de integração da API — Finance Manager

Este documento descreve os recursos disponíveis na API (`/api/v1`) para integração do frontend.

## Autenticação

A API usa Laravel Sanctum com token Bearer.

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout` *(autenticado)*
- `GET /api/v1/auth/me` *(autenticado)*

Todas as rotas abaixo exigem o header `Authorization: Bearer <token>` e operam isoladas por usuário: um recurso que pertence a outro usuário sempre retorna **404**, nunca 403.

## Recursos existentes (resumo)

- **Contas** (`/api/v1/accounts`) — contas financeiras do usuário.
- **Categorias** (`/api/v1/categories`) — categorias de receita/despesa do usuário.
- **Transações** (`/api/v1/transactions`) — lançamentos financeiros (`pay`, `cancel`, filtros e paginação).

## Recorrências (`/api/v1/recurrences`)

> **Importante:** esta feature implementa apenas o **CRUD da regra de recorrência**. Ela **não gera transações automaticamente**. Não existem, ainda, scheduler, command, job de processamento ou dashboard associados a recorrências — isso fica para uma etapa futura.

Uma recorrência representa uma regra que descreve uma receita ou despesa recorrente (ex.: salário mensal, aluguel mensal, assinatura mensal, academia semanal, pagamento quinzenal, seguro anual). Ela pertence ao usuário autenticado, referencia uma conta (obrigatória) e opcionalmente uma categoria.

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
- Pausar uma regra não a remove nem apaga seu histórico — apenas marca `is_active = false`.
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

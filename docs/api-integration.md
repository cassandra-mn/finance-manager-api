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

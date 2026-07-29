<?php

namespace App\Services;

use App\Common\Money;
use App\Exceptions\ServiceException;
use App\Http\Requests\Accounts\StoreAccountRequest;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Repositories\AccountRepository;
use App\Repositories\CategoryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

final class AiAssistantService
{
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly GeminiClient $geminiClient,
    ) {}

    /** @return array{clarification: ?string, actions: list<array<string, mixed>>} */
    public function quickAdd(string $message, User $user): array
    {
        $accounts = $this->accountRepository->listForUser($user->id);
        $categories = $this->categoryRepository->listForUser($user->id);

        try {
            $raw = $this->geminiClient->generateStructured(
                $this->buildSystemInstruction(),
                $this->buildUserMessage($message, $accounts, $categories),
                $this->responseSchema(),
            );
        } catch (ServiceException $e) {
            return ['clarification' => $e->getMessage(), 'actions' => []];
        }

        $clarification = is_string($raw['clarification'] ?? null) ? $raw['clarification'] : null;
        $rawActions = is_array($raw['actions'] ?? null) ? $raw['actions'] : [];

        $actions = [];
        $hadInvalidAction = false;

        foreach ($rawActions as $index => $rawAction) {
            if (! is_array($rawAction)) {
                $hadInvalidAction = true;

                continue;
            }

            $kind = $rawAction['kind'] ?? null;
            $clientId = is_string($rawAction['client_id'] ?? null) ? $rawAction['client_id'] : 'a'.($index + 1);
            $summary = is_string($rawAction['summary'] ?? null) ? $rawAction['summary'] : '';

            try {
                match ($kind) {
                    'create_account' => $actions[] = $this->buildAccountAction($clientId, $summary, $rawAction['account'] ?? null),
                    'create_transaction' => $actions[] = $this->buildTransactionAction($clientId, $summary, $rawAction['transaction'] ?? null),
                    'create_installment_transactions' => array_push($actions, ...$this->buildInstallmentActions($clientId, $summary, $rawAction['installment'] ?? null)),
                    default => throw new InvalidArgumentException("kind desconhecido: {$kind}"),
                };
            } catch (Throwable $e) {
                Log::warning('finance.assistant.action_discarded', [
                    'kind' => $kind,
                    'message' => $e->getMessage(),
                ]);
                $hadInvalidAction = true;
            }
        }

        if ($clarification === null && $hadInvalidAction && $actions === []) {
            $clarification = 'Não consegui confirmar todos os dados do seu pedido. Pode detalhar de novo (conta, valor e categoria)?';
        }

        return ['clarification' => $clarification, 'actions' => $actions];
    }

    /** @return array<string, mixed> */
    private function buildAccountAction(string $clientId, string $summary, mixed $raw): array
    {
        if (! is_array($raw)) {
            throw new InvalidArgumentException('ação create_account sem dados de conta.');
        }

        $payload = [
            'name' => (string) ($raw['name'] ?? ''),
            'type' => (string) ($raw['type'] ?? ''),
            'initial_balance_cents' => Money::fromAmount($raw['initial_balance'] ?? '0.00')->cents,
            'credit_limit_cents' => isset($raw['credit_limit'])
                ? Money::fromAmount($raw['credit_limit'])->cents
                : null,
            'color' => $raw['color'] ?? null,
        ];

        Validator::make($payload, (new StoreAccountRequest)->rules())->validate();

        return [
            'client_id' => $clientId,
            'kind' => 'account',
            'summary' => $summary,
            'account_ref' => null,
            'payload' => $payload,
        ];
    }

    /** @return array<string, mixed> */
    private function buildTransactionAction(
        string $clientId,
        string $summary,
        mixed $raw,
        ?string $forcedAccountId = null,
        ?string $forcedAccountRef = null,
    ): array {
        if (! is_array($raw)) {
            throw new InvalidArgumentException('ação create_transaction sem dados de transação.');
        }

        $accountId = $forcedAccountId !== null
            ? (int) $forcedAccountId
            : (isset($raw['account_id']) && $raw['account_id'] !== null ? (int) $raw['account_id'] : null);

        $accountRef = $forcedAccountRef ?? (is_string($raw['account_ref'] ?? null) ? $raw['account_ref'] : null);

        if ($accountId === null && $accountRef === null) {
            throw new InvalidArgumentException('transação sem account_id nem account_ref.');
        }

        if (! isset($raw['amount'])) {
            throw new InvalidArgumentException('valor da transação ausente.');
        }

        $payload = [
            'account_id' => $accountId,
            'category_id' => isset($raw['category_id']) && $raw['category_id'] !== null ? (int) $raw['category_id'] : null,
            'type' => (string) ($raw['type'] ?? ''),
            'entry_type' => (string) ($raw['entry_type'] ?? 'single'),
            'description' => (string) ($raw['description'] ?? ''),
            'amount_cents' => Money::fromAmount($raw['amount'])->cents,
            'due_date' => (string) ($raw['due_date'] ?? ''),
            'notes' => $raw['notes'] ?? null,
        ];

        $rules = (new StoreTransactionRequest)->rules();

        if ($accountRef !== null && $accountId === null) {
            $rules['account_id'] = ['nullable'];
        }

        Validator::make($payload, $rules)->validate();

        return [
            'client_id' => $clientId,
            'kind' => 'transaction',
            'summary' => $summary,
            'account_ref' => $accountRef,
            'payload' => $payload,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function buildInstallmentActions(string $clientId, string $summary, mixed $raw): array
    {
        if (! is_array($raw)) {
            throw new InvalidArgumentException('ação create_installment_transactions sem dados.');
        }

        $accountId = isset($raw['account_id']) && $raw['account_id'] !== null ? (string) $raw['account_id'] : null;
        $accountRef = is_string($raw['account_ref'] ?? null) ? $raw['account_ref'] : null;

        if ($accountId === null && $accountRef === null) {
            throw new InvalidArgumentException('parcelamento sem account_id nem account_ref.');
        }

        if (! isset($raw['total_amount'])) {
            throw new InvalidArgumentException('valor total do parcelamento ausente.');
        }

        if (! isset($raw['first_due_date'])) {
            throw new InvalidArgumentException('vencimento da primeira parcela ausente.');
        }

        $count = (int) ($raw['installments_count'] ?? 0);

        if ($count < 1 || $count > 60) {
            throw new InvalidArgumentException('número de parcelas inválido.');
        }

        $totalCents = Money::fromAmount($raw['total_amount'])->cents;
        $base = intdiv($totalCents, $count);
        $remainder = $totalCents - ($base * $count);
        $firstDueDate = Carbon::parse((string) $raw['first_due_date']);

        $baseDescription = (string) ($raw['description'] ?? '');
        $actions = [];

        for ($i = 0; $i < $count; $i++) {
            $amountCents = $base + ($i === 0 ? $remainder : 0);
            $dueDate = $firstDueDate->copy()->addMonthsNoOverflow($i);
            $installmentNumber = $i + 1;
            $installmentSuffix = " ({$installmentNumber}/{$count})";

            $installmentRaw = [
                'account_id' => $raw['account_id'] ?? null,
                'account_ref' => $accountRef,
                'category_id' => $raw['category_id'] ?? null,
                'type' => $raw['type'] ?? null,
                'entry_type' => 'single',
                'description' => $baseDescription.$installmentSuffix,
                'amount' => number_format($amountCents / 100, 2, '.', ''),
                'due_date' => $dueDate->toDateString(),
                'notes' => null,
            ];

            $actions[] = $this->buildTransactionAction(
                $clientId.'-'.$installmentNumber,
                "{$summary} (parcela {$installmentNumber}/{$count})",
                $installmentRaw,
                $accountId,
                $accountRef,
            );
        }

        return $actions;
    }

    private function buildSystemInstruction(): string
    {
        $today = now()->translatedFormat('l, d/m/Y');

        return <<<TEXT
            Você é um assistente que interpreta pedidos em português sobre lançamentos financeiros (contas e transações) de um app de finanças pessoais.

            Hoje é {$today}. Use essa data para resolver expressões relativas como "amanhã", "sexta que vem" ou "dia 10".

            Regras importantes:
            - Use APENAS os ids de conta/categoria fornecidos na lista abaixo. Nunca invente um id.
            - Se o usuário mencionar uma conta ou cartão que não está na lista, só proponha criá-lo (kind: create_account) se estiver claro que é isso que o usuário quer; caso contrário, deixe uma pergunta em "clarification".
            - Toda ação create_transaction/create_installment_transactions PRECISA ter account_id (de uma conta da lista) ou account_ref (de uma ação create_account desta mesma resposta) preenchido. Se não estiver claro em qual conta lançar (por exemplo, o usuário não citou nenhum nome de conta/cartão e há mais de uma conta cadastrada), NÃO proponha a ação: em vez disso, pergunte em "clarification" qual conta usar.
            - Valores monetários devem ser strings decimais em reais (ex.: "300.00"), nunca centavos.
            - Datas devem estar no formato YYYY-MM-DD.
            - "category_id" é opcional: deixe null se não tiver certeza da categoria, não force uma categoria errada.
            - Para compras parceladas (ex.: "3x"), use a ação create_installment_transactions com o valor TOTAL da compra, não o valor de cada parcela.
            - Se uma ação cria uma conta nova e outra ação lança uma transação nessa mesma conta nova, use "account_ref" na transação/parcelamento apontando para o "client_id" da ação create_account, em vez de "account_id".
            - Se não houver informação suficiente para propor uma ação com segurança, preencha "clarification" com uma pergunta objetiva em português. "clarification" pode coexistir com ações já resolvidas.
            TEXT;
    }

    /** @param Collection<int, Account> $accounts
     *  @param Collection<int, Category> $categories */
    private function buildUserMessage(string $message, Collection $accounts, Collection $categories): string
    {
        $accountLines = $accounts->isEmpty()
            ? 'Nenhuma conta cadastrada ainda.'
            : $accounts->map(fn (Account $account): string => "[{$account->id}] {$account->name} ({$account->type->label()})")->implode("\n");

        $categoryLines = $categories->isEmpty()
            ? 'Nenhuma categoria cadastrada ainda.'
            : $categories->map(fn (Category $category): string => "[{$category->id}] {$category->name} ({$category->type->label()})")->implode("\n");

        return <<<TEXT
            Contas do usuário:
            {$accountLines}

            Categorias do usuário:
            {$categoryLines}

            Pedido do usuário: "{$message}"
            TEXT;
    }

    /** @return array<string, mixed> */
    private function responseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'clarification' => ['type' => 'STRING', 'nullable' => true],
                'actions' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'client_id' => ['type' => 'STRING'],
                            'kind' => [
                                'type' => 'STRING',
                                'enum' => ['create_account', 'create_transaction', 'create_installment_transactions'],
                            ],
                            'summary' => ['type' => 'STRING'],
                            'account' => [
                                'type' => 'OBJECT',
                                'nullable' => true,
                                'properties' => [
                                    'name' => ['type' => 'STRING'],
                                    'type' => [
                                        'type' => 'STRING',
                                        'enum' => ['checking', 'savings', 'wallet', 'credit_card', 'investment', 'other'],
                                    ],
                                    'initial_balance' => ['type' => 'STRING'],
                                    'credit_limit' => ['type' => 'STRING', 'nullable' => true],
                                    'color' => ['type' => 'STRING', 'nullable' => true],
                                ],
                                'required' => ['name', 'type', 'initial_balance'],
                            ],
                            'transaction' => [
                                'type' => 'OBJECT',
                                'nullable' => true,
                                'properties' => [
                                    'account_id' => ['type' => 'INTEGER', 'nullable' => true],
                                    'account_ref' => ['type' => 'STRING', 'nullable' => true],
                                    'category_id' => ['type' => 'INTEGER', 'nullable' => true],
                                    'type' => ['type' => 'STRING', 'enum' => ['income', 'expense']],
                                    'entry_type' => ['type' => 'STRING', 'enum' => ['fixed', 'variable', 'single']],
                                    'description' => ['type' => 'STRING'],
                                    'amount' => ['type' => 'STRING'],
                                    'due_date' => ['type' => 'STRING'],
                                    'notes' => ['type' => 'STRING', 'nullable' => true],
                                ],
                                'required' => ['type', 'entry_type', 'description', 'amount', 'due_date'],
                            ],
                            'installment' => [
                                'type' => 'OBJECT',
                                'nullable' => true,
                                'properties' => [
                                    'account_id' => ['type' => 'INTEGER', 'nullable' => true],
                                    'account_ref' => ['type' => 'STRING', 'nullable' => true],
                                    'category_id' => ['type' => 'INTEGER', 'nullable' => true],
                                    'type' => ['type' => 'STRING', 'enum' => ['income', 'expense']],
                                    'description' => ['type' => 'STRING'],
                                    'total_amount' => ['type' => 'STRING'],
                                    'installments_count' => ['type' => 'INTEGER'],
                                    'first_due_date' => ['type' => 'STRING'],
                                ],
                                'required' => ['type', 'description', 'total_amount', 'installments_count', 'first_due_date'],
                            ],
                        ],
                        'required' => ['client_id', 'kind', 'summary'],
                    ],
                ],
            ],
            'required' => ['actions'],
        ];
    }
}

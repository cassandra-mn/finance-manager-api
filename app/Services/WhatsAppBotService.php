<?php

namespace App\Services;

use App\Common\Money;
use App\Enum\WhatsAppSessionState;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WhatsAppSession;
use App\Repositories\AccountRepository;
use App\Repositories\BudgetRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\WhatsAppSessionRepository;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * "Cérebro" do bot do WhatsApp: roteia cada mensagem recebida (menu, texto
 * livre, botões de confirmação) e decide o que responder, delegando a lógica
 * de negócio já existente (saldo, transações, orçamento, assistente de IA).
 */
final class WhatsAppBotService
{
    private const MENU_BALANCE = 'menu_balance';

    private const MENU_TRANSACTIONS = 'menu_transactions';

    private const MENU_BUDGET_STATUS = 'menu_budget_status';

    private const MENU_ADD_TRANSACTION = 'menu_add_transaction';

    private const MENU_HELP = 'menu_help';

    private const CONFIRM_YES = 'confirm_yes';

    private const CONFIRM_NO = 'confirm_no';

    /** @var list<string> */
    private const MENU_TRIGGERS = ['menu', 'oi', 'ola', 'olá', 'ajuda'];

    private const MAX_ACCOUNTS_LISTED = 10;

    private const RECENT_TRANSACTIONS_LIMIT = 5;

    public function __construct(
        private readonly WhatsAppSessionRepository $sessionRepository,
        private readonly WhatsAppLinkService $linkService,
        private readonly WhatsAppClient $client,
        private readonly AiAssistantService $assistantService,
        private readonly AssistantActionExecutorService $actionExecutor,
        private readonly AccountRepository $accountRepository,
        private readonly AccountBalanceService $balanceService,
        private readonly TransactionRepository $transactionRepository,
        private readonly BudgetRepository $budgetRepository,
        private readonly BudgetStatusService $budgetStatusService,
    ) {}

    public function handleIncomingMessage(string $waId, string $messageType, ?string $text, ?string $interactiveReplyId): void
    {
        $phoneNumber = $this->normalizePhone($waId);
        $session = $this->sessionRepository->firstOrCreateForPhoneNumber($phoneNumber);

        if ($session->isStale((int) config('whatsapp.session_ttl_minutes'))) {
            $session = $this->sessionRepository->update($session, [
                'state' => WhatsAppSessionState::IDLE,
                'context' => null,
            ]);
        }

        if ($session->user_id === null) {
            $this->handleUnlinked($session, $text);

            return;
        }

        $user = $session->user;

        if ($user === null) {
            $session = $this->sessionRepository->update($session, ['user_id' => null]);
            $this->handleUnlinked($session, $text);

            return;
        }

        if ($interactiveReplyId !== null) {
            $this->handleInteractiveReply($session, $user, $interactiveReplyId);

            return;
        }

        if ($session->state === WhatsAppSessionState::AWAITING_CONFIRMATION) {
            $this->resendConfirmation($session);

            return;
        }

        $normalizedText = mb_strtolower(trim((string) $text));

        if ($normalizedText === '') {
            $this->client->sendText($phoneNumber, 'Por enquanto só consigo processar mensagens de texto. Envie "menu" para ver as opções.');

            return;
        }

        if (in_array($normalizedText, self::MENU_TRIGGERS, true)) {
            $this->sendMainMenu($phoneNumber, $user);

            return;
        }

        $this->handleQuickAdd($session, $user, (string) $text);
    }

    private function handleUnlinked(WhatsAppSession $session, ?string $text): void
    {
        $phoneNumber = $session->phone_number;
        $trimmed = trim((string) $text);

        if (preg_match('/^\d{6}$/', $trimmed) === 1) {
            $user = $this->linkService->resolveLinkCode($trimmed);

            if ($user !== null) {
                $this->linkService->link($user, $phoneNumber);
                $this->sessionRepository->update($session, ['user_id' => $user->id]);

                $this->client->sendText($phoneNumber, "Número vinculado à sua conta, {$user->name}!");
                $this->sendMainMenu($phoneNumber, $user);

                return;
            }
        }

        $this->client->sendText(
            $phoneNumber,
            'Esse número ainda não está vinculado a nenhuma conta. Abra o app, vá em Configurações > WhatsApp, gere um código e envie ele aqui.',
        );
    }

    private function handleInteractiveReply(WhatsAppSession $session, User $user, string $replyId): void
    {
        if ($session->state === WhatsAppSessionState::AWAITING_CONFIRMATION) {
            match ($replyId) {
                self::CONFIRM_YES => $this->confirmPendingActions($session, $user),
                self::CONFIRM_NO => $this->cancelPendingActions($session),
                default => $this->resendConfirmation($session),
            };

            return;
        }

        match ($replyId) {
            self::MENU_BALANCE => $this->sendBalance($session->phone_number, $user),
            self::MENU_TRANSACTIONS => $this->sendRecentTransactions($session->phone_number, $user),
            self::MENU_BUDGET_STATUS => $this->sendBudgetStatus($session->phone_number, $user),
            self::MENU_ADD_TRANSACTION => $this->client->sendText($session->phone_number, 'Descreva o lançamento (ex.: gastei 50 reais no mercado).'),
            self::MENU_HELP => $this->sendHelp($session->phone_number),
            default => $this->sendMainMenu($session->phone_number, $user),
        };
    }

    private function sendMainMenu(string $phoneNumber, User $user): void
    {
        $this->client->sendInteractiveList(
            $phoneNumber,
            "Olá, {$user->name}! O que você quer fazer?",
            [[
                'title' => 'Menu',
                'rows' => [
                    ['id' => self::MENU_BALANCE, 'title' => 'Ver saldo'],
                    ['id' => self::MENU_TRANSACTIONS, 'title' => 'Últimas transações'],
                    ['id' => self::MENU_BUDGET_STATUS, 'title' => 'Status do orçamento'],
                    ['id' => self::MENU_ADD_TRANSACTION, 'title' => 'Adicionar transação'],
                    ['id' => self::MENU_HELP, 'title' => 'Ajuda'],
                ],
            ]],
        );
    }

    private function sendBalance(string $phoneNumber, User $user): void
    {
        $accounts = $this->accountRepository->listForUser($user->id);

        if ($accounts->isEmpty()) {
            $this->client->sendText($phoneNumber, 'Você ainda não tem contas cadastradas.');

            return;
        }

        $lines = $accounts->take(self::MAX_ACCOUNTS_LISTED)->map(
            fn (Account $account): string => "{$account->name}: {$this->balanceService->calculateCurrentBalance($account)->format()}",
        );

        if ($accounts->count() > self::MAX_ACCOUNTS_LISTED) {
            $remaining = $accounts->count() - self::MAX_ACCOUNTS_LISTED;
            $lines->push("...e mais {$remaining} conta(s).");
        }

        $this->client->sendText($phoneNumber, $lines->implode("\n"));
    }

    private function sendRecentTransactions(string $phoneNumber, User $user): void
    {
        $transactions = $this->transactionRepository->listRecentForUser($user->id, self::RECENT_TRANSACTIONS_LIMIT);

        if ($transactions->isEmpty()) {
            $this->client->sendText($phoneNumber, 'Nenhuma transação encontrada.');

            return;
        }

        $lines = $transactions->map(function (Transaction $transaction): string {
            $date = $transaction->due_date?->format('d/m') ?? '--/--';
            $amount = Money::fromCents($transaction->amount_cents)->format();

            return "{$date} — {$transaction->description}: {$amount}";
        });

        $this->client->sendText($phoneNumber, "Últimas transações:\n".$lines->implode("\n"));
    }

    private function sendBudgetStatus(string $phoneNumber, User $user): void
    {
        $budgets = $this->budgetRepository->listForPeriod($user->id, (int) now()->month, (int) now()->year);
        $statuses = $this->budgetStatusService->calculate($user->id, $budgets, (int) now()->month, (int) now()->year);

        if ($statuses === []) {
            $this->client->sendText($phoneNumber, 'Você ainda não tem orçamentos cadastrados para este mês.');

            return;
        }

        $lines = collect($statuses)->map(function (array $status): string {
            $spent = Money::fromCents($status['spent_cents'])->format();
            $limit = Money::fromCents($status['budget']->amount_cents)->format();

            return "{$status['budget']->category->name}: {$spent} de {$limit} ({$status['usage_percentage']}%)";
        });

        $this->client->sendText($phoneNumber, "Orçamento do mês:\n".$lines->implode("\n"));
    }

    private function sendHelp(string $phoneNumber): void
    {
        $this->client->sendText(
            $phoneNumber,
            'Envie "menu" a qualquer momento para ver as opções, ou descreva um gasto/receita em texto livre (ex.: "recebi 2000 de salário") que eu tento lançar para você.',
        );
    }

    private function handleQuickAdd(WhatsAppSession $session, User $user, string $text): void
    {
        Auth::setUser($user);

        $result = $this->assistantService->quickAdd($text, $user);

        if ($result['actions'] === []) {
            $message = $result['clarification'] ?? 'Não consegui entender o pedido. Pode detalhar (conta, valor e categoria)?';
            $this->client->sendText($session->phone_number, $message);

            return;
        }

        $summary = collect($result['actions'])->map(fn (array $action): string => "• {$action['summary']}")->implode("\n");
        $prefix = $result['clarification'] !== null ? $result['clarification']."\n\n" : '';

        $this->sessionRepository->update($session, [
            'state' => WhatsAppSessionState::AWAITING_CONFIRMATION,
            'context' => $result['actions'],
        ]);

        $this->client->sendInteractiveButtons(
            $session->phone_number,
            "{$prefix}Confirma isso?\n{$summary}",
            [
                ['id' => self::CONFIRM_YES, 'title' => 'Confirmar'],
                ['id' => self::CONFIRM_NO, 'title' => 'Cancelar'],
            ],
        );
    }

    private function confirmPendingActions(WhatsAppSession $session, User $user): void
    {
        /** @var list<array<string, mixed>> $actions */
        $actions = $session->context ?? [];

        try {
            $results = $this->actionExecutor->execute($actions, $user);
        } catch (Throwable) {
            $this->client->sendText($session->phone_number, 'Não foi possível concluir o lançamento. Tente novamente.');
            $this->resetSession($session);

            return;
        }

        $summary = collect($results)->map(fn (array $result): string => "• {$result['summary']}")->implode("\n");
        $this->client->sendText($session->phone_number, "Pronto! Lançado:\n{$summary}");
        $this->resetSession($session);
    }

    private function cancelPendingActions(WhatsAppSession $session): void
    {
        $this->client->sendText($session->phone_number, 'Ok, cancelado.');
        $this->resetSession($session);
    }

    private function resendConfirmation(WhatsAppSession $session): void
    {
        /** @var list<array<string, mixed>> $actions */
        $actions = $session->context ?? [];
        $summary = collect($actions)->map(fn (array $action): string => "• {$action['summary']}")->implode("\n");

        $this->client->sendInteractiveButtons(
            $session->phone_number,
            "Ainda aguardando sua confirmação:\n{$summary}",
            [
                ['id' => self::CONFIRM_YES, 'title' => 'Confirmar'],
                ['id' => self::CONFIRM_NO, 'title' => 'Cancelar'],
            ],
        );
    }

    private function resetSession(WhatsAppSession $session): void
    {
        $this->sessionRepository->update($session, [
            'state' => WhatsAppSessionState::IDLE,
            'context' => null,
        ]);
    }

    private function normalizePhone(string $waId): string
    {
        return '+'.preg_replace('/\D+/', '', $waId);
    }
}

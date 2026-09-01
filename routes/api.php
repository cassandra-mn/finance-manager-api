<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AssistantController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\GoalController;
use App\Http\Controllers\Api\V1\InsightsController;
use App\Http\Controllers\Api\V1\RecurrenceController;
use App\Http\Controllers\Api\V1\StatementImportController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\TransactionGroupController;
use App\Http\Controllers\Api\V1\WhatsApp\WhatsAppLinkController;
use App\Http\Controllers\Api\V1\WhatsApp\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('google', [AuthController::class, 'google'])->name('google');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
        });
    });

    // Cada rota abaixo (exceto o núcleo mínimo — auth, accounts, categories,
    // transactions básicas) fica atrás de um middleware `feature:<flag>`,
    // que responde 404 quando a flag correspondente está desligada em
    // config('features') — mesmo pra quem tentar acessar direto pela API,
    // não só uma questão de esconder o link no frontend. Ver EnsureFeatureEnabled.
    Route::prefix('whatsapp')->name('whatsapp.')->middleware('feature:whatsapp')->group(function (): void {
        Route::get('webhook', [WhatsAppWebhookController::class, 'verify'])->name('webhook.verify');
        Route::post('webhook', [WhatsAppWebhookController::class, 'handle'])
            ->middleware('throttle:whatsapp-webhook')
            ->name('webhook.handle');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('link-code', [WhatsAppLinkController::class, 'generateCode'])->name('link-code');
            Route::delete('link', [WhatsAppLinkController::class, 'unlink'])->name('unlink');
        });
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        // Núcleo mínimo: sempre ativo, sem flag.
        Route::apiResource('accounts', AccountController::class);
        Route::apiResource('categories', CategoryController::class);
        // GET transactions/export precisa vir antes do apiResource: senão o
        // {transaction} de "GET transactions/{transaction}" (show) casa com
        // "export" como se fosse um ID e o route-model-binding falha antes
        // de chegar na rota certa.
        Route::get('transactions/export', [TransactionController::class, 'export'])
            ->middleware('feature:transaction_export')
            ->name('transactions.export');
        Route::apiResource('transactions', TransactionController::class);
        Route::post('transactions/{transaction}/pay', [TransactionController::class, 'pay'])->name('transactions.pay');
        Route::post('transactions/{transaction}/unpay', [TransactionController::class, 'unpay'])->name('transactions.unpay');
        Route::post('transactions/{transaction}/cancel', [TransactionController::class, 'cancel'])->name('transactions.cancel');

        Route::middleware('feature:recurrences')->group(function (): void {
            Route::apiResource('recurrences', RecurrenceController::class);
            Route::post('recurrences/{recurrence}/pause', [RecurrenceController::class, 'pause'])->name('recurrences.pause');
            Route::post('recurrences/{recurrence}/resume', [RecurrenceController::class, 'resume'])->name('recurrences.resume');
        });

        Route::middleware('feature:budgets')->group(function (): void {
            Route::get('budgets/status', [BudgetController::class, 'status'])->name('budgets.status');
            Route::apiResource('budgets', BudgetController::class);
        });

        Route::apiResource('goals', GoalController::class)->middleware('feature:goals');

        Route::middleware('feature:invoices')->group(function (): void {
            Route::apiResource('transaction-groups', TransactionGroupController::class)
                ->parameters(['transaction-groups' => 'transactionGroup'])
                ->only(['index', 'store', 'show', 'update', 'destroy']);
            Route::post('transaction-groups/{transactionGroup}/pay', [TransactionGroupController::class, 'pay'])->name('transaction-groups.pay');
            Route::post('transaction-groups/{transactionGroup}/close', [TransactionGroupController::class, 'close'])->name('transaction-groups.close');
        });

        Route::middleware('feature:statement_import')->group(function (): void {
            Route::get('accounts/{account}/statement-imports', [StatementImportController::class, 'index'])->name('accounts.statement-imports.index');
            Route::post('accounts/{account}/statement-imports', [StatementImportController::class, 'store'])->name('accounts.statement-imports.store');
        });

        Route::prefix('insights')->name('insights.')->group(function (): void {
            Route::middleware('feature:insights_panel')->group(function (): void {
                Route::get('spending-summary', [InsightsController::class, 'spendingSummary'])->name('spending-summary');
                Route::get('anomalies', [InsightsController::class, 'anomalies'])->name('anomalies');
                Route::get('budget-projection', [InsightsController::class, 'budgetProjection'])->name('budget-projection');
                Route::get('partial-payments', [InsightsController::class, 'partialPayments'])->name('partial-payments');
                Route::get('cash-flow-forecast', [InsightsController::class, 'cashFlowForecast'])->name('cash-flow-forecast');
                Route::get('net-worth-history', [InsightsController::class, 'netWorthHistory'])->name('net-worth-history');
                Route::get('recurring-commitments', [InsightsController::class, 'recurringCommitments'])->name('recurring-commitments');
            });
            Route::get('annual-report', [InsightsController::class, 'annualReport'])
                ->middleware('feature:annual_report')
                ->name('annual-report');
            Route::get('debt-payoff-plan', [InsightsController::class, 'debtPayoffPlan'])
                ->middleware('feature:debt_payoff_plan')
                ->name('debt-payoff-plan');
        });

        Route::middleware(['throttle:ai-assistant', 'feature:ai_assistant'])->group(function (): void {
            Route::post('assistant/quick-add', [AssistantController::class, 'quickAdd'])->name('assistant.quick-add');
            Route::post('assistant/ask', [AssistantController::class, 'askQuestion'])->name('assistant.ask');
        });
    });
});

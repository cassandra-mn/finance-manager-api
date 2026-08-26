<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AssistantController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\CategoryController;
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

    Route::prefix('whatsapp')->name('whatsapp.')->group(function (): void {
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
        Route::apiResource('accounts', AccountController::class);
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('transactions', TransactionController::class);
        Route::apiResource('recurrences', RecurrenceController::class);

        Route::get('budgets/status', [BudgetController::class, 'status'])->name('budgets.status');
        Route::apiResource('budgets', BudgetController::class);

        Route::post('transactions/{transaction}/pay', [TransactionController::class, 'pay'])->name('transactions.pay');
        Route::post('transactions/{transaction}/unpay', [TransactionController::class, 'unpay'])->name('transactions.unpay');
        Route::post('transactions/{transaction}/cancel', [TransactionController::class, 'cancel'])->name('transactions.cancel');

        Route::apiResource('transaction-groups', TransactionGroupController::class)
            ->parameters(['transaction-groups' => 'transactionGroup'])
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::post('transaction-groups/{transactionGroup}/pay', [TransactionGroupController::class, 'pay'])->name('transaction-groups.pay');
        Route::post('transaction-groups/{transactionGroup}/close', [TransactionGroupController::class, 'close'])->name('transaction-groups.close');

        Route::post('recurrences/{recurrence}/pause', [RecurrenceController::class, 'pause'])->name('recurrences.pause');
        Route::post('recurrences/{recurrence}/resume', [RecurrenceController::class, 'resume'])->name('recurrences.resume');

        Route::get('accounts/{account}/statement-imports', [StatementImportController::class, 'index'])->name('accounts.statement-imports.index');
        Route::post('accounts/{account}/statement-imports', [StatementImportController::class, 'store'])->name('accounts.statement-imports.store');

        Route::prefix('insights')->name('insights.')->group(function (): void {
            Route::get('spending-summary', [InsightsController::class, 'spendingSummary'])->name('spending-summary');
            Route::get('anomalies', [InsightsController::class, 'anomalies'])->name('anomalies');
            Route::get('budget-projection', [InsightsController::class, 'budgetProjection'])->name('budget-projection');
            Route::get('partial-payments', [InsightsController::class, 'partialPayments'])->name('partial-payments');
            Route::get('cash-flow-forecast', [InsightsController::class, 'cashFlowForecast'])->name('cash-flow-forecast');
            Route::get('net-worth-history', [InsightsController::class, 'netWorthHistory'])->name('net-worth-history');
            Route::get('recurring-commitments', [InsightsController::class, 'recurringCommitments'])->name('recurring-commitments');
        });

        Route::middleware('throttle:ai-assistant')->group(function (): void {
            Route::post('assistant/quick-add', [AssistantController::class, 'quickAdd'])->name('assistant.quick-add');
            Route::post('assistant/ask', [AssistantController::class, 'askQuestion'])->name('assistant.ask');
        });
    });
});

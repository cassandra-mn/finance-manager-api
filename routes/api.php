<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AssistantController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\OpenFinance\BankConnectionController;
use App\Http\Controllers\Api\V1\OpenFinance\OpenFinanceWebhookController;
use App\Http\Controllers\Api\V1\RecurrenceController;
use App\Http\Controllers\Api\V1\TransactionController;
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

    Route::prefix('open-finance')->name('open-finance.')->group(function (): void {
        Route::post('webhook', [OpenFinanceWebhookController::class, 'handle'])
            ->middleware('throttle:60,1')
            ->name('webhook');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('connect-token', [BankConnectionController::class, 'connectToken'])->name('connect-token');
            Route::apiResource('connections', BankConnectionController::class)->only(['index', 'show', 'store', 'destroy']);
            Route::post('connections/{connection}/resync', [BankConnectionController::class, 'resync'])->name('connections.resync');
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
        Route::post('transactions/{transaction}/cancel', [TransactionController::class, 'cancel'])->name('transactions.cancel');

        Route::post('recurrences/{recurrence}/pause', [RecurrenceController::class, 'pause'])->name('recurrences.pause');
        Route::post('recurrences/{recurrence}/resume', [RecurrenceController::class, 'resume'])->name('recurrences.resume');

        Route::middleware('throttle:ai-assistant')->group(function (): void {
            Route::post('assistant/quick-add', [AssistantController::class, 'quickAdd'])->name('assistant.quick-add');
        });
    });
});

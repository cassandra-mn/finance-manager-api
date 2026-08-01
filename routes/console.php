<?php

use App\Console\Commands\GenerateRecurringTransactionsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Geração diária das ocorrências de recorrências ativas. Em produção, o
// scheduler do Laravel precisa ser executado pelo ambiente de hospedagem
// (ex.: cron rodando `php artisan schedule:run` a cada minuto, ou
// `php artisan schedule:work` em um worker dedicado) — nada aqui dispara
// isso sozinho.
Schedule::command(GenerateRecurringTransactionsCommand::class)
    ->daily()
    ->withoutOverlapping();

<?php

return [

    'pluggy' => [
        'client_id' => env('PLUGGY_CLIENT_ID'),
        'client_secret' => env('PLUGGY_CLIENT_SECRET'),
        'base_url' => env('PLUGGY_BASE_URL', 'https://api.pluggy.ai'),
        'webhook_secret' => env('PLUGGY_WEBHOOK_SECRET'),
    ],

    'sync' => [
        /*
         * Intervalo (em horas) entre execuções do comando agendado
         * open-finance:sync-connections. Complementa (não substitui) o
         * webhook da Pluggy, que dispara uma sincronização assim que o banco
         * avisa que há dados novos.
         */
        'interval_hours' => (int) env('OPEN_FINANCE_SYNC_INTERVAL_HOURS', 1),

        /*
         * Janela (em dias) de transações buscadas na primeira sincronização
         * de uma conta recém-conectada. Sincronizações seguintes usam
         * last_synced_at (com uma margem de alguns dias) como ponto de
         * partida, independente deste valor.
         */
        'transactions_lookback_days' => (int) env('OPEN_FINANCE_TRANSACTIONS_LOOKBACK_DAYS', 90),
    ],

];

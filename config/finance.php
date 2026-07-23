<?php

return [
    'recurrences' => [
        /*
         * Janela (em dias) usada pelo comando finance:generate-recurring-transactions
         * para gerar ocorrências futuras a partir da data de referência, evitando que
         * uma transação só apareça no dia exato do vencimento.
         */
        'generation_days' => (int) env('FINANCE_RECURRENCES_GENERATION_DAYS', 60),
    ],
];

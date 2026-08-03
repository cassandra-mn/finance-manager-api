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

    'credit_cards' => [
        /*
         * Offset padrão (em dias) usado para calcular o dia de fechamento da
         * fatura quando a conta não tem invoice_closing_day explícito: dia de
         * fechamento = dia de vencimento - offset. Ver
         * Account::effectiveInvoiceClosingDay e CreditCardCycleResolver.
         */
        'default_closing_offset_days' => (int) env('FINANCE_CREDIT_CARDS_DEFAULT_CLOSING_OFFSET_DAYS', 10),
    ],
];

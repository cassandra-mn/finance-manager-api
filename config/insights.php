<?php

return [
    /*
     * Quantidade padrão de categorias no ranking de "top categorias" da
     * comparação de períodos, quando não informado na requisição.
     */
    'top_categories' => (int) env('INSIGHTS_TOP_CATEGORIES', 5),

    'anomaly' => [
        /*
         * Quantos períodos anteriores entram na média histórica usada como
         * base de comparação para detectar gasto anômalo por categoria.
         */
        'lookback_periods' => (int) env('INSIGHTS_ANOMALY_LOOKBACK_PERIODS', 3),

        /*
         * Percentual acima da média histórica a partir do qual uma categoria
         * é sinalizada como anômala.
         */
        'threshold_percentage' => (int) env('INSIGHTS_ANOMALY_THRESHOLD_PERCENTAGE', 40),
    ],

    'partial_payments' => [
        /*
         * Quantos meses (incluindo o atual) entram no histórico de valores
         * deixados pendentes por pagamentos parciais, quando não informado
         * na requisição.
         */
        'lookback_months' => (int) env('INSIGHTS_PARTIAL_PAYMENTS_LOOKBACK_MONTHS', 6),
    ],
];

<?php

/*
 * Feature flags: cada funcionalidade "avançada" do app nasce desligada por
 * padrão e só liga com uma variável de ambiente explícita — o objetivo é
 * poder ligar cada uma aos poucos num ambiente de staging antes de expor pra
 * produção. Espelha exatamente as mesmas chaves de src/shared/config/
 * features.ts no frontend (o nome muda de snake_case pra camelCase, o resto
 * é 1:1). Lidas pelo middleware `feature` (App\Http\Middleware\
 * EnsureFeatureEnabled), aplicado por grupo de rotas em routes/api.php.
 */
return [
    'budgets' => (bool) env('FEATURE_BUDGETS', false),
    'goals' => (bool) env('FEATURE_GOALS', false),
    'invoices' => (bool) env('FEATURE_INVOICES', false),
    'recurrences' => (bool) env('FEATURE_RECURRENCES', false),
    'insights_panel' => (bool) env('FEATURE_INSIGHTS_PANEL', false),
    'annual_report' => (bool) env('FEATURE_ANNUAL_REPORT', false),
    'debt_payoff_plan' => (bool) env('FEATURE_DEBT_PAYOFF_PLAN', false),
    'ai_assistant' => (bool) env('FEATURE_AI_ASSISTANT', false),
    'whatsapp' => (bool) env('FEATURE_WHATSAPP', false),
    'statement_import' => (bool) env('FEATURE_STATEMENT_IMPORT', false),
    'transaction_export' => (bool) env('FEATURE_TRANSACTION_EXPORT', false),
];

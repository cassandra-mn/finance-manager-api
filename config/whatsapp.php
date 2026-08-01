<?php

return [
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'app_secret' => env('WHATSAPP_APP_SECRET'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
    'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),

    /*
     * Tempo (em minutos) que uma sessão de conversa "aguardando confirmação"
     * fica válida antes de ser considerada parada e resetada para o estado
     * inicial na próxima mensagem recebida daquele número.
     */
    'session_ttl_minutes' => (int) env('WHATSAPP_SESSION_TTL_MINUTES', 15),
];

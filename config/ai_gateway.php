<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MatBaoAI-SEO gateway
    |--------------------------------------------------------------------------
    |
    | Every call that needs a Google credential (GA4, Search Console) or an AI
    | model goes through the gateway. This website holds only its own project
    | token, written into .env by the internal tool when the site is created; a
    | leaked token reads this project's data and nothing else.
    |
    */

    'url' => rtrim((string) env('MATBAO_AI_URL', ''), '/'),

    'token' => env('MATBAO_AI_TOKEN'),

    // Seconds. The gateway answers from its own cache most of the time.
    'timeout' => (int) env('MATBAO_AI_TIMEOUT', 15),
];

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

    // Signs the gateway's webhooks (POST /api/ai/webhook). Without it webhooks are refused
    // and results are fetched when the page is opened.
    'webhook_secret' => env('MATBAO_AI_WEBHOOK_SECRET'),

    // Seconds. The gateway answers from its own cache most of the time.
    'timeout' => (int) env('MATBAO_AI_TIMEOUT', 15),

    // Seconds for one AI task; the model alone may use most of the gateway's 30 s.
    'task_timeout' => (int) env('MATBAO_AI_TASK_TIMEOUT', 45),
];

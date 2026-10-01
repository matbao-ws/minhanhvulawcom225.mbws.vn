<?php

return [
    /*
    | The timezone "today" and the date ranges are computed in. Google reports a
    | property in its own timezone, so this should match the one set on the GA4
    | property (Vietnam for every shop built on this core).
    */
    'timezone' => env('ANALYTICS_TIMEZONE', 'Asia/Ho_Chi_Minh'),

    // Longest range the dashboard will request (two years).
    'max_days' => 731,
];

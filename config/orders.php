<?php

return [
    /*
     * Online orders take stock, voucher uses and promotion quota the moment they
     * are placed. One left unpaid this long is cancelled and hands them back
     * (orders:expire-unpaid). VNPAY's own payment session lasts 15 minutes; a
     * bank transfer through SePay can reasonably take a day. COD never expires.
     */
    'unpaid_ttl_minutes' => [
        'vnpay' => (int) env('ORDER_UNPAID_TTL_VNPAY', 30),
        'sepay' => (int) env('ORDER_UNPAID_TTL_SEPAY', 1440),
    ],

    /*
     * Ceilings on one checkout. Without them a single anonymous request could
     * reserve a product's entire stock or flash-sale quota, or ask for
     * thousands of lines that each lock a product row.
     */
    'max_lines' => (int) env('ORDER_MAX_LINES', 50),
    'max_line_quantity' => (int) env('ORDER_MAX_LINE_QUANTITY', 100),
];

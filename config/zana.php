<?php

return [
    'allow_unofficial_whatsapp' => env('ZANA_ALLOW_UNOFFICIAL_WHATSAPP', false),
    'merchant_nav_v2' => env('ZANA_MERCHANT_NAV_V2', true),
    'hide_india_merchant_payments' => env('ZANA_HIDE_INDIA_MERCHANT_PAYMENTS', true),
    'enable_daraja_sandbox' => env('ZANA_ENABLE_DARAJA_SANDBOX', false),
    'daraja_sandbox_only' => env('ZANA_DARAJA_SANDBOX_ONLY', true),
];

<?php

return [

    'token_url' => env('BOG_TOKEN_URL', 'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token'),

    'sdk_client_id' => env('BOG_SDK_CLIENT_ID'),

    'installment' => [
        'public_key' => env('BOG_INSTALLMENT_PUBLIC_KEY'),
        'secret_key' => env('BOG_INSTALLMENT_SECRET_KEY'),
        'order_url' => env('BOG_INSTALLMENT_ORDER_URL', 'https://installment.bog.ge/v1/installment/checkout'),
        'status_url' => env('BOG_INSTALLMENT_STATUS_URL', 'https://installment.bog.ge/v1/installment/checkout'),
        'handling_fee' => (float) env('BOG_INSTALLMENT_FEE', 0.05),
        'months' => [3, 6, 12, 18, 24],
    ],

];

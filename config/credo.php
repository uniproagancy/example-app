<?php

return [

    'merchant_id' => env('CREDO_MERCHANT_ID'),
    'widget_url' => env('CREDO_WIDGET_URL', 'https://ganvadeba.credo.ge/widget/'),
    'fee' => (float) env('CREDO_FEE', 0.10),
    'field' => env('CREDO_FIELD', 'credoinstallment'),
    'label' => env('CREDO_LABEL', 'Credo'),
    'status_url' => env('CREDO_STATUS_URL', 'https://ganvadeba.credo.ge/widget/api.php'),
    'secret' => env('CREDO_SECRET'),
    'timeout' => (int) env('CREDO_TIMEOUT', 20),

    'split' => [
        'merchant_id' => env('CREDO_SPLIT_MERCHANT_ID'),
        'widget_url' => env('CREDO_SPLIT_WIDGET_URL', env('CREDO_WIDGET_URL', 'https://ganvadeba.credo.ge/widget/')),
        'fee' => (float) env('CREDO_SPLIT_FEE', env('CREDO_FEE', 0.10)),
        'field' => env('CREDO_SPLIT_FIELD', 'credoinstallment'),
        'label' => env('CREDO_SPLIT_LABEL', 'Credo Split'),
        'status_url' => env('CREDO_SPLIT_STATUS_URL', env('CREDO_STATUS_URL', 'https://ganvadeba.credo.ge/widget/api.php')),
        'secret' => env('CREDO_SPLIT_SECRET'),
        'timeout' => (int) env('CREDO_TIMEOUT', 20),
    ],

];

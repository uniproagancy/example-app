<?php

return [

    'supportedLocales' => [
        'ka' => ['name' => 'Georgian', 'script' => 'Geor', 'native' => 'ქართული', 'regional' => 'ka_GE'],
    ],

    'useAcceptLanguageHeader' => true,

    'hideDefaultLocaleInURL' => false,

    'localesOrder' => ['ka'],

    'localesMapping' => [],

    'utf8suffix' => env('LARAVELLOCALIZATION_UTF8SUFFIX', '.UTF-8'),

    'urlsIgnored' => [],

    'httpMethodsIgnored' => ['POST', 'PUT', 'PATCH', 'DELETE'],

];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'zoommer' => [
        'api_url' => env('ZOOMMER_API_URL', 'https://zoommer.ge/api/proxy/'),
        'access_token' => env('ZOOMMER_ACCESS_TOKEN'),
    ],

    'elite' => [
        'api_url' => env('ELITE_API_URL'),
        'token' => env('ELITE_TOKEN'),
    ],

    'alta' => [
        'wsdl' => env('ALTA_WSDL', 'http://extra.alta.com.ge/b2b/b2bEWS?WSDL'),
        'user' => env('ALTA_B2B_USER'),
        'password' => env('ALTA_B2B_PASSWORD'),
        'timeout' => (int) env('ALTA_TIMEOUT', 25),
        'token' => env('ALTA_ACCESS_TOKEN'),
        'user_agent' => env('ALTA_USER_AGENT'),
        'retail_directory' => env('ALTA_RETAIL_DIRECTORY', storage_path('app/private/alta-retail')),
        'retail_max_age' => (int) env('ALTA_RETAIL_MAX_AGE', 86400),
    ],

    'allmarket' => [
        'url' => env('ALLMARKET_URL', 'https://b2b.allmarket.ge/_mvcapi/productsapi/get'),
        'app_secret' => env('ALLMARKET_APP_SECRET'),
    ],

    'citrus' => [
        'base_url' => env('CITRUS_BASE_URL', 'https://citrus.ge'),
        'user_agent' => env('CITRUS_USER_AGENT'),
    ],

    'midea' => [
        'base_url' => env('MIDEA_BASE_URL', 'https://www.midea.ge'),
        'search_url' => env('MIDEA_SEARCH_URL'),
        'search_param' => env('MIDEA_SEARCH_PARAM', 'search'),
        'user_agent' => env('MIDEA_USER_AGENT'),
        'image_path' => env('MIDEA_IMAGE_PATH', '/uploads/products/'),
        'spec_xpath' => [
            '//table//tr[td]',
            '//*[contains(@class,"spec")]//li',
            '//*[contains(@class,"characteristic")]//div[count(*)=2]',
        ],
        'description_xpath' => [
            '//*[contains(@class,"description")]',
            '//*[@id="description"]',
        ],
    ],

    'alneo' => [
        'shop_url' => env('ALNEO_SHOP_URL', 'https://alneo.ge/shop/?ppp=-1'),
        'user_agent' => env('ALNEO_USER_AGENT'),
    ],

    'ingco' => [
        'user_agent' => env('INGCO_USER_AGENT'),
    ],

    'kontakt' => [
        'user_agent' => env('KONTAKT_USER_AGENT'),
    ],

    'metromart' => [
        'user_agent' => env('METROMART_USER_AGENT'),
    ],

    'facebook' => [
        'pixel_id' => env('FACEBOOK_PIXEL_ID'),
        'access_token' => env('FACEBOOK_ACCESS_TOKEN'),
        'api_version' => env('FACEBOOK_API_VERSION', 'v24.0'),
        'test_event_code' => env('FACEBOOK_TEST_EVENT_CODE') ?: null,
        'queue_events' => (bool) env('FACEBOOK_QUEUE_EVENTS', true),
    ],

];

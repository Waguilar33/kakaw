<?php

return [

    'default' => env('KAKAW_CERTIFIER', 'digifact'),

    'certifiers' => [

        'digifact' => [
            'driver' => 'digifact',
            'environment' => env('KAKAW_DIGIFACT_ENVIRONMENT', 'test'),
            'tax_id' => env('KAKAW_DIGIFACT_TAX_ID'),
            'username' => env('KAKAW_DIGIFACT_USERNAME'),
            'password' => env('KAKAW_DIGIFACT_PASSWORD'),
            'base_url' => env('KAKAW_DIGIFACT_BASE_URL'),
            'timeout' => (int) env('KAKAW_DIGIFACT_TIMEOUT', 60),
            'connect_timeout' => (int) env('KAKAW_DIGIFACT_CONNECT_TIMEOUT', 5),
        ],

    ],

    'cache' => [
        'store' => env('KAKAW_CACHE_STORE'),
    ],

    'issuer' => [
        'tax_id' => env('KAKAW_ISSUER_TAX_ID'),
        'name' => env('KAKAW_ISSUER_NAME'),
        'commercial_name' => env('KAKAW_ISSUER_COMMERCIAL_NAME'),
        'establishment_code' => env('KAKAW_ISSUER_ESTABLISHMENT_CODE', '1'),
        'vat_affiliation' => env('KAKAW_ISSUER_VAT_AFFILIATION', 'GEN'),
        'address' => [
            'street' => env('KAKAW_ISSUER_STREET'),
            'postal_code' => env('KAKAW_ISSUER_POSTAL_CODE'),
            'municipality' => env('KAKAW_ISSUER_MUNICIPALITY'),
            'department' => env('KAKAW_ISSUER_DEPARTMENT'),
            'country' => 'GT',
        ],
        'phrases' => [
            ['type' => 1, 'scenario' => 1],
        ],
    ],

];

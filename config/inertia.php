<?php

return [

    'ssr' => [

        'enabled' => (bool) env('INERTIA_SSR_ENABLED', true),

        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),

        'ensure_bundle_exists' => (bool) env('INERTIA_SSR_ENSURE_BUNDLE_EXISTS', true),

    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | This app resolves Vue pages from resources/js/pages (see resources/js/app.js).
    | Defaults in inertia-laravel use resources/js/Pages — override so assertions
    | and optional disk checks match the real layout.
    |
    */

    'ensure_pages_exist' => false,

    'page_paths' => [
        resource_path('js/pages'),
    ],

    'page_extensions' => [
        'js',
        'jsx',
        'svelte',
        'ts',
        'tsx',
        'vue',
    ],

    'use_script_element_for_initial_page' => (bool) env('INERTIA_USE_SCRIPT_ELEMENT_FOR_INITIAL_PAGE', false),

    'testing' => [

        'ensure_pages_exist' => true,

        'page_paths' => [
            resource_path('js/pages'),
        ],

        'page_extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],

    ],

    'history' => [

        'encrypt' => (bool) env('INERTIA_ENCRYPT_HISTORY', false),

    ],

];

<?php

return [

    /* SEO branding and defaults for meta tags, Open Graph, Twitter Card, and JSON-LD.
     * Production APP_URL must be the canonical HTTPS origin without a trailing slash. */
    'site_name' => env('SEO_SITE_NAME', env('APP_NAME', 'DForm')),

    'default_description' => env(
        'SEO_DEFAULT_DESCRIPTION',
        'Platform manajemen acara dan formulir pendaftaran digital — kelola event, pendaftaran, QR absensi, dan pelaporan dalam satu tempat.'
    ),

    /** BCP47 locale for `og:locale`, e.g. `id_ID`. */
    'locale' => env('SEO_LOCALE', 'id_ID'),

    /** Language code for the `<html lang>` attribute. */
    'html_lang' => env('SEO_HTML_LANG', 'id'),

    /** Default `og:image`/`twitter:image` path relative to `public/`. */
    'default_og_image' => env('SEO_DEFAULT_OG_IMAGE', 'images/logo.webp'),

    /** @var string|null Twitter @handle tanpa @ */
    'twitter_site' => env('SEO_TWITTER_SITE'),

    'twitter_creator' => env('SEO_TWITTER_CREATOR'),

    /** Browser chrome color exposed through `theme-color`. */
    'theme_color' => env('SEO_THEME_COLOR', '#0f172a'),

    /** Optional search-console verification content values. */
    'google_site_verification' => env('SEO_GOOGLE_SITE_VERIFICATION'),
    'bing_site_verification' => env('SEO_BING_SITE_VERIFICATION'),
    'yandex_verification' => env('SEO_YANDEX_VERIFICATION'),

    /** Path prefixes excluded from indexing and used by the robots generator. */
    'disallow_paths' => [
        '/admin',
        '/user',
        '/dashboard',
        '/auth',
        '/info',
    ],

];

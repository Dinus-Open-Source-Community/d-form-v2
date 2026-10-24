<!DOCTYPE html>
<html lang="{{ config('seo.html_lang', 'id') }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="theme-color" content="{{ config('seo.theme_color', '#0f172a') }}" />
        <meta name="color-scheme" content="light dark" />
        <meta name="format-detection" content="telephone=no" />
        @if (filled(config('seo.google_site_verification')))
            <meta name="google-site-verification" content="{{ config('seo.google_site_verification') }}" />
        @endif
        @if (filled(config('seo.bing_site_verification')))
            <meta name="msvalidate.01" content="{{ config('seo.bing_site_verification') }}" />
        @endif
        @if (filled(config('seo.yandex_verification')))
            <meta name="yandex-verification" content="{{ config('seo.yandex_verification') }}" />
        @endif
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            rel="stylesheet"
            href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap"
        />
        <link rel="icon" href="/favicon.ico" sizes="any" />
        <link rel="icon" type="image/png" sizes="192x192" href="/favicon-192.png" />
        <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
        <script
            defer
            src="https://seeyu.doscom.org/script.js"
            data-website-id="101b4f55-d333-41de-bc03-5a92eecc4b13"
        ></script>
    </head>
    <body>
        @inertia
    </body>
</html>

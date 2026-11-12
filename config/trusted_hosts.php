<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Hosts
    |--------------------------------------------------------------------------
    |
    | The host of APP_URL is always accepted. TRUSTED_HOSTS adds exact extra
    | hosts, comma separated (or an array when defined in a config override).
    | Subdomains are not trusted implicitly: "api.example.com" must be listed
    | on its own. Empty means "APP_URL host only" - never "trust every host",
    | because an empty allowlist would disable the check entirely.
    |
    */

    'hosts' => env('TRUSTED_HOSTS'),

];

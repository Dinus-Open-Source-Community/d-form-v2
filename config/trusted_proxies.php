<?php

use Illuminate\Http\Request;

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | The app always runs behind a TLS-terminating ingress, so the peer address
    | is the ingress, not the browser. Without this the client IP, scheme, host
    | and port all resolve to the container network instead of the real request.
    |
    */

    // Empty falls back to the Docker private networks listed below.
    'proxies' => env('TRUSTED_PROXIES'),

    // The ingress and load balancer live in the private Docker networks.
    // IpUtils treats IPv4-mapped peers as their embedded IPv4 address; only private
    // and loopback ranges are trusted, so mapped public peers remain untrusted.
    'default_proxies' => [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.1',
        '::1',
    ],

    // Trust only headers set by the ingress; path-rewriting and mask values remain
    // untrusted so a compromised peer cannot forge them.
    'headers' => Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT,

];

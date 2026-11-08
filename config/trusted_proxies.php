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
    // Peer IPv4-mapped IPv6 addresses (::ffff:172.x.x.x) do not match IPv4 CIDRs,
    // so their forwarded headers are ignored (fail-closed); make the ingress connect
    // over IPv4 when the allowlist is IPv4-based.
    'default_proxies' => [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.1',
        '::1',
    ],

    // Only the headers the ingress actually sets. X-Forwarded-Prefix (a header that can
    // rewrite the request path) and HEADER_X_FORWARDED_AWS_ELB (a mask, not a header) stay
    // untrusted so a compromised peer cannot forge them.
    'headers' => Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT,

];

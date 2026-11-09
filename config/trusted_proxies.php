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
    // Since symfony/http-foundation 7.4.23 (GHSA-xg2q) IpUtils::checkIp() judges an
    // IPv4-mapped IPv6 peer as the IPv4 address it embeds, so a dual-stack ingress
    // reporting ::ffff:172.18.0.5 still matches 172.16.0.0/12 and is trusted. Only
    // private/loopback ranges are listed, so mapped public peers stay untrusted.
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

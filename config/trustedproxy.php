<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | The proxies whose X-Forwarded-* headers this application will believe.
    | Read by bootstrap/app.php.
    |
    | WHY THIS MATTERS. Behind a TLS-terminating load balancer, every request
    | reaches PHP over plain http. Without trusting the proxy:
    |
    |   - $request->secure() is false, so a `secure` session cookie is never
    |     sent and the session silently stops working over HTTPS;
    |   - route()/url() generate http:// links on an https:// site, which
    |     browsers then block as mixed content;
    |   - $request->ip() is the balancer's address, so rate limiting keys
    |     every visitor to the same bucket.
    |
    | THE DEFAULT IS '*', AND THAT IS A REAL TRADE-OFF. Trusting every proxy
    | means trusting whatever X-Forwarded-For a client sends — UNLESS the
    | balancer overwrites it, which every managed one (ALB, Cloudflare,
    | nginx with the standard config) does. It is the right default because
    | the alternative — a wrong or empty list — fails silently and
    | confusingly, whereas this fails only if the app is exposed directly to
    | the internet with no proxy at all.
    |
    | IF THE APP IS REACHABLE WITHOUT GOING THROUGH THE BALANCER, set
    | TRUSTED_PROXIES to the balancer's actual addresses (comma-separated).
    | An attacker could otherwise spoof their IP for rate limiting and for
    | anything that logs it.
    |
    */

    'proxies' => match (true) {
        env('TRUSTED_PROXIES') === null => '*',
        env('TRUSTED_PROXIES') === '*' => '*',
        default => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES'))))),
    },

];

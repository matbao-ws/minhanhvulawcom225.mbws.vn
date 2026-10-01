<?php

/*
 * Read by Laravel's TrustProxies middleware on every request, after .env and
 * the config cache are loaded. (An env() call in bootstrap/app.php runs before
 * .env is loaded, so a value set there in .env was silently ignored.)
 *
 * Behind a TLS-terminating proxy the app otherwise sees plain HTTP and builds
 * http:// asset URLs, which browsers block as mixed content. "*" trusts the
 * proxy that connects to this app (only safe when nothing else can reach it),
 * or give a comma-separated list of proxy addresses. Empty trusts nobody.
 */
return [
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];

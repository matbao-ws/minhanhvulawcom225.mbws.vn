<?php

/*
 * Core releases (AI_BUILD_PROMPT.md, "Cập nhật phiên bản core"). Core-owned: an update ships it,
 * which is how a new signing key reaches every website before releases are signed with it.
 */
return [
    /*
     * Public keys allowed to sign a release: base64 of the DER public key, as printed by
     * `php artisan core:keygen`. A release signed by any other key is refused. To rotate, ship a
     * release that lists old and new, sign with the new one from then on, drop the old one later.
     * The private key lives only in the GitHub secret CORE_RELEASE_SIGNING_KEY.
     */
    'public_keys' => [
        'MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEDMfz7jysVO2mOwuBnkvS5nbfqrANPkHb2OvXOhDZiiifgetY3KLR9wq1sLyCgxAI2HI0pcs76W8dej5hS5WKmQ==',
    ],

    /*
     * The oldest version that may update straight to the release being built. Raise it when a
     * release needs an earlier one installed first (typically after a contract migration).
     */
    'min_from' => '1.0.0',

    /*
     * The CLI php the updater starts for migrate/package:discover/core:health. Empty = the binary
     * running the update (right under cron). Set it on hosts with several PHP versions.
     */
    'php_binary' => env('CORE_UPDATE_PHP_BINARY'),

    /*
     * Off where the code is not this website's to change: a Docker image (staging), a developer
     * machine. Then the website neither polls the update service nor runs requested updates.
     */
    'enabled' => (bool) env('CORE_UPDATE_ENABLED', true),
];

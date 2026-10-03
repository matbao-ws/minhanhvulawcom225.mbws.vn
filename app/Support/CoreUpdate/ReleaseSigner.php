<?php

namespace App\Support\CoreUpdate;

use RuntimeException;

/**
 * ECDSA P-256 / SHA-256 signatures over the exact bytes of release.json (a detached
 * release.json.sig), through ext-openssl — which Laravel already requires, so every website can
 * verify, unlike sodium which some shared hosts lack.
 *
 * Public keys are written as base64 of their DER form (one line, fits in config); a key's id is
 * the first 16 hex characters of the sha256 of that DER.
 */
class ReleaseSigner
{
    /** @return array{private_pem: string, public: string, key_id: string} */
    public static function generate(): array
    {
        $options = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1', 'private_key_bits' => 384];
        $config = null;

        // PHP on Windows often has no openssl.cnf; key generation (not verification) needs one.
        if (! getenv('OPENSSL_CONF') || ! is_file((string) getenv('OPENSSL_CONF'))) {
            $config = tempnam(sys_get_temp_dir(), 'ossl');
            file_put_contents($config, "[req]\ndistinguished_name=dn\n[dn]\n");
            $options['config'] = $config;
        }

        try {
            $key = openssl_pkey_new($options);
            if ($key === false || ! openssl_pkey_export($key, $pem, null, $config ? ['config' => $config] : [])) {
                throw new RuntimeException('Could not generate a key: '.openssl_error_string());
            }
        } finally {
            if ($config) {
                @unlink($config);
            }
        }

        $public = self::publicFromPem(openssl_pkey_get_details($key)['key']);

        return ['private_pem' => $pem, 'public' => $public, 'key_id' => self::keyId($public)];
    }

    public static function sign(string $payload, string $privatePem): string
    {
        $key = openssl_pkey_get_private($privatePem);
        if ($key === false || openssl_pkey_get_details($key)['type'] !== OPENSSL_KEYTYPE_EC) {
            throw new RuntimeException('The signing key is not a valid EC private key.');
        }
        if (! openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Signing failed.');
        }

        return base64_encode($signature);
    }

    /** The public key (base64 DER) of a private key. */
    public static function publicOf(string $privatePem): string
    {
        $key = openssl_pkey_get_private($privatePem);
        if ($key === false) {
            throw new RuntimeException('The signing key is not a valid private key.');
        }

        return self::publicFromPem(openssl_pkey_get_details($key)['key']);
    }

    public static function verify(string $payload, string $signatureBase64, string $public): bool
    {
        $signature = base64_decode(trim($signatureBase64), true);
        $key = openssl_pkey_get_public(self::pem($public));

        return $signature !== false && $key !== false && openssl_verify($payload, $signature, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    public static function keyId(string $public): string
    {
        return substr(hash('sha256', (string) base64_decode($public, true)), 0, 16);
    }

    private static function publicFromPem(string $pem): string
    {
        return preg_replace('/-----[^-]+-----|\s+/', '', $pem);
    }

    private static function pem(string $public): string
    {
        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(trim($public), 64, "\n")."-----END PUBLIC KEY-----\n";
    }
}

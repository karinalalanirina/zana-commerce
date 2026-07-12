<?php

namespace App\Support;

use App\Models\TranslationProvider;

class ZanaTranslationProviderPresenter
{
    public static function isSecretKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (['secret', 'private', 'password', 'token', 'api_key', 'credential', 'signing'] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function publicCredentialMap(array $credentials): array
    {
        $out = [];

        foreach ($credentials as $key => $value) {
            if (! is_string($key) || self::isSecretKey($key)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $out[$key] = $value === null ? '' : (string) $value;
            }
        }

        return $out;
    }

    public static function adminPublicCredentialValues(TranslationProvider $provider, array $fields): array
    {
        $creds = $provider->getDecryptedCredentials();
        $public = self::publicCredentialMap($creds);
        $out = [];

        foreach ($fields as $key => $spec) {
            if (array_key_exists($key, $public)) {
                $out[$key] = $public[$key];
            }
        }

        return $out;
    }

    public static function adminCredentialSetMap(TranslationProvider $provider, array $fields): array
    {
        $creds = $provider->getDecryptedCredentials();
        $out = [];

        foreach ($fields as $key => $spec) {
            $value = $creds[$key] ?? null;
            $out[$key] = ! blank($value);
        }

        return $out;
    }
}

<?php

namespace App\Support;

use App\Models\PaymentGateway;

class ZanaPaymentGatewayPresenter
{
    /** @var string[] */
    private const PUBLIC_CREDENTIAL_KEYS = [
        'key',
        'key_id',
        'public_key',
        'publishable_key',
        'client_id',
        'merchant_id',
        'app_id',
        'account_id',
        'mode',
        'environment',
        'username',
    ];

    public static function isSecretKey(string $key): bool
    {
        $key = strtolower(trim($key));

        return str_contains($key, 'secret')
            || str_contains($key, 'private')
            || str_contains($key, 'password')
            || str_contains($key, 'webhook')
            || str_contains($key, 'token')
            || str_contains($key, 'credential')
            || str_contains($key, 'signing');
    }

    public static function looksPublic(string $key): bool
    {
        $key = strtolower(trim($key));

        return in_array($key, self::PUBLIC_CREDENTIAL_KEYS, true)
            || str_starts_with($key, 'public_')
            || str_starts_with($key, 'publishable_');
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, string>
     */
    public static function publicCredentialMap(array $credentials): array
    {
        $public = [];

        foreach ($credentials as $key => $value) {
            $key = (string) $key;
            if (self::isSecretKey($key) || ! self::looksPublic($key)) {
                continue;
            }

            if (! is_scalar($value) || $value === null) {
                continue;
            }

            $public[$key] = (string) $value;
        }

        return $public;
    }

    /**
     * @param  iterable<PaymentGateway>  $gateways
     * @return array<string, string>
     */
    public static function legacyFlatPublicData(iterable $gateways): array
    {
        $data = [];

        foreach ($gateways as $gateway) {
            foreach (self::publicCredentialMap($gateway->getDecryptedCredentials()) as $key => $value) {
                $data[$gateway->slug . '_' . $key] = $value;
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function mobileGateway(PaymentGateway $gateway): array
    {
        return [
            'id' => $gateway->id,
            'slug' => $gateway->slug,
            'name' => $gateway->name,
            'display_name' => $gateway->name,
            'description' => $gateway->description,
            'enabled' => (bool) $gateway->is_active,
            'payment_method' => $gateway->slug,
            'mode' => (string) ($gateway->mode ?: 'sandbox'),
            'supported_currencies' => array_values($gateway->supported_currencies ?? []),
            'public_keys' => self::publicCredentialMap($gateway->getDecryptedCredentials()),
        ];
    }

    /**
     * @param  array<string, mixed>  $credentialFields
     * @return array<string, string>
     */
    public static function adminPublicCredentialValues(PaymentGateway $gateway, array $credentialFields): array
    {
        $creds = $gateway->getDecryptedCredentials();
        $values = [];

        foreach ($credentialFields as $key => $spec) {
            $key = (string) $key;
            if (self::isSecretKey($key) || ! array_key_exists($key, $creds)) {
                continue;
            }

            $value = $creds[$key];
            if (is_scalar($value) && $value !== null) {
                $values[$key] = (string) $value;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $credentialFields
     * @return array<string, bool>
     */
    public static function adminCredentialSetMap(PaymentGateway $gateway, array $credentialFields): array
    {
        $creds = $gateway->getDecryptedCredentials();
        $set = [];

        foreach ($credentialFields as $key => $spec) {
            $key = (string) $key;
            $set[$key] = ! empty($creds[$key]);
        }

        return $set;
    }
}

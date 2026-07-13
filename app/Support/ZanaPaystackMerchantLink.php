<?php

namespace App\Support;

use App\Models\WaOrder;
use App\Models\WaStorefront;
use App\Services\Storefront\StorefrontPaymentService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

class ZanaPaystackMerchantLink
{
    private const API_BASE = 'https://api.paystack.co';

    public static function storefrontConfig(?WaStorefront $storefront, bool $withSecrets = false): array
    {
        $raw = ZanaAfricaPayments::storefrontConfig($storefront);

        return [
            'enabled' => (bool) ($raw['paystack_enabled'] ?? false),
            'public_key' => trim((string) ($raw['paystack_public_key'] ?? '')),
            'secret_key' => $withSecrets ? self::decryptSecret($raw['paystack_secret_key'] ?? '') : '',
            'reference_prefix' => trim((string) ($raw['paystack_reference_prefix'] ?? 'ZANA')),
            'fallback_customer_email' => trim((string) ($raw['paystack_fallback_customer_email'] ?? '')),
            'redirect_note' => trim((string) ($raw['paystack_redirect_note'] ?? '')),
            'has_secret_key' => !empty($raw['paystack_secret_key']),
        ];
    }

    public static function readiness(?WaStorefront $storefront): array
    {
        $config = self::storefrontConfig($storefront, true);
        $hasConfig = self::hasRequiredConfig($storefront);

        return [
            'enabled' => (bool) $config['enabled'],
            'configured' => $hasConfig,
            'can_generate' => (bool) $config['enabled'] && $hasConfig,
            'label' => !$config['enabled']
                ? 'Paystack order-link generation is off'
                : ($hasConfig ? 'Paystack ready for order links' : 'Paystack config incomplete'),
            'notes' => !$config['enabled']
                ? 'Enable Paystack link mode only if this merchant wants order-specific hosted payment links.'
                : ($hasConfig
                    ? 'Zana can generate an order-specific Paystack checkout link and then send or copy it through the existing merchant flow.'
                    : 'Add a Paystack secret key and a fallback customer email before generating order links.'),
        ];
    }

    public static function hasRequiredConfig(?WaStorefront $storefront): bool
    {
        $config = self::storefrontConfig($storefront, true);

        return $config['enabled']
            && $config['secret_key'] !== ''
            && filter_var($config['fallback_customer_email'], FILTER_VALIDATE_EMAIL);
    }

    public static function buildReference(WaOrder $order, ?WaStorefront $storefront = null): string
    {
        $config = self::storefrontConfig($storefront);
        $prefix = preg_replace('/[^A-Z0-9_-]+/i', '', strtoupper($config['reference_prefix'] ?: 'ZANA'));

        return trim($prefix . '-ORD-' . $order->id . '-' . now()->format('His'), '-');
    }

    public static function initializeForOrder(WaStorefront $storefront, WaOrder $order): array
    {
        $config = self::storefrontConfig($storefront, true);

        if (!$config['enabled']) {
            return ['ok' => false, 'error' => 'Paystack order-link generation is not enabled for this storefront.'];
        }
        if (!$config['has_secret_key']) {
            return ['ok' => false, 'error' => 'Missing Paystack secret key for this storefront.'];
        }

        $email = trim((string) ($order->customer_email ?: $config['fallback_customer_email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'A valid customer or fallback email is required before generating a Paystack link.'];
        }

        $amountMinor = (int) $order->total_minor;
        if ($amountMinor < 100) {
            return ['ok' => false, 'error' => 'Paystack requires an amount of at least 1.00 in the order currency.'];
        }

        $reference = self::buildReference($order, $storefront);
        $currency = strtoupper((string) ($order->currency_code ?: $storefront->currency_code ?: 'KES'));
        $redirectUrl = ($storefront->custom_domain_verified && $storefront->custom_domain
            ? 'https://' . $storefront->custom_domain
            : url('/s/' . $storefront->slug)) . '/order/' . $order->recovery_token;

        $body = [
            'email' => $email,
            'amount' => $amountMinor,
            'currency' => $currency,
            'reference' => $reference,
            'callback_url' => $redirectUrl,
            'metadata' => array_filter([
                'wa_order_id' => $order->id,
                'storefront_id' => $storefront->id,
                'workspace_id' => $storefront->workspace_id,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'redirect_note' => $config['redirect_note'],
            ], static fn ($value) => $value !== null && $value !== ''),
        ];

        try {
            $response = Http::withToken($config['secret_key'])
                ->acceptJson()
                ->timeout(20)
                ->post(self::API_BASE . '/transaction/initialize', $body);

            $json = $response->json() ?: [];
            if (($json['status'] ?? false) !== true || empty($json['data']['authorization_url'])) {
                return [
                    'ok' => false,
                    'error' => (string) ($json['message'] ?? 'Paystack link generation failed.'),
                    'response' => $json,
                ];
            }

            return [
                'ok' => true,
                'url' => (string) $json['data']['authorization_url'],
                'access_code' => (string) ($json['data']['access_code'] ?? ''),
                'reference' => (string) ($json['data']['reference'] ?? $reference),
                'currency' => $currency,
                'amount_minor' => $amountMinor,
                'email_used' => $email,
                'redirect_url' => $redirectUrl,
                'provider' => 'paystack',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => 'Paystack link generation exception: ' . $e->getMessage(),
            ];
        }
    }

    public static function encryptSecret(?string $value): ?string
    {
        return StorefrontPaymentService::encryptSecret($value);
    }

    private static function decryptSecret(mixed $value): string
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return $value;
        }
    }
}

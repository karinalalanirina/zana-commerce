<?php

namespace App\Support;

use App\Models\WaOrder;
use App\Models\WaStorefront;
use App\Services\Storefront\StorefrontPaymentService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ZanaDarajaSandbox
{
    private const TOKEN_URL = 'https://sandbox.safaricom.co.ke/oauth/v1/generate';
    private const STK_URL = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

    public static function enabled(): bool
    {
        return (bool) config('zana.enable_daraja_sandbox', false);
    }

    public static function sandboxOnly(): bool
    {
        return (bool) config('zana.daraja_sandbox_only', true);
    }

    public static function storefrontConfig(?WaStorefront $storefront, bool $withSecrets = false): array
    {
        $raw = ZanaAfricaPayments::storefrontConfig($storefront);

        return [
            'enabled' => (bool) ($raw['daraja_enabled'] ?? false),
            'environment' => (string) ($raw['daraja_environment'] ?? 'sandbox'),
            'shortcode' => trim((string) ($raw['daraja_shortcode'] ?? '')),
            'transaction_type' => trim((string) ($raw['daraja_transaction_type'] ?? 'CustomerPayBillOnline')),
            'callback_enabled' => (bool) ($raw['daraja_callback_enabled'] ?? true),
            'reference_prefix' => trim((string) ($raw['daraja_reference_prefix'] ?? 'ORDER')),
            'callback_token' => trim((string) ($raw['daraja_callback_token'] ?? '')),
            'consumer_key' => $withSecrets ? self::decryptSecret($raw['daraja_consumer_key'] ?? '') : '',
            'consumer_secret' => $withSecrets ? self::decryptSecret($raw['daraja_consumer_secret'] ?? '') : '',
            'passkey' => $withSecrets ? self::decryptSecret($raw['daraja_passkey'] ?? '') : '',
            'has_consumer_key' => !empty($raw['daraja_consumer_key']),
            'has_consumer_secret' => !empty($raw['daraja_consumer_secret']),
            'has_passkey' => !empty($raw['daraja_passkey']),
        ];
    }

    public static function readiness(?WaStorefront $storefront): array
    {
        $config = self::storefrontConfig($storefront);
        $phoneExample = 'Use full Kenya mobile numbers like 2547XXXXXXXX. Zana normalizes +254 / 07 / 7 formats before sandbox requests.';

        if (!self::enabled()) {
            return [
                'enabled' => false,
                'configured' => false,
                'can_initiate' => false,
                'label' => 'Hidden by feature flag',
                'notes' => 'Daraja sandbox is disabled by the current Zana feature flags.',
                'phone_guidance' => $phoneExample,
                'callback_url' => null,
            ];
        }

        $configured = self::hasRequiredConfig($storefront);

        return [
            'enabled' => true,
            'configured' => $configured,
            'can_initiate' => $configured && $config['enabled'],
            'label' => $configured ? 'Sandbox ready for STK testing' : 'Sandbox config incomplete',
            'notes' => $configured
                ? 'Sandbox STK initiation is available for staging validation. Production hardening still comes later.'
                : 'Add shortcode, consumer key, consumer secret, and passkey before testing STK initiation.',
            'phone_guidance' => $phoneExample,
            'callback_url' => $config['callback_token'] !== '' ? self::callbackUrl($storefront) : null,
            'environment' => $config['environment'],
            'callback_enabled' => $config['callback_enabled'],
        ];
    }

    public static function hasRequiredConfig(?WaStorefront $storefront): bool
    {
        $config = self::storefrontConfig($storefront, true);

        return self::enabled()
            && $config['enabled']
            && $config['environment'] === 'sandbox'
            && $config['shortcode'] !== ''
            && $config['consumer_key'] !== ''
            && $config['consumer_secret'] !== ''
            && $config['passkey'] !== '';
    }

    public static function buildReference(WaOrder $order, ?WaStorefront $storefront = null): string
    {
        $config = self::storefrontConfig($storefront);
        $prefix = strtoupper(trim((string) ($config['reference_prefix'] ?: 'ORDER')));

        return Str::limit($prefix . '-' . $order->id, 20, '');
    }

    public static function callbackUrl(?WaStorefront $storefront): ?string
    {
        $config = self::storefrontConfig($storefront);
        if ($config['callback_token'] === '') {
            return null;
        }

        return route('storefront.pay.daraja-sandbox.webhook', ['token' => $config['callback_token']]);
    }

    public static function ensureCallbackToken(array $existing): string
    {
        $token = trim((string) ($existing['daraja_callback_token'] ?? ''));

        return $token !== '' ? $token : Str::random(32);
    }

    public static function normalizeKenyaPhone(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '254') && strlen($digits) === 12) {
            return $digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '254' . substr($digits, 1);
        }
        if (str_starts_with($digits, '7') && strlen($digits) === 9) {
            return '254' . $digits;
        }

        return null;
    }

    public static function initiateForOrder(WaStorefront $storefront, WaOrder $order): array
    {
        if (!self::enabled()) {
            return ['ok' => false, 'error' => 'Daraja sandbox is disabled by feature flag.'];
        }

        $config = self::storefrontConfig($storefront, true);
        if (!$config['enabled']) {
            return ['ok' => false, 'error' => 'Daraja sandbox is not enabled for this storefront.'];
        }
        if ($config['environment'] !== 'sandbox' && self::sandboxOnly()) {
            return ['ok' => false, 'error' => 'Only the Daraja sandbox path is enabled right now.'];
        }
        if (!self::hasRequiredConfig($storefront)) {
            return ['ok' => false, 'error' => 'Daraja sandbox configuration is incomplete for this storefront.'];
        }

        $phone = self::normalizeKenyaPhone($order->customer_phone);
        if (!$phone) {
            return ['ok' => false, 'error' => 'Customer phone must be a valid Kenya mobile number for STK Push testing.'];
        }

        $callbackUrl = $config['callback_enabled'] ? self::callbackUrl($storefront) : null;
        if ($config['callback_enabled'] && !$callbackUrl) {
            return ['ok' => false, 'error' => 'Daraja callback token is missing for this storefront.'];
        }

        $amount = max(1, (int) round(((int) $order->total_minor) / 100));
        $reference = self::buildReference($order, $storefront);
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($config['shortcode'] . $config['passkey'] . $timestamp);

        $tokenResponse = Http::asForm()
            ->withBasicAuth($config['consumer_key'], $config['consumer_secret'])
            ->acceptJson()
            ->timeout(20)
            ->get(self::TOKEN_URL, ['grant_type' => 'client_credentials']);

        if (!$tokenResponse->successful()) {
            return [
                'ok' => false,
                'error' => 'Could not get a Daraja sandbox access token.',
                'http_status' => $tokenResponse->status(),
                'response' => $tokenResponse->json(),
            ];
        }

        $token = (string) ($tokenResponse->json('access_token') ?? '');
        if ($token === '') {
            return ['ok' => false, 'error' => 'Daraja sandbox access token was empty.'];
        }

        $payload = [
            'BusinessShortCode' => $config['shortcode'],
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => $config['transaction_type'] ?: 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $config['shortcode'],
            'PhoneNumber' => $phone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $reference,
            'TransactionDesc' => 'Zana order ' . $reference,
        ];

        $stkResponse = Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->post(self::STK_URL, $payload);

        if (!$stkResponse->successful()) {
            return [
                'ok' => false,
                'error' => 'Daraja sandbox STK initiation failed.',
                'http_status' => $stkResponse->status(),
                'response' => $stkResponse->json(),
                'request' => $payload,
            ];
        }

        $response = $stkResponse->json();
        $merchantRequestId = (string) ($response['MerchantRequestID'] ?? '');
        $checkoutRequestId = (string) ($response['CheckoutRequestID'] ?? '');

        return [
            'ok' => true,
            'merchant_request_id' => $merchantRequestId,
            'checkout_request_id' => $checkoutRequestId,
            'response_code' => (string) ($response['ResponseCode'] ?? ''),
            'response_description' => (string) ($response['ResponseDescription'] ?? ''),
            'customer_message' => (string) ($response['CustomerMessage'] ?? ''),
            'request_phone' => $phone,
            'request_amount' => $amount,
            'request_reference' => $reference,
            'request_payload' => $payload,
            'callback_url' => $callbackUrl,
        ];
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

    public static function encryptSecret(?string $value): ?string
    {
        return StorefrontPaymentService::encryptSecret($value);
    }
}

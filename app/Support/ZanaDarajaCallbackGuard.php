<?php

namespace App\Support;

use App\Models\User;
use App\Models\WaOrder;
use App\Models\WaStorefront;

class ZanaDarajaCallbackGuard
{
    public static function resolveStorefrontByToken(string $token): ?WaStorefront
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return WaStorefront::query()
            ->get()
            ->first(function (WaStorefront $storefront) use ($token) {
                $config = ZanaAfricaPayments::storefrontConfig($storefront);

                return trim((string) ($config['daraja_callback_token'] ?? '')) === $token;
            });
    }

    public static function handleCallback(WaStorefront $storefront, array $payload): array
    {
        $callback = $payload['Body']['stkCallback'] ?? null;
        if (!is_array($callback)) {
            return ['ok' => false, 'error' => 'invalid_payload'];
        }

        $merchantRequestId = trim((string) ($callback['MerchantRequestID'] ?? ''));
        $checkoutRequestId = trim((string) ($callback['CheckoutRequestID'] ?? ''));
        $resultCode = (int) ($callback['ResultCode'] ?? 1);
        $resultDesc = trim((string) ($callback['ResultDesc'] ?? ''));
        $metadata = self::metadataMap($callback['CallbackMetadata']['Item'] ?? []);
        $fingerprint = self::callbackFingerprint($merchantRequestId, $checkoutRequestId, $resultCode, $metadata);

        $order = self::matchOrder($storefront, $merchantRequestId, $checkoutRequestId);
        if (!$order) {
            return [
                'ok' => false,
                'error' => 'unmatched_callback',
                'merchant_request_id' => $merchantRequestId,
                'checkout_request_id' => $checkoutRequestId,
            ];
        }

        $existingDaraja = ZanaManualPayment::paymentMeta($order)['daraja'] ?? [];
        $processed = is_array($existingDaraja['processed_callback_keys'] ?? null)
            ? $existingDaraja['processed_callback_keys']
            : [];
        if (in_array($fingerprint, $processed, true)) {
            $existingDaraja['duplicate_callback_count'] = (int) ($existingDaraja['duplicate_callback_count'] ?? 0) + 1;
            $existingDaraja['last_duplicate_callback_at'] = now()->toIso8601String();
            $order->meta_json = ZanaManualPayment::mergeIntoOrder($order, [
                'daraja' => $existingDaraja,
            ]);
            $order->save();

            return ['ok' => true, 'duplicate' => true, 'order_id' => $order->id];
        }

        $processed[] = $fingerprint;
        $darajaData = array_merge($existingDaraja, [
            'status' => $resultCode === 0 ? 'callback_success' : 'callback_failed',
            'merchant_request_id' => $merchantRequestId,
            'checkout_request_id' => $checkoutRequestId,
            'callback_received_at' => now()->toIso8601String(),
            'callback_result_code' => $resultCode,
            'callback_result_desc' => $resultDesc,
            'callback_metadata' => $metadata,
            'processed_callback_keys' => array_slice($processed, -10),
            'last_callback_payload' => $payload,
        ]);

        $paymentAttributes = [
            'payment_method' => 'daraja_stk',
            'daraja' => $darajaData,
        ];
        $eventType = 'daraja_callback_failed';

        if ($resultCode === 0) {
            $paymentAttributes = array_merge($paymentAttributes, [
                'status' => ZanaManualPayment::paymentStatus($order) === 'paid_confirmed' ? 'paid_confirmed' : 'customer_says_paid',
                'transaction_reference' => trim((string) ($metadata['MpesaReceiptNumber'] ?? ($existingDaraja['transaction_reference'] ?? ''))),
                'amount_received' => ZanaManualPayment::parseAmount((string) ($metadata['Amount'] ?? '')),
                'amount_received_currency' => $order->currency_code ?: 'KES',
                'payer_note' => trim((string) ($metadata['PhoneNumber'] ?? ($existingDaraja['request_phone'] ?? ''))),
                'confirmation_note' => $resultDesc !== '' ? $resultDesc : 'Daraja sandbox callback success received.',
                'customer_says_paid' => true,
                'customer_says_paid_at' => now()->toIso8601String(),
                'customer_says_paid_by' => 'Daraja Sandbox Callback',
            ]);
            $eventType = 'daraja_callback_success';
        } else {
            $paymentAttributes = array_merge($paymentAttributes, [
                'status' => 'payment_failed',
                'confirmation_note' => $resultDesc !== '' ? $resultDesc : 'Daraja sandbox callback failed.',
            ]);
        }

        $systemActor = User::query()->find(1);
        $order->meta_json = ZanaManualPayment::mergeIntoOrder(
            $order,
            $paymentAttributes,
            $systemActor,
            $eventType,
            [
                'merchant_request_id' => $merchantRequestId,
                'checkout_request_id' => $checkoutRequestId,
                'callback_result_code' => $resultCode,
                'callback_result_desc' => $resultDesc,
            ]
        );
        $order->save();

        return ['ok' => true, 'duplicate' => false, 'order_id' => $order->id];
    }

    private static function matchOrder(WaStorefront $storefront, string $merchantRequestId, string $checkoutRequestId): ?WaOrder
    {
        return WaOrder::query()
            ->where('storefront_id', $storefront->id)
            ->get()
            ->first(function (WaOrder $order) use ($merchantRequestId, $checkoutRequestId) {
                $daraja = ZanaManualPayment::paymentMeta($order)['daraja'] ?? [];

                return ($checkoutRequestId !== '' && (string) ($daraja['checkout_request_id'] ?? '') === $checkoutRequestId)
                    || ($merchantRequestId !== '' && (string) ($daraja['merchant_request_id'] ?? '') === $merchantRequestId);
            });
    }

    private static function metadataMap(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $mapped = [];
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['Name'])) {
                continue;
            }
            $mapped[(string) $item['Name']] = $item['Value'] ?? null;
        }

        return $mapped;
    }

    private static function callbackFingerprint(string $merchantRequestId, string $checkoutRequestId, int $resultCode, array $metadata): string
    {
        return sha1(json_encode([
            'merchant_request_id' => $merchantRequestId,
            'checkout_request_id' => $checkoutRequestId,
            'result_code' => $resultCode,
            'receipt' => $metadata['MpesaReceiptNumber'] ?? null,
            'phone' => $metadata['PhoneNumber'] ?? null,
            'amount' => $metadata['Amount'] ?? null,
        ]));
    }
}

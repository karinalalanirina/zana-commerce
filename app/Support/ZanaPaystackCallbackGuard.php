<?php

namespace App\Support;

use App\Models\User;
use App\Models\WaOrder;
use App\Models\WaStorefront;

class ZanaPaystackCallbackGuard
{
    public static function extractReference(array $payload): string
    {
        return trim((string) (
            $payload['data']['reference']
            ?? $payload['reference']
            ?? $payload['trxref']
            ?? ''
        ));
    }

    public static function resolveOrderByReference(string $reference): ?WaOrder
    {
        if ($reference === '') {
            return null;
        }

        return WaOrder::query()
            ->get()
            ->first(function (WaOrder $order) use ($reference) {
                $paystack = ZanaManualPayment::paystackMeta($order);

                return trim((string) ($paystack['reference'] ?? '')) === $reference;
            });
    }

    public static function resolveStorefrontForOrder(?WaOrder $order): ?WaStorefront
    {
        if (!$order) {
            return null;
        }

        return $order->storefront()->first();
    }

    public static function verifySignature(?WaStorefront $storefront, string $rawBody, ?string $signature): bool
    {
        if (!$storefront || !$signature) {
            return false;
        }

        $secret = ZanaPaystackMerchantLink::storefrontConfig($storefront, true)['secret_key'] ?? '';
        if ($secret === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawBody, $secret), trim($signature));
    }

    public static function handleWebhook(WaOrder $order, array $payload): array
    {
        $event = trim((string) ($payload['event'] ?? ''));
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $reference = self::extractReference($payload);
        $status = trim((string) ($data['status'] ?? ''));
        $amountMinor = (int) ($data['amount'] ?? 0);
        $currency = strtoupper(trim((string) ($data['currency'] ?? ($order->currency_code ?: 'KES'))));
        $paidAt = trim((string) ($data['paid_at'] ?? $data['transaction_date'] ?? ''));
        $gatewayPaymentId = trim((string) ($data['id'] ?? ''));
        $customerEmail = trim((string) ($data['customer']['email'] ?? ''));
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];

        $fingerprint = sha1(json_encode([
            'event' => $event,
            'reference' => $reference,
            'status' => $status,
            'gateway_payment_id' => $gatewayPaymentId,
            'amount' => $amountMinor,
        ]));

        $existingPaystack = ZanaManualPayment::paystackMeta($order);
        $processed = is_array($existingPaystack['processed_callback_keys'] ?? null)
            ? $existingPaystack['processed_callback_keys']
            : [];

        if (in_array($fingerprint, $processed, true)) {
            $existingPaystack['duplicate_callback_count'] = (int) ($existingPaystack['duplicate_callback_count'] ?? 0) + 1;
            $existingPaystack['last_duplicate_callback_at'] = now()->toIso8601String();
            $order->meta_json = ZanaManualPayment::mergeIntoOrder($order, [
                'paystack' => $existingPaystack,
            ]);
            $order->save();

            return ['ok' => true, 'duplicate' => true, 'order_id' => $order->id];
        }

        $processed[] = $fingerprint;
        $basePaystack = array_merge($existingPaystack, [
            'provider' => 'paystack',
            'reference' => $reference,
            'gateway_payment_id' => $gatewayPaymentId,
            'callback_event' => $event,
            'callback_status' => $status,
            'callback_received_at' => now()->toIso8601String(),
            'callback_amount_minor' => $amountMinor,
            'callback_currency' => $currency,
            'callback_customer_email' => $customerEmail,
            'callback_paid_at' => $paidAt !== '' ? $paidAt : null,
            'callback_metadata' => $metadata,
            'processed_callback_keys' => array_slice($processed, -10),
            'last_callback_payload' => $payload,
        ]);

        $expectedMinor = (int) $order->total_minor;
        $amountMatches = $amountMinor > 0 && $expectedMinor > 0 && $amountMinor === $expectedMinor;
        $success = in_array($event, ['charge.success', 'paymentrequest.success'], true) && $status === 'success';

        $attributes = [
            'payment_method' => 'payment_link',
            'paystack' => $basePaystack,
        ];
        $eventType = 'paystack_callback_received';

        if ($success && $amountMatches) {
            $attributes = array_merge($attributes, [
                'status' => 'paid_confirmed',
                'transaction_reference' => $reference,
                'amount_received' => ZanaManualPayment::parseAmount((string) ($amountMinor / 100)),
                'amount_received_currency' => $currency,
                'confirmation_note' => 'Paystack payment verified by signed callback.',
                'confirmed_at' => now()->toIso8601String(),
                'confirmed_by' => 'Paystack Callback',
                'customer_says_paid' => true,
                'customer_says_paid_at' => now()->toIso8601String(),
                'customer_says_paid_by' => 'Paystack Callback',
                'paystack' => array_merge($basePaystack, ['status' => 'confirmed']),
            ]);
            $eventType = 'paystack_payment_confirmed';
            $order->status = 'paid';
        } elseif ($success) {
            $attributes = array_merge($attributes, [
                'status' => 'customer_says_paid',
                'transaction_reference' => $reference,
                'amount_received' => $amountMinor > 0 ? ZanaManualPayment::parseAmount((string) ($amountMinor / 100)) : null,
                'amount_received_currency' => $currency,
                'confirmation_note' => 'Paystack callback received but amount requires merchant review.',
                'customer_says_paid' => true,
                'customer_says_paid_at' => now()->toIso8601String(),
                'customer_says_paid_by' => 'Paystack Callback',
                'paystack' => array_merge($basePaystack, ['status' => 'awaiting_verification']),
            ]);
            $eventType = 'paystack_callback_received';
            $order->status = 'confirmed';
        } else {
            $attributes = array_merge($attributes, [
                'status' => 'payment_failed',
                'transaction_reference' => $reference !== '' ? $reference : ($existingPaystack['reference'] ?? null),
                'confirmation_note' => 'Paystack callback reported a non-success status.',
                'paystack' => array_merge($basePaystack, ['status' => 'failed']),
            ]);
            $eventType = 'paystack_payment_failed';
            $order->status = 'cancelled';
        }

        $systemActor = User::query()->find(1);
        $order->meta_json = ZanaManualPayment::mergeIntoOrder($order, $attributes, $systemActor, $eventType, [
            'provider' => 'paystack',
            'transaction_reference' => $reference,
            'amount_matches_order' => $amountMatches ? 'yes' : 'no',
            'gateway_payment_id' => $gatewayPaymentId,
            'callback_status' => $status,
        ]);
        $order->save();

        return [
            'ok' => true,
            'duplicate' => false,
            'order_id' => $order->id,
            'success' => $success,
            'auto_confirmed' => $success && $amountMatches,
        ];
    }
}

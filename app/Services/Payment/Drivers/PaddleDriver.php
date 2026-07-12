<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * Paddle (Billing) payment gateway driver.
 *
 * Creates a one-off transaction with a non-catalog price and redirects
 * to Paddle's hosted checkout.
 *
 * @see https://developer.paddle.com/api-reference/
 */
class PaddleDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://sandbox-api.paddle.com';
    private const PROD_BASE    = 'https://api.paddle.com';

    public static function credentialFields(): array
    {
        return [
            'api_key'        => ['label' => 'API Key',            'type' => 'password', 'required' => true],
            'product_id'     => ['label' => 'Product ID',         'type' => 'text',     'required' => true],
            'webhook_secret' => ['label' => 'Webhook Secret Key', 'type' => 'password', 'required' => false],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $apiKey    = (string) $this->cred('api_key');
        $productId = (string) $this->cred('product_id');
        if ($apiKey === '' || $productId === '') return PaymentResult::failed('paddle_credentials_missing');

        $body = [
            'items' => [[
                'price' => [
                    'description'   => "Order #{$order->order_number}",
                    'product_id'    => $productId,
                    'billing_cycle' => null,
                    'unit_price' => [
                        'amount'        => (string) ((int) round((float) $order->amount * 100)),
                        'currency_code' => strtoupper($order->currency ?? 'USD'),
                    ],
                ],
                'quantity' => 1,
            ]],
            'custom_data' => [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
            ],
        ];

        try {
            $r = Http::withToken($apiKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->baseUrl() . '/transactions', $body);
            $json = $r->json() ?: [];
            if (isset($json['data']['checkout']['url'])) {
                return PaymentResult::redirect(
                    $json['data']['checkout']['url'],
                    $json['data']['id'] ?? null,
                    $json,
                );
            }
            return PaymentResult::failed('paddle: ' . ($json['error']['detail'] ?? 'create_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('paddle_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $txnId  = $payload['transaction_id'] ?? $payload['_ptxn'] ?? null;
        $status = $payload['status'] ?? '';
        if (!$txnId) return new PaymentResult(status: 'pending', payload: $payload);

        if ($status === 'completed' || $status === 'paid') {
            return PaymentResult::paid(gatewayPaymentId: (string) $txnId, payload: $payload);
        }
        return new PaymentResult(status: 'pending', gatewayPaymentId: (string) $txnId, payload: $payload);
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = (string) $this->cred('webhook_secret');
        if ($secret === '' || $signatureHeader === null) return true;

        $parts = [];
        foreach (explode(';', $signatureHeader) as $item) {
            [$k, $v] = explode('=', $item, 2) + [1 => ''];
            $parts[$k] = $v;
        }
        $ts = $parts['ts'] ?? '';
        $h1 = $parts['h1'] ?? '';
        if ($ts === '' || $h1 === '') return false;
        $expected = hash_hmac('sha256', $ts . ':' . $rawBody, $secret);
        return hash_equals($expected, $h1);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $event = $payload['event_type'] ?? '';
        $data  = $payload['data'] ?? [];
        if ($event === 'transaction.completed') {
            return PaymentResult::paid(
                gatewayPaymentId: (string) ($data['id'] ?? ''),
                payload:          $data,
            );
        }
        return PaymentResult::failed("unhandled_paddle_event: {$event}", $payload);
    }

    // ── Recurring subscriptions ──────────────────────────────────────
    //
    // Verified: Paddle has no "create subscription" call. You create a
    // transaction for a RECURRING price (billing_cycle set) and Paddle creates
    // the subscription itself once the customer pays the hosted checkout. Each
    // cycle fires transaction.completed (data.subscription_id).

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function createSubscription(Order $order, string $callbackUrl): PaymentResult
    {
        $apiKey    = (string) $this->cred('api_key');
        $productId = (string) $this->cred('product_id');
        if ($apiKey === '' || $productId === '') return PaymentResult::failed('paddle_credentials_missing');

        $plan = $this->planInterval($order);                         // interval=day/week/month/year, count
        $body = [
            'items' => [[
                'price' => [
                    'description'   => "Order #{$order->order_number}",
                    'product_id'    => $productId,
                    // Setting billing_cycle is what makes Paddle treat this as a
                    // subscription instead of a one-off.
                    'billing_cycle' => ['interval' => $plan['interval'], 'frequency' => $plan['count']],
                    'unit_price' => [
                        'amount'        => (string) ((int) round((float) $order->amount * 100)),
                        'currency_code' => strtoupper($order->currency ?? 'USD'),
                    ],
                ],
                'quantity' => 1,
            ]],
            'collection_mode' => 'automatic',
            'custom_data' => [
                'order_id'     => (string) $order->id,
                'order_number' => $order->order_number,
                'workspace_id' => (string) $order->workspace_id,
            ],
        ];

        try {
            $r = Http::withToken($apiKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->baseUrl() . '/transactions', $body);
            $json = $r->json() ?: [];
            if (isset($json['data']['checkout']['url'])) {
                return PaymentResult::redirect($json['data']['checkout']['url'], $json['data']['id'] ?? null, $json);
            }
            return PaymentResult::failed('paddle_subscription: ' . ($json['error']['detail'] ?? 'create_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('paddle_subscription_exception: ' . $e->getMessage());
        }
    }

    public function parseSubscriptionWebhook(array $payload): ?array
    {
        $event = (string) ($payload['event_type'] ?? '');
        $data  = $payload['data'] ?? [];
        if (!is_array($data)) return null;
        $orderId = $data['custom_data']['order_id'] ?? null;

        switch ($event) {
            case 'transaction.completed':
                // Only subscription transactions carry a subscription_id.
                $subId = $data['subscription_id'] ?? null;
                if (!$subId) return null;
                return ['type' => 'renewed', 'subscription_id' => $subId, 'payment_id' => $data['id'] ?? null, 'period_end' => null, 'order_id' => $orderId];

            case 'subscription.created':
            case 'subscription.activated':
                $periodEnd = $data['current_billing_period']['ends_at'] ?? ($data['next_billed_at'] ?? null);
                return ['type' => 'created', 'subscription_id' => $data['id'] ?? null, 'payment_id' => null, 'period_end' => $periodEnd, 'order_id' => $orderId];

            case 'subscription.canceled':
                return ['type' => 'canceled', 'subscription_id' => $data['id'] ?? null, 'payment_id' => null, 'period_end' => null, 'order_id' => $orderId];

            case 'transaction.payment_failed':
                $subId = $data['subscription_id'] ?? null;
                if (!$subId) return null;
                return ['type' => 'payment_failed', 'subscription_id' => $subId, 'payment_id' => $data['id'] ?? null, 'period_end' => null, 'order_id' => $orderId];

            default:
                return null;
        }
    }

    public function cancelSubscription(string $gatewaySubscriptionId, array $context = []): PaymentResult
    {
        $apiKey = (string) $this->cred('api_key');
        if ($apiKey === '') return PaymentResult::failed('paddle_api_key_missing');
        try {
            $r = Http::withToken($apiKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->baseUrl() . '/subscriptions/' . $gatewaySubscriptionId . '/cancel', ['effective_from' => 'next_billing_period']);
            if (!$r->successful()) return PaymentResult::failed('paddle_cancel: HTTP ' . $r->status());
            return PaymentResult::paid(gatewayOrderId: $gatewaySubscriptionId, payload: $r->json());
        } catch (\Throwable $e) {
            return PaymentResult::failed('paddle_cancel_exception: ' . $e->getMessage());
        }
    }

    private function baseUrl(): string
    {
        return $this->isLive() ? self::PROD_BASE : self::SANDBOX_BASE;
    }
}

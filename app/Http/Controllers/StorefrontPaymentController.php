<?php

namespace App\Http\Controllers;

use App\Models\WaStorefront;
use App\Services\Storefront\StorefrontPaymentService;
use App\Support\ZanaDarajaCallbackGuard;
use App\Support\ZanaPaystackCallbackGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * S2 — Razorpay webhook for storefront payment links. Razorpay POSTs the
 * raw JSON with an `X-Razorpay-Signature` header (HMAC-SHA256 of the raw
 * body with the merchant's webhook secret). We resolve the storefront from
 * the payment-link notes, verify with THAT merchant's secret, then flip the
 * order to paid. Public + CSRF-exempt; the HMAC is the auth.
 */
class StorefrontPaymentController extends Controller
{
    public function razorpayWebhook(Request $request, StorefrontPaymentService $pay): JsonResponse
    {
        $raw  = $request->getContent();
        $sig  = $request->header('X-Razorpay-Signature');
        $json = json_decode($raw, true);
        if (!is_array($json)) return response()->json(['ok' => false], 400);

        // Resolve which merchant/storefront this event belongs to (from notes).
        $entity = $json['payload']['payment_link']['entity']
            ?? $json['payload']['payment']['entity']
            ?? [];
        $sfId = (int) ($entity['notes']['storefront_id'] ?? 0);
        $sf   = $sfId ? WaStorefront::find($sfId) : null;
        if (!$sf) return response()->json(['ok' => false, 'error' => 'storefront_unknown'], 404);

        if (!$pay->verifyWebhook($sf, $raw, $sig)) {
            Log::warning('[STOREFRONT-PAY] webhook signature mismatch (sf ' . $sfId . ')');
            return response()->json(['ok' => false, 'error' => 'bad_signature'], 400);
        }

        $event = (string) ($json['event'] ?? '');
        if (in_array($event, ['payment_link.paid', 'payment.captured', 'order.paid'], true)) {
            $order = $pay->applyPaidEvent($json);
            return response()->json(['ok' => true, 'order' => $order?->id]);
        }

        // Acknowledge other events so Razorpay doesn't retry.
        return response()->json(['ok' => true, 'ignored' => $event]);
    }

    public function darajaSandboxWebhook(Request $request, string $token): JsonResponse
    {
        $storefront = ZanaDarajaCallbackGuard::resolveStorefrontByToken($token);
        if (!$storefront) {
            Log::warning('[DARAJA-SANDBOX] callback token mismatch');

            return response()->json(['ok' => false, 'error' => 'storefront_unknown'], 404);
        }

        $payload = $request->json()->all();
        if (!is_array($payload) || empty($payload)) {
            $payload = json_decode($request->getContent(), true) ?: [];
        }
        if (!is_array($payload) || empty($payload)) {
            $payload = $request->all();
        }

        $result = ZanaDarajaCallbackGuard::handleCallback($storefront, $payload);
        if (($result['duplicate'] ?? false) === true) {
            return response()->json(['ok' => true, 'duplicate' => true, 'order_id' => $result['order_id'] ?? null]);
        }
        if (!($result['ok'] ?? false)) {
            Log::warning('[DARAJA-SANDBOX] callback handling failed', $result);

            return response()->json($result, ($result['error'] ?? '') === 'unmatched_callback' ? 202 : 422);
        }

        return response()->json(['ok' => true, 'order_id' => $result['order_id'] ?? null]);
    }

    public function paystackMerchantWebhook(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        $signature = $request->header('x-paystack-signature');
        $payload = $request->json()->all();
        if (!is_array($payload) || empty($payload)) {
            $payload = json_decode($raw, true) ?: [];
        }
        if (!is_array($payload) || empty($payload)) {
            $payload = $request->all();
        }
        if (!is_array($payload) || empty($payload)) {
            return response()->json(['ok' => false, 'error' => 'invalid_payload'], 400);
        }

        $reference = ZanaPaystackCallbackGuard::extractReference($payload);
        $order = ZanaPaystackCallbackGuard::resolveOrderByReference($reference);
        if (!$order) {
            Log::warning('[PAYSTACK-MERCHANT] unmatched callback reference', ['reference' => $reference]);

            return response()->json(['ok' => false, 'error' => 'unmatched_callback', 'reference' => $reference], 202);
        }

        $storefront = ZanaPaystackCallbackGuard::resolveStorefrontForOrder($order);
        if (!ZanaPaystackCallbackGuard::verifySignature($storefront, $raw, $signature)) {
            Log::warning('[PAYSTACK-MERCHANT] signature mismatch', ['reference' => $reference, 'order_id' => $order->id]);

            return response()->json(['ok' => false, 'error' => 'bad_signature'], 400);
        }

        $result = ZanaPaystackCallbackGuard::handleWebhook($order, $payload);
        if (($result['duplicate'] ?? false) === true) {
            return response()->json(['ok' => true, 'duplicate' => true, 'order_id' => $result['order_id'] ?? null]);
        }

        return response()->json([
            'ok' => true,
            'order_id' => $result['order_id'] ?? null,
            'auto_confirmed' => (bool) ($result['auto_confirmed'] ?? false),
        ]);
    }
}

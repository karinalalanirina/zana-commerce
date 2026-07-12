<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * PhonePe payment gateway driver (India — UPI / cards).
 *
 * Uses the PhonePe Business API. Payload is base64 + SHA-256 checksum
 * (X-VERIFY header).
 *
 * @see https://developer.phonepe.com/docs/pg-api-reference/
 */
class PhonepeDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://api-preprod.phonepe.com/apis/pg-sandbox';
    private const PROD_BASE    = 'https://api.phonepe.com/apis/hermes';

    public static function credentialFields(): array
    {
        return [
            'merchant_id' => ['label' => 'Merchant ID', 'type' => 'text',     'required' => true],
            'salt_key'    => ['label' => 'Salt Key',    'type' => 'password', 'required' => true],
            'salt_index'  => ['label' => 'Salt Index',  'type' => 'text',     'required' => true],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $merchantId = (string) $this->cred('merchant_id');
        $saltKey    = (string) $this->cred('salt_key');
        $saltIndex  = (string) $this->cred('salt_index');
        if ($merchantId === '' || $saltKey === '' || $saltIndex === '') return PaymentResult::failed('phonepe_credentials_missing');

        $merchantTxId = 'PP_' . $order->order_number . '_' . time();
        $body = [
            'merchantId'            => $merchantId,
            'merchantTransactionId' => $merchantTxId,
            'merchantUserId'        => 'USER_' . ($order->user_id ?? 'GUEST'),
            'amount'                => (int) round((float) $order->amount * 100), // paisa
            'redirectUrl'           => $callbackUrl,
            'redirectMode'          => 'REDIRECT',
            'callbackUrl'           => route('payment.webhook', ['gateway' => 'phonepe']),
            'paymentInstrument'     => ['type' => 'PAY_PAGE'],
        ];

        $base64Payload = base64_encode(json_encode($body));
        $apiEndpoint   = '/pg/v1/pay';
        $checksum      = hash('sha256', $base64Payload . $apiEndpoint . $saltKey) . '###' . $saltIndex;

        try {
            $r = Http::withHeaders([
                'X-VERIFY' => $checksum,
            ])->timeout(self::HTTP_TIMEOUT_SECONDS)
              ->post($this->baseUrl() . $apiEndpoint, ['request' => $base64Payload]);
            $json = $r->json() ?: [];
            if (($json['success'] ?? false) === true) {
                $url = $json['data']['instrumentResponse']['redirectInfo']['url'] ?? null;
                if ($url) return PaymentResult::redirect($url, $merchantTxId, $json);
            }
            return PaymentResult::failed('phonepe: ' . ($json['message'] ?? 'init_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('phonepe_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $responseData = $payload['response'] ?? null;
        if (!$responseData) return new PaymentResult(status: 'pending', payload: $payload);

        $saltKey   = (string) $this->cred('salt_key');
        $saltIndex = (string) $this->cred('salt_index');
        $xVerify   = $payload['x-verify'] ?? $payload['X-VERIFY'] ?? null;
        if ($xVerify) {
            $expected = hash('sha256', $responseData . $saltKey) . '###' . $saltIndex;
            if (!hash_equals($expected, $xVerify)) return PaymentResult::failed('phonepe_checksum_invalid');
        }

        $decoded      = json_decode(base64_decode($responseData), true) ?: [];
        $merchantTxId = $decoded['data']['merchantTransactionId'] ?? null;
        if (!$merchantTxId) return PaymentResult::failed('missing_phonepe_merchant_tx');

        return $this->queryStatus((string) $merchantTxId);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        return $this->handleCallback($payload);
    }

    public function verify(Order $order): PaymentResult
    {
        $txnId = $order->gateway_order_id ?? $order->gateway_payment_id;
        if (!$txnId) return PaymentResult::failed('no_transaction_id');
        return $this->queryStatus((string) $txnId);
    }

    private function queryStatus(string $merchantTxId): PaymentResult
    {
        $merchantId = (string) $this->cred('merchant_id');
        $saltKey    = (string) $this->cred('salt_key');
        $saltIndex  = (string) $this->cred('salt_index');
        $endpoint   = "/pg/v1/status/{$merchantId}/{$merchantTxId}";
        $checksum   = hash('sha256', $endpoint . $saltKey) . '###' . $saltIndex;

        try {
            $r = Http::withHeaders([
                'X-VERIFY'      => $checksum,
                'X-MERCHANT-ID' => $merchantId,
            ])->timeout(self::HTTP_TIMEOUT_SECONDS)
              ->get($this->baseUrl() . $endpoint);
            $json = $r->json() ?: [];
            $code = $json['code'] ?? '';
            $data = $json['data'] ?? [];
            if ($code === 'PAYMENT_SUCCESS') {
                return PaymentResult::paid(
                    gatewayPaymentId: (string) ($data['transactionId'] ?? $merchantTxId),
                    gatewayOrderId:   $merchantTxId,
                    payload:          $json,
                );
            }
            if ($code === 'PAYMENT_PENDING') {
                return new PaymentResult(status: 'pending', gatewayOrderId: $merchantTxId, payload: $json);
            }
            return PaymentResult::failed("phonepe_status: {$code}", $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('phonepe_status_exception: ' . $e->getMessage());
        }
    }

    private function baseUrl(): string
    {
        return $this->isLive() ? self::PROD_BASE : self::SANDBOX_BASE;
    }
}

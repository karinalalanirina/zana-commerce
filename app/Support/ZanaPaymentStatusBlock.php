<?php

namespace App\Support;

use App\Models\WaOrder;

class ZanaPaymentStatusBlock
{
    public static function build(?WaOrder $order): array
    {
        $payment = ZanaManualPayment::paymentMeta($order);
        $timeline = ZanaManualPayment::timeline($order);
        $paystack = ZanaManualPayment::paystackMeta($order);
        $daraja = ZanaManualPayment::darajaMeta($order);
        $status = ZanaManualPayment::paymentStatus($order);
        $method = (string) ($payment['payment_method'] ?? '');
        $latestSend = self::latestSendState($timeline, $payment);

        return [
            'rail' => self::paymentRail($method, $paystack, $daraja),
            'status' => ZanaManualPayment::statusLabel($status),
            'provider' => self::providerStatus($paystack, $daraja),
            'amount_check' => self::amountCheck($paystack, $daraja),
            'send_state' => $latestSend,
            'reference' => trim((string) ($payment['transaction_reference'] ?? '')),
            'next_action' => self::nextAction($status, $paystack, $daraja, $latestSend),
        ];
    }

    private static function paymentRail(string $method, array $paystack, array $daraja): string
    {
        if ($daraja !== []) {
            return 'Daraja STK';
        }

        if ($paystack !== []) {
            return 'Paystack';
        }

        return match ($method) {
            'mpesa_till', 'mpesa_paybill' => 'Manual M-Pesa',
            'bank_transfer' => 'Bank Transfer',
            'payment_link' => 'Payment Link',
            'cash' => 'Cash',
            'other' => 'Other',
            default => 'Manual payment',
        };
    }

    private static function providerStatus(array $paystack, array $daraja): ?string
    {
        if ($daraja !== []) {
            return match ((string) ($daraja['status'] ?? '')) {
                'initiated' => 'STK initiated',
                'callback_success' => 'Callback received',
                'callback_failed' => 'Callback failed',
                'initiation_failed' => 'STK initiation failed',
                default => !empty($daraja['checkout_request_id']) ? 'Awaiting callback' : null,
            };
        }

        if ($paystack !== []) {
            return match ((string) ($paystack['status'] ?? '')) {
                'link_generated' => 'Link generated',
                'confirmed' => 'Callback received',
                'awaiting_verification' => 'Callback received',
                'failed' => 'Callback failed',
                'generation_failed' => 'Link generation failed',
                default => !empty($paystack['callback_received_at']) ? 'Callback received' : (!empty($paystack['reference']) ? 'Link generated' : null),
            };
        }

        return null;
    }

    private static function amountCheck(array $paystack, array $daraja): ?string
    {
        if ($paystack !== []) {
            return match ((string) ($paystack['amount_matches_order'] ?? '')) {
                'yes' => 'Exact amount match',
                'no' => 'Amount mismatch',
                default => !empty($paystack['callback_received_at']) ? 'Amount not yet verified' : null,
            };
        }

        if ($daraja !== [] && !empty($daraja['callback_received_at'])) {
            return !empty($daraja['callback_metadata']['Amount']) ? 'Amount captured' : 'Amount not yet verified';
        }

        return null;
    }

    private static function latestSendState(array $timeline, array $payment): ?string
    {
        foreach ($timeline as $event) {
            $label = trim((string) ($event['message_delivery_label'] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }

        return match ((string) ($payment['last_send_result'] ?? '')) {
            'sent' => 'Sent',
            'template_fallback_sent' => 'Submitted',
            'fallback_copy' => 'Copied instead',
            'template_required_not_configured' => 'Template required',
            default => null,
        };
    }

    private static function nextAction(string $status, array $paystack, array $daraja, ?string $latestSend): string
    {
        if ($status === 'paid_confirmed' || $status === 'refunded') {
            return 'No action needed';
        }

        if ($status === 'payment_failed') {
            return 'Retry send';
        }

        if ($status === 'customer_says_paid') {
            return 'Review payment reference';
        }

        if ($daraja !== []) {
            return match ((string) ($daraja['status'] ?? '')) {
                'initiated' => 'Check callback',
                'callback_success' => 'Confirm manually',
                'callback_failed', 'initiation_failed' => 'Retry send',
                default => 'Wait for customer payment',
            };
        }

        if ($paystack !== []) {
            return match ((string) ($paystack['status'] ?? '')) {
                'link_generated' => 'Wait for customer payment',
                'awaiting_verification' => 'Review payment reference',
                'confirmed' => 'No action needed',
                'failed', 'generation_failed' => 'Retry send',
                default => !empty($paystack['reference']) ? 'Wait for customer payment' : 'Send instructions',
            };
        }

        if ($latestSend === 'Copied instead' || $latestSend === 'Template required') {
            return 'Retry send';
        }

        return in_array($status, ['awaiting_payment', 'payment_link_sent', 'payment_reminder_sent'], true)
            ? 'Wait for customer payment'
            : 'Send instructions';
    }
}

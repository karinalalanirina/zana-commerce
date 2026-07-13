<?php

namespace App\Support;

use App\Models\WaOrder;
use App\Models\WaStorefront;

class ZanaKenyaPaymentShortcut
{
    public static function hasMpesaDetails(?WaStorefront $storefront): bool
    {
        $config = ZanaAfricaPayments::storefrontConfig($storefront);

        return trim((string) ($config['mpesa_till_number'] ?? '')) !== ''
            || trim((string) ($config['mpesa_paybill_number'] ?? '')) !== '';
    }

    public static function instructionText(?WaStorefront $storefront, ?WaOrder $order = null): ?string
    {
        if (!self::hasMpesaDetails($storefront)) {
            return ZanaAfricaPayments::instructionsText($storefront, $order);
        }

        $config = ZanaAfricaPayments::storefrontConfig($storefront);
        $customerName = trim((string) ($order?->customer_name ?: 'Customer'));
        $reference = trim((string) ($config['payment_reference_format'] ?? ('Order #' . ($order?->id ?? ''))));
        $business = trim((string) ($config['mpesa_business_name'] ?? ($storefront?->shop_name ?? 'your business')));
        $till = trim((string) ($config['mpesa_till_number'] ?? ''));
        $paybill = trim((string) ($config['mpesa_paybill_number'] ?? ''));
        $currency = strtoupper((string) ($order?->currency_code ?: 'KES'));
        $amount = trim((string) ($order?->total_display ?: ''));

        $lines = [];
        $lines[] = 'Hello ' . $customerName . ',';
        if ($order) {
            $lines[] = "To complete your order #{$order->id}, please pay {$amount} via M-Pesa.";
        } else {
            $lines[] = 'Please complete your payment via M-Pesa.';
        }
        $lines[] = '';
        $lines[] = 'Business: ' . $business;
        if ($till !== '') {
            $lines[] = 'Till: ' . $till;
        }
        if ($paybill !== '') {
            $lines[] = 'Paybill: ' . $paybill;
        }
        if ($reference !== '') {
            $lines[] = 'Reference: ' . $reference;
        }
        $lines[] = '';
        $lines[] = 'After payment, please reply with your M-Pesa confirmation code so we can confirm your order.';

        if ($currency !== 'KES') {
            $lines[] = 'Payment currency: ' . $currency . '.';
        }

        return trim(implode("\n", array_filter($lines, static fn ($line) => $line !== null)));
    }
}

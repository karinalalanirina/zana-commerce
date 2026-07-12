<?php

namespace App\Support;

use App\Models\WaOrder;
use App\Models\WaStorefront;
use App\Models\WorkspacePaymentConfig;

class ZanaAfricaPayments
{
    public static function hidesIndiaMerchantPayments(): bool
    {
        return (bool) config('zana.hide_india_merchant_payments', true);
    }

    public static function indiaMerchantPaymentsAvailable(?string $iso2): bool
    {
        if (!self::hidesIndiaMerchantPayments()) {
            return true;
        }

        return WorkspacePaymentConfig::isCountrySupported($iso2);
    }

    public static function storefrontConfig(?WaStorefront $storefront): array
    {
        return is_array($storefront?->payment_config_json) ? $storefront->payment_config_json : [];
    }

    public static function externalPaymentLink(?WaStorefront $storefront, ?WaOrder $order = null): ?string
    {
        $config = self::storefrontConfig($storefront);
        $candidates = [
            $order?->payment_link,
            $config['external_payment_link'] ?? null,
            $config['handle'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    public static function acceptedMethodsText(?WaStorefront $storefront): string
    {
        $config = self::storefrontConfig($storefront);
        $value = trim((string) ($config['accepted_payment_methods_text'] ?? ''));

        return $value !== ''
            ? $value
            : 'M-Pesa, bank transfer, manual payment link, or cash on delivery';
    }

    public static function instructionsText(?WaStorefront $storefront, ?WaOrder $order = null): ?string
    {
        $config = self::storefrontConfig($storefront);
        $template = trim((string) ($config['default_payment_instructions_template'] ?? ''));
        $business = trim((string) ($config['mpesa_business_name'] ?? ($storefront?->shop_name ?? 'your business')));
        $till = trim((string) ($config['mpesa_till_number'] ?? ''));
        $paybill = trim((string) ($config['mpesa_paybill_number'] ?? ''));
        $reference = trim((string) ($config['payment_reference_format'] ?? ('Order #' . ($order?->id ?? ''))));
        $bankInstructions = trim((string) ($config['bank_transfer_instructions'] ?? ''));
        $externalLink = self::externalPaymentLink($storefront, $order);

        $replacements = [
            '{customer_name}' => trim((string) ($order?->customer_name ?? 'there')),
            '{order_id}' => (string) ($order?->id ?? ''),
            '{order_total}' => (string) ($order?->total_display ?? ''),
            '{business_name}' => $business,
            '{mpesa_till}' => $till,
            '{mpesa_paybill}' => $paybill,
            '{payment_reference}' => $reference,
            '{external_payment_link}' => $externalLink ?? '',
            '{bank_transfer_instructions}' => $bankInstructions,
            '{accepted_payment_methods}' => self::acceptedMethodsText($storefront),
        ];

        if ($template !== '') {
            return trim(strtr($template, $replacements));
        }

        $lines = [];
        if ($order) {
            $lines[] = "Order #{$order->id} is awaiting payment for {$order->total_display}.";
        }
        $lines[] = 'Accepted payment methods: ' . self::acceptedMethodsText($storefront) . '.';

        if ($till !== '' || $paybill !== '') {
            $lines[] = 'M-Pesa business name: ' . $business . '.';
            if ($till !== '') {
                $lines[] = 'Till number: ' . $till . '.';
            }
            if ($paybill !== '') {
                $lines[] = 'Paybill number: ' . $paybill . '.';
            }
            if ($reference !== '') {
                $lines[] = 'Use reference: ' . $reference . '.';
            }
        }

        if ($bankInstructions !== '') {
            $lines[] = 'Bank transfer instructions: ' . $bankInstructions;
        }

        if ($externalLink !== null) {
            $lines[] = 'Payment link: ' . $externalLink;
        }

        return trim(implode("\n", array_filter($lines, fn ($line) => trim((string) $line) !== '')));
    }
}

<?php

namespace App\Support;

use App\Models\WaOrder;
use App\Models\WaStorefront;

class ZanaOrderPaymentGuidance
{
    public static function build(
        ?WaOrder $order,
        ?WaStorefront $storefront,
        ?int $workspaceId,
        array $paymentStatusBlock,
        array $templateReadiness,
        array $paystackReadiness,
        array $darajaReadiness
    ): array {
        return [
            'payment_rail' => self::paymentRail($paymentStatusBlock),
            'panels' => [
                self::templatePanel($templateReadiness),
                self::paystackPanel($storefront, $paystackReadiness),
                self::darajaPanel($storefront, $darajaReadiness),
            ],
        ];
    }

    private static function paymentRail(array $paymentStatusBlock): array
    {
        $label = trim((string) ($paymentStatusBlock['rail'] ?? ''));

        return [
            'label' => $label !== '' ? $label : 'Manual payment',
            'detail' => trim((string) ($paymentStatusBlock['provider'] ?? '')),
        ];
    }

    private static function templatePanel(array $readiness): array
    {
        $instruction = is_array($readiness['instruction'] ?? null) ? $readiness['instruction'] : [];
        $reminder = is_array($readiness['reminder'] ?? null) ? $readiness['reminder'] : [];
        $instructionReady = ($instruction['state'] ?? '') === 'ready';
        $reminderReady = ($reminder['state'] ?? '') === 'ready';
        $supportsTemplateFallback = (bool) ($readiness['supports_template_fallback'] ?? false);
        $isOfficial = (bool) ($readiness['is_official'] ?? false);
        $ready = $supportsTemplateFallback && $instructionReady && $reminderReady;

        return [
            'key' => 'template_fallback',
            'title' => 'Template fallback readiness',
            'status_label' => $ready ? 'Ready' : ($supportsTemplateFallback ? 'Needs setup' : 'Manual fallback'),
            'tone' => $ready ? 'success' : ($isOfficial ? 'warning' : 'neutral'),
            'body' => (string) ($readiness['outside_24h_guidance'] ?? 'Copy/manual fallback remains available if template fallback is not configured.'),
            'rows' => [
                ['label' => 'Send engine', 'value' => (string) ($readiness['engine_label'] ?? 'Unknown')],
                ['label' => 'Instructions template', 'value' => (string) ($instruction['label'] ?? 'Unknown')],
                ['label' => 'Reminder template', 'value' => (string) ($reminder['label'] ?? 'Unknown')],
                ['label' => 'Fallback mode', 'value' => $ready ? 'Approved templates available' : 'Copy/manual fallback if template send is unavailable'],
            ],
            'hint' => $ready
                ? 'Payment instruction and reminder templates are available for compliant fallback sends.'
                : 'Configure approved payment templates if this workspace sends outside the 24-hour service window.',
        ];
    }

    private static function paystackPanel(?WaStorefront $storefront, array $readiness): array
    {
        $config = ZanaPaystackMerchantLink::storefrontConfig($storefront);
        $enabled = (bool) ($config['enabled'] ?? false);
        $configured = (bool) ($readiness['configured'] ?? false);
        $missing = [];

        if ($enabled && empty($config['has_secret_key'])) {
            $missing[] = 'secret key';
        }
        if ($enabled && !filter_var((string) ($config['fallback_customer_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            $missing[] = 'fallback customer email';
        }

        return [
            'key' => 'paystack',
            'title' => 'Paystack order links',
            'status_label' => !$enabled ? 'Not enabled' : ($configured ? 'Ready' : 'Config incomplete'),
            'tone' => !$enabled ? 'neutral' : ($configured ? 'success' : 'warning'),
            'body' => (string) ($readiness['notes'] ?? 'Paystack order-link generation is not enabled for this merchant.'),
            'rows' => array_values(array_filter([
                ['label' => 'Link mode', 'value' => $enabled ? 'Enabled' : 'Disabled'],
                ['label' => 'Secret key', 'value' => !empty($config['has_secret_key']) ? 'Saved' : 'Missing'],
                ['label' => 'Fallback email', 'value' => filter_var((string) ($config['fallback_customer_email'] ?? ''), FILTER_VALIDATE_EMAIL) ? (string) $config['fallback_customer_email'] : 'Missing'],
                ['label' => 'Reference prefix', 'value' => (string) ($config['reference_prefix'] ?? 'ZANA')],
            ])),
            'hint' => $configured
                ? 'Order-specific hosted payment links can be generated from this order.'
                : ($enabled && $missing !== []
                    ? 'Missing: ' . implode(', ', $missing) . '.'
                    : 'Enable Paystack only for merchants who want hosted order payment links.'),
        ];
    }

    private static function darajaPanel(?WaStorefront $storefront, array $readiness): array
    {
        $config = ZanaDarajaSandbox::storefrontConfig($storefront);
        $flagEnabled = ZanaDarajaSandbox::enabled();
        $missing = [];

        if ($flagEnabled && empty($config['shortcode'])) {
            $missing[] = 'shortcode';
        }
        if ($flagEnabled && empty($config['has_consumer_key'])) {
            $missing[] = 'consumer key';
        }
        if ($flagEnabled && empty($config['has_consumer_secret'])) {
            $missing[] = 'consumer secret';
        }
        if ($flagEnabled && empty($config['has_passkey'])) {
            $missing[] = 'passkey';
        }

        $ready = (bool) ($readiness['can_initiate'] ?? false);

        return [
            'key' => 'daraja',
            'title' => 'Daraja STK sandbox',
            'status_label' => !$flagEnabled ? 'Hidden by feature flag' : ($ready ? 'STK ready' : 'Config incomplete'),
            'tone' => !$flagEnabled ? 'neutral' : ($ready ? 'success' : 'warning'),
            'body' => (string) ($readiness['notes'] ?? 'Daraja sandbox is disabled by the current Zana feature flags.'),
            'rows' => array_values(array_filter([
                ['label' => 'Global flag', 'value' => $flagEnabled ? 'Enabled' : 'Off'],
                ['label' => 'Merchant sandbox', 'value' => !empty($config['enabled']) ? 'Enabled' : 'Disabled'],
                ['label' => 'Shortcode', 'value' => $config['shortcode'] !== '' ? $config['shortcode'] : 'Missing'],
                ['label' => 'Credentials', 'value' => self::credentialSummary($config)],
                ['label' => 'Callback testing', 'value' => !empty($config['callback_enabled']) ? 'Enabled' : 'Disabled'],
                !empty($readiness['callback_url']) ? ['label' => 'Callback URL', 'value' => (string) $readiness['callback_url']] : null,
            ])),
            'hint' => !$flagEnabled
                ? 'Enable the Daraja sandbox flag before configuring STK testing.'
                : ($ready ? 'Sandbox STK can be initiated for orders with valid Kenya mobile numbers.' : ($missing !== [] ? 'Missing: ' . implode(', ', $missing) . '.' : 'Enable Daraja sandbox for this storefront before testing STK.')),
        ];
    }

    private static function credentialSummary(array $config): string
    {
        $saved = array_filter([
            !empty($config['has_consumer_key']) ? 'key' : null,
            !empty($config['has_consumer_secret']) ? 'secret' : null,
            !empty($config['has_passkey']) ? 'passkey' : null,
        ]);

        return $saved === [] ? 'Missing' : 'Saved: ' . implode(', ', $saved);
    }
}

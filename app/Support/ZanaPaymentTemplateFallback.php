<?php

namespace App\Support;

use App\Models\WaOrder;
use App\Models\WaStorefront;
use App\Models\WaTemplate;
use App\Services\Waba\TemplateSender;

class ZanaPaymentTemplateFallback
{
    public const CONFIG_KEYS = [
        'send_instructions' => 'payment_instruction_template_id',
        'send_reminder' => 'payment_reminder_template_id',
        'resend_link' => 'payment_instruction_template_id',
    ];

    public static function selectedTemplateId(?WaStorefront $storefront, string $paymentAction): ?int
    {
        $config = ZanaAfricaPayments::storefrontConfig($storefront);
        $key = self::CONFIG_KEYS[$paymentAction] ?? null;
        if (!$key) {
            return null;
        }

        $value = (int) ($config[$key] ?? 0);

        return $value > 0 ? $value : null;
    }

    public static function shouldUseTemplateFallback(array $dispatchResult): bool
    {
        $error = strtolower(trim((string) ($dispatchResult['error'] ?? '')));
        if ($error === '') {
            return false;
        }

        foreach ([
            '131047',
            '24-hour',
            '24 hour',
            're-engagement',
            'customer service window expired',
            'templates are required',
        ] as $needle) {
            if (str_contains($error, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function sendForOrder(WaOrder $order, string $paymentAction, string $fallbackMessage, string $paymentLink = ''): array
    {
        $storefront = $order->storefront()->first();
        $templateId = self::selectedTemplateId($storefront, $paymentAction);

        if (!$templateId) {
            return [
                'ok' => false,
                'reason' => 'not_configured',
                'error' => 'No approved fallback template is configured for this storefront.',
            ];
        }

        $template = WaTemplate::query()
            ->forCurrentWorkspace()
            ->where('id', $templateId)
            ->where('meta_status', 'APPROVED')
            ->where(function ($query) {
                $query->where('channel', 'waba')
                    ->orWhereNotNull('meta_template_id');
            })
            ->first();

        if (!$template) {
            return [
                'ok' => false,
                'reason' => 'template_missing',
                'error' => 'The configured fallback template is unavailable for this workspace.',
            ];
        }

        $result = app(TemplateSender::class)->send(
            $template,
            (string) $order->customer_phone,
            self::buildTemplateVars($order, $storefront, $fallbackMessage, $paymentLink)
        );

        return array_merge($result, [
            'reason' => ($result['ok'] ?? false) ? 'sent' : 'send_failed',
            'template_name' => (string) ($template->template_name ?? ''),
        ]);
    }

    public static function buildTemplateVars(WaOrder $order, ?WaStorefront $storefront, string $fallbackMessage, string $paymentLink = ''): array
    {
        $config = ZanaAfricaPayments::storefrontConfig($storefront);
        $reference = trim((string) ($config['payment_reference_format'] ?? ('Order #' . $order->id)));
        $link = $paymentLink !== '' ? $paymentLink : (ZanaAfricaPayments::externalPaymentLink($storefront, $order) ?? '');

        return [
            'body' => array_values(array_filter([
                trim($fallbackMessage),
                trim((string) ($order->customer_name ?: 'Customer')),
                (string) $order->id,
                (string) $order->total_display,
                trim((string) ($config['mpesa_business_name'] ?? ($storefront?->shop_name ?? 'Zana merchant'))),
                trim((string) ($config['mpesa_till_number'] ?? '')),
                trim((string) ($config['mpesa_paybill_number'] ?? '')),
                $reference,
                $link,
                trim((string) ($config['bank_transfer_instructions'] ?? '')),
                ZanaAfricaPayments::acceptedMethodsText($storefront),
            ], static fn ($value) => trim((string) $value) !== '')),
        ];
    }
}

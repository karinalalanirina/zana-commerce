<?php

namespace App\Support;

use App\Models\WaStorefront;
use App\Models\WaTemplate;
use App\Services\WorkspaceEngine;

class ZanaPaymentTemplateReadiness
{
    public static function forStorefront(?WaStorefront $storefront, ?int $workspaceId): array
    {
        $engine = WorkspaceEngine::for($workspaceId);
        $config = ZanaAfricaPayments::storefrontConfig($storefront);

        return [
            'engine' => $engine,
            'engine_label' => WorkspaceEngine::descriptor($engine)['label'] ?? strtoupper($engine),
            'is_official' => in_array($engine, ['waba', 'twilio'], true),
            'supports_template_fallback' => $engine === 'waba',
            'outside_24h_guidance' => $engine === 'waba'
                ? 'Outside the 24-hour service window, approved Meta templates are the compliant fallback for payment sends.'
                : ($engine === 'twilio'
                    ? 'This workspace uses Twilio. Zana currently validates Meta template fallback only for Cloud API workspaces, so keep manual copy fallback ready here.'
                    : 'This workspace is not on an official Cloud API send path, so payment templates are optional and copy fallback remains the safety net.'),
            'instruction' => self::templateState($workspaceId, $config['payment_instruction_template_id'] ?? null),
            'reminder' => self::templateState($workspaceId, $config['payment_reminder_template_id'] ?? null),
        ];
    }

    public static function templateState(?int $workspaceId, mixed $templateId): array
    {
        $templateId = (int) $templateId;
        if ($templateId <= 0) {
            return [
                'configured' => false,
                'state' => 'missing',
                'label' => 'Not configured',
                'notes' => 'If the 24-hour window closes, operators will need to copy the payment message manually.',
                'template' => null,
            ];
        }

        $template = WaTemplate::query()
            ->when($workspaceId, fn ($query) => $query->where('workspace_id', $workspaceId))
            ->where('id', $templateId)
            ->first(['id', 'template_name', 'meta_status', 'language', 'meta_category', 'channel', 'meta_template_id']);

        if (!$template) {
            return [
                'configured' => true,
                'state' => 'missing_record',
                'label' => 'Configured template missing',
                'notes' => 'The stored template ID no longer resolves in this workspace.',
                'template' => null,
            ];
        }

        $approved = strtoupper((string) ($template->meta_status ?? '')) === 'APPROVED';
        $wabaReady = (string) ($template->channel ?? '') === 'waba' || !empty($template->meta_template_id);

        if (!$approved || !$wabaReady) {
            return [
                'configured' => true,
                'state' => 'unavailable',
                'label' => 'Configured but unavailable',
                'notes' => 'The selected template is not currently approved and ready for compliant Cloud API sending.',
                'template' => $template,
            ];
        }

        return [
            'configured' => true,
            'state' => 'ready',
            'label' => 'Configured and approved',
            'notes' => 'This template is available for compliant payment fallback sends.',
            'template' => $template,
        ];
    }
}

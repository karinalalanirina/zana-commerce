<?php

namespace App\Services;

use App\Models\InboxMessage;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for "what plan is this workspace on, how much of
 * it have they used this month, and which features are unlocked". Used by
 * the dashboard plan card and the /account profile usage panel so both
 * always agree (no hardcoded plan names or counts anywhere).
 *
 * Everything is resolved live: plan name via Workspace::billingPackage(),
 * limits via effectiveLimit() (respects admin plan_overrides), usage by
 * counting real rows for the current calendar month, scoped to the
 * workspace and its active engine.
 */
class PlanUsage
{
    /**
     * Human-readable labels for the plan feature flags we surface. Keys are
     * the exact Package columns; only these are shown (curated, not every
     * raw column) so the UI stays readable.
     */
    public const FEATURE_LABELS = [
        'broadcast'                 => 'Broadcasts',
        'campaign'                  => 'Campaigns',
        'autoflow'                  => 'Flow automations',
        'schedulemessage'           => 'Scheduled messages',
        'autoreply'                 => 'Auto-replies',
        'access_keyword_replies'    => 'Keyword replies',
        'template'                  => 'Message templates',
        'access_carousel_templates' => 'Carousel templates',
        'access_drip_campaigns'     => 'Drip campaigns',
        'access_ctwa'               => 'Click-to-WhatsApp ads',
        'access_analytics'          => 'Advanced analytics',
        'access_kanban_view'        => 'Kanban team inbox',
        'access_routing_rules'      => 'Auto-assign routing',
        'access_business_hours'     => 'Business hours / SLA',
        'access_sla_policies'       => 'SLA policies',
        'access_appointment_booking'=> 'Appointment booking',
        'access_ai_agents'          => 'AI agents',
        'access_ai_chat_assistant'  => 'AI chat assistant',
        'access_ai_voice_agent'     => 'AI voice agent',
        'access_ai_training'        => 'AI training',
        'access_waba_calling'       => 'WhatsApp calling',
        'access_call_recording'     => 'Call recording',
        'access_wa_storefront'      => 'WhatsApp storefront',
        'access_flows_commerce'     => 'Commerce flows',
        'access_chatbot_widgets'    => 'Website chat widgets',
        'access_outbound_webhooks'  => 'Outbound webhooks',
        'access_translation'        => 'Auto-translation',
        'integration_shopify'       => 'Shopify integration',
        'integration_woocommerce'   => 'WooCommerce integration',
        'integration_hubspot'       => 'HubSpot integration',
        'integration_google_calendar' => 'Google Calendar',
    ];

    /** Numeric limit meters we surface (column => label). */
    public const LIMIT_METERS = [
        'monthly_messages_limit' => 'Messages this month',
        'contacts_limit'         => 'Contacts',
        'device_limit'           => 'Connected numbers',
        'user_seat_limit'        => 'Team seats',
        'flow_limit'             => 'Flows',
    ];

    public static function summary(Workspace $ws): array
    {
        $pkg = $ws->billingPackage();

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd   = Carbon::now()->endOfMonth();
        $usedMessages = self::messagesThisMonth($ws->id, $monthStart, $monthEnd);

        $msgLimit  = (int) ($ws->effectiveLimit('monthly_messages_limit', 0) ?: 0);
        $unlimited = $msgLimit <= 0;
        $remaining = $unlimited ? null : max(0, $msgLimit - $usedMessages);
        $pct       = ($unlimited || $msgLimit === 0) ? 0 : min(100, (int) round($usedMessages / $msgLimit * 100));

        // Feature flags — split into unlocked / locked using effectiveLimit so
        // admin overrides are respected.
        $unlocked = [];
        $locked   = [];
        foreach (self::FEATURE_LABELS as $key => $label) {
            if ((bool) $ws->effectiveLimit($key, false)) {
                $unlocked[$key] = $label;
            } else {
                $locked[$key] = $label;
            }
        }

        // Numeric limit meters (used / limit). null limit = unlimited.
        $meters = [];
        foreach (self::LIMIT_METERS as $key => $label) {
            $limit = $ws->effectiveLimit($key, 0);
            $limit = is_numeric($limit) ? (int) $limit : 0;
            $used  = match ($key) {
                'monthly_messages_limit' => $usedMessages,
                'contacts_limit'         => self::countModel(\App\Models\Contact::class, $ws->id),
                'device_limit'           => self::countModel(\App\Models\Device::class, $ws->id),
                'user_seat_limit'        => \App\Models\User::where('current_workspace_id', $ws->id)->count(),
                'flow_limit'             => self::countModel(\App\Models\Flow::class, $ws->id),
                default                  => 0,
            };
            $meters[$key] = [
                'label'     => $label,
                'used'      => $used,
                'limit'     => $limit,
                'unlimited' => $limit <= 0,
                'pct'       => $limit > 0 ? min(100, (int) round($used / $limit * 100)) : 0,
            ];
        }

        $ownerCredits = (int) (\App\Models\User::where('id', $ws->owner_user_id)->value('wallet_credits') ?? 0);

        return [
            'plan_name'      => $pkg?->pname ?: 'Free',
            'plan_id'        => $pkg?->id,
            'is_free'        => $pkg === null,
            'messages_used'  => $usedMessages,
            'messages_limit' => $msgLimit,
            'messages_unlimited' => $unlimited,
            'messages_remaining' => $remaining,
            'messages_pct'   => $pct,
            'credits'        => $ownerCredits,
            'unlocked'       => $unlocked,
            'locked'         => $locked,
            'unlocked_count' => count($unlocked),
            'feature_total'  => count(self::FEATURE_LABELS),
            'meters'         => $meters,
            'month_label'    => $monthStart->format('F Y'),
            'cycle_reset'    => $monthEnd->copy()->addDay()->startOfDay()->format('M j'),
            'days_left'      => (int) Carbon::now()->startOfDay()->diffInDays($monthEnd->copy()->startOfDay()) + 1,
        ];
    }

    /** Outbound messages (bulk + inbox) for the workspace this calendar month. */
    private static function messagesThisMonth(int $wsId, Carbon $start, Carbon $end): int
    {
        $bulk = Message::query()
            ->where('workspace_id', $wsId)
            ->forCurrentEngine()
            ->where('direction', 'out')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $inbox = InboxMessage::query()
            ->forCurrentEngine()
            ->where('direction', 'out')
            ->whereBetween('created_at', [$start, $end])
            ->whereHas('conversation', fn ($q) => $q->where('workspace_id', $wsId))
            ->count();

        return $bulk + $inbox;
    }

    /** Defensive workspace-scoped count — tolerates models lacking the column. */
    private static function countModel(string $class, int $wsId): int
    {
        try {
            return $class::query()->where('workspace_id', $wsId)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}

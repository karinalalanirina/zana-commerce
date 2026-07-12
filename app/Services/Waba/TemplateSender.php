<?php

namespace App\Services\Waba;

use App\Models\SystemSetting;
use App\Models\WaProviderConfig;
use App\Models\WaTemplate;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Single entry-point for sending an approved WABA template.
 *
 * Caller passes a `WaTemplate` + recipient number + per-recipient
 * variable map. We enforce four ban-prevention rails before the
 * request ever leaves our server:
 *
 *   1. Meta status MUST be APPROVED (refuse PENDING/REJECTED/PAUSED/DISABLED).
 *   2. Quality floor — refuse to send when `quality_score = RED`
 *      unless the admin override flag is set. Sending into a RED
 *      quality template is the fast track to losing the WABA.
 *   3. Per-template per-24h send cap. Default = workspace's daily
 *      messaging tier ÷ 4 (Meta's tier 1000 → 250/day/template max).
 *      Configurable via `waba_template_daily_cap` (system_setting).
 *   4. Auto-pause check — if `paused_until` is in the future, refuse.
 *
 * On success, returns the wamid. On failure, returns an error code
 * + hint string so the caller can decide whether to retry, fall
 * back to a different template, or surface to the operator.
 */
class TemplateSender
{
    public const RC_OK              = 'ok';
    public const RC_NOT_APPROVED    = 'not_approved';
    public const RC_QUALITY_FLOOR   = 'quality_floor';
    public const RC_PAUSED          = 'paused';
    public const RC_RATE_LIMITED    = 'rate_limited';
    public const RC_NO_PROVIDER     = 'no_provider';
    public const RC_META_ERROR      = 'meta_error';

    /**
     * @param  array{header?:string,header_media_id?:string,header_media_url?:string,body?:array<int,string>,buttons?:array<int,array>,cards?:array<int,array>}  $vars
     * @return array{ok:bool,code:string,wamid?:?string,error?:?string,template_id:int}
     */
    public function send(WaTemplate $tpl, string $toNumber, array $vars = [], ?WaProviderConfig $cfg = null): array
    {
        // 1. Approval gate ------------------------------------------------
        if ($tpl->meta_status !== 'APPROVED') {
            return $this->fail(self::RC_NOT_APPROVED, $tpl,
                "Template is not approved by Meta (current: {$tpl->meta_status}). Wait for approval before sending.");
        }

        // 2. Paused gate --------------------------------------------------
        if ($tpl->paused_until && $tpl->paused_until->isFuture()) {
            return $this->fail(self::RC_PAUSED, $tpl,
                'Template is paused until ' . $tpl->paused_until->toIso8601String() . '. Quality must recover before sending again.');
        }

        // 3. Quality floor — ONLY a confirmed RED rating blocks a send.
        // UNKNOWN (Meta hasn't rated a brand-new approved template yet — true of
        // EVERY freshly-approved template), YELLOW and GREEN are all allowed.
        // Blocking UNKNOWN/YELLOW stopped legitimate templates from ever going
        // out; RED is the only "fast track to losing the WABA" signal, so that
        // is all we refuse.
        $score = strtoupper((string) ($tpl->quality_score ?: 'UNKNOWN'));
        if ($score === 'RED') {
            return $this->fail(self::RC_QUALITY_FLOOR, $tpl,
                'Template quality score is RED — sending would accelerate the quality drop and risk losing the WABA. Refusing.');
        }

        // 4. Per-template rate limit -------------------------------------
        $cap = (int) SystemSetting::get('waba_template_daily_cap', 0);
        if ($cap > 0) {
            $count = (int) Cache::get($this->capKey($tpl), 0);
            if ($count >= $cap) {
                return $this->fail(self::RC_RATE_LIMITED, $tpl,
                    "Daily send cap of $cap reached for this template. Resets at midnight UTC.");
            }
        }

        // 5. Resolve provider --------------------------------------------
        $cfg = $cfg ?? ($tpl->provider_config_id
            ? WaProviderConfig::find($tpl->provider_config_id)
            : WaProviderConfig::primaryForWorkspace($tpl->workspace_id)->first());
        if (!$cfg || $cfg->provider !== 'waba') {
            return $this->fail(self::RC_NO_PROVIDER, $tpl,
                'No WABA provider configured for this workspace.');
        }
        $creds   = $cfg->creds();
        $token   = (string) ($creds['access_token'] ?? '');
        $phoneId = (string) (($cfg->meta_json['phone_number_id'] ?? null) ?: ($creds['phone_number_id'] ?? ''));
        if ($token === '' || $phoneId === '') {
            return $this->fail(self::RC_NO_PROVIDER, $tpl,
                'WABA provider is missing access_token or phone_number_id.');
        }

        // 6. Click-tracking — wrap any URL-button parameter that looks
        //    like a full URL via LinkTracker. Templates whose button URL
        //    pattern already lives on our shortlink domain just stay as
        //    the token. For partial-value buttons (placeholder inside a
        //    URL), tracking happens at TEMPLATE CREATE time via the
        //    builder's button rewrite — see TemplatePayloadBuilder.
        $vars = $this->wrapTrackableButtonValues($tpl, $toNumber, $vars);

        // 6b. LOCATION header — feed the template's stored coordinates into
        //     the vars so TemplatePayloadBuilder::buildSendHeader fires its
        //     location branch (it reads $vars['header_location']).
        if (empty($vars['header_location']) && is_array($tpl->header_location) && !empty($tpl->header_location)) {
            $vars['header_location'] = $tpl->header_location;
        }

        // 7. Build + POST ------------------------------------------------
        $version = (string) SystemSetting::get('waba_graph_api_version', 'v23.0');
        $base    = 'https://graph.facebook.com/' . ltrim($version, '/');

        $template = (new TemplatePayloadBuilder())->buildSend($tpl, $vars);
        $payload  = [
            'messaging_product' => 'whatsapp',
            'to'                => preg_replace('/\D+/', '', $toNumber),
            'type'              => 'template',
            'template'          => $template,
        ];

        // Full visibility into what we send Meta (the exact Graph payload).
        Log::info('[WABA-template-send] POST', [
            'tpl'              => $tpl->id,
            'template'         => $tpl->template_name,
            'meta_template_id' => $tpl->meta_template_id,
            'phone_id'         => $phoneId,
            'to'               => $toNumber,
            'payload'          => $payload,
        ]);

        try {
            $resp = Http::withToken($token)->acceptJson()->timeout(20)
                ->post("{$base}/{$phoneId}/messages", $payload);
        } catch (\Throwable $e) {
            return $this->fail(self::RC_META_ERROR, $tpl, 'HTTP exception: ' . $e->getMessage());
        }

        if (!$resp->successful()) {
            $err     = (array) ($resp->json('error') ?? []);
            $errCode = (int) ($err['code'] ?? 0);
            // Surface META'S REAL error (error_user_msg / error_data.details +
            // code + trace) rather than our paraphrase, so the operator sees the
            // exact reason and can act / quote it to Meta support.
            $real    = MetaError::describe($err) ?: ('HTTP ' . $resp->status());
            Log::warning('[WABA-template-send] failed', [
                'tpl' => $tpl->id, 'to' => $toNumber, 'code' => $errCode, 'msg' => $real,
                'body' => $resp->body(),   // Meta's full error response
            ]);
            return $this->fail(self::RC_META_ERROR, $tpl, $real);
        }

        $wamid = (string) ($resp->json('messages.0.id') ?? '');

        // Bump the cap counter (24h sliding window).
        if ($cap > 0) {
            $key = $this->capKey($tpl);
            Cache::add($key, 0, now()->addDay());
            Cache::increment($key);
        }

        Log::info('[WABA-template-send] ok', [
            'tpl'              => $tpl->id,
            'meta_template_id' => $tpl->meta_template_id,
            'quality_at_send'  => $score,
            'to'               => $toNumber,
            'wamid'            => $wamid,
        ]);

        // Fire `message_sent` outbound webhook — gives the customer's
        // external systems a real-time "we accepted the send" event
        // BEFORE Meta's status webhook lands. Status update webhooks
        // (delivered/read/failed) fire later from WaWebhookController.
        WebhookService::dispatch('message_sent', [
            'workspace_id' => $tpl->workspace_id,
            'template_id'  => $tpl->id,
            'template_name'=> $tpl->template_name,
            'recipient'    => $toNumber,
            'wamid'        => $wamid,
            'status'       => 'sent',
            'timestamp'    => now()->timestamp,
            'quality_at_send' => $score,
        ], $tpl->user_id);

        return [
            'ok'          => true,
            'code'        => self::RC_OK,
            'wamid'       => $wamid,
            'error'       => null,
            'template_id' => $tpl->id,
        ];
    }

    /**
     * Wrap any URL-shaped button parameter via LinkTracker, attaching
     * per-recipient context so the broadcasts page can answer
     * "which contact clicked".
     *
     * Operates only on button parameters whose `value` is a full
     * `http(s)://` URL (templates where the variable IS the URL).
     * For URL-pattern templates (placeholder inside a fixed URL), the
     * link-tracking rewrite happens at template create time so Meta
     * approves the wadesk shortlink domain.
     */
    private function wrapTrackableButtonValues(WaTemplate $tpl, string $toNumber, array $vars): array
    {
        if (!LinkTracker::enabled()) return $vars;

        $context = [
            'workspace_id' => $tpl->workspace_id,
            'template_id'  => $tpl->id,
            'phone'        => preg_replace('/\D+/', '', $toNumber),
            // broadcast_id / contact_id / message_id are merged in by
            // the caller (e.g. BroadcastsController) via the vars
            // `_tracking` key — kept optional so unit tests stay clean.
        ];
        if (!empty($vars['_tracking']) && is_array($vars['_tracking'])) {
            $context = array_merge($context, $vars['_tracking']);
        }

        foreach (($vars['buttons'] ?? []) as $i => $btn) {
            $sub = strtolower((string) ($btn['sub_type'] ?? ''));
            $val = (string) ($btn['value'] ?? '');
            if ($sub === 'url' && filter_var($val, FILTER_VALIDATE_URL)) {
                $vars['buttons'][$i]['value'] = LinkTracker::wrap($val, $context);
            }
        }

        foreach (($vars['cards'] ?? []) as $cardIdx => $card) {
            foreach (($card['buttons'] ?? []) as $i => $btn) {
                $sub = strtolower((string) ($btn['sub_type'] ?? ''));
                $val = (string) ($btn['value'] ?? '');
                if ($sub === 'url' && filter_var($val, FILTER_VALIDATE_URL)) {
                    $vars['cards'][$cardIdx]['buttons'][$i]['value'] = LinkTracker::wrap($val, $context);
                }
            }
        }

        return $vars;
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function qualityMeetsFloor(string $score, string $floor): bool
    {
        $rank = ['UNKNOWN' => 1, 'RED' => 0, 'YELLOW' => 2, 'GREEN' => 3];
        return ($rank[$score] ?? 1) >= ($rank[$floor] ?? 2);
    }

    private function capKey(WaTemplate $tpl): string
    {
        return 'waba_tpl_cap:' . $tpl->id . ':' . now()->format('Y-m-d');
    }

    private function errorHint(int $code, string $msg): string
    {
        return match ($code) {
            132000          => "Template parameter mismatch — your `vars` shape doesn't match what was submitted. Re-check placeholders.",
            132001          => "Template language not supported on this WABA: $msg",
            132005          => 'Template not found on Meta — it may have been deleted or never approved.',
            132007          => "Translation missing for this language: $msg",
            131026          => 'Recipient is not on WhatsApp.',
            131047          => '24-hour customer service window expired AND recipient has no active conversation. Templates are required to re-open.',
            131056          => 'Pair throttled — too many messages to this number recently. Back off and retry.',
            190             => 'Meta access token expired. Reconnect the WABA at /devices.',
            default         => "Meta error $code: $msg",
        };
    }

    private function fail(string $code, WaTemplate $tpl, string $error): array
    {
        return [
            'ok'          => false,
            'code'        => $code,
            'wamid'       => null,
            'error'       => $error,
            'template_id' => $tpl->id,
        ];
    }
}

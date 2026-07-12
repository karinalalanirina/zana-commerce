<?php

namespace App\Http\Controllers;

use App\Models\WaLinkClick;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Public link-click redirect handler. Hit by /r/{token} when a
 * WhatsApp recipient taps a URL button or tapped link in body text.
 *
 * Flow:
 *   1. Look up `wa_link_clicks` by token. 404 if missing.
 *   2. 410 Gone if expired (helps prove abandonment vs. dead link).
 *   3. Bump `clicks`, `last_click_at`, `last_ip`, `last_user_agent`.
 *   4. Bump `unique_clicks` if this IP hasn't clicked in the last
 *      hour (cheap dedup — full distinct-IP tracking would need a
 *      separate child table).
 *   5. Bump `broadcasts.clicked_count` if this row is tied to a
 *      broadcast (and we haven't already counted this contact).
 *   6. Fire `campaign_contact_clicked` outbound webhook.
 *   7. 302 → original_url.
 *
 * Robots / link-preview bots can artificially inflate clicks. We
 * filter the obvious ones (WhatsApp's link preview UA, "Facebook
 * Crawler", "googlebot") to a no-count visit — they still get
 * redirected so previews show, but don't count.
 */
class LinkRedirectController extends Controller
{
    private const BOT_UA_PATTERNS = [
        'whatsapp', 'facebook', 'meta-externalfetcher', 'twitterbot',
        'linkedinbot', 'slackbot', 'googlebot', 'bingbot', 'discordbot',
        'embedly', 'curl/', 'wget/', 'python-requests',
    ];

    public function go(Request $request, string $token): Response|\Symfony\Component\HttpFoundation\RedirectResponse
    {
        $row = WaLinkClick::where('token', $token)->first();
        if (!$row) {
            return response('Link not found', 404);
        }
        if ($row->expires_at && $row->expires_at->isPast()) {
            return response('Link expired', 410);
        }

        $ua  = (string) $request->userAgent();
        $ip  = (string) $request->ip();
        $isBot = $this->looksLikeBot($ua);

        if (!$isBot) {
            $this->countClick($row, $ip, $ua);
            $this->fireWebhook($row, $ip, $ua);
        }

        return redirect()->away($row->original_url, 302);
    }

    private function countClick(WaLinkClick $row, string $ip, string $ua): void
    {
        $now = now();
        $isUniqueIp = Cache::add("link_click_ip:{$row->id}:{$ip}", 1, $now->copy()->addHour());

        // Use a single SQL UPDATE so concurrent clicks don't lose increments.
        DB::table('wa_link_clicks')->where('id', $row->id)->update([
            'clicks'          => DB::raw('clicks + 1'),
            'unique_clicks'   => $isUniqueIp ? DB::raw('unique_clicks + 1') : DB::raw('unique_clicks'),
            'first_click_at'  => $row->first_click_at ?: $now,
            'last_click_at'   => $now,
            'last_ip'         => mb_substr($ip, 0, 45),
            'last_user_agent' => mb_substr($ua, 0, 191),
            'updated_at'      => $now,
        ]);

        // Per-broadcast clicked_count: only bump on the first click
        // from any given contact, so re-clicks don't inflate the rate.
        if ($row->broadcast_id && $row->contact_id && $isUniqueIp) {
            $alreadyClicked = WaLinkClick::query()
                ->where('broadcast_id', $row->broadcast_id)
                ->where('contact_id',   $row->contact_id)
                ->where('id', '<', $row->id)
                ->where('clicks', '>', 0)
                ->exists();
            if (!$alreadyClicked) {
                DB::table('broadcasts')->where('id', $row->broadcast_id)
                    ->update(['clicked_count' => DB::raw('clicked_count + 1'), 'updated_at' => $now]);
            }
        }
    }

    private function fireWebhook(WaLinkClick $row, string $ip, string $ua): void
    {
        if (!$row->workspace_id) return;

        WebhookService::dispatch('campaign_contact_clicked', [
            'workspace_id' => $row->workspace_id,
            'broadcast_id' => $row->broadcast_id,
            'campaign_id'  => $row->campaign_id,
            'message_id'   => $row->message_id,
            'contact_id'   => $row->contact_id,
            'template_id'  => $row->template_id,
            'phone'        => $row->phone,
            'original_url' => $row->original_url,
            'token'        => $row->token,
            'click_at'     => now()->toIso8601String(),
            'ip'           => $ip,
            'user_agent'   => $ua,
        ]);
    }

    private function looksLikeBot(string $ua): bool
    {
        $u = mb_strtolower($ua);
        foreach (self::BOT_UA_PATTERNS as $p) if (str_contains($u, $p)) return true;
        return false;
    }
}

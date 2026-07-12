<?php

namespace App\Services\Instagram;

use App\Models\InstagramAccount;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper over the official Instagram Graph API (v21). Mirrors how
 * MetaAdsService wraps the Marketing API: Bearer token, versioned base,
 * error translation. All sends go through here so policy/limits live in
 * one place. Exact payloads documented in
 * D:\Vault\kapil\Wasnap - Instagram Automation.md §1b.
 */
class InstagramService
{
    private string $base;

    public function __construct(private InstagramAccount $account)
    {
        $v = (string) SystemSetting::get('instagram_graph_version', 'v23.0');
        // FB-Login path uses graph.facebook.com; IG-Login uses graph.instagram.com.
        $host = $account->login_type === 'instagram' ? 'graph.instagram.com' : 'graph.facebook.com';
        $this->base = "https://{$host}/{$v}";
    }

    private function token(): string
    {
        return (string) $this->account->access_token;
    }

    private function igId(): string
    {
        return (string) $this->account->ig_user_id;
    }

    /** POST to the account's /messages endpoint with a built message object. */
    private function send(array $recipient, array $message): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->post("{$this->base}/{$this->igId()}/messages", [
                    'recipient' => $recipient,
                    'message'   => $message,
                ]);
            if (!$r->successful()) {
                Log::warning('[IG-SEND] failed', ['account' => $this->account->id, 'status' => $r->status(), 'error' => $r->json('error', [])]);
                return ['ok' => false, 'error' => (string) ($r->json('error.message') ?? 'send failed')];
            }
            return ['ok' => true, 'mid' => (string) ($r->json('message_id') ?? '')];
        } catch (\Throwable $e) {
            Log::error('[IG-SEND] threw: ' . $e->getMessage(), ['account' => $this->account->id]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Plain text DM. */
    public function sendDm(string $igsid, string $text): array
    {
        return $this->send(['id' => $igsid], ['text' => mb_substr($text, 0, 1000)]);
    }

    /** DM with tap-to-choose quick replies: [['title'=>,'payload'=>], …]. */
    public function sendQuickReplies(string $igsid, string $text, array $replies): array
    {
        $qr = array_map(fn ($r) => [
            'content_type' => 'text',
            'title'        => mb_substr((string) ($r['title'] ?? ''), 0, 20),
            'payload'      => (string) ($r['payload'] ?? ($r['title'] ?? '')),
        ], array_slice($replies, 0, 13));
        return $this->send(['id' => $igsid], ['text' => $text, 'quick_replies' => $qr]);
    }

    /** DM with a button template: [['type'=>'web_url|postback','title'=>,'url'|'payload'=>], …]. */
    public function sendButtonTemplate(string $igsid, string $text, array $buttons): array
    {
        $btns = array_map(function ($b) {
            $type = ($b['type'] ?? 'postback') === 'web_url' ? 'web_url' : 'postback';
            $out  = ['type' => $type, 'title' => mb_substr((string) ($b['title'] ?? ''), 0, 20)];
            if ($type === 'web_url') $out['url'] = (string) ($b['url'] ?? '');
            else $out['payload'] = (string) ($b['payload'] ?? ($b['title'] ?? ''));
            return $out;
        }, array_slice($buttons, 0, 3));
        return $this->send(['id' => $igsid], ['attachment' => ['type' => 'template', 'payload' => [
            'template_type' => 'button', 'text' => mb_substr($text, 0, 640), 'buttons' => $btns,
        ]]]);
    }

    /** Private reply to a comment (comment → DM). 7-day window. */
    public function privateReply(string $commentId, string $text): array
    {
        return $this->send(['comment_id' => $commentId], ['text' => mb_substr($text, 0, 1000)]);
    }

    /** Public reply under a comment. */
    public function replyComment(string $commentId, string $text): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->post("{$this->base}/{$commentId}/replies", ['message' => mb_substr($text, 0, 2000)]);
            return $r->successful()
                ? ['ok' => true, 'id' => (string) ($r->json('id') ?? '')]
                : ['ok' => false, 'error' => (string) ($r->json('error.message') ?? 'reply failed')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Leave a comment on a media object (used for caption @mentions). */
    public function commentOnMedia(string $mediaId, string $text): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->post("{$this->base}/{$mediaId}/comments", ['message' => mb_substr($text, 0, 2000)]);
            return ['ok' => $r->successful(), 'id' => (string) ($r->json('id') ?? '')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Hide / unhide a comment. */
    public function hideComment(string $commentId, bool $hide = true): array
    {
        try {
            $r = Http::withToken($this->token())->asForm()->timeout(15)
                ->post("{$this->base}/{$commentId}", ['hide' => $hide ? 'true' : 'false']);
            return ['ok' => $r->successful()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Account profile (username, followers, name). */
    public function getProfile(): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$this->igId()}", ['fields' => 'username,name,profile_picture_url,followers_count']);
            return $r->successful() ? (array) $r->json() : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Publish a feed photo — official 2-step container flow:
     *   1) POST /{ig-id}/media       (image_url + caption) → creation_id
     *   2) POST /{ig-id}/media_publish (creation_id)        → published media id
     * Image containers are ready immediately (no status polling). The image
     * URL must be a public HTTPS JPEG.
     */
    public function publishImage(string $imageUrl, string $caption = ''): array
    {
        try {
            $c = Http::withToken($this->token())->acceptJson()->timeout(30)
                ->post("{$this->base}/{$this->igId()}/media", array_filter([
                    'image_url' => $imageUrl,
                    'caption'   => $caption !== '' ? mb_substr($caption, 0, 2200) : null,
                ]));
            $creationId = (string) ($c->json('id') ?? '');
            if (!$c->successful() || $creationId === '') {
                return ['ok' => false, 'error' => (string) ($c->json('error.message') ?? 'container create failed')];
            }
            $p = Http::withToken($this->token())->acceptJson()->timeout(30)
                ->post("{$this->base}/{$this->igId()}/media_publish", ['creation_id' => $creationId]);
            $mediaId = (string) ($p->json('id') ?? '');
            if (!$p->successful() || $mediaId === '') {
                return ['ok' => false, 'error' => (string) ($p->json('error.message') ?? 'publish failed'), 'creation_id' => $creationId];
            }
            return ['ok' => true, 'media_id' => $mediaId];
        } catch (\Throwable $e) {
            Log::error('[IG-PUBLISH] threw: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Account-level insights. Web-verified (2025): account insights now REQUIRE
     * `metric_type`. `reach` is the only safe time-series metric — `impressions`
     * is deprecated (v22+/all versions since 2025-04-21) and `profile_views` was
     * deprecated at account level effective 2025-01-08. So we pull `reach` as a
     * day series + a `total_value` pass for engagement counters.
     * Returns ['reach' => [['t'=>iso,'v'=>int], …], '_totals' => ['likes'=>int, …]].
     * (First arg kept for backward-compat with old callers; it is ignored.)
     */
    public function accountInsights(array $metrics = [], int $days = 14): array
    {
        $out = [];
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$this->igId()}/insights", [
                    'metric'      => 'reach',
                    'metric_type' => 'time_series',
                    'period'      => 'day',
                    'since'       => now()->subDays($days)->timestamp,
                    'until'       => now()->timestamp,
                ]);
            foreach ((array) $r->json('data', []) as $m) {
                $name = (string) ($m['name'] ?? '');
                if ($name === '') continue;
                $out[$name] = array_map(fn ($v) => [
                    't' => (string) ($v['end_time'] ?? ''),
                    'v' => (int) ($v['value'] ?? 0),
                ], (array) ($m['values'] ?? []));
            }
        } catch (\Throwable $e) {
            Log::warning('[IG-INSIGHTS] reach threw: ' . $e->getMessage());
        }
        // Engagement counters (total_value) over the same window.
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$this->igId()}/insights", [
                    'metric'      => 'accounts_engaged,total_interactions,likes,comments,shares,saves,views',
                    'metric_type' => 'total_value',
                    'period'      => 'day',
                    'since'       => now()->subDays($days)->timestamp,
                    'until'       => now()->timestamp,
                ]);
            $totals = [];
            foreach ((array) $r->json('data', []) as $m) {
                $name = (string) ($m['name'] ?? '');
                if ($name === '') continue;
                $totals[$name] = (int) ($m['total_value']['value'] ?? 0);
            }
            if ($totals) $out['_totals'] = $totals;
        } catch (\Throwable $e) {
            Log::warning('[IG-INSIGHTS] totals threw: ' . $e->getMessage());
        }
        return $out;
    }

    // ───────────── Send API: media + generic template + actions ─────────────

    /** DM a media attachment (image|video|audio|file). ≤25MB; public HTTPS url. */
    public function sendMediaDm(string $igsid, string $type, string $url, bool $reusable = true): array
    {
        $type = in_array($type, ['image', 'video', 'audio', 'file'], true) ? $type : 'image';
        $payload = ['url' => $url];
        // `is_reusable` is documented for the Messenger / FB-Login attachment
        // payload, NOT the pure IG-Login messaging payload (which is {url} only),
        // where it can be rejected as an unknown field. Only send it on FB-Login.
        if ($reusable && $this->account->login_type !== 'instagram') $payload['is_reusable'] = true;
        return $this->send(['id' => $igsid], ['attachment' => ['type' => $type, 'payload' => $payload]]);
    }

    /**
     * DM a generic (media/carousel) template. Up to 10 horizontally-scrollable
     * elements, 3 buttons each (web_url|postback). title/subtitle ≤80 chars.
     * elements: [['title','image_url','subtitle','url','buttons'=>[...]], …]
     */
    public function sendGenericTemplate(string $igsid, array $elements): array
    {
        $els = array_map(function ($e) {
            $out = ['title' => mb_substr((string) ($e['title'] ?? ''), 0, 80)];
            if (!empty($e['image_url'])) $out['image_url'] = (string) $e['image_url'];
            if (!empty($e['subtitle']))  $out['subtitle']  = mb_substr((string) $e['subtitle'], 0, 80);
            if (!empty($e['url']))       $out['default_action'] = ['type' => 'web_url', 'url' => (string) $e['url']];
            $btns = array_map(function ($b) {
                $type = ($b['type'] ?? 'postback') === 'web_url' ? 'web_url' : 'postback';
                $o = ['type' => $type, 'title' => mb_substr((string) ($b['title'] ?? ''), 0, 20)];
                if ($type === 'web_url') $o['url'] = (string) ($b['url'] ?? '');
                else $o['payload'] = (string) ($b['payload'] ?? ($b['title'] ?? ''));
                return $o;
            }, array_slice($e['buttons'] ?? [], 0, 3));
            if ($btns) $out['buttons'] = array_values($btns);
            return $out;
        }, array_slice($elements, 0, 10));
        return $this->send(['id' => $igsid], ['attachment' => ['type' => 'template', 'payload' => [
            'template_type' => 'generic', 'elements' => array_values($els),
        ]]]);
    }

    /**
     * sender_action post — reactions + typing + seen all ride the SAME
     * /messages endpoint but carry `sender_action` (no `message`), so the
     * normal send() helper can't express them.
     */
    private function senderAction(string $igsid, string $action, ?array $payload = null): array
    {
        $body = ['recipient' => ['id' => $igsid], 'sender_action' => $action];
        if ($payload !== null) $body['payload'] = $payload;
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->post("{$this->base}/{$this->igId()}/messages", $body);
            return $r->successful()
                ? ['ok' => true]
                : ['ok' => false, 'error' => (string) ($r->json('error.message') ?? 'action failed')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** React to a message — reaction is a keyword ('love') or an emoji. */
    public function sendReaction(string $igsid, string $messageId, string $reaction = 'love'): array
    {
        return $this->senderAction($igsid, 'react', ['message_id' => $messageId, 'reaction' => $reaction]);
    }

    /** Remove our reaction from a message. */
    public function removeReaction(string $igsid, string $messageId): array
    {
        return $this->senderAction($igsid, 'unreact', ['message_id' => $messageId]);
    }

    public function typingOn(string $igsid): array  { return $this->senderAction($igsid, 'typing_on'); }
    public function typingOff(string $igsid): array { return $this->senderAction($igsid, 'typing_off'); }
    public function markSeen(string $igsid): array  { return $this->senderAction($igsid, 'mark_seen'); }

    /**
     * Human-agent tagged DM — the ONLY message tag IG supports. Extends the
     * 24h window to 7 days for genuine human support. Requires the Human Agent
     * feature approved; use ONLY for real operator replies, never bots.
     */
    public function sendHumanAgent(string $igsid, string $text): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->post("{$this->base}/{$this->igId()}/messages", [
                    'recipient'      => ['id' => $igsid],
                    'messaging_type' => 'MESSAGE_TAG',
                    'tag'            => 'HUMAN_AGENT',
                    'message'        => ['text' => mb_substr($text, 0, 1000)],
                ]);
            return $r->successful()
                ? ['ok' => true, 'mid' => (string) ($r->json('message_id') ?? '')]
                : ['ok' => false, 'error' => (string) ($r->json('error.message') ?? 'send failed')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ───────────── Content Publishing: all media types ─────────────

    /** GET a container's status_code (EXPIRED|ERROR|FINISHED|IN_PROGRESS|PUBLISHED). */
    public function containerStatus(string $containerId): string
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$containerId}", ['fields' => 'status_code']);
            return (string) ($r->json('status_code') ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** Poll a container until FINISHED (Meta: once/min, ≤5 min). Returns true when ready. */
    private function pollContainer(string $containerId, int $maxSeconds = 90): bool
    {
        $deadline = time() + max(5, $maxSeconds);
        while (true) {
            $s = $this->containerStatus($containerId);
            if ($s === 'FINISHED') return true;
            // PUBLISHED here means it's already live — don't let the caller
            // re-publish (the API rejects a double publish). ERROR/EXPIRED fail.
            if ($s === 'PUBLISHED' || $s === 'ERROR' || $s === 'EXPIRED') return false;
            if (time() >= $deadline) return false;
            sleep(3);
        }
    }

    /** POST /media → creation_id. */
    private function createContainer(array $params): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(60)
                ->post("{$this->base}/{$this->igId()}/media", $params);
            $id = (string) ($r->json('id') ?? '');
            return ($r->successful() && $id !== '')
                ? ['ok' => true, 'id' => $id]
                : ['ok' => false, 'error' => (string) ($r->json('error.message') ?? 'container create failed')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** POST /media_publish → published media id. */
    private function publishContainer(string $creationId): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(60)
                ->post("{$this->base}/{$this->igId()}/media_publish", ['creation_id' => $creationId]);
            $id = (string) ($r->json('id') ?? '');
            return ($r->successful() && $id !== '')
                ? ['ok' => true, 'media_id' => $id]
                : ['ok' => false, 'error' => (string) ($r->json('error.message') ?? 'publish failed'), 'creation_id' => $creationId];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Publish a Reel / single video. video_url = public HTTPS MP4/MOV (H264/HEVC,
     * AAC, 3s–15min, 9:16, ≤300MB). opts: cover_url, thumb_offset(ms),
     * share_to_feed(bool), audio_name, location_id, max_wait. (No licensed music
     * via API — audio_name only renames original audio.) Polls to FINISHED first.
     */
    public function publishReel(string $videoUrl, string $caption = '', array $opts = []): array
    {
        $params = array_filter([
            'media_type'    => 'REELS',
            'video_url'     => $videoUrl,
            'caption'       => $caption !== '' ? mb_substr($caption, 0, 2200) : null,
            'cover_url'     => $opts['cover_url'] ?? null,
            'thumb_offset'  => $opts['thumb_offset'] ?? null,
            'share_to_feed' => array_key_exists('share_to_feed', $opts) ? ($opts['share_to_feed'] ? 'true' : 'false') : null,
            'audio_name'    => $opts['audio_name'] ?? null,
            'location_id'   => $opts['location_id'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        $c = $this->createContainer($params);
        if (empty($c['ok'])) return $c;
        if (!$this->pollContainer($c['id'], (int) ($opts['max_wait'] ?? 90))) {
            return ['ok' => false, 'error' => 'video still processing / failed (container ' . $c['id'] . ')', 'creation_id' => $c['id']];
        }
        return $this->publishContainer($c['id']);
    }

    /** Publish a Story (image or video). */
    public function publishStory(string $url, bool $isVideo = false): array
    {
        $params = $isVideo
            ? ['media_type' => 'STORIES', 'video_url' => $url]
            : ['media_type' => 'STORIES', 'image_url' => $url];
        $c = $this->createContainer($params);
        if (empty($c['ok'])) return $c;
        if ($isVideo && !$this->pollContainer($c['id'], 90)) {
            return ['ok' => false, 'error' => 'story video still processing', 'creation_id' => $c['id']];
        }
        return $this->publishContainer($c['id']);
    }

    /**
     * Publish a carousel (2-10 items). items: [['type'=>'image'|'video','url'=>], …].
     * Caption lives on the parent only. Counts as ONE post.
     */
    public function publishCarousel(array $items, string $caption = ''): array
    {
        $items = array_slice(array_values($items), 0, 10);
        if (count($items) < 2) return ['ok' => false, 'error' => 'carousel needs 2-10 items'];
        $childIds = [];
        foreach ($items as $it) {
            $isVid = ($it['type'] ?? 'image') === 'video';
            $p = $isVid
                ? ['media_type' => 'VIDEO', 'video_url' => (string) ($it['url'] ?? ''), 'is_carousel_item' => 'true']
                : ['image_url' => (string) ($it['url'] ?? ''), 'is_carousel_item' => 'true'];
            $c = $this->createContainer($p);
            if (empty($c['ok'])) return ['ok' => false, 'error' => 'carousel child failed: ' . ($c['error'] ?? '')];
            if ($isVid) $this->pollContainer($c['id'], 90);
            $childIds[] = $c['id'];
        }
        $parent = $this->createContainer(array_filter([
            'media_type' => 'CAROUSEL',
            'children'   => implode(',', $childIds),
            'caption'    => $caption !== '' ? mb_substr($caption, 0, 2200) : null,
        ], fn ($v) => $v !== null && $v !== ''));
        if (empty($parent['ok'])) return $parent;
        $this->pollContainer($parent['id'], 60);
        return $this->publishContainer($parent['id']);
    }

    /** Remaining publishing quota. Reads quota_total from the live response (don't hardcode). */
    public function publishingLimit(): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$this->igId()}/content_publishing_limit", ['fields' => 'config,quota_usage']);
            $row = (array) ($r->json('data.0') ?? []);
            return [
                'used'   => (int) ($row['quota_usage'] ?? 0),
                'total'  => (int) ($row['config']['quota_total'] ?? 0),
                'window' => (int) ($row['config']['quota_duration'] ?? 86400),
            ];
        } catch (\Throwable $e) {
            return ['used' => 0, 'total' => 0, 'window' => 86400];
        }
    }

    // ───────────── Comments + insights + discovery ─────────────

    /** Permanently delete a comment (media owner only). */
    public function deleteComment(string $commentId): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->delete("{$this->base}/{$commentId}");
            return ['ok' => $r->successful(), 'error' => $r->successful() ? null : (string) ($r->json('error.message') ?? 'delete failed')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Per-media insights. Metrics differ by media type (web-verified 2025):
     * media-level uses `views` (not plays/video_views) and `saved` (not saves).
     * Albums (CAROUSEL_ALBUM) have NO insights — returns [] without calling.
     */
    public function mediaInsights(string $mediaId, string $mediaType = ''): array
    {
        $t = strtoupper(trim($mediaType));
        if ($t === 'CAROUSEL_ALBUM') return [];
        $metrics = match (true) {
            str_contains($t, 'REEL') => 'reach,likes,comments,saved,shares,views,total_interactions,ig_reels_avg_watch_time,ig_reels_video_view_total_time',
            $t === 'STORY'           => 'reach,views,replies,shares,total_interactions',
            default                  => 'reach,likes,comments,saved,shares,views,total_interactions',
        };
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$mediaId}/insights", ['metric' => $metrics]);
            if (!$r->successful()) return [];
            $out = [];
            foreach ((array) $r->json('data', []) as $m) {
                $name = (string) ($m['name'] ?? '');
                if ($name === '') continue;
                $out[$name] = (int) ($m['values'][0]['value'] ?? $m['total_value']['value'] ?? 0);
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Competitor lookup by username (public Business/Creator only; FB-Login + Public Content Access). */
    public function businessDiscovery(string $username): array
    {
        if ($this->account->login_type === 'instagram') return []; // Business Discovery is FB-Login only (graph.facebook.com).
        $username = preg_replace('/[^A-Za-z0-9._]/', '', $username);
        if ($username === '') return [];
        $fields = "business_discovery.username({$username}){username,name,biography,followers_count,follows_count,media_count,profile_picture_url,website,media.limit(12){id,caption,media_type,media_url,permalink,timestamp,like_count,comments_count}}";
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$this->igId()}", ['fields' => $fields]);
            return $r->successful() ? (array) ($r->json('business_discovery') ?? []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Hashtag → id (FB-Login only; 30 unique tags / rolling 7 days). */
    public function hashtagId(string $q): string
    {
        if ($this->account->login_type === 'instagram') return ''; // Hashtag search is FB-Login only.
        $q = ltrim(trim($q), '#');
        if ($q === '') return '';
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/ig_hashtag_search", ['user_id' => $this->igId(), 'q' => $q]);
            return (string) ($r->json('data.0.id') ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** Hashtag media: kind = top_media | recent_media (recent = last 24h only). */
    public function hashtagMedia(string $hashtagId, string $kind = 'top_media', int $limit = 25): array
    {
        if ($this->account->login_type === 'instagram') return []; // Hashtag media is FB-Login only.
        $edge = $kind === 'recent_media' ? 'recent_media' : 'top_media';
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$hashtagId}/{$edge}", [
                    'user_id' => $this->igId(),
                    'fields'  => 'id,media_type,caption,permalink,timestamp,like_count,comments_count',
                    'limit'   => $limit,
                ]);
            return $r->successful() ? (array) $r->json('data', []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** List DM conversation threads (also the source of message_id for reactions). */
    public function getConversations(int $limit = 20): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$this->igId()}/conversations", [
                    'platform' => 'instagram',
                    'fields'   => 'id,updated_time,participants',
                    'limit'    => $limit,
                ]);
            return $r->successful() ? (array) $r->json('data', []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Messages inside a conversation thread. */
    public function getMessages(string $conversationId, int $limit = 25): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$conversationId}", [
                    'fields' => "messages.limit({$limit}){id,from,to,message,created_time}",
                ]);
            return (array) ($r->json('messages.data') ?? []);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * List the account's own recent media (for analytics/comment moderation
     * pickers). GET /{ig-user-id}/media.
     */
    public function getMedia(int $limit = 25): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$this->igId()}/media", [
                    'fields' => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,comments_count,like_count',
                    'limit'  => max(1, min(50, $limit)),
                ]);
            return $r->successful() ? (array) $r->json('data', []) : [];
        } catch (\Throwable $e) {
            Log::warning('[IG-MEDIA] ' . $e->getMessage(), ['account' => $this->account->id]);
            return [];
        }
    }

    /** List comments on one of the account's media (for live moderation). */
    public function getComments(string $mediaId, int $limit = 50): array
    {
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->get("{$this->base}/{$mediaId}/comments", [
                    'fields' => 'id,text,username,timestamp,hidden,like_count,replies{id,text,username,timestamp}',
                    'limit'  => max(1, min(100, $limit)),
                ]);
            return $r->successful() ? (array) $r->json('data', []) : [];
        } catch (\Throwable $e) {
            Log::warning('[IG-COMMENTS] ' . $e->getMessage(), ['account' => $this->account->id]);
            return [];
        }
    }

    /**
     * Subscribe the account to the webhook fields we handle so events actually
     * flow after connect. Best-effort: logs and returns ['ok'=>false] on error,
     * never throws (so a failed subscribe never blocks the connect flow).
     */
    public function subscribeWebhooks(): array
    {
        // Per the official IG webhooks reference — 'messaging_reactions' is NOT a
        // valid field (the reaction field is 'message_reactions'); an unknown field
        // makes Meta reject the whole subscribe (error 100) and nothing flows in.
        $fields = 'messages,messaging_postbacks,message_reactions,comments,live_comments,mentions';
        // FB-Login subscribes the Page; IG-Login subscribes the IG user node.
        $node = ($this->account->login_type === 'instagram' || empty($this->account->page_id))
            ? $this->igId()
            : (string) $this->account->page_id;
        try {
            $r = Http::withToken($this->token())->acceptJson()->timeout(15)
                ->asForm()->post("{$this->base}/{$node}/subscribed_apps", ['subscribed_fields' => $fields]);
            if ($r->successful()) return ['ok' => true];
            Log::warning('[IG-SUBSCRIBE] failed', ['account' => $this->account->id, 'error' => $r->json('error.message')]);
            return ['ok' => false, 'error' => (string) ($r->json('error.message') ?? 'subscribe failed')];
        } catch (\Throwable $e) {
            Log::warning('[IG-SUBSCRIBE] ' . $e->getMessage(), ['account' => $this->account->id]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Refresh a long-lived IG token (IG-Login), valid 60 days, refreshable after 24h. */
    public function refreshLongLivedToken(): array
    {
        try {
            $r = Http::acceptJson()->timeout(15)->get('https://graph.instagram.com/refresh_access_token', [
                'grant_type'   => 'ig_refresh_token',
                'access_token' => $this->token(),
            ]);
            if ($r->successful() && $r->json('access_token')) {
                return ['ok' => true, 'access_token' => (string) $r->json('access_token'), 'expires_in' => (int) $r->json('expires_in', 0)];
            }
            return ['ok' => false, 'error' => (string) ($r->json('error.message') ?? 'refresh failed')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Infer the AI provider from a model id so EVERY provider works in the
     * IG AI nodes (not just OpenAI). Mirrors FlowNodeActionsController::aiCall.
     */
    public static function providerForModel(string $model): string
    {
        $m = strtolower(trim($model));
        return str_starts_with($m, 'claude') ? 'anthropic'
            : (str_starts_with($m, 'gemini') ? 'gemini'
            : ((str_starts_with($m, 'mistral') || str_starts_with($m, 'ministral') || str_starts_with($m, 'open-mistral') || str_starts_with($m, 'open-mixtral')) ? 'mistral'
            : 'openai'));
    }

    // ── Static OAuth helpers (no account instance needed) ──────────────

    /** Exchange an OAuth code for an access token (FB-Login path). */
    public static function exchangeCode(string $code, string $redirectUri): array
    {
        $v      = (string) SystemSetting::get('instagram_graph_version', 'v23.0');
        $appId  = (string) SystemSetting::get('instagram_app_id', '');
        $secret = (string) SystemSetting::get('instagram_app_secret', '');
        try {
            $r = Http::acceptJson()->timeout(15)->get("https://graph.facebook.com/{$v}/oauth/access_token", [
                'client_id'     => $appId,
                'client_secret' => $secret,
                'redirect_uri'  => $redirectUri,
                'code'          => $code,
            ]);
            return $r->successful() ? (array) $r->json() : ['error' => $r->json('error.message', 'token exchange failed')];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Upgrade a short-lived FB user token to a 60-day long-lived token
     * (grant_type=fb_exchange_token). Also used to *refresh* a long-lived
     * token before it expires (re-exchanging resets the 60-day window).
     */
    public static function extendFacebookToken(string $token): array
    {
        $v      = (string) SystemSetting::get('instagram_graph_version', 'v23.0');
        $appId  = (string) SystemSetting::get('instagram_app_id', '');
        $secret = (string) SystemSetting::get('instagram_app_secret', '');
        if ($appId === '' || $secret === '' || $token === '') {
            return ['error' => 'missing app credentials or token'];
        }
        try {
            $r = Http::acceptJson()->timeout(15)->get("https://graph.facebook.com/{$v}/oauth/access_token", [
                'grant_type'        => 'fb_exchange_token',
                'client_id'         => $appId,
                'client_secret'     => $secret,
                'fb_exchange_token' => $token,
            ]);
            if ($r->successful() && $r->json('access_token')) {
                return ['ok' => true, 'access_token' => (string) $r->json('access_token'), 'expires_in' => (int) $r->json('expires_in', 5184000)];
            }
            return ['error' => (string) ($r->json('error.message') ?? 'long-lived exchange failed')];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * IG-Login OAuth code exchange (Instagram-Login, not FB). Two steps:
     * POST api.instagram.com/oauth/access_token → short token + user_id, then
     * GET graph.instagram.com/access_token?grant_type=ig_exchange_token → 60-day.
     */
    public static function exchangeCodeInstagram(string $code, string $redirectUri): array
    {
        $appId  = (string) SystemSetting::get('instagram_ig_app_id', SystemSetting::get('instagram_app_id', ''));
        $secret = (string) SystemSetting::get('instagram_ig_app_secret', SystemSetting::get('instagram_app_secret', ''));
        if ($appId === '' || $secret === '') return ['error' => 'Instagram-Login app id/secret not configured'];
        try {
            $r = Http::asForm()->acceptJson()->timeout(15)->post('https://api.instagram.com/oauth/access_token', [
                'client_id'     => $appId,
                'client_secret' => $secret,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $redirectUri,
                'code'          => $code,
            ]);
            if (!$r->successful() || !$r->json('access_token')) {
                return ['error' => (string) ($r->json('error_message') ?? $r->json('error.message') ?? 'IG token exchange failed')];
            }
            $short  = (string) $r->json('access_token');
            $userId = (string) ($r->json('user_id') ?? $r->json('permissions') ?? '');
            // Upgrade to a 60-day long-lived token.
            $l = Http::acceptJson()->timeout(15)->get('https://graph.instagram.com/access_token', [
                'grant_type'    => 'ig_exchange_token',
                'client_secret' => $secret,
                'access_token'  => $short,
            ]);
            $token = ($l->successful() && $l->json('access_token')) ? (string) $l->json('access_token') : $short;
            $exp   = (int) ($l->json('expires_in') ?? 0);
            return ['ok' => true, 'access_token' => $token, 'user_id' => $userId, 'expires_in' => $exp ?: 5184000];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

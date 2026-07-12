<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstagramAccount;
use App\Models\InstagramAutomation;
use App\Models\InstagramBroadcast;
use App\Models\InstagramRepostItem;
use App\Models\InstagramReposterSetting;
use App\Models\InstagramScheduledPost;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Node-bridge for the Instagram scheduler + reels-autoposter (IG-N0b).
 *
 * Node owns ALL Instagram scheduling/posting: it pulls due jobs here, posts
 * them via its own igGraphClient (the official Graph API, in Node), and
 * reports results back. Laravel's job is the queue/DB, handing Node the
 * account's Graph token + versioned base, hosting scraped videos at a public
 * URL (Graph cURLs the URL server-side), and arming the comment→DM rule after
 * a scheduled post publishes. Every endpoint is authed by the shared
 * X-Node-Token (server-to-server, same as the WA node bridge).
 */
class InstagramNodeController extends Controller
{
    private function assertNode(Request $r): void
    {
        $expected = (string) (config('services.node.token') ?: env('NODE_WEBHOOK_TOKEN', ''));
        $given    = (string) $r->header('X-Node-Token', '');
        abort_if($expected === '' || !hash_equals($expected, $given), 403, 'bad node token');
    }

    /** Graph base (host+version) + token + ig id for an account. */
    private function accountAuth(InstagramAccount $a): array
    {
        $v    = (string) SystemSetting::get('instagram_graph_version', 'v21.0');
        $host = $a->login_type === 'instagram' ? 'graph.instagram.com' : 'graph.facebook.com';
        return ['base' => "https://{$host}/{$v}", 'token' => (string) $a->access_token, 'ig' => (string) $a->ig_user_id];
    }

    private function connected(?InstagramAccount $a): bool
    {
        return $a && $a->status === 'connected' && (string) $a->access_token !== '' && (string) $a->ig_user_id !== '';
    }

    /**
     * GET /api/instagram/jobs/due — everything Node needs to fire right now:
     * due scheduled posts, active DM broadcasts, and enabled reposter configs
     * (each with the account's Graph auth so Node can post directly).
     */
    public function due(Request $r): JsonResponse
    {
        $this->assertNode($r);
        $accounts = [];
        $acct = function (int $id) use (&$accounts): ?InstagramAccount {
            return $accounts[$id] ??= InstagramAccount::find($id);
        };

        // --- scheduled posts (image/reel/story/carousel) ---
        $scheduled = [];
        foreach (InstagramScheduledPost::where('status', 'pending')
            ->where('scheduled_at', '<=', now())->whereNull('claimed_at')
            ->orderBy('scheduled_at')->limit(25)->get() as $p) {
            $a = $acct($p->instagram_account_id);
            if (!$this->connected($a)) continue;
            $scheduled[] = [
                'id'         => $p->id,
                'account_id' => $p->instagram_account_id,
                'media_type' => $p->media_type ?: 'image',
                'image_url'  => $p->image_url,
                'video_url'  => $p->video_url,
                'media_urls' => $p->media_urls,
                'caption'    => $p->caption,
                'auth'       => $this->accountAuth($a),
            ];
        }

        // --- DM broadcasts (drain with cursor + 24h window honored Node-side) ---
        $broadcasts = [];
        foreach (InstagramBroadcast::whereIn('status', ['pending', 'running'])
            ->whereNull('claimed_at')->orderBy('id')->limit(10)->get() as $b) {
            $a = $acct($b->instagram_account_id);
            if (!$this->connected($a)) continue;
            $broadcasts[] = [
                'id'         => $b->id,
                'account_id' => $b->instagram_account_id,
                'body'       => $b->body,
                'recipients' => $b->recipients ?: [],
                'cursor'     => (int) $b->cursor,
                'total'      => (int) $b->total,
                'auth'       => $this->accountAuth($a),
            ];
        }

        // --- reposter configs (scrape + post on their own intervals) ---
        $reposter = [];
        foreach (InstagramReposterSetting::where('enabled', true)->get() as $s) {
            $a = $acct($s->instagram_account_id);
            if (!$this->connected($a)) continue;
            $postedToday = InstagramRepostItem::where('instagram_account_id', $s->instagram_account_id)
                ->where('status', 'posted')->where('posted_at', '>=', now()->subDay())->count();
            $next = InstagramRepostItem::where('instagram_account_id', $s->instagram_account_id)
                ->where('status', 'queued')->whereNull('claimed_at')
                ->orderBy('id')->first();
            $reposter[] = [
                'account_id'           => $s->instagram_account_id,
                'workspace_id'         => $s->workspace_id,
                'source_ig_accounts'   => $s->source_ig_accounts ?: [],
                'youtube_enabled'      => (bool) $s->youtube_enabled,
                'youtube_api_key'      => (string) $s->youtube_api_key,
                'source_yt_channels'   => $s->source_yt_channels ?: [],
                'fetch_limit'          => (int) $s->fetch_limit,
                'scraper_interval_min' => (int) $s->scraper_interval_min,
                'posting_interval_min' => (int) $s->posting_interval_min,
                'daily_cap'            => (int) $s->daily_cap,
                'remove_after_min'     => (int) $s->remove_after_min,
                'post_to_story'        => (bool) $s->post_to_story,
                'hashtags'             => (string) $s->hashtags,
                'posted_today'         => $postedToday,
                'auth'                 => $this->accountAuth($a),
                'next_queued'          => $next ? [
                    'id'         => $next->id,
                    'public_url' => $next->public_url,
                    'caption'    => $next->caption,
                ] : null,
            ];
        }

        return response()->json(['ok' => true, 'scheduled' => $scheduled, 'broadcasts' => $broadcasts, 'reposter' => $reposter]);
    }

    /**
     * POST /api/instagram/jobs/claim {kind,id,node_job_id} — atomic claim so
     * two Node workers never fire the same row. Returns won=true/false.
     */
    public function claim(Request $r): JsonResponse
    {
        $this->assertNode($r);
        $kind = (string) $r->input('kind');
        $id   = (int) $r->input('id');
        $job  = (string) $r->input('node_job_id', '');

        $table = match ($kind) {
            'scheduled' => 'instagram_scheduled_posts',
            'broadcast' => 'instagram_broadcasts',
            'repost'    => 'instagram_repost_items',
            default     => null,
        };
        if (!$table) return response()->json(['ok' => false, 'error' => 'bad kind'], 422);

        $won = DB::table($table)->where('id', $id)->whereNull('claimed_at')
            ->update(['claimed_at' => now(), 'node_job_id' => $table === 'instagram_repost_items' ? null : $job]);

        return response()->json(['ok' => true, 'won' => (bool) $won]);
    }

    /**
     * POST /api/instagram/jobs/result — Node reports the outcome of a fire.
     * For a published scheduled post it also arms the comment→DM rule
     * (identical to the old RunInstagramScheduledPosts behaviour).
     */
    public function result(Request $r): JsonResponse
    {
        $this->assertNode($r);
        $kind   = (string) $r->input('kind');
        $id     = (int) $r->input('id');
        $status = (string) $r->input('status'); // posted|published|failed
        $media  = (string) $r->input('media_id', '');
        $error  = (string) $r->input('error', '');

        if ($kind === 'scheduled') {
            $p = InstagramScheduledPost::find($id);
            if (!$p) return response()->json(['ok' => false, 'error' => 'not found'], 404);
            if ($status === 'published' && $media !== '') {
                $p->update(['status' => 'published', 'media_id' => $media, 'last_error' => null]);
                $this->armCommentToDm($p, $media);
            } else {
                $p->update(['status' => 'failed', 'last_error' => mb_substr($error ?: 'publish failed', 0, 500), 'claimed_at' => null]);
            }
            return response()->json(['ok' => true]);
        }

        if ($kind === 'broadcast') {
            $b = InstagramBroadcast::find($id);
            if (!$b) return response()->json(['ok' => false, 'error' => 'not found'], 404);
            $b->update([
                'cursor'     => (int) $r->input('cursor', $b->cursor),
                // sent/failed arrive as per-batch DELTAS → accumulate.
                'sent'       => (int) $b->sent + (int) $r->input('sent', 0),
                'failed'     => (int) $b->failed + (int) $r->input('failed', 0),
                'status'     => (string) $r->input('bstatus', $b->status), // running|done
                'last_error' => $error !== '' ? mb_substr($error, 0, 500) : $b->last_error,
                // release the claim each batch so the next tick can resume it
                'claimed_at' => $r->input('bstatus') === 'done' ? $b->claimed_at : null,
            ]);
            // Per-recipient ledger — stamp each IGSID in this batch sent/failed.
            foreach ((array) $r->input('results', []) as $res) {
                $ig = (string) ($res['igsid'] ?? '');
                if ($ig === '') continue;
                $row = \App\Models\InstagramBroadcastRecipient::where('broadcast_id', $id)
                    ->where('igsid', $ig)->where('status', 'pending')->first();
                if (!$row) continue;
                if (!empty($res['ok'])) {
                    $row->update(['status' => 'sent', 'mid' => (string) ($res['mid'] ?? ''), 'sent_at' => now()]);
                } else {
                    $row->update(['status' => 'failed', 'error' => mb_substr((string) ($res['error'] ?? 'send failed'), 0, 500)]);
                }
            }
            return response()->json(['ok' => true]);
        }

        if ($kind === 'repost') {
            $it = InstagramRepostItem::find($id);
            if (!$it) return response()->json(['ok' => false, 'error' => 'not found'], 404);
            if ($status === 'posted' && $media !== '') {
                $it->update(['status' => 'posted', 'media_id' => $media, 'posted_at' => now(), 'last_error' => null]);
            } else {
                $it->update(['status' => 'failed', 'last_error' => mb_substr($error ?: 'post failed', 0, 500), 'claimed_at' => null]);
            }
            return response()->json(['ok' => true]);
        }

        return response()->json(['ok' => false, 'error' => 'bad kind'], 422);
    }

    /** Recreate the comment→DM automation row for a freshly-published post. */
    private function armCommentToDm(InstagramScheduledPost $post, string $mediaId): void
    {
        try {
            if (empty($post->auto_dm) && !(!empty($post->auto_flow_id) && !empty($post->auto_keyword))) return;
            $isFlow = !empty($post->auto_flow_id);
            InstagramAutomation::create([
                'workspace_id'         => $post->workspace_id,
                'instagram_account_id' => $post->instagram_account_id,
                'type'                 => $isFlow ? 'flow' : 'comment_to_dm',
                'name'                 => 'Scheduled post ' . substr($mediaId, -6) . ' · comment→DM',
                'trigger_keyword'      => $post->auto_keyword,
                'match_mode'           => $post->auto_keyword ? 'contains' : 'any',
                'post_id'              => $mediaId,
                'public_reply'         => $post->auto_public_reply,
                'dm_message'           => $post->auto_dm ?? '',
                'flow_id'              => $isFlow ? (int) $post->auto_flow_id : null,
                'is_active'            => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[IG-NODE] arm comment→DM failed: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/instagram/reposter/enqueue — Node uploads a scraped clip; we
     * host it on the public disk (so Graph can cURL it) and queue a dedup row.
     */
    public function enqueue(Request $r): JsonResponse
    {
        $this->assertNode($r);
        $data = $r->validate([
            'account_id'    => 'required|integer',
            'source'        => 'required|string|max:16',
            'source_id'     => 'required|string|max:191',
            'source_handle' => 'nullable|string|max:191',
            'caption'       => 'nullable|string',
            'video'         => 'required|file|mimetypes:video/mp4,video/quicktime|max:307200', // ≤300MB (reel cap)
        ]);

        $account = InstagramAccount::find((int) $data['account_id']);
        if (!$this->connected($account)) return response()->json(['ok' => false, 'error' => 'account not connected'], 422);

        // Idempotent: skip if this source clip is already queued/posted.
        $exists = InstagramRepostItem::where('instagram_account_id', $account->id)
            ->where('source_id', $data['source_id'])->first();
        if ($exists) return response()->json(['ok' => true, 'duplicate' => true, 'id' => $exists->id]);

        $path = $r->file('video')->store('ig-reposter', media_disk());
        $item = InstagramRepostItem::create([
            'workspace_id'         => $account->workspace_id,
            'instagram_account_id' => $account->id,
            'source'               => $data['source'],
            'source_id'            => $data['source_id'],
            'source_handle'        => $data['source_handle'] ?? null,
            'caption'              => $data['caption'] ?? null,
            'video_path'           => $path,
            'public_url'           => media_url($path),
            'status'               => 'queued',
        ]);

        return response()->json(['ok' => true, 'id' => $item->id, 'public_url' => $item->public_url]);
    }

    /**
     * POST /api/instagram/reposter/cleanup {account_id, older_than_min} —
     * delete the hosted files for posted clips older than the window.
     */
    public function cleanup(Request $r): JsonResponse
    {
        $this->assertNode($r);
        $accountId = (int) $r->input('account_id');
        $minutes   = max(5, (int) $r->input('older_than_min', 120));

        $n = 0;
        InstagramRepostItem::where('instagram_account_id', $accountId)
            ->where('status', 'posted')->whereNotNull('video_path')
            ->where('posted_at', '<=', now()->subMinutes($minutes))
            ->chunkById(100, function ($items) use (&$n) {
                foreach ($items as $it) {
                    try { media_storage()->delete($it->video_path); } catch (\Throwable $e) {}
                    $it->update(['video_path' => null]);
                    $n++;
                }
            });

        return response()->json(['ok' => true, 'cleaned' => $n]);
    }
}

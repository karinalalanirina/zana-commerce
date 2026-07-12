<?php

namespace App\Http\Controllers;

use App\Models\InstagramAccount;
use App\Models\InstagramRepostItem;
use App\Models\InstagramReposterSetting;
use App\Services\PlanLimitGuard;
use Illuminate\Http\Request;

/**
 * Instaflow "Reels Autopilot" — user-facing config for the scrape→queue→post
 * reposter. Node owns the scheduling/scraping (yt-dlp) + posts via the
 * official Graph API; this page just lets the operator set sources, cadence,
 * hashtags + watch the queue. Plan-gated by access_instagram_reposter.
 */
class InstagramReposterController extends Controller
{
    private function wsId(): int
    {
        return (int) (auth()->user()->current_workspace_id ?? 0);
    }

    public function index(Request $r)
    {
        $wsId = $this->wsId();
        $ws   = auth()->user()->current_workspace;
        $hasFeature = PlanLimitGuard::hasFeature($ws, 'access_instagram_reposter');

        $accounts  = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();
        $accountId = (int) $r->query('account', (int) ($accounts->first()->id ?? 0));
        // Only allow an account that belongs to this workspace.
        if ($accountId && !$accounts->contains('id', $accountId)) $accountId = (int) ($accounts->first()->id ?? 0);

        $setting = $accountId
            ? InstagramReposterSetting::firstOrNew(['instagram_account_id' => $accountId])
            : new InstagramReposterSetting();

        $items = $accountId
            ? InstagramRepostItem::where('instagram_account_id', $accountId)->orderByDesc('id')->limit(60)->get()
            : collect();

        $base  = $accountId ? InstagramRepostItem::where('instagram_account_id', $accountId) : null;
        $stats = [
            'queued' => $base ? (clone $base)->where('status', 'queued')->count() : 0,
            'posted' => $base ? (clone $base)->where('status', 'posted')->count() : 0,
            'failed' => $base ? (clone $base)->where('status', 'failed')->count() : 0,
        ];

        return view('instagram.reposter', compact('accounts', 'accountId', 'setting', 'items', 'stats', 'hasFeature'));
    }

    public function save(Request $r)
    {
        $ws = auth()->user()->current_workspace;
        abort_unless(PlanLimitGuard::hasFeature($ws, 'access_instagram_reposter'), 403, 'Reels Autopilot is not in your plan.');
        $wsId = $this->wsId();

        $data = $r->validate([
            'instagram_account_id' => 'required|integer',
            'source_ig_accounts'   => 'nullable|string',
            'source_yt_channels'   => 'nullable|string',
            'youtube_api_key'      => 'nullable|string|max:191',
            'fetch_limit'          => 'nullable|integer|min:1|max:50',
            'scraper_interval_min' => 'nullable|integer|min:10|max:1440',
            'posting_interval_min' => 'nullable|integer|min:1|max:1440',
            'daily_cap'            => 'nullable|integer|min:1|max:50',
            'remove_after_min'     => 'nullable|integer|min:5|max:1440',
            'hashtags'             => 'nullable|string|max:2000',
        ]);

        $accountId = (int) $data['instagram_account_id'];
        abort_unless(
            InstagramAccount::forWorkspace($wsId)->whereKey($accountId)->exists(),
            403, 'That Instagram account is not in this workspace.'
        );

        $split = fn (?string $s) => array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $s))));

        $update = [
            'workspace_id'         => $wsId,
            'enabled'              => $r->boolean('enabled'),
            'source_ig_accounts'   => $split($data['source_ig_accounts'] ?? ''),
            'youtube_enabled'      => $r->boolean('youtube_enabled'),
            'source_yt_channels'   => $split($data['source_yt_channels'] ?? ''),
            'fetch_limit'          => (int) ($data['fetch_limit'] ?? 10),
            'scraper_interval_min' => (int) ($data['scraper_interval_min'] ?? 120),
            'posting_interval_min' => (int) ($data['posting_interval_min'] ?? 30),
            'daily_cap'            => (int) ($data['daily_cap'] ?? 10),
            'remove_after_min'     => (int) ($data['remove_after_min'] ?? 120),
            'post_to_story'        => $r->boolean('post_to_story'),
            'hashtags'             => $data['hashtags'] ?? null,
        ];
        // Only overwrite the encrypted YouTube key when a new value is typed
        // (the field renders blank, so an empty submit must NOT wipe it).
        if (!empty($data['youtube_api_key'])) $update['youtube_api_key'] = $data['youtube_api_key'];

        InstagramReposterSetting::updateOrCreate(['instagram_account_id' => $accountId], $update);

        return back()->with('status', __('Reels Autopilot settings saved.'));
    }

    /** Re-queue a failed clip (only if its hosted file still exists). */
    public function retry(Request $r, int $id)
    {
        $wsId = $this->wsId();
        $item = InstagramRepostItem::where('workspace_id', $wsId)->whereKey($id)->firstOrFail();
        if (!$item->video_path) return back()->with('error', __('That clip was already cleaned up — re-scrape it instead.'));
        $item->update(['status' => 'queued', 'claimed_at' => null, 'last_error' => null]);
        return back()->with('status', __('Clip re-queued.'));
    }

    public function destroy(Request $r, int $id)
    {
        $wsId = $this->wsId();
        $item = InstagramRepostItem::where('workspace_id', $wsId)->whereKey($id)->firstOrFail();
        if ($item->video_path) { try { media_storage()->delete($item->video_path); } catch (\Throwable $e) {} }
        $item->delete();
        return back()->with('status', __('Clip removed from the queue.'));
    }
}

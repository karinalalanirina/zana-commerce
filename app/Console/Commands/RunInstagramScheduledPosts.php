<?php

namespace App\Console\Commands;

use App\Models\InstagramAccount;
use App\Models\InstagramAutomation;
use App\Models\InstagramScheduledPost;
use App\Services\Instagram\InstagramService;
use Illuminate\Console\Command;

/**
 * Publishes Instagram posts whose scheduled time has arrived, then arms the
 * post's optional comment→DM rule (bound to the freshly-created media id).
 * Run from the same no-cron sweep that drains bulk DMs.
 */
class RunInstagramScheduledPosts extends Command
{
    protected $signature = 'instagram:run-scheduled';
    protected $description = 'Publish due scheduled Instagram posts + arm their comment→DM rule';

    public function handle(): int
    {
        // Node now owns Instagram scheduling (igScheduler) — it claims + fires
        // due posts via the official Graph API. Skip the legacy Laravel sweep
        // unless an admin explicitly flips ownership back, else both would
        // publish the same row (double-post).
        if (\App\Models\SystemSetting::get('instagram_scheduler_owner', 'node') === 'node') {
            return self::SUCCESS;
        }

        $due = InstagramScheduledPost::where('status', 'pending')
            ->where('scheduled_at', '<=', now())->orderBy('scheduled_at')->limit(10)->get();

        foreach ($due as $post) {
            $account = InstagramAccount::find($post->instagram_account_id);
            if (!$account || $account->status !== 'connected') {
                $post->update(['status' => 'failed', 'last_error' => 'account not connected']);
                continue;
            }

            // Route by media type (image | reels | story | carousel) through the
            // same verified dispatcher the composer uses.
            $res = \App\Http\Controllers\InstagramController::publishByType(
                new InstagramService($account),
                $post->media_type ?: 'image',
                [
                    'image_url'  => $post->image_url,
                    'video_url'  => $post->video_url,
                    'media_urls' => $post->media_urls,
                    'caption'    => $post->caption,
                ]
            );
            if (empty($res['ok'])) {
                $post->update(['status' => 'failed', 'last_error' => (string) ($res['error'] ?? 'publish failed')]);
                continue;
            }
            $mediaId = (string) $res['media_id'];
            $post->update(['status' => 'published', 'media_id' => $mediaId, 'last_error' => null]);

            // Arm the comment→DM rule on the new post, if configured.
            if (!empty($post->auto_dm) || (!empty($post->auto_flow_id) && !empty($post->auto_keyword))) {
                $isFlow = !empty($post->auto_flow_id);
                InstagramAutomation::create([
                    'workspace_id'         => $post->workspace_id,
                    'instagram_account_id' => $account->id,
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
            }
            $this->info("Published scheduled post #{$post->id} → media {$mediaId}");
        }

        return self::SUCCESS;
    }
}

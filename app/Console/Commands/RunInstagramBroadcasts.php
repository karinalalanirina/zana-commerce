<?php

namespace App\Console\Commands;

use App\Models\InstagramAccount;
use App\Models\InstagramBroadcast;
use App\Models\InstagramMessage;
use App\Services\Instagram\InstagramService;
use Illuminate\Console\Command;

/**
 * Drains pending Instagram bulk-DM jobs a SAFE batch at a time. Run every
 * few minutes from cron — at 15/run × ~12 runs/hr that stays under Meta's
 * 200-DM/hour cap. Only sends to recipients still inside the 24-hour
 * messaging window (re-checked at send time), so it's ban-safe.
 */
class RunInstagramBroadcasts extends Command
{
    protected $signature = 'instagram:run-broadcasts {--batch=15}';
    protected $description = 'Send a safe batch of pending Instagram bulk DMs (24h-window + 200/hr safe)';

    public function handle(): int
    {
        // Node owns Instagram scheduling now (igScheduler drains broadcasts via
        // the Graph API). Skip the legacy Laravel sweep unless ownership is
        // explicitly flipped back, else both drain the same cursor (double-send).
        if (\App\Models\SystemSetting::get('instagram_scheduler_owner', 'node') === 'node') {
            return self::SUCCESS;
        }

        $batch = max(1, min(40, (int) $this->option('batch')));

        $jobs = InstagramBroadcast::whereIn('status', ['pending', 'running'])->orderBy('id')->limit(3)->get();
        foreach ($jobs as $bc) {
            $account = InstagramAccount::find($bc->instagram_account_id);
            if (!$account || $account->status !== 'connected') {
                $bc->update(['status' => 'done', 'last_error' => 'account not connected']);
                continue;
            }

            $recipients = is_array($bc->recipients) ? $bc->recipients : [];
            $slice = array_slice($recipients, (int) $bc->cursor, $batch);
            if (empty($slice)) { $bc->update(['status' => 'done']); continue; }

            $bc->update(['status' => 'running']);
            $svc = new InstagramService($account);
            $cutoff = now()->subHours(24);
            $sent = (int) $bc->sent; $failed = (int) $bc->failed;

            foreach ($slice as $igsid) {
                // Re-check the 24h window at send time (it may have closed).
                $open = InstagramMessage::where('instagram_account_id', $account->id)
                    ->where('igsid', $igsid)->where('direction', 'in')
                    ->where('created_at', '>=', $cutoff)->exists();
                if (!$open) { continue; } // window closed → skip silently (counted via cursor)

                $r = $svc->sendDm($igsid, $bc->body);
                if (!empty($r['ok'])) {
                    $sent++;
                    InstagramMessage::log($account, $igsid, 'out', $bc->body, 'broadcast', $r['mid'] ?? null);
                } else {
                    $failed++;
                }
                usleep(400000); // 0.4s gentle gap within the batch
            }

            $newCursor = (int) $bc->cursor + count($slice);
            $bc->update([
                'cursor' => $newCursor,
                'sent'   => $sent,
                'failed' => $failed,
                'status' => $newCursor >= count($recipients) ? 'done' : 'running',
            ]);
            $this->info("Broadcast #{$bc->id}: {$newCursor}/" . count($recipients) . " (sent {$sent}, failed {$failed})");
        }

        return self::SUCCESS;
    }
}

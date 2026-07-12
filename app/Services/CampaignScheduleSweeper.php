<?php

namespace App\Services;

use App\Http\Controllers\WaCampaignsController;
use App\Models\WpCampaign;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Fires due scheduled / recurring campaigns. WaDesk runs NO Laravel scheduler
 * (project constraint), so this is driven by the Node bridge's 30-second
 * heartbeat (WaConnectController::nodeHeartbeat) — the bridge is always running
 * because it IS the WhatsApp engine, so it's a reliable 24/7 tick.
 *
 * A scheduled campaign sits at status='scheduled' until its send_date/send_time
 * (in its own timezone) passes; we then fire it through the exact same
 * dispatch path as the "Send now" button. Recurring campaigns re-arm
 * themselves afterwards (see WpCampaign::advanceRecurring).
 */
class CampaignScheduleSweeper
{
    /**
     * @return int how many campaigns were fired this pass
     */
    public function sweep(int $max = 25): int
    {
        // One sweep at a time: many heartbeats can land in the same window
        // (multiple devices/workspaces), and we must never double-fire.
        $lock = Cache::lock('campaign-schedule-sweep', 25);
        if (! $lock->get()) {
            return 0;
        }

        $fired = 0;
        try {
            // Coarse DB filter (status + type + date) then exact per-row due
            // check in PHP, because each campaign's due time is in its own
            // timezone and can't be compared in a single SQL clause.
            $upper = Carbon::now('UTC')->addDay()->toDateString();
            $candidates = WpCampaign::query()
                ->where('status', 'scheduled')
                ->whereIn('schedule_type', ['scheduled', 'recurring'])
                ->whereNotNull('send_date')
                ->whereDate('send_date', '<=', $upper)
                ->orderBy('send_date')->orderBy('send_time')
                ->limit($max)
                ->get();

            $controller = app(WaCampaignsController::class);

            // Diagnostic (Log::warning so it survives production log level):
            // proves the heartbeat actually reached the sweeper, and how many
            // scheduled campaigns it's considering this tick. If you NEVER see
            // this line, the Node heartbeat isn't getting through (token/403).
            Log::warning('[CAMPAIGN SWEEP] tick', [
                'candidates' => $candidates->count(),
                'now_utc'    => Carbon::now('UTC')->toDateTimeString(),
            ]);

            foreach ($candidates as $campaign) {
                if (! $campaign->isDue()) {
                    // Show WHY a candidate is held back — almost always its due
                    // time (in its own timezone) simply hasn't passed yet.
                    Log::warning('[CAMPAIGN SWEEP] not due yet', [
                        'id'        => $campaign->id,
                        'send_date' => (string) $campaign->send_date,
                        'send_time' => (string) $campaign->send_time,
                        'tz'        => $campaign->timezone ?: 'UTC',
                        'due_utc'   => optional($campaign->dueAtUtc())->toDateTimeString(),
                        'now_utc'   => Carbon::now('UTC')->toDateTimeString(),
                    ]);
                    continue;
                }
                try {
                    $controller->fireScheduledCampaign($campaign);
                    $fired++;
                    Log::info('[CAMPAIGN SWEEP] fired due campaign', [
                        'id'   => $campaign->id,
                        'type' => $campaign->schedule_type,
                        'ws'   => $campaign->workspace_id,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('[CAMPAIGN SWEEP] fire failed', ['id' => $campaign->id, 'err' => $e->getMessage()]);
                }
            }
        } finally {
            optional($lock)->release();
        }

        return $fired;
    }
}

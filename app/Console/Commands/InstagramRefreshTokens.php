<?php

namespace App\Console\Commands;

use App\Models\InstagramAccount;
use App\Services\Instagram\InstagramService;
use Illuminate\Console\Command;

/**
 * Keep Instagram tokens alive. Long-lived FB/IG tokens last ~60 days; this
 * re-exchanges any token within 7 days of expiry so connected accounts never
 * silently flip to "needs re-auth". Run once/day (wired into the no-cron
 * dashboard sweep, like the broadcast sweeper).
 */
class InstagramRefreshTokens extends Command
{
    protected $signature = 'instagram:refresh-tokens {--workspace= : Limit to one workspace id}';
    protected $description = 'Refresh Instagram long-lived access tokens nearing expiry';

    public function handle(): int
    {
        $q = InstagramAccount::where('status', 'connected')
            ->where(function ($w) {
                $w->whereNull('token_expires_at')
                  ->orWhere('token_expires_at', '<=', now()->addDays(7));
            });
        if ($this->option('workspace')) {
            $q->where('workspace_id', (int) $this->option('workspace'));
        }

        $ok = 0; $fail = 0;
        foreach ($q->get() as $account) {
            try {
                $svc = new InstagramService($account);
                $res = $account->login_type === 'instagram'
                    ? $svc->refreshLongLivedToken()
                    : InstagramService::extendFacebookToken((string) $account->access_token);

                if (!empty($res['ok']) && !empty($res['access_token'])) {
                    $account->access_token     = (string) $res['access_token'];
                    $account->token_expires_at = now()->addSeconds((int) ($res['expires_in'] ?: 5184000));
                    $account->last_error       = null;
                    $account->save();
                    $ok++;
                } else {
                    // Already expired with no working refresh → needs re-auth.
                    if ($account->token_expires_at && $account->token_expires_at->isPast()) {
                        $account->status = 'needs_reauth';
                    }
                    $account->last_error = (string) ($res['error'] ?? 'token refresh failed');
                    $account->save();
                    $fail++;
                }
            } catch (\Throwable $e) {
                $account->last_error = $e->getMessage();
                $account->save();
                $fail++;
            }
        }

        $this->info("Instagram token refresh: {$ok} refreshed, {$fail} failed.");
        return self::SUCCESS;
    }
}

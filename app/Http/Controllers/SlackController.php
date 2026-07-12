<?php

namespace App\Http\Controllers;

use App\Models\SlackIntegration;
use App\Models\SlackIntegrationLog;
use App\Models\SystemSetting;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

/**
 * User-side Slack integration: connect a Slack workspace (paste Bot Token +
 * Signing Secret) so a `/wa send <name>: <message>` slash command sends a
 * WhatsApp message via the workspace's connected device. Mirrors HubspotController.
 */
class SlackController extends Controller
{
    public function index(Request $request): View
    {
        $wsId        = (int) (Auth::user()?->current_workspace_id ?? 0);
        $integration = SlackIntegration::where('workspace_id', $wsId)->first();
        $enabled     = (bool) SystemSetting::get('slack_enabled', true);
        $logs        = $integration
            ? SlackIntegrationLog::where('integration_id', $integration->id)->latest()->limit(20)->get()
            : collect();

        return view('user.slack.dashboard', [
            'integration' => $integration,
            'enabled'     => $enabled,
            'logs'        => $logs,
            'commandUrl'  => url('/webhooks/slack/command'),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        if (!(bool) SystemSetting::get('slack_enabled', true)) {
            return back()->withErrors(['slack' => __('Slack integration is currently disabled by the platform admin.')]);
        }

        $data = $request->validate([
            'bot_token'      => 'required|string|max:255',
            'signing_secret' => 'required|string|max:255',
            'slash_command'  => 'nullable|string|max:32',
        ]);

        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $uId  = (int) Auth::id();
        if (!$wsId) return back()->withErrors(['slack' => __('No active workspace. Pick one and try again.')]);

        // Validate the bot token directly with Slack (auth.test).
        try {
            $res = Http::asForm()->withToken($data['bot_token'])->acceptJson()->timeout(15)
                ->post('https://slack.com/api/auth.test');
            $j = $res->json() ?? [];
            if (!($j['ok'] ?? false)) {
                return back()->withErrors(['bot_token' => __('Slack rejected this bot token: ') . ($j['error'] ?? 'invalid_auth')])->withInput();
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['bot_token' => __('Could not reach Slack: ') . $e->getMessage()])->withInput();
        }

        $teamId = (string) ($j['team_id'] ?? '');
        if ($teamId !== '' && SlackIntegration::where('team_id', $teamId)->where('workspace_id', '!=', $wsId)->exists()) {
            return back()->withErrors(['bot_token' => __('This Slack workspace is already connected to another WaDesk workspace.')])->withInput();
        }

        $cmd = trim((string) ($data['slash_command'] ?? '/wa')) ?: '/wa';
        if ($cmd[0] !== '/') $cmd = '/' . ltrim($cmd, '/');

        $integration = SlackIntegration::updateOrCreate(
            ['workspace_id' => $wsId],
            [
                'user_id'        => $uId,
                'team_id'        => $teamId ?: null,
                'team_name'      => $j['team'] ?? null,
                'bot_user_id'    => $j['user_id'] ?? null,
                'bot_token'      => $data['bot_token'],
                'signing_secret' => $data['signing_secret'],
                'slash_command'  => $cmd,
                'status'         => 'active',
                'connected_at'   => now(),
            ]
        );

        Audit::log('integration.slack.connected', [
            'resource' => $integration,
            'meta'     => ['team' => $j['team'] ?? null, 'team_id' => $teamId],
        ]);

        return redirect()->route('user.slack')->with('status', __('Slack connected to workspace ') . '"' . ($j['team'] ?? '') . '".');
    }

    public function disconnect(int $id): RedirectResponse
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $row  = SlackIntegration::where('id', $id)->where('workspace_id', $wsId)->first();
        if ($row) {
            Audit::log('integration.slack.disconnected', ['resource' => $row]);
            $row->delete();
        }
        return redirect()->route('user.slack')->with('status', __('Slack disconnected.'));
    }
}

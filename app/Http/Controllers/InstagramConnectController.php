<?php

namespace App\Http\Controllers;

use App\Models\InstagramAccount;
use App\Models\SystemSetting;
use App\Services\Instagram\InstagramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Instagram account OAuth connect (Facebook-Login-for-Business path).
 *   GET /instagram/connect   → redirect to Meta's OAuth dialog
 *   GET /instagram/callback  → exchange code, resolve IG account, store
 *   DELETE /instagram/{id}   → disconnect
 */
class InstagramConnectController extends Controller
{
    private function redirectUri(): string
    {
        return url('/instagram/callback');
    }

    /** Kick off OAuth. */
    public function start(Request $request)
    {
        // IG-Login (Instagram-Login, graph.instagram.com) path — admin-selected.
        if ((string) SystemSetting::get('instagram_login_type', 'facebook') === 'instagram') {
            $igAppId = (string) SystemSetting::get('instagram_ig_app_id', SystemSetting::get('instagram_app_id', ''));
            if ($igAppId === '') {
                return back()->withErrors(['instagram' => 'Instagram-Login app id is not configured at /admin/settings/instagram.']);
            }
            $params = [
                'client_id'     => $igAppId,
                'redirect_uri'  => $this->redirectUri(),
                'response_type' => 'code',
                'scope'         => 'instagram_business_basic,instagram_business_manage_messages,instagram_business_manage_comments,instagram_business_content_publish',
                'state'         => csrf_token(),
            ];
            return redirect('https://www.instagram.com/oauth/authorize?' . http_build_query($params));
        }

        $appId    = (string) SystemSetting::get('instagram_app_id', '');
        $configId = (string) SystemSetting::get('instagram_config_id', '');
        $v        = (string) SystemSetting::get('instagram_graph_version', 'v23.0');
        if ($appId === '') {
            return back()->withErrors(['instagram' => 'Instagram is not configured. Ask the platform admin to set the App ID at /admin/settings/instagram.']);
        }
        // Insights → analytics page; content_publish → composer/reels; manage_metadata → webhook subscribe.
        $scope = 'instagram_basic,instagram_manage_messages,instagram_manage_comments,instagram_manage_insights,instagram_content_publish,pages_show_list,pages_messaging,pages_read_engagement,pages_manage_metadata,business_management';
        $params = [
            'client_id'     => $appId,
            'redirect_uri'  => $this->redirectUri(),
            'response_type' => 'code',
            'scope'         => $scope,
            'state'         => csrf_token(),
        ];
        if ($configId !== '') $params['config_id'] = $configId;
        return redirect('https://www.facebook.com/' . $v . '/dialog/oauth?' . http_build_query($params));
    }

    /** OAuth callback → store the connected account. */
    public function callback(Request $request)
    {
        if ($request->filled('error')) {
            return redirect('/instagram')->withErrors(['instagram' => (string) $request->string('error_description')]);
        }
        $code = (string) $request->string('code');
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        if ($code === '' || !$wsId) {
            return redirect('/instagram')->withErrors(['instagram' => 'Missing code or workspace.']);
        }

        // IG-Login path → Instagram-Login token exchange + graph.instagram.com profile.
        if ((string) SystemSetting::get('instagram_login_type', 'facebook') === 'instagram') {
            return $this->callbackInstagram($code, $wsId);
        }

        $tok = InstagramService::exchangeCode($code, $this->redirectUri());
        if (empty($tok['access_token'])) {
            Log::warning('[IG-CONNECT] token exchange failed', ['err' => $tok['error'] ?? '']);
            return redirect('/instagram')->withErrors(['instagram' => 'Token exchange failed: ' . ($tok['error'] ?? 'unknown')]);
        }
        $token   = (string) $tok['access_token'];
        $expires = isset($tok['expires_in']) ? now()->addSeconds((int) $tok['expires_in']) : null;
        $v       = (string) SystemSetting::get('instagram_graph_version', 'v23.0');

        // Upgrade the short-lived (~1-2h) token to a 60-day long-lived token,
        // otherwise the account flips to "needs re-auth" almost immediately.
        $long = InstagramService::extendFacebookToken($token);
        if (!empty($long['ok'])) {
            $token   = (string) $long['access_token'];
            $expires = now()->addSeconds((int) ($long['expires_in'] ?: 5184000));
        } else {
            Log::warning('[IG-CONNECT] long-lived exchange failed: ' . ($long['error'] ?? 'unknown'));
        }

        // Resolve the IG Professional account behind the user's Pages.
        $igId = ''; $username = ''; $name = ''; $pageId = ''; $pic = ''; $followers = null;
        try {
            $pages = Http::withToken($token)->acceptJson()->timeout(15)
                ->get("https://graph.facebook.com/{$v}/me/accounts", ['fields' => 'id,name,instagram_business_account'])
                ->json('data', []);
            foreach ((array) $pages as $p) {
                if (!empty($p['instagram_business_account']['id'])) {
                    $pageId = (string) $p['id'];
                    $igId   = (string) $p['instagram_business_account']['id'];
                    break;
                }
            }
            if ($igId) {
                $prof = Http::withToken($token)->acceptJson()->timeout(15)
                    ->get("https://graph.facebook.com/{$v}/{$igId}", ['fields' => 'username,name,profile_picture_url,followers_count'])
                    ->json();
                $username  = (string) ($prof['username'] ?? '');
                $name      = (string) ($prof['name'] ?? '');
                $pic       = (string) ($prof['profile_picture_url'] ?? '');
                $followers = isset($prof['followers_count']) ? (int) $prof['followers_count'] : null;
            }
        } catch (\Throwable $e) {
            Log::warning('[IG-CONNECT] account resolve failed: ' . $e->getMessage());
        }

        if ($igId === '') {
            return redirect('/instagram')->withErrors(['instagram' => 'No Instagram Professional account is linked to your Facebook Page. Link one in the Instagram app, then retry.']);
        }

        $account = InstagramAccount::updateOrCreate(
            ['workspace_id' => $wsId, 'ig_user_id' => $igId],
            [
                'user_id'          => Auth::id(),
                'username'         => $username,
                'name'             => $name,
                'profile_pic_url'  => $pic,
                'page_id'          => $pageId,
                'login_type'       => 'facebook',
                'access_token'     => $token,
                'token_expires_at' => $expires,
                'scopes'           => ['instagram_basic', 'instagram_manage_messages', 'instagram_manage_comments', 'instagram_manage_insights', 'instagram_content_publish'],
                'status'           => 'connected',
                'followers_count'  => $followers,
                'last_error'       => null,
            ]
        );

        // Subscribe the account to webhook fields so DMs/comments flow in (best-effort).
        try {
            (new InstagramService($account))->subscribeWebhooks();
        } catch (\Throwable $e) {
            Log::warning('[IG-CONNECT] subscribe failed: ' . $e->getMessage());
        }

        return redirect('/instagram')->with('status', 'Instagram account @' . ($username ?: $igId) . ' connected.');
    }

    /** IG-Login (graph.instagram.com) connect — short→long token + IG profile. */
    private function callbackInstagram(string $code, int $wsId)
    {
        $tok = InstagramService::exchangeCodeInstagram($code, $this->redirectUri());
        if (empty($tok['ok'])) {
            return redirect('/instagram')->withErrors(['instagram' => 'Instagram-Login token exchange failed: ' . ($tok['error'] ?? 'unknown')]);
        }
        $token   = (string) $tok['access_token'];
        $igId    = (string) ($tok['user_id'] ?? '');
        $expires = now()->addSeconds((int) ($tok['expires_in'] ?: 5184000));

        $username = ''; $name = ''; $pic = ''; $followers = null;
        try {
            $prof = Http::withToken($token)->acceptJson()->timeout(15)
                ->get('https://graph.instagram.com/me', ['fields' => 'user_id,username,name,profile_picture_url,followers_count'])->json();
            $username  = (string) ($prof['username'] ?? '');
            $name      = (string) ($prof['name'] ?? '');
            $pic       = (string) ($prof['profile_picture_url'] ?? '');
            $followers = isset($prof['followers_count']) ? (int) $prof['followers_count'] : null;
            if ($igId === '') $igId = (string) ($prof['user_id'] ?? '');
        } catch (\Throwable $e) {
            Log::warning('[IG-CONNECT] IG-login profile fetch failed: ' . $e->getMessage());
        }
        if ($igId === '') {
            return redirect('/instagram')->withErrors(['instagram' => 'Could not resolve the Instagram account.']);
        }

        $account = InstagramAccount::updateOrCreate(
            ['workspace_id' => $wsId, 'ig_user_id' => $igId],
            [
                'user_id'          => Auth::id(),
                'username'         => $username,
                'name'             => $name,
                'profile_pic_url'  => $pic,
                'page_id'          => null,
                'login_type'       => 'instagram',
                'access_token'     => $token,
                'token_expires_at' => $expires,
                'scopes'           => ['instagram_business_basic', 'instagram_business_manage_messages', 'instagram_business_manage_comments', 'instagram_business_content_publish'],
                'status'           => 'connected',
                'followers_count'  => $followers,
                'last_error'       => null,
            ]
        );
        try { (new InstagramService($account))->subscribeWebhooks(); } catch (\Throwable $e) {}

        return redirect('/instagram')->with('status', 'Instagram account @' . ($username ?: $igId) . ' connected.');
    }

    /** Refresh an account's profile stats (username / name / followers / avatar). */
    public function refresh(int $id)
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $acc = InstagramAccount::where('workspace_id', $wsId)->where('id', $id)->first();
        if (!$acc) return back()->withErrors(['instagram' => 'Account not found.']);
        $p = (new InstagramService($acc))->getProfile();
        if (!empty($p)) {
            if (!empty($p['username'])) $acc->username = (string) $p['username'];
            if (!empty($p['name'])) $acc->name = (string) $p['name'];
            if (!empty($p['profile_picture_url'])) $acc->profile_pic_url = (string) $p['profile_picture_url'];
            if (isset($p['followers_count'])) $acc->followers_count = (int) $p['followers_count'];
            $acc->save();
            return back()->with('status', 'Profile refreshed.');
        }
        return back()->withErrors(['instagram' => 'Could not refresh profile (token may need re-auth).']);
    }

    public function disconnect(int $id)
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        InstagramAccount::where('workspace_id', $wsId)->where('id', $id)->delete();
        return back()->with('status', 'Instagram account disconnected.');
    }
}

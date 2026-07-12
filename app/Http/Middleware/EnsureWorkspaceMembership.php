<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates workspace-scoped routes. The user must have a `current_workspace_id`
 * set AND actually belong to that workspace. We verify membership rather
 * than just trust the column value, because direct DB updates or stale
 * sessions could leave a user pointing at a workspace they were removed from.
 *
 * If the user has *no* workspace at all (fresh signup, all memberships
 * removed) we send them to /workspaces/select where they can pick or create.
 */
class EnsureWorkspaceMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) abort(401);

        $wsId = $user->current_workspace_id;
        if (!$wsId) {
            return redirect('/workspaces/select');
        }

        $isMember = $user->workspaces()->where('workspaces.id', $wsId)->exists();
        if (!$isMember) {
            // Impersonation exception. A platform admin with an active
            // impersonation session is DELIBERATELY viewing a workspace they
            // don't belong to — that's the feature. Without this guard, the
            // block below would "correct" the admin off the target AND persist
            // a new current_workspace_id onto the admin's own row (leaking the
            // impersonated workspace into the admin's real account, and showing
            // "no data" because queries then hit the wrong workspace). Bail out
            // early so the ImpersonationBanner's in-memory target stands.
            // (Only queried on the membership-miss path, so normal members pay
            // nothing.)
            if (\App\Models\ImpersonationSession::active()
                    ->forAdmin($user->id)
                    ->where('target_workspace_id', $wsId)
                    ->exists()) {
                return $next($request);
            }

            // The user was removed from this workspace — pick the first one they
            // still belong to, or send them to the picker if none.
            $fallback = $user->workspaces()->first();
            if (!$fallback) {
                $user->forceFill(['current_workspace_id' => null])->save();
                return redirect('/workspaces/select');
            }
            $user->switchWorkspace($fallback->id);
        }

        return $next($request);
    }
}

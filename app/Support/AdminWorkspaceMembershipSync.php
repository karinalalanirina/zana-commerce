<?php

namespace App\Support;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminWorkspaceMembershipSync
{
    public static function syncUserAssignment(User $user, ?Workspace $workspace, ?string $platformRole = null): void
    {
        if (!$workspace) {
            return;
        }

        self::ensureMembership(
            $workspace,
            $user,
            self::workspaceRoleForPlatformRole($platformRole, (int) $workspace->owner_user_id === (int) $user->id)
        );

        $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    }

    public static function syncWorkspaceOwner(Workspace $workspace, ?User $owner): void
    {
        if (!$owner) {
            return;
        }

        self::ensureMembership($workspace, $owner, WorkspacePermissions::ROLE_OWNER);
        $owner->forceFill(['current_workspace_id' => $workspace->id])->save();
    }

    public static function workspaceRoleForPlatformRole(?string $platformRole, bool $isOwner = false): string
    {
        if ($isOwner) {
            return WorkspacePermissions::ROLE_OWNER;
        }

        return match ((string) $platformRole) {
            'agent' => WorkspacePermissions::ROLE_AGENT,
            'admin' => WorkspacePermissions::ROLE_ADMIN,
            'owner' => WorkspacePermissions::ROLE_OWNER,
            'suspended' => WorkspacePermissions::ROLE_VIEWER,
            default => WorkspacePermissions::ROLE_MANAGER,
        };
    }

    private static function ensureMembership(Workspace $workspace, User $user, string $role): void
    {
        $existing = DB::table('workspace_user')
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            DB::table('workspace_user')
                ->where('workspace_id', $workspace->id)
                ->where('user_id', $user->id)
                ->update([
                    'role' => $role,
                    'joined_at' => $existing->joined_at ?: Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

            return;
        }

        $workspace->members()->attach($user->id, [
            'role' => $role,
            'joined_at' => now(),
        ]);
    }
}

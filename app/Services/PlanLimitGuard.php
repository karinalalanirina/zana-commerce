<?php

namespace App\Services;

use App\Exceptions\PlanLimitReachedException;
use App\Models\Workspace;

/**
 * Single entry-point for plan enforcement. Controllers call:
 *
 *   PlanLimitGuard::check($workspace, 'flow_limit', $currentFlowCount);
 *   PlanLimitGuard::feature($workspace, 'access_kanban_view');
 *
 * Both throw PlanLimitReachedException on failure — the exception's
 * render() method returns 422 JSON or back-with-error depending on
 * request type.
 *
 * Bypass: workspace owners with the "Super Admin" or "Admin" Spatie
 * role bypass every limit (matches SnapNest's pattern — admins are
 * never blocked by their own product).
 */
class PlanLimitGuard
{
    /**
     * Throw if the workspace's count for $limitKey is already at or
     * above its package limit. NULL limit = unlimited (no throw).
     *
     * Pass the count of CURRENT rows (not "rows after this create").
     * The check is `>=` so it triggers when the count equals the cap
     * — about to create row N+1.
     */
    public static function check(?Workspace $workspace, string $limitKey, int $used): void
    {
        if (!$workspace || self::bypass($workspace)) return;

        $limit = $workspace->effectiveLimit($limitKey, null);
        if ($limit === null) return;            // unlimited
        if ((int) $limit <= 0) return;           // 0 / negative = unlimited too (defensive)
        if ($used < (int) $limit) return;

        throw new PlanLimitReachedException(
            limitKey: $limitKey,
            used:     $used,
            limit:    (int) $limit,
        );
    }

    /**
     * Throw if a feature toggle is off on the workspace's package.
     * The package's column for $featureKey is a boolean — true =
     * feature available, false = blocked.
     *
     * Per-workspace plan_overrides can flip a feature on / off
     * regardless of the underlying package (admin override).
     */
    public static function feature(?Workspace $workspace, string $featureKey): void
    {
        if (!$workspace || self::bypass($workspace)) return;

        $enabled = $workspace->effectiveLimit($featureKey, true);
        if ($enabled) return;

        throw new PlanLimitReachedException(
            limitKey: $featureKey,
            reason:   'feature_disabled',
        );
    }

    /** Non-throwing version of feature() — returns bool. */
    public static function hasFeature(?Workspace $workspace, string $featureKey): bool
    {
        if (!$workspace) return false;
        if (self::bypass($workspace)) return true;
        return (bool) $workspace->effectiveLimit($featureKey, true);
    }

    /**
     * Bypass for platform admins. Spatie's hasRole is the source of
     * truth; falls back to the legacy `users.role` column.
     */
    private static function bypass(Workspace $workspace): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        try {
            if ($user->hasRole('Super Admin') || $user->hasRole('Admin')) return true;
        } catch (\Throwable $e) {}
        return in_array($user->role ?? null, ['admin', 'A'], true);
    }
}

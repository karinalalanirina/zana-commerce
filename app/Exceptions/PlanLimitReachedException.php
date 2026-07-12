<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown by App\Services\PlanLimitGuard when a workspace has hit
 * (or exceeded) its package's cap on a specific resource, or when
 * a feature is disabled on the current plan.
 *
 * Caught at controller boundaries → 422 JSON for API, redirect-back
 * with-error for web forms. Renderable for the App\Exceptions\Handler.
 */
class PlanLimitReachedException extends RuntimeException
{
    public function __construct(
        public readonly string $limitKey,
        public readonly int|string|null $used = null,
        public readonly int|string|null $limit = null,
        public readonly string $reason = 'limit_reached', // 'limit_reached' | 'feature_disabled'
        string $message = '',
        ?Throwable $previous = null,
    ) {
        $message = $message !== '' ? $message : $this->buildMessage();
        parent::__construct($message, 0, $previous);
    }

    private function buildMessage(): string
    {
        if ($this->reason === 'feature_disabled') {
            return 'This feature isn\'t available on your current plan. Upgrade to unlock it.';
        }
        $label = strtolower(str_replace('_', ' ', preg_replace('/_limit$/', '', $this->limitKey)));
        if ($this->limit !== null) {
            return "You've reached your plan's limit of {$this->limit} {$label}s. Upgrade your plan to add more, or delete an existing one to free up space.";
        }
        return "You've reached your plan's limit for {$label}. Upgrade your plan to add more, or delete an existing one.";
    }

    public function render($request)
    {
        $payload = [
            'ok'      => false,
            'error'   => 'plan_limit_reached',
            'reason'  => $this->reason,
            'key'     => $this->limitKey,
            'used'    => $this->used,
            'limit'   => $this->limit,
            'message' => $this->getMessage(),
        ];
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json($payload, 422);
        }
        return back()->with('error', $this->getMessage());
    }
}

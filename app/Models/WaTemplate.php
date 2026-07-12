<?php

namespace App\Models;

use App\Models\Concerns\LogsNotifications;
use App\Services\WorkspaceEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WhatsApp Business template — the Meta-approved kind that gets
 * sent through `WhatsAppDispatcher` and embedded in broadcasts.
 *
 * Distinct from `App\Models\ChatTemplate` (chat snippets the
 * operator picks from the chat-page picker) — that one is local
 * and not Meta-approved. Both can coexist.
 */
class WaTemplate extends Model
{
    use HasFactory, LogsNotifications;

    protected $table = 'wa_templates';

    protected $fillable = [
        'user_id',
        'workspace_id',
        'provider_config_id',
        'meta_template_id',
        'twilio_content_sid',
        'channel',
        'meta_status',
        'quality_score',
        'rejection_reason_code',
        'template_name',
        'category',
        'meta_category',
        'template_type',
        'header',
        'header_location',
        'template_body',
        'footer',
        'buttons',
        'carousel_data',
        'variable_map',
        'attachment_type',
        'attachment_file',
        'language',
        'parameter_format',
        'status',
        'rejection_reason',
        'approved_at',
        'submitted_at',
        'last_synced_at',
        'paused_until',
    ];

    protected $casts = [
        'template_name'    => 'encrypted',
        'header'           => 'encrypted',
        'header_location'  => 'encrypted:array',
        'template_body'    => 'encrypted',
        'footer'           => 'encrypted',
        'buttons'          => 'encrypted:array',
        'carousel_data'    => 'encrypted:array',
        'variable_map'     => 'encrypted:array',
        'rejection_reason' => 'encrypted',
        'approved_at'      => 'datetime',
        'submitted_at'     => 'datetime',
        'last_synced_at'   => 'datetime',
        'paused_until'     => 'datetime',
    ];

    public const STATUSES   = ['pending', 'approved', 'rejected', 'public'];
    public const CATEGORIES = ['travel', 'healthcare', 'education', 'ecommerce', 'festival', 'finance', 'utility'];
    public const TYPES      = ['standard', 'carousel', 'media', 'auth'];

    /** Meta-side status machine — distinct from the local UI `status`. */
    public const META_STATUSES = [
        'PENDING', 'APPROVED', 'REJECTED', 'IN_APPEAL', 'PENDING_DELETION',
        'DELETED', 'DISABLED', 'PAUSED', 'LIMIT_EXCEEDED', 'FLAGGED',
    ];
    public const META_QUALITY  = ['UNKNOWN', 'GREEN', 'YELLOW', 'RED'];
    public const PARAM_FORMATS = ['POSITIONAL', 'NAMED'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(WaProviderConfig::class, 'provider_config_id');
    }

    /**
     * Which engine this template targets: 'baileys' (Unofficial API),
     * 'waba' (Meta Cloud), or 'twilio'. Prefers the stored `channel`;
     * falls back to deriving from the Meta/Twilio fields for rows that
     * pre-date the column.
     */
    public function engineKey(): string
    {
        if (in_array($this->channel, ['baileys', 'waba', 'twilio'], true)) {
            return $this->channel;
        }
        if (!empty($this->twilio_content_sid)) return 'twilio';
        if ($this->meta_template_id || $this->provider_config_id) return 'waba';
        return 'baileys';
    }

    /** Human label for the template's engine (user-facing, never "Baileys"). */
    public function engineLabel(): string
    {
        return [
            'baileys' => 'Unofficial API',
            'waba'    => 'Meta (WABA)',
            'twilio'  => 'Twilio',
        ][$this->engineKey()] ?? 'Unofficial API';
    }

    /**
     * True if the row is sendable as a Meta-Cloud template. Used by
     * the dispatcher's quality gate — refuses to dispatch unless the
     * template is APPROVED on Meta's side AND not paused.
     */
    public function isMetaApproved(): bool
    {
        if ($this->meta_status !== 'APPROVED') return false;
        if ($this->paused_until && $this->paused_until->isFuture()) return false;
        return true;
    }

    /** Quality-gate floor: RED quality blocks all sends. */
    public function isQualityHealthy(): bool
    {
        return ! in_array($this->quality_score, ['RED'], true);
    }

    public function scopeWithMetaStatus(Builder $q, ?string $status): Builder
    {
        return $status ? $q->where('meta_status', strtoupper($status)) : $q;
    }

    /** Templates that need a Meta GET sweep because the webhook may have missed them. */
    public function scopeStaleSweepTargets(Builder $q): Builder
    {
        return $q->where('meta_status', 'PENDING')
                 ->whereNotNull('meta_template_id')
                 ->where('submitted_at', '<', now()->subHour())
                 ->where(function ($qq) {
                     $qq->whereNull('last_synced_at')
                        ->orWhere('last_synced_at', '<', now()->subMinutes(30));
                 });
    }

    public function scopeForUser(Builder $q, ?int $userId): Builder
    {
        return $userId ? $q->where('user_id', $userId) : $q;
    }

    /**
     * Workspace-shared visibility. ALSO includes admin-seeded global
     * templates (workspace_id IS NULL AND user_id IS NULL) so every
     * workspace can use those without duplicating rows.
     */
    public function scopeForCurrentWorkspace(Builder $q): Builder
    {
        $user = auth()->user();
        if (!$user) return $q->whereRaw('1=0');
        $uId  = (int) $user->id;
        $wsId = (int) ($user->current_workspace_id ?? 0);
        return $q->where(function ($qq) use ($wsId, $uId) {
            // Templates owned by this workspace
            $qq->where('workspace_id', $wsId)
            // Admin-seeded globals — visible to every workspace
               ->orWhere(function ($qqq) {
                   $qqq->whereNull('workspace_id')->whereNull('user_id');
               })
            // Legacy: pre-migration user-owned rows (still NULL workspace_id)
               ->orWhere(function ($qqq) use ($uId) {
                   $qqq->whereNull('workspace_id')->where('user_id', $uId);
               });
        });
    }

    public function scopeWithStatus(Builder $q, ?string $status): Builder
    {
        if (!$status || $status === 'all') return $q;
        return $q->where('status', $status);
    }

    public function scopeOfCategory(Builder $q, ?string $category): Builder
    {
        if (!$category || $category === 'all') return $q;
        return $q->where('category', $category);
    }

    /**
     * "Sendable" templates for the current workspace's engine.
     *
     * WABA workspaces need Meta's verdict — only `meta_status='APPROVED'`
     * counts, and PAUSED/DISABLED/PENDING_DELETION must be excluded
     * because Meta will refuse them at send time. The local `status`
     * column is synthetic on this engine (Baileys flow marks every
     * template 'approved' locally) so it must not be the filter.
     *
     * Baileys/Twilio workspaces have no Meta gate — `status` IN
     * ('approved','public') is the operator-controlled signal.
     *
     * One scope, eleven callers (BroadcastsController, ScheduledController,
     * ChatController, TeamInboxController, TemplatesController, …) all
     * automatically get the right answer for their workspace's engine.
     */
    public function scopeApproved(Builder $q): Builder
    {
        $user = auth()->user();
        $wsId = (int) ($user?->current_workspace_id ?? 0);

        if ($wsId && WorkspaceEngine::isWaba($wsId)) {
            // `meta_template_id` is the ONLY trustworthy signal that
            // this template was actually submitted to and approved by
            // Meta. Migration 2026_05_24_040000 bulk-set
            // meta_status='APPROVED' on every locally-approved row to
            // keep legacy sends working, so filtering only on
            // meta_status would still show Baileys synthetic approvals.
            // Requiring meta_template_id rules those ghosts out.
            $q->whereNotNull('meta_template_id')
              ->where('meta_status', 'APPROVED')
              ->where(function ($qq) {
                  $qq->whereNull('paused_until')
                     ->orWhere('paused_until', '<=', now());
              });
        } else {
            $q->whereIn('status', ['approved', 'public']);
        }
        // A template lives on a specific WABA number. If that number was
        // DISCONNECTED (config kept, status≠connected) or REMOVED (config row
        // deleted), the template can't be sent — so it must disappear from every
        // send picker. providerLive() enforces that. provider_config_id NULL =
        // non-WABA template (Baileys/legacy) → always kept.
        return $q->providerLive();
    }

    /**
     * Only templates whose WABA number is still connected — or that aren't
     * tied to a WABA number at all. Hides templates whose provider config was
     * disconnected or deleted, everywhere this scope is applied.
     */
    public function scopeProviderLive(Builder $q): Builder
    {
        return $q->where(function ($w) {
            $w->whereNull('provider_config_id')
              ->orWhereHas('provider', function ($c) {
                  $c->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED);
              });
        });
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    public function scopeRejected(Builder $q): Builder
    {
        return $q->where('status', 'rejected');
    }

    /**
     * Search template_name in PHP after hydration — `template_name`
     * is encrypted, so LIKE on ciphertext returns nothing. Caller
     * passes the already-fetched collection.
     */
    public static function filterByName($items, ?string $term)
    {
        $term = mb_strtolower(trim((string) $term));
        if ($term === '') return $items;
        return $items->filter(fn ($t) => str_contains(mb_strtolower((string) $t->template_name), $term))->values();
    }

    /**
     * Read a template's stored attachment ONCE and return it base64-inlined
     * so the Node bulk senders never have to download media from a URL per
     * recipient (the old path silently dropped the image whenever Node
     * couldn't reach `APP_DOMAIN_NAME/storage/...` — localhost, no
     * storage:link, private bucket, auth wall, etc.).
     *
     * Files are stored via `$file->store('wa-templates', 'public')`, so the
     * stored value is a public-disk-relative path like `wa-templates/abc.jpg`
     * and the real disk path is `storage/app/public/wa-templates/abc.jpg`.
     *
     * Cheap + safe even at 10k recipients: templateData is built ONCE per bulk
     * job, not per recipient. Never throws — on a missing/unreadable file it
     * returns nulls (callers keep `attachment_url` as the network fallback)
     * and logs so the operator can see why media was inlined-null.
     *
     * @return array{attachment_base64: ?string, attachment_mime: ?string}
     */
    public static function inlineAttachment(?string $attachmentFile): array
    {
        $out = ['attachment_base64' => null, 'attachment_mime' => null];
        if (empty($attachmentFile)) {
            return $out;
        }

        try {
            $disk = media_storage();
            $rel  = ltrim($attachmentFile, '/');

            if (!$disk->exists($rel)) {
                \Illuminate\Support\Facades\Log::warning(
                    '[TEMPLATE-MEDIA] attachment file missing on disk — Node will fall back to URL download',
                    ['attachment_file' => $attachmentFile]
                );
                return $out;
            }

            $bytes = $disk->get($rel);
            if ($bytes === null || $bytes === '') {
                \Illuminate\Support\Facades\Log::warning(
                    '[TEMPLATE-MEDIA] attachment file empty — Node will fall back to URL download',
                    ['attachment_file' => $attachmentFile]
                );
                return $out;
            }

            $out['attachment_base64'] = base64_encode($bytes);
            try {
                $out['attachment_mime'] = $disk->mimeType($rel) ?: null;
            } catch (\Throwable $e) {
                $out['attachment_mime'] = null;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[TEMPLATE-MEDIA] inlineAttachment failed', [
                'attachment_file' => $attachmentFile,
                'error'           => $e->getMessage(),
            ]);
            return ['attachment_base64' => null, 'attachment_mime' => null];
        }

        return $out;
    }

    /**
     * Pretty status label used in card pills + filter tabs.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'Approved',
            'public'   => 'Public',
            'pending'  => 'In review',
            'rejected' => 'Rejected',
            default    => ucfirst((string) $this->status),
        };
    }
}

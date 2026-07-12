<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One scraped clip in the reels-autoposter queue. Deduped by
 * (instagram_account_id, source_id). Node scrapes + enqueues; Node's post
 * tick claims the oldest `queued` row, publishes it via the official Graph
 * API, and flips it to `posted` / `failed`. Mirrors Reels-AutoPilot's
 * `reels` table.
 */
class InstagramRepostItem extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_POSTED = 'posted';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'workspace_id', 'instagram_account_id', 'source', 'source_id', 'source_handle',
        'caption', 'video_path', 'public_url', 'status', 'claimed_at',
        'media_id', 'last_error', 'posted_at', 'meta',
    ];

    protected $casts = [
        'meta'       => 'array',
        'claimed_at' => 'datetime',
        'posted_at'  => 'datetime',
    ];

    public function scopeQueued(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_QUEUED);
    }

    public function account()
    {
        return $this->belongsTo(InstagramAccount::class, 'instagram_account_id');
    }
}

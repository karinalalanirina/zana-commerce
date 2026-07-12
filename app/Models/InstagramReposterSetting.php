<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-IG-account reels-autoposter config. Scraping is done by yt-dlp (no IG
 * login); posting uses the account's existing official Graph token. The only
 * secret here is the YouTube Data API key (encrypted at rest → TEXT column).
 */
class InstagramReposterSetting extends Model
{
    protected $fillable = [
        'workspace_id', 'instagram_account_id', 'enabled',
        'source_ig_accounts', 'youtube_enabled', 'youtube_api_key', 'source_yt_channels',
        'fetch_limit', 'scraper_interval_min', 'posting_interval_min',
        'daily_cap', 'remove_after_min', 'post_to_story', 'hashtags',
        'last_scrape_at', 'last_post_at',
    ];

    protected $casts = [
        'enabled'              => 'boolean',
        'youtube_enabled'      => 'boolean',
        'post_to_story'        => 'boolean',
        'source_ig_accounts'   => 'array',
        'source_yt_channels'   => 'array',
        'youtube_api_key'      => 'encrypted',
        'fetch_limit'          => 'integer',
        'scraper_interval_min' => 'integer',
        'posting_interval_min' => 'integer',
        'daily_cap'            => 'integer',
        'remove_after_min'     => 'integer',
        'last_scrape_at'       => 'datetime',
        'last_post_at'         => 'datetime',
    ];

    protected $hidden = ['youtube_api_key'];

    public function account()
    {
        return $this->belongsTo(InstagramAccount::class, 'instagram_account_id');
    }

    public function items()
    {
        return $this->hasMany(InstagramRepostItem::class, 'instagram_account_id', 'instagram_account_id');
    }
}

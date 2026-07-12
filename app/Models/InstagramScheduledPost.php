<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstagramScheduledPost extends Model
{
    protected $fillable = [
        'workspace_id', 'instagram_account_id', 'media_type', 'image_url', 'video_url',
        'media_urls', 'caption', 'scheduled_at', 'status', 'media_id', 'last_error',
        'auto_keyword', 'auto_public_reply', 'auto_dm', 'auto_flow_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'media_urls'   => 'array',
    ];
}

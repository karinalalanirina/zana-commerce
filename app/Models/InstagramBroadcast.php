<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstagramBroadcast extends Model
{
    protected $fillable = [
        'workspace_id', 'instagram_account_id', 'body',
        'recipients', 'total', 'cursor',
        'sent', 'failed', 'skipped_window', 'status', 'last_error', 'claimed_at',
    ];

    protected $casts = [
        'recipients' => 'array',
        'claimed_at' => 'datetime',
    ];

    public function recipientRows()
    {
        return $this->hasMany(InstagramBroadcastRecipient::class, 'broadcast_id');
    }
}

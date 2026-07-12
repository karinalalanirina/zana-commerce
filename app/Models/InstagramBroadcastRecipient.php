<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstagramBroadcastRecipient extends Model
{
    protected $fillable = [
        'broadcast_id', 'igsid', 'status', 'mid', 'error', 'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function broadcast()
    {
        return $this->belongsTo(InstagramBroadcast::class, 'broadcast_id');
    }
}

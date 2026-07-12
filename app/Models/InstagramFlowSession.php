<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstagramFlowSession extends Model
{
    protected $fillable = [
        'workspace_id', 'instagram_account_id', 'igsid',
        'flow_id', 'node_id', 'vars', 'expires_at',
    ];

    protected $casts = [
        'vars'       => 'array',
        'expires_at' => 'datetime',
    ];

    public function isLive(): bool
    {
        return !$this->expires_at || $this->expires_at->isFuture();
    }
}

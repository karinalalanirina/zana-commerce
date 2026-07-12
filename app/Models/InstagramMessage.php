<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstagramMessage extends Model
{
    protected $fillable = [
        'workspace_id', 'instagram_account_id', 'igsid',
        'direction', 'body', 'mid', 'source',
    ];

    /** Append a row and return it. */
    public static function log(InstagramAccount $account, string $igsid, string $direction, ?string $body, ?string $source = null, ?string $mid = null): self
    {
        return self::create([
            'workspace_id'         => $account->workspace_id,
            'instagram_account_id' => $account->id,
            'igsid'                => $igsid,
            'direction'            => $direction,
            'body'                 => $body,
            'source'               => $source,
            'mid'                  => $mid,
        ]);
    }
}

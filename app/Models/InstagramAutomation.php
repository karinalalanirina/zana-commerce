<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstagramAutomation extends Model
{
    protected $fillable = [
        'workspace_id', 'instagram_account_id', 'type', 'name',
        'trigger_keyword', 'match_mode', 'post_id',
        'public_reply', 'dm_message', 'flow_id',
        'is_active', 'fired_count', 'meta_json',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'meta_json'  => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(InstagramAccount::class, 'instagram_account_id');
    }

    /** Does this rule's keyword(s) match the given text? */
    public function matches(string $text): bool
    {
        $text = mb_strtolower(trim($text));
        if ($this->match_mode === 'any') return true;
        $kw = array_filter(array_map('trim', explode(',', mb_strtolower((string) $this->trigger_keyword))));
        foreach ($kw as $k) {
            if ($k === '') continue;
            if ($this->match_mode === 'exact' && $text === $k) return true;
            if ($this->match_mode !== 'exact' && str_contains($text, $k)) return true;
        }
        return false;
    }
}

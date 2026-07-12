<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-managed currency. Ported from SnapNest's pattern.
 *
 * `exchange_rate` is value of 1 unit in USD (system base). Updated
 * either manually in the admin form or via the Fetch Rates button
 * which pulls from https://open.er-api.com/v6/latest/USD (free).
 */
class Currency extends Model
{
    protected $fillable = [
        'name', 'code', 'symbol', 'precision', 'exchange_rate', 'is_active',
    ];

    protected $casts = [
        'precision'     => 'integer',
        'exchange_rate' => 'decimal:6',
        'is_active'     => 'boolean',
    ];

    public function scopeActive(Builder $q): Builder { return $q->where('is_active', true); }

    /**
     * Normalize the code to uppercase before save — ISO codes are
     * conventionally uppercase ("USD" not "usd").
     */
    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }
}

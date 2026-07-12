<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot row between `broadcasts` and `contacts` — one per
 * recipient, carries the WhatsApp delivery lifecycle (pending →
 * processing → sent → delivered → read, or failed).
 *
 * Treated as a real model (not just a pivot) so the controller
 * can group / count by status without re-running ad-hoc SQL.
 */
class BroadcastContact extends Model
{
    use HasFactory;

    protected $table = 'broadcast_contacts';

    protected $fillable = [
        'broadcast_id',
        'contact_id',
        'status',
        'error_message',
        'whatsapp_message_id',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        // Encrypted because Node sometimes returns the recipient
        // phone or template body in the failure detail.
        'error_message' => 'encrypted',
        'sent_at'       => 'datetime',
        'delivered_at'  => 'datetime',
        'read_at'       => 'datetime',
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}

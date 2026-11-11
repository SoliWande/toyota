<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class CallAuditAttempt extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $hidden = ['note_encrypted', 'source_fingerprint_used', 'idempotency_key', 'payload_digest'];

    protected $casts = [
        'attempt_no' => 'integer', 'item_version_used' => 'integer',
        'started_at' => 'immutable_datetime', 'recorded_at' => 'immutable_datetime',
        'retry_due_at' => 'immutable_datetime', 'answer_1' => 'boolean',
        'answer_2' => 'boolean', 'answer_3' => 'boolean', 'note_encrypted' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Recorded call audit attempts are append-only.');
        });
        static::deleting(function () {
            throw new LogicException('Recorded call audit attempts are append-only.');
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CallAuditItem::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CallAuditEvent::class, 'attempt_id');
    }
}

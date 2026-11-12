<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AwardPublication extends Model
{
    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['*'];

    protected $hidden = ['evidence_encrypted', 'receipt_encrypted', 'intent_digest', 'evidence_digest', 'digest_key_id', 'request_key'];

    protected $casts = ['evidence_encrypted' => 'encrypted:array', 'receipt_encrypted' => 'encrypted:array',
        'published_at' => 'immutable_datetime', 'source_cutoff_at' => 'immutable_datetime',
        'batch_generation' => 'integer', 'ledger_cutoff_id' => 'integer'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Publication evidence is immutable.'));
        static::deleting(fn () => throw new LogicException('Publication evidence is immutable.'));
    }
}

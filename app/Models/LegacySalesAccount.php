<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Audit data only. Deliberately does not implement Authenticatable or HasApiTokens. */
class LegacySalesAccount extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['password', 'remember_token', 'email', 'phone', 'admin_note', 'rejection_reason'];

    protected static function booted(): void
    {
        static::saving(fn () => throw new LogicException('Legacy accounts are read-only audit data.'));
        static::deleting(fn () => throw new LogicException('Legacy accounts are read-only audit data.'));
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }
}

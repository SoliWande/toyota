<?php

namespace App\Models;

use App\Enums\AwardPeriod;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use LogicException;

class Award extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'period_type', 'period_start', 'period_end'];

    protected $casts = [
        'period_type' => AwardPeriod::class,
        'period_start' => 'immutable_datetime',
        'period_end' => 'immutable_datetime',
        'published_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $award) {
            if ($award->published_by !== null
                && ! User::whereKey($award->published_by)->where('role', UserRole::Admin->value)->exists()) {
                throw new InvalidArgumentException('An award publisher must be an admin.');
            }
        });

        static::updating(function (self $award) {
            if ($award->getOriginal('published_at') !== null) {
                throw new LogicException('Published awards are immutable.');
            }
        });

        static::deleting(function (self $award) {
            if ($award->published_at !== null) {
                throw new LogicException('Published awards are immutable.');
            }
        });
    }

    public function winners(): HasMany
    {
        return $this->hasMany(AwardWinner::class);
    }

    public function getStatusAttribute(): string
    {
        return $this->published_at === null ? 'draft' : 'published';
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}

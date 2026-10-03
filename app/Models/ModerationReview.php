<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use LogicException;

class ModerationReview extends Model
{
    use HasFactory;

    protected $guarded = ['*'];

    protected $casts = ['reviewed_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $review) {
            if (! User::whereKey($review->reviewed_by)->where('role', UserRole::Admin->value)->exists()) {
                throw new InvalidArgumentException('A moderation reviewer must be an admin.');
            }

            if ($review->sales_id !== null
                && ! User::whereKey($review->sales_id)->where('role', UserRole::Sales->value)->exists()) {
                throw new InvalidArgumentException('An account review must target a sales account.');
            }
        });

        static::updating(fn () => throw new LogicException('Moderation history is append-only.'));
        static::deleting(fn () => throw new LogicException('Moderation history is append-only.'));
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(CustomerSubmission::class, 'customer_submission_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

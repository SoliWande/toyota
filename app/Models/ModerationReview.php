<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
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
            $actor = User::find($review->reviewed_by);
            if ($review->customer_submission_id !== null) {
                if (! $actor || $actor->role !== UserRole::Admin) {
                    throw new InvalidArgumentException('A submission reviewer must be an admin.');
                }

                return; // Customer moderation authorization remains unchanged.
            }
            $target = User::find($review->sales_id);
            if (! $target || ! in_array($target->role, [UserRole::Sales, UserRole::Dealer], true)) {
                throw new InvalidArgumentException('An account review must target a registered Sales or Manager account.');
            }
            $resubmit = $actor && $actor->id === $target->id && $review->from_status === 'rejected'
                && $review->to_status === 'pending' && $target->status === UserStatus::Pending
                && $review->rejection_reason === null && $review->admin_note === null;
            $manager = $actor && $actor->status === UserStatus::Active && $actor->can('manageStaff', $target);
            if (! $actor || (! $resubmit && $actor->role !== UserRole::Admin && ! $manager)) {
                throw new InvalidArgumentException('Account reviewer has no authority for this Dealer.');
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

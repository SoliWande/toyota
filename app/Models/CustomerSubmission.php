<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Support\FacebookProfileUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class CustomerSubmission extends Model
{
    use HasFactory;

    protected $fillable = ['customer_name', 'facebook_url', 'phone', 'notes'];

    protected $hidden = ['approved_facebook_identity'];

    protected $attributes = ['status' => 'pending'];

    protected $casts = [
        'status' => SubmissionStatus::class,
        'submitted_at' => 'immutable_datetime',
        'reviewed_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $submission) {
            $submission->submitted_at ??= now();
        });

        static::saving(function (self $submission) {
            $submission->facebook_url_normalized = FacebookProfileUrl::normalize($submission->facebook_url);

            if (! User::whereKey($submission->sales_id)->where('role', UserRole::Sales->value)->exists()) {
                throw new InvalidArgumentException('A submission must belong to a sales account.');
            }

            if ($submission->reviewed_by !== null
                && ! User::whereKey($submission->reviewed_by)->where('role', UserRole::Admin->value)->exists()) {
                throw new InvalidArgumentException('A submission reviewer must be an admin.');
            }
        });
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function moderationReviews(): HasMany
    {
        return $this->hasMany(ModerationReview::class);
    }
}

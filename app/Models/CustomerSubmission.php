<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Services\FacebookUrlNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class CustomerSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_name', 'facebook_url', 'phone', 'notes',
        'vehicle_model', 'first_registration_year', 'vehicle_color', 'license_plate',
    ];

    protected $hidden = ['approved_facebook_identity', 'evidence_image_path'];

    protected $attributes = ['status' => 'pending'];

    protected $casts = [
        'status' => SubmissionStatus::class,
        'submitted_at' => 'immutable_datetime',
        'reviewed_at' => 'immutable_datetime',
        'first_registration_year' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $submission) {
            $submission->submitted_at ??= now();
            $creator = User::whereKey($submission->created_by_user_id)->where('role', UserRole::Dealer->value)->first();
            if ($creator === null || $creator->dealer_id === null || $submission->sales_id !== null) {
                throw new InvalidArgumentException('A new submission requires a Dealer account.');
            }
            $submission->dealer_id = $creator->dealer_id;
        });

        static::updating(function (self $submission) {
            if ($submission->isDirty('dealer_id') || $submission->isDirty('sales_id')
                || $submission->isDirty('created_by_user_id') || $submission->isDirty('submitted_at')) {
                throw new \LogicException('Submission attribution and submitted time are immutable.');
            }
        });

        static::saving(function (self $submission) {
            $submission->facebook_url_normalized = app(FacebookUrlNormalizer::class)->normalize($submission->facebook_url);

            if ($submission->reviewed_by !== null
                && ! User::whereKey($submission->reviewed_by)->where('role', UserRole::Admin->value)->exists()) {
                throw new InvalidArgumentException('A submission reviewer must be an admin.');
            }
        });
    }

    public function sales(): BelongsTo
    {
        // Historical audit compatibility; never used to authorize a Dealer.
        return $this->legacySales();
    }

    public function legacySales(): BelongsTo
    {
        return $this->belongsTo(LegacySalesAccount::class, 'sales_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
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

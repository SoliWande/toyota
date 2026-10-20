<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Services\FacebookUrlNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class CustomerSubmission extends Model
{
    use HasFactory;

    private bool $adminPendingRevision = false;

    public function saveAdminRevision(User $admin): void
    {
        Gate::forUser($admin)->authorize('review', $this);
        $this->adminPendingRevision = $this->getRawOriginal('status') === SubmissionStatus::Pending->value
            && $this->status === SubmissionStatus::Pending;
        try {
            $this->save();
        } finally {
            $this->adminPendingRevision = false;
        }
    }

    protected $fillable = [
        'customer_name', 'facebook_url', 'phone', 'notes',
        'address_line', 'province_code', 'province_name', 'ward_code', 'ward_name',
        'vehicle_model', 'first_registration_year', 'vehicle_color', 'license_plate',
    ];

    protected $hidden = [
        'approved_facebook_identity', 'evidence_image_path',
        'address_line', 'province_code', 'province_name', 'ward_code', 'ward_name',
    ];

    protected $attributes = ['status' => 'pending', 'source' => 'sales'];

    protected $casts = [
        'status' => SubmissionStatus::class,
        'submitted_at' => 'immutable_datetime',
        'reviewed_at' => 'immutable_datetime',
        'first_registration_year' => 'integer',
        'consented_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $submission) {
            $submission->submitted_at ??= now();
            if ($submission->source === 'sales') {
                $submission->dealer_id = User::whereKey($submission->sales_id)->value('dealer_id');
            }
            if ($submission->dealer_id === null) {
                throw new InvalidArgumentException('A new submission requires its sales dealer snapshot.');
            }
        });

        static::saving(function (self $submission) {
            if ($submission->exists && ($submission->isDirty('source')
                || (! $submission->adminPendingRevision && ($submission->isDirty('dealer_id') || $submission->isDirty('sales_id'))))) {
                throw new \LogicException('Submission sales and dealer attribution are immutable.');
            }
            $qr = $submission->source === 'dealer_qr';
            $submission->facebook_url_normalized = $qr && $submission->facebook_url === null
                ? null : app(FacebookUrlNormalizer::class)->normalize($submission->facebook_url);

            if ((! $qr && ! User::whereKey($submission->sales_id)->where('role', UserRole::Sales->value)->exists())
                || ($qr && ($submission->sales_id !== null || $submission->dealer_id === null))
                || ! in_array($submission->source, ['sales', 'dealer_qr'], true)) {
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

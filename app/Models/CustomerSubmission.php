<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Services\FacebookUrlNormalizer;
use App\Services\RegistrationPhone;
use App\Services\SubmissionIntake;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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
        'odo' => 'integer',
        'status' => SubmissionStatus::class,
        'submitted_at' => 'immutable_datetime',
        'reviewed_at' => 'immutable_datetime',
        'first_registration_year' => 'integer',
        'consented_at' => 'immutable_datetime',
    ];

    protected function performInsert(Builder $query)
    {
        return app(SubmissionIntake::class)->locked(function () use ($query) {
            if (in_array($this->status, [SubmissionStatus::Pending, SubmissionStatus::Approved], true)) {
                $errors = app(SubmissionIntake::class)->conflicts($this->phone, $this->license_plate, true,
                    $this->getConnection(), null, [$this->phone_normalized ?? '']);
                if ($errors) {
                    throw ValidationException::withMessages($errors);
                }
            }

            return parent::performInsert($query);
        }, $this->getConnection());
    }

    protected function performUpdate(Builder $query)
    {
        $reactivating = $this->isDirty('status') && $this->getRawOriginal('status') === 'rejected'
            && in_array($this->status, [SubmissionStatus::Pending, SubmissionStatus::Approved], true);
        if (! $reactivating && ! $this->isDirty(['phone', 'phone_normalized', 'license_plate'])) {
            return parent::performUpdate($query);
        }

        return app(SubmissionIntake::class)->locked(function () use ($query) {
            $current = $this->getConnection()->table($this->getTable())->where('id', $this->getKey())->lockForUpdate()->first();
            if ($current && in_array($this->isDirty('status') ? $this->status->value : $current->status, ['pending', 'approved'], true)) {
                if ($current->status === 'rejected') {
                    $errors = app(SubmissionIntake::class)->conflicts($this->phone, $this->license_plate, true,
                        $this->getConnection(), $this->getKey(), [$this->phone_normalized ?? '']);
                    if ($errors) {
                        throw ValidationException::withMessages($errors);
                    }
                }
                $oldPhones = array_map(RegistrationPhone::normalize(...), [$current->phone ?? '', $current->phone_normalized ?? '']);
                $errors = [];
                foreach (['phone', 'phone_normalized'] as $field) {
                    $phone = RegistrationPhone::normalize($this->$field ?? '');
                    if ($this->isDirty($field) && $phone !== '' && ! in_array($phone, $oldPhones, true)) {
                        $errors += app(SubmissionIntake::class)->conflicts($phone, null, true, $this->getConnection(), $this->getKey());
                    }
                }
                if ($this->isDirty('license_plate') && SubmissionIntake::plate($this->license_plate) !== SubmissionIntake::plate($current->license_plate)) {
                    $errors += app(SubmissionIntake::class)->conflicts(null, $this->license_plate, true, $this->getConnection(), $this->getKey());
                }
                if ($errors) {
                    throw ValidationException::withMessages($errors);
                }
            }

            return parent::performUpdate($query);
        }, $this->getConnection());
    }

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

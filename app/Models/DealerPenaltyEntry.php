<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class DealerPenaltyEntry extends Model
{
    public const POLICY = 'dealer_minus_one_floor_zero_v1';

    public const PROJECTION_POLICY = 'dealer_display_floor_zero_v1';

    public const RANKING_POLICY = 'display_desc_achieved_asc_id_asc_v1';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $hidden = ['policy_snapshot_encrypted', 'dealer_attribution_encrypted', 'source_fingerprint',
        'encryption_key_id', 'encryption_version'];

    protected $casts = [
        'points' => 'integer', 'period_start' => 'immutable_datetime', 'period_end' => 'immutable_datetime',
        'source_submitted_at' => 'immutable_datetime', 'attribution_observed_at' => 'immutable_datetime',
        'applied_at' => 'immutable_datetime', 'effective_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime',
        'policy_snapshot_encrypted' => 'encrypted:array', 'dealer_attribution_encrypted' => 'encrypted:array',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Dealer penalty entries are immutable.');
        });
        static::deleting(function () {
            throw new LogicException('Dealer penalty entries are immutable.');
        });
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(CustomerSubmission::class);
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(DealerPenaltyEvidence::class, 'entry_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DealerPenaltyEvent::class, 'entry_id');
    }
}

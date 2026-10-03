<?php

namespace App\Models;

use App\Enums\AwardWinnerType;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use LogicException;

class AwardWinner extends Model
{
    use HasFactory;

    // Snapshot fields are assigned explicitly by the future publication action.
    protected $guarded = ['*'];

    protected $casts = [
        'winner_type' => AwardWinnerType::class,
        'rank' => 'integer',
        'score' => 'integer',
        'sales_dealer_id_snapshot' => 'integer',
    ];

    protected static function booted(): void
    {
        $ensureDraft = function (self $winner) {
            $ids = array_filter([$winner->award_id, $winner->getRawOriginal('award_id')]);
            if (Award::whereIn('id', $ids)->whereNotNull('published_at')->exists()) {
                throw new LogicException('Winners of published awards are immutable.');
            }
        };

        static::saving($ensureDraft);
        static::deleting($ensureDraft);
        static::saving(function (self $winner) {
            if ($winner->winner_type === AwardWinnerType::Sales
                && ! User::whereKey($winner->sales_id)->where('role', UserRole::Sales->value)->exists()) {
                throw new InvalidArgumentException('A sales award winner must be a sales account.');
            }
        });
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(Award::class);
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }
}

<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Dealer extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'province', 'phone', 'address', 'is_active', 'website', 'facebook_url', 'zalo_url', 'opening_hours'];

    protected $casts = ['is_active' => 'boolean', 'toyota_source_id' => 'integer'];

    protected $attributes = ['is_active' => true];

    protected static function booted(): void
    {
        static::deleting(function (self $dealer) {
            if (User::where('dealer_id', $dealer->id)->exists() || $dealer->submissions()->exists() || $dealer->awardWins()->exists()
                || AwardWinner::where('sales_dealer_id_snapshot', $dealer->id)->exists()) {
                throw new LogicException('Dealer has accounts or award history; deactivate it instead.');
            }
        });
    }

    public function sales(): HasMany
    {
        return $this->hasMany(User::class)->where('role', UserRole::Sales->value);
    }

    public function awardWins(): HasMany
    {
        return $this->hasMany(AwardWinner::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(CustomerSubmission::class);
    }
}

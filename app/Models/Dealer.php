<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dealer extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name'];

    protected $casts = ['is_active' => 'boolean'];

    protected $attributes = ['is_active' => true];

    public function sales(): HasMany
    {
        return $this->hasMany(User::class)->where('role', UserRole::Sales->value);
    }

    public function awardWins(): HasMany
    {
        return $this->hasMany(AwardWinner::class);
    }
}

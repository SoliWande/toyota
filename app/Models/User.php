<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use InvalidArgumentException;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'role' => UserRole::class,
        'status' => UserStatus::class,
        'reviewed_at' => 'immutable_datetime',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected $attributes = [
        'role' => 'sales',
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $user) {
            if ($user->reviewed_by !== null
                && ! self::whereKey($user->reviewed_by)->where('role', UserRole::Admin->value)->exists()) {
                throw new InvalidArgumentException('An account reviewer must be an admin.');
            }
        });
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reviewed_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(CustomerSubmission::class, 'sales_id');
    }

    public function reviewedSubmissions(): HasMany
    {
        return $this->hasMany(CustomerSubmission::class, 'reviewed_by');
    }

    public function reviewedSales(): HasMany
    {
        return $this->hasMany(self::class, 'reviewed_by');
    }

    public function moderationReviews(): HasMany
    {
        return $this->hasMany(ModerationReview::class, 'sales_id');
    }

    public function performedReviews(): HasMany
    {
        return $this->hasMany(ModerationReview::class, 'reviewed_by');
    }

    public function publishedAwards(): HasMany
    {
        return $this->hasMany(Award::class, 'published_by');
    }

    public function awardWins(): HasMany
    {
        return $this->hasMany(AwardWinner::class, 'sales_id');
    }
}

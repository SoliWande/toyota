<?php

namespace App\Services;

use App\Models\Dealer;
use Illuminate\Support\Str;

class DealerSlugs
{
    public const RESERVED = ['admin', 'dealer', 'dealers', 'sales', 'login', 'logout', 'register', 'profile', 'account',
        'api', 'sanctum', 'storage', 'build', 'up', 'health', 'leaderboard', 'awards', 'submissions',
        'forgot-password', 'reset-password', 'password', 'favicon', 'robots', '_ignition'];

    public function generate(string $name): string
    {
        $base = substr(Str::slug($name), 0, 180) ?: 'dai-ly';
        $slug = $base;
        for ($suffix = 2; in_array($slug, self::RESERVED, true) || Dealer::where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}

<?php

namespace App\Services\CallAudit;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class FreezeClock
{
    public function now(): CarbonImmutable
    {
        $value = DB::selectOne('SELECT UTC_TIMESTAMP(6) AS instant')->instant;

        return CarbonImmutable::parse($value, 'UTC')->setTimezone(config('app.timezone'));
    }
}

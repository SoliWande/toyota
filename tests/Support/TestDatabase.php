<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use LogicException;

final class TestDatabase
{
    public static function assertIsolated(): void
    {
        $expected = getenv('TOYOTA_TEST_DATABASE') ?: 'toyota_testing';

        if (! preg_match('/^toyota_testing(?:_upgrade_[0-9]{8,14})?$/D', $expected)
            || ! app()->environment('testing')
            || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== $expected) {
            throw new LogicException('Tests require an explicitly isolated Toyota testing database.');
        }
    }
}

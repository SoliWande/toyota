<?php

namespace Tests\Feature;

use App\Services\SubmissionIntake;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

class SubmissionIntakeRetryTest extends TestCase
{
    public function test_outer_mutex_transaction_retries_a_retryable_deadlock(): void
    {
        $this->assertSame('toyota_testing', DB::selectOne('SELECT DATABASE() AS n')->n);
        $attempts = 0;
        $result = app(SubmissionIntake::class)->locked(function () use (&$attempts) {
            if (++$attempts < 3) {
                throw new QueryException('mysql', 'synthetic statement', [], new PDOException('Deadlock found when trying to get lock; try restarting transaction', 40001));
            }

            return 'completed';
        });
        $this->assertSame(3, $attempts);
        $this->assertSame('completed', $result);
        $this->assertSame(0, DB::transactionLevel());
    }
}

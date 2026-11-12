<?php

namespace Tests\Unit;

use App\Services\CallAudit\SimulationRanking;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CallAuditSimulationRankingTest extends TestCase
{
    public function test_signed_negative_and_zero_scores_are_retained_without_creating_a_live_rank(): void
    {
        $result = (new SimulationRanking)->compare([
            ['id' => 2, 'score' => 1, 'achieved_at' => '2026-10-02 12:00:00'],
        ], [2 => 1, 9 => 2]);
        $this->assertSame(['id' => 2, 'baseline_present' => true, 'before_score' => 1, 'before_rank' => 1,
            'deduction' => 1, 'after_score' => 0, 'after_rank' => 1], $result['rows'][0]);
        $this->assertSame(['id' => 9, 'baseline_present' => false, 'before_score' => 0, 'before_rank' => null,
            'deduction' => 2, 'after_score' => -2, 'after_rank' => 2], $result['rows'][1]);
        $this->assertSame([2], array_column($result['top3_before'], 'id'));
        $this->assertSame([2, 9], array_column($result['top3_after'], 'id'));
    }

    public function test_same_score_keeps_timestamp_then_id_tiebreak_and_sequential_ranks(): void
    {
        $result = (new SimulationRanking)->compare([
            ['id' => 7, 'score' => 3, 'achieved_at' => '2026-10-02 12:00:00'],
            ['id' => 5, 'score' => 3, 'achieved_at' => '2026-10-02 12:00:00'],
            ['id' => 8, 'score' => 3, 'achieved_at' => '2026-10-01 12:00:00'],
            ['id' => 9, 'score' => 2, 'achieved_at' => null],
        ], [8 => 1]);
        $this->assertSame([8, 5, 7], array_column($result['top3_before'], 'id'));
        $this->assertSame([5, 7, 9], array_column($result['top3_after'], 'id'));
        $this->assertSame([1, 2, 3, 4], array_column($result['rows'], 'after_rank'));
        $this->assertSame([9], $result['top3_changes']['entered_ids']);
        $this->assertSame([8], $result['top3_changes']['exited_ids']);
        $this->assertSame([5, 7], $result['top3_changes']['reordered_ids']);
    }

    public function test_baseline_does_not_change_without_deductions_and_inputs_are_not_mutated(): void
    {
        $baseline = [['id' => 3, 'score' => 4, 'achieved_at' => '2026-10-03', 'dealer_id' => 11]];
        $copy = $baseline;
        $result = (new SimulationRanking)->compare($baseline, []);
        $this->assertSame($result['top3_before'], $result['top3_after']);
        $this->assertSame($copy, $baseline);
        $this->assertSame(11, $result['rows'][0]['dealer_id']);
        $this->assertSame(['entered_ids' => [], 'exited_ids' => [], 'reordered_ids' => []], $result['top3_changes']);
        $this->assertSame([], (new SimulationRanking)->compare([], [])['rows']);
    }

    #[DataProvider('invalid')]
    public function test_invalid_baseline_or_deduction_does_not_produce_a_ranking(array $baseline, array $deductions): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SimulationRanking)->compare($baseline, $deductions);
    }

    public static function invalid(): array
    {
        $valid = ['id' => 1, 'score' => 2, 'achieved_at' => null];

        return [[[$valid, $valid], []], [[['id' => 0] + $valid], []], [[array_replace($valid, ['score' => -1])], []],
            [[array_replace($valid, ['score' => '2'])], []], [[['id' => 1, 'score' => 2]], []],
            [[$valid], [1 => 0]], [[$valid], [1 => -1]], [[$valid], [1 => '1']], [[$valid], [0 => 1]]];
    }
}

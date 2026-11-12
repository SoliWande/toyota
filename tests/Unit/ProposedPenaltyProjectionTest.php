<?php

namespace Tests\Unit;

use App\Services\CallAudit\ProposedPenaltyLedger;
use App\Services\CallAudit\ProposedPenaltyProjection;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProposedPenaltyProjectionTest extends TestCase
{
    public function test_raw_penalty_net_and_floor_are_distinct_and_excess_is_not_lost(): void
    {
        $entries = [$this->entry(1), $this->entry(2), $this->entry(3)];
        $result = (new ProposedPenaltyProjection)->preview('2026-10', [['id' => 7, 'score' => 1]], $entries);
        $this->assertSame(['id' => 7, 'raw_score' => 1, 'baseline_present' => true, 'penalty_points' => 3,
            'net_score' => -2, 'proposed_display_score' => 0], $result['dealer_scores'][0]);
        $this->assertSame('PROPOSED_V1', $result['approval']);
        $this->assertFalse($result['executable']);
        $this->assertSame('BLOCKED_PROPOSED_POLICY', $result['ranking_status']);
        $this->assertArrayNotHasKey('top3', $result);
    }

    public function test_duplicate_evidence_or_repeated_plan_does_not_increase_penalty(): void
    {
        $entry = $this->entry(1);
        $copy = $entry;
        $copy['recorded_entry_id'] = 90;
        $result = (new ProposedPenaltyProjection)->preview('2026-10', [['id' => 7, 'score' => 4]], [$entry, $copy, $entry]);
        $this->assertSame([4, 1, 3, 3], array_values(array_intersect_key($result['dealer_scores'][0],
            array_flip(['raw_score', 'penalty_points', 'net_score', 'proposed_display_score']))));
    }

    public function test_historic_dealer_without_raw_score_has_no_invented_rank(): void
    {
        $result = (new ProposedPenaltyProjection)->preview('2026-10', [['id' => 8, 'score' => 3]], [$this->entry(1)]);
        $this->assertSame(['id' => 7, 'raw_score' => 0, 'baseline_present' => false, 'penalty_points' => 1,
            'net_score' => -1, 'proposed_display_score' => 0], $result['dealer_scores'][0]);
        $this->assertSame(3, $result['dealer_scores'][1]['raw_score']);
        $this->assertSame(0, $result['dealer_scores'][1]['penalty_points']);
        $this->assertArrayNotHasKey('rank', $result['dealer_scores'][0]);
    }

    public function test_floor_ties_are_not_ranked_or_used_to_decide_top3(): void
    {
        $result = (new ProposedPenaltyProjection)->preview('2026-10', [['id' => 7, 'score' => 0], ['id' => 8, 'score' => 1]],
            [$this->entry(1), array_replace($this->entry(2), ['dealer_id' => 8])]);
        $this->assertSame([0, 0], array_column($result['dealer_scores'], 'proposed_display_score'));
        $this->assertSame([-1, 0], array_column($result['dealer_scores'], 'net_score'));
        $this->assertSame('BLOCKED_PROPOSED_POLICY', $result['ranking_status']);
    }

    public function test_sales_is_unchanged_and_published_is_preserved_while_draft_is_not_recalculated(): void
    {
        $sales = [['id' => 19, 'score' => 4, 'rank' => 1, 'name' => 'PRIVATE-SALES']];
        $record = ['winner_type' => 'dealer', 'dealer_id' => 7, 'rank' => 1, 'score' => 8, 'winner_name_snapshot' => 'PRIVATE-SNAPSHOT'];
        $awards = [['id' => 20, 'status' => 'draft', 'records' => [$record]], ['id' => 21, 'status' => 'published', 'records' => [$record]]];
        $before = [$sales, $awards];
        $result = (new ProposedPenaltyProjection)->preview('2026-10', [['id' => 7, 'score' => 8]], [$this->entry(1)], $sales, $awards);
        $this->assertSame([['id' => 19, 'score' => 4, 'rank' => 1]], $result['sales_unchanged']);
        $this->assertSame('BLOCKED_PROPOSED_POLICY', $result['award_references'][0]['effective_results_status']);
        $this->assertSame('PUBLISHED_SNAPSHOT_UNCHANGED', $result['award_references'][1]['effective_results_status']);
        $this->assertSame([8, 8], array_map(fn ($award) => $award['reference_records'][0]['score'], $result['award_references']));
        $this->assertSame(7, $result['dealer_scores'][0]['proposed_display_score']);
        $this->assertSame($before, [$sales, $awards]);
        $this->assertStringNotContainsString('PRIVATE', json_encode($result));
    }

    #[DataProvider('invalidEntries')]
    public function test_invalid_scope_subject_amount_policy_or_conflicting_dealer_is_rejected(string $case): void
    {
        $entry = $this->entry(1);
        $other = $entry;
        switch ($case) {
            case 'month': $other['monthly_scope_key'] = '2026-09';
                break;
            case 'dealer': $other['dealer_id'] = 8;
                break;
            case 'sales': $other['subject_type'] = 'sales';
                break;
            case 'points': $other['points'] = 2;
                break;
            case 'policy': $other['policy_version'] = 'approved';
                break;
        }
        $this->expectException(InvalidArgumentException::class);
        (new ProposedPenaltyProjection)->preview('2026-10', [['id' => 7, 'score' => 8]], [$entry, $other]);
    }

    public static function invalidEntries(): array
    {
        return [['month'], ['dealer'], ['sales'], ['points'], ['policy']];
    }

    public function test_empty_is_not_an_executable_plan(): void
    {
        $result = (new ProposedPenaltyProjection)->preview('2026-10', [], []);
        $this->assertSame([], $result['dealer_scores']);
        $this->assertSame([], $result['sales_unchanged']);
        $this->assertSame('PREPARATION_ONLY', $result['mode']);
        $this->assertFalse($result['executable']);
    }

    private function entry(int $submission): array
    {
        return ['monthly_scope_key' => '2026-10', 'submission_id' => $submission, 'dealer_id' => 7,
            'points' => 1, 'subject_type' => 'dealer', 'policy_version' => ProposedPenaltyLedger::POLICY];
    }
}

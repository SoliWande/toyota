<?php

namespace Tests\Unit;

use App\Services\CallAudit\ProposedPenaltyLedger;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProposedPenaltyLedgerTest extends TestCase
{
    public function test_same_submission_overlapping_batches_proposes_only_one_point_and_keeps_both_evidences(): void
    {
        $first = $this->finding();
        $other = $this->finding(['evidence' => array_replace($first['evidence'], [
            'batch_id' => 12, 'item_id' => 22, 'attempt_id' => 32, 'call_event_id' => 42, 'resolution_event_id' => 52, 'purpose' => 'award_top3',
        ])]);
        $findings = [$first, $first, $other];
        $copy = $findings;
        $plan = $this->prepare($findings);
        $this->assertSame('PREPARATION_ONLY', $plan['mode']);
        $this->assertSame('PROPOSED_V1', $plan['approval']);
        $this->assertFalse($plan['executable']);
        $this->assertSame('STRUCTURAL_ONLY_REQUIRES_SERVER_E1_E2_VERIFICATION', $plan['evidence_validation']);
        $this->assertSame(1, $plan['new_entry_count']);
        $this->assertCount(1, $plan['entries']);
        $this->assertCount(2, $plan['entries'][0]['evidence']);
        $this->assertSame(1, $plan['entries'][0]['points']);
        $this->assertSame('dealer', $plan['entries'][0]['subject_type']);
        $this->assertSame($copy, $findings);
    }

    public function test_multiple_submissions_same_dealer_remain_distinct_business_keys(): void
    {
        $a = $this->finding();
        $b = $this->finding(['submission_id' => 2, 'evidence' => array_replace($a['evidence'], [
            'item_id' => 22, 'attempt_id' => 32, 'call_event_id' => 42, 'resolution_event_id' => 52,
        ])]);
        $plan = $this->prepare([$a, $b]);
        $this->assertSame(2, $plan['new_entry_count']);
        $this->assertSame([1, 2], array_column($plan['entries'], 'submission_id'));
        $this->assertSame([7, 7], array_column($plan['entries'], 'dealer_id'));
    }

    public function test_non_confirmed_decisions_never_prepare_penalty(): void
    {
        foreach (['dismissed', 'needs_review'] as $decision) {
            $plan = $this->prepare([$this->finding(['decision' => $decision])]);
            $this->assertSame([], $plan['entries']);
            $this->assertSame(0, $plan['new_entry_count']);
        }
    }

    public function test_recorded_reference_is_not_charged_again_and_retry_fingerprint_survives_recording(): void
    {
        $finding = $this->finding();
        $first = $this->prepare([$finding]);
        $recorded = $this->recorded();
        $retry = $this->prepare([$finding], [$recorded]);
        $this->assertSame($first['request_fingerprint'], $retry['request_fingerprint']);
        $this->assertSame(0, $retry['new_entry_count']);
        $this->assertSame(1, $retry['recorded_entry_count']);
        $this->assertFalse($retry['entries'][0]['would_create']);
        $this->assertSame(91, $retry['entries'][0]['recorded_entry_id']);
        $this->assertSame(1, $retry['entries'][0]['points']);
        $this->assertCount(1, $retry['entries'][0]['evidence']);
        $this->assertSame(1, $this->prepare([], [$recorded])['recorded_entry_count']);
    }

    public function test_intent_is_canonical_but_changes_with_payload_actor_request_or_month(): void
    {
        $a = $this->finding();
        $b = $this->finding(['submission_id' => 2, 'evidence' => array_replace($a['evidence'], ['item_id' => 22, 'resolution_event_id' => 52])]);
        $base = $this->prepare([$a, $b])['request_fingerprint'];
        $this->assertSame($base, $this->prepare([$b, $a, $a], [], ['prepared_at' => '2026-11-13T12:00:00+00:00'])['request_fingerprint']);
        foreach ([['actor_id' => 99], ['idempotency_key' => 'another']] as $audit) {
            $this->assertNotSame($base, $this->prepare([$a, $b], [], $audit)['request_fingerprint']);
        }
        $this->assertNotSame($base, $this->prepare([$a])['request_fingerprint']);
        $this->assertNotSame($base, $this->prepare([$this->finding(['dealer_id' => 8])])['request_fingerprint']);
        $this->assertNotSame($this->prepare([$a])['request_fingerprint'],
            (new ProposedPenaltyLedger)->prepare('2026-09', [$this->finding(['monthly_scope_key' => '2026-09'])], [], $this->audit())['request_fingerprint']);
    }

    public function test_audit_draft_has_actor_time_and_only_allowlisted_evidence(): void
    {
        $finding = $this->finding(['phone' => 'PRIVATE-PHONE', 'reason' => 'PRIVATE-REASON']);
        $finding['evidence']['seed'] = 'PRIVATE-SEED';
        $plan = $this->prepare([$finding], [], ['reason' => 'PRIVATE-AUDIT-REASON']);
        $this->assertSame(['prepared_by' => 90, 'prepared_at' => '2026-11-12T12:00:00+00:00', 'idempotency_key' => 'request-1'], $plan['audit_draft']);
        $this->assertSame(80, $plan['entries'][0]['evidence'][0]['confirmed_by']);
        foreach (['PRIVATE', 'phone', 'reason', 'seed', 'manifest', 'sales_id'] as $private) {
            $this->assertStringNotContainsString($private, json_encode($plan));
        }
    }

    public function test_supplied_approval_flags_never_turn_preparation_into_execution(): void
    {
        $plan = $this->prepare([$this->finding(['approval' => 'USER_APPROVED', 'executable' => true])], [], ['executable' => true]);
        $this->assertFalse($plan['executable']);
        $this->assertSame('PROPOSED_V1', $plan['approval']);
        $this->assertSame([], $this->prepare([])['entries']);
    }

    public function test_invalid_month_is_rejected_even_for_empty_preparation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ProposedPenaltyLedger)->prepare('2026-13', [], [], $this->audit());
    }

    #[DataProvider('invalidFindings')]
    public function test_invalid_or_conflicting_findings_cannot_be_prepared(string $case): void
    {
        $a = $this->finding();
        $b = $a;
        switch ($case) {
            case 'dealer': $b['dealer_id'] = 8;
                break;
            case 'same-event-other-source': $b['submission_id'] = 2;
                break;
            case 'same-event-other-attempt': $b['evidence']['attempt_id'] = 99;
                break;
            case 'same-item-other-version': $b['evidence']['resolution_event_id'] = 52;
                $b['evidence']['resolution_version'] = 2;
                break;
            case 'month': $b['monthly_scope_key'] = '2026-09';
                break;
            case 'unknown-decision': $b['decision'] = 'apply';
                break;
            case 'missing-event': unset($b['evidence']['resolution_event_id']);
                break;
            case 'invalid-id': $b['submission_id'] = '2';
                break;
            case 'unknown-purpose': $b['evidence']['purpose'] = 'manager';
                break;
            case 'invalid-version': $b['evidence']['resolution_version'] = 3;
                break;
            case 'invalid-time': $b['evidence']['confirmed_at'] = '2026-02-31T12:00:00+00:00';
                break;
        }
        $this->expectException(InvalidArgumentException::class);
        $this->prepare([$a, $b]);
    }

    public static function invalidFindings(): array
    {
        return array_map(fn ($case) => [$case], ['dealer', 'same-event-other-source', 'same-event-other-attempt', 'same-item-other-version',
            'month', 'unknown-decision', 'missing-event', 'invalid-id', 'unknown-purpose', 'invalid-version', 'invalid-time']);
    }

    #[DataProvider('invalidRecorded')]
    public function test_recorded_business_key_never_allows_a_second_penalty(array $overrides, bool $duplicate): void
    {
        $recorded = array_replace($this->recorded(), $overrides);
        $this->expectException(InvalidArgumentException::class);
        $this->prepare([$this->finding()], $duplicate ? [$recorded, $recorded] : [$recorded]);
    }

    public static function invalidRecorded(): array
    {
        return [[['dealer_id' => 8], false], [['policy_version' => 'another'], false], [['points' => 2], false],
            [['monthly_scope_key' => '2026-09'], false], [[], true]];
    }

    #[DataProvider('invalidAudit')]
    public function test_invalid_audit_identity_time_or_key_is_rejected(array $audit): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->prepare([$this->finding()], [], $audit);
    }

    public static function invalidAudit(): array
    {
        return [[['actor_id' => 0]], [['prepared_at' => 'tomorrow']], [['prepared_at' => '2026-11-09T12:00:00+00:00']], [['idempotency_key' => 'bad key']]];
    }

    private function prepare(array $findings, array $recorded = [], array $audit = []): array
    {
        return (new ProposedPenaltyLedger)->prepare('2026-10', $findings, $recorded, array_replace($this->audit(), $audit));
    }

    private function audit(): array
    {
        return ['actor_id' => 90, 'prepared_at' => '2026-11-12T12:00:00+00:00', 'idempotency_key' => 'request-1'];
    }

    private function recorded(): array
    {
        return ['entry_id' => 91, 'monthly_scope_key' => '2026-10', 'submission_id' => 1, 'dealer_id' => 7,
            'points' => 1, 'policy_version' => ProposedPenaltyLedger::POLICY];
    }

    private function finding(array $overrides = []): array
    {
        return array_replace(['monthly_scope_key' => '2026-10', 'submission_id' => 1, 'dealer_id' => 7, 'decision' => 'confirmed',
            'evidence' => ['batch_id' => 11, 'item_id' => 21, 'attempt_id' => 31, 'call_event_id' => 41, 'resolution_event_id' => 51,
                'resolution_version' => 1, 'purpose' => 'general_audit', 'confirmed_by' => 80, 'confirmed_at' => '2026-11-10T12:00:00+00:00']], $overrides);
    }
}

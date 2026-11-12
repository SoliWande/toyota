<?php

namespace App\Services\CallAudit;

use InvalidArgumentException;

/** Proposed display arithmetic, deliberately no effective ranking or award calculation. */
final class ProposedPenaltyProjection
{
    public function preview(string $month, array $dealerBaseline, array $entries, array $salesBaseline = [], array $awards = []): array
    {
        $ledger = new ProposedPenaltyLedger;
        $ledger->assertMonth($month);
        $penalties = [];
        $seen = [];
        foreach ($entries as $entry) {
            $key = $ledger->key($month, $entry);
            if (! is_int($entry['dealer_id'] ?? null) || $entry['dealer_id'] < 1 || ($entry['points'] ?? null) !== 1
                || ($entry['subject_type'] ?? null) !== 'dealer' || ($entry['policy_version'] ?? null) !== ProposedPenaltyLedger::POLICY) {
                throw new InvalidArgumentException('Invalid proposed dealer entry.');
            }
            if (isset($seen[$key])) {
                if ($seen[$key] !== $entry['dealer_id']) {
                    throw new InvalidArgumentException('Conflicting proposed dealer entry.');
                }

                continue;
            }
            $seen[$key] = $entry['dealer_id'];
            $penalties[$entry['dealer_id']] = ($penalties[$entry['dealer_id']] ?? 0) + 1;
        }
        $rows = [];
        foreach ($dealerBaseline as $row) {
            $this->score($row);
            if (isset($rows[$row['id']])) {
                throw new InvalidArgumentException('Duplicate dealer baseline.');
            }
            $rows[$row['id']] = ['id' => $row['id'], 'raw_score' => $row['score'], 'baseline_present' => true];
        }
        foreach ($penalties as $dealer => $points) {
            $rows[$dealer] ??= ['id' => $dealer, 'raw_score' => 0, 'baseline_present' => false];
        }
        ksort($rows, SORT_NUMERIC);
        foreach ($rows as &$row) {
            $row['penalty_points'] = $penalties[$row['id']] ?? 0;
            $row['net_score'] = $row['raw_score'] - $row['penalty_points'];
            $row['proposed_display_score'] = max(0, $row['net_score']);
        }
        unset($row);
        $sales = [];
        foreach ($salesBaseline as $row) {
            $this->score($row);
            if (! is_int($row['rank'] ?? null) || $row['rank'] < 1) {
                throw new InvalidArgumentException('Invalid unchanged Sales ranking.');
            }
            $sales[] = ['id' => $row['id'], 'score' => $row['score'], 'rank' => $row['rank']];
        }
        $references = [];
        foreach ($awards as $award) {
            if (! is_int($award['id'] ?? null) || $award['id'] < 1 || ! in_array($award['status'] ?? null, ['draft', 'published'], true)) {
                throw new InvalidArgumentException('Invalid award reference.');
            }
            $records = [];
            foreach ($award['records'] ?? [] as $record) {
                if (! in_array($record['winner_type'] ?? null, ['dealer', 'sales'], true)
                    || ! is_int($record['score'] ?? null) || $record['score'] < 0
                    || ! is_int($record['rank'] ?? null) || $record['rank'] < 1 || $record['rank'] > 3) {
                    throw new InvalidArgumentException('Invalid award reference record.');
                }
                $field = $record['winner_type'] === 'dealer' ? 'dealer_id' : 'sales_id';
                if (! is_int($record[$field] ?? null) || $record[$field] < 1) {
                    throw new InvalidArgumentException('Invalid award subject.');
                }
                $records[] = ['winner_type' => $record['winner_type'], $field => $record[$field], 'rank' => $record['rank'], 'score' => $record['score']];
            }
            $references[] = ['id' => $award['id'], 'status' => $award['status'], 'reference_records' => $records,
                'effective_results_status' => $award['status'] === 'published' ? 'PUBLISHED_SNAPSHOT_UNCHANGED' : 'BLOCKED_PROPOSED_POLICY'];
        }

        return ['mode' => 'PREPARATION_ONLY', 'approval' => 'PROPOSED_V1', 'executable' => false,
            'monthly_scope_key' => $month, 'dealer_scores' => array_values($rows), 'sales_unchanged' => $sales,
            'ranking_status' => 'BLOCKED_PROPOSED_POLICY', 'award_references' => $references];
    }

    private function score(array $row): void
    {
        if (! is_int($row['id'] ?? null) || $row['id'] < 1 || ! is_int($row['score'] ?? null) || $row['score'] < 0) {
            throw new InvalidArgumentException('Invalid raw score.');
        }
    }
}

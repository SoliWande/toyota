<?php

namespace App\Services\CallAudit;

use InvalidArgumentException;

/** Pure signed arithmetic; never remove sources, mutate scores or decide award eligibility. */
final class SimulationRanking
{
    public function compare(array $baseline, array $deductions): array
    {
        $before = [];
        foreach ($baseline as $row) {
            if (! is_int($row['id'] ?? null) || $row['id'] < 1 || isset($before[$row['id']])
                || ! is_int($row['score'] ?? null) || $row['score'] < 0
                || ! array_key_exists('achieved_at', $row) || ($row['achieved_at'] !== null && ! is_string($row['achieved_at']))) {
                throw new InvalidArgumentException('Invalid simulation baseline.');
            }
            $before[$row['id']] = $row;
        }
        $before = $this->rank(array_values($before));
        $beforeById = array_column($before, null, 'id');
        $afterById = $beforeById;
        foreach ($deductions as $id => $count) {
            if (! is_int($id) || $id < 1 || ! is_int($count) || $count < 1) {
                throw new InvalidArgumentException('Invalid simulation deduction.');
            }
            $afterById[$id] ??= ['id' => $id, 'score' => 0, 'achieved_at' => null];
            $afterById[$id]['score'] -= $count;
        }
        $after = $this->rank(array_values($afterById));
        $rows = [];
        foreach ($after as $row) {
            $old = $beforeById[$row['id']] ?? null;
            $rows[] = ['id' => $row['id'], 'baseline_present' => $old !== null,
                'before_score' => $old['score'] ?? 0, 'before_rank' => $old['rank'] ?? null,
                'deduction' => $deductions[$row['id']] ?? 0, 'after_score' => $row['score'], 'after_rank' => $row['rank']]
                + (array_key_exists('dealer_id', $row) ? ['dealer_id' => $row['dealer_id']] : []);
        }
        $beforeTop = $this->top($before);
        $afterTop = $this->top($after);
        $beforeIds = array_column($beforeTop, 'id');
        $afterIds = array_column($afterTop, 'id');

        return ['rows' => $rows, 'top3_before' => $beforeTop, 'top3_after' => $afterTop,
            'top3_changes' => ['entered_ids' => array_values(array_diff($afterIds, $beforeIds)),
                'exited_ids' => array_values(array_diff($beforeIds, $afterIds)),
                'reordered_ids' => array_values(array_column(array_filter($rows,
                    fn ($row) => in_array($row['id'], $beforeIds, true) && in_array($row['id'], $afterIds, true)
                        && $row['before_rank'] !== $row['after_rank']), 'id'))]];
    }

    private function rank(array $rows): array
    {
        // MySQL ascending NULLs sort first; keep the live timestamp unchanged after deduction.
        usort($rows, fn ($a, $b) => ($b['score'] <=> $a['score'])
            ?: (($a['achieved_at'] === null || $b['achieved_at'] === null)
                ? (($a['achieved_at'] !== null) <=> ($b['achieved_at'] !== null))
                : strcmp($a['achieved_at'], $b['achieved_at'])) ?: ($a['id'] <=> $b['id']));
        foreach ($rows as $index => &$row) {
            $row['rank'] = $index + 1;
        }
        unset($row);

        return $rows;
    }

    private function top(array $rows): array
    {
        return array_map(fn ($row) => ['id' => $row['id'], 'score' => $row['score'], 'rank' => $row['rank']]
            + (array_key_exists('dealer_id', $row) ? ['dealer_id' => $row['dealer_id']] : []), array_slice($rows, 0, 3));
    }
}

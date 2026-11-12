<?php

namespace App\Services\CallAudit;

use DateTimeImmutable;
use InvalidArgumentException;

/** Structural preparation only. No authorization, evidence signature verification or persistence. */
final class ProposedPenaltyLedger
{
    public const POLICY = 'dealer_minus_one_floor_zero_proposed_v1';

    public function prepare(string $month, array $findings, array $recorded, array $audit): array
    {
        $this->assertMonth($month);
        $this->positive($audit['actor_id'] ?? null);
        $this->instant($audit['prepared_at'] ?? null);
        if (! is_string($audit['idempotency_key'] ?? null) || ! preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $audit['idempotency_key'])) {
            throw new InvalidArgumentException('Invalid preparation request.');
        }
        $entries = [];
        foreach ($recorded as $row) {
            $key = $this->key($month, $row);
            $this->positive($row['entry_id'] ?? null);
            $this->positive($row['dealer_id'] ?? null);
            if (isset($entries[$key]) || ($row['points'] ?? null) !== 1 || ($row['policy_version'] ?? null) !== self::POLICY) {
                throw new InvalidArgumentException('Recorded ledger conflict requires review.');
            }
            $entries[$key] = $this->entry($month, $row['submission_id'], $row['dealer_id'], $row['entry_id']);
        }
        $requested = [];
        $eventOwners = [];
        $itemVersions = [];
        foreach ($findings as $finding) {
            $key = $this->key($month, $finding);
            if (! in_array($finding['decision'] ?? null, ['confirmed', 'dismissed', 'needs_review'], true)) {
                throw new InvalidArgumentException('Invalid finding decision.');
            }
            if ($finding['decision'] !== 'confirmed') {
                continue;
            }
            $this->positive($finding['dealer_id'] ?? null);
            $evidence = $this->evidence($finding['evidence'] ?? []);
            if (new DateTimeImmutable($evidence['confirmed_at']) > new DateTimeImmutable($audit['prepared_at'])) {
                throw new InvalidArgumentException('Preparation predates confirmation.');
            }
            $entry = $entries[$key] ?? $this->entry($month, $finding['submission_id'], $finding['dealer_id'], null);
            if ($entry['dealer_id'] !== $finding['dealer_id']) {
                throw new InvalidArgumentException('Frozen dealer attribution conflict.');
            }
            $digest = (new ManifestCodec)->digest(['key' => $key, 'dealer_id' => $finding['dealer_id'], 'evidence' => $evidence]);
            $eventId = $evidence['resolution_event_id'];
            $itemKey = $evidence['batch_id'].':'.$evidence['item_id'];
            if ((isset($eventOwners[$eventId]) && $eventOwners[$eventId] !== $digest)
                || (isset($itemVersions[$itemKey]) && $itemVersions[$itemKey] !== $digest)) {
                throw new InvalidArgumentException('Conflicting evidence identity.');
            }
            $eventOwners[$eventId] = $itemVersions[$itemKey] = $digest;
            $entry['evidence'][$eventId] = $evidence;
            $entries[$key] = $entry;
            $requested[$key] = ['submission_id' => $finding['submission_id'], 'dealer_id' => $finding['dealer_id'],
                'evidence' => ($requested[$key]['evidence'] ?? []) + [$eventId => $evidence]];
        }
        ksort($entries, SORT_STRING);
        ksort($requested, SORT_STRING);
        foreach ($entries as &$entry) {
            ksort($entry['evidence'], SORT_NUMERIC);
            $entry['evidence'] = array_values($entry['evidence']);
        }
        unset($entry);
        foreach ($requested as &$row) {
            ksort($row['evidence'], SORT_NUMERIC);
            $row['evidence'] = array_values($row['evidence']);
        }
        unset($row);
        $newCount = count(array_filter($entries, fn ($entry) => $entry['recorded_entry_id'] === null));
        $fingerprint = (new ManifestCodec)->digest(['policy' => self::POLICY, 'month' => $month,
            'actor_id' => $audit['actor_id'], 'idempotency_key' => $audit['idempotency_key'], 'findings' => array_values($requested)]);

        return ['mode' => 'PREPARATION_ONLY', 'approval' => 'PROPOSED_V1', 'executable' => false,
            'evidence_validation' => 'STRUCTURAL_ONLY_REQUIRES_SERVER_E1_E2_VERIFICATION', 'policy_version' => self::POLICY,
            'monthly_scope_key' => $month, 'entries' => array_values($entries),
            'new_entry_count' => $newCount, 'recorded_entry_count' => count($entries) - $newCount,
            'request_fingerprint' => $fingerprint,
            'audit_draft' => ['prepared_by' => $audit['actor_id'], 'prepared_at' => $audit['prepared_at'], 'idempotency_key' => $audit['idempotency_key']]];
    }

    public function key(string $month, array $row): string
    {
        $this->assertMonth($month);
        $this->positive($row['submission_id'] ?? null);
        if (($row['monthly_scope_key'] ?? null) !== $month) {
            throw new InvalidArgumentException('Ledger month scope conflict.');
        }

        return $month.':'.$row['submission_id'];
    }

    private function entry(string $month, int $submission, int $dealer, ?int $recordedId): array
    {
        return ['monthly_scope_key' => $month, 'submission_id' => $submission, 'dealer_id' => $dealer,
            'subject_type' => 'dealer', 'points' => 1, 'policy_version' => self::POLICY,
            'recorded_entry_id' => $recordedId, 'would_create' => $recordedId === null, 'evidence' => []];
    }

    private function evidence(array $row): array
    {
        $data = [];
        foreach (['batch_id', 'item_id', 'attempt_id', 'call_event_id', 'resolution_event_id', 'resolution_version', 'confirmed_by'] as $field) {
            $this->positive($row[$field] ?? null);
            $data[$field] = $row[$field];
        }
        if (! in_array($row['purpose'] ?? null, ['general_audit', 'award_top3'], true) || $data['resolution_version'] > 2) {
            throw new InvalidArgumentException('Invalid evidence scope.');
        }
        $this->instant($row['confirmed_at'] ?? null);
        $data['purpose'] = $row['purpose'];
        $data['confirmed_at'] = $row['confirmed_at'];

        return $data;
    }

    private function positive(mixed $value): void
    {
        if (! is_int($value) || $value < 1) {
            throw new InvalidArgumentException('Invalid preparation identity.');
        }
    }

    public function assertMonth(string $month): void
    {
        if (! preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $month)) {
            throw new InvalidArgumentException('Invalid monthly scope.');
        }
    }

    private function instant(mixed $value): void
    {
        if (! is_string($value) || ! preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]{1,6})?(Z|[+-][0-9]{2}:[0-9]{2})$/D', $value)) {
            throw new InvalidArgumentException('Invalid audit instant.');
        }
        try {
            new DateTimeImmutable($value);
        } catch (\Exception) {
            throw new InvalidArgumentException('Invalid audit instant.');
        }
        $errors = DateTimeImmutable::getLastErrors();
        if ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) {
            throw new InvalidArgumentException('Invalid audit instant.');
        }
    }
}

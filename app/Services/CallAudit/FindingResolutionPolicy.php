<?php

namespace App\Services\CallAudit;

final class FindingResolutionPolicy
{
    public const VERSION = 'finding_resolution_v1';

    public const EVENT = 'finding_resolution_recorded';

    public const COMMAND = 'resolve_finding';

    public const SCHEMA = 'call_audit_resolution_event_v1';

    public function validate(array $input): array
    {
        $allowed = ['purpose', 'month', 'attempt_id', 'row_version', 'resolution_version', 'decision', 'reason', 'idempotency_key'];
        if (array_diff(array_keys($input), $allowed)
            || ! in_array($input['purpose'] ?? null, ['general_audit', 'award_top3'], true)
            || ! in_array($input['decision'] ?? null, ['confirmed', 'dismissed', 'needs_review'], true)
            || ! is_string($input['reason'] ?? null) || trim($input['reason']) === '' || mb_strlen($input['reason']) > 2000
            || ! is_string($input['idempotency_key'] ?? null) || ! preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $input['idempotency_key'])
            || (isset($input['month']) && (! is_string($input['month']) || ! preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $input['month'])))) {
            throw new CallCommandRejected('invalid_resolution_command', 422);
        }
        foreach (['attempt_id', 'row_version', 'resolution_version'] as $field) {
            if (! is_int($input[$field] ?? null) || $input[$field] < ($field === 'resolution_version' ? 0 : 1)
                || $input[$field] >= 4294967295) {
                throw new CallCommandRejected('invalid_resolution_command', 422);
            }
        }
        $input['reason'] = trim($input['reason']);
        $input['month'] ??= null;

        return $input;
    }

    public function transition(?string $current, string $next): void
    {
        if (! (($current === null && in_array($next, ['confirmed', 'dismissed', 'needs_review'], true))
            || ($current === 'needs_review' && in_array($next, ['confirmed', 'dismissed'], true)))) {
            throw new CallCommandRejected('resolution_transition_conflict');
        }
    }
}

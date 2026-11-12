<?php

namespace App\Services\CallAudit;

use App\Models\CallAuditItem;
use Carbon\CarbonImmutable;

final class CallResultPolicy
{
    public const VERSION = 'call_result_v1';

    public const QUESTIONNAIRE = 'call_questionnaire_v1';

    public const RETRY = 'no_answer_24h_max2_v1';

    public function validate(array $input): void
    {
        $outcome = $input['outcome'] ?? null;
        if (! in_array($outcome, ['correct', 'wrong', 'no_answer'], true)) {
            throw new CallCommandRejected('invalid_call_result', 422);
        }
        $answers = [];
        foreach (['answer_1', 'answer_2', 'answer_3'] as $field) {
            if (! array_key_exists($field, $input) || ($input[$field] !== null && ! is_bool($input[$field]))) {
                throw new CallCommandRejected('invalid_call_result', 422);
            }
            $answers[] = $input[$field];
        }
        if (($outcome === 'no_answer' && $answers !== [null, null, null])
            || ($outcome !== 'no_answer' && in_array(null, $answers, true))
            || ($outcome === 'correct' && $answers !== [true, true, true])
            || ($outcome === 'wrong' && ! in_array(false, $answers, true))
            || (isset($input['note']) && (! is_string($input['note']) || mb_strlen($input['note']) > 2000))) {
            throw new CallCommandRejected('invalid_call_result', 422);
        }
    }

    /** Derive eligibility from immutable attempts as well as the cached projection. */
    public function assertCallable(CallAuditItem $item, CarbonImmutable $now): int
    {
        $attempts = $item->attempts()->orderBy('attempt_no')->lockForUpdate()->get();
        if ($item->source_stale_at !== null) {
            throw new CallCommandRejected('item_not_claimable');
        }
        if ($attempts->isEmpty() && $item->queue_state === 'uncalled' && $item->attempt_count === 0
            && $item->latest_outcome === null && $item->retry_due_at === null) {
            return 1;
        }
        $first = $attempts->first();
        if ($attempts->count() !== 1 || $first->attempt_no !== 1 || $first->outcome !== 'no_answer'
            || $first->questionnaire_version !== self::QUESTIONNAIRE || $first->retry_policy_version !== self::RETRY
            || $first->answer_1 !== null || $first->answer_2 !== null || $first->answer_3 !== null
            || $first->retry_due_at === null || $item->retry_due_at === null
            || ! $first->retry_due_at->equalTo($item->retry_due_at)
            || $item->queue_state !== 'recall' || $item->attempt_count !== 1 || $item->latest_outcome !== 'no_answer') {
            throw new CallCommandRejected('item_not_claimable');
        }
        if ($now < $item->retry_due_at) {
            throw new CallCommandRejected('retry_not_due');
        }

        return 2;
    }
}

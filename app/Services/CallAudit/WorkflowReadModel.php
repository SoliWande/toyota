<?php

namespace App\Services\CallAudit;

use App\Models\CallAuditBatch;
use App\Models\CallAuditItem;
use Carbon\CarbonImmutable;

final class WorkflowReadModel
{
    public function __construct(private CallResultPolicy $policy) {}

    /** Only non-PII evidence/projection fields; never fetch notes, receipts, seed or manifest. */
    public function read(CallAuditBatch $batch, CarbonImmutable $now): array
    {
        $items = $batch->items()->orderBy('selection_order')->orderBy('id')->get([
            'id', 'batch_id', 'dealer_id_snapshot', 'row_version', 'queue_state', 'attempt_count',
            'latest_outcome', 'retry_due_at', 'source_fingerprint', 'fingerprint_version',
        ]);
        if ($items->count() !== $batch->actual_count) {
            throw new ReadUnavailable('Frozen item count mismatch.');
        }
        $items->load(['attempts' => fn ($query) => $query->orderBy('attempt_no')->select([
            'id', 'item_id', 'actor_id', 'attempt_no', 'started_at', 'recorded_at', 'questionnaire_version',
            'outcome', 'answer_1', 'answer_2', 'answer_3', 'source_fingerprint_used', 'item_version_used',
            'retry_due_at', 'retry_policy_version',
        ])]);
        $events = $batch->events()->where(fn ($query) => $query->where('event_type', 'call_result_recorded')
            ->orWhere('command_name', 'record_call_result'))->orderBy('event_sequence')->get([
                'id', 'batch_id', 'item_id', 'attempt_id', 'actor_id', 'occurred_at', 'event_type',
                'event_schema_version', 'command_name', 'event_ordinal', 'before_version', 'after_version',
                'reason_code', 'metadata', 'event_sequence',
            ]);
        if ($events->contains(fn ($event) => ! $items->contains('id', $event->item_id))) {
            throw new ReadUnavailable('Orphan result event.');
        }
        $byItem = $events->groupBy('item_id');
        $states = [];
        foreach ($items as $item) {
            $states[$item->id] = $this->classify($item, $byItem->get($item->id, collect()), $now);
        }

        return ['items' => $items, 'states' => $states, 'progress' => $this->counts(array_values($states))];
    }

    public function counts(array $states): array
    {
        $queues = array_count_values(array_column($states, 'state'));
        $needs = $queues['needs_call'] ?? 0;
        $recall = $queues['recall'] ?? 0;
        $pending = $queues['finding_pending'] ?? 0;

        return ['called_count' => count($states) - $needs, 'needs_call_count' => $needs,
            'recall_count' => $recall, 'completed_count' => $queues['completed'] ?? 0,
            'finding_pending_count' => $pending,
            'is_complete' => count($states) > 0 && $needs === 0 && $recall === 0 && $pending === 0];
    }

    private function classify(CallAuditItem $item, $events, CarbonImmutable $now): array
    {
        $attempts = $item->attempts;
        $number = $attempts->count();
        if ($number > 2 || $item->attempt_count !== $number || $events->count() !== $number || $item->row_version < 1) {
            throw new ReadUnavailable('Attempt/projection/event count mismatch.');
        }
        $previous = null;
        $previousSequence = 0;
        foreach ($attempts as $index => $attempt) {
            try {
                $this->policy->validate(['outcome' => $attempt->outcome,
                    'answer_1' => $attempt->answer_1, 'answer_2' => $attempt->answer_2, 'answer_3' => $attempt->answer_3]);
            } catch (CallCommandRejected) {
                throw new ReadUnavailable('Unknown answer/outcome mapping.');
            }
            $event = $events->firstWhere('attempt_id', $attempt->id);
            $retry = $index === 0 && $attempt->outcome === 'no_answer';
            if ($attempt->attempt_no !== $index + 1 || $attempt->questionnaire_version !== CallResultPolicy::QUESTIONNAIRE
                || $item->fingerprint_version !== SourceFingerprint::VERSION
                || ! hash_equals($item->source_fingerprint, $attempt->source_fingerprint_used)
                || $attempt->item_version_used < 1 || $attempt->item_version_used >= $item->row_version
                || $attempt->started_at === null || $attempt->recorded_at === null || $attempt->recorded_at < $attempt->started_at
                || $attempt->recorded_at > $now
                || ($retry && ($attempt->retry_policy_version !== CallResultPolicy::RETRY || $attempt->retry_due_at === null
                    || ! $attempt->retry_due_at->equalTo($attempt->recorded_at->utc()->addSeconds(86400))))
                || (! $retry && ($attempt->retry_due_at !== null || $attempt->retry_policy_version !== null))
                || ($previous !== null && ($previous->outcome !== 'no_answer' || $previous->retry_due_at === null
                    || $attempt->started_at < $previous->retry_due_at || $attempt->item_version_used <= $previous->item_version_used))
                || $event === null || (int) $event->actor_id !== (int) $attempt->actor_id
                || (int) $event->item_id !== (int) $item->id || $event->event_type !== 'call_result_recorded'
                || $event->command_name !== 'record_call_result' || $event->event_schema_version !== 'call_audit_result_event_v1'
                || $event->event_ordinal !== 0 || $event->reason_code !== CallResultPolicy::VERSION
                || $event->before_version !== $attempt->item_version_used || $event->after_version !== $attempt->item_version_used + 1
                || $event->event_sequence <= $previousSequence || $event->occurred_at === null
                || ! $event->occurred_at->equalTo($attempt->recorded_at)
                || ! is_array($event->metadata)
                || ($event->metadata['policy'] ?? null) !== CallResultPolicy::VERSION
                || ! array_key_exists('finding_status', $event->metadata ?? [])
                || $event->metadata['finding_status'] !== ($attempt->outcome === 'wrong' ? 'pending' : null)) {
                throw new ReadUnavailable('Inconsistent call history.');
            }
            $previous = $attempt;
            $previousSequence = $event->event_sequence;
        }
        $outcome = $previous?->outcome;
        $queue = $number === 0 ? 'uncalled' : ($outcome === 'no_answer' && $number === 1 ? 'recall'
            : ($outcome === 'wrong' ? 'finding_pending' : 'completed'));
        $due = $queue === 'recall' ? $previous->retry_due_at : null;
        if ($item->queue_state !== $queue || $item->latest_outcome !== $outcome
            || ($due === null ? $item->retry_due_at !== null : ($item->retry_due_at === null || ! $due->equalTo($item->retry_due_at)))) {
            throw new ReadUnavailable('Latest result projection mismatch.');
        }

        return ['state' => $queue === 'uncalled' ? 'needs_call' : $queue, 'outcome' => $outcome,
            'attempt_count' => $number, 'retry_due_at' => $due?->toIso8601String(),
            'is_due' => $due === null ? null : $now >= $due,
            'finding_status' => $queue === 'finding_pending' ? 'pending' : null];
    }
}

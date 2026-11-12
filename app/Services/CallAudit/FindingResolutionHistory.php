<?php

namespace App\Services\CallAudit;

use App\Models\CallAuditBatch;
use App\Models\CallAuditEvent;
use Carbon\CarbonImmutable;

final class FindingResolutionHistory
{
    public function __construct(private FindingResolutionPolicy $policy, private ManifestCodec $codec, private SourceFingerprint $keys) {}

    public function intent(int $batch, int $item, int $actor, array $input): string
    {
        return hash_hmac('sha256', 'finding-resolution-intent-v1|'.$this->codec->encode([
            'batch_id' => $batch, 'item_id' => $item, 'actor_id' => $actor, 'input' => $input,
        ]), config('app.key'));
    }

    public function signature(array $envelope, array $private): string
    {
        return hash_hmac('sha256', 'finding-resolution-event-v1|'.$this->codec->encode([
            'event' => $envelope, 'private_digest' => $this->codec->digest($private),
        ]), config('app.key'));
    }

    public function read(CallAuditBatch $batch, array $finding, CarbonImmutable $now): array
    {
        $events = $batch->events()->where('item_id', $finding['item_id'])->where(fn ($query) => $query
            ->where('event_type', FindingResolutionPolicy::EVENT)->orWhere('command_name', FindingResolutionPolicy::COMMAND)
            ->orWhere('event_schema_version', FindingResolutionPolicy::SCHEMA))
            ->orderBy('event_sequence')->get();
        if ($events->count() > 2) {
            throw new ReadUnavailable('Invalid resolution history length.');
        }
        $rows = [];
        $receipts = [];
        $current = null;
        $version = 0;
        $previousTime = CarbonImmutable::parse($finding['recorded_at']);
        $previousSequence = (int) $batch->events()->whereKey($finding['event_id'])->value('event_sequence');
        foreach ($events as $event) {
            $private = $event->private_payload_encrypted;
            if (! is_array($private) || ! is_array($private['input'] ?? null) || ! is_array($private['response'] ?? null)
                || ! is_string($private['payload_digest'] ?? null)) {
                throw new ReadUnavailable('Resolution receipt unavailable.');
            }
            try {
                $input = $this->policy->validate($private['input']);
                $this->policy->transition($current, $input['decision']);
            } catch (CallCommandRejected) {
                throw new ReadUnavailable('Invalid resolution intent or transition.');
            }
            $expected = $this->response($finding, $version + 1, $input['decision'], (int) $event->actor_id, $event->occurred_at);
            $metadata = ['policy' => FindingResolutionPolicy::VERSION, 'version_namespace' => 'resolution',
                'decision' => $input['decision'], 'item_row_version' => $finding['row_version'], 'finding_event_id' => $finding['event_id']];
            $predecessor = $batch->events()->where('event_sequence', $event->event_sequence - 1)->first(['event_digest', 'occurred_at']);
            if ($event->event_type !== FindingResolutionPolicy::EVENT || $event->command_name !== FindingResolutionPolicy::COMMAND
                || $event->event_schema_version !== FindingResolutionPolicy::SCHEMA || $event->event_ordinal !== 0
                || $event->reason_code !== FindingResolutionPolicy::VERSION || $event->digest_key_id !== $this->keys->keyId()
                || (int) $event->attempt_id !== $finding['attempt_id'] || (int) $event->actor_id < 1
                || $event->before_version !== $version || $event->after_version !== $version + 1
                || $event->event_sequence <= $previousSequence || $event->occurred_at === null
                || $event->occurred_at < $previousTime || $event->occurred_at > $now
                || $predecessor === null || $predecessor->occurred_at > $event->occurred_at
                || ! hash_equals($predecessor->event_digest, $event->previous_event_digest ?? '')
                || $input['purpose'] !== $batch->purpose || ($input['month'] !== null && $input['month'] !== $batch->monthly_scope_key)
                || $input['attempt_id'] !== $finding['attempt_id'] || $input['row_version'] !== $finding['row_version']
                || $input['resolution_version'] !== $version || $input['idempotency_key'] !== $event->command_key
                || ! hash_equals($this->codec->digest($input), $this->codec->digest($private['input']))
                || ! hash_equals($this->codec->digest($metadata), $this->codec->digest($event->metadata))
                || ! hash_equals($this->codec->digest($expected), $this->codec->digest($private['response']))
                || ! hash_equals($this->intent((int) $batch->id, $finding['item_id'], (int) $event->actor_id, $input), $private['payload_digest'])
                || ! hash_equals($this->signature($this->envelope($event), $private), $event->event_digest)) {
                throw new ReadUnavailable('Resolution history integrity conflict.');
            }
            $version++;
            $current = $input['decision'];
            $previousTime = $event->occurred_at;
            $previousSequence = $event->event_sequence;
            $rows[] = $expected;
            $receipts[] = ['actor_id' => (int) $event->actor_id, 'key' => $event->command_key,
                'digest' => $private['payload_digest'], 'response' => $expected];
        }

        return ['current' => $rows === [] ? $this->response($finding, 0, null, null, null) : end($rows),
            'history' => $rows, 'receipts' => $receipts];
    }

    public function response(array $finding, int $version, ?string $decision, ?int $actor, ?CarbonImmutable $time): array
    {
        return ['batch_id' => $finding['batch_id'], 'item_id' => $finding['item_id'], 'attempt_id' => $finding['attempt_id'],
            'row_version' => $finding['row_version'], 'resolution_version' => $version, 'decision' => $decision,
            'actor_id' => $actor, 'recorded_at' => $time?->toIso8601String()];
    }

    private function envelope(CallAuditEvent $event): array
    {
        $data = [];
        foreach (['batch_id', 'item_id', 'attempt_id', 'actor_id', 'event_sequence', 'event_ordinal', 'before_version', 'after_version'] as $field) {
            $data[$field] = (int) $event->$field;
        }
        foreach (['event_type', 'event_schema_version', 'command_name', 'command_key', 'reason_code', 'correlation_id',
            'metadata', 'previous_event_digest', 'digest_key_id'] as $field) {
            $data[$field] = $event->$field;
        }
        $data['occurred_at'] = $event->occurred_at->format('Y-m-d H:i:s.u');

        return $data;
    }
}

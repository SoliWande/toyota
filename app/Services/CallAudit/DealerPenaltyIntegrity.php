<?php

namespace App\Services\CallAudit;

use App\Models\DealerPenaltyEntry;
use App\Models\DealerPenaltyEvent;
use Illuminate\Database\Eloquent\Model;

final class DealerPenaltyIntegrity
{
    public function __construct(private ManifestCodec $codec, private SourceFingerprint $keys) {}

    public function seal(Model $model): string
    {
        $data = $model->getAttributes();
        if ($model instanceof DealerPenaltyEvent) {
            unset($data['id'], $data['event_digest']);
            $data['metadata'] = $model->metadata;
            $data['occurred_at'] = $model->occurred_at->format('Y-m-d H:i:s.u');
        }

        return hash_hmac('sha256', 'dealer-penalty-seal-v1|'.$this->codec->encode($data), config('app.key'));
    }

    public function verify(DealerPenaltyEntry $entry): void
    {
        if ($entry->points !== 1 || $entry->policy_version !== DealerPenaltyEntry::POLICY
            || $entry->projection_policy_version !== DealerPenaltyEntry::PROJECTION_POLICY
            || $entry->ranking_policy_version !== DealerPenaltyEntry::RANKING_POLICY
            || $entry->encryption_key_id !== $this->keys->keyId()) {
            throw new ReadUnavailable('Unsupported dealer penalty policy/key.');
        }
        // The first signed event seals the immutable entry including its encrypted attribution/policy.
        $evidence = $entry->evidence()->orderBy('id')->get();
        $events = $entry->events()->orderBy('event_sequence')->get();
        $count = 0;
        $previous = null;
        foreach ($events as $i => $event) {
            if ($event->digest_key_id !== $this->keys->keyId() || $event->encryption_key_id !== $this->keys->keyId()
                || $event->policy_version !== DealerPenaltyEntry::POLICY || $event->event_sequence !== $i + 1
                || $event->before_version !== $i || $event->after_version !== $i + 1 || $event->previous_event_digest !== $previous
                || ! hash_equals($event->event_digest, $this->seal($event))
                || $event->evidence_count_before !== $count || ($event->metadata['entry_seal'] ?? null) !== $this->seal($entry)) {
                throw new ReadUnavailable('Dealer penalty event integrity conflict.');
            }
            $intent = $event->intent_encrypted;
            $receipt = $event->receipt_encrypted;
            if (! is_array($intent) || ! is_array($receipt) || ! hash_equals($event->payload_digest, $this->intent((int) $event->actor_id, $intent))) {
                throw new ReadUnavailable('Dealer penalty receipt integrity conflict.');
            }
            $count = $event->evidence_count_after;
            $previous = $event->event_digest;
        }
        if ($events->isEmpty() || $evidence->count() !== $count || $count === 0) {
            throw new ReadUnavailable('Dealer penalty evidence missing.');
        }
        $sealed = [];
        foreach ($events as $event) {
            foreach ($event->metadata['evidence_seals'] ?? [] as $item) {
                $sealed[$item['id']] = $item['seal'];
            }
        }
        foreach ($evidence as $row) {
            if ($row->digest_key_id !== $this->keys->keyId() || $row->encryption_key_id !== $this->keys->keyId()
                || ! isset($sealed[$row->id]) || ! hash_equals($sealed[$row->id], $this->seal($row))) {
                throw new ReadUnavailable('Dealer penalty evidence integrity conflict.');
            }
        }
    }

    public function intent(int $actor, array $intent): string
    {
        return hash_hmac('sha256', 'dealer-penalty-intent-v1|'.$this->codec->encode(['actor_id' => $actor, 'input' => $intent]), config('app.key'));
    }
}

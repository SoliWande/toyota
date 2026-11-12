<?php

namespace App\Actions;

use App\Models\Award;
use App\Models\AwardPublication;
use App\Models\AwardWinner;
use App\Models\CallAuditBatch;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\AwardPublicationReadiness;
use App\Services\AwardResults;
use App\Services\CallAudit\CallCommandRejected;
use App\Services\CallAudit\FreezeClock;
use App\Services\CallAudit\ManifestCodec;
use App\Services\CallAudit\ReadUnavailable;
use App\Services\CallAudit\SourceFingerprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class PublishAuditedAward
{
    public function execute(int $awardId, string $key, ?string $fingerprint = null): array
    {
        if (! preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $key)) {
            throw new CallCommandRejected('invalid_publication_command', 422);
        }
        $key = strtolower($key);
        if (DB::transactionLevel() !== 0) {
            throw new CallCommandRejected('requires_top_level_transaction');
        }

        if (! in_array(strtoupper(DB::selectOne('SELECT @@transaction_isolation AS level')->level), ['REPEATABLE-READ', 'SERIALIZABLE'], true)) {
            throw new CallCommandRejected('unsupported_snapshot_isolation');
        }

        return DB::transaction(function () use ($awardId, $key, $fingerprint) {
            // Shared with source intake, sampling and ledger writer. All pre-snapshot reads are locking reads.
            if (! DB::table('submission_intake_mutex')->where('id', 1)->lockForUpdate()->first()) {
                throw new CallCommandRejected('intake_mutex_missing');
            }
            $actor = User::whereKey(Auth::id())->sharedLock()->firstOrFail();
            $award = Award::whereKey($awardId)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('publish', $award);
            $codec = app(ManifestCodec::class);
            $intent = hash_hmac('sha256', $codec->encode(['award_id' => $awardId, 'actor_id' => (int) $actor->id,
                'fingerprint' => $fingerprint, 'policy' => AwardPublicationReadiness::POLICY]), config('app.key'));
            $receipt = AwardPublication::where('actor_id', $actor->id)->where('request_key', $key)->lockForUpdate()->first();
            if ($receipt) {
                $this->verify($receipt);
                if (! hash_equals($receipt->intent_digest, $intent)) {
                    throw new CallCommandRejected('idempotency_payload_conflict');
                }

                return $receipt->receipt_encrypted;
            }
            if ($award->published_at !== null || AwardPublication::where('award_id', $awardId)->lockForUpdate()->exists()) {
                throw new CallCommandRejected('already_published');
            }
            $month = $award->period_start->format('Y-m');
            $batches = CallAuditBatch::where('monthly_scope_key', $month)->orderBy('id')->lockForUpdate()->get();
            $sources = CustomerSubmission::where('submitted_at', '>=', $award->period_start)->where('submitted_at', '<', $award->period_end)
                ->orderBy('id')->lockForUpdate()->get();
            // Protect ranking joins/name snapshots and terminal call/E2 evidence until commit.
            User::orderBy('id')->sharedLock()->get(['id']);
            Dealer::orderBy('id')->sharedLock()->get(['id']);
            $ledger = DB::table('dealer_penalty_entries')->where('monthly_scope_key', $month)->orderBy('id')->lockForUpdate()->pluck('id');
            $ledgerEvents = DB::table('dealer_penalty_events')->whereIn('entry_id', $ledger)->orderBy('id')->lockForUpdate()->get(['id', 'event_digest']);
            // First consistent read starts here, after competing writers have committed and locks are held.
            $readiness = app(AwardPublicationReadiness::class)->read($actor, $awardId);
            if (! $readiness['can_publish']) {
                throw new CallCommandRejected('publication_not_ready');
            }
            if ($fingerprint !== null && ! hash_equals($readiness['preview_fingerprint'], $fingerprint)) {
                throw new CallCommandRejected('publication_source_conflict');
            }
            $results = app(AwardResults::class);
            $preview = $results->preview($award);
            if (! hash_equals($readiness['preview_fingerprint'], $results->fingerprint($award, $preview))) {
                throw new CallCommandRejected('publication_source_conflict');
            }
            if ($award->winners()->exists()) {
                throw new CallCommandRejected('draft_winner_history_conflict');
            }
            $now = app(FreezeClock::class)->now();
            $winners = collect($preview['dealers'])->merge($preview['sales'])->values()->all();
            foreach ($winners as $row) {
                (new AwardWinner)->forceFill(['award_id' => $awardId] + $row)->save();
            }
            $response = ['award_id' => $awardId, 'status' => 'published', 'published_at' => $now->toIso8601String(),
                'winner_count' => count($winners)];
            $evidence = ['policy' => AwardPublicationReadiness::POLICY, 'readiness' => $readiness, 'winners' => $winners,
                'source_state_digest' => hash_hmac('sha256', $codec->encode($sources->map->getAttributes()->all()), config('app.key')),
                'ledger_events' => $ledgerEvents->map(fn ($event) => (array) $event)->all(),
                'batches' => $batches->map(fn ($batch) => ['id' => (int) $batch->id, 'generation' => $batch->generation,
                    'manifest_digest' => $batch->manifest_digest, 'selection_digest' => $batch->selection_digest,
                    'event_digests' => $batch->events()->orderBy('event_sequence')->pluck('event_digest')->all()])->all(),
                'post_publication_adjustments' => 'separate_workflow_only'];
            $publication = (new AwardPublication)->forceFill(['award_id' => $awardId, 'actor_id' => $actor->id,
                'monthly_scope_key' => $month, 'request_key' => $key, 'intent_digest' => $intent,
                'policy_version' => AwardPublicationReadiness::POLICY, 'digest_key_id' => app(SourceFingerprint::class)->keyId(),
                'source_cutoff_at' => $now, 'published_at' => $now, 'ledger_cutoff_id' => $ledgerEvents->max('id') ?? 0,
                'batch_generation' => $batches->where('purpose', 'award_top3')->max('generation'),
                'preview_fingerprint' => $readiness['preview_fingerprint'], 'evidence_encrypted' => $evidence, 'receipt_encrypted' => $response]);
            $publication->evidence_digest = $this->seal($publication);
            $publication->save();
            $award->forceFill(['published_by' => $actor->id, 'published_at' => $now])->save();
            $this->verify($publication->fresh());

            return $response;
        }, 3);
    }

    private function seal(AwardPublication $row): string
    {
        $header = [];
        foreach (['award_id', 'actor_id', 'monthly_scope_key', 'request_key', 'intent_digest', 'policy_version',
            'digest_key_id', 'ledger_cutoff_id', 'batch_generation', 'preview_fingerprint'] as $key) {
            $header[$key] = in_array($key, ['award_id', 'actor_id', 'ledger_cutoff_id', 'batch_generation'], true) ? (int) $row->$key : $row->$key;
        }
        foreach (['source_cutoff_at', 'published_at'] as $key) {
            $header[$key] = $row->$key->format('Y-m-d H:i:s.u');
        }

        return hash_hmac('sha256', 'award-publication-v1|'.app(ManifestCodec::class)->encode([
            $header, $row->evidence_encrypted, $row->receipt_encrypted,
        ]), config('app.key'));
    }

    public function verify(AwardPublication $row): void
    {
        $award = Award::findOrFail($row->award_id);
        $actual = $award->winners()->orderByRaw("winner_type = 'sales'")->orderBy('rank')->get()
            ->map(fn ($winner) => array_intersect_key($winner->getAttributes(), array_flip([
                'winner_type', 'sales_id', 'dealer_id', 'rank', 'score', 'winner_name_snapshot', 'sales_dealer_id_snapshot', 'dealer_name_snapshot', 'dealer_code_snapshot',
            ])))->all();
        if ($row->digest_key_id !== app(SourceFingerprint::class)->keyId() || ! hash_equals($row->evidence_digest, $this->seal($row))
            || $award->published_at === null || ! $award->published_at->equalTo($row->published_at->startOfSecond())
            || (int) $award->published_by !== (int) $row->actor_id
            || app(ManifestCodec::class)->digest($actual) !== app(ManifestCodec::class)->digest($row->evidence_encrypted['winners'])) {
            throw new ReadUnavailable('Publication evidence unavailable.');
        }
    }
}

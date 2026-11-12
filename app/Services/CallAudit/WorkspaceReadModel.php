<?php

namespace App\Services\CallAudit;

use App\Models\CallAuditBatch;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\SubmissionVerification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class WorkspaceReadModel
{
    public function __construct(private SourceFingerprint $fingerprints, private FreezeClock $clock, private WorkflowReadModel $workflow) {}

    public function read(int $batchId, int $itemId, array $input): array
    {
        return DB::transaction(function () use ($batchId, $itemId, $input) {
            $actor = User::select(['id', 'role', 'status'])->whereKey(Auth::id())->sharedLock()->first();
            if ($actor === null || Gate::forUser($actor)->denies('viewAny', CallAuditBatch::class)) {
                throw new CallCommandRejected('workspace_forbidden', 403);
            }
            $batch = CallAuditBatch::whereKey($batchId)->where('purpose', $input['purpose'])->where('lifecycle_state', 'frozen')
                ->sharedLock()->first(['id', 'purpose', 'actual_count', 'digest_key_id']);
            $item = $batch?->items()->whereKey($itemId)->sharedLock()->first();
            if ($batch === null || $item === null) {
                throw new CallCommandRejected('audit_item_not_found', 404);
            }
            if (Gate::forUser($actor)->denies('view', $batch)) {
                throw new CallCommandRejected('workspace_forbidden', 403);
            }
            if ($item->row_version !== (int) $input['row_version'] || $batch->digest_key_id !== $this->fingerprints->keyId()) {
                throw new CallCommandRejected('workspace_version_conflict');
            }
            $now = $this->clock->now();
            $state = $this->workflow->read($batch, $now)['states'][$itemId];
            $contact = isset($input['claim_token']);
            if ($contact && (Gate::forUser($actor)->denies('viewCallContact', $batch)
                || (int) $item->claim_owner_id !== (int) $actor->id || $item->claim_expires_at === null
                || $item->claim_expires_at <= $now || ! hash_equals($item->claim_token_hash ?? '', hash('sha256', $input['claim_token']))
                || ! in_array($state['state'], ['needs_call', 'recall'], true)
                || ($state['state'] === 'recall' && ! $state['is_due']))) {
                throw new CallCommandRejected('contact_claim_conflict');
            }
            $source = CustomerSubmission::whereKey($item->submission_id)->sharedLock()->first(SourceFingerprint::FIELDS);
            $dealer = $source ? Dealer::whereKey($source->dealer_id)->sharedLock()->first(['id', 'name', 'code']) : null;
            $source?->setRelation('dealer', $dealer);
            if ($source === null || $item->fingerprint_version !== SourceFingerprint::VERSION
                || ! $this->fingerprints->matches($item->source_fingerprint, $source)) {
                throw new CallCommandRejected('source_drift_conflict');
            }
            $snapshot = $item->source_snapshot_encrypted;
            $dealerSnapshot = $item->dealer_snapshot_encrypted;
            if (! is_array($snapshot) || ! is_array($dealerSnapshot) || (int) ($snapshot['id'] ?? 0) !== (int) $item->submission_id
                || (int) ($dealerSnapshot['id'] ?? 0) !== (int) $item->dealer_id_snapshot) {
                throw new CallCommandRejected('workspace_snapshot_conflict');
            }
            $frozenSource = new CustomerSubmission;
            $frozenSource->setRawAttributes($snapshot, true);
            $frozenDealer = new Dealer;
            $frozenDealer->setRawAttributes($dealerSnapshot, true);
            $frozenSource->setRelation('dealer', $frozenDealer);
            if (! $this->fingerprints->matches($item->source_fingerprint, $frozenSource)) {
                throw new CallCommandRejected('workspace_snapshot_conflict');
            }
            $phone = (new PhoneEligibility)->inspect($snapshot['phone'] ?? null, $snapshot['phone_normalized'] ?? null);
            $name = $snapshot['customer_name'] ?? null;
            $dealerName = $dealerSnapshot['name'] ?? null;
            $vehicle = config('vehicles.toyota_models.'.($snapshot['vehicle_model'] ?? '')) ?? ($snapshot['vehicle_description'] ?? null);
            $plate = $snapshot['license_plate'] ?? null;
            if ($phone['status'] !== 'READY' || ! is_string($name) || trim($name) === '' || ! is_string($dealerName)
                || ! is_string($vehicle) || trim($vehicle) === '' || ! is_string($plate) || trim($plate) === '') {
                throw new CallCommandRejected('workspace_data_unavailable');
            }
            $verification = app(SubmissionVerification::class);
            $evidence = $verification->state($source->id);
            $verified = null;
            if ($verification->evidencedProgress($source, $evidence) === 'member') {
                $at = DB::table('submission_verification_audits')->where('submission_id', $source->id)
                    ->where('action', 'group')->orderByDesc('id')->value('created_at');
                $verified = $at ? CarbonImmutable::parse($at, config('app.timezone'))->format('d/m') : null;
            }
            $latest = $item->attempts()->orderByDesc('attempt_no')->first(['answer_1', 'answer_2', 'answer_3']);
            $vehiclePhrase = str_starts_with(mb_strtolower($vehicle), 'toyota ') ? $vehicle : 'Toyota '.$vehicle;

            return ['id' => $itemId, 'row_version' => $item->row_version, 'name' => $name, 'dealer' => $dealerName,
                'vehicle' => trim($vehicle.' '.($snapshot['first_registration_year'] ?? '')), 'plate' => $plate, 'verified_at' => $verified,
                'phone' => $contact ? $phone['phone'] : null,
                'phone_masked' => substr($phone['phone'], 0, 2).'****'.substr($phone['phone'], -3),
                'questions' => ['Anh có đăng ký tham gia cộng đồng không ạ?',
                    'Anh đang đi '.$vehiclePhrase.', biển số '.$plate.' phải không ạ?',
                    'Anh đăng ký qua đại lý '.$dealerName.' đúng không ạ?'],
                'script' => '“Chào anh '.$name.', em gọi từ ban quản trị Cộng đồng Toyota Veloz & Hilux. Em xin phép hỏi nhanh ba câu để xác nhận đăng ký của anh ạ.”',
                'state' => $state['state'], 'outcome' => $state['outcome'], 'is_due' => $state['is_due'],
                'answers' => $latest ? [$latest->answer_1, $latest->answer_2, $latest->answer_3] : [null, null, null]];
        }, 3);
    }
}

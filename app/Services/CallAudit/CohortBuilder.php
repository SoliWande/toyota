<?php

namespace App\Services\CallAudit;

use App\Enums\LeaderboardPeriod;
use App\Models\CustomerSubmission;
use App\Services\DemoSubmissionScope;
use App\Services\FacebookUrlNormalizer;
use App\Services\LeaderboardService;
use App\Services\ResetDemoRetirement;
use App\Services\SubmissionIntake;
use App\Services\SubmissionIntegrity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class CohortBuilder
{
    public const VERSION = 'approved_monthly_cohort_v1';

    public function __construct(
        private LeaderboardService $leaderboard,
        private DemoSubmissionScope $demo,
        private PhoneEligibility $phones,
        private FacebookUrlNormalizer $facebook,
        private SubmissionIntegrity $integrity,
        private SourceFingerprint $fingerprints,
        private ManifestCodec $codec,
    ) {}

    /** Internal, read-only cohort; the caller must authorize access. No raw identities leave this result. */
    public function build(CarbonImmutable $month): array
    {
        return DB::transaction(function () use ($month) {
            [$start, $end] = $this->leaderboard->bounds(LeaderboardPeriod::Month, $month);
            $context = ['monthly_scope_key' => $start->format('Y-m'), 'period_start' => $start->format('Y-m-d H:i:s'),
                'period_end' => $end->format('Y-m-d H:i:s'), 'calendar_zone' => config('app.timezone'),
                'calendar_version' => 'leaderboard_current_v1', 'observed_at' => CarbonImmutable::now()->format('Y-m-d H:i:s.u'),
                'eligibility_policy_version' => self::VERSION,
                'phone_policy_version' => PhoneEligibility::VERSION, 'fingerprint_version' => SourceFingerprint::VERSION,
                'fingerprint_key_id' => $this->fingerprints->keyId()];
            $base = CustomerSubmission::query()->select(SourceFingerprint::FIELDS)->where('status', 'approved')
                ->where('submitted_at', '>=', $context['period_start'])->where('submitted_at', '<', $context['period_end']);
            try {
                $allIds = (clone $base)->orderBy('id')->pluck('id')->all();
                $rows = $this->demo->apply($base, 'customer_submissions.id')
                    ->with(['dealer:id,name,code', 'sales:id,role'])->orderBy('id')->get();
                $context['demo_policy'] = [
                    'version' => 'existing_demo_scope_v1', 'retired_config' => (bool) config('realistic_demo.retired'),
                    'retirement_receipt_present' => app(ResetDemoRetirement::class)->isPresent(),
                    'enabled' => (bool) config('realistic_demo.enabled'), 'include_in_metrics' => (bool) config('realistic_demo.include_in_metrics'),
                    'excluded_ids_digest' => $this->codec->digest(array_values(array_diff($allIds, $rows->pluck('id')->all()))),
                ];
            } catch (LogicException) {
                return ['status' => 'BLOCKED', 'context' => $context, 'candidates' => [], 'excluded' => [],
                    'issues' => [['code' => 'demo_scope_blocked', 'submission_ids' => []]]];
            }
            $issues = $excluded = $candidates = [];
            $indexes = ['phone' => [], 'plate' => [], 'facebook' => []];
            foreach ($rows as $row) {
                $id = (int) $row->id;
                if ($this->integrity->warning($row) !== null) {
                    $issues[] = ['code' => 'source_integrity_conflict', 'submission_ids' => [$id]];
                }
                $phone = $this->phones->inspect($row->phone, $row->phone_normalized);
                if ($phone['status'] === 'BLOCKED') {
                    $issues[] = ['code' => $phone['code'], 'submission_ids' => [$id]];
                }
                foreach ($phone['aliases'] as $alias) {
                    if (preg_match('/^0[1-9][0-9]{8,9}$/D', $alias)) {
                        $indexes['phone'][$alias][] = $id;
                    }
                }
                $plate = SubmissionIntake::plate($row->license_plate);
                if ($plate !== '') {
                    $indexes['plate'][$plate][] = $id;
                }
                $identities = [];
                foreach (['facebook_url', 'facebook_url_normalized', 'approved_facebook_identity'] as $field) {
                    if (! is_string($row->$field) || $row->$field === '') {
                        $issues[] = ['code' => 'facebook_identity_missing', 'submission_ids' => [$id]];

                        continue;
                    }
                    try {
                        $identities[] = $this->facebook->normalize($row->$field);
                    } catch (InvalidArgumentException) {
                        $issues[] = ['code' => 'facebook_identity_invalid', 'submission_ids' => [$id]];
                    }
                }
                $identities = array_values(array_unique($identities));
                if (count($identities) > 1) {
                    $issues[] = ['code' => 'facebook_alias_conflict', 'submission_ids' => [$id]];
                }
                foreach ($identities as $identity) {
                    $indexes['facebook'][$identity][] = $id;
                }
                if ($phone['status'] !== 'READY') {
                    $excluded[] = ['submission_id' => $id, 'code' => $phone['code']];

                    continue;
                }
                $candidates[] = ['submission_id' => $id, 'dealer_id' => (int) $row->dealer_id,
                    'sales_id' => $row->sales_id === null ? null : (int) $row->sales_id,
                    'source_fingerprint' => $this->fingerprints->of($row)];
            }
            foreach ($indexes as $kind => $groups) {
                foreach ($groups as $ids) {
                    $ids = array_values(array_unique($ids));
                    sort($ids, SORT_NUMERIC);
                    if (count($ids) > 1) {
                        $issues[] = ['code' => 'duplicate_'.$kind, 'submission_ids' => $ids];
                    }
                }
            }
            $unique = [];
            foreach ($issues as $issue) {
                $unique[$this->codec->encode($issue)] = $issue;
            }
            ksort($unique, SORT_STRING);

            return ['status' => $issues === [] ? 'READY' : 'BLOCKED', 'context' => $context,
                'candidates' => $issues === [] ? $candidates : [], 'excluded' => $excluded, 'issues' => array_values($unique)];
        });
    }
}

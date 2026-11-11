<?php

namespace App\Services\CallAudit;

use App\Enums\AwardPeriod;
use App\Models\Award;
use App\Models\CallAuditBatch;
use App\Models\User;
use App\Services\AwardResults;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

final class SamplingPlanner
{
    public function __construct(
        private CohortBuilder $cohorts,
        private AwardResults $awards,
        private SamplingAlgorithm $algorithm,
        private ManifestCodec $codec,
    ) {}

    public function general(User $actor, CarbonImmutable $month, ?string $seed = null, int $generation = 1): SamplingPlan
    {
        Gate::forUser($actor)->authorize('create', CallAuditBatch::class);

        return $this->plan(CallAuditBatch::PURPOSE_GENERAL, $month, null, $seed, $generation);
    }

    public function awardTop3(User $actor, Award $award, ?string $seed = null, int $generation = 1): SamplingPlan
    {
        Gate::forUser($actor)->authorize('create', CallAuditBatch::class);
        if (! $award->exists) {
            return $this->blocked(CallAuditBatch::PURPOSE_AWARD, [['code' => 'award_requires_monthly_draft', 'submission_ids' => []]]);
        }

        return DB::transaction(function () use ($award, $seed, $generation) {
            $current = Award::find($award->getKey());
            if ($current === null || $current->period_type !== AwardPeriod::Monthly || $current->published_at !== null) {
                return $this->blocked(CallAuditBatch::PURPOSE_AWARD, [['code' => 'award_requires_monthly_draft', 'submission_ids' => []]]);
            }

            return $this->plan(CallAuditBatch::PURPOSE_AWARD, CarbonImmutable::instance($current->period_start), $current, $seed, $generation);
        });
    }

    /** Reproduce entirely from a frozen private manifest/seed, never from live cohort queries. */
    public function reproduce(array $manifest, string $seed): array
    {
        if (strlen($seed) !== 32 || ($manifest['manifest_version'] ?? null) !== ManifestCodec::VERSION
            || ($manifest['algorithm_version'] ?? null) !== SamplingAlgorithm::VERSION
            || ! is_string($manifest['seed_commitment'] ?? null)
            || ! hash_equals($manifest['seed_commitment'], hash('sha256', $seed))
            || ! is_array($manifest['domain'] ?? null) || ! is_array($manifest['strata'] ?? null)
            || ! is_array($manifest['candidates'] ?? null) || ! array_is_list($manifest['candidates']) || ! array_is_list($manifest['strata'])) {
            throw new InvalidArgumentException('Unsupported sampling manifest/algorithm version.');
        }
        $purpose = $manifest['domain']['purpose'] ?? null;
        $strata = $manifest['strata'] ?? [];
        if (! in_array($purpose, [CallAuditBatch::PURPOSE_GENERAL, CallAuditBatch::PURPOSE_AWARD], true)
            || ($purpose === CallAuditBatch::PURPOSE_GENERAL && count($strata) !== 1)
            || ($purpose === CallAuditBatch::PURPOSE_AWARD && count($strata) > 3)) {
            throw new InvalidArgumentException('Invalid sampling purpose or strata.');
        }
        $ids = array_column($manifest['candidates'], 'submission_id');
        if (count($ids) !== count(array_unique($ids))) {
            throw new InvalidArgumentException('Duplicate submission in sampling manifest.');
        }
        foreach ($manifest['candidates'] as $candidate) {
            if (! is_array($candidate) || ! is_int($candidate['submission_id'] ?? null) || $candidate['submission_id'] < 1
                || ! is_int($candidate['dealer_id'] ?? null) || $candidate['dealer_id'] < 1
                || ! is_string($candidate['source_fingerprint'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $candidate['source_fingerprint'])) {
                throw new InvalidArgumentException('Invalid sampling candidate.');
            }
        }
        $seenStrata = [];
        $coveredDealers = [];
        $selected = [];
        foreach ($strata as $stratum) {
            if (! is_array($stratum) || ! is_string($stratum['key'] ?? null)
                || ! array_key_exists('dealer_id', $stratum) || ! is_int($stratum['requested_count'] ?? null)) {
                throw new InvalidArgumentException('Invalid sampling stratum shape.');
            }
            $dealer = $stratum['dealer_id'];
            if (isset($seenStrata[$stratum['key']])
                || ($purpose === CallAuditBatch::PURPOSE_GENERAL && ($dealer !== null || $stratum['key'] !== 'all' || $stratum['requested_count'] !== 60))
                || ($purpose === CallAuditBatch::PURPOSE_AWARD && (! is_int($dealer) || $dealer < 1 || $stratum['key'] !== 'dealer:'.$dealer || $stratum['requested_count'] !== 5))) {
                throw new InvalidArgumentException('Invalid or duplicate sampling stratum.');
            }
            $seenStrata[$stratum['key']] = true;
            $coveredDealers[] = $dealer;
            $domain = array_replace($manifest['domain'], ['stratum' => $stratum['key']]);
            $pool = array_values(array_filter($manifest['candidates'], fn ($row) => $stratum['dealer_id'] === null
                || $row['dealer_id'] === $stratum['dealer_id']));
            array_push($selected, ...$this->algorithm->select($pool, $stratum['requested_count'], $seed, $domain));
        }
        if ($purpose === CallAuditBatch::PURPOSE_AWARD) {
            foreach ($manifest['candidates'] as $candidate) {
                if (! in_array($candidate['dealer_id'], $coveredDealers, true)) {
                    throw new InvalidArgumentException('Candidate outside the frozen sampling strata.');
                }
            }
        }
        if (isset($manifest['selection_digest']) && ! hash_equals($manifest['selection_digest'], $this->codec->digest($selected))) {
            throw new InvalidArgumentException('Sampling selection digest mismatch.');
        }

        return $selected;
    }

    private function plan(string $purpose, CarbonImmutable $month, ?Award $award, ?string $seed, int $generation): SamplingPlan
    {
        if ($generation < 1 || ($seed !== null && strlen($seed) !== 32)) {
            throw new InvalidArgumentException('Invalid sampling generation or seed.');
        }

        return DB::transaction(function () use ($purpose, $month, $award, $seed, $generation) {
            $baseline = null;
            if ($award !== null) {
                try {
                    $results = $this->awards->preview($award);
                    $baseline = ['award_id' => (int) $award->id,
                        'fingerprint' => $this->awards->fingerprint($award, $results),
                        'dealers' => $results['dealers']->all(), 'sales' => $results['sales']->all()];
                } catch (ValidationException|LogicException) {
                    return $this->blocked($purpose, [['code' => 'award_baseline_blocked', 'submission_ids' => []]]);
                }
            }
            $cohort = $this->cohorts->build($month);
            if ($cohort['status'] !== 'READY') {
                return $this->blocked($purpose, $cohort['issues']);
            }
            $strata = $award === null ? [['key' => 'all', 'dealer_id' => null, 'requested_count' => 60]]
                : array_map(fn ($dealer) => ['key' => 'dealer:'.$dealer['dealer_id'],
                    'dealer_id' => $dealer['dealer_id'], 'rank' => $dealer['rank'], 'requested_count' => 5], $baseline['dealers']);
            $dealerIds = array_column($strata, 'dealer_id');
            $candidates = $award === null ? $cohort['candidates']
                : array_values(array_filter($cohort['candidates'], fn ($row) => in_array($row['dealer_id'], $dealerIds, true)));
            $cohort['context']['validated_monthly_cohort_digest'] = $this->codec->digest($cohort['candidates']);
            $manifest = ['manifest_version' => ManifestCodec::VERSION, 'algorithm_version' => SamplingAlgorithm::VERSION,
                'domain' => ['purpose' => $purpose, 'monthly_scope_key' => $cohort['context']['monthly_scope_key'],
                    'period_start' => $cohort['context']['period_start'], 'period_end' => $cohort['context']['period_end'], 'generation' => $generation],
                'context' => $cohort['context'], 'candidates' => $candidates,
                'excluded' => $cohort['excluded'], 'award_baseline' => $baseline, 'strata' => $strata];
            $seed ??= $this->algorithm->newSeed();
            $manifest['seed_commitment'] = hash('sha256', $seed);
            $selected = $this->reproduce($manifest, $seed);
            $counts = [];
            foreach ($strata as $stratum) {
                $eligible = count(array_filter($candidates, fn ($row) => $stratum['dealer_id'] === null || $row['dealer_id'] === $stratum['dealer_id']));
                $counts[] = $stratum + ['eligible_count' => $eligible, 'actual_count' => min($eligible, $stratum['requested_count'])];
            }
            $manifest['counts'] = ['requested_count' => array_sum(array_column($counts, 'requested_count')),
                'actual_count' => count($selected), 'eligible_count' => array_sum(array_column($counts, 'eligible_count')),
                'target_cap' => $award === null ? 60 : 15, 'strata' => $counts];
            $manifest['selection_digest'] = $this->codec->digest($selected);

            return new SamplingPlan(['status' => 'READY', 'purpose' => $purpose, 'issues' => []] + $manifest['counts'], $manifest, $seed, $selected);
        });
    }

    private function blocked(string $purpose, array $issues): SamplingPlan
    {
        return new SamplingPlan(['status' => 'BLOCKED', 'purpose' => $purpose, 'issues' => $issues,
            'requested_count' => $purpose === CallAuditBatch::PURPOSE_GENERAL ? 60 : null,
            'actual_count' => 0, 'eligible_count' => null, 'target_cap' => $purpose === CallAuditBatch::PURPOSE_GENERAL ? 60 : 15]);
    }
}

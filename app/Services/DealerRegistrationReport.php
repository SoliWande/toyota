<?php

namespace App\Services;

use App\Enums\LeaderboardPeriod;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class DealerRegistrationReport
{
    public const STAGES = [
        'new' => 'Mới đăng ký',
        'group' => 'Chờ vào group',
        'verification' => 'Chờ xác minh',
        'supplement' => 'Cần bổ sung',
        'verified' => 'Đã xác minh',
    ];

    public function forDealer(Dealer $dealer): array
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        [$start, $end] = app(LeaderboardService::class)->bounds(LeaderboardPeriod::Month, $now);
        $result = ['counts' => array_fill_keys(array_keys(self::STAGES), 0), 'rejected' => 0,
            'stalled_count' => 0, 'stalled' => [], 'missing_clock' => 0];
        $verification = app(SubmissionVerification::class);
        $query = app(DemoSubmissionScope::class)->apply($dealer->submissions()->getQuery())
            ->select(['id', 'customer_name', 'status', 'submitted_at', 'facebook_url_normalized'])
            ->where(fn ($query) => $query->where('status', 'pending')
                ->orWhere(fn ($query) => $query->where('submitted_at', '>=', $start)->where('submitted_at', '<', $end)));

        // Bounded memory and three queries per batch, never one evidence query per customer.
        $query->chunkById(250, function ($customers) use (&$result, $start, $end, $now, $verification) {
            $ids = $customers->modelKeys();
            $states = DB::table('submission_verification_states')->whereIn('submission_id', $ids)->get()->keyBy('submission_id');
            $audits = DB::table('submission_verification_audits')->whereIn('submission_id', $ids)
                ->orderBy('id')->get()->groupBy('submission_id');
            foreach ($customers as $customer) {
                $history = $audits->get($customer->id, collect());
                $lastGroup = $history->last(fn ($audit) => $audit->action === 'group');
                $state = $states->get($customer->id);
                $progress = $verification->evidencedProgressFromAudit($customer, $state, $lastGroup?->new_values);
                $stage = $this->stage($customer, $state, $progress);
                if ($customer->submitted_at && $customer->submitted_at >= $start && $customer->submitted_at < $end) {
                    if ($stage === null) {
                        $result['rejected']++;
                    } else {
                        $result['counts'][$stage]++;
                    }
                }
                if ($customer->status->value !== 'pending') {
                    continue;
                }
                // Replay actual workflow transitions. Re-saving the same stage does not restart its clock.
                $since = $customer->submitted_at;
                $previousStage = 'new';
                $groupAudit = null;
                foreach ($history as $audit) {
                    $snapshot = (object) json_decode($audit->new_values, true, flags: JSON_THROW_ON_ERROR);
                    if ($audit->action === 'group') {
                        $groupAudit = $audit->new_values;
                    }
                    $nextStage = $this->stage($customer, $snapshot,
                        $verification->evidencedProgressFromAudit($customer, $snapshot, $groupAudit));
                    if ($nextStage !== $previousStage) {
                        $since = $audit->created_at ? CarbonImmutable::parse($audit->created_at, config('app.timezone')) : null;
                        $previousStage = $nextStage;
                    }
                }
                // A changed Facebook profile invalidates old group evidence; its change time is not inferred.
                if ($previousStage !== $stage || ($stage === 'new' && $state?->group_progress && $progress === null)) {
                    $since = null;
                }
                if ($since === null) {
                    $result['missing_clock']++;
                } elseif ($since < $now->subHours(24)) {
                    $result['stalled_count']++;
                    $result['stalled'][] = ['id' => $customer->id, 'name' => $customer->customer_name,
                        'stage' => self::STAGES[$stage], 'since' => $since];
                    usort($result['stalled'], fn ($a, $b) => $a['since'] <=> $b['since'] ?: $a['id'] <=> $b['id']);
                    $result['stalled'] = array_slice($result['stalled'], 0, 5);
                }
            }
        });

        return $result;
    }

    private function stage(CustomerSubmission $customer, ?object $state, ?string $progress): ?string
    {
        if ($customer->status->value === 'rejected') {
            return null;
        }
        if ($customer->status->value === 'approved') {
            return 'verified'; // Moderation only; this never asserts Group membership or changes points.
        }
        if (($state->supplement_state ?? null) === 'open') {
            return 'supplement';
        }
        if (($state->supplement_state ?? null) === 'submitted'
            || isset(SubmissionVerification::BLOCKERS[$state->blocker_code ?? ''])
            || $progress === 'member') {
            return 'verification';
        }

        return in_array($progress, ['not_requested', 'requested'], true) ? 'group' : 'new';
    }
}

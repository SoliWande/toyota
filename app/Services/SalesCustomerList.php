<?php

namespace App\Services;

use App\Models\CustomerSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Read-only B08 projection; never infer Group membership from elapsed time. */
final class SalesCustomerList
{
    public function groups(User $sales): array
    {
        $groups = ['remind' => [], 'verification' => [], 'verified' => [], 'all' => []];
        $sales->submissions()->select(['id', 'status', 'facebook_url_normalized'])->chunkById(250, function ($rows) use (&$groups) {
            foreach ($this->project($rows) as $id => $row) {
                $groups['all'][] = $id;
                $group = match ($row['stage']) {
                    'new', 'group', 'supplement' => 'remind',
                    'verification' => 'verification', 'verified' => 'verified', default => null,
                };
                if ($group !== null) {
                    $groups[$group][] = $id;
                }
            }
        });

        return $groups;
    }

    public function project($customers): array
    {
        $ids = $customers->modelKeys();
        $states = DB::table('submission_verification_states')->whereIn('submission_id', $ids)->get()->keyBy('submission_id');
        $audits = DB::table('submission_verification_audits')->whereIn('submission_id', $ids)->where('action', 'group')
            ->orderBy('id')->get(['submission_id', 'new_values'])->keyBy('submission_id');
        $result = [];
        foreach ($customers as $customer) {
            $state = $states->get($customer->id);
            $progress = app(SubmissionVerification::class)->evidencedProgressFromAudit($customer, $state, $audits->get($customer->id)?->new_values);
            $stage = $this->stage($customer, $state, $progress);
            $result[$customer->id] = ['stage' => $stage,
                'label' => DealerRegistrationReport::STAGES[$stage] ?? $customer->status->label(),
                'note' => $stage === 'supplement' ? (SubmissionVerification::REASONS[$state?->supplement_reason ?? ''] ?? null) : null,
            ];
        }

        return $result;
    }

    private function stage(CustomerSubmission $customer, ?object $state, ?string $progress): string
    {
        if ($customer->status->value === 'approved') {
            return 'verified';
        }
        if ($customer->status->value === 'rejected') {
            return 'rejected';
        }
        if (($state->supplement_state ?? null) === 'open') {
            return 'supplement';
        }
        if (($state->supplement_state ?? null) === 'submitted'
            || isset(SubmissionVerification::BLOCKERS[$state->blocker_code ?? '']) || $progress === 'member') {
            return 'verification';
        }

        return in_array($progress, ['not_requested', 'requested'], true) ? 'group' : 'new';
    }
}

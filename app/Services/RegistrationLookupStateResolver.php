<?php

namespace App\Services;

use App\Models\CustomerSubmission;
use App\Models\Dealer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class RegistrationLookupStateResolver
{
    public function resolve(int $id, array $summary, Request $request, Dealer $dealer): array
    {
        // A rolling/local upgrade may not yet have the additive verification schema.
        // Existence lookup remains usable, but cannot claim any workflow evidence.
        if (! Schema::hasTable('submission_verification_states')
            || ! Schema::hasTable('submission_verification_audits')) {
            Log::warning('Lookup verification schema unavailable. Apply the verification workflow migration.');

            return $summary;
        }
        $submission = CustomerSubmission::findOrFail($id);
        $service = app(SubmissionVerification::class);
        $state = $service->state($id);
        if (! $state) {
            return $summary;
        }
        $owns = $service->owns($request, $dealer, $id);
        $progress = $service->evidencedProgress($submission, $state);
        if ($state->supplement_state === 'open') {
            $summary['kind'] = 'supplement';
            $summary['group_confirmed'] = in_array($progress, ['requested', 'member'], true);
            $summary['reason'] = SubmissionVerification::REASONS[$state->supplement_reason] ?? SubmissionVerification::REASONS['missing_information'];
            if ($owns && $dealer->is_active && $submission->dealer_id === $dealer->id
                && $submission->status->value === 'pending' && $state->supplement_reason === 'facebook_unavailable') {
                $summary['supplement_key'] = $state->supplement_key;
                $summary['verification_version'] = $service->version($submission, $state);
            }

            return $summary;
        }
        if ($state->supplement_state === 'submitted' && $owns) {
            return array_replace($summary, ['kind' => 'supplemented']);
        }
        if (isset(SubmissionVerification::BLOCKERS[$state->blocker_code])) {
            return array_replace($summary, ['kind' => 'blocked', 'reason' => SubmissionVerification::BLOCKERS[$state->blocker_code],
                'group_confirmed' => in_array($progress, ['requested', 'member'], true)]);
        }
        // Group evidence and moderation are independent; do not label a rejected registration an official member.
        if ($submission->status->value === 'rejected') {
            return $summary;
        }

        return array_replace($summary, ['kind' => match ($progress) {
            'member' => 'member', 'requested' => 'requested', 'not_requested' => 'not_requested', default => 'found',
        }]);
    }
}

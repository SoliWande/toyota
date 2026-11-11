<?php

namespace App\Services;

use App\Models\CustomerSubmission;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AdminGroupLookup
{
    public function find(string $phone, bool $lock = false): array
    {
        $id = null;
        $query = CustomerSubmission::select(['id', 'phone', 'phone_normalized']);
        if ($lock) {
            $query->lockForUpdate();
        }
        // Canonical exact comparison includes historical raw formats and rejected records.
        // Conflicting aliases are ambiguous even when only one record matches.
        foreach ($query->lazyById(500) as $row) {
            $aliases = array_values(array_unique(array_filter(array_map(RegistrationPhone::normalize(...), [$row->phone ?? '', $row->phone_normalized ?? '']))));
            if (! in_array($phone, $aliases, true)) {
                continue;
            }
            if (count($aliases) !== 1 || $id !== null) {
                return ['kind' => 'ambiguous'];
            }
            $id = $row->id;
        }

        return $id === null ? ['kind' => 'not_found'] : ['kind' => 'found', 'id' => $id];
    }

    public function profileUrl(CustomerSubmission $submission): ?string
    {
        try {
            $url = app(FacebookUrlNormalizer::class)->normalize($submission->facebook_url ?? '');

            return $url === $submission->facebook_url_normalized ? $url : null;
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function recent(SubmissionVerification $verification): array
    {
        $events = DB::table('submission_verification_audits')->where('actor_type', 'admin')
            ->whereIn('action', ['group', 'supplement'])->orderByDesc('id')->limit(50)->get();
        $customers = CustomerSubmission::whereIn('id', $events->pluck('submission_id'))->get()->keyBy('id');
        $rows = [];
        foreach ($events as $event) {
            $submission = $customers->get($event->submission_id);
            $values = json_decode($event->new_values, true, flags: JSON_THROW_ON_ERROR);
            if (! $submission) {
                continue;
            }
            $member = $event->action === 'group' && ($values['group_progress'] ?? null) === 'member'
                && $verification->evidencedProgress($submission, $verification->state($submission->id)) === 'member'
                && $verification->evidencedProgressFromAudit($submission, (object) ['group_progress' => 'member'], $event->new_values) === 'member';
            $supplement = $event->action === 'supplement' && ($values['supplement_state'] ?? null) === 'open';
            if ($member || $supplement) {
                $rows[] = ['name' => $submission->customer_name, 'member' => $member, 'at' => $event->created_at];
            }
            if (count($rows) === 2) {
                break;
            }
        }

        return $rows;
    }
}

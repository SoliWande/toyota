<?php

namespace App\Services;

final class AdminSubmissionQueue
{
    public const TABS = ['pending' => 'Chờ xác minh', 'supplement' => 'Cần bổ sung',
        'group' => 'Chờ vào group', 'approved' => 'Đã xác minh', 'rejected' => 'Từ chối'];

    public function groups($query): array
    {
        $groups = array_fill_keys(array_keys(self::TABS), []);
        // Reuse the existing profile-bound evidence projection; no SQL guesses from raw group_progress.
        (clone $query)->select(['customer_submissions.id', 'status', 'facebook_url_normalized'])->chunkById(250, function ($rows) use (&$groups) {
            foreach (app(SalesCustomerList::class)->project($rows) as $id => $row) {
                $stage = $row['stage'];
                if ($stage === 'verified' || $stage === 'rejected') {
                    $groups[$stage === 'verified' ? 'approved' : 'rejected'][] = $id;
                } else {
                    // C01 pending remains the full moderation queue; workflow subsets may overlap it.
                    $groups['pending'][] = $id;
                    if (in_array($stage, ['supplement', 'group'], true)) {
                        $groups[$stage][] = $id;
                    }
                }
            }
        }, 'customer_submissions.id', 'id');

        return $groups;
    }
}

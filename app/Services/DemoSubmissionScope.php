<?php

namespace App\Services;

use LogicException;

/** Applied to metrics only, never authorization, review queues or identity occupancy. */
final class DemoSubmissionScope
{
    public function apply($query, string $idColumn = 'customer_submissions.id'): mixed
    {
        if (config('realistic_demo.retired')) {
            app(DemoDataGuard::class)->assertRetired();

            return $query;
        }
        if (config('realistic_demo.include_in_metrics')) {
            if (! config('realistic_demo.enabled')
                || (! app()->environment(['local', 'testing'])
                    && ! (app()->environment('production') && config('realistic_demo.allow_production')))) {
                throw new LogicException('Demo metrics require an explicitly enabled demo environment.');
            }

            return $query;
        }

        $ids = app(ToyotaRealisticDemo::class)->ownedSubmissionIds();

        return $ids === [] ? $query : $query->whereNotIn($idColumn, $ids);
    }
}

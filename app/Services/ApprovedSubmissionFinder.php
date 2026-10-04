<?php

namespace App\Services;

use App\Models\CustomerSubmission;

class ApprovedSubmissionFinder
{
    public function __construct(private FacebookUrlNormalizer $normalizer) {}

    public function find(string $facebookUrl): ?CustomerSubmission
    {
        return CustomerSubmission::with(['sales', 'dealer'])
            ->where('approved_facebook_identity', $this->normalizer->normalize($facebookUrl))
            ->first();
    }
}

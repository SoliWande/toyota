<?php

namespace App\Services\CallAudit;

use RuntimeException;

final class FreezeBlocked extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}

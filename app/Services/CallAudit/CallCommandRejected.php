<?php

namespace App\Services\CallAudit;

use RuntimeException;

final class CallCommandRejected extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $status = 409)
    {
        parent::__construct($reason);
    }
}

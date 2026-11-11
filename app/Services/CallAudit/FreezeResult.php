<?php

namespace App\Services\CallAudit;

use JsonSerializable;

final readonly class FreezeResult implements JsonSerializable
{
    public function __construct(public array $summary) {}

    public static function blocked(string $code): self
    {
        return new self(['status' => 'BLOCKED', 'code' => $code]);
    }

    public function jsonSerialize(): array
    {
        return $this->summary;
    }
}

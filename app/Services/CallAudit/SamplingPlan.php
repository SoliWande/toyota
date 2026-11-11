<?php

namespace App\Services\CallAudit;

use JsonSerializable;

final readonly class SamplingPlan implements JsonSerializable
{
    public function __construct(
        public array $summary,
        private ?array $manifestData = null,
        private ?string $seedBytes = null,
        private array $selection = [],
    ) {}

    /** Private backend material: A3 must encrypt before persistence; never return through an API. */
    public function manifest(): ?array
    {
        return $this->manifestData;
    }

    public function seed(): ?string
    {
        return $this->seedBytes;
    }

    public function selected(): array
    {
        return $this->selection;
    }

    public function jsonSerialize(): array
    {
        return $this->summary;
    }
}

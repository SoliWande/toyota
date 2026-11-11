<?php

namespace App\Services\CallAudit;

use InvalidArgumentException;

final class SamplingAlgorithm
{
    public const VERSION = 'hmac_sha256_sort_v1';

    public function __construct(private ManifestCodec $codec) {}

    public function newSeed(): string
    {
        return random_bytes(32);
    }

    /** @return array Ordered candidates, without replacement. Domain includes purpose/scope/generation/stratum. */
    public function select(array $candidates, int $requested, string $seed, array $domain): array
    {
        if (strlen($seed) !== 32 || $requested < 0) {
            throw new InvalidArgumentException('Sampling requires a 32-byte seed and nonnegative requested count.');
        }
        $ranked = [];
        foreach ($candidates as $candidate) {
            $id = $candidate['submission_id'] ?? null;
            if (! is_int($id) || $id < 1 || isset($ranked[$id])) {
                throw new InvalidArgumentException('Each sampling unit must have one unique positive submission ID.');
            }
            $ranked[$id] = ['candidate' => $candidate, 'score' => hash_hmac('sha256', $this->codec->encode([
                'algorithm' => self::VERSION, 'domain' => $domain, 'submission_id' => $id,
            ]), $seed, true)];
        }
        usort($ranked, fn ($a, $b) => strcmp($a['score'], $b['score'])
            ?: $a['candidate']['submission_id'] <=> $b['candidate']['submission_id']);

        return array_column(array_slice($ranked, 0, min($requested, count($ranked))), 'candidate');
    }
}

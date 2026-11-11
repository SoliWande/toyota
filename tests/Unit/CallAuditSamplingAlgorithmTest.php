<?php

namespace Tests\Unit;

use App\Services\CallAudit\ManifestCodec;
use App\Services\CallAudit\PhoneEligibility;
use App\Services\CallAudit\SamplingAlgorithm;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CallAuditSamplingAlgorithmTest extends TestCase
{
    #[DataProvider('phones')]
    public function test_phone_normalization_validity_and_alias_consistency(?string $raw, ?string $alias, string $status, ?string $canonical): void
    {
        $result = (new PhoneEligibility)->inspect($raw, $alias);
        $this->assertSame($status, $result['status']);
        $this->assertSame($canonical, $result['phone']);
    }

    public static function phones(): array
    {
        return [
            ['+84 (901) 234-567', '0901234567', 'READY', '0901234567'],
            ['0084 901 234 567', null, 'READY', '0901234567'],
            [null, '0901234567', 'READY', '0901234567'],
            ['0901234567', '', 'READY', '0901234567'],
            ['01234567890', null, 'READY', '01234567890'],
            [null, null, 'EXCLUDED', null], ['1234', null, 'EXCLUDED', null],
            ['0012345678', null, 'EXCLUDED', null], ['0901234567x', null, 'EXCLUDED', null],
            ['0901234567', '0901234568', 'BLOCKED', null],
            ['0901234567', 'invalid', 'BLOCKED', null],
        ];
    }

    public function test_canonical_encoding_sorts_object_keys_preserves_lists_and_has_no_ambiguous_delimiters(): void
    {
        $codec = new ManifestCodec;
        $this->assertSame('{"a":{"x":1,"y":2},"z":[2,1]}', $codec->encode(['z' => [2, 1], 'a' => ['y' => 2, 'x' => 1]]));
        $this->assertSame($codec->digest(['b' => 2, 'a' => 1]), $codec->digest(['a' => 1, 'b' => 2]));
        $this->assertNotSame($codec->digest([1, 2]), $codec->digest([2, 1]));
        $this->assertNotSame($codec->digest(['a|b', 'c']), $codec->digest(['a', 'b|c']));
        $this->expectException(InvalidArgumentException::class);
        $codec->encode(['not_versioned_float' => 1.5]);
    }

    public function test_fixed_vector_matches_independent_python_hmac_reference_and_is_input_order_independent(): void
    {
        $algorithm = new SamplingAlgorithm(new ManifestCodec);
        $seed = pack('C*', ...range(0, 31));
        $pool = $this->pool();
        $expected = [2, 7, 1, 5, 4, 8, 3, 6];
        $this->assertSame($expected, array_column($algorithm->select($pool, 8, $seed, $this->domain()), 'submission_id'));
        $this->assertSame(array_slice($expected, 0, 3), array_column($algorithm->select(array_reverse($pool), 3, $seed, $this->domain()), 'submission_id'));
    }

    public function test_sparse_numeric_arrays_cannot_be_misread_as_canonical_objects(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ManifestCodec)->encode(['ambiguous_map' => [2 => 'value']]);
    }

    public function test_underfill_empty_zero_request_and_sampling_without_replacement(): void
    {
        $algorithm = new SamplingAlgorithm(new ManifestCodec);
        $seed = str_repeat('s', 32);
        $this->assertSame([], $algorithm->select([], 60, $seed, $this->domain()));
        $this->assertSame([], $algorithm->select($this->pool(), 0, $seed, $this->domain()));
        $selection = $algorithm->select($this->pool(), 60, $seed, $this->domain());
        $this->assertCount(8, $selection);
        $this->assertCount(8, array_unique(array_column($selection, 'submission_id')));
    }

    public function test_domains_are_separated_and_dealer_attributes_cannot_weight_general_sampling(): void
    {
        $algorithm = new SamplingAlgorithm(new ManifestCodec);
        $seed = str_repeat('d', 32);
        $base = $algorithm->select($this->pool(), 8, $seed, $this->domain());
        foreach ([['purpose' => 'award_top3'], ['stratum' => 'dealer:4'], ['generation' => 2]] as $change) {
            $other = $algorithm->select($this->pool(), 8, $seed, array_replace($this->domain(), $change));
            $this->assertNotSame(array_column($base, 'submission_id'), array_column($other, 'submission_id'));
        }
        $renamed = array_map(fn ($row) => $row + ['dealer_id' => 500], $this->pool());
        $this->assertSame(array_column($base, 'submission_id'), array_column($algorithm->select($renamed, 8, $seed, $this->domain()), 'submission_id'));
    }

    public function test_fixed_seed_sweep_has_no_missing_units_or_systematic_id_preference(): void
    {
        $algorithm = new SamplingAlgorithm(new ManifestCodec);
        $counts = array_fill(1, 8, 0);
        for ($trial = 0; $trial < 512; $trial++) {
            $selected = $algorithm->select($this->pool(), 2, hash('sha256', 'audit-trial-'.$trial, true), $this->domain());
            $this->assertCount(2, array_unique(array_column($selected, 'submission_id')));
            foreach ($selected as $row) {
                $counts[$row['submission_id']]++;
            }
        }
        foreach ($counts as $count) {
            $this->assertGreaterThan(64, $count);
            $this->assertLessThan(192, $count);
        }
    }

    public function test_csprng_seed_is_32_bytes_and_not_a_fixed_timestamp_or_constant(): void
    {
        $algorithm = new SamplingAlgorithm(new ManifestCodec);
        $first = $algorithm->newSeed();
        $this->assertSame(32, strlen($first));
        $this->assertNotSame($first, $algorithm->newSeed());
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_seed_request_or_duplicate_units_are_rejected(array $pool, int $requested, string $seed): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SamplingAlgorithm(new ManifestCodec))->select($pool, $requested, $seed, $this->domain());
    }

    public static function invalidInputs(): array
    {
        return [
            [[], 1, 'short'], [[], -1, str_repeat('s', 32)],
            [[['submission_id' => 1], ['submission_id' => 1]], 2, str_repeat('s', 32)],
            [[['submission_id' => 0]], 1, str_repeat('s', 32)],
            [[['submission_id' => '1']], 1, str_repeat('s', 32)],
        ];
    }

    private function pool(): array
    {
        return array_map(fn ($id) => ['submission_id' => $id], range(1, 8));
    }

    private function domain(): array
    {
        return ['purpose' => 'general_audit', 'monthly_scope_key' => '2026-10', 'generation' => 1, 'stratum' => 'all'];
    }
}

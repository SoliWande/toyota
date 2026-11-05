<?php

namespace App\Services;

use App\Models\Dealer;
use Illuminate\Support\Str;

/** Options from active Dealers; never infer location from addresses or snapshots. */
class DealerDirectoryProvinces
{
    public function normalize(string $province): string
    {
        return Str::squish($province);
    }

    /** Display label => original DB variants for a bound WHERE IN. */
    public function groups(): array
    {
        $groups = [];
        $labels = [];
        foreach (Dealer::where('is_active', true)->whereNotNull('province')->where('province', '!=', '')
            ->distinct()->orderBy('province')->pluck('province') as $raw) {
            $label = $this->normalize($raw);
            if ($label === '' || ! preg_match('/\p{L}/u', $label)) {
                continue;
            }
            $key = mb_strtolower($label);
            $canonical = $labels[$key] ??= $label;
            $groups[$canonical][] = $raw;
        }
        uksort($groups, fn ($a, $b) => strnatcasecmp(Str::ascii($a), Str::ascii($b)) ?: strcmp($a, $b));

        return $groups;
    }

    public function selected(string $input, array $groups): string
    {
        $normalized = $this->normalize($input);
        foreach (array_keys($groups) as $label) {
            if (mb_strtolower($label) === mb_strtolower($normalized)) {
                return $label;
            }
        }

        return $normalized;
    }
}

<?php

namespace App\Services\CallAudit;

use InvalidArgumentException;

final class ManifestCodec
{
    public const VERSION = 'call_audit_manifest_v1';

    public function encode(array $value): string
    {
        return json_encode($this->canonical($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function digest(array $value): string
    {
        return hash('sha256', $this->encode($value));
    }

    private function canonical(mixed $value): mixed
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                foreach (array_keys($value) as $key) {
                    if (! is_string($key)) {
                        throw new InvalidArgumentException('Manifest objects require string keys; numeric arrays must be lists.');
                    }
                }
                ksort($value, SORT_STRING);
            }

            return array_map($this->canonical(...), $value);
        }
        if ($value !== null && ! is_string($value) && ! is_int($value) && ! is_bool($value)) {
            throw new InvalidArgumentException('Manifest v1 only accepts arrays, strings, integers, booleans and null.');
        }

        return $value;
    }
}

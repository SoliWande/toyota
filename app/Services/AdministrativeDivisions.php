<?php

namespace App\Services;

class AdministrativeDivisions
{
    private array $provinces;

    public function __construct()
    {
        $snapshot = json_decode(file_get_contents(database_path('data/vietnam_administrative_units.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->provinces = array_column($snapshot['provinces'], null, 'code');
    }

    public function provinces(): array
    {
        return array_values(array_map(fn ($province) => ['code' => $province['code'], 'name' => $province['name']], $this->provinces));
    }

    public function province(string $code): ?array
    {
        $province = $this->provinces[$code] ?? null;

        return $province ? ['code' => $province['code'], 'name' => $province['name']] : null;
    }

    public function wards(string $provinceCode): array
    {
        return $this->provinces[$provinceCode]['wards'] ?? [];
    }

    public function ward(string $provinceCode, string $wardCode): ?array
    {
        foreach ($this->wards($provinceCode) as $ward) {
            if ($ward['code'] === $wardCode) {
                return $ward;
            }
        }

        return null;
    }
}

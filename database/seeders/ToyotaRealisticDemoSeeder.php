<?php

namespace Database\Seeders;

use App\Services\ToyotaRealisticDemo;
use Illuminate\Database\Seeder;
use LogicException;

class ToyotaRealisticDemoSeeder extends Seeder
{
    public function run(): void
    {
        // db:seed --force must never bypass the dedicated confirmation workflow.
        if (! app(ToyotaRealisticDemo::class)->confirmed) {
            throw new LogicException('Use php artisan toyota:demo seed and confirm the database name.');
        }
        app(ToyotaRealisticDemo::class)->seed();
    }
}

<?php

namespace Database\Seeders;

use App\Models\Dealer;
use Illuminate\Database\Seeder;
use LogicException;

class DevelopmentDealerSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demo dealer seeding is restricted to local/testing.');
        }

        Dealer::firstOrCreate(['code' => 'DEV-TOYOTA'], ['name' => 'Development dealer (demo)']);
    }
}

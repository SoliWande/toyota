<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ToyotaDealerSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call([
                DevelopmentAdminSeeder::class,
                DevelopmentDealerSeeder::class,
            ]);
        }
    }
}

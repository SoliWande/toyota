<?php

namespace Tests\Unit;

use App\Models\Dealer;
use App\Services\ToyotaRealisticDemo;
use Database\Seeders\ToyotaRealisticDemoSeeder;
use LogicException;
use ReflectionMethod;
use Tests\TestCase;

class ToyotaRealisticDemoSafetyTest extends TestCase
{
    public function test_direct_seed_is_blocked_without_confirmation_even_when_enabled(): void
    {
        config(['realistic_demo.enabled' => true, 'realistic_demo.allow_production' => true]);
        $this->expectException(LogicException::class);
        app(ToyotaRealisticDemoSeeder::class)->run();
    }

    public function test_production_is_blocked_by_default_without_opening_a_connection(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config(['realistic_demo.enabled' => true, 'realistic_demo.allow_production' => false]);
        $this->expectException(LogicException::class);
        app(ToyotaRealisticDemo::class)->assertEnabled();
    }

    public function test_production_opt_in_does_not_change_environment(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config(['realistic_demo.enabled' => true, 'realistic_demo.allow_production' => true]);
        app(ToyotaRealisticDemo::class)->assertEnabled();
        $this->assertTrue(app()->environment('production'));
    }

    public function test_directory_has_enough_geographically_valid_demo_locations(): void
    {
        $snapshot = json_decode(file_get_contents(database_path('data/toyota_dealers.json')), true, flags: JSON_THROW_ON_ERROR);
        $demo = app(ToyotaRealisticDemo::class);
        $method = new ReflectionMethod($demo, 'province');
        $matched = 0;
        foreach ($snapshot['dealers'] as $row) {
            $dealer = new Dealer(['province' => $row['province']]);
            if ($method->invoke($demo, $dealer) !== null) {
                $matched++;
            }
        }
        $this->assertCount(87, $snapshot['dealers']);
        $this->assertGreaterThanOrEqual(25, $matched);
    }
}

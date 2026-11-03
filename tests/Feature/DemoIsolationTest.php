<?php

namespace Tests\Feature;

use App\Enums\LeaderboardPeriod;
use App\Models\Award;
use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\AwardResults;
use App\Services\DealerDashboardData;
use App\Services\LeaderboardService;
use App\Services\SalesPublicProfile;
use App\Services\SubmissionIntake;
use App\Services\ToyotaRealisticDemo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class DemoIsolationTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginFixtureTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        TestDatabase::assertIsolated();
        $this->assertSame('toyota_testing', DB::selectOne('SELECT DATABASE() AS n')->n);
        $this->beginFixtureTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['realistic_demo.include_in_metrics' => false, 'realistic_demo.enabled' => false]);
    }

    /** Small signed registry fixture; unused IDs stand for the remaining dataset members. */
    private function manifest(CustomerSubmission $demo): User
    {
        $admin = User::factory()->admin()->create(['email' => ToyotaRealisticDemo::EMAIL]);
        $ids = [$demo->id, ...range(900000001, 900001499)];
        $envelope = ['dataset' => ToyotaRealisticDemo::TAG, 'database' => DB::connection()->getDatabaseName(),
            'admin' => $admin->id, 'submissions' => $ids];
        $admin->forceFill(['admin_note' => json_encode(['tag' => ToyotaRealisticDemo::TAG,
            'users' => [$admin->id], 'submissions' => $ids,
            'ownership' => Crypt::encryptString(json_encode($envelope, JSON_THROW_ON_ERROR)),
        ], JSON_THROW_ON_ERROR)])->save();

        return $admin;
    }

    public function test_verified_demo_is_excluded_consistently_without_modifying_real_or_demo_rows(): void
    {
        $sales = User::factory()->active()->create();
        $demo = CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        // Same Sales/Dealer and arbitrary demo label: this real row must still count.
        $real = CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'notes' => ToyotaRealisticDemo::TAG]);
        $admin = $this->manifest($demo);
        $before = [$demo->refresh()->getRawOriginal(), $real->refresh()->getRawOriginal()];
        $leaderboard = app(LeaderboardService::class);
        foreach (LeaderboardPeriod::cases() as $period) {
            $this->assertSame(1, (int) $leaderboard->sales($period)->first()->score);
            $this->assertSame(1, (int) $leaderboard->dealers($period)->first()->score);
        }
        $profile = app(SalesPublicProfile::class)->data($sales->load('dealer'));
        $this->assertSame(1, $profile['approvedTotal']);
        $this->assertSame(1, $profile['months'][0]['count']);
        $standings = app(DealerDashboardData::class)->standings($sales->dealer, $leaderboard);
        $this->assertSame(1, (int) $standings['all']->score);
        $preview = app(AwardResults::class)->preview(Award::factory()->create());
        $this->assertSame(1, $preview['sales']->first()['score']);
        $this->assertSame(1, $preview['dealers']->first()['score']);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['approved_submissions'] === 1 && $stats['submissions'] === 1);
        $this->get(route('admin.dealers.show', $sales->dealer))->assertOk()->assertViewHas('approvedCustomers', 1);
        $this->actingAs($sales)->get(route('sales.dashboard'))->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['approved'] === 1 && $stats['total'] === 1);
        $manager = User::factory()->dealer()->create(['dealer_id' => $sales->dealer_id]);
        $this->actingAs($manager)->get(route('dealer.dashboard'))->assertOk()->assertViewHas('customerCount', 1);
        // Operational records remain visible; filtering metrics must not hide review/history.
        $this->assertSame(2, app(DealerDashboardData::class)->customers($sales->dealer)->count());
        $this->get(route('dealers.profile', $sales->dealer->slug))->assertOk()
            ->assertViewHas('standing', fn ($standing) => (int) $standing->score === 1);
        $this->assertTrue($before === [$demo->fresh()->getRawOriginal(), $real->fresh()->getRawOriginal()]);
    }

    public function test_demo_mode_requires_explicit_enablement_and_restores_demo_metrics(): void
    {
        $record = CustomerSubmission::factory()->approved()->create();
        $this->manifest($record);
        config(['realistic_demo.include_in_metrics' => true, 'realistic_demo.enabled' => true]);
        $this->assertSame(1, (int) app(LeaderboardService::class)->dealerScores()->first()->score);
        config(['realistic_demo.enabled' => false]);
        $this->expectException(LogicException::class);
        app(LeaderboardService::class)->dealerScores()->count();
    }

    public function test_editing_demo_content_does_not_change_authenticated_ownership(): void
    {
        $record = CustomerSubmission::factory()->approved()->create();
        $this->manifest($record);
        $record->update(['customer_name' => 'SYNTHETIC_EDIT', 'notes' => null]);
        $this->assertSame(0, app(LeaderboardService::class)->dealerScores()->count());
        $this->assertSame('approved', $record->fresh()->status->value);
    }

    public function test_tampered_manifest_cannot_exclude_real_data_silently(): void
    {
        $demo = CustomerSubmission::factory()->approved()->create();
        $real = CustomerSubmission::factory()->approved()->create();
        $admin = $this->manifest($demo);
        $manifest = json_decode($admin->admin_note, true);
        $manifest['submissions'][0] = $real->id;
        $admin->forceFill(['admin_note' => json_encode($manifest, JSON_THROW_ON_ERROR)])->save();
        $this->expectException(LogicException::class);
        app(LeaderboardService::class)->dealerScores()->get();
    }

    public function test_metrics_exclusion_never_releases_demo_identity_occupancy(): void
    {
        $demo = CustomerSubmission::factory()->approved()->create(['phone' => '0990000081']);
        $this->manifest($demo);
        $this->assertArrayHasKey('phone', app(SubmissionIntake::class)->conflicts($demo->phone));
        $this->assertSame(0, app(LeaderboardService::class)->dealerScores()->count());
    }

    public function test_unsigned_unverifiable_manifest_blocks_metrics_instead_of_guessing(): void
    {
        $demo = CustomerSubmission::factory()->approved()->create();
        $admin = $this->manifest($demo);
        $manifest = json_decode($admin->admin_note, true);
        unset($manifest['ownership']);
        $manifest['fingerprint'] = str_repeat('0', 64);
        $admin->forceFill(['admin_note' => json_encode($manifest, JSON_THROW_ON_ERROR)])->save();
        $this->expectException(LogicException::class);
        app(LeaderboardService::class)->dealerScores()->get();
    }

    public function test_insert_checks_normalized_phone_alias_even_when_raw_phone_is_absent(): void
    {
        $first = CustomerSubmission::factory()->create(['phone' => '0990000082']);
        $candidate = CustomerSubmission::factory()->make(['sales_id' => $first->sales_id,
            'phone' => null, 'phone_normalized' => '+84 990 000 082']);
        try {
            $candidate->saveQuietly();
            $this->fail('Normalized alias bypassed insert guard.');
        } catch (ValidationException $error) {
            $this->assertSame(['phone'], array_keys($error->errors()));
            $this->assertFalse($candidate->exists);
        }
    }

    public function test_rejected_normalized_alias_does_not_reserve_identity_on_insert(): void
    {
        $old = CustomerSubmission::factory()->rejected()->create(['phone' => null, 'phone_normalized' => '+84 990 000 083']);
        $new = CustomerSubmission::factory()->create(['phone' => '0990000083']);
        $this->assertTrue($new->exists);
        $this->assertSame('rejected', $old->fresh()->status->value);
    }

    public function test_instance_save_quietly_does_not_bypass_identity_update_guard(): void
    {
        $first = CustomerSubmission::factory()->create(['phone' => '0990000084']);
        $second = CustomerSubmission::factory()->create(['phone' => '0990000085']);
        $second->phone_normalized = $first->phone;
        try {
            $second->saveQuietly();
            $this->fail('Quiet save bypassed identity update guard.');
        } catch (ValidationException $error) {
            $this->assertSame(['phone'], array_keys($error->errors()));
            $this->assertTrue($second->fresh()->phone_normalized !== $first->phone);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use Database\Seeders\DevelopmentDealerAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class DevelopmentDealerAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    private array $exports = [];

    protected function tearDown(): void
    {
        foreach ($this->exports as $file) {
            File::delete($file);
        }
        parent::tearDown();
    }

    private function seedManagers(): DevelopmentDealerAccountSeeder
    {
        $seeder = new DevelopmentDealerAccountSeeder;
        $seeder->run();
        if ($seeder->credentialsFile !== null) {
            $this->exports[] = $seeder->credentialsFile;
        }

        return $seeder;
    }

    public function test_seeder_creates_unique_random_credentials_and_one_manager_per_existing_dealer(): void
    {
        $first = Dealer::factory()->create();
        $second = Dealer::factory()->create();
        $seeder = $this->seedManagers();
        $this->assertSame(2, User::where('role', UserRole::Dealer)->count());
        $this->assertSame(2, Dealer::count());
        $export = json_decode(File::get($seeder->credentialsFile), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(route('login'), $export['login_url']);
        $this->assertCount(2, $export['accounts']);
        $this->assertNotSame($export['accounts'][0]['password'], $export['accounts'][1]['password']);
        foreach ($export['accounts'] as $row) {
            $manager = User::where('email', $row['email'])->sole();
            $this->assertSame(UserRole::Dealer, $manager->role);
            $this->assertSame(UserStatus::Active, $manager->status);
            $this->assertSame($row['dealer_id'], $manager->dealer_id);
            $this->assertGreaterThanOrEqual(32, strlen($row['password']));
            $this->assertTrue(Hash::check($row['password'], $manager->password));
            $this->assertNull($manager->public_slug);
        }
        $this->post(route('login'), ['email' => $export['accounts'][0]['email'], 'password' => $export['accounts'][0]['password']])
            ->assertSessionHasNoErrors()->assertRedirect(route('dealer.dashboard'));
        $this->get(route('dealer.dashboard'))->assertOk();
        $this->assertSame(1, $first->manager()->count());
        $this->assertSame(1, $second->manager()->count());
    }

    public function test_rerun_preserves_existing_accounts_passwords_status_and_export(): void
    {
        $manager = User::factory()->dealer()->create(['status' => UserStatus::Blocked]);
        $before = $manager->refresh()->getRawOriginal();
        $seeder = $this->seedManagers();
        $this->assertNull($seeder->credentialsFile);
        $this->assertSame($before, $manager->fresh()->getRawOriginal());
        $other = Dealer::factory()->create();
        $firstRun = $this->seedManagers();
        $contents = File::get($firstRun->credentialsFile);
        $hash = $other->manager()->sole()->password;
        $this->assertNull($this->seedManagers()->credentialsFile);
        $this->assertSame($hash, $other->manager()->sole()->password);
        $this->assertSame($contents, File::get($firstRun->credentialsFile));
    }

    public function test_existing_sales_and_submission_history_are_not_modified(): void
    {
        $submission = CustomerSubmission::factory()->approved()->create();
        $sales = $submission->sales;
        $beforeSales = $sales->refresh()->getRawOriginal();
        $beforeSubmission = $submission->refresh()->getRawOriginal();
        $this->seedManagers();
        $this->assertSame($beforeSales, $sales->fresh()->getRawOriginal());
        $this->assertSame($beforeSubmission, $submission->fresh()->getRawOriginal());
        $this->assertSame(1, $sales->dealer->manager()->count());
    }

    public function test_inactive_dealer_gets_a_blocked_manager(): void
    {
        $dealer = Dealer::factory()->create(['is_active' => false]);
        $this->seedManagers();
        $this->assertSame(UserStatus::Blocked, $dealer->manager()->sole()->status);
    }

    public function test_conflicting_email_rolls_back_every_new_manager(): void
    {
        Dealer::factory()->create();
        $second = Dealer::factory()->create();
        User::factory()->active()->create(['dealer_id' => $second->id, 'email' => 'demo.dealer.'.$second->id.'@example.test']);
        try {
            $this->seedManagers();
            $this->fail('A conflicting reserved email must stop seeding.');
        } catch (LogicException) {
            $this->assertSame(0, User::where('role', UserRole::Dealer)->count());
        }
    }

    public function test_seeder_refuses_production(): void
    {
        app()->detectEnvironment(fn () => 'production');
        $this->expectException(LogicException::class);
        $this->seedManagers();
    }
}

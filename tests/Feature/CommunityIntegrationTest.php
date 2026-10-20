<?php

namespace Tests\Feature;

use App\Enums\LeaderboardPeriod;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\AdministrativeDivisions;
use App\Services\DealerProfile;
use App\Services\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class CommunityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user, string $password): void
    {
        $this->post(route('login'), ['email' => $user->email, 'password' => $password])
            ->assertSessionHasNoErrors()->assertRedirect(route($user->homeRoute()));
        $this->assertAuthenticatedAs($user);
    }

    private function logout(): void
    {
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    private function salesCustomer(string $identity, string $name): CustomerSubmission
    {
        $this->post(route('sales.submissions.store'), [
            'customer_name' => $name, 'facebook_url' => 'https://facebook.com/'.$identity,
            'customer_phone' => '0987654321', 'note' => 'IT_PRIVATE_NOTE',
            'vehicle_model' => 'hilux', 'first_registration_year' => 2024, 'vehicle_color' => 'Trắng',
            'license_plate' => '51A-123.45', 'evidence_image' => UploadedFile::fake()->image('private-evidence.png'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        return CustomerSubmission::where('customer_name', $name)->sole();
    }

    private function dealerScore(Dealer $dealer): int
    {
        return (int) (app(LeaderboardService::class)->dealers(LeaderboardPeriod::AllTime)->get()->firstWhere('id', $dealer->id)?->score ?? 0);
    }

    public function test_qr_dealer_admin_sales_avatar_public_profile_and_transfer_work_together(): void
    {
        TestDatabase::assertIsolated();
        Storage::fake('sales_avatars');
        Storage::fake('submission_evidence');
        $this->travelTo(now()->setDate(2025, 11, 20)->setTime(10, 0));
        $password = Str::random(32);
        $first = User::factory()->dealer()->create(['password' => $password]);
        $second = User::factory()->dealer()->create(['password' => $password]);
        $admin = User::factory()->admin()->create(['password' => $password]);
        $sales = User::factory()->active()->create(['dealer_id' => $first->dealer_id, 'password' => $password,
            'name' => 'IT_PUBLIC_SALES_NAME', 'phone' => '0901234567']);
        $originalDealer = $first->dealer;
        $targetDealer = $second->dealer;
        $salesUrl = route('sales.public.show', ['slug' => $sales->public_slug]);

        // A real prior-month Sales submission; Admin approves it in a different month.
        $this->login($sales, $password);
        $old = $this->salesCustomer('integration.old.customer', 'IT_PRIVATE_OLD_CUSTOMER');
        $this->logout();
        $this->travelTo(now()->setDate(2026, 1, 15));
        $this->login($admin, $password);
        $this->post(route('admin.submissions.approve', $old))->assertSessionHasNoErrors();
        $this->logout();
        $this->assertSame(1, $this->dealerScore($originalDealer));

        $url = app(DealerProfile::class)->url($originalDealer);
        $qr = $this->get(route('dealers.qr', ['dealer' => $originalDealer, 'download' => 1]))
            ->assertOk()->assertHeader('Content-Type', 'image/png')->getContent();
        $this->get($url)->assertOk()->assertViewHas('dealer', fn ($dealer) => $dealer->id === $originalDealer->id)
            ->assertSee('name="vehicle_description"', false)->assertDontSee($sales->name);
        $directory = app(AdministrativeDivisions::class);
        $province = $directory->provinces()[0];
        $data = ['customer_name' => 'IT_PRIVATE_QR_APPROVE', 'phone' => '0912345678', 'address_line' => 'IT_PRIVATE_QR_ADDRESS',
            'province_code' => $province['code'], 'ward_code' => $directory->wards($province['code'])[0]['code'],
            'vehicle_description' => 'IT_PRIVATE_QR_VEHICLE', 'consent' => '1'];
        $postUrl = route('dealers.registrations.store', ['slug' => $originalDealer->slug]);
        $this->post($postUrl, $data)->assertSessionHasNoErrors()->assertRedirect(route('dealers.profile', ['slug' => $originalDealer->slug]).'#registration');
        $pending = CustomerSubmission::where('customer_name', $data['customer_name'])->sole();
        $this->assertSame($originalDealer->id, $pending->dealer_id);
        $this->assertSame('pending', $pending->status->value);
        $this->assertSame('dealer_qr', $pending->source);
        $this->assertNull($pending->sales_id);
        $this->assertSame($data['address_line'], $pending->address_line);
        $this->assertSame($data['vehicle_description'], $pending->vehicle_description);
        $this->assertSame(1, $this->dealerScore($originalDealer));
        $this->post($postUrl, [...$data, 'customer_name' => 'IT_PRIVATE_QR_REJECT', 'phone' => '0923456789'])->assertSessionHasNoErrors();
        $rejected = CustomerSubmission::where('customer_name', 'IT_PRIVATE_QR_REJECT')->sole();

        $this->login($first, $password);
        $this->get(route('dealer.customers.index'))->assertOk()->assertSee($pending->customer_name);
        $this->get(route('dealer.customers.show', $pending))->assertOk()->assertSee($pending->address_line);
        $this->postJson(route('admin.submissions.approve', $pending))->assertForbidden();
        $this->postJson(route('admin.submissions.reject', $pending))->assertForbidden();
        $this->logout();
        $this->login($second, $password);
        $this->get(route('dealer.customers.index'))->assertOk()->assertDontSee($pending->customer_name);
        $this->get(route('dealer.customers.show', $pending))->assertNotFound();
        $this->getJson(route('submissions.evidence', $old))->assertForbidden();
        $this->logout();

        $this->login($admin, $password);
        $this->get(route('admin.submissions.show', $pending))->assertOk()->assertSee($pending->customer_name);
        $this->postJson(route('admin.submissions.approve', $pending))->assertUnprocessable()->assertJsonValidationErrors('verified_facebook_url');
        $this->post(route('admin.submissions.approve', $pending), ['verified_facebook_url' => 'https://facebook.com/integration.qr.approved'])->assertSessionHasNoErrors();
        $this->post(route('admin.submissions.reject', $rejected), ['rejection_reason' => 'Không xác minh được đăng ký'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $pending->fresh()->status->value);
        $this->assertSame('rejected', $rejected->fresh()->status->value);
        $this->assertSame(2, $this->dealerScore($originalDealer));
        $this->get($salesUrl)->assertOk()->assertViewHas('profile', fn ($profile) => $profile['approvedTotal'] === 1);
        $this->logout();

        $this->login($sales, $password);
        $this->get(route('sales.dashboard'))->assertOk();
        $this->patch(route('profile.update'), ['name' => $sales->name, 'phone' => $sales->phone, 'avatar' => UploadedFile::fake()->image('avatar.webp')])->assertSessionHasNoErrors();
        $current = $this->salesCustomer('integration.current.customer', 'IT_PRIVATE_CURRENT_CUSTOMER');
        $this->assertSame($sales->id, $current->sales_id);
        $this->assertSame($originalDealer->id, $current->dealer_id);
        $this->get(route('submissions.evidence', $current))->assertOk();
        $this->get(route('sales.submissions.show', $pending))->assertForbidden();
        $this->logout();
        $this->get($salesUrl)->assertOk()->assertSee($sales->name)->assertSee('••• ••• 567')->assertDontSee($sales->phone)->assertDontSee($sales->email)
            ->assertViewHas('profile', fn ($profile) => $profile['approvedTotal'] === 1);
        $this->get(route('sales.public.avatar', ['slug' => $sales->public_slug]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->getJson(route('submissions.evidence', $current))->assertUnauthorized();
        $this->getJson(route('sales.avatar', $sales))->assertUnauthorized();

        $this->login($admin, $password);
        $this->post(route('admin.submissions.approve', $current))->assertSessionHasNoErrors();
        $this->patch(route('admin.sales.update', $sales), ['name' => $sales->name, 'phone' => $sales->phone, 'dealer_id' => $targetDealer->id])->assertSessionHasNoErrors();
        $this->logout();
        $this->assertSame($originalDealer->id, $old->fresh()->dealer_id);
        $this->assertSame($originalDealer->id, $current->fresh()->dealer_id);
        $this->assertSame($originalDealer->id, $pending->fresh()->dealer_id);
        $this->assertSame(3, $this->dealerScore($originalDealer));
        $this->assertSame(0, $this->dealerScore($targetDealer));
        $profile = $this->get($salesUrl)->assertOk()->assertSee($targetDealer->name)->viewData('profile');
        $this->assertSame(2, $profile['approvedTotal']);
        $this->assertSame([['label' => '01/2026', 'count' => 1], ['label' => '12/2025', 'count' => 0], ['label' => '11/2025', 'count' => 1]], $profile['months']);
        $this->assertSame($salesUrl, route('sales.public.show', ['slug' => $sales->fresh()->public_slug]));

        $this->login($first, $password);
        $this->get(route('dealer.customers.show', $current))->assertOk();
        $this->get(route('submissions.evidence', $current))->assertOk();
        $this->get(route('dealer.sales.show', $sales))->assertNotFound();
        $this->logout();
        $this->login($second, $password);
        $this->get(route('dealer.sales.show', $sales))->assertOk();
        $this->get(route('dealer.customers.show', $current))->assertNotFound();
        $this->getJson(route('submissions.evidence', $current))->assertForbidden();
        $this->logout();

        foreach ([$url, $salesUrl] as $publicUrl) {
            foreach ([false, true] as $json) {
                $response = ($json ? $this->getJson($publicUrl) : $this->get($publicUrl))->assertOk();
                foreach ([$pending->customer_name, $pending->address_line, $pending->vehicle_description, $rejected->customer_name,
                    $current->customer_name, $old->customer_name, $current->evidence_image_path, 'IT_PRIVATE_NOTE', $sales->email, $sales->phone] as $private) {
                    $response->assertDontSee($private);
                }
            }
        }
        Storage::disk('submission_evidence')->assertExists($old->evidence_image_path);
        Storage::disk('submission_evidence')->assertExists($current->evidence_image_path);
        // Real endpoint output for QR decoding in browser QA, without contacting the local application.
        $folder = base_path('.laravel-tools/integration-qa/flow');
        File::ensureDirectoryExists($folder);
        File::put($folder.'/dealer-qr.png', $qr);
        File::put($folder.'/manifest.json', json_encode(['dealerUrl' => $url, 'salesUrl' => $salesUrl,
            'dealerScore' => 3, 'salesApprovedTotal' => 2, 'salesMonths' => $profile['months']], JSON_PRETTY_PRINT));
    }

    public function test_dealer_and_sales_slug_namespaces_do_not_capture_system_routes(): void
    {
        $sales = User::factory()->active()->create();
        $sameSlugDealer = Dealer::factory()->create(['name' => $sales->public_slug]);
        $this->assertSame($sales->public_slug, $sameSlugDealer->slug);
        $this->get(app(DealerProfile::class)->url($sameSlugDealer))->assertOk()->assertViewIs('dealers.show');
        $this->get(route('sales.public.show', ['slug' => $sales->public_slug]).'/')->assertOk()->assertViewIs('sales.public-profile');
        foreach (['login', 'logout', 'sales', 'admin', 'dealer'] as $reserved) {
            $dealer = Dealer::factory()->create(['name' => $reserved]);
            $this->assertNotSame($reserved, $dealer->slug);
            $this->get(app(DealerProfile::class)->url($dealer))->assertOk()->assertViewIs('dealers.show');
        }
        $this->get('/login')->assertOk()->assertViewIs('auth.login');
        $this->get('/logout')->assertStatus(405);
        $this->post('/logout')->assertRedirect(route('login'));
        $this->get('/sales/dashboard')->assertRedirect(route('login'));
        $this->get('/sales/submissions')->assertRedirect(route('login'));
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->get('/dealer/dashboard')->assertRedirect(route('login'));
        $this->get('/sales/'.$sales->id.'/avatar')->assertRedirect(route('login'));
    }
}

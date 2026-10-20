<?php

namespace Tests\Feature;

use App\Actions\ApproveCustomerSubmission;
use App\Enums\UserStatus;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\AdministrativeDivisions;
use App\Services\LeaderboardService;
use App\Services\SalesPublicProfile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class SalesPublicProfileTest extends TestCase
{
    use RefreshDatabase;

    private function url(User $sales): string
    {
        return route('sales.public.show', ['slug' => $sales->public_slug]);
    }

    public function test_public_html_and_json_use_only_safe_projection_with_placeholder(): void
    {
        $sales = User::factory()->active()->create(['phone' => '+84 901 234 567', 'admin_note' => 'PRIVATE_ADMIN_NOTE']);
        $submission = CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'customer_name' => 'PRIVATE_CUSTOMER',
            'phone' => '0988888888', 'address_line' => 'PRIVATE_ADDRESS', 'evidence_image_path' => 'PRIVATE_EVIDENCE.jpg']);
        foreach ([false, true] as $json) {
            $response = ($json ? $this->getJson($this->url($sales)) : $this->get($this->url($sales)))->assertOk()
                ->assertSee($sales->name)->assertSee($sales->dealer->name)->assertSee('••• ••• 567')
                ->assertSee('Ảnh đại diện mặc định')->assertDontSee('data-sales-avatar')
                ->assertDontSee($sales->email)->assertDontSee($sales->phone)->assertDontSee('84901234567')
                ->assertDontSee($sales->admin_note)->assertDontSee($submission->customer_name)->assertDontSee($submission->facebook_url)
                ->assertDontSee($submission->phone)->assertDontSee($submission->address_line)->assertDontSee($submission->evidence_image_path)
                ->assertDontSee(route('submissions.evidence', $submission))->assertHeader('Cache-Control', 'no-store, private')
                ->assertViewMissing('sales')->assertViewMissing('user');
            $data = $response->viewData('profile');
            $this->assertSame(1, $data['approvedTotal']);
            foreach (['id', 'email', 'phone', 'avatar_path', 'dealer_id', 'customers', 'submissions'] as $field) {
                $this->assertArrayNotHasKey($field, $data);
            }
        }
        $this->get($this->url($sales).'?email=PRIVATE_QUERY&include=customers&phone=PRIVATE_PHONE')->assertOk()->assertDontSee('PRIVATE_QUERY')->assertDontSee('PRIVATE_PHONE');
    }

    public function test_three_months_use_submitted_at_include_zero_and_handle_year_boundary(): void
    {
        $this->travelTo(now()->setDate(2026, 1, 15));
        $sales = User::factory()->active()->create();
        foreach (['2026-01-01 00:00:00', '2025-11-30 23:59:59', '2025-10-31 23:59:59'] as $time) {
            CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => $time, 'reviewed_at' => now()]);
        }
        CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->rejected()->create(['sales_id' => $sales->id]);
        CustomerSubmission::factory()->approved()->create();
        $profile = $this->get($this->url($sales))->assertOk()->viewData('profile');
        $this->assertSame(3, $profile['approvedTotal']);
        $this->assertSame([['label' => '01/2026', 'count' => 1], ['label' => '12/2025', 'count' => 0], ['label' => '11/2025', 'count' => 1]], $profile['months']);
    }

    public function test_sales_transfer_preserves_slug_personal_totals_and_historical_dealer_scores(): void
    {
        $sales = User::factory()->active()->create();
        $oldDealer = $sales->dealer;
        $submission = CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id]);
        $url = $this->url($sales);
        $next = Dealer::factory()->create();
        $sales->forceFill(['name' => 'Updated Display Name', 'dealer_id' => $next->id])->save();
        $this->get($url)->assertOk()->assertSee($next->name)->assertViewHas('profile', fn ($data) => $data['approvedTotal'] === 1);
        $this->assertSame($oldDealer->id, $submission->fresh()->dealer_id);
        $scores = app(LeaderboardService::class)->dealerScores()->get();
        $this->assertSame(1, (int) $scores->firstWhere('id', $oldDealer->id)->score);
        $this->assertNull($scores->firstWhere('id', $next->id));
        $this->assertSame($url, $this->url($sales->fresh()));
    }

    public function test_qr_registrations_never_count_toward_sales(): void
    {
        $sales = User::factory()->active()->create();
        $directory = app(AdministrativeDivisions::class);
        $province = $directory->provinces()[0];
        $this->postJson(route('dealers.registrations.store', ['slug' => $sales->dealer->slug]), [
            'customer_name' => 'QR Customer', 'phone' => '0901234567', 'address_line' => 'Private address',
            'province_code' => $province['code'], 'ward_code' => $directory->wards($province['code'])[0]['code'],
            'vehicle_description' => 'Toyota Veloz', 'consent' => 1,
        ])->assertCreated();
        $qr = CustomerSubmission::where('source', 'dealer_qr')->sole();
        app(ApproveCustomerSubmission::class)->execute(User::factory()->admin()->create(), $qr, null, 'https://facebook.com/qr.only.customer');
        $this->get($this->url($sales))->assertOk()->assertViewHas('profile', fn ($data) => $data['approvedTotal'] === 0 && array_sum(array_column($data['months'], 'count')) === 0);
    }

    public function test_only_active_sales_have_public_profiles_and_public_avatar_routes(): void
    {
        foreach ([UserStatus::Pending, UserStatus::Blocked, UserStatus::Rejected] as $status) {
            $sales = User::factory()->create(['status' => $status]);
            $this->getJson($this->url($sales))->assertNotFound()->assertDontSee($sales->email);
            $this->get(route('sales.public.avatar', ['slug' => $sales->public_slug]))->assertNotFound();
        }
        $admin = User::factory()->admin()->create();
        $this->assertNull($admin->public_slug);
        $this->get('/sales/s-'.str_repeat('a', 32))->assertNotFound();
        $this->get('/sales/'.$admin->id)->assertNotFound();
        $this->get('/sales/dashboard')->assertRedirect(route('login'));
        $this->get('/sales/submissions')->assertRedirect(route('login'));
        $this->get('/logout')->assertStatus(405);
    }

    public static function phones(): array
    {
        return [['0901234567', '••• ••• 567'], ['+84 (901) 234 567', '••• ••• 567'], [null, 'Chưa công khai'], ['', 'Chưa công khai'], ['123', 'Chưa công khai'], ['PRIVATE_TEXT', 'Chưa công khai']];
    }

    #[DataProvider('phones')]
    public function test_phone_mask_never_exposes_full_number(?string $input, string $expected): void
    {
        $this->assertSame($expected, app(SalesPublicProfile::class)->maskPhone($input));
    }

    public static function imageTypes(): array
    {
        return [['jpg'], ['png'], ['webp']];
    }

    #[DataProvider('imageTypes')]
    public function test_uploaded_avatar_is_reencoded_and_served_publicly_by_opaque_slug(string $extension): void
    {
        Storage::fake('sales_avatars');
        $sales = User::factory()->active()->create();
        $slug = $sales->public_slug;
        $this->actingAs($sales)->patch(route('profile.update'), ['name' => $sales->name, 'avatar' => UploadedFile::fake()->image('untrusted-name.'.$extension, 800, 600)])
            ->assertSessionHasNoErrors();
        $path = $sales->fresh()->avatar_path;
        $this->assertStringNotContainsString('untrusted', $path);
        $this->assertSame([512, 384, IMAGETYPE_JPEG], array_slice(getimagesizefromstring(Storage::disk('sales_avatars')->get($path)), 0, 3));
        $this->post(route('logout'));
        $this->get(route('sales.public.avatar', ['slug' => $slug]))->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get($this->url($sales))->assertOk()->assertSee('data-sales-avatar')->assertDontSee($path);
        $this->getJson(route('sales.avatar', $sales))->assertUnauthorized();
    }

    public function test_demo_ai_avatars_are_never_used_as_public_default_identity(): void
    {
        Storage::fake('sales_avatars');
        $sales = User::factory()->active()->create(['name' => 'Renamed Demo', 'email' => 'demo.sales.01@example.test', 'avatar_path' => 'ai.jpg']);
        Storage::disk('sales_avatars')->put('ai.jpg', 'demo-image');
        $this->get($this->url($sales))->assertOk()->assertSee('Ảnh đại diện mặc định')->assertDontSee('data-sales-avatar');
        $this->get(route('sales.public.avatar', ['slug' => $sales->public_slug]))->assertNotFound();
        Storage::disk('sales_avatars')->assertExists('ai.jpg');
        // An explicit new upload is a chosen avatar, rather than an automatically generated default.
        $this->actingAs($sales)->patch(route('profile.update'), ['name' => $sales->name, 'avatar' => UploadedFile::fake()->image('chosen.png')])
            ->assertSessionHasNoErrors();
        $this->assertStringStartsWith('uploaded-', $sales->fresh()->avatar_path);
        $this->post(route('logout'));
        $this->get($this->url($sales))->assertOk()->assertSee('data-sales-avatar');
        $this->get(route('sales.public.avatar', ['slug' => $sales->public_slug]))->assertOk();
    }

    public function test_admin_can_manage_avatar_but_dealer_and_other_sales_cannot(): void
    {
        Storage::fake('sales_avatars');
        Storage::fake('submission_evidence');
        Storage::disk('submission_evidence')->put('preserve.jpg', 'private-evidence');
        $sales = User::factory()->active()->create();
        $data = ['name' => $sales->name, 'dealer_id' => $sales->dealer_id, 'avatar' => UploadedFile::fake()->image('avatar.png')];
        foreach ([User::factory()->active()->create(), User::factory()->dealer()->create(['dealer_id' => $sales->dealer_id])] as $other) {
            $this->actingAs($other)->patchJson(route('admin.sales.update', $sales), $data)->assertForbidden();
            $this->patch(route('profile.update'), ['name' => $other->name, 'user_id' => $sales->id, 'avatar' => UploadedFile::fake()->image('attack.jpg')])
                ->assertSessionHasErrors('user_id');
        }
        $this->assertNull($sales->fresh()->avatar_path);
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.sales.update', $sales), $data)->assertSessionHasNoErrors();
        $first = $sales->fresh()->avatar_path;
        Storage::disk('sales_avatars')->assertExists($first);
        $this->patch(route('admin.sales.update', $sales), [...$data, 'avatar' => UploadedFile::fake()->image('new.jpg')])->assertSessionHasNoErrors();
        Storage::disk('sales_avatars')->assertMissing($first);
        $this->assertSame('private-evidence', Storage::disk('submission_evidence')->get('preserve.jpg'));
    }

    public function test_admin_upload_validation_rejects_svg_fake_images_oversize_and_dimensions(): void
    {
        Storage::fake('sales_avatars');
        $sales = User::factory()->active()->create();
        $this->actingAs(User::factory()->admin()->create());
        foreach ([UploadedFile::fake()->createWithContent('x.svg', '<svg/>'), UploadedFile::fake()->createWithContent('bad.jpg', '<?php phpinfo();'),
            UploadedFile::fake()->image('big.png')->size(5121), UploadedFile::fake()->image('huge.jpg', 4097, 10)] as $file) {
            $this->patch(route('admin.sales.update', $sales), ['name' => $sales->name, 'dealer_id' => $sales->dealer_id, 'avatar' => $file])->assertSessionHasErrors('avatar');
        }
        $this->assertSame([], Storage::disk('sales_avatars')->allFiles());
    }

    public function test_slug_is_random_stable_and_immutable_in_model_database_and_requests(): void
    {
        $sales = User::factory()->active()->create();
        $this->assertMatchesRegularExpression('/^s-[a-f0-9]{32}$/D', $sales->public_slug);
        $this->assertNotSame($sales->public_slug, User::factory()->active()->create()->public_slug);
        $this->actingAs($sales)->patch(route('profile.update'), ['name' => 'New name', 'public_slug' => 'forged'])->assertSessionHasErrors('public_slug');
        try {
            $sales->forceFill(['public_slug' => 'changed'])->save();
            $this->fail('Model must prevent slug changes.');
        } catch (\LogicException) {
            $this->assertTrue(true);
        }
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $sales->id)->update(['public_slug' => 'changed']);
    }

    public function test_duplicate_public_slug_is_rejected_by_database_unique_index(): void
    {
        $sales = User::factory()->active()->create();
        $attributes = $sales->getRawOriginal();
        unset($attributes['id']);
        $attributes['email'] = 'different.unique@example.test';
        try {
            DB::table('users')->insert($attributes);
            $this->fail('Duplicate public slug must fail.');
        } catch (QueryException $error) {
            $this->assertSame(1062, $error->errorInfo[1]);
            $this->assertStringContainsString('users_public_slug_unique', $error->errorInfo[2]);
        }
    }

    public function test_browser_fixtures_render_with_uploaded_avatar_and_placeholder(): void
    {
        TestDatabase::assertIsolated();
        Storage::fake('sales_avatars');
        $sales = User::factory()->active()->create(['name' => 'Nguyễn Minh Anh', 'phone' => '0901234567']);
        $sales->dealer->update(['name' => 'Toyota An Sương']);
        CustomerSubmission::factory()->approved()->count(3)->create(['sales_id' => $sales->id]);
        $folder = base_path('.laravel-tools/sales-public-profile/qa');
        File::ensureDirectoryExists($folder);
        file_put_contents($folder.'/placeholder.html', $this->get($this->url($sales))->assertOk()->getContent());
        $this->actingAs($sales)->patch(route('profile.update'), ['name' => $sales->name, 'avatar' => UploadedFile::fake()->image('avatar.png')])->assertSessionHasNoErrors();
        $sales->refresh();
        $this->post(route('logout'));
        file_put_contents($folder.'/avatar.html', $this->get($this->url($sales))->assertOk()->getContent());
        file_put_contents($folder.'/avatar.jpg', Storage::disk('sales_avatars')->get($sales->avatar_path));
        file_put_contents($folder.'/manifest.json', json_encode(['url' => $this->url($sales),
            'avatarUrl' => route('sales.public.avatar', ['slug' => $sales->public_slug]),
            'privateEmail' => $sales->email, 'privatePhone' => $sales->phone], JSON_PRETTY_PRINT));
    }
}

<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\AwardWinner;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\DealerProfile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class DealerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        TestDatabase::assertIsolated();
    }

    private function data(array $overrides = []): array
    {
        return array_merge(['name' => 'Toyota Demo', 'code' => 'DEMO-001', 'province' => 'Hồ Chí Minh', 'phone' => '0901234567', 'address' => '123 Đường Demo', 'is_active' => '1', 'opening_hours' => '8:00 – 17:00', 'facebook_url' => 'facebook.com/toyota', 'zalo_url' => 'https://zalo.me/0901234567'], $overrides);
    }

    public function test_admin_can_create_and_update_all_dealer_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.dealers.create'))->assertOk();
        $this->post(route('admin.dealers.store'), $this->data())->assertSessionHasNoErrors();
        $dealer = Dealer::where('code', 'DEMO-001')->sole();
        $this->assertSame('0901234567', $dealer->phone);
        $this->assertSame('Hồ Chí Minh', $dealer->province);
        $this->assertSame('123 Đường Demo', $dealer->address);
        $this->assertTrue($dealer->is_active);
        $slug = $dealer->slug;
        $this->assertSame('8:00 – 17:00', $dealer->opening_hours);
        $this->assertSame('https://facebook.com/toyota', $dealer->facebook_url);
        $this->assertSame('https://zalo.me/0901234567', $dealer->zalo_url);
        $this->get(route('admin.dealers.edit', $dealer))->assertOk()->assertSee($dealer->phone);
        $this->put(route('admin.dealers.update', $dealer), $this->data(['name' => 'Tên mới', 'is_active' => '0', 'opening_hours' => '7:00 – 18:00', 'facebook_url' => null, 'zalo_url' => 'zalo.me/0907654321']))
            ->assertRedirect(route('admin.dealers.edit', $dealer))->assertSessionHasNoErrors();
        $this->assertSame('Tên mới', $dealer->fresh()->name);
        $this->assertFalse($dealer->fresh()->is_active);
        $this->assertSame($slug, $dealer->fresh()->slug);
        $this->assertSame('7:00 – 18:00', $dealer->fresh()->opening_hours);
        $this->assertNull($dealer->fresh()->facebook_url);
        $this->assertSame('https://zalo.me/0907654321', $dealer->fresh()->zalo_url);
    }

    public function test_contact_fields_are_optional_and_creation_ignores_unvalidated_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.dealers.store'), [
            'name' => 'Minimal dealer', 'code' => 'MINIMAL', 'is_active' => 1,
            'id' => 999999, 'created_at' => '2000-01-01 00:00:00',
        ])->assertSessionHasNoErrors();
        $dealer = Dealer::where('code', 'MINIMAL')->sole();
        $this->assertNull($dealer->province);
        $this->assertNull($dealer->phone);
        $this->assertNull($dealer->address);
        $this->assertNotSame(999999, $dealer->id);
        $this->assertNotSame('2000-01-01', $dealer->created_at->toDateString());
    }

    #[DataProvider('invalidFields')]
    public function test_validation_on_create_and_update(string $field, mixed $value): void
    {
        $admin = User::factory()->admin()->create();
        $dealer = Dealer::factory()->create();
        $data = $this->data([$field => $value]);
        $this->actingAs($admin)->post(route('admin.dealers.store'), $data)->assertSessionHasErrors($field);
        $this->put(route('admin.dealers.update', $dealer), $data)->assertSessionHasErrors($field);
        $this->assertSame(1, Dealer::count());
    }

    public static function invalidFields(): array
    {
        return [
            ['name', '   '], ['name', str_repeat('a', 256)], ['name', ['array']],
            ['code', null], ['code', str_repeat('a', 51)],
            ['province', str_repeat('a', 256)], ['phone', str_repeat('1', 31)],
            ['address', str_repeat('a', 2001)], ['is_active', 'invalid'], ['is_active', null],
            ['opening_hours', str_repeat('a', 2001)], ['facebook_url', 'javascript:alert(1)'],
            ['zalo_url', 'data:text/html,test'], ['facebook_url', str_repeat('a', 2049)],
        ];
    }

    public function test_code_unique_constraint_on_create_and_update_and_same_code_allowed_for_owner(): void
    {
        $admin = User::factory()->admin()->create();
        $first = Dealer::factory()->create(['code' => 'UNIQUE-CODE']);
        $second = Dealer::factory()->create();
        $this->actingAs($admin)->post(route('admin.dealers.store'), $this->data(['code' => 'unique-code']))->assertSessionHasErrors('code');
        $this->put(route('admin.dealers.update', $second), $this->data(['code' => $first->code]))->assertSessionHasErrors('code');
        $this->put(route('admin.dealers.update', $first), $this->data(['code' => $first->code]))->assertSessionHasNoErrors();
        $this->assertSame(2, Dealer::count());
    }

    #[DataProvider('searchFields')]
    public function test_search_fields_are_supported(string $field, string $value, string $search): void
    {
        $admin = User::factory()->admin()->create();
        $match = Dealer::factory()->create([$field => $value]);
        Dealer::factory()->create();
        $this->actingAs($admin)->get(route('admin.dealers.index', ['q' => $search]))->assertOk()
            ->assertViewHas('dealers', fn ($dealers) => $dealers->total() === 1 && $dealers->first()->is($match));
    }

    public static function searchFields(): array
    {
        return [
            ['name', 'Toyota Match', 'Match'], ['code', 'MATCH-123', 'match-123'],
            ['province', 'Hồ Chí Minh', 'Chí Minh'], ['phone', '0901234567', '012345'],
            ['address', '123 Unique Road', 'Unique Road'], ['name', 'Literal % Dealer', '%'],
        ];
    }

    public function test_list_pagination_preserves_search_and_counts_sales(): void
    {
        $admin = User::factory()->admin()->create();
        $dealers = Dealer::factory()->count(21)->create(['name' => 'Matching Dealer', 'province' => 'Hồ Chí Minh']);
        User::factory()->count(2)->for($dealers->first())->create();
        $this->actingAs($admin)->get(route('admin.dealers.index', ['q' => 'Matching', 'province' => 'Hồ Chí Minh', 'sort' => 'name', 'status' => 'active']))->assertOk()
            ->assertViewHas('dealers', fn ($page) => $page->total() === 21 && $page->count() === 20
                && $page->first()->sales_count === 2 && str_contains($page->nextPageUrl(), 'q=Matching')
                && str_contains($page->nextPageUrl(), 'province=') && str_contains($page->nextPageUrl(), 'sort=name') && str_contains($page->nextPageUrl(), 'status=active'));
    }

    #[DataProvider('actors')]
    public function test_unauthorized_actors_cannot_access_any_dealer_endpoint(?string $role, string $status): void
    {
        $dealer = Dealer::factory()->create();
        if ($role !== null) {
            $factory = $role === 'admin' ? User::factory()->admin() : User::factory()->for($dealer);
            $this->actingAs($factory->create(['status' => $status]));
        }
        $requests = [
            ['GET', route('admin.dealers.index'), []], ['GET', route('admin.dealers.create'), []],
            ['GET', route('admin.dealers.edit', $dealer), []], ['POST', route('admin.dealers.store'), $this->data()],
            ['PUT', route('admin.dealers.update', $dealer), $this->data()],
            ['PATCH', route('admin.dealers.status', $dealer), ['is_active' => 0]],
        ];
        foreach ($requests as [$method, $url, $data]) {
            $response = $this->call($method, $url, $data);
            if ($role === null) {
                $response->assertRedirect(route('login'));
            } elseif ($role === 'sales') {
                $response->assertForbidden();
            } else {
                $response->assertRedirect(route('account.status'));
            }
        }
        $this->assertSame(1, Dealer::count());
        $this->assertTrue($dealer->fresh()->is_active);
    }

    public static function actors(): array
    {
        return [[null, 'pending'], ['sales', 'pending'], ['sales', 'active'], ['sales', 'rejected'], ['sales', 'blocked'], ['admin', 'pending'], ['admin', 'rejected'], ['admin', 'blocked']];
    }

    public function test_status_change_controls_registration_without_changing_existing_sales(): void
    {
        $admin = User::factory()->admin()->create();
        $dealer = Dealer::factory()->create();
        $sales = User::factory()->active()->for($dealer)->create();
        $this->actingAs($admin)->patch(route('admin.dealers.status', $dealer), ['is_active' => 0, 'name' => 'Forged name'])->assertSessionHasNoErrors();
        $this->assertFalse($dealer->fresh()->is_active);
        $this->assertNotSame('Forged name', $dealer->fresh()->name);
        $this->assertSame(UserStatus::Active, $sales->fresh()->status);
        auth()->logout();
        Cache::flush();
        $this->get('/register')->assertOk()->assertDontSee($dealer->name);
        $registration = ['name' => 'New sales', 'phone' => '0901234567', 'email' => 'new@example.test', 'dealer_id' => $dealer->id, 'password' => 'test-password', 'password_confirmation' => 'test-password'];
        $this->post('/register', $registration)->assertSessionHasErrors('dealer_id');
        $this->actingAs($admin)->patch(route('admin.dealers.status', $dealer), ['is_active' => 1])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get('/register')->assertOk()->assertSee($dealer->name);
        $this->post('/register', $registration)->assertSessionHasNoErrors()->assertRedirect(route('account.status'));
    }

    public function test_invalid_status_is_rejected_and_no_delete_endpoint_exists(): void
    {
        $admin = User::factory()->admin()->create();
        $dealer = Dealer::factory()->create();
        $this->actingAs($admin)->patch(route('admin.dealers.status', $dealer), ['is_active' => 'invalid'])->assertSessionHasErrors('is_active');
        $this->delete('/admin/dealers/'.$dealer->id)->assertStatus(405);
        $this->assertDatabaseHas('dealers', ['id' => $dealer->id, 'is_active' => 1]);
    }

    public function test_editing_dealer_keeps_award_snapshots_unchanged(): void
    {
        $admin = User::factory()->admin()->create();
        $dealer = Dealer::factory()->create();
        $winner = AwardWinner::factory()->dealer()->create(['dealer_id' => $dealer->id]);
        $originalName = $winner->dealer_name_snapshot;
        $originalCode = $winner->dealer_code_snapshot;
        $winner->award->forceFill(['published_by' => $admin->id, 'published_at' => now()])->save();
        $this->actingAs($admin)->put(route('admin.dealers.update', $dealer), $this->data(['name' => 'Renamed dealer', 'code' => 'RENAMED', 'is_active' => 0]))->assertSessionHasNoErrors();
        $this->assertSame($originalName, $winner->fresh()->dealer_name_snapshot);
        $this->assertSame($originalCode, $winner->fresh()->dealer_code_snapshot);
    }

    public function test_c04_tabs_filters_and_panel_use_approved_month_and_active_manager_without_changing_ranking(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(12, 0));
        $admin = User::factory()->admin()->create();
        $first = Dealer::factory()->create(['name' => 'Toyota First', 'province' => 'Hồ Chí Minh']);
        $second = Dealer::factory()->inactive()->create(['name' => 'Toyota Second', 'province' => 'Hà Nội']);
        $third = Dealer::factory()->create(['name' => 'Toyota Third', 'province' => 'Hồ Chí Minh']);
        $sales = User::factory()->active()->for($first)->create();
        User::factory()->dealer()->active()->for($first)->create();
        User::factory()->dealer()->for($third)->create(['status' => UserStatus::Pending]);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => now()->startOfMonth()]);
        CustomerSubmission::factory()->approved()->create(['sales_id' => $sales->id, 'submitted_at' => now()->startOfMonth()->subSecond()]);
        CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $other = User::factory()->active()->for($second)->create();
        CustomerSubmission::factory()->approved()->count(2)->create(['sales_id' => $other->id]);
        // Historical attribution must survive a subsequent Sales transfer.
        $sales->forceFill(['dealer_id' => $third->id])->save();
        $response = $this->actingAs($admin)->get(route('admin.dealers.index', ['selected' => $first->id]))->assertOk()
            ->assertViewHas('counts', ['all' => 3, 'active' => 2, 'inactive' => 1, 'no_customers' => 1, 'no_manager' => 2])
            ->assertViewHas('standing', fn ($standing) => $standing->rank === 2)
            ->assertViewHas('selected', fn ($dealer) => $dealer->id === $first->id && $dealer->month_count === 1 && $dealer->approved_count === 2 && $dealer->active_sales_count === 0);
        $response->assertDontSee('QR sẽ dẫn tới trang báo lỗi.')->assertDontSee('Đại lý không còn trên bảng xếp hạng kỳ mới.');
        foreach (['no_customers' => [$third->id], 'no_manager' => [$second->id, $third->id], 'inactive' => [$second->id]] as $status => $ids) {
            $page = $this->get(route('admin.dealers.index', ['status' => $status, 'sort' => 'name', 'selected' => $first->id]))->assertOk()->viewData('dealers');
            $this->assertSame($ids, $page->modelKeys());
        }
        $this->get(route('admin.dealers.index', ['province' => 'Hồ Chí Minh', 'q' => 'Toyota', 'sort' => 'total']))->assertOk()
            ->assertViewHas('dealers', fn ($page) => $page->modelKeys() === [$first->id, $third->id]);
        $this->getJson(route('admin.dealers.index', ['sort' => 'untrusted']))->assertUnprocessable();
        $this->patchJson(route('admin.dealers.status', $first), ['is_active' => 0])->assertOk();
        $this->get(route('admin.dealers.index', ['selected' => $first->id]))->assertOk()
            ->assertViewHas('standing', fn ($standing) => $standing->rank === 2)
            ->assertViewHas('selected', fn ($dealer) => ! $dealer->is_active && $dealer->approved_count === 2);
    }

    public function test_c04_json_crud_validates_and_preserves_canonical_identity_and_history(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->postJson(route('admin.dealers.store'), $this->data())->assertCreated()
            ->assertJsonPath('message', 'Đã tạo đại lý · QR và standee đã sẵn sàng');
        $dealer = Dealer::findOrFail($response->json('id'));
        $profile = app(DealerProfile::class);
        $url = $profile->url($dealer);
        $qr = $profile->qr($dealer);
        $this->putJson(route('admin.dealers.update', $dealer), $this->data(['name' => 'Renamed', 'code' => 'CHANGED']))->assertOk()
            ->assertJsonPath('message', 'Đã lưu thông tin đại lý');
        $this->putJson(route('admin.dealers.update', $dealer), $this->data(['slug' => 'forged']))->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->patchJson(route('admin.dealers.status', $dealer), ['is_active' => 0])->assertOk()->assertJsonPath('message', 'Đã ngừng tham gia Renamed');
        $this->assertSame($url, $profile->url($dealer->fresh()));
        $this->assertSame($qr, $profile->qr($dealer->fresh()));
        $this->assertDatabaseCount('dealers', 1);
    }

    public function test_c04_mutations_reject_missing_csrf_without_writing(): void
    {
        $admin = User::factory()->admin()->create();
        $dealer = Dealer::factory()->create();
        $this->actingAs($admin);
        // Exercise the real web middleware instead of its PHPUnit CSRF bypass.
        $previousEnvironment = $this->app['env'];
        $this->app['env'] = 'local';
        try {
            $this->post(route('admin.dealers.store'), $this->data())->assertStatus(419);
            $this->put(route('admin.dealers.update', $dealer), $this->data())->assertStatus(419);
            $this->patch(route('admin.dealers.status', $dealer), ['is_active' => 0])->assertStatus(419);
        } finally {
            $this->app['env'] = $previousEnvironment;
        }
        $this->assertDatabaseCount('dealers', 1);
        $this->assertTrue($dealer->fresh()->is_active);
        $this->assertNotSame('Toyota Demo', $dealer->fresh()->name);
    }

    private function referencedDealer(string $relation): Dealer
    {
        $dealer = Dealer::factory()->create();
        if ($relation === 'sales') {
            User::factory()->for($dealer)->create();
        } elseif ($relation === 'dealer_award') {
            AwardWinner::factory()->dealer()->create(['dealer_id' => $dealer->id]);
        } else {
            AwardWinner::factory()->create(['sales_dealer_id_snapshot' => $dealer->id]);
        }

        return $dealer;
    }

    #[DataProvider('references')]
    public function test_model_blocks_deletion_of_referenced_dealer(string $relation): void
    {
        $dealer = $this->referencedDealer($relation);
        $this->expectException(LogicException::class);
        $dealer->delete();
    }

    #[DataProvider('references')]
    public function test_database_blocks_raw_deletion_of_referenced_dealer(string $relation): void
    {
        $dealer = $this->referencedDealer($relation);
        $this->expectException(QueryException::class);
        DB::table('dealers')->where('id', $dealer->id)->delete();
    }

    public static function references(): array
    {
        return [['sales'], ['dealer_award'], ['sales_snapshot']];
    }
}

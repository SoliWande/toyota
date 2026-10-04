<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\AwardWinner;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class DealerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Database tests may only refresh toyota_testing.');
        }
    }

    private function data(array $overrides = []): array
    {
        return array_merge(['name' => 'Toyota Demo', 'code' => 'DEMO-001', 'province' => 'Hồ Chí Minh', 'phone' => '0901234567', 'address' => '123 Đường Demo', 'is_active' => '1'], $overrides);
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
        $this->get(route('admin.dealers.edit', $dealer))->assertOk()->assertSee($dealer->phone);
        $this->put(route('admin.dealers.update', $dealer), $this->data(['name' => 'Tên mới', 'is_active' => '0']))
            ->assertRedirect(route('admin.dealers.edit', $dealer))->assertSessionHasNoErrors();
        $this->assertSame('Tên mới', $dealer->fresh()->name);
        $this->assertFalse($dealer->fresh()->is_active);
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

    /** @dataProvider invalidFields */
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

    /** @dataProvider searchFields */
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
        $dealers = Dealer::factory()->count(21)->create(['name' => 'Matching Dealer']);
        User::factory()->count(2)->for($dealers->first())->create();
        $this->actingAs($admin)->get(route('admin.dealers.index', ['q' => 'Matching']))->assertOk()
            ->assertViewHas('dealers', fn ($page) => $page->total() === 21 && $page->count() === 20
                && $page->first()->sales_count === 2 && str_contains($page->nextPageUrl(), 'q=Matching'));
    }

    /** @dataProvider actors */
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
        $registration = ['name' => 'New sales', 'email' => 'new@example.test', 'dealer_id' => $dealer->id, 'password' => 'test-password', 'password_confirmation' => 'test-password'];
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

    /** @dataProvider references */
    public function test_model_blocks_deletion_of_referenced_dealer(string $relation): void
    {
        $dealer = $this->referencedDealer($relation);
        $this->expectException(LogicException::class);
        $dealer->delete();
    }

    /** @dataProvider references */
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

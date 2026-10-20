<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\User;
use App\Services\DealerProfile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DealerSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_is_unique_reserved_safe_and_stable_after_name_and_contact_updates(): void
    {
        $first = Dealer::factory()->create(['name' => 'Toyota An Sương']);
        $second = Dealer::factory()->create(['name' => $first->name]);
        $reserved = Dealer::factory()->create(['name' => 'Admin']);
        $this->assertSame('toyota-an-suong', $first->slug);
        $this->assertNotSame($first->slug, $second->slug);
        $this->assertNotSame('admin', $reserved->slug);
        $before = app(DealerProfile::class)->qr($first);
        $this->actingAs(User::factory()->admin()->create())->put(route('admin.dealers.update', $first), [
            'name' => 'Renamed Dealer', 'code' => $first->code, 'address' => 'New address', 'phone' => '0123456789', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame($first->slug, $first->fresh()->slug);
        $this->assertSame($before, app(DealerProfile::class)->qr($first->fresh()));
        $this->get(route('dealers.show', $first))->assertStatus(301)->assertRedirect(app(DealerProfile::class)->url($first));
        $this->get(app(DealerProfile::class)->url($first))->assertOk()->assertSee('Renamed Dealer');
    }

    public function test_slug_mutation_is_blocked_in_model_database_and_admin_form(): void
    {
        $dealer = Dealer::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->put(route('admin.dealers.update', $dealer), [
            'name' => $dealer->name, 'code' => $dealer->code, 'is_active' => 1, 'slug' => 'changed',
        ])->assertSessionHasErrors('slug');
        try {
            $dealer->slug = 'changed';
            $dealer->save();
            $this->fail('Model changed slug.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }
        try {
            DB::table('dealers')->where('id', $dealer->id)->update(['slug' => 'changed']);
            $this->fail('Database changed slug.');
        } catch (QueryException $exception) {
            $this->assertSame(1644, $exception->errorInfo[1]);
        }
    }

    #[DataProvider('invalidPublicBases')]
    public function test_qr_rejects_credentials_and_query_tokens_in_base_url(string $base): void
    {
        config(['dealer.public_base_url' => $base]);
        $dealer = Dealer::factory()->create();
        $this->expectException(LogicException::class);
        app(DealerProfile::class)->qr($dealer);
    }

    public static function invalidPublicBases(): array
    {
        return array_map(fn ($url) => [$url], ['https://official.example/?token=secret', 'https://user:secret@official.example',
            'https://official.example/#private', 'javascript:alert(1)']);
    }
}

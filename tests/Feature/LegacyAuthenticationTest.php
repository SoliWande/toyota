<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\RestoresVerifiedDealerDatabase;
use Tests\TestCase;

/** Verify fail-closed behavior even BEFORE the database migration archives Sales. */
class LegacyAuthenticationTest extends TestCase
{
    use RestoresVerifiedDealerDatabase;

    public function test_valid_legacy_password_session_remember_token_and_recovery_are_rejected_before_migration(): void
    {
        DB::beginTransaction();
        try {
            $email = 'legacy-auth-fixture@example.test';
            $id = DB::table('users')->insertGetId(['name' => 'Legacy fixture', 'email' => $email,
                'password' => Hash::make('known-legacy-password'), 'remember_token' => 'known-remember-token',
                'role' => 'sales', 'status' => 'active', 'dealer_id' => DB::table('dealers')->value('id')]);
            $provider = auth()->getProvider();
            $this->assertNull($provider->retrieveByCredentials(['email' => $email, 'password' => 'known-legacy-password']));
            $this->assertNull($provider->retrieveById($id));
            $this->assertNull($provider->retrieveByToken($id, 'known-remember-token'));
            $this->post('/login', ['email' => $email, 'password' => 'known-legacy-password'])->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->withSession([auth()->guard('web')->getName() => $id])->get('/profile')->assertRedirect(route('login'));
            Notification::fake();
            $this->post('/forgot-password', ['email' => $email])->assertSessionHasNoErrors();
            Notification::assertNothingSent();
            $this->assertSame(0, DB::table('password_reset_tokens')->where('email', $email)->count());
            $tokenId = DB::table('personal_access_tokens')->insertGetId([
                'tokenable_type' => User::class, 'tokenable_id' => $id, 'name' => 'Legacy token fixture',
                'token' => hash('sha256', 'known-legacy-api-token'), 'abilities' => '["*"]',
            ]);
            auth()->forgetGuards();
            $this->getJson('/api/user', ['Authorization' => 'Bearer '.$tokenId.'|known-legacy-api-token'])->assertForbidden();
            auth()->forgetGuards();
            $legacy = new User;
            $legacy->setRawAttributes(['id' => $id, 'role' => 'sales', 'status' => 'active', 'dealer_id' => DB::table('dealers')->value('id')]);
            $this->actingAs($legacy, 'web')->getJson('/dealer/submissions')->assertForbidden();
        } finally {
            DB::rollBack();
        }
    }
}

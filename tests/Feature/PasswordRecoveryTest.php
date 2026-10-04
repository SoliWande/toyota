<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use LogicException;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'toyota_testing') {
            throw new LogicException('Password tests may only refresh toyota_testing.');
        }
    }

    private function data(User $user, string $token): array
    {
        return ['email' => $user->email, 'token' => $token, 'password' => 'reset-password-123', 'password_confirmation' => 'reset-password-123'];
    }

    public function test_forgot_password_sends_token_without_disclosing_account_existence(): void
    {
        Notification::fake();
        $user = User::factory()->active()->create();
        $this->get(route('password.request'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $known = $this->post(route('password.email'), ['email' => strtoupper($user->email)])->assertSessionHasNoErrors();
        $message = session('success');
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);
            $this->assertStringContainsString('/reset-password/'.$notification->token, $mail->actionUrl);
            $this->assertStringContainsString(urlencode($user->email), $mail->actionUrl);

            return Password::tokenExists($user, $notification->token);
        });
        $this->post(route('password.email'), ['email' => 'missing@example.test'])->assertSessionHasNoErrors()->assertSessionHas('success', $message);
        $this->assertSame(1, DB::table('password_reset_tokens')->count());
        $this->assertSame($known->getStatusCode(), 302);
    }

    /** @dataProvider accountKinds */
    public function test_reset_preserves_email_dealer_role_status_and_token_is_one_time(string $kind): void
    {
        $user = $kind === 'admin' ? User::factory()->admin()->create() : User::factory()->create(['status' => $kind]);
        $original = $user->refresh()->only(['email', 'dealer_id', 'role', 'status']);
        $token = Password::createToken($user);
        $storedToken = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->assertNotSame($token, $storedToken);
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk()->assertSee($user->email)->assertDontSee('type="email"', false);
        $this->post(route('password.store'), $this->data($user, $token))->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('reset-password-123', $user->fresh()->password));
        $this->assertSame($original, $user->fresh()->only(['email', 'dealer_id', 'role', 'status']));
        $this->assertGuest();
        $this->post(route('password.store'), $this->data($user, $token))->assertSessionHasErrors('email');
        $this->post(route('login'), ['email' => $user->email, 'password' => 'reset-password-123'])->assertRedirect(route($user->homeRoute()));
    }

    public static function accountKinds(): array
    {
        return [['active'], ['pending'], ['rejected'], ['blocked'], ['admin']];
    }

    public function test_reset_token_cannot_target_a_different_email(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = Password::createToken($owner);
        $this->post(route('password.store'), $this->data($other, $token))->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $owner->fresh()->password));
        $this->assertTrue(Hash::check('password', $other->fresh()->password));
        $this->assertSame($owner->email, $owner->fresh()->email);
        $this->assertSame($other->email, $other->fresh()->email);
    }

    public function test_reset_rejects_expired_token_bad_confirmation_and_privilege_fields(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->post(route('password.store'), array_merge($this->data($user, $token), ['password_confirmation' => 'wrong']))->assertSessionHasErrors('password');
        $this->post(route('password.store'), $this->data($user, $token) + ['role' => 'admin', 'status' => 'active', 'dealer_id' => 99])->assertSessionHasErrors(['role', 'status', 'dealer_id']);
        $this->travel(61)->minutes();
        $this->post(route('password.store'), $this->data($user, $token))->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertSame('pending', $user->fresh()->status->value);
    }

    public function test_reset_invalidates_existing_authenticated_session(): void
    {
        $user = User::factory()->active()->create();
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
        $oldHash = session('password_hash_web');
        $user->forceFill(['password' => Hash::make('reset-password-123')])->save();
        $this->actingAs($user->fresh())->withSession(['password_hash_web' => $oldHash])->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_forgot_and_reset_endpoints_have_separate_rate_limits(): void
    {
        Notification::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('password.email'), ['email' => 'missing@example.test'])->assertRedirect();
        }
        $this->postJson(route('password.email'), ['email' => 'missing@example.test'])->assertStatus(429);
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('password.store'), [])->assertUnprocessable();
        }
        $this->postJson(route('password.store'), [])->assertStatus(429);
    }

    public function test_bulk_update_cannot_change_email_at_database_level(): void
    {
        $user = User::factory()->create();
        try {
            DB::table('users')->where('id', $user->id)->update(['email' => 'changed@example.test']);
            $this->fail('Bulk email update was accepted.');
        } catch (QueryException $error) {
            $this->assertSame('45000', $error->errorInfo[0]);
            $this->assertSame($user->email, $user->fresh()->email);
        }
    }

    public function test_guest_profile_and_malformed_reset_email_are_protected(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->patch(route('profile.update'), ['name' => 'Attack'])->assertRedirect(route('login'));
        $this->put(route('profile.password'), [])->assertRedirect(route('login'));
        $this->getJson(route('password.reset', ['token' => 'abc', 'email' => ['invalid']]))->assertUnprocessable();
    }
}

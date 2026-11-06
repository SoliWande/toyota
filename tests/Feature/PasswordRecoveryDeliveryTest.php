<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class PasswordRecoveryDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        TestDatabase::assertIsolated();
    }

    public static function nonDeliveryMailers(): array
    {
        return [['array'], ['log'], ['failover']];
    }

    #[DataProvider('nonDeliveryMailers')]
    public function test_non_delivery_configuration_does_not_create_tokens_or_claim_sent_mail(string $mailer): void
    {
        config(['mail.default' => $mailer]);
        Notification::fake();
        $user = User::factory()->create();
        $this->from(route('password.request'))->post(route('password.email'), ['email' => $user->email])->assertRedirect(route('password.request'));
        $message = session('success');
        $this->assertStringContainsString('chưa sẵn sàng', $message);
        $this->post(route('password.email'), ['email' => 'absent@example.test'])->assertSessionHas('success', $message);
        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
        $this->get(route('password.request'))->assertOk()->assertSee($message);
    }

    public function test_smtp_failure_has_same_public_response_as_unknown_email_and_no_exception_details(): void
    {
        config(['mail.default' => 'smtp']);
        $user = User::factory()->create();
        Notification::shouldReceive('send')->once()->andThrow(new TransportException('SECRET_SMTP_TOKEN_AND_CREDENTIAL'));
        Log::spy();
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();
        $message = session('success');
        $this->assertStringNotContainsString('SECRET', $message);
        $this->assertStringNotContainsString('đã gửi', $message);
        $this->post(route('password.email'), ['email' => 'unknown@example.test'])->assertSessionHas('success', $message);
        Log::shouldHaveReceived('warning')->once()->with('Password reset mail transport unavailable.');
    }

    public function test_email_validation_and_malformed_old_input_render_safely_without_tokens(): void
    {
        foreach (['invalid-email', ['array'], str_repeat('a', 256).'@example.test'] as $email) {
            $this->from(route('password.request'))->post(route('password.email'), ['email' => $email])->assertSessionHasErrors('email');
            $this->get(route('password.request'))->assertOk();
        }
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_reset_form_does_not_refill_passwords_and_renders_real_token_errors(): void
    {
        $user = User::factory()->create();
        $response = $this->from(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
            ->post(route('password.store'), ['token' => 'invalid-token', 'email' => $user->email,
                'password' => 'unrepeatable-private-password', 'password_confirmation' => 'unrepeatable-private-password']);
        $response->assertSessionHasErrors('email');
        $this->get(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))->assertOk()
            ->assertSee('Liên kết không hợp lệ hoặc đã hết hạn')->assertSee('Yêu cầu liên kết mới')
            ->assertDontSee('unrepeatable-private-password')->assertSee('data-account-password', false);
    }
}

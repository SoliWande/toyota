<?php

namespace Tests\Feature;

use App\Actions\ReviewAccountRegistration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestDatabase;
use Tests\TestCase;

class AccountStatusPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        TestDatabase::assertIsolated();
    }

    public function test_status_uses_own_dealer_public_reason_and_resubmission_date_for_both_roles(): void
    {
        foreach (['sales', 'dealer'] as $role) {
            $reviewer = $role === 'sales' ? User::factory()->dealer()->create() : User::factory()->admin()->create();
            $attributes = ['role' => $role, 'created_at' => now()->subDays(3), 'admin_note' => 'PRIVATE_REVIEW_NOTE'];
            if ($role === 'sales') {
                $attributes['dealer_id'] = $reviewer->dealer_id;
            }
            $account = User::factory()->create($attributes);
            $this->actingAs($account)->get(route('account.status'))->assertOk()->assertSee($account->dealer->name)
                ->assertSee($role === 'sales' ? 'Quản lý đại lý' : 'Admin Ô tô Sài Gòn')->assertDontSee('PRIVATE_REVIEW_NOTE');
            app(ReviewAccountRegistration::class)->execute($reviewer, $account, 'reject', ['rejection_reason' => 'Lý do công khai cho chính tài khoản.']);
            $this->actingAs($account->fresh())->get(route('account.status'))->assertOk()->assertSee('Lý do công khai cho chính tài khoản.')
                ->assertSee('Đăng ký lại')->assertDontSee('PRIVATE_REVIEW_NOTE');
            $this->post(route('account.resubmit'))->assertRedirect(route('account.status'));
            $response = $this->actingAs($account->fresh())->get(route('account.status'))->assertOk();
            $this->assertEquals(now()->format('d/m/Y H:i'), Carbon::parse($response->viewData('sentAt'))->format('d/m/Y H:i'));
            app(ReviewAccountRegistration::class)->execute($reviewer, $account->fresh(), 'approve');
            $this->actingAs($account->fresh())->get(route('account.status'))->assertRedirect(route($account->fresh()->homeRoute()));
        }
    }

    public function test_blocked_account_is_not_rejected_and_has_no_resubmit_or_invented_review_time(): void
    {
        $account = User::factory()->create(['status' => 'blocked', 'reviewed_at' => null, 'reviewed_by' => null]);
        $this->actingAs($account)->get(route('account.status'))->assertOk()->assertSee('Tài khoản đã bị khóa')
            ->assertSee('Chưa có thời điểm ghi nhận')->assertDontSee('Chưa duyệt được tài khoản')
            ->assertDontSee('action="'.route('account.resubmit').'"', false);
    }
}

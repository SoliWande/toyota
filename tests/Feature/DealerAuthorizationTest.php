<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\CustomerSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DealerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_other_roles_cannot_access_dealer_pages(): void
    {
        $sales = User::factory()->active()->create();
        $submission = CustomerSubmission::factory()->create(['sales_id' => $sales->id]);
        $urls = [route('dealer.dashboard'), route('dealer.sales.index'), route('dealer.sales.show', $sales),
            route('dealer.customers.index'), route('dealer.customers.show', $submission), route('dealer.awards.index')];
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
            $this->getJson($url)->assertUnauthorized();
        }
        foreach ([$sales, User::factory()->admin()->create()] as $user) {
            $this->actingAs($user);
            foreach ($urls as $url) {
                $this->get($url)->assertForbidden();
            }
        }
        foreach ([UserStatus::Pending, UserStatus::Rejected, UserStatus::Blocked] as $status) {
            $this->actingAs(User::factory()->dealer()->create(['status' => $status]));
            foreach ($urls as $url) {
                $this->getJson($url)->assertForbidden();
            }
        }
    }

    public function test_dealer_cannot_read_other_dealer_records_or_write_via_any_role_endpoint(): void
    {
        Storage::fake('sales_avatars');
        $manager = User::factory()->dealer()->create();
        $ownSales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id]);
        $own = CustomerSubmission::factory()->create(['sales_id' => $ownSales->id]);
        $other = CustomerSubmission::factory()->create();
        $this->actingAs($manager)->getJson(route('dealer.customers.show', $other))->assertNotFound();
        $this->getJson(route('dealer.sales.show', $other->sales_id))->assertNotFound();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.dealers.show', $other->dealer_id))->assertForbidden();
        $this->get(route('sales.submissions.create'))->assertForbidden();
        $this->patch(route('sales.submissions.update', $own), ['customer_name' => 'Tampered'])->assertForbidden();
        $this->delete(route('sales.submissions.destroy', $own))->assertForbidden();
        $this->post(route('admin.submissions.approve', $own))->assertForbidden();
        $this->post(route('admin.submissions.reject', $own))->assertForbidden();
        $this->patch(route('admin.sales.update', $ownSales), ['name' => 'Tampered'])->assertForbidden();
        $this->post(route('admin.awards.store'), [])->assertForbidden();
        $this->assertSame($own->customer_name, $own->fresh()->customer_name);
        $this->assertSame('pending', $own->fresh()->status->value);
        $this->assertSame($ownSales->name, $ownSales->fresh()->name);
    }

    public function test_private_avatars_follow_current_staff_assignment_and_never_become_public(): void
    {
        Storage::fake('sales_avatars');
        $manager = User::factory()->dealer()->create();
        $sales = User::factory()->active()->create(['dealer_id' => $manager->dealer_id, 'avatar_path' => 'staff.jpg']);
        Storage::disk('sales_avatars')->put('staff.jpg', 'staff-image');
        $this->actingAs($manager)->get(route('sales.avatar', $sales))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs(User::factory()->dealer()->create())->get(route('sales.avatar', $sales))->assertForbidden();
        $this->post(route('logout'));
        $this->getJson(route('sales.avatar', $sales))->assertUnauthorized();
        $this->get('/dealers/'.$manager->dealer_id.'/sales/'.$sales->id.'/avatar')->assertNotFound();
    }
}

<?php

namespace Tests\Unit;

use App\Models\Dealer;
use App\Services\DealerRegistrationDraft;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DealerRegistrationDraftStorageTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = storage_path('framework/testing/draft-storage-'.bin2hex(random_bytes(8)));
        config(['app.key' => 'base64:'.base64_encode(str_repeat('u', 32)), 'cache.stores.file.path' => $this->path]);
        Cache::forgetDriver('file');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);
        parent::tearDown();
    }

    private function request(): Request
    {
        $request = Request::create('/');
        $session = new Store('draft-test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        return $request;
    }

    private function dealer(int $id): Dealer
    {
        return (new Dealer)->forceFill(['id' => $id, 'is_active' => true]);
    }

    public function test_encrypted_server_storage_has_no_personal_session_data_even_with_cookie_driver(): void
    {
        config(['session.driver' => 'cookie']);
        $request = $this->request();
        $dealer = $this->dealer(1);
        $drafts = new DealerRegistrationDraft;
        $values = ['customer_name' => 'SYNTHETIC_PRIVATE_NAME', 'phone' => '0901234567', 'vehicle_description' => 'FJ'];
        $drafts->write($request, $dealer, $values);
        $this->assertTrue($drafts->read($request, $dealer)['values'] === $values, 'Draft roundtrip failed.');
        $this->assertFalse(str_contains(serialize($request->session()->all()), $values['customer_name']));
        $files = File::allFiles($this->path);
        $this->assertCount(1, $files);
        $this->assertFalse(str_contains($files[0]->getContents(), $values['customer_name']));
        $this->assertFalse(str_contains($files[0]->getContents(), $values['phone']));
    }

    public function test_session_and_dealer_boundaries_and_independent_tab_edits(): void
    {
        $request = $this->request();
        $drafts = new DealerRegistrationDraft;
        $drafts->write($request, $this->dealer(1), ['vehicle_description' => 'FJ']);
        $this->assertNull($drafts->read($request, $this->dealer(2)));
        $this->assertNull($drafts->read($this->request(), $this->dealer(1)));
        $drafts->write($request, $this->dealer(2), ['vehicle_description' => 'Hilux']);
        $drafts->write($request, $this->dealer(1), ['vehicle_description' => 'Veloz']);
        $this->assertSame('Veloz', $drafts->read($request, $this->dealer(1))['values']['vehicle_description']);
        $this->assertSame('Hilux', $drafts->read($request, $this->dealer(2))['values']['vehicle_description']);
    }

    public function test_ttl_is_fixed_and_inactive_dealer_invalidates_draft(): void
    {
        $drafts = new DealerRegistrationDraft;
        $request = $this->request();
        $dealer = $this->dealer(1);
        $drafts->write($request, $dealer, []);
        $this->travel(29)->minutes();
        $this->assertNotNull($drafts->read($request, $dealer));
        $this->travel(1)->minutes();
        $this->assertNull($drafts->read($request, $dealer));
        $drafts->write($request, $dealer, []);
        $dealer->is_active = false;
        $this->assertNull($drafts->read($request, $dealer));
        $dealer->is_active = true;
        $this->assertNull($drafts->read($request, $dealer));
    }

    public function test_error_state_is_scoped_and_cannot_be_used_as_complete_draft(): void
    {
        $drafts = new DealerRegistrationDraft;
        $request = $this->request();
        $dealer = $this->dealer(1);
        $drafts->write($request, $dealer, [], ['phone' => ['Generic validation error']], true);
        $draft = $drafts->read($request, $dealer);
        $this->assertFalse($draft['complete']);
        $this->assertTrue($draft['duplicate']);
        $this->assertFalse($request->session()->has('_old_input'));
    }
}

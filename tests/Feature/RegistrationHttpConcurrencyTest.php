<?php

namespace Tests\Feature;

use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\AdministrativeDivisions;
use App\Services\SubmissionVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\TestDatabase;
use Tests\TestCase;

/** Real loopback HTTP, two independent PHP servers/connections; only owned fixtures are cleaned. */
class RegistrationHttpConcurrencyTest extends TestCase
{
    public static function cases(): array
    {
        $cases = ['phone', 'plate', 'same-draft', 'retry', 'rollback', 'legacy-sales', 'failure-retry',
            'approval-phone', 'approval-plate', 'approval-pair', 'verification-pair', 'supplement-pair'];

        return array_combine($cases, array_map(fn ($v) => [$v], $cases));
    }

    private function send($curl, string $url, ?array $payload = null, array $headers = []): array
    {
        curl_setopt_array($curl, [CURLOPT_URL => $url, CURLOPT_POST => $payload !== null,
            CURLOPT_HTTPHEADER => ['Accept: text/html', ...$headers], CURLOPT_POSTFIELDS => $payload === null ? null : http_build_query($payload)]);
        // Setting POSTFIELDS even to null can select POST; restore the intended method last.
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $payload === null ? 'GET' : 'POST');
        $body = curl_exec($curl);
        $this->assertTrue($body !== false, 'Loopback HTTP request failed.');

        return [(int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), $body];
    }

    private function token(string $html): string
    {
        preg_match('/name="_token" value="([^"]+)"/', $html, $match);
        $this->assertTrue(isset($match[1]), 'CSRF token missing from fixture page.');

        return html_entity_decode($match[1]);
    }

    #[DataProvider('cases')]
    public function test_two_real_http_requests(string $case): void
    {
        TestDatabase::assertIsolated();
        $this->assertSame('toyota_testing', DB::selectOne('SELECT DATABASE() AS n')->n);
        $root = storage_path('framework/testing/http-race-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($root.'/sessions');
        $servers = [];
        $dealers = [];
        $users = [];
        $clients = [];
        $baseline = DB::table('customer_submissions')->count();
        $connection = config('database.connections.mysql');
        $env = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('h', 32)),
            'APP_URL' => 'http://127.0.0.1', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'toyota_testing',
            'DATABASE_URL' => '', 'DB_URL' => '', 'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
            'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
            'TOYOTA_HTTP_RACE' => '1', 'TOYOTA_HTTP_RACE_PATH' => $root];
        try {
            $ports = [];
            foreach ([0, 1] as $i) {
                $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
                $this->assertTrue($socket !== false, 'Loopback sockets unavailable.');
                $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
                fclose($socket);
                $ports[] = $port;
                $server = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, base_path('tests/Fixtures/registration_http_router.php')], base_path(), $env);
                $server->start();
                $servers[] = $server;
                $deadline = microtime(true) + 10;
                do {
                    $ready = @fsockopen('127.0.0.1', $port);
                    if ($ready) {
                        fclose($ready);
                        break;
                    }
                    usleep(20000);
                } while ($server->isRunning() && microtime(true) < $deadline);
                $this->assertTrue((bool) $ready, 'HTTP fixture server failed to listen.');
                $clients[$i] = curl_init();
                curl_setopt_array($clients[$i], [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 20]);
                $dealers[$i] = Dealer::factory()->create(['name' => 'Synthetic HTTP Dealer '.bin2hex(random_bytes(4))]);
            }
            $directory = app(AdministrativeDivisions::class);
            $province = $directory->provinces()[0]['code'];
            $address = ['province_code' => $province, 'ward_code' => $directory->wards($province)[0]['code'], 'address_line' => 'SYNTHETIC_HTTP_ADDRESS'];
            $payloads = [];
            $urls = [];
            foreach ([0, 1] as $i) {
                $base = 'http://127.0.0.1:'.$ports[$i];
                $slug = $dealers[$i]->slug;
                [$status, $html] = $this->send($clients[$i], $base.'/'.$slug);
                $this->assertSame(200, $status);
                $token = $this->token($html);
                $phone = $i === 0 || in_array($case, ['phone', 'rollback', 'legacy-sales']) ? '0990000081' : '0990000082';
                if ($case === 'legacy-sales') {
                    if ($i === 0) {
                        $payloads[$i] = $address + ['_token' => $token, 'customer_name' => 'SYNTHETIC_LEGACY',
                            'phone' => $phone, 'vehicle_description' => 'FJ', 'consent' => '1'];
                        $urls[$i] = $base.'/'.$slug.'/registrations';

                        continue;
                    }
                    $sales = User::factory()->active()->create(['dealer_id' => $dealers[$i]->id, 'password' => bcrypt('Synthetic-Only-123!')]);
                    $users[] = $sales->id;
                    [, $login] = $this->send($clients[$i], $base.'/login');
                    [$status] = $this->send($clients[$i], $base.'/login', ['_token' => $this->token($login), 'email' => $sales->email, 'password' => 'Synthetic-Only-123!']);
                    $this->assertSame(302, $status);
                    [, $form] = $this->send($clients[$i], $base.'/sales/submissions/create');
                    $image = $root.'/synthetic.png';
                    file_put_contents($image, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aV1cAAAAASUVORK5CYII='));
                    $payloads[$i] = ['_token' => $this->token($form), 'customer_name' => 'SYNTHETIC_SALES', 'customer_phone' => $phone,
                        'facebook_url' => 'https://facebook.com/synthetic.http.sales', 'vehicle_model' => 'hilux', 'vehicle_color' => 'Synthetic',
                        'first_registration_year' => 2024, 'license_plate' => '51K90082', 'evidence_image' => new \CURLFile($image, 'image/png')];
                    $urls[$i] = $base.'/sales/submissions';

                    continue;
                }
                [$status] = $this->send($clients[$i], $base.'/'.$slug.'/registrations/step-one',
                    ['_token' => $token, 'customer_name' => 'SYNTHETIC_HTTP', 'phone' => $phone, 'vehicle_description' => 'FJ']);
                $this->assertSame(302, $status);
                [, $form] = $this->send($clients[$i], $base.'/'.$slug.'/registrations/step-two');
                preg_match('/name="_draft" value="([^"]+)"/', $form, $draft);
                $this->assertTrue(isset($draft[1]), 'Draft identity missing.');
                $payloads[$i] = $address + ['_token' => $this->token($form), '_flow' => 'two-step', '_draft' => $draft[1],
                    'license_plate' => $case === 'plate' ? '51K90081' : '51K9008'.($i + 1), 'first_registration_year' => 2024,
                    'facebook_url' => 'https://facebook.com/synthetic.http.customer'.$i, 'consent' => '1'];
                $urls[$i] = $base.'/'.$slug.'/registrations';
            }
            $approvalCase = str_starts_with($case, 'approval-');
            if ($case === 'supplement-pair') {
                [$status] = $this->send($clients[0], $urls[0], $payloads[0]);
                $this->assertSame(302, $status);
                $target = CustomerSubmission::where('dealer_id', $dealers[0]->id)->firstOrFail();
                $admin = User::factory()->admin()->create();
                $users[] = $admin->id;
                $service = app(SubmissionVerification::class);
                $service->adminUpdate($admin, $target, ['action' => 'supplement', 'supplement_reason' => 'facebook_unavailable',
                    'verification_version' => $service->version($target)]);
                [$status, $form] = $this->send($clients[0], 'http://127.0.0.1:'.$ports[0].'/'.$dealers[0]->slug.'/registrations/lookup',
                    ['_token' => $payloads[0]['_token'], 'phone' => $target->phone, 'license_plate' => $target->license_plate]);
                $this->assertSame(200, $status);
                foreach (['supplement_key', 'verification_version'] as $field) {
                    preg_match('/name="'.$field.'" value="([^"]+)"/', $form, $match);
                    $this->assertTrue(isset($match[1]), 'Owned supplementation form missing.');
                    $fields[$field] = $match[1];
                }
                $payloads[0] = $fields + ['_token' => $this->token($form), 'facebook_profile_url' => 'https://facebook.com/synthetic.http.corrected'];
                $payloads[1] = $payloads[0];
                foreach (curl_getinfo($clients[0], CURLINFO_COOKIELIST) as $cookie) {
                    curl_setopt($clients[1], CURLOPT_COOKIELIST, $cookie);
                }
                foreach ([0, 1] as $i) {
                    $urls[$i] = 'http://127.0.0.1:'.$ports[$i].'/'.$dealers[0]->slug.'/registrations/lookup/supplement';
                }
            }
            if ($case === 'verification-pair') {
                $sales = User::factory()->active()->create(['dealer_id' => $dealers[0]->id]);
                $users[] = $sales->id;
                $target = CustomerSubmission::factory()->create(['sales_id' => $sales->id,
                    'customer_name' => 'SYNTHETIC_VERIFICATION_RACE', 'phone' => '0990000083', 'license_plate' => '51K99983']);
                foreach ([0, 1] as $i) {
                    $admin = User::factory()->admin()->create(['password' => bcrypt('Synthetic-Only-123!')]);
                    $users[] = $admin->id;
                    $base = 'http://127.0.0.1:'.$ports[$i];
                    [, $login] = $this->send($clients[$i], $base.'/login');
                    [$status] = $this->send($clients[$i], $base.'/login', ['_token' => $this->token($login),
                        'email' => $admin->email, 'password' => 'Synthetic-Only-123!']);
                    $this->assertSame(302, $status);
                    [$status, $form] = $this->send($clients[$i], $base.'/admin/submissions/'.$target->id);
                    $this->assertSame(200, $status);
                    preg_match('/name="verification_version" value="([^"]+)"/', $form, $version);
                    $this->assertTrue(isset($version[1]), 'Verification version missing.');
                    $payloads[$i] = ['_token' => $this->token($form), 'verification_version' => $version[1],
                        'action' => 'group', 'group_progress' => $i === 0 ? 'requested' : 'member'];
                    $urls[$i] = $base.'/admin/submissions/'.$target->id.'/verification';
                }
            }
            if ($approvalCase) {
                // Drafts already exist before the competing pending row(s), as in a real stale D02.
                $admin = User::factory()->admin()->create(['password' => bcrypt('Synthetic-Only-123!')]);
                $users[] = $admin->id;
                $targets = [];
                foreach ($case === 'approval-pair' ? [0, 1] : [1] as $i) {
                    $sales = User::factory()->active()->create(['dealer_id' => $dealers[$i]->id]);
                    $users[] = $sales->id;
                    $target = CustomerSubmission::factory()->create(['sales_id' => $sales->id, 'customer_name' => 'SYNTHETIC_APPROVAL_RACE',
                        'phone' => $case === 'approval-plate' ? '0990000083' : '099000008'.($case === 'approval-pair' ? $i + 1 : 1),
                        'license_plate' => $case === 'approval-plate' ? '51K90081' : '51K9998'.$i]);
                    $targets[] = $target;
                    $base = 'http://127.0.0.1:'.$ports[$i];
                    [, $login] = $this->send($clients[$i], $base.'/login');
                    [$status] = $this->send($clients[$i], $base.'/login', ['_token' => $this->token($login),
                        'email' => $admin->email, 'password' => 'Synthetic-Only-123!']);
                    $this->assertSame(302, $status);
                    [, $form] = $this->send($clients[$i], $base.'/admin/submissions/'.$target->id);
                    $payloads[$i] = ['_token' => $this->token($form), 'review_submission_id' => $target->id];
                    $urls[$i] = $base.'/admin/submissions/'.$target->id.'/approve';
                }
                if ($case === 'approval-pair') {
                    DB::table('customer_submissions')->where('id', $targets[1]->id)->update(['phone' => $targets[0]->phone]);
                }
            }
            if (in_array($case, ['same-draft', 'retry'])) {
                foreach (curl_getinfo($clients[0], CURLINFO_COOKIELIST) as $cookie) {
                    curl_setopt($clients[1], CURLOPT_COOKIELIST, $cookie);
                }
                $payloads[1] = $payloads[0];
                $urls[1] = 'http://127.0.0.1:'.$ports[1].'/'.$dealers[0]->slug.'/registrations';
            }
            // Verify real CSRF enforcement, independently of concurrency.
            $bad = $payloads[0];
            unset($bad['_token']);
            [$status] = $this->send($clients[0], $urls[0], $bad);
            $this->assertSame(419, $status);
            if ($case === 'failure-retry') {
                [$status] = $this->send($clients[0], $urls[0], $payloads[0], ['X-Fixture-Fail: 1']);
                $this->assertSame(500, $status);
                $this->assertSame($baseline, DB::table('customer_submissions')->count());
                $this->assertSame(0, DB::table('dealer_registration_receipts')->where('submission_id', '>', 0)->count());
                [$status] = $this->send($clients[0], $urls[0], $payloads[0]);
                $this->assertSame(302, $status);
                $this->assertSame($baseline + 1, DB::table('customer_submissions')->count());

                return;
            }
            $multi = curl_multi_init();
            foreach ([0, 1] as $i) {
                curl_setopt_array($clients[$i], [CURLOPT_URL => $urls[$i], CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $case === 'legacy-sales' && $i === 1 ? $payloads[$i] : http_build_query($payloads[$i]),
                    CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Race-Barrier: 1', ...($case === 'rollback' ? ($i === 0 ? ['X-Fixture-Fail: 1'] : ['X-Fixture-Delay: 1']) : [])]]);
                curl_multi_add_handle($multi, $clients[$i]);
            }
            do {
                curl_multi_exec($multi, $running);
                if ($running) {
                    curl_multi_select($multi, 0.1);
                }
            } while ($running);
            $statuses = [];
            foreach ($clients as $curl) {
                $this->assertSame(0, curl_errno($curl), 'Concurrent loopback HTTP failed.');
                $statuses[] = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                curl_multi_remove_handle($multi, $curl);
            }
            curl_multi_close($multi);
            $this->assertNotContains(503, $statuses);
            if ($case === 'rollback') {
                $this->assertContains(500, $statuses);
            } else {
                $this->assertNotContains(500, $statuses);
            }
            $ids = array_map(fn ($d) => $d->id, $dealers);
            $records = DB::table('customer_submissions')->whereIn('dealer_id', $ids)->get();
            $expectedCount = $case === 'approval-pair' ? 2 : 1;
            $this->assertCount($expectedCount, $records);
            $this->assertSame($approvalCase && $case !== 'approval-pair' ? 'approved' : 'pending', $records[0]->status);
            $this->assertSame($baseline + $expectedCount, DB::table('customer_submissions')->count());
            $receipts = DB::table('dealer_registration_receipts')->whereIn('submission_id', $records->pluck('id'))->count();
            $this->assertSame($case === 'legacy-sales' || $approvalCase || $case === 'verification-pair' ? 0 : 1, $receipts);
            if ($case === 'verification-pair') {
                $this->assertSame([302, 302], $statuses);
                $state = DB::table('submission_verification_states')->where('submission_id', $target->id)->first();
                $this->assertSame(1, $state->version);
                $this->assertContains($state->group_progress, ['requested', 'member']);
                $this->assertSame(1, DB::table('submission_verification_audits')->where('submission_id', $target->id)->count());
                $this->assertSame(0, DB::table('moderation_reviews')->where('customer_submission_id', $target->id)->count());
                $this->assertNull($records[0]->approved_facebook_identity);
                $staleCount = 0;
                foreach ([0, 1] as $i) {
                    [, $html] = $this->send($clients[$i], 'http://127.0.0.1:'.$ports[$i].'/admin/submissions/'.$target->id);
                    if (str_contains($html, 'Thông tin đã thay đổi.')) {
                        $staleCount++;
                    }
                }
                $this->assertSame(1, $staleCount, 'Exactly one Admin must receive a stale-write warning.');
            }
            if ($case === 'supplement-pair') {
                sort($statuses);
                $this->assertSame([302, 422], $statuses);
                $state = DB::table('submission_verification_states')->where('submission_id', $target->id)->first();
                $this->assertSame('submitted', $state->supplement_state);
                $this->assertSame(2, $state->version);
                $this->assertSame(1, DB::table('submission_verification_audits')->where('submission_id', $target->id)->where('action', 'response')->count());
                $this->assertSame(0, DB::table('moderation_reviews')->where('customer_submission_id', $target->id)->count());
                $this->assertNull($records[0]->approved_facebook_identity);
            }
            if ($approvalCase) {
                $this->assertSame($case === 'approval-pair' ? [422, 422] : [302, 302], $statuses);
                $this->assertSame($case === 'approval-pair' ? 0 : 1,
                    DB::table('moderation_reviews')->whereIn('customer_submission_id', $records->pluck('id'))->count());
                if ($case === 'approval-pair') {
                    $this->assertTrue($records->every(fn ($record) => $record->status === 'pending' && $record->approved_facebook_identity === null));
                }
            }
            if (in_array($case, ['same-draft', 'retry'])) {
                $this->assertSame([302, 302], $statuses);
                [$status] = $this->send($clients[0], $urls[0], $payloads[0]);
                $this->assertSame(302, $status);
                $this->assertSame($baseline + 1, DB::table('customer_submissions')->count());
            }
        } finally {
            foreach ($servers as $i => $server) {
                if ($server->isRunning() && PHP_OS_FAMILY === 'Windows' && isset($ports[$i])) {
                    $pidFile = $root.'/'.$ports[$i].'.pid';
                    if (is_file($pidFile)) {
                        $pid = (int) file_get_contents($pidFile);
                        (new Process([getenv('SystemRoot').'/System32/WindowsPowerShell/v1.0/powershell.exe',
                            '-NoProfile', '-NonInteractive', '-Command', 'Stop-Process -Id '.$pid.' -ErrorAction SilentlyContinue'], base_path(), null, null, 10))->run();
                    }
                }
                if ($server->isRunning()) {
                    $server->stop(1);
                }
            }
            foreach ($clients as $curl) {
                curl_close($curl);
            }
            // Delete only records owned by these newly-created testing fixtures.
            $ids = array_map(fn ($d) => $d->id, $dealers);
            if ($ids) {
                $records = DB::table('customer_submissions')->whereIn('dealer_id', $ids)->pluck('id');
                DB::table('dealer_registration_receipts')->whereIn('submission_id', $records)->delete();
                DB::table('submission_verification_audits')->whereIn('submission_id', $records)->delete();
                DB::table('submission_verification_states')->whereIn('submission_id', $records)->delete();
                DB::table('moderation_reviews')->whereIn('customer_submission_id', $records)->delete();
                DB::table('customer_submissions')->whereIn('id', $records)->delete();
                DB::table('users')->whereIn('id', $users)->delete();
                DB::table('dealers')->whereIn('id', $ids)->delete();
            }
            File::deleteDirectory($root);
        }
    }
}

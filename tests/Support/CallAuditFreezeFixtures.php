<?php

namespace Tests\Support;

use App\Models\Award;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class CallAuditFreezeFixtures
{
    public readonly string $tag;

    private array $ids = [];

    private int $sequence = 0;

    private ?User $reviewer = null;

    public function __construct()
    {
        self::assertLocal();
        $this->tag = 'p09h-'.bin2hex(random_bytes(10));
        $this->journal();
    }

    public static function assertLocal(): void
    {
        TestDatabase::assertIsolated();
        $db = DB::connection();
        if ($db->getConfig('host') !== '127.0.0.1' || $db->getConfig('database') !== 'toyota_testing'
            || $db->getConfig('url') || $db->getConfig('unix_socket') || $db->getConfig('read') || $db->getConfig('write')) {
            throw new RuntimeException('Freeze fixtures require exact local test configuration before PDO.');
        }
        if (DB::selectOne('SELECT DATABASE() AS db')->db !== 'toyota_testing') {
            throw new RuntimeException('Wrong actual test database.');
        }
    }

    public function admin(): User
    {
        return $this->create(fn () => User::factory()->admin()->create([
            'name' => $this->tag, 'email' => $this->tag.'-'.bin2hex(random_bytes(4)).'@example.test',
        ]));
    }

    public function dealer(): Dealer
    {
        return $this->create(fn () => Dealer::factory()->create(['name' => $this->tag, 'code' => $this->tag.'-'.bin2hex(random_bytes(4))]));
    }

    public function submission(Dealer $dealer, array $overrides = []): CustomerSubmission
    {
        $this->reviewer ??= auth()->user() instanceof User && in_array(auth()->id(), $this->ids['users'] ?? [], true)
            ? auth()->user() : $this->admin();
        $n = ++$this->sequence;

        return $this->create(fn () => CustomerSubmission::factory()->approved()->create(array_replace([
            'customer_name' => $this->tag, 'source' => 'dealer_qr', 'sales_id' => null, 'dealer_id' => $dealer->id,
            'reviewed_by' => $this->reviewer->id, 'reviewed_at' => '2026-11-02', 'submitted_at' => '2026-10-02',
            'phone' => sprintf('090%07d', $n), 'phone_normalized' => sprintf('090%07d', $n),
            'license_plate' => sprintf('51K-%05d', $n), 'vehicle_description' => 'Toyota fixture',
            'address_line' => 'Isolated fixture', 'province_code' => '79', 'ward_code' => '26734',
            'consented_at' => '2026-10-02', 'consent_version' => 'p09h-test-only',
        ], $overrides)));
    }

    public function award(): Award
    {
        return $this->create(fn () => Award::factory()->create([
            'title' => $this->tag, 'period_start' => '2026-10-01', 'period_end' => '2026-11-01',
        ]));
    }

    public function change(Model $model, array $values): void
    {
        if (! in_array($model->id, $this->ids[$model->getTable()] ?? [], true)) {
            throw new RuntimeException('Cannot change a record not owned by this fixture run.');
        }
        DB::transaction(fn () => DB::table($model->getTable())->where('id', $model->id)->update($values));
    }

    public function cleanup(): void
    {
        self::assertLocal();
        if (DB::transactionLevel() !== 0) {
            throw new RuntimeException('Refusing cleanup inside an unexpected transaction.');
        }
        DB::transaction(function () {
            // Also collect an in-flight child process's tagged late submission after workers stop.
            $children = DB::table('customer_submissions')->where('customer_name', $this->tag)
                ->whereIn('dealer_id', $this->ids['dealers'] ?? [])->pluck('id')->all();
            $this->ids['customer_submissions'] = array_values(array_unique(array_merge($this->ids['customer_submissions'] ?? [], $children)));
            $batches = DB::table('call_audit_batches')->whereIn('created_by', $this->ids['users'] ?? [])
                ->where(fn ($query) => $query->where('create_request_key', 'like', $this->tag.'-%')
                    ->orWhereIn('award_id', $this->ids['awards'] ?? []))->pluck('id');
            $items = DB::table('call_audit_items')->whereIn('batch_id', $batches)->pluck('id');
            if (Schema::hasTable('dealer_penalty_entries')) {
                $entries = DB::table('dealer_penalty_entries')->whereIn('submission_id', $this->ids['customer_submissions'] ?? [])->pluck('id');
                DB::table('dealer_penalty_events')->whereIn('entry_id', $entries)->delete();
                DB::table('dealer_penalty_evidence')->whereIn('entry_id', $entries)->delete();
                DB::table('dealer_penalty_entries')->whereIn('id', $entries)->delete();
            }
            if (Schema::hasTable('award_publications')) {
                DB::table('award_publications')->whereIn('award_id', $this->ids['awards'] ?? [])->delete();
            }
            DB::table('award_winners')->whereIn('award_id', $this->ids['awards'] ?? [])->delete();
            DB::table('call_audit_events')->whereIn('batch_id', $batches)->delete();
            DB::table('call_audit_attempts')->whereIn('item_id', $items)->delete();
            DB::table('call_audit_items')->whereIn('batch_id', $batches)->delete();
            foreach ($batches->sortDesc() as $batchId) {
                DB::table('call_audit_batches')->where('id', $batchId)->delete();
            }
            DB::table('moderation_reviews')->whereIn('customer_submission_id', $this->ids['customer_submissions'] ?? [])->delete();
            foreach (['submission_verification_audits', 'submission_verification_states'] as $table) {
                DB::table($table)->whereIn('submission_id', $this->ids['customer_submissions'] ?? [])->delete();
            }
            foreach (['customer_submissions', 'awards', 'users', 'dealers'] as $table) {
                DB::table($table)->whereIn('id', $this->ids[$table] ?? [])->delete();
            }
        });
        $this->journal(true);
    }

    private function create(callable $factory): mixed
    {
        return DB::transaction(function () use ($factory) {
            $model = $factory();
            $this->ids[$model->getTable()][] = $model->id;
            // Write IDs before commit; a crash leaves a private, scoped cleanup receipt.
            $this->journal();

            return $model;
        });
    }

    private function journal(bool $cleaned = false): void
    {
        $directory = storage_path('app/private/p09h-fixtures');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
            throw new RuntimeException('Cannot create fixture journal.');
        }
        if (file_put_contents($directory.'/'.$this->tag.'.json', json_encode(['tag' => $this->tag, 'ids' => $this->ids, 'cleaned' => $cleaned], JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException('Cannot persist fixture cleanup receipt.');
        }
    }
}

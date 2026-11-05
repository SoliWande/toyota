<?php

namespace App\Services;

use App\Models\Dealer;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

final class LocalToyotaDealerImport
{
    public const SOURCE_HASH = '4b439594ad65fca121ffc0f0233e73f61d7296d09ff722c251e9b46ecc0bc205';

    public function guard(): array
    {
        $db = DB::connection();
        if (! app()->environment('local') || $db->getDriverName() !== 'mysql'
            || ! in_array($db->getConfig('host'), ['127.0.0.1', 'localhost'], true)
            || $db->getConfig('database') !== 'toyota' || $db->getConfig('read') || $db->getConfig('write')) {
            throw new RuntimeException('Requires single local loopback MySQL toyota.');
        }
        if ($db->getConfig('host') === 'localhost' && ! in_array(gethostbyname('localhost'), ['127.0.0.1'], true)) {
            throw new RuntimeException('localhost is not loopback.');
        }
        $actual = (array) $db->selectOne('SELECT DATABASE() AS db, @@port AS port, @@server_uuid AS uuid, @@foreign_key_checks AS fk');
        if ($actual['db'] !== 'toyota' || (int) $actual['port'] !== 3306 || (int) $actual['fk'] !== 1) {
            throw new RuntimeException('Actual database/port/FK guard mismatch.');
        }

        return $actual;
    }

    public function source(): array
    {
        $path = database_path('data/toyota_dealers.json');
        if (hash_file('sha256', $path) !== self::SOURCE_HASH) {
            throw new RuntimeException('Approved snapshot checksum mismatch.');
        }
        $source = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        Validator::make($source, [
            'source_url' => ['required', 'in:https://www.toyota.com.vn/lien-he-dai-ly'],
            'retrieved_at' => ['required', 'date_format:Y-m-d'], 'count' => ['required', 'integer', 'in:87'],
            'dealers' => ['required', 'array', 'size:87'],
            'dealers.*' => ['array:toyota_source_id,code,name,province,address,phone,website,facebook_url,zalo_url,opening_hours'],
            'dealers.*.toyota_source_id' => ['required', 'integer', 'min:1', 'distinct'],
            'dealers.*.code' => ['required', 'string', 'max:50', 'distinct'],
            'dealers.*.name' => ['required', 'string', 'max:255', 'distinct'],
            'dealers.*.province' => ['required', 'string', 'max:255'],
            'dealers.*.address' => ['required', 'string', 'max:2000'],
            'dealers.*.phone' => ['required', 'string', 'max:30'],
            'dealers.*.website' => ['nullable', 'url:http,https', 'max:2048'],
            'dealers.*.facebook_url' => ['nullable', 'url:http,https', 'max:2048'],
            'dealers.*.zalo_url' => ['nullable', 'url:http,https', 'max:2048'],
            'dealers.*.opening_hours' => ['required', 'string', 'max:2000'],
        ])->validate();

        return $source['dealers'];
    }

    /** No geographic inference or name-based merge. */
    public function plan(array $rows): array
    {
        $items = [];
        $slugs = Dealer::pluck('slug')->all();
        foreach ($rows as $row) {
            $match = Dealer::where('toyota_source_id', $row['toyota_source_id'])->first();
            $collision = Dealer::where('code', $row['code'])->when($match, fn ($q) => $q->where('id', '!=', $match->id))->exists();
            $nameCollision = ! $match && Dealer::where('name', $row['name'])->exists();
            $action = 'INSERT';
            if ($match) {
                $same = true;
                foreach ($row as $field => $value) {
                    $same = $same && $match->getRawOriginal($field) === $value;
                }
                $same = $same && $match->is_active;
                $action = $same ? 'SKIP' : 'CONFLICT';
                $slug = $match->slug;
            } else {
                $base = substr(Str::slug($row['name']), 0, 180) ?: 'dai-ly';
                $slug = $base;
                for ($suffix = 2; in_array($slug, DealerSlugs::RESERVED, true) || in_array($slug, $slugs, true); $suffix++) {
                    $slug = $base.'-'.$suffix;
                }
                $slugs[] = $slug;
            }
            if ($collision || $nameCollision) {
                $action = 'CONFLICT';
            }
            $items[] = ['action' => $action, 'source' => $row, 'slug' => $slug];
        }
        $counts = array_fill_keys(['INSERT', 'UPDATE', 'SKIP', 'CONFLICT'], 0);
        foreach ($items as $item) {
            $counts[$item['action']]++;
        }

        return ['source_hash' => self::SOURCE_HASH, 'policy' => 'snapshot-province-unchanged-active-true-local', 'counts' => $counts, 'items' => $items];
    }

    /** Hashes only, never persist customer/account values in receipts. */
    public function capture(?string $schema = null, ?ConnectionInterface $connection = null): array
    {
        $db = $connection ?? DB::connection();
        $schema ??= $db->getDatabaseName();
        if (! preg_match('/\Atoyota(?:_restore_[a-z0-9_]+|_testing)?\z/', $schema)) {
            throw new RuntimeException('Unexpected audit schema.');
        }
        $tables = $db->select('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE=? ORDER BY TABLE_NAME', [$schema, 'BASE TABLE']);
        $result = [];
        foreach ($tables as $table) {
            $name = $table->TABLE_NAME;
            if (! preg_match('/\A[a-zA-Z0-9_]+\z/', $name) || $table->ENGINE !== 'InnoDB') {
                throw new RuntimeException('Unexpected table name or non-InnoDB engine.');
            }
            $values = $db->select("SELECT * FROM `$schema`.`$name`");
            $hashes = array_map(function ($value) {
                $value = (array) $value;
                ksort($value);

                return serialize($value);
            }, $values);
            sort($hashes, SORT_STRING);
            $create = array_values((array) $db->selectOne("SHOW CREATE TABLE `$schema`.`$name`"))[1];
            $result[$name] = ['count' => count($values), 'sha256' => hash('sha256', serialize($hashes)), 'schema_sha256' => $this->schemaHash($create, $schema)];
        }
        $objects = [];
        foreach ([
            'triggers' => ['TRIGGERS', 'TRIGGER_SCHEMA', 'TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, ACTION_TIMING, ACTION_STATEMENT, SQL_MODE, DEFINER'],
            'routines' => ['ROUTINES', 'ROUTINE_SCHEMA', 'ROUTINE_NAME, ROUTINE_TYPE, ROUTINE_DEFINITION, SQL_MODE, DEFINER'],
            'events' => ['EVENTS', 'EVENT_SCHEMA', 'EVENT_NAME, EVENT_DEFINITION, EVENT_TYPE, STATUS, SQL_MODE, DEFINER'],
        ] as $key => [$table, $field, $select]) {
            $values = $db->select("SELECT $select FROM information_schema.$table WHERE $field=? ORDER BY 1", [$schema]);
            $objects[$key] = ['count' => count($values), 'sha256' => hash('sha256', serialize($values))];
        }

        return ['tables' => $result, 'objects' => $objects];
    }

    public function schemaHash(string $create, string $schema): string
    {
        $create = preg_replace('/ AUTO_INCREMENT=\d+/', '', $create);
        // MySQL SHOW CREATE may spell the charset explicitly after dump/restore.
        // A collation already determines exactly that charset; preserve collation.
        $create = preg_replace('/ CHARACTER SET ([a-z0-9]+)(?= COLLATE \1_)/i', '', $create);
        $create = str_replace('`'.$schema.'`.', '`SCHEMA`.', $create);

        return hash('sha256', $create);
    }

    /** Caller must establish transaction, environment guard and exclusive execution. */
    public function insertBatch(array $plan): array
    {
        if (DB::transactionLevel() < 1 || $plan['counts'] !== ['INSERT' => 87, 'UPDATE' => 0, 'SKIP' => 0, 'CONFLICT' => 0]) {
            throw new RuntimeException('Requires transaction and exactly 87 approved inserts.');
        }
        // This method is also exercised only in explicitly isolated tests.
        if (app()->environment('testing')) {
            if (DB::connection()->getDatabaseName() !== 'toyota_testing' || DB::selectOne('SELECT DATABASE() AS db')->db !== 'toyota_testing') {
                throw new RuntimeException('Unsafe testing connection.');
            }
        } else {
            $this->guard();
        }
        $created = [];
        foreach ($plan['items'] as $item) {
            $row = $item['source'];
            if (Dealer::where('toyota_source_id', $row['toyota_source_id'])->orWhere('code', $row['code'])->orWhere('slug', $item['slug'])->exists()) {
                throw new RuntimeException('Collision immediately before insert.');
            }
            $attributes = $row;
            unset($attributes['toyota_source_id']);
            $dealer = new Dealer($attributes + ['is_active' => true]);
            $dealer->toyota_source_id = $row['toyota_source_id'];
            $dealer->save();
            if ($dealer->slug !== $item['slug']) {
                throw new RuntimeException('Slug changed after planning.');
            }
            $raw = $dealer->fresh()->getRawOriginal();
            $created[] = ['id' => $dealer->id, 'toyota_source_id' => $row['toyota_source_id'], 'code' => $row['code'], 'before_sha256' => null, 'after_sha256' => hash('sha256', serialize($raw))];
        }

        return $created;
    }
}

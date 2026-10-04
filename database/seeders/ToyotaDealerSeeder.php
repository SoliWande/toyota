<?php

namespace Database\Seeders;

use App\Models\Dealer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class ToyotaDealerSeeder extends Seeder
{
    public function run(): void
    {
        $snapshot = json_decode(file_get_contents(database_path('data/toyota_dealers.json')), true, flags: JSON_THROW_ON_ERROR);
        Validator::make($snapshot, [
            'source_url' => ['required', 'in:https://www.toyota.com.vn/lien-he-dai-ly'],
            'retrieved_at' => ['required', 'date_format:Y-m-d'],
            'count' => ['required', 'integer', 'min:1'],
            'dealers' => ['required', 'array', 'size:'.($snapshot['count'] ?? 0)],
            'dealers.*.toyota_source_id' => ['required', 'integer', 'min:1', 'distinct'],
            'dealers.*.code' => ['required', 'string', 'max:50', 'distinct'],
            'dealers.*.name' => ['required', 'string', 'max:255', 'distinct'],
            'dealers.*.province' => ['required', 'string', 'max:255'],
            'dealers.*.phone' => ['required', 'string', 'max:30'],
            'dealers.*.address' => ['required', 'string', 'max:2000'],
            'dealers.*.website' => ['nullable', 'url:http,https', 'max:2048'],
            'dealers.*.facebook_url' => ['nullable', 'url:http,https', 'max:2048'],
            'dealers.*.zalo_url' => ['nullable', 'url:http,https', 'max:2048'],
            'dealers.*.opening_hours' => ['required', 'string', 'max:2000'],
        ])->validate();

        DB::transaction(function () use ($snapshot) {
            $existing = Dealer::query()->lockForUpdate()->get();
            foreach ($snapshot['dealers'] as $row) {
                $dealer = $existing->firstWhere('toyota_source_id', $row['toyota_source_id']);
                if (! $dealer) {
                    $matches = $existing->filter(fn ($item) => $item->code === $row['code']
                        || Str::lower(Str::squish($item->name)) === Str::lower(Str::squish($row['name'])));
                    if ($matches->count() > 1) {
                        throw new RuntimeException('Ambiguous existing dealers for Toyota source ID '.$row['toyota_source_id'].'. No records were imported.');
                    }
                    $dealer = $matches->first();
                    if ($dealer && $dealer->toyota_source_id !== null) {
                        throw new RuntimeException('Conflicting Toyota source identity. No records were imported.');
                    }
                }
                if (! $dealer) {
                    $dealer = new Dealer(['code' => $row['code'], 'is_active' => true]);
                    $existing->push($dealer);
                }
                // Keep existing IDs, internal codes, status and all account/history relations.
                unset($row['code']);
                $dealer->forceFill($row);
                if (! $dealer->exists || $dealer->isDirty()) {
                    $dealer->save();
                }
            }
        });

        $this->command?->info('Imported '.$snapshot['count'].' Toyota directory locations from snapshot '.$snapshot['retrieved_at'].'.');
    }
}

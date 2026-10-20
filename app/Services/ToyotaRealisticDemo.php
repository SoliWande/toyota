<?php

namespace App\Services;

use App\Actions\ApproveCustomerSubmission;
use App\Actions\RejectCustomerSubmission;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;

class ToyotaRealisticDemo
{
    public const TAG = '[TOYOTA-REALISTIC-V1]';

    public const EMAIL = 'dev-admin@toyota.test';

    public bool $confirmed = false;

    public function assertEnabled(): void
    {
        if (! config('realistic_demo.enabled')
            || (! app()->environment(['local', 'testing']) && ! (app()->environment('production') && config('realistic_demo.allow_production')))) {
            throw new LogicException('Enable TOYOTA_DEMO_ENABLED and, on production demo VMs, TOYOTA_DEMO_ALLOW_PRODUCTION.');
        }
    }

    public function status(): array
    {
        $admin = User::where('email', self::EMAIL)->first();
        if (! $admin) {
            return ['dataset' => self::TAG, 'installed' => false];
        }
        $manifest = $this->manifest($admin);

        return ['dataset' => self::TAG, 'installed' => true, 'manifest' => $manifest,
            'users_present' => User::whereIn('id', $manifest['users'])->count(),
            'submissions_present' => CustomerSubmission::whereIn('id', $manifest['submissions'])->count(),
            'statuses' => CustomerSubmission::whereIn('id', $manifest['submissions'])->select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all()];
    }

    public function seed(): void
    {
        $this->assertEnabled();
        if (! $this->confirmed) {
            throw new LogicException('Database confirmation required.');
        }
        DB::transaction(function () {
            // Serialize seed/cleanup and never edit the directory rows.
            $directory = Dealer::orderBy('id')->lockForUpdate()->get();
            $existing = User::where('email', self::EMAIL)->first();
            if ($existing) {
                $manifest = $this->manifest($existing);
                if (User::whereIn('id', $manifest['users'])->count() !== count($manifest['users'])
                    || CustomerSubmission::whereIn('id', $manifest['submissions'])->count() !== 1500) {
                    throw new LogicException('Incomplete demo dataset: inspect before making any changes.');
                }

                return; // Preserve later customer reviews and account edits.
            }
            if (User::where('email', 'like', 'toyota.realistic.v1.%@example.com')->exists()
                || CustomerSubmission::where('notes', self::TAG)
                    ->orWhere('facebook_url_normalized', 'like', 'https://www.facebook.com/toyota.demo.synthetic.v1.%')
                    ->orWhere('license_plate', 'like', 'DEMO-V1-%')
                    ->orWhereBetween('phone', ['0000700000', '0000701499'])->exists()) {
                throw new LogicException('Reserved demo identities already exist; refusing to overwrite.');
            }
            $dealers = $directory->whereNotNull('toyota_source_id')->where('is_active', true)
                ->filter(fn ($d) => $this->province($d) !== null)
                ->sortBy(fn ($d) => $d->province.' '.$d->code)->values();
            if ($directory->whereNotNull('toyota_source_id')->count() !== 87 || $dealers->count() < 25) {
                throw new LogicException('Requires the existing 87 Toyota directory entries, including at least 25 active locations. Import separately after review.');
            }
            // Spread activity across the directory instead of selecting one region only.
            $dealers = collect(range(0, 24))->map(fn ($i) => $dealers[(int) floor($i * $dealers->count() / 25)]);
            $password = config('realistic_demo.password');
            if (! is_string($password) || strlen($password) < 8) {
                throw new LogicException('Set TOYOTA_DEMO_PASSWORD to a secret of at least 8 characters.');
            }
            $hash = Hash::make($password);
            $now = CarbonImmutable::now()->startOfSecond();
            $start = $now->startOfMonth()->subMonths(5);
            $users = [];
            $submissions = [];
            $admin = $this->user('admin', 'Quản trị demo', null, $hash, $start);
            $users[] = $admin->id;
            $sales = collect();
            for ($i = 0; $i < 120; $i++) {
                $sales->push($this->user('sales.'.sprintf('%03d', $i), $this->name($i), $dealers[$i % 25]->id, $hash, $start));
                $users[] = $sales->last()->id;
            }
            foreach ($dealers as $i => $dealer) {
                // A real/previously created manager is never replaced.
                if (! $dealer->manager()->exists()) {
                    $users[] = $this->user('dealer.'.$i, 'Quản lý demo '.$dealer->name, $dealer->id, $hash, $start)->id;
                }
            }
            $transfers = [];
            for ($month = 0; $month < 6; $month++) {
                if ($month === 3) {
                    for ($i = 0; $i < 5; $i++) {
                        $from = $sales[$i]->dealer_id;
                        $sales[$i]->forceFill(['dealer_id' => $dealers[($i + 7) % 25]->id, 'updated_at' => $start->addMonths(3)])->save();
                        $transfers[] = ['sales_id' => $sales[$i]->id, 'from' => $from, 'to' => $sales[$i]->dealer_id, 'at' => $start->addMonths(3)->toDateTimeString()];
                    }
                }
                $monthStart = $start->addMonths($month);
                $end = $monthStart->addMonth()->min($now);
                $seconds = max(1, $monthStart->diffInSeconds($end));
                for ($slot = 0; $slot < 250; $slot++) {
                    $i = $month * 250 + $slot;
                    $qr = $slot % 8 === 0;
                    $leader = $month * 3 + 5;
                    // Strong, medium and occasional Sales, with a different leading team each month.
                    $owner = $sales[$slot < 85 ? $leader : ($slot < 165 ? ($slot % 40) : ($slot * 37 + $month * 13) % 120)];
                    $dealer = $qr ? $dealers[$slot < 85 ? $leader % 25 : $slot % 25] : $directory->firstWhere('id', $owner->dealer_id);
                    [$province, $ward] = $this->address($dealer, $i);
                    $at = $monthStart->addSeconds((int) (($slot * 15485863 + $month * 3571) % $seconds));
                    $reviewAt = $at->addHours(2 + $i % 70)->min($now);
                    $phone = sprintf('000%07d', 700000 + $i);
                    // Facebook is mandatory in current domain logic; use explicit synthetic identities, never scrape profiles.
                    $facebook = 'https://www.facebook.com/toyota.demo.synthetic.v1.'.sprintf('%04d', $i);
                    $record = new CustomerSubmission;
                    $record->forceFill([
                        'source' => $qr ? 'dealer_qr' : 'sales', 'sales_id' => $qr ? null : $owner->id,
                        'dealer_id' => $dealer->id, 'customer_name' => $this->name($i + 131),
                        'facebook_url' => $qr ? null : $facebook, 'phone' => $phone, 'phone_normalized' => $phone,
                        'notes' => self::TAG, 'address_line' => (10 + $i % 180).' Đường demo '.$ward['name'],
                        'province_code' => $province['code'], 'province_name' => $province['name'],
                        'ward_code' => $ward['code'], 'ward_name' => $ward['name'],
                        'vehicle_model' => $i % 10 < 6 ? 'veloz' : 'hilux',
                        'vehicle_description' => $i % 10 < 6 ? 'Toyota Veloz Cross' : 'Toyota Hilux',
                        'first_registration_year' => $at->year - $i % 4,
                        'vehicle_color' => ['Trắng ngọc trai', 'Bạc', 'Đen', 'Xám', 'Đỏ'][$i % 5],
                        'license_plate' => sprintf('DEMO-V1-%04d', $i),
                        'consented_at' => $qr ? $at : null, 'consent_version' => $qr ? 'demo-v1' : null,
                        'submitted_at' => $at, 'created_at' => $at, 'updated_at' => $at,
                    ])->save();
                    $submissions[] = $record->id;
                    $bucket = ($i * 37) % 100;
                    // Scope the simulated clock to this review and always restore it, including on exceptions.
                    $clock = Carbon::getTestNow();
                    try {
                        Carbon::setTestNow($reviewAt);
                        if ($bucket < 75) {
                            app(ApproveCustomerSubmission::class)->execute($admin, $record, self::TAG, $qr ? $facebook : null);
                        } elseif ($bucket >= 93) {
                            app(RejectCustomerSubmission::class)->execute($admin, $record, 'Demo: chưa xác minh được thành viên.', self::TAG);
                        }
                    } finally {
                        Carbon::setTestNow($clock);
                    }
                }
            }
            $reviews = DB::table('moderation_reviews')->whereIn('customer_submission_id', $submissions)->pluck('id')->all();
            $admin->forceFill(['admin_note' => json_encode(['tag' => self::TAG, 'users' => $users,
                'submissions' => $submissions, 'reviews' => $reviews, 'transfers' => $transfers,
                'fingerprint' => $this->fingerprint($users, $submissions, $reviews),
                'created_at' => $now->toIso8601String()], JSON_THROW_ON_ERROR), 'updated_at' => $now])->save();
        });
    }

    private function user(string $key, string $name, ?int $dealer, string $hash, CarbonImmutable $at): User
    {
        $role = explode('.', $key)[0];
        $user = new User;
        $user->forceFill(['name' => '[DEMO] '.$name, 'email' => 'toyota.realistic.v1.'.$key.'@example.com',
            'password' => $hash, 'role' => $role, 'status' => 'active', 'dealer_id' => $dealer,
            'phone' => '000'.sprintf('%07d', 800000 + (int) substr($key, strrpos($key, '.') + 1)),
            'email_verified_at' => $at, 'created_at' => $at, 'updated_at' => $at])->save();

        return $user;
    }

    private function name(int $i): string
    {
        $family = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Huỳnh', 'Phan', 'Vũ', 'Võ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ', 'Ngô', 'Dương', 'Lý', 'Đinh'];
        $given = ['Minh Anh', 'Quốc Bảo', 'Thu Hà', 'Thanh Hằng', 'Trung Hiếu', 'Ngọc Hân', 'Gia Huy', 'Khánh Linh', 'Tuấn Kiệt', 'Hoài Nam', 'Bảo Ngọc', 'Hồng Nhung', 'Đức Phúc', 'Minh Quân', 'Phương Thảo', 'Anh Tuấn', 'Hải Yến', 'Quỳnh Trang', 'Văn Sơn', 'Mỹ Duyên', 'Đức Long', 'Thanh Tâm', 'Ngọc Mai'];

        return $family[$i % count($family)].' '.$given[intdiv($i, count($family)) % count($given)];
    }

    private function address(Dealer $dealer, int $i): array
    {
        $divisions = app(AdministrativeDivisions::class);
        $province = $this->province($dealer);
        if (! $province) {
            throw new LogicException('Cannot map dealer province: '.$dealer->province.'. No data committed.');
        }
        $wards = $divisions->wards($province['code']);

        return [$province, $wards[$i % count($wards)]];
    }

    private function province(Dealer $dealer): ?array
    {
        // Only use an exact regional match; do not guess mappings for merged provinces.
        return collect(app(AdministrativeDivisions::class)->provinces())->first(fn ($p) => Str::contains(Str::lower($p['name']), Str::lower($dealer->province)));
    }

    private function manifest(User $admin): array
    {
        $manifest = json_decode($admin->admin_note ?? '', true);
        if ($admin->role->value !== 'admin' || ($manifest['tag'] ?? null) !== self::TAG
            || count($manifest['submissions'] ?? []) !== 1500 || ! in_array($admin->id, $manifest['users'] ?? [], true)) {
            throw new LogicException('Demo manifest missing or conflicting. Manual inspection required.');
        }

        return $manifest;
    }

    private function fingerprint(array $users, array $submissions, array $reviews): string
    {
        $accounts = DB::table('users')->whereIn('id', $users)->orderBy('id')->get()->map(function ($user) {
            // Manifest is stored on the demo admin itself; do not hash its envelope.
            unset($user->admin_note, $user->updated_at, $user->remember_token);

            return $user;
        });

        return hash('sha256', json_encode([$accounts,
            DB::table('customer_submissions')->whereIn('id', $submissions)->orderBy('id')->get(),
            DB::table('moderation_reviews')->whereIn('id', $reviews)->orderBy('id')->get(),
        ], JSON_THROW_ON_ERROR));
    }

    public function cleanup(): void
    {
        $this->assertEnabled();
        if (! $this->confirmed) {
            throw new LogicException('Database confirmation required.');
        }
        DB::transaction(function () {
            Dealer::orderBy('id')->lockForUpdate()->get();
            $admin = User::where('email', self::EMAIL)->lockForUpdate()->first();
            if (! $admin) {
                throw new LogicException('No manifest; nothing may be deleted.');
            }
            $m = $this->manifest($admin);
            if (! hash_equals($m['fingerprint'] ?? '', $this->fingerprint($m['users'], $m['submissions'], $m['reviews']))) {
                throw new LogicException('Owned records were modified or removed. Refusing automatic cleanup.');
            }
            // Refuse to remove records modified/reviewed after installation or used by real records.
            if (CustomerSubmission::whereIn('id', $m['submissions'])->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', '!=', self::TAG))->exists()
                || User::whereIn('id', $m['users'])->where('email', 'not like', 'toyota.realistic.v1.%@example.com')->exists()
                || DB::table('moderation_reviews')->whereIn('customer_submission_id', $m['submissions'])->whereNotIn('id', $m['reviews'])->exists()
                || DB::table('submission_edits')->whereIn('submission_id_snapshot', $m['submissions'])->exists()
                || DB::table('personal_access_tokens')->where('tokenable_type', (new User)->getMorphClass())->whereIn('tokenable_id', $m['users'])->exists()) {
                throw new LogicException('Demo ownership/history changed. Refusing automatic cleanup.');
            }
            // Restrictive foreign keys abort the whole transaction for any outside references.
            DB::table('moderation_reviews')->whereIn('id', $m['reviews'])->delete();
            CustomerSubmission::whereIn('id', $m['submissions'])->delete();
            User::whereIn('id', $m['users'])->delete();
        });
    }
}

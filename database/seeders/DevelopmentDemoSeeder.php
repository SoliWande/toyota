<?php

namespace Database\Seeders;

use App\Enums\AwardPeriod;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Award;
use App\Models\AwardWinner;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\ModerationReview;
use App\Models\User;
use App\Services\AwardResults;
use App\Services\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

class DevelopmentDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demo data is restricted to local/testing.');
        }
        $existing = User::whereIn('email', array_map(fn ($i) => sprintf('demo.sales.%02d@example.test', $i), range(1, 50)))->get();
        if ($existing->isNotEmpty()) {
            if ($existing->count() !== 50 || $existing->contains(fn ($user) => $user->role !== UserRole::Sales || ! str_starts_with($user->name, '[DEMO] '))) {
                throw new LogicException('Partial or conflicting demo accounts exist; refusing to overwrite them.');
            }
            $this->command?->info('Demo dataset already exists; keeping all records and moderation changes.');

            return;
        }
        $password = config('development.demo_password');
        if (! is_string($password) || strlen($password) < 12) {
            throw new LogicException('Set DEV_DEMO_PASSWORD (at least 12 characters) before seeding.');
        }
        $files = [];
        try {
            DB::transaction(function () use ($password, &$files) {
                $now = CarbonImmutable::now(config('app.timezone'))->startOfMinute();
                $admin = User::where('role', UserRole::Admin->value)->where('status', UserStatus::Active->value)->first();
                if (! $admin) {
                    if (User::where('email', 'demo.admin@example.test')->exists()) {
                        throw new LogicException('The demo admin email is occupied; refusing to change its role/status.');
                    }
                    $admin = User::factory()->admin()->create([
                        'name' => '[DEMO] Quản trị viên', 'email' => 'demo.admin@example.test', 'password' => Hash::make($password),
                    ]);
                }
                $dealers = Dealer::where('is_active', true)->whereNotNull('toyota_source_id')->orderBy('id')->limit(12)->get();
                if ($dealers->count() < 12) {
                    $dealers = Dealer::where('is_active', true)->orderBy('id')->limit(12)->get();
                }
                while ($dealers->count() < 12) {
                    $dealers->push(Dealer::factory()->create(['name' => '[DEMO] Đại lý '.($dealers->count() + 1)]));
                }
                $sales = collect();
                $hash = Hash::make($password);
                for ($i = 0; $i < 50; $i++) {
                    $status = match (true) {
                        $i < 36 => UserStatus::Active,
                        $i < 44 => UserStatus::Pending,
                        $i < 47 => UserStatus::Rejected,
                        default => UserStatus::Blocked,
                    };
                    $reviewedAt = $status === UserStatus::Blocked ? $now->subDays(2) : $now->subMonths(6)->addDay();
                    $user = User::factory()->create([
                        'name' => '[DEMO] '.fake('vi_VN')->name(), 'email' => sprintf('demo.sales.%02d@example.test', $i + 1),
                        'phone' => sprintf('000000%04d', $i + 1), 'password' => $hash,
                        'dealer_id' => $dealers[$i % 12]->id, 'status' => $status,
                        'created_at' => $now->subMonths(6), 'updated_at' => $now->subMonths(6),
                        'reviewed_by' => $status === UserStatus::Pending ? null : $admin->id,
                        'reviewed_at' => $status === UserStatus::Pending ? null : $reviewedAt,
                        'rejection_reason' => $status === UserStatus::Rejected ? '[DEMO] Thông tin đăng ký chưa đầy đủ.' : null,
                        'admin_note' => $status === UserStatus::Pending ? null : '[DEMO] Lịch sử xét duyệt giả lập.',
                    ]);
                    $sales->push($user);
                    if ($status !== UserStatus::Pending) {
                        $this->accountHistory($user, $admin, 'pending', $status === UserStatus::Blocked ? 'active' : $status->value, $now->subMonths(6)->addDay());
                        if ($status === UserStatus::Blocked) {
                            $this->accountHistory($user, $admin, 'active', 'blocked', $reviewedAt);
                        }
                    }
                }
                $eligible = $sales->filter(fn ($user) => in_array($user->status, [UserStatus::Active, UserStatus::Blocked], true))->values();
                $image = $this->evidenceImage();
                for ($i = 0; $i < 500; $i++) {
                    $owner = $eligible[$i < 40 ? 0 : ($i < 72 ? 1 : ($i < 98 ? 2 : 3 + (($i - 98) % 36)))];
                    $status = $i % 10 < 6 ? SubmissionStatus::Approved : ($i % 10 < 9 ? SubmissionStatus::Pending : SubmissionStatus::Rejected);
                    $submittedAt = $this->submissionTime($now, $i);
                    if ($owner->status === UserStatus::Blocked) {
                        $submittedAt = $now->startOfMonth()->subMonth()->addDays($i % 20)->addHours(10);
                    }
                    // Include a month-boundary example with review in the following month.
                    if ($i === 10) {
                        $submittedAt = $now->startOfMonth()->subMinutes(30);
                    }
                    $reviewedAt = $submittedAt->addHours(4 + ($i % 72))->min($now);
                    $path = 'demo/'.Str::uuid().'.png';
                    $files[] = $path;
                    Storage::disk('submission_evidence')->put($path, $image);
                    $profile = in_array($i, [6, 7], true) ? ($i === 6 ? 0 : 10) : $i;
                    $submission = CustomerSubmission::factory()->create([
                        'sales_id' => $owner->id, 'customer_name' => '[DEMO] '.fake('vi_VN')->name(),
                        'facebook_url' => sprintf('https://www.facebook.com/demo.toyota.customer.%04d', $profile + 1),
                        'phone' => sprintf('000%07d', $i + 1), 'notes' => '[DEMO] Dữ liệu giả để kiểm tra giao diện, không phải khách hàng thật.',
                        'vehicle_model' => ['veloz', 'hilux', 'camry', 'vios', 'corolla_cross'][$i % 5],
                        'first_registration_year' => $now->year - 1 - ($i % 7), 'vehicle_color' => ['Trắng', 'Đen', 'Bạc', 'Đỏ', 'Xanh'][$i % 5],
                        'license_plate' => sprintf('DEMO-%04d', $i + 1), 'evidence_image_path' => $path,
                        'submitted_at' => $submittedAt, 'created_at' => $submittedAt, 'updated_at' => $status === SubmissionStatus::Pending ? $submittedAt : $reviewedAt,
                        'status' => $status, 'reviewed_by' => $status === SubmissionStatus::Pending ? null : $admin->id,
                        'reviewed_at' => $status === SubmissionStatus::Pending ? null : $reviewedAt,
                        'rejection_reason' => $status === SubmissionStatus::Rejected ? '[DEMO] Chưa xác nhận thành viên trong nhóm.' : null,
                        'admin_note' => $status === SubmissionStatus::Pending ? null : '[DEMO] Kết quả xác minh giả lập.',
                    ]);
                    if ($status !== SubmissionStatus::Pending) {
                        ModerationReview::factory()->create([
                            'customer_submission_id' => $submission->id, 'reviewed_by' => $admin->id,
                            'to_status' => $status->value, 'reviewed_at' => $reviewedAt,
                            'rejection_reason' => $submission->rejection_reason, 'admin_note' => $submission->admin_note,
                            'created_at' => $reviewedAt, 'updated_at' => $reviewedAt,
                        ]);
                    }
                }
                $this->awards($admin, $now);
            });
        } catch (Throwable $error) {
            Storage::disk('submission_evidence')->delete($files);
            throw $error;
        }
        $this->command?->info('Created 50 demo Sales, 500 submissions and published demo award snapshots. Login password is DEV_DEMO_PASSWORD; existing admin credentials are unchanged.');
    }

    private function accountHistory(User $sales, User $admin, string $from, string $to, CarbonImmutable $time): void
    {
        ModerationReview::factory()->sales()->create([
            'sales_id' => $sales->id, 'reviewed_by' => $admin->id, 'from_status' => $from, 'to_status' => $to,
            'reviewed_at' => $time, 'rejection_reason' => $to === 'rejected' ? $sales->rejection_reason : null,
            'admin_note' => '[DEMO] Lịch sử xét duyệt giả lập.', 'created_at' => $time, 'updated_at' => $time,
        ]);
    }

    private function submissionTime(CarbonImmutable $now, int $i): CarbonImmutable
    {
        $slot = $i % 7;
        if ($slot < 3) {
            $start = $slot < 2 ? $now->startOfWeek(CarbonImmutable::MONDAY) : $now->startOfMonth();
            $minutes = max(1, (int) $start->diffInMinutes($now));

            return $start->addMinutes(($i * 113) % $minutes);
        }
        $start = $now->startOfMonth()->subMonths($slot - 2);

        return $start->addDays(($i * 7) % $start->daysInMonth)->addHours(9 + ($i % 8))->addMinutes($i % 60);
    }

    private function awards(User $admin, CarbonImmutable $now): void
    {
        $leaderboard = app(LeaderboardService::class);
        foreach ([AwardPeriod::Monthly, AwardPeriod::Weekly] as $type) {
            $created = 0;
            for ($offset = 1; $offset <= 12 && $created < 2; $offset++) {
                $at = $type === AwardPeriod::Monthly ? $now->startOfMonth()->subMonths($offset) : $now->startOfWeek(CarbonImmutable::MONDAY)->subWeeks($offset);
                [$start, $end] = $leaderboard->bounds($type->leaderboardPeriod(), $at);
                if (Award::where('period_type', $type->value)->where('period_start', $start)->where('period_end', $end)->exists()
                    || $leaderboard->salesForRange($start, $end)->limit(3)->get()->count() < 3
                    || $leaderboard->dealersForRange($start, $end)->limit(3)->get()->count() < 3) {
                    continue;
                }
                $award = Award::factory()->create([
                    'title' => '[DEMO] Vinh danh '.($type === AwardPeriod::Monthly ? 'tháng '.$start->format('m/Y') : 'tuần '.$start->format('d/m/Y')),
                    'period_type' => $type, 'period_start' => $start, 'period_end' => $end,
                ]);
                $results = app(AwardResults::class)->preview($award);
                foreach ($results['sales']->concat($results['dealers']) as $winner) {
                    AwardWinner::factory()->create($winner + ['award_id' => $award->id]);
                }
                $award->forceFill(['published_by' => $admin->id, 'published_at' => $now])->save();
                $created++;
            }
        }
    }

    private function evidenceImage(): string
    {
        $image = imagecreatetruecolor(640, 360);
        imagefill($image, 0, 0, imagecolorallocate($image, 241, 245, 249));
        $red = imagecolorallocate($image, 185, 28, 28);
        imagestring($image, 5, 40, 135, 'DEMO TOYOTA - SYNTHETIC EVIDENCE', $red);
        imagestring($image, 4, 40, 175, 'Not a real customer or vehicle photograph.', $red);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}

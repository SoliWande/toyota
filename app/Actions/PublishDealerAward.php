<?php

namespace App\Actions;

use App\Enums\SubmissionStatus;
use App\Models\Award;
use App\Models\AwardWinner;
use App\Models\User;
use App\Services\AwardResults;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PublishDealerAward
{
    public function __construct(private AwardResults $results) {}

    public function execute(User $admin, Award $award, string $previewHash): void
    {
        Gate::forUser($admin)->authorize('publish', $award);

        DB::transaction(function () use ($admin, $award, $previewHash) {
            $admin = User::whereKey($admin->id)->lockForUpdate()->firstOrFail();
            $locked = Award::whereKey($award->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($admin)->authorize('publish', $locked);
            if ($locked->published_at !== null || $locked->competition_scope !== 'dealer') {
                throw ValidationException::withMessages(['award' => 'Kỳ này đã công bố hoặc thuộc lịch sử Sales; không thể công bố lại.']);
            }
            if (CarbonImmutable::now(config('app.timezone'))->lt($locked->period_end)) {
                throw ValidationException::withMessages(['award' => 'Chỉ được công bố sau khi kỳ kết thúc.']);
            }

            // Lock the complete half-open period, including gaps, before checking pending
            // or computing scores. Concurrent moderation and inserts cannot change it.
            $submissions = DB::table('customer_submissions')
                ->where('submitted_at', '>=', $locked->period_start->format('Y-m-d H:i:s'))
                ->where('submitted_at', '<', $locked->period_end->format('Y-m-d H:i:s'))
                ->orderBy('id')->lockForUpdate()->get(['id', 'status']);
            if ($submissions->contains('status', SubmissionStatus::Pending->value)) {
                throw ValidationException::withMessages(['award' => 'Còn khai báo chờ duyệt trong kỳ; hãy xử lý trước khi công bố.']);
            }
            // Lock names/codes too so the preview and stored snapshots agree.
            DB::table('dealers')->orderBy('id')->sharedLock()->get(['id']);
            $preview = $this->results->preview($locked);
            if (! hash_equals($this->results->fingerprint($locked, $preview), $previewHash)) {
                throw ValidationException::withMessages(['preview_hash' => 'Kết quả đã thay đổi. Vui lòng xem Preview mới trước khi công bố.']);
            }
            if ($locked->winners()->exists()) {
                throw ValidationException::withMessages(['award' => 'Bản nháp đã có snapshot. Cần kiểm tra trước khi công bố.']);
            }
            foreach ($preview['dealers'] as $snapshot) {
                $winner = new AwardWinner;
                $winner->forceFill(['award_id' => $locked->id] + $snapshot)->save();
            }
            $locked->forceFill(['published_at' => now(), 'published_by' => $admin->id])->save();
        }, 3);
    }
}

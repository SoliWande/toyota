<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Separate evidence axes. All writes use mutex -> submission -> state -> receipt. */
final class SubmissionVerification
{
    public const GROUP = ['unknown' => 'Chưa có dữ liệu xác minh', 'not_requested' => 'Chưa ghi nhận yêu cầu vào Group',
        'requested' => 'Đã xác nhận gửi yêu cầu vào Group', 'member' => 'Đã xác nhận thành viên Group'];

    public const REASONS = ['facebook_unavailable' => 'Link Facebook không mở được.',
        'missing_information' => 'Cần bổ sung thông tin để xác minh.'];

    public const BLOCKERS = ['duplicate_plate' => 'Biển số này cần được đối chiếu lại với đăng ký khác.',
        'identity_conflict' => 'Thông tin đăng ký cần được đối chiếu lại.',
        'verification_unavailable' => 'Hiện chưa thể xác minh thông tin đăng ký.'];

    private const FIELDS = ['group_progress', 'blocker_code', 'supplement_state', 'supplement_reason'];

    public function state(int $id, bool $lock = false): ?object
    {
        $query = DB::table('submission_verification_states')->where('submission_id', $id);

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    public function version(CustomerSubmission $submission, ?object $state = null): string
    {
        return RegistrationPhone::key('verification|'.$submission->id.'|'.($state?->version ?? 0).'|'
            .$submission->status->value.'|'.$submission->facebook_url.'|'.$submission->updated_at?->format('Y-m-d H:i:s.u'));
    }

    public function evidencedProgress(CustomerSubmission $submission, ?object $state): ?string
    {
        if (! $state?->group_progress) {
            return null;
        }
        $audit = DB::table('submission_verification_audits')->where('submission_id', $submission->id)
            ->where('action', 'group')->orderByDesc('id')->value('new_values');

        return $this->evidencedProgressFromAudit($submission, $state, $audit);
    }

    /** The report preloads audits in batches; keep the same evidence check as public lookup. */
    public function evidencedProgressFromAudit(CustomerSubmission $submission, ?object $state, ?string $audit): ?string
    {
        $key = $audit ? (json_decode($audit, true, flags: JSON_THROW_ON_ERROR)['profile_key'] ?? '') : '';

        return $state && is_string($key) && hash_equals($this->profileKey($submission), $key) ? ($state->group_progress ?? null) : null;
    }

    private function profileKey(CustomerSubmission $submission): string
    {
        return RegistrationPhone::key('group-profile|'.$submission->id.'|'.$submission->facebook_url_normalized);
    }

    public function adminUpdate(User $admin, CustomerSubmission $submission, array $data): void
    {
        Gate::forUser($admin)->authorize('review', $submission);
        $data = validator($data, [
            'verification_version' => ['required', 'string', 'size:64'],
            'action' => ['required', Rule::in(['group', 'blocker', 'supplement', 'close_supplement'])],
            'group_progress' => ['required_if:action,group', Rule::in(array_keys(self::GROUP))],
            'blocker_code' => ['nullable', Rule::in(array_keys(self::BLOCKERS))],
            'supplement_reason' => ['required_if:action,supplement', Rule::in(array_keys(self::REASONS))],
        ])->validate();
        app(SubmissionIntake::class)->locked(function () use ($admin, $submission, $data) {
            $current = CustomerSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($admin)->authorize('review', $current);
            $state = $this->state($current->id, true);
            if (! hash_equals($this->version($current, $state), $data['verification_version'])) {
                throw ValidationException::withMessages(['verification_version' => 'Thông tin đã thay đổi. Vui lòng tải lại trang trước khi lưu.']);
            }
            $values = [];
            switch ($data['action']) {
                case 'group':
                    // Record independently; never change moderation. Rejected/member needs review, not a public membership claim.
                    $values = ['group_progress' => $data['group_progress'] === 'unknown' ? null : $data['group_progress'],
                        'group_verified_by' => $admin->id, 'group_verified_at' => now()];
                    break;
                case 'blocker':
                    $values = ['blocker_code' => $data['blocker_code'] ?? null,
                        'blocker_updated_by' => $admin->id, 'blocker_updated_at' => now()];
                    break;
                case 'supplement':
                    if ($current->status !== SubmissionStatus::Pending) {
                        throw ValidationException::withMessages(['supplement_reason' => 'Chỉ yêu cầu khách cập nhật trực tuyến đối với đăng ký đang chờ duyệt.']);
                    }
                    $values = ['supplement_key' => (string) Str::uuid(), 'supplement_state' => 'open',
                        'supplement_reason' => $data['supplement_reason'], 'supplement_requested_by' => $admin->id,
                        'supplement_requested_at' => now(), 'supplement_responded_at' => null];
                    break;
                case 'close_supplement':
                    if (! $state || ! in_array($state->supplement_state, ['open', 'submitted'], true)) {
                        throw ValidationException::withMessages(['verification_version' => 'Không có yêu cầu bổ sung cần đóng. Vui lòng tải lại trang.']);
                    }
                    $values = ['supplement_state' => 'closed'];
                    break;
            }
            $this->persist($current->id, $state, $values, 'admin', $admin->id, $data['action'],
                $data['action'] === 'group' ? ['profile_key' => $this->profileKey($current)] : []);
        });
    }

    public function owns(Request $request, Dealer $dealer, int $id, bool $lock = false): bool
    {
        $query = DB::table('dealer_registration_receipts')->where('submission_id', $id)
            ->where('owner_key', app(DealerRegistrationReceipt::class)->owner($request, $dealer))
            ->where('expires_at', '>', now());

        return ($lock ? $query->lockForUpdate() : $query)->first() !== null;
    }

    public function respond(Request $request, Dealer $dealer, string $key, string $version, string $url): void
    {
        app(SubmissionIntake::class)->locked(function () use ($request, $dealer, $key, $version, $url) {
            // Non-locking locator only. Re-read every authorization condition after acquiring business locks.
            $id = DB::table('submission_verification_states')->where('supplement_key', $key)->value('submission_id');
            abort_unless($id, 403);
            $submission = CustomerSubmission::whereKey($id)->lockForUpdate()->firstOrFail();
            $state = $this->state($id, true);
            $active = Dealer::whereKey($dealer->id)->value('is_active');
            abort_unless($active && $submission->dealer_id === $dealer->id
                && $this->owns($request, $dealer, $id, true), 403);
            if ($state?->supplement_key !== $key || $state->supplement_state !== 'open'
                || $submission->status !== SubmissionStatus::Pending || $state->supplement_reason !== 'facebook_unavailable'
                || ! hash_equals($this->version($submission, $state), $version)) {
                throw ValidationException::withMessages(['facebook_profile_url' => 'Yêu cầu đã thay đổi hoặc đã được tiếp nhận. Vui lòng tra cứu lại.']);
            }
            $submission->facebook_url = app(FacebookUrlNormalizer::class)->normalize($url);
            $submission->save(); // Guarded model path; status, identity and attribution are untouched.
            $this->persist($id, $state, ['supplement_state' => 'submitted', 'supplement_responded_at' => now()],
                'customer', null, 'response');
        });
    }

    private function persist(int $id, ?object $state, array $values, string $actorType, ?int $actor, string $action, array $auditEvidence = []): void
    {
        $old = array_intersect_key((array) $state, array_flip(self::FIELDS));
        $new = array_intersect_key(array_replace($old, $values), array_flip(self::FIELDS));
        // Never audit raw URLs, names, identity values, session keys or customer credentials.
        DB::table('submission_verification_audits')->insert(['submission_id' => $id, 'actor_id' => $actor,
            'actor_type' => $actorType, 'action' => $action, 'old_values' => json_encode($old, JSON_THROW_ON_ERROR),
            'new_values' => json_encode($new + $auditEvidence, JSON_THROW_ON_ERROR), 'created_at' => now()]);
        $values += ['version' => ($state?->version ?? 0) + 1, 'updated_at' => now()];
        if ($state) {
            DB::table('submission_verification_states')->where('submission_id', $id)->update($values);
        } else {
            DB::table('submission_verification_states')->insert($values + ['submission_id' => $id, 'created_at' => now()]);
        }
    }
}

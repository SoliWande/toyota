<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApproveCustomerSubmission;
use App\Actions\RejectCustomerSubmission;
use App\Actions\UpdateCustomerSubmission;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewSubmissionRequest;
use App\Http\Requests\Admin\SubmissionIndexRequest;
use App\Http\Requests\Admin\SubmissionQueueRequest;
use App\Http\Requests\Admin\UpdateSubmissionRequest;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\AdministrativeDivisions;
use App\Services\AdminSubmissionQueue;
use App\Services\ApprovedSubmissionFinder;
use App\Services\LeaderboardService;
use App\Services\RegistrationPhone;
use App\Services\SubmissionCollisionAudit;
use App\Services\SubmissionIntake;
use App\Services\SubmissionVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubmissionReviewController extends Controller
{
    public function index(SubmissionQueueRequest $request, AdminSubmissionQueue $queue): View|RedirectResponse
    {
        $this->authorize('review', new CustomerSubmission);
        $filters = $request->validated();
        $status = $request->has('status') ? ($filters['status'] ?? '') : SubmissionStatus::Pending->value;
        $filters['status'] = $status;
        $workflow = $filters['workflow'] ?? ($status !== '' ? $status : null);
        $query = CustomerSubmission::query();
        if (! empty($filters['dealer_id'])) {
            $query->where('customer_submissions.dealer_id', $filters['dealer_id']);
        }
        if (! empty($filters['sales_id'])) {
            $query->where('sales_id', $filters['sales_id']);
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(function ($query) use ($search) {
                foreach (['customer_name', 'phone', 'phone_normalized', 'license_plate', 'facebook_url'] as $field) {
                    $query->orWhereRaw($field." LIKE ? ESCAPE '!'", [$search]);
                }
            });
        }
        if (! empty($filters['submitted_from'])) {
            $query->where('submitted_at', '>=', Carbon::parse($filters['submitted_from'])->startOfDay());
        }
        if (! empty($filters['submitted_to'])) {
            $query->where('submitted_at', '<', Carbon::parse($filters['submitted_to'])->addDay()->startOfDay());
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }
        $groups = $queue->groups($query);
        $tabCounts = array_map('count', $groups);
        if ($workflow !== null) {
            $query->whereIn('customer_submissions.id', $groups[$workflow]);
        }
        // Explicit status= remains the historical all-status deep filter used by Dashboard KPI links.
        if ($status !== '' && ! isset($filters['workflow'])) {
            $query->where('status', $status);
        }
        $query->with(['sales:id,name,dealer_id,role', 'dealer:id,name,code'])->select('customer_submissions.*');

        $submissions = $query->orderBy('submitted_at')->orderBy('id')->paginate(20)->withQueryString()->appends(['status' => $status, 'selected' => null]);
        if ($submissions->currentPage() > $submissions->lastPage()) {
            return redirect()->route('admin.submissions.index', array_replace($filters, ['page' => $submissions->lastPage()]));
        }
        $selected = $submissions->firstWhere('id', $filters['selected'] ?? null) ?? $submissions->first();
        $selectionIndex = $selected ? $submissions->getCollection()->search(fn ($row) => $row->id === $selected->id) : null;
        $selectionPosition = $selected ? $submissions->firstItem() + $selectionIndex : null;
        $previousId = $selected && $selectionPosition > 1 ? (clone $query)->offset($selectionPosition - 2)->limit(1)->value('customer_submissions.id') : null;
        $nextId = $selected && $selectionPosition < $submissions->total() ? (clone $query)->offset($selectionPosition)->limit(1)->value('customer_submissions.id') : null;
        $previousPage = $selectionIndex === 0 ? $submissions->currentPage() - 1 : $submissions->currentPage();
        $nextPage = $selectionIndex === $submissions->count() - 1 ? $submissions->currentPage() + 1 : $submissions->currentPage();
        $selectionLinks = [
            'previous' => $previousId ? route('admin.submissions.index', array_replace($filters, ['selected' => $previousId, 'page' => $previousPage])) : null,
            'next' => $nextId ? route('admin.submissions.index', array_replace($filters, ['selected' => $nextId, 'page' => $nextPage])) : null,
        ];
        $state = $selected ? app(SubmissionVerification::class)->state($selected->id) : null;
        $groupProgress = $selected ? app(SubmissionVerification::class)->evidencedProgress($selected, $state) : null;
        $conflicts = $selected ? (app(SubmissionCollisionAudit::class)->warningsFor([$selected->id])[$selected->id] ?? []) : [];
        $noDuplicates = $selected && $selected->status !== SubmissionStatus::Rejected
            && RegistrationPhone::normalize($selected->phone ?? '') !== ''
            && SubmissionIntake::plate($selected->license_plate) !== '' && filled($selected->facebook_url_normalized)
            && $conflicts === [] && ! CustomerSubmission::whereIn('status', ['pending', 'approved'])->where('id', '!=', $selected->id)
                ->where('facebook_url_normalized', $selected->facebook_url_normalized)->exists();

        return view('admin.submissions.index', [
            // Preserve the explicit all-status filter after empty-string middleware normalization.
            'submissions' => $submissions,
            'selected' => $selected,
            'selectedState' => $state,
            'groupProgress' => $groupProgress,
            'tabCounts' => $tabCounts,
            'workflow' => $workflow,
            'selectionPosition' => $selectionPosition,
            'selectionLinks' => $selectionLinks,
            'noDuplicates' => $noDuplicates,
            'editingAvailable' => Schema::hasTable('submission_edits'),
            'status' => $status,
            'filters' => $filters,
            'dealers' => Dealer::orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function update(UpdateSubmissionRequest $request, CustomerSubmission $submission, UpdateCustomerSubmission $update): RedirectResponse|JsonResponse
    {
        $context = $this->queueContext($request);
        $filters = $context ?? validator($request->query(), (new SubmissionIndexRequest)->rules())->validate();
        $update->execute($request->user(), $submission, $request->validated());
        $message = null;
        if ($context !== null) {
            $submission->refresh();
            $message = hash_equals($request->validated('edit_version'), UpdateCustomerSubmission::version($submission)) ? null : 'Đã lưu thông tin khách · ghi vào lịch sử';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message]);
            }
        }
        $filters['status'] ??= '';

        return redirect()->route('admin.submissions.index', $filters)->with('success', 'Đã lưu thay đổi. Trạng thái duyệt và lịch sử thành tích được giữ nguyên.')->with('cq_toast', $message);
    }

    public function wards(Request $request, string $province, AdministrativeDivisions $directory): JsonResponse
    {
        $this->authorize('review', new CustomerSubmission);
        abort_unless($directory->province($province), 404);

        return response()->json(['wards' => $directory->wards($province)]);
    }

    public function show(Request $request, CustomerSubmission $submission, ApprovedSubmissionFinder $duplicates): View
    {
        $this->authorize('review', $submission);
        $submission->load('sales', 'dealer', 'reviewer');

        return view('admin.submissions.show', [
            'submission' => $submission,
            'queueContext' => $this->queueContext($request),
            'verificationState' => app(SubmissionVerification::class)->state($submission->id),
            'verificationAudits' => DB::table('submission_verification_audits as audit')->leftJoin('users', 'users.id', '=', 'audit.actor_id')
                ->where('audit.submission_id', $submission->id)->orderByDesc('audit.id')->limit(20)
                ->get(['audit.*', 'users.name as actor_name']),
            'duplicate' => $submission->status === SubmissionStatus::Pending ? $duplicates->find($submission->facebook_url) : null,
            'identityConflicts' => $submission->status === SubmissionStatus::Pending
                ? (app(SubmissionCollisionAudit::class)->warningsFor([$submission->id])[$submission->id] ?? []) : [],
            'dealers' => Dealer::orderBy('name')->get(['id', 'name']),
            'salesOptions' => User::where('role', UserRole::Sales->value)->orderBy('name')->get(['id', 'name', 'dealer_id']),
            'editingAvailable' => Schema::hasTable('submission_edits'),
        ]);
    }

    public function approve(ReviewSubmissionRequest $request, CustomerSubmission $submission, ApproveCustomerSubmission $approve): RedirectResponse|JsonResponse
    {
        $this->authorize('review', $submission);
        $data = $request->validated();
        $context = $this->validateQueueReview($request, $submission);
        $approve->execute($request->user(), $submission, $data['admin_note'] ?? null, $data['verified_facebook_url'] ?? null);
        $message = null;
        if ($context !== null) {
            $submission->refresh();
            $contribution = app(LeaderboardService::class)->dealerScores()->where('submissions.id', $submission->id)->first()?->score;
            $message = (int) $contribution === 1 ? 'Đã xác minh '.$submission->customer_name.' · đại lý +1 điểm' : null;
            if ($request->expectsJson()) {
                return response()->json(['message' => $message]);
            }
        }

        return $this->reviewRedirect($request, $submission)->with('success', 'Đã duyệt khai báo và ghi nhận thành tích.')->with('cq_toast', $message);
    }

    public function reject(ReviewSubmissionRequest $request, CustomerSubmission $submission, RejectCustomerSubmission $reject): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $context = $this->validateQueueReview($request, $submission);
        $reject->execute($request->user(), $submission, $data['rejection_reason'] ?? null, $data['admin_note'] ?? null);
        $message = $context !== null && $submission->source === 'sales' && filled($data['rejection_reason'] ?? null) ? 'Đã từ chối khai báo · sales sẽ thấy lý do' : null;
        if ($context !== null && $request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return $this->reviewRedirect($request, $submission)->with('success', 'Đã từ chối khai báo.')->with('cq_toast', $message);
    }

    private function reviewRedirect(ReviewSubmissionRequest $request, CustomerSubmission $submission): RedirectResponse
    {
        if ($request->boolean('return_to_queue')) {
            $context = $this->queueContext($request);
            $filters = $context ?? $request->safe()->only(array_keys((new SubmissionIndexRequest)->rules()));
            if ($request->has('status')) {
                $filters['status'] ??= '';
            }

            return redirect()->route('admin.submissions.index', $filters);
        }

        return redirect()->route('admin.submissions.show', $submission);
    }

    private function queueContext(Request $request): ?array
    {
        if (! $request->boolean('c02')) {
            return null;
        }

        $filters = validator($request->query(), (new SubmissionQueueRequest)->rules())->validate();
        $filters['status'] ??= '';

        return $filters;
    }

    private function validateQueueReview(Request $request, CustomerSubmission $submission): ?array
    {
        $context = $this->queueContext($request);
        if ($context !== null) {
            validator($request->all(), ['review_submission_id' => ['required', 'string', Rule::in([(string) $submission->id])]])->validate();
        }

        return $context;
    }
}

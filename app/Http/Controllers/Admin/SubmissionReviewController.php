<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApproveCustomerSubmission;
use App\Actions\RejectCustomerSubmission;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewSubmissionRequest;
use App\Http\Requests\Admin\SubmissionIndexRequest;
use App\Models\CustomerSubmission;
use App\Models\Dealer;
use App\Models\User;
use App\Services\ApprovedSubmissionFinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SubmissionReviewController extends Controller
{
    public function index(SubmissionIndexRequest $request): View
    {
        $this->authorize('review', new CustomerSubmission);
        $filters = $request->validated();
        $status = $request->has('status') ? ($filters['status'] ?? '') : SubmissionStatus::Pending->value;
        $filters['status'] = $status;
        $query = CustomerSubmission::with(['sales:id,name,dealer_id', 'dealer:id,name'])->select('customer_submissions.*')
            ->addSelect(['duplicate_approved_id' => DB::table('customer_submissions as approved')
                ->select('approved.id')->whereColumn('approved.approved_facebook_identity', 'customer_submissions.facebook_url_normalized')->limit(1)]);
        if ($status !== '') {
            $query->where('status', $status);
        }
        if (! empty($filters['dealer_id'])) {
            $query->where('customer_submissions.dealer_id', $filters['dealer_id']);
        }
        if (! empty($filters['sales_id'])) {
            $query->where('sales_id', $filters['sales_id']);
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(function ($query) use ($search) {
                foreach (['customer_name', 'phone', 'facebook_url'] as $field) {
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

        return view('admin.submissions.index', [
            'submissions' => $query->orderBy('submitted_at')->orderBy('id')->paginate(20)->withQueryString(),
            'status' => $status,
            'filters' => $filters,
            'dealers' => Dealer::orderBy('name')->get(['id', 'name']),
            'salesOptions' => User::where('role', UserRole::Sales->value)->orderBy('name')->get(['id', 'name', 'dealer_id']),
        ]);
    }

    public function show(CustomerSubmission $submission, ApprovedSubmissionFinder $duplicates): View
    {
        $this->authorize('review', $submission);
        $submission->load('sales', 'dealer', 'reviewer');

        return view('admin.submissions.show', [
            'submission' => $submission,
            'duplicate' => $submission->status === SubmissionStatus::Pending ? $duplicates->find($submission->facebook_url) : null,
        ]);
    }

    public function approve(ReviewSubmissionRequest $request, CustomerSubmission $submission, ApproveCustomerSubmission $approve): RedirectResponse
    {
        $this->authorize('review', $submission);
        $data = $request->validated();
        $approve->execute($request->user(), $submission, $data['admin_note'] ?? null);

        return $this->reviewRedirect($request, $submission)->with('success', 'Đã duyệt khai báo và ghi nhận thành tích.');
    }

    public function reject(ReviewSubmissionRequest $request, CustomerSubmission $submission, RejectCustomerSubmission $reject): RedirectResponse
    {
        $data = $request->validated();
        $reject->execute($request->user(), $submission, $data['rejection_reason'] ?? null, $data['admin_note'] ?? null);

        return $this->reviewRedirect($request, $submission)->with('success', 'Đã từ chối khai báo.');
    }

    private function reviewRedirect(ReviewSubmissionRequest $request, CustomerSubmission $submission): RedirectResponse
    {
        if ($request->boolean('return_to_queue')) {
            $filters = $request->safe()->only(array_keys((new SubmissionIndexRequest)->rules()));
            if ($request->has('status')) {
                $filters['status'] ??= '';
            }

            return redirect()->route('admin.submissions.index', $filters);
        }

        return redirect()->route('admin.submissions.show', $submission);
    }
}

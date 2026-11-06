<?php

namespace App\Http\Controllers\Sales;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SubmissionRequest;
use App\Models\CustomerSubmission;
use App\Models\User;
use App\Services\LeaderboardService;
use App\Services\StaffCustomerLinks;
use App\Services\StaffSubmissionProgress;
use App\Services\SubmissionIntake;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class SubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', CustomerSubmission::class);

        return app(DashboardController::class)($request, app(LeaderboardService::class));
    }

    public function create(): View
    {
        $this->authorize('create', CustomerSubmission::class);

        return view('sales.submissions.form', ['submission' => new CustomerSubmission]);
    }

    public function store(SubmissionRequest $request): RedirectResponse
    {
        $newPath = null;
        try {
            $submission = app(SubmissionIntake::class)->locked(function () use ($request, &$newPath) {
                // Share the user row lock with dealer transfers so creation has one attribution.
                $sales = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                abort_unless($sales->can('create', CustomerSubmission::class), 403);
                $submission = new CustomerSubmission($request->submissionData());
                $submission->sales()->associate($sales);
                $submission->status = SubmissionStatus::Pending;
                $submission->submitted_at = now();
                $newPath ??= $request->file('evidence_image')->store('', 'submission_evidence');
                $submission->evidence_image_path = $newPath;
                $submission->save();

                return $submission;
            });
        } catch (Throwable $error) {
            $this->removeEvidence($newPath);
            if ($error instanceof ValidationException && isset($error->errors()['phone'])) {
                throw ValidationException::withMessages(['customer_phone' => $error->errors()['phone']] + array_diff_key($error->errors(), ['phone' => true]));
            }
            throw $error;
        }

        return redirect()->route('sales.submissions.saved', $submission)->with('success', 'Đã gửi khai báo, vui lòng chờ admin duyệt.');
    }

    public function show(CustomerSubmission $submission): View
    {
        $this->authorize('view', $submission);

        $progress = app(StaffSubmissionProgress::class)->data($submission);
        $lookupUrl = app(StaffCustomerLinks::class)->lookup($submission);

        return view('sales.submissions.show', compact('submission', 'progress', 'lookupUrl'));
    }

    public function saved(CustomerSubmission $submission): View
    {
        $this->authorize('view', $submission);
        $links = app(StaffCustomerLinks::class);
        $lookupUrl = $links->lookup($submission);

        return view('sales.submissions.saved', ['submission' => $submission, 'lookupUrl' => $lookupUrl, 'message' => $links->message($lookupUrl)]);
    }

    public function edit(CustomerSubmission $submission): View
    {
        $this->authorize('update', $submission);

        return view('sales.submissions.form', compact('submission'));
    }

    public function update(SubmissionRequest $request, CustomerSubmission $submission): RedirectResponse
    {
        $newPath = null;
        $oldPath = null;
        try {
            app(SubmissionIntake::class)->locked(function () use ($request, $submission, &$newPath, &$oldPath) {
                $locked = CustomerSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
                $this->authorize('update', $locked);
                if ($request->hasFile('evidence_image')) {
                    $newPath ??= $request->file('evidence_image')->store('', 'submission_evidence');
                    $oldPath = $locked->evidence_image_path;
                    $locked->evidence_image_path = $newPath;
                }
                $locked->fill($request->submissionData())->save();
            });
        } catch (Throwable $error) {
            $this->removeEvidence($newPath);
            if ($error instanceof ValidationException && isset($error->errors()['phone'])) {
                throw ValidationException::withMessages(['customer_phone' => $error->errors()['phone']] + array_diff_key($error->errors(), ['phone' => true]));
            }
            throw $error;
        }
        $this->removeEvidence($oldPath);

        return redirect()->route('sales.submissions.show', $submission)->with('success', 'Đã cập nhật khai báo.');
    }

    public function destroy(CustomerSubmission $submission): RedirectResponse
    {
        $path = DB::transaction(function () use ($submission) {
            $locked = CustomerSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $this->authorize('delete', $locked);
            $locked->delete();

            return $locked->evidence_image_path;
        });
        $this->removeEvidence($path);

        return redirect()->route('sales.submissions.index')->with('success', 'Đã xóa khai báo.');
    }

    private function removeEvidence(?string $path): void
    {
        if ($path === null) {
            return;
        }
        try {
            Storage::disk('submission_evidence')->delete($path);
        } catch (Throwable $error) {
            Log::warning('Evidence file cleanup failed.', ['exception_type' => $error::class]);
        }
    }
}

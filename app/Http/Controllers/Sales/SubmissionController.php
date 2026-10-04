<?php

namespace App\Http\Controllers\Sales;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SubmissionRequest;
use App\Models\CustomerSubmission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', CustomerSubmission::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(SubmissionStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = $request->user()->submissions()->select(['id', 'sales_id', 'customer_name', 'status', 'submitted_at']);
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(function ($query) use ($search) {
                foreach (['customer_name', 'facebook_url', 'phone'] as $field) {
                    $query->orWhereRaw($field." LIKE ? ESCAPE '!'", [$search]);
                }
            });
        }

        return view('sales.submissions.index', [
            'submissions' => $query->orderByDesc('submitted_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
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
            $submission = DB::transaction(function () use ($request, &$newPath) {
                // Share the user row lock with dealer transfers so creation has one attribution.
                $sales = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                abort_unless($sales->can('create', CustomerSubmission::class), 403);
                $submission = new CustomerSubmission($request->submissionData());
                $submission->sales()->associate($sales);
                $submission->status = SubmissionStatus::Pending;
                $submission->submitted_at = now();
                $newPath = $request->file('evidence_image')->store('', 'submission_evidence');
                $submission->evidence_image_path = $newPath;
                $submission->save();

                return $submission;
            });
        } catch (Throwable $error) {
            $this->removeEvidence($newPath);
            throw $error;
        }

        return redirect()->route('sales.submissions.show', $submission)->with('success', 'Đã gửi khai báo, vui lòng chờ admin duyệt.');
    }

    public function show(CustomerSubmission $submission): View
    {
        $this->authorize('view', $submission);

        return view('sales.submissions.show', compact('submission'));
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
            DB::transaction(function () use ($request, $submission, &$newPath, &$oldPath) {
                $locked = CustomerSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
                $this->authorize('update', $locked);
                if ($request->hasFile('evidence_image')) {
                    $newPath = $request->file('evidence_image')->store('', 'submission_evidence');
                    $oldPath = $locked->evidence_image_path;
                    $locked->evidence_image_path = $newPath;
                }
                $locked->fill($request->submissionData())->save();
            });
        } catch (Throwable $error) {
            $this->removeEvidence($newPath);
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerSubmission;
use App\Services\SubmissionVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SubmissionVerificationController extends Controller
{
    public function update(Request $request, CustomerSubmission $submission, SubmissionVerification $verification): RedirectResponse
    {
        $this->authorize('review', $submission);
        $data = $request->validateWithBag('verification', [
            'verification_version' => ['required', 'string', 'size:64'],
            'action' => ['required', Rule::in(['group', 'blocker', 'supplement', 'close_supplement'])],
            'group_progress' => ['required_if:action,group', Rule::in(array_keys(SubmissionVerification::GROUP))],
            'blocker_code' => ['nullable', Rule::in(array_keys(SubmissionVerification::BLOCKERS))],
            'supplement_reason' => ['required_if:action,supplement', Rule::in(array_keys(SubmissionVerification::REASONS))],
        ]);
        try {
            $verification->adminUpdate($request->user(), $submission, $data);
        } catch (ValidationException $e) {
            return redirect()->route('admin.submissions.show', $submission)->withErrors($e->errors(), 'verification')
                ->withInput($request->only(['action', 'group_progress', 'blocker_code', 'supplement_reason']));
        }

        return redirect()->route('admin.submissions.show', $submission)->with('success', 'Đã lưu xác minh. Trạng thái duyệt và thành tích được giữ nguyên.');
    }
}

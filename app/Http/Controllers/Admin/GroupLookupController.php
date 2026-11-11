<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GroupLookupRequest;
use App\Models\CustomerSubmission;
use App\Services\AdminGroupLookup;
use App\Services\RegistrationPhone;
use App\Services\SubmissionIntake;
use App\Services\SubmissionVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

final class GroupLookupController extends Controller
{
    private function phone(Request $request): ?string
    {
        $context = $request->session()->get('admin_group_lookup');

        return is_array($context) && ($context['actor'] ?? null) === $request->user()->id
            && ($context['expires'] ?? 0) > now()->timestamp ? $context['phone'] : null;
    }

    public function index(Request $request, AdminGroupLookup $lookup, SubmissionVerification $verification): Response
    {
        $this->authorize('review', new CustomerSubmission);
        $phone = $this->phone($request);
        $match = $phone ? $lookup->find($phone) : ['kind' => 'idle'];
        $submission = isset($match['id']) ? CustomerSubmission::with(['dealer', 'sales'])->find($match['id']) : null;
        $state = $submission ? $verification->state($submission->id) : null;
        $progress = $submission ? $verification->evidencedProgress($submission, $state) : null;
        $version = $submission ? $verification->version($submission, $state) : null;
        $profileUrl = $submission ? $lookup->profileUrl($submission) : null;
        $recent = $lookup->recent($verification);

        return response()->view('admin.group-lookup.index', compact('phone', 'match', 'submission', 'state', 'progress', 'version', 'profileUrl', 'recent'))
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function search(GroupLookupRequest $request): RedirectResponse
    {
        $request->session()->put('admin_group_lookup', ['phone' => RegistrationPhone::normalize($request->validated('phone')),
            'actor' => $request->user()->id, 'expires' => now()->addMinutes(10)->timestamp]);

        return redirect()->route('admin.group-lookup.index')->header('Cache-Control', 'no-store, private');
    }

    public function verify(GroupLookupRequest $request, AdminGroupLookup $lookup, SubmissionVerification $verification): RedirectResponse
    {
        $phone = $this->phone($request);
        $data = $request->validated();
        $name = app(SubmissionIntake::class)->locked(function () use ($phone, $data, $request, $lookup, $verification) {
            $match = $phone ? $lookup->find($phone, true) : [];
            if (($match['id'] ?? null) !== (int) $data['submission_id']) {
                throw ValidationException::withMessages(['lookup' => 'Kết quả tra cứu không còn xác định duy nhất. Vui lòng tra cứu lại.']);
            }
            $submission = CustomerSubmission::whereKey($match['id'])->lockForUpdate()->firstOrFail();
            $this->authorize('review', $submission);
            $state = $verification->state($submission->id, true);
            if (! $lookup->profileUrl($submission) || ! filled($submission->license_plate)
                || $verification->evidencedProgress($submission, $state) === 'member') {
                throw ValidationException::withMessages(['lookup' => 'Hồ sơ không phù hợp hoặc đã được xác minh. Vui lòng tra cứu lại.']);
            }
            $verification->adminUpdate($request->user(), $submission, [
                'action' => 'group', 'group_progress' => 'member', 'verification_version' => $data['verification_version'],
            ]);

            return $submission->customer_name;
        });

        return redirect()->route('admin.group-lookup.index')->with('group_lookup_success', 'Đã xác minh '.$name.' · bấm [Duyệt] bên Facebook');
    }
}

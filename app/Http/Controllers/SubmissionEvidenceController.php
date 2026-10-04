<?php

namespace App\Http\Controllers;

use App\Models\CustomerSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionEvidenceController extends Controller
{
    public function __invoke(Request $request, CustomerSubmission $submission): StreamedResponse
    {
        abort_unless($request->user()->can('view', $submission) || $request->user()->can('review', $submission), 403);
        $disk = Storage::disk('submission_evidence');
        abort_unless($submission->evidence_image_path && $disk->exists($submission->evidence_image_path), 404);

        return $disk->response($submission->evidence_image_path, 'bang-chung.'.pathinfo($submission->evidence_image_path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

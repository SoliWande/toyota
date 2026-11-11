<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CallAudit\ClaimItem;
use App\Actions\CallAudit\ReleaseClaim;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CallAuditClaimRequest;
use App\Services\CallAudit\CallCommandRejected;
use Illuminate\Http\JsonResponse;
use Throwable;

class CallAuditClaimController extends Controller
{
    public function claim(CallAuditClaimRequest $request, int $batch, int $item, ClaimItem $claim): JsonResponse
    {
        return $this->respond(fn () => $claim->execute($batch, $item, $this->input($request)));
    }

    public function release(CallAuditClaimRequest $request, int $batch, int $item, ReleaseClaim $release): JsonResponse
    {
        return $this->respond(fn () => $release->execute($batch, $item, $this->input($request)));
    }

    private function input(CallAuditClaimRequest $request): array
    {
        $input = $request->validated();
        $input['row_version'] = (int) $input['row_version'];

        return $input;
    }

    private function respond(callable $command): JsonResponse
    {
        try {
            return response()->json(['data' => $command()])->header('Cache-Control', 'no-store, private');
        } catch (CallCommandRejected $rejected) {
            return response()->json(['error' => $rejected->reason], $rejected->status)->header('Cache-Control', 'no-store, private');
        } catch (Throwable) {
            // Avoid exception handlers logging SQL bindings, decrypted receipts or tokens.
            return response()->json(['error' => 'audit_command_unavailable'], 503)->header('Cache-Control', 'no-store, private');
        }
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CallAuditPenaltySimulationRequest;
use App\Services\CallAudit\PenaltySimulationReadModel;
use App\Services\CallAudit\ReadUnavailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Throwable;

class CallAuditPenaltySimulationController extends Controller
{
    public function __invoke(CallAuditPenaltySimulationRequest $request, PenaltySimulationReadModel $read): JsonResponse
    {
        try {
            return response()->json($read->read($request->user(), $request->validated('month')))->header('Cache-Control', 'no-store, private');
        } catch (AuthorizationException) {
            return $this->error('simulation_forbidden', 403);
        } catch (ReadUnavailable|DecryptException) {
            return $this->error('simulation_unavailable', 409);
        } catch (Throwable) {
            // Keep private evidence, SQL bindings, reasons and exception details out of response/log.
            return $this->error('simulation_unavailable', 503);
        }
    }

    private function error(string $code, int $status): JsonResponse
    {
        return response()->json(['error' => $code], $status)->header('Cache-Control', 'no-store, private');
    }
}

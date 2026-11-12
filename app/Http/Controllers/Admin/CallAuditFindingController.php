<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CallAuditFindingReadRequest;
use App\Services\CallAudit\FindingReadModel;
use App\Services\CallAudit\ReadUnavailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class CallAuditFindingController extends Controller
{
    public function index(CallAuditFindingReadRequest $request, int $batch, FindingReadModel $read): JsonResponse
    {
        return $this->respond(fn () => $read->index($request->user(), $batch, $request->validated()));
    }

    public function show(CallAuditFindingReadRequest $request, int $batch, int $item, FindingReadModel $read): JsonResponse
    {
        return $this->respond(fn () => $read->show($request->user(), $batch, $item, $request->validated()));
    }

    private function respond(callable $read): JsonResponse
    {
        try {
            return response()->json($read())->header('Cache-Control', 'no-store, private');
        } catch (AuthorizationException) {
            return $this->error('audit_findings_forbidden', 403);
        } catch (ModelNotFoundException|NotFoundHttpException) {
            return $this->error('audit_finding_not_found', 404);
        } catch (ReadUnavailable) {
            return $this->error('audit_findings_unavailable', 409);
        } catch (Throwable) {
            // No decrypted receipt, PII, SQL bindings or exception details in response/log.
            return $this->error('audit_findings_unavailable', 409);
        }
    }

    private function error(string $code, int $status): JsonResponse
    {
        return response()->json(['error' => $code], $status)->header('Cache-Control', 'no-store, private');
    }
}

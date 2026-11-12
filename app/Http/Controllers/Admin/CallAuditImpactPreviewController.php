<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CallAuditImpactPreviewRequest;
use App\Services\CallAudit\ImpactPreviewReadModel;
use App\Services\CallAudit\ReadUnavailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class CallAuditImpactPreviewController extends Controller
{
    public function index(CallAuditImpactPreviewRequest $request, ImpactPreviewReadModel $read): JsonResponse
    {
        return $this->respond(fn () => $read->read($request->user(), $request->validated()));
    }

    public function show(CallAuditImpactPreviewRequest $request, int $batch, int $item, ImpactPreviewReadModel $read): JsonResponse
    {
        return $this->respond(fn () => $read->read($request->user(), $request->validated(), $batch, $item));
    }

    private function respond(callable $read): JsonResponse
    {
        try {
            return response()->json($read())->header('Cache-Control', 'no-store, private');
        } catch (AuthorizationException) {
            return $this->error('impact_preview_forbidden', 403);
        } catch (ModelNotFoundException|NotFoundHttpException) {
            return $this->error('impact_candidate_not_found', 404);
        } catch (ReadUnavailable|DecryptException) {
            return $this->error('impact_preview_unavailable', 409);
        } catch (Throwable) {
            // Never report private evidence, reasons, PII or SQL bindings to response/log.
            return $this->error('impact_preview_unavailable', 503);
        }
    }

    private function error(string $code, int $status): JsonResponse
    {
        return response()->json(['error' => $code], $status)->header('Cache-Control', 'no-store, private');
    }
}

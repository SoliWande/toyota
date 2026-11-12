<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CallAudit\ResolveFinding;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CallAuditFindingResolutionRequest;
use App\Services\CallAudit\CallCommandRejected;
use App\Services\CallAudit\FindingResolutionReadModel;
use App\Services\CallAudit\ReadUnavailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class CallAuditFindingResolutionController extends Controller
{
    public function store(CallAuditFindingResolutionRequest $request, int $batch, int $item, ResolveFinding $resolve): JsonResponse
    {
        $input = $request->validated();
        foreach (['attempt_id', 'row_version', 'resolution_version'] as $key) {
            $input[$key] = (int) $input[$key];
        }

        return $this->respond(fn () => ['data' => $resolve->execute($batch, $item, $input)]);
    }

    public function show(CallAuditFindingResolutionRequest $request, int $batch, int $item, FindingResolutionReadModel $read): JsonResponse
    {
        return $this->respond(fn () => $read->read($request->user(), $batch, $item, $request->validated()));
    }

    public function history(CallAuditFindingResolutionRequest $request, int $batch, int $item, FindingResolutionReadModel $read): JsonResponse
    {
        return $this->respond(fn () => $read->read($request->user(), $batch, $item, $request->validated(), true));
    }

    private function respond(callable $read): JsonResponse
    {
        try {
            return response()->json($read())->header('Cache-Control', 'no-store, private');
        } catch (CallCommandRejected $error) {
            return $this->error($error->reason, $error->status);
        } catch (AuthorizationException) {
            return $this->error('finding_resolution_forbidden', 403);
        } catch (ModelNotFoundException|NotFoundHttpException) {
            return $this->error('audit_finding_not_found', 404);
        } catch (ReadUnavailable|DecryptException) {
            return $this->error('finding_resolution_unavailable', 409);
        } catch (Throwable) {
            // Do not send decrypted reasons, SQL bindings or private exception details to logs/responses.
            return $this->error('finding_resolution_unavailable', 503);
        }
    }

    private function error(string $code, int $status): JsonResponse
    {
        return response()->json(['error' => $code], $status)->header('Cache-Control', 'no-store, private');
    }
}

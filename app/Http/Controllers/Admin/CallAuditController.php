<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CallAuditReadRequest;
use App\Services\CallAudit\BatchReadModel;
use App\Services\CallAudit\ReadUnavailable;
use Illuminate\Http\JsonResponse;

class CallAuditController extends Controller
{
    public function index(CallAuditReadRequest $request, BatchReadModel $read): JsonResponse
    {
        return $this->respond(fn () => $read->index($request->user(), $request->validated()));
    }

    public function show(CallAuditReadRequest $request, int $batch, BatchReadModel $read): JsonResponse
    {
        return $this->respond(fn () => $read->show($request->user(), $batch, $request->validated()));
    }

    public function items(CallAuditReadRequest $request, int $batch, BatchReadModel $read): JsonResponse
    {
        return $this->respond(fn () => $read->items($request->user(), $batch, $request->validated()));
    }

    public function item(CallAuditReadRequest $request, int $batch, int $item, BatchReadModel $read): JsonResponse
    {
        return $this->respond(fn () => $read->showItem($request->user(), $batch, $item, $request->validated()));
    }

    private function respond(callable $read): JsonResponse
    {
        try {
            return response()->json($read())->header('Cache-Control', 'no-store, private');
        } catch (ReadUnavailable) {
            return response()->json(['error' => 'audit_read_unavailable'], 409)->header('Cache-Control', 'no-store, private');
        }
    }
}

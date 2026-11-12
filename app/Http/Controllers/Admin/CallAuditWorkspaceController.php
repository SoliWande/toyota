<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CallAuditWorkspaceRequest;
use App\Models\CallAuditBatch;
use App\Models\User;
use App\Services\CallAudit\CallCommandRejected;
use App\Services\CallAudit\ReadUnavailable;
use App\Services\CallAudit\WorkspaceReadModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Throwable;

class CallAuditWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        Gate::forUser(User::findOrFail($request->user()->id))->authorize('viewAny', CallAuditBatch::class);
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'purpose' => ['nullable', Rule::in(['general_audit', 'award_top3'])], 'batch' => ['nullable', 'integer', 'min:1']]);
        abort_if(array_diff(array_keys($request->query()), ['month', 'purpose', 'batch']), 422);
        $purpose = $data['purpose'] ?? 'general_audit';
        $batchId = $data['batch'] ?? null;
        if ($batchId) {
            CallAuditBatch::whereKey($batchId)->where('purpose', $purpose)->where('lifecycle_state', 'frozen')
                ->when($data['month'] ?? null, fn ($q, $month) => $q->where('monthly_scope_key', $month))->firstOrFail(['id']);
        }

        return response()->view('admin.call-audits.index', ['month' => $data['month'] ?? null, 'purpose' => $purpose, 'batchId' => $batchId])->header('Cache-Control', 'no-store, private');
    }

    public function details(CallAuditWorkspaceRequest $request, int $batch, int $item, WorkspaceReadModel $workspace)
    {
        try {
            return response()->json(['data' => $workspace->read($batch, $item, $request->validated())])->header('Cache-Control', 'no-store, private');
        } catch (CallCommandRejected $rejected) {
            return response()->json(['error' => $rejected->reason], $rejected->status)->header('Cache-Control', 'no-store, private');
        } catch (ReadUnavailable) {
            return response()->json(['error' => 'audit_read_unavailable'], 409)->header('Cache-Control', 'no-store, private');
        } catch (Throwable) {
            return response()->json(['error' => 'workspace_unavailable'], 409)->header('Cache-Control', 'no-store, private');
        }
    }
}

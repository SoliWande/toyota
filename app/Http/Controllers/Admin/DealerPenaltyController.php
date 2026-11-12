<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CallAudit\ApplyDealerPenalty;
use App\Actions\CallAudit\CreateAndFreezeBatch;
use App\Actions\PublishAuditedAward;
use App\Enums\LeaderboardPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CallAuditFreezeRequest;
use App\Http\Requests\Admin\DealerPenaltyRequest;
use App\Models\Award;
use App\Models\User;
use App\Services\AwardPublicationReadiness;
use App\Services\CallAudit\CallCommandRejected;
use App\Services\CallAudit\DealerPenaltyReadModel;
use App\Services\CallAudit\ReadUnavailable;
use App\Services\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class DealerPenaltyController extends Controller
{
    public function freeze(CallAuditFreezeRequest $request, CreateAndFreezeBatch $freeze)
    {
        $input = $request->validated();

        return $this->respond(function () use ($input, $freeze) {
            $result = $freeze->execute($input['purpose'], CarbonImmutable::createFromFormat('!Y-m', $input['month'], config('app.timezone')),
                $input['idempotency_key'], isset($input['award_id']) ? (int) $input['award_id'] : null)->summary;
            if ($result['status'] !== 'FROZEN') {
                throw new CallCommandRejected($result['code']);
            }

            return ['data' => $result];
        });
    }

    public function scores(DealerPenaltyRequest $request, LeaderboardService $leaderboard)
    {
        return $this->respond(fn () => ['month' => $request->validated('month'), 'data' => $leaderboard->dealers(LeaderboardPeriod::Month,
            CarbonImmutable::createFromFormat('!Y-m', $request->validated('month'), config('app.timezone')))->get()
            ->map(fn ($row) => ['dealer_id' => (int) $row->id, 'raw_score' => (int) $row->raw_score, 'penalty_points' => (int) $row->penalty_points,
                'net_score' => (int) $row->net_score, 'display_score' => (int) $row->display_score, 'rank' => (int) $row->rank])->all()]);
    }

    public function store(DealerPenaltyRequest $request, int $batch, int $item, ApplyDealerPenalty $apply)
    {
        $input = $request->validated();
        $input['resolution_version'] = (int) $input['resolution_version'];

        return $this->respond(fn () => ['data' => $apply->execute($batch, $item, $input)]);
    }

    public function index(DealerPenaltyRequest $request, DealerPenaltyReadModel $read)
    {
        return $this->respond(fn () => $read->read($request->user(), $request->validated('month')));
    }

    public function publication(Request $request, int $award, AwardPublicationReadiness $read)
    {
        if ($request->query->count()) {
            return $this->error('invalid_publication_command', 422);
        }

        return $this->respond(fn () => $read->read($request->user(), $award));
    }

    public function supplemental(Request $request, int $award, CreateAndFreezeBatch $freeze)
    {
        $body = $request->isJson() ? $request->json()->all() : $request->request->all();
        if ($request->query->count() || array_diff(array_keys($body), ['_token', 'idempotency_key'])) {
            return $this->error('invalid_sampling_command', 422);
        }
        $input = $request->validate(['idempotency_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,64}$/D']]);

        return $this->respond(function () use ($request, $award, $freeze, $input) {
            $model = Award::findOrFail($award);
            Gate::forUser(User::findOrFail($request->user()->id))->authorize('view', $model);
            $result = $freeze->execute('award_top3', $model->period_start, $input['idempotency_key'], $award, true)->summary;
            if ($result['status'] !== 'FROZEN') {
                throw new CallCommandRejected($result['code']);
            }

            return ['data' => $result];
        });
    }

    public function publish(Request $request, int $award, PublishAuditedAward $publish)
    {
        $body = $request->isJson() ? $request->json()->all() : $request->request->all();
        if ($request->query->count() || array_diff(array_keys($body), ['_token', 'idempotency_key', 'preview_fingerprint'])) {
            return $this->error('invalid_publication_command', 422);
        }
        $input = $request->validate(['idempotency_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,64}$/D'],
            'preview_fingerprint' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/D']]);

        return $this->respond(fn () => ['data' => $publish->execute($award, $input['idempotency_key'], $input['preview_fingerprint'] ?? null)]);
    }

    private function respond(callable $operation, int $status = 200)
    {
        try {
            return response()->json($operation(), $status)->header('Cache-Control', 'no-store, private');
        } catch (CallCommandRejected $error) {
            return $this->error($error->reason, $error->status);
        } catch (AuthorizationException) {
            return $this->error('penalty_forbidden', 403);
        } catch (ModelNotFoundException|NotFoundHttpException) {
            return $this->error('penalty_not_found', 404);
        } catch (ReadUnavailable|DecryptException) {
            return $this->error('penalty_unavailable', 409);
        } catch (Throwable) {
            return $this->error('penalty_unavailable', 503);
        }
    }

    private function error(string $code, int $status)
    {
        return response()->json(['error' => $code], $status)->header('Cache-Control', 'no-store, private');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeaderboardPeriod;
use App\Enums\SubmissionStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DealerRequest;
use App\Models\Dealer;
use App\Services\AdministrativeDivisions;
use App\Services\DealerProfile;
use App\Services\DealerStandee;
use App\Services\DemoSubmissionScope;
use App\Services\LeaderboardService;
use App\Services\SalesAvatars;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class DealerController extends Controller
{
    public function index(Request $request, LeaderboardService $leaderboard): View
    {
        $this->authorize('viewAny', Dealer::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'no_customers', 'no_manager'])],
            'province' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['month', 'total', 'name'])],
            'selected' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        [$start, $end] = $leaderboard->bounds(LeaderboardPeriod::Month);
        $approved = fn ($query) => app(DemoSubmissionScope::class)->apply($query)->where('status', SubmissionStatus::Approved->value);
        $monthly = fn ($query) => $approved($query)->where('submitted_at', '>=', $start)->where('submitted_at', '<', $end);
        $activeManager = fn ($query) => $query->where('status', UserStatus::Active->value);
        $counts = [
            'all' => Dealer::count(),
            'active' => Dealer::where('is_active', true)->count(),
            'inactive' => Dealer::where('is_active', false)->count(),
            'no_customers' => Dealer::whereDoesntHave('submissions', $monthly)->count(),
            'no_manager' => Dealer::whereDoesntHave('manager', $activeManager)->count(),
        ];
        $query = Dealer::with(['manager' => $activeManager])->withCount([
            'sales', 'sales as active_sales_count' => fn ($query) => $query->where('status', UserStatus::Active->value),
            'submissions as month_count' => $monthly, 'submissions as approved_count' => $approved,
        ]);
        match ($filters['status'] ?? '') {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            'no_customers' => $query->whereDoesntHave('submissions', $monthly),
            'no_manager' => $query->whereDoesntHave('manager', $activeManager),
            default => null,
        };
        if (filled($filters['province'] ?? null)) {
            $query->where('province', $filters['province']);
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(function ($query) use ($search) {
                foreach (['name', 'code', 'province', 'phone', 'address'] as $field) {
                    $query->orWhereRaw($field." LIKE ? ESCAPE '!'", [$search]);
                }
            });
        }

        match ($filters['sort'] ?? 'month') {
            'month' => $query->orderByDesc('month_count'),
            'total' => $query->orderByDesc('approved_count'),
            default => null,
        };
        $dealers = $query->orderBy('name')->orderBy('id')->paginate(20)->withQueryString();
        // Selection is restricted to the current filtered page, including after a mutation.
        $selected = $dealers->firstWhere('id', $filters['selected'] ?? null) ?? $dealers->first();
        $standing = $selected ? DB::query()->fromSub($leaderboard->dealers(LeaderboardPeriod::Month), 'standing')->where('id', $selected->id)->first() : null;
        $provinces = Dealer::whereNotNull('province')->where('province', '!=', '')->distinct()->orderBy('province')->pluck('province');
        $formProvinces = collect(app(AdministrativeDivisions::class)->provinces())->pluck('name')->merge($provinces)->unique()->sort()->values();

        return view('admin.dealers.index', compact('dealers', 'filters', 'counts', 'selected', 'standing', 'provinces', 'formProvinces'));
    }

    public function create(): View
    {
        $this->authorize('create', Dealer::class);

        return view('admin.dealers.form', ['dealer' => new Dealer]);
    }

    public function show(Request $request, Dealer $dealer, LeaderboardService $leaderboard): View
    {
        $this->authorize('view', $dealer);
        $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'customers_page' => ['nullable', 'integer', 'min:1']]);
        $standing = DB::query()->fromSub($leaderboard->dealers(LeaderboardPeriod::AllTime), 'standing')->where('id', $dealer->id)->first();
        $sales = $dealer->sales()->select(['id', 'name', 'email', 'status'])->orderBy('name')->orderBy('id')->paginate(20)
            ->appends($request->only('customers_page'));
        $customers = $dealer->submissions()->select(['id', 'sales_id', 'dealer_id', 'customer_name', 'phone', 'status', 'submitted_at'])
            ->with('sales:id,name')->orderByDesc('submitted_at')->orderByDesc('id')->paginate(20, ['*'], 'customers_page')
            ->appends($request->only('page'));
        $approvedCustomers = app(DemoSubmissionScope::class)->apply($dealer->submissions())
            ->where('status', SubmissionStatus::Approved->value)->count();

        return view('admin.dealers.show', compact('dealer', 'standing', 'sales', 'customers', 'approvedCustomers'));
    }

    public function store(DealerRequest $request): RedirectResponse|JsonResponse
    {
        $dealer = new Dealer;
        $this->save($dealer, $request);

        if ($request->expectsJson()) {
            return response()->json(['id' => $dealer->id, 'message' => 'Đã tạo đại lý · QR và standee đã sẵn sàng'], 201);
        }

        return redirect()->route('admin.dealers.edit', $dealer)->with('success', 'Đã tạo đại lý.');
    }

    public function edit(Dealer $dealer): View
    {
        $this->authorize('update', $dealer);

        return view('admin.dealers.form', compact('dealer'));
    }

    public function update(DealerRequest $request, Dealer $dealer): RedirectResponse|JsonResponse
    {
        $this->save($dealer, $request);

        if ($request->expectsJson()) {
            return response()->json(['id' => $dealer->id, 'message' => 'Đã lưu thông tin đại lý']);
        }

        return redirect()->route('admin.dealers.edit', $dealer)->with('success', 'Đã cập nhật đại lý.');
    }

    public function status(Request $request, Dealer $dealer): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $dealer);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $dealer->update($data);

        if ($request->expectsJson()) {
            return response()->json(['id' => $dealer->id, 'message' => $dealer->is_active ? null : 'Đã ngừng tham gia '.$dealer->name]);
        }

        return redirect()->route('admin.dealers.index')->with('success', 'Đã cập nhật trạng thái đại lý.');
    }

    public function download(Dealer $dealer, string $format, DealerProfile $profile, DealerStandee $standee): Response
    {
        // Admin-only adapter; Manager own-Dealer endpoints retain their existing policy.
        $this->authorize('viewAny', Dealer::class);
        $pdf = $format === 'pdf';

        return response($pdf ? $standee->pdf($dealer, $profile) : $profile->qr($dealer), 200, [
            'Content-Type' => $pdf ? 'application/pdf' : 'image/png',
            'Content-Disposition' => 'attachment; filename="dealer-'.$dealer->id.($pdf ? '-standee-a4.pdf' : '-qr.png').'"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function save(Dealer $dealer, DealerRequest $request): void
    {
        $data = $request->safe()->except('photo');
        $photo = $request->hasFile('photo') ? app(SalesAvatars::class)->store($request->file('photo')->getRealPath(), 'dealer_photos', 1200) : null;
        try {
            DB::transaction(function () use ($dealer, $data, $photo) {
                if ($dealer->exists) {
                    $dealer = Dealer::whereKey($dealer->id)->lockForUpdate()->firstOrFail();
                }
                $previous = $dealer->photo_path;
                $dealer->fill($data);
                if ($photo !== null) {
                    $dealer->photo_path = $photo;
                }
                $dealer->save();
                if ($photo !== null && $previous) {
                    DB::afterCommit(fn () => rescue(fn () => Storage::disk('dealer_photos')->delete($previous)));
                }
            });
        } catch (Throwable $exception) {
            if ($photo !== null) {
                Storage::disk('dealer_photos')->delete($photo);
            }
            if (! $exception instanceof QueryException) {
                throw $exception;
            }
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }
            if (str_contains($exception->getMessage(), 'dealers_slug_unique')) {
                throw ValidationException::withMessages(['name' => 'Một đại lý cùng tên vừa được tạo. Vui lòng thử lại để nhận slug riêng.']);
            }

            throw ValidationException::withMessages(['code' => 'Mã đại lý này đã tồn tại.']);
        }
    }
}

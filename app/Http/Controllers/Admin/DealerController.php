<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeaderboardPeriod;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DealerRequest;
use App\Models\Dealer;
use App\Services\LeaderboardService;
use App\Services\SalesAvatars;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class DealerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Dealer::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', Rule::in(['active', 'inactive'])], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = Dealer::withCount('sales');
        if (filled($filters['status'] ?? null)) {
            $query->where('is_active', $filters['status'] === 'active');
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(function ($query) use ($search) {
                foreach (['name', 'code', 'province', 'phone', 'address'] as $field) {
                    $query->orWhereRaw($field." LIKE ? ESCAPE '!'", [$search]);
                }
            });
        }

        return view('admin.dealers.index', ['dealers' => $query->orderBy('name')->orderBy('id')->paginate(20)->withQueryString(), 'filters' => $filters]);
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
        $approvedCustomers = $dealer->submissions()->where('status', SubmissionStatus::Approved->value)->count();

        return view('admin.dealers.show', compact('dealer', 'standing', 'sales', 'customers', 'approvedCustomers'));
    }

    public function store(DealerRequest $request): RedirectResponse
    {
        $dealer = new Dealer;
        $this->save($dealer, $request);

        return redirect()->route('admin.dealers.edit', $dealer)->with('success', 'Đã tạo đại lý.');
    }

    public function edit(Dealer $dealer): View
    {
        $this->authorize('update', $dealer);

        return view('admin.dealers.form', compact('dealer'));
    }

    public function update(DealerRequest $request, Dealer $dealer): RedirectResponse
    {
        $this->save($dealer, $request);

        return redirect()->route('admin.dealers.edit', $dealer)->with('success', 'Đã cập nhật đại lý.');
    }

    public function status(Request $request, Dealer $dealer): RedirectResponse
    {
        $this->authorize('update', $dealer);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $dealer->update($data);

        return redirect()->route('admin.dealers.index')->with('success', 'Đã cập nhật trạng thái đại lý.');
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

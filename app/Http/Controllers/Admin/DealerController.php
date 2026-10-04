<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DealerRequest;
use App\Models\Dealer;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DealerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Dealer::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = Dealer::withCount('sales');
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

    public function store(DealerRequest $request): RedirectResponse
    {
        $dealer = new Dealer;
        $this->save($dealer, $request->validated());

        return redirect()->route('admin.dealers.edit', $dealer)->with('success', 'Đã tạo đại lý.');
    }

    public function edit(Dealer $dealer): View
    {
        $this->authorize('update', $dealer);

        return view('admin.dealers.form', compact('dealer'));
    }

    public function update(DealerRequest $request, Dealer $dealer): RedirectResponse
    {
        $this->save($dealer, $request->validated());

        return redirect()->route('admin.dealers.edit', $dealer)->with('success', 'Đã cập nhật đại lý.');
    }

    public function status(Request $request, Dealer $dealer): RedirectResponse
    {
        $this->authorize('update', $dealer);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $dealer->update($data);

        return redirect()->route('admin.dealers.index')->with('success', 'Đã cập nhật trạng thái đại lý.');
    }

    private function save(Dealer $dealer, array $data): void
    {
        try {
            $dealer->fill($data)->save();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }

            throw ValidationException::withMessages(['code' => 'Mã đại lý này đã tồn tại.']);
        }
    }
}

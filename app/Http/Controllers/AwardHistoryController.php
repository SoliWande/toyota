<?php

namespace App\Http\Controllers;

use App\Enums\AwardPeriod;
use App\Services\PublicAwardResults;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AwardHistoryController extends Controller
{
    public function index(Request $request, PublicAwardResults $results): View
    {
        $data = $request->validate(['period_type' => ['nullable', Rule::enum(AwardPeriod::class)], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = $results->query();
        if (! empty($data['period_type'])) {
            $query->where('period_type', $data['period_type']);
        }

        return view('awards.index', ['awards' => $query->orderByDesc('period_start')->orderByDesc('id')->paginate(12)
            ->appends($request->only('period_type')), 'periodType' => $data['period_type'] ?? '']);
    }

    public function show(int $award, PublicAwardResults $results): View
    {
        return view('awards.show', ['award' => $results->find($award)]);
    }
}

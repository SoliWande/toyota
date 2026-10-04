<?php

namespace App\Http\Controllers;

use App\Enums\AwardPeriod;
use App\Models\Award;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AwardHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['period_type' => ['nullable', Rule::enum(AwardPeriod::class)], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = Award::whereNotNull('published_at');
        if (! empty($data['period_type'])) {
            $query->where('period_type', $data['period_type']);
        }

        return view('awards.index', ['awards' => $query->orderByDesc('period_start')->orderByDesc('id')->paginate(12)->withQueryString(), 'periodType' => $data['period_type'] ?? '']);
    }

    public function show(Award $award): View
    {
        abort_if($award->published_at === null, 404);

        return view('awards.show', ['award' => $award->load(['winners' => fn ($query) => $query->orderBy('rank')])]);
    }
}

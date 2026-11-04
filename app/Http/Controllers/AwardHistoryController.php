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
        $history = $this->history($request, $results);

        return view('awards.index', [...$history, 'selectedAward' => $history['awards']->first()]);
    }

    public function show(Request $request, int $award, PublicAwardResults $results): View
    {
        // Selection still goes through the existing published Dealer-only gate.
        $selected = $results->find($award);

        return view('awards.show', [...$this->history($request, $results), 'award' => $selected, 'selectedAward' => $selected]);
    }

    private function history(Request $request, PublicAwardResults $results): array
    {
        $data = $request->validate(['period_type' => ['nullable', Rule::enum(AwardPeriod::class)], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = $results->query();
        if (! empty($data['period_type'])) {
            $query->where('period_type', $data['period_type']);
        }

        // B03 needs leading snapshot names for every visible period. One bounded
        // eager load avoids lazy per-period queries and selects no Sales fields.
        return ['awards' => $results->withDealers($query)->orderByDesc('period_start')->orderByDesc('id')->paginate(12)
            ->appends($request->only('period_type')), 'periodType' => $data['period_type'] ?? ''];
    }
}

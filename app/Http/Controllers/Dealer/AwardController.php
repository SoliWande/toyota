<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Services\DealerDashboardData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AwardController extends Controller
{
    public function index(Request $request, DealerDashboardData $data): View
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $dealer = $data->currentDealer($request->user());

        return view('dealer.awards.index', ['dealer' => $dealer, 'awards' => $data->awards($dealer)->paginate(20)]);
    }
}

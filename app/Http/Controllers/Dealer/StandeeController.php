<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Services\DealerDashboardData;
use App\Services\DealerProfile;
use App\Services\DealerStandee;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class StandeeController extends Controller
{
    public function __invoke(Request $request, DealerDashboardData $data, DealerProfile $profile, DealerStandee $standee): Response
    {
        $dealer = $data->currentDealer($request->user());
        $this->authorize('view', $dealer);

        return response($standee->pdf($dealer, $profile), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="dealer-'.$dealer->id.'-standee-a4.pdf"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

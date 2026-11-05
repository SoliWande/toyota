<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class DealerRegistrationController extends Controller
{
    public function __invoke(): View
    {
        $this->authorize('reviewDealerRegistrations', User::class);

        return view('admin.dealer-registrations', ['accounts' => User::where('role', 'dealer')->where('status', 'pending')
            ->with('dealer')->orderBy('id')->paginate(20)]);
    }
}

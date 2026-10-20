<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Services\AdministrativeDivisions;
use Illuminate\Http\JsonResponse;

class AddressController extends Controller
{
    public function __invoke(string $province, AdministrativeDivisions $directory): JsonResponse
    {
        abort_if($directory->province($province) === null, 404);

        return response()->json(['wards' => $directory->wards($province)]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesAvatarController extends Controller
{
    public function __invoke(Request $request, User $sales): StreamedResponse
    {
        abort_unless($sales->role === UserRole::Sales && ($request->user()->is($sales) || $request->user()->can('view', $sales)), 403);
        $disk = Storage::disk('sales_avatars');
        abort_unless($sales->avatar_path && $disk->exists($sales->avatar_path), 404);

        return $disk->response($sales->avatar_path, 'avatar.jpg', [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

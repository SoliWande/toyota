<?php

namespace App\Http\Controllers;

use App\Services\SalesPublicProfile;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicSalesProfileController extends Controller
{
    public function show(string $slug, SalesPublicProfile $profiles): Response
    {
        $profile = $profiles->data($profiles->find($slug));

        return response()->view('sales.public-profile', compact('profile'))->header('Cache-Control', 'no-store, private');
    }

    public function avatar(string $slug, SalesPublicProfile $profiles): StreamedResponse
    {
        $sales = $profiles->find($slug);
        $disk = Storage::disk('sales_avatars');
        abort_unless($profiles->hasPublicAvatar($sales) && $disk->exists($sales->avatar_path), 404);

        // Keep the storage root private: stream only the selected Sales avatar, never arbitrary files.
        return $disk->response($sales->avatar_path, 'avatar.jpg', [
            'Content-Type' => 'image/jpeg', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private',
        ]);
    }
}

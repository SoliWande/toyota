<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Services\DealerProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DealerProfileMediaController extends Controller
{
    public function qr(Request $request, Dealer $dealer, DealerProfile $profile): Response
    {
        $data = $request->validate(['download' => ['nullable', 'boolean']]);

        return response($profile->qr($dealer), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => (($data['download'] ?? false) ? 'attachment' : 'inline').'; filename="dealer-'.$dealer->id.'-qr.png"',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function photo(Dealer $dealer): StreamedResponse
    {
        $disk = Storage::disk('dealer_photos');
        abort_unless($dealer->photo_path && $disk->exists($dealer->photo_path), 404);

        return $disk->response($dealer->photo_path, 'dealer.jpg', [
            'Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=300', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

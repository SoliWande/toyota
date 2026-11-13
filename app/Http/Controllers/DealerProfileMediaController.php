<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Services\DealerIntroductionContact;
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

    public function photo(Dealer $dealer, DealerIntroductionContact $contacts): StreamedResponse
    {
        $photo = $contacts->photoFor($dealer);
        abort_unless($photo, 404);
        $disk = Storage::disk($photo['disk']);

        return $disk->response($photo['path'], 'dealer.jpg', [
            'Content-Type' => 'image/jpeg', 'Cache-Control' => $photo['manager'] ? 'private, no-store' : 'public, max-age=300', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

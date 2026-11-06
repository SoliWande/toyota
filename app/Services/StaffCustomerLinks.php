<?php

namespace App\Services;

use App\Models\CustomerSubmission;
use App\Models\User;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

final class StaffCustomerLinks
{
    public function sales(User $sales): ?string
    {
        if (! $sales->dealer || ! preg_match('/^s-[a-f0-9]{32}$/D', $sales->public_slug ?? '')) {
            return null;
        }
        $dealerUrl = app(DealerProfile::class)->url($sales->dealer);
        $base = substr($dealerUrl, 0, -strlen('/'.$sales->dealer->slug.'/'));

        return $base.route('sales.public.show', ['slug' => $sales->public_slug], false);
    }

    public function lookup(CustomerSubmission $submission): ?string
    {
        if (! preg_match('/^0[1-9][0-9]{8,9}$/D', RegistrationPhone::normalize($submission->phone ?? ''))
            || ! preg_match('/^[A-Z0-9]{3,20}$/D', SubmissionIntake::plate($submission->license_plate ?? ''))) {
            return null; // Do not promise a usable phone/plate lookup when identity fields are missing.
        }

        return $submission->dealer ? app(DealerProfile::class)->url($submission->dealer).'registrations/lookup' : null;
    }

    public function qr(string $url): string
    {
        return 'data:image/png;base64,'.base64_encode((new Writer(new GDLibRenderer(600)))->writeString($url));
    }

    public function message(?string $url): ?string
    {
        return $url ? 'Bạn có thể tra cứu hồ sơ tại '.$url.'. Vui lòng nhập số điện thoại và biển số đã đăng ký. Nếu cần hỗ trợ, liên hệ đại lý.' : null;
    }
}

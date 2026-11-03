<?php

namespace App\Services;

use App\Models\Dealer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class DealerRegistrationReceipt
{
    public function owner(Request $request, Dealer $dealer): string
    {
        return RegistrationPhone::key('receipt|'.$request->session()->getId().'|'.$dealer->id);
    }

    public function key(Request $request, Dealer $dealer, string $draftId): string
    {
        return RegistrationPhone::key($this->owner($request, $dealer).'|'.$draftId);
    }

    public function find(Request $request, Dealer $dealer, ?string $draftId = null): ?array
    {
        $query = DB::table('dealer_registration_receipts')->where('owner_key', $this->owner($request, $dealer));
        if ($draftId !== null) {
            $query->where('draft_key', $this->key($request, $dealer, $draftId));
        }
        $record = $query->where('expires_at', '>', now())->orderByDesc('created_at')->orderByDesc('submission_id')->first();

        return $record ? json_decode(Crypt::decryptString($record->confirmation), true, flags: JSON_THROW_ON_ERROR) : null;
    }
}

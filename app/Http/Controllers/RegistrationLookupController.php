<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrationLookupRequest;
use App\Models\Dealer;
use App\Services\DealerRegistrationDraft;
use App\Services\RegistrationLookup;
use App\Services\RegistrationLookupStateResolver;
use App\Services\RegistrationPhone;
use App\Services\SubmissionIntake;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class RegistrationLookupController extends Controller
{
    public function show(Request $request): Response
    {
        return $this->page($request);
    }

    public function store(RegistrationLookupRequest $request, RegistrationLookup $lookup, DealerRegistrationDraft $drafts): Response
    {
        $data = $request->validated();
        $dealer = $this->dealer($request);
        $result = $lookup->find($data['phone'], $data['license_plate'],
            fn ($id, $summary) => app(RegistrationLookupStateResolver::class)->resolve($id, $summary, $request, $dealer));
        if ($result === null) {
            $draft = $drafts->read($request, $dealer);
            $values = $draft['values'] ?? [];
            $plate = $draft['step_two']['values']['license_plate'] ?? '';
            if (($draft['complete'] ?? false) && is_string($plate) && $plate !== ''
                && RegistrationPhone::normalize($values['phone'] ?? '') === RegistrationPhone::normalize($data['phone'])
                && SubmissionIntake::plate($plate) === SubmissionIntake::plate($data['license_plate'])) {
                $result = $lookup->summary($values['customer_name'], $values['vehicle_description'] ?? null);
                $result['kind'] = 'draft'; // Proven only by this session's valid encrypted draft.
            }
        }

        return $this->page($request, result: $result ?? ['kind' => 'not_found']);
    }

    public function page(Request $request, array $errors = [], ?array $result = null, int $status = 200): Response
    {
        $dealer = $this->dealer($request);
        $values = [];
        if ($request->isMethod('POST')) {
            foreach (['phone', 'license_plate'] as $field) {
                $value = $request->input($field);
                $values[$field] = is_string($value) ? mb_substr($value, 0, 30) : '';
            }
        }

        return response()->view('dealers.registration.lookup', compact('dealer', 'values', 'errors', 'result'), $status)
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function dealer(Request $request): Dealer
    {
        // Inactive Dealers may still serve read-only lookup for historical registrations.
        return Dealer::where('slug', $request->route('slug'))
            ->firstOrFail(['id', 'slug', 'name', 'phone', 'is_active']);
    }
}

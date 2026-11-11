<?php

namespace App\Services\CallAudit;

use App\Models\CustomerSubmission;
use LogicException;

final class SourceFingerprint
{
    public const VERSION = 'call_audit_source_v1';

    public const FIELDS = ['id', 'status', 'source', 'dealer_id', 'sales_id', 'customer_name', 'phone', 'phone_normalized',
        'license_plate', 'facebook_url', 'facebook_url_normalized', 'approved_facebook_identity',
        'vehicle_model', 'vehicle_description', 'first_registration_year', 'vehicle_color',
        'submitted_at', 'reviewed_at', 'updated_at'];

    public function __construct(private ManifestCodec $codec) {}

    public function keyId(): string
    {
        return 'app:'.substr(hash('sha256', $this->rootKey()), 0, 32);
    }

    public function of(CustomerSubmission $submission): string
    {
        $data = [];
        foreach (self::FIELDS as $field) {
            $data[$field] = $submission->getRawOriginal($field);
        }
        foreach (['id', 'dealer_id', 'sales_id', 'first_registration_year'] as $field) {
            $data[$field] = $data[$field] === null ? null : (int) $data[$field];
        }
        // Historic attribution only; a Sales transfer must not move the sample.
        $data['dealer_snapshot'] = $submission->dealer === null ? null : [
            'id' => (int) $submission->dealer->id, 'name' => $submission->dealer->name, 'code' => $submission->dealer->code,
        ];
        $key = hash_hmac('sha256', 'call-audit/source-key/v1', $this->rootKey(), true);

        return hash_hmac('sha256', $this->codec->encode(['version' => self::VERSION, 'source' => $data]), $key);
    }

    public function matches(string $fingerprint, CustomerSubmission $current): bool
    {
        return hash_equals($fingerprint, $this->of($current));
    }

    private function rootKey(): string
    {
        $key = config('app.key');
        if (! is_string($key) || $key === '') {
            throw new LogicException('Call audit fingerprints require the application key.');
        }

        return $key;
    }
}

<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/** Read-only canonical scan. Only counts and generic field messages leave this service. */
final class SubmissionCollisionAudit
{
    public const MESSAGES = [
        'phone' => 'Số điện thoại đã được sử dụng trong một đăng ký khác.',
        'license_plate' => 'Biển số xe đã được sử dụng trong một đăng ký khác.',
    ];

    private function identities(object $row): array
    {
        return ['phone' => array_unique([RegistrationPhone::normalize($row->phone ?? ''), RegistrationPhone::normalize($row->phone_normalized ?? '')]),
            'license_plate' => [SubmissionIntake::plate($row->license_plate)]];
    }

    /** One bounded-memory global scan for a page of authorized pending submissions. */
    public function warningsFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $wanted = [];
        $messages = [];
        $query = DB::table('customer_submissions')->whereIn('status', ['pending', 'approved'])
            ->select(['id', 'phone', 'phone_normalized', 'license_plate']);
        foreach ((clone $query)->whereIn('id', $ids)->get() as $target) {
            foreach ($this->identities($target) as $field => $values) {
                foreach ($values as $value) {
                    if ($value !== '') {
                        $wanted[$field][RegistrationPhone::key($field.'|'.$value)][$target->id] = true;
                    }
                }
            }
        }
        if ($wanted === []) {
            return [];
        }
        foreach ($query->lazyById(500) as $row) {
            foreach ($this->identities($row) as $field => $values) {
                foreach ($values as $value) {
                    foreach (array_keys($wanted[$field][RegistrationPhone::key($field.'|'.$value)] ?? []) as $id) {
                        if ((string) $id !== (string) $row->id) {
                            $messages[$id][$field] = self::MESSAGES[$field];
                        }
                    }
                }
            }
        }

        return $messages;
    }

    public function scan(?Connection $connection = null): array
    {
        $connection ??= DB::connection();
        $groups = ['phone' => [], 'license_plate' => []];
        $total = 0;
        foreach ($connection->table('customer_submissions')->whereIn('status', ['pending', 'approved'])
            ->select(['id', 'dealer_id', 'status', 'phone', 'phone_normalized', 'license_plate'])->lazyById(500) as $row) {
            $total++;
            $identities = $this->identities($row);
            foreach ($identities as $field => $values) {
                foreach ($values as $value) {
                    if ($value === '') {
                        continue;
                    }
                    $key = RegistrationPhone::key($field.'|'.$value);
                    $groups[$field][$key][$row->id] = ['approved' => $row->status === 'approved', 'dealer' => $row->dealer_id];
                }
            }
        }
        $counts = ['active_submissions' => $total, 'phone_groups' => 0, 'plate_groups' => 0,
            'affected_submissions' => 0, 'groups_with_approved' => 0, 'pending_only_groups' => 0, 'cross_dealer_groups' => 0];
        $affected = [];
        foreach ($groups as $field => $identities) {
            foreach ($identities as $records) {
                if (count($records) < 2) {
                    continue;
                }
                $counts[$field === 'phone' ? 'phone_groups' : 'plate_groups']++;
                $hasApproved = in_array(true, array_column($records, 'approved'), true);
                $counts[$hasApproved ? 'groups_with_approved' : 'pending_only_groups']++;
                $dealers = array_unique(array_filter(array_column($records, 'dealer'), fn ($id) => $id !== null));
                $counts['cross_dealer_groups'] += count($dealers) > 1 ? 1 : 0;
                foreach (array_keys($records) as $id) {
                    $affected[$id] = true;
                }
            }
        }
        $counts['affected_submissions'] = count($affected);

        return $counts;
    }
}

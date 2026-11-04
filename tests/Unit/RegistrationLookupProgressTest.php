<?php

namespace Tests\Unit;

use App\Services\RegistrationLookupProgress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RegistrationLookupProgressTest extends TestCase
{
    public static function evidenceCases(): array
    {
        return [
            'D11 unfinished draft' => [['kind' => 'draft'], ['current', 'pending', 'pending']],
            'D12 needs a request' => [['kind' => 'not_requested'], ['completed', 'current', 'pending']],
            'D13 request is not membership' => [['kind' => 'requested'], ['completed', 'completed', 'current']],
            'D14 evidenced member' => [['kind' => 'member'], ['completed', 'completed', 'completed']],
            'D7 no Group evidence' => [['kind' => 'supplement'], ['completed', 'pending', 'warning']],
            'D7 Group evidence' => [['kind' => 'supplement', 'group_confirmed' => true], ['completed', 'completed', 'warning']],
            'D15 no Group evidence' => [['kind' => 'blocked'], ['completed', 'pending', 'blocked']],
            'D15 Group evidence' => [['kind' => 'blocked', 'group_confirmed' => true], ['completed', 'completed', 'blocked']],
            'neutral ignores stray confirmation' => [['kind' => 'found', 'group_confirmed' => true], ['completed', 'pending', 'pending']],
            'nonboolean evidence is not confirmed' => [['kind' => 'supplement', 'group_confirmed' => 'true'], ['completed', 'pending', 'warning']],
        ];
    }

    #[DataProvider('evidenceCases')]
    public function test_progress_never_claims_more_than_resolver_evidence(array $dto, array $states): void
    {
        $original = $dto;
        $steps = RegistrationLookupProgress::steps($dto);
        $this->assertSame($states, array_column($steps, 'state'));
        $this->assertSame(['registration', 'group', 'membership'], array_column($steps, 'key'));
        $this->assertSame($original, $dto);
        foreach ($steps as $step) {
            $this->assertNotEmpty($step['title']);
            $this->assertNotEmpty($step['description']);
            $this->assertNotEmpty($step['status_label']);
            $this->assertNotEmpty($step['icon']);
            $this->assertStringNotContainsString('48 giờ', $step['description']);
        }
    }

    public function test_request_and_blocker_copy_cannot_be_mistaken_for_membership(): void
    {
        $request = RegistrationLookupProgress::steps(['kind' => 'requested']);
        $this->assertSame('Đã gửi yêu cầu vào group', $request[1]['description']);
        $this->assertSame('waiting', $request[2]['icon']);
        $this->assertStringNotContainsString('Đã vào group', $request[1]['description']);
        $blocked = RegistrationLookupProgress::steps(['kind' => 'blocked', 'reason' => 'Lý do từ backend.']);
        $this->assertSame('Lý do từ backend.', $blocked[2]['description']);
        $this->assertNotSame($blocked[1]['icon'], $blocked[2]['icon']);
    }

    public function test_no_progress_for_absent_registration_or_confirmation_only_pages(): void
    {
        foreach ([[], ['kind' => 'not_found'], ['kind' => 'supplemented'], ['kind' => 'rules'], ['kind' => 'invalid']] as $dto) {
            $this->assertSame([], RegistrationLookupProgress::steps($dto));
        }
    }
}

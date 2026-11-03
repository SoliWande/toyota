<?php

namespace Tests\Unit;

use App\Services\RegistrationPhone;
use App\Services\SubmissionIntake;
use PHPUnit\Framework\TestCase;

class SubmissionIntakeNormalizationTest extends TestCase
{
    public function test_plate_and_phone_format_variants_share_an_identity(): void
    {
        foreach (['51K-123.45', '51k 12345', " 51K\t-123.45\n", '51K12345'] as $plate) {
            $this->assertTrue(SubmissionIntake::plate($plate) === '51K12345');
        }
        foreach (['0990000007', '+84 (990) 000-007', '0084 990 000 007'] as $phone) {
            $this->assertTrue(RegistrationPhone::normalize($phone) === '0990000007');
        }
        $this->assertSame('', SubmissionIntake::plate(null));
    }
}

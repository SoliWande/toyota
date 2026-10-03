<?php

namespace Tests\Unit;

use App\Support\FacebookProfileUrl;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FacebookProfileUrlTest extends TestCase
{
    /** @dataProvider validProfiles */
    public function test_profile_normalization(string $url, string $expected): void
    {
        $this->assertSame($expected, FacebookProfileUrl::normalize($url));
    }

    public static function validProfiles(): array
    {
        return [
            ['http://m.facebook.com/Alice.Example/?ref=share#about', 'https://www.facebook.com/alice.example'],
            [' https://FACEBOOK.com/Alice.Example ', 'https://www.facebook.com/alice.example'],
            ['https://mbasic.facebook.com/profile.php?ref=share&id=00123456', 'https://www.facebook.com/profile.php?id=123456'],
            ['https://web.facebook.com/profile.php?id=123456&ref=share#about', 'https://www.facebook.com/profile.php?id=123456'],
        ];
    }

    /** @dataProvider invalidProfiles */
    public function test_invalid_or_ambiguous_urls_are_rejected(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        FacebookProfileUrl::normalize($url);
    }

    public static function invalidProfiles(): array
    {
        return array_map(fn ($url) => [$url], [
            'https://facebook.com.evil.test/alice',
            'https://evil.test/?url=https://facebook.com/alice',
            'javascript:alert(1)',
            'https://user:pass@facebook.com/alice',
            'https://facebook.com:443/alice',
            'https://www.facebook.com/groups/toyota',
            'https://www.facebook.com/share/abc',
            'https://www.facebook.com/alice/photos',
            'https://www.facebook.com/profile.php',
            'https://www.facebook.com/profile.php?id=0',
            'https://www.facebook.com/profile.php?id=123&id=456',
            'https://www.facebook.com/profile.php?id[]=123',
            'https://www.facebook.com/profile.php?id=notnumeric',
            'https://www.facebook.com/',
        ]);
    }
}

<?php

namespace App\Support;

use App\Services\FacebookUrlNormalizer;

final class FacebookProfileUrl
{
    public static function normalize(string $url): string
    {
        return (new FacebookUrlNormalizer)->normalize($url);
    }
}

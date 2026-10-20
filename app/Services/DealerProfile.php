<?php

namespace App\Services;

use App\Models\Dealer;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use LogicException;

class DealerProfile
{
    public function url(int|Dealer $dealer): string
    {
        // Use the configured canonical URL, never a caller-supplied Host or redirect.
        $slug = $dealer instanceof Dealer ? $dealer->slug : Dealer::whereKey($dealer)->value('slug');
        abort_unless($slug, 404);

        $base = rtrim(config('dealer.public_base_url') ?: config('app.url'), '/');
        $parts = parse_url($base);
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['https', 'http'], true) || empty($parts['host'])
            || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new LogicException('Configure an HTTP(S) public Dealer base URL without credentials, query or fragment.');
        }

        return $base.'/'.$slug.'/';
    }

    public function qr(int|Dealer $dealer): string
    {
        return (new Writer(new GDLibRenderer(600)))->writeString($this->url($dealer));
    }
}

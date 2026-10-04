<?php

namespace App\Services;

use InvalidArgumentException;

final class FacebookUrlNormalizer
{
    public function normalize(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);

        if (strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL) || $parts === false
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || ! in_array(strtolower($parts['host'] ?? ''), ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'mobile.facebook.com', 'mbasic.facebook.com', 'web.facebook.com'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            throw new InvalidArgumentException('Facebook profile URL is invalid.');
        }

        $path = trim($parts['path'] ?? '', '/');
        $query = $parts['query'] ?? '';

        if (strtolower($path) === 'profile.php') {
            $ids = [];
            foreach (explode('&', $query) as $parameter) {
                [$key, $value] = array_pad(explode('=', $parameter, 2), 2, '');
                if (strtolower(urldecode($key)) === 'id') {
                    $ids[] = urldecode($value);
                } elseif (str_starts_with(strtolower(urldecode($key)), 'id[')) {
                    throw new InvalidArgumentException('Facebook profile ID is ambiguous.');
                }
            }

            if (count($ids) !== 1 || ! preg_match('/^[0-9]{1,30}$/D', $ids[0])) {
                throw new InvalidArgumentException('Facebook profile requires one numeric ID.');
            }

            $id = ltrim($ids[0], '0');
            if ($id === '') {
                throw new InvalidArgumentException('Facebook profile ID is invalid.');
            }

            return 'https://www.facebook.com/profile.php?id='.$id;
        }

        $username = strtolower($path);
        $reserved = ['groups', 'pages', 'people', 'watch', 'reel', 'reels', 'photo', 'photos', 'videos', 'events', 'marketplace', 'gaming', 'share', 'sharer.php', 'login', 'login.php', 'l.php', 'plugins', 'settings', 'help', 'home.php', 'search', 'stories', 'profile.php'];

        if (! preg_match('/^[a-z0-9][a-z0-9.]{0,99}$/D', $username) || in_array($username, $reserved, true)) {
            throw new InvalidArgumentException('URL must point to a Facebook profile.');
        }

        return 'https://www.facebook.com/'.$username;
    }
}

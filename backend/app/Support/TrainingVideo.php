<?php

namespace App\Support;

class TrainingVideo
{
    /**
     * @return array{kind: string, src: ?string, href: ?string}
     */
    public static function present(?string $url): array
    {
        $url = trim((string) $url);
        if ($url === '' || ! self::isSafeHttps($url)) {
            return ['kind' => 'none', 'src' => null, 'href' => null];
        }

        if (preg_match('~(?:youtube\.com/watch\?v=|youtube\.com/embed/|youtu\.be/)([\w-]{6,})~', $url, $match)) {
            return [
                'kind' => 'iframe',
                'src' => 'https://www.youtube-nocookie.com/embed/'.$match[1],
                'href' => null,
            ];
        }

        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $match)) {
            return [
                'kind' => 'iframe',
                'src' => 'https://player.vimeo.com/video/'.$match[1],
                'href' => null,
            ];
        }

        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        if (preg_match('/\.(mp4|webm|ogg)$/', $path)) {
            return ['kind' => 'file', 'src' => $url, 'href' => null];
        }

        return ['kind' => 'link', 'src' => null, 'href' => $url];
    }

    public static function isSafeHttps(string $url): bool
    {
        if (strlen($url) > 500 || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        return $scheme === 'https' && $host !== '' && ! in_array($host, ['localhost', '127.0.0.1'], true);
    }
}

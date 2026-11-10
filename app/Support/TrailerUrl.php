<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Turns a stored movie trailer value into a link users open in a new tab.
 *
 * Trailers are never embedded: the Content Security Policy does not allow
 * third-party frames, so YouTube embed links become regular watch pages.
 */
final class TrailerUrl
{
    private const string YOUTUBE_ID = '[A-Za-z0-9_-]{6,20}';

    public static function watchUrl(mixed $trailer): ?string
    {
        if (! is_string($trailer)) {
            return null;
        }

        $trailer = trim($trailer);
        if ($trailer === '') {
            return null;
        }

        $youtubeId = self::youtubeId($trailer);
        if ($youtubeId !== null) {
            return 'https://www.youtube.com/watch?v='.$youtubeId;
        }

        if (preg_match('#^(?:https?://)?v\.traileraddict\.com/(\d+)$#i', $trailer, $matches) === 1) {
            return 'https://v.traileraddict.com/'.$matches[1];
        }

        if (str_starts_with(strtolower($trailer), 'http://')) {
            $trailer = 'https://'.substr($trailer, 7);
        }

        if (! str_starts_with(strtolower($trailer), 'https://')
            || preg_match('/[\s"\'<>`]/', $trailer) === 1
            || filter_var($trailer, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $trailer;
    }

    private static function youtubeId(string $trailer): ?string
    {
        $patterns = [
            '#^(?:(?:https?:)?//)?(?:www\.|m\.)?youtube(?:-nocookie)?\.com/(?:embed|v|shorts)/('.self::YOUTUBE_ID.')(?:[?&/\#].*)?$#i',
            '#^(?:(?:https?:)?//)?(?:www\.|m\.)?youtube\.com/watch\?(?:.*&)?v=('.self::YOUTUBE_ID.')(?:[&\#].*)?$#i',
            '#^(?:(?:https?:)?//)?youtu\.be/('.self::YOUTUBE_ID.')(?:[?\#].*)?$#i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $trailer, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }
}

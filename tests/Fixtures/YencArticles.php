<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/** Independent scalar fixture writer; never calls the production encoder. */
final class YencArticles
{
    public static function article(string $data, string $eol = "\r\n"): string
    {
        $lines = [];
        $line = '';
        $length = strlen($data);
        for ($offset = 0; $offset < $length; $offset++) {
            $encoded = (ord($data[$offset]) + 42) % 256;
            if (in_array($encoded, [0, 10, 13, 61], true)) {
                $line .= '='.chr(($encoded + 64) % 256);
            } else {
                $line .= chr($encoded);
            }
            if (strlen($line) >= 128) {
                $lines[] = $line;
                $line = '';
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        $size = strlen($data);

        return '=ybegin line=128 size='.$size.' name=fixture file.bin'.$eol
            .implode($eol, $lines).$eol.'=yend size='.$size.' crc32='.hash('crc32b', $data);
    }

    /**
     * One part of a multipart yEnc post: $data sits at 1-based offset $begin of a file of $fileSize bytes.
     */
    public static function part(string $data, string $name, int $fileSize, int $part, int $begin, int $total = 1): string
    {
        $encoded = '';
        foreach (str_split($data) as $byte) {
            $value = (ord($byte) + 42) % 256;
            $encoded .= in_array($value, [0, 10, 13, 61], true) ? '='.chr(($value + 64) % 256) : chr($value);
        }
        $end = $begin + strlen($data) - 1;

        return '=ybegin part='.$part.' total='.$total.' line=128 size='.$fileSize.' name='.$name."\r\n"
            .'=ypart begin='.$begin.' end='.$end."\r\n"
            .implode("\r\n", str_split($encoded, 128))."\r\n"
            .'=yend size='.strlen($data).' part='.$part.' pcrc32='.hash('crc32b', $data);
    }

    public static function bytes(): string
    {
        return implode('', array_map(chr(...), range(0, 255)));
    }

    public static function randomBytes(int $length): string
    {
        $data = '';
        for ($counter = 0; strlen($data) < $length; $counter++) {
            $data .= hash('sha256', 'yenc-fixture-'.$counter, true);
        }

        return substr($data, 0, $length);
    }
}

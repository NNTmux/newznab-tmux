<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Yenc Facade
 *
 * @method static string|false decode(string &$text, bool $ignore = false)
 * @method static string decodeIgnore(string &$text)
 * @method static bool enabled()
 * @method static bool isYencEncoded(string $text)
 * @method static string encode(string $data, string $filename, int $lineLength = 128, bool $includeCrc32 = true)
 * @method static array|null extractMetadata(string $text)
 *
 * @see \App\Services\YencService
 */
class Yenc extends Facade // @phpstan-ignore missingType.iterableValue
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\YencService::class;
    }
}

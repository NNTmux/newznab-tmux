<?php

declare(strict_types=1);

namespace App\Services\Yenc;

/**
 * A decoder that tolerates CR/LF line endings in the payload, allowing callers
 * to skip stripping them before decoding.
 */
interface RawPayloadDecoder extends PayloadDecoder
{
    /** Decode a payload that may contain \r and \n characters; line endings are ignored. */
    public function decodeRaw(string $payload): string;
}

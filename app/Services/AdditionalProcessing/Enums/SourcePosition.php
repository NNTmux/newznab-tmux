<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\Enums;

/**
 * Which part of a media file a downloaded copy holds.
 */
enum SourcePosition: string
{
    case Complete = 'complete';
    case Head = 'head';
    case Mid = 'mid';
}

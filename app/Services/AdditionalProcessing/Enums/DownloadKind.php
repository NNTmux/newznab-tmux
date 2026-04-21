<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\Enums;

enum DownloadKind: string
{
    case Sample = 'sample';
    case MediaInfo = 'media-info';
    case Audio = 'audio';
    case Jpg = 'jpg';
    case Compressed = 'compressed';
}

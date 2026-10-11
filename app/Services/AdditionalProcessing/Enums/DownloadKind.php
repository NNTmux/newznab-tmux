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
    case Par2Index = 'par2-index';
    case MediaSource = 'media-source';

    /**
     * Downloads made only to find a preview, counted against the media byte budget.
     */
    public function isPreviewSource(): bool
    {
        return $this === self::Par2Index || $this === self::MediaSource;
    }
}

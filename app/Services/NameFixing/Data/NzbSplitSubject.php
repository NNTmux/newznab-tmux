<?php

declare(strict_types=1);

namespace App\Services\NameFixing\Data;

/**
 * One parsed NZBSPLIT subject or file name, e.g.
 * `[80/98] "MLB__NZBSPLIT__<md5>__NZBSPLIT__MLB_RS_2026_..._720pEN60fps.7z.080" yEnc`.
 *
 * The uploader tag (`MLB`) is a category hint, the `[n/m]` counter is the number of files the
 * poster declared, and `extension` is the media or document type the archive volumes hold
 * (only known when the parsed value was an inner file name).
 */
final readonly class NzbSplitSubject
{
    private const string SPORT_TAG_PATTERN = '/^(?:MLB|NBA|WNBA|NHL|NFL|NCAA[FB]?|EPL|Fifa|LaLiga|UEFA|UCL|Bundesliga|SerieA|Ligue1|MLS|Soccer|Football|Tennis|Formula\d|F1|MotoGP|NASCAR|IndyCar|MMA|UFC|Boxing|Wrestling|WWE|AEW|Golf|MOTD|Cricket|Rugby|Darts|Snooker|Cycling|(?:Winter|Summer)?Olympics?\d{0,4})$/i';

    /**
     * @var list<string>
     */
    private const array DOCUMENT_EXTENSIONS = ['pdf', 'epub', 'mobi', 'azw3', 'cbr', 'cbz'];

    public function __construct(
        public ?string $tag,
        public string $hash,
        public string $title,
        public bool $isSample = false,
        public ?string $extension = null,
        public ?int $fileIndex = null,
        public ?int $declaredFiles = null,
    ) {}

    public function hasSportTag(): bool
    {
        return self::isSportTag($this->tag);
    }

    public static function isSportTag(?string $tag): bool
    {
        return $tag !== null && $tag !== '' && preg_match(self::SPORT_TAG_PATTERN, $tag) === 1;
    }

    public function isDocument(): bool
    {
        return self::isDocumentExtension($this->extension);
    }

    public static function isDocumentExtension(?string $extension): bool
    {
        return $extension !== null && in_array(strtolower($extension), self::DOCUMENT_EXTENSIONS, true);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Categorization;

use App\Services\NameFixing\Data\NzbSplitSubject;

/**
 * Value object containing release information for categorization.
 */
class ReleaseContext
{
    private const string STANDALONE_SEASON_TOKEN_REGEX = '/(?:^|[._ -])S\d{1,3}(?=$|[._ -])/i';

    private const string BOOK_SIGNAL_REGEX = '/\b(?:MAGAZINE|e-?Books?|EPUB|MOBI|AZW3?|PDF|Comics?|CB[RZ]|Audiobooks?)\b/i';

    /**
     * @param  string|null  $sourceTag  Uploader tag of an NZBSPLIT subject (`MLB`, `Fifa`, ...)
     * @param  string|null  $contentExtension  Extension of the file inside the archive, when known
     */
    public function __construct(
        public readonly string $releaseName,
        public readonly int|string $groupId,
        public readonly string $groupName = '',
        public readonly string $poster = '',
        public readonly bool $categorizeForeign = true,
        public readonly bool $catWebDL = true,
        public readonly ?string $sourceTag = null,
        public readonly ?string $contentExtension = null,
    ) {}

    public function hasSportSourceTag(): bool
    {
        return NzbSplitSubject::isSportTag($this->sourceTag);
    }

    /**
     * The archive holds a document (PDF, ebook, comic) rather than video.
     */
    public function hasDocumentContent(): bool
    {
        return NzbSplitSubject::isDocumentExtension($this->contentExtension);
    }

    /**
     * Book and magazine markers that must win over a sport hint (`World.Soccer.June.2026.MAGAZINE`).
     */
    public function hasBookSignals(): bool
    {
        return $this->hasDocumentContent() || preg_match(self::BOOK_SIGNAL_REGEX, $this->releaseName) === 1;
    }

    /**
     * Get the release name in lowercase for case-insensitive matching.
     */
    public function getLowerReleaseName(): string
    {
        return strtolower($this->releaseName);
    }

    /**
     * Check if the release name matches a pattern.
     */
    public function matchesPattern(string $pattern): bool
    {
        return (bool) preg_match($pattern, $this->releaseName);
    }

    /**
     * Check if the release name contains a substring (case-insensitive).
     */
    public function containsString(string $needle): bool
    {
        return stripos($this->releaseName, $needle) !== false;
    }

    /**
     * Check if the group name matches a pattern.
     */
    public function groupMatchesPattern(string $pattern): bool
    {
        return (bool) preg_match($pattern, $this->groupName);
    }

    /**
     * Check whether the release name contains a delimiter-bounded season-pack token.
     */
    public function hasStandaloneSeasonToken(): bool
    {
        return preg_match(self::STANDALONE_SEASON_TOKEN_REGEX, $this->releaseName) === 1;
    }

    public function hasOnlyFansMarker(): bool
    {
        return preg_match('/\bOnly[-_ ]?Fans\b|^OF\./i', $this->releaseName) === 1;
    }

    /**
     * Check if this release has adult/XXX markers.
     */
    public function hasAdultMarkers(): bool
    {
        if ($this->hasOnlyFansMarker()) {
            return true;
        }

        // Check for explicit XXX markers and common adult keywords/studios
        if (preg_match('/\b(XXX|Porn|Anal|Brazzers|BangBros|Bangbros|NaughtyAmerica|RealityKings|Tushy|Vixen|Blacked|OnlyFans|MetArt|JoyMii|Creampie|MP4-XXX|PureTaboo|Lady[._ -]?Lyne|TeamSkeet|GirlsWay|EvilAngel|Kink|FakeHub|FakeTaxi|SexArt|Nubiles|Defloration|Deeper|Bellesa|Twistys|Mofos|MissaX|LegalPorno|AnalVids|JAV|Hentai|RoccoSiffredi|DivineBitches|Device[._ -]?Bondage|Hogtied|Wired[._ -]?Pussy|Fucking[._ -]?Machines|Ultimate[._ -]?Surrender|Public[._ -]?Disgrace|Sex[._ -]?And[._ -]?Submission|Bound[._ -]?Gang[._ -]?Bangs|Electro[._ -]?Sluts|Whipped[._ -]?Ass|TS[._ -]?Seduction|Infernal[._ -]?Restraints|Sexually[._ -]?Broken)\b/i', $this->releaseName)) {
            return true;
        }

        // Check for adult keywords combined with resolution (likely adult clip)
        if (preg_match('/\b(Fuck|Fucked|Fucking|Cock|Dick|Pussy|Cum|Cumshot|Blowjob|Handjob|MILF|Teen|Lesbian|Threesome|Gangbang|Hardcore|Interracial)\b/i', $this->releaseName) &&
            preg_match('/\b(720p|1080p|2160p|4k|mp4)\b/i', $this->releaseName)) {
            return true;
        }

        return false;
    }
}

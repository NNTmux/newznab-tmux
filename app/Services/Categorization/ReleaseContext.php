<?php

declare(strict_types=1);

namespace App\Services\Categorization;

/**
 * Value object containing release information for categorization.
 */
class ReleaseContext
{
    public function __construct(
        public readonly string $releaseName,
        public readonly int|string $groupId,
        public readonly string $groupName = '',
        public readonly string $poster = '',
        public readonly bool $categorizeForeign = true,
        public readonly bool $catWebDL = true,
    ) {}

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
     * Check if this release has adult/XXX markers.
     */
    /**
     * Check if this release has adult/XXX markers.
     */
    public function hasAdultMarkers(): bool
    {
        // Check for explicit XXX markers and common adult keywords/studios
        if (preg_match('/\b(XXX|Porn|Anal|Brazzers|BangBros|Bangbros|NaughtyAmerica|RealityKings|Tushy|Vixen|Blacked|OnlyFans|MetArt|JoyMii|Creampie|MP4-XXX|PureTaboo|LadyLyne|TeamSkeet|GirlsWay|EvilAngel|Kink|FakeHub|FakeTaxi|SexArt|Nubiles|Defloration|Deeper|Bellesa|Twistys|Mofos|MissaX|LegalPorno|AnalVids|JAV|Hentai|RoccoSiffredi|DivineBitches|Device[._ -]?Bondage|Hogtied|Wired[._ -]?Pussy|Fucking[._ -]?Machines|Ultimate[._ -]?Surrender|Public[._ -]?Disgrace|Sex[._ -]?And[._ -]?Submission|Bound[._ -]?Gang[._ -]?Bangs|Electro[._ -]?Sluts|Whipped[._ -]?Ass|TS[._ -]?Seduction|Infernal[._ -]?Restraints|Sexually[._ -]?Broken)\b/i', $this->releaseName)) {
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

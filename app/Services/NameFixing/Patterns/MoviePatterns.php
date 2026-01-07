<?php

declare(strict_types=1);

namespace App\Services\NameFixing\Patterns;

/**
 * Regex patterns for Movie release name matching.
 *
 * Organized by pattern complexity and modern release types.
 */
final class MoviePatterns
{
    // ========================================================================
    // 4K/UHD PATTERNS (Modern releases - highest priority)
    // ========================================================================

    /**
     * 4K/UHD releases with HDR.
     * Example: Movie.Name.2023.2160p.HDR.BluRay.HEVC-GROUP
     */
    public const UHD_HDR = '/\w[\-\w.\',;& ]+((19|20)\d\d)[._ -](2160p|4K|UHD)[._ -](HDR10\+?|DV|Dolby[._ -]?Vision|HLG)?[._ -]?(REMUX|BluRay|WEB-?DL|WEB-?RIP|UHD[._ -]?BluRay)[._ -](HEVC|x265|H\.?265|AV1)[._ -]?(Atmos|DTS[._ -]?(HD)?|TrueHD)?[\-\w.\',;& ]+\w/i';

    /**
     * 4K BluRay REMUX.
     * Example: Movie.Name.2023.2160p.REMUX.HEVC-GROUP
     */
    public const UHD_REMUX = '/\w[\-\w.\',;& ]+((19|20)\d\d)[._ -](2160p|4K)[._ -](REMUX|Complete[._ -]?UHD)[._ -](HEVC|x265|H\.?265)[\-\w.\',;& ]+\w/i';

    /**
     * 4K Streaming service releases.
     * Example: Movie.Name.2023.2160p.AMZN.WEB-DL.HDR.HEVC-GROUP
     */
    public const STREAMING_4K = '/\w[\-\w.\',;& ]+((19|20)\d\d)[._ -](2160p|4K)[._ -](AMZN|ATVP|DSNP|HMAX|HULU|iT|NF|PMTP|PCOK|ROKU|STAN|VUDU)[._ -](WEB-?DL|WEB-?RIP)[._ -](HDR10\+?|DV)?[._ -]?(HEVC|x265|H\.?265)[\-\w.\',;& ]+\w/i';

    // ========================================================================
    // HD STREAMING PATTERNS
    // ========================================================================

    /**
     * HD Streaming service releases (1080p/720p).
     * Example: Movie.Name.2023.1080p.NF.WEB-DL.x264-GROUP
     */
    public const STREAMING_HD = '/\w[\-\w.\',;& ]+((19|20)\d\d)[._ -](1080p|720p)[._ -](AMZN|ATVP|DSNP|HMAX|HULU|iT|NF|PMTP|PCOK|ROKU|STAN|VUDU)[._ -](WEB-?DL|WEB-?RIP)[._ -](x264|x265|H\.?264|H\.?265|HEVC)[\-\w.\',;& ]+\w/i';

    // ========================================================================
    // STANDARD MOVIE PATTERNS
    // ========================================================================

    /**
     * Year + resolution + video codec.
     * Example: Movie.Name.2023.1080p.x264-GROUP
     */
    public const YEAR_RES_VCODEC = '/\w[\-\w.\',;& ]+((19|20)\d\d)[\-\w.\',;& ]+(480|720|1080|2160)[ip]?[._ -](DivX|[HX][._ -]?264|[HX][._ -]?265|HEVC|MPEG2|XviD(HD)?|WMV|AV1)[\-\w.\',;& ]+\w/i';

    /**
     * Year + source + video codec + resolution.
     * Example: Movie.Name.2023.BluRay.x264.1080p-GROUP
     */
    public const YEAR_SOURCE_VCODEC_RES = '/\w[\-\w.\',;& ]+((19|20)\d\d)[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-?DL|WEB-?RIP|REMUX)[._ -](DivX|[HX][._ -]?264|[HX][._ -]?265|HEVC|MPEG2|XviD(HD)?|WMV|AV1)[._ -](480|720|1080|2160)[ip]?[\-\w.\',;& ]+\w/i';

    /**
     * Year + source + video codec + audio codec.
     * Example: Movie.Name.2023.BluRay.x264.DTS-GROUP
     */
    public const YEAR_SOURCE_VCODEC_ACODEC = '/\w[\-\w.\',;& ]+((19|20)\d\d)[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-?DL|WEB-?RIP|REMUX)[._ -](DivX|[HX][._ -]?264|[HX][._ -]?265|HEVC|MPEG2|XviD(HD)?|WMV|AV1)[._ -](AAC( LC)?|AC-?3|DD5([._ -]1)?|(A_)?DTS-?(HD)?(-?MA)?|Dolby( ?TrueHD)?|MP3|TrueHD|Atmos|EAC3|FLAC)[\-\w.\',;& ]+\w/i';

    /**
     * Resolution + source + audio codec + video codec.
     * Example: Movie.Name.1080p.BluRay.DTS.x264-GROUP
     */
    public const RES_SOURCE_ACODEC_VCODEC = '/\w[\-\w.\',;& ]+(480|720|1080|2160)[ip]?[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-?DL|WEB-?RIP|REMUX)[._ -](AAC( LC)?|AC-?3|DD5([._ -]1)?|(A_)?DTS-?(HD)?(-?MA)?|Dolby( ?TrueHD)?|MP3|TrueHD|Atmos|EAC3|FLAC)[._ -](DivX|[HX][._ -]?264|[HX][._ -]?265|HEVC|MPEG2|XviD(HD)?|WMV|AV1)[\-\w.\',;& ]+\w/i';

    /**
     * Resolution + audio codec + source + year.
     * Example: Movie.Name.1080p.DTS.BluRay.2023-GROUP
     */
    public const RES_ACODEC_SOURCE_YEAR = '/\w[\-\w.\',;& ]+(480|720|1080|2160)[ip]?[._ -](AAC( LC)?|AC-?3|DD5([._ -]1)?|(A_)?DTS-?(HD)?(-?MA)?|Dolby( ?TrueHD)?|MP3|TrueHD|Atmos|EAC3|FLAC)[\-\w.\',;& ]+(BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-?DL|WEB-?RIP|REMUX)[._ -]((19|20)\d\d)[\-\w.\',;& ]+\w/i';

    // ========================================================================
    // LANGUAGE-SPECIFIC PATTERNS
    // ========================================================================

    /**
     * Multi-language releases.
     * Example: Movie.Name.FRENCH.2023.DTS.BluRay-GROUP
     */
    public const MULTI_LANGUAGE = '/\w[\-\w.\',;& ]+(Brazilian|Chinese|Croatian|Danish|Deutsch|Dutch|Estonian|English|Finnish|Flemish|Francais|French|German|Greek|Hebrew|Icelandic|Italian|Japenese|Japan|Japanese|Korean|Latin|Nordic|Norwegian|Polish|Portuguese|Russian|Serbian|Slovenian|Swedish|Spanisch|Spanish|Thai|Turkish|MULTi)[._ -]((19|20)\d\d)[._ -](AAC( LC)?|AC-?3|DD5([._ -]1)?|(A_)?DTS-?(HD)?(-?MA)?|Dolby( ?TrueHD)?|MP3|TrueHD|Atmos|EAC3|FLAC)[._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-?DL|WEB-?RIP|REMUX)[\-\w.\',;& ]+\w/i';

    // ========================================================================
    // FALLBACK PATTERNS
    // ========================================================================

    /**
     * Generic movie with year, resolution, and source.
     * Example: Movie.Name.2023.1080p.BluRay-GROUP
     */
    public const GENERIC = '/\w[\-\w.\',;& ]+((19|20)\d\d)[\-\w.\',;& ]+(480|720|1080|2160)[ip]?[\-\w.\',;& ]+(BluRay|BDRip|DVDRip|HDTV|WEB-?DL|WEB-?RIP)[\-\w.\',;& ]+\w/i';

    // ========================================================================
    // PATTERN COLLECTIONS
    // ========================================================================

    /**
     * Get all patterns in priority order.
     */
    public static function getAllPatterns(): array
    {
        return [
            'UHD_HDR' => self::UHD_HDR,
            'UHD_REMUX' => self::UHD_REMUX,
            'STREAMING_4K' => self::STREAMING_4K,
            'STREAMING_HD' => self::STREAMING_HD,
            'YEAR_RES_VCODEC' => self::YEAR_RES_VCODEC,
            'YEAR_SOURCE_VCODEC_RES' => self::YEAR_SOURCE_VCODEC_RES,
            'YEAR_SOURCE_VCODEC_ACODEC' => self::YEAR_SOURCE_VCODEC_ACODEC,
            'RES_SOURCE_ACODEC_VCODEC' => self::RES_SOURCE_ACODEC_VCODEC,
            'RES_ACODEC_SOURCE_YEAR' => self::RES_ACODEC_SOURCE_YEAR,
            'MULTI_LANGUAGE' => self::MULTI_LANGUAGE,
            'GENERIC' => self::GENERIC,
        ];
    }

    /**
     * Get 4K/UHD patterns only.
     */
    public static function get4KPatterns(): array
    {
        return [
            'UHD_HDR' => self::UHD_HDR,
            'UHD_REMUX' => self::UHD_REMUX,
            'STREAMING_4K' => self::STREAMING_4K,
        ];
    }
}

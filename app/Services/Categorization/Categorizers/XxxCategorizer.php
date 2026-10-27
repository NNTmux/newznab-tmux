<?php

declare(strict_types=1);

namespace App\Services\Categorization\Categorizers;

use App\Models\Category;
use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\ReleaseContext;

/**
 * Categorizer for Adult/XXX content.
 */
class XxxCategorizer extends AbstractCategorizer
{
    protected int $priority = 10; // High priority - should run early

    // Known adult studios/sites - comprehensive list
    protected const KNOWN_STUDIOS = 'Brazzers|NaughtyAmerica|RealityKings|Bangbros|BangBros18|TeenFidelity|PornPros|SexArt|WowGirls|Vixen|Blacked|Tushy|Deeper|Bellesa|Defloration|MetArt|MetArtX|TheLifeErotic|VivThomas|JoyMii|Nubiles|NubileFilms|Anilos|FamilyStrokes|X-Art|Babes|Twistys|WetAndPuffy|WowPorn|MomsTeachSex|Mofos|BangBus|Passion-HD|EvilAngel|DorcelClub|Private|Hustler|CherryPimps|PureTaboo|Lady[._ -]?Lyne|TeamSkeet|GirlsWay|SweetSinner|NewSensations|Digital[._ -]?Playground|Wicked|Penthouse|Playboy|Kink|HardX|ArchAngel|JulesJordan|ManuelFerrara|LesbianX|AllAnal|DarkX|Elegant[._ -]?Angel|ZeroTolerance|Score|PornFidelity|Kelly[._ -]?Madison|DDF[._ -]?Network|21Sextury|21Naturals|Colette|SexMex|Bang|SpankBang|PornWorld|LegalPorno|AnalVids|GonzoXXX|RoccoSiffredi|Fake[._ -]?Hub|FakeAgent|FakeTaxi|FakeHostel|PublicAgent|StrandedTeens|Property[._ -]?Sex|Dane[._ -]?Jones|Lets[._ -]?Doe[._ -]?It|Office[._ -]?Obsession|SexyHub|Massage[._ -]?Rooms|Fitness[._ -]?Rooms|Female[._ -]?Agent|MissaX|All[._ -]?Girl[._ -]?Massage|Fantasy[._ -]?Massage|Nurumassage|Soapymassage|Reality[._ -]?Junkies|Perv[._ -]?Mom|Bad[._ -]?Milfs|Milf[._ -]?Body|Step[._ -]?Siblings|Sis[._ -]?Loves[._ -]?Me|Brother[._ -]??Crush|Dad[._ -]?Crush|Mom[._ -]?Knows[._ -]?Best|Bratty[._ -]?Sis|My[._ -]?Family[._ -]?Pies|Family[._ -]?Therapy|Nubiles[._ -]?Porn|Step[._ -]?Fantasy|Caught[._ -]?Fapping|She[._ -]?Will[._ -]?Cheat|Dirty[._ -]?Wives[._ -]?Club|Big[._ -]?Tits[._ -]?Round[._ -]?Asses|Ass[._ -]?Parade|Monsters[._ -]?Of[._ -]?Cock|Brown[._ -]?Bunnies|Teens[._ -]?Love[._ -]?Huge[._ -]?Cocks|Ass[._ -]?Masterpiece|Bang[._ -]?Casting|Holed|Tiny4K|Lubed|POVD|Exotic4K|CastingCouch[._ -]?X|Casting[._ -]?Couch|Creampie[._ -]?Angels|Digital[._ -]?Desire|Femjoy|Hegre|Joymii|Met[._ -]?Art|MPL[._ -]?Studios|Rylsky[._ -]?Art|Showy[._ -]?Beauty|Stunning18|Photodromm|Watch4Beauty|Wow[._ -]?Girls|Yonitale|Mommys[._ -]?Boy|AllOver30|MyFirst|10musume|Caribbeancom|Heyzo|Pacopacomama|1Pondo|TokyoHot|Mommy[._ -]?Blows[._ -]?Best|Milfs[._ -]?Like[._ -]?It[._ -]?Big|Mommy[._ -]?Got[._ -]?Boobs|My[._ -]?Friends[._ -]?Hot[._ -]?Mom|Seduced[._ -]?By[._ -]?A[._ -]?Cougar|Hot[._ -]?Mom[._ -]?Next[._ -]?Door|Jacquie[._ -]?Et[._ -]?Michel(?:[._ -]?(?:TV|Elite))?';

    // Adult keywords
    protected const ADULT_KEYWORDS = 'Anal|Ass|BBW|BDSM|Blow|Boob|Bukkake|Casting|Couch|Cock|Compilation|Creampie|Cum|Dick|Dildo|Facial|Fetish|Fuck|Gang|Hardcore|Homemade|Horny|Interracial|Lesbian|MILF|Masturbat|Nympho|Oral|Orgasm|Penetrat|Pornstar|POV|Pussy|Riding|Seduct|Sex|Shaved|Slut|Squirt|Suck|Swallow|Threesome|Tits|Titty|Toy|Virgin|Whore';

    // VR sites
    protected const VR_SITES = 'SexBabesVR|LittleCapriceVR|VRoomed|VRMagic|TonightsGirlfriend|NaughtyAmericaVR|BaDoinkVR|WankzVR|VRBangers|StripzVR|RealJamVR|TmwVRnet|MilfVR|KinkVR|CzechVR(?:Fetish)?|HoloGirlsVR|WetVR|XSinsVR|VRCosplayX|BIBIVR|SLR|SexLikeReal|CutiesVR|18VR|PornCornVR|DarkRoomVR|JackAndJillVR|PasstroughVR|JUVR|KIWVR|KAVR|NKKVR|VRSPy|BadoinkVR|SinsVR|FuckPassVR|CRVR|CatalinaCruzVR|StockingsVR|VirtualRealPorn|VRHush|VRConk|SwallowBay';

    // Generic VR adult site pattern (e.g. StockingsVR.com, SomeSiteVR.net)
    protected const GENERIC_VR_SITE = '\b[A-Z][A-Za-z0-9]{2,}VR(?:\.com|\.net|\.tv)?\b';

    // VR headset / platform tokens
    protected const VR_DEVICES = 'GearVR|Oculus|Quest[123]?|PSVR|Vive|Index|Pimax';

    // JAV studios and sites (also listed in KNOWN_STUDIOS)
    protected const JAV_STUDIOS = '10musume|Caribbeancom(?:pr)?|Heyzo|Pacopacomama|1Pondo|Tokyo-?Hot|Gachinco|Kin8tengoku|HeyDouga|S-Cute|Muramura|JavHD|Mesubuta|Hitozuma[._ -]?Giri';

    // Common JAV label prefixes, matched as LABEL-123 product codes
    protected const JAV_LABELS = 'ABP|ABW|ADN|ATID|BBAN|CAWD|DASD|DVDMS|EBOD|FSDSS|GVH|HMN|HND|IPX|IPZ|JUFE|JUQ|JUY|KAWD|MEYD|MIAA|MIDE|MIDV|MIGD|MIMK|MKMP|MXGS|NHDTB|PPPD|PRED|RBD|REBD|SDDE|SDMU|SNIS|SONE|SSIS|SSNI|STARS|WAAA|WANZ|XVSR|YMDD';

    // Video containers and codecs; their presence means a release is not an image collection
    protected const VIDEO_MARKERS = 'mp4|mkv|wmv|avi|mov|webm|m4v|x264|x265|h\.?264|h\.?265|hevc|xvid|divx';

    public function getName(): string
    {
        return 'XXX';
    }

    public function categorize(ReleaseContext $context): CategorizationResult
    {
        $name = $context->releaseName;
        $javResult = $this->checkJav($name);

        if ($javResult === null && ! $context->hasOnlyFansMarker() && ! $this->looksLikeXxx($name)) {
            return $this->noMatch();
        }

        return $this->checkOnlyFans($context)
            ?? $javResult
            ?? $this->checkImageset($name)
            ?? $this->checkVR($name)
            ?? $this->checkDVD($name)
            ?? $this->checkPack($name)
            ?? $this->checkResolution($name, $context->poster)
            ?? $this->matched(Category::XXX_OTHER, 0.6, 'other');
    }

    /**
     * Check if release name looks like adult content.
     */
    protected function looksLikeXxx(string $name): bool
    {
        // Check for XXX marker
        if (preg_match('/\bXXX\b/i', $name)) {
            return true;
        }

        // Check for known studios/sites
        if (preg_match('/\b('.self::KNOWN_STUDIOS.')\b/i', $name, $matches)) {
            $matchedStudio = $matches[1];

            // If the studio name appears at the start, check if it's actually a movie release pattern
            // Movie pattern: Title.Year.Resolution.Source (e.g., Wicked.2024.1080p.WEB-DL)
            // Adult pattern: Studio.Date.Performer (e.g., Wicked.24.01.15.Performer.Name)
            if (preg_match('/^'.preg_quote($matchedStudio, '/').'[.\-_ ]/i', $name)) {
                // Check if this looks like a movie release: Studio.Year.Resolution or Studio.Title.Year
                // Movie releases typically have: 4-digit year followed by resolution/source markers
                if (preg_match('/^'.preg_quote($matchedStudio, '/').'[.\-_ ](?:[A-Za-z]+[.\-_ ])*(?:19|20)\d{2}[.\-_ ](?:720p|1080p|2160p|4K|UHD)/i', $name) &&
                    preg_match('/\b(WEB-?DL|WEBRip|BluRay|BDRip|HDRip|HDTV|DVDRip|Remux|PROPER|REPACK|HC|KORSUB)\b/i', $name) &&
                    ! preg_match('/\b('.self::ADULT_KEYWORDS.')\b/i', $name)) {
                    // This looks like a movie release (Title.Year.Resolution.Source), not adult content
                    return false;
                }

                // Adult studio releases typically use date patterns: Studio.YY.MM.DD or Studio.YYYY.MM.DD
                if (preg_match('/^'.preg_quote($matchedStudio, '/').'[.\-_ ](19|20)?\d{2}[.\-_ ]\d{2}[.\-_ ]\d{2}[.\-_ ]/i', $name)) {
                    return true;
                }
            }

            return true;
        }

        // Check for known VR sites
        if (preg_match('/\b('.self::VR_SITES.')\b/i', $name)) {
            return true;
        }

        // Generic VR adult site (e.g. StockingsVR.com)
        if (preg_match('/'.self::GENERIC_VR_SITE.'/i', $name)) {
            return true;
        }

        // VR device combined with HD/UHD video markers
        if (preg_match('/\b(?:'.self::VR_DEVICES.')\b/i', $name) &&
            preg_match('/\b(720p|1080p|2160p|4K|VR180|VR360)\b/i', $name)) {
            return true;
        }

        // Check for adult content indicators combined with video markers
        if (preg_match('/\b('.self::ADULT_KEYWORDS.')\b/i', $name) &&
            preg_match('/\b(720p|1080p|2160p|4k|mp4|mkv|avi|wmv)\b/i', $name)) {
            return true;
        }

        // Check for JAV/AV marker (common in Japanese adult releases)
        if (preg_match('/\b(AV|JAV)\b/', $name) && preg_match('/\b('.self::KNOWN_STUDIOS.')\b/i', $name)) {
            return true;
        }

        // Site with date pattern: sitename.YYYY.MM.DD or sitename.YY.MM.DD
        // This pattern is very common for adult sites but rare for regular content
        if (preg_match('/^[A-Za-z]+[.\-_ ](19|20)?\d{2}[.\-_ ]\d{2}[.\-_ ]\d{2}[.\-_ ][A-Za-z]/i', $name)) {
            // Check it's not a TV daily show by checking for adult keywords or specific patterns
            if (preg_match('/\b('.self::ADULT_KEYWORDS.')\b/i', $name)) {
                return true;
            }
            // Check for performer name patterns (firstname.lastname) after the date
            if (preg_match('/\d{2}[.\-_ ]([a-z]+)[.\-_ ]([a-z]+)[.\-_ ]/i', $name)) {
                // Has a "firstname.lastname" pattern after date - likely adult
                // But exclude obvious TV patterns
                if (! preg_match('/\b(S\d{1,2}E\d{1,2}|Episode|Season|HDTV|PDTV)\b/i', $name)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function checkOnlyFans(ReleaseContext $context): ?CategorizationResult
    {
        $name = $context->releaseName;

        // Skip photo packs unless there's a video hint
        if (preg_match('/\b(photo(set)?|image(set)?|pics?|wallpapers?|collection|pack)\b/i', $name) &&
            ! preg_match('/\b(mp4|mkv|mov|wmv|avi|webm|h\.?264|x264|h\.?265|x265)\b/i', $name)) {
            return null;
        }

        if ($context->hasOnlyFansMarker()) {
            return $this->matched(Category::XXX_ONLYFANS, 0.95, 'onlyfans');
        }

        return null;
    }

    protected function checkVR(string $name): ?CategorizationResult
    {
        $hasKnownVRSite = (bool) preg_match('/\b('.self::VR_SITES.')\b/i', $name);
        $hasGenericVRSite = (bool) preg_match('/'.self::GENERIC_VR_SITE.'/i', $name);
        $hasVRSite = $hasKnownVRSite || $hasGenericVRSite;
        $hasVRDevice = (bool) preg_match('/\b(?:'.self::VR_DEVICES.')\b/i', $name);
        $hasVR180360 = (bool) preg_match('/\bVR(?:180|360)\b/i', $name);

        if (! $hasVRSite && ! $hasVRDevice && ! $hasVR180360 &&
            stripos($name, 'vr') === false && stripos($name, 'oculus') === false && stripos($name, 'quest') === false) {
            return null;
        }

        // Require either a VR site token, explicit VR180/VR360, or VR device
        if (! $hasVR180360 && ! $hasVRSite && ! $hasVRDevice) {
            return null;
        }

        // VR pattern matching - includes VR devices and generic VR sites
        $vrPattern = '/\b('.self::VR_SITES.')\b|'.self::GENERIC_VR_SITE.'|\bVR(?:180|360)\b|\b(?:5K|6K|7K|8K)\b.*\bVR\b|\b(?:'.self::VR_DEVICES.')\b/i';

        if (preg_match($vrPattern, $name)) {
            // Known VR sites are definitively adult content
            if ($hasKnownVRSite) {
                return $this->matched(Category::XXX_VR, 0.95, 'vr_site');
            }

            // Generic VR site pattern (e.g. StockingsVR.com)
            if ($hasGenericVRSite) {
                return $this->matched(Category::XXX_VR, 0.9, 'vr_site_generic');
            }

            // VR device with adult studio, keywords, or XXX marker
            if ($hasVRDevice && (
                preg_match('/\bXXX\b/i', $name) ||
                preg_match('/\b('.self::ADULT_KEYWORDS.')\b/i', $name) ||
                preg_match('/\b('.self::KNOWN_STUDIOS.')\b/i', $name)
            )) {
                return $this->matched(Category::XXX_VR, 0.9, 'vr_device');
            }

            // VR180/VR360 with adult markers
            if ($hasVR180360 && (
                preg_match('/\bXXX\b/i', $name) ||
                preg_match('/\b('.self::ADULT_KEYWORDS.')\b/i', $name) ||
                preg_match('/\b('.self::VR_SITES.')\b/i', $name)
            )) {
                return $this->matched(Category::XXX_VR, 0.9, 'vr_180_360');
            }
        }

        return null;
    }

    /**
     * JAV needs positive identification: a JAV studio, an FC2-PPV id, a known label code, or an explicit JAV token.
     */
    protected function checkJav(string $name): ?CategorizationResult
    {
        if (preg_match('/\b(?:'.self::JAV_STUDIOS.')\b/i', $name)) {
            return $this->matched(Category::XXX_JAV, 0.95, 'jav_studio');
        }

        if (preg_match('/\bFC2[._ -]?PPV[._ -]?\d{5,8}\b/i', $name)) {
            return $this->matched(Category::XXX_JAV, 0.95, 'jav_fc2');
        }

        if (preg_match('/\b(?:'.self::JAV_LABELS.')-\d{3,5}\b/i', $name)) {
            return $this->matched(Category::XXX_JAV, 0.95, 'jav_code');
        }

        if (preg_match('/\bJAV\b/i', $name)) {
            return $this->matched(Category::XXX_JAV, 0.9, 'jav');
        }

        return null;
    }

    protected function checkImageset(string $name): ?CategorizationResult
    {
        if (preg_match('/\b(?:'.self::VIDEO_MARKERS.')\b/i', $name)) {
            return null;
        }

        if (preg_match('/(?:IMAGE|PICTURE|PHOTO)[._ -]?SETS?|ABPEA|\b(?:Pics|Photos|Images|JPE?Gs?|Wallpapers?)\b/i', $name)) {
            return $this->matched(Category::XXX_IMAGESET, 0.9, 'imageset');
        }

        return null;
    }

    /**
     * Full DVD images or structures. DVDRip is a re-encode, so it counts as SD video.
     */
    protected function checkDVD(string $name): ?CategorizationResult
    {
        if (preg_match('/\bDVD-?R(?:[._ -]|$)|\bDVD[59]\b|VIDEO_TS|\bDVD[._ -]?ISO\b|\bDVD\b.*\b(?:ISO|IMG|NTSC|PAL)\b|\bFull[._ -]DVD\b/i', $name)) {
            return $this->matched(Category::XXX_DVD, 0.85, 'dvd');
        }

        return null;
    }

    /**
     * Video collections.
     */
    protected function checkPack(string $name): ?CategorizationResult
    {
        if (preg_match('/[._ -]PACK(?:[._ -]|$)|\bMega[._ -]?Pack\b|\bSite[._ -]?Rip\b|^(?:Complete|Collection|Anthology)\b|\b\d+[._ -]?(?:clips|videos|scenes)\b/i', $name)) {
            return $this->matched(Category::XXX_PACK, 0.85, 'pack');
        }

        return null;
    }

    /**
     * Sort video by resolution: UHD at 2160p or above, HD from 720p, SD below that.
     * When several resolutions are present the highest one wins.
     */
    protected function checkResolution(string $name, string $poster): ?CategorizationResult
    {
        if (preg_match('/(?<!\d)(?:2160|4320)[pi](?![a-z])|\b(?:4K|8K|UHD|Ultra[._ -]?HD)\b/i', $name)) {
            return $this->matched(Category::XXX_UHD, 0.9, 'uhd');
        }

        if (preg_match('/(?<!\d)(?:720|1080|1440)[pi](?![a-z])|\b(?:FullHD|FHD)\b/i', $name)) {
            return $this->matched(Category::XXX_HD, 0.9, 'hd');
        }

        // A bare HD token is weaker than an explicit resolution. Passion-HD is a studio name, not a resolution.
        if (preg_match('/\bHD\b/i', (string) preg_replace('/Passion[._ -]?HD/i', '', $name))) {
            return $this->matched(Category::XXX_HD, 0.8, 'hd_token');
        }

        if (preg_match('/(?<!\d)(?:240|270|360|480|540|576)[pi](?![a-z])|\bSD\b/i', $name)) {
            return $this->matched(Category::XXX_SD, 0.9, 'sd');
        }

        if (preg_match('/\b(?:XviD|DivX|(?:DVD|BD|BR)Rip|WMV)\b|\.wmv$|SDX264XXX|XXX\.HR\.|iPT\sTeam|KLEENEX|SDPORN/i', $name) ||
            preg_match('/anon@y[.]com|@md-hobbys[.]com|oz@lot[.]com/i', $poster)) {
            return $this->matched(Category::XXX_SD, 0.85, 'sd_legacy');
        }

        return null;
    }
}

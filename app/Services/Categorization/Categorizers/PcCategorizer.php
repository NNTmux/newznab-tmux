<?php

declare(strict_types=1);

namespace App\Services\Categorization\Categorizers;

use App\Models\Category;
use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\ReleaseContext;

/**
 * Categorizer for PC content (Games, Software, ISO, 0day, Mac, Phone apps).
 */
class PcCategorizer extends AbstractCategorizer
{
    protected int $priority = 30;

    // Common PC game release groups (matched at end of release name only)
    protected const PC_GROUPS = '0x0007|ALiAS|ANOMALY|BACKLASH|BAT|BLASTCiTY|CHRONOS|CODEX|CONSPIRACY|CPY|CROSSFiRE|DARKS(?:iDERS|IDERS)|DELiGHT|DEViANCE|DINOByTES|DOGE|DODI|DVN|ELAMIGOS|EMPRESS|ENiGMA|FANiSO|FAS(?:DOX|iSO)|FCKDRM|FITGIRL|FLT|FLTDOX|GGS|GOG(?:-?GAMES)?|GOLDBERG|HATRED|HI2U|HOODLUM|I_KnoW|iNLAWS|iNTERNAL|JAGUAR|KACZKRULL|KAOS|KaOsKrew|LAZARUS|LiGHTFORCE|MEPHISTO|OUTLAWS|PARADOX|PHOENIX|PLAZA|POSTMORTEM|PROPHET|PROVOKED|RADIUM|RAZOR(?:1911|DOX)|RELOADED|REVOLUTiON|RUNE|SAFARi|SCRUBS|SiLENTGATE|SiMPLEX|SKIDROW|SPLiNTERCELL|STEAMPUNKS|SUXXORS|TENOKE|TiNYiSO|UNLEASHED|VENGEANCE|ViTALiTY|VENOM|VACE|ZEKE|P2P';

    // PC-only keywords (can appear anywhere) - triggers PC_GAMES category
    protected const PC_KEYWORDS = 'PC[ _.-]?GAMES?|\[PC\]|\(PC\)|Steam[._-]?Rip|Retail\s*PC|DRM-?Free';

    // PC/Warez usenet groups
    protected const WAREZ_GROUPS = [
        'alt.binaries.warez',
        'alt.binaries.warez.0-day',
        'alt.binaries.0day',
        'alt.binaries.apps',
        'alt.binaries.software',
        'alt.binaries.pc',
        'alt.binaries.games.pc',
    ];

    public function getName(): string
    {
        return 'PC';
    }

    /**
     * Check if the group is a warez/PC software group.
     */
    protected function isWarezGroup(string $groupName): bool
    {
        if (empty($groupName)) {
            return false;
        }

        return in_array($groupName, self::WAREZ_GROUPS) ||
               (bool) preg_match('/alt\.binaries\.(warez|0day|apps|software)/i', $groupName);
    }

    /**
     * Check if the group is a PC games group.
     */
    protected function isPCGamesGroup(string $groupName): bool
    {
        if (empty($groupName)) {
            return false;
        }

        return (bool) preg_match('/alt\.binaries\.games\.pc/i', $groupName);
    }

    public function shouldSkip(ReleaseContext $context): bool
    {
        if ($context->hasAdultMarkers()) {
            return true;
        }
        // Skip TV shows (season patterns)
        if (preg_match('/[._ -]S\d{1,3}[._ -]?(E\d|Complete|Full|1080|720|480|2160|WEB|HDTV|BluRay)/i', $context->releaseName)) {
            return true;
        }

        return false;
    }

    public function categorize(ReleaseContext $context): CategorizationResult
    {
        $name = $context->releaseName;
        $groupName = $context->groupName;

        // Check if from PC-related group
        $isFromWarezGroup = $this->isWarezGroup($groupName);
        $isFromPCGamesGroup = $this->isPCGamesGroup($groupName);

        // Try each PC category
        if ($result = $this->checkPhone($name)) {
            return $result;
        }

        if ($result = $this->checkMac($name)) {
            return $result;
        }

        if ($result = $this->checkPCGame($name, $context->poster)) {
            return $result;
        }

        // If from PC games group but no specific match, categorize as PC game
        if ($isFromPCGamesGroup) {
            return $this->matched(Category::PC_GAMES, 0.7, 'fallback_pc_games_group');
        }

        if ($result = $this->checkISO($name)) {
            return $result;
        }

        if ($result = $this->check0day($name)) {
            return $result;
        }

        // If from warez group but no specific match, try to categorize by content hints
        if ($isFromWarezGroup) {
            return $this->categorizeByContentHints($name);
        }

        return $this->noMatch();
    }

    /**
     * Fallback categorization based on content hints for warez group releases.
     */
    protected function categorizeByContentHints(string $name): CategorizationResult
    {
        // Check for ISO indicators
        if (preg_match('/\.iso$|\bISO\b/i', $name)) {
            return $this->matched(Category::PC_ISO, 0.65, 'fallback_iso');
        }

        // Check for game indicators
        if (preg_match('/\b(Game|Trainer|Crack|Keygen)\b/i', $name)) {
            return $this->matched(Category::PC_GAMES, 0.65, 'fallback_game');
        }

        // Check for Mac indicators
        if (preg_match('/\b(Mac|OSX|macOS)\b/i', $name)) {
            return $this->matched(Category::PC_MAC, 0.65, 'fallback_mac');
        }

        // Check for mobile indicators
        if (preg_match('/\b(Android|APK|iOS|iPhone|iPad)\b/i', $name)) {
            if (preg_match('/\b(iOS|iPhone|iPad)\b/i', $name)) {
                return $this->matched(Category::PC_PHONE_IOS, 0.65, 'fallback_ios');
            }

            return $this->matched(Category::PC_PHONE_ANDROID, 0.65, 'fallback_android');
        }

        // Default to 0day
        return $this->matched(Category::PC_0DAY, 0.5, 'fallback_0day');
    }

    protected function checkPhone(string $name): ?CategorizationResult
    {
        // iOS
        if (preg_match('/[^a-z0-9](IPHONE|ITOUCH|IPAD)[._ -]/i', $name)) {
            return $this->matched(Category::PC_PHONE_IOS, 0.9, 'ios');
        }

        // Android
        if (preg_match('/[._ -]?(ANDROID)[._ -]/i', $name)) {
            return $this->matched(Category::PC_PHONE_ANDROID, 0.9, 'android');
        }

        // Other mobile platforms
        if (preg_match('/[^a-z0-9](symbian|xscale|wm5|wm6)[._ -]/i', $name)) {
            return $this->matched(Category::PC_PHONE_OTHER, 0.85, 'phone_other');
        }

        return null;
    }

    protected function checkMac(string $name): ?CategorizationResult
    {
        if (preg_match('/(\b|[._ -])mac([\.\s])?osx(\b|[\-_. ])/i', $name)) {
            return $this->matched(Category::PC_MAC, 0.9, 'mac');
        }

        if (preg_match('/\b(Mac\s?OS\s?X|macOS)\b/i', $name)) {
            return $this->matched(Category::PC_MAC, 0.9, 'macos');
        }

        return null;
    }

    protected function checkPCGame(string $name, string $poster): ?CategorizationResult
    {
        // Exclude console releases
        $consoleOrMac = '/\b(PS5|PS4|PS3|PlayStation|PS(Vita|V)\b|Xbox\s?(Series|One|360)|XBOX(ONE|360|SERIES|SX|SS)?|XSX|XSS|XBSX|NSW|Switch|WiiU|Wii|3DS|NDS|PSP|PSV(ita)?|GameCube|NGC|CUSA\d{5}|XCI|NSP|PKG)\b/i';
        if (preg_match($consoleOrMac, $name) || preg_match('/\b(Mac\s?OS\s?X|macOS)\b/i', $name)) {
            return null;
        }

        // Exclude TV shows
        $tvPatterns = '/\b(S\d{1,4}[._ -]?E\d{1,4}|S\d{1,4}[._ -]?D\d{1,4}|\d{1,2}x\d{2,3}|Season[._ -]?\d{1,3}|Episode[._ -]?\d{1,4}|HDTV|PDTV|DSR|WEB[._ -]?DL|WEB[._ -]?RIP|TVRip)\b/i';
        if (preg_match($tvPatterns, $name)) {
            return null;
        }

        // Check for PC game patterns
        // PC_GROUPS must be at the END of the release name (scene naming: Game.Name-GROUP)
        // PC_KEYWORDS can appear anywhere
        $groupPattern = '/[-.]('.self::PC_GROUPS.')$/i';
        $keywordPattern = '/'.self::PC_KEYWORDS.'/i';

        if (preg_match($groupPattern, $name) || preg_match($keywordPattern, $name)) {
            return $this->matched(Category::PC_GAMES, 0.9, 'pc_game');
        }

        // Check poster
        if (preg_match('/<PC@MASTER\.RACE>/i', $poster)) {
            return $this->matched(Category::PC_GAMES, 0.85, 'pc_game_poster');
        }

        return null;
    }

    protected function checkISO(string $name): ?CategorizationResult
    {
        if (preg_match('/[._ -]([a-zA-Z]{2,10})?iso[ _.-]|[\-. ]([a-z]{2,10})?iso$/i', $name)) {
            return $this->matched(Category::PC_ISO, 0.85, 'iso');
        }

        // Training/Tutorial ISOs
        if (preg_match('/[._ -](DYNAMiCS|INFINITESKILLS|UDEMY|kEISO|PLURALSIGHT|DIGITALTUTORS|TUTSPLUS|OSTraining|PRODEV|CBT\.Nuggets|COMPRISED)/i', $name)) {
            return $this->matched(Category::PC_ISO, 0.9, 'training_iso');
        }

        return null;
    }

    protected function check0day(string $name): ?CategorizationResult
    {
        if (preg_match('/\.(msix|msixbundle|appx|appxbundle|msi)$/i', $name)) {
            return $this->matched(Category::PC_0DAY, 0.9, '0day_msix_installer');
        }

        // Explicit 0day indicators
        if (preg_match('/[._ -]exe$|[._ -](utorrent|Virtualbox)[._ -]|\b0DAY\b|incl.+crack| DRM$|>DRM</i', $name)) {
            return $this->matched(Category::PC_0DAY, 0.9, '0day_explicit');
        }

        // System/architecture indicators
        if (preg_match('/[._ -]((32|64)bit|converter|i\d86|key(gen|maker)|freebsd|GAMEGUiDE|hpux|irix|linux|multilingual|Patch|Pro v\d{1,3}|portable|regged|software|solaris|template|unix|win2kxp2k3|win64|win(2k|32|64|all|dows|nt(2k)?(xp)?|xp)|win9x(me|nt)?|x(32|64|86))[._ -]/i', $name)) {
            return $this->matched(Category::PC_0DAY, 0.85, '0day_system');
        }

        // Software vendors and patterns
        if (preg_match('/\b(Adobe|auto(cad|desk)|-BEAN|Cracked|Cucusoft|CYGNUS|Divx[._ -]Plus|\.(deb|exe)|DIGERATI|FOSI|-FONT|Key(filemaker|gen|maker)|Lynda\.com|lz0|MULTiLANGUAGE|Microsoft\s*(Office|Windows|Server)|MultiOS|-(iNViSiBLE|SPYRAL|SUNiSO|UNION|TE)|v\d{1,3}.*?Pro|[._ -]v\d{1,3}[._ -]|\(x(64|86)\)|Xilisoft)\b/i', $name)) {
            return $this->matched(Category::PC_0DAY, 0.85, '0day_software');
        }

        return null;
    }
}

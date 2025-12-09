<?php

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

    // Common PC game release groups
    protected const PC_GROUPS = '0x0007|ALiAS|ANOMALY|BACKLASH|BAT|CODEX|CPY|DARKS(?:iDERS|IDERS)|DEViANCE|DOGE|DODI|ELAMIGOS|EMPRESS|FITGIRL|FAS(?:DOX|iSO)|FLT|GOG(?:-GAMES)?|GOLDBERG|HI2U|HOODLUM|INLAWS|JAGUAR|MAZE|MONEY|OUTLAWS|PLAZA|PROPHET|RAZOR1911|RAiN|RELOADED|RUNE|SiMPLEX|SKIDROW|TENOKE|TiNYiSO|UNLEASHED|P2P';

    // PC-only keywords
    protected const PC_KEYWORDS = 'PC[ _.-]?GAMES?|\[(?:PC)\]|\(PC\)|Steam(?:[ ._-]?Rip|\b)|GOG(?:\b|[ ._-])|Retail\s*PC|DRM-?Free|Win(All|32|64)\b|Windows(?:\s?10|\s?11)?\b|Repack';

    public function getName(): string
    {
        return 'PC';
    }

    public function shouldSkip(ReleaseContext $context): bool
    {
        if ($context->hasAdultMarkers()) return true;
        // Skip TV shows (season patterns)
        if (preg_match('/[._ -]S\d{1,3}[._ -]?(E\d|Complete|Full|1080|720|480|2160|WEB|HDTV|BluRay)/i', $context->releaseName)) return true;
        return false;
    }

    public function categorize(ReleaseContext $context): CategorizationResult
    {
        $name = $context->releaseName;

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

        if ($result = $this->checkISO($name)) {
            return $result;
        }

        if ($result = $this->check0day($name)) {
            return $result;
        }

        return $this->noMatch();
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
        $pattern = '/(?:(?:^|[\s\._-])(?:' . self::PC_GROUPS . ')(?:$|[\s\._-])|' . self::PC_KEYWORDS . ')/i';

        if (preg_match($pattern, $name)) {
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


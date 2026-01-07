<?php

declare(strict_types=1);

namespace App\Services\NameFixing\Extractors;

use App\Services\NameFixing\DTO\NameFixResult;

/**
 * Extracts release names from NFO content.
 *
 * Handles various NFO parsing patterns for TV, Movies, Music, Games, etc.
 */
class NfoNameExtractor
{
    /**
     * Check NFO content for TV patterns.
     */
    public function checkForTv(string $nfoContent): ?NameFixResult
    {
        $result = [];

        // Generic TV pattern 1: path with SxxExx
        if (preg_match('/:\s*.*[\\\\\/]([A-Z0-9].+?S\d+[.-_ ]?[ED]\d+.+?)\.\w{2,}\s+/i', $nfoContent, $result)) {
            return NameFixResult::fromMatch($result[1], 'Generic TV 1', 'NFO');
        }

        // Generic TV pattern 2: colon with SxxExx
        if (preg_match('/(?:(\:\s{1,}))(.+?S\d{1,3}[.-_ ]?[ED]\d{1,3}.+?)(\s{2,}|\r|\n)/i', $nfoContent, $result)) {
            return NameFixResult::fromMatch($result[2], 'Generic TV 2', 'NFO');
        }

        return null;
    }

    /**
     * Check NFO content for Movie patterns.
     */
    public function checkForMovie(string $nfoContent): ?NameFixResult
    {
        $result = [];

        // Generic Movies 1
        if (preg_match('/(?:((?!Source\s)\:\s{1,}))(.+?(19|20)\d\d.+?(BDRip|bluray|DVD(R|Rip)?|XVID).+?)(\s{2,}|\r|\n)/i', $nfoContent, $result)) {
            return NameFixResult::fromMatch($result[2], 'Generic Movies 1', 'NFO');
        }

        // Generic Movies 2
        if (preg_match('/(?:(\s{2,}))((?!Source).+?[\.\-_ ](19|20)\d\d.+?(BDRip|bluray|DVD(R|Rip)?|XVID).+?)(\s{2,}|\r|\n)/i', $nfoContent, $result)) {
            return NameFixResult::fromMatch($result[2], 'Generic Movies 2', 'NFO');
        }

        // Generic Movies 3 (NTSC/MULTi)
        if (preg_match('/(?:(\s{2,}))(.+?[\.\-_ ](NTSC|MULTi).+?(MULTi|DVDR)[\.\-_ ].+?)(\s{2,}|\r|\n)/i', $nfoContent, $result)) {
            return NameFixResult::fromMatch($result[2], 'Generic Movies 3', 'NFO');
        }

        return null;
    }

    /**
     * Check NFO content for Music patterns.
     */
    public function checkForMusic(string $nfoContent): ?NameFixResult
    {
        $result = [];

        // FM Radio pattern
        if (preg_match('/(?:\s{2,})(.+?-FM-\d{2}-\d{2})/i', $nfoContent, $result)) {
            $newName = str_replace('-FM-', '-FM-Radio-MP3-', $result[1]);

            return NameFixResult::fromMatch($newName, 'Music FM RADIO', 'NFO');
        }

        return null;
    }

    /**
     * Check NFO content for Title (Year) patterns.
     *
     * This is a comprehensive check that also extracts codec info.
     */
    public function checkForTitleYear(string $nfoContent): ?NameFixResult
    {
        $result = [];

        // Skip PDFs and audiobooks
        if (preg_match('/\.pdf|Audio ?Book/i', $nfoContent)) {
            return null;
        }

        // Look for Title (Year) pattern
        if (! preg_match('/(\w[\-\w`~!@#$%^&*()_+={}|"<>?\[\]\\;\',.\/ ]+\s?\((19|20)\d\d\))/i', $nfoContent, $result)) {
            return null;
        }

        $releaseName = $result[0];

        // Extract language
        $releaseName = $this->appendLanguageFromNfo($releaseName, $nfoContent);

        // Extract resolution
        $releaseName = $this->appendResolutionFromNfo($releaseName, $nfoContent);

        // Extract source
        $releaseName = $this->appendSourceFromNfo($releaseName, $nfoContent);

        // Extract video codec
        $releaseName = $this->appendVideoCodecFromNfo($releaseName, $nfoContent);

        // Extract audio codec
        $releaseName = $this->appendAudioCodecFromNfo($releaseName, $nfoContent);

        $releaseName .= '-NoGroup';

        return NameFixResult::fromMatch($releaseName, 'Title (Year)', 'NFO');
    }

    /**
     * Check NFO content for Game patterns.
     */
    public function checkForGame(string $nfoContent): ?NameFixResult
    {
        $result = [];

        // Check for known game groups
        if (! preg_match('/ALiAS|BAT-TEAM|FAiRLiGHT|Game Type|Glamoury|HI2U|iTWINS|JAGUAR|(LARGE|MEDIUM)ISO|MAZE|nERv|PROPHET|PROFiT|PROCYON|RELOADED|REVOLVER|ROGUE|ViTALiTY/i', $nfoContent)) {
            return null;
        }

        // (c) pattern
        if (preg_match('/\w[\w.+&*\/\()\',;: -]+\(c\)[\-\w.\',;& ]+\w/i', $nfoContent, $result)) {
            $releaseName = str_replace(['(c)', '(C)'], '(GAMES) (c)', $result[0]);

            return NameFixResult::fromMatch($releaseName, 'PC Games (c)', 'NFO');
        }

        // *ISO* pattern
        if (preg_match('/\w[\w.+&*\/()\',;: -]+\*ISO\*/i', $nfoContent, $result)) {
            $releaseName = str_replace('*ISO*', '*ISO* (PC GAMES)', $result[0]);

            return NameFixResult::fromMatch($releaseName, 'PC Games *ISO*', 'NFO');
        }

        return null;
    }

    /**
     * Check NFO content for IGUANA supplier patterns.
     */
    public function checkForIguana(string $nfoContent): ?NameFixResult
    {
        if (! preg_match('/Supplier.+?IGUANA/i', $nfoContent)) {
            return null;
        }

        $releaseName = '';
        $result = [];

        if (preg_match('/\w[\-\w`~!@#$%^&*()+={}|:"<>?\[\]\\;\',.\/ ]+\s\((19|20)\d\d\)/i', $nfoContent, $result)) {
            $releaseName = $result[0];
        }

        if (preg_match('/\s\[\*\] (English|Dutch|French|German|Spanish)\b/i', $nfoContent, $result)) {
            $releaseName .= '.'.$result[1];
        }

        if (preg_match('/\s\[\*\] (DT?S [2567][._ -][0-2]( MONO)?)\b/i', $nfoContent, $result)) {
            $releaseName .= '.'.$result[2];
        }

        if (preg_match('/Format.+(DVD([59R])?|[HX][._ -]?264)\b/i', $nfoContent, $result)) {
            $releaseName .= '.'.$result[1];
        }

        if (preg_match('/\[(640x.+|1280x.+|1920x.+)\] Resolution\b/i', $nfoContent, $result)) {
            $res = match (true) {
                $result[1] === '640x.+' => '480p',
                $result[1] === '1280x.+' => '720p',
                $result[1] === '1920x.+' => '1080p',
                default => $result[1],
            };
            $releaseName .= '.'.$res;
        }

        if ($releaseName !== '') {
            return NameFixResult::fromMatch($releaseName.'.IGUANA', 'IGUANA', 'NFO');
        }

        return null;
    }

    /**
     * Append language from NFO content.
     */
    protected function appendLanguageFromNfo(string $releaseName, string $nfoContent): string
    {
        $result = [];
        if (preg_match('/(idiomas|lang|language|langue|sprache).*?\b(?P<lang>Brazilian|Chinese|Croatian|Danish|DE|Deutsch|Dutch|Estonian|ES|English|Englisch|Finnish|Flemish|Francais|French|FR|German|Greek|Hebrew|Icelandic|Italian|Japenese|Japan|Japanese|Korean|Latin|Nordic|Norwegian|Polish|Portuguese|Russian|Serbian|Slovenian|Swedish|Spanisch|Spanish|Thai|Turkish)\b/i', $nfoContent, $result)) {
            $lang = match ($result['lang']) {
                'DE' => 'GERMAN',
                'Englisch' => 'ENGLISH',
                'FR' => 'FRENCH',
                'ES' => 'SPANISH',
                default => $result['lang'],
            };
            $releaseName .= '.'.$lang;
        }

        return $releaseName;
    }

    /**
     * Append resolution from NFO content.
     */
    protected function appendResolutionFromNfo(string $releaseName, string $nfoContent): string
    {
        $result = [];

        if (preg_match('/(frame size|(video )?res(olution)?|video).*?(?P<res>(272|336|480|494|528|608|\(?640|688|704|720x480|810|816|820|1 ?080|1280( \@)?|1 ?920(x1080)?))/i', $nfoContent, $result)) {
            $res = match ($result['res']) {
                '272', '336', '480', '494', '608', '640', '(640', '688', '704', '720x480' => '480p',
                '1280x720', '1280', '1280 @' => '720p',
                '810', '816', '820', '1920', '1 920', '1080', '1 080', '1920x1080' => '1080p',
                '2160' => '2160p',
                default => $result['res'],
            };
            $releaseName .= '.'.$res;
        } elseif (preg_match('/(largeur|width).*?(?P<res>(\(?640|688|704|720|1280( \@)?|1 ?920))/i', $nfoContent, $result)) {
            $res = match ($result['res']) {
                '640', '(640', '688', '704', '720' => '480p',
                '1280 @', '1280' => '720p',
                '1920', '1 920' => '1080p',
                '2160' => '2160p',
                default => $result['res'],
            };
            $releaseName .= '.'.$res;
        }

        return $releaseName;
    }

    /**
     * Append source from NFO content.
     */
    protected function appendSourceFromNfo(string $releaseName, string $nfoContent): string
    {
        $result = [];

        if (preg_match('/source.*?\b(?P<source>BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)\b/i', $nfoContent, $result)) {
            $source = match ($result['source']) {
                'BD' => 'Bluray.x264',
                'CAMRIP' => 'CAM',
                'DBrip' => 'BDRIP',
                'DVD R1', 'NTSC', 'PAL', 'VOD' => 'DVD',
                'HD' => 'HDTV',
                'Ripped ' => 'DVDRIP',
                default => $result['source'],
            };
            $releaseName .= '.'.$source;
        } elseif (preg_match('/(codec( (name|code))?|(original )?format|res(olution)|video( (codec|format|res))?|tv system|type|writing library).*?\b(?P<video>AVC|AVI|DBrip|DIVX|\(Divx|DVD|[HX][._ -]?264|MPEG-4 Visual|NTSC|PAL|WMV|XVID)\b/i', $nfoContent, $result)) {
            $video = match ($result['video']) {
                'AVI' => 'DVDRIP',
                'DBrip' => 'BDRIP',
                '(Divx' => 'DIVX',
                'h264', 'h-264', 'h.264' => 'H264',
                'MPEG-4 Visual', 'x264', 'x-264', 'x.264' => 'x264',
                'NTSC', 'PAL' => 'DVD',
                default => $result['video'],
            };
            $releaseName .= '.'.$video;
        }

        return $releaseName;
    }

    /**
     * Append video codec from NFO content.
     */
    protected function appendVideoCodecFromNfo(string $releaseName, string $nfoContent): string
    {
        // This is handled in appendSourceFromNfo for simplicity
        return $releaseName;
    }

    /**
     * Append audio codec from NFO content.
     */
    protected function appendAudioCodecFromNfo(string $releaseName, string $nfoContent): string
    {
        $result = [];

        if (preg_match('/(audio( format)?|codec( name)?|format).*?\b(?P<audio>0x0055 MPEG-1 Layer 3|AAC( LC)?|AC-?3|\(AC3|DD5(.1)?|(A_)?DTS-?(HD)?(-?MA)?|Dolby(\s?TrueHD)?|TrueHD|FLAC|MP3)\b/i', $nfoContent, $result)) {
            $audio = match ($result['audio']) {
                '0x0055 MPEG-1 Layer 3' => 'MP3',
                'AC-3', '(AC3' => 'AC3',
                'AAC LC' => 'AAC',
                'A_DTS', 'DTS-HD', 'DTSHD' => 'DTS',
                default => $result['audio'],
            };
            $releaseName .= '.'.$audio;
        }

        return $releaseName;
    }

    /**
     * Check all NFO patterns and return the first match.
     */
    public function extractFromNfo(string $nfoContent): ?NameFixResult
    {
        // Try each pattern in order of reliability
        $result = $this->checkForTv($nfoContent);
        if ($result !== null) {
            return $result;
        }

        $result = $this->checkForMovie($nfoContent);
        if ($result !== null) {
            return $result;
        }

        $result = $this->checkForMusic($nfoContent);
        if ($result !== null) {
            return $result;
        }

        $result = $this->checkForTitleYear($nfoContent);
        if ($result !== null) {
            return $result;
        }

        $result = $this->checkForGame($nfoContent);
        if ($result !== null) {
            return $result;
        }

        $result = $this->checkForIguana($nfoContent);
        if ($result !== null) {
            return $result;
        }

        return null;
    }
}

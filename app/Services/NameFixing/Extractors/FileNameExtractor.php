<?php

declare(strict_types=1);

namespace App\Services\NameFixing\Extractors;

use App\Services\NameFixing\DTO\NameFixResult;
use App\Services\NameFixing\FileNameCleaner;

/**
 * Extracts release names from file names and paths.
 *
 * Handles various filename patterns for scene releases, streaming services,
 * video files, music, games, ebooks, and more.
 */
class FileNameExtractor
{
    /**
     * PreDB regex pattern for scene release names.
     */
    public const PREDB_REGEX = '/([\w.\'()\[\]-]+(?:[\s._-]+[\w.\'()\[\]-]+)+[-.][\w]+)/ui';

    protected FileNameCleaner $cleaner;

    public function __construct(?FileNameCleaner $cleaner = null)
    {
        $this->cleaner = $cleaner ?? new FileNameCleaner;
    }

    /**
     * Extract a release name from a filename.
     */
    public function extractFromFile(string $filename): ?NameFixResult
    {
        $result = [];
        $cleanedFilename = $this->cleaner->cleanForTitleMatch($filename);

        // Try each pattern in order of specificity

        // Scene TV release with group suffix
        if (preg_match('/^(.+?(x264|x265|HEVC|XviD|H\.?264|H\.?265)\-[A-Za-z0-9]+)\\\\/i', $filename, $result)) {
            return NameFixResult::fromMatch($result[1], 'Scene release with group', 'File');
        }

        // TVP group format
        if (preg_match('/^(.+?(x264|XviD)\-TVP)\\\\/i', $filename, $result)) {
            return NameFixResult::fromMatch($result[1], 'TVP', 'File');
        }

        // Generic TV - SxxExx format with quality/source info
        if (preg_match('/^(\\\\|\/)?(.+(\\\\|\/))*(.+?S\d{1,3}[.-_ ]?E\d{1,3}(?:[.-_ ]?E\d{1,3})?[.-_ ].+?(?:720p|1080p|2160p|4K|HDTV|WEB-?DL|WEB-?RIP|BluRay|AMZN|HMAX|NF|DSNP).+?)\.(.+)$/iu', $filename, $result)) {
            return NameFixResult::fromMatch($result[4], 'TV SxxExx with quality', 'File');
        }

        // Generic TV - any SxxExx format
        if (preg_match('/^(\\\\|\/)?(.+(\\\\|\/))*(.+?S\d{1,3}[.-_ ]?[ED]\d{1,3}.+)\.(.+)$/iu', $filename, $result)) {
            return NameFixResult::fromMatch($result[4], 'Generic TV', 'File');
        }

        // 4K/UHD Movies - modern formats
        if (preg_match('/^(\\\\|\/)?(.+(\\\\|\/))*(.+?[\.\-_ ](19|20)\d\d[\.\-_ ].+?(2160p|4K|UHD).+?(HDR10?\+?|DV|Dolby[\.\-_ ]?Vision)?.+?(HEVC|x265|H\.?265).+?)\.(.+)$/iu', $filename, $result)) {
            return NameFixResult::fromMatch($result[4], '4K/UHD Movie', 'File');
        }

        // HD Movies with modern codecs
        if (preg_match('/^(\\\\|\/)?(.+(\\\\|\/))*(.+?[\.\-_ ](19|20)\d\d[\.\-_ ].+?(720p|1080p).+?(BluRay|WEB-?DL|WEB-?RIP|BDRip|REMUX).+?(x264|x265|HEVC|H\.?264|H\.?265|AVC).+?)\.(.+)$/iu', $filename, $result)) {
            return NameFixResult::fromMatch($result[4], 'HD Movie modern codec', 'File');
        }

        // Standard HD Movies
        if (preg_match('/^(\\\\|\/)?(.+(\\\\|\/))*(.+?([\.\-_ ]\d{4}[\.\-_ ].+?(BDRip|bluray|DVDRip|XVID|WEB-?DL|HDTV)).+)\.(.+)$/iu', $filename, $result)) {
            return NameFixResult::fromMatch($result[4], 'Generic movie 1', 'File');
        }

        if (preg_match('/^([a-z0-9\.\-_]+(19|20)\d\d[a-z0-9\.\-_]+[\.\-_ ](720p|1080p|2160p|4K|BDRip|bluray|DVDRip|x264|x265|XviD|HEVC)[a-z0-9\.\-_]+)\.[a-z]{2,}$/i', $filename, $result)) {
            return NameFixResult::fromMatch($result[1], 'Generic movie 2', 'File');
        }

        // Streaming service releases
        if (preg_match('/^([A-Za-z0-9\.\-_]+[\.\-_ ](AMZN|ATVP|DSNP|HMAX|HULU|iT|NF|PMTP|PCOK|ROKU|STAN|TVNZ|VUDU)[\.\-_ ].+?(WEB-?DL|WEB-?RIP).+?)\.(.+)$/i', $filename, $result)) {
            return NameFixResult::fromMatch($result[1], 'Streaming service release', 'File');
        }

        // Music releases
        if (preg_match('/(.+?([\.\-_ ](CD|FM)|[\.\-_ ]\dCD|CDR|FLAC|SAT|WEB).+?(19|20)\d\d.+?)\\\\.+/i', $filename, $result)) {
            return NameFixResult::fromMatch($result[1], 'Generic music', 'File');
        }

        if (preg_match('/^(.+?(19|20)\d\d\-([a-z0-9]{3}|[a-z]{2,}|C4))\\\\/i', $filename, $result)) {
            return NameFixResult::fromMatch($result[1], 'music groups', 'File');
        }

        // FLAC music releases
        if (preg_match('/^(.+?[\.\-_ ](FLAC|MP3|AAC|OGG)[\.\-_ ].+?[\.\-_ ]\d{4}[\.\-_ ].+?\-[A-Za-z0-9]+)[\\\\\/.]/i', $filename, $result)) {
            return NameFixResult::fromMatch($result[1], 'Music with codec', 'File');
        }

        // Movie with year in parentheses - AVI format
        if (preg_match('/.+\\\\(.+\((19|20)\d\d\)\.avi)$/i', $filename, $result)) {
            $newName = str_replace('.avi', ' DVDRip XVID NoGroup', $result[1]);

            return NameFixResult::fromMatch($newName, 'Movie (year) avi', 'File');
        }

        // Movie with year in parentheses - ISO format
        if (preg_match('/.+\\\\(.+\((19|20)\d\d\)\.iso)$/i', $filename, $result)) {
            $newName = str_replace('.iso', ' DVD NoGroup', $result[1]);

            return NameFixResult::fromMatch($newName, 'Movie (year) iso', 'File');
        }

        // Movie with year in parentheses - MKV format
        if (preg_match('/.+\\\\(.+\((19|20)\d\d\)\.(mkv|mp4|m4v))$/i', $filename, $result)) {
            $newName = preg_replace('/\.(mkv|mp4|m4v)$/i', ' BDRip x264 NoGroup', $result[1]);

            return NameFixResult::fromMatch($newName, 'Movie (year) mkv/mp4', 'File');
        }

        // RAR file contents - look for release name in RAR path
        if (preg_match('/^([A-Za-z0-9][\w.\-]+(?:[\.\-_ ][\w.\-]+)+)[\\\\\\/](?:CD\d|Disc\d|DVD\d|Subs?)?[\\\\\\/]?.+\.(rar|r\d{2,3}|zip|7z)$/i', $filename, $result)) {
            return NameFixResult::fromMatch($result[1], 'RAR archive path', 'File');
        }

        // Scene release in RAR
        if (preg_match('/^([A-Za-z0-9][\w.\-]+\-[A-Za-z0-9]+)[\\\\\\/].+\.(rar|r\d{2,3})$/i', $filename, $result)) {
            return NameFixResult::fromMatch($result[1], 'Scene RAR release', 'File');
        }

        // XXX Imagesets
        if (preg_match('/^(.+?IMAGESET.+?)\\\\.+/i', $filename, $result)) {
            return NameFixResult::fromMatch($result[1], 'XXX Imagesets', 'File');
        }

        // VIDEOOT releases
        if (preg_match('/^VIDEOOT-[A-Z0-9]+\\\\([\w!.,& ()\[\]\'\`-]{8,}?\b.?)([\-_](proof|sample|thumbs?))*(\.part\d*(\.rar)?|\.rar|\.7z)?(\d{1,3}\.rev|\.vol.+?|\.mp4)/', $filename, $result)) {
            return NameFixResult::fromMatch($result[1].' XXX DVDRIP XviD-VIDEOOT', 'XXX XviD VIDEOOT', 'File');
        }

        // XXX SDPORN
        if (preg_match('/^.+?SDPORN/i', $filename, $result)) {
            return NameFixResult::fromMatch($result[0], 'XXX SDPORN', 'File');
        }

        // R&C releases
        if (preg_match('/\w[\-\w.\',;& ]+1080i[._ -]DD5[._ -]1[._ -]MPEG2-R&C(?=\.ts)$/i', $filename, $result)) {
            $newResult = str_replace('MPEG2', 'MPEG2.HDTV', $result[0]);

            return NameFixResult::fromMatch($newResult, 'R&C', 'File');
        }

        // NhaNc3 releases
        if (preg_match('/\w[\-\w.\',;& ]+((s\d{1,2}[._ -]?[bde]\d{1,2})|\d{1,2}x\d{2}|ep[._ -]?\d{2})[._ -](480|720|1080)[ip][._ -](BD(-?(25|50|RIP))?|Blu-?Ray ?(3D)?|BRRIP|CAM(RIP)?|DBrip|DTV|DVD\-?(5|9|(R(IP)?|scr(eener)?))?|[HPS]D?(RIP|TV(RIP)?)?|NTSC|PAL|R5|Ripped |S?VCD|scr(eener)?|SAT(RIP)?|TS|VHS(RIP)?|VOD|WEB-DL)[._ -]nSD[._ -](DivX|[HX][._ -]?264|MPEG2|XviD(HD)?|WMV)[._ -]NhaNC3[\-\w.\',;& ]+\w/i', $filename, $result)) {
            return NameFixResult::fromMatch($result[0], 'NhaNc3', 'File');
        }

        // TVP releases (alternate pattern)
        if (preg_match('/\wtvp-[\w.\',;]+((s\d{1,2}[._ -]?[bde]\d{1,2})|\d{1,2}x\d{2}|ep[._ -]?\d{2})[._ -](720p|1080p|xvid)(?=\.(avi|mkv))$/i', $filename, $result)) {
            $newResult = str_replace('720p', '720p.HDTV.X264', $result[0]);
            $newResult = str_replace('1080p', '1080p.Bluray.X264', $newResult);
            $newResult = str_replace('xvid', 'XVID.DVDrip', $newResult);

            return NameFixResult::fromMatch($newResult, 'tvp', 'File');
        }

        // LOL releases
        if (preg_match('/\w[\-\w.\',;& ]+\d{3,4}\.hdtv-lol\.(avi|mp4|mkv|ts|nfo|nzb)/i', $filename, $result)) {
            return NameFixResult::fromMatch($result[0], 'Title.211.hdtv-lol.extension', 'File');
        }

        // DL releases
        if (preg_match('/\w[\-\w.\',;& ]+-S\d{1,2}[EX]\d{1,2}-XVID-DL\.avi/i', $filename, $result)) {
            return NameFixResult::fromMatch($result[0], 'Title-SxxExx-XVID-DL.avi', 'File');
        }

        // Title - SxxExx - Episode title format
        if (preg_match('/\S.*[\w.\-\',;]+\s\-\ss\d{2}[ex]\d{2}\s\-\s[\w.\-\',;].+\./i', $filename, $result)) {
            return NameFixResult::fromMatch($result[0], 'Title - SxxExx - Eptitle', 'File');
        }

        // Nintendo DS
        if (preg_match('/\w.+?\)\.nds$/i', $filename, $result)) {
            return NameFixResult::fromMatch($result[0], ').nds Nintendo DS', 'File');
        }

        // Nintendo 3DS
        if (preg_match('/3DS_\d{4}.+\d{4} - (.+?)\.3ds/i', $filename, $result)) {
            return NameFixResult::fromMatch('3DS '.$result[1], '.3ds Nintendo 3DS', 'File');
        }

        // Nintendo Switch
        if (preg_match('/^(.+?)\[[\w]+\]\.(?:nsp|xci|nsz)$/i', $filename, $result)) {
            return NameFixResult::fromMatch(trim($result[1]).' Switch', 'Nintendo Switch', 'File');
        }

        // PlayStation/Xbox game releases
        if (preg_match('/^(.+?[\.\-_ ](PS[345P]|PSV|XBOX360|XBOXONE|NSW)[\.\-_ ].+?\-[A-Za-z0-9]+)[\\\\\/.]/i', $filename, $result)) {
            return NameFixResult::fromMatch($result[1], 'Console game release', 'File');
        }

        // EBooks
        if (preg_match('/\w.+?\.(epub|mobi|azw3?|opf|fb2|prc|djvu|cb[rz])/i', $filename, $result)) {
            $newResult = str_replace('.'.$result[1], ' ('.$result[1].')', $result[0]);

            return NameFixResult::fromMatch($newResult, 'EBook', 'File');
        }

        // Audiobooks
        if (preg_match('/^(.+?[\.\-_ ]Audiobook[\.\-_ ].+?)[\\\\\/.]/i', $filename, $result)) {
            return NameFixResult::fromMatch($result[1], 'Audiobook', 'File');
        }

        // Scene release from cleaned filename
        if (preg_match('/^([A-Za-z0-9][\w.\-]+\-[A-Za-z0-9]{2,15})$/i', $cleanedFilename, $result) && preg_match(self::PREDB_REGEX, $cleanedFilename)) {
            return NameFixResult::fromMatch($result[1], 'Cleaned scene name', 'File');
        }

        // Folder name fallback
        if (preg_match('/\w+[\-\w.\',;& ]+$/i', $filename, $result) && preg_match(self::PREDB_REGEX, $filename)) {
            return NameFixResult::fromMatch($result[0], 'Folder name', 'File');
        }

        return null;
    }
}

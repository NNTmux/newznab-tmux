<?php

declare(strict_types=1);

namespace App\Services\Nzb;

/**
 * Service for parsing and extracting information from NZB file contents.
 * Handles parsing NZB XML structure and extracting file lists, extensions, sizes, etc.
 */
class NzbParserService
{
    /**
     * Retrieve various information on an NZB file (the subject, # of pars,
     * file extensions, file sizes, file completion, group names, # of parts).
     *
     * @param  string  $nzb  The NZB contents in a string.
     * @param  array  $options  Optional settings:
     *                          - 'no-file-key': Use numeric keys instead of subject (default: true)
     *                          - 'strip-count': Strip file/part count from subject for sorting (default: false)
     * @return array $result Empty if not an NZB or the contents of the NZB.
     */
    public function parseNzbFileList(string $nzb, array $options = []): array
    {
        $defaults = [
            'no-file-key' => true,
            'strip-count' => false,
        ];
        $options += $defaults;

        $i = 0;
        $result = [];

        if (! $nzb) {
            return $result;
        }

        $xml = @simplexml_load_string(str_replace("\x0F", '', $nzb));
        if (! $xml || strtolower($xml->getName()) !== 'nzb') {
            return $result;
        }

        foreach ($xml->file as $file) {
            // Subject.
            $title = (string) $file->attributes()->subject;

            if ($options['no-file-key'] === false) {
                $i = $title;
                if ($options['strip-count']) {
                    // Strip file / part count to get proper sorting.
                    $i = preg_replace('#\d+[- ._]?(/|\||[o0]f)[- ._]?\d+?(?![- ._]\d)#i', '', $i);
                    // Change .rar and .par2 to be sorted before .part0x.rar and .volxxx+xxx.par2
                    if (str_contains($i, '.par2') && ! preg_match('#\.vol\d+\+\d+\.par2#i', $i)) {
                        $i = str_replace('.par2', '.vol0.par2', $i);
                    } elseif (preg_match('#\.rar[^a-z0-9]#i', $i) && ! preg_match('#\.part\d+\.rar$#i', $i)) {
                        $i = preg_replace('#\.rar(?:[^a-z0-9])#i', '.part0.rar', $i);
                    }
                }
            }

            $result[$i]['title'] = $title;

            // Extensions.
            if (preg_match(
                '/\.(\d{2,3}|7z|ace|ai7|srr|srt|sub|aiff|asc|avi|audio|bin|bz2|'
                .'c|cfc|cfm|chm|class|conf|cpp|cs|css|csv|cue|deb|divx|doc|dot|'
                .'eml|enc|exe|file|gif|gz|hlp|htm|html|image|iso|jar|java|jpeg|'
                .'jpg|js|lua|m|m3u|mkv|mm|mov|mp3|mp4|mpg|nfo|nzb|odc|odf|odg|odi|odp|'
                .'ods|odt|ogg|par2|parity|pdf|pgp|php|pl|png|ppt|ps|py|r\d{2,3}|'
                .'ram|rar|rb|rm|rpm|rtf|sfv|sig|sql|srs|swf|sxc|sxd|sxi|sxw|tar|'
                .'tex|tgz|txt|vcf|video|vsd|wav|wma|wmv|xls|xml|xpi|xvid|zip7|zip)'
                .'[" ](?!([\)|\-]))/i',
                $title,
                $ext
            )
            ) {
                if (preg_match('/\.r\d{2,3}/i', $ext[0])) {
                    $ext[1] = 'rar';
                }
                $result[$i]['ext'] = strtolower($ext[1]);
            } else {
                $result[$i]['ext'] = '';
            }

            $fileSize = $numSegments = 0;

            // Parts.
            if (! isset($result[$i]['segments'])) {
                $result[$i]['segments'] = [];
            }

            // File size.
            foreach ($file->segments->segment as $segment) {
                $result[$i]['segments'][] = (string) $segment;
                $fileSize += $segment->attributes()->bytes;
                $numSegments++;
            }
            $result[$i]['size'] = $fileSize;

            // File completion.
            if (preg_match('/(\d+)\)$/', $title, $parts)) {
                $result[$i]['partstotal'] = $parts[1];
            }
            $result[$i]['partsactual'] = $numSegments;

            // Groups.
            if (! isset($result[$i]['groups'])) {
                $result[$i]['groups'] = [];
            }
            foreach ($file->groups->group as $g) {
                $result[$i]['groups'][] = (string) $g;
            }

            if ($options['no-file-key']) {
                $i++;
            }
        }

        return $result;
    }

    /**
     * Load and parse an NZB file from its contents string.
     *
     * @param  string  $nzbContents  The decompressed NZB file contents
     * @param  bool  $echoErrors  Whether to echo errors on parse failure
     * @param  string  $guid  Optional GUID for error messages
     * @return \SimpleXMLElement|false The parsed NZB file as SimpleXMLElement or false on failure
     */
    public function parseNzbXml(string $nzbContents, bool $echoErrors = false, string $guid = ''): \SimpleXMLElement|false
    {
        if (empty($nzbContents)) {
            return false;
        }

        // Safely parse the XML content
        libxml_use_internal_errors(true);
        $nzbFile = simplexml_load_string($nzbContents);

        if ($nzbFile === false) {
            if ($echoErrors) {
                $errors = libxml_get_errors();
                $errorMsg = ! empty($errors) ? ' - XML error: '.$errors[0]->message : '';
                echo PHP_EOL."Unable to load NZB: {$guid} appears to be an invalid NZB{$errorMsg}, skipping.".PHP_EOL;
                libxml_clear_errors();
            }

            return false;
        }

        return $nzbFile;
    }

    /**
     * Get the file extension from a subject line.
     *
     * @param  string  $subject  The file subject/name
     * @return string The detected extension, or empty string if none found
     */
    public function detectFileExtension(string $subject): string
    {
        if (preg_match(
            '/\.(\d{2,3}|7z|ace|ai7|srr|srt|sub|aiff|asc|avi|audio|bin|bz2|'
            .'c|cfc|cfm|chm|class|conf|cpp|cs|css|csv|cue|deb|divx|doc|dot|'
            .'eml|enc|exe|file|gif|gz|hlp|htm|html|image|iso|jar|java|jpeg|'
            .'jpg|js|lua|m|m3u|mkv|mm|mov|mp3|mp4|mpg|nfo|nzb|odc|odf|odg|odi|odp|'
            .'ods|odt|ogg|par2|parity|pdf|pgp|php|pl|png|ppt|ps|py|r\d{2,3}|'
            .'ram|rar|rb|rm|rpm|rtf|sfv|sig|sql|srs|swf|sxc|sxd|sxi|sxw|tar|'
            .'tex|tgz|txt|vcf|video|vsd|wav|wma|wmv|xls|xml|xpi|xvid|zip7|zip)'
            .'[" ](?!([\)|\-]))/i',
            $subject,
            $ext
        )
        ) {
            if (preg_match('/\.r\d{2,3}/i', $ext[0])) {
                return 'rar';
            }

            return strtolower($ext[1]);
        }

        return '';
    }

    /**
     * Check if a subject indicates an NFO file.
     *
     * @param  string  $subject  The file subject
     * @return array|false Returns array with detection info or false if not an NFO
     */
    public function detectNfoFile(string $subject): array|false
    {
        // Standard NFO extensions
        if (preg_match('/\.\b(nfo|diz|info?)\b(?![.-])/i', $subject)) {
            return ['hidden' => false, 'priority' => 1];
        }

        // Alternative NFO naming patterns (group-specific or obfuscated)
        if (preg_match('/(?:^|["\s])(?:file(?:_?id)?|readme|release|info(?:rmation)?|about|desc(?:ription)?|notes?|read\.?me|00-|000-|0-|_-_).*?\.(?:txt|nfo|diz)(?:["\s]|$)/i', $subject)) {
            return ['hidden' => false, 'priority' => 2];
        }

        return false;
    }

    /**
     * Check if a subject might indicate a hidden NFO file.
     *
     * @param  string  $subject  The file subject
     * @param  int  $segmentCount  The number of segments in the file
     * @return array|false Returns array with detection info or false if not a hidden NFO
     */
    public function detectHiddenNfoFile(string $subject, int $segmentCount): array|false
    {
        $isHiddenNfoCandidate = false;

        // Pattern 1: Single segment files with (1/1)
        if ($segmentCount === 1 && preg_match('/\(1\/1\)$/i', $subject)) {
            $isHiddenNfoCandidate = true;
        }

        // Pattern 2: Small segment count (1-2) with NFO-like names but no extension
        if (! $isHiddenNfoCandidate && $segmentCount <= 2 && preg_match('/(?:^|["\s])(?:nfo|info|readme|release|file_?id|about)(?:["\s]|$)/i', $subject)) {
            $isHiddenNfoCandidate = true;
        }

        // Pattern 3: Scene-style NFO naming (group-release.nfo without extension visible)
        if (! $isHiddenNfoCandidate && $segmentCount === 1 && preg_match('/^[a-z0-9._-]+["\s]*\(1\/1\)/i', $subject)) {
            // Check for scene-like naming pattern
            if (preg_match('/^[a-z0-9]+[._-][a-z0-9._-]+["\s]*\(1\/1\)/i', $subject)) {
                $isHiddenNfoCandidate = true;
            }
        }

        // Pattern 4: Very small files (NFOs are typically small)
        // Files described as very small in bytes could be NFOs
        if (! $isHiddenNfoCandidate && $segmentCount === 1 && preg_match('/yEnc\s*\(\d+\)\s*\[1\/1\]/i', $subject)) {
            $isHiddenNfoCandidate = true;
        }

        if (! $isHiddenNfoCandidate) {
            return false;
        }

        // Enhanced exclusion: check if it's NOT likely another common file type
        $excludedExtensions = '/\.(?:'.
            // Executables
            'exe|com|bat|cmd|scr|dll|msi|pkg|deb|rpm|apk|ipa|app|'.
            // Archives
            'zip|rar|[rst]\d{2}|7z|ace|tar|gz|bz2|xz|lzma|cab|iso|bin|cue|img|mdf|nrg|dmg|vhd|'.
            // Audio
            'mp3|flac|ogg|aac|wav|wma|m4a|opus|ape|wv|mpc|'.
            // Video
            'avi|mkv|mp4|mov|wmv|mpg|mpeg|ts|vob|m2ts|webm|flv|ogv|divx|xvid|'.
            // Images
            'jpg|jpeg|png|gif|bmp|tif|tiff|psd|webp|svg|ico|raw|cr2|nef|'.
            // Documents
            'pdf|doc|docx|xls|xlsx|ppt|pptx|odt|ods|odp|rtf|epub|mobi|azw|'.
            // Code
            'html|htm|css|js|php|py|java|c|cpp|h|cs|sql|json|xml|yml|yaml|'.
            // Data
            'db|dbf|mdb|accdb|sqlite|csv|'.
            // Verification
            'par2?|sfv|md5|sha1|sha256|sha512|crc|'.
            // Misc
            'url|lnk|cfg|ini|inf|sys|tmp|bak|log|srt|sub|idx|ass|ssa|vtt'.
            ')\b/i';

        if (preg_match($excludedExtensions, $subject)) {
            return false;
        }

        return ['hidden' => true, 'priority' => 10];
    }

    /**
     * Check if a subject indicates a PAR2 index file.
     *
     * @param  string  $subject  The file subject
     * @return bool True if it's a PAR2 index file
     */
    public function detectPar2IndexFile(string $subject): bool
    {
        return (bool) preg_match('/\.par2$/i', $subject);
    }

    /**
     * Calculate artificial parts from a subject line.
     *
     * @param  string  $subject  The file subject
     * @return int The estimated total parts, or 0 if not determinable
     */
    public function extractPartsTotal(string $subject): int
    {
        // Improve artificial parts calculation robustness (e.g., "[15/20]", "(15/20)")
        if (preg_match('/(?:[(\[])?(\d+)[\/)\\]](\d+)[)\]]?$/', $subject, $parts)) {
            if (isset($parts[2]) && (int) $parts[2] > 0) {
                return (int) $parts[2];
            }
        }

        // Fallback to original simple check
        if (preg_match('/(\d+)\)$/', $subject, $parts)) {
            return (int) $parts[1];
        }

        return 0;
    }
}

<?php

namespace Blacklight\utility;

use App\Models\Settings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Class Utility.
 */
class Utility
{
    /**
     *  Regex for detecting multi-platform path. Use it where needed so it can be updated in one location as required characters get added.
     */
    public const PATH_REGEX = '(?P<drive>[A-Za-z]:|)(?P<path>[/\w.-]+|)';

    public const VERSION_REGEX = '#(?P<all>v(?P<digits>(?P<major>\d+)\.(?P<minor>\d+)\.(?P<revision>\d+)(?:\.(?P<fix>\d+))?)(?:-(?P<suffix>(?:RC\d+|dev)))?)#';

    /**
     * Checks all levels of the supplied path are readable and executable by current user.
     *
     * @todo Make this recursive with a switch to only check end point.
     *
     * @param $path	*nix path to directory or file
     * @return bool|string True is successful, otherwise the part of the path that failed testing.
     */
    public static function canExecuteRead($path): bool|string
    {
        $paths = explode('#/#', $path);
        $fullPath = DS;
        foreach ($paths as $singlePath) {
            if ($singlePath !== '') {
                $fullPath .= $singlePath.DS;
                if (! is_readable($fullPath) || ! is_executable($fullPath)) {
                    return "The '$fullPath' directory must be readable and executable by all .".PHP_EOL;
                }
            }
        }

        return true;
    }

    public static function clearScreen(): void
    {
        if (self::isCLI()) {
            passthru('clear');
        }
    }

    /**
     * Removes the preceeding or proceeding portion of a string
     * relative to the last occurrence of the specified character.
     * The character selected may be retained or discarded.
     *
     * @param string $character  the character to search for.
     * @param string $string  the string to search through.
     * @param string $side  determines whether text to the left or the right of the character is returned.
     *                        Options are: left, or right.
     * @param bool $keep_character  determines whether or not to keep the character.
     *                                Options are: true, or false.
     * @return string
     */
    public static function cutStringUsingLast(string $character, string $string, string $side, bool $keep_character = true): string
    {
        $offset = ($keep_character ? 1 : 0);
        $whole_length = \strlen($string);
        $right_length = (\strlen(strrchr($string, $character)) - 1);
        $left_length = ($whole_length - $right_length - 1);
        switch ($side) {
            case 'left':
                $piece = substr($string, 0, $left_length + $offset);
                break;
            case 'right':
                $start = (0 - ($right_length + $offset));
                $piece = substr($string, $start);
                break;
            default:
                $piece = false;
                break;
        }

        return $piece;
    }

    /**
     * @param  array|null  $options
     * @return array|null
     */
    public static function getDirFiles(array $options = null): ?array
    {
        $defaults = [
            'dir'   => false,
            'ext'   => '', // no full stop (period) separator should be used.
            'file'    => true,
            'path'  => '',
            'regex' => '',
        ];
        $options += $defaults;
        if (! $options['dir'] && ! $options['file']) {
            return null;
        }

        // Replace windows style path separators with unix style.
        $iterator = new \FilesystemIterator(
            str_replace('\\', '/', $options['path']),
            \FilesystemIterator::KEY_AS_PATHNAME |
            \FilesystemIterator::SKIP_DOTS |
            \FilesystemIterator::UNIX_PATHS
        );

        $files = [];
        foreach ($iterator as $fileInfo) {
            $file = $iterator->key();
            switch (true) {
                case ! $options['dir'] && $fileInfo->isDir():
                    break;
                case ! empty($options['ext']) && $fileInfo->getExtension() !== $options['ext']:
                    break;
                case empty($options['regex']) || ! preg_match($options['regex'], $file):
                    break;
                case ! $options['file'] && $fileInfo->isFile():
                    break;
                default:
                    $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @return array
     */
    public static function getThemesList(): array
    {
        $ignoredThemes = ['admin', 'shared'];
        $themes = scandir(base_path().'/resources/views/themes', SCANDIR_SORT_ASCENDING);
        $themeList[] = 'None';
        foreach ($themes as $theme) {
            if (! str_contains($theme, '.') && ! \in_array($theme, $ignoredThemes, false) && File::isDirectory(base_path().'/resources/views/themes/'.$theme)) {
                $themeList[] = $theme;
            }
        }

        sort($themeList);

        return $themeList;
    }

    /**
     * Detect if the command is accessible on the system.
     *
     *
     * @param $cmd
     * @return bool
     */
    public static function hasCommand($cmd): bool
    {
        $returnVal = shell_exec("which $cmd");

        return $returnVal !== null;
    }

    /**
     * Check if user is running from CLI.
     *
     * @return bool
     */
    public static function isCLI(): bool
    {
        return strtolower(PHP_SAPI) === 'cli';
    }

    /**
     * @param $filename
     * @return bool|null|string
     */
    public static function isGZipped($filename)
    {
        $gzipped = null;
        if (($fp = fopen($filename, 'rb')) !== false) {
            if (@fread($fp, 2) === "\x1F\x8B") { // this is a gzip'd file
                fseek($fp, -4, SEEK_END);
                if (\strlen($datum = @fread($fp, 4)) === 4) {
                    $gzipped = $datum;
                }
            }
            fclose($fp);
        }

        return $gzipped;
    }

    /**
     * Strips non-printing characters from a string.
     *
     * Operates directly on the text string, but also returns the result for situations requiring a
     * return value (use in ternary, etc.)/
     *
     * @param string $text  String variable to strip.
     * @return string The stripped variable.
     */
    public static function stripNonPrintingChars(string &$text): string
    {
        $text = str_replace('/[[:^print:]]/', '', $text);

        return $text;
    }

    /**
     * Unzip a gzip file, return the output. Return false on error / empty.
     *
     * @param string $filePath
     * @return bool|string
     */
    public static function unzipGzipFile(string $filePath)
    {
        $string = '';
        $gzFile = @gzopen($filePath, 'rb', 0);
        if ($gzFile) {
            while (! gzeof($gzFile)) {
                $temp = gzread($gzFile, 1024);
                // Check for empty string.
                // Without this the loop would be endless and consume 100% CPU.
                // Do not set $string empty here, as the data might still be good.
                if (! $temp) {
                    break;
                }
                $string .= $temp;
            }
            gzclose($gzFile);
        }

        return $string === '' ? false : $string;
    }

    /**
     * @param $path
     */
    public static function setCoversConstant($path): void
    {
        if (! \defined('NN_COVERS')) {
            switch (true) {
                case $path[0] === '/' || $path[1] === ':' || $path[0] === '\\':
                    \define('NN_COVERS', Str::finish($path, '/'));
                    break;
                case $path !== '' && $path[0] !== '/' && $path[1] !== ':' && $path[0] !== '\\':
                    \define('NN_COVERS', realpath(base_path().Str::finish($path, '/')));
                    break;
                case empty($path): // Default to resources location.
                default:
                    \define('NN_COVERS', NN_RES.'covers/');
            }
        }
    }

    /**
     * Creates an array to be used with stream_context_create() to verify openssl certificates
     * when connecting to a tls or ssl connection when using stream functions (fopen/file_get_contents/etc).
     *
     * @param bool $forceIgnore  Force ignoring of verification.
     * @return array
     * @static
     */
    public static function streamSslContextOptions(bool $forceIgnore = false): array
    {
        if (config('nntmux_ssl.ssl_cafile') === '' && config('nntmux_ssl.ssl_capath') === '') {
            $options = [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ];
        } else {
            $options = [
                'verify_peer'       => $forceIgnore ? false : config('nntmux_ssl.ssl_verify_peer'),
                'verify_peer_name'  => $forceIgnore ? false : config('nntmux_ssl.ssl_verify_host'),
                'allow_self_signed' => $forceIgnore ? true : config('nntmux_ssl.ssl_allow_self_signed'),
            ];
            if (config('nntmux_ssl.ssl_cafile') !== '') {
                $options['cafile'] = config('nntmux_ssl.ssl_cafile');
            }
            if (config('nntmux_ssl.ssl_capath') !== '') {
                $options['capath'] = config('nntmux_ssl.ssl_capath');
            }
        }
        // If we set the transport to tls and the server falls back to ssl,
        // the context options would be for tls and would not apply to ssl,
        // so set both tls and ssl context in case the server does not support tls.
        return ['tls' => $options, 'ssl' => $options];
    }

    /**
     * Set curl context options for verifying SSL certificates.
     *
     * @param bool $verify  false = Ignore config.php and do not verify the openssl cert.
     *                        true  = Check config.php and verify based on those settings.
     *                        If you know the certificate will be self-signed, pass false.
     * @return array
     * @static
     */
    public static function curlSslContextOptions(bool $verify = true): array
    {
        $options = [];
        if ($verify && config('nntmux_ssl.ssl_verify_host') && (! empty(config('nntmux_ssl.ssl_cafile')) || ! empty(config('nntmux_ssl.ssl_capath')))) {
            $options += [
                CURLOPT_SSL_VERIFYPEER => (bool) config('nntmux_ssl.ssl_verify_peer'),
                CURLOPT_SSL_VERIFYHOST => config('nntmux_ssl.ssl_verify_host') ? 2 : 0,
            ];
            if (! empty(config('nntmux_ssl.ssl_cafile'))) {
                $options += [CURLOPT_CAINFO => config('nntmux_ssl.ssl_cafile')];
            }
            if (! empty(config('nntmux_ssl.ssl_capath'))) {
                $options += [CURLOPT_CAPATH => config('nntmux_ssl.ssl_capath')];
            }
        } else {
            $options += [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ];
        }

        return $options;
    }

    /**
     * @param  array  $options
     * @return string
     */
    public static function getCoverURL(array $options = []): string
    {
        $defaults = [
            'id'     => null,
            'suffix' => '-cover.jpg',
            'type'   => '',
        ];
        $options += $defaults;
        $fileSpecTemplate = '%s/%s%s';
        $fileSpec = '';

        if (! empty($options['id']) && \in_array(
            $options['type'],
            ['anime', 'audio', 'audiosample', 'book', 'console', 'games', 'movies', 'music', 'preview', 'sample', 'tvrage', 'video', 'xxx'],
            false
            )
        ) {
            $fileSpec = sprintf($fileSpecTemplate, $options['type'], $options['id'], $options['suffix']);
            $fileSpec = file_exists(NN_COVERS.$fileSpec) ? $fileSpec :
                sprintf($fileSpecTemplate, $options['type'], 'no', $options['suffix']);
        }

        return $fileSpec;
    }

    /**
     * Converts XML to an associative array with namespace preservation -- use if intending to JSON encode.
     *
     * @author Tamlyn from Outlandish.com
     *
     * @param  \SimpleXMLElement  $xml  The SimpleXML parsed XML string data
     * @param  array  $options
     * @return array The associate array of the XML namespaced file
     */
    public static function xmlToArray(\SimpleXMLElement $xml, array $options = []): array
    {
        $defaults = [
            'namespaceSeparator' => ':', //you may want this to be something other than a colon
            'attributePrefix' => '@',   //to distinguish between attributes and nodes with the same name
            'alwaysArray' => [],   //array of xml tag names which should always become arrays
            'autoArray' => true,        //only create arrays for tags which appear more than once
            'textContent' => '$',       //key used for the text content of elements
            'autoText' => true,         //skip textContent key if node has no attributes or child nodes
            'keySearch' => false,       //optional search and replace on tag and attribute names
            'keyReplace' => false,       //replace values for above search values (as passed to str_replace())
        ];
        $options = array_merge($defaults, $options);
        $namespaces = $xml->getDocNamespaces();
        $namespaces[''] = null; //add base (empty) namespace

        $attributesArray = $tagsArray = [];
        foreach ($namespaces as $prefix => $namespace) {
            //get attributes from all namespaces
            foreach ($xml->attributes($namespace) as $attributeName => $attribute) {
                //replace characters in attribute name
                if ($options['keySearch']) {
                    $attributeName =
                    str_replace($options['keySearch'], $options['keyReplace'], $attributeName);
                }
                $attributeKey = $options['attributePrefix']
                    .($prefix ? $prefix.$options['namespaceSeparator'] : '')
                    .$attributeName;
                $attributesArray[$attributeKey] = (string) $attribute;
            }
            //get child nodes from all namespaces
            foreach ($xml->children($namespace) as $childXml) {
                //recurse into child nodes
                $childArray = self::xmlToArray($childXml, $options);
                $childTagName = key($childArray);
                $childProperties = current($childArray);

                //replace characters in tag name
                if ($options['keySearch']) {
                    $childTagName =
                    str_replace($options['keySearch'], $options['keyReplace'], $childTagName);
                }
                //add namespace prefix, if any
                if ($prefix) {
                    $childTagName = $prefix.$options['namespaceSeparator'].$childTagName;
                }

                if (! isset($tagsArray[$childTagName])) {
                    //only entry with this key
                    //test if tags of this type should always be arrays, no matter the element count
                    $tagsArray[$childTagName] =
                        \in_array($childTagName, $options['alwaysArray'], false) || ! $options['autoArray']
                            ? [$childProperties] : $childProperties;
                } elseif (
                    \is_array($tagsArray[$childTagName]) && array_is_list($tagsArray[$childTagName])
                ) {
                    //key already exists and is integer indexed array
                    $tagsArray[$childTagName][] = $childProperties;
                } else {
                    //key exists so convert to integer indexed array with previous value in position 0
                    $tagsArray[$childTagName] = [$tagsArray[$childTagName], $childProperties];
                }
            }
        }

        //get text content of node
        $textContentArray = [];
        $plainText = trim((string) $xml);
        if ($plainText !== '') {
            $textContentArray[$options['textContent']] = $plainText;
        }

        //stick it all together
        $propertiesArray = ! $options['autoText'] || $attributesArray || $tagsArray || ($plainText === '')
            ? array_merge($attributesArray, $tagsArray, $textContentArray) : $plainText;

        //return node as array
        return [
            $xml->getName() => $propertiesArray,
        ];
    }

    /**
     * Return file type/info using magic numbers.
     * Try using `file` program where available, fallback to using PHP's finfo class.
     *
     * @param string $path  Path to the file / folder to check.
     * @return string File info. Empty string on failure.
     *
     * @throws \Exception
     */
    public static function fileInfo(string $path): string
    {
        $magicPath = Settings::settingValue('apps.indexer.magic_file_path');
        if ($magicPath !== null && self::hasCommand('file')) {
            $magicSwitch = " -m $magicPath";
            $output = runCmd('file'.$magicSwitch.' -b "'.$path.'"');
        } else {
            $fileInfo = $magicPath === null ? finfo_open(FILEINFO_RAW) : finfo_open(FILEINFO_RAW, $magicPath);

            $output = finfo_file($fileInfo, $path);
            if (empty($output)) {
                $output = '';
            }
            finfo_close($fileInfo);
        }

        return $output;
    }

    /**
     * @param $code
     * @return bool
     */
    public function checkStatus($code): bool
    {
        return $code === 0;
    }

    /**
     * Convert Code page 437 chars to UTF.
     *
     * @param string $string
     * @return string
     */
    public static function cp437toUTF(string $string): string
    {
        return iconv('CP437', 'UTF-8//IGNORE//TRANSLIT', $string);
    }

    /**
     * Fetches an embeddable video to a IMDB trailer from http://www.traileraddict.com.
     *
     * @param $imdbID
     * @return string
     */
    public static function imdb_trailers($imdbID): string
    {
        $xml = getRawHtml('https://api.traileraddict.com/?imdb='.$imdbID);
        if ($xml !== false && preg_match('#(v\.traileraddict\.com/\d+)#i', $xml, $html)) {
            return 'https://'.$html[1];
        }

        return '';
    }

    /**
     * Convert obj to array.
     *
     * @param  $arrObjData
     * @param  array  $arrSkipIndices
     * @return array
     */
    public static function objectsIntoArray($arrObjData, array $arrSkipIndices = []): array
    {
        $arrData = [];

        // If input is object, convert into array.
        if (\is_object($arrObjData)) {
            $arrObjData = get_object_vars($arrObjData);
        }

        if (\is_array($arrObjData)) {
            foreach ($arrObjData as $index => $value) {
                // Recursive call.
                if (\is_object($value) || \is_array($value)) {
                    $value = self::objectsIntoArray($value, $arrSkipIndices);
                }
                if (\in_array($index, $arrSkipIndices, false)) {
                    continue;
                }
                $arrData[$index] = $value;
            }
        }

        return $arrData;
    }

    /**
     * Remove unsafe chars from a filename.
     *
     * @param string $filename
     * @return string
     */
    public static function safeFilename(string $filename): string
    {
        return trim(preg_replace('/[^\w\s.-]*/i', '', $filename));
    }

    /**
     * @param $input
     * @return \SimpleXMLElement
     */
    public static function responseXmlToObject($input): \SimpleXMLElement
    {
        $input = str_replace('<newznab:', '<', $input);

        return @simplexml_load_string($input);
    }

    /**
     * Display error/error code.
     *
     * @param int $errorCode
     * @param string $errorText
     */
    public static function showApiError(int $errorCode = 900, string $errorText = ''): void
    {
        $errorHeader = 'HTTP 1.1 400 Bad Request';
        if ($errorText === '') {
            switch ($errorCode) {
                case 100:
                    $errorText = 'Incorrect user credentials';
                    $errorHeader = 'HTTP 1.1 401 Unauthorized';
                    break;
                case 101:
                    $errorText = 'Account suspended';
                    $errorHeader = 'HTTP 1.1 403 Forbidden';
                    break;
                case 102:
                    $errorText = 'Insufficient privileges/not authorized';
                    $errorHeader = 'HTTP 1.1 401 Unauthorized';
                    break;
                case 103:
                    $errorText = 'Registration denied';
                    $errorHeader = 'HTTP 1.1 403 Forbidden';
                    break;
                case 104:
                    $errorText = 'Registrations are closed';
                    $errorHeader = 'HTTP 1.1 403 Forbidden';
                    break;
                case 105:
                    $errorText = 'Invalid registration (Email Address Taken)';
                    $errorHeader = 'HTTP 1.1 403 Forbidden';
                    break;
                case 106:
                    $errorText = 'Invalid registration (Email Address Bad Format)';
                    $errorHeader = 'HTTP 1.1 403 Forbidden';
                    break;
                case 107:
                    $errorText = 'Registration Failed (Data error)';
                    $errorHeader = 'HTTP 1.1 400 Bad Request';
                    break;
                case 200:
                    $errorText = 'Missing parameter';
                    $errorHeader = 'HTTP 1.1 400 Bad Request';
                    break;
                case 201:
                    $errorText = 'Incorrect parameter';
                    $errorHeader = 'HTTP 1.1 400 Bad Request';
                    break;
                case 202:
                    $errorText = 'No such function';
                    $errorHeader = 'HTTP 1.1 404 Not Found';
                    break;
                case 203:
                    $errorText = 'Function not available';
                    $errorHeader = 'HTTP 1.1 400 Bad Request';
                    break;
                case 300:
                    $errorText = 'No such item';
                    $errorHeader = 'HTTP 1.1 404 Not Found';
                    break;
                case 500:
                    $errorText = 'Request limit reached';
                    $errorHeader = 'HTTP 1.1 429 Too Many Requests';
                    break;
                case 501:
                    $errorText = 'Download limit reached';
                    $errorHeader = 'HTTP 1.1 429 Too Many Requests';
                    break;
                case 910:
                    $errorText = 'API disabled';
                    $errorHeader = 'HTTP 1.1 401 Unauthorized';
                    break;
                default:
                    $errorText = 'Unknown error';
                    $errorHeader = 'HTTP 1.1 400 Bad Request';
                    break;
            }
        }

        $response =
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
            '<error code="'.$errorCode.'" description="'.$errorText."\"/>\n";
        header('Content-type: text/xml');
        header('Content-Length: '.\strlen($response));
        header('X-NNTmux: API ERROR ['.$errorCode.'] '.$errorText);
        header($errorHeader);

        exit($response);
    }

    /**
     * Simple function to reduce duplication in html string formatting.
     *
     * @param $string
     * @return string
     */
    public static function htmlfmt($string): string
    {
        return htmlspecialchars($string, ENT_QUOTES, 'utf-8');
    }

    /**
     * @param $tableName
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function getRange($tableName): LengthAwarePaginator
    {
        $range = DB::table($tableName);
        if ($tableName === 'xxxinfo') {
            $range->selectRaw('UNCOMPRESS(plot) AS plot');
        }

        return $range->orderByDesc('created_at')->paginate(config('nntmux.items_per_page'));
    }

    /**
     * @param $tableName
     * @return int
     */
    public static function getCount($tableName): int
    {
        $res = DB::table($tableName)->count('id');

        return $res === false ? 0 : $res;
    }
}

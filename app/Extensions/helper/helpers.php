<?php

use App\Models\Country as CountryModel;
use App\Models\Release;
use Blacklight\NZB;
use Blacklight\XXX;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use sspat\ESQuerySanitizer\Sanitizer;
use Symfony\Component\Process\Process;
use Zip as ZipStream;

if (! function_exists('getRawHtml')) {
    /**
     * @param  bool  $cookie
     * @return bool|mixed|string
     */
    function getRawHtml($url, $cookie = false, $postData = null)
    {
        // Check if this is an adult site that needs age verification
        $adultSites = [
            'adultdvdempire.com',
            'adultdvdmarketplace.com',
            'aebn.net',
            'hotmovies.com',
            'popporn.com',
        ];

        $isAdultSite = false;
        foreach ($adultSites as $site) {
            if (stripos($url, $site) !== false) {
                $isAdultSite = true;
                break;
            }
        }

        // For adult sites, use age verification manager if available
        if ($isAdultSite && class_exists('\App\Services\AdultProcessing\AgeVerificationManager')) {
            try {
                static $ageVerificationManager = null;
                if ($ageVerificationManager === null) {
                    $ageVerificationManager = new \App\Services\AdultProcessing\AgeVerificationManager;
                }

                if ($postData !== null) {
                    // Handle POST requests
                    $cookieJar = $ageVerificationManager->getCookieJar($url);
                    $client = new Client([
                        'cookies' => $cookieJar,
                        'headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'],
                    ]);

                    $response = $client->post($url, ['form_params' => $postData]);
                    $response = $response->getBody()->getContents();
                } else {
                    $response = $ageVerificationManager->makeRequest($url);
                }

                if ($response !== false) {
                    $jsonResponse = json_decode($response, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $jsonResponse;
                    }

                    return $response;
                }
            } catch (\Exception $e) {
                // Fall through to standard method
                if (function_exists('config') && config('app.debug') === true) {
                    Log::error('Age verification failed, falling back to standard method: '.$e->getMessage());
                }
            }
        }

        // Standard method for non-adult sites or if age verification fails
        $cookieJar = new CookieJar;
        $client = new Client(['headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246']]);
        if ($cookie !== false && $cookie !== null && $cookie !== '') {
            $cookie = $cookieJar->setCookie(SetCookie::fromString($cookie));
            $client = new Client(['cookies' => $cookie, 'headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246']]);
        }
        try {
            $response = $client->get($url)->getBody()->getContents();
            $jsonResponse = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $response = $jsonResponse;
            }
        } catch (RequestException $e) {
            if (function_exists('config') && config('app.debug') === true) {
                Log::error($e->getMessage());
            }
            $response = false;
        } catch (RuntimeException $e) {
            if (function_exists('config') && config('app.debug') === true) {
                Log::error($e->getMessage());
            }
            $response = false;
        }

        return $response;
    }
}

if (! function_exists('makeFieldLinks')) {
    /**
     * @return string
     *
     * @throws Exception
     */
    function makeFieldLinks($data, $field, $type)
    {
        $tmpArr = explode(', ', $data[$field]);
        $newArr = [];
        $i = 0;
        foreach ($tmpArr as $ta) {
            if (trim($ta) === '') {
                continue;
            }
            if ($type === 'xxx' && $field === 'genre') {
                $ta = (new XXX)->getGenres(true, $ta);
                $ta = $ta['title'] ?? '';
            }
            if ($i > 7) {
                break;
            }
            $newArr[] = '<a href="'.url('/'.ucfirst($type).'?'.$field.'='.urlencode($ta)).'" title="'.$ta.'">'.$ta.'</a>';
            $i++;
        }

        return implode(', ', $newArr);
    }
}

if (! function_exists('getUserBrowseOrder')) {
    /**
     * @param  string  $orderBy
     */
    function getUserBrowseOrder($orderBy): array
    {
        $order = ($orderBy === '' ? 'username_desc' : $orderBy);
        $orderArr = explode('_', $order);
        $orderField = match ($orderArr[0]) {
            'email' => 'email',
            'host' => 'host',
            'createdat' => 'created_at',
            'lastlogin' => 'lastlogin',
            'apiaccess' => 'apiaccess',
            'apirequests' => 'apirequests',
            'grabs' => 'grabs',
            'roles_id' => 'users_role_id',
            'rolechangedate' => 'rolechangedate',
            default => 'username',
        };
        $orderSort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'desc';

        return [$orderField, $orderSort];
    }
}

if (! function_exists('getUserBrowseOrdering')) {
    function getUserBrowseOrdering(): array
    {
        return [
            'username_asc',
            'username_desc',
            'email_asc',
            'email_desc',
            'host_asc',
            'host_desc',
            'createdat_asc',
            'createdat_desc',
            'lastlogin_asc',
            'lastlogin_desc',
            'apiaccess_asc',
            'apiaccess_desc',
            'apirequests_asc',
            'apirequests_desc',
            'grabs_asc',
            'grabs_desc',
            'role_asc',
            'role_desc',
            'rolechangedate_asc',
            'rolechangedate_desc',
            'verification_asc',
            'verification_desc',
        ];
    }
}

if (! function_exists('getSimilarName')) {
    /**
     * @param  string  $name
     */
    function getSimilarName($name): string
    {
        return implode(' ', \array_slice(str_word_count(str_replace(['.', '_', '-'], ' ', $name), 2), 0, 2));
    }
}

if (! function_exists('human_filesize')) {
    /**
     * @param  int  $decimals
     */
    function human_filesize($bytes, $decimals = 0): string
    {
        $size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $factor = floor((\strlen($bytes) - 1) / 3);

        return round(sprintf("%.{$decimals}f", $bytes / (1024 ** $factor)), $decimals).@$size[$factor];
    }
}

if (! function_exists('bcdechex')) {
    /**
     * @return string
     */
    function bcdechex($dec)
    {
        $hex = '';
        do {
            $last = bcmod($dec, 16);
            $hex = dechex($last).$hex;
            $dec = bcdiv(bcsub($dec, $last), 16);
        } while ($dec > 0);

        return $hex;
    }
}

if (! function_exists('runCmd')) {
    /**
     * Run CLI command.
     *
     *
     * @param  string  $command
     * @param  bool  $debug
     * @return string
     */
    function runCmd($command, $debug = false)
    {
        if ($debug) {
            echo '-Running Command: '.PHP_EOL.'   '.$command.PHP_EOL;
        }

        $process = Process::fromShellCommandline('exec '.$command);
        $process->setTimeout(1800);
        $process->run();
        $output = $process->getOutput();

        if ($debug) {
            echo '-Command Output: '.PHP_EOL.'   '.$output.PHP_EOL;
        }

        return $output;
    }
}

if (! function_exists('escapeString')) {

    function escapeString($string): string
    {
        return DB::connection()->getPdo()->quote($string);
    }
}

if (! function_exists('realDuration')) {

    function realDuration($milliseconds): string
    {
        $time = round($milliseconds / 1000);

        return sprintf('%02dh:%02dm:%02ds', floor($time / 3600), floor($time / 60 % 60), $time % 60);
    }
}

if (! function_exists('is_it_json')) {
    /**
     * @throws JsonException
     */
    function is_it_json($isIt): bool
    {
        if (is_array($isIt)) {
            return false;
        }
        json_decode($isIt, true, 512, JSON_THROW_ON_ERROR);

        return json_last_error() === JSON_ERROR_NONE;
    }
}

if (! function_exists('getStreamingZip')) {
    /**
     * @throws Exception
     */
    function getStreamingZip(array $guids = []): STS\ZipStream\Builder
    {
        $nzb = new NZB;
        $zipped = ZipStream::create(now()->format('Ymdhis').'.zip');
        foreach ($guids as $guid) {
            $nzbPath = $nzb->NZBPath($guid);
            if ($nzbPath) {
                $nzbContents = unzipGzipFile($nzbPath);
                if ($nzbContents) {
                    $filename = $guid;
                    $r = Release::query()->where('guid', $guid)->first();
                    if ($r) {
                        $filename = $r['searchname'];
                    }
                    $zipped->addRaw($nzbContents, $filename.'.nzb');
                }
            }
        }

        return $zipped;
    }
}

if (! function_exists('release_flag')) {
    // Function inspired by c0r3@newznabforums adds country flags on the browse page.
    /**
     * @param  string  $text  Text to match against.
     * @param  string  $page  Type of page. browse or search.
     */
    function release_flag(string $text, string $page): bool|string
    {
        $code = $language = '';

        switch (true) {
            case stripos($text, 'Arabic') !== false:
                $code = 'PK';
                $language = 'Arabic';
                break;
            case stripos($text, 'Cantonese') !== false:
                $code = 'TW';
                $language = 'Cantonese';
                break;
            case preg_match('/Chinese|Mandarin|\bc[hn]\b/i', $text):
                $code = 'CN';
                $language = 'Chinese';
                break;
            case preg_match('/\bCzech\b/i', $text):
                $code = 'CZ';
                $language = 'Czech';
                break;
            case stripos($text, 'Danish') !== false:
                $code = 'DK';
                $language = 'Danish';
                break;
            case stripos($text, 'Finnish') !== false:
                $code = 'FI';
                $language = 'Finnish';
                break;
            case preg_match('/Flemish|\b(Dutch|nl)\b|NlSub/i', $text):
                $code = 'NL';
                $language = 'Dutch';
                break;
            case preg_match('/French|Vostfr|Multi/i', $text):
                $code = 'FR';
                $language = 'French';
                break;
            case preg_match('/German(bed)?|\bger\b/i', $text):
                $code = 'DE';
                $language = 'German';
                break;
            case preg_match('/\bGreek\b/i', $text):
                $code = 'GR';
                $language = 'Greek';
                break;
            case preg_match('/Hebrew|Yiddish/i', $text):
                $code = 'IL';
                $language = 'Hebrew';
                break;
            case preg_match('/\bHindi\b/i', $text):
                $code = 'IN';
                $language = 'Hindi';
                break;
            case preg_match('/Hungarian|\bhun\b/i', $text):
                $code = 'HU';
                $language = 'Hungarian';
                break;
            case preg_match('/Italian|\bita\b/i', $text):
                $code = 'IT';
                $language = 'Italian';
                break;
            case preg_match('/Japanese|\bjp\b/i', $text):
                $code = 'JP';
                $language = 'Japanese';
                break;
            case preg_match('/Korean|\bkr\b/i', $text):
                $code = 'KR';
                $language = 'Korean';
                break;
            case stripos($text, 'Norwegian') !== false:
                $code = 'NO';
                $language = 'Norwegian';
                break;
            case stripos($text, 'Polish') !== false:
                $code = 'PL';
                $language = 'Polish';
                break;
            case stripos($text, 'Portuguese') !== false:
                $code = 'PT';
                $language = 'Portugese';
                break;
            case stripos($text, 'Romanian') !== false:
                $code = 'RO';
                $language = 'Romanian';
                break;
            case stripos($text, 'Spanish') !== false:
                $code = 'ES';
                $language = 'Spanish';
                break;
            case preg_match('/Swe(dish|sub)/i', $text):
                $code = 'SE';
                $language = 'Swedish';
                break;
            case preg_match('/Tagalog|Filipino/i', $text):
                $code = 'PH';
                $language = 'Tagalog|Filipino';
                break;
            case preg_match('/\bThai\b/i', $text):
                $code = 'TH';
                $language = 'Thai';
                break;
            case stripos($text, 'Turkish') !== false:
                $code = 'TR';
                $language = 'Turkish';
                break;
            case stripos($text, 'Russian') !== false:
                $code = 'RU';
                $language = 'Russian';
                break;
            case stripos($text, 'Vietnamese') !== false:
                $code = 'VN';
                $language = 'Vietnamese';
                break;
        }

        if ($code !== '' && $page === 'browse') {
            return '<img title="'.$language.'" alt="'.$language.'" src="'.asset('/assets/images/flags/'.$code.'.png').'"/>';
        }

        if ($page === 'search') {
            if ($code === '') {
                return false;
            }

            return $code;
        }

        return '';
    }
}

if (! function_exists('getReleaseCover')) {
    /**
     * Get the cover image URL for a release based on its type and ID
     *
     * @param  object|array  $release  The release object or array
     * @return string The cover image URL or placeholder if no cover exists
     */
    function getReleaseCover($release): string
    {
        $coverType = null;
        $coverId = null;

        // Helper function to get value from object or array
        $getValue = function ($data, $key) {
            if (is_array($data)) {
                return $data[$key] ?? null;
            } elseif (is_object($data)) {
                return $data->$key ?? null;
            }

            return null;
        };

        // Determine cover type and ID based on category
        $imdbid = $getValue($release, 'imdbid');
        $musicinfo_id = $getValue($release, 'musicinfo_id');
        $consoleinfo_id = $getValue($release, 'consoleinfo_id');
        $bookinfo_id = $getValue($release, 'bookinfo_id');
        $gamesinfo_id = $getValue($release, 'gamesinfo_id');
        $xxxinfo_id = $getValue($release, 'xxxinfo_id');
        $anidbid = $getValue($release, 'anidbid');

        if (! empty($imdbid) && $imdbid > 0) {
            $coverType = 'movies';
            $coverId = str_pad($imdbid, 7, '0', STR_PAD_LEFT);
        } elseif (! empty($musicinfo_id)) {
            $coverType = 'music';
            $coverId = $musicinfo_id;
        } elseif (! empty($consoleinfo_id)) {
            $coverType = 'console';
            $coverId = $consoleinfo_id;
        } elseif (! empty($bookinfo_id)) {
            $coverType = 'book';
            $coverId = $bookinfo_id;
        } elseif (! empty($gamesinfo_id)) {
            $coverType = 'games';
            $coverId = $gamesinfo_id;
        } elseif (! empty($xxxinfo_id)) {
            $coverType = 'xxx';
            $coverId = $xxxinfo_id;
        } elseif (! empty($anidbid) && $anidbid > 0) {
            $coverType = 'anime';
            $coverId = $anidbid;
        }

        // Return the cover URL if we have a type and ID
        // The CoverController will handle serving the file or returning a placeholder
        if ($coverType && $coverId) {
            return url("/covers/{$coverType}/{$coverId}-cover.jpg");
        }

        // Return placeholder image if no cover type/ID found
        return asset('assets/images/no-cover.png');
    }
}

if (! function_exists('sanitize')) {
    function sanitize(array|string $phrases, array $doNotSanitize = []): string
    {
        if (! is_array($phrases)) {
            $wordArray = explode(' ', str_replace('.', ' ', $phrases));
        } else {
            $wordArray = $phrases;
        }

        $keywords = [];
        $tempWords = [];
        foreach ($wordArray as $words) {
            $words = preg_split('/\s+/', $words);
            foreach ($words as $st) {
                if (Str::startsWith($st, ['!', '+', '-', '?', '*']) && Str::length($st) > 1 && ! preg_match('/([!+?\-*]){2,}/', $st)) {
                    $str = $st;
                } elseif (Str::endsWith($st, ['+', '-', '?', '*']) && Str::length($st) > 1 && ! preg_match('/([!+?\-*]){2,}/', $st)) {
                    $str = $st;
                } else {
                    $str = Sanitizer::escape($st, $doNotSanitize);
                }
                $tempWords[] = $str;
            }

            $keywords = $tempWords;
        }

        return implode(' ', $keywords);
    }
}

if (! function_exists('formatBytes')) {
    /**
     * Format bytes into human-readable file size.
     *
     * @param  int|float|null  $bytes
     */
    function formatBytes($bytes = 0): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max((int) $bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2).' '.$units[$pow];
    }
}

if (! function_exists('csp_nonce')) {
    /**
     * Generate a CSP nonce for inline scripts
     * This should be stored in the request and reused across the request lifecycle
     */
    function csp_nonce(): string
    {
        static $nonce = null;

        if ($nonce === null) {
            $nonce = base64_encode(random_bytes(16));
        }

        return $nonce;
    }
}

if (! function_exists('userDate')) {
    /**
     * Format a date/time string according to the authenticated user's timezone
     *
     * @param  string|null  $date  The date to format
     * @param  string  $format  The format string (default: 'M d, Y H:i')
     * @return string The formatted date in user's timezone
     */
    function userDate(?string $date, string $format = 'M d, Y H:i'): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            // Parse the date in the app's timezone (which should be UTC)
            // If dates in DB are stored in server timezone, they'll be parsed correctly
            $appTimezone = config('app.timezone', 'UTC');
            $carbon = \Illuminate\Support\Carbon::parse($date, $appTimezone);

            // If user is authenticated and has a timezone set, convert to it
            if (\Illuminate\Support\Facades\Auth::check() && \Illuminate\Support\Facades\Auth::user()->timezone) {
                $carbon->setTimezone(\Illuminate\Support\Facades\Auth::user()->timezone);
            }

            return $carbon->format($format);
        } catch (\Exception $e) {
            return $date;
        }
    }
}

if (! function_exists('userDateDiffForHumans')) {
    /**
     * Format a date/time string as a human-readable diff according to the authenticated user's timezone
     *
     * @param  string|null  $date  The date to format
     * @return string The formatted date diff in user's timezone
     */
    function userDateDiffForHumans(?string $date): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            // Parse the date in the app's timezone (which should be UTC)
            // If dates in DB are stored in server timezone, they'll be parsed correctly
            $appTimezone = config('app.timezone', 'UTC');
            $carbon = \Illuminate\Support\Carbon::parse($date, $appTimezone);

            // If user is authenticated and has a timezone set, convert to it
            if (\Illuminate\Support\Facades\Auth::check() && \Illuminate\Support\Facades\Auth::user()->timezone) {
                $carbon->setTimezone(\Illuminate\Support\Facades\Auth::user()->timezone);
            }

            return $carbon->diffForHumans();
        } catch (\Exception $e) {
            return $date;
        }
    }
}

if (! function_exists('getAvailableTimezones')) {
    /**
     * Get a list of available timezones grouped by region
     *
     * @return array Array of timezones grouped by region
     */
    function getAvailableTimezones(): array
    {
        $timezones = [];
        $regions = [
            'Africa' => \DateTimeZone::AFRICA,
            'America' => \DateTimeZone::AMERICA,
            'Antarctica' => \DateTimeZone::ANTARCTICA,
            'Arctic' => \DateTimeZone::ARCTIC,
            'Asia' => \DateTimeZone::ASIA,
            'Atlantic' => \DateTimeZone::ATLANTIC,
            'Australia' => \DateTimeZone::AUSTRALIA,
            'Europe' => \DateTimeZone::EUROPE,
            'Indian' => \DateTimeZone::INDIAN,
            'Pacific' => \DateTimeZone::PACIFIC,
        ];

        foreach ($regions as $name => $region) {
            $timezones[$name] = \DateTimeZone::listIdentifiers($region);
        }

        return $timezones;
    }
}

if (! function_exists('countryCode')) {
    /**
     * Get a country code for a country name.
     *
     * @return mixed
     */
    function countryCode(string $country)
    {
        if (\strlen($country) > 2) {
            $code = CountryModel::whereFullName($country)->orWhere('name', $country)->first(['iso_3166_2']);
            if ($code !== null && isset($code['iso_3166_2'])) {
                return $code['iso_3166_2'];
            }
        }

        return '';
    }
}

if (! function_exists('unzipGzipFile')) {
    /**
     * Unzip a gzip file, return the output. Return false on error / empty.
     *
     * @return bool|string
     */
    function unzipGzipFile(string $filePath)
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
}

if (! function_exists('streamSslContextOptions')) {
    /**
     * Creates an array to be used with stream_context_create() to verify openssl certificates
     * when connecting to a tls or ssl connection when using stream functions (fopen/file_get_contents/etc).
     *
     * @param  bool  $forceIgnore  Force ignoring of verification (useful for self-signed certs in development).
     * @return array Stream context options for SSL/TLS connections
     */
    function streamSslContextOptions(bool $forceIgnore = false): array
    {
        $cafile = config('nntmux_ssl.ssl_cafile', '');
        $capath = config('nntmux_ssl.ssl_capath', '');
        $hasCustomCerts = $cafile !== '' || $capath !== '';

        // Base options - either insecure (no certs configured) or configured
        $options = [
            'verify_peer' => ! $forceIgnore && $hasCustomCerts && config('nntmux_ssl.ssl_verify_peer', false),
            'verify_peer_name' => ! $forceIgnore && $hasCustomCerts && config('nntmux_ssl.ssl_verify_host', false),
            'allow_self_signed' => $forceIgnore ? true : config('nntmux_ssl.ssl_allow_self_signed', true),
        ];

        // Add certificate paths if configured
        if ($hasCustomCerts && ! $forceIgnore) {
            if ($cafile !== '') {
                $options['cafile'] = $cafile;
            }
            if ($capath !== '') {
                $options['capath'] = $capath;
            }
        }

        // Additional security options for modern TLS
        $options['crypto_method'] = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        $options['disable_compression'] = true; // Prevent CRIME attacks
        $options['SNI_enabled'] = true; // Enable Server Name Indication

        // If we set the transport to tls and the server falls back to ssl,
        // the context options would be for tls and would not apply to ssl,
        // so set both tls and ssl context in case the server does not support tls.
        return ['tls' => $options, 'ssl' => $options];
    }
}

if (! function_exists('getCoverURL')) {
    function getCoverURL(array $options = []): string
    {
        $defaults = [
            'id' => null,
            'suffix' => '-cover.jpg',
            'type' => '',
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
            $fileSpec = file_exists(storage_path('covers/').$fileSpec) ? $fileSpec :
                sprintf($fileSpecTemplate, $options['type'], 'no', $options['suffix']);
        }

        return $fileSpec;
    }
}

if (! function_exists('fileInfo')) {
    /**
     * Return file type/info using magic numbers.
     * Try using `file` program where available, fallback to using PHP's finfo class.
     *
     * @param  string  $path  Path to the file / folder to check.
     * @return string File info. Empty string on failure.
     *
     * @throws \Exception
     */
    function fileInfo(string $path): string
    {
        $magicPath = config('nntmux_settings.magic_file_path');
        if ($magicPath !== null && \Illuminate\Support\Facades\Process::run('which file')->successful()) {
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
}

if (! function_exists('cp437toUTF')) {
    /**
     * Convert Code page 437 chars to UTF.
     */
    function cp437toUTF(string $string): string
    {
        return iconv('CP437', 'UTF-8//IGNORE//TRANSLIT', $string);
    }
}

if (! function_exists('imdb_trailers')) {
    /**
     * Fetches an embeddable video to a IMDB trailer from http://www.traileraddict.com.
     */
    function imdb_trailers($imdbID): string
    {
        $xml = getRawHtml('https://api.traileraddict.com/?imdb='.$imdbID);
        if ($xml !== false && preg_match('#(v\.traileraddict\.com/\d+)#i', $xml, $html)) {
            return 'https://'.$html[1];
        }

        return '';
    }
}

if (! function_exists('showApiError')) {
    function showApiError(int $errorCode = 900, string $errorText = '')
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

        return response($response)->header('Content-type', 'text/xml')->header('Content-Length', strlen($response))->header('X-NNTmux', 'API ERROR ['.$errorCode.'] '.$errorText)->header('HTTP/1.1', $errorHeader);
    }
}

if (! function_exists('getRange')) {
    function getRange($tableName): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $range = \Illuminate\Support\Facades\DB::table($tableName);
        if ($tableName === 'xxxinfo') {
            $range->selectRaw('UNCOMPRESS(plot) AS plot');
        }

        return $range->orderByDesc('created_at')->paginate(config('nntmux.items_per_page'));
    }
}

if (! function_exists('isValidNewznabNzb')) {
    /**
     * Validate if the content is a valid Newznab NZB file.
     *
     * @param  string  $content  The NZB file content
     * @return bool True if valid NZB, false otherwise
     */
    function isValidNewznabNzb(string $content): bool
    {
        // Check if content starts with valid XML declaration and contains NZB namespace
        if (empty($content)) {
            return false;
        }

        // Try to load as XML
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($content);

        if ($xml === false) {
            libxml_clear_errors();
            return false;
        }

        // Check for NZB namespace or nzb root element
        $namespaces = $xml->getNamespaces(true);
        $hasNzbNamespace = isset($namespaces['']) && str_contains($namespaces[''], 'nzb');
        $isNzbRoot = strtolower($xml->getName()) === 'nzb';

        libxml_clear_errors();

        return $hasNzbNamespace || $isNzbRoot;
    }
}

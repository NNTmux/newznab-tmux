<?php

namespace App\Services\AdultProcessing\Pipes;

use App\Services\AdultProcessing\AdultProcessingPassable;
use App\Services\AdultProcessing\AdultProcessingResult;
use App\Services\AdultProcessing\AgeVerificationManager;
use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use voku\helper\HtmlDomParser;

/**
 * Base class for adult movie processing pipe handlers.
 *
 * Each pipe is responsible for processing releases through a specific adult site provider.
 *
 * Note: This class intentionally uses lazy loading for HtmlDomParser to avoid
 * serialization issues with DOMDocument when using Laravel's Concurrency facade.
 */
abstract class AbstractAdultProviderPipe
{
    protected int $priority = 50;
    protected bool $echoOutput = true;
    protected ?HtmlDomParser $html = null;
    protected ?string $cookie = null;

    /**
     * Minimum similarity threshold for matching (percentage).
     */
    protected float $minimumSimilarity = 90.0;

    /**
     * HTTP client for making requests.
     */
    protected ?Client $httpClient = null;

    /**
     * Cookie jar for maintaining session cookies.
     */
    protected CookieJar|FileCookieJar|null $cookieJar = null;

    /**
     * Age verification manager for handling site-specific cookies.
     */
    protected ?AgeVerificationManager $ageVerificationManager = null;

    /**
     * Maximum number of retry attempts for failed requests.
     */
    protected int $maxRetries = 3;

    /**
     * Delay between retries in milliseconds.
     */
    protected int $retryDelay = 1000;

    /**
     * Rate limit delay between requests in milliseconds.
     */
    protected int $rateLimitDelay = 500;

    /**
     * Last request timestamp for rate limiting.
     */
    protected static array $lastRequestTime = [];

    /**
     * Cache duration for search results in minutes.
     */
    protected int $cacheDuration = 60;

    /**
     * Whether to use caching for this provider.
     */
    protected bool $useCache = true;

    public function __construct()
    {
        // Lazy load HtmlDomParser to avoid serialization issues
    }

    /**
     * Get the HtmlDomParser instance (lazy loaded).
     */
    protected function getHtmlParser(): HtmlDomParser
    {
        if ($this->html === null) {
            $this->html = new HtmlDomParser();
        }
        return $this->html;
    }

    /**
     * Handle the adult movie processing request.
     */
    public function handle(AdultProcessingPassable $passable, Closure $next): AdultProcessingPassable
    {
        // If we already have a match, skip processing
        if ($passable->shouldStopProcessing()) {
            return $next($passable);
        }

        // Set the cookie from passable
        $this->cookie = $passable->getCookie();

        // Skip if this provider shouldn't process
        if ($this->shouldSkip($passable)) {
            $passable->updateResult(
                AdultProcessingResult::skipped('Provider skipped', $this->getName()),
                $this->getName()
            );
            return $next($passable);
        }

        // Output processing message
        if ($this->echoOutput) {
            $this->getColorCli()->info('Checking '.$this->getDisplayName().' for movie info');
        }

        try {
            // Apply rate limiting
            $this->applyRateLimit();

            // Attempt to process with this provider
            $result = $this->process($passable);

            // Update the result
            $passable->updateResult($result, $this->getName());
        } catch (\Exception $e) {
            Log::error('Adult provider '.$this->getName().' failed: '.$e->getMessage(), [
                'provider' => $this->getName(),
                'title' => $passable->getCleanTitle(),
                'exception' => get_class($e),
            ]);

            $passable->updateResult(
                AdultProcessingResult::failed($e->getMessage(), $this->getName()),
                $this->getName()
            );
        }

        return $next($passable);
    }

    /**
     * Apply rate limiting between requests to the same provider.
     */
    protected function applyRateLimit(): void
    {
        $providerName = $this->getName();
        $now = microtime(true) * 1000;

        if (isset(self::$lastRequestTime[$providerName])) {
            $elapsed = $now - self::$lastRequestTime[$providerName];
            if ($elapsed < $this->rateLimitDelay) {
                usleep((int)(($this->rateLimitDelay - $elapsed) * 1000));
            }
        }

        self::$lastRequestTime[$providerName] = microtime(true) * 1000;
    }

    /**
     * Get the priority of this provider (lower = higher priority).
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Get the internal name of this provider.
     */
    abstract public function getName(): string;

    /**
     * Get the display name for user-facing output.
     */
    abstract public function getDisplayName(): string;

    /**
     * Get the base URL for the provider.
     */
    abstract protected function getBaseUrl(): string;

    /**
     * Attempt to process the movie through this provider.
     */
    abstract protected function process(AdultProcessingPassable $passable): AdultProcessingResult;

    /**
     * Search for a movie on this provider.
     *
     * @return array|false Returns array with 'title' and 'url' keys on success, false on failure
     */
    abstract protected function search(string $movie): array|false;

    /**
     * Get all movie information from the provider.
     */
    abstract protected function getMovieInfo(): array|false;

    /**
     * Check if this provider should be skipped for the given passable.
     */
    protected function shouldSkip(AdultProcessingPassable $passable): bool
    {
        return empty($passable->getCleanTitle());
    }

    /**
     * Set echo output flag.
     */
    public function setEchoOutput(bool $echo): self
    {
        $this->echoOutput = $echo;
        return $this;
    }

    /**
     * Get cached search result if available.
     */
    protected function getCachedSearch(string $movie): array|false|null
    {
        if (!$this->useCache) {
            return null;
        }

        $cacheKey = 'adult_search_' . $this->getName() . '_' . md5(strtolower($movie));
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            if ($this->echoOutput) {
                $this->getColorCli()->info('Using cached result for: ' . $movie);
            }
            return $cached;
        }

        return null;
    }

    /**
     * Cache a search result.
     */
    protected function cacheSearchResult(string $movie, array|false $result): void
    {
        if (!$this->useCache) {
            return;
        }

        $cacheKey = 'adult_search_' . $this->getName() . '_' . md5(strtolower($movie));
        Cache::put($cacheKey, $result, now()->addMinutes($this->cacheDuration));
    }

    /**
     * Fetch raw HTML from a URL with retry support.
     */
    protected function fetchHtml(string $url, ?string $cookie = null, ?array $postData = null): string|false
    {
        $attempt = 0;
        $lastException = null;
        $ageVerificationAttempted = false;

        while ($attempt < $this->maxRetries) {
            try {
                $attempt++;
                $client = $this->getHttpClient();

                $options = [
                    'headers' => $this->getDefaultHeaders(),
                ];

                // Add custom cookie if provided
                if ($cookie) {
                    $options['headers']['Cookie'] = $cookie;
                }

                // Handle POST data
                if ($postData !== null) {
                    $options['form_params'] = $postData;
                    $response = $client->post($url, $options);
                } else {
                    $response = $client->get($url, $options);
                }

                $body = $response->getBody()->getContents();

                // Check if we were redirected to an age verification page
                $finalUrl = $response->getHeaderLine('X-Guzzle-Redirect-History');
                if (empty($finalUrl)) {
                    // Use the effective URI if available
                    $effectiveUri = $response->getHeader('X-Guzzle-Redirect-History');
                    if (!empty($effectiveUri)) {
                        $finalUrl = end($effectiveUri);
                    }
                }

                // Check for common error pages
                if ($this->isErrorPage($body)) {
                    Log::warning('Received error page from ' . $this->getName() . ': ' . $url);
                    if ($attempt < $this->maxRetries) {
                        usleep($this->retryDelay * 1000);
                        continue;
                    }
                    return false;
                }

                // Check for age verification requirement
                if ($this->requiresAgeVerification($body)) {
                    // If we haven't tried age verification yet, refresh cookies and retry
                    if (!$ageVerificationAttempted) {
                        $ageVerificationAttempted = true;

                        // Refresh cookies using the manager
                        $this->getAgeVerificationManager()->refreshCookies($this->getBaseUrl());

                        // Reset HTTP client to pick up new cookies
                        $this->httpClient = null;
                        $this->cookieJar = null;

                        Log::info('Refreshed age verification cookies for ' . $this->getName() . ', retrying...');
                        continue;
                    }

                    $body = $this->handleAgeVerification($url, $body);
                    if ($body === false) {
                        return false;
                    }
                }

                return $body;

            } catch (ConnectException $e) {
                $lastException = $e;
                Log::warning('Connection failed for ' . $this->getName() . ' (attempt ' . $attempt . '): ' . $e->getMessage());

                if ($attempt < $this->maxRetries) {
                    usleep($this->retryDelay * 1000 * $attempt); // Exponential backoff
                }
            } catch (RequestException $e) {
                $lastException = $e;
                $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;

                // Don't retry on 4xx client errors (except 429 rate limit)
                if ($statusCode >= 400 && $statusCode < 500 && $statusCode !== 429) {
                    Log::error('HTTP ' . $statusCode . ' for ' . $this->getName() . ': ' . $url);
                    return false;
                }

                Log::warning('Request failed for ' . $this->getName() . ' (attempt ' . $attempt . '): ' . $e->getMessage());

                if ($attempt < $this->maxRetries) {
                    // Longer delay for rate limit errors
                    $delay = $statusCode === 429 ? $this->retryDelay * 5 : $this->retryDelay * $attempt;
                    usleep($delay * 1000);
                }
            } catch (\Exception $e) {
                $lastException = $e;
                Log::error('Unexpected error for ' . $this->getName() . ': ' . $e->getMessage());

                if ($attempt < $this->maxRetries) {
                    usleep($this->retryDelay * 1000);
                }
            }
        }

        if ($lastException) {
            Log::error('All retry attempts failed for ' . $this->getName() . ': ' . $lastException->getMessage());
        }

        return false;
    }

    /**
     * Get default HTTP headers.
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'User-Agent' => $this->getRandomUserAgent(),
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
            'Upgrade-Insecure-Requests' => '1',
        ];
    }

    /**
     * Get a random user agent string.
     */
    protected function getRandomUserAgent(): string
    {
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
        ];

        return $userAgents[array_rand($userAgents)];
    }

    /**
     * Check if the response is an error page.
     */
    protected function isErrorPage(string $html): bool
    {
        $errorPatterns = [
            'Access Denied',
            'Service Unavailable',
            '503 Service',
            '502 Bad Gateway',
            'temporarily unavailable',
            'maintenance mode',
            'rate limit exceeded',
        ];

        foreach ($errorPatterns as $pattern) {
            if (stripos($html, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the page requires age verification.
     */
    protected function requiresAgeVerification(string $html): bool
    {
        // First check if this looks like a proper content page
        // Content pages have actual movie info, cast, etc.
        $contentIndicators = [
            '<title>.*?DVD.*?</title>',
            'product-info',
            'movie-details',
            'cast-list',
            'genre-list',
            '"@type":\s*"Movie"',
            '"@type":\s*"Product"',
        ];

        foreach ($contentIndicators as $pattern) {
            // Note: Using # as delimiter to avoid issues with / in patterns like </title>
            if (preg_match('#' . $pattern . '#is', $html)) {
                return false; // This is a content page, not an age verification page
            }
        }

        // Check for short page that might just be a redirect/age gate
        if (strlen($html) < 500) {
            return true; // Very short response likely means we got redirected
        }

        // Now check for explicit age verification indicators
        $agePatterns = [
            'age verification',
            'are you 18',
            'are you over 18',
            'confirm your age',
            'enter your age',
            'must be 18',
            'age-gate',
            'ageGate',
            'AgeConfirmation', // PopPorn specific
            'ageConfirmationButton', // ADE specific
            'age-confirmation', // Generic
            'verify your age',
            'adult content warning',
            'I am 18 or older',
            'I am over 18',
            'this site contains adult',
        ];

        // Count how many patterns match - if multiple match on a short page, it's likely age verification
        $matchCount = 0;
        foreach ($agePatterns as $pattern) {
            if (stripos($html, $pattern) !== false) {
                $matchCount++;
                // If the page is relatively short and has an age pattern, it's probably an age gate
                if (strlen($html) < 10000) {
                    return true;
                }
            }
        }

        // If multiple patterns match, it's likely an age verification page
        return $matchCount >= 2;
    }

    /**
     * Handle age verification requirement.
     */
    protected function handleAgeVerification(string $url, string $html): string|false
    {
        // First, try to use site-specific cookies from the AgeVerificationManager
        $manager = $this->getAgeVerificationManager();
        $domain = parse_url($this->getBaseUrl(), PHP_URL_HOST);
        $domain = preg_replace('/^www\./', '', $domain);

        // Re-initialize cookies from the manager and retry
        if ($this->cookieJar) {
            // The manager already handles setting cookies, but let's ensure they're fresh
            Log::info('Attempting to handle age verification for ' . $this->getName() . ' with domain: ' . $domain);
        }

        // Try to find and submit age verification form
        $this->getHtmlParser()->loadHtml($html);

        // Look for common age verification form patterns
        $forms = $this->getHtmlParser()->find('form');
        foreach ($forms as $form) {
            $action = $form->action ?? '';
            $method = strtoupper($form->method ?? 'GET');

            // Check if this looks like an age verification form
            $formHtml = $form->innerHtml ?? '';
            if (stripos($formHtml, 'age') !== false || stripos($formHtml, '18') !== false ||
                stripos($formHtml, 'adult') !== false || stripos($formHtml, 'enter') !== false ||
                stripos($formHtml, 'confirm') !== false) {
                // Try to submit the form with age confirmation
                $postData = $this->extractAgeVerificationFormData($form);

                if (!empty($postData)) {
                    $submitUrl = $action;
                    if (!str_starts_with($submitUrl, 'http')) {
                        $submitUrl = $this->getBaseUrl() . '/' . ltrim($submitUrl, '/');
                    }

                    // Submit the age verification
                    try {
                        $response = $this->getHttpClient()->request($method, $submitUrl, [
                            'form_params' => $postData,
                            'headers' => $this->getDefaultHeaders(),
                        ]);

                        $body = $response->getBody()->getContents();

                        // Check if we still get age verification after submit
                        if (!$this->requiresAgeVerification($body)) {
                            return $body;
                        }
                    } catch (\Exception $e) {
                        Log::warning('Age verification submission failed for ' . $this->getName() . ': ' . $e->getMessage());
                    }
                }
            }
        }

        // Look for JavaScript-based age verification (click to enter)
        if (preg_match('/onclick\s*=\s*["\'].*?(enter|agree|confirm|over18|adult).*?["\']/i', $html) ||
            preg_match('/<a[^>]*href\s*=\s*["\']([^"\']*)["\'][^>]*>(Enter|I am over 18|Agree|Enter Site|I Agree)/i', $html, $matches)) {
            // Try to follow the link or simulate the click
            if (!empty($matches[1])) {
                $enterUrl = $matches[1];
                if (!str_starts_with($enterUrl, 'http')) {
                    $enterUrl = $this->getBaseUrl() . '/' . ltrim($enterUrl, '/');
                }

                try {
                    $response = $this->getHttpClient()->get($enterUrl, [
                        'headers' => $this->getDefaultHeaders(),
                    ]);
                    $body = $response->getBody()->getContents();

                    if (!$this->requiresAgeVerification($body)) {
                        return $body;
                    }
                } catch (\Exception $e) {
                    Log::warning('Age verification link follow failed for ' . $this->getName() . ': ' . $e->getMessage());
                }
            }
        }

        // If all else fails, try to just refetch the original URL
        // (sometimes the cookies from previous attempts work)
        try {
            $response = $this->getHttpClient()->get($url, [
                'headers' => $this->getDefaultHeaders(),
            ]);
            $body = $response->getBody()->getContents();

            if (!$this->requiresAgeVerification($body)) {
                return $body;
            }
        } catch (\Exception $e) {
            Log::warning('Age verification retry failed for ' . $this->getName() . ': ' . $e->getMessage());
        }

        // If we couldn't handle age verification, log and return false
        Log::warning('Could not handle age verification for ' . $this->getName() . ': ' . $url);
        return false;
    }

    /**
     * Extract form data for age verification submission.
     */
    protected function extractAgeVerificationFormData($form): array
    {
        $data = [];

        // Get all input fields
        foreach ($form->find('input') as $input) {
            $name = $input->name ?? '';
            $type = strtolower($input->type ?? 'text');
            $value = $input->value ?? '';

            if (empty($name)) {
                continue;
            }

            // Handle different input types
            switch ($type) {
                case 'hidden':
                    $data[$name] = $value;
                    break;
                case 'checkbox':
                    // Usually age verification checkboxes need to be checked
                    if (stripos($name, 'age') !== false || stripos($name, 'agree') !== false || stripos($name, 'confirm') !== false) {
                        $data[$name] = $value ?: '1';
                    }
                    break;
                case 'submit':
                    // Include submit button value if it has a name
                    if (!empty($value)) {
                        $data[$name] = $value;
                    }
                    break;
                default:
                    // For text inputs that might be age/birthdate
                    if (stripos($name, 'age') !== false || stripos($name, 'year') !== false) {
                        $data[$name] = '1990'; // Default to a valid birth year
                    }
            }
        }

        // Handle select elements (for birthdate selection)
        foreach ($form->find('select') as $select) {
            $name = $select->name ?? '';
            if (empty($name)) {
                continue;
            }

            if (stripos($name, 'year') !== false) {
                $data[$name] = '1990';
            } elseif (stripos($name, 'month') !== false) {
                $data[$name] = '01';
            } elseif (stripos($name, 'day') !== false) {
                $data[$name] = '01';
            }
        }

        return $data;
    }

    /**
     * Get the age verification manager instance.
     */
    protected function getAgeVerificationManager(): AgeVerificationManager
    {
        if ($this->ageVerificationManager === null) {
            $this->ageVerificationManager = new AgeVerificationManager();
        }
        return $this->ageVerificationManager;
    }

    /**
     * Get or create HTTP client with retry middleware.
     */
    protected function getHttpClient(): Client
    {
        if ($this->httpClient === null) {
            // Use the AgeVerificationManager to get proper cookie jar with age verification cookies
            $this->cookieJar = $this->getAgeVerificationManager()->getCookieJar($this->getBaseUrl());

            $this->httpClient = new Client([
                'timeout' => 30,
                'connect_timeout' => 15,
                'verify' => false,
                'cookies' => $this->cookieJar,
                'allow_redirects' => [
                    'max' => 5,
                    'strict' => false,
                    'referer' => true,
                    'track_redirects' => true,
                ],
                'http_errors' => true,
            ]);
        }

        return $this->httpClient;
    }

    /**
     * Calculate similarity between two strings using multiple algorithms.
     */
    protected function calculateSimilarity(string $searchTerm, string $resultTitle): float
    {
        // Clean up both strings for comparison
        $cleanSearch = $this->cleanTitleForComparison($searchTerm);
        $cleanResult = $this->cleanTitleForComparison($resultTitle);

        // Calculate similarity using multiple methods
        similar_text($cleanSearch, $cleanResult, $similarTextPercent);

        // Also calculate Levenshtein distance based similarity
        $maxLen = max(strlen($cleanSearch), strlen($cleanResult));
        if ($maxLen > 0) {
            $levenshtein = levenshtein($cleanSearch, $cleanResult);
            $levenshteinPercent = (1 - ($levenshtein / $maxLen)) * 100;
        } else {
            $levenshteinPercent = 0;
        }

        // Use the higher of the two similarity scores
        return max($similarTextPercent, $levenshteinPercent);
    }

    /**
     * Clean a title for comparison purposes.
     */
    protected function cleanTitleForComparison(string $title): string
    {
        $title = strtolower($title);
        $title = str_replace('/XXX/', '', $title);

        // Remove common adult movie prefixes/suffixes
        $removePatterns = [
            '/\b(xxx|adult|porn|erotic|hd|4k|1080p|720p|dvdrip|webrip|bluray)\b/i',
            '/\(.*?\)/',
            '/\[.*?\]/',
            '/[._-]+/',
            '/\s+/',
        ];

        foreach ($removePatterns as $pattern) {
            $title = preg_replace($pattern, ' ', $title);
        }

        return trim($title);
    }

    /**
     * Extract movie information from the loaded HTML.
     */
    protected function extractCovers(): array
    {
        return [];
    }

    protected function extractSynopsis(): array
    {
        return [];
    }

    protected function extractCast(): array
    {
        return [];
    }

    protected function extractGenres(): array
    {
        return [];
    }

    protected function extractProductInfo(bool $extras = false): array
    {
        return [];
    }

    protected function extractTrailers(): array
    {
        return [];
    }

    /**
     * Output match success message.
     */
    protected function outputMatch(string $title): void
    {
        if (! $this->echoOutput) {
            return;
        }

        $this->getColorCli()->primary('Found match on '.$this->getDisplayName().': '.$title);
    }

    /**
     * Output failure message.
     */
    protected function outputNotFound(): void
    {
        if (! $this->echoOutput) {
            return;
        }

        $this->getColorCli()->notice('No match found on '.$this->getDisplayName());
    }

    /**
     * Parse JSON-LD structured data from HTML.
     */
    protected function extractJsonLd(string $html): ?array
    {
        // Note: Using # as delimiter because pattern contains / in </script>
        if (preg_match_all('#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#si', $html, $matches)) {
            foreach ($matches[1] as $json) {
                $data = json_decode(trim($json), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    // Handle both single object and array of objects
                    if (isset($data['@type'])) {
                        return $data;
                    } elseif (isset($data[0]['@type'])) {
                        return $data[0];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract Open Graph meta data from HTML.
     */
    protected function extractOpenGraph(string $html): array
    {
        $og = [];
        $this->getHtmlParser()->loadHtml($html);

        $metaTags = [
            'og:title' => 'title',
            'og:description' => 'description',
            'og:image' => 'image',
            'og:url' => 'url',
        ];

        foreach ($metaTags as $property => $key) {
            $meta = $this->getHtmlParser()->findOne('meta[property="' . $property . '"]');
            if ($meta && isset($meta->content)) {
                $og[$key] = trim($meta->content);
            }
        }

        return $og;
    }
}


<?php

namespace App\Services\AdultProcessing;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Cookie\SetCookie;

/**
 * Age Verification Cookie Manager
 *
 * Automatically handles age verification for adult sites by:
 * 1. Detecting age verification pages
 * 2. Simulating acceptance (setting appropriate cookies)
 * 3. Persisting cookies to disk for reuse
 * 4. Loading saved cookies automatically
 */
class AgeVerificationManager
{
    /**
     * Cookie storage directory
     */
    private string $cookieDir;

    /**
     * Cookie jar instances per site
     */
    private array $cookieJars = [];

    /**
     * Site-specific age verification configurations
     */
    private array $siteConfigs = [
        'adultdvdempire.com' => [
            'cookies' => [
                ['name' => 'age_verified', 'value' => '1'],
                ['name' => 'age_gate_passed', 'value' => 'true'],
                ['name' => 'over18', 'value' => '1'],
                ['name' => 'ageConfirmed', 'value' => 'true'],
                ['name' => 'isAdult', 'value' => 'true'],
            ],
            'detection' => ['ageConfirmationButton', 'age-confirmation', 'confirm you are over'],
        ],
        'adultdvdmarketplace.com' => [
            'cookies' => [
                ['name' => 'age_verified', 'value' => '1'],
                ['name' => 'over18', 'value' => 'yes'],
                ['name' => 'ageConfirmed', 'value' => 'true'],
            ],
            'detection' => ['age verification', 'are you 18'],
        ],
        'straight.theater.aebn.net' => [
            'cookies' => [
                ['name' => 'age_verified', 'value' => '1'],
                ['name' => 'aebn_age_check', 'value' => 'passed'],
                ['name' => 'over18', 'value' => 'true'],
            ],
            'detection' => ['age verification', 'over 18'],
        ],
        'hotmovies.com' => [
            'cookies' => [
                ['name' => 'age_verified', 'value' => '1'],
                ['name' => 'over18', 'value' => 'true'],
                ['name' => 'ageConfirmed', 'value' => 'true'],
                ['name' => 'hmAgeVerified', 'value' => '1'],
            ],
            'detection' => ['age verification', 'enter'],
        ],
        'popporn.com' => [
            'cookies' => [
                ['name' => 'age_verified', 'value' => '1'],
                ['name' => 'over_18', 'value' => 'yes'],
                ['name' => 'ageVerified', 'value' => 'true'],
                ['name' => 'popAgeConfirmed', 'value' => '1'],
                ['name' => 'adultConsent', 'value' => 'true'],
                // Note: etoken is dynamically set by the server, we can't pre-set it
            ],
            'detection' => ['age verification', 'over 18', 'AgeConfirmation'],
            'redirectBypass' => true, // Indicates this site uses redirect-based verification
        ],
    ];

    /**
     * Constructor
     */
    public function __construct(?string $cookieDir = null)
    {
        if ($cookieDir !== null) {
            $this->cookieDir = $cookieDir;
        } else {
            $this->cookieDir = storage_path('app/cookies/adult_sites');
        }

        // Create cookie directory if it doesn't exist
        if (! is_dir($this->cookieDir)) {
            mkdir($this->cookieDir, 0755, true);
        }
    }

    /**
     * Get cookie jar for a specific site
     * Loads existing cookies or creates new jar
     */
    public function getCookieJar(string $url): FileCookieJar
    {
        $domain = $this->extractDomain($url);

        if (! isset($this->cookieJars[$domain])) {
            $cookieFile = $this->getCookieFilePath($domain);
            $this->cookieJars[$domain] = new FileCookieJar($cookieFile, true);

            // If cookie file is new or empty, set age verification cookies
            if (! file_exists($cookieFile) || filesize($cookieFile) < 10) {
                $this->setAgeVerificationCookies($domain, $this->cookieJars[$domain]);
            }
        }

        return $this->cookieJars[$domain];
    }

    /**
     * Make HTTP request with automatic age verification handling
     */
    public function makeRequest(string $url, array $options = []): string|false
    {
        $domain = $this->extractDomain($url);
        $cookieJar = $this->getCookieJar($url);

        $client = new Client([
            'cookies' => $cookieJar,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate, br',
                'DNT' => '1',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ],
            'allow_redirects' => true,
            'verify' => false, // For development; set to true in production
        ]);

        try {
            $response = $client->get($url, $options);
            $html = $response->getBody()->getContents();

            // Check if we got an age verification page
            if ($this->isAgeVerificationPage($html, $domain)) {
                // Set age verification cookies and retry
                $this->setAgeVerificationCookies($domain, $cookieJar);
                $cookieJar->save($this->getCookieFilePath($domain));

                // Retry request with new cookies
                $response = $client->get($url, $options);
                $html = $response->getBody()->getContents();
            }

            return $html;

        } catch (\Exception $e) {
            error_log("Age Verification Manager: Request failed for {$url}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Check if response is an age verification page
     */
    private function isAgeVerificationPage(string $html, string $domain): bool
    {
        if (! isset($this->siteConfigs[$domain])) {
            return false;
        }

        $detection = $this->siteConfigs[$domain]['detection'];
        $htmlLower = strtolower($html);

        foreach ($detection as $keyword) {
            if (stripos($htmlLower, strtolower($keyword)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set age verification cookies for a domain
     */
    private function setAgeVerificationCookies(string $domain, CookieJar $cookieJar): void
    {
        if (! isset($this->siteConfigs[$domain])) {
            return;
        }

        $cookies = $this->siteConfigs[$domain]['cookies'];
        $expiry = time() + (365 * 24 * 60 * 60); // 1 year

        foreach ($cookies as $cookieData) {
            $cookie = new SetCookie([
                'Name' => $cookieData['name'],
                'Value' => $cookieData['value'],
                'Domain' => '.'.$domain,
                'Path' => '/',
                'Expires' => $expiry,
                'Secure' => true,
                'HttpOnly' => false,
            ]);

            $cookieJar->setCookie($cookie);
        }

        // Save cookies to file
        if ($cookieJar instanceof FileCookieJar) {
            $cookieJar->save($this->getCookieFilePath($domain));
        }
    }

    /**
     * Extract domain from URL
     */
    private function extractDomain(string $url): string
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';

        // Remove www. prefix if present
        $host = preg_replace('/^www\./', '', $host);

        // Map known subdomains to main domains
        $domainMap = [
            'straight.theater.aebn.net' => 'straight.theater.aebn.net',
            'adultdvdempire.com' => 'adultdvdempire.com',
            'adultdvdmarketplace.com' => 'adultdvdmarketplace.com',
            'hotmovies.com' => 'hotmovies.com',
            'popporn.com' => 'popporn.com',
        ];

        return $domainMap[$host] ?? $host;
    }

    /**
     * Force refresh cookies for a domain.
     */
    public function refreshCookies(string $url): FileCookieJar
    {
        $domain = $this->extractDomain($url);
        $cookieFile = $this->getCookieFilePath($domain);

        // Clear existing cookie jar
        if (isset($this->cookieJars[$domain])) {
            unset($this->cookieJars[$domain]);
        }

        // Delete old cookie file if exists
        if (file_exists($cookieFile)) {
            unlink($cookieFile);
        }

        // Create new jar with fresh cookies
        $this->cookieJars[$domain] = new FileCookieJar($cookieFile, true);
        $this->setAgeVerificationCookies($domain, $this->cookieJars[$domain]);

        return $this->cookieJars[$domain];
    }

    /**
     * Get cookie file path for a domain
     */
    private function getCookieFilePath(string $domain): string
    {
        $safeName = preg_replace('/[^a-z0-9_-]/', '_', strtolower($domain));

        return $this->cookieDir.'/'.$safeName.'_cookies.json';
    }

    /**
     * Clear cookies for a specific domain
     */
    public function clearCookies(string $domain): bool
    {
        $cookieFile = $this->getCookieFilePath($domain);

        if (file_exists($cookieFile)) {
            unlink($cookieFile);
            unset($this->cookieJars[$domain]);

            return true;
        }

        return false;
    }

    /**
     * Clear all stored cookies
     */
    public function clearAllCookies(): int
    {
        $cleared = 0;
        $files = glob($this->cookieDir.'/*_cookies.json');

        foreach ($files as $file) {
            if (unlink($file)) {
                $cleared++;
            }
        }

        $this->cookieJars = [];

        return $cleared;
    }

    /**
     * Get list of domains with stored cookies
     */
    public function getStoredDomains(): array
    {
        $domains = [];
        $files = glob($this->cookieDir.'/*_cookies.json');

        foreach ($files as $file) {
            $basename = basename($file, '_cookies.json');
            $domain = str_replace('_', '.', $basename);
            $domains[] = $domain;
        }

        return $domains;
    }

    /**
     * Check if cookies exist for a domain
     */
    public function hasCookies(string $domain): bool
    {
        $cookieFile = $this->getCookieFilePath($domain);

        return file_exists($cookieFile) && filesize($cookieFile) > 10;
    }

    /**
     * Get cookie statistics
     */
    public function getCookieStats(): array
    {
        $stats = [
            'total_domains' => 0,
            'total_cookies' => 0,
            'domains' => [],
        ];

        foreach ($this->siteConfigs as $domain => $config) {
            if ($this->hasCookies($domain)) {
                $cookieJar = $this->getCookieJar('https://'.$domain);
                $cookieCount = count($cookieJar);

                $stats['domains'][$domain] = [
                    'has_cookies' => true,
                    'cookie_count' => $cookieCount,
                    'file' => $this->getCookieFilePath($domain),
                ];

                $stats['total_domains']++;
                $stats['total_cookies'] += $cookieCount;
            } else {
                $stats['domains'][$domain] = [
                    'has_cookies' => false,
                    'cookie_count' => 0,
                ];
            }
        }

        return $stats;
    }

    /**
     * Get the cookie directory path
     */
    public function getCookieDirectory(): string
    {
        return $this->cookieDir;
    }
}

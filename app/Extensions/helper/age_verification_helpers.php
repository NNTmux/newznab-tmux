<?php

/**
 * Enhanced getRawHtml function with automatic age verification
 *
 * This is a replacement/enhancement for the existing getRawHtml() in helpers.php
 * that automatically handles age verification for adult sites.
 */

use App\Services\AdultProcessing\AgeVerificationManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

if (! function_exists('getRawHtmlWithAgeVerification')) {
    /**
     * Get raw HTML with automatic age verification handling
     *
     * @param  string  $url  URL to fetch
     * @param  string|false  $cookie  Optional cookie string (legacy support)
     * @param  string|null  $postData  Optional POST data
     * @return string|array|false
     */
    function getRawHtmlWithAgeVerification($url, $cookie = false, $postData = null)
    {
        static $ageVerificationManager = null;

        // Initialize manager on first use
        if ($ageVerificationManager === null) {
            $ageVerificationManager = new AgeVerificationManager;
        }

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

        // For adult sites, use the age verification manager
        if ($isAdultSite) {
            try {
                $options = [];

                // Handle POST data if provided
                if ($postData !== null) {
                    $options['form_params'] = $postData;
                    $cookieJar = $ageVerificationManager->getCookieJar($url);

                    $client = new Client([
                        'cookies' => $cookieJar,
                        'headers' => [
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        ],
                    ]);

                    $response = $client->post($url, $options);
                    $html = $response->getBody()->getContents();
                } else {
                    $html = $ageVerificationManager->makeRequest($url, $options);
                }

                // Try to decode as JSON (some APIs return JSON)
                if ($html !== false) {
                    $jsonResponse = json_decode($html, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $jsonResponse;
                    }
                }

                return $html;

            } catch (RequestException $e) {
                if (config('app.debug') === true) {
                    \Log::error('getRawHtmlWithAgeVerification: '.$e->getMessage());
                }

                return false;
            } catch (\Exception $e) {
                if (config('app.debug') === true) {
                    \Log::error('getRawHtmlWithAgeVerification: '.$e->getMessage());
                }

                return false;
            }
        }

        // For non-adult sites, use standard approach
        return getRawHtml($url, $cookie, $postData);
    }
}

if (! function_exists('initializeAdultSiteCookies')) {
    /**
     * Initialize age verification cookies for all adult sites
     * Run this once during application setup
     *
     * @return array Statistics about initialized cookies
     */
    function initializeAdultSiteCookies(): array
    {
        $manager = new AgeVerificationManager;

        $sites = [
            'https://www.adultdvdempire.com',
            'https://www.adultdvdmarketplace.com',
            'https://straight.theater.aebn.net',
            'https://www.hotmovies.com',
            'https://www.popporn.com',
        ];

        $results = [
            'initialized' => 0,
            'failed' => 0,
            'sites' => [],
        ];

        foreach ($sites as $url) {
            try {
                // Making a request will automatically set up cookies
                $cookieJar = $manager->getCookieJar($url);
                $domain = parse_url($url, PHP_URL_HOST);

                $results['sites'][$domain] = [
                    'status' => 'initialized',
                    'cookies' => count($cookieJar),
                ];
                $results['initialized']++;

            } catch (\Exception $e) {
                $domain = parse_url($url, PHP_URL_HOST);
                $results['sites'][$domain] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
                $results['failed']++;
            }
        }

        return $results;
    }
}

if (! function_exists('getAdultSiteCookieStats')) {
    /**
     * Get statistics about stored adult site cookies
     *
     * @return array Cookie statistics
     */
    function getAdultSiteCookieStats(): array
    {
        $manager = new AgeVerificationManager;

        return $manager->getCookieStats();
    }
}

if (! function_exists('clearAdultSiteCookies')) {
    /**
     * Clear all stored adult site cookies
     *
     * @param  string|null  $domain  Optional specific domain to clear
     * @return int|bool Number of cookies cleared (or bool for specific domain)
     */
    function clearAdultSiteCookies(?string $domain = null)
    {
        $manager = new AgeVerificationManager;

        if ($domain !== null) {
            return $manager->clearCookies($domain);
        }

        return $manager->clearAllCookies();
    }
}

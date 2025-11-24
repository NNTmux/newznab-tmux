#!/usr/bin/env php
<?php

/**
 * Adult Site Cookie Manager
 *
 * Command-line tool to manage age verification cookies for adult sites.
 *
 * Usage:
 *   php manage_adult_cookies.php init         # Initialize cookies for all sites
 *   php manage_adult_cookies.php stats        # Show cookie statistics
 *   php manage_adult_cookies.php clear [site] # Clear cookies
 *   php manage_adult_cookies.php test [site]  # Test if cookies work
 */

require __DIR__.'/vendor/autoload.php';

use Blacklight\processing\adult\AgeVerificationManager;

$command = $argv[1] ?? 'help';
$argument = $argv[2] ?? null;

$manager = new AgeVerificationManager;

switch ($command) {
    case 'init':
        echo "================================================================================\n";
        echo "Initializing Age Verification Cookies\n";
        echo "================================================================================\n\n";

        $sites = [
            'https://www.adultdvdempire.com' => 'Adult DVD Empire',
            'https://www.adultdvdmarketplace.com' => 'Adult DVD Marketplace',
            'https://straight.theater.aebn.net' => 'AEBN Theater',
            'https://www.hotmovies.com' => 'HotMovies',
            'https://www.popporn.com' => 'Popporn',
        ];

        foreach ($sites as $url => $name) {
            echo "Setting up {$name}...\n";
            try {
                $cookieJar = $manager->getCookieJar($url);
                $count = count($cookieJar);
                echo "  ✓ Cookies initialized ({$count} cookies)\n";
            } catch (Exception $e) {
                echo "  ❌ Failed: {$e->getMessage()}\n";
            }
            echo "\n";
        }

        echo 'Done! Cookies are saved to: '.$manager->getCookieDirectory()."\n";
        break;

    case 'stats':
        echo "================================================================================\n";
        echo "Adult Site Cookie Statistics\n";
        echo "================================================================================\n\n";

        $stats = $manager->getCookieStats();

        echo "Total domains with cookies: {$stats['total_domains']}\n";
        echo "Total cookies stored: {$stats['total_cookies']}\n\n";

        echo "Domain Details:\n";
        echo str_repeat('-', 80)."\n";

        foreach ($stats['domains'] as $domain => $info) {
            $status = $info['has_cookies'] ? '✓' : '❌';
            $cookies = $info['cookie_count'];
            echo sprintf("  %s %-40s %d cookies\n", $status, $domain, $cookies);

            if ($info['has_cookies']) {
                echo "     File: {$info['file']}\n";
            }
        }

        echo "\n";
        break;

    case 'clear':
        if ($argument) {
            echo "Clearing cookies for: {$argument}\n";
            if ($manager->clearCookies($argument)) {
                echo "✓ Cookies cleared\n";
            } else {
                echo "❌ No cookies found for this domain\n";
            }
        } else {
            echo "Clearing all adult site cookies...\n";
            $cleared = $manager->clearAllCookies();
            echo "✓ Cleared {$cleared} cookie file(s)\n";
        }
        break;

    case 'test':
        echo "================================================================================\n";
        echo "Testing Adult Site Access\n";
        echo "================================================================================\n\n";

        $testSites = [
            'https://www.adultdvdempire.com/dvd/search?q=test' => 'Adult DVD Empire',
            'https://www.adultdvdmarketplace.com' => 'Adult DVD Marketplace',
            'https://straight.theater.aebn.net' => 'AEBN Theater',
            'https://www.hotmovies.com' => 'HotMovies',
            'https://www.popporn.com' => 'Popporn',
        ];

        if ($argument) {
            // Test specific site
            $testSites = array_filter($testSites, function ($name, $url) use ($argument) {
                return stripos($url, $argument) !== false;
            }, ARRAY_FILTER_USE_BOTH);
        }

        foreach ($testSites as $url => $name) {
            echo "Testing {$name}...\n";
            try {
                $response = $manager->makeRequest($url);

                if ($response === false) {
                    echo "  ❌ Request failed\n";
                } else {
                    $responseLength = strlen($response);
                    echo "  ✓ Response received ({$responseLength} bytes)\n";

                    // Check for age verification indicators
                    if (stripos($response, 'age') !== false &&
                        (stripos($response, 'verification') !== false ||
                         stripos($response, 'confirm') !== false)) {
                        echo "  ⚠️  Age verification page detected\n";
                    } else {
                        echo "  ✓ Successfully bypassed age gate\n";
                    }
                }
            } catch (Exception $e) {
                echo "  ❌ Error: {$e->getMessage()}\n";
            }
            echo "\n";
        }
        break;

    case 'help':
    default:
        echo "================================================================================\n";
        echo "Adult Site Cookie Manager\n";
        echo "================================================================================\n\n";

        echo "Usage:\n";
        echo "  php manage_adult_cookies.php <command> [arguments]\n\n";

        echo "Commands:\n";
        echo "  init              Initialize age verification cookies for all sites\n";
        echo "  stats             Show statistics about stored cookies\n";
        echo "  clear [domain]    Clear cookies (all or specific domain)\n";
        echo "  test [domain]     Test if cookies work for sites\n";
        echo "  help              Show this help message\n\n";

        echo "Examples:\n";
        echo "  php manage_adult_cookies.php init\n";
        echo "  php manage_adult_cookies.php stats\n";
        echo "  php manage_adult_cookies.php clear adultdvdempire.com\n";
        echo "  php manage_adult_cookies.php test\n\n";

        echo "Cookie Storage:\n";
        echo "  Cookies are stored in: storage/app/cookies/adult_sites/\n";
        echo "  Format: JSON files, one per domain\n";
        echo "  Expiry: 1 year from creation\n\n";
        break;
}

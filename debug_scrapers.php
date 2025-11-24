#!/usr/bin/env php
<?php

/**
 * Debug Adult Scrapers
 * Shows what HTML is actually being returned from sites
 */

require __DIR__.'/vendor/autoload.php';

echo "Testing ADE Search\n";
echo str_repeat('=', 80)."\n\n";

$movie = 'Pirates';
$url = 'https://www.adultdvdempire.com/dvd/search?q='.rawurlencode($movie);

echo "Search URL: {$url}\n\n";

// Test the getRawHtml function
$response = getRawHtml($url);

if ($response === false) {
    echo "ERROR: getRawHtml returned false\n";
    echo "This usually means:\n";
    echo "  - Network error\n";
    echo "  - Site is blocking requests\n";
    echo "  - Site is down\n";
    exit(1);
}

if (empty($response)) {
    echo "ERROR: getRawHtml returned empty response\n";
    exit(1);
}

echo 'Response received: '.strlen($response)." bytes\n\n";

// Save response for inspection
file_put_contents('/tmp/ade_search_response.html', $response);
echo "Full response saved to: /tmp/ade_search_response.html\n\n";

// Try to parse it
$parser = new \voku\helper\HtmlDomParser;
$html = $parser->loadHtml($response);

// Try various selectors
$selectors = [
    'a.boxcover-link',
    'a.grid-item__link',
    'a[class*="boxcover"]',
    'div.card a[href*="/item/"]',
    'a[href*="/item/"]',
    'a.fancybox-button',
    'a[class=fancybox-button]',
    'div.card a.boxcover-link',
];

echo "Testing selectors:\n";
echo str_repeat('-', 80)."\n";

foreach ($selectors as $selector) {
    $results = $html->find($selector);
    echo sprintf("%-40s : %d results\n", $selector, count($results));

    if (! empty($results)) {
        echo "  First result:\n";
        $first = $results[0];
        echo '    href: '.($first->href ?? 'N/A')."\n";
        echo '    title: '.($first->title ?? 'N/A')."\n";
        echo '    text: '.trim($first->plaintext ?? 'N/A')."\n";
        echo "\n";
    }
}

// Check for common error messages
if (stripos($response, 'blocked') !== false) {
    echo "\n⚠️  WARNING: Response contains 'blocked' - might be blocked\n";
}

if (stripos($response, 'captcha') !== false) {
    echo "\n⚠️  WARNING: Response contains 'captcha' - CAPTCHA required\n";
}

if (stripos($response, 'access denied') !== false) {
    echo "\n⚠️  WARNING: Response contains 'access denied'\n";
}

if (stripos($response, 'cloudflare') !== false) {
    echo "\n⚠️  WARNING: Response contains 'cloudflare' - might be Cloudflare protection\n";
}

echo "\n";
echo str_repeat('=', 80)."\n";
echo "Done! Check /tmp/ade_search_response.html for full HTML\n";

#!/usr/bin/env php
<?php

/**
 * Adult Scrapers Mock Data Test
 *
 * Tests scraper parsing logic using saved HTML files (mock data).
 * This approach works without needing age verification cookies.
 *
 * Usage: php test_adult_scrapers_mock.php
 */

require __DIR__.'/vendor/autoload.php';

use Blacklight\processing\adult\ADE;

echo "================================================================================\n";
echo "Adult Scrapers Mock Data Test\n";
echo "Testing parsing logic with saved HTML\n";
echo "================================================================================\n\n";

// Directory for mock data
$mockDataDir = __DIR__.'/tests/mock_data/adult';

if (! is_dir($mockDataDir)) {
    mkdir($mockDataDir, 0755, true);
    echo "ℹ️  Created mock data directory: {$mockDataDir}\n\n";
}

echo "📝 Mock Data Testing Strategy:\n";
echo str_repeat('-', 80)."\n";
echo "1. Manually visit adult sites and verify age\n";
echo "2. Search for test movies\n";
echo "3. Save search results HTML\n";
echo "4. Save movie detail pages HTML\n";
echo "5. Test scrapers against saved HTML\n";
echo "\n";

echo "📁 Mock data directory: {$mockDataDir}\n";
echo "\n";

// Check for existing mock files
$mockFiles = [
    'ade_search_pirates.html' => 'ADE search results for "Pirates"',
    'ade_detail_pirates.html' => 'ADE detail page for "Pirates"',
    'adm_search_pirates.html' => 'ADM search results for "Pirates"',
    'adm_detail_pirates.html' => 'ADM detail page for "Pirates"',
    'aebn_search_pirates.html' => 'AEBN search results for "Pirates"',
    'aebn_detail_pirates.html' => 'AEBN detail page for "Pirates"',
    'hotmovies_search_pirates.html' => 'Hotmovies search results for "Pirates"',
    'hotmovies_detail_pirates.html' => 'Hotmovies detail page for "Pirates"',
    'popporn_search_pirates.html' => 'Popporn search results for "Pirates"',
    'popporn_detail_pirates.html' => 'Popporn detail page for "Pirates"',
];

echo "📋 Checking for mock data files:\n";
echo str_repeat('-', 80)."\n";

$foundFiles = [];
$missingFiles = [];

foreach ($mockFiles as $file => $description) {
    $path = $mockDataDir.'/'.$file;
    if (file_exists($path)) {
        $size = filesize($path);
        echo "  ✓ {$file} ({$size} bytes)\n";
        $foundFiles[] = $file;
    } else {
        echo "  ❌ {$file} (missing)\n";
        $missingFiles[] = $file;
    }
}

echo "\n";

if (empty($foundFiles)) {
    echo "================================================================================\n";
    echo "ℹ️  No mock data files found\n";
    echo "================================================================================\n\n";

    echo "To create mock data files:\n\n";

    echo "1. Open a browser and navigate to each adult site\n";
    echo "2. Click 'I am over 18' or equivalent age verification\n";
    echo "3. Search for 'Pirates' (or other test movie)\n";
    echo "4. Right-click → View Page Source\n";
    echo "5. Save as: {$mockDataDir}/[scraper]_search_pirates.html\n";
    echo "6. Click on first result\n";
    echo "7. View Page Source of detail page\n";
    echo "8. Save as: {$mockDataDir}/[scraper]_detail_pirates.html\n\n";

    echo "Example for ADE:\n";
    echo "  Search page: https://www.adultdvdempire.com/dvd/search?q=Pirates\n";
    echo "  Save HTML to: {$mockDataDir}/ade_search_pirates.html\n";
    echo "  Click first result, save detail HTML to: {$mockDataDir}/ade_detail_pirates.html\n\n";

    echo "Then run this script again.\n";
    exit(0);
}

// Test with available mock data
echo "================================================================================\n";
echo "Testing with Available Mock Data\n";
echo "================================================================================\n\n";

// Test ADE if we have the files
if (in_array('ade_search_pirates.html', $foundFiles) &&
    in_array('ade_detail_pirates.html', $foundFiles)) {

    echo "Testing ADE (Adult DVD Empire):\n";
    echo str_repeat('-', 80)."\n";

    $scraper = new ADE;
    $reflection = new ReflectionClass($scraper);

    // Load search results
    $searchHtml = file_get_contents($mockDataDir.'/ade_search_pirates.html');

    // Set protected properties using reflection
    $responseProp = $reflection->getProperty('_response');
    $responseProp->setAccessible(true);
    $responseProp->setValue($scraper, $searchHtml);

    $htmlProp = $reflection->getProperty('_html');
    $htmlProp->setAccessible(true);
    $html = $htmlProp->getValue($scraper);
    $html->loadHtml($searchHtml);

    // Try to extract search results
    $selectors = [
        'a.boxcover-link',
        'a.grid-item__link',
        'a[href*="/item/"]',
    ];

    $found = false;
    foreach ($selectors as $selector) {
        $results = $html->find($selector);
        if (! empty($results)) {
            echo "  ✓ Found search results using: {$selector}\n";
            echo '    Result count: '.count($results)."\n";
            if (! empty($results[0])) {
                $firstResult = $results[0];
                $title = $firstResult->title ?? $firstResult->plaintext ?? 'N/A';
                echo '    First result: '.substr($title, 0, 50)."...\n";
            }
            $found = true;
            break;
        }
    }

    if (! $found) {
        echo "  ⚠️  Could not find search results with any selector\n";
        echo "    This means the HTML structure may have changed\n";
        echo "    Need to update selectors in ADE.php\n";
    }

    // Load detail page
    $detailHtml = file_get_contents($mockDataDir.'/ade_detail_pirates.html');
    $responseProp->setValue($scraper, $detailHtml);
    $html->loadHtml($detailHtml);

    // Set a fake direct URL so getAll() works
    $directUrlProp = $reflection->getProperty('_directUrl');
    $directUrlProp->setAccessible(true);
    $directUrlProp->setValue($scraper, 'https://www.adultdvdempire.com/item/12345-pirates.html');

    $titleProp = $reflection->getProperty('_title');
    $titleProp->setAccessible(true);
    $titleProp->setValue($scraper, 'Pirates');

    // Test extraction using public getAll() method
    echo "\n  Testing data extraction:\n";

    try {
        $data = $scraper->getAll();

        if (! empty($data)) {
            echo "    ✓ Data extraction successful\n";
            echo '    Fields found: '.count($data)."\n";

            if (! empty($data['title'])) {
                echo '    ✓ Title: '.$data['title']."\n";
            }

            if (! empty($data['synopsis'])) {
                $synopsisText = substr($data['synopsis'], 0, 80);
                echo "    ✓ Synopsis: {$synopsisText}...\n";
            } else {
                echo "    ⚠️  Synopsis not found\n";
            }

            if (! empty($data['cast']) && is_array($data['cast'])) {
                echo '    ✓ Cast: '.count($data['cast'])." members\n";
                if (! empty($data['cast'][0])) {
                    echo '      First: '.$data['cast'][0]."\n";
                }
            } else {
                echo "    ⚠️  Cast not found\n";
            }

            if (! empty($data['genres']) && is_array($data['genres'])) {
                $genreList = implode(', ', array_slice($data['genres'], 0, 3));
                echo "    ✓ Genres: {$genreList}\n";
            } else {
                echo "    ⚠️  Genres not found\n";
            }

            if (! empty($data['boxcover'])) {
                echo '    ✓ Box cover: '.$data['boxcover']."\n";
            } else {
                echo "    ⚠️  Box cover not found\n";
            }

            if (! empty($data['director'])) {
                echo '    ✓ Director: '.$data['director']."\n";
            }

            if (! empty($data['productinfo'])) {
                echo "    ✓ Product info available\n";
            }

            // Show what fields are missing
            $expectedFields = ['synopsis', 'cast', 'genres', 'boxcover', 'backcover'];
            $missingFields = [];
            foreach ($expectedFields as $field) {
                if (empty($data[$field])) {
                    $missingFields[] = $field;
                }
            }

            if (! empty($missingFields)) {
                echo "\n    ⚠️  Missing fields: ".implode(', ', $missingFields)."\n";
                echo "    → This means selectors may need updating\n";
            }
        } else {
            echo "    ❌ No data extracted\n";
        }
    } catch (Exception $e) {
        echo '    ❌ Extraction error: '.$e->getMessage()."\n";
        echo "    → Stack trace:\n";
        echo '    '.$e->getTraceAsString()."\n";
    }

    echo "\n";
}

// Add similar tests for other scrapers...

echo "================================================================================\n";
echo "Summary\n";
echo "================================================================================\n";
echo 'Mock data files found: '.count($foundFiles).'/'.count($mockFiles)."\n";
echo "\n";

if (count($missingFiles) > 0) {
    echo "To test more scrapers, add these mock data files:\n";
    foreach (array_slice($missingFiles, 0, 5) as $file) {
        echo "  - {$file}\n";
    }
    if (count($missingFiles) > 5) {
        echo '  ... and '.(count($missingFiles) - 5)." more\n";
    }
}

echo "\n";
echo "ℹ️  This approach tests the parsing logic without needing age verification cookies.\n";
echo "ℹ️  In production, scrapers work within your application where users have verified their age.\n";

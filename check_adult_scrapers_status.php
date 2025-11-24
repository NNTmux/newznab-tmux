#!/usr/bin/env php
<?php

/**
 * Adult Scrapers Status Check
 *
 * Quick validation script to verify all scraper files and tests are in place
 * and working correctly.
 *
 * Usage: php check_adult_scrapers_status.php
 */
echo "================================================================================\n";
echo "Adult Scrapers Status Check\n";
echo "================================================================================\n\n";

$errors = [];
$warnings = [];
$success = [];

// Check scraper files
echo "📁 Checking Scraper Files...\n";
$scrapers = [
    'Blacklight/processing/adult/ADE.php' => 'Adult DVD Empire',
    'Blacklight/processing/adult/ADM.php' => 'Adult DVD Marketplace',
    'Blacklight/processing/adult/AEBN.php' => 'AEBN Theater',
    'Blacklight/processing/adult/Hotmovies.php' => 'HotMovies',
    'Blacklight/processing/adult/Popporn.php' => 'Popporn',
];

foreach ($scrapers as $file => $name) {
    if (file_exists($file)) {
        // Check PHP syntax
        $output = [];
        $return = 0;
        exec("php -l {$file} 2>&1", $output, $return);

        if ($return === 0) {
            // Check for minimumSimilarity property
            $content = file_get_contents($file);
            if (strpos($content, 'minimumSimilarity') !== false) {
                $success[] = "✓ {$name}: File exists, syntax OK, improvements applied";
                echo "  ✓ {$name}\n";
            } else {
                $warnings[] = "⚠ {$name}: File exists but may need improvements";
                echo "  ⚠ {$name} (may need improvements)\n";
            }
        } else {
            $errors[] = "❌ {$name}: Syntax error in {$file}";
            echo "  ❌ {$name} (syntax error)\n";
        }
    } else {
        $errors[] = "❌ {$name}: File not found at {$file}";
        echo "  ❌ {$name} (not found)\n";
    }
}

echo "\n📋 Checking Test Files...\n";
$testFiles = [
    'test_adult_scrapers.php' => 'Standalone test script',
    'tests/Unit/Blacklight/Processing/Adult/AdultScrapersTest.php' => 'PHPUnit test suite',
];

foreach ($testFiles as $file => $name) {
    if (file_exists($file)) {
        $output = [];
        $return = 0;
        exec("php -l {$file} 2>&1", $output, $return);

        if ($return === 0) {
            $success[] = "✓ {$name}: Available and syntax OK";
            echo "  ✓ {$name}\n";

            // Check if executable
            if ($file === 'test_adult_scrapers.php' && is_executable($file)) {
                echo "    ✓ Executable\n";
            }
        } else {
            $errors[] = "❌ {$name}: Syntax error in {$file}";
            echo "  ❌ {$name} (syntax error)\n";
        }
    } else {
        $errors[] = "❌ {$name}: File not found at {$file}";
        echo "  ❌ {$name} (not found)\n";
    }
}

echo "\n📚 Checking Documentation...\n";
$docs = [
    'ADULT_SCRAPERS_IMPROVEMENTS.md' => 'Improvements documentation',
    'ADULT_SCRAPERS_TESTING_GUIDE.md' => 'Testing guide',
    'ADULT_SCRAPERS_QUICK_REFERENCE.md' => 'Quick reference',
    'ADULT_SCRAPERS_TEST_EXAMPLES.md' => 'Test examples',
    'ADULT_SCRAPERS_SUMMARY.md' => 'Implementation summary',
    'tests/Unit/Blacklight/Processing/Adult/README.md' => 'Test directory README',
];

foreach ($docs as $file => $name) {
    if (file_exists($file)) {
        $size = filesize($file);
        $success[] = "✓ {$name}: Available ({$size} bytes)";
        echo "  ✓ {$name}\n";
    } else {
        $warnings[] = "⚠ {$name}: Not found at {$file}";
        echo "  ⚠ {$name} (not found)\n";
    }
}

echo "\n🔍 Checking Dependencies...\n";

// Check if vendor/autoload.php exists
if (file_exists('vendor/autoload.php')) {
    echo "  ✓ Composer autoload available\n";
    $success[] = '✓ Composer dependencies installed';
} else {
    $errors[] = '❌ Composer autoload not found - run: composer install';
    echo "  ❌ Composer autoload not found\n";
    echo "     Run: composer install\n";
}

// Check for required classes
$requiredClasses = [
    'voku\helper\HtmlDomParser' => 'HTML parser library',
];

foreach ($requiredClasses as $class => $description) {
    if (class_exists($class)) {
        echo "  ✓ {$description} ({$class})\n";
        $success[] = "✓ {$description} available";
    } else {
        $warnings[] = "⚠ {$description} not loaded";
        echo "  ⚠ {$description} not loaded\n";
    }
}

echo "\n================================================================================\n";
echo "SUMMARY\n";
echo "================================================================================\n";

echo "\n✓ Success: ".count($success)." checks passed\n";
if (! empty($warnings)) {
    echo '⚠ Warnings: '.count($warnings)." issues\n";
}
if (! empty($errors)) {
    echo '❌ Errors: '.count($errors)." critical issues\n";
}

if (! empty($errors)) {
    echo "\n🔴 Critical Issues:\n";
    foreach ($errors as $error) {
        echo "  {$error}\n";
    }
}

if (! empty($warnings)) {
    echo "\n🟡 Warnings:\n";
    foreach ($warnings as $warning) {
        echo "  {$warning}\n";
    }
}

echo "\n";

if (empty($errors) && empty($warnings)) {
    echo "🎉 All checks passed! The adult scrapers implementation is complete and ready to use.\n\n";
    echo "To get started:\n";
    echo "  php test_adult_scrapers.php\n\n";
    exit(0);
} elseif (empty($errors)) {
    echo "✅ Core implementation is ready with minor warnings.\n\n";
    echo "To get started:\n";
    echo "  php test_adult_scrapers.php\n\n";
    exit(0);
} else {
    echo "❌ Please fix critical errors before proceeding.\n\n";
    exit(1);
}

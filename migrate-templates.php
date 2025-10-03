#!/usr/bin/env php
<?php

/**
 * Smarty to Blade Migration Helper Script
 *
 * This script helps convert Smarty template files to Blade templates
 * Usage: php migrate-templates.php [template-file.tpl]
 */
function convertSmartyToBlade($smartyContent)
{
    $bladeContent = $smartyContent;

    // Convert comments
    $bladeContent = preg_replace('/\{\*(.+?)\*\}/s', '{{-- $1 --}}', $bladeContent);

    // Convert variables: {$var} to {{ $var }}
    $bladeContent = preg_replace('/\{\$([a-zA-Z0-9_\.\[\]\'\"]+)\}/', '{{ $$$1 }}', $bladeContent);

    // Convert if statements
    $bladeContent = preg_replace('/\{if\s+(.+?)\}/', '@if($1)', $bladeContent);
    $bladeContent = preg_replace('/\{elseif\s+(.+?)\}/', '@elseif($1)', $bladeContent);
    $bladeContent = preg_replace('/\{else\}/', '@else', $bladeContent);
    $bladeContent = preg_replace('/\{\/if\}/', '@endif', $bladeContent);

    // Convert foreach loops
    $bladeContent = preg_replace('/\{foreach\s+\$([a-zA-Z0-9_]+)\s+as\s+\$([a-zA-Z0-9_]+)\}/', '@foreach($$1 as $$2)', $bladeContent);
    $bladeContent = preg_replace('/\{foreach\s+\$([a-zA-Z0-9_]+)\s+as\s+\$([a-zA-Z0-9_]+)\s*=>\s*\$([a-zA-Z0-9_]+)\}/', '@foreach($$1 as $$2 => $$3)', $bladeContent);
    $bladeContent = preg_replace('/\{\/foreach\}/', '@endforeach', $bladeContent);

    // Convert includes
    $bladeContent = preg_replace('/\{include\s+file=[\'"](.+?)[\'"]\}/', '@include(\'$1\')', $bladeContent);

    // Convert URLs - keep as is since they're already using Laravel syntax
    // {{url()}} and {{route()}} work in both Smarty and Blade

    // Convert Auth checks
    $bladeContent = preg_replace('/\{if\s+Auth::check\(\)\}/', '@auth', $bladeContent);

    // Fix object property access: $obj.prop to $obj->prop
    $bladeContent = preg_replace('/\$([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)/', '$$$1->$2', $bladeContent);

    return $bladeContent;
}

if ($argc < 2) {
    echo "Usage: php migrate-templates.php [template-file.tpl]\n";
    echo "Example: php migrate-templates.php resources/views/themes/Gentele/browse.tpl\n";
    exit(1);
}

$inputFile = $argv[1];

if (! file_exists($inputFile)) {
    echo "Error: File not found: $inputFile\n";
    exit(1);
}

$smartyContent = file_get_contents($inputFile);
$bladeContent = convertSmartyToBlade($smartyContent);

// Determine output filename
$outputFile = preg_replace('/\.tpl$/', '.blade.php', $inputFile);
$outputFile = str_replace('/themes/Gentele/', '/', $outputFile);
$outputFile = str_replace('/themes/admin/', '/admin/', $outputFile);
$outputFile = str_replace('/themes/shared/', '/shared/', $outputFile);

echo "Converting: $inputFile\n";
echo "To: $outputFile\n";
echo "\nPreview of converted content:\n";
echo str_repeat('-', 80)."\n";
echo substr($bladeContent, 0, 500)."...\n";
echo str_repeat('-', 80)."\n";

echo "\nDo you want to save this conversion? (y/n): ";
$handle = fopen('php://stdin', 'r');
$line = fgets($handle);
if (trim($line) != 'y') {
    echo "Cancelled.\n";
    exit(0);
}

// Create directory if it doesn't exist
$outputDir = dirname($outputFile);
if (! is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

file_put_contents($outputFile, $bladeContent);
echo "Saved to: $outputFile\n";
echo "\nNote: Please review the converted file and make manual adjustments as needed.\n";
echo "Common manual adjustments:\n";
echo "  - Array access: \$arr['key'] instead of \$arr.key\n";
echo "  - Complex expressions may need refinement\n";
echo "  - Bootstrap classes should be converted to TailwindCSS\n";

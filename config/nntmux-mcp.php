<?php

declare(strict_types=1);

return [
    'schema_version' => 1,

    'allowed_roots' => [
        'app',
        'bootstrap',
        'config',
        'database',
        'resources',
        'routes',
        'tests',
    ],

    'allowed_root_files' => [
        '.env.example',
        'AGENTS.md',
        'README.md',
        'composer.json',
        'composer.lock',
        'package.json',
        'package-lock.json',
        'phpstan.neon',
        'phpunit.xml',
        'pint.json',
        'vite.config.js',
    ],

    'denied_segments' => [
        '.git',
        'node_modules',
        'public/build',
        'storage',
        'storage-runtime',
        'vendor',
    ],

    'limits' => [
        'max_paths' => 20,
        'max_results' => 50,
        'max_resource_bytes' => 65_536,
        'max_process_output_bytes' => 32_768,
    ],

    'timeouts' => [
        'repository_seconds' => 20,
        'verification_seconds' => 600,
        'frontend_seconds' => 900,
    ],

    'check_profiles' => [
        'targeted' => ['phpunit', 'pint', 'phpstan', 'php-lint', 'frontend', 'composer', 'diff'],
    ],

    'architecture_areas' => [
        'app/Console/Commands' => 'Artisan commands and operational workflows',
        'app/Http/Controllers/Api' => 'Newznab v1 and JSON v2 APIs',
        'app/Mcp' => 'NNTmux development MCP server',
        'app/Services/AdditionalProcessing' => 'Additional release post-processing',
        'app/Services/Backfill' => 'Usenet backfill planning and execution',
        'app/Services/Binaries' => 'Header parsing and binary persistence',
        'app/Services/Categorization' => 'Release categorization pipeline',
        'app/Services/NameFixing' => 'Release name analysis and correction',
        'app/Services/NNTP' => 'NNTP connections and article retrieval',
        'app/Services/Search' => 'Manticore and Elasticsearch abstraction',
        'app/Services/StatusProbes' => 'Read-only service health probes',
        'app/Services/Tmux' => 'Tmux processing orchestration and monitoring',
        'resources' => 'Blade, Alpine, Tailwind, and frontend assets',
        'routes' => 'HTTP, API, RSS, console, and MCP registration',
        'tests' => 'PHPUnit regression coverage',
    ],
];

<?php

declare(strict_types=1);

return [
    'required' => env('RELEASE_VERIFICATION_REQUIRED', true),
    'scheduled' => env('RELEASE_VERIFICATION_SCHEDULED', true),
    'storage_path' => storage_path('app/release-verification'),
    'scratch_bytes' => (int) env('RELEASE_VERIFICATION_SCRATCH_BYTES', 214748364800),
    'free_bytes' => (int) env('RELEASE_VERIFICATION_FREE_BYTES', 10737418240),
    'article_bytes' => (int) env('RELEASE_VERIFICATION_ARTICLE_BYTES', 16777216),
    'bytes_per_second' => (int) env('RELEASE_VERIFICATION_BYTES_PER_SECOND', 10485760),
    'pending_bytes' => (int) env('RELEASE_VERIFICATION_PENDING_BYTES', 10737418240),
    'lease_seconds' => 300,
    'retry_seconds' => 900,
    'expiry_hours' => 48,
    'par2_binary' => env('RELEASE_VERIFICATION_PAR2_BINARY', 'par2'),
];

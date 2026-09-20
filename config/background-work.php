<?php

declare(strict_types=1);

return [
    'backpressure' => [
        'enabled' => (bool) env('BACKGROUND_WORK_BACKPRESSURE_ENABLED', true),
        'pause_load_ratio' => (float) env('BACKGROUND_WORK_PAUSE_LOAD_RATIO', 0.75),
        'resume_load_ratio' => (float) env('BACKGROUND_WORK_RESUME_LOAD_RATIO', 0.60),
        'pause_available_memory_percent' => (float) env('BACKGROUND_WORK_PAUSE_AVAILABLE_MEMORY_PERCENT', 15),
        'resume_available_memory_percent' => (float) env('BACKGROUND_WORK_RESUME_AVAILABLE_MEMORY_PERCENT', 20),
        'pause_dependency_latency_ms' => (int) env('BACKGROUND_WORK_PAUSE_DEPENDENCY_LATENCY_MS', 100),
        'resume_dependency_latency_ms' => (int) env('BACKGROUND_WORK_RESUME_DEPENDENCY_LATENCY_MS', 50),
        'pause_outbox_age_seconds' => (int) env('BACKGROUND_WORK_PAUSE_OUTBOX_AGE_SECONDS', 5),
        'resume_outbox_age_seconds' => (int) env('BACKGROUND_WORK_RESUME_OUTBOX_AGE_SECONDS', 2),
        'healthy_samples' => (int) env('BACKGROUND_WORK_HEALTHY_SAMPLES', 2),
        'poll_seconds' => (int) env('BACKGROUND_WORK_POLL_SECONDS', 15),
        'state_path' => storage_path('tmux/background-work-pressure.json'),
    ],
    'search_outbox' => [
        'batch_size' => (int) env('SEARCH_INDEX_OUTBOX_BATCH_SIZE', 1000),
        'debounce_seconds' => (int) env('SEARCH_INDEX_OUTBOX_DEBOUNCE_SECONDS', 2),
    ],
];

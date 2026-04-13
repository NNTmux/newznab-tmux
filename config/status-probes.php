<?php

declare(strict_types=1);

return [
    'disk' => [
        'mount_points' => array_filter(array_map('trim', explode(',', (string) env('STATUS_PROBE_DISK_MOUNT_POINTS', '/')))),
        'warning_threshold_gb' => (float) env('STATUS_PROBE_DISK_WARNING_THRESHOLD_GB', 5),
        'critical_threshold_gb' => (float) env('STATUS_PROBE_DISK_CRITICAL_THRESHOLD_GB', 1),
    ],
    'nntp' => [
        'check_alternate' => (bool) env('STATUS_PROBE_NNTP_CHECK_ALTERNATE', false),
        'timeout_seconds' => (int) env('STATUS_PROBE_NNTP_TIMEOUT_SECONDS', 10),
    ],
    'redis' => [
        'probe' => (bool) env('STATUS_PROBE_REDIS', true),
        'only_when_used' => (bool) env('STATUS_PROBE_REDIS_ONLY_WHEN_USED', true),
    ],
];

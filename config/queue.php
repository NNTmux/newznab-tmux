<?php

$queue = require base_path('vendor/laravel/framework/config/queue.php');
$queue['connections']['redis']['retry_after'] = (int) env('REDIS_QUEUE_RETRY_AFTER', 180);

return $queue;

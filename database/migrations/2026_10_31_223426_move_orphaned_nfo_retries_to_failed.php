<?php

declare(strict_types=1);

use App\Services\NfoService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A download failure at the lowest retried nfostatus used to decrement one step further, to a value no
 * NFO query selects. Move those releases to NFO_FAILED so the archive fallback pass can still try them.
 */
return new class extends Migration
{
    /** Same default and clamp as NfoService::getMaxRetries(). */
    private const int DEFAULT_MAX_NFO_RETRIES = 5;

    private const int LOWEST_RETRY_STATUS = -8;

    public function up(): void
    {
        if (! Schema::hasTable('releases')) {
            return;
        }

        $floor = $this->retryFloor();
        if ($floor <= self::LOWEST_RETRY_STATUS) {
            return;
        }

        DB::table('releases')
            ->whereBetween('nfostatus', [self::LOWEST_RETRY_STATUS, $floor - 1])
            ->update(['nfostatus' => NfoService::NFO_FAILED]);
    }

    public function down(): void
    {
        // The original retry counters are not kept; NFO_FAILED rows stay where they are.
    }

    private function retryFloor(): int
    {
        $maxRetries = self::DEFAULT_MAX_NFO_RETRIES;
        if (Schema::hasTable('post_processing_configurations')) {
            $configured = DB::table('post_processing_configurations')->value('max_nfo_retries');
            if ($configured !== null) {
                $maxRetries = (int) $configured;
            }
        }

        $floor = $maxRetries >= 0 ? -($maxRetries + 1) : NfoService::NFO_UNPROC;

        return max($floor, self::LOWEST_RETRY_STATUS);
    }
};

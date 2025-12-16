<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Settings;
use App\Models\UsenetGroup;
use App\Services\Binaries\BinariesService;
use Blacklight\ColorCLI;
use Blacklight\NNTP;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateBinaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:binaries
                            {group? : Group name to update (optional, processes all if omitted)}
                            {max? : Maximum headers to download}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update binaries for a specific group or all groups';

    private ColorCLI $colorCLI;

    private bool $echoCLI;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->colorCLI = new ColorCLI();
        $this->echoCLI = (bool) config('nntmux.echocli');

        $groupName = $this->argument('group');
        $max = $this->argument('max');

        $maxHeaders = is_numeric($max) && $max > 0
            ? (int) $max
            : ((int) Settings::settingValue('max_headers_iteration') ?: 1000000);

        $startTime = now()->toImmutable();

        try {
            $this->outputBanner();

            $nntp = $this->getNntp();
            $binaries = new BinariesService();
            $binaries->setNntp($nntp);

            if ($groupName && !is_numeric($groupName)) {
                $this->outputHeader('Updating Single Group');
                $this->outputInfo("Group: {$groupName}");
                $this->outputInfo('Max headers: ' . number_format($maxHeaders));
                $this->updateSingleGroup($binaries, $groupName, $maxHeaders);
            } else {
                $this->outputHeader('Updating All Groups');
                $this->outputInfo('Max headers: ' . number_format($maxHeaders));
                $binaries->updateAllGroups($maxHeaders);
            }

            $this->outputSummary($startTime);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            $this->colorCLI->error('Error: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Update a single group.
     */
    private function updateSingleGroup(BinariesService $binaries, string $groupName, int $maxHeaders): void
    {
        $group = UsenetGroup::getByName($groupName);

        if ($group === null) {
            throw new \RuntimeException("Group not found: {$groupName}");
        }

        $binaries->updateGroup($group->toArray(), $maxHeaders);
    }

    /**
     * Get NNTP connection.
     */
    private function getNntp(): NNTP
    {
        $nntp = new NNTP();

        if ($nntp->doConnect() !== true) {
            throw new \RuntimeException('Unable to connect to usenet.');
        }

        return $nntp;
    }

    /**
     * Output the banner.
     */
    private function outputBanner(): void
    {
        if (!$this->echoCLI) {
            return;
        }

        echo PHP_EOL;
        $this->colorCLI->header('NNTmux Binary Update');
        $this->colorCLI->info('Started: ' . now()->format('Y-m-d H:i:s'));
    }

    /**
     * Output a section header.
     */
    private function outputHeader(string $title): void
    {
        if (!$this->echoCLI) {
            return;
        }

        echo PHP_EOL;
        $this->colorCLI->header(strtoupper($title));
        $this->colorCLI->header(str_repeat('-', strlen($title)));
    }

    /**
     * Output an info line.
     */
    private function outputInfo(string $message): void
    {
        if (!$this->echoCLI) {
            return;
        }

        $this->colorCLI->info("  {$message}");
    }

    /**
     * Output the summary.
     */
    private function outputSummary(\DateTimeInterface $startTime): void
    {
        if (!$this->echoCLI) {
            return;
        }

        $elapsed = now()->diffInSeconds($startTime, true);
        $timeStr = $this->formatElapsedTime($elapsed);

        echo PHP_EOL;
        $this->colorCLI->header('COMPLETE');
        $this->colorCLI->header('--------');
        $this->colorCLI->info("  Total time: {$timeStr}");
        echo PHP_EOL;
    }

    /**
     * Format elapsed time.
     */
    private function formatElapsedTime(int|float $seconds): string
    {
        if ($seconds < 1) {
            return sprintf('%dms', (int) ($seconds * 1000));
        }

        if ($seconds < 60) {
            return sprintf('%.1fs', $seconds);
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return sprintf('%dm %ds', $minutes, (int) $remainingSeconds);
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return sprintf('%dh %dm', $hours, $remainingMinutes);
    }
}

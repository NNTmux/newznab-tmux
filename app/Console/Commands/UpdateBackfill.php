<?php

namespace App\Console\Commands;

use App\Services\Backfill\BackfillService;
use Blacklight\NNTP;
use Illuminate\Console\Command;

class UpdateBackfill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:backfill
                            {mode=all : Mode: all, alph, date, safe, or group name}
                            {quantity? : Number of articles to backfill}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill groups by various methods';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $mode = $this->argument('mode');
        $quantity = $this->argument('quantity');

        try {
            $nntp = $this->getNntp();
            $backfill = new BackfillService(nntp: $nntp);

            match (true) {
                $mode === 'all' && ! isset($quantity) => $backfill->backfillAllGroups(),
                $mode === 'alph' && is_numeric($quantity) => $backfill->backfillAllGroups('', (int) $quantity, 'normal'),
                $mode === 'date' && is_numeric($quantity) => $backfill->backfillAllGroups('', (int) $quantity, 'date'),
                $mode === 'safe' && is_numeric($quantity) => $backfill->safeBackfill((int) $quantity),
                preg_match('/^alt\.binaries\..+$/i', $mode) && ! isset($quantity) => $backfill->backfillAllGroups($mode),
                preg_match('/^alt\.binaries\..+$/i', $mode) && is_numeric($quantity) => $backfill->backfillAllGroups($mode, (int) $quantity),
                default => throw new \Exception($this->getHelpText()),
            };

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Get help text.
     */
    private function getHelpText(): string
    {
        return "Wrong set of arguments.\n\n"
            ."Examples:\n"
            ."  php artisan update:backfill all                           - Backfills all groups 1 at a time, by date\n"
            ."  php artisan update:backfill alt.binaries.ath              - Backfills a group by name, by date\n"
            ."  php artisan update:backfill alt.binaries.ath 200000       - Backfills a group by name by number of articles\n"
            ."  php artisan update:backfill alph 200000                   - Backfills all groups (alphabetically) by number of articles\n"
            ."  php artisan update:backfill date 200000                   - Backfills all groups (by least backfilled) by number of articles\n"
            ."  php artisan update:backfill safe 200000                   - Safe backfill, stops at 2012-06-24\n";
    }

    /**
     * Get NNTP connection.
     */
    private function getNntp(): NNTP
    {
        $nntp = new NNTP;

        if ($nntp->doConnect() !== true) {
            throw new \Exception('Unable to connect to usenet.');
        }

        return $nntp;
    }
}

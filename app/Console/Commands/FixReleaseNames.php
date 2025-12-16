<?php

namespace App\Console\Commands;

use App\Services\NameFixing\NameFixingService;
use Blacklight\NNTP;
use Illuminate\Console\Command;

class FixReleaseNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'releases:fix-names
                                 {method : The method number (3-20) to use for fixing names}
                                 {--update : Actually update the names, otherwise just display results}
                                 {--category=other : Category to process: "other", "all", or "predb_id"}
                                 {--set-status : Set releases as checked after processing}
                                 {--show : Display detailed release changes rather than just counters}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix release names using various methods (NFO, file name, Par2, etc.)';

    /**
     * Execute the console command.
     */
    public function handle(NameFixingService $nameFixingService, NNTP $nntp): int
    {
        $method = $this->argument('method');
        $update = (bool) $this->option('update');
        $setStatus = (bool) $this->option('set-status');
        $show = $this->option('show');

        // Set category option
        $categoryOption = $this->option('category');
        $other = 1;
        if ($categoryOption === 'all') {
            $other = 2;
        } elseif ($categoryOption === 'predb_id') {
            $other = 3;
        }

        // Connect to NNTP if method requires it
        if ($method === '7' || $method === '8') {
            $compressedHeaders = config('nntmux_nntp.compressed_headers');
            if ((config('nntmux_nntp.use_alternate_nntp_server') === true ?
                $nntp->doConnect($compressedHeaders, true) :
                $nntp->doConnect()) !== true) {
                $this->error('Unable to connect to usenet.');

                return 1;
            }
        }

        switch ($method) {
            case '3':
                $nameFixingService->fixNamesWithNfo(1, $update, $other, $setStatus, $show);
                break;
            case '4':
                $nameFixingService->fixNamesWithNfo(2, $update, $other, $setStatus, $show);
                break;
            case '5':
                $nameFixingService->fixNamesWithFiles(1, $update, $other, $setStatus, $show);
                break;
            case '6':
                $nameFixingService->fixNamesWithFiles(2, $update, $other, $setStatus, $show);
                break;
            case '7':
                $nameFixingService->fixNamesWithPar2(1, $update, $other, $setStatus, $show, $nntp);
                break;
            case '8':
                $nameFixingService->fixNamesWithPar2(2, $update, $other, $setStatus, $show, $nntp);
                break;
            case '9':
                $nameFixingService->fixNamesWithMedia(1, $update, $other, $setStatus, $show);
                break;
            case '10':
                $nameFixingService->fixNamesWithMedia(2, $update, $other, $setStatus, $show);
                break;
            case '11':
                $nameFixingService->fixXXXNamesWithFiles(1, $update, $other, $setStatus, $show);
                break;
            case '12':
                $nameFixingService->fixXXXNamesWithFiles(2, $update, $other, $setStatus, $show);
                break;
            case '13':
                $nameFixingService->fixNamesWithSrr(1, $update, $other, $setStatus, $show);
                break;
            case '14':
                $nameFixingService->fixNamesWithSrr(2, $update, $other, $setStatus, $show);
                break;
            case '15':
                $nameFixingService->fixNamesWithParHash(1, $update, $other, $setStatus, $show);
                break;
            case '16':
                $nameFixingService->fixNamesWithParHash(2, $update, $other, $setStatus, $show);
                break;
            case '17':
                $nameFixingService->fixNamesWithMediaMovieName(1, $update, $other, $setStatus, $show);
                break;
            case '18':
                $nameFixingService->fixNamesWithMediaMovieName(2, $update, $other, $setStatus, $show);
                break;
            case '19':
                $nameFixingService->fixNamesWithCrc(1, $update, $other, $setStatus, $show);
                break;
            case '20':
                $nameFixingService->fixNamesWithCrc(2, $update, $other, $setStatus, $show);
                break;
            default:
                $this->showHelp();

                return 1;
        }

        return 0;
    }

    /**
     * Display detailed help information.
     */
    protected function showHelp(): void
    {
        $this->info('');
        $this->info('Usage Examples:');
        $this->info('');
        $this->info('php artisan releases:fix-names 3 : Fix release names using NFO in the past 6 hours.');
        $this->info('php artisan releases:fix-names 4 : Fix release names using NFO.');
        $this->info('php artisan releases:fix-names 5 : Fix release names in misc categories using File Name in the past 6 hours.');
        $this->info('php artisan releases:fix-names 6 : Fix release names in misc categories using File Name.');
        $this->info('php artisan releases:fix-names 7 : Fix release names in misc categories using Par2 Files in the past 6 hours.');
        $this->info('php artisan releases:fix-names 8 : Fix release names in misc categories using Par2 Files.');
        $this->info('php artisan releases:fix-names 9 : Fix release names in misc categories using UID in the past 6 hours.');
        $this->info('php artisan releases:fix-names 10 : Fix release names in misc categories using UID.');
        $this->info('php artisan releases:fix-names 11 : Fix SDPORN XXX release names using specific File Name in the past 6 hours.');
        $this->info('php artisan releases:fix-names 12 : Fix SDPORN XXX release names using specific File Name.');
        $this->info('php artisan releases:fix-names 13 : Fix release names using SRR files in the past 6 hours.');
        $this->info('php artisan releases:fix-names 14 : Fix release names using SRR files.');
        $this->info('php artisan releases:fix-names 15 : Fix release names using PAR2 hash_16K block in the past 6 hours.');
        $this->info('php artisan releases:fix-names 16 : Fix release names using PAR2 hash_16K block.');
        $this->info('php artisan releases:fix-names 17 : Fix release names using Mediainfo in the past 6 hours.');
        $this->info('php artisan releases:fix-names 18 : Fix release names using Mediainfo.');
        $this->info('php artisan releases:fix-names 19 : Fix release names using CRC32 in the past 6 hours.');
        $this->info('php artisan releases:fix-names 20 : Fix release names using CRC32.');
        $this->info('');
        $this->info('Options:');
        $this->info('  --update           Actually update the names (default: only display potential changes)');
        $this->info('  --category=VALUE   Process "other" categories (default), "all" categories, or "predb_id" for unmatched');
        $this->info('  --set-status       Mark releases as checked so they won\'t be processed again');
        $this->info('  --show             Display detailed release changes (default: only show counters)');
        $this->info('');
    }
}

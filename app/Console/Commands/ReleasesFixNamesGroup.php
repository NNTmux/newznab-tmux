<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\NameFixing\NameFixingQueryService;
use App\Services\NameFixing\NameFixingService;
use App\Services\NfoService;
use App\Services\NNTP\NNTPService;
use App\Services\Nzb\NzbContentsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReleasesFixNamesGroup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'releases:fix-names-group
                            {type : Type of fix (standard|predbft)}
                            {--guid-char= : GUID character to process (for standard type)}
                            {--limit=1000 : Maximum releases to process}
                            {--thread=1 : Worker number (for predbft type)}
                            {--workers=1 : Total workers (for predbft type)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix release names using various methods (group-based processing)';

    private NameFixingService $nameFixingService;

    private NameFixingQueryService $queries;

    private int $checked = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->argument('type');
        $maxPerRun = (int) $this->option('limit');

        $this->nameFixingService = app(NameFixingService::class);
        $this->queries = app(NameFixingQueryService::class);

        switch ($type) {
            case 'standard':
                return $this->processStandard($maxPerRun);

            case 'predbft':
                return $this->processPredbFulltext($maxPerRun);

            default:
                $this->error("Invalid type: {$type}. Use 'standard' or 'predbft'");

                return Command::FAILURE;
        }
    }

    /**
     * Process standard name fixing
     */
    protected function processStandard(int $maxPerRun): int
    {
        $guidChar = $this->option('guid-char');

        if ($guidChar === null) {
            $this->error('--guid-char is required for standard type');

            return Command::FAILURE;
        }

        $this->info("Processing releases with GUID starting with: {$guidChar}");
        $this->info("Maximum per run: {$maxPerRun}");

        $nntp = null;
        $nzbcontents = null;
        $connectionAttempted = false;
        $par2Processor = function (object $release) use (&$connectionAttempted, &$nntp, &$nzbcontents): bool {
            if (! $connectionAttempted) {
                $connectionAttempted = true;
                $nntp = app(NNTPService::class);
                $compressedHeaders = config('nntmux_nntp.compressed_headers');
                $connectResult = config('nntmux_nntp.use_alternate_nntp_server') === true
                    ? $nntp->doConnect($compressedHeaders, true)
                    : $nntp->doConnect();

                if ($connectResult !== true) {
                    $errorMessage = 'Unable to connect to usenet for PAR2 processing';
                    if (NNTPService::isError($connectResult)) {
                        $errorMessage .= ' Error: '.$connectResult->getMessage();
                    }
                    $this->warn($errorMessage);
                } else {
                    $nzbcontents = app(NzbContentsService::class);
                    $nzbcontents->setNntp($nntp);
                    $nzbcontents->setNfo(app(NfoService::class));
                    $nzbcontents->setEchoOutput(false);
                }
            }

            return $nzbcontents !== null && $nzbcontents->checkPar2(
                $release->guid,
                $release->releases_id,
                $release->groups_id,
                1,
                1
            );
        };

        $stats = $this->nameFixingService->processStandardBatch($guidChar, $maxPerRun, true, $par2Processor);
        $this->checked += $stats['checked'];

        if ($stats['checked'] === 0) {
            $this->info('No releases to process');

            return Command::SUCCESS;
        }

        $this->info("Processed {$stats['checked']} releases");
        $this->info("Fixed {$stats['fixed']} release names");

        return Command::SUCCESS;
    }

    /**
     * Process PreDB fulltext matching
     */
    protected function processPredbFulltext(int $maxPerRun): int
    {
        $thread = (int) $this->option('thread');
        $workers = (int) $this->option('workers');

        $this->info('Processing PreDB fulltext matching');
        $this->info("Worker: {$thread}/{$workers}, Limit: {$maxPerRun}");

        $pres = $this->queries->predbBatch($thread, $workers, $maxPerRun);

        if ($pres === []) {
            $this->info('No PreDB entries to process');

            return Command::SUCCESS;
        }

        $this->info('Found '.count($pres).' PreDB entries to process');
        $bar = $this->output->createProgressBar(count($pres));
        $bar->start();

        foreach ($pres as $pre) {
            $searched = 0;

            try {
                $ftmatched = $this->nameFixingService->matchPredbFulltext($pre);
            } catch (RuntimeException $exception) {
                $bar->finish();
                $this->newLine(2);
                $this->error($exception->getMessage());

                return Command::FAILURE;
            }

            if ($ftmatched > 0) {
                $searched = 1;
            } elseif ($ftmatched < 0) {
                $searched = -6;
            } else {
                $searched = (int) $pre->searched - 1;
            }

            DB::update('UPDATE predb SET searched = ? WHERE id = ?', [$searched, $pre->predb_id]);
            $this->checked++;

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Processed {$this->checked} PreDB entries");

        return Command::SUCCESS;
    }
}

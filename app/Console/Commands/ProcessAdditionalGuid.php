<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Facades\Search;
use App\Models\Release;
use App\Services\AdditionalProcessing\AdditionalProcessingOrchestrator;
use App\Services\AdditionalProcessing\DTO\ReleaseProcessingResult;
use Illuminate\Console\Command;

class ProcessAdditionalGuid extends Command
{
    protected $signature = 'releases:additional
                                {guid? : Release GUID (optional if using --id)}
                                {--id= : Numeric release ID to run additional postprocessing on (alternative to GUID)}
                                {--reset : Reset key additional postprocessing flags before running}
                                {--no-progress : Suppress progress / status output (forces quiet mode)}
                                {--profile : Display stage, network, and persistence metrics}';

    protected $description = 'Run additional postprocessing for a specific release by GUID or ID regardless of prior postprocessing status';

    public function handle(): int
    {
        $guidArg = trim((string) $this->argument('guid'));
        $idOpt = $this->option('id');

        if ($guidArg === '' && ($idOpt === null || $idOpt === '')) {
            $this->error('You must supply either a GUID argument or --id=<release id>.');

            return 1; // missing identifier
        }

        if ($guidArg !== '' && $idOpt !== null && $idOpt !== '') {
            $this->error('Provide only one identifier: GUID or --id, not both.');

            return 4; // conflicting identifiers
        }

        $release = null;
        $guid = '';

        if ($idOpt !== null && $idOpt !== '') {
            if (! ctype_digit((string) $idOpt)) {
                $this->error('Release ID must be numeric.');

                return 5; // invalid id format
            }
            $release = Release::find((int) $idOpt);
            if ($release === null) {
                $this->error('Release not found for ID: '.$idOpt);

                return 2; // not found
            }
            $guid = $release->guid;
        } else {
            $guid = $guidArg;
            if ($guid === '') { // should not happen but guard
                $this->error('GUID is required if --id not supplied.');

                return 1;
            }
            $release = Release::where('guid', $guid)->first();
            if ($release === null) {
                $this->error('Release not found for GUID: '.$guid);

                return 2;
            }
        }

        if ($this->option('reset')) {
            Release::where('id', $release->id)->update([
                'passwordstatus' => -1,
                'haspreview' => -1,
                'jpgstatus' => 0,
                'videostatus' => 0,
                'audiostatus' => 0,
                'nfostatus' => -1,
            ]);
            Search::updateRelease((int) $release->id);
            $this->info('Reset postprocessing flags for release ID '.$release->id.' (GUID '.$guid.')');
        }

        if ($this->option('no-progress')) {
            config(['nntmux.echocli' => false]);
        }

        $processor = app(AdditionalProcessingOrchestrator::class);

        try {
            $result = $processor->processSingleGuid($guid);
        } finally {
            $processor->finish();
        }

        $identifier = $idOpt ? 'ID '.$idOpt : 'GUID '.$guid;

        $this->displayResult($result, $identifier);

        if ($this->option('profile')) {
            $this->displayProfile($result);
        }

        if (! $result->isSuccessful()) {
            return 3;
        }

        return 0;
    }

    private function displayResult(ReleaseProcessingResult $result, string $identifier): void
    {
        $rows = [
            ['Release', $identifier],
            ['Pipeline', 'v2'],
            ['Outcome', $result->outcome->value],
            ['Artifacts created', $result->artifactsCreated ? 'yes' : 'no'],
            ['Release files added', (string) $result->releaseFilesAdded],
            ['Duration', number_format($result->elapsedSeconds, 3).' seconds'],
        ];

        if ($result->reason !== '') {
            $rows[] = ['Reason', $result->reason];
        }

        $this->table(['Result', 'Value'], $rows);

        $message = 'Additional postprocessing ended with outcome '.$result->outcome->value.' for '.$identifier
            .' after '.number_format($result->elapsedSeconds, 3).' seconds.';

        if ($result->isSuccessful()) {
            $this->info($message);

            return;
        }

        $this->error($message);
    }

    private function displayProfile(ReleaseProcessingResult $result): void
    {
        $stageRows = [];
        foreach ($result->stageDurations as $stage => $duration) {
            $stageRows[] = [$stage, number_format($duration, 3)];
        }

        if ($stageRows !== []) {
            $this->newLine();
            $this->line('Stage profile');
            $this->table(['Stage', 'Seconds'], $stageRows);
        }

        if ($result->downloadMetrics !== null) {
            $this->newLine();
            $this->line('Download profile');
            $this->table(['Metric', 'Value'], [
                ['Logical requests', (string) $result->downloadMetrics->logicalRequests],
                ['Network requests', (string) $result->downloadMetrics->networkRequests],
                ['Cache hits', (string) $result->downloadMetrics->cacheHits],
                ['Bytes downloaded', (string) $result->downloadMetrics->bytesDownloaded],
                ['Bytes reused', (string) $result->downloadMetrics->bytesReused],
            ]);
        }

        if ($result->persistenceMetrics !== null) {
            $this->newLine();
            $this->line('Persistence profile');
            $this->table(['Metric', 'Value'], [
                ['Database statements', (string) $result->persistenceMetrics->databaseStatements],
                ['Database milliseconds', number_format($result->persistenceMetrics->databaseMilliseconds, 3)],
                ['Search sync requests', (string) $result->persistenceMetrics->searchSyncRequests],
                ['Search sync executions', (string) $result->persistenceMetrics->searchSyncExecutions],
            ]);
        }
    }
}

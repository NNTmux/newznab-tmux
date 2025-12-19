<?php

namespace App\Console\Commands;

use App\Models\Release;
use App\Services\AdditionalProcessing\AdditionalProcessingOrchestrator;
use Illuminate\Console\Command;

class ProcessAdditionalGuid extends Command
{
    protected $signature = 'releases:additional
                                {guid? : Release GUID (optional if using --id)}
                                {--id= : Numeric release ID to run additional postprocessing on (alternative to GUID)}
                                {--reset : Reset key additional postprocessing flags before running}
                                {--no-progress : Suppress progress / status output (forces quiet mode)}';

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
            $this->info('Reset postprocessing flags for release ID '.$release->id.' (GUID '.$guid.')');
        }

        if ($this->option('no-progress')) {
            config(['nntmux.echocli' => false]);
        }

        $processor = app(AdditionalProcessingOrchestrator::class);
        $ok = $processor->processSingleGuid($guid);

        if (! $ok) {
            $this->error('Processing failed or nothing processed for '.($idOpt ? 'ID '.$idOpt : 'GUID '.$guid).'.');

            return 3;
        }

        $this->info('Additional postprocessing completed for '.($idOpt ? 'ID '.$idOpt : 'GUID '.$guid).'.');

        return 0;
    }
}

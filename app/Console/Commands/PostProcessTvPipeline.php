<?php

namespace App\Console\Commands;

use App\Services\TvProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PostProcessTvPipeline extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'postprocess:tv-pipeline
                            {guid : First character of release guid (a-f, 0-9)}
                            {renamed? : Process renamed only (optional)}
                            {--mode=pipeline : Processing mode: pipeline or parallel}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Post process TV releases by GUID character using pipelined providers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $guid = $this->argument('guid');
        $renamed = $this->argument('renamed') ?? '';
        $mode = $this->option('mode') ?? 'pipeline';

        if (! $this->isValidChar($guid)) {
            $this->error('GUID character must be a-f or 0-9.');

            return self::FAILURE;
        }

        if (! in_array($mode, ['pipeline', 'parallel'], true)) {
            $this->error('Mode must be either "pipeline" or "parallel".');

            return self::FAILURE;
        }

        try {
            $tvProcessor = new TvProcessor(true); // true = echo output
            $tvProcessor->process('', $guid, $renamed, $mode);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error($e->getTraceAsString());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Check if the character contains a-f or 0-9.
     */
    private function isValidChar(string $char): bool
    {
        return \in_array(
            $char,
            ['a', 'b', 'c', 'd', 'e', 'f', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            true
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ForkingService;
use Illuminate\Console\Command;

class ProcessPostProcess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'multiprocessing:postprocess
                            {type : Type: ama, add, ani, mov, nfo or tv}
                            {renamed=false : For mov/tv: only post-process renamed releases (true/false)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Post-process releases using multiprocessing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->warn('⚠️  WARNING: This command is to be used from cli.');
        $this->line('');

        $type = $this->argument('type');
        $renamed = $this->argument('renamed');

        if (! \in_array($type, ['ama', 'add', 'ani', 'mov', 'nfo', 'tv'], true)) {
            $this->error('Type must be one of: ama, add, ani, mov, nfo, sha, tv');
            $this->line('');
            $this->line('ama => Do amazon/books processing');
            $this->line('add => Do additional (rar|zip) processing');
            $this->line('ani => Do anime processing');
            $this->line('mov => Do movie processing');
            $this->line('nfo => Do NFO processing');
            $this->line('tv  => Do TV processing');

            return self::FAILURE;
        }

        try {
            $renamedOnly = $renamed === 'true' || $renamed === true;
            $service = new ForkingService;

            match ($type) {
                'ama' => $service->processBooks(),
                'add' => $service->processAdditional(),
                'ani' => $service->processAnime(),
                'mov' => $service->processMovies($renamedOnly),
                'nfo' => $service->processNfo(),
                'tv' => $service->processTv($renamedOnly),
            };

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReleaseRemoverService;
use Illuminate\Console\Command;

class RemoveCrapReleases extends Command
{
    protected $signature = 'releases:remove-crap
                            {--type= : Type of crap to remove}
                            {--time=full : Time limit in hours or full}
                            {--blacklist-id= : Specific blacklist ID}
                            {--delete : Actually delete releases}';

    protected $description = 'Remove crap releases based on various criteria';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $type = $this->option('type') ?? '';
        $time = $this->option('time') ?? 'full';
        $blacklistId = $this->option('blacklist-id') ?? '';
        $delete = $this->option('delete');

        if (! $delete) {
            $this->warn('Running in DRY-RUN mode. Use --delete to actually remove releases.');
        }

        try {
            $remover = new ReleaseRemoverService;
            $result = $remover->removeCrap($delete, $time, $type, $blacklistId);

            if ($result === true) {
                return self::SUCCESS;
            }

            $this->error($result);

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}

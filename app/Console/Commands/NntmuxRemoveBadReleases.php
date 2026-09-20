<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Release;
use App\Models\ReleaseFile;
use App\Services\Nzb\NzbService;
use App\Services\ReleaseImageService;
use App\Support\ReleaseSearchIndexSync;
use Illuminate\Console\Command;

class NntmuxRemoveBadReleases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:remove-bad';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update releases that have passworded files inside archives and remove releases that cannot be PPA\'d properly';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     *
     * @throws \Exception
     */
    public function handle(): void
    {
        // Select releases with password status -2 and smaller and delete them. Also delete the files from the filesystem.
        $badReleases = Release::query()->where('passwordstatus', '<=', -2)->get();
        foreach ($badReleases as $badRelease) {
            app(NzbService::class)->deleteNzb($badRelease->guid);
            (new ReleaseImageService)->delete($badRelease->guid);
            $badRelease->delete();
        }
        Release::query()->where('passwordstatus', '=', -2)->delete();

        $passReleases = ReleaseFile::query()->where('passworded', '=', 1)->groupBy('releases_id')->get();

        $count = 0;
        foreach ($passReleases as $passRelease) {
            $releasesId = (int) $passRelease->releases_id;
            Release::whereId($releasesId)->update(['passwordstatus' => 1]);
            ReleaseSearchIndexSync::forIds([$releasesId]);
            $count++;
        }

        $this->info('Updated '.$count.' bad releases');
    }
}

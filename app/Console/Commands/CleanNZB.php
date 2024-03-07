<?php

namespace App\Console\Commands;

use App\Models\Release;
use App\Models\Settings;
use Blacklight\NZB;
use Blacklight\ReleaseImage;
use Blacklight\Releases;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class CleanNZB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:nzbclean
    {--notindb : Delete NZBs that dont exist in database}
    {--notondisk : Delete release in database that dont have a NZB on disk}
    {--chunksize=25000 : Chunk size for releases query}
    {--delete : Pass this argument to actually delete the files. Otherwise it\'s just a dry run.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find NZBs that dont have a release, or releases that have no NZBs.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Check if any options are false
        if (! $this->option('notindb') && ! $this->option('notondisk')) {
            $this->error('You must specify at least one option. See: --help');
            exit();
        }
        if ($this->option('notindb')) {
            $this->GetNZBsWithNoDatabaseEntry($this->option('delete'));
        }
        if ($this->option('notondisk')) {
            $this->GetReleasesWithNoNZBOnDisk($this->option('delete'));
        }
    }

    private function GetNZBsWithNoDatabaseEntry($delete = false)
    {
        $this->info('Getting list of NZB files on disk to check if they exist in database');
        $releases = new Release();
        $checked = $deleted = 0;
        // Get the list of NZBs in the NZB folder
        $dirItr = new \RecursiveDirectoryIterator(Settings::settingValue('..nzbpath'));
        $itr = new \RecursiveIteratorIterator($dirItr, \RecursiveIteratorIterator::LEAVES_ONLY);

        // Checking filename GUIDs against the releases table
        foreach ($itr as $filePath) {
            $guid = stristr($filePath->getFilename(), '.nzb.gz', true);
            if (File::isFile($filePath) && $guid) {
                // If NZB file guid is not present in DB delete the file from disk
                if (! $releases->whereGuid($guid)->exists()) {
                    if ($delete) {
                        File::delete($filePath);
                    }
                    $deleted++;
                    $this->line("Deleted orphan file: $guid.nzb.gz");
                }
                $checked++;
            }
            echo "Checked: $checked / Deleted: $deleted\r";
        }
        $this->info("Checked: $checked / Deleted: $deleted");
    }

    private function GetReleasesWithNoNZBOnDisk($delete = false)
    {
        // Setup
        $nzb = new NZB();
        $rel = new Releases();
        $checked = $deleted = 0;

        $this->info('Getting list of releases from database to check if they have a corresponding NZB on disk');
        $total = Release::count();
        $this->alert("Total releases to check: $total");

        Release::where('nzbstatus', 1)->chunkById((int) $this->option('chunksize'), function (Collection $releases) use ($delete, &$checked, &$deleted, $nzb, $rel) {
            echo 'Total done: '.$checked."\r";
            foreach ($releases as $r) {

                if (! $nzb->NZBPath($r->guid)) {
                    if ($delete) {
                        $rel->deleteSingle(['g' => $r->guid, 'i' => $r->id], $nzb, new ReleaseImage());
                    }
                    $deleted++;
                    $this->line("Deleted: $r->searchname -> $r->guid");
                }
                $checked++;
            }
        });
        $this->info("Checked: $checked / Deleted: $deleted");
    }
}

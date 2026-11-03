<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FindImdbMatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:find-imdb-matches';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find releases with IMDB IDs and match them against videos table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $releases = DB::table('releases')->whereRaw("searchname REGEXP '^tt[0-9]{7,8}.*'")->get();

        $count = 0;
        foreach ($releases as $release) {
            // Extract IMDB ID using regex
            if (preg_match('/^tt([0-9]{7,8})/', $release->searchname, $matches)) {
                $imdbId = $matches[1]; // Get the ID without 'tt' prefix

                // Look for matching video
                $video = DB::table('videos')
                    ->where('imdb', $imdbId)
                    ->first();

                if ($video) {
                    $count++;
                    $this->info('Match found:');
                    $this->line("Release: {$release->searchname}");
                    $this->line("Video: {$video->title}");
                    $this->line('---');
                }
            }
        }

        $this->info("Found {$count} matches in total.");

        return self::SUCCESS;
    }
}

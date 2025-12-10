<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SteamApp;
use App\Services\SteamService;
use Illuminate\Console\Command;

use function Laravel\Prompts\progress;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\table;

class SteamSearchGames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'steam:search
                            {query : The search query}
                            {--limit=10 : Maximum number of results}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Search for games in the local Steam database';

    /**
     * Execute the console command.
     */
    public function handle(SteamService $steamService): int
    {
        $query = $this->argument('query');
        $limit = (int) $this->option('limit');

        info("Searching for: {$query}");

        $results = $steamService->searchMultiple($query, $limit);

        if ($results->isEmpty()) {
            warning('No games found matching your query.');
            return Command::SUCCESS;
        }

        info("Found {$results->count()} result(s):");

        $tableData = $results->map(fn($r) => [
            $r['appid'],
            substr($r['name'], 0, 60),
            number_format($r['score'], 1) . '%',
            'https://store.steampowered.com/app/' . $r['appid'],
        ])->toArray();

        table(
            ['App ID', 'Name', 'Score', 'Store URL'],
            $tableData
        );

        return Command::SUCCESS;
    }
}


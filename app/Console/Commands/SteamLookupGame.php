<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SteamService;
use Illuminate\Console\Command;

class SteamLookupGame extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'steam:lookup
                            {title : The game title to search for}
                            {--details : Show full game details}
                            {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Look up a game on Steam by title';

    /**
     * Execute the console command.
     */
    public function handle(SteamService $steamService): int
    {
        $title = $this->argument('title');
        $showDetails = $this->option('details');
        $outputJson = $this->option('json');

        $this->info("Searching for: {$title}");
        $this->newLine();

        // Search for the game
        $appId = $steamService->search($title);

        if ($appId === null) {
            $this->error('No matching game found.');

            return Command::FAILURE;
        }

        $this->info("Found game with Steam App ID: {$appId}");
        $this->newLine();

        if ($showDetails) {
            $details = $steamService->getGameDetails($appId);

            if ($details === false) {
                $this->error('Could not retrieve game details.');

                return Command::FAILURE;
            }

            if ($outputJson) {
                $this->line(json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->displayGameDetails($details);
            }

            // Get additional data
            $reviews = $steamService->getReviewsSummary($appId);
            $playerCount = $steamService->getPlayerCount($appId);

            if ($reviews !== null) {
                $this->newLine();
                $this->info('Reviews:');
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Rating', $reviews['review_score_desc'] ?? 'Unknown'],
                        ['Positive', number_format($reviews['total_positive'] ?? 0)],
                        ['Negative', number_format($reviews['total_negative'] ?? 0)],
                        ['Total', number_format($reviews['total_reviews'] ?? 0)],
                    ]
                );
            }

            if ($playerCount !== null) {
                $this->info('Current Players: '.number_format($playerCount));
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Display game details in a formatted way.
     */
    protected function displayGameDetails(array $details): void
    {
        $this->info('=== Game Details ===');
        $this->newLine();

        // Basic info table
        $basicInfo = [
            ['Title', $details['title'] ?? 'N/A'],
            ['Steam ID', $details['steamid'] ?? 'N/A'],
            ['Type', ucfirst($details['type'] ?? 'game')],
            ['Publisher', $details['publisher'] ?? 'N/A'],
            ['Developers', implode(', ', $details['developers'] ?? []) ?: 'N/A'],
            ['Release Date', $details['releasedate'] ?? 'N/A'],
            ['Genres', $details['genres'] ?? 'N/A'],
            ['Platforms', implode(', ', $details['platforms'] ?? [])],
        ];

        $this->table(['Property', 'Value'], $basicInfo);

        // Scores
        if (! empty($details['metacritic_score'])) {
            $this->newLine();
            $this->info("Metacritic Score: {$details['metacritic_score']}");
        }

        // Price
        if (! empty($details['price'])) {
            $price = $details['price'];
            $this->newLine();
            $this->info('Pricing:');

            $priceInfo = [
                ['Currency', $price['currency'] ?? 'USD'],
                ['Price', $price['final_formatted'] ?? sprintf('$%.2f', $price['final'] ?? 0)],
            ];

            if (($price['discount_percent'] ?? 0) > 0) {
                $priceInfo[] = ['Original', sprintf('$%.2f', $price['initial'] ?? 0)];
                $priceInfo[] = ['Discount', "{$price['discount_percent']}%"];
            }

            $this->table(['Field', 'Value'], $priceInfo);
        }

        // Description
        if (! empty($details['description'])) {
            $this->newLine();
            $this->info('Description:');
            $this->line(wordwrap($details['description'], 80));
        }

        // URLs
        $this->newLine();
        $this->info('Links:');
        $this->line("Store: {$details['directurl']}");

        if (! empty($details['website'])) {
            $this->line("Website: {$details['website']}");
        }

        if (! empty($details['metacritic_url'])) {
            $this->line("Metacritic: {$details['metacritic_url']}");
        }

        // Stats
        if (! empty($details['achievements']) || ! empty($details['recommendations'])) {
            $this->newLine();
            $this->info('Stats:');

            if (! empty($details['achievements'])) {
                $this->line("Achievements: {$details['achievements']}");
            }

            if (! empty($details['recommendations'])) {
                $this->line('Recommendations: '.number_format($details['recommendations']));
            }
        }

        // Categories (multiplayer, etc.)
        if (! empty($details['categories'])) {
            $this->newLine();
            $this->info('Features:');
            $this->line(implode(', ', $details['categories']));
        }
    }
}

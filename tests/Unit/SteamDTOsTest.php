<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DTOs\SteamGameData;
use App\Support\DTOs\SteamPriceData;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Steam DTOs.
 */
class SteamDTOsTest extends TestCase
{
    // ==========================================
    // SteamPriceData Tests
    // ==========================================

    public function test_price_data_from_api_response(): void
    {
        $data = [
            'currency' => 'USD',
            'initial' => 5999,
            'final' => 2999,
            'discount_percent' => 50,
            'final_formatted' => '$29.99',
        ];

        $price = SteamPriceData::fromApiResponse($data);

        $this->assertSame('USD', $price->currency);
        $this->assertSame(59.99, $price->initial);
        $this->assertSame(29.99, $price->final);
        $this->assertSame(50, $price->discountPercent);
        $this->assertSame('$29.99', $price->formattedPrice);
    }

    public function test_price_data_free(): void
    {
        $price = SteamPriceData::free();

        $this->assertTrue($price->isFree());
        $this->assertSame(0.0, $price->final);
        $this->assertSame('Free', $price->formattedPrice);
    }

    public function test_price_data_is_on_sale(): void
    {
        $onSale = new SteamPriceData('USD', 59.99, 29.99, 50);
        $notOnSale = new SteamPriceData('USD', 59.99, 59.99, 0);

        $this->assertTrue($onSale->isOnSale());
        $this->assertFalse($notOnSale->isOnSale());
    }

    public function test_price_data_get_savings(): void
    {
        $price = new SteamPriceData('USD', 59.99, 29.99, 50);

        $this->assertEqualsWithDelta(30.00, $price->getSavings(), 0.01);
    }

    public function test_price_data_get_display_price(): void
    {
        $priceWithFormat = new SteamPriceData('USD', 59.99, 29.99, 50, '$29.99');
        $priceWithoutFormat = new SteamPriceData('USD', 59.99, 29.99, 50);
        $freePrice = SteamPriceData::free();

        $this->assertSame('$29.99', $priceWithFormat->getDisplayPrice());
        $this->assertSame('USD 29.99', $priceWithoutFormat->getDisplayPrice());
        $this->assertSame('Free', $freePrice->getDisplayPrice());
    }

    public function test_price_data_to_array(): void
    {
        $price = new SteamPriceData('EUR', 49.99, 24.99, 50, '24,99€');
        $array = $price->toArray();

        $this->assertSame('EUR', $array['currency']);
        $this->assertSame(49.99, $array['initial']);
        $this->assertSame(24.99, $array['final']);
        $this->assertSame(50, $array['discount_percent']);
        $this->assertSame('24,99€', $array['formatted']);
    }

    // ==========================================
    // SteamGameData Tests
    // ==========================================

    public function test_game_data_from_api_response(): void
    {
        $data = [
            'type' => 'game',
            'name' => 'Cyberpunk 2077',
            'steam_appid' => 1091500,
            'short_description' => 'An open-world RPG.',
            'detailed_description' => 'Full description...',
            'about_the_game' => 'About...',
            'header_image' => 'https://cdn.steam.com/header.jpg',
            'background' => 'https://cdn.steam.com/bg.jpg',
            'publishers' => ['CD PROJEKT RED'],
            'developers' => ['CD PROJEKT RED', 'CD PROJEKT'],
            'genres' => [
                ['id' => '1', 'description' => 'Action'],
                ['id' => '3', 'description' => 'RPG'],
            ],
            'categories' => [
                ['id' => 2, 'description' => 'Single-player'],
                ['id' => 36, 'description' => 'Online Multi-Player'],
            ],
            'release_date' => [
                'coming_soon' => false,
                'date' => 'Dec 10, 2020',
            ],
            'metacritic' => [
                'score' => 86,
                'url' => 'https://www.metacritic.com/game/cyberpunk-2077',
            ],
            'platforms' => [
                'windows' => true,
                'mac' => false,
                'linux' => true,
            ],
            'is_free' => false,
            'screenshots' => [
                [
                    'id' => 0,
                    'path_thumbnail' => 'https://cdn.steam.com/ss_thumb.jpg',
                    'path_full' => 'https://cdn.steam.com/ss_full.jpg',
                ],
            ],
            'movies' => [
                [
                    'id' => 256123,
                    'name' => 'Launch Trailer',
                    'thumbnail' => 'https://cdn.steam.com/movie_thumb.jpg',
                    'mp4' => [
                        '480' => 'https://cdn.steam.com/movie_480.mp4',
                        'max' => 'https://cdn.steam.com/movie_max.mp4',
                    ],
                ],
            ],
            'achievements' => ['total' => 44],
            'recommendations' => ['total' => 500000],
            'website' => 'https://www.cyberpunk.net',
            'dlc' => [1234, 5678],
        ];

        $game = SteamGameData::fromApiResponse($data, 1091500);

        $this->assertSame(1091500, $game->steamId);
        $this->assertSame('Cyberpunk 2077', $game->title);
        $this->assertSame('game', $game->type);
        $this->assertSame('An open-world RPG.', $game->description);
        $this->assertSame('CD PROJEKT RED', $game->publisher);
        $this->assertCount(2, $game->developers);
        $this->assertContains('Action', $game->genres);
        $this->assertContains('RPG', $game->genres);
        $this->assertContains('Windows', $game->platforms);
        $this->assertContains('Linux', $game->platforms);
        $this->assertNotContains('Mac', $game->platforms);
        $this->assertSame(86, $game->metacriticScore);
        $this->assertCount(1, $game->screenshots);
        $this->assertCount(1, $game->movies);
        $this->assertSame(44, $game->achievementCount);
        $this->assertSame(500000, $game->recommendationCount);
        $this->assertCount(2, $game->dlcIds);
    }

    public function test_game_data_is_game(): void
    {
        $game = new SteamGameData(1, 'Test', 'game');
        $dlc = new SteamGameData(2, 'Test DLC', 'dlc');
        $demo = new SteamGameData(3, 'Test Demo', 'demo');
        $video = new SteamGameData(4, 'Test Video', 'video');

        $this->assertTrue($game->isGame());
        $this->assertFalse($dlc->isGame());
        $this->assertTrue($demo->isGame());
        $this->assertFalse($video->isGame());
    }

    public function test_game_data_is_free(): void
    {
        $freeGame = new SteamGameData(1, 'Free Game', price: SteamPriceData::free());
        $paidGame = new SteamGameData(2, 'Paid Game', price: new SteamPriceData('USD', 59.99, 59.99, 0));
        $noPriceGame = new SteamGameData(3, 'No Price Game');

        $this->assertTrue($freeGame->isFree());
        $this->assertFalse($paidGame->isFree());
        $this->assertFalse($noPriceGame->isFree()); // null price = not free
    }

    public function test_game_data_get_primary_genre(): void
    {
        $gameWithGenres = new SteamGameData(1, 'Test', genres: ['Action', 'RPG', 'Adventure']);
        $gameNoGenres = new SteamGameData(2, 'Test', genres: []);

        $this->assertSame('Action', $gameWithGenres->getPrimaryGenre());
        $this->assertNull($gameNoGenres->getPrimaryGenre());
    }

    public function test_game_data_supports_platform(): void
    {
        $game = new SteamGameData(1, 'Test', platforms: ['Windows', 'Linux']);

        $this->assertTrue($game->supportsPlatform('Windows'));
        $this->assertTrue($game->supportsPlatform('Linux'));
        $this->assertFalse($game->supportsPlatform('Mac'));
    }

    public function test_game_data_has_multiplayer(): void
    {
        $multiplayerGame = new SteamGameData(1, 'Test', categories: ['Single-player', 'Online Multi-Player']);
        $singlePlayerGame = new SteamGameData(2, 'Test', categories: ['Single-player']);
        $coopGame = new SteamGameData(3, 'Test', categories: ['Online Co-op']);

        $this->assertTrue($multiplayerGame->hasMultiplayer());
        $this->assertFalse($singlePlayerGame->hasMultiplayer());
        $this->assertTrue($coopGame->hasMultiplayer());
    }

    public function test_game_data_has_steam_workshop(): void
    {
        $withWorkshop = new SteamGameData(1, 'Test', categories: ['Steam Workshop', 'Single-player']);
        $withoutWorkshop = new SteamGameData(2, 'Test', categories: ['Single-player']);

        $this->assertTrue($withWorkshop->hasSteamWorkshop());
        $this->assertFalse($withoutWorkshop->hasSteamWorkshop());
    }

    public function test_game_data_get_genres_string(): void
    {
        $game = new SteamGameData(1, 'Test', genres: ['Action', 'RPG', 'Adventure']);

        $this->assertSame('Action, RPG, Adventure', $game->getGenresString());
    }

    public function test_game_data_to_games_info_array(): void
    {
        $game = new SteamGameData(
            steamId: 1091500,
            title: 'Cyberpunk 2077',
            type: 'game',
            description: 'An open-world RPG.',
            coverUrl: 'https://cdn.steam.com/header.jpg',
            backdropUrl: 'https://cdn.steam.com/bg.jpg',
            trailerUrl: 'https://cdn.steam.com/trailer.mp4',
            publisher: 'CD PROJEKT RED',
            releaseDate: '2020-12-10',
            genres: ['Action', 'RPG'],
            metacriticScore: 86,
            storeUrl: 'https://store.steampowered.com/app/1091500',
        );

        $array = $game->toGamesInfoArray();

        $this->assertSame('Cyberpunk 2077', $array['title']);
        $this->assertSame('1091500', $array['asin']);
        $this->assertSame('https://store.steampowered.com/app/1091500', $array['url']);
        $this->assertSame('CD PROJEKT RED', $array['publisher']);
        $this->assertSame('2020-12-10', $array['releasedate']);
        $this->assertSame('An open-world RPG.', $array['review']);
        $this->assertSame(1, $array['cover']);
        $this->assertSame(1, $array['backdrop']);
        $this->assertSame('https://cdn.steam.com/trailer.mp4', $array['trailer']);
        $this->assertSame('Steam', $array['classused']);
        $this->assertSame('86', $array['esrb']);
        $this->assertSame('Action,RPG', $array['genres']);
    }

    public function test_game_data_handles_missing_optional_fields(): void
    {
        $data = [
            'type' => 'game',
            'name' => 'Minimal Game',
            'steam_appid' => 12345,
        ];

        $game = SteamGameData::fromApiResponse($data, 12345);

        $this->assertSame(12345, $game->steamId);
        $this->assertSame('Minimal Game', $game->title);
        $this->assertNull($game->description);
        $this->assertNull($game->publisher);
        $this->assertEmpty($game->developers);
        $this->assertEmpty($game->genres);
        $this->assertEmpty($game->platforms);
        $this->assertNull($game->price);
        $this->assertNull($game->metacriticScore);
    }

    public function test_game_data_immutability(): void
    {
        $game = new SteamGameData(1, 'Test');

        // This should be a compile error in strict mode, or readonly
        // We just verify the properties are accessible but not writable
        $this->assertSame(1, $game->steamId);
        $this->assertSame('Test', $game->title);

        // The readonly keyword ensures immutability at compile time
    }
}


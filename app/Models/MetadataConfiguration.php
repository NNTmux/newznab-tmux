<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LookupMode;
use App\Models\Concerns\IsSingletonConfiguration;
use Illuminate\Database\Eloquent\Model;

/**
 * @property LookupMode $anime_lookup
 * @property LookupMode $book_lookup
 * @property LookupMode $game_lookup
 * @property LookupMode $movie_lookup
 * @property LookupMode $music_lookup
 * @property LookupMode $tv_lookup
 * @property string $movie_language
 * @property bool $imdb_alternate_url
 * @property int $max_anime_processed
 * @property int $max_books_processed
 * @property int $max_games_processed
 * @property int $max_movies_processed
 * @property int $max_music_processed
 * @property int $max_tv_processed
 * @property string|null $amazon_public_key
 * @property string|null $amazon_private_key
 * @property string|null $amazon_associate_tag
 * @property int $amazon_sleep_milliseconds
 */
final class MetadataConfiguration extends Model
{
    use IsSingletonConfiguration;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'anime_lookup' => LookupMode::class,
            'book_lookup' => LookupMode::class,
            'game_lookup' => LookupMode::class,
            'movie_lookup' => LookupMode::class,
            'music_lookup' => LookupMode::class,
            'tv_lookup' => LookupMode::class,
            'imdb_alternate_url' => 'boolean',
            'amazon_public_key' => 'encrypted',
            'amazon_private_key' => 'encrypted',
            'amazon_associate_tag' => 'encrypted',
        ];
    }
}

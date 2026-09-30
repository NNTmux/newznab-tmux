<?php

declare(strict_types=1);

namespace App\Support\Configuration;

use App\Enums\LookupMode;
use App\Models\MetadataConfiguration;

final readonly class MetadataConfigurationData
{
    public function __construct(
        public LookupMode $animeLookup,
        public LookupMode $bookLookup,
        public LookupMode $gameLookup,
        public LookupMode $movieLookup,
        public LookupMode $musicLookup,
        public LookupMode $tvLookup,
        public string $movieLanguage,
        public bool $imdbAlternateUrl,
        public int $maxAnimeProcessed,
        public int $maxBooksProcessed,
        public int $maxGamesProcessed,
        public int $maxMoviesProcessed,
        public int $maxMusicProcessed,
        public int $maxTvProcessed,
        public ?string $amazonPublicKey,
        public ?string $amazonPrivateKey,
        public ?string $amazonAssociateTag,
        public int $amazonSleepMilliseconds,
    ) {}

    public static function defaults(): self
    {
        return new self(LookupMode::Disabled, LookupMode::All, LookupMode::All, LookupMode::All, LookupMode::All, LookupMode::All, 'en', false, 100, 300, 150, 100, 150, 75, null, null, null, 1000);
    }

    public static function fromModel(MetadataConfiguration $model): self
    {
        return new self(
            $model->anime_lookup,
            $model->book_lookup,
            $model->game_lookup,
            $model->movie_lookup,
            $model->music_lookup,
            $model->tv_lookup,
            (string) $model->movie_language,
            (bool) $model->imdb_alternate_url,
            (int) $model->max_anime_processed,
            (int) $model->max_books_processed,
            (int) $model->max_games_processed,
            (int) $model->max_movies_processed,
            (int) $model->max_music_processed,
            (int) $model->max_tv_processed,
            $model->amazon_public_key === null ? null : (string) $model->amazon_public_key,
            $model->amazon_private_key === null ? null : (string) $model->amazon_private_key,
            $model->amazon_associate_tag === null ? null : (string) $model->amazon_associate_tag,
            (int) $model->amazon_sleep_milliseconds,
        );
    }

    /** @return array<string, int|bool|string|null> */
    public function toArray(): array
    {
        $values = get_object_vars($this);
        foreach (['animeLookup', 'bookLookup', 'gameLookup', 'movieLookup', 'musicLookup', 'tvLookup'] as $key) {
            $values[$key] = $values[$key]->value;
        }

        return $values;
    }
}

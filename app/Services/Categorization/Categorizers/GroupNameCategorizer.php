<?php

declare(strict_types=1);

namespace App\Services\Categorization\Categorizers;

use App\Models\Category;
use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\ReleaseContext;

/**
 * Categorizer based on Usenet group name.
 * Runs early with high priority to handle group-specific categorization.
 */
class GroupNameCategorizer extends AbstractCategorizer
{
    protected int $priority = 5;

    // Group constants from Categorize.php
    protected const ANIME_GROUPS = [
        'alt.binaries.anime',
        'alt.binaries.multimedia.anime.highspeed',
        'alt.binaries.multimedia.anime.repost',
        'alt.binaries.multimedia.anime',
        'alt.binaries.multimedia.erotica.anime',
        'alt.binaries.anime.highspeed',
        'alt.binaries.anime.repost',
    ];

    protected const XXX_GROUPS = [
        'alt.binaries.erotica.erotica',
        'alt.binaries.erotica.sheep',
        'alt.binaries.erotica.amateur',
        'alt.binaries.multimedia.erotica.amateur',
        'alt.binaries.misc.xxx',
        'alt.binaries.multimedia.erotica',
    ];

    protected const AUDIO_GROUPS = [
        'alt.binaries.sounds.music',
        'alt.binaries.sounds.misc',
    ];

    protected const COMIC_GROUPS = [
        'alt.binaries.comics.dcp',
        'alt.binaries.comics',
        'alt.binaries.pictures.comics.dcp',
    ];

    public function getName(): string
    {
        return 'GroupName';
    }

    public function categorize(ReleaseContext $context): CategorizationResult
    {
        $groupName = $context->groupName;
        $poster = $context->poster;
        if (empty($groupName)) {
            return $this->noMatch();
        }
        // Check for anime groups first (from Categorize.php byGroupName)
        // Use lower confidence (0.6) so TvCategorizer can provide more specific subcategory
        if (in_array($groupName, self::ANIME_GROUPS) && ! preg_match('/^Bob the Builder$/i', $poster)) {
            return $this->matched(Category::TV_ANIME, 0.6, 'group_anime');
        }
        // Check for XXX groups (from Categorize.php byGroupName)
        // Use lower confidence (0.6) so XxxCategorizer can provide more specific subcategory
        if (in_array($groupName, self::XXX_GROUPS)) {
            return $this->matched(Category::XXX_OTHER, 0.6, 'group_xxx_explicit');
        }
        // Erotica groups pattern
        // Use lower confidence (0.6) so XxxCategorizer can provide more specific subcategory
        if (preg_match('/alt\.binaries\.erotica([.]\w+)?/i', $groupName)) {
            return $this->matched(Category::XXX_OTHER, 0.6, 'group_erotica');
        }
        // Podcast group (from Categorize.php byGroupName)
        // Use lower confidence (0.6) so MusicCategorizer can provide more specific subcategory
        if (preg_match('/alt\.binaries\.podcast$/i', $groupName)) {
            return $this->matched(Category::MUSIC_PODCAST, 0.6, 'group_podcast');
        }
        // Comic groups
        // Use lower confidence (0.6) so BookCategorizer can provide more specific subcategory
        if (in_array($groupName, self::COMIC_GROUPS)) {
            return $this->matched(Category::BOOKS_COMICS, 0.6, 'group_comics');
        }
        // Audio groups - note: these may still contain FLAC movies, handled by shouldSkip in other categorizers
        // Use lower confidence (0.6) so MusicCategorizer can provide more specific subcategory
        if (in_array($groupName, self::AUDIO_GROUPS)) {
            return $this->matched(Category::MUSIC_OTHER, 0.6, 'group_audio');
        }
        // Audiobook group
        // Use lower confidence (0.6) so MusicCategorizer can provide more specific subcategory
        if (str_contains($groupName, 'audiobook')) {
            return $this->matched(Category::MUSIC_AUDIOBOOK, 0.6, 'group_audiobook');
        }
        // Generic group patterns
        if (preg_match('/alt\.binaries\..*?(tv|hdtv|tvseries)/i', $groupName)) {
            return $this->matched(Category::TV_OTHER, 0.6, 'group_name_tv');
        }
        if (preg_match('/alt\.binaries\..*?(movies?|dvd|bluray|x264)/i', $groupName)) {
            return $this->matched(Category::MOVIE_OTHER, 0.6, 'group_name_movie');
        }

        if (preg_match('/alt\.binaries\..*?(erotica|pictures\.erotica|xxx)/i', $groupName)) {
            return $this->matched(Category::XXX_OTHER, 0.7, 'group_name_xxx');
        }
        if (preg_match('/alt\.binaries\..*?(sounds?|mp3|music|lossless)/i', $groupName)) {
            return $this->matched(Category::MUSIC_OTHER, 0.6, 'group_name_music');
        }
        if (preg_match('/alt\.binaries\..*?(games?|console|psx|nintendo)/i', $groupName)) {
            return $this->matched(Category::GAME_OTHER, 0.6, 'group_name_game');
        }
        if (preg_match('/alt\.binaries\..*?(warez|0day|apps?|software)/i', $groupName)) {
            return $this->matched(Category::PC_0DAY, 0.6, 'group_name_pc');
        }
        if (preg_match('/alt\.binaries\..*?(e-?book|ebook|comics?)/i', $groupName)) {
            return $this->matched(Category::BOOKS_EBOOK, 0.5, 'group_name_book');
        }

        return $this->noMatch();
    }
}

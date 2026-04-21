<?php

declare(strict_types=1);

namespace App\Services\Categorization\Categorizers;

use App\Models\Category;
use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\ReleaseContext;

class GroupNameCategorizer extends AbstractCategorizer
{
    protected int $priority = 5;

    public function getName(): string
    {
        return 'GroupName';
    }

    public function categorize(ReleaseContext $context): CategorizationResult
    {
        $groupName = $context->groupName;
        if (empty($groupName)) {
            return $this->noMatch();
        }
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

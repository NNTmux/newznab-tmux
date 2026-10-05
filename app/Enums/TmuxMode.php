<?php

declare(strict_types=1);

namespace App\Enums;

enum TmuxMode: int
{
    case Full = 0;
    case Basic = 1;
    case Stripped = 2;

    /** @return array<string, TmuxPaneRole> */
    public function tasks(): array
    {
        $tasks = match ($this) {
            self::Full => ['binaries' => TmuxPaneRole::Binaries, 'backfill' => TmuxPaneRole::Backfill, 'releases' => TmuxPaneRole::Releases],
            self::Basic => ['releases' => TmuxPaneRole::Releases],
            self::Stripped => ['main' => TmuxPaneRole::Sequential],
        };
        $tasks['fixnames'] = TmuxPaneRole::FixNames;
        if ($this !== self::Stripped) {
            $tasks += ['removecrap' => TmuxPaneRole::RemoveCrap, 'ppadditional' => TmuxPaneRole::PostAdditional, 'tv' => TmuxPaneRole::PostTv, 'movies' => TmuxPaneRole::PostMovies];
        }
        $tasks['amazon'] = TmuxPaneRole::PostMetadata;
        $tasks['scraper'] = TmuxPaneRole::IrcScraper;

        return $tasks;
    }
}

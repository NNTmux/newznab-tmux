<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TvEpisode;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EpisodeHydrationService
{
    /**
     * Hydrate missing season/episode numbers on release objects.
     * Uses episode metadata from the database, then falls back to regex parsing of searchname.
     *
     * @param  iterable<int, object>  $releases
     */
    public function hydrateEpisodeMetadata(iterable $releases): void
    {
        $episodeIds = collect($releases)->pluck('tv_episodes_id')->filter(fn ($v) => $v > 0)->unique()->values();
        $episodeMeta = $episodeIds->isNotEmpty()
            ? TvEpisode::whereIn('id', $episodeIds)->get()->keyBy('id')
            : collect();

        foreach ($releases as $r) {
            $this->hydrateFromMetadata($r, $episodeMeta);
            $this->hydrateFromFirstAired($r);
            $this->hydrateFromSearchname($r);
        }
    }

    /**
     * @param  Collection<int, TvEpisode>  $episodeMeta
     */
    private function hydrateFromMetadata(object $r, Collection $episodeMeta): void
    {
        if ($r->tv_episodes_id <= 0 || ! $episodeMeta->has($r->tv_episodes_id)) {
            return;
        }

        $meta = $episodeMeta[$r->tv_episodes_id];

        if ($this->isMissing($r->series)) {
            $r->series = (int) $meta->series;
        }
        if ($this->isMissing($r->episode)) {
            $r->episode = (int) $meta->episode;
        }

        if ((! $meta->series || (int) $meta->series === 0) && ! empty($meta->firstaired)) {
            if ($this->isMissing($r->series)) {
                $r->series = (int) Carbon::parse($meta->firstaired)->format('Y');
            }
            if ($this->isMissing($r->episode)) {
                $r->episode = (int) Carbon::parse($meta->firstaired)->format('md');
            }
        }
    }

    private function hydrateFromFirstAired(object $r): void
    {
        if (empty($r->firstaired) || $r->tv_episodes_id > 0) {
            return;
        }

        if ($this->isMissing($r->series)) {
            $r->series = (int) Carbon::parse($r->firstaired)->format('Y');
        }
        if ($this->isMissing($r->episode)) {
            $r->episode = (int) Carbon::parse($r->firstaired)->format('md');
        }
    }

    private function hydrateFromSearchname(object $r): void
    {
        if (! $this->needsSearchnameParsing($r)) {
            return;
        }

        $patterns = [
            '/\bS(\d{1,2})E(\d{1,3})\b/i',
            '/\b(\d{1,2})x(\d{1,3})\b/i',
            '/\bSeason[\s._-]*(\d{1,2})[\s._-]*Episode[\s._-]*(\d{1,3})\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $r->searchname, $m)) {
                if ($this->isMissing($r->series)) {
                    $r->series = (int) $m[1];
                }
                if ($this->isMissing($r->episode)) {
                    $r->episode = (int) $m[2];
                }

                return;
            }
        }

        // Compact format: 102 = S1E02
        if (preg_match('/\b(\d)(0[1-9]|[1-9]\d)\b/', $r->searchname, $m)) {
            if ($this->isMissing($r->series) && $this->isMissing($r->episode)) {
                $r->series = (int) $m[1];
                $r->episode = (int) $m[2];

                return;
            }
        }

        // Date-based: 2024.01.15
        if (preg_match('/\b(\d{4})[._-](\d{2})[._-](\d{2})\b/', $r->searchname, $m)) {
            if ($this->isMissing($r->series)) {
                $r->series = (int) $m[1];
            }
            if ($this->isMissing($r->episode)) {
                $r->episode = (int) ($m[2].$m[3]);
            }

            return;
        }

        // Part/Pt N
        if (preg_match('/\b(?:Part|Pt)[\s._-]*(\d{1,3})\b/i', $r->searchname, $m)) {
            if ($this->isMissing($r->episode)) {
                $r->episode = (int) $m[1];
                if ($this->isMissing($r->series)) {
                    $r->series = 1;
                }

                return;
            }
        }

        // Ep/E N
        if ($this->isMissing($r->episode) && preg_match('/\bEp?[\s._-]*(\d{1,3})\b/i', $r->searchname, $m)) {
            $r->episode = (int) $m[1];
            if ($this->isMissing($r->series)) {
                $r->series = 1;
            }
        }
    }

    private function isMissing(mixed $value): bool
    {
        return empty($value) || (int) $value === 0;
    }

    private function needsSearchnameParsing(object $r): bool
    {
        return ($this->isMissing($r->series) || $this->isMissing($r->episode)) && ! empty($r->searchname);
    }
}

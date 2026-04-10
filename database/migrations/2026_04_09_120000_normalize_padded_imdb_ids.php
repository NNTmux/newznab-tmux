<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalize legacy zero-padded IMDb IDs while preserving legitimate leading-zero IDs.
     *
     * Historically some paths stored IMDb IDs with imdb_id_pad(), which converted 7-digit
     * IDs like 0137523 into 8-digit padded values. Real 8+ digit IMDb IDs are never expected
     * to start with 0, so values matching /^0\d{7}$/ can safely drop a single leading zero.
     *
     * Sentinel values like 0000000 / 00000000 become empty strings to preserve the existing
     * "attempted but unresolved" behavior in string-based code paths.
     */
    public function up(): void
    {
        $this->normalizeImdbColumn('releases', 'imdbid');
        $this->normalizeMovieInfo();
        $this->normalizeImdbColumn('user_movies', 'imdbid');
        $this->normalizeImdbColumn('videos', 'imdb');
    }

    public function down(): void
    {
        // No-op: this migration removes lossy zero-padding and cannot be safely reversed.
    }

    private function normalizeImdbColumn(string $table, string $column): void
    {
        DB::table($table)
            ->whereIn($column, ['0000000', '00000000', '0'])
            ->update([$column => '']);

        DB::table($table)
            ->whereRaw(sprintf("`%s` REGEXP '^0[0-9]{7}$'", $column))
            ->update([$column => DB::raw(sprintf('SUBSTRING(`%s`, 2)', $column))]);
    }

    private function normalizeMovieInfo(): void
    {
        DB::transaction(function (): void {
            $rows = DB::table('movieinfo')->select('*')->orderBy('id')->get();

            if ($rows->isEmpty()) {
                return;
            }

            $groups = [];

            foreach ($rows as $row) {
                $normalizedImdbId = $this->normalizeImdbValue($row->imdbid);
                $groups[$normalizedImdbId][] = $row;
            }

            foreach ($groups as $normalizedImdbId => $group) {
                $normalizedImdbId = (string) $normalizedImdbId;
                $requiresNormalization = count($group) > 1;

                if (! $requiresNormalization) {
                    $requiresNormalization = (string) $group[0]->imdbid !== $normalizedImdbId;
                }

                if (! $requiresNormalization) {
                    continue;
                }

                $canonicalRow = $this->chooseCanonicalMovieInfoRow($group, $normalizedImdbId);
                $duplicateIds = [];

                foreach ($group as $row) {
                    if ((int) $row->id !== (int) $canonicalRow->id) {
                        $duplicateIds[] = (int) $row->id;
                    }
                }

                if ($duplicateIds !== []) {
                    DB::table('releases')
                        ->whereIn('movieinfo_id', $duplicateIds)
                        ->update(['movieinfo_id' => $canonicalRow->id]);

                    DB::table('movieinfo')->whereIn('id', $duplicateIds)->delete();
                }

                $updates = $this->buildMovieInfoUpdates($canonicalRow, $group, $normalizedImdbId);

                if ($updates !== []) {
                    DB::table('movieinfo')->where('id', $canonicalRow->id)->update($updates);
                }
            }
        });
    }

    private function normalizeImdbValue(?string $imdbId): string
    {
        if ($imdbId === null) {
            return '';
        }

        $imdbId = trim($imdbId);

        if (in_array($imdbId, ['0000000', '00000000', '0'], true)) {
            return '';
        }

        if (preg_match('/^0\d{7}$/', $imdbId) === 1) {
            return substr($imdbId, 1);
        }

        return $imdbId;
    }

    /**
     * @param  array<int, object>  $group
     */
    private function chooseCanonicalMovieInfoRow(array $group, string $normalizedImdbId): object
    {
        usort($group, function (object $left, object $right) use ($normalizedImdbId): int {
            $scoreComparison = $this->movieInfoScore($right) <=> $this->movieInfoScore($left);
            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            $leftMatchesNormalized = ((string) $left->imdbid === $normalizedImdbId);
            $rightMatchesNormalized = ((string) $right->imdbid === $normalizedImdbId);
            if ($leftMatchesNormalized !== $rightMatchesNormalized) {
                return $rightMatchesNormalized <=> $leftMatchesNormalized;
            }

            return (int) $left->id <=> (int) $right->id;
        });

        return $group[0];
    }

    private function movieInfoScore(object $row): int
    {
        $score = 0;

        foreach (['title', 'tagline', 'rating', 'rtrating', 'plot', 'year', 'genre', 'type', 'director', 'actors', 'language', 'trailer'] as $field) {
            if (trim((string) ($row->{$field} ?? '')) !== '') {
                $score++;
            }
        }

        foreach (['tmdbid', 'traktid', 'cover', 'backdrop'] as $field) {
            if ((int) ($row->{$field} ?? 0) > 0) {
                $score++;
            }
        }

        return $score;
    }

    /**
     * @param  array<int, object>  $group
     * @return array<string, mixed>
     */
    private function buildMovieInfoUpdates(object $canonicalRow, array $group, string $normalizedImdbId): array
    {
        $updates = [];
        $canonical = (array) $canonicalRow;

        if ((string) $canonicalRow->imdbid !== $normalizedImdbId) {
            $updates['imdbid'] = $normalizedImdbId;
        }

        foreach (['title', 'tagline', 'rating', 'rtrating', 'plot', 'year', 'genre', 'type', 'director', 'actors', 'language', 'trailer'] as $field) {
            if (trim((string) ($canonical[$field] ?? '')) !== '') {
                continue;
            }

            foreach ($group as $row) {
                $value = trim((string) ($row->{$field} ?? ''));
                if ($value !== '') {
                    $updates[$field] = $value;
                    break;
                }
            }
        }

        foreach (['tmdbid', 'traktid', 'cover', 'backdrop'] as $field) {
            if ((int) ($canonical[$field] ?? 0) > 0) {
                continue;
            }

            foreach ($group as $row) {
                $value = (int) ($row->{$field} ?? 0);
                if ($value > 0) {
                    $updates[$field] = $value;
                    break;
                }
            }
        }

        return $updates;
    }
};

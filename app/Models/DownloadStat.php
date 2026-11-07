<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DownloadStat extends Model
{
    use HasFactory; // @phpstan-ignore missingType.generics

    protected $guarded = [];

    public static function insertTopDownloads(): void
    {
        $releases = Release::query()
            ->where('grabs', '>', 0)
            ->select(['id', 'searchname', 'guid', 'adddate'])
            ->selectRaw('SUM(grabs) as grabs')
            ->groupBy('id', 'searchname', 'adddate')
            ->havingRaw('SUM(grabs) > 0')
            ->orderByDesc('grabs')
            ->limit(10)
            ->get();

        // The table only caches the current top list: rebuild it (this also clears duplicate rows
        // left by the old collector, which keyed rows on every column including grabs).
        DB::transaction(static function () use ($releases): void {
            self::query()->delete();
            foreach ($releases as $release) {
                self::query()->create([
                    'guid' => $release->guid,
                    'searchname' => $release->searchname,
                    'adddate' => $release->adddate,
                    'grabs' => $release->grabs,
                ]);
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getTopDownloads(): array
    {
        return self::query()->select(['searchname', 'guid', 'adddate', 'grabs'])->orderByDesc('grabs')->limit(10)->get()->toArray();
    }
}

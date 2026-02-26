<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

        foreach ($releases as $release) {
            self::updateOrCreate([
                'searchname' => $release->searchname,
                'guid' => $release->guid,
                'adddate' => $release->adddate,
                'grabs' => $release->grabs,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function getTopDownloads(): array
    {
        return self::query()->select(['searchname', 'guid', 'adddate', 'grabs'])->get()->toArray();
    }
}

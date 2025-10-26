<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mhor\MediaInfo\Container\MediaInfoContainer;

class MediaInfo extends Model
{
    use HasFactory;

    protected $guarded = [];

    public static function addData($id, MediaInfoContainer $xmlArray): void
    {
        $mediainfoArray = $xmlArray->getGeneral();
        if (! $mediainfoArray) {
            return;
        }

        // Check if we have the same release in the database
        if (self::where('releases_id', $id)->exists()) {
            return;
        }

        $mediaUniqueId = $mediainfoArray->get('unique_id');
        // If unique_id is not present or is 0 or 0x0, we don't want to store it, as it's not unique
        if ($mediaUniqueId === null || $mediaUniqueId === '0' || $mediaUniqueId === '0x0' || $mediaUniqueId === 0) {
            $mediaUniqueId = null;
        }

        self::insertOrIgnore([
            'releases_id' => $id,
            'movie_name' => $mediainfoArray->get('movie_name') ?? null,
            'file_name' => $mediainfoArray->get('file_name') ?? null,
            'unique_id' => $mediainfoArray->get('unique_id') ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * AniDB API Resource for transforming anime data.
 */
class AnidbResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'anidbid' => $this->anidbid,
            'anilist_id' => $this->anilist_id,
            'mal_id' => $this->mal_id,
            'title' => $this->title,
            'english_title' => $this->english_title ?? null,
            'original_title' => $this->original_title ?? null,
            'romaji_title' => $this->romaji_title ?? null,
            'type' => $this->type,
            'media_type' => $this->media_type,
            'country' => $this->country,
            'startdate' => $this->startdate,
            'enddate' => $this->enddate,
            'episodes' => $this->episodes,
            'duration' => $this->duration,
            'status' => $this->status,
            'source' => $this->source,
            'rating' => $this->rating,
            'description' => $this->description,
            'picture' => $this->picture,
            'categories' => $this->categories,
            'characters' => $this->characters,
            'creators' => $this->creators,
            'related' => $this->related,
            'similar' => $this->similar,
            'hashtag' => $this->hashtag,
            'updated' => $this->updated,
            'picture_url' => $this->getPictureUrl(),
        ];
    }

    /**
     * Get the picture URL.
     */
    protected function getPictureUrl(): ?string
    {
        if (empty($this->picture)) {
            return null;
        }

        // If picture is already a URL, return it
        if (filter_var($this->picture, FILTER_VALIDATE_URL)) {
            return $this->picture;
        }

        // Otherwise construct the local path
        $picturePath = storage_path('covers/anime/'.$this->anidbid.'.jpg');
        if (file_exists($picturePath)) {
            return url('/covers/anime/'.$this->anidbid.'.jpg');
        }

        return null;
    }
}


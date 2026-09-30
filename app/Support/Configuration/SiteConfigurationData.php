<?php

declare(strict_types=1);

namespace App\Support\Configuration;

use App\Models\SiteConfiguration;

final readonly class SiteConfigurationData
{
    public function __construct(
        public string $title,
        public string $homeLink,
        public ?string $logoPath,
        public string $strapline,
        public string $metaTitle,
        public string $metaDescription,
        public string $metaKeywords,
        public string $footer,
        public string $dereferrerLink,
        public string $terms,
        public bool $trailersDisplay,
        public int $trailerWidth,
        public int $trailerHeight,
    ) {}

    public static function defaults(): self
    {
        return new self('NNTmux', '/', null, 'A great usenet indexer', 'An indexer', 'A usenet indexing website', 'usenet,nzbs,cms,community', 'Usenet binary indexer.', '', '', true, 480, 345);
    }

    public static function fromModel(SiteConfiguration $model): self
    {
        return new self(
            (string) $model->title,
            (string) $model->home_link,
            $model->site_logo === null || $model->site_logo === '' ? null : (string) $model->site_logo,
            (string) $model->strapline,
            (string) $model->meta_title,
            (string) $model->meta_description,
            (string) $model->meta_keywords,
            (string) $model->footer,
            (string) $model->dereferrer_link,
            (string) $model->terms,
            (bool) $model->trailers_display,
            (int) $model->trailers_size_x,
            (int) $model->trailers_size_y,
        );
    }

    /** @return array<string, string|int|bool|null> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

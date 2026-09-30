<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\IsSingletonConfiguration;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $title
 * @property string $home_link
 * @property string|null $site_logo
 * @property string $strapline
 * @property string $meta_title
 * @property string $meta_description
 * @property string $meta_keywords
 * @property string $footer
 * @property string $dereferrer_link
 * @property string $terms
 * @property bool $trailers_display
 * @property int $trailers_size_x
 * @property int $trailers_size_y
 */
final class SiteConfiguration extends Model
{
    use IsSingletonConfiguration;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'trailers_display' => 'boolean',
            'trailers_size_x' => 'integer',
            'trailers_size_y' => 'integer',
        ];
    }
}

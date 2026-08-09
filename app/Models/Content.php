<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Content.
 *
 * @property int $id
 * @property string $title
 * @property string|null $url
 * @property string|null $body
 * @property string $metadescription
 * @property string $metakeywords
 * @property int $contenttype
 * @property int $status
 * @property int|null $ordinal
 * @property int $role
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereContenttype($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereMetadescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereMetakeywords($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereOrdinal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content active()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content ofType($type)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content forRole($role)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content frontPage()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content ordered()
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content query()
 */
class Content extends Model
{
    // Content type constants
    public const TYPE_USEFUL = 1;

    public const TYPE_INDEX = 3;

    // Status constants
    public const STATUS_ENABLED = 1;

    public const STATUS_DISABLED = 0;

    // Role constants (aligned with User roles)
    public const ROLE_EVERYONE = 0;

    public const ROLE_LOGGED_IN = 1;

    public const ROLE_ADMIN = 2;

    /**
     * @var string
     */
    protected $table = 'content';

    /**
     * @var bool
     */
    public $timestamps = false;

    protected $dateFormat = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contenttype' => 'integer',
            'status' => 'integer',
            'ordinal' => 'integer',
            'role' => 'integer',
        ];
    }

    /**
     * Scope: Get only active content.
     *
     * @param  Builder<Content>  $query
     * @return Builder<Content>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ENABLED);
    }

    /**
     * Scope: Get content of a specific type.
     *
     * @param  Builder<Content>  $query
     * @return Builder<Content>
     */
    public function scopeOfType(Builder $query, int $type): Builder
    {
        return $query->where('contenttype', $type);
    }

    /**
     * Scope: Get content accessible by a specific role.
     *
     * @param  Builder<Content>  $query
     * @return Builder<Content>
     */
    public function scopeForRole(Builder $query, int $role): Builder
    {
        // Admins and moderators can see everything
        if (\in_array($role, [UserRole::ADMIN->value, UserRole::MODERATOR->value], true)) {
            return $query;
        }

        // Others can only see content for their role or everyone
        return $query->where(function ($q) use ($role) {
            $q->where('role', self::ROLE_EVERYONE)
                ->orWhere('role', $role);
        });
    }

    /**
     * Scope: Order content by type and ordinal within each type.
     *
     * @param  Builder<Content>  $query
     * @return Builder<Content>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('contenttype')
            ->orderByRaw('COALESCE(ordinal, 1000000) ASC, id ASC');
    }

    /**
     * Scope: Get front page content.
     *
     * @param  Builder<Content>  $query
     * @return Builder<Content>
     */
    public function scopeFrontPage(Builder $query): Builder
    {
        return $query->active()
            ->ofType(self::TYPE_INDEX)
            ->ordered();
    }

    /**
     * Get content type label.
     */
    public function getContentTypeLabel(): string
    {
        return match ($this->contenttype) {
            self::TYPE_USEFUL => 'Useful Link',
            self::TYPE_INDEX => 'Homepage',
            default => 'Unknown',
        };
    }

    /**
     * Get role label.
     */
    public function getRoleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_EVERYONE => 'Everyone',
            self::ROLE_LOGGED_IN => 'Logged in Users',
            self::ROLE_ADMIN => 'Admins',
            default => 'Unknown',
        };
    }

    /**
     * Check if content is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }

    /**
     * Check if content is a homepage type.
     */
    public function isHomepage(): bool
    {
        return $this->contenttype === self::TYPE_INDEX;
    }

    /**
     * Resolve the content URL: external URLs are used as-is (domains without
     * protocol get https://), internal paths are prefixed with the site URL.
     *
     * @return Attribute<string|null, never>
     */
    protected function resolvedUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $url = $this->url;

                if (empty($url)) {
                    return null;
                }

                if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                    return $url;
                }

                // Domain pattern without protocol (e.g. google.com, chatgpt.ai)
                if (! str_starts_with($url, '/') && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]*\.[a-zA-Z]{2,}/', $url)) {
                    return 'https://'.$url;
                }

                return url($url);
            }
        );
    }

    /**
     * Whether the content URL points to an external site.
     *
     * @return Attribute<bool, never>
     */
    protected function isExternalUrl(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                $url = $this->url;

                if (empty($url)) {
                    return false;
                }

                return str_starts_with($url, 'http://')
                    || str_starts_with($url, 'https://')
                    || (! str_starts_with($url, '/') && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]*\.[a-zA-Z]{2,}/', $url));
            }
        );
    }
}

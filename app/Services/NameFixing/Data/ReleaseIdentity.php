<?php

declare(strict_types=1);

namespace App\Services\NameFixing\Data;

/**
 * The release row values a rename decision was based on.
 *
 * Captured before expensive work and compared against the locked row when the result is applied,
 * so a rename computed for one literal identity is never written over a row that has since been
 * renamed, replaced or linked elsewhere. Values are compared byte for byte in PHP: a
 * case- or accent-insensitive collation must not certify a different literal.
 *
 * Fields the capturing query did not select are null and are not compared.
 *
 * Adapted from the release snapshot guards in xbmc4lyfe/newznab-tmux PR #73 (GPL-3.0).
 */
final readonly class ReleaseIdentity
{
    public function __construct(
        public int $id,
        public ?string $guid = null,
        public ?string $name = null,
        public ?string $searchname = null,
        public ?int $predbId = null,
    ) {}

    /**
     * @param  object|array<string, mixed>  $release
     */
    public static function fromRelease(object|array $release): self
    {
        $values = is_array($release) ? $release : self::attributes($release);
        $id = (int) ($values['releases_id'] ?? $values['id'] ?? 0);

        return new self(
            $id,
            self::stringOrNull($values['guid'] ?? null),
            self::stringOrNull($values['name'] ?? null),
            self::stringOrNull($values['searchname'] ?? null),
            isset($values['predb_id']) && is_numeric($values['predb_id']) ? (int) $values['predb_id'] : null,
        );
    }

    public function matches(object $row): bool
    {
        return (int) ($row->id ?? 0) === $this->id
            && ($this->guid === null || (string) ($row->guid ?? '') === $this->guid)
            && ($this->name === null || (string) ($row->name ?? '') === $this->name)
            && ($this->searchname === null || (string) ($row->searchname ?? '') === $this->searchname)
            && ($this->predbId === null || (int) ($row->predb_id ?? 0) === $this->predbId);
    }

    /**
     * @return array<string, mixed>
     */
    private static function attributes(object $release): array
    {
        if (method_exists($release, 'getAttributes')) {
            return [...$release->getAttributes(), 'releases_id' => $release->releases_id ?? null];
        }

        return get_object_vars($release);
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}

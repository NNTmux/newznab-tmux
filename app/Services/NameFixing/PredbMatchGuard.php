<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;

/**
 * Decides whether a PreDB row may rename a release through anything weaker than its title.
 *
 * Some sources (xrel) announce generic or wrong filenames, so their filename is never used.
 * A PreDB row already linked to many releases is refused as well: that is what a generic
 * filename pulling unrelated releases onto one title looks like.
 */
class PredbMatchGuard
{
    /**
     * @var list<string>
     */
    private array $untrustedFilenameSources;

    private int $maxReleasesPerPre;

    /**
     * @param  list<string>|null  $untrustedFilenameSources
     */
    public function __construct(?array $untrustedFilenameSources = null, ?int $maxReleasesPerPre = null)
    {
        $sources = $untrustedFilenameSources ?? (array) self::configured('nntmux.predb_filename_untrusted_sources', ['xrel', 'xrelp2p']);
        $this->untrustedFilenameSources = array_values(array_filter(array_map(
            static fn (mixed $source): string => strtolower(trim((string) $source)),
            $sources
        )));
        $this->maxReleasesPerPre = $maxReleasesPerPre ?? (int) self::configured('nntmux.predb_max_releases_per_pre', 5);
    }

    /**
     * Config value, or the default when no application is booted (plain unit tests).
     */
    private static function configured(string $key, mixed $default): mixed
    {
        $container = Container::getInstance();

        return $container->bound('config') ? $container->make('config')->get($key, $default) : $default;
    }

    public function allowsFilenameMatch(?string $source): bool
    {
        return ! in_array(strtolower(trim((string) $source)), $this->untrustedFilenameSources, true);
    }

    /**
     * True once the PreDB row is linked to the maximum number of releases (0 disables the cap).
     */
    public function exceedsReleaseFanout(int $predbId): bool
    {
        if ($predbId <= 0 || $this->maxReleasesPerPre <= 0) {
            return false;
        }

        // Count over a LIMITed subquery so a heavily linked row stops after max index entries.
        $linked = DB::query()
            ->fromSub(
                DB::table('releases')->select('id')->where('predb_id', $predbId)->limit($this->maxReleasesPerPre),
                'linked_releases'
            )
            ->count();

        return $linked >= $this->maxReleasesPerPre;
    }

    public function maxReleasesPerPre(): int
    {
        return $this->maxReleasesPerPre;
    }

    /**
     * @return list<string>
     */
    public function untrustedFilenameSources(): array
    {
        return $this->untrustedFilenameSources;
    }
}

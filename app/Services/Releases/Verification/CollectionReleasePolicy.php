<?php

declare(strict_types=1);

namespace App\Services\Releases\Verification;

use App\Services\Configuration\ConfigurationProvider;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CollectionReleasePolicy
{
    /** @param array<string, mixed> $manifest */
    public function verifiedRejection(array $manifest): ?string
    {
        if (($manifest['metadata_source'] ?? null) === 'import') {
            return null;
        }
        $bytes = $manifest['verified_bytes'] ?? null;
        $names = $manifest['verified_file_names'] ?? null;
        if (! is_int($bytes) || $bytes < 0 || ! is_array($names) || ! array_is_list($names) || $names === []) {
            throw new RuntimeException('Authoritative verified size and inventory are required for collection publication.');
        }
        foreach ($names as $name) {
            if (! is_string($name) || $name === '') {
                throw new RuntimeException('Authoritative verified inventory contains an invalid filename.');
            }
        }
        $names = array_values(array_unique($names));
        $limits = $this->limits((int) $manifest['groups_id']);
        if ($bytes < $limits['min_size']) {
            return 'Verified payload is below the configured minimum size.';
        }
        if ($limits['max_size'] > 0 && $bytes > $limits['max_size']) {
            return 'Verified payload exceeds the configured maximum size.';
        }
        if (count($names) < $limits['min_files']) {
            return 'Verified inventory is below the configured minimum file count.';
        }
        if (array_filter($names, static fn (string $name): bool => preg_match('/\.par2\z/i', $name) !== 1) === []) {
            return 'Verified inventory contains only PAR2 files without a proved source payload.';
        }

        return null;
    }

    /** @return array{min_size:int,max_size:int,min_files:int} */
    private function limits(int $groupId): array
    {
        $settings = app(ConfigurationProvider::class)->ingestion();
        $group = DB::table('usenet_groups')->where('id', $groupId)->first();

        return ['min_size' => max(0, (int) ($group->minsizetoformrelease ?? 0), $settings->minSizeToFormRelease),
            'max_size' => max(0, $settings->maxSizeToFormRelease),
            'min_files' => max(0, (int) ($group->minfilestoformrelease ?? 0), $settings->minFilesToFormRelease)];
    }
}

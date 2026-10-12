<?php

declare(strict_types=1);

namespace App\Services\Releases;

use App\Models\UsenetGroup;

final class ReleaseGroupResolver
{
    /**
     * Resolve valid crosspost names, retaining the inactive defaults used by release processing.
     *
     * @param  list<string>  $names
     * @return array<string, int> IDs keyed by the original name, including normalized aliases.
     */
    public function idsByName(array $names): array
    {
        $normalized = [];
        foreach (array_unique($names) as $name) {
            $valid = UsenetGroup::isValidGroup($name);
            if (is_string($valid) && strlen($valid) <= 255) {
                $normalized[$name] = $valid;
            }
        }
        $ids = UsenetGroup::query()->whereIn('name', array_values(array_unique($normalized)))
            ->pluck('id', 'name')->map(static fn ($id): int => (int) $id)->all();
        foreach (array_unique($normalized) as $name) {
            if (isset($ids[$name])) {
                continue;
            }
            $ids[$name] = (int) UsenetGroup::addGroup([
                'name' => $name,
                'description' => 'Added by Release processing',
                'backfill_target' => 1,
                'first_record' => 0,
                'last_record' => 0,
                'active' => 0,
                'backfill' => 0,
                'minfilestoformrelease' => '',
                'minsizetoformrelease' => '',
            ]);
        }
        $resolved = [];
        foreach ($normalized as $original => $name) {
            $resolved[$original] = $ids[$name];
        }

        return $resolved;
    }
}

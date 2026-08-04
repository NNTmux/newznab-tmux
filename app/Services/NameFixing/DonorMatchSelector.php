<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

final class DonorMatchSelector
{
    /**
     * @param  list<object>  $donors
     */
    public function select(array $donors, int $targetSize, int $tolerancePercent): ?object
    {
        if ($targetSize <= 0 || $donors === []) {
            return null;
        }

        $eligible = [];

        foreach ($donors as $donor) {
            $donorSize = (int) ($donor->relsize ?? $donor->size ?? 0);
            if ($donorSize <= 0) {
                continue;
            }

            $difference = abs($donorSize - $targetSize);
            $differencePercent = ($difference / $donorSize) * 100;

            if ($differencePercent <= $tolerancePercent) {
                $eligible[] = [
                    'donor' => $donor,
                    'difference' => $difference,
                    'id' => (int) ($donor->releases_id ?? $donor->id ?? PHP_INT_MAX),
                ];
            }
        }

        if ($eligible === []) {
            return null;
        }

        usort($eligible, static function (array $left, array $right): int {
            return [$left['difference'], $left['id']] <=> [$right['difference'], $right['id']];
        });

        return $eligible[0]['donor'];
    }
}

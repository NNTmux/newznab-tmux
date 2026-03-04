<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DateRangeFilter
{
    /**
     * Parse a date range from request parameters.
     * Supports explicit start_date/end_date or a period preset.
     *
     * @return array{0: ?Carbon, 1: Carbon} [$startDate, $endDate]
     */
    public static function fromRequest(Request $request, int $defaultDays = 30): array
    {
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays($defaultDays);

        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));
        } elseif ($request->has('period')) {
            $startDate = match ($request->input('period')) {
                '7days' => Carbon::now()->subDays(7),
                '30days' => Carbon::now()->subDays(30),
                '90days' => Carbon::now()->subDays(90),
                'year' => Carbon::now()->subYear(),
                'all' => null,
                default => Carbon::now()->subDays($defaultDays),
            };
        }

        return [$startDate, $endDate];
    }
}

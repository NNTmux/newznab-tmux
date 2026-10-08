<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Api\ApiInputCanonicalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApiInputCanonicalizerTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed}>
     */
    public static function equivalentCategoryInputs(): array
    {
        return [
            'GET csv' => ['2000,5030'],
            'GET csv with spaces and empties' => [' 2000, ,5030 '],
            'GET cat[] list' => [['2000', '5030']],
            'QUERY integer list' => [[2000, 5030]],
        ];
    }

    #[Test]
    #[DataProvider('equivalentCategoryInputs')]
    public function category_lists_and_csv_strings_share_one_canonical_form(mixed $cat): void
    {
        $canonical = (new ApiInputCanonicalizer)->canonicalize(['cat' => $cat]);

        $this->assertSame(['cat' => '2000,5030'], $canonical);
    }

    #[Test]
    public function scalars_become_trimmed_strings_and_empty_values_become_null(): void
    {
        $canonical = (new ApiInputCanonicalizer)->canonicalize([
            'limit' => 50,
            'minsize' => 1.5,
            'id' => '  ubuntu ',
            'season' => '',
            'ep' => null,
            'cat' => [],
        ]);

        $this->assertSame([
            'cat' => null,
            'ep' => null,
            'id' => 'ubuntu',
            'limit' => '50',
            'minsize' => '1.5',
            'season' => null,
        ], $canonical);
    }

    #[Test]
    public function keys_are_sorted_so_parameter_order_does_not_matter(): void
    {
        $canonicalizer = new ApiInputCanonicalizer;

        $this->assertSame(
            $canonicalizer->canonicalize(['id' => 'a', 'cat' => '1', 'sort' => 'name_asc']),
            $canonicalizer->canonicalize(['sort' => 'name_asc', 'id' => 'a', 'cat' => '1']),
        );
    }

    #[Test]
    public function legacy_nested_arrays_are_canonicalized_deterministically(): void
    {
        $canonical = (new ApiInputCanonicalizer)->canonicalize(['o' => ['b' => ' 2 ', 'a' => 1]]);

        $this->assertSame(['o' => ['a' => '1', 'b' => '2']], $canonical);
    }
}

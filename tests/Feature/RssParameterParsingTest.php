<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\RssController;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RssParameterParsingTest extends TestCase
{
    #[Test]
    public function feed_limit_is_an_integer_capped_at_one_hundred(): void
    {
        [, , , , $limit] = $this->parse(['limit' => '500']);
        [, , , , $smallLimit] = $this->parse(['limit' => '-3']);

        $this->assertSame(100, $limit);
        $this->assertSame(1, $smallLimit);
    }

    #[Test]
    public function non_numeric_show_and_anidb_ids_are_ignored_instead_of_erroring(): void
    {
        [$show] = $this->parse(['show' => 'abc']);
        [, $anidb] = $this->parse(['anidb' => '1 OR 1=1']);
        [$numericShow] = $this->parse(['show' => '42']);

        $this->assertSame(-1, $show);
        $this->assertSame(-1, $anidb);
        $this->assertSame(42, $numericShow);
    }

    /**
     * @param  array<string, string>  $query
     * @return array<int, mixed>
     */
    private function parse(array $query): array
    {
        $controller = (new \ReflectionClass(RssController::class))->newInstanceWithoutConstructor();

        return (new \ReflectionMethod($controller, 'parseCommonRssParams'))->invoke($controller, Request::create('/rss/full-feed', 'GET', $query));
    }
}

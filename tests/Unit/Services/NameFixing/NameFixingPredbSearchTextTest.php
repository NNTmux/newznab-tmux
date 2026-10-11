<?php

declare(strict_types=1);

namespace Tests\Unit\Services\NameFixing;

use App\Services\NameFixing\NameFixingService;
use App\Services\Search\Drivers\ManticoreSearchDriver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NameFixingPredbSearchTextTest extends TestCase
{
    /**
     * Hashed file names with unbalanced parentheses, quotes and operators reached Manticore unescaped
     * and failed with "P08: syntax error, unexpected ')'" (BUGS.md #35).
     */
    #[DataProvider('machineGeneratedNames')]
    public function test_machine_generated_names_become_plain_words(string $fileName, string $expected): void
    {
        $text = NameFixingService::predbSearchText($fileName);

        $this->assertSame($expected, $text);
        $this->assertDoesNotMatchRegularExpression('/[()\[\]"\'|!@~&\/$=\\\\<>:;,#?*+{}^%]/', $text);
        // What the driver builds from it contains no query operators either.
        $this->assertDoesNotMatchRegularExpression('/[()|]/', ManticoreSearchDriver::prepareUserSearchQuery($text));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function machineGeneratedNames(): array
    {
        return [
            'ordinary release name is unchanged' => ['Some.Show.S01E01.1080p.WEB-DL-GRP', 'Some.Show.S01E01.1080p.WEB-DL-GRP'],
            'unbalanced parenthesis' => ['idk_rza-everyone_knows_)-21ccf990', 'idk_rza-everyone_knows_ 21ccf990'],
            'hashed junk from the logs' => [')C<b;<C6.U#sjLBU~~hBk0eQ:@Z0J=qSl?Xu!;Y>g!\'RO]elC0K[(hc!0r?;s.F}< F4;D$sd@GQ;H+>;8<I@[=+52@Tw)', 'C b C6.U sjLBU hBk0eQ Z0J qSl Xu Y g RO elC0K hc 0r s.F F4 D sd GQ H 8 I 52 Tw'],
            'negation and OR operators' => ['-excluded | other !not', 'excluded other not'],
            'only punctuation' => ['())(|', ''],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\NNTP\NNTPService;
use DariusIII\NetNntp\Protocol\ResponseCode;
use PHPUnit\Framework\TestCase;

final class NNTPXoverOverviewCacheTest extends TestCase
{
    public function test_xover_fields_stay_aligned_after_get_overview_cached_the_format(): void
    {
        $nntp = new class extends NNTPService
        {
            public function __construct() {}

            protected function _checkConnection(bool $reSelectGroup = true): mixed
            {
                return true;
            }

            protected function _enableCompression(bool $secondTry = false): mixed
            {
                return true;
            }

            protected function _sendCommand(string $cmd): mixed
            {
                return ResponseCode::OverviewFollows->value;
            }

            public function _getTextResponse(): NNTPService|array|string
            {
                return ["12345\tShow.S01E01 yEnc (1/10)\tposter <p@x>\tThu, 08 Oct 2026 22:25:54 GMT\t<id@x>\t\t740276\t5691\tXref: news alt.test:12345"];
            }
        };
        // Same shape getOverview() leaves in the shared cache.
        (new \ReflectionProperty(NNTPService::class, '_overviewFormatCache'))->setValue($nntp, [
            'Number' => false, 'Subject' => false, 'From' => false, 'Date' => false, 'Message-ID' => false,
            'References' => false, 'Bytes' => false, 'Lines' => false, 'Xref' => true,
        ]);

        $headers = $nntp->getXOVER('12345-12345');

        $this->assertSame('12345', $headers[0]['Number']);
        $this->assertSame('Show.S01E01 yEnc (1/10)', $headers[0]['Subject']);
        $this->assertSame('poster <p@x>', $headers[0]['From']);
        $this->assertSame('news alt.test:12345', $headers[0]['Xref']);
    }
}

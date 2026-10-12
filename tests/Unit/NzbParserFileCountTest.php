<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Nzb\NzbParserService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NzbParserFileCountTest extends TestCase
{
    #[Test]
    public function stripped_subjects_that_merge_files_keep_the_original_file_count(): void
    {
        $nzb = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <nzb xmlns="http://www.newzbin.com/DTD/2003/nzb">
              <file poster="p" date="1" subject="&quot;KlUC4yTqeaIpcTbOIYdzhqqWF&quot; yEnc (1/2)">
                <groups><group>alt.binaries.misc</group></groups>
                <segments><segment bytes="10" number="1">a@x</segment></segments>
              </file>
              <file poster="p" date="1" subject="&quot;KlUC4yTqeaIpcTbOIYdzhqqWF&quot; yEnc (2/2)">
                <groups><group>alt.binaries.misc</group></groups>
                <segments><segment bytes="10" number="1">b@x</segment></segments>
              </file>
              <file poster="p" date="1" subject="&quot;other&quot; yEnc (1/1)">
                <groups><group>alt.binaries.misc</group></groups>
                <segments><segment bytes="10" number="1">c@x</segment></segments>
              </file>
            </nzb>
            XML;

        $files = app(NzbParserService::class)->parseNzbFileList($nzb, ['no-file-key' => false, 'strip-count' => true]);

        $counts = array_column($files, 'filecount');
        sort($counts);
        $this->assertCount(2, $files);
        $this->assertSame([1, 2], $counts);
    }

    #[Test]
    public function it_orders_segments_by_number_and_flags_files_missing_segment_one(): void
    {
        $nzb = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <nzb xmlns="http://www.newzbin.com/DTD/2003/nzb">
              <file poster="p" date="1" subject="&quot;release.part01.rar&quot; yEnc (1/3)">
                <groups><group>alt.binaries.misc</group></groups>
                <segments>
                  <segment bytes="10" number="3">p1-3@x</segment>
                  <segment bytes="10" number="2">p1-2@x</segment>
                </segments>
              </file>
              <file poster="p" date="1" subject="&quot;release.part02.rar&quot; yEnc (1/3)">
                <groups><group>alt.binaries.misc</group></groups>
                <segments>
                  <segment bytes="10" number="2">p2-2@x</segment>
                  <segment bytes="10" number="1">p2-1@x</segment>
                </segments>
              </file>
            </nzb>
            XML;

        $files = app(NzbParserService::class)->parseNzbFileList($nzb);

        $this->assertFalse($files[0]['firstsegment']);
        $this->assertSame(['p1-2@x', 'p1-3@x'], $files[0]['segments']);
        $this->assertTrue($files[1]['firstsegment']);
        $this->assertSame(['p2-1@x', 'p2-2@x'], $files[1]['segments']);
    }
}

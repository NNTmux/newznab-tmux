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
}

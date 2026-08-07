<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Facades\Search;
use App\Models\MediaInfo as MediaInfoRecord;
use App\Services\ReleaseExtraService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mhor\MediaInfo\Container\MediaInfoContainer;
use Mhor\MediaInfo\Type\General;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReleaseExtraServiceTest extends TestCase
{
    #[Test]
    public function it_reindexes_the_release_once_after_media_info_persistence(): void
    {
        Search::shouldReceive('updateRelease')
            ->once()
            ->with(42);

        (new ReleaseExtraService)->addFromXml(42, new MediaInfoContainer);
    }

    #[Test]
    public function it_does_not_persist_invalid_media_unique_id_sentinels(): void
    {
        Schema::create('media_infos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('releases_id');
            $table->string('movie_name')->nullable();
            $table->string('file_name')->nullable();
            $table->string('unique_id')->nullable();
            $table->timestamps();
        });

        $general = new General;
        $general->set('movie_name', 'Example Movie');
        $general->set('unique_id', '0x0');

        $mediaInfo = new MediaInfoContainer;
        $mediaInfo->setGeneral($general);

        try {
            MediaInfoRecord::addData(43, $mediaInfo);
            $record = MediaInfoRecord::query()->where('releases_id', 43)->firstOrFail();
        } finally {
            Schema::drop('media_infos');
        }

        $this->assertSame('Example Movie', $record->movie_name);
        $this->assertNull($record->unique_id);
    }
}

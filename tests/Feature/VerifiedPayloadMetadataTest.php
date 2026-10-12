<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Models\Category;
use App\Services\Categorization\CategorizationService;
use App\Services\ReleaseCleaningService;
use App\Services\Releases\Verification\PayloadIdentityResolver;
use App\Services\Releases\Verification\ReleaseMetadataResolver;
use App\Support\Utf8;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VerifiedPayloadMetadataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        Schema::create('predb', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('filename')->default('');
        });
        DB::table('predb')->insert(['id' => 7, 'title' => 'Known.Movie.2026.1080p.WEB-DL-GRP']);
        Schema::create('usenet_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        DB::table('usenet_groups')->insert(['id' => 1, 'name' => 'alt.test']);
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->integer('status');
        });
        DB::table('categories')->insert([
            ['id' => 2040, 'status' => Category::STATUS_ACTIVE],
            ['id' => 2045, 'status' => Category::STATUS_INACTIVE],
            ['id' => 2050, 'status' => Category::STATUS_DISABLED],
        ]);
    }

    public function test_primary_group_id_selects_naming_rules_instead_of_first_sorted_crosspost(): void
    {
        $manifest = $this->manifest();
        $manifest['groups'] = ['alt.first.crosspost', 'alt.test'];
        $cleaning = $this->createPartialMock(ReleaseCleaningService::class, ['releaseCleaner']);
        $cleaning->expects($this->once())->method('releaseCleaner')->with('masked', 'poster', 'alt.test')
            ->willReturn(['cleansubject' => 'Primary.Group.Name', 'properlynamed' => true, 'id' => 42]);
        Search::shouldReceive('matchPredbExact')->once()->with('Primary.Group.Name')->andReturnNull();
        $category = $this->createMock(CategorizationService::class);
        $category->expects($this->once())->method('determineCategory')->willReturn(['categories_id' => 2000]);

        $metadata = (new ReleaseMetadataResolver($cleaning, categorization: $category))->resolve($manifest);

        $this->assertSame('Primary.Group.Name', $metadata['searchname']);
        $this->assertSame(42, $metadata['naming_regex_id']);
    }

    public function test_selected_import_filename_skips_cleaner_and_conflicting_payload_identity(): void
    {
        $manifest = array_replace($this->manifest(), ['metadata_source' => 'import', 'import_name_override' => true, 'subject' => 'My selected custom name']);
        $cleaning = $this->createPartialMock(ReleaseCleaningService::class, ['releaseCleaner']);
        $cleaning->expects($this->never())->method('releaseCleaner');
        Search::shouldReceive('matchPredbExact')->once()->with('My selected custom name')->andReturnNull();
        $category = $this->createMock(CategorizationService::class);
        $category->expects($this->once())->method('determineCategory')->with(1, 'My selected custom name', 'poster')->willReturn(['categories_id' => 2000]);

        $metadata = (new ReleaseMetadataResolver($cleaning, categorization: $category))->resolve($manifest);

        $this->assertSame('My selected custom name', $metadata['searchname']);
        $this->assertSame(1, $metadata['isrenamed']);
        $this->assertSame(0, $metadata['predb_id']);
        $this->assertSame(0, $metadata['naming_regex_id']);
    }

    public function test_selected_import_filename_still_receives_legacy_predb_lookup(): void
    {
        $manifest = array_replace($this->manifest(), ['metadata_source' => 'import', 'import_name_override' => true,
            'subject' => 'Known.Movie.2026.1080p.WEB-DL-GRP']);
        $cleaning = $this->createPartialMock(ReleaseCleaningService::class, ['releaseCleaner']);
        $cleaning->expects($this->never())->method('releaseCleaner');
        Search::shouldReceive('matchPredbExact')->once()->with($manifest['subject'])->andReturn(['id' => 7]);
        $category = $this->createMock(CategorizationService::class);
        $category->expects($this->once())->method('determineCategory')->willReturn(['categories_id' => 2000]);

        $metadata = (new ReleaseMetadataResolver($cleaning, categorization: $category))->resolve($manifest);

        $this->assertSame($manifest['subject'], $metadata['searchname']);
        $this->assertSame(7, $metadata['predb_id']);
        $this->assertSame(1, $metadata['isrenamed']);
    }

    public function test_import_display_subject_remains_separate_from_selected_filename_including_zero(): void
    {
        foreach (['My selected custom name', '0'] as $selectedName) {
            $manifest = array_replace($this->manifest(), ['metadata_source' => 'import', 'import_name_override' => true,
                'subject' => $selectedName, 'original_subject' => '#Original.Header yEnc']);
            $cleaning = $this->createPartialMock(ReleaseCleaningService::class, ['releaseCleaner']);
            $cleaning->expects($this->never())->method('releaseCleaner');
            Search::shouldReceive('matchPredbExact')->once()->with($selectedName)->andReturnNull();
            $category = $this->createMock(CategorizationService::class);
            $category->expects($this->once())->method('determineCategory')->with(1, $selectedName, 'poster')->willReturn(['categories_id' => 2000]);

            $metadata = (new ReleaseMetadataResolver($cleaning, categorization: $category))->resolve($manifest);

            $this->assertSame('#Original.Header yEnc', $metadata['name']);
            $this->assertSame($selectedName, $metadata['searchname']);
            $this->assertSame(1, $metadata['isrenamed']);
        }
    }

    public function test_import_without_filename_override_cleans_the_preserved_original_subject(): void
    {
        $manifest = array_replace($this->manifest(), ['metadata_source' => 'import', 'original_subject' => '#Original.Header yEnc']);
        $cleaning = $this->createPartialMock(ReleaseCleaningService::class, ['releaseCleaner']);
        $cleaning->expects($this->once())->method('releaseCleaner')->with('#Original.Header yEnc', 'poster', 'alt.test')
            ->willReturn(['cleansubject' => 'Cleaned.Header', 'properlynamed' => true]);
        Search::shouldReceive('matchPredbExact')->once()->with('Cleaned.Header')->andReturnNull();
        $category = $this->createMock(CategorizationService::class);
        $category->expects($this->once())->method('determineCategory')->willReturn(['categories_id' => 2000]);

        $metadata = (new ReleaseMetadataResolver($cleaning, categorization: $category))->resolve($manifest);

        $this->assertSame('#Original.Header yEnc', $metadata['name']);
        $this->assertSame('Cleaned.Header', $metadata['searchname']);
    }

    public function test_name_override_flag_is_ignored_outside_import_context(): void
    {
        Search::shouldReceive('matchPredbExact')->times(2)->with('Cleaned.Name')->andReturnNull();
        foreach (['collection', 'audit'] as $source) {
            $manifest = array_replace($this->manifest(), ['metadata_source' => $source, 'import_name_override' => true, 'original_subject' => 'Untrusted.Replacement']);
            $cleaning = $this->createPartialMock(ReleaseCleaningService::class, ['releaseCleaner']);
            $cleaning->expects($this->once())->method('releaseCleaner')->willReturn(['cleansubject' => 'Cleaned.Name', 'properlynamed' => true]);
            $category = $this->createMock(CategorizationService::class);
            $category->expects($this->once())->method('determineCategory')->willReturn(['categories_id' => 2000]);

            $metadata = (new ReleaseMetadataResolver($cleaning, categorization: $category))->resolve($manifest);

            $this->assertSame('Cleaned.Name', $metadata['searchname']);
            $this->assertSame('masked', $metadata['name']);
        }
    }

    public function test_collection_display_and_poster_follow_legacy_sanitization_without_changing_cleaner_input(): void
    {
        $manifest = $this->manifest();
        $manifest['subject'] = '#@Movie.$%^§¨©Ö'."\xE9";
        $manifest['fromname'] = "'post\xE9r'";
        $expectedName = Utf8::clean("Movie.\xE9");
        $expectedPoster = Utf8::clean("post\xE9r");
        $cleaning = $this->createPartialMock(ReleaseCleaningService::class, ['releaseCleaner']);
        $cleaning->expects($this->once())->method('releaseCleaner')
            ->with($manifest['subject'], $manifest['fromname'], 'alt.test')->willReturn(false);
        Search::shouldReceive('matchPredbExact')->once()->with($expectedName)->andReturnNull();
        $category = $this->createMock(CategorizationService::class);
        $category->expects($this->once())->method('determineCategory')->with(1, $expectedName, $expectedPoster)
            ->willReturn(['categories_id' => 2000]);

        $metadata = (new ReleaseMetadataResolver($cleaning, categorization: $category))->resolve($manifest);

        $this->assertSame($expectedName, $metadata['name']);
        $this->assertSame($expectedName, $metadata['searchname']);
        $this->assertSame($expectedPoster, $metadata['fromname']);
    }

    public function test_active_explicit_import_category_wins_without_changing_imported_display_text(): void
    {
        $manifest = array_replace($this->manifest(), ['metadata_source' => 'import', 'categories_id' => 2040,
            'subject' => '#Imported.Movie', 'fromname' => "'poster'"]);
        $cleaning = $this->createPartialMock(ReleaseCleaningService::class, ['releaseCleaner']);
        $cleaning->expects($this->once())->method('releaseCleaner')->willReturn(false);
        Search::shouldReceive('matchPredbExact')->once()->with('#Imported.Movie')->andReturnNull();
        $category = $this->createMock(CategorizationService::class);
        $category->expects($this->never())->method('determineCategory');

        $metadata = (new ReleaseMetadataResolver($cleaning, categorization: $category))->resolve($manifest);

        $this->assertSame(2040, $metadata['categories_id']);
        $this->assertSame('#Imported.Movie', $metadata['name']);
        $this->assertSame("'poster'", $metadata['fromname']);
    }

    public function test_missing_inactive_disabled_or_invalid_import_category_uses_automatic_category(): void
    {
        $cleaning = $this->createPartialMock(ReleaseCleaningService::class, ['releaseCleaner']);
        $cleaning->expects($this->exactly(5))->method('releaseCleaner')->willReturn(false);
        Search::shouldReceive('matchPredbExact')->times(5)->with('masked')->andReturnNull();
        $category = $this->createMock(CategorizationService::class);
        $category->expects($this->exactly(5))->method('determineCategory')->willReturn(['categories_id' => 2000]);
        $resolver = new ReleaseMetadataResolver($cleaning, categorization: $category);

        foreach ([null, 9999, 2045, 2050, '2040junk'] as $id) {
            $metadata = $resolver->resolve(array_replace($this->manifest(), ['metadata_source' => 'import', 'categories_id' => $id]));
            $this->assertSame(2000, $metadata['categories_id']);
        }
    }

    public function test_crosspost_group_fallback_is_only_used_without_a_primary_group_id(): void
    {
        Search::shouldReceive('matchPredbExact')->times(2)->with('masked')->andReturnNull();
        foreach ([0 => 'alt.fallback', 99 => ''] as $groupId => $expectedGroup) {
            $manifest = array_replace($this->manifest(), ['groups_id' => $groupId, 'groups' => ['alt.fallback']]);
            $cleaning = $this->createPartialMock(ReleaseCleaningService::class, ['releaseCleaner']);
            $cleaning->expects($this->once())->method('releaseCleaner')->with('masked', 'poster', $expectedGroup)->willReturn(false);
            $category = $this->createMock(CategorizationService::class);
            $category->expects($this->once())->method('determineCategory')->willReturn(['categories_id' => 2000]);

            $metadata = (new ReleaseMetadataResolver($cleaning, categorization: $category))->resolve($manifest);

            $this->assertSame('masked', $metadata['searchname']);
        }
    }

    public function test_collection_and_audit_manifests_do_not_override_automatic_categories(): void
    {
        $cleaning = $this->createPartialMock(ReleaseCleaningService::class, ['releaseCleaner']);
        $cleaning->expects($this->exactly(2))->method('releaseCleaner')->willReturn(false);
        Search::shouldReceive('matchPredbExact')->times(2)->with('masked')->andReturnNull();
        $category = $this->createMock(CategorizationService::class);
        $category->expects($this->exactly(2))->method('determineCategory')->willReturn(['categories_id' => 2000]);
        $resolver = new ReleaseMetadataResolver($cleaning, categorization: $category);

        foreach (['collection', 'audit'] as $source) {
            $metadata = $resolver->resolve(array_replace($this->manifest(), ['metadata_source' => $source, 'categories_id' => 2040]));
            $this->assertSame(2000, $metadata['categories_id']);
        }
    }

    public function test_empty_cleaned_name_uses_legacy_sanitized_display_name_for_search(): void
    {
        $manifest = array_replace($this->manifest(), ['subject' => '#Display.Name']);
        $cleaning = $this->createPartialMock(ReleaseCleaningService::class, ['releaseCleaner']);
        $cleaning->expects($this->once())->method('releaseCleaner')->willReturn(['cleansubject' => '', 'properlynamed' => true]);
        $category = $this->createMock(CategorizationService::class);
        $category->expects($this->once())->method('determineCategory')->with(1, '', 'poster')->willReturn(['categories_id' => 2000]);

        $metadata = (new ReleaseMetadataResolver($cleaning, categorization: $category))->resolve($manifest);

        $this->assertSame('Display.Name', $metadata['name']);
        $this->assertSame('Display.Name', $metadata['searchname']);
    }

    public function test_masked_subject_recovers_verified_filename_and_corroborated_predb_identity(): void
    {
        $cleaning = $this->createPartialMock(ReleaseCleaningService::class, ['releaseCleaner']);
        $cleaning->expects($this->once())->method('releaseCleaner')->willReturn(['cleansubject' => 'masked', 'properlynamed' => false]);
        $category = $this->createMock(CategorizationService::class);
        $category->expects($this->once())->method('determineCategory')->with(1, 'Known.Movie.2026.1080p.WEB-DL-GRP', 'poster')->willReturn(['categories_id' => 2000]);
        Search::shouldReceive('matchPredbExact')->once()->with('masked')->andReturnNull();
        Search::shouldReceive('matchPredbExact')->once()->with('Known.Movie.2026.1080p.WEB-DL-GRP')->andReturn(['id' => 7]);
        $resolver = new ReleaseMetadataResolver($cleaning, new PayloadIdentityResolver($cleaning), $category);
        $metadata = $resolver->resolve(array_replace($this->manifest(), ['metadata_source' => 'import']));
        $this->assertSame('masked', $metadata['name']);
        $this->assertSame('Known.Movie.2026.1080p.WEB-DL-GRP', $metadata['searchname']);
        $this->assertSame(7, $metadata['predb_id']);
        $this->assertSame(1, $metadata['isrenamed']);
    }

    public function test_properly_named_subject_wins_over_conflicting_payload_hint(): void
    {
        $cleaning = $this->createPartialMock(ReleaseCleaningService::class, ['releaseCleaner']);
        $cleaning->expects($this->once())->method('releaseCleaner')->willReturn(['cleansubject' => 'Already.Named.2026.1080p-GRP', 'properlynamed' => true]);
        $category = $this->createMock(CategorizationService::class);
        $category->expects($this->once())->method('determineCategory')->willReturn(['categories_id' => 2000]);
        Search::shouldReceive('matchPredbExact')->once()->with('Already.Named.2026.1080p-GRP')->andReturnNull();
        $metadata = (new ReleaseMetadataResolver($cleaning, new PayloadIdentityResolver($cleaning), $category))->resolve($this->manifest());
        $this->assertSame('Already.Named.2026.1080p-GRP', $metadata['searchname']);
    }

    public function test_filename_alias_for_a_different_stored_title_cannot_assign_predb_id(): void
    {
        DB::table('predb')->where('id', 7)->update(['title' => 'Different.Movie.2026.1080p.WEB-DL-GRP', 'filename' => 'Known.Movie.2026.1080p.WEB-DL-GRP']);
        $cleaning = $this->createPartialMock(ReleaseCleaningService::class, ['releaseCleaner']);
        $cleaning->expects($this->once())->method('releaseCleaner')->willReturn(['cleansubject' => 'masked', 'properlynamed' => false]);
        $category = $this->createMock(CategorizationService::class);
        $category->expects($this->once())->method('determineCategory')->willReturn(['categories_id' => 2000]);
        Search::shouldReceive('matchPredbExact')->once()->with('masked')->andReturnNull();
        Search::shouldReceive('matchPredbExact')->once()->with('Known.Movie.2026.1080p.WEB-DL-GRP')->andReturn(['id' => 7]);
        $metadata = (new ReleaseMetadataResolver($cleaning, new PayloadIdentityResolver($cleaning), $category))->resolve($this->manifest());
        $this->assertSame('Known.Movie.2026.1080p.WEB-DL-GRP', $metadata['searchname']);
        $this->assertSame(0, $metadata['predb_id']);
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        return ['subject' => 'masked', 'fromname' => 'poster', 'groups' => ['alt.test'], 'groups_id' => 1,
            'identity_hints' => [['name' => 'Known.Movie.2026.1080p.WEB-DL-GRP.mkv', 'source' => 'yenc']]];
    }
}

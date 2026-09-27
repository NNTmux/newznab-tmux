<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\AnidbTitle;
use App\Models\CategoryRegex;
use App\Models\CollectionRegex;
use App\Models\DnzbFailure;
use App\Models\ParHash;
use App\Models\Part;
use App\Models\PredbImport;
use App\Models\ReleaseFile;
use App\Models\ReleaseNamingRegex;
use App\Models\ReleaseRegex;
use App\Models\ReleasesGroups;
use App\Models\ReleaseUnique;
use App\Models\ShortGroup;
use App\Models\VideoAlias;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModelDefinitionTest extends TestCase
{
    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function modelsWithoutTimestampColumns(): array
    {
        return [
            'parts' => [Part::class],
            'predb_imports' => [PredbImport::class],
            'short_groups' => [ShortGroup::class],
            'collection_regexes' => [CollectionRegex::class],
            'category_regexes' => [CategoryRegex::class],
            'release_naming_regexes' => [ReleaseNamingRegex::class],
        ];
    }

    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function compositeKeyModels(): array
    {
        return [
            'releases_groups' => [ReleasesGroups::class],
            'parts' => [Part::class],
            'release_files' => [ReleaseFile::class],
            'par_hashes' => [ParHash::class],
            'release_regexes' => [ReleaseRegex::class],
            'release_unique' => [ReleaseUnique::class],
            'dnzb_failures' => [DnzbFailure::class],
            'videos_aliases' => [VideoAlias::class],
            'anidb_titles' => [AnidbTitle::class],
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    #[Test]
    #[DataProvider('modelsWithoutTimestampColumns')]
    public function models_for_tables_without_timestamp_columns_do_not_write_timestamps(string $modelClass): void
    {
        self::assertFalse((new $modelClass)->usesTimestamps());
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    #[Test]
    #[DataProvider('compositeKeyModels')]
    public function composite_key_models_match_the_schema_primary_key(string $modelClass): void
    {
        $model = new $modelClass;
        self::assertTrue(method_exists($model, 'compositeKeyColumns'));

        $schemaKey = $this->schemaPrimaryKey($model->getTable());

        self::assertSame($schemaKey, $model->compositeKeyColumns());
        self::assertSame($schemaKey[0], $model->getKeyName());
        self::assertFalse($model->getIncrementing());
    }

    #[Test]
    public function models_for_dropped_tables_are_removed(): void
    {
        self::assertFalse(class_exists('App\\Models\\AnidbEpisode'));
        self::assertFalse(class_exists('App\\Models\\PredbHash'));
    }

    /**
     * @return list<string>
     */
    private function schemaPrimaryKey(string $table): array
    {
        $schema = (string) file_get_contents(dirname(__DIR__, 3).'/database/schema/mariadb-schema.sql');

        $pattern = '/CREATE TABLE `'.preg_quote($table, '/').'` \((.*?)\n\) ENGINE/s';
        self::assertSame(1, preg_match($pattern, $schema, $tableMatch), "Table [{$table}] is missing from the schema dump.");
        self::assertSame(1, preg_match('/PRIMARY KEY \(([^)]+)\)/', $tableMatch[1], $keyMatch), "Table [{$table}] has no primary key.");

        return array_map(static fn (string $column): string => trim($column, '` '), explode(',', $keyMatch[1]));
    }
}

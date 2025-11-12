<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Predb;
use App\Models\Release;
use Blacklight\Categorize;
use Blacklight\ColorCLI;
use Blacklight\ElasticSearchSiteSearch;
use Blacklight\ManticoreSearch;
use Illuminate\Console\Command;

class RenameOtherMiscReleases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'releases:rename-other-misc
                                 {--limit= : Maximum number of releases to process}
                                 {--dry-run : Show what would be renamed without actually updating}
                                 {--show : Display detailed release changes}
                                 {--size-tolerance=5 : Size tolerance percentage for matching (default: 5%)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rename releases in other->misc and other-hashed categories using PreDB entries';

    protected ?ColorCLI $colorCLI = null;

    protected int $renamed = 0;

    protected int $checked = 0;

    protected int $matched = 0;

    /**
     * Cache for category lookups to avoid repeated DB queries.
     */
    protected array $categoryCache = [];

    /**
     * Cached ManticoreSearch instance to avoid re-instantiation.
     */
    protected ?ManticoreSearch $manticore = null;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->colorCLI = new ColorCLI;
        $categorize = new Categorize;

        $limit = $this->option('limit');
        $dryRun = $this->option('dry-run');
        $show = $this->option('show');
        $sizeTolerance = (float) $this->option('size-tolerance');

        if ($limit && ! is_numeric($limit)) {
            $this->error('Limit must be a numeric value.');

            return Command::FAILURE;
        }

        $this->colorCLI->header('Starting rename of releases in other->misc and other-hashed categories');

        if ($dryRun) {
            $this->colorCLI->info('DRY RUN MODE - No changes will be made');
        }

        $startTime = now();

        try {
            // Process releases in a single pass with cascading match attempts
            $this->info('Processing releases with PreDB matching...');
            $this->processReleases($limit, $dryRun, $show, $sizeTolerance, $categorize);

            $duration = now()->diffInSeconds($startTime, true);

            $this->colorCLI->header('Processing Complete');
            $this->colorCLI->primary("Checked: {$this->checked} releases");
            $this->colorCLI->primary("Matched: {$this->matched} releases");
            $this->colorCLI->primary("Renamed: {$this->renamed} releases");
            $this->colorCLI->primary("Duration: {$duration} seconds");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    /**
     * Lazily get a cached ManticoreSearch instance or null if unavailable.
     */
    protected function getManticore(): ?ManticoreSearch
    {
        if ($this->manticore !== null) {
            return $this->manticore;
        }

        if (config('nntmux.manticore.enabled') === true || config('sphinxsearch.host')) {
            try {
                $this->manticore = new ManticoreSearch;
            } catch (\Exception $e) {
                // If Manticore isn't usable, keep it null and fall back to DB queries
                $this->manticore = null;
            }
        }

        return $this->manticore;
    }

    /**
     * Process releases with PreDB matching in a single pass.
     */
    protected function processReleases($limit, bool $dryRun, bool $show, float $sizeTolerance, Categorize $categorize): void
    {
        $query = Release::query()
            ->whereIn('categories_id', [Category::OTHER_MISC, Category::OTHER_HASHED])
            ->where('predb_id', 0)
            ->select(['id', 'guid', 'name', 'searchname', 'size', 'fromname', 'categories_id', 'groups_id'])
            ->orderBy('id', 'DESC'); // Process newest first

        if ($limit) {
            $query->limit((int) $limit);
        }

        $releases = $query->get();
        $total = $releases->count();

        if ($total === 0) {
            $this->info('No releases found to process.');

            return;
        }

        $this->info("Processing {$total} releases...");

        foreach ($releases as $release) {
            $this->checked++;

            // Clean the release name for matching
            $cleanName = $this->cleanReleaseName($release->searchname);

            if (empty($cleanName)) {
                continue;
            }

            // Try matching in order of confidence (most strict to least strict)
            $matched = false;

            // 1. Title + Size Match (most reliable)
            if (! $matched) {
                $matched = $this->matchByTitleAndSize($release, $cleanName, $dryRun, $show, $sizeTolerance, $categorize);
            }

            // 2. Filename + Size Match
            if (! $matched) {
                $matched = $this->matchByFilenameAndSize($release, $cleanName, $dryRun, $show, $sizeTolerance, $categorize);
            }

            // 3. Direct Title Match (no size check)
            if (! $matched) {
                $matched = $this->matchByDirectTitle($release, $cleanName, $dryRun, $show, $categorize);
            }

            // 4. Direct Filename Match (no size check)
            if (! $matched) {
                $matched = $this->matchByDirectFilename($release, $cleanName, $dryRun, $show, $categorize);
            }

            // 5. Partial Title Match (least strict, last resort)
            if (! $matched) {
                $matched = $this->matchByPartialTitle($release, $cleanName, $dryRun, $show, $categorize);
            }

            if ($matched) {
                $this->matched++;
            }

            if (! $show && $this->checked % 10 === 0) {
                $percent = round(($this->checked / $total) * 100, 1);
                $this->info(
                    "Progress: {$percent}% ({$this->checked}/{$total}) | ".
                    "Matched: {$this->matched} | Renamed: {$this->renamed}"
                );
            }
        }

        if (! $show) {
            echo PHP_EOL;
        }
    }

    /**
     * Match release by direct title (no size check).
     */
    protected function matchByDirectTitle($release, string $cleanName, bool $dryRun, bool $show, Categorize $categorize): bool
    {
        // Use ManticoreSearch for fast exact matching
        $manticore = $this->getManticore();

        if ($manticore) {
            try {
                // Get exact match ID from Manticore (extremely fast)
                $predbId = $manticore->exactMatch('predb_rt', $cleanName, 'title');

                if ($predbId) {
                    // Load single PreDB record from database
                    $predb = Predb::query()
                        ->where('id', $predbId)
                        ->first(['id', 'title', 'size', 'source']);

                    if ($predb) {
                        return $this->updateReleaseFromPredb($release, $predb, 'Direct Title Match', $dryRun, $show, $categorize);
                    }
                }
            } catch (\Exception $e) {
                // Fall through to direct query
            }
        }

        // Fallback to direct database query if Manticore unavailable
        $predb = Predb::query()
            ->where('title', $cleanName)
            ->first(['id', 'title', 'size', 'source']);

        if ($predb) {
            return $this->updateReleaseFromPredb($release, $predb, 'Direct Title Match', $dryRun, $show, $categorize);
        }

        return false;
    }

    /**
     * Match release by direct filename (no size check).
     */
    protected function matchByDirectFilename($release, string $cleanName, bool $dryRun, bool $show, Categorize $categorize): bool
    {
        // Use ManticoreSearch for fast exact matching
        $manticore = $this->getManticore();

        if ($manticore) {
            try {
                // Get exact match ID from Manticore (extremely fast)
                $predbId = $manticore->exactMatch('predb_rt', $cleanName, 'filename');

                if ($predbId) {
                    // Load single PreDB record from database
                    $predb = Predb::query()
                        ->where('id', $predbId)
                        ->first(['id', 'title', 'size', 'source']);

                    if ($predb) {
                        return $this->updateReleaseFromPredb($release, $predb, 'Direct Filename Match', $dryRun, $show, $categorize);
                    }
                }
            } catch (\Exception $e) {
                // Fall through to direct query
            }
        }

        // Fallback to direct database query if Manticore unavailable
        $predb = Predb::query()
            ->where('filename', $cleanName)
            ->first(['id', 'title', 'size', 'source']);

        if ($predb) {
            return $this->updateReleaseFromPredb($release, $predb, 'Direct Filename Match', $dryRun, $show, $categorize);
        }

        return false;
    }

    /**
     * Match release by partial title using ManticoreSearch or LIKE.
     */
    protected function matchByPartialTitle($release, string $cleanName, bool $dryRun, bool $show, Categorize $categorize): bool
    {
        // Only try partial match if clean name is reasonably long to avoid too many false positives
        if (strlen($cleanName) < 15) {
            return false;
        }

        // Try ManticoreSearch first if available - it's much faster for fuzzy matching
        $manticore = $this->getManticore();

        if ($manticore) {
            try {
                // Use a custom limited search for partial matching
                $results = $this->limitedSearch('predb_rt', $cleanName, ['title', 'filename'], 10);

                if (! empty($results)) {
                    // Batch load just the limited PreDB rows
                    $predbs = Predb::query()->whereIn('id', $results)->get(['id', 'title', 'size', 'source'])->keyBy('id');

                    foreach ($results as $predbId) {
                        if (isset($predbs[$predbId])) {
                            $predb = $predbs[$predbId];

                            return $this->updateReleaseFromPredb($release, $predb, 'Partial Title Match (ManticoreSearch)', $dryRun, $show, $categorize);
                        }
                    }
                }
            } catch (\Exception $e) {
                // Fall through to direct query
            }
        }

        // Fallback to LIKE query (slower)
        $searchPattern = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $cleanName).'%';
        $predb = Predb::query()
            ->where('title', 'LIKE', $searchPattern)
            ->first(['id', 'title', 'size', 'source']);

        if ($predb) {
            return $this->updateReleaseFromPredb($release, $predb, 'Partial Title Match', $dryRun, $show, $categorize);
        }

        return false;
    }

    /**
     * Perform a limited Manticore search returning only IDs up to specified limit.
     */
    protected function limitedSearch(string $index, string $searchString, array $fields, int $limit = 10): array
    {
        $manticore = $this->getManticore();
        if (! $manticore) {
            return [];
        }

        try {
            $escapedSearch = ManticoreSearch::escapeString($searchString);
            if (empty($escapedSearch)) {
                return [];
            }

            $searchColumns = '';
            if (! empty($fields)) {
                if (count($fields) > 1) {
                    $searchColumns = '@('.implode(',', $fields).')';
                } else {
                    $searchColumns = '@'.$fields[0];
                }
            }

            $searchExpr = '@@relaxed '.$searchColumns.' '.$escapedSearch;

            $query = (new \Manticoresearch\Search($manticore->manticoreSearch))
                ->setTable($index)
                ->option('ranker', 'sph04')
                ->limit($limit)
                ->stripBadUtf8(true)
                ->search($searchExpr);

            $results = $query->get();

            $ids = [];
            foreach ($results as $doc) {
                $ids[] = $doc->getId();
            }

            return $ids;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Process releases with exact PreDB matches (title/filename + size).
     *
     * @deprecated Use processReleases() instead for better performance
     */
    protected function processExactMatches($limit, bool $dryRun, bool $show, float $sizeTolerance, Categorize $categorize): void
    {
        $query = Release::query()
            ->whereIn('categories_id', [Category::OTHER_MISC, Category::OTHER_HASHED])
            ->where('predb_id', 0)
            ->select(['id', 'guid', 'name', 'searchname', 'size', 'fromname', 'categories_id', 'groups_id']);

        if ($limit) {
            $query->limit((int) $limit);
        }

        $releases = $query->get();
        $total = $releases->count();

        if ($total === 0) {
            $this->info('No releases found for exact matching.');

            return;
        }

        $this->info("Processing {$total} releases for exact matching...");

        foreach ($releases as $release) {
            $this->checked++;

            // Clean the release name for matching
            $cleanName = $this->cleanReleaseName($release->searchname);

            // Try to match by title and size
            $matched = $this->matchByTitleAndSize($release, $cleanName, $dryRun, $show, $sizeTolerance, $categorize);

            if (! $matched) {
                // Try to match by filename and size
                $matched = $this->matchByFilenameAndSize($release, $cleanName, $dryRun, $show, $sizeTolerance, $categorize);
            }

            if ($matched) {
                $this->matched++;
            }

            if (! $show && $this->checked % 10 === 0) {
                $percent = round(($this->checked / $total) * 100, 1);
                $this->info(
                    "Progress: {$percent}% ({$this->checked}/{$total}) | ".
                    "Matched: {$this->matched} | Renamed: {$this->renamed}"
                );
            }
        }

        if (! $show) {
            echo PHP_EOL;
        }
    }

    /**
     * Process releases with title matches only.
     */
    protected function processTitleMatches($limit, bool $dryRun, bool $show, Categorize $categorize): void
    {
        $query = Release::query()
            ->whereIn('categories_id', [Category::OTHER_MISC, Category::OTHER_HASHED])
            ->where('predb_id', 0)
            ->select(['id', 'guid', 'name', 'searchname', 'size', 'fromname', 'categories_id', 'groups_id']);

        if ($limit) {
            $query->limit((int) $limit);
        }

        $releases = $query->get();
        $total = $releases->count();

        if ($total === 0) {
            $this->info('No releases found for title matching.');

            return;
        }

        $this->info("Processing {$total} releases for title matching...");

        $initialChecked = $this->checked;

        foreach ($releases as $release) {
            $this->checked++;

            // Clean the release name for matching
            $cleanName = $this->cleanReleaseName($release->searchname);

            // Try fuzzy title matching
            $matched = $this->matchByFuzzyTitle($release, $cleanName, $dryRun, $show, $categorize);

            if ($matched) {
                $this->matched++;
            }

            if (! $show && ($this->checked - $initialChecked) % 10 === 0) {
                $percent = round((($this->checked - $initialChecked) / $total) * 100, 1);
                $this->info(
                    "Progress: {$percent}% ({$this->checked}/{$total}) | ".
                    "Matched: {$this->matched} | Renamed: {$this->renamed}"
                );
            }
        }

        if (! $show) {
            echo PHP_EOL;
        }
    }

    /**
     * Match release by title and size in PreDB.
     */
    protected function matchByTitleAndSize($release, string $cleanName, bool $dryRun, bool $show, float $sizeTolerance, Categorize $categorize): bool
    {
        if (empty($cleanName)) {
            return false;
        }

        // Calculate size range for matching
        $sizeMin = $release->size * (1 - ($sizeTolerance / 100));
        $sizeMax = $release->size * (1 + ($sizeTolerance / 100));

        // Use ManticoreSearch for fast exact title matching, then verify size
        $manticore = $this->getManticore();

        if ($manticore) {
            try {
                // Get exact match ID from Manticore (extremely fast)
                $predbId = $manticore->exactMatch('predb_rt', $cleanName, 'title');

                if ($predbId) {
                    // Load and verify size
                    $predb = Predb::query()
                        ->where('id', $predbId)
                        ->where(function ($query) use ($sizeMin, $sizeMax) {
                            $query->whereNull('size')
                                ->orWhereBetween('size', [$sizeMin, $sizeMax]);
                        })
                        ->first(['id', 'title', 'size', 'source']);

                    if ($predb) {
                        return $this->updateReleaseFromPredb($release, $predb, 'Title + Size Match', $dryRun, $show, $categorize);
                    }
                }
            } catch (\Exception $e) {
                // Fall through to direct query
            }
        }

        // Fallback to direct database query
        $predb = Predb::query()
            ->where('title', $cleanName)
            ->where(function ($query) use ($sizeMin, $sizeMax) {
                $query->whereNull('size')
                    ->orWhereBetween('size', [$sizeMin, $sizeMax]);
            })
            ->first(['id', 'title', 'size', 'source']);

        if ($predb) {
            return $this->updateReleaseFromPredb($release, $predb, 'Title + Size Match', $dryRun, $show, $categorize);
        }

        return false;
    }

    /**
     * Match release by filename and size in PreDB.
     */
    protected function matchByFilenameAndSize($release, string $cleanName, bool $dryRun, bool $show, float $sizeTolerance, Categorize $categorize): bool
    {
        if (empty($cleanName)) {
            return false;
        }

        // Calculate size range for matching
        $sizeMin = $release->size * (1 - ($sizeTolerance / 100));
        $sizeMax = $release->size * (1 + ($sizeTolerance / 100));

        // Use ManticoreSearch for fast exact filename matching, then verify size
        $manticore = $this->getManticore();

        if ($manticore) {
            try {
                // Get exact match ID from Manticore (extremely fast)
                $predbId = $manticore->exactMatch('predb_rt', $cleanName, 'filename');

                if ($predbId) {
                    // Load and verify size
                    $predb = Predb::query()
                        ->where('id', $predbId)
                        ->where(function ($query) use ($sizeMin, $sizeMax) {
                            $query->whereNull('size')
                                ->orWhereBetween('size', [$sizeMin, $sizeMax]);
                        })
                        ->first(['id', 'title', 'size', 'source']);

                    if ($predb) {
                        return $this->updateReleaseFromPredb($release, $predb, 'Filename + Size Match', $dryRun, $show, $categorize);
                    }
                }
            } catch (\Exception $e) {
                // Fall through to direct query
            }
        }

        // Fallback to direct database query
        $predb = Predb::query()
            ->where('filename', $cleanName)
            ->where(function ($query) use ($sizeMin, $sizeMax) {
                $query->whereNull('size')
                    ->orWhereBetween('size', [$sizeMin, $sizeMax]);
            })
            ->first(['id', 'title', 'size', 'source']);

        if ($predb) {
            return $this->updateReleaseFromPredb($release, $predb, 'Filename + Size Match', $dryRun, $show, $categorize);
        }

        return false;
    }

    /**
     * Match release by fuzzy title matching using search.
     *
     * @deprecated Split into separate methods for better performance
     */
    protected function matchByFuzzyTitle($release, string $cleanName, bool $dryRun, bool $show, Categorize $categorize): bool
    {
        // Try direct title match first
        if ($this->matchByDirectTitle($release, $cleanName, $dryRun, $show, $categorize)) {
            return true;
        }

        // Try filename match
        if ($this->matchByDirectFilename($release, $cleanName, $dryRun, $show, $categorize)) {
            return true;
        }

        // Try partial title match
        return $this->matchByPartialTitle($release, $cleanName, $dryRun, $show, $categorize);
    }

    /**
     * Update release from PreDB entry.
     */
    protected function updateReleaseFromPredb($release, $predb, string $matchType, bool $dryRun, bool $show, Categorize $categorize): bool
    {
        // Get old category name using cache
        $oldCategoryName = $this->getCategoryName($release->categories_id);

        if ($release->searchname === $predb->title) {
            // Names already match, just update predb_id
            if (! $dryRun) {
                Release::where('id', $release->id)->update(['predb_id' => $predb->id]);
            }

            if ($show) {
                $this->colorCLI->primary('═══════════════════════════════════════════════════════════');
                $this->colorCLI->header("Release ID: {$release->id}");
                $this->colorCLI->primary("GUID: {$release->guid}");
                $this->colorCLI->info("Match Type: {$matchType}");
                $this->colorCLI->info("Searchname: {$release->searchname}");
                $this->colorCLI->info("Category: {$oldCategoryName}");
                $this->colorCLI->info("PreDB Title: {$predb->title}");
                $this->colorCLI->info("PreDB Source: {$predb->source}");
                $this->colorCLI->warning('Action: Same name, only updating predb_id');
                if ($dryRun) {
                    $this->colorCLI->info('[DRY RUN - Not actually updated]');
                }
                $this->colorCLI->primary('═══════════════════════════════════════════════════════════');
                echo PHP_EOL;
            }

            return true;
        }

        // Names differ, perform full rename
        $oldName = $release->name;
        $oldSearchName = $release->searchname;
        $newName = $predb->title;
        $newCategory = null;
        $newCategoryName = $oldCategoryName;

        if (! $dryRun) {
            // Update release
            Release::where('id', $release->id)->update([
                'name' => $newName,
                'searchname' => $newName,
                'isrenamed' => 1,
                'predb_id' => $predb->id,
            ]);

            // Recategorize if needed
            $newCategory = $categorize->determineCategory($release->groups_id, $newName);
            if ($newCategory !== null && is_int($newCategory) && $newCategory !== $release->categories_id) {
                Release::where('id', $release->id)->update(['categories_id' => $newCategory]);
                $newCategoryName = $this->getCategoryName($newCategory);
            }

            // Update search indexes
            if (config('nntmux.elasticsearch_enabled') === true) {
                (new ElasticSearchSiteSearch)->updateRelease($release->id);
            } else {
                (new ManticoreSearch)->updateRelease($release->id);
            }
        } else {
            // Dry run: calculate what the new category would be
            $newCategory = $categorize->determineCategory($release->groups_id, $newName);
            if ($newCategory !== null && is_int($newCategory) && $newCategory !== $release->categories_id) {
                $newCategoryName = $this->getCategoryName($newCategory);
            }
        }

        $this->renamed++;

        if ($show) {
            $this->colorCLI->primary('═══════════════════════════════════════════════════════════');
            $this->colorCLI->header("Release ID: {$release->id}");
            $this->colorCLI->primary("GUID: {$release->guid}");
            $this->colorCLI->info("Match Type: {$matchType}");
            echo PHP_EOL;
            $this->colorCLI->warning("OLD Searchname: {$oldSearchName}");
            $this->colorCLI->warning("OLD Category:   {$oldCategoryName}");
            echo PHP_EOL;
            $this->colorCLI->header("NEW Searchname: {$newName}");
            if ($newCategory !== null && $newCategory !== $release->categories_id) {
                $this->colorCLI->header("NEW Category:   {$newCategoryName}");
            } else {
                $this->colorCLI->info("NEW Category:   {$newCategoryName} (unchanged)");
            }
            echo PHP_EOL;
            $this->colorCLI->info("PreDB Source: {$predb->source}");
            if ($dryRun) {
                $this->colorCLI->info('[DRY RUN - Not actually updated]');
            }
            $this->colorCLI->primary('═══════════════════════════════════════════════════════════');
            echo PHP_EOL;
        }

        return true;
    }

    /**
     * Clean release name for matching.
     */
    protected function cleanReleaseName(string $name): string
    {
        // Remove common release group tags and clean up
        $cleaned = trim($name);

        // Remove leading/trailing dots, dashes, underscores
        $cleaned = trim($cleaned, '._- ');

        // Replace multiple spaces with single space
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);

        return $cleaned;
    }

    /**
     * Get category name with caching to avoid repeated DB lookups.
     */
    protected function getCategoryName(int $categoryId): string
    {
        if (! isset($this->categoryCache[$categoryId])) {
            $category = Category::query()->where('id', $categoryId)->first(['title']);
            $this->categoryCache[$categoryId] = $category ? $category->title : "Unknown (ID: {$categoryId})";
        }

        return $this->categoryCache[$categoryId];
    }
}
